import test from 'node:test';
import assert from 'node:assert/strict';
import { parseExistingSourceStorageAudit, verifyExistingSourceStorageAudit } from './runtime/existing-source-storage-audit.mjs';

const bytes = value => Buffer.from(JSON.stringify(value));
function fixture() {
  const common = { result: 'PASS', schema: 2, qualification_job_id: '11111111-1111-4111-8111-111111111111',
    binding_sha256: 'a'.repeat(64), capture_artifact_sha256: 'b'.repeat(64), measurement_record_sha256: 'c'.repeat(64),
    parity_sha256: 'd'.repeat(64), source_context_sha256: 'e'.repeat(64),
    independent_consistent_readback: true, complete_sidecar_relation: true, at_rest_only: true,
    current_access_proven: false, admitted: false, production_changes: false };
  return {
    storage: { ...common, scope: 'existing-source-storage-seed-v2', original_marker_sha256: 'f'.repeat(64),
      budget_sha256: '1'.repeat(64), actual_original_marker_count: 1, sidecar_records: 1, source_controls: 0,
      source_capture_reserved: false, source_status: 0, source_reads: 0, source_recovery: 0, source_claims: 0,
      completion_sha256: null, source_slots: 0, source_frames: 0, native_acquisition_proven: false },
    context: { ...common, actual_original_marker_count: 2, sidecar_records: 7 },
    binding: { result: 'PASS', schema: 2, scope: 'disposable-native-existing-queue-access-v2-only',
      qualification: { job_id: common.qualification_job_id },
      capture: { binding_sha256: common.binding_sha256, artifact_sha256: common.capture_artifact_sha256,
        measurements_sha256: common.measurement_record_sha256 },
      sourceContext: { source_context_sha256: common.source_context_sha256, parity_sha256: common.parity_sha256 } },
  };
}
const verify = f => verifyExistingSourceStorageAudit(bytes(f.storage), bytes(f.context), f.binding);

test('storage seed audit matches separate original gateway and context evidence without granting authority', () => {
  const f = fixture(); assert.deepEqual(verify(f), f.storage);
  assert.equal(Object.keys(f.storage).length, 29);
});

test('storage seed audit rejects missing records, spent budgets, completion and authority claims', () => {
  const good = fixture().storage;
  for (const key of Object.keys(good)) {
    const missing = { ...good }; delete missing[key];
    assert.throws(() => parseExistingSourceStorageAudit(bytes(missing)), key);
    const changed = { ...good, [key]: typeof good[key] === 'boolean' ? !good[key]
      : typeof good[key] === 'number' ? good[key] + 1 : 'invalid' };
    assert.throws(() => parseExistingSourceStorageAudit(bytes(changed)), key);
  }
  for (const change of [{ source_controls: 1 }, { source_capture_reserved: true }, { completion_sha256: 'a'.repeat(64) },
    { source_frames: 1 }, { private_source: 'must not escape' }, { source_slots: '0' }])
    assert.throws(() => parseExistingSourceStorageAudit(bytes({ ...good, ...change })));
});

test('storage seed audit refuses substituted originals even when each artifact is separately well formed', () => {
  for (const field of ['qualification_job_id', 'binding_sha256', 'capture_artifact_sha256',
    'measurement_record_sha256', 'parity_sha256', 'source_context_sha256']) {
    for (const target of ['storage', 'context']) {
      const f = fixture(); f[target][field] = field === 'qualification_job_id'
        ? '22222222-2222-4222-8222-222222222222' : '9'.repeat(64);
      assert.throws(() => verify(f), `${target}.${field}`);
    }
  }
  for (const change of [{ schema: 1 }, { result: 'FAIL' }, { scope: 'live' }]) {
    const f = fixture(); Object.assign(f.binding, change); assert.throws(() => verify(f));
  }
  const f = fixture(); f.context.sidecar_records = 1; assert.throws(() => verify(f));
});

test('storage audit intake is bounded and refuses malformed or non-object output', () => {
  for (const value of [Buffer.alloc(8193), Buffer.from('{bad'), Buffer.from('null'), Buffer.from('[]'), {}, 'not bytes'])
    assert.throws(() => parseExistingSourceStorageAudit(value));
});
