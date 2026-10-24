import test from 'node:test';
import assert from 'node:assert/strict';
import { ManagementReportTransport, createManagementReportTransport } from '../src/management-report-transport.mjs';

const retainName = 'mrn_mainwp__retain_content_edit_qualification_report_v1';
const readName = 'mrn_mainwp__read_content_edit_qualification_report_v1';
// Shape fixtures do not assert valid signatures, original custody or admission.
const envelope = () => ({ key_id: 'shape-only', payload_base64: 'e30=', signature_base64: Buffer.alloc(64).toString('base64') });
const input = () => ({ signed_request: envelope(), signed_qa_report: envelope() });
const summary = () => ({ qualification_job_id: '10000000-0000-4000-8000-000000000001',
  management_context_sha256: 'a'.repeat(64), qualification_report_sha256: 'b'.repeat(64),
  qualification_archive_sha256: 'c'.repeat(64), acquisition_archive_sha256: 'd'.repeat(64),
  acquisition_observation_sha256: 'e'.repeat(64), evidence_policy_epoch: 1, evidence_policy_sha256: 'f'.repeat(64),
  retained_at: 1800000000, state: 'retained', consumer_binding_issued: false, production_qualified: false });
function fixture() {
  const state = { status: { connected: true, dashboardHost: 'wpcontrol.mrndev.io', abilitiesCount: 115 },
    tools: [retainName, readName].map(name => ({ name, annotations: { readOnlyHint: false, destructiveHint: false } })),
    output: summary(), calls: [], closed: 0 };
  const client = {
    async readResource({ uri }) { assert.equal(uri, 'mainwp://status'); return { contents: [{ text: JSON.stringify(state.status) }] }; },
    async listTools() { return { tools: state.tools }; },
    async callTool(request, unused, options) {
      state.calls.push({ request, options }); await state.duringCall?.();
      if (state.error) throw state.error;
      return { content: [{ type: 'text', text: JSON.stringify(state.output) }] };
    },
    async close() { state.closed++; },
  };
  return { state, client, transport: new ManagementReportTransport(client) };
}

test('private report transport selects canonical POST routes with independent 90s retention and 20s read bounds', async () => {
  const f = fixture(); await assert.rejects(f.transport.retain(input())); assert.equal(f.state.calls.length, 0);
  await f.transport.initialize();
  assert.deepEqual(await f.transport.retain(input()), summary());
  assert.deepEqual(await f.transport.read({ signed_request: envelope() }), summary());
  assert.deepEqual(f.state.calls.map(v => [v.request.name, v.options.timeout]), [[retainName, 90000], [readName, 20000]]);
  assert.deepEqual(f.state.calls[0].request.arguments, input());
  assert.deepEqual(f.state.calls[1].request.arguments, { signed_request: envelope() });
});

test('missing, aliased, destructive or read-only tools and incorrect Dashboard identity refuse discovery', async () => {
  for (const mutate of [s => { s.status.connected = false; }, s => { s.status.dashboardHost = 'other.example'; },
    s => { s.status.abilitiesCount = 0; }, s => s.tools.pop(), s => { s.tools[0].name += '_alias'; },
    s => { s.tools[0].annotations.destructiveHint = true; }, s => { s.tools[1].annotations.readOnlyHint = true; },
    s => s.tools.push(s.tools[0])]) {
    const f = fixture(); mutate(f.state); await assert.rejects(f.transport.initialize());
    await assert.rejects(f.transport.retain(input())); assert.equal(f.state.calls.length, 0);
  }
});

test('caller selectors, missing signatures, oversized envelopes and getters fail before any backend request', async () => {
  const f = fixture(); await f.transport.initialize();
  const getter = Object.defineProperty(input(), 'signed_request', { enumerable: true, get() { throw new Error('must not run'); } });
  for (const bad of [{ ...input(), email: 'caller@example.com' }, { ...input(), run_id: '123' },
    { signed_request: envelope() }, { ...input(), signed_qa_report: { ...envelope(), payload_base64: 'a'.repeat(300000) } }, getter]) {
    await assert.rejects(f.transport.retain(bad));
  }
  await assert.rejects(f.transport.read(input())); assert.equal(f.state.calls.length, 0);
});

test('malformed or admitting summaries and unknown response fields fail closed', async () => {
  for (const mutate of [s => { s.production_qualified = true; }, s => { s.consumer_binding_issued = true; },
    s => { s.state = 'pending'; }, s => { delete s.acquisition_observation_sha256; },
    s => { s.qualification_report_sha256 = 'invalid'; }, s => { s.raw_report = {}; }]) {
    const f = fixture(); await f.transport.initialize(); mutate(f.state.output);
    await assert.rejects(f.transport.retain(input())); assert.equal(f.state.calls.length, 1);
  }
});

test('uncertain transport failure is propagated once without automatic acquisition retry or recovery', async () => {
  const f = fixture(); await f.transport.initialize(); f.state.error = new Error('synthetic response loss');
  await assert.rejects(f.transport.retain(input()), /synthetic response loss/);
  assert.equal(f.state.calls.length, 1); assert.equal(f.state.calls[0].request.name, retainName);
});

test('optional lifecycle stays disabled by default and refuses to reuse the callback connection', async () => {
  let connects = 0;
  assert.equal(await createManagementReportTransport({ connect: () => { connects++; } }), null); assert.equal(connects, 0);
  const f = fixture();
  await assert.rejects(createManagementReportTransport({ enabled: true, connect: async () => f.client, callbackClient: f.client }));
  assert.equal(f.state.closed, 0); assert.equal(f.state.calls.length, 0);
});

test('independent lifecycle closes only its own client and withholds an in-flight response after close', async () => {
  const f = fixture(), callback = fixture();
  const runtime = await createManagementReportTransport({ enabled: true, connect: async () => f.client, callbackClient: callback.client });
  f.state.duringCall = () => runtime.close();
  await assert.rejects(runtime.retain(input())); await runtime.close();
  await assert.rejects(runtime.read({ signed_request: envelope() }));
  assert.equal(f.state.closed, 1); assert.equal(callback.state.closed, 0); assert.equal(f.state.calls.length, 1);
  const broken = fixture(); broken.state.tools = [];
  await assert.rejects(createManagementReportTransport({ enabled: true, connect: async () => broken.client, callbackClient: callback.client }));
  assert.equal(broken.state.closed, 1); assert.equal(callback.state.closed, 0);
});
