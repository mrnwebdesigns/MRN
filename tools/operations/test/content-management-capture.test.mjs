import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { digest } from '../src/contracts.mjs';
import { delegationCanonical, delegationDigest } from '../src/delegation.mjs';
import { compileManagementCaptureSchema } from '../src/content-management-schema.mjs';
import { managementCandidate, managementCandidateHash } from '../src/content-management-contract.mjs';
import { managementCaptureContract, managementCaptureTypes, validateManagementCapture, validateManagementMeasurements } from '../src/content-management-capture.mjs';

const source = JSON.parse(readFileSync(new URL('../contracts/content-management-qualification-source.json', import.meta.url)));
const vectors = JSON.parse(readFileSync(new URL('./fixtures/content-management-qualification-v1.json', import.meta.url)));
function fixture(capture = vectors.capture_output) {
  const value = structuredClone(capture), a = value.capture_artifact;
  const { before_sha256: expected_snapshot_sha256, ...request } = a.migration.request;
  const expected = { queuedContext: { binding: structuredClone(a.binding), binding_sha256: a.binding_sha256,
    state: 'queued', capture_artifact_sha256: null },
  evidence: { review: structuredClone(a.review), migration: structuredClone(a.migration), read: structuredClone(a.dataset.read) },
  request: { ...request, expected_snapshot_sha256, reason: a.migration.reason, idempotency_key: a.binding.operation_id }, profile: structuredClone(a.review.profile),
  now: (a.binding.created_at + 2) * 1000 };
  validateManagementCapture(value, expected);
  return { value, expected };
}
// Simulate an untrusted sender recomputing every public digest. None of these
// self-consistent hashes can replace the independently retained expected state.
function rehash(value) {
  const a = value.capture_artifact, d = a.dataset, m = value.measurements;
  d.parity_sha256 = delegationDigest(d.parity_manifest); a.dataset_sha256 = delegationDigest(d);
  a.binding_sha256 = delegationDigest(a.binding); value.capture_artifact_sha256 = delegationDigest(a);
  Object.assign(m, { binding_sha256: a.binding_sha256, capture_artifact_sha256: value.capture_artifact_sha256,
    dataset_sha256: a.dataset_sha256, parity_sha256: d.parity_sha256, dataset_bytes: Buffer.byteLength(delegationCanonical(d)) });
  m.chunks_received = Math.ceil(m.dataset_bytes / 32768);
  m.readback_bytes = Buffer.byteLength(delegationCanonical(d.read)); m.readback_sha256 = delegationDigest(d.read);
  m.readback_chunks = Math.ceil(m.readback_bytes / 32768); m.native_calls = m.chunks_received + m.readback_chunks;
}

test('private capture contract and synthetic vectors match immutable source; every ability schema compiles', () => {
  assert.equal(source.runtimeAdoption, false); assert.equal(source.productionQualified, false);
  for (const entry of Object.values(source.files))
    assert.equal(digest(readFileSync(new URL('../' + entry.path, import.meta.url))), entry.sha256);
  assert.equal(vectors.synthetic, true); assert.equal(vectors.admission_authority, false);
  assert.equal(vectors.contract_sha256, source.files['contracts/content-management-qualification-v1.json'].sha256);
  for (const ability of Object.values(managementCaptureContract.abilities)) {
    assert.ok(compileManagementCaptureSchema({ ...ability.input, $defs: managementCaptureContract.$defs }));
    assert.ok(compileManagementCaptureSchema({ ...ability.output, $defs: managementCaptureContract.$defs }));
  }
  assert.equal(managementCaptureContract.production_enabled, false);
  assert.equal(managementCaptureContract.consumer_binding_enabled, false);
});

test('complete private capture and independent measurement read match the original queue and reviewed record', () => {
  const { value, expected } = fixture();
  assert.deepEqual(validateManagementCapture(value, expected), value);
  assert.deepEqual(validateManagementMeasurements(vectors.measurement_output, value, expected), vectors.measurement_output);
  assert.equal(value.measurements.rendered_runtime_qualified, false);
  assert.equal(value.measurements.consumer_binding_issued, false);
  assert.deepEqual(value, vectors.capture_output); // Validation does not change retained bytes.
});

test('large full-record readback has its own measured byte count, digest and multiple chunks', () => {
  const value = structuredClone(vectors.capture_output), a = value.capture_artifact, d = a.dataset, b = a.binding;
  d.read.snapshot.core.post_content = 'A long page 🌍 '.repeat(6000);
  d.read.snapshot_sha256 = delegationDigest(d.read.snapshot);
  a.migration.request.before_sha256 = d.read.snapshot_sha256;
  a.migration.rollback_snapshot = structuredClone(d.read.snapshot);
  a.migration.candidate_snapshot = managementCandidate(d.read.snapshot, a.migration.request);
  a.migration.candidate_sha256 = managementCandidateHash(a.migration.candidate_snapshot);
  Object.assign(a.review, { before_snapshot: structuredClone(d.read.snapshot), before_sha256: d.read.snapshot_sha256,
    candidate_snapshot: structuredClone(a.migration.candidate_snapshot), candidate_sha256: a.migration.candidate_sha256 });
  a.review.migration_artifact.sha256 = delegationDigest(a.migration);
  const descriptor = { ...a.review }; delete descriptor.digest; a.review.digest = delegationDigest(descriptor);
  Object.assign(b, { before_sha256: a.review.before_sha256, candidate_sha256: a.review.candidate_sha256,
    reviewed_digest: a.review.digest, request_sha256: delegationDigest(a.migration.request), migration_sha256: delegationDigest(a.migration) });
  value.measurements.snapshot_sha256 = b.before_sha256; rehash(value);
  const checked = fixture(value);
  assert.ok(value.measurements.readback_chunks > 1);
  assert.equal(value.measurements.native_calls, value.measurements.chunks_received + value.measurements.readback_chunks);
  assert.notEqual(value.measurements.readback_sha256, value.measurements.snapshot_sha256);
  const truncated = structuredClone(value); truncated.measurements.readback_chunks = 1;
  truncated.measurements.native_calls = truncated.measurements.chunks_received + 1;
  assert.throws(() => validateManagementCapture(truncated, checked.expected), { code: 'MANAGEMENT_CAPTURE_MISMATCH' });
});

test('original plugin activation order and numeric gaps survive a separately sorted parity inventory', () => {
  for (const plugins of [['z/plugin.php', 'a/plugin.php'], { 2: 'z/plugin.php', 5: 'a/plugin.php' }]) {
    const value = structuredClone(vectors.capture_output), a = value.capture_artifact, d = a.dataset, b = a.binding;
    d.active_plugins_original_json = delegationCanonical(plugins);
    d.parity_manifest.active_plugins = Object.values(plugins).sort();
    d.read.snapshot.runtime.plugins_sha256 = digest(d.active_plugins_original_json);
    d.read.snapshot_sha256 = delegationDigest(d.read.snapshot);
    a.migration.request.before_sha256 = d.read.snapshot_sha256;
    a.migration.rollback_snapshot = structuredClone(d.read.snapshot);
    a.migration.candidate_snapshot = managementCandidate(d.read.snapshot, a.migration.request);
    a.migration.candidate_sha256 = managementCandidateHash(a.migration.candidate_snapshot);
    Object.assign(a.review, { before_snapshot: structuredClone(d.read.snapshot), before_sha256: d.read.snapshot_sha256,
      candidate_snapshot: structuredClone(a.migration.candidate_snapshot), candidate_sha256: a.migration.candidate_sha256 });
    a.review.migration_artifact.sha256 = delegationDigest(a.migration);
    const descriptor = { ...a.review }; delete descriptor.digest; a.review.digest = delegationDigest(descriptor);
    Object.assign(b, { before_sha256: a.review.before_sha256, candidate_sha256: a.review.candidate_sha256,
      reviewed_digest: a.review.digest, request_sha256: delegationDigest(a.migration.request), migration_sha256: delegationDigest(a.migration) });
    value.measurements.snapshot_sha256 = b.before_sha256; rehash(value);
    const checked = fixture(value);
    assert.deepEqual(validateManagementCapture(checked.value, checked.expected), value);
    assert.notEqual(d.read.snapshot.runtime.plugins_sha256, delegationDigest(d.parity_manifest.active_plugins));
  }
});

test('rehashed captures cannot change original identity, policy, source, target, preview or expiry', () => {
  const mutations = [b => { b.requester_binding_sha256 = 'e'.repeat(64); }, b => { b.authorization_policy_sha256 = 'e'.repeat(64); },
    b => { b.provider_source_sha = 'e'.repeat(40); }, b => { b.installed_manifest_sha256 = 'e'.repeat(64); },
    b => { b.source_binding_sha256 = 'e'.repeat(64); }, b => { b.site_id++; }, b => { b.target.post_id++; },
    b => { b.operation_id = '88888888-8888-4888-8888-888888888888'; }, b => { b.reviewed_digest = 'e'.repeat(64); },
    b => { b.expires_at++; }, b => { b.job_intent_ttl_seconds++; }];
  for (const mutate of mutations) {
    const { value, expected } = fixture(); mutate(value.capture_artifact.binding); rehash(value);
    assert.throws(() => validateManagementCapture(value, expected), { code: 'MANAGEMENT_CAPTURE_MISMATCH' });
  }
});

test('rehashed runtime, schema, original paths, plugin order or unrequested record changes fail', () => {
  const mutations = [d => { d.parity_manifest.wordpress_version = '0.0.1'; }, d => { d.parity_manifest.php_version = '7.4.33'; },
    d => { d.parity_manifest.stylesheet = 'other-theme'; }, d => { d.parity_manifest.template = 'other-parent'; },
    d => { d.read.affected_paths = ['/unrequested/']; }, d => { d.acf_schema_sha256 = 'e'.repeat(64); },
    d => { d.read.snapshot.core.post_title = 'Unrequested'; d.read.snapshot_sha256 = delegationDigest(d.read.snapshot); },
    d => { d.active_plugins_original_json = '["other/plugin.php"]'; d.parity_manifest.active_plugins = ['other/plugin.php']; },
    d => { d.active_plugins_original_json += ' '; }, d => { d.active_plugins_original_json = '{"0":"acf/acf.php"}'; }];
  for (const mutate of mutations) {
    const { value, expected } = fixture(); mutate(value.capture_artifact.dataset); rehash(value);
    assert.throws(() => validateManagementCapture(value, expected), { code: 'MANAGEMENT_CAPTURE_MISMATCH' });
  }
});

test('measured counts, time, digests and no-admission claims remain independently enforced', () => {
  const mutations = [m => { m.native_calls++; }, m => { m.chunks_received++; }, m => { m.dataset_bytes++; },
    m => { m.captured_at = 1; }, m => { m.captured_at += 10; }, m => { m.binding_sha256 = 'e'.repeat(64); },
    m => { m.snapshot_sha256 = 'e'.repeat(64); }, m => { m.acf_schema_sha256 = 'e'.repeat(64); },
    m => { m.source_binding_sha256 = 'e'.repeat(64); }, m => { m.readback_bytes++; },
    m => { m.readback_chunks++; m.native_calls++; }, m => { m.readback_sha256 = m.snapshot_sha256; }, m => { m.rendered_runtime_qualified = true; }, m => { m.consumer_binding_issued = true; }];
  for (const mutate of mutations) {
    const { value, expected } = fixture(); mutate(value.measurements);
    assert.throws(() => validateManagementCapture(value, expected));
  }
  const { value, expected } = fixture(), measured = structuredClone(vectors.measurement_output);
  measured.measurements.native_calls++; measured.measurement_record_sha256 = delegationDigest(measured.measurements);
  assert.throws(() => validateManagementMeasurements(measured, value, expected), { code: 'MANAGEMENT_CAPTURE_MISMATCH' });
});

test('original preview and persisted intent lifetime cannot be extended or replaced with captured context', () => {
  const { value, expected } = fixture(), b = expected.queuedContext.binding;
  assert.throws(() => validateManagementCapture(value, { ...expected, now: b.expires_at * 1000 }), { code: 'MANAGEMENT_CAPTURE_MISMATCH' });
  assert.throws(() => validateManagementCapture(value, { ...expected, now: (b.created_at - 1) * 1000 }), { code: 'MANAGEMENT_CAPTURE_MISMATCH' });
  assert.throws(() => validateManagementCapture(value, { ...expected, queuedContext: vectors.context }), { code: 'MANAGEMENT_CAPTURE_MISMATCH' });
  b.expires_at++; expected.queuedContext.binding_sha256 = delegationDigest(b);
  assert.throws(() => validateManagementCapture(value, expected), { code: 'MANAGEMENT_CAPTURE_MISMATCH' });
});

test('private response allowance does not replace the smaller parity byte bound', () => {
  const { value, expected } = fixture(), d = value.capture_artifact.dataset;
  d.parity_manifest.roots = [{ path: 'themes/fixture', kind: 'directory', present: true,
    files: Array.from({ length: 7300 }, (_, i) => ({ path: 'x'.repeat(500) + i, sha256: 'a'.repeat(64) })) }];
  rehash(value);
  assert.ok(managementCaptureTypes.capture_output.safeParse(value).success);
  assert.ok(Buffer.byteLength(delegationCanonical(d)) < 4400000);
  assert.ok(Buffer.byteLength(delegationCanonical(d.parity_manifest)) > 4194304);
  assert.throws(() => validateManagementCapture(value, expected), { code: 'MANAGEMENT_CAPTURE_MISMATCH' });
});

test('private response allowance does not replace the smaller dataset node bound', () => {
  const { value, expected } = fixture(), d = value.capture_artifact.dataset;
  d.parity_manifest.roots = Array.from({ length: 3 }, (_, root) => ({ path: `themes/fixture-${root}`, kind: 'directory', present: true,
    files: Array.from({ length: 12000 }, (_, i) => ({ path: `file-${i}.php`, sha256: 'a'.repeat(64) })) }));
  rehash(value);
  assert.ok(managementCaptureTypes.capture_output.safeParse(value).success);
  assert.ok(Buffer.byteLength(delegationCanonical(d.parity_manifest)) < 4194304);
  assert.throws(() => validateManagementCapture(value, expected), { code: 'MANAGEMENT_CAPTURE_MISMATCH' });
});
