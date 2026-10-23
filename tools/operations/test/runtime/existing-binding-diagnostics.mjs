// Fixture-only projection. Never retain messages, input, signatures or bodies.
const toolLabels = new Map([
  ['mrn_mainwp__get_content_edit_binding_readiness_v2', 'readiness'],
  ['mrn_mainwp__bind_content_edit_authorization_v2', 'bind'],
  ['mrn_mainwp__read_content_edit_authorization_v2', 'binding-read'],
  ['mrn_mainwp__get_content_edit_qualification_context_existing_v2', 'existing-context'],
  ['mrn_mainwp__capture_content_edit_qualification_existing_v2', 'existing-capture'],
  ['mrn_mainwp__read_content_edit_qualification_capture_existing_v2', 'existing-capture-read'],
  ['mrn_mainwp__read_content_edit_qualification_measurements_existing_v2', 'existing-measurements-read'],
  ['mrn_mainwp__read_management_access_veto_query_existing_v2', 'existing-access-read'],
  ['mrn_mainwp__get_delegation_challenge_v1', 'challenge'],
  ['mrn_mainwp__get_team_access_v1', 'staff-access'],
  ['mrn_mainwp__read_delegated_operation_v1', 'delegated-read'],
  ['mrn_mainwp__run_delegated_operation_v1', 'delegated-write'],
  ['mrn_mainwp__sync_delegated_site_v1', 'sync'],
]);
const codes = new Set([
  'mrn_binding_contract', 'mrn_binding_control', 'mrn_binding_readiness',
  'mrn_existing_control',
  'mrn_management_original_authorization', 'mrn_management_existing_original', 'mrn_management_qualification',
  'mrn_requester_initialize', 'mrn_requester_review', 'mrn_requester_state',
  'mrn_content_approval', 'mrn_content_consumed', 'mrn_content_epoch', 'mrn_content_input',
  'mrn_content_json', 'mrn_content_output', 'mrn_content_profile', 'mrn_content_protocol',
  'mrn_content_record', 'mrn_content_retry', 'mrn_content_size', 'mrn_content_source',
  'mrn_content_stale', 'mrn_content_storage', 'mrn_content_target', 'mrn_content_uncertain',
  'mrn_code_disabled', 'mrn_delegation_audit', 'mrn_delegation_binding', 'mrn_delegation_challenge',
  'mrn_delegation_claims', 'mrn_delegation_confirmation', 'mrn_delegation_expired', 'mrn_delegation_google',
  'mrn_delegation_guard', 'mrn_delegation_input', 'mrn_delegation_invalid', 'mrn_delegation_json',
  'mrn_delegation_mode', 'mrn_delegation_owner', 'mrn_delegation_policy', 'mrn_delegation_replay',
  'mrn_delegation_required', 'mrn_delegation_retained', 'mrn_delegation_revoked', 'mrn_delegation_role',
  'mrn_delegation_signature', 'mrn_delegation_storage', 'mrn_delegation_target',
  'mrn_delegation_unavailable', 'mrn_delegation_uncertain',
  'rest_forbidden', 'rest_invalid_param', 'rest_missing_callback_param',
]);

export function bindingFixtureDiagnostic(name, value, confirmed = false, transportError = false) {
  let kind = 'returned', remote;
  if (transportError) { kind = 'transport-error'; remote = value; }
  else {
    const text = Array.isArray(value?.content)
      ? value.content.find(item => item?.type === 'text' && typeof item.text === 'string')?.text : undefined;
    if (text === undefined) kind = 'unparseable';
    else if (Buffer.byteLength(text) > 131072) kind = 'not-inspected';
    else {
      try {
        const body = JSON.parse(text);
        if (value.isError || body?.error) { kind = 'tool-error'; remote = body?.error ?? body; }
      } catch { kind = 'unparseable'; }
    }
  }
  let code = codes.has(remote?.code) ? remote.code : codes.has(remote?.data?.upstream_code)
    ? remote.data.upstream_code : codes.has(remote) ? remote : undefined;
  const message = typeof remote === 'string' ? remote : remote?.message;
  if (!code && typeof message === 'string' && message.length <= 131072) {
    const symbol = /^(?:MainWP failed: )?Ability execution failed: ([a-z0-9_]+)(?: - | \(HTTP [1-5][0-9]{2}\)\.)/.exec(message)?.[1];
    if (codes.has(symbol)) code = symbol;
  }
  return { tool: toolLabels.get(name) ?? 'other', kind, confirmed: confirmed === true,
    provider_code: code ?? (['tool-error', 'transport-error'].includes(kind) ? 'unclassified' : 'none') };
}

const routeLabels = new Map([...toolLabels].map(([tool, label]) =>
  [`/wp-json/wp-abilities/v1/abilities/mrn-mainwp/${tool.slice('mrn_mainwp__'.length).replaceAll('_', '-')}/run`, label]));

// Publish only fixed labels and numeric HTTP status. Raw paths, subjects,
// request bodies and arbitrary error strings stay out of the retained report.
export function bindingFixtureNetworkDiagnostic(bytes) {
  if (bytes === undefined) return { state: 'unavailable', requests: [] };
  if (typeof bytes !== 'string' || Buffer.byteLength(bytes) > 1048576)
    return { state: 'not-inspected', requests: [] };
  const lines = bytes.trim() ? bytes.trim().split('\n') : [];
  if (lines.length > 4096) return { state: 'not-inspected', requests: [] };
  const requests = [];
  try {
    for (const line of lines) {
      const row = JSON.parse(line);
      if (!row || typeof row !== 'object' || Array.isArray(row)) return { state: 'malformed', requests: [] };
      const tool = routeLabels.get(row.path);
      if (!tool) continue;
      // Readiness uses GET; private registrar and delegated controls use POST.
      // Their request-method gates remain in the actual transport guard.
      if (!['GET', 'POST'].includes(row.method) || !Number.isInteger(row.status) || row.status < 100 || row.status > 599)
        return { state: 'malformed', requests: [] };
      requests.push({ tool, status: row.status,
        provider_code: codes.has(row.errorCode) ? row.errorCode : row.status >= 400 ? 'unclassified' : 'none' });
    }
  } catch { return { state: 'malformed', requests: [] }; }
  return { state: 'available', requests: requests.slice(-32) };
}
