# Mobile navigation: preserve layout on first paint

## Problem and shared fix

The mobile navigation's JavaScript-disabled fallback participates in normal
document flow. Selecting drawer mode from a footer script can therefore remove
a painted menu from the flow and move the page content. On Gloves Online at
412 CSS pixels, the content moved by 380px and contributed about 0.366 CLS.

The parent theme now emits its small navigation controller inline in the head
through WordPress's existing `mrn-base-stack-mobile-navigation` handle. The
controller observes the navigation as the browser parses it and selects its
configured mode before first paint. It waits for `DOMContentLoaded` before
enhancing the complete menu and its submenus. No child header hook is required.

This trades a small amount of inline HTML for eliminating a separate controller
request and the race between first paint and footer initialization. The drawer
transition is enabled on the first deliberate opening so enhancement itself
does not animate the expanded fallback into its hidden position. It does not
change page content, design, or the configured mobile breakpoint.

## Contracts to preserve

- Read `--mrn-mobile-menu-breakpoint`; retain the supported 320–1600 range and
  the existing 1199px default.
- Keep the unenhanced navigation usable when JavaScript is disabled. If the
  expected menu markup is incomplete, remove the early active-mode attribute.
- Initialize submenu controls only after the complete document has been parsed.
- Preserve keyboard operation, Escape and focus return, focus cycling, scroll
  unlocking, and mode changes on resize.
- Preserve the script handle and support older footer placement, although
  footer placement cannot guarantee stable first paint.
- Cache/optimization layers must preserve the critical inline controller's
  head execution. Verify the emitted production HTML after deployment.

## Regression coverage

`stack/themes/mrn-base-stack/tests/playwright/mobile-navigation-first-paint.spec.js`
streams a real controller/style fixture and delays the document tail. This
makes the initial layout observable before `DOMContentLoaded` rather than
testing only the final rendered state. Nine tests cover widths below, at, and
above a custom breakpoint, desktop, menu interaction, axe WCAG A/AA checks,
JavaScript-disabled navigation, legacy placement, initial transition suppression,
and incomplete markup.

From the theme directory, with the existing Playwright and axe dependencies:

```sh
npx playwright test tests/playwright/mobile-navigation-first-paint.spec.js --workers=1
php tests/php/mobile-navigation-breakpoint.php
php tests/php/frontend-component-assets.php
```

## Initial measurement: Gloves Online, 23 September 2026

Controlled local Lighthouse 13.4.1 runs replayed the same public homepage HTML
with fresh browser contexts and unchanged public assets. The treatment replaced
only the footer controller with this implementation in the head. Three mobile
pairs alternated treatment order; desktop had one pair.

| Metric | Baseline | Fix |
| --- | ---: | ---: |
| Mobile scores, all three runs | 67, 48, 45 | 65, 63, 66 |
| Mobile median performance | 48 | 65 |
| Mobile median CLS | 0.36635 | 0.00613 |
| Mobile median FCP | 4.080 s | 4.279 s |
| Mobile median LCP | 5.388 s | 5.479 s |
| Mobile median TBT | 245.5 ms | 220.5 ms |
| Desktop performance, one pair | 94 | 94 |

The baseline did not shift in its first run. That timing dependence explains
some score instability; the repair targets the confirmed navigation movement.
The timing measurements do not establish an FCP/LCP improvement. Other blocking
assets remain and must be evaluated separately.

These are local comparisons, not post-deployment PageSpeed Insights results.
The snapshot bypasses the production document response and its TTFB, and the
local Lighthouse version differs from Google's. The initial eight browser regression tests and changed-file MRN static QA
passed. A ninth test subsequently covered initial transition suppression while
preserving user-triggered opening/closing animation.

## Release scope

This is an isolated shared-parent source fix, not a Stack promotion. The first
two-file canary was deployed to Gloves on 23 September after a remote database
backup and checksum validation. Live navigation and accessibility checks passed,
but fast-load observation identified an initial drawer transition that is now
covered by the additional guard and regression test. Gloves reports parent 1.3.3
while the source theme is 1.4.0; do not deploy the entire newer theme to test this
change. Verify the exact production source, prepare the narrow reviewed patch
and rollback files, pass the labeled remote Updraft backup gate, and verify
emitted HTML, navigation, and repeated mobile/desktop PSI after deployment.

Carry the change through the normal merged-source Stack promotion and immutable
release process before claiming it has reached the fleet. Keep subsequent
performance changes separate so their effects remain measurable.
