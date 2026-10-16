import { z } from 'zod';
import { digest, requireThat, sha } from './contracts.mjs';
import { managementEvidenceLimits } from './github-management-evidence.mjs';
import { managementEvidenceTypes } from './management-evidence-contract.mjs';

const identifier = z.string().regex(/^[1-9][0-9]{0,19}$/);
const recordSchema = z.object({ repository: z.string().regex(/^mrnwebdesigns\/[A-Za-z0-9_-]+$/),
  repositoryId: identifier, workflowId: identifier, runId: identifier, appActorId: identifier,
  workflowPath: z.string().regex(/^\.github\/workflows\/[a-z0-9._-]+\.ya?ml$/),
  runnerCommit: z.string().regex(/^[a-f0-9]{40}$/),
  requestedAt: z.number().int().safe().positive(), originalExpiresAt: z.number().int().safe().positive() }).strict();
const check = (condition, message) => requireThat(condition, 'MANAGEMENT_EVIDENCE_BINDING', message);
const id = value => {
  const text = typeof value === 'number' && Number.isSafeInteger(value) && value > 0 ? String(value) : value;
  check(identifier.safeParse(text).success, 'GitHub returned an invalid or imprecise identifier.'); return text;
};
const timestamp = value => {
  check(typeof value === 'string' && /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{3})?Z$/.test(value), 'GitHub returned an invalid UTC observation time.');
  const at = Date.parse(value);
  check(Number.isSafeInteger(at) && new Date(at).toISOString() === (value.length === 20 ? value.replace('Z', '.000Z') : value),
    'GitHub returned an invalid calendar observation time.');
  return Math.floor(at / 1000);
};

// Internal only: record must come from immutable service-recorded dispatch,
// joined to the original human intent. No public tool, report or callback can
// supply it. Download verification alone cannot authorize report retention.
export async function downloadManagementEvidence({ github, record: input, authorize, now = () => Date.now() }) {
  const parsed = recordSchema.safeParse(input);
  check(parsed.success, 'An exact original service dispatch record is required.');
  const record = parsed.data;
  check(record.requestedAt <= now() && record.originalExpiresAt > now() && record.originalExpiresAt > record.requestedAt,
    'The original service dispatch is stale or invalid.');
  const api = await github.managementEvidence(record.repository, authorize, { expiresAt: record.originalExpiresAt });
  const receivedRun = await api.readRun(record.runId), run = receivedRun.value;
  check(sha.safeParse(receivedRun.sha256).success && run && typeof run === 'object', 'Original run metadata is required.');
  check(id(run.id) === record.runId && id(run.repository?.id) === record.repositoryId && run.repository.full_name === record.repository
    && id(run.head_repository?.id) === record.repositoryId && run.head_repository.full_name === record.repository
    && id(run.workflow_id) === record.workflowId && run.path === record.workflowPath
    && run.head_sha === record.runnerCommit && run.head_branch === 'main' && run.event === 'workflow_dispatch'
    && run.run_attempt === 1 && run.status === 'completed' && run.conclusion === 'success'
    && id(run.actor?.id) === record.appActorId && id(run.triggering_actor?.id) === record.appActorId,
  'The completed run does not match the service-recorded protected dispatch.');
  const created = timestamp(run.created_at), updated = timestamp(run.updated_at);
  check(created >= Math.floor(record.requestedAt / 1000) && updated >= created && updated <= Math.floor(now() / 1000),
    'The run observations do not fit the original dispatch lifetime.');
  const observedRun = { repository_id: record.repositoryId, workflow_id: record.workflowId, run_id: record.runId,
    run_attempt: 1, head_sha: record.runnerCommit, head_branch: 'main', event: 'workflow_dispatch', status: 'completed',
    conclusion: 'success', actor_id: record.appActorId, triggering_actor_id: record.appActorId,
    created_at: created, updated_at: updated, metadata_response_sha256: receivedRun.sha256 };
  const receivedInventory = await api.readArtifacts(record.runId), inventory = receivedInventory.value;
  check(sha.safeParse(receivedInventory.sha256).success && inventory?.total_count === 2
    && Array.isArray(inventory.artifacts) && inventory.artifacts.length === 2,
  'A complete exact two-artifact inventory is required; partial or additional evidence is refused.');
  const roles = { qualification: 'management-qualification-evidence-v1', acquisition: 'management-acquisition-evidence-v1' };
  const selected = {}, identifiers = new Set();
  // Validate the entire inventory before downloading either artifact.
  for (const [role, name] of Object.entries(roles)) {
    const matches = inventory.artifacts.filter(item => item?.name === name);
    check(matches.length === 1, 'Evidence artifact roles are missing or ambiguous.');
    const item = matches[0], artifactId = id(item.id), createdAt = timestamp(item.created_at), expiresAt = timestamp(item.expires_at);
    check(!identifiers.has(artifactId) && item.expired === false && typeof item.digest === 'string'
      && /^sha256:[a-f0-9]{64}$/.test(item.digest) && Number.isSafeInteger(item.size_in_bytes)
      && item.size_in_bytes > 0 && item.size_in_bytes <= managementEvidenceLimits[`${role}Bytes`]
      && id(item.workflow_run?.id) === record.runId && id(item.workflow_run?.repository_id) === record.repositoryId
      && item.workflow_run.head_sha === record.runnerCommit && item.workflow_run.head_branch === 'main'
      && createdAt >= created && createdAt <= updated && expiresAt > Math.floor(now() / 1000),
    'The evidence artifact is stale, oversized or belongs to another run or source.');
    identifiers.add(artifactId); selected[role] = { item, artifactId, createdAt, expiresAt };
  }
  const outputs = {};
  for (const [role, name] of Object.entries(roles)) {
    const { item, artifactId, createdAt, expiresAt } = selected[role];
    const archive = await api.downloadArtifact(artifactId, role);
    check(Buffer.isBuffer(archive.bytes) && archive.bytes.length === item.size_in_bytes
      && archive.sha256 === digest(archive.bytes) && item.digest === `sha256:${archive.sha256}`
      && Number.isSafeInteger(archive.downloadedAt) && archive.downloadedAt <= now()
      && archive.downloadedAt >= record.requestedAt && archive.downloadedAt < record.originalExpiresAt
      && archive.downloadedAt < expiresAt * 1000,
    'The downloaded original bytes do not match their independently observed artifact metadata.');
    outputs[`${role}_zip`] = archive.bytes;
    outputs[`${role}_artifact`] = { artifact_id: artifactId, name, bytes: archive.bytes.length,
      sha256: archive.sha256, reported_digest: item.digest, expired: false,
      workflow_run: { repository_id: record.repositoryId, run_id: record.runId, head_sha: record.runnerCommit },
      created_at: createdAt, expires_at: expiresAt, download_completed_at: Math.floor(archive.downloadedAt / 1000) };
  }
  const timing = await api.finish();
  check(timing.startedAt >= record.requestedAt && timing.completedAt >= timing.startedAt
    && timing.completedAt - timing.startedAt < managementEvidenceLimits.acquisitionMs
    && timing.completedAt <= now() && now() < record.originalExpiresAt,
  'The acquisition did not complete within the original protected intent.');
  check(managementEvidenceTypes.run_observation.safeParse(observedRun).success
    && managementEvidenceTypes.artifact_observation.safeParse(outputs.qualification_artifact).success
    && managementEvidenceTypes.artifact_observation.safeParse(outputs.acquisition_artifact).success,
  'The independently acquired evidence does not match the separate provider observation contract.');
  return { observed_run: observedRun, ...outputs, started_at: Math.floor(timing.startedAt / 1000),
    completed_at: Math.floor(timing.completedAt / 1000) };
}
