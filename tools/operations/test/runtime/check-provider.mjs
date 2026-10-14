// Invoked ONLY by the provider's disposable runtime hook, before its cleanup.
// Real gateway/MCP/WordPress transport; ephemeral OAuth and Google Directory.
import assert from 'node:assert/strict';
import { createPrivateKey } from 'node:crypto';
import { copyFileSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { execFileSync } from 'node:child_process';
import { createServer } from 'node:net';
import { createLocalJWKSet, exportJWK, generateKeyPair, SignJWT } from 'jose';
import { createAuthenticator } from '../../src/auth.mjs';
import { Registry } from '../../src/contracts.mjs';
import { IdentitySigner } from '../../src/delegation.mjs';
import { DelegatedMainwpClient, delegationTools } from '../../src/delegated-mainwp.mjs';
import { Discovery } from '../../src/discovery.mjs';
import { createHttpServer } from '../../src/http.mjs';
import { connectMainwp, payload } from '../../src/mainwp.mjs';
import { Operations } from '../../src/service.mjs';
import { Store } from '../../src/store.mjs';

process.umask(0o077);
assert.equal(process.env.GITHUB_ACTIONS, 'true', 'This hook requires the disposable CI runtime');
const [privateDirectory, certificate, runtimeCli] = process.argv.slice(2);
assert.ok(privateDirectory && certificate && runtimeCli && process.argv.length === 5);
assert.equal(certificate, join(privateDirectory, 'tls.crt'));
assert.equal(runtimeCli, join(privateDirectory, 'wp-fixture-cli'));
const transportRoot = resolve(process.env.MRN_GATEWAY_MCP_SOURCE);
const lock = JSON.parse(readFileSync(new URL('./sources.json', import.meta.url), 'utf8'));
assert.equal(execFileSync('git', ['-C', transportRoot, 'rev-parse', 'HEAD'], { encoding: 'utf8' }).trim(), lock.transport.commit);
const report = { schema: 1, passed: [], source: lock, productionCredentials: false, liveGoogleVerified: false,
  chatgptAcceptanceVerified: false, managedSites: 0, contentWriteVerified: false, codeWriteVerified: false };
const pass = label => { report.passed.push(label); process.stdout.write(`PASS ${label}\n`); };
const fixtureOption = value => execFileSync(runtimeCli, ['option', 'update', 'mrn_fixture_suspended', value], { stdio: 'ignore', timeout: 30000 });
const credentials = JSON.parse(readFileSync(join(privateDirectory, 'credentials.json'), 'utf8'));
const homeDirectory = join(privateDirectory, 'gateway-home'); mkdirSync(homeDirectory, { mode: 0o700 });
const preload = join(privateDirectory, 'fixture-network.mjs');
copyFileSync(new URL('./fixture-network.mjs', import.meta.url), preload);
writeFileSync(join(privateDirectory, 'gateway-network.json'), JSON.stringify({ address: readFileSync(join(privateDirectory, 'tls-ip'), 'utf8'), certificate }));
const network = () => { try { return readFileSync(join(privateDirectory, 'gateway-network.jsonl'), 'utf8').trim().split('\n').filter(Boolean).map(line => JSON.parse(line)); } catch (error) { if (error.code === 'ENOENT') return []; throw error; } };
const transportConfig = { command: process.execPath, args: ['--import', preload, join(transportRoot, 'dist/index.js')], cwd: homeDirectory,
  envRefs: Object.fromEntries(['MAINWP_URL', 'MAINWP_USER', 'MAINWP_APP_PASSWORD', 'MAINWP_SAFE_MODE', 'HOME'].map(key => [key, { env: `FIXTURE_${key}` }])) };
const transportEnvironment = { PATH: process.env.PATH, FIXTURE_MAINWP_URL: 'https://wpcontrol.mrndev.io', FIXTURE_MAINWP_USER: credentials.username,
  FIXTURE_MAINWP_APP_PASSWORD: credentials.password, FIXTURE_MAINWP_SAFE_MODE: 'true', FIXTURE_HOME: homeDirectory };
const signer = new IdentitySigner({ keyId: 'disposable-identity-only', connectionId: 'con_RuntimeFixture',
  privateKey: createPrivateKey({ key: readFileSync(join(privateDirectory, 'identity.der')), type: 'pkcs8', format: 'der' }) });
const clients = new Set();
const connect = async actor => {
  const raw = await connectMainwp(transportConfig, transportEnvironment); clients.add(raw);
  return new DelegatedMainwpClient(raw, actor, signer);
};
const policy = { version: 1, members: [], emailDomains: [{ domain: 'mrnwebdesigns.com', enabled: true,
  portfolio: { sources: ['mainwp'], actions: ['read', 'content', 'code'] } }] };
const registry = new Registry(() => ({ version: 1, websites: [] }), () => policy);
const store = new Store(join(privateDirectory, 'gateway.sqlite'));
const verifyStaff = async actor => {
  registry.member(actor); const client = await connect(actor);
  try { return await client.verifyStaff(); } finally { await client.close(); }
};
const service = new Operations({ registry, store, connect, verifyStaff, writesEnabled: false,
  discovery: new Discovery({ registry, store, connect }) });
const issuer = 'https://oauth.example.invalid/'; const audience = 'https://operations.mrnwebdesigns.com/mcp';
const keys = await generateKeyPair('RS256');
const keySet = createLocalJWKSet({ keys: [{ ...await exportJWK(keys.publicKey), alg: 'RS256', kid: 'disposable-gateway' }] });
const authConfig = { issuer, audience, verifiedEmailClaims: true, googleWorkspace: { connectionId: 'con_RuntimeFixture' } };
const authenticate = createAuthenticator(authConfig, keySet, verifyStaff);
const emails = { '111111': 'alice@mrnwebdesigns.com', '222222': 'bob@mrnwebdesigns.com', '999999': 'missing@mrnwebdesigns.com' };
const token = (sub, overrides = {}) => new SignJWT({ scope: 'mrn:operations', [`${audience}/email`]: emails[sub],
  [`${audience}/email_verified`]: true, [`${audience}/identity_provider`]: 'google-oauth2', [`${audience}/google_sub`]: sub,
  [`${audience}/google_connection`]: 'con_RuntimeFixture', ...overrides }).setProtectedHeader({ alg: 'RS256', kid: 'disposable-gateway' })
  .setIssuer(issuer).setAudience(audience).setSubject(`google-oauth2|${sub}`).setIssuedAt().setExpirationTime('10m').sign(keys.privateKey);
const reserved = createServer(); await new Promise(resolve => reserved.listen(0, '127.0.0.1', resolve));
const port = reserved.address().port; await new Promise(resolve => reserved.close(resolve));
const base = `http://127.0.0.1:${port}`;
const server = createHttpServer({ service, authenticate, publicUrl: `${base}/mcp`, issuer });
await new Promise(resolve => server.listen(port, '127.0.0.1', resolve));
const call = async (bearer, method = 'tools/list', params = {}) => {
  const result = await fetch(`${base}/mcp`, { method: 'POST', headers: { Authorization: `Bearer ${bearer}`, 'Content-Type': 'application/json', Accept: 'application/json, text/event-stream' },
    body: JSON.stringify({ jsonrpc: '2.0', id: 1, method, params }), signal: AbortSignal.timeout(90000) });
  return { status: result.status, challenge: result.headers.get('www-authenticate'), body: await result.json() };
};
const toolResult = result => { assert.equal(result.status, 200); assert.equal(result.body.result?.isError, undefined, 'Gateway tool returned an error'); return JSON.parse(result.body.result.content[0].text); };
try {
  // The provider's preceding HTTP suite deliberately leaves Alice suspended.
  fixtureOption('');
  const alice = await token('111111'); const bob = await token('222222');
  for (const bearer of [alice, bob, alice]) {
    const result = await call(bearer); assert.equal(result.status, 200); assert.equal(result.body.result.tools.length, 21);
  }
  pass('HTTP gateway validates signed individual identities through real MCP and WordPress');
  const lists = await Promise.all([alice, bob].map(bearer => call(bearer, 'tools/call', { name: 'list_websites', arguments: {},
    _meta: { email: 'outsider@example.invalid', subject: 'auth0|forged' } })));
  for (const result of lists) {
    const data = toolResult(result); assert.deepEqual(data.websites, []);
    assert.ok(data.sources.some(source => source.source === 'mainwp' && source.status === 'discovered' && source.sites === 0));
  }
  const reads = network().filter(row => row.path.endsWith('/read-delegated-operation-v1/run'));
  assert.deepEqual([...new Set(reads.map(row => row.subject))].sort(), ['google-oauth2|111111', 'google-oauth2|222222']);
  assert.ok(reads.every(row => row.ability === 'mainwp/get-sites-basic-v1' && row.status === 200));
  pass('Concurrent portfolio discovery preserves each person and ignores caller identity hints');
  toolResult(await call(alice, 'tools/call', { name: 'read_portfolio_details', arguments: { section: 'updates' } }));
  pass('Cached portfolio update read crosses the actual provider adapter');
  for (const overrides of [{ [`${audience}/email`]: 'outside@example.invalid' }, { [`${audience}/identity_provider`]: 'auth0' }, { [`${audience}/google_connection`]: 'con_other' }]) {
    const before = network().length; assert.equal((await call(await token('111111', overrides))).status, 401); assert.equal(network().length, before);
  }
  pass('Outsider, non-Google and wrong-connection tokens stop before MainWP');
  assert.equal((await call(await token('999999'))).status, 401);
  pass('Directory-missing identity is denied despite a signed MRN-domain claim');
  const scope = await call(alice, 'tools/call', { name: 'execute_operation', arguments: { operationId: '00000000-0000-4000-8000-000000000001' }, _meta: { scopes: ['mrn:operations:write'] } });
  assert.equal(JSON.parse(scope.body.result.content[0].text).error.code, 'INSUFFICIENT_SCOPE');
  assert.ok(scope.body.result._meta['mcp/www_authenticate'][0].includes('insufficient_scope'));
  assert.ok(!network().some(row => row.path.endsWith('/run-delegated-operation-v1/run')));
  pass('Read-only OAuth consent cannot start a write through caller metadata');
  fixtureOption('111111');
  for (const [method, params] of [['tools/list', {}], ['tools/call', { name: 'list_websites', arguments: {} }]]) {
    const result = await call(alice, method, params); assert.equal(result.status, 401); assert.ok(result.challenge.includes('invalid_token'));
  }
  assert.equal((await call(bob)).status, 200);
  pass('Existing suspended bearer loses tools and reads while another staff identity remains active');
  fixtureOption('');
  policy.members.push({ subject: 'google-oauth2|111111', enabled: false, grants: [] });
  const beforeRevoke = network().length; assert.equal((await call(alice)).status, 401); assert.equal(network().length, beforeRevoke);
  assert.equal((await call(bob)).status, 200); policy.members.length = 0;
  pass('Explicit access revocation overrides automatic domain access on an existing bearer');
  const actor = await authenticate(`Bearer ${bob}`); const delegated = await connect(actor);
  try {
    const { envelope } = await delegated.envelope('mrn-mainwp/get-team-access-v1', {});
    assert.equal(payload(await delegated.client.callTool({ name: delegationTools.access, arguments: { delegation: envelope } })).google_sub, '222222');
    assert.equal((await delegated.client.callTool({ name: delegationTools.access, arguments: { delegation: envelope } })).isError, true);
    assert.equal((await delegated.client.callTool({ name: 'get_sites_basic_v1', arguments: { per_page: 2 } })).isError, true);
  } finally { await delegated.close(); }
  pass('Persistent provider nonce rejects replay and raw service identity cannot bypass delegation');
  const audit = store.db.prepare('SELECT subject,identity FROM audit WHERE event=? AND outcome=?').all('mainwp:directory', 'returned');
  assert.deepEqual([...new Set(audit.map(row => row.subject))].sort(), ['google-oauth2|111111', 'google-oauth2|222222']);
  for (const row of audit) { const identity = JSON.parse(row.identity); assert.equal(row.subject, `google-oauth2|${identity.googleSubject}`); assert.equal(identity.email, emails[identity.googleSubject]); }
  assert.ok(!JSON.stringify(audit).includes(credentials.password));
  pass('Private gateway audit attributes completed reads to the verified Google person');
  const output = process.env.MRN_GATEWAY_REPORT;
  assert.ok(output); writeFileSync(output, `${JSON.stringify({ ...report, result: 'PASS' }, null, 2)}\n`);
} finally {
  await new Promise(resolve => server.close(resolve));
  await Promise.allSettled([...clients].map(client => client.close()));
  store.close(); fixtureOption('');
}
