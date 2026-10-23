import { readFileSync } from 'node:fs';
import { verify } from 'node:crypto';
import { digest, OpsError, requireThat } from './contracts.mjs';
import { delegationCanonical, delegationDigest } from './delegation.mjs';
import { compileManagementEvidenceSchema } from './content-management-schema.mjs';
import { existingManagementWires, validateExistingManagementBinding } from './management-existing-contracts.mjs';

const code = 'MANAGEMENT_EXISTING_WORKER_CONTRACT';
const message = 'The original protected worker record is unavailable, changed or expired.';
const need = value => requireThat(value, code, message);
export const existingWorkerContractSha256 = '8881911cea6435b470f3776aab29a200f54015828ee2d53589abbe1b4810247d';
const bytes = readFileSync(new URL('../contracts/content-management-existing-worker-v2-proposal.json', import.meta.url));
need(digest(bytes) === existingWorkerContractSha256);
const wire = JSON.parse(bytes);
need(wire.production_enabled === false && wire.runtime_registered === false && wire.dispatch_enabled === false
  && wire.source_admission_enabled === false && wire.signature_role === 'identity');
for (const [file, hash] of Object.entries(wire.frozen_inputs_sha256))
  need(digest(readFileSync(new URL('../contracts/' + file, import.meta.url))) === hash);
for (const name of wire.imported_definitions)
  need(delegationDigest(wire.$defs[name]) === delegationDigest(existingManagementWires.evidence.$defs[name]));
export const existingWorkerPurpose = wire.purpose;
export const existingWorkerUrl = wire.private_url;
const types = Object.fromEntries(['pointer', 'dispatch_request', 'dispatch_observation', 'retained_record', 'claims', 'output'].map(name => [name,
  compileManagementEvidenceSchema({ $ref: '#/$defs/' + name, $defs: wire.$defs })]));
export const parseExistingWorkerRecord = (name, input) => { const result = types[name].safeParse(input); need(result.success); return result.data; };
const parse = parseExistingWorkerRecord;
const same = (a, b) => need(delegationDigest(a) === delegationDigest(b));
const positive = value => Number.isSafeInteger(value) && value > 0;

// Consistency verification only. The retained record, original binding, current
// identity public key and receipt times must come from independent service or
// supervisor state. No input grants authority or installs a key. This module
// registers no route, records no dispatch and makes no network/source request.
export function verifyExistingManagementWorkerRecord({ raw, pointer, retained, originalBinding,
  identityKeyId, identityPublicKey, startedAt, completedAt }) {
  try {
    need(Buffer.isBuffer(raw) && raw.length > 0 && raw.length <= wire.limits.response_bytes
      && positive(startedAt) && positive(completedAt) && completedAt >= startedAt
      && completedAt - startedAt < wire.limits.read_seconds * 1000);
    const requested = parse('pointer', pointer), expected = parse('retained_record', retained);
    need(Buffer.byteLength(delegationCanonical(requested)) <= wire.limits.pointer_bytes);
    validateExistingManagementBinding(expected.context.binding, originalBinding);
    need(typeof identityKeyId === 'string' && /^[A-Za-z0-9_-]{1,64}$/.test(identityKeyId)
      && identityPublicKey?.type === 'public' && identityPublicKey.asymmetricKeyType === 'ed25519');
    const outer = parse('output', JSON.parse(raw.toString('utf8')));
    need(Buffer.from(delegationCanonical(outer)).equals(raw));
    const envelope = outer.signed_context;
    const payload = Buffer.from(envelope.payload_base64, 'base64'), signature = Buffer.from(envelope.signature_base64, 'base64');
    need(envelope.key_id === identityKeyId && payload.length > 0 && payload.length <= wire.limits.claims_bytes
      && payload.toString('base64') === envelope.payload_base64 && signature.length === 64
      && signature.toString('base64') === envelope.signature_base64
      && verify(null, Buffer.concat([Buffer.from(existingWorkerPurpose), payload]), identityPublicKey, signature));
    const claims = parse('claims', JSON.parse(payload.toString('utf8')));
    need(Buffer.from(delegationCanonical(claims)).equals(payload));
    same(claims.context, expected.context); same(claims.recorded_dispatch, expected.provenance);
    same(claims.dispatch_record, expected.observation);
    const p = claims.recorded_dispatch, o = claims.dispatch_record, b = claims.context.binding;
    need(claims.worker_contract_sha256 === existingWorkerContractSha256
      && o.request.worker_contract_sha256 === existingWorkerContractSha256
      && claims.dispatch_id === requested.dispatch_id && p.dispatch_id === requested.dispatch_id
      && o.request.dispatch_id === requested.dispatch_id && claims.context_sha256 === requested.context_sha256
      && requested.context_sha256 === delegationDigest(claims.context)
      && o.context_sha256 === requested.context_sha256 && o.request.context_sha256 === requested.context_sha256
      && claims.context.management_context_sha256 === delegationDigest(b)
      && o.evidence_policy_sha256 === claims.context.evidence_policy_sha256
      && p.dispatch_request_sha256 === delegationDigest(o.request) && p.dispatch_record_sha256 === delegationDigest(o)
      && p.repository === 'mrnwebdesigns/mrn-code-qa-runner' && p.run_id === o.run_id
      && claims.audience === `https://github.com/${p.repository}/actions/runs/${p.run_id}`);
    need(claims.expires_at > claims.issued_at && claims.expires_at - claims.issued_at <= wire.limits.read_seconds
      && claims.issued_at >= Math.floor(startedAt / 1000) && claims.issued_at <= Math.floor(completedAt / 1000)
      && claims.expires_at * 1000 > completedAt && claims.expires_at * 1000 <= o.original_expires_at
      && b.created_at * 1000 <= o.requested_at && o.requested_at <= o.observed_at
      && o.observed_at <= completedAt && o.observed_at < (claims.issued_at + 1) * 1000
      && completedAt < o.requested_at + wire.limits.worker_ttl_seconds * 1000
      && claims.expires_at * 1000 <= o.requested_at + wire.limits.worker_ttl_seconds * 1000
      && o.original_expires_at > completedAt && o.original_expires_at <= b.expires_at * 1000);
    return { state: 'existing-worker-record-consistent', context: claims.context,
      recorded_dispatch: p, dispatch_record: o, current_access_proven: false,
      hosted_oidc_proven: false, source_verified: false, admitted: false,
      readback: { request_id: claims.request_id, issued_at: claims.issued_at, expires_at: claims.expires_at,
        started_at_ms: startedAt, completed_at_ms: completedAt, response_sha256: digest(raw),
        payload_sha256: digest(payload), worker_contract_sha256: existingWorkerContractSha256 } };
  } catch { throw new OpsError(code, message); }
}
