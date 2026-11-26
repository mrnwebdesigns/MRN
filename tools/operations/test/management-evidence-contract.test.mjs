import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { digest } from '../src/contracts.mjs';
import { managementEvidenceWire, managementReportWire, managementEvidenceTypes, managementReportTypes } from '../src/management-evidence-contract.mjs';
import { managementWireLimits, managementCaptureWireLimits, managementEvidenceWireLimits,
  compileManagementSchema } from '../src/content-management-schema.mjs';

const source = JSON.parse(readFileSync(new URL('../contracts/content-management-evidence-source.json', import.meta.url)));
const envelope = { key_id: 'synthetic-shape-only', payload_base64: Buffer.from('{}').toString('base64'), signature_base64: Buffer.alloc(64).toString('base64') };
test('management evidence schemas are exact separate source-only imports with immutable dependencies and purposes', () => {
  assert.equal(source.commit, 'bc320cde81ec6a2359d315d9985debf8bbf523ca');
  assert.equal(source.runtimeAdoption, false); assert.equal(source.productionQualified, false);
  assert.equal(source.files['content-management-report-v1.json'].sha256, '312b7fb7d7179e419686bed93d51f27adfbbe432c75f1f52db909b4d9f6728e6');
  assert.equal(source.files['content-management-evidence-v1.json'].sha256, '6923e15402d99203e297b590145947b6ac1d39552e5cde7bc173e45090a47084');
  for (const entry of Object.values(source.files)) assert.equal(digest(readFileSync(new URL('../' + entry.path, import.meta.url))), entry.sha256);
  for (const wire of [managementReportWire, managementEvidenceWire]) {
    assert.equal(wire.production_enabled, false); assert.equal(wire.consumer_binding_enabled, false);
    for (const ability of Object.values(wire.abilities)) { assert.equal(ability.registered, false); assert.equal(ability.http_method, 'POST'); }
    for (const purpose of Object.values(wire.purposes)) assert.match(purpose, /^mrn-content-management-/);
    for (const [file, hash] of Object.entries(wire.frozen_dependencies)) assert.equal(digest(readFileSync(new URL('../contracts/' + file, import.meta.url))), hash);
  }
  assert.equal(new Set([...Object.values(managementReportWire.purposes), ...Object.values(managementEvidenceWire.purposes)]).size, 6);
  assert.equal(Object.keys(managementEvidenceTypes).length, Object.keys(managementEvidenceWire.$defs).length);
  assert.equal(Object.keys(managementReportTypes).length, Object.keys(managementReportWire.$defs).length);
});

test('binary evidence has a separate bounded response and does not widen public or capture envelopes', () => {
  assert.equal(managementWireLimits.bytes, 524288); assert.equal(managementCaptureWireLimits.bytes, 5242880);
  assert.equal(managementEvidenceWireLimits.bytes, 1048576);
  const response = { signed_acquisition: envelope, qualification_zip_base64: Buffer.alloc(262144).toString('base64'),
    acquisition_zip_base64: Buffer.alloc(196608).toString('base64') };
  assert.equal(managementEvidenceTypes.acquisition_response.safeParse(response).success, true);
  const ordinary = compileManagementSchema({ $ref: '#/$defs/acquisition_response', $defs: managementEvidenceWire.$defs });
  assert.equal(ordinary.safeParse(response).success, false);
  assert.equal(managementEvidenceTypes.acquisition_response.safeParse({ ...response, qualification_zip_base64: Buffer.alloc(262147).toString('base64') }).success, false);
  assert.equal(managementEvidenceTypes.acquisition_response.safeParse({ ...response, acquisition_zip_base64: Buffer.alloc(196609).toString('base64') }).success, false);
  assert.equal(managementEvidenceTypes.acquisition_response.safeParse({ ...response, caller_url: 'https://untrusted.test' }).success, false);
});

test('opaque query pointers cannot carry actor, job, source, run, key or endpoint selections', () => {
  const pointer = { query_id: '12345678-1234-4234-8234-123456789abc', provider_query_nonce: 'a'.repeat(64) };
  assert.equal(managementEvidenceTypes.query_pointer.safeParse(pointer).success, true);
  for (const key of ['email', 'job_id', 'repository', 'run_id', 'key_id', 'url'])
    assert.equal(managementEvidenceTypes.query_pointer.safeParse({ ...pointer, [key]: 'caller-selected' }).success, false);
  const getter = Object.defineProperty({ ...pointer }, 'provider_query_nonce', { enumerable: true, get() { throw new Error('must not run'); } });
  assert.equal(managementEvidenceTypes.query_pointer.safeParse(getter).success, false);
});
