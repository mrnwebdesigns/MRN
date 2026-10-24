// Actual SQLite/gateway/runner composition with synthetic Google and OIDC.
// The fixture's original state is supplied independently of the HTTP response.
import assert from 'node:assert/strict';
import https from 'node:https';
import { mock } from 'node:test';
import { execFileSync } from 'node:child_process';
import { mkdtempSync, readFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';
import { once } from 'node:events';
import { createHttpServer } from '../../src/http.mjs';

export async function existingWorkerHttpFixture(f, runner) {
  const lock = JSON.parse(readFileSync(new URL('./sources.json', import.meta.url)));
  assert.equal(execFileSync('git', ['-C', runner, 'rev-parse', 'HEAD'], { encoding: 'utf8' }).trim(), lock.managementWorker.commit);
  const { acquireExistingManagementWorkerRecord, existingManagementWorkerUrl }
    = await import(pathToFileURL(join(runner, 'src/management-existing-worker-http.mjs')));
  const gateway = createHttpServer({ publicUrl: 'https://operations.mrnwebdesigns.com/mcp', existingManagementWorker: f.reader });
  const disabled = createHttpServer({ publicUrl: 'https://operations.mrnwebdesigns.com/mcp' });
  const directory = mkdtempSync(join(tmpdir(), 'mrn-existing-worker-composed-')), cert = join(directory, 'cert.pem'), key = join(directory, 'key.pem');
  execFileSync('openssl', ['req', '-x509', '-nodes', '-newkey', 'rsa:2048', '-keyout', key, '-out', cert, '-days', '1',
    '-subj', '/CN=operations.mrnwebdesigns.com', '-addext', 'subjectAltName=DNS:operations.mrnwebdesigns.com'], { stdio: 'ignore' });
  let active = gateway, calls = 0;
  const sockets = new Set(), server = https.createServer({ cert: readFileSync(cert), key: readFileSync(key) }, (req, res) => {
    calls++; f.workerState.now = Date.now(); active.emit('request', req, res);
  });
  server.on('connection', socket => { sockets.add(socket); socket.once('close', () => sockets.delete(socket)); });
  server.listen(0, '127.0.0.1'); await once(server, 'listening');
  const realRequest = https.request, hook = mock.method(https, 'request', (url, options, callback) => {
    assert.equal(url, existingManagementWorkerUrl); assert.equal(options.rejectUnauthorized, true);
    return realRequest(url, { ...options, hostname: '127.0.0.1', port: server.address().port, ca: readFileSync(cert),
      headers: { ...options.headers, Host: 'operations.mrnwebdesigns.com' } }, callback);
  });
  const passed = [];
  try {
    const request = { pointer: f.pointer, retained: f.retained, originalBinding: f.context.binding,
      identityKeyId: f.ledger.signer.keyId, identityPublicKey: f.keys.publicKey, oidcToken: await f.token() };
    const result = await acquireExistingManagementWorkerRecord(request);
    assert.equal(result.state, 'existing-worker-record-consistent'); assert.equal(calls, 1);
    for (const field of ['current_access_proven', 'hosted_oidc_proven', 'source_verified', 'admitted']) assert.equal(result[field], false);
    passed.push('original-sqlite-record-acquired-over-tls-by-independent-runner');
    const other = structuredClone(request.originalBinding); other.operation_id = '99999999-9999-4999-8999-999999999999';
    await assert.rejects(() => acquireExistingManagementWorkerRecord({ ...request, originalBinding: other }));
    assert.equal(calls, 1); passed.push('foreign-original-refused-before-network');
    const old = f.claims.run_id; f.claims.run_id = '999';
    await assert.rejects(() => f.token().then(token => acquireExistingManagementWorkerRecord({ ...request, oidcToken: token })));
    f.claims.run_id = old; assert.equal(calls, 2); passed.push('different-oidc-run-refused');
    f.ledger.recordQueuePendingRevocation(f.job);
    await assert.rejects(() => acquireExistingManagementWorkerRecord(request), /MANAGEMENT_WORKER_HTTP/);
    assert.equal(calls, 3); passed.push('revoked-original-withheld');
    active = disabled;
    await assert.rejects(() => acquireExistingManagementWorkerRecord(request), /MANAGEMENT_WORKER_HTTP/);
    assert.equal(calls, 4); passed.push('production-default-route-absent');
    return { result: 'PASS', scope: 'actual-original-ledger-tls-synthetic-identity', passed,
      runnerSource: lock.managementWorker.commit, actualSqlite: true, actualTls: true,
      liveGoogleVerified: false, hostedOidcVerified: false, workerBootstrapVerified: false,
      sourceVerified: false, admitted: false, productionChanges: false };
  } finally {
    hook.mock.restore(); for (const socket of sockets) socket.destroy();
    await new Promise(resolve => server.close(resolve)); rmSync(directory, { recursive: true, force: true });
  }
}
