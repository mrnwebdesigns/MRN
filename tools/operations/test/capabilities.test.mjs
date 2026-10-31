import test from 'node:test';
import assert from 'node:assert/strict';
import { actor, setup } from './helpers.mjs';
import { toolNames } from '../src/mainwp.mjs';

test('email audit discovery distinguishes configured workflow from filtered native ability discovery', async t => {
  const f = setup(); t.after(f.close);
  const names = ['mrn_mainwp__authorize_email_address_audit_v1', 'mrn_mainwp__audit_email_address_v1'];
  const original = f.client.listTools.bind(f.client);
  f.client.listTools = async () => ({ tools: [...(await original()).tools, ...names.map(name => ({ name, inputSchema: { type: 'object' } }))] });
  let report = await f.service.capabilities(actor);
  assert.equal(report.emailAudit.workflow, 'unavailable');
  assert.equal(report.capabilities.find(v => v.capability === names[0]).integration, 'integration_required');
  f.service.emailAudit = {};
  report = await f.service.capabilities(actor);
  assert.equal(report.emailAudit.workflow, 'configured'); assert.equal(report.emailAudit.nativeAbilities, 'discovered');
  assert.equal(report.capabilities.find(v => v.capability === names[0]).workflow, 'email_address_audit');
  f.client.listTools = original;
  report = await f.service.capabilities(actor);
  assert.equal(report.emailAudit.workflow, 'configured'); assert.equal(report.emailAudit.nativeAbilities, 'not_in_filtered_directory');
  assert.equal(f.state.calls.length, 0);
});

test('capability inventory distinguishes backend counts, connected workflows and unknown tools', async t => {
  const f = setup(); t.after(f.close); f.state.contentManagement = true;
  const original = f.client.listTools.bind(f.client);
  f.client.listTools = async () => ({ tools: [...(await original()).tools, { name: 'future_mutator_v1', inputSchema: { type: 'object' }, annotations: { readOnlyHint: true } }] });
  const report = await f.service.capabilities(actor);
  assert.equal(report.providerDiscoveredAbilities, 84);
  assert.equal(report.backendExposedTools, Object.values(toolNames).length); // v2 absent, plus one unknown tool.
  assert.equal(report.capabilities.find(c => c.capability === 'future_mutator_v1').integration, 'integration_required');
  assert.equal(report.capabilities.find(c => c.capability === toolNames.contentRecord).workflow, 'read_website_content');
  assert.equal(report.content.acf, 'structured_read_available_editing_requires_qualification');
  assert.equal(report.content.posts, 'structured_mainwp');
  assert.equal(report.content.pages, 'structured_mainwp');
  assert.deepEqual(report.content.readRoutes, { record: 'structured_mainwp', search: 'structured_mainwp' });
  assert.equal(f.state.calls.length, 0);
});

test('content inventory does not claim unavailable readers and distinguishes partial discovery from configured REST', async t => {
  const f = setup(); t.after(f.close); f.state.contentManagement = true;
  const original = f.client.listTools.bind(f.client);
  let available = [];
  f.client.listTools = async () => ({ tools: (await original()).tools
    .filter(tool => ![toolNames.contentRecord, toolNames.contentRecords].includes(tool.name) || available.includes(tool.name)) });
  const cases = [
    [[], false, 'unavailable', 'unavailable', 'unavailable'],
    [[], true, 'native_rest_route', 'native_rest_route', 'native_rest_route'],
    [[toolNames.contentRecord], false, 'partially_available', 'structured_mainwp', 'unavailable'],
    [[toolNames.contentRecords], false, 'partially_available', 'unavailable', 'structured_mainwp'],
    [[toolNames.contentRecord], true, 'mixed_mainwp_routes', 'structured_mainwp', 'native_rest_route'],
    [[toolNames.contentRecords], true, 'mixed_mainwp_routes', 'native_rest_route', 'structured_mainwp'],
  ];
  for (const [tools, native, availability, record, search] of cases) {
    available = tools; f.service.content = native ? {} : undefined;
    const report = await f.service.capabilities(actor);
    assert.equal(report.content.posts, availability); assert.equal(report.content.pages, availability);
    assert.deepEqual(report.content.readRoutes, { record, search });
    assert.equal(report.content.acf, tools.includes(toolNames.contentRecord)
      ? 'structured_read_available_editing_requires_qualification' : 'qualification_required');
    assert.match(report.content.scope, /do not prove.*authorization/);
  }
  assert.equal(f.state.calls.length, 0);
});

test('capability discovery withholds its inventory if access is revoked while the directory is pending', async t => {
  const f = setup(); t.after(f.close);
  const original = f.client.listTools.bind(f.client);
  f.client.listTools = async () => { const result = await original(); f.policy.members[0].enabled = false; return result; };
  await assert.rejects(() => f.service.capabilities(actor), { code: 'FORBIDDEN' });
  assert.equal(f.state.calls.length, 0);
  f.policy.members[0].enabled = true;
  let suspended = false, checkedAfterDirectory = false;
  f.service.verifyStaff = async () => {
    if (suspended) { checkedAfterDirectory = true; throw Object.assign(new Error('Current membership denied'), { code: 'STAFF_ACCESS_DENIED' }); }
  };
  f.client.listTools = async () => { const result = await original(); suspended = true; return result; };
  await assert.rejects(() => f.service.capabilities(actor), { code: 'STAFF_ACCESS_DENIED' });
  assert.equal(checkedAfterDirectory, true);
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

test('aggregate updates retain portfolio authorization, bounded filters and cached-inventory scope', async t => {
  const f = setup(); t.after(f.close);
  await assert.rejects(() => f.service.portfolioDetails(actor, { section: 'updates' }), { code: 'FORBIDDEN' });
  f.policy.members[0].portfolio = { sources: ['mainwp'], actions: ['read'] };
  const list = f.client.listTools.bind(f.client);
  f.client.listTools = async () => ({ tools: [...(await list()).tools, { name: 'list_updates_v1', inputSchema: { type: 'object' } }] });
  let calls = 0;
  f.client.callTool = async ({ name, arguments: args }) => {
    calls++; assert.equal(name, 'list_updates_v1'); assert.deepEqual(args, { page: 2, per_page: 100, types: ['plugins', 'themes'] });
    return { content: [{ type: 'text', text: JSON.stringify({ updates: [{ site_id: 1, name: 'Example', current_version: '1', new_version: '2', token: 'private' }], total: 101 }) }] };
  };
  const result = await f.service.portfolioDetails(actor, { section: 'updates', page: 2, types: ['plugins', 'themes'] });
  assert.deepEqual(result.data.updates[0], { site_id: 1, name: 'Example', current_version: '1', new_version: '2' });
  assert.match(result.scope, /Cached.*no child sync/);
  for (const input of [{ section: 'updates', types: [] }, { section: 'updates', types: ['plugins', 'plugins'] }, { section: 'updates', types: ['shell'] },
    { section: 'updates', search: 'site' }, { section: 'updates', recordId: 1 }, { section: 'sites', types: ['plugins'] }])
    await assert.rejects(() => f.service.portfolioDetails(actor, input));
  assert.equal(calls, 1);
});

test('batch status reads select one bounded existing ID and discard results after revocation', async t => {
  const f = setup(); t.after(f.close);
  await assert.rejects(() => f.service.portfolioDetails(actor, { section: 'batch_job', batchJobId: 'sync_fixture-123' }), { code: 'FORBIDDEN' });
  f.policy.members[0].portfolio = { sources: ['mainwp'], actions: ['read'] };
  const list = f.client.listTools.bind(f.client);
  f.client.listTools = async () => ({ tools: [...(await list()).tools, { name: 'get_batch_job_status_v1', inputSchema: { type: 'object' } }] });
  let calls = 0;
  f.client.callTool = async ({ name, arguments: args }) => {
    calls++; assert.equal(name, 'get_batch_job_status_v1'); assert.deepEqual(args, { job_id: 'sync_fixture-123' });
    if (calls === 2) f.policy.members[0].enabled = false;
    return { content: [{ type: 'text', text: JSON.stringify({ status: 'running', confirmation_token: 'private' }) }] };
  };
  const result = await f.service.portfolioDetails(actor, { section: 'batch_job', batchJobId: 'sync_fixture-123' });
  assert.deepEqual(result.data, { status: 'running' }); assert.equal(result.batchJobId, 'sync_fixture-123');
  for (const input of [{ section: 'batch_job' }, { section: 'batch_job', batchJobId: '../job' }, { section: 'batch_job', batchJobId: 'sync_fixture-123', page: 2 },
    { section: 'batch_job', batchJobId: 'sync_fixture-123', search: 'site' }, { section: 'sites', batchJobId: 'sync_fixture-123' }])
    await assert.rejects(() => f.service.portfolioDetails(actor, input));
  assert.equal(calls, 1);
  await assert.rejects(() => f.service.portfolioDetails(actor, { section: 'batch_job', batchJobId: 'sync_fixture-123' }), { code: 'FORBIDDEN' });
});
