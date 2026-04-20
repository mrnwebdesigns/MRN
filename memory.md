MRN PROJECT CONTEXT

This project is a WordPress-based website building platform.

Platform facts:
- Uses Classic Editor, not Gutenberg
- Built as a modular system using a stack theme, plugins, MU plugins, and shared runtime code
- Features often affect admin UI, frontend rendering, saved data, and third-party APIs

Architecture:
- Theme owns layout, rendering, and builder behavior
- Plugins own features, integrations, and admin behavior
- MU plugins own shared runtime and cross-cutting behavior
- Site Styles owns design tokens such as colors and accents
- Config Helper owns site-wide settings and social/config data

Critical constraints:
- Preserve existing builder behavior unless explicitly told otherwise
- Preserve shared theme hook contracts, including CSS classes, CSS variables, data attributes, and helper output used by other parts of the system
- Treat site updates as stack updates, not plugin-only swaps
- Stack sites run a cloned active site theme by default; only add a child theme later when the site is handed to the development/front-end team
- Prefer existing helpers, APIs, and established contracts over one-off logic
- Assume changes may affect multiple connected areas

Product quality standards:
- Accessibility and front-end performance are stack-level requirements, not optional polish
- Theme-owned front-end work should preserve or improve a WCAG 2.1 AA baseline where the stack controls markup, styles, and behavior
- Favor semantic markup, strong heading structure, usable labels, keyboard access, visible focus states, meaningful alternative text, usable contrast, and reduced-motion-safe behavior
- Treat page speed as a release concern for stack-owned pages; avoid unnecessary JavaScript, render-blocking assets, layout shift, oversized media, duplicate payloads, and other avoidable regressions
- Target Lighthouse/PageSpeed scores in the 90s or better on stack-owned pages when the stack controls the result
- If a third-party script, embed, or platform constraint prevents the target, document the blocker and do not make theme-owned output worse

Before making changes:
1. Identify what part of the system is being changed
2. Identify what dependencies may be affected
3. Make the smallest safe change
4. Note risks before making broad or cross-system changes

Working approach:
- Only use relevant context for the current task
- Do NOT assume full system memory is loaded
- For Media Library folder drag/drop regressions, verify browser and user-session state before changing stack code. Firefox failures can reproduce across multiple media-folder plugins even when Chrome/Safari work, and a saved local `View Admin As` override on the `admin` user can block manual Media folder drag/drop until cleared.

Deployment habits to preserve:
- Record the exact approved release SHA before deploy, even when the approved release is a local commit that is ahead of `origin/main`
- For live site deploys, resolve the site owner first and use the emitted site-owner SSH verify command before any fallback access path
- Use direct site-owner writes for live site refreshes; prefer the explicit `<site-user>@mrndev-site-owner` form when a helper needs a direct SSH host
- For live theme deploys, refresh the active site theme directory and preserve the live stylesheet slug, `Theme Name`, and `Text Domain` unless the handoff explicitly changes the theme architecture
- Fresh site bootstrap now owns direct site-owner SSH authorization by ensuring `/home/<site-user>/.ssh/authorized_keys` contains the canonical MRN site-owner public key from `stack/configs/site-owner-authorized-key.pub`; older sites may still need a one-time backfill
- Canonical live-site preflight helper is `/Users/khofmeyer/Development/MRN/stack/scripts/preflight-live-site-deploy.sh`, and stack feature deploys should call it before any live refresh
- For Updraft preflight backups, run a full `plugins,themes,uploads,others` plus database backup and only normalize malformed placeholder `0` or empty-array values if they reappear
- Updraft settings imports should strip placeholder `"0"` and empty-string array rows for `updraft_service` and reporting/email options so new sites do not inherit broken backup config
- The stack feature deploy helper now strips inherited/default ACLs from the live rollout-owned theme, shared runtime, and MU runtime trees before enforcing `644` representative file modes
- After live file sync plus chmod normalization, run `stat` on representative changed files and re-fix any unexpected mode before calling the deploy complete

If unclear:
- ask before making large or cross-system changes

Thread notes (2026-04-17 layout contract rollout):
- Standardized a shared primary layout contract across top-level builder layouts and nested non-link repeaters:
  - Name (admin use only), Label, Tag, Heading, Tag, Subheading, Tag, Text, Links
- Removed legacy admin label suffix text such as `(full editor)`, `(allowed html)`, and `(tag chooser)`.
- Normalized heading-tag field labels to `Tag` and applied the side-by-side text/tag pattern recursively through repeaters/subfields.
- Ensured non-link repeaters use `Content | Configs | Effects`, with grouped/collapsible config sections and `Effects` kept in its own tab.
- Standardized repeater naming (for example `Grids`) and added zebra striping for repeater/subfield scanability.
- Kept link controls on link-specific tabs, removed link `Background Color`, defaulted icon source to empty, and removed icon spacing when icon is not set.
- Added a lightweight non-ACF row-level `Layout` tab for flex controls (to protect editor performance).
- Added `Apply To` scope for row flex (`Row` vs `Repeaters Only`) so repeater collections can be centered independently from the row intro/header shell.
- Added child-theme override support for row flex at both PHP and CSS layers:
  - PHP filters: `mrn_base_stack_builder_row_flex_payload`, `mrn_base_stack_builder_row_flex_settings`, `mrn_base_stack_builder_flex_contract`
  - CSS override variables: `--mrn-row-flex-*-override` family
