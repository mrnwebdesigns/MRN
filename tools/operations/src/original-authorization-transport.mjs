import { readFileSync } from 'node:fs';
import { z } from 'zod';
import { digest, requireThat, sha } from './contracts.mjs';
import { delegationCanonical, delegationDigest } from './delegation.mjs';
import { createPrivateQueryReader } from './access-control.mjs';
import { compileManagementEvidenceSchema } from './content-management-schema.mjs';
import { payload } from './mainwp.mjs';

const check = value => requireThat(value, 'ORIGINAL_AUTHORIZATION_TRANSPORT',
  'Original authorization readiness or its exact provider response is unavailable; do not retry a bind.');
const wireHash = '8f524002fc5fd900f10e2851d0edd475b4e6ecfe3e6db2a1335573a6e3b7a579';
const raw = readFileSync(new URL('../contracts/content-management-existing-dispatch-v2-proposal.json', import.meta.url));
check(digest(raw) === wireHash);
const wire = JSON.parse(raw), authorization = JSON.parse(readFileSync(new URL('../contracts/content-management-original-authorization-v2.json', import.meta.url)));
for (const [file, hash] of Object.entries(wire.source_dependencies))
  check(/^[a-z0-9-]+\.json$/.test(file) && digest(readFileSync(new URL(`../contracts/${file}`, import.meta.url))) === hash);
const types = Object.fromEntries(Object.keys(wire.$defs).map(name => [name,
  compileManagementEvidenceSchema({ $ref: `#/$defs/${name}`, $defs: wire.$defs })]));
const claimsType = compileManagementEvidenceSchema({ $ref: '#/$defs/claims', $defs: authorization.$defs });
const policyType = z.object({ provider_source_sha: z.string().regex(/^[a-f0-9]{40}$/), authorization_policy_sha256: sha }).strict();
const parse = (schema, value) => { const result = schema.safeParse(value); check(result.success); return result.data; };
const same = (a, b) => delegationDigest(a) === delegationDigest(b);
const tools = Object.fromEntries(Object.entries(wire.machine_abilities).map(([key, value]) => [key, value.name.replace('/', '__').replaceAll('-', '_')]));
const freeze = value => { if (value && typeof value === 'object') { Object.values(value).forEach(freeze); Object.freeze(value); } return value; };
const projectionFields = ['operation_id', 'reviewed_digest', 'qualification_protocol', 'protocol_contracts_sha256',
  'original_delegation_request_id', 'original_resource_authorization_sha256', 'original_resource_authorization_expires_at'];

// Machine-only source adapter. No startup registration, public tool, preview
// selector or admission. Its successful response still needs durable retention
// against the original ledger and later current-access/QA/backup approvals.
export class OriginalAuthorizationTransport {
  #readiness = new WeakMap();
  constructor(client, { policy, now = () => Date.now(), monotonic = () => performance.now() }) {
    check(typeof policy === 'function');
    Object.assign(this, { client, policy, now, monotonic, ready: false });
    this.directories = Object.entries(wire.machine_abilities).map(([kind, value]) =>
      new (createPrivateQueryReader(tools[kind], value.annotations))(client));
  }
  #policy() { return parse(policyType, this.policy()); }
  #fresh(value) {
    const at = Math.floor(this.now() / 1000);
    check(value.observed_at <= at && value.expires_at > at
      && value.expires_at <= value.observed_at + wire.limits.readiness_freshness_seconds);
  }
  #within(start, seconds) { const elapsed = this.monotonic() - start; check(elapsed >= 0 && elapsed < seconds * 1000); }
  async initialize() {
    this.ready = false;
    for (const directory of this.directories) await directory.initialize();
    this.ready = true;
  }
  async status() {
    check(this.ready);
    const policy = this.#policy(), start = this.monotonic(), input = parse(types.readiness_input, { action: 'status' });
    const result = parse(types.readiness_output, payload(await this.client.callTool(
      { name: tools.readiness, arguments: input }, undefined, { timeout: wire.limits.readiness_call_seconds * 1000 })));
    this.#within(start, wire.limits.readiness_call_seconds); this.#fresh(result);
    check(this.ready && same(policy, this.#policy()) && result.provider_source_sha === policy.provider_source_sha
      && result.authorization_policy_sha256 === policy.authorization_policy_sha256 && result.dispatch_contract_sha256 === wireHash
      && Buffer.byteLength(delegationCanonical(result)) <= wire.limits.readiness_output_bytes);
    const value = freeze(result);
    if (value.binding_readiness === 'ready') this.#readiness.set(value, policy);
    return value;
  }
  async #control(kind, value, readiness) {
    check(this.ready);
    const admitted = this.#readiness.get(readiness);
    check(admitted && same(admitted, this.#policy())); this.#fresh(readiness);
    const input = parse(types.authorization_input, value), encoded = input.signed_request.payload_base64;
    check(Buffer.byteLength(delegationCanonical(input)) <= wire.limits.authorization_input_bytes);
    const bytes = Buffer.from(encoded, 'base64');
    check(bytes.length <= wire.limits.authorization_decoded_claims_bytes && bytes.toString('base64') === encoded);
    let decoded; try { decoded = JSON.parse(bytes.toString()); } catch { check(false); }
    const claims = parse(claimsType, decoded), at = Math.floor(this.now() / 1000);
    check(bytes.equals(Buffer.from(delegationCanonical(claims))) && claims.action === `${kind}-existing-management-authorization`
      && claims.provider_source_sha === admitted.provider_source_sha && claims.authorization_policy_sha256 === admitted.authorization_policy_sha256
      && claims.issued_at <= at && claims.expires_at > at && claims.expires_at <= claims.issued_at + wire.limits.control_transport_seconds
      && claims.expires_at <= claims.original_resource_authorization_expires_at);
    // Consume readiness before I/O: an uncertain control requires a new status
    // and a new original-bound signed read, never a second use of this permit.
    this.#readiness.delete(readiness);
    const start = this.monotonic();
    const response = parse(types.authorization_projection, payload(await this.client.callTool(
      { name: tools[kind], arguments: input }, undefined, { timeout: wire.limits.authorization_call_seconds * 1000 })));
    this.#within(start, wire.limits.authorization_call_seconds); this.#fresh(readiness);
    check(this.ready && same(admitted, this.#policy()) && claims.expires_at > Math.floor(this.now() / 1000)
      && Buffer.byteLength(delegationCanonical(response)) <= wire.limits.authorization_output_bytes
      && projectionFields.every(field => same(response[field], claims[field])));
    return freeze(response);
  }
  bind(input, readiness) { return this.#control('bind', input, readiness); }
  read(input, readiness) { return this.#control('read', input, readiness); }
}

export async function createOriginalAuthorizationTransport({ enabled = false, connect, policy, now, monotonic } = {}) {
  check(typeof enabled === 'boolean');
  if (!enabled) return null;
  check(typeof connect === 'function' && typeof policy === 'function');
  const client = await connect(), transport = new OriginalAuthorizationTransport(client, { policy, now, monotonic });
  let closing;
  const close = () => {
    if (!closing) { transport.ready = false; closing = Promise.resolve().then(() => client.close()); }
    return closing;
  };
  try {
    await transport.initialize();
    return { status: () => transport.status(), bind: (input, ready) => transport.bind(input, ready),
      read: (input, ready) => transport.read(input, ready), close };
  } catch (error) { await close().catch(() => {}); throw error; }
}
