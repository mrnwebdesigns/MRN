import test from 'node:test';
import assert from 'node:assert/strict';
import { setup, actor } from './helpers.mjs';
import { responseEvidence } from '../src/probe.mjs';
import { OpsError } from '../src/contracts.mjs';
import { toolNames } from '../src/mainwp.mjs';
import { response } from './fixtures/mainwp.mjs';

const html = '<html lang="en"><head><title>Website</title><link href="/site.css"></head><body><form></form></body></html>';
const rest = '{"namespaces":["wp/v2"],"routes":{"/wp/v2":{}}}';
const measure = (url, body, status = 200, options) => responseEvidence(url, status, Buffer.from(body), { ttfbMs: 100, totalMs: 150 }, options);
const inspect = f => f.service.inspect(actor, { website: 'example' });
const unavailable = 'Ability execution failed: mrn_mainwp_stack_report_unavailable - The child site did not return an MRN stack runtime report.';
function runtimeFailure(f, message = unavailable, qualification = {}) {
  const call = f.client.callTool.bind(f.client);
  f.client.callTool = async request => {
    if (request.name === toolNames.runtime) return { isError: true, ...response({ error: { message } }) };
    if (request.name === toolNames.qualify) {
      f.state.calls.push(request.name);
      assert.deepEqual(request.arguments, { site_id: f.state.id });
      if (qualification instanceof Error) throw qualification;
      return response({ site_id: f.state.id, site_url: f.state.url, classification: 'deployment_agent_upgrade_required',
        agent: { available: true, version: '0.1.7', managed_credentials: { secret: 'private-sentinel' }, message: 'private-sentinel' },
        runtime: { available: false, message: 'private-sentinel' },
        blockers: ['deployment_agent_upgrade_required', 'site_theme_shape_unavailable', 'private-sentinel'], ...qualification });
    }
    return call(request);
  };
}

test('HTML and assets contain no REST verdict; an explicit valid REST-root check passes', () => {
  const page = measure('https://example.test', html, 200, { references: ['https://example.test/site.css'] });
  assert.equal(page.titlePresent, true); assert.equal(page.langPresent, true); assert.equal(page.forms, 1);
  assert.deepEqual(page.references, [{ url: 'https://example.test/site.css', present: true }]);
  assert.equal(Object.hasOwn(page, 'restHealthy'), false);
  assert.equal(Object.hasOwn(measure('https://example.test/site.css', 'body{}'), 'restHealthy'), false);
  assert.equal(measure('https://example.test/wp-json/', rest, 200, { checkRest: true }).restHealthy, true);
});

for (const [label, body, status] of [
  ['HTML', html, 200], ['invalid JSON', '{', 200], ['null', 'null', 200], ['array root', '[]', 200],
  ['missing routes', '{"namespaces":[]}', 200], ['null routes', '{"namespaces":[],"routes":null}', 200],
  ['array routes', '{"namespaces":[],"routes":[]}', 200], ['error JSON', '{"code":"rest_forbidden"}', 200],
  ['HTTP 403', rest, 403], ['redirect', rest, 301],
]) test(`${label} cannot pass the public REST-root check`, () => {
  assert.equal(measure('https://example.test/wp-json/', body, status, { checkRest: true }).restHealthy, false);
});

test('inspection requests the actual subdirectory REST root and separates its evidence from HTML', async t => {
  const f = setup(); t.after(() => f.close());
  f.state.url = 'https://example.test/wordpress'; f.registryData.websites[0].environments[0].url = f.state.url;
  const calls = [];
  f.service.publicProbe = async (url, authorize, options) => {
    authorize(); calls.push(url);
    return measure(url, url.endsWith('/wp-json/') ? rest : html, 200, options);
  };
  const result = await inspect(f);
  assert.deepEqual(calls, [f.state.url, `${f.state.url}/wp-json/`]);
  assert.equal(Object.hasOwn(result.evidence.find(e => e.source === 'public:sample'), 'restHealthy'), false);
  const api = result.evidence.find(e => e.source === 'public:wordpress-rest');
  assert.equal(api.sourceUrl, calls[1]); assert.equal(api.status, 200); assert.equal(api.restHealthy, true);
  assert.ok(Number.isFinite(Date.parse(api.measuredAt)));
  assert.equal(result.coverage.find(c => c.check === 'wordpress_rest').status, 'checked');
  assert.ok(!result.findings.some(f => f.code === 'REST_ROOT_CHECK_FAILED'));
});

test('denied public REST response is a bounded nonrepairable finding, not proof of site-wide API failure', async t => {
  const f = setup(); t.after(() => f.close());
  f.service.publicProbe = async (url, authorize, options) => {
    authorize(); return measure(url, html, url.endsWith('/wp-json/') ? 403 : 200, options);
  };
  const result = await inspect(f);
  assert.equal(result.coverage.find(c => c.check === 'wordpress_rest').status, 'unhealthy');
  const finding = result.findings.find(f => f.code === 'REST_ROOT_CHECK_FAILED');
  assert.equal(finding.repairable, false); assert.match(finding.description, /Access policy/);
  assert.equal(result.evidence.find(e => e.source === 'public:sample').status, 200);
  assert.equal(f.state.mutations, 0); assert.equal(f.state.backups, 0);
});

test('unreachable REST root records a blocked check without inventing a failed response', async t => {
  const f = setup(); t.after(() => f.close());
  f.service.publicProbe = async (...args) => {
    if (args[0].endsWith('/wp-json/')) throw new Error('private-sentinel connection failure');
    return f.publicProbe(...args);
  };
  const result = await inspect(f);
  assert.equal(result.coverage.find(c => c.check === 'wordpress_rest').status, 'blocked');
  assert.ok(!result.evidence.some(e => e.source === 'public:wordpress-rest'));
  assert.ok(!JSON.stringify(result).includes('private-sentinel'));
});

for (const stage of ['homepage', 'REST']) for (const code of ['FORBIDDEN', 'AUTH_REQUIRED']) test(`${code} at ${stage} aborts inspection without further probes or a saved result`, async t => {
  const f = setup(); t.after(() => f.close()); let count = 0;
  f.service.publicProbe = async (...args) => {
    count++;
    if (stage === 'homepage' || args[0].endsWith('/wp-json/')) throw new OpsError(code, 'Identity unavailable.');
    return f.publicProbe(...args);
  };
  await assert.rejects(() => inspect(f), { code });
  assert.equal(count, stage === 'homepage' ? 1 : 2);
  assert.equal((await f.service.history(actor, 'example')).length, 0);
});

test('missing runtime report gets exact-site read-only qualification with allowlisted persisted facts', async t => {
  const f = setup(); t.after(() => f.close()); runtimeFailure(f);
  const result = await inspect(f);
  assert.equal(result.coverage.find(c => c.check === 'stack').status, 'qualification_required');
  const evidence = result.evidence.find(e => e.source === 'mainwp:stack-qualification');
  assert.equal(evidence.classification, 'deployment_agent_upgrade_required');
  assert.equal(evidence.agentVersion, '0.1.7'); assert.equal(evidence.runtimeAvailable, false);
  assert.deepEqual(evidence.blockers, ['deployment_agent_upgrade_required', 'site_theme_shape_unavailable']);
  assert.ok(result.findings.some(f => f.code === 'STACK_QUALIFICATION' && !f.repairable));
  assert.ok(!JSON.stringify(f.service.get(actor, result.id)).includes('private-sentinel'));
  assert.equal(f.state.mutations, 0); assert.equal(f.state.backups, 0);
});

for (const reason of ['generic failure', 'permission denied', 'authentication failed mrn_mainwp_stack_report_unavailable']) test(`${reason} never invokes the missing-report qualification path`, async t => {
  const f = setup(); t.after(() => f.close()); runtimeFailure(f, reason);
  if (reason === 'generic failure') {
    const result = await inspect(f);
    assert.equal(result.coverage.find(c => c.check === 'stack').code, 'MAINWP_FAILED');
  } else await assert.rejects(() => inspect(f), { code: 'MAINWP_ACCESS' });
  assert.ok(!f.state.calls.includes(toolNames.qualify));
});

for (const mismatch of [{ site_id: 999 }, { site_url: 'https://other.test' }]) test(`qualification rejects a different ${Object.keys(mismatch)[0]}`, async t => {
  const f = setup(); t.after(() => f.close()); runtimeFailure(f, unavailable, mismatch);
  await assert.rejects(() => inspect(f), { code: 'TARGET_MISMATCH' });
});

test('qualification access failure stops; missing capability or ordinary failure stays explicitly blocked', async t => {
  const f = setup(); t.after(() => f.close());
  runtimeFailure(f, unavailable, new Error('403 permission denied'));
  await assert.rejects(() => inspect(f), { code: 'MAINWP_ACCESS' });
  f.state.missing = [toolNames.qualify];
  const missing = await inspect(f);
  assert.equal(missing.coverage.find(c => c.check === 'stack').code, 'STACK_REPORT_UNAVAILABLE');
  assert.ok(!missing.evidence.some(e => e.source === 'mainwp:stack-qualification'));
  f.state.missing = []; runtimeFailure(f, unavailable, new Error('private-sentinel'));
  const failed = await inspect(f);
  assert.equal(failed.coverage.find(c => c.check === 'stack_qualification').status, 'blocked');
  assert.ok(!JSON.stringify(failed).includes('private-sentinel'));
});

test('Fleet smoke explicitly checks REST and rejects HTML returned from its root', async t => {
  const f = setup(); t.after(() => f.close());
  const target = f.registry.resolve(actor, 'example');
  const session = { authorize: () => f.registry.current(actor, target, 'read') };
  assert.equal((await f.fleet.smoke(target, session, { assets: [] }))[1].restHealthy, true);
  f.fleet.publicProbe = async (url, authorize, options) => { authorize(); return measure(url, html, 200, options); };
  await assert.rejects(() => f.fleet.smoke(target, session, { assets: [] }), { code: 'PUBLIC_VERIFICATION' });
});
