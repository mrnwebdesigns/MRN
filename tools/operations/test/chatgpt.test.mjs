import test from 'node:test';
import assert from 'node:assert/strict';
import { createServer as reservePort } from 'node:net';
import { execFileSync, spawnSync } from 'node:child_process';
import { readFileSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';
import { createHttpServer } from '../src/http.mjs';
import { oauthChallenge } from '../src/auth.mjs';
import { configSchema } from '../src/config.mjs';
import { actor, setup } from './helpers.mjs';

async function hosted(t, authenticate) {
  const f = setup(); t.after(() => f.close());
  const reserved = reservePort(); await new Promise(resolve => reserved.listen(0, '127.0.0.1', resolve));
  const port = reserved.address().port; await new Promise(resolve => reserved.close(resolve));
  const base = `http://127.0.0.1:${port}`;
  const server = createHttpServer({ service: f.service, authenticate, publicUrl: `${base}/mcp`, issuer: 'https://identity.example.test' });
  await new Promise(resolve => server.listen(port, '127.0.0.1', resolve));
  t.after(() => new Promise(resolve => server.close(resolve)));
  const call = async (method, params = {}) => {
    const response = await fetch(`${base}/mcp`, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json, text/event-stream' },
      body: JSON.stringify({ jsonrpc: '2.0', id: 1, method, params }) });
    return { status: response.status, body: await response.json(), challenge: response.headers.get('www-authenticate') };
  };
  return { ...f, base, call };
}

test('ChatGPT receives OAuth declarations on the wire and accurate operation effects', async t => {
  const f = await hosted(t, async () => actor);
  const { status, body } = await f.call('tools/list'); assert.equal(status, 200);
  assert.equal(body.result.tools.length, 11);
  for (const tool of body.result.tools) {
    assert.deepEqual(tool.securitySchemes, [{ type: 'oauth2', scopes: ['mrn:operations'] }]);
    assert.deepEqual(tool._meta.securitySchemes, tool.securitySchemes);
    assert.equal(tool.inputSchema.type, 'object'); assert.ok(tool.title);
  }
  const tools = Object.fromEntries(body.result.tools.map(tool => [tool.name, tool]));
  for (const name of ['inspect_website', 'test_website', 'prepare_repair', 'prepare_rollback', 'assess_fleet']) {
    assert.equal(tools[name].annotations.readOnlyHint, false); assert.equal(tools[name].annotations.destructiveHint, false);
  }
  assert.equal(tools.execute_operation.annotations.destructiveHint, true);
  assert.equal(tools.prepare_repair.annotations.idempotentHint, true);
  assert.equal(tools.inspect_website.annotations.idempotentHint, false);
  assert.equal(tools.list_websites.annotations.openWorldHint, false);
  assert.equal(tools.inspect_website.annotations.openWorldHint, true);
  const inspection = await f.call('tools/call', { name: 'inspect_website', arguments: { website: 'example', environment: 'development' } });
  assert.equal(JSON.parse(inspection.body.result.content[0].text).target.url, f.state.url);
});

test('an expired tool identity returns the ChatGPT relinking challenge without accessing MainWP', async t => {
  const f = await hosted(t, async () => ({ ...actor, expiresAt: 1 }));
  const response = await f.call('tools/call', { name: 'inspect_website', arguments: { website: 'example' } });
  assert.equal(response.body.result.isError, true);
  assert.deepEqual(response.body.result._meta['mcp/www_authenticate'], [oauthChallenge(`${f.base}/mcp`)]);
  assert.match(response.body.result._meta['mcp/www_authenticate'][0], /error="invalid_token".*error_description=/);
  assert.equal(f.state.calls.length, 0);
});

test('role denial never triggers a misleading sign-in loop or trusts ChatGPT identity hints', async t => {
  const f = await hosted(t, async () => ({ ...actor, subject: 'not-authorized' }));
  const response = await f.call('tools/call', { name: 'inspect_website', arguments: { website: 'example' }, _meta: { 'openai/subject': actor.subject, 'openai/organization': 'mrn' } });
  assert.equal(JSON.parse(response.body.result.content[0].text).error.code, 'TARGET_UNAVAILABLE');
  assert.equal(response.body.result._meta, undefined); assert.equal(f.state.calls.length, 0);
});

const builder = new URL('../chatgpt/package.py', import.meta.url).pathname;
test('ChatGPT resource discovery and token validation cannot be configured for different audiences', () => {
  const config = JSON.parse(readFileSync(new URL('../config/service.example.json', import.meta.url), 'utf8'));
  assert.equal(configSchema.safeParse(config).success, true);
  config.auth.audience = 'another-resource';
  assert.equal(configSchema.safeParse(config).success, false);
});

test('the portable ChatGPT package is deterministic, remote-only and excludes service secrets', t => {
  const f = setup(); t.after(() => f.close()); const output = join(f.root, 'mrn.zip');
  const args = [builder, '--server-url', 'https://operations.mrnwebdesigns.com/mcp', '--output', output];
  writeFileSync(join(f.root, 'service-secret.json'), 'never-package-this');
  const first = JSON.parse(execFileSync('python3', args, { encoding: 'utf8' }));
  const second = JSON.parse(execFileSync('python3', [...args.slice(0, -1), join(f.root, 'second.zip')], { encoding: 'utf8' }));
  assert.equal(first.sha256, second.sha256); assert.equal(first.hostingVerified, false); assert.equal(first.chatgptAcceptanceVerified, false);
  const files = JSON.parse(execFileSync('python3', ['-c', 'import json,sys,zipfile; z=zipfile.ZipFile(sys.argv[1]); print(json.dumps({n:json.loads(z.read(n)) for n in z.namelist()}))', output], { encoding: 'utf8' }));
  assert.deepEqual(Object.keys(files), ['plugin.json', 'mcp.json']);
  assert.equal(files['plugin.json'].extensions['com.openai'].interface.displayName, 'MRN Website Operations');
  assert.deepEqual(files['mcp.json'].mcpServers, { 'mrn-operations': { type: 'streamable-http', url: 'https://operations.mrnwebdesigns.com/mcp' } });
  assert.ok(!/command|envRefs|never-package|skills|hooks|\.app\.json/.test(JSON.stringify(files)));
  const before = readFileSync(output); const overwrite = spawnSync('python3', args);
  assert.equal(overwrite.status, 1); assert.deepEqual(readFileSync(output), before);
});

test('packaging rejects credentials, local endpoints and ambiguous URL components without reflecting secrets', t => {
  const f = setup(); t.after(() => f.close());
  for (const url of ['http://ops.example.com/mcp', 'https://ops.invalid/mcp', 'https://127.0.0.1/mcp', 'https://ops.local/mcp',
    'https://secret-sentinel:password@ops.example.com/mcp', 'https://ops.example.com/mcp?key=secret-sentinel', 'https://ops.example.com/mcp#fragment', 'https://ops.example.com/other']) {
    const result = spawnSync('python3', [builder, '--server-url', url, '--output', join(f.root, 'bad.zip')], { encoding: 'utf8' });
    assert.equal(result.status, 1, url); assert.ok(!/secret-sentinel|password/.test(result.stdout + result.stderr));
  }
});
