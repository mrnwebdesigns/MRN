import test from 'node:test';
import assert from 'node:assert/strict';
import { generateKeyPairSync, randomUUID, verify } from 'node:crypto';
import { mkdtempSync, readFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { request as httpRequest } from 'node:http';
import { Store } from '../src/store.mjs';
import { digest } from '../src/contracts.mjs';
import { delegationCanonical, delegationDigest } from '../src/delegation.mjs';
import { AccessSigner, AccessQueryReader, ContentAccessVeto, accessReadbackTool, accessContract, accessVetoPath } from '../src/content-access.mjs';
import { ContentAccessLedger, requesterDigest } from '../src/content-access-store.mjs';
import { createHttpServer } from '../src/http.mjs';
import { compileContentSchema } from '../src/content-schema.mjs';

const vectors = JSON.parse(readFileSync(new URL('./fixtures/content-qualification-contract.json', import.meta.url)));
const vector = definition => structuredClone(vectors.cases.find(c => c.definition === definition).value);
const h = 'a'.repeat(64);
const decoded = envelope => JSON.parse(Buffer.from(envelope.payload_base64, 'base64'));
function fixture(t, file = ':memory:') {
  const state = { now: 1800000000000, mono: 0, calls: 0, deny: false, localResult: true };
  const keys = generateKeyPairSync('ed25519'), signer = new AccessSigner({ keyId: 'gateway-identity', privateKey: keys.privateKey, now: () => state.now });
  const store = new Store(file); t.after(() => { if (!state.closed) store.close(); });
  const ledger = new ContentAccessLedger(store, { connectionId: 'con_test', now: () => state.now });
  const actor = { subject: 'google-oauth2|123', googleSubject: '123', verifiedEmail: 'staff@mrnwebdesigns.com',
    identityProvider: 'google-oauth2', hostedDomain: 'mrnwebdesigns.com', googleConnection: 'con_test', expiresAt: state.now + 3600000,
    scopes: ['mrn:operations', 'mrn:operations:write'], token: 'must-not-be-stored' };
  const request = vector('request_input'), job = { ...vector('job_output'), expires_at: state.now / 1000 + 300,
    recipe_catalog_sha256: h, recipe_sha256: h };
  const expected = Object.fromEntries(['authorization_policy_sha256', 'recipe_catalog_sha256', 'recipe_sha256'].map(k => [k, job[k]]));
  const map = () => ledger.recordQueued(actor, request, job, expected);
  const query = () => ({ schema: 1, issuer: 'https://wpcontrol.mrndev.io', audience: 'https://operations.mrnwebdesigns.com',
    issued_at: state.now / 1000, expires_at: state.now / 1000 + 5, request_id: randomUUID(), nonce: h,
    authorization_policy_sha256: job.authorization_policy_sha256, qualification_job_id: job.qualification_job_id,
    site_id: job.site_id, site_url: job.site_url, target: job.target, action: 'pending-veto-query', requester_binding_sha256: requesterDigest(actor),
    phase: 'capture', check_id: randomUUID(), query_id: randomUUID(), provider_query_nonce: h });
  const reader = { async read(envelope) {
    state.calls++; state.readback = decoded(envelope);
    assert.equal(state.readback.query_id, state.query.query_id);
    state.onRead?.();
    return state.result || { stored: true, query: state.query, query_sha256: delegationDigest(state.query) };
  } };
  const veto = new ContentAccessVeto({ signer, reader, ledger, now: () => state.now, monotonic: () => state.mono,
    checkLocalAccess: () => { if (state.deny) throw new Error('local revocation'); return state.localResult; } });
  const call = async (q = query()) => {
    state.query = q; return veto.respond({ query_id: q.query_id, provider_query_nonce: q.provider_query_nonce });
  };
  return { state, signer, keys, store, ledger, actor, request, job, expected, map, query, call, veto };
}

test('candidate access contract is immutable and all closed definitions compile', () => {
  assert.equal(digest(readFileSync(new URL('../contracts/content-current-access-v1.json', import.meta.url))),
    '02fa1caca043f61a92a0e7cfe369db4f271dcebcaf2599b6a4422a3a4885d172');
  for (const name of Object.keys(accessContract.definitions)) compileContentSchema({ $ref: `#/definitions/${name}` }, accessContract.definitions);
});

test('authenticated queued map yields a purpose-separated exact signed veto without retaining a token', async t => {
  const f = fixture(t); f.map(); const q = f.query(), result = await f.call(q), claims = decoded(result.signed_veto);
  assert.deepEqual(Object.keys(result), ['signed_veto']); assert.equal(claims.state, 'clear'); assert.equal(claims.gateway_revision, 1);
  assert.equal(claims.query_sha256, delegationDigest(q)); assert.equal(claims.expires_at, q.expires_at);
  assert.equal(claims.requester_binding_sha256, requesterDigest(f.actor)); assert.equal(claims.qualification_job_id, f.job.qualification_job_id);
  const bytes = Buffer.from(result.signed_veto.payload_base64, 'base64'), signature = Buffer.from(result.signed_veto.signature_base64, 'base64');
  assert.equal(bytes.toString(), delegationCanonical(claims));
  assert.ok(verify(null, Buffer.concat([Buffer.from(accessContract.veto_purpose_prefix), bytes]), f.keys.publicKey, signature));
  assert.equal(verify(null, Buffer.concat([Buffer.from(accessContract.query_readback_purpose_prefix), bytes]), f.keys.publicKey, signature), false);
  const row = f.store.db.prepare('SELECT body FROM content_access_jobs').get();
  assert.equal(row.body.includes('must-not-be-stored'), false); assert.equal(row.body.includes('token'), false);
  assert.deepEqual(Object.keys(f.state.readback).sort(), ['action', 'audience', 'expires_at', 'issued_at', 'issuer', 'nonce', 'provider_query_nonce', 'query_id', 'request_id', 'schema'].sort());
});

test('unknown map, missing ledger, corrupt binding and untrusted pointer cannot manufacture clearance', async t => {
  const f = fixture(t);
  await assert.rejects(() => f.call(), { code: 'ACCESS_MAPPING' });
  f.map();
  for (const extra of [{ email: f.actor.verifiedEmail }, { url: 'https://attacker.example' }, { requester_binding_sha256: h }])
    await assert.rejects(() => f.veto.respond({ query_id: randomUUID(), provider_query_nonce: h, ...extra }), { code: 'ACCESS_INVALID' });
  assert.equal(f.state.calls, 1);
  f.store.db.exec('DELETE FROM content_access_principals');
  await assert.rejects(() => f.call(), { code: 'ACCESS_UNAVAILABLE' });
  f.store.db.exec("UPDATE content_access_jobs SET body='{}'");
  await assert.rejects(() => f.call(), { code: 'ACCESS_MAPPING' });
});

test('queue mappings reject outsiders, expired/read-only identities and mismatched results before retention', t => {
  const f = fixture(t);
  for (const mutation of [{ verifiedEmail: 'person@outsider.test' }, { identityProvider: 'password' }, { hostedDomain: 'other.test' },
    { subject: 'google-oauth2|456' }, { googleConnection: 'con_other' }, { expiresAt: f.state.now }, { scopes: ['mrn:operations'] }])
    assert.throws(() => f.ledger.recordQueued({ ...f.actor, ...mutation }, f.request, f.job, f.expected));
  for (const mutation of [{ site_id: 7 }, { target: { ...f.job.target, field: 'post_content' } }, { state: 'captured' },
    { recipe_sha256: 'b'.repeat(64) }, { authorization_policy_sha256: 'b'.repeat(64) }, { expires_at: 1 }])
    assert.throws(() => f.ledger.recordQueued(f.actor, f.request, { ...f.job, ...mutation }, f.expected));
  assert.equal(f.store.db.prepare('SELECT count(*) AS n FROM content_access_jobs').get().n, 0);
  f.map();
  assert.throws(() => f.ledger.recordQueued({ ...f.actor, verifiedEmail: 'other@mrnwebdesigns.com' }, f.request, f.job, f.expected));
  const repeated = f.ledger.recordQueued({ ...f.actor, expiresAt: f.actor.expiresAt + 100000 }, f.request, f.job, f.expected);
  assert.equal(repeated.actor.expiresAt, f.actor.expiresAt);
});

test('changed scope, identity, policy, query digest or extra readback data fails closed', async t => {
  const f = fixture(t); f.map();
  for (const mutation of [{ site_id: 9 }, { site_url: 'https://other.example.invalid' }, { target: { ...f.job.target, post_id: 9 } },
    { requester_binding_sha256: 'b'.repeat(64) }, { authorization_policy_sha256: 'b'.repeat(64) }])
    await assert.rejects(() => f.call({ ...f.query(), ...mutation }), { code: 'ACCESS_BINDING' });
  for (const result of [q => ({ stored: true, query: q, query_sha256: 'b'.repeat(64) }),
    q => ({ stored: true, query: q, query_sha256: delegationDigest(q), email: f.actor.verifiedEmail }),
    q => ({ stored: false, query: q, query_sha256: delegationDigest(q) })]) {
    const q = f.query(); f.state.result = result(q); await assert.rejects(() => f.call(q));
  }
  assert.equal(f.store.db.prepare('SELECT count(*) AS n FROM content_access_queries').get().n, 0);
});

test('fresh readback may outlive query deadline but signed answer cannot; expired/slow reads are denied', async t => {
  const f = fixture(t); f.map(); const q = { ...f.query(), issued_at: f.state.now / 1000 - 2, expires_at: f.state.now / 1000 + 3 };
  const response = await f.call(q);
  assert.ok(f.state.readback.expires_at > q.expires_at); assert.equal(decoded(response.signed_veto).expires_at, q.expires_at);
  await assert.rejects(() => f.call({ ...f.query(), expires_at: f.state.now / 1000 }), { code: 'ACCESS_EXPIRED' });
  await assert.rejects(() => f.call({ ...f.query(), issued_at: f.state.now / 1000 + 1 }), { code: 'ACCESS_EXPIRED' });
  f.state.onRead = () => { f.state.now += 5000; };
  await assert.rejects(() => f.call(), { code: 'ACCESS_EXPIRED' });
  f.state.onRead = () => { f.state.mono += 2500; };
  await assert.rejects(() => f.call(), { code: 'ACCESS_EXPIRED' });
  assert.equal(f.store.db.prepare('SELECT count(*) AS n FROM content_access_queries').get().n, 1);
});

test('pending revocation survives restart and overrides every job for the original principal', async t => {
  const dir = mkdtempSync(join(tmpdir(), 'mrn-access-')); t.after(() => rmSync(dir, { recursive: true, force: true }));
  const file = join(dir, 'test.sqlite'), f = fixture(t, file); f.map();
  const another = { ...f.job, qualification_job_id: randomUUID() };
  f.ledger.recordQueued(f.actor, f.request, another, f.expected);
  const pending = f.ledger.recordPendingRevocation(f.job.qualification_job_id);
  assert.deepEqual(pending, { state: 'pending-revocation', revision: 2, committed_denial: false });
  assert.deepEqual(f.ledger.recordPendingRevocation(f.job.qualification_job_id), pending);
  assert.throws(() => f.map(), { code: 'ACCESS_DENIED' });
  const consumed = f.query(); await f.call(consumed);
  f.store.close(); f.state.closed = true;
  const reopened = new Store(file); t.after(() => reopened.close());
  f.veto.ledger = new ContentAccessLedger(reopened, { connectionId: 'con_test', now: () => f.state.now });
  await assert.rejects(() => f.call(consumed), { code: 'ACCESS_REPLAY' });
  for (const job of [f.job, another]) {
    const answer = decoded((await f.call({ ...f.query(), qualification_job_id: job.qualification_job_id })).signed_veto);
    assert.equal(answer.state, 'pending-revocation'); assert.equal(answer.gateway_revision, 2);
  }
});

test('local revocation, asynchronous policy lookup and expired original login cannot return clear', async t => {
  const f = fixture(t); f.map(); f.state.deny = true;
  assert.equal(decoded((await f.call()).signed_veto).state, 'denied');
  f.state.deny = false; f.state.localResult = Promise.resolve(true);
  assert.equal(decoded((await f.call()).signed_veto).state, 'denied');
  f.state.localResult = undefined;
  assert.equal(decoded((await f.call()).signed_veto).state, 'denied');
  f.state.localResult = true;
  const row = f.store.db.prepare('SELECT body FROM content_access_jobs').get(), body = JSON.parse(row.body);
  body.actor.expiresAt = f.state.now - 1;
  const bytes = JSON.stringify(body);
  f.store.db.prepare('UPDATE content_access_jobs SET body=?,digest=?').run(bytes, digest(bytes));
  assert.equal(decoded((await f.call()).signed_veto).state, 'denied');
});

test('same query/check cannot answer twice, including after restart or lost response', async t => {
  const f = fixture(t); f.map(); const q = f.query(); await f.call(q);
  await assert.rejects(() => f.call(q), { code: 'ACCESS_REPLAY' });
  await assert.rejects(() => f.call({ ...q, query_id: randomUUID() }), { code: 'ACCESS_REPLAY' });
  await f.call(); assert.equal(f.state.calls, 4);
  // Signing before a failed durable commit does not return an answer.
  f.store.db.exec("CREATE TRIGGER fail_access_insert BEFORE INSERT ON content_access_queries BEGIN SELECT RAISE(ABORT, 'test failure'); END");
  await assert.rejects(() => f.call());
  assert.equal(f.store.db.prepare('SELECT count(*) AS n FROM content_access_queries').get().n, 2);
});

test('pending change during independent readback is checked before signing; signing expiry suppresses the answer', async t => {
  const f = fixture(t); f.map(); f.state.onRead = () => f.ledger.recordPendingRevocation(f.job.qualification_job_id);
  assert.equal(decoded((await f.call()).signed_veto).state, 'pending-revocation');
  const original = f.signer.envelope.bind(f.signer);
  f.signer.envelope = (claims, prefix) => {
    const result = original(claims, prefix);
    if (prefix === accessContract.veto_purpose_prefix) f.state.now += 5000;
    return result;
  };
  await assert.rejects(() => f.call(), { code: 'ACCESS_EXPIRED' });
  assert.equal(f.store.db.prepare('SELECT count(*) AS n FROM content_access_queries').get().n, 1);
});

test('bounded per-job query budget fails closed without deleting retained replay evidence', async t => {
  const f = fixture(t); f.map();
  const stmt = f.store.db.prepare('INSERT INTO content_access_queries VALUES(?,?,?,?,?,?)');
  f.store.transaction(() => { for (let i = 0; i < 512; i++) stmt.run(randomUUID(), f.job.qualification_job_id, randomUUID(), h, 'clear', 1); });
  await assert.rejects(() => f.call(), { code: 'ACCESS_REPLAY' });
  assert.equal(f.store.db.prepare('SELECT count(*) AS n FROM content_access_queries').get().n, 512);
});

test('private MainWP reader requires expected Dashboard and POST metadata; one attempt with no credential fallback', async () => {
  let calls = 0;
  const status = { connected: true, dashboardHost: 'wpcontrol.mrndev.io', abilitiesCount: 5 };
  const client = { async readResource() { return { contents: [{ uri: 'mainwp://status', text: JSON.stringify(status) }] }; },
    async listTools() { return { tools: [{ name: accessReadbackTool, annotations: { readOnlyHint: false, destructiveHint: false } }] }; },
    async callTool(request, _schema, options) { calls++; assert.equal(request.name, accessReadbackTool); assert.equal(options.timeout, 2000); throw new Error('timeout'); } };
  const reader = new AccessQueryReader(client);
  await assert.rejects(() => reader.read({}), { code: 'ACCESS_UNAVAILABLE' });
  await reader.initialize(); await assert.rejects(() => reader.read({})); assert.equal(calls, 1);
  status.dashboardHost = 'wrong.example'; await assert.rejects(() => new AccessQueryReader(client).initialize());
  status.dashboardHost = 'wpcontrol.mrndev.io';
  client.listTools = async () => ({ tools: [{ name: accessReadbackTool, annotations: { readOnlyHint: true, destructiveHint: false } }] });
  await assert.rejects(() => new AccessQueryReader(client).initialize(), { code: 'ACCESS_TRANSPORT' });
});

test('private HTTP callback is bounded, canonical, separate from OAuth and absent unless explicitly installed', async t => {
  const f = fixture(t); f.map(); let authCalls = 0;
  const server = createHttpServer({ service: {}, authenticate: () => { authCalls++; }, publicUrl: 'https://operations.mrnwebdesigns.com/mcp',
    issuer: 'https://identity.example.invalid', accessVeto: f.veto });
  await new Promise(resolve => server.listen(0, '127.0.0.1', resolve)); t.after(() => new Promise(resolve => server.close(resolve)));
  const call = (body, extra = {}, target = server) => new Promise((resolve, reject) => {
    const req = httpRequest({ host: '127.0.0.1', port: target.address().port, path: accessVetoPath, method: 'POST',
      headers: { host: 'operations.mrnwebdesigns.com', 'content-type': 'application/json', ...extra } }, res => {
      const chunks = []; res.on('data', c => chunks.push(c)); res.on('end', () => resolve({ status: res.statusCode, body: Buffer.concat(chunks).toString() }));
    }); req.on('error', reject); req.end(body);
  });
  const q = f.query(); f.state.query = q;
  const pointer = delegationCanonical({ query_id: q.query_id, provider_query_nonce: q.provider_query_nonce });
  const ok = await call(pointer); assert.equal(ok.status, 200);
  assert.equal(ok.body, delegationCanonical(JSON.parse(ok.body))); assert.equal(decoded(JSON.parse(ok.body).signed_veto).state, 'clear');
  for (const body of [pointer + ' ', '{"query_id":"x","query_id":"y"}', 'x'.repeat(513), JSON.stringify({ email: f.actor.verifiedEmail })])
    assert.equal((await call(body)).status, 400);
  assert.equal((await call(pointer, { authorization: 'Bearer ignored' })).status, 400); assert.equal(authCalls, 0);
  assert.throws(() => createHttpServer({ publicUrl: 'https://other.example/mcp', accessVeto: f.veto }), { code: 'ACCESS_TRANSPORT' });
  const disabled = createHttpServer({ publicUrl: 'https://operations.mrnwebdesigns.com/mcp' });
  await new Promise(resolve => disabled.listen(0, '127.0.0.1', resolve)); t.after(() => new Promise(resolve => disabled.close(resolve)));
  const absent = await call(pointer, {}, disabled);
  assert.equal(absent.status, 404);
});
