import { createRemoteJWKSet, jwtVerify } from 'jose';
import { OpsError, requireThat } from './contracts.mjs';

export const operationsScope = 'mrn:operations';
export const securitySchemes = [{ type: 'oauth2', scopes: [operationsScope] }];
export const oauthChallenge = publicUrl => `Bearer resource_metadata="${new URL(publicUrl).origin}/.well-known/oauth-protected-resource/mcp", error="invalid_token", error_description="Sign in to MRN Website Operations to continue.", scope="${operationsScope}"`;

export function createAuthenticator(config, keySet = createRemoteJWKSet(new URL(config.jwksUrl), { timeoutDuration: 5000 })) {
  return async header => {
    requireThat(typeof header === 'string' && /^Bearer [A-Za-z0-9._~-]+$/.test(header) && header.length < 16000, 'AUTH_REQUIRED', 'An individual access token is required.');
    try {
      const { payload } = await jwtVerify(header.slice(7), keySet, {
        issuer: config.issuer, audience: config.audience, algorithms: ['RS256', 'ES256'],
        requiredClaims: ['sub', 'exp', 'iat'], maxTokenAge: '1h', clockTolerance: 5,
      });
      requireThat(typeof payload.sub === 'string' && payload.sub.length <= 200 && String(payload.scope || '').split(' ').includes(operationsScope), 'AUTH_REQUIRED', 'The access token lacks the MRN Operations scope.');
      return Object.freeze({ subject: payload.sub, expiresAt: payload.exp * 1000 });
    } catch { throw new OpsError('AUTH_REQUIRED', 'The individual access token is invalid, expired or intended for another service.'); }
  };
}
