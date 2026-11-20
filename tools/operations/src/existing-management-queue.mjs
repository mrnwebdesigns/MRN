import { readFileSync } from 'node:fs';
import { digest, requireThat } from './contracts.mjs';
import { delegationDigest } from './delegation.mjs';
import { validateManagementQueuePins } from './content-management-access-store.mjs';
import { existingManagementHashes, existingManagementProtocol, existingManagementTypes } from './management-existing-contracts.mjs';
import { parseOriginalBindingProjection } from './original-authorization-transport.mjs';

const managementContractSha256 = digest(readFileSync(new URL('../contracts/content-management-v1.json', import.meta.url)));
const check = condition => requireThat(condition, 'EXISTING_MANAGEMENT_QUEUE',
  'The qualification job differs from its retained original review, authority, source or lifetime.');
const same = (a, b) => delegationDigest(a) === delegationDigest(b);

// Private metadata validation only. Original and providerBinding must come from
// fresh service-owned ledger reads; pins come from independent protected-worker
// readiness. This function does not establish identity, store/dispatch a job,
// acquire source, register a tool or authorize a content write.
export function validateExistingManagementQueue(value, { original, providerBinding, request, pins, dispatchedAt, now = Date.now() }) {
  const context = existingManagementTypes.qualification.context.parse(value);
  const input = existingManagementTypes.qualification.request_input.parse(request);
  const projection = parseOriginalBindingProjection(providerBinding?.projection);
  pins = validateManagementQueuePins(pins);
  const m = original?.mapping, review = original?.review, anchor = review?.qualification;
  check(original?.state === 'reviewed' && review?.schema === 2 && anchor
    && anchor.managementContractSha256 === managementContractSha256
    && Number.isSafeInteger(dispatchedAt) && dispatchedAt > 0 && dispatchedAt <= now
    && Number.isSafeInteger(now) && now > 0);
  check(providerBinding.original === m.requestId && providerBinding.requester === m.requester
    && providerBinding.principal === m.principal && same(providerBinding.actor, m.actor)
    && providerBinding.coherenceEpochSha256 === m.readiness.coherenceEpochSha256
    && projection.original_delegation_request_id === m.requestId
    && projection.original_resource_authorization_sha256 === m.authorization.authorizationSha256
    && projection.original_resource_authorization_expires_at === Math.floor(m.authorization.expiresAt / 1000)
    && projection.operation_id === review.operationId && projection.reviewed_digest === review.reviewedDigest
    && projection.qualification_protocol === existingManagementProtocol
    && same(projection.protocol_contracts_sha256, existingManagementHashes)
    && pins.provider_source_sha === m.pins.provider_source_sha
    && pins.authorization_policy_sha256 === m.pins.authorization_policy_sha256);
  const expectedInput = { site_id: m.target.site_id, site_url: m.target.site_url,
    operation_id: review.operationId, reviewed_digest: review.reviewedDigest };
  check(same(input, expectedInput));
  const b = context.binding, at = Math.floor(now / 1000);
  check(context.state === 'queued' && context.capture_artifact_sha256 === null
    && context.binding_sha256 === delegationDigest(b)
    && b.created_at >= Math.floor(dispatchedAt / 1000) && b.created_at <= at && b.expires_at > at);
  const expected = { schema: 2, adapter: 'content-management-v1', qualification_job_id: b.qualification_job_id,
    ...expectedInput, requester_binding_sha256: m.requester, ...pins,
    target: { kind: 'wordpress-content', post_type: m.target.post_type, post_id: m.target.post_id },
    expected_target_revision: 0, before_sha256: review.beforeSha256, candidate_sha256: review.candidateSha256,
    request_sha256: anchor.requestSha256, migration_sha256: review.migrationSha256,
    profile_sha256: anchor.profileSha256, runtime_context_sha256: anchor.runtimeContextSha256,
    acf_schema_sha256: anchor.acfSchemaSha256, management_contract_sha256: anchor.managementContractSha256,
    before_paths_sha256: anchor.beforePathsSha256, created_at: b.created_at,
    expires_at: Math.min(Math.floor(review.expiresAt / 1000), Math.floor(m.authorization.expiresAt / 1000),
      b.created_at + pins.job_intent_ttl_seconds, b.created_at + 900),
    qualification_protocol: existingManagementProtocol, protocol_contracts_sha256: existingManagementHashes };
  check(same(b, expected));
  return context;
}
