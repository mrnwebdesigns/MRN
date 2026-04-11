# Stack Operations

This document explains the operational rules for local development, stack syncing, and server-side stack updates.

## Canonical Paths

- Workspace root:
  - `/Users/khofmeyer/Development/MRN`
- Live server stack root:
  - `/home/mrndev-stack-manager/stack`

## Local Development Rule

The local MRN test site should point to canonical source via symlinks.

### Plugins

- MRN normal plugins should symlink to:
  - `/Users/khofmeyer/Development/MRN/plugins/`

### MU Plugins

- MRN MU plugins should symlink to:
  - `/Users/khofmeyer/Development/MRN/mu-plugins/`

### Theme

- The local active stack theme slug should symlink to:
  - `/Users/khofmeyer/Development/MRN/stack/themes/mrn-base-stack`

This is the preferred local workflow because it keeps local testing aligned with the real source of truth.

## Server Ownership Rule

- Stack-owned files should be written as:
  - `mrndev-stack-manager:mrndev-stack-manager`
- Final site files should be owned by the site owner, not by a personal SSH user.

## Server User Rule

- Use the neutral ops user flow for routine work:
  - `mrn-ops`
  - local SSH alias: `mrndev-ops`
- `kyle` should be treated as fallback/admin access, not the normal write path.

## Sync Rule

Preferred sync pattern:

```bash
rsync --rsync-path='sudo -n -u mrndev-stack-manager rsync'
```

This ensures stack files are written as the app owner instead of a personal operator user.

## Live Site Rule

- Resolve the live site owner first with:
  - `/Users/khofmeyer/Development/MRN/stack/scripts/resolve-live-site-owner.sh <site-hostname>`
- Treat the resolver output as part of the deploy contract:
  - `SITE_USER`
  - `SITE_ROOT`
  - `SSH_ALIAS`
  - `SSH_LOGIN`
  - `SSH_VERIFY`
- Owner resolution alone is not enough.
  - Run the emitted `SSH_VERIFY` path and confirm the site-owner alias/key works before using any fallback access path.
  - If direct site-owner SSH fails, diagnose the alias and `IdentityFile` first.
  - Do not substitute `kyle` or `mrn-ops` as the write path for a live site refresh.
- When using direct site-owner SSH with a deploy helper, prefer the explicit host form:
  - `<site-user>@mrndev-site-owner`
- Do not sync directly into live site `wp-content` paths as `mrn-ops`, `kyle`, or any other operator user.
- Live site theme/plugin/MU refreshes should run as the destination site owner.
- Preferred pattern for live site file syncs:
  - from an ops/stack user with site-owner sudo:

```bash
rsync -rlt --omit-dir-times --delete \
  --rsync-path='sudo -n -u <site-user> rsync'
```

  - verified fallback when site-owner sudo is not available yet:

```bash
rsync -rlt --omit-dir-times --delete \
  /local/source/ \
  <site-user>@<host>:/absolute/live/path/
```

- Avoid preserving local owner/group/permission metadata onto live site paths.
  - Use content-only sync flags such as `-rlt` instead of `-a` when syncing into live site directories.
  - Then normalize directories to `755` and files to `644` as the site owner.
- Before any individual-site live write, run a full Updraft backup from the site-owner context:
  - `wp updraftplus backup --include-files='plugins,themes,uploads,others' --send-to-cloud --always-keep --label='<label>'`
- Treat malformed Updraft placeholder values as part of deploy readiness.
  - Check `updraft_service`, `updraft_email`, `updraft_report_warningsonly`, `updraft_report_wholebackup`, and `updraft_report_dbbackup`.
  - If those values contain placeholder entries such as `"0"` or empty-string array values, remove only the placeholders, keep the real storage backend, and rerun the same backup until the latest log is clean.
- If there is no dedicated helper for the exact live site change, sync only the changed live surface instead of broad site-wide paths.
  - Example: for a single MU plugin release, sync only that MU plugin directory as the site owner.
- After the broad normalization pass, run `stat` on representative changed files.
  - Do not assume a successful recursive `chmod` means every changed file landed with the expected mode.
  - If a representative file still shows an unexpected mode, fix that exact file and re-verify before calling the deploy done.
- Current canonical helper for live theme refreshes:
  - `/Users/khofmeyer/Development/MRN/stack/scripts/deploy-live-theme.sh`
- Current canonical helper for stack feature deploys that should also refresh `default-configs.mrndev.io`:
  - `/Users/khofmeyer/Development/MRN/stack/scripts/deploy-feature-stack-and-default-configs.sh`
- Use the feature deploy helper when stack theme or stack MU plugin work needs to stay mirrored to the stack server and the `default-configs` site in one step.
- The feature deploy helper must also mirror `/Users/khofmeyer/Development/MRN/shared` into `wp-content/shared` on `default-configs.mrndev.io` because settings-style sticky bars and other shared runtime helpers load from that path.
- The feature deploy helper must sync the local stack theme into the live site's active stylesheet directory, not just `/wp-content/themes/mrn-base-stack/`, because `default-configs.mrndev.io` may still run a cloned active theme slug such as `default-configs`.
- The feature deploy helper must verify its post-sync permission normalization and fail if sync-user-owned live files remain outside `644`.
- Standard plugins still follow their own plugin release flow and are not part of the stack feature deploy helper.
- When a stack-packaged standard plugin changes, rebuild its local zip, sync that artifact into `/home/mrndev-stack-manager/stack/packages/<plugin>.zip`, and if the plugin is meant to be live on `default-configs.mrndev.io`, run a forced `wp plugin install ... --force --activate` against that site so the live version matches the refreshed package.
- Fresh site bootstrap must delete any preinstalled standard plugins from the host before installing the stack manifest so new sites match the stack plugin set exactly.
- Fresh site bootstrap must also sync the shared runtime into `wp-content/shared` as part of the initial rollout.
- The helper now supports both modes:
  - default ops/stack-user sync with `sudo -n -u <site-user>`
  - direct site-owner SSH via `--direct-ssh`
- Required server-side sudoers policy:
  - `mrn-ops` needs `NOPASSWD` access to run at least:
    - `/usr/bin/rsync`
    - `/usr/bin/find`
    - `/usr/bin/chmod`
    - `/usr/bin/perl`
    - `/usr/bin/wp`
  - and it must be allowed to run those commands as the relevant site owner user, not only as `mrndev-stack-manager`
- Verified current gap on `2026-04-03` for `default-configs.mrndev.io`:
  - `mrndev-stack-manager` does not have site-owner sudo rights for live-site sync commands
  - `mrn-ops` can become `mrndev-stack-manager`, but it still does not have `sudo -n -u <site-user>` rights for `rsync/find/chmod/perl/wp`
- Until that sudoers policy is fixed, use direct site-owner SSH instead of writing live files as an operator user.
- `default-configs.mrndev.io` currently runs the cloned `default-configs` active stylesheet, so rollout verification must check the live active theme slug and version rather than assuming `mrn-base-stack`.

## Theme Rollout Rule

- The stack theme is a controlled MRN starter theme:
  - `mrn-base-stack`
- The stack should install the packaged theme zip, not a bare wp.org slug.

Current expected manifest pattern:

```text
/home/mrndev-stack-manager/stack/themes/mrn-base-stack.zip|active
```

The stack does not pull fresh `_s` from upstream on each rollout.
- Keep the packaged theme zip in the existing flat-root format with `style.css` at the archive root; current rollout QA reads the version directly from that location.

## Bootstrap Execution Rule

- Dry runs and stack execution should run as:
  - `mrndev-stack-manager`
- Running stack automation as a personal user can create noisy permission warnings and ownership drift.

## Packaging / Update Rule

When updating stack-managed assets:

1. update the canonical local source
2. decide whether the update also affects the shared parent theme, not only the originally targeted plugin or MU plugin
3. confirm stable child-theme hooks are still preserved, especially classes, CSS variables, data attributes, and other theming targets
4. package when needed
5. sync the correct source or artifact to `/home/mrndev-stack-manager/stack`
6. run `/Users/khofmeyer/Development/MRN/stack/scripts/qa-rollout-contract.sh` for stack theme, stack MU, bootstrap, or rollout-path changes that affect `default-configs.mrndev.io`
7. verify the live server copy after sync

For individual live-site deploys:

1. confirm the exact approved release state, including whether local is ahead of `origin/main`
2. take the required Updraft backup before writing live files
3. inspect backup output and settings for malformed placeholder values such as:
   - `updraft_email = ""`
   - report arrays stored as `["0"]`
4. normalize only those malformed reporting placeholders if they reappear
5. sync only the intended live site paths as `SITE_USER`
6. verify the changed runtime files are present and loaded after deploy

If a shared theming hook must change, treat it as a documented compatibility update and include downstream child-theme follow-up in rollout notes.

For full operator flow, use:

- `/Users/khofmeyer/Development/MRN/stack/ROLLOUT_CHECKLIST.md`

That checklist is the canonical pre-flight, deploy, and post-deploy parity reference.

## Local Test Site Rule

The local stack site is a development environment, not a second source of truth.

- Do not treat copied local site plugin/theme folders as canonical.
- If local site wiring drifts, repair the symlinks rather than editing the local copy by hand.

## Environment Variable Rule

WordPress environment type should be set deliberately because some stack-managed behavior changes by environment.

- Prefer `wp_get_environment_type()` as the canonical runtime source.
- If WordPress is not providing that yet, some MRN code falls back to `WP_ENV`.
- Current example:
  - `mrn-seo-helper` treats SEO Title and Meta Description as optional in `local` and `development`.
  - The plugin Tools UI can override that and force the fields back to required when needed.

## Secrets Rule

- Secrets belong in the stack secrets paths on the server.
- Do not store secret values in repo files or thread memory.

## Documentation Habit

When durable stack workflow or deployment rules change:

1. update this file
2. update `/Users/khofmeyer/Development/MRN/memory.md`
3. update release notes when the change affects rollout expectations
