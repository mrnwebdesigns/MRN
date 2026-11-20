# Optional native scroll-active FAQ presentation

This is unpublished migration work on `codex/local-faq-aside-migration-20261021`,
authorized in the MRN Web Designs migration chat. The parent header stays 1.6.9;
there is no release-lock, Fleet publication, merge or site deployment.

Native FAQ fields now optionally select `faq_presentation=scroll-active` and edit
the viewport activation ratio, mobile expansion, heading aside/content width,
sticky offset, responsive gaps, item spacing, heading type/tracking/color and
feature-list framing. Existing editorial FAQ controls supply question and answer
type/colors; question tracking is independently editable. Blank selection retains
ordinary behavior. All dimensions, tracking, palette colors and opaque feature
color use existing bounded validators. Existing row presets own outer spacing.

The RBL plugin is unchanged. The theme's existing reusable renderer adds scoped
attributes and opens all native details for no-script access. The existing FAQ
asset skips its ordinary animation only for this mode, uses one passive scroll
listener with requestAnimationFrame batching, and preserves focus and native
keyboard disclosures. Mobile and reduced-motion views start expanded and retain
manual toggles; reduced motion removes the sticky heading. Manual controls do not
force smooth scrolling. These interaction improvements differ from legacy div
clicks and remain visible in the site's full-page review hold.

The contained/full-width FAQ CSS accepts an optional column variable, retaining
its original one-column fallback. This prevents a higher-specificity shell rule
from overriding the selected aside. Paragraph line height, divider placement and
tracking are scoped or optional; unselected fields do not emit styles. No new
frontend dependency/request or child-theme CSS is introduced.

Qualification on the exact disposable localhost clone:

- 26 isolated PHP suites pass on both PHP 8.2.35 and 8.5.6 (52 runs).
- Sixteen isolated browser checks cover no-script output, 45% activation,
  keyboard focus/toggling, mobile, resize and reduced motion. Existing exclusive
  and independent ordinary FAQ behavior passes its regression.
- A saved native 5-service / 24-feature row is compared with its actual legacy
  source at nine widths: 18 samples, zero copy/box/type/tracking/color/state
  differences and no new overflow. Classic Editor retains the final configuration.
- The existing native 27-item FAQ/aside test passes 95 assertions, with one initial
  open answer and owned-draft cleanup. All 79 authored states, 65 routes and four
  Site Styles options remain unchanged; no authored page is converted in this step.
- MRN QA passes source/security/API, public browser smoke, REST HTTP 200,
  accessibility (owner-authorized contrast-only exclusion), timing and CWV for
  Home plus the saved-native static comparison. Home LCP 2568ms is advisory
  needs-improvement; comparison LCP 160ms, CLS .00169 and INP 8ms are good.
  A static comparison does not qualify a full native WordPress page's PHP cost.
- Engine PHPStan reports 20 advisories. A full-theme scan using the repository
  configuration and existing baseline reports zero errors. No baseline is changed.
- Remote Config Helper parity and deployment/rollout/license checks are excluded
  by the local-only task scope. Existing actual-page performance failures require
  their own requalification. No broad site-release acceptance is claimed.

Reproduction: `tests/php/faq-scroll.php`, `tests/js/scroll-faq-browser.cjs`
(`MRN_PLAYWRIGHT_MODULE` may point at the QA Engine's installed Playwright),
`tests/js/exclusive-faq.cjs` and `tests/runtime/faq-aside-frame.php`. Site-owned
converter, editor fixture, responsive comparison and sanitized evidence stay in
the site migration branch. The remaining article-feed/map contracts and complete
page conversions are separate continuation work.
