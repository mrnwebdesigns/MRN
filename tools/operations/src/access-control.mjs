import { randomBytes, randomUUID, sign } from 'node:crypto';
import { z } from 'zod';
import { requireThat, sha } from './contracts.mjs';
import { delegationCanonical, delegationDigest, delegationIssuer, delegationAudience } from './delegation.mjs';
import { payload, validateStatus } from './mainwp.mjs';

const pointerSchema = z.object({ query_id: z.string().uuid(), provider_query_nonce: sha }).strict();
const parse = (schema, input) => {
  const result = schema.safeParse(input);
  requireThat(result.success, 'ACCESS_INVALID', 'The private access contract is invalid.'); return result.data;
};
const fresh = (claims, now) => requireThat(claims.issued_at <= now && claims.expires_at > now
  && claims.expires_at > claims.issued_at && claims.expires_at - claims.issued_at <= 5,
'ACCESS_EXPIRED', 'The private access check expired.');

// Internal source-selected protocol. Never take contract, purpose or tool name
// from a callback, OAuth claim, public argument or provider response.
export function createAccessControl({ contract, compile, readbackTool, readbackSchemaVersion = 1, vetoSchemaVersion = 1 }) {
  requireThat([1, 2].includes(readbackSchemaVersion) && [1, 2].includes(vetoSchemaVersion),
    'ACCESS_CONFIG', 'The callback versions must be selected by source.');
  const querySchema = compile('query_readback_output'), claimsSchema = compile('veto_claims');
  const readbackSchema = compile('query_readback_claims');
  class AccessSigner {
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
      const claims = parse(readbackSchema, { schema: readbackSchemaVersion, issuer: delegationIssuer, audience: delegationAudience,
        issued_at: now, expires_at: now + 5, request_id: randomUUID(), nonce: randomBytes(32).toString('hex'),
        action: 'read-veto-query', ...pointer });
      return { claims, envelope: this.envelope(claims, contract.query_readback_purpose_prefix) };
    }
    veto(query, querySha256, state, revision, expiresAt) {
      const now = Math.floor(this.now() / 1000);
      const claims = parse(claimsSchema, { ...query, schema: vetoSchemaVersion, issuer: delegationIssuer, audience: delegationAudience,
        action: 'access-veto', issued_at: now, expires_at: Math.min(now + 5, query.expires_at, expiresAt),
        request_id: randomUUID(), nonce: randomBytes(32).toString('hex'), query_sha256: querySha256,
        state, gateway_revision: revision });
      fresh(claims, now);
      const envelope = this.envelope(claims, contract.veto_purpose_prefix);
      fresh(claims, Math.floor(this.now() / 1000));
      return { signed_veto: envelope };
    }
  }

  const AccessQueryReader = createPrivateQueryReader(readbackTool);

  class ContentAccessVeto {
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
      let deadline = Math.min(claims.expires_at, query.expires_at);
      // Mapping/ledger reread, local denial, one-use consumption and signing share
      // one short transaction, with no network operation inside its lock.
      const response = this.ledger.answer(query, result.query_sha256, (state, revision, expiresAt = deadline) => {
        withinDeadline();
        deadline = Math.min(deadline, expiresAt);
        return this.signer.veto(query, result.query_sha256, state, revision, deadline);
      });
      withinDeadline();
      requireThat(deadline > Math.floor(this.now() / 1000), 'ACCESS_EXPIRED', 'The private access answer expired before return.');
      return response;
    }
  }
  return { AccessSigner, AccessQueryReader, ContentAccessVeto };
}

// Own a warmed, separate MCP control connection. Never reuse the session whose
// synchronous capture is waiting for this callback, or delegate a fresh actor.
export function createPrivateQueryReader(readbackTool, { readOnlyHint = false, idempotentHint } = {}) {
  requireThat(typeof readOnlyHint === 'boolean' && (idempotentHint === undefined || typeof idempotentHint === 'boolean'),
    'ACCESS_TRANSPORT', 'Private capability annotations must be selected by source.');
  return class PrivateQueryReader {
    constructor(client) { this.client = client; this.ready = false; }
    async initialize() {
      this.ready = false;
      validateStatus(await this.client.readResource({ uri: 'mainwp://status' }));
      let cursor, found; const cursors = new Set(); let count = 0;
      do {
        const page = await this.client.listTools(cursor ? { cursor } : {});
        requireThat(Array.isArray(page.tools) && (count += page.tools.length) <= 1000,
          'ACCESS_TRANSPORT', 'Invalid private control capability directory.');
        for (const tool of page.tools) if (tool.name === readbackTool) {
          requireThat(!found, 'ACCESS_TRANSPORT', 'Duplicate private control capability.'); found = tool;
        }
        cursor = page.nextCursor;
        requireThat(!cursor || (typeof cursor === 'string' && !cursors.has(cursor) && cursors.size < 32), 'ACCESS_TRANSPORT', 'Repeated or excessive private capability pages.');
        if (cursor) cursors.add(cursor);
      } while (cursor);
      requireThat(found?.annotations?.readOnlyHint === readOnlyHint && found.annotations.destructiveHint === false
        && (idempotentHint === undefined || found.annotations.idempotentHint === idempotentHint),
      'ACCESS_TRANSPORT', 'Private control requires the exact canonical provider capability.');
      this.ready = true;
    }
    async read(envelope) {
      requireThat(this.ready, 'ACCESS_UNAVAILABLE', 'The independent control connection is not ready.');
      return payload(await this.client.callTool({ name: readbackTool, arguments: { signed_request: envelope } }, undefined, { timeout: 2000 }));
    }
  };
}
