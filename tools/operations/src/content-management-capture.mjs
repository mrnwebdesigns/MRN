import { readFileSync } from 'node:fs';
import { digest, requireThat } from './contracts.mjs';
import { delegationCanonical, delegationDigest, delegationIssuer } from './delegation.mjs';
import { managementAccessVetoPath } from './content-access.mjs';
import { compileManagementCaptureSchema } from './content-management-schema.mjs';
import { validateManagementEvidence } from './content-management-contract.mjs';

// Source-only consumer. No private reader, queue, signer, dispatch, admission or
// workspace registration is enabled here. Expected state must come from the
// original authenticated queue and independently validated preview, never from
// the capture response or a public tool argument.
const source = JSON.parse(readFileSync(new URL('../contracts/content-management-qualification-source.json', import.meta.url)));
const bytes = readFileSync(new URL('../contracts/content-management-qualification-v1.json', import.meta.url));
const insist = condition => requireThat(condition, 'MANAGEMENT_CAPTURE_MISMATCH', 'The private capture differs from its original requester, source, plan or measured state.');
insist(digest(bytes) === source.files['contracts/content-management-qualification-v1.json'].sha256);
export const managementCaptureContract = JSON.parse(bytes);
insist(managementCaptureContract.adapter === 'content-management-qualification-v1'
  && managementCaptureContract.production_enabled === false && managementCaptureContract.consumer_binding_enabled === false
  && managementCaptureContract.callback_url === delegationIssuer + managementAccessVetoPath);
export const managementCaptureTypes = Object.fromEntries(['binding', 'context', 'dataset', 'artifact', 'measurements', 'capture_output', 'measurement_output']
  .map(name => [name, compileManagementCaptureSchema({ $ref: `#/$defs/${name}`, $defs: managementCaptureContract.$defs })]));
const managementContractHash = digest(readFileSync(new URL('../contracts/content-management-v1.json', import.meta.url)));
const same = (a, b) => delegationDigest(a) === delegationDigest(b);
const size = value => Buffer.byteLength(delegationCanonical(value));

function datasetBounds(value) {
  // The public snapshot validator enforces its own 196608-byte limit. The
  // private outer envelope does not replace these separate inner limits.
  insist(size(value) <= 4400000 && size(value.parity_manifest) <= 4194304);
  let nodes = 0; const pending = [value];
  while (pending.length) {
    const item = pending.pop(); insist(++nodes <= 100000);
    if (item && typeof item === 'object') pending.push(...Object.values(item));
  }
}

function pluginInventory(dataset) {
  const raw = dataset.active_plugins_original_json;
  let plugins; try { plugins = JSON.parse(raw); } catch { insist(false); }
  insist(plugins && typeof plugins === 'object' && Object.keys(plugins).length <= 100);
  // A PHP array with gaps is an object. Empty/contiguous arrays serialize as
  // lists; preserve this distinction and the original activation order/hash.
  if (!Array.isArray(plugins)) {
    const keys = Object.keys(plugins);
    insist(keys.length > 0 && keys.every(key => /^(?:0|[1-9][0-9]{0,18})$/.test(key) && BigInt(key) <= 9223372036854775807n)
      && !keys.every((key, index) => key === String(index)));
  }
  insist(Object.values(plugins).every(value => typeof value === 'string') && delegationCanonical(plugins) === raw
    && digest(raw) === dataset.read.snapshot.runtime.plugins_sha256
    && same(Object.values(plugins).sort(), dataset.parity_manifest.active_plugins));
}

export function validateManagementContext(queuedContext, { evidence, request, profile, now = Date.now() }) {
  const context = managementCaptureTypes.context.parse(queuedContext), b = context.binding;
  const at = Math.floor(now / 1000);
  insist(context.state === 'queued' && context.capture_artifact_sha256 === null && context.binding_sha256 === delegationDigest(b)
    && b.target.post_id > 0 && b.expected_target_revision === 0 && b.created_at <= at && b.expires_at > at
    && b.expires_at === Math.min(evidence.review.expires_at, b.created_at + b.job_intent_ttl_seconds));
  const original = validateManagementEvidence(evidence, { site: { id: b.site_id, url: b.site_url }, target: b.target,
    request, profile, rollbackOf: evidence.review.rollback_operation_id, now });
  const review = original.review, migration = original.migration;
  insist(b.operation_id === review.operation_id && b.reviewed_digest === review.digest
    && b.before_sha256 === review.before_sha256 && b.candidate_sha256 === review.candidate_sha256
    && b.request_sha256 === delegationDigest(migration.request) && b.migration_sha256 === delegationDigest(migration)
    && b.profile_sha256 === profile.sha256 && b.runtime_context_sha256 === profile.runtime_context_sha256
    && b.before_paths_sha256 === delegationDigest(original.read.affected_paths)
    && b.acf_schema_sha256 === delegationDigest(original.read.snapshot.acf_definitions)
    && b.management_contract_sha256 === managementContractHash);
  return { context, original };
}

export function validateManagementCapture(value, expected) {
  const { context, original } = validateManagementContext(expected.queuedContext, expected);
  const b = context.binding, { review, migration } = original;
  const at = Math.floor((expected.now ?? Date.now()) / 1000);
  const result = managementCaptureTypes.capture_output.parse(value), artifact = result.capture_artifact;
  insist(size(artifact) <= 5000000 && result.qualification_job_id === b.qualification_job_id
    && same(artifact.binding, b) && artifact.binding_sha256 === context.binding_sha256
    && same(artifact.review, review) && same(artifact.migration, migration)
    && result.capture_artifact_sha256 === delegationDigest(artifact));
  const dataset = artifact.dataset, runtime = dataset.read.snapshot.runtime, parity = dataset.parity_manifest;
  datasetBounds(dataset);
  insist(same(dataset.read, original.read) && dataset.acf_schema_sha256 === b.acf_schema_sha256
    && dataset.source_binding_sha256 === b.source_binding_sha256 && artifact.dataset_sha256 === delegationDigest(dataset)
    && dataset.parity_sha256 === delegationDigest(parity)
    && parity.wordpress_version === runtime.wordpress && parity.php_version.split('.').slice(0, 2).join('.') === runtime.php_minor
    && parity.stylesheet === runtime.stylesheet && parity.template === runtime.template);
  pluginInventory(dataset);
  const measured = result.measurements;
  insist(measured.binding_sha256 === context.binding_sha256 && measured.capture_artifact_sha256 === result.capture_artifact_sha256
    && measured.dataset_sha256 === artifact.dataset_sha256 && measured.snapshot_sha256 === b.before_sha256
    && measured.acf_schema_sha256 === b.acf_schema_sha256 && measured.parity_sha256 === dataset.parity_sha256
    && measured.source_binding_sha256 === b.source_binding_sha256 && measured.dataset_bytes === size(dataset)
    && measured.chunks_received === Math.ceil(measured.dataset_bytes / 32768) && measured.readback_bytes === size(dataset.read)
    && measured.readback_sha256 === delegationDigest(dataset.read)
    && measured.readback_chunks === Math.ceil(measured.readback_bytes / 32768)
    && measured.native_calls === measured.chunks_received + measured.readback_chunks
    && measured.captured_at >= b.created_at && measured.captured_at <= at && measured.captured_at < b.expires_at);
  return result;
}

export function validateManagementMeasurements(value, capture, expected) {
  capture = validateManagementCapture(capture, expected);
  const result = managementCaptureTypes.measurement_output.parse(value);
  insist(result.qualification_job_id === capture.qualification_job_id && result.binding_sha256 === capture.capture_artifact.binding_sha256
    && same(result.measurements, capture.measurements) && result.measurement_record_sha256 === delegationDigest(result.measurements));
  return result;
}
