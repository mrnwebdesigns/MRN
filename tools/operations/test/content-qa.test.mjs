import test from 'node:test';
import assert from 'node:assert/strict';
import { createPublicKey, verify } from 'node:crypto';
import { readFileSync } from 'node:fs';
import { contentQaFixture, makePlan, original, target, profile } from './fixtures/content.mjs';
import { contentSchemas, validateContentPlan, validateContentMigration, contentSnapshot, contentPaths, contentPlanDescriptor } from '../src/content-contract.mjs';
import { contentCandidate, verifyContentReceipt, ProtectedContentQa, contentQaPolicySchema, managementQaInput, managementReportFromZip } from '../src/content-qa.mjs';
import { managementCandidate, managementCandidateHash } from '../src/content-management-contract.mjs';
import { digest } from '../src/contracts.mjs';
import { delegationCanonical, delegationDigest } from '../src/delegation.mjs';

function managementUnit() {
  const legacy = contentQaFixture(), vector = JSON.parse(readFileSync(new URL('./fixtures/content-management-v1.json', import.meta.url))).cases[0];
  const read = structuredClone(vector.read), source = Object.fromEntries(['child/content-acf.php', 'child/content-fields.php', 'child/content-records.php',
    'includes/class-mrn-mainwp-content-management.php', 'includes/class-mrn-mainwp-content-source.php', 'release-source.json'].map(k => [k, 'a'.repeat(64)]));
  const hash = delegationDigest({ adapter: 'content-management-v1', runtime: read.snapshot.runtime, acf_definitions: read.snapshot.acf_definitions, source });
  const descriptor = { sha256: hash, runtime_url: 'https://mrn-qa.internal:9443', runtime_context_sha256: hash };
  const path = ['field_rows', 0, 'field_body'], preview = { site_id: read.snapshot.site_id, site_url: read.snapshot.site_url,
    post_type: 'page', post_id: read.snapshot.post_id, expected_snapshot_sha256: read.snapshot_sha256,
    idempotency_key: '77777777-7777-4777-8777-777777777777', reason: 'Unit fixture only', acf_patches: [{ path, value: '<p>Unit replacement</p>' }] };
  const { expected_snapshot_sha256: before_sha256, idempotency_key: _id, reason, ...base } = preview;
  const patchRequest = { ...base, before_sha256 }, candidate = managementCandidate(read.snapshot, patchRequest);
  const operation_id = '88888888-8888-4888-8888-888888888888', migration = { schema: 1, adapter: 'content-management-v1', operation_id, reason,
    request: patchRequest, candidate_snapshot: candidate, candidate_sha256: managementCandidateHash(candidate), rollback_snapshot: read.snapshot };
  const review = { site_id: preview.site_id, site_url: preview.site_url, operation_id,
    target: { kind: 'wordpress-content', post_type: 'page', post_id: preview.post_id }, before_snapshot: read.snapshot,
    candidate_snapshot: candidate, before_sha256, candidate_sha256: migration.candidate_sha256, affected_paths: read.affected_paths,
    rollback_operation_id: '', migration_artifact: { ref: `mrn-content://management/${operation_id}`, sha256: delegationDigest(migration) },
    profile: descriptor, expires_at: Math.floor(Date.now() / 1000) + 900 };
  review.digest = delegationDigest(review);
  const request = managementQaInput({ review, read, migration }, preview, '1'.repeat(32));
  const policy = contentQaPolicySchema.parse({ ...legacy.policy, profiles: [{ siteUrl: preview.site_url, profile: descriptor,
    targets: [{ target: review.target, selector: '.unit-acf-copy', acfPath: path, providerSource: source }] }] });
  const reportSha256 = digest('Unit fixture report; no native QA claim');
  const claims = { ...vector.qa_claims, issued_at: Math.floor(Date.now() / 1000), expires_at: review.expires_at,
    receipt_id: digest('7:1:' + request.request_id).slice(0, 32), site_id: review.site_id, site_url: review.site_url, target: review.target,
    before_sha256: review.before_sha256, candidate_sha256: review.candidate_sha256, plan_digest: review.digest,
    rollback_operation_id: '', qa_report_sha256: reportSha256, engine_commit: policy.engineCommit, runtime_url: descriptor.runtime_url,
    runtime_context_sha256: hash, profile_sha256: hash, affected_paths: review.affected_paths, migration_artifact_sha256: review.migration_artifact.sha256 };
  const envelope = legacy.envelope(claims, { prefix: 'mrn-content-management-qa-v1:' });
  return { request, policy, qa: new ProtectedContentQa(null, policy), reportSha256, claims, envelope, legacy };
}

test('typed ACF unit qualification binds complete snapshots, one owner-qualified scalar and separate QA purpose/report', () => {
  const f = managementUnit(); assert.equal(f.qa.qualifyRequest(f.request).kind, 'content-management');
  assert.equal(f.qa.verifyReceipt(f.envelope, f.request, 7, f.reportSha256).claims.qa_report_sha256, f.reportSha256);
  assert.throws(() => f.qa.verifyReceipt(f.envelope, f.request, 7, '0'.repeat(64)), { code: 'QA_PROVENANCE' });
  assert.throws(() => f.qa.verifyReceipt(f.legacy.envelope(f.claims), f.request, 7, f.reportSha256), { code: 'QA_SIGNATURE' });
  for (const change of [r => { r.preview.changes = { post_title: 'Unrequested core' }; }, r => { r.preview.acf_patches.push(r.preview.acf_patches[0]); },
    r => { r.preview.acf_patches[0].path = ['field_rows', 1, 'field_body']; }, r => { r.read.snapshot.runtime.acf = 'different'; },
    r => { r.review.affected_paths = ['/other/']; }, r => { r.preview.extra = true; }]) {
    const request = structuredClone(f.request); change(request); assert.throws(() => f.qa.qualifyRequest(request));
  }
});

function archive(members) {
  const locals = [], central = []; let offset = 0;
  for (const [name, input] of Object.entries(members)) {
    const bytes = Buffer.from(input), key = Buffer.from(name); let crc = 0xffffffff;
    for (const byte of bytes) { crc ^= byte; for (let n = 0; n < 8; n++) crc = (crc >>> 1) ^ ((crc & 1) ? 0xedb88320 : 0); }
    crc = (crc ^ 0xffffffff) >>> 0;
    const local = Buffer.alloc(30); local.writeUInt32LE(0x04034b50); local.writeUInt32LE(crc, 14); local.writeUInt32LE(bytes.length, 18); local.writeUInt32LE(bytes.length, 22); local.writeUInt16LE(key.length, 26);
    const entry = Buffer.alloc(46); entry.writeUInt32LE(0x02014b50); entry.writeUInt32LE(crc, 16); entry.writeUInt32LE(bytes.length, 20); entry.writeUInt32LE(bytes.length, 24); entry.writeUInt16LE(key.length, 28); entry.writeUInt32LE(offset, 42);
    locals.push(local, key, bytes); central.push(entry, key); offset += local.length + key.length + bytes.length;
  }
  const directory = Buffer.concat(central), end = Buffer.alloc(22), count = Object.keys(members).length; end.writeUInt32LE(0x06054b50); end.writeUInt16LE(count, 8); end.writeUInt16LE(count, 10); end.writeUInt32LE(directory.length, 12); end.writeUInt32LE(offset, 16);
  return Buffer.concat([...locals, directory, end]);
}
test('management report identity comes from exact bounded qualification archive bytes', () => {
  const f = managementUnit(), members = { 'qa.md': 'Unit fixture report', 'qualification.json': '{}', 'request.json': delegationCanonical(f.request) };
  assert.equal(managementReportFromZip(archive(members), f.request), digest(members['qa.md']));
  for (const changed of [{ ...members, 'request.json': '{}' }, { ...members, 'qa.md': 'x'.repeat(1024 * 1024 + 1) },
    { ...members, '../qa.md': members['qa.md'] }, { ...members, 'qualification.json': '' }])
    assert.throws(() => managementReportFromZip(archive(changed), f.request), { code: 'QA_ARCHIVE' });
  const corrupt = archive(members); corrupt[35] ^= 1; assert.throws(() => managementReportFromZip(corrupt, f.request));
});

test('protected management receipt verifies exact run and independently downloaded report artifact', async () => {
  const f = managementUnit(), repository = f.policy.repository, ticket = { runId: 7, requestId: f.request.request_id,
    workflowId: 42, workflowCommit: f.policy.workflowCommit, url: `https://github.com/${repository}/actions/runs/7` };
  const state = { badRun: false, badArtifact: false, badReport: false };
  const api = async route => {
    if (route === '/actions/runs/7') return { id: 7, run_attempt: 1, repository: { id: 99, full_name: repository }, head_repository: { id: 99 },
      event: 'workflow_dispatch', head_branch: 'main', head_sha: state.badRun ? '0'.repeat(40) : ticket.workflowCommit,
      workflow_id: 42, path: '.github/workflows/content-qa.yml', display_title: `MRN Content QA ${ticket.requestId}`,
      actor: { login: f.policy.actor }, triggering_actor: { login: f.policy.actor }, status: 'completed', conclusion: 'success' };
    assert.equal(route, '/actions/runs/7/artifacts?per_page=100');
    return { total_count: 2, artifacts: ['receipt', 'qualification'].map((name, i) => ({ id: i + 11, name: `${name}-7-1`, expired: false,
      size_in_bytes: 30000, workflow_run: { id: 7, head_sha: state.badArtifact && name === 'qualification' ? '0'.repeat(40) : ticket.workflowCommit } })) };
  };
  api.artifact = async id => id === 11 ? archive({ 'receipt.json': JSON.stringify(f.envelope) }) : archive({
    'qa.md': state.badReport ? 'Substituted report' : 'Unit fixture report; no native QA claim', 'qualification.json': '{}', 'request.json': delegationCanonical(f.request) });
  f.qa.github = { repositories: { [repository]: 99 }, scoped: async () => api };
  assert.equal((await f.qa.receipt(ticket, f.request, async () => {})).reportSha256, f.reportSha256);
  for (const [flag, code] of [['badRun', 'QA_PROVENANCE'], ['badArtifact', 'QA_ARTIFACT'], ['badReport', 'QA_PROVENANCE']]) {
    state[flag] = true; await assert.rejects(() => f.qa.receipt(ticket, f.request, async () => {}), { code }); state[flag] = false;
  }
});

test('PHP provider public content vector has identical semantic hashes, descriptor and Ed25519 signature in Node', () => {
  const vector = JSON.parse(readFileSync(new URL('./fixtures/content-v1.json', import.meta.url)));
  assert.equal(delegationDigest(vector.before_snapshot), vector.before_sha256);
  assert.equal(delegationDigest(vector.candidate_snapshot), vector.candidate_sha256);
  assert.equal(delegationDigest(vector.migration_bundle), vector.migration_artifact_sha256);
  assert.equal(delegationDigest(vector.plan_descriptor), vector.plan_digest);
  const descriptor = contentPlanDescriptor({ ...vector.plan_descriptor, migration_artifact: { sha256: vector.migration_artifact_sha256 }, profile: { sha256: vector.plan_descriptor.profile_sha256 } });
  assert.deepEqual(descriptor, vector.plan_descriptor);
  const bytes = Buffer.from(vector.receipt_envelope.payload_base64, 'base64');
  assert.equal(bytes.toString('utf8'), delegationCanonical(vector.receipt_claims));
  const key = createPublicKey({ key: Buffer.concat([Buffer.from('302a300506032b6570032100', 'hex'), Buffer.from(vector.public_key_base64, 'base64')]), format: 'der', type: 'spki' });
  assert.equal(verify(null, Buffer.concat([Buffer.from('mrn-content-qa-v1:'), bytes]), key, Buffer.from(vector.receipt_envelope.signature_base64, 'base64')), true);
  assert.equal(vector.rollback_case.rollback_snapshot_sha256, vector.candidate_sha256);
});

test('frozen content schemas reject unknown fields, invalid Unicode and UTF-8 byte overflow', () => {
  assert.throws(() => contentSchemas['inspect-content-v1'].input.parse({ site_id: 1, site_url: original.site_url, target, email: 'caller@example.test' }));
  assert.throws(() => contentSnapshot({ ...original, post_content: '\ud800' }));
  assert.throws(() => contentSnapshot({ ...original, post_content: '😀'.repeat(3000) }), { code: 'CONTENT_SIZE' });
  for (const path of ['//outsider.test/', '/\\outsider.test/', '/sample/../private/', '/%2foutsider/', '/sample/?token=private'])
    assert.throws(() => contentPaths([path], original.site_url), { code: 'CONTENT_PUBLIC_PATH' });
});

test('content plans bind one changed field, baseline, profile, migration and rollback identity', () => {
  const { plan, migration } = makePlan();
  const context = { site: { id: 1, url: original.site_url }, target, profile,
    inspected: { snapshot: original, affected_paths: plan.affected_paths, stable_selector: plan.stable_selector } };
  assert.equal(validateContentPlan(plan, context).operation_id, plan.operation_id);
  assert.equal(validateContentMigration(plan, migration).bundle.candidate_sha256, plan.candidate_sha256);
  for (const change of [{ candidate_snapshot: { ...plan.candidate_snapshot, post_title: 'Unrequested' } }, { before_sha256: '0'.repeat(64) },
    { site_id: 2 }, { profile: { ...profile, runtime_context_sha256: '0'.repeat(64) } }, { rollback_operation_id: plan.operation_id }])
    assert.throws(() => validateContentPlan({ ...plan, ...change }, context));
  assert.throws(() => validateContentMigration(plan, { ...migration, bundle: { ...migration.bundle, adapter_version: '2' } }));
});

test('protected content receipts reject code signatures, mismatched fields, stale receipts and noncanonical claims', () => {
  const f = contentQaFixture(), claims = f.claims(f.request, 7);
  assert.equal(verifyContentReceipt(f.envelope(claims), f.request, f.policy, 7).claims.kind, 'content');
  for (const change of [{ kind: 'file' }, { target: { ...target, field: 'post_title' } }, { affected_paths: ['/other/'] },
    { profile_sha256: '0'.repeat(64) }, { runtime_context_sha256: '0'.repeat(64) }, { migration_artifact_sha256: '0'.repeat(64) },
    { plan_digest: '0'.repeat(64) }, { site_id: 2 }, { expires_at: 1 }, { receipt_id: '0'.repeat(32) }, { engine_commit: '0'.repeat(40) }])
    assert.throws(() => verifyContentReceipt(f.envelope({ ...claims, ...change }), f.request, f.policy, 7));
  assert.throws(() => verifyContentReceipt(f.envelope(claims, { prefix: 'mrn-code-qa-v1:' }), f.request, f.policy, 7), { code: 'QA_SIGNATURE' });
  assert.throws(() => verifyContentReceipt(f.envelope(claims, { canonical: false }), f.request, f.policy, 7), { code: 'QA_CANONICAL' });
  assert.throws(() => contentCandidate({ ...f.request, new_value: 'Different' }), { code: 'CONTENT_QA_SNAPSHOT' });
});

test('content QA cannot expand company qualification or omit the exact render assertion', () => {
  const f = contentQaFixture();
  assert.equal(f.qa.qualifyRequest(f.request).operation_id, f.request.operation_id);
  assert.throws(() => f.qa.profile('https://client.test', target, profile), { code: 'CONTENT_PROFILE_UNQUALIFIED' });
  assert.throws(() => f.qa.profile(original.site_url, { ...target, post_id: 43 }, profile), { code: 'CONTENT_TARGET_UNQUALIFIED' });
  assert.throws(() => f.qa.profile(original.site_url, target, { ...profile, ownership: 'builder' }), { code: 'CONTENT_PROFILE_UNQUALIFIED' });
  assert.equal(f.qa.workflowFile(), 'content-qa.yml');
});
