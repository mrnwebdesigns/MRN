import { randomBytes, randomUUID, sign } from 'node:crypto';
import { readFileSync } from 'node:fs';
import { z } from 'zod';
import { requireThat, sha } from './contracts.mjs';
import { delegationCanonical, delegationDigest, delegationIssuer, delegationAudience } from './delegation.mjs';
import { compileContentSchema } from './content-schema.mjs';
import { payload, validateStatus } from './mainwp.mjs';

// Candidate source contract. No startup registration, policy provisioning or
// workspace tool is enabled by importing this private control implementation.
export const accessContract = JSON.parse(readFileSync(new URL('../contracts/content-current-access-v1.json', import.meta.url), 'utf8'));
const compile = name => compileContentSchema({ $ref: `#/definitions/${name}` }, accessContract.definitions);
const querySchema = compile('query_readback_output'), claimsSchema = compile('veto_claims');
const pointerSchema = z.object({ query_id: z.string().uuid(), provider_query_nonce: sha }).strict();
export const accessVetoPath = '/internal/mainwp/access-veto';
// Distinct fixed transport selection; an opaque query pointer cannot select
// its signature domain and must never trigger cross-domain readback retries.
export const managementAccessVetoPath = '/internal/mainwp/management-access-veto';
export const accessReadbackTool = 'mrn_mainwp__read_access_veto_query_v1';
const readbackSchema = compile('query_readback_claims');
const parse = (schema, input) => {
  const result = schema.safeParse(input);
  requireThat(result.success, 'ACCESS_INVALID', 'The private access contract is invalid.'); return result.data;
};
const fresh = (claims, now) => requireThat(claims.issued_at <= now && claims.expires_at > now
  && claims.expires_at > claims.issued_at && claims.expires_at - claims.issued_at <= 5,
'ACCESS_EXPIRED', 'The private access check expired.');

export class AccessSigner {
  constructor({ keyId, privateKey, now = () => Date.now() }) {
    requireThat(/^[A-Za-z0-9_-]{1,64}$/.test(keyId) && privateKey?.asymmetricKeyType === 'ed25519',
      'ACCESS_KEY', 'Use the configured gateway identity authority.');
    Object.assign(this, { keyId, privateKey, now });
  }
  envelope(claims, prefix) {
    const bytes = Buffer.from(delegationCanonical(claims));
    return { key_id: this.keyId, payload_base64: bytes.toString('base64'),
      signature_base64: sign(null, Buffer.concat([Buffer.from(prefix), bytes]), this.privateKey).toString('base64') };
  }
  readback(input) {
    const pointer = parse(pointerSchema, input), now = Math.floor(this.now() / 1000);
    const claims = parse(readbackSchema, { schema: 1, issuer: delegationIssuer, audience: delegationAudience,
      issued_at: now, expires_at: now + 5, request_id: randomUUID(), nonce: randomBytes(32).toString('hex'),
      action: 'read-veto-query', ...pointer });
    return { claims, envelope: this.envelope(claims, accessContract.query_readback_purpose_prefix) };
  }
  veto(query, querySha256, state, revision, expiresAt) {
    const now = Math.floor(this.now() / 1000);
    const claims = parse(claimsSchema, { ...query, issuer: delegationIssuer, audience: delegationAudience,
      action: 'access-veto', issued_at: now, expires_at: Math.min(now + 5, query.expires_at, expiresAt),
      request_id: randomUUID(), nonce: randomBytes(32).toString('hex'), query_sha256: querySha256,
      state, gateway_revision: revision });
    fresh(claims, now);
    const envelope = this.envelope(claims, accessContract.veto_purpose_prefix);
    fresh(claims, Math.floor(this.now() / 1000));
    return { signed_veto: envelope };
  }
}

// Own a warmed, separate MCP control connection. Never reuse the session whose
// synchronous capture is waiting for this callback, or delegate a fresh actor.
export class AccessQueryReader {
  constructor(client) { this.client = client; this.ready = false; }
  async initialize() {
    this.ready = false;
    validateStatus(await this.client.readResource({ uri: 'mainwp://status' }));
    let cursor, found; const cursors = new Set(); let count = 0;
    do {
      const page = await this.client.listTools(cursor ? { cursor } : {});
      requireThat(Array.isArray(page.tools) && (count += page.tools.length) <= 1000,
        'ACCESS_TRANSPORT', 'Invalid private control capability directory.');
      for (const tool of page.tools) if (tool.name === accessReadbackTool) {
        requireThat(!found, 'ACCESS_TRANSPORT', 'Duplicate private control capability.'); found = tool;
      }
      cursor = page.nextCursor;
      requireThat(!cursor || (typeof cursor === 'string' && !cursors.has(cursor) && cursors.size < 32), 'ACCESS_TRANSPORT', 'Repeated or excessive private capability pages.');
      if (cursor) cursors.add(cursor);
    } while (cursor);
    requireThat(found?.annotations?.readOnlyHint === false && found.annotations.destructiveHint === false,
      'ACCESS_TRANSPORT', 'Private readback requires the canonical POST-only provider route.');
    this.ready = true;
  }
  async read(envelope) {
    requireThat(this.ready, 'ACCESS_UNAVAILABLE', 'The independent control connection is not ready.');
    return payload(await this.client.callTool({ name: accessReadbackTool, arguments: { signed_request: envelope } }, undefined, { timeout: 2000 }));
  }
}

export class ContentAccessVeto {
  constructor({ signer, reader, ledger, now = () => Date.now(), monotonic = () => performance.now() }) {
    Object.assign(this, { signer, reader, ledger, now, monotonic });
  }
  async respond(input) {
    const pointer = parse(pointerSchema, input), started = this.monotonic();
    const withinDeadline = () => requireThat(this.monotonic() - started < 2500 && this.monotonic() >= started,
      'ACCESS_EXPIRED', 'The private access lookup exceeded its deadline.');
    const { claims, envelope } = this.signer.readback(pointer);
    // No lookup fallback, retry, caller URL, actor, or caller-supplied job map.
    const result = parse(querySchema, await this.reader.read(envelope));
    withinDeadline(); fresh(claims, Math.floor(this.now() / 1000));
    const query = result.query;
    fresh(query, Math.floor(this.now() / 1000));
    requireThat(query.query_id === pointer.query_id && query.provider_query_nonce === pointer.provider_query_nonce
      && result.query_sha256 === delegationDigest(query), 'ACCESS_BINDING', 'The provider query does not match its authenticated readback.');
    const deadline = Math.min(claims.expires_at, query.expires_at);
    // Mapping/ledger reread, local denial, one-use consumption and signing share
    // one short transaction, with no network operation inside its lock.
    const response = this.ledger.answer(query, result.query_sha256, (state, revision) => {
      withinDeadline();
      return this.signer.veto(query, result.query_sha256, state, revision, deadline);
    });
    withinDeadline();
    requireThat(deadline > Math.floor(this.now() / 1000), 'ACCESS_EXPIRED', 'The private access answer expired before return.');
    return response;
  }
}
