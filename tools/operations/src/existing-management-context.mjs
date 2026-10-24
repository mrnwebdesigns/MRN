import { randomBytes, randomUUID } from 'node:crypto';
import { requireThat } from './contracts.mjs';
import { delegationCanonical, delegationDigest } from './delegation.mjs';
import { createPrivateQueryReader } from './access-control.mjs';
import { payload } from './mainwp.mjs';
import { ManagementEvidenceSigner } from './management-evidence-callback.mjs';
import { existingManagementTypes, existingManagementWires } from './management-existing-contracts.mjs';
import { OriginalManagementAuthorizationLedger } from './original-management-authorization.mjs';

const wire = existingManagementWires.qualification, types = existingManagementTypes.qualification;
const check = value => requireThat(value, 'MANAGEMENT_EXISTING_CONTEXT', 'The original queued qualification context is unavailable or changed.');
const parse = (schema, value) => { const result = schema.safeParse(value); check(result.success); return result.data; };
const same = (a, b) => delegationDigest(a) === delegationDigest(b);
export const existingManagementContextTool = 'mrn_mainwp__get_content_edit_qualification_context_existing_v2';

export class ExistingManagementContextReader extends createPrivateQueryReader(existingManagementContextTool) {
  async read(envelope) {
    check(this.ready);
    const input = parse(types.signed_input, { signed_request: envelope });
    const sentDigest = delegationDigest(input);
    const result = parse(types.context, payload(await this.client.callTool({ name: existingManagementContextTool, arguments: input },
      undefined, { timeout: wire.limits.read_seconds * 1000 })));
    check(delegationDigest(input) === sentDigest && Buffer.byteLength(delegationCanonical(result)) <= wire.limits.response_bytes); return result;
  }
}

// The registrar signs an existing original, never a caller-supplied binding or
// email. The provider independently checks it and makes the current-access
// callback through a separate warmed identity connection before returning.
export class ExistingManagementContext {
  constructor({ ledger, reader, registrar, now = () => Date.now(), monotonic = () => performance.now() }) {
    check(ledger instanceof OriginalManagementAuthorizationLedger && reader instanceof ExistingManagementContextReader);
    Object.assign(this, { ledger, reader, now, monotonic });
    this.signer = new ManagementEvidenceSigner({ ...registrar, now });
  }
  async read(jobId) {
    const started = this.monotonic(), startedAt = this.now(), limit = wire.limits.read_seconds * 1000;
    const within = () => check(this.monotonic() >= started && this.monotonic() - started < limit
      && this.now() >= startedAt && this.now() - startedAt < limit);
    const bounded = async perform => {
      within(); let timer;
      try {
        const result = await Promise.race([Promise.resolve().then(perform), new Promise((_, reject) => {
          timer = setTimeout(() => reject(new Error('MANAGEMENT_EXISTING_CONTEXT_TIMEOUT')),
            Math.max(1, limit - (this.monotonic() - started)));
        })]);
        within(); return result;
      } finally { clearTimeout(timer); }
    };
    const original = await bounded(() => this.ledger.currentQueuedJob(jobId)), queued = original.queue.context;
    const issued = Math.floor(this.now() / 1000), expiry = Math.min(queued.binding.expires_at,
      Math.floor(original.original.mapping.authorization.expiresAt / 1000), issued + wire.limits.read_seconds);
    check(expiry > issued && issued >= queued.binding.created_at);
    const claims = parse(types.worker_claims, { schema: 2, issuer: 'mrn-content-qualification', audience: 'https://wpcontrol.mrndev.io',
      issued_at: issued, expires_at: expiry, request_id: randomUUID(), nonce: randomBytes(32).toString('hex'), action: 'context',
      binding: queued.binding, binding_sha256: queued.binding_sha256 });
    const input = parse(types.signed_input, { signed_request: this.signer.envelope(claims, wire.purposes.context) });
    const inputDigest = delegationDigest(input);
    const output = await bounded(() => this.reader.read(input.signed_request));
    check(same(output, queued) && delegationDigest(input) === inputDigest && expiry * 1000 > this.now());
    const current = await bounded(() => this.ledger.currentQueuedJob(jobId));
    check(current.original.digest === original.original.digest && current.revision === original.revision
      && current.queue.digest === original.queue.digest && same(current.queue.context, queued) && expiry * 1000 > this.now());
    return output;
  }
}

// Prepared private composition only; production startup never imports this.
export async function createExistingManagementContextRuntime({ enabled = false, ledger, registrar, connect, now, monotonic } = {}) {
  check(typeof enabled === 'boolean'); if (!enabled) return null;
  check(typeof connect === 'function');
  const client = await connect(), reader = new ExistingManagementContextReader(client);
  let closing;
  const close = () => {
    if (!closing) { reader.ready = false; closing = Promise.resolve().then(() => client.close()); }
    return closing;
  };
  try {
    await reader.initialize();
    const context = new ExistingManagementContext({ ledger, reader, registrar, now, monotonic });
    return { context, close };
  } catch (error) { await close().catch(() => {}); throw error; }
}
