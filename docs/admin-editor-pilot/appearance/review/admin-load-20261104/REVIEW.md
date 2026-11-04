# UX-038 — Stable admin first paint

Request: the admin visibly builds itself during page navigation. Exact review host: `admin-editor-pilot.mrndev.io`. Source commit: `51bbfd60e561` (full hash in install receipt).

## Cause and change

The native editor painted before ACF mounted the workspace, moving the title from the standard sidebar layout into the focused workspace. The publishing toolbar could mount after the navigator; its height was then corrected by a 500ms timer. On native lists, a footer script rebuilt the heading and inserted selection feedback below the toolbar, and Actions enhancement increased row height.

- The eligible Classic Editor renders a small themed “Opening editor…” state while the existing workspace mounts. Native field geometry remains available; no ACF initialization, field movement, form names, validation or save code changed. The state clears immediately when the workspace and installed toolbar are ready, with an 8-second fail-open deadline if enhancement fails. JavaScript-disabled browsers retain the native form.
- The existing direct-child notice observer now measures the publishing toolbar on insertion before paint. The delayed 500ms correction is removed; existing resize handling remains.
- Native h1/Add action markup is styled directly, removing the late wrapper reconstruction. Selection feedback occupies space reserved inside the list toolbar, and native row actions reserve the shared button footprint before enhancement.
- No dependencies, polling, additional asset requests, settings or third-party application overrides. The independent Sticky Bar plugin is unchanged. Existing emergency and eligible-screen gates remain authoritative.

## Targeted qualification

MRN staged-source QA passes: PHP lint, WPCS/security, PHP compatibility, Semgrep, API surface, secrets/debug and diff hygiene. PHPStan remains advisory because the QA snapshot lacks its WP-CLI stub. JavaScript syntax checks pass. The source gate intentionally skips browser/API/performance runtime rows; the separate authenticated browser harness supplies targeted coverage below. Whole-component readiness, cross-repository parity, release and Fleet qualification are not claimed.

Candidate checks pass for 19 sections and 3,206 ACF controls, original values and form ancestry, TinyMCE/Select2 initialization counts, Content/Page Settings, nested collapse, content search, native publishing toolbar availability, Light/Dark scoped axe WCAG A/AA (zero violations), 390px layout, list checkbox feedback, shared Actions keyboard/focus and Quick Edit cancellation. Dashboard and General Settings retain their native heading markup. Blocking the navigator, blocking the publishing-toolbar script, and disabling JavaScript all leave an accessible editor after the bounded fallback. No content save or publishing mutation was performed: these paths are unchanged. A first test assertion matched hidden native Quick Edit templates as well as the active row; it was narrowed to the visible row and rerun successfully.

The balanced before/candidate/candidate/before sample preserved the same ACF integrity hash in every run. Browser CPU samples were 8,131 / 3,657 / 4,223 / 3,652 ms, with 36.5–37.2 MB used JS heap. The first before sample is an outlier in this small set; this small sample does not qualify CPU improvement or rule out smaller regressions. The existing editor CPU and Local Hub OOM release blockers stay open. This patch addresses presentation/reflow, not server response time.

## Deployment and evidence

Exactly six committed files installed by the dedicated site owner after a labeled, database-only Updraft backup was verified at the configured remote destination. The installer requires exact old hashes, correct owner, review marker, recent backup receipt and matching candidate hashes, preserves private rollback copies and restores them on install failure. See `install-receipt.json`.

Changed components/files:
- MRN Admin UI CSS: `appearance.php`, `appearance.js`, `modern-admin.js`, `mrn-admin-shell.css`, `mrn-modern-admin.css`.
- MRN Editor Lockdown: `assets/section-navigator.js`.

Raw first-paint traces and selected frames are named `before-*` and `installed-*`. Candidate HTML/assets were intercepted only in the isolated QA browser; installed traces use the actual served implementation. `candidate-qa.json` and `installed-qa.json` hold runtime checks. The reusable test scripts accept `MRN_REVIEW_AUTH_FILE` pointing to a private, short-lived native session cookie file; no authentication material is included here. Run them from the task worktree. Theme checks change only the isolated DOM, not the saved user preference.



## Installed result

All 13 grouped runtime checks pass against actual served code. The native fixture readback is identical before/after and the save guard remains enabled. The installed control/form integrity hash also matches the candidate and original baseline. The two installed CPU samples remain variable (3,752 and 1,517 ms), so no CPU qualification claim is made.

| Screen | Before shift sum | Installed shift sum |
| --- | ---: | ---: |
| Job editor 1963 | 0.9033 | 0.0142 |
| Complex draft 2523 | 1.4541 | 0.0152 |
| Pages list | 0.0418 | 0.0004 |

These are bounded Chromium first-paint traces at 1487×1000, summing unexpected LayoutShift entries through page load plus 1.7 seconds; they are not field Core Web Vitals or multi-user performance estimates. In the installed editor traces the title is first visible at its final y=243.2px, with the toolbar already accounted for. Remaining small shifts include toolbar icon/font rendering and native ACF initialization. Pages retains its heading position and action-row geometry. The 8-second value is a recovery deadline, not an added wait during successful initialization.

The agent-owned Codex review tab now shows a freshly loaded Job editor. Existing edit tabs were preserved. Visual acceptance awaits the owner's next annotation round. No production deployment, release publication, Fleet activation, or Local Hub infrastructure changes.

### Captures

- [Installed editor, Light](installed-light-editor.png) / [Dark](installed-dark-editor.png).
- [Mobile editor](installed-mobile-editor.png) / [Mobile list](installed-mobile-list.png).
- [Refreshed Codex Job editor](codex-job-editor.png).
- First-paint frames and raw trace JSON sit alongside this report.

All five actual browser-served JS/CSS files match their committed hashes; the authenticated REST root is healthy (`served-readback.json`). A direct urllib probe was rejected with HTTP 403; verification used normal authenticated browser responses without changing edge protection.
