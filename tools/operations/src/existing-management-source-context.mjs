import { randomBytes, randomUUID } from 'node:crypto';
import { z } from 'zod';
import { OpsError, requireThat } from './contracts.mjs';
import { delegationCanonical, delegationDigest } from './delegation.mjs';
import { createPrivateQueryReader } from './access-control.mjs';
import { payload } from './mainwp.mjs';
import { ManagementEvidenceSigner } from './management-evidence-callback.mjs';
import { existingManagementTypes, existingManagementWires } from './management-existing-contracts.mjs';
import { OriginalManagementAuthorizationLedger } from './original-management-authorization.mjs';

const wire = existingManagementWires.source_context, types = existingManagementTypes.source_context;
const check = value => requireThat(value, 'MANAGEMENT_EXISTING_SOURCE_CONTEXT',
  'The original captured source context is unavailable, changed or expired.');
const same = (a, b) => delegationDigest(a) === delegationDigest(b);
const hash = z.string().regex(/^[a-f0-9]{64}$/);
const ownerType = z.object({ evidence_policy_epoch: z.number().int().positive().safe(),
  evidence_policy_sha256: hash, source_recipe_catalog_sha256: hash }).strict();
export const parseExistingManagementSourceOwner = value => ownerType.parse(value);
export const existingManagementSourceContextTool = 'mrn_mainwp__read_content_edit_qualification_source_context_existing_v2';

export class ExistingManagementSourceContextReader extends createPrivateQueryReader(existingManagementSourceContextTool) {
  async read(envelope) {
    check(this.ready);
    const input = types.input.parse({ signed_request: envelope }), sent = delegationDigest(input);
    check(Buffer.byteLength(delegationCanonical(input)) <= wire.limits.request_bytes);
    const result = types.output.parse(payload(await this.client.callTool(
      { name: existingManagementSourceContextTool, arguments: input }, undefined,
      { timeout: wire.limits.lookup_deadline_seconds * 1000 })));
    check(this.ready && sent === delegationDigest(input)
      && Buffer.byteLength(delegationCanonical(result)) <= wire.limits.response_bytes);
    return result;
  }
}

// The owner resolver is service-owned configuration, never a team tool input or
// a policy inferred from the provider response. Measurement bytes must match
// the independently accepted capture receipt. This only returns metadata;
// source acquisition, catalog admission and production setup are separate gates.
export class ExistingManagementSourceContext {
  constructor({ ledger, reader, registrar, owner, now = () => Date.now(), monotonic = () => performance.now() }) {
    check(ledger instanceof OriginalManagementAuthorizationLedger
      && reader instanceof ExistingManagementSourceContextReader && typeof owner === 'function');
    Object.assign(this, { ledger, reader, owner, now, monotonic });
    this.signer = new ManagementEvidenceSigner({ ...registrar, now });
  }
  async read(jobId, measurement) {
    const measured = existingManagementTypes.qualification.measurement_output.parse(measurement);
    const expectedOwner = ownerType.parse(this.owner());
    const started = this.monotonic(), startedAt = this.now(), limit = wire.limits.lookup_deadline_seconds * 1000;
    const within = () => check(this.reader.ready && same(ownerType.parse(this.owner()), expectedOwner)
      && this.monotonic() >= started && this.monotonic() - started < limit
      && this.now() >= startedAt && this.now() - startedAt < limit);
    const bounded = async perform => {
      within(); let timer;
      try {
        const value = await Promise.race([Promise.resolve().then(perform), new Promise((_, reject) => {
          timer = setTimeout(() => reject(new OpsError('MANAGEMENT_EXISTING_SOURCE_CONTEXT_TIMEOUT',
            'The original source-context lookup exceeded its time limit; no retry was dispatched.')),
            Math.max(1, limit - (this.monotonic() - started)));
        })]);
        within(); return value;
      } finally { clearTimeout(timer); }
    };
    const { current: original, capture: captured } = await bounded(() => this.ledger.currentQueuedCapture(jobId));
    const queued = original.queue.context, receipt = captured.receipt;
    check(captured.state === 'captured' && receipt?.bindingSha256 === queued.binding_sha256
      && measured.qualification_job_id === jobId && measured.binding_sha256 === queued.binding_sha256
      && measured.measurement_record_sha256 === delegationDigest(measured.measurements)
      && measured.measurement_record_sha256 === receipt.measurementSha256
      && measured.measurements.binding_sha256 === receipt.bindingSha256
      && measured.measurements.capture_artifact_sha256 === receipt.captureArtifactSha256
      && measured.measurements.dataset_sha256 === receipt.datasetSha256);
    const expected = types.source_context.parse({ management: { binding: queued.binding,
      management_context_sha256: queued.binding_sha256, capture_artifact_sha256: receipt.captureArtifactSha256,
      measurement_record_sha256: receipt.measurementSha256, parity_sha256: measured.measurements.parity_sha256,
      evidence_policy_epoch: expectedOwner.evidence_policy_epoch, evidence_policy_sha256: expectedOwner.evidence_policy_sha256 },
    source_recipe_catalog_sha256: expectedOwner.source_recipe_catalog_sha256 });
    const issued = Math.floor(this.now() / 1000), expires = Math.min(queued.binding.expires_at,
      Math.floor(original.original.mapping.authorization.expiresAt / 1000), Math.floor((startedAt + limit) / 1000));
    check(expires > issued && issued >= queued.binding.created_at);
    const claims = types.claims.parse({ schema: 2, issuer: 'mrn-content-qualification', audience: 'https://wpcontrol.mrndev.io',
      issued_at: issued, expires_at: expires, request_id: randomUUID(), nonce: randomBytes(32).toString('hex'),
      action: 'read-management-source-context', qualification_job_id: jobId, management_context_sha256: queued.binding_sha256 });
    const input = types.input.parse({ signed_request: this.signer.envelope(claims, wire.purpose) }), sent = delegationDigest(input);
    const output = await bounded(() => this.reader.read(input.signed_request));
    check(sent === delegationDigest(input) && output.request_sha256 === sent
      && output.source_context_sha256 === delegationDigest(output.source_context) && same(output.source_context, expected)
      && expires * 1000 > this.now());
    const { current, capture: after } = await bounded(() => this.ledger.currentQueuedCapture(jobId));
    check(current.original.digest === original.original.digest && current.revision === original.revision
      && current.queue.digest === original.queue.digest && same(current.queue.context, queued)
      && same(after, captured) && expires * 1000 > this.now());
    within(); return output;
  }
}

// No startup/workspace registration or fallback to the legacy v1 reader.
export async function createExistingManagementSourceContextRuntime({ enabled = false, ledger, registrar, owner, connect, now, monotonic } = {}) {
  check(typeof enabled === 'boolean'); if (!enabled) return null;
  check(typeof connect === 'function');
  const client = await connect(), reader = new ExistingManagementSourceContextReader(client);
  let closing;
  const close = () => {
    if (!closing) { reader.ready = false; closing = Promise.resolve().then(() => client.close()); }
    return closing;
  };
  try {
    await reader.initialize();
    return { sourceContext: new ExistingManagementSourceContext({ ledger, reader, registrar, owner, now, monotonic }), close };
  } catch (error) { await close().catch(() => {}); throw error; }
}
