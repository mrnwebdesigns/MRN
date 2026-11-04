# Annotation log

**Round 3 approved — 3 November 2026:** seven findings (`UX-018`–`UX-024`) from six supplied screenshots; five component clusters. Owner approved all five clusters with “Make these udpates and push to dev”; implementation and exact review-site deployment are authorized, with release/Fleet gates closed. Next ID: `UX-025`. See [batch 005](annotation-batches/005-round3-screen-and-account.json), [batch 006](annotation-batches/006-editor-dashboard-return.json) and [batch 007](annotation-batches/007-updraftplus-icon-light.json).

**Round 2 approved — 3 November 2026:** UX-011–UX-017 / five clusters approved by “Make those udpates, then we can do another round from there.” Source implementation is installed on the authorized protected mrndev.io review copy; native save qualification and performance checks are tracked separately. [Round-2 status](REFINEMENTS-ROUND2.md).

**Round 1 collection complete. Received: 10 annotations in 3 batches. Grouped: 7 refinement clusters. Approved clusters: 7. Source implementation: 7; native Light/Dark review and 19/32 save/reload evidence available; automated/performance qualification open.**

Owner approval, 2 November 2026: “Let's make these changes for now, then another round of annotations.” This authorizes all seven bounded clusters below. The [implementation record](REFINEMENTS-ROUND1.md) separates installed source changes from blocked runtime evidence. No cluster is visually accepted or closed yet.

The first human batch covers the live Dashboard rendered in Light appearance at a reported 1487×1909 viewport. Original marked screenshots are attached to the user's submitted message. Exact wording, selectors, paths and coordinates are retained in [batch 001](annotation-batches/001-dashboard-light.json). No opaque native annotation IDs or local screenshot paths were supplied, so none have been invented. The blue selection rectangles and numbered markers are annotation overlays, not product styling findings.

These are Light observations. Dark-mode consequences are queued for comparison, not claimed as confirmed defects. Existing technical findings in the parent backlog remain separate from these user annotations.

The second batch reports the Universal Sticky Bar covering the Editor Workspace menu during scroll. The [original supplied crop](annotation-batches/002-editor-sticky-overlap.png) and [read-only geometry/hit-test evidence](annotation-batches/002-editor-sticky-overlap.json) are retained. The current inspected state also overlaps at scrollTop 0; no scroll movement was performed to reproduce the transition. Four actual menu-control centers are intercepted by toolbar elements, confirming an interaction blocker in that state.

The [third batch](annotation-batches/003-editor-hierarchy-and-search.json) adds top-of-editor spacing, ACF tabs, nested Cards/repeater hierarchy and a request to search layout content. Its marked Cards screenshot remains attached to the submitted chat message; no local file path was supplied. Top spacing is linked to the existing chrome cluster as a distinct symptom, not marked resolved by the overlap diagnosis. No browser controls were clicked, scrolled, expanded, collapsed or changed to collect this batch.

## Original annotations

| ID | Received / native reference | Original wording | Screen and state | Mode / viewport | Cluster |
| --- | --- | --- | --- | --- | --- |
| UX-001 | 2 Nov 2026 / batch 001, User Comment 1 | “The con is hard to see” | Dashboard toolbar, SEOPress icon beside SEO10 | Light observed / 1487×1909 | UX-C01 |
| UX-002 | 2 Nov 2026 / batch 001, User Comment 2 | “Any ideas what could be better than this floating around?” | Dashboard, closed native Screen Options control | Light observed / 1487×1909 | UX-C03 |
| UX-003 | 2 Nov 2026 / batch 001, User Comment 3 | “Spacing between items is a bit much. The main sections could stand out a little more.” | Main navigation, item spacing and AME section headings | Light observed / 1487×1909 | UX-C02 |
| UX-004 | 2 Nov 2026 / batch 001, User Comment 4 | “This styling seems off” | Dashboard navigation item, current/selected appearance | Light observed / 1487×1909 | UX-C02 |
| UX-005 | 2 Nov 2026 / batch 001, User Comment 5 | “Icon not visible, match color of reset” | Main navigation, Relevanssi image icon | Light observed / 1487×1909 | UX-C01 |
| UX-006 | 2 Nov 2026 / batch 002, supplied screenshot | “On scroll the universal sticky bar covers the menu under it.” | Complex editor 2523, sticky bar covering workspace navigation | Light observed / live client area 1472×1909; attachment is a crop | UX-C04 |
| UX-007 | 2 Nov 2026 / batch 003, User Comment 1 | “Space at the top of the page is off” | Editor chrome and top content spacing | Light context / 1487×1909 | UX-C04 |
| UX-008 | 2 Nov 2026 / batch 003, User Comment 2 | “Tabs need some styling updates” | Native ACF tab row, Export target | Light context / 1487×1909 | UX-C05 |
| UX-009 | 2 Nov 2026 / batch 003, User Comment 3 | “Style a way for sub components stand out. THis page gets really long, ideas?” | Nested Cards repeater inside the selected Stacked Content Section | Light screenshot / 1487×1909 | UX-C06 |
| UX-010 | 2 Nov 2026 / batch 003, User Comment 4 | “Can this also serach the content in the layouts to help get to a layout faster?” | Persistent Page structure search | Light context / 1487×1909 | UX-C07 |

Round 1 ended at `UX-010`; round 2 is recorded below. Next annotation ID: `UX-018`. Original typos remain intact. Triage interprets “con” as the selected icon and “reset” as likely “rest” (matching neighboring icons); those are interpretations, not edits to the feedback. No unsent browser drafts were inspected.

## Refinement clusters

| Cluster | Source annotations | Shared component / mode differences | Priority and reason | Proposed bounded refinement | Approval / status | Targeted regression evidence |
| --- | --- | --- | --- | --- | --- | --- |
| UX-C04 — Sticky editor chrome and top spacing | UX-006, UX-007 | Existing Universal Sticky Bar positioning, Editor Lockdown chrome-offset calculation and Admin UI workspace CSS. Light is confirmed in the inspected state; scroll transitions/Dark/mobile remain pending. | P1, first before visual polish: confirmed navigation obstruction; two intended navigation locations hit publishing buttons. Release blocker until corrected and regression-tested. | Keep WordPress toolbar → Universal Sticky Bar → workspace navigation in a non-overlapping stack using consistent rendered geometry/offsets. Reuse the existing shared offset mechanism for outline, Site menu and jump/focus scrolling. Do not solve it by merely drawing navigation over publishing actions. Preserve ACF form placement and native save/publish behavior. | Approved 2 Nov; candidate implemented; partial native checks pass; qualification open | Top/intermediate/deep scrolling, initial top spacing, resize and wrapped toolbar, desktop/narrow widths, both themes. Menu hit tests must resolve to the intended controls; publishing controls remain reachable. Check outline, jump/focus offsets, media/publishing overlays, native validation and save/reload/truncated-save guard. |
| UX-C01 — Icon visibility | UX-001, UX-005 | Admin UI CSS shell/tokens; toolbar and sidebar icon renderers differ. Light is observed; Dark/default/hover/current/focus remain to be checked. | P2, rank 4: repeated icon legibility problem; promote if a measured accessibility regression is confirmed. | Reuse existing semantic icon colors for the two shell targets. Inspect image/SVG/background formats before selecting a narrow treatment; do not apply broad vendor-interface filters or change AME configuration. | Approved 2 Nov; candidate implemented; partial native checks pass; qualification open | Verify both icon targets in both themes and states, contrast where required, accessible names, badges and unchanged links. |
| UX-C02 — Navigation density, hierarchy and current state | UX-003, UX-004 | Admin UI CSS shared menu spacing/current-state styles; AME owns headings, order and labels. Light Dashboard current state is observed; other screens/themes are pending. | P2, rank 3: daily navigation scanability and unnecessary height. | Reduce item and group gaps while retaining usable targets; strengthen section-heading weight/contrast and tighten heading-to-items spacing. Recommend a simple rounded current row with one consistent accent cue, without the pointed/notched silhouette seen in the capture. Confirm the intended selected treatment during approval. | Approved 2 Nov; candidate implemented; partial native checks pass; qualification open | Desktop/mobile/collapsed navigation, default/hover/current/expanded/focus, submenu keyboard entry, section hierarchy, preserved AME labels/order/links and normal editor navigation. |
| UX-C03 — Page-header utilities | UX-002 | Admin UI CSS native-screen header treatment; WordPress retains Screen Options/Help controls and panels. Light Dashboard is observed; settings/lists/editor variations are pending. | P2, rank 6: detached utility controls weaken page hierarchy. | Recommended: visually group Screen Options and Help in a compact utility area aligned with the page title and content edges. Retain the native labels, panels, focus and toggle behavior. Avoid adding a second settings system or an extra menu interaction just to hide these controls. | Approved 2 Nov; candidate implemented; partial native checks pass; qualification open | Native panel open/close and focus, header alignment on Dashboard/lists/settings, narrow-screen wrapping, reduced motion and unchanged third-party screen ownership. |


| UX-C06 — Nested component hierarchy and long sections | UX-009 | Admin UI workspace CSS and the existing ACF/Card builder row controls. Distinguish nested row headers from the outer section. Light observed; Dark and row states pending. | P2, rank 1: primary complex-page editing friction. | Use clear second-level row headers, subtle nested surfaces/borders, item counts and concise content-based summaries. Reuse native ACF row collapse/open behavior where supported to work on one subcomponent at a time; keep all fields in their original form. Reveal matching/error rows and their native tabs when appropriate, respecting conditional logic. | Approved 2 Nov; candidate implemented; partial native checks pass; qualification open | Nested add/remove/duplicate/reorder/collapse, keyboard/focus, TinyMCE lifecycle, conditional logic, error reveal, search reveal and unchanged serialized fields/save-reload. Avoid a global collapse mechanism across vendor interfaces. |
| UX-C07 — Search authored layout content | UX-010 | Existing Editor Lockdown search/filter and per-layout refresh path. Current source searches layout type plus meaningful title. Applies to both themes; only a Light request supplied. | P2, rank 2: faster discovery of the right layout in long pages. | Extend the current client-side search to authored text, headings, button/link labels and rich-text plain text, including nested rows and unsaved edits. Show a short matched excerpt and component/field context; selecting it should open the relevant existing layout/row/tab and focus the target. Update a bounded per-layout index on relevant field/TinyMCE changes; do not rescan the entire form on each keystroke or persist a second content store. | Approved 2 Nov; candidate implemented; partial native checks pass; qualification open | Saved/unsaved and nested matches, initialized/uninitialized TinyMCE, duplicate/removed/clone rows, no-results/counts, safe text excerpts, keyboard and visible focus, conditional logic, native error reveal, save/reload integrity and 19/32-section CPU/heap checks. No new plugin/API/framework. |
| UX-C05 — Native ACF tab presentation | UX-008 | Admin UI shared ACF/workspace tokens and styles; ACF retains tab activation and field lifecycle. Parent and nested tab rows need related but distinguishable hierarchy. | P2, rank 5: scanability and consistency; escalate measured accessibility regressions if found. | Use a coherent compact tab strip with clear selected, hover and keyboard-focus states, consistent spacing and subtle separation from its content panel. Keep existing labels and native controls; support wrapping/overflow at narrow widths. | Approved 2 Nov; candidate implemented; partial native checks pass; qualification open | Parent/nested tabs in both themes, keyboard/focus, active/hover/disabled/validation states, 390px reflow, conditional fields, TinyMCE and unchanged native serialization. |

Group related Light/Dark comments under one component cluster while retaining distinct symptoms and references. Default status is **Awaiting approval**. Record an explicit owner approval reference before implementation. Conflicting comments stay visible until resolved.

**Historical pre-implementation triage:** read-only source inspection supported a shared menu treatment: shell CSS currently gives menu labels 11px vertical padding, menu links 3px margins, heading links 20px top margins and separators additional margins. Icon color rules explicitly cover pseudo-element glyphs; the Relevanssi target is an `img`, so that rule alone does not tint it. These are implementation leads, not confirmed causes for every observed shape or icon. The native active-state/AME rule interaction and SEOPress icon format still need narrow inspection before an approved edit.

**Historical UX-C04 evidence:** the Universal Sticky Bar occupied y=86–156.99px while the workspace header occupies y=103–157px: a 53.99px vertical overlap over its 54px height. The stored offset is 103px. `updateChromeOffset()` uses bar height plus its computed CSS top, which differs from its current rendered bottom. This identifies an offset/positioning interaction; it does not establish a final repair. Site menu and Back to list centers hit the permalink; Content and Page settings centers hit Publish and View / Publish controls. No click or save was attempted. Keep interaction testing of the covered row paused until an approved fix is verified.


**Historical UX-C07 evidence:** read-only source inspection confirmed `refreshItem()` assigns `item.text = item.type + ' ' + title`, and `filter()` searches that string. Content search is feasible as an extension of the existing navigator, but it is not currently implemented. Results should avoid helper text, internal field IDs and unrelated settings as noisy matches, preserve native conditional logic, and read current editor values rather than just stale saved textarea markup. Results must not inject authored HTML into the review UI.

The proposed order now puts nested section usability and content discovery ahead of cosmetic sidebar/header work, while the confirmed chrome interaction blocker remains first. Cluster IDs remain stable; ranking is a triage recommendation, not approval.

## Review decision

- Round 1 collection complete: **Yes**. Further feedback belongs to round 2, starting at `UX-011`.
- Approved batch: **UX-C01–UX-C07**, direct owner instruction on 2 November 2026.
- Product source changes authorized: **All seven bounded refinements**, local fixture only.
- Implementation status: source written and eight files initially installed with rollback copies; source/installed and complete native data hashes match; native checks pass. Full automated/performance qualification remains open.
- Next action: collect the next annotations from the revised native editor/admin tabs or paired gallery while keeping the remaining qualification gates closed. Do not collect final visual approval against historical screenshots or call this release-ready.


## Round 2 — new-page workflow and toolbar

Received 3 November 2026 against local candidate `4c4ec6e9`. Preserve all seven original comments and target references in [batch 004](annotation-batches/004-round2-new-page-and-toolbar.json). The supplied screenshots show Light appearance; Dark defects are not inferred as confirmed. Original images remain attached in chat. No user tab was navigated, no new draft was created, and no controls/settings/content were changed while collecting this batch.

| ID / source | Original wording | State | Cluster |
| --- | --- | --- | --- |
| UX-011 / Comment 1 | “No way to add sections on a new page” | New page, zero sections, Content focus view | UX-C08 |
| UX-012 / Comment 2 | “Padding so the broders aren't hitting the edges” | New page, Arrange view, empty Hero field | UX-C06 |
| UX-013 / Comment 3 | “I like this, just not the empty space” | New page, Page settings; broad workspace target | UX-C08; spacing cross-reference UX-C04 |
| UX-014 / Comment 4 | “Icon issue” | WPForms sidebar icon | UX-C01 |
| UX-015 / Comment 5 | “Icon issue” | SEOPress sidebar icon, distinct from the previously refined toolbar icon | UX-C01 |
| UX-016 / Comment 6 | “Still not a fan and the top looks broken. WOnder if this becomes part of the universal serial bar or the User Dropdown in the upper right? Thoughts?” | Dashboard Screen Options / header | UX-C03 |
| UX-017 / Comment 7 | “Ths hould be part of the user drop down and simpler with icons” | Open appearance menu | UX-C09 |

### Triage and bounded recommendations — approved 3 November 2026

| Priority | Cluster / owner | Recommendation and evidence | Verification after approval |
| --- | --- | --- | --- |
| P1 — functional blocker | UX-C08 — zero-section onboarding; Editor Lockdown + workspace CSS | Keep each eligible region's existing Add section entry reachable when empty. Show a concise first-section prompt; distinguish a genuinely empty page from a nonempty page with no search matches. Hide unavailable arrange/previous/next controls in the empty state. Current `filter()` hides the group including its Add button; `applyView()` parks regions without an active row, while focused CSS hides native Add actions. Arrange mode reveals them, matching the supplied screenshot. Preserve the approved Content/Page settings structure. UX-013's exact unwanted gap is not established by the broad target; inspect the existing title/tool spacing and empty-state treatment before changing it. | Authorized blank fixture: zero→first section, last section removal→zero, Hero/Content/After Content entry, native layout picker, correct first selection, keyboard/mobile/both modes, conditional eligibility, no-results behavior, native ACF save/reload and incomplete-submission guard. Existing 19/32-section fixture behavior must remain intact. |
| P2 | UX-C06 — component/region insets; Admin UI CSS | Add consistent inner spacing for native empty-builder/arrangement surfaces, preserving nested component hierarchy. Existing root field/inside padding is explicitly zero; do not apply a global ACF padding rule. | Empty/populated Hero, Content and After Content, arrangement handles/Add buttons, native picker, Light/Dark, 390px reflow and unchanged field placement. |
| P2 | UX-C01 — shell icons; Admin UI CSS, AME retains configuration | Extend the renderer-specific visibility audit to WPForms and SEOPress sidebar icons. These are distinct targets from the previous toolbar SEO/Relevanssi fixes. Reuse their actual existing glyphs and semantic colors after inspecting the rendered formats; no broad filters or application-interior styling. | Default/hover/active/focus/expanded/folded states in Light/Dark; contrast, accessible labels, preserved branding and links. Root cause remains unconfirmed for these targets. |
| P2 | UX-C03 — page utilities; Admin UI shell + native WordPress | The previous heading-row placement remains visually unsatisfactory. Recommend one compact, page-specific “Screen” utility in the existing WordPress toolbar near the user menu, opening the native Screen Options/Help panels. Keep native IDs, form placement, listeners and focus behavior. Avoid adding a publishing bar to Dashboard/list/settings screens. Keep page-specific controls distinct from account preferences; placement was subsequently approved with the other round-2 refinements. | Dashboard/list/settings/editor, native panel toggles, native screen-option persistence, keyboard/Escape/focus return, mobile overflow, title/Add Page alignment, no collisions with publishing/workspace bars or vendor-owned screens. |
| P2 | UX-C09 — personal appearance; Admin UI CSS appearance component | Move the existing per-user Light/Dark/System choice into the native user dropdown as a compact labelled sun/moon/system icon group. Keep text labels and selected state; avoid ambiguous icon-only controls. Preserve the current save endpoint, nonce/capability checks, optimistic rollback, System behavior and early theme selection. No extra settings layer. | Mouse/keyboard/assistive names, preference persistence and failed-save recovery, System follow behavior, initial paint/navigation, Light/Dark focus/contrast, mobile user-menu reachability and third-party native islands. |

**Performance-first constraint:** prefer scoped CSS and reuse the existing native controls, reconciliation state, delegated handlers and preference endpoint. Do not add polling, a second editor-content index, startup-wide form rescans, a new framework or another global MutationObserver for these refinements. These are implementation constraints to verify, not performance claims. Keep bytes/requests, startup CPU, long tasks, retained heap and input/search latency visible in matched, fully initialized before/candidate 0/19/32-section measurements. Separate server/forwarding delay from client CPU. Current Local Hub OOM and matched editor CPU blockers remain open, independently of visual approval. No heavy browser benchmark or VM/runtime change was performed merely to collect this feedback.

**Round 2 status:** all five clusters approved by the subsequent owner instruction, source changes implemented and installed on the authorized protected mrndev.io copy; scoped visual/accessibility and account/Screen tests pass, and the explicitly approved exact-site Cloudflare save exception has been applied; native save qualification is being completed. No further annotation batch is approved automatically. Next annotation `UX-018`. ACF definition/storage, Classic Editor, AME configuration, publishing, release and deployment behavior remain preserved in scope. See [implementation status](REFINEMENTS-ROUND2.md).


## Round 3 — account appearance and contextual view options

The four original user screenshots are copied byte-for-byte into
[batch 005](annotation-batches/005-round3-screen-and-account.json), with filenames,
image dimensions and SHA-256 checksums. They show Light appearance; Dark symptoms
remain unverified. Screenshot pixel dimensions are not browser viewport values.
The ambient page is editor fixture 2580; no per-image selectors, opaque annotation
IDs or browser zoom were supplied. No live page controls were changed for triage.

| ID | Original wording | Observation / cluster |
| --- | --- | --- |
| UX-018 | “These could look way better. Appearance is too large, text above is white on white” | Oversized account Appearance choices; UX-C09. |
| UX-019 | Same original comment as UX-018, separately tracked symptom | Reported white-on-white account text above Appearance; UX-C09. Exact contrast/state remains to be measured. |
| UX-020 | “Screen options has padding/spacing issues. Hover issues.” | Screen popover row spacing and hover extending past its edge; UX-C03. |
| UX-021 | Same original comment as UX-020, separately tracked symptom | Expanded native Screen Options panel edge spacing and large workspace overlay; UX-C03. Crop prevents exact geometry attribution. |
| UX-022 | “Screen options don't make sense with the two page design of "Content" "Page Settings" but sometimes the page setting contains the amount of items loaded.” | Contextual display preferences compete with the editor's Content/Page Settings model; UX-C10. |

### Proposed refinements — awaiting approval

| Priority | Shared component / cluster | Bounded recommendation | Verification after approval |
| --- | --- | --- | --- |
| P1 reported legibility, then P2 density | UX-C09 — account appearance; existing Admin UI shell/tokens | Compact single-row Light/Dark/System segmented choices with small icons beside short labels. Reduce surrounding space without shrinking touch/focus targets. Give username, profile and sign-out consistent readable default/hover/focus colors. Preserve native account links and existing per-user appearance endpoint. | Light/Dark/System, actual text contrast, keyboard/pressed/focus/disabled states, 390px reflow, successful persistence and failed-save rollback. No extra network request for opening the menu. |
| P2 | UX-C03 — toolbar Screen controls and native panel | Normalize compact menu rows, panel insets, sizing and contained hover/focus backgrounds. Remove overflow and excessive overlay footprint; preserve Help, Escape, native toggle events and focus return. Inspect the complete account/toolbar CSS cascade before selecting narrow overrides. | Open/hover/focus/close on dashboard, editor, list and settings screens; both themes; no overflow/clipped content at desktop, narrow widths or zoom; publishing and workspace navigation remain reachable. |
| P2 | UX-C10 — contextual view preferences; Admin UI + Editor Workspace/native WordPress | Use a clear “View options” role for per-user display preferences. On lists retain native columns and items per page; on Dashboard retain widget visibility. In the editor, offer only optional workspace/panel visibility that makes sense under Content/Page Settings, identify the affected area, and retire obsolete one/two-column layout controls only for the managed workspace. Required content regions/Add paths remain reachable. Keep page data in Page Settings and Help separately discoverable. | Native list count/column persistence; dashboard widget behavior; editor optional panels mapped to the correct area; 0/19/32 section paths; restoration of previously hidden panels; native fallback when pilot disabled; unchanged form serialization, ACF lifecycle, validation and save/reload. Preserve third-party-owned controls. |

“Amount of items loaded” is interpreted as list-table items per page. It is not
permission to alter ACF row loading, lazy loading or pagination. Any actual
plugin-owned control requires owner-specific inspection before modification.

Source inspection supports the density and workflow leads: the appearance form
is 264px with vertical 56px-minimum choices; the Screen buttons combine 100% width
with padding and no scoped box-sizing; original native panel contents are moved
unchanged behind the toolbar trigger. The workspace independently owns box
visibility and layout. These are leads, not computed-style or performance results.

Performance constraints: favor scoped CSS and existing screen/state handlers;
no polling, new global observer, full-form rescan, second preference store or new
component framework. CPU/save-latency/OOM gates remain open independently of this
batch. No implementation, deployment, release or Fleet approval is inferred.


### Round 3 follow-up — Dashboard return path

**UX-023:** “Dashboard link is gone.” The [original screenshot](annotation-batches/006-editor-dashboard-return.png)
shows the site-name dropdown with Visit Site only, with Site menu and Back to list
below. [Batch 006](annotation-batches/006-editor-dashboard-return.json) retains
original wording, provenance and image checksum. Light is observed; Dark and
keyboard behavior have not been rechecked. No native annotation ID/selector or
browser viewport was supplied, and no live controls were changed during triage.

**UX-C11 — editor return navigation, P2:** propose an explicit **Dashboard** entry
above **Visit Site** in the existing site-name menu. Preserve Back to list and
AME's ownership of navigation order, labels, branding and role visibility; inspect
the existing AME toolbar configuration before implementing a fallback or creating
a duplicate entry. Keep native unsaved-change warnings and same-site admin URLs.
Use the existing server-rendered menu path, without new polling, observers or
network requests. Status: **awaiting approval**, no implementation.

The current workspace source hides the main navigation until Site menu opens it.
[WordPress's core menu function](https://developer.wordpress.org/reference/functions/wp_admin_bar_site_menu/)
adds Visit Site inside admin and Dashboard on the frontend. Together these support
a missing/discoverable return-route problem in the workspace; they do not prove
a configured Dashboard entry was deleted or that access permissions changed.
Verify exact menu configuration and duplicate avoidance during approved work.

After approval, check editor/list/Dashboard routes, both themes, keyboard/focus,
narrow layouts, role visibility, AME configuration and unsaved-edit navigation.
Next annotation: `UX-024`. Existing CPU/save-latency/OOM release blockers are unchanged.


### Round 3 follow-up — UpdraftPlus icon

**UX-024:** “White icon still.” The [original Light screenshot](annotation-batches/007-updraftplus-icon-light.png)
shows a nearly white UpdraftPlus glyph on the light sidebar, alongside readable
link text. [Batch 007](annotation-batches/007-updraftplus-icon-light.json) preserves
original wording and image provenance/checksum. The ambient screen is the Pages
list; exact viewport, hover state, renderer and Dark appearance remain unverified.

Group with **UX-C01 — shell icon visibility**, alongside UX-001, UX-005, UX-014 and
UX-015. This confirms the backup-plugin icon already noted in the implementation
record. **P2:** match the existing semantic sidebar icon color, preserving the
recognizable glyph, destination, accessible label and AME configuration. Inspect
the asset/renderer before choosing a narrow treatment; no global image filters
or third-party application restyling. Avoid extra requests, observers or scans.

After approval, verify Light/Dark default/hover/focus/active/expanded/folded states.
The visible text labels the control, so an essential non-text contrast failure
is not presumed from the crop alone. Status: **awaiting approval**; no product or
runtime change. Next annotation: `UX-025`.


**Round 3 approval:** “Make these udpates and push to dev,” 3 November 2026. This supersedes the historical awaiting-approval status for UX-018–UX-024 / UX-C01, UX-C03, UX-C09, UX-C10 and UX-C11. Exact destination: admin-editor-pilot.mrndev.io. No production, release or Fleet authority is added.


**Round 3 installed:** UX-018–UX-024 are implemented on the exact authorized dev review site (candidate `4dd60f23`), with [before/after evidence and checks](REFINEMENTS-ROUND3.md). Human visual acceptance remains pending. Native per-page POST is blocked by Cloudflare; other targeted preference checks pass. No production/Fleet action or new WAF change. Next annotation: `UX-025`.

### Round 4 — native navigation and list density

Standing owner approval: “As I send feedback, consider approval to implement
them and deploy to test after qa.” This supersedes the earlier collection-only
approval language for subsequent feedback. Scope remains the dedicated review
site; no production, release publication or Fleet authority is added.

- **UX-025 / UX-C02, P2:** “Secondary active menu looks to close in styling to
  the primary.” Primary retains its filled highlight; the indented current
  submenu uses an underlined label without a competing fill. Installed source
  `162a1d8d`; [Light/Dark, keyboard and mobile evidence](menu-hierarchy-20261104/REVIEW.md).
- **UX-026 / UX-C12, P2:** “In the stack in media, we build this is a dropdown,
  lets replicate that anywhere these occure as they also get messed up with long
  horizontal table data.” Compact Actions menus preserve original native controls
  and remain above clipped tables. Source `74532ad7`; [scope, native Quick Edit,
  accessibility and overflow evidence](row-actions-20261104/REVIEW.md).

Both share Light/Dark tokens and await the next human annotation round. No new
configuration owner or dependency. Next annotation: `UX-027`.

**UX-026 accepted:** “This should be a reusable component FYI, looks great.”
The owner approves the row-actions appearance and requires shared reuse. The
[existing Admin UI contract](../../../../mu-plugins/mrn-admin-ui-css/ADMIN_UI_CONTRACT.md#reusable-row-actions-component)
now documents the native markup interface, central ownership, supported-screen
scope, progressive fallback and reuse verification. No new renderer, API,
dependency or runtime change is needed. This visual acceptance does not clear
the independent performance or release gates, or imply acceptance of UX-025.

### UX-027 / UX-C10 — Page Settings visibility regression

“Page settings lost all of it's boxes?” New-page screenshot and installed DOM
confirm hidden optional settings panels plus an empty sidebar gap. **P1** root
cause: core's `:hidden` preference serialization includes panels parked by the
workspace. Source `35b01869` preserves explicit checkbox choices in the existing
native save path, removes the artificial sidebar minimum height and restores the
five affected review-account panels after verified backup. [Reproduction,
regression and installed evidence](page-settings-20261104/REVIEW.md). Keep the
user's unsaved new page intact. Human recheck pending; next annotation `UX-028`.

### UX-028 / UX-C05 — Clean horizontal tab borders

“Clean up all the top and bottom borders acrosss the board on tabs.” **P2**.
ACF's native list border, workspace wrapper rule, per-tab borders and the first
field's partial-width rule overlapped. Shared Admin UI CSS now uses one active
underline, no tab perimeter or wrapper rule, and a separate visible focus ring.
Parent/nested ACF tabs and supported native MRN settings tabs share tokens.
Source `1d0f6c68`; [visual, accessibility and timing evidence](tab-borders-20261104/REVIEW.md).
The workspace structure, native fields and third-party application ownership
remain intact. Next human annotation round pending; next ID `UX-029`.

### UX-029 / UX-C01 — Native ACF number/grip visibility

“That pill is from ACF, is it normal to be a pill till hover or touch?” **P2**.
The number-to-grip swap is native ACF behavior. Native active-layout colors left
the number nearly white on the MRN header. Shared semantic tokens now preserve
readable numbers and the native grip; Light/Dark contrast and hover checks pass.
[Installed evidence](section-orientation-20261104/REVIEW.md).

### UX-030 / UX-C06 — Orientation within long Content sections

“For sections inside content, this is still cumbersome and you get lost.
Possible seperation with zebra stripes? Maybe a stick and box scrolls over box
as you go?” **P2**. Implemented a compact sticky native section heading plus
alternating visible field-row and nested-card surfaces, sharing Light/Dark
tokens. Paired fields stay together. One document scrollbar; native forms and
controls remain in place. Mobile, short windows and Arrange sections retain
normal scrolling. Existing focus handling clears the heading.

Source `6a54037e4f25` is installed on the exact review site after source/runtime
QA and fresh remote backup. [Checks, performance limits and captures](section-orientation-20261104/REVIEW.md).
The user's unsaved new page is preserved. Human visual acceptance remains open;
CPU/OOM and release gates are unchanged. Next annotation: `UX-031`.

### UX-031 / UX-C10 — Remove empty Page Settings grid space

“No white space.” **P2**. The supplied Page Settings screenshot shows large
holes beneath short panels in equal-height grid rows. Native CSS column flow now
packs whole cards with 16px separation, preserving DOM/form order and one-column
mobile layout. One existing native Publish-footer Dark contrast defect found in
baseline QA was corrected with existing semantic tokens. Source `d822de61c747`
is installed after QA and a verified remote backup. [Evidence](settings-flow-20261104/REVIEW.md).
The existing editor tab remains untouched; a fresh review tab is open. Human
acceptance is pending; next annotation `UX-032`.

### UX-032 / UX-C06 — Pause sticky sections; restore collapsed nested items

“Not 100% what I had in mind with the scrolling. Comment that work out, bring
back the collapsed sub sections.” Owner clarification: **“Nested Cards/FAQ
items start collapsed.”** **P2**. The sticky heading/scroll adjustment is now
commented out. Existing nested rows start collapsed once, preserve explicit
expansion on revisit, and use native ACF controls. Newly added rows stay open;
validation reveals hidden items. Main section navigation and field bands remain.
Source `8106b4fbd6a7` is installed on the review site after scoped QA and a fresh
verified backup. [Evidence](subsections-20261104/REVIEW.md). This supersedes the
sticky portion of UX-030. Next annotation: `UX-033`.

### UX-033 — Give Media a focused workspace

“Have media not use the wordpress side bar just like content.” **P2**.
Installed on the authorized review site: Library list/grid and Add Media open
full width; **Site menu** in the toolbar reveals the original AME navigation.
Folders and native media controls remain available. Mobile wrappers stack and
wrap instead of squeezing the library. Light/Dark, keyboard, native dialog,
responsive and fallback checks pass. Startup layout shift was reduced before
installation; broader CPU/OOM/release gates remain open. Source `976258a2` plus
`d6ccdacd6661`; [installed evidence](media-workspace-20261104/REVIEW.md).
A fresh Codex tab is ready for annotations. Human acceptance pending.
Next annotation: `UX-034`.

### UX-034 — Center heading label and caret

“Center text and carrot.” **P2**. Native AME section labels and carets now center
as one group horizontally and vertically. Shared CSS covers Light/Dark, regular
sidebar, mobile and Media drawer; native disclosure and menu configuration remain
unchanged. Source `c4cd1947e5a4` installed after scoped QA and fresh verified backup.
[Evidence](heading-align-20261104/REVIEW.md). Fresh Codex dashboard is ready for
review. Human acceptance pending; next annotation `UX-035`.

### UX-035 — Fix oversized and displaced Media drawer icons

Owner screenshot shows WPForms covering its submenu and displaced/duplicate
SEO/Relevanssi icons. **P1**. The drawer now anchors masks to their own holders;
the duplicate source image is hidden. Related mobile wrapper visibility and
submenu overlap conflicts were corrected in the same scoped CSS. Candidate and
installed checks pass; existing WPForms Light label contrast remains separate.
Source `82850e991d29`; [evidence](media-icons-20261104/REVIEW.md). Installed after
scoped QA and verified backup. Fresh Codex Media tab ready; human acceptance
pending. Next annotation: `UX-036`.

### UX-036 — Left-align headings through AME ownership

“Left justified unless this is a styling I can achieve in AME.” **P2**.
AME has Title → Alignment in its heading style editor. Removed MRN's horizontal
centering/title overrides, restoring the existing native left alignment while
preserving vertical caret centering. This supersedes UX-034's horizontal choice.
Source `fcd00a2aa5b7` installed after scoped QA and verified remote backup.
[Evidence](heading-owner-20261104/REVIEW.md). Both themes, native disclosure,
mobile and Media drawer pass. Human acceptance pending; next annotation `UX-037`.


### UX-037 — Media uses the shared Actions component

“Update to our shared component.” **P2**. Media list now uses the same initializer
and extracted shared stylesheet as the native/MRN lists. Original action nodes,
nonces, handlers and form/row ancestry remain intact. Light/Dark, keyboard/focus,
mobile, native Copy URL, Pages regression, fallback and exact asset scoping pass.
Source `8bd54ff425dd`, installed to the dedicated review host after MRN source QA
and a verified remote backup. [Evidence](media-actions-20261104/REVIEW.md).
Codex Media tab refreshed with the dropdown open. Human visual acceptance pending;
next annotation `UX-038`. Existing editor CPU and Local Hub OOM gates remain open.


### UX-038 — Visible admin assembly during navigation

**P1, implemented and installed for review.** Replaced the editor's visible native-to-workspace transition with a brief themed opening state and an 8-second fail-open fallback; toolbar geometry is measured on insertion. Native list headings, selection feedback and shared Actions reserve their final space before enhancement. Native ACF lifecycle/values, keyboard, scoped axe, Light/Dark, mobile and failure recovery checks pass. [Evidence](admin-load-20261104/REVIEW.md). Existing CPU/OOM release blockers stay open. Await human visual acceptance; next annotation `UX-039`.


### UX-039 — Remove the active section-heading border

**P2, implemented and installed for review.** The heading outline now uses `:focus-visible`, removing the pointer-selection rectangle and retaining the keyboard focus indicator. Light/Dark pointer and keyboard checks, source QA and served-file hash verification pass. [Evidence](section-focus-20261104/REVIEW.md). Next annotation: `UX-040`.

### UX-040 — Use the editor navigation pattern for Media

“I like this over the top dropdown you added for media.” **P2, implemented and installed for review.** Media now shares the editor's Site menu bar below the WordPress toolbar, with Dashboard/Back to library navigation. AME owns the original drawer contents; the shared row Actions component remains unchanged. Both themes, keyboard, mobile, native media controls, editor regression and fallbacks pass. Source `253be2e2`; [evidence](media-navigation-20261104/REVIEW.md). Fresh Codex Media tab ready for annotations. Await visual review; next annotation `UX-041`.
