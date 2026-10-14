import test from 'node:test';
import assert from 'node:assert/strict';
import { actor, setup } from './helpers.mjs';
import { toolNames } from '../src/mainwp.mjs';

test('capability inventory distinguishes backend counts, connected workflows and unknown tools', async t => {
  const f = setup(); t.after(f.close);
  const original = f.client.listTools.bind(f.client);
  f.client.listTools = async () => ({ tools: [...(await original()).tools, { name: 'future_mutator_v1', inputSchema: { type: 'object' }, annotations: { readOnlyHint: true } }] });
  const report = await f.service.capabilities(actor);
  assert.equal(report.providerDiscoveredAbilities, 84);
  assert.equal(report.backendExposedTools, Object.values(toolNames).length + 1);
  assert.equal(report.capabilities.find(c => c.capability === 'future_mutator_v1').integration, 'integration_required');
  assert.equal(f.state.calls.length, 0);
});

test('additional website reads retain exact-site fresh sync, permission and secret filtering', async t => {
  const f = setup(); t.after(f.close);
  const result = await f.service.details(actor, { website: 'example', section: 'abandoned_plugins' });
  assert.equal(result.site.id, 1);
  assert.deepEqual(f.state.calls, [toolNames.site, toolNames.sync, toolNames.site, toolNames.abandonedPlugins]);
  assert.deepEqual(result.data, { items: [] });
  await assert.rejects(() => f.service.details(actor, { website: 'example', section: 'arbitrary_command' }), { code: 'CAPABILITY_DENIED' });
  f.policy.members[0].enabled = false;
  await assert.rejects(() => f.service.details(actor, { website: 'example', section: 'security' }));
  assert.equal(f.state.calls.length, 4);
});

test('portfolio directory cannot be used by a single-site reader and strips private fields', async t => {
  const f = setup(); t.after(f.close);
  const original = f.client.listTools.bind(f.client);
  f.client.listTools = async () => ({ tools: [...(await original()).tools, { name: 'get_client_v1', inputSchema: { type: 'object' } }] });
  await assert.rejects(() => f.service.portfolioDetails(actor, { section: 'client', recordId: 5 }), { code: 'FORBIDDEN' });
  f.policy.members[0].portfolio = { sources: ['mainwp'], actions: ['read'] };
  const call = f.client.callTool.bind(f.client);
  f.client.callTool = async request => {
    if (request.name !== 'get_client_v1') return call(request);
    assert.deepEqual(request.arguments, { client_id_or_email: 5 });
    return { content: [{ type: 'text', text: JSON.stringify({ id: 5, name: 'Example', notes: 'private', url: 'https://example.test/?token=private' }) }] };
  };
  const result = await f.service.portfolioDetails(actor, { section: 'client', recordId: 5 });
  assert.deepEqual(result.data, { id: 5, name: 'Example', url: 'https://example.test/' });
  await assert.rejects(() => f.service.portfolioDetails(actor, { section: 'client' }), { code: 'TARGET_REQUIRED' });
});

test('ignored updates stay on the fresh exact site and never allow empty or expanded selectors', async t => {
  const f = setup(); t.after(f.close);
  const call = f.client.callTool.bind(f.client);
  f.client.callTool = async request => {
    if (request.name === toolNames.ignoredUpdates) assert.deepEqual(request.arguments, { site_ids_or_domains: [1] });
    return call(request);
  };
  const result = await f.service.details(actor, { website: 'example', section: 'ignored_updates' });
  assert.equal(result.site.id, 1);
  assert.deepEqual(f.state.calls, [toolNames.site, toolNames.sync, toolNames.site, toolNames.ignoredUpdates]);
  const target = f.service.registry.resolve(actor, 'example');
  const session = await f.service.session(actor, target, 'read'); t.after(() => session.close());
  await session.fresh(); const count = f.state.calls.length;
  for (const args of [{ site_ids_or_domains: [] }, { site_ids_or_domains: [1, 2] }, { site_ids_or_domains: [2] }, { site_ids_or_domains: [1], other: true }])
    await assert.rejects(() => session.call(toolNames.ignoredUpdates, args), { code: 'TARGET_MISMATCH' });
  assert.equal(f.state.calls.length, count);
});

test('site directory search is bounded and requires portfolio read permission', async t => {
  const f = setup(); t.after(f.close);
  const original = f.client.listTools.bind(f.client);
  f.client.listTools = async () => ({ tools: [...(await original()).tools, { name: 'list_sites_v1', inputSchema: { type: 'object' } }] });
  await assert.rejects(() => f.service.portfolioDetails(actor, { section: 'sites' }), { code: 'FORBIDDEN' });
  f.policy.members[0].portfolio = { sources: ['mainwp'], actions: ['read'] };
  let dispatches = 0;
  f.client.callTool = async ({ name, arguments: args }) => {
    dispatches++;
    assert.equal(name, 'list_sites_v1');
    assert.deepEqual(args, { page: 2, per_page: 100, search: 'company' });
    return { content: [{ type: 'text', text: JSON.stringify({ items: [{ id: 1, name: 'Company' }] }) }] };
  };
  const result = await f.service.portfolioDetails(actor, { section: 'sites', page: 2, search: 'company' });
  assert.equal(result.data.items[0].id, 1);
  for (const input of [{ section: 'sites', page: 101 }, { section: 'sites', recordId: 2 }, { section: 'sites', search: 'x'.repeat(201) }])
    await assert.rejects(() => f.service.portfolioDetails(actor, input));
  assert.equal(dispatches, 1);
});
