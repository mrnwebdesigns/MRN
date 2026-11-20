# Local native text tags and media actions — October 21

Owner-authorized Stack work in this site migration chat remains local, uncommitted
and unpublished on `codex/local-faq-aside-migration-20261021`. No rollout, catalog,
version, lock or managed runtime was changed. Installed parent 1.6.9 remains
separate from the CLI source candidate with the same version string.

Body Text adds separate structured tags and optional heading links. Tags expose
a bounded five-item repeater, optional pill geometry and existing palette colors.
They retain repeated order, text and complete URL values. The URL text control
permits blank, `#` and site-relative destinations through ACF editor validation;
escaping refuses unsafe protocols when rendered. Tag records are exempt from the
generic repeater item-subheading injection, retaining only their semantic fields.

Image Content adds an optional action, separate accessible name, and decorative
marker with bounded internal geometry. Blank/unsafe/unnamed actions retain the
prior image markup. `_blank` actions receive safe rel values and visible keyboard
focus. Stats accepts one column while blank/unset values retain the old default.
Existing helpers/field factories own nested ACF key isolation and rendering.

Runtime proof: 26 new assertions, 307 existing native assertions, 47 FAQ/aside
assertions and 24 standalone PHP scripts pass. Site source proof verifies 46
conditions against all four real homepage panels and 15 tags, on an owned draft
which is removed. Both installed/candidate converter tests pass 32 groups and
remove all 48 fixtures. Authored content remains unchanged. Full candidate-page
WCAG 2.1 A/AA/keyboard smoke passes without ignored rules/regions; local CWV
LCP 92 ms, CLS 0.005146 and INP 16 ms are good. These qualify the local evidence,
not a release or production behavior.

**Known remaining native gap:** legacy homepage Tabs becomes a vertical accordion
at <=767 px; native Tabs keeps a tab bar. Logo sizing, nested gutters, typography
and panel spacing also differ. Browser measurements at five widths are in the
adjacent evidence. The site mapper now encodes data but explicitly blocks
authored conversion and reports `case-study-tab-responsive-frame`. Add native
responsive/frame controls and qualify all panels, complete-page rendering and
actual Classic Editor save/reload before accepting parity or adopting locally.
There is no compensating child CSS or flattened rich-text substitute.

Static staged MRN QA is a separate task snapshot gate; it does not prove local
installation, publication, promotion or deployment. Remote Config Helper parity,
Fleet and release gates are intentionally outside the owner's local-only scope.
Changed-source lint, security/WordPress sniffs, compatibility, Semgrep and diff
checks pass. PHPStan is advisory because the snapshot lacks its WP-CLI stub
path; strict type clearance is not claimed. The adjacent receipt also records
site symbol advisories and skipped rows. Local runtime REST returns HTTP 200.
