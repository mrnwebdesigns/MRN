# UX-034 — Center navigation heading text and caret

Installed on **admin-editor-pilot.mrndev.io** for visual review; source
`c4cd1947e5a4bbf7d490e4345041d531ddc9ef01`.

The native AME caret and section label now center together horizontally and
vertically. AME stretched the title and applied an automatic trailing margin;
the narrowly scoped CSS removes that margin and centers the existing flex group.
No menu configuration, labels, links, native disclosure handlers or stored
preferences were changed.

## Verification

- Scoped MRN QA commit gate passed; see `source-qa.log`.
- Candidate and installed Chromium checks pass for all five headings in Light
  and Dark, expanded/collapsed keyboard states, mobile navigation and the Media
  Site menu drawer. Maximum measured centering error is under 0.01 CSS pixels.
- Scoped axe WCAG A/AA scans: zero violations in all four theme/disclosure states.
  Native links remain unchanged; no browser script exceptions in the final run.
- AME disclosure state stays in cookies in a disposable QA browser. The initial
  diagnostic incorrectly stubbed all AJAX and broke HappyFiles data loading;
  removing that test stub restored native behavior. No runtime change was needed.
- Payload: +147 raw CSS bytes / +21 gzip bytes; no added JavaScript or requests.
  A broad CPU benchmark, API/save tests and Fleet/release QA were intentionally
  not repeated for two layout-only CSS rules. Existing CPU/OOM/save qualification
  blockers remain open.
- Fresh database-only remote Updraft backup `6bda152e0537` verified before the
  single-file atomic install; installed SHA and private rollback are recorded
  in `deploy-receipt.json`.
- Fresh authenticated Codex dashboard shows the result (`codex-installed.png`).
  Existing editing tabs were not reloaded. Human visual acceptance remains open.

Changed component file: `mu-plugins/mrn-admin-ui-css/mrn-admin-shell.css`.
No release, production or Fleet deployment.
