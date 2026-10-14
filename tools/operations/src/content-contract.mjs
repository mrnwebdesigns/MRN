import { readFileSync } from 'node:fs';
import { z } from 'zod';
import { requireThat } from './contracts.mjs';
import { delegationCanonical, delegationDigest } from './delegation.mjs';

export const contentContract = JSON.parse(readFileSync(new URL('../contracts/content-v1.json', import.meta.url), 'utf8'));
export const contentPolicyContract = JSON.parse(readFileSync(new URL('../contracts/content-policy-v2.json', import.meta.url), 'utf8'));

// Compile only the small, checked-in schema vocabulary. Unsupported contract
// changes stop startup instead of silently weakening runtime validation.
function compile(s) {
  const keys = ['type', 'properties', 'required', 'additionalProperties', 'anyOf', 'enum', 'minimum', 'maximum', 'minLength', 'maxLength', 'pattern', 'items', 'minItems', 'maxItems'];
  requireThat(Object.keys(s).every(k => keys.includes(k)), 'CONTENT_CONTRACT', 'Unsupported content wire schema.');
  if (s.anyOf) return z.union(s.anyOf.map(compile));
  if (Array.isArray(s.type)) return z.union(s.type.map(type => compile({ ...s, type })));
  let out;
  if (s.type === 'object') {
    requireThat(s.additionalProperties === false && Array.isArray(s.required), 'CONTENT_CONTRACT', 'Content objects must be closed.');
    out = z.object(Object.fromEntries(Object.entries(s.properties).map(([k, v]) => [k, s.required.includes(k) ? compile(v) : compile(v).optional()]))).strict();
  } else if (s.type === 'array') {
    out = z.array(compile(s.items));
    if (s.minItems !== undefined) out = out.min(s.minItems);
    if (s.maxItems !== undefined) out = out.max(s.maxItems);
  } else if (s.type === 'string') {
    out = z.string();
    if (s.minLength !== undefined) out = out.min(s.minLength);
    if (s.maxLength !== undefined) out = out.max(s.maxLength);
    if (s.pattern) out = out.regex(new RegExp(s.pattern));
    out = out.refine(value => value.isWellFormed() && !value.includes('\0'), 'Invalid content Unicode.');
  } else if (s.type === 'integer') {
    out = z.number().int().safe();
    if (s.minimum !== undefined) out = out.min(s.minimum);
    if (s.maximum !== undefined) out = out.max(s.maximum);
  } else if (s.type === 'boolean') out = z.boolean();
  else if (s.type === 'null') out = z.null();
  else throw new Error('Unsupported content contract type.');
  return s.enum ? out.refine(value => s.enum.includes(value), 'Unsupported content enum.') : out;
}
export const contentSchemas = Object.fromEntries(Object.entries(contentContract.abilities).map(([name, a]) => [name, { input: compile(a.input), output: compile(a.output) }]));
requireThat(contentPolicyContract.ability === 'mrn-mainwp/get-content-policy-v2' && contentPolicyContract.role === 'reader'
  && contentPolicyContract.wrapper === 'read-delegated-operation-v1', 'CONTENT_CONTRACT', 'Unsupported target-aware policy contract.');
contentSchemas['get-content-policy-v2'] = { input: compile(contentPolicyContract.input), output: compile(contentPolicyContract.output) };
export const contentTargetSchema = compile(contentContract.abilities['inspect-content-v1'].input.properties.target);
export const contentProfileSchema = compile(contentContract.abilities['prepare-content-change-v1'].output.properties.profile);
export const contentSnapshotSchema = compile(contentContract.abilities['inspect-content-v1'].output.properties.snapshot);
export const contentEnvelopeSchema = compile(contentContract.qa.envelope);
export const contentSame = (a, b) => delegationDigest(a) === delegationDigest(b);
export function contentPlanDescriptor(plan) {
  return { schema: 1, operation_id: plan.operation_id, site_id: plan.site_id, site_url: plan.site_url, target: plan.target,
    before_sha256: plan.before_sha256, candidate_sha256: plan.candidate_sha256, affected_paths: plan.affected_paths,
    stable_selector: plan.stable_selector, migration_artifact_sha256: plan.migration_artifact.sha256, profile_sha256: plan.profile.sha256,
    rollback_operation_id: plan.rollback_operation_id, adapter_version: '1' };
}
export function contentSnapshot(value) {
  const snapshot = contentSnapshotSchema.parse(value);
  for (const field of ['post_title', 'post_excerpt', 'post_content']) requireThat(Buffer.byteLength(snapshot[field]) <= 8192, 'CONTENT_SIZE', 'Content field exceeds the qualified UTF-8 limit.');
  requireThat(Buffer.byteLength(delegationCanonical(snapshot)) <= 32768, 'CONTENT_SIZE', 'Content snapshot exceeds the qualified limit.');
  return snapshot;
}
export function contentPaths(paths, siteUrl) {
  for (const path of paths) {
    const url = new URL(path, siteUrl);
    requireThat(path.startsWith('/') && !path.startsWith('//') && !/[\\\s?#]/.test(path)
      && !/%(?:00|0a|0d|2f|5c)/i.test(path) && url.origin === new URL(siteUrl).origin && url.pathname === path,
    'CONTENT_PUBLIC_PATH', 'Content verification requires an exact, credential-free public permalink path.');
  }
  return paths;
}
export async function contentCall(session, ability, input) {
  const contract = contentSchemas[ability];
  requireThat(contract, 'CONTENT_CONTRACT', 'Unknown content operation.');
  const args = contract.input.parse(input);
  requireThat(Buffer.byteLength(delegationCanonical(args)) <= 98304, 'CONTENT_SIZE', 'Content request exceeds the delegated limit.');
  return contract.output.parse(await session.call(`mrn_mainwp__${ability.replaceAll('-', '_')}`, args));
}
export function validateContentPlan(plan, { site, target, inspected, profile, rollbackOf = '' }) {
  contentSchemas['prepare-content-change-v1'].output.parse(plan);
  const before = contentSnapshot(plan.before_snapshot), after = contentSnapshot(plan.candidate_snapshot);
  requireThat(plan.site_id === site.id && plan.site_url === site.url && before.site_id === site.id && before.site_url === site.url
    && contentSame(plan.target, target) && contentSame(before.target, target) && contentSame(plan.before_snapshot, inspected.snapshot)
    && contentSame(plan.profile, profile) && contentSame(plan.affected_paths, inspected.affected_paths)
    && contentSame(plan.stable_selector, inspected.stable_selector) && plan.stable_selector.post_type === target.post_type,
  'CONTENT_PLAN_MISMATCH', 'The provider prepared a different target, baseline or qualified profile.');
  requireThat(contentSame(after, { ...before, [target.field]: after[target.field] }) && after[target.field] !== before[target.field]
    && plan.before_sha256 === delegationDigest(before) && plan.candidate_sha256 === delegationDigest(after)
    && plan.rollback_operation_id === rollbackOf && plan.migration_artifact.ref === `mrn-content://migrations/${plan.operation_id}`,
  'CONTENT_PLAN_MISMATCH', 'The provider plan changed another field or has inconsistent snapshot evidence.');
  requireThat(delegationDigest(contentPlanDescriptor(plan)) === plan.digest, 'CONTENT_PLAN_MISMATCH', 'The content descriptor digest is inconsistent.');
  const now = Math.floor(Date.now() / 1000);
  requireThat(plan.expires_at > now && plan.expires_at <= now + 900, 'PLAN_EXPIRED', 'The content proposal is expired or has an unsupported lifetime.');
  contentPaths(plan.affected_paths, site.url);
  return plan;
}
export function validateContentMigration(plan, value) {
  const migration = contentSchemas['get-content-migration-v1'].output.parse(value), b = migration.bundle;
  requireThat(contentSame(migration.migration_artifact, plan.migration_artifact)
    && delegationDigest(b) === plan.migration_artifact.sha256 && b.migration_id === plan.operation_id && b.site_url === plan.site_url
    && contentSame(b.target, plan.target) && contentSame(b.stable_selector, plan.stable_selector)
    && b.expected_snapshot_sha256 === plan.before_sha256 && b.candidate_sha256 === plan.candidate_sha256
    && contentSame(b.candidate_snapshot, plan.candidate_snapshot) && contentSame(b.affected_paths, plan.affected_paths)
    && b.rollback_snapshot_sha256 === plan.before_sha256, 'CONTENT_MIGRATION', 'The retained replayable migration does not match the exact proposal.');
  return migration;
}
