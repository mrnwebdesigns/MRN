# Annotation log

**Collection open. Received: 6 annotations in 2 batches. Grouped: 4 refinement clusters. Approved clusters: 0. Implemented refinements: 0.**

The first human batch covers the live Dashboard rendered in Light appearance at a reported 1487×1909 viewport. Original marked screenshots are attached to the user's submitted message. Exact wording, selectors, paths and coordinates are retained in [batch 001](annotation-batches/001-dashboard-light.json). No opaque native annotation IDs or local screenshot paths were supplied, so none have been invented. The blue selection rectangles and numbered markers are annotation overlays, not product styling findings.

These are Light observations. Dark-mode consequences are queued for comparison, not claimed as confirmed defects. Existing technical findings in the parent backlog remain separate from these user annotations.

The second batch reports the Universal Sticky Bar covering the Editor Workspace menu during scroll. The [original supplied crop](annotation-batches/002-editor-sticky-overlap.png) and [read-only geometry/hit-test evidence](annotation-batches/002-editor-sticky-overlap.json) are retained. The current inspected state also overlaps at scrollTop 0; no scroll movement was performed to reproduce the transition. Four actual menu-control centers are intercepted by toolbar elements, confirming an interaction blocker in that state.

## Original annotations

| ID | Received / native reference | Original wording | Screen and state | Mode / viewport | Cluster |
| --- | --- | --- | --- | --- | --- |
| UX-001 | 2 Nov 2026 / batch 001, User Comment 1 | “The con is hard to see” | Dashboard toolbar, SEOPress icon beside SEO10 | Light observed / 1487×1909 | UX-C01 |
| UX-002 | 2 Nov 2026 / batch 001, User Comment 2 | “Any ideas what could be better than this floating around?” | Dashboard, closed native Screen Options control | Light observed / 1487×1909 | UX-C03 |
| UX-003 | 2 Nov 2026 / batch 001, User Comment 3 | “Spacing between items is a bit much. The main sections could stand out a little more.” | Main navigation, item spacing and AME section headings | Light observed / 1487×1909 | UX-C02 |
| UX-004 | 2 Nov 2026 / batch 001, User Comment 4 | “This styling seems off” | Dashboard navigation item, current/selected appearance | Light observed / 1487×1909 | UX-C02 |
| UX-005 | 2 Nov 2026 / batch 001, User Comment 5 | “Icon not visible, match color of reset” | Main navigation, Relevanssi image icon | Light observed / 1487×1909 | UX-C01 |
| UX-006 | 2 Nov 2026 / batch 002, supplied screenshot | “On scroll the universal sticky bar covers the menu under it.” | Complex editor 2523, sticky bar covering workspace navigation | Light observed / live client area 1472×1909; attachment is a crop | UX-C04 |

Next annotation ID: `UX-007`. Original typos remain intact. Triage interprets “con” as the selected icon and “reset” as likely “rest” (matching neighboring icons); those are interpretations, not edits to the feedback. No unsent browser drafts were inspected.

## Refinement clusters

| Cluster | Source annotations | Shared component / mode differences | Priority and reason | Proposed bounded refinement | Approval / status | Targeted regression evidence |
| --- | --- | --- | --- | --- | --- | --- |
| UX-C04 — Sticky editor chrome overlap | UX-006 | Existing Universal Sticky Bar positioning, Editor Lockdown chrome-offset calculation and Admin UI workspace CSS. Light is confirmed in the inspected state; scroll transitions/Dark/mobile remain pending. | P1, first before visual polish: confirmed navigation obstruction; two intended navigation locations hit publishing buttons. Release blocker until corrected and regression-tested. | Keep WordPress toolbar → Universal Sticky Bar → workspace navigation in a non-overlapping stack using consistent rendered geometry/offsets. Reuse the existing shared offset mechanism for outline, Site menu and jump/focus scrolling. Do not solve it by merely drawing navigation over publishing actions. Preserve ACF form placement and native save/publish behavior. | Awaiting approval; no code change | Top/intermediate/deep scrolling, resize and wrapped toolbar, desktop/narrow widths, both themes. Menu hit tests must resolve to the intended controls; publishing controls remain reachable. Check outline, jump/focus offsets, media/publishing overlays, native validation and save/reload/truncated-save guard. |
| UX-C01 — Icon visibility | UX-001, UX-005 | Admin UI CSS shell/tokens; toolbar and sidebar icon renderers differ. Light is observed; Dark/default/hover/current/focus remain to be checked. | P2, rank 2: repeated icon legibility problem; promote if a measured accessibility regression is confirmed. | Reuse existing semantic icon colors for the two shell targets. Inspect image/SVG/background formats before selecting a narrow treatment; do not apply broad vendor-interface filters or change AME configuration. | Awaiting approval; no code change | Verify both icon targets in both themes and states, contrast where required, accessible names, badges and unchanged links. |
| UX-C02 — Navigation density, hierarchy and current state | UX-003, UX-004 | Admin UI CSS shared menu spacing/current-state styles; AME owns headings, order and labels. Light Dashboard current state is observed; other screens/themes are pending. | P2, rank 1: daily navigation scanability and unnecessary height. | Reduce item and group gaps while retaining usable targets; strengthen section-heading weight/contrast and tighten heading-to-items spacing. Recommend a simple rounded current row with one consistent accent cue, without the pointed/notched silhouette seen in the capture. Confirm the intended selected treatment during approval. | Awaiting approval; no code change | Desktop/mobile/collapsed navigation, default/hover/current/expanded/focus, submenu keyboard entry, section hierarchy, preserved AME labels/order/links and normal editor navigation. |
| UX-C03 — Page-header utilities | UX-002 | Admin UI CSS native-screen header treatment; WordPress retains Screen Options/Help controls and panels. Light Dashboard is observed; settings/lists/editor variations are pending. | P2, rank 3: detached utility controls weaken page hierarchy. | Recommended: visually group Screen Options and Help in a compact utility area aligned with the page title and content edges. Retain the native labels, panels, focus and toggle behavior. Avoid adding a second settings system or an extra menu interaction just to hide these controls. | Awaiting approval; no code change | Native panel open/close and focus, header alignment on Dashboard/lists/settings, narrow-screen wrapping, reduced motion and unchanged third-party screen ownership. |

Group related Light/Dark comments under one component cluster while retaining distinct symptoms and references. Default status is **Awaiting approval**. Record an explicit owner approval reference before implementation. Conflicting comments stay visible until resolved.

Read-only source triage supports a shared menu treatment: shell CSS currently gives menu labels 11px vertical padding, menu links 3px margins, heading links 20px top margins and separators additional margins. Icon color rules explicitly cover pseudo-element glyphs; the Relevanssi target is an `img`, so that rule alone does not tint it. These are implementation leads, not confirmed causes for every observed shape or icon. The native active-state/AME rule interaction and SEOPress icon format still need narrow inspection before an approved edit.

For UX-C04, the Universal Sticky Bar occupies y=86–156.99px while the workspace header occupies y=103–157px: a 53.99px vertical overlap over its 54px height. The stored offset is 103px. `updateChromeOffset()` uses bar height plus its computed CSS top, which differs from its current rendered bottom. This identifies an offset/positioning interaction; it does not establish a final repair. Site menu and Back to list centers hit the permalink; Content and Page settings centers hit Publish and View / Publish controls. No click or save was attempted. Keep interaction testing of the covered row paused until an approved fix is verified.

## Review decision

- Review complete: **No**.
- Approved batch: **None**.
- Product source changes authorized by submitted annotations: **None**.
- Next action: continue collecting feedback, especially Dark comparisons and Editor Workspace observations. When the owner declares collection complete, present a bounded batch of these clusters and record explicit approval before implementation using [the review procedure](REVIEW.md).
