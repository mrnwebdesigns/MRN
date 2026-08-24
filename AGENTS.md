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

Content ownership:
- Site content belongs in ACF or the appropriate WordPress data store, and is created and edited there. Never build a WordPress site whose content lives in code.
- Never fix a content issue by editing a hardcoded value in a theme, plugin, or template. Content in code cannot be edited by the client, diverges between environments, and turns every copy change into a developer task and a deploy.
- Theme-level fallback arrays and default strings are a safety net for an unset field, not the place where content is authored. If a live value appears to come from a fallback, the field is unset: populate the field and leave the fallback alone.
- Apply content changes as replayable scripts run with `wp eval-file` and commit them with the work, so the change is versioned and can be replayed to staging and production rather than re-entered by hand in each environment.
- Never hardcode an environment URL in content or in a content script. Resolve destinations at run time with WordPress APIs (`get_permalink()`, `get_page_by_path()`, `home_url()`), so the same value is correct on local, staging, and production.

WordPress site-building and QA content policy:
- Treat client-requested content and media changes as site-building work, not as disposable local edits. This applies to every MRN WordPress site, site project, and follow-up thread.
- Keep content and media separate from theme code. Do not make a direct edit inside `wp-content/uploads` the only record of an approved change, and do not treat a file in Downloads or another untracked folder as a deployable source of truth.
- For every approved Client Review or equivalent QA content/media change, create a tracked migration bundle with:
  - an idempotent `scripts/content/<change-id>-<slug>.php` script intended to run through `wp eval-file`;
  - the approved source asset(s), or a tracked manifest/checksum and documented source location when the assets are too large for the repository;
  - the exact target page, attachment, ACF field/repeater row, menu item, or other WordPress record;
  - required alt text, captions, labels, and any environment-independent lookup values.
- For ACF image fields and repeater rows, prefer importing the approved asset as a new attachment and updating the exact field reference. Preserve the old attachment until the replacement is verified; do not delete or orphan media as part of the first QA pass.
- If an existing attachment must be preserved for stable references, the migration must update the original file through WordPress-aware tooling, refresh attachment metadata and generated sizes, and verify the resulting URLs. A raw filename overwrite is not an acceptable substitute.
- Migration scripts must be safe to rerun: detect an already-applied change, refuse ambiguous matches, avoid hardcoded attachment IDs where a stable lookup exists, and report exactly what changed.
- The standard handoff sequence is: apply locally -> provide the local preview URL, original comment URL, and change summary -> receive owner approval -> run the smallest applicable MRN QA -> commit the migration bundle -> apply to the approved remote environment through the authorized deployment/content workflow -> verify the remote URL -> reply to the original review comment with the commit hash and verification summary.
- Local preview approval does not authorize a remote write. Before any staging, development, or production database/media mutation, pass the applicable backup gate, then run the same migration bundle and flush relevant caches/transients.
- Do not commit generated uploads, cache output, database dumps, or environment-specific runtime files as a substitute for a migration. The committed migration bundle is the audit trail and replay mechanism; the WordPress media library remains the runtime content store.
- For visual QA, use the supplied designer-approved asset rather than compensating for a mismatched asset with arbitrary CSS transforms, cropping, or per-item scaling. Verify the asset at the affected desktop, tablet, and mobile layouts when the component is responsive.

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
- Resolve each site's theme shape from its live `stylesheet` and `template` values before a theme deploy; both bootstraps now activate a child theme, so clone-style is not the default
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

Git hygiene gate:
- See the canonical Git hygiene gate in `docs/MRN-AGENT-OPERATING-CONTEXT.md` (section 11). It applies here as in every MRN repository: hanging branches or uncommitted work in a plugin/theme/MU-plugin/stack component are a hard stop on adding that component to a rollout, deploy, or `component-catalog.json` entry until resolved.

Release baseline:
- After changing a plugin, theme, MU-plugin, stack runtime code, or the QA engine, run the smallest relevant MRN QA suite before declaring the work complete. Use full release/signoff QA only when release readiness, deployment, or a user request requires it.
- Report QA rows that were intentionally skipped and why. Never describe a release as complete when a required runtime check is blocked or skipped.
- QA may inspect and report automatically, but it must not commit, push, deploy, or modify production without the user's explicit authorization.
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

MRN Updraft backup policy:
- Treat `stack/BACKUP_POLICY.md` as the canonical backup policy for all agent work in this repository.
- Stack-managed staging and production sites use daily Updraft file/database backups, `4/4` retention, local deletion after remote transfer, WordPress-core exclusion, deterministic 01:00-04:59 scheduling, and a unique S3 prefix ending in `sites/<sanitized-hostname>`.
- Development/review environments (for example `*.mrndev.io`) do not run that routine daily schedule; `mrn-updraft-local-retention` enforces `updraft_interval`/`updraft_interval_database` as `manual` there instead. The pre-deploy backup gate further below still applies unconditionally on every environment, including development/review — it protects against the write itself, not time-based drift, so it never depends on the scheduled-backup cadence.
- Development sites do not need to be added to MainWP. Use their dedicated site-owner SSH path when auditing or applying the policy manually.
- Routine scheduled backups on staging/production, and every manual or pre-deploy backup on any environment, stay inside the rolling four-set limit. Do not mark routine backups `always_keep`.
- Use **Always Keep** only for an explicitly named milestone before risky work, and remove that protection when the milestone is no longer useful.
- Never scan a shared S3 bucket root. Updraft treats backups discovered by remote scan as imported and exempts them from automatic retention.
- Never delete shared-root remote objects until ownership is proven. When correcting a legacy shared prefix, first isolate the site, then clear only stale local history or delete individually verified site-owned objects.
- Before any non-dry-run write to a shared development, staging, or production WordPress runtime, the deployment workflow must create and verify a labeled, database-only Updraft backup sent to the configured remote destination. A Git push or QA pass does not satisfy this gate by itself.
- QA remains read-only: it verifies backup-policy/deploy readiness and reports blockers. The deployment helper or deployment job performs the required backup immediately before the write.
- If a push triggers automatic deployment, verify that the deployment job contains the backup-and-verification gate before allowing the push/deploy workflow to proceed.

## Global MRN operating context

For MRN-wide conventions (Local Hub vs Production Hub, access strategy, SSH/deployment safety), load:
- /Users/khofmeyer/Development/MRN/docs/MRN-AGENT-OPERATING-CONTEXT.md

Project-specific instructions in this repo still apply as overrides in their scope.
