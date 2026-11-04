# UX-041 — Stable shared Site menu

The owner reported a jumpy Media menu with unrelated submenus already expanded,
and requested shared functionality with Edit/Create. The old Media controller
focused Dashboard on opening; its CSS expanded in-flow submenus on focus/hover
and for the current menu. WPForms also forced its submenu to display. The editor
had separate click/Escape handlers and different drawer CSS.

Both workspaces now use the same delegated controller in `appearance.js` and the
same drawer/disclosure CSS in `mrn-admin-shell.css`. All submenus start collapsed.
Separate named expand buttons reveal them explicitly, while original parent and
child links keep their destinations. Opening leaves focus on Site menu; Tab or
ArrowDown enters the drawer, Escape returns focus, and outside click/focus closes
it. Native AME group headings/configuration are retained. Disclosure state lasts
only for that loaded page, with no new preference store.

Source: `f9f231e290a1767810aba213838edd1a22a9e712`. Five files were installed only
to `admin-editor-pilot.mrndev.io` after source QA and a fresh labeled database-only
Updraft backup was verified remotely. [Receipt, file hashes and rollback](install-receipt.json).
No production, Fleet, release publication or infrastructure changes.

## Scope and QA

Changed files: `appearance.js`, `appearance.php` (localized disclosure label),
`mrn-admin-shell.css`, `mrn-editor-workspace.css`, and Editor Lockdown's
`assets/section-navigator.js` (remove duplicate handlers; retain anchor fallback).
The component usage is documented in the Admin UI CSS README.

MRN staged-source QA passed PHP lint, WordPress/security standards, static API
audit, Semgrep, secrets, compatibility and whitespace checks. PHPStan retains the
existing advisory missing WP-CLI stub. [Source report](source-qa.txt). Generated
release wording in that report is task source proof, not release qualification.

Targeted candidate and installed runtime checks cover Media list/grid/Add Media,
complex Edit Page and new Page: all submenus initially collapsed, hover without
height changes, explicit mouse/Space/Enter disclosure, Tab/ArrowDown entry,
Escape/focus restoration, Light/Dark scoped axe, 390px placement, unchanged native
editor form values, native media dialog/upload controls and shared row Actions.
Installed assets are compared byte-for-byte with committed source. Additional
checks cover scrolled opening without page jumps, original AME disclosure/link
preservation, native REST health, no-JavaScript navigation and blocked appearance
script fallback on Media and Editor. Native library view is restored to List.
No content save, upload, replacement or deletion was performed; Add Page QA visits
use WordPress's native auto-draft creation. [Results](installed.json) and
[bounded timing/fallback checks](installed-extra.json).

Installed verification passed 28 main checks and 6 additional checks. The bounded
installed first open/close, including control preparation and layout, took 35.3 ms;
repeat median was 1.8 ms. Combined existing JavaScript/CSS adds 720 gzip bytes
([payload](payload.json)).

No new assets, requests, observers, scroll listeners or polling. The controller
prepares 21 disclosure buttons once on the first opening, outside initial page
load; later openings reuse them. Timing samples include synchronous native click
handlers and forced drawer layout, not whole-page CPU qualification. Existing
editor CPU and Local Hub OOM release blockers remain open. Full release/parity,
Fleet, whole-page accessibility and broad compatibility suites were intentionally
not run; focused axe checks cover the shared bar/new controls, preserving the
separately tracked vendor label/native ACF accessibility debt.

[Before](before-media.png) · [Media Light](installed-media-light.png) · [Media Dark](installed-media-dark.png) · [Editor](installed-editor-light.png) · [Create](installed-create-light.png) · [Mobile](installed-media-mobile.png)

[Codex review tab](codex-media.png) is refreshed with the menu open and submenus collapsed.
Await owner visual review. Next annotation: UX-042.
