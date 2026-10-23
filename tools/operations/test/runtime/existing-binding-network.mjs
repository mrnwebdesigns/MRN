// Additional v2-only guard, loaded in the separate MCP subprocess after the
// shared fixture's canonical-host DNS/TLS restriction and redacted logger.
import './fixture-network.mjs';
import assert from 'node:assert/strict';
const restrictedFetch = globalThis.fetch;
globalThis.fetch = async (input, options = {}) => {
  const url = new URL(typeof input === 'string' || input instanceof URL ? input : input.url);
  if (/\/mrn-mainwp\/(?:(?:bind|read)-content-edit-authorization-v2|get-content-edit-qualification-context-existing-v2|(?:capture-content-edit-qualification|read-content-edit-qualification-(?:capture|measurements))-existing-v2|read-management-access-veto-query-existing-v2)\/run$/.test(url.pathname)) {
    assert.equal(options.method, 'POST'); assert.equal(url.search, '');
  }
  return restrictedFetch(input, options);
};
