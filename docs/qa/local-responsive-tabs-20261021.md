# Local responsive homepage Tabs — October 21

The unpublished local Stack candidate now has an optional mobile accordion and
native frame fields for Tabs, nested Split columns, Body Text, Stats and Image
Content. The converter is **20261021.5**. Spacing values remain native ACF fields
and row presets; no child-theme CSS patch or global settings change was made.

[Full homepage candidate](https://mrnwebdesigns-stack-migration.localhost/stack-local-tabs-preview-20261021/tabs.html)
replaces only the saved Tabs row in compiled HTML and inlines candidate parent CSS
and JS. It retains the real surrounding homepage. This is a proof page, not an
installed parent or a conversion of authored home page 12.

## Behavior and geometry

- Each of four panels has one content copy. Native details provides the fallback;
  supported browsers enhance it to desktop tabs above the selected breakpoint.
- At 767px and below, the homepage uses one open accordion panel. Enter/Space
  activate summaries. Desktop arrows/Home/End activate tabs. Selection survives
  breakpoint changes and focus moves to the corresponding visible control.
- Saved values include the 1230px content box, 30/15px gutters, 30px column gap,
  360px image height and responsive typography/pill fields. The source's four
  panels, 15 tags, rich text, media references and complete action values survive.
- A real mobile check caught native cover images stretching to 1500px. The
  explicit native height now produces 360px in all four checked mobile panels.
  Duplicate default header/panel gaps were removed inside the opt-in frame.
- Unselected fields preserve ordinary Tabs and existing row geometry. Responsive
  mode uses instant switching without overwriting a saved animation choice.

## Qualification

- **74** responsive-frame assertions; **307** existing native assertions and
  **106** adjacent FAQ/tags/Cards assertions: **487** native checks total.
- **56** exact saved-home/source-refusal/geometry persistence assertions.
- Installed and candidate converter suites: **32 groups / 49 fixtures removed**.
- **24** standalone PHP scripts; Node syntax check and unsupported-matchMedia
  fallback regression pass. The standalone renderer regression now passes with
  optional helper availability, as ordinary Tabs did before this change.
- Browser: all four desktop/mobile panels, desktop navigation keys, mobile
  Enter/Space, 767/768px focus transfer and 991px columns; no horizontal overflow
  observed at checked widths. Native fallback markup has unique IDs, four
  disclosures and exactly one initially open panel. No JavaScript-disabled
  browser emulation or actual editor-save acceptance is claimed.
- Full saved homepage and compiled candidate WCAG 2.1 A/AA smoke: **2 passed**.
- Static staged-snapshot QA: PHP lint/security/WordPress/compatibility and shared
  Semgrep pass. PHPStan remains advisory: missing shared WP-CLI stub path and
  61 site symbol/type notices. Site Semgrep is not configured.
- REST health: `/wp-json/` returned **200**.
- Timing is **not fully qualified**: candidate LCP 712ms / INP 40ms are good,
  **CLS 0.1261 needs improvement**. Saved homepage LCP 3928ms needs improvement;
  its CLS 0.0051 and INP 32ms are good. These are local smoke samples, not release
  clearance.

## Remaining migration work

The audit now explicitly records `case-study-tab-action-presentation`: native
controls for muted inactive logos, decorated heading actions and the responsive
pointer-follow image action remain absent. The former responsive accordion/frame
schema gap is implemented in this candidate. Full comparison of every panel,
layout-shift diagnosis, local adoption and actual Classic Editor save/reload
qualification remain required before authored home conversion.

Current candidate inventory: **298 rows / 63 records** — **174** mapped data,
**104** converter-work rows, **1** known Stack presentation gap, **15** presentation
reviews, **3** mixed-native reconciliation rows and **1** retained converted source.
Zero owner content decisions remain. All **63 complete-record plans still refuse
writes**. These row counts do not certify visual/editor parity.

All 63 indexed source/target snapshots match the fresh pre-pass baseline exactly.
The owner's Aphasia/Elixian row removals and image-free SEO cards remain preserved.
Page **11578** is still the only converted authored record. Managed parent 1.6.9,
RBL 0.2.2 and Config Helper 0.1.74 remain unchanged; source remains unpublished,
uncommitted local work in the site and shared Stack task worktrees. No push,
PR update, Fleet promotion, remote install or production write occurred. The
remote fleet Config Helper parity/site-owner readiness audit was intentionally
not run for this local-only source qualification.

Evidence in the adjacent directory contains sanitized qualification receipts,
not private SQL or content/schema snapshots. Historical October 21 checkpoints
remain historical; fresh plans bind the updated converter and renderer hashes.
