import test from 'node:test';
import assert from 'node:assert/strict';
import { generateKeyPairSync, verify } from 'node:crypto';
import { request as httpRequest } from 'node:http';
import { digest } from '../src/contracts.mjs';
import { delegationCanonical, delegationDigest } from '../src/delegation.mjs';
import { managementEvidenceWire as wire } from '../src/management-evidence-contract.mjs';
import { ManagementEvidenceCallback, ManagementEvidenceSigner, ManagementEvidenceQueryReader,
  managementEvidenceReadbackTool, managementEvidencePath, createManagementEvidenceRuntime } from '../src/management-evidence-callback.mjs';
import { createHttpServer } from '../src/http.mjs';
import { managementEvidenceFixture } from './fixtures/management-evidence.mjs';

function fixture(t) {
  const f = managementEvidenceFixture(t); f.dispatch(); const query = f.query();
  const pointer = { query_id: query.query_id, provider_query_nonce: query.provider_query_nonce };
  const keys = generateKeyPairSync('ed25519'), state = f.state;
  const signer = new ManagementEvidenceSigner({ keyId: 'gateway-fixture', privateKey: keys.privateKey, now: () => state.now });
  const calls = [], status = { connected: true, dashboardHost: 'wpcontrol.mrndev.io', abilitiesCount: 115 };
  const tool = { name: managementEvidenceReadbackTool, annotations: { readOnlyHint: false, destructiveHint: false } };
  const client = {
    async readResource() { return { contents: [{ text: JSON.stringify(status) }] }; },
    async listTools() { return { tools: [tool] }; },
    async callTool(input) {
      calls.push('readback'); assert.equal(input.name, managementEvidenceReadbackTool);
      const envelope = input.arguments.signed_request, bytes = Buffer.from(envelope.payload_base64, 'base64');
      assert.ok(verify(null, Buffer.concat([Buffer.from(wire.purposes.query_readback), bytes]), keys.publicKey, Buffer.from(envelope.signature_base64, 'base64')));
      assert.equal(JSON.parse(bytes).action, 'read-management-evidence-query');
      await state.duringRead?.();
      return { content: [{ type: 'text', text: JSON.stringify({ query, query_sha256: delegationDigest(query), ...state.readback }) }] };
    },
  };
  const reader = new ManagementEvidenceQueryReader(client), stamp = () => new Date(state.now).toISOString();
  const archives = { qualification: Buffer.from('original qualification fixture'), acquisition: Buffer.from('original companion fixture') };
  const run = { id: 30, repository: { id: 10, full_name: f.policy.repository }, head_repository: { id: 10, full_name: f.policy.repository },
    workflow_id: 20, path: f.policy.workflow_path, head_sha: f.policy.runner_source_sha, head_branch: 'main', event: 'workflow_dispatch',
    run_attempt: 1, status: 'completed', conclusion: 'success', actor: { id: 40 }, triggering_actor: { id: 40 },
    created_at: stamp(), updated_at: stamp() };
  const artifacts = Object.entries(archives).map(([role, bytes], index) => ({ id: 50 + index,
    name: `management-${role === 'qualification' ? 'qualification' : 'acquisition'}-evidence-v1`,
    size_in_bytes: bytes.length, digest: `sha256:${digest(bytes)}`, expired: false,
    created_at: stamp(), expires_at: new Date(state.now + 3600000).toISOString(),
    workflow_run: { id: 30, repository_id: 10, head_sha: f.policy.runner_source_sha, head_branch: 'main' } }));
  const github = { managementEvidence: async (repository, authorize, options) => {
    calls.push('grant'); assert.equal(repository, f.policy.repository);
    assert.equal(options.expiresAt, f.ledger.dispatch(f.job).record.originalExpiresAt); await authorize();
    const startAt = state.now;
    return {
      readRun: async runId => { await authorize(); calls.push('run'); assert.equal(runId, '30'); return { value: run, sha256: digest(JSON.stringify(run)) }; },
      readArtifacts: async () => { await authorize(); calls.push('inventory'); return { value: { total_count: 2, artifacts }, sha256: digest(JSON.stringify(artifacts)) }; },
      downloadArtifact: async (id, role) => { await authorize(); calls.push(role); await state.duringDownload?.(role); await authorize();
        return { bytes: archives[role], sha256: digest(archives[role]), downloadedAt: state.now }; },
      finish: async () => { await authorize(); return { startedAt: startAt, completedAt: state.now }; },
    };
  } };
  const handler = new ManagementEvidenceCallback({ signer, reader, ledger: f.ledger, github, verifyStaff: async actor => {
    assert.equal(actor.googleSubject, f.actor.googleSubject); assert.equal(actor.expiresAt, f.actor.expiresAt);
    await state.duringStaff?.();
  }, now: () => state.now, monotonic: () => state.mono });
  const call = async (input = pointer) => { if (!reader.ready) await reader.initialize(); return handler.respond(input); };
  return { ...f, query, pointer, keys, signer, reader, status, tool, client, archives, run, artifacts, handler, call, calls };
}

test('authenticated evidence callback signs exact independent run and original ZIP observations under its separate purpose', async t => {
  const f = fixture(t), result = await f.call(), envelope = result.signed_acquisition;
  const bytes = Buffer.from(envelope.payload_base64, 'base64'), claims = JSON.parse(bytes);
  assert.equal(bytes.toString(), delegationCanonical(claims));
  assert.ok(verify(null, Buffer.concat([Buffer.from(wire.purposes.acquisition), bytes]), f.keys.publicKey, Buffer.from(envelope.signature_base64, 'base64')));
  assert.equal(verify(null, Buffer.concat([Buffer.from(wire.purposes.qa_manifest), bytes]), f.keys.publicKey, Buffer.from(envelope.signature_base64, 'base64')), false);
  assert.equal(claims.context_sha256, delegationDigest(f.query.context)); assert.equal(claims.query_sha256, delegationDigest(f.query));
  assert.equal(claims.recorded_dispatch.dispatch_id, f.ledger.dispatch(f.job).provenance.dispatch_id);
  assert.equal(claims.observed_run.run_id, '30'); assert.equal(claims.expires_at, f.state.now / 1000 + 5);
  assert.deepEqual(Buffer.from(result.qualification_zip_base64, 'base64'), f.archives.qualification);
  assert.deepEqual(Buffer.from(result.acquisition_zip_base64, 'base64'), f.archives.acquisition);
  assert.deepEqual(f.calls, ['readback', 'grant', 'run', 'inventory', 'qualification', 'acquisition']);
  assert.equal('passed' in claims, false); assert.equal('admitted' in claims, false);
  await assert.rejects(f.call); assert.equal(f.calls.filter(x => x === 'grant').length, 1);
});

test('evidence reader requires the exact canonical MainWP POST ability, connected expected Dashboard and no fallback', async t => {
  for (const mutate of [f => { f.status.connected = false; }, f => { f.status.dashboardHost = 'foreign.example'; },
    f => { f.tool.name = 'mrn_mainwp__read_management_access_veto_query_v1'; }, f => { f.tool.annotations.readOnlyHint = true; },
    f => { f.client.listTools = async () => ({ tools: [f.tool, f.tool] }); }]) {
    const f = fixture(t); mutate(f); await assert.rejects(f.call); assert.deepEqual(f.calls, []);
  }
});

test('unknown pointers, altered authenticated context and delayed readback never select a run or mint a grant', async t => {
  for (const mutate of [f => { f.pointer.email = f.actor.verifiedEmail; }, f => { f.pointer.run_id = '999'; },
    f => { f.pointer.query_id = '00000000-0000-4000-8000-000000000001'; },
    f => { f.query.context.capture_artifact_sha256 = 'f'.repeat(64); },
    f => { f.state.readback = { query_sha256: 'f'.repeat(64) }; },
    f => { f.state.duringRead = () => { f.state.now += 5000; }; },
    f => { f.state.duringRead = () => f.access.recordPendingRevocation(f.job); }]) {
    const f = fixture(t); mutate(f); await assert.rejects(f.call); assert.equal(f.calls.includes('grant'), false);
  }
});

test('revocation or original budget expiry during acquisition withholds signature and permanently consumes the job', async t => {
  for (const mutate of [f => f.access.recordPendingRevocation(f.job),
    f => { f.policy.policy_epoch++; }, f => { f.state.mono = 60000; }, f => { f.state.now += 60000; },
    f => { f.state.mono = -1; }, f => { f.state.now--; }]) {
    const f = fixture(t); f.state.duringDownload = () => mutate(f);
    await assert.rejects(f.call);
    assert.equal(f.store.db.prepare('SELECT state FROM management_evidence_acquisitions').get().state, 'acquiring');
    await assert.rejects(f.call); assert.equal(f.calls.filter(x => x === 'grant').length, 1);
  }
});

test('altered artifacts and expiry during final signing never return a completed observation', async t => {
  for (const mutate of [f => { f.artifacts[0].digest = `sha256:${'f'.repeat(64)}`; }, f => { f.run.run_attempt = 2; },
    f => { const original = f.signer.acquisition.bind(f.signer); f.signer.acquisition = (...args) => { const result = original(...args); f.state.now += 60000; return result; }; }]) {
    const f = fixture(t); mutate(f); await assert.rejects(f.call);
    assert.equal(f.store.db.prepare('SELECT state FROM management_evidence_acquisitions').get().state, 'acquiring');
  }
});

test('fresh staff verification uses the original actor and denies suspended or unavailable membership before and after download', async t => {
  for (const afterDownload of [false, true]) {
    const f = fixture(t);
    const deny = () => { f.state.duringStaff = () => { throw new Error('fixture membership unavailable'); }; };
    if (afterDownload) f.state.duringDownload = deny; else deny();
    await assert.rejects(f.call);
    assert.equal(f.calls.includes('grant'), afterDownload);
    assert.equal(f.store.db.prepare('SELECT state FROM management_evidence_acquisitions').get().state, 'acquiring');
    f.state.duringStaff = undefined;
    await assert.rejects(f.call); assert.equal(f.calls.filter(x => x === 'grant').length, afterDownload ? 1 : 0);
  }
  const f = fixture(t);
  f.state.duringRead = () => { f.state.mono = 59980; };
  f.state.duringStaff = () => new Promise(() => {});
  await assert.rejects(f.call, { code: 'MANAGEMENT_EVIDENCE_CALLBACK' });
  assert.equal(f.calls.includes('grant'), false);
  assert.throws(() => new ManagementEvidenceCallback({ ledger: f.ledger }));
});

function post(server, path, body, headers = {}) {
  return new Promise((resolve, reject) => {
    const req = httpRequest({ host: '127.0.0.1', port: server.address().port, path, method: 'POST', agent: false,
      headers: { host: 'operations.mrnwebdesigns.com', 'content-type': 'application/json', ...headers } }, res => {
      const chunks = []; res.on('data', c => chunks.push(c));
      res.on('end', () => { const raw = Buffer.concat(chunks); resolve({ status: res.statusCode, raw, body: JSON.parse(raw) }); });
    }); req.on('error', reject); req.end(body);
  });
}

test('actual HTTP evidence callback is opt-in, canonical, bounded and separate from caller OAuth', async t => {
  const f = fixture(t); await f.reader.initialize();
  const server = createHttpServer({ publicUrl: 'https://operations.mrnwebdesigns.com/mcp', managementEvidence: f.handler });
  await new Promise(resolve => server.listen(0, '127.0.0.1', resolve)); t.after(() => server.close());
  const body = delegationCanonical(f.pointer);
  for (const [value, headers] of [[body, { authorization: 'Bearer forbidden' }], [` ${body}`, {}], [JSON.stringify({ padding: 'x'.repeat(512) }), {}]]) {
    assert.equal((await post(server, managementEvidencePath, value, headers)).status, 400);
  }
  assert.deepEqual(f.calls, []);
  const response = await post(server, managementEvidencePath, body);
  assert.equal(response.status, 200); assert.ok(response.body.signed_acquisition);
  assert.equal(response.raw.toString('utf8'), delegationCanonical(response.body));
  assert.ok(response.raw.toString('utf8').startsWith('{"acquisition_zip_base64":'));
  assert.equal((await post(server, managementEvidencePath, body)).status, 400);
  assert.equal((await post(server, '/internal/mainwp/management-access-veto', body)).status, 404);
  const disabled = createHttpServer({ publicUrl: 'https://operations.mrnwebdesigns.com/mcp' });
  await new Promise(resolve => disabled.listen(0, '127.0.0.1', resolve)); t.after(() => disabled.close());
  assert.equal((await post(disabled, managementEvidencePath, body)).status, 404);
  assert.throws(() => createHttpServer({ publicUrl: 'https://foreign.example/mcp', managementEvidence: f.handler }));
});

test('occupied evidence slots never consume the independent short access-veto capacity', async t => {
  let release, admitted, count = 0;
  const held = new Promise(resolve => { release = resolve; }), both = new Promise(resolve => { admitted = resolve; });
  const server = createHttpServer({ publicUrl: 'https://operations.mrnwebdesigns.com/mcp',
    managementEvidence: { respond: async () => { if (++count === 2) admitted(); await held; return { observation: 'capacity-fixture' }; } },
    managementAccessVeto: { respond: async () => ({ capacity: 'independent', a: 1 }) } });
  await new Promise(resolve => server.listen(0, '127.0.0.1', resolve)); t.after(() => server.close());
  const body = delegationCanonical({ query_id: '00000000-0000-4000-8000-000000000001', provider_query_nonce: 'a'.repeat(64) });
  const first = post(server, managementEvidencePath, body), second = post(server, managementEvidencePath, body);
  await both;
  assert.equal((await post(server, managementEvidencePath, body)).status, 429);
  const veto = await post(server, '/internal/mainwp/management-access-veto', body);
  assert.equal(veto.status, 200); assert.equal(veto.body.capacity, 'independent');
  assert.equal(veto.raw.toString('utf8'), '{"capacity":"independent","a":1}');
  release(); assert.equal((await first).status, 200); assert.equal((await second).status, 200);
});

test('evidence HTTP serialization is canonical independently of handler insertion order', async t => {
  const value = { signed_acquisition: { signature_base64: 'signature', payload_base64: 'payload', key_id: 'fixture' },
    qualification_zip_base64: 'qualification', acquisition_zip_base64: 'acquisition' };
  const server = createHttpServer({ publicUrl: 'https://operations.mrnwebdesigns.com/mcp',
    managementEvidence: { respond: async () => value } });
  await new Promise(resolve => server.listen(0, '127.0.0.1', resolve)); t.after(() => server.close());
  const response = await post(server, managementEvidencePath, '{}');
  assert.equal(response.status, 200);
  assert.equal(response.raw.toString('utf8'), delegationCanonical(value));
  assert.notEqual(response.raw.toString('utf8'), JSON.stringify(value));
});

test('optional evidence lifecycle warms a separate canonical reader, closes once and is absent by default', async t => {
  assert.equal(await createManagementEvidenceRuntime(), null);
  const f = fixture(t); let closes = 0; f.client.close = async () => { closes++; };
  const runtime = await createManagementEvidenceRuntime({ enabled: true, access: f.access, policy: () => f.policy, appActorId: '40',
    identity: { keyId: 'gateway-fixture', privateKey: f.keys.privateKey }, verifyStaff: async () => {}, connect: async () => f.client });
  await runtime.close(); await runtime.close(); assert.equal(closes, 1);
  await assert.rejects(() => runtime.callback.respond(f.pointer)); assert.deepEqual(f.calls, []);
  f.tool.annotations.readOnlyHint = true;
  await assert.rejects(() => createManagementEvidenceRuntime({ enabled: true, access: f.access, policy: () => f.policy, appActorId: '40',
    identity: { keyId: 'gateway-fixture', privateKey: f.keys.privateKey }, verifyStaff: async () => {}, connect: async () => f.client }));
  assert.equal(closes, 2);
});
