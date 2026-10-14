import { generateKeyPairSync, randomUUID, sign } from 'node:crypto';
import { digest } from '../../src/contracts.mjs';
import { delegationCanonical, delegationDigest } from '../../src/delegation.mjs';
import { contentQaInput, ProtectedContentQa } from '../../src/content-qa.mjs';
import { contentPlanDescriptor } from '../../src/content-contract.mjs';

export const target = { kind: 'post', post_type: 'page', post_id: 42, field: 'post_content' };
export const profile = { id: 'fixture-only', sha256: 'a'.repeat(64), runtime_url: 'https://mrn-qa.internal:9443', runtime_context_sha256: 'b'.repeat(64),
  ownership: 'wordpress-post', covered_fields: ['post_title', 'post_excerpt', 'post_content'], requires_render_assertions: true };
export const original = { schema: 1, site_id: 1, site_url: 'https://example.test', target, post_status: 'publish', post_name: 'sample-page',
  post_title: 'Sample', post_excerpt: 'Summary', post_content: '<p>Original &amp; safe.</p>' };
export function makePlan(before = original, value = '<p>Updated &amp; safe.</p>', rollback = '') {
  const candidate = { ...before, [before.target.field]: value };
  const plan = { operation_id: randomUUID(), digest: 'c'.repeat(64), site_id: before.site_id, site_url: before.site_url, target: before.target,
    before_snapshot: before, candidate_snapshot: candidate, before_sha256: delegationDigest(before), candidate_sha256: delegationDigest(candidate),
    affected_paths: ['/sample-page/'], stable_selector: { post_type: 'page', post_path: 'sample-page' }, profile,
    rollback_operation_id: rollback, expires_at: Math.floor(Date.now() / 1000) + 900 };
  const bundle = { schema: 1, migration_id: plan.operation_id, site_url: plan.site_url, target: plan.target, stable_selector: plan.stable_selector,
    expected_snapshot_sha256: plan.before_sha256, candidate_snapshot: candidate, candidate_sha256: plan.candidate_sha256,
    affected_paths: plan.affected_paths, rollback_snapshot_sha256: plan.before_sha256, adapter_version: '1' };
  plan.migration_artifact = { ref: `mrn-content://migrations/${plan.operation_id}`, sha256: delegationDigest(bundle) };
  plan.digest = delegationDigest(contentPlanDescriptor(plan));
  return { plan, migration: { migration_artifact: plan.migration_artifact, bundle } };
}
export function contentQaFixture() {
  const keys = generateKeyPairSync('ed25519');
  const policy = { version: 1, repository: 'mrnwebdesigns/mrn-code-qa-runner', workflowCommit: 'd'.repeat(40), actor: 'fixture[bot]', keyId: 'fixture-content',
    publicKeyBase64: keys.publicKey.export({ format: 'der', type: 'spki' }).subarray(-32).toString('base64'), engineCommit: 'e'.repeat(40),
    profiles: [{ siteUrl: original.site_url, profile, targets: [{ target, selector: 'main .entry-content' }] }] };
  const claims = (request, runId) => ({ schema: 1, issuer: 'mrn-qa-engine', audience: 'https://wpcontrol.mrndev.io', kind: 'content', result: 'Pass', mode: 'release', smoke_strict: true,
    issued_at: Math.floor(Date.now() / 1000), expires_at: Math.floor(Date.now() / 1000) + 900, receipt_id: digest(`${runId}:1:${request.request_id}`).slice(0, 32),
    site_id: request.site_id, site_url: request.site_url, target: request.target, before_sha256: request.before_sha256, candidate_sha256: request.candidate_sha256,
    plan_digest: request.plan_digest, rollback_operation_id: request.rollback_operation_id, qa_report_sha256: 'f'.repeat(64), engine_commit: policy.engineCommit,
    runtime_url: request.runtime_url, runtime_context_sha256: request.profile.runtime_context_sha256, profile_sha256: request.profile.sha256,
    affected_paths: request.affected_paths, migration_artifact_sha256: request.migration_artifact.sha256 });
  const envelope = (value, { prefix = 'mrn-content-qa-v1:', canonical = true } = {}) => {
    const bytes = Buffer.from(canonical ? delegationCanonical(value) : JSON.stringify(value));
    return { key_id: policy.keyId, payload_base64: bytes.toString('base64'), signature_base64: sign(null, Buffer.concat([Buffer.from(prefix), bytes]), keys.privateKey).toString('base64') };
  };
  const qa = new ProtectedContentQa(null, policy);
  return { qa, policy, claims, envelope, request: contentQaInput(makePlan().plan, '1'.repeat(32)) };
}
