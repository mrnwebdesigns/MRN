import { createPublicKey, verify } from 'node:crypto';
import { inflateRawSync } from 'node:zlib';
import { z } from 'zod';
import { absoluteUrl, digest, requireThat, sha } from './contracts.mjs';
import { delegationCanonical, delegationDigest } from './delegation.mjs';
import { ProtectedCodeQa } from './code-qa.mjs';
import { receiptFromZip } from './receipt-zip.mjs';
import { managementTypes, managementSchemas, validateManagementEvidence } from './content-management-contract.mjs';
import { verifyManagementReceipt } from './content-management-qa.mjs';
import { acfPatchSchema, acfScalar, managedTargetSchema } from './content.mjs';
import { contentEnvelopeSchema, contentProfileSchema, contentSnapshotSchema, contentSnapshot, contentTargetSchema, contentSame, contentPaths, contentSchemas, contentPlanDescriptor } from './content-contract.mjs';

const commit = z.string().regex(/^[a-f0-9]{40}$/);
const uuid = z.string().regex(/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/);
const planOutput = contentSchemas['prepare-content-change-v1'].output.shape;
const providerSource = z.object(Object.fromEntries(['child/content-acf.php', 'child/content-fields.php', 'child/content-records.php',
  'includes/class-mrn-mainwp-content-management.php', 'includes/class-mrn-mainwp-content-source.php', 'release-source.json'].map(k => [k, sha]))).strict();
const managementProfile = z.object({ siteUrl: absoluteUrl, profile: managementTypes.profile,
  targets: z.array(z.object({ target: managedTargetSchema, selector: z.string().min(1).max(256),
    acfPath: acfPatchSchema.shape.path, providerSource }).strict()).min(1) }).strict();
export const contentQaPolicySchema = z.object({ version: z.literal(1), repository: z.literal('mrnwebdesigns/mrn-code-qa-runner'), workflowCommit: commit,
  actor: z.string().regex(/^[A-Za-z0-9-]+\[bot\]$/), keyId: z.string().regex(/^[A-Za-z0-9_-]{1,64}$/), publicKeyBase64: z.string().length(44), engineCommit: commit,
  profiles: z.array(z.union([z.object({ siteUrl: absoluteUrl, profile: contentProfileSchema,
    targets: z.array(z.object({ target: contentTargetSchema, selector: z.string().min(1).max(256) }).strict()).min(1) }).strict(), managementProfile])).min(1),
}).strict();
export const managementQaRequest = z.object({ schema: z.literal(1), request_id: z.string().regex(/^[a-f0-9]{32}$/), kind: z.literal('content-management'),
  preview: managementSchemas['preview-content-edit-v1'].input, review: managementTypes.review,
  migration: managementTypes.migration, read: managementSchemas['read-content-record-v1'].output }).strict();
export function managementQaInput(evidence, preview, requestId) {
  return managementQaRequest.parse({ schema: 1, request_id: requestId, kind: 'content-management', preview, ...evidence });
}
export const contentQaRequest = z.object({ schema: z.literal(1), request_id: z.string().regex(/^[a-f0-9]{32}$/), kind: z.literal('content'),
  operation_id: uuid, site_id: z.number().int().positive().safe(), site_url: absoluteUrl, target: contentTargetSchema,
  before_snapshot: contentSnapshotSchema, new_value: z.string().max(8192), before_sha256: sha, candidate_sha256: sha, plan_digest: sha,
  rollback_operation_id: z.union([uuid, z.literal('')]), profile: contentProfileSchema, runtime_url: absoluteUrl,
  affected_paths: planOutput.affected_paths, stable_selector: planOutput.stable_selector, migration_artifact: planOutput.migration_artifact,
}).strict();
export function contentQaInput(plan, requestId) {
  return contentQaRequest.parse({ schema: 1, request_id: requestId, kind: 'content', operation_id: plan.operation_id,
    site_id: plan.site_id, site_url: plan.site_url, target: plan.target, before_snapshot: plan.before_snapshot,
    new_value: plan.candidate_snapshot[plan.target.field], before_sha256: plan.before_sha256, candidate_sha256: plan.candidate_sha256,
    plan_digest: plan.digest, rollback_operation_id: plan.rollback_operation_id, profile: plan.profile,
    runtime_url: plan.profile.runtime_url, affected_paths: plan.affected_paths, stable_selector: plan.stable_selector, migration_artifact: plan.migration_artifact });
}
export function contentCandidate(request) {
  const before = contentSnapshot(request.before_snapshot), after = contentSnapshot({ ...before, [request.target.field]: request.new_value });
  requireThat(before.site_id === request.site_id && before.site_url === request.site_url && contentSame(before.target, request.target)
    && delegationDigest(before) === request.before_sha256 && delegationDigest(after) === request.candidate_sha256
    && request.before_sha256 !== request.candidate_sha256, 'CONTENT_QA_SNAPSHOT', 'Content QA must receive the exact before and candidate values.');
  const bundle = { schema: 1, migration_id: request.operation_id, site_url: request.site_url, target: request.target, stable_selector: request.stable_selector,
    expected_snapshot_sha256: request.before_sha256, candidate_snapshot: after, candidate_sha256: request.candidate_sha256,
    affected_paths: request.affected_paths, rollback_snapshot_sha256: request.before_sha256, adapter_version: '1' };
  requireThat(request.migration_artifact.ref === `mrn-content://migrations/${request.operation_id}` && delegationDigest(bundle) === request.migration_artifact.sha256,
    'CONTENT_QA_MIGRATION', 'Content QA must reconstruct the exact immutable migration.');
  requireThat(delegationDigest(contentPlanDescriptor(request)) === request.plan_digest, 'CONTENT_QA_PLAN', 'Content QA must bind the exact canonical descriptor.');
  return after;
}
const claimsSchema = z.object({ schema: z.literal(1), issuer: z.literal('mrn-qa-engine'), audience: z.literal('https://wpcontrol.mrndev.io'),
  kind: z.literal('content'), result: z.literal('Pass'), mode: z.literal('release'), smoke_strict: z.literal(true),
  issued_at: z.number().int().safe(), expires_at: z.number().int().safe(), receipt_id: z.string().regex(/^[a-f0-9]{32}$/),
  site_id: z.number().int().positive().safe(), site_url: z.string(), target: contentTargetSchema,
  before_sha256: sha, candidate_sha256: sha, plan_digest: sha, rollback_operation_id: z.union([uuid, z.literal('')]), qa_report_sha256: sha,
  engine_commit: commit, runtime_url: z.string(), runtime_context_sha256: sha, profile_sha256: sha,
  affected_paths: planOutput.affected_paths, migration_artifact_sha256: sha,
}).strict();
export function verifyContentReceipt(envelope, request, policy, runId, now = Date.now()) {
  envelope = contentEnvelopeSchema.parse(envelope); request = contentQaRequest.parse(request); contentCandidate(request);
  requireThat(Buffer.byteLength(JSON.stringify(envelope)) <= 16384, 'QA_SIZE', 'Content receipt exceeds its wire limit.');
  const bytes = Buffer.from(envelope.payload_base64, 'base64'), signature = Buffer.from(envelope.signature_base64, 'base64'), key = Buffer.from(policy.publicKeyBase64, 'base64');
  requireThat(key.length === 32 && key.toString('base64') === policy.publicKeyBase64 && envelope.key_id === policy.keyId, 'QA_KEY', 'The content receipt uses a different protected signer.');
  requireThat(bytes.toString('base64') === envelope.payload_base64 && signature.length === 64 && signature.toString('base64') === envelope.signature_base64
    && verify(null, Buffer.concat([Buffer.from('mrn-content-qa-v1:'), bytes]), createPublicKey({ key: Buffer.concat([Buffer.from('302a300506032b6570032100', 'hex'), key]), type: 'spki', format: 'der' }), signature),
  'QA_SIGNATURE', 'Content requires an independently signed content receipt; code and source-review receipts cannot substitute.');
  const claims = claimsSchema.parse(JSON.parse(bytes.toString('utf8')));
  requireThat(bytes.equals(Buffer.from(delegationCanonical(claims))), 'QA_CANONICAL', 'Content QA claims must use strict canonical JSON.');
  const at = Math.floor(now / 1000);
  requireThat(claims.issued_at <= at + 30 && claims.issued_at >= at - 900 && claims.expires_at > at && claims.expires_at <= claims.issued_at + 900, 'QA_EXPIRED', 'Content qualification expired.');
  requireThat(claims.receipt_id === digest(`${runId}:1:${request.request_id}`).slice(0, 32) && claims.engine_commit === policy.engineCommit, 'QA_PROVENANCE', 'Content QA belongs to a different protected execution.');
  for (const field of ['site_id', 'site_url', 'before_sha256', 'candidate_sha256', 'plan_digest', 'rollback_operation_id', 'runtime_url'])
    requireThat(claims[field] === request[field], 'QA_CONTEXT', 'Content QA belongs to a different operation or snapshot.');
  requireThat(contentSame(claims.target, request.target) && contentSame(claims.affected_paths, request.affected_paths)
    && claims.profile_sha256 === request.profile.sha256 && claims.runtime_context_sha256 === request.profile.runtime_context_sha256
    && claims.migration_artifact_sha256 === request.migration_artifact.sha256, 'QA_CONTEXT', 'Content QA does not prove the exact profile, render paths and migration.');
  return { envelope, claims };
}
export class ProtectedContentQa extends ProtectedCodeQa {
  workflowFile() { return 'content-qa.yml'; }
  runName(id) { return `MRN Content QA ${id}`; }
  verifyReceipt(envelope, request, runId, reportSha256) {
    if (request.kind !== 'content-management') return verifyContentReceipt(envelope, request, this.policy, runId);
    request = this.qualifyRequest(request);
    const p = request.review;
    return verifyManagementReceipt({ envelope, evidence: request,
      expected: { site: { id: p.site_id, url: p.site_url }, target: p.target, request: request.preview, profile: p.profile, rollbackOf: p.rollback_operation_id },
      signer: { keyId: this.policy.keyId, publicKeyBase64: this.policy.publicKeyBase64, engineCommit: this.policy.engineCommit },
      run: { id: runId, requestId: request.request_id, reportSha256 } });
  }
  managementProfile(siteUrl, target, read) {
    const matches = this.policy.profiles.flatMap(p => p.siteUrl === siteUrl && !p.profile.ownership
      ? p.targets.filter(t => contentSame(t.target, target)).map(t => ({ ...t, profile: p.profile })) : []);
    requireThat(matches.length === 1, 'CONTENT_TARGET_UNQUALIFIED', 'This existing ACF record has no unique qualified scalar rendering profile.');
    const match = matches[0], snapshot = read.snapshot;
    const context = delegationDigest({ adapter: 'content-management-v1', runtime: snapshot.runtime,
      acf_definitions: snapshot.acf_definitions, source: match.providerSource });
    requireThat(match.profile.sha256 === context && match.profile.runtime_context_sha256 === context
      && snapshot.core.post_status === 'publish' && read.affected_paths.length > 0,
    'CONTENT_PROFILE_UNQUALIFIED', 'The ACF definitions, runtime, provider sources or public record differ from the trusted profile.');
    contentPaths(read.affected_paths, siteUrl); acfScalar(snapshot, match.acfPath); return match;
  }
  profile(siteUrl, target, descriptor) {
    const matches = this.policy.profiles.filter(p => p.siteUrl === siteUrl && contentSame(p.profile, descriptor));
    requireThat(matches.length === 1 && descriptor.ownership === 'wordpress-post' && descriptor.covered_fields.includes(target.field)
      && descriptor.requires_render_assertions === true, 'CONTENT_PROFILE_UNQUALIFIED', 'MRN services must qualify this exact content ownership and runtime profile.');
    const targets = matches[0].targets.filter(t => contentSame(t.target, target));
    requireThat(targets.length === 1, 'CONTENT_TARGET_UNQUALIFIED', 'This record/field has no qualified rendering assertion.');
    return targets[0];
  }
  qualifyRequest(input) {
    if (input.kind === 'content-management') {
      const request = managementQaRequest.parse(input), p = request.review;
      const qualified = this.managementProfile(p.site_url, p.target, request.read);
      requireThat(!request.preview.changes || Object.keys(request.preview.changes).length === 0, 'CONTENT_ACF_SCALAR', 'The ACF workflow changes one scalar leaf and preserves all core fields.');
      requireThat(request.preview.acf_patches?.length === 1, 'CONTENT_ACF_SCALAR', 'Qualify one existing ACF scalar leaf at a time.');
      const patch = acfPatchSchema.parse(request.preview.acf_patches[0]);
      requireThat(contentSame(patch.path, qualified.acfPath) && patch.value.isWellFormed() && !patch.value.includes('\0')
        && Buffer.byteLength(patch.value) <= 8192, 'CONTENT_ACF_PATH', 'The ACF scalar path and bounded UTF-8 value must match the trusted rendering profile.');
      validateManagementEvidence(request, { site: { id: p.site_id, url: p.site_url }, target: p.target,
        request: request.preview, profile: qualified.profile, rollbackOf: p.rollback_operation_id });
      requireThat(contentSame(request.read.affected_paths, p.affected_paths), 'CONTENT_MANAGEMENT_MISMATCH', 'The ACF rendering paths changed in the proposal.');
      requireThat(Buffer.byteLength(JSON.stringify(request)) <= 48000, 'QA_SIZE', 'The complete ACF request exceeds the protected dispatch bound. No fields were truncated.');
      return request;
    }
    const request = contentQaRequest.parse(input); contentCandidate(request);
    requireThat(request.runtime_url === request.profile.runtime_url && /^https:\/\//.test(request.runtime_url), 'QA_CONTEXT', 'The runtime must match the trusted content profile.');
    this.profile(request.site_url, request.target, request.profile); contentPaths(request.affected_paths, request.site_url);
    requireThat(Buffer.byteLength(JSON.stringify(request)) <= 48000, 'QA_SIZE', 'The exact content request exceeds the protected dispatch size. No content was truncated.');
    return request;
  }
  async receipt(ticket, request, authorize) {
    if (request.kind !== 'content-management') return super.receipt(ticket, request, authorize);
    this.qualifyRequest(request);
    const status = await this.status(ticket, authorize); if (status.status !== 'receipt_available') return status;
    const api = await this.github.scoped(this.policy.repository, 'qaRead', authorize);
    const listing = await api(`/actions/runs/${ticket.runId}/artifacts?per_page=100`);
    requireThat(Number.isSafeInteger(listing.total_count) && listing.total_count <= 100 && Array.isArray(listing.artifacts), 'QA_ARTIFACT', 'Qualification artifacts could not be enumerated completely.');
    const matches = listing.artifacts.filter(a => a.name === `qualification-${ticket.runId}-1`);
    requireThat(matches.length === 1 && !matches[0].expired && Number.isSafeInteger(matches[0].id) && matches[0].size_in_bytes <= 65536
      && matches[0].workflow_run?.id === ticket.runId && matches[0].workflow_run?.head_sha === ticket.workflowCommit,
    'QA_ARTIFACT', 'The protected qualification artifact is missing, expired or mismatched.');
    const reportSha256 = managementReportFromZip(await api.artifact(matches[0].id), request);
    const envelope = receiptFromZip(await api.artifact(status.artifactId));
    return { status: 'qualified', ...this.verifyReceipt(envelope, request, ticket.runId, reportSha256), reportSha256, url: ticket.url };
  }
}

// Read exactly the protected workflow's three data members in memory. No file
// extraction, path traversal, ZIP64, encryption, duplicate names or unbounded
// inflation is admitted. The report hash comes from this independently scoped
// run artifact, never from unverified receipt claims.
export function managementReportFromZip(zip, request) {
  const limits = { 'qa.md': 1024 * 1024, 'qualification.json': 16384, 'request.json': 48000 };
  const check = condition => requireThat(condition, 'QA_ARCHIVE', 'Invalid bounded management qualification archive.');
  check(Buffer.isBuffer(zip) && zip.length >= 100 && zip.length <= 65536);
  const end = zip.length - 22;
  check(zip.readUInt32LE(end) === 0x06054b50 && zip.readUInt16LE(end + 20) === 0 && zip.readUInt16LE(end + 4) === 0
    && zip.readUInt16LE(end + 6) === 0 && zip.readUInt16LE(end + 8) === 3 && zip.readUInt16LE(end + 10) === 3);
  const directory = zip.readUInt32LE(end + 16), size = zip.readUInt32LE(end + 12);
  check(directory > 0 && directory + size === end); let cursor = directory; const files = {}, ranges = [];
  for (let index = 0; index < 3; index++) {
    check(cursor + 46 <= end && zip.readUInt32LE(cursor) === 0x02014b50);
    const flags = zip.readUInt16LE(cursor + 8), method = zip.readUInt16LE(cursor + 10), crc = zip.readUInt32LE(cursor + 16);
    const compressed = zip.readUInt32LE(cursor + 20), length = zip.readUInt32LE(cursor + 24), names = zip.readUInt16LE(cursor + 28);
    const extras = zip.readUInt16LE(cursor + 30), comments = zip.readUInt16LE(cursor + 32), offset = zip.readUInt32LE(cursor + 42);
    check(cursor + 46 + names + extras + comments <= end);
    const name = zip.subarray(cursor + 46, cursor + 46 + names).toString('utf8');
    check(Object.hasOwn(limits, name) && !Object.hasOwn(files, name) && (flags & ~0x808) === 0 && [0, 8].includes(method)
      && length > 0 && length <= limits[name] && compressed <= 65536 && offset + 30 <= directory);
    check(zip.readUInt32LE(offset) === 0x04034b50 && zip.readUInt16LE(offset + 6) === flags && zip.readUInt16LE(offset + 8) === method
      && zip.readUInt16LE(offset + 26) === names && zip.subarray(offset + 30, offset + 30 + names).toString('utf8') === name);
    const start = offset + 30 + names + zip.readUInt16LE(offset + 28);
    check(start + compressed <= directory);
    let bytes;
    try { bytes = method === 0 ? zip.subarray(start, start + compressed) : inflateRawSync(zip.subarray(start, start + compressed), { maxOutputLength: limits[name] }); }
    catch { check(false); }
    check(bytes.length === length); let checksum = 0xffffffff;
    for (const byte of bytes) { checksum ^= byte; for (let bit = 0; bit < 8; bit++) checksum = (checksum >>> 1) ^ ((checksum & 1) ? 0xedb88320 : 0); }
    check(((checksum ^ 0xffffffff) >>> 0) === crc);
    files[name] = bytes; ranges.push({ offset, end: start + compressed }); cursor += 46 + names + extras + comments;
  }
  check(cursor === end); ranges.sort((a, b) => a.offset - b.offset); check(ranges[0].offset === 0);
  for (let index = 0; index < ranges.length; index++) check(ranges[index].end <= (ranges[index + 1]?.offset ?? directory));
  check(files['request.json'].equals(Buffer.from(delegationCanonical(request))));
  return digest(files['qa.md']);
}
