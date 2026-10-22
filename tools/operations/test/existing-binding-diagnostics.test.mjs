import test from 'node:test';
import assert from 'node:assert/strict';
import { bindingFixtureDiagnostic } from './runtime/existing-binding-diagnostics.mjs';

const name = 'mrn_mainwp__get_content_edit_binding_readiness_v2';
const result = (body, isError = false) => ({ content: [{ type: 'text', text: JSON.stringify(body) }], isError });

test('fixture diagnostics retain a known refusal without messages, identity or private error data', () => {
  const secret = 'PRIVATE_FIXTURE_MATERIAL';
  for (const error of [
    { code: 'mrn_delegation_required', message: secret, data: { token: secret } },
    `Ability execution failed: mrn_delegation_required - ${secret}`,
  ]) {
    const projected = bindingFixtureDiagnostic(name, result({ error, subject: secret }, true));
    assert.deepEqual(projected, { tool: 'readiness', kind: 'tool-error', confirmed: false, provider_code: 'mrn_delegation_required' });
    assert.equal(JSON.stringify(projected).includes(secret), false);
  }
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
  ]) {
    assert.deepEqual(bindingFixtureDiagnostic(name, result({ error: { code: 'mrn_existing_control', message: 'PRIVATE' } }, true)),
      { tool: label, kind: 'tool-error', confirmed: false, provider_code: 'mrn_existing_control' });
  }
});
