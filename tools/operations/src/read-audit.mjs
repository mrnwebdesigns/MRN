import { AsyncLocalStorage } from 'node:async_hooks';
import { requireThat } from './contracts.mjs';

const current = new AsyncLocalStorage();

export async function traceProviderReads(actor, work) {
  const trace = { actor, ids: new Set() };
  const value = await current.run(trace, work);
  return { value, ids: [...trace.ids] };
}

export function retainProviderRead(actor, id) {
  const trace = current.getStore();
  if (!trace) return;
  requireThat(trace.actor.subject === actor.subject && trace.actor.googleSubject === actor.googleSubject,
    'DELEGATION_MISMATCH', 'An unrelated identity cannot contribute to this read receipt.');
  requireThat(trace.ids.size < 100 || trace.ids.has(id), 'AUDIT_UNAVAILABLE', 'The exact read exceeded its audit evidence limit.');
  trace.ids.add(id);
}
