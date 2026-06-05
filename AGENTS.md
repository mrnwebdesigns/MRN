WORKING RULES

Scope:
- Work on one feature/task at a time
- Do not modify unrelated systems

Implementation:
- Prefer existing helpers, APIs, and contracts
- Avoid duplicating logic across theme/plugins
- Keep changes minimal and targeted

WordPress rules:
- Respect Classic Editor workflows
- Separate admin behavior vs frontend rendering
- Use proper:
  - sanitization
  - escaping
  - nonce/capability checks

Product quality:
- Accessibility and frontend performance are required, not optional polish
- Theme-owned frontend work should preserve or improve a WCAG 2.1 AA baseline where the stack controls markup, styles, and behavior
- Favor semantic markup, strong heading structure, labels, keyboard access, visible focus, usable contrast, meaningful text alternatives, and reduced-motion-safe behavior
- Optimize stack-owned pages for Lighthouse/PageSpeed scores in the 90s or higher when the stack controls the result
- Avoid unnecessary JavaScript, render-blocking assets, layout shift, oversized media, duplicate payloads, and other avoidable regressions
- If a third-party dependency blocks a target, document the blocker and avoid making theme-owned output worse

Safety:
- Assume shared components affect multiple features
- Treat site updates as coordinated stack changes when rendering, helpers, or theming hooks are involved
- Treat cloned live site themes as the default stack runtime surface until a site is explicitly handed to the development/front-end team for child-theme setup
- Avoid breaking:
  - builder layouts
  - reusable blocks
  - theme rendering contracts
  - live site styling hooks such as stable classes, CSS variables, and data attributes

When fixing issues:
1. Identify root cause
2. Change only necessary code
3. Avoid full rewrites unless required
4. Explain impact and risks

Release baseline:
- For "Run QA", "MRN QA", plugin QA, theme QA, file QA, or release QA, use the MRN QA Engine.
- Preferred command: `mrn-qa run --project-root /Users/khofmeyer/Development/MRN`
- For whole plugin/theme/directory QA, use `MRN_QA_CODE_ANALYSIS_SCOPE=all mrn-qa run --project-root /Users/khofmeyer/Development/MRN`
- For release/signoff QA, use `mrn-qa run --project-root /Users/khofmeyer/Development/MRN --mode release --smoke-strict 1`
- Let MRN QA auto gates decide when to run WordPress best practices, API surface/runtime, accessibility, performance, browser smoke, and security checks.
- WordPress API QA is required coverage: REST routes, admin-ajax, admin-post, permission callbacks, nonces, capabilities/auth, sanitization, escaping, and `/wp-json/` runtime health when applicable.
- Accessibility QA is required coverage: axe-core WCAG A/AA scans when runtime is available/applicable, semantic markup, headings, labels/control names, image alt text, keyboard/focus risk, visible text/link names, and WCAG 2.1 AA baseline where MRN controls output.
- PHP linting
- diff check
- security review (nonce, sanitize, escape)
- accessibility review
- performance review
- stack plugin parity check for UI/runtime-dependent releases (`stack/scripts/audit-config-helper-parity.sh`)
- site-owner deploy readiness check (site-owner SSH + write access for the target live plugin/theme paths)
