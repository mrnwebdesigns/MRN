# Server Config Tracking

This directory stores tracked server configuration snapshots and source files for stack operations.

Goals:
- Keep server runtime configuration visible in Git.
- Make pull/push operations repeatable.
- Separate writable user-level config from root-owned config.

Use:
- `stack/scripts/sync-mrndev-server-config.sh --pull`
- `stack/scripts/sync-mrndev-server-config.sh --push`

Notes:
- `--push` only deploys entries marked writable in the manifest.
- Root-owned files are tracked as pull-only snapshots unless root deployment access is explicitly added.
