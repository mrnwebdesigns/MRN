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

- Do not sync directly into live site `wp-content` paths as `mrn-ops`, `kyle`, or any other operator user.
- Live site theme/plugin/MU refreshes should run as the destination site owner.
- Preferred pattern for live site file syncs:

```bash
rsync -rlt --omit-dir-times --delete \
  --rsync-path='sudo -n -u <site-user> rsync'
```

- Avoid preserving local owner/group/permission metadata onto live site paths.
  - Use content-only sync flags such as `-rlt` instead of `-a` when syncing into live site directories.
  - Then normalize directories to `755` and files to `644` as the site owner.
- Current canonical helper for live theme refreshes:
  - `/Users/khofmeyer/Development/MRN/stack/scripts/deploy-live-theme.sh`
- Required server-side sudoers policy:
  - `mrn-ops` needs `NOPASSWD` access to run at least:
    - `/usr/bin/rsync`
    - `/usr/bin/find`
    - `/usr/bin/chmod`
    - `/usr/bin/perl`
  - and it must be allowed to run those commands as the relevant site owner user, not only as `mrndev-stack-manager`
- If that sudoers access is missing, stop and fix the server policy first rather than falling back to direct `mrn-ops` writes.

## Theme Rollout Rule

- The stack theme is a controlled MRN starter theme:
  - `mrn-base-stack`
- The stack should install the packaged theme zip, not a bare wp.org slug.

Current expected manifest pattern:

```text
/home/mrndev-stack-manager/stack/themes/mrn-base-stack.zip|active
```

The stack does not pull fresh `_s` from upstream on each rollout.

## Bootstrap Execution Rule

- Dry runs and stack execution should run as:
  - `mrndev-stack-manager`
- Running stack automation as a personal user can create noisy permission warnings and ownership drift.

## Packaging / Update Rule

When updating stack-managed assets:

1. update the canonical local source
2. package when needed
3. sync the correct source or artifact to `/home/mrndev-stack-manager/stack`
4. verify the live server copy after sync

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
