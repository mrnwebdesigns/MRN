import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { digest } from '../src/contracts.mjs';
import { delegationCanonical, delegationDigest } from '../src/delegation.mjs';
import { validateExistingSourceBatch, validateSourceBatch } from '../src/management-source-transfer.mjs';

const readFixture = name => JSON.parse(readFileSync(new URL(`./fixtures/${name}.json`, import.meta.url)));
function fixture() {
  const old = readFixture('content-management-source-transfer-v1'), vectors = readFixture('content-management-existing-v2');
  const request = structuredClone(vectors.positive.find(x => x.domain === 'source_transfer' && x.definition === 'read_claims').value);
  const context = request.context;
  // The provider vectors intentionally establish schema/signature shape only.
  // This synthetic packet additionally supplies the actual context binding hash.
  context.management.management_context_sha256 = delegationDigest(context.management.binding);
  const manifest = { ...old.transfer_manifest, schema: 2, purpose: 'content-management-existing-source-transfer-v2', context };
  const bytes = Buffer.from(delegationCanonical(manifest));
  const manifestFrames = Math.ceil(bytes.length / 32768), nativeFrames = old.read_output.frames.filter(x => x.kind === 'native');
  const slot = { ...old.capture_output.slot, transfer_id: request.transfer_id, transfer_manifest_sha256: digest(bytes),
    manifest_bytes: bytes.length, manifest_frames: manifestFrames, total_frames: manifestFrames + nativeFrames.length };
  request.transfer_manifest_sha256 = slot.transfer_manifest_sha256;
  const frames = [];
  for (let index = 0; index < manifestFrames; index++) {
    const frame = bytes.subarray(index * 32768, (index + 1) * 32768);
    frames.push({ index, kind: 'manifest', bytes: frame.length, sha256: digest(frame), data_base64: frame.toString('base64') });
  }
  for (const frame of nativeFrames) frames.push({ ...frame, index: frames.length });
  // Synthetic shape-only signed-input envelope. No signature verification or
  // provenance is claimed by the byte validator; the real broker owns those.
  const input = { signed_request: { key_id: 'fixture', payload_base64: Buffer.from(delegationCanonical(request)).toString('base64'),
    signature_base64: Buffer.alloc(64, 1).toString('base64') } };
  const output = { schema: 2, context, slot, request_sha256: delegationDigest(input), batch_index: request.batch_index, frames };
  const original = vectors.retained_original_binding;
  const call = (value = output) => validateExistingSourceBatch(value, context, slot, request, input, original);
  return { old, output, context, slot, request, input, original, call };
}

test('private v2 batch validation binds unchanged provider frames to the exact retained original and whole signed input', () => {
  const f = fixture(); assert.deepEqual(f.call(), f.output);
  assert.equal(f.output.slot.admitted, false); assert.equal(f.output.slot.authority_verified, false);
  assert.throws(() => f.call({ ...f.output, request_sha256: delegationDigest(f.request) }));
  const changed = structuredClone(f.input); changed.signed_request.key_id = 'substitute';
  assert.throws(() => validateExistingSourceBatch(f.output, f.context, f.slot, f.request, changed, f.original));
  for (const change of [{ site_url: 'https://another.example.org' }, { expires_at: f.original.expires_at + 1 },
    { requester_binding_sha256: 'f'.repeat(64) }, { provider_source_sha: 'f'.repeat(40) }])
    assert.throws(() => validateExistingSourceBatch(f.output, f.context, f.slot, f.request, f.input, { ...f.original, ...change }));
});

test('v1 and v2 source batches cannot be substituted, relabeled or accepted through a fallback parser', () => {
  const f = fixture(), old = f.old;
  assert.deepEqual(validateSourceBatch(old.read_output, old.context, old.capture_output.slot, old.read_claims, old.read_input), old.read_output);
  assert.throws(() => f.call(old.read_output));
  assert.throws(() => f.call({ ...old.read_output, schema: 2 }));
  assert.throws(() => validateSourceBatch(f.output, f.context, f.slot, f.request, f.input));
  const context = structuredClone(f.context); context.management.management_context_sha256 = 'f'.repeat(64);
  assert.throws(() => validateExistingSourceBatch(f.output, context, f.slot, f.request, f.input, f.original));
});

test('v2 source frame damage, omission, ordering, manifest geometry and request mutations are refused', () => {
  for (const change of [v => { v.frames[0].data_base64 = 'YWJj'; }, v => { v.frames[0].sha256 = 'f'.repeat(64); },
    v => { v.frames[0].bytes++; }, v => { v.frames.reverse(); }, v => { v.frames.pop(); },
    v => { v.frames[0].kind = 'native'; }, v => { v.slot.manifest_frames++; }, v => { v.slot.total_frames++; },
    v => { v.batch_index++; }, v => { v.context.management.binding.site_id++; }, v => { v.extra = true; }]) {
    const f = fixture(), value = structuredClone(f.output); change(value); assert.throws(() => f.call(value));
  }
  const f = fixture();
  assert.throws(() => validateExistingSourceBatch(f.output, f.context, f.slot, { ...f.request, nonce: 'f'.repeat(64) }, f.input, f.original));
  const changed = structuredClone(f.input); changed.signed_request.payload_base64 += '=';
  assert.throws(() => validateExistingSourceBatch(f.output, f.context, f.slot, f.request, changed, f.original));
});
