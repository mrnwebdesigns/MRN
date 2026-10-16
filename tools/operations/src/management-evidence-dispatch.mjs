import { requireThat } from './contracts.mjs';
import { delegationCanonical, delegationDigest } from './delegation.mjs';
import { branchHead, workflowSource } from './github.mjs';
import { managementEvidenceTypes } from './management-evidence-contract.mjs';

const check = (condition, message) => requireThat(condition, 'MANAGEMENT_EVIDENCE_DISPATCH', message);
const id = value => {
  const result = typeof value === 'number' && Number.isSafeInteger(value) && value > 0 ? String(value) : value;
  check(managementEvidenceTypes.id.safeParse(result).success, 'The protected dispatch identifier is invalid or imprecise.'); return result;
};

// Internal candidate only. Neither startup nor public tools construct it, and
// the CI source-read App is never repurposed. Actual activation requires the
// separately approved central Actions grant, worker and original-record reader.
export class ManagementEvidenceDispatcher {
  constructor({ github, ledger, verifyStaff, now = () => Date.now(), monotonic = () => performance.now() }) {
    check(typeof verifyStaff === 'function', 'Fresh original-staff verification is required.');
    Object.assign(this, { github, ledger, verifyStaff, now, monotonic });
  }
  async dispatch(jobId) {
    const first = this.ledger.captured(jobId), policyDigest = delegationDigest(first.policy);
    const start = this.monotonic(), startedAt = this.now(); let expired = false;
    const current = () => {
      const original = this.ledger.captured(jobId);
      check(!expired && this.monotonic() >= start && this.monotonic() - start < 60000 && this.now() >= startedAt && this.now() - startedAt < 60000
        && delegationDigest(original.policy) === policyDigest && delegationDigest(original.context) === delegationDigest(first.context),
      'The original dispatch deadline, capture or policy changed.');
      return original;
    };
    const bounded = async perform => {
      const original = current(); let timer;
      try {
        const remaining = Math.min(30000, 60000 - (this.monotonic() - start),
          original.mapping.actor.expiresAt - this.now(), original.context.binding.expires_at * 1000 - this.now());
        const result = await Promise.race([Promise.resolve().then(perform), new Promise((_, reject) => {
          timer = setTimeout(() => { expired = true;
            try { check(false, 'The protected dispatch response is unavailable. No retry is permitted.'); } catch (error) { reject(error); }
          }, Math.max(1, remaining));
        })]);
        current(); return result;
      } finally { clearTimeout(timer); }
    };
    const authorize = async () => { const original = current(); await bounded(() => this.verifyStaff(original.mapping.actor)); current(); };
    const { policy } = first;
    check(id(this.github.repositories?.[policy.repository]) === policy.repository_id,
      'The approved evidence repository differs from the configured central App grant.');
    const api = await bounded(() => this.github.scoped(policy.repository, 'qa', authorize));
    const read = async path => { const result = await bounded(() => api(path)); await authorize(); return result; };
    check(await branchHead(read, 'main') === policy.runner_source_sha, 'Protected runner main changed; requalification is required.');
    const workflow = await read(`/actions/workflows/${policy.workflow_id}`);
    check(id(workflow.id) === policy.workflow_id && workflow.path === policy.workflow_path && workflow.state === 'active',
      'The protected workflow identity or active state changed.');
    const source = await workflowSource(read, policy.runner_source_sha, policy.workflow_path);
    check(source.sha256 === policy.workflow_sha256 && source.mode === '100644', 'The protected workflow bytes differ from the approved source.');
    // Recheck the branch immediately before dispatch; the returned run is also
    // checked afterward, so a concurrent source change cannot yield evidence.
    check(await branchHead(read, 'main') === policy.runner_source_sha, 'Protected runner main changed before dispatch.');
    await authorize();
    const request = this.ledger.prepareDispatch(jobId), requestedAt = this.now();
    const response = await bounded(() => api(`/actions/workflows/${policy.workflow_id}/dispatches`, 'POST', {
      ref: 'main', inputs: { request: Buffer.from(delegationCanonical(request)).toString('base64'), dispatch_id: request.dispatch_id },
    }));
    await authorize();
    const runId = id(response.workflow_run_id);
    check(response.run_url === `https://api.github.com/repos/${policy.repository}/actions/runs/${runId}`
      && response.html_url === `https://github.com/${policy.repository}/actions/runs/${runId}`,
    'GitHub did not return the exact created run identity. Do not redispatch.');
    const run = await read(`/actions/runs/${runId}`);
    const created = typeof run.created_at === 'string' && /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/.test(run.created_at) ? Date.parse(run.created_at) : NaN;
    check(id(run.id) === runId && id(run.repository?.id) === policy.repository_id && run.repository.full_name === policy.repository
      && id(run.head_repository?.id) === policy.repository_id && run.head_repository.full_name === policy.repository
      && id(run.workflow_id) === policy.workflow_id && run.path === policy.workflow_path && run.head_sha === policy.runner_source_sha
      && run.head_branch === 'main' && run.event === 'workflow_dispatch' && run.run_attempt === 1
      && id(run.actor?.id) === this.ledger.appActorId && id(run.triggering_actor?.id) === this.ledger.appActorId
      && run.display_title === `MRN Management QA ${request.dispatch_id}`
      && Number.isSafeInteger(created) && new Date(created).toISOString() === run.created_at.replace('Z', '.000Z')
      && created >= Math.floor(requestedAt / 1000) * 1000 && created <= this.now()
      && (['queued', 'in_progress', 'pending', 'waiting', 'requested'].includes(run.status) ? run.conclusion === null
        : run.status === 'completed' && run.conclusion === 'success'),
    'The original run does not match the protected source, App, request and creation time.');
    await authorize();
    const provenance = this.ledger.recordDispatch(jobId, request.dispatch_id, runId);
    return { provenance, url: response.html_url };
  }
}
