# UX-044 — Shared, centered pagination

Source `9228dae6eabc70ff7323126cea64fa9b4e00ebfd`, installed on
`admin-editor-pilot.mrndev.io` on 5 November 2026. Awaiting human visual review.

## Result

The native count, current-page input, total and arrows now share one horizontal
centerline. The control group keeps its existing desktop toolbar position and
centers on narrow screens. Arrow buttons and input are 36px high on desktop,
44px on narrow screens. Top and bottom pagination share the same styling.

This is the existing admin component's reusable native-markup contract, documented
in `mrn-admin-ui-css/ADMIN_UI_CONTRACT.md`. Its single implementation lives in
`mrn-admin-shell.css`, scoped to audited MRN/native list screens and Media Library.
No renderer, configuration layer, script, observer, listener or request was added.
Native URLs, form submission, accessible names and disabled controls are unchanged.
Media's unboxed count follows its native light canvas for legibility in both modes;
the individual controls use their semantic theme surfaces.

[Before](before.png), [after](after.png), [Light](installed-light.png),
[Dark](installed-dark.png), [Codex Pages workspace](codex-pages.png).

## Verification

- MRN staged source gate passed: `d2767162f24ff4ebd3592fdaaa85071cadc2a284`.
  [Source report](source-qa.txt). Diff check passed.
- [Candidate checks](candidate.json) passed before installation. Accessibility
  caught a Media count contrast problem on its native canvas; the final source
  corrects it. No application-wide recoloring was introduced.
- All 31 [installed grouped checks](installed.json) passed. Pages, Posts,
  Team Members and Media in Light/Dark at 1487px and 390px have zero centerline
  difference, consistent control height and contained pagination at narrow widths.
- Eight scoped axe WCAG A/AA scans (four screens in both themes) found no violations.
  Keyboard focus, Enter on Next, native current-page submission, first/last page
  bounds, preserved sorting, one-page/empty results and no-JavaScript fallback pass.
  The workflow check waits for WordPress initialization after navigation; an earlier
  run interacted before initialization completed. No post or media data was edited.
- Native REST health returned HTTP 200. No REST, AJAX or admin-post handler changed.
  The source gate's runtime/browser/API/performance rows are skipped by its staged
  scope; the named-site checks above provide relevant runtime evidence. Full release,
  Fleet parity and editor save/CPU/OOM qualification were not run for this CSS change.
- [Payload](payload.json): +423 gzip bytes of CSS, zero added JavaScript or requests.
  Styles apply without an initializer. No claim of improved server/CPU performance.
- The actual versioned stylesheet loaded by the browser matches committed source:
  [served response](served-file.json). A direct unversioned probe was unsuitable
  for this check because it hit a different edge/cache response.

## Deployment

Only `mrn-admin-ui-css/mrn-admin-shell.css` was installed, after exact host/owner,
preimage hash and writable-path checks, followed by a fresh verified labeled remote
Updraft database backup. [Install receipt and rollback location](install-receipt.json).
The shared contract is source documentation only. No production or Fleet deployment,
release publication, plugin configuration or content changes.

Existing Local Hub OOM, editor CPU and native-save-latency release blockers remain
open. Next annotation: UX-045.
