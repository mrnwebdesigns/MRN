// Additional v2-only guard, loaded in the separate MCP subprocess after the
// shared fixture's canonical-host DNS/TLS restriction and redacted logger.
import './fixture-network.mjs';
import assert from 'node:assert/strict';
import { appendFileSync } from 'node:fs';
import { bindingSourceContextFailure } from './existing-binding-diagnostics.mjs';
const restrictedFetch = globalThis.fetch;
globalThis.fetch = async (input, options = {}) => {
  const url = new URL(typeof input === 'string' || input instanceof URL ? input : input.url);
  if (/\/mrn-mainwp\/(?:(?:bind|read)-content-edit-authorization-v2|get-content-edit-qualification-context-existing-v2|(?:capture-content-edit-qualification|read-content-edit-qualification-(?:capture|measurements|source-context))-existing-v2|read-management-access-veto-query-existing-v2)\/run$/.test(url.pathname)) {
    assert.equal(options.method, 'POST'); assert.equal(url.search, '');
  }
  const response = await restrictedFetch(input, options);
  if (!response.ok && url.pathname === '/wp-json/wp-abilities/v1/abilities/mrn-mainwp/read-content-edit-qualification-source-context-existing-v2/run') {
    const reader = response.clone().body?.getReader();
    if (reader) {
      try {
        const chunks = []; let size = 0;
        while (true) {
          const { done, value } = await reader.read(); if (done) break;
          size += value.byteLength;
          if (size > 16384) { reader.cancel().catch(() => {}); break; }
          chunks.push(Buffer.from(value));
        }
        const diagnostic = size <= 16384 ? bindingSourceContextFailure(Buffer.concat(chunks).toString('utf8')) : null;
        if (diagnostic) appendFileSync(new URL('./gateway-existing-source-diagnostics.jsonl', import.meta.url),
          JSON.stringify(diagnostic) + '\n', { mode: 0o600 });
      } catch { /* Diagnostic failure must not replace the actual tool result. */ }
      finally { reader.releaseLock(); }
    }
  }
  return response;
};
