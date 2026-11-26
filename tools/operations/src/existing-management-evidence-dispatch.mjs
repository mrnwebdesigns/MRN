import { requireThat } from './contracts.mjs';
import { delegationCanonical, delegationDigest } from './delegation.mjs';
import { branchHead, workflowSource } from './github.mjs';
import { existingManagementTypes } from './management-existing-contracts.mjs';
import { OriginalManagementAuthorizationLedger } from './original-management-authorization.mjs';

const check = value => requireThat(value, 'MANAGEMENT_EXISTING_DISPATCH',
  'The protected original dispatch is unavailable, changed or uncertain; do not retry it.');
const id = value => {
  const result = typeof value === 'number' && Number.isSafeInteger(value) && value > 0 ? String(value) : value;
  check(existingManagementTypes.evidence.id.safeParse(result).success); return result;
};

// Private source candidate only. No startup, route, tool, workflow or GitHub
// grant constructs this class. The central QA App must be separately approved;
// source-read CI credentials and staff credentials cannot substitute for it.
export class ExistingManagementEvidenceDispatcher {
  constructor({ github, ledger, now = () => Date.now(), monotonic = () => performance.now() }) {
    check(ledger instanceof OriginalManagementAuthorizationLedger);
    Object.assign(this, { github, ledger, now, monotonic });
  }
  async dispatch(jobId, measurement) {
    const first = this.ledger.originalWorkerHistory(jobId);
    check(first.state === 'unused');
    const configurationHash = delegationDigest(first.configuration), start = this.monotonic(), startedAt = this.now();
    let expired = false, phase = 'unused';
    const within = () => check(!expired && this.monotonic() >= start && this.monotonic() - start < 60000
      && this.now() >= startedAt && this.now() - startedAt < 60000);
    const current = () => {
      within();
      const value = this.ledger.originalWorkerHistory(jobId);
      check(value.state === phase
        && delegationDigest(value.configuration) === configurationHash
        && value.current.original.digest === first.current.original.digest
        && value.current.queue.digest === first.current.queue.digest && value.current.revision === first.current.revision);
      return value;
    };
    const bounded = async perform => {
      const original = current(); let timer;
      try {
        const remaining = Math.min(30000, 60000 - (this.monotonic() - start), 60000 - (this.now() - startedAt),
          original.current.queue.context.binding.expires_at * 1000 - this.now());
        check(remaining > 0);
        const result = await Promise.race([Promise.resolve().then(perform), new Promise((_, reject) => {
          timer = setTimeout(() => { expired = true; try { check(false); } catch (error) { reject(error); } }, remaining);
        })]);
        current(); return result;
      } finally { clearTimeout(timer); }
    };
    const authorize = async () => {
      const value = current();
      await bounded(() => this.ledger.verifyStaff(value.current.original.mapping.actor)); current();
    };
    await bounded(() => this.ledger.readWorkerHistory(jobId));
    const { policy, app_actor_id: appActorId } = first.configuration;
    check(id(this.github.repositories?.[policy.repository]) === policy.repository_id);
    const api = await bounded(() => this.github.scoped(policy.repository, 'qa', authorize));
    const read = async path => { const value = await bounded(() => api(path)); await authorize(); return value; };
    check(await branchHead(read, 'main') === policy.runner_source_sha);
    const workflow = await read(`/actions/workflows/${policy.workflow_id}`);
    check(id(workflow.id) === policy.workflow_id && workflow.path === policy.workflow_path && workflow.state === 'active');
    const source = await workflowSource(read, policy.runner_source_sha, policy.workflow_path);
    check(source.sha256 === policy.workflow_sha256 && source.mode === '100644');
    check(await branchHead(read, 'main') === policy.runner_source_sha);
    await authorize();
    let prepared;
    await bounded(async () => {
      prepared = await this.ledger.prepareWorkerDispatch(jobId, measurement, within);
      phase = 'prepared'; // Committed before the first POST; never reset on error.
    });
    const request = prepared.request, requestedAt = this.now();
    const response = await bounded(() => api(`/actions/workflows/${policy.workflow_id}/dispatches`, 'POST', {
      ref: 'main', inputs: { request: Buffer.from(delegationCanonical(request)).toString('base64'), dispatch_id: request.dispatch_id },
    }));
    await authorize();
    const runId = id(response.workflow_run_id);
    check(response.run_url === `https://api.github.com/repos/${policy.repository}/actions/runs/${runId}`
      && response.html_url === `https://github.com/${policy.repository}/actions/runs/${runId}`);
    const run = await read(`/actions/runs/${runId}`);
    const created = typeof run.created_at === 'string' && /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/.test(run.created_at)
      ? Date.parse(run.created_at) : NaN;
    check(id(run.id) === runId && id(run.repository?.id) === policy.repository_id && run.repository.full_name === policy.repository
      && id(run.head_repository?.id) === policy.repository_id && run.head_repository.full_name === policy.repository
      && id(run.workflow_id) === policy.workflow_id && run.path === policy.workflow_path && run.head_sha === policy.runner_source_sha
      && run.head_branch === 'main' && run.event === 'workflow_dispatch' && run.run_attempt === 1
      && id(run.actor?.id) === appActorId && id(run.triggering_actor?.id) === appActorId
      && run.display_title === `MRN Existing Management QA ${request.dispatch_id}`
      && Number.isSafeInteger(created) && new Date(created).toISOString() === run.created_at.replace('Z', '.000Z')
      && created >= Math.floor(requestedAt / 1000) * 1000 && created <= this.now()
      && (['queued', 'in_progress', 'pending', 'waiting', 'requested'].includes(run.status) ? run.conclusion === null
        : run.status === 'completed' && run.conclusion === 'success'));
    await authorize();
    let retained;
    await bounded(async () => {
      retained = await this.ledger.recordWorkerDispatch(prepared.handle, runId, within); phase = 'observed';
    });
    return { retained, url: response.html_url, source_verified: false, admitted: false };
  }
}
