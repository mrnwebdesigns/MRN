# MRN Updraft Backup Policy

WordPress plugin release unit for `mrn-updraft-local-retention`.

The MU plugin enforces the non-secret MRN Updraft policy on every stack runtime:

- daily file and database backups;
- four file and database backup sets retained;
- local archives deleted after successful remote transfer;
- WordPress core excluded;
- a deterministic daily start time between 01:00 and 04:59; and
- no more than four local Updraft backup sets.

It also recreates missing Updraft file and database cron events after a restore.
Remote credentials are never created or changed. Administrators receive a
visible warning unless Amazon S3 uses a unique path ending in
`sites/<site-hostname>`.

## Development backups

Routine scheduled and manual backups share the same rolling four-set retention.
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
