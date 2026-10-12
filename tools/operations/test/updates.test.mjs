import test from 'node:test';
import assert from 'node:assert/strict';
import { setup, actor } from './helpers.mjs';
import { updateEvidence } from '../src/updates.mjs';
import { toolNames } from '../src/mainwp.mjs';
import { response } from './fixtures/mainwp.mjs';

const row = overrides => ({ type: 'plugin', slug: 'example/example.php', name: 'Example Plugin', current_version: '1.2.3', new_version: '1.2.4', ...overrides });
const inventory = (updates, overrides = {}) => ({ site_id: 1, site_url: 'https://example.test/', updates,
  summary: { core: updates.filter(r => r?.type === 'core').length, plugins: updates.filter(r => r?.type === 'plugin').length,
    themes: updates.filter(r => r?.type === 'theme').length, translations: updates.filter(r => r?.type === 'translation').length, total: updates.length }, ...overrides });
const evidence = data => updateEvidence(data, 1, 'https://example.test');
const inspect = f => f.service.inspect(actor, { website: 'example' });

test('mixed MainWP update rows retain names and version pairs with separate type totals', () => {
  const result = evidence(inventory([
    row({ name: 'PDF Invoices & Packing Slips', site_id: 1, site_url: 'https://example.test/' }),
    row({ type: 'core', name: 'WordPress', slug: 'wordpress', current_version: '7.1.3', new_version: '7.1.4' }),
    row({ type: 'theme', name: 'Child Theme', slug: 'child-theme' }),
    row({ type: 'translation', name: 'German translation', slug: 'de_DE' }),
  ]));
  assert.deepEqual(result.summary, { core: 1, plugins: 1, themes: 1, translations: 1, unknown: 0, total: 4 });
  assert.equal(result.complete, true); assert.equal(result.records, 4);
  assert.deepEqual(result.updates[0], { type: 'plugin', name: 'PDF Invoices & Packing Slips', slug: 'example/example.php', currentVersion: '1.2.3', availableVersion: '1.2.4' });
  assert.match(result.note, /does not establish compatibility/);
});

test('nine plugin updates stay readable in saved inspection/history despite REST 403 and missing Stack agent', async t => {
  const f = setup(); t.after(() => f.close());
  const names = ['Announcer', 'MainWP Child', 'PDF Invoices & Packing Slips for WooCommerce', 'Post Types Order',
    'Site Kit by Google', 'WooCommerce', 'WP All Import - WooCommerce Import Add-On Pro', 'WPForms', 'YITH WooCommerce Wishlist'];
  f.state.updateInventory = inventory(names.map((name, index) => row({ name, slug: `fixture-${index}/plugin.php`,
    current_version: index === 5 ? '11.1.2' : '1.0.0', new_version: index === 5 ? '11.2.1' : '1.1.0',
    package: 'https://private.test/package?token=private-sentinel', download_url: 'private-sentinel', secret: 'private-sentinel',
    extra: { credentials: 'private-sentinel' } })), { rollback_items: { plugins: [{ token: 'private-sentinel' }] } });
  const call = f.client.callTool.bind(f.client);
  f.client.callTool = async request => {
    if (request.name === toolNames.runtime) return { isError: true, ...response({ error: 'mrn_mainwp_stack_report_unavailable' }) };
    if (request.name === toolNames.qualify) return response({ site_id: 1, site_url: f.state.url,
      classification: 'deployment_agent_unavailable', agent: { available: false }, runtime: { available: false }, blockers: ['deployment_agent_unavailable'] });
    return call(request);
  };
  f.service.publicProbe = async (...args) => ({ ...await f.publicProbe(...args), ...(args[0].endsWith('/wp-json/') ? { status: 403, restHealthy: false } : {}) });
  const result = await inspect(f);
  const updates = result.evidence.find(e => e.source === 'mainwp:updates');
  assert.deepEqual(updates.summary, { core: 0, plugins: 9, themes: 0, translations: 0, unknown: 0, total: 9 });
  assert.deepEqual(updates.updates.map(r => r.name), names);
  assert.equal(updates.updates[5].currentVersion, '11.1.2'); assert.equal(updates.updates[5].availableVersion, '11.2.1');
  assert.equal(result.coverage.find(c => c.check === 'updates').status, 'queried');
  assert.equal(result.coverage.find(c => c.check === 'wordpress_rest').status, 'unhealthy');
  assert.ok(result.findings.some(f => f.code === 'UPDATES_AVAILABLE' && !f.repairable));
  const saved = f.service.get(actor, result.id); const history = await f.service.history(actor, 'example');
  assert.deepEqual(saved.evidence.find(e => e.source === 'mainwp:updates'), updates);
  assert.deepEqual(history[0].evidence.find(e => e.source === 'mainwp:updates'), updates);
  assert.ok(!/private-sentinel|download_url|rollback_items/.test(JSON.stringify([saved, history])));
  const syncIndex = f.state.calls.indexOf(toolNames.sync); const updateIndex = f.state.calls.indexOf(toolNames.updates);
  assert.ok(syncIndex >= 0 && updateIndex > syncIndex);
  assert.equal(f.state.backups, 0); assert.equal(f.state.mutations, 0);
});

test('zero updates requires a valid empty list and matching summary', () => {
  const result = evidence(inventory([])); assert.equal(result.complete, true); assert.equal(result.summary.total, 0);
  assert.throws(() => evidence({ site_id: 1, site_url: 'https://example.test', updates: {}, summary: { total: 0 } }), { code: 'MAINWP_UPDATES_SCHEMA' });
  assert.equal(evidence(inventory([], { summary: undefined })).complete, false);
});

test('missing and unsafe display values remain unknown without reflecting arbitrary metadata', () => {
  const result = evidence(inventory([row({ name: '<script>private-sentinel</script>', slug: '../private-sentinel',
    current_version: '', new_version: 'https://private-sentinel.test', credential: 'private-sentinel' }),
  row({ name: 'Line\nprivate-sentinel', slug: 'https://private-sentinel.test' }), null, row({ type: 'unrecognized-private-sentinel' })]));
  assert.equal(result.complete, false); assert.equal(result.summary.unknown, 2);
  assert.deepEqual(result.updates[0], { type: 'plugin', name: null, slug: null, currentVersion: null, availableVersion: null });
  assert.ok(!JSON.stringify(result).includes('private-sentinel'));
  assert.match(result.limitations.join(' '), /null values are unknown/);
});

test('summary disagreement and bounded output explicitly report incomplete coverage', () => {
  const mismatch = evidence(inventory([row()], { summary: { core: 0, plugins: 9, themes: 0, translations: 0, total: 9 } }));
  assert.equal(mismatch.complete, false); assert.equal(mismatch.summary.plugins, 1);
  assert.match(mismatch.limitations.join(' '), /does not match/);
  const capped = evidence(inventory(Array.from({ length: 251 }, () => row())));
  assert.equal(capped.updates.length, 250); assert.equal(capped.summary.plugins, 251); assert.equal(capped.complete, false);
  assert.match(capped.limitations.join(' '), /first 250 of 251/);
});

for (const other of [{ site_id: 2 }, { site_url: 'https://other.test' }, { site_url: 'invalid' }]) test(`wrong update envelope ${JSON.stringify(other)} aborts inspection`, async t => {
  const f = setup(); t.after(() => f.close()); f.state.updateInventory = inventory([row()], other);
  await assert.rejects(() => inspect(f), { code: 'TARGET_MISMATCH' });
  assert.equal((await f.service.history(actor, 'example')).length, 0);
});

test('cross-site update rows, including beyond the output cap, are rejected', () => {
  for (const other of [{ site_id: 2 }, { site_url: 'https://other.test' }, { site_url: null }]) {
    assert.throws(() => evidence(inventory([row(other)])), { code: 'TARGET_MISMATCH' });
  }
  assert.throws(() => evidence(inventory([...Array.from({ length: 250 }, () => row()), row({ site_id: 2 })])), { code: 'TARGET_MISMATCH' });
});

test('malformed update payload is blocked; incomplete rows are not reported as complete', async t => {
  const f = setup(); t.after(() => f.close()); f.state.updateInventory = { items: [] };
  const result = await inspect(f);
  assert.equal(result.coverage.find(c => c.check === 'updates').code, 'MAINWP_UPDATES_SCHEMA');
  assert.ok(!result.evidence.some(e => e.source === 'mainwp:updates'));
  f.state.updateInventory = inventory([row({ new_version: undefined })]);
  const partial = await inspect(f);
  assert.equal(partial.coverage.find(c => c.check === 'updates').status, 'incomplete');
  assert.equal(partial.evidence.find(e => e.source === 'mainwp:updates').updates[0].availableVersion, null);
});

test('revocation during update retrieval discards details and saves no inspection', async t => {
  const f = setup(); t.after(() => f.close()); const call = f.client.callTool.bind(f.client);
  f.client.callTool = async request => {
    if (request.name === toolNames.updates) f.policy.members[0].enabled = false;
    return call(request);
  };
  await assert.rejects(() => inspect(f), { code: 'FORBIDDEN' });
  assert.equal(f.store.db.prepare('SELECT COUNT(*) AS n FROM records').get().n, 0);
});
