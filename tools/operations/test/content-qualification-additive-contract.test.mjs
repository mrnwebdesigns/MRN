import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { compileContentSchema } from '../src/content-schema.mjs';
import { delegationCanonical, delegationDigest } from '../src/delegation.mjs';

const read = path => JSON.parse(readFileSync(new URL(`../${path}`, import.meta.url)));
const names = ['content-capture-read-v1.json', 'content-qualification-report-v1.json'];
const contracts = Object.fromEntries(names.map(name => [name, read(`contracts/${name}`)]));
const vectors = read('test/fixtures/content-qualification-additive-v1.json');
const schema = (name, definition) => compileContentSchema({ $ref: `#/definitions/${definition}` }, contracts[name].definitions);

test('PHP additive contract definitions and canonical vectors agree with the gateway parser', () => {
  let definitions = 0;
  for (const [name, contract] of Object.entries(contracts)) {
    for (const definition of Object.keys(contract.definitions)) { schema(name, definition); definitions++; }
    assert.equal(contract.frozen_dependencies['content-qualification-v1.json'],
      '9c8d3fa9e27d7e4aa36109ccb33d76f7babf5bc6aea4f3661fcfd6e26ab62e03');
  }
  assert.equal(definitions, 25); assert.equal(vectors.cases.length, 4);
  for (const row of vectors.cases) {
    const parser = schema(row.contract, row.definition);
    assert.deepEqual(parser.parse(row.value), row.value);
    assert.equal(delegationCanonical(row.value), row.canonical);
    assert.equal(delegationDigest(row.value), row.sha256);
    assert.equal(parser.safeParse({ ...row.value, caller_success: true }).success, false);
    for (const key of Object.keys(row.value)) {
      const missing = structuredClone(row.value); delete missing[key];
      assert.equal(parser.safeParse(missing).success, false, `required ${row.definition}.${key}`);
    }
  }
});

test('recovery retains the exact capture schema and cannot represent recapture or caller identity', () => {
  const recovery = contracts[names[0]], original = read('contracts/content-qualification-v1.json');
  for (const key of ['capture_artifact', 'parity_manifest', 'target'])
    assert.deepEqual(recovery.definitions[key], original.definitions[key]);
  const claims = vectors.cases[0].value, parser = schema(names[0], 'claims');
  for (const changes of [{ action: 'capture' }, { actor: 'staff@mrnwebdesigns.com' },
    { site_url: 'https://mrnwebdesigns.com' }, { retry: true }, { expected_target_revision: -1 }])
    assert.equal(parser.safeParse({ ...claims, ...changes }).success, false);
  assert.equal(recovery.purpose_prefix, 'mrn-content-qualification-capture-read-v1:');
  assert.equal(recovery.limits.replay_claims_per_job, 8);
  assert.equal(recovery.limits.capture_artifact_utf8_bytes, 4400000);
  for (const contract of Object.values(contracts)) {
    assert.equal(contract.http_method, 'POST'); assert.equal(contract.acceptance_scope, 'provider-only');
    assert.equal(contract.production_enabled, false); assert.equal(contract.binding_issuance, false);
    assert.equal(contract.ability.workspace_exposed, false); assert.equal(contract.ability.delegated_staff_allowed, false);
  }
});

test('profile reports require every evidence reference and protected provenance without accepting caller assertions', () => {
  const report = contracts[names[1]], value = vectors.cases[3].value, parser = schema(names[1], 'qa_report_claims');
  assert.deepEqual(report.mandatory_evidence_groups, ['wordpress_api', 'accessibility', 'performance',
    'native_content_lifecycle', 'capture_integrity', 'scope_ownership', 'parity_consistency', 'source_integrity']);
  for (const group of report.mandatory_evidence_groups) {
    const missing = structuredClone(value); delete missing.mandatory_evidence[group];
    assert.equal(parser.safeParse(missing).success, false);
    const asserted = structuredClone(value); asserted.mandatory_evidence[group].passed = true;
    assert.equal(parser.safeParse(asserted).success, false);
  }
  for (const changes of [{ purpose: 'content-change' }, { result: 'Skipped' }, { actor: 'caller' },
    { issuer: 'mrn-content-qualification' }, { run_provenance: { ...value.run_provenance, ref: 'refs/heads/unreviewed' } },
    { run_provenance: { ...value.run_provenance, repository: 'caller/runner' } },
    { run_provenance: { ...value.run_provenance, runtime_url: 'https://mrnwebdesigns.com' } }])
    assert.equal(parser.safeParse({ ...value, ...changes }).success, false);
  assert.equal(report.qa_purpose_prefix, 'mrn-content-profile-qualification-report-v1:');
  assert.equal(report.registrar_purpose_prefix, 'mrn-content-qualification-report-retain-v1:');
  assert.deepEqual(report.definitions.output.properties.qualification_binding, { type: 'null' });
  assert.equal(report.limits.evidence_archive_compressed_bytes, 16777216);
  assert.equal(report.limits.evidence_archive_expanded_bytes, 33554432);
  assert.equal(report.limits.evidence_archive_member_bytes, 4400000);
  assert.equal(report.limits.evidence_archive_members, 64);
  assert.equal(report.limits.measurement_manifest_utf8_bytes, 98304);
});

test('golden request and report vectors bind the same retained artifact; all deliberate substitutions differ', () => {
  const artifact = vectors.cases[1].value.capture_artifact;
  const fields = ['schema', 'authorization_policy_sha256', 'catalog_epoch', 'qualification_job_id',
    'expected_snapshot_sha256', 'requester_binding_sha256', 'site_id', 'site_url', 'target',
    'recipe_catalog_sha256', 'recipe_id', 'recipe_sha256', 'expected_target_revision'];
  const project = value => Object.fromEntries(fields.map(key => [key, value[key]]));
  const expected = { ...project(artifact), capture_artifact_sha256: delegationDigest(artifact) };
  const binding = value => ({ ...project(value), capture_artifact_sha256: value.capture_artifact_sha256 });
  for (const index of [0, 2, 3]) assert.deepEqual(binding(vectors.cases[index].value), expected);
  assert.equal(vectors.binding_mismatches.length, 12);
  for (const mismatch of vectors.binding_mismatches) {
    const value = structuredClone(vectors.cases[mismatch.case_index].value); value[mismatch.field] = mismatch.value;
    assert.notDeepEqual(binding(value), expected);
  }
  assert.equal(vectors.cases[2].value.qualification_report_sha256, delegationDigest(vectors.cases[3].value));
  // These are cross-language wire/fixture checks. No production consumer loads
  // the contracts; signatures, evidence admission and current access are separate.
});
