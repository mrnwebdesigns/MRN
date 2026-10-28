// Run against the built, immutable MCP dependency named in the audit source lock.
import test from 'node:test';
import assert from 'node:assert/strict';
import { generateKeyPairSync, randomBytes, verify } from 'node:crypto';
import { pathToFileURL } from 'node:url';
import { mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { createServer as reservePort } from 'node:net';
import { EmailAuditWorkflow, EmailAuditStorage, auditOwner } from '../../src/email-audit.mjs';
import { DelegatedMainwpClient, delegationTools } from '../../src/delegated-mainwp.mjs';
import { IdentitySigner, delegationDigest, delegationPrefix } from '../../src/delegation.mjs';
import { Registry, OpsError } from '../../src/contracts.mjs';
import { Store } from '../../src/store.mjs';
import { createHttpServer } from '../../src/http.mjs';

assert.ok(process.env.MRN_EMAIL_AUDIT_ENGINE, 'Specify the reviewed built MCP audit engine');
const { executeEmailAuditWithTransport: engine } = await import(pathToFileURL(process.env.MRN_EMAIL_AUDIT_ENGINE));
const response = value => ({ content: [{ type: 'text', text: JSON.stringify(value) }] });
const url = 'https://mrnwebdesigns.example';
const address = 'maintenance@mrnwebdesigns.com';
const person = (id = '123') => ({ subject: `google-oauth2|${id}`, googleSubject: id, verifiedEmail: `staff${id}@mrnwebdesigns.com`,
  identityProvider: 'google-oauth2', hostedDomain: 'mrnwebdesigns.com', googleConnection: 'con_MRNGoogle', expiresAt: Date.now() + 300000, scopes: ['mrn:operations'] });
function fixture(t) {
  const root = mkdtempSync(join(tmpdir(), 'mrn-audit-')), file = join(root, 'audit.sqlite');
  const store = new Store(file); t.after(() => { store.close(); rmSync(root, { recursive: true, force: true }); });
  const policy = { version: 1, members: [], emailDomains: [{ domain: 'mrnwebdesigns.com', enabled: true, portfolio: { sources: ['mainwp'], actions: ['read'] } }] };
  const registry = new Registry(() => ({ version: 1, websites: [] }), () => policy);
  const { privateKey, publicKey } = generateKeyPairSync('ed25519');
  const signer = new IdentitySigner({ keyId: 'fixture', privateKey, connectionId: 'con_MRNGoogle' });
  const state = { calls: [], denied: false, page: 0, drift: false, failSync: false, expireOnInspect: false, afterCall: null };
  const raw = {
    async readResource() { return { contents: [{ text: JSON.stringify({ connected: true, dashboardHost: 'wpcontrol.mrndev.io', abilitiesCount: 121 }) }] }; },
    async listTools() { return { tools: Object.values(delegationTools).map(name => ({ name, annotations: { readOnlyHint: false, destructiveHint: name === delegationTools.run } })) }; },
    async close() {},
    async callTool({ name, arguments: args }) {
      if (name === delegationTools.challenge) return response({ nonce: randomBytes(32).toString('hex'), expires_at: Math.floor(Date.now() / 1000) + 60,
        authenticated_connection_sha256: 'a'.repeat(64), policy_sha256: 'b'.repeat(64) });
      assert.ok([delegationTools.access, delegationTools.read, delegationTools.sync].includes(name), 'No raw calls or write wrappers');
      const bytes = Buffer.from(args.delegation.payload_base64, 'base64');
      assert.ok(verify(null, Buffer.concat([Buffer.from(delegationPrefix), bytes]), publicKey, Buffer.from(args.delegation.signature_base64, 'base64')));
      const claims = JSON.parse(bytes), actor = { oauth_subject: claims.oauth_subject, google_sub: claims.google_sub, email: claims.email };
      if (state.denied) throw new OpsError('AUTH_REQUIRED', 'Current access denied.');
      if (name === delegationTools.access) return response({ ...actor, schema: 1, roles: ['reader'], portfolio_access: true,
        expires_at: claims.expires_at, policy_sha256: claims.policy_sha256 });
      const input = JSON.parse(args.arguments_json);
      assert.equal(delegationDigest(input), claims.arguments_sha256); assert.equal(args.ability, claims.ability);
      state.calls.push({ ability: args.ability, input, actor });
      let result;
      switch (args.ability) {
        case 'mainwp/list-sites-v1': result = { items: [{ id: 7, url: state.drift ? 'https://changed.example' : url }], total: 1 }; break;
        case 'mrn-mainwp/authorize-email-address-audit-v1':
          assert.deepEqual(input.sites, [{ site_id: 7, site_url: url }]);
          result = { contract: 'mrn-email-address-audit-v1', authorized: true, actor_id: 1, site_count: 1 }; break;
        case 'mainwp/get-site-knowledge-v1': result = {}; break;
        case 'mainwp/sync-sites-v1':
          assert.deepEqual(input, { site_ids: [7] }); if (state.failSync) throw new Error('timeout'); result = {}; break;
        case 'mainwp/get-site-v1': result = { id: 7, url, status: 'connected', last_sync: new Date().toISOString() }; break;
        case 'mrn-mainwp/audit-email-address-v1': {
          const done = input.cursor === 'next'; state.page++;
          result = { contract: 'mrn-email-address-audit-v1', site_id: 7, site_url: url, address, scan_complete: done, next_cursor: done ? '' : 'next',
            matches: done ? [] : [{ site_id: 7, site_url: url, address, component: 'wpforms', form_id: 3, form_title: '=UNTRUSTED()',
              setting_path: 'posts.3.notification.email', role: 'recipient', match_type: 'direct', enabled_state: 'current', inspection_timestamp: new Date().toISOString() }],
            coverage: { plugins: { 'wpforms/wpforms.php': { version: '1.0', active: true } } }, limitations: ['runtime_filters_not_inspected'] };
          if (state.expireOnInspect) state.denied = true;
          break;
        }
        default: throw new Error(`Unexpected ability ${args.ability}`);
      }
      state.afterCall?.(args.ability, result);
      return response({ schema: 1, request_id: claims.request_id, ability: args.ability, actor, result, policy_sha256: claims.policy_sha256 });
    },
  };
  const connect = async actor => new DelegatedMainwpClient(raw, actor, signer);
  const workflow = new EmailAuditWorkflow({ registry, store, connect, engine });
  return { workflow, store, file, policy, state, connect, registry };
}
const start = f => f.workflow.run(person(), 'start', { address, scope: 'site', site_urls: [url] });
async function complete(f, id) {
  return f.workflow.run(person(), 'continue', { audit_id: id, max_pages_per_site: 2 });
}

test('real shared engine resumes, attributes every call, retains coverage and provides private CSV plus coverage', async t => {
  const f = fixture(t), job = await start(f);
  assert.equal(job.total, 1);
  const first = await f.workflow.run(person(), 'continue', { audit_id: job.audit_id, max_pages_per_site: 1 });
  assert.equal(first.finished, false);
  const reopened = new Store(f.file); t.after(() => reopened.close());
  const resumed = new EmailAuditWorkflow({ registry: f.registry, store: reopened, connect: f.connect, engine });
  const second = await resumed.run(person(), 'continue', { audit_id: job.audit_id });
  assert.equal(second.finished, true); assert.equal(second.incomplete, 1); assert.equal(second.matched, 1);
  assert.equal(f.state.calls.filter(c => c.ability === 'mainwp/sync-sites-v1').length, 1);
  assert.ok(f.state.calls.every(c => c.actor.google_sub === '123'));
  const exported = await f.workflow.run(person(), 'export', { audit_id: job.audit_id, format: 'csv' });
  assert.equal(exported.reports.length, 2); assert.equal(exported.file, undefined); assert.equal(exported.delivery_verified, false);
  assert.ok(exported.reports.every(r => r.uri.startsWith('mrn-operations://email-audits/')));
  const csv = await f.workflow.readReport(person(), exported.reports[0].uri);
  assert.match(csv.contents[0].text, /'=UNTRUSTED/);
  const coverage = await f.workflow.readReport(person(), exported.reports[1].uri);
  assert.match(coverage.contents[0].text, /runtime_filters_not_inspected/);
  assert.ok(f.store.db.prepare('SELECT identity FROM audit WHERE event LIKE ?').all('email-audit:%').every(r => JSON.parse(r.identity).googleSubject === '123'));
});

test('another active staff member cannot continue, view, export or download an owner job', async t => {
  const f = fixture(t), job = await start(f); await complete(f, job.audit_id);
  const exported = await f.workflow.run(person(), 'export', { audit_id: job.audit_id, format: 'json' });
  const before = f.state.calls.length;
  for (const action of ['continue', 'get', 'export']) await assert.rejects(() => f.workflow.run(person('456'), action,
    { audit_id: job.audit_id, ...(action === 'export' ? { format: 'json' } : {}) }), { code: 'NOT_FOUND' });
  await assert.rejects(() => f.workflow.readReport(person('456'), exported.reports[0].uri), { code: 'NOT_FOUND' });
  assert.equal(f.state.calls.length, before);
});

test('outsiders, non-Google, expired, explicitly revoked and suspended actors are denied', async t => {
  const f = fixture(t);
  for (const actor of [{ ...person(), verifiedEmail: 'staff@outside.test' }, { ...person(), identityProvider: 'auth0' }, { ...person(), expiresAt: 1 }])
    await assert.rejects(() => f.workflow.run(actor, 'start', { address, scope: 'fleet' }), { code: 'AUTH_REQUIRED' });
  f.policy.members.push({ subject: person().subject, enabled: false });
  await assert.rejects(() => start(f), { code: 'FORBIDDEN' });
  f.policy.members = []; f.state.denied = true;
  await assert.rejects(() => start(f), { code: 'AUTH_REQUIRED' });
});

test('revocation during scan prevents evidence release and subsequent calls; downloaded evidence rechecks access', async t => {
  const f = fixture(t), job = await start(f); f.state.expireOnInspect = true;
  await assert.rejects(() => complete(f, job.audit_id), { code: 'AUTH_REQUIRED' });
  assert.equal(f.state.page, 1);
  f.state.expireOnInspect = false; f.state.denied = false;
  await complete(f, job.audit_id);
  const exported = await f.workflow.run(person(), 'export', { audit_id: job.audit_id, format: 'json' });
  f.state.denied = true;
  await assert.rejects(() => f.workflow.readReport(person(), exported.reports[0].uri), { code: 'AUTH_REQUIRED' });
});

test('exact inventory drift denies retained results and timed-out sync is read back without replay', async t => {
  const f = fixture(t), job = await start(f); f.state.failSync = true;
  await complete(f, job.audit_id);
  assert.equal(f.state.calls.filter(c => c.ability === 'mainwp/sync-sites-v1').length, 1);
  f.state.drift = true;
  await assert.rejects(() => f.workflow.run(person(), 'get', { audit_id: job.audit_id }), /EMAIL_AUDIT_SITE_SCOPE_CHANGED/);
});

test('active job locks and stale exports fail closed; generic records contain no audit evidence', async t => {
  const f = fixture(t), job = await start(f), storage = new EmailAuditStorage(f.store, auditOwner(person()));
  const release = storage.lock(job.audit_id);
  await assert.rejects(() => complete(f, job.audit_id), { code: 'EMAIL_AUDIT_BUSY' }); release();
  const exported = await f.workflow.run(person(), 'export', { audit_id: job.audit_id, format: 'json' });
  await complete(f, job.audit_id);
  await assert.rejects(() => f.workflow.readReport(person(), exported.reports[0].uri), { code: 'EMAIL_AUDIT_STALE_EXPORT' });
  assert.throws(() => f.store.get(job.audit_id), { code: 'NOT_FOUND' });
});

test('MCP advertises OAuth read scopes and returns protected resource links; each read authenticates separately', async t => {
  const f = fixture(t); let actor = person();
  const reservation = reservePort();
  await new Promise(resolve => reservation.listen(0, '127.0.0.1', resolve));
  const port = reservation.address().port, endpoint = `http://127.0.0.1:${port}/mcp`;
  await new Promise(resolve => reservation.close(resolve));
  const server = createHttpServer({ service: { emailAudit: f.workflow }, authenticate: async () => actor,
    publicUrl: endpoint, issuer: 'https://identity.example' });
  await new Promise((resolve, reject) => { server.once('error', reject); server.listen(port, '127.0.0.1', resolve); }); t.after(() => new Promise(resolve => server.close(resolve)));
  const call = async (method, params) => {
    const r = await fetch(endpoint, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json, text/event-stream' }, body: JSON.stringify({ jsonrpc: '2.0', id: 1, method, params }) });
    return (await r.json());
  };
  const catalog = await call('tools/list', {});
  for (const tool of catalog.result.tools.filter(v => v.name.endsWith('email_address_audit')))
    assert.deepEqual(tool.securitySchemes, [{ type: 'oauth2', scopes: ['mrn:operations'] }]);
  const started = await call('tools/call', { name: 'start_email_address_audit', arguments: { address, scope: 'site', site_urls: [url] } });
  const job = JSON.parse(started.result.content[0].text);
  const exported = await call('tools/call', { name: 'export_email_address_audit', arguments: { audit_id: job.audit_id, format: 'json' } });
  const link = exported.result.content.find(v => v.type === 'resource_link'); assert.ok(link);
  const read = await call('resources/read', { uri: link.uri }); assert.ok(read.result.contents[0].text);
  actor = person('456'); const denied = await call('resources/read', { uri: link.uri }); assert.ok(denied.error);
});
