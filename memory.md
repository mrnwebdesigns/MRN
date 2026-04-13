MRN PROJECT CONTEXT

This is a WordPress-based website building platform.

Key facts:
- Uses Classic Editor (NOT Gutenberg)
- Built as a modular system using:
  - stack theme (mrn-base-stack)
  - plugins + MU plugins
  - shared runtime code
- Many features are interconnected across admin, frontend, and APIs

Core architecture rules:
- Theme = layout, rendering, builder system
- Plugins = features, integrations, admin behavior
- MU plugins = shared runtime and cross-cutting logic
- Site Styles = design tokens (colors, accents)
- Config Helper = global settings + social data

Critical constraints:
- Do NOT break existing builder behavior
- Do NOT break shared theme hooks (CSS classes, variables, data attributes)
- Treat site updates as stack updates, not plugin-only swaps
- Future sites should use a child theme for site-specific styling and branding
- Always assume changes can affect multiple system areas
- Prefer existing helpers/contracts over new logic

Working approach:
- Only use relevant context for the current task
- Do NOT assume full system memory is loaded
- Always identify:
  1. what part of the system is being changed
  2. what dependencies might be affected
  3. the minimal safe change
- For Media Library folder drag/drop regressions, verify browser and user-session state before changing stack code. Firefox failures can reproduce across multiple media-folder plugins even when Chrome/Safari work, and a saved local `View Admin As` override on the `admin` user can block manual Media folder drag/drop until cleared.

Deployment habits to preserve:
- Record the exact approved release SHA before deploy, even when the approved release is a local commit that is ahead of `origin/main`
- For live site deploys, resolve the site owner first and use the emitted site-owner SSH verify command before any fallback access path
- Use direct site-owner writes for live site refreshes; prefer the explicit `<site-user>@mrndev-site-owner` form when a helper needs a direct SSH host
- Fresh site bootstrap now owns direct site-owner SSH authorization by ensuring `/home/<site-user>/.ssh/authorized_keys` contains the canonical MRN site-owner public key from `stack/configs/site-owner-authorized-key.pub`; older sites may still need a one-time backfill
- Canonical live-site preflight helper is `/Users/khofmeyer/Development/MRN/stack/scripts/preflight-live-site-deploy.sh`, and stack feature deploys should call it before any live refresh
- For Updraft preflight backups, run a full `plugins,themes,uploads,others` plus database backup and only normalize malformed placeholder `0` or empty-array values if they reappear
- Updraft settings imports should strip placeholder `"0"` and empty-string array rows for `updraft_service` and reporting/email options so new sites do not inherit broken backup config
- After live file sync plus chmod normalization, run `stat` on representative changed files and re-fix any unexpected mode before calling the deploy complete

If unclear:
- ask before making large or cross-system changes
