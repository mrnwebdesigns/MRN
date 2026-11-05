# UX-050 — Stable admin startup

Installed on **admin-editor-pilot.mrndev.io** only, source commit
`de73316444ff716135a8b114ef82c9e0f77f7d82`. Ready for owner review. No release,
production deployment, Fleet activation, security-policy change or Local Hub
infrastructure change.

## What changed

- The shared MRN settings bar now occupies its actual space using CSS sticky
  positioning from the first paint. This removes the fixed 96px/88px placeholder
  being replaced by a measured height after DOM ready. It also handles wrapped
  mobile controls without guessing their height. Config Helper's nested wrapper
  uses the same behavior and retains its native form/content gutters.
- ACF settings panels become visible after ACF's own `ready` action has arranged
  tabs and conditional fields. Only these initializing panels wait; navigation,
  the title/save bar and other content remain visible. A short status is exposed
  during initialization. No ACF action is retriggered or replaced, and fields stay
  in their original form with their original values. Geometry remains available
  to ACF. JavaScript disabled leaves native content visible; an eight-second
  failure fallback prevents a missing ACF asset from stranding the form.
- Business Information, Header/Footer, Header Utility Message, Site Configurations
  and native General Settings no longer request the list Actions JavaScript/CSS.
  Dashboard also drops the unused list script. List/taxonomy and unknown MRN
  table consumers retain the shared component. AME's decorative-separator
  accessibility cleanup moves into the existing appearance script.
- The existing General Settings tagline help now renders in the native
  description on the server. Its existing `aria-describedby` association is
  preserved. The footer no longer inserts another paragraph beneath the field
  after paint.

The scoped panel readiness boundary prevents partial ACF painting; it is not a
claim that ACF initializes faster. The toolbar and help changes eliminate actual
late layout changes. Removing unused assets reduces payload and execution work.

## Measured result

Three alternating baseline/candidate samples per screen, isolated fresh Chromium
contexts, 1487×1000, unchanged account and server. Both phases use equally buffered
document responses; candidate markup simulates the small PHP output changes and
serves the committed JS/CSS. Separate installed checks use the real streamed
response with no substitutions. CLS excludes user-initiated shifts.

| Screen | Before median CLS | Candidate median CLS | Real installed CLS |
| --- | ---: | ---: | ---: |
| Business Information | 0.04193 | 0.00000 | 0.000001 |
| General Settings | 0.01062 | 0.00042 | 0.00042 |
| Site Configurations | 0.02334 | 0.00079 | 0.00079 |
| Pages list (control) | 0.00042 | 0.00042 | 0.00042 |

[Raw paired samples](compare-before.json), [summary/ranges](performance-summary.json),
[real installed samples](compare-installed.json), [initial source-node trace](trace-business.json).
The measured remaining shifts are predominantly tiny toolbar/icon/font settling,
not the main form movement. General Settings' initial trace also identifies the
late help paragraph as the source of its 26px shift.

Qualified form pages make **two fewer asset requests**, removing about 13.7KB
uncompressed list assets ([exact payload](payload.json)). No new asset URL,
framework, polling loop or global observer was added.

CPU and memory **remain unqualified**: paired script medians varied (Business
572→520ms, General 365→550ms, Config 443→523ms, Pages 556→427ms), with overlapping
and outlier-heavy samples. Business heap medians were 27.3→32.0MB with overlapping
ranges; the other pages were approximately unchanged. These are not evidence of
an overall CPU/memory improvement. A targeted four-run CPU sample found a
**1,962ms Cloudflare challenge-script outlier in the baseline**, versus 23–55ms in
the other runs. Native WordPress block-editor/component and vendor code also
consume startup time. [CPU attribution](cpu.json). No WAF behavior or vendor
asset ownership was changed to improve the measurement.

## Review evidence

[Before startup video](before-business-startup.webm) ·
[Installed startup video](installed-business-startup.webm)

| Screen | Before | Installed |
| --- | --- | --- |
| Business Information | [before](before-business.png) | [Light](installed-business.png) / [Dark](installed-mrn-business-information-dark.png) |
| General Settings | [before](before-general.png) | [after](installed-general.png) |
| Site Configurations | [before](before-config.png) | [Light](installed-config.png) / [Dark](installed-mrn-config-helper-dark.png) |
| Pages list | [before](before-pages.png) | [after](installed-pages.png) |

Mobile: [Business Information](installed-mrn-business-information-390.png),
[Site Configurations](installed-mrn-config-helper-390.png).

## QA and integrity

- MRN task-snapshot QA passed: `9418e5801b11f971491e409a8e5540873cc94f5d`.
  [Source gate](source-qa.log): PHP lint, WordPress/security standards, static API,
  security, accessibility and performance review. JavaScript syntax and diff
  checks also passed.
- 25 candidate checks passed (24 [initial](candidate.json), one [retry](candidate-retry.json))
  and all 25 [installed checks](installed.json) passed using MRN QA Engine's
  Playwright/axe tooling. The retry was a WebKit navigation timeout, not a failed
  assertion. Coverage: Chromium/WebKit, Light/Dark, four settings screens, native
  ACF tabs and form-value digests, sticky scroll position, sidebar alignment,
  980/900/390px layouts, scoped axe WCAG A/AA, accessible original H1, native
  keyboard save dispatch and empty editor shell.
- [Installed fallbacks](installed-fallback.json) pass with JavaScript disabled
  and ACF input deliberately blocked. Navigation stays available and the form
  fails open. [Candidate fallbacks](candidate-fallback.json) also pass.
- [Served checksums / REST / complex editor](served-proof.json) pass. The actual
  server emits the ACF readiness handler in the head, omits unused list assets,
  and renders tagline help with the native description. `/wp-json/` returns 200.
- [Fixture readback](content-readback.json) confirms the 19/32-row drafts, native
  ACF and core content hashes, save guard, active child/parent themes, noindex and
  manual-backup policy are unchanged. Original account preference remains Light.
- Native save dispatch is intercepted in QA; business settings and editor content
  were not saved or republished. This is not a new backend save/reload test: save
  handlers/storage are unchanged. Existing Header/Footer collapse behavior remains
  a separately documented baseline issue.
- The source gate deliberately skips runtime rows; the named-site checks above
  provide targeted runtime, API, accessibility and performance evidence. Broad
  vendor/fleet/release qualification was not run. CPU/memory and Local Hub OOM
  remain release blockers.

## Changed source and installation

- `mu-plugins/mrn-admin-ui-css/mrn-admin-shell.css`: initial toolbar geometry and
  scoped ACF readiness presentation.
- `mu-plugins/mrn-admin-ui-css/appearance.php`: native ACF-ready subscription and
  localized status, within the existing scope/emergency switch.
- `mu-plugins/mrn-admin-ui-css/appearance.js` / `modern-admin.js`: move the existing
  separator accessibility cleanup; preserve Actions functionality on lists.
- `mu-plugins/mrn-admin-ui-css/mrn-admin-ui-css.php`: qualified asset omission.
- `stack/themes/mrn-base-stack/inc/template-functions.php`: render existing help
  in core's description rather than inserting it after load.

[Install receipt](install-receipt.json): exact host/review marker, site-owner
permissions, before/after checksums, a fresh labeled database-only Updraft backup
verified at its remote destination, atomic replacements and private rollback at
`/home/mrndev-admin-editor/review-private/before-startup-de73316444ff`.
The existing worktree/branch remains the task source. No other project was edited.
The owner's open Codex tab was not reloaded or navigated.

## Next bounded performance work

1. Attribute editor and settings CPU/heap with repeated warm and cold runs,
   recording Cloudflare execution separately; preserve security configuration.
2. Audit the enqueue owners/dependency chains for the native WordPress component
   and vendor bundles on basic settings pages before considering any dequeue.
3. Reduce remaining small toolbar/icon-font settling if it is visible in review.
4. Continue Local Hub OOM diagnosis separately. This test-host result does not
   qualify or repair the Local Hub runtime.

Visual refinements remain paused for this performance review. Test helpers here
require a private temporary `auth.json` cookie fixture (never committed); they do
not use or control the owner's browser tab.
