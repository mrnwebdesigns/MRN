import test from 'node:test';
import assert from 'node:assert/strict';
import { digest } from '../src/contracts.mjs';
import { downloadManagementEvidence } from '../src/management-evidence-download.mjs';

function fixture() {
  const now = 1800000000000, stamp = offset => new Date(now + offset).toISOString();
  const record = { repository: 'mrnwebdesigns/mrn-code-qa-runner', repositoryId: '10', workflowId: '20', runId: '30',
    appActorId: '40', workflowPath: '.github/workflows/management.yml', runnerCommit: 'a'.repeat(40),
    requestedAt: now - 60000, originalExpiresAt: now + 60000 };
  const run = { id: 30, repository: { id: 10, full_name: record.repository }, head_repository: { id: 10, full_name: record.repository },
    workflow_id: 20, path: record.workflowPath, head_sha: record.runnerCommit, head_branch: 'main', event: 'workflow_dispatch',
    run_attempt: 1, status: 'completed', conclusion: 'success', actor: { id: 40 }, triggering_actor: { id: 40 },
    created_at: stamp(-50000), updated_at: stamp(-10000) };
  const archives = { qualification: Buffer.from('original qualification ZIP fixture'), acquisition: Buffer.from('original companion ZIP fixture') };
  const artifacts = Object.entries(archives).map(([role, bytes], index) => ({ id: 50 + index,
    name: role === 'qualification' ? 'management-qualification-evidence-v1' : 'management-acquisition-evidence-v1',
    size_in_bytes: bytes.length, digest: `sha256:${digest(bytes)}`, expired: false,
    created_at: stamp(-20000), expires_at: stamp(3600000), workflow_run: { id: 30, repository_id: 10, head_sha: record.runnerCommit, head_branch: 'main' } }));
  const inventory = { total_count: 2, artifacts }, calls = [], downloaded = {}, timing = { startedAt: now - 1000, completedAt: now };
  let authorized = 0;
  const authorize = async () => { authorized++; };
  const github = { managementEvidence: async (repository, check, options) => {
    assert.equal(repository, record.repository); assert.equal(check, authorize);
    assert.deepEqual(options, { expiresAt: record.originalExpiresAt });
    return {
      readRun: async id => { calls.push('run'); assert.equal(id, record.runId); return { value: run, sha256: digest(JSON.stringify(run)) }; },
      readArtifacts: async id => { calls.push('inventory'); assert.equal(id, record.runId); return { value: inventory, sha256: digest(JSON.stringify(inventory)) }; },
      downloadArtifact: async (id, role) => { calls.push(role); assert.equal(id, role === 'qualification' ? '50' : '51');
        return { bytes: archives[role], sha256: digest(archives[role]), downloadedAt: now, ...downloaded[role] }; },
      finish: async () => { calls.push('finish'); await check(); return timing; },
    };
  } };
  return { now, stamp, record, run, archives, inventory, calls, downloaded, timing,
    get authorized() { return authorized; }, acquire: () => downloadManagementEvidence({ github, record, authorize, now: () => now }) };
}

test('closed evidence observations bind original service dispatch, complete inventory and actual archive bytes', async () => {
  const f = fixture(), result = await f.acquire();
  assert.equal(result.observed_run.run_id, '30'); assert.equal(result.observed_run.repository_id, '10');
  assert.equal(result.observed_run.metadata_response_sha256, digest(JSON.stringify(f.run)));
  for (const role of ['qualification', 'acquisition']) {
    assert.deepEqual(result[`${role}_zip`], f.archives[role]);
    assert.equal(result[`${role}_artifact`].reported_digest, `sha256:${digest(f.archives[role])}`);
    assert.deepEqual(result[`${role}_artifact`].workflow_run, { repository_id: '10', run_id: '30', head_sha: f.record.runnerCommit });
    assert.equal(result[`${role}_artifact`].expired, false);
  }
  assert.deepEqual(f.calls, ['run', 'inventory', 'qualification', 'acquisition', 'finish']); assert.equal(f.authorized, 1);
});

test('foreign or stale dispatch metadata refuses before any artifact download', async () => {
  for (const change of [f => { f.run.id = 31; }, f => { f.run.id = Number.MAX_SAFE_INTEGER + 1; },
    f => { f.run.repository.id = 11; }, f => { f.run.head_repository.full_name = 'outsider/fork'; },
    f => { f.run.workflow_id = 21; }, f => { f.run.path = '.github/workflows/other.yml'; },
    f => { f.run.head_sha = 'b'.repeat(40); }, f => { f.run.head_branch = 'candidate'; },
    f => { f.run.event = 'push'; }, f => { f.run.run_attempt = 2; }, f => { f.run.conclusion = 'failure'; },
    f => { f.run.status = 'in_progress'; }, f => { f.run.actor.id = 41; }, f => { f.run.triggering_actor.id = 41; },
    f => { f.run.created_at = f.stamp(-61000); }, f => { f.run.updated_at = f.stamp(1000); },
    f => { f.run.updated_at = '2026-02-31T12:00:00Z'; }, f => { f.record.originalExpiresAt = f.now; },
    f => { f.record.callerUrl = 'https://untrusted.test'; }]) {
    const f = fixture(); change(f);
    await assert.rejects(f.acquire, { code: 'MANAGEMENT_EVIDENCE_BINDING' });
    assert.equal(f.calls.some(x => ['qualification', 'acquisition'].includes(x)), false);
  }
});

test('entire artifact inventory must match before either archive is fetched', async () => {
  for (const change of [f => { f.inventory.total_count = 3; }, f => { f.inventory.artifacts.pop(); },
    f => { f.inventory.artifacts[1].name = f.inventory.artifacts[0].name; },
    f => { f.inventory.artifacts[1].id = 50; }, f => { f.inventory.artifacts[1].expired = true; },
    f => { f.inventory.artifacts[1].workflow_run.id = 31; }, f => { f.inventory.artifacts[1].workflow_run.repository_id = 11; },
    f => { f.inventory.artifacts[1].workflow_run.head_sha = 'b'.repeat(40); }, f => { f.inventory.artifacts[1].workflow_run.head_branch = 'other'; },
    f => { f.inventory.artifacts[1].digest = null; }, f => { f.inventory.artifacts[1].size_in_bytes = 196609; },
    f => { f.inventory.artifacts[0].size_in_bytes = 262145; }, f => { f.inventory.artifacts[1].expires_at = f.stamp(0); },
    f => { f.inventory.artifacts[1].created_at = f.stamp(-60000); }]) {
    const f = fixture(); change(f); await assert.rejects(f.acquire, { code: 'MANAGEMENT_EVIDENCE_BINDING' });
    assert.deepEqual(f.calls, ['run', 'inventory']);
  }
});

test('reported digest, raw bytes, download time and original lifetime must agree without substituting archive contents', async () => {
  for (const change of [f => { f.inventory.artifacts[0].digest = `sha256:${'b'.repeat(64)}`; },
    f => { f.downloaded.qualification = { bytes: Buffer.from('different') }; },
    f => { f.downloaded.acquisition = { sha256: 'b'.repeat(64) }; },
    f => { f.inventory.artifacts[0].size_in_bytes++; },
    f => { f.downloaded.acquisition = { downloadedAt: f.now + 1 }; },
    f => { f.downloaded.acquisition = { downloadedAt: f.record.requestedAt - 1 }; },
    f => { f.timing.startedAt = f.now - 60000; }, f => { f.timing.completedAt = f.now + 1; }]) {
    const f = fixture(); change(f); await assert.rejects(f.acquire, { code: 'MANAGEMENT_EVIDENCE_BINDING' });
    assert.equal(f.calls.filter(x => x === 'qualification').length, 1);
  }
});
