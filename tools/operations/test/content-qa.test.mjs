import test from 'node:test';
import assert from 'node:assert/strict';
import { createPublicKey, verify } from 'node:crypto';
import { readFileSync } from 'node:fs';
import { contentQaFixture, makePlan, original, target, profile } from './fixtures/content.mjs';
import { contentSchemas, validateContentPlan, validateContentMigration, contentSnapshot, contentPaths, contentPlanDescriptor } from '../src/content-contract.mjs';
import { contentCandidate, verifyContentReceipt } from '../src/content-qa.mjs';
import { delegationCanonical, delegationDigest } from '../src/delegation.mjs';

test('PHP provider public content vector has identical semantic hashes, descriptor and Ed25519 signature in Node', () => {
  const vector = JSON.parse(readFileSync(new URL('./fixtures/content-v1.json', import.meta.url)));
  assert.equal(delegationDigest(vector.before_snapshot), vector.before_sha256);
  assert.equal(delegationDigest(vector.candidate_snapshot), vector.candidate_sha256);
  assert.equal(delegationDigest(vector.migration_bundle), vector.migration_artifact_sha256);
  assert.equal(delegationDigest(vector.plan_descriptor), vector.plan_digest);
  const descriptor = contentPlanDescriptor({ ...vector.plan_descriptor, migration_artifact: { sha256: vector.migration_artifact_sha256 }, profile: { sha256: vector.plan_descriptor.profile_sha256 } });
  assert.deepEqual(descriptor, vector.plan_descriptor);
  const bytes = Buffer.from(vector.receipt_envelope.payload_base64, 'base64');
  assert.equal(bytes.toString('utf8'), delegationCanonical(vector.receipt_claims));
  const key = createPublicKey({ key: Buffer.concat([Buffer.from('302a300506032b6570032100', 'hex'), Buffer.from(vector.public_key_base64, 'base64')]), format: 'der', type: 'spki' });
  assert.equal(verify(null, Buffer.concat([Buffer.from('mrn-content-qa-v1:'), bytes]), key, Buffer.from(vector.receipt_envelope.signature_base64, 'base64')), true);
  assert.equal(vector.rollback_case.rollback_snapshot_sha256, vector.candidate_sha256);
});

test('frozen content schemas reject unknown fields, invalid Unicode and UTF-8 byte overflow', () => {
  assert.throws(() => contentSchemas['inspect-content-v1'].input.parse({ site_id: 1, site_url: original.site_url, target, email: 'caller@example.test' }));
  assert.throws(() => contentSnapshot({ ...original, post_content: '\ud800' }));
  assert.throws(() => contentSnapshot({ ...original, post_content: '😀'.repeat(3000) }), { code: 'CONTENT_SIZE' });
  for (const path of ['//outsider.test/', '/\\outsider.test/', '/sample/../private/', '/%2foutsider/', '/sample/?token=private'])
    assert.throws(() => contentPaths([path], original.site_url), { code: 'CONTENT_PUBLIC_PATH' });
});

test('content plans bind one changed field, baseline, profile, migration and rollback identity', () => {
  const { plan, migration } = makePlan();
  const context = { site: { id: 1, url: original.site_url }, target, profile,
    inspected: { snapshot: original, affected_paths: plan.affected_paths, stable_selector: plan.stable_selector } };
  assert.equal(validateContentPlan(plan, context).operation_id, plan.operation_id);
  assert.equal(validateContentMigration(plan, migration).bundle.candidate_sha256, plan.candidate_sha256);
  for (const change of [{ candidate_snapshot: { ...plan.candidate_snapshot, post_title: 'Unrequested' } }, { before_sha256: '0'.repeat(64) },
    { site_id: 2 }, { profile: { ...profile, runtime_context_sha256: '0'.repeat(64) } }, { rollback_operation_id: plan.operation_id }])
    assert.throws(() => validateContentPlan({ ...plan, ...change }, context));
  assert.throws(() => validateContentMigration(plan, { ...migration, bundle: { ...migration.bundle, adapter_version: '2' } }));
});

test('protected content receipts reject code signatures, mismatched fields, stale receipts and noncanonical claims', () => {
  const f = contentQaFixture(), claims = f.claims(f.request, 7);
  assert.equal(verifyContentReceipt(f.envelope(claims), f.request, f.policy, 7).claims.kind, 'content');
  for (const change of [{ kind: 'file' }, { target: { ...target, field: 'post_title' } }, { affected_paths: ['/other/'] },
    { profile_sha256: '0'.repeat(64) }, { runtime_context_sha256: '0'.repeat(64) }, { migration_artifact_sha256: '0'.repeat(64) },
    { plan_digest: '0'.repeat(64) }, { site_id: 2 }, { expires_at: 1 }, { receipt_id: '0'.repeat(32) }, { engine_commit: '0'.repeat(40) }])
    assert.throws(() => verifyContentReceipt(f.envelope({ ...claims, ...change }), f.request, f.policy, 7));
  assert.throws(() => verifyContentReceipt(f.envelope(claims, { prefix: 'mrn-code-qa-v1:' }), f.request, f.policy, 7), { code: 'QA_SIGNATURE' });
  assert.throws(() => verifyContentReceipt(f.envelope(claims, { canonical: false }), f.request, f.policy, 7), { code: 'QA_CANONICAL' });
  assert.throws(() => contentCandidate({ ...f.request, new_value: 'Different' }), { code: 'CONTENT_QA_SNAPSHOT' });
});

test('content QA cannot expand company qualification or omit the exact render assertion', () => {
  const f = contentQaFixture();
  assert.equal(f.qa.qualifyRequest(f.request).operation_id, f.request.operation_id);
  assert.throws(() => f.qa.profile('https://client.test', target, profile), { code: 'CONTENT_PROFILE_UNQUALIFIED' });
  assert.throws(() => f.qa.profile(original.site_url, { ...target, post_id: 43 }, profile), { code: 'CONTENT_TARGET_UNQUALIFIED' });
  assert.throws(() => f.qa.profile(original.site_url, target, { ...profile, ownership: 'builder' }), { code: 'CONTENT_PROFILE_UNQUALIFIED' });
  assert.equal(f.qa.workflowFile(), 'content-qa.yml');
});
