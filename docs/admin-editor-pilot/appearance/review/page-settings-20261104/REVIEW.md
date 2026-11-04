# Page Settings visibility recovery — 4 November 2026

Source `35b01869`, on `codex/admin-editor-pilot-20261029`. The owner reported that
Page Settings had lost its boxes. Standing approval covers implementation, QA and
deployment only to **admin-editor-pilot.mrndev.io**.

## Cause and correction

The fields and metaboxes were present. The account's `metaboxhidden_page` contained
Featured image, Page Attributes, Author, Import Layout, Revisions and SEO. Core
`wp-admin/js/postbox.js` derives its `closed-postboxes` AJAX `hidden` value from
`.postbox:hidden`. Content/Page Settings intentionally hides inactive panels,
so a native View checkbox change in Content could persist those temporary states
as user preferences. A baseline native interaction reproduced this exact payload
with the settings checkboxes still checked. The native side-sortables 250px
minimum height separately explained the blank area above Slug.

The mounted workspace now narrowly normalizes only the existing same-URL,
same-screen `closed-postboxes` request: native View checkboxes determine the
optional panels' hidden state. Core still sends the request, nonce, closed-panel
state and callback. IDs without a checkbox retain the original core treatment.
There is no endpoint, request, persistent state store or publishing change.
The workspace's sidebar minimum height is zero; visible native panels keep
their natural size.

A replayable, exact-host/account CLI recovery restores only the five reported
settings panels for `mrn-admin`, preserving the unrelated Revisions preference.
It refuses an unexpected host, user or preference type, verifies its readback,
and is safe to rerun. No page content or other account was changed by recovery.

## Verification

- Baseline: reproduced unwanted hidden IDs for checked panels while in Content.
- Candidate/installed: only explicit unchecked choices enter the optional-panel
  hidden payload; the original nonce, screen and closed state are preserved.
- New page and authorized 19-section draft: native form values remain identical
  across View and workspace changes. No ACF field is moved or reinitialized.
- Light/Dark: scoped WCAG A/AA axe scans on native settings panels and workspace
  controls; 390px single-column layout and restored-panel screenshots.
- The committed browser regression intercepts exploratory preference saves.
  Installed mode additionally performs a real native Author hide/reload/restore
  on the empty draft fixture, then checks the recovered panels again.
- The zero-height test was corrected to allow a visible native Publish metabox:
  the requirement is removal of the artificial 250px minimum, not hiding native
  publishing controls. No runtime code change was needed for that test correction.

MRN staged-source QA passed for tree
`72715a7048d0f0879d788f9526333455bbe8d906`: lint, PHPCS security, WordPress standards,
PHP compatibility, Semgrep, secrets, diff and Composer. Existing PHPStan advisory:
missing `wp-cli-stubs` in the staged snapshot. Its runtime rows are intentionally
skipped; the named-site regression supplies targeted browser/AJAX/accessibility
evidence. No new server API surface exists. Full content save, broad compatibility,
editor CPU/OOM, release/parity and Fleet qualification were not repeated and
remain separate gates.

Payload increases by **387 gzip bytes JS + 12 bytes CSS**, with no new assets or
network requests. The preference callback inspects the handful of native View
checkboxes only for a matching preference save; no field scan, observer or polling
was added. This is not a general editor performance qualification.

Installation verifies exact host/owner, source and installed hashes, and a fresh
remote Updraft DB backup **32cea1509ed7** before replacing the two assets and
running the private recovery script. Originals and recovery before/after values
are recorded in the installation receipt. Filemtime cache versioning remains.

Changed runtime files: `mrn-editor-lockdown/assets/section-navigator.js` and
`mrn-admin-ui-css/mrn-editor-workspace.css`. The regression lives in Editor
Lockdown's `tests/runtime/editor-panel-preferences.cjs`; recovery is
`scripts/review/20261104-page-settings/restore-panels.php`.

- [Baseline reproduction](baseline-results.json)
- [Candidate checks](candidate-results.json)
- [Installed checks](installed-results.json)
- [Backup, recovery and installed hashes](install-receipt.json)
- [Light](installed-light-settings.png) / [Dark](installed-dark-settings.png)
- [Restored settings after native save/reload](installed-restored-settings.png)

The user's original unsaved new-page tab is preserved. It must load the new
script after its draft is safely saved; a separate review tab can demonstrate
the fixed workspace without discarding those edits. No production or Fleet work.
