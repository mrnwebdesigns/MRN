# UX-047 — Appearance switches without leaving the screen

Installed only on `admin-editor-pilot.mrndev.io`, source commit `93c13211eb79`.
Awaiting visual review. Production, release promotion and Fleet remain closed.

## Cause and behavior

The appearance buttons already saved through the existing per-user AJAX handler.
However, clicking the account-menu parent still followed WordPress's Profile URL.
Both Chromium and WebKit reproduced that navigation from All Pages. Opening by
hover and clicking the theme itself stayed on All Pages in both engines.
[Before click reproduction](trigger-before.log), [before hover behavior](before.log).

In the existing appearance component, clicking or tapping the account parent now
opens its menu in place. Enter/Space opens it; ArrowDown enters the menu; Escape
closes it and restores focus. A click-open menu stays open when the pointer leaves,
and closes on a second click, an outside click or focus moving outside. Native
hover access remains available. The actual Edit Profile and Log Out links remain
native links. The existing Light/Dark/System form, per-user storage, nonce,
capability checks, failure rollback and no-JavaScript fallback are unchanged.

No editor fields, content/save handlers, AME configuration or other application
interfaces were modified. Existing server-rendered theme selection still prevents
a wrong-theme flash on the next page load.

[Installed account menu](installed-account-menu.png),
[unsaved editor retained while switching](installed-chromium-editor-dark.png),
[touch review](installed-touch-dark.png).

## Qualification

- MRN staged-source QA passed, proof `242d216f141664618cf940709a43989831a96ec2`.
  [Source gate](source-qa.log). JS syntax and diff checks also pass.
- [11 candidate checks](candidate.json) and [11 installed checks](installed.json)
  pass: Chromium/WebKit on All Pages, editor fixture 2580, Dashboard and Profile,
  plus Chromium touch at 390px. Light/Dark/System changes retain the URL; System
  follows both OS preferences; Dark persists after reload; selecting the current
  preference sends no extra save; explicit Edit Profile still navigates correctly.
- Unsaved editor title and scroll position survive theme changes. A simulated 503
  restores the prior appearance with an accessible failure message. The test title
  was discarded, and server readback confirms `Local pilot — Empty page workflow`.
  Native publishing was not invoked. Existing complex fixtures' 19/32 rows and
  content hashes are unchanged: [integrity readback](content-integrity.json).
- Scoped axe WCAG A/AA scans of the account menu in Dark return zero
  violations in both desktop engines. Keyboard, Escape/focus return and touch
  activation pass. This is scoped coverage, not whole-admin AA certification.
- [Served assets and REST readback](served-readback.json) match committed hashes;
  `/wp-json/` returns 200. The unchanged AJAX handler was manually reviewed for
  current-user ownership, nonce/capability checks and the appearance allowlist.
- [Payload change](payload.json): 397 extra gzip bytes across the existing JS/CSS
  files, no new assets, polling or observers. Fifty synchronous open/close events
  in the installed browser add no network requests; their timing is recorded in
  the readback. Each actual preference change continues to send one existing AJAX
  save. This bounded interaction check does not resolve broader CPU qualification.

The staged gate intentionally skips named-site runtime tests, supplied above by
MRN QA Engine's Playwright/axe tooling. PHP lint/API static rows are inapplicable
because no PHP changed. Whole-plugin release, broad compatibility, Fleet parity
and promotion checks are outside this review-only change. Existing CPU/OOM and
other release blockers remain open.

## Files and installation

- `mu-plugins/mrn-admin-ui-css/appearance.js`
- `mu-plugins/mrn-admin-ui-css/mrn-admin-shell.css`

Exact host, site owner, marker, writable files and before/after hashes were checked.
A fresh labeled database-only Updraft backup was verified in the configured remote
destination immediately before atomic installation. Private rollback copies remain
available: [installation receipt](install-receipt.json). QA restored the original
Light preference and removed temporary authentication JSON files. The user's open
editor was not reloaded or navigated; existing tabs receive the fix on their next
page load.
