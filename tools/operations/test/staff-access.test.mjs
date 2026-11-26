import test from 'node:test';
import assert from 'node:assert/strict';
import { createRequire } from 'node:module';
import { createLocalJWKSet, exportJWK, generateKeyPair, SignJWT } from 'jose';
import { createAuthenticator } from '../src/auth.mjs';
import { policySchema } from '../src/contracts.mjs';
import { Discovery } from '../src/discovery.mjs';
import { setup } from './helpers.mjs';

const issuer = 'https://identity.example.test/';
const audience = 'https://operations.mrnwebdesigns.com/mcp';
const domainGrant = { domain: 'mrnwebdesigns.com', enabled: true, portfolio: { sources: ['mainwp'], actions: ['read'] } };
const claims = (email = 'STAFF@mrnwebdesigns.com', verified = true) => ({ [`${audience}/email`]: email, [`${audience}/email_verified`]: verified });
async function identity() {
  const keys = await generateKeyPair('RS256');
  const jwk = { ...await exportJWK(keys.publicKey), kid: 'staff', alg: 'RS256' };
  const keySet = createLocalJWKSet({ keys: [jwk] });
  const config = { issuer, audience, jwksUrl: `${issuer}.well-known/jwks.json`, verifiedEmailClaims: true };
  const sign = (payload = {}, subject = 'google-oauth2|staff') => new SignJWT({ scope: 'mrn:operations', ...payload })
    .setProtectedHeader({ alg: 'RS256', kid: 'staff' }).setIssuer(issuer).setAudience(audience)
    .setSubject(subject).setIssuedAt().setExpirationTime('10m').sign(keys.privateKey);
  return { sign, auth: createAuthenticator(config, keySet), disabled: createAuthenticator({ ...config, verifiedEmailClaims: false }, keySet) };
}

test('only resource-bound signed verified email grants staff access; no downstream call for outsiders', async t => {
  const f = setup(); t.after(f.close);
  f.policy.members = []; f.policy.emailDomains = [{ ...domainGrant }];
  f.registryData.websites = []; f.state.directory = [{ id: 1, url: f.state.url, name: 'Client' }];
  f.service.discovery = new Discovery({ registry: f.registry, store: f.store, connect: async () => f.client });
  const { sign, auth, disabled } = await identity();
  for (const payload of [
    {}, { email: 'staff@mrnwebdesigns.com', email_verified: true }, claims(undefined, false), claims(undefined, 'true'),
    claims('staff@other.test'), claims('staff@sub.mrnwebdesigns.com'), claims('staff@mrnwebdesigns.com.evil.test'),
    claims('staff@mrnwebdesigns.com '), claims('staff@@mrnwebdesigns.com'), claims('staff@mrnwebdesıgns.com'),
    { [`https://qaengine.mrndev.io/google-mcp/email`]: 'staff@mrnwebdesigns.com', [`https://qaengine.mrndev.io/google-mcp/email_verified`]: true },
  ]) {
    const actor = await auth(`Bearer ${await sign(payload)}`);
    await assert.rejects(() => f.service.list(actor), { code: 'FORBIDDEN' });
    assert.equal(f.state.calls.length, 0);
  }
  const valid = await sign(claims());
  const noClaims = await disabled(`Bearer ${valid}`);
  assert.equal(noClaims.verifiedEmail, undefined);
  await assert.rejects(() => f.service.list(noClaims), { code: 'FORBIDDEN' });
  const staff = await auth(`Bearer ${valid}`);
  assert.equal(staff.verifiedEmail, 'staff@mrnwebdesigns.com');
  assert.equal((await f.service.list(staff)).websites.length, 1);
  const target = f.registry.resolve(staff, f.state.url);
  for (const action of ['test', 'repair', 'deploy_development', 'release_production']) {
    assert.throws(() => f.registry.authorize(staff, target, action), { code: 'FORBIDDEN' });
  }
  const count = f.state.calls.length;
  f.policy.emailDomains = [];
  await assert.rejects(() => f.service.list(staff), { code: 'FORBIDDEN' });
  assert.equal(f.state.calls.length, count);
});

test('explicit revocation and restricted member grants override domain access immediately', async t => {
  const f = setup(); t.after(f.close);
  const { sign, auth } = await identity(); const actor = await auth(`Bearer ${await sign(claims())}`);
  f.policy.emailDomains = [{ ...domainGrant }];
  f.policy.members = [{ subject: actor.subject, enabled: false, grants: [] }];
  assert.throws(() => f.registry.member(actor), { code: 'FORBIDDEN' });
  f.policy.members[0].enabled = true;
  assert.equal(f.registry.member(actor).portfolio, undefined);
  assert.throws(() => f.registry.authorize(actor, { url: f.state.url, sources: ['mainwp'] }, 'read'), { code: 'FORBIDDEN' });
  assert.equal(f.state.calls.length, 0);
});

test('machine tokens, duplicate identities and domain write grants fail closed', async () => {
  const { sign, auth } = await identity();
  await assert.rejects(() => sign({ ...claims(), gty: 'client-credentials' }).then(token => auth(`Bearer ${token}`)), { code: 'AUTH_REQUIRED' });
  await assert.rejects(() => sign(claims(), 'machine@clients').then(token => auth(`Bearer ${token}`)), { code: 'AUTH_REQUIRED' });
  for (const policy of [
    { members: [], emailDomains: [domainGrant, domainGrant] },
    { members: [{ subject: 'duplicate', enabled: false }, { subject: 'duplicate', enabled: true }] },
    { members: [], emailDomains: [{ ...domainGrant, portfolio: { sources: ['mainwp'], actions: ['repair'] } }] },
    { members: [], emailDomains: [{ ...domainGrant, domain: '*.mrnwebdesigns.com' }] },
  ]) assert.equal(policySchema.safeParse({ version: 1, ...policy }).success, false);
});

test('Auth0 Action adds verified claims only for Operations, preserving other applications', async () => {
  const action = createRequire(import.meta.url)('../deploy/auth0-post-login-action.cjs').onExecutePostLogin;
  const values = {}; const denied = []; const api = { accessToken: { setCustomClaim: (key, value) => { values[key] = value; } }, access: { deny: message => denied.push(message) } };
  for (const resource of ['https://qaengine.mrndev.io/google-mcp', 'https://clientreview.mrnwebdesigns.com/mcp', undefined]) {
    await action({ resource_server: { identifier: resource }, user: { email: 'staff@mrnwebdesigns.com', email_verified: true } }, api);
  }
  await action({ resource_server: { identifier: audience }, user: { email: 'staff@mrnwebdesigns.com', email_verified: false } }, api);
  assert.deepEqual(values, {});
  assert.equal(denied.length, 1);
  await action({ resource_server: { identifier: audience }, connection: { strategy: 'google-oauth2', id: 'con_MRNGoogle' }, user: { user_id: 'google-oauth2|123456789', email: 'STAFF@mrnwebdesigns.com', email_verified: true } }, api);
  assert.deepEqual(values, { ...claims('staff@mrnwebdesigns.com'), [`${audience}/identity_provider`]: 'google-oauth2', [`${audience}/google_sub`]: '123456789', [`${audience}/google_connection`]: 'con_MRNGoogle' });
});

test('Google-only admission checks active membership on every authenticated request', async () => {
  const keys = await generateKeyPair('RS256');
  const keySet = createLocalJWKSet({ keys: [{ ...await exportJWK(keys.publicKey), kid: 'staff', alg: 'RS256' }] });
  const config = { issuer, audience, verifiedEmailClaims: true, googleWorkspace: { connectionId: 'con_MRNGoogle' } };
  const valid = { ...claims(), [`${audience}/identity_provider`]: 'google-oauth2', [`${audience}/google_sub`]: '123456789', [`${audience}/google_connection`]: 'con_MRNGoogle' };
  const sign = (payload, subject = 'google-oauth2|123456789') => new SignJWT({ scope: 'mrn:operations', ...payload }).setProtectedHeader({ alg: 'RS256', kid: 'staff' }).setIssuer(issuer).setAudience(audience).setSubject(subject).setIssuedAt().setExpirationTime('10m').sign(keys.privateKey);
  let checks = 0; let active = true;
  const auth = createAuthenticator(config, keySet, async actor => { checks++; assert.equal(actor.googleSubject, '123456789'); if (!active) throw new Error('suspended or revoked'); });
  for (const [payload, subject] of [
    [claims()], [{ ...valid, [`${audience}/identity_provider`]: 'auth0' }],
    [{ ...valid, [`${audience}/google_connection`]: 'con_Other' }],
    [{ ...valid, [`${audience}/google_sub`]: 'different' }],
    [{ ...valid, ...claims('staff@outside.test') }],
    [valid, 'auth0|linked-primary'], [valid, 'google-oauth2|987654321'],
  ]) await assert.rejects(() => sign(payload, subject).then(token => auth(`Bearer ${token}`)), { code: 'AUTH_REQUIRED' });
  assert.equal(checks, 0);
  const token = await sign(valid);
  const actor = await auth(`Bearer ${token}`);
  assert.equal(Object.isFrozen(actor), true);
  assert.equal(actor.googleConnection, 'con_MRNGoogle');
  assert.equal(actor.verifiedEmail, 'staff@mrnwebdesigns.com');
  active = false;
  await assert.rejects(() => auth(`Bearer ${token}`), { code: 'AUTH_REQUIRED' });
  assert.equal(checks, 2);
  await assert.rejects(() => createAuthenticator(config, keySet)(`Bearer ${token}`), { code: 'AUTH_REQUIRED' });
});

test('Auth0 denies non-Google and outsiders rather than issuing staff claims', async () => {
  const action = createRequire(import.meta.url)('../deploy/auth0-post-login-action.cjs').onExecutePostLogin;
  const base = { resource_server: { identifier: audience }, connection: { strategy: 'google-oauth2', id: 'con_MRNGoogle' }, user: { user_id: 'google-oauth2|123456789', email: 'staff@mrnwebdesigns.com', email_verified: true } };
  for (const event of [
    { ...base, connection: { strategy: 'auth0', id: 'con_MRNGoogle' } },
    { ...base, user: { ...base.user, user_id: 'auth0|linked-primary', identities: [{ provider: 'google-oauth2' }] } },
    ...['staff@outside.test', 'staff@sub.mrnwebdesigns.com', 'staff@mrnwebdesigns.com ', 'staff@@mrnwebdesigns.com'].map(email => ({ ...base, user: { ...base.user, email } })),
  ]) {
    let denied = false;
    await action(event, { access: { deny: () => { denied = true; } }, accessToken: { setCustomClaim: () => assert.fail('Invalid account received staff claims') } });
    assert.equal(denied, true);
  }
});
