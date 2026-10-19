import test from 'node:test';
import assert from 'node:assert/strict';
import { generateKeyPairSync, randomUUID, verify } from 'node:crypto';
import { mkdtempSync, readFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { createLocalJWKSet, exportJWK, generateKeyPair, SignJWT } from 'jose';
import { createAuthenticator, originalAuthorizationAnchor } from '../src/auth.mjs';
import { IdentitySigner, delegationDigest, delegationPrefix } from '../src/delegation.mjs';
import { digest } from '../src/contracts.mjs';
import { Store } from '../src/store.mjs';
import { OriginalManagementAuthorizationLedger } from '../src/original-management-authorization.mjs';
import { ManagementAccessLedger } from '../src/content-management-access-store.mjs';
import { ContentAccessLedger } from '../src/content-access-store.mjs';
import { requesterDigest } from '../src/access-principal.mjs';
import { accessRegistry } from './fixtures/access-registry.mjs';
import { managementAccessFixture } from './fixtures/management-access.mjs';

const audience = 'https://operations.mrnwebdesigns.com/mcp', issuer = 'https://identity.example.test/';
async function fixture(t, durable = false) {
  const now = Math.floor(Date.now() / 1000), oauth = await generateKeyPair('ES256');
  const keys = generateKeyPairSync('ed25519'), stores = [];
  const dir = durable ? mkdtempSync(join(tmpdir(), 'mrn-original-auth-')) : null;
  const file = dir ? join(dir, 'private.sqlite') : ':memory:';
  t.after(() => { for (const store of stores) store.close(); if (dir) rmSync(dir, { recursive: true, force: true }); });
  const auth = createAuthenticator({ issuer, audience, verifiedEmailClaims: true, googleWorkspace: { connectionId: 'con_fixture' } },
    createLocalJWKSet({ keys: [{ ...await exportJWK(oauth.publicKey), kid: 'fixture', alg: 'ES256' }] }), async () => {});
  const token = (exp = now + 600, sub = '123', overrides = {}) => new SignJWT({ scope: 'mrn:operations mrn:operations:write',
    [`${audience}/email`]: 'staff@mrnwebdesigns.com', [`${audience}/email_verified`]: true,
    [`${audience}/google_sub`]: sub, [`${audience}/identity_provider`]: 'google-oauth2',
    [`${audience}/google_connection`]: 'con_fixture', ...overrides })
    .setProtectedHeader({ kid: 'fixture', alg: 'ES256' }).setIssuer(issuer).setAudience(audience)
    .setSubject(`google-oauth2|${sub}`).setIssuedAt(now).setExpirationTime(exp).setJti(randomUUID()).sign(oauth.privateKey);
  const raw = await token(), actor = await auth(`Bearer ${raw}`);
  const values = managementAccessFixture(actor, Date.now()), request = values.expected.request;
  const { registry, registryData, policy } = accessRegistry(request.site_url);
  const state = { checks: 0, duringCheck: null };
  const pins = { provider_source_sha: 'a'.repeat(40), authorization_policy_sha256: 'b'.repeat(64), delegation_policy_sha256: 'c'.repeat(64) };
  const signer = new IdentitySigner({ keyId: 'fixture', privateKey: keys.privateKey, connectionId: 'con_fixture' });
  const options = { signer, registry, policy: () => pins, verifyStaff: async value => {
    assert.equal(value.googleSubject, '123'); state.checks++; await state.duringCheck?.(value);
  } };
  const open = () => { const store = new Store(file); stores.push(store); return { store, ledger: new OriginalManagementAuthorizationLedger(store, options) }; };
  const current = open(), attempt = randomUUID();
  const challenge = { nonce: 'd'.repeat(64), expires_at: now + 60, authenticated_connection_sha256: 'e'.repeat(64), policy_sha256: pins.delegation_policy_sha256 };
  const prepare = (value = actor, args = request, id = attempt, c = challenge) => current.ledger.prepare(value, args, c, id);
  return { ...current, values, state, now, actor, request, attempt, prepare, token, raw, auth, open, keys, signer, options,
    pins, registry, registryData, policy, challenge };
}
const count = f => f.store.db.prepare('SELECT count(*) n FROM original_management_authorizations').get().n;

test('durably retain exact original authorization before one-shot dispatch without token, content or signature storage', async t => {
  const f = await fixture(t), handle = await f.prepare();
  assert.ok(Object.isFrozen(handle)); assert.equal(count(f), 1);
  const retained = await f.ledger.readCurrent(handle.requestId, f.actor), m = retained.mapping;
  assert.equal(retained.state, 'prepared'); assert.deepEqual(m.authorization, originalAuthorizationAnchor(f.actor));
  assert.equal(m.argumentsSha256, delegationDigest(f.request)); assert.equal(m.requestId, handle.requestId);
  assert.equal(m.actor.expiresAt, m.authorization.expiresAt); assert.deepEqual(m.pins, f.pins);
  const envelope = await f.ledger.claimDispatch(handle, f.actor), bytes = Buffer.from(envelope.payload_base64, 'base64');
  assert.ok(verify(null, Buffer.concat([Buffer.from(delegationPrefix), bytes]), f.keys.publicKey, Buffer.from(envelope.signature_base64, 'base64')));
  assert.equal(JSON.parse(bytes).request_id, m.requestId); assert.equal(JSON.parse(bytes).arguments_sha256, m.argumentsSha256);
  assert.equal(delegationDigest(envelope), m.envelopeSha256);
  assert.equal((await f.ledger.readCurrent(handle.requestId, f.actor)).state, 'dispatched');
  for (const value of [handle, { ...handle }, null]) await assert.rejects(() => f.ledger.claimDispatch(value, f.actor));
  await assert.rejects(() => f.prepare());
  const body = f.store.db.prepare('SELECT body FROM original_management_authorizations').get().body;
  for (const secret of [f.raw, envelope.payload_base64, envelope.signature_base64, JSON.stringify(f.request.changes)])
    if (secret) assert.equal(body.includes(secret), false);
  assert.equal('original_resource_authorization_current' in m, false);
  assert.deepEqual(f.store.db.prepare('SELECT event FROM audit ORDER BY seq').all().map(x => x.event),
    ['original-authorization-retained', 'original-preview-dispatch']);
});

test('restart and later login recover only the original metadata, never a dispatch handle or extended grant', async t => {
  const f = await fixture(t, true), handle = await f.prepare(), before = await f.ledger.readCurrent(handle.requestId, f.actor);
  const next = await f.auth(`Bearer ${await f.token(f.now + 1800)}`), recovered = f.open();
  assert.deepEqual(await recovered.ledger.readCurrent(handle.requestId, next), before);
  await assert.rejects(() => recovered.ledger.claimDispatch(handle, next));
  await assert.rejects(() => f.ledger.claimDispatch(handle, next));
  await assert.rejects(() => recovered.ledger.prepare(next, f.request, f.challenge, f.attempt));
  const changed = await recovered.ledger.readCurrent(handle.requestId, next); changed.mapping.authorization.expiresAt += 600000;
  assert.deepEqual(await recovered.ledger.readCurrent(handle.requestId, next), before);
});

test('missing or corrupt original, attempt or principal history cannot be reconstructed by a fresh login', async t => {
  for (const damage of ['body-missing', 'attempt-missing', 'hash', 'body', 'principal-missing']) {
    const f = await fixture(t), handle = await f.prepare(), next = await f.auth(`Bearer ${await f.token(f.now + 1800)}`);
    if (damage === 'body-missing') f.store.db.exec('DELETE FROM original_management_authorizations');
    if (damage === 'attempt-missing') f.store.db.exec('DELETE FROM original_management_attempts');
    if (damage === 'hash') f.store.db.exec("UPDATE original_management_authorizations SET digest='bad'");
    if (damage === 'body') f.store.db.exec("UPDATE original_management_authorizations SET body='{}'");
    if (damage === 'principal-missing') f.store.db.exec('DELETE FROM content_access_principals');
    await assert.rejects(() => f.ledger.readCurrent(handle.requestId, next), damage);
    await assert.rejects(() => f.ledger.claimDispatch(handle, f.actor), damage);
    await assert.rejects(() => f.prepare(next), damage);
    if (damage === 'principal-missing') await assert.rejects(() => f.prepare(next, f.request, randomUUID()));
  }
});

test('denied and pending principal history veto recovery and dispatch before and during fresh verification', async t => {
  for (const state of ['pending-revocation', 'denied']) for (const during of [false, true]) {
    const f = await fixture(t), handle = await f.prepare();
    const deny = () => f.store.db.prepare('UPDATE content_access_principals SET state=?,revision=revision+1').run(state);
    if (during) f.state.duringCheck = deny; else deny();
    await assert.rejects(() => f.ledger.readCurrent(handle.requestId, f.actor));
    await assert.rejects(() => f.ledger.claimDispatch(handle, f.actor));
    await assert.rejects(() => f.prepare(f.actor, f.request, randomUUID()));
    assert.equal(f.store.db.prepare('SELECT state FROM original_management_authorizations').get().state, 'prepared');
  }
});

test('failed fresh Directory verification and changed principal revisions deny without dispatch or renewal', async t => {
  for (const stage of ['prepare', 'read', 'dispatch']) {
    const f = await fixture(t), handle = stage === 'prepare' ? null : await f.prepare();
    f.state.duringCheck = () => { throw new Error('Directory revoked/unavailable'); };
    await assert.rejects(() => stage === 'prepare' ? f.prepare() : stage === 'read'
      ? f.ledger.readCurrent(handle.requestId, f.actor) : f.ledger.claimDispatch(handle, f.actor));
    assert.equal(count(f), stage === 'prepare' ? 0 : 1);
  }
  const f = await fixture(t), handle = await f.prepare();
  f.state.duringCheck = () => f.store.db.exec('UPDATE content_access_principals SET revision=revision+1');
  await assert.rejects(() => f.ledger.readCurrent(handle.requestId, f.actor));
  await assert.rejects(() => f.ledger.claimDispatch(handle, f.actor));
});

test('original source, policy and exact website must remain unchanged across verification', async t => {
  for (const kind of ['source', 'content-policy', 'delegation-policy', 'member-policy', 'site']) {
    const f = await fixture(t), handle = await f.prepare();
    f.state.duringCheck = () => {
      if (kind === 'source') f.pins.provider_source_sha = 'f'.repeat(40);
      if (kind === 'content-policy') f.pins.authorization_policy_sha256 = 'f'.repeat(64);
      if (kind === 'delegation-policy') f.pins.delegation_policy_sha256 = 'f'.repeat(64);
      if (kind === 'member-policy') f.policy.emailDomains[0].portfolio.actions = ['read'];
      if (kind === 'site') f.registryData.websites[0].environments[0].url = 'https://other.example.invalid';
    };
    await assert.rejects(() => f.ledger.readCurrent(handle.requestId, f.actor), kind);
    await assert.rejects(() => f.ledger.claimDispatch(handle, f.actor), kind);
  }
  const f = await fixture(t); f.state.duringCheck = () => { f.pins.provider_source_sha = 'f'.repeat(40); };
  await assert.rejects(() => f.prepare()); assert.equal(count(f), 0);
});

test('caller-created identity, wrong person, read-only token, new record and mismatched challenge cannot retain authority', async t => {
  const f = await fixture(t);
  for (const actor of [{ ...f.actor }, { ...f.actor, verifiedEmail: 'owner@mrnwebdesigns.com' }, null])
    await assert.rejects(() => f.prepare(actor));
  const readOnly = await f.auth(`Bearer ${await f.token(f.now + 600, '123', { scope: 'mrn:operations' })}`);
  await assert.rejects(() => f.prepare(readOnly));
  await assert.rejects(() => f.prepare(f.actor, { ...f.request, post_id: 0 }));
  await assert.rejects(() => f.prepare(f.actor, f.request, randomUUID(), { ...f.challenge, policy_sha256: 'f'.repeat(64) }));
  assert.equal(count(f), 0);
  const handle = await f.prepare(), other = await f.auth(`Bearer ${await f.token(f.now + 600, '456')}`);
  await assert.rejects(() => f.ledger.readCurrent(handle.requestId, other));
});

test('transport expiry prevents redispatch while original metadata expires at its unchanged resource grant', async t => {
  const f = await fixture(t), handle = await f.prepare(), next = await f.auth(`Bearer ${await f.token(f.now + 1800)}`);
  let at = (f.now + 61) * 1000;
  t.mock.method(Date, 'now', () => at);
  await assert.rejects(() => f.ledger.claimDispatch(handle, f.actor));
  assert.equal((await f.ledger.readCurrent(handle.requestId, next)).mapping.authorization.expiresAt, (f.now + 600) * 1000);
  at = (f.now + 601) * 1000;
  await assert.rejects(() => f.ledger.readCurrent(handle.requestId, next));
});

test('two service instances cannot reserve or dispatch the same original attempt twice', async t => {
  const f = await fixture(t, true), second = f.open();
  const outcomes = await Promise.allSettled([f.prepare(), second.ledger.prepare(f.actor, f.request, f.challenge, f.attempt)]);
  assert.equal(outcomes.filter(x => x.status === 'fulfilled').length, 1); assert.equal(count(f), 1);
  const index = outcomes.findIndex(x => x.status === 'fulfilled'), ledger = index === 0 ? f.ledger : second.ledger;
  const h = outcomes[index].value, dispatched = await Promise.allSettled([ledger.claimDispatch(h, f.actor), ledger.claimDispatch(h, f.actor)]);
  assert.equal(dispatched.filter(x => x.status === 'fulfilled').length, 1);
});

test('failed local persistence rolls back history, audit and principal together before any dispatch handle is returned', async t => {
  const f = await fixture(t);
  f.store.db.exec("CREATE TRIGGER reject_original BEFORE INSERT ON audit BEGIN SELECT RAISE(ABORT,'fixture persistence failure'); END");
  await assert.rejects(() => f.prepare());
  assert.equal(count(f), 0);
  assert.equal(f.store.db.prepare('SELECT count(*) n FROM original_management_attempts').get().n, 0);
  assert.equal(f.store.db.prepare('SELECT count(*) n FROM content_access_principals').get().n, 0);
  assert.equal(f.store.db.prepare('SELECT count(*) n FROM content_access_principal_history').get().n, 0);
  assert.equal(f.store.db.prepare('SELECT count(*) n FROM audit').get().n, 0);
});

test('tampering with both original body and its hash still conflicts with retained attempt history', async t => {
  const f = await fixture(t), h = await f.prepare();
  const row = f.store.db.prepare('SELECT body FROM original_management_authorizations').get(), m = JSON.parse(row.body);
  m.authorization.expiresAt += 600000; m.actor.expiresAt += 600000;
  const body = JSON.stringify(m);
  f.store.db.prepare('UPDATE original_management_authorizations SET body=?,digest=?').run(body, digest(body));
  await assert.rejects(() => f.ledger.readCurrent(h.requestId, f.actor));
});

test('only the exact dispatched and fully reconstructed preview can retain an immutable operation mapping', async t => {
  const f = await fixture(t), h = await f.prepare(), { evidence, profile } = f.values.expected;
  const retain = (input = f.request, value = evidence) => f.ledger.recordReview(h.requestId, f.actor, input, value, profile);
  await assert.rejects(() => retain());
  await f.ledger.claimDispatch(h, f.actor);
  await assert.rejects(() => retain({ ...f.request, reason: 'substituted' }));
  const bad = structuredClone(evidence); bad.migration.operation_id = randomUUID();
  await assert.rejects(() => retain(f.request, bad));
  const result = await retain();
  assert.equal(result.state, 'reviewed'); assert.equal(result.review.operationId, evidence.review.operation_id);
  assert.equal(result.review.reviewedDigest, evidence.review.digest);
  assert.equal(result.mapping.authorization.expiresAt, f.actor.expiresAt);
  await assert.rejects(() => retain());
  const body = f.store.db.prepare('SELECT body FROM original_management_reviews').get().body;
  assert.equal(body.includes('post_content'), false); assert.equal(body.includes(f.raw), false);
  f.store.db.exec('DELETE FROM original_management_reviews');
  await assert.rejects(() => f.ledger.readCurrent(h.requestId, f.actor));
  await assert.rejects(() => retain());
});

test('existing principal revision changes during another preview preparation deny the new attempt', async t => {
  const f = await fixture(t); await f.prepare();
  f.state.duringCheck = () => f.store.db.exec('UPDATE content_access_principals SET revision=revision+1');
  await assert.rejects(() => f.prepare(f.actor, f.request, randomUUID()));
  assert.equal(count(f), 1);
});

function writers(f) {
  const management = new ManagementAccessLedger(f.store, { connectionId: 'con_fixture', registry: f.registry });
  const vectors = JSON.parse(readFileSync(new URL('./fixtures/content-qualification-contract.json', import.meta.url)));
  const request = vectors.cases.find(c => c.definition === 'request_input').value;
  const job = { ...vectors.cases.find(c => c.definition === 'job_output').value,
    expires_at: f.now + 300, recipe_catalog_sha256: 'c'.repeat(64), recipe_sha256: null };
  const admission = { authorization_policy_sha256: job.authorization_policy_sha256,
    recipe_catalog_sha256: job.recipe_catalog_sha256, recipe_sha256: 'd'.repeat(64) };
  const content = new ContentAccessLedger(f.store, { connectionId: 'con_fixture', registry: accessRegistry(request.site_url).registry });
  return {
    original: () => f.prepare(f.actor, f.request, randomUUID()),
    management: () => management.recordQueued(f.actor, f.values.request, f.values.context, f.values.expected,
      management.assertQueueAllowed(f.actor, f.values.request)),
    content: () => content.recordQueued(f.actor, request, job, admission, content.assertQueueAllowed(f.actor, request)),
  };
}

test('all protocol writers refuse cross-protocol principal loss, even if the new history table is also empty', async t => {
  for (const from of ['content', 'management', 'original']) for (const to of ['content', 'management', 'original'])
    for (const loseHistory of [false, true]) {
      const f = await fixture(t), write = writers(f); await write[from]();
      f.store.transaction(() => f.ledger.principals.pending(digest(f.actor.googleSubject), requesterDigest(f.actor)));
      f.store.db.exec('DELETE FROM content_access_principals');
      if (loseHistory) f.store.db.exec('DELETE FROM content_access_principal_history');
      await assert.rejects(async () => write[to](), `${from} to ${to}, history lost ${loseHistory}`);
      assert.equal(f.store.db.prepare('SELECT count(*) n FROM content_access_principals').get().n, 0);
    }
});

test('independent append-only denial history rejects an older clear row and survives loss of every workflow mapping', async t => {
  const f = await fixture(t), write = writers(f); await write.management();
  f.store.transaction(() => f.ledger.principals.pending(digest(f.actor.googleSubject), requesterDigest(f.actor)));
  assert.deepEqual(f.store.db.prepare('SELECT revision,state FROM content_access_principal_history ORDER BY revision').all()
    .map(x => ({ ...x })), [{ revision: 1, state: 'clear' }, { revision: 2, state: 'pending-revocation' }]);
  f.store.db.exec("UPDATE content_access_principals SET state='clear',revision=1");
  for (const create of Object.values(write)) await assert.rejects(async () => create());
  f.store.db.exec('DELETE FROM content_access_principals; DELETE FROM management_access_jobs');
  for (const create of Object.values(write)) await assert.rejects(async () => create());
  assert.equal(f.store.db.prepare('SELECT count(*) n FROM content_access_principals').get().n, 0);
});

test('a legacy primary without its new history is blocked rather than silently migrated during a team login', async t => {
  const f = await fixture(t), write = writers(f); await write.content();
  f.store.db.exec('DELETE FROM content_access_principal_history');
  for (const create of Object.values(write)) await assert.rejects(async () => create());
  assert.equal(f.store.db.prepare('SELECT count(*) n FROM content_access_principal_history').get().n, 0);
});

test('corrupt historical JSON identity or a valid-looking identity with a bad body hash cannot count as another person', async t => {
  for (const damage of ['invalid-hashes', 'valid-hashes-wrong-actor', 'body-hash', 'invalid-json', 'missing-actor']) {
    const f = await fixture(t), write = writers(f); await write.management();
    f.store.db.exec('DELETE FROM content_access_principals; DELETE FROM content_access_principal_history');
    const row = f.store.db.prepare('SELECT body FROM management_access_jobs').get(), m = JSON.parse(row.body);
    if (damage === 'invalid-hashes') { m.principal = 'lost'; m.requester = 'lost'; }
    if (damage === 'valid-hashes-wrong-actor') { m.principal = 'a'.repeat(64); m.requester = 'b'.repeat(64); }
    if (damage === 'body-hash') m.actor.googleSubject = '999';
    if (damage === 'missing-actor') delete m.actor;
    const body = damage === 'invalid-json' ? '{invalid' : JSON.stringify(m);
    f.store.db.prepare('UPDATE management_access_jobs SET body=?,digest=?').run(body, damage === 'body-hash' ? 'f'.repeat(64) : digest(body));
    for (const create of Object.values(write)) await assert.rejects(async () => create(), damage);
    assert.equal(f.store.db.prepare('SELECT count(*) n FROM content_access_principals').get().n, 0);
  }
});

test('intact history for another person permits automatic first use without clearing that person’s pending denial', async t => {
  const f = await fixture(t), write = writers(f);
  await write.content(); await write.management(); await write.original();
  f.store.transaction(() => f.ledger.principals.pending(digest(f.actor.googleSubject), requesterDigest(f.actor)));
  const other = await f.auth(`Bearer ${await f.token(f.now + 600, '456', { [`${audience}/email`]: 'another@mrnwebdesigns.com' })}`);
  const ledger = new OriginalManagementAuthorizationLedger(f.store, { ...f.options, verifyStaff: async actor => assert.equal(actor.googleSubject, '456') });
  const handle = await ledger.prepare(other, f.request, f.challenge, randomUUID());
  assert.equal((await ledger.readCurrent(handle.requestId, other)).mapping.actor.googleSubject, '456');
  assert.equal(f.ledger.principals.row(digest(f.actor.googleSubject), requesterDigest(f.actor)).state, 'pending-revocation');
});
