import { writeFileSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { execFileSync } from 'node:child_process';
import { setTimeout as delay } from 'node:timers/promises';
import {
  runFleetUpdate, buildAbilityInputFromPlan, buildWriteInput, verifyRegisteredArtifact,
} from '../../../stack/scripts/mrn-fleet-update.mjs';
import { digest, readJson, requireThat } from './contracts.mjs';
import { payload, toolNames } from './mainwp.mjs';

export function binding(input) {
  const artifact = a => ({ version: a.version, filename: a.filename, package_sha256: a.package_sha256,
    tree_sha256: a.tree_sha256, file_count: a.file_count });
  return { site_id: input.site_id, site_url: input.site_url, plugin_slug: input.plugin_slug,
    baseline: input.baseline, target: artifact(input.target), rollback: artifact(input.rollback) };
}
function validatePackages(input) {
  for (const a of [input.target, input.rollback]) requireThat(digest(Buffer.from(a.package_base64, 'base64')) === a.package_sha256, 'ARTIFACT_CHANGED', 'An approved release package has changed.');
}
export class FleetAdapter {
  constructor({ root, stateDir, artifactRoots, sourceRepositories = {}, dependencies = {}, publicProbe, qualifications }) {
    Object.assign(this, { root, stateDir, artifactRoots, sourceRepositories, dependencies, publicProbe, qualifications });
  }
  catalog() { return this.dependencies.catalog || readJson(join(this.root, 'stack/manifests/component-catalog.json')); }
  releases() {
    if (this.dependencies.registry) return this.dependencies.registry;
    const registry = readJson(join(this.root, 'stack/manifests/stack-plugin-releases.json'));
    return { ...registry, releases: registry.releases.map(r => ({ ...r, source: { ...r.source,
      path: this.sourceRepositories[r.source.repository] || '/unconfigured-mrn-source-repository' } })) };
  }
  buildPlan = args => {
    const releases = this.releases();
    const entries = releases.releases.filter(r => r.slug === args.component);
    requireThat(entries.length > 0 && entries.every(r => r.source.path !== '/unconfigured-mrn-source-repository'), 'SOURCE_UNPROVISIONED', 'Provision the registered component source repository on the service host.');
    const registryFile = join(dirname(args.planPath), 'host-release-registry.json');
    writeFileSync(registryFile, JSON.stringify(releases), { mode: 0o600 });
    // Relocate only checkout paths; the canonical builder still validates clean
    // merged commits, package bytes, tree hashes, baseline and rollback source.
    execFileSync('python3', [join(this.root, 'stack/scripts/build-mainwp-stack-plugin-plan.py'),
      '--catalog', join(this.root, 'stack/manifests/component-catalog.json'), '--release-registry', registryFile,
      '--release-lock-dir', join(this.root, 'stack/manifests/release-locks'),
      '--inventory', args.inventoryPath, '--plugin-slug', args.component, '--plan-id', args.planId,
      '--target-artifact', args.targetPath, '--rollback-artifact', args.rollbackPath, '--as-of', args.asOf, '--output', args.planPath],
    { cwd: this.root, timeout: 120000, maxBuffer: 1024 * 1024, stdio: ['ignore', 'pipe', 'pipe'],
      env: { PATH: process.env.PATH, HOME: process.env.HOME } });
    return readJson(args.planPath);
  };
  sourceDigest() { return digest({ catalog: this.catalog(), releases: this.releases() }); }
  options(target, component, dir, execute = false, approve = '') {
    return { site: target.url, component, outputDir: dir, execute, approve, confirmSite: execute ? target.url : '',
      smokePaths: [], configPath: '', backupTimeoutSeconds: 600, json: true };
  }
  async smoke(target, session, qualification) {
    const result = await this.publicProbe(target.url, () => session.authorize(), { references: qualification.assets.map(a => a.url) });
    const api = await this.publicProbe(`${target.url}/wp-json/`, () => session.authorize());
    requireThat(result.status === 200 && api.status === 200 && api.restHealthy === true, 'PUBLIC_VERIFICATION', 'The public website and WordPress REST root must return healthy responses after the operation.');
    const checks = [{ path: '/', status: result.status, ok: true, measuredAt: result.measuredAt },
      { path: '/wp-json/', status: api.status, restHealthy: true, ok: true, measuredAt: api.measuredAt }];
    for (const asset of qualification.assets) {
      requireThat(result.references?.some(r => r.url === asset.url && r.present === true), 'STALE_HTML', 'The public page does not reference the approved immutable asset URL.');
      const served = await this.publicProbe(asset.url, () => session.authorize());
      requireThat(served.status === 200 && served.contentSha256 === asset.sha256, 'STALE_ASSET', 'The publicly served asset does not match the approved checksum.');
      checks.push({ path: new URL(asset.url).pathname, url: asset.url, status: served.status, ok: true,
        referencedByPage: true, contentSha256: served.contentSha256, measuredAt: served.measuredAt });
    }
    return checks;
  }
  async plan(target, finding, session, operation) {
    requireThat(target.management === 'mainwp' && target.backup === 'updraft', 'ROUTE_UNAVAILABLE', 'This Fleet repair requires a MainWP-managed site with the approved Updraft backup path. Provider-native backup sites need their qualified adapter.');
    const dir = join(this.stateDir, operation, 'plan');
    const summary = await runFleetUpdate(this.options(target, finding.component, dir), {
      ...this.dependencies, client: session, catalog: this.catalog(), registry: this.releases(), artifactRoots: this.artifactRoots,
      runPlanBuilder: this.dependencies.runPlanBuilder || this.buildPlan,
    });
    requireThat(summary.status === 'approval_required', 'FINDING_RESOLVED', 'This installed component no longer needs the proposed repair.');
    const fleetPlan = readJson(join(dir, 'plan.json'));
    const input = buildAbilityInputFromPlan(fleetPlan); validatePackages(input);
    for (const item of [fleetPlan.plugin.target, fleetPlan.plugin.rollback]) requireThat(/^[a-f0-9]{40}$/.test(item.source?.git_commit || ''), 'SOURCE_UNPROVEN', 'Each artifact needs a verified immutable source commit.');
    return { kind: 'fleet_plugin', component: finding.component, direction: 'update',
      precondition: summary.precondition_hash, sourceDigest: this.sourceDigest(), binding: binding(input), fleetPlan,
      description: `Update ${finding.component} from ${summary.current_version} to ${summary.target_version}.`,
      sourceCommit: fleetPlan.plugin.target.source.git_commit,
      artifactSha256: input.target.package_sha256,
      recovery: { kind: 'code', version: input.rollback.version, sourceCommit: fleetPlan.plugin.rollback.source.git_commit,
        artifactSha256: input.rollback.package_sha256, database: 'Separate provider-approved recovery; code rollback does not restore data or media.' } };
  }
  assertPlan(plan) {
    requireThat(plan.sourceDigest === this.sourceDigest(), 'SOURCE_CHANGED', 'The approved release registry changed. Prepare a new plan.');
    const input = buildAbilityInputFromPlan(plan.fleetPlan); validatePackages(input);
    requireThat(digest(binding(input)) === digest(plan.binding), 'ARTIFACT_CHANGED', 'The approved artifact bindings changed.');
    // Re-check registered bytes (including the retained rollback package).
    for (const version of [input.target.version, input.rollback.version]) {
      const entries = this.releases().releases.filter(r => r.slug === plan.component && r.version === version);
      requireThat(entries.length === 1, 'SOURCE_CHANGED', 'No unique registered artifact remains.');
      verifyRegisteredArtifact(entries[0], this.artifactRoots);
    }
    return input;
  }
  async execute(op, session) {
    const plan = op.plan; this.assertPlan(plan);
    requireThat(this.qualifications, 'QA_REQUIRED', 'Configure exact artifact QA qualification evidence before executing.');
    const qualification = this.qualifications.find(op.target, plan);
    if (plan.direction === 'rollback') return this.executeRollback(op, session);
    const client = {
      readResource: request => session.readResource(request), listTools: () => session.listTools(),
      callTool: (request, schema, options) => {
        if ([toolNames.preflight, toolNames.update].includes(request.name)) {
          validatePackages(request.arguments);
          requireThat(digest(binding(request.arguments)) === digest(plan.binding), 'PLAN_CHANGED', 'Fresh workflow inputs differ from the approved site, baseline or artifacts.');
        }
        return session.callTool(request, schema, options);
      },
    };
    const result = await runFleetUpdate(this.options(op.target, plan.component, join(this.stateDir, op.id, 'execution'), true, plan.precondition), {
      ...this.dependencies, client, catalog: this.catalog(), registry: this.releases(), artifactRoots: this.artifactRoots,
      runPlanBuilder: this.dependencies.runPlanBuilder || this.buildPlan,
      smokeCheck: () => this.smoke(op.target, session, qualification),
    });
    requireThat(result.status === 'verified' && result.backup_verified === true && result.runtime_verified === true && result.smoke_verified === true, 'VERIFICATION_FAILED', 'Execution did not prove the approved runtime and public result.');
    return { backup: 'verified', runtime: 'verified', public: 'verified', version: result.to_version, artifactSha256: plan.artifactSha256,
      qualification: { verifiedBy: qualification.verifiedBy, sourceQa: qualification.sourceQa, runtimeQa: qualification.runtimeQa } };
  }
  async reconcile(op, session) {
    const { plan, target } = op;
    requireThat(plan.kind === 'fleet_plugin' && ['update', 'rollback'].includes(plan.direction), 'RECOVERY_UNAVAILABLE', 'Only the installed-plugin Fleet workflow has a qualified reconciliation adapter.');
    requireThat(this.qualifications, 'QA_REQUIRED', 'Exact artifact QA qualification is required for reconciliation.');
    const expected = plan.binding;
    const readback = async () => {
      const inventory = await session.fresh();
      requireThat(inventory.id === expected.site_id && inventory.url === expected.site_url, 'TARGET_MISMATCH', 'The recovered MainWP identity differs from the approved operation.');
      const runtime = await session.runtime();
      requireThat(['current', 'current_with_approved_overlays'].includes(runtime?.fleet_state)
        && Array.isArray(runtime.unknown_drifted_required) && runtime.unknown_drifted_required.length === 0
        && Array.isArray(runtime.stale_approved_overlays) && runtime.stale_approved_overlays.length === 0,
      'RECOVERY_RUNTIME_UNQUALIFIED', 'Unknown Stack drift or stale overlays require investigation before releasing the lock.');
      return { inventory, runtime };
    };
    const first = await readback();
    const matches = ['target', 'rollback'].filter(key => {
      try { this.verifyComponent(first.runtime, plan.component, expected[key], expected.baseline); return true; }
      catch (error) { if (error.code === 'RUNTIME_MISMATCH') return false; throw error; }
    });
    requireThat(matches.length === 1, 'RECOVERY_RUNTIME_MISMATCH', 'Fresh runtime must match exactly the approved code or its retained prior release. Unknown or mixed code keeps the website locked.');
    const key = matches[0]; const artifact = expected[key];
    const observedPlan = { sourceCommit: plan.fleetPlan.plugin[key].source.git_commit, artifactSha256: artifact.package_sha256 };
    const qualification = this.qualifications.find(target, observedPlan);
    const publicChecks = await this.smoke(target, session, qualification);
    // An observed match before public probes alone cannot close the operation.
    const last = await readback();
    this.verifyComponent(last.runtime, plan.component, artifact, expected.baseline);
    requireThat(digest(this.qualifications.find(target, observedPlan)) === digest(qualification), 'QA_CHANGED', 'Artifact qualification changed during recovery.');
    const intended = plan.direction === 'update' ? 'target' : 'rollback';
    return { outcome: key === intended ? 'intended_code_verified' : 'prior_code_verified',
      runtime: 'verified', public: 'verified', backup: 'not_reverified', databaseAndMedia: 'not_assessed',
      version: artifact.version, sourceCommit: observedPlan.sourceCommit, artifactSha256: artifact.package_sha256,
      treeSha256: artifact.tree_sha256, fileCount: artifact.file_count, inventory: last.inventory, publicChecks,
      qualification: { verifiedBy: qualification.verifiedBy, sourceQa: qualification.sourceQa, runtimeQa: qualification.runtimeQa } };
  }
  async rollbackPlan(previous, session) {
    const input = this.assertPlan(previous.plan);
    await session.fresh();
    const runtime = await session.runtime();
    this.verifyComponent(runtime, previous.plan.component, input.target, input.baseline);
    const preflightInput = { ...input, operation: 'rollback' };
    const preflight = await session.call(toolNames.preflight, preflightInput);
    this.checkRollbackPreflight(preflight, preflightInput);
    return { ...previous.plan, direction: 'rollback', precondition: preflight.precondition_hash,
      sourceCommit: previous.plan.fleetPlan.plugin.rollback.source.git_commit,
      artifactSha256: input.rollback.package_sha256,
      description: `Restore ${previous.plan.component} to ${input.rollback.version}; code only.`,
      reverses: previous.id };
  }
  checkRollbackPreflight(p, input) {
    requireThat(p.ready === true && p.operation === 'rollback' && p.site_id === input.site_id && p.site_url.replace(/\/$/, '') === input.site_url && p.plan_id === input.plan_id && p.plugin_slug === input.plugin_slug && digest(p.baseline) === digest(input.baseline)
      && p.backup_readiness?.ready === true && p.rollback_readiness?.ready === true && Array.isArray(p.blockers) && p.blockers.length === 0 && /^[a-f0-9]{64}$/.test(p.precondition_hash), 'ROLLBACK_PREFLIGHT', 'The exact rollback is not ready.');
    for (const [actual, expected] of [[p.target, input.target], [p.rollback_readiness, input.rollback]]) {
      requireThat(['version', 'package_sha256', 'tree_sha256', 'file_count'].every(k => actual?.[k] === expected[k]), 'ROLLBACK_PREFLIGHT', 'Rollback artifact evidence does not match the approved packages.');
    }
  }
  verifyComponent(report, component, artifact, baseline) {
    requireThat(report?.release_lock?.valid === true && report.release_lock.release_id === baseline.release_id && report.release_lock.sha256 === baseline.lock_sha256, 'RUNTIME_MISMATCH', 'The runtime baseline changed.');
    const list = report?.components?.filter(c => c.slug === component) || [];
    requireThat(list.length === 1 && list[0].loaded === true && list[0].version === artifact.version && list[0].sha256 === artifact.tree_sha256 && list[0].file_count === artifact.file_count, 'RUNTIME_MISMATCH', 'The fresh installed component differs from the approved release tree.');
  }
  async executeRollback(op, session) {
    const qualification = this.qualifications.find(op.target, op.plan);
    const input = { ...this.assertPlan(op.plan), operation: 'rollback' };
    await session.fresh(); this.verifyComponent(await session.runtime(), op.plan.component, input.target, input.baseline);
    const preflight = await session.call(toolNames.preflight, input); this.checkRollbackPreflight(preflight, input);
    requireThat(preflight.precondition_hash === op.plan.precondition, 'PLAN_CHANGED', 'Rollback preconditions changed after approval.');
    const probe = payload(await session.callTool({ name: toolNames.rollback, arguments: buildWriteInput(input, preflight.precondition_hash,
      { site_id: session.siteId, nonce: '0', receipt: 'confirmation-gate-probe' }) }), { allowError: true });
    requireThat(probe.status === 'CONFIRMATION_REQUIRED' && probe.confirmation_token, 'CONFIRMATION_GATE', 'MainWP rollback confirmation is unavailable or safe mode is enabled; no backup was started.');
    const started = await session.call(toolNames.backup, { site_id: session.siteId });
    requireThat(started.site_id === session.siteId && /^[a-f0-9]+$/i.test(started.nonce || ''), 'BACKUP_FAILED', 'The backup did not return an exact-site nonce.');
    let receipt; const deadline = Date.now() + 600000;
    while (Date.now() < deadline) {
      const status = await session.call(toolNames.backupStatus, { site_id: session.siteId, nonce: started.nonce });
      if (status.status === 'complete' && typeof status.receipt === 'string' && status.receipt) { receipt = status.receipt; break; }
      requireThat(status.status !== 'failed', 'BACKUP_FAILED', 'The required remote database backup failed.');
      await delay(5000);
    }
    requireThat(receipt, 'BACKUP_FAILED', 'The required remote database backup did not complete.');
    const write = buildWriteInput(input, preflight.precondition_hash, { site_id: session.siteId, nonce: started.nonce, receipt });
    const preview = payload(await session.callTool({ name: toolNames.rollback, arguments: write }), { allowError: true });
    requireThat(preview.status === 'CONFIRMATION_REQUIRED' && preview.confirmation_token, 'CONFIRMATION_GATE', 'MainWP did not provide the mandatory rollback confirmation gate.');
    const result = await session.call(toolNames.rollback, { ...write, user_confirmed: true, confirmation_token: preview.confirmation_token });
    requireThat(result.success === true && result.operation === 'rollback' && result.site_id === session.siteId && result.site_url.replace(/\/$/, '') === input.site_url && result.plan_id === input.plan_id && result.plugin_slug === input.plugin_slug && result.to_version === input.rollback.version && result.package_sha256 === input.rollback.package_sha256 && result.tree_sha256 === input.rollback.tree_sha256 && result.file_count === input.rollback.file_count && result.active === true && digest(result.baseline) === digest(input.baseline) && result.receipt_consumed === true, 'ROLLBACK_UNVERIFIED', 'Rollback returned incomplete or mismatched evidence.');
    await session.fresh(); this.verifyComponent(await session.runtime(), op.plan.component, input.rollback, input.baseline);
    await this.smoke(op.target, session, qualification);
    return { backup: 'verified', runtime: 'verified', public: 'verified', version: input.rollback.version, artifactSha256: input.rollback.package_sha256, recovery: 'code_only' };
  }
}
