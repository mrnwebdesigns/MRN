import test from 'node:test';
import assert from 'node:assert/strict';
import { bindingFixtureDiagnostic, bindingFixtureNetworkDiagnostic } from './runtime/existing-binding-diagnostics.mjs';

const name = 'mrn_mainwp__get_content_edit_binding_readiness_v2';
const result = (body, isError = false) => ({ content: [{ type: 'text', text: JSON.stringify(body) }], isError });

test('fixture diagnostics retain a known refusal without messages, identity or private error data', () => {
  const secret = 'PRIVATE_FIXTURE_MATERIAL';
  for (const error of [
    { code: 'mrn_delegation_required', message: secret, data: { token: secret } },
    { code: -32603, message: secret, data: { upstream_code: 'mrn_delegation_required', token: secret } },
    `Ability execution failed: mrn_delegation_required - ${secret}`,
    `Ability execution failed: mrn_delegation_required (HTTP 400). ${secret}`,
  ]) {
    const projected = bindingFixtureDiagnostic(name, result({ error, subject: secret }, true));
    assert.deepEqual(projected, { tool: 'readiness', kind: 'tool-error', confirmed: false, provider_code: 'mrn_delegation_required' });
    assert.equal(JSON.stringify(projected).includes(secret), false);
  }
});

test('failure network projection distinguishes proxy HTTP errors from provider refusals without private data', () => {
  const path = '/wp-json/wp-abilities/v1/abilities/mrn-mainwp/capture-content-edit-qualification-existing-v2/run';
  const secret = 'PRIVATE_FIXTURE_MATERIAL';
  const rows = [
    { path: `/unknown/${secret}`, status: 401, subject: secret },
    { path: '/wp-json/wp-abilities/v1/abilities/mrn-mainwp/get-content-edit-binding-readiness-v2/run', method: 'GET', status: 200 },
    { path, method: 'POST', status: 504, subject: secret, errorCode: secret, body: secret },
    { path, method: 'POST', status: 403, errorCode: 'mrn_delegation_revoked', subject: secret },
    { path, method: 'POST', status: 200, subject: secret },
  ];
  const projected = bindingFixtureNetworkDiagnostic(rows.map(row => JSON.stringify(row)).join('\n') + '\n');
  assert.deepEqual(projected, { state: 'available', requests: [
    { tool: 'readiness', status: 200, provider_code: 'none' },
    { tool: 'existing-capture', status: 504, provider_code: 'unclassified' },
    { tool: 'existing-capture', status: 403, provider_code: 'mrn_delegation_revoked' },
    { tool: 'existing-capture', status: 200, provider_code: 'none' },
  ] });
  assert.equal(JSON.stringify(projected).includes(secret), false);
  assert.equal(bindingFixtureNetworkDiagnostic(Array(33).fill(JSON.stringify(rows[2])).join('\n')).requests.length, 32);
});

test('failure network projection refuses malformed and oversized evidence without masking the original failure', () => {
  assert.deepEqual(bindingFixtureNetworkDiagnostic(), { state: 'unavailable', requests: [] });
  assert.deepEqual(bindingFixtureNetworkDiagnostic(''), { state: 'available', requests: [] });
  for (const bytes of ['PRIVATE', 'null', '{}\nPRIVATE'])
    assert.deepEqual(bindingFixtureNetworkDiagnostic(bytes), { state: 'malformed', requests: [] });
  const path = '/wp-json/wp-abilities/v1/abilities/mrn-mainwp/read-content-edit-qualification-capture-existing-v2/run';
  for (const status of ['504', 99, 600, null, 200.5])
    assert.deepEqual(bindingFixtureNetworkDiagnostic(JSON.stringify({ path, method: 'POST', status })),
      { state: 'malformed', requests: [] });
  assert.deepEqual(bindingFixtureNetworkDiagnostic(JSON.stringify({ path, method: 'PRIVATE', status: 200 })),
    { state: 'malformed', requests: [] });
  for (const bytes of ['x'.repeat(1048577), Array(4097).fill('{}').join('\n'), {}])
    assert.deepEqual(bindingFixtureNetworkDiagnostic(bytes), { state: 'not-inspected', requests: [] });
});

test('unknown tools and error codes cannot reflect arbitrary provider or transport data', () => {
  const secret = 'PRIVATE_FIXTURE_MATERIAL';
  for (const error of [{ code: secret, message: secret }, { code: 'mrn_binding_readiness', message: secret }]) {
    const projected = bindingFixtureDiagnostic(secret, error, { token: secret }, true);
    assert.equal(projected.tool, 'other'); assert.equal(projected.kind, 'transport-error');
    assert.equal(projected.confirmed, false); assert.equal(JSON.stringify(projected).includes(secret), false);
    assert.equal(projected.provider_code, error.code === secret ? 'unclassified' : error.code);
  }
});

test('successful, malformed and oversized responses have bounded body-free diagnostics', () => {
  const success = bindingFixtureDiagnostic(name, result({ confirmation_token: 'PRIVATE_FIXTURE_MATERIAL' }), true);
  assert.deepEqual(success, { tool: 'readiness', kind: 'returned', confirmed: true, provider_code: 'none' });
  for (const input of [{}, { content: [{ type: 'text', text: 'not JSON PRIVATE_FIXTURE_MATERIAL' }] }])
    assert.equal(bindingFixtureDiagnostic(name, input).kind, 'unparseable');
  const large = bindingFixtureDiagnostic(name, result({ error: 'PRIVATE_FIXTURE_MATERIAL'.repeat(10000) }, true));
  assert.equal(large.kind, 'not-inspected'); assert.equal(JSON.stringify(large).includes('PRIVATE_FIXTURE_MATERIAL'), false);
});

test('existing context and identity callback diagnostics preserve only fixed route labels and denial codes', () => {
  for (const [name, label] of [
    ['mrn_mainwp__get_content_edit_qualification_context_existing_v2', 'existing-context'],
    ['mrn_mainwp__read_management_access_veto_query_existing_v2', 'existing-access-read'],
    ['mrn_mainwp__capture_content_edit_qualification_existing_v2', 'existing-capture'],
    ['mrn_mainwp__read_content_edit_qualification_capture_existing_v2', 'existing-capture-read'],
    ['mrn_mainwp__read_content_edit_qualification_measurements_existing_v2', 'existing-measurements-read'],
    ['mrn_mainwp__read_content_edit_qualification_source_context_existing_v2', 'existing-source-context'],
  ]) {
    assert.deepEqual(bindingFixtureDiagnostic(name, result({ error: { code: 'mrn_existing_control', message: 'PRIVATE' } }, true)),
      { tool: label, kind: 'tool-error', confirmed: false, provider_code: 'mrn_existing_control' });
  }
  assert.deepEqual(bindingFixtureDiagnostic('mrn_mainwp__read_content_edit_qualification_source_context_existing_v2',
    result({ error: { code: 'mrn_management_existing_source_context', message: 'PRIVATE' } }, true)),
  { tool: 'existing-source-context', kind: 'tool-error', confirmed: false, provider_code: 'mrn_management_existing_source_context' });
});
