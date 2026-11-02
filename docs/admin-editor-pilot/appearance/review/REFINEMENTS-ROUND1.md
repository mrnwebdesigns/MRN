# Annotation refinements — round 1 implementation

**Round 1 implementation: 2 November 2026. Local review handoff: 3 November 2026. Source is installed; native client and save/reload checks pass. Automated browser/performance qualification remains incomplete. Visual acceptance and release readiness are separate gates.**

The owner approved all seven clusters / ten annotations with “Let's make these changes for now, then another round of annotations.” Work stays on `codex/admin-editor-pilot-20261029` in the existing dedicated worktree. The only installed target is `https://admin-editor-pilot.localhost`. No shared development, staging, production, release publication or Fleet action is authorized or performed.

## What materially changes

| Cluster | Editing / administration experience |
| --- | --- |
| UX-C04 | The native publishing toolbar and workspace menu use one fixed vertical stack below the WordPress toolbar. The existing height calculation now measures all three bars; a single content spacer replaces competing sticky flow. Outline, Site menu and field/jump offsets use those same heights, including wrapped mobile controls. No publishing control or ACF field is reparented. |
| UX-C06 | Nested block/row repeaters gain named second-level headers, item counts and distinct component surfaces. Each header toggles its row; the local group control collapses/expands its rows through ACF's installed native `collapse` / `expand` methods. Event delegation supports duplicated rows without copying handlers. Table repeaters keep their native column layout. |
| UX-C07 | The existing outline searches authored text, headings, links/labels, URLs, plain rich text and nested content, including unsaved changes. Matching results include a short field/component excerpt. Selection activates native parent/nested tabs, expands the native row and focuses the input/editor or existing link-edit control. Excerpts use `textContent`; no authored HTML is inserted. The index is lazy, held only in memory per layout, and refreshed on dirty-layout / TinyMCE changes. Clones, disabled conditional inputs and named internal style/configuration fields are excluded. |
| UX-C05 | ACF parent and nested tabs become compact related strips, with a defined selected surface/underline, hover and focus states, and narrow-screen wrapping. Native tab activation remains ACF-owned. |
| UX-C02 | Sidebar rows and group gaps are tighter; AME group labels have stronger weight and contrast. Current rows use a rounded surface/accent without the pointer silhouette. Mobile targets remain at least 44px. AME still owns menu configuration, branding, ordering and collapsing. |
| UX-C01 | The two known monochrome SEOPress/Relevanssi shell assets use their existing alpha shapes with semantic icon colors. This is narrowly scoped to the verified renderers; no global inversion/filter or vendor-app restyling is introduced. Unsupported/custom image renderers retain native styling. |
| UX-C03 | Native Screen Options and Help align with the page-title row on allowlisted native/MRN screens with a compatible heading. Original IDs, native panels, listeners and form controls remain. Other application interfaces are unchanged. |

Light and Dark reuse the current semantic tokens. Existing appearance preferences, per-user storage, early theme selection, emergency disable, Classic Editor, ACF field definitions/storage and native save-integrity protections are unchanged.

## Installation and rollback

Eight changed runtime files were copied to the isolated fixture. [Source/installed SHA-256 verification](refinements-round1/installed.json) records matching bytes. Previous files are retained under the ignored local `outputs/admin-refinements/runtime-before/` directory for rollback. No migration, fixture reset or media write was performed. Native Save Draft was exercised once on each authorized draft. Temporary appearance and menu preferences were restored to the original Light/folded state; original editor tab 1 was left untouched.

The [pre-change native fixture readback](refinements-round1/baseline-readback.json) and [final native readback](refinements-round1/final-native-readback.json) retain 19 and 32 sections on drafts 2523 and 2524. **Both drafts' builder content, complete native ACF and post-content hashes match the baseline.** Revisions increased from 40→41 and 6→7 after one native Save Draft each; both remain drafts. [Full hash comparison](refinements-round1/save-integrity-comparison.json).

Native Save Draft success notices were observed, followed by explicit reloads and 19/32-section readbacks. The browser inspector caps returned arrays at 2,000; its value-vector hashes are explicitly a **sample**, not all fields. The real/stress forms expose 3,834/6,376 native controls. Full integrity is established independently by WordPress/ACF readback rather than that sample. [Real-page browser check](refinements-round1/native-save-19.json), [stress check](refinements-round1/native-save-32.json), [native client probes](refinements-round1/live-editor-checks.json).


## Verification

| Coverage | Result |
| --- | --- |
| Both full-component MRN QA source suites | Pass: PHP lint, PHPCS/security, WordPress best practices, compatibility, PHPStan, secrets/debug scanning and diff checks. [Admin source report](refinements-round1/qa-admin-static.md), [editor source report](refinements-round1/qa-editor-static.md). |
| JavaScript syntax / existing PHP contract harnesses | Pass: both changed browser scripts and the new targeted harness parse; native editor-assets/dependency/CSP and ACF layout-discovery/visibility harnesses pass. |
| Runtime WordPress API | Intermittent: initial [standard QA](refinements-round1/qa-admin.md) returned 503; later REST returned 200 in 1.43s and an authenticated Dashboard document returned 200 in 8.59s. Source-only reports use `--run-api static`; their generic “100% SUCCESS” text does not include runtime qualification. |
| Client workflows | Pass in the native Codex browser: saved rich-text search finds “ongoing technical support” in Cards 2; an unsaved Card Name updates the summary/excerpt; selecting the result expands its native row and focuses the input. Test text was restored. Light→Dark preference save was acknowledged, Dark persisted across Dashboard→Pages→MRN settings navigation, and original Light/folded preferences were restored. |
| Targeted automated browser suite | **Partial:** Light shell controls/icons and owned shell axe pass (zero violations), plus all nine Light workspace geometry/hit/reflow cases. Native Cards collapse from 9,009px to 703.19px with identical successful ACF FormData. Test selectors for empty Card Name / native ACF rich text were corrected, but full automated completion remains pending after repeated load/inspection timeouts. [Latest blocked attempt](refinements-round1/annotation-test-final.log). |
| Before/after full-page screenshots | **Available:** [paired annotation review](round-2.html) covers Dashboard, keyboard-expanded sidebar, Pages, MRN settings and expanded/condensed editor in both modes. Light hover/mobile captures are included; Dark hover/mobile remain pending. Before images are the genuine 30 October baseline. Current native/headless widths and branding differences are disclosed in the gallery. |
| Native Save Draft / explicit reload | **Pass:** both authorized drafts retain complete native WordPress/ACF hashes, all 19/32 sections and draft status. No Publish action was used and no authored content remains changed. |
| Validation / arrangement / media / incomplete submission | Backend protections and native controls are unchanged. The existing earlier evidence remains valid for its tested source; the full negative-validation, truncated-submission, arrangement and media rerun is **pending** for this candidate. No such new pass is claimed. |
| Accessibility / keyboard / responsive behavior | Source review, Light owned-shell axe, nine Light width/scroll hit cases, native nested-row focus and Dark keyboard submenu checks pass. Five computed Dark editor pairs have ratios 5.97–12.62:1, including the disabled previous control. [Measured pairs](refinements-round1/dark-editor-contrast.json). Complete editor/Dark axe and narrow Dark checks remain **pending**; this is not full WCAG certification. |
| Editing CPU / heap and usability | Measured Cards height reduction is a workspace observation, not a percentage improvement in human task speed. No new matched startup CPU/heap qualification was obtained. Separate CPU and Local Hub stability release blockers remain open. |

The admin-only QA profiles intentionally skip generic public-page browser, axe and performance rows; authenticated targeted suites must supply that coverage. Semgrep is skipped because this project has no Semgrep configuration. Full release, remote parity, packaging and Fleet checks are intentionally outside the owner's closed gate.

## Runtime blocker, kept separate

The fixture stopped responding before the revised source was installed. Static files still returned HTTP 200 from VM loopback, while native WordPress documents and REST requests stalled or returned 503. MariaDB/Redis/OpenLiteSpeed reported active; available memory remained about 4.8–5.0GiB with unused swap. The inspected guest agent had about 62MiB RSS and 1,300 descriptors, following the earlier VM start. These observations do not clear the historically reproduced forwarding leak or prove a new OOM.

The final read-only snapshot on 3 November at 12:22 UTC found 9,383 guest-agent descriptors and 185.4MiB RSS, with about 4.46GiB available memory and unused swap. The current-boot kernel query found no matching OOM entries (no-match exit 1). Descriptor growth keeps the forwarding-leak investigation open; this snapshot is not an OOM repair or CPU qualification. [End snapshot](refinements-round1/runtime-end-snapshot.json).

VM CPU pressure was high (later `some avg10=80.53`, `avg60=83.46`). Worker file-path samples also identified another local site in the shared runtime; this is evidence of concurrent shared-runtime activity, not proof of its cause. The owner confirmed no other chat is working on this project.

A narrowly scoped recovery recycled six PHP workers only after sampled file paths identified `admin-editor-pilot`; unidentified and other-site workers were left running. [Sanitized recovery log](refinements-round1/php-worker-recovery.json). Native login still timed out at 30s afterward; [memory/pressure readback](refinements-round1/runtime-recovery-check.txt). No VM/global service restart, upgrade, CPU/RAM tuning, runtime configuration change, credential change or other-site source/content edit was performed. Repeated failing headless loads were paused. When the native Codex page became usable, checks continued in the loaded page and through normal native navigation. Full-load and Accessibility/Runtime inspection timeouts are not all attributable to a failed WordPress save or the historical OOM: native success notices and independent database readback verified both saves. Current timeout causes remain incompletely isolated.

A browser-test setup issue also amplified repeated traffic: Playwright request routing disables HTTP caching ([official documentation](https://playwright.dev/docs/api/class-browsercontext#browser-context-route)). The final harness uses Chromium Network allow/block patterns to preserve normal cache behavior while rejecting off-origin requests; its fallback wildcard denies all HTTP if the allow-pattern capability is unsupported ([protocol schema](https://raw.githubusercontent.com/ChromeDevTools/devtools-protocol/master/json/browser_protocol.json)). No runtime configuration was changed. This does not prove the native WordPress stalls or historical OOM are repaired.

[Partial Light checkpoints](refinements-round1/verified-light-checkpoints.json), [latest blocked run](refinements-round1/annotation-test-final.log), [REST health](refinements-round1/final-runtime-health.txt), [authenticated document health](refinements-round1/document-health.jsonl).

## Changed files and components

- `mrn-editor-lockdown` 1.0.39: `assets/section-navigator.js`, `includes/section-navigator.php`, plugin header and component `stack.lock`.
- `mrn-admin-ui-css` 3.3.1: `mrn-editor-workspace.css`, `mrn-admin-shell.css`, `mrn-modern-admin.css`, `appearance.js`, plugin header and component `stack.lock`.
- Targeted native save/reload/truncated-save harness (prepared, not run while page loads are unreliable): `mrn-admin-ui-css/tests/runtime/refinement-integrity.cjs`.
- Targeted combined runtime harness: `mrn-admin-ui-css/tests/runtime/annotation-refinements.cjs`. `--baseline` serves the retained original CSS/JS in that test browser only; it does not roll back installed files or change preferences.
- Review log, prioritized UX backlog, historical gallery status and this evidence record; `.gitignore` adds generated local refinement outputs.

## Next step and unresolved issues

1. **P1:** coordinate reliable Local Hub WordPress responsiveness without altering unrelated sites or treating a restart as a leak repair. Preserve the current user editor tab and any unsaved work.
2. **P1:** finish the automated validation/truncated-save, arrangement/media, accessibility and matched 19/32-section CPU/heap checks after runtime stability is understood. Native save/reload and final full data/source readbacks now pass.
3. **P2:** review the supplied paired Dashboard/editor/list/settings captures and live interfaces. Finish Dark hover/mobile evidence and record further polish without automatically implementing it.
4. **P2:** resume annotation collection at `UX-011`, group shared Light/Dark findings, and seek approval before implementing a further batch. Table-repeaters, unsupported/custom icon assets and native third-party accessibility debt remain explicit compatibility limits.

The native editor, MRN settings and paired annotation gallery are left open for review. The original user editor is preserved. New feedback starts at UX-011 and remains collect-only until separately approved. No cluster is visually closed solely because its code is written. Another architecture redesign, broad compatibility testing, release preparation and Fleet rollout remain out of scope.
