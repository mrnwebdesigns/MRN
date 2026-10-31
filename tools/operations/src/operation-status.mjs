import { requireThat } from './contracts.mjs';
import { toolNames } from './mainwp.mjs';
import { contentCall, contentSame } from './content-contract.mjs';

// Read only the retained provider operation belonging to an existing local
// plan. Provider completion is not live verification and cannot release locks.
export async function providerOperationStatus(session, operation) {
  const plan = operation.plan?.provider;
  requireThat(['code', 'content'].includes(operation.workflow) && plan, 'OPERATION_STATUS_UNAVAILABLE', 'This operation has no retained MainWP code/content plan to inspect.');
  // Resolve the exact current directory identity. This reads retained Dashboard
  // audit metadata, not child inventory/health; do not start a child sync.
  await session.call(toolNames.site, { site_id_or_domain: session.target.url, include_stats: false });
  const site = { id: session.siteId, url: session.target.url };
  requireThat(plan.site_id === site.id, 'TARGET_MISMATCH', 'The retained provider plan belongs to a different resolved website.');
  let result;
  if (operation.workflow === 'content') {
    requireThat(plan.site_url === site.url, 'TARGET_MISMATCH', 'The retained content plan belongs to a different website.');
    session.contentOperation = { id: plan.operation_id, digest: plan.digest };
    result = await contentCall(session, 'get-content-operation-v1', { operation_id: plan.operation_id });
    requireThat(result.operation_id === plan.operation_id && result.site_id === site.id && result.site_url === site.url
      && contentSame(result.target, plan.target) && result.before_sha256 === plan.before_sha256 && result.candidate_sha256 === plan.candidate_sha256
      && contentSame(result.affected_paths, plan.affected_paths) && contentSame(result.migration_artifact, plan.migration_artifact),
    'OPERATION_STATUS_MISMATCH', 'MainWP returned metadata for a different content plan.');
  } else {
    requireThat(plan.site_url === site.url + '/', 'TARGET_MISMATCH', 'The retained code plan belongs to a different website.');
    session.codeOperation = { id: plan.operation_id, digest: plan.digest };
    result = await session.call(toolNames.codeStatus, { operation_id: plan.operation_id });
    requireThat(result?.operation_id === plan.operation_id && result.digest === plan.digest && result.site_id === site.id
      && result.site_url === plan.site_url && result.path === plan.path && result.kind === plan.kind
      && result.before_sha256 === plan.before_sha256 && result.new_sha256 === plan.new_sha256
      && ['prepared', 'dispatching', 'completed', 'unknown', 'reported_failure', 'resolved_after_inspection'].includes(result.state),
    'OPERATION_STATUS_MISMATCH', 'MainWP returned metadata for a different code plan or an unsupported state.');
  }
  return { providerOperationId: plan.operation_id, state: result.state, observedAt: new Date().toISOString(),
    evidence: 'retained-provider-operation', liveStateVerified: false, lockReleased: false,
    explanation: 'This is the provider’s retained operation status. It does not verify current content or code, retry a change, release a lock or qualify rollback. The recorded Operations outcome remains unchanged.' };
}
