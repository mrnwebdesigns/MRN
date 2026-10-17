// Synthetic transport boundaries only. This does not qualify a capture, run a
// protected worker, or contact a Dashboard. The unmodified pinned MCP process
// talks to an authenticated loopback HTTPS fixture with a new private CA.
import assert from 'node:assert/strict';
import { randomBytes } from 'node:crypto';
import { execFileSync } from 'node:child_process';
import { mkdtempSync, mkdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { createServer } from 'node:https';
import { Client } from '@modelcontextprotocol/sdk/client/index.js';
import { StdioClientTransport } from '@modelcontextprotocol/sdk/client/stdio.js';
import { delegationCanonical, delegationDigest } from '../../src/delegation.mjs';
import { compileContentSchema } from '../../src/content-schema.mjs';

process.umask(0o077);
const source = resolve(process.env.MRN_GATEWAY_MCP_SOURCE);
const lock = JSON.parse(readFileSync(new URL('./sources.json', import.meta.url)));
assert.equal(execFileSync('git', ['-C', source, 'rev-parse', 'HEAD'], { encoding: 'utf8' }).trim(), lock.transport.commit);
const wire = JSON.parse(readFileSync(new URL('../../contracts/content-qualification-v1.json', import.meta.url)));
const vectors = JSON.parse(readFileSync(new URL('../fixtures/content-qualification-contract.json', import.meta.url)));
const artifact = structuredClone(vectors.cases.find(row => row.definition === 'capture_artifact').value);
// Exercise the outer 4.4 MB ceiling. This synthetic inventory deliberately does
// not claim the independent parity, filesystem or source eligibility gates.
const files = artifact.parity_manifest.roots[2].files = [];
const makeFile = (index, length) => ({ path: (`wp-content/themes/fixture-child/${String(index).padStart(5, '0')}-`).padEnd(length, 'x'), sha256: 'a'.repeat(64) });
const rowBytes = Buffer.byteLength(delegationCanonical(makeFile(0, 512)));
const remaining = 4_400_000 - Buffer.byteLength(delegationCanonical(artifact));
const count = Math.ceil((remaining + 1) / (rowBytes + 1));
const pathBytes = remaining - (count - 1) - count * (rowBytes - 512);
const length = Math.floor(pathBytes / count), extra = pathBytes % count;
assert.ok(length >= 50 && length <= 512 && (length < 512 || extra === 0));
for (let index = 0; index < count; index++) files.push(makeFile(index, length + (index < extra ? 1 : 0)));
artifact.parity_sha256 = delegationDigest(artifact.parity_manifest);
assert.equal(Buffer.byteLength(delegationCanonical(artifact)), 4_400_000);
const output = { qualification_job_id: artifact.qualification_job_id, state: 'running', capture_artifact: artifact, capture_artifact_sha256: delegationDigest(artifact) };
compileContentSchema({ $ref: '#/definitions/capture_output' }, wire.definitions).parse(output);
// WordPress's default JSON encoding escapes slashes. The ASCII-only fixture
// makes that HTTP representation exact without modifying canonical hash input.
const body = JSON.stringify(output).replaceAll('/', '\\/');
const directory = mkdtempSync(join(tmpdir(), 'mrn-capture-transport-'));
const certificate = join(directory, 'tls.crt'), privateKey = join(directory, 'tls.key');
execFileSync('openssl', ['req', '-x509', '-nodes', '-newkey', 'rsa:2048', '-keyout', privateKey, '-out', certificate,
  '-days', '1', '-subj', '/CN=127.0.0.1', '-addext', 'subjectAltName=IP:127.0.0.1'], { stdio: 'ignore' });
const username = 'fixture', password = randomBytes(32).toString('hex');
const authorization = `Basic ${Buffer.from(`${username}:${password}`).toString('base64')}`;
const ability = { name: 'mrn-mainwp/capture-transport-fixture-v1', label: 'Private synthetic transport fixture',
  category: 'mrn-mainwp', description: 'Loopback fixture only', input_schema: { type: 'object', properties: {}, additionalProperties: false },
  meta: { annotations: { readonly: false, destructive: false, idempotent: true } } };
let mode = 'full', calls = 0;
const timers = new Set();
const later = (fn, delay) => { const timer = setTimeout(() => { timers.delete(timer); fn(); }, delay); timers.add(timer); };
const server = createServer({ key: readFileSync(privateKey), cert: readFileSync(certificate) }, (request, response) => {
  if (request.headers.authorization !== authorization) { response.writeHead(401); response.end('{}'); return; }
  response.setHeader('Content-Type', 'application/json');
  const path = new URL(request.url, 'https://127.0.0.1').pathname;
  if (path === '/wp-json/wp-abilities/v1/abilities') { response.end(JSON.stringify([ability])); return; }
  if (path !== '/wp-json/wp-abilities/v1/abilities/mrn-mainwp/capture-transport-fixture-v1/run' || request.method !== 'POST') {
    response.writeHead(404); response.end('{}'); return;
  }
  calls++;
  if (mode === 'oversize') response.end(JSON.stringify({ padding: 'x'.repeat(10 * 1024 * 1024) }));
  else if (mode === 'truncated') response.end(body.slice(0, -1));
  else if (mode === 'slow-headers') later(() => response.end(body), 800);
  else if (mode === 'slow-body') { response.write(body.slice(0, 100)); later(() => response.end(body.slice(100)), 800); }
  else if (mode === 'over-default-deadline') later(() => response.end(body), 31_000);
  else response.end(body);
});
const passed = [], clients = new Set();
try {
  await new Promise(resolveListen => server.listen(0, '127.0.0.1', resolveListen));
  let sequence = 0;
  const run = async (scenario, timeout) => {
    mode = scenario; calls = 0;
    const home = join(directory, String(sequence++)); mkdirSync(home, { mode: 0o700 });
    const client = new Client({ name: 'mrn-private-capture-transport-test', version: '0.1.0' }); clients.add(client);
    const env = { PATH: process.env.PATH, HOME: home, NODE_EXTRA_CA_CERTS: certificate,
      MAINWP_URL: `https://127.0.0.1:${server.address().port}`, MAINWP_USER: username, MAINWP_APP_PASSWORD: password,
      MAINWP_SAFE_MODE: 'true', MAINWP_RETRY_ENABLED: 'false', MAINWP_ABILITY_NAMESPACES: 'mainwp,mrn-mainwp',
      MAINWP_SKIP_SSL_VERIFY: 'false', MAINWP_ALLOW_HTTP: 'false' };
    if (timeout) env.MAINWP_REQUEST_TIMEOUT = String(timeout);
    const transport = new StdioClientTransport({ command: process.execPath, args: [join(source, 'dist/index.js')], cwd: home, env, stderr: 'pipe' });
    transport.stderr?.on('data', () => {});
    try {
      await client.connect(transport);
      const result = await client.callTool({ name: 'mrn_mainwp__capture_transport_fixture_v1', arguments: {} }, undefined, { timeout: 160_000 });
      assert.equal(calls, 1, 'Capture must not retry automatically');
      if (['full', 'over-default-deadline'].includes(scenario)) {
        assert.notEqual(result.isError, true);
        const received = JSON.parse(result.content[0].text);
        assert.equal(delegationDigest(received.capture_artifact), output.capture_artifact_sha256);
        assert.equal(delegationCanonical(received), delegationCanonical(output));
      } else {
        assert.equal(result.isError, true);
        const text = result.content.map(row => row.text ?? '').join('');
        assert.ok(text.length < 65536 && !text.includes(password));
        assert.match(text, scenario === 'oversize' ? /exceeds|maximum|size/i : scenario === 'truncated' ? /JSON|parse|position|expected/i : /timeout|timed out/i);
      }
      passed.push(scenario); process.stdout.write(`PASS capture transport: ${scenario}\n`);
    } finally { await client.close(); clients.delete(client); }
  };
  await run('full');
  await run('oversize');
  await run('truncated');
  await run('slow-headers', 300);
  await run('slow-body', 300);
  await run('over-default-deadline', 150_000);
  const report = { schema: 1, result: 'PASS', source: lock.transport, passed, artifactCanonicalBytes: 4_400_000,
    httpBytes: Buffer.byteLength(body), fixtureOutputSha256: delegationDigest(output), tlsVerified: true, automaticRetries: 0,
    productionCredentials: false, realDashboardVerified: false, providerCaptureVerified: false, protectedWorkerVerified: false,
    semanticQualificationVerified: false, chatgptAcceptanceVerified: false };
  assert.ok(process.env.MRN_CAPTURE_TRANSPORT_REPORT);
  writeFileSync(process.env.MRN_CAPTURE_TRANSPORT_REPORT, JSON.stringify(report, null, 2) + '\n', { mode: 0o600 });
} finally {
  for (const timer of timers) clearTimeout(timer);
  await Promise.allSettled([...clients].map(client => client.close()));
  server.closeAllConnections(); await new Promise(resolveClose => server.close(resolveClose));
  rmSync(directory, { recursive: true, force: true });
}
