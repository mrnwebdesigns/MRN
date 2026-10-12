import https from 'node:https';
import { lookup } from 'node:dns/promises';
import ipaddr from 'ipaddr.js';
import { requireThat, digest } from './contracts.mjs';

export function publicAddress(address) {
  try { const parsed = ipaddr.process(address); return parsed.range() === 'unicast'; } catch { return false; }
}
export function responseEvidence(url, status, body, timing, { references = [], checkRest = false } = {}) {
  const html = body.toString('utf8');
  const linked = [...html.matchAll(/<(?:script|link)\b[^>]*\b(?:src|href)\s*=\s*["']([^"']+)["']/gi)].map(m => { try { return new URL(m[1], url).href; } catch { return ''; } });
  const result = { status, ...timing, bytes: body.length, contentSha256: digest(body),
    references: references.map(url => ({ url, present: linked.includes(url) })),
    titlePresent: /<title\b[^>]*>\s*[^<\s]/i.test(html), langPresent: /<html\b[^>]*\blang\s*=/i.test(html),
    noindex: /<meta\b[^>]*name\s*=\s*["']robots["'][^>]*content\s*=\s*["'][^"']*noindex/i.test(html),
    forms: (html.match(/<form\b/gi) || []).length, measuredAt: new Date().toISOString(), sourceUrl: url };
  // HTML pages and static assets say nothing about REST availability.
  if (checkRest) {
    result.restHealthy = false;
    try {
      const json = JSON.parse(html);
      result.restHealthy = status === 200 && Boolean(json && !Array.isArray(json) && Array.isArray(json.namespaces)
        && json.routes && typeof json.routes === 'object' && !Array.isArray(json.routes));
    } catch { /* Invalid JSON or a non-REST response fails this bounded check. */ }
  }
  return result;
}
// Exact registry destinations only. Pin the DNS result to the TLS connection;
// never follow redirects or fetch arbitrary links from untrusted page content.
export async function probe(url, authorize, options = {}) {
  authorize();
  const target = new URL(url);
  requireThat(target.protocol === 'https:' && !target.username && !target.password && !target.search && !target.hash && (!target.port || target.port === '443'), 'PROBE_TARGET', 'Public inspection requires a credential-free HTTPS URL on port 443.');
  const addresses = await lookup(target.hostname, { all: true });
  requireThat(addresses.length > 0 && addresses.every(a => publicAddress(a.address)), 'PROBE_PRIVATE', 'Public inspection will not connect to a private or reserved network.');
  authorize();
  return new Promise((resolve, reject) => {
    const start = performance.now(); let done = false;
    const finish = (err, value) => { if (done) return; done = true; clearTimeout(timer); err ? reject(err) : resolve(value); };
    const req = https.get(target, {
      headers: { 'User-Agent': 'MRN-Operations/0.1 (authorized inspection)', Accept: '*/*', 'Accept-Encoding': 'identity' },
      lookup: (_host, options, callback) => options.all ? callback(null, [addresses[0]]) : callback(null, addresses[0].address, addresses[0].family),
    }, res => {
      const ttfbMs = Math.round(performance.now() - start); let bytes = 0; const chunks = [];
      res.on('data', chunk => { bytes += chunk.length; if (bytes > 2 * 1024 * 1024) req.destroy(new Error('Response limit')); else chunks.push(chunk); });
      res.on('error', error => finish(error));
      res.on('end', () => {
        finish(null, responseEvidence(url, res.statusCode, Buffer.concat(chunks),
          { ttfbMs, totalMs: Math.round(performance.now() - start) }, options));
      });
    });
    const timer = setTimeout(() => req.destroy(new Error('Inspection deadline')), 20000);
    req.on('error', error => finish(error));
  });
}
