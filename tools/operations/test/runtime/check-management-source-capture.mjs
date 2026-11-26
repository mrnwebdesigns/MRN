import assert from 'node:assert/strict';
import { generateKeyPairSync, sign, verify } from 'node:crypto';
import { readFileSync } from 'node:fs';
import { digest } from '../../src/contracts.mjs';
import { delegationCanonical, delegationDigest } from '../../src/delegation.mjs';
import { ManagementSourceCapture } from '../../src/management-source-capture.mjs';
import { ManagementSourceCatalogRegistry } from '../../src/management-source-catalog.mjs';
import { managementSourceWire as wire } from '../../src/management-source-transfer.mjs';

// Actual local retention/catalog/broker code, synthetic authenticated provider
// bootstrap and capture. The following caller exercises its recipient over TLS.
export async function retainManagementSourceFixture(s) {
  const fixture = JSON.parse(readFileSync(new URL('../fixtures/content-management-source-transfer-v1.json', import.meta.url)));
  const catalog = fixture.catalog, binding = s.source.context.management.binding;
  catalog.issued_at = binding.created_at; catalog.expires_at = binding.expires_at;
  const pairs = { root: generateKeyPairSync('ed25519'), registrar: s.registrar, identity: s.identity, qa: generateKeyPairSync('ed25519') };
  const keys = Object.fromEntries(Object.entries(pairs).map(([role, p]) => [role, [{ id: role,
    public_key_base64: p.publicKey.export({ format: 'der', type: 'spki' }).subarray(-32).toString('base64') }]]));
  keys.registrar[0].id = s.registrar.keyId; keys.identity[0].id = s.identity.keyId;
  const bytes = Buffer.from(delegationCanonical(catalog)), hash = delegationDigest(catalog);
  const envelope = { key_id: 'root', payload_base64: bytes.toString('base64'),
    signature_base64: sign(null, Buffer.concat([Buffer.from(wire.purposes.catalog), bytes]), pairs.root.privateKey).toString('base64') };
  const registry = { schema: 1, keys, catalogs: [{ sha256: hash, envelope }] }, pin = delegationDigest(registry);
  s.source.context.source_recipe_catalog_sha256 = hash; s.source.ownerCatalogSha256 = hash;
  const manifest = JSON.parse(Buffer.from(s.frames[0].data_base64, 'base64'));
  manifest.context = s.source.context;
  const raw = Buffer.from(delegationCanonical(manifest));
  Object.assign(s.frames[0], { bytes: raw.length, sha256: digest(raw), data_base64: raw.toString('base64') });
  Object.assign(s.source.slot, { manifest_bytes: raw.length, transfer_manifest_sha256: digest(raw) });
  let bootstraps = 0, captures = 0;
  const retained = new ManagementSourceCapture({ ledger: s.ledger,
    catalogs: new ManagementSourceCatalogRegistry({ ledger: s.ledger, current: () => registry, expectedSha256: () => pin }),
    registrar: s.registrar, verifyStaff: async actor => assert.equal(actor.googleSubject, s.actor.googleSubject),
    contextReader: { async read(job) { bootstraps++; assert.equal(job, s.job); return structuredClone(s.source.context); } },
    captureReader: { async capture(input) {
      captures++; const signed = input.signed_request, raw = Buffer.from(signed.payload_base64, 'base64');
      assert.equal(verify(null, Buffer.concat([Buffer.from(wire.purposes.capture), raw]), s.registrar.publicKey,
        Buffer.from(signed.signature_base64, 'base64')), true);
      assert.deepEqual(JSON.parse(raw).context, s.source.context); assert.deepEqual(input.signed_source_catalog, envelope);
      return structuredClone({ schema: 1, context: s.source.context, slot: s.source.slot, state: 'complete' });
    } } });
  await retained.capture(s.job);
  await assert.rejects(() => retained.capture(s.job), { code: 'MANAGEMENT_SOURCE_CAPTURE' });
  s.broker.resolveSource = original => retained.resolve(original);
  for (let n = 0; n < 80; n++) assert.deepEqual(retained.resolve(s.ledger.captured(s.job)), s.source);
  assert.equal(bootstraps, 1); assert.equal(captures, 1);
  return retained;
}
