import test from 'node:test';
import assert from 'node:assert/strict';
import { join } from 'node:path';
import { actor, setup, prepared, execute, enrollRecovery } from './helpers.mjs';
import { digest } from '../src/contracts.mjs';
import { Store } from '../src/store.mjs';
import { Operations } from '../src/service.mjs';
import { MainwpSession, toolNames } from '../src/mainwp.mjs';

const locked = f => f.store.db.prepare('SELECT count(*) AS n FROM locks').get().n;
async function uncertain(f, failure = 'loseResponse') {
  const { op } = await prepared(f); f.state[failure] = true;
  assert.equal((await execute(f, op)).status, 'uncertain');
  enrollRecovery(f, op.id); return op;
}
function changeRuntime(f, change) {
  const call = f.client.callTool.bind(f.client);
  f.client.callTool = async (...args) => {
    const response = await call(...args);
    if (args[0].name === toolNames.runtime) {
      const data = JSON.parse(response.content[0].text); change(data.report);
      response.content[0].text = JSON.stringify(data);
    }
    return response;
  };
}

test('lost response reconciles exact current code without claiming the original backup or repeating any write', async t => {
  const f = setup(); t.after(() => f.close()); const op = await uncertain(f);
  const original = f.store.get(op.id); const start = f.state.calls.length;
  f.service.writesEnabled = false; f.state.safeMode = true;
  const recovered = await f.service.reconcile(actor, { operationId: op.id });
  assert.equal(recovered.status, 'reconciled'); assert.equal(recovered.reconciliation.outcome, 'intended_code_verified');
  assert.equal(recovered.reconciliation.backup, 'not_reverified'); assert.equal(recovered.reconciliation.databaseAndMedia, 'not_assessed');
  assert.deepEqual(recovered.error, original.error); assert.equal(recovered.result, undefined);
  assert.equal(recovered.reconciliation.reconciledBy, actor.subject); assert.equal(locked(f), 0);
  assert.ok(f.state.calls.slice(start).every(n => [toolNames.site, toolNames.sync, toolNames.runtime].includes(n)));
  const count = f.state.calls.length;
  assert.deepEqual(await f.service.reconcile(actor, { operationId: op.id }), recovered);
  assert.equal(f.state.calls.length, count);
  f.service.writesEnabled = true;
  assert.equal((await f.service.execute(actor, { operationId: op.id })).status, 'reconciled');
  assert.equal(f.state.mutations, 1); assert.equal(f.state.backups, 1);
  f.state.loseResponse = false; f.state.safeMode = false;
  const rollback = await f.service.prepareRollback(actor, { operationId: op.id, requestKey: 'reconciled-rollback' });
  assert.equal(rollback.status, 'prepared'); assert.equal((await execute(f, rollback)).status, 'verified');
  assert.equal(f.state.mutations, 2); assert.equal(f.state.backups, 2);
});

test('failed backup reconciles prior code, never marks deployment successful, and permits a newly qualified repair', async t => {
  const f = setup(); t.after(() => f.close()); const op = await uncertain(f, 'backupFailed');
  const result = await f.service.reconcile(actor, { operationId: op.id });
  assert.equal(result.reconciliation.outcome, 'prior_code_verified'); assert.equal(f.state.mutations, 0); assert.equal(locked(f), 0);
  await assert.rejects(() => f.service.prepareRollback(actor, { operationId: op.id, requestKey: 'cannot-undo' }), { code: 'ROLLBACK_UNAVAILABLE' });
  f.state.backupFailed = false;
  const next = await prepared(f); assert.equal((await execute(f, next.op)).status, 'verified');
  assert.equal(f.state.mutations, 1); assert.equal(f.state.backups, 2);
});

test('a restart preserves the running lock; reviewed reconciliation works through a second SQLite connection', async t => {
  const f = setup(); t.after(() => f.close()); const { op } = await prepared(f);
  f.service.approve(actor, { operationId: op.id, planDigest: op.planDigest }); f.store.claim(op.id, actor);
  const proof = enrollRecovery(f, op.id); const second = new Store(join(f.root, 'state/operations.sqlite'));
  try {
    const restarted = new Operations({ ...f.service, store: second, writesEnabled: false });
    assert.equal(restarted.get(actor, op.id).status, 'running'); assert.equal(locked(f), 1);
    assert.equal(restarted.get(actor, op.id).recoverySnapshotDigest, proof.records[0].operationDigest);
    const result = await restarted.reconcile(actor, { operationId: op.id });
    assert.equal(result.reconciliation.previousStatus, 'running'); assert.equal(result.reconciliation.outcome, 'prior_code_verified');
    assert.equal(f.service.get(actor, op.id).status, 'reconciled'); assert.equal(locked(f), 0);
    assert.equal(f.state.backups, 0); assert.equal(f.state.mutations, 0);
  } finally { second.close(); }
});

for (const [label, change, code] of [
  ['missing evidence', d => { d.records = []; }, 'RECOVERY_EVIDENCE_REQUIRED'],
  ['duplicate evidence', d => d.records.push(d.records[0]), 'RECOVERY_EVIDENCE_REQUIRED'],
  ['different snapshot', d => { d.records[0].operationDigest = 'f'.repeat(64); }, 'RECOVERY_EVIDENCE_MISMATCH'],
  ['different site', d => { d.records[0].siteUrl = 'https://another.test'; }, 'RECOVERY_EVIDENCE_MISMATCH'],
  ['different environment', d => { d.records[0].environment = 'production'; }, 'RECOVERY_EVIDENCE_MISMATCH'],
  ['expired evidence', d => { d.records[0].validUntil = '2000-01-01T00:00:00.000Z'; }, 'RECOVERY_EVIDENCE_EXPIRED'],
  ['future attestation', d => { d.records[0].verifiedAt = new Date(Date.now() + 60000).toISOString(); }, 'RECOVERY_EVIDENCE_EXPIRED'],
  ['pre-operation attestation', d => { d.records[0].verifiedAt = '2000-01-01T00:00:00.000Z'; }, 'RECOVERY_EVIDENCE_EXPIRED'],
  ['excessive expiry', d => { d.records[0].validUntil = new Date(Date.now() + 3600000).toISOString(); }, 'RECOVERY_EVIDENCE_EXPIRED'],
]) test(`recovery rejects ${label} before any downstream call`, async t => {
  const f = setup(); t.after(() => f.close()); const op = await uncertain(f);
  change(enrollRecovery(f, op.id)); const calls = f.state.calls.length; const before = digest(f.store.get(op.id));
  await assert.rejects(() => f.service.reconcile(actor, { operationId: op.id }), { code });
  assert.equal(f.state.calls.length, calls); assert.equal(locked(f), 1); assert.equal(digest(f.store.get(op.id)), before);
});

test('recovery requires release permission and rejects a still-active local worker', async t => {
  const f = setup(); t.after(() => f.close()); const op = await uncertain(f); const count = f.state.calls.length;
  await assert.rejects(() => f.service.reconcile({ subject: 'reader', expiresAt: actor.expiresAt }, { operationId: op.id }), { code: 'FORBIDDEN' });
  f.service.jobs.set(op.id, Promise.resolve());
  await assert.rejects(() => f.service.reconcile(actor, { operationId: op.id }), { code: 'OPERATION_ACTIVE' });
  assert.equal(f.state.calls.length, count); assert.equal(locked(f), 1);
});

test('expired coordination can be renewed for recovery and subsequent rollback without rewriting the original target', async t => {
  const f = setup(); t.after(() => f.close()); const op = await uncertain(f);
  const before = f.store.get(op.id); const enrolled = f.registryData.websites[0].environments[0];
  enrolled.coordination.validUntil = '2000-01-01T00:00:00.000Z';
  await assert.rejects(() => f.service.reconcile(actor, { operationId: op.id }), { code: 'COORDINATION_REQUIRED' });
  enrolled.coordination = { ...enrolled.coordination, evidenceRef: 'fixture/renewed-exclusion', validUntil: new Date(Date.now() + 3600000).toISOString() };
  const result = await f.service.reconcile(actor, { operationId: op.id });
  assert.equal(result.reconciliation.coordination.evidenceRef, 'fixture/renewed-exclusion');
  assert.deepEqual(f.store.get(op.id).target, before.target); assert.equal(f.store.get(op.id).planDigest, before.planDigest);
  const rollback = await f.service.prepareRollback(actor, { operationId: op.id, requestKey: 'renewed-rollback' });
  assert.equal(rollback.status, 'prepared');
  assert.equal(f.store.get(rollback.id).target.coordination.evidenceRef, 'fixture/renewed-exclusion');
  f.state.loseResponse = false;
  assert.equal((await execute(f, rollback)).status, 'verified');
});

test('renewing recovery enrollment cannot change target ownership, management or backup policy', async t => {
  const f = setup(); t.after(() => f.close()); const op = await uncertain(f); const count = f.state.calls.length;
  f.registryData.websites[0].environments[0].backup = 'kinsta';
  await assert.rejects(() => f.service.reconcile(actor, { operationId: op.id }), { code: 'TARGET_CHANGED' });
  assert.equal(f.state.calls.length, count); assert.equal(locked(f), 1);
});

for (const [label, alter, code] of [
  ['wrong dashboard', f => { f.state.host = 'unexpected.test'; }, 'MAINWP_IDENTITY'],
  ['access denied', f => { f.client.readResource = async () => { throw new Error('401 unauthorized secret-sentinel'); }; }, 'MAINWP_ACCESS'],
  ['missing runtime capability', f => { f.state.missing = [toolNames.runtime]; }, 'CAPABILITY_MISSING'],
  ['stale sync', f => { f.state.stale = true; f.state.lastSync = '2000-01-01T00:00:00Z'; }, 'STALE_INVENTORY'],
  ['different MainWP site ID', f => { f.state.id = 2; }, 'TARGET_MISMATCH'],
  ['unknown tree', f => changeRuntime(f, r => { r.components[0].sha256 = 'd'.repeat(64); }), 'RECOVERY_RUNTIME_MISMATCH'],
  ['wrong baseline', f => changeRuntime(f, r => { r.release_lock.sha256 = 'd'.repeat(64); }), 'RECOVERY_RUNTIME_MISMATCH'],
  ['missing component', f => { f.state.absent = true; }, 'RECOVERY_RUNTIME_MISMATCH'],
  ['unqualified drift', f => changeRuntime(f, r => { r.unknown_drifted_required = ['another-component']; }), 'RECOVERY_RUNTIME_UNQUALIFIED'],
  ['missing observed artifact QA', f => { f.qualificationData.records = []; }, 'QA_REQUIRED'],
  ['failed public REST check', f => { f.fleet.publicProbe = async () => ({ status: 200, restHealthy: false }); }, 'PUBLIC_VERIFICATION'],
]) test(`recovery retains uncertain state and lock on ${label}`, async t => {
  const f = setup(); t.after(() => f.close()); const op = await uncertain(f); alter(f);
  const before = digest(f.store.get(op.id)); const writes = f.state.mutations; const backups = f.state.backups;
  await assert.rejects(() => f.service.reconcile(actor, { operationId: op.id }), { code });
  assert.equal(locked(f), 1); assert.equal(digest(f.store.get(op.id)), before);
  assert.equal(f.state.mutations, writes); assert.equal(f.state.backups, backups);
});

for (const scenario of ['html', 'bytes']) test(`stale ${scenario} blocks reconciliation of a deployed frontend artifact`, async t => {
  const f = setup(); t.after(() => f.close()); const op = await uncertain(f);
  const asset = { url: `https://example.test/assets/app.${'d'.repeat(12)}.css`, sha256: 'd'.repeat(64) };
  Object.assign(f.qualificationData.records[0], { frontend: 'immutable-assets', assets: [asset] });
  f.fleet.publicProbe = async () => ({ status: 200, restHealthy: true, references: [{ url: asset.url, present: scenario !== 'html' }], contentSha256: 'e'.repeat(64) });
  await assert.rejects(() => f.service.reconcile(actor, { operationId: op.id }), { code: scenario === 'html' ? 'STALE_HTML' : 'STALE_ASSET' });
  assert.equal(locked(f), 1);
});

test('successful recovery retains served immutable-asset checksums and public verification timestamps', async t => {
  const f = setup(); t.after(() => f.close()); const op = await uncertain(f);
  const asset = { url: `https://example.test/assets/app.${'d'.repeat(12)}.css`, sha256: 'd'.repeat(64) };
  Object.assign(f.qualificationData.records[0], { frontend: 'immutable-assets', assets: [asset] });
  const measuredAt = new Date().toISOString();
  f.fleet.publicProbe = async () => ({ status: 200, restHealthy: true, references: [{ url: asset.url, present: true }], contentSha256: asset.sha256, measuredAt });
  const result = await f.service.reconcile(actor, { operationId: op.id });
  const check = result.reconciliation.publicChecks.find(c => c.url === asset.url);
  assert.equal(check.contentSha256, asset.sha256); assert.equal(check.referencedByPage, true); assert.equal(check.measuredAt, measuredAt);
  assert.equal(result.reconciliation.publicChecks.find(c => c.path === '/wp-json/').restHealthy, true);
});

for (const scenario of ['revocation', 'proof-withdrawn', 'runtime-changed']) test(`recovery rechecks ${scenario} after public verification`, async t => {
  const f = setup(); t.after(() => f.close()); const op = await uncertain(f); const data = enrollRecovery(f, op.id);
  f.fleet.publicProbe = async (...args) => {
    if (scenario === 'revocation') f.policy.members[0].enabled = false;
    if (scenario === 'proof-withdrawn') data.records = [];
    if (scenario === 'runtime-changed') f.state.updated = false;
    return f.publicProbe(...args);
  };
  await assert.rejects(() => f.service.reconcile(actor, { operationId: op.id }), {
    code: { revocation: 'FORBIDDEN', 'proof-withdrawn': 'RECOVERY_EVIDENCE_REQUIRED', 'runtime-changed': 'RUNTIME_MISMATCH' }[scenario],
  });
  assert.equal(locked(f), 1); assert.equal(f.store.get(op.id).status, 'uncertain');
});

test('concurrent recovery requests resolve once and never overwrite an execution that finished during readback', async t => {
  const f = setup(); t.after(() => f.close()); const op = await uncertain(f);
  const peer = new Store(join(f.root, 'state/operations.sqlite'));
  let results;
  try {
    const other = new Operations({ ...f.service, store: peer });
    results = await Promise.allSettled([f.service.reconcile(actor, { operationId: op.id }), other.reconcile(actor, { operationId: op.id })]);
  } finally { peer.close(); }
  assert.equal(results.filter(r => r.status === 'fulfilled').length, 1);
  assert.equal(results.find(r => r.status === 'rejected').reason.code, 'RECOVERY_STATE_CHANGED');
  assert.equal(f.store.db.prepare("SELECT count(*) AS n FROM audit WHERE event='reconcile' AND outcome='intended_code_verified'").get().n, 1);
  assert.equal(locked(f), 0);
  f.state.loseResponse = false; f.state.updated = false;
  const next = (await prepared(f)).op;
  f.service.approve(actor, { operationId: next.id, planDigest: next.planDigest }); f.store.claim(next.id, actor); enrollRecovery(f, next.id);
  let first = true;
  f.fleet.publicProbe = async (...args) => {
    if (first) { first = false; f.store.finish(next.id, 'uncertain', { error: { code: 'LATEST_EXECUTION' } }, actor); }
    return f.publicProbe(...args);
  };
  await assert.rejects(() => f.service.reconcile(actor, { operationId: next.id }), { code: 'RECOVERY_STATE_CHANGED' });
  assert.equal(locked(f), 1); assert.equal(f.store.get(next.id).error.code, 'LATEST_EXECUTION');
});

test('recovery sessions forbid backup and mutation calls even for a release-authorized caller', async t => {
  const f = setup(); t.after(() => f.close()); const op = await uncertain(f); const backups = f.state.backups;
  f.fleet.reconcile = async (_op, session) => { await session.fresh(); await session.call(toolNames.backup, { site_id: session.siteId }); };
  await assert.rejects(() => f.service.reconcile(actor, { operationId: op.id }), { code: 'CAPABILITY_DENIED' });
  assert.equal(f.state.backups, backups); assert.equal(locked(f), 1);
});

test('a late original worker cannot call downstream or overwrite reconciliation and release a newer site lock', async t => {
  const f = setup(); t.after(() => f.close()); const op = await uncertain(f);
  const saved = f.store.get(op.id);
  const late = new MainwpSession(f.client, f.registry, f.store, actor, saved.target, {
    action: 'deploy_development', operation: op.id, checkAdmission: () => f.store.assertRunning(op.id),
  });
  await f.service.reconcile(actor, { operationId: op.id });
  const peer = f.store.create('operation', actor, saved.target, { status: 'approved', expiresAt: Date.now() + 60000 });
  f.store.claim(peer.id, actor); const count = f.state.calls.length;
  await assert.rejects(() => late.fresh(), { code: 'EXECUTION_FENCED' });
  assert.throws(() => f.store.finish(op.id, 'verified', {}, actor), { code: 'EXECUTION_FENCED' });
  assert.equal(f.state.calls.length, count); assert.equal(locked(f), 1);
  assert.equal(f.store.get(op.id).status, 'reconciled');
});

test('lost rollback response reconciles the rollback destination as intended code and cannot be undone as an update', async t => {
  const f = setup(); t.after(() => f.close()); const { op } = await prepared(f);
  assert.equal((await execute(f, op)).status, 'verified');
  const rollback = await f.service.prepareRollback(actor, { operationId: op.id, requestKey: 'lost-rollback' });
  f.state.loseResponse = true; assert.equal((await execute(f, rollback)).status, 'uncertain'); enrollRecovery(f, rollback.id);
  const result = await f.service.reconcile(actor, { operationId: rollback.id });
  assert.equal(result.reconciliation.outcome, 'intended_code_verified'); assert.equal(result.reconciliation.version, '1.0.0');
  assert.equal(f.state.mutations, 2); assert.equal(f.state.backups, 2);
  await assert.rejects(() => f.service.prepareRollback(actor, { operationId: rollback.id, requestKey: 'cannot-redo' }), { code: 'ROLLBACK_UNAVAILABLE' });
});
