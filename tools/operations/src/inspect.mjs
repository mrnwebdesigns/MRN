import { compareVersions } from '../../../stack/scripts/mrn-fleet-update.mjs';
import { safeError } from './contracts.mjs';
import { toolNames } from './mainwp.mjs';

const slug = v => typeof v === 'string' && /^[a-z0-9_.-]{1,160}$/.test(v) ? v : 'unknown';
const version = v => typeof v === 'string' && /^[a-zA-Z0-9.+_-]{1,60}$/.test(v) ? v : 'unknown';
const fatal = error => ['MAINWP_ACCESS', 'MAINWP_IDENTITY', 'FORBIDDEN', 'AUTH_REQUIRED', 'TARGET_MISMATCH', 'TARGET_CHANGED'].includes(error.code);
const qualificationStates = new Set(['deployment_agent_unavailable', 'deployment_agent_upgrade_required', 'site_theme_shape_unavailable',
  'incompatible_theme_shape', 'incomplete_rollout_requires_reconciliation', 'deployment_storage_not_ready', 'managed_credentials_not_ready', 'ready_for_release_preflight']);
function qualificationEvidence(data) {
  const boolean = value => typeof value === 'boolean' ? value : null;
  return { classification: qualificationStates.has(data.classification) ? data.classification : 'unknown',
    blockers: [...new Set((Array.isArray(data.blockers) ? data.blockers : []).filter(b => qualificationStates.has(b) && b !== 'ready_for_release_preflight'))],
    agentVersion: version(data.agent?.version), agentAvailable: boolean(data.agent?.available), runtimeAvailable: boolean(data.runtime?.available),
    interpretation: 'Read-only deployment qualification explains missing evidence; it does not establish a verified release baseline or authorize a change.' };
}
export function runtimeEvidence(report) {
  return { schema: report?.schema_version ?? null,
    release: report?.release_lock?.valid === true ? version(report.release_lock.release_id) : null,
    lockSha256: report?.release_lock?.present === true && report?.release_lock?.valid === true && /^[a-f0-9]{64}$/.test(report?.release_lock?.sha256) ? report.release_lock.sha256 : null,
    fleetState: ['current', 'current_with_approved_overlays', 'drifted'].includes(report?.fleet_state) ? report.fleet_state : 'unqualified',
    components: (Array.isArray(report?.components) ? report.components : []).slice(0, 300).map(c => ({ slug: slug(c.slug), version: version(c.version),
      runtimeType: slug(c.runtime_type), loaded: c.loaded === true,
      sha256: /^[a-f0-9]{64}$/.test(c.sha256) ? c.sha256 : null, matchesRelease: c.matches_release === true })),
  };
}
export async function inspectTarget({ target, session, catalog, publicProbe, authorize, previous = null }) {
  const evidence = []; const coverage = []; const findings = [];
  const add = (code, description, data, repairable = false) => findings.push({ code, description, confidence: 'confirmed', repairable, ...data });
  evidence.push({ source: 'mrn:website-knowledge', environment: target.environment, backup: target.backup,
    issues: target.knowledgeIssues || [], facts: (target.facts || []).map(f => ({ ...f, stale: Date.parse(f.expiresAt) <= Date.now() })) });
  coverage.push({ check: 'website_knowledge', status: target.environment === 'unknown' || target.backup === 'unknown' || target.knowledgeIssues?.length ? 'incomplete' : 'recorded',
    interpretation: 'Saved facts describe intended configuration. Missing or conflicting write prerequisites do not prevent read-only inspection.' });
  if (target.management === 'mainwp') {
    const site = await session.fresh(); evidence.push({ source: 'mainwp:exact-site-sync', ...site });
    for (const [name, check] of [[toolNames.runtime, 'stack'], [toolNames.security, 'security'], [toolNames.updates, 'updates'], [toolNames.themes, 'themes'], [toolNames.changes, 'recent_changes']]) {
      if (!await session.supports(name)) { coverage.push({ check, status: 'unavailable', reason: 'Capability not exposed by the connected MainWP service.' }); continue; }
      try {
        if (check === 'stack') {
          const report = await session.runtime(); const runtime = runtimeEvidence(report);
          evidence.push({ source: 'mainwp:runtime', observedAt: new Date().toISOString(), ...runtime });
          coverage.push({ check, status: runtime.lockSha256 ? 'checked' : 'qualification_required' });
          if (!runtime.lockSha256) add('STACK_QUALIFICATION', 'This installation lacks a verified Stack release baseline. Qualification or onboarding is required; missing components will not be installed automatically.', {});
          for (const component of runtime.components) {
            const expected = catalog.components.filter(c => c.slug === component.slug && c.runtime_type === 'standard-plugin' && c.target_tier === 'platform-required');
            if (expected.length !== 1 || !component.loaded || component.runtimeType !== 'standard-plugin') continue;
            let outdated = false;
            try { outdated = compareVersions(component.version, expected[0].version) < 0; } catch { /* Unknown versions require qualification. */ }
            if (outdated) add('STACK_PLUGIN_BEHIND', `${component.slug} is installed at ${component.version}; the approved catalog names ${version(expected[0].version)}. Compatibility and repair readiness still require preflight.`,
              { component: component.slug, condition: { version: component.version, sha256: component.sha256, baseline: runtime.lockSha256 }, targetVersion: version(expected[0].version) }, Boolean(runtime.lockSha256));
          }
          if (runtime.fleetState === 'drifted') add('STACK_DRIFT', 'Installed Stack files differ from the installed release baseline. Inspect component evidence before planning a repair.', {});
        } else {
          const result = await session.call(name, { site_id_or_domain: session.siteId });
          // Inventory may contain private addresses, credentials or arbitrary plugin
          // data. Persist only counts, never whole MainWP payloads.
          const collection = result[check] ?? result.items ?? result.themes ?? result.changes;
          evidence.push({ source: `mainwp:${check}`, observedAt: new Date().toISOString(),
            records: Array.isArray(collection) ? collection.length : null,
            note: 'Capability returned successfully; this alone does not establish a clean site.' });
          coverage.push({ check, status: 'queried', interpretation: 'Inventory only; not a comprehensive scan.' });
        }
      } catch (error) {
        if (fatal(error)) throw error;
        if (check === 'stack' && error.code === 'STACK_REPORT_UNAVAILABLE' && await session.supports(toolNames.qualify)) {
          try {
            const qualification = qualificationEvidence(await session.qualification());
            evidence.push({ source: 'mainwp:stack-qualification', observedAt: new Date().toISOString(), ...qualification });
            coverage.push({ check, status: 'qualification_required', ...safeError(error) });
            add('STACK_QUALIFICATION', 'The child site returned no Stack runtime report. Read-only qualification details are recorded; a verified release baseline is still required. No site change was made.', {});
            continue;
          } catch (qualificationError) {
            if (fatal(qualificationError)) throw qualificationError;
            coverage.push({ check: 'stack_qualification', status: 'blocked', ...safeError(qualificationError) });
          }
        }
        coverage.push({ check, status: 'blocked', ...safeError(error) });
      }
    }
  } else coverage.push({ check: 'management', status: 'unavailable', reason: 'Dedicated environment route recorded. Public inspection is available; a qualified site workflow must be provisioned for deeper access.' });
  try {
    const measured = await publicProbe(target.url, authorize);
    evidence.push({ source: 'public:sample', ...measured });
    coverage.push({ check: 'public_performance', status: 'checked', scope: 'One bounded GET sample; no Core Web Vitals or historical root-cause conclusion.' });
    if (measured.status !== 200) add('PUBLIC_HTTP', `The inspected URL returned HTTP ${measured.status}.`, { condition: { status: measured.status } });
    if (measured.ttfbMs > 1500) add('SLOW_SAMPLE', `The observed first-byte time was ${measured.ttfbMs} ms. This sample does not establish the cause or prove a change since yesterday.`, {});
    if (measured.titlePresent === false) add('TITLE_MISSING', 'No nonempty HTML title was detected in the sampled response.', {});
    if (measured.langPresent === false) add('LANG_MISSING', 'No HTML language attribute was detected in the sampled response.', {});
    if (measured.noindex && target.environment === 'production') add('NOINDEX_DETECTED', 'A noindex directive was detected in the sampled production page. Confirm page intent before changing it.', {});
    const before = previous?.evidence?.find(e => e.source === 'public:sample');
    if (before && before.status === measured.status && before.sourceUrl === measured.sourceUrl) evidence.push({ source: 'public:comparison', previousInspection: previous.id,
      previousMeasuredAt: before.measuredAt, currentMeasuredAt: measured.measuredAt, ttfbDeltaMs: measured.ttfbMs - before.ttfbMs,
      interpretation: 'Two individual samples; not comparable daily aggregates and not proof of causation.' });
  } catch (error) {
    if (fatal(error)) throw error;
    coverage.push({ check: 'public_performance', status: 'blocked', ...safeError(error) });
  }
  try {
    const measured = await publicProbe(`${target.url}/wp-json/`, authorize, { checkRest: true });
    const healthy = measured.status === 200 && measured.restHealthy === true;
    evidence.push({ source: 'public:wordpress-rest', sourceUrl: measured.sourceUrl, measuredAt: measured.measuredAt,
      status: measured.status, restHealthy: healthy });
    coverage.push({ check: 'wordpress_rest', status: healthy ? 'checked' : 'unhealthy',
      scope: 'One public GET of /wp-json/ checks HTTP 200 and WordPress REST-root JSON structure. It does not verify every route or authenticated API access.' });
    if (!healthy) add('REST_ROOT_CHECK_FAILED', 'The public WordPress REST-root check did not return HTTP 200 with the expected JSON structure. Access policy or endpoint configuration may explain this; the cause and other REST routes remain unverified.', { condition: { status: measured.status } });
  } catch (error) {
    if (fatal(error)) throw error;
    coverage.push({ check: 'wordpress_rest', status: 'blocked', ...safeError(error) });
  }
  for (const check of ['accessibility_axe', 'functional_browser', 'broken_assets', 'forms_validation', 'forms_submission', 'forms_delivery', 'technical_seo_full']) coverage.push({ check, status: 'not_run', reason: check.startsWith('forms_') ? 'Requires a recorded form procedure and approved destinations. No submission or external effect was initiated.' : 'Requires the configured site-specific QA workflow.' });
  return { evidence, coverage, findings, explanation: findings.length
    ? 'The recorded findings are supported by the checks listed below. Unavailable and unrun checks remain unresolved.'
    : 'No finding was confirmed by the checks that ran. This is not a full clean bill of health.' };
}
