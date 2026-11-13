// Disposable canonical MainWP/Child transport qualification, never a team tool.
import assert from 'node:assert/strict';
import { randomBytes, randomUUID } from 'node:crypto';
import { execFile, execFileSync } from 'node:child_process';
import { promisify } from 'node:util';
import { readFileSync } from 'node:fs';
import { join, basename } from 'node:path';
import { pathToFileURL } from 'node:url';
import { digest } from '../../src/contracts.mjs';
import { delegationCanonical, delegationDigest } from '../../src/delegation.mjs';
import { createPrivateQueryReader } from '../../src/access-control.mjs';
import { ManagementEvidenceSigner } from '../../src/management-evidence-callback.mjs';
import { payload } from '../../src/mainwp.mjs';
import { existingManagementTypes, existingManagementWires } from '../../src/management-existing-contracts.mjs';
import { validateExistingSourceBatch } from '../../src/management-source-transfer.mjs';
import { parseExistingSourceStorageAudit } from './existing-source-storage-audit.mjs';

export const existingSourceTransferTools = Object.freeze({
  capture: 'mrn_mainwp__capture_content_edit_qualification_source_existing_v2',
  status: 'mrn_mainwp__get_content_edit_qualification_source_existing_v2',
  read: 'mrn_mainwp__read_content_edit_qualification_source_existing_v2',
});
const wire = existingManagementWires.source_transfer, types = existingManagementTypes.source_transfer;

export function existingSourceInventoryWithin(inventory, context, parity, recipe, current) {
  const started = Date.now(), monotonic = performance.now();
  const within = () => {
    current();
    assert.ok(Date.now() >= started && Date.now() - started < 5000
      && performance.now() >= monotonic && performance.now() - monotonic < 5000);
  };
  within();
  const result = inventory(context, parity, recipe, within);
  within(); return result;
}

export function assertExistingSourceManifestContext(manifest, context) {
  // The strict runner parser uses null-prototype objects. Compare exact wire
  // values after closed-schema validation without normalizing the source bytes.
  assert.equal(delegationCanonical(manifest.context), delegationCanonical(context));
}

export async function qualifyExistingSourceTransfer({ client, ledger, jobId, context, catalog, parity, registrar, runtimeCli, runner,
  observe = () => {}, observeCheckpoint = () => {} }) {
  assert.equal(process.env.GITHUB_ACTIONS, 'true');
  assert.equal(process.env.MRN_DISPOSABLE_EXISTING_SOURCE_TRANSPORT_TEST, '1');
  const lock = JSON.parse(readFileSync(new URL('./sources.json', import.meta.url)));
  assert.equal(execFileSync('git', ['-C', runner, 'rev-parse', 'HEAD'], { encoding: 'utf8' }).trim(), lock.managementWorker.commit);
  const { existingManagementSourceInventory, managementNativeSourceAllowed } = await import(pathToFileURL(join(runner, 'src/management-source.mjs')));
  const { validateExistingSourceShape } = await import(pathToFileURL(join(runner, 'src/management-existing-contracts.mjs')));
  const { sourceManifestJson } = await import(pathToFileURL(join(runner, 'src/measurement-json.mjs')));
  context = types.source_context.parse(context);
  const initial = await ledger.currentQueuedCapture(jobId), b = initial.current.queue.context.binding;
  const current = () => {
    const now = ledger.originalQueuedJob(jobId);
    assert.equal(now.original.digest, initial.current.original.digest); assert.equal(now.revision, initial.current.revision);
    assert.deepEqual(now.queue.context, initial.current.queue.context);
    assert.ok(Date.now() >= b.created_at * 1000
      && Date.now() < Math.min(b.expires_at * 1000, now.original.mapping.authorization.expiresAt));
    return now;
  };
  assert.deepEqual(context.management.binding, b);
  assert.equal(context.management.capture_artifact_sha256, initial.capture.receipt.captureArtifactSha256);
  assert.equal(context.management.measurement_record_sha256, initial.capture.receipt.measurementSha256);
  assert.equal(context.source_recipe_catalog_sha256, delegationDigest(catalog.catalog));
  const recipe = { schema: 1, job_uuid: jobId, site_id: b.site_id, site_url: b.site_url,
    binding_sha256: context.management.management_context_sha256, capture_sha256: context.management.capture_artifact_sha256,
    parity_sha256: context.management.parity_sha256, expires_at: b.expires_at,
    roots: parity.roots.map(({ path, kind }) => ({ path, kind })), native_reads: catalog.catalog.native_reads };
  const inventory = existingSourceInventoryWithin(existingManagementSourceInventory, context.management, parity, recipe, current);
  observeCheckpoint('capability-discovery');
  for (const tool of Object.values(existingSourceTransferTools)) await new (createPrivateQueryReader(tool))(client).initialize();
  observeCheckpoint('empty-storage');
  const seedResult = await promisify(execFile)(runtimeCli, ['source-storage-seed-audit'], { timeout: 20000, maxBuffer: 16384 });
  const seed = parseExistingSourceStorageAudit(Buffer.from(seedResult.stdout.trim()));
  assert.equal(seed.qualification_job_id, jobId); assert.equal(seed.source_context_sha256, delegationDigest(context));
  const signer = new ManagementEvidenceSigner(registrar), calls = [], frames = [];
  const call = async (action, extra = {}) => {
    const started = Date.now(), monotonic = performance.now(), limit = action === 'capture' ? 120000 : 20000;
    let expired = false;
    const within = () => { current(); assert.ok(!expired && Date.now() >= started && Date.now() - started < limit
      && performance.now() >= monotonic && performance.now() - monotonic < limit); };
    const bounded = async operation => {
      within(); let timer;
      try {
        const value = await Promise.race([Promise.resolve().then(operation), new Promise((_, reject) => {
          timer = setTimeout(() => { expired = true; reject(new Error('EXISTING_SOURCE_FIXTURE_TIMEOUT')); },
            Math.max(1, Math.min(limit - (Date.now() - started), limit - (performance.now() - monotonic))));
        })]); within(); return value;
      } finally { clearTimeout(timer); }
    };
    await bounded(() => ledger.verifyStaff(current().original.mapping.actor));
    const claims = types[action + '_claims'].parse({ schema: 2, issuer: 'mrn-content-qualification', audience: 'https://wpcontrol.mrndev.io',
      issued_at: Math.floor(Date.now() / 1000), expires_at: Math.min(b.expires_at, Math.floor((started + limit) / 1000)),
      request_id: randomUUID(), nonce: randomBytes(32).toString('hex'), context,
      action: { capture: 'capture-management-source', status: 'read-management-source-status', read: 'read-management-source' }[action], ...extra });
    const input = types[action + '_input'].parse({ signed_request: signer.envelope(claims, wire.purposes[action]),
      ...(action === 'capture' ? { signed_source_catalog: catalog.signedCatalog } : {}) });
    const sent = delegationDigest(input);
    assert.ok(Buffer.byteLength(delegationCanonical(input)) <= (action === 'capture' ? wire.limits.capture_input_bytes : wire.limits.read_input_bytes));
    const observation = { action, ordinal: calls.length + 1, kind: 'pending', elapsed_ms: 0,
      granted_ms: claims.expires_at * 1000 - Date.now(), timeout_ms: limit, error: 'none' };
    observe({ ...observation });
    let output;
    try {
      output = types[action + '_output'].parse(payload(await bounded(() => client.callTool(
        { name: existingSourceTransferTools[action], arguments: input }, undefined, { timeout: limit }))));
      observation.kind = 'received';
    } catch (error) {
      observation.kind = 'failed';
      observation.error = error?.message === 'EXISTING_SOURCE_FIXTURE_TIMEOUT' ? 'deadline'
        : error?.code === 'MAINWP_FAILED' ? 'provider-refused' : 'transport-or-schema';
      throw error;
    } finally { observation.elapsed_ms = Math.max(0, Math.min(300000, Date.now() - started)); observe({ ...observation }); }
    within(); assert.equal(delegationDigest(input), sent); assert.deepEqual(output.context, context);
    assert.ok(Buffer.byteLength(delegationCanonical(output)) <= wire.limits.private_response_bytes);
    await bounded(() => ledger.verifyStaff(current().original.mapping.actor));
    assert.ok(claims.expires_at * 1000 > Date.now());
    calls.push({ action, elapsed_ms: Date.now() - started, request_sha256: sent });
    return { output, claims, input };
  };
  observeCheckpoint('capture');
  const captured = await call('capture'), slot = captured.output.slot;
  assert.equal(slot.recipe_sha256, delegationDigest(recipe));
  observeCheckpoint('status');
  const status = await call('status'); assert.equal(status.output.state, 'complete'); assert.deepEqual(status.output.slot, slot);
  observeCheckpoint('frame-batches');
  for (let batch = 0; batch * 16 < slot.total_frames; batch++) {
    const value = await call('read', { transfer_id: slot.transfer_id, transfer_manifest_sha256: slot.transfer_manifest_sha256, batch_index: batch });
    frames.push(...validateExistingSourceBatch(value.output, context, slot, value.claims, value.input, b).frames);
  }
  assert.equal(frames.length, slot.total_frames);
  observeCheckpoint('manifest');
  const manifestBytes = Buffer.concat(frames.slice(0, slot.manifest_frames).map(frame => Buffer.from(frame.data_base64, 'base64')));
  assert.equal(manifestBytes.length, slot.manifest_bytes); assert.equal(digest(manifestBytes), slot.transfer_manifest_sha256);
  const manifest = sourceManifestJson(manifestBytes); validateExistingSourceShape('transfer_manifest', manifest);
  assert.equal(delegationCanonical(manifest), manifestBytes.toString('utf8')); manifestBytes.fill(0);
  assertExistingSourceManifestContext(manifest, context); assert.equal(manifest.recipe_sha256, delegationDigest(recipe));
  assert.equal(manifest.transfer_id, slot.transfer_id);
  observeCheckpoint('original-inventory');
  const expected = new Map();
  for (const root of [...parity.roots, { kind: 'core-php', path: '', files: parity.core_php }]) {
    if (!root.files.length) continue;
    const entries = Object.fromEntries(root.files.map(f => [root.kind === 'file' ? basename(f.path)
      : root.kind === 'core-php' ? f.path : f.path.slice(root.path.length), f.sha256]).sort(([a], [b]) => a < b ? -1 : a > b ? 1 : 0));
    const matches = catalog.catalog.artifacts.filter(a => a.root_kind === root.kind && a.relative_inventory_sha256 === delegationDigest(entries));
    assert.ok(matches.length <= 1);
    for (const file of root.files) expected.set(file.path, matches[0]?.artifact_id ?? null);
  }
  const paths = [...inventory.files.keys()]; assert.equal(manifest.origins.length, paths.length);
  let nativeFrames = 0, nativeFiles = 0, nativeBytes = 0;
  for (const [i, origin] of manifest.origins.entries()) {
    assert.equal(origin.inventory_index, i); assert.ok(expected.has(paths[i]));
    if (expected.get(paths[i]) !== null) { assert.equal(origin.origin, 'cache'); assert.equal(origin.artifact_id, expected.get(paths[i])); continue; }
    assert.equal(origin.origin, 'native'); assert.equal(origin.native_frame_start, nativeFrames);
    assert.equal(managementNativeSourceAllowed(paths[i]), true);
    assert.equal(origin.native_frame_count, Math.max(1, Math.ceil(origin.bytes / 32768)));
    const selected = frames.slice(slot.manifest_frames + nativeFrames, slot.manifest_frames + nativeFrames + origin.native_frame_count);
    assert.equal(selected.length, origin.native_frame_count);
    for (const [index, frame] of selected.entries()) assert.equal(frame.bytes, Math.min(32768, origin.bytes - index * 32768));
    const bytes = Buffer.concat(selected.map(frame => Buffer.from(frame.data_base64, 'base64')));
    try { assert.equal(bytes.length, origin.bytes); assert.equal(digest(bytes), inventory.files.get(paths[i])); }
    finally { bytes.fill(0); }
    nativeFrames += origin.native_frame_count; nativeFiles++; nativeBytes += origin.bytes;
  }
  observeCheckpoint('native-summary');
  for (const [key, value] of Object.entries({ native_frames: nativeFrames, native_files: nativeFiles, native_bytes: nativeBytes })) {
    assert.equal(slot[key], value); assert.equal(manifest[key], value);
  }
  assert.ok(nativeFiles > 0 && nativeBytes > 0 && nativeFrames > 0);
  current();
  observeCheckpoint('complete');
  return { schema: 2, result: 'PASS', transfer_id: slot.transfer_id, transfer_manifest_sha256: slot.transfer_manifest_sha256,
    source_context_sha256: delegationDigest(context), recipe_sha256: slot.recipe_sha256, calls,
    source_controls: calls.length, source_status: 1, source_reads: calls.length - 2, source_recovery: 0,
    source_frames: slot.total_frames, native_files: nativeFiles, native_bytes: nativeBytes, native_frames: nativeFrames,
    native_frame_bytes_verified: true, seed_checkpoint_preserved: true, cache_provenance_verified: false,
    full_source_verified: false, authority_verified: false, admitted: false };
}
