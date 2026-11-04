# Compact Page Settings — 4 November 2026

UX-031 / UX-C10, source **d822de61c747**, installed only on
**admin-editor-pilot.mrndev.io** after standing owner authorization, scoped QA,
and verified remote database-only Updraft backup **5aa9f2cf62c3**.

The previous equal-height grid reserved the tallest card's height for every row.
Settings now use native CSS columns, flowing top-to-bottom and then across,
with 16px separation and one column at 700px and below. Cards stay whole, retain
their native DOM order/form and preserve optional panel visibility. No controls
or fields are reparented. Internal panel padding remains. Full-width Slug/SEO
panels retain their existing layout, separated from the columns.

On the complex fixture, the unused gap below the first card fell from **265px
to 16px**. The stylesheet is the only changed runtime file:
`mu-plugins/mrn-admin-ui-css/mrn-editor-workspace.css`.

During QA, unchanged baseline and candidate both exposed the same existing
Dark Mode contrast failure on the native Publish panel's Trash link (1.59:1).
The native footer now uses existing surface/border tokens and the Trash link
uses the existing danger token. Publishing behavior is unchanged.

## Checks

- MRN staged-source gate passes for tree
  `6f4045acbf7659c2a5af5b4fb9769d6dcf2614cc`: diff and Composer audit.
- Candidate and installed checks pass on the 19-section and empty fixtures:
  whole cards, consistent column gaps, no horizontal overflow, native panel
  collapse/expand, unchanged DOM order and form values, Content/Page Settings
  navigation and 390px single-column reflow.
- Four scoped WCAG A/AA axe scans pass per completed run: both fixtures in
  Light/Dark. No page errors. Native collapse preference writes were intercepted
  in the isolated QA browser; account settings and page content were not saved.
- Native reorder arrows are hidden in the existing editor, so arrow/drag
  reordering was not qualified by this check. Native DOM order was verified
  unchanged. No new reorder controls or owners are introduced.
- Performance review: **71 additional gzip bytes**, no JavaScript, listeners,
  observers, requests, dependencies or startup work. Only the small native
  settings-panel container uses multicolumn flow. No new timing benchmark was
  run for this bounded CSS change; no CPU/startup improvement is claimed.
- PHP/API, full save/reload and release/parity checks were not repeated because
  no PHP, storage, save, publishing or API path changed. Source QA intentionally
  skips runtime rows; the named-site harness supplies the scoped browser and
  accessibility evidence. Existing CPU/OOM, save-latency and release gates remain
  open. No production or Fleet action.

The exact-owner installer verified previous/candidate hashes, took a fresh
isolated remote backup and retained private rollback before replacing one CSS
asset. Installed checks and a fresh Codex tab confirm the result. The owner's
existing editor tab was not reloaded or saved. Human visual acceptance remains
pending another annotation round.

- [User before](user-before.png), [baseline geometry](baseline-results.json)
- [Existing contrast finding](baseline-empty-contrast.json)
- [Candidate](candidate-results.json), [installed](installed-results.json)
- [Installed Light](installed-2523-light.png), [installed Dark](installed-2523-dark.png)
- [Empty page Light](installed-2580-light.png), [empty page Dark](installed-2580-dark.png)
- [Mobile](installed-2523-mobile.png), [Codex review](codex-review.png)
- [Backup and installed hash](install-receipt.json), [source QA](source-qa.env)

The diagnostic `check.cjs` accepts a private `MRN_QA_AUTH_FILE` path and phase
`baseline`, `candidate` or `installed`. Use only the named review fixtures;
authentication material is excluded from this record.
