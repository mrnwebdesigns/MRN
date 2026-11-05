# UX-051 — Shared CPT list header

Installed on **admin-editor-pilot.mrndev.io** for visual review only. Source:
`b2f59801f4460d03742448c2159d453510dbe4ab` (screen coverage) and
`f24dd81efea2aaca76f95c3b028f845424dd8b72` (table sizing; see installation receipt for full commit).
The final mobile disclosure-spacing follow-up is `02ca9bbe`.
No release, Fleet, production, configuration or content changes.

## Result

Native content CPT lists now use the approved Careers composition: title and Add
action, status views with search, then the native bulk/filter/count toolbar.
Previously, eligibility followed the frontend/editor-shell type list and omitted
private data types. The existing public-content and MRN admin-data registries now
resolve list eligibility once per request. No new component, setting, endpoint,
JavaScript header builder or ownership contract was introduced.

Coverage includes Posts, Pages, Careers, Services, Press Releases, Team Members,
Resources, Events, Locations, Announcements and all seven reusable-content types.
Mega Menus normally redirects to its separate workspace; that owner interface is
preserved, while its native Trash list receives the shared header. ACF and SEOPress
administration remain native; source boundary checks also exclude WooCommerce,
Tutor and WPForms even when those types appear in the data registry. Taxonomy and
editor routing are unchanged.

Visual QA exposed a shared table sizing conflict: percentage title widths made
sparse lists allocate unused space to the checkbox column. The title now takes
remaining width with a readable desktop minimum; native mobile title width is
restored. The existing checkbox/drag gutter stays compact, and core's mobile
row-disclosure space is retained so long titles clear the arrow. This is shared CSS,
not a per-CPT adjustment.

## Review screenshots

| Screen | Before | Installed |
| --- | --- | --- |
| Resources | [Before](before-resource.png) | [Light](installed-layout-resource-1487-light.png) / [Dark](installed-layout-resource-1487-dark.png) |
| Careers (existing layout control) | [Before](before-job_posting.png) | [Light](installed-layout-job_posting-1487-light.png) / [Dark](installed-layout-job_posting-1487-dark.png) |
| Resources, narrow | — | [Light](installed-layout-resource-390-light.png) / [Dark](installed-layout-resource-390-dark.png) |
| Careers, narrow | — | [Light](installed-layout-job_posting-390-light.png) / [Dark](installed-layout-job_posting-390-dark.png) |

## Qualification

- MRN task-snapshot QA passed for PHP scope (`cad66802a429510ca1bc4b9a11559eadc369a6ed`)
  and CSS (`2c465235eb7fe5e2f81dde26a3ec96cb20c24a2d`). PHP lint, WordPress/security,
  static API/accessibility/performance and diff checks pass. [PHP gate](source-qa.log),
  [CSS gate](column-source-qa.log), [15 isolated scope boundaries](scope.json).
- [Installed inventory and interactions](installed.json): original title/Add URLs,
  status counts/links, filter and bulk options, record IDs and row-action URLs are
  preserved across native lists. Light/Dark scoped axe WCAG A/AA scans pass for four
  representative headers. Chromium/WebKit keyboard Actions, Escape/focus return,
  selection feedback, native GET search and responsive headers pass.
- The initial inventory had one incorrect test expectation: it assumed Mega Menus
  still used a native list. [Supplemental checks](extra.json) verify the existing
  custom-workspace redirect, its separate native Trash list, category filtering,
  Published view, REST 200, and native Actions/header fallback without JavaScript.
  The corrected reproduction script reflects that boundary; the original failed
  expectation is retained in the raw evidence rather than erased.
- The sizing correction passes all 24 [candidate](candidate-layout.json) and
  [installed](installed-layout.json) checks: Resources, Pages, Careers and reusable
  content at 1487/980/390px, both appearances. Checkbox gutter, usable title width,
  adjacency and header viewport bounds are asserted. Final screenshots use the
  actual installed CSS, without substitutions. The final mobile follow-up adds
  eight [candidate](mobile-candidate-layout.json) and eight
  [installed](mobile-installed-layout.json) checks for title/disclosure clearance
  and native row expansion. Its [source gate](mobile-source-qa.log) passes
  (`f19c2ef00353545094345fb8c25ecc667b3bb82b`).
- [Served proof](served-proof.json) verifies the stylesheet SHA-256 against source,
  server-rendered list scope and stable heading geometry after DOM ready. Sampled
  CLS: job_posting 0.00038, resource 0.00000, mrn_reusable_basic 0.00027. This is not a new CPU/heap qualification; existing
  CPU/memory and Local Hub OOM release blockers remain open. Newly covered lists
  load the existing shared assets, without adding an asset or header initializer.
- [Fixture readback](content-readback.json) preserves the 19/32-row draft content
  hashes, save guard, active themes, noindex and manual backup policy. No editor
  values, publishing handlers or storage paths changed. No backend save/reload
  test was repeated for this list-only change. Appearance was changed only in
  isolated browser DOMs; the owner's saved preference and open tab are untouched.
- Runtime rows intentionally skipped by the source snapshot gate are covered by
  the named-site checks above. Broad third-party compatibility, release/fleet QA
  and Local Hub investigation were not part of this bounded header refinement.

## Installation and ownership

Only `mu-plugins/mrn-admin-ui-css/modern-admin.php` and `mrn-modern-admin.css` were
installed. The existing component contract documents the shared native header.
Each install checked the exact host, review marker, site-owner permissions and
before hashes, created and verified a fresh labeled remote database-only Updraft
backup, then atomically replaced the scoped file with a private rollback copy.
[Scope receipt](install-receipt.json) · [CSS receipt](columns-install-receipt.json) · [Mobile receipt](mobile-install-receipt.json).

Continue annotations on the review site. Keep CPU/heap and OOM qualification as
separate release blockers; do not turn this review acceptance into Fleet rollout.
