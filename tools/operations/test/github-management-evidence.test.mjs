import test from 'node:test';
import assert from 'node:assert/strict';
import { generateKeyPairSync } from 'node:crypto';
import { GithubApp } from '../src/github.mjs';
import { digest } from '../src/contracts.mjs';
import { managementEvidenceLimits as limits } from '../src/github-management-evidence.mjs';

const key = generateKeyPairSync('rsa', { modulusLength: 2048 });
const repository = 'mrnwebdesigns/mrn-code-qa-runner';
const json = (value, status = 200) => new Response(JSON.stringify(value), { status, headers: { 'content-type': 'application/json' } });
function fixture(options = {}) {
  const calls = [], clock = { wall: 1800000000000, mono: 1000, denied: false }, raw = Buffer.alloc(100000, 65);
  const fetchImpl = async (input, request) => {
    const url = String(input); calls.push({ url, request });
    if (url.endsWith('/access_tokens')) {
      assert.deepEqual(JSON.parse(request.body), { repository_ids: [789], permissions: { contents: 'read', actions: 'read' } });
      return json({ token: 'installation-fixture-only', expires_at: new Date(clock.wall + 3600000).toISOString(),
        repositories: [{ id: 789, full_name: repository }], permissions: { contents: 'read', actions: 'read', metadata: 'read' }, ...options.grant }, 201);
    }
    if (options.fetch) return options.fetch(url, request, clock, raw);
    if (url.startsWith('https://api.github.com/')) {
      assert.equal(request.headers.Authorization, 'Bearer installation-fixture-only');
      if (url.endsWith('/zip')) return new Response('', { status: 302, headers: { location: 'https://fixture.blob.core.windows.net/evidence?signature=test' } });
      return json({ id: 123 });
    }
    assert.ok(request.headers === undefined || JSON.stringify(request.headers) === JSON.stringify({ 'Accept-Encoding': 'identity' }));
    assert.equal(request.redirect, 'error'); return new Response(raw);
  };
  const app = new GithubApp({ appId: 123, installationId: 456, privateKey: key.privateKey, repositories: { [repository]: 789 },
    now: () => clock.wall, fetchImpl });
  const start = (expiresAt = clock.wall + 600000) => app.managementEvidence(repository,
    async () => { if (clock.denied) throw new Error('revoked original principal'); }, { expiresAt, monotonic: () => clock.mono });
  return { app, clock, calls, raw, start };
}

test('management reader reuses the exact central grant, returns original byte hashes and strips storage authorization', async () => {
  const f = fixture(), api = await f.start();
  assert.deepEqual(Object.keys(api), ['readRun', 'readArtifacts', 'downloadArtifact', 'finish']);
  const run = await api.readRun('123'); assert.equal(run.sha256, digest(JSON.stringify({ id: 123 })));
  await api.readArtifacts('123');
  for (const [id, role] of [['456', 'qualification'], ['789', 'acquisition']]) {
    const archive = await api.downloadArtifact(id, role);
    assert.deepEqual(archive.bytes, f.raw); assert.equal(archive.sha256, digest(f.raw));
  }
  assert.deepEqual(await api.finish(), { startedAt: f.clock.wall, completedAt: f.clock.wall });
  assert.equal(f.calls.length, 7); assert.equal(f.calls.filter(c => c.request.method === 'POST').length, 1);
  assert.equal(f.calls.some(c => /dispatches|mainwp|operations\.mrn/.test(c.url)), false);
  assert.equal(f.calls.every(c => c.request.signal instanceof AbortSignal), true);
});

test('management reader preserves the old receipt cap and enforces separate role and metadata caps', async () => {
  assert.deepEqual(limits, { acquisitionMs: 60000, requestMs: 30000, grantBytes: 32768, metadataBytes: 131072, qualificationBytes: 262144, acquisitionBytes: 196608 });
  for (const [role, size] of [['qualification', 262145], ['acquisition', 196609], ['metadata', 131073]]) {
    const f = fixture({ fetch: (url) => url.endsWith('/zip')
      ? new Response('', { status: 302, headers: { location: 'https://fixture.actions.githubusercontent.com/data' } })
      : new Response(Buffer.alloc(size, 65), { headers: { 'content-type': 'application/json' } }) });
    const api = await f.start();
    await assert.rejects(async () => role === 'metadata' ? api.readRun('1') : api.downloadArtifact('1', role), { code: 'MANAGEMENT_EVIDENCE_SIZE' });
    const count = f.calls.length;
    await assert.rejects(() => api.readArtifacts('1'), { code: 'MANAGEMENT_EVIDENCE_EXPIRED' }); assert.equal(f.calls.length, count);
  }
  const f = fixture(), old = await f.app.scoped(repository, 'qaRead', async () => {});
  await assert.rejects(() => old.artifact(1), { code: 'QA_ARTIFACT_SIZE' });
});

test('original intent expiry, total monotonic budget and backwards clocks cannot restart on later reads', async () => {
  for (const change of [f => { f.clock.wall += 5001; }, f => { f.clock.mono += 60000; },
    f => { f.clock.mono -= 1; }, f => { f.clock.wall -= 1; }]) {
    const f = fixture(), api = await f.start(f.clock.wall + 5000); change(f);
    await assert.rejects(() => api.readRun('123'), { code: 'MANAGEMENT_EVIDENCE_EXPIRED' }); assert.equal(f.calls.length, 1);
  }
  const f = fixture(), api = await f.start();
  f.clock.mono += 40000; await api.readRun('123'); f.clock.mono += 20000;
  await assert.rejects(() => api.readArtifacts('123'), { code: 'MANAGEMENT_EVIDENCE_EXPIRED' }); assert.equal(f.calls.length, 2);
});

test('revocation and deadline reached during download deny bytes and poison the original acquisition', async () => {
  for (const failure of ['revoked', 'expired']) {
    const f = fixture({ fetch: (url, request, clock) => {
      if (url.endsWith('/zip')) return new Response('', { status: 302, headers: { location: 'https://fixture.blob.core.windows.net/data' } });
      if (failure === 'revoked') clock.denied = true; else clock.mono += 60000;
      return new Response('archive');
    } });
    const api = await f.start();
    await assert.rejects(() => api.downloadArtifact('1', 'qualification'), failure === 'revoked' ? /revoked original principal/ : { code: 'MANAGEMENT_EVIDENCE_EXPIRED' });
    await assert.rejects(() => api.readRun('123'), { code: 'MANAGEMENT_EVIDENCE_EXPIRED' }); assert.equal(f.calls.length, 3);
  }
});

test('redirects reject unknown origins, credentials, ports and chained redirects without credential fallback', async () => {
  for (const location of ['https://evil.test/file', 'http://fixture.blob.core.windows.net/file',
    'https://user:pass@fixture.blob.core.windows.net/file', 'https://fixture.blob.core.windows.net:444/file',
    'https://fixture.blob.core.windows.net/file#fragment', 'not a URL']) {
    const f = fixture({ fetch: () => new Response('', { status: 302, headers: { location } }) }), api = await f.start();
    await assert.rejects(() => api.downloadArtifact('1', 'qualification'), { code: 'MANAGEMENT_EVIDENCE_HOST' }); assert.equal(f.calls.length, 2);
  }
  const f = fixture({ fetch: () => new Response('', { status: 302, headers: { location: 'https://fixture.blob.core.windows.net/data' } }) }), api = await f.start();
  await assert.rejects(() => api.downloadArtifact('1', 'qualification'), { code: 'MANAGEMENT_EVIDENCE_RESPONSE' });
  assert.equal(f.calls.length, 3); assert.deepEqual(f.calls[2].request.headers, { 'Accept-Encoding': 'identity' });
});

test('unsafe identifiers, changed run, repeated roles and duplicate artifact IDs cannot select new reads', async () => {
  const f = fixture(), api = await f.start();
  for (const id of [1, '01', '-1', '../other', '1?x=2', '1'.repeat(21)]) assert.throws(() => api.readRun(id), { code: 'MANAGEMENT_EVIDENCE_ID' });
  await api.readRun('123');
  assert.throws(() => api.readArtifacts('124'), { code: 'MANAGEMENT_EVIDENCE_ID' });
  await assert.rejects(() => api.readRun('123'), { code: 'MANAGEMENT_EVIDENCE_REPLAY' });
  await api.downloadArtifact('456', 'qualification');
  assert.throws(() => api.downloadArtifact('456', 'acquisition'), { code: 'MANAGEMENT_EVIDENCE_ID' });
  await assert.rejects(() => api.downloadArtifact('789', 'qualification'), { code: 'MANAGEMENT_EVIDENCE_REPLAY' });
  assert.throws(() => api.downloadArtifact('999', 'caller-role'), { code: 'MANAGEMENT_EVIDENCE_ID' });
  assert.equal(f.calls.length, 4);
});

test('incomplete, malformed and oversized announced metadata refuse without retries', async () => {
  for (const response of [() => new Response('', { headers: { 'content-type': 'application/json' } }),
    () => new Response('{', { headers: { 'content-type': 'application/json' } }),
    () => new Response('{}', { headers: { 'content-type': 'application/json', 'content-length': '4' } }),
    () => new Response('{}', { headers: { 'content-type': 'application/json', 'content-length': '131073' } }),
    () => new Response('{}', { headers: { 'content-type': 'application/json', 'content-encoding': 'gzip' } }),
    () => new Response(Buffer.from([255]), { headers: { 'content-type': 'application/json' } }),
    () => new Response('{}', { status: 403, headers: { 'content-type': 'application/json' } })]) {
    const f = fixture({ fetch: response }), api = await f.start(); await assert.rejects(() => api.readRun('1'));
    await assert.rejects(() => api.readArtifacts('1'), { code: 'MANAGEMENT_EVIDENCE_EXPIRED' }); assert.equal(f.calls.length, 2);
  }
  const f = fixture(), api = await f.start(); await assert.rejects(() => api.finish(), { code: 'MANAGEMENT_EVIDENCE_INCOMPLETE' });
});

test('overbroad installation grants fail through the shared central App verifier', async () => {
  const f = fixture({ grant: { permissions: { contents: 'write', actions: 'read' } } });
  await assert.rejects(() => f.start(), { code: 'GITHUB_SCOPE' }); assert.equal(f.calls.length, 1);
});

test('a hung network call is aborted at the original deadline and overlapping reads cannot complete the acquisition', async () => {
  let observedSignal;
  const f = fixture({ fetch: (url, request) => { observedSignal = request.signal; return new Promise(() => {}); } });
  const api = await f.start(f.clock.wall + 30);
  const pending = api.readRun('123');
  await assert.rejects(() => api.readArtifacts('123'), { code: 'MANAGEMENT_EVIDENCE_REPLAY' });
  await assert.rejects(() => api.finish(), { code: 'MANAGEMENT_EVIDENCE_REPLAY' });
  await assert.rejects(() => pending, { code: 'MANAGEMENT_EVIDENCE_EXPIRED' });
  assert.equal(observedSignal.aborted, true);
  await assert.rejects(() => api.readArtifacts('123'), { code: 'MANAGEMENT_EVIDENCE_EXPIRED' }); assert.equal(f.calls.length, 2);
});
