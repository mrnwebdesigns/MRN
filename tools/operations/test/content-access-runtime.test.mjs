import test from 'node:test';
import assert from 'node:assert/strict';
import { generateKeyPairSync, randomUUID } from 'node:crypto';
import { readFileSync } from 'node:fs';
import { Store } from '../src/store.mjs';
import { createContentAccessRuntime } from '../src/content-access-runtime.mjs';
import { accessReadbackTool } from '../src/content-access.mjs';
import { requesterDigest } from '../src/content-access-store.mjs';
import { delegationDigest } from '../src/delegation.mjs';
import { accessRegistry } from './fixtures/access-registry.mjs';

const vectors = JSON.parse(readFileSync(new URL('./fixtures/content-qualification-contract.json', import.meta.url)));
const vector = definition => structuredClone(vectors.cases.find(c => c.definition === definition).value);
const h = 'a'.repeat(64);
const decoded = envelope => JSON.parse(Buffer.from(envelope.payload_base64, 'base64'));
function fixture(t) {
  const state = { connects: 0, closes: 0, reads: 0, calls: 0 };
  const store = new Store(':memory:'); t.after(() => store.close());
  const request = vector('request_input'), { registry, policy } = accessRegistry(request.site_url);
  const identity = { keyId: 'fixture', privateKey: generateKeyPairSync('ed25519').privateKey };
  const status = { connected: true, dashboardHost: 'wpcontrol.mrndev.io', abilitiesCount: 1 };
  const tool = { name: accessReadbackTool, annotations: { readOnlyHint: false, destructiveHint: false } };
  const client = {
    async readResource(input) {
      state.reads++; assert.deepEqual(input, { uri: 'mainwp://status' });
      return { contents: [{ uri: input.uri, text: JSON.stringify(status) }] };
    },
    async listTools() { return { tools: [tool] }; },
    async callTool(input) {
      state.calls++; assert.equal(input.name, accessReadbackTool);
      const claims = decoded(input.arguments.signed_request);
      assert.equal(claims.query_id, state.query.query_id);
      await state.duringRead?.();
      return { content: [{ type: 'text', text: JSON.stringify({ stored: true, query: state.query, query_sha256: delegationDigest(state.query) }) }] };
    },
    async close() { state.closes++; },
  };
  const options = { enabled: true, store, registry, identity, connectionId: 'con_fixture',
    connect: async () => { state.connects++; return client; } };
  const tables = () => store.db.prepare("SELECT name FROM sqlite_master WHERE name LIKE 'content_access_%'").all();
  const mappedQuery = runtime => {
    const now = Date.now(), actor = { subject: 'google-oauth2|123', googleSubject: '123', verifiedEmail: 'staff@mrnwebdesigns.com',
      identityProvider: 'google-oauth2', hostedDomain: 'mrnwebdesigns.com', googleConnection: 'con_fixture', expiresAt: now + 3600000,
      scopes: ['mrn:operations', 'mrn:operations:write'] };
    const job = { ...vector('job_output'), expires_at: Math.floor(now / 1000) + 300, recipe_catalog_sha256: h, recipe_sha256: null };
    runtime.ledger.recordQueued(actor, request, job, { authorization_policy_sha256: job.authorization_policy_sha256,
      recipe_catalog_sha256: h, recipe_sha256: h }, runtime.ledger.assertQueueAllowed(actor, request));
    state.query = { schema: 1, issuer: 'https://wpcontrol.mrndev.io', audience: 'https://operations.mrnwebdesigns.com',
      issued_at: Math.floor(now / 1000), expires_at: Math.floor(now / 1000) + 5, request_id: randomUUID(), nonce: h,
      authorization_policy_sha256: job.authorization_policy_sha256, qualification_job_id: job.qualification_job_id,
      site_id: job.site_id, site_url: job.site_url, target: job.target, action: 'pending-veto-query', requester_binding_sha256: requesterDigest(actor),
      phase: 'capture', check_id: randomUUID(), query_id: randomUUID(), provider_query_nonce: h };
    return { query_id: state.query.query_id, provider_query_nonce: h };
  };
  return { state, store, policy, status, tool, client, options, tables, mappedQuery };
}

test('disabled private control opens neither a connection nor ledger state', async t => {
  const f = fixture(t);
  assert.equal(await createContentAccessRuntime({ ...f.options, enabled: false }), null);
  assert.equal(await createContentAccessRuntime({}), null);
  assert.equal(f.state.connects, 0); assert.deepEqual(f.tables(), []);
  await assert.rejects(() => createContentAccessRuntime({ ...f.options, enabled: 'true' }), { code: 'ACCESS_CONFIG' });
});

test('missing existing signing authority refuses before connecting or creating ledger state', async t => {
  const f = fixture(t);
  for (const identity of [null, {}, { keyId: 'fixture', privateKey: {} }])
    await assert.rejects(() => createContentAccessRuntime({ ...f.options, identity }), { code: 'ACCESS_KEY' });
  assert.equal(f.state.connects, 0); assert.deepEqual(f.tables(), []);
});

for (const failure of ['wrong-dashboard', 'disconnected', 'missing-ability', 'unsafe-ability', 'directory-error']) {
  test(`private control ${failure} closes the connection without installing a ledger or retrying`, async t => {
    const f = fixture(t);
    if (failure === 'wrong-dashboard') f.status.dashboardHost = 'other.example.invalid';
    if (failure === 'disconnected') f.status.connected = false;
    if (failure === 'missing-ability') f.client.listTools = async () => ({ tools: [] });
    if (failure === 'unsafe-ability') f.tool.annotations.readOnlyHint = true;
    if (failure === 'directory-error') f.client.listTools = async () => { throw new Error('unavailable'); };
    await assert.rejects(() => createContentAccessRuntime(f.options));
    assert.equal(f.state.connects, 1); assert.equal(f.state.closes, 1); assert.equal(f.state.calls, 0);
    assert.deepEqual(f.tables(), []);
  });
}

test('ledger setup failure closes validated transport and preserves the original failure if cleanup also fails', async t => {
  const f = fixture(t);
  f.client.close = async () => { f.state.closes++; throw new Error('close failed'); };
  await assert.rejects(() => createContentAccessRuntime({ ...f.options, registry: null }), { code: 'ACCESS_MAPPING' });
  assert.equal(f.state.connects, 1); assert.equal(f.state.reads, 1); assert.equal(f.state.closes, 1);
  assert.deepEqual(f.tables(), []);
});

test('validated service lifecycle answers original mapped identity then closes once without reconnecting', async t => {
  const f = fixture(t), runtime = await createContentAccessRuntime(f.options);
  const pointer = f.mappedQuery(runtime);
  const result = await runtime.veto.respond(pointer);
  assert.equal(decoded(result.signed_veto).state, 'clear');
  assert.equal(decoded(result.signed_veto).requester_binding_sha256, f.state.query.requester_binding_sha256);
  const first = runtime.close(); assert.equal(runtime.close(), first); await first;
  await assert.rejects(() => runtime.veto.respond(pointer), { code: 'ACCESS_UNAVAILABLE' });
  assert.equal(f.state.connects, 1); assert.equal(f.state.calls, 1); assert.equal(f.state.closes, 1);
});

test('closing during authenticated readback withholds any in-flight signed clearance', async t => {
  const f = fixture(t), runtime = await createContentAccessRuntime(f.options), pointer = f.mappedQuery(runtime);
  let entered, release;
  const reading = new Promise(resolve => { entered = resolve; });
  const held = new Promise(resolve => { release = resolve; });
  f.state.duringRead = async () => { entered(); await held; };
  const answer = runtime.veto.respond(pointer);
  const rejected = assert.rejects(answer, { code: 'ACCESS_UNAVAILABLE' });
  await reading; await runtime.close(); release(); await rejected;
  assert.equal(f.state.connects, 1); assert.equal(f.state.calls, 1); assert.equal(f.state.closes, 1);
});
