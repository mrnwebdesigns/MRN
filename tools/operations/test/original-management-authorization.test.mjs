import test from 'node:test';
import assert from 'node:assert/strict';
import { createPublicKey, generateKeyPairSync, randomUUID, sign, verify } from 'node:crypto';
import { mkdtempSync, readFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { request as httpRequest } from 'node:http';
import { setImmediate } from 'node:timers';
import { createLocalJWKSet, exportJWK, generateKeyPair, SignJWT } from 'jose';
import { createAuthenticator, originalAuthorizationAnchor } from '../src/auth.mjs';
import { IdentitySigner, delegationCanonical, delegationDigest, delegationPrefix } from '../src/delegation.mjs';
import { digest } from '../src/contracts.mjs';
import { Store } from '../src/store.mjs';
import { OriginalManagementAuthorizationLedger, originalQualificationAbility } from '../src/original-management-authorization.mjs';
import { ManagementAccessLedger } from '../src/content-management-access-store.mjs';
import { ContentAccessLedger } from '../src/content-access-store.mjs';
import { requesterDigest } from '../src/access-principal.mjs';
import { accessRegistry } from './fixtures/access-registry.mjs';
import { managementAccessFixture } from './fixtures/management-access.mjs';
import { existingManagementHashes, existingManagementWires } from '../src/management-existing-contracts.mjs';
import { OriginalAuthorizationTransport, originalBindingObservation, originalReadinessObservation } from '../src/original-authorization-transport.mjs';
import { DelegatedMainwpClient, delegationTools } from '../src/delegated-mainwp.mjs';
import { validateExistingManagementQueue } from '../src/existing-management-queue.mjs';
import { ExistingManagementAccessSigner, ExistingManagementAccessQueryReader, ExistingManagementAccessVeto,
  ExistingManagementAccessLedger, existingManagementAccessReadbackTool, existingManagementAccessPath } from '../src/existing-management-access.mjs';
import { createExistingManagementAccessRuntime } from '../src/content-access-runtime.mjs';
import { managementCaptureContract } from '../src/content-management-capture.mjs';
import { createHttpServer } from '../src/http.mjs';
import { ExistingManagementContext, ExistingManagementContextReader, existingManagementContextTool,
  createExistingManagementContextRuntime } from '../src/existing-management-context.mjs';
import { ExistingManagementCapture, ExistingManagementCaptureReader, existingManagementCaptureTools,
  createExistingManagementCaptureRuntime, validateExistingManagementCapture } from '../src/existing-management-capture.mjs';
import { ExistingManagementMeasurements, ExistingManagementMeasurementsReader, existingManagementMeasurementsTool,
  createExistingManagementMeasurementsRuntime } from '../src/existing-management-measurements.mjs';
import { ExistingManagementSourceContext, ExistingManagementSourceContextReader, existingManagementSourceContextTool,
  createExistingManagementSourceContextRuntime } from '../src/existing-management-source-context.mjs';
import { existingWorkerContractSha256, existingWorkerPurpose, verifyExistingManagementWorkerRecord } from '../src/existing-management-worker-contract.mjs';
import { ExistingManagementSourceCatalogRegistry } from '../src/existing-management-source-catalog.mjs';

const audience = 'https://operations.mrnwebdesigns.com/mcp', issuer = 'https://identity.example.test/';
async function fixture(t, durable = false, workerConfiguration) {
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
  const options = { signer, registry, workerConfiguration, policy: () => pins, verifyStaff: async value => {
    assert.equal(value.googleSubject, '123'); state.checks++; await state.duringCheck?.(value);
  } };
  const open = () => { const store = new Store(file); stores.push(store); return { store, ledger: new OriginalManagementAuthorizationLedger(store, options) }; };
  const current = open(), attempt = randomUUID();
  const challenge = { nonce: 'd'.repeat(64), expires_at: now + 60, authenticated_connection_sha256: 'e'.repeat(64), policy_sha256: pins.delegation_policy_sha256 };
  const service = await provider({ pins, keys }), ready = () => service.transport.status();
  const prepare = async (value = actor, args = request, id = attempt, c = challenge) => current.ledger.prepare(value, args, c, id, await ready());
  return { ...current, values, state, now, actor, request, attempt, prepare, token, raw, auth, open, keys, signer, options,
    pins, registry, registryData, policy, challenge, service, ready,
    close: store => { stores.splice(stores.indexOf(store), 1); store.close(); } };
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

async function reviewed(t, durable = false, workerConfiguration) {
  const f = await fixture(t, durable, workerConfiguration), handle = await f.prepare();
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
  assert.equal(source.commit, 'f084797442149ce0c49ef5e9c5d5b3dcc9e5ac2e');
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

async function existingQueue(t, workerConfiguration) {
  const f = await reviewed(t, true, workerConfiguration), p = await provider(f), observed = await p.observe();
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

async function queueFixture(t, workerConfiguration) {
  const { f, context, expected } = await existingQueue(t, workerConfiguration);
  const state = { pins: structuredClone(expected.pins), epoch: expected.original.mapping.readiness.coherenceEpochSha256,
    checks: 0, duringCheck: null };
  f.options.queueReadiness = async (actor, input) => {
    // Human queue callers are branded by the ledger; a private worker receives
    // the same independently retained identity, without manufacturing a JWT.
    assert.equal(requesterDigest(actor), requesterDigest(f.actor));
    state.lastActor = structuredClone(actor); assert.deepEqual(input, f.values.request);
    state.checks++; await state.duringCheck?.();
    return { pins: structuredClone(state.pins), coherenceEpochSha256: state.epoch };
  };
  f.ledger = new OriginalManagementAuthorizationLedger(f.store, f.options);
  const request = f.values.request;
  const prepare = (ledger = f.ledger, actor = f.actor) => ledger.prepareQueue(f.original, actor, request, f.challenge);
  const output = () => {
    const value = structuredClone(context), created = Math.floor(Date.now() / 1000);
    value.binding.created_at = created;
    value.binding.expires_at = Math.min(Math.floor(expected.original.review.expiresAt / 1000), f.now + 600,
      created + state.pins.job_intent_ttl_seconds, created + 900);
    value.binding_sha256 = delegationDigest(value.binding); return value;
  };
  return { ...f, queueState: state, queueRequest: request, queuePrepare: prepare, queueOutput: output };
}
const queueCount = f => f.store.db.prepare('SELECT count(*) n FROM original_management_queues').get().n;

test('original qualification queue survives close/reopen and fresh login without replay, renewal or secret storage', async t => {
  const f = await queueFixture(t), h = await f.queuePrepare();
  assert.ok(Object.isFrozen(h)); assert.equal(queueCount(f), 1);
  const prepared = await f.ledger.readQueue(f.original, f.actor);
  assert.equal(prepared.state, 'prepared'); assert.equal(prepared.dispatchedAt, 0);
  const envelope = await f.ledger.claimQueueDispatch(h, f.actor);
  const raw = Buffer.from(envelope.payload_base64, 'base64'), claims = JSON.parse(raw);
  assert.ok(verify(null, Buffer.concat([Buffer.from(delegationPrefix), raw]), f.keys.publicKey, Buffer.from(envelope.signature_base64, 'base64')));
  assert.equal(claims.ability, originalQualificationAbility);
  assert.equal(claims.arguments_sha256, delegationDigest(f.queueRequest));
  assert.notEqual(claims.request_id, f.original);
  const context = f.queueOutput(), saved = await f.ledger.recordQueue(f.original, h.queueId, f.actor, context);
  assert.equal(saved.state, 'observed'); assert.deepEqual(saved.context, context);
  const body = f.store.db.prepare('SELECT body FROM original_management_queues').get().body;
  for (const secret of [f.raw, envelope.payload_base64, envelope.signature_base64, JSON.stringify(f.request.changes)])
    if (secret) assert.equal(body.includes(secret), false);
  assert.deepEqual(f.store.db.prepare('SELECT state FROM original_management_queue_history ORDER BY ordinal').all().map(x => x.state),
    ['prepared', 'dispatched', 'observed']);
  assert.equal(f.store.db.prepare("SELECT count(*) n FROM audit WHERE event='original-qualification-queue'").get().n, 3);
  f.close(f.store);
  const next = await f.auth(`Bearer ${await f.token(f.now + 1800)}`), reopened = f.open();
  assert.deepEqual(await reopened.ledger.readQueue(f.original, next), saved);
  assert.equal((await reopened.ledger.readCurrent(f.original, next)).mapping.authorization.expiresAt, f.actor.expiresAt);
  await assert.rejects(() => f.queuePrepare(reopened.ledger, next));
  await assert.rejects(() => reopened.ledger.claimQueueDispatch(h, next));
  await assert.rejects(() => reopened.ledger.recordQueue(f.original, h.queueId, next, context));
});

test('concurrent original queue preparation, dispatch and observation each have exactly one durable winner', async t => {
  const f = await queueFixture(t), second = f.open();
  const attempts = await Promise.allSettled([f.queuePrepare(), f.queuePrepare(second.ledger)]);
  assert.equal(attempts.filter(x => x.status === 'fulfilled').length, 1); assert.equal(queueCount(f), 1);
  const index = attempts.findIndex(x => x.status === 'fulfilled'), ledger = index === 0 ? f.ledger : second.ledger;
  const h = attempts[index].value;
  const dispatch = await Promise.allSettled([ledger.claimQueueDispatch(h, f.actor), ledger.claimQueueDispatch(h, f.actor)]);
  assert.equal(dispatch.filter(x => x.status === 'fulfilled').length, 1);
  const response = f.queueOutput();
  const observed = await Promise.allSettled([f.ledger, second.ledger].map(l => l.recordQueue(f.original, h.queueId, f.actor, response)));
  assert.equal(observed.filter(x => x.status === 'fulfilled').length, 1);
  assert.equal(f.store.db.prepare('SELECT count(*) n FROM original_management_queue_history').get().n, 3);
});

test('queue persistence failure rolls back its marker, transition history and audit at every boundary', async t => {
  for (const stage of ['prepared', 'dispatched', 'observed']) {
    const f = await queueFixture(t);
    let h;
    if (stage !== 'prepared') h = await f.queuePrepare();
    if (stage === 'observed') await f.ledger.claimQueueDispatch(h, f.actor);
    const baseline = f.store.db.prepare('SELECT * FROM original_management_queues').all();
    const history = f.store.db.prepare('SELECT * FROM original_management_queue_history').all();
    const marker = f.store.db.prepare('SELECT queue_id FROM original_management_attempts').get();
    const audit = f.store.db.prepare('SELECT count(*) n FROM audit').get().n;
    f.store.db.exec("CREATE TRIGGER reject_queue BEFORE INSERT ON audit WHEN NEW.event='original-qualification-queue' BEGIN SELECT RAISE(ABORT,'fixture'); END");
    const operation = () => stage === 'prepared' ? f.queuePrepare() : stage === 'dispatched'
      ? f.ledger.claimQueueDispatch(h, f.actor) : f.ledger.recordQueue(f.original, h.queueId, f.actor, f.queueOutput());
    await assert.rejects(operation, stage);
    assert.deepEqual(f.store.db.prepare('SELECT * FROM original_management_queues').all(), baseline);
    assert.deepEqual(f.store.db.prepare('SELECT * FROM original_management_queue_history').all(), history);
    assert.deepEqual(f.store.db.prepare('SELECT queue_id FROM original_management_attempts').get(), marker);
    assert.equal(f.store.db.prepare('SELECT count(*) n FROM audit').get().n, audit);
    f.store.db.exec('DROP TRIGGER reject_queue'); await operation();
  }
});

test('queue refuses absent readiness, forged identity, wrong request, missing provider binding and mismatched source', async t => {
  for (const failure of ['disabled', 'forged', 'readonly', 'person', 'request', 'binding', 'policy', 'source', 'epoch']) {
    const f = await queueFixture(t); let actor = f.actor, request = f.queueRequest;
    if (failure === 'disabled') f.ledger.queueReadiness = undefined;
    if (failure === 'forged') actor = { ...actor };
    if (failure === 'readonly') actor = await f.auth(`Bearer ${await f.token(f.now + 600, '123', { scope: 'mrn:operations' })}`);
    if (failure === 'person') actor = await f.auth(`Bearer ${await f.token(f.now + 600, '456')}`);
    if (failure === 'request') request = { ...request, actor: 'caller-supplied' };
    if (failure === 'binding') f.store.db.exec('DELETE FROM original_management_bindings');
    if (failure === 'policy') f.queueState.pins.authorization_policy_sha256 = 'f'.repeat(64);
    if (failure === 'source') f.queueState.pins.provider_source_sha = 'f'.repeat(40);
    if (failure === 'epoch') f.queueState.epoch = 'f'.repeat(64);
    await assert.rejects(() => f.ledger.prepareQueue(f.original, actor, request, f.challenge), failure);
    assert.equal(queueCount(f), 0);
  }
});

test('current protected source and Directory authority are rechecked for queue dispatch and observation', async t => {
  for (const stage of ['prepare', 'dispatch', 'observe']) for (const failure of ['directory', 'revocation', 'source', 'manifest', 'epoch', 'unavailable']) {
    const f = await queueFixture(t);
    const h = stage === 'prepare' ? null : await f.queuePrepare();
    if (stage === 'observe') await f.ledger.claimQueueDispatch(h, f.actor);
    if (failure === 'directory') f.state.duringCheck = () => { throw new Error('Directory unavailable'); };
    if (failure === 'revocation') f.queueState.duringCheck = () => f.store.transaction(() =>
      f.ledger.principals.pending(digest(f.actor.googleSubject), requesterDigest(f.actor)));
    if (failure === 'source') f.queueState.pins.provider_source_sha = 'f'.repeat(40);
    if (failure === 'manifest') {
      if (stage === 'prepare') continue; // A new original may admit the current manifest; retained queues cannot change it.
      f.queueState.pins.installed_manifest_sha256 = 'f'.repeat(64);
    }
    if (failure === 'epoch') f.queueState.epoch = 'f'.repeat(64);
    if (failure === 'unavailable') f.queueState.duringCheck = () => { throw new Error('Protected worker unavailable'); };
    await assert.rejects(() => stage === 'prepare' ? f.queuePrepare() : stage === 'dispatch'
      ? f.ledger.claimQueueDispatch(h, f.actor) : f.ledger.recordQueue(f.original, h.queueId, f.actor, f.queueOutput()), `${stage}/${failure}`);
    assert.equal(f.store.db.prepare('SELECT state FROM original_management_queues').get()?.state,
      stage === 'prepare' ? undefined : stage === 'dispatch' ? 'prepared' : 'dispatched');
  }
});

test('lost or damaged queue rows, markers and ordered history cannot be reset or recreated', async t => {
  for (const damage of ['body-missing', 'history-missing', 'marker-missing', 'body-and-marker-missing', 'hash', 'body-and-hash', 'rewind', 'history-state', 'response']) {
    const f = await queueFixture(t), h = await f.queuePrepare();
    await f.ledger.claimQueueDispatch(h, f.actor);
    await f.ledger.recordQueue(f.original, h.queueId, f.actor, f.queueOutput());
    if (['body-missing', 'body-and-marker-missing'].includes(damage)) f.store.db.exec('DELETE FROM original_management_queues');
    if (damage === 'history-missing') f.store.db.exec('DELETE FROM original_management_queue_history');
    if (['marker-missing', 'body-and-marker-missing'].includes(damage)) f.store.db.exec("UPDATE original_management_attempts SET queue_id=''");
    if (damage === 'hash') f.store.db.exec("UPDATE original_management_queues SET digest='bad'");
    if (damage === 'body-and-hash') {
      const q = JSON.parse(f.store.db.prepare('SELECT body FROM original_management_queues').get().body);
      q.currentAuthorizationSha256 = 'f'.repeat(64); const body = JSON.stringify(q);
      f.store.db.prepare('UPDATE original_management_queues SET body=?,digest=?').run(body, digest(body));
    }
    if (damage === 'rewind') f.store.db.exec("UPDATE original_management_queues SET state='prepared',dispatched_at=0,response='',response_digest=''");
    if (damage === 'history-state') f.store.db.exec("UPDATE original_management_queue_history SET state='prepared' WHERE ordinal=2");
    if (damage === 'response') f.store.db.exec("UPDATE original_management_queues SET response='{}'");
    await assert.rejects(() => f.ledger.readQueue(f.original, f.actor), damage);
    await assert.rejects(() => f.queuePrepare(), damage);
    await assert.rejects(() => f.ledger.recordQueue(f.original, h.queueId, f.actor, f.queueOutput()), damage);
  }
});

test('queue rows and history independently forbid missing principal reinitialization across all protocols', async t => {
  for (const survivor of ['row', 'history']) {
    const f = await queueFixture(t); await f.queuePrepare(); const write = writers(f);
    f.store.db.exec(`DELETE FROM content_access_principals; DELETE FROM content_access_principal_history;
      DELETE FROM original_management_authorizations; DELETE FROM original_management_attempts; DELETE FROM original_management_reviews;
      DELETE FROM original_management_controls; DELETE FROM original_management_control_history; DELETE FROM original_management_bindings;`);
    f.store.db.exec(`DELETE FROM ${survivor === 'row' ? 'original_management_queue_history' : 'original_management_queues'}`);
    for (const create of Object.values(write)) await assert.rejects(async () => create(), survivor);
    assert.equal(f.store.db.prepare('SELECT count(*) n FROM content_access_principals').get().n, 0);
  }
});

function originalQueueClient(f) {
  const state = { calls: [], confirmed: 0 }, response = value => ({ content: [{ type: 'text', text: JSON.stringify(value) }] });
  const client = {
    async readResource() { return { contents: [{ text: JSON.stringify({ connected: true, dashboardHost: 'wpcontrol.mrndev.io', abilitiesCount: 115 }) }] }; },
    async listTools() { return { tools: Object.values(delegationTools).map(name => ({ name,
      annotations: { readOnlyHint: false, destructiveHint: name === delegationTools.run } })) }; },
    async callTool({ name, arguments: args }) {
      state.calls.push({ name, args });
      if (name === delegationTools.challenge) {
        assert.equal(args.ability, originalQualificationAbility);
        assert.equal(args.arguments_sha256, delegationDigest(f.queueRequest)); return response(f.challenge);
      }
      assert.equal(name, delegationTools.run);
      const bytes = Buffer.from(args.delegation.payload_base64, 'base64'), claims = JSON.parse(bytes);
      assert.ok(verify(null, Buffer.concat([Buffer.from(delegationPrefix), bytes]), f.keys.publicKey, Buffer.from(args.delegation.signature_base64, 'base64')));
      const retained = await f.ledger.readQueue(f.original, f.actor);
      assert.equal(retained.state, 'dispatched'); assert.equal(retained.queue.envelopeSha256, delegationDigest(args.delegation));
      assert.equal(claims.request_id, retained.queue.delegationRequestId);
      assert.equal(args.ability, originalQualificationAbility); assert.equal(args.arguments_json, delegationCanonical(f.queueRequest));
      if (!args.user_confirmed) {
        state.first = structuredClone(args); await state.duringConfirmation?.();
        return response(state.failure === 'confirmation' ? { status: 'SAFE_MODE_BLOCKED' }
          : { status: 'CONFIRMATION_REQUIRED', confirmation_token: 'fixture-queue-confirmation' });
      }
      assert.deepEqual(args.delegation, state.first.delegation); assert.equal(args.confirmation_token, 'fixture-queue-confirmation');
      state.confirmed++;
      if (state.failure === 'lost-response') throw new Error('Fixture lost queue response');
      const result = { schema: 1, request_id: claims.request_id, ability: args.ability, policy_sha256: claims.policy_sha256,
        actor: { oauth_subject: claims.oauth_subject, google_sub: claims.google_sub, email: claims.email }, result: f.queueOutput() };
      state.corrupt?.(result); await state.duringResult?.(); return response(result);
    },
  };
  const delegated = new DelegatedMainwpClient(client, f.actor, f.signer);
  const queue = () => delegated.queueOriginalManagementQualification(f.queueRequest, { ledger: f.ledger, originalRequestId: f.original });
  return { state, client, delegated, queue };
}

test('signed existing queue crosses both confirmation phases once and durably retains the exact provider context', async t => {
  const f = await queueFixture(t), p = originalQueueClient(f), output = await p.queue();
  assert.deepEqual((await f.ledger.readQueue(f.original, f.actor)).context, output);
  assert.deepEqual(p.state.calls.map(row => row.name), [delegationTools.challenge, delegationTools.run, delegationTools.run]);
  assert.equal(p.state.confirmed, 1);
  await assert.rejects(p.queue); assert.equal(p.state.confirmed, 1);
  await assert.rejects(() => p.delegated.callTool({ name: 'mrn_mainwp__request_content_edit_qualification_existing_v2', arguments: f.queueRequest }));
  await assert.rejects(() => p.delegated.queueOriginalManagementQualification(f.queueRequest, { ledger: {}, originalRequestId: f.original }));
});

test('failed confirmation and uncertain queue response stay consumed across fresh login and SQLite reopen', async t => {
  for (const failure of ['confirmation', 'lost-response']) {
    const f = await queueFixture(t), p = originalQueueClient(f); p.state.failure = failure;
    await assert.rejects(p.queue);
    const before = p.state.calls.length;
    await assert.rejects(p.queue); assert.equal(p.state.calls.length, before + 1); // Challenge is read-only; no second dispatch.
    assert.equal(p.state.confirmed, failure === 'confirmation' ? 0 : 1);
    const saved = await f.ledger.readQueue(f.original, f.actor); assert.equal(saved.state, 'dispatched');
    const next = await f.auth(`Bearer ${await f.token(f.now + 1800)}`);
    f.close(f.store); const reopened = f.open();
    assert.deepEqual(await reopened.ledger.readQueue(f.original, next), saved);
    await assert.rejects(() => f.queuePrepare(reopened.ledger, next));
  }
});

test('a changed actor, request, policy or queued context is refused without storing provider success', async t => {
  for (const field of ['actor', 'request', 'policy', 'source', 'target', 'protocol', 'unknown']) {
    const f = await queueFixture(t), p = originalQueueClient(f);
    p.state.corrupt = result => {
      if (field === 'actor') result.actor.google_sub = '999';
      if (field === 'request') result.request_id = randomUUID();
      if (field === 'policy') result.policy_sha256 = 'f'.repeat(64);
      if (field === 'source') result.result.binding.provider_source_sha = 'f'.repeat(40);
      if (field === 'target') result.result.binding.target.post_id++;
      if (field === 'protocol') result.result.binding.schema = 1;
      if (field === 'unknown') result.result.caller_email = f.actor.verifiedEmail;
      result.result.binding_sha256 = delegationDigest(result.result.binding);
    };
    await assert.rejects(p.queue, field);
    assert.equal(f.store.db.prepare('SELECT state,response FROM original_management_queues').get().state, 'dispatched');
    assert.equal(f.store.db.prepare('SELECT response FROM original_management_queues').get().response, '');
  }
});

test('changed readiness or revocation between confirmation phases and response withholds further work', async t => {
  for (const stage of ['duringConfirmation', 'duringResult']) for (const failure of ['revocation', 'manifest', 'source-binding', 'epoch', 'expiry']) {
    const f = await queueFixture(t), p = originalQueueClient(f);
    p.state[stage] = () => {
      if (failure === 'revocation') f.store.transaction(() => f.ledger.principals.pending(digest(f.actor.googleSubject), requesterDigest(f.actor)));
      if (failure === 'manifest') f.queueState.pins.installed_manifest_sha256 = 'f'.repeat(64);
      if (failure === 'source-binding') f.queueState.pins.source_binding_sha256 = 'f'.repeat(64);
      if (failure === 'epoch') f.queueState.epoch = 'f'.repeat(64);
      if (failure === 'expiry') t.mock.method(Date, 'now', () => (f.now + 61) * 1000);
    };
    await assert.rejects(p.queue, `${stage}/${failure}`);
    assert.equal(p.state.confirmed, stage === 'duringConfirmation' ? 0 : 1);
    assert.equal(f.store.db.prepare('SELECT state FROM original_management_queues').get().state, 'dispatched');
    t.mock.restoreAll();
  }
});

test('fresh login cannot claim a prepared handle or renew an expired queue transport or original grant', async t => {
  const f = await queueFixture(t), h = await f.queuePrepare();
  const next = await f.auth(`Bearer ${await f.token(f.now + 1800)}`);
  await assert.rejects(() => f.ledger.claimQueueDispatch({ ...h }, f.actor));
  await assert.rejects(() => f.ledger.claimQueueDispatch(h, next));
  let at = (f.now + 61) * 1000; t.mock.method(Date, 'now', () => at);
  assert.equal((await f.ledger.readQueue(f.original, next)).state, 'prepared');
  await assert.rejects(() => f.ledger.claimQueueDispatch(h, f.actor));
  await assert.rejects(() => f.queuePrepare(f.open().ledger, next));
  at = (f.now + 601) * 1000;
  await assert.rejects(() => f.ledger.readQueue(f.original, next));
});

async function existingAccessFixture(t, workerConfiguration) {
  const f = await queueFixture(t, workerConfiguration), context = await originalQueueClient(f).queue();
  const identity = { keyId: 'fixture', privateKey: f.keys.privateKey }, state = { calls: 0, closes: 0, mono: 0 };
  const ledger = new ExistingManagementAccessLedger(f.ledger), signer = new ExistingManagementAccessSigner(identity);
  const tool = { name: existingManagementAccessReadbackTool, annotations: { readOnlyHint: false, destructiveHint: false } };
  const status = { connected: true, dashboardHost: 'wpcontrol.mrndev.io', abilitiesCount: 115 };
  const client = {
    async readResource() { return { contents: [{ text: JSON.stringify(status) }] }; },
    async listTools() { return { tools: [tool] }; },
    async callTool({ name, arguments: args }) {
      state.calls++; assert.equal(name, existingManagementAccessReadbackTool);
      const bytes = Buffer.from(args.signed_request.payload_base64, 'base64'), claims = JSON.parse(bytes);
      assert.equal(claims.schema, 2); assert.equal(claims.query_id, state.query.query_id);
      assert.ok(verify(null, Buffer.concat([Buffer.from(existingManagementWires.qualification.query_readback_purpose_prefix), bytes]),
        f.keys.publicKey, Buffer.from(args.signed_request.signature_base64, 'base64')));
      await state.duringRead?.();
      return { content: [{ type: 'text', text: JSON.stringify(state.output ?? { stored: true, query: state.query, query_sha256: delegationDigest(state.query) }) }] };
    },
    async close() { state.closes++; },
  };
  const reader = new ExistingManagementAccessQueryReader(client);
  const veto = new ExistingManagementAccessVeto({ signer, reader, ledger, monotonic: () => state.mono });
  const query = () => ({ schema: 1, issuer: 'https://wpcontrol.mrndev.io', audience: 'https://operations.mrnwebdesigns.com',
    issued_at: Math.floor(Date.now() / 1000), expires_at: Math.floor(Date.now() / 1000) + 5, request_id: randomUUID(), nonce: 'a'.repeat(64),
    ...Object.fromEntries(['authorization_policy_sha256', 'qualification_job_id', 'site_id', 'site_url', 'target', 'requester_binding_sha256'].map(k => [k, context.binding[k]])),
    action: 'pending-veto-query', phase: 'capture', check_id: randomUUID(), query_id: randomUUID(), provider_query_nonce: 'b'.repeat(64),
    management_context_sha256: context.binding_sha256 });
  const pointer = q => ({ query_id: q.query_id, provider_query_nonce: q.provider_query_nonce });
  const call = async (q = query()) => { state.query = q; if (!reader.ready) await reader.initialize(); return veto.respond(pointer(q)); };
  return { ...f, context, identity, accessState: state, accessLedger: ledger, signer, reader, veto, client, tool, status, query, pointer, call };
}
const decodeEnvelope = value => JSON.parse(Buffer.from(value.payload_base64, 'base64'));

test('existing-v2 access uses its fixed POST reader and signature domain against the actual observed original queue', async t => {
  const f = await existingAccessFixture(t), q = f.query(), response = (await f.call(q)).signed_veto;
  const claims = decodeEnvelope(response), bytes = Buffer.from(response.payload_base64, 'base64');
  assert.equal(existingManagementAccessPath, new URL(existingManagementWires.qualification.callback_url).pathname);
  assert.equal(claims.schema, 2); assert.equal(claims.state, 'clear');
  assert.equal(claims.management_context_sha256, f.context.binding_sha256);
  assert.equal(claims.requester_binding_sha256, f.context.binding.requester_binding_sha256);
  assert.ok(verify(null, Buffer.concat([Buffer.from(existingManagementWires.qualification.veto_purpose_prefix), bytes]),
    f.keys.publicKey, Buffer.from(response.signature_base64, 'base64')));
  assert.equal(verify(null, Buffer.concat([Buffer.from(managementCaptureContract.veto_purpose_prefix), bytes]),
    f.keys.publicKey, Buffer.from(response.signature_base64, 'base64')), false);
  const original = f.ledger.originalQueuedJob(q.qualification_job_id);
  assert.deepEqual(original.queue.context, f.context); assert.equal(original.original.mapping.authorization.expiresAt, f.actor.expiresAt);
  assert.equal(f.store.db.prepare("SELECT count(*) n FROM sqlite_master WHERE name='management_access_jobs'").get().n, 0);
  const saved = f.store.db.prepare('SELECT body FROM original_management_access_queries').get().body;
  for (const forbidden of [f.raw, response.payload_base64, response.signature_base64, JSON.stringify(f.request.changes)])
    if (forbidden) assert.equal(saved.includes(forbidden), false);
  await assert.rejects(() => f.call(q));
  await assert.rejects(() => f.call({ ...f.query(), check_id: q.check_id }));
});

test('existing access refuses legacy, substituted, missing and unobserved jobs without creating a local map', async t => {
  for (const change of ['schema2-query', 'legacy', 'job', 'target', 'requester', 'context', 'policy', 'lost-map', 'lost-index', 'unobserved']) {
    const f = await existingAccessFixture(t), q = f.query();
    if (change === 'schema2-query') q.schema = 2;
    if (change === 'legacy') q.management_context_sha256 = delegationDigest(f.values.context.binding);
    if (change === 'job') q.qualification_job_id = randomUUID();
    if (change === 'target') q.target = { ...q.target, post_id: q.target.post_id + 1 };
    if (change === 'requester') q.requester_binding_sha256 = 'f'.repeat(64);
    if (change === 'context') q.management_context_sha256 = 'f'.repeat(64);
    if (change === 'policy') q.authorization_policy_sha256 = 'f'.repeat(64);
    if (change === 'lost-map') f.store.db.exec('DELETE FROM original_management_authorizations');
    if (change === 'lost-index') f.store.db.exec('UPDATE original_management_queues SET provider_job=NULL');
    if (change === 'unobserved') f.store.db.exec("UPDATE original_management_queues SET state='dispatched'");
    await assert.rejects(() => f.call(q), change);
    assert.equal(f.store.db.prepare('SELECT count(*) n FROM original_management_access_queries').get().n, 0);
  }
});

test('pending revocation, current policy removal and provider source changes deny existing callbacks after readback', async t => {
  for (const kind of ['revocation', 'policy', 'source']) {
    const f = await existingAccessFixture(t);
    f.accessState.duringRead = () => {
      if (kind === 'revocation') f.accessLedger.recordPendingRevocation(f.context.binding.qualification_job_id);
      if (kind === 'policy') f.policy.emailDomains[0].portfolio.actions = ['read'];
      if (kind === 'source') f.pins.provider_source_sha = 'f'.repeat(40);
    };
    const claims = decodeEnvelope((await f.call()).signed_veto);
    assert.equal(claims.state, kind === 'revocation' ? 'pending-revocation' : 'denied');
    assert.throws(() => f.ledger.originalQueuedJob(f.context.binding.qualification_job_id));
  }
});

test('access consumption is atomic and survives SQLite reopen; lost history cannot replenish its budget', async t => {
  const f = await existingAccessFixture(t), q = f.query();
  f.store.db.exec("CREATE TRIGGER fail_v2_access BEFORE INSERT ON audit WHEN NEW.event='original-queue-access' BEGIN SELECT RAISE(ABORT,'fixture'); END");
  await assert.rejects(() => f.call(q));
  assert.equal(f.store.db.prepare('SELECT count(*) n FROM original_management_access_queries').get().n, 0);
  assert.equal(f.store.db.prepare('SELECT access_count FROM original_management_attempts').get().access_count, 0);
  f.store.db.exec('DROP TRIGGER fail_v2_access'); await f.call(q);
  f.close(f.store); const reopened = f.open();
  const ledger = new ExistingManagementAccessLedger(reopened.ledger);
  assert.throws(() => ledger.answer(q, delegationDigest(q), () => ({})));
  reopened.store.db.exec('DELETE FROM original_management_access_queries');
  assert.throws(() => ledger.answer(f.query(), delegationDigest(q), () => ({})));
  assert.throws(() => reopened.ledger.originalQueuedJob(f.context.binding.qualification_job_id));
  const changed = f.query();
  assert.throws(() => ledger.answer(changed, delegationDigest(changed), () => ({})));
});

test('the existing access budget exhausts after 512 accepted queries and cannot be reset by a counter rewind', async t => {
  const f = await existingAccessFixture(t), now = Date.now(); t.mock.method(Date, 'now', () => now);
  for (let n = 0; n < 512; n++) {
    const q = f.query();
    const answer = f.accessLedger.answer(q, delegationDigest(q), (state, revision) => ({ state, revision }));
    assert.equal(answer.state, 'clear');
  }
  assert.equal(f.store.db.prepare('SELECT access_count FROM original_management_attempts').get().access_count, 512);
  const q = f.query(); let signed = false;
  const answer = () => f.accessLedger.answer(q, delegationDigest(q), () => { signed = true; return {}; });
  assert.throws(answer); assert.equal(signed, false);
  assert.equal(f.store.db.prepare('SELECT count(*) n FROM original_management_access_queries').get().n, 512);
  f.store.db.exec("UPDATE original_management_attempts SET access_count=0,access_digest=''");
  assert.throws(answer); assert.equal(signed, false);
  assert.throws(() => f.ledger.originalQueuedJob(q.qualification_job_id));
});

test('access history alone prevents principal reinitialization and concurrent callbacks cannot consume one query twice', async t => {
  const f = await existingAccessFixture(t), q = f.query();
  const outcomes = await Promise.allSettled([f.call(q), f.call(q)]);
  assert.equal(outcomes.filter(r => r.status === 'fulfilled').length, 1);
  const writersForPrincipal = writers(f);
  f.store.db.exec(`DELETE FROM content_access_principals; DELETE FROM content_access_principal_history;
    DELETE FROM original_management_authorizations; DELETE FROM original_management_attempts; DELETE FROM original_management_reviews;
    DELETE FROM original_management_controls; DELETE FROM original_management_control_history;
    DELETE FROM original_management_bindings; DELETE FROM original_management_queues; DELETE FROM original_management_queue_history;`);
  for (const create of Object.values(writersForPrincipal)) await assert.rejects(async () => create());
});

test('existing callback closes on changed reader metadata, timeout, or runtime shutdown with no old-route fallback', async t => {
  for (const kind of ['old-tool', 'unsafe', 'host', 'expired', 'slow']) {
    const f = await existingAccessFixture(t);
    if (kind === 'old-tool') f.tool.name = 'mrn_mainwp__read_management_access_veto_query_v1';
    if (kind === 'unsafe') f.tool.annotations.readOnlyHint = true;
    if (kind === 'host') f.status.dashboardHost = 'other.example.invalid';
    if (kind === 'expired') f.accessState.duringRead = () => { f.accessState.query.expires_at = Math.floor(Date.now() / 1000) - 1; };
    if (kind === 'slow') f.accessState.duringRead = () => { f.accessState.mono = 2500; };
    await assert.rejects(() => f.call(), kind);
    assert.equal(f.store.db.prepare('SELECT count(*) n FROM original_management_access_queries').get().n, 0);
  }
  assert.equal(await createExistingManagementAccessRuntime(), null);
  const f = await existingAccessFixture(t);
  const runtime = await createExistingManagementAccessRuntime({ enabled: true, ledger: f.ledger, identity: f.identity, connect: async () => f.client });
  f.accessState.query = f.query(); await runtime.veto.respond(f.pointer(f.accessState.query));
  await runtime.close(); await runtime.close(); assert.equal(f.accessState.closes, 1);
  await assert.rejects(() => runtime.veto.respond(f.pointer(f.query())));
});

test('existing access HTTP is opt-in, fixed-origin and shares the bounded private POST controls', async t => {
  const f = await existingAccessFixture(t); await f.reader.initialize();
  assert.throws(() => createHttpServer({ publicUrl: 'https://other.example/mcp', existingManagementAccessVeto: f.veto }));
  const server = createHttpServer({ publicUrl: audience, existingManagementAccessVeto: f.veto });
  await new Promise(resolve => server.listen(0, '127.0.0.1', resolve)); t.after(() => new Promise(resolve => server.close(resolve)));
  const send = (path, value, headers = {}, method = 'POST') => new Promise((resolve, reject) => {
    const req = httpRequest({ host: '127.0.0.1', port: server.address().port, path, method, agent: false,
      headers: { Host: 'operations.mrnwebdesigns.com', 'Content-Type': 'application/json', ...headers } }, res => {
      const chunks = []; res.on('data', c => chunks.push(c)); res.on('end', () => resolve({ status: res.statusCode, body: JSON.parse(Buffer.concat(chunks)) }));
    }); req.on('error', reject); req.end(delegationCanonical(value));
  });
  f.accessState.query = f.query(); const pointer = f.pointer(f.accessState.query);
  assert.equal((await send(existingManagementAccessPath, pointer)).status, 200);
  for (const [path, body, headers, method] of [
    [existingManagementAccessPath, pointer, {}, 'GET'], [existingManagementAccessPath, pointer, { Authorization: 'Bearer caller' }],
    [existingManagementAccessPath, pointer, { Origin: 'https://caller.example' }],
    [existingManagementAccessPath, { ...pointer, actor: f.actor.verifiedEmail }, {}],
    [existingManagementAccessPath, { padding: 'x'.repeat(600) }, {}],
    ['/internal/mainwp/management-existing-access-veto', pointer, {}], ['/internal/mainwp/management-access-veto', pointer, {}],
  ]) assert.notEqual((await send(path, body, headers, method)).status, 200);
  const disabled = createHttpServer({ publicUrl: audience });
  await new Promise(resolve => disabled.listen(0, '127.0.0.1', resolve));
  const result = await new Promise((resolve, reject) => {
    const req = httpRequest({ host: '127.0.0.1', port: disabled.address().port, path: existingManagementAccessPath,
      method: 'POST', headers: { Host: 'operations.mrnwebdesigns.com', 'Content-Type': 'application/json' } }, res => {
      res.resume(); res.on('end', () => resolve(res.statusCode));
    }); req.on('error', reject); req.end(delegationCanonical(pointer));
  });
  assert.equal(result, 404); await new Promise(resolve => disabled.close(resolve));
});

async function existingContextFixture(t) {
  const f = await existingAccessFixture(t), state = { calls: 0, closes: 0, mono: 0, claims: [] };
  const registrarKeys = generateKeyPairSync('ed25519'), registrar = { keyId: 'registrar-fixture', privateKey: registrarKeys.privateKey };
  const tool = { name: existingManagementContextTool, annotations: { readOnlyHint: false, destructiveHint: false } };
  const client = {
    readResource: () => f.client.readResource(), listTools: async () => ({ tools: [tool] }),
    async callTool({ name, arguments: input }, _schema, options) {
      assert.equal(name, existingManagementContextTool); assert.equal(options.timeout, 20000);
      state.calls++;
      const bytes = Buffer.from(input.signed_request.payload_base64, 'base64'), claims = JSON.parse(bytes);
      assert.equal(claims.schema, 2); assert.equal(claims.action, 'context');
      assert.equal(claims.issuer, 'mrn-content-qualification'); assert.equal(claims.audience, 'https://wpcontrol.mrndev.io');
      assert.deepEqual(claims.binding, f.context.binding); assert.equal(claims.binding_sha256, f.context.binding_sha256);
      assert.ok(claims.expires_at <= claims.issued_at + 20 && claims.expires_at <= f.actor.expiresAt / 1000);
      assert.ok(verify(null, Buffer.concat([Buffer.from(existingManagementWires.qualification.purposes.context), bytes]),
        registrarKeys.publicKey, Buffer.from(input.signed_request.signature_base64, 'base64')));
      assert.equal(verify(null, Buffer.concat([Buffer.from(existingManagementWires.qualification.purposes.context), bytes]),
        f.keys.publicKey, Buffer.from(input.signed_request.signature_base64, 'base64')), false);
      assert.equal(verify(null, Buffer.concat([Buffer.from(managementCaptureContract.purposes.context), bytes]),
        registrarKeys.publicKey, Buffer.from(input.signed_request.signature_base64, 'base64')), false);
      state.claims.push(claims); await state.duringRead?.(input);
      // Controlled provider fixture only. Native provider generation/transport
      // is a separate combined test; this exercises both gateway components.
      const veto = decodeEnvelope((await f.call({ ...f.query(), phase: 'read-capture' })).signed_veto);
      assert.equal(veto.state, 'clear');
      await state.afterCallback?.();
      return { content: [{ type: 'text', text: JSON.stringify(state.output ?? f.context) }] };
    },
    async close() { state.closes++; },
  };
  const reader = new ExistingManagementContextReader(client);
  const contextReader = new ExistingManagementContext({ ledger: f.ledger, reader, registrar, monotonic: () => state.mono });
  await reader.initialize();
  return { ...f, registrar, contextClient: client, contextState: state, contextTool: tool, contextReader, reader,
    readContext: () => contextReader.read(f.context.binding.qualification_job_id) };
}

test('private existing context uses the frozen registrar route, original job and independent identity callback', async t => {
  const f = await existingContextFixture(t), checks = f.state.checks;
  assert.deepEqual(await f.readContext(), f.context);
  assert.equal(f.contextState.calls, 1); assert.equal(f.accessState.calls, 1);
  assert.equal(f.queueState.lastActor.expiresAt, f.actor.expiresAt);
  assert.equal(f.state.checks - checks, 4); // Before/after protected readiness, on each side of provider I/O.
  assert.deepEqual(await f.readContext(), f.context);
  assert.notEqual(f.contextState.claims[0].request_id, f.contextState.claims[1].request_id);
  assert.notEqual(f.contextState.claims[0].nonce, f.contextState.claims[1].nonce);
  assert.equal(f.store.db.prepare('SELECT access_count FROM original_management_attempts').get().access_count, 2);
  await assert.rejects(() => f.contextReader.read(randomUUID())); assert.equal(f.contextState.calls, 2);
});

test('existing context checks staff, source and revocation before dispatch and after the callback', async t => {
  for (const stage of ['before', 'after']) for (const kind of ['revoked', 'staff', 'policy', 'source', 'epoch']) {
    const f = await existingContextFixture(t), mutate = () => {
      if (kind === 'revoked') f.accessLedger.recordPendingRevocation(f.context.binding.qualification_job_id);
      if (kind === 'staff') f.state.duringCheck = () => { throw new Error('Current Directory denied'); };
      if (kind === 'policy') f.policy.emailDomains[0].portfolio.actions = ['read'];
      if (kind === 'source') f.queueState.pins.installed_manifest_sha256 = 'f'.repeat(64);
      if (kind === 'epoch') f.queueState.epoch = 'f'.repeat(64);
    };
    if (stage === 'before') mutate(); else f.contextState.afterCallback = mutate;
    await assert.rejects(f.readContext, `${stage}/${kind}`);
    assert.equal(f.contextState.calls, stage === 'before' ? 0 : 1);
  }
});

test('existing context discards substituted or late responses without retrying or changing its retained binding', async t => {
  for (const kind of ['binding', 'state', 'extra', 'mutated-input', 'expiry', 'monotonic']) {
    const f = await existingContextFixture(t);
    f.contextState.afterCallback = () => {
      const value = structuredClone(f.context);
      if (kind === 'binding') value.binding.original_resource_authorization_sha256 = 'f'.repeat(64);
      if (kind === 'state') value.state = 'capturing';
      if (kind === 'extra') value.current = true;
      if (kind === 'expiry') t.mock.method(Date, 'now', () => (f.contextState.claims[0].expires_at + 1) * 1000);
      if (kind === 'monotonic') f.contextState.mono = 20000;
      f.contextState.output = value;
    };
    if (kind === 'mutated-input') f.contextState.duringRead = input => { input.signed_request.key_id = 'changed'; };
    await assert.rejects(f.readContext, kind); assert.equal(f.contextState.calls, 1);
    t.mock.restoreAll();
    assert.deepEqual(f.ledger.originalQueuedJob(f.context.binding.qualification_job_id).queue.context, f.context);
  }
});

test('existing context has no legacy capability fallback and private lifecycle cannot read after shutdown', async t => {
  for (const kind of ['legacy', 'unsafe', 'host']) {
    const f = await existingContextFixture(t);
    if (kind === 'legacy') f.contextTool.name = 'mrn_mainwp__get_content_edit_qualification_context_v1';
    if (kind === 'unsafe') f.contextTool.annotations.readOnlyHint = true;
    if (kind === 'host') f.status.dashboardHost = 'other.example.invalid';
    await assert.rejects(() => f.reader.initialize()); await assert.rejects(f.readContext);
    assert.equal(f.contextState.calls, 0);
  }
  assert.equal(await createExistingManagementContextRuntime(), null);
  const f = await existingContextFixture(t);
  const runtime = await createExistingManagementContextRuntime({ enabled: true, ledger: f.ledger, registrar: f.registrar,
    connect: async () => f.contextClient });
  assert.deepEqual(await runtime.context.read(f.context.binding.qualification_job_id), f.context);
  await runtime.close(); await runtime.close(); assert.equal(f.contextState.closes, 1);
  await assert.rejects(() => runtime.context.read(f.context.binding.qualification_job_id));
  await assert.rejects(() => createExistingManagementContextRuntime({ enabled: true, ledger: {}, registrar: f.registrar,
    connect: async () => f.contextClient })); assert.equal(f.contextState.closes, 2);
});

async function existingCaptureFixture(t, workerConfiguration) {
  const f = await existingAccessFixture(t, workerConfiguration), state = { calls: [], closes: 0, mono: 0 };
  const keys = generateKeyPairSync('ed25519'), registrar = { keyId: 'capture-registrar', privateKey: keys.privateKey };
  const value = JSON.parse(readFileSync(new URL('./fixtures/content-management-qualification-v1.json', import.meta.url))).capture_output;
  const artifact = value.capture_artifact, b = f.context.binding;
  Object.assign(artifact, { binding: structuredClone(b), binding_sha256: f.context.binding_sha256,
    review: structuredClone(f.values.expected.evidence.review), migration: structuredClone(f.values.expected.evidence.migration) });
  artifact.dataset.read = structuredClone(f.values.expected.evidence.read);
  artifact.dataset.source_binding_sha256 = b.source_binding_sha256;
  const rehash = output => {
    const a = output.capture_artifact, d = a.dataset;
    a.binding_sha256 = delegationDigest(a.binding); d.parity_sha256 = delegationDigest(d.parity_manifest);
    a.dataset_sha256 = delegationDigest(d); output.capture_artifact_sha256 = delegationDigest(a);
    Object.assign(output.measurements, { binding_sha256: a.binding_sha256, capture_artifact_sha256: output.capture_artifact_sha256,
      dataset_sha256: a.dataset_sha256, snapshot_sha256: a.binding.before_sha256, source_binding_sha256: d.source_binding_sha256,
      parity_sha256: d.parity_sha256, dataset_bytes: Buffer.byteLength(delegationCanonical(d)),
      readback_bytes: Buffer.byteLength(delegationCanonical(d.read)), readback_sha256: delegationDigest(d.read) });
    output.measurements.chunks_received = Math.ceil(output.measurements.dataset_bytes / 32768);
    output.measurements.readback_chunks = Math.ceil(output.measurements.readback_bytes / 32768);
    output.measurements.native_calls = output.measurements.chunks_received + output.measurements.readback_chunks;
  };
  value.qualification_job_id = b.qualification_job_id; value.measurements.captured_at = Math.floor(Date.now() / 1000); rehash(value);
  const tools = Object.values(existingManagementCaptureTools).map(name => ({ name, annotations: { readOnlyHint: false, destructiveHint: false } }));
  const client = {
    readResource: () => f.client.readResource(), listTools: async () => ({ tools }), close: async () => { state.closes++; },
    async callTool({ name, arguments: input }, _schema, options) {
      const bytes = Buffer.from(input.signed_request.payload_base64, 'base64'), claims = JSON.parse(bytes);
      const action = claims.action;
      assert.equal(name, existingManagementCaptureTools[action]);
      assert.equal(options.timeout, action === 'capture' ? 120000 : 20000);
      assert.equal(claims.schema, 2); assert.deepEqual(claims.binding, b); assert.equal(claims.binding_sha256, f.context.binding_sha256);
      assert.ok(claims.expires_at <= b.expires_at && claims.expires_at <= f.actor.expiresAt / 1000);
      assert.ok(verify(null, Buffer.concat([Buffer.from(existingManagementWires.qualification.purposes[action]), bytes]),
        keys.publicKey, Buffer.from(input.signed_request.signature_base64, 'base64')));
      assert.equal(verify(null, Buffer.concat([Buffer.from(managementCaptureContract.purposes[action]), bytes]),
        keys.publicKey, Buffer.from(input.signed_request.signature_base64, 'base64')), false);
      const row = f.store.db.prepare('SELECT body FROM original_management_capture_history ORDER BY ordinal DESC LIMIT 1').get();
      assert.equal(JSON.parse(row.body).requestId, claims.request_id); assert.equal(JSON.parse(row.body).phase, 'dispatched');
      state.calls.push({ action, claims }); await state.duringRead?.(input);
      const veto = decodeEnvelope((await f.call({ ...f.query(), phase: action })).signed_veto); assert.equal(veto.state, 'clear');
      await state.afterCallback?.(); if (state.failure) throw new Error('Lost private capture response');
      return { content: [{ type: 'text', text: JSON.stringify(state.output ?? value) }] };
    },
  };
  const reader = new ExistingManagementCaptureReader(client); await reader.initialize();
  const make = ledger => new ExistingManagementCapture({ ledger, reader, registrar, monotonic: () => state.mono });
  const capture = make(f.ledger), job = b.qualification_job_id;
  return { ...f, value, rehash, captureState: state, captureTools: tools, captureClient: client, captureReader: reader,
    registrar, makeCapture: make, capture, job, runCapture: () => capture.capture(job, f.values.expected),
    recoverCapture: () => capture.recover(job, f.values.expected) };
}

test('existing capture uses the fixed registrar route and retains only immutable metadata before and after I/O', async t => {
  const f = await existingCaptureFixture(t);
  assert.deepEqual(await f.runCapture(), f.value);
  assert.deepEqual(await f.ledger.readCapture(f.job), { state: 'captured', recoveryReads: 0, receipt: {
    bindingSha256: f.context.binding_sha256, captureArtifactSha256: f.value.capture_artifact_sha256,
    measurementSha256: delegationDigest(f.value.measurements), datasetSha256: f.value.capture_artifact.dataset_sha256 } });
  await assert.rejects(f.runCapture); assert.equal(f.captureState.calls.length, 1);
  const rows = f.store.db.prepare('SELECT body FROM original_management_capture_history ORDER BY ordinal').all();
  assert.deepEqual(rows.map(r => JSON.parse(r.body).phase), ['dispatched', 'observed']);
  for (const forbidden of [f.raw, f.value.capture_artifact.dataset.read.snapshot.core.post_content, JSON.stringify(f.request.changes)])
    assert.equal(JSON.stringify(rows).includes(forbidden), false);
  assert.equal(f.store.db.prepare("SELECT count(*) n FROM audit WHERE event='original-qualification-capture'").get().n, 2);
});

test('lost response and process restart permit only exact original read recovery, never another capture or grant renewal', async t => {
  const f = await existingCaptureFixture(t); f.captureState.failure = true;
  await assert.rejects(f.runCapture);
  assert.deepEqual(await f.ledger.readCapture(f.job), { state: 'uncertain', recoveryReads: 0, receipt: null });
  const next = f.open(), recovered = f.makeCapture(next.ledger); f.captureState.failure = false;
  await assert.rejects(() => recovered.capture(f.job, f.values.expected));
  assert.deepEqual(await recovered.recover(f.job, f.values.expected), f.value);
  assert.equal((await next.ledger.readCapture(f.job)).state, 'captured');
  assert.deepEqual(f.captureState.calls.map(c => c.action), ['capture', 'read-capture']);
  assert.equal((await next.ledger.currentQueuedJob(f.job)).original.mapping.authorization.expiresAt, f.actor.expiresAt);
  assert.notEqual(f.captureState.calls[0].claims.request_id, f.captureState.calls[1].claims.request_id);
});

test('capture recovery requires an existing attempt and its durable eight-read limit survives reopen', async t => {
  const f = await existingCaptureFixture(t); await assert.rejects(f.recoverCapture); assert.equal(f.captureState.calls.length, 0);
  f.captureState.failure = true; await assert.rejects(f.runCapture);
  for (let i = 0; i < 8; i++) await assert.rejects(() => f.makeCapture(f.open().ledger).recover(f.job, f.values.expected));
  await assert.rejects(f.recoverCapture); assert.equal(f.captureState.calls.length, 9);
  assert.equal((await f.ledger.readCapture(f.job)).recoveryReads, 8);
});

test('concurrent capture claims have one winner and an older observation cannot override later recovery', async t => {
  const f = await existingCaptureFixture(t), second = f.open().ledger;
  const results = await Promise.allSettled([f.ledger.claimCapture(f.job, 'capture'), second.claimCapture(f.job, 'capture')]);
  assert.equal(results.filter(r => r.status === 'fulfilled').length, 1);
  const index = results.findIndex(r => r.status === 'fulfilled'), ledger = index === 0 ? f.ledger : second;
  const handle = results[index].value;
  await second.claimCapture(f.job, 'read-capture');
  const receipt = { bindingSha256: f.context.binding_sha256, captureArtifactSha256: f.value.capture_artifact_sha256,
    measurementSha256: delegationDigest(f.value.measurements), datasetSha256: f.value.capture_artifact.dataset_sha256 };
  await assert.rejects(() => ledger.recordCapture(handle, receipt));
  assert.equal((await f.ledger.readCapture(f.job)).state, 'uncertain');
});

test('missing, rewritten or rewound capture history cannot become an unused allowance after restart', async t => {
  for (const damage of ['rows', 'marker', 'hash', 'body', 'rewind']) {
    const f = await existingCaptureFixture(t); await f.runCapture();
    if (damage === 'rows') f.store.db.exec('DELETE FROM original_management_capture_history');
    if (damage === 'marker') f.store.db.exec("UPDATE original_management_attempts SET capture_count=0,capture_digest=''");
    if (damage === 'hash') f.store.db.exec("UPDATE original_management_capture_history SET digest='bad'");
    if (damage === 'body') f.store.db.exec("UPDATE original_management_capture_history SET body='{}'");
    if (damage === 'rewind') f.store.db.exec('DELETE FROM original_management_capture_history WHERE ordinal=2');
    const next = f.open().ledger;
    await assert.rejects(() => next.readCapture(f.job), damage);
    await assert.rejects(() => next.claimCapture(f.job, 'capture'), damage);
    await assert.rejects(() => next.claimCapture(f.job, 'read-capture'), damage);
  }
});

test('capture persistence failure rolls back events, independent markers and audit together', async t => {
  for (const phase of ['dispatched', 'observed']) {
    const f = await existingCaptureFixture(t);
    f.store.db.exec(`CREATE TRIGGER reject_capture BEFORE INSERT ON audit WHEN NEW.event='original-qualification-capture'
      AND NEW.outcome='capture:${phase}' BEGIN SELECT RAISE(ABORT,'fixture'); END`);
    await assert.rejects(f.runCapture);
    const marker = f.store.db.prepare('SELECT capture_count FROM original_management_attempts').get();
    assert.equal(marker.capture_count, phase === 'dispatched' ? 0 : 1);
    assert.equal(f.captureState.calls.length, phase === 'dispatched' ? 0 : 1);
    assert.equal((await f.ledger.readCapture(f.job)).state, phase === 'dispatched' ? 'not-started' : 'uncertain');
  }
});

test('current staff, source, policy and revocation gate capture and recovery before dispatch and after I/O', async t => {
  for (const action of ['capture', 'recover']) for (const stage of ['before', 'after'])
    for (const kind of ['staff', 'revoked', 'source', 'policy', 'epoch']) {
      const f = await existingCaptureFixture(t);
      if (action === 'recover') { f.captureState.failure = true; await assert.rejects(f.runCapture); f.captureState.failure = false; }
      const baseline = f.captureState.calls.length;
      const mutate = () => {
        if (kind === 'staff') f.state.duringCheck = () => { throw new Error('Directory denied'); };
        if (kind === 'revoked') f.accessLedger.recordPendingRevocation(f.job);
        if (kind === 'source') f.queueState.pins.installed_manifest_sha256 = 'f'.repeat(64);
        if (kind === 'policy') f.policy.emailDomains[0].portfolio.actions = ['read'];
        if (kind === 'epoch') f.queueState.epoch = 'f'.repeat(64);
      };
      if (stage === 'before') mutate(); else f.captureState.afterCallback = mutate;
      await assert.rejects(action === 'capture' ? f.runCapture : f.recoverCapture, `${action}/${stage}/${kind}`);
      assert.equal(f.captureState.calls.length, baseline + (stage === 'before' ? 0 : 1));
      assert.equal(f.store.db.prepare('SELECT capture_count FROM original_management_attempts').get().capture_count,
        baseline + (stage === 'before' ? 0 : 1));
    }
});

test('rehashed capture substitutions, input mutation, late responses and changed recovered bytes never become captured', async t => {
  for (const kind of ['site', 'person', 'content', 'parity', 'measurement', 'input', 'monotonic', 'expiry', 'legacy']) {
    const f = await existingCaptureFixture(t), value = structuredClone(f.value);
    if (kind === 'site') value.capture_artifact.binding.site_id++;
    if (kind === 'person') value.capture_artifact.binding.requester_binding_sha256 = 'f'.repeat(64);
    if (kind === 'content') value.capture_artifact.dataset.read.snapshot.core.post_title = 'Unapproved';
    if (kind === 'parity') value.capture_artifact.dataset.parity_manifest.wordpress_version = '0.0.1';
    if (kind === 'legacy') value.capture_artifact.binding.schema = 1;
    f.rehash(value); if (kind === 'measurement') value.measurements.native_calls++;
    f.captureState.output = value;
    if (kind === 'input') f.captureState.duringRead = input => { input.signed_request.key_id = 'changed'; };
    if (kind === 'monotonic') f.captureState.afterCallback = () => { f.captureState.mono = 120000; };
    if (kind === 'expiry') f.captureState.afterCallback = () => t.mock.method(Date, 'now', () => f.context.binding.expires_at * 1000);
    await assert.rejects(f.runCapture, kind); t.mock.restoreAll();
    assert.equal((await f.ledger.readCapture(f.job)).state, 'uncertain');
  }
  const f = await existingCaptureFixture(t);
  // Capture can finish in a later second than its provider measurement.
  let captureNow = Date.now() + 2000;
  t.mock.method(Date, 'now', () => captureNow);
  await f.runCapture();
  const other = structuredClone(f.value); other.measurements.captured_at++;
  // Make the substituted timestamp valid without rewinding retained events.
  captureNow = Math.max(captureNow, other.measurements.captured_at * 1000);
  assert.doesNotThrow(() => validateExistingManagementCapture(other, f.ledger.originalQueuedJob(f.job), f.values.expected));
  f.captureState.output = other;
  await assert.rejects(f.recoverCapture, { code: 'ORIGINAL_AUTHORIZATION',
    message: 'Capture response is stale, superseded or differs from the retained artifact.' });
  assert.deepEqual(f.captureState.calls.map(call => call.action), ['capture', 'read-capture']);
  assert.equal((await f.ledger.readCapture(f.job)).receipt.measurementSha256, delegationDigest(f.value.measurements));
});

test('capture history alone prevents reinitializing a lost principal through any content protocol', async t => {
  const f = await existingCaptureFixture(t); await f.runCapture(); const write = writers(f);
  f.store.db.exec(`DELETE FROM content_access_principals; DELETE FROM content_access_principal_history;
    DELETE FROM original_management_authorizations; DELETE FROM original_management_attempts; DELETE FROM original_management_reviews;
    DELETE FROM original_management_controls; DELETE FROM original_management_control_history; DELETE FROM original_management_bindings;
    DELETE FROM original_management_queues; DELETE FROM original_management_queue_history; DELETE FROM original_management_access_queries;`);
  for (const create of Object.values(write)) await assert.rejects(async () => create());
  assert.equal(f.store.db.prepare('SELECT count(*) n FROM content_access_principals').get().n, 0);
});

test('private capture lifecycle requires both exact v2 tools and closes without a legacy fallback or production registration', async t => {
  assert.equal(await createExistingManagementCaptureRuntime(), null);
  for (const change of ['missing', 'unsafe', 'host']) {
    const f = await existingCaptureFixture(t);
    if (change === 'missing') f.captureTools.pop();
    if (change === 'unsafe') f.captureTools[0].annotations.destructiveHint = true;
    if (change === 'host') f.status.dashboardHost = 'other.example.invalid';
    await assert.rejects(() => f.captureReader.initialize()); await assert.rejects(f.runCapture);
    assert.equal(f.captureState.calls.length, 0);
  }
  const f = await existingCaptureFixture(t), runtime = await createExistingManagementCaptureRuntime({ enabled: true,
    ledger: f.ledger, registrar: f.registrar, connect: async () => f.captureClient });
  await runtime.close(); await runtime.close(); assert.equal(f.captureState.closes, 1);
  await assert.rejects(() => runtime.capture.capture(f.job, f.values.expected)); assert.equal(f.captureState.calls.length, 0);
});

async function existingMeasurementsFixture(t, capture = true) {
  const f = await existingCaptureFixture(t);
  const publicKey = createPublicKey(f.registrar.privateKey);
  if (capture) await f.runCapture();
  const state = { calls: 0, closes: 0, mono: 0 }, value = {
    qualification_job_id: f.job, binding_sha256: f.context.binding_sha256,
    measurement_record_sha256: delegationDigest(f.value.measurements), measurements: structuredClone(f.value.measurements),
  };
  const tools = [{ name: existingManagementMeasurementsTool, annotations: { readOnlyHint: false, destructiveHint: false } }];
  const client = {
    readResource: () => f.client.readResource(), listTools: async () => ({ tools }), close: async () => { state.closes++; },
    async callTool({ name, arguments: input }, _schema, options) {
      assert.equal(name, existingManagementMeasurementsTool); assert.equal(options.timeout, 20000);
      const raw = Buffer.from(input.signed_request.payload_base64, 'base64'), claims = JSON.parse(raw);
      assert.equal(claims.schema, 2); assert.equal(claims.action, 'read-measurements');
      assert.deepEqual(claims.binding, f.context.binding); assert.equal(claims.binding_sha256, f.context.binding_sha256);
      assert.ok(claims.expires_at <= claims.issued_at + 20 && claims.expires_at <= f.context.binding.expires_at
        && claims.expires_at <= f.actor.expiresAt / 1000);
      assert.ok(verify(null, Buffer.concat([Buffer.from(existingManagementWires.qualification.purposes['read-measurements']), raw]),
        publicKey, Buffer.from(input.signed_request.signature_base64, 'base64')));
      assert.equal(verify(null, Buffer.concat([Buffer.from(managementCaptureContract.purposes['read-measurements']), raw]),
        publicKey, Buffer.from(input.signed_request.signature_base64, 'base64')), false);
      state.calls++; state.claims = claims; await state.duringRead?.(input);
      // The provider's measurement route uses its shared recovery access phase.
      const veto = decodeEnvelope((await f.call({ ...f.query(), phase: 'read-capture' })).signed_veto);
      assert.equal(veto.state, 'clear'); await state.afterCallback?.();
      if (state.failure) throw new Error('Lost measurement response');
      return { content: [{ type: 'text', text: JSON.stringify(state.output ?? value) }] };
    },
  };
  const reader = new ExistingManagementMeasurementsReader(client); await reader.initialize();
  const make = ledger => new ExistingManagementMeasurements({ ledger, reader, registrar: f.registrar, monotonic: () => state.mono });
  const measurements = make(f.ledger);
  return { ...f, measurementState: state, measurementClient: client, measurementTools: tools, measurementReader: reader,
    measurementValue: value, measurements, makeMeasurements: make, readMeasurements: () => measurements.read(f.job) };
}

test('existing measurements bind the original capture receipt and current callback across SQLite reopen', async t => {
  const f = await existingMeasurementsFixture(t), before = await f.ledger.readCapture(f.job);
  assert.deepEqual(await f.readMeasurements(), f.measurementValue);
  assert.deepEqual(await f.makeMeasurements(f.open().ledger).read(f.job), f.measurementValue);
  assert.equal(f.measurementState.calls, 2); assert.equal(f.captureState.calls.length, 1);
  assert.deepEqual(await f.ledger.readCapture(f.job), before);
  assert.equal((await f.ledger.currentQueuedJob(f.job)).original.mapping.authorization.expiresAt, f.actor.expiresAt);
});

test('missing, uncertain or corrupt retained capture cannot authorize a measurement read', async t => {
  for (const kind of ['missing', 'uncertain', 'corrupt']) {
    const f = await existingMeasurementsFixture(t, kind === 'corrupt');
    if (kind === 'uncertain') { f.captureState.failure = true; await assert.rejects(f.runCapture); }
    if (kind === 'corrupt') f.store.db.exec("UPDATE original_management_capture_history SET digest='bad'");
    await assert.rejects(f.readMeasurements); assert.equal(f.measurementState.calls, 0);
  }
});

test('rehashed measurement substitution and modified signed inputs are rejected against retained capture', async t => {
  for (const kind of ['job', 'binding', 'hash', 'measurement', 'extra', 'input']) {
    const f = await existingMeasurementsFixture(t), value = structuredClone(f.measurementValue);
    if (kind === 'job') value.qualification_job_id = randomUUID();
    if (kind === 'binding') value.binding_sha256 = 'f'.repeat(64);
    if (kind === 'hash') value.measurement_record_sha256 = 'f'.repeat(64);
    if (kind === 'measurement') { value.measurements.native_calls++; value.measurement_record_sha256 = delegationDigest(value.measurements); }
    if (kind === 'extra') value.admitted = true;
    if (kind === 'input') f.measurementState.duringRead = input => { input.signed_request.key_id = 'changed'; };
    f.measurementState.output = value; await assert.rejects(f.readMeasurements, kind);
    assert.equal(f.measurementState.calls, 1);
    assert.equal((await f.ledger.readCapture(f.job)).receipt.measurementSha256, f.measurementValue.measurement_record_sha256);
  }
});

test('measurement reads recheck original staff, policy, source and revocation before and after I/O', async t => {
  for (const stage of ['before', 'after']) for (const kind of ['staff', 'revoked', 'source', 'policy', 'epoch']) {
    const f = await existingMeasurementsFixture(t), mutate = () => {
      if (kind === 'staff') f.state.duringCheck = () => { throw new Error('Directory denied'); };
      if (kind === 'revoked') f.accessLedger.recordPendingRevocation(f.job);
      if (kind === 'source') f.queueState.pins.installed_manifest_sha256 = 'f'.repeat(64);
      if (kind === 'policy') f.policy.emailDomains[0].portfolio.actions = ['read'];
      if (kind === 'epoch') f.queueState.epoch = 'f'.repeat(64);
    };
    if (stage === 'before') mutate(); else f.measurementState.afterCallback = mutate;
    await assert.rejects(f.readMeasurements, `${stage}/${kind}`);
    assert.equal(f.measurementState.calls, stage === 'before' ? 0 : 1);
  }
});

test('measurement deadlines, original expiry and clock reversal reject late responses without automatic retry', async t => {
  for (const kind of ['monotonic', 'wall', 'reverse', 'expiry', 'lost']) {
    const f = await existingMeasurementsFixture(t);
    if (kind === 'lost') f.measurementState.failure = true;
    else f.measurementState.afterCallback = () => {
      if (kind === 'monotonic') f.measurementState.mono = 20000;
      if (kind === 'reverse') f.measurementState.mono = -1;
      if (kind === 'wall') { const at = Date.now() + 20000; t.mock.method(Date, 'now', () => at); }
      if (kind === 'expiry') t.mock.method(Date, 'now', () => f.context.binding.expires_at * 1000);
    };
    await assert.rejects(f.readMeasurements, kind); t.mock.restoreAll();
    assert.equal(f.measurementState.calls, 1); assert.equal(f.captureState.calls.length, 1);
    assert.equal((await f.ledger.readCapture(f.job)).receipt.measurementSha256, f.measurementValue.measurement_record_sha256);
  }
});

test('private measurement lifecycle requires the exact v2 route and closes without a legacy fallback', async t => {
  assert.equal(await createExistingManagementMeasurementsRuntime(), null);
  for (const change of ['missing', 'legacy', 'unsafe', 'host']) {
    const f = await existingMeasurementsFixture(t);
    if (change === 'missing') f.measurementTools.pop();
    if (change === 'legacy') f.measurementTools[0].name = existingManagementMeasurementsTool.replace('_existing_v2', '_v1');
    if (change === 'unsafe') f.measurementTools[0].annotations.destructiveHint = true;
    if (change === 'host') f.status.dashboardHost = 'other.invalid';
    await assert.rejects(() => f.measurementReader.initialize()); await assert.rejects(f.readMeasurements);
    assert.equal(f.measurementState.calls, 0);
  }
  const f = await existingMeasurementsFixture(t), runtime = await createExistingManagementMeasurementsRuntime({ enabled: true,
    ledger: f.ledger, registrar: f.registrar, connect: async () => f.measurementClient });
  await runtime.close(); await runtime.close(); assert.equal(f.measurementState.closes, 1);
  await assert.rejects(() => runtime.measurements.read(f.job)); assert.equal(f.measurementState.calls, 0);
});

async function existingSourceContextFixture(t, capture = true) {
  const f = await existingMeasurementsFixture(t, capture), state = { calls: 0, closes: 0, mono: 0, claims: [] };
  const owner = { evidence_policy_epoch: 1, evidence_policy_sha256: 'b'.repeat(64), source_recipe_catalog_sha256: 'c'.repeat(64) };
  const expected = { management: { binding: structuredClone(f.context.binding), management_context_sha256: f.context.binding_sha256,
    capture_artifact_sha256: f.value.capture_artifact_sha256, measurement_record_sha256: f.measurementValue.measurement_record_sha256,
    parity_sha256: f.value.measurements.parity_sha256, evidence_policy_epoch: owner.evidence_policy_epoch,
    evidence_policy_sha256: owner.evidence_policy_sha256 }, source_recipe_catalog_sha256: owner.source_recipe_catalog_sha256 };
  const tools = [{ name: existingManagementSourceContextTool, annotations: { readOnlyHint: false, destructiveHint: false } }];
  const client = { readResource: () => f.client.readResource(), listTools: async () => ({ tools }),
    close: async () => { state.closes++; }, async callTool({ name, arguments: input }, _schema, options) {
      assert.equal(name, existingManagementSourceContextTool); assert.equal(options.timeout, 20000);
      const bytes = Buffer.from(input.signed_request.payload_base64, 'base64'), claims = JSON.parse(bytes);
      assert.equal(claims.schema, 2); assert.equal(claims.action, 'read-management-source-context');
      assert.equal(claims.qualification_job_id, f.job); assert.equal(claims.management_context_sha256, f.context.binding_sha256);
      assert.ok(claims.expires_at <= claims.issued_at + 20 && claims.expires_at <= f.context.binding.expires_at
        && claims.expires_at <= f.actor.expiresAt / 1000);
      const publicKey = createPublicKey(f.registrar.privateKey);
      assert.ok(verify(null, Buffer.concat([Buffer.from(existingManagementWires.source_context.purpose), bytes]),
        publicKey, Buffer.from(input.signed_request.signature_base64, 'base64')));
      assert.equal(verify(null, Buffer.concat([Buffer.from('mrn-content-management-source-context-lookup-v1:'), bytes]),
        publicKey, Buffer.from(input.signed_request.signature_base64, 'base64')), false);
      const requestHash = delegationDigest(input); state.calls++; state.claims.push(claims);
      await state.duringRead?.(input);
      const veto = decodeEnvelope((await f.call({ ...f.query(), phase: 'retain-report' })).signed_veto);
      assert.equal(veto.state, 'clear'); await state.afterCallback?.();
      if (state.failure) throw new Error('Lost source context response');
      const output = { schema: 2, source_context: structuredClone(expected), source_context_sha256: delegationDigest(expected),
        source_bytes_verified: false, slot_created: false, admitted: false, request_sha256: requestHash };
      state.changeOutput?.(output);
      return { content: [{ type: 'text', text: JSON.stringify(output) }] };
    } };
  const reader = new ExistingManagementSourceContextReader(client); await reader.initialize();
  const make = ledger => new ExistingManagementSourceContext({ ledger, reader, registrar: f.registrar,
    owner: () => owner, monotonic: () => state.mono });
  const source = make(f.ledger);
  return { ...f, sourceOwner: owner, sourceState: state, sourceTools: tools, sourceClient: client, sourceReader: reader,
    sourceExpected: expected, source, makeSource: make, readSource: () => source.read(f.job, f.measurementValue) };
}

test('existing source context binds retained capture, service owner pins and original identity across restart', async t => {
  const f = await existingSourceContextFixture(t), before = await f.ledger.readCapture(f.job);
  const first = await f.readSource(), second = await f.makeSource(f.open().ledger).read(f.job, f.measurementValue);
  assert.deepEqual(first.source_context, f.sourceExpected); assert.deepEqual(second.source_context, f.sourceExpected);
  assert.equal(first.source_bytes_verified, false); assert.equal(first.slot_created, false); assert.equal(first.admitted, false);
  assert.notEqual(f.sourceState.claims[0].request_id, f.sourceState.claims[1].request_id);
  assert.notEqual(f.sourceState.claims[0].nonce, f.sourceState.claims[1].nonce);
  assert.equal(f.captureState.calls.length, 1); assert.deepEqual(await f.ledger.readCapture(f.job), before);
  assert.equal((await f.ledger.currentQueuedJob(f.job)).original.mapping.authorization.expiresAt, f.actor.expiresAt);
});

test('source context refuses absent capture, rehashed measurements and unconfigured owner pins before dispatch', async t => {
  for (const kind of ['missing', 'uncertain', 'corrupt', 'measurement', 'job', 'owner']) {
    const f = await existingSourceContextFixture(t, !['missing', 'uncertain'].includes(kind));
    if (kind === 'uncertain') { f.captureState.failure = true; await assert.rejects(f.runCapture); }
    if (kind === 'corrupt') f.store.db.exec("UPDATE original_management_capture_history SET digest='bad'");
    if (kind === 'measurement') { f.measurementValue.measurements.parity_sha256 = 'f'.repeat(64);
      f.measurementValue.measurement_record_sha256 = delegationDigest(f.measurementValue.measurements); }
    if (kind === 'job') f.measurementValue.qualification_job_id = randomUUID();
    if (kind === 'owner') delete f.sourceOwner.evidence_policy_sha256;
    await assert.rejects(f.readSource, kind); assert.equal(f.sourceState.calls, 0);
  }
});

test('source context rejects rehashed substitutions, authority flags and signed input changes', async t => {
  for (const kind of ['binding', 'artifact', 'measurement', 'parity', 'epoch', 'policy', 'catalog', 'request', 'flags', 'extra', 'input']) {
    const f = await existingSourceContextFixture(t);
    if (kind === 'input') f.sourceState.duringRead = input => { input.signed_request.key_id = 'changed'; };
    f.sourceState.changeOutput = value => {
      const context = value.source_context, management = context.management;
      if (kind === 'binding') management.binding.site_url = 'https://other.example.test';
      if (kind === 'artifact') management.capture_artifact_sha256 = 'f'.repeat(64);
      if (kind === 'measurement') management.measurement_record_sha256 = 'f'.repeat(64);
      if (kind === 'parity') management.parity_sha256 = 'f'.repeat(64);
      if (kind === 'epoch') management.evidence_policy_epoch++;
      if (kind === 'policy') management.evidence_policy_sha256 = 'f'.repeat(64);
      if (kind === 'catalog') context.source_recipe_catalog_sha256 = 'f'.repeat(64);
      if (kind === 'request') value.request_sha256 = 'f'.repeat(64);
      if (kind === 'flags') value.admitted = true;
      if (kind === 'extra') value.verifiedEmail = 'other@mrnwebdesigns.com';
      value.source_context_sha256 = delegationDigest(context);
    };
    await assert.rejects(f.readSource, kind); assert.equal(f.sourceState.calls, 1);
  }
});

test('source context rechecks staff, revocation and immutable owner/source state after callback', async t => {
  for (const kind of ['staff', 'revoked', 'source', 'policy', 'epoch', 'owner', 'capture']) {
    const f = await existingSourceContextFixture(t);
    f.sourceState.afterCallback = () => {
      if (kind === 'staff') f.state.duringCheck = () => { throw new Error('Directory denied'); };
      if (kind === 'revoked') f.accessLedger.recordPendingRevocation(f.job);
      if (kind === 'source') f.queueState.pins.installed_manifest_sha256 = 'f'.repeat(64);
      if (kind === 'policy') f.policy.emailDomains[0].portfolio.actions = ['read'];
      if (kind === 'epoch') f.queueState.epoch = 'f'.repeat(64);
      if (kind === 'owner') f.sourceOwner.evidence_policy_epoch++;
      if (kind === 'capture') f.store.db.exec("UPDATE original_management_capture_history SET digest='bad'");
    };
    await assert.rejects(f.readSource, kind); assert.equal(f.sourceState.calls, 1);
  }
});

test('source context refuses lost and late responses without retry or original renewal', async t => {
  for (const kind of ['monotonic', 'wall', 'reverse', 'expiry', 'lost']) {
    const f = await existingSourceContextFixture(t);
    if (kind === 'lost') f.sourceState.failure = true;
    else f.sourceState.afterCallback = () => {
      if (kind === 'monotonic') f.sourceState.mono = 20000;
      if (kind === 'reverse') f.sourceState.mono = -1;
      if (kind === 'wall') { const at = Date.now() + 20000; t.mock.method(Date, 'now', () => at); }
      if (kind === 'expiry') t.mock.method(Date, 'now', () => f.context.binding.expires_at * 1000);
    };
    await assert.rejects(f.readSource, kind); t.mock.restoreAll();
    assert.equal(f.sourceState.calls, 1); assert.equal(f.captureState.calls.length, 1);
  }
});

test('source context bounded timer rejects a stalled transport without a second dispatch', async t => {
  const f = await existingSourceContextFixture(t), schedule = globalThis.setTimeout;
  let release;
  f.sourceState.duringRead = () => new Promise(resolve => { release = resolve; });
  t.mock.method(globalThis, 'setTimeout', (callback, milliseconds, ...args) => {
    assert.ok(milliseconds > 0 && milliseconds <= 20000);
    return schedule(callback, 0, ...args);
  });
  await assert.rejects(f.readSource, { code: 'MANAGEMENT_EXISTING_SOURCE_CONTEXT_TIMEOUT',
    message: 'The original source-context lookup exceeded its time limit; no retry was dispatched.' });
  assert.equal(f.sourceState.calls, 1); assert.equal(typeof release, 'function');
  t.mock.restoreAll(); release();
  await new Promise(resolve => setImmediate(resolve));
  assert.equal(f.sourceState.calls, 1); assert.equal(f.captureState.calls.length, 1);
});

test('source context remains private and requires the exact v2 route with shutdown refusal', async t => {
  assert.equal(await createExistingManagementSourceContextRuntime(), null);
  for (const kind of ['missing', 'legacy', 'unsafe', 'host']) {
    const f = await existingSourceContextFixture(t);
    if (kind === 'missing') f.sourceTools.pop();
    if (kind === 'legacy') f.sourceTools[0].name = 'mrn_mainwp__read_content_edit_qualification_source_context_v1';
    if (kind === 'unsafe') f.sourceTools[0].annotations.destructiveHint = true;
    if (kind === 'host') f.status.dashboardHost = 'other.invalid';
    await assert.rejects(() => f.sourceReader.initialize()); await assert.rejects(f.readSource);
    assert.equal(f.sourceState.calls, 0);
  }
  const f = await existingSourceContextFixture(t), runtime = await createExistingManagementSourceContextRuntime({ enabled: true,
    ledger: f.ledger, registrar: f.registrar, owner: () => f.sourceOwner, connect: async () => f.sourceClient });
  await runtime.close(); await runtime.close(); assert.equal(f.sourceState.closes, 1);
  await assert.rejects(() => runtime.sourceContext.read(f.job, f.measurementValue)); assert.equal(f.sourceState.calls, 0);
});

async function existingCatalogFixture(t, captured = true) {
  const f = await existingCaptureFixture(t); if (captured) await f.runCapture();
  const pair = Object.fromEntries(['root', 'registrar', 'identity', 'qa'].map(role => [role, generateKeyPairSync('ed25519')]));
  const keys = Object.fromEntries(Object.entries(pair).map(([role, key]) => [role, [{ id: role,
    public_key_base64: key.publicKey.export({ format: 'der', type: 'spki' }).subarray(-32).toString('base64') }]]));
  const binding = f.context.binding;
  const catalog = { schema: 2, adapter: 'content-management-existing-source-catalog-v2', catalog_id: randomUUID(),
    issued_at: binding.created_at - 10, expires_at: binding.expires_at + 10, native_reads: true, artifacts: [] };
  const purpose = existingManagementWires.source_transfer.purposes.catalog;
  const envelope = (bytes = Buffer.from(delegationCanonical(catalog)), selectedPurpose = purpose, role = 'root') => ({
    key_id: role, payload_base64: bytes.toString('base64'),
    signature_base64: sign(null, Buffer.concat([Buffer.from(selectedPurpose), bytes]), pair[role].privateKey).toString('base64') });
  const owner = { evidence_policy_epoch: 1, evidence_policy_sha256: 'b'.repeat(64), source_recipe_catalog_sha256: 'c'.repeat(64) };
  const state = { registry: { schema: 1, keys, catalogs: [] }, pin: null, mono: 0, offset: 0, reads: 0, duringRead: null };
  const repin = () => { state.pin = delegationDigest(state.registry); };
  const publish = () => {
    owner.source_recipe_catalog_sha256 = delegationDigest(catalog);
    state.registry.catalogs = [{ sha256: owner.source_recipe_catalog_sha256, envelope: envelope() }]; repin();
  };
  publish();
  const options = { ledger: f.ledger, owner: () => owner, current: () => { state.duringRead?.(++state.reads); return state.registry; },
    expectedSha256: () => state.pin, now: () => Date.now() + state.offset, monotonic: () => state.mono };
  const registry = new ExistingManagementSourceCatalogRegistry(options);
  return { ...f, catalog, catalogOwner: owner, catalogState: state, catalogRegistry: registry, catalogOptions: options,
    catalogEnvelope: envelope, publishCatalog: publish, repinCatalog: repin, resolveCatalog: () => registry.resolve(f.job) };
}

test('existing catalog is absent by default and only resolves independent frozen-v2 owner signatures for the original captured job', async t => {
  const f = await existingCatalogFixture(t), before = await f.ledger.readCapture(f.job);
  await assert.rejects(() => new ExistingManagementSourceCatalogRegistry({ ledger: f.ledger }).resolve(f.job),
    { code: 'MANAGEMENT_SOURCE_MISSING_CATALOG' });
  for (const patch of [{ ledger: {} }, { owner: undefined }, { expectedSha256: undefined }, { current: undefined }])
    assert.throws(() => new ExistingManagementSourceCatalogRegistry({ ...f.catalogOptions, ...patch }));
  const n = f.store.db.prepare('SELECT total_changes() n').get().n, first = await f.resolveCatalog();
  assert.deepEqual(first.catalog, f.catalog); assert.equal(first.ownerCatalogSha256, f.catalogOwner.source_recipe_catalog_sha256);
  assert.equal(first.registrySha256, f.catalogState.pin);
  assert.equal(f.store.db.prepare('SELECT total_changes() n').get().n, n);
  const reopened = new ExistingManagementSourceCatalogRegistry({ ...f.catalogOptions, ledger: f.open().ledger });
  assert.deepEqual(await reopened.resolve(f.job), first);
  first.catalog.native_reads = false; first.signedCatalog.key_id = 'caller';
  assert.equal((await f.resolveCatalog()).catalog.native_reads, true);
  assert.equal((await f.resolveCatalog()).signedCatalog.key_id, 'root');
  assert.deepEqual(await f.ledger.readCapture(f.job), before);
  assert.equal(f.captureState.calls.length, 1);
});

test('existing catalog refuses missing, uncertain and corrupt original capture without reconstructing it', async t => {
  for (const kind of ['missing', 'uncertain', 'corrupt', 'unknown-job']) {
    const f = await existingCatalogFixture(t, kind === 'corrupt' || kind === 'unknown-job');
    if (kind === 'uncertain') { f.captureState.failure = true; await assert.rejects(f.runCapture); }
    if (kind === 'corrupt') f.store.db.exec("UPDATE original_management_capture_history SET digest='bad'");
    await assert.rejects(() => f.catalogRegistry.resolve(kind === 'unknown-job' ? randomUUID() : f.job));
    assert.equal(f.catalogState.reads, 0);
  }
});

test('existing catalog refuses legacy schemas, cross-purpose signatures and signer-role substitution', async t => {
  for (const kind of ['legacy-schema', 'legacy-purpose', 'capture-purpose', 'identity', 'registrar', 'qa', 'bad-signature']) {
    const f = await existingCatalogFixture(t);
    if (kind === 'legacy-schema') {
      f.catalog.schema = 1; f.catalog.adapter = 'content-management-source-catalog-v1'; f.publishCatalog();
    } else if (kind === 'bad-signature') f.catalogState.registry.catalogs[0].envelope.signature_base64 = Buffer.alloc(64).toString('base64');
    else {
      const purpose = kind === 'legacy-purpose' ? 'mrn-content-management-source-catalog-v1:'
        : kind === 'capture-purpose' ? existingManagementWires.source_transfer.purposes.capture
          : existingManagementWires.source_transfer.purposes.catalog;
      f.catalogState.registry.catalogs[0].envelope = f.catalogEnvelope(undefined, purpose,
        ['identity', 'registrar', 'qa'].includes(kind) ? kind : 'root');
    }
    f.repinCatalog(); await assert.rejects(f.resolveCatalog, kind);
  }
});

test('existing catalog refuses role aliases, duplicates, malformed or noncanonical preimages and false owner labels', async t => {
  for (const kind of ['role-alias', 'duplicate-catalog', 'duplicate-key', 'duplicate-artifact', 'label', 'hash', 'noncanonical', 'malformed', 'extra', 'oversized']) {
    const f = await existingCatalogFixture(t), registry = f.catalogState.registry;
    if (kind === 'role-alias') registry.keys.qa[0].public_key_base64 = registry.keys.root[0].public_key_base64;
    if (kind === 'duplicate-catalog') registry.catalogs.push(structuredClone(registry.catalogs[0]));
    if (kind === 'duplicate-key') registry.keys.root.push(structuredClone(registry.keys.root[0]));
    if (kind === 'duplicate-artifact') {
      const a = { artifact_id: 'fixture', root_kind: 'directory', relative_inventory_sha256: 'a'.repeat(64),
        archive_sha256: 'b'.repeat(64), archive_format: 'zip', maximum_archive_bytes: 1024 };
      f.catalog.artifacts = [a, { ...a }]; f.publishCatalog();
    }
    if (kind === 'label') registry.catalogs[0].sha256 = f.catalogOwner.source_recipe_catalog_sha256 = 'e'.repeat(64);
    if (kind === 'hash') f.catalogOwner.source_recipe_catalog_sha256 = 'e'.repeat(64);
    const invalid = { noncanonical: Buffer.from(JSON.stringify(f.catalog, null, 2)), malformed: Buffer.from([255]),
      extra: Buffer.from(delegationCanonical({ ...f.catalog, authority_verified: true })), oversized: Buffer.alloc(98305, 32) };
    if (invalid[kind]) registry.catalogs[0].envelope = f.catalogEnvelope(invalid[kind]);
    f.repinCatalog(); await assert.rejects(f.resolveCatalog, kind);
  }
});

test('existing catalog must predate the original queue and cover its entire unchanged lifetime', async t => {
  for (const kind of ['late-issue', 'early-expiry', 'invalid-range']) {
    const f = await existingCatalogFixture(t);
    if (kind === 'late-issue') f.catalog.issued_at = f.context.binding.created_at + 1;
    if (kind === 'early-expiry') f.catalog.expires_at = f.context.binding.expires_at - 1;
    if (kind === 'invalid-range') f.catalog.issued_at = f.catalog.expires_at;
    f.publishCatalog(); await assert.rejects(f.resolveCatalog, kind);
  }
  const f = await existingCatalogFixture(t);
  f.catalog.issued_at = f.context.binding.created_at; f.catalog.expires_at = f.context.binding.expires_at; f.publishCatalog();
  assert.deepEqual((await f.resolveCatalog()).catalog, f.catalog);
});

test('existing catalog rechecks fresh staff, original source, policy, capture and owner state before releasing configuration', async t => {
  for (const stage of ['before', 'during']) for (const kind of ['staff', 'revoked', 'source', 'policy', 'epoch', 'capture', 'owner', 'registry']) {
    const f = await existingCatalogFixture(t), change = () => {
      if (kind === 'staff') f.state.duringCheck = () => { throw new Error('Directory denied'); };
      if (kind === 'revoked') f.accessLedger.recordPendingRevocation(f.job);
      if (kind === 'source') f.queueState.pins.installed_manifest_sha256 = 'f'.repeat(64);
      if (kind === 'policy') f.policy.emailDomains[0].portfolio.actions = ['read'];
      if (kind === 'epoch') f.queueState.epoch = 'f'.repeat(64);
      if (kind === 'capture') f.store.db.exec("UPDATE original_management_capture_history SET digest='bad'");
      if (kind === 'owner') f.catalogOwner.source_recipe_catalog_sha256 = 'e'.repeat(64);
      if (kind === 'registry') f.catalogState.registry.catalogs[0].sha256 = 'e'.repeat(64);
    };
    if (stage === 'before') change(); else f.catalogState.duringRead = count => { if (count === 1) change(); };
    await assert.rejects(f.resolveCatalog, `${stage}/${kind}`);
  }
  for (const field of ['evidence_policy_epoch', 'evidence_policy_sha256', 'source_recipe_catalog_sha256']) {
    const f = await existingCatalogFixture(t);
    f.catalogState.duringRead = count => { if (count === 1) f.catalogOwner[field] = field === 'evidence_policy_epoch' ? 2 : 'd'.repeat(64); };
    await assert.rejects(f.resolveCatalog, field);
  }
  const f = await existingCatalogFixture(t);
  f.catalogState.duringRead = count => { if (count === 2) { f.catalog.native_reads = false; f.publishCatalog(); } };
  await assert.rejects(f.resolveCatalog);
});

test('existing catalog rejects original expiry, clock reversal and late resolution without extending authority', async t => {
  for (const kind of ['monotonic', 'wall', 'reverse', 'expiry']) {
    const f = await existingCatalogFixture(t);
    f.catalogState.duringRead = count => { if (count === 1) {
      if (kind === 'monotonic') f.catalogState.mono = 20000;
      if (kind === 'wall') f.catalogState.offset = 20000;
      if (kind === 'reverse') f.catalogState.mono = -1;
      if (kind === 'expiry') f.catalogState.offset = f.context.binding.expires_at * 1000 - Date.now();
    } };
    await assert.rejects(f.resolveCatalog, kind);
    assert.equal((await f.ledger.currentQueuedJob(f.job)).original.mapping.authorization.expiresAt, f.actor.expiresAt);
  }
});

test('existing catalog bounds a stalled fresh staff check and never dispatches source capture', async t => {
  const f = await existingCatalogFixture(t), schedule = globalThis.setTimeout;
  let release;
  f.state.duringCheck = () => new Promise(resolve => { release = resolve; });
  t.mock.method(globalThis, 'setTimeout', (callback, ms, ...args) => {
    assert.ok(ms > 0 && ms <= 20000); return schedule(callback, 0, ...args);
  });
  await assert.rejects(f.resolveCatalog, { message: 'MANAGEMENT_EXISTING_SOURCE_CATALOG_TIMEOUT' });
  assert.equal(f.catalogState.reads, 0); assert.equal(f.captureState.calls.length, 1);
  t.mock.restoreAll(); f.state.duringCheck = null; release();
  await new Promise(resolve => setImmediate(resolve));
  assert.equal(f.catalogState.reads, 0);
});

test('one fresh queued-capture observation atomically binds original authority and complete capture history across restart', async t => {
  const f = await existingCaptureFixture(t);
  const initial = await f.ledger.currentQueuedCapture(f.job);
  assert.equal(initial.capture.state, 'not-started'); assert.equal(initial.capture.receipt, null);
  await f.runCapture();
  const n = f.state.checks, saved = await f.ledger.currentQueuedCapture(f.job);
  assert.equal(f.state.checks - n, 2, 'fresh staff checks still bracket protected readiness');
  assert.equal(saved.capture.state, 'captured');
  assert.equal(saved.capture.receipt.bindingSha256, saved.current.queue.context.binding_sha256);
  assert.equal(saved.current.original.mapping.authorization.expiresAt, f.actor.expiresAt);
  assert.deepEqual((await f.ledger.currentQueuedJob(f.job)).queue.context, saved.current.queue.context);
  assert.deepEqual(await f.ledger.readCapture(f.job), saved.capture);
  assert.deepEqual(await f.open().ledger.currentQueuedCapture(f.job), saved);
  saved.capture.receipt.bindingSha256 = 'f'.repeat(64);
  assert.notEqual((await f.ledger.currentQueuedCapture(f.job)).capture.receipt.bindingSha256, 'f'.repeat(64));
});

test('queued-capture observation rejects changed history and current authority during its fresh check', async t => {
  for (const kind of ['capture-body', 'capture-marker', 'revoked', 'staff', 'source', 'queue']) {
    const f = await existingCaptureFixture(t); await f.runCapture();
    f.state.duringCheck = () => {
      if (kind === 'capture-body') f.store.db.exec("UPDATE original_management_capture_history SET digest='bad'");
      if (kind === 'capture-marker') f.store.db.exec('UPDATE original_management_attempts SET capture_count=0');
      if (kind === 'revoked') f.accessLedger.recordPendingRevocation(f.job);
      if (kind === 'staff') throw new Error('Directory denied');
      if (kind === 'source') f.queueState.pins.installed_manifest_sha256 = 'f'.repeat(64);
      if (kind === 'queue') f.store.db.exec("UPDATE original_management_queues SET response_digest='bad'");
    };
    await assert.rejects(() => f.ledger.currentQueuedCapture(f.job), kind);
    assert.equal(f.captureState.calls.length, 1);
  }
});


async function workerHistoryFixture(t, captured = true) {
  const configuration = { schema: 2, worker_contract_sha256: existingWorkerContractSha256, app_actor_id: '40',
    policy: { schema: 1, enabled: false, policy_epoch: 1, qa_key_ids: ['fixture-qa'],
      repository: 'mrnwebdesigns/mrn-code-qa-runner', repository_id: '10', ref: 'refs/heads/main', workflow_id: '20',
      workflow_path: '.github/workflows/management.yml', workflow_sha256: 'a'.repeat(64), runner_source_sha: 'b'.repeat(40),
      engine_commit: 'c'.repeat(40), engine_configuration_sha256: 'd'.repeat(64), runtime_sha256: 'e'.repeat(64),
      performance_budget_sha256: 'f'.repeat(64), render_assertions_sha256: '1'.repeat(64), hook_policy_sha256: '2'.repeat(64) } };
  const f = await existingCaptureFixture(t, () => configuration);
  if (captured) await f.runCapture();
  const measurement = { qualification_job_id: f.job, binding_sha256: f.context.binding_sha256,
    measurement_record_sha256: delegationDigest(f.value.measurements), measurements: structuredClone(f.value.measurements) };
  return { ...f, configuration, measurement, prepareWorker: ledger => (ledger ?? f.ledger).prepareWorkerDispatch(f.job, measurement) };
}

test('worker history is seeded only with a genuine new original and survives independent reopen', async t => {
  const f = await workerHistoryFixture(t), initial = await f.ledger.readWorkerHistory(f.job);
  assert.equal(initial.state, 'unused');
  assert.equal(initial.current.original.mapping.workerConfigurationSha256, delegationDigest(f.configuration));
  const prepared = await f.prepareWorker(), saved = await f.open().ledger.readWorkerHistory(f.job);
  assert.equal(saved.state, 'prepared'); assert.equal(saved.value.prepared.request.schema, 2);
  assert.equal(saved.value.prepared.request.worker_contract_sha256, existingWorkerContractSha256);
  assert.deepEqual(prepared.request, saved.value.prepared.request);
  assert.equal(saved.value.prepared.original_expires_at, f.context.binding.expires_at * 1000);
  prepared.request.context_sha256 = 'f'.repeat(64);
  assert.notEqual((await f.ledger.readWorkerHistory(f.job)).value.prepared.request.context_sha256, prepared.request.context_sha256);
  const retained = await f.ledger.recordWorkerDispatch(prepared.handle, '30');
  assert.deepEqual((await f.open().ledger.readWorkerHistory(f.job)).value.retained, retained);
  assert.equal(retained.observation.app_actor_id, '40'); assert.equal(retained.provenance.run_attempt, 1);
  assert.equal(f.store.db.prepare('SELECT count(*) n FROM original_management_worker_history').get().n, 3);
  for (const ledger of [f.ledger, f.open().ledger]) {
    await assert.rejects(() => f.prepareWorker(ledger));
    await assert.rejects(() => ledger.recordWorkerDispatch(prepared.handle, '31'));
  }
  // The actual durable record is accepted by the independent byte verifier;
  // synthetic signing proves consistency, not hosted identity or source access.
  const at = Date.now(), claims = { schema: 2, issuer: 'https://operations.mrnwebdesigns.com',
    audience: 'https://github.com/mrnwebdesigns/mrn-code-qa-runner/actions/runs/30', issued_at: Math.floor(at / 1000),
    expires_at: Math.min(Math.floor(at / 1000) + 5, f.context.binding.expires_at), request_id: randomUUID(), nonce: 'c'.repeat(64),
    action: 'read-original-existing-management-dispatch', dispatch_id: retained.provenance.dispatch_id,
    context_sha256: delegationDigest(retained.context), worker_contract_sha256: existingWorkerContractSha256,
    context: retained.context, recorded_dispatch: retained.provenance, dispatch_record: retained.observation };
  const payload = Buffer.from(delegationCanonical(claims)), raw = Buffer.from(delegationCanonical({ signed_context: {
    key_id: 'fixture', payload_base64: payload.toString('base64'), signature_base64: sign(null,
      Buffer.concat([Buffer.from(existingWorkerPurpose), payload]), f.keys.privateKey).toString('base64') } }));
  const result = verifyExistingManagementWorkerRecord({ raw, pointer: { dispatch_id: retained.provenance.dispatch_id,
    context_sha256: delegationDigest(retained.context) }, retained, originalBinding: f.context.binding,
    identityKeyId: 'fixture', identityPublicKey: f.keys.publicKey, startedAt: at, completedAt: at + 1 });
  assert.equal(result.admitted, false); assert.equal(result.current_access_proven, false);
  const rows = f.store.db.prepare('SELECT body FROM original_management_worker_history').all();
  for (const secret of [f.raw, JSON.stringify(f.request.changes), f.value.capture_artifact.dataset.read.snapshot.core.post_content])
    assert.equal(JSON.stringify(rows).includes(secret), false);
});

test('old originals cannot gain worker eligibility from newly supplied configuration', async t => {
  const f = await existingCaptureFixture(t); await f.runCapture();
  const candidate = await workerHistoryFixture(t);
  f.options.workerConfiguration = () => candidate.configuration;
  const reopened = f.open().ledger;
  await assert.rejects(() => reopened.readWorkerHistory(f.job));
  await assert.rejects(() => reopened.prepareWorkerDispatch(f.job, candidate.measurement));
  assert.equal(f.store.db.prepare('SELECT count(*) n FROM original_management_worker_history').get().n, 0);
});

test('worker configuration drift during original preparation rolls back both original and seed', async t => {
  const f = await fixture(t), source = await workerHistoryFixture(t);
  f.options.workerConfiguration = () => source.configuration;
  const ledger = new OriginalManagementAuthorizationLedger(f.store, f.options);
  f.state.duringCheck = () => { source.configuration.policy.policy_epoch++; };
  await assert.rejects(async () => ledger.prepare(f.actor, f.request, f.challenge, f.attempt, await f.ready()));
  assert.equal(count(f), 0); assert.equal(f.store.db.prepare('SELECT count(*) n FROM original_management_worker_history').get().n, 0);
});

test('worker preparation requires exact captured measurements, never uncertain or altered source', async t => {
  for (const kind of ['uncaptured', 'uncertain', 'measurement', 'rehashed', 'job', 'binding', 'extra']) {
    const f = await workerHistoryFixture(t, !['uncaptured', 'uncertain'].includes(kind));
    if (kind === 'uncertain') { f.captureState.failure = true; await assert.rejects(f.runCapture); }
    if (kind === 'measurement' || kind === 'rehashed') f.measurement.measurements.native_calls++;
    if (kind === 'rehashed') f.measurement.measurement_record_sha256 = delegationDigest(f.measurement.measurements);
    if (kind === 'job') f.measurement.qualification_job_id = randomUUID();
    if (kind === 'binding') f.measurement.binding_sha256 = 'f'.repeat(64);
    if (kind === 'extra') f.measurement.email = 'caller@mrnwebdesigns.com';
    await assert.rejects(() => f.prepareWorker(), kind);
    assert.equal((await f.ledger.readWorkerHistory(f.job)).state, 'unused');
  }
});

test('concurrent worker preparations have one winner and restart never recreates its handle', async t => {
  const f = await workerHistoryFixture(t), reopened = f.open().ledger;
  const results = await Promise.allSettled([f.prepareWorker(), f.prepareWorker(reopened)]);
  assert.equal(results.filter(x => x.status === 'fulfilled').length, 1);
  const h = results.find(x => x.status === 'fulfilled').value.handle, third = f.open().ledger;
  await assert.rejects(() => third.recordWorkerDispatch(h, '30'));
  await assert.rejects(() => third.recordWorkerDispatch({ ...h }, '30'));
  await assert.rejects(() => f.prepareWorker(third));
  assert.equal((await third.readWorkerHistory(f.job)).state, 'prepared');
});

test('missing and partial worker journal or anchors cannot be repaired or redispatched', async t => {
  for (const kind of ['all', 'seed', 'prepared', 'body', 'digest', 'marker', 'capture']) {
    const f = await workerHistoryFixture(t), { handle } = await f.prepareWorker();
    if (kind === 'all') f.store.db.exec('DELETE FROM original_management_worker_history');
    if (kind === 'seed') f.store.db.exec('DELETE FROM original_management_worker_history WHERE ordinal=0');
    if (kind === 'prepared') f.store.db.exec('DELETE FROM original_management_worker_history WHERE ordinal=1');
    if (kind === 'body') f.store.db.exec("UPDATE original_management_worker_history SET body='{}' WHERE ordinal=1");
    if (kind === 'digest') f.store.db.exec("UPDATE original_management_worker_history SET digest='bad' WHERE ordinal=1");
    if (kind === 'marker') f.store.db.exec("UPDATE original_management_attempts SET worker_history_digest=''");
    if (kind === 'capture') f.store.db.exec('DELETE FROM original_management_capture_history');
    const reopened = f.open().ledger;
    await assert.rejects(() => reopened.readWorkerHistory(f.job), kind);
    await assert.rejects(() => f.ledger.recordWorkerDispatch(handle, '30'), kind);
    await assert.rejects(() => f.prepareWorker(reopened), kind);
  }
});

test('worker history rechecks original staff, scope, policy, source and expiry before and after current verification', async t => {
  for (const stage of ['before', 'during']) for (const kind of ['staff', 'revoked', 'policy', 'app', 'source', 'scope', 'expiry']) {
    const f = await workerHistoryFixture(t), { handle } = await f.prepareWorker();
    const deny = () => {
      if (kind === 'staff') throw new Error('Directory unavailable');
      if (kind === 'revoked') f.ledger.recordQueuePendingRevocation(f.job);
      if (kind === 'policy') f.configuration.policy.policy_epoch++;
      if (kind === 'app') f.configuration.app_actor_id = '41';
      if (kind === 'source') f.configuration.policy.runner_source_sha = 'e'.repeat(40);
      if (kind === 'scope') f.policy.emailDomains[0].portfolio.actions = ['read'];
      if (kind === 'expiry' && !f.expiryApplied) { f.expiryApplied = true; t.mock.method(Date, 'now', () => f.context.binding.expires_at * 1000); }
    };
    if (stage === 'during' || kind === 'staff') f.state.duringCheck = deny; else deny();
    await assert.rejects(() => f.ledger.recordWorkerDispatch(handle, '30'), `${stage}/${kind}`);
    assert.equal(f.store.db.prepare('SELECT count(*) n FROM original_management_worker_history').get().n, 2);
    t.mock.restoreAll();
  }
});

test('late or failed worker observation consumes its handle and retains uncertain preparation', async t => {
  for (const kind of ['deadline', 'invalid-run', 'audit-failure']) {
    const f = await workerHistoryFixture(t), { handle } = await f.prepareWorker();
    if (kind === 'deadline') { const at = (await f.ledger.readWorkerHistory(f.job)).value.prepared.requested_at + 60000; t.mock.method(Date, 'now', () => at); }
    if (kind === 'audit-failure') t.mock.method(f.store, 'audit', () => { throw new Error('disk failure'); });
    await assert.rejects(() => f.ledger.recordWorkerDispatch(handle, kind === 'invalid-run' ? '0' : '30'));
    t.mock.restoreAll();
    await assert.rejects(() => f.ledger.recordWorkerDispatch(handle, '31'));
    assert.equal((await f.ledger.readWorkerHistory(f.job)).state, 'prepared');
  }
});
