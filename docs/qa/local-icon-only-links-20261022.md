# Local icon-only link qualification — October 22

The optional shared Icon Position now includes **Icon Only (Label for Screen
Readers)**. The common compact-label helper wraps the escaped label in the
existing screen-reader class only when valid icon markup exists. Without a glyph,
the label remains visible. Left/right composition is unchanged. Native library
clones receive the choice only while this theme provides its rendering contract;
unrelated client field keys are excluded. No library plugin source changed.

The icon container retains one line's height so hiding its label does not shrink
the button. CTA Frame adds an optional validated Action Media Icon Height. These
are opt-in presentation controls; section spacing remains in native row presets.
No new JavaScript, API handler, database writer or child stylesheet is added.

The guarded migration clone passes 12 native icon-only assertions, 48 existing
CTA frame assertions and 14 saved NAA CTA assertions. Native ACF/write-guard field
choices, save/readback, rendered naming, escaping, missing-icon fallback, existing
positions, new-tab safety and fixture removal are covered. The converter's
32 groups / 49 fixtures pass. All 63 indexed authored source/native states remain
exact during qualification; the later approved removal changes only Big R source
while all native states and the other 62 records remain exact. Case studies keep
their current model.

Compiled NAA CTA comparison at 1280/900px matches section/button sizes and outer
spacing. At 390px, native spacing corrects inherited 96px side padding without
overflow; the action stays 200×53.59px. Keyboard focus reaches the named PDF link.
Full-page/editor acceptance, managed adoption and site-wide CWV remain open.
Standard source/local runtime MRN QA passes with PHPStan runtime-symbol
advisories. Remote parity/publication/deployment gates are outside this local scope.

Sanitized site receipts and screenshots:
`/Users/khofmeyer/.codex/worktrees/stack-migration/mrnwebdesigns/docs/stack-migration-2026-10-22/case-cta/`.
Preview: <https://mrnwebdesigns-stack-migration.localhost/stack-case-cta-preview-20261022/naa.html>.
Prepared prior work was committed as `b1fc113`; no push or publication occurred.
