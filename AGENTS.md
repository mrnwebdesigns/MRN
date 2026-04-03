# Project Instructions

## Startup
- Read `/Users/khofmeyer/Development/MRN/memory.md` first.
- Then read `/Users/khofmeyer/Development/MRN/AGENTS.md`.
- If memory and the current request conflict, ask which should win.

## Pinned UI Copy
```text
Always read /Users/khofmeyer/Development/MRN/memory.md first for project context.
Then follow /Users/khofmeyer/Development/MRN/AGENTS.md.
If memory and current request conflict, ask which should win.
```

## Memory Navigation
- Active work: `/Users/khofmeyer/Development/MRN/memory/current.md`
- Stable architecture: `/Users/khofmeyer/Development/MRN/memory/spec/architecture.md`
- Conventions and packaging rules: `/Users/khofmeyer/Development/MRN/memory/spec/conventions.md`
- Helper contracts and API expectations: `/Users/khofmeyer/Development/MRN/memory/spec/api-rules.md`
- Historical reference: `/Users/khofmeyer/Development/MRN/memory/archive/`

## Working Rules
- Keep edits scoped and minimal.
- Prefer concrete file paths and exact commands in summaries.
- Active implementation scope is usually `/Users/khofmeyer/Development/MRN/stack/themes/mrn-base-stack`.
- Use shared helpers and existing contracts before adding one-off behavior.
- Update the memory system when durable decisions change:
  - `memory/current.md` for active changes
  - `memory/spec/` for stable rules
  - `memory/archive/` for completed or historical context

## Packaging
- Packaging means:
  - security check
  - code-quality check
  - version bump when new code was added
  - git commit
  - GitHub push
  - zip creation
- Minimum release verification:
  - `php -l` on every changed PHP file
  - `git diff --check`
  - risky-pattern scan for unsafe functions
  - review nonce, capability, sanitization, and escaping coverage for new admin or save flows
  - run any relevant existing verification command
  - verify packaged version/header when practical
- `Perform a release flow` is defined in:
  `/Users/khofmeyer/Development/MRN/memory/spec/conventions.md`
