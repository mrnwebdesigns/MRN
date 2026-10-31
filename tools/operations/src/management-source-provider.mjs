import { readFileSync } from 'node:fs';
import { randomBytes, randomUUID } from 'node:crypto';
import { digest, requireThat } from './contracts.mjs';
import { delegationCanonical, delegationDigest } from './delegation.mjs';
import { compileManagementEvidenceSchema } from './content-management-schema.mjs';
import { createPrivateQueryReader } from './access-control.mjs';
import { payload } from './mainwp.mjs';
import { ManagementEvidenceLedger } from './management-evidence-store.mjs';
import { ManagementEvidenceSigner } from './management-evidence-callback.mjs';
import { ManagementSourceCapture } from './management-source-capture.mjs';
import { managementSourceWire } from './management-source-transfer.mjs';

const check = value => requireThat(value, 'MANAGEMENT_SOURCE_PROVIDER',
  'The original authenticated MainWP source context is unavailable or changed.');
const bytes = readFileSync(new URL('../contracts/content-management-source-context-v1.json', import.meta.url));
check(digest(bytes) === 'd114207fef098af71299e3c5b5a6b22f3ef36b55115b56efb03bb0d4bcab5418');
const wire = JSON.parse(bytes);
check(wire.source_transfer_contract_sha256 === '36320f6d2d5dac5523ecc68b0fa463c2fa1246cbf75f81e3209c055aa196f3e9');
const compile = (contract, name) => compileManagementEvidenceSchema({ $ref: `#/$defs/${name}`, $defs: contract.$defs });
const types = Object.fromEntries(['claims', 'input', 'output'].map(name => [name, compile(wire, name)]));
const captureInput = compile(managementSourceWire, 'capture_input');
const captureOutput = compile(managementSourceWire, 'capture_output');
const parse = (schema, value) => { const result = schema.safeParse(value); check(result.success); return result.data; };
const same = (left, right) => delegationDigest(left) === delegationDigest(right);
const contextTool = 'mrn_mainwp__read_content_edit_qualification_source_context_v1';
const captureTool = 'mrn_mainwp__capture_content_edit_qualification_source_v1';

// These are source-selected private machine routes. Inherited initialization
// requires the canonical Dashboard and POST-only, non-destructive annotations.
// No public input can choose a tool, timeout, backend account or transport.
export class ManagementSourceContextReader extends createPrivateQueryReader(contextTool) {
  async read(envelope) {
    check(this.ready);
    const input = parse(types.input, { signed_request: envelope });
    check(Buffer.byteLength(delegationCanonical(input)) <= wire.limits.request_bytes);
    const output = payload(await this.client.callTool({ name: contextTool, arguments: input }, undefined,
      { timeout: wire.limits.lookup_deadline_seconds * 1000 }));
    const result = parse(types.output, output);
    check(Buffer.byteLength(delegationCanonical(result)) <= wire.limits.response_bytes);
    return result;
  }
}

export class ManagementSourceCaptureReader extends createPrivateQueryReader(captureTool) {
  async capture(value) {
    check(this.ready);
    const input = parse(captureInput, value);
    check(Buffer.byteLength(delegationCanonical(input)) <= managementSourceWire.limits.capture_input_bytes);
    const result = parse(captureOutput, payload(await this.client.callTool({ name: captureTool, arguments: input }, undefined,
      { timeout: managementSourceWire.limits.capture_seconds * 1000 })));
    check(Buffer.byteLength(delegationCanonical(result)) <= managementSourceWire.limits.private_response_bytes);
    return result;
  }
}

// Resolve only from the immutable original ledger. Provider-returned fields
// cannot replace the original actor, site, source, policy or human expiry.
export class ManagementSourceBootstrap {
  constructor({ ledger, reader, registrar, verifyStaff, now = () => Date.now(), monotonic = () => performance.now() }) {
    check(ledger instanceof ManagementEvidenceLedger && typeof reader?.read === 'function' && typeof verifyStaff === 'function');
    Object.assign(this, { ledger, reader, verifyStaff, now, monotonic });
    this.signer = new ManagementEvidenceSigner({ ...registrar, now });
  }
  async read(jobId) {
    const original = this.ledger.captured(jobId), startedAt = this.now(), started = this.monotonic();
    const expiry = Math.min(original.mapping.actor.expiresAt, original.context.binding.expires_at * 1000);
    const current = () => {
      const value = this.ledger.captured(jobId);
      check(same(value.context, original.context) && this.now() >= startedAt && this.now() < expiry
        && this.now() - startedAt < 20000 && this.monotonic() >= started && this.monotonic() - started < 20000);
      return value;
    };
    const bounded = async perform => {
      current(); let timer;
      try {
        const result = await Promise.race([Promise.resolve().then(perform), new Promise((_, reject) => {
          timer = setTimeout(() => reject(new Error('MANAGEMENT_SOURCE_CONTEXT_TIMEOUT')),
            Math.max(1, Math.min(20000 - (this.monotonic() - started), expiry - this.now())));
        })]);
        current(); return result;
      } finally { clearTimeout(timer); }
    };
    await bounded(() => this.verifyStaff(current().mapping.actor));
    const issued = Math.floor(this.now() / 1000);
    const claims = parse(types.claims, { schema: 1, issuer: 'mrn-content-qualification', audience: 'https://wpcontrol.mrndev.io',
      issued_at: issued, expires_at: Math.min(issued + 20, Math.floor(expiry / 1000)), request_id: randomUUID(),
      nonce: randomBytes(32).toString('hex'), action: 'read-management-source-context', qualification_job_id: jobId,
      management_context_sha256: original.context.management_context_sha256 });
    check(claims.issued_at >= original.context.binding.created_at && claims.expires_at > issued);
    const input = parse(types.input, { signed_request: this.signer.envelope(claims, wire.purpose) });
    const sentDigest = delegationDigest(input);
    const result = parse(types.output, await bounded(() => this.reader.read(input.signed_request)));
    check(delegationDigest(input) === sentDigest && result.request_sha256 === sentDigest
      && result.source_context_sha256 === delegationDigest(result.source_context)
      && same(result.source_context.management, original.context));
    await bounded(() => this.verifyStaff(current().mapping.actor));
    current(); check(claims.expires_at * 1000 > this.now());
    return result.source_context;
  }
}

// Prepared lifecycle only: main.mjs does not import or construct this runtime.
// The connection is separate from the warmed current-access callback reader.
export async function createManagementSourceProvider({ enabled = false, ledger, catalogs, registrar,
  verifyStaff, connect, now, monotonic } = {}) {
  check(typeof enabled === 'boolean');
  if (!enabled) return null;
  check(typeof connect === 'function');
  const client = await connect(), context = new ManagementSourceContextReader(client), native = new ManagementSourceCaptureReader(client);
  let closed = false, closing;
  const close = () => {
    if (!closing) { closed = true; context.ready = false; native.ready = false; closing = Promise.resolve().then(() => client.close()); }
    return closing;
  };
  try {
    await context.initialize(); await native.initialize();
    const bootstrap = new ManagementSourceBootstrap({ ledger, reader: context, registrar, verifyStaff, now, monotonic });
    const capture = new ManagementSourceCapture({ ledger, catalogs, contextReader: bootstrap,
      captureReader: native, registrar, verifyStaff, now, monotonic });
    return { capture, close, async prepare(jobId) {
      check(!closed); const value = await capture.capture(jobId); check(!closed); return value;
    } };
  } catch (error) { await close().catch(() => {}); throw error; }
}
