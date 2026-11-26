import { randomBytes, randomUUID } from 'node:crypto';
import { requireThat } from './contracts.mjs';
import { delegationCanonical, delegationDigest } from './delegation.mjs';
import { createPrivateQueryReader } from './access-control.mjs';
import { payload } from './mainwp.mjs';
import { ManagementEvidenceSigner } from './management-evidence-callback.mjs';
import { existingManagementTypes, existingManagementWires } from './management-existing-contracts.mjs';
import { OriginalManagementAuthorizationLedger } from './original-management-authorization.mjs';

const wire = existingManagementWires.qualification, types = existingManagementTypes.qualification;
const check = value => requireThat(value, 'MANAGEMENT_EXISTING_MEASUREMENTS',
  'The original captured measurements are unavailable, changed or expired.');
const same = (a, b) => delegationDigest(a) === delegationDigest(b);
export const existingManagementMeasurementsTool = 'mrn_mainwp__read_content_edit_qualification_measurements_existing_v2';

export class ExistingManagementMeasurementsReader extends createPrivateQueryReader(existingManagementMeasurementsTool) {
  async read(envelope) {
    check(this.ready);
    const input = types.signed_input.parse({ signed_request: envelope }), sent = delegationDigest(input);
    const value = types.measurement_output.parse(payload(await this.client.callTool(
      { name: existingManagementMeasurementsTool, arguments: input }, undefined, { timeout: wire.limits.read_seconds * 1000 })));
    check(this.ready && sent === delegationDigest(input) && Buffer.byteLength(delegationCanonical(value)) <= wire.limits.response_bytes);
    return value;
  }
}

// Read only after the original ledger has accepted capture bytes. The response
// cannot create that receipt, substitute another login or become QA admission.
// Provider read budgets and current-access callbacks remain independently enforced.
export class ExistingManagementMeasurements {
  constructor({ ledger, reader, registrar, now = () => Date.now(), monotonic = () => performance.now() }) {
    check(ledger instanceof OriginalManagementAuthorizationLedger && reader instanceof ExistingManagementMeasurementsReader);
    Object.assign(this, { ledger, reader, now, monotonic });
    this.signer = new ManagementEvidenceSigner({ ...registrar, now });
  }
  async read(jobId) {
    const started = this.monotonic(), startedAt = this.now(), limit = wire.limits.read_seconds * 1000;
    const within = () => check(this.reader.ready && this.monotonic() >= started && this.monotonic() - started < limit
      && this.now() >= startedAt && this.now() - startedAt < limit);
    const bounded = async perform => {
      within(); let timer;
      try {
        const value = await Promise.race([Promise.resolve().then(perform), new Promise((_, reject) => {
          timer = setTimeout(() => reject(new Error('MANAGEMENT_EXISTING_MEASUREMENTS_TIMEOUT')),
            Math.max(1, limit - (this.monotonic() - started)));
        })]);
        within(); return value;
      } finally { clearTimeout(timer); }
    };
    const original = await bounded(() => this.ledger.currentQueuedJob(jobId));
    const captured = await bounded(() => this.ledger.readCapture(jobId)), queued = original.queue.context;
    check(captured.state === 'captured' && captured.receipt?.bindingSha256 === queued.binding_sha256);
    const issued = Math.floor(this.now() / 1000), expires = Math.min(queued.binding.expires_at,
      Math.floor(original.original.mapping.authorization.expiresAt / 1000), Math.floor((startedAt + limit) / 1000));
    check(expires > issued && issued >= queued.binding.created_at);
    const claims = types.worker_claims.parse({ schema: 2, issuer: 'mrn-content-qualification', audience: 'https://wpcontrol.mrndev.io',
      issued_at: issued, expires_at: expires, request_id: randomUUID(), nonce: randomBytes(32).toString('hex'), action: 'read-measurements',
      binding: queued.binding, binding_sha256: queued.binding_sha256 });
    const input = types.signed_input.parse({ signed_request: this.signer.envelope(claims, wire.purposes['read-measurements']) });
    const sent = delegationDigest(input), output = await bounded(() => this.reader.read(input.signed_request));
    const receipt = captured.receipt;
    check(sent === delegationDigest(input) && expires * 1000 > this.now()
      && output.qualification_job_id === jobId && output.binding_sha256 === queued.binding_sha256
      && output.measurement_record_sha256 === delegationDigest(output.measurements)
      && output.measurement_record_sha256 === receipt.measurementSha256
      && output.measurements.binding_sha256 === receipt.bindingSha256
      && output.measurements.capture_artifact_sha256 === receipt.captureArtifactSha256
      && output.measurements.dataset_sha256 === receipt.datasetSha256);
    const current = await bounded(() => this.ledger.currentQueuedJob(jobId));
    const after = await bounded(() => this.ledger.readCapture(jobId));
    check(current.original.digest === original.original.digest && current.revision === original.revision
      && current.queue.digest === original.queue.digest && same(current.queue.context, queued)
      && same(after, captured) && expires * 1000 > this.now());
    return output;
  }
}

// Private preparation only; production startup and workspace tools do not import this.
export async function createExistingManagementMeasurementsRuntime({ enabled = false, ledger, registrar, connect, now, monotonic } = {}) {
  check(typeof enabled === 'boolean'); if (!enabled) return null;
  check(typeof connect === 'function');
  const client = await connect(), reader = new ExistingManagementMeasurementsReader(client);
  let closing;
  const close = () => {
    if (!closing) { reader.ready = false; closing = Promise.resolve().then(() => client.close()); }
    return closing;
  };
  try {
    await reader.initialize();
    return { measurements: new ExistingManagementMeasurements({ ledger, reader, registrar, now, monotonic }), close };
  } catch (error) { await close().catch(() => {}); throw error; }
}
