# Integrated W5 editor and W6 admin — Local Hub review candidate

Both workstreams are implemented on the isolated **admin-editor-pilot** Local Hub site. Editor Lockdown **1.0.38**, Admin UI CSS **3.2.8** and Dashboard Support **1.3.2** remain existing components. No plugin, framework, content schema, endpoint, menu configuration system or additional feature flag was introduced. This is a local review candidate. Release preparation, publication, shared-environment deployment and Fleet work have not begun.

## What changes in daily work

**The dashboard is a starting workspace.** Four capability-gated content tasks and the existing Notifications Center unread/attention state appear first. Site details, launch-date controls and support remain available in a keyboard-operable disclosure. Existing analytics and forms widgets follow in an adaptive two-column layout, retaining their internal interfaces. Empty stored dashboard columns no longer reserve a large blank area at rest; native drop areas return while dragging or focusing move controls. The MRN start widget stays inside its original full-width native sortable container. No dashboard layout preference is overwritten.

**Navigation keeps its existing owner.** AME still controls menu labels, order, groups, visibility and branding. The presentation gives those groups clearer headings, generous item spacing and distinct active states. Decorative AME submenu separators are removed from keyboard tab order; real menu actions are unchanged. This is no replacement menu editor.

**Content lists expose the next action.** Status views, search, filters and bulk controls have distinct work areas. Titles carry more visual weight, row actions stay visible, and a live selection count explains the native bulk workflow. Native search, Quick Edit, pagination, filtering and bulk handlers retain their original form and identifiers. The design does not hide or rebuild third-party SEO columns.

**Settings share the workspace language.** Native forms have readable label/value rows and an accessible sticky native Save area. Existing MRN settings keep their own tabs, fields, submit buttons and handlers, with shared surfaces, controls and toolbar styling. Mobile native/MRN forms reflow without relocating fields. The supported MRN pages were inspected explicitly; none is selected by an unrestricted prefix rule.

**The approved editor structure stays intact.** Its persistent outline, focused native section, Content/Page settings split, original publishing toolbar and return navigation remain. W5 adds real keyboard arrangement and validation discovery, preserves outline nodes while typing, and waits for ACF's native load phase before revealing fields. [Editor details and measurements](EDITOR.md).

## Demonstration and complete screenshots

Use Local Hub → **admin-editor-pilot** → **Login to Admin**. Open the [dashboard](https://admin-editor-pilot.localhost/wp-admin/index.php), then Manage pages and the [real 19-section draft](https://admin-editor-pilot.localhost/wp-admin/post.php?post=2523&action=edit). The [32-section derived fixture](https://admin-editor-pilot.localhost/wp-admin/post.php?post=2524&action=edit) remains available. A clearly named local publishing QA page (2573) verifies new-page publishing. No original client page or upload was changed.

| Workspace | Before | Completed local revision |
| --- | --- | --- |
| Dashboard | [Native dashboard](evidence/before-dashboard.png) | [Task workspace](evidence/after-dashboard.png) |
| Editor | [Approved structure before W5](evidence/before-editor.png) | [Complete editor](evidence/after-editor.png), [full height](evidence/after-workspace-full.png) |
| Section arrangement | [Approved arrangement view](../revision/evidence/after-arrange.png) | [Keyboard arrangement](evidence/after-arrange.png) |
| Pages | [Native list](evidence/before-pages.png) | [Modern list](evidence/after-pages.png) |
| Posts | [Native list](evidence/before-posts.png) | [Modern list](evidence/after-posts.png) |
| MRN CPT | [Team Members](evidence/before-cpt.png) | [Modern CPT list](evidence/after-cpt.png) |
| General Settings | [Native form](evidence/before-settings.png) | [Modern form](evidence/after-settings.png) |
| MRN Site Configurations | [Existing settings](evidence/before-config.png) | [Shared workspace](evidence/after-config.png) |
| Notifications Center | [Existing center](evidence/before-notifications.png) | [Shared workspace](evidence/after-notifications.png) |

The admin baselines were captured with the same local flag disabled, returning the existing native presentation. The editor comparison preserves the already-approved workspace as its W5 baseline. [Original pilot before the workspace redesign](../revision/evidence/before-workspace.png) supplies the broader project comparison. Screenshots include the full viewport chrome, not cropped controls. Desktop uses 1600×1100; mobile uses 390×844. Browser requests to other origins were blocked; imported off-origin branding/avatar images may therefore appear unavailable in test captures.

## Functional integrity and accessibility

- Full editor workflow passes with zero page errors: native validation/error discovery, keyboard focus/return, content/settings/form-entry equivalence, keyboard and actual pointer arrangement, native add/duplicate/remove, media open/cancel, TinyMCE and nested repeater edits, Save Draft/reload, restoration, 32-section save and incomplete-save HTTP 400. [Full workflow](evidence/workspace-results.json), [arrangement](evidence/arrangement-results.json).
- Native new-page publishing and reload pass with two ACF layouts. Editor access and subscriber denial remain native. [Publishing/roles](evidence/permissions-publishing-results.json).
- Native empty-result search and selection handling, search, Quick Edit save/reopen/restoration, draft filters, bulk Edit/open/cancel, list selection, Notifications Center filters and General Settings save/reload/restoration pass. [Admin workflow](evidence/admin-results.json).
- New editor surfaces and representative admin surfaces have zero WCAG A/AA axe violations at the tested desktop/mobile sizes. [Admin scans](evidence/admin-accessibility.json), [editor desktop](evidence/axe-desktop.json), [editor mobile](evidence/axe-mobile.json). Owned control borders were also manually checked above 3:1 against the light surfaces. [Contrast evidence](evidence/control-contrast.json). Existing native ACF/TinyMCE accessibility findings remain a provider-baseline issue; these results do not certify the whole editor as WCAG AA.
- WebKit smoke passes on dashboard, Pages, MRN settings and editor search/settings/mobile navigation, with zero page errors after native background requests settle. An initial rapidly navigated WebKit harness aborted preferences/heartbeat requests; the settled rerun is clean. The final native-sortable dashboard also passes a separate WebKit mobile check. Chromium provides the full native write coverage. Firefox and a fleet/version matrix were intentionally not run. [WebKit](evidence/webkit-results.json).
- Native dashboard keyboard move down/up, real pointer dragging and collapse/expand pass with measurable native containers. Order/state requests were intercepted in that UI check to preserve fixture preferences. [Dashboard widget regression](evidence/dashboard-widget-results.json).
- The existing Notifications Center notice-channel/toolbar regression harness passes. Its provider, state, permissions, nonces and handlers are reused.

The fixture's imported Editor role already has `manage_options`; its Notifications Center access is preserved. Subscriber receives no task/notification links and cannot open the edit form. No roles or capabilities were changed.

## Performance and warning resolution

Early section reveal caused hidden Select2 controls to be enhanced before ACF's native load phase. Deferring reveal removes the extra startup allocation while preserving native initialization. Three alternating original/approved/final pairs retain four TinyMCE instances in each equivalent expanded view. Approved → final retained heap is **29.2 → 27.1 MB** (19 rows) and **31.9 → 30.1 MB** (32 rows). Typing script CPU falls **135.7 → 42.4 ms** and **208.5 → 53.8 ms**, with zero outline-group replacements. Final startup CPU is within roughly 2% of the original pilot. [Raw samples](evidence/editor-performance-final.json), [summary](evidence/editor-performance-summary.json).

For admin screens, three native/enabled samples show unchanged retained heap: dashboard about **21.0 MB**, Pages **19.0 MB**, General Settings **19.6 MB**. Initial dashboard CPU batches were mixed, so four alternating pairs were added: native **228.0 ms**, modern **234.0 ms** median; script CPU **151.9 → 148.5 ms**. Pages/Settings timings vary within overlapping local ranges. Native/modern ranges overlap; the final grid adds about 4.7 ms of median layout work. No material regression is established by these small samples, and no general speed claim is made. [Admin measurements](evidence/admin-performance-summary.json), [paired dashboard samples](evidence/dashboard-performance-paired.json).

WordPress 7 opts admin documents into CSS cross-document view transitions. Supported pilot screens now explicitly use ordinary navigation, avoiding snapshot transitions around large forms. The verified rule sequence is `auto` then `none`, and editor-to-list navigation has no snapshot. No error is caught or suppressed. The original one-time warning's precise historical trigger was not captured. [Startup/transition verification](evidence/startup-verification.json).

Usability percentages remain measurement targets. The recorded search/Enter response is a supplied-query browser latency, not human task time. An MRN editor should now review the integrated workflow and record completion time, mistakes and confidence.

## Local runtime interruption

A final read-only check encountered HTTP 502/ECONNRESET. The VM kernel recorded memory exhaustion and killed the Lima guest agent (about 2.8 GB resident), interrupting local forwarding. The Local Hub `start-site` recovery call timed out after 300 seconds; no completion claim is based on that result. Subsequent HTTP and authenticated dashboard checks verify that the demonstration is responding again. Safety constants remain intact. No manual VM/PHP memory-limit changes or broad infrastructure changes were applied. The exact allocation cause remains unconfirmed and local runtime stability is a prerequisite for any release qualification. This is separate from the measured browser startup heap correction. [Recovery evidence](evidence/runtime-interruption.json).

## Scope, emergency disable and review boundary

The single strict `MRN_EDITOR_EXPERIENCE_ENABLED` boolean and `.localhost` gate remain. Set it to `false` in the local fixture's `public/wp-config.php`, allow the local PHP cache to revalidate (3 seconds used), and reload. The original dashboard/admin/editor return without data migration. Native rows and save guards remain. [Admin disable](evidence/emergency-disable-admin.json), [editor disable](evidence/disable-results.json). Blocked-script fallback and native conditional logic are also checked. Native searchable Select2 controls pass across tabs, section changes and return after reload; the startup fix preserves their enhancement. [Native select check](evidence/native-select2-results.json).

Only core dashboard, native MRN/core content tables, named native settings screens and exact MRN page slugs receive the new admin assets. WPForms, SEOPress, Media and ACF field-definition screens were checked for asset exclusion; WooCommerce/Tutor and other app interfaces are excluded by the scope rule rather than restyled internally. No new app was installed for compatibility testing.

MRN QA Engine component suites pass for Editor Lockdown, Admin UI CSS and Dashboard Support. The staged-snapshot gate passes with a PHPStan advisory because that isolated snapshot lacks WP CLI stubs; the full component PHPStan checks pass. Standard component policy intentionally skips generic public-page browser/accessibility/performance/CWV rows; authenticated Playwright/axe/CDP runs provide the relevant admin coverage. API-surface changes are not applicable; the REST index returns HTTP 200, while native post/admin-ajax write paths are exercised. Full release, remote parity, deployment, backup/deploy readiness, Fleet and broad compatibility checks were intentionally excluded. Local Hub is exempt from the remote Updraft pre-write gate. Component QA boilerplate is not release signoff.

[Changed files](CHANGED-FILES.md) and the [prioritized UX backlog](UX-BACKLOG.md) accompany the evidence. The recommendation is an integrated MRN editor review followed by a bounded polish/compatibility sprint addressing the observed tasks. Keep release preparation and Fleet rollout stopped until separately approved.
