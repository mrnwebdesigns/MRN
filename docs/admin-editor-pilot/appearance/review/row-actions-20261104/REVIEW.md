# Compact native row actions — 4 November 2026

Source `74532ad7`, Admin UI 3.3.5. Standing owner authorization covers feedback
implementation, targeted QA and deployment to **admin-editor-pilot.mrndev.io**.
Production, publication and Fleet gates remain closed.

The long inline action links are replaced by a compact **Actions** dropdown on
supported native/MRN list tables. It follows the existing Media affordance, with
destructive actions separated at the bottom. Pages, Posts, Team Members,
categories, users, plugins and Trash were exercised. Existing Media menus and
plugin-owned application interfaces retain their ownership; Dashboard widget
tables are excluded.

Original action nodes, URLs/nonces, row/form ancestry and delegated WordPress
handlers remain intact. The original container uses the browser's native
[Popover top layer](https://developer.mozilla.org/en-US/docs/Web/API/Popover_API/Using),
so wide or clipped tables cannot cut off the menu. It is bounded to the viewport,
opens above the invoker when appropriate, and closes on outside click, Escape,
focus leaving, scrolling the invoker or resizing. Quick Edit cancellation/save
returns focus to the visible Actions button. Native links remain available when
JavaScript or Popover support is absent. No action endpoints or permissions change.

## Verification

Candidate checks substitute only the two assets in an isolated QA browser;
installed checks use actual deployed assets. They cover:

- Light/Dark appearance, hover background and destructive semantic color.
- Enter/Tab/ArrowDown/Escape, outside click, focus departure and focus return.
- Original action-node identity, URLs/nonces and native row/form ancestry
  in the candidate; the installed native Quick Edit route is exercised directly.
- Unchanged native Quick Edit save/reload of authorized empty draft **2580**,
  one-time enhancement of its AJAX replacement row and restored keyboard focus.
- Seven representative native list types; no destructive actions executed.
- A browser-only 2,400px table inside a 480px-wide clipped scroll container:
  the menu remains hit-testable beyond the container and closes when it scrolls.
- 390px mobile placement, resize dismissal and JavaScript-disabled fallback.
- Scoped axe WCAG A/AA: zero violations for the affected row/menu in both modes.

The MRN QA staged-source gate passed for tree
`c36a84b8050dd63ebdba9813f51f1b49bb5c7bf1`: PHP lint, PHPCS security, WordPress
standards, PHP compatibility, Semgrep, secrets, diff and Composer checks. PHPStan
retains the existing missing `wp-cli-stubs` advisory. The commit gate intentionally
skips runtime browser/accessibility/performance rows; the named-site checks above
supply targeted evidence. No REST/AJAX/admin-post endpoint changed. Broad editor,
cross-browser, parity, release and Fleet qualification were not rerun.

Added compressed payload: **2,169 bytes JS + 587 bytes CSS** in existing assets;
zero additional requests, dependencies or polling. Measured initializer time in
the final candidate run was **1.2ms Light / 1.5ms Dark** on the 20-row Pages table
(including QA instrumentation). These samples are not a general CPU qualification.
One child-list observer per native table inspects only added subtrees. Layout is
measured when opening, or when scrolling with a menu open. Existing editor CPU,
Local Hub OOM and save-latency release blockers remain open.

Installation verifies exact host/owner and before/after SHA-256 values, creates
and verifies remote Updraft DB backup **0647cea226ed**, then atomically replaces
the four files. Originals remain in the private rollback directory recorded in
the receipt. Existing filemtime asset versioning supplies cache invalidation.
Appearance preferences are restored after QA. The only content write is the
unchanged Quick Edit fixture save; no complex ACF page content was changed.

Changed component files: `modern-admin.js`, `mrn-modern-admin.css`,
`mrn-admin-ui-css.php` and `stack.lock`, all inside `mrn-admin-ui-css`.

- [Candidate results](candidate-results.json)
- [Installed results](installed-results.json)
- [Backup, rollback and installed hashes](install-receipt.json)
- [Light](installed-light-pages.png) / [Dark](installed-dark-pages.png)
- [Wide-table clipping check](installed-wide-overflow.png) / [Mobile](installed-mobile.png)

Human visual review remains open. The independent Media Tools repository was
read to reuse its interaction pattern and was not changed. Unsupported browser
versions deliberately retain native inline links. Third-party application tables
outside the existing screen allowlist are not globally rewritten.
