import test from 'node:test';
import assert from 'node:assert/strict';
import { randomUUID } from 'node:crypto';
import { setup } from './helpers.mjs';
import { response } from './fixtures/mainwp.mjs';
import { CodeWorkflow } from '../src/code-workflow.mjs';
import { toolNames } from '../src/mainwp.mjs';
import { digest } from '../src/contracts.mjs';

const person = { subject: 'google-oauth2|123456', googleSubject: '123456', identityProvider: 'google-oauth2', verifiedEmail: 'staff@mrnwebdesigns.com', expiresAt: Date.now() + 3600000, scopes: ['mrn:operations', 'mrn:operations:write'] };
const path = 'wp-content/themes/fixture/file.php';
function configured(t) {
  const f = setup(); t.after(f.close);
  f.policy.members.push({ subject: person.subject, enabled: true, grants: [{ website: 'example', environments: ['development'], actions: ['read', 'code'] }] });
  const state = { content: '<?php // original\n', prepared: new Map(), applications: 0, qa: 0, qaKinds: [], revoked: false };
  f.service.verifyStaff = async () => { if (state.revoked) throw new Error('revoked'); };
  const call = f.client.callTool.bind(f.client);
  f.client.callTool = async request => {
    const { name, arguments: args } = request;
    if (name === toolNames.codeInspect) return response({ success: true, exists: true, sha256: digest(state.content), bytes: Buffer.byteLength(state.content), content_base64: Buffer.from(state.content).toString('base64'), code_storage: { backup_ready: true, atomic_ready: true } });
    if (name === toolNames.codePrepare) {
      const id = randomUUID(); const newHash = args.kind === 'rollback' ? state.prepared.get(args.rollback_operation_id).beforeHash : args.new_sha256;
      const plan = { operation_id: id, digest: digest(args), site_id: 1, site_url: f.state.url + '/', path, before_sha256: args.expected_sha256,
        new_sha256: args.new_sha256 || null, kind: args.kind, qa_evidence_sha256: args.qa_evidence_sha256, prepared_by: person.verifiedEmail,
        expires_at: Math.floor(Date.now() / 1000) + 600, qa: { new_sha256: newHash } };
      state.prepared.set(id, { plan, args, beforeContent: state.content, beforeHash: digest(state.content) }); return response(plan);
    }
    if (name === toolNames.codeApprove) return response({ operation_id: args.operation_id, digest: args.reviewed_digest, approved_by: person.verifiedEmail });
    if (name === toolNames.codeExecute) {
      assert.equal(f.state.backups > 0, true); assert.equal(args.backup_receipt.receipt, 'fixture-private-backup-receipt');
      const saved = state.prepared.get(args.operation_id); assert.equal(saved.plan.digest, args.reviewed_digest);
      state.content = saved.args.kind === 'rollback' ? state.prepared.get(saved.args.rollback_operation_id).beforeContent : Buffer.from(saved.args.content_base64, 'base64').toString();
      state.applications++;
      if (state.lostResponse) throw new Error('timed out after apply');
      return response({ operation_id: args.operation_id, state: 'completed', data: { success: true, sha256: digest(state.content) } });
    }
    return call(request);
  };
  const qa = { policy: { profiles: [{ siteUrl: f.state.url + '/', paths: [path], runtimeUrl: 'https://runtime.invalid', source: { mode: 'unversioned' } }] },
    dispatch: async request => { state.qa++; state.qaKinds.push(request.kind); return { runId: state.qa, url: 'https://github.com/mrnwebdesigns/qa/actions/runs/1' }; },
    receipt: async (_ticket, request) => {
      if (state.failQa) throw new Error('QA failed');
      return { status: 'qualified', envelope: { fixture: true }, claims: { qa_report_sha256: 'e'.repeat(64), expires_at: Math.floor(Date.now() / 1000) + 600, new_sha256: request.new_sha256 } };
    } };
  f.service.codeWorkflow = new CodeWorkflow(f.service, { qa, delay: async () => {} });
  return { ...f, codeState: state };
}
async function prepared(f, key = 'code-one') {
  const op = await f.service.prepareCode(person, { website: 'example', path, expectedSha256: digest(f.codeState.content), content: '<?php // changed\n', reason: 'Fixture acceptance', requestKey: key });
  await f.service.jobs.get(op.id); return f.service.get(person, op.id);
}
async function apply(f, op) {
  await f.service.approve(person, { operationId: op.id, planDigest: op.planDigest });
  await f.service.execute(person, { operationId: op.id }); await f.service.jobs.get(op.id); return f.service.get(person, op.id);
}

test('snapshot code path qualifies, previews, approves, backs up, verifies and separately rolls back exact bytes', async t => {
  const f = configured(t); const original = f.codeState.content;
  const op = await prepared(f); assert.equal(op.status, 'prepared'); assert.equal(op.preview.before, original);
  assert.equal(f.codeState.applications, 0); assert.equal(JSON.stringify(op).includes('qa_receipt'), false);
  const finished = await apply(f, op); assert.equal(finished.status, 'verified'); assert.equal(finished.result.backupVerified, true);
  assert.equal(f.codeState.applications, 1); assert.equal(f.codeState.content, '<?php // changed\n');
  const rollback = await f.service.prepareRollback(person, { operationId: op.id, requestKey: 'rollback-code' });
  await f.service.jobs.get(rollback.id);
  const rolled = await apply(f, f.service.get(person, rollback.id)); assert.equal(rolled.status, 'verified');
  assert.equal(f.codeState.content, original); assert.equal(f.state.backups, 2); assert.equal(f.codeState.qa, 2);
});

test('failed QA, stale live state and missing approval never dispatch code', async t => {
  const f = configured(t); f.codeState.failQa = true;
  const blocked = await prepared(f); assert.equal(blocked.status, 'blocked'); assert.equal(f.codeState.prepared.size, 0);
  f.codeState.failQa = false; const op = await prepared(f, 'code-two');
  await assert.rejects(() => f.service.execute(person, { operationId: op.id }), { code: 'APPROVAL_REQUIRED' });
  await f.service.approve(person, { operationId: op.id, planDigest: op.planDigest }); f.codeState.content += '// drift\n';
  await f.service.execute(person, { operationId: op.id }); await f.service.jobs.get(op.id);
  assert.equal(f.service.get(person, op.id).status, 'failed'); assert.equal(f.codeState.applications, 0); assert.equal(f.state.backups, 0);
});

test('lost code response keeps the durable lock and rejects Fleet reconciliation and redispatch', async t => {
  const f = configured(t); const op = await prepared(f); f.codeState.lostResponse = true;
  const result = await apply(f, op); assert.equal(result.status, 'uncertain'); assert.equal(f.codeState.applications, 1);
  await f.service.execute(person, { operationId: op.id }); assert.equal(f.codeState.applications, 1);
  await assert.rejects(() => f.service.reconcile(person, { operationId: op.id }), { code: 'RECOVERY_UNAVAILABLE' });
  assert.equal(f.store.db.prepare('SELECT count(*) AS n FROM locks').get().n, 1);
});

test('revocation and unqualified sites stop code before QA or source work', async t => {
  const f = configured(t); f.codeState.revoked = true;
  await assert.rejects(() => prepared(f), /revoked/); assert.equal(f.codeState.qa, 0);
  f.codeState.revoked = false; f.service.codeWorkflow.qa.policy.profiles[0].siteUrl = 'https://other.test/';
  await assert.rejects(() => prepared(f), { code: 'QA_PROFILE_UNQUALIFIED' }); assert.equal(f.codeState.qa, 0);
});

test('Git source approval is separate from live approval and unqualified source automation is blocked', async t => {
  const f = configured(t); const wf = f.service.codeWorkflow; let proposals = 0; let merges = 0;
  const source = { mode: 'git', repository: 'https://github.com/mrnwebdesigns/example', branch: 'main', paths: { 'wp-content/themes/fixture/': 'theme/' } };
  wf.qa.policy.profiles[0].source = source;
  wf.source = {
    propose: async input => { assert.equal(input.reviewProof.status, 'qualified'); assert.equal(f.codeState.qa, 1); proposals++; return { repository: 'mrnwebdesigns/example', path: 'theme/file.php', branch: 'main', head: 'c'.repeat(40), number: 1, url: 'https://github.com/mrnwebdesigns/example/pull/1' }; },
    merge: async () => { merges++; return { mode: 'git', repository: source.repository, branch: 'main', commit: 'd'.repeat(40) }; },
    merged: async () => ({ mode: 'git', repository: source.repository, branch: 'main', commit: 'd'.repeat(40) }),
  };
  const blocked = await prepared(f, 'source-blocked'); assert.equal(blocked.status, 'blocked'); assert.equal(proposals, 0);
  wf.qa.policy.profiles[0].sourceAutomation = { deploymentDisabled: true, baseCommit: 'a'.repeat(40), expiresAt: Date.now() + 3600000, evidenceRef: 'fixture-proof' };
  const op = await prepared(f, 'source-ready'); assert.equal(op.status, 'source_review'); assert.equal(proposals, 1); assert.equal(f.codeState.qa, 1);
  await assert.rejects(() => f.service.approveCodeSource(person, { operationId: op.id, planDigest: '0'.repeat(64) }), { code: 'PLAN_CHANGED' });
  assert.equal(merges, 0);
  await f.service.approveCodeSource(person, { operationId: op.id, planDigest: op.planDigest }); await f.service.jobs.get(op.id);
  const qualified = f.service.get(person, op.id); assert.equal(qualified.status, 'prepared'); assert.equal(merges, 1); assert.equal(f.codeState.qa, 2);
  assert.deepEqual(f.codeState.qaKinds, ['source_review', 'file']);
  assert.notEqual(qualified.planDigest, op.planDigest); assert.equal(f.codeState.applications, 0);
  await assert.rejects(() => f.service.execute(person, { operationId: op.id }), { code: 'APPROVAL_REQUIRED' });
  assert.equal((await apply(f, qualified)).status, 'verified');
});

test('failed preliminary QA or disabled writes cannot publish a Git source PR', async t => {
  const f = configured(t); const wf = f.service.codeWorkflow; let proposals = 0;
  wf.qa.policy.profiles[0].source = { mode: 'git', repository: 'https://github.com/mrnwebdesigns/example', branch: 'main', paths: { 'wp-content/themes/fixture/': 'theme/' } };
  wf.qa.policy.profiles[0].sourceAutomation = { deploymentDisabled: true, baseCommit: 'a'.repeat(40), expiresAt: Date.now() + 3600000, evidenceRef: 'fixture-proof' };
  wf.source = { propose: async () => { proposals++; } };
  f.codeState.failQa = true;
  assert.equal((await prepared(f, 'qa-fails')).status, 'blocked');
  f.codeState.failQa = false; f.service.writesEnabled = false;
  assert.equal((await prepared(f, 'writes-off')).error.code, 'WRITES_DISABLED');
  assert.equal(proposals, 0); assert.equal(f.codeState.prepared.size, 0);
});
