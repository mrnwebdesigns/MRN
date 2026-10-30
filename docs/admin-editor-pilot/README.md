# MRN Local Admin Editor Pilot

**Historical W0–W4 report. Actual MRN editor review found the experience insufficiently differentiated. The current implementation and review evidence are in [the workspace revision](revision/README.md).**

The approved W0–W4 sprint implements the editor pilot with existing MRN components. The demonstration runs only in the isolated Local Hub fixture. This version passed technical checks but did not meet the editor experience objective; W5–W9, Fleet publication and remote deployment remain outside this authorization.

## Demonstration

In Local Hub, select **admin-editor-pilot** and use **Login to Admin**. Open the [real page copy](https://admin-editor-pilot.localhost/wp-admin/post.php?post=2523&action=edit), then choose **Sections** in the WordPress toolbar. Search **More From**, jump to the last section, and use **Return to sections**. At smaller widths, use the floating **Page sections** button.

The source is the existing Trilliant Local Hub snapshot’s published Design Layouts Page, copied to a draft with 19 top-level sections. It contains nested layouts, FAQ/card repeaters, rich text and media. The [32-section stress copy](https://admin-editor-pilot.localhost/wp-admin/post.php?post=2524&action=edit) repeats the first 13 source sections after the original 19; it is deliberately derived test content, not a second real client page. A separate local page, 2540, records the new-page publishing check.

The fixture uses WordPress 7.0.4, ACF Pro 6.8.9 and PHP 8.4. External WordPress HTTP requests, cron and outgoing WordPress mail are blocked in the clone. Uploaded files are referenced through the source fixture’s uploads symlink; tests only opened/canceled media selection and never uploaded, replaced or deleted source media. No shared development, staging or production environment was changed.

## Implemented behavior

- Collapsed rows show the existing layout type and a separate meaningful title. Native renamed titles take precedence, followed by internal name, heading, label and the native layout title. Pipe-delimited developer hints are omitted from the displayed type.
- The collapsible section outline searches type and title, shows order and the section containing keyboard focus, opens native collapsed layouts, and returns focus through ordinary buttons and Escape. It follows native ACF additions, duplication, removal, reordering and renamed titles.
- Native validation errors reveal their layouts and appear as **Needs attention** entries with an error filter. Desktop errors expose the index without stealing ACF’s field focus. Smaller screens retain a visible error count on the navigation button and close the panel when a target is selected.
- Neutral light surfaces, readable row hierarchy, visible focus and a light publishing bar are scoped to the pilot. Toolbar actions wrap on small screens; navigation measures the existing toolbar and scrolls section headings below it.
- Contextual notices retain their original links and controls. The two identified GTranslate review/upgrade promotions are hidden; unknown, conflict, debug and actionable warning notices are retained.

ACF fields remain in their original form and DOM order. The navigator has no submitted field names, save handler, ordering store or content store. Existing ACF tabs, native controls, conditional-logic attributes, field keys, picker ownership and rendering templates are unchanged. There is no new plugin, framework, provider registry, schema or generalized configuration system.

## Components and changed files

| Component | Candidate version | Responsibility |
| --- | --- | --- |
| Editor Lockdown | 1.0.35 | Local/screen/capability gates, native toolbar entry and ACF navigation behavior |
| Admin UI CSS | 3.2.5 | Scoped pilot presentation; excludes the pilot form from global cleanup/clipping rules |
| Dashboard Support | 1.3.1 | Keeps pilot editor notices in context rather than flattening them into Notifications Center |

Product changes:

- `mu-plugins/mrn-editor-lockdown/mrn-editor-lockdown.php`
- `mu-plugins/mrn-editor-lockdown/includes/section-navigator.php`
- `mu-plugins/mrn-editor-lockdown/assets/section-navigator.js`
- `mu-plugins/mrn-admin-ui-css/mrn-admin-ui-css.php`
- `mu-plugins/mrn-admin-ui-css/mrn-editor-workspace.css`
- `mu-plugins/mrn-dashboard-support/mrn-dashboard-support.php`
- The three components’ `README.md` and `stack.lock` files.

Test and review files are the `section-navigator-*` scripts under Editor Lockdown’s `tests/runtime/`, this report and its `evidence/` directory. `outputs/admin-editor-pilot/.gitignore` keeps working scripts and diagnostics out of Git. No Config Helper, base-theme, ACF definition, frontend rendering or distribution manifest source was changed.

## Screenshots

[Before editor](evidence/before-editor.png) · [Before sections](evidence/before-sections.png) · [After sections](evidence/after-sections.png) · [After navigator](evidence/after-navigator.png) · [Validation discovery](evidence/after-validation.png) · [Mobile](evidence/after-mobile.png) · [Reflow](evidence/after-reflow.png)

## Editing and performance measurements

The final timing comparison uses six adjacent baseline/pilot pairs per page, alternating order in the same warm Chromium context at 1440 × 1000. External requests were blocked. These are local VM measurements, not a human usability study or Fleet capacity qualification.

| Page | Baseline load median | Pilot load median | Baseline client DOM readiness | Pilot client DOM readiness | Baseline long-task total | Pilot long-task total |
| --- | --- | --- | --- | --- | --- | --- |
| 19-section real copy | 2.611 s | 2.496 s | 278.0 ms | 276.0 ms | 175.0 ms | 173.5 ms |
| 32-section derived copy | 2.848 s | 2.775 s | 432.7 ms | 428.8 ms | 391.0 ms | 391.0 ms |

Client DOM readiness subtracts the document response end, reducing the effect of server-response variation. The alternating runs did not reproduce the startup slowdown seen in the initial unpaired 19-section run. No load-speed improvement is claimed. The initial and paired raw samples are both retained.

| Task state | Baseline manual wheel actions | Pilot manual wheel actions | Baseline scripted task | Pilot scripted task |
| --- | --- | --- | --- | --- |
| 19 sections, collapsed | 2 | 0 | 0.168 s | 0.153 s |
| 19 sections, expanded | 75 | 0 | 4.145 s | 0.129 s |
| 32 sections, collapsed | 3 | 0 | 0.270 s | 0.223 s |
| 32 sections, expanded | 115 | 0 | 6.309 s | 0.165 s |

The scripted task locates the known last section using 800px wheel increments in the baseline and search/jump in the pilot. It includes no reading or decision time. The pilot performs an automatic document jump; “zero” means no manual wheel gestures, not no document movement. The separate keyboard jump assertion completed in about 39 ms. Usability percentages remain targets for a human review, not acceptance gates.

Chrome’s approximate heap medians were 35.1 → 37.3 MB for 19 sections and 42.1 → 43.4 MB for 32. These coarse, GC-sensitive values show modest added heap; retained-memory profiling is still required before promotion. The two new plain JS/CSS assets total about 18 KB uncompressed and add no library dependency.

See [paired timing samples](evidence/paired-metrics.json), [initial baseline samples](evidence/before-metrics.json), [initial pilot samples](evidence/after-metrics.json), [baseline task results](evidence/before-tasks.json) and [final pilot task results](evidence/after-tasks.json).

## Save and regression evidence

The feature-disabled native editor established the save baseline. After native section-name, nested FAQ question and TinyMCE edits, Save Draft, explicit reload and restoration through the form, both fixture pages exactly matched that baseline for all raw ACF fields, main Flexible Content data, WordPress `post_content`, row counts and draft status. See [stored readback](evidence/content-readback.json), [baseline](evidence/baseline-content.json) and [comparison](evidence/save-integrity-comparison.json). A [final readback](evidence/final-content-readback.json) after the last browser checks matches again.

The imported snapshot normalizes an empty taxonomy selector to its existing `category` default and serializes the opened FAQ rich-text fields through native TinyMCE. Both behaviors also occur with the pilot disabled. The baseline includes those native changes; it does not pretend the imported byte representation is immutable under the existing editor.

The snapshot predates the current Stack save guard. The fixture loads the existing `editor-save-integrity.php` unchanged through a test-only hook in both modes. A native form submission with its completion marker removed returned HTTP 400, and the subsequent content readback matched the baseline. The pilot does not modify that guard or its nonce/capability checks.

| Runtime check | Result |
| --- | --- |
| 19 real sections and 32 derived sections indexed | Passed |
| Search, keyboard activation, jump, return, Escape and focus restoration | Passed |
| Jump heading visible below the publishing bar at desktop and 390px | Passed |
| Native duplicate, add, remove and sort lifecycle updates the index | Passed |
| Discarded row operations leave stored rows unchanged | Passed |
| Native server validation blocks publishing; error discovery/correction | Passed |
| Native image field media selection opens and cancels | Passed |
| Nested FAQ question and TinyMCE edit/save/reload | Passed |
| Complete 32-section native save/reload | Passed |
| Native motion-settings conditional hide/reveal | Passed |
| Existing incomplete-submission guard | HTTP 400; stored content unchanged |
| New native page with two ACF layouts publishes/reloads | Passed, local page 2540 |
| Editor role on permitted page; subscriber denied edit form | Passed |
| Pilot JavaScript unavailable | Native 32-row form remains available |
| Media admin exclusion | Pilot assets absent |
| Emergency disable and non-local hostname guard | Passed |
| Axe WCAG A/AA on owned navigator and publishing bar | Zero violations on desktop and mobile |
| 200% equivalent 720px CSS viewport | Panel remains within viewport |

The native Save Draft path intentionally permits incomplete drafts. Validation enforcement was tested through Publish. A test-only server sentinel supplied the validation error; no required-field definitions were changed. Native PHP save guards remained active in both paths.

MRN QA Engine ran full component static analysis for all three changed components, including PHP lint, PHPCS security/best-practice checks, PHP compatibility, PHPStan, secrets/debug scans, whitespace checks and `/wp-json/` runtime smoke. All three reports pass: [Editor Lockdown](evidence/qa-editor.md), [Admin UI CSS](evidence/qa-admin-ui.md), [Dashboard Support](evidence/qa-dashboard.md). Their profiles deliberately skip generic public-page browser/accessibility/performance rows; the authenticated scripts above cover this editor instead. CWV was skipped because frontend output is unchanged. Fleet parity, packaging, publication, site-owner remote deploy checks and full release signoff were not run in this local pilot.

## Compatibility limits and review items

- This qualification is one imported Local Hub site and the named WordPress/ACF versions. WooCommerce, Tutor LMS, WPForms applications, other themes, other ACF releases, actual browser zoom and assistive-technology user testing are not qualified by it. Broad plugin compatibility belongs to a separately approved sprint.
- The fixture’s real page has 19 top-level sections. The 32-section result uses a derived stress page. Human task times and improvement percentages remain measurement targets for owner/editor review.
- Native taxonomy defaults and TinyMCE serialization are inherited behavior. The pilot introduces no additional stored-field drift in the completed comparison.
- The current source save guard must be present before a later distribution is qualified. The older imported runtime alone did not establish that protection.
- AME’s blanket **Hide ALL admin notices** setting was not active in this fixture. Its precedence on another configuration remains unqualified; no speculative ownership/provider system was added.
- The active-section marker follows editing focus, rather than attempting to infer the section from viewport position. Native drag controls remain unchanged; custom keyboard reordering and focused-workspace editing are outside W0–W4.
- Alternating timing samples show no reproducible startup slowdown. Approximate heap increased modestly; retained-memory profiling remains a promotion/release review item. This pilot does not establish full release readiness.

## Disable and local recovery

There is one product flag: `MRN_EDITOR_EXPERIENCE_ENABLED`. It defaults off and also requires a `.localhost` home URL, Classic Editor, ACF, an MRN singular-shell type and normal editing capability. The only enabled fixture is **admin-editor-pilot**.

For emergency disable, set the constant to `false` or remove it in this fixture’s `wp-config.php`, allow PHP’s configuration cache to refresh and reload the editor. The browser check confirmed that pilot JS/CSS, toolbar entry and navigation disappear while all 19 native rows and the existing save guard remain. A true constant was rejected when the home hostname was temporarily filtered to a non-local value within a CLI test.

The fixture’s private backup directory contains the original three component files. The versioned fixture helper can reset the copied real page from its untouched source page 1189. Session files, database dumps and fixture credentials stay outside Git. Use Local Hub’s existing **Login to Admin** mechanism; no credentials or permanent login bridge are included in the review evidence.

## Recommendation for the next sprint

Review the real page’s collapsed summaries, navigator, validation flow and small-screen publishing controls with an MRN editor first. Capture human find/edit/return task times against the native baseline and approve the interaction pattern before W5–W9.

The next bounded proposal should retain the native ACF form, extend lifecycle/conditional-logic and accessibility coverage on a second authorized Local Hub fixture, complete retained-memory profiling, and only then consider native section-management affordances. Broad admin styling, focused drawers/modals, new provider contracts and Fleet rollout should remain separately reviewed work. No W5–W9 work has begun.
