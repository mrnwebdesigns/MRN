import test from 'node:test';
import assert from 'node:assert/strict';
import { generateKeyPairSync, verify } from 'node:crypto';
import { readFileSync, writeFileSync, mkdtempSync, chmodSync, symlinkSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { IdentitySigner, delegationCanonical, delegationDigest, delegationPrefix, readIdentityKey } from '../src/delegation.mjs';

test('delegation canonical bytes match shared PHP/Node vectors and reject ambiguous inputs', () => {
  const fixture = JSON.parse(readFileSync(new URL('./fixtures/delegation-v1.json', import.meta.url), 'utf8'));
  for (const v of fixture.canonical_vectors) {
    assert.equal(delegationCanonical(v.input), v.canonical);
    assert.equal(delegationDigest(v.input), v.sha256);
  }
  const cycle = {}; cycle.loop = cycle;
  for (const value of [undefined, NaN, Infinity, -0, 1.1, 9007199254740992, '\ud800', { 'é': 1 }, { a: undefined }, new Date(), cycle, new Array(2), JSON.parse('{"__proto__":1}')]) {
    assert.throws(() => delegationCanonical(value), { code: 'DELEGATION_INPUT' });
  }
});

test('concurrent operators produce independent signatures bound to their identity, request and expiry', async () => {
  const { publicKey, privateKey } = generateKeyPairSync('ed25519');
  const now = 1800000000000;
  const signer = new IdentitySigner({ keyId: 'fixture-only', privateKey, connectionId: 'con_MRNGoogle', now: () => now });
  const challenge = { nonce: 'c'.repeat(64), expires_at: now / 1000 + 120, authenticated_connection_sha256: 'a'.repeat(64), policy_sha256: 'b'.repeat(64) };
  const actor = id => ({ subject: `google-oauth2|${id}`, googleSubject: id, verifiedEmail: `staff${id}@mrnwebdesigns.com`, expiresAt: now + 30000, identityProvider: 'google-oauth2', googleConnection: 'con_MRNGoogle', hostedDomain: 'mrnwebdesigns.com' });
  const results = await Promise.all(['123', '456'].map(async id => signer.sign(actor(id), 'mainwp/get-site-v1', { site_id_or_domain: 'https://example.test' }, challenge)));
  const payloads = results.map(r => {
    const bytes = Buffer.from(r.payload_base64, 'base64');
    assert.equal(verify(null, Buffer.concat([Buffer.from(delegationPrefix), bytes]), publicKey, Buffer.from(r.signature_base64, 'base64')), true);
    assert.equal(verify(null, bytes, publicKey, Buffer.from(r.signature_base64, 'base64')), false);
    return JSON.parse(bytes);
  });
  assert.notEqual(payloads[0].request_id, payloads[1].request_id);
  assert.deepEqual(payloads.map(p => p.google_sub), ['123', '456']);
  assert.equal(payloads[0].expires_at, now / 1000 + 30);
  assert.equal(payloads[0].arguments_sha256, delegationDigest({ site_id_or_domain: 'https://example.test' }));
  for (const override of [{ subject: 'auth0|linked' }, { googleSubject: 'other' }, { verifiedEmail: 'staff@outside.test' }, { googleConnection: 'con_Other' }, { expiresAt: now - 1 }]) {
    assert.throws(() => signer.sign({ ...actor('123'), ...override }, 'mainwp/get-site-v1', {}, challenge), { code: 'AUTH_REQUIRED' });
  }
  assert.throws(() => signer.sign(actor('123'), 'mainwp/get-site-v1', {}, { ...challenge, expires_at: now / 1000 - 1 }), { code: 'DELEGATION_EXPIRED' });
  for (const ability of ['mrn-mainwp/get-content-policy-v2', 'mrn-mainwp/request-content-edit-qualification-existing-v2']) {
    const envelope = signer.sign(actor('123'), ability, {}, challenge);
    assert.equal(JSON.parse(Buffer.from(envelope.payload_base64, 'base64')).ability, ability);
  }
  for (const ability of ['mrn-mainwp/request-content-edit-qualification-existing-v3', 'mainwp/request-content-edit-qualification-existing-v2',
    'mrn-mainwp/execute-content-change-v2', 'mrn-mainwp/arbitrary-v2'])
    assert.throws(() => signer.sign(actor('123'), ability, {}, challenge), { code: 'DELEGATION_INPUT' });
});

test('identity key loader rejects readable, linked and non-Ed25519 files', t => {
  const dir = mkdtempSync(join(tmpdir(), 'mrn-test-key-')); t.after(() => rmSync(dir, { recursive: true, force: true }));
  const { privateKey } = generateKeyPairSync('ed25519'); const file = join(dir, 'identity.pem');
  writeFileSync(file, privateKey.export({ type: 'pkcs8', format: 'pem' }), { mode: 0o600 });
  assert.equal(readIdentityKey(file).asymmetricKeyType, 'ed25519');
  chmodSync(file, 0o644); assert.throws(() => readIdentityKey(file), { code: 'DELEGATION_KEY' });
  chmodSync(file, 0o600); symlinkSync(file, join(dir, 'link')); assert.throws(() => readIdentityKey(join(dir, 'link')));
});
