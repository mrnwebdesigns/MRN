// Disposable, at-rest storage proof only. This never authorizes a source read,
// worker, native acquisition or live operation.
import assert from 'node:assert/strict';
import { z } from 'zod';
import { parseExistingSourceContextAudit } from './existing-binding-context.mjs';

const sha = z.string().regex(/^[a-f0-9]{64}$/);
const storageAuditType = z.object({
  result: z.literal('PASS'), schema: z.literal(2), scope: z.literal('existing-source-storage-seed-v2'),
  qualification_job_id: z.string().regex(/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/),
  binding_sha256: sha, capture_artifact_sha256: sha, measurement_record_sha256: sha,
  parity_sha256: sha, source_context_sha256: sha, original_marker_sha256: sha, budget_sha256: sha,
  actual_original_marker_count: z.literal(1), sidecar_records: z.literal(1),
  source_controls: z.literal(0), source_capture_reserved: z.literal(false), source_status: z.literal(0),
  source_reads: z.literal(0), source_recovery: z.literal(0), source_claims: z.literal(0),
  completion_sha256: z.null(), source_slots: z.literal(0), source_frames: z.literal(0),
  independent_consistent_readback: z.literal(true), complete_sidecar_relation: z.literal(true),
  at_rest_only: z.literal(true), current_access_proven: z.literal(false),
  native_acquisition_proven: z.literal(false), admitted: z.literal(false), production_changes: z.literal(false),
}).strict();

export function parseExistingSourceStorageAudit(bytes) {
  if (!Buffer.isBuffer(bytes) || bytes.length > 8192) throw new Error('Invalid disposable source storage audit');
  try { return storageAuditType.parse(JSON.parse(bytes.toString('utf8'))); }
  catch { throw new Error('Invalid disposable source storage audit'); }
}

export function verifyExistingSourceStorageAudit(bytes, contextBytes, binding) {
  const storage = parseExistingSourceStorageAudit(bytes), context = parseExistingSourceContextAudit(contextBytes);
  assert.equal(binding.schema, 2); assert.equal(binding.result, 'PASS');
  assert.equal(binding.scope, 'disposable-native-existing-queue-access-v2-only');
  const expected = {
    qualification_job_id: binding.qualification.job_id,
    binding_sha256: binding.capture.binding_sha256,
    capture_artifact_sha256: binding.capture.artifact_sha256,
    measurement_record_sha256: binding.capture.measurements_sha256,
    parity_sha256: binding.sourceContext.parity_sha256,
    source_context_sha256: binding.sourceContext.source_context_sha256,
  };
  for (const [field, value] of Object.entries(expected)) {
    assert.equal(storage[field], value, 'Storage audit differs from original gateway evidence');
    assert.equal(context[field], value, 'Storage audit differs from independent original context evidence');
  }
  return storage;
}
