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

## Required Verification
- `php -l` on changed PHP files
- `git diff --check`
- Security review for nonce, capability, sanitize, and escape coverage
- Relevant local stack QA
- Accessibility review on affected flows
- Performance review on affected stack-owned pages

## Suggested Local QA
- `/Users/khofmeyer/Development/MRN/stack/scripts/qa-theme.sh`
- `/Users/khofmeyer/Development/MRN/stack/scripts/qa-security.sh`
- `/Users/khofmeyer/Development/MRN/stack/scripts/qa-playwright-local-stack-site.sh`
- `/Users/khofmeyer/Development/MRN/stack/scripts/qa-page-speed.sh http://mrn-plugin-stack.local / /sample-page/`
- Lighthouse or equivalent browser performance review when the feature changes rendered output materially

## Blockers Or Exceptions
- Note any third-party, platform, or legacy constraint that prevents the desired implementation or score target
- Explicitly state what was protected from regression even if the full target could not be reached
```

## Short Prompt Version

Use this shorter version when you do not need the full template:

```md
Implement this as a minimal safe stack change. Preserve existing builder behavior and shared theme hook contracts. Prefer existing helpers and contracts over one-off logic. Respect Classic Editor workflows and separate admin behavior from frontend rendering. Treat accessibility and front-end performance as required: maintain a WCAG 2.1 AA baseline where the stack controls the UI, preserve keyboard access, focus visibility, labels, contrast, and reduced-motion-safe behavior, and avoid unnecessary JavaScript, render-blocking assets, layout shift, oversized media, and other avoidable regressions. Target Lighthouse/PageSpeed scores in the 90s or better on stack-owned pages when the stack controls the result. If a third-party dependency blocks that target, document the blocker and do not make theme-owned output worse. Include release verification for linting, diff check, security review, accessibility review, and performance review.
```

## Usage Notes

- Use the full template for new features, rollouts, and cross-system changes.
- Use the short version for focused follow-up tasks that still need the same quality bar.
- If the request changes theme-owned frontend output, do not omit the accessibility or performance sections.
- If a request is admin-only, keep the accessibility section and scale the performance section to the actual UI impact.
