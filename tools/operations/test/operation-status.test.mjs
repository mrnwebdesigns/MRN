import test from 'node:test';
import assert from 'node:assert/strict';
import { randomUUID } from 'node:crypto';
import { setup, actor } from './helpers.mjs';
import { response } from './fixtures/mainwp.mjs';
import { makePlan } from './fixtures/content.mjs';
import { toolNames } from '../src/mainwp.mjs';
import { OpsError } from '../src/contracts.mjs';

const reader = { ...actor, identityProvider: 'google-oauth2', scopes: ['mrn:operations'] };
function configured(t, workflow = 'code') {
  const f = setup(); t.after(f.close);
  const target = f.registry.resolve(reader, 'example', 'development');
  const plan = workflow === 'content' ? makePlan().plan : { operation_id: randomUUID(), digest: 'a'.repeat(64), site_id: 1,
    site_url: 'https://example.test/', kind: 'file', path: 'wp-content/themes/fixture/file.php', before_sha256: 'b'.repeat(64), new_sha256: 'c'.repeat(64) };
  let result = workflow === 'code' ? { ...plan, state: 'completed', secret: 'raw-secret-sentinel' }
    : Object.fromEntries(['operation_id', 'site_id', 'site_url', 'target', 'before_sha256', 'candidate_sha256', 'migration_artifact', 'affected_paths'].map(k => [k, plan[k]]));
  if (workflow === 'content') Object.assign(result, { state: 'completed', current_sha256: plan.candidate_sha256, changed: true, rollback_available: true, error_code: null });
  const state = { result, revoked: false, statusReads: 0 };
  f.service.verifyStaff = async () => { if (state.revoked) throw new OpsError('STAFF_ACCESS_DENIED', 'Revoked.'); };
  const originalCall = f.client.callTool.bind(f.client);
  f.client.callTool = async request => {
    if (request.name === toolNames[workflow === 'code' ? 'codeStatus' : 'contentStatus']) {
      assert.deepEqual(request.arguments, { operation_id: plan.operation_id }); state.statusReads++;
      state.onStatus?.(); return response(state.result);
    }
    return originalCall(request);
  };
  const op = f.store.create('operation', actor, target, { workflow, status: 'approved', expiresAt: Date.now() + 60000,
    plan: { provider: plan, path: plan.path, description: 'Fixture operation' } });
  f.store.claim(op.id, actor); f.store.finish(op.id, 'uncertain', {}, actor);
  return { ...f, op: f.store.get(op.id), plan, statusState: state };
}

for (const workflow of ['code', 'content']) test(`${workflow} status refresh reads exact provider metadata without changing uncertainty or releasing its lock`, async t => {
  const f = configured(t, workflow), before = f.store.get(f.op.id);
  const result = await f.service.refreshOperation(reader, f.op.id);
  assert.equal(result.status, 'uncertain'); assert.equal(result.providerStatus.state, 'completed');
  assert.equal(result.providerStatus.liveStateVerified, false); assert.equal(result.providerStatus.lockReleased, false);
  assert.equal(result.providerStatus.providerOperationId, f.plan.operation_id);
  assert.match(result.providerStatus.explanation, /does not verify current content or code/);
  assert.deepEqual(f.store.get(f.op.id), before); f.store.assertLocked(f.op);
  assert.equal(f.statusState.statusReads, 1); assert.equal(f.state.backups, 0); assert.equal(f.state.mutations, 0);
  assert.deepEqual(f.state.calls, [toolNames.site]);
  assert.ok(!JSON.stringify(result).includes('raw-secret-sentinel'));
});

test('provider status rejects mismatched plan, site, file, record, hashes and state', async t => {
  for (const workflow of ['code', 'content']) {
    const f = configured(t, workflow), original = structuredClone(f.statusState.result);
    const variants = [{ operation_id: randomUUID() }, { site_id: 2 }, { site_url: 'https://other.test/' }, { before_sha256: 'e'.repeat(64) }, { state: 'unrecognized' },
      ...(workflow === 'code' ? [{ digest: 'e'.repeat(64) }, { path: 'wp-content/themes/fixture/other.php' }, { new_sha256: 'e'.repeat(64) }]
        : [{ target: { ...f.plan.target, post_id: 99 } }, { candidate_sha256: 'e'.repeat(64) }, { affected_paths: ['/other/'] }])];
    for (const variant of variants) {
      f.statusState.result = { ...original, ...variant };
      await assert.rejects(() => f.service.refreshOperation(reader, f.op.id));
      f.store.assertLocked(f.op); assert.equal(f.store.get(f.op.id).status, 'uncertain');
    }
  }
});

test('revoked membership blocks status before dispatch and withholds a result revoked during dispatch', async t => {
  const f = configured(t); f.statusState.revoked = true;
  await assert.rejects(() => f.service.refreshOperation(reader, f.op.id), { code: 'STAFF_ACCESS_DENIED' });
  assert.equal(f.statusState.statusReads, 0); assert.equal(f.state.calls.length, 0);
  f.statusState.revoked = false; f.statusState.onStatus = () => { f.statusState.revoked = true; };
  await assert.rejects(() => f.service.refreshOperation(reader, f.op.id), { code: 'STAFF_ACCESS_DENIED' });
  assert.equal(f.statusState.statusReads, 1); f.store.assertLocked(f.op);
});

test('status refresh requires Google and exact existing local access but no write OAuth scope', async t => {
  const f = configured(t);
  await assert.rejects(() => f.service.refreshOperation(actor, f.op.id), { code: 'AUTH_REQUIRED' });
  await assert.rejects(() => f.service.refreshOperation({ ...reader, subject: 'outsider' }, f.op.id), { code: 'FORBIDDEN' });
  assert.equal(f.statusState.statusReads, 0);
  assert.equal((await f.service.refreshOperation(reader, f.op.id)).providerStatus.state, 'completed');
});

test('ordinary inspections and unprepared plans retain their local read path without provider dispatch', async t => {
  const f = configured(t), record = f.store.get(f.op.id); delete record.plan.provider; f.store.save(record);
  assert.equal(f.service.get(reader, f.op.id).status, 'uncertain');
  await assert.rejects(() => f.service.refreshOperation(reader, f.op.id), { code: 'OPERATION_STATUS_UNAVAILABLE' });
  assert.equal(f.statusState.statusReads, 0); assert.equal(f.state.calls.length, 0);
});

test('provider-side resolution does not reconcile or unlock the separate local operation', async t => {
  const f = configured(t); f.statusState.result.state = 'resolved_after_inspection';
  const result = await f.service.refreshOperation(reader, f.op.id);
  assert.equal(result.providerStatus.state, 'resolved_after_inspection'); assert.equal(result.status, 'uncertain');
  assert.equal(result.providerStatus.liveStateVerified, false); f.store.assertLocked(f.op);
});
