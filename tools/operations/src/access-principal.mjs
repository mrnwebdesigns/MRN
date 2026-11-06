import { z } from 'zod';
import { digest, requireThat, requireWriteScope, Registry } from './contracts.mjs';
import { delegationDigest } from './delegation.mjs';

const actorFields = { subject: z.string(), googleSubject: z.string().regex(/^[0-9]{1,64}$/),
  verifiedEmail: z.string().email().max(254).regex(/^[^@\s]+@mrnwebdesigns\.com$/),
  identityProvider: z.literal('google-oauth2'), hostedDomain: z.literal('mrnwebdesigns.com'),
  googleConnection: z.string().regex(/^con_[A-Za-z0-9]+$/), expiresAt: z.number().int().positive().safe(),
  scopes: z.array(z.string()).max(2),
};
export const accessActorSchema = z.object(actorFields).strict().refine(actor => actor.subject === `google-oauth2|${actor.googleSubject}`
  && actor.verifiedEmail === actor.verifiedEmail.toLowerCase());

export const requesterDigest = actor => delegationDigest({ sub: actor.googleSubject, email: actor.verifiedEmail, oauth_subject: actor.subject });

// Both content protocols share a deny-first principal history. Separate job
// maps/purpose domains must never let a new workflow clear pending revocation.
export class AccessPrincipals {
  constructor(store, { connectionId, registry, now = () => Date.now() }) {
    requireThat(/^con_[A-Za-z0-9]+$/.test(connectionId), 'ACCESS_MAPPING', 'The configured Google connection is required.');
    requireThat(registry instanceof Registry, 'ACCESS_MAPPING', 'Current service-owned website authorization is required.');
    Object.assign(this, { store, connectionId, registry, now });
    store.db.exec(`CREATE TABLE IF NOT EXISTS content_access_principals(
      principal TEXT PRIMARY KEY, requester TEXT NOT NULL, revision INTEGER NOT NULL, state TEXT NOT NULL);
      CREATE TABLE IF NOT EXISTS content_access_principal_history(
      principal TEXT NOT NULL, revision INTEGER NOT NULL, requester TEXT NOT NULL, state TEXT NOT NULL,
      PRIMARY KEY(principal,revision));`);
  }
  actor(value) {
    const result = accessActorSchema.safeParse(Object.fromEntries(Object.keys(actorFields).map(key => [key, value?.[key]])));
    requireThat(result.success, 'ACCESS_MAPPING', 'An exact authenticated queue mapping is required.');
    const actor = result.data;
    requireThat(actor.googleConnection === this.connectionId && actor.expiresAt > this.now(), 'AUTH_REQUIRED', 'A current verified MRN Google requester is required.');
    requireWriteScope(actor); return actor;
  }
  assertAllowed(actor) {
    const principal = digest(actor.googleSubject), requester = requesterDigest(actor);
    const exists = this.store.db.prepare('SELECT principal FROM content_access_principals WHERE principal=?').get(principal);
    const row = exists ? this.row(principal, requester) : null;
    if (!row) this.assertNeverInitialized(principal, requester);
    requireThat(!row || row.state === 'clear',
      'ACCESS_DENIED', 'Current access is revoked, pending or unavailable.');
  }
  // Shared across every protocol. Absence of a primary row alone is never
  // evidence of first use, including before the new protocol has any history.
  assertNeverInitialized(principal, requester) {
    requireThat(!this.store.db.prepare('SELECT principal FROM content_access_principal_history WHERE principal=? LIMIT 1').get(principal),
      'ACCESS_UNAVAILABLE', 'Established principal state is missing; automatic reinitialization is forbidden.');
    for (const table of ['content_access_jobs', 'management_access_jobs', 'original_management_authorizations', 'original_management_controls',
      'original_management_bindings', 'original_management_queues', 'original_management_access_queries']) {
      if (!this.store.db.prepare("SELECT name FROM sqlite_master WHERE type='table' AND name=?").get(table)) continue;
      // Fixed internal tables; stream rows without retaining content. A valid
      // JSON string alone cannot prove a historical map belonged to someone
      // else. Check its full body hash and exact actor-derived identity first.
      for (const row of this.store.db.prepare(`SELECT body,digest FROM ${table}`).iterate()) {
        let mapping;
        try { mapping = typeof row.body === 'string' && digest(row.body) === row.digest ? JSON.parse(row.body) : null; }
        catch { mapping = null; }
        const person = accessActorSchema.safeParse(mapping?.actor);
        requireThat(person.success && mapping.principal === digest(person.data.googleSubject)
          && mapping.requester === requesterDigest(person.data), 'ACCESS_UNAVAILABLE', 'Historical identity integrity is unavailable.');
        requireThat(mapping.principal !== principal && mapping.requester !== requester,
          'ACCESS_UNAVAILABLE', 'Surviving workflow history forbids recreating missing principal state.');
      }
    }
    for (const table of ['original_management_attempts', 'original_management_control_history', 'original_management_queue_history',
      'original_management_capture_history']) {
      if (!this.store.db.prepare("SELECT name FROM sqlite_master WHERE type='table' AND name=?").get(table)) continue;
      for (const row of this.store.db.prepare(`SELECT principal,requester FROM ${table}`).iterate()) {
        requireThat(/^[a-f0-9]{64}$/.test(row.principal) && /^[a-f0-9]{64}$/.test(row.requester),
          'ACCESS_UNAVAILABLE', 'Historical preview identity integrity is unavailable.');
        requireThat(row.principal !== principal && row.requester !== requester,
          'ACCESS_UNAVAILABLE', 'Surviving preview history forbids recreating missing principal state.');
      }
    }
  }
  row(principal, requester) {
    const row = this.store.db.prepare('SELECT * FROM content_access_principals WHERE principal=?').get(principal);
    const history = this.store.db.prepare('SELECT * FROM content_access_principal_history WHERE principal=? ORDER BY revision DESC LIMIT 1').get(principal);
    requireThat(row && row.requester === requester && Number.isSafeInteger(row.revision) && row.revision >= 1
      && ['clear', 'pending-revocation', 'denied'].includes(row.state)
      && history?.requester === requester && history.revision === row.revision && history.state === row.state,
    'ACCESS_UNAVAILABLE', 'Current revocation state or its established history is unavailable.');
    return row;
  }
  // Callers hold their local transaction. No replace/upsert/reset operation.
  // Pre-history service databases require a qualified central migration, not
  // silent per-person bootstrap or reconstruction from an OAuth login.
  retain(principal, requester) {
    if (this.store.db.prepare('SELECT principal FROM content_access_principals WHERE principal=?').get(principal)) {
      requireThat(this.row(principal, requester).state === 'clear', 'ACCESS_DENIED', 'Current access is revoked or pending.');
      return;
    }
    this.assertNeverInitialized(principal, requester);
    this.store.db.prepare('INSERT INTO content_access_principals VALUES(?,?,1,?)').run(principal, requester, 'clear');
    this.store.db.prepare('INSERT INTO content_access_principal_history VALUES(?,1,?,?)').run(principal, requester, 'clear');
  }
  // Caller holds the short local transaction and records attributable audit.
  pending(principal, requester) {
    const row = this.row(principal, requester);
    requireThat(row.revision < Number.MAX_SAFE_INTEGER && ['clear', 'pending-revocation'].includes(row.state),
      'ACCESS_UNAVAILABLE', 'The current revocation ledger is unavailable.');
    if (row.state === 'clear') {
      this.store.db.prepare('UPDATE content_access_principals SET state=?,revision=revision+1 WHERE principal=?').run('pending-revocation', principal);
      this.store.db.prepare('INSERT INTO content_access_principal_history VALUES(?,?,?,?)').run(principal, row.revision + 1, requester, 'pending-revocation');
    }
    return { changed: row.state === 'clear', revision: row.revision + (row.state === 'clear' ? 1 : 0) };
  }
}
