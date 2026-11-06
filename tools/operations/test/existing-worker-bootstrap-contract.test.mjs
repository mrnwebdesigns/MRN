import test from 'node:test';
import assert from 'node:assert/strict';
import { generateKeyPairSync, sign } from 'node:crypto';
import { readFileSync } from 'node:fs';
import { digest } from '../src/contracts.mjs';
import { delegationCanonical as canonical, delegationDigest as hash } from '../src/delegation.mjs';
import { existingWorkerContractSha256 } from '../src/existing-management-worker-contract.mjs';
import { existingBootstrapContractSha256, existingBootstrapPurposes, existingBootstrapUrl,
  verifyExistingWorkerBootstrap } from '../src/existing-worker-bootstrap-contract.mjs';

const fixture = () => {
  const vectors = JSON.parse(readFileSync(new URL('./fixtures/content-management-existing-v2.json', import.meta.url)));
  const binding = structuredClone(vectors.retained_original_binding), registrar = generateKeyPairSync('ed25519'), identity = generateKeyPairSync('ed25519');
  const policy = { schema: 1, enabled: false, policy_epoch: 1, qa_key_ids: ['fixture-qa'],
    repository: 'mrnwebdesigns/mrn-code-qa-runner', repository_id: '10', ref: 'refs/heads/main', workflow_id: '20',
    workflow_path: '.github/workflows/management.yml', workflow_sha256: 'a'.repeat(64), runner_source_sha: 'b'.repeat(40),
    engine_commit: 'c'.repeat(40), engine_configuration_sha256: 'd'.repeat(64), runtime_sha256: 'e'.repeat(64),
    performance_budget_sha256: 'f'.repeat(64), render_assertions_sha256: '1'.repeat(64), hook_policy_sha256: '2'.repeat(64) };
  const runPolicy = { repository_owner_id: '99', environment: 'MRN QA', runner_environment: 'github-hosted' };
  const publicHash = key => digest(key.export({ format: 'der', type: 'spki' }));
  const bootstrap = { schema: 2, policy_epoch: 1, contract_sha256: existingBootstrapContractSha256,
    worker_policy_sha256: hash(policy), run_policy_sha256: hash(runPolicy), app_actor_id: '40',
    registrar_key_id: 'fixture-bootstrap-registrar', registrar_public_key_sha256: publicHash(registrar.publicKey),
    identity_key_id: 'fixture-identity', identity_public_key_sha256: publicHash(identity.publicKey) };
  const context = { binding, management_context_sha256: hash(binding), capture_artifact_sha256: '3'.repeat(64),
    measurement_record_sha256: '4'.repeat(64), parity_sha256: '5'.repeat(64), evidence_policy_epoch: 1, evidence_policy_sha256: hash(policy) };
  const pointer = { dispatch_id: '44444444-4444-4444-8444-444444444444', context_sha256: hash(context) };
  const at = binding.created_at * 1000 + 10000;
  const originalObservation = { request: { schema: 2, ...pointer, worker_contract_sha256: existingWorkerContractSha256 },
    context_sha256: pointer.context_sha256, evidence_policy_sha256: hash(policy), requested_at: at - 4000,
    observed_at: at - 3000, original_expires_at: binding.expires_at * 1000, app_actor_id: '40', run_id: '30' };
  const provenance = { ...Object.fromEntries(['repository', 'repository_id', 'ref', 'workflow_id', 'workflow_path', 'workflow_sha256',
    'runner_source_sha', 'engine_commit', 'engine_configuration_sha256', 'runtime_sha256'].map(k => [k, policy[k]])),
  dispatch_id: pointer.dispatch_id, dispatch_request_sha256: hash(originalObservation.request), dispatch_record_sha256: hash(originalObservation),
  run_id: '30', run_attempt: 1 };
  const commitment = { schema: 2, issuer: 'https://operations.mrnwebdesigns.com', audience: existingBootstrapUrl,
    bootstrap_contract_sha256: existingBootstrapContractSha256, bootstrap_policy_sha256: hash(bootstrap), worker_contract_sha256: existingWorkerContractSha256,
    context, request: structuredClone(originalObservation.request), requested_at: originalObservation.requested_at,
    original_expires_at: originalObservation.original_expires_at, app_actor_id: '40' };
  const observation = { schema: 2, issuer: 'https://operations.mrnwebdesigns.com',
    audience: 'https://github.com/mrnwebdesigns/mrn-code-qa-runner/actions/runs/30',
    bootstrap_contract_sha256: existingBootstrapContractSha256, bootstrap_policy_sha256: hash(bootstrap), commitment_sha256: '',
    worker_history_sha256: '6'.repeat(64), retained: { context: structuredClone(context), provenance, observation: originalObservation } };
  const input = { pointer, bootstrapPolicy: bootstrap, bootstrapPolicySha256: hash(bootstrap), workerPolicy: policy,
    registrarPublicKey: registrar.publicKey, identityPublicKey: identity.publicKey, runPolicy,
    run: { ...Object.fromEntries(['repository', 'repository_id', 'workflow_id', 'workflow_path', 'workflow_sha256', 'runner_source_sha', 'ref'].map(k => [k, policy[k]])),
      ...runPolicy, run_id: '30', run_attempt: 1, repository_visibility: 'private', app_actor_id: '40', event_name: 'workflow_dispatch' },
    startedAt: at, completedAt: at + 500 };
  const envelope = (value, name, key = registrar.privateKey, purpose = existingBootstrapPurposes[name]) => {
    const payload = Buffer.isBuffer(value) ? value : Buffer.from(canonical(value));
    return { key_id: bootstrap.registrar_key_id, payload_base64: payload.toString('base64'),
      signature_base64: sign(null, Buffer.concat([Buffer.from(purpose), payload]), key).toString('base64') };
  };
  const output = () => {
    const signed = envelope(commitment, 'commitment'); observation.commitment_sha256 = hash(signed);
    return { commitment: signed, observation: envelope(observation, 'observation') };
  };
  const read = (raw = Buffer.from(canonical(output()))) => verifyExistingWorkerBootstrap({ ...input, raw });
  return { input, commitment, observation, registrar, identity, envelope, output, read };
};
const denies = mutate => { const f = fixture(); mutate(f); assert.throws(() => f.read(), { code: 'MANAGEMENT_EXISTING_BOOTSTRAP_CONTRACT' }); };

test('independent registrar bootstrap binds original, policies and current run without claiming delivery or authority', () => {
  const f = fixture(), result = f.read();
  assert.equal(result.state, 'existing-bootstrap-signatures-consistent');
  assert.deepEqual(result.retained, f.observation.retained); assert.deepEqual(result.originalBinding, f.commitment.context.binding);
  for (const key of ['original_retained', 'delivery_consumed', 'current_access_proven', 'hosted_oidc_proven', 'live_google_proven', 'source_verified', 'admitted'])
    assert.equal(result[key], false);
});

test('missing, changed or response-enrolled trust and identity/registrar key substitution refuse', () => {
  for (const mutate of [f => { delete f.input.runPolicy; }, f => { f.input.bootstrapPolicySha256 = '0'.repeat(64); },
    f => { f.input.runPolicy.environment = 'Changed'; f.input.run.environment = 'Changed'; },
    f => { f.input.registrarPublicKey = f.identity.publicKey; }, f => { f.input.identityPublicKey = f.registrar.publicKey; },
    f => { f.input.registrarPublicKey = f.registrar.privateKey; }, f => { f.input.identityPublicKey = generateKeyPairSync('ed25519').publicKey; },
    f => { f.input.bootstrapPolicy.registrar_key_id = 'unknown'; }, f => { f.input.workerPolicy.policy_epoch++; },
    f => { f.input.email = 'caller@mrnwebdesigns.com'; }]) denies(mutate);
});

test('every independent run and workflow constraint rejects substitution before accepting bootstrap metadata', () => {
  for (const key of Object.keys(fixture().input.run)) denies(f => {
    f.input.run[key] = key === 'run_attempt' ? 2 : key === 'repository_visibility' ? 'public' : String(f.input.run[key]) + '1';
  });
  for (const key of ['dispatch_id', 'context_sha256']) denies(f => { f.input.pointer[key] = key === 'dispatch_id'
    ? '99999999-9999-4999-8999-999999999999' : '0'.repeat(64); });
  denies(f => { f.input.pointer.url = existingBootstrapUrl; });
});

test('distinct purpose and exact public key are required for each signature; valid identity signatures cannot substitute', () => {
  for (const name of ['commitment', 'observation']) {
    for (const purpose of [existingBootstrapPurposes[name === 'commitment' ? 'observation' : 'commitment'], 'mrn-content-management-existing-worker-context-v2:']) {
      const f = fixture(), out = f.output(); out[name] = f.envelope(f[name], name, f.registrar.privateKey, purpose);
      assert.throws(() => f.read(Buffer.from(canonical(out))));
    }
    const f = fixture(), out = f.output(); out[name] = f.envelope(f[name], name, f.identity.privateKey);
    assert.throws(() => f.read(Buffer.from(canonical(out))));
  }
});

test('registrar-signed mismatched original, dispatch, source, policy and envelope hash are refused', () => {
  for (const mutate of [f => { f.commitment.context.binding.site_id++; }, f => { f.commitment.context.management_context_sha256 = '0'.repeat(64); },
    f => { f.commitment.context.evidence_policy_epoch++; }, f => { f.commitment.requested_at++; },
    f => { f.commitment.original_expires_at++; }, f => { f.commitment.app_actor_id = '99'; },
    f => { f.commitment.worker_contract_sha256 = '0'.repeat(64); }, f => { f.observation.audience += '1'; },
    f => { f.observation.retained.provenance.engine_commit = '0'.repeat(40); }, f => { f.observation.retained.provenance.run_attempt = 2; },
    f => { f.observation.retained.observation.run_id = '99'; }, f => { f.observation.retained.observation.app_actor_id = '99'; },
    f => { f.observation.retained.provenance.dispatch_record_sha256 = '0'.repeat(64); }]) denies(mutate);
  const f = fixture(), out = f.output(); f.observation.commitment_sha256 = hash(f.commitment);
  out.observation = f.envelope(f.observation, 'observation'); assert.throws(() => f.read(Buffer.from(canonical(out))));
});

test('canonical raw bytes, closed payloads and individual/combined bounds are mandatory despite valid signatures', () => {
  const f = fixture(), out = f.output(), raw = Buffer.from(canonical(out));
  for (const bad of [Buffer.concat([Buffer.from(' '), raw]), Buffer.from('{"commitment":null,' + raw.toString().slice(1)),
    Buffer.from([0xff, 0xfe]), Buffer.alloc(65537), Buffer.from('null')]) assert.throws(() => f.read(bad));
  for (const name of ['commitment', 'observation']) for (const payload of [Buffer.from(' ' + canonical(f[name])),
    Buffer.from('{"schema":2,' + canonical(f[name]).slice(1)), Buffer.from(canonical({ ...f[name], email: 'caller' })),
    Buffer.alloc(name === 'commitment' ? 16385 : 32769, 32)]) {
    const changed = f.output(); changed[name] = f.envelope(payload, name);
    assert.throws(() => f.read(Buffer.from(canonical(changed))));
  }
});

test('receipt clocks, original expiry and elapsed worker lifetime never renew through bootstrap signatures', () => {
  for (const mutate of [f => { f.input.completedAt = f.input.startedAt + 5000; },
    f => { f.input.completedAt = f.input.startedAt - 1; }, f => { f.input.startedAt = NaN; },
    f => { f.input.completedAt = f.input.startedAt = f.commitment.original_expires_at; },
    f => { f.input.completedAt = f.input.startedAt = f.commitment.requested_at + 300000; }]) denies(mutate);
  const f = fixture(); f.observation.retained.observation.observed_at = f.input.completedAt + 1;
  f.observation.retained.provenance.dispatch_record_sha256 = hash(f.observation.retained.observation);
  assert.throws(() => f.read());
});

test('attested worker journal hash is preserved but cannot establish independent at-rest or one-use proof', () => {
  const f = fixture(); f.observation.worker_history_sha256 = 'a'.repeat(64);
  const result = f.read(); assert.equal(result.worker_history_sha256, 'a'.repeat(64));
  assert.equal(result.original_retained, false); assert.equal(result.delivery_consumed, false);
});

test('private trust input rejects accessors, symbols, inherited fields and forged key objects', () => {
  for (const mutate of [input => { input[Symbol('hidden')] = 'caller'; },
    input => { Object.setPrototypeOf(input, { caller: true }); }, input => {
      const saved = input.pointer; Object.defineProperty(input, 'pointer', { get: () => saved, enumerable: true });
    }, input => { const key = input.identityPublicKey; input.identityPublicKey = {
      type: 'public', asymmetricKeyType: 'ed25519', export: options => key.export(options),
    }; }]) {
    const f = fixture(), input = { ...f.input, raw: Buffer.from(canonical(f.output())) }; mutate(input);
    assert.throws(() => verifyExistingWorkerBootstrap(input));
  }
});
