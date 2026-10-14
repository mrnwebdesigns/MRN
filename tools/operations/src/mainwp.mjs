import { Client } from '@modelcontextprotocol/sdk/client/index.js';
import { StdioClientTransport } from '@modelcontextprotocol/sdk/client/stdio.js';
import { parseResourceResult, parseToolResult, normalizeSiteUrl } from '../../../stack/scripts/mrn-fleet-update.mjs';
import { OpsError, requireThat } from './contracts.mjs';

export const toolNames = {
  site: 'get_site_v1', sync: 'sync_sites_v1', plugins: 'get_site_plugins_v1', themes: 'get_site_themes_v1',
  security: 'get_site_security_v1', updates: 'get_site_updates_v1', ignoredUpdates: 'list_ignored_updates_v1', changes: 'get_site_changes_v1',
  runtime: 'mrn_mainwp__get_stack_runtime_report_v1', qualify: 'mrn_mainwp__qualify_stack_site_v1',
  preflight: 'mrn_mainwp__preflight_stack_plugin_update_v1', backup: 'mrn_mainwp__start_database_backup_v1',
  backupStatus: 'mrn_mainwp__get_database_backup_status_v1', update: 'mrn_mainwp__update_stack_plugin_v1',
  rollback: 'mrn_mainwp__rollback_stack_plugin_v1',
  abandonedPlugins: 'get_abandoned_plugins_v1', abandonedThemes: 'get_abandoned_themes_v1',
  siteClient: 'get_site_client_v1', siteCosts: 'get_site_costs_v1',
  fonts: 'mrn_mainwp__get_google_fonts_status_v1', backupPolicy: 'mrn_mainwp__inspect_backup_retention_policy_v1',
  deploymentAgent: 'mrn_mainwp__get_stack_deployment_agent_status_v1',
  codeInspect: 'mrn_mainwp__inspect_code_file_v1',
  codePrepare: 'mrn_mainwp__prepare_code_change_v1', codeApprove: 'mrn_mainwp__approve_code_change_v1',
  codeExecute: 'mrn_mainwp__execute_code_change_v1', codeStatus: 'mrn_mainwp__get_code_operation_v1',
  contentPolicy: 'mrn_mainwp__get_content_policy_v1', contentInspect: 'mrn_mainwp__inspect_content_v1',
  contentTargetPolicy: 'mrn_mainwp__get_content_policy_v2',
  contentPrepare: 'mrn_mainwp__prepare_content_change_v1', contentApprove: 'mrn_mainwp__approve_content_change_v1',
  contentExecute: 'mrn_mainwp__execute_content_change_v1', contentStatus: 'mrn_mainwp__get_content_operation_v1',
  contentRollback: 'mrn_mainwp__prepare_content_rollback_v1', contentMigration: 'mrn_mainwp__get_content_migration_v1',
};
const readTools = new Set(Object.entries(toolNames).filter(([k]) => !['preflight', 'backup', 'backupStatus', 'update', 'rollback', 'codePrepare', 'codeApprove', 'codeExecute', 'contentPrepare', 'contentApprove', 'contentExecute', 'contentRollback'].includes(k)).map(([,v]) => v));
export function withoutSecrets(value) {
  if (Array.isArray(value)) return value.map(withoutSecrets);
  if (typeof value === 'string' && /^https?:\/\//i.test(value)) {
    try { const url = new URL(value); url.username = ''; url.password = ''; url.search = ''; url.hash = ''; return url.href; }
    catch { return '[invalid URL omitted]'; }
  }
  if (!value || typeof value !== 'object') return value;
  return Object.fromEntries(Object.entries(value).filter(([key]) => !/(password|secret|token|credential|authorization|cookie|license.?key|api.?key|private.?key|access.?key|package_base64|^nonce$|^receipt$|^(?:package|download|notes|custom_fields|meta)(?:_url|_link)?$)/i.test(key)).map(([key, item]) => [key, withoutSecrets(item)]));
}

const providerBlockers = Object.freeze({
  SAFE_MODE_BLOCKED: ['SAFE_MODE_BLOCKED', 'MainWP Safe Mode blocked this action. MRN services must review the restriction; do not retry through another route.'],
  mrn_delegation_target: ['TARGET_MISMATCH', 'MainWP could not bind this request to one exact permitted website. Resolve its exact URL again; do not substitute a different target or access route.'],
  mrn_content_disabled: ['CONTENT_DISABLED', 'Content editing is not enabled in MRN services. Staff do not need another account or enrollment.'],
  mrn_content_profile: ['CONTENT_PROFILE_UNQUALIFIED', 'MRN services have not qualified the current rendering dependencies for this exact content target. This change remains blocked.'],
  mrn_content_ownership: ['CONTENT_OWNERSHIP', 'The content owner or rendering adapter is not qualified. ACF and builder content require their own supported workflow.'],
  mrn_content_target: ['CONTENT_TARGET_MISMATCH', 'The provider could not verify the exact content record or retained operation. Resolve and inspect the target again.'],
  mrn_content_size: ['CONTENT_SIZE', 'The complete content request exceeds the qualified limit. No content was truncated.'],
  mrn_content_precondition: ['CONTENT_PRECONDITION', 'The current content no longer matches the reviewed snapshot. Inspect it again and prepare a fresh preview.'],
  mrn_content_qa: ['CONTENT_QA_REQUIRED', 'The exact content plan lacks a valid protected QA receipt. A new qualifying QA run is required.'],
  mrn_content_approval: ['CONTENT_APPROVAL', 'The provider could not verify the current operator, exact approval or required execution evidence. Inspect the operation before proceeding.'],
  mrn_content_backup: ['BACKUP_UNVERIFIED', 'A fresh, verified remote database backup for this exact website is required.'],
  mrn_content_locked: ['SITE_LOCKED', 'Another code or content operation still holds this website lock. Inspect its outcome; do not force-unlock or retry the change.'],
  mrn_content_consumed: ['CONTENT_CONSUMED', 'This operation or its evidence has already been consumed. Read its retained outcome; do not execute it again.'],
  mrn_content_uncertain: ['CONTENT_OUTCOME_UNVERIFIED', 'The provider could not verify the operation outcome. Retain its lock and rollback evidence; do not retry the change.'],
  mrn_content_unsupported: ['CONTENT_ADAPTER_UNAVAILABLE', 'The required typed MainWP content adapter is unavailable. MRN services must complete the integration.'],
  mrn_code_operation: ['OPERATION_STATUS_UNAVAILABLE', 'The retained MainWP operation is unavailable. Keep any uncertain outcome and lock until qualified reconciliation.'],
  mrn_code_directory_inactive: ['STAFF_ACCESS_DENIED', 'The Google Workspace account is inactive, revoked or no longer matches the verified MRN identity. Access is denied.'],
  mrn_code_directory_unavailable: ['STAFF_VERIFICATION_UNAVAILABLE', 'Current Google Workspace membership could not be verified. Access remains blocked until verification is available.'],
  mrn_code_directory_setup: ['STAFF_VERIFICATION_UNAVAILABLE', 'MRN services have not configured the Google Workspace membership check. Access remains blocked.'],
  mrn_code_directory_auth: ['STAFF_VERIFICATION_UNAVAILABLE', 'The central Google Workspace membership check is unavailable. MRN services must restore it.'],
  mrn_code_directory_key: ['STAFF_VERIFICATION_UNAVAILABLE', 'The central Google Workspace membership check is unavailable. MRN services must restore it.'],
  mrn_code_directory_integrity: ['STAFF_VERIFICATION_UNAVAILABLE', 'The central Google Workspace membership check is unavailable. MRN services must restore it.'],
});
export function integrationError(error) {
  if (error instanceof OpsError) return error;
  // Classify privately, without reflecting arbitrary remote text or credentials.
  const message = String(error?.message || '');
  // The pinned MCP transport retains WordPress's code in this fixed prefix.
  // Only recognize exact codes or that prefix, never instructions/substrings in
  // arbitrary error prose. Caller-visible text comes solely from this table.
  const code = typeof error?.code === 'string' && Object.hasOwn(providerBlockers, error.code) ? error.code
    : /^(?:MainWP failed: )?Ability execution failed: ([a-zA-Z0-9_]+) - /.exec(message)?.[1];
  if (code && Object.hasOwn(providerBlockers, code)) return new OpsError(...providerBlockers[code]);
  if (/timeout|timed out/i.test(message)) return new OpsError('DOWNSTREAM_TIMEOUT', 'MainWP timed out. Read back exact state before retrying any mutation.');
  if (/401|403|authentication|permission|unauthoriz/i.test(message)) return new OpsError('MAINWP_ACCESS', 'MainWP rejected authentication or permission. Repair the approved connection; no fallback was attempted.');
  if (/\bmrn_mainwp_stack_report_unavailable\b/.test(message)) return new OpsError('STACK_REPORT_UNAVAILABLE', 'The child site did not return a Stack runtime report. Read-only qualification may explain what is missing.');
  return new OpsError('MAINWP_FAILED', 'The approved MainWP connection failed or returned an unsupported response.');
}
export function payload(result, { allowError = false } = {}) {
  try {
    const parsed = parseToolResult(result, 'MainWP', { allowError: true });
    if (parsed.isError && !allowError) {
      const remote = parsed.payload?.error;
      throw integrationError({ code: typeof remote === 'string' ? remote : remote?.code,
        message: typeof remote?.message === 'string' ? remote.message : parsed.payload?.message ?? (typeof remote === 'string' ? remote : '') });
    }
    return parsed.payload;
  } catch (error) { throw integrationError(error); }
}
export function validateStatus(result) {
  let status;
  try { status = parseResourceResult(result, 'MainWP status'); } catch { throw new OpsError('MAINWP_STATUS', 'MainWP did not return valid connection status.'); }
  requireThat(status.connected === true && status.dashboardHost === 'wpcontrol.mrndev.io' && Number.isInteger(status.abilitiesCount) && status.abilitiesCount > 0,
    'MAINWP_IDENTITY', 'MainWP must be connected to wpcontrol.mrndev.io with nonzero capabilities.');
  return { connected: true, dashboardHost: status.dashboardHost, abilitiesCount: status.abilitiesCount };
}

function validateSync(result, siteId, siteUrl) {
  let exact = false;
  if (Array.isArray(result?.synced) && result.synced.length === 1 && result.synced[0]?.id === siteId
    && typeof result.synced[0]?.url === 'string') {
    try { exact = normalizeSiteUrl(result.synced[0].url) === siteUrl; } catch { /* Untrusted URL details stay private. */ }
  }
  requireThat(result?.queued === false && result.total_synced === 1 && result.total_errors === 0
    && Array.isArray(result.errors) && result.errors.length === 0 && exact,
  'SYNC_UNVERIFIED', 'MainWP did not confirm a successful sync of this exact website. Fresh-state checks remain blocked; do not switch targets or access routes.');
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
    if ([toolNames.backup, toolNames.backupStatus].includes(name)) requireThat(canWrite || ['code', 'content'].includes(this.action), 'CAPABILITY_DENIED', 'Change permission is required before a backup.');
    if ([toolNames.update, toolNames.rollback].includes(name)) requireThat(canWrite, 'CAPABILITY_DENIED', 'Release permission is required before a Fleet mutation.');
    if ([toolNames.codePrepare, toolNames.codeApprove, toolNames.codeExecute].includes(name)) requireThat(this.action === 'code', 'CAPABILITY_DENIED', 'Verified code-change permission is required.');
    if ([toolNames.contentPrepare, toolNames.contentApprove, toolNames.contentExecute, toolNames.contentRollback].includes(name)) requireThat(this.action === 'content', 'CAPABILITY_DENIED', 'Verified content-change permission is required.');
    if (name === toolNames.site) {
      requireThat(args.site_id_or_domain === this.target.url, 'TARGET_MISMATCH', 'Resolve the exact registered URL before using a site ID.');
    } else {
      requireThat(Number.isInteger(this.siteId) && this.siteId > 0, 'TARGET_UNRESOLVED', 'Exact site resolution is required.');
      if (name === toolNames.sync) requireThat(Object.keys(args).length === 1 && Array.isArray(args.site_ids) && args.site_ids.length === 1 && args.site_ids[0] === this.siteId,
        'TARGET_MISMATCH', 'Synchronization requires exactly one resolved site.');
      else if (name === toolNames.ignoredUpdates) requireThat(Object.keys(args).length === 1
        && Array.isArray(args.site_ids_or_domains) && args.site_ids_or_domains.length === 1 && args.site_ids_or_domains[0] === this.siteId,
      'TARGET_MISMATCH', 'Ignored-update inspection requires exactly one resolved site.');
      else if ([toolNames.codeApprove, toolNames.codeExecute, toolNames.codeStatus].includes(name)) requireThat(this.codeOperation && args.operation_id === this.codeOperation.id
        && (name === toolNames.codeStatus || args.reviewed_digest === this.codeOperation.digest), 'TARGET_MISMATCH', 'The provider operation must belong to the exact stored website plan.');
      else if ([toolNames.contentApprove, toolNames.contentExecute, toolNames.contentStatus, toolNames.contentMigration, toolNames.contentRollback].includes(name)) requireThat(this.contentOperation && args.operation_id === this.contentOperation.id
        && (![toolNames.contentApprove, toolNames.contentExecute].includes(name) || args.reviewed_digest === this.contentOperation.digest), 'TARGET_MISMATCH', 'The content operation must belong to the exact stored website plan.');
      else requireThat((args.site_id ?? args.site_id_or_domain) === this.siteId && (!args.site_url || normalizeSiteUrl(args.site_url) === this.target.url), 'TARGET_MISMATCH', 'The downstream target differs from the authorized website.');
    }
    if ([toolNames.backup, toolNames.update, toolNames.rollback].includes(name) && (name === toolNames.backup || args.user_confirmed === true)) this.onMutation();
    if ([toolNames.codeExecute, toolNames.contentExecute].includes(name)) this.onMutation();
    const result = await this.guarded(`mainwp:${name}`, () => this.client.callTool(request, schema, { timeout: 120000, ...options }));
    // Native MainWP can return HTTP 200 with per-site sync errors. A transport
    // response alone must never count as a successful synchronization.
    if (name === toolNames.sync) validateSync(payload(result), this.siteId, this.target.url);
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
  async qualification() {
    const data = await this.call(toolNames.qualify, { site_id: this.siteId });
    requireThat(Number(data.site_id) === this.siteId && normalizeSiteUrl(data.site_url || '') === this.target.url, 'TARGET_MISMATCH', 'Qualification evidence belongs to a different website.');
    return data;
  }
  async close() { await this.client.close(); }
}
