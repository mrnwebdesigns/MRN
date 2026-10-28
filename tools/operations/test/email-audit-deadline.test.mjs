import test from 'node:test';
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { setImmediate as settle } from 'node:timers/promises';
import { Client } from '@modelcontextprotocol/sdk/client/index.js';
import { Server } from '@modelcontextprotocol/sdk/server/index.js';
import { InMemoryTransport } from '@modelcontextprotocol/sdk/inMemory.js';
import { CallToolRequestSchema, McpError, ErrorCode } from '@modelcontextprotocol/sdk/types.js';
import { emailAuditDeadline } from '../src/email-audit-deadline.mjs';
import { OpsError } from '../src/contracts.mjs';

const worker = 'mrn-mainwp/audit-email-address-v1';
const budget = (overrides = {}) => ({ action: 'continue', ability: worker, timeout: 25,
  perCallLimit: 30000, deadlineAt: Date.now() + 25, ...overrides });
const waitAbort = signal => new Promise(resolve => {
  if (signal.aborted) resolve(); else signal.addEventListener('abort', resolve, { once: true });
});
async function sdk(t, handle) {
  const client = new Client({ name: 'audit-budget-test', version: '1' });
  const server = new Server({ name: 'audit-budget-fixture', version: '1' }, { capabilities: { tools: {} } });
  server.setRequestHandler(CallToolRequestSchema, handle);
  const [a, b] = InMemoryTransport.createLinkedPair();
  await Promise.all([server.connect(a), client.connect(b)]);
  t.after(async () => { await client.close(); await server.close(); });
  return options => client.callTool({ name: 'audit-fixture', arguments: {} }, undefined, options);
}

test('the actual SDK preserves this request abort reason and cancels exactly once', async t => {
  let calls = 0, cancelled = 0, receivedReason;
  const call = await sdk(t, async (_request, extra) => {
    calls++; await waitAbort(extra.signal); cancelled++;
    return { content: [{ type: 'text', text: 'late evidence' }] };
  });
  await assert.rejects(() => emailAuditDeadline(budget(), async options => {
    try { return await call(options); } catch (error) {
      receivedReason = options.signal.reason;
      assert.equal(error, receivedReason); throw error;
    }
  }), { message: 'EMAIL_AUDIT_BATCH_BUDGET' });
  await settle(); assert.equal(calls, 1); assert.equal(cancelled, 1);
  assert.ok(receivedReason instanceof McpError);
});

test('a remote SDK timeout with identical fields is never proof of the local timer', async t => {
  const call = await sdk(t, async () => { throw new McpError(ErrorCode.RequestTimeout,
    'Audit request timed out', { timeout: 25 }); });
  await assert.rejects(() => emailAuditDeadline(budget({ timeout: 500, deadlineAt: Date.now() + 500 }), call),
    error => error instanceof McpError && error.code === ErrorCode.RequestTimeout);
});

test('only clipped continuation worker calls can defer after owned cancellation', async () => {
  for (const ability of [worker, 'mainwp/get-site-knowledge-v1', 'mainwp/get-site-v1', 'mainwp/sync-sites-v1'])
    await assert.rejects(() => emailAuditDeadline(budget({ ability }), async options => {
      await waitAbort(options.signal); throw options.signal.reason;
    }), { message: 'EMAIL_AUDIT_BATCH_BUDGET' });
  for (const overrides of [{ timeout: 25, perCallLimit: 25 }, { action: 'get' },
    { ability: 'mainwp/list-sites-v1' }, { ability: 'mrn-mainwp/authorize-email-address-audit-v1' }])
    await assert.rejects(() => emailAuditDeadline(budget(overrides), async options => {
      await waitAbort(options.signal); throw options.signal.reason;
    }), error => error instanceof McpError && error.code === ErrorCode.RequestTimeout);
});

test('expiry never masks access, backend or foreign timeout errors even after the owned timer fires', async () => {
  for (const error of [new OpsError('AUTH_REQUIRED', 'Revoked'), new OpsError('MAINWP_FAILED', 'Refused'),
    new McpError(ErrorCode.RequestTimeout, 'Audit request timed out', { timeout: 25 }),
    Object.assign(new Error('Request timeout'), { code: 'ETIMEDOUT' })])
    await assert.rejects(() => emailAuditDeadline(budget(), async options => {
      await waitAbort(options.signal); throw error;
    }), actual => actual === error);
});

test('late success is withheld and clock rollback cannot manufacture a deferral', async t => {
  await assert.rejects(() => emailAuditDeadline(budget(), async options => {
    await waitAbort(options.signal); return 'late evidence';
  }), { message: 'EMAIL_AUDIT_BATCH_BUDGET' });
  const now = Date.now();
  const clock = t.mock.method(Date, 'now', () => now);
  await assert.rejects(() => emailAuditDeadline(budget(), async options => {
    await waitAbort(options.signal); return 'late evidence';
  }), error => error instanceof McpError && error.code === ErrorCode.RequestTimeout);
  clock.mock.restore();
  // A blocked event loop can settle before its overdue timer callback runs.
  await assert.rejects(() => emailAuditDeadline(budget(), async () => {
    const end = performance.now() + 30; while (performance.now() < end) { /* block */ }
    return 'late evidence';
  }), error => error instanceof McpError && error.code === ErrorCode.RequestTimeout);
});

test('expired and invalid budgets do not dispatch; successful calls clean up their timer', async () => {
  let calls = 0;
  await assert.rejects(() => emailAuditDeadline(budget({ deadlineAt: Date.now() - 1 }), async () => calls++),
    { message: 'EMAIL_AUDIT_BATCH_BUDGET' });
  for (const overrides of [{ deadlineAt: undefined }, { timeout: 0 }, { perCallLimit: 30001 }, { timeout: 30001 }])
    await assert.rejects(() => emailAuditDeadline(budget(overrides), async () => calls++), { code: 'EMAIL_AUDIT_TRANSPORT_BUDGET' });
  assert.equal(calls, 0);
  let signal;
  const value = await emailAuditDeadline(budget(), async options => { signal = options.signal; return 'valid'; });
  assert.equal(value, 'valid');
  await new Promise(resolve => setTimeout(resolve, 40)); assert.equal(signal.aborted, false);
});

test('a fresh process remains alive until its sole audit cancellation settles', () => {
  const module = new URL('../src/email-audit-deadline.mjs', import.meta.url).href;
  const source = `
    import { emailAuditDeadline } from ${JSON.stringify(module)};
    import assert from 'node:assert/strict';
    await assert.rejects(() => emailAuditDeadline({ action: 'continue', ability: 'mrn-mainwp/audit-email-address-v1',
      timeout: 25, perCallLimit: 30000, deadlineAt: Date.now() + 25 }, async ({ signal }) => {
        await new Promise(resolve => signal.addEventListener('abort', resolve, { once: true }));
        throw signal.reason;
      }), { message: 'EMAIL_AUDIT_BATCH_BUDGET' });
    process.stdout.write('settled');
  `;
  assert.equal(execFileSync(process.execPath, ['--input-type=module', '-e', source],
    { encoding: 'utf8', timeout: 5000, maxBuffer: 4096 }), 'settled');
});
