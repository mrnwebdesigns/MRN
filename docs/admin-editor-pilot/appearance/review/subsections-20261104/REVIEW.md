# Restore collapsed subsections — 4 November 2026

**UX-032 / UX-C06**, source **8106b4fbd6a7**. Installed only on
**admin-editor-pilot.mrndev.io** under standing feedback implementation/test
approval. The owner clarified: **“Nested Cards/FAQ items start collapsed.”**

## Result

- Sticky section heading CSS, its extra scroll clearance and the associated
  focus-scroll adjustment are commented out, as requested. Main publishing and
  workspace navigation remain unchanged. The prior field bands remain.
- Existing supported nested repeater items start collapsed when their section
  is first prepared. Native ACF summary fields remain visible and editable.
  Named headers and Expand items/Collapse items use ACF's own APIs.
- Opening an item is respected on subsequent refreshes and section revisits.
  Newly appended items remain open for editing. Native validation still reveals
  the affected nested item. Table-layout repeaters retain their native layout.
- The disabled experiment stays in source for review; no feature flag, option,
  plugin or alternate form owner was introduced.

Runtime changes are limited to `mrn-editor-workspace.css` and
`section-navigator.js` in their existing components. The prior orientation
runtime harness now expects a non-sticky heading and no longer tests the
intentionally disabled scroll adjustment.

## Verification and limits

Candidate and installed checks pass on the complex review fixture's Cards and
FAQ sections. They verify initial collapsed state, Enter/Space expansion,
expanded-state retention after revisiting, group expand/collapse, static heading
position while scrolling, native `invalid_field` reveal, native row addition
remaining open, native row removal, identical original successful form values,
390px overflow and continued Page Settings access. Four scoped WCAG A/AA axe
scans pass per run in Light/Dark; no page errors.

The new-row test initially targeted a single-link group with a native maximum
of one. The corrected test uses the repeatable Cards group and passes without
changing the single-link limit. QA changes exist only in isolated browser state;
no content was saved. The validation check invokes the existing ACF lifecycle,
not a publishing request. Full save/reload and server validation were not rerun;
no storage, nonce, publishing or API path changed.

MRN source QA passes for staged tree
`64404c007e14afd7b5496eedf9aaf4a4ea98acad`: Semgrep, secret/debug checks,
diff checks and Composer audit. JavaScript syntax checks pass. PHP/API and
release/parity checks are inapplicable or outside this review patch; runtime rows
skipped by the source gate are supplied by the named-site checks above.

Performance review: the scroll geometry work is inactive. Collapse runs once
per existing supported row through the current lazy section enhancement; the
existing append event marks only the added subtree so new rows stay editable.
No new observer, scroll listener, request, dependency or global startup scan.
The two assets add **198 gzip bytes**, including retained commented code.
No new CPU benchmark was run and no general performance improvement is claimed.
Existing CPU/OOM, native save-latency and release gates remain open.

## Installed proof

The exact-owner installer checked previous/candidate file hashes, verified fresh
remote database-only Updraft backup **3a5b8c298410**, retained private rollback
copies, and atomically replaced the two assets. Installed regression passes.
A fresh Codex review tab shows section 18 with five collapsed FAQ items and
computed section-heading position `static`; existing editing tabs were preserved.
No production, release publication or Fleet work.

- [Owner before screenshot](user-before.png)
- [Candidate results](candidate-results.json), [installed results](installed-results.json)
- [Cards Light](installed-0-light-collapsed.png), [Cards Dark](installed-0-dark-collapsed.png)
- [FAQ Light](installed-17-light-collapsed.png), [FAQ Dark](installed-17-dark-collapsed.png)
- [Mobile](installed-mobile.png), [Codex review](codex-review.png)
- [Backup, rollback and hashes](install-receipt.json), [source QA](source-qa.env), [payload](payload.json)

The diagnostic `check.cjs` takes a private `MRN_QA_AUTH_FILE` and phase
`candidate` or `installed`. Human visual acceptance remains pending.
