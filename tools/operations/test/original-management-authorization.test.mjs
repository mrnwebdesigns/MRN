import test from 'node:test';
import assert from 'node:assert/strict';
import { generateKeyPairSync, randomUUID, verify } from 'node:crypto';
import { mkdtempSync, readFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { createLocalJWKSet, exportJWK, generateKeyPair, SignJWT } from 'jose';
import { createAuthenticator, originalAuthorizationAnchor } from '../src/auth.mjs';
import { IdentitySigner, delegationCanonical, delegationDigest, delegationPrefix } from '../src/delegation.mjs';
import { digest } from '../src/contracts.mjs';
import { Store } from '../src/store.mjs';
import { OriginalManagementAuthorizationLedger } from '../src/original-management-authorization.mjs';
import { ManagementAccessLedger } from '../src/content-management-access-store.mjs';
import { ContentAccessLedger } from '../src/content-access-store.mjs';
import { requesterDigest } from '../src/access-principal.mjs';
import { accessRegistry } from './fixtures/access-registry.mjs';
import { managementAccessFixture } from './fixtures/management-access.mjs';
import { existingManagementHashes } from '../src/management-existing-contracts.mjs';
import { OriginalAuthorizationTransport, originalBindingObservation, originalReadinessObservation } from '../src/original-authorization-transport.mjs';
import { DelegatedMainwpClient, delegationTools } from '../src/delegated-mainwp.mjs';
import { validateExistingManagementQueue } from '../src/existing-management-queue.mjs';

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
  const service = await provider({ pins, keys }), ready = () => service.transport.status();
  const prepare = async (value = actor, args = request, id = attempt, c = challenge) => current.ledger.prepare(value, args, c, id, await ready());
  return { ...current, values, state, now, actor, request, attempt, prepare, token, raw, auth, open, keys, signer, options,
    pins, registry, registryData, policy, challenge, service, ready };
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
  await assert.rejects(async () => recovered.ledger.prepare(next, f.request, f.challenge, f.attempt, await f.ready()));
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
  const outcomes = await Promise.allSettled([f.prepare(), second.ledger.prepare(f.actor, f.request, f.challenge, f.attempt, await f.ready())]);
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

test('qualification hashes survive SQLite reopen and a new login without retaining content or renewing the review', async t => {
  const f = await fixture(t, true), h = await f.prepare(), { evidence, profile } = f.values.expected;
  await f.ledger.claimDispatch(h, f.actor);
  const retained = await f.ledger.recordReview(h.requestId, f.actor, f.request, evidence, profile);
  assert.deepEqual(retained.review.qualification, {
    requestSha256: delegationDigest(evidence.migration.request), profileSha256: profile.sha256,
    runtimeContextSha256: profile.runtime_context_sha256,
    acfSchemaSha256: delegationDigest(evidence.read.snapshot.acf_definitions),
    beforePathsSha256: delegationDigest(evidence.read.affected_paths),
    managementContractSha256: digest(readFileSync(new URL('../contracts/content-management-v1.json', import.meta.url))),
  });
  assert.equal(retained.review.schema, 2);
  const row = f.store.db.prepare('SELECT body,digest FROM original_management_reviews').get();
  assert.equal(f.store.db.prepare('SELECT review_digest FROM original_management_attempts').get().review_digest, digest(row.body));
  for (const secret of [f.raw, JSON.stringify(f.request.changes), JSON.stringify(evidence.read.snapshot),
    JSON.stringify(evidence.read.snapshot.acf_definitions), JSON.stringify(evidence.migration)])
    if (secret && secret.length > 2) assert.equal(row.body.includes(secret), false);
  const reopened = f.open(), next = await f.auth(`Bearer ${await f.token(f.now + 1800)}`);
  assert.deepEqual(await reopened.ledger.readCurrent(h.requestId, next), retained);
  retained.review.qualification.profileSha256 = 'f'.repeat(64);
  const recovered = await reopened.ledger.readCurrent(h.requestId, next);
  assert.equal(recovered.review.qualification.profileSha256, profile.sha256);
  assert.equal(recovered.review.expiresAt, evidence.review.expires_at * 1000);
  assert.equal(recovered.mapping.authorization.expiresAt, f.actor.expiresAt);
});

test('missing, changed or pre-anchor review history cannot be repaired by login or repeated evidence', async t => {
  for (const damage of ['missing-marker', 'missing-body', 'old-review', 'unexpected-field',
    'requestSha256', 'profileSha256', 'runtimeContextSha256', 'acfSchemaSha256', 'beforePathsSha256', 'managementContractSha256']) {
    const f = await fixture(t), h = await f.prepare(), { evidence, profile } = f.values.expected;
    await f.ledger.claimDispatch(h, f.actor);
    await f.ledger.recordReview(h.requestId, f.actor, f.request, evidence, profile);
    if (damage === 'missing-marker') f.store.db.exec("UPDATE original_management_attempts SET review_digest=''");
    else if (damage === 'missing-body') f.store.db.exec('DELETE FROM original_management_reviews');
    else {
      const value = JSON.parse(f.store.db.prepare('SELECT body FROM original_management_reviews').get().body);
      if (damage === 'old-review') { delete value.schema; delete value.qualification; }
      else if (damage === 'unexpected-field') value.qualification.actor = 'caller-supplied';
      else value.qualification[damage] = 'f'.repeat(64);
      const body = JSON.stringify(value);
      f.store.db.prepare('UPDATE original_management_reviews SET body=?,digest=?').run(body, digest(body));
      // Even consistent checksums do not upgrade old or open-ended schemas.
      if (['old-review', 'unexpected-field'].includes(damage))
        f.store.db.prepare('UPDATE original_management_attempts SET review_digest=?').run(digest(body));
    }
    const next = await f.auth(`Bearer ${await f.token(f.now + 1800)}`), reopened = new OriginalManagementAuthorizationLedger(f.store, f.options);
    await assert.rejects(() => reopened.readCurrent(h.requestId, next), damage);
    await assert.rejects(() => reopened.recordReview(h.requestId, next, f.request, evidence, profile), damage);
  }
});

test('review, qualification marker, state and audit commit atomically across failures and concurrent service instances', async t => {
  const f = await fixture(t, true), h = await f.prepare(), { evidence, profile } = f.values.expected;
  await f.ledger.claimDispatch(h, f.actor);
  f.store.db.exec("CREATE TRIGGER fail_review BEFORE INSERT ON audit WHEN NEW.event='original-preview-retained' BEGIN SELECT RAISE(ABORT,'fixture'); END");
  await assert.rejects(() => f.ledger.recordReview(h.requestId, f.actor, f.request, evidence, profile));
  assert.equal(f.store.db.prepare('SELECT count(*) n FROM original_management_reviews').get().n, 0);
  assert.equal(f.store.db.prepare('SELECT review_digest FROM original_management_attempts').get().review_digest, '');
  assert.equal(f.store.db.prepare('SELECT state FROM original_management_authorizations').get().state, 'dispatched');
  f.store.db.exec('DROP TRIGGER fail_review');
  const second = f.open(), outcomes = await Promise.allSettled([f.ledger, second.ledger].map(ledger =>
    ledger.recordReview(h.requestId, f.actor, f.request, evidence, profile)));
  assert.equal(outcomes.filter(value => value.status === 'fulfilled').length, 1);
  assert.equal(f.store.db.prepare("SELECT count(*) n FROM audit WHERE event='original-preview-retained'").get().n, 1);
  assert.deepEqual(await f.ledger.readCurrent(h.requestId, f.actor), await second.ledger.readCurrent(h.requestId, f.actor));
});

test('a review anchor changed during current Directory verification is refused', async t => {
  const f = await fixture(t), h = await f.prepare(), { evidence, profile } = f.values.expected;
  await f.ledger.claimDispatch(h, f.actor);
  await f.ledger.recordReview(h.requestId, f.actor, f.request, evidence, profile);
  f.state.duringCheck = () => {
    const value = JSON.parse(f.store.db.prepare('SELECT body FROM original_management_reviews').get().body);
    value.qualification.profileSha256 = 'f'.repeat(64);
    const body = JSON.stringify(value), hash = digest(body);
    f.store.db.prepare('UPDATE original_management_reviews SET body=?,digest=?').run(body, hash);
    f.store.db.prepare('UPDATE original_management_attempts SET review_digest=?').run(hash);
  };
  await assert.rejects(() => f.ledger.readCurrent(h.requestId, f.actor), { code: 'ORIGINAL_AUTHORIZATION' });
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
  const handle = await ledger.prepare(other, f.request, f.challenge, randomUUID(), await f.ready());
  assert.equal((await ledger.readCurrent(handle.requestId, other)).mapping.actor.googleSubject, '456');
  assert.equal(f.ledger.principals.row(digest(f.actor.googleSubject), requesterDigest(f.actor)).state, 'pending-revocation');
});

async function reviewed(t, durable = false) {
  const f = await fixture(t, durable), handle = await f.prepare();
  await f.ledger.claimDispatch(handle, f.actor);
  const { evidence, profile } = f.values.expected;
  await f.ledger.recordReview(handle.requestId, f.actor, f.request, evidence, profile);
  return { ...f, original: handle.requestId };
}
const readyControl = async (ledger, kind, requestId, actor, f) => ledger[kind === 'bind' ? 'prepareBind' : 'prepareRead'](requestId, actor, await f.ready());
const controls = f => f.store.db.prepare('SELECT count(*) n FROM original_management_controls').get().n;

test('bind control is signed only from intact verified original authority with the exact frozen provider purpose', async t => {
  const wireBytes = readFileSync(new URL('../contracts/content-management-original-authorization-v2.json', import.meta.url));
  assert.equal(digest(wireBytes), '3a77cf8ad2131fd1e2255e74d2a3dcd9ca1d18051e5acc1d51bdb6b7d6fa73c2');
  const source = JSON.parse(readFileSync(new URL('../contracts/content-management-original-authorization-source.json', import.meta.url)));
  assert.equal(source.commit, '293b1387a839043eceeb1cf7703bc8d119a0a3ed');
  assert.equal(source.repository, 'mrnwebdesigns/mrn-mainwp-operations-api');
  assert.equal(source.sha256, digest(wireBytes)); assert.equal(source.runtimeRegistered, true); assert.equal(source.productionQualified, false);
  const wire = JSON.parse(wireBytes), f = await reviewed(t);
  const handle = await readyControl(f.ledger, 'bind', f.original, f.actor, f);
  await assert.rejects(() => readyControl(f.ledger, 'read', f.original, f.actor, f));
  const input = await f.ledger.claimControlDispatch(handle, f.actor), envelope = input.signed_request;
  const bytes = Buffer.from(envelope.payload_base64, 'base64'), c = JSON.parse(bytes), m = (await f.ledger.readCurrent(f.original, f.actor)).mapping;
  assert.equal(bytes.toString(), delegationCanonical(c));
  assert.ok(verify(null, Buffer.concat([Buffer.from(wire.purposes.bind), bytes]), f.keys.publicKey, Buffer.from(envelope.signature_base64, 'base64')));
  for (const purpose of [wire.purposes.read, delegationPrefix, 'mrn-content-management-qa-v1:'])
    assert.equal(verify(null, Buffer.concat([Buffer.from(purpose), bytes]), f.keys.publicKey, Buffer.from(envelope.signature_base64, 'base64')), false);
  assert.deepEqual(c.protocol_contracts_sha256, existingManagementHashes);
  assert.equal(c.lifetime_domain, 'original-resource-authorization-v2');
  assert.equal(c.original_resource_authorization_current, true);
  assert.equal(c.original_resource_authorization_sha256, m.authorization.authorizationSha256);
  assert.equal(c.original_resource_authorization_expires_at * 1000, m.authorization.expiresAt);
  assert.equal(c.original_delegation_request_id, m.requestId);
  assert.equal(c.requester_binding_sha256, m.requester);
  assert.ok(c.expires_at <= Math.floor(m.transportExpiresAt / 1000));
  const body = f.store.db.prepare('SELECT body FROM original_management_controls').get().body;
  for (const secret of [f.raw, envelope.payload_base64, envelope.signature_base64, JSON.stringify(f.request.changes)])
    if (secret) assert.equal(body.includes(secret), false);
  assert.ok(Object.isFrozen(input) && Object.isFrozen(envelope));
  await assert.rejects(() => f.ledger.claimControlDispatch(handle, f.actor));
  await assert.rejects(() => readyControl(f.ledger, 'bind', f.original, f.actor, f));
});

test('simultaneous bind issuers have one winner and a lost local handle cannot renew the bind', async t => {
  const f = await reviewed(t, true);
  const attempts = await Promise.allSettled([readyControl(f.ledger, 'bind', f.original, f.actor, f), readyControl(f.ledger, 'bind', f.original, f.actor, f)]);
  assert.equal(attempts.filter(v => v.status === 'fulfilled').length, 1); assert.equal(controls(f), 1);
  const h = attempts.find(v => v.status === 'fulfilled').value, restarted = f.open();
  await assert.rejects(() => readyControl(restarted.ledger, 'bind', f.original, f.actor, f));
  await assert.rejects(() => restarted.ledger.claimControlDispatch(h, f.actor));
  await assert.rejects(() => readyControl(restarted.ledger, 'read', f.original, f.actor, f));
  const dispatch = await Promise.allSettled([f.ledger.claimControlDispatch(h, f.actor), f.ledger.claimControlDispatch(h, f.actor)]);
  assert.equal(dispatch.filter(v => v.status === 'fulfilled').length, 1);
  const read = await readyControl(restarted.ledger, 'read', f.original, f.actor, f);
  assert.ok(await restarted.ledger.claimControlDispatch(read, f.actor));
  assert.equal(controls(f), 2);
});

test('fresh read after a lost bind response and transport expiry preserves the original hash and expiration across restart and login', async t => {
  const f = await reviewed(t, true), next = await f.auth(`Bearer ${await f.token(f.now + 1800)}`);
  const h = await readyControl(f.ledger, 'bind', f.original, f.actor, f), bind = await f.ledger.claimControlDispatch(h, f.actor);
  const original = JSON.parse(Buffer.from(bind.signed_request.payload_base64, 'base64'));
  let at = (f.now + 61) * 1000; t.mock.method(Date, 'now', () => at);
  const restarted = f.open(), r = await readyControl(restarted.ledger, 'read', f.original, next, f);
  const read = await restarted.ledger.claimControlDispatch(r, next), c = JSON.parse(Buffer.from(read.signed_request.payload_base64, 'base64'));
  assert.equal(c.original_resource_authorization_sha256, original.original_resource_authorization_sha256);
  assert.equal(c.original_resource_authorization_expires_at, original.original_resource_authorization_expires_at);
  assert.equal(c.original_delegation_request_id, original.original_delegation_request_id);
  assert.notEqual(c.request_id, original.request_id); assert.notEqual(c.nonce, original.nonce);
  const bytes = Buffer.from(read.signed_request.payload_base64, 'base64');
  assert.ok(verify(null, Buffer.concat([Buffer.from('mrn-content-management-existing-resource-authorization-read-v2:'), bytes]), f.keys.publicKey,
    Buffer.from(read.signed_request.signature_base64, 'base64')));
  await assert.rejects(() => readyControl(restarted.ledger, 'bind', f.original, next, f));
  at = (f.now + 601) * 1000;
  await assert.rejects(() => readyControl(restarted.ledger, 'read', f.original, next, f));
});

test('first bind cannot be created after the original preview transport expires or before exact review reconstruction', async t => {
  const f = await fixture(t), h = await f.prepare();
  await assert.rejects(() => readyControl(f.ledger, 'bind', h.requestId, f.actor, f));
  await f.ledger.claimDispatch(h, f.actor);
  await assert.rejects(() => readyControl(f.ledger, 'bind', h.requestId, f.actor, f));
  await f.ledger.recordReview(h.requestId, f.actor, f.request, f.values.expected.evidence, f.values.expected.profile);
  t.mock.method(Date, 'now', () => (f.now + 61) * 1000);
  await assert.rejects(() => readyControl(f.ledger, 'bind', h.requestId, f.actor, f));
  assert.equal(controls(f), 0);
});

test('lost, corrupt and rolled-back control history cannot authorize retries or fresh recovery signatures', async t => {
  for (const damage of ['body-missing', 'history-missing', 'both-missing', 'marker-missing', 'counter-reset', 'digest', 'rolled-back-state', 'changed-body-and-hash', 'orphan-history']) {
    const f = await reviewed(t), h = await readyControl(f.ledger, 'bind', f.original, f.actor, f);
    await f.ledger.claimControlDispatch(h, f.actor);
    if (damage === 'body-missing') f.store.db.exec('DELETE FROM original_management_controls');
    if (damage === 'history-missing') f.store.db.exec('DELETE FROM original_management_control_history');
    if (damage === 'both-missing') f.store.db.exec('DELETE FROM original_management_controls; DELETE FROM original_management_control_history');
    if (damage === 'marker-missing') f.store.db.exec("UPDATE original_management_attempts SET bind_control=''");
    if (damage === 'counter-reset') f.store.db.exec('UPDATE original_management_attempts SET control_count=0');
    if (damage === 'digest') f.store.db.exec("UPDATE original_management_controls SET digest='bad'");
    if (damage === 'rolled-back-state') f.store.db.exec("UPDATE original_management_controls SET state='prepared'");
    if (damage === 'orphan-history') f.store.db.prepare('UPDATE original_management_control_history SET id=?').run(randomUUID());
    if (damage === 'changed-body-and-hash') {
      const v = JSON.parse(f.store.db.prepare('SELECT body FROM original_management_controls').get().body);
      v.claims.original_resource_authorization_expires_at += 300;
      const body = JSON.stringify(v); f.store.db.prepare('UPDATE original_management_controls SET body=?,digest=?').run(body, digest(body));
    }
    await assert.rejects(() => readyControl(f.ledger, 'read', f.original, f.actor, f), damage);
    await assert.rejects(() => readyControl(f.ledger, 'bind', f.original, f.actor, f), damage);
  }
});

test('fresh staff, principal and policy vetoes deny control issuance and dispatch without asserting renewed access', async t => {
  for (const stage of ['prepare', 'dispatch']) for (const failure of ['directory', 'revocation', 'policy']) {
    const f = await reviewed(t), h = stage === 'dispatch' ? await readyControl(f.ledger, 'bind', f.original, f.actor, f) : null;
    f.state.duringCheck = () => {
      if (failure === 'directory') throw new Error('Directory unavailable or revoked');
      if (failure === 'revocation') f.store.transaction(() => f.ledger.principals.pending(digest(f.actor.googleSubject), requesterDigest(f.actor)));
      if (failure === 'policy') f.pins.authorization_policy_sha256 = 'f'.repeat(64);
    };
    await assert.rejects(() => h ? f.ledger.claimControlDispatch(h, f.actor) : readyControl(f.ledger, 'bind', f.original, f.actor, f));
    assert.equal(controls(f), h ? 1 : 0);
    assert.equal(f.store.db.prepare("SELECT count(*) n FROM original_management_controls WHERE state='dispatched'").get().n, 0);
  }
  const f = await reviewed(t), readOnly = await f.auth(`Bearer ${await f.token(f.now + 600, '123', { scope: 'mrn:operations' })}`);
  const outsider = await f.auth(`Bearer ${await f.token(f.now + 600, '456')}`);
  for (const actor of [{ ...f.actor }, readOnly, outsider]) await assert.rejects(() => readyControl(f.ledger, 'bind', f.original, actor, f));
});

test('a surviving control or control history blocks principal reinitialization across all content writers', async t => {
  for (const survivor of ['control', 'history']) {
    const f = await reviewed(t), write = writers(f), h = await readyControl(f.ledger, 'bind', f.original, f.actor, f);
    await f.ledger.claimControlDispatch(h, f.actor);
    f.store.db.exec(`DELETE FROM content_access_principals; DELETE FROM content_access_principal_history;
      DELETE FROM original_management_authorizations; DELETE FROM original_management_attempts; DELETE FROM original_management_reviews;
      DELETE FROM ${survivor === 'control' ? 'original_management_control_history' : 'original_management_controls'}`);
    for (const create of Object.values(write)) await assert.rejects(async () => create(), survivor);
    assert.equal(f.store.db.prepare('SELECT count(*) n FROM content_access_principals').get().n, 0);
  }
});

test('control persistence and audit commit atomically before any signature can be dispatched', async t => {
  const f = await reviewed(t);
  f.store.db.exec("CREATE TRIGGER fail_control_audit BEFORE INSERT ON audit WHEN NEW.event='original-authorization-bind' BEGIN SELECT RAISE(ABORT,'fixture'); END");
  await assert.rejects(() => readyControl(f.ledger, 'bind', f.original, f.actor, f));
  assert.equal(controls(f), 0);
  assert.equal(f.store.db.prepare('SELECT count(*) n FROM original_management_control_history').get().n, 0);
  f.store.db.exec('DROP TRIGGER fail_control_audit');
  const h = await readyControl(f.ledger, 'bind', f.original, f.actor, f);
  f.store.db.exec("CREATE TRIGGER fail_control_dispatch BEFORE INSERT ON audit WHEN NEW.outcome='dispatched' BEGIN SELECT RAISE(ABORT,'fixture'); END");
  await assert.rejects(() => f.ledger.claimControlDispatch(h, f.actor));
  assert.equal(f.store.db.prepare('SELECT state FROM original_management_controls').get().state, 'prepared');
  assert.equal(f.store.db.prepare('SELECT count(*) n FROM original_management_control_history').get().n, 1);
});

// The real private transport performs discovery, schema/identity correlation,
// purpose, freshness and size checks. Only its returned object can be retained.
async function provider(f) {
  const raw = readFileSync(new URL('../contracts/content-management-existing-dispatch-v2-proposal.json', import.meta.url));
  const wire = JSON.parse(raw), names = Object.fromEntries(Object.entries(wire.machine_abilities)
    .map(([k, v]) => [k, v.name.replace('/', '__').replaceAll('-', '_')]));
  const state = { epoch: '7'.repeat(64), binding: '8'.repeat(64), calls: [], enabled: true };
  const policy = () => ({ provider_source_sha: f.pins.provider_source_sha, authorization_policy_sha256: f.pins.authorization_policy_sha256 });
  const client = {
    async readResource() { return { contents: [{ text: JSON.stringify({ connected: true, dashboardHost: 'wpcontrol.mrndev.io', abilitiesCount: 115 }) }] }; },
    async listTools() { return { tools: Object.entries(wire.machine_abilities).map(([kind, v]) => ({ name: names[kind], annotations: v.annotations })) }; },
    async callTool({ name, arguments: input }) {
      state.calls.push(name); let result;
      if (name === names.readiness) result = { schema: 2, dashboard_origin: 'https://wpcontrol.mrndev.io',
        qualification_protocol: 'content-management-existing-v2', lifetime_domain: 'original-resource-authorization-v2',
        protocol_contracts_sha256: existingManagementHashes, ...policy(),
        original_authorization_contract_sha256: '3a77cf8ad2131fd1e2255e74d2a3dcd9ca1d18051e5acc1d51bdb6b7d6fa73c2',
        dispatch_contract_sha256: digest(raw), observed_at: Math.floor(Date.now() / 1000), expires_at: Math.floor(Date.now() / 1000) + 30,
        binding_readiness: state.enabled ? 'ready' : 'disabled', coherence_ready: true, coherence_epoch_sha256: state.epoch,
        source_selected_preview_enabled: state.enabled, machine_binding_enabled: state.enabled, production_qualified: false, consumer_binding_issued: false };
      else {
        const e = input.signed_request, bytes = Buffer.from(e.payload_base64, 'base64'), c = JSON.parse(bytes);
        const kind = name === names.bind ? 'bind' : 'read'; assert.equal(name, names[kind]);
        assert.ok(verify(null, Buffer.concat([Buffer.from(wire.machine_abilities[kind].purpose), bytes]), f.keys.publicKey, Buffer.from(e.signature_base64, 'base64')));
        result = { schema: 2, ...Object.fromEntries(['operation_id', 'reviewed_digest', 'qualification_protocol', 'protocol_contracts_sha256',
          'original_delegation_request_id', 'original_resource_authorization_sha256', 'original_resource_authorization_expires_at'].map(k => [k, c[k]])),
          state: 'bound-blocked', authorization_binding_sha256: state.binding, production_qualified: false, consumer_binding_issued: false };
      }
      return { content: [{ type: 'text', text: JSON.stringify(result) }] };
    },
  };
  const transport = new OriginalAuthorizationTransport(client, { policy }); await transport.initialize();
  async function observe(kind = 'bind', ledger = f.ledger, actor = f.actor) {
    const ready = await transport.status();
    const handle = await ledger[kind === 'bind' ? 'prepareBind' : 'prepareRead'](f.original, actor, ready);
    const input = await ledger.claimControlDispatch(handle, actor);
    return { handle, input, response: await transport[kind](input, ready) };
  }
  return { state, observe, transport };
}

async function existingQueue(t) {
  const f = await reviewed(t, true), p = await provider(f), observed = await p.observe();
  await f.ledger.recordProviderBinding(observed.handle.controlId, f.actor, observed.response);
  const next = await f.auth(`Bearer ${await f.token(f.now + 1800)}`), reopened = f.open();
  const original = await reopened.ledger.readCurrent(f.original, next);
  const providerBinding = await reopened.ledger.readProviderBinding(f.original, next);
  const pins = { ...f.values.expected.pins, provider_source_sha: f.pins.provider_source_sha,
    authorization_policy_sha256: f.pins.authorization_policy_sha256 };
  const dispatchedAt = Date.now(), created = Math.floor(dispatchedAt / 1000);
  const context = structuredClone(f.values.context);
  Object.assign(context.binding, { ...pins, schema: 2, qualification_protocol: 'content-management-existing-v2',
    protocol_contracts_sha256: existingManagementHashes, created_at: created,
    expires_at: Math.min(Math.floor(original.review.expiresAt / 1000), Math.floor(original.mapping.authorization.expiresAt / 1000),
      created + pins.job_intent_ttl_seconds, created + 900) });
  context.binding_sha256 = delegationDigest(context.binding);
  return { f, context, expected: { original, providerBinding, request: f.values.request, pins, dispatchedAt, now: dispatchedAt } };
}

test('existing-v2 queue metadata binds the durable original review recovered by a fresh login', async t => {
  const { f, context, expected } = await existingQueue(t);
  assert.deepEqual(validateExistingManagementQueue(context, expected), context);
  // This metadata verifier is not a queue, dispatch, capture or admission.
  assert.equal(f.store.db.prepare("SELECT count(*) n FROM sqlite_master WHERE name='management_access_jobs'").get().n, 0);
  assert.equal(expected.original.mapping.authorization.expiresAt, f.actor.expiresAt);
  const legacy = structuredClone(f.values.context);
  assert.throws(() => validateExistingManagementQueue(legacy, expected));
});

test('closed v2 queue metadata refuses substituted identity, content, source, target, version and protocol hashes', async t => {
  const { context, expected } = await existingQueue(t);
  const changes = [
    ...Object.keys(context.binding).filter(k => k.endsWith('_sha256')).map(k => [k, 'f'.repeat(64)]),
    ['operation_id', randomUUID()], ['site_id', 99], ['site_url', 'https://other.example.invalid'],
    ['target', { ...context.binding.target, post_id: 999 }], ['expected_target_revision', 1],
    ['provider_source_sha', 'f'.repeat(40)], ['schema', 1], ['qualification_protocol', 'content-management-qualification-v1'],
    ['protocol_contracts_sha256', { ...existingManagementHashes, qualification: 'f'.repeat(64) }],
    ['job_intent_ttl_seconds', context.binding.job_intent_ttl_seconds + 1],
  ];
  for (const [field, value] of changes) {
    const bad = structuredClone(context); bad.binding[field] = value; bad.binding_sha256 = delegationDigest(bad.binding);
    assert.throws(() => validateExistingManagementQueue(bad, expected), field);
  }
  for (const change of [{ state: 'captured' }, { capture_artifact_sha256: 'f'.repeat(64) }, { binding_sha256: 'f'.repeat(64) }, { caller: 'staff' }])
    assert.throws(() => validateExistingManagementQueue({ ...context, ...change }, expected));
  const bad = structuredClone(expected);
  bad.providerBinding.coherenceEpochSha256 = 'f'.repeat(64);
  assert.throws(() => validateExistingManagementQueue(context, bad));
  for (const field of ['operation_id', 'reviewed_digest', 'original_delegation_request_id', 'original_resource_authorization_sha256']) {
    const changed = structuredClone(expected);
    changed.providerBinding.projection[field] = field.endsWith('_id') ? randomUUID() : 'f'.repeat(64);
    assert.throws(() => validateExistingManagementQueue(context, changed), field);
  }
});

test('existing queue lifetime is capped by original resource, review and catalog without adopting a new login expiry', async t => {
  const { context, expected } = await existingQueue(t);
  for (const limiting of ['review', 'original-resource', 'catalog']) {
    const wanted = structuredClone(expected), value = structuredClone(context), created = value.binding.created_at;
    const expiry = created + 60;
    if (limiting === 'review') wanted.original.review.expiresAt = expiry * 1000;
    if (limiting === 'original-resource') {
      wanted.original.mapping.authorization.expiresAt = expiry * 1000;
      wanted.providerBinding.projection.original_resource_authorization_expires_at = expiry;
    }
    if (limiting === 'catalog') wanted.pins.job_intent_ttl_seconds = value.binding.job_intent_ttl_seconds = 60;
    value.binding.expires_at = expiry; value.binding_sha256 = delegationDigest(value.binding);
    assert.deepEqual(validateExistingManagementQueue(value, wanted), value, limiting);
    value.binding.expires_at++; value.binding_sha256 = delegationDigest(value.binding);
    assert.throws(() => validateExistingManagementQueue(value, wanted), limiting);
  }
  for (const created of [context.binding.created_at - 1, context.binding.created_at + 1]) {
    const value = structuredClone(context); value.binding.created_at = created; value.binding_sha256 = delegationDigest(value.binding);
    assert.throws(() => validateExistingManagementQueue(value, expected));
  }
  assert.throws(() => validateExistingManagementQueue(context, { ...expected, now: context.binding.expires_at * 1000 }));
  assert.throws(() => validateExistingManagementQueue(context, { ...expected, dispatchedAt: expected.now + 1 }));
  assert.throws(() => validateExistingManagementQueue(context, { ...expected, request: { ...expected.request, actor: 'caller' } }));
});

test('only the exact authenticated provider observation is retained with immutable original metadata and audit', async t => {
  const f = await reviewed(t), p = await provider(f), { handle, input, response } = await p.observe();
  for (const value of [{ ...response }, structuredClone(response), null]) {
    assert.throws(() => originalBindingObservation(value));
    await assert.rejects(() => f.ledger.recordProviderBinding(handle.controlId, f.actor, value));
  }
  await assert.rejects(() => f.ledger.recordProviderBinding(randomUUID(), f.actor, response));
  const saved = await f.ledger.recordProviderBinding(handle.controlId, f.actor, response);
  assert.equal(saved.original, f.original); assert.deepEqual(saved.projection, response);
  assert.equal(saved.coherenceEpochSha256, p.state.epoch);
  assert.equal(saved.projection.original_resource_authorization_expires_at, f.now + 600);
  assert.equal(saved.projection.state, 'bound-blocked'); assert.equal(saved.projection.production_qualified, false);
  assert.equal(saved.projection.consumer_binding_issued, false);
  assert.deepEqual(await f.ledger.readProviderBinding(f.original, f.actor), saved);
  await assert.rejects(() => f.ledger.recordProviderBinding(handle.controlId, f.actor, response));
  assert.equal(f.store.db.prepare('SELECT state FROM original_management_controls').get().state, 'observed');
  assert.equal(f.store.db.prepare('SELECT count(*) n FROM original_management_control_history').get().n, 3);
  const body = f.store.db.prepare('SELECT body FROM original_management_bindings').get().body;
  for (const secret of [f.raw, input.signed_request.payload_base64, input.signed_request.signature_base64, JSON.stringify(f.request.changes)])
    if (secret) assert.equal(body.includes(secret), false);
  assert.equal(f.store.db.prepare("SELECT count(*) n FROM audit WHERE event='original-provider-binding'").get().n, 1);
});

test('a lost bind response is recovered by a fresh read after restart and transport expiry without extending original authority', async t => {
  const f = await reviewed(t, true), p = await provider(f), lost = await p.observe();
  const next = await f.auth(`Bearer ${await f.token(f.now + 1800)}`);
  let at = (f.now + 61) * 1000; t.mock.method(Date, 'now', () => at);
  const reopened = f.open();
  await assert.rejects(() => reopened.ledger.readProviderBinding(f.original, next));
  await assert.rejects(() => reopened.ledger.recordProviderBinding(lost.handle.controlId, next, lost.response));
  const fresh = await p.observe('read', reopened.ledger, next);
  const saved = await reopened.ledger.recordProviderBinding(fresh.handle.controlId, next, fresh.response);
  assert.deepEqual(saved.projection, lost.response);
  assert.equal(saved.projection.original_resource_authorization_expires_at, f.now + 600);
  assert.deepEqual(await f.ledger.readProviderBinding(f.original, next), saved);
  const again = await p.observe('read', f.ledger, next);
  assert.deepEqual(await f.ledger.recordProviderBinding(again.handle.controlId, next, again.response), saved);
  await assert.rejects(() => readyControl(f.ledger, 'bind', f.original, next, f));
  at = (f.now + 601) * 1000;
  await assert.rejects(() => f.ledger.readProviderBinding(f.original, next));
});

test('a later provider observation cannot substitute the retained binding hash or observed coherence epoch', async t => {
  for (const field of ['binding', 'epoch']) {
    const f = await reviewed(t), p = await provider(f), first = await p.observe();
    const saved = await f.ledger.recordProviderBinding(first.handle.controlId, f.actor, first.response);
    p.state[field] = '9'.repeat(64);
    if (field === 'epoch') {
      await assert.rejects(() => p.observe('read'));
      assert.equal(controls(f), 1);
    } else {
      const next = await p.observe('read');
      await assert.rejects(() => f.ledger.recordProviderBinding(next.handle.controlId, f.actor, next.response), field);
      assert.equal(f.store.db.prepare('SELECT state FROM original_management_controls WHERE id=?').get(next.handle.controlId).state, 'dispatched');
    }
    assert.deepEqual(await f.ledger.readProviderBinding(f.original, f.actor), saved);
  }
});

test('missing, corrupt or rolled-back provider receipt history denies recovery and any new controls', async t => {
  for (const damage of ['body-missing', 'marker-missing', 'body-and-marker-missing', 'digest', 'body-and-hash', 'observation-missing', 'state-rewound']) {
    const f = await reviewed(t), p = await provider(f), first = await p.observe();
    await f.ledger.recordProviderBinding(first.handle.controlId, f.actor, first.response);
    if (damage.includes('body-missing') || damage === 'body-and-marker-missing') f.store.db.exec('DELETE FROM original_management_bindings');
    if (damage.includes('marker-missing')) f.store.db.exec("UPDATE original_management_attempts SET binding_digest=''");
    if (damage === 'digest') f.store.db.exec("UPDATE original_management_bindings SET digest='bad'");
    if (damage === 'body-and-hash') {
      const saved = JSON.parse(f.store.db.prepare('SELECT body FROM original_management_bindings').get().body);
      saved.projection.authorization_binding_sha256 = 'a'.repeat(64);
      const body = JSON.stringify(saved); f.store.db.prepare('UPDATE original_management_bindings SET body=?,digest=?').run(body, digest(body));
    }
    if (damage === 'observation-missing') f.store.db.exec("DELETE FROM original_management_control_history WHERE state='observed'");
    if (damage === 'state-rewound') f.store.db.exec("UPDATE original_management_controls SET state='dispatched'");
    await assert.rejects(() => f.ledger.readProviderBinding(f.original, f.actor), damage);
    await assert.rejects(() => readyControl(f.ledger, 'read', f.original, f.actor, f), damage);
    await assert.rejects(() => readyControl(f.ledger, 'bind', f.original, f.actor, f), damage);
  }
});

test('revoked, unavailable or changed authority withholds binding retention and recovery after fresh verification', async t => {
  for (const stage of ['retain', 'recover']) for (const failure of ['directory', 'revocation', 'revision', 'policy']) {
    const f = await reviewed(t), p = await provider(f), first = await p.observe();
    if (stage === 'recover') await f.ledger.recordProviderBinding(first.handle.controlId, f.actor, first.response);
    f.state.duringCheck = () => {
      if (failure === 'directory') throw new Error('Directory unavailable or revoked');
      if (failure === 'revocation') f.store.transaction(() => f.ledger.principals.pending(digest(f.actor.googleSubject), requesterDigest(f.actor)));
      if (failure === 'revision') f.store.db.exec('UPDATE content_access_principals SET revision=revision+1');
      if (failure === 'policy') f.pins.authorization_policy_sha256 = 'f'.repeat(64);
    };
    await assert.rejects(() => stage === 'recover' ? f.ledger.readProviderBinding(f.original, f.actor)
      : f.ledger.recordProviderBinding(first.handle.controlId, f.actor, first.response), `${stage}/${failure}`);
    assert.equal(f.store.db.prepare('SELECT count(*) n FROM original_management_bindings').get().n, stage === 'recover' ? 1 : 0);
  }
});

test('binding retention and observation history are atomic across service instances and persistence failure', async t => {
  const f = await reviewed(t, true), p = await provider(f), first = await p.observe(), reopened = f.open();
  f.store.db.exec("CREATE TRIGGER fail_binding BEFORE INSERT ON audit WHEN NEW.event='original-provider-binding' BEGIN SELECT RAISE(ABORT,'fixture'); END");
  await assert.rejects(() => f.ledger.recordProviderBinding(first.handle.controlId, f.actor, first.response));
  assert.equal(f.store.db.prepare('SELECT count(*) n FROM original_management_bindings').get().n, 0);
  assert.equal(f.store.db.prepare('SELECT binding_digest FROM original_management_attempts').get().binding_digest, '');
  assert.equal(f.store.db.prepare('SELECT state FROM original_management_controls').get().state, 'dispatched');
  assert.equal(f.store.db.prepare("SELECT count(*) n FROM original_management_control_history WHERE state='observed'").get().n, 0);
  f.store.db.exec('DROP TRIGGER fail_binding');
  const results = await Promise.allSettled([f.ledger, reopened.ledger].map(ledger => ledger.recordProviderBinding(first.handle.controlId, f.actor, first.response)));
  assert.equal(results.filter(v => v.status === 'fulfilled').length, 1);
  assert.equal(f.store.db.prepare('SELECT count(*) n FROM original_management_bindings').get().n, 1);
});

test('a surviving provider binding alone forbids principal reinitialization through every content writer', async t => {
  const f = await reviewed(t), p = await provider(f), first = await p.observe();
  await f.ledger.recordProviderBinding(first.handle.controlId, f.actor, first.response);
  const write = writers(f);
  f.store.db.exec(`DELETE FROM content_access_principals; DELETE FROM content_access_principal_history;
    DELETE FROM original_management_authorizations; DELETE FROM original_management_attempts; DELETE FROM original_management_reviews;
    DELETE FROM original_management_controls; DELETE FROM original_management_control_history;`);
  for (const create of Object.values(write)) await assert.rejects(async () => create());
  assert.equal(f.store.db.prepare('SELECT count(*) n FROM content_access_principals').get().n, 0);
});

test('original preview retains a genuine fresh readiness anchor before any dispatch and never accepts a supplied epoch', async t => {
  const f = await fixture(t), ready = await f.ready(), observation = originalReadinessObservation(ready);
  for (const value of [undefined, { ...ready }, structuredClone(ready), observation]) {
    await assert.rejects(() => f.ledger.prepare(f.actor, f.request, f.challenge, f.attempt, value));
    assert.equal(count(f), 0);
  }
  const handle = await f.ledger.prepare(f.actor, f.request, f.challenge, f.attempt, ready);
  const saved = await f.ledger.readCurrent(handle.requestId, f.actor);
  const { policy, ...anchor } = observation;
  assert.equal(policy.provider_source_sha, f.pins.provider_source_sha);
  assert.deepEqual(saved.mapping.readiness, anchor);
  assert.equal(saved.state, 'prepared');
  const dispatched = await f.ledger.claimDispatch(handle, f.actor);
  const claims = JSON.parse(Buffer.from(dispatched.payload_base64, 'base64'));
  assert.equal(claims.ability, 'mrn-mainwp/preview-content-edit-v1');
  assert.equal('coherence_epoch_sha256' in claims, false); // Provider selects the domain; unchanged public v1 input.
});

test('disabled, expired or differently pinned provider readiness cannot create a new original', async t => {
  for (const failure of ['disabled', 'expired', 'source', 'policy']) {
    const f = await fixture(t);
    if (failure === 'disabled') f.service.state.enabled = false;
    const p = await provider({ keys: f.keys, pins: { ...f.pins,
      ...(failure === 'source' ? { provider_source_sha: 'f'.repeat(40) } : {}),
      ...(failure === 'policy' ? { authorization_policy_sha256: 'f'.repeat(64) } : {}) } });
    const ready = await (failure === 'disabled' ? f.ready() : p.transport.status());
    if (failure === 'expired') t.mock.method(Date, 'now', () => (f.now + 31) * 1000);
    await assert.rejects(() => f.ledger.prepare(f.actor, f.request, f.challenge, f.attempt, ready), failure);
    assert.equal(count(f), 0); t.mock.restoreAll();
  }
});

test('readiness is rechecked across fresh Directory verification and immediately before preview dispatch', async t => {
  for (const stage of ['prepare', 'dispatch']) for (const failure of ['expired', 'closed']) {
    const f = await fixture(t), ready = await f.ready();
    const handle = stage === 'dispatch' ? await f.ledger.prepare(f.actor, f.request, f.challenge, f.attempt, ready) : null;
    f.state.duringCheck = () => {
      if (failure === 'expired') t.mock.method(Date, 'now', () => (f.now + 31) * 1000);
      else f.service.transport.ready = false;
    };
    await assert.rejects(() => handle ? f.ledger.claimDispatch(handle, f.actor)
      : f.ledger.prepare(f.actor, f.request, f.challenge, f.attempt, ready), `${stage}/${failure}`);
    assert.equal(count(f), handle ? 1 : 0);
    if (handle) assert.equal(f.store.db.prepare('SELECT state FROM original_management_authorizations').get().state, 'prepared');
    t.mock.restoreAll();
  }
});

test('control issuance and dispatch require fresh genuine readiness with the original epoch', async t => {
  const f = await reviewed(t), p = await provider(f);
  const ready = await p.transport.status();
  for (const value of [undefined, { ...ready }]) await assert.rejects(() => f.ledger.prepareBind(f.original, f.actor, value));
  p.state.epoch = 'f'.repeat(64);
  await assert.rejects(async () => f.ledger.prepareBind(f.original, f.actor, await p.transport.status()));
  assert.equal(controls(f), 0);
  p.state.epoch = '7'.repeat(64);
  const handle = await f.ledger.prepareBind(f.original, f.actor, await p.transport.status());
  t.mock.method(Date, 'now', () => (f.now + 31) * 1000);
  await assert.rejects(() => f.ledger.claimControlDispatch(handle, f.actor));
  assert.equal(f.store.db.prepare('SELECT state FROM original_management_controls').get().state, 'prepared');
});

test('first provider binding cannot attach a newer readiness epoch to an older original preview', async t => {
  const f = await reviewed(t), p = await provider(f), ready = await p.transport.status();
  const handle = await f.ledger.prepareBind(f.original, f.actor, ready), input = await f.ledger.claimControlDispatch(handle, f.actor);
  p.state.epoch = '9'.repeat(64);
  const response = await p.transport.bind(input, await p.transport.status());
  await assert.rejects(() => f.ledger.recordProviderBinding(handle.controlId, f.actor, response));
  assert.equal(f.store.db.prepare('SELECT count(*) n FROM original_management_bindings').get().n, 0);
  assert.equal(f.store.db.prepare('SELECT state FROM original_management_controls').get().state, 'dispatched');
});

test('missing or altered preview epoch cannot be repaired from a new readiness observation', async t => {
  for (const damage of ['missing', 'changed']) {
    const f = await reviewed(t), row = f.store.db.prepare('SELECT body FROM original_management_authorizations').get();
    const m = JSON.parse(row.body);
    if (damage === 'missing') delete m.readiness; else m.readiness.coherenceEpochSha256 = 'f'.repeat(64);
    const body = JSON.stringify(m); f.store.db.prepare('UPDATE original_management_authorizations SET body=?,digest=?').run(body, digest(body));
    await assert.rejects(() => f.ledger.readCurrent(f.original, f.actor));
    await assert.rejects(async () => f.ledger.prepareBind(f.original, f.actor, await f.ready()));
    await assert.rejects(() => f.prepare());
  }
});

function originalPreviewClient(f) {
  const state = { calls: [], confirmed: 0, corrupt: null, failure: null };
  const response = value => ({ content: [{ type: 'text', text: JSON.stringify(value) }] });
  const client = {
    async readResource() { return { contents: [{ text: JSON.stringify({ connected: true, dashboardHost: 'wpcontrol.mrndev.io', abilitiesCount: 115 }) }] }; },
    async listTools() { return { tools: Object.values(delegationTools).map(name => ({ name,
      annotations: { readOnlyHint: false, destructiveHint: name === delegationTools.run } })) }; },
    async callTool({ name, arguments: args }) {
      state.calls.push({ name, args });
      if (name === delegationTools.challenge) {
        assert.equal(args.ability, 'mrn-mainwp/preview-content-edit-v1');
        assert.equal(args.arguments_sha256, delegationDigest(f.request)); return response(f.challenge);
      }
      assert.equal(name, delegationTools.run);
      const raw = Buffer.from(args.delegation.payload_base64, 'base64'), claims = JSON.parse(raw);
      assert.ok(verify(null, Buffer.concat([Buffer.from(delegationPrefix), raw]), f.keys.publicKey, Buffer.from(args.delegation.signature_base64, 'base64')));
      const retained = await f.ledger.readCurrent(claims.request_id, f.actor);
      assert.equal(retained.state, 'dispatched');
      assert.equal(retained.mapping.envelopeSha256, delegationDigest(args.delegation));
      assert.equal(retained.mapping.readiness.coherenceEpochSha256, f.service.state.epoch);
      assert.equal(args.ability, claims.ability); assert.equal(args.arguments_json, delegationCanonical(f.request));
      if (!args.user_confirmed) {
        state.first = structuredClone(args);
        await state.duringConfirmation?.();
        return response(state.failure === 'confirmation' ? { status: 'SAFE_MODE_BLOCKED' }
          : { status: 'CONFIRMATION_REQUIRED', confirmation_token: 'fixture-confirmation' });
      }
      assert.deepEqual(args.delegation, state.first.delegation); assert.equal(args.confirmation_token, 'fixture-confirmation');
      state.confirmed++;
      if (state.failure === 'lost-response') throw new Error('Fixture response lost after provider execution');
      const result = { schema: 1, request_id: claims.request_id, ability: args.ability, policy_sha256: claims.policy_sha256,
        actor: { oauth_subject: claims.oauth_subject, google_sub: claims.google_sub, email: claims.email },
        result: structuredClone(f.values.expected.evidence.review) };
      state.corrupt?.(result); await state.duringResult?.(); return response(result);
    },
  };
  const delegated = new DelegatedMainwpClient(client, f.actor, f.signer);
  const preview = async () => delegated.previewOriginalManagementEdit(f.request,
    { ledger: f.ledger, attemptId: f.attempt, readiness: await f.ready() });
  return { state, delegated, preview, client };
}

test('original preview crosses both confirmed wrapper phases using the exact durably retained signed envelope', async t => {
  const f = await fixture(t), p = originalPreviewClient(f), result = await p.preview();
  assert.deepEqual(result.review, f.values.expected.evidence.review);
  assert.deepEqual(p.state.calls.map(row => row.name), [delegationTools.challenge, delegationTools.run, delegationTools.run]);
  const current = await f.ledger.readCurrent(result.originalRequestId, f.actor);
  assert.equal(current.state, 'dispatched'); assert.equal(p.state.confirmed, 1);
  const retained = await f.ledger.recordReview(result.originalRequestId, f.actor, f.request,
    { ...f.values.expected.evidence, review: result.review }, f.values.expected.profile);
  assert.equal(retained.state, 'reviewed');
  const providerAdapter = await provider({ ...f, original: result.originalRequestId });
  const binding = await providerAdapter.observe();
  const saved = await f.ledger.recordProviderBinding(binding.handle.controlId, f.actor, binding.response);
  assert.equal(saved.original, result.originalRequestId); assert.equal(saved.coherenceEpochSha256, current.mapping.readiness.coherenceEpochSha256);
  assert.equal(saved.projection.consumer_binding_issued, false);
});

test('failed confirmation and uncertain original preview responses cannot cause automatic or repeated dispatch', async t => {
  for (const failure of ['confirmation', 'lost-response']) {
    const f = await fixture(t), p = originalPreviewClient(f); p.state.failure = failure;
    await assert.rejects(p.preview);
    const before = p.state.calls.filter(row => row.name === delegationTools.run).length;
    assert.equal(before, failure === 'confirmation' ? 1 : 2);
    assert.equal(f.store.db.prepare('SELECT state FROM original_management_authorizations').get().state, 'dispatched');
    await assert.rejects(p.preview);
    assert.equal(p.state.calls.filter(row => row.name === delegationTools.run).length, before);
    assert.equal(count(f), 1);
  }
});

test('original preview rejects changed person, request, policy or target without retaining a review', async t => {
  for (const field of ['person', 'request', 'policy', 'target']) {
    const f = await fixture(t), p = originalPreviewClient(f);
    p.state.corrupt = result => {
      if (field === 'person') result.actor.google_sub = '999';
      if (field === 'request') result.request_id = randomUUID();
      if (field === 'policy') result.policy_sha256 = 'f'.repeat(64);
      if (field === 'target') result.result.target.post_id++;
    };
    await assert.rejects(p.preview, field);
    assert.equal(f.store.db.prepare('SELECT count(*) n FROM original_management_reviews').get().n, 0);
    assert.equal(p.state.confirmed, 1);
  }
});

test('original preview requires the genuine actor, readiness and service ledger while the generic tool path stays closed', async t => {
  const f = await fixture(t), p = originalPreviewClient(f), ready = await f.ready();
  for (const options of [{}, { ledger: {}, attemptId: f.attempt, readiness: ready },
    { ledger: f.ledger, attemptId: f.attempt, readiness: { ...ready } }])
    await assert.rejects(() => p.delegated.previewOriginalManagementEdit(f.request, options));
  await assert.rejects(() => p.delegated.callTool({ name: 'mrn_mainwp__preview_content_edit_v1', arguments: f.request }));
  const copied = new DelegatedMainwpClient(p.client, { ...f.actor }, f.signer);
  await assert.rejects(() => copied.previewOriginalManagementEdit(f.request, { ledger: f.ledger, attemptId: f.attempt, readiness: ready }));
  assert.equal(count(f), 0); assert.equal(p.state.confirmed, 0);
});

test('fresh local authorization and readiness are rechecked before the confirmed original preview dispatch', async t => {
  for (const failure of ['revocation', 'policy', 'expired-readiness']) {
    const f = await fixture(t), p = originalPreviewClient(f);
    p.state.duringConfirmation = () => {
      if (failure === 'revocation') f.store.transaction(() => f.ledger.principals.pending(digest(f.actor.googleSubject), requesterDigest(f.actor)));
      if (failure === 'policy') f.pins.authorization_policy_sha256 = 'f'.repeat(64);
      if (failure === 'expired-readiness') t.mock.method(Date, 'now', () => (f.now + 31) * 1000);
    };
    await assert.rejects(p.preview, failure);
    assert.equal(p.state.confirmed, 0);
    assert.equal(p.state.calls.filter(row => row.name === delegationTools.run).length, 1);
    assert.equal(f.store.db.prepare('SELECT state FROM original_management_authorizations').get().state, 'dispatched');
    t.mock.restoreAll();
  }
});

test('pending revocation during the confirmed original preview withholds the returned review', async t => {
  const f = await fixture(t), p = originalPreviewClient(f);
  p.state.duringResult = () => f.store.transaction(() => f.ledger.principals.pending(digest(f.actor.googleSubject), requesterDigest(f.actor)));
  await assert.rejects(p.preview);
  assert.equal(p.state.confirmed, 1);
  assert.equal(f.store.db.prepare('SELECT count(*) n FROM original_management_reviews').get().n, 0);
});
