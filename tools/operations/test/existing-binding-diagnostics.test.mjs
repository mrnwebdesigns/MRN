import test from 'node:test';
import assert from 'node:assert/strict';
import { bindingFixtureDiagnostic, bindingFixtureNetworkDiagnostic, createBindingSourceTimings } from './runtime/existing-binding-diagnostics.mjs';

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

test('HTTP timings retain only bounded numeric header durations and preserve older observations', () => {
  const row = { path: '/wp-json/wp-abilities/v1/abilities/mrn-mainwp/get-team-access-v1/run', method: 'POST', status: 200,
    response_headers_ms: 2301, headers: 'PRIVATE', subject: 'PRIVATE', body: 'PRIVATE' };
  assert.deepEqual(bindingFixtureNetworkDiagnostic(JSON.stringify(row)), { state: 'available', requests: [
    { tool: 'staff-access', status: 200, response_headers_ms: 2301, provider_code: 'none' },
  ] });
  for (const value of ['PRIVATE', null, -1, 300001, 0.1])
    assert.deepEqual(bindingFixtureNetworkDiagnostic(JSON.stringify({ ...row, response_headers_ms: value })),
      { state: 'malformed', requests: [] });
});

test('source timing preserves pending and rejected I/O without recording results, errors or arbitrary labels', async () => {
  let at = 100, finish;
  const timing = createBindingSourceTimings(() => at), secret = new Error('PRIVATE');
  const pending = timing.measure('first', 'staff', () => new Promise(resolve => { finish = resolve; }));
  at += 51;
  await assert.rejects(() => timing.measure('first', 'total', async () => { at += 9; throw secret; }), error => error === secret);
  assert.deepEqual(timing.snapshot(), [
    { span: 'first', step: 'staff', kind: 'pending', started_ms: 0, elapsed_ms: 60 },
    { span: 'first', step: 'total', kind: 'rejected', started_ms: 51, elapsed_ms: 9 },
  ]);
  at += 12; finish({ token: 'PRIVATE' }); assert.deepEqual(await pending, { token: 'PRIVATE' });
  assert.equal(timing.snapshot()[0].kind, 'returned'); assert.equal(timing.snapshot()[0].elapsed_ms, 72);
  for (const [span, step] of [['PRIVATE', 'staff'], ['first', 'PRIVATE']])
    await assert.rejects(() => timing.measure(span, step, () => { throw new Error('must not dispatch'); }));
  assert.equal(JSON.stringify(timing.snapshot()).includes('PRIVATE'), false);
  for (let i = 2; i < 64; i++) await timing.measure('second', 'readiness', async () => {});
  await assert.rejects(() => timing.measure('second', 'readiness', async () => {}));
  assert.equal(timing.snapshot().length, 64);
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

test('source-context failure retains only exact closed provider phase and bounded timing before MCP normalization', async () => {
  const { bindingSourceContextFailure, bindingSourceContextHistory } = await import('./runtime/existing-binding-diagnostics.mjs');
  const value = { phase: 'post-reauth-current', elapsed_ms: 15001, lookup_remaining_ms: 4999,
    claim_committed: true, signed_remaining_ms: 3999, original_remaining_ms: 90000, veto_remaining_ms: -1 };
  const project = data => bindingSourceContextFailure(JSON.stringify({ code: 'mrn_management_existing_source_context',
    message: 'PRIVATE_FIXTURE_MATERIAL', data }));
  assert.deepEqual(project(value), value);
  assert.deepEqual(project({ ...value, status: 500 }), value);
  assert.equal(JSON.stringify(project(value)).includes('PRIVATE'), false);
  const before = { ...value, phase: 'authenticate', claim_committed: false,
    signed_remaining_ms: null, original_remaining_ms: null, veto_remaining_ms: null };
  assert.deepEqual(project(before), before);
  for (const key of Object.keys(value)) { const missing = { ...value }; delete missing[key]; assert.equal(project(missing), null); }
  for (const patch of [{ phase: 'PRIVATE' }, { exception: 'PRIVATE' }, { status: '500' }, { status: 501 }, { status: 600 },
    { claim_committed: 1 }, { elapsed_ms: -1 }, { elapsed_ms: 120001 }, { lookup_remaining_ms: 5000 },
    { signed_remaining_ms: 900001 }, { original_remaining_ms: -900001 }, { veto_remaining_ms: 5001 },
    { veto_remaining_ms: '0' }, { elapsed_ms: 1.5 }]) assert.equal(project({ ...value, ...patch }), null);
  for (const bytes of [undefined, {}, 'PRIVATE', 'null', 'x'.repeat(16385),
    JSON.stringify({ code: 'other', data: value })]) assert.equal(bindingSourceContextFailure(bytes), null);
  assert.deepEqual(bindingSourceContextHistory([before, value].map(row => JSON.stringify(row)).join('\n')),
    { state: 'available', requests: [before, value] });
  assert.deepEqual(bindingSourceContextHistory(), { state: 'unavailable', requests: [] });
  assert.deepEqual(bindingSourceContextHistory(''), { state: 'available', requests: [] });
  for (const bytes of [JSON.stringify({ ...value, status: 500 }), JSON.stringify({ ...value, raw: 'PRIVATE' }), 'null', 'PRIVATE'])
    assert.deepEqual(bindingSourceContextHistory(bytes), { state: 'malformed', requests: [] });
  for (const bytes of ['x'.repeat(16385), Array(5).fill(JSON.stringify(value)).join('\n')])
    assert.deepEqual(bindingSourceContextHistory(bytes), { state: 'not-inspected', requests: [] });
});
