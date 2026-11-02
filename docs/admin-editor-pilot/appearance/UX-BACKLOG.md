# Prioritized follow-up

**Annotation phase open — 2 November 2026.** The [paired review](http://127.0.0.1:8764/review/) and [collection procedure](review/REVIEW.md) preserve the current candidate. The [annotation log](review/ANNOTATIONS.md) contains six submitted annotations across Dashboard and Editor Workspace, grouped into four clusters, with zero approved clusters and zero implemented refinements. Original wording and mode-specific evidence are retained. Implementation waits for approval of a bounded cluster batch.

## Human refinement queue — awaiting approval

| Rank within UX queue | Cluster / references | Bounded direction | Status |
| --- | --- | --- | --- |
| 0 — P1 blocker | UX-C04 / UX-006 | Correct the Universal Sticky Bar/workspace-header offsets and positioning so the navigation row is visible and receives its own clicks. The current Light-state overlap intercepts navigation with permalink/publishing controls. | Confirmed by geometry and hit tests; pause covered-row interaction tests; no implementation approval. Verify scroll transitions, both themes and native save/publish behavior after correction. |
| 1 — P2 | UX-C02 / UX-003, UX-004 | Denser navigation, clearer AME section headings and a consistent Dashboard/current-row treatment. | Light observed; Dark and other states pending; no implementation approval. |
| 2 — P2 | UX-C01 / UX-001, UX-005 | Improve SEOPress toolbar and Relevanssi sidebar icon visibility through the existing semantic palette and narrow renderer-specific treatment. | Light observed; Dark and measured contrast pending; no implementation approval. |
| 3 — P2 | UX-C03 / UX-002 | Integrate native Screen Options/Help visually into the page-header utility area. | Recommendation only; native behavior retained; no implementation approval. |

Potential measured accessibility or functional regressions take P1 precedence if confirmed. Submitted comments and visual styling previews do not by themselves approve code changes. Review remains open for the Editor Workspace and other screens.

## Technical blockers and existing follow-up

1. **P1 — Release blocker: editor CPU qualification.** The [2 November diagnostic](review/QUALIFICATION-20261102.md) finds similar current Light/Dark medians and dominant sampled jQuery/native-editor work, with unchanged form/source hashes. It is not a matched prior/candidate qualification; the later helper-omission samples are order-confounded. Repeat fully initialized balanced comparisons after runtime stability is resolved, and attribute any repeatable cost before changing lifecycle behavior. Retained heap alone does not qualify startup performance.
2. **P1 — Release blocker: Local Hub stability.** The [31 October investigation](LOCAL-HUB-OOM.md) reproduces one retained agent socket per forwarded static-file connection on Lima 2.1.2; VM loopback requests do not retain sockets. The [2 November follow-up](review/QUALIFICATION-20261102.md) records continued descriptor accumulation during serial loads, despite no new OOM. Coordinate a supported Local Hub correction containing upstream fix 5216, verify host/guest versions and repeat descriptor/memory observation before clearing the blocker. No forwarding fix, upgrade or memory tuning was applied. The stopped VM was started with its existing configuration on 2 November to restore Codex browser access; resetting process state does not qualify a leak repair.
3. **P2 — Existing accessibility debt.** Native ACF image uploaders expose nested interactive controls, and TinyMCE diagnostic scans report native button/interaction semantics. Coordinate upstream/owner fixes without replacing native field controls or save behavior. Keep native light islands until application owners support reliable theme integration.
4. **P2 — Human polish review.** Have MRN editors compare Light/Dark on the complex draft, common list work and settings. Refine density, hover emphasis, outline scanability, tab contrast and selected/disabled states based on actual editing tasks. Preserve the approved workspace structure.
5. **P2 — Broader qualification only after approval.** Firefox, additional MRN settings/provider screens, WooCommerce/Tutor and other application variations need separately bounded compatibility checks before release. They were not installed or restyled in this sprint.
6. **P3 — Fixture branding.** Resolve the imported off-origin branding URL through the existing AME/Local Hub owner workflow before using this clone as a polished demo; do not change production branding or media to correct an isolated QA capture.

No release preparation, promotion or Fleet rollout is authorized by this local result.
