import { createServer } from 'node:http';
import { StreamableHTTPServerTransport } from '@modelcontextprotocol/sdk/server/streamableHttp.js';
import { createMcp } from './mcp.mjs';
import { OpsError, requireThat, safeError } from './contracts.mjs';
import { operationsScope, oauthChallenge } from './auth.mjs';

async function readBody(req) {
  let size = 0; const chunks = [];
  for await (const chunk of req) { size += chunk.length; requireThat(size <= 65536, 'BODY_LIMIT', 'Request body exceeds the allowed size.'); chunks.push(chunk); }
  try { const data = JSON.parse(Buffer.concat(chunks).toString()); requireThat(data && !Array.isArray(data), 'BODY_INVALID', 'Use one JSON-RPC request at a time.'); return data; }
  catch (e) { if (e instanceof OpsError) throw e; throw new OpsError('BODY_INVALID', 'Invalid JSON request.'); }
}
export function createHttpServer({ service, authenticate, publicUrl, issuer, origins = [] }) {
  const canonical = new URL(publicUrl); const rate = new Map(); let active = 0;
  const json = (res, status, value) => { res.writeHead(status, { 'Content-Type': 'application/json', 'Cache-Control': 'no-store' }); res.end(JSON.stringify(value)); };
  const server = createServer(async (req, res) => {
    let admitted = false;
    try {
      requireThat(req.headers.host === canonical.host, 'HOST_DENIED', 'Unexpected Host header.');
      requireThat(!req.headers.origin || origins.includes(req.headers.origin), 'ORIGIN_DENIED', 'Origin is not allowed.');
      if (req.method === 'GET' && ['/.well-known/oauth-protected-resource', '/.well-known/oauth-protected-resource/mcp'].includes(req.url)) {
        return json(res, 200, { resource: publicUrl, authorization_servers: [issuer], scopes_supported: [operationsScope], bearer_methods_supported: ['header'] });
      }
      if (req.method === 'GET' && req.url === '/healthz') return json(res, 200, { status: 'ok', version: '0.1.0' });
      requireThat(req.url === '/mcp', 'NOT_FOUND', 'Route not found.');
      const actor = await authenticate(req.headers.authorization);
      if (req.method !== 'POST') { res.setHeader('Allow', 'POST'); return json(res, 405, { error: 'Use POST for stateless MCP.' }); }
      requireThat(String(req.headers['content-type'] || '').split(';')[0] === 'application/json', 'BODY_INVALID', 'Use application/json.');
      const now = Date.now();
      for (const [key, value] of rate) if (value.until < now) rate.delete(key);
      const counter = rate.get(actor.subject) || { until: now + 60000, count: 0 };
      counter.count++; rate.set(actor.subject, counter);
      requireThat(counter.count <= 60 && rate.size <= 10000 && active < 16, 'RATE_LIMIT', 'Service capacity reached; retry read-only requests later.');
      active++; admitted = true;
      const body = await readBody(req);
      // Never share an MCP server/transport across authenticated requests.
      const mcp = createMcp(service, actor, { publicUrl });
      const transport = new StreamableHTTPServerTransport({ sessionIdGenerator: undefined, enableJsonResponse: true });
      res.on('close', () => { void transport.close(); void mcp.close(); });
      await mcp.connect(transport); await transport.handleRequest(req, res, body);
    } catch (error) {
      const safe = safeError(error);
      const status = safe.code === 'AUTH_REQUIRED' ? 401 : safe.code === 'NOT_FOUND' ? 404 : safe.code === 'RATE_LIMIT' ? 429 : ['HOST_DENIED', 'ORIGIN_DENIED', 'FORBIDDEN'].includes(safe.code) ? 403 : 400;
      if (status === 401) res.setHeader('WWW-Authenticate', oauthChallenge(publicUrl));
      if (!res.headersSent) json(res, status, { error: safe }); else res.destroy();
    } finally { if (admitted) active--; }
  });
  server.requestTimeout = 30000; server.headersTimeout = 10000;
  return server;
}
