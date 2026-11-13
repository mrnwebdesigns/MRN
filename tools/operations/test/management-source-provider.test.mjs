import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createPublicKey, generateKeyPairSync, sign, verify } from 'node:crypto';
import { delegationCanonical, delegationDigest } from '../src/delegation.mjs';
import { digest } from '../src/contracts.mjs';
import { ManagementSourceBootstrap, ManagementSourceContextReader, ManagementSourceCaptureReader,
  createManagementSourceProvider } from '../src/management-source-provider.mjs';
import { ManagementSourceCatalogRegistry } from '../src/management-source-catalog.mjs';
import { managementSourceWire } from '../src/management-source-transfer.mjs';
import { managementEvidenceFixture } from './fixtures/management-evidence.mjs';

const purpose = 'mrn-content-management-source-context-v1:';
const contextTool = 'mrn_mainwp__read_content_edit_qualification_source_context_v1';
const captureTool = 'mrn_mainwp__capture_content_edit_qualification_source_v1';
const fixtureRead = name => JSON.parse(readFileSync(new URL(`./fixtures/${name}`, import.meta.url)));
const response = value => ({ content: [{ type: 'text', text: JSON.stringify(value) }] });

test('bootstrap contract and independent vector preserve their exact provider source bytes', () => {
  const source = JSON.parse(readFileSync(new URL('../contracts/content-management-source-context-source.json', import.meta.url)));
  assert.equal(source.repository, 'mrnwebdesigns/mrn-mainwp-operations-api');
  assert.equal(source.commit, '18922d4844d4785af53556685601effebc66f999');
  assert.deepEqual(Object.keys(source.files).sort(), ['contracts/content-management-source-context-v1.json',
    'tests/fixtures/content-management-source-context-v1.json']);
  for (const file of Object.values(source.files))
    assert.equal(digest(readFileSync(new URL(`../${file.local}`, import.meta.url))), file.sha256);
});

function fixture(t) {
  const f = managementEvidenceFixture(t); f.recordCapture();
  const keys = generateKeyPairSync('ed25519'), registrar = { keyId: 'registrar', ...keys };
  const context = { management: f.ledger.captured(f.job).context, source_recipe_catalog_sha256: 'a'.repeat(64) };
  const state = { staff: 0, reads: 0, calls: [], closes: 0 };
  const verifyStaff = async actor => {
    state.staff++; assert.equal(actor.googleSubject, f.actor.googleSubject); assert.equal(actor.token, undefined);
    await state.duringStaff?.();
  };
  const reader = { async read(envelope) {
    state.reads++; const raw = Buffer.from(envelope.payload_base64, 'base64'), claims = JSON.parse(raw);
    assert.equal(verify(null, Buffer.concat([Buffer.from(purpose), raw]), keys.publicKey,
      Buffer.from(envelope.signature_base64, 'base64')), true);
    assert.equal(raw.toString(), delegationCanonical(claims));
    assert.equal(claims.qualification_job_id, f.job);
    assert.equal(claims.management_context_sha256, context.management.management_context_sha256);
    assert.equal(claims.expires_at, Math.min(claims.issued_at + 20, context.management.binding.expires_at));
    assert.equal(raw.toString().includes('@'), false);
    state.input = { signed_request: envelope }; state.claims = claims;
    const output = { schema: 1, source_context: structuredClone(context), source_context_sha256: delegationDigest(context),
      request_sha256: delegationDigest(state.input), source_bytes_verified: false, slot_created: false, admitted: false };
    await state.duringRead?.(output); return output;
  } };
  const options = { ledger: f.ledger, registrar, reader, verifyStaff, now: () => f.state.now, monotonic: () => f.state.mono };
  const bootstrap = new ManagementSourceBootstrap(options);
  const status = { connected: true, dashboardHost: 'wpcontrol.mrndev.io', abilitiesCount: 115 };
  const tools = [contextTool, captureTool].map(name => ({ name, annotations: { readOnlyHint: false, destructiveHint: false } }));
  const client = {
    async readResource({ uri }) { assert.equal(uri, 'mainwp://status'); return { contents: [{ text: JSON.stringify(status) }] }; },
    async listTools() { return { tools }; },
    async callTool(input, schema, callOptions) {
      state.calls.push({ name: input.name, timeout: callOptions.timeout }); assert.equal(schema, undefined);
      if (input.name === contextTool) return response(await reader.read(input.arguments.signed_request));
      assert.equal(input.name, captureTool); assert.equal(callOptions.timeout, 120000);
      state.captureInput = input.arguments;
      return response({ schema: 1, context, state: 'complete', slot: fixtureRead('content-management-source-transfer-v1.json').capture_output.slot });
    },
    async close() { state.closes++; },
  };
  return { ...f, stateProvider: state, registrar, context, options, bootstrap, reader, client, status, tools,
    read: () => bootstrap.read(f.job) };
}

test('independent provider vector binds the whole signed input and distinct bootstrap purpose', () => {
  const vector = fixtureRead('content-management-source-context-v1.json');
  const raw = Buffer.from(vector.input.signed_request.payload_base64, 'base64');
  const key = createPublicKey({ key: Buffer.concat([Buffer.from('302a300506032b6570032100', 'hex'),
    Buffer.from(vector.public_key_base64, 'base64')]), type: 'spki', format: 'der' });
  assert.equal(verify(null, Buffer.concat([Buffer.from(purpose), raw]), key,
    Buffer.from(vector.input.signed_request.signature_base64, 'base64')), true);
  assert.equal(raw.toString(), delegationCanonical(vector.claims));
  assert.equal(vector.output.request_sha256, delegationDigest(vector.input));
  assert.notEqual(vector.output.request_sha256, delegationDigest(vector.claims));
  assert.equal(vector.output.source_context_sha256, delegationDigest(vector.output.source_context));
});

test('bootstrap signs only the retained job pointer and rechecks the original Google actor', async t => {
  const f = fixture(t); assert.equal(delegationCanonical(await f.read()), delegationCanonical(f.context));
  assert.equal(f.stateProvider.reads, 1); assert.equal(f.stateProvider.staff, 2);
  assert.equal(f.stateProvider.claims.action, 'read-management-source-context');
  assert.equal(f.stateProvider.claims.issuer, 'mrn-content-qualification');
  assert.equal(f.stateProvider.claims.audience, 'https://wpcontrol.mrndev.io');
});

test('bootstrap refuses claims-only hashes, substitutions, extra fields and false admission claims', async t => {
  for (const mutate of [
    (v, f) => { v.request_sha256 = delegationDigest(f.stateProvider.claims); },
    v => { v.source_context_sha256 = 'f'.repeat(64); },
    v => { v.source_context.management.binding.site_id++; v.source_context_sha256 = delegationDigest(v.source_context); },
    v => { v.source_context.management.evidence_policy_epoch++; v.source_context_sha256 = delegationDigest(v.source_context); },
    v => { v.source_context.caller_email = 'owner@mrnwebdesigns.com'; },
    v => { v.admitted = true; },
    v => { v.slot_created = true; },
    (v, f) => { f.stateProvider.input.signed_request.key_id = 'substituted'; },
  ]) {
    const f = fixture(t); f.stateProvider.duringRead = v => mutate(v, f);
    await assert.rejects(f.read, { code: 'MANAGEMENT_SOURCE_PROVIDER' });
  }
});

test('revoked or changed original access and Google denial withhold source context', async t => {
  const f = fixture(t); f.access.recordPendingRevocation(f.job); await assert.rejects(f.read); assert.equal(f.stateProvider.reads, 0);
  const g = fixture(t); g.stateProvider.duringRead = () => g.access.recordPendingRevocation(g.job); await assert.rejects(g.read);
  const h = fixture(t); h.stateProvider.duringStaff = () => { if (h.stateProvider.staff === 2) throw new Error('suspended'); };
  await assert.rejects(h.read); assert.equal(h.stateProvider.reads, 1);
  const i = fixture(t); i.stateProvider.duringRead = () => { i.policy.policy_epoch++; }; await assert.rejects(i.read);
});

test('bootstrap preserves both deadlines and rejects clock reversal', async t => {
  for (const change of [f => { f.state.mono += 20000; }, f => { f.state.now += 20000; },
    f => { f.state.now--; }, f => { f.state.mono--; },
    f => { f.state.now = f.context.management.binding.expires_at * 1000; }]) {
    const f = fixture(t); f.stateProvider.duringRead = () => change(f); await assert.rejects(f.read);
  }
});

test('private readers discover only canonical POST routes and use their fixed separate deadlines', async t => {
  const f = fixture(t), reader = new ManagementSourceContextReader(f.client);
  const bootstrap = new ManagementSourceBootstrap({ ...f.options, reader });
  await assert.rejects(() => bootstrap.read(f.job)); assert.equal(f.stateProvider.calls.length, 0);
  await reader.initialize(); await bootstrap.read(f.job);
  assert.deepEqual(f.stateProvider.calls, [{ name: contextTool, timeout: 20000 }]);
  const capture = new ManagementSourceCaptureReader(f.client); await capture.initialize();
  await capture.capture(fixtureRead('content-management-source-transfer-v1.json').capture_input);
  assert.equal(f.stateProvider.calls.at(-1).timeout, 120000);
  await assert.rejects(() => capture.capture({ caller_url: 'https://other.invalid' }));
  assert.equal(f.stateProvider.calls.length, 2);
  for (const failure of ['missing', 'duplicate', 'read-only', 'destructive', 'host', 'disconnected']) {
    const g = fixture(t), r = new ManagementSourceContextReader(g.client);
    if (failure === 'missing') g.tools.shift();
    if (failure === 'duplicate') g.tools.push(g.tools[0]);
    if (failure === 'read-only') g.tools[0].annotations.readOnlyHint = true;
    if (failure === 'destructive') g.tools[0].annotations.destructiveHint = true;
    if (failure === 'host') g.status.dashboardHost = 'another.invalid';
    if (failure === 'disconnected') g.status.connected = false;
    await assert.rejects(() => r.initialize()); assert.equal(g.stateProvider.calls.length, 0);
  }
});

test('explicit private lifecycle connects canonical bootstrap to the one-use retained capture', async t => {
  assert.equal(await createManagementSourceProvider(), null);
  const f = fixture(t), b = f.context.management.binding;
  const pairs = { root: generateKeyPairSync('ed25519'), registrar: f.registrar,
    identity: generateKeyPairSync('ed25519'), qa: generateKeyPairSync('ed25519') };
  const keys = Object.fromEntries(Object.entries(pairs).map(([role, p]) => [role, [{ id: role,
    public_key_base64: p.publicKey.export({ format: 'der', type: 'spki' }).subarray(-32).toString('base64') }]]));
  const catalog = { schema: 1, adapter: 'content-management-source-catalog-v1',
    catalog_id: '11111111-1111-4111-8111-111111111111', issued_at: b.created_at, expires_at: b.expires_at, native_reads: true, artifacts: [] };
  const raw = Buffer.from(delegationCanonical(catalog));
  const envelope = { key_id: 'root', payload_base64: raw.toString('base64'), signature_base64:
    sign(null, Buffer.concat([Buffer.from(managementSourceWire.purposes.catalog), raw]), pairs.root.privateKey).toString('base64') };
  const registry = { schema: 1, keys, catalogs: [{ sha256: delegationDigest(catalog), envelope }] };
  f.context.source_recipe_catalog_sha256 = delegationDigest(catalog);
  const catalogs = new ManagementSourceCatalogRegistry({ ledger: f.ledger, current: () => registry,
    expectedSha256: () => delegationDigest(registry), now: () => f.state.now });
  const runtime = await createManagementSourceProvider({ ...f.options, catalogs, enabled: true, connect: async () => f.client });
  const result = await runtime.prepare(f.job);
  assert.equal(delegationCanonical(result.context), delegationCanonical(f.context));
  assert.deepEqual(f.stateProvider.captureInput.signed_source_catalog, envelope);
  await assert.rejects(() => runtime.prepare(f.job)); assert.equal(f.stateProvider.calls.length, 2);
  await runtime.close(); await runtime.close(); assert.equal(f.stateProvider.closes, 1);
  await assert.rejects(() => runtime.prepare(f.job)); assert.equal(f.stateProvider.calls.length, 2);
  const g = fixture(t); g.tools.length = 0;
  await assert.rejects(() => createManagementSourceProvider({ ...g.options, enabled: true, connect: async () => g.client }));
  assert.equal(g.stateProvider.closes, 1);
  const startup = readFileSync(new URL('../src/main.mjs', import.meta.url), 'utf8');
  assert.equal(startup.includes('createManagementSourceProvider'), false);
});
