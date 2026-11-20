import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { generateKeyPairSync, sign } from 'node:crypto';
import { digest } from '../src/contracts.mjs';
import { delegationCanonical, delegationDigest } from '../src/delegation.mjs';
import { managementCandidateHash, managementContract } from '../src/content-management-contract.mjs';
import { verifyManagementReceipt } from '../src/content-management-qa.mjs';

const vectors = JSON.parse(readFileSync(new URL('./fixtures/content-management-v1.json', import.meta.url)));
function fixture(index = 0) {
  const v = structuredClone(vectors.cases[index]), keys = generateKeyPairSync('ed25519');
  const signer = { keyId: 'management-test', publicKeyBase64: keys.publicKey.export({ type: 'spki', format: 'der' }).subarray(-32).toString('base64'),
    engineCommit: v.qa_claims.engine_commit };
  const run = { id: 12345, requestId: '1'.repeat(32), reportSha256: v.qa_claims.qa_report_sha256 };
  const claims = { ...v.qa_claims, receipt_id: digest(`${run.id}:1:${run.requestId}`).slice(0, 32) };
  const { before_sha256, ...request } = v.migration.request;
  const input = { evidence: { review: v.review, migration: v.migration, read: v.read },
    expected: { site: { id: v.review.site_id, url: v.review.site_url }, target: v.review.target, profile: v.review.profile,
      request: { ...request, expected_snapshot_sha256: before_sha256, reason: v.migration.reason,
        idempotency_key: '77777777-7777-4777-8777-777777777777' } }, signer, run, now: vectors.clock * 1000 };
  const envelope = (value = claims, { purpose = managementContract.qa_purpose, raw } = {}) => {
    const bytes = Buffer.from(raw ?? delegationCanonical(value));
    return { key_id: signer.keyId, payload_base64: bytes.toString('base64'),
      signature_base64: sign(null, Buffer.concat([Buffer.from(purpose), bytes]), keys.privateKey).toString('base64') };
  };
  return { v, input, claims, envelope, verify: (value = claims, options) => verifyManagementReceipt({ ...input, envelope: envelope(value, options) }) };
}

test('management receipts bind the exact core and nested ACF preview, migration and independently identified report', () => {
  const f = fixture(), result = f.verify();
  assert.deepEqual(result.claims, f.claims);
  assert.equal(result.claims.plan_digest, f.v.review.digest);
  assert.equal(result.claims.qa_report_sha256, f.input.run.reportSha256);
  result.claims.target.post_id++;
  assert.deepEqual(f.verify().claims, f.claims);
});

test('new draft QA retains ID zero and semantic candidate hash rather than a later installed record hash', () => {
  const f = fixture(1);
  assert.equal(f.verify().claims.target.post_id, 0);
  assert.notEqual(f.claims.candidate_sha256, f.v.state.installed_sha256);
  for (const change of [{ target: { ...f.claims.target, post_id: f.v.state.post_id } }, { candidate_sha256: f.v.state.installed_sha256 }])
    assert.throws(() => f.verify({ ...f.claims, ...change }), { code: 'QA_CONTEXT' });
});

test('valid signatures cannot authorize another site, target, profile, snapshot, path, rollback or migration', () => {
  const f = fixture();
  for (const change of [{ site_id: 74 }, { site_url: 'https://other.example.invalid' }, { target: { ...f.claims.target, post_id: 8 } },
    { before_sha256: '0'.repeat(64) }, { candidate_sha256: '0'.repeat(64) }, { plan_digest: '0'.repeat(64) },
    { profile_sha256: '0'.repeat(64) }, { runtime_context_sha256: '0'.repeat(64) }, { affected_paths: ['/other/'] },
    { rollback_operation_id: 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa' }, { migration_artifact_sha256: '0'.repeat(64) }])
    assert.throws(() => f.verify({ ...f.claims, ...change }), { code: 'QA_CONTEXT' });
});

test('management verifier rejects legacy purposes, another key, noncanonical JSON and extra signed claims', () => {
  const f = fixture();
  for (const purpose of ['mrn-content-qa-v1:', 'mrn-code-qa-v1:', ''])
    assert.throws(() => f.verify(f.claims, { purpose }), { code: 'QA_SIGNATURE' });
  assert.throws(() => verifyManagementReceipt({ ...f.input, envelope: { ...f.envelope(), key_id: 'other' } }), { code: 'QA_KEY' });
  assert.throws(() => f.verify(f.claims, { raw: JSON.stringify(f.claims, null, 2) }), { code: 'QA_CANONICAL' });
  assert.throws(() => f.verify({ ...f.claims, approved: true }));
  const canonical = delegationCanonical(f.claims);
  assert.throws(() => f.verify(f.claims, { raw: canonical.replace('"site_id":73', '"site_id":73,"site_id":73') }), { code: 'QA_CANONICAL' });
  const other = fixture();
  assert.throws(() => verifyManagementReceipt({ ...f.input, envelope: other.envelope() }), { code: 'QA_SIGNATURE' });
});

test('receipt validity is bounded by the original plan and cannot be renewed by caller time or a fresh signature', () => {
  const f = fixture(), at = vectors.clock;
  for (const change of [{ issued_at: at + 31 }, { issued_at: at - 901 }, { expires_at: at },
    { expires_at: at + 901 }, { issued_at: at + 20, expires_at: at + 10 }])
    assert.throws(() => f.verify({ ...f.claims, ...change }), { code: 'QA_EXPIRED' });
  assert.throws(() => verifyManagementReceipt({ ...f.input, now: (f.v.review.expires_at + 1) * 1000,
    expected: { ...f.input.expected, now: f.input.now }, envelope: f.envelope() }), { code: 'PLAN_EXPIRED' });
  for (const now of [NaN, Infinity, -1])
    assert.throws(() => verifyManagementReceipt({ ...f.input, now, envelope: f.envelope() }), { code: 'QA_CLOCK' });
  const evidence = structuredClone(f.input.evidence);
  evidence.review.expires_at = at + 500;
  const descriptor = { ...evidence.review }; delete descriptor.digest;
  evidence.review.digest = delegationDigest(descriptor);
  assert.throws(() => verifyManagementReceipt({ ...f.input, evidence,
    envelope: f.envelope({ ...f.claims, plan_digest: evidence.review.digest }) }), { code: 'QA_EXPIRED' });
});

test('a receipt from another run, request, engine or report is rejected even with a valid protected signature', () => {
  const f = fixture();
  for (const change of [{ receipt_id: '0'.repeat(32) }, { engine_commit: '0'.repeat(40) }, { qa_report_sha256: '0'.repeat(64) }])
    assert.throws(() => f.verify({ ...f.claims, ...change }), { code: 'QA_PROVENANCE' });
  for (const change of [{ id: f.input.run.id + 1 }, { requestId: '2'.repeat(32) }, { reportSha256: '0'.repeat(64) }])
    assert.throws(() => verifyManagementReceipt({ ...f.input, run: { ...f.input.run, ...change }, envelope: f.envelope() }), { code: 'QA_PROVENANCE' });
});

test('signed and fully rehashed unrequested sibling edits fail independent candidate reconstruction', () => {
  const f = fixture(), evidence = structuredClone(f.input.evidence);
  evidence.review.candidate_snapshot.acf_values.field_untouched = 'Unrequested sibling';
  evidence.migration.candidate_snapshot = structuredClone(evidence.review.candidate_snapshot);
  evidence.review.candidate_sha256 = evidence.migration.candidate_sha256 = managementCandidateHash(evidence.review.candidate_snapshot);
  evidence.review.migration_artifact.sha256 = delegationDigest(evidence.migration);
  const descriptor = { ...evidence.review }; delete descriptor.digest; evidence.review.digest = delegationDigest(descriptor);
  const claims = { ...f.claims, candidate_sha256: evidence.review.candidate_sha256, plan_digest: evidence.review.digest,
    migration_artifact_sha256: evidence.review.migration_artifact.sha256 };
  assert.throws(() => verifyManagementReceipt({ ...f.input, evidence, envelope: f.envelope(claims) }), { code: 'CONTENT_MANAGEMENT_MISMATCH' });
});
