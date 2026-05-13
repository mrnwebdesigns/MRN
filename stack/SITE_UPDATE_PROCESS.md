# Site Update Process

This document defines the standard process for updating an MRN site when the codebase changes.

## Core Rule

Updating a site is not a plugin-only action.

Because MRN sites are built from coordinated plugins, MU plugins, shared runtime files, and the stack theme, every site update should evaluate whether the live active theme also needs to be updated.

For stack-managed sites, assume:

- the site runs a cloned active theme directory based on the shared stack theme
- a child theme is introduced later only when the site is handed to the development/front-end team

That means shared code updates should preserve the live theme slug plus `Theme Name` and `Text Domain`, and should keep stable theming hooks available for later front-end handoff work whenever practical.

## Stable Theming Contract

Do not change these parent-theme theming hooks unless the change is truly necessary and documented:

- CSS class names used as live-theme styling targets
- CSS variables and token names
- data attributes used for styling or behavior hooks
- shared accent hooks
- helper or template outputs intentionally consumed by site theming

Prefer:

- additive classes over renamed classes
- additive CSS variables over renamed variables
- internal refactors over markup-contract changes
- new wrapper hooks over breaking old ones

## Standard Update Flow

1. Identify the scope of the change.
   - Confirm whether the work touches a normal plugin, MU plugin, shared runtime file, stack theme, or more than one layer.
2. Resolve the live site owner and back up first.
   - Resolve the target owner with `/Users/khofmeyer/Development/MRN/stack/scripts/resolve-live-site-owner.sh <site-hostname>`.
   - Verify the direct site-owner SSH path before any write.
   - New sites should receive that direct site-owner SSH authorization during stack bootstrap; if an older site still fails the verify step, treat it as a one-time site-owner `authorized_keys` backfill instead of a theme/plugin deploy bug.
   - Prefer the canonical helper:
     `/Users/khofmeyer/Development/MRN/stack/scripts/preflight-live-site-deploy.sh --site-hostname <site-hostname>`
   - Run a full Updraft backup for `plugins`, `themes`, `uploads`, `others`, and database before deploying.
   - If Updraft storage/report settings contain placeholder values such as `"0"` or empty-string array entries, normalize only those placeholders and rerun the backup until the latest log is clean.
3. Review theme impact.
   - If rendering, template structure, helper output, classes, variables, or accent hooks changed, include the parent theme update in the rollout plan.
4. Check live-theme compatibility.
   - Assume the site is running its cloned active theme directory unless a front-end handoff has already introduced a child theme.
   - If `stylesheet != template`, treat the site as child-theme mode and deploy stack parent source into the active parent template directory only.
   - Do not sync `stack/themes/mrn-base-stack` into a child stylesheet directory during normal rollout work.
   - The live theme helper now blocks this risky path by default; `--force-stack-source-child-overwrite` is emergency-only and requires rollout-note documentation.
   - Avoid renaming or removing theming hooks unless required.
5. Package the shared code.
   - Follow the project packaging and release-flow rules in memory and stack docs.
6. Update the target site.
   - Sync the changed plugins, MU plugins, shared runtime files, and parent theme as needed.
   - When using the live theme helper, pass `--site-hostname <site-hostname>` so it resolves the owner and runs the canonical preflight before syncing.
7. Verify the site.
   - Confirm functionality, front-end rendering, admin behavior, and any visual theming still behave correctly.
   - Verify representative live file ownership and mode when the deploy flow includes rsync/chmod steps.
8. Document the rollout.
   - Note any required site follow-up, especially if a later front-end handoff or child-theme setup must be adjusted.

## When A Breaking Theming Change Is Allowed

Only make a theming-contract change when one of these is true:

- the old hook is incorrect or blocks required functionality
- the old hook creates a real maintenance or performance problem
- the new contract materially improves the shared system and cannot be introduced additively

If that happens:

- document the change before release
- list the affected classes, variables, or hooks
- note the expected live-theme or later child-theme follow-up
- add language for the master Google Doc

## Master Google Doc Copy

Use this wording in the master Google Doc:

> Site updates should be treated as stack updates, not plugin-only swaps. When code changes are prepared for a site, review the affected plugins, MU plugins, shared runtime files, and the live active theme together. Stack-managed sites should be assumed to run a cloned active theme until they are explicitly handed to the development/front-end team for child-theme setup, so theme updates should preserve the live stylesheet slug, `Theme Name`, `Text Domain`, and stable theming hooks whenever possible. Do not rename or remove shared class names, CSS variables, data attributes, or other styling hooks unless the change is necessary and documented in the rollout notes. If a breaking theming change is required, the update plan must include the active-theme change, any needed later handoff follow-up, and documentation updates for the master Google Doc.
