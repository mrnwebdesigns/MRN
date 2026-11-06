import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync, mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { generateKeyPairSync, sign, verify } from 'node:crypto';
import { delegationCanonical, delegationDigest } from '../src/delegation.mjs';
import { ManagementSourceCapture } from '../src/management-source-capture.mjs';
import { ManagementSourceCatalogRegistry } from '../src/management-source-catalog.mjs';
import { managementSourceWire as wire } from '../src/management-source-transfer.mjs';
import { managementEvidenceFixture } from './fixtures/management-evidence.mjs';

function fixture(t, file = ':memory:') {
  const f = managementEvidenceFixture(t, file); f.recordCapture();
  const pairs = Object.fromEntries(['root', 'registrar', 'identity', 'qa'].map(role => [role, generateKeyPairSync('ed25519')]));
  const keys = Object.fromEntries(Object.entries(pairs).map(([role, p]) => [role, [{ id: role,
    public_key_base64: p.publicKey.export({ format: 'der', type: 'spki' }).subarray(-32).toString('base64') }]]));
  const original = f.ledger.captured(f.job);
  const catalog = { schema: 1, adapter: 'content-management-source-catalog-v1',
    catalog_id: '11111111-1111-4111-8111-111111111111', issued_at: original.context.binding.created_at,
    expires_at: original.context.binding.expires_at, native_reads: true, artifacts: [] };
  const raw = Buffer.from(delegationCanonical(catalog)), envelope = { key_id: 'root', payload_base64: raw.toString('base64'),
    signature_base64: sign(null, Buffer.concat([Buffer.from(wire.purposes.catalog), raw]), pairs.root.privateKey).toString('base64') };
  const configuration = { schema: 1, keys, catalogs: [{ sha256: delegationDigest(catalog), envelope }] };
  const state = { pin: delegationDigest(configuration), contextReads: 0, nativeReads: 0, staffReads: 0 };
  const catalogs = new ManagementSourceCatalogRegistry({ ledger: f.ledger, current: () => configuration,
    expectedSha256: () => state.pin, now: () => f.state.now });
  const context = { management: original.context, source_recipe_catalog_sha256: delegationDigest(catalog) };
  const slot = JSON.parse(readFileSync(new URL('./fixtures/content-management-source-transfer-v1.json', import.meta.url))).capture_output.slot;
  const options = { ledger: f.ledger, catalogs, now: () => f.state.now, monotonic: () => f.state.mono,
    registrar: { keyId: 'registrar', privateKey: pairs.registrar.privateKey },
    verifyStaff: async actor => {
      state.staffReads++; assert.equal(actor.googleSubject, f.actor.googleSubject);
      assert.equal(actor.verifiedEmail, f.actor.verifiedEmail); assert.equal(actor.token, undefined); await state.staff?.();
    },
    contextReader: { async read(job) {
      state.contextReads++; assert.equal(job, f.job); await state.context?.(); return structuredClone(context);
    } },
    captureReader: { async capture(input) {
      state.nativeReads++;
      state.requestSha256 = delegationDigest(input);
      const signed = input.signed_request, bytes = Buffer.from(signed.payload_base64, 'base64'), claims = JSON.parse(bytes);
      assert.equal(signed.key_id, 'registrar');
      assert.equal(verify(null, Buffer.concat([Buffer.from(wire.purposes.capture), bytes]), pairs.registrar.publicKey,
        Buffer.from(signed.signature_base64, 'base64')), true);
      assert.equal(delegationCanonical(claims), bytes.toString());
      assert.equal(claims.action, 'capture-management-source'); assert.deepEqual(claims.context, context);
      assert.deepEqual(input.signed_source_catalog, envelope);
      const output = structuredClone({ schema: 1, context, state: 'complete', slot });
      await state.native?.(output); return output;
    } } };
  const capture = new ManagementSourceCapture(options);
  return { ...f, sourceState: state, context, slot, configuration, options, capture,
    run: () => capture.capture(f.job), resolve: () => capture.resolve(f.ledger.captured(f.job)) };
}
const refused = callback => assert.rejects(callback, { code: 'MANAGEMENT_SOURCE_CAPTURE' });
const row = f => f.store.db.prepare('SELECT * FROM management_source_captures WHERE job=?').get(f.job);

test('authenticated original context and signed catalog produce one immutable source mapping', async t => {
  const f = fixture(t); const result = await f.run();
  assert.deepEqual(result, { context: f.context, slot: f.slot, ownerCatalogSha256: f.context.source_recipe_catalog_sha256 });
  assert.equal(row(f).state, 'complete');
  assert.equal(JSON.parse(row(f).body).requestSha256, f.sourceState.requestSha256);
  assert.deepEqual([f.sourceState.contextReads, f.sourceState.nativeReads, f.sourceState.staffReads], [1, 1, 2]);
  const before = f.store.db.prepare('SELECT total_changes() AS n').get().n;
  for (let i = 0; i < 80; i++) assert.deepEqual(f.resolve(), result);
  assert.equal(f.store.db.prepare('SELECT total_changes() AS n').get().n, before);
  assert.equal(f.sourceState.contextReads, 1); assert.equal(f.sourceState.nativeReads, 1);
  result.slot.transfer_manifest_sha256 = 'f'.repeat(64);
  assert.equal(f.resolve().slot.transfer_manifest_sha256, f.slot.transfer_manifest_sha256);
  await refused(f.run);
  const text = JSON.stringify(row(f));
  for (const forbidden of ['never-store-this-token', 'staff@', 'data_base64', 'signed_request', 'signed_source_catalog']) assert.ok(!text.includes(forbidden));
});

test('source capture rejects substituted original context before native capture', async t => {
  for (const mutate of [v => { v.management.binding.site_id++; }, v => { v.management.evidence_policy_epoch++; },
    v => { v.management.capture_artifact_sha256 = 'e'.repeat(64); }, v => { v.caller_email = 'owner@mrnwebdesigns.com'; }]) {
    const f = fixture(t); mutate(f.context); await refused(f.run);
    assert.equal(f.sourceState.nativeReads, 0); assert.equal(row(f).state, 'failed'); await refused(f.run);
  }
});

test('missing or changed owner catalog and original staff denial withhold source capture', async t => {
  const f = fixture(t); f.configuration.catalogs = []; f.sourceState.pin = delegationDigest(f.configuration);
  await refused(f.run); assert.equal(f.sourceState.nativeReads, 0);
  const g = fixture(t); g.sourceState.staff = () => { throw new Error('revoked'); };
  await refused(g.run); assert.equal(g.sourceState.contextReads, 0); assert.equal(g.sourceState.nativeReads, 0);
});

test('pending revocation or trust change while native source arrives leaves a spent failed record', async t => {
  const f = fixture(t); f.sourceState.native = () => f.access.recordPendingRevocation(f.job);
  await refused(f.run); assert.equal(row(f).state, 'failed'); assert.equal(row(f).body, null);
  const g = fixture(t); g.sourceState.native = () => { g.configuration.keys.qa[0].id = 'rotated'; g.sourceState.pin = delegationDigest(g.configuration); };
  await refused(g.run); assert.equal(row(g).state, 'failed'); assert.equal(row(g).body, null);
  const h = fixture(t); h.sourceState.staff = () => { if (h.sourceState.staffReads === 2) throw new Error('suspended'); };
  await refused(h.run); assert.equal(row(h).body, null);
});

test('mismatched capture and invalid slot arithmetic never become a mapping', async t => {
  for (const mutate of [v => { v.context.management.parity_sha256 = 'e'.repeat(64); }, v => { v.slot.manifest_frames++; },
    v => { v.slot.total_frames++; }, v => { v.slot.native_files = 0; }, v => { v.slot.admitted = true; },
    v => { v.state = 'uncertain'; }]) {
    const f = fixture(t); f.sourceState.native = mutate; await refused(f.run); assert.equal(row(f).body, null);
  }
});

test('concurrent duplicate capture does not interfere with the sole original attempt', async t => {
  const f = fixture(t); let release, entered;
  const reached = new Promise(resolve => { entered = resolve; });
  f.sourceState.native = () => new Promise(resolve => { release = resolve; entered(); });
  const first = f.run(); await reached; await refused(f.run);
  assert.equal(row(f).state, 'capturing'); assert.equal(f.sourceState.nativeReads, 1);
  release(); await first; assert.equal(row(f).state, 'complete');
});

test('context and native deadlines include authorization and never renew the original lifetime', async t => {
  const f = fixture(t); f.sourceState.context = () => { f.state.mono += 20000; };
  await refused(f.run); assert.equal(f.sourceState.nativeReads, 0);
  const g = fixture(t); g.sourceState.native = () => { g.state.mono += 120000; };
  await refused(g.run); assert.equal(row(g).body, null);
  const h = fixture(t); h.sourceState.native = () => { h.state.now = h.context.management.binding.expires_at * 1000; };
  await refused(h.run); assert.equal(row(h).body, null);
});

test('failed response and interrupted capture remain consumed after SQLite reopen', async t => {
  const directory = mkdtempSync(join(tmpdir(), 'mrn-source-capture-')); t.after(() => rmSync(directory, { recursive: true, force: true }));
  for (const status of ['failed', 'capturing']) {
    const path = join(directory, `${status}.sqlite`), f = fixture(t, path);
    if (status === 'failed') {
      f.sourceState.native = () => { throw new Error('lost'); }; await refused(f.run);
    } else f.store.db.prepare('INSERT INTO management_source_captures VALUES(?,?,?,?,NULL,NULL)')
      .run(f.job, 'capturing', delegationDigest(f.context.management), f.state.now);
    f.store.close(); f.state.closed = true;
    const g = fixture(t, path); await refused(g.run);
    assert.equal(row(g).state, status); assert.equal(g.sourceState.contextReads, 0); assert.equal(g.sourceState.nativeReads, 0);
  }
});

test('retained resolution refuses revoked, expired, changed or corrupt original state without backend polling', async t => {
  const f = fixture(t); await f.run(); f.access.recordPendingRevocation(f.job); assert.throws(f.resolve);
  const g = fixture(t); await g.run(); g.sourceState.pin = 'e'.repeat(64); assert.throws(g.resolve);
  const h = fixture(t); await h.run(); h.state.now = h.context.management.binding.expires_at * 1000; assert.throws(h.resolve);
  const i = fixture(t); await i.run(); i.store.db.prepare('UPDATE management_source_captures SET body=?').run('{}'); assert.throws(i.resolve);
  for (const value of [f,g,h,i]) assert.equal(value.sourceState.nativeReads, 1);
});
