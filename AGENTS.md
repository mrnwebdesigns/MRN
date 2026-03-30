# Project Instructions

## Startup Context
- Read `/Users/khofmeyer/Development/MRN/THREAD_MEMORY.md` first in every new thread before performing work.
- Use `THREAD_MEMORY.md` as continuity context across threads.
- If `THREAD_MEMORY.md` conflicts with the current user request, ask which source should take precedence.

## Pinned Instructions (UI Copy Block)
- Use this exact pinned block in the project UI:
```text
Always read /Users/khofmeyer/Development/MRN/THREAD_MEMORY.md first for project context.
Then follow /Users/khofmeyer/Development/MRN/AGENTS.md.
If memory and current request conflict, ask which should win.
```

## Agent Behavior
- Keep edits scoped and minimal.
- Prefer concrete file paths and exact commands in summaries.
- Update `/Users/khofmeyer/Development/MRN/THREAD_MEMORY.md` when durable decisions are made.

## Current Source Of Truth (2026-03-30 VS Code Migration)
- Workspace root: `/Users/khofmeyer/Development/MRN`.
- Startup order remains mandatory:
  - read `/Users/khofmeyer/Development/MRN/THREAD_MEMORY.md` first
  - then read `/Users/khofmeyer/Development/MRN/AGENTS.md`
- Active implementation scope is the stack theme:
  - `/Users/khofmeyer/Development/MRN/stack/themes/mrn-base-stack`
- Current phase focus:
  - modernize builder/page shell behavior
  - enforce shared `Section Width` (`Content`, `Wide`, `Full Width`)
  - keep `After Content` as a placement bucket using the same layout vocabulary as `Content`
  - centralize wrapper logic in shared helpers
  - use seeded local QA pages as the acceptance harness
- Next implementation direction:
  - treat width-class rendering as solved
  - improve visual expression of width modes by layout family (starting with `Basic` and likely `Image Content`)
  - avoid one-off random layout patching
  - continue updating `THREAD_MEMORY.md` when durable decisions change

## Packaging Definition
- To "package" a plugin, perform all of the following:
- Do a security check.
- Check code quality.
- If new code was added, bump to a new version.
- Commit to git.
- Push to GitHub.
- Create a zip file.
- Minimum packaging verification checklist:
- Run `php -l` on every changed PHP file.
- Run `git diff --check` in the plugin repo.
- Run a lightweight risky-pattern scan for common unsafe functions such as `eval`, `base64_decode`, `exec`, `shell_exec`, `system`, `passthru`, `proc_open`, `popen`, remote write/delete helpers, and similar high-risk patterns.
- Review capability checks, nonce usage, and sanitization/escaping for any new admin forms, AJAX handlers, or settings saves.
- If the plugin has a relevant test or verification command already available, run it before release.
- Rebuild the zip only after the source and version are final, then verify the packaged main plugin file header/version inside the zip when practical.
