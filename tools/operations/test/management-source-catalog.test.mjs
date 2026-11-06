import test from 'node:test';
import assert from 'node:assert/strict';
import { generateKeyPairSync, sign } from 'node:crypto';
import { delegationCanonical, delegationDigest } from '../src/delegation.mjs';
import { managementSourceWire as wire } from '../src/management-source-transfer.mjs';
import { ManagementSourceCatalogRegistry } from '../src/management-source-catalog.mjs';
import { managementEvidenceFixture } from './fixtures/management-evidence.mjs';

function fixture(t) {
  const f = managementEvidenceFixture(t); f.recordCapture();
  const pair = Object.fromEntries(['root', 'registrar', 'identity', 'qa'].map(role => [role, generateKeyPairSync('ed25519')]));
  const keys = Object.fromEntries(Object.entries(pair).map(([role, p]) => [role, [{ id: role,
    public_key_base64: p.publicKey.export({ format: 'der', type: 'spki' }).subarray(-32).toString('base64') }]]));
  const binding = f.ledger.captured(f.job).context.binding;
  const catalog = { schema: 1, adapter: 'content-management-source-catalog-v1',
    catalog_id: '11111111-1111-4111-8111-111111111111', issued_at: binding.created_at - 10,
    expires_at: binding.expires_at + 10, native_reads: true, artifacts: [] };
  const envelope = (bytes = Buffer.from(delegationCanonical(catalog)), purpose = wire.purposes.catalog, role = 'root') => ({
    key_id: role, payload_base64: bytes.toString('base64'),
    signature_base64: sign(null, Buffer.concat([Buffer.from(purpose), bytes]), pair[role].privateKey).toString('base64') });
  const state = { registry: { schema: 1, keys, catalogs: [] }, pin: null, read: null, readPin: null };
  const repin = () => { state.pin = delegationDigest(state.registry); };
  const publish = () => { state.registry.catalogs = [{ sha256: delegationDigest(catalog), envelope: envelope() }]; repin(); };
  publish();
  const options = { ledger: f.ledger, now: () => f.state.now,
    current: () => { state.read?.(); return state.registry; }, expectedSha256: () => state.readPin?.() ?? state.pin };
  const registry = new ManagementSourceCatalogRegistry(options);
  return { ...f, pair, keys, binding, catalog, envelope, config: state, options, registry, repin, publish,
    resolve: () => registry.resolve(f.job, delegationDigest(catalog)) };
}
const refused = callback => assert.throws(callback, { code: 'MANAGEMENT_SOURCE_CATALOG' });

test('source catalog is missing by default and cannot acquire authority from an incoming catalog', t => {
  const f = fixture(t), absent = new ManagementSourceCatalogRegistry({ ledger: f.ledger });
  assert.throws(() => absent.resolve(f.job, delegationDigest(f.catalog)), { code: 'MANAGEMENT_SOURCE_MISSING_CATALOG' });
  refused(() => new ManagementSourceCatalogRegistry({ ...f.options, ledger: {} }));
  refused(() => new ManagementSourceCatalogRegistry({ ledger: f.ledger, current: () => f.config.registry }));
  assert.throws(() => f.registry.resolve('99999999-9999-4999-8999-999999999999', delegationDigest(f.catalog)));
});

test('independent owner signature resolves exact canonical catalog for original immutable job only', t => {
  const f = fixture(t), before = f.store.db.prepare('SELECT total_changes() AS n').get().n;
  const value = f.resolve();
  assert.deepEqual(value.catalog, f.catalog); assert.deepEqual(value.signedCatalog, f.config.registry.catalogs[0].envelope);
  assert.equal(value.ownerCatalogSha256, delegationDigest(f.catalog)); assert.equal(value.registrySha256, f.config.pin);
  assert.equal(f.store.db.prepare('SELECT total_changes() AS n').get().n, before);
  value.catalog.native_reads = false; value.signedCatalog.key_id = 'caller';
  assert.equal(f.resolve().catalog.native_reads, true); assert.equal(f.resolve().signedCatalog.key_id, 'root');
});

test('root key aliases to registrar, identity or QA and duplicate key identities are rejected', t => {
  const f = fixture(t), original = structuredClone(f.config.registry);
  for (const role of ['registrar', 'identity', 'qa']) {
    f.config.registry = structuredClone(original);
    f.config.registry.keys[role][0].public_key_base64 = f.keys.root[0].public_key_base64; f.repin(); refused(f.resolve);
  }
  f.config.registry = structuredClone(original);
  f.config.registry.keys.root.push({ ...f.keys.root[0], public_key_base64: f.keys.qa[0].public_key_base64 }); f.repin(); refused(f.resolve);
  f.config.registry = structuredClone(original);
  f.config.registry.keys.root[0].public_key_base64 = 'A'.repeat(43) + '!'; f.repin(); refused(f.resolve);
});

test('wrong signer and cross-purpose valid signatures cannot authorize a source catalog', t => {
  const f = fixture(t);
  for (const role of ['identity', 'registrar', 'qa']) {
    f.config.registry.catalogs[0].envelope = f.envelope(undefined, wire.purposes.catalog, role); f.repin(); refused(f.resolve);
  }
  f.config.registry.catalogs[0].envelope = f.envelope(undefined, wire.purposes.capture); f.repin(); refused(f.resolve);
  f.publish(); f.config.registry.catalogs[0].envelope.signature_base64 = Buffer.alloc(64).toString('base64'); f.repin(); refused(f.resolve);
});

test('signed malformed, noncanonical and oversized original bytes are refused', t => {
  const f = fixture(t);
  for (const raw of [Buffer.from(JSON.stringify(f.catalog, null, 2)), Buffer.from([255]),
    Buffer.from(delegationCanonical(f.catalog).replace('"schema":1', '"schema":1,"schema":1')),
    Buffer.from(delegationCanonical({ ...f.catalog, authority_verified: true })), Buffer.alloc(98305, 32)]) {
    f.config.registry.catalogs[0].envelope = f.envelope(raw); f.repin(); refused(f.resolve);
  }
});

test('catalog identity cannot be substituted by registry label, duplicate entries or changed configuration', t => {
  const f = fixture(t), wanted = delegationDigest(f.catalog);
  assert.throws(() => f.registry.resolve(f.job, 'f'.repeat(64)), { code: 'MANAGEMENT_SOURCE_MISSING_CATALOG' });
  f.config.registry.catalogs[0].sha256 = 'f'.repeat(64); f.repin();
  refused(() => f.registry.resolve(f.job, 'f'.repeat(64)));
  f.publish(); f.config.registry.catalogs.push(structuredClone(f.config.registry.catalogs[0])); f.repin(); refused(f.resolve);
  f.publish(); f.config.registry.catalogs[0].sha256 = 'e'.repeat(64);
  refused(() => f.registry.resolve(f.job, wanted));
});

test('owner catalog must predate the original queue and cover its whole lifetime', t => {
  const f = fixture(t), original = { ...f.catalog };
  for (const patch of [{ issued_at: f.binding.created_at + 1 }, { expires_at: f.binding.expires_at - 1 },
    { issued_at: f.binding.expires_at + 1, expires_at: f.binding.expires_at }]) {
    Object.assign(f.catalog, original, patch); f.publish(); refused(f.resolve);
  }
  Object.assign(f.catalog, original, { issued_at: f.binding.created_at, expires_at: f.binding.expires_at }); f.publish();
  assert.deepEqual(f.resolve().catalog, f.catalog);
  f.state.now = f.binding.expires_at * 1000; assert.throws(f.resolve);
});

test('original revocation and configuration changes during resolution withhold catalog', t => {
  const f = fixture(t); let reads = 0;
  f.config.read = () => { if (++reads === 1) f.access.recordPendingRevocation(f.job); };
  assert.throws(f.resolve);
  const g = fixture(t); let pins = 0;
  g.config.readPin = () => ++pins === 1 ? g.config.pin : 'f'.repeat(64); refused(g.resolve);
  const h = fixture(t); let times = 0;
  const slow = new ManagementSourceCatalogRegistry({ ...h.options, now: () => h.state.now + (++times === 1 ? 0 : 5000) });
  refused(() => slow.resolve(h.job, delegationDigest(h.catalog)));
});

test('duplicate catalog artifact identities are refused even when owner signature is valid', t => {
  const f = fixture(t), a = { artifact_id: 'fixture', root_kind: 'directory', relative_inventory_sha256: 'a'.repeat(64),
    archive_sha256: 'b'.repeat(64), archive_format: 'zip', maximum_archive_bytes: 1024 };
  f.catalog.artifacts = [a, { ...a }]; f.publish(); refused(f.resolve);
});
