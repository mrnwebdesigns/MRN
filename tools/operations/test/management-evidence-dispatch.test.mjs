import test from 'node:test';
import assert from 'node:assert/strict';
import { digest } from '../src/contracts.mjs';
import { delegationCanonical, delegationDigest } from '../src/delegation.mjs';
import { sourceFile } from '../src/github.mjs';
import { ManagementEvidenceDispatcher } from '../src/management-evidence-dispatch.mjs';
import { managementEvidenceFixture } from './fixtures/management-evidence.mjs';

function fixture(t) {
  const f = managementEvidenceFixture(t), content = 'name: MRN management fixture\non: workflow_dispatch\n';
  f.policy.workflow_sha256 = digest(content); f.recordCapture();
  const calls = [], state = f.state, repo = f.policy.repository, commit = f.policy.runner_source_sha;
  const root = 'a'.repeat(40), githubTree = 'c'.repeat(40), workflowTree = 'd'.repeat(40), blob = 'e'.repeat(40);
  const rows = {
    '/git/ref/heads/main': { ref: 'refs/heads/main', object: { type: 'commit', sha: commit } },
    '/actions/workflows/20': { id: 20, path: f.policy.workflow_path, state: 'active' },
    [`/git/commits/${commit}`]: { sha: commit, tree: { sha: root } },
    [`/git/trees/${root}`]: { sha: root, truncated: false, tree: [{ path: '.github', type: 'tree', mode: '040000', sha: githubTree }] },
    [`/git/trees/${githubTree}`]: { sha: githubTree, truncated: false, tree: [{ path: 'workflows', type: 'tree', mode: '040000', sha: workflowTree }] },
    [`/git/trees/${workflowTree}`]: { sha: workflowTree, truncated: false, tree: [{ path: 'management.yml', type: 'blob', mode: '100644', sha: blob }] },
    [`/git/blobs/${blob}`]: { sha: blob, encoding: 'base64', size: Buffer.byteLength(content), content: Buffer.from(content).toString('base64') },
  };
  const response = { workflow_run_id: 30, run_url: `https://api.github.com/repos/${repo}/actions/runs/30`, html_url: `https://github.com/${repo}/actions/runs/30` };
  const run = { id: 30, repository: { id: 10, full_name: repo }, head_repository: { id: 10, full_name: repo },
    workflow_id: 20, path: f.policy.workflow_path, head_sha: commit, head_branch: 'main', event: 'workflow_dispatch', run_attempt: 1,
    actor: { id: 40 }, triggering_actor: { id: 40 }, created_at: new Date(state.now).toISOString().replace('.000Z', 'Z'),
    status: 'queued', conclusion: null };
  const github = { repositories: { [repo]: 10 }, scoped: async (repository, purpose, authorize) => {
    assert.equal(repository, repo); assert.equal(purpose, 'qa'); calls.push({ path: 'grant' }); await authorize();
    return async (path, method = 'GET', body) => {
      await authorize(); calls.push({ path, method, body }); await state.duringCall?.(path, method);
      if (method === 'POST') {
        assert.equal(path, '/actions/workflows/20/dispatches'); assert.equal(body.ref, 'main');
        const request = JSON.parse(Buffer.from(body.inputs.request, 'base64'));
        assert.equal(Buffer.from(body.inputs.request, 'base64').toString(), delegationCanonical(request));
        assert.equal(request.dispatch_id, body.inputs.dispatch_id); assert.equal(request.context_sha256, delegationDigest(f.ledger.captured(f.job).context));
        run.display_title = `MRN Management QA ${request.dispatch_id}`; await state.afterPost?.(); return response;
      }
      if (path === '/actions/runs/30') return run;
      assert.ok(Object.hasOwn(rows, path), `Unexpected fixture route: ${path}`); return rows[path];
    };
  } };
  const dispatcher = new ManagementEvidenceDispatcher({ github, ledger: f.ledger,
    verifyStaff: async actor => { assert.equal(actor.googleSubject, f.actor.googleSubject); assert.equal(actor.expiresAt, f.actor.expiresAt); await state.duringStaff?.(); },
    now: () => state.now, monotonic: () => state.mono });
  return { ...f, calls, rows, run, response, content, root, githubTree, workflowTree, blob, github, dispatcher,
    execute: () => dispatcher.dispatch(f.job), posts: () => calls.filter(c => c.method === 'POST') };
}

test('protected management dispatcher verifies immutable workflow bytes then records the exact created run once', async t => {
  const f = fixture(t), result = await f.execute();
  assert.equal(result.provenance.run_id, '30'); assert.equal(result.provenance.workflow_sha256, digest(f.content));
  assert.equal(result.url, f.response.html_url); assert.equal(f.posts().length, 1);
  const request = JSON.parse(Buffer.from(f.posts()[0].body.inputs.request, 'base64'));
  assert.equal(result.provenance.dispatch_request_sha256, delegationDigest(request));
  assert.equal(f.ledger.dispatch(f.job).provenance.dispatch_record_sha256, result.provenance.dispatch_record_sha256);
  assert.equal(f.calls.filter(c => c.path === '/git/ref/heads/main').length, 2);
  assert.deepEqual(Object.keys(request), ['context_sha256', 'dispatch_id', 'schema']);
  await assert.rejects(f.execute); assert.equal(f.posts().length, 1);
});

test('source, workflow, symlink and truncated-tree failures stop before preparing or posting a run', async t => {
  for (const mutate of [f => { f.rows['/git/ref/heads/main'].object.sha = 'f'.repeat(40); },
    f => { f.github.repositories[f.policy.repository] = 11; },
    f => { f.rows['/actions/workflows/20'].state = 'disabled_manually'; },
    f => { f.rows['/actions/workflows/20'].path = '.github/workflows/other.yml'; },
    f => { f.rows[`/git/trees/${f.root}`].tree[0].mode = '120000'; },
    f => { f.rows[`/git/trees/${f.workflowTree}`].truncated = true; },
    f => { f.rows[`/git/trees/${f.workflowTree}`].tree[0].mode = '100755'; },
    f => { f.rows[`/git/blobs/${f.blob}`].content = Buffer.from('different source').toString('base64'); },
    f => { f.state.duringStaff = () => { throw new Error('staff suspended'); }; }]) {
    const f = fixture(t); mutate(f); await assert.rejects(f.execute); assert.equal(f.posts().length, 0);
    assert.equal(f.store.db.prepare('SELECT count(*) AS n FROM management_evidence_dispatches').get().n, 0);
  }
  let reads = 0; await assert.rejects(() => sourceFile(() => { reads++; }, 'a'.repeat(40), '.github/workflows/management.yml'));
  assert.equal(reads, 0); // Read-only workflow preflight does not widen site editing.
});

test('uncertain dispatch, malformed returned identity and mismatched observed run can never redispatch', async t => {
  for (const mutate of [f => { f.state.afterPost = () => { throw new Error('lost original response'); }; },
    f => { f.response.workflow_run_id = Number.MAX_SAFE_INTEGER + 1; },
    f => { f.response.run_url = 'https://untrusted.test/run'; },
    f => { f.run.repository.id++; }, f => { f.run.head_repository.full_name = 'outsider/fork'; },
    f => { f.run.workflow_id++; }, f => { f.run.actor.id++; }, f => { f.run.triggering_actor.id++; },
    f => { f.run.head_sha = 'f'.repeat(40); }, f => { f.run.run_attempt++; },
    f => { f.run.event = 'push'; }, f => { f.run.head_branch = 'candidate'; },
    f => { f.run.created_at = '2026-02-31T12:00:00Z'; },
    f => { f.state.afterPost = () => { f.run.display_title = 'wrong original request'; }; },
    f => { f.run.status = 'completed'; f.run.conclusion = 'failure'; }]) {
    const f = fixture(t); mutate(f); await assert.rejects(f.execute); assert.equal(f.posts().length, 1);
    assert.equal(f.store.db.prepare('SELECT run FROM management_evidence_dispatches').get().run, null);
    await assert.rejects(f.execute); assert.equal(f.posts().length, 1);
  }
});

test('revocation, policy change and original expiry after dispatch withhold the usable mapping', async t => {
  for (const mutate of [f => f.access.recordPendingRevocation(f.job),
    f => { f.policy.policy_epoch++; }, f => { f.state.now += 60000; },
    f => { f.state.mono = 60000; }, f => { f.state.now--; },
    f => { f.state.duringStaff = () => { throw new Error('revoked'); }; }]) {
    const f = fixture(t); f.state.afterPost = () => mutate(f); await assert.rejects(f.execute);
    assert.equal(f.posts().length, 1); assert.equal(f.store.db.prepare('SELECT run FROM management_evidence_dispatches').get().run, null);
  }
});

test('hanging response exhausts one original dispatch budget and late data cannot create a mapping', async t => {
  const f = fixture(t); let release;
  f.state.duringCall = (path, method) => { if (path === '/git/ref/heads/main') f.state.mono = 59900;
    if (method === 'POST') return new Promise(resolve => { release = resolve; }); };
  await assert.rejects(f.execute, { code: 'MANAGEMENT_EVIDENCE_DISPATCH' });
  assert.equal(f.posts().length, 1); assert.equal(f.store.db.prepare('SELECT run FROM management_evidence_dispatches').get().run, null);
  release(); await new Promise(resolve => globalThis.setImmediate(resolve));
  assert.equal(f.store.db.prepare('SELECT run FROM management_evidence_dispatches').get().run, null);
});
