import test from 'node:test';
import assert from 'node:assert/strict';
import { setImmediate } from 'node:timers';
import { setup } from './helpers.mjs';
import { response } from './fixtures/mainwp.mjs';
import { contentQaFixture, makePlan, original, target, profile } from './fixtures/content.mjs';
import { ContentWorkflow } from '../src/content-workflow.mjs';
import { toolNames } from '../src/mainwp.mjs';
import { delegationDigest } from '../src/delegation.mjs';
import { responseEvidence } from '../src/probe.mjs';

const person = { subject: 'google-oauth2|123456', googleSubject: '123456', identityProvider: 'google-oauth2', verifiedEmail: 'staff@mrnwebdesigns.com', expiresAt: Date.now() + 3600000, scopes: ['mrn:operations', 'mrn:operations:write'] };
function configured(t) {
  const f = setup(); t.after(f.close); const q = contentQaFixture();
  f.policy.members.push({ subject: person.subject, enabled: true, grants: [{ website: 'example', environments: ['development'], actions: ['read', 'content'] }] });
  const state = { snapshot: structuredClone(original), prepared: new Map(), qa: 0, applied: 0, approvals: 0, revoked: false, ownership: 'wordpress-post' };
  f.service.verifyStaff = async () => { if (state.revoked) throw new Error('revoked'); };
  const call = f.client.callTool.bind(f.client);
  f.client.callTool = async request => {
    const { name, arguments: args } = request;
    if (name === toolNames.contentPolicy) return response({ site_id: 1, site_url: f.state.url, enabled: true, profile,
      qa_keys: [{ id: q.policy.keyId, public_key_base64: q.policy.publicKeyBase64 }], maximum_value_bytes: 8192, maximum_snapshot_bytes: 32768,
      policy_sha256: '9'.repeat(64), supported_target_kinds: ['post'] });
    if (name === toolNames.contentInspect) return response({ snapshot: structuredClone(state.snapshot), snapshot_sha256: delegationDigest(state.snapshot), ownership: state.ownership,
      affected_paths: ['/sample-page/'], stable_selector: { post_type: 'page', post_path: 'sample-page' } });
    if ([toolNames.contentPrepare, toolNames.contentRollback].includes(name)) {
      const previous = name === toolNames.contentRollback ? state.prepared.get(args.operation_id) : null;
      const value = previous ? previous.plan.before_snapshot[target.field] : args.new_value;
      const proposal = makePlan(structuredClone(state.snapshot), value, previous?.plan.operation_id || '');
      state.prepared.set(proposal.plan.operation_id, proposal); return response(proposal.plan);
    }
    if (name === toolNames.contentMigration) return response(state.prepared.get(args.operation_id).migration);
    if (name === toolNames.contentApprove) {
      state.approvals++; await state.approvalWait;
      const p = state.prepared.get(args.operation_id).plan;
      return response({ operation_id: p.operation_id, digest: p.digest, approved_by: person.verifiedEmail, expires_at: p.expires_at });
    }
    if (name === toolNames.contentExecute) {
      assert.equal(f.state.backups > 0, true); assert.equal(args.backup_receipt.receipt, 'fixture-private-backup-receipt');
      const p = state.prepared.get(args.operation_id).plan; assert.equal(args.reviewed_digest, p.digest);
      state.snapshot = structuredClone(p.candidate_snapshot); state.applied++;
      if (state.lostResponse) throw new Error('timed out after applying');
      return response({ operation_id: p.operation_id, state: 'completed', site_id: p.site_id, site_url: p.site_url, target: p.target,
        before_sha256: p.before_sha256, candidate_sha256: p.candidate_sha256, current_sha256: p.candidate_sha256, changed: true,
        migration_artifact: p.migration_artifact, affected_paths: p.affected_paths, rollback_available: true, error_code: null });
    }
    const result = await call(request);
    if (name === toolNames.backupStatus && state.afterBackup) state.afterBackup();
    return result;
  };
  q.qa.dispatch = async request => {
    q.qa.qualifyRequest(request); state.qa++; return { runId: state.qa, url: `https://github.com/mrnwebdesigns/mrn-code-qa-runner/actions/runs/${state.qa}` };
  };
  q.qa.receipt = async (ticket, request) => {
    if (state.failQa) throw new Error('QA failed');
    const claims = q.claims(request, ticket.runId), envelope = q.envelope(claims);
    return { status: 'qualified', ...q.qa.verifyReceipt(envelope, request, ticket.runId) };
  };
  const publicProbe = f.service.publicProbe;
  f.service.publicProbe = async (url, authorize, options) => {
    if (!options?.contentAssertions) return publicProbe(url, authorize, options);
    authorize(); const value = state.staleFrontend ? original.post_content : state.snapshot.post_content;
    return responseEvidence(url, 200, Buffer.from(`<html lang="en"><head><title>Sample</title></head><body><main><div class="entry-content">${value}</div></main></body></html>`), {}, options);
  };
  f.service.contentWorkflow = new ContentWorkflow(f.service, { qa: q.qa, delay: async () => {} });
  return { ...f, contentState: state, q };
}
async function prepared(f, requestKey = 'content-one') {
  const op = await f.service.prepareContent(person, { website: 'example', target, expectedSnapshotSha256: delegationDigest(f.contentState.snapshot),
    newValue: '<p>Updated &amp; safe.</p>', reason: 'Content fixture acceptance', requestKey });
  await f.service.jobs.get(op.id); return f.service.get(person, op.id);
}
async function execute(f, op) {
  await f.service.approve(person, { operationId: op.id, planDigest: op.planDigest });
  await f.service.execute(person, { operationId: op.id }); await f.service.jobs.get(op.id); return f.service.get(person, op.id);
}

test('content preview, approved edit and separate rollback verify snapshots and exact rendered text', async t => {
  const f = configured(t), op = await prepared(f);
  assert.equal(op.status, 'prepared'); assert.equal(op.preview.before, original.post_content); assert.equal(f.contentState.applied, 0);
  assert.equal(JSON.stringify(op).includes('signature_base64'), false); assert.equal(JSON.stringify(op).includes('before_snapshot'), false);
  const result = await execute(f, op); assert.equal(result.status, 'verified'); assert.equal(result.result.publicHtmlContentVerified, true);
  assert.equal(result.result.liveBrowserVerified, false); assert.equal(result.result.publicVerificationMethod, 'exact-selector-html-text'); assert.equal(f.contentState.applied, 1);
  const rollback = await f.service.prepareRollback(person, { operationId: op.id, requestKey: 'content-rollback' }); await f.service.jobs.get(rollback.id);
  const rolled = await execute(f, f.service.get(person, rollback.id));
  assert.equal(rolled.status, 'verified'); assert.deepEqual(f.contentState.snapshot, original); assert.equal(f.state.backups, 2); assert.equal(f.contentState.qa, 2);
});

test('failed content QA, unknown ownership and live drift never dispatch a write', async t => {
  const f = configured(t); f.contentState.failQa = true;
  assert.equal((await prepared(f)).status, 'blocked'); f.contentState.failQa = false; f.contentState.ownership = 'builder';
  assert.equal((await prepared(f, 'builder')).status, 'blocked'); f.contentState.ownership = 'wordpress-post'; const op = await prepared(f, 'fresh');
  await assert.rejects(() => f.service.execute(person, { operationId: op.id }), { code: 'APPROVAL_REQUIRED' });
  await f.service.approve(person, { operationId: op.id, planDigest: op.planDigest }); f.contentState.snapshot.post_title = 'Changed concurrently';
  await f.service.execute(person, { operationId: op.id }); await f.service.jobs.get(op.id);
  assert.equal(f.service.get(person, op.id).status, 'failed'); assert.equal(f.contentState.applied, 0); assert.equal(f.state.backups, 0);
});

test('revocation or drift during backup blocks content dispatch and retains the uncertain lock', async t => {
  for (const change of ['revoke', 'drift']) await t.test(change, async t => {
    const f = configured(t), op = await prepared(f);
    f.contentState.afterBackup = () => { if (change === 'revoke') f.contentState.revoked = true; else f.contentState.snapshot.post_title = 'New title'; };
    const result = await execute(f, op); assert.equal(result.status, 'uncertain'); assert.equal(f.contentState.applied, 0);
    f.store.assertLocked(f.store.get(op.id));
  });
});

test('lost response and stale frontend retain content locks without retry or Fleet reconciliation', async t => {
  for (const flag of ['lostResponse', 'staleFrontend']) await t.test(flag, async t => {
    const f = configured(t), op = await prepared(f); f.contentState[flag] = true;
    const result = await execute(f, op); assert.equal(result.status, 'uncertain'); assert.equal(f.contentState.applied, 1); f.store.assertLocked(f.store.get(op.id));
    await f.service.execute(person, { operationId: op.id }); assert.equal(f.contentState.applied, 1);
    await assert.rejects(() => f.service.reconcile(person, { operationId: op.id }), { code: 'RECOVERY_UNAVAILABLE' });
  });
});

test('parallel approvals claim one content provider approval and cross-operation calls fail', async t => {
  const f = configured(t), op = await prepared(f); let release;
  f.contentState.approvalWait = new Promise(resolve => { release = resolve; });
  const first = f.service.approve(person, { operationId: op.id, planDigest: op.planDigest });
  while (!f.contentState.approvals) await new Promise(resolve => setImmediate(resolve));
  await assert.rejects(() => f.service.approve(person, { operationId: op.id, planDigest: op.planDigest }), { code: 'OPERATION_ACTIVE' });
  release(); await first; assert.equal(f.contentState.approvals, 1);
  const session = await f.service.session(person, f.registry.resolve(person, 'example'), 'content'); t.after(() => session.close()); await session.fresh();
  await assert.rejects(() => session.call(toolNames.contentExecute, { operation_id: 'caller-chosen', reviewed_digest: '0'.repeat(64) }), { code: 'TARGET_MISMATCH' });
});
