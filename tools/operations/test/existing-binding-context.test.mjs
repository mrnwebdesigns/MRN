import test from 'node:test';
import assert from 'node:assert/strict';
import { existingBindingProfileFiles, parseExistingBindingContext,
  parseExistingSourceContextOwner, parseExistingSourceContextAudit } from './runtime/existing-binding-context.mjs';

const now = 1800000000000;
function fixture() {
  return { schema: 2, mode: 'disposable-existing-binding-v2', production_credentials: false, production_activation: false,
    consumer_binding_issued: false, site: { site_id: 7, site_url: 'https://child.fixture.example.invalid' },
    target: { kind: 'wordpress-content', post_type: 'page', post_id: 12 },
    actor: { subject: 'google-oauth2|222222', googleSubject: '222222', verifiedEmail: 'bob@mrnwebdesigns.com',
      identityProvider: 'google-oauth2', hostedDomain: 'mrnwebdesigns.com', googleConnection: 'con_RuntimeFixture',
      expiresAt: now + 300000, scopes: ['mrn:operations', 'mrn:operations:write'] },
    profile_source: Object.fromEntries(existingBindingProfileFiles.map(file => [file, 'a'.repeat(64)])),
    profile_runtime_url: 'https://mrn-qa.internal:9443',
    pins: { provider_source_sha: 'a'.repeat(40), installed_manifest_sha256: 'a'.repeat(64), source_binding_sha256: 'a'.repeat(64),
      authorization_policy_sha256: 'b'.repeat(64), delegation_policy_sha256: 'c'.repeat(64), coherence_epoch_sha256: 'd'.repeat(64),
      qualification_catalog_sha256: 'e'.repeat(64), qualification_authority_sha256: 'f'.repeat(64) },
    limits: { hook_seconds: 300, owner_wait_seconds: 320 } };
}
const parse = value => parseExistingBindingContext(Buffer.from(JSON.stringify(value)), now);
test('v2 fixture accepts only its separate synthetic existing-record descriptor', () => {
  const value = fixture(); assert.deepEqual(parse(value), value);
  for (const [key, replacement] of [['schema', 1], ['mode', 'disposable-management-integration'],
    ['production_credentials', true], ['production_activation', true], ['consumer_binding_issued', true]]) {
    assert.throws(() => parse({ ...value, [key]: replacement }));
  }
});
test('wrong identity, real host, new record, stale grant and extended deadlines cannot enter the native hook', () => {
  const changes = [c => { c.site.site_url = 'https://mrnwebdesigns.com'; }, c => { c.target.post_id = 0; },
    c => { c.actor.subject = 'google-oauth2|111111'; }, c => { c.actor.verifiedEmail = 'owner@mrnwebdesigns.com'; },
    c => { c.actor.googleConnection = 'con_Production'; }, c => { c.actor.expiresAt = now; },
    c => { c.actor.expiresAt += 1000; }, c => { c.actor.scopes.pop(); },
    c => { c.limits.hook_seconds = 180; }, c => { c.limits.hook_seconds = 301; },
    c => { c.limits.owner_wait_seconds = 200; }, c => { c.limits.owner_wait_seconds = 321; }];
  for (const change of changes) { const c = fixture(); change(c); assert.throws(() => parse(c)); }
});
test('every descriptor boundary is closed and every profile file and immutable pin is mandatory', () => {
  for (const key of [null, 'site', 'target', 'actor', 'profile_source', 'pins', 'limits']) {
    const c = fixture(); (key ? c[key] : c).unexpected = true; assert.throws(() => parse(c));
  }
  for (const key of ['profile_source', 'pins']) for (const field of Object.keys(fixture()[key])) {
    const missing = fixture(); delete missing[key][field]; assert.throws(() => parse(missing));
    const corrupt = fixture(); corrupt[key][field] = 'not-an-immutable-hash'; assert.throws(() => parse(corrupt));
  }
  assert.throws(() => parseExistingBindingContext(Buffer.alloc(32769), now));
  assert.throws(() => parseExistingBindingContext(Buffer.from('{broken'), now));
});

test('independent source owner sidecar is bounded, closed and synthetic only', () => {
  const value = { schema: 2, mode: 'disposable-existing-source-context-owner-v2', production_credentials: false,
    production_activation: false, evidence_policy_epoch: 1, evidence_policy_sha256: 'a'.repeat(64),
    source_recipe_catalog_sha256: 'b'.repeat(64) };
  const parse = v => parseExistingSourceContextOwner(Buffer.from(JSON.stringify(v)));
  assert.deepEqual(parse(value), value);
  for (const key of Object.keys(value)) { const missing = { ...value }; delete missing[key]; assert.throws(() => parse(missing)); }
  for (const change of [{ schema: 1 }, { mode: 'production' }, { production_credentials: true }, { production_activation: true },
    { evidence_policy_epoch: 0 }, { evidence_policy_epoch: 1.5 }, { evidence_policy_sha256: 'bad' },
    { source_recipe_catalog_sha256: 'bad' }, { signed_request: {} }]) assert.throws(() => parse({ ...value, ...change }));
  for (const bytes of [Buffer.alloc(4097), Buffer.from('{bad'), {}, Buffer.from('null')])
    assert.throws(() => parseExistingSourceContextOwner(bytes));
});

test('source audit requires complete original-bound at-rest proof without claiming current access', () => {
  const value = { result: 'PASS', schema: 2, qualification_job_id: '11111111-1111-4111-8111-111111111111',
    binding_sha256: 'a'.repeat(64), source_context_sha256: 'b'.repeat(64), capture_artifact_sha256: 'c'.repeat(64),
    measurement_record_sha256: 'd'.repeat(64), parity_sha256: 'e'.repeat(64), actual_original_marker_count: 2,
    independent_consistent_readback: true, complete_sidecar_relation: true, sidecar_records: 7,
    at_rest_only: true, current_access_proven: false, admitted: false, production_changes: false };
  const parse = v => parseExistingSourceContextAudit(Buffer.from(JSON.stringify(v)));
  assert.deepEqual(parse(value), value);
  for (const key of Object.keys(value)) { const missing = { ...value }; delete missing[key]; assert.throws(() => parse(missing)); }
  for (const change of [{ result: 'FAIL' }, { schema: 1 }, { qualification_job_id: 'bad' }, { binding_sha256: 'bad' },
    { source_context_sha256: 'bad' }, { capture_artifact_sha256: 'bad' }, { measurement_record_sha256: 'bad' }, { parity_sha256: 'bad' },
    { actual_original_marker_count: 1 }, { sidecar_records: 6 },
    { independent_consistent_readback: false }, { complete_sidecar_relation: false }, { at_rest_only: false },
    { current_access_proven: true }, { admitted: true }, { production_changes: true }, { raw: 'PRIVATE' }])
    assert.throws(() => parse({ ...value, ...change }));
  for (const bytes of [Buffer.alloc(4097), Buffer.from('{bad'), {}, Buffer.from('null')])
    assert.throws(() => parseExistingSourceContextAudit(bytes));
});
