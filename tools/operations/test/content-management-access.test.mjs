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
import { accessContract, accessVetoPath, managementAccessVetoPath, accessReadbackTool } from '../src/content-access.mjs';
import { ContentAccessLedger } from '../src/content-access-store.mjs';
import { managementCaptureContract } from '../src/content-management-capture.mjs';
import { ManagementAccessSigner, ManagementAccessQueryReader, ManagementAccessVeto, managementAccessReadbackTool } from '../src/content-management-access.mjs';
import { ManagementAccessLedger } from '../src/content-management-access-store.mjs';
import { createManagementAccessRuntime } from '../src/content-access-runtime.mjs';
import { createHttpServer } from '../src/http.mjs';
import { accessRegistry } from './fixtures/access-registry.mjs';
import { managementAccessFixture } from './fixtures/management-access.mjs';

const h = 'a'.repeat(64), decoded = envelope => JSON.parse(Buffer.from(envelope.payload_base64, 'base64'));
function fixture(t, file = ':memory:') {
  const state = { now: 1800000000000, mono: 0, calls: [], closes: 0 };
  const keys = generateKeyPairSync('ed25519');
  const identity = { keyId: 'gateway-identity', privateKey: keys.privateKey, now: () => state.now };
  const signer = new ManagementAccessSigner(identity);
  const store = new Store(file); t.after(() => { if (!state.closed) store.close(); });
  const actor = { subject: 'google-oauth2|123', googleSubject: '123', verifiedEmail: 'staff@mrnwebdesigns.com',
    identityProvider: 'google-oauth2', hostedDomain: 'mrnwebdesigns.com', googleConnection: 'con_fixture',
    expiresAt: state.now + 3600000, scopes: ['mrn:operations', 'mrn:operations:write'], token: 'never-retain-this-token' };
  const { context, request, expected } = managementAccessFixture(actor, state.now);
  const { registry, registryData, policy } = accessRegistry(request.site_url);
  const ledger = new ManagementAccessLedger(store, { connectionId: 'con_fixture', registry, now: () => state.now });
  const site = ledger.assertQueueAllowed(actor, request);
  const tool = { name: managementAccessReadbackTool, annotations: { readOnlyHint: false, destructiveHint: false } };
  const status = { connected: true, dashboardHost: 'wpcontrol.mrndev.io', abilitiesCount: 115 };
  const client = {
    async readResource(input) { assert.equal(input.uri, 'mainwp://status'); return { contents: [{ text: JSON.stringify(status) }] }; },
    async listTools() { return { tools: [tool] }; },
    async callTool(input) {
      state.calls.push(input.name); assert.equal(input.name, managementAccessReadbackTool);
      const envelope = input.arguments.signed_request, bytes = Buffer.from(envelope.payload_base64, 'base64');
      assert.ok(verify(null, Buffer.concat([Buffer.from(managementCaptureContract.query_readback_purpose_prefix), bytes]), keys.publicKey, Buffer.from(envelope.signature_base64, 'base64')));
      const claims = decoded(envelope); assert.equal(claims.query_id, state.query.query_id);
      await state.duringRead?.();
      const value = state.output ?? { stored: true, query: state.query, query_sha256: delegationDigest(state.query) };
      return { content: [{ type: 'text', text: JSON.stringify(value) }] };
    },
    async close() { state.closes++; },
  };
  const reader = new ManagementAccessQueryReader(client);
  const veto = new ManagementAccessVeto({ signer, reader, ledger, now: () => state.now, monotonic: () => state.mono });
  const query = () => ({ schema: 1, issuer: 'https://wpcontrol.mrndev.io', audience: 'https://operations.mrnwebdesigns.com',
    issued_at: state.now / 1000, expires_at: state.now / 1000 + 5, request_id: randomUUID(), nonce: h,
    ...Object.fromEntries(['authorization_policy_sha256', 'qualification_job_id', 'site_id', 'site_url', 'target', 'requester_binding_sha256'].map(k => [k, context.binding[k]])),
    action: 'pending-veto-query', phase: 'capture', check_id: randomUUID(), query_id: randomUUID(), provider_query_nonce: h,
    management_context_sha256: context.binding_sha256 });
  const pointer = q => ({ query_id: q.query_id, provider_query_nonce: q.provider_query_nonce });
  const call = async (q = query()) => { state.query = q; if (!reader.ready) await reader.initialize(); return veto.respond(pointer(q)); };
  const map = () => ledger.recordQueued(actor, request, context, expected, site);
  return { state, keys, identity, signer, store, actor, context, request, expected, registry, registryData, policy,
    ledger, site, tool, status, client, reader, veto, query, pointer, call, map };
}

test('management callback signs only its exact context and distinct purpose, without retaining a token or content', async t => {
  const f = fixture(t); f.map();
  const q = f.query(), answer = (await f.call(q)).signed_veto, claims = decoded(answer);
  assert.equal(claims.state, 'clear'); assert.equal(claims.management_context_sha256, f.context.binding_sha256);
  assert.equal(claims.requester_binding_sha256, f.context.binding.requester_binding_sha256);
  const bytes = Buffer.from(answer.payload_base64, 'base64'), signature = Buffer.from(answer.signature_base64, 'base64');
  assert.equal(bytes.toString(), delegationCanonical(claims));
  assert.ok(verify(null, Buffer.concat([Buffer.from(managementCaptureContract.veto_purpose_prefix), bytes]), f.keys.publicKey, signature));
  assert.equal(verify(null, Buffer.concat([Buffer.from(accessContract.veto_purpose_prefix), bytes]), f.keys.publicKey, signature), false);
  const body = f.store.db.prepare('SELECT body FROM management_access_jobs').get().body;
  assert.equal(body.includes('never-retain-this-token'), false); assert.equal(body.includes('post_content'), false);
  assert.equal(f.store.db.prepare('SELECT event FROM management_access_audit ORDER BY seq DESC').get().event, 'veto-answered');
  await assert.rejects(() => f.call(q), { code: 'ACCESS_REPLAY' });
  await assert.rejects(() => f.call({ ...f.query(), check_id: q.check_id }), { code: 'ACCESS_REPLAY' });
});

test('management requires its own canonical POST reader and never falls back to the single-field route', async t => {
  for (const failure of ['missing', 'unsafe', 'duplicate', 'wrong-dashboard', 'disconnected', 'read-error']) {
    const f = fixture(t); f.map();
    if (failure === 'missing') f.client.listTools = async () => ({ tools: [{ ...f.tool, name: accessReadbackTool }] });
    if (failure === 'unsafe') f.tool.annotations.readOnlyHint = true;
    if (failure === 'duplicate') f.client.listTools = async () => ({ tools: [f.tool, f.tool] });
    if (failure === 'wrong-dashboard') f.status.dashboardHost = 'other.example';
    if (failure === 'disconnected') f.status.connected = false;
    if (failure === 'read-error') f.state.duringRead = () => { throw new Error('read denied'); };
    await assert.rejects(() => f.call());
    assert.ok(f.state.calls.length <= 1); assert.ok(f.state.calls.every(name => name === managementAccessReadbackTool));
    assert.equal(f.store.db.prepare('SELECT count(*) AS n FROM management_access_queries').get().n, 0);
  }
});

test('caller identity, absent maps, other contexts and unrequested changes cannot create clearance', async t => {
  const f = fixture(t); await assert.rejects(() => f.call(), { code: 'ACCESS_MAPPING' }); f.map();
  for (const extra of [{ email: f.actor.verifiedEmail }, { url: f.request.site_url }, { management_context_sha256: f.context.binding_sha256 }])
    await assert.rejects(() => f.veto.respond({ ...f.pointer(f.query()), ...extra }), { code: 'ACCESS_INVALID' });
  for (const mutation of [{ management_context_sha256: h }, { site_id: f.request.site_id + 1 },
    { requester_binding_sha256: h }, { authorization_policy_sha256: h }, { target: { ...f.context.binding.target, post_id: f.context.binding.target.post_id + 1 } }])
    await assert.rejects(() => f.call({ ...f.query(), ...mutation }), { code: 'ACCESS_BINDING' });
  const old = f.query(); delete old.management_context_sha256;
  await assert.rejects(() => f.call(old), { code: 'ACCESS_INVALID' });
  const q = f.query(); f.state.output = { stored: true, query: q, query_sha256: h };
  await assert.rejects(() => f.call(q), { code: 'ACCESS_BINDING' });
  assert.equal(f.store.db.prepare('SELECT count(*) AS n FROM management_access_queries').get().n, 0);
});

test('queue mapping independently checks source pins, preview, original identity and immutable lifetime', t => {
  const f = fixture(t);
  for (const change of [{ verifiedEmail: 'outside@example.com' }, { googleConnection: 'con_other' }, { subject: 'google-oauth2|999' },
    { expiresAt: f.state.now }, { scopes: ['mrn:operations'] }, { identityProvider: 'password' }])
    assert.throws(() => f.ledger.recordQueued({ ...f.actor, ...change }, f.request, f.context, f.expected, f.site));
  for (const key of Object.keys(f.expected.pins)) {
    const expected = structuredClone(f.expected);
    expected.pins[key] = key === 'job_intent_ttl_seconds' ? 121 : 'e'.repeat(key === 'provider_source_sha' ? 40 : 64);
    assert.throws(() => f.ledger.recordQueued(f.actor, f.request, f.context, expected, f.site));
  }
  for (const key of ['candidate_sha256', 'before_sha256', 'request_sha256', 'acf_schema_sha256']) {
    const context = structuredClone(f.context); context.binding[key] = h; context.binding_sha256 = delegationDigest(context.binding);
    assert.throws(() => f.ledger.recordQueued(f.actor, f.request, context, f.expected, f.site));
  }
  f.map();
  const repeated = f.ledger.recordQueued({ ...f.actor, expiresAt: f.actor.expiresAt + 100000 }, f.request, f.context, f.expected, f.site);
  assert.equal(repeated.actor.expiresAt, f.actor.expiresAt);
  const context = structuredClone(f.context); context.binding.qualification_job_id = randomUUID(); context.binding_sha256 = delegationDigest(context.binding);
  assert.throws(() => f.ledger.recordQueued(f.actor, f.request, context, f.expected, f.site), { code: 'ACCESS_MAPPING' });
  assert.equal(f.store.db.prepare('SELECT count(*) AS n FROM management_access_jobs').get().n, 1);
});

test('original OAuth expiry bounds clearance and a later login cannot renew it', async t => {
  const f = fixture(t); f.actor.expiresAt = f.state.now + 2000; f.map();
  assert.equal(decoded((await f.call()).signed_veto).expires_at, f.actor.expiresAt / 1000);
  f.state.now += 2000;
  assert.equal(decoded((await f.call()).signed_veto).state, 'denied');
  assert.throws(() => f.ledger.recordQueued({ ...f.actor, expiresAt: f.state.now + 60000 }, f.request, f.context, f.expected, f.site), { code: 'AUTH_REQUIRED' });
});

test('OAuth expiry during ledger commit withholds an already signed clearance', async t => {
  const f = fixture(t); f.actor.expiresAt = f.state.now + 2000; f.map();
  const audit = f.ledger.audit.bind(f.ledger);
  f.ledger.audit = (...args) => { audit(...args); f.state.now += 2000; };
  await assert.rejects(() => f.call(), { code: 'ACCESS_EXPIRED' });
  assert.equal(f.store.db.prepare('SELECT count(*) AS n FROM management_access_queries').get().n, 1);
});

test('pending revocation survives restart and the two content protocols share its monotonic history', async t => {
  for (const fromManagement of [true, false]) {
    const dir = mkdtempSync(join(tmpdir(), 'mrn-management-access-')); t.after(() => rmSync(dir, { recursive: true, force: true }));
    const file = join(dir, 'access.sqlite'), f = fixture(t, file); f.map();
    const old = new ContentAccessLedger(f.store, { connectionId: 'con_fixture', registry: f.registry, now: () => f.state.now });
    const vectors = JSON.parse(readFileSync(new URL('./fixtures/content-qualification-contract.json', import.meta.url)));
    const get = name => structuredClone(vectors.cases.find(c => c.definition === name).value);
    const request = { ...get('request_input'), site_id: f.request.site_id, site_url: f.request.site_url };
    const job = { ...get('job_output'), site_id: request.site_id, site_url: request.site_url, expires_at: f.state.now / 1000 + 300, recipe_catalog_sha256: h, recipe_sha256: null };
    old.recordQueued(f.actor, request, job, { authorization_policy_sha256: job.authorization_policy_sha256, recipe_catalog_sha256: h, recipe_sha256: h }, old.assertQueueAllowed(f.actor, request));
    const pending = fromManagement ? f.ledger.recordPendingRevocation(f.context.binding.qualification_job_id) : old.recordPendingRevocation(job.qualification_job_id);
    assert.equal(pending.revision, 2); assert.equal(pending.committed_denial, false);
    assert.throws(() => old.assertQueueAllowed(f.actor, request), { code: 'ACCESS_DENIED' });
    f.store.close(); f.state.closed = true;
    const reopened = new Store(file); t.after(() => reopened.close());
    f.veto.ledger = new ManagementAccessLedger(reopened, { connectionId: 'con_fixture', registry: f.registry, now: () => f.state.now });
    const result = decoded((await f.call()).signed_veto);
    assert.equal(result.state, 'pending-revocation'); assert.equal(result.gateway_revision, 2);
    assert.throws(() => f.veto.ledger.assertQueueAllowed(f.actor, f.request), { code: 'ACCESS_DENIED' });
  }
});

test('permission changes during readback, delayed answers and corrupt state fail closed without query reuse', async t => {
  const f = fixture(t); f.map();
  f.state.duringRead = () => { f.policy.emailDomains[0].portfolio.actions = ['read']; };
  assert.equal(decoded((await f.call()).signed_veto).state, 'denied');
  f.policy.emailDomains[0].portfolio.actions = ['read', 'content'];
  f.state.duringRead = () => { f.state.mono += 2500; };
  await assert.rejects(() => f.call(), { code: 'ACCESS_EXPIRED' });
  f.state.duringRead = undefined;
  f.store.db.exec("UPDATE management_access_jobs SET body='{}'");
  await assert.rejects(() => f.call(), { code: 'ACCESS_MAPPING' });
});

test('signing or durable consumption failure returns no clearance and rolls back query consumption', async t => {
  const f = fixture(t); f.map(); await f.reader.initialize();
  const original = f.signer.veto.bind(f.signer), q = f.query();
  f.signer.veto = () => { throw new Error('signer unavailable'); };
  await assert.rejects(() => f.call(q));
  assert.equal(f.store.db.prepare('SELECT count(*) AS n FROM management_access_queries').get().n, 0);
  f.signer.veto = original;
  f.store.db.exec("CREATE TRIGGER reject_management_query BEFORE INSERT ON management_access_queries BEGIN SELECT RAISE(ABORT,'fixture storage failure'); END");
  await assert.rejects(() => f.call(q));
  assert.equal(f.store.db.prepare('SELECT count(*) AS n FROM management_access_queries').get().n, 0);
  f.store.db.exec('DROP TRIGGER reject_management_query');
  assert.equal(decoded((await f.call(q)).signed_veto).state, 'clear');
});

test('optional management runtime validates its separate reader and closes without reconnecting', async t => {
  const f = fixture(t);
  const options = { enabled: true, store: f.store, registry: f.registry, identity: f.identity,
    connectionId: 'con_fixture', connect: async () => f.client };
  assert.equal(await createManagementAccessRuntime(), null);
  const runtime = await createManagementAccessRuntime(options);
  const first = runtime.close(); assert.equal(runtime.close(), first); await first;
  await assert.rejects(() => runtime.veto.respond(f.pointer(f.query())), { code: 'ACCESS_UNAVAILABLE' });
  assert.equal(f.state.closes, 1);
  f.client.listTools = async () => ({ tools: [{ ...f.tool, name: accessReadbackTool }] });
  await assert.rejects(() => createManagementAccessRuntime(options), { code: 'ACCESS_TRANSPORT' });
  assert.equal(f.state.closes, 2);
});

test('actual HTTP management path uses the authenticated reader and immutable ledger only', async t => {
  const f = fixture(t); f.map(); await f.reader.initialize();
  const server = createHttpServer({ publicUrl: 'https://operations.mrnwebdesigns.com/mcp', managementAccessVeto: f.veto });
  await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
  t.after(() => new Promise(resolve => server.close(resolve)));
  const q = f.query(); f.state.query = q;
  const call = path => new Promise((resolve, reject) => {
    const req = httpRequest({ host: '127.0.0.1', port: server.address().port, path, method: 'POST', agent: false,
      headers: { host: 'operations.mrnwebdesigns.com', 'content-type': 'application/json' } }, res => {
      const chunks = []; res.on('data', chunk => chunks.push(chunk));
      res.on('end', () => resolve({ status: res.statusCode, json: () => JSON.parse(Buffer.concat(chunks).toString()) }));
    });
    req.on('error', reject); req.end(delegationCanonical(f.pointer(q)));
  });
  const response = await call(managementAccessVetoPath); assert.equal(response.status, 200);
  assert.equal(decoded((await response.json()).signed_veto).state, 'clear');
  assert.equal((await call(accessVetoPath)).status, 404);
  assert.equal(f.state.calls.length, 1);
  const row = f.store.db.prepare('SELECT body,digest FROM management_access_jobs').get(); assert.equal(digest(row.body), row.digest);
});
