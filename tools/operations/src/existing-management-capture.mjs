import { randomBytes } from 'node:crypto';
import { requireThat } from './contracts.mjs';
import { delegationCanonical, delegationDigest } from './delegation.mjs';
import { createPrivateQueryReader } from './access-control.mjs';
import { payload } from './mainwp.mjs';
import { ManagementEvidenceSigner } from './management-evidence-callback.mjs';
import { existingManagementHashes, existingManagementTypes, existingManagementWires } from './management-existing-contracts.mjs';
import { validateManagementReviewBinding, validateManagementCapturePayload } from './content-management-capture.mjs';
import { OriginalManagementAuthorizationLedger } from './original-management-authorization.mjs';

const wire = existingManagementWires.qualification, types = existingManagementTypes.qualification;
const check = value => requireThat(value, 'MANAGEMENT_EXISTING_CAPTURE', 'The original capture is unavailable, changed or expired.');
const same = (a, b) => delegationDigest(a) === delegationDigest(b);
export const existingManagementCaptureTools = Object.freeze({
  capture: 'mrn_mainwp__capture_content_edit_qualification_existing_v2',
  'read-capture': 'mrn_mainwp__read_content_edit_qualification_capture_existing_v2',
});

// The queue and original authorization come from the ledger. Expected content
// comes from the independently retained preview, never from a capture response.
export function validateExistingManagementCaptureContext(current, expected, now = Date.now()) {
  const context = types.context.parse(current.queue.context), b = context.binding;
  const originalExpires = Math.floor(current.original.mapping.authorization.expiresAt / 1000), at = Math.floor(now / 1000);
  check(current.queue.state === 'observed' && context.state === 'queued' && context.capture_artifact_sha256 === null
    && context.binding_sha256 === delegationDigest(b) && same(b.protocol_contracts_sha256, existingManagementHashes)
    && b.created_at <= at && b.expires_at > at && b.expected_target_revision === 0 && b.target.post_id > 0
    && b.expires_at === Math.min(expected.evidence.review.expires_at, originalExpires, b.created_at + b.job_intent_ttl_seconds, b.created_at + 900));
  return { context, original: validateManagementReviewBinding(b, { ...expected, now }) };
}

export function validateExistingManagementCapture(value, current, expected, now = Date.now()) {
  const retained = validateExistingManagementCaptureContext(current, expected, now);
  return validateManagementCapturePayload(types.capture_output.parse(value), { ...retained, now });
}

const CaptureConnection = createPrivateQueryReader(existingManagementCaptureTools.capture);
const RecoveryConnection = createPrivateQueryReader(existingManagementCaptureTools['read-capture']);
export class ExistingManagementCaptureReader {
  constructor(client) {
    this.client = client; this.ready = false;
    this.connections = [new CaptureConnection(client), new RecoveryConnection(client)];
  }
  async initialize() {
    this.ready = false;
    for (const connection of this.connections) await connection.initialize();
    this.ready = true;
  }
  async read(action, envelope) {
    check(this.ready && Object.hasOwn(existingManagementCaptureTools, action));
    const input = types.signed_input.parse({ signed_request: envelope }), inputDigest = delegationDigest(input);
    const result = types.capture_output.parse(payload(await this.client.callTool({ name: existingManagementCaptureTools[action], arguments: input },
      undefined, { timeout: (action === 'capture' ? wire.limits.capture_seconds : wire.limits.read_seconds) * 1000 })));
    check(this.ready && delegationDigest(input) === inputDigest && Buffer.byteLength(delegationCanonical(result)) <= wire.limits.response_bytes);
    return result;
  }
}

export class ExistingManagementCapture {
  constructor({ ledger, reader, registrar, now = () => Date.now(), monotonic = () => performance.now() }) {
    check(ledger instanceof OriginalManagementAuthorizationLedger && reader instanceof ExistingManagementCaptureReader);
    Object.assign(this, { ledger, reader, now, monotonic });
    this.signer = new ManagementEvidenceSigner({ ...registrar, now });
  }
  capture(jobId, expected) { return this.#run(jobId, expected, 'capture'); }
  recover(jobId, expected) { return this.#run(jobId, expected, 'read-capture'); }
  async #run(jobId, expected, action) {
    const started = this.monotonic(), startedAt = this.now();
    const seconds = action === 'capture' ? wire.limits.capture_seconds : wire.limits.read_seconds, limit = seconds * 1000;
    const within = () => check(this.reader.ready && this.monotonic() >= started && this.monotonic() - started < limit
      && this.now() >= startedAt && this.now() - startedAt < limit);
    const bounded = async perform => {
      within(); let timer;
      try {
        const value = await Promise.race([Promise.resolve().then(perform), new Promise((_, reject) => {
          timer = setTimeout(() => reject(new Error('MANAGEMENT_EXISTING_CAPTURE_TIMEOUT')), Math.max(1, limit - (this.monotonic() - started)));
        })]);
        within(); return value;
      } finally { clearTimeout(timer); }
    };
    // Snapshot service-owned expected evidence before any asynchronous work.
    const saved = structuredClone(expected);
    const current = await bounded(() => this.ledger.currentQueuedJob(jobId));
    const { context } = validateExistingManagementCaptureContext(current, saved, this.now());
    // Mark uncertain before any provider I/O. A restart permits bounded reads
    // of the existing slot only; it never restores this one capture allowance.
    const handle = await bounded(() => this.ledger.claimCapture(jobId, action, within));
    const issued = Math.floor(this.now() / 1000), expires = Math.min(context.binding.expires_at,
      Math.floor(current.original.mapping.authorization.expiresAt / 1000), Math.floor((startedAt + limit) / 1000));
    check(expires > issued);
    const claims = types.worker_claims.parse({ schema: 2, issuer: 'mrn-content-qualification', audience: 'https://wpcontrol.mrndev.io',
      issued_at: issued, expires_at: expires, request_id: handle.requestId, nonce: randomBytes(32).toString('hex'), action,
      binding: context.binding, binding_sha256: context.binding_sha256 });
    const envelope = this.signer.envelope(claims, wire.purposes[action]), inputDigest = delegationDigest(envelope);
    const output = await bounded(() => this.reader.read(action, envelope));
    check(delegationDigest(envelope) === inputDigest && expires * 1000 > this.now());
    const result = validateExistingManagementCapture(output, current, saved, this.now());
    const receipt = { bindingSha256: context.binding_sha256, captureArtifactSha256: result.capture_artifact_sha256,
      measurementSha256: delegationDigest(result.measurements), datasetSha256: result.capture_artifact.dataset_sha256 };
    const currentWithin = () => { within(); check(expires * 1000 > this.now()); };
    await bounded(() => this.ledger.recordCapture(handle, receipt, currentWithin));
    currentWithin(); return result;
  }
}

// Private composition only; production startup and workspace tools do not
// import this module. Provider capture is not content-edit or QA admission.
export async function createExistingManagementCaptureRuntime({ enabled = false, ledger, registrar, connect, now, monotonic } = {}) {
  check(typeof enabled === 'boolean'); if (!enabled) return null;
  check(typeof connect === 'function');
  const client = await connect(), reader = new ExistingManagementCaptureReader(client);
  let closing;
  const close = () => {
    if (!closing) { reader.ready = false; closing = Promise.resolve().then(() => client.close()); }
    return closing;
  };
  try {
    await reader.initialize();
    return { capture: new ExistingManagementCapture({ ledger, reader, registrar, now, monotonic }), close };
  } catch (error) { await close().catch(() => {}); throw error; }
}
