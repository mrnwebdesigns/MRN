import { Client } from '@modelcontextprotocol/sdk/client/index.js';
import { StdioClientTransport } from '@modelcontextprotocol/sdk/client/stdio.js';
import { parseResourceResult, parseToolResult, normalizeSiteUrl } from '../../../stack/scripts/mrn-fleet-update.mjs';
import { OpsError, requireThat } from './contracts.mjs';

export const toolNames = {
  site: 'get_site_v1', sync: 'sync_sites_v1', plugins: 'get_site_plugins_v1', themes: 'get_site_themes_v1',
  security: 'get_site_security_v1', updates: 'get_site_updates_v1', changes: 'get_site_changes_v1',
  runtime: 'mrn_mainwp__get_stack_runtime_report_v1', qualify: 'mrn_mainwp__qualify_stack_site_v1',
  preflight: 'mrn_mainwp__preflight_stack_plugin_update_v1', backup: 'mrn_mainwp__start_database_backup_v1',
  backupStatus: 'mrn_mainwp__get_database_backup_status_v1', update: 'mrn_mainwp__update_stack_plugin_v1',
  rollback: 'mrn_mainwp__rollback_stack_plugin_v1',
};
const readTools = new Set(Object.entries(toolNames).filter(([k]) => !['preflight', 'backup', 'backupStatus', 'update', 'rollback'].includes(k)).map(([,v]) => v));
function withoutSecrets(value) {
  if (Array.isArray(value)) return value.map(withoutSecrets);
  if (!value || typeof value !== 'object') return value;
  return Object.fromEntries(Object.entries(value).filter(([key]) => !/(password|secret|token|credential|authorization|cookie|package_base64|^nonce$|^receipt$)/i.test(key)).map(([key, item]) => [key, withoutSecrets(item)]));
}

export function integrationError(error) {
  if (error instanceof OpsError) return error;
  // Classify privately, without reflecting arbitrary remote text or credentials.
  const message = String(error?.message || '');
  if (/timeout|timed out/i.test(message)) return new OpsError('DOWNSTREAM_TIMEOUT', 'MainWP timed out. Read back exact state before retrying any mutation.');
  if (/401|403|authentication|permission|unauthoriz/i.test(message)) return new OpsError('MAINWP_ACCESS', 'MainWP rejected authentication or permission. Repair the approved connection; no fallback was attempted.');
  return new OpsError('MAINWP_FAILED', 'The approved MainWP connection failed or returned an unsupported response.');
}
export function payload(result, { allowError = false } = {}) {
  try { return parseToolResult(result, 'MainWP', { allowError }).payload; } catch (error) { throw integrationError(error); }
}
export function validateStatus(result) {
  let status;
  try { status = parseResourceResult(result, 'MainWP status'); } catch { throw new OpsError('MAINWP_STATUS', 'MainWP did not return valid connection status.'); }
  requireThat(status.connected === true && status.dashboardHost === 'wpcontrol.mrndev.io' && Number.isInteger(status.abilitiesCount) && status.abilitiesCount > 0,
    'MAINWP_IDENTITY', 'MainWP must be connected to wpcontrol.mrndev.io with nonzero capabilities.');
  return { connected: true, dashboardHost: status.dashboardHost, abilitiesCount: status.abilitiesCount };
}

export async function connectMainwp(config, environment = process.env) {
  requireThat(config.command && Array.isArray(config.args) && config.cwd, 'MAINWP_CONFIG', 'Configure the hosted MainWP process, arguments, working directory and credential references explicitly.');
  const env = { PATH: environment.PATH || '/usr/local/bin:/usr/bin:/bin' };
  for (const [key, { env: reference }] of Object.entries(config.envRefs || {})) {
    requireThat(/^(MAINWP_[A-Z_]+|HOME)$/.test(key) && /^[A-Z][A-Z0-9_]*$/.test(reference), 'MAINWP_CONFIG', 'Invalid downstream environment reference.');
    requireThat(typeof environment[reference] === 'string' && environment[reference].length > 0, 'MAINWP_CREDENTIAL_MISSING', 'A configured service credential reference is unavailable.');
    env[key] = environment[reference];
  }
  // Explicit hosted identity, no ambient application or personal-login inheritance.
  requireThat(env.MAINWP_URL === 'https://wpcontrol.mrndev.io' && env.MAINWP_USER && env.MAINWP_APP_PASSWORD && env.HOME && ['true', 'false'].includes(env.MAINWP_SAFE_MODE),
    'MAINWP_CONFIG', 'Hosted MainWP requires explicit dashboard and application-password references.');
  Object.assign(env, { MAINWP_REQUIRE_USER_CONFIRMATION: 'true', MAINWP_RETRY_ENABLED: 'false', MAINWP_ABILITY_NAMESPACES: 'mainwp,mrn-mainwp', MAINWP_SKIP_SSL_VERIFY: 'false', MAINWP_ALLOW_HTTP: 'false' });
  const client = new Client({ name: 'mrn-operations', version: '0.1.0' });
  const transport = new StdioClientTransport({ command: config.command, args: config.args, cwd: config.cwd, env, stderr: 'pipe' });
  transport.stderr?.on('data', () => {}); // MainWP diagnostics may contain sensitive configuration.
  try { await client.connect(transport); return client; }
  catch (error) { await client.close().catch(() => {}); throw integrationError(error); }
}

// This is the ONLY MainWP access surface given to workflow adapters. The service
// credential's broad access never replaces the requesting person's permissions.
export class MainwpSession {
  constructor(client, registry, store, actor, target, { action = 'read', operation = '', onMutation = () => {}, checkAdmission = () => {} } = {}) {
    Object.assign(this, { client, registry, store, actor, target, action, operation, onMutation, checkAdmission });
    this.siteId = null; this.names = null;
  }
  authorize() { this.registry.current(this.actor, this.target, this.action); this.checkAdmission(); }
  async guarded(event, fn) {
    this.authorize();
    this.store.audit(this.actor, this.target, this.operation, event, 'attempted');
    try { const result = await fn(); this.store.audit(this.actor, this.target, this.operation, event, 'returned'); return result; }
    catch (error) { this.store.audit(this.actor, this.target, this.operation, event, 'failed'); throw integrationError(error); }
  }
  async readResource({ uri }) {
    requireThat(uri === 'mainwp://status', 'CAPABILITY_DENIED', 'Only MainWP connection status is exposed to workflows.');
    const result = await this.guarded('mainwp:status', () => this.client.readResource({ uri }));
    validateStatus(result); return result;
  }
  async listTools() {
    await this.readResource({ uri: 'mainwp://status' });
    const all = []; let cursor;
    do {
      const result = await this.guarded('mainwp:discover', () => this.client.listTools(cursor ? { cursor } : {}));
      all.push(...result.tools); cursor = result.nextCursor;
      requireThat(all.length <= 1000, 'MAINWP_SCHEMA', 'Unexpected capability pagination.');
    } while (cursor);
    this.names = new Set(all.map(t => t.name));
    return { tools: all.filter(t => Object.values(toolNames).includes(t.name)) };
  }
  async supports(name) { if (!this.names) await this.listTools(); return this.names.has(name); }
  async callTool(request, schema, options) {
    const { name, arguments: args } = request;
    requireThat(Object.values(toolNames).includes(name), 'CAPABILITY_DENIED', 'This downstream capability is not admitted by MRN Operations.');
    requireThat(await this.supports(name), 'CAPABILITY_MISSING', 'The connected MainWP service does not expose this required capability.');
    await this.readResource({ uri: 'mainwp://status' });
    if (!readTools.has(name)) requireThat(this.action !== 'read' && this.action !== 'test', 'CAPABILITY_DENIED', 'This operation requires repair or release permissions.');
    const canWrite = ['deploy_development', 'release_production'].includes(this.action);
    if ([toolNames.backup, toolNames.backupStatus, toolNames.update, toolNames.rollback].includes(name)) requireThat(canWrite, 'CAPABILITY_DENIED', 'Release permission is required before a backup or mutation.');
    if (name === toolNames.site) {
      requireThat(args.site_id_or_domain === this.target.url, 'TARGET_MISMATCH', 'Resolve the exact registered URL before using a site ID.');
    } else {
      requireThat(Number.isInteger(this.siteId) && this.siteId > 0, 'TARGET_UNRESOLVED', 'Exact site resolution is required.');
      if (name === toolNames.sync) requireThat(Object.keys(args).length === 1 && Array.isArray(args.site_ids) && args.site_ids.length === 1 && args.site_ids[0] === this.siteId,
        'TARGET_MISMATCH', 'Synchronization requires exactly one resolved site.');
      else requireThat((args.site_id ?? args.site_id_or_domain) === this.siteId && (!args.site_url || normalizeSiteUrl(args.site_url) === this.target.url), 'TARGET_MISMATCH', 'The downstream target differs from the authorized website.');
    }
    if ([toolNames.backup, toolNames.update, toolNames.rollback].includes(name) && (name === toolNames.backup || args.user_confirmed === true)) this.onMutation();
    const result = await this.guarded(`mainwp:${name}`, () => this.client.callTool(request, schema, { timeout: 120000, ...options }));
    if (name === toolNames.site) {
      const site = payload(result); const id = Number(site.id ?? site.site_id);
      requireThat(Number.isInteger(id) && id > 0 && normalizeSiteUrl(site.url ?? site.site_url ?? '') === this.target.url && site.status === 'connected', 'TARGET_MISMATCH', 'MainWP did not return the exact connected website.');
      requireThat(this.siteId === null || this.siteId === id, 'TARGET_CHANGED', 'MainWP website identity changed during this operation.');
      this.siteId = id;
    }
    // Fleet persists runtime/preflight/write evidence. Strip credential-shaped
    // fields before it can persist unexpected downstream metadata. Tokens needed
    // for confirmation/backup remain ephemeral on their dedicated responses.
    if ([toolNames.runtime, toolNames.preflight].includes(name) || ([toolNames.update, toolNames.rollback].includes(name) && args.user_confirmed === true)) {
      return { ...result, content: [{ type: 'text', text: JSON.stringify(withoutSecrets(payload(result, { allowError: true }))) }], structuredContent: undefined };
    }
    return result;
  }
  async call(name, args, options) { return payload(await this.callTool({ name, arguments: args }, undefined, options)); }
  async fresh() {
    await this.call(toolNames.site, { site_id_or_domain: this.target.url, include_stats: false });
    const start = Math.floor(Date.now() / 1000) * 1000;
    try { await this.call(toolNames.sync, { site_ids: [this.siteId] }); }
    catch (e) { if (e.code !== 'DOWNSTREAM_TIMEOUT') throw e; }
    const site = await this.call(toolNames.site, { site_id_or_domain: this.target.url, include_stats: false });
    const at = Date.parse(site.last_sync);
    requireThat(Number.isFinite(at) && at >= start && at <= Date.now() + 60000, 'STALE_INVENTORY', 'A targeted sync and exact readback did not prove fresh inventory.');
    return { id: this.siteId, url: this.target.url, syncedAt: new Date(at).toISOString() };
  }
  async runtime() {
    const data = await this.call(toolNames.runtime, { site_id: this.siteId });
    requireThat(Number(data.site_id) === this.siteId && normalizeSiteUrl(data.site_url || '') === this.target.url, 'TARGET_MISMATCH', 'Runtime evidence belongs to a different website.');
    return data.report;
  }
  async close() { await this.client.close(); }
}
