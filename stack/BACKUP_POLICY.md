# MRN Stack Backup Policy

## Scheduled Backups

- Run one combined Updraft file and database backup per day.
- Retain four scheduled backup sets locally and remotely.
- Exclude WordPress core because it is reproducible.
- Delete local archives after successful remote transfer.
- Assign each site a deterministic time between 01:00 and 04:59 so shared
  servers do not start every backup at midnight.
- Store each site in its own S3 prefix:
  `bucket/sites/<sanitized-hostname>`.

## Deployments

- Code-only theme, plugin, and MU-plugin deployments do not create backups.
- Use Git for rollback of site-owned and shared code.
- Use `--with-db-backup` only when a deployment changes stored data, runs a
  migration, or makes another non-code database change.
- Explicit pre-deploy backups are database-only, are labeled, and remain under
  normal retention. They are not marked `always_keep`.

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
legal, launch, or migration milestone. Do not use `always_keep` for routine
deployments.
