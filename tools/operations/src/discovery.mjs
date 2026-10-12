import { normalizeSiteUrl } from '../../../stack/scripts/mrn-fleet-update.mjs';
import { digest, requireThat, registrySchema } from './contracts.mjs';
import { payload, validateStatus, integrationError } from './mainwp.mjs';
import { buildKnowledge, readLocalHubRecords, safeLabel } from './knowledge.mjs';

const basicTool = 'get_sites_basic_v1';
// Directory calls are separately authorized. Site-work sessions never acquire
// bulk inventory permission just because their service credential has it.
export async function mainwpDirectory(client, guarded, exactUrls = null) {
  validateStatus(await guarded('mainwp:status', () => client.readResource({ uri: 'mainwp://status' })));
  const names = new Set(); const cursors = new Set(); let cursor;
  do {
    const result = await guarded('mainwp:discover', () => client.listTools(cursor ? { cursor } : {}));
    requireThat(Array.isArray(result.tools) && names.size + result.tools.length <= 1000, 'MAINWP_SCHEMA', 'Invalid MainWP capability directory.');
    for (const t of result.tools) names.add(t.name);
    cursor = result.nextCursor;
    requireThat(!cursor || (!cursors.has(cursor) && cursors.size < 50), 'MAINWP_SCHEMA', 'MainWP capability pagination repeated.');
    if (cursor) cursors.add(cursor);
  } while (cursor);
  requireThat(names.has(exactUrls ? 'get_site_v1' : basicTool), 'CAPABILITY_MISSING', 'The connected MainWP MCP does not expose the required website discovery capability. This is an integration gap, not a statement of MainWP product capability.');
  if (exactUrls) {
    const sites = []; const ids = new Set();
    for (const siteUrl of exactUrls) {
      validateStatus(await guarded('mainwp:status', () => client.readResource({ uri: 'mainwp://status' })));
      const site = payload(await guarded('mainwp:site_discovery', () => client.callTool({ name: 'get_site_v1', arguments: { site_id_or_domain: siteUrl, include_stats: false } }, undefined, { timeout: 60000 }), siteUrl));
      requireThat(Number.isInteger(site.id) && site.id > 0 && typeof site.name === 'string' && normalizeSiteUrl(site.url) === siteUrl,
        'TARGET_MISMATCH', 'MainWP did not return the exact URL authorized for discovery.');
      requireThat(!ids.has(site.id), 'DISCOVERY_CONFLICT', 'MainWP returned one identity for different exact website URLs.'); ids.add(site.id);
      sites.push({ id: site.id, url: siteUrl, name: safeLabel(site.name) || new URL(siteUrl).host, source: 'mainwp:get_site_v1' });
    }
    return { sites, issues: [] };
  }
  const sites = []; const issues = []; const ids = new Set(); const urls = new Set(); let total;
  for (let page = 1; page <= 100; page++) {
    validateStatus(await guarded('mainwp:status', () => client.readResource({ uri: 'mainwp://status' })));
    const data = payload(await guarded('mainwp:directory', () => client.callTool({ name: basicTool, arguments: { page, per_page: 100 } }, undefined, { timeout: 60000 })));
    requireThat(Array.isArray(data.items) && data.page === page && data.per_page === 100 && Number.isInteger(data.total) && data.total >= 0 && data.total <= 10000
      && (total === undefined || total === data.total) && data.items.length === Math.min(100, data.total - (page - 1) * 100),
    'DISCOVERY_INCOMPLETE', 'MainWP directory changed or returned incomplete pagination. Retry discovery; cached inventory was not substituted.');
    total = data.total;
    for (const row of data.items) {
      requireThat(Number.isInteger(row.id) && row.id > 0 && !ids.has(row.id) && typeof row.name === 'string' && typeof row.url === 'string', 'DISCOVERY_CONFLICT', 'MainWP returned duplicate or invalid website identities.');
      ids.add(row.id);
      let siteUrl;
      try { siteUrl = normalizeSiteUrl(row.url); }
      catch { issues.push({ source: 'mainwp', siteId: row.id, name: safeLabel(row.name), code: 'UNSUPPORTED_SITE_URL' }); continue; }
      requireThat(!urls.has(siteUrl), 'DISCOVERY_CONFLICT', 'MainWP returned more than one identity for an exact website URL.'); urls.add(siteUrl);
      sites.push({ id: row.id, url: siteUrl, name: safeLabel(row.name) || new URL(siteUrl).host });
    }
    if (ids.size === total) return { sites, issues };
  }
  requireThat(false, 'DISCOVERY_INCOMPLETE', 'MainWP directory exceeded the discovery limit.');
}

export class Discovery {
  constructor({ registry, store, connect, localHubRoots = [] }) { Object.assign(this, { registry, store, connect, localHubRoots }); this.pending = new Map(); }
  async refresh(actor, { force = false } = {}) {
    const member = this.registry.member(actor);
    const sources = member.portfolio?.actions.includes('read') ? member.portfolio.sources : [];
    const exactUrls = [...new Set(member.grants.filter(g => g.website.startsWith('https://') && g.actions.includes('read')).map(g => g.website))];
    requireThat(exactUrls.length <= 100, 'DISCOVERY_LIMIT', 'Scoped discovery supports at most 100 exact URL grants.');
    // Scoped legacy records keep working. No portfolio grant means no bulk call.
    if (!sources.length && !exactUrls.length) { this.registry.discovered.delete(actor.subject); return; }
    const prior = this.registry.discovered.get(actor.subject);
    if (!force && prior?.refreshedAt > Date.now() - 30000 && prior.policyDigest === digest(member)) return;
    if (this.pending.has(actor.subject)) { await this.pending.get(actor.subject); this.registry.member(actor); return; }
    const job = this.collect(actor, member, sources, exactUrls).catch(error => { this.registry.discovered.delete(actor.subject); throw error; }).finally(() => this.pending.delete(actor.subject));
    this.pending.set(actor.subject, job); await job;
  }
  async collect(actor, member, sources, exactUrls) {
    const authorize = () => requireThat(digest(this.registry.member(actor)) === digest(member), 'FORBIDDEN', 'Discovery permission changed; no additional source was accessed.');
    const guarded = async (event, fn, url = 'mainwp://directory') => {
      authorize(); this.store.audit(actor, { url }, '', event, 'attempted');
      try { const result = await fn(); authorize(); this.store.audit(actor, { url }, '', event, 'returned'); return result; }
      catch (e) { this.store.audit(actor, { url }, '', event, 'failed'); throw integrationError(e); }
    };
    let directory = { sites: [], issues: [] }; let local = { records: [], issues: [] }; const coverage = [];
    if (sources.includes('mainwp') || exactUrls.length) {
      authorize(); const client = await this.connect();
      try { directory = await mainwpDirectory(client, guarded, sources.includes('mainwp') ? null : exactUrls); coverage.push({ source: 'mainwp', status: 'discovered', sites: directory.sites.length,
        scope: 'Directory identities only. Runtime, inventory and health require exact-site sync.' }); }
      finally { await client.close().catch(() => {}); }
    }
    if (sources.includes('local-hub')) {
      authorize();
      this.store.audit(actor, { url: 'local-hub://records' }, '', 'knowledge:read', 'attempted');
      local = readLocalHubRecords(this.localHubRoots, authorize);
      coverage.push({ source: 'local-hub', status: this.localHubRoots.length ? 'read' : 'not_configured', records: local.records.length });
    }
    authorize();
    const websites = registrySchema.parse({ version: 1, websites: buildKnowledge(directory.sites, local.records) }).websites;
    this.registry.discovered.set(actor.subject, { websites, coverage, issues: [...directory.issues, ...local.issues],
      policyDigest: digest(member), refreshedAt: Date.now(), expiresAt: Date.now() + 3600000 });
  }
}
