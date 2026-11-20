import { readFileSync } from 'node:fs';
import { delegationDigest } from '../../src/delegation.mjs';
import { requesterDigest } from '../../src/access-principal.mjs';

// Source vectors provide data, not authority. Rebase synthetic time and bind
// the fixture's independently selected identity before mapping a queue.
export function managementAccessFixture(actor, now) {
  const a = JSON.parse(readFileSync(new URL('./content-management-qualification-v1.json', import.meta.url))).capture_output.capture_artifact;
  const delta = Math.floor(now / 1000) - a.binding.created_at - 2;
  a.review.expires_at += delta;
  const descriptor = { ...a.review }; delete descriptor.digest; a.review.digest = delegationDigest(descriptor);
  Object.assign(a.binding, { created_at: a.binding.created_at + delta, expires_at: a.binding.expires_at + delta,
    requester_binding_sha256: requesterDigest(actor), reviewed_digest: a.review.digest });
  const context = { binding: a.binding, binding_sha256: delegationDigest(a.binding), state: 'queued', capture_artifact_sha256: null };
  const request = Object.fromEntries(['site_id', 'site_url', 'operation_id', 'reviewed_digest'].map(key => [key, a.binding[key]]));
  const { before_sha256: expected_snapshot_sha256, ...change } = a.migration.request;
  const expected = { evidence: { review: a.review, migration: a.migration, read: a.dataset.read },
    request: { ...change, expected_snapshot_sha256, reason: a.migration.reason, idempotency_key: a.binding.operation_id },
    profile: a.review.profile,
    pins: Object.fromEntries(['authorization_policy_sha256', 'provider_source_sha', 'installed_manifest_sha256', 'source_binding_sha256', 'job_intent_ttl_seconds']
      .map(key => [key, a.binding[key]])) };
  return { context, request, expected };
}
