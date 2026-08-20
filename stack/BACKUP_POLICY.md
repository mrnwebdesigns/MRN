# MRN Stack Backup Policy

## Scheduled Backups

- Run one combined Updraft file and database backup per day on staging and
  production.
- Development/review environments (any host `mrn-environment-runtime`
  classifies as `non_production`, for example `*.mrndev.io`) skip this routine
  daily schedule instead of running it: `mrn-updraft-local-retention` enforces
  `updraft_interval`/`updraft_interval_database` as `manual` there rather than
  `daily`. This only turns off the time-based cron; it does not change the
  Deployments gate below, which still runs unconditionally on every
  environment, including development/review.
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

## Deployments

- Before every non-dry-run write to a shared development, staging, or production
  WordPress runtime, create and verify a database-only remote backup. This
  includes code-only theme, plugin, and MU-plugin deployments. This applies
  unconditionally, including development/review environments that otherwise
  skip the routine daily schedule above — it protects against the write
  itself, not time-based drift, so it does not depend on the scheduled-backup
  cadence.
- Use Git for code rollback; the database backup protects WordPress state,
  configuration, and schema that can still be affected during plugin loading.
- Deployment helpers must pass `--with-db-backup`. `--skip-backup` is allowed
  only for a dry run or a genuinely read-only readiness check.
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
