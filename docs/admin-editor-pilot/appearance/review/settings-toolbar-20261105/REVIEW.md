# UX-048 — One title and a cohesive internal settings bar

Installed only on `admin-editor-pilot.mrndev.io`, source commit `dded613f2137`.
Awaiting visual review; no production, Fleet or release promotion.

The shared settings toolbar retained legacy full borders, shadow, padding and a
small title, while native page and ACF group headings repeated that title. The
existing MRN appearance stylesheet now gives the settings bar the content editor's
semantic Light/Dark surfaces, primary/disabled controls, border treatment and
spacing. Its horizontal position follows AME's existing menu-width token, native
folded widths and the helper's desktop measurements. The bar clears the WordPress
toolbar at desktop, tablet and mobile sizes.

Business Information, Theme Header/Footer, Header Utility Message and Site
Configurations keep the toolbar as their sole visible page title. Their original
H1 remains available to screen readers. Exact, known duplicate ACF group titles
are visually suppressed while expanded; distinct section headings are retained.
The native collapse control stays beside the first tab/field, and a deliberately
collapsed panel retains its native restore heading. Title reduction fails open
when the toolbar is absent. It applies in CSS before painting, without a late
JavaScript rewrite, extra settings or another ownership contract.

Only `mu-plugins/mrn-admin-ui-css/mrn-admin-shell.css` changed. Forms, field names,
values, ACF lifecycle/conditional logic, toolbar save handlers, WordPress security
checks, AME configuration and third-party interfaces remain owned by their existing
components.

## Visual review

| Screen | Before | Installed Light | Installed Dark |
| --- | --- | --- | --- |
| Business Information | [before](before-mrn-business-information.png) | [Light](installed-mrn-business-information-light.png) | [Dark](installed-mrn-business-information-dark.png) |
| Header/Footer | [before](before-mrn-theme-header-footer.png) | [Light](installed-mrn-theme-header-footer-light.png) | [Dark](installed-mrn-theme-header-footer-dark.png) |
| Header Utility Message | [before](before-mrn-header-utility-message.png) | [Light](installed-mrn-header-utility-message-light.png) | [Dark](installed-mrn-header-utility-message-dark.png) |
| Site Configurations | [before](before-mrn-config-helper.png) | [Light](installed-mrn-config-helper-light.png) | [Dark](installed-mrn-config-helper-dark.png) |

[Business Information viewport](installed-business-review.png),
[collapsed-sidebar tablet](installed-mrn-business-information-900.png),
[mobile](installed-mrn-business-information-390.png),
[multi-tab mobile bar](installed-mrn-config-helper-390.png).

## Qualification

- MRN staged-source QA passed: `c376f5a7b8c130ff8b34efc06bcad52ff22e1e9f`.
  [Source gate](source-qa.log). Diff/whitespace check passed. PHP/API source rows
  are inapplicable because this patch changes CSS only.
- [25 candidate checks](candidate.json) and [25 installed checks](installed.json)
  pass using MRN QA Engine Playwright/axe tooling. The four supported screens were
  checked in Chromium and WebKit in both appearance modes; 980px, 900px and 390px
  checks verify bar/AME alignment, sticky clearance and visible save controls.
- ACF tabs retain their original form values (compared by digest). Business
  Information and Header Utility Message collapse/expand normally. Keyboard
  activation of the original Save control dispatches the associated native form;
  submission was intercepted, so no business/settings data was rewritten. This
  is not a new backend save/reload qualification; the backend is unchanged.
- Scoped axe WCAG A/AA scans of all four bars pass in both themes and browsers.
  The accessible H1 remains present. Core General Settings and the Classic/ACF
  editor toolbar retain their previous behavior. Missing-toolbar fallback passed.
- [Served CSS](served-readback.json) matches the committed checksum; `/wp-json/`
  returns 200. [Runtime readback](content-readback.json) confirms the existing
  complex editor fixtures and original Light preference are unchanged. Theme QA
  sets the root appearance attribute in its isolated browser, not user meta.
- The patch adds [918 gzip CSS bytes](payload.json), zero JavaScript and no asset
  URLs/imports. [Four alternating startup samples](performance.json) show median
  layout time 39.9ms before / 23.3ms installed, style recalculation 63.3ms / 50.7ms,
  and layout shift 0.046 / 0.030. These small samples do not qualify a CPU or memory
  improvement: script/task timings vary strongly and heap medians were 35.1MB /
  38.8MB. [Summary](performance-summary.json). The 187/188 request count difference
  was an opaque browser resource alongside varying Cloudflare challenge paths;
  ordinary site resource paths are unchanged. [Request comparison](request-difference.json).

The source gate deliberately skips named-site runtime rows; the checks above
supply targeted runtime, accessibility and performance coverage. Whole-plugin
release readiness, broad vendor compatibility, Fleet parity and promotion were
not run for this review-only refinement. Existing Local Hub OOM and CPU/memory
qualification gates remain open.

## Retained compatibility issue

Header/Footer's existing collapse chevron does not close its panel in the installed
baseline. The candidate behaves identically; [before/after comparison](collapse.json).
This was recorded for a separate native-control refinement rather than altering
that screen's lifecycle in a CSS task. No new collapse regression was introduced.

## Installation

The site-owner installer verified exact host, review marker, ownership, writable
path and before/after file hashes. A fresh labeled database-only Updraft backup was
verified at its configured remote destination immediately before atomic replacement.
[Receipt and private rollback location](install-receipt.json). Temporary QA cookie
files were removed. The user's open tab was not reloaded or navigated; it receives
the stylesheet on its next page load.
