import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createHash, createPublicKey, verify } from 'node:crypto';
import { delegationCanonical, delegationDigest } from '../src/delegation.mjs';
import { managementContract, managementSchemas, managementTypes, managementCandidate, managementCandidateHash,
  validateManagementRead, validateManagementEvidence, validateManagementOutcome } from '../src/content-management-contract.mjs';

const fixtures = JSON.parse(readFileSync(new URL('./fixtures/content-management-v1.json', import.meta.url)));
const source = JSON.parse(readFileSync(new URL('../contracts/content-management-source.json', import.meta.url)));
const copy = value => structuredClone(value);
const requestFor = fixture => {
  const { before_sha256, ...request } = copy(fixture.migration.request);
  return { ...request, expected_snapshot_sha256: before_sha256, reason: fixture.migration.reason,
    idempotency_key: '77777777-7777-4777-8777-777777777777' };
};
const expectedFor = fixture => ({ site: { id: fixture.review.site_id, url: fixture.review.site_url }, target: fixture.review.target,
  profile: fixture.review.profile, request: requestFor(fixture), now: fixtures.clock * 1000 });
const evidenceFor = fixture => ({ read: fixture.read, review: fixture.review, migration: fixture.migration });

test('management schemas and vectors are the exact separately pinned provider files, not the runtime pin', () => {
  assert.equal(source.commit, '9e0511f2508c558428257b417ab00c14519d7c47');
  assert.equal(source.scope, 'contract-and-synthetic-vectors-only'); assert.equal(source.productionQualified, false);
  for (const entry of Object.values(source.files)) {
    const data = readFileSync(new URL('../' + entry.path, import.meta.url));
    assert.equal(createHash('sha256').update(data).digest('hex'), entry.sha256);
  }
  assert.equal(Object.keys(managementSchemas).length, 8);
  assert.equal(fixtures.fixture_only, true);
});

for (const fixture of fixtures.cases) {
  test(`${fixture.name}: complete canonical read, preview, migration and installed result agree across PHP and Node`, () => {
    const expected = expectedFor(fixture), evidence = validateManagementEvidence(evidenceFor(fixture), expected);
    const outcome = validateManagementOutcome(fixture.state, fixture.installed_read, evidence.review);
    assert.equal(delegationDigest(evidence.review), fixture.review_canonical_sha256);
    assert.equal(delegationDigest(evidence.read.snapshot), fixture.review.before_sha256);
    assert.equal(managementCandidateHash(evidence.review.candidate_snapshot), fixture.review.candidate_sha256);
    assert.equal(delegationDigest(evidence.migration), fixture.review.migration_artifact.sha256);
    assert.equal(delegationDigest(outcome.read.snapshot), fixture.state.installed_sha256);
    assert.equal(outcome.requiresFreshQualification, fixture.name === 'creation');
    if (fixture.name === 'creation') {
      assert.equal(fixture.review.target.post_id, 0); assert.ok(outcome.state.post_id > 0);
      assert.notEqual(fixture.review.candidate_sha256, outcome.state.installed_sha256);
    }
  });

  test(`${fixture.name}: the management QA signature cannot substitute for the legacy content purpose`, () => {
    const claims = managementTypes.qa_claims.parse(fixture.qa_claims), envelope = managementTypes.qa_envelope.parse(fixture.qa_envelope);
    const payload = Buffer.from(envelope.payload_base64, 'base64'), signature = Buffer.from(envelope.signature_base64, 'base64');
    const publicKey = createPublicKey({ key: Buffer.concat([Buffer.from('302a300506032b6570032100', 'hex'),
      Buffer.from(fixture.qa_public_key_base64, 'base64')]), format: 'der', type: 'spki' });
    assert.equal(delegationCanonical(claims), fixture.qa_payload_canonical);
    assert.equal(payload.toString(), delegationCanonical(claims));
    assert.equal(verify(null, Buffer.concat([Buffer.from(managementContract.qa_purpose), payload]), publicKey, signature), true);
    for (const purpose of ['mrn-content-qa-v1:', 'mrn-code-qa-v1:', ''])
      assert.equal(verify(null, Buffer.concat([Buffer.from(purpose), payload]), publicKey, signature), false);
    assert.equal(claims.plan_digest, fixture.review.digest);
    assert.equal(claims.migration_artifact_sha256, fixture.review.migration_artifact.sha256);
  });
}

test('unknown controls, Unicode map keys, raw decimals and incomplete success vectors are refused', () => {
  for (const vector of fixtures.negative_schema_vectors)
    assert.equal(managementSchemas[vector.schema][vector.surface].safeParse(vector.value).success, false, vector.name);
  for (const fixture of fixtures.cases) {
    const review = copy(fixture.review); review.unrestricted_php = true;
    assert.equal(managementTypes.review.safeParse(review).success, false);
    const state = copy(fixture.state); delete state.installed_sha256;
    assert.equal(managementTypes.state.safeParse(state).success, false);
  }
  const input = requestFor(fixtures.cases[0]); input.acf_patches[0].value = 1.5;
  assert.equal(managementSchemas['preview-content-edit-v1'].input.safeParse(input).success, false);
  for (const value of [fixtures.decimal_read, fixtures.layout_metadata.before, fixtures.layout_metadata.installed]) {
    const parsed = managementSchemas['read-content-record-v1'].output.parse(value);
    assert.equal(delegationDigest(parsed), delegationDigest(value));
  }
});

test('a changed caller target, baseline, profile, reason or requested value cannot reuse a valid preview', () => {
  const fixture = fixtures.cases[0];
  const changes = [expected => { expected.site.id++; }, expected => { expected.site.url = 'https://different.example.invalid'; },
    expected => { expected.target.post_id++; }, expected => { expected.request.expected_snapshot_sha256 = '0'.repeat(64); },
    expected => { expected.profile.sha256 = '0'.repeat(64); }, expected => { expected.request.reason = 'Different reason'; },
    expected => { expected.request.changes.post_title = 'Different title'; }, expected => { expected.request.acf_patches[0].value = 'Other content'; },
    expected => { expected.rollbackOf = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa'; }];
  for (const change of changes) {
    const expected = copy(expectedFor(fixture)); change(expected);
    assert.throws(() => validateManagementEvidence(evidenceFor(fixture), expected), { code: 'CONTENT_MANAGEMENT_MISMATCH' });
  }
  assert.throws(() => validateManagementEvidence(evidenceFor(fixture), { ...expectedFor(fixture), now: (fixture.review.expires_at + 1) * 1000 }), { code: 'PLAN_EXPIRED' });
});

test('provider-rehashed unrelated edits still fail the independently reconstructed requested candidate', () => {
  const fixture = fixtures.cases[0], evidence = copy(evidenceFor(fixture));
  evidence.review.candidate_snapshot.acf_values.field_untouched = 'Unauthorized sibling';
  evidence.migration.candidate_snapshot = copy(evidence.review.candidate_snapshot);
  evidence.review.candidate_sha256 = evidence.migration.candidate_sha256 = managementCandidateHash(evidence.review.candidate_snapshot);
  evidence.review.migration_artifact.sha256 = delegationDigest(evidence.migration);
  const descriptor = { ...evidence.review }; delete descriptor.digest; evidence.review.digest = delegationDigest(descriptor);
  assert.throws(() => validateManagementEvidence(evidence, expectedFor(fixture)), { code: 'CONTENT_MANAGEMENT_MISMATCH' });
});

test('leaf reconstruction preserves layout/row metadata and refuses duplicate, ambiguous or structural paths', () => {
  const fixture = fixtures.cases[0], before = copy(fixture.read.snapshot), request = copy(fixture.migration.request);
  before.acf_values.field_rows[0].acf_fc_layout_disabled = true;
  before.acf_values.field_rows[0].acf_fc_layout_custom_label = 'Keep this label';
  request.before_sha256 = delegationDigest(before);
  const candidate = managementCandidate(before, request);
  assert.equal(candidate.acf_values.field_rows[0].acf_fc_layout_disabled, true);
  assert.equal(candidate.acf_values.field_rows[0].acf_fc_layout_custom_label, 'Keep this label');
  assert.deepEqual(candidate.acf_values.field_rows[1], before.acf_values.field_rows[1]);
  assert.deepEqual(candidate.acf_values.field_rows[0].field_nested, before.acf_values.field_rows[0].field_nested);
  for (const patches of [[request.acf_patches[0], request.acf_patches[0]], [{ path: ['field_rows', 5, 'field_body'], value: 'x' }],
    [{ path: ['field_rows'], value: [] }], [{ path: ['field_missing'], value: 'x' }]])
    assert.throws(() => managementCandidate(before, { ...request, acf_patches: patches }), { code: 'CONTENT_MANAGEMENT_MISMATCH' });
  const ambiguous = copy(before); ambiguous.acf_definitions.push(copy(ambiguous.acf_definitions[0]));
  assert.throws(() => managementCandidate(ambiguous, { ...request, before_sha256: delegationDigest(ambiguous) }), { code: 'CONTENT_MANAGEMENT_MISMATCH' });
});

test('creation requires an explicit named draft and cannot apply fields before the new ID exists', () => {
  const fixture = fixtures.cases[1];
  for (const mutate of [request => { request.changes.post_status = 'publish'; }, request => { delete request.changes.post_name; },
    request => { request.acf_patches = [{ path: ['field_body'], value: 'x' }]; }]) {
    const request = copy(fixture.migration.request); mutate(request);
    assert.throws(() => managementCandidate(fixture.read.snapshot, request), { code: 'CONTENT_MANAGEMENT_MISMATCH' });
  }
});

test('uncertain execution, changed installed identity or substituted readback never count as verified success', () => {
  for (const fixture of fixtures.cases) {
    assert.throws(() => validateManagementOutcome({ state: 'uncertain', site_id: fixture.review.site_id, site_url: fixture.review.site_url,
      operation_id: fixture.review.operation_id, changed: null, rollback_available: false }, fixture.installed_read, fixture.review), { code: 'CONTENT_OUTCOME_UNVERIFIED' });
    for (const mutate of [state => { state.site_id++; }, state => { state.post_id++; },
      state => { state.installed_sha256 = '0'.repeat(64); }, state => { state.rollback_available = !state.rollback_available; }]) {
      const state = copy(fixture.state); mutate(state);
      assert.throws(() => validateManagementOutcome(state, fixture.installed_read, fixture.review), { code: 'CONTENT_MANAGEMENT_MISMATCH' });
    }
    const read = copy(fixture.installed_read); read.snapshot.core.post_content = 'Different installed body';
    read.snapshot_sha256 = delegationDigest(read.snapshot);
    assert.throws(() => validateManagementRead(read, { site: { id: fixture.review.site_id + 1, url: fixture.review.site_url }, target: fixture.review.target }),
      { code: 'CONTENT_MANAGEMENT_MISMATCH' });
    assert.throws(() => validateManagementOutcome({ ...fixture.state, installed_sha256: read.snapshot_sha256 }, read, fixture.review),
      { code: 'CONTENT_MANAGEMENT_MISMATCH' });
  }
});
