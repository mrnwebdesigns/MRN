# Long-section orientation — 4 November 2026

Source **6a54037e4f25**, dedicated branch `codex/admin-editor-pilot-20261029`.
UX-029 and UX-030 are installed only on **admin-editor-pilot.mrndev.io** under
the owner's standing feedback → QA → test deployment authorization.

## Editing experience

The native section heading stays immediately below the workspace navigation
while its fields scroll underneath. Its compact title preserves context without
introducing a second scrollbar. Sticky behavior applies to focused editing at
widths above 1000px and heights of at least 700px; short/mobile viewports and
Arrange sections retain normal scrolling. Keyboard and validation focus clear
the sticky heading, including native media buttons.

Subtle alternating field-row surfaces make long Content sections easier to scan
in Light and Dark modes. Paired fields share a band. Native tab and conditional
visibility changes recalculate the visible row sequence. Existing nested repeater
cards receive alternating surfaces. Validation error backgrounds take priority.

ACF's numbered pill is native: its number switches to the drag grip on hover.
The faint white number came from ACF's active-row colors on the MRN header. The
shared theme now supplies readable colors while retaining native behavior.
Measured active-badge contrast is **5.72:1 Light / 6.84:1 Dark**.

## Implementation and QA

Changed runtime files:

- `mu-plugins/mrn-admin-ui-css/mrn-admin-shell.css`: native badge/grip tokens.
- `mu-plugins/mrn-admin-ui-css/mrn-editor-workspace.css`: compact sticky heading,
  alternating surfaces and scroll clearance.
- `mu-plugins/mrn-editor-lockdown/assets/section-navigator.js`: coalesced field
  banding from native width/row metadata and bounded focus clearance.

The bander visits only immediate fields in the selected section, with no geometry
reads, typing hooks, scroll listener or additional observer. It reuses existing
selection/resize paths and ACF show/hide events. No field moves, lifecycle
reinitialization, plugin, dependency, configuration system or extra request.
The existing assets add **935 gzip bytes** in total.

MRN staged-source QA passes for tree
`acf5eb67afcdf902aad46e77ef414433bc5deff2`: Semgrep, secret/debug scanning,
diff checks and Composer audit. JavaScript syntax checks pass. PHP lint and API
checks are inapplicable to this CSS/JS change; the commit gate skips runtime
rows. The separate named-site harness supplies the targeted runtime coverage:

- Baseline, candidate and installed captures on the 19-section fixture.
- Both themes, paired columns, native Configs/Content tabs, nested Card section.
- Sticky geometry matches the workspace bottom; obscured input and media-button
  focus move into view; native badge-to-grip hover still works.
- 390px and short-viewport fallback; Arrange sections stays nonsticky.
- Page Settings panels and empty-page Add section controls remain reachable.
- Native form values remain identical through navigation and view changes.
- Two scoped WCAG A/AA axe scans pass, with no page errors.

Axe covers the owned header and immediate text/select fields. Existing native
TinyMCE/image accessibility debt remains outside this patch. Full save/reload,
release/parity and broad vendor testing were not repeated: no storage, save,
PHP or publishing paths changed. This is scoped review qualification, not release
signoff; existing CPU, save-latency and Local Hub OOM blockers remain open.

## Performance evidence

Eight serial loads in ABBAABBA order, four per variant, use the same fixture and
fully initialized Basic/TinyMCE section. Each warms a tab pair, measures ten
native tab switches, then a separate 24-frame scroll sample. Source QA did not
run concurrently. All samples are retained in [performance.json](performance.json).

| Browser task time | Baseline median / mean | Candidate median / mean |
| --- | ---: | ---: |
| Ten tab switches | 1006 / 1018 ms | 670 / 710 ms |
| 24-frame scroll sample | 60 / 64 ms | 51 / 73 ms |

Overall timing remains noisy. A candidate scroll sample reached 140 ms, so the
mean increased despite three samples near 49–51 ms. These results show no
consistent added slowdown and do not establish a performance improvement or
clear general CPU qualification. The instrumented band callback measured
**0.1 ms median / 0.5 ms maximum** across 68 calls. Diagnostic instrumentation
exists only in the test harness, not the installed asset. The blur shadow and
geometry-reading prototype were removed before installation.

## Installation and review

The exact-owner installer verified prior and candidate hashes, created and
verified remote database-only Updraft backup **f324a494d65c**, retained private
rollback copies, and atomically replaced only the three committed assets.
Installed regression passes. Filemtime cache versioning remains in use.
The existing clean Codex review tab shows the revised section; the user's
unsaved new-page tab was not reloaded, navigated or saved. Human visual
acceptance is pending the next annotation round. No production, release or Fleet
operation occurred.

- [User's before capture](user-before.png), [badge before](badge-before.png)
- [Baseline](baseline-results.json), [candidate](candidate-results.json), [installed](installed-results.json)
- [Light fields](installed-light-fields.png), [Dark fields](installed-dark-fields.png)
- [Light sticky heading](installed-light-scrolled.png), [Dark sticky heading](installed-dark-scrolled.png)
- [Mobile](installed-mobile.png), [Codex review](codex-review.png), [Codex scrolling](codex-scrolled.png)
- [Backup, rollback and hashes](install-receipt.json), [source QA](source-qa.env), [payload](payload.json)

The committed runtime harness is
`mu-plugins/mrn-editor-lockdown/tests/runtime/section-orientation.cjs`.
The performance harness accepts private `MRN_QA_AUTH_FILE` and
`MRN_QA_BASELINE_DIR` environment paths. Run from this worktree after the exact
review-site backup gate; never commit authentication material.
