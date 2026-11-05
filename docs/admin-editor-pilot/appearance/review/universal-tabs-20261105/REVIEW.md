# UX-049 — Shared tab component

Installed only on `admin-editor-pilot.mrndev.io`; source commit `33759d5c20ff`.
Awaiting visual review. No release, production deployment or Fleet promotion.

ACF fields, the Content/Page settings switcher, Site Configurations, native
WordPress navigation (including Notifications), and Classic Editor Visual/Text
switches now share the existing Admin UI component's tab presentation. Each uses
14px labels, 44px minimum targets, semantic Light/Dark colors, a single active
underline, a neutral hover surface and visible keyboard focus. Rows wrap on narrow
screens. ACF vertical groups retain their orientation. Save buttons remain actions.

This consolidates duplicated selectors in `mrn-admin-shell.css`,
`mrn-modern-admin.css` and `mrn-editor-workspace.css`; the component contract now
records the same reusable treatment for future screens. There is no new plugin,
framework, setting, script, asset request or DOM construction. Existing links,
buttons, ACF conditional visibility, forms, save controls and field order keep
native ownership. Application-owned interfaces remain outside the approved screen
scope. Classic's mode switch row has a themed surface while TinyMCE's native
editing toolbar and content canvas keep their existing appearance.

## Screenshots

| Tab family | Before | Installed Light | Installed Dark |
| --- | --- | --- | --- |
| Business Information / ACF | [before](before-business-light.png) | [Light](installed-business-light.png) | [Dark](installed-business-dark.png) |
| Site Configurations | [before](before-config-light.png) | [Light](installed-config-light.png) | [Dark](installed-config-dark.png) |
| Notifications / native WP | [before](before-notifications-light.png) | [Light](installed-notifications-light.png) | [Dark](installed-notifications-dark.png) |
| Editor / ACF and workspace | [before](before-editor-light.png) | [Light](installed-editor-light.png) | [Dark](installed-editor-dark.png) |
| Classic Editor mode controls | native mode controls | [Light](installed-classic-light.png) | [Dark](installed-classic-dark.png) |

[Mobile ACF](installed-business-390.png), [mobile settings tabs](installed-config-390.png),
[mobile editor](installed-editor-390.png).

## Verification

- MRN staged-source QA passed: `867215079f5a1d29c9afe8a98d93509f389db535`. [Source gate](source-qa.log).
  Diff/whitespace checks pass. PHP/API source checks are inapplicable: no PHP,
  JavaScript, endpoints, authentication or save code changed.
- [Candidate](candidate.json) and [installed](installed.json) checks use the MRN QA
  Engine's Playwright and axe tools. Thirty candidate and thirty installed checks cover
  five tab families in Chromium and WebKit in
  both appearances; 900px and 390px checks cover wrapping and target placement.
  [Four additional installed checks](core-installed.json) cover native WordPress
  Classic content tabs in Light/Dark and both responsive sizes.
- Native ACF selection, settings navigation, Notifications links, Content/Page
  settings and initialized Classic Visual/Text switching pass. Tests initialize
  delayed TinyMCE through its native affordance and respect its current mode.
  Authored ACF/core content and settings values are compared by digest. Navigation
  bookkeeping (`mrn_active_tab` and the native referer) is excluded. No content or
  settings saves were performed; this is not a new backend save/reload qualification.
- Scoped axe WCAG A/AA scans pass for the tab controls. Keyboard tests verify the
  actual focused control and activation; WebKit may retain its native focus ring.
  The final check includes the initialized, not just delayed, Classic editor.
- [SEOPress boundary](vendor-boundary.json): 32 embedded vendor controls have the
  same computed styles before and after in Dark mode. The supported-screen
  allowlist is unchanged. Broad third-party compatibility was not rerun.
- [Installed hashes and REST health](served-readback.json),
  [runtime fixture readback](content-readback.json) and [installation receipt](install-receipt.json)
  identify the exact test target. Existing fixture content remains unchanged.
  Theme screenshots set the root appearance attribute only in an isolated QA
  context; the account's Light preference is preserved.

The [payload comparison](payload.json) records a 142-byte gzip CSS increase, zero added
JavaScript and no new asset URLs. Six alternating startup samples on Site
Configurations show median style recalculation 24.4ms before / 22.9ms candidate,
layout 21.4ms / 20.2ms, and CLS 0.0219 / 0.0234. These are small, noisy observations,
not a CPU or memory improvement claim. Median JS heap was 30.8MB / 36.9MB; existing
CPU/memory qualification and Local Hub OOM release blockers remain open.
[Raw samples](performance.json), [summary](performance-summary.json).
The before/after heap ranges overlap (27.6–35.1MB / 27.3–36.9MB); no memory
regression or improvement is qualified by these six samples. The broader memory
investigation remains a release blocker. Browser timings use final committed CSS.

Harnesses require a private native QA session and the baseline styles from
`33759d5c^` as `before-shell.css`, `before-modern.css` and `before-workspace.css`.
Authentication material is intentionally absent from these artifacts.

The staged gate deliberately skips named-site runtime rows; the targeted runtime
checks above supply that coverage. Full-plugin release/signoff, Fleet parity,
promotion and broad compatibility remain outside this review-only patch.

## Installation and next review

The site-owner workflow verifies exact host, marker, file ownership, writable
paths and previous hashes. A fresh labeled database-only Updraft backup is verified
at its configured remote destination immediately before atomic installation.
The receipt retains the private rollback path. Temporary QA authentication files
are removed after verification. The owner's open browser tab was not navigated.

Next: assess the common tab density and wrapping in the next annotation round.
The previously recorded Header/Footer collapse behavior remains a separate issue;
this change does not alter its native handler.
