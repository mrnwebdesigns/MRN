import test from 'node:test';
import assert from 'node:assert/strict';
import { actor, setup, prepared, execute } from './helpers.mjs';
import { QaAdapter } from '../src/qa.mjs';
import { writeFileSync } from 'node:fs';

for (const condition of ['missing', 'expired', 'wrong-site', 'wrong-artifact']) test(`QA gate blocks ${condition} qualification before any backup or write`, async t => {
  const f = setup(); t.after(() => f.close()); const { op } = await prepared(f);
  const q = f.qualificationData.records[0];
  if (condition === 'missing') f.qualificationData.records = [];
  if (condition === 'expired') q.validUntil = '2000-01-01T00:00:00Z';
  if (condition === 'wrong-site') q.siteUrl = 'https://other.test';
  if (condition === 'wrong-artifact') q.artifactSha256 = 'f'.repeat(64);
  const result = await execute(f, op); assert.equal(result.status, 'failed'); assert.equal(f.state.mutations, 0); assert.equal(f.state.backups, 0);
});

for (const stale of ['html', 'bytes', 'rest']) test(`stale ${stale} cannot be reported as verified`, async t => {
  const f = setup(); t.after(() => f.close()); const { op } = await prepared(f);
  const q = f.qualificationData.records[0]; q.frontend = 'immutable-assets';
  q.assets = [{ url: `https://example.test/assets/site.${'a'.repeat(12)}.css`, sha256: 'a'.repeat(64) }];
  f.fleet.publicProbe = async () => ({ status: 200, restHealthy: stale !== 'rest',
    references: [{ url: q.assets[0].url, present: stale !== 'html' }], contentSha256: stale === 'bytes' ? 'b'.repeat(64) : 'a'.repeat(64) });
  const result = await execute(f, op);
  assert.equal(result.status, 'uncertain'); assert.equal(f.state.mutations, 1);
  assert.ok(['STALE_HTML', 'STALE_ASSET', 'PUBLIC_VERIFICATION'].includes(result.error.code));
});

test('uncoordinated deployment routes and expired plans remain blocked', async t => {
  const f = setup(); t.after(() => f.close()); delete f.registryData.websites[0].environments[0].coordination;
  const { op } = await prepared(f); f.service.approve(actor, { operationId: op.id, planDigest: op.planDigest });
  await assert.rejects(() => f.service.execute(actor, { operationId: op.id }), { code: 'COORDINATION_REQUIRED' });
  const stored = f.store.get(op.id); stored.expiresAt = 0; stored.status = 'prepared'; f.store.save(stored);
  assert.throws(() => f.service.approve(actor, { operationId: op.id, planDigest: op.planDigest }), { code: 'PLAN_EXPIRED' });
});

test('request keys are bound to the exact finding and operation', async t => {
  const f = setup(); t.after(() => f.close()); const first = await prepared(f);
  const second = await f.service.inspect(actor, { website: 'example' });
  await assert.rejects(() => f.service.prepare(actor, { inspectionId: second.id, findingId: second.findings[0].id, requestKey: `prepare-${first.inspection.id}` }), { code: 'REQUEST_CONFLICT' });
});

test('QA adapter uses registered site project, isolated temp directory and no form-submission/browser-write flow', async t => {
  const f = setup(); t.after(() => f.close());
  f.registryData.websites[0].environments[0].qaProject = f.root;
  let invoked = false;
  const adapter = new QaAdapter({ executable: '/approved/mrn-qa', stateDir: f.root, registry: f.registry, store: f.store,
    executor: async (command, args, options) => {
      invoked = true; assert.equal(command, '/approved/mrn-qa');
      assert.equal(args[args.indexOf('--site-url') + 1], 'https://example.test');
      assert.equal(args[args.indexOf('--run-smoke') + 1], 'never');
      assert.equal(args[args.indexOf('--run-phpcbf') + 1], 'never');
      assert.equal(options.env.MAINWP_APP_PASSWORD, undefined);
      assert.ok(options.env.TMPDIR.startsWith(f.root));
      writeFileSync(args[args.indexOf('--output-file') + 1], 'Controlled QA report');
    } });
  const result = await adapter.run(actor, f.registry.resolve(actor, 'example'));
  assert.equal(invoked, true); assert.equal(result.status, 'completed'); assert.ok(result.unrun.includes('delivery'));
});
