import { readFileSync } from 'node:fs';
import { z } from 'zod';
import { digest, requireThat, requireWriteScope, sha } from './contracts.mjs';
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
const mappingSchema = z.object({ actor: actorSchema, request: requestSchema, job: jobSchema, requester: sha, principal: sha }).strict();
const parse = (schema, value) => {
  const result = schema.safeParse(value);
  requireThat(result.success, 'ACCESS_MAPPING', 'An exact authenticated queue mapping is required.'); return result.data;
};
export const requesterDigest = actor => delegationDigest({ sub: actor.googleSubject, email: actor.verifiedEmail, oauth_subject: actor.subject });

// Private service state, separate from mutable operation records. No grant,
// enrollment or actor-creation API. Only a successful authenticated queue may
// establish a map; callback input can only look up an existing one.
export class ContentAccessLedger {
  constructor(store, { connectionId, now = () => Date.now() }) {
    requireThat(/^con_[A-Za-z0-9]+$/.test(connectionId), 'ACCESS_MAPPING', 'The configured Google connection is required.');
    Object.assign(this, { store, connectionId, now });
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
  assertQueueAllowed(value) {
    const actor = this.actor(value), principal = digest(actor.googleSubject);
    const row = this.store.db.prepare('SELECT * FROM content_access_principals WHERE principal=?').get(principal);
    requireThat(!row || (row.state === 'clear' && row.requester === requesterDigest(actor) && Number.isSafeInteger(row.revision) && row.revision >= 1),
      'ACCESS_DENIED', 'Current access is revoked, pending or unavailable.');
    return actor;
  }
  // Called only after protected-worker readiness and delegated provider response
  // validation. expected is an independently verified service policy/recipe pin,
  // not a tool argument. The callback route cannot call this method.
  recordQueued(value, input, output, expected) {
    const actor = this.actor(value), request = parse(requestSchema, input), job = parse(jobSchema, output);
    expected = parse(expectedSchema, expected);
    requireThat(job.state === 'queued' && job.reason_code === 'none' && job.acceptance_scope === 'provider-only'
      && job.qualification_binding === null && job.capture_artifact_sha256 === null && job.profile_sha256 === null
      && job.qualification_report_sha256 === null && job.target_revision === null && job.is_current === false
      && job.current_target_revision === null && job.current_profile_sha256 === null && job.current_binding_sha256 === null
      && job.expires_at > Math.floor(this.now() / 1000)
      && ['site_id', 'site_url', 'target', 'expected_snapshot_sha256'].every(k => delegationDigest(job[k]) === delegationDigest(request[k]))
      && Object.keys(expected).every(k => expected[k] === job[k]),
    'ACCESS_MAPPING', 'The authenticated queue result differs from the original target or approved worker inputs.');
    const mapping = { actor, request, job, requester: requesterDigest(actor), principal: digest(actor.googleSubject) };
    return this.store.transaction(() => {
      this.assertQueueAllowed(actor);
      const existing = this.store.db.prepare('SELECT body,digest FROM content_access_jobs WHERE id=?').get(job.qualification_job_id);
      if (existing) {
        const original = this.decode(existing);
        requireThat(original.requester === mapping.requester && delegationDigest(original.request) === delegationDigest(request)
          && delegationDigest(original.job) === delegationDigest(job), 'ACCESS_MAPPING', 'An immutable queue mapping cannot be replaced.');
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
      && mapping.actor.googleConnection === this.connectionId, 'ACCESS_MAPPING', 'The original requester binding is invalid.');
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
  answer(query, queryDigest, checkLocal, signAnswer) {
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
          const local = checkLocal(mapping);
          requireThat(local === true, 'ACCESS_UNAVAILABLE', 'Local access must be explicitly verified inside the current ledger transaction.');
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
