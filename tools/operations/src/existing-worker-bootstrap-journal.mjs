import { KeyObject, createPublicKey, sign, verify } from 'node:crypto';
import { z } from 'zod';
import { digest, requireThat } from './contracts.mjs';
import { delegationCanonical as canonical, delegationDigest as hash } from './delegation.mjs';
import { existingWorkerContractSha256 } from './existing-management-worker-contract.mjs';
import { existingBootstrapContractSha256, existingBootstrapPurposes, existingBootstrapUrl,
  parseExistingBootstrapRecord as parse } from './existing-worker-bootstrap-contract.mjs';

const need = value => requireThat(value, 'MANAGEMENT_EXISTING_BOOTSTRAP_JOURNAL',
  'The original bootstrap history, policy or signing authority is unavailable or changed.');
const same = (a, b) => hash(a) === hash(b);
const runPolicyType = z.object({ repository_owner_id: z.string().regex(/^[1-9][0-9]{0,19}$/),
  environment: z.string().min(1).max(255), runner_environment: z.literal('github-hosted') }).strict();
const shaType = z.string().regex(/^[a-f0-9]{64}$/);
const publicHash = key => digest(key.export({ type: 'spki', format: 'der' }));

// Private registrar persistence only. The original ledger owns every call and
// its transaction; no startup, route or delivery handler constructs this.
// These signatures attest retained state, never hosted OIDC or source admission.
export class ExistingWorkerBootstrapJournal {
  constructor(store, descriptor, identitySigner) {
    need(descriptor && Object.keys(descriptor).sort().join(',') === 'configuration,keyId,privateKey'
      && typeof descriptor.configuration === 'function' && descriptor.privateKey instanceof KeyObject
      && descriptor.privateKey.type === 'private' && descriptor.privateKey.asymmetricKeyType === 'ed25519');
    this.store = store; this.configurationSource = descriptor.configuration;
    this.keyId = descriptor.keyId; this.privateKey = descriptor.privateKey;
    this.publicKey = createPublicKey(this.privateKey);
    this.identityKeyId = identitySigner.keyId; this.identityPublicKey = createPublicKey(identitySigner.privateKey);
    need(this.keyId !== this.identityKeyId && publicHash(this.publicKey) !== publicHash(this.identityPublicKey));
    store.db.exec(`CREATE TABLE IF NOT EXISTS original_management_bootstrap_history(
      original TEXT NOT NULL, ordinal INTEGER NOT NULL, body TEXT NOT NULL, digest TEXT NOT NULL,
      PRIMARY KEY(original,ordinal));`);
    if (!store.db.prepare('PRAGMA table_info(original_management_attempts)').all().some(c => c.name === 'bootstrap_history_digest'))
      store.db.exec("ALTER TABLE original_management_attempts ADD COLUMN bootstrap_history_digest TEXT NOT NULL DEFAULT ''");
    store.db.exec(`CREATE TABLE IF NOT EXISTS original_management_bootstrap_delivery(
      original TEXT PRIMARY KEY, body TEXT NOT NULL, digest TEXT NOT NULL);`);
    for (const column of ['bootstrap_delivery_digest', 'bootstrap_delivery_state'])
      if (!store.db.prepare('PRAGMA table_info(original_management_attempts)').all().some(c => c.name === column))
        store.db.exec(`ALTER TABLE original_management_attempts ADD COLUMN ${column} TEXT NOT NULL DEFAULT ''`);
  }
  configuration(worker) {
    need(worker);
    const value = this.configurationSource();
    need(value && Object.keys(value).sort().join(',') === 'policy,policySha256,runPolicy');
    const policy = parse('bootstrap_policy', value.policy), runPolicy = runPolicyType.parse(value.runPolicy);
    need(shaType.safeParse(value.policySha256).success && value.policySha256 === hash(policy)
      && policy.contract_sha256 === existingBootstrapContractSha256 && policy.worker_policy_sha256 === hash(worker.policy)
      && policy.run_policy_sha256 === hash(runPolicy) && policy.app_actor_id === worker.app_actor_id
      && policy.registrar_key_id === this.keyId && policy.registrar_public_key_sha256 === publicHash(this.publicKey)
      && policy.identity_key_id === this.identityKeyId && policy.identity_public_key_sha256 === publicHash(this.identityPublicKey));
    return { policy, policySha256: value.policySha256, runPolicy };
  }
  #rows(original) {
    return this.store.db.prepare(`SELECT original,ordinal,
      CASE WHEN length(CAST(body AS BLOB))<=65536 THEN body ELSE NULL END AS body,digest
      FROM original_management_bootstrap_history WHERE original=? ORDER BY ordinal LIMIT 4`).all(original);
  }
  #append(mapping, value, ordinal, previous) {
    const rows = this.#rows(mapping.requestId), body = canonical(value);
    need(rows.length === ordinal && Buffer.byteLength(body) <= 65536);
    this.store.db.prepare('INSERT INTO original_management_bootstrap_history VALUES(?,?,?,?)')
      .run(mapping.requestId, ordinal, body, digest(body));
    need(this.store.db.prepare('UPDATE original_management_attempts SET bootstrap_history_digest=? WHERE attempt=? AND bootstrap_history_digest=?')
      .run(hash(this.#rows(mapping.requestId)), mapping.attempt, previous).changes === 1);
  }
  #envelope(value, purpose) {
    const bytes = Buffer.from(canonical(value));
    return { key_id: this.keyId, payload_base64: bytes.toString('base64'),
      signature_base64: sign(null, Buffer.concat([Buffer.from(purpose), bytes]), this.privateKey).toString('base64') };
  }
  #open(envelope, name) {
    need(envelope && Object.keys(envelope).sort().join(',') === 'key_id,payload_base64,signature_base64'
      && envelope.key_id === this.keyId && typeof envelope.payload_base64 === 'string' && typeof envelope.signature_base64 === 'string');
    const raw = Buffer.from(envelope.payload_base64, 'base64'), signature = Buffer.from(envelope.signature_base64, 'base64');
    need(raw.length > 0 && raw.length <= (name === 'commitment' ? 16384 : 32768)
      && raw.toString('base64') === envelope.payload_base64 && signature.length === 64
      && signature.toString('base64') === envelope.signature_base64
      && verify(null, Buffer.concat([Buffer.from(existingBootstrapPurposes[name]), raw]), this.publicKey, signature));
    const value = parse(name, JSON.parse(raw.toString('utf8'))); need(raw.equals(Buffer.from(canonical(value)))); return value;
  }
  #commitment(worker, configuration) {
    const observed = worker.state === 'observed', value = worker.value;
    const context = observed ? value.retained.context : value.context;
    const p = observed ? value.retained.observation : value.prepared;
    return parse('commitment', { schema: 2, issuer: 'https://operations.mrnwebdesigns.com', audience: existingBootstrapUrl,
      bootstrap_contract_sha256: existingBootstrapContractSha256, bootstrap_policy_sha256: configuration.policySha256,
      worker_contract_sha256: existingWorkerContractSha256, context, request: p.request, requested_at: p.requested_at,
      original_expires_at: p.original_expires_at, app_actor_id: p.app_actor_id });
  }
  #observation(worker, configuration, commitment) {
    return parse('observation', { schema: 2, issuer: 'https://operations.mrnwebdesigns.com',
      audience: `https://github.com/mrnwebdesigns/mrn-code-qa-runner/actions/runs/${worker.value.retained.provenance.run_id}`,
      bootstrap_contract_sha256: existingBootstrapContractSha256, bootstrap_policy_sha256: configuration.policySha256,
      commitment_sha256: hash(commitment), worker_history_sha256: worker.historyDigest, retained: worker.value.retained });
  }
  seed(mapping, originalDigest, worker) {
    const configuration = this.configuration(worker);
    need(mapping.bootstrapConfigurationSha256 === hash(configuration));
    this.#append(mapping, { schema: 2, state: 'unused', original: mapping.requestId,
      original_sha256: originalDigest, configuration }, 0, '');
    // New originals only, in the same transaction. Constructor migrations never
    // enroll old originals or repair absent delivery state.
    const body = canonical({ schema: 1, state: 'unused', original: mapping.requestId, original_sha256: originalDigest });
    this.store.db.prepare('INSERT INTO original_management_bootstrap_delivery VALUES(?,?,?)').run(mapping.requestId, body, digest(body));
    need(this.store.db.prepare(`UPDATE original_management_attempts SET bootstrap_delivery_digest=?,bootstrap_delivery_state='unused'
      WHERE attempt=? AND bootstrap_delivery_digest='' AND bootstrap_delivery_state=''`).run(digest(body), mapping.attempt).changes === 1);
  }
  // Worker and bootstrap journals advance together. The ledger calls this
  // after appending its worker event, before checking/committing either journal.
  advance(original, worker, workerRows) {
    const m = original.mapping, configuration = this.configuration(worker.configuration);
    need(m.bootstrapConfigurationSha256 === hash(configuration));
    const rows = this.#rows(m.requestId), target = { prepared: 1, observed: 2 }[worker.state];
    need(target && rows.length === target && workerRows.length === target + 1 && hash(workerRows) === worker.historyDigest);
    // Validate the previous state against the exact prefix of the now-current
    // worker journal. This permits no missing-row repair or bootstrap backfill.
    const prefix = workerRows.slice(0, target), previousWorker = { state: target === 1 ? 'unused' : 'prepared',
      value: JSON.parse(prefix.at(-1).body), configuration: worker.configuration, historyDigest: hash(prefix) };
    const before = this.read(original, previousWorker, prefix);
    const value = target === 1
      ? { schema: 2, state: 'prepared', commitment: this.#envelope(this.#commitment(worker, configuration), existingBootstrapPurposes.commitment),
        worker_history_sha256: worker.historyDigest }
      : { schema: 2, state: 'observed', observation: this.#envelope(this.#observation(worker, configuration,
        before.commitment), existingBootstrapPurposes.observation) };
    this.#append(m, value, target, before.historyDigest);
    this.read(original, worker, workerRows);
  }
  read(original, worker, workerRows) {
    const m = original.mapping, configuration = this.configuration(worker.configuration), rows = this.#rows(m.requestId);
    need(m.bootstrapConfigurationSha256 === hash(configuration) && hash(workerRows) === worker.historyDigest);
    const marker = this.store.db.prepare('SELECT bootstrap_history_digest FROM original_management_attempts WHERE attempt=?').get(m.attempt);
    const count = { unused: 1, prepared: 2, observed: 3 }[worker.state];
    need(count && rows.length === count && workerRows.length === count && marker?.bootstrap_history_digest === hash(rows));
    const values = rows.map((row, i) => {
      need(row.original === m.requestId && row.ordinal === i && typeof row.body === 'string' && digest(row.body) === row.digest);
      const value = JSON.parse(row.body); need(canonical(value) === row.body); return value;
    });
    need(same(values[0], { schema: 2, state: 'unused', original: m.requestId, original_sha256: original.digest, configuration }));
    let commitment, observation;
    if (count >= 2) {
      const v = values[1];
      need(Object.keys(v).sort().join(',') === 'commitment,schema,state,worker_history_sha256'
        && v.schema === 2 && v.state === 'prepared' && v.worker_history_sha256 === hash(workerRows.slice(0, 2)));
      commitment = v.commitment;
      need(same(this.#open(commitment, 'commitment'), this.#commitment(worker, configuration)));
    }
    if (count === 3) {
      const v = values[2];
      need(Object.keys(v).sort().join(',') === 'observation,schema,state' && v.schema === 2 && v.state === 'observed');
      observation = v.observation;
      need(same(this.#open(observation, 'observation'), this.#observation(worker, configuration, commitment)));
      const output = parse('output', { commitment, observation }); need(Buffer.byteLength(canonical(output)) <= 65536);
    }
    const saved = { state: worker.state, historyDigest: marker.bootstrap_history_digest, configuration, commitment, observation };
    this.#delivery(original, worker, saved); return saved;
  }
  #delivery(original, worker, saved) {
    const m = original.mapping;
    const row = this.store.db.prepare(`SELECT CASE WHEN length(CAST(body AS BLOB))<=2048 THEN body ELSE NULL END AS body,digest
      FROM original_management_bootstrap_delivery WHERE original=?`).get(m.requestId);
    const marker = this.store.db.prepare('SELECT bootstrap_delivery_digest,bootstrap_delivery_state FROM original_management_attempts WHERE attempt=?').get(m.attempt);
    need(row && typeof row.body === 'string' && digest(row.body) === row.digest && marker?.bootstrap_delivery_digest === row.digest);
    const value = JSON.parse(row.body);
    need(canonical(value) === row.body && ['unused', 'consumed'].includes(value.state) && marker.bootstrap_delivery_state === value.state);
    const seed = { schema: 1, state: value.state, original: m.requestId, original_sha256: original.digest };
    if (value.state === 'unused') need(same(value, seed));
    else {
      const retained = worker.value.retained, at = value.consumed_at;
      need(worker.state === 'observed' && Number.isSafeInteger(at) && at >= retained.observation.observed_at
        && at < Math.min(retained.observation.original_expires_at, retained.observation.requested_at + 300000)
        && same(value, { ...seed, dispatch_id: retained.provenance.dispatch_id, context_sha256: hash(retained.context),
          worker_history_sha256: worker.historyDigest, bootstrap_history_sha256: saved.historyDigest,
          response_sha256: hash({ commitment: saved.commitment, observation: saved.observation }), consumed_at: at }));
    }
    return { value, digest: row.digest };
  }
  consume(original, worker, workerRows, at) {
    const saved = this.read(original, worker, workerRows), prior = this.#delivery(original, worker, saved), m = original.mapping;
    need(worker.state === 'observed' && prior.value.state === 'unused');
    const retained = worker.value.retained;
    need(Number.isSafeInteger(at) && at >= retained.observation.observed_at
      && at < Math.min(retained.observation.original_expires_at, retained.observation.requested_at + 300000));
    const value = { ...prior.value, state: 'consumed', dispatch_id: retained.provenance.dispatch_id,
      context_sha256: hash(retained.context), worker_history_sha256: worker.historyDigest, bootstrap_history_sha256: saved.historyDigest,
      response_sha256: hash({ commitment: saved.commitment, observation: saved.observation }), consumed_at: at };
    const body = canonical(value), next = digest(body); need(Buffer.byteLength(body) <= 2048);
    need(this.store.db.prepare('UPDATE original_management_bootstrap_delivery SET body=?,digest=? WHERE original=? AND digest=?')
      .run(body, next, m.requestId, prior.digest).changes === 1);
    need(this.store.db.prepare(`UPDATE original_management_attempts SET bootstrap_delivery_digest=?,bootstrap_delivery_state='consumed'
      WHERE attempt=? AND bootstrap_delivery_digest=? AND bootstrap_delivery_state='unused'`).run(next, m.attempt, prior.digest).changes === 1);
    need(this.#delivery(original, worker, saved).value.state === 'consumed');
    return { commitment: saved.commitment, observation: saved.observation };
  }
}
