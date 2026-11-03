# Prioritized follow-up

**Round 1 approved — 2 November 2026.** All ten annotations / seven clusters were approved by the owner's “Let's make these changes for now” instruction. Source changes are implemented in the existing task worktree; native Light/Dark visual and save/reload checks are complete for the selected fixtures; full automated/performance qualification remains open. See [the implementation and verification record](review/REFINEMENTS-ROUND1.md). No cluster is visually accepted or closed. [Round 2 paired annotations](review/round-2.html) are ready without WordPress login; this is not release sign-off.

## Round 2 — installed on protected mrndev.io; functional checks passed; performance open (3 November 2026)

Seven comments (`UX-011`–`UX-017`) are preserved in [batch 004](review/annotation-batches/004-round2-new-page-and-toolbar.json) and [triaged in the annotation log](review/ANNOTATIONS.md#round-2--new-page-workflow-and-toolbar). The owner approved all five clusters with “Make those udpates, then we can do another round from there.” Source changes are installed on the authorized mrndev.io review copy. Scoped both-theme visual/accessibility, account preference and native Screen panel checks pass. The approved exact-site Cloudflare exception is applied; 19/32-section save/reload and incomplete-save rejection pass. Slow native saves remain a performance blocker. Codex live Basic Auth sign-in remains unresolved; [32 paired captures](review/round-3.html) support static annotation review. [Current implementation/check status](review/REFINEMENTS-ROUND2.md). Performance and usability lead the work; the approved workspace structure is preserved.

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

Round 2 now contains `UX-011`–`UX-017`; next is `UX-018`. Retain original references and group Light/Dark symptoms by shared component. No further refinements are authorized automatically by newly collected annotations.

## Technical blockers and existing follow-up

- **P1 — Native save latency:** the approved exact-site exception is verified, and 19/32 native saves/reloads plus incomplete-save rejection pass. Save responses took 41.6s / 120.5s in one serial run. Attribute latency before release; preserve native storage, validation and ACF lifecycle. The complete empty-page 0→1→0 workflow passes after the native append-route correction.
- **P1 — Live annotation access:** Codex rejected Basic Auth; retain protection. Current static captures are available for visual annotations.
- **P2 — Picker cancellation lifecycle:** the Escape/synthetic-append diagnostic left a backing ACF cancel listener that consumed the next click. Target a real cancel→next action keyboard regression before changing the existing picker owner; successful visible-card selection and 0→1→0 saves are verified.
- **P2 — Backup policy evidence:** successful isolated remote jobs do not independently prove Premium advanced-retention capability.


1. **P1 — Release blocker: editor CPU qualification.** The [2 November diagnostic](review/QUALIFICATION-20261102.md) finds similar current Light/Dark medians and dominant sampled jQuery/native-editor work, with unchanged form/source hashes. It is not a matched prior/candidate qualification; the later helper-omission samples are order-confounded. Repeat fully initialized balanced comparisons after runtime stability is resolved, and attribute any repeatable cost before changing lifecycle behavior. Retained heap alone does not qualify startup performance. The [3 November review-host run](review/refinements-round2/performance-summary.json) records 1.79s median startup CPU for 19 sections and 2.28/2.44s for 32 sections in Light/Dark; no matched baseline exists yet. One instrumented wall-time outlier reached 32.3s and needs attribution.
2. **P1 — Release blocker: Local Hub stability.** The [31 October investigation](LOCAL-HUB-OOM.md) reproduces one retained agent socket per forwarded static-file connection on Lima 2.1.2; VM loopback requests do not retain sockets. The [2 November follow-up](review/QUALIFICATION-20261102.md) records continued descriptor accumulation during serial loads, despite no new OOM. Coordinate a supported Local Hub correction containing upstream fix 5216, verify host/guest versions and repeat descriptor/memory observation before clearing the blocker. No forwarding fix, upgrade or memory tuning was applied. The stopped VM was started with its existing configuration on 2 November to restore Codex browser access; resetting process state does not qualify a leak repair.
3. **P2 — Existing accessibility debt.** Native ACF image uploaders expose nested interactive controls, and TinyMCE diagnostic scans report native button/interaction semantics. Coordinate upstream/owner fixes without replacing native field controls or save behavior. Keep native light islands until application owners support reliable theme integration.
4. **P2 — Human polish review.** Have MRN editors compare Light/Dark on the complex draft, common list work and settings. Refine density, hover emphasis, outline scanability, tab contrast and selected/disabled states based on actual editing tasks. Preserve the approved workspace structure.
5. **P2 — Broader qualification only after approval.** Firefox, additional MRN settings/provider screens, WooCommerce/Tutor and other application variations need separately bounded compatibility checks before release. They were not installed or restyled in this sprint.
6. **P3 — Fixture branding.** Resolve the imported off-origin branding URL through the existing AME/Local Hub owner workflow before using this clone as a polished demo; do not change production branding or media to correct an isolated QA capture.

Only the dedicated mrndev.io review deployment is authorized. No release preparation, promotion, production deployment or Fleet rollout is authorized.
