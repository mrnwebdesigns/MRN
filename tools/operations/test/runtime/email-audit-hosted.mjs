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
import { McpError, ErrorCode } from '@modelcontextprotocol/sdk/types.js';
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
  const state = { calls: [], denied: false, page: 0, drift: false, failSync: false, expireOnInspect: false, scanFailure: null, failureAbility: 'mrn-mainwp/audit-email-address-v1', afterCall: null };
  const raw = {
    async readResource() { return { contents: [{ text: JSON.stringify({ connected: true, dashboardHost: 'wpcontrol.mrndev.io', abilitiesCount: 121 }) }] }; },
    async listTools() { return { tools: Object.values(delegationTools).map(name => ({ name, annotations: { readOnlyHint: false, destructiveHint: name === delegationTools.run } })) }; },
    async close() {},
    async callTool({ name, arguments: args }, _schema, options) {
      if (name === delegationTools.challenge) { await state.beforeChallenge?.(args.ability, options); return response({ nonce: randomBytes(32).toString('hex'), expires_at: Math.floor(Date.now() / 1000) + 60,
        authenticated_connection_sha256: 'a'.repeat(64), policy_sha256: 'b'.repeat(64) }); }
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
      await state.beforeCall?.(args.ability, input, options);
      if (state.scanFailure && args.ability === state.failureAbility) {
        if (state.expireOnInspect) state.denied = true;
        return { isError: true, ...response({ error: { code: -32603,
          message: `Ability execution failed: ${state.scanFailure} (HTTP 500). The upstream message is withheld because this call carried write-only input.` } }) };
      }
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

test('native reader and inspection failures retain explicit incomplete coverage through the delegated MCP error envelope', async t => {
  for (const code of ['EMAIL_AUDIT_READER_UNAVAILABLE', 'EMAIL_AUDIT_TRANSPORT', 'EMAIL_AUDIT_STALE_INVENTORY', 'EMAIL_AUDIT_INSPECTION_FAILED']) {
    const f = fixture(t), job = await start(f); f.state.scanFailure = code;
    const result = await complete(f, job.audit_id);
    assert.equal(result.failed, 1); assert.equal(result.incomplete, 1); assert.equal(result.matched, 0);
    const retained = await f.workflow.run(person(), 'get', { audit_id: job.audit_id });
    assert.equal(retained.sites[0].error_code, code);
    assert.equal(retained.sites[0].status, 'failed');
    assert.ok(retained.sites[0].limitations.includes('settings_inspection_incomplete'));
    assert.equal(JSON.stringify(retained).includes('PRIVATE_PROVIDER_MESSAGE'), false);
    const reads = f.state.calls.filter(row => row.ability === 'mrn-mainwp/audit-email-address-v1');
    assert.equal(reads.length, 1, 'A refused inspection is not silently retried');
    assert.ok(f.state.calls.slice(f.state.calls.indexOf(reads[0]) + 1).some(row => row.ability === 'mrn-mainwp/authorize-email-address-audit-v1'));
    f.state.scanFailure = null;
    const resumed = await f.workflow.run(person(), 'continue', { audit_id: job.audit_id, retry_site_ids: [7], max_pages_per_site: 2 });
    assert.equal(resumed.failed, 0); assert.equal(resumed.matched, 1);
    assert.equal(f.state.calls.filter(row => row.ability === 'mainwp/sync-sites-v1').length, 1);
  }
});

test('audit access, identity, malformed-contract and unknown refusals remain fatal; revocation hides coverage', async t => {
  for (const code of ['EMAIL_AUDIT_PERMISSION', 'EMAIL_AUDIT_SITE_IDENTITY', 'EMAIL_AUDIT_CONTRACT',
    'mrn_delegation_revoked', 'mrn_code_directory_inactive', 'UNRECOGNIZED']) {
    const f = fixture(t), job = await start(f); f.state.scanFailure = code;
    await assert.rejects(() => complete(f, job.audit_id), error => error instanceof OpsError);
    assert.equal(f.state.calls.at(-1).ability, 'mrn-mainwp/audit-email-address-v1');
  }
  const f = fixture(t), job = await start(f);
  f.state.scanFailure = 'EMAIL_AUDIT_READER_UNAVAILABLE'; f.state.expireOnInspect = true;
  await assert.rejects(() => complete(f, job.audit_id), { code: 'AUTH_REQUIRED' });
});

test('coverage errors on authorization or inventory never become partial scan results', async t => {
  for (const ability of ['mainwp/get-site-knowledge-v1', 'mainwp/sync-sites-v1', 'mainwp/get-site-v1',
    'mrn-mainwp/authorize-email-address-audit-v1']) {
    const f = fixture(t), job = await start(f);
    f.state.scanFailure = 'EMAIL_AUDIT_READER_UNAVAILABLE'; f.state.failureAbility = ability;
    await assert.rejects(() => complete(f, job.audit_id), { code: 'EMAIL_AUDIT_READER_UNAVAILABLE' });
    assert.equal(f.state.calls.at(-1).ability, ability);
    assert.equal(f.state.page, 0);
  }
});


test('hosted SDK and injected transport timeout codes cannot impersonate a native budget deferral', async t => {
  for (const kind of ['sdk-timeout', 'injected-ETIMEDOUT']) {
    const f = fixture(t); let now = Date.now();
    const clock = t.mock.method(Date, 'now', () => now);
    try {
      f.state.beforeCall = (ability, input, options) => {
        if (ability === 'mainwp/get-site-knowledge-v1') now += 3000;
        if (ability === 'mainwp/sync-sites-v1') now += 4000;
        if (ability === 'mainwp/get-site-v1') now += 3000;
        if (ability === 'mrn-mainwp/audit-email-address-v1') {
          if (!input.cursor) now += 3000;
          else {
            assert.equal(options.timeout, 2000); now += options.timeout;
            if (kind === 'sdk-timeout') throw new McpError(ErrorCode.RequestTimeout, 'Request timed out', { timeout: options.timeout });
            throw Object.assign(new Error('Request timeout'), { code: 'ETIMEDOUT' });
          }
        }
      };
      f.state.afterCall = (ability, result) => { if (ability === 'mainwp/get-site-v1') result.last_sync = new Date(now).toISOString(); };
      const job = await start(f);
      const result = await f.workflow.run(person(), 'continue', { audit_id: job.audit_id, max_duration_ms: 15000 });
      assert.equal(result.failed, 1); assert.equal(result.pending, 0);
      const storage = new EmailAuditStorage(f.store, auditOwner(person()));
      const row = storage.load(job.audit_id).rows[0];
      assert.equal(row.pages, 1); assert.equal(row.cursor, 'next'); assert.equal(row.matches.length, 1);
      assert.equal(row.error_code, 'EMAIL_AUDIT_TIMEOUT');
      const scans = () => f.state.calls.filter(c => c.ability === 'mrn-mainwp/audit-email-address-v1').length;
      assert.equal(scans(), 2);
      await f.workflow.run(person(), 'continue', { audit_id: job.audit_id, max_duration_ms: 15000 });
      assert.equal(scans(), 2); // A failed row never silently retries.
    } finally { clock.mock.restore(); }
  }
});

test('hosted budget exhaustion cannot soften access or unknown OpsErrors and never releases evidence after revocation', async t => {
  for (const code of ['AUTH_REQUIRED', 'MAINWP_ACCESS', 'DOWNSTREAM_TIMEOUT', 'MAINWP_FAILED']) {
    const f = fixture(t); let now = Date.now(); const clock = t.mock.method(Date, 'now', () => now);
    try {
      const job = await start(f);
      f.state.beforeCall = (ability, _input, options) => {
        if (ability === 'mrn-mainwp/audit-email-address-v1') {
          now += options.timeout;
          throw new OpsError(code, 'Fixture refusal');
        }
      };
      await assert.rejects(() => f.workflow.run(person(), 'continue', { audit_id: job.audit_id, max_duration_ms: 2000 }), { code });
      assert.equal(f.store.db.prepare("SELECT COUNT(*) n FROM audit WHERE event='email-audit:continue' AND outcome='returned'").get().n, 0);
    } finally { clock.mock.restore(); }
  }
  const f = fixture(t), job = await start(f);
  f.state.beforeCall = ability => {
    if (ability === 'mrn-mainwp/audit-email-address-v1') {
      f.state.denied = true; throw new McpError(ErrorCode.RequestTimeout, 'Request timed out');
    }
  };
  await assert.rejects(() => complete(f, job.audit_id), { code: 'AUTH_REQUIRED' });
});

test('owned hosted deadline retains the cursor and resumes without repeating completed preparation', async t => {
  const f = fixture(t), realNow = Date.now.bind(Date); let offset = 0;
  const clock = t.mock.method(Date, 'now', () => realNow() + offset);
  try {
    f.state.beforeCall = async (ability, input, options) => {
      if (ability === 'mainwp/get-site-knowledge-v1') offset += 300;
      if (ability === 'mainwp/sync-sites-v1') offset += 400;
      if (ability === 'mainwp/get-site-v1') offset += 300;
      if (ability === 'mrn-mainwp/audit-email-address-v1') {
        if (!input.cursor) offset += 300;
        else { await new Promise(resolve => options.signal.addEventListener('abort', resolve, { once: true })); throw options.signal.reason; }
      }
    };
    f.state.afterCall = (ability, result) => { if (ability === 'mainwp/get-site-v1') result.last_sync = new Date(Date.now()).toISOString(); };
    const job = await start(f);
    const result = await f.workflow.run(person(), 'continue', { audit_id: job.audit_id, max_duration_ms: 2500 });
    assert.equal(result.failed, 0); assert.equal(result.pending, 1); assert.equal(result.finished, false);
    const row = new EmailAuditStorage(f.store, auditOwner(person())).load(job.audit_id).rows[0];
    assert.equal(row.pages, 1); assert.equal(row.cursor, 'next'); assert.equal(row.matches.length, 1);
    assert.equal(row.status, 'pending'); assert.ok(!row.error_code);
    assert.equal(f.state.calls.filter(c => c.ability === 'mrn-mainwp/audit-email-address-v1').length, 2);
    f.state.beforeCall = null;
    const resumed = await complete(f, job.audit_id);
    assert.equal(resumed.finished, true); assert.equal(resumed.failed, 0); assert.equal(resumed.matched, 1);
    assert.equal(f.state.calls.filter(c => c.ability === 'mainwp/sync-sites-v1').length, 1);
    assert.equal(f.state.calls.filter(c => c.ability === 'mainwp/get-site-knowledge-v1').length, 1);
  } finally { clock.mock.restore(); }
});

test('owned cancellation covers delegation challenges and revoked membership still hides pending evidence', async t => {
  for (const revoked of [false, true]) {
    const f = fixture(t), job = await start(f); let aborts = 0;
    f.state.beforeChallenge = async (ability, options) => {
      if (ability !== 'mainwp/get-site-knowledge-v1') return;
      assert.ok(options.signal); assert.ok(options.timeout <= 1500);
      await new Promise(resolve => options.signal.addEventListener('abort', resolve, { once: true }));
      aborts++; if (revoked) f.state.denied = true;
      // A late challenge result must not cause the delegated read to dispatch.
    };
    const run = () => f.workflow.run(person(), 'continue', { audit_id: job.audit_id, max_duration_ms: 1500 });
    if (revoked) await assert.rejects(run, { code: 'AUTH_REQUIRED' });
    else { const result = await run(); assert.equal(result.pending, 1); assert.equal(result.failed, 0); }
    assert.equal(aborts, 1);
    assert.equal(f.state.calls.filter(c => c.ability === 'mainwp/get-site-knowledge-v1').length, 0);
    if (revoked) assert.equal(f.store.db.prepare("SELECT COUNT(*) n FROM audit WHERE event='email-audit:continue' AND outcome='returned'").get().n, 0);
  }
});
