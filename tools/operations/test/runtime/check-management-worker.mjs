// Actual gateway/runner interoperability over disposable TLS and a signed
// synthetic GitHub OIDC identity. No hosted OIDC, Google session or site write.
import assert from 'node:assert/strict';
import https from 'node:https';
import { mock } from 'node:test';
import { generateKeyPairSync } from 'node:crypto';
import { execFileSync } from 'node:child_process';
import { mkdtempSync, readFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { pathToFileURL } from 'node:url';
import { once } from 'node:events';
import { createLocalJWKSet, exportJWK, SignJWT } from 'jose';
import { delegationDigest } from '../../src/delegation.mjs';
import { createHttpServer } from '../../src/http.mjs';
import { ManagementWorkerContext, managementWorkerAudience } from '../../src/management-worker-context.mjs';
import { managementEvidenceFixture } from '../fixtures/management-evidence.mjs';
import { sourceBrokerFixture } from '../fixtures/management-source.mjs';

process.umask(0o077);
const runner = resolve(process.env.MRN_GATEWAY_MANAGEMENT_RUNNER_SOURCE);
const lock = JSON.parse(readFileSync(new URL('./sources.json', import.meta.url)));
assert.equal(execFileSync('git', ['-C', runner, 'rev-parse', 'HEAD'], { encoding: 'utf8' }).trim(), lock.managementWorker.commit);
const provider = resolve(process.env.MRN_GATEWAY_PROVIDER_SOURCE), acquisition = resolve(process.env.MRN_GATEWAY_RUNNER_SOURCE);
assert.equal(execFileSync('git', ['-C', provider, 'rev-parse', 'HEAD'], { encoding: 'utf8' }).trim(), lock.provider.commit);
assert.equal(execFileSync('git', ['-C', acquisition, 'rev-parse', 'HEAD'], { encoding: 'utf8' }).trim(), lock.runner.commit);
// Fail before the long native test if its independently frozen acquisition
// runner differs. The new context reader does not replace that source boundary.
for (const name of ['prepare-node-acquisition.py', 'node-acquisition-control.py']) {
  const source = execFileSync('git', ['-C', provider, 'show', `${lock.provider.commit}:tests/runtime/${name}`], { encoding: 'utf8' });
  const matches = [...source.matchAll(/^RUNNER_COMMIT = '([a-f0-9]{40})'$/gm)];
  assert.equal(matches.length, 1); assert.equal(matches[0][1], lock.runner.commit);
}
const { acquireManagementWorkerContext } = await import(pathToFileURL(join(runner, 'src/management-worker-context.mjs')));
const { ManagementSourceRecipient } = await import(pathToFileURL(join(runner, 'src/management-source-recipient.mjs')));
const { bindManagementSourceRecipient, readManagementSourceBatch, managementSourceRecipientUrl, managementSourceBatchUrl }
  = await import(pathToFileURL(join(runner, 'src/management-source-http.mjs')));
assert.deepEqual(readFileSync(join(runner, 'contracts/content-management-source-transfer-v1.json')),
  readFileSync(new URL('../../contracts/content-management-source-transfer-v1.json', import.meta.url)));
// Compare the imported closed subset against the actual immutable gateway wire.
const subset = JSON.parse(readFileSync(join(runner, 'contracts/management-worker-context-v1.json')));
const wire = JSON.parse(readFileSync(new URL('../../contracts/content-management-evidence-v1.json', import.meta.url)));
for (const [name, schema] of Object.entries(subset.definitions))
  assert.deepEqual(schema, JSON.parse(JSON.stringify(wire.$defs[name]).replaceAll('#/$defs/', '#/definitions/')));

const cleanup = [], f = managementEvidenceFixture({ after: cb => cleanup.push(cb) }, ':memory:', Math.floor(Date.now() / 1000) * 1000);
const provenance = f.dispatch(), original = f.ledger.dispatch(f.job), identity = generateKeyPairSync('ed25519');
const oidc = generateKeyPairSync('rsa', { modulusLength: 2048 });
const jwk = { ...await exportJWK(oidc.publicKey), kid: 'synthetic-oidc', alg: 'RS256' };
const trust = { repositoryOwnerId: '98', subject: 'repo:mrnwebdesigns/mrn-code-qa-runner:environment:MRN QA',
  environment: 'MRN QA', runnerEnvironment: 'github-hosted' };
let checks = 0, denyStaff = false;
const reader = new ManagementWorkerContext({ ledger: f.ledger, identity: { keyId: 'fixture', privateKey: identity.privateKey },
  keys: createLocalJWKSet({ keys: [jwk] }), trust: () => trust, verifyStaff: async actor => {
    assert.equal(actor.googleSubject, f.actor.googleSubject); assert.equal(actor.verifiedEmail, f.actor.verifiedEmail);
    checks++; if (denyStaff) throw new Error('Synthetic Directory denial');
  } });
const gateway = createHttpServer({ publicUrl: 'https://operations.mrnwebdesigns.com/mcp', managementWorker: reader });
const disabled = createHttpServer({ publicUrl: 'https://operations.mrnwebdesigns.com/mcp' });
const directory = mkdtempSync(join(tmpdir(), 'mrn-worker-integration-')), cert = join(directory, 'cert.pem'), key = join(directory, 'key.pem');
execFileSync('openssl', ['req', '-x509', '-nodes', '-newkey', 'rsa:2048', '-keyout', key, '-out', cert, '-days', '1',
  '-subj', '/CN=operations.mrnwebdesigns.com', '-addext', 'subjectAltName=DNS:operations.mrnwebdesigns.com'], { stdio: 'ignore' });
let active = gateway, calls = 0;
const sockets = new Set(), server = https.createServer({ cert: readFileSync(cert), key: readFileSync(key) }, (req, res) => {
  calls++; active.emit('request', req, res);
});
server.on('connection', socket => { sockets.add(socket); socket.once('close', () => sockets.delete(socket)); });
server.listen(0, '127.0.0.1'); await once(server, 'listening');
const realRequest = https.request;
const hook = mock.method(https, 'request', (url, options, handler) => {
  assert.ok([managementWorkerAudience, managementSourceRecipientUrl, managementSourceBatchUrl].includes(url));
  assert.equal(options.rejectUnauthorized, true);
  // Test-only address/CA substitution; the shipping client has no such inputs.
  return realRequest(url, { ...options, hostname: '127.0.0.1', port: server.address().port, ca: readFileSync(cert),
    headers: { ...options.headers, Host: 'operations.mrnwebdesigns.com' } }, handler);
});
const pointer = { dispatch_id: provenance.dispatch_id, context_sha256: delegationDigest(original.context) };
const expected = { ...Object.fromEntries(['repository_id', 'workflow_id', 'workflow_path', 'workflow_sha256', 'run_id',
  'runner_source_sha', 'engine_commit', 'engine_configuration_sha256', 'runtime_sha256'].map(name => [name, provenance[name]])),
app_actor_id: original.observation.app_actor_id, evidence_policy_sha256: original.context.evidence_policy_sha256 };
const request = async (patch = {}) => {
  f.state.now = Date.now(); const now = Math.floor(Date.now() / 1000);
  const token = await new SignJWT({ iss: 'https://token.actions.githubusercontent.com', aud: managementWorkerAudience,
    sub: trust.subject, iat: now, nbf: now, exp: now + 60, jti: 'disposable-worker',
    repository: provenance.repository, repository_id: provenance.repository_id, repository_owner: 'mrnwebdesigns',
    repository_owner_id: '98', repository_visibility: 'private', run_id: provenance.run_id, run_attempt: '1', actor_id: expected.app_actor_id,
    workflow_ref: `${provenance.repository}/${provenance.workflow_path}@${provenance.ref}`, workflow_sha: provenance.runner_source_sha,
    sha: provenance.runner_source_sha, ref: provenance.ref, ref_type: 'branch', event_name: 'workflow_dispatch',
    environment: trust.environment, runner_environment: 'github-hosted', ...patch })
    .setProtectedHeader({ alg: 'RS256', kid: jwk.kid }).sign(oidc.privateKey);
  return { pointer, expected, gatewayKeyId: 'fixture', gatewayPublicKey: identity.publicKey, oidcToken: token };
};
const passed = [];
try {
  const result = await acquireManagementWorkerContext(await request());
  assert.equal(checks, 1); assert.equal(calls, 1); assert.equal(result.state, 'original-record-read');
  assert.equal(delegationDigest(result.context), pointer.context_sha256);
  assert.equal(delegationDigest(result.dispatch_record), provenance.dispatch_record_sha256);
  passed.push('actual-gateway-signed-record-accepted-by-runner');
  await assert.rejects(() => request({ run_id: '999' }).then(acquireManagementWorkerContext));
  assert.equal(checks, 1); passed.push('different-protected-run-denied-before-staff-check');
  denyStaff = true; await assert.rejects(() => request().then(acquireManagementWorkerContext)); denyStaff = false;
  assert.equal(checks, 2); passed.push('fresh-original-staff-denial-withholds-record');
  const wrong = await request(); wrong.expected = { ...expected, runtime_sha256: 'f'.repeat(64) };
  await assert.rejects(() => acquireManagementWorkerContext(wrong));
  assert.equal(checks, 3); passed.push('runner-rejects-valid-signature-for-different-runtime');
  f.access.recordPendingRevocation(f.job);
  await assert.rejects(() => request().then(acquireManagementWorkerContext));
  assert.equal(checks, 3); passed.push('original-pending-revocation-withholds-record');
  active = disabled; await assert.rejects(() => request().then(acquireManagementWorkerContext));
  assert.equal(checks, 3); assert.equal(calls, 6); passed.push('production-startup-leaves-route-absent');
  // Actual optional HTTP handlers and independent supervisor client over TLS;
  // source controller remains a synthetic fixture, never a live capture.
  const s = await sourceBrokerFixture({ after: cb => cleanup.push(cb) }, { bindRecipient: false, now: Math.floor(Date.now() / 1000) * 1000 });
  Object.defineProperty(s.state, 'now', { get: () => Date.now() });
  const sourceOriginal = s.ledger.originalDispatch(s.pointer.dispatch_id);
  const recipient = new ManagementSourceRecipient({ original: { state: 'original-record-read', context: sourceOriginal.context,
    recorded_dispatch: sourceOriginal.provenance, dispatch_record: sourceOriginal.observation }, sourceContext: s.source.context,
  slot: s.source.slot, gatewayKeyId: s.identity.keyId, gatewayPublicKey: s.identity.publicKey });
  active = createHttpServer({ publicUrl: 'https://operations.mrnwebdesigns.com/mcp', managementSource: s.broker });
  const token = (await s.token()).slice(7);
  await bindManagementSourceRecipient({ recipient, oidcToken: token });
  const opened = await readManagementSourceBatch({ recipient, batchIndex: 0, oidcToken: token });
  assert.equal(opened.state, 'partial-source-batch-verified');
  assert.equal(opened.batch.frames[1].data_base64, 'YWJj');
  assert.equal(opened.full_source_verified, false); assert.equal(opened.admitted, false);
  passed.push('actual-broker-encryption-opened-by-independent-supervisor-over-tls');
  active = disabled;
  await assert.rejects(() => readManagementSourceBatch({ recipient, batchIndex: 0, oidcToken: token }));
  passed.push('production-startup-leaves-source-routes-absent');
  active = createHttpServer({ publicUrl: 'https://operations.mrnwebdesigns.com/mcp', managementSource: s.broker });
  s.state.backend = () => s.access.recordPendingRevocation(s.job);
  await assert.rejects(() => readManagementSourceBatch({ recipient, batchIndex: 0, oidcToken: token })); recipient.destroy();
  passed.push('original-revocation-after-source-read-withholds-ciphertext');
  process.stdout.write(JSON.stringify({ result: 'PASS', scope: 'disposable-context-tls-and-source-codec', passed,
    runnerSource: lock.managementWorker.commit, productionCredentials: false, liveGoogleVerified: false,
    sourceHttpVerified: true, fullSourceClosureVerified: false, hostedOidcVerified: false,
    contentWriteVerified: false, chatgptAcceptanceVerified: false }) + '\n');
} finally {
  hook.mock.restore(); for (const socket of sockets) socket.destroy();
  await new Promise(r => server.close(r)); cleanup.forEach(cb => cb()); rmSync(directory, { recursive: true, force: true });
}
