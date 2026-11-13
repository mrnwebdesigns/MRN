import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createPublicKey, verify } from 'node:crypto';
import { digest } from '../src/contracts.mjs';
import { delegationCanonical, delegationDigest } from '../src/delegation.mjs';
import { existingManagementProtocol, existingManagementHashes, existingManagementWires, existingManagementTypes,
  validateExistingManagementBinding } from '../src/management-existing-contracts.mjs';
import { managementCaptureTypes } from '../src/content-management-capture.mjs';
import { managementReportTypes, managementEvidenceTypes } from '../src/management-evidence-contract.mjs';
import { sourceTransferTypes } from '../src/management-source-transfer.mjs';

const fixtureBytes = readFileSync(new URL('./fixtures/content-management-existing-v2.json', import.meta.url));
const vectors = JSON.parse(fixtureBytes);
const source = JSON.parse(readFileSync(new URL('../contracts/content-management-existing-source.json', import.meta.url)));
const schema = (domain, name) => existingManagementTypes[domain][name];

test('existing-record contracts and synthetic vectors retain their immutable canonical provider provenance', () => {
  assert.equal(source.repository, 'mrnwebdesigns/mrn-mainwp-operations-api');
  assert.equal(source.commit, 'e2413f0b38a09fe44403bc9b03dcd3a66b7d657e');
  assert.equal(source.runtimeAdoption, false); assert.equal(source.productionQualified, false);
  assert.equal(source.schema_and_synthetic_signatures_only, true);
  assert.equal(vectors.production_enabled, false); assert.equal(vectors.runtime_registered, false);
  assert.equal(vectors.native_evidence_relations_verified, false);
  assert.deepEqual(vectors.contract_hashes, existingManagementHashes);
  assert.equal(Object.keys(source.files).length, 17);
  for (const [path, entry] of Object.entries(source.files)) {
    assert.match(path, /^(?:contracts|test\/fixtures)\/[a-z0-9-]+\.json$/);
    assert.equal(digest(readFileSync(new URL('../' + path, import.meta.url))), entry.sha256, path);
  }
  for (const wire of Object.values(existingManagementWires)) {
    assert.equal(wire.qualification_protocol, existingManagementProtocol);
    assert.equal(wire.production_enabled, false); assert.equal(wire.runtime_registered, false);
    assert.equal(wire.consumer_binding_enabled, false); assert.ok(Object.isFrozen(wire));
  }
  assert.equal(Object.values(existingManagementTypes).reduce((n, types) => n + Object.keys(types).length, 0), 155);
  assert.equal(existingManagementTypes.source_transfer.transfer_manifest, undefined);
  assert.equal(existingManagementWires.source_transfer.limits.full_source_files, 20000);
  assert.equal(existingManagementWires.source_transfer.limits.full_source_bytes, 536870912);
});

test('the provider positive, negative and semantic substitution vectors cross the gateway bounded v2 compiler', () => {
  assert.equal(vectors.positive.length, 20); assert.equal(vectors.negative.length, 13);
  for (const v of vectors.positive) {
    assert.ok(schema(v.domain, v.definition).safeParse(v.value).success, `${v.domain}/${v.definition}`);
    assert.equal(schema(v.domain, v.definition).safeParse({ ...v.value, caller_override: true }).success, false);
  }
  for (const v of vectors.negative)
    assert.equal(schema(v.domain, v.definition).safeParse(v.value).success, false, v.label);
  assert.deepEqual(validateExistingManagementBinding(vectors.retained_original_binding, vectors.retained_original_binding), vectors.retained_original_binding);
  assert.equal(delegationCanonical(vectors.retained_original_binding), vectors.retained_original_binding_canonical);
  assert.equal(delegationDigest(vectors.retained_original_binding), vectors.retained_original_binding_sha256);
  for (const v of vectors.semantic_negative)
    assert.throws(() => validateExistingManagementBinding(v.value, vectors.retained_original_binding), v.label);
  for (const value of [{ ...vectors.retained_original_binding, expires_at: vectors.retained_original_binding.created_at },
    { ...vectors.retained_original_binding, job_intent_ttl_seconds: 901 }])
    assert.throws(() => validateExistingManagementBinding(value, value));
});

test('all seventeen synthetic signatures verify only their frozen v2 purpose and correct existing role', () => {
  const keys = Object.fromEntries(Object.entries(vectors.public_keys).map(([role, encoded]) => {
    const bytes = Buffer.from(encoded, 'base64'); assert.equal(bytes.length, 32); assert.equal(bytes.toString('base64'), encoded);
    return [role, createPublicKey({ format: 'der', type: 'spki', key: Buffer.concat([Buffer.from('302a300506032b6570032100', 'hex'), bytes]) })];
  }));
  assert.deepEqual(Object.keys(keys).sort(), ['identity', 'qa', 'registrar', 'root']);
  assert.equal(vectors.signatures.length, 17);
  for (const v of vectors.signatures) {
    schema(v.domain, v.definition).parse(v.value);
    assert.equal(delegationCanonical(v.value), v.canonical_json); assert.equal(digest(v.canonical_json), v.claims_sha256);
    assert.equal(existingManagementWires.wire_registry.signature_roles[v.purpose], v.role);
    const bytes = Buffer.from(v.canonical_json), sig = Buffer.from(v.signature_base64, 'base64');
    assert.ok(verify(null, Buffer.concat([Buffer.from(v.purpose), bytes]), keys[v.role], sig));
    for (const [role, key] of Object.entries(keys)) if (role !== v.role)
      assert.equal(verify(null, Buffer.concat([Buffer.from(v.purpose), bytes]), key, sig), false);
    for (const purpose of existingManagementWires.wire_registry.legacy_purposes_denied)
      assert.equal(verify(null, Buffer.concat([Buffer.from(purpose), bytes]), keys[v.role], sig), false);
    assert.equal(verify(null, Buffer.concat([Buffer.from(v.purpose), bytes, Buffer.from(' ')]), keys[v.role], sig), false);
  }
});

test('v2 parsing cannot change legacy schemas or admit unsafe JavaScript values and oversized metadata', () => {
  for (const types of [managementCaptureTypes, managementReportTypes, managementEvidenceTypes])
    assert.equal(types.binding.safeParse(vectors.retained_original_binding).success, false);
  const source = vectors.positive.find(v => v.domain === 'source_transfer' && v.definition === 'read_claims');
  assert.equal(sourceTransferTypes.read_claims.safeParse(source.value).success, false);
  const binding = vectors.retained_original_binding, candidate = { ...binding };
  Object.defineProperty(candidate, 'site_url', { enumerable: true, get() { throw new Error('getter must not run'); } });
  assert.equal(schema('qualification', 'binding').safeParse(candidate).success, false);
  const cycle = { ...binding }; cycle.target = cycle;
  assert.equal(schema('qualification', 'binding').safeParse(cycle).success, false);
  assert.equal(schema('qualification', 'binding').safeParse({ ...binding, site_url: 'x'.repeat(5242881) }).success, false);
  assert.throws(() => { existingManagementWires.qualification.qualification_protocol = 'caller'; }, TypeError);
  assert.equal(existingManagementProtocol, 'content-management-existing-v2');
});
