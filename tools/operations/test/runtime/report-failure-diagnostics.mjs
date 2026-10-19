// Fixture diagnostics only. Never retain arbitrary provider text or identities.
const routes = new Map([
  ['retain-content-edit-qualification-report-v1', 'retain'],
  ['read-content-edit-qualification-report-v1', 'read'],
  ['read-management-evidence-query-v1', 'query'],
].map(([route, stage]) => [`/wp-json/wp-abilities/v1/abilities/mrn-mainwp/${route}/run`, stage]));
const codes = new Set(['mrn_management_report_acquisition', 'mrn_management_report_storage',
  'mrn_management_report_control', 'mrn_management_report_query']);

export function reportFailureDiagnostics(raw) {
  const unavailable = { available: false, responses: [] };
  if (typeof raw !== 'string' || Buffer.byteLength(raw) > 2097152) return unavailable;
  try {
    const responses = [];
    for (const line of raw.split('\n').filter(Boolean)) {
      const row = JSON.parse(line);
      if (!row || !routes.has(row.path)) continue;
      if (row.method !== 'POST' || !Number.isInteger(row.status) || row.status < 100 || row.status > 599) return unavailable;
      responses.push({ stage: routes.get(row.path), status: row.status,
        code: codes.has(row.errorCode) ? row.errorCode : row.errorCode === undefined ? 'NONE' : 'OTHER' });
      if (responses.length > 8) responses.shift();
    }
    return { available: true, responses };
  } catch { return unavailable; }
}
