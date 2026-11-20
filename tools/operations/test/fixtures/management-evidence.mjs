import { readFileSync } from 'node:fs';
import { randomUUID } from 'node:crypto';
import { Store } from '../../src/store.mjs';
import { delegationDigest } from '../../src/delegation.mjs';
import { ManagementAccessLedger } from '../../src/content-management-access-store.mjs';
import { ManagementEvidenceLedger } from '../../src/management-evidence-store.mjs';
import { managementAccessFixture } from './management-access.mjs';
import { accessRegistry } from './access-registry.mjs';

export function managementEvidenceFixture(t, file = ':memory:', now = 1800000000000) {
  const state = { now, mono: 0, closed: false };
  const store = new Store(file); t.after(() => { if (!state.closed) store.close(); });
  const actor = { subject: 'google-oauth2|123', googleSubject: '123', verifiedEmail: 'staff@mrnwebdesigns.com',
    identityProvider: 'google-oauth2', hostedDomain: 'mrnwebdesigns.com', googleConnection: 'con_fixture',
    expiresAt: state.now + 3600000, scopes: ['mrn:operations', 'mrn:operations:write'], token: 'never-store-this-token' };
  const { context, request, expected } = managementAccessFixture(actor, state.now);
  const { registry, policy: registryPolicy, registryData } = accessRegistry(request.site_url);
  const access = new ManagementAccessLedger(store, { connectionId: 'con_fixture', registry, now: () => state.now });
  access.recordQueued(actor, request, context, expected, access.assertQueueAllowed(actor, request));
  const capture = JSON.parse(readFileSync(new URL('./content-management-qualification-v1.json', import.meta.url))).capture_output;
  Object.assign(capture.capture_artifact, { binding: context.binding, binding_sha256: context.binding_sha256,
    review: expected.evidence.review, migration: expected.evidence.migration });
  capture.capture_artifact_sha256 = delegationDigest(capture.capture_artifact);
  Object.assign(capture.measurements, { binding_sha256: context.binding_sha256,
    capture_artifact_sha256: capture.capture_artifact_sha256, captured_at: state.now / 1000 });
  const measurements = { qualification_job_id: context.binding.qualification_job_id, binding_sha256: context.binding_sha256,
    measurements: capture.measurements, measurement_record_sha256: delegationDigest(capture.measurements) };
  const policy = { schema: 1, enabled: false, policy_epoch: 1, qa_key_ids: ['fixture-qa'],
    repository: 'mrnwebdesigns/mrn-code-qa-runner', repository_id: '10', ref: 'refs/heads/main', workflow_id: '20',
    workflow_path: '.github/workflows/management.yml', workflow_sha256: 'a'.repeat(64), runner_source_sha: 'b'.repeat(40),
    engine_commit: 'c'.repeat(40), engine_configuration_sha256: 'd'.repeat(64), runtime_sha256: 'e'.repeat(64),
    performance_budget_sha256: 'f'.repeat(64), render_assertions_sha256: '1'.repeat(64), hook_policy_sha256: '2'.repeat(64) };
  const options = { policy: () => policy, appActorId: '40', now: () => state.now };
  const ledger = new ManagementEvidenceLedger(access, options), job = context.binding.qualification_job_id;
  const recordCapture = () => ledger.recordCapture(job, capture, measurements, { ...expected, queuedContext: context });
  const prepare = () => { recordCapture(); return ledger.prepareDispatch(job); };
  const dispatch = () => { const req = prepare(); return ledger.recordDispatch(job, req.dispatch_id, '30'); };
  const query = () => ({ schema: 1, query_id: randomUUID(), provider_query_nonce: '3'.repeat(64),
    context: ledger.captured(job).context, created_at: state.now / 1000, expires_at: state.now / 1000 + 90 });
  return { state, store, actor, access, ledger, registry, registryPolicy, registryData, options, policy,
    context, request, expected, capture, measurements, job, recordCapture, prepare, dispatch, query };
}
