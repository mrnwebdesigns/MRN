import { readFileSync } from 'node:fs';
import { z } from 'zod';
import { digest, requireThat, requireWriteScope, sha, Registry, registryTargetSchema } from './contracts.mjs';
import { delegationDigest } from './delegation.mjs';
import { compileContentSchema } from './content-schema.mjs';

const contract = JSON.parse(readFileSync(new URL('../contracts/content-qualification-v1.json', import.meta.url), 'utf8'));
const compile = name => compileContentSchema({ $ref: `#/definitions/${name}` }, contract.definitions);
export const qualificationRequestSchema = compile('request_input');
const requestSchema = qualificationRequestSchema, jobSchema = compile('job_output');
const actorFields = { subject: z.string(), googleSubject: z.string().regex(/^[0-9]{1,64}$/),
  verifiedEmail: z.string().email().max(254).regex(/^[^@\s]+@mrnwebdesigns\.com$/),
  identityProvider: z.literal('google-oauth2'), hostedDomain: z.literal('mrnwebdesigns.com'),
  googleConnection: z.string().regex(/^con_[A-Za-z0-9]+$/), expiresAt: z.number().int().positive().safe(),
  scopes: z.array(z.string()).max(2),
};
const actorSchema = z.object(actorFields).strict().refine(actor => actor.subject === `google-oauth2|${actor.googleSubject}`
  && actor.verifiedEmail === actor.verifiedEmail.toLowerCase());
const expectedSchema = z.object({ authorization_policy_sha256: sha, recipe_catalog_sha256: sha, recipe_sha256: sha }).strict();
export const validateQueuePins = value => parse(expectedSchema, value);
const mappingSchema = z.object({ actor: actorSchema, site: registryTargetSchema, request: requestSchema, job: jobSchema, admission: expectedSchema, requester: sha, principal: sha }).strict();
const parse = (schema, value) => {
  const result = schema.safeParse(value);
  requireThat(result.success, 'ACCESS_MAPPING', 'An exact authenticated queue mapping is required.'); return result.data;
};
export const requesterDigest = actor => delegationDigest({ sub: actor.googleSubject, email: actor.verifiedEmail, oauth_subject: actor.subject });

// Private service state, separate from mutable operation records. No grant,
// enrollment or actor-creation API. Only a successful authenticated queue may
// establish a map; callback input can only look up an existing one.
export class ContentAccessLedger {
  constructor(store, { connectionId, registry, now = () => Date.now() }) {
    requireThat(/^con_[A-Za-z0-9]+$/.test(connectionId), 'ACCESS_MAPPING', 'The configured Google connection is required.');
    requireThat(registry instanceof Registry, 'ACCESS_MAPPING', 'Current service-owned website authorization is required.');
    Object.assign(this, { store, connectionId, registry, now });
    store.db.exec(`CREATE TABLE IF NOT EXISTS content_access_principals(
        principal TEXT PRIMARY KEY, requester TEXT NOT NULL, revision INTEGER NOT NULL, state TEXT NOT NULL);
      CREATE TABLE IF NOT EXISTS content_access_jobs(id TEXT PRIMARY KEY, body TEXT NOT NULL, digest TEXT NOT NULL);
      CREATE TABLE IF NOT EXISTS content_access_queries(id TEXT PRIMARY KEY, job TEXT NOT NULL, check_id TEXT NOT NULL UNIQUE,
        query_digest TEXT NOT NULL, state TEXT NOT NULL, revision INTEGER NOT NULL);
      CREATE TABLE IF NOT EXISTS content_access_audit(seq INTEGER PRIMARY KEY, time INTEGER NOT NULL, job TEXT NOT NULL,
        event TEXT NOT NULL, phase TEXT NOT NULL, state TEXT NOT NULL, revision INTEGER NOT NULL);`);
  }
  audit(job, event, phase, state, revision) {
    // Identity resolves through the immutable job map; no raw assertion, query
    // nonce, bearer, tool body, private key or provider response enters audit.
    this.store.db.prepare('INSERT INTO content_access_audit(time,job,event,phase,state,revision) VALUES(?,?,?,?,?,?)')
      .run(this.now(), job, event, phase, state, revision);
  }
  actor(value) {
    // Select only verified identity fields; never persist bearer tokens or a new lease.
    const actor = parse(actorSchema, Object.fromEntries(Object.keys(actorFields).map(key => [key, value?.[key]])));
    requireThat(actor.googleConnection === this.connectionId && actor.expiresAt > this.now(), 'AUTH_REQUIRED', 'A current verified MRN Google requester is required.');
    requireWriteScope(actor); return actor;
  }
  site(value, input, saved) {
    const request = parse(requestSchema, input);
    const site = parse(registryTargetSchema, saved
      ? this.registry.current(value, parse(registryTargetSchema, saved), 'content')
      : this.registry.resolve(value, request.site_url, undefined, 'content'));
    requireThat(site.url === request.site_url && site.management === 'mainwp',
      'ACCESS_MAPPING', 'Qualification must bind the exact authorized MainWP website and environment.');
    return site;
  }
  assertQueueAllowed(value, input, saved) {
    const actor = this.actor(value), principal = digest(actor.googleSubject);
    const row = this.store.db.prepare('SELECT * FROM content_access_principals WHERE principal=?').get(principal);
    requireThat(!row || (row.state === 'clear' && row.requester === requesterDigest(actor) && Number.isSafeInteger(row.revision) && row.revision >= 1),
      'ACCESS_DENIED', 'Current access is revoked, pending or unavailable.');
    return this.site(actor, input, saved);
  }
  // Called only after protected-worker readiness and delegated provider response
  // validation. expected is an independently verified service policy/recipe pin,
  // not a tool argument. The callback route cannot call this method.
  recordQueued(value, input, output, expected, admittedSite) {
    const actor = this.actor(value), request = parse(requestSchema, input), job = parse(jobSchema, output);
    expected = parse(expectedSchema, expected);
    requireThat(job.state === 'queued' && job.reason_code === 'none' && job.acceptance_scope === 'provider-only'
      && job.qualification_binding === null && job.capture_artifact_sha256 === null && job.profile_sha256 === null
      && job.qualification_report_sha256 === null && job.target_revision === null && job.is_current === false
      && job.current_binding_sha256 === null && job.recipe_sha256 === null
      && (job.current_target_revision === null) === (job.current_profile_sha256 === null)
      && job.expires_at > Math.floor(this.now() / 1000)
      && ['site_id', 'site_url', 'target', 'expected_snapshot_sha256'].every(k => delegationDigest(job[k]) === delegationDigest(request[k]))
      && ['authorization_policy_sha256', 'recipe_catalog_sha256'].every(k => expected[k] === job[k]),
    'ACCESS_MAPPING', 'The authenticated queue result differs from the original target or approved worker inputs.');
    // Queue pins the catalog; the provider chooses/binds a recipe at capture.
    // Keep the independently admitted recipe separately, never fabricate it in
    // the authenticated queued response or treat it as already captured.
    return this.store.transaction(() => {
      // The site was resolved before asynchronous worker admission. Recheck it
      // after the provider response; a changed environment cannot be rebound.
      const site = this.assertQueueAllowed(actor, request, parse(registryTargetSchema, admittedSite));
      const mapping = { actor, site, request, job, admission: expected, requester: requesterDigest(actor), principal: digest(actor.googleSubject) };
      const existing = this.store.db.prepare('SELECT body,digest FROM content_access_jobs WHERE id=?').get(job.qualification_job_id);
      if (existing) {
        const original = this.decode(existing);
        requireThat(original.requester === mapping.requester && delegationDigest(original.request) === delegationDigest(request)
          && delegationDigest(original.job) === delegationDigest(job) && delegationDigest(original.admission) === delegationDigest(expected)
          && original.site.websiteId === site.websiteId && original.site.environment === site.environment,
        'ACCESS_MAPPING', 'An immutable queue mapping cannot be replaced.');
        this.site(original.actor, original.request, original.site);
        return original; // A repeated login cannot renew the original retained identity or lifetime.
      }
      this.store.db.prepare('INSERT OR IGNORE INTO content_access_principals VALUES(?,?,1,?)').run(mapping.principal, mapping.requester, 'clear');
      const body = JSON.stringify(mapping);
      this.store.db.prepare('INSERT INTO content_access_jobs VALUES(?,?,?)').run(job.qualification_job_id, body, digest(body));
      this.audit(job.qualification_job_id, 'queue-mapped', '', 'clear', 1);
      return mapping;
    });
  }
  decode(row) {
    requireThat(row && digest(row.body) === row.digest, 'ACCESS_MAPPING', 'The original queue mapping is missing or corrupt.');
    let value; try { value = JSON.parse(row.body); } catch { requireThat(false, 'ACCESS_MAPPING', 'The original queue mapping is corrupt.'); }
    const mapping = parse(mappingSchema, value);
    requireThat(mapping.requester === requesterDigest(mapping.actor) && mapping.principal === digest(mapping.actor.googleSubject)
      && mapping.actor.googleConnection === this.connectionId
      && mapping.site.url === mapping.request.site_url && mapping.site.management === 'mainwp'
      && ['site_id', 'site_url', 'target', 'expected_snapshot_sha256'].every(k => delegationDigest(mapping.request[k]) === delegationDigest(mapping.job[k]))
      && ['authorization_policy_sha256', 'recipe_catalog_sha256'].every(k => mapping.admission[k] === mapping.job[k]),
    'ACCESS_MAPPING', 'The original requester binding is invalid.');
    return mapping;
  }
  // Internal deny-first bookkeeping only. Owner authorization and authoritative
  // propagation/readback remain a separate integration; this never claims that
  // provider revocation has committed, and offers no clear/regrant operation.
  recordPendingRevocation(jobId) {
    return this.store.transaction(() => {
      const mapping = this.decode(this.store.db.prepare('SELECT body,digest FROM content_access_jobs WHERE id=?').get(jobId));
      const row = this.store.db.prepare('SELECT * FROM content_access_principals WHERE principal=?').get(mapping.principal);
      requireThat(row && Number.isSafeInteger(row.revision) && row.revision >= 1 && row.revision < Number.MAX_SAFE_INTEGER
        && ['clear', 'pending-revocation'].includes(row.state), 'ACCESS_UNAVAILABLE', 'The current revocation ledger is unavailable.');
      if (row.state === 'clear') this.store.db.prepare('UPDATE content_access_principals SET state=?,revision=revision+1 WHERE principal=?').run('pending-revocation', mapping.principal);
      if (row.state === 'clear') this.audit(jobId, 'revocation-pending', '', 'pending-revocation', row.revision + 1);
      return { state: 'pending-revocation', revision: row.revision + (row.state === 'clear' ? 1 : 0), committed_denial: false };
    });
  }
  answer(query, queryDigest, signAnswer) {
    return this.store.transaction(() => {
      const mapping = this.decode(this.store.db.prepare('SELECT body,digest FROM content_access_jobs WHERE id=?').get(query.qualification_job_id));
      requireThat(mapping.requester === query.requester_binding_sha256
        && ['site_id', 'site_url', 'target', 'authorization_policy_sha256'].every(k => delegationDigest(mapping.job[k]) === delegationDigest(query[k]))
        && mapping.job.expires_at >= query.expires_at && mapping.job.expires_at > Math.floor(this.now() / 1000),
      'ACCESS_BINDING', 'The stored query does not bind the original queued request.');
      const row = this.store.db.prepare('SELECT * FROM content_access_principals WHERE principal=?').get(mapping.principal);
      requireThat(row && row.requester === mapping.requester && Number.isSafeInteger(row.revision) && row.revision >= 1
        && ['clear', 'pending-revocation', 'denied'].includes(row.state), 'ACCESS_UNAVAILABLE', 'Current revocation state is unavailable.');
      let state = row.state;
      if (mapping.actor.expiresAt <= this.now()) state = 'denied';
      if (state === 'clear') {
        try {
          // Synchronous local permissions and exact configuration only. Expired
          // discovery fails closed; callback data never refreshes or reconstructs
          // a target. The provider independently checks current Google Directory.
          this.site(this.actor(mapping.actor), mapping.request, mapping.site);
        } catch { state = 'denied'; }
      }
      const count = this.store.db.prepare('SELECT count(*) AS n FROM content_access_queries WHERE job=?').get(query.qualification_job_id).n;
      requireThat(count < 512 && !this.store.db.prepare('SELECT id FROM content_access_queries WHERE id=? OR check_id=?').get(query.query_id, query.check_id),
        'ACCESS_REPLAY', 'The private query is consumed or its bounded check budget is exhausted.');
      const response = signAnswer(state, row.revision);
      requireThat(response && !response.then, 'ACCESS_UNAVAILABLE', 'Private signing must finish inside the ledger transaction.');
      this.store.db.prepare('INSERT INTO content_access_queries VALUES(?,?,?,?,?,?)').run(query.query_id, query.qualification_job_id, query.check_id, queryDigest, state, row.revision);
      this.audit(query.qualification_job_id, 'veto-answered', query.phase, state, row.revision);
      return response;
    });
  }
}
