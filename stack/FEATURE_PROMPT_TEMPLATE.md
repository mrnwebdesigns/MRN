# Stack Feature Prompt Template

Use this template when creating a new feature request, implementation prompt, or handoff ticket for the MRN stack.

The goal is to make sure new work starts with the right scope, ownership, accessibility, performance, and rollout expectations instead of adding those late in QA.

## Copy/Paste Template

```md
# Feature Request

## Objective
- Describe the user-facing or editor-facing outcome.

## System Area
- Theme / plugin / MU plugin / shared runtime / stack rollout
- Note whether the change affects admin UI, frontend rendering, saved data, integrations, or multiple areas

## Ownership
- Theme owns layout, rendering, and builder behavior
- Plugins own features, integrations, and admin behavior
- MU plugins own shared runtime and cross-cutting behavior
- Site Styles owns design tokens such as colors and accents
- Config Helper owns site-wide settings and social/config data

## Scope
- In scope:
- Out of scope:

## Existing Contracts To Preserve
- Preserve existing builder behavior unless explicitly changing it
- Preserve shared theme hook contracts, including CSS classes, CSS variables, data attributes, and helper output used elsewhere
- Prefer existing helpers, APIs, and established contracts over one-off logic
- Keep the change as small and safe as practical

## Dependency Review
- What part of the system is changing?
- What dependencies may be affected?
- Does this require coordinated theme/plugin/MU/shared rollout work?
- Are there risks to live cloned themes or later child-theme handoff?

## Theme Deployment Mode (Required for theme-owned rendering/hook changes)
- Is each target site clone-style mode (`stylesheet == template`) or child-theme mode (`stylesheet != template`)?
- Which theme directory receives the parent stack update (active template path)?
- Does this feature intentionally include child-theme changes (template overrides, child-only templates, or child CSS)?
- If child-theme changes are included, list exact child files and confirm why the change does not belong in shared parent/plugin/MU layers.
- Confirm the rollout plan does not sync stack parent source into a child stylesheet directory in normal deployment flow.

## Implementation Rules
- Respect Classic Editor workflows
- Separate admin behavior from frontend rendering
- Use proper sanitization, escaping, and nonce/capability checks
- Avoid broad rewrites unless they are truly necessary

## Accessibility Requirements
- Accessibility is required, not optional polish
- Theme-owned or admin-owned UI should preserve or improve a WCAG 2.1 AA baseline where the stack controls markup, styles, and behavior
- Use semantic markup and a sensible heading structure
- Ensure keyboard access and visible focus states
- Use proper labels, instructions, and text alternatives
- Maintain usable color contrast
- Respect `prefers-reduced-motion` where motion or autoplay behavior is involved
- Avoid introducing accessibility regressions in existing flows

## Performance Requirements
- Front-end performance is required, not optional polish
- Treat Lighthouse/PageSpeed scores in the 90s or better as the target for stack-owned pages when the stack controls the result
- Avoid unnecessary JavaScript, render-blocking assets, duplicate payloads, layout shift, oversized media, and other avoidable regressions
- Prefer built-in browser behavior and existing stack helpers over new runtime code when possible
- Defer, lazy-load, or conditionally load non-critical assets when practical
- If a third-party dependency blocks the target, document the blocker and do not make theme-owned output worse

## Acceptance Criteria
- Functional behavior:
- Builder/editor behavior:
- Frontend rendering:
- Accessibility:
- Performance:
- Rollout or compatibility notes:
- Parent vs child theme rollout decision:
- Theme target path(s) on live:
- Child-theme follow-up required after release?:

## Required Verification
- `php -l` on changed PHP files
- `git diff --check`
- Security review for nonce, capability, sanitize, and escape coverage
- Relevant local stack QA
- Theme deploy mode verification when theme output/contracts changed:
  - verify live `stylesheet` and `template` before deployment
  - confirm parent updates target the active template directory
  - confirm child updates are only included when explicitly in scope
- Playwright smoke only when scope requires it:
  - required: frontend rendering changes, login/auth flows, role/capability changes, or critical admin workflows
  - optional: routine admin-only visual/styling changes that do not affect auth, permissions, or data handling
- Accessibility review on affected flows
- Performance review on affected stack-owned pages
- PHPStan policy:
  - prefer `/Users/khofmeyer/Development/MRN/scripts/mrn-phpstan.sh` so checks use the shared MRN binary/config
  - required when the shared runner/config are available in the current environment
  - if not configured/available, mark `Skipped` with reason (not a release blocker by itself)

## Suggested Local QA
- `/Users/khofmeyer/Development/MRN/stack/scripts/qa-theme.sh`
- `/Users/khofmeyer/Development/MRN/stack/scripts/qa-security.sh`
- `/Users/khofmeyer/Development/MRN/scripts/mrn-smoke.sh --scope public` (default for non-auth/non-permission UI checks)
- `/Users/khofmeyer/Development/MRN/scripts/mrn-smoke.sh --scope full` (when auth/permissions/admin workflows are in scope)
- `/Users/khofmeyer/Development/MRN/stack/scripts/qa-playwright-local-stack-site.sh` (only when required by scope above)
- `/Users/khofmeyer/Development/MRN/stack/scripts/qa-page-speed.sh http://mrn-plugin-stack.local / /sample-page/`
- Lighthouse or equivalent browser performance review when the feature changes rendered output materially

## Blockers Or Exceptions
- Note any third-party, platform, or legacy constraint that prevents the desired implementation or score target
- Explicitly state what was protected from regression even if the full target could not be reached
```

## Short Prompt Version

Use this shorter version when you do not need the full template:

```md
Implement this as a minimal safe stack change. Preserve existing builder behavior and shared theme hook contracts. Prefer existing helpers and contracts over one-off logic. Respect Classic Editor workflows and separate admin behavior from frontend rendering. For theme-owned rendering or hook changes, explicitly decide clone-style vs child-theme deployment mode (`stylesheet` vs `template`), target parent updates to the active template path, and only include child-theme file updates when they are intentionally in scope. Treat accessibility and front-end performance as required: maintain a WCAG 2.1 AA baseline where the stack controls the UI, preserve keyboard access, focus visibility, labels, contrast, and reduced-motion-safe behavior, and avoid unnecessary JavaScript, render-blocking assets, layout shift, oversized media, and other avoidable regressions. Target Lighthouse/PageSpeed scores in the 90s or better on stack-owned pages when the stack controls the result. If a third-party dependency blocks that target, document the blocker and do not make theme-owned output worse. Include release verification for linting, diff check, security review, accessibility review, and performance review.
```

## Usage Notes

- Use the full template for new features, rollouts, and cross-system changes.
- Use the short version for focused follow-up tasks that still need the same quality bar.
- If the request changes theme-owned frontend output, do not omit the accessibility or performance sections.
- If theme rendering/hooks/contracts are in scope, do not skip the parent-vs-child deployment mode decision.
- If a request is admin-only, keep the accessibility section and scale the performance section to the actual UI impact.
