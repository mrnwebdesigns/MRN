import test from 'node:test';
import assert from 'node:assert/strict';
import { request as httpRequest } from 'node:http';
import { createHttpServer } from '../src/http.mjs';
import { accessVetoPath, managementAccessVetoPath } from '../src/content-access.mjs';
import { delegationCanonical } from '../src/delegation.mjs';
import { OpsError } from '../src/contracts.mjs';

const publicUrl = 'https://operations.mrnwebdesigns.com/mcp';
const pointer = { query_id: '11111111-1111-4111-8111-111111111111', provider_query_nonce: 'a'.repeat(64) };
async function server(t, handlers) {
  const value = createHttpServer({ publicUrl, ...handlers });
  await new Promise(resolve => value.listen(0, '127.0.0.1', resolve));
  t.after(() => new Promise(resolve => value.close(resolve)));
  return value;
}
function call(target, path, { method = 'POST', headers = {}, body = delegationCanonical(pointer) } = {}) {
  return new Promise((resolve, reject) => {
    const req = httpRequest({ host: '127.0.0.1', port: target.address().port, path, method, agent: false,
      headers: { host: 'operations.mrnwebdesigns.com', 'content-type': 'application/json', ...headers } }, res => {
      const chunks = []; res.on('data', chunk => chunks.push(chunk));
      res.on('end', () => resolve({ status: res.statusCode, body: JSON.parse(Buffer.concat(chunks).toString()) }));
    });
    req.on('error', reject); req.end(body);
  });
}

test('each fixed callback path invokes only its explicitly installed domain handler', async t => {
  const seen = []; let authCalls = 0;
  const target = await server(t, { authenticate() { authCalls++; },
    accessVeto: { respond(value) { seen.push(['old', value]); return { fixture: 'old' }; } },
    managementAccessVeto: { respond(value) { seen.push(['management', value]); return { fixture: 'management' }; } } });
  assert.deepEqual(await call(target, accessVetoPath), { status: 200, body: { fixture: 'old' } });
  assert.deepEqual(await call(target, managementAccessVetoPath), { status: 200, body: { fixture: 'management' } });
  assert.deepEqual(seen, [['old', pointer], ['management', pointer]]); assert.equal(authCalls, 0);
  for (const path of [managementAccessVetoPath + '/', managementAccessVetoPath + '?domain=old', accessVetoPath + '?domain=management'])
    assert.equal((await call(target, path)).status, 404);
  assert.equal(seen.length, 2);
});

test('a denied management readback never retries the old domain; both retain private ingress restrictions', async t => {
  let oldCalls = 0, managementCalls = 0;
  const target = await server(t, {
    accessVeto: { respond() { oldCalls++; throw new Error('Old domain must not be used'); } },
    managementAccessVeto: { respond() { managementCalls++; throw new OpsError('ACCESS_DENIED', 'Fixture management denial'); } } });
  assert.equal((await call(target, managementAccessVetoPath)).status, 400);
  assert.equal(managementCalls, 1); assert.equal(oldCalls, 0);
  for (const path of [managementAccessVetoPath, accessVetoPath]) {
    for (const input of [{ method: 'GET' }, { headers: { authorization: 'Bearer ignored' } },
      { headers: { origin: 'https://operations.mrnwebdesigns.com' } }, { headers: { host: 'other.example' } },
      { headers: { 'content-type': 'text/plain' } }, { body: delegationCanonical(pointer) + ' ' }, { body: 'x'.repeat(513) }])
      assert.ok([400, 403].includes((await call(target, path, input)).status));
  }
  assert.equal(managementCalls, 1); assert.equal(oldCalls, 0);
});

test('one domain cannot enable the other callback and management is absent by default', async t => {
  const oldOnly = await server(t, { accessVeto: { respond() { throw new Error('Wrong domain'); } } });
  assert.equal((await call(oldOnly, managementAccessVetoPath)).status, 404);
  const managementOnly = await server(t, { managementAccessVeto: { respond() { throw new Error('Wrong domain'); } } });
  assert.equal((await call(managementOnly, accessVetoPath)).status, 404);
  const disabled = await server(t, {});
  assert.equal((await call(disabled, managementAccessVetoPath)).status, 404);
  assert.throws(() => createHttpServer({ publicUrl: 'https://other.example/mcp', managementAccessVeto: {} }), { code: 'ACCESS_TRANSPORT' });
});

test('both callback domains share the existing concurrent capacity', { timeout: 5000 }, async t => {
  const releases = []; let admitted = 0;
  const ready = Promise.withResolvers();
  const handler = { respond() { admitted++; return new Promise(resolve => {
    const release = () => { clearTimeout(timer); resolve({ fixture: 'released' }); };
    const timer = setTimeout(release, 1000);
    releases.push(release); if (admitted === 4) ready.resolve();
  }); } };
  const target = await server(t, { accessVeto: handler, managementAccessVeto: handler });
  const pending = [accessVetoPath, managementAccessVetoPath, accessVetoPath, managementAccessVetoPath].map(path => call(target, path));
  try {
    await ready.promise;
    assert.equal((await call(target, managementAccessVetoPath)).status, 429);
    assert.equal((await call(target, accessVetoPath)).status, 429);
    assert.equal(admitted, 4);
  } finally { releases.forEach(release => release()); await Promise.all(pending); }
});
