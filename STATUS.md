# Tharrington Smith: Site Kit consent integration candidate

Prepared September 23, 2026. **Implementation is merged and immutable packages are retained; production is unchanged.** MainWP's saved safe mode remains enabled. The one-site update requires an explicit exception, its confirmation flow, and a fresh verified labeled remote database backup. The existing live pre-consent tracking defect remains until that deployment is completed.

## Root cause and tag ownership

Clean anonymous browser traces reproduced a parser-initiated request to `googletagmanager.com/gtag/js?id=GT-WPD79HLV`, followed by GA collection before any consent choice. Consent Mode was denied (`gcs=G100`), but denied-mode requests still violate the required no-tracking-before-opt-in behavior. This happened independently of the correctly displayed Cookie Consent banner.

Fresh MainWP discovery verified Dashboard `wpcontrol.mrndev.io`, connected status and 84 abilities. Exact URL lookup resolved `https://tharringtonsmith.com/` to site 94; targeted sync was confirmed by readback at `2026-09-23T17:01:13+00:00` after the sync request timed out. No broad sync was used.

| Surface | Verified ownership and state |
| --- | --- |
| MRN Cookie Consent | Active 1.1.45, raw settings exactly `{"enabled":1}` |
| Site Kit | Active 1.160.1; Analytics 4 and PageSpeed Insights modules; `useSnippet=true`; conversion tracking enabled |
| Destinations | Google tag `GT-WPD79HLV`; GA4 `G-0DPCZE00EQ`; property `504163756`; stream `12132291507` |
| Exclusions | Site Kit excludes logged-in users from tracking |
| GTM / advertising | MRN GTM Injector absent; Site Kit Tag Manager, Ads and AdSense options absent; no advertising conversion destination found |
| Theme / other snippets | Standalone `tharrington_smith` theme; live theme, MU-plugin and custom snippet inspection found no additional tracking source |
| Optimization | SiteGround Speed Optimizer 7.8.2; dynamic cache enabled; JavaScript optimization disabled; combine/defer/delay settings absent |
| Other Google resources | Maps, reCAPTCHA and fonts are separate from the identified analytics tag; this change preserves them |

There was one observed Google tag owner, Site Kit, rather than a duplicate GTM installation. Disabling Site Kit code placement alone removes its frontend analytics and conversion providers. The chosen integration therefore keeps Site Kit's connection, destination configuration, exclusions, linker settings and original conversion provider code, and defers that original output until analytics consent. No GTM installation or new production plugin is required. Supported settings and Consent Mode alone cannot both preserve the current tracking and satisfy the stricter request requirement.

References: [Site Kit code placement](https://sitekit.withgoogle.com/documentation/using-site-kit/manage-site-kit-placed-code/) and [Site Kit Consent Mode](https://sitekit.withgoogle.com/documentation/using-site-kit/consent-mode/). The installed 1.160.1 source and actual network traces were the authority for this site's implementation.

## Reviewable implementation

- Cookie Consent release source: `/Users/khofmeyer/Development/MRN-plugins/mrn-cookie-consent`, commit `9d9af85d05bce7aabd35112ee9d9dd99af435219`; merged to plugin `main` by PR #4 as `e3e1737f1db325f3076025c341af55a1cd7318bc`.
- MRN migration and regression tests: commit `1ccd3ab59ab25738d8bbff43eaed0d3bf6406fb7` on the same named branch in this worktree.
- Plugin diff: [artifacts/plugin-reviewed.patch](/Users/khofmeyer/Development/MRN-task-artifacts/sitekit-consent-integration-20260923T181426Z/preserved-worktree/artifacts/plugin-reviewed.patch). Migration diff: [artifacts/migration-reviewed.patch](/Users/khofmeyer/Development/MRN-task-artifacts/sitekit-consent-integration-20260923T181426Z/preserved-worktree/artifacts/migration-reviewed.patch).
- Migration: [scripts/integrations/20260923-tharringtonsmith-sitekit-consent.php](scripts/integrations/20260923-tharringtonsmith-sitekit-consent.php).

Cookie Consent 1.1.46 adds the optional `sitekit_analytics_gate` setting, off by default. Its final WordPress script filter converts Site Kit's Google tag, inline configuration and conversion-provider scripts into inert markup, preserving CSP nonces. Matching tracking resource hints are removed. Consent releases the original scripts once, in dependency order. Google Signals and advertising personalization are disabled for this analytics-only integration, including when Accept All is selected.

Revocation sets the GA opt-out flag, drops subsequent gtag commands, expires applicable first-party Analytics cookies and reloads after saving the choice. Tests found that Google could otherwise flush an already-queued event on pagehide. A narrow guard suppresses the identified tracking endpoints through fetch, beacon, XHR and image transports during revocation; unrelated transports continue normally. This is not a universal blocker for arbitrary third-party code or unqualified destinations.

Unsupported configuration fails closed for these Site Kit scripts. The first supported combination uses bundled consent assets, generated optional Analytics consent, no custom JSON/language overrides/direct scripts, no MRN GTM Injector, no Google tag gateway, and Site Kit Analytics without Ads, AdSense or Tag Manager. Broader integrations require separate qualification.

The migration is idempotent and exact-target guarded. It checks the production URL, single-site status, plugin versions/activity, raw settings, destinations, exclusions and active modules, then requires a fresh labeled remote database backup before writing. Its only successful apply change is:

```json
{"before":{"enabled":1},"after":{"enabled":1,"sitekit_analytics_gate":1}}
```

Site Kit settings remain unchanged on apply, including `useSnippet=true`. The read-only live migration plan succeeded. No third-party Site Kit code, WPForms routing, layout, vendor consent assets, unrelated accessibility code, Stack lock or fleet catalog was changed. The 1.1.45 branding, diagnostic authorization and switch-name fixes remain included.

## Immutable candidate and rollback

| Artifact | SHA-256 |
| --- | --- |
| `zip/mrn-cookie-consent-1.1.46.zip` (67,706 bytes) | `1f7604ab913ef4468813270e7a4701845169725f754a4cdf7d946c3959c50275` |
| `artifacts/rollback-mrn-cookie-consent-1.1.45.zip` | `59aa760ea99d34d9b5eb084247ce1c2ec650fb9ff6cc001a7bf1972c8fa498a5` |

The final package is built deterministically from the clean release-source commit. The prior exact-runtime candidate and the final package have identical runtime PHP, JavaScript, CSS and readme files; only the packaged `README.md` gained immutable-artifact documentation. The original disposable WordPress runtime evidence therefore still covers every executable byte. [Final candidate manifest](docs/releases/sitekit-consent-1.1.46/candidate-manifest.json); [runtime checksum evidence](/Users/khofmeyer/Development/MRN-task-artifacts/sitekit-consent-integration-20260923T181426Z/preserved-worktree/evidence/artifact-runtime.json).

## Verification and limits

| Check | Result / evidence |
| --- | --- |
| Live before evidence | Homepage and Contact, desktop 1440 and mobile 390; timing and initiators in [before-browser.json](/Users/khofmeyer/Development/MRN-task-artifacts/sitekit-consent-integration-20260923T181426Z/preserved-worktree/evidence/before-browser.json) |
| Server gating / eligibility | Inert markup, default-off behavior, qualification guards, nonce preservation and resource hints passed; fixture-only Site Kit warnings from minimal test settings are retained in [server log](/Users/khofmeyer/Development/MRN-task-artifacts/sitekit-consent-integration-20260923T181426Z/preserved-worktree/evidence/sitekit-server-tests.log) |
| Real Site Kit / real Google JS | Four page/viewport cases passed no-choice, reject/reload, warmed cache, analytics-only, Accept, exactly one tag/config/pageview, saved choices, conversion API event, revocation and cookie cleanup; [final trace](/Users/khofmeyer/Development/MRN-task-artifacts/sitekit-consent-integration-20260923T181426Z/preserved-worktree/evidence/sitekit-browser-final.json) |
| Cache verification | Browser cache remained enabled via CDP; two cache hits in each final fixture case. Live SiteGround cache behavior still requires deployment verification |
| Live-page local replay | Captured production HTML, original scripts/assets and candidate gate; see [replay trace](/Users/khofmeyer/Development/MRN-task-artifacts/sitekit-consent-integration-20260923T181426Z/preserved-worktree/evidence/sitekit-page-replay-final.json). This is local replay, not live deployment proof |
| Revocation transports | String/URL/Request fetch, sendBeacon, XHR, image src and setAttribute tests passed; unrelated fetch/reCAPTCHA/necessary-cookie preservation passed; [log](/Users/khofmeyer/Development/MRN-task-artifacts/sitekit-consent-integration-20260923T181426Z/preserved-worktree/evidence/transports.log) |
| Roles / diagnostics | Anonymous, administrator, editor and subscriber tested; Site Kit logged-in exclusions preserved; only administrators receive diagnostic endpoint/nonce; [roles](/Users/khofmeyer/Development/MRN-task-artifacts/sitekit-consent-integration-20260923T181426Z/preserved-worktree/evidence/sitekit-roles.json), [WordPress regression](/Users/khofmeyer/Development/MRN-task-artifacts/sitekit-consent-integration-20260923T181426Z/preserved-worktree/evidence/wordpress-regression.json) |
| Consent controls | Desktop/mobile modal names, keyboard operation, saved choices and scoped Axe passed; earlier branding/runtime regressions passed |
| Migration | 12 cases passed, including no-op rerun, wrong site/version, settings/destination drift, absent/stale/failed backup, safe rollback and explicit prior-state acknowledgement; [log](/Users/khofmeyer/Development/MRN-task-artifacts/sitekit-consent-integration-20260923T181426Z/preserved-worktree/evidence/migration-tests.log) |
| MRN QA | Original executable candidate source and exact-package release QA report **100% SUCCESS**; runtime smoke, accessibility, API and performance rows ran. Final merged-source static/security QA passes; its strict standalone rerun correctly reports the missing disposable runtime URL rather than treating static checks as runtime proof. [Release report](/Users/khofmeyer/Development/MRN-task-artifacts/sitekit-consent-integration-20260923T181426Z/preserved-worktree/evidence/artifact-release-qa.md) |

Synthetic analytics collection was intercepted and fulfilled locally; real Google tag JavaScript ran, but test events were not delivered to the production analytics property. No live contact form was submitted.

QA qualifications are explicit. A private copy of QA Engine revision `80b652be31149cc2ecdb47e634c4c085f859baa3` isolated log paths. Its first release scan hit a transient contrast result during the existing vendor's 350ms fade-in. The second run waited 700ms before the initial Axe scan; assertions and all other checks were retained, and canonical QA Engine was unchanged. [Initial result](/Users/khofmeyer/Development/MRN-task-artifacts/sitekit-consent-integration-20260923T181426Z/preserved-worktree/evidence/artifact-release-qa-initial.md) and [adjustment record](/Users/khofmeyer/Development/MRN-task-artifacts/sitekit-consent-integration-20260923T181426Z/preserved-worktree/evidence/qa-isolation.json) are preserved. This pass measures settled controls; it does not assert that the transient animation has been remediated.

PHPStan ran in the engine's advisory mode and reported 20 symbol/signature warnings: 11 WordPress hook calls affected by pre-existing zero-argument test stubs, and nine unknown `WP_CLI` calls in the existing legacy migration. See [advisories](/Users/khofmeyer/Development/MRN-task-artifacts/sitekit-consent-integration-20260923T181426Z/preserved-worktree/evidence/artifact-phpstan-advisories.log). Semgrep had no configured rules; npm/Composer/PHPUnit checks were not applicable to the standalone plugin's missing manifests/configuration. Full Stack parity, licensing and rollout-contract checks were outside this optional-plugin scope. The report's success is not a claim that every possible tool ran.

The production HTML replay includes AccessiBe's exact localhost-only “The snipped is executed in unsupported environment” error. It was reproduced with unmodified HTML, retained in the report, and narrowly separated from candidate errors. [Baseline](/Users/khofmeyer/Development/MRN-task-artifacts/sitekit-consent-integration-20260923T181426Z/preserved-worktree/evidence/replay-baseline-errors.json). No other browser error is excluded.

The prior 1.1.45 canary's full-site report contains unrelated label/logo-link issues and homepage CLS around 0.62. Those findings were not changed here. Local fixture performance and scoped consent Axe results do not clear those full-site findings.

## Production status and required authorization

**Applied remotely in this task: no plugin, option, source, cache or backup writes.** Only targeted MainWP inventory/sync/preflight and read-only source/configuration inspection were performed. No other fleet site was touched.

The optional-plugin preflight at `2026-09-23T17:03:14+00:00` returned ready with no blockers for the exact candidate and rollback hashes: installed active 1.1.45, upgrade-only target 1.1.46, no new installation, existing UpdraftPlus/S3 available. [Preflight](/Users/khofmeyer/Development/MRN-task-artifacts/sitekit-consent-integration-20260923T181426Z/preserved-worktree/evidence/mainwp-preflight.json). This readiness result is not a backup receipt or deployment approval.

Saved MainWP `safeMode=true` remains unchanged. Its connector blocks destructive updates before the token-confirmation flow. The user explicitly required honoring safe mode and said the prior exception was not standing permission. Consequently the remaining authorization is a one-site, one-release exception for this reviewed package, migration, cache purge and safe rollback, with permission to complete MainWP's confirmation flow. [Gate evidence](/Users/khofmeyer/Development/MRN-task-artifacts/sitekit-consent-integration-20260923T181426Z/preserved-worktree/evidence/mainwp-write-gate.json). No bypass or failed update attempt was made.

## Canary sequence after approval

1. Recheck exact MainWP Dashboard identity and site URL, fresh targeted sync/readback, plugin/option/source drift and package hashes. Refresh preflight. Do not reuse an expired precondition or receipt.
2. Create a database-only UpdraftPlus backup with a unique `pre-sitekit-consent-1.1.46-tharringtonsmith-<UTC timestamp>` label using the existing remote S3 destination. MainWP's backup-start ability has no label field; use the specifically approved exact-site UpdraftPlus WP-CLI operation for that requirement, without changing storage settings or activating the inactive backup helper. Verify remote completion, database object size/checksum, task label, no file entities and no errors; obtain a fresh MainWP deployment receipt for the completed nonce. Re-backup if the receipt or migration's 30-minute window expires.
3. Use the narrowly authorized safe-mode exception and MainWP optional-plugin update with the exact package, rollback, precondition and backup receipt. Complete its two-step confirmation. Read back before retrying any timeout. Preserve the plugin's active state.
4. Run the committed migration through the authorized exact-site operation: `wp eval-file <migration.php> apply <backup-nonce>`. Never substitute an ad hoc option write. Verify raw option and all retained Site Kit values.
5. Purge SiteGround dynamic cache plus applicable WordPress/object/asset caches through the site's supported commands. Read back plugin 1.1.46, all nine file hashes, frontend asset versions/content and inert Site Kit markup in uncached and warmed responses.
6. Run the requested live homepage/Contact, desktop/mobile, cold/warm consent matrix. Capture network initiators/timestamps and choices; ensure no tracking requests/iframe before choice or rejection, correct exact-once analytics after grant, no advertising, and no post-revocation requests/cookies. Check administrator and non-admin behavior, accessible controls, diagnostics and browser errors. Avoid sending a real contact submission or contaminating analytics with synthetic conversion events.
7. Repeat the critical public checks approximately 15 minutes after deployment, including warmed cache and revocation. Record actual times and compare to immediate evidence.
8. At approximately 24 hours, fresh-sync the exact site and repeat version/configuration/cache/request/consent checks, inspect operational errors and analytics continuity, and confirm rollback remains available. This follow-up is required and must be scheduled against the actual deployment time; no post-deployment timer was started while deployment is pending.

### Rollback

Acquire a fresh labeled remote DB backup and use the same exact-site authorization and verification gates. First run the versioned migration with `rollback <backup-nonce>` while the candidate is still installed. It disables Site Kit snippet placement before removing the integration flag, restoring the consent raw option to `{"enabled":1}`. Then, if package rollback is needed, use MainWP's optional-plugin rollback to the recorded 1.1.45 ZIP. Purge caches, verify hashes/options, banner/diagnostics and no tracking requests. Site Kit's connection/reporting configuration remains intact; frontend analytics is temporarily off during this safe recovery.

An exact restoration of the original `useSnippet=true` state with 1.1.45 would reopen the proven defect. The migration's `restore-prior` mode therefore additionally requires literal `acknowledge-preconsent-tracking` after the backup nonce and a separately reviewed decision. Do not automatically use exact prior-state restoration for an incident rollback.

## Fleet and Git disposition

The versioned-artifact repair is complete: plugin `main` retains exact 1.1.43, 1.1.45 and 1.1.46 ZIPs, and the unversioned ZIP is byte-identical to 1.1.46. See [ARTIFACT-HANDOFF.md](docs/releases/sitekit-consent-1.1.46/ARTIFACT-HANDOFF.md) for checksums. The Tharrington Smith 1.1.46 canary and explicit deployment gates remain unchanged.

No fleet-readiness claim is made. Each future site needs exact inventory/destination ownership, compatible Site Kit and WordPress versions, role exclusions, conversion behavior, advertising/linked-destination review, CSP, cache/optimizer and cold/warm public verification. GTM, Ads/AdSense, AMP, tag gateway/server-side transport, custom consent configurations and other snippet owners remain separate qualification gates. Complete this canary's immediate, 15-minute and 24-hour evidence first; then review the reusable optional-plugin release and authorize the next named canary/cohort. No full Stack rollout is implied.

Plugin PR #4 is merged, the canonical plugin checkout is clean on `main`, and immutable artifacts are committed. The full review material remains in the verified local archive recorded in [source-handoff.json](docs/releases/sitekit-consent-1.1.46/source-handoff.json); raw site inventory, browser traces, screenshots, operational helpers and the isolated QA runtime stay local because MRN is public. No Stack release lock, component catalog, MainWP package or child site is promoted by this source merge. MRN PR #96 is superseded by the 1.1.46 path and must not be merged as the current Fleet release. The canary authorization gate remains pending.
