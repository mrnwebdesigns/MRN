import test from 'node:test';
import assert from 'node:assert/strict';
import { generateKeyPairSync, sign } from 'node:crypto';
import { ProtectedCodeQa, verifyCodeReceipt } from '../src/code-qa.mjs';
import { digest } from '../src/contracts.mjs';

const keys = generateKeyPairSync('ed25519'); const bytes = Buffer.from('<?php // fixture\n');
const request = { schema: 1, request_id: 'a'.repeat(32), site_id: 1, site_url: 'https://fixture.test/', kind: 'file', path: 'wp-content/themes/fixture/file.php',
  repository_path: 'wp-content/themes/fixture/file.php', expected_sha256: 'b'.repeat(64), new_sha256: digest(bytes), rollback_operation_id: '', runtime_url: 'https://runtime.invalid', source: { mode: 'unversioned' }, payload_base64: bytes.toString('base64') };
const policy = { repository: 'mrnwebdesigns/mrn-code-qa-runner', workflowCommit: 'c'.repeat(40), actor: 'test-app[bot]', keyId: 'test-only',
  publicKeyBase64: keys.publicKey.export({ format: 'der', type: 'spki' }).subarray(-32).toString('base64'), engineCommit: 'd'.repeat(40),
  profiles: [{ siteUrl: request.site_url, paths: [request.path], runtimeUrl: request.runtime_url, source: { mode: 'unversioned' } }] };
const now = Math.floor(Date.now() / 1000);
const claims = { schema: 1, issuer: 'mrn-qa-engine', audience: 'https://wpcontrol.mrndev.io', result: 'Pass', mode: 'release', smoke_strict: true,
  issued_at: now, expires_at: now + 900, receipt_id: digest(`7:1:${request.request_id}`).slice(0, 32), site_id: request.site_id, site_url: request.site_url, kind: request.kind,
  path: request.path, expected_sha256: request.expected_sha256, new_sha256: request.new_sha256, rollback_operation_id: '', qa_report_sha256: 'e'.repeat(64), engine_commit: policy.engineCommit,
  runtime_url: request.runtime_url, runtime_sha256: request.new_sha256, source: request.source };
const envelope = value => {
  const payload = Buffer.from(JSON.stringify(value));
  return { key_id: policy.keyId, payload_base64: payload.toString('base64'), signature_base64: sign(null, Buffer.concat([Buffer.from('mrn-code-qa-v1:'), payload]), keys.privateKey).toString('base64') };
};

test('signed QA must bind exact site/path/bytes/source/runtime/run and fresh strict QA', () => {
  assert.equal(verifyCodeReceipt(envelope(claims), request, policy, 7).claims.qa_report_sha256, claims.qa_report_sha256);
  for (const change of [{ site_id: 2 }, { path: request.path + '.bak' }, { expected_sha256: '0'.repeat(64) }, { new_sha256: '0'.repeat(64) },
    { runtime_url: 'https://other.invalid' }, { expires_at: now - 1 }, { source: { mode: 'git', repository: 'https://github.com/mrnwebdesigns/other', branch: 'main', commit: 'f'.repeat(40), repository_path: 'file.php', clean: true, remote_verified: true } },
    { receipt_id: '0'.repeat(32) }, { engine_commit: '0'.repeat(40) }, { result: 'Fail' }, { smoke_strict: false }]) {
    assert.throws(() => verifyCodeReceipt(envelope({ ...claims, ...change }), request, policy, 7));
  }
  const tampered = envelope(claims); tampered.payload_base64 = Buffer.from(JSON.stringify({ ...claims, site_id: 2 })).toString('base64');
  assert.throws(() => verifyCodeReceipt(tampered, request, policy, 7), { code: 'QA_SIGNATURE' });
});

test('company/snapshot qualification never grants other sites or downgrades Git ownership', () => {
  const qa = new ProtectedCodeQa(null, policy);
  assert.equal(qa.qualifyRequest(request).site_id, 1);
  assert.throws(() => qa.qualifyRequest({ ...request, site_url: 'https://client.test/' }), { code: 'QA_PROFILE_UNQUALIFIED' });
  assert.throws(() => qa.qualifyRequest({ ...request, path: 'wp-content/themes/fixture/other.php' }), { code: 'QA_PROFILE_UNQUALIFIED' });
  assert.throws(() => qa.qualifyRequest({ ...request, payload_base64: Buffer.from('different').toString('base64') }), { code: 'QA_PAYLOAD' });
  const gitPolicy = { ...policy, profiles: [{ ...policy.profiles[0], source: { mode: 'git', repository: 'https://github.com/mrnwebdesigns/example', branch: 'main', paths: { 'wp-content/themes/fixture/': 'theme/' } } }] };
  assert.throws(() => new ProtectedCodeQa(null, gitPolicy).qualifyRequest(request), { code: 'QA_PROFILE_MISMATCH' });
});

test('QA dispatch persists intent and correlates the exact run without retry', async () => {
  const calls = []; let checkpoint; let fail = false;
  const api = async (path, method = 'GET', body) => {
    calls.push({ path, method, body });
    if (path === '/git/ref/heads/main') return { ref: 'refs/heads/main', object: { type: 'commit', sha: policy.workflowCommit } };
    if (path === '/actions/workflows/code-qa.yml') return { id: 99, path: '.github/workflows/code-qa.yml', state: 'active' };
    assert.equal(path, '/actions/workflows/code-qa.yml/dispatches'); assert.equal(checkpoint.status, 'qa_dispatching');
    if (fail) throw new Error('lost response');
    return { workflow_run_id: 7, html_url: `https://github.com/${policy.repository}/actions/runs/7` };
  };
  const qa = new ProtectedCodeQa({ scoped: async () => api }, policy);
  const ticket = await qa.dispatch(request, async () => {}, value => { checkpoint = value; });
  assert.equal(ticket.runId, 7);
  assert.deepEqual(JSON.parse(Buffer.from(calls.at(-1).body.inputs.request, 'base64')), request);
  fail = true; const before = calls.length;
  await assert.rejects(() => qa.dispatch(request, async () => {}, () => {}), /lost response/);
  assert.equal(calls.slice(before).filter(c => c.method === 'POST').length, 1);
});

test('receipt availability requires exact successful workflow, app actor and immutable artifact', async () => {
  let changed = {};
  const run = { id: 7, run_attempt: 1, repository: { id: 55, full_name: policy.repository }, head_repository: { id: 55 }, event: 'workflow_dispatch', head_branch: 'main',
    head_sha: policy.workflowCommit, workflow_id: 99, path: '.github/workflows/code-qa.yml', display_title: `MRN QA ${request.request_id}`, actor: { login: policy.actor }, triggering_actor: { login: policy.actor }, status: 'completed', conclusion: 'success' };
  const qa = new ProtectedCodeQa({ repositories: { [policy.repository]: 55 }, scoped: async () => async path => path.endsWith('/runs/7') ? { ...run, ...changed }
    : { total_count: 1, artifacts: [{ id: 8, name: 'receipt-7-1', expired: false, size_in_bytes: 1234, workflow_run: { id: 7, head_sha: policy.workflowCommit } }] } }, policy);
  const ticket = { runId: 7, requestId: request.request_id, workflowCommit: policy.workflowCommit, workflowId: 99, url: 'https://github.com/example' };
  assert.equal((await qa.status(ticket, async () => {})).artifactId, 8);
  for (changed of [{ actor: { login: 'outsider' } }, { display_title: 'MRN QA other' }, { run_attempt: 2 }, { conclusion: 'failure' }, { head_sha: '0'.repeat(40) }])
    await assert.rejects(() => qa.status(ticket, async () => {}));
});
