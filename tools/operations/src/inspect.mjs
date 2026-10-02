import { compareVersions } from '../../../stack/scripts/mrn-fleet-update.mjs';
import { safeError } from './contracts.mjs';
import { toolNames } from './mainwp.mjs';

const slug = v => typeof v === 'string' && /^[a-z0-9_.-]{1,160}$/.test(v) ? v : 'unknown';
const version = v => typeof v === 'string' && /^[a-zA-Z0-9.+_-]{1,60}$/.test(v) ? v : 'unknown';
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
        if (['MAINWP_ACCESS', 'MAINWP_IDENTITY', 'FORBIDDEN', 'AUTH_REQUIRED', 'TARGET_MISMATCH', 'TARGET_CHANGED'].includes(error.code)) throw error;
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
  } catch (error) { coverage.push({ check: 'public_performance', status: 'blocked', ...safeError(error) }); }
  for (const check of ['accessibility_axe', 'functional_browser', 'broken_assets', 'forms_validation', 'forms_submission', 'forms_delivery', 'technical_seo_full']) coverage.push({ check, status: 'not_run', reason: check.startsWith('forms_') ? 'Requires a recorded form procedure and approved destinations. No submission or external effect was initiated.' : 'Requires the configured site-specific QA workflow.' });
  return { evidence, coverage, findings, explanation: findings.length
    ? 'The recorded findings are supported by the checks listed below. Unavailable and unrun checks remain unresolved.'
    : 'No finding was confirmed by the checks that ran. This is not a full clean bill of health.' };
}
