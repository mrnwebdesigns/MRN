import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { generateKeyPairSync, randomUUID, sign, verify } from 'node:crypto';
import { OriginalAuthorizationTransport, createOriginalAuthorizationTransport } from '../src/original-authorization-transport.mjs';
import { existingManagementHashes } from '../src/management-existing-contracts.mjs';
import { delegationCanonical } from '../src/delegation.mjs';
import { digest } from '../src/contracts.mjs';
import { compileManagementEvidenceSchema } from '../src/content-management-schema.mjs';

const wireBytes = readFileSync(new URL('../contracts/content-management-existing-dispatch-v2-proposal.json', import.meta.url));
const wire = JSON.parse(wireBytes), tools = Object.fromEntries(Object.entries(wire.machine_abilities)
  .map(([kind, v]) => [kind, v.name.replace('/', '__').replaceAll('-', '_')]));
function fixture() {
  const keys = generateKeyPairSync('ed25519');
  const state = { now: 1800000001000, clock: 0, calls: [], closes: 0,
    connection: { connected: true, dashboardHost: 'wpcontrol.mrndev.io', abilitiesCount: 115 },
    tools: Object.entries(wire.machine_abilities).map(([kind, v]) => ({ name: tools[kind], annotations: { ...v.annotations } })),
    policy: { provider_source_sha: 'a'.repeat(40), authorization_policy_sha256: 'b'.repeat(64) },
    readiness: { schema: 2, dashboard_origin: 'https://wpcontrol.mrndev.io', qualification_protocol: 'content-management-existing-v2',
      lifetime_domain: 'original-resource-authorization-v2', protocol_contracts_sha256: existingManagementHashes,
      original_authorization_contract_sha256: '3a77cf8ad2131fd1e2255e74d2a3dcd9ca1d18051e5acc1d51bdb6b7d6fa73c2',
      dispatch_contract_sha256: digest(wireBytes), provider_source_sha: 'a'.repeat(40), authorization_policy_sha256: 'b'.repeat(64),
      observed_at: 1800000001, expires_at: 1800000031, binding_readiness: 'ready', coherence_ready: true,
      coherence_epoch_sha256: 'c'.repeat(64), source_selected_preview_enabled: true, machine_binding_enabled: true,
      production_qualified: false, consumer_binding_issued: false },
  };
  function input(kind = 'bind', changes = {}) {
    const c = { schema: 2, issuer: 'https://operations.mrnwebdesigns.com', audience: 'https://wpcontrol.mrndev.io',
      issued_at: 1800000001, expires_at: 1800000061, request_id: randomUUID(), nonce: 'f'.repeat(64),
      action: `${kind}-existing-management-authorization`, qualification_protocol: 'content-management-existing-v2',
      protocol_contracts_sha256: existingManagementHashes, lifetime_domain: 'original-resource-authorization-v2',
      operation_id: '11111111-1111-4111-8111-111111111111', reviewed_digest: 'd'.repeat(64), ...state.policy,
      requester_binding_sha256: 'e'.repeat(64), original_delegation_request_id: '22222222-2222-4222-8222-222222222222',
      original_resource_authorization_sha256: '8'.repeat(64), original_resource_authorization_expires_at: 1800000601,
      original_resource_authorization_current: true, ...changes };
    const bytes = Buffer.from(delegationCanonical(c));
    return { signed_request: { key_id: 'identity', payload_base64: bytes.toString('base64'),
      signature_base64: sign(null, Buffer.concat([Buffer.from(wire.machine_abilities[kind].purpose), bytes]), keys.privateKey).toString('base64') } };
  }
  const client = {
    async readResource() { return { contents: [{ text: JSON.stringify(state.connection) }] }; },
    async listTools() { return { tools: state.tools }; },
    async callTool(request, unused, options) {
      state.calls.push({ request, options }); await state.duringCall?.(request);
      if (state.error) throw state.error;
      if (request.name === tools.readiness) return { content: [{ type: 'text', text: JSON.stringify(state.readiness) }] };
      const kind = request.name === tools.bind ? 'bind' : 'read', e = request.arguments.signed_request;
      const bytes = Buffer.from(e.payload_base64, 'base64'), c = JSON.parse(bytes);
      assert.ok(verify(null, Buffer.concat([Buffer.from(wire.machine_abilities[kind].purpose), bytes]), keys.publicKey, Buffer.from(e.signature_base64, 'base64')));
      const value = { schema: 2, ...Object.fromEntries(['operation_id', 'reviewed_digest', 'qualification_protocol', 'protocol_contracts_sha256',
        'original_delegation_request_id', 'original_resource_authorization_sha256', 'original_resource_authorization_expires_at'].map(k => [k, c[k]])),
        state: 'bound-blocked', authorization_binding_sha256: '9'.repeat(64), production_qualified: false, consumer_binding_issued: false };
      state.changeProjection?.(value);
      return { content: [{ type: 'text', text: JSON.stringify(value) }] };
    },
    async close() { state.closes++; },
  };
  const options = { policy: () => state.policy, now: () => state.now, monotonic: () => state.clock };
  return { state, input, client, options, transport: new OriginalAuthorizationTransport(client, options) };
}

test('canonical readiness and control transport retain fixed bounds and original projection without admission', async () => {
  const f = fixture(); await assert.rejects(() => f.transport.status());
  await f.transport.initialize();
  for (const kind of ['bind', 'read']) {
    const status = await f.transport.status(), input = f.input(kind), result = await f.transport[kind](input, status);
    assert.equal(result.state, 'bound-blocked'); assert.equal(result.production_qualified, false);
    assert.equal(result.consumer_binding_issued, false); assert.ok(Object.isFrozen(result));
    assert.equal(result.original_resource_authorization_sha256, '8'.repeat(64));
    assert.equal(result.original_resource_authorization_expires_at, 1800000601);
    await assert.rejects(() => f.transport[kind](input, status));
  }
  assert.deepEqual(f.state.calls.map(v => [v.request.name, v.options.timeout]),
    [[tools.readiness, 5000], [tools.bind, 20000], [tools.readiness, 5000], [tools.read, 20000]]);
  assert.equal(wire.limits.automatic_retries, 0);
});

test('all frozen source dispatch schema vectors retain bounded closed parsing', () => {
  assert.equal(digest(wireBytes), '8f524002fc5fd900f10e2851d0edd475b4e6ecfe3e6db2a1335573a6e3b7a579');
  const vectorBytes = readFileSync(new URL('./fixtures/management-existing-dispatch.json', import.meta.url)), v = JSON.parse(vectorBytes);
  const source = JSON.parse(readFileSync(new URL('../contracts/content-management-existing-dispatch-source.json', import.meta.url)));
  assert.match(source.commit, /^[a-f0-9]{40}$/); assert.equal(source.sha256, digest(wireBytes));
  assert.equal(source.fixture_sha256, digest(vectorBytes)); assert.equal(source.runtimeRegistered, false);
  assert.equal(v.schema_only, true); assert.equal(v.runtime_registered, false);
  assert.equal(v.contract_sha256, digest(wireBytes)); assert.equal(v.positive.length, 7); assert.equal(v.negative.length, 21);
  for (const [rows, accepted] of [[v.positive, true], [v.negative, false]]) for (const row of rows) {
    const type = compileManagementEvidenceSchema({ $ref: `#/$defs/${row.type}`, $defs: wire.$defs });
    assert.equal(type.safeParse(row.value).success, accepted, row.label ?? row.type);
  }
});

test('missing or differently annotated capabilities, aliases, duplicates and wrong Dashboard identity deny initialization', async () => {
  for (const change of [s => s.tools.pop(), s => s.tools.push(s.tools[0]), s => { s.tools[0].name += '_alias'; },
    s => { s.tools[0].annotations.readOnlyHint = false; }, s => { s.tools[1].annotations.readOnlyHint = true; },
    s => { s.tools[2].annotations.idempotentHint = true; }, s => { s.tools[1].annotations.destructiveHint = true; },
    s => { s.connection.connected = false; }, s => { s.connection.abilitiesCount = 0; }, s => { s.connection.dashboardHost = 'other.example'; }]) {
    const f = fixture(); change(f.state); await assert.rejects(() => f.transport.initialize());
    await assert.rejects(() => f.transport.status()); assert.equal(f.state.calls.length, 0);
  }
});

test('disabled readiness never permits control, including an intact but disabled coherence epoch or a copied ready result', async () => {
  for (const coherent of [true, false]) {
    const f = fixture(); await f.transport.initialize(); Object.assign(f.state.readiness, { binding_readiness: 'disabled',
      coherence_ready: coherent, coherence_epoch_sha256: coherent ? 'c'.repeat(64) : null,
      source_selected_preview_enabled: false, machine_binding_enabled: false });
    const status = await f.transport.status(); assert.equal(status.binding_readiness, 'disabled');
    await assert.rejects(() => f.transport.bind(f.input(), status)); assert.equal(f.state.calls.length, 1);
  }
  const f = fixture(); await f.transport.initialize(); const status = await f.transport.status();
  await assert.rejects(() => f.transport.bind(f.input(), { ...status }));
  await assert.rejects(() => f.transport.bind(f.input(), undefined)); assert.equal(f.state.calls.length, 1);
});

test('stale, future, changed-source, overlong and admitting readiness is refused', async () => {
  for (const change of [v => { v.observed_at++; }, v => { v.expires_at = v.observed_at; }, v => { v.expires_at = v.observed_at + 31; },
    v => { v.provider_source_sha = 'b'.repeat(40); }, v => { v.authorization_policy_sha256 = 'f'.repeat(64); },
    v => { v.dispatch_contract_sha256 = 'd'.repeat(64); }, v => { v.production_qualified = true; },
    v => { v.machine_binding_enabled = false; }, v => { v.caller_override = true; }]) {
    const f = fixture(); await f.transport.initialize(); change(f.state.readiness); await assert.rejects(() => f.transport.status());
  }
  const f = fixture(); await f.transport.initialize(); const status = await f.transport.status();
  f.state.now += 30000; await assert.rejects(() => f.transport.bind(f.input(), status)); assert.equal(f.state.calls.length, 1);
});

test('control claims must bind the fixed action, provider, policy, canonical bytes and original authorization ceiling', async () => {
  const f = fixture(); await f.transport.initialize();
  const bad = [f.input('read'), f.input('bind', { provider_source_sha: 'b'.repeat(40) }),
    f.input('bind', { authorization_policy_sha256: 'd'.repeat(64) }), f.input('bind', { expires_at: 1800000062 }),
    f.input('bind', { original_resource_authorization_expires_at: 1800000020 }),
    f.input('bind', { original_resource_authorization_current: false }), f.input('bind', { issued_at: 1800000002 }),
    { ...f.input(), email: 'caller@example.com' }];
  const noncanonical = f.input(); noncanonical.signed_request.payload_base64 = Buffer.from(' ' + Buffer.from(noncanonical.signed_request.payload_base64, 'base64')).toString('base64'); bad.push(noncanonical);
  const status = await f.transport.status();
  for (const value of bad) await assert.rejects(() => f.transport.bind(value, status));
  assert.equal(f.state.calls.length, 1);
});

test('projection substitution, admission flags and late or changing responses are discarded without retry', async () => {
  for (const change of [v => { v.operation_id = randomUUID(); }, v => { v.original_resource_authorization_expires_at++; },
    v => { v.original_resource_authorization_sha256 = 'f'.repeat(64); }, v => { v.reviewed_digest = 'e'.repeat(64); },
    v => { v.consumer_binding_issued = true; }, v => { v.state = 'ready'; }, v => { v.extra = true; }]) {
    const f = fixture(); await f.transport.initialize(); const status = await f.transport.status(); f.state.changeProjection = change;
    await assert.rejects(() => f.transport.bind(f.input(), status)); assert.equal(f.state.calls.length, 2);
  }
  for (const kind of ['status', 'bind']) {
    const f = fixture(); await f.transport.initialize(); const status = kind === 'bind' ? await f.transport.status() : null;
    f.state.duringCall = () => { f.state.clock += kind === 'bind' ? 20000 : 5000; };
    await assert.rejects(() => kind === 'bind' ? f.transport.bind(f.input(), status) : f.transport.status());
  }
  const f = fixture(); await f.transport.initialize(); const status = await f.transport.status();
  f.state.duringCall = () => { f.state.policy.authorization_policy_sha256 = 'f'.repeat(64); };
  await assert.rejects(() => f.transport.bind(f.input(), status));
});

test('uncertain responses and concurrent calls consume readiness once; optional lifecycle closes only its owned connection', async () => {
  const f = fixture(); await f.transport.initialize(); const status = await f.transport.status();
  f.state.error = new Error('synthetic lost response');
  const attempts = await Promise.allSettled([f.transport.bind(f.input(), status), f.transport.bind(f.input(), status)]);
  assert.ok(attempts.every(v => v.status === 'rejected')); assert.equal(f.state.calls.length, 2);
  let connects = 0;
  assert.equal(await createOriginalAuthorizationTransport({ connect: () => { connects++; } }), null); assert.equal(connects, 0);
  const g = fixture(), runtime = await createOriginalAuthorizationTransport({ enabled: true, connect: async () => g.client, ...g.options });
  const ready = await runtime.status(); g.state.duringCall = () => runtime.close();
  await assert.rejects(() => runtime.bind(g.input(), ready)); await runtime.close(); assert.equal(g.state.closes, 1);
  await assert.rejects(() => runtime.status());
  const h = fixture(); h.state.tools = [];
  await assert.rejects(() => createOriginalAuthorizationTransport({ enabled: true, connect: async () => h.client, ...h.options }));
  assert.equal(h.state.closes, 1);
});
