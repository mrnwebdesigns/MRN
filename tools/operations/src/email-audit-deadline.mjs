import { McpError, ErrorCode } from '@modelcontextprotocol/sdk/types.js';
import { requireThat } from './contracts.mjs';

const { AbortController } = globalThis;
const workers = new Set(['mrn-mainwp/audit-email-address-v1', 'mainwp/get-site-knowledge-v1',
  'mainwp/get-site-v1', 'mainwp/sync-sites-v1']);

// Error codes/messages received over MCP cannot prove a local timeout. Only
// this request's private abort-reason object may establish budget exhaustion.
// Await SDK settlement; never race away leaving a sibling request behind.
export async function emailAuditDeadline({ action, ability, timeout, perCallLimit, deadlineAt }, perform) {
  requireThat([timeout, perCallLimit, deadlineAt].every(Number.isSafeInteger)
    && timeout > 0 && timeout <= perCallLimit && perCallLimit <= 30000 && typeof perform === 'function',
  'EMAIL_AUDIT_TRANSPORT_BUDGET', 'The audit transport budget is unavailable.');
  const started = Date.now(), monotonic = performance.now(), remaining = Math.min(timeout, deadlineAt - started);
  const resumable = action === 'continue' && workers.has(ability) && timeout < perCallLimit;
  const budgetExpired = () => Date.now() >= deadlineAt && Date.now() >= started && performance.now() >= monotonic;
  const pending = () => new Error('EMAIL_AUDIT_BATCH_BUDGET');
  const localTimeout = new McpError(ErrorCode.RequestTimeout, 'Audit request timed out', { timeout });
  if (remaining <= 0) {
    if (resumable && budgetExpired()) throw pending();
    throw localTimeout;
  }
  const controller = new AbortController(); let fired = false;
  const timer = setTimeout(() => { fired = true; controller.abort(localTimeout); }, remaining);
  timer.unref();
  try {
    const result = await perform({ timeout: remaining, signal: controller.signal });
    // An adapter that settles late must not release a post-deadline result.
    if (fired || Date.now() >= deadlineAt || performance.now() - monotonic >= remaining) {
      if (fired && resumable && budgetExpired()) throw pending();
      throw localTimeout;
    }
    return result;
  } catch (error) {
    if (fired && error === localTimeout && resumable && budgetExpired()) throw pending();
    throw error;
  } finally { clearTimeout(timer); }
}
