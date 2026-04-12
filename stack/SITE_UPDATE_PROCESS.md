# Site Update Process

This document defines the standard process for updating an MRN site when the codebase changes.

## Core Rule

Updating a site is not a plugin-only action.

Because MRN sites are built from coordinated plugins, MU plugins, shared runtime files, and the stack theme, every site update should evaluate whether the parent theme also needs to be updated.

For future sites, assume:

- the shared stack theme is the parent theme
- the site-specific visual layer lives in a child theme

That means shared code updates should protect the child theme's theming hooks whenever practical.

## Stable Theming Contract

Do not change these parent-theme theming hooks unless the change is truly necessary and documented:

- CSS class names used as child-theme styling targets
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
   - Prefer the canonical helper:
     `/Users/khofmeyer/Development/MRN/stack/scripts/preflight-live-site-deploy.sh --site-hostname <site-hostname>`
   - Run a full Updraft backup for `plugins`, `themes`, `uploads`, `others`, and database before deploying.
   - If Updraft storage/report settings contain placeholder values such as `"0"` or empty-string array entries, normalize only those placeholders and rerun the backup until the latest log is clean.
3. Review theme impact.
   - If rendering, template structure, helper output, classes, variables, or accent hooks changed, include the parent theme update in the rollout plan.
4. Check child-theme compatibility.
   - Assume future sites theme through a child theme.
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
   - Note any required site follow-up, especially if a child theme must be adjusted.

## When A Breaking Theming Change Is Allowed

Only make a theming-contract change when one of these is true:

- the old hook is incorrect or blocks required functionality
- the old hook creates a real maintenance or performance problem
- the new contract materially improves the shared system and cannot be introduced additively

If that happens:

- document the change before release
- list the affected classes, variables, or hooks
- note the expected child-theme follow-up
- add language for the master Google Doc

## Master Google Doc Copy

Use this wording in the master Google Doc:

> Site updates should be treated as stack updates, not plugin-only swaps. When code changes are prepared for a site, review the affected plugins, MU plugins, shared runtime files, and the shared parent theme together. Future sites should use a child theme for site-specific styling and branding, so parent-theme updates should preserve stable theming hooks whenever possible. Do not rename or remove shared class names, CSS variables, data attributes, or other styling hooks unless the change is necessary and documented in the rollout notes. If a breaking theming change is required, the update plan must include the parent-theme change, any needed child-theme follow-up, and documentation updates for the master Google Doc.
