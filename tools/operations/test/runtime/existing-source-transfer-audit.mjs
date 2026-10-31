// Independent intake of the final disposable SQL/frame audit. Not admission.
import assert from 'node:assert/strict';
import { z } from 'zod';
import { digest } from '../../src/contracts.mjs';
import { verifyExistingSourceStorageAudit } from './existing-source-storage-audit.mjs';

const sha = z.string().regex(/^[a-f0-9]{64}$/), uuid = z.string().uuid();
const count = maximum => z.number().int().min(0).max(maximum);
const auditType = z.object({
  result: z.literal('PASS'), schema: z.literal(2), scope: z.literal('existing-source-transfer-at-rest-v2'),
  qualification_job_id: uuid, completion_sha256: sha, transfer_id: uuid, transfer_manifest_sha256: sha,
  binding_sha256: sha, capture_artifact_sha256: sha, measurement_record_sha256: sha,
  parity_sha256: sha, source_context_sha256: sha, seed_checkpoint_sha256: sha,
  source_controls: count(48), source_status: count(7), source_reads: count(32), source_recovery: count(8),
  source_frames: z.number().int().min(1).max(391), native_files: count(128), native_bytes: count(4194304),
  native_frames: count(256), native_signing_packets: z.number().int().min(2).max(258),
  independent_consistent_readback: z.literal(true), complete_sidecar_relation: z.literal(true),
  actual_native_frame_bytes_verified: z.literal(true), actual_mainwp_signing_observed: z.literal(true), at_rest_only: z.literal(true),
  current_access_proven: z.literal(false), live_google_proven: z.literal(false), hosted_oidc_proven: z.literal(false),
  cache_provenance_verified: z.literal(false), full_source_verified: z.literal(false), admitted: z.literal(false), production_changes: z.literal(false),
}).strict();

export function parseExistingSourceTransferAudit(bytes) {
  if (!Buffer.isBuffer(bytes) || bytes.length > 8192) throw new Error('Invalid disposable source transfer audit');
  try { return auditType.parse(JSON.parse(bytes.toString('utf8'))); }
  catch { throw new Error('Invalid disposable source transfer audit'); }
}

export function verifyExistingSourceTransferAudit(bytes, seedBytes, contextBytes, binding) {
  const audit = parseExistingSourceTransferAudit(bytes);
  const seed = verifyExistingSourceStorageAudit(seedBytes, contextBytes, binding), transfer = binding.sourceTransfer;
  assert.equal(transfer.schema, 2); assert.equal(transfer.result, 'PASS');
  for (const key of ['qualification_job_id', 'binding_sha256', 'capture_artifact_sha256', 'measurement_record_sha256', 'parity_sha256', 'source_context_sha256'])
    assert.equal(audit[key], seed[key], 'Final transfer differs from the preserved pre-capture checkpoint');
  assert.equal(audit.seed_checkpoint_sha256, digest(seedBytes));
  for (const key of ['transfer_id', 'transfer_manifest_sha256', 'source_context_sha256', 'source_controls', 'source_status',
    'source_reads', 'source_recovery', 'source_frames', 'native_files', 'native_bytes', 'native_frames'])
    assert.equal(audit[key], transfer[key], 'Final retained SQL/frame state differs from actual gateway transport');
  assert.equal(audit.source_reads, Math.ceil(audit.source_frames / 16));
  assert.equal(audit.source_status, 1); assert.equal(audit.source_recovery, 0);
  assert.equal(audit.source_controls, 1 + audit.source_reads + audit.source_status);
  assert.equal(audit.native_signing_packets, audit.native_frames + 2);
  assert.ok(audit.native_files > 0 && audit.native_bytes > 0 && audit.native_frames > 0,
    'This native transport qualification must exercise actual native file bytes');
  assert.ok(audit.source_frames > audit.native_frames);
  assert.equal(transfer.native_frame_bytes_verified, true); assert.equal(transfer.seed_checkpoint_preserved, true);
  for (const key of ['cache_provenance_verified', 'full_source_verified', 'authority_verified', 'admitted']) assert.equal(transfer[key], false);
  assert.deepEqual(transfer.calls.map(c => c.action), ['capture', 'status', ...Array(audit.source_reads).fill('read')]);
  assert.equal(new Set(transfer.calls.map(c => c.request_sha256)).size, transfer.calls.length);
  for (const call of transfer.calls) {
    assert.match(call.request_sha256, /^[a-f0-9]{64}$/);
    assert.ok(Number.isSafeInteger(call.elapsed_ms) && call.elapsed_ms >= 0 && call.elapsed_ms < (call.action === 'capture' ? 120000 : 20000));
  }
  return audit;
}
