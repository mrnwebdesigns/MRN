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
      principal TEXT PRIMARY KEY, requester TEXT NOT NULL, revision INTEGER NOT NULL, state TEXT NOT NULL)`);
  }
  actor(value) {
    const result = accessActorSchema.safeParse(Object.fromEntries(Object.keys(actorFields).map(key => [key, value?.[key]])));
    requireThat(result.success, 'ACCESS_MAPPING', 'An exact authenticated queue mapping is required.');
    const actor = result.data;
    requireThat(actor.googleConnection === this.connectionId && actor.expiresAt > this.now(), 'AUTH_REQUIRED', 'A current verified MRN Google requester is required.');
    requireWriteScope(actor); return actor;
  }
  assertAllowed(actor) {
    const row = this.store.db.prepare('SELECT * FROM content_access_principals WHERE principal=?').get(digest(actor.googleSubject));
    requireThat(!row || (row.state === 'clear' && row.requester === requesterDigest(actor) && Number.isSafeInteger(row.revision) && row.revision >= 1),
      'ACCESS_DENIED', 'Current access is revoked, pending or unavailable.');
  }
  row(principal, requester) {
    const row = this.store.db.prepare('SELECT * FROM content_access_principals WHERE principal=?').get(principal);
    requireThat(row && row.requester === requester && Number.isSafeInteger(row.revision) && row.revision >= 1
      && ['clear', 'pending-revocation', 'denied'].includes(row.state), 'ACCESS_UNAVAILABLE', 'Current revocation state is unavailable.');
    return row;
  }
  retain(principal, requester) {
    this.store.db.prepare('INSERT OR IGNORE INTO content_access_principals VALUES(?,?,1,?)').run(principal, requester, 'clear');
  }
  // Caller holds the short local transaction and records attributable audit.
  pending(principal, requester) {
    const row = this.row(principal, requester);
    requireThat(row.revision < Number.MAX_SAFE_INTEGER && ['clear', 'pending-revocation'].includes(row.state),
      'ACCESS_UNAVAILABLE', 'The current revocation ledger is unavailable.');
    if (row.state === 'clear') this.store.db.prepare('UPDATE content_access_principals SET state=?,revision=revision+1 WHERE principal=?').run('pending-revocation', principal);
    return { changed: row.state === 'clear', revision: row.revision + (row.state === 'clear' ? 1 : 0) };
  }
}
