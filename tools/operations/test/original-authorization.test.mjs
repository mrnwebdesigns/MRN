import test from 'node:test';
import assert from 'node:assert/strict';
import { createHash } from 'node:crypto';
import { createLocalJWKSet, exportJWK, generateKeyPair, SignJWT } from 'jose';
import { createAuthenticator, originalAuthorizationAnchor } from '../src/auth.mjs';

const issuer = 'https://identity.example.test/';
const audience = 'https://operations.mrnwebdesigns.com/mcp';
const claims = { [`${audience}/email`]: 'staff@mrnwebdesigns.com', [`${audience}/email_verified`]: true,
  [`${audience}/google_sub`]: '123456789', [`${audience}/identity_provider`]: 'google-oauth2',
  [`${audience}/google_connection`]: 'con_MRNGoogle' };
async function fixture(verifyStaff = async () => {}) {
  const keys = await generateKeyPair('RS256');
  const keySet = createLocalJWKSet({ keys: [{ ...await exportJWK(keys.publicKey), kid: 'original', alg: 'RS256' }] });
  const config = { issuer, audience, verifiedEmailClaims: true, googleWorkspace: { connectionId: 'con_MRNGoogle' } };
  const now = Math.floor(Date.now() / 1000);
  const sign = (overrides = {}, expires = now + 600, id = 'original') => new SignJWT({ scope: 'mrn:operations mrn:operations:write', ...claims, ...overrides })
    .setProtectedHeader({ alg: 'RS256', kid: 'original' }).setIssuer(issuer).setAudience(audience)
    .setSubject('google-oauth2|123456789').setIssuedAt(now).setExpirationTime(expires).setJti(id).sign(keys.privateKey);
  return { now, sign, auth: createAuthenticator(config, keySet, verifyStaff),
    legacy: createAuthenticator({ ...config, googleWorkspace: undefined }, keySet) };
}
const unavailable = fn => assert.throws(fn, { code: 'AUTH_REQUIRED' });

test('only the exact successfully verified actor can expose a private immutable original authorization anchor', async () => {
  const f = await fixture(), token = await f.sign(), actor = await f.auth(`Bearer ${token}`), anchor = originalAuthorizationAnchor(actor);
  assert.deepEqual(anchor, { schema: 1, authorizationSha256: createHash('sha256')
    .update('mrn-operations-original-authorization-v1\0').update(token).digest('hex'), expiresAt: (f.now + 600) * 1000 });
  assert.ok(Object.isFrozen(anchor));
  assert.throws(() => { anchor.expiresAt += 60000; }, TypeError);
  assert.ok(!JSON.stringify(actor).includes(anchor.authorizationSha256));
  assert.ok(!JSON.stringify(anchor).includes(token));
  for (const fake of [undefined, null, 'caller-session', {}, { ...actor }, structuredClone(actor),
    { ...actor, authorizationSha256: anchor.authorizationSha256 }, anchor]) unavailable(() => originalAuthorizationAnchor(fake));
  // A new authenticated request using the same exact bearer has the same
  // authorization identity; it still cannot mint or recover an original job.
  assert.deepEqual(originalAuthorizationAnchor(await f.auth(`Bearer ${token}`)), anchor);
});

test('later login by the same Google user cannot replace the earlier authorization or extend its expiry', async () => {
  const f = await fixture(), first = await f.auth(`Bearer ${await f.sign({}, f.now + 120, 'first')}`);
  const original = originalAuthorizationAnchor(first);
  const next = await f.auth(`Bearer ${await f.sign({}, f.now + 600, 'second')}`), replacement = originalAuthorizationAnchor(next);
  assert.equal(first.googleSubject, next.googleSubject);
  assert.notEqual(original.authorizationSha256, replacement.authorizationSha256);
  assert.equal(original.expiresAt, (f.now + 120) * 1000);
  assert.equal(replacement.expiresAt, (f.now + 600) * 1000);
  assert.deepEqual(originalAuthorizationAnchor(first), original);
});

test('failed active-staff verification and non-Google legacy authentication create no usable anchor', async () => {
  let attempted;
  const f = await fixture(async actor => { attempted = actor; throw new Error('suspended or revoked'); });
  const token = await f.sign();
  await assert.rejects(() => f.auth(`Bearer ${token}`), { code: 'AUTH_REQUIRED' });
  assert.ok(attempted); unavailable(() => originalAuthorizationAnchor(attempted));
  const legacy = await f.legacy(`Bearer ${token}`);unavailable(() => originalAuthorizationAnchor(legacy));
  for (const overrides of [{ [`${audience}/email`]: 'outsider@example.test' }, { [`${audience}/identity_provider`]: 'auth0' },
    { [`${audience}/google_connection`]: 'con_Foreign' }, { [`${audience}/email_verified`]: false }])
    await assert.rejects(() => f.sign(overrides).then(value => f.auth(`Bearer ${value}`)), { code: 'AUTH_REQUIRED' });
});

test('original authorization expiry is enforced even while another same-person authorization remains current', async t => {
  const f = await fixture(), first = await f.auth(`Bearer ${await f.sign({}, f.now + 60, 'short')}`);
  const next = await f.auth(`Bearer ${await f.sign({}, f.now + 600, 'long')}`);
  t.mock.method(Date, 'now', () => (f.now + 61) * 1000);
  unavailable(() => originalAuthorizationAnchor(first));
  assert.equal(originalAuthorizationAnchor(next).expiresAt, (f.now + 600) * 1000);
});
