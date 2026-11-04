# UX-042 — Stable Actions first paint

Installed for visual review on `admin-editor-pilot.mrndev.io`, 4 November 2026.
Source commit: `5eb079d95e39c450de7bbdfe2a4c82e41d93311a`.

## Cause and correction

All Pages painted the native inline action links before the footer script replaced
them with Actions. UX-038 reserved row height but could not prevent this visible
presentation change. The existing component now renders a usable native disclosure
through the WordPress post/page row-action filters on eligible lists. The shared
enhancer adopts its original action nodes without changing the control footprint.
An early-opened menu and its focus survive enhancement.

Changed components/files:

- `mrn-admin-ui-css/modern-admin.php`: scoped native disclosure renderer; preserves
  original action HTML, class lists, handlers, URLs and nonces.
- `mrn-admin-ui-css/modern-admin.js`: adopts the first-paint disclosure and preserves
  open state/focus; continues using the existing shared Popover and row observer.
- `mrn-admin-ui-css/row-actions.css`: common initial/enhanced geometry and theme
  states; usable no-script disclosure styling.

No additional requests, observers, timers, settings, frameworks or page-hiding
mechanism. Existing screen allowlist and owner disclosures remain respected.

## Verification

The browser harness deliberately holds `modern-admin.js`, measures the initial
control, releases the script and measures again. Installed results:

| Metric | Light | Dark |
| --- | ---: | ---: |
| Row height change | 0 px | 0 px |
| Row top change | 0 px | 0 px |
| Actions width change | 0 px | 0 px |
| Actions height change | 0 px | 0 px |
| Actions top change | 0 px | 0 px |

The previous implementation preserved row height but changed control width by
248.859 px, height by 7 px and top position by 7 px. An initial candidate test
caught a 6 px enhancement mismatch; matching native button styling corrected it
before installation.

All 16 installed grouped checks pass in [installed.json](installed.json): native
links/nonces/handlers; Quick Edit open/cancel; keyboard opening and Escape/focus;
scoped axe on Actions; Light/Dark; no JavaScript, blocked script and unavailable
Popover fallbacks; Posts, representative CPT and Media regression; 390 px bounds;
native REST health. Additional [interaction evidence](installed-interaction.json)
confirms early-open/focus preservation, exactly-once row replacement enhancement,
and served JavaScript/CSS equality to source. PHP hashes were verified by the
installer. No content was saved, deleted, duplicated or published.

MRN source commit gate passed, proof
`481018bb6d17b365da13d9f5c2e488d236305917`; PHP lint, JavaScript syntax and diff
checks passed. [Source QA log](source-qa.txt). PHPStan's existing missing WP-CLI
stub advisory remains. Static API coverage is not applicable to these changed
handlers; no REST, AJAX or admin-post endpoint was added. Runtime `/wp-json/`
returned HTTP 200. Commit-gate browser/accessibility/performance rows are skipped
by design and supplemented by the targeted browser tests above.

Whole-page accessibility, broader compatibility, matched editor CPU qualification,
Local Hub OOM, release parity and Fleet qualification were not run or cleared.
The geometry test establishes stable Actions first paint, not overall page-load
or CPU acceptance. Other eligible table types retain their existing initialization.

## Installation and recovery

The exact review host passed the owner/write-path preflight. A fresh labeled
database-only Updraft backup was verified remotely immediately before the three
file writes. See [install receipt](install-receipt.json) for hashes, backup receipt
and rollback directory. Files were installed atomically with rollback copies.
Only the dedicated review host was changed; no release or Fleet promotion.

## Captures and reproducibility

- [Codex All Pages after refresh](codex-pages.png)
- Light: [before fix, script pending](before-light-before-js.png),
  [installed, script pending](installed-light-before-js.png),
  [installed, enhanced](installed-light-enhanced.png)
- Dark: [before fix, script pending](before-dark-before-js.png),
  [installed, script pending](installed-dark-before-js.png),
  [installed, enhanced](installed-dark-enhanced.png)
- [Installed mobile](installed-mobile.png)
- Harnesses: [qa.cjs](qa.cjs), [interaction.cjs](interaction.cjs). Run from the
  worktree with an authorized, private cookie file supplied through
  `MRN_REVIEW_AUTH_FILE`; credentials are not included in these artifacts.

Human visual acceptance remains pending. Next annotation: UX-043.
