# Optional Card and nested CTA presentation — local checkpoint

Optional native Card controls expose heading widths, independent rich-intro alignment,
first-paragraph label typography, intro type/spacing, 900px item spacing, equal-height
items and a 1199px two-column / 900px one-column view. CTA controls expose body
paragraph typography, responsive bottom padding (900px default or 1199px selected),
filled parent/action alignment, button border/hover palette colors and inline icon
flow. Existing blank/default frames retain their behavior. Timeline remains separate.

Outer section spacing stays in row presets. New inner values reuse bounded length,
Site Text, palette, enum and boolean validation. No page ID, site URL, copy or site
spacing constant enters shared source. No new JavaScript, API/form handling, media,
Site Styles default, version, lock or release manifest change. The local installed
parent differs in exactly card-frame.php, cta-frame.php and style.css; originals and
hashes are journaled for rollback.

The site converter 20261104.29 maps Thank You’s two sections (three/four items), keeps
raw intro HTML/CRLF, exact wording/links/targets and the verified decorative arrow.
The second section uses two columns at 1199px; both use full-width single columns
at 900px, correcting the old leftover one-third item width. Wide desktop keeps the
source’s three/four columns. Native icon gap 8px approximates source inline whitespace
(~7.875px); button width comparison allows 0.2px. No source content is rewritten.

Verification: card presentation **6,683 assertions / 120 samples / 20 widths**, normal
and reduced motion and no-page-script output; keyboard/hover **32 assertions**;
Hero regression **1,240 / 102**; existing body boundaries **515 / 32**. Source mapping
and native-schema refusal **41** plus established row mapping **30** pass. Native
Card/CTA runtime tests pass **118/115** assertions and delete their owned drafts.
All 56 PHP suite runs (8.2.35/8.5.6) pass; configured full-theme PHPStan has zero errors.

Classic Editor Save Draft visibly succeeds on owned fixture 17682: 1,216 configured
metadata entries and identical rendered SHA-256 survive. Only 56 exactly verified
CTA spacing aliases are accepted after checking the registered zero-valued preset.
1,190 additional normal editor defaults are allowed; raw/reference/content comparisons
stay strict. Fixture 17682 is removed after runtime QA. All 79 authored states,
65 routes, four Site Styles options, 63 source records and the other 62 candidates
remain unchanged. Only Thank You’s candidate changes; authored migration stays 15/63.

Fresh MRN runtime artifact QA completes with real API, public browser, WCAG A/AA
(contrast alone excluded), keyboard/dynamic and page-timing passes. Separate real
source gates retain source qualification. This does not rerun or clear the preceding
aggregate full-theme exit 143 attempts and is not whole-Stack/release acceptance.
Web Vitals still fail: Home CLS 0.1261343; owned-body LCP 4,764ms. Diagnostic WordPress
TTFB is 17.7s on the owned body and 19.0s on existing source; static full native LCP is
684ms. Shared response-delay cause remains unconfirmed; static speed cannot qualify
the saved WordPress page. Existing full-page/performance holds remain.

Site evidence: docs/stack-migration-2026-11-04/LOCAL_CONFIRMATION_CARDS.md on the site
migration branch, including redacted MRN report, screenshots, preservation and SQL
receipt. No merge, PR, release/Fleet promotion, remote deployment or case-study-model
change. The owner’s local-only boundary defers remote parity/deploy-readiness gates.
