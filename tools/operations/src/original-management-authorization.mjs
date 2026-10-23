import { z } from 'zod';
import { readFileSync } from 'node:fs';
import { randomBytes, randomUUID, sign } from 'node:crypto';
import { originalAuthorizationAnchor } from './auth.mjs';
import { AccessPrincipals, accessActorSchema, requesterDigest } from './access-principal.mjs';
import { IdentitySigner, challengeSchema, delegationCanonical, delegationDigest, delegationIssuer, delegationAudience } from './delegation.mjs';
import { absoluteUrl, digest, registryTargetSchema, requireThat, sha } from './contracts.mjs';
import { managementSchemas, validateManagementEvidence } from './content-management-contract.mjs';
import { compileManagementEvidenceSchema } from './content-management-schema.mjs';
import { existingManagementHashes, existingManagementProtocol, existingManagementTypes } from './management-existing-contracts.mjs';
import { originalBindingObservation, originalReadinessObservation, parseOriginalBindingProjection } from './original-authorization-transport.mjs';
import { validateExistingManagementQueue } from './existing-management-queue.mjs';
import { existingWorkerContractSha256, parseExistingWorkerRecord } from './existing-management-worker-contract.mjs';
import { validateManagementQueuePins } from './content-management-access-store.mjs';

const check = (ok, message) => requireThat(ok, 'ORIGINAL_AUTHORIZATION', message);
const parse = (schema, value) => {
  const parsed = schema.safeParse(value); check(parsed.success, 'Original authorization metadata is invalid.'); return parsed.data;
};
const authorizationBytes = readFileSync(new URL('../contracts/content-management-original-authorization-v2.json', import.meta.url));
check(digest(authorizationBytes) === '3a77cf8ad2131fd1e2255e74d2a3dcd9ca1d18051e5acc1d51bdb6b7d6fa73c2', 'Original authorization contract changed.');
const authorizationWire = JSON.parse(authorizationBytes);
const authorizationClaims = compileManagementEvidenceSchema({ $ref: '#/$defs/claims', $defs: authorizationWire.$defs });
const controlSchema = z.object({ schema: z.literal(1), original: z.string().uuid(), kind: z.enum(['bind', 'read']),
  actor: accessActorSchema, principal: sha, requester: sha, mappingDigest: sha, inputDigest: sha,
  claims: authorizationClaims }).strict();
const pinsSchema = z.object({ provider_source_sha: z.string().regex(/^[a-f0-9]{40}$/),
  authorization_policy_sha256: sha, delegation_policy_sha256: sha }).strict();
const managementContractSha256 = digest(readFileSync(new URL('../contracts/content-management-v1.json', import.meta.url)));
const qualificationAnchorSchema = z.object({ requestSha256: sha, profileSha256: sha, runtimeContextSha256: sha,
  acfSchemaSha256: sha, beforePathsSha256: sha, managementContractSha256: z.literal(managementContractSha256) }).strict();
const reviewSchema = z.object({ schema: z.literal(2), operationId: z.string().uuid(), reviewedDigest: sha,
  expiresAt: z.number().int().positive().safe(), beforeSha256: sha, candidateSha256: sha, migrationSha256: sha,
  qualification: qualificationAnchorSchema }).strict();
export const originalQualificationAbility = 'mrn-mainwp/request-content-edit-qualification-existing-v2';
const queueRequestSchema = existingManagementTypes.qualification.request_input;
const captureReceiptSchema = z.object({ bindingSha256: sha, captureArtifactSha256: sha,
  measurementSha256: sha, datasetSha256: sha }).strict();
const captureEventSchema = z.object({ schema: z.literal(1), job: z.string().uuid(), original: z.string().uuid(),
  queueDigest: sha, bindingSha256: sha, principal: sha, requester: sha, requestId: z.string().uuid(),
  action: z.enum(['capture', 'read-capture']), phase: z.enum(['dispatched', 'observed']),
  at: z.number().int().positive().safe(), receipt: captureReceiptSchema.nullable() }).strict();
const queueSchema = z.object({ schema: z.literal(1), id: z.string().uuid(), original: z.string().uuid(),
  actor: accessActorSchema, principal: sha, requester: sha, originalDigest: sha, reviewDigest: sha, bindingDigest: sha,
  coherenceEpochSha256: sha, request: queueRequestSchema, pins: z.unknown(),
  envelopeSha256: sha, delegationRequestId: z.string().uuid(), currentAuthorizationSha256: sha,
  transportExpiresAt: z.number().int().positive().safe() }).strict();
const readinessSchema = z.object({ schema: z.literal(1), coherenceEpochSha256: sha,
  observedAt: z.number().int().positive().safe(), expiresAt: z.number().int().positive().safe(),
  dispatchContractSha256: z.literal('8f524002fc5fd900f10e2851d0edd475b4e6ecfe3e6db2a1335573a6e3b7a579'),
}).strict().refine(value => value.expiresAt > value.observedAt && value.expiresAt <= value.observedAt + 30000);
const workerConfigurationSchema = z.object({ schema: z.literal(2), worker_contract_sha256: z.literal(existingWorkerContractSha256),
  policy: existingManagementTypes.evidence.policy, app_actor_id: existingManagementTypes.evidence.id }).strict()
  .refine(v => v.policy.repository === 'mrnwebdesigns/mrn-code-qa-runner');
const workerSourceFields = ['repository', 'repository_id', 'ref', 'workflow_id', 'workflow_path', 'workflow_sha256',
  'runner_source_sha', 'engine_commit', 'engine_configuration_sha256', 'runtime_sha256'];
const mappingSchema = z.object({ schema: z.literal(1), attempt: z.string().uuid(), requestId: z.string().uuid(),
  actor: accessActorSchema, site: registryTargetSchema, requester: sha, principal: sha, pins: pinsSchema,
  workerConfigurationSha256: sha.optional(),
  memberPolicySha256: sha, argumentsSha256: sha, envelopeSha256: sha, readiness: readinessSchema,
  authorization: z.object({ schema: z.literal(1), authorizationSha256: sha, expiresAt: z.number().int().positive().safe() }).strict(),
  transportExpiresAt: z.number().int().positive().safe(),
  target: z.object({ site_id: z.number().int().positive().safe(), site_url: absoluteUrl,
    post_type: z.enum(['post', 'page']), post_id: z.number().int().positive().safe(), expected_snapshot_sha256: sha }).strict(),
}).strict();

// Private source preparation only. main.mjs does not construct this ledger or
// register an endpoint. Its original-current control assertions do not qualify
// a provider protocol or permit a write. Fresh readiness, provider readback,
// complete source/QA admission and later access vetoes remain separate.
export class OriginalManagementAuthorizationLedger {
  #handles = new WeakMap();
  #controlHandles = new WeakMap();
  #queueHandles = new WeakMap();
  #captureHandles = new WeakMap();
  #workerHandles = new WeakMap();
  constructor(store, { signer, registry, policy, verifyStaff, queueReadiness, workerConfiguration, now = () => Date.now() }) {
    check(signer instanceof IdentitySigner && typeof policy === 'function' && typeof verifyStaff === 'function',
      'The existing service identity signer, policy and active staff check are required.');
    check(queueReadiness === undefined || typeof queueReadiness === 'function', 'Protected qualification readiness must be service-owned.');
    check(workerConfiguration === undefined || typeof workerConfiguration === 'function', 'Worker configuration must be service-owned.');
    Object.assign(this, { store, signer, registry, policy, verifyStaff, queueReadiness, workerConfiguration, now });
    this.principals = new AccessPrincipals(store, { registry, connectionId: signer.connectionId, now });
    this.store.db.exec(`CREATE TABLE IF NOT EXISTS original_management_authorizations(
      request TEXT PRIMARY KEY, attempt TEXT NOT NULL UNIQUE, body TEXT NOT NULL, digest TEXT NOT NULL,
      state TEXT NOT NULL CHECK(state IN ('prepared','dispatched','reviewed')));
      CREATE TABLE IF NOT EXISTS original_management_attempts(
      attempt TEXT PRIMARY KEY, request TEXT NOT NULL UNIQUE, digest TEXT NOT NULL, principal TEXT NOT NULL, requester TEXT NOT NULL);
      CREATE TABLE IF NOT EXISTS original_management_reviews(
      request TEXT PRIMARY KEY, operation TEXT NOT NULL UNIQUE, body TEXT NOT NULL, digest TEXT NOT NULL);
      CREATE TABLE IF NOT EXISTS original_management_controls(
      id TEXT PRIMARY KEY, original TEXT NOT NULL, kind TEXT NOT NULL, body TEXT NOT NULL, digest TEXT NOT NULL, state TEXT NOT NULL);
      CREATE UNIQUE INDEX IF NOT EXISTS original_management_single_bind ON original_management_controls(original) WHERE kind='bind';
      CREATE TABLE IF NOT EXISTS original_management_control_history(
      id TEXT NOT NULL, original TEXT NOT NULL, kind TEXT NOT NULL, digest TEXT NOT NULL, principal TEXT NOT NULL,
      requester TEXT NOT NULL, state TEXT NOT NULL, PRIMARY KEY(id,state));
      CREATE TABLE IF NOT EXISTS original_management_bindings(original TEXT PRIMARY KEY, body TEXT NOT NULL, digest TEXT NOT NULL);
      CREATE TABLE IF NOT EXISTS original_management_queues(id TEXT PRIMARY KEY, original TEXT NOT NULL UNIQUE,
      body TEXT NOT NULL, digest TEXT NOT NULL, state TEXT NOT NULL, dispatched_at INTEGER NOT NULL DEFAULT 0,
      response TEXT NOT NULL DEFAULT '', response_digest TEXT NOT NULL DEFAULT '');
      CREATE TABLE IF NOT EXISTS original_management_queue_history(id TEXT NOT NULL, ordinal INTEGER NOT NULL,
      original TEXT NOT NULL, state TEXT NOT NULL, digest TEXT NOT NULL, dispatched_at INTEGER NOT NULL,
      response_digest TEXT NOT NULL, principal TEXT NOT NULL, requester TEXT NOT NULL, PRIMARY KEY(id,ordinal));
      CREATE TABLE IF NOT EXISTS original_management_access_queries(id TEXT PRIMARY KEY, job TEXT NOT NULL,
      check_id TEXT NOT NULL UNIQUE, body TEXT NOT NULL, digest TEXT NOT NULL);
      CREATE TABLE IF NOT EXISTS original_management_capture_history(job TEXT NOT NULL, ordinal INTEGER NOT NULL,
      body TEXT NOT NULL, digest TEXT NOT NULL, principal TEXT NOT NULL, requester TEXT NOT NULL, PRIMARY KEY(job,ordinal));`);
    if (!this.store.db.prepare('PRAGMA table_info(original_management_queues)').all().some(c => c.name === 'provider_job'))
      this.store.db.exec('ALTER TABLE original_management_queues ADD COLUMN provider_job TEXT');
    this.store.db.exec('CREATE UNIQUE INDEX IF NOT EXISTS original_queue_provider_job ON original_management_queues(provider_job)');
    this.store.db.exec(`CREATE TABLE IF NOT EXISTS original_management_worker_history(
      original TEXT NOT NULL, ordinal INTEGER NOT NULL, body TEXT NOT NULL, digest TEXT NOT NULL,
      dispatch TEXT UNIQUE, run TEXT UNIQUE, PRIMARY KEY(original,ordinal));`);
    const columns = this.store.db.prepare('PRAGMA table_info(original_management_attempts)').all();
    if (!columns.some(c => c.name === 'worker_history_digest'))
      this.store.db.exec("ALTER TABLE original_management_attempts ADD COLUMN worker_history_digest TEXT NOT NULL DEFAULT ''");
    if (!columns.some(c => c.name === 'bind_control'))
      this.store.db.exec("ALTER TABLE original_management_attempts ADD COLUMN bind_control TEXT NOT NULL DEFAULT ''");
    if (!columns.some(c => c.name === 'control_count'))
      this.store.db.exec('ALTER TABLE original_management_attempts ADD COLUMN control_count INTEGER NOT NULL DEFAULT 0');
    if (!columns.some(c => c.name === 'binding_digest'))
      this.store.db.exec("ALTER TABLE original_management_attempts ADD COLUMN binding_digest TEXT NOT NULL DEFAULT ''");
    // A missing marker on an established review is not permission to rebuild
    // its anchor. Only a new, fully reconstructed review writes both together.
    if (!columns.some(c => c.name === 'review_digest'))
      this.store.db.exec("ALTER TABLE original_management_attempts ADD COLUMN review_digest TEXT NOT NULL DEFAULT ''");
    if (!columns.some(c => c.name === 'queue_id'))
      this.store.db.exec("ALTER TABLE original_management_attempts ADD COLUMN queue_id TEXT NOT NULL DEFAULT ''");
    if (!columns.some(c => c.name === 'access_count'))
      this.store.db.exec('ALTER TABLE original_management_attempts ADD COLUMN access_count INTEGER NOT NULL DEFAULT 0');
    if (!columns.some(c => c.name === 'access_digest'))
      this.store.db.exec("ALTER TABLE original_management_attempts ADD COLUMN access_digest TEXT NOT NULL DEFAULT ''");
    if (!columns.some(c => c.name === 'capture_count'))
      this.store.db.exec('ALTER TABLE original_management_attempts ADD COLUMN capture_count INTEGER NOT NULL DEFAULT 0');
    if (!columns.some(c => c.name === 'capture_digest'))
      this.store.db.exec("ALTER TABLE original_management_attempts ADD COLUMN capture_digest TEXT NOT NULL DEFAULT ''");
  }
  #actor(value) {
    // Never accept a caller-created actor, hash or session ID at this boundary.
    const authorization = originalAuthorizationAnchor(value), actor = this.principals.actor(value);
    check(authorization.expiresAt === actor.expiresAt, 'Original resource authorization differs from its verified person.');
    this.principals.assertAllowed(actor); return { actor, authorization };
  }
  #site(actor, input, saved) {
    const site = parse(registryTargetSchema, saved ? this.registry.current(actor, saved, 'content')
      : this.registry.resolve(actor, input.site_url, undefined, 'content'));
    check(site.url === input.site_url && site.management === 'mainwp', 'The original authorization requires one exact managed website.');
    return site;
  }
  #pins() { return parse(pinsSchema, this.policy()); }
  #readiness(value, pins) {
    const { policy, ...anchor } = originalReadinessObservation(value);
    check(policy.provider_source_sha === pins.provider_source_sha && policy.authorization_policy_sha256 === pins.authorization_policy_sha256,
      'Provider readiness belongs to another source or authorization policy.');
    return parse(readinessSchema, anchor);
  }
  #sameEpoch(current, readiness) {
    const observed = this.#readiness(readiness, current.mapping.pins);
    check(observed.coherenceEpochSha256 === current.mapping.readiness.coherenceEpochSha256,
      'Provider readiness no longer matches the original preview epoch.');
    return observed;
  }
  #load(requestId) {
    parse(z.string().uuid(), requestId);
    const row = this.store.db.prepare('SELECT * FROM original_management_authorizations WHERE request=?').get(requestId);
    check(row && digest(row.body) === row.digest && ['prepared', 'dispatched', 'reviewed'].includes(row.state),
      'The original authorization is missing or corrupt; do not recreate it.');
    let body; try { body = JSON.parse(row.body); } catch { check(false, 'The original authorization is corrupt.'); }
    const mapping = parse(mappingSchema, body);
    const attempt = this.store.db.prepare('SELECT * FROM original_management_attempts WHERE attempt=?').get(mapping.attempt);
    check(mapping.requestId === row.request && mapping.attempt === row.attempt && attempt?.request === row.request
      && attempt.digest === row.digest && attempt.principal === mapping.principal && attempt.requester === mapping.requester
      && mapping.requester === requesterDigest(mapping.actor)
      && mapping.principal === digest(mapping.actor.googleSubject) && mapping.actor.expiresAt === mapping.authorization.expiresAt
      && mapping.transportExpiresAt <= mapping.authorization.expiresAt && mapping.target.site_url === mapping.site.url,
    'The original attempt history is unavailable or inconsistent; do not recreate it.');
    const retained = this.store.db.prepare('SELECT * FROM original_management_reviews WHERE request=?').get(requestId);
    let review;
    if (row.state === 'reviewed') {
      check(retained && digest(retained.body) === retained.digest && retained.digest === attempt.review_digest,
        'The original preview mapping or its immutable anchor is missing or corrupt.');
      let value; try { value = JSON.parse(retained.body); } catch { check(false, 'The original preview mapping is corrupt.'); }
      review = parse(reviewSchema, value);
      check(review.operationId === retained.operation && review.beforeSha256 === mapping.target.expected_snapshot_sha256,
        'The original preview mapping differs from its retained request.');
    } else check(!retained && attempt.review_digest === '', 'The original preview history is inconsistent.');
    return { mapping, state: row.state, digest: row.digest, ...(review ? { review } : {}) };
  }
  #current(requestId, currentActor) {
    const { actor } = this.#actor(currentActor), retained = this.#load(requestId), m = retained.mapping;
    check(requesterDigest(actor) === m.requester && actor.googleConnection === m.actor.googleConnection,
      'This preview belongs to a different verified person.');
    return this.#retainedAuthority(retained);
  }
  // Private workers recover only the independently retained original. This is
  // never used to accept an identity supplied by a public caller or callback.
  #retainedAuthority(retained) {
    const m = retained.mapping;
    // Reuse the retained original expiry. The current caller may have a newer
    // token, but cannot replace the original grant or its policy/site mapping.
    this.principals.actor(m.actor);
    const principal = this.principals.row(m.principal, m.requester);
    check(principal.state === 'clear', 'Original access is denied or pending revocation.');
    check(!retained.review || retained.review.expiresAt > this.now(), 'The original reviewed preview expired.');
    check(delegationDigest(this.#pins()) === delegationDigest(m.pins)
      && delegationDigest(this.registry.member(m.actor)) === m.memberPolicySha256
      && delegationDigest(this.#site(m.actor, m.target, m.site)) === delegationDigest(m.site),
    'The original source, policy or website mapping changed.');
    return { ...retained, revision: principal.revision };
  }
  // The stable service-owned attempt ID is reserved before any downstream call.
  // Repeating it refuses, including after a lost response or missing body.
  async prepare(value, input, challenge, attemptId, readiness) {
    parse(z.string().uuid(), attemptId);
    const args = managementSchemas['preview-content-edit-v1'].input.parse(input);
    check(args.post_id > 0, 'Only an existing exact content record can retain original authorization.');
    const worker = this.workerConfiguration ? parse(workerConfigurationSchema, this.workerConfiguration()) : null;
    const initial = this.#actor(value), site = this.#site(initial.actor, args), pins = this.#pins();
    const readinessAnchor = this.#readiness(readiness, pins);
    const principalBefore = this.store.db.prepare('SELECT * FROM content_access_principals WHERE principal=?').get(digest(initial.actor.googleSubject));
    const memberPolicySha256 = delegationDigest(this.registry.member(initial.actor));
    const c = challengeSchema.parse(challenge);
    check(c.policy_sha256 === pins.delegation_policy_sha256, 'The preview challenge uses a different delegation policy.');
    await this.verifyStaff(initial.actor);
    return this.store.transaction(() => {
      check(delegationDigest(this.workerConfiguration ? parse(workerConfigurationSchema, this.workerConfiguration()) : null)
        === delegationDigest(worker), 'Worker configuration changed during original preparation.');
      const { actor, authorization } = this.#actor(value);
      const principalAfter = this.store.db.prepare('SELECT * FROM content_access_principals WHERE principal=?').get(digest(actor.googleSubject));
      check(delegationDigest(principalBefore ?? null) === delegationDigest(principalAfter ?? null),
        'Principal history changed during active staff verification.');
      check(delegationDigest(this.#pins()) === delegationDigest(pins)
        && delegationDigest(this.#readiness(readiness, pins)) === delegationDigest(readinessAnchor)
        && delegationDigest(this.#site(actor, args, site)) === delegationDigest(site)
        && delegationDigest(this.registry.member(actor)) === memberPolicySha256,
      'Original authority changed during active staff verification.');
      check(!this.store.db.prepare('SELECT attempt FROM original_management_attempts WHERE attempt=?').get(attemptId)
        && !this.store.db.prepare('SELECT request FROM original_management_authorizations WHERE attempt=?').get(attemptId),
      'This original preview attempt is already retained; read its state instead of retrying.');
      const envelope = Object.freeze(this.signer.sign(value, 'mrn-mainwp/preview-content-edit-v1', args, c));
      const claims = JSON.parse(Buffer.from(envelope.payload_base64, 'base64'));
      const mapping = parse(mappingSchema, { ...(worker ? { workerConfigurationSha256: delegationDigest(worker) } : {}), schema: 1, attempt: attemptId, requestId: claims.request_id, readiness: readinessAnchor,
        actor, authorization, site, pins, requester: requesterDigest(actor), principal: digest(actor.googleSubject),
        memberPolicySha256, argumentsSha256: claims.arguments_sha256, envelopeSha256: delegationDigest(envelope),
        transportExpiresAt: claims.expires_at * 1000,
        target: Object.fromEntries(['site_id', 'site_url', 'post_type', 'post_id', 'expected_snapshot_sha256'].map(k => [k, args[k]])) });
      this.principals.retain(mapping.principal, mapping.requester);
      const body = JSON.stringify(mapping), hash = digest(body);
      this.store.db.prepare('INSERT INTO original_management_authorizations VALUES(?,?,?,?,?)')
        .run(mapping.requestId, attemptId, body, hash, 'prepared');
      this.store.db.prepare('INSERT INTO original_management_attempts(attempt,request,digest,principal,requester) VALUES(?,?,?,?,?)')
        .run(attemptId, mapping.requestId, hash, mapping.principal, mapping.requester);
      if (worker) this.#appendWorkerHistory(mapping, { schema: 2, state: 'unused', original: mapping.requestId,
        original_sha256: hash, configuration: worker }, '');
      this.store.audit(actor, site, mapping.requestId, 'original-authorization-retained', 'prepared');
      this.#current(mapping.requestId, value);
      const handle = Object.freeze({ requestId: mapping.requestId });
      this.#handles.set(handle, { envelope, authorizationSha256: authorization.authorizationSha256, readiness });
      return handle;
    });
  }
  // One-shot in-process handle: a restart or another authenticated login cannot
  // recover an envelope and redispatch an uncertain preview. Mark before I/O.
  async claimDispatch(handle, actor) {
    const held = this.#handles.get(handle);
    check(held, 'The original in-process dispatch handle is unavailable or consumed.');
    const before = this.#current(handle.requestId, actor);
    await this.verifyStaff(before.mapping.actor);
    const envelope = this.store.transaction(() => {
      const current = this.#current(handle.requestId, actor), m = current.mapping;
      this.#sameEpoch(current, held.readiness);
      check(this.#handles.get(handle) === held && current.digest === before.digest && current.revision === before.revision
        && current.state === 'prepared' && m.transportExpiresAt > this.now()
        && originalAuthorizationAnchor(actor).authorizationSha256 === held.authorizationSha256
        && delegationDigest(held.envelope) === m.envelopeSha256,
      'The original dispatch is consumed, expired or no longer current.');
      check(this.store.db.prepare("UPDATE original_management_authorizations SET state='dispatched' WHERE request=? AND state='prepared'")
        .run(handle.requestId).changes === 1, 'The original dispatch could not be claimed.');
      this.store.audit(m.actor, m.site, m.requestId, 'original-preview-dispatch', 'claimed');
      return held.envelope;
    });
    this.#handles.delete(handle); return envelope;
  }
  // Called only after the delegated response request_id/identity check. Repeat
  // full candidate/migration reconstruction, then retain its operation pointer
  // and hash-only qualification anchor. No content or callback can reconstruct
  // missing review history, and a later login cannot supply replacement pins.
  async recordReview(requestId, actor, input, evidence, profile) {
    const before = await this.readCurrent(requestId, actor), m = before.mapping;
    check(before.state === 'dispatched' && delegationDigest(input) === m.argumentsSha256,
      'Only the exact dispatched preview may retain its original review.');
    const { review, migration, read } = validateManagementEvidence(evidence, { site: { id: m.target.site_id, url: m.target.site_url },
      target: { kind: 'wordpress-content', post_type: m.target.post_type, post_id: m.target.post_id }, request: input, profile, now: this.now() });
    const projection = parse(reviewSchema, { schema: 2, operationId: review.operation_id, reviewedDigest: review.digest,
      expiresAt: review.expires_at * 1000, beforeSha256: review.before_sha256, candidateSha256: review.candidate_sha256,
      migrationSha256: review.migration_artifact.sha256, qualification: {
        requestSha256: delegationDigest(migration.request), profileSha256: review.profile.sha256,
        runtimeContextSha256: review.profile.runtime_context_sha256, acfSchemaSha256: delegationDigest(read.snapshot.acf_definitions),
        beforePathsSha256: delegationDigest(read.affected_paths), managementContractSha256,
      } });
    return this.store.transaction(() => {
      const current = this.#current(requestId, actor);
      check(current.state === 'dispatched' && current.digest === before.digest && current.revision === before.revision,
        'The original dispatch changed before review retention.');
      const body = JSON.stringify(projection), hash = digest(body);
      this.store.db.prepare('INSERT INTO original_management_reviews VALUES(?,?,?,?)')
        .run(requestId, projection.operationId, body, hash);
      check(this.store.db.prepare("UPDATE original_management_attempts SET review_digest=? WHERE attempt=? AND review_digest=''")
        .run(hash, m.attempt).changes === 1, 'The immutable original review anchor could not be retained.');
      check(this.store.db.prepare("UPDATE original_management_authorizations SET state='reviewed' WHERE request=? AND state='dispatched'")
        .run(requestId).changes === 1, 'The original review could not be retained.');
      this.store.audit(m.actor, m.site, requestId, 'original-preview-retained', 'reviewed');
      return this.#current(requestId, actor);
    });
  }
  // Metadata recovery only. A fresh Directory result brackets immutable local
  // history, original expiry and deny-first principal/policy/site checks. This
  // does not introspect Auth0 logout/session state or issue current:true.
  async readCurrent(requestId, actor) {
    const before = this.#current(requestId, actor);
    await this.verifyStaff(before.mapping.actor);
    return this.store.transaction(() => {
      const after = this.#current(requestId, actor);
      check(after.digest === before.digest && after.revision === before.revision && after.state === before.state
        && delegationDigest(after.review ?? null) === delegationDigest(before.review ?? null),
        'Original authorization changed during active staff verification.');
      return after;
    });
  }

  // Every control has independent append-only issuance/dispatch history. Lost
  // or rolled-back primary state cannot turn an uncertain bind into first use.
  #controls(current) {
    const m = current.mapping;
    const rows = this.store.db.prepare('SELECT * FROM original_management_controls WHERE original=? LIMIT 66').all(m.requestId);
    const history = this.store.db.prepare('SELECT * FROM original_management_control_history WHERE original=? LIMIT 196').all(m.requestId);
    const attempt = this.store.db.prepare('SELECT bind_control,control_count FROM original_management_attempts WHERE attempt=?').get(m.attempt);
    const binding = this.#binding(current);
    check(rows.length <= 65 && history.length <= 195, 'Original authorization control history exceeds its bound.');
    check(Number.isSafeInteger(attempt?.control_count) && attempt.control_count === rows.length,
      'The original attempt has lost control history; do not reconstruct it.');
    const values = rows.map(row => {
      check(digest(row.body) === row.digest, 'Original authorization control is corrupt.');
      let value; try { value = JSON.parse(row.body); } catch { check(false, 'Original authorization control is corrupt.'); }
      const control = parse(controlSchema, value), c = control.claims;
      check(control.original === m.requestId && control.kind === row.kind && c.request_id === row.id
        && control.mappingDigest === current.digest && control.principal === m.principal && control.requester === m.requester
        && delegationDigest(control.actor) === delegationDigest(m.actor)
        && c.original_delegation_request_id === m.requestId && c.operation_id === current.review?.operationId
        && c.reviewed_digest === current.review?.reviewedDigest
        && c.original_resource_authorization_sha256 === m.authorization.authorizationSha256
        && c.original_resource_authorization_expires_at === Math.floor(m.authorization.expiresAt / 1000)
        && c.requester_binding_sha256 === m.requester && c.provider_source_sha === m.pins.provider_source_sha
        && c.authorization_policy_sha256 === m.pins.authorization_policy_sha256
        && c.action === `${control.kind}-existing-management-authorization`
        && c.expires_at > c.issued_at && c.expires_at <= Math.min(c.issued_at + 60,
          c.original_resource_authorization_expires_at, Math.floor(current.review.expiresAt / 1000),
          control.kind === 'bind' ? Math.floor(m.transportExpiresAt / 1000) : Infinity),
      'Original authorization control differs from retained authority.');
      const states = row.state === 'prepared' ? ['prepared'] : row.state === 'dispatched' ? ['prepared', 'dispatched']
        : row.state === 'observed' && binding ? ['prepared', 'dispatched', 'observed'] : [];
      const entries = history.filter(entry => entry.id === row.id);
      check(states.length > 0 && entries.length === states.length && states.every(state => entries.some(entry => entry.state === state
        && entry.kind === row.kind && entry.digest === row.digest && entry.principal === m.principal && entry.requester === m.requester)),
      'Original authorization control history is missing or inconsistent.');
      return { ...row, control };
    });
    check(history.every(entry => rows.some(row => row.id === entry.id)), 'An original control body is missing; do not recreate it.');
    check(values.filter(row => row.kind === 'bind').length <= 1, 'Original bind history is ambiguous.');
    check(attempt.bind_control === (values.find(row => row.kind === 'bind')?.id ?? '')
      && (!values.length || values.some(row => row.kind === 'bind')), 'The original bind marker is missing or inconsistent.');
    check(!binding || values.some(row => row.state === 'observed'), 'The original provider binding has lost its observation history.');
    return values;
  }
  #appendControlHistory(row, m, state) {
    this.store.db.prepare('INSERT INTO original_management_control_history VALUES(?,?,?,?,?,?,?)')
      .run(row.id, m.requestId, row.kind, row.digest, m.principal, m.requester, state);
  }
  async #prepareControl(requestId, actor, kind, readiness) {
    const before = await this.readCurrent(requestId, actor);
    return this.store.transaction(() => {
      const current = this.#current(requestId, actor), m = current.mapping, review = current.review;
      this.#sameEpoch(current, readiness);
      check(current.state === 'reviewed' && current.digest === before.digest && current.revision === before.revision,
        'Original authority changed before control issuance.');
      const controls = this.#controls(current), bound = controls.find(row => row.kind === 'bind');
      check(controls.length < 65 && (kind === 'bind' ? !bound : ['dispatched', 'observed'].includes(bound?.state)),
        'Bind is already issued or unavailable; recovery requires the original dispatched bind.');
      const issued = Math.floor(this.now() / 1000);
      const claims = parse(authorizationClaims, { schema: 2, issuer: delegationIssuer, audience: delegationAudience,
        issued_at: issued, expires_at: Math.min(issued + 60, Math.floor(m.authorization.expiresAt / 1000),
          Math.floor(review.expiresAt / 1000), kind === 'bind' ? Math.floor(m.transportExpiresAt / 1000) : Infinity),
        request_id: randomUUID(), nonce: randomBytes(32).toString('hex'), action: `${kind}-existing-management-authorization`,
        qualification_protocol: existingManagementProtocol, protocol_contracts_sha256: existingManagementHashes,
        lifetime_domain: authorizationWire.lifetime_domain, operation_id: review.operationId, reviewed_digest: review.reviewedDigest,
        provider_source_sha: m.pins.provider_source_sha, authorization_policy_sha256: m.pins.authorization_policy_sha256,
        requester_binding_sha256: m.requester, original_delegation_request_id: m.requestId,
        original_resource_authorization_sha256: m.authorization.authorizationSha256,
        original_resource_authorization_expires_at: Math.floor(m.authorization.expiresAt / 1000),
        original_resource_authorization_current: true });
      check(claims.expires_at > issued, 'The original authorization control window expired.');
      const bytes = Buffer.from(delegationCanonical(claims));
      check(controls.every(row => row.control.claims.nonce !== claims.nonce), 'Original authorization nonce already exists.');
      check(bytes.length <= authorizationWire.limits.claims_bytes, 'Original authorization claims exceed their bound.');
      const envelope = Object.freeze({ key_id: this.signer.keyId, payload_base64: bytes.toString('base64'),
        signature_base64: sign(null, Buffer.concat([Buffer.from(authorizationWire.purposes[kind]), bytes]), this.signer.privateKey).toString('base64') });
      const input = Object.freeze({ signed_request: envelope });
      const control = parse(controlSchema, { schema: 1, original: m.requestId, kind, actor: m.actor,
        principal: m.principal, requester: m.requester, mappingDigest: current.digest, inputDigest: delegationDigest(input), claims });
      const body = JSON.stringify(control), hash = digest(body), row = { id: claims.request_id, kind, digest: hash };
      this.store.db.prepare('INSERT INTO original_management_controls VALUES(?,?,?,?,?,?)')
        .run(row.id, m.requestId, kind, body, hash, 'prepared');
      this.#appendControlHistory(row, m, 'prepared');
      check(this.store.db.prepare('UPDATE original_management_attempts SET bind_control=?,control_count=control_count+1 WHERE attempt=? AND control_count=? AND bind_control=?')
        .run(kind === 'bind' ? row.id : bound.id, m.attempt, controls.length, bound?.id ?? '').changes === 1,
      'The original control marker could not be retained.');
      this.store.audit(m.actor, m.site, m.requestId, `original-authorization-${kind}`, 'prepared');
      const handle = Object.freeze({ originalRequestId: m.requestId, controlId: row.id });
      this.#controlHandles.set(handle, { input, readiness }); return handle;
    });
  }
  // Neither method accepts caller identity, expiry, hashes or protocol fields.
  // Bind requests a provider proof of its source-selected original domain; the
  // legacy review shape alone never establishes that proof or admission.
  prepareBind(requestId, actor, readiness) { return this.#prepareControl(requestId, actor, 'bind', readiness); }
  prepareRead(requestId, actor, readiness) { return this.#prepareControl(requestId, actor, 'read', readiness); }
  async claimControlDispatch(handle, actor) {
    const held = this.#controlHandles.get(handle);
    check(held, 'Original authorization dispatch handle is unavailable or consumed.');
    const { input, readiness } = held;
    const before = await this.readCurrent(handle.originalRequestId, actor);
    const result = this.store.transaction(() => {
      const current = this.#current(handle.originalRequestId, actor);
      this.#sameEpoch(current, readiness);
      check(current.digest === before.digest && current.revision === before.revision
        && this.#controlHandles.get(handle) === held, 'Original authorization changed before dispatch.');
      const row = this.#controls(current).find(row => row.id === handle.controlId);
      check(row?.state === 'prepared' && row.control.claims.expires_at > Math.floor(this.now() / 1000)
        && row.control.inputDigest === delegationDigest(input), 'Original authorization dispatch is expired or consumed.');
      check(this.store.db.prepare("UPDATE original_management_controls SET state='dispatched' WHERE id=? AND state='prepared'")
        .run(row.id).changes === 1, 'Original authorization dispatch could not be claimed.');
      this.#appendControlHistory(row, current.mapping, 'dispatched');
      this.store.audit(current.mapping.actor, current.mapping.site, current.mapping.requestId, `original-authorization-${row.kind}`, 'dispatched');
      return input;
    });
    this.#controlHandles.delete(handle); return result;
  }

  #binding(current) {
    const m = current.mapping, row = this.store.db.prepare('SELECT body,digest FROM original_management_bindings WHERE original=?').get(m.requestId);
    const marker = this.store.db.prepare('SELECT binding_digest FROM original_management_attempts WHERE attempt=?').get(m.attempt);
    check(marker && typeof marker.binding_digest === 'string', 'Original provider binding marker is unavailable.');
    if (marker.binding_digest === '') { check(!row, 'The original provider binding marker is missing.'); return null; }
    check(row && digest(row.body) === row.digest && row.digest === marker.binding_digest, 'Original provider binding is missing or corrupt.');
    let value; try { value = JSON.parse(row.body); } catch { check(false, 'Original provider binding is corrupt.'); }
    const saved = parse(z.object({ schema: z.literal(1), actor: accessActorSchema, principal: sha, requester: sha,
      original: z.string().uuid(), projection: z.unknown(), coherenceEpochSha256: sha }).strict(), value);
    const projection = parseOriginalBindingProjection(saved.projection);
    check(saved.original === m.requestId && saved.principal === m.principal && saved.requester === m.requester
      && saved.coherenceEpochSha256 === m.readiness.coherenceEpochSha256
      && delegationDigest(saved.actor) === delegationDigest(m.actor), 'Original provider binding belongs to another person.');
    this.#projection(current, projection);
    return { ...saved, projection, digest: row.digest };
  }
  #projection(current, projection) {
    const m = current.mapping;
    check(projection.operation_id === current.review?.operationId && projection.reviewed_digest === current.review?.reviewedDigest
      && projection.original_delegation_request_id === m.requestId
      && projection.original_resource_authorization_sha256 === m.authorization.authorizationSha256
      && projection.original_resource_authorization_expires_at === Math.floor(m.authorization.expiresAt / 1000),
    'The provider projection does not bind the retained original.');
  }
  // The private transport brands the exact authenticated result after checking
  // its input, readiness, source/policy, deadline and complete response. A plain
  // object, another control or a deserialized copy cannot create this record.
  async recordProviderBinding(controlId, actor, response) {
    const observation = originalBindingObservation(response);
    check(observation.controlId === controlId, 'The provider response belongs to another control.');
    const before = await this.readCurrent(observation.originalRequestId, actor);
    const projection = parseOriginalBindingProjection(response);
    return this.store.transaction(() => {
      const current = this.#current(observation.originalRequestId, actor), m = current.mapping;
      check(observation.coherenceEpochSha256 === m.readiness.coherenceEpochSha256,
        'The provider observation does not belong to the original preview epoch.');
      check(current.digest === before.digest && current.revision === before.revision, 'Original authority changed before binding retention.');
      const control = this.#controls(current).find(row => row.id === controlId);
      check(control?.state === 'dispatched' && control.control.inputDigest === observation.inputDigest
        && control.control.claims.expires_at > Math.floor(this.now() / 1000), 'The provider observation is expired, consumed or unrelated.');
      this.#projection(current, projection);
      const saved = { schema: 1, actor: m.actor, principal: m.principal, requester: m.requester, original: m.requestId,
        projection, coherenceEpochSha256: observation.coherenceEpochSha256 }, body = JSON.stringify(saved), hash = digest(body);
      const existing = this.#binding(current);
      if (existing) check(existing.digest === hash, 'A fresh read cannot replace the original provider binding or coherence epoch.');
      else {
        this.store.db.prepare('INSERT INTO original_management_bindings VALUES(?,?,?)').run(m.requestId, body, hash);
        check(this.store.db.prepare("UPDATE original_management_attempts SET binding_digest=? WHERE attempt=? AND binding_digest=''")
          .run(hash, m.attempt).changes === 1, 'The original provider binding marker could not be retained.');
      }
      check(this.store.db.prepare("UPDATE original_management_controls SET state='observed' WHERE id=? AND state='dispatched'")
        .run(controlId).changes === 1, 'Provider observation could not be retained.');
      this.#appendControlHistory(control, m, 'observed');
      this.store.audit(m.actor, m.site, m.requestId, 'original-provider-binding', 'observed');
      this.#controls(current); return this.#binding(current);
    });
  }
  async readProviderBinding(requestId, actor) {
    const before = await this.readCurrent(requestId, actor);
    return this.store.transaction(() => {
      const current = this.#current(requestId, actor);
      check(current.digest === before.digest && current.revision === before.revision, 'Original authority changed before provider binding recovery.');
      this.#controls(current); const binding = this.#binding(current);
      check(binding, 'The original provider binding has not been observed.'); return binding;
    });
  }

  #queueHistory(row, mapping) {
    const ordinal = ['prepared', 'dispatched', 'observed'].indexOf(row.state) + 1;
    check(ordinal > 0, 'Original queue state is invalid.');
    this.store.db.prepare('INSERT INTO original_management_queue_history VALUES(?,?,?,?,?,?,?,?,?)')
      .run(mapping.id, ordinal, mapping.original, row.state, row.digest, row.dispatched_at,
        row.response_digest, mapping.principal, mapping.requester);
  }
  async #queueAdmission(actor, input) {
    check(typeof this.queueReadiness === 'function', 'Protected existing-record qualification readiness is unavailable.');
    const value = parse(z.object({ pins: z.unknown(), coherenceEpochSha256: sha }).strict(),
      await this.queueReadiness(actor, structuredClone(input)));
    return { ...value, pins: validateManagementQueuePins(value.pins) };
  }
  #sameQueueAdmission(queue, admission) {
    check(queue && delegationDigest(queue.pins) === delegationDigest(admission.pins)
      && queue.coherenceEpochSha256 === admission.coherenceEpochSha256,
    'Protected qualification source, catalog or coherence changed; the retained queue cannot be renewed.');
  }
  #queue(current) {
    const m = current.mapping;
    const marker = this.store.db.prepare('SELECT queue_id FROM original_management_attempts WHERE attempt=?').get(m.attempt);
    const row = this.store.db.prepare('SELECT * FROM original_management_queues WHERE original=?').get(m.requestId);
    const history = this.store.db.prepare('SELECT * FROM original_management_queue_history WHERE original=? ORDER BY ordinal').all(m.requestId);
    check(marker && typeof marker.queue_id === 'string', 'Original queue marker is unavailable.');
    if (marker.queue_id === '') {
      check(!row && history.length === 0, 'Surviving queue history forbids treating this original as unqueued.'); return null;
    }
    check(row && row.id === marker.queue_id && digest(row.body) === row.digest, 'Original queue is missing or corrupt; do not recreate it.');
    let raw; try { raw = JSON.parse(row.body); } catch { check(false, 'Original queue metadata is corrupt.'); }
    const queue = parse(queueSchema, raw), pins = validateManagementQueuePins(queue.pins), binding = this.#binding(current);
    this.#controls(current);
    check(binding && queue.id === row.id && queue.original === m.requestId && queue.originalDigest === current.digest
      && queue.reviewDigest === delegationDigest(current.review) && queue.bindingDigest === binding.digest
      && queue.principal === m.principal && queue.requester === m.requester && delegationDigest(queue.actor) === delegationDigest(m.actor)
      && queue.coherenceEpochSha256 === m.readiness.coherenceEpochSha256
      && pins.provider_source_sha === m.pins.provider_source_sha && pins.authorization_policy_sha256 === m.pins.authorization_policy_sha256
      && queue.request.site_id === m.target.site_id && queue.request.site_url === m.target.site_url
      && queue.request.operation_id === current.review?.operationId && queue.request.reviewed_digest === current.review?.reviewedDigest,
    'Original queue no longer binds its original person, source, review or provider authorization.');
    const states = ['prepared', 'dispatched', 'observed'], count = states.indexOf(row.state) + 1;
    check(count > 0 && history.length === count && history.every((entry, index) =>
      entry.id === queue.id && entry.ordinal === index + 1 && entry.state === states[index] && entry.digest === row.digest
      && entry.principal === m.principal && entry.requester === m.requester
      && entry.dispatched_at === (index === 0 ? 0 : row.dispatched_at)
      && entry.response_digest === (index < 2 ? '' : row.response_digest)),
    'Original queue history is inconsistent; retrying or resetting it is forbidden.');
    check(Number.isSafeInteger(row.dispatched_at) && (count === 1 ? row.dispatched_at === 0 : row.dispatched_at > 0),
      'The original queue dispatch boundary is unavailable.');
    let context = null;
    if (row.state === 'observed') {
      check(row.response && digest(row.response) === row.response_digest, 'Original queue response is missing or corrupt.');
      let output; try { output = JSON.parse(row.response); } catch { check(false, 'Original queue response is corrupt.'); }
      context = validateExistingManagementQueue(output, { original: current, providerBinding: binding,
        request: queue.request, pins, dispatchedAt: row.dispatched_at, now: this.now() });
      check(row.provider_job === context.binding.qualification_job_id, 'The independent provider job index differs from its retained response.');
    } else check(row.response === '' && row.response_digest === '' && row.provider_job === null,
      'An unobserved queue cannot retain a response or provider job.');
    return { queue: { ...queue, pins }, state: row.state, digest: row.digest, dispatchedAt: row.dispatched_at, context };
  }
  // The only queue selector is the actual retained v2 provider binding.
  // Readiness is a service-owned function, never a public argument or callback.
  async prepareQueue(requestId, actor, input, challenge) {
    const args = parse(queueRequestSchema, input), before = await this.readCurrent(requestId, actor);
    const binding = await this.readProviderBinding(requestId, actor);
    const admission = await this.#queueAdmission(actor, args);
    const pins = admission.pins, c = challengeSchema.parse(challenge);
    // Recheck Directory after potentially asynchronous protected readiness.
    await this.readCurrent(requestId, actor);
    return this.store.transaction(() => {
      const current = this.#current(requestId, actor), m = current.mapping, observed = this.#binding(current);
      this.#controls(current);
      check(current.state === 'reviewed' && current.digest === before.digest && current.revision === before.revision
        && delegationDigest(current.review) === delegationDigest(before.review) && observed?.digest === binding.digest
        && admission.coherenceEpochSha256 === m.readiness.coherenceEpochSha256
        && pins.provider_source_sha === m.pins.provider_source_sha && pins.authorization_policy_sha256 === m.pins.authorization_policy_sha256
        && c.policy_sha256 === m.pins.delegation_policy_sha256
        && args.site_id === m.target.site_id && args.site_url === m.target.site_url
        && args.operation_id === current.review.operationId && args.reviewed_digest === current.review.reviewedDigest,
      'Protected queue readiness differs from the original authorization.');
      check(this.#queue(current) === null, 'This original already has a qualification attempt; do not dispatch another.');
      const envelope = Object.freeze(this.signer.sign(actor, originalQualificationAbility, args, c));
      const claims = JSON.parse(Buffer.from(envelope.payload_base64, 'base64'));
      const queue = parse(queueSchema, { schema: 1, id: randomUUID(), original: requestId, actor: m.actor,
        principal: m.principal, requester: m.requester, originalDigest: current.digest, reviewDigest: delegationDigest(current.review),
        bindingDigest: observed.digest, coherenceEpochSha256: admission.coherenceEpochSha256, request: args, pins,
        envelopeSha256: delegationDigest(envelope), delegationRequestId: claims.request_id,
        currentAuthorizationSha256: originalAuthorizationAnchor(actor).authorizationSha256, transportExpiresAt: claims.expires_at * 1000 });
      const body = JSON.stringify(queue), hash = digest(body);
      this.store.db.prepare("INSERT INTO original_management_queues(id,original,body,digest,state) VALUES(?,?,?,?,'prepared')")
        .run(queue.id, requestId, body, hash);
      check(this.store.db.prepare("UPDATE original_management_attempts SET queue_id=? WHERE attempt=? AND queue_id=''")
        .run(queue.id, m.attempt).changes === 1, 'The one-time queue marker could not be retained.');
      this.#queueHistory({ state: 'prepared', digest: hash, dispatched_at: 0, response_digest: '' }, queue);
      this.store.audit(m.actor, m.site, requestId, 'original-qualification-queue', 'prepared');
      this.#queue(current);
      const handle = Object.freeze({ queueId: queue.id, originalRequestId: requestId });
      this.#queueHandles.set(handle, envelope); return handle;
    });
  }
  async claimQueueDispatch(handle, actor) {
    const envelope = this.#queueHandles.get(handle);
    check(envelope, 'Original queue handle is unavailable or consumed.');
    // This checks current protected readiness as well as fresh Directory state;
    // a previously prepared queue never freezes permission to dispatch later.
    const admitted = await this.readQueue(handle.originalRequestId, actor);
    const before = await this.readCurrent(handle.originalRequestId, actor);
    const result = this.store.transaction(() => {
      const current = this.#current(handle.originalRequestId, actor), q = this.#queue(current);
      check(current.digest === before.digest && current.revision === before.revision && q?.state === 'prepared'
        && q.digest === admitted.digest
        && q.queue.id === handle.queueId && q.queue.envelopeSha256 === delegationDigest(envelope)
        && q.queue.currentAuthorizationSha256 === originalAuthorizationAnchor(actor).authorizationSha256
        && q.queue.transportExpiresAt > this.now() && this.#queueHandles.get(handle) === envelope,
      'Original queue dispatch is consumed, expired or changed.');
      const at = this.now();
      check(this.store.db.prepare("UPDATE original_management_queues SET state='dispatched',dispatched_at=? WHERE id=? AND state='prepared'")
        .run(at, q.queue.id).changes === 1, 'Original queue dispatch could not be claimed.');
      this.#queueHistory({ state: 'dispatched', digest: q.digest, dispatched_at: at, response_digest: '' }, q.queue);
      this.store.audit(current.mapping.actor, current.mapping.site, handle.originalRequestId, 'original-qualification-queue', 'dispatched');
      this.#queue(current); return envelope;
    });
    this.#queueHandles.delete(handle); return result;
  }
  async readQueue(requestId, actor) {
    const before = await this.readCurrent(requestId, actor);
    const saved = this.store.transaction(() => this.#queue(this.#current(requestId, actor)));
    check(saved, 'This original has no retained qualification attempt.');
    const admission = await this.#queueAdmission(actor, saved.queue.request);
    await this.readCurrent(requestId, actor);
    return this.store.transaction(() => {
      const current = this.#current(requestId, actor);
      check(current.digest === before.digest && current.revision === before.revision, 'Original authority changed during queue recovery.');
      const queue = this.#queue(current);
      check(queue?.digest === saved.digest, 'The original queue changed during protected readiness verification.');
      this.#sameQueueAdmission(queue.queue, admission); return queue;
    });
  }
  // The delegated client must validate response identity before calling this.
  // An uncertain response leaves its dispatched marker intact; there is no
  // callback reconstruction, retry, reset or replacement-job path.
  async recordQueue(requestId, queueId, actor, output) {
    const admitted = await this.readQueue(requestId, actor);
    const before = await this.readCurrent(requestId, actor);
    return this.store.transaction(() => {
      const current = this.#current(requestId, actor), q = this.#queue(current), binding = this.#binding(current);
      check(current.digest === before.digest && current.revision === before.revision && q?.state === 'dispatched'
        && q.digest === admitted.digest
        && q.queue.id === queueId && q.queue.transportExpiresAt > this.now(), 'Original queue response is expired, consumed or unrelated.');
      const context = validateExistingManagementQueue(output, { original: current, providerBinding: binding,
        request: q.queue.request, pins: q.queue.pins, dispatchedAt: q.dispatchedAt, now: this.now() });
      const body = JSON.stringify(context), hash = digest(body);
      check(this.store.db.prepare("UPDATE original_management_queues SET state='observed',response=?,response_digest=?,provider_job=? WHERE id=? AND state='dispatched'")
        .run(body, hash, context.binding.qualification_job_id, queueId).changes === 1, 'Original qualification response could not be retained.');
      this.#queueHistory({ state: 'observed', digest: q.digest, dispatched_at: q.dispatchedAt, response_digest: hash }, q.queue);
      this.store.audit(current.mapping.actor, current.mapping.site, requestId, 'original-qualification-queue', 'observed');
      return this.#queue(current);
    });
  }

  #queuedJob(jobId) {
    parse(z.string().uuid(), jobId);
    const row = this.store.db.prepare('SELECT original FROM original_management_queues WHERE provider_job=?').get(jobId);
    check(row, 'The original observed provider job is unavailable; callback data cannot create it.');
    const original = this.#load(row.original), queue = this.#queue(original);
    check(queue?.state === 'observed' && queue.context.binding.qualification_job_id === jobId,
      'The original provider job is missing or changed.');
    return { original, queue };
  }
  // Same bounded local check budget as the legacy management callback. An
  // independent counter/digest on the original attempt prevents lost query
  // rows from being mistaken for unused allowance after restart.
  #accessHistory(original, jobId) {
    const marker = this.store.db.prepare('SELECT access_count,access_digest FROM original_management_attempts WHERE attempt=?')
      .get(original.mapping.attempt);
    const rows = this.store.db.prepare('SELECT id,check_id,body,digest FROM original_management_access_queries WHERE job=? ORDER BY id LIMIT 513').all(jobId);
    check(marker && Number.isSafeInteger(marker.access_count) && marker.access_count >= 0 && marker.access_count <= 512
      && rows.length === marker.access_count && rows.every(r => digest(r.body) === r.digest)
      && marker.access_digest === (rows.length ? delegationDigest(rows.map(r => ({ ...r }))) : ''),
    'Original current-access history is missing or corrupt; its allowance cannot be reset.');
    return marker;
  }
  #currentQueued(jobId) {
    const value = this.#queuedJob(jobId);
    this.#accessHistory(value.original, jobId);
    return { ...value, revision: this.#retainedAuthority(value.original).revision };
  }
  originalQueuedJob(jobId) { return this.store.transaction(() => this.#currentQueued(jobId)); }
  async currentQueuedJob(jobId) {
    // Only a service-owned worker may recover this original; no current caller
    // or provider response can replace its actor, grant, policy or queue pins.
    const before = this.originalQueuedJob(jobId);
    await this.verifyStaff(before.original.mapping.actor);
    const admission = await this.#queueAdmission(before.original.mapping.actor, before.queue.queue.request);
    await this.verifyStaff(before.original.mapping.actor);
    return this.store.transaction(() => {
      const after = this.#currentQueued(jobId);
      check(after.original.digest === before.original.digest && after.revision === before.revision
        && after.queue.digest === before.queue.digest && delegationDigest(after.queue.context) === delegationDigest(before.queue.context),
      'The original queued job changed during current staff and protected readiness checks.');
      this.#sameQueueAdmission(after.queue.queue, admission); return after;
    });
  }

  // Metadata only: never retain content, signatures or OAuth tokens here. An
  // independent original-attempt counter/digest anchors every dispatch and
  // observation, including failures whose response is lost across a restart.
  #captureHistory(current) {
    const { original, queue } = current, m = original.mapping, job = queue.context.binding.qualification_job_id;
    const marker = this.store.db.prepare('SELECT capture_count,capture_digest FROM original_management_attempts WHERE attempt=?').get(m.attempt);
    const rows = this.store.db.prepare('SELECT * FROM original_management_capture_history WHERE job=? ORDER BY ordinal LIMIT 19').all(job);
    check(marker && Number.isSafeInteger(marker.capture_count) && marker.capture_count >= 0 && marker.capture_count <= 18
      && rows.length === marker.capture_count && marker.capture_digest === (rows.length ? delegationDigest(rows) : ''),
    'Original capture history is unavailable or changed; never reset or recapture.');
    let reads = 0, receipt = null, previous = null; const requests = new Set();
    const base = { schema: 1, job, original: m.requestId, queueDigest: queue.digest,
      bindingSha256: queue.context.binding_sha256, principal: m.principal, requester: m.requester };
    for (const [index, row] of rows.entries()) {
      check(row.ordinal === index + 1 && row.job === job && row.principal === m.principal && row.requester === m.requester
        && digest(row.body) === row.digest, 'Original capture event integrity is unavailable.');
      let raw; try { raw = JSON.parse(row.body); } catch { check(false, 'Original capture event is corrupt.'); }
      const event = parse(captureEventSchema, raw);
      check(Object.entries(base).every(([key, value]) => event[key] === value)
        && event.at >= (previous?.at ?? queue.dispatchedAt) && event.at <= this.now(), 'Original capture event binding changed.');
      if (event.phase === 'dispatched') {
        check(event.receipt === null && !requests.has(event.requestId)
          && event.action === (index === 0 ? 'capture' : 'read-capture'), 'Original capture dispatch history changed.');
        requests.add(event.requestId); if (event.action === 'read-capture') reads++;
      } else {
        check(previous?.phase === 'dispatched' && previous.requestId === event.requestId && previous.action === event.action
          && event.receipt?.bindingSha256 === base.bindingSha256
          && (!receipt || delegationDigest(receipt) === delegationDigest(event.receipt)), 'Original capture observation changed.');
        receipt = event.receipt;
      }
      previous = event;
    }
    check(reads <= 8, 'Original capture recovery budget is exhausted.');
    return { marker, rows, base, reads, receipt, previous,
      state: receipt ? 'captured' : rows.length ? 'uncertain' : 'not-started' };
  }
  #captureEvent(current, history, event) {
    const value = parse(captureEventSchema, { ...history.base, ...event }), body = JSON.stringify(value);
    this.store.db.prepare('INSERT INTO original_management_capture_history VALUES(?,?,?,?,?,?)')
      .run(value.job, history.rows.length + 1, body, digest(body), value.principal, value.requester);
    const rows = this.store.db.prepare('SELECT * FROM original_management_capture_history WHERE job=? ORDER BY ordinal').all(value.job);
    check(this.store.db.prepare('UPDATE original_management_attempts SET capture_count=?,capture_digest=? WHERE attempt=? AND capture_count=? AND capture_digest=?')
      .run(rows.length, delegationDigest(rows), current.original.mapping.attempt, history.marker.capture_count, history.marker.capture_digest).changes === 1,
    'Original capture history changed before commit.');
    this.store.audit(current.original.mapping.actor, current.original.mapping.site, value.original, 'original-qualification-capture',
      `${value.action}:${value.phase}`);
    return this.#captureHistory(current);
  }
  #sameCaptureAuthority(current, before) {
    check(current.original.digest === before.original.digest && current.revision === before.revision
      && current.queue.digest === before.queue.digest
      && delegationDigest(current.queue.context) === delegationDigest(before.queue.context), 'Original capture authority changed.');
  }
  // Pair the original queue and its capture history in one transaction after
  // the existing fresh staff/readiness checks. Internal consumers needing both
  // must not repeat the same remote membership checks to assemble one snapshot.
  async currentQueuedCapture(jobId) {
    const before = await this.currentQueuedJob(jobId);
    return this.store.transaction(() => {
      const current = this.#currentQueued(jobId); this.#sameCaptureAuthority(current, before);
      const history = this.#captureHistory(current);
      return { current, capture: { state: history.state, recoveryReads: history.reads, receipt: history.receipt } };
    });
  }
  async readCapture(jobId) { return (await this.currentQueuedCapture(jobId)).capture; }
  async claimCapture(jobId, action, within = () => {}) {
    check(['capture', 'read-capture'].includes(action), 'Only fixed capture and recovery actions are supported.');
    const before = await this.currentQueuedJob(jobId);
    return this.store.transaction(() => {
      within(); const current = this.#currentQueued(jobId); this.#sameCaptureAuthority(current, before);
      const history = this.#captureHistory(current);
      check(action === 'capture' ? history.state === 'not-started' : history.state !== 'not-started' && history.reads < 8,
        'Capture is consumed or recovery is unavailable; never retry the capture.');
      const event = { action, phase: 'dispatched', requestId: randomUUID(), at: this.now(), receipt: null };
      const saved = this.#captureEvent(current, history, event);
      within(); const handle = Object.freeze({ jobId, requestId: event.requestId });
      this.#captureHandles.set(handle, { current, event, digest: saved.marker.capture_digest }); return handle;
    });
  }
  async recordCapture(handle, value, within = () => {}) {
    const held = this.#captureHandles.get(handle); check(held, 'Original capture handle is unavailable or consumed.');
    this.#captureHandles.delete(handle);
    const receipt = parse(captureReceiptSchema, value), before = await this.currentQueuedJob(handle.jobId);
    return this.store.transaction(() => {
      within(); const current = this.#currentQueued(handle.jobId);
      this.#sameCaptureAuthority(current, before); this.#sameCaptureAuthority(current, held.current);
      const history = this.#captureHistory(current);
      check(history.marker.capture_digest === held.digest && receipt.bindingSha256 === history.base.bindingSha256
        && (!history.receipt || delegationDigest(history.receipt) === delegationDigest(receipt)),
      'Capture response is stale, superseded or differs from the retained artifact.');
      this.#captureEvent(current, history, { ...held.event, phase: 'observed', at: this.now(), receipt });
      within(); return receipt;
    });
  }
  // Independent original/attempt anchors make deleted or partial worker history
  // a refusal. Only prepare() creates the unused seed, in the original transaction.
  #workerRows(original) {
    // Bound each persisted body before materializing it. Four rows detect any
    // impossible extra event without scanning or accepting unbounded history.
    return this.store.db.prepare(`SELECT original,ordinal,
      CASE WHEN length(CAST(body AS BLOB))<=32768 THEN body ELSE NULL END AS body,
      digest,dispatch,run FROM original_management_worker_history WHERE original=? ORDER BY ordinal LIMIT 4`).all(original);
  }
  #appendWorkerHistory(mapping, value, previousDigest) {
    const rows = this.#workerRows(mapping.requestId);
    check(rows.length === { unused: 0, prepared: 1, observed: 2 }[value.state], 'Original worker history is already consumed.');
    const body = delegationCanonical(value);
    check(Buffer.byteLength(body) <= 32768, 'Original worker history exceeds its metadata bound.');
    const dispatch = value.state === 'prepared' ? value.prepared.request.dispatch_id : null;
    const run = value.state === 'observed' ? value.retained.provenance.run_id : null;
    this.store.db.prepare('INSERT INTO original_management_worker_history VALUES(?,?,?,?,?,?)')
      .run(mapping.requestId, rows.length, body, digest(body), dispatch, run);
    const after = this.#workerRows(mapping.requestId);
    check(this.store.db.prepare('UPDATE original_management_attempts SET worker_history_digest=? WHERE attempt=? AND worker_history_digest=?')
      .run(delegationDigest(after), mapping.attempt, previousDigest).changes === 1, 'Original worker history changed before commit.');
  }
  #workerHistory(current) {
    const { original } = current, m = original.mapping;
    check(m.workerConfigurationSha256 && typeof this.workerConfiguration === 'function',
      'This original has no prepared worker history; existing jobs cannot be enrolled or backfilled.');
    const configuration = parse(workerConfigurationSchema, this.workerConfiguration());
    check(delegationDigest(configuration) === m.workerConfigurationSha256, 'Original worker policy or source changed.');
    const marker = this.store.db.prepare('SELECT worker_history_digest FROM original_management_attempts WHERE attempt=?').get(m.attempt);
    const rows = this.#workerRows(m.requestId);
    check(rows.length >= 1 && rows.length <= 3 && marker?.worker_history_digest === delegationDigest(rows),
      'Original worker history is missing, partial or changed; never redispatch.');
    const seed = { schema: 2, state: 'unused', original: m.requestId, original_sha256: original.digest, configuration };
    const values = rows.map((row, index) => {
      check(row.original === m.requestId && row.ordinal === index && digest(row.body) === row.digest,
        'Original worker event integrity changed.');
      let value; try { value = JSON.parse(row.body); } catch { check(false, 'Original worker event is corrupt.'); }
      check(delegationCanonical(value) === row.body, 'Original worker event encoding changed.'); return value;
    });
    check(delegationDigest(values[0]) === delegationDigest(seed) && rows[0].dispatch === null && rows[0].run === null,
      'Original worker seed differs from the genuine original.');
    if (rows.length > 1) {
      const v = values[1], p = v.prepared, capture = this.#captureHistory(current);
      check(Object.keys(v).sort().join(',') === 'context,prepared,schema,state' && v.schema === 2 && v.state === 'prepared'
        && p && Object.keys(p).sort().join(',') === 'app_actor_id,context_sha256,evidence_policy_sha256,original_expires_at,request,requested_at'
        && capture.state === 'captured', 'Original worker preparation changed.');
      const context = existingManagementTypes.evidence.context.parse(v.context), request = parseExistingWorkerRecord('dispatch_request', p.request);
      const b = current.queue.context.binding, receipt = capture.receipt;
      check(delegationDigest(context.binding) === delegationDigest(b) && context.management_context_sha256 === current.queue.context.binding_sha256
        && context.capture_artifact_sha256 === receipt.captureArtifactSha256 && context.measurement_record_sha256 === receipt.measurementSha256
        && context.evidence_policy_epoch === configuration.policy.policy_epoch && context.evidence_policy_sha256 === delegationDigest(configuration.policy)
        && p.context_sha256 === delegationDigest(context) && p.evidence_policy_sha256 === context.evidence_policy_sha256
        && request.context_sha256 === p.context_sha256 && request.worker_contract_sha256 === existingWorkerContractSha256
        && p.app_actor_id === configuration.app_actor_id && p.original_expires_at === b.expires_at * 1000
        && Number.isSafeInteger(p.requested_at) && p.requested_at >= b.created_at * 1000 && p.requested_at <= this.now()
        && p.original_expires_at > this.now() && rows[1].dispatch === request.dispatch_id && rows[1].run === null,
      'Original worker preparation no longer matches captured source, policy or lifetime.');
      if (rows.length === 3) {
        const v2 = values[2], retained = parseExistingWorkerRecord('retained_record', v2.retained), o = retained.observation, prov = retained.provenance;
        check(Object.keys(v2).sort().join(',') === 'retained,schema,state' && v2.schema === 2 && v2.state === 'observed'
          && delegationDigest(retained.context) === delegationDigest(context)
          && delegationDigest(Object.fromEntries(Object.entries(o).filter(([key]) => !['run_id', 'observed_at'].includes(key)))) === delegationDigest(p)
          && rows[2].dispatch === null && rows[2].run === o.run_id && prov.run_id === o.run_id
          && prov.dispatch_id === request.dispatch_id && prov.dispatch_request_sha256 === delegationDigest(request)
          && prov.dispatch_record_sha256 === delegationDigest(o) && workerSourceFields.every(k => prov[k] === configuration.policy[k])
          && o.observed_at >= p.requested_at && o.observed_at <= this.now(), 'Original worker observation changed.');
      }
    }
    return { state: values.at(-1).state, value: values.at(-1), configuration, historyDigest: marker.worker_history_digest };
  }
  // No GitHub call or source admission: reserve one opaque protected dispatch
  // before I/O. A lost response or restart consumes it permanently.
  async prepareWorkerDispatch(jobId, measurement, within = () => {}) {
    const measured = existingManagementTypes.qualification.measurement_output.parse(measurement);
    const { current: before } = await this.currentQueuedCapture(jobId);
    return this.store.transaction(() => {
      within();
      const current = this.#currentQueued(jobId); this.#sameCaptureAuthority(current, before);
      const history = this.#workerHistory(current), capture = this.#captureHistory(current), b = current.queue.context.binding;
      check(history.state === 'unused' && capture.state === 'captured' && measured.qualification_job_id === jobId
        && measured.binding_sha256 === current.queue.context.binding_sha256
        && measured.measurement_record_sha256 === delegationDigest(measured.measurements)
        && measured.measurement_record_sha256 === capture.receipt.measurementSha256,
      'A worker requires the exact captured original and its independent measurements.');
      const policy = history.configuration.policy;
      const context = existingManagementTypes.evidence.context.parse({ binding: b, management_context_sha256: current.queue.context.binding_sha256,
        capture_artifact_sha256: capture.receipt.captureArtifactSha256, measurement_record_sha256: capture.receipt.measurementSha256,
        parity_sha256: measured.measurements.parity_sha256, evidence_policy_epoch: policy.policy_epoch, evidence_policy_sha256: delegationDigest(policy) });
      const request = parseExistingWorkerRecord('dispatch_request', { schema: 2, dispatch_id: randomUUID(),
        context_sha256: delegationDigest(context), worker_contract_sha256: existingWorkerContractSha256 });
      const prepared = { request, context_sha256: request.context_sha256, evidence_policy_sha256: context.evidence_policy_sha256,
        requested_at: this.now(), original_expires_at: b.expires_at * 1000, app_actor_id: history.configuration.app_actor_id };
      this.#appendWorkerHistory(current.original.mapping, { schema: 2, state: 'prepared', context, prepared }, history.historyDigest);
      const saved = this.#workerHistory(current), handle = Object.freeze({ jobId, dispatchId: request.dispatch_id });
      this.#workerHandles.set(handle, { current, historyDigest: saved.historyDigest });
      this.store.audit(current.original.mapping.actor, current.original.mapping.site, current.original.mapping.requestId, 'original-worker-dispatch', 'prepared');
      within();
      return { handle, request: structuredClone(request) };
    });
  }
  // A private dispatcher must independently verify the actual protected GitHub
  // run before calling this. Public tools/callbacks never receive the handle.
  async recordWorkerDispatch(handle, runId, within = () => {}) {
    const held = this.#workerHandles.get(handle); check(held, 'Original worker handle is missing or consumed.');
    this.#workerHandles.delete(handle); existingManagementTypes.evidence.id.parse(runId);
    const before = await this.currentQueuedJob(handle.jobId);
    return this.store.transaction(() => {
      within();
      const current = this.#currentQueued(handle.jobId); this.#sameCaptureAuthority(current, before); this.#sameCaptureAuthority(current, held.current);
      const history = this.#workerHistory(current);
      check(history.state === 'prepared' && history.historyDigest === held.historyDigest
        && this.now() - history.value.prepared.requested_at < 60000, 'Original worker dispatch changed or expired.');
      const p = history.value.prepared, observation = { ...p, run_id: runId, observed_at: this.now() };
      const provenance = existingManagementTypes.evidence.provenance.parse({ ...Object.fromEntries(workerSourceFields.map(k => [k, history.configuration.policy[k]])),
        dispatch_id: p.request.dispatch_id, dispatch_request_sha256: delegationDigest(p.request), dispatch_record_sha256: delegationDigest(observation),
        run_id: runId, run_attempt: 1 });
      const retained = parseExistingWorkerRecord('retained_record', { context: history.value.context, provenance, observation });
      this.#appendWorkerHistory(current.original.mapping, { schema: 2, state: 'observed', retained }, history.historyDigest);
      this.#workerHistory(current);
      this.store.audit(current.original.mapping.actor, current.original.mapping.site, current.original.mapping.requestId, 'original-worker-dispatch', 'observed');
      within();
      return retained;
    });
  }
  originalWorkerHistory(jobId) {
    return this.store.transaction(() => {
      const current = this.#currentQueued(jobId);
      return { ...this.#workerHistory(current), current };
    });
  }
  #workerDispatch(dispatchId) {
    parse(z.string().uuid(), dispatchId);
    const row = this.store.db.prepare('SELECT original FROM original_management_worker_history WHERE dispatch=?').get(dispatchId);
    check(row, 'The original worker dispatch is unavailable; a pointer cannot create it.');
    const original = this.#load(row.original), queue = this.#queue(original);
    check(queue?.state === 'observed', 'The original worker queue is unavailable.');
    const current = this.#currentQueued(queue.context.binding.qualification_job_id), history = this.#workerHistory(current);
    check(history.state === 'observed' && history.value.retained.provenance.dispatch_id === dispatchId,
      'The original worker run has not been independently observed.');
    return { ...history, current };
  }
  originalWorkerDispatch(dispatchId) { return this.store.transaction(() => this.#workerDispatch(dispatchId)); }
  // The private reader signs and checks final state inside one audit transaction.
  // The synchronous read callback cannot start another transaction or do I/O.
  withOriginalWorkerDispatch(dispatchId, readback) {
    return this.store.transaction(() => {
      const result = readback(() => this.#workerDispatch(dispatchId));
      check(!result?.then, 'Worker readback must complete synchronously inside its original transaction.');
      return result;
    });
  }
  async readWorkerHistory(jobId) {
    const before = await this.currentQueuedJob(jobId);
    return this.store.transaction(() => {
      const current = this.#currentQueued(jobId); this.#sameCaptureAuthority(current, before);
      const history = this.#workerHistory(current);
      return { ...history, current };
    });
  }
  recordQueuePendingRevocation(jobId) {
    return this.store.transaction(() => {
      const { original } = this.#queuedJob(jobId), m = original.mapping;
      this.#accessHistory(original, jobId);
      const result = this.principals.pending(m.principal, m.requester);
      if (result.changed) this.store.audit(m.actor, m.site, m.requestId, 'original-queue-access', 'pending-revocation');
      return { state: 'pending-revocation', revision: result.revision, committed_denial: false };
    });
  }
  answerQueueAccess(value, queryDigest, signAnswer) {
    const query = parse(existingManagementTypes.qualification.veto_query, value);
    return this.store.transaction(() => {
      const { original, queue } = this.#queuedJob(query.qualification_job_id), m = original.mapping, b = queue.context.binding;
      const now = Math.floor(this.now() / 1000);
      check(queryDigest === delegationDigest(query) && query.issued_at <= now && query.expires_at > now
        && query.expires_at > query.issued_at && query.expires_at - query.issued_at <= 5
        && query.requester_binding_sha256 === m.requester && query.management_context_sha256 === queue.context.binding_sha256
        && ['site_id', 'site_url', 'target', 'authorization_policy_sha256'].every(k => delegationDigest(query[k]) === delegationDigest(b[k]))
        && query.expires_at <= b.expires_at,
      'The current-access query does not bind the original observed qualification job.');
      const principal = this.principals.row(m.principal, m.requester);
      let state = principal.state;
      if (state === 'clear') {
        try { this.#retainedAuthority(original); } catch { state = 'denied'; }
      }
      const history = this.#accessHistory(original, b.qualification_job_id);
      check(history.access_count < 512
        && !this.store.db.prepare('SELECT id FROM original_management_access_queries WHERE id=? OR check_id=?').get(query.query_id, query.check_id),
      'The original current-access query was already consumed or its check budget is exhausted.');
      const expires = Math.min(b.expires_at, Math.floor(m.authorization.expiresAt / 1000));
      const response = signAnswer(state, principal.revision, expires);
      check(response && !response.then && query.expires_at > Math.floor(this.now() / 1000),
        'Current-access signing must complete synchronously before the query expires.');
      const body = JSON.stringify({ original: m.requestId, job: b.qualification_job_id, query: query.query_id,
        check: query.check_id, queryDigest, phase: query.phase, state, revision: principal.revision,
        principal: m.principal, requester: m.requester, actor: m.actor });
      this.store.db.prepare('INSERT INTO original_management_access_queries VALUES(?,?,?,?,?)')
        .run(query.query_id, b.qualification_job_id, query.check_id, body, digest(body));
      const rows = this.store.db.prepare('SELECT id,check_id,body,digest FROM original_management_access_queries WHERE job=? ORDER BY id LIMIT 513')
        .all(b.qualification_job_id).map(r => ({ ...r }));
      check(this.store.db.prepare('UPDATE original_management_attempts SET access_count=access_count+1,access_digest=? WHERE attempt=? AND access_count=? AND access_digest=?')
        .run(delegationDigest(rows), m.attempt, history.access_count, history.access_digest).changes === 1,
      'Current-access history changed before its one-time claim.');
      this.store.audit(m.actor, m.site, m.requestId, 'original-queue-access', state);
      this.#accessHistory(original, b.qualification_job_id); return response;
    });
  }
}
