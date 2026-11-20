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
import { contentContract, contentPolicyContract, contentSchemas } from '../src/content-contract.mjs';

const person = { subject: 'google-oauth2|123456', googleSubject: '123456', identityProvider: 'google-oauth2', verifiedEmail: 'staff@mrnwebdesigns.com', expiresAt: Date.now() + 3600000, scopes: ['mrn:operations', 'mrn:operations:write'] };
function configured(t) {
  const f = setup(); t.after(f.close); const q = contentQaFixture();
  f.policy.members.push({ subject: person.subject, enabled: true, grants: [{ website: 'example', environments: ['development'], actions: ['read', 'content'] }] });
  const state = { snapshot: structuredClone(original), prepared: new Map(), qa: 0, applied: 0, approvals: 0, revoked: false, ownership: 'wordpress-post', policyRequests: [] };
  f.service.verifyStaff = async () => { if (state.revoked) throw new Error('revoked'); };
  const call = f.client.callTool.bind(f.client);
  f.client.callTool = async request => {
    const { name, arguments: args } = request;
    if ([toolNames.contentPolicy, toolNames.contentTargetPolicy].includes(name)) {
      state.policyRequests.push({ name, args });
      if (state.policyError) return { isError: true, ...response({ error: { code: state.policyError, message: 'private-sentinel' } }) };
      const selected = state.policyProfiles && args.target ? state.policyProfiles[`${args.target.post_id}:${args.target.field}`] ?? null : profile;
      const result = { site_id: 1, site_url: f.state.url, enabled: true, profile: selected,
      qa_keys: [{ id: q.policy.keyId, public_key_base64: q.policy.publicKeyBase64 }], maximum_value_bytes: 8192, maximum_snapshot_bytes: 32768,
      policy_sha256: '9'.repeat(64), supported_target_kinds: ['post'] };
      if (state.malformedPolicy) delete result.enabled;
      return response(result);
    }
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
        migration_artifact: p.migration_artifact, affected_paths: p.affected_paths,
        rollback_available: p.rollback_operation_id === '', error_code: '', ...state.executionResult });
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
  assert.equal(result.result.rollbackAvailable, true);
  assert.equal(result.result.liveBrowserVerified, false); assert.equal(result.result.publicVerificationMethod, 'exact-selector-html-text'); assert.equal(f.contentState.applied, 1);
  const rollback = await f.service.prepareRollback(person, { operationId: op.id, requestKey: 'content-rollback' }); await f.service.jobs.get(rollback.id);
  const rolled = await execute(f, f.service.get(person, rollback.id));
  assert.equal(rolled.status, 'verified'); assert.deepEqual(f.contentState.snapshot, original); assert.equal(f.state.backups, 2); assert.equal(f.contentState.qa, 2);
  assert.equal(rolled.result.rollbackAvailable, false);
  await assert.rejects(() => f.service.prepareRollback(person, { operationId: rolled.id, requestKey: 'reverse-again' }), { code: 'ROLLBACK_UNAVAILABLE' });
});

test('the nullable success error code remains compatible with the frozen content contract', async t => {
  const f = configured(t), op = await prepared(f); f.contentState.executionResult = { error_code: null };
  assert.equal((await execute(f, op)).status, 'verified');
});

test('failed, inconsistent or uncertain provider outcomes retain the lock and never retry the content mutation', async t => {
  for (const result of [{ error_code: 'mrn_content_uncertain' }, { error_code: 'unexpected-error' }, { error_code: ' ' },
    { state: 'uncertain' }, { state: 'failed' }, { current_sha256: '0'.repeat(64) }, { rollback_available: false }]) {
    const f = configured(t), op = await prepared(f); f.contentState.executionResult = result;
    const completed = await execute(f, op);
    assert.equal(completed.status, 'uncertain'); assert.equal(completed.error.code, 'CONTENT_OUTCOME_UNVERIFIED');
    assert.equal(f.contentState.applied, 1); f.store.assertLocked(f.store.get(op.id));
    await f.service.execute(person, { operationId: op.id }); assert.equal(f.contentState.applied, 1);
  }
});

test('a reverse plan cannot claim another rollback is available', async t => {
  const f = configured(t), op = await prepared(f); await execute(f, op);
  const rollback = await f.service.prepareRollback(person, { operationId: op.id, requestKey: 'reverse-flag' });
  await f.service.jobs.get(rollback.id); f.contentState.executionResult = { rollback_available: true };
  const reversed = await execute(f, f.service.get(person, rollback.id));
  assert.equal(reversed.status, 'uncertain'); f.store.assertLocked(f.store.get(rollback.id));
  assert.equal(f.contentState.applied, 2);
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

test('target-aware lookup preserves the closed v1 output and requires the entire exact target', () => {
  assert.deepEqual(contentPolicyContract.output, contentContract.abilities['get-content-policy-v1'].output);
  const input = { site_id: 1, site_url: original.site_url, target };
  assert.deepEqual(contentSchemas['get-content-policy-v2'].input.parse(input), input);
  for (const bad of [{ site_id: 1, site_url: original.site_url }, { ...input, email: 'caller@mrnwebdesigns.com' },
    { ...input, target: { ...target, post_id: 0 } }, { ...input, target: { ...target, kind: 'acf' } }]) {
    assert.throws(() => contentSchemas['get-content-policy-v2'].input.parse(bad));
  }
});

test('v2 selects separate exact record/field policies on one site; v1 is used only when absent', async t => {
  const f = configured(t);
  await f.service.inspectContent(person, { website: 'example', target });
  assert.deepEqual(f.contentState.policyRequests[0], { name: toolNames.contentPolicy, args: { site_id: 1, site_url: original.site_url } });
  f.state.contentTargetPolicy = true; f.contentState.policyRequests = [];
  const another = { ...target, post_id: 77, field: 'post_title' }, alternate = { ...profile, id: 'second-record', sha256: '2'.repeat(64) };
  f.contentState.policyProfiles = { [`${target.post_id}:${target.field}`]: profile, [`${another.post_id}:${another.field}`]: alternate };
  const first = await f.service.inspectContent(person, { website: 'example', target }); assert.deepEqual(first.policy.profile, profile);
  f.contentState.snapshot.target = another;
  const second = await f.service.inspectContent(person, { website: 'example', target: another }); assert.deepEqual(second.policy.profile, alternate);
  assert.deepEqual(f.contentState.policyRequests, [target, another].map(selected => ({ name: toolNames.contentTargetPolicy,
    args: { site_id: 1, site_url: original.site_url, target: selected } })));
  assert.equal(f.contentState.applied, 0); assert.equal(f.state.backups, 0);
});

test('v2 denial, malformed output and missing qualification never downgrade to the legacy lookup', async t => {
  for (const mode of ['denied', 'malformed', 'unqualified']) {
    const f = configured(t); f.state.contentTargetPolicy = true;
    if (mode === 'denied') f.contentState.policyError = 'mrn_content_profile';
    if (mode === 'malformed') f.contentState.malformedPolicy = true;
    if (mode === 'unqualified') f.contentState.policyProfiles = {};
    const op = await prepared(f, mode); assert.equal(op.status, 'blocked');
    assert.deepEqual(f.contentState.policyRequests.map(p => p.name), [toolNames.contentTargetPolicy]);
    assert.equal(f.contentState.prepared.size, 0); assert.equal(f.contentState.qa, 0); assert.equal(f.state.backups, 0);
  }
});

test('v2 qualification cannot substitute an untrusted record profile during preparation', async t => {
  const f = configured(t); f.state.contentTargetPolicy = true;
  f.contentState.policyProfiles = { [`${target.post_id}:${target.field}`]: { ...profile, id: 'wrong-record', sha256: '2'.repeat(64) } };
  const op = await prepared(f); assert.equal(op.status, 'blocked'); assert.equal(op.error.code, 'CONTENT_PROFILE_UNQUALIFIED');
  assert.equal(f.contentState.prepared.size, 0); assert.equal(f.contentState.qa, 0);
});

test('target-aware policy supports the unchanged content edit and rollback lifecycle', async t => {
  const f = configured(t); f.state.contentTargetPolicy = true;
  const op = await prepared(f); assert.equal(op.status, 'prepared'); assert.equal((await execute(f, op)).status, 'verified');
  const rollback = await f.service.prepareRollback(person, { operationId: op.id, requestKey: 'v2-rollback' }); await f.service.jobs.get(rollback.id);
  assert.equal((await execute(f, f.service.get(person, rollback.id))).status, 'verified');
  assert.deepEqual(f.contentState.snapshot, original);
  assert.ok(f.contentState.policyRequests.every(p => p.name === toolNames.contentTargetPolicy && p.args.target.post_id === target.post_id && p.args.target.field === target.field));
});
