import { readFileSync } from 'node:fs';
import { requireThat } from './contracts.mjs';
import { delegationCanonical, delegationDigest } from './delegation.mjs';
import { contentPaths } from './content-contract.mjs';
import { compileManagementSchema } from './content-management-schema.mjs';

// A separate adapter, not a replacement for content-v1 or its QA authority.
// Structured read workflows enforce it. Management writes and their distinct
// protected signer remain unregistered until their runtime qualifies.
export const managementContract = JSON.parse(readFileSync(new URL('../contracts/content-management-v1.json', import.meta.url)));
requireThat(managementContract.adapter === 'content-management-v1' && managementContract.qa_purpose === 'mrn-content-management-qa-v1:',
  'CONTENT_MANAGEMENT_CONTRACT', 'Unsupported management adapter or QA purpose.');
export const managementSchemas = Object.fromEntries(Object.entries(managementContract.abilities)
  .map(([name, value]) => [name, { input: compileManagementSchema(value.input), output: compileManagementSchema(value.output) }]));
export const managementTypes = Object.fromEntries(Object.keys(managementContract.$defs)
  .map(name => [name, compileManagementSchema({ $ref: `#/$defs/${name}`, $defs: managementContract.$defs })]));
const same = (a, b) => delegationDigest(a) === delegationDigest(b);
const insist = condition => requireThat(condition, 'CONTENT_MANAGEMENT_MISMATCH', 'Content evidence does not match the exact requested record, change or qualified profile.');

export function managementSnapshot(value) {
  const parsed = managementTypes.snapshot.parse(value);
  requireThat(Buffer.byteLength(delegationCanonical(parsed)) <= managementContract.limits.snapshot_bytes,
    'CONTENT_SIZE', 'The complete management snapshot exceeds its qualified bound.');
  return parsed;
}
export function managementCandidateHash(value) {
  const snapshot = managementSnapshot(value);
  delete snapshot.post_id; delete snapshot.unrelated_meta_sha256; delete snapshot.post_record_sha256;
  return delegationDigest(snapshot);
}
export function validateManagementRead(value, { site, target }) {
  target = managementTypes.target.parse(target);
  const result = managementSchemas['read-content-record-v1'].output.parse(value), snapshot = managementSnapshot(result.snapshot);
  insist(snapshot.site_id === site.id && snapshot.site_url === site.url
    && snapshot.post_type === target.post_type && snapshot.post_id === target.post_id
    && result.snapshot_sha256 === delegationDigest(snapshot));
  contentPaths(result.affected_paths, site.url);
  return result;
}

const findField = (definitions, key) => {
  const matches = definitions.filter(field => field.key === key);
  insist(matches.length === 1); return matches[0];
};
function replacement(field, previous, value) {
  const type = field.type;
  if (['text', 'textarea', 'wysiwyg', 'url', 'email'].includes(type)) insist(typeof value === 'string');
  else if (type === 'number') insist(Number.isSafeInteger(value) || typeof value === 'string'
    && /^-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?(?:e[+-]?[0-9]+)?$/.test(value));
  else if (type === 'true_false') insist([0, 1, '0', '1'].includes(value));
  else if (['select', 'radio', 'checkbox', 'button_group'].includes(type)) {
    const many = type === 'checkbox' || !!field.multiple, values = many ? value : [value];
    insist(Array.isArray(values) && (!many || new Set(values).size === values.length)
      && values.every(item => typeof item === 'string' && (field.choices ?? []).some(choice => choice.key === item)));
  } else insist(false);
  if (field.required) insist(value !== '' && value !== null && !(Array.isArray(value) && !value.length));
  return typeof previous === 'string' && ['number', 'true_false'].includes(type) ? String(value) : value;
}
function applyLeaf(field, container, key, path, value, depth = 0) {
  insist(depth <= 12 && container !== null && typeof container === 'object' && Object.hasOwn(container, key));
  if (!path.length) { container[key] = replacement(field, container[key], value); return; }
  const current = container[key];
  if (field.type === 'group') {
    const [next, ...remaining] = path; insist(typeof next === 'string' && !Array.isArray(current));
    applyLeaf(findField(field.sub_fields ?? [], next), current, next, remaining, value, depth + 1); return;
  }
  insist(['repeater', 'flexible_content'].includes(field.type));
  const [row, next, ...remaining] = path;
  insist(Number.isSafeInteger(row) && row >= 0 && Array.isArray(current) && row < current.length && typeof next === 'string');
  let fields = field.sub_fields ?? [];
  if (field.type === 'flexible_content') {
    const layouts = (field.layouts ?? []).filter(layout => layout.name === current[row]?.acf_fc_layout);
    insist(layouts.length === 1); fields = layouts[0].sub_fields ?? [];
  }
  applyLeaf(findField(fields, next), current[row], next, remaining, value, depth + 1);
}
export function managementCandidate(before, request) {
  const snapshot = managementSnapshot(before), input = managementTypes.request.parse(request);
  insist(input.site_id === snapshot.site_id && input.site_url === snapshot.site_url
    && input.post_type === snapshot.post_type && input.post_id === snapshot.post_id && input.before_sha256 === delegationDigest(snapshot));
  const changes = input.changes ?? {}, patches = input.acf_patches ?? [];
  insist(Object.keys(changes).length + patches.length > 0);
  requireThat(Buffer.byteLength(delegationCanonical([input.changes ?? [], patches])) <= managementContract.limits.patch_bytes,
    'CONTENT_SIZE', 'The complete management patch exceeds its qualified bound.');
  if (!input.post_id) {
    insist(Array.isArray(snapshot.core) && snapshot.core.length === 0 && patches.length === 0
      && Object.hasOwn(changes, 'post_title') && Object.hasOwn(changes, 'post_name') && changes.post_status === 'draft');
    snapshot.core = { post_title: '', post_name: '', post_content: '', post_excerpt: '', post_status: 'draft' };
  }
  for (const [key, value] of Object.entries(changes)) { insist(Object.hasOwn(snapshot.core, key)); snapshot.core[key] = value; }
  const paths = new Set();
  for (const patch of patches) {
    const hash = delegationDigest(patch.path); insist(!paths.has(hash)); paths.add(hash);
    const [key, ...path] = patch.path; insist(typeof key === 'string');
    applyLeaf(findField(snapshot.acf_definitions, key), snapshot.acf_values, key, path, patch.value);
  }
  insist(managementCandidateHash(snapshot) !== managementCandidateHash(before));
  return managementSnapshot(snapshot);
}

export function validateManagementEvidence({ review, migration, read }, { site, target, request, profile, rollbackOf = '', now = Date.now() }) {
  review = managementTypes.review.parse(review); migration = managementTypes.migration.parse(migration);
  request = managementSchemas['preview-content-edit-v1'].input.parse(request);
  profile = managementTypes.profile.parse(profile); read = validateManagementRead(read, { site, target });
  const descriptor = { ...review }; delete descriptor.digest;
  const expectedRequest = { site_id: request.site_id, site_url: request.site_url, post_type: request.post_type,
    post_id: request.post_id, before_sha256: request.expected_snapshot_sha256,
    ...(request.changes && Object.keys(request.changes).length ? { changes: request.changes } : {}),
    ...(Object.hasOwn(request, 'acf_patches') ? { acf_patches: request.acf_patches } : {}) };
  const candidate = managementCandidate(read.snapshot, expectedRequest);
  insist(review.site_id === site.id && review.site_url === site.url && same(review.target, target)
    && same(review.before_snapshot, read.snapshot) && same(review.candidate_snapshot, candidate)
    && review.before_sha256 === read.snapshot_sha256 && review.candidate_sha256 === managementCandidateHash(candidate)
    && same(review.profile, profile) && review.rollback_operation_id === rollbackOf
    && review.digest === delegationDigest(descriptor)
    && review.migration_artifact.ref === `mrn-content://management/${review.operation_id}`);
  insist(migration.operation_id === review.operation_id && migration.reason === request.reason && same(migration.request, expectedRequest)
    && same(migration.rollback_snapshot, read.snapshot) && same(migration.candidate_snapshot, candidate)
    && migration.candidate_sha256 === review.candidate_sha256 && delegationDigest(migration) === review.migration_artifact.sha256);
  const at = Math.floor(now / 1000);
  requireThat(review.expires_at > at && review.expires_at <= at + 900, 'PLAN_EXPIRED', 'The management preview expired or exceeds its supported lifetime.');
  contentPaths(review.affected_paths, site.url);
  return { review, migration, read };
}

export function validateManagementOutcome(value, installedRead, review) {
  review = managementTypes.review.parse(review);
  const state = managementTypes.state.parse(value);
  requireThat(state.state === 'completed', 'CONTENT_OUTCOME_UNVERIFIED', 'Retain the content lock and inspect the uncertain outcome; do not retry.');
  insist(state.operation_id === review.operation_id && state.site_id === review.site_id && state.site_url === review.site_url
    && (!review.target.post_id || state.post_id === review.target.post_id)
    && state.rollback_available === (review.target.post_id > 0 && review.rollback_operation_id === ''));
  const read = validateManagementRead(installedRead, { site: { id: review.site_id, url: review.site_url },
    target: { ...review.target, post_id: state.post_id } });
  insist(state.installed_sha256 === read.snapshot_sha256 && same(read.snapshot.core, review.candidate_snapshot.core)
    && same(read.snapshot.runtime, review.candidate_snapshot.runtime));
  // Creation can activate new ACF location rules only after WordPress assigns
  // the ID. Retain that complete fresh read; it grants no future field access.
  if (review.target.post_id) insist(managementCandidateHash(read.snapshot) === review.candidate_sha256);
  return { state, read, requiresFreshQualification: review.target.post_id === 0 };
}
