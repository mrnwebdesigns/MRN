# Cookie Consent and GTM Injector Local Release Candidate

## Status and scope

This package records reviewed release candidates for MRN Cookie Consent
`1.1.43` and MRN GTM Injector `1.0.14`. Their source PRs are merged and their
checksum-locked packages are registered on the Fleet review branch. No package
was published through MainWP, no MainWP configuration or child-site setting was
changed, and no development or production child site was deployed.

These plugins are optional integrations. They are removed from the universal
Stack bootstrap input and remain outside the schema-2 full Stack release.

## Root cause and evidence boundary

The shared contract defect is confirmed in source history:

- Cookie Consent changed the default `enabled` value from `0` to `1` in the
  release work that became `1.1.36`, and it applies defaults with
  `wp_parse_args()`.
- GTM Injector `1.0.11` through `1.0.13` independently reads the raw
  `mrn_silktide_consent_settings` option and treats a missing `enabled` key as
  false.
- A partial legacy option can therefore display Cookie Consent while GTM
  Injector emits its ordinary script and noscript output immediately.
- The Trilliant local option reproduces the prerequisite state: it contains
  theme-color settings but no `enabled` key. Its local GTM ID is empty, so the
  local homepage is not itself a reproduction of the production request leak.

The exact remote raw option and any production-only
`mrn_gtm_defer_until_consent` filter remain an evidence gap. The configured
MainWP MCP exposes plugin inventory but no read-only raw-option or source-filter
ability. This package therefore confirms the shared code defect and the public
leak, but does not claim that the defect is the sole site-specific cause on MRN
Web Designs or Trilliant production.

## Fresh public and inventory evidence

All public checks used cache-bypass query strings, no-cache request headers,
new browser contexts, empty storage, and blocked service workers. Network
observations were made before any consent interaction.

| Target | Fresh evidence | MainWP evidence and gap |
| --- | --- | --- |
| `https://mrnwebdesigns.com` | Banner and Consent Mode defaults are present, but ordinary `GTM-PMMPVD9` output and its noscript iframe are also present. Requests reached `googletagmanager.com`, `google-analytics.com`, and `pagead2.googlesyndication.com` before consent. | Exact URL resolved as site `85`; active Cookie Consent `1.1.37` and GTM Injector `1.0.9`. The targeted sync timed out client-side; readback showed current plugin inventory. Raw option/filter state is unavailable. |
| `https://trilliant.mrndev.io` | Banner and Consent Mode defaults are present. No ordinary GTM, deferred-loader marker, noscript iframe, or Google tracking request was emitted. This is quiet, but is not proof of functional deferral because no GTM output was configured/rendered. | Exact URL resolved as site `109`; targeted sync timed out, then readback reported `last_sync=2026-09-14T16:28:05+00:00` and active versions `1.1.42`/`1.0.13`. Raw option, filter, and GTM ID remain unavailable. |
| `https://trilliant.com` | Banner and Consent Mode defaults are present, but ordinary `GTM-5XQGZCBR` output and its noscript iframe are also present. Requests reached `googletagmanager.com` and `google-analytics.com` before consent. Both Kinsta and Cloudflare reported bypass/dynamic handling on the raw evidence request. | Exact URL resolved as site `110`; targeted sync timed out, then readback reported `last_sync=2026-09-14T16:27:33+00:00` and active versions `1.1.42`/`1.0.13`. Raw option/filter state is unavailable. |
| `https://gloves.mrndev.io` | No MRN Cookie Consent output, GTM output, noscript iframe, or Google tracking request was observed. | The exact URL is not present in the connected MainWP Dashboard. The approved management route and remote inventory remain unresolved. |
| Gloves local | Active Cookie Consent `1.1.29` and GTM Injector `1.0.11`; Cookie Consent and GTM options are absent. | Local and Dev remain separate states. Local WP-CLI also emits an unrelated PHP vendor deprecation advisory. |
| `https://www.gloves-online.com` | No MRN Cookie Consent or GTM Injector output. Direct `gtag` for `G-RTZD6VL2FD` makes pre-consent requests to Google Analytics/Ads/DoubleClick hosts. | Existing production behavior is outside this rollout and must not be changed by it. |

The MainWP connection itself passed the required safety check:
`connected=true`, `dashboardHost=wpcontrol.mrndev.io`, and 81 abilities. Each
sync request contained exactly one freshly resolved site ID. No sync was retried
blindly after its timeout.

## Trilliant reCAPTCHA finding

Trilliant production also made pre-consent requests to `www.google.com` and
`www.gstatic.com`. The active MRN reCAPTCHA Enterprise Manager configures and
auto-enables reCAPTCHA for WPForms, and the page contains WPForms form `71`.
This is a functional anti-abuse/security dependency, not a GTM or analytics
tag. It is still a third-party Google exposure and needs a separate consent,
security, and form-function review; it is intentionally unchanged here.

The existing Onavey result remains the baseline:
`https://onavey.com/scan/6949b19e-3023-467e-9f98-76e58fa9af4a`.
Rerun it only after an approved Trilliant production rollout and clean-browser
verification.

## Candidate behavior

Cookie Consent `1.1.43`:

- exposes `MRN_Silktide_Consent::get_effective_enabled_state()`;
- returns `true` or `false` only for an explicit saved `1` or `0` choice and
  returns `null` for an absent, malformed, partial, or ambiguous option;
- keeps unconfigured legacy sites disabled instead of silently enabling them;
- retains the existing translation, responsive banner, settings, branding, and
  Consent Mode behavior.

GTM Injector `1.0.14`:

- consumes the public Cookie Consent contract instead of interpreting the raw
  option;
- distinguishes `absent`, `enabled`, `disabled`, and `ambiguous` integration
  states;
- fails closed for an enabled or ambiguous active integration, suppressing both
  the ordinary GTM script and the noscript iframe;
- preserves immediate standalone GTM output when Cookie Consent is genuinely
  absent or explicitly disabled;
- lets `mrn_gtm_defer_until_consent` force deferral for absent/disabled states,
  but does not let the filter bypass enabled/ambiguous fail-closed behavior.

Paired updates must install GTM Injector first, then Cookie Consent. That order
ensures GTM `1.0.14` treats an older active Cookie Consent integration as
ambiguous and does not create an immediate-loading transition window.

## Tests and QA

Focused regression coverage passes for:

- absent/malformed/partial options and explicit `enabled=0`/`enabled=1`;
- Cookie Consent absent and integration state/filter behavior;
- ordinary script and noscript suppression;
- Consent Mode default ordering before the deferred loader;
- Accept loading GTM exactly once;
- Reject, rejected reload, and initial denied state remaining quiet;
- accepted reload and consent revocation reload behavior;
- existing translation overrides, settings precedence, branding, and narrow
  banner layout.

A no-database-write probe against the real Trilliant local WordPress runtime
temporarily loaded the candidates as active `1.1.43`/`1.0.14`. With explicit
runtime-only option filters and `GTM-TEST123`, it emitted one deferred loader,
placed Consent Mode defaults first, and emitted no ordinary GTM script or
noscript iframe. The original `1.1.42`/`1.0.13` plugin directories were then
restored and every pre-test file checksum passed.

Canonical MRN QA results:

| Candidate | Standard task QA | Strict clean-commit release QA |
| --- | --- | --- |
| Cookie Consent `1.1.43` | 100% success | 100% success; Version and Stable tag both `1.1.43` |
| GTM Injector `1.0.14` | 100% success | 100% success; Version and Stable tag both `1.0.14` |

Both release runs passed PHP lint, PHPCS security checks, WordPress best
practices, PHP `7.4-8.3` compatibility, PHPStan, secrets/debug scans, REST index
runtime health, `git diff --check`, and local Core Web Vitals. Semgrep, ESLint,
Stylelint, Composer audit, npm audit, and PHPUnit were skipped as not applicable
because their project configuration was absent. Generic browser smoke,
accessibility smoke, and PageSpeed rows were skipped by the standalone plugin
project policy; focused Playwright coverage and real-runtime checks above cover
the changed browser contract.

Fleet standard QA also finished at 100%: the optional rollout contract and
license coverage passed. The config-helper parity audit was advisory and
reported eight fleet parity/readiness warnings outside these two catalog-only
plugin changes; no full Stack promotion or parity claim is part of this phase.
No required candidate runtime check was blocked.

MainWP Operations API `0.8.2` passed all four focused controller regressions,
PHP `7.4` and `8.3` GitHub CI, the staged MRN commit gate, and merged-source
all-file MRN QA at 100%. Runtime/browser rows were not applicable because this
Dashboard-only controller has not been deployed under the current no-site-write
instruction.

Complete reports:

- [`release-evidence/2026-09-14-cookie-gtm/cookie-consent-1.1.43-standard-qa.md`](release-evidence/2026-09-14-cookie-gtm/cookie-consent-1.1.43-standard-qa.md)
- [`release-evidence/2026-09-14-cookie-gtm/cookie-consent-1.1.43-release-qa.md`](release-evidence/2026-09-14-cookie-gtm/cookie-consent-1.1.43-release-qa.md)
- [`release-evidence/2026-09-14-cookie-gtm/gtm-injector-1.0.14-standard-qa.md`](release-evidence/2026-09-14-cookie-gtm/gtm-injector-1.0.14-standard-qa.md)
- [`release-evidence/2026-09-14-cookie-gtm/gtm-injector-1.0.14-release-qa.md`](release-evidence/2026-09-14-cookie-gtm/gtm-injector-1.0.14-release-qa.md)
- [`release-evidence/2026-09-14-cookie-gtm/mainwp-operations-api-0.8.2-merged-qa.md`](release-evidence/2026-09-14-cookie-gtm/mainwp-operations-api-0.8.2-merged-qa.md)

## Immutable ZIP candidates

| Candidate | Source commit | Local path | Size | SHA-256 |
| --- | --- | --- | ---: | --- |
| Cookie Consent `1.1.43` | `1e14bd239e2e9651033be31fe36b694fa19856de` | `/Users/khofmeyer/Development/MRN-plugins/mrn-cookie-consent/zip/mrn-cookie-consent-1.1.43.zip` | 74,037 bytes | `98a6703e6e2053ab60bdadc0506e174a72b60371a0b6736a01bf2ea13d731419` |
| GTM Injector `1.0.14` | `1c8908013fa0b35581409c13502f9a98894a96a0` | `/Users/khofmeyer/Development/MRN-plugins/mrn-gtm-injector/zip/mrn-gtm-injector-1.0.14.zip` | 27,750 bytes | `e227b8a33ad8dffc1904cacbfba1c48a068e6182f204ee6f13e4a804746ce6c1` |
| MainWP Operations API `0.8.2` | `ef86b25f79cf8aafb81200eec85590699869ec35` | `/Users/khofmeyer/Development/MRN-task-worktrees/fleet-cookie-gtm/releases/plugins/mrn-mainwp-operations-api-0.8.2.zip` | 59,288 bytes | `cc130c5178129ab984e31282d4ac1314fb92625ec5e5c837444fd4da3670e9cf` |

Each ZIP has exactly one slug-matching top-level directory, contains the stated
main file and embedded version, is readable, and contains no Git metadata or
unsafe path. These are candidates, not published releases.

## Operator-held rollback artifacts

The following rollback ZIPs are local, checksum-locked operator artifacts. They
are not release-registry entries and are not published packages. They exist so
an individually approved site update can prove recovery to that site's exact
starting version before any write occurs.

| Site/version use | Source commit | Local path | Size | SHA-256 |
| --- | --- | --- | ---: | --- |
| Gloves local: Cookie Consent `1.1.29` | `b9f68abd75ebad2e4f87d5843c5ef1299ee81cdd` | `/Users/khofmeyer/Development/MRN-task-worktrees/fleet-cookie-gtm/releases/plugins/mrn-cookie-consent-1.1.29-rollback.zip` | 46,228 bytes | `68334c05e271e26ab359b4b49516641613e0aa10f039f3510d03237860889990` |
| MRN Web Designs: Cookie Consent `1.1.37` | `97f5fcf0385a124988b2ebc6cbcc8c3dcb7c03d2` | `/Users/khofmeyer/Development/MRN-task-worktrees/fleet-cookie-gtm/releases/plugins/mrn-cookie-consent-1.1.37-rollback.zip` | 67,011 bytes | `ef018e099e665664ae9f67fc134b1d930eb8f4161d520e57f74bc1ab07d300d5` |
| Trilliant: Cookie Consent `1.1.42` | `e633a797bcef802725d04e1a1e72ea709f7b5b5c` | `/Users/khofmeyer/Development/MRN-task-worktrees/fleet-cookie-gtm/releases/plugins/mrn-cookie-consent-1.1.42-rollback.zip` | 70,654 bytes | `365e427f68caa01dc82cd1ca9bc933073ff187ac0b22d67ea6e0527bb359cdb1` |
| MRN Web Designs: GTM Injector `1.0.9` | `c446175fc2a5a9a8580aa99c17e053dd0a36e413` | `/Users/khofmeyer/Development/MRN-task-worktrees/fleet-cookie-gtm/releases/plugins/mrn-gtm-injector-1.0.9-rollback.zip` | 7,000 bytes | `9af1bdfd49aa0dedd50d8c8a1029ab71580b4abca4843faedfad0321d70e3d28` |
| Gloves local: GTM Injector `1.0.11` | `86e152e4eb02669b7549b3093b2d59c5ce36b163` | `/Users/khofmeyer/Development/MRN-task-worktrees/fleet-cookie-gtm/releases/plugins/mrn-gtm-injector-1.0.11-rollback.zip` | 8,899 bytes | `f6050670e0d89656823d6765536bd85e7c301ec7df411ebe1bf40fc2a3221ce9` |
| Trilliant: GTM Injector `1.0.13` | `106035f8c7d263e9f90ec30f49431addbe99f4d2` | `/Users/khofmeyer/Development/MRN-task-worktrees/fleet-cookie-gtm/releases/plugins/mrn-gtm-injector-1.0.13-rollback.zip` | 19,944 bytes | `5a0e66e0d0c37a6814e248527bf45c9426f8df72097c7469a3537d43fc64fc8c` |

MRN Web Designs and Trilliant therefore have local rollback pairs matching the
versions observed during discovery. Gloves has a pair matching the local
runtime only; its Dev inventory and management route remain unresolved, so
those local artifacts do not make Gloves Dev ready.

## Registered optional release entries

The following are the exact objects in
`manifests/optional-plugin-releases.json`. Both source commits are merged to
their `origin/main` branches, the catalog versions are advanced, and the exact
artifacts are present at the registered canonical paths. Inclusion makes them
available to a reviewed plan; it does not authorize a site write.

```json
{
  "slug": "mrn-cookie-consent",
  "version": "1.1.43",
  "target_tier": "optional-integration",
  "current_distribution": "catalog-only",
  "source": {
    "repository": "mrnwebdesigns/mrn-cookie-consent",
    "path": "/Users/khofmeyer/Development/MRN-plugins/mrn-cookie-consent",
    "git_commit": "1e14bd239e2e9651033be31fe36b694fa19856de"
  },
  "package": {
    "path": "/Users/khofmeyer/Development/MRN-plugins/mrn-cookie-consent/zip/mrn-cookie-consent-1.1.43.zip",
    "filename": "mrn-cookie-consent.zip",
    "main_file": "mrn-cookie-consent/mrn-cookie-consent.php",
    "size_bytes": 74037,
    "sha256": "98a6703e6e2053ab60bdadc0506e174a72b60371a0b6736a01bf2ea13d731419"
  },
  "update_policy": {
    "mode": "upgrade-only",
    "preserve_active_state": true,
    "new_install": "requires-separate-owner-authorization-and-plan"
  }
}
```

```json
{
  "slug": "mrn-gtm-injector",
  "version": "1.0.14",
  "target_tier": "optional-integration",
  "current_distribution": "catalog-only",
  "source": {
    "repository": "mrnwebdesigns/mrn-gtm-injector",
    "path": "/Users/khofmeyer/Development/MRN-plugins/mrn-gtm-injector",
    "git_commit": "1c8908013fa0b35581409c13502f9a98894a96a0"
  },
  "package": {
    "path": "/Users/khofmeyer/Development/MRN-plugins/mrn-gtm-injector/zip/mrn-gtm-injector-1.0.14.zip",
    "filename": "mrn-gtm-injector-1.0.14.zip",
    "main_file": "mrn-gtm-injector/mrn-gtm-injector.php",
    "size_bytes": 27750,
    "sha256": "e227b8a33ad8dffc1904cacbfba1c48a068e6182f204ee6f13e4a804746ce6c1"
  },
  "update_policy": {
    "mode": "upgrade-only",
    "preserve_active_state": true,
    "new_install": "requires-separate-owner-authorization-and-plan"
  }
}
```

MainWP Operations API `0.8.2` source is merged at
`ef86b25f79cf8aafb81200eec85590699869ec35` and allowlists the exact Cookie
Consent, Database Retention, and GTM Injector main files. A site rollout remains
blocked until that controller package is deployed to the Dashboard and the
connected MCP schema is read back with both new choices.

## One-site rollout matrix

Every plugin/site update remains a separate operation. For each row: resolve
the exact URL again, perform a one-ID MainWP sync where available, obtain fresh
inventory, run a read-only preflight, prove the exact current-version rollback
ZIP, create and verify a labeled remote database-only Updraft backup receipt,
obtain owner confirmation, update GTM Injector first, read back and verify it,
then repeat the complete plan/backup/confirmation/update/readback sequence for
Cookie Consent. A successful QA run or source push is not a site-write
authorization.

| Order | Site | Preconditions and blockers | Post-update proof |
| ---: | --- | --- | --- |
| 1 | MRN Web Designs, `https://mrnwebdesigns.com` | Re-resolve the current MainWP ID; confirm the raw consent option and any defer filter; confirm explicit enabled state and `GTM-PMMPVD9`; prepare rollback ZIPs for `1.0.9` and `1.1.37`; controller allowlist and verified backup receipt required. | Clean storage/browser: banner and default Consent Mode first; no gated host request or noscript before consent; Reject/reload quiet; Accept loads once; revocation removes the active container. |
| 2 | Trilliant Dev, `https://trilliant.mrndev.io` | Re-resolve the current MainWP ID; confirm raw consent state and filter; confirm the intended GTM ID because the current public page emits no GTM/deferred marker; explicit settings and rollback ZIPs for `1.0.13`/`1.1.42`; controller allowlist and Dev backup receipt required. | Same lifecycle proof as canary 1, plus confirm the container is genuinely configured so silence is deferral rather than absence. |
| 3 | Trilliant production, `https://trilliant.com` | Do not proceed until Dev passes and raw option/filter state is confirmed; confirm `GTM-5XQGZCBR`; prepare `1.0.13`/`1.1.42` rollback ZIPs; controller allowlist, production confirmation, and verified backup receipt required. Keep reCAPTCHA behavior out of this change. | Same lifecycle proof, verify no GTM/GA/Ads/DoubleClick request before consent, rerun the Onavey scan, and record the result. |
| 4 | Gloves local, then `https://gloves.mrndev.io` | Local has absent settings/ID and Dev is not managed by MainWP. Establish explicit consent choices, approved GTM container, exact Dev site-owner management route, inventory, rollback readiness, and backup capability. If either plugin is absent on Dev, the upgrade-only Fleet plan cannot seed it; a separate owner-authorized installation plan is required. Gloves production is excluded. | Prove local first, then Dev with the same clean-browser lifecycle and host-level network checks. Do not infer Dev from local state. |

## Acceptance checks for each approved site

Before consent, verify all of the following with a clean browser context and
actual network capture:

- no request to `googletagmanager.com`;
- no request to `google-analytics.com`;
- no request to `googleadservices.com` or `googleads.g.doubleclick.net`;
- no GTM noscript iframe;
- the banner is available and Consent Mode defaults precede every potentially
  gated integration.

Then verify Reject, Accept, reload, and revocation as independent cases. Any
failure triggers rollback through that site's preverified artifact and a new
readback; it does not authorize moving to the next site.
