import { createServer } from 'node:http';
import { StreamableHTTPServerTransport } from '@modelcontextprotocol/sdk/server/streamableHttp.js';
import { createMcp } from './mcp.mjs';
import { OpsError, requireThat, safeError } from './contracts.mjs';
import { oauthChallenge } from './auth.mjs';
import { operationsScopes } from './scopes.mjs';
import { accessVetoPath, managementAccessVetoPath } from './content-access.mjs';
import { delegationCanonical, delegationIssuer } from './delegation.mjs';
import { managementEvidencePath } from './management-evidence-callback.mjs';
import { managementWorkerPath } from './management-worker-context.mjs';

async function readBody(req, { limit = 65536, canonical = false } = {}) {
  let size = 0; const chunks = [];
  for await (const chunk of req) { size += chunk.length; requireThat(size <= limit, 'BODY_LIMIT', 'Request body exceeds the allowed size.'); chunks.push(chunk); }
  try {
    const bytes = Buffer.concat(chunks), data = JSON.parse(bytes.toString());
    requireThat(data && !Array.isArray(data), 'BODY_INVALID', 'Use one JSON request at a time.');
    requireThat(!canonical || bytes.equals(Buffer.from(delegationCanonical(data))), 'BODY_INVALID', 'Use exact canonical JSON for private control requests.');
    return data;
  }
  catch (e) { if (e instanceof OpsError) throw e; throw new OpsError('BODY_INVALID', 'Invalid JSON request.'); }
}
export function createHttpServer({ service, authenticate, publicUrl, issuer, origins = [], accessVeto = null, managementAccessVeto = null, managementEvidence = null, managementWorker = null }) {
  const canonical = new URL(publicUrl); const rate = new Map(); let active = 0;
  requireThat(!(accessVeto || managementAccessVeto || managementEvidence || managementWorker) || canonical.origin === delegationIssuer, 'ACCESS_TRANSPORT', 'Private access callbacks require the fixed MRN gateway origin.');
  let controlActive = 0, controlCount = 0, controlUntil = 0;
  let evidenceActive = 0, evidenceCount = 0, evidenceUntil = 0;
  let workerActive = 0, workerCount = 0, workerUntil = 0;
  const json = (res, status, value, serialize = JSON.stringify) => {
    const body = serialize(value);
    res.writeHead(status, { 'Content-Type': 'application/json', 'Cache-Control': 'no-store' }); res.end(body);
  };
  const server = createServer(async (req, res) => {
    let admitted = false, controlAdmitted = false, evidenceAdmitted = false, workerAdmitted = false, controlTimer;
    try {
      requireThat(req.headers.host === canonical.host, 'HOST_DENIED', 'Unexpected Host header.');
      requireThat(!req.headers.origin || origins.includes(req.headers.origin), 'ORIGIN_DENIED', 'Origin is not allowed.');
      if (req.method === 'GET' && ['/.well-known/oauth-protected-resource', '/.well-known/oauth-protected-resource/mcp'].includes(req.url)) {
        return json(res, 200, { resource: publicUrl, authorization_servers: [issuer], scopes_supported: operationsScopes, bearer_methods_supported: ['header'] });
      }
      if (req.method === 'GET' && req.url === '/healthz') return json(res, 200, { status: 'ok', version: '0.1.0' });
      if (managementWorker && req.url === managementWorkerPath) {
        requireThat(req.method === 'POST' && !req.headers.origin && req.headers['content-type'] === 'application/json',
          'BODY_INVALID', 'Use the protected worker POST readback.');
        const now = Date.now();
        if (now >= workerUntil) { workerUntil = now + 60000; workerCount = 0; }
        requireThat(workerActive < 2 && ++workerCount <= 32, 'RATE_LIMIT', 'Protected context capacity reached.');
        workerActive++; workerAdmitted = true;
        controlTimer = setTimeout(() => req.destroy(), 5000); controlTimer.unref();
        const body = await readBody(req, { limit: 512, canonical: true });
        const response = await managementWorker.read(req.headers.authorization, body);
        requireThat(Buffer.byteLength(JSON.stringify(response)) <= 65536, 'BODY_LIMIT', 'The protected context exceeds its limit.');
        return json(res, 200, response);
      }
      if (managementEvidence && req.url === managementEvidencePath) {
        requireThat(req.method === 'POST' && !req.headers.origin && !req.headers.authorization
          && req.headers['content-type'] === 'application/json', 'BODY_INVALID', 'Use the private POST evidence callback.');
        const now = Date.now();
        if (now >= evidenceUntil) { evidenceUntil = now + 60000; evidenceCount = 0; }
        requireThat(evidenceActive < 2 && ++evidenceCount <= 16, 'RATE_LIMIT', 'Private evidence capacity reached.');
        evidenceActive++; evidenceAdmitted = true;
        // Separate bounded evidence budget; the old 2.5-second access-veto path
        // and its slots remain unchanged and available during downloads.
        controlTimer = setTimeout(() => req.destroy(), 60000); controlTimer.unref();
        const body = await readBody(req, { limit: 512, canonical: true });
        const response = await managementEvidence.respond(body);
        requireThat(Buffer.byteLength(delegationCanonical(response)) <= 1048576, 'BODY_LIMIT', 'The evidence response exceeds its fixed limit.');
        // The provider verifies these exact outer bytes as well as each signed
        // payload. Do not depend on schema/handler property insertion order.
        return json(res, 200, response, delegationCanonical);
      }
      const control = req.url === accessVetoPath ? accessVeto : req.url === managementAccessVetoPath ? managementAccessVeto : null;
      if (control) {
        requireThat(req.method === 'POST' && !req.headers.origin && !req.headers.authorization
          && req.headers['content-type'] === 'application/json', 'BODY_INVALID', 'Use the private POST query callback.');
        const now = Date.now();
        if (now >= controlUntil) { controlUntil = now + 60000; controlCount = 0; }
        requireThat(controlActive < 4 && ++controlCount <= 1024, 'RATE_LIMIT', 'Private control capacity reached.');
        controlActive++; controlAdmitted = true;
        // Total callback deadline includes body receipt; trickling bytes cannot
        // hold a control slot indefinitely by resetting an idle timeout.
        controlTimer = setTimeout(() => req.destroy(), 2500);
        controlTimer.unref();
        const body = await readBody(req, { limit: 512, canonical: true });
        // Opaque pointer is not authentication: respond must independently read
        // the stored query from MainWP and resolve the existing requester map.
        return json(res, 200, await control.respond(body));
      }
      requireThat(req.url === '/mcp', 'NOT_FOUND', 'Route not found.');
      // Directory verification may involve a protected backend request. Bound
      // admission before authentication so valid-token floods cannot spawn an
      // unbounded number of verification processes.
      requireThat(active < 16, 'RATE_LIMIT', 'Service capacity reached; retry read-only requests later.');
      active++; admitted = true;
      const actor = await authenticate(req.headers.authorization);
      if (req.method !== 'POST') { res.setHeader('Allow', 'POST'); return json(res, 405, { error: 'Use POST for stateless MCP.' }); }
      requireThat(String(req.headers['content-type'] || '').split(';')[0] === 'application/json', 'BODY_INVALID', 'Use application/json.');
      const now = Date.now();
      for (const [key, value] of rate) if (value.until < now) rate.delete(key);
      const counter = rate.get(actor.subject) || { until: now + 60000, count: 0 };
      counter.count++; rate.set(actor.subject, counter);
      requireThat(counter.count <= 60 && rate.size <= 10000, 'RATE_LIMIT', 'Service capacity reached; retry read-only requests later.');
      const body = await readBody(req);
      // Never share an MCP server/transport across authenticated requests.
      const mcp = createMcp(service, actor, { publicUrl });
      const transport = new StreamableHTTPServerTransport({ sessionIdGenerator: undefined, enableJsonResponse: true });
      res.on('close', () => { void transport.close(); void mcp.close(); });
      await mcp.connect(transport); await transport.handleRequest(req, res, body);
    } catch (error) {
      const safe = safeError(error);
      const status = ['AUTH_REQUIRED', 'MANAGEMENT_WORKER_AUTH'].includes(safe.code) ? 401 : safe.code === 'NOT_FOUND' ? 404 : safe.code === 'RATE_LIMIT' ? 429 : ['HOST_DENIED', 'ORIGIN_DENIED', 'FORBIDDEN'].includes(safe.code) ? 403 : 400;
      if (status === 401) res.setHeader('WWW-Authenticate', req.url === managementWorkerPath ? 'Bearer realm="MRN protected QA"' : oauthChallenge(publicUrl));
      if (!res.headersSent) json(res, status, { error: safe }); else res.destroy();
    } finally {
      clearTimeout(controlTimer);
      if (admitted) active--;
      if (controlAdmitted) controlActive--;
      if (evidenceAdmitted) evidenceActive--;
      if (workerAdmitted) workerActive--;
    }
  });
  server.requestTimeout = 30000; server.headersTimeout = 10000;
  return server;
}
