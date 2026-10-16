import { z } from 'zod';
import { digest, requireThat, sha, registryTargetSchema } from './contracts.mjs';
import { delegationDigest } from './delegation.mjs';
import { AccessPrincipals, accessActorSchema, requesterDigest } from './access-principal.mjs';
import { managementCaptureContract, managementCaptureTypes, validateManagementContext } from './content-management-capture.mjs';
import { compileManagementSchema } from './content-management-schema.mjs';

export const managementQualificationRequestSchema = compileManagementSchema({
  $ref: '#/$defs/request_input', $defs: managementCaptureContract.$defs,
});
const pinsSchema = z.object({ authorization_policy_sha256: sha, provider_source_sha: z.string().regex(/^[a-f0-9]{40}$/),
  installed_manifest_sha256: sha, source_binding_sha256: sha, job_intent_ttl_seconds: z.number().int().min(60).max(900) }).strict();
const mappingSchema = z.object({ actor: accessActorSchema, site: registryTargetSchema,
  request: managementQualificationRequestSchema, context: managementCaptureTypes.context, pins: pinsSchema,
  reviewExpiresAt: z.number().int().positive().safe(), requester: sha, principal: sha }).strict();
const parse = (schema, value) => {
  const result = schema.safeParse(value);
  requireThat(result.success, 'ACCESS_MAPPING', 'An exact authenticated management queue mapping is required.'); return result.data;
};
export const validateManagementQueuePins = value => parse(pinsSchema, value);
const same = (a, b) => delegationDigest(a) === delegationDigest(b);
const bound = (mapping, connectionId) => {
  const { actor, request, context, pins, site } = mapping, b = context.binding;
  requireThat(context.state === 'queued' && context.capture_artifact_sha256 === null
    && context.binding_sha256 === delegationDigest(b) && b.target.post_id > 0 && b.expected_target_revision === 0
    && mapping.requester === requesterDigest(actor) && mapping.principal === digest(actor.googleSubject)
    && b.requester_binding_sha256 === mapping.requester && actor.googleConnection === connectionId
    && site.url === request.site_url && site.management === 'mainwp'
    && ['site_id', 'site_url', 'operation_id', 'reviewed_digest'].every(key => same(request[key], b[key]))
    && Object.keys(pins).every(key => pins[key] === b[key])
    && b.expires_at === Math.min(mapping.reviewExpiresAt, b.created_at + pins.job_intent_ttl_seconds),
  'ACCESS_MAPPING', 'The original management requester, source or intent binding is invalid.');
};

// Private service state. A worker, callback pointer or fresh login cannot create
// an original-requester map. No raw token, page body or signature is retained.
export class ManagementAccessLedger {
  constructor(store, { connectionId, registry, now = () => Date.now() }) {
    Object.assign(this, { store, connectionId, registry, now });
    this.principals = new AccessPrincipals(store, { connectionId, registry, now });
    store.db.exec(`CREATE TABLE IF NOT EXISTS management_access_jobs(
      id TEXT PRIMARY KEY, operation TEXT NOT NULL UNIQUE, body TEXT NOT NULL, digest TEXT NOT NULL);
      CREATE TABLE IF NOT EXISTS management_access_queries(id TEXT PRIMARY KEY, job TEXT NOT NULL, check_id TEXT NOT NULL UNIQUE,
        query_digest TEXT NOT NULL, state TEXT NOT NULL, revision INTEGER NOT NULL);
      CREATE TABLE IF NOT EXISTS management_access_audit(seq INTEGER PRIMARY KEY, time INTEGER NOT NULL, job TEXT NOT NULL,
        event TEXT NOT NULL, phase TEXT NOT NULL, state TEXT NOT NULL, revision INTEGER NOT NULL);`);
  }
  audit(job, event, phase, state, revision) {
    this.store.db.prepare('INSERT INTO management_access_audit(time,job,event,phase,state,revision) VALUES(?,?,?,?,?,?)')
      .run(this.now(), job, event, phase, state, revision);
  }
  site(actor, input, saved) {
    const request = parse(managementQualificationRequestSchema, input);
    const site = parse(registryTargetSchema, saved
      ? this.registry.current(actor, parse(registryTargetSchema, saved), 'content')
      : this.registry.resolve(actor, request.site_url, undefined, 'content'));
    requireThat(site.url === request.site_url && site.management === 'mainwp',
      'ACCESS_MAPPING', 'Qualification must bind the exact authorized MainWP website and environment.');
    return site;
  }
  assertQueueAllowed(value, input, saved) {
    const actor = this.principals.actor(value);
    this.principals.assertAllowed(actor); return this.site(actor, input, saved);
  }
  // expected comes from service-owned protected-worker readiness and original
  // preview reconstruction, never from a public tool or the queue response.
  recordQueued(value, input, output, expected, admittedSite) {
    const actor = this.principals.actor(value), request = parse(managementQualificationRequestSchema, input);
    const pins = validateManagementQueuePins(expected?.pins);
    const { context, original } = validateManagementContext(output, { evidence: expected.evidence,
      request: expected.request, profile: expected.profile, now: this.now() });
    return this.store.transaction(() => {
      const site = this.assertQueueAllowed(actor, request, parse(registryTargetSchema, admittedSite));
      const mapping = { actor, site, request, context, pins, reviewExpiresAt: original.review.expires_at,
        requester: requesterDigest(actor), principal: digest(actor.googleSubject) };
      bound(mapping, this.connectionId);
      const b = context.binding;
      const existing = this.store.db.prepare('SELECT body,digest FROM management_access_jobs WHERE id=? OR operation=?')
        .get(b.qualification_job_id, b.operation_id);
      if (existing) {
        const originalMapping = this.decode(existing);
        requireThat(originalMapping.requester === mapping.requester && same(originalMapping.request, request)
          && same(originalMapping.context, context) && same(originalMapping.pins, pins)
          && originalMapping.reviewExpiresAt === mapping.reviewExpiresAt && same(originalMapping.site, site),
        'ACCESS_MAPPING', 'An immutable management queue mapping cannot be replaced.');
        this.principals.actor(originalMapping.actor);
        this.site(originalMapping.actor, originalMapping.request, originalMapping.site);
        return originalMapping;
      }
      this.principals.retain(mapping.principal, mapping.requester);
      const body = JSON.stringify(mapping);
      this.store.db.prepare('INSERT INTO management_access_jobs VALUES(?,?,?,?)').run(b.qualification_job_id, b.operation_id, body, digest(body));
      this.audit(b.qualification_job_id, 'queue-mapped', '', 'clear', this.principals.row(mapping.principal, mapping.requester).revision);
      return mapping;
    });
  }
  decode(row) {
    requireThat(row && digest(row.body) === row.digest, 'ACCESS_MAPPING', 'The original management mapping is missing or corrupt.');
    let value; try { value = JSON.parse(row.body); } catch { requireThat(false, 'ACCESS_MAPPING', 'The original management mapping is corrupt.'); }
    const mapping = parse(mappingSchema, value); bound(mapping, this.connectionId); return mapping;
  }
  recordPendingRevocation(jobId) {
    return this.store.transaction(() => {
      const mapping = this.decode(this.store.db.prepare('SELECT body,digest FROM management_access_jobs WHERE id=?').get(jobId));
      const { changed, revision } = this.principals.pending(mapping.principal, mapping.requester);
      if (changed) this.audit(jobId, 'revocation-pending', '', 'pending-revocation', revision);
      return { state: 'pending-revocation', revision, committed_denial: false };
    });
  }
  answer(query, queryDigest, signAnswer) {
    return this.store.transaction(() => {
      const mapping = this.decode(this.store.db.prepare('SELECT body,digest FROM management_access_jobs WHERE id=?').get(query.qualification_job_id));
      const b = mapping.context.binding;
      requireThat(mapping.requester === query.requester_binding_sha256
        && query.management_context_sha256 === mapping.context.binding_sha256
        && ['site_id', 'site_url', 'target', 'authorization_policy_sha256'].every(key => same(b[key], query[key]))
        && b.expires_at >= query.expires_at && b.expires_at > Math.floor(this.now() / 1000),
      'ACCESS_BINDING', 'The query does not bind the original management intent.');
      const row = this.principals.row(mapping.principal, mapping.requester);
      let state = row.state;
      if (mapping.actor.expiresAt <= this.now()) state = 'denied';
      if (state === 'clear') {
        try { this.site(this.principals.actor(mapping.actor), mapping.request, mapping.site); } catch { state = 'denied'; }
      }
      const count = this.store.db.prepare('SELECT count(*) AS n FROM management_access_queries WHERE job=?').get(query.qualification_job_id).n;
      requireThat(count < 512 && !this.store.db.prepare('SELECT id FROM management_access_queries WHERE id=? OR check_id=?').get(query.query_id, query.check_id),
        'ACCESS_REPLAY', 'The management query is consumed or its bounded check budget is exhausted.');
      const expires = state === 'clear' ? Math.min(b.expires_at, Math.floor(mapping.actor.expiresAt / 1000)) : b.expires_at;
      const response = signAnswer(state, row.revision, expires);
      requireThat(response && !response.then, 'ACCESS_UNAVAILABLE', 'Private signing must finish inside the ledger transaction.');
      this.store.db.prepare('INSERT INTO management_access_queries VALUES(?,?,?,?,?,?)')
        .run(query.query_id, query.qualification_job_id, query.check_id, queryDigest, state, row.revision);
      this.audit(query.qualification_job_id, 'veto-answered', query.phase, state, row.revision); return response;
    });
  }
}
