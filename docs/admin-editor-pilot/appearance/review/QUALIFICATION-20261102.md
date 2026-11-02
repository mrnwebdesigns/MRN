# Review preparation and technical qualification — 2 November 2026

**Visual collection is open. Runtime stability and editor CPU qualification remain release blockers.** Product source and installed component files were preserved. No annotation has been received, approved or implemented. No infrastructure upgrade, restart, configuration tuning, release preparation, publication, deployment or Fleet activation occurred.

## Annotation preparation

The [built-in-browser review](http://127.0.0.1:8764/review/) pairs 27 existing states and 54 full-page images, with the component owner, live route and Light/Dark labels for each state. Original capture hashes still match their committed manifest. The existing candidate remains `c1f249f1`; this is not a new visual implementation.

All 27 screen selections, one-open-state behavior, comparison filtering and six-property native annotation metadata passed the local artifact test. Desktop and 390px gallery axe scans had no WCAG A/AA violations; narrow width was 390px with a 390px scroll width. There were no gallery script errors. These checks cover the review artifact's controls and markup, not accessibility inside screenshot pixels. Existing product accessibility evidence and native ACF/TinyMCE findings remain unchanged.

The native annotation entry button was visible in Codex's built-in browser. An agent click did not confirm active annotation mode while the agent controlled the tab. The reviewer should select **Annotate** after handoff; no synthetic comment was saved or submitted. The [official annotation workflow](https://learn.chatgpt.com/docs/browser) remains the feedback channel. Screenshot review needs no WordPress sign-in. Live interaction review needs the built-in browser's separate local WordPress session; the app queued the supported Local Hub login handoff rather than immediately authenticating the existing tab.

[Gallery QA](evidence/review-gallery-qa-20261102.json), [dashboard review preview](evidence/review-dashboard-20261102.png), [editor review preview](evidence/review-editor-20261102.png), [annotation log](ANNOTATIONS.md).

## CPU investigation

The new bounded diagnostic runs serially against the real 19-section draft. It uses the installed MRN QA Engine Chromium/Playwright dependencies, a fresh context for every load, a 1600×1100 viewport, external-request blocking and fully initialized four-instance TinyMCE. Eight source/installed file hashes are checked before and after. The Light/Dark preference is overridden only in the in-memory HTML response before first paint; no user preference or installed file is changed. The native successful ACF form has the same SHA-256 across all 14 loads.

There are two warm-ups, eight full-candidate samples in balanced mode order, and four exploratory loads omitting only `appearance.js` in the browser. Two full-candidate samples include CPU profiling and are excluded from timing comparisons. Chromium reports version 148.0.7778.96. The remaining full-candidate measurements are:

| Current candidate | Valid unprofiled samples | Startup CPU median | Range | Script median | Retained heap median |
| --- | ---: | ---: | ---: | ---: | ---: |
| Light | 3 | 932.8 ms | 923.9–974.8 ms | 576.3 ms | 25.94 MB |
| Dark | 3 | 949.3 ms | 926.9–977.5 ms | 601.7 ms | 25.94 MB |

The two themes were close in this small current-candidate run. It is **not** a matched prior/candidate benchmark and does not establish performance non-regression. Browser/runtime conditions differ from older captures, so their absolute timings or retained heaps are not a qualified before/after comparison.

At a one-millisecond sampling interval, jQuery accounts for about 328–337 ms of sampled self time; TinyMCE accounts for about 64 ms; Section Navigator accounts for about 20 ms. `appearance.js` has no self-time sample in either profile. This is a sampling observation, not proof of zero cost or attribution of jQuery's callers. Further source attribution should examine the existing native ACF/TinyMCE initialization call paths before proposing any lifecycle change.

The later JS-omission runs were slower and more variable (CPU medians 1,285 ms Light and 1,408 ms Dark, two each). Because they ran after the full-candidate sequence, order/runtime variance confounds this experiment. They do not prove that the appearance helper makes the editor faster, nor that omitting it resolves the previous CPU concern. No speculative performance fix was applied.

The reusable [diagnostic harness](../../../../mu-plugins/mrn-admin-ui-css/tests/runtime/appearance-cpu-diagnostic.cjs) stops below 1.5 GB available VM memory and keeps runtime/source verification explicit. Run from the task worktree:

```sh
node mu-plugins/mrn-admin-ui-css/tests/runtime/appearance-cpu-diagnostic.cjs
```

[Raw bounded measurements and sanitized script attribution](evidence/cpu-diagnostic-20261102.json). Next qualification requires a stable Local Hub runtime, fully initialized matched prior/candidate inputs, balanced run order and enough repeated samples to resolve a repeatable cost. Do not clear the CPU blocker using this current-theme comparison alone.

## Local Hub OOM follow-up

Host and guest still report Lima 2.1.2. The guest-agent PID is unchanged, with one service restart, and no new kernel OOM entry was found since the previous investigation. That is current health evidence, not a repaired leak.

During the 14 serial editor loads, agent descriptors grew **51,209 → 55,246**. Agent RSS grew from about **578 MiB to 769 MiB** at the final sampling point; a later idle snapshot fell to about **641 MiB**, while descriptor count remained **55,250**. RSS can be reclaimed and should not be modeled as a fixed per-connection allocation. Descriptor accumulation remains the consistent signal. Available VM memory stayed above 3.8 GiB in the sampled workload and memory-pressure averages stayed at zero.

This extends the [previous static-forward reproduction and upstream match](../LOCAL-HUB-OOM.md). CPU collection did not run concurrently with other agent-driven heavy fixture jobs. Normal external use of the shared local runtime cannot be excluded, and the descriptor delta is not asserted to consist exclusively of this harness's requests. No additional stress was used to force another OOM.

The required infrastructure follow-up remains a coordinated Local Hub correction containing the upstream forwarding fix, verification of both host and guest versions, the loopback/forward/proxy comparison and bounded traffic/idle observation. That correction was not performed from this annotation task. Increasing RAM or restarting alone would not demonstrate that retention is fixed.

[Final health/version evidence](evidence/oom-followup-20261102.json).

## Integrity, MRN QA and exclusions

- Real 19-row and derived 32-row drafts retain their original status, main-content hash, complete native ACF hash and post-content hash after the diagnostics. [Readback evidence](evidence/review-integrity-20261102.json).
- Node syntax checking and the bounded diagnostic pass. All eight source/runtime hashes remain identical, and all 14 native ACF form hashes match.
- Full-component MRN QA passes PHP lint, WPCS/security, PHP compatibility, PHPStan, secret/debug checks and API surface review. Runtime REST returns HTTP 200. [QA report](evidence/qa-review-preparation-20261102.md).
- Semgrep is skipped because this component has no rule configuration. ESLint is skipped because the component has no root package manifest. The diagnostic's Node syntax/runtime checks supply its relevant executable verification.
- Generic public-page smoke/accessibility/performance/CWV rows are intentionally skipped by the existing admin-only QA profile. The new authenticated CPU diagnostic and static gallery axe checks run separately. No new product accessibility or save/reload pass is inferred from unchanged runtime files; the original appearance evidence remains the functional baseline.
- Broad vendor compatibility, release/parity/distribution qualification, source promotion, packaging, publication, remote backup/deployment readiness and installed remote verification are excluded by the owner's closed gates.

The staged MRN gate qualifies only this task snapshot. Its generic release-success boilerplate is not a clearance of the OOM/CPU blockers or authority to release. Approved annotation clusters will receive their own targeted regression checks after implementation in the same worktree.
