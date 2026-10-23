import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { generateKeyPairSync, sign } from 'node:crypto';
import { digest } from '../src/contracts.mjs';
import { delegationCanonical, delegationDigest } from '../src/delegation.mjs';
import { existingManagementWires } from '../src/management-existing-contracts.mjs';
import { existingWorkerContractSha256, existingWorkerPurpose, existingWorkerUrl,
  verifyExistingManagementWorkerRecord } from '../src/existing-management-worker-contract.mjs';

const vectors = JSON.parse(readFileSync(new URL('./fixtures/content-management-existing-v2.json', import.meta.url)));
const keys = generateKeyPairSync('ed25519');
const refused = { code: 'MANAGEMENT_EXISTING_WORKER_CONTRACT' };
function fixture() {
  const v = vectors.positive.find(row => row.domain === 'evidence' && row.definition === 'manifest_claims').value;
  const context = structuredClone(v.context), provenance = structuredClone(v.run_provenance);
  // The imported vectors prove schema only. Build this separate synthetic
  // handoff's actual hash relations without changing those frozen fixtures.
  context.management_context_sha256 = delegationDigest(context.binding);
  provenance.repository = 'mrnwebdesigns/mrn-code-qa-runner';
  const binding = structuredClone(context.binding), at = binding.created_at * 1000 + 10000;
  const pointer = { dispatch_id: provenance.dispatch_id, context_sha256: delegationDigest(context) };
  const observation = { request: { schema: 2, ...pointer, worker_contract_sha256: existingWorkerContractSha256 },
    context_sha256: pointer.context_sha256, evidence_policy_sha256: context.evidence_policy_sha256,
    requested_at: at - 5000, observed_at: at - 4000, original_expires_at: binding.expires_at * 1000,
    app_actor_id: '98', run_id: provenance.run_id };
  provenance.dispatch_request_sha256 = delegationDigest(observation.request);
  provenance.dispatch_record_sha256 = delegationDigest(observation);
  const retained = { context, provenance, observation };
  const claims = { schema: 2, issuer: 'https://operations.mrnwebdesigns.com',
    audience: `https://github.com/${provenance.repository}/actions/runs/${provenance.run_id}`,
    issued_at: at / 1000, expires_at: at / 1000 + 5,
    request_id: '33333333-3333-4333-8333-333333333333', nonce: 'c'.repeat(64),
    action: 'read-original-existing-management-dispatch', ...pointer, worker_contract_sha256: existingWorkerContractSha256,
    context: structuredClone(context), recorded_dispatch: structuredClone(provenance), dispatch_record: structuredClone(observation) };
  const input = { pointer, retained, originalBinding: binding, identityKeyId: 'fixture-identity', identityPublicKey: keys.publicKey,
    startedAt: at, completedAt: at + 500 };
  const encode = (body = claims, purpose = existingWorkerPurpose) => {
    const payload = Buffer.isBuffer(body) ? body : Buffer.from(delegationCanonical(body));
    return Buffer.from(JSON.stringify({ signed_context: { key_id: input.identityKeyId, payload_base64: payload.toString('base64'),
      signature_base64: sign(null, Buffer.concat([Buffer.from(purpose), payload]), keys.privateKey).toString('base64') } }));
  };
  const verify = () => verifyExistingManagementWorkerRecord({ ...input, raw: encode() });
  return { claims, input, encode, verify };
}

test('additive worker wire preserves exact frozen v2 definitions and keeps every operational gate closed', () => {
  const raw = readFileSync(new URL('../contracts/content-management-existing-worker-v2-proposal.json', import.meta.url));
  assert.equal(digest(raw), existingWorkerContractSha256);
  const wire = JSON.parse(raw);
  for (const name of wire.imported_definitions) assert.deepEqual(wire.$defs[name], existingManagementWires.evidence.$defs[name]);
  for (const flag of ['production_enabled', 'runtime_registered', 'dispatch_enabled', 'source_admission_enabled']) assert.equal(wire[flag], false);
  assert.equal(existingWorkerPurpose, 'mrn-content-management-existing-worker-context-v2:');
  assert.equal(existingWorkerUrl, 'https://operations.mrnwebdesigns.com/internal/mainwp/management-existing-worker-context');
  assert.equal(wire.signature_role, 'identity');
  assert.equal(wire.limits.read_seconds, 5); assert.equal(wire.limits.original_intent_seconds, 900);
});

test('exact signed v2 worker record proves consistency only, using independent original and dispatch records', () => {
  const f = fixture(), result = f.verify();
  assert.equal(result.state, 'existing-worker-record-consistent');
  for (const field of ['current_access_proven', 'hosted_oidc_proven', 'source_verified', 'admitted']) assert.equal(result[field], false);
  assert.deepEqual(result.context, f.input.retained.context);
  assert.equal(result.readback.worker_contract_sha256, existingWorkerContractSha256);
});

for (const [label, mutate] of [
  ['legacy schema', f => { f.claims.schema = 1; }],
  ['caller identity', f => { f.claims.email = 'caller@mrnwebdesigns.com'; }],
  ['foreign context', f => { f.claims.context.capture_artifact_sha256 = 'd'.repeat(64); }],
  ['foreign original', f => { f.input.originalBinding.operation_id = '44444444-4444-4444-8444-444444444444'; }],
  ['changed source', f => { f.claims.recorded_dispatch.runner_source_sha = 'd'.repeat(40); }],
  ['changed dispatch', f => { f.input.pointer.dispatch_id = '44444444-4444-4444-8444-444444444444'; }],
  ['changed policy', f => { f.claims.dispatch_record.evidence_policy_sha256 = 'd'.repeat(64); }],
  ['changed contract', f => { f.claims.worker_contract_sha256 = 'd'.repeat(64); }],
  ['renewed read grant', f => { f.claims.expires_at++; }],
  ['expired signed record', f => { f.claims.expires_at = f.claims.issued_at; }],
  ['late receipt', f => { f.input.completedAt = f.input.startedAt + 5000; }],
  ['backward clock', f => { f.input.completedAt = f.input.startedAt - 1; }],
  ['wrong key role or identity key', f => { f.input.identityPublicKey = generateKeyPairSync('ed25519').publicKey; }],
  ['caller endpoint', f => { f.input.pointer.url = 'https://caller.invalid'; }],
]) test(`existing worker record refuses ${label}`, () => {
  const f = fixture(); mutate(f); assert.throws(f.verify, refused);
});

test('a matching signed and retained observation still cannot extend the original or substitute its internal digests', () => {
  for (const mutate of [
    o => { o.original_expires_at++; },
    o => { o.requested_at = 1; },
    o => { o.observed_at = o.requested_at - 1; },
    o => { o.request.worker_contract_sha256 = 'd'.repeat(64); },
    o => { o.request.schema = 1; },
    o => { o.request.context_sha256 = 'd'.repeat(64); },
  ]) {
    const f = fixture(); mutate(f.input.retained.observation);
    f.input.retained.provenance.dispatch_request_sha256 = delegationDigest(f.input.retained.observation.request);
    f.input.retained.provenance.dispatch_record_sha256 = delegationDigest(f.input.retained.observation);
    f.claims.dispatch_record = structuredClone(f.input.retained.observation);
    f.claims.recorded_dispatch = structuredClone(f.input.retained.provenance);
    assert.throws(f.verify, refused);
  }
});

test('legacy or other v2 domains cannot sign the new worker record', () => {
  const f = fixture();
  for (const purpose of ['mrn-content-management-worker-context-v1:',
    ...Object.keys(existingManagementWires.wire_registry.signature_roles)]) {
    assert.throws(() => verifyExistingManagementWorkerRecord({ ...f.input, raw: f.encode(f.claims, purpose) }), refused);
  }
});

test('signed noncanonical, duplicate-key and oversized payloads are refused before record admission', () => {
  const f = fixture();
  const canonical = delegationCanonical(f.claims);
  for (const body of [Buffer.from(' ' + canonical), Buffer.from('{"schema":1,' + canonical.slice(1)),
    Buffer.alloc(48001, 32), Buffer.from('{')]) {
    assert.throws(() => verifyExistingManagementWorkerRecord({ ...f.input, raw: f.encode(body) }), refused);
  }
  for (const raw of [Buffer.alloc(65537), Buffer.alloc(0), Buffer.from('{'), 'caller string'])
    assert.throws(() => verifyExistingManagementWorkerRecord({ ...f.input, raw }), refused);
  const encoded = f.encode().toString();
  for (const raw of [Buffer.from(' ' + encoded), Buffer.from('{"signed_context":null,' + encoded.slice(1))])
    assert.throws(() => verifyExistingManagementWorkerRecord({ ...f.input, raw }), refused);
});
