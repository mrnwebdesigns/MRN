import test from 'node:test';
import assert from 'node:assert/strict';
import { generateKeyPairSync, verify } from 'node:crypto';
import { request as httpRequest } from 'node:http';
import { createLocalJWKSet, exportJWK, SignJWT } from 'jose';
import { delegationCanonical, delegationDigest } from '../src/delegation.mjs';
import { createHttpServer } from '../src/http.mjs';
import { ManagementWorkerContext, createManagementWorkerKeySet, managementWorkerAudience, managementWorkerPath,
  managementWorkerPurpose } from '../src/management-worker-context.mjs';
import { managementEvidenceFixture } from './fixtures/management-evidence.mjs';

const oidc = generateKeyPairSync('rsa', { modulusLength: 2048 });
const jwk = { ...await exportJWK(oidc.publicKey), alg: 'RS256', kid: 'fixture-key' };
const localKeys = createLocalJWKSet({ keys: [jwk] });
function fixture(t, keys = localKeys) {
  const f = managementEvidenceFixture(t); const p = f.dispatch(), state = f.state;
  const identity = generateKeyPairSync('ed25519');
  const pointer = { dispatch_id: p.dispatch_id, context_sha256: delegationDigest(f.ledger.captured(f.job).context) };
  const trust = { repositoryOwnerId: '98', environment: 'MRN QA', runnerEnvironment: 'github-hosted',
    subject: `repo:${p.repository}:environment:MRN QA` };
  const claims = { iss: 'https://token.actions.githubusercontent.com', aud: managementWorkerAudience,
    sub: trust.subject, iat: state.now / 1000, nbf: state.now / 1000, exp: state.now / 1000 + 60, jti: 'fixture-original-token',
    repository: p.repository, repository_id: p.repository_id, repository_owner: 'mrnwebdesigns', repository_owner_id: '98',
    repository_visibility: 'private', run_id: p.run_id, run_attempt: '1', actor_id: '40',
    workflow_ref: `${p.repository}/${p.workflow_path}@${p.ref}`, workflow_sha: p.runner_source_sha, sha: p.runner_source_sha,
    ref: p.ref, ref_type: 'branch', event_name: 'workflow_dispatch', environment: trust.environment,
    runner_environment: 'github-hosted', head_ref: '', base_ref: '' };
  let staffChecks = 0;
  const reader = new ManagementWorkerContext({ ledger: f.ledger, identity: { keyId: 'gateway-fixture', privateKey: identity.privateKey },
    trust: () => trust, keys, verifyStaff: async actor => { staffChecks++;
      assert.equal(actor.googleSubject, f.actor.googleSubject); assert.equal(actor.expiresAt, f.actor.expiresAt);
      await state.duringStaff?.(); }, now: () => state.now, monotonic: () => state.mono });
  const token = async () => new SignJWT(claims).setProtectedHeader({ alg: 'RS256', kid: 'fixture-key' }).sign(oidc.privateKey);
  const call = async (value = pointer) => reader.read(`Bearer ${await token()}`, value);
  return { ...f, p, identity, pointer, trust, claims, reader, token, call, get staffChecks() { return staffChecks; } };
}

test('original worker context is signed for the exact recorded run and excludes staff tokens and caller identities', async t => {
  const f = fixture(t), result = await f.call(), envelope = result.signed_context;
  const bytes = Buffer.from(envelope.payload_base64, 'base64'), claims = JSON.parse(bytes);
  assert.ok(verify(null, Buffer.concat([Buffer.from(managementWorkerPurpose), bytes]), f.identity.publicKey, Buffer.from(envelope.signature_base64, 'base64')));
  assert.equal(bytes.toString(), delegationCanonical(claims));
  assert.equal(claims.audience, `https://github.com/${f.p.repository}/actions/runs/${f.p.run_id}`);
  assert.equal(claims.dispatch_id, f.p.dispatch_id); assert.equal(claims.context_sha256, delegationDigest(claims.context));
  assert.equal(claims.recorded_dispatch.dispatch_record_sha256, delegationDigest(claims.dispatch_record));
  assert.equal(claims.dispatch_record.requested_at, f.state.now);
  assert.equal(claims.dispatch_record.observed_at, f.state.now); assert.equal(claims.expires_at, f.state.now / 1000 + 5);
  for (const field of ['googleSubject', 'verifiedEmail', 'never-store-this-token', 'post_content', 'fixture-original-token']) assert.equal(bytes.includes(field), false);
  assert.equal(f.staffChecks, 1);
  assert.equal(f.store.db.prepare('SELECT event FROM management_access_audit ORDER BY seq DESC').get().event, 'worker-context-read');
  assert.equal(f.store.db.prepare('SELECT count(*) AS n FROM management_evidence_acquisitions').get().n, 0);
});

test('validly signed but wrong OIDC source, subject, owner, audience, run or environment cannot read original context', async t => {
  for (const patch of [{ iss: 'https://untrusted.test' }, { aud: [managementWorkerAudience, 'other'] }, { sub: 'untrusted-subject' },
    { repository: 'outsider/fork' }, { repository_id: '11' }, { repository_owner_id: '99' }, { repository_owner: 'outsider' },
    { repository_visibility: 'public' }, { run_id: '31' }, { run_attempt: '2' }, { run_attempt: 1 }, { actor_id: '41' },
    { workflow_ref: 'mrnwebdesigns/mrn-code-qa-runner/.github/workflows/other.yml@refs/heads/main' },
    { workflow_sha: 'f'.repeat(40) }, { sha: 'f'.repeat(40) }, { ref: 'refs/heads/candidate' }, { ref_type: 'tag' },
    { event_name: 'pull_request' }, { environment: 'unprotected' }, { runner_environment: 'self-hosted' },
    { job_workflow_ref: 'unqualified/reusable' }, { job_workflow_sha: 'f'.repeat(40) }, { head_ref: 'fork' }]) {
    const f = fixture(t); Object.assign(f.claims, patch); await assert.rejects(f.call); assert.equal(f.staffChecks, 0);
  }
});

test('expired, premature or substituted tokens and caller-selected data cannot reach staff verification', async t => {
  for (const patch of [{ exp: 1800000000 }, { iat: 1800000001 }, { nbf: 1800000001 },
    { exp: 1800000301 }, { iat: 1799999999 }, { jti: '' }]) {
    const f = fixture(t); Object.assign(f.claims, patch); await assert.rejects(f.call); assert.equal(f.staffChecks, 0);
  }
  const f = fixture(t);
  for (const header of [undefined, 'Bearer staff-token', `Bearer ${(await f.token()).slice(0, -4)}abcd`])
    await assert.rejects(() => f.reader.read(header, f.pointer));
  for (const pointer of [{ ...f.pointer, email: f.actor.verifiedEmail }, { ...f.pointer, run_id: '30' },
    { ...f.pointer, context_sha256: 'f'.repeat(64) }, { ...f.pointer, dispatch_id: '00000000-0000-4000-8000-000000000001' }])
    await assert.rejects(() => f.call(pointer));
  assert.equal(f.staffChecks, 0);
});

test('fresh original staff denial and changes during verification withhold the signed dispatch record', async t => {
  for (const mutate of [f => f.access.recordPendingRevocation(f.job), f => { f.policy.policy_epoch++; },
    f => { f.trust.environment = 'changed'; }, f => { f.state.now += 5000; }, f => { f.state.mono = 5000; },
    f => { f.state.now--; }, () => { throw new Error('suspended'); }]) {
    const f = fixture(t); f.state.duringStaff = () => mutate(f); await assert.rejects(f.call);
    assert.equal(f.store.db.prepare("SELECT count(*) AS n FROM management_access_audit WHERE event='worker-context-read'").get().n, 0);
  }
  const f = fixture(t); f.state.duringStaff = () => { f.state.mono = 4999; return Promise.resolve(); };
  const audit = f.access.audit.bind(f.access); f.access.audit = (...args) => { audit(...args); f.state.mono = 5000; };
  await assert.rejects(f.call);
  assert.equal(f.store.db.prepare("SELECT count(*) AS n FROM management_access_audit WHERE event='worker-context-read'").get().n, 0);
});

test('GitHub public key acquisition is fixed, bounded, cacheable and never receives the workflow token', async t => {
  const requests = [];
  const keys = createManagementWorkerKeySet(async (url, options) => { requests.push({ url, options });
    return new Response(JSON.stringify({ keys: [jwk] }), { headers: { 'content-type': 'application/json' } }); });
  const f = fixture(t, keys); await f.call(); await f.call(); assert.equal(requests.length, 1);
  assert.equal(requests[0].url, 'https://token.actions.githubusercontent.com/.well-known/jwks');
  assert.equal(requests[0].options.redirect, 'error'); assert.equal(requests[0].options.headers.Authorization, undefined);
  for (const response of [() => new Response('', { status: 302, headers: { location: 'https://untrusted.test' } }),
    () => new Response('x'.repeat(65537), { headers: { 'content-type': 'application/json' } }),
    () => new Response('{}', { headers: { 'content-type': 'application/json', 'content-encoding': 'gzip' } })]) {
    const bad = fixture(t, createManagementWorkerKeySet(async () => response())); await assert.rejects(bad.call, { code: 'MANAGEMENT_WORKER_AUTH' });
  }
});

test('real private HTTP readback requires workflow authorization and leaves the endpoint absent by default', async t => {
  const f = fixture(t), server = createHttpServer({ publicUrl: 'https://operations.mrnwebdesigns.com/mcp', managementWorker: f.reader });
  await new Promise(resolve => server.listen(0, '127.0.0.1', resolve)); t.after(() => server.close());
  const post = (target, header, body = delegationCanonical(f.pointer)) => new Promise((resolve, reject) => {
    const req = httpRequest({ host: '127.0.0.1', port: target.address().port, path: managementWorkerPath, method: 'POST', agent: false,
      headers: { host: 'operations.mrnwebdesigns.com', 'content-type': 'application/json', ...(header ? { authorization: header } : {}) } }, res => {
      const chunks = []; res.on('data', c => chunks.push(c)); res.on('end', () => resolve({ status: res.statusCode, headers: res.headers, body: JSON.parse(Buffer.concat(chunks)) }));
    }); req.on('error', reject); req.end(body);
  });
  const denied = await post(server); assert.equal(denied.status, 401); assert.equal(denied.headers['www-authenticate'], 'Bearer realm="MRN protected QA"');
  const token = `Bearer ${await f.token()}`;
  assert.equal((await post(server, token, ` ${delegationCanonical(f.pointer)}`)).status, 400);
  const accepted = await post(server, token); assert.equal(accepted.status, 200); assert.ok(accepted.body.signed_context);
  const disabled = createHttpServer({ publicUrl: 'https://operations.mrnwebdesigns.com/mcp' });
  await new Promise(resolve => disabled.listen(0, '127.0.0.1', resolve)); t.after(() => disabled.close());
  assert.equal((await post(disabled, token)).status, 404);
});
