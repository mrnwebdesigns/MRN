# Round 3 — account, navigation and contextual View options

Approved by “Make these udpates and push to dev,” 3 November 2026. Scope is UX-018–UX-024 / UX-C01, UX-C03, UX-C09, UX-C10 and UX-C11. Installed only on [the review site](https://admin-editor-pilot.mrndev.io/wp-admin/), using the existing pilot worktree. Human visual acceptance remains open; next annotation is UX-025.

## What changed for editors

- The account menu is 304px wide, with readable profile text and one compact horizontal Light / Dark / System choice. Native account links and the existing per-user save endpoint remain intact.
- **View** replaces the toolbar's Screen label. Its original WordPress View options / Help controls have contained hover and focus areas. The native panel is a bounded, scrollable popover with an accessible Close control, rather than a full-width overlay across the workspace.
- In the Editor Workspace, View options describes **Page Settings panels** and offers a direct **Open Page Settings** action. The legacy one/two-column choice is hidden while the workspace owns layout. Required Hero, Content and After Content regions remain available; optional settings panels retain native visibility preferences.
- On list screens, native columns, items-per-page and their persistence remain available. Dashboard widgets and native Help keep their own controls.
- **Dashboard** is restored in the existing site-name dropdown, alongside Visit Site. AME retains final menu order; this installation places Dashboard after Visit Site. Back to list and native navigation behavior are unchanged.
- UpdraftPlus's actual white PNG shape now uses the existing semantic shell icon treatment. Its link, AME configuration and plugin interface are unchanged.

No fields were moved out of the native form. No plugin, dependency, preference store, observer, polling loop or general integration layer was added. Contextual panel labels are prepared lazily on opening View; the required-region check uses the existing three-region collection loop.

## Root causes and corrections

Core account spans explicitly declared white text, overriding their correctly colored parent. Native avatar-specific margins also overrode the previous compact-menu rules. Both are now scoped to the account menu. Screen toggle buttons inherited content-box sizing, making 100% width plus padding overflow their menu. Border-box sizing contains those states. A core form could intercept the old Close button; the control now has an explicit position and stacking order. Rapid interaction also exposed the native active-class timing race; Close finishes only the native panel animation and then uses the original toggle. Native white checked SVGs were invisible on the Light surface; scoped semantic selected colors now make actual checked state visible without replacing inputs. Generic editor metabox/layout choices did not reflect the workspace's Content / Page Settings ownership; the adapted native panel now makes that distinction without replacing its form or storage.

## Source and deployment

The task branch remains intentionally local and unmerged. This dev installation is not source promotion, a release or Fleet readiness.

- Editor source: `e1dff3dc` — Editor Lockdown 1.0.43.
- Admin source: `bc4de340`, `71090a19`, `5b294f9b`, `1e579b4d`, `4dd60f23` — Admin UI CSS 3.3.4, including installed visual corrections.
- Exact changed runtime files: `mrn-admin-ui-css/{appearance.php,appearance.js,mrn-admin-shell.css,mrn-admin-ui-css.php,stack.lock}` and `mrn-editor-lockdown/{assets/section-navigator.js,mrn-editor-lockdown.php,stack.lock}` under `mu-plugins/`.
- The scoped installer checks target owner, review marker, exact host, all before/after hashes and PHP lint before making a fresh labeled database-only Updraft backup. It verifies the remote receipt, preserves all eight original files outside the web root, replaces files atomically and checks installed hashes.
- No AME configuration, Cloudflare rule, authentication gate, WordPress content, production runtime or Fleet publication was changed by deployment. Authorized draft-save regression checks are reported separately below.

## Verification

[Paired Light/Dark review gallery](round-4.html) contains 68 original before/after captures of the Dashboard, account menu, hover states, View options, Help, site menu, Pages, Team Members, General settings, empty/complex editor and narrow panels. [Live Codex capture](refinements-round3/codex-live-account.png) confirms the installed account menu and Updraft icon with real branding.

- **Pass:** 12 scoped axe WCAG A/AA scans, zero violations. Profile text contrast is 13.83:1 Light / 12.62:1 Dark; Updraft glyph contrast is 4.85:1 / 8.05:1; selected checkbox indicator contrast is 6.86:1 / 8.82:1.
- **Pass:** contained menu hover/focus, Close/Escape and focus return, 390px panel bounds/reflow, Dashboard link, optional Page Settings navigation, required-region entry points and native form ancestry on 0/19/32-section fixtures.
- **Pass:** native list-column and optional editor-panel visibility save/reload/restore. Light/Dark/System preference persistence and System response to emulated OS appearance pass. Test preference changes are restored. No JavaScript errors were reported.
- **Blocked:** native Team Members items-per-page persistence. The original `adv-settings` form submits `edit_team_member_per_page=5` to `/wp-admin/edit.php`; Cloudflare returns HTTP 403 and a challenge, and the original value remains 20. This is outside the existing narrowly approved `post.php` exception. No WAF change was made. The current-rule read-only preflight also hit an OP CLI timeout, so no new exception was applied or represented as qualified.
- **Pass:** native Save Draft plus explicit reload preserve complete ACF FormData on both 19- and 32-section drafts. Native ACF/content hashes match the pre-test readback, and the incomplete-submission guard rejects the truncated request with HTTP 400. Save response times were 37.8s / 112.4s in this run; these include the instrumented, contended client and do not clear save-latency qualification.

The first full harness run reached the per-page blocker after all 12 visual/accessibility scopes passed; its raw result deliberately retains that failure. The separate native continuation explicitly skips that blocked POST and passes the unaffected preferences/System/form checks. These records do not assert an all-green runtime or release result.

Source MRN QA passed for each staged product snapshot. The commit gate's runtime API, browser/accessibility and performance rows are intentionally static/skipped; they are not a runtime verdict. Dedicated exact-site tests supply that coverage. Broad compatibility, release/parity, production and Fleet checks are outside this approved refinement scope.

## Performance limits

The three changed client assets add **1,641 gzip bytes** in total, with no new dependency or polling/observer system. A balanced serial before/after asset probe is prepared in `round3-performance.cjs`, but **was not run** under the observed host contention (load averages 211.33 / 126.21 / 71.53 with substantial memory compression, later fluctuating above 100). Functional-harness CPU/heap readings are descriptive only, not a regression verdict. This neither clears nor attributes the original Local Hub OOM or pilot startup-memory/CPU issue. See the [performance status](refinements-round3/performance-status.json).

## Open gates and next review

The original Local Hub OOM/socket retention issue, original pilot startup CPU/heap qualification and native complex-page save latency remain release blockers. This bounded refinement does not clear them. Existing picker-cancellation lifecycle, native ACF/TinyMCE accessibility debt, fixture branding and broader browser/plugin compatibility remain separately tracked in the [prioritized backlog](../UX-BACKLOG.md).

Review the compact account menu, View options on Dashboard/lists/editor, Dashboard return link and sidebar icon in both themes. Collect the next annotation batch before making further refinements. No release preparation, production deployment or Fleet activation is authorized.
