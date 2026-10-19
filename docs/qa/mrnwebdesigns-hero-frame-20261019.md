# Optional native Hero frame controls

Parent candidate **1.6.1**, based on merged 1.6.0. The previous migration branch
was merged in PR 323; this continuation uses `codex/migration-hero-frame-20261019`.
Older unmerged branch tips were reconciled with merged PRs or active worktrees;
none were imported, deleted or reset.

The basic Hero previously fixed content at 40rem, headings at 14ch and internal
spacing at fixed values. A complete local confirmation-page comparison measured
750px legacy content versus 640px native content and 64px versus 48px headings.
Outer row selectors could not express that frame.

The optional native controls now expose widths/gutters, content/heading/action
spacing, fluid typography, paragraph styling and action media-icon height.
Existing row margin/padding remains in its native selectors. Blank controls
retain existing markup/formatting; body Basic and Split Heroes gain no fields.
Only non-negative lengths, bounded simple clamps, valid weights and existing
Site Colors render. There is no new JavaScript, endpoint or asset dependency.

## Qualification

- **65 native ACF/render assertions**, including actual save/readback, isolated
  Hero fields, repeat normalization, complete query links, blank-value defaults
  and malformed-input rejection. Its owned draft is removed.
- **186 existing native regression assertions**, covering limits, collections,
  presentations, responsive Logos, lists, Cards, finite Tabs/Split and field
  queries. The Tabs test's two optional proof-export assertions were not enabled.
- **23 standalone PHP regressions** pass.
- Site converter candidate `20261019.1`: **25 groups / 41 cleaned fixtures**
  against both candidate and installed 1.5.24. The newer native contract retains
  widths, responsive type, internal zero spacing and existing button skins.
  The site converter is a separate site-owned migration, not a Stack dependency.
- CUA source preview at 390px: 360px content, 16px body type, no horizontal
  overflow; preserved action query. Viewport overrides are temporary.
- MRN QA Engine component WCAG 2.1 A/AA and keyboard smoke passes without
  ignored rules/regions. Local component CWV: **LCP 116ms, CLS 0, INP 8ms**.
- Full-page source rendering retains the confirmation copy, both labels/URLs,
  750px content, 64px heading, original relative positions, 36px heading gap,
  56px action margin and 15px/18px action gaps. Embedded heading colors remain
  independent of paragraph color. The legacy decorative arrow still needs a
  native Media Library reference in the site migration.

Evidence: [native integration](hero-frame-20261019/hero-frame.json),
[existing regressions](hero-frame-20261019/summary.json),
[PHP regressions](hero-frame-20261019/php-regressions.json),
[component accessibility](hero-frame-20261019/accessibility.txt),
[component performance](hero-frame-20261019/core-web-vitals.ndjson).
Source previews:
https://mrnwebdesigns-stack-migration.localhost/stack-hero-frame-preview-20261019/
and https://mrnwebdesigns-stack-migration.localhost/stack-hero-confirmation-preview-20261019/ .

## Installation and content boundary

The migration clone still runs published **1.5.24/RBL 0.2.2** at source
qualification time. Managed files were not replaced with this candidate.
Publication, installed editor acceptance and authored-page conversion remain
separate gates. No original page was switched and production was not written.

The 390px comparison also found that the original Hero has only 121px of content
width on both localhost and live: 120px desktop horizontal padding scales to
96px mobile padding through the site's default spacing. Correct this through a
new site-owned native spacing preset, preserving desktop settings and existing
global defaults. Do not add compensating child CSS or copy this mobile defect.
The exact arrow asset and this preset belong in a replayable site migration.

Task source QA and component runtime checks do not certify production readiness.
Remote parity/site-owner deployment checks are outside this local/source scope.
