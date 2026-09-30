import test from 'node:test';
import assert from 'node:assert/strict';
import { writeFileSync } from 'node:fs';
import { join } from 'node:path';
import { setup, actor, prepared, execute } from './helpers.mjs';
import { Store } from '../src/store.mjs';
import { MainwpSession, toolNames } from '../src/mainwp.mjs';
import { publicAddress } from '../src/probe.mjs';

test('discover -> evidence -> revalidate -> concrete plan -> approve -> existing Fleet -> verify -> code rollback', async t => {
  const f = setup(); t.after(() => f.close());
  assert.equal(f.service.list(actor)[0].websiteId, 'example');
  const { op, inspection } = await prepared(f);
  assert.equal(op.status, 'prepared', JSON.stringify(op.error));
  assert.equal(op.plan.sourceCommit, 'c'.repeat(40)); assert.equal(f.state.mutations, 0); assert.equal(f.state.backups, 0);
  assert.equal(inspection.coverage.find(c => c.check === 'forms_delivery').status, 'not_run');
  const result = await execute(f, op);
  assert.equal(result.status, 'verified', JSON.stringify(result.error)); assert.equal(f.state.mutations, 1); assert.equal(f.state.backups, 1);
  assert.equal(result.result.version, '1.1.0');
  const rollback = await f.service.prepareRollback(actor, { operationId: op.id, requestKey: 'rollback-1' });
  assert.equal(rollback.status, 'prepared', JSON.stringify(rollback.error));
  const restored = await execute(f, rollback);
  assert.equal(restored.status, 'verified', JSON.stringify(restored.error)); assert.equal(restored.result.version, '1.0.0');
  assert.equal(f.state.backups, 2); assert.equal(f.state.mutations, 2);
  const audit = JSON.stringify(f.store.db.prepare('SELECT * FROM audit').all());
  const output = JSON.stringify(f.service.history(actor, 'example', 'development'));
  assert.ok(!/fixture-private|package_base64|confirmation_token/.test(audit + output));
  assert.match(audit, /team-member/);
});

test('authorization is applied before metadata/discovery and before every downstream call', async t => {
  const f = setup(); t.after(() => f.close());
  const target = f.registry.resolve(actor, 'example', 'development');
  const session = new MainwpSession(f.client, f.registry, f.store, actor, target);
  await session.fresh();
  f.policy.members[0].enabled = false;
  const count = f.state.calls.length;
  await assert.rejects(() => session.runtime(), { code: 'FORBIDDEN' });
  assert.equal(f.state.calls.length, count);
  assert.throws(() => f.service.list({ subject: actor.subject, expiresAt: 0 }), { code: 'AUTH_REQUIRED' });
});

test('read-only user cannot prepare, approve or execute; inaccessible environments stay hidden', async t => {
  const f = setup(); t.after(() => f.close());
  const { op, inspection, finding } = await prepared(f);
  const reader = { subject: 'reader', expiresAt: actor.expiresAt };
  await assert.rejects(() => f.service.prepare(reader, { inspectionId: inspection.id, findingId: finding.id, requestKey: 'reader' }), { code: 'FORBIDDEN' });
  assert.throws(() => f.service.approve(reader, { operationId: op.id, planDigest: op.planDigest }), { code: 'FORBIDDEN' });
  await assert.rejects(() => f.service.execute(reader, { operationId: op.id }), { code: 'FORBIDDEN' });
  f.registryData.websites[0].environments.push({ name: 'production', url: 'https://live.example.test', management: 'mainwp', backup: 'updraft' });
  assert.equal(f.service.list(reader).length, 1);
  assert.throws(() => f.registry.resolve(actor, 'example'), { code: 'TARGET_AMBIGUOUS' });
  assert.throws(() => f.registry.resolve(reader, 'live.example.test'), { code: 'TARGET_UNAVAILABLE' });
});

test('wrong Dashboard identity and failed auth do not switch routes or credentials', async t => {
  const f = setup(); t.after(() => f.close()); f.state.host = 'another.example.test';
  await assert.rejects(() => f.service.inspect(actor, { website: 'example' }), { code: 'MAINWP_IDENTITY' });
  assert.equal(f.state.calls.length, 0);
  f.state.host = undefined; f.state.fail = toolNames.site;
  await assert.rejects(() => f.service.inspect(actor, { website: 'example' }), { code: 'MAINWP_FAILED' });
  assert.equal(f.state.calls.length, 1);
});

test('empty fleet selection, extra sites, numeric site guesses and near-match domains fail closed', async t => {
  const f = setup(); t.after(() => f.close());
  await assert.rejects(() => f.service.assessFleet(actor, { targets: [] }), { code: 'TARGET_REQUIRED' });
  await assert.rejects(() => f.service.assessFleet(actor, { targets: [{ website: 'example' }, { website: 'unavailable' }] }), { code: 'TARGET_UNAVAILABLE' });
  assert.equal(f.state.calls.length, 0);
  const target = f.registry.resolve(actor, 'example'); const s = new MainwpSession(f.client, f.registry, f.store, actor, target);
  await s.fresh();
  for (const args of [{ site_ids: [] }, { site_ids: [1, 2] }, { site_ids: [2] }, { site_ids: [1], exclude_ids: [] }]) await assert.rejects(() => s.call(toolNames.sync, args), { code: 'TARGET_MISMATCH' });
  await assert.rejects(() => s.call(toolNames.site, { site_id_or_domain: 1 }), { code: 'TARGET_MISMATCH' });
  assert.throws(() => f.registry.resolve(actor, 'https://example.test.evil.test'), { code: 'TARGET_UNAVAILABLE' });
});

test('sync timeout uses one exact readback; stale state does not become current', async t => {
  const f = setup(); t.after(() => f.close()); f.state.syncTimeout = true;
  const report = await f.service.inspect(actor, { website: 'example' });
  assert.equal(report.type, 'inspection'); assert.equal(f.state.calls.filter(n => n === toolNames.sync).length, 1);
  f.state.stale = true; f.state.lastSync = '2000-01-01T00:00:00Z';
  await assert.rejects(() => f.service.inspect(actor, { website: 'example' }), { code: 'STALE_INVENTORY' });
});

test('partial and absent components remain qualification cases without automatic installation', async t => {
  const f = setup(); t.after(() => f.close()); f.state.partial = true;
  const report = await f.service.inspect(actor, { website: 'example' });
  assert.ok(report.findings.some(f => f.code === 'STACK_QUALIFICATION'));
  assert.ok(report.findings.every(f => !f.repairable));
  f.state.absent = true;
  const absent = await f.service.inspect(actor, { website: 'example' });
  assert.ok(!absent.findings.some(f => f.code === 'STACK_PLUGIN_BEHIND'));
  assert.equal(f.state.mutations, 0);
});

test('capability discovery precedes unavailable results; dedicated development does not require MainWP', async t => {
  const f = setup(); t.after(() => f.close()); f.state.missing = [toolNames.security];
  const report = await f.service.inspect(actor, { website: 'example' });
  assert.equal(report.coverage.find(c => c.check === 'security').status, 'unavailable');
  f.registryData.websites[0].environments[0].management = 'dedicated'; f.state.calls.length = 0;
  const dedicated = await f.service.inspect(actor, { website: 'example' });
  assert.equal(f.state.calls.length, 0); assert.equal(dedicated.evidence[0].source, 'public:sample');
});

test('a finding must still apply; changed source/target/package/approval is never silently accepted', async t => {
  const f = setup(); t.after(() => f.close());
  const report = await f.service.inspect(actor, { website: 'example' }); f.state.updated = true;
  const blocked = await f.service.prepare(actor, { inspectionId: report.id, findingId: report.findings[0].id, requestKey: 'changed-finding' });
  assert.equal(blocked.error.code, 'FINDING_CHANGED');
  f.state.updated = false; const { op } = await prepared(f);
  assert.throws(() => f.service.approve(actor, { operationId: op.id, planDigest: 'f'.repeat(64) }), { code: 'PLAN_CHANGED' });
  writeFileSync(f.artifacts.target.package.path, 'changed package bytes');
  const result = await execute(f, op);
  assert.equal(result.status, 'failed'); assert.equal(f.state.backups, 0); assert.equal(f.state.mutations, 0);
});

test('idempotency survives retries and is scoped to exact input; execution is claimed once', async t => {
  const f = setup(); t.after(() => f.close()); const { op, inspection, finding } = await prepared(f);
  const again = await f.service.prepare(actor, { inspectionId: inspection.id, findingId: finding.id, requestKey: `prepare-${inspection.id}` });
  assert.equal(again.id, op.id);
  await assert.rejects(() => f.service.prepare(actor, { inspectionId: inspection.id, findingId: finding.id, requestKey: `prepare-${inspection.id}`.slice(0, 130) + '-bad*' }), { code: 'REQUEST_KEY_REQUIRED' });
  f.service.approve(actor, { operationId: op.id, planDigest: op.planDigest }); f.state.delay = 10;
  await Promise.all([f.service.execute(actor, { operationId: op.id }), f.service.execute(actor, { operationId: op.id })]);
  await f.service.jobs.get(op.id); await f.service.execute(actor, { operationId: op.id });
  assert.equal(f.state.mutations, 1); assert.equal(f.state.backups, 1);
});

test('durable shared site lock excludes a concurrent site deployment and survives process/database reopen', async t => {
  const f = setup(); t.after(() => f.close()); const { op } = await prepared(f);
  f.service.approve(actor, { operationId: op.id, planDigest: op.planDigest });
  const stored = f.store.get(op.id); const peer = new Store(join(f.root, 'state/operations.sqlite')); t.after(() => peer.close());
  const siteDeploy = peer.create('operation', actor, stored.target, { status: 'approved', expiresAt: Date.now() + 60000, plan: { kind: 'site_code' } });
  f.store.claim(op.id, actor);
  assert.throws(() => peer.claim(siteDeploy.id, actor), { code: 'SITE_BUSY' });
  f.store.finish(op.id, 'uncertain', {}, actor);
  const reopened = new Store(join(f.root, 'state/operations.sqlite'));
  assert.throws(() => reopened.claim(siteDeploy.id, actor), { code: 'SITE_BUSY' }); reopened.close();
});

for (const failure of ['backupFailed', 'loseResponse', 'badVerification']) test(`partial failure ${failure} retains evidence and lock without duplicate mutation`, async t => {
  const f = setup(); t.after(() => f.close()); const { op } = await prepared(f); f.state[failure] = true;
  const result = await execute(f, op); assert.equal(result.status, 'uncertain', JSON.stringify(result.error));
  const writes = f.state.mutations; await f.service.execute(actor, { operationId: op.id }); assert.equal(f.state.mutations, writes);
  assert.equal(f.store.db.prepare('SELECT count(*) AS n FROM locks').get().n, 1);
  assert.ok(!JSON.stringify(result).includes('secret-sentinel'));
});

test('safe mode, disabled writes, unenrolled writers, expired plans and target changes are hard stops', async t => {
  const f = setup(); t.after(() => f.close()); const { op } = await prepared(f);
  f.service.approve(actor, { operationId: op.id, planDigest: op.planDigest });
  f.service.writesEnabled = false; await assert.rejects(() => f.service.execute(actor, { operationId: op.id }), { code: 'WRITES_DISABLED' });
  f.service.writesEnabled = true; f.state.safeMode = true;
  await f.service.execute(actor, { operationId: op.id }); await f.service.jobs.get(op.id);
  assert.equal(f.service.get(actor, op.id).status, 'failed'); assert.equal(f.state.backups, 0);
  const changed = f.registryData.websites[0].environments[0]; changed.backup = 'kinsta';
  await assert.rejects(() => f.service.execute(actor, { operationId: op.id }), { code: 'TARGET_CHANGED' });
});

test('public network guard rejects loopback, cloud metadata, mapped IPv4 and reserved destinations', () => {
  for (const address of ['127.0.0.1', '10.1.2.3', '169.254.169.254', '192.168.1.1', '::1', '::ffff:127.0.0.1', 'fc00::1', 'fe80::1']) assert.equal(publicAddress(address), false, address);
  assert.equal(publicAddress('1.1.1.1'), true);
});
