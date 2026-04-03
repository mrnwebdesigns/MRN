# Conventions

## Startup Order
- Read `/Users/khofmeyer/Development/MRN/memory.md` first in every new thread.
- Then read `/Users/khofmeyer/Development/MRN/AGENTS.md`.
- If memory and the current request conflict, ask which should take precedence.

## Memory Maintenance
- Keep active tasks, blockers, and recent decisions in:
  `/Users/khofmeyer/Development/MRN/memory/current.md`
- Keep durable knowledge in:
  `/Users/khofmeyer/Development/MRN/memory/spec/`
- Move completed, outdated, and long thread history into:
  `/Users/khofmeyer/Development/MRN/memory/archive/`
- Keep active files short, contradiction-free, and easy to scan.

## Editing Principles
- Keep edits scoped and minimal.
- Prefer shared helpers over one-off patches.
- Preserve canonical ownership boundaries between theme, plugins, MU plugins, and shared sources.
- Treat width rendering and wrapper layering as solved infrastructure when adding new layouts.
- Improve layout-family expression through shared shell patterns rather than ad hoc CSS tweaks.

## Builder Rules
- Shared `Section Width` values are:
  `Content`, `Wide`, `Full Width`
- `After Content` should use the same layout vocabulary as `Content`.
- Hero remains a separate contract.
- Reusable block QA should explicitly verify mobile collapse for width-sensitive full-width layouts.

## Product Quality Standards
- Performance is a first-class requirement.
- SEO is a first-class requirement.
- Accessibility is a first-class requirement.
- Favor semantic markup, strong page structure, clean metadata, keyboard support, usable contrast, and avoidance of regressions.

## Packaging Definition
- To package a plugin or similar deliverable:
  - do a security check
  - check code quality
  - bump the version if new code was added
  - commit to git
  - push to GitHub
  - create the zip only after source and version are final
- Minimum verification checklist:
  - run `php -l` on every changed PHP file
  - run `git diff --check`
  - run a lightweight risky-pattern scan for high-risk functions such as `eval`, `base64_decode`, `exec`, `shell_exec`, `system`, `passthru`, `proc_open`, and `popen`
  - review capability checks, nonce usage, and sanitization or escaping for new admin forms, AJAX handlers, and settings saves
  - run any relevant existing test or verification command before release
  - verify the packaged main plugin file header and version when practical

## Release Flow Definition
- `Perform a release flow` means completing all of the following in order:
  - `QA`: check code for issues and do visual QA when possible or needed
  - `Check for performance`: review for performance regressions or obvious performance concerns
  - `Commit`: commit task-specific code to git with a simple commit message describing what was done
  - `Push`: push the new commit to GitHub
  - `Package`: create all necessary additions for the stack server and create the required local zip file
  - `Deploy`: deploy the code to the stack server
  - `QA`: verify the deployed code is in place, versions are correct, required configs are added to the manifest, and any new plugins are added to the manifest
  - `Document`: update memory and any other documentation needed to keep the project clean

## Deployment Guardrails
- CloudPanel stack files should be written as `mrndev-stack-manager:mrndev-stack-manager`.
- Default stack SSH target is `mrndev-stack-manager@167.99.54.77`.
- Stack site/server credential details are stored locally at `/Users/khofmeyer/Development/MRN/.local/secrets/default-configs-server-info.txt`.
- If work is for a specific project/site, request that site's server information instead of relying on the default stack SSH target.
- Preferred sync pattern:
  `rsync --rsync-path='sudo -n -u mrndev-stack-manager rsync'`
- When syncing into live site paths, do not preserve local owner, group, or mode metadata.
- Use content-oriented rsync flags such as `-rlt --omit-dir-times`, then normalize to `755` for directories and `644` for files as the site owner.
- Avoid leaving live files unreadable after manual or selective syncs.

## Reference Docs
- QA toolkit:
  `/Users/khofmeyer/Development/MRN/stack/QA.md`
- Builder conventions:
  `/Users/khofmeyer/Development/MRN/stack/BUILDER_CONVENTIONS.md`
- Theme direction and handoff:
  `/Users/khofmeyer/Development/MRN/stack/THEME_ROADMAP.md`
- Tactical theme execution notes:
  `/Users/khofmeyer/Development/MRN/stack/THEME_TASKLIST.md`
- Stack baseline snapshot:
  `/Users/khofmeyer/Development/MRN/stack/STACK_VERSION.md`
- Stack changelog:
  `/Users/khofmeyer/Development/MRN/stack/CHANGELOG.md`
