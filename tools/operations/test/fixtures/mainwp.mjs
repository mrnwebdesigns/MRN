import { readFileSync, writeFileSync } from 'node:fs';
import { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js';
import { z } from 'zod';
import { toolNames } from '../../src/mainwp.mjs';
import { pathToFileURL } from 'node:url';

export const response = data => ({ content: [{ type: 'text', text: JSON.stringify(data) }] });
export class FixtureMainwp {
  constructor(data, persist = () => {}) { this.data = data; this.persist = persist; }
  async close() {}
  async readResource() {
    return { contents: [{ uri: 'mainwp://status', text: JSON.stringify({ connected: true, dashboardHost: this.data.host || 'wpcontrol.mrndev.io', abilitiesCount: 84 }) }] };
  }
  async listTools() { return { tools: Object.values(toolNames).filter(n => !(this.data.missing || []).includes(n)).map(name => ({ name, inputSchema: { type: 'object', properties: {} } })) }; }
  async callTool({ name, arguments: a }) {
    this.data.calls.push(name);
    if (name === this.data.fail) throw new Error('DOWNSTREAM FAILURE private-token-sentinel');
    if (name === toolNames.site) return response({ id: this.data.id, url: this.data.url, status: 'connected', last_sync: this.data.lastSync || '2000-01-01T00:00:00Z' });
    if (name === toolNames.sync) {
      if (!this.data.stale) this.data.lastSync = new Date().toISOString();
      this.persist();
      if (this.data.syncTimeout) throw new Error('Request timed out');
      return response({ success: true });
    }
    const forward = this.data.artifacts.target; const old = this.data.artifacts.rollback;
    const current = this.data.updated ? forward : old;
    if (name === toolNames.runtime) return response({ site_id: this.data.id, site_url: this.data.url, report: {
      schema_version: 1,
      release_lock: { present: !this.data.partial, valid: !this.data.partial, release_id: 'fixture-1', sha256: 'a'.repeat(64) },
      fleet_state: this.data.updated ? 'current_with_approved_overlays' : 'current',
      drifted_required: this.data.updated ? ['mrn-test'] : [], unknown_drifted_required: [], stale_approved_overlays: [],
      approved_overlays: this.data.updated ? [{ component_slug: 'mrn-test', baseline: { release_id: 'fixture-1', lock_sha256: 'a'.repeat(64) }, version: forward.version,
        package_sha256: forward.package.sha256, tree_sha256: forward.tree.sha256, file_count: forward.tree.file_count }] : [],
      components: this.data.absent ? [] : [{ slug: 'mrn-test', version: current.version, loaded: true, runtime_type: 'standard-plugin',
        path: 'plugins/mrn-test', sha256: current.tree.sha256, file_count: current.tree.file_count,
        hash_algorithm: 'sha256-tree-v1', matches_release: !this.data.updated }],
    } });
    if (name === toolNames.preflight) {
      const safeArtifact = value => { const { package_base64: _package, ...rest } = value; return rest; };
      return response({ ready: true, operation: a.operation, operation_type: 'selective-stack-plugin', plan_id: a.plan_id,
        site_id: a.site_id, site_url: a.site_url, plugin_slug: a.plugin_slug, baseline: a.baseline,
        precondition_hash: this.data.precondition || 'a'.repeat(64),
        installed: { installed: true, active: true, version: current.version }, target: safeArtifact(a.target),
        backup_readiness: { ready: true }, rollback_readiness: { ...safeArtifact(a.rollback), ready: true }, blockers: [] });
    }
    if (name === toolNames.backup) { this.data.backups++; this.persist(); return response({ site_id: this.data.id, nonce: 'abc123' }); }
    if (name === toolNames.backupStatus) return response({ status: this.data.backupFailed ? 'failed' : 'complete', receipt: 'fixture-private-backup-receipt' });
    if ([toolNames.update, toolNames.rollback].includes(name)) {
      if (this.data.safeMode) return { isError: true, ...response({ error: 'SAFE_MODE_BLOCKED' }) };
      if (a.user_confirmed !== true) return response({ status: 'CONFIRMATION_REQUIRED', confirmation_token: 'fixture-private-confirmation' });
      if (this.data.delay) await new Promise(resolve => setTimeout(resolve, this.data.delay));
      this.data.updated = name === toolNames.update; this.data.mutations++; this.persist();
      if (this.data.loseResponse) throw new Error('Timed out after committing write; secret-sentinel');
      const selected = this.data.updated ? forward : old;
      return response({ success: true, operation: this.data.updated ? 'update' : 'rollback', site_id: this.data.id, site_url: this.data.url,
        plan_id: a.plan_id, plugin_slug: a.plugin_slug, baseline: a.baseline, from_version: current.version, to_version: selected.version,
        active: true, package_sha256: selected.package.sha256, tree_sha256: this.data.badVerification ? 'f'.repeat(64) : selected.tree.sha256, file_count: selected.tree.file_count,
        baseline_component_match: false, matches_component_plan: true, approved_overlay_recorded: true,
        fleet_state: 'current_with_approved_overlays', receipt_consumed: true });
    }
    return response({ items: [], hiddenSecret: 'fixture-private-secret' });
  }
}
if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  const file = process.argv[2]; const state = JSON.parse(readFileSync(file));
  const fixture = new FixtureMainwp(state, () => writeFileSync(file, JSON.stringify(state)));
  const server = new McpServer({ name: 'controlled-mainwp-fixture', version: '1.0.0' });
  server.registerResource('status', 'mainwp://status', {}, () => fixture.readResource());
  for (const name of Object.values(toolNames)) server.registerTool(name, { inputSchema: z.object({}).passthrough() }, args => fixture.callTool({ name, arguments: args }));
  await server.connect(new StdioServerTransport());
}
