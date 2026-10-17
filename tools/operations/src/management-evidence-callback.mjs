import { randomBytes, randomUUID, sign } from 'node:crypto';
import { requireThat } from './contracts.mjs';
import { delegationCanonical, delegationDigest, delegationIssuer, delegationAudience } from './delegation.mjs';
import { createPrivateQueryReader } from './access-control.mjs';
import { managementEvidenceTypes as types, managementEvidenceWire as wire } from './management-evidence-contract.mjs';
import { downloadManagementEvidence } from './management-evidence-download.mjs';
import { ManagementEvidenceLedger } from './management-evidence-store.mjs';

export const managementEvidencePath = '/internal/mainwp/management-evidence';
export const managementEvidenceReadbackTool = 'mrn_mainwp__read_management_evidence_query_v1';
export const ManagementEvidenceQueryReader = createPrivateQueryReader(managementEvidenceReadbackTool);
const check = (value, message) => requireThat(value, 'MANAGEMENT_EVIDENCE_CALLBACK', message);
const parse = (schema, value) => {
  const result = schema.safeParse(value); check(result.success, 'Invalid private evidence callback contract.'); return result.data;
};
check(wire.callback_url === delegationIssuer + managementEvidencePath, 'Use only the fixed MRN evidence callback.');

export class ManagementEvidenceSigner {
  constructor({ keyId, privateKey, now = () => Date.now() }) {
    check(/^[A-Za-z0-9_-]{1,64}$/.test(keyId) && privateKey?.asymmetricKeyType === 'ed25519', 'The configured gateway identity signing key is required.');
    Object.assign(this, { keyId, privateKey, now });
  }
  envelope(value, purpose) {
    const bytes = Buffer.from(delegationCanonical(value));
    return { key_id: this.keyId, payload_base64: bytes.toString('base64'),
      signature_base64: sign(null, Buffer.concat([Buffer.from(purpose), bytes]), this.privateKey).toString('base64') };
  }
  base(expiresAt = Infinity) {
    const at = Math.floor(this.now() / 1000);
    check(expiresAt > at, 'The original evidence observation expired.');
    return { schema: 1, issuer: delegationIssuer, audience: delegationAudience, issued_at: at,
      expires_at: Math.min(at + 5, expiresAt), request_id: randomUUID(), nonce: randomBytes(32).toString('hex') };
  }
  readback(pointer) {
    const claims = parse(types.query_readback_claims, { ...this.base(), ...parse(types.query_pointer, pointer), action: 'read-management-evidence-query' });
    return { claims, envelope: this.envelope(claims, wire.purposes.query_readback) };
  }
  acquisition(lease, original, observation) {
    const claims = parse(types.acquisition_claims, { ...this.base(Math.min(lease.query.expires_at, Math.floor(original.record.originalExpiresAt / 1000))),
      action: 'observe-management-evidence-acquisition', query_id: lease.query.query_id,
      provider_query_nonce: lease.query.provider_query_nonce, query_sha256: lease.queryDigest,
      context_sha256: delegationDigest(original.context), recorded_dispatch: original.provenance,
      observed_run: observation.observed_run, qualification_artifact: observation.qualification_artifact,
      acquisition_artifact: observation.acquisition_artifact, started_at: observation.started_at, completed_at: observation.completed_at });
    const response = parse(types.acquisition_response, { signed_acquisition: this.envelope(claims, wire.purposes.acquisition),
      qualification_zip_base64: observation.qualification_zip.toString('base64'), acquisition_zip_base64: observation.acquisition_zip.toString('base64') });
    check(Buffer.byteLength(JSON.stringify(response)) <= wire.limits.http_response_bytes
      && claims.expires_at > Math.floor(this.now() / 1000), 'The bounded evidence response expired before return.');
    return response;
  }
}

// Explicit opt-in only; main.mjs does not construct or register this handler.
// The opaque caller pointer cannot choose an actor, run, repository, key or URL.
// These signatures describe independently downloaded bytes, not QA admission.
export class ManagementEvidenceCallback {
  constructor({ signer, reader, ledger, github, verifyStaff, now = () => Date.now(), monotonic = () => performance.now() }) {
    check(typeof verifyStaff === 'function', 'Fresh original-staff verification is required for evidence acquisition.');
    Object.assign(this, { signer, reader, ledger, github, verifyStaff, now, monotonic });
  }
  async respond(input) {
    const pointer = parse(types.query_pointer, input), start = this.monotonic(), startAt = this.now();
    const within = () => check(this.monotonic() >= start && this.monotonic() - start < 60000 && this.now() >= startAt,
      'The single evidence callback deadline expired.');
    const bounded = async (perform, limit) => {
      within(); let timer;
      try {
        return await Promise.race([Promise.resolve().then(perform), new Promise((_, reject) => {
          timer = setTimeout(() => {
            try { check(false, 'The original authenticated evidence check exceeded its deadline.'); } catch (error) { reject(error); }
          }, Math.max(1, Math.min(limit, 60000 - (this.monotonic() - start))));
        })]);
      } finally { clearTimeout(timer); }
    };
    const { claims, envelope } = this.signer.readback(pointer);
    const result = parse(types.query_readback_output, await bounded(() => this.reader.read(envelope), 2500));
    within();
    check(claims.issued_at <= Math.floor(this.now() / 1000) && claims.expires_at > Math.floor(this.now() / 1000)
      && result.query.query_id === pointer.query_id && result.query.provider_query_nonce === pointer.provider_query_nonce
      && result.query_sha256 === delegationDigest(result.query), 'Authenticated readback does not match the original query.');
    const lease = this.ledger.begin(result.query);
    const authorize = async () => {
      within(); const original = this.ledger.current(lease);
      await bounded(() => this.verifyStaff(original.mapping.actor), Math.min(5000, original.record.originalExpiresAt - this.now()));
      within(); this.ledger.current(lease);
    };
    await authorize();
    const original = this.ledger.current(lease);
    const observation = await downloadManagementEvidence({ github: this.github, record: original.record, authorize, now: this.now });
    await authorize();
    const response = this.ledger.finish(lease, current => { within(); return this.signer.acquisition(lease, current, observation); });
    within();
    check(original.record.originalExpiresAt > this.now() && lease.query.expires_at > Math.floor(this.now() / 1000),
      'The original evidence intent expired before return.');
    return response;
  }
}

// Prepared lifecycle only. Startup still supplies no configuration or route;
// source/native qualification and concrete production approval are separate.
export async function createManagementEvidenceRuntime({ enabled = false, access, policy, appActorId,
  identity, github, verifyStaff, connect } = {}) {
  check(typeof enabled === 'boolean', 'Evidence runtime activation must be explicit.');
  if (!enabled) return null;
  const signer = new ManagementEvidenceSigner(identity || {});
  const ledger = new ManagementEvidenceLedger(access, { policy, appActorId });
  const client = await connect(), reader = new ManagementEvidenceQueryReader(client);
  let closing = false, closePromise;
  const close = () => {
    if (!closePromise) { closing = true; reader.ready = false; closePromise = Promise.resolve().then(() => client.close()); }
    return closePromise;
  };
  try {
    await reader.initialize();
    const handler = new ManagementEvidenceCallback({ signer, reader, ledger, github, verifyStaff });
    return { ledger, close, callback: { async respond(input) {
      check(!closing, 'The private evidence reader is closed.');
      const result = await handler.respond(input);
      check(!closing, 'The private evidence reader closed before return.'); return result;
    } } };
  } catch (error) { await close().catch(() => {}); throw error; }
}
