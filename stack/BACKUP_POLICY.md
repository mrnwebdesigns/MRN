# MRN Stack Backup Policy

## Scheduled Backups

- This scheduled remote-backup policy applies only to sites marked **Yes** for
  P&P in the MRN Website Information source. MainWP targets must be reconciled
  to that source by exact URL; a site without a verified **Yes** is excluded.
- For a site without **Yes**, the guarded MainWP decommission operation disables
  all Updraft remote destinations, sets routine file and database schedules to
  `manual`, and records an explicit policy-excluded mode so the MU plugin does
  not re-enable the policy or emit routine-retention warnings. It retains
  stored destination credentials in a disabled state and never deletes existing
  remote objects. Historical S3 cleanup is a separate, ownership-verified
  operation.
- Run one combined Updraft file and database backup per day on staging and
  production.
- An explicitly configured WordPress `staging` environment remains daily,
  including hosting-provider preview domains. Local/development environments
  remain manual. Other development/review environments (any host `mrn-environment-runtime`
  classifies as `non_production`, for example `*.mrndev.io`) skip this routine
  daily schedule instead of running it: `mrn-updraft-local-retention` enforces
  `updraft_interval`/`updraft_interval_database` as `manual` there rather than
  `daily`. This only turns off the time-based cron; it does not change the
  Deployments gate below, which still runs unconditionally on every
  environment, including development/review.
- Retain remote recovery points as seven daily, four weekly, and twelve
  30-day sets. UpdraftPlus keeps every backup for the first seven days, no
  more than one per seven-day period from day 8 through day 35, and no more
  than one per 30-day period from day 36 through day 395.
- Require UpdraftPlus Premium advanced retention. A site without the advanced
  retention engine is noncompliant and must not enter the rollout until that
  prerequisite is remediated.
- Use numerical safety caps of 23 file sets and 100 database sets. The larger
  database cap accommodates labeled pre-deploy database-only backups without
  displacing the scheduled recovery points before the time-bucket rules run.
- Exclude WordPress core because it is reproducible.
- Delete local archives after successful remote transfer.
- Cap stranded local archives at four complete sets. This is a local disk
  safety limit, not the remote recovery-point policy.
- Assign each site a deterministic time between 01:00 and 04:59 so shared
  servers do not start every backup at midnight.
- Store each site in its own S3 prefix: `bucket/sites/<sanitized-hostname>`, using
  the full hostname with punctuation replaced by hyphens (for example,
  `example-com` for `example.com`). During an approved domain transition, the
  non-secret `MRN_UPDRAFT_BACKUP_HOSTNAME` constant can bind validation and
  scheduling to the final hostname. This does not move archives or modify
  credentials. Disabled instances are excluded from validation; every active
  S3 destination must match the site's isolated prefix.

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

## Kinsta Native Provider Exception

- This exception applies only to an exact MainWP site/environment pair that is
  explicitly enrolled with the reviewed Kinsta native-backup provider. A
  Kinsta tag, the Kinsta MU plugin, SSH access, or a manually created Kinsta
  backup does not constitute enrollment.
- Enrollment records the exact MainWP site URL, Kinsta site ID, Kinsta
  environment ID, primary domain, environment class, and public-root identity.
  The provider must verify that every value resolves to the intended target
  before it can create a backup or issue a deployment receipt. Provider tokens
  and SSH credentials remain in approved secret storage and are never written
  to policy records, plans, receipts, command lines, or logs.
- A Kinsta-enrolled environment uses a native Kinsta manual snapshot for its
  pre-deployment gate. The snapshot covers files and database, is labeled with
  the deployment/request identity, and must be created immediately before the
  first remote write. The provider must poll Kinsta to successful completion
  and bind a short-lived receipt to the exact site, environment, snapshot, and
  request. A queued, failed, stale, mismatched, or externally created snapshot
  cannot satisfy this gate.
- A protected, durably journaled adapter may opt into the approved
  oldest-eligible MRN backup rotation rule. When the five manual slots are
  full, retire at most one manual snapshot whose ID/label is recorded in the
  authenticated MRN backup ledger and whose matching deployment has a signed
  terminal verification, completed rollback, or pre-activation abort receipt.
  Select by provider creation time, oldest first. Customer-created, untracked,
  explicitly protected milestone, active-run, failed-run, and unresolved-run
  snapshots are never eligible. A stale lease does not grant eligibility.
- Rotation requires the same persistent target journal, signing key and
  exclusive writer lease as deployment. Record intent before each API write,
  retain deletion/creation job IDs, verify the one selected deletion, then
  create and verify the replacement before any deployment write. Pending jobs
  resume by their recorded ID; an ambiguous write without a recorded job ID
  stops for reconciliation. A retry must not delete another snapshot.
- Rotation permission belongs in protected enrollment, never in a release
  artifact or an ephemeral GitHub workspace. It does not require approval for
  each ordinary rotation once enrolled. If no eligible tracked backup exists,
  stop for a deliberate owner retention decision; do not adopt existing
  snapshots from an MRN-looking label alone. First-time enrollment may need
  this one-time legacy-backup review. MainWP's standalone Kinsta backup button
  does not acquire this behavior automatically; managed requests must use the
  same protected controller and journal.
- A Kinsta-enrolled environment must not also be configured for the Updraft
  scheduled-backup or pre-deployment route. Its native snapshot is the sole
  deployment gate for that environment; this prevents competing writers,
  duplicate backup behavior, and one provider's receipt being used for another
  provider's write.
- Native Kinsta restores are emergency, owner-directed full-environment
  recovery operations. Routine code rollback continues to use the qualified
  immutable release/rollback mechanism and does not restore a database.
- Before a Kinsta provider is enabled for production, it must pass a controlled
  non-production qualification that proves identity validation, snapshot
  creation and completion polling, receipt expiry/replay rejection, failed
  snapshot handling, and recovery instructions. Each production enrollment
  requires explicit owner approval after its exact target mapping is reviewed.
- Until the reviewed Kinsta provider and the exact enrollment are available,
  deployment is blocked for that environment. Do not silently fall back to
  Updraft, direct SSH, a browser upload, or an unverified manual snapshot.

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
- Deployment helpers must pass `--with-db-backup` for shared development,
  staging, and production deployments. `--skip-backup` is allowed for LOCAL
  deployments and for dry-run or genuinely read-only readiness checks.
- Explicit pre-deploy backups are database-only, are labeled, and remain under
  normal retention. They are not marked `always_keep`.
- QA does not create the backup. QA reports whether the backup gate and runtime
  prerequisites are ready; the deployment job performs and verifies the backup
  immediately before its first remote write.
- For an enrolled Kinsta environment, the Kinsta Native Provider Exception
  replaces the database-only Updraft gate in this section. For every other
  shared development, staging, or production environment, the Updraft gate
  remains mandatory.

## Development Workflow

- Routine scheduled, manual, and pre-deploy backups share the time-bucketed
  retention policy. They must not be exempted from pruning.
- Use **Always Keep** only for a named milestone before risky work.
- Remove Always Keep protection when that milestone is no longer useful.
- Never scan a shared bucket root; remotely scanned imports are exempt from
  Updraft's normal retention.
- Provision every development site with a unique S3 prefix ending in
  `sites/<sanitized-hostname>`. Development sites do not have to be enrolled in MainWP; use the
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
