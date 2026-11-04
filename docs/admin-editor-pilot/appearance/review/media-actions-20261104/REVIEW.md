# UX-037 — Shared Actions in Media

Request: replace Media’s older Actions disclosure with the reusable component already used by Pages and Posts. Review host only: `admin-editor-pilot.mrndev.io`.

## Change

- One initializer (`modern-admin.js`) and one shared stylesheet (`row-actions.css`) now own qualified native/MRN lists and Media Library list view.
- Media’s existing action nodes are adopted from its known MRN disclosure. Edit, Copy URL, Download, Replace media, Remove background and Delete Permanently retain their original attributes, nonces, handlers and row/form ancestry. Media Tools itself is unchanged.
- The dropdown uses semantic Light/Dark tokens, viewport-bounded native popover placement, keyboard open/close and file-specific accessible names. Destructive actions remain last behind a divider.
- Core’s saved Media view preference gates the assets. Grid and Add Media receive neither shared action asset. Media receives no broad modern-admin stylesheet. No new settings, polling, dependencies or API routes.

## Qualification

Candidate checks pass on the real 20-row Media table, with both themes, scoped axe WCAG A/AA, keyboard/focus, narrow viewport, top-layer hit testing, native Copy URL, original node/attribute integrity, dynamically inserted row enhancement, native fallback without JS/Popover, and Pages Quick Edit cancel/focus regression. Theme inspection changes only the isolated browser DOM, not the user’s saved preference.

The MRN QA Engine staged-source gate covers PHP lint, PHPCS security, WordPress coding practices, compatibility, Semgrep, secrets/debug checks and diff hygiene. PHPStan is advisory because the gate snapshot lacks a wp-cli stub. The source gate intentionally skips browser/performance/API runtime rows; the targeted authenticated browser harness supplies those checks here. No new REST/AJAX/admin-post handler is introduced; `/wp-json/` health and original action attributes are checked separately.

The shared payload is 8,993 JS bytes (3,005 gzip) and 2,904 CSS bytes (941 gzip). CSS was extracted from the existing admin stylesheet, not duplicated. Media list gains two shared asset requests; existing modern lists gain the extracted CSS request. Candidate initializer cost was 4.9 ms for 20 Media rows and 2.4 ms for 20 Page rows in one instrumented run. These bounded measurements do not qualify overall editor CPU or resolve the Local Hub OOM blocker.

No attachment was deleted, replaced or processed. No content save path changed; mutation testing was limited to browser-only row insertion and native Quick Edit cancellation. Grid/list preference verification after installation restores list mode. Broad vendor/fleet compatibility and release qualification remain out of scope.

## Deployment evidence

Source commit: `8bd54ff425ddb9b419d2a06e9b0fa707c93e23dc` on the dedicated `codex/admin-editor-pilot-20261029` branch. Exactly four runtime files installed by the dedicated site owner after a labeled database-only Updraft backup was verified at the configured remote destination. Before/after hashes and rollback path are in [install-receipt.json](install-receipt.json). The independent Media Tools repository was inspected read-only and remains clean.

Installed verification **passes**: both themes, scoped axe (zero violations), all original actions, native Copy URL, keyboard/focus, 390px viewport, existing wide-table hit testing, dynamically added row, Pages Quick Edit cancellation, no-JS/no-Popover fallback, public JS/CSS equality, list-only enqueue checks and REST-root health. See [installed-results.json](installed-results.json). The final installed measurements are 3.5 ms for 20 Media rows and 2.0 ms for 20 Page rows; these are single bounded initializer measurements, not overall page-speed claims. One initial assertion correctly detected core `wp-admin/js/common.js:2049` adding `role="button"` to `.aria-button-if-js` links after our initializer. Immediate before/after attributes are identical; the harness now separately permits that verified core accessibility initialization at document load while requiring every other attribute, original node and row/form ancestor to remain identical. No product change was needed.

The refreshed Codex Media tab shows the new shared Actions menu. Existing release, Fleet, Local Hub OOM and editor CPU gates remain open.


## Review captures

- [User before](user-before.png)
- [Light workspace](installed-light-media.png) / [Light menu](installed-light-menu.png)
- [Dark workspace](installed-dark-media.png) / [Dark menu](installed-dark-menu.png)
- [Mobile](installed-mobile-media.png) / [Codex review tab](codex-media.png)

Changed source files: `modern-admin.js`, `mrn-admin-ui-css.php`, `mrn-modern-admin.css`, new shared `row-actions.css`, and `ADMIN_UI_CONTRACT.md`. No new unresolved component issue was found in this bounded pass. Human visual acceptance remains pending. No production deployment, release publication or Fleet activation occurred.
