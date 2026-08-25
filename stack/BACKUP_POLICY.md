# MRN Stack Backup Policy

## Scheduled Backups

- Run one combined Updraft file and database backup per day for shared
  development/review, staging, and production sites.
- Retain four scheduled backup sets locally and remotely.
- Exclude WordPress core because it is reproducible.
- Delete local archives after successful remote transfer.
- Assign each site a deterministic time between 01:00 and 04:59 so shared
  servers do not start every backup at midnight.
- Store each site in its own S3 prefix: `bucket/sites/<site-slug>`, where
  `<site-slug>` is the sanitized first label of the hostname (for example
  `trilliant` for `trilliant.mrndev.io`), not the full hostname. This keeps one
  stable prefix for a site's backup history as it moves between environments
  (`trilliant.localhost` locally, `trilliant.mrndev.io` in review, an eventual
  production domain) instead of fragmenting per environment/TLD.

## Local Development Exception

- LOCAL means a site managed by Local Hub and served from a local runtime, such
  as a `.localhost` URL or a manifest with a `local-*` runtime.
- Local sites are disposable development environments. Do not schedule
  Updraft backups, send local backups to S3, provision a local S3 prefix, or
  require a backup gate before code, theme, plugin, or MU-plugin writes.
- Git is the rollback mechanism for local code. Treat local database/content
  snapshots as explicit, temporary opt-in operations only when a destructive
  migration or other exceptional data change warrants one. Keep those
  snapshots local, label them, and remove them after the work is verified.
- This exception does not apply to externally reachable development/review
  sites such as `mrndev.io`, staging, or production.

## Deployments

- Before every non-dry-run write to a shared development, staging, or production
  WordPress runtime, create and verify a database-only remote backup. This
  includes code-only theme, plugin, and MU-plugin deployments.
- LOCAL deployments are excluded from this remote-backup gate under the Local
  Development Exception above.
- Use Git for code rollback; the database backup protects WordPress state,
  configuration, and schema that can still be affected during plugin loading.
- Deployment helpers must pass `--with-db-backup` for shared development,
  staging, and production deployments. `--skip-backup` is allowed for LOCAL
  deployments and for dry-run or genuinely read-only readiness checks.
- Explicit pre-deploy backups are database-only, are labeled, and remain under
  normal retention. They are not marked `always_keep`.
- QA does not create the backup. QA reports whether the backup gate and runtime
  prerequisites are ready; the deployment job performs and verifies the backup
  immediately before its first remote write.

## Development Workflow

- Routine scheduled and manual backups share the rolling four-set retention.
- Use **Always Keep** only for a named milestone before risky work.
- Remove Always Keep protection when that milestone is no longer useful.
- Never scan a shared bucket root; remotely scanned imports are exempt from
  Updraft's normal retention.
- Provision every development site with a unique S3 prefix ending in
  `sites/<site-slug>` (the site's stable slug, not its per-environment
  hostname). Development sites do not have to be enrolled in MainWP; use the
  dedicated site-owner SSH path when they are managed directly.

## Restores and Cleanup

- Restoring a database can restore old Updraft history without restoring the
  corresponding WP-Cron rows. The MRN Updraft Backup Policy MU plugin repairs
  missing file and database cron events.
- Never delete a shared remote backup until its site ownership is proven.
- When separating a legacy shared S3 prefix, preserve the old storage instance
  as disabled until required historical backups have been audited.
- Export `updraft_backup_history` before removing stale history rows.
- A remote rescan should target only the site's unique S3 prefix.

## Recommended Retention Exceptions

Create a separately documented archive outside normal Updraft retention for a
legal, launch, or migration milestone. A temporary `always_keep` backup is
acceptable for an explicitly named risky-development milestone, but it must be
reviewed and unprotected when no longer useful. Do not use `always_keep` for
routine deployments.
