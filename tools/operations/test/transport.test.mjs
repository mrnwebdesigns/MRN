import test from 'node:test';
import assert from 'node:assert/strict';
import { createLocalJWKSet, exportJWK, generateKeyPair, SignJWT } from 'jose';
import { Client } from '@modelcontextprotocol/sdk/client/index.js';
import { StreamableHTTPClientTransport } from '@modelcontextprotocol/sdk/client/streamableHttp.js';
import { StdioClientTransport } from '@modelcontextprotocol/sdk/client/stdio.js';
import { writeFileSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import { createServer as reservePort } from 'node:net';
import { setup, actor, prepared, execute, enrollRecovery } from './helpers.mjs';
import { createAuthenticator } from '../src/auth.mjs';
import { createHttpServer } from '../src/http.mjs';
import { connectMainwp } from '../src/mainwp.mjs';
import { configSchema } from '../src/config.mjs';
import { Discovery } from '../src/discovery.mjs';

const issuer = 'https://identity.example.test'; const audience = 'https://ops.example.test/mcp';
async function identity() {
  const keys = await generateKeyPair('RS256'); const jwk = { ...await exportJWK(keys.publicKey), kid: 'test-key', alg: 'RS256', use: 'sig' };
  const sign = (subject = actor.subject, overrides = {}) => new SignJWT({ scope: 'mrn:operations', ...overrides }).setProtectedHeader({ alg: 'RS256', kid: jwk.kid })
    .setIssuer(issuer).setAudience(audience).setSubject(subject).setIssuedAt().setExpirationTime('10m').sign(keys.privateKey);
  const auth = createAuthenticator({ issuer, audience, jwksUrl: `${issuer}/keys` }, createLocalJWKSet({ keys: [jwk] }));
  return { keys, sign, auth };
}
test('JWT identity rejects absent, expired, unsigned, wrong-issuer, wrong-audience and wrong-scope tokens', async () => {
  const { auth, sign, keys } = await identity();
  assert.equal((await auth(`Bearer ${await sign()}`)).subject, actor.subject);
  for (const value of ['', 'Basic abc', 'Bearer abc.def.ghi']) await assert.rejects(() => auth(value), { code: 'AUTH_REQUIRED' });
  for (const options of [{ aud: 'another-service' }, { iss: 'https://evil.test' }, { exp: 1 }, { scope: 'read' }]) {
    const token = await new SignJWT({ sub: actor.subject, iat: Math.floor(Date.now() / 1000), exp: Math.floor(Date.now() / 1000) + 300,
      aud: audience, iss: issuer, scope: 'mrn:operations', ...options }).setProtectedHeader({ alg: 'RS256', kid: 'test-key' }).sign(keys.privateKey);
    await assert.rejects(() => auth(`Bearer ${token}`), { code: 'AUTH_REQUIRED' });
  }
});

test('hosted HTTP MCP authenticates each request and keeps concurrent identities isolated', async t => {
  const f = setup(); t.after(() => f.close()); const { sign, auth } = await identity();
  f.state.directory = [{ id: 1, url: f.state.url, name: 'Example' }];
  f.policy.members[0].portfolio = { sources: ['mainwp'], actions: ['read'] };
  f.service.discovery = new Discovery({ registry: f.registry, store: f.store, connect: async () => f.client });
  const reserved = reservePort(); await new Promise(resolve => reserved.listen(0, '127.0.0.1', resolve));
  const port = reserved.address().port; await new Promise(resolve => reserved.close(resolve));
  const base = `http://127.0.0.1:${port}`;
  const server = createHttpServer({ service: f.service, authenticate: auth, publicUrl: `${base}/mcp`, issuer });
  await new Promise(resolve => server.listen(port, '127.0.0.1', resolve)); t.after(() => new Promise(resolve => server.close(resolve)));
  const headers = {};
  const metadata = await fetch(`${base}/.well-known/oauth-protected-resource/mcp`, { headers }); assert.equal(metadata.status, 200);
  assert.equal((await metadata.json()).resource, `${base}/mcp`);
  const unauthorized = await fetch(`${base}/mcp`, { method: 'POST', headers: { ...headers, 'Content-Type': 'application/json' }, body: '{}' });
  assert.equal(unauthorized.status, 401); assert.match(unauthorized.headers.get('www-authenticate'), /resource_metadata/);
  assert.match(unauthorized.headers.get('www-authenticate'), /error="invalid_token".*error_description=.*scope="mrn:operations"/);
  assert.equal((await fetch(`http://localhost:${port}/healthz`)).status, 403);
  assert.equal((await fetch(`${base}/healthz`, { headers: { ...headers, Origin: 'https://evil.test' } })).status, 403);
  const clients = [];
  const connect = async subject => {
    const client = new Client({ name: 'test', version: '1' });
    await client.connect(new StreamableHTTPClientTransport(new URL(`${base}/mcp`), { requestInit: { headers: { ...headers, Authorization: `Bearer ${await sign(subject)}` } } }));
    clients.push(client); return client;
  };
  t.after(async () => { await Promise.all(clients.map(c => c.close())); });
  const [owner, denied] = await Promise.all([connect(actor.subject), connect('unregistered')]);
  assert.ok((await owner.listTools()).tools.some(t => t.name === 'prepare_repair'));
  const [yes, no] = await Promise.all([owner.callTool({ name: 'list_websites', arguments: {} }), denied.callTool({ name: 'list_websites', arguments: {} })]);
  assert.equal(JSON.parse(yes.content[0].text).websites.length, 1); assert.equal(no.isError, true);
  assert.equal(JSON.parse(no.content[0].text).error.code, 'FORBIDDEN');
  const recoveryTool = (await owner.listTools()).tools.find(t => t.name === 'reconcile_operation');
  assert.equal(recoveryTool.annotations.readOnlyHint, false); // Can release local locks.
  assert.deepEqual(Object.keys(recoveryTool.inputSchema.properties), ['operationId']);
  const { op } = await prepared(f); f.state.loseResponse = true; await execute(f, op); enrollRecovery(f, op.id);
  const reader = await connect('reader'); const calls = f.state.calls.length;
  const forbidden = await reader.callTool({ name: 'reconcile_operation', arguments: { operationId: op.id } });
  assert.equal(forbidden.isError, true); assert.equal(JSON.parse(forbidden.content[0].text).error.code, 'FORBIDDEN');
  assert.equal(f.state.calls.length, calls);
  const reconciled = await owner.callTool({ name: 'reconcile_operation', arguments: { operationId: op.id } });
  assert.equal(JSON.parse(reconciled.content[0].text).reconciliation.outcome, 'intended_code_verified');
  assert.ok(!/fixture-private|secret-sentinel|package_base64/.test(reconciled.content[0].text));
  assert.equal(f.state.mutations, 1); assert.equal(f.state.backups, 1);
  f.policy.members[0].enabled = false;
  const afterRecovery = f.state.calls.length;
  const revoked = await owner.callTool({ name: 'inspect_website', arguments: { website: 'example' } });
  assert.equal(revoked.isError, true); assert.equal(f.state.calls.length, afterRecovery);
});

test('actual stdio downstream MCP supports Fleet execution and recovery after a lost rollback response', async t => {
  const f = setup(); t.after(() => f.close()); const stateFile = join(f.root, 'mainwp-state.json'); writeFileSync(stateFile, JSON.stringify(f.state));
  f.service.connect = async () => {
    const client = new Client({ name: 'mrn-ops-integration', version: '1' });
    await client.connect(new StdioClientTransport({ command: process.execPath,
      args: [new URL('./fixtures/mainwp.mjs', import.meta.url).pathname, stateFile], stderr: 'pipe' }));
    return client;
  };
  const inspection = await f.service.inspect(actor, { website: 'example' });
  const plan = await f.service.prepare(actor, { inspectionId: inspection.id, findingId: inspection.findings[0].id, requestKey: 'protocol-repair' });
  assert.equal(plan.status, 'prepared', JSON.stringify(plan.error));
  await f.service.approve(actor, { operationId: plan.id, planDigest: plan.planDigest });
  await f.service.execute(actor, { operationId: plan.id }); await f.service.jobs.get(plan.id);
  assert.equal(f.service.get(actor, plan.id).status, 'verified');
  assert.equal(JSON.parse(readFileSync(stateFile)).mutations, 1);
  const rollback = await f.service.prepareRollback(actor, { operationId: plan.id, requestKey: 'protocol-rollback' });
  const state = JSON.parse(readFileSync(stateFile)); state.loseResponse = true; writeFileSync(stateFile, JSON.stringify(state));
  assert.equal((await execute(f, rollback)).status, 'uncertain'); enrollRecovery(f, rollback.id);
  const result = await f.service.reconcile(actor, { operationId: rollback.id });
  assert.equal(result.status, 'reconciled'); assert.equal(result.reconciliation.outcome, 'intended_code_verified');
  assert.equal(result.reconciliation.version, '1.0.0');
  const recovered = JSON.parse(readFileSync(stateFile)); assert.equal(recovered.mutations, 2); assert.equal(recovered.backups, 2);
});

test('hosted configuration cannot silently inherit an interactive downstream credential', async () => {
  await assert.rejects(() => connectMainwp({ command: process.execPath, args: ['unused'], cwd: '/tmp', envRefs: {} }, {}), { code: 'MAINWP_CONFIG' });
  await assert.rejects(() => connectMainwp({ command: process.execPath, args: ['unused'], cwd: '/tmp', envRefs: { MAINWP_APP_PASSWORD: { env: 'MISSING' } } }, {}), { code: 'MAINWP_CREDENTIAL_MISSING' });
  assert.equal(configSchema.safeParse({ version: 1, listen: { host: '0.0.0.0', port: 8800 } }).success, false);
});

test('automatic MainWP discovery and inspection work over real stdio without an Operations website record', async t => {
  const f = setup(); t.after(() => f.close());
  f.registryData.websites = [];
  f.policy.members[0] = { subject: actor.subject, enabled: true, portfolio: { sources: ['mainwp'], actions: ['read'] }, grants: [] };
  f.state.directory = [{ id: 1, url: f.state.url, name: 'Discovered Client' }];
  const stateFile = join(f.root, 'discovery-state.json'); writeFileSync(stateFile, JSON.stringify(f.state));
  f.service.connect = async () => {
    const client = new Client({ name: 'mrn-discovery-integration', version: '1' });
    await client.connect(new StdioClientTransport({ command: process.execPath,
      args: [new URL('./fixtures/mainwp.mjs', import.meta.url).pathname, stateFile], stderr: 'pipe' }));
    return client;
  };
  f.service.discovery = new Discovery({ registry: f.registry, store: f.store, connect: f.service.connect });
  const list = await f.service.list(actor); assert.equal(list.websites[0].name, 'Discovered Client');
  const report = await f.service.inspect(actor, { website: 'Discovered Client' });
  assert.equal(report.target.url, f.state.url); assert.ok(report.findings.some(f => f.code === 'STACK_PLUGIN_BEHIND'));
  const state = JSON.parse(readFileSync(stateFile));
  assert.equal(state.calls.filter(n => n === 'sync_sites_v1').length, 1); assert.equal(state.mutations, 0); assert.equal(state.backups, 0);
  assert.equal(f.registryData.websites.length, 0);
});
