import { mkdtempSync, writeFileSync, rmSync, readFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { Registry, digest } from '../src/contracts.mjs';
import { Store } from '../src/store.mjs';
import { Operations } from '../src/service.mjs';
import { FleetAdapter } from '../src/fleet.mjs';
import { FixtureMainwp } from './fixtures/mainwp.mjs';
import { Qualifications } from '../src/qualification.mjs';
import { RecoveryEvidence } from '../src/recovery.mjs';
import { responseEvidence } from '../src/probe.mjs';

export const actor = { subject: 'team-member', expiresAt: Date.now() + 3600000 };
export function setup() {
  const root = mkdtempSync(join(tmpdir(), 'mrn-ops-'));
  const registryData = { version: 1, websites: [{ id: 'example', name: 'Example', aliases: ['Client'], facts: [], environments: [{ name: 'development',
    url: 'https://example.test', management: 'mainwp', backup: 'updraft',
    coordination: { exclusiveWriter: 'mrn-operations', evidenceRef: 'fixture-controlled-writers', validUntil: new Date(Date.now() + 3600000).toISOString() } }] }] };
  const policy = { version: 1, members: [{ subject: actor.subject, enabled: true, grants: [{ website: 'example', environments: ['development', 'production'], actions: ['read', 'test', 'repair', 'deploy_development', 'release_production'] }] },
    { subject: 'reader', enabled: true, grants: [{ website: 'example', environments: ['development'], actions: ['read'] }] }] };
  const registry = new Registry(() => registryData, () => policy);
  const store = new Store(join(root, 'state/operations.sqlite'));
  const makeArtifact = (v, char) => {
    const file = join(root, `mrn-test-${v}.zip`); writeFileSync(file, `controlled fixture artifact ${v}`);
    return { version: v, source: { git_commit: char.repeat(40), repository: 'fixture/plugin' },
      package: { path: file, filename: `mrn-test-${v}.zip`, main_file: 'mrn-test/mrn-test.php', size_bytes: readFileSync(file).length, sha256: digest(readFileSync(file)) },
      tree: { hash_algorithm: 'sha256-tree-v1', sha256: char.repeat(64), file_count: 3 } };
  };
  const artifacts = { target: makeArtifact('1.1.0', 'c'), rollback: makeArtifact('1.0.0', 'b') };
  const state = { id: 1, url: 'https://example.test', calls: [], updated: false, mutations: 0, backups: 0, artifacts };
  const client = new FixtureMainwp(state);
  const catalog = { components: [{ slug: 'mrn-test', version: '1.1.0', runtime_type: 'standard-plugin', target_tier: 'platform-required', current_distribution: 'standard-bootstrap' }] };
  const releases = { releases: Object.values(artifacts).map(a => ({ slug: 'mrn-test', ...a })) };
  const publicProbe = async (url, authorize, options) => {
    authorize();
    const body = url.endsWith('/wp-json/') ? '{"namespaces":["wp/v2"],"routes":{"/wp/v2":{}}}'
      : '<html lang="en"><head><title>Example</title></head><body><form></form></body></html>';
    return responseEvidence(url, 200, Buffer.from(body), { ttfbMs: 100, totalMs: 120 }, options);
  };
  const qualificationData = { version: 1, records: Object.values(artifacts).map(a => ({ siteUrl: state.url, environment: 'development', sourceCommit: a.source.git_commit,
    artifactSha256: a.package.sha256, verifiedBy: 'fixture-test-runner', verifiedAt: new Date().toISOString(), validUntil: new Date(Date.now() + 3600000).toISOString(),
    sourceQa: { status: 'passed', reportRef: 'fixture/source', reportSha256: 'a'.repeat(64) }, runtimeQa: { status: 'passed', reportRef: 'fixture/runtime', reportSha256: 'a'.repeat(64) }, frontend: 'none', assets: [] })) };
  const qualifications = new Qualifications(() => qualificationData);
  const fleet = new FleetAdapter({ root, stateDir: root, artifactRoots: [root], publicProbe, qualifications, dependencies: {
    catalog, registry: releases,
    runPlanBuilder: ({ planPath, planId }) => {
      const plan = { plan_id: planId, site: { site_id: 1, site_url: state.url }, baseline: { release_id: 'fixture-1', lock_sha256: 'a'.repeat(64) },
        plugin: { slug: 'mrn-test', main_file: 'mrn-test/mrn-test.php', ...artifacts } };
      writeFileSync(planPath, JSON.stringify(plan)); return plan;
    },
  } });
  const service = new Operations({ registry, store, fleet, publicProbe, writesEnabled: true, connect: async () => client });
  return { root, registryData, registry, policy, store, state, client, service, fleet, artifacts, publicProbe, qualificationData,
    close() { store.close(); rmSync(root, { recursive: true, force: true }); } };
}
export async function prepared(f) {
  const inspection = await f.service.inspect(actor, { website: 'example', environment: 'development' });
  const finding = inspection.findings.find(r => r.code === 'STACK_PLUGIN_BEHIND');
  const op = await f.service.prepare(actor, { inspectionId: inspection.id, findingId: finding.id, requestKey: `prepare-${inspection.id}` });
  return { inspection, finding, op };
}
export async function execute(f, op) {
  await f.service.approve(actor, { operationId: op.id, planDigest: op.planDigest });
  await f.service.execute(actor, { operationId: op.id });
  await f.service.jobs.get(op.id);
  return f.service.get(actor, op.id);
}
export function enrollRecovery(f, operationId) {
  const op = f.store.get(operationId);
  const proof = { operationId, operationDigest: digest(op), siteUrl: op.target.url, environment: op.target.environment,
    verifiedBy: 'fixture-recovery-operator', verifiedAt: new Date().toISOString(), validUntil: new Date(Date.now() + 600000).toISOString(),
    workersStopped: { reportRef: 'fixture/stopped-workers', reportSha256: 'a'.repeat(64) },
    downstreamIdle: { reportRef: 'fixture/idle-mainwp', reportSha256: 'b'.repeat(64) },
    independentWritersExcluded: { reportRef: 'fixture/writer-exclusion', reportSha256: 'c'.repeat(64) } };
  const data = { version: 1, records: [proof] };
  f.service.recoveryEvidence = new RecoveryEvidence(() => data);
  return data;
}
