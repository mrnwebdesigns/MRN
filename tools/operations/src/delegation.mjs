import { createPrivateKey, randomUUID, sign } from 'node:crypto';
import { closeSync, constants, fstatSync, openSync, readFileSync } from 'node:fs';
import { z } from 'zod';
import { digest, requireThat, sha } from './contracts.mjs';

export const delegationPrefix = 'mrn-mainwp-delegation-v1:';
export const delegationIssuer = 'https://operations.mrnwebdesigns.com';
export const delegationAudience = 'https://wpcontrol.mrndev.io';

// Intentionally smaller than arbitrary JavaScript/JSON. PHP and Node must hash
// identical bytes; floats, sparse arrays and invalid Unicode are ambiguous.
export function delegationCanonical(value, stack = new Set()) {
  if (value === null || typeof value === 'boolean') return JSON.stringify(value);
  if (typeof value === 'number') {
    requireThat(Number.isSafeInteger(value) && !Object.is(value, -0), 'DELEGATION_INPUT', 'Delegated numbers must be safe integers.');
    return String(value);
  }
  if (typeof value === 'string') {
    requireThat(value.isWellFormed(), 'DELEGATION_INPUT', 'Delegated strings must contain valid Unicode.');
    return JSON.stringify(value);
  }
  requireThat(value && typeof value === 'object' && !stack.has(value), 'DELEGATION_INPUT', 'Delegation requires strict acyclic JSON.');
  stack.add(value);
  try {
    if (Array.isArray(value)) {
      requireThat(Object.keys(value).length === value.length && value.every((_, i) => Object.hasOwn(value, i)), 'DELEGATION_INPUT', 'Sparse arrays are not supported.');
      return `[${value.map(v => delegationCanonical(v, stack)).join(',')}]`;
    }
    requireThat([Object.prototype, null].includes(Object.getPrototypeOf(value)) && !Object.getOwnPropertySymbols(value).length,
      'DELEGATION_INPUT', 'Only JSON objects can be delegated.');
    const keys = Object.keys(value).sort();
    requireThat(keys.every(k => /^[\x20-\x7e]+$/.test(k) && !['__proto__', 'prototype', 'constructor'].includes(k)),
      'DELEGATION_INPUT', 'Delegated object keys must be unambiguous ASCII names.');
    return `{${keys.map(k => `${JSON.stringify(k)}:${delegationCanonical(value[k], stack)}`).join(',')}}`;
  } finally { stack.delete(value); }
}
export const delegationDigest = value => digest(delegationCanonical(value));

export function readIdentityKey(path) {
  const fd = openSync(path, constants.O_RDONLY | constants.O_NOFOLLOW);
  try {
    const stat = fstatSync(fd);
    requireThat(stat.isFile() && stat.nlink === 1 && (stat.mode & 0o077) === 0 && stat.size <= 4096
      && [0, process.getuid()].includes(stat.uid), 'DELEGATION_KEY', 'Use a private owner-only identity signing key.');
    const key = createPrivateKey(readFileSync(fd));
    requireThat(key.asymmetricKeyType === 'ed25519', 'DELEGATION_KEY', 'Identity delegation requires a separate Ed25519 key.');
    return key;
  } finally { closeSync(fd); }
}

export const challengeSchema = z.object({
  nonce: sha, expires_at: z.number().int().positive(),
  authenticated_connection_sha256: sha, policy_sha256: sha,
}).strict();

export class IdentitySigner {
  constructor({ keyId, privateKey, connectionId, now = () => Date.now() }) {
    requireThat(/^[A-Za-z0-9_-]{1,64}$/.test(keyId) && privateKey?.asymmetricKeyType === 'ed25519', 'DELEGATION_KEY', 'Invalid identity signing key configuration.');
    Object.assign(this, { keyId, privateKey, connectionId, now });
  }
  sign(actor, ability, args, challenge) {
    const now = Math.floor(this.now() / 1000);
    requireThat(actor?.identityProvider === 'google-oauth2' && /^[0-9]{1,64}$/.test(actor.googleSubject || '')
      && actor.subject === `google-oauth2|${actor.googleSubject}` && actor.hostedDomain === 'mrnwebdesigns.com'
      && actor.googleConnection === this.connectionId && actor.verifiedEmail?.endsWith('@mrnwebdesigns.com')
      && actor.expiresAt > this.now(), 'AUTH_REQUIRED', 'Only a current verified MRN Google identity can be delegated.');
    requireThat(/^(?:mainwp|mrn-mainwp)\/[a-z0-9-]+-v1$/.test(ability) || ability === 'mrn-mainwp/get-content-policy-v2', 'DELEGATION_INPUT', 'Invalid delegated ability name.');
    challenge = challengeSchema.parse(challenge);
    const expires = Math.min(now + 60, challenge.expires_at, Math.floor(actor.expiresAt / 1000));
    requireThat(expires > now, 'DELEGATION_EXPIRED', 'The delegation challenge expired.');
    const payload = {
      schema: 1, issuer: delegationIssuer, audience: delegationAudience,
      issued_at: now, expires_at: expires, request_id: randomUUID(), nonce: challenge.nonce,
      authenticated_connection_sha256: challenge.authenticated_connection_sha256, policy_sha256: challenge.policy_sha256,
      oauth_subject: actor.subject, google_sub: actor.googleSubject, email: actor.verifiedEmail, email_verified: true,
      hosted_domain: actor.hostedDomain, identity_provider: actor.identityProvider, google_connection: actor.googleConnection,
      ability, arguments_sha256: delegationDigest(args),
    };
    const bytes = Buffer.from(delegationCanonical(payload));
    return { key_id: this.keyId, payload_base64: bytes.toString('base64'),
      signature_base64: sign(null, Buffer.concat([Buffer.from(delegationPrefix), bytes]), this.privateKey).toString('base64') };
  }
}
