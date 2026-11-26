import { readFileSync } from 'node:fs';
import { KeyObject, verify } from 'node:crypto';
import { z } from 'zod';
import { digest, OpsError, requireThat } from './contracts.mjs';
import { delegationCanonical, delegationDigest } from './delegation.mjs';
import { compileManagementEvidenceSchema } from './content-management-schema.mjs';
import { existingManagementWires, validateExistingManagementBinding } from './management-existing-contracts.mjs';
import { existingWorkerContractSha256 } from './existing-management-worker-contract.mjs';

const code = 'MANAGEMENT_EXISTING_BOOTSTRAP_CONTRACT';
const message = 'The protected original bootstrap differs from its independent trust, run or lifetime.';
const need = value => requireThat(value, code, message);
export const existingBootstrapContractSha256 = '2bafd97752fd2596edd1214a7a761d2156ca1f96dd1a65248b3aec1ffe4ea5be';
const bytes = readFileSync(new URL('../contracts/content-management-existing-bootstrap-v2-proposal.json', import.meta.url));
need(digest(bytes) === existingBootstrapContractSha256);
const wire = JSON.parse(bytes);
for (const field of ['production_enabled', 'runtime_registered', 'delivery_enabled', 'source_admission_enabled']) need(wire[field] === false);
need(wire.signature_role === 'registrar');
for (const [file, hash] of Object.entries(wire.frozen_inputs_sha256))
  need(digest(readFileSync(new URL('../contracts/' + file, import.meta.url))) === hash);
const worker = JSON.parse(readFileSync(new URL('../contracts/content-management-existing-worker-v2-proposal.json', import.meta.url)));
for (const name of wire.imported_worker_definitions) need(delegationDigest(wire.$defs[name]) === delegationDigest(worker.$defs[name]));
need(delegationDigest(wire.$defs.worker_policy) === delegationDigest(existingManagementWires.evidence.$defs.policy));
export const existingBootstrapUrl = wire.private_url;
export const existingBootstrapPurposes = Object.freeze({ ...wire.purposes });
const types = Object.fromEntries(['pointer', 'output', 'worker_policy', 'bootstrap_policy', 'commitment', 'observation'].map(name =>
  [name, compileManagementEvidenceSchema({ $ref: '#/$defs/' + name, $defs: wire.$defs })]));
const parse = (name, value) => { const r = types[name].safeParse(value); need(r.success); return r.data; };
export function parseExistingBootstrapRecord(name, value) {
  need(Object.hasOwn(types, name)); return parse(name, value);
}
const same = (a, b) => need(delegationDigest(a) === delegationDigest(b));
const positive = value => Number.isSafeInteger(value) && value > 0;
const id = z.string().regex(/^[1-9][0-9]{0,19}$/), hash = z.string().regex(/^[a-f0-9]{64}$/);
const runType = z.object({ run_id: id, run_attempt: z.literal(1), repository: z.literal('mrnwebdesigns/mrn-code-qa-runner'),
  repository_id: id, repository_owner_id: id, repository_visibility: z.literal('private'), app_actor_id: id,
  workflow_id: id, workflow_path: z.string().max(191).regex(/^\.github\/workflows\/[a-z0-9._-]+\.ya?ml$/),
  workflow_sha256: hash, runner_source_sha: z.string().regex(/^[a-f0-9]{40}$/), ref: z.literal('refs/heads/main'),
  event_name: z.literal('workflow_dispatch'), environment: z.string().min(1).max(255), runner_environment: z.literal('github-hosted') }).strict();
const runPolicyType = z.object({ repository_owner_id: id, environment: z.string().min(1).max(255),
  runner_environment: z.literal('github-hosted') }).strict();
const keyFingerprint = key => {
  need(key instanceof KeyObject && key.type === 'public' && key.asymmetricKeyType === 'ed25519');
  return digest(key.export({ type: 'spki', format: 'der' }));
};
const canonicalObject = (raw, name, maximum) => {
  need(Buffer.isBuffer(raw) && raw.length > 0 && raw.length <= maximum);
  const value = parse(name, JSON.parse(raw.toString('utf8')));
  need(Buffer.from(delegationCanonical(value)).equals(raw)); return value;
};

// Pure consistency candidate, never a bootstrap issuer/delivery or authority
// grant. Policies, public keys, pointer and full current run must already come
// from independent protected supervisor state; no response may install them.
// See deploy/EXISTING-WORKER-BOOTSTRAP-SEMANTICS.md. Durable original retention,
// actual OIDC/Google and consume-before-release remain separate required gates.
export function verifyExistingWorkerBootstrap(input) {
  try {
    need(input && [Object.prototype, null].includes(Object.getPrototypeOf(input)) && !Object.getOwnPropertySymbols(input).length
      && Object.values(Object.getOwnPropertyDescriptors(input)).every(d => Object.hasOwn(d, 'value'))
      && Object.keys(input).sort().join(',') === ['raw', 'pointer', 'bootstrapPolicy', 'bootstrapPolicySha256',
      'workerPolicy', 'registrarPublicKey', 'identityPublicKey', 'run', 'runPolicy', 'startedAt', 'completedAt'].sort().join(','));
    const { raw, registrarPublicKey, identityPublicKey, startedAt, completedAt } = input;
    need(positive(startedAt) && positive(completedAt) && completedAt >= startedAt && completedAt - startedAt < 5000);
    const pointer = parse('pointer', input.pointer), policy = parse('bootstrap_policy', input.bootstrapPolicy);
    const expectedWorker = parse('worker_policy', input.workerPolicy), run = runType.parse(input.run), runPolicy = runPolicyType.parse(input.runPolicy);
    need(Buffer.byteLength(delegationCanonical(pointer)) <= wire.limits.pointer_bytes);
    need(hash.safeParse(input.bootstrapPolicySha256).success && delegationDigest(policy) === input.bootstrapPolicySha256
      && policy.contract_sha256 === existingBootstrapContractSha256 && policy.worker_policy_sha256 === delegationDigest(expectedWorker)
      && policy.run_policy_sha256 === delegationDigest(runPolicy));
    const registrarHash = keyFingerprint(registrarPublicKey), identityHash = keyFingerprint(identityPublicKey);
    need(registrarHash === policy.registrar_public_key_sha256 && identityHash === policy.identity_public_key_sha256
      && registrarHash !== identityHash && policy.registrar_key_id !== policy.identity_key_id);
    need(run.repository_owner_id === runPolicy.repository_owner_id && run.environment === runPolicy.environment
      && run.runner_environment === runPolicy.runner_environment && run.app_actor_id === policy.app_actor_id);
    for (const key of ['repository', 'repository_id', 'workflow_id', 'workflow_path', 'workflow_sha256', 'runner_source_sha', 'ref'])
      need(run[key] === expectedWorker[key]);
    const output = canonicalObject(raw, 'output', wire.limits.response_bytes);
    const open = name => {
      const envelope = output[name], payload = Buffer.from(envelope.payload_base64, 'base64');
      const signature = Buffer.from(envelope.signature_base64, 'base64');
      need(envelope.key_id === policy.registrar_key_id && payload.toString('base64') === envelope.payload_base64
        && signature.length === 64 && signature.toString('base64') === envelope.signature_base64
        && payload.length > 0 && payload.length <= wire.limits[name + '_bytes']
        && verify(null, Buffer.concat([Buffer.from(wire.purposes[name]), payload]), registrarPublicKey, signature));
      const claims = canonicalObject(payload, name, wire.limits[name + '_bytes']);
      need(claims.bootstrap_contract_sha256 === existingBootstrapContractSha256 && claims.bootstrap_policy_sha256 === input.bootstrapPolicySha256);
      return claims;
    };
    const commitment = open('commitment'), observation = open('observation'), retained = observation.retained;
    const { context, provenance: p, observation: o } = retained, b = context.binding;
    // Binding shape/protocol is checked here. Its origin is only attested by
    // the distinct registrar signature; this is not independent SQL proof.
    validateExistingManagementBinding(b, b);
    same(commitment.context, context); same(commitment.request, o.request);
    need(context.management_context_sha256 === delegationDigest(b)
      && context.evidence_policy_epoch === expectedWorker.policy_epoch && context.evidence_policy_sha256 === delegationDigest(expectedWorker)
      && commitment.worker_contract_sha256 === existingWorkerContractSha256 && o.request.worker_contract_sha256 === existingWorkerContractSha256
      && o.request.context_sha256 === pointer.context_sha256 && pointer.context_sha256 === delegationDigest(context)
      && o.request.dispatch_id === pointer.dispatch_id && p.dispatch_id === pointer.dispatch_id
      && o.context_sha256 === pointer.context_sha256 && o.evidence_policy_sha256 === context.evidence_policy_sha256
      && p.dispatch_request_sha256 === delegationDigest(o.request) && p.dispatch_record_sha256 === delegationDigest(o)
      && observation.commitment_sha256 === delegationDigest(output.commitment));
    for (const key of Object.keys(p).filter(key => !['dispatch_id', 'dispatch_request_sha256', 'dispatch_record_sha256', 'run_id', 'run_attempt'].includes(key)))
      need(p[key] === expectedWorker[key]);
    need(p.run_id === run.run_id && o.run_id === run.run_id && p.run_attempt === run.run_attempt
      && o.app_actor_id === policy.app_actor_id && commitment.app_actor_id === o.app_actor_id
      && observation.audience === `https://github.com/${run.repository}/actions/runs/${run.run_id}`
      && commitment.requested_at === o.requested_at && commitment.original_expires_at === o.original_expires_at
      && o.original_expires_at === b.expires_at * 1000 && b.created_at * 1000 <= o.requested_at
      && o.requested_at <= o.observed_at && o.observed_at <= completedAt
      && completedAt < Math.min(o.original_expires_at, o.requested_at + 300000));
    return { state: 'existing-bootstrap-signatures-consistent', pointer, retained, originalBinding: b,
      commitment_sha256: observation.commitment_sha256, worker_history_sha256: observation.worker_history_sha256,
      bootstrap_policy_sha256: input.bootstrapPolicySha256, bootstrap_contract_sha256: existingBootstrapContractSha256,
      response_sha256: digest(raw), receipt: { started_at_ms: startedAt, completed_at_ms: completedAt },
      original_retained: false, delivery_consumed: false, current_access_proven: false, hosted_oidc_proven: false,
      live_google_proven: false, source_verified: false, admitted: false };
  } catch { throw new OpsError(code, message); }
}
