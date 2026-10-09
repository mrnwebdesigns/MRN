import test from 'node:test';
import assert from 'node:assert/strict';
import { mkdirSync, writeFileSync, symlinkSync, rmSync } from 'node:fs';
import { join } from 'node:path';
import { Discovery } from '../src/discovery.mjs';
import { readLocalHubRecords } from '../src/knowledge.mjs';
import { Registry, policySchema } from '../src/contracts.mjs';
import { actor, setup, prepared, execute } from './helpers.mjs';
import { toolNames, MainwpSession } from '../src/mainwp.mjs';

function enable(f, sources = ['mainwp', 'local-hub']) {
  f.registryData.websites = [];
  f.policy.members[0].grants = [];
  f.policy.members[0].portfolio = { sources, actions: ['read'] };
  f.state.directory = [{ id: 1, url: f.state.url, name: 'Example Client', password: 'never-copy-secret' }];
  const recordsRoot = join(f.root, 'existing-sites'); mkdirSync(recordsRoot);
  f.service.discovery = new Discovery({ registry: f.registry, store: f.store, connect: async () => f.client, localHubRoots: [recordsRoot] });
  return recordsRoot;
}
function manifest(root, slug = 'example', overrides = {}) {
  const dir = join(root, slug); mkdirSync(dir, { recursive: true });
  writeFileSync(join(dir, '.mrn-site.json'), JSON.stringify({ title: 'Client Alias', localUrl: 'https://example.localhost', liveUrl: 'https://example.test',
    deploymentEnvironment: 'development', provider: 'mrndev', deploymentGithubRepo: 'mrnwebdesigns/example-child', deploymentGithubBranch: 'main',
    deploymentGithubWorkflow: 'site-deploy.yml', deploymentMethod: 'github-actions', updatedAt: '2026-01-01T00:00:00.000Z',
    dbPassword: 'never-copy-secret', remoteSsh: 'private-user@private-host', notes: 'do not disclose notes', ...overrides }));
}

test('an empty Operations registry discovers MainWP websites and inspects one without enrollment or a bulk sync', async t => {
  const f = setup(); t.after(() => f.close()); enable(f, ['mainwp']);
  const result = await f.service.list(actor);
  assert.equal(result.websites.length, 1); assert.equal(result.websites[0].name, 'Example Client');
  assert.equal(result.websites[0].environment, 'unknown'); assert.deepEqual(result.websites[0].missingKnowledge, ['environment', 'backup route']);
  assert.equal(f.state.calls.filter(n => n === toolNames.sync).length, 0);
  assert.ok(!JSON.stringify(result).includes('never-copy-secret')); assert.equal(f.registryData.websites.length, 0);
  const inspection = await f.service.inspect(actor, { website: 'Example Client' });
  assert.equal(inspection.target.url, f.state.url); assert.ok(inspection.findings.some(i => i.code === 'STACK_PLUGIN_BEHIND'));
  assert.equal(f.state.calls.filter(n => n === toolNames.sync).length, 1);
  assert.ok(inspection.coverage.some(c => c.check === 'website_knowledge' && c.status === 'incomplete'));
  const finding = inspection.findings.find(i => i.code === 'STACK_PLUGIN_BEHIND');
  await assert.rejects(() => f.service.prepare(actor, { inspectionId: inspection.id, findingId: finding.id, requestKey: 'read-only' }), { code: 'FORBIDDEN' });
  assert.equal(f.state.mutations, 0); assert.equal(f.state.backups, 0);
});

test('Local Hub records enrich exact URLs and preserve local/remote relationships, freshness and secret exclusion', async t => {
  const f = setup(); t.after(() => f.close()); const root = enable(f); manifest(root);
  const result = await f.service.list(actor); assert.equal(result.websites.length, 2);
  const remote = result.websites.find(w => w.url === f.state.url); const local = result.websites.find(w => w.environment === 'local');
  assert.equal(remote.websiteId, local.websiteId); assert.equal(remote.environment, 'development'); assert.equal(local.management, 'dedicated');
  assert.ok(remote.facts.some(v => v.key === 'deployment.repository' && v.value === 'mrnwebdesigns/example-child' && v.stale));
  assert.ok(remote.facts.every(v => v.source && v.observedAt && v.expiresAt));
  assert.ok(!/never-copy-secret|private-user|private-host|disclose notes/.test(JSON.stringify(result)));
  await assert.rejects(() => f.service.inspect(actor, { website: 'Client Alias' }), { code: 'TARGET_AMBIGUOUS' });
  const report = await f.service.inspect(actor, { website: 'Client Alias', environment: 'development' });
  assert.equal(report.target.url, f.state.url);
});

test('directory additions and removals appear without editing Operations configuration or losing historical evidence', async t => {
  const f = setup(); t.after(() => f.close()); enable(f, ['mainwp']);
  const first = await f.service.list(actor); const inspection = await f.service.inspect(actor, { website: f.state.url });
  f.state.directory.push({ id: 2, url: 'https://second.test', name: 'Second' });
  assert.equal((await f.service.list(actor)).websites.length, 2);
  assert.equal((await f.service.history(actor, f.state.url))[0].id, inspection.id);
  f.state.directory = f.state.directory.slice(1);
  assert.equal((await f.service.list(actor)).websites.length, 1);
  await assert.rejects(() => f.service.inspect(actor, { website: first.websites[0].url }), { code: 'TARGET_UNAVAILABLE' });
  assert.equal(f.service.get(actor, inspection.id).id, inspection.id);
});

test('inspection resolves the site ID again rather than trusting the ID seen during discovery', async t => {
  const f = setup(); t.after(() => f.close()); enable(f, ['mainwp']); f.state.directory[0].id = 99;
  await f.service.list(actor);
  const call = f.client.callTool.bind(f.client); let synchronized;
  f.client.callTool = async args => {
    const result = await call(args);
    if (args.name === toolNames.site) { const data = JSON.parse(result.content[0].text); data.id = 1; result.content[0].text = JSON.stringify(data); }
    if (args.name === toolNames.sync) synchronized = args.arguments.site_ids;
    return result;
  };
  await f.service.inspect(actor, { website: f.state.url }); assert.deepEqual(synchronized, [1]);
});

test('basic inventory pagination is complete and duplicate or moving pages are rejected without retaining a partial directory', async t => {
  const f = setup(); t.after(() => f.close()); enable(f, ['mainwp']);
  f.state.directory = Array.from({ length: 101 }, (_, i) => ({ id: i + 1, url: `https://site${i}.test`, name: `Site ${i}` }));
  assert.equal((await f.service.list(actor)).websites.length, 101);
  assert.equal(f.state.calls.filter(n => n === 'get_sites_basic_v1').length, 2);
  f.state.directory[100] = f.state.directory[0];
  await assert.rejects(() => f.service.list(actor), { code: 'DISCOVERY_CONFLICT' });
  assert.equal(f.registry.discovered.has(actor.subject), false);
  f.state.directory = [];
  assert.equal((await f.service.list(actor)).websites.length, 0);
});

test('a scoped URL grant discovers only that exact MainWP site, without bulk access or a site registry entry', async t => {
  const f = setup(); t.after(() => f.close()); enable(f);
  delete f.policy.members[0].portfolio;
  f.policy.members[0].grants = [{ website: f.state.url, environments: ['unknown'], actions: ['read'] }];
  f.state.directory.push({ id: 2, url: 'https://private.test', name: 'Private Client' });
  const result = await f.service.list(actor);
  assert.equal(result.websites.length, 1); assert.equal(result.websites[0].url, f.state.url);
  assert.ok(!f.state.calls.includes('get_sites_basic_v1')); assert.equal(f.state.calls.filter(n => n === toolNames.site).length, 1);
  assert.ok(!JSON.stringify(result).includes('Private Client'));
  const report = await f.service.inspect(actor, { website: f.state.url }); assert.equal(report.target.url, f.state.url);
});

test('unregistered, disabled and site-only members cannot use a broadly privileged service credential for discovery', async t => {
  const f = setup(); t.after(() => f.close()); enable(f); let connections = 0;
  f.service.discovery.connect = async () => { connections++; return f.client; };
  await assert.rejects(() => f.service.list({ ...actor, subject: 'not-a-member' }), { code: 'FORBIDDEN' });
  const reader = { ...actor, subject: 'reader' };
  assert.deepEqual(await f.service.list(reader), { websites: [], sources: [], issues: [] });
  f.policy.members[0].enabled = false;
  await assert.rejects(() => f.service.list(actor), { code: 'FORBIDDEN' }); assert.equal(connections, 0);
});

test('revocation between directory pages stops before the next downstream call and discards the partial result', async t => {
  const f = setup(); t.after(() => f.close()); enable(f, ['mainwp']);
  f.state.directory = Array.from({ length: 101 }, (_, i) => ({ id: i + 1, url: `https://site${i}.test`, name: 'Client' }));
  const call = f.client.callTool.bind(f.client);
  f.client.callTool = async args => { const result = await call(args); if (args.name === 'get_sites_basic_v1') f.policy.members[0].enabled = false; return result; };
  await assert.rejects(() => f.service.list(actor), { code: 'FORBIDDEN' });
  assert.equal(f.state.calls.length, 1); assert.equal(f.registry.discovered.has(actor.subject), false);
});

for (const failure of ['identity', 'authentication', 'capability', 'pagination']) test(`directory ${failure} failure does not use stale inventory or another credential/route`, async t => {
  const f = setup(); t.after(() => f.close()); enable(f, ['mainwp']); await f.service.list(actor);
  if (failure === 'identity') f.state.host = 'wrong-dashboard.test';
  if (failure === 'authentication') f.client.readResource = async () => { throw new Error('401 token secret-sentinel'); };
  if (failure === 'capability') f.state.missing = ['get_sites_basic_v1'];
  if (failure === 'pagination') {
    const call = f.client.callTool.bind(f.client);
    f.client.callTool = async args => { const result = await call(args); if (args.name === 'get_sites_basic_v1') result.content[0].text = JSON.stringify({ items: [], page: 1, per_page: 100, total: 2 }); return result; };
  }
  await assert.rejects(() => f.service.list(actor), { code: { identity: 'MAINWP_IDENTITY', authentication: 'MAINWP_ACCESS', capability: 'CAPABILITY_MISSING', pagination: 'DISCOVERY_INCOMPLETE' }[failure] });
  assert.equal(f.registry.discovered.has(actor.subject), false); assert.equal(f.state.backups, 0);
});

test('conflicting environment records remain inspectable and cannot authorize a write', async t => {
  const f = setup(); t.after(() => f.close()); const root = enable(f);
  manifest(root, 'dev', { localUrl: '', deploymentEnvironment: 'development' }); manifest(root, 'live', { localUrl: '', deploymentEnvironment: 'production' });
  const result = await f.service.list(actor); const site = result.websites[0];
  assert.equal(site.environment, 'unknown'); assert.ok(site.knowledgeIssues.some(s => /Conflicting/.test(s)));
  f.policy.members[0].grants = [{ website: f.state.url, environments: ['unknown'], actions: ['read', 'repair'] }];
  const inspection = await f.service.inspect(actor, { website: f.state.url });
  await assert.rejects(() => f.service.prepare(actor, { inspectionId: inspection.id, findingId: inspection.findings[0].id, requestKey: 'conflict' }), { code: 'KNOWLEDGE_REQUIRED' });
});

test('a matching display name never joins different websites, and URL selection resolves ambiguity', async t => {
  const f = setup(); t.after(() => f.close()); enable(f, ['mainwp']);
  f.state.directory.push({ id: 2, url: 'https://elsewhere.test', name: 'Example Client' });
  assert.equal((await f.service.list(actor)).websites.length, 2);
  await assert.rejects(() => f.service.inspect(actor, { website: 'Example Client' }), { code: 'TARGET_AMBIGUOUS' });
  assert.equal((await f.service.inspect(actor, { website: f.state.url })).target.url, f.state.url);
});

test('local-only discovery uses documented records without adding the site to MainWP or using its credentials', async t => {
  const f = setup(); t.after(() => f.close()); const root = enable(f, ['local-hub']); manifest(root);
  f.service.discovery.connect = async () => { assert.fail('Local Hub discovery must not connect to MainWP'); };
  const sites = await f.service.list(actor); assert.equal(sites.websites.length, 2);
  assert.ok(sites.websites.every(w => w.management === 'dedicated'));
  const report = await f.service.inspect(actor, { website: f.state.url });
  assert.ok(report.coverage.some(c => c.check === 'management' && c.status === 'unavailable')); assert.equal(f.state.calls.length, 0);
});

test('Local Hub reader ignores directory symlinks, rejects manifest symlinks, and reports malformed records', async t => {
  const f = setup(); t.after(() => f.close()); const root = enable(f); manifest(root);
  const outside = join(f.root, 'outside'); mkdirSync(outside); manifest(outside, 'hidden');
  symlinkSync(join(outside, 'hidden'), join(root, 'link'));
  assert.equal(readLocalHubRecords([root], () => {}).records.length, 1);
  mkdirSync(join(root, 'bad')); writeFileSync(join(root, 'bad', '.mrn-site.json'), 'invalid JSON');
  assert.equal(readLocalHubRecords([root], () => {}).issues[0].code, 'INVALID_MANIFEST');
  mkdirSync(join(root, 'escape')); symlinkSync(join(outside, 'hidden', '.mrn-site.json'), join(root, 'escape', '.mrn-site.json'));
  assert.throws(() => readLocalHubRecords([root], () => {}), { code: 'KNOWLEDGE_SOURCE_INVALID' });
  rmSync(join(root, 'escape'), { recursive: true });
  assert.throws(() => readLocalHubRecords([join(root, 'missing')], () => {}), { code: 'KNOWLEDGE_SOURCE_UNAVAILABLE' });
});

test('unsupported HTTP identities are reported as coverage gaps rather than silently upgraded to HTTPS', async t => {
  const f = setup(); t.after(() => f.close()); enable(f, ['mainwp']);
  f.state.directory.push({ id: 2, url: 'http://legacy.test', name: 'Legacy' });
  const result = await f.service.list(actor); assert.equal(result.websites.length, 1);
  assert.deepEqual(result.issues, [{ source: 'mainwp', siteId: 2, name: 'Legacy', code: 'UNSUPPORTED_SITE_URL' }]);
});

test('discovery freshness updates do not change an approved explicit target; write gates remain intact', async t => {
  const f = setup(); t.after(() => f.close());
  f.policy.members[0].portfolio = { sources: ['mainwp'], actions: ['read'] };
  f.state.directory = [{ id: 1, url: f.state.url, name: 'Example Client' }];
  f.service.discovery = new Discovery({ registry: f.registry, store: f.store, connect: async () => f.client });
  const { op } = await prepared(f); assert.equal(op.status, 'prepared');
  await f.service.list(actor);
  assert.equal((await execute(f, op)).status, 'verified');
  assert.equal(f.state.mutations, 1); assert.equal(f.state.backups, 1);
});

test('per-site workflow sessions cannot access bulk discovery and portfolio permissions cannot include releases', async t => {
  const f = setup(); t.after(() => f.close()); enable(f, ['mainwp']); await f.service.list(actor);
  const target = f.registry.resolve(actor, f.state.url);
  const session = new MainwpSession(f.client, f.registry, f.store, actor, target);
  await assert.rejects(() => session.call('get_sites_basic_v1', { page: 1, per_page: 100 }), { code: 'CAPABILITY_DENIED' });
  f.policy.members[0].portfolio.actions.push('release_production');
  assert.equal(policySchema.safeParse(f.policy).success, false);
});

test('source cache is isolated by subject and cannot survive a permissions change', async t => {
  const f = setup(); t.after(() => f.close()); enable(f, ['mainwp']); await f.service.list(actor);
  const reader = { ...actor, subject: 'reader' };
  assert.equal((await f.service.list(reader)).websites.length, 0);
  delete f.policy.members[0].portfolio;
  assert.throws(() => f.registry.resolve(actor, f.state.url), { code: 'DISCOVERY_STALE' });
  assert.equal((await f.service.list(actor)).websites.length, 0);
  const registry = new Registry(() => ({ version: 1, websites: [] }), () => f.policy);
  assert.equal(registry.list(actor).length, 0);
});

test('an override using the discovered identity retains related environments and cannot claim an unrelated URL', async t => {
  const f = setup(); t.after(() => f.close()); const root = enable(f); manifest(root);
  const first = await f.service.list(actor); const identity = first.websites[0].websiteId;
  f.registryData.websites = [{ id: identity, name: 'Approved Client', environments: [{ name: 'development', url: f.state.url, management: 'mainwp', backup: 'updraft' }] }];
  const second = await f.service.list(actor);
  assert.equal(second.websites.length, 2); assert.ok(second.websites.every(w => w.websiteId === identity));
  assert.equal(f.registry.resolve(actor, f.state.url).backup, 'updraft');
  assert.equal(f.registry.resolve(actor, 'https://example.localhost').environment, 'local');
  f.registryData.websites[0].environments[0].url = 'https://unrelated.test';
  await assert.rejects(() => f.service.list(actor), { code: 'REGISTRY_INVALID' });
});

test('missing knowledge on another permitted site does not block an exact known repair target', t => {
  const f = setup(); t.after(() => f.close());
  f.registryData.websites.unshift({ id: 'unknown', name: 'Unknown', environments: [{ name: 'unknown', url: 'https://unknown.test', management: 'mainwp', backup: 'unknown' }] });
  f.policy.members[0].grants.push({ website: 'unknown', environments: ['unknown'], actions: ['read', 'repair'] });
  assert.equal(f.registry.resolve(actor, f.state.url, 'development', 'repair').url, f.state.url);
  assert.throws(() => f.registry.resolve(actor, 'https://unknown.test', undefined, 'repair'), { code: 'KNOWLEDGE_REQUIRED' });
});

test('scoped discovery rejects one MainWP ID returned for two different permitted URLs', async t => {
  const f = setup(); t.after(() => f.close()); enable(f); delete f.policy.members[0].portfolio;
  f.state.directory.push({ id: 1, url: 'https://different.test', name: 'Different' });
  f.policy.members[0].grants = f.state.directory.map(w => ({ website: w.url, environments: ['unknown'], actions: ['read'] }));
  await assert.rejects(() => f.service.list(actor), { code: 'DISCOVERY_CONFLICT' });
  assert.equal(f.registry.discovered.has(actor.subject), false); assert.equal(f.state.mutations, 0);
});
