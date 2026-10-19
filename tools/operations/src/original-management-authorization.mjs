import { z } from 'zod';
import { readFileSync } from 'node:fs';
import { randomBytes, randomUUID, sign } from 'node:crypto';
import { originalAuthorizationAnchor } from './auth.mjs';
import { AccessPrincipals, accessActorSchema, requesterDigest } from './access-principal.mjs';
import { IdentitySigner, challengeSchema, delegationCanonical, delegationDigest, delegationIssuer, delegationAudience } from './delegation.mjs';
import { digest, registryTargetSchema, requireThat, sha } from './contracts.mjs';
import { managementSchemas, validateManagementEvidence } from './content-management-contract.mjs';
import { compileManagementEvidenceSchema } from './content-management-schema.mjs';
import { existingManagementHashes, existingManagementProtocol } from './management-existing-contracts.mjs';

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
const reviewSchema = z.object({ operationId: z.string().uuid(), reviewedDigest: sha, expiresAt: z.number().int().positive().safe(),
  beforeSha256: sha, candidateSha256: sha, migrationSha256: sha }).strict();
const mappingSchema = z.object({ schema: z.literal(1), attempt: z.string().uuid(), requestId: z.string().uuid(),
  actor: accessActorSchema, site: registryTargetSchema, requester: sha, principal: sha, pins: pinsSchema,
  memberPolicySha256: sha, argumentsSha256: sha, envelopeSha256: sha,
  authorization: z.object({ schema: z.literal(1), authorizationSha256: sha, expiresAt: z.number().int().positive().safe() }).strict(),
  transportExpiresAt: z.number().int().positive().safe(),
  target: z.object({ site_id: z.number().int().positive().safe(), site_url: z.string().url(),
    post_type: z.enum(['post', 'page']), post_id: z.number().int().positive().safe(), expected_snapshot_sha256: sha }).strict(),
}).strict();

// Private source preparation only. main.mjs does not construct this ledger or
// register an endpoint. Its original-current control assertions do not qualify
// a provider protocol or permit a write. Fresh readiness, provider readback,
// complete source/QA admission and later access vetoes remain separate.
export class OriginalManagementAuthorizationLedger {
  #handles = new WeakMap();
  #controlHandles = new WeakMap();
  constructor(store, { signer, registry, policy, verifyStaff, now = () => Date.now() }) {
    check(signer instanceof IdentitySigner && typeof policy === 'function' && typeof verifyStaff === 'function',
      'The existing service identity signer, policy and active staff check are required.');
    Object.assign(this, { store, signer, registry, policy, verifyStaff, now });
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
      requester TEXT NOT NULL, state TEXT NOT NULL, PRIMARY KEY(id,state));`);
    const columns = this.store.db.prepare('PRAGMA table_info(original_management_attempts)').all();
    if (!columns.some(c => c.name === 'bind_control'))
      this.store.db.exec("ALTER TABLE original_management_attempts ADD COLUMN bind_control TEXT NOT NULL DEFAULT ''");
    if (!columns.some(c => c.name === 'control_count'))
      this.store.db.exec('ALTER TABLE original_management_attempts ADD COLUMN control_count INTEGER NOT NULL DEFAULT 0');
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
      check(retained && digest(retained.body) === retained.digest, 'The original preview mapping is missing or corrupt.');
      let value; try { value = JSON.parse(retained.body); } catch { check(false, 'The original preview mapping is corrupt.'); }
      review = parse(reviewSchema, value);
      check(review.operationId === retained.operation && review.beforeSha256 === mapping.target.expected_snapshot_sha256,
        'The original preview mapping differs from its retained request.');
    } else check(!retained, 'The original preview history is inconsistent.');
    return { mapping, state: row.state, digest: row.digest, ...(review ? { review } : {}) };
  }
  #current(requestId, currentActor) {
    const { actor } = this.#actor(currentActor), retained = this.#load(requestId), m = retained.mapping;
    check(requesterDigest(actor) === m.requester && actor.googleConnection === m.actor.googleConnection,
      'This preview belongs to a different verified person.');
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
  async prepare(value, input, challenge, attemptId) {
    parse(z.string().uuid(), attemptId);
    const args = managementSchemas['preview-content-edit-v1'].input.parse(input);
    check(args.post_id > 0, 'Only an existing exact content record can retain original authorization.');
    const initial = this.#actor(value), site = this.#site(initial.actor, args), pins = this.#pins();
    const principalBefore = this.store.db.prepare('SELECT * FROM content_access_principals WHERE principal=?').get(digest(initial.actor.googleSubject));
    const memberPolicySha256 = delegationDigest(this.registry.member(initial.actor));
    const c = challengeSchema.parse(challenge);
    check(c.policy_sha256 === pins.delegation_policy_sha256, 'The preview challenge uses a different delegation policy.');
    await this.verifyStaff(initial.actor);
    return this.store.transaction(() => {
      const { actor, authorization } = this.#actor(value);
      const principalAfter = this.store.db.prepare('SELECT * FROM content_access_principals WHERE principal=?').get(digest(actor.googleSubject));
      check(delegationDigest(principalBefore ?? null) === delegationDigest(principalAfter ?? null),
        'Principal history changed during active staff verification.');
      check(delegationDigest(this.#pins()) === delegationDigest(pins)
        && delegationDigest(this.#site(actor, args, site)) === delegationDigest(site)
        && delegationDigest(this.registry.member(actor)) === memberPolicySha256,
      'Original authority changed during active staff verification.');
      check(!this.store.db.prepare('SELECT attempt FROM original_management_attempts WHERE attempt=?').get(attemptId)
        && !this.store.db.prepare('SELECT request FROM original_management_authorizations WHERE attempt=?').get(attemptId),
      'This original preview attempt is already retained; read its state instead of retrying.');
      const envelope = Object.freeze(this.signer.sign(value, 'mrn-mainwp/preview-content-edit-v1', args, c));
      const claims = JSON.parse(Buffer.from(envelope.payload_base64, 'base64'));
      const mapping = parse(mappingSchema, { schema: 1, attempt: attemptId, requestId: claims.request_id,
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
      this.store.audit(actor, site, mapping.requestId, 'original-authorization-retained', 'prepared');
      this.#current(mapping.requestId, value);
      const handle = Object.freeze({ requestId: mapping.requestId });
      this.#handles.set(handle, { envelope, authorizationSha256: authorization.authorizationSha256 });
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
  // full candidate/migration reconstruction, then retain just its immutable
  // operation pointer and hashes. Missing review history can never be rebound.
  async recordReview(requestId, actor, input, evidence, profile) {
    const before = await this.readCurrent(requestId, actor), m = before.mapping;
    check(before.state === 'dispatched' && delegationDigest(input) === m.argumentsSha256,
      'Only the exact dispatched preview may retain its original review.');
    const { review } = validateManagementEvidence(evidence, { site: { id: m.target.site_id, url: m.target.site_url },
      target: { kind: 'wordpress-content', post_type: m.target.post_type, post_id: m.target.post_id }, request: input, profile, now: this.now() });
    const projection = parse(reviewSchema, { operationId: review.operation_id, reviewedDigest: review.digest,
      expiresAt: review.expires_at * 1000, beforeSha256: review.before_sha256, candidateSha256: review.candidate_sha256,
      migrationSha256: review.migration_artifact.sha256 });
    return this.store.transaction(() => {
      const current = this.#current(requestId, actor);
      check(current.state === 'dispatched' && current.digest === before.digest && current.revision === before.revision,
        'The original dispatch changed before review retention.');
      const body = JSON.stringify(projection);
      this.store.db.prepare('INSERT INTO original_management_reviews VALUES(?,?,?,?)')
        .run(requestId, projection.operationId, body, digest(body));
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
      check(after.digest === before.digest && after.revision === before.revision && after.state === before.state,
        'Original authorization changed during active staff verification.');
      return after;
    });
  }

  // Every control has independent append-only issuance/dispatch history. Lost
  // or rolled-back primary state cannot turn an uncertain bind into first use.
  #controls(current) {
    const m = current.mapping;
    const rows = this.store.db.prepare('SELECT * FROM original_management_controls WHERE original=? LIMIT 66').all(m.requestId);
    const history = this.store.db.prepare('SELECT * FROM original_management_control_history WHERE original=? LIMIT 131').all(m.requestId);
    const attempt = this.store.db.prepare('SELECT bind_control,control_count FROM original_management_attempts WHERE attempt=?').get(m.attempt);
    check(rows.length <= 65 && history.length <= 130, 'Original authorization control history exceeds its bound.');
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
      const states = row.state === 'prepared' ? ['prepared'] : row.state === 'dispatched' ? ['prepared', 'dispatched'] : [];
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
    return values;
  }
  #appendControlHistory(row, m, state) {
    this.store.db.prepare('INSERT INTO original_management_control_history VALUES(?,?,?,?,?,?,?)')
      .run(row.id, m.requestId, row.kind, row.digest, m.principal, m.requester, state);
  }
  async #prepareControl(requestId, actor, kind) {
    const before = await this.readCurrent(requestId, actor);
    return this.store.transaction(() => {
      const current = this.#current(requestId, actor), m = current.mapping, review = current.review;
      check(current.state === 'reviewed' && current.digest === before.digest && current.revision === before.revision,
        'Original authority changed before control issuance.');
      const controls = this.#controls(current), bound = controls.find(row => row.kind === 'bind');
      check(controls.length < 65 && (kind === 'bind' ? !bound : bound?.state === 'dispatched'),
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
      this.#controlHandles.set(handle, input); return handle;
    });
  }
  // Neither method accepts caller identity, expiry, hashes or protocol fields.
  // Bind requests a provider proof of its source-selected original domain; the
  // legacy review shape alone never establishes that proof or admission.
  prepareBind(requestId, actor) { return this.#prepareControl(requestId, actor, 'bind'); }
  prepareRead(requestId, actor) { return this.#prepareControl(requestId, actor, 'read'); }
  async claimControlDispatch(handle, actor) {
    const input = this.#controlHandles.get(handle);
    check(input, 'Original authorization dispatch handle is unavailable or consumed.');
    const before = await this.readCurrent(handle.originalRequestId, actor);
    const result = this.store.transaction(() => {
      const current = this.#current(handle.originalRequestId, actor);
      check(current.digest === before.digest && current.revision === before.revision
        && this.#controlHandles.get(handle) === input, 'Original authorization changed before dispatch.');
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
}
