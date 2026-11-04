# Tab border refinement — 4 November 2026

Source **1d0f6c68**, dedicated branch `codex/admin-editor-pilot-20261029`.
UX-028 is implemented on **admin-editor-pilot.mrndev.io** under the owner's
standing feedback → QA → test deployment authorization.

## Experience

Horizontal tabs now have one clear active underline, a quiet hover surface and
an independent keyboard focus ring. The overlapping tab perimeter, wrapper and
list borders are gone, including the partial-width line above the first field.
Internal field dividers and surrounding card edges remain. The shared shell
owns the treatment; workspace CSS only supplies compact spacing. Parent and
nested ACF tabs and native MRN settings tabs use the same Light/Dark tokens.
Vertical ACF tabs, Classic Editor controls and third-party application navigation
retain their existing ownership. No field, form, handler or stored content moves.

Changed runtime assets, all in `mu-plugins/mrn-admin-ui-css`:

- `mrn-admin-shell.css`: shared scoped tab appearance.
- `mrn-editor-workspace.css`: removes competing historical rules; retains density.
- `mrn-modern-admin.css`: defers tab visuals to the shared rule.

## Verification

The committed `tests/runtime/tab-borders.cjs` compares baseline, locally routed
candidate CSS and installed CSS against the authorized 19-section draft and MRN
Notifications Center. It verifies parent/nested native tab switching, keyboard
activation and focus, unchanged native form values, Content/Page Settings
navigation, restored Page Settings panels, 390px wrapping and 44px touch targets.
Eight scoped WCAG A/AA axe scans pass in each completed run, including hover/focus
and both themes. No page errors. Config Helper's three native tab groups receive
an additional installed computed-style check without settings writes.

MRN staged-source QA passes for tree
`9a11a940687c46de211bed9d867254d84f6579ff`: diff and Composer audit. PHP, API and
security-code rows are inapplicable to the CSS-only runtime change; the source
gate intentionally skips runtime rows. The named-site harness supplies browser,
accessibility and timing coverage separately. No new endpoints, dependencies,
JavaScript, polling, observers or network requests. PHP lint and full content
save testing were not repeated because no PHP, save logic or field behavior
changed; the native form-value regression passes. Release/parity, broad vendor
compatibility, Fleet and the separate Local Hub OOM/startup CPU gates remain open.

The three assets together add **148 gzip bytes**. An initial one-shot timing
comparison ran concurrently with source QA and was slower; it is not treated as
qualification. The follow-up alternates baseline/candidate CSS in the original
stylesheet positions on one fully initialized page, ABBAABBA order, four samples
per variant, ten native tab switches per sample after warm-up. Median times:

| Metric, per ten switches | Baseline | Candidate |
| --- | ---: | ---: |
| Browser task time | 2802 ms | 2750 ms |
| Script time | 998 ms | 937 ms |
| Style recalculation | 462 ms | 435 ms |
| Layout | 314 ms | 285 ms |

This bounded comparison shows no observed regression; it does not clear general
editor CPU or server/save latency qualification. [Raw samples](performance.json).

## Test installation and next review

The exact-owner installer checked previous and candidate hashes, verified fresh
remote database-only Updraft backup **8977b47749c8**, retained private rollback
copies, and atomically replaced only the three CSS assets. Filemtime cache
versioning remains in use. The installed regression passes. A fresh Codex editor
tab is ready for annotations; the user's unsaved new-page tab was preserved.
No release publication, production or Fleet work.

- [User's before capture](user-before.png)
- [Baseline](baseline-results.json), [candidate](candidate-results.json), [installed](installed-results.json)
- [Light tabs](installed-light-tabs.png) / [Dark tabs](installed-dark-tabs.png)
- [Light workspace](installed-light-workspace.png) / [Dark workspace](installed-dark-workspace.png)
- [Mobile](installed-mobile-workspace.png)
- [Light MRN tabs](installed-light-settings.png) / [Dark MRN tabs](installed-dark-settings.png)
- [Installed Config Helper groups](installed-config-tabs.json)
- [Codex review view](codex-review.png)
- [Backup, rollback and installed hashes](install-receipt.json)
- [Source QA proof](source-qa.env) / [payload comparison](payload.json)

Human visual acceptance remains pending the next annotation round.
