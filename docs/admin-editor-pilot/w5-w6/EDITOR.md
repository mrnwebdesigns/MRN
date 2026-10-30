# W5 editor completion — local candidate

The approved workspace structure is retained. Summary/error refreshes now affect one section without rebuilding the outline; removed rows are released from title observers. Arrangement adds explicit keyboard move controls through ACF's existing sort/unmount/remount lifecycle, with a remount guard for rapid moves. Indexing ignores native transient sort placeholders and waits until ACF finishes a drag. ACF continues to own field order, serialization, controls and publishing.

The authenticated 19/32-section workflow passes: Content/Settings/Arrange form equivalence, search and return focus, keyboard order save/reload/restoration, actual pointer dragging, native add/duplicate/remove/sort, media open/cancel, server validation discovery, TinyMCE/repeater Save Draft/reload and restoration, incomplete-save HTTP 400, mobile reflow and new-control axe scans. No page errors occurred in the full save run. WordPress 7 automatically opts admin documents into CSS cross-document view transitions. The pilot now explicitly uses ordinary navigation on its supported screens, avoiding snapshot transitions around large native ACF/TinyMCE forms. No JavaScript error is caught or suppressed. The original warning had no captured stack; its historical trigger remains unconfirmed, while the enabled pilot no longer opts into that transition path.

The startup cause is now bounded and corrected: revealing the first layout before ACF's native load phase eagerly enhanced hidden Select2 controls. The selected layout now opens after that phase. The DOM diagnostic found 94 additional Select2 instances with early reveal; native TinyMCE still has four instances/containers in both variants. No native widget is replaced or its contents relocated.

Three alternating original/approved/final pairs per fixture compare identical expanded first sections. CDP CPU is sampled before test-only full GC; retained heap is sampled afterward. Medians:

| Fixture | Original / approved / final retained heap | Approved → final startup CPU | Approved → final typing script CPU |
| --- | --- | --- | --- |
| 19 rows | 27.1 / 29.2 / 27.1 MB | 1.091 → 0.934 s | 135.7 → 42.4 ms |
| 32 rows | 29.6 / 31.9 / 30.1 MB | 1.450 → 1.328 s | 208.5 → 53.8 ms |

Final startup CPU is within roughly 2% of the original pilot (0.949 / 1.306 s). The approved visual structure is preserved with about 810 fewer eagerly created DOM nodes. The supplied typing workload replaces outline groups 96 times in the prior versions and zero times in the final version. These are local browser measurements, not human usability percentages or broad performance guarantees. The reported initial 6–10 MB increase used coarse heap estimates and mismatched lifecycle timing; the controlled run supersedes that conclusion. The measurement JS fingerprint predates a comment-only clarification about Select2; runtime logic is identical.

The final full save run passes with zero page errors. Search/Enter response measured 36.6 ms after a supplied query; the first editable field remains at 680 px in a 1100 px viewport. Native save round trips varied from 30–124 s under the local VM's concurrent browser/test workload; these are explicitly not used as a product speed comparison. Persisted hashes are verified independently after the runs.

Evidence in `evidence/` is bounded to admin-editor-pilot.localhost. Generic public-page QA browser/accessibility/CWV checks are intentionally skipped by component policy; authenticated checks provide the relevant coverage. No remote compatibility matrix, release preparation or deployment was run. Existing native ACF/TinyMCE accessibility findings remain separate from the zero-violation new-control scans.
