import { createPublicKey, verify } from 'node:crypto';
import { z } from 'zod';
import { requireThat } from './contracts.mjs';
import { delegationCanonical, delegationDigest } from './delegation.mjs';

const hash = z.string().regex(/^[a-f0-9]{64}$/);
const key = z.object({ id: z.string().regex(/^[A-Za-z0-9_-]{1,64}$/), public_key_base64: z.string().length(44) }).strict();
const keys = z.array(key).min(1).max(20);
export const requireSourceCatalog = (ok, code = 'MANAGEMENT_SOURCE_CATALOG') => requireThat(ok, code,
  code === 'MANAGEMENT_SOURCE_MISSING_CATALOG' ? 'The original website source catalog is not available in MRN services.'
    : 'The original source catalog, owner signature or current trust configuration changed.');

// Pure consistency/signature validation, not authority. The two private
// callers select their frozen protocol at construction and independently
// establish the original job and service-owned configuration before and after.
export function createSourceCatalogValidator(wire, catalogSchema, envelopeSchema) {
  const registrySchema = z.object({ schema: z.literal(1),
    keys: z.object({ root: keys, registrar: keys, identity: keys, qa: keys }).strict(),
    catalogs: z.array(z.object({ sha256: hash, envelope: envelopeSchema }).strict()).max(128) }).strict();
  const fail = requireSourceCatalog;
  return (current, pin, expectedCatalogSha256, binding, startedAt) => {
    fail(hash.safeParse(expectedCatalogSha256).success && Number.isSafeInteger(startedAt)
      && hash.safeParse(pin).success && Buffer.byteLength(delegationCanonical(current)) <= 18000000);
    const parsed = registrySchema.safeParse(current);
    fail(parsed.success && delegationDigest(parsed.data) === pin);
    const registry = parsed.data, publicKeys = new Set();
    for (const entries of Object.values(registry.keys)) {
      const ids = new Set();
      for (const entry of entries) {
        const bytes = Buffer.from(entry.public_key_base64, 'base64');
        fail(bytes.length === 32 && bytes.toString('base64') === entry.public_key_base64
          && !ids.has(entry.id) && !publicKeys.has(entry.public_key_base64));
        ids.add(entry.id); publicKeys.add(entry.public_key_base64);
      }
    }
    fail(new Set(registry.catalogs.map(c => c.sha256)).size === registry.catalogs.length);
    const selected = registry.catalogs.find(c => c.sha256 === expectedCatalogSha256);
    fail(selected, 'MANAGEMENT_SOURCE_MISSING_CATALOG');
    const envelope = selected.envelope, bytes = Buffer.from(envelope.payload_base64, 'base64');
    const signature = Buffer.from(envelope.signature_base64, 'base64');
    const root = registry.keys.root.find(k => k.id === envelope.key_id);
    fail(root && bytes.length > 0 && bytes.length <= wire.limits.catalog_decoded_bytes
      && bytes.toString('base64') === envelope.payload_base64 && signature.length === 64
      && signature.toString('base64') === envelope.signature_base64);
    const publicKey = createPublicKey({ key: Buffer.concat([Buffer.from('302a300506032b6570032100', 'hex'),
      Buffer.from(root.public_key_base64, 'base64')]), type: 'spki', format: 'der' });
    fail(verify(null, Buffer.concat([Buffer.from(wire.purposes.catalog), bytes]), publicKey, signature));
    let decoded;
    try { decoded = JSON.parse(new globalThis.TextDecoder('utf-8', { fatal: true }).decode(bytes)); } catch { fail(false); }
    const value = catalogSchema.safeParse(decoded); fail(value.success);
    const catalog = value.data;
    fail(delegationCanonical(catalog) === bytes.toString('utf8') && delegationDigest(catalog) === expectedCatalogSha256
      && catalog.issued_at <= binding.created_at && catalog.expires_at >= binding.expires_at
      && catalog.expires_at > catalog.issued_at && catalog.issued_at * 1000 <= startedAt
      && catalog.expires_at * 1000 > startedAt
      && new Set(catalog.artifacts.map(a => a.artifact_id)).size === catalog.artifacts.length);
    return { catalog, signedCatalog: envelope, ownerCatalogSha256: expectedCatalogSha256, registrySha256: pin };
  };
}
