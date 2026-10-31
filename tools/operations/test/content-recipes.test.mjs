import test from 'node:test';
import assert from 'node:assert/strict';
import { generateKeyPairSync, sign } from 'node:crypto';
import { readFileSync } from 'node:fs';
import { delegationCanonical, delegationDigest } from '../src/delegation.mjs';
import { verifyContentRecipeCatalog, matchContentRecipe } from '../src/content-recipes.mjs';

const vectors = JSON.parse(readFileSync(new URL('./fixtures/content-qualification-contract.json', import.meta.url), 'utf8'));
const catalogVector = vectors.cases.find(row => row.definition === 'catalog_claims').value;
const now = 1700000100000;
const scope = { site_id: 85, site_url: 'https://child.fixture.example.invalid', target: { kind: 'post', post_type: 'page', post_id: 12, field: 'post_title' } };
function fixture() {
  const keys = Array.from({ length: 5 }, () => generateKeyPairSync('ed25519'));
  const key = index => ({ id: `fixture-${index}`, public_key_base64: keys[index].publicKey.export({ type: 'spki', format: 'der' }).subarray(-32).toString('base64') });
  const catalog = structuredClone(catalogVector);
  const policy = { catalog_epoch: 1, trusted_recipe_catalog_sha256: delegationDigest(catalog), recipe_root_keys: [key(0), key(4)],
    registrar_keys: [key(1)], identity_keys: [key(2)], qa_keys: [key(3)] };
  const envelope = (value = catalog, { index = 0, prefix = 'mrn-content-recipe-catalog-v1:', bytes = Buffer.from(delegationCanonical(value)) } = {}) => ({
    key_id: key(index).id, payload_base64: bytes.toString('base64'), signature_base64: sign(null, Buffer.concat([Buffer.from(prefix), bytes]), keys[index].privateKey).toString('base64') });
  const pin = value => ({ ...policy, trusted_recipe_catalog_sha256: delegationDigest(value) });
  return { catalog, policy, envelope, pin, key };
}

test('approved catalogs verify with either independently configured root and preserve the exact payload digest', () => {
  const f = fixture();
  for (const index of [0, 4]) {
    const result = verifyContentRecipeCatalog(f.envelope(f.catalog, { index }), f.policy, now);
    assert.deepEqual(result, { catalog: f.catalog, catalogSha256: delegationDigest(f.catalog) });
  }
  const result = matchContentRecipe(f.envelope(), f.policy, f.catalog.recipes[0], scope, now);
  assert.deepEqual(result, { recipe: f.catalog.recipes[0], recipeSha256: delegationDigest(f.catalog.recipes[0]),
    catalogSha256: delegationDigest(f.catalog), catalogEpoch: 1, catalogRevision: 1 });
  assert.equal(Object.hasOwn(result, 'qualification_binding'), false);
});

test('registrar, identity, QA, unknown keys and other signature purposes cannot issue recipe catalogs', () => {
  const f = fixture();
  for (const index of [1, 2, 3]) assert.throws(() => verifyContentRecipeCatalog(f.envelope(f.catalog, { index }), f.policy, now), { code: 'RECIPE_KEY' });
  assert.throws(() => verifyContentRecipeCatalog({ ...f.envelope(), key_id: 'unknown' }, f.policy, now), { code: 'RECIPE_KEY' });
  for (const prefix of ['mrn-content-catalog-v1:', 'mrn-content-target-binding-v1:', 'mrn-content-qa-v1:', ''])
    assert.throws(() => verifyContentRecipeCatalog(f.envelope(f.catalog, { prefix }), f.policy, now), { code: 'RECIPE_SIGNATURE' });
  const tampered = { ...f.envelope(), payload_base64: Buffer.from(delegationCanonical({ ...f.catalog, catalog_revision: 2 })).toString('base64') };
  assert.throws(() => verifyContentRecipeCatalog(tampered, f.policy, now), { code: 'RECIPE_SIGNATURE' });
});

test('trust configuration requires all independent key roles and rejects reused identifiers or key bytes', () => {
  const f = fixture();
  for (const role of ['recipe_root_keys', 'registrar_keys', 'identity_keys', 'qa_keys']) {
    assert.throws(() => verifyContentRecipeCatalog(f.envelope(), { ...f.policy, [role]: [] }, now), { code: 'RECIPE_TRUST' });
    for (const bad of [f.key(0), { ...f.key(0), id: 'different-id' }, { ...f.key(1), id: f.key(0).id }]) {
      const policy = structuredClone(f.policy); policy[role].push(bad);
      assert.throws(() => verifyContentRecipeCatalog(f.envelope(), policy, now), { code: 'RECIPE_KEY_SEPARATION' });
    }
  }
  const noncanonical = { ...f.key(3), public_key_base64: 'A'.repeat(42) + 'B=' };
  assert.throws(() => verifyContentRecipeCatalog(f.envelope(), { ...f.policy, qa_keys: [noncanonical] }, now), { code: 'RECIPE_KEY' });
  assert.throws(() => verifyContentRecipeCatalog(f.envelope(), { ...f.policy, actor: 'caller' }, now), { code: 'RECIPE_TRUST' });
});

test('catalog approval binds the exact payload and current epoch on every call', () => {
  const f = fixture();
  for (const policy of [{ ...f.policy, catalog_epoch: 2 }, { ...f.policy, trusted_recipe_catalog_sha256: '0'.repeat(64) }])
    assert.throws(() => verifyContentRecipeCatalog(f.envelope(), policy, now), { code: 'RECIPE_POLICY_CHANGED' });
  const changed = { ...f.catalog, catalog_revision: 2 };
  assert.throws(() => verifyContentRecipeCatalog(f.envelope(changed), f.policy, now), { code: 'RECIPE_POLICY_CHANGED' });
  assert.equal(verifyContentRecipeCatalog(f.envelope(changed), f.pin(changed), now).catalog.catalog_revision, 2);
  assert.throws(() => matchContentRecipe(f.envelope(), f.pin(changed), f.catalog.recipes[0], scope, now), { code: 'RECIPE_POLICY_CHANGED' });
});

test('expiry and future issuance fail closed at their exact clock boundaries', () => {
  const f = fixture();
  assert.ok(verifyContentRecipeCatalog(f.envelope(), f.policy, f.catalog.issued_at * 1000));
  assert.ok(verifyContentRecipeCatalog(f.envelope(), f.policy, f.catalog.expires_at * 1000 - 1));
  for (const at of [f.catalog.issued_at * 1000 - 1, f.catalog.expires_at * 1000])
    assert.throws(() => verifyContentRecipeCatalog(f.envelope(), f.policy, at), { code: 'RECIPE_EXPIRED' });
  for (const at of [NaN, Infinity, -1, now + 0.5])
    assert.throws(() => verifyContentRecipeCatalog(f.envelope(), f.policy, at), { code: 'RECIPE_CLOCK' });
});

test('closed catalog schema requires all consumers and prohibits widened origins, fields or embedded keys', () => {
  const f = fixture();
  const changes = [c => { c.audiences.pop(); }, c => { c.audiences[0] = c.audiences[1]; },
    c => { c.audiences.push('other-consumer'); }, c => { c.public_key_base64 = f.key(0).public_key_base64; },
    c => { c.recipes[0].origin_scope = 'production'; }, c => { c.recipes[0].eligible_fields = ['post_meta']; },
    c => { c.recipes[0].eligible_post_types = ['attachment']; }, c => { c.recipes[0].callback = 'arbitrary'; }];
  for (const change of changes) {
    const catalog = structuredClone(f.catalog); change(catalog);
    assert.throws(() => verifyContentRecipeCatalog(f.envelope(catalog), f.pin(catalog), now), { code: 'RECIPE_SCHEMA' });
  }
});

test('canonical byte validation rejects alternate encodings, duplicate JSON fields and invalid UTF-8', () => {
  const f = fixture(), canonical = delegationCanonical(f.catalog);
  const payloads = [Buffer.from(JSON.stringify(f.catalog, null, 2)), Buffer.from(canonical.replace('"schema":1', '"schema":1e0')),
    Buffer.from(canonical.replace('"schema":1', '"schema":1,"schema":1')),
    Buffer.from(canonical.replace('"schema":1', '"\\u0073chema":1')),
    Buffer.concat([Buffer.from(canonical), Buffer.from([0xff])])];
  for (const bytes of payloads) assert.throws(() => verifyContentRecipeCatalog(f.envelope(f.catalog, { bytes }), f.policy, now),
    error => ['RECIPE_CANONICAL', 'RECIPE_SCHEMA'].includes(error.code));
  assert.throws(() => verifyContentRecipeCatalog(f.envelope(f.catalog, { bytes: Buffer.from('{') }), f.policy, now), { code: 'RECIPE_SCHEMA' });
});

test('noncanonical base64, oversized envelopes and extra envelope fields are rejected', () => {
  const f = fixture(), envelope = f.envelope();
  const signature = envelope.signature_base64;
  const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/';
  const index = alphabet.indexOf(signature[85]);
  const altered = signature.slice(0, 85) + alphabet[index + 1] + '==';
  assert.deepEqual(Buffer.from(altered, 'base64'), Buffer.from(signature, 'base64'));
  assert.throws(() => verifyContentRecipeCatalog({ ...envelope, signature_base64: altered }, f.policy, now), { code: 'RECIPE_ENCODING' });
  for (const changed of [{ payload_base64: 'A'.repeat(131076) }, { public_key_base64: f.key(0).public_key_base64 }, { payload_base64: envelope.payload_base64 + '\n' }])
    assert.throws(() => verifyContentRecipeCatalog({ ...envelope, ...changed }, f.policy, now), { code: 'RECIPE_ENVELOPE' });
});

test('duplicate recipe identifiers and unsorted recipe or eligibility lists cannot be approved', () => {
  const f = fixture();
  const changes = [c => { c.recipes.push({ ...c.recipes[0], provider_source_sha: 'c'.repeat(40) }); },
    c => { c.recipes.push({ ...c.recipes[0], recipe_id: 'aaa' }); },
    c => { c.recipes[0].eligible_post_types.reverse(); },
    c => { c.recipes[0].eligible_fields = ['post_title', 'post_content']; }];
  for (const change of changes) {
    const catalog = structuredClone(f.catalog); change(catalog);
    assert.throws(() => verifyContentRecipeCatalog(f.envelope(catalog), f.pin(catalog), now), { code: 'RECIPE_ORDER' });
  }
});

test('every independently pinned recipe value must match before any worker request is eligible', () => {
  const f = fixture(), recipe = f.catalog.recipes[0];
  for (const field of Object.keys(recipe).filter(key => /sha(?:256)?$/.test(key))) {
    const changed = { ...recipe, [field]: 'c'.repeat(recipe[field].length) };
    assert.throws(() => matchContentRecipe(f.envelope(), f.policy, changed, scope, now), { code: 'RECIPE_SOURCE' });
  }
  for (const changed of [{ ...recipe, recipe_id: 'unknown' }, { ...recipe, eligible_fields: ['post_title', 'post_excerpt'] },
    { ...recipe, eligible_post_types: ['page'] }])
    assert.throws(() => matchContentRecipe(f.envelope(), f.policy, changed, scope, now), { code: 'RECIPE_SOURCE' });
});

test('recipe matching rejects real origins, unqualified fields and ambiguous or caller-expanded target scopes', () => {
  const f = fixture(), recipe = f.catalog.recipes[0];
  for (const changed of [{ ...scope, site_url: 'https://mrnwebdesigns.com' }, { ...scope, site_url: 'https://child.example.invalid.evil.example' },
    { ...scope, site_url: 'https://user@child.example.invalid' }, { ...scope, site_id: 0 }, { ...scope, actor: 'caller' },
    { ...scope, target: { ...scope.target, post_id: 0 } }, { ...scope, target: { ...scope.target, callback: 'arbitrary' } }])
    assert.throws(() => matchContentRecipe(f.envelope(), f.policy, recipe, changed, now), { code: 'RECIPE_SCOPE' });
  assert.throws(() => matchContentRecipe(f.envelope(), f.policy, recipe, { ...scope, target: { ...scope.target, field: 'post_content' } }, now), { code: 'RECIPE_INELIGIBLE' });
  const catalog = structuredClone(f.catalog); catalog.recipes[0].eligible_post_types = ['post'];
  assert.throws(() => matchContentRecipe(f.envelope(catalog), f.pin(catalog), catalog.recipes[0], scope, now), { code: 'RECIPE_INELIGIBLE' });
});
