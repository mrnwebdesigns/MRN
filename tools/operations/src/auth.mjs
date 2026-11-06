import { createRemoteJWKSet, jwtVerify } from 'jose';
import { createHash } from 'node:crypto';
import { OpsError, requireThat } from './contracts.mjs';
import { z } from 'zod';
import { operationsScope, operationsScopes } from './scopes.mjs';

// Only successful JWT + active Google verification may populate this map.
// Metadata stays outside the public actor/schema and disappears with its actor.
// A future original-intent ledger must persist it BEFORE preview dispatch;
// no later actor or supplied session ID can reconstruct that lost intent.
// This is historical binding metadata, never a substitute for a fresh
// principal/Directory/current-access check or permission to dispatch.
const originalAuthorizations = new WeakMap();
const maximumAuthorizationAgeSeconds = 60 * 60;
export function originalAuthorizationAnchor(actor) {
  const value = originalAuthorizations.get(actor);
  requireThat(value && Number.isSafeInteger(value.expiresAt) && value.expiresAt > Date.now(),
    'AUTH_REQUIRED', 'The original verified MRN authorization is unavailable or expired.');
  return value;
}

export const oauthChallenge = (publicUrl, insufficientScope = false) => `Bearer resource_metadata="${new URL(publicUrl).origin}/.well-known/oauth-protected-resource/mcp", error="${insufficientScope ? 'insufficient_scope' : 'invalid_token'}", error_description="${insufficientScope ? 'Reconnect MRN Website Operations to authorize guarded changes.' : 'Sign in to MRN Website Operations to continue.'}", scope="${insufficientScope ? operationsScopes.join(' ') : operationsScope}"`;

export function createAuthenticator(config, keySet = createRemoteJWKSet(new URL(config.jwksUrl), { timeoutDuration: 5000 }), verifyStaff) {
  return async header => {
    requireThat(typeof header === 'string' && /^Bearer [A-Za-z0-9._~-]+$/.test(header) && header.length < 16000, 'AUTH_REQUIRED', 'An individual access token is required.');
    try {
      const { payload } = await jwtVerify(header.slice(7), keySet, {
        issuer: config.issuer, audience: config.audience, algorithms: ['RS256', 'ES256'],
        requiredClaims: ['sub', 'exp', 'iat'], maxTokenAge: maximumAuthorizationAgeSeconds, clockTolerance: 5,
      });
      // A retained actor must never outlive the same original bearer under the
      // resource's admission policy. Clock tolerance permits verification skew;
      // it is not extra authority for a later dispatch or recovery.
      requireThat(Number.isSafeInteger(payload.exp) && Number.isSafeInteger(payload.iat),
        'AUTH_REQUIRED', 'The access token has invalid authorization timestamps.');
      const expiresAt = Math.min(payload.exp, payload.iat + maximumAuthorizationAgeSeconds) * 1000;
      requireThat(Number.isSafeInteger(expiresAt) && expiresAt > Date.now(),
        'AUTH_REQUIRED', 'The original authorization has expired.');
      requireThat(typeof payload.scope === 'string' && payload.scope.length <= 4096
        && /^[\x21\x23-\x5b\x5d-\x7e]+(?: [\x21\x23-\x5b\x5d-\x7e]+)*$/.test(payload.scope), 'AUTH_REQUIRED', 'The access token has invalid OAuth scopes.');
      const scopes = Object.freeze(operationsScopes.filter(scope => payload.scope.split(' ').includes(scope)));
      requireThat(typeof payload.sub === 'string' && payload.sub.length <= 200 && scopes.includes(operationsScope), 'AUTH_REQUIRED', 'The access token lacks the MRN Operations scope.');
      requireThat(payload.gty !== 'client-credentials' && !payload.sub.endsWith('@clients'), 'AUTH_REQUIRED', 'Use an individual member login.');
      // Only this resource's signed, explicitly verified claim can grant domain
      // access. Plain email claims and client/tool metadata are never identity.
      const email = payload[`${config.audience}/email`];
      const verifiedEmail = config.verifiedEmailClaims === true && payload[`${config.audience}/email_verified`] === true
        && typeof email === 'string' && email.length <= 254 && /^[\x21-\x7e]+$/.test(email) && z.string().email().safeParse(email).success
        ? email.toLowerCase() : undefined;
      const identity = { subject: payload.sub, expiresAt, scopes, ...(verifiedEmail ? { verifiedEmail } : {}) };
      if (config.googleWorkspace) {
        const googleSubject = payload[`${config.audience}/google_sub`];
        const googleConnection = payload[`${config.audience}/google_connection`];
        requireThat(verifiedEmail?.endsWith('@mrnwebdesigns.com') && typeof googleSubject === 'string'
          && /^[0-9]{1,64}$/.test(googleSubject) && payload.sub === `google-oauth2|${googleSubject}`
          && payload[`${config.audience}/identity_provider`] === 'google-oauth2'
          && googleConnection === config.googleWorkspace.connectionId,
        'AUTH_REQUIRED', 'Sign in through the approved MRN Google Workspace connection.');
        Object.assign(identity, { googleSubject, googleConnection, identityProvider: 'google-oauth2', hostedDomain: 'mrnwebdesigns.com' });
      }
      const actor = Object.freeze(identity);
      // No positive membership cache: suspended/revoked accounts must fail on
      // tools/list and read requests as well as on guarded changes. The provider
      // repeats this check at dispatch, including long-running workflows.
      if (config.googleWorkspace) {
        requireThat(typeof verifyStaff === 'function', 'AUTH_REQUIRED', 'Active Google Workspace verification is unavailable.');
        await verifyStaff(actor);
        requireThat(identity.expiresAt > Date.now(), 'AUTH_REQUIRED', 'The original authorization expired during staff verification.');
        // Hash the exact already-verified resource bearer in memory. Never
        // retain the token or accept a caller-provided token/session hash.
        originalAuthorizations.set(actor, Object.freeze({ schema: 1,
          authorizationSha256: createHash('sha256').update('mrn-operations-original-authorization-v1\0').update(header.slice(7)).digest('hex'),
          expiresAt: identity.expiresAt }));
      }
      return actor;
    } catch { throw new OpsError('AUTH_REQUIRED', 'The individual access token is invalid, expired or intended for another service.'); }
  };
}
