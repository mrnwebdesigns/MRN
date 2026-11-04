# UX-043 — Align native drag indicators and selection

Source `95fd44183318e74ea7c92076dc6012ca63a96af3`, dedicated review target
`admin-editor-pilot.mrndev.io`, 4 November 2026.

## Change

Post Types Order positioned its drag glyph below the checkbox. HappyFiles supplied
a separate below-checkbox placement on Media. The shared shell now places both
original glyphs left of their checkbox with an 8 px gap and matching centerline.
Header/footer selection controls retain native vertical alignment and line up
with row checkboxes horizontally.

- `mrn-admin-ui-css/appearance.php` adds the scope class in the initial server
  response only for supported native lists with Post Types Order, or Media with
  HappyFiles. Existing provider eligibility and native sort-disabled screens are
  respected; no configuration or capability ownership changes.
- `mrn-admin-ui-css/mrn-admin-shell.css` defines the shared gutter and positioning.
  The exact placement override wins HappyFiles' existing three-ID selector without
  overriding its application interface, glyph or drag/save handlers.

No new JavaScript, listeners, observers, polling or requests. CSS adds 304 gzip
bytes; [payload](payload.json). The initial gutter avoids waiting for Sortable's
footer initialization. Native checkbox labels, keyboard behavior, drag handlers,
nonces and persistence paths are unchanged.

## QA

MRN commit gate passed, proof `937aa84ef20a56fd67c08ab9be366fa943eb3848`.
PHP lint and diff check pass. [Source QA](source-qa.txt). PHPStan retains the
existing missing WP-CLI stub advisory. No REST/AJAX/admin-post handler changed;
the browser harness checks native REST health. Release-wide API, parity and Fleet
qualification are out of scope.

The targeted browser harness checks Posts, Pages, Team Members and Media in Light
and Dark at 1487 px and 390 px. It measures checkbox/glyph centerline and spacing,
header checkbox alignment, native pointer dragging, keyboard selection, select-all,
reloading original order, sort-disabled fallback and scoped checkbox axe.
Before the fix, Posts had a 20.2 px desktop / 30.5 px narrow centerline mismatch;
Media had 21 px / 16.5 px. [Before](before.json), [candidate](candidate.json).

An early candidate test had a request-route ordering mistake: the broad HTML
interceptor bypassed the reorder save interceptor. This temporarily saved a Posts
order. The verified pre-QA database backup proved every post's original menu_order
was zero. A fresh remote backup preceded a drift-checked restoration of only the
48 changed menu_order values; all 49 post values matched the pre-test baseline on
readback. [Restoration receipt](order-restore.json). No content field was restored
or overwritten. The corrected route uses fallback for non-document requests;
subsequent native reorder saves are intercepted and reload verifies original order.

All 27 [installed grouped checks](installed.json) pass: 16 theme/width/screen
alignment combinations have zero centerline mismatch and an 8 px gap. The
[delayed-initialization check](first-paint.json) records zero checkbox movement
and verifies the served stylesheet matches committed source. A final database
comparison of 722 existing Posts, Pages, Team Member and attachment records
found no menu_order differences from the pre-QA backup
([order verification](final-order-check.json)).
The 390 px checks resize a desktop browser; the provider's real mobile-device
sorting exclusions remain its own behavior. These bounded checks do not clear
the existing editor CPU, native-save-latency or Local Hub OOM release blockers.

## Install and review

The two-file install verifies exact review host/owner, writable paths and source
hashes. A fresh labeled database-only remote Updraft backup precedes atomic writes;
rollback copies and installed hashes are in [install receipt](install-receipt.json).
No production, release publication or Fleet activation.

The [updated control detail](installed-control-detail.png) and
[Codex Posts workspace](codex-posts.png) are ready for visual review. Light/Dark
captures use `before-*`, `candidate-*` and `installed-*` filenames.
Harnesses [qa.cjs](qa.cjs) and [first-paint.cjs](first-paint.cjs) run from the worktree
with an existing private session file supplied through `MRN_REVIEW_AUTH_FILE`.
No credentials or database dumps are in the evidence bundle.

Human visual acceptance remains pending. Next annotation: UX-044.
