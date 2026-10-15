// Disposable MCP subprocess only. No production configuration imports this file.
import assert from 'node:assert/strict';
import dns from 'node:dns';
import tls from 'node:tls';
import { appendFileSync, readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import { syncBuiltinESMExports } from 'node:module';
import { isIP } from 'node:net';

const directory = dirname(fileURLToPath(import.meta.url));
const { address, certificate } = JSON.parse(readFileSync(join(directory, 'gateway-network.json'), 'utf8'));
assert.equal(isIP(address), 4);
assert.match(address, /^(?:10\.|172\.(?:1[6-9]|2[0-9]|3[01])\.|192\.168\.)/);
assert.equal(certificate, join(directory, 'tls.crt'));
// Verify the actual hostname and this newly generated fixture CA. Never skip TLS.
tls.setDefaultCACertificates([readFileSync(certificate, 'utf8')]);
const lookup = (host, options, callback) => {
  if (typeof options === 'function') { callback = options; options = {}; }
  if (host !== 'wpcontrol.mrndev.io') return callback(new Error('Fixture blocked external DNS'));
  if (options?.all) callback(null, [{ address, family: 4 }]);
  else callback(null, address, 4);
};
dns.lookup = lookup;
dns.promises.lookup = (host, options) => new Promise((resolve, reject) => lookup(host, options, (error, result, family) => {
  if (error) reject(error); else resolve(Array.isArray(result) ? result : { address: result, family });
}));
syncBuiltinESMExports();
const originalFetch = globalThis.fetch;
globalThis.fetch = async (input, options = {}) => {
  const url = new URL(typeof input === 'string' || input instanceof URL ? input : input.url);
  assert.equal(url.origin, 'https://wpcontrol.mrndev.io', 'Fixture blocked external HTTP');
  assert.ok(url.pathname.startsWith('/wp-json/'), 'Fixture permits only the disposable REST surface');
  const method = options.method || 'GET';
  const delegated = /\/mrn-mainwp\/(?:get-delegation-challenge|get-team-access|read-delegated-operation|sync-delegated-site|run-delegated-operation|queue-delegated-qualification|read-access-veto-query)-v1\/run$/.test(url.pathname);
  if (delegated) { assert.equal(method, 'POST'); assert.equal(url.search, ''); }
  const response = await originalFetch(input, options);
  let errorCode;
  if (!response.ok) {
    try {
      const body = await response.clone().json(), candidate = body.code || body.error?.code;
      if (typeof candidate === 'string' && /^[A-Za-z0-9_-]{1,80}$/.test(candidate)) errorCode = candidate;
    } catch { /* Never retain error bodies or upstream messages. */ }
  }
  // Never retain authentication headers, tokens, signatures, nonces or payloads.
  let person;
  if (delegated && typeof options.body === 'string') {
    const envelope = JSON.parse(options.body)?.input?.delegation;
    if (envelope) {
      const claims = JSON.parse(Buffer.from(envelope.payload_base64, 'base64').toString('utf8'));
      person = { subject: claims.oauth_subject, ability: claims.ability };
    }
  }
  appendFileSync(join(directory, 'gateway-network.jsonl'), `${JSON.stringify({ path: url.pathname, method, status: response.status, errorCode, ...person })}\n`, { mode: 0o600 });
  return response;
};
