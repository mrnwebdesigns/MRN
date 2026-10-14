import { createRemoteJWKSet, jwtVerify } from 'jose';
import { OpsError, requireThat } from './contracts.mjs';
import { z } from 'zod';

export const operationsScope = 'mrn:operations';
export const securitySchemes = [{ type: 'oauth2', scopes: [operationsScope] }];
export const oauthChallenge = publicUrl => `Bearer resource_metadata="${new URL(publicUrl).origin}/.well-known/oauth-protected-resource/mcp", error="invalid_token", error_description="Sign in to MRN Website Operations to continue.", scope="${operationsScope}"`;

export function createAuthenticator(config, keySet = createRemoteJWKSet(new URL(config.jwksUrl), { timeoutDuration: 5000 }), verifyStaff) {
  return async header => {
    requireThat(typeof header === 'string' && /^Bearer [A-Za-z0-9._~-]+$/.test(header) && header.length < 16000, 'AUTH_REQUIRED', 'An individual access token is required.');
    try {
      const { payload } = await jwtVerify(header.slice(7), keySet, {
        issuer: config.issuer, audience: config.audience, algorithms: ['RS256', 'ES256'],
        requiredClaims: ['sub', 'exp', 'iat'], maxTokenAge: '1h', clockTolerance: 5,
      });
      requireThat(typeof payload.sub === 'string' && payload.sub.length <= 200 && String(payload.scope || '').split(' ').includes(operationsScope), 'AUTH_REQUIRED', 'The access token lacks the MRN Operations scope.');
      requireThat(payload.gty !== 'client-credentials' && !payload.sub.endsWith('@clients'), 'AUTH_REQUIRED', 'Use an individual member login.');
      // Only this resource's signed, explicitly verified claim can grant domain
      // access. Plain email claims and client/tool metadata are never identity.
      const email = payload[`${config.audience}/email`];
      const verifiedEmail = config.verifiedEmailClaims === true && payload[`${config.audience}/email_verified`] === true
        && typeof email === 'string' && email.length <= 254 && /^[\x21-\x7e]+$/.test(email) && z.string().email().safeParse(email).success
        ? email.toLowerCase() : undefined;
      const identity = { subject: payload.sub, expiresAt: payload.exp * 1000, ...(verifiedEmail ? { verifiedEmail } : {}) };
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
      }
      return actor;
    } catch { throw new OpsError('AUTH_REQUIRED', 'The individual access token is invalid, expired or intended for another service.'); }
  };
}
