# MRN Updraft Backup Policy

WordPress plugin release unit for `mrn-updraft-local-retention`.

The MU plugin enforces the non-secret MRN Updraft policy on every stack runtime:

- daily file and database backups on staging and production;
- no routine scheduled backups on development/review environments;
- seven daily, four weekly, and twelve 30-day remote recovery points retained;
- file/database numerical safety caps of 23/100;
- explicit noncompliance reporting when UpdraftPlus Premium advanced retention
  is unavailable;
- local archives deleted after successful remote transfer;
- WordPress core excluded;
- a deterministic daily start time between 01:00 and 04:59; and
- no more than four local Updraft backup sets.

It also recreates missing Updraft file and database cron events after a restore.
Remote credentials are never created or changed. Administrators receive a
visible warning unless Amazon S3 uses a unique path ending in
`sites/<sanitized-hostname>`, using the full hostname with punctuation replaced by hyphens.

## Temporary hostnames and staging

An explicit WordPress `staging` environment keeps daily backups even on a
hosting-provider preview domain. Local/development and recognized review
hosts remain manual. During a domain transition, set the non-secret
`MRN_UPDRAFT_BACKUP_HOSTNAME` constant to the approved final hostname. This
keeps the storage-prefix validation and deterministic start time stable; it
does not change credentials, move archives, or rewrite the storage path.
Invalid overrides fail prefix validation. Disabled storage instances never
satisfy compliance, and every active S3 instance must use the isolated prefix.

## Development backups

Routine scheduled, manual, and pre-deploy backups share the same time-bucketed
retention policy.
Before risky development work, use Updraft's **Always Keep** option only for a
deliberate milestone. Ordinary manual backups remain disposable and are pruned
by Updraft after newer backups complete.

Backups imported by scanning remote storage are intentionally exempt from
Updraft's automatic retention. Never scan a shared bucket root. Correct the site
prefix first, then remove stale imported history without deleting another site's
remote objects.

## QA Engine

Run plugin-scoped QA with full static analysis:

```bash
MRN_QA_CODE_ANALYSIS_SCOPE=all mrn-qa run --project-root /Users/khofmeyer/Development/MRN/mu-plugins/mrn-updraft-local-retention
```

Runtime browser, accessibility, API, and performance checks should be run separately against an explicit target site when this plugin change affects rendered output or live WordPress behavior.
