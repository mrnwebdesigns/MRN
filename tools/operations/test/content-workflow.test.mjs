import test from 'node:test';
import assert from 'node:assert/strict';
import { setImmediate } from 'node:timers';
import { readFileSync } from 'node:fs';
import { randomUUID } from 'node:crypto';
import { setup } from './helpers.mjs';
import { response } from './fixtures/mainwp.mjs';
import { contentQaFixture, makePlan, original, target, profile } from './fixtures/content.mjs';
import { ContentWorkflow } from '../src/content-workflow.mjs';
import { toolNames } from '../src/mainwp.mjs';
import { delegationDigest } from '../src/delegation.mjs';
import { responseEvidence } from '../src/probe.mjs';
import { contentContract, contentPolicyContract, contentSchemas } from '../src/content-contract.mjs';
import { managementCandidate, managementCandidateHash } from '../src/content-management-contract.mjs';
import { acfScalar } from '../src/content.mjs';
import { digest } from '../src/contracts.mjs';

const person = { subject: 'google-oauth2|123456', googleSubject: '123456', identityProvider: 'google-oauth2', verifiedEmail: 'staff@mrnwebdesigns.com', expiresAt: Date.now() + 3600000, scopes: ['mrn:operations', 'mrn:operations:write'] };

// Unit transport/signature fixture only. These cases never claim native QA,
// remote backup qualification or production readiness.
function managed(t) {
  const f = setup(); t.after(f.close); const q = contentQaFixture();
  const vector = JSON.parse(readFileSync(new URL('./fixtures/content-management-v1.json', import.meta.url))).cases[0];
  const snapshot = structuredClone(vector.read.snapshot); snapshot.site_id = 1; snapshot.site_url = f.state.url;
  const leaf = ['field_rows', 0, 'field_body'], recordTarget = { kind: 'wordpress-content', post_type: 'page', post_id: snapshot.post_id };
  const providerSource = Object.fromEntries(['child/content-acf.php', 'child/content-fields.php', 'child/content-records.php',
    'includes/class-mrn-mainwp-content-management.php', 'includes/class-mrn-mainwp-content-source.php', 'release-source.json'].map(k => [k, 'a'.repeat(64)]));
  const hash = delegationDigest({ adapter: 'content-management-v1', runtime: snapshot.runtime, acf_definitions: snapshot.acf_definitions, source: providerSource });
  const descriptor = { sha256: hash, runtime_url: 'https://mrn-qa.internal:9443', runtime_context_sha256: hash };
  q.policy.profiles = [{ siteUrl: f.state.url, profile: descriptor, targets: [{ target: recordTarget, selector: '.unit-acf', acfPath: leaf, providerSource }] }];
  f.policy.members.push({ subject: person.subject, enabled: true, grants: [{ website: 'example', environments: ['development'], actions: ['read', 'content'] }] });
  f.state.contentManagement = true;
  const state = { snapshot, plans: new Map(), applied: 0, approvals: 0, reads: 0, qa: 0, revoked: false };
  const initial = structuredClone(snapshot), read = () => ({ success: true, snapshot: structuredClone(state.snapshot),
    snapshot_sha256: delegationDigest(state.snapshot), affected_paths: ['/sample-page/'] });
  const make = (preview, rollbackOf = '') => {
    const { expected_snapshot_sha256: before_sha256, idempotency_key: _key, reason, ...base } = preview;
    const request = { ...base, before_sha256 }, before = structuredClone(state.snapshot), candidate = managementCandidate(before, request), operation_id = randomUUID();
    const migration = { schema: 1, adapter: 'content-management-v1', operation_id, reason, request,
      candidate_snapshot: candidate, candidate_sha256: managementCandidateHash(candidate), rollback_snapshot: before };
    const plan = { site_id: 1, site_url: f.state.url, operation_id, target: recordTarget, before_snapshot: before, candidate_snapshot: candidate,
      before_sha256, candidate_sha256: migration.candidate_sha256, affected_paths: ['/sample-page/'], rollback_operation_id: rollbackOf,
      migration_artifact: { ref: `mrn-content://management/${operation_id}`, sha256: delegationDigest(migration) }, profile: descriptor,
      expires_at: Math.floor(Date.now() / 1000) + 900 };
    plan.digest = delegationDigest(plan); state.plans.set(operation_id, { plan, migration }); return plan;
  };
  f.service.verifyStaff = async () => { if (state.revoked) throw new Error('revoked unit member'); };
  const call = f.client.callTool.bind(f.client);
  f.client.callTool = async request => {
    const { name, arguments: args } = request;
    if (name === toolNames.contentRecord) { state.reads++; return response(read()); }
    if (name === toolNames.editPreview) return response(make(args));
    if (name === toolNames.editRollback) {
      const previous = state.plans.get(args.operation_id).plan;
      return response(make({ site_id: 1, site_url: f.state.url, post_type: 'page', post_id: recordTarget.post_id,
        expected_snapshot_sha256: args.expected_snapshot_sha256, idempotency_key: args.idempotency_key, reason: args.reason,
        acf_patches: [{ path: leaf, value: acfScalar(previous.before_snapshot, leaf) }] }, previous.operation_id));
    }
    if (name === toolNames.editMigration) return response(state.plans.get(args.operation_id).migration);
    const refusal = () => ({ isError: true, ...response({ error: { code: 'mrn_content_not_dispatched', message: 'Private provider refusal',
      ...(state.messageOnly ? {} : { data: { operation_id: state.wrongRefusalId ? randomUUID() : args.operation_id,
        state: 'not_dispatched', changed: false, new_plan_required: true, audit_persisted: !state.auditMissing } }) } }) });
    if (name === toolNames.editStatus && state.refuse) {
      if (state.statusUnavailable) throw new Error('Status unavailable');
      return refusal();
    }
    if (name === toolNames.editApprove) {
      state.approvals++; assert.equal(args.confirm, true);
      return response({ operation_id: args.operation_id, digest: args.reviewed_digest, state: 'approved' });
    }
    if (name === toolNames.editExecute) {
      assert.equal(args.confirm, true); assert.equal(args.backup_receipt.receipt, 'fixture-private-backup-receipt');
      assert.equal(f.state.backups > 0, true);
      const p = state.plans.get(args.operation_id).plan; assert.equal(args.reviewed_digest, p.digest);
      if (state.refuse) { if (state.driftOnRefusal) state.snapshot.core.post_title = 'Concurrent change'; if (state.revokeOnRefusal) state.revoked = true; return refusal(); }
      state.snapshot = structuredClone(p.candidate_snapshot); state.applied++;
      if (state.tamperUnrelated) state.snapshot.unrelated_meta_sha256 = '0'.repeat(64);
      if (state.tamperCore) state.snapshot.core.post_title = 'Collateral title';
      if (state.lostResponse) throw new Error('timed out after applying unit fixture');
      return response({ site_id: 1, site_url: f.state.url, operation_id: p.operation_id, post_id: p.target.post_id,
        installed_sha256: delegationDigest(state.snapshot), state: 'completed', changed: true, rollback_available: !p.rollback_operation_id, error_code: '' });
    }
    const result = await call(request); if (name === toolNames.backupStatus) state.afterBackup?.(); return result;
  };
  const reportSha256 = digest('Unit fixture only; no actual QA Engine claim');
  q.qa.dispatch = async request => { q.qa.qualifyRequest(request); return { runId: ++state.qa, url: 'https://github.com/unit/fixture' }; };
  q.qa.receipt = async (ticket, request) => {
    if (state.failQa) throw new Error('unit QA failure');
    const p = request.review, claims = { ...vector.qa_claims, issued_at: Math.floor(Date.now() / 1000), expires_at: p.expires_at,
      receipt_id: digest(`${ticket.runId}:1:${request.request_id}`).slice(0, 32), site_id: p.site_id, site_url: p.site_url, target: p.target,
      before_sha256: p.before_sha256, candidate_sha256: p.candidate_sha256, plan_digest: p.digest, rollback_operation_id: p.rollback_operation_id,
      qa_report_sha256: reportSha256, engine_commit: q.policy.engineCommit, runtime_url: p.profile.runtime_url,
      runtime_context_sha256: hash, profile_sha256: hash, affected_paths: p.affected_paths, migration_artifact_sha256: p.migration_artifact.sha256 };
    const envelope = q.envelope(claims, { prefix: 'mrn-content-management-qa-v1:' });
    return { status: 'qualified', ...q.qa.verifyReceipt(envelope, request, ticket.runId, reportSha256), reportSha256 };
  };
  const publicProbe = f.service.publicProbe;
  f.service.publicProbe = async (url, authorize, options) => {
    if (!options?.contentAssertions) return publicProbe(url, authorize, options);
    authorize(); const value = acfScalar(state.staleFrontend ? initial : state.snapshot, leaf);
    return responseEvidence(url, 200, Buffer.from(`<html><body><div class="unit-acf">${value}</div></body></html>`), {}, options);
  };
  f.service.contentWorkflow = new ContentWorkflow(f.service, { qa: q.qa, delay: async () => {} });
  const prepare = async (requestKey = 'managed-one', change = {}) => {
    const op = await f.service.prepareContent(person, { website: 'example', target: recordTarget, expectedSnapshotSha256: delegationDigest(state.snapshot),
      acfPatch: { path: leaf, value: '<p>Exact managed update</p>' }, reason: 'Unit ACF workflow', requestKey, ...change });
    await f.service.jobs.get(op.id); return f.service.get(person, op.id);
  };
  return { ...f, mainwpState: f.state, state, initial, leaf, recordTarget, prepare };
}

test('typed ACF unit lifecycle shares approval, backup, complete readback, rendering and separately guarded inverse', async t => {
  const f = managed(t), inspected = await f.service.inspectContent(person, { website: 'example', target: f.recordTarget });
  assert.equal(inspected.snapshot_sha256, delegationDigest(f.initial));
  const op = await f.prepare(); assert.equal(op.status, 'prepared', JSON.stringify(op.error));
  assert.equal(op.preview.before, acfScalar(f.initial, f.leaf)); assert.deepEqual(op.preview.acfPath, f.leaf); assert.equal(f.state.applied, 0);
  await assert.rejects(() => f.service.execute(person, { operationId: op.id }), { code: 'APPROVAL_REQUIRED' });
  const result = await execute(f, op); assert.equal(result.status, 'verified', JSON.stringify(result.error)); assert.equal(result.result.unrelatedMetadataVerified, true);
  assert.equal(f.state.applied, 1); assert.equal(f.mainwpState.backups, 1); assert.ok(f.state.reads >= 5);
  assert.equal(result.requestedBy, person.subject); assert.equal(result.approvedBy, person.subject); assert.equal(result.executedBy, person.subject);
  const inverse = await f.service.prepareRollback(person, { operationId: op.id, requestKey: 'managed-inverse' }); await f.service.jobs.get(inverse.id);
  assert.equal(f.service.get(person, inverse.id).status, 'prepared');
  const restored = await execute(f, f.service.get(person, inverse.id)); assert.equal(restored.status, 'verified', JSON.stringify(restored.error));
  assert.deepEqual(f.state.snapshot, f.initial); assert.equal(f.state.applied, 2); assert.equal(f.state.qa, 2); assert.equal(f.mainwpState.backups, 2);
  assert.equal(restored.result.rollbackAvailable, false);
  const audit = JSON.stringify(f.store.db.prepare('SELECT * FROM audit').all());
  assert.equal(/signature_base64|fixture-private|payload_base64/.test(audit + JSON.stringify(restored)), false);
});

test('typed ACF unit failures never substitute core evidence or retry uncertain mutation', async t => {
  for (const flag of ['failQa', 'staleFrontend', 'lostResponse', 'tamperUnrelated', 'tamperCore']) await t.test(flag, async t => {
    const f = managed(t); f.state[flag] = true; const op = await f.prepare();
    if (flag === 'failQa') { assert.equal(op.status, 'blocked'); assert.equal(f.state.applied, 0); return; }
    const result = await execute(f, op); assert.equal(result.status, 'uncertain'); f.store.assertLocked(f.store.get(op.id));
    assert.equal(f.state.applied, 1); await f.service.execute(person, { operationId: op.id }); assert.equal(f.state.applied, 1);
  });
});

test('typed ACF complete baseline, path, membership and production gates remain enforced', async t => {
  const f = managed(t);
  assert.equal((await f.prepare('bad-path', { acfPatch: { path: ['field_rows', 1, 'field_body'], value: 'Different row' } })).status, 'blocked');
  const op = await f.prepare();
  f.state.snapshot.unrelated_meta_sha256 = 'b'.repeat(64);
  await assert.rejects(() => f.service.approve(person, { operationId: op.id, planDigest: op.planDigest }), { code: 'CONTENT_PRECONDITION' });
  assert.equal(f.state.approvals, 0); assert.equal(f.state.applied, 0);
  const second = managed(t), prepared = await second.prepare(); second.service.writesEnabled = false;
  await second.service.approve(person, { operationId: prepared.id, planDigest: prepared.planDigest });
  await assert.rejects(() => second.service.execute(person, { operationId: prepared.id }), { code: 'WRITES_DISABLED' });
  assert.equal(second.state.applied, 0);
});

test('only persisted operation-bound ACF non-dispatch plus retained status and fresh baseline releases the owned gateway lock', async t => {
  const f = managed(t), op = await f.prepare(); f.state.refuse = true;
  const result = await execute(f, op); assert.equal(result.status, 'failed', JSON.stringify(result.error));
  assert.equal(result.result.childDispatched, false); assert.equal(result.result.consumed, true); assert.equal(f.state.applied, 0);
  assert.equal(f.store.db.prepare('SELECT count(*) AS n FROM locks').get().n, 0);
  await f.service.execute(person, { operationId: op.id }); assert.equal(f.state.applied, 0); assert.equal(f.state.approvals, 1);
  for (const flag of ['messageOnly', 'wrongRefusalId', 'auditMissing', 'statusUnavailable', 'driftOnRefusal', 'revokeOnRefusal', 'gatewayAuditMissing']) await t.test(flag, async t => {
    const f = managed(t), op = await f.prepare(); f.state.refuse = true;
    if (flag === 'gatewayAuditMissing') {
      const audit = f.store.audit.bind(f.store); f.store.audit = (...args) => { if (args[3] === 'change:non-dispatch') throw new Error('Audit unavailable'); return audit(...args); };
    } else f.state[flag] = true;
    const result = await execute(f, op); assert.equal(result.status, 'uncertain'); assert.equal(f.state.applied, 0); f.store.assertLocked(f.store.get(op.id));
  });
});
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
