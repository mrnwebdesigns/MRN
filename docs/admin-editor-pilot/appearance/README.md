# MRN administration appearance — Local Hub review candidate

**Status:** implemented locally for visual/workflow review. Functional checks pass; performance qualification remains blocked by variable editor CPU results. No release or Fleet action is authorized.

**Annotation review is open.** Use the [paired Light/Dark review in the built-in browser](http://127.0.0.1:8764/review/) and [review guide](review/REVIEW.md). The [annotation log](review/ANNOTATIONS.md) records human comments and approvals separately; no refinement is applied automatically. Product source remains the existing appearance candidate.

The administration now uses one semantic Light/Dark palette. Light Mode replaces WordPress's black navigation with a light neutral sidebar; Dark Mode uses slate navigation, a darker canvas and raised content surfaces. Navigation has distinct default, hover, current, expanded-submenu and keyboard-focus states. AME still owns order, labels, grouping, branding and destinations.

The dashboard, core content/user/plugin lists, taxonomy forms, core tools/privacy/update screens, Quick Edit, native settings, MRN Site Configurations, Notifications Center, Business Information and the approved Classic/ACF workspace share the same appearance. The workspace structure, persistent outline, native ACF form tree and publishing workflow remain intact.

## Scope and use

- Local demonstration: https://admin-editor-pilot.localhost/wp-admin/ . Open **Appearance** in the toolbar and choose **Light**, **Dark** or **System**.
- Real complex draft: https://admin-editor-pilot.localhost/wp-admin/post.php?post=2523&action=edit . The page retains 19 top-level content sections.
- Existing worktree/branch: `.worktrees/admin-editor-pilot-20261029`, `codex/admin-editor-pilot-20261029`.
- Candidate component: `mrn-admin-ui-css` 3.3.0. No additional plugin, framework, configuration owner or content schema.
- The existing strict `MRN_EDITOR_EXPERIENCE_ENABLED` boolean and `.localhost` boundary remain the emergency disable mechanism. There is no new feature flag. No shared development, staging, production, publication or Fleet action was performed.

## Preference and loading behavior

`mrn_admin_appearance` is WordPress user meta for the authenticated user. The toolbar has a labeled native select, save action and announced status. Changes save through authenticated AJAX; the same form has a native `admin-post.php` fallback. Requests require the `read` capability and a valid WordPress nonce, accept only the three appearance values and always derive the user ID from the session. There is no global option, browser-wide theme cookie or localStorage dependency. A failed save restores the previous preference and announces the failure.

The server emits `data-mrn-appearance` in the opening HTML tag before styles. Render-blocking CSS resolves the palette; a CSS media query resolves System before first paint and follows live device changes. No late theme initialization script or transition is required. Cross-document view transitions remain disabled on the heavy editor.

## Deliberate native surfaces

The shell follows the account preference on application screens. WPForms, SEOPress, ACF field administration and Media keep their own light content surfaces and receive no modern content/workspace stylesheet. WooCommerce/Tutor and other application-owned screens are outside the native content allowlist. Their internal interfaces are not inverted or restyled. WooCommerce and Tutor were not installed solely to expand this local test.

Native TinyMCE editing chrome/content, media dialogs and third-party dashboard widget interiors remain explicit light surfaces. This preserves their own controls, graphics and contrast. Dashboard widget containers and headers use the MRN palette, while native drag/order/collapse behavior stays in its original containers.

## Verification

Final measurements, screenshot gallery, component QA and unresolved issues are recorded below after the final candidate checks. This is a local review result, not release or Fleet qualification.

### Functional and accessibility results

- Authenticated Chromium: Light/Dark switching, persistence across nine representative screens, fresh-browser persistence, independent Editor account, explicit overrides of System, live media-query changes and failure rollback all passed. JavaScript-disabled native preference saving and CSS-only System behavior passed.
- WebKit: both themes passed dashboard, page list, core settings, MRN settings and editor workflows/reflow at 390px. Firefox was not installed/run; this is targeted compatibility coverage.
- Eighteen additional Light/Dark core-screen axe scans passed with no violations: Users, Plugins, categories, Tools, Import, Export, Privacy, Updates and Add User. All reflowed at 390px. No user creation, plugin activation/update or import/export action was submitted.
- Native keyboard typeahead, Tab/Enter Save and Escape passed, including retained focus after saving and a pointer moved away from the toolbar.
- Native page search, Quick Edit change/save/reopen/restore, bulk Edit/open/cancel, empty search results, Notifications Center filters and General settings save/reload/restore passed. No test content/settings changes were left in place.
- In both modes, switching appearance left the successful native ACF FormData identical. Native Save Draft and explicit reload retained all 19 sections. Actual server validation blocked Publish and revealed the parked error. Media open/cancel passed; no upload/media mutation occurred. The existing truncated-save guard returned HTTP 400 for an incomplete submission.
- Final database readback matched the recorded main-content, complete native ACF and post-content hashes for both the 19-row real and 32-row stress drafts. See [readback evidence](evidence/save-reload-readback.json).
- The emergency switch removed appearance/workspace assets and controls, rejected preference writes and left all 19 native ACF rows. The exact original local configuration was restored.
- AJAX permission checks rejected invalid/missing nonces, invalid/array preferences and unauthenticated requests. No caller-supplied user ID is used. The REST index returned HTTP 200.
- The initial 28 targeted theme axe rows found zero contrast violations after corrections. Dashboard, navigation/hover, appearance control, lists/Quick Edit, core/MRN settings, Notifications Center, workspace and arrangement surfaces passed their owned scans. ACF's existing image uploader reported the same nested-interactive semantics in each mode: four Business Information images and three editor images. Native TinyMCE diagnostic scans also reported existing button-name/nested-interactive findings; its light editing island retains native markup and behavior. These are not reported as passes.
- Six additional MRN layout/icon picker and native publishing-panel axe scans passed. Existing picker behavior and cancel/close worked in both themes. No ACF field was moved or disabled by the appearance implementation.
- Forty semantic palette comparisons passed: normal/muted text, primary/hover, navigation/hover/current, selected, disabled, danger, warning, success, control boundaries and focus. Text uses at least 4.5:1 and control/focus comparisons at least 3:1. Disabled workspace controls use opaque semantic colors.
- Final complete toolbar and navigation axe scans passed in both modes. The existing toolbar indexing warning uses semantic warning colors while retaining its original action; missing image-only branding-link names are supplied without changing AME configuration or the image/destination.
- All 18 representative first-contentful-paint observations had the selected server preference and expected canvas color. Both themes reflowed at 390px; sidebar hover/flyout, expanded current menus, keyboard submenu entry and the mobile navigation were exercised.

### Performance result: qualification remains blocked

The first series used three fresh contexts per screen and actual installed prior source (`b2ab931c`, component 3.2.8), followed by Light and Dark. Startup CPU was captured before forced GC; retained heap after GC. All initial editor samples had four TinyMCE instances.

| Screen | Before CPU median | Light CPU median | Dark CPU median | Before / Light / Dark retained heap |
| --- | ---: | ---: | ---: | ---: |
| Dashboard | 222 ms | 230 ms | 237 ms | 20.34 / 20.33 / 20.34 MB |
| Pages | 187 ms | 239 ms | 202 ms | 18.29 / 18.30 / 18.30 MB |
| Complex editor | 822 ms | 929 ms | 1,187 ms | 25.50 / 25.49 / 25.50 MB |

Retained memory is effectively unchanged in this bounded sample. CPU is **not qualified**: the first series includes slower editor samples, particularly Dark. Six additional editor pairs reversed before/after order; baseline CPU itself ranged from 1,326–2,692 ms, and valid Dark pairs ranged from 9% faster to 58% slower than their paired baseline. One Light sample initialized only one TinyMCE instance and is explicitly excluded; the harness now requires four initialized instances. The inconsistent results do not establish a reliable non-regression result or an improvement. Raw records and exclusions are retained. Final focus/dialog refinements do not resolve this outstanding qualification issue.

Do not release or promote this candidate until controlled performance measurements resolve the CPU concern. No claim is made that the prior Local Hub VM/guest-agent OOM issue was repaired by a browser appearance change. The fixture was healthy during the final checks, but the earlier infrastructure stability issue remains a separate blocker to release qualification.

**31 October follow-up:** all eight installed appearance files still match source, and five representative authenticated screens load successfully. A bounded static-file comparison now reproduces guest-agent socket retention on forwarded requests, independently of WordPress. It strongly matches the installed Lima version's upstream forwarding defect. No runtime repair or upgrade was applied; see the [separate OOM investigation](LOCAL-HUB-OOM.md). Both runtime stability and editor CPU qualification remain open release blockers.

### Scope of MRN QA

The staged commit gate uses the installed Bash 5 runtime after a stalled legacy-shell run. It passes with an advisory missing WP-CLI stub in its isolated temporary snapshot; full component PHPStan passes. Semgrep is skipped because this component has no configured Semgrep rules.

Full component MRN QA covered PHP lint, WordPress coding/security standards, PHP compatibility, PHPStan, secrets/debug scans, API surface and runtime REST health. Authenticated browser/axe/performance checks use the installed MRN QA Engine Playwright/axe dependencies and the named fixture.

Generic unauthenticated public smoke, axe, performance and CWV rows are intentionally skipped by this component's admin-only profile; they would scan the login/public page rather than these workflows. License/fleet/parity, source promotion, release lock, packaging, publication, site-owner remote deployment readiness and remote installed verification were not run: the owner explicitly stopped this work at local review. A component QA pass is not performance or release sign-off.

## Review artifacts

Editor captures wait for four initialized native TinyMCE instances and verify the publishing-toolbar/workspace-header geometry. Full-page images are linked below; `-viewport` images provide readable opening-workspace views. Before images are the actual prior local implementation, not mockups. External requests were blocked consistently in QA; the imported off-origin AME branding image can appear broken. Its configured URL/image was not changed.

| Screen | Before | Light | Dark |
| --- | --- | --- | --- |
| Dashboard | [Before](evidence/before-dashboard.png) | [Light](evidence/light-dashboard.png) | [Dark](evidence/dark-dashboard.png) |
| Complex ACF editor | [Before](evidence/before-editor.png) | [Light](evidence/light-editor.png) | [Dark](evidence/dark-editor.png) |
| Pages | [Before](evidence/before-pages.png) | [Light](evidence/light-pages.png) | [Dark](evidence/dark-pages.png) |
| General settings | [Before](evidence/before-settings.png) | [Light](evidence/light-settings.png) | [Dark](evidence/dark-settings.png) |
| MRN Site Configurations | [Before](evidence/before-config.png) | [Light](evidence/light-config.png) | [Dark](evidence/dark-config.png) |
| Posts | — | [Light](evidence/light-posts.png) | [Dark](evidence/dark-posts.png) |
| Team members CPT | — | [Light](evidence/light-cpt.png) | [Dark](evidence/dark-cpt.png) |
| Users | — | [Light](evidence/light-users.png) | [Dark](evidence/dark-users.png) |
| Plugins | — | [Light](evidence/light-plugins.png) | [Dark](evidence/dark-plugins.png) |
| Categories | — | [Light](evidence/light-categories.png) | [Dark](evidence/dark-categories.png) |
| Privacy | — | [Light](evidence/light-privacy.png) | [Dark](evidence/dark-privacy.png) |
| Core tools | — | [Light](evidence/light-tools.png) | [Dark](evidence/dark-tools.png) |
| Notifications Center | — | [Light](evidence/light-notifications.png) | [Dark](evidence/dark-notifications.png) |
| Business Information | — | [Light](evidence/light-business.png) | [Dark](evidence/dark-business.png) |
| Sidebar hover / flyout | — | [Light](evidence/light-sidebar-hover.png) | [Dark](evidence/dark-sidebar-hover.png) |
| Sidebar keyboard focus | — | [Light](evidence/light-sidebar-keyboard.png) | [Dark](evidence/dark-sidebar-keyboard.png) |
| Mobile expanded navigation | — | [Light](evidence/light-sidebar-mobile-expanded.png) | [Dark](evidence/dark-sidebar-mobile-expanded.png) |
| Arrange sections | — | [Light](evidence/light-editor-arrange.png) | [Dark](evidence/dark-editor-arrange.png) |
| Page settings | — | [Light](evidence/light-editor-settings.png) | [Dark](evidence/dark-editor-settings.png) |
| Validation error | — | [Light](evidence/light-validation.png) | [Dark](evidence/dark-validation.png) |
| Native media dialog | — | [Light](evidence/light-native-media-dialog.png) | [Dark](evidence/dark-native-media-dialog.png) |
| Layout picker | — | [Light](evidence/light-layout-picker.png) | [Dark](evidence/dark-layout-picker.png) |
| Icon picker | — | [Light](evidence/light-icon-picker.png) | [Dark](evidence/dark-icon-picker.png) |
| Publishing panel | — | [Light](evidence/light-publishing-panel.png) | [Dark](evidence/dark-publishing-panel.png) |

See [changed files](CHANGED-FILES.md), [prioritized follow-up](UX-BACKLOG.md) and the JSON/QA evidence beside the images. Local source is intentionally unpublished and stops here for review.
