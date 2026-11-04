# UX-035 — Contain Media drawer icons and submenus

Source `82850e991d290172e86340598204533a736dbc45` is installed on
**admin-editor-pilot.mrndev.io** for visual review. Only
`mu-plugins/mrn-admin-ui-css/mrn-admin-shell.css` changed.

## Cause and correction

The Media drawer forced icon holders to `position: static !important`, so their
absolute mask pseudo-elements sized against the whole menu item and expanded
submenu. Live WPForms measured 249 × 437.758px instead of 20 × 20px. Restoring the
holder's relative positioning contains all four named masked vendor icons.
Relevanssi's inline stylesheet forced its source image opaque; the scoped mask
rule now hides that duplicate while retaining the original glyph through its mask.

Targeted mobile QA also found the drawer could be empty below 600px: core hid
`#adminmenuwrap`. Its inner wrapper/menu now remain in normal flow inside the
open drawer. Drawer submenus override native flyout margin offsets and vendor
mobile visibility rules so focused/hovered submenus stay below their parent.
The normal sidebar, AME configuration, native links and plugin applications
retain their owners. No new JavaScript, observers, handlers or requests.

## Evidence

- MRN scoped commit gate passed (`source-qa.log`).
- Candidate and installed runs each pass ten screen/theme/viewport combinations:
  Media list at 1487px and 390px, grid, Add Media and regular Dashboard at desktop,
  each in Light/Dark. All four glyphs stay bounded in their holders; source images
  stay hidden. Expanded WPForms submenu stays below its parent.
- Keyboard Tab reaches the native All Forms link; Escape closes the Media drawer
  and returns focus to Site menu. Native link URLs are unchanged. No script errors.
- axe scans of the menu report no new findings against the baseline. Existing
  WPForms orange NEW/Addons labels fail Light contrast (2.28:1); Dark passes.
  This existing vendor-label issue is retained in the refinement backlog.
- +216 raw CSS bytes / +88 gzip bytes. Broad CPU/heap qualification, API/save tests
  and release/Fleet QA were intentionally not repeated for this CSS-only patch.
  Existing editor CPU, Local Hub OOM and save-latency gates remain open.
- Fresh database-only Updraft backup `fc0f58704010` verified remotely before the
  atomic single-file install. `deploy-receipt.json` records hashes and rollback.
- `codex-installed.png` shows the actual installed result in a fresh Media tab.
  Existing editing tabs were preserved. Human visual acceptance remains pending.

No production deployment, release publication or Fleet activation.
