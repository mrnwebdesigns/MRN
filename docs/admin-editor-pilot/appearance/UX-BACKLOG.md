# Prioritized follow-up

**Round 1 approved — 2 November 2026.** All ten annotations / seven clusters were approved by the owner's “Let's make these changes for now” instruction. Source changes are implemented in the existing task worktree; native Light/Dark visual and save/reload checks are complete for the selected fixtures; full automated/performance qualification remains open. See [the implementation and verification record](review/REFINEMENTS-ROUND1.md). No cluster is visually accepted or closed. [Round 2 paired annotations](review/round-2.html) are ready without WordPress login; this is not release sign-off.

## Round 3 — installed on dev; awaiting visual review (3 November 2026)

Seven findings `UX-018`–`UX-024` are preserved with six original images in
[batch 005](review/annotation-batches/005-round3-screen-and-account.json),
[batch 006](review/annotation-batches/006-editor-dashboard-return.json) and
[batch 007](review/annotation-batches/007-updraftplus-icon-light.json).
[Detailed triage](review/ANNOTATIONS.md#round-3--account-appearance-and-contextual-view-options)
groups them into five bounded refinements:

1. **P1 reported text legibility / P2 density — UX-C09:** readable account/profile
   states and compact horizontal Light/Dark/System controls. Measure the reported
   white-on-white state; Light evidence does not establish a Dark defect.
2. **P2 menu/panel geometry — UX-C03:** consistent row/inset sizing, contained
   hover/focus backgrounds and a restrained Screen Options panel footprint.
3. **P2 workflow — UX-C10:** contextual View options. Preserve native list columns
   and items-per-page, Dashboard widgets and meaningful optional editor panels;
   reconcile legacy box/layout choices with Content/Page Settings. Page data and
   personal display preferences remain distinct; required ACF regions stay usable.

4. **P2 navigation — UX-C11:** explicit Dashboard return entry in the existing
   site-name dropdown alongside Visit Site; preserve Back to list, AME ownership,
   permissions and native unsaved-change warnings. No new runtime workload.

5. **P2 icon visibility — UX-C01 / UX-024:** UpdraftPlus still uses a nearly white
   glyph on the light sidebar. Match semantic shell icon colors using a narrow
   renderer-specific treatment; preserve the glyph, AME configuration and vendor
   interface. Confirm both themes/states without adding runtime work.

All five are **approved** by “Make these udpates and push to dev” on 3 November. Prefer scoped CSS/existing events; no new
startup scanning, polling, observer or preference system. Implementation is installed on the exact review host. [Evidence and open checks](review/REFINEMENTS-ROUND3.md); [next paired review](review/round-4.html). No cluster is visually accepted automatically. Next annotation: `UX-025`.

## Round 2 — installed on protected mrndev.io; functional checks passed; performance open (3 November 2026)

Seven comments (`UX-011`–`UX-017`) are preserved in [batch 004](review/annotation-batches/004-round2-new-page-and-toolbar.json) and [triaged in the annotation log](review/ANNOTATIONS.md#round-2--new-page-workflow-and-toolbar). The owner approved all five clusters with “Make those udpates, then we can do another round from there.” Source changes are installed on the authorized mrndev.io review copy. Scoped both-theme visual/accessibility, account preference and native Screen panel checks pass. The approved exact-site Cloudflare exception is applied; 19/32-section save/reload and incomplete-save rejection pass. Slow native saves remain a performance blocker. Live Codex sign-in is verified after the owner-approved extra HTTP-gate removal; WordPress authentication remains intact. [32 paired captures](review/round-3.html) remain available for comparison. [Current implementation/check status](review/REFINEMENTS-ROUND2.md). Performance and usability lead the work; the approved workspace structure is preserved.

| Priority | Cluster / comments | Approved change and verification |
| --- | --- | --- |
| P1 functional blocker | UX-C08 / UX-011, UX-013 | Restore reachable Add section paths on a zero-section page, use a useful empty state, remove unavailable controls and inspect avoidable new-page gaps. Editor Lockdown 1.0.42 routes to native bottom append; the installed picker and complete 0→1→0 save/reload now pass. Human visual acceptance remains open. |
| P2 | UX-C06 / UX-012 | Consistent empty/arrangement region insets; preserve native nested fields and controls. |
| P2 | UX-C01 / UX-014, UX-015 | Diagnose and narrowly correct WPForms/SEOPress sidebar glyphs in both themes; preserve AME configuration. |
| P2 | UX-C03 / UX-016 | Replace the unsatisfactory detached header utilities with one page-specific Screen utility in the existing toolbar, retaining native panels. Implemented and native open/focus/Escape checks pass in both modes. |
| P2 | UX-C09 / UX-017 | Compact labelled Light/Dark/System icons in the user dropdown, using existing user-meta save/first-paint behavior. |

Prefer CSS and existing state/event paths. No new polling, startup-wide field rescans, global observer, dependency or settings system. Qualify matched startup CPU/long tasks/heap and input latency on authorized 0/19/32-section fixtures before claiming performance acceptance. OOM/CPU remain separate release blockers; annotation collection does not clear them.

## Human refinement queue — implemented, awaiting visual review

| Priority | Cluster / references | Material change | Remaining gate |
| --- | --- | --- | --- |
| P1 | UX-C04 / UX-006, UX-007 | One fixed publishing/workspace stack, measured heights and a single content spacer; matching outline/jump/focus offsets. | Nine Light width/scroll hit cases and native saves pass. Dark narrow/overlay cases and human top-spacing review remain. |
| P2, rank 1 | UX-C06 / UX-009 | Named nested block/repeater headers, item counts, native ACF row collapse/expand controls and component surfaces. | Native add/remove/sort/duplicate, TinyMCE, conditional logic, validation and serialization tests. Table repeaters retain native column layout. |
| P2, rank 2 | UX-C07 / UX-010 | Lazy per-layout authored-content index; current text/rich text, contextual excerpts and native row/tab reveal. | Saved rich-text and unsaved nested-text search/focus pass. Unsaved Visual TinyMCE, full lifecycle matrix and matched CPU/heap comparison remain. |
| P2, rank 3 | UX-C02 / UX-003, UX-004 | Denser rows, stronger AME group headings and rounded current-state treatment without pointer pseudo-elements. | Both-mode captures, Light hover/focus and Dark keyboard submenu checked. Dark hover/mobile and human density review remain. |
| P2, rank 4 | UX-C01 / UX-001, UX-005 | Narrow alpha masks retain the SEOPress and Relevanssi glyphs using semantic shell colors. | Computed contrast, shape and visibility in both themes/states; vendor/AME configuration unchanged. |
| P2, rank 5 | UX-C05 / UX-008 | Compact parent/nested ACF tab strips with clearer selected, hover and focus states. | Live native activation, wrapping and conditional/validation behavior. |
| P2, rank 6 | UX-C03 / UX-002, UX-016 | Round-1 title-row placement superseded by the approved round-2 Screen toolbar menu. | Both-theme native panel/focus/Escape checks pass; human review remains. |

Round 2 contains `UX-011`–`UX-017`; round 3 is recorded above. Retain original references and group Light/Dark symptoms by shared component. The owner's later standing instruction authorizes feedback implementation and test deployment after QA; production, release and Fleet gates remain closed.

Round 4: **UX-025 / UX-C02** primary/submenu distinction and **UX-026 / UX-C12**
compact native row-action dropdowns are implemented on the dedicated test site,
with targeted Light/Dark, keyboard/mobile and native-workflow checks. UX-026 was
visually accepted with the explicit requirement to remain a reusable component;
its native markup interface and central ownership are documented in the shared
Admin UI contract. UX-025 human acceptance remains pending. See [menu hierarchy](review/menu-hierarchy-20261104/REVIEW.md)
and [row actions](review/row-actions-20261104/REVIEW.md).

**P1 UX-027 / UX-C10:** Page Settings panels were accidentally persisted as hidden
by native View-option saves while Content was active. The bounded compatibility
fix preserves explicit checkbox choices; five review-account panels are restored
and the empty sidebar gap is removed. [Evidence](review/page-settings-20261104/REVIEW.md).
Await the owner's visual recheck. Next annotation: `UX-028`.

**P2 UX-028 / UX-C05:** Consolidate horizontal tab borders. Shared parent/nested
ACF and supported native MRN settings tabs now use one active underline without
stacked rules or boxed edges. [Verification](review/tab-borders-20261104/REVIEW.md).
Await the next visual annotation round. Next annotation: `UX-029`.

**P2 UX-029 / UX-C01 and UX-030 / UX-C06:** Native ACF number/grip contrast,
alternating visible field rows and nested-card surfaces are installed on the
review site. The sticky section experiment was subsequently disabled by UX-032. Both-theme, mobile/short-window,
keyboard, native-tab and form-value checks pass. Bounded timings show no
consistent added slowdown, but are noisy and do not clear CPU/OOM qualification.
[Evidence and next visual review](review/section-orientation-20261104/REVIEW.md).
Await owner feedback on orientation and stripe strength. Next annotation: `UX-031`.

**P2 UX-031 / UX-C10:** Remove the large blank grid-row gaps in Page Settings.
Installed native CSS columns retain whole panels, 16px separation, mobile reflow
and DOM/form order. Existing Dark Publish-footer contrast is corrected. Both
fixtures and themes pass scoped checks; [evidence](review/settings-flow-20261104/REVIEW.md).
Await the next visual review. Next annotation: `UX-032`.

**P2 UX-032 / UX-C06:** Owner rejected the sticky section direction and clarified
that nested Cards/FAQ items should start collapsed. The sticky CSS and focus
adjustment are commented out; existing items start compact once while opened
and newly added items remain editable. Native keyboard/group controls,
validation reveal and form-value checks pass in both themes. [Installed evidence](review/subsections-20261104/REVIEW.md).
Await visual review; next annotation `UX-033`.

**P2 UX-033:** Media Library list/grid and Add Media now use full width, with
original AME navigation available through **Site menu**. Folders remain native;
mobile layout, both themes, keyboard and dialog checks pass. A startup layout
jump was corrected; bounded retained heap is unchanged. [Installed evidence](review/media-workspace-20261104/REVIEW.md).
Await visual review; next annotation `UX-034`. Existing performance gates remain.

**P2 UX-034:** Section heading text and carets now center together in the existing
AME navigation. Both themes, native keyboard disclosure, mobile and Media drawer
checks pass. [Installed evidence](review/heading-align-20261104/REVIEW.md).
Await visual review; next annotation `UX-035`. Existing performance gates remain.

## Technical blockers and existing follow-up

- **P1 — Review-site list preference POST:** Cloudflare rejects the native `edit.php` items-per-page submission with HTTP 403. The original value remains 20. A separate exact-path security-rule approval and fresh preflight are needed before extending the prior `post.php` exception; no WAF change was made. Native column and optional editor-panel AJAX persistence pass.
- **P1 — Timing qualification under host contention:** the round-3 balanced client-asset probe is prepared but not run. Local load exceeded 200 and remained unstable; no performance acceptance is claimed. The bounded source adds 1,641 gzip bytes. Preserve the existing OOM/startup/save latency gates below.

- **P1 — Native save latency:** the approved exact-site exception is verified, and 19/32 native saves/reloads plus incomplete-save rejection pass. Save responses took 41.6s / 120.5s in one serial run. Attribute latency before release; preserve native storage, validation and ACF lifecycle. The complete empty-page 0→1→0 workflow passes after the native append-route correction.
- **Resolved — Live annotation access:** the owner-approved review-site HTTP-gate removal is recorded in commit `19ee2d9d`; native WordPress sign-in was verified in Codex. WordPress authentication remains required. Static captures remain available for comparison.
- **P2 — Picker cancellation lifecycle:** the Escape/synthetic-append diagnostic left a backing ACF cancel listener that consumed the next click. Target a real cancel→next action keyboard regression before changing the existing picker owner; successful visible-card selection and 0→1→0 saves are verified.
- **P2 — Backup policy evidence:** successful isolated remote jobs do not independently prove Premium advanced-retention capability.


1. **P1 — Release blocker: editor CPU qualification.** The [2 November diagnostic](review/QUALIFICATION-20261102.md) finds similar current Light/Dark medians and dominant sampled jQuery/native-editor work, with unchanged form/source hashes. It is not a matched prior/candidate qualification; the later helper-omission samples are order-confounded. Repeat fully initialized balanced comparisons after runtime stability is resolved, and attribute any repeatable cost before changing lifecycle behavior. Retained heap alone does not qualify startup performance. The [3 November review-host run](review/refinements-round2/performance-summary.json) records 1.79s median startup CPU for 19 sections and 2.28/2.44s for 32 sections in Light/Dark; no matched baseline exists yet. One instrumented wall-time outlier reached 32.3s and needs attribution.
2. **P1 — Release blocker: Local Hub stability.** The [31 October investigation](LOCAL-HUB-OOM.md) reproduces one retained agent socket per forwarded static-file connection on Lima 2.1.2; VM loopback requests do not retain sockets. The [2 November follow-up](review/QUALIFICATION-20261102.md) records continued descriptor accumulation during serial loads, despite no new OOM. Coordinate a supported Local Hub correction containing upstream fix 5216, verify host/guest versions and repeat descriptor/memory observation before clearing the blocker. No forwarding fix, upgrade or memory tuning was applied. The stopped VM was started with its existing configuration on 2 November to restore Codex browser access; resetting process state does not qualify a leak repair.
3. **P2 — Existing accessibility debt.** Native ACF image uploaders expose nested interactive controls, and TinyMCE diagnostic scans report native button/interaction semantics. Coordinate upstream/owner fixes without replacing native field controls or save behavior. Keep native light islands until application owners support reliable theme integration.
4. **P2 — Human polish review.** Have MRN editors compare Light/Dark on the complex draft, common list work and settings. Refine density, hover emphasis, outline scanability, tab contrast and selected/disabled states based on actual editing tasks. Preserve the approved workspace structure.
5. **P2 — Broader qualification only after approval.** Firefox, additional MRN settings/provider screens, WooCommerce/Tutor and other application variations need separately bounded compatibility checks before release. They were not installed or restyled in this sprint.
6. **P3 — Fixture branding.** Resolve the imported off-origin branding URL through the existing AME/Local Hub owner workflow before using this clone as a polished demo; do not change production branding or media to correct an isolated QA capture.

Only the dedicated mrndev.io review deployment is authorized. No release preparation, promotion, production deployment or Fleet rollout is authorized.
