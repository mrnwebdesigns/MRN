# Prioritized follow-up

**Round 1 approved — 2 November 2026.** All ten annotations / seven clusters were approved by the owner's “Let's make these changes for now” instruction. Source changes are implemented in the existing task worktree; native Light/Dark visual and save/reload checks are complete for the selected fixtures; full automated/performance qualification remains open. See [the implementation and verification record](review/REFINEMENTS-ROUND1.md). No cluster is visually accepted or closed. [Round 2 paired annotations](review/round-2.html) are ready without WordPress login; this is not release sign-off.

## Human refinement queue — implemented, awaiting visual review

| Priority | Cluster / references | Material change | Remaining gate |
| --- | --- | --- | --- |
| P1 | UX-C04 / UX-006, UX-007 | One fixed publishing/workspace stack, measured heights and a single content spacer; matching outline/jump/focus offsets. | Nine Light width/scroll hit cases and native saves pass. Dark narrow/overlay cases and human top-spacing review remain. |
| P2, rank 1 | UX-C06 / UX-009 | Named nested block/repeater headers, item counts, native ACF row collapse/expand controls and component surfaces. | Native add/remove/sort/duplicate, TinyMCE, conditional logic, validation and serialization tests. Table repeaters retain native column layout. |
| P2, rank 2 | UX-C07 / UX-010 | Lazy per-layout authored-content index; current text/rich text, contextual excerpts and native row/tab reveal. | Saved rich-text and unsaved nested-text search/focus pass. Unsaved Visual TinyMCE, full lifecycle matrix and matched CPU/heap comparison remain. |
| P2, rank 3 | UX-C02 / UX-003, UX-004 | Denser rows, stronger AME group headings and rounded current-state treatment without pointer pseudo-elements. | Both-mode captures, Light hover/focus and Dark keyboard submenu checked. Dark hover/mobile and human density review remain. |
| P2, rank 4 | UX-C01 / UX-001, UX-005 | Narrow alpha masks retain the SEOPress and Relevanssi glyphs using semantic shell colors. | Computed contrast, shape and visibility in both themes/states; vendor/AME configuration unchanged. |
| P2, rank 5 | UX-C05 / UX-008 | Compact parent/nested ACF tab strips with clearer selected, hover and focus states. | Live native activation, wrapping and conditional/validation behavior. |
| P2, rank 6 | UX-C03 / UX-002 | Native Screen Options/Help grouped in the page-title row on allowlisted screens. | Light native panels pass; paired Dashboard/list/settings alignment captured. Complete Dark panel/reflow and human utility placement review remain. |

Round 2 starts at `UX-011`. Retain original references and group Light/Dark symptoms by shared component. No further refinements are authorized automatically by newly collected annotations.

## Technical blockers and existing follow-up

1. **P1 — Release blocker: editor CPU qualification.** The [2 November diagnostic](review/QUALIFICATION-20261102.md) finds similar current Light/Dark medians and dominant sampled jQuery/native-editor work, with unchanged form/source hashes. It is not a matched prior/candidate qualification; the later helper-omission samples are order-confounded. Repeat fully initialized balanced comparisons after runtime stability is resolved, and attribute any repeatable cost before changing lifecycle behavior. Retained heap alone does not qualify startup performance.
2. **P1 — Release blocker: Local Hub stability.** The [31 October investigation](LOCAL-HUB-OOM.md) reproduces one retained agent socket per forwarded static-file connection on Lima 2.1.2; VM loopback requests do not retain sockets. The [2 November follow-up](review/QUALIFICATION-20261102.md) records continued descriptor accumulation during serial loads, despite no new OOM. Coordinate a supported Local Hub correction containing upstream fix 5216, verify host/guest versions and repeat descriptor/memory observation before clearing the blocker. No forwarding fix, upgrade or memory tuning was applied. The stopped VM was started with its existing configuration on 2 November to restore Codex browser access; resetting process state does not qualify a leak repair.
3. **P2 — Existing accessibility debt.** Native ACF image uploaders expose nested interactive controls, and TinyMCE diagnostic scans report native button/interaction semantics. Coordinate upstream/owner fixes without replacing native field controls or save behavior. Keep native light islands until application owners support reliable theme integration.
4. **P2 — Human polish review.** Have MRN editors compare Light/Dark on the complex draft, common list work and settings. Refine density, hover emphasis, outline scanability, tab contrast and selected/disabled states based on actual editing tasks. Preserve the approved workspace structure.
5. **P2 — Broader qualification only after approval.** Firefox, additional MRN settings/provider screens, WooCommerce/Tutor and other application variations need separately bounded compatibility checks before release. They were not installed or restyled in this sprint.
6. **P3 — Fixture branding.** Resolve the imported off-origin branding URL through the existing AME/Local Hub owner workflow before using this clone as a polished demo; do not change production branding or media to correct an isolated QA capture.

No release preparation, promotion or Fleet rollout is authorized by this local result.
