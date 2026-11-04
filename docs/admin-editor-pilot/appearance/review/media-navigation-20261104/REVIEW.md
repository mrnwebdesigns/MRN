# UX-040 — Shared Media workspace navigation

The owner prefers the editor's **Site menu | Back to list** bar over the Media admin-toolbar dropdown. Media Library list/grid and Add Media now use the same shared navigation presentation beneath the WordPress toolbar. The library links back to Dashboard; Add Media links back to the library. The original AME menu opens below this bar, and the HappyFiles folder panel stays beneath it while scrolling. The shared row Actions component is unchanged.

Source commit: `253be2e25cd279f688602d735958e82dc4c9c2ca`. Installed only to `admin-editor-pilot.mrndev.io`. The fresh labeled database-only Updraft remote backup, four installed SHA-256 pairs and private rollback directory are recorded in [install-receipt.json](install-receipt.json). No AME configuration, content, media files, publishing or save-path changes.

## Changed components

- `mu-plugins/mrn-admin-ui-css/appearance.php`: render the Media bar in the native admin header, replacing the toolbar node. Native capability checks and escaped URLs/text remain.
- `mu-plugins/mrn-admin-ui-css/appearance.js`: connect the existing drawer handlers to the new control; add its enhanced button role.
- `mu-plugins/mrn-admin-ui-css/mrn-admin-shell.css`: shared workspace navigation presentation, scoped Media geometry, folder offset and mobile/fallback handling.
- `mu-plugins/mrn-admin-ui-css/mrn-editor-workspace.css`: retain the editor's publishing-toolbar offset while reusing the shared bar styles.

## Verification

MRN staged-source QA passed before installation: PHP lint, WordPress/security standards, compatibility, static API audit, Semgrep, secrets, Composer audit and whitespace checks. PHPStan remains advisory because the staged snapshot lacks the existing WP-CLI stub. See [source-qa.txt](source-qa.txt); its generated release wording represents task source proof, not release qualification.

Final candidate runtime QA passed 21 grouped checks; installed runtime QA passed 22, including exact browser-served JavaScript/CSS equality to source. [Candidate results](candidate.json) and [installed results](installed.json) cover:

- Library list/grid and Add Media in Light/Dark: bar position, return URLs, drawer opening, Escape/focus return and scoped axe WCAG A/AA checks with zero violations in the new navigation.
- Keyboard Space/Enter and outside dismissal; 390px layout and drawer placement.
- Shared list Actions, folder scrolling, native grid attachment-details open/close, native upload controls.
- Existing editor navigation and publishing-toolbar geometry on the Job fixture, without editing its fields.
- JavaScript disabled: native navigation stays available. Appearance script blocked: native anchor fallback opens the menu.

No media upload/delete/replace or content save was performed. Native grid/list navigation updates the saved Media view; testing ended in List. Full API runtime, broad accessibility/performance suites, parity, Fleet and release checks were intentionally not run for this navigation-only task. Native media dialogs were exercised; no endpoint or save handler changed. Existing editor CPU and Local Hub OOM release blockers remain open.

No new requests, dependencies, polling, observers or preference stores. Combined JavaScript/CSS payload changes by +307 raw bytes / +157 gzip bytes across these three existing assets; JavaScript alone adds 27 raw / 3 gzip bytes. This is a payload/scope review, not CPU qualification. [Payload comparison](payload.json).

### QA procedure correction

An initial backup label was rejected because it lacked the required prefix. Initial browser checks started before that rejection was read, changing the native Media view preference and restoring List; no code or content installation occurred. This was disclosed to the owner. A corrected backup was verified, and a fresh final-QA backup was explicitly verified before the complete candidate rerun. The installer independently verified a new remote backup immediately before writing the four files. The initial no-JavaScript assertion also targeted a zero-height native wrapper; it was corrected to check its visible menu link. Both final fallback checks pass.

## Visual review

[Before](before-list.png) · [Installed Light](installed-list-light.png) · [Installed Dark](installed-list-dark.png) · [Mobile menu](installed-list-mobile.png) · [Add Media](installed-add-light.png) · [Codex view](codex-media.png) · [Codex menu open](codex-media-menu.png)

A fresh signed-in Codex Media tab is left ready for annotations; existing edit tabs were preserved. Await human visual acceptance. No release publication, production deployment or Fleet activation.
