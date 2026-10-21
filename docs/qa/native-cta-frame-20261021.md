# Optional native CTA frame — parent 1.6.9

The centered CTA migration needs editable inner gutters, content width/gaps,
paragraph/action spacing and alignment. The previous native grid and child
spacing overrides cannot reproduce that frame through row presets alone.

The opt-in Stacked Content frame stores these values in native ACF fields and
keeps row spacing/motion on the outer wrapper. Existing unselected CTAs retain
both their previous markup and presentation. New frame fields have isolated
nested keys, bounded dimensions and fixed choice allowlists. Complete content,
media, heading semantics, links and existing Site Colors remain in native fields.
No new endpoint, JavaScript or asset request is introduced.

## Corrected acceptance evidence

The initial standalone comparison used saved SEO row 9, "Did You Know?". That
row is disabled. Its standalone frame checks remain useful, but the initial
complete-page proof substituted the wrong saved CTA and is superseded.

The corrected installed proof uses **rendered SEO row 12**, "Ready to Improve
Your Visibility?". A new site-owned verifier checks the selected legacy fixture
against the actual page, then checks native visible copy and complete link
URLs/targets. It passes row 12 and refuses the earlier disabled-row-9 proof.
The complete-page preview retains the installed head-script dependencies and
rebases stylesheet asset URLs before substituting the matching rendered row.

- 39 native frame assertions and 260 other native assertions pass against both
  candidate and installed parent 1.6.9. 24 standalone PHP regressions pass.
- Classic Editor Save Draft plus explicit reload preserves all **27 native
  values** copied from rendered row 12. The authored source remains unchanged.
- Corrected complete-page MRN QA WCAG 2.1 A/AA and keyboard smoke passes without
  ignored regions/rules. All other rows retain their legacy renderer in this
  proof; this does not qualify the remaining layouts for conversion.
- Corrected preview CWV: LCP 104ms / CLS **0.1241 (needs-improvement)** / INP 8ms.
  The engine rejects poor metrics, so its pass is not an all-good CWV signoff.
  Complete-page layout shift remains open. The actual homepage and converted
  Hero route retain good local metrics after adoption.
- Native row height and heading/action geometry match the same saved legacy row
  at viewport widths 2560, 1280, 1199, 960 and 820px. Measured client widths are
  recorded separately; scrollbars are included consistently on both versions.
- The site-owned **CTA Side Padding** preset stores desktop 120px / mobile 0px.
  At measured client width 375px, the heading/body frame expands from 121px to
  313px, with no document overflow. It is an explicit native preset, separate
  from the parent feature; global defaults are unchanged.
- A fresh saved-state inventory finds **29 disabled rows across 12 records**
  among 300 saved rows / 63 records. Saved rows are not DOM visibility claims.
  Order-aware disabled/renamed metadata mapping remains required before writes.
- All 63 complete legacy/native builder metadata sets remain unchanged. Only
  the previously accepted confirmation page 11578 is converted. CTA presentation
  holds remain until individual page, visibility and performance acceptance.

Evidence: [native checks](cta-frame-20261021/cta-frame.json),
[existing native checks](cta-frame-20261021/native-regressions.json),
[PHP regressions](cta-frame-20261021/php-regressions.json),
[rendered-source equality](cta-frame-20261021/rendered-source-match.json),
[wrong-row refusal](cta-frame-20261021/source-selection-regression.json),
[editor persistence](cta-frame-20261021/editor-readback.json),
[visibility inventory](cta-frame-20261021/visibility-audit.json),
[responsive geometry](cta-frame-20261021/responsive.json),
[accessibility](cta-frame-20261021/accessibility.txt),
[CWV](cta-frame-20261021/web-vitals.ndjson).

## Publication and adoption

Feature PR 349 and automatic promotion PR 350 are merged. Published release
`2026.10.21-fleet-auto-67aeab7ebc8c` is installed and verified on the isolated
migration clone, with a fresh SQL/code backup and the normal locked updater.
This evidence correction changes root QA documentation only, outside the parent
package. Parent code/version and package payload remain unchanged. Production
has not been written.
