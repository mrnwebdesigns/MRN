import test from 'node:test';
import assert from 'node:assert/strict';
import { reportFailureDiagnostics } from './runtime/report-failure-diagnostics.mjs';

const path = '/wp-json/wp-abilities/v1/abilities/mrn-mainwp/retain-content-edit-qualification-report-v1/run';
test('failed custody diagnostics retain only closed stages, statuses and symbolic codes', () => {
  const raw = [
    { path: '/private-secret', status: 500, errorCode: 'private-secret' },
    { path, method: 'POST', status: 500, errorCode: 'mrn_management_report_storage', subject: 'private-secret', message: 'private-secret' },
    { path, method: 'POST', status: 500, errorCode: 'private-secret' },
    { path, method: 'POST', status: 200 },
  ].map(JSON.stringify).join('\n');
  const result = reportFailureDiagnostics(raw);
  assert.deepEqual(result, { available: true, responses: [
    { stage: 'retain', status: 500, code: 'mrn_management_report_storage' },
    { stage: 'retain', status: 500, code: 'OTHER' }, { stage: 'retain', status: 200, code: 'NONE' },
  ] });
  assert.equal(JSON.stringify(result).includes('private-secret'), false);
});

test('malformed or oversized fixture diagnostics are unavailable and bounded to eight responses', () => {
  for (const raw of [undefined, '{private-secret', 'x'.repeat(2097153),
    JSON.stringify({ path, method: 'POST', status: 'private-secret' }),
    JSON.stringify({ path, method: 'GET', status: 500 })]) {
    assert.deepEqual(reportFailureDiagnostics(raw), { available: false, responses: [] });
  }
  const row = JSON.stringify({ path, method: 'POST', status: 500, errorCode: 'mrn_management_report_acquisition' });
  assert.equal(reportFailureDiagnostics(Array(20).fill(row).join('\n')).responses.length, 8);
});
