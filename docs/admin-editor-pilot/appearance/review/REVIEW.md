# MRN annotation review — open

**Current, 3 November 2026:** local round-1 refinements are installed at `4c4ec6e9` (Admin UI 3.3.1 / Editor Lockdown 1.0.39). [Current paired gallery](http://127.0.0.1:8765/review/round-2.html), [verification](REFINEMENTS-ROUND1.md), and [round-2 annotations UX-011–UX-017](ANNOTATIONS.md#round-2--new-page-workflow-and-toolbar). Round 2 is collect-only pending separate approval; performance and usability are the leading priorities. Historical appearance review instructions follow.

Review candidate: `c1f249f1`, Admin UI CSS 3.3.0, in the existing `codex/admin-editor-pilot-20261029` worktree. Preparation date: 2 November 2026. Product source, AME configuration, native ACF forms, Classic Editor and publishing behavior are preserved. Release preparation, publication, shared-environment deployment and Fleet gates remain closed.

## Start review

Open the [paired annotation review](http://127.0.0.1:8764/review/) in this chat's built-in browser. It presents 27 paired states using 54 existing full-page images from the appearance review. Screen names, Light/Dark labels, owning components and live links stay beside the images. Choose Light only or Dark only when a full-width capture is easier to inspect. Captures are dated 30 October; they have not been altered to suggest refinements were implemented.

Use the browser toolbar's **Annotate** control, or the review page's feature-detected **Enter annotation mode** button. Select the relevant element or drag over an area, save the comment, and send it in this chat with **Collect only**. Only the human reviewer submits annotations. The review page does not store, export, transmit or apply comments. Native style previews remain review feedback and are not source changes. These steps follow the [official OpenAI browser annotation workflow](https://learn.chatgpt.com/docs/browser).

For interaction feedback, open the live page in the built-in browser and annotate its actual controls. The built-in browser has its own WordPress login session; an authenticated Chrome tab does not imply the built-in browser is signed in. On 2 November, the Codex browser itself was verified signed in as `mrn-admin` after starting the stopped Local Hub VM. Continue through the normal `/wp-admin/` URL. Local Hub one-time links open the default browser and delete themselves after a successful login; a reused link is not a reliable Codex sign-in route. If this browser session later expires, complete ordinary local sign-in there; do not paste credentials into chat. Paired screenshot review is ready without a WordPress session. The complex review draft is [page 2523](https://admin-editor-pilot.localhost/wp-admin/post.php?post=2523&action=edit), with 19 sections. Use the draft for ordinary editing exploration; avoid saving or publishing content during visual collection. Existing native save/validation behavior remains available for approved regression checks.

The [screen inventory](inventory.json) covers Dashboard, Notifications Center, sidebar hover/flyout/focus/expanded mobile navigation, Posts/Pages/MRN custom post type lists, General/MRN settings, Business Information, Users, Plugins, Categories, Privacy, Tools, the complex Editor Workspace, arrangement, Page settings, validation, native publishing/media and MRN layout/icon pickers. Dashboard/editor/settings narrow captures are included. Plugin-owned interfaces remain native compatibility boundaries; screenshots are not a claim that all vendor application screens have been qualified.

When Annotation mode captures navigation clicks, exit using the browser's annotation control or hold Space to use ordinary page controls. No custom annotation plugin is installed in WordPress. Optional metadata and the entry button exist only in this static review artifact.

## Collection and decisions

The [annotation log](ANNOTATIONS.md) retains all submitted batches. On each submitted batch:

1. Preserve the original wording and native annotation reference in a new `UX-###` record. Record screen, state, Light/Dark/Both, viewport and the desired outcome. Keep an unknown value unknown; ask only when it prevents useful triage.
2. Cluster related comments across modes and screens by the underlying component or workflow. Keep mode-specific differences and all source annotation references. A Light comment does not establish that Dark has the same defect.
3. Identify the existing owner and smallest likely change. Shared text/contrast/controls belong to semantic tokens and Admin UI CSS; navigation visual states belong to shell CSS while AME retains configuration; editor workflow belongs to Editor Lockdown and workspace CSS; native publishing/media/ACF and third-party behavior retain their existing owners.
4. Prioritize functional/accessibility/data-integrity regressions first, then repeated daily-workflow friction, then polish. Keep OOM and CPU release blockers in the technical backlog, separate from editor opinions.
5. Mark the cluster **Awaiting approval**. Receipt, grouping, a style preview, a suggested solution, a priority assignment or review completion is not approval to implement. No source changes are made automatically.

When the owner declares annotation collection complete, present one bounded batch with the clusters, proposed edits, shared-component impact, conflicts, remaining questions and regression checks. Record the owner's explicit approval by cluster IDs. Implement only those approved refinements directly in this worktree, with targeted MRN QA and before/after evidence. Do not create another architecture plan or widen release authority.

## Existing component and regression map

| Feedback group | Existing owner | Checks after an approved change |
| --- | --- | --- |
| Shared colors, text, controls, status and focus | Admin UI CSS tokens/shared styles | Light/Dark contrast, hover/focus/disabled/validation, System and first paint, keyboard, native light islands |
| Navigation spacing, hierarchy and states | Admin UI shell CSS; AME configuration remains authoritative | Default/current/expanded/flyout/focus, submenu keyboard entry, collapsed/mobile navigation, unchanged labels/order/links |
| Dashboard, notifications and list workflow | Admin UI CSS; Dashboard Support/Notifications Center/native WordPress behavior | Widget ordering/collapse, notification filters, search/filters, Quick Edit and bulk actions |
| Editor outline, content/settings and arrangement | Editor Lockdown and workspace CSS | 19/32 sections, search/jump/return, arrangement keyboard/focus, error discovery, unchanged native form fields, save/reload and truncated-save guard |
| Native publishing, media and MRN pickers | Native WordPress/ACF, Sticky Bar, Shared Assets | Open/cancel, focus restoration, native validation/publishing, controls inside native form, both themes |
| Core/MRN settings and responsive layout | Admin UI CSS; existing settings owner | Labels, notices, controls, save/reload/restore when applicable, 390px reflow, keyboard/focus |

## Local artifact server

The review server serves this sanitized appearance-artifact directory only, bound to `127.0.0.1:8764`. It has no comment POST endpoint, authentication bridge or WordPress integration. To restart it from the task worktree, run:

```sh
python3 -m http.server 8764 --bind 127.0.0.1 --directory docs/admin-editor-pilot/appearance
```

The server is a local review convenience, not a deployed application. The existing [QA report and before/after gallery](../README.md), [refinement backlog](../UX-BACKLOG.md) and [OOM investigation](../LOCAL-HUB-OOM.md) remain authoritative for the implementation and its unresolved technical limits.

The [2 November qualification follow-up](QUALIFICATION-20261102.md) records review-artifact checks, readback integrity, serial CPU attribution and current guest-agent retention. Neither technical release blocker is cleared.
