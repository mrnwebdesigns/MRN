# UX-045 — Embedded SEO panel appearance boundary

Source `68bf806a964cabc4bd26402e7c330a8b7f6945aa`, installed on
`admin-editor-pilot.mrndev.io` on 5 November 2026. Awaiting visual review.

## Root cause and correction

The shared appearance shell recolored fields inside the embedded SEOPress
application, while SEOPress retained its native white surfaces and dark placeholder.
The result was a dark title field with an almost invisible generated-title hint.
The computed placeholder contrast was 1.08:1 over the dark field.

The shared shell now excludes `#seopress_metabox_opener .inside` descendants from
its general field, link, button, notice and description palette. That container
gets the same native light fallback already used for plugin-owned applications,
media dialogs and TinyMCE. The MRN metabox heading and Page Settings shell retain
the selected theme. No vendor internals, application stylesheets or behavior are
reimplemented, and no filters/inversion are used.

This deliberately preserves SEOPress's native light interior in Dark/System mode;
it fixes the inconsistent and unreadable fields. Native SEO remains responsible
for its tabs, controls, labels, previews, saving and validation.

[Before Dark](before-dark.png), [installed Dark](installed-dark.png),
[installed Light](installed-light.png), [System following Dark](installed-system.png).

## Qualification

- MRN source gate passed: `c173061440b8569e7b3883e0ff6daf8586c4e26e`.
  [Source QA](source-qa.txt); diff check passed. No PHP/API/save handler changed.
- Candidate then installed browser QA targeted the reported draft fixture 2580.
  [Before measurements](before.json), [candidate](candidate.json),
  [installed checks](installed.json). Light, Dark and System following Dark were
  checked at 1487px and 390px. Native input geometry, form ancestry and adjacent
  Parent field colors match the pre-change baseline.
- Scoped axe WCAG A/AA scans pass for SEO in all three modes, Social, Advanced,
  Title settings, and adjacent Page Attributes/Slug controls. Keyboard focus,
  Content/Page Settings navigation and unchanged SEO values pass. No draft or
  SEO values were saved during testing; save/reload was not rerun for this CSS fix.
- Axe did not flag the original generated-title placeholder. A separate computed
  alpha-blended contrast measurement confirms its improvement from 1.08:1 to
  4.71:1, above the 4.5:1 text threshold. [Contrast and payload](contrast-payload.json).
- CSS adds 81 gzip bytes; no added JavaScript, requests, observers or initialization.
  No claim of server/CPU improvement. Existing CPU, native-save-latency and Local Hub
  OOM blockers remain open.
- REST health returned 200. The [actual versioned browser stylesheet response](served-file.json)
  matches committed source. The Codex review tab is refreshed on Page Settings.

The source gate skips broader runtime/API/accessibility/performance checks by its
staged-source scope; the named-site checks above supply relevant runtime evidence.
Whole-plugin/Fleet qualification and broad third-party compatibility remain outside
this review correction.

## Installation

Only `mrn-admin-ui-css/mrn-admin-shell.css` changed on the review runtime. Exact-host,
owner and preimage hash checks preceded a fresh labeled, verified remote database
backup and atomic file install. [Backup, installed hashes and rollback](install-receipt.json).
The component README documents the boundary. No production or Fleet deployment,
release publication, content or plugin configuration change.

Next annotation: UX-046.
