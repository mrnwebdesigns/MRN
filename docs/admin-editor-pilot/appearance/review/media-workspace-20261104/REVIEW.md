# Media workspace — 4 November 2026

**UX-033**, implemented under the owner's standing feedback/test-deployment
approval. Source commits **976258a2** and **d6ccdacd6661** are installed only on
**admin-editor-pilot.mrndev.io**. Human visual acceptance remains pending.

The Media Library now opens without the WordPress sidebar, with **Site menu**
always available in the top toolbar. It reveals the original AME-owned menu as
a drawer, then closes with Escape, outside click or focus leaving the menu.
No menu links, order, permissions or branding configuration are recreated.

The library gains **225px of visible width** at the measured 1487px viewport.
The existing folders panel stays visible; its native controls and persistence
remain owned by HappyFiles. The core content wrapper can now shrink alongside
that panel instead of overflowing the viewport. On narrow screens, the panel
flows above the library and existing filters/bulk selectors fit the screen.
Wide media table data retains its existing horizontal scroll component.

Scope is the native Library list/grid and Add Media screens. Application-owned
media subpages, attachment edit forms, Dashboard, Pages and ACF Editor Workspace
are not assigned the new shell. Media application interiors/dialogs keep their
existing native appearance; the toolbar and navigation use shared Light/Dark
tokens. No plugin, setting, dependency or separate asset was added.

## Changed runtime files

All changes extend the existing `mrn-admin-ui-css` component:

- `appearance.php`: exact screen guard, body class and native toolbar node.
- `appearance.js`: event-driven disclosure, keyboard/focus and close behavior.
- `mrn-admin-shell.css`: full-width shell, drawer and narrow-screen wrappers.

The server-scoped class establishes width before first paint. With JavaScript
disabled, the new workspace rules do not apply; existing core/AME state remains.
If only the enhancement script fails, the original link's fragment target still
exposes the menu. The existing emergency switch disables the whole addition.

## Verification

Candidate and installed checks pass for both Library modes and Light/Dark:

- Original menu hrefs unchanged; keyboard Enter/Space opening, Escape/focus
  return, outside click, folded-state labels and 390px drawer placement.
- Four scoped axe WCAG A/AA scans per run with no violations in the changed
  control/menu; no page errors. This is not a whole-vendor-interface a11y claim.
- Search, native view links and bulk controls retained; native grid attachment
  dialog opens/closes; Add Media uploader remains available.
- Both modes have 390px document width at a 390px viewport. Native table content
  continues to scroll within its existing container.
- Failed-script anchor fallback; no-JavaScript core/AME computed state matches
  before/after. Full no-JavaScript vendor workflows are not qualified.
- Installed Dashboard, Pages and complex ACF editor remain outside Media scope.
- PHP screen-guard probe covers exact screens, vendor exclusions, frontend and
  emergency-disable boundaries. PHP lint and JavaScript syntax pass.

MRN staged-source QA passes for trees `b5084b84931321b7d4c568aa877206bf65138014`
and `03c58234d5a40f7a0afe5b6f4728024f6ae5c9d1`. PHP security/WordPress standards,
compatibility and static API audit passed. PHPStan reported its pre-existing
missing WP-CLI stub in advisory mode. Browser/a11y/performance rows skipped by
the static commit gate are supplied by the named-site probes here. Full
release/parity, publishing/save and media upload/delete tests were not run:
this patch changes presentation/navigation, not their handlers or storage.
No content, attachment, menu configuration or account preference was saved.

## Bounded performance check

Serial isolated Chromium contexts: one excluded warm-up, then ABBA with two
measured loads per variant. Candidate PHP output was represented by its exact
body class/native toolbar markup before installation; installed checks then
require actual PHP output. No general performance qualification is inferred.

| Mean of measured samples | Before | Candidate |
| --- | ---: | ---: |
| Browser task CPU | 417.3ms | 382.1ms |
| Script CPU | 237.8ms | 239.6ms |
| Retained JS heap after forced GC | 26.165 MB | 26.144 MB |
| Layout shift | 0.389 | 0.221 |

An earlier candidate hid the sidebar after paint and increased layout shift;
that defect was corrected before installation. Remaining layout shift and
project CPU/OOM/native save-latency qualification are still open. Raw pre-GC
heap readings vary substantially, so retained heap is recorded separately.
Existing JS/CSS assets add **1,122 gzip bytes**; no additional request, observer,
polling loop or scroll listener. The drawer's listeners run only on Media.

## Installation and review

The exact-owner installer checked previous/candidate hashes, verified fresh
remote database-only Updraft backup **a7195b5ae3b9**, saved private rollback
copies and replaced only the three files. The installed regression passes.
A fresh Codex Media tab is open for annotation; existing editor tabs were not
reloaded. No release publication, production deployment or Fleet activation.

- [Before](before-list.png), [owner screenshot](user-before.png)
- [Installed list Light](installed-list-light.png), [Dark](installed-list-dark.png)
- [Installed grid Light](installed-grid-light.png), [Dark](installed-grid-dark.png)
- [Menu Light](installed-list-light-menu.png), [Dark](installed-list-dark-menu.png)
- [Mobile list](installed-list-mobile.png), [mobile grid](installed-grid-mobile.png)
- [Codex review](codex-review.png), [Codex menu](codex-menu.png)
- [Candidate checks](candidate-results.json), [installed checks](installed-results.json)
- [Performance](performance.json), [payload](payload.json), [API health](api-health.json)
- [Backup/rollback and hashes](install-receipt.json)
- [Source QA](source-qa.log), [startup correction QA](initial-layout-qa.log)

Diagnostic scripts take a private `MRN_QA_AUTH_FILE`; credentials are not in
this evidence. Next review: confirm navigation placement and library width.
