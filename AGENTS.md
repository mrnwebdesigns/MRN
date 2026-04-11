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

Safety:
- Assume shared components affect multiple features
- Treat site updates as coordinated stack changes when rendering, helpers, or theming hooks are involved
- Avoid breaking:
  - builder layouts
  - reusable blocks
  - theme rendering contracts
  - child-theme styling hooks such as stable classes, CSS variables, and data attributes

When fixing issues:
1. Identify root cause
2. Change only necessary code
3. Avoid full rewrites unless required
4. Explain impact and risks

Release baseline:
- PHP linting
- diff check
- security review (nonce, sanitize, escape)
