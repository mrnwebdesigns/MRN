/** Paced Playwright browser requests for use in MRN release proof adapters. */
import { performance } from 'node:perf_hooks';
import { setTimeout as delay } from 'node:timers/promises';

/**
 * Each approved origin gets one in-flight fetch. Abort further traffic after
 * provider throttling so the orchestrator can record Retry-After before retry.
 * Create the context with serviceWorkers blocked before installing this route.
 */
export async function paceBrowser(context, origins, { intervalMs = 250, timeoutMs = 15000 } = {}) {
  const allowed = new Set(origins);
  const queues = new Map();
  let throttle;
  let transportFailure = false;

  await context.route('**/*', async route => {
    const request = route.request();
    const { origin, protocol } = new URL(request.url());
    if (!allowed.has(origin) || !['http:', 'https:'].includes(protocol) ||
        !['GET', 'HEAD'].includes(request.method())) {
      await route.abort('blockedbyclient');
      return;
    }

    const prior = queues.get(origin) ?? { pending: Promise.resolve(), next: 0 };
    const entry = { next: prior.next };
    entry.pending = prior.pending.then(async () => {
      const remaining = prior.next - performance.now();
      if (remaining > 0) await delay(remaining);
      if (throttle || transportFailure) {
        await route.abort();
        return;
      }
      entry.next = performance.now() + intervalMs;
      // Let the browser see and route redirects itself so every hop must pass
      // the same origin and safe-method policy.
      const response = await route.fetch({ maxRedirects: 0, timeout: timeoutMs });
      try {
        const status = response.status();
        const retryAfter = response.headers()['retry-after'];
        if (status === 429 || (status === 503 && retryAfter !== undefined)) {
          throttle ??= { status, retry_after: retryAfter ?? null };
          await route.abort();
        } else {
          await route.fulfill({ response });
        }
      } finally {
        await response.dispose();
      }
    }).catch(async () => {
      if (!throttle) transportFailure = true;
      await route.abort().catch(() => {});
    });
    queues.set(origin, entry);
    await entry.pending;
    if (queues.get(origin) === entry) {
      // Keep the latest spacing deadline without retaining the Promise chain.
      queues.set(origin, { next: entry.next, pending: Promise.resolve() });
    }
  });

  // Explicitly block WebSockets so proof cannot send app-level writes.
  await context.routeWebSocket('**/*', socket => socket.close());
  return () => ({ throttle, transport_failure: transportFailure });
}
