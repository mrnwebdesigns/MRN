import test from 'node:test';
import assert from 'node:assert/strict';
import { randomBytes, verify } from 'node:crypto';
import { request as httpRequest } from 'node:http';
import { delegationCanonical, delegationDigest } from '../src/delegation.mjs';
import { ManagementSourceContext, managementSourceContextPath, managementSourceContextPurpose } from '../src/management-source-context.mjs';
import { createHttpServer } from '../src/http.mjs';
import { sourceBrokerFixture } from './fixtures/management-source.mjs';
import { retainManagementSourceFixture } from './runtime/check-management-source-capture.mjs';

async function fixture(t) {
  const f = await sourceBrokerFixture(t, { now: Math.floor(Date.now() / 1000) * 1000 });
  Object.defineProperty(f.state, 'now', { get: () => Date.now() });
  const source = await retainManagementSourceFixture(f), options = { worker: f.worker, source,
    monotonic: () => f.state.mono }, reader = new ManagementSourceContext(options);
  const request = () => ({ ...f.pointer, nonce: randomBytes(32).toString('hex') });
  const read = async (input = request()) => reader.read(await f.token(), input);
  return { ...f, source, options, reader, request, read };
}
const rows = f => f.store.db.prepare('SELECT * FROM management_source_context_reads').all();

test('source metadata is signed for exact original run and immutable captured context only', async t => {
  const f = await fixture(t), input = f.request(), result = await f.read(input);
  const raw = Buffer.from(result.signed_source_context.payload_base64, 'base64'), claims = JSON.parse(raw);
  assert.equal(verify(null, Buffer.concat([Buffer.from(managementSourceContextPurpose), raw]), f.identity.publicKey,
    Buffer.from(result.signed_source_context.signature_base64, 'base64')), true);
  assert.equal(raw.toString(), delegationCanonical(claims));
  assert.equal(claims.dispatch_record_sha256, f.p.dispatch_record_sha256);
  assert.equal(claims.audience, `https://github.com/${f.p.repository}/actions/runs/${f.p.run_id}`);
  assert.deepEqual(claims.source_context, f.source.resolve(f.ledger.captured(f.job)).context);
  assert.equal(claims.source_context_sha256, delegationDigest(claims.source_context));
  assert.equal(claims.slot_sha256, delegationDigest(claims.slot)); assert.equal(claims.nonce, input.nonce);
  assert.ok(claims.expires_at * 1000 <= f.ledger.originalDispatch(f.pointer.dispatch_id).observation.original_expires_at);
  assert.deepEqual(rows(f).map(row => row.state), ['observed']);
  assert.equal(f.calls, 0);
});

test('source metadata cannot be supplied caller identity, another run, context or unauthenticated request', async t => {
  const f = await fixture(t);
  await assert.rejects(() => f.reader.read('', f.request()), { code: 'MANAGEMENT_WORKER_AUTH' });
  for (const extra of [{ email: f.actor.verifiedEmail }, { slot: f.source.slot }, { url: 'https://untrusted.test' }])
    await assert.rejects(() => f.read({ ...f.request(), ...extra }));
  f.claims.run_id = '999'; await assert.rejects(f.read); f.claims.run_id = f.p.run_id;
  await assert.rejects(() => f.read({ ...f.request(), context_sha256: 'f'.repeat(64) }));
  assert.equal(rows(f).length, 0);
});

test('metadata nonces and two-read bound survive reader reconstruction without renewing source', async t => {
  const f = await fixture(t), input = f.request(); await f.read(input); await assert.rejects(() => f.read(input));
  const restarted = new ManagementSourceContext(f.options);
  await restarted.read(await f.token(), f.request());
  await assert.rejects(async () => restarted.read(await f.token(), f.request()));
  await assert.rejects(() => f.read()); assert.equal(rows(f).length, 2); assert.equal(f.calls, 0);
});

test('revocation, trust changes and time spent resolving source withhold metadata and consume the attempt', async t => {
  for (const mutate of [f => f.access.recordPendingRevocation(f.job), f => { f.policy.policy_epoch++; },
    f => { f.state.mono += 5000; }, f => { f.worker.trust = () => ({ changed: true }); }]) {
    const f = await fixture(t), resolve = f.source.resolve.bind(f.source);
    f.source.resolve = original => { const result = resolve(original); mutate(f); return result; };
    await assert.rejects(f.read); assert.deepEqual(rows(f).map(row => row.state), ['failed']);
  }
});

test('failed source lookup remains consumed after a new reader is constructed', async t => {
  const f = await fixture(t), input = f.request(), resolve = f.source.resolve.bind(f.source);
  f.source.resolve = () => { throw new Error('unavailable'); };
  await assert.rejects(() => f.read(input)); f.source.resolve = resolve;
  const next = new ManagementSourceContext(f.options);
  await assert.rejects(async () => next.read(await f.token(), input));
  await assert.rejects(() => f.read(input)); assert.equal(rows(f)[0].state, 'failed');
  await next.read(await f.token(), f.request()); assert.equal(rows(f).length, 2);
});

test('optional source-context HTTP route preserves fixed host, POST, body and authentication gates', async t => {
  const f = await fixture(t), server = createHttpServer({ publicUrl: 'https://operations.mrnwebdesigns.com/mcp', managementSourceContext: f.reader });
  const disabled = createHttpServer({ publicUrl: 'https://operations.mrnwebdesigns.com/mcp' });
  for (const target of [server, disabled]) { await new Promise(resolve => target.listen(0, '127.0.0.1', resolve)); t.after(() => target.close()); }
  const call = (target, body, authorization, extra = {}) => new Promise((resolve, reject) => {
    const req = httpRequest({ host: '127.0.0.1', port: target.address().port, path: managementSourceContextPath, method: 'POST', agent: false,
      headers: { Host: 'operations.mrnwebdesigns.com', 'Content-Type': 'application/json', ...(authorization ? { Authorization: authorization } : {}), ...extra } }, res => {
      const chunks = []; res.on('data', c => chunks.push(c)); res.on('end', () => resolve({ status: res.statusCode, headers: res.headers, body: Buffer.concat(chunks) }));
    }); req.on('error', reject); req.end(body);
  });
  const body = delegationCanonical(f.request()), token = await f.token();
  assert.equal((await call(server, body)).status, 401);
  assert.equal((await call(server, body, token, { Origin: 'https://untrusted.test' })).status, 403);
  assert.equal((await call(server, ' ' + body, token)).status, 400);
  assert.equal((await call(server, 'x'.repeat(513), token)).status, 400);
  const result = await call(server, body, token); assert.equal(result.status, 200);
  assert.equal(result.headers['cache-control'], 'no-store'); assert.equal(result.body.toString(), delegationCanonical(JSON.parse(result.body)));
  assert.ok(result.body.length <= 32768); assert.equal((await call(disabled, body, token)).status, 404);
});
