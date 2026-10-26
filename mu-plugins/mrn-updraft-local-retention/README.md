# MRN Updraft Backup Policy

WordPress plugin release unit for `mrn-updraft-local-retention`.

For sites marked **Yes** for P&P in the MRN Website Information sheet, the MU
plugin enforces the non-secret MRN Updraft policy:

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

Sites without **Yes** for P&P are explicitly excluded through the guarded
MainWP operation. That operation disables Updraft's remote destinations,
switches routine file and database schedules to `manual`, and stops MRN
retention enforcement and notices on that site. It preserves the stored
destination credentials in a disabled state and does not delete historical
S3 objects. Historical-object deletion requires its own reviewed site-owned
manifest and approval.

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

## Guarded MainWP maintenance (0.8.8 candidate)

After each successful native update, the batch admits only the selected item's
approved version and new code tree into its next expected state. Activation,
theme selection, core and every other installed entry must remain unchanged.
This permits subsequent approved items to run while still refusing unrelated
changes before, during or after an individual replacement. A partial or
unverified result must be reconciled before any further update attempt.

Adds `mrn_maintenance_request` only on MainWP Child's existing authenticated
`extra_execution` hook. There is no child REST route, new credential, raw-code
input, install, activation/deactivation or core operation. Exact existing
plugin/theme updates require independently verified remote Updraft DB-only
backup history, completed log, isolated S3 objects, a frozen code/package plan,
current versions and single-use UUID/nonce. Leading-digit plugin updates need
an exact per-plugin override; theme majors and MRN Fleet components are refused.

Native WordPress upgraders consume only the validated/checksummed ZIP, preserve
plugin activation and check stale state immediately before replacement. MainWP
update and rollback hooks remain in use. Independent readback is bound to the
same exact operation and selection. Unknown operations are not retried, locks
are not stolen, and audit records are not silently expired. New operations stop
at 2,000 durable operation/nonce entries pending separately authorized archival.

ZIP validation accepts the single trailing slash used by standard directory
entries in official WordPress packages. Empty internal path segments, dot or
parent aliases, duplicate/case-colliding paths, symlinks and unsafe types remain
refused. `php tests/maintenance-packages.php` covers both plugin/theme directory
entries and the corresponding unsafe archives without touching a WordPress site.

Remote proof initializes native S3 TLS certificate verification and discovers
the bucket region before its bounded exact-prefix listing. Region discovery
failure is a refusal; verification never creates a bucket or changes credentials.
`php tests/mainwp-s3-client.php /path/to/updraftplus/includes/S3.php` exercises
the installed native client API with offline network substitutes.

After an authenticated, site-matched preflight refusal, the existing MainWP
runtime report can include `last_maintenance_preflight_refusal`. It contains
only a schema version, a fixed code and a timestamp, retained for ten minutes
under one fixed transient key. Reading the report performs no new backup,
remote scan or diagnostic write. Download refusals distinguish fixed network, TLS, HTTP and size categories; no native message or response body is retained. The same 30-second timeout, three-redirect cap and 50 MiB limit remain enforced. Unknown exceptions remain `internal_error`;
raw messages, signed URLs, backup nonces and credentials are never retained.
This is diagnostic history, not deployment approval or current readiness.

Qualification includes `php tests/schedule-repair.php` (actual policy adapter
with history/log/S3 fixtures) and the Dashboard repository's disposable
`tests/native-maintenance.py` integration harness (real native WordPress file
updates with mocked transport/backup boundary). Neither proves deployment or
remote runtime readiness. This local candidate must not be promoted or installed
without the separately approved release, backup and pilot gates.

MainWP Child 6.2.1 omits the native Updraft request argument. The backup-options
bridge preserves only the fixed MRN label for a new, remote, full-database
backup requested through that signed flow. It never changes existing history,
starts a backup, changes credentials, or relaxes receipt verification.

The October 23 maintenance repair returns closed per-item preflight and update
stages/codes through the signed transport. Native exception text, URLs and logs
remain withheld. A failed batch explicitly marks remaining items not attempted.
Independent readback distinguishes changed installed state from unknown mutation
history after an attempted replacement. No-change evidence requires unchanged
versions, trees, activation and core, plus proof that destination processing never
started. Temporary protection of `active_plugins` prevents updater hooks from
leaving an initially active plugin deactivated on failure; an attempted activation
change fails the batch. No activation/deactivation tool or raw execution path is
added. The Dashboard and connector repairs must be released together before
claiming the new diagnostics are available to chats.

### Timeout checkpoints

The same authenticated runtime report may include
`last_maintenance_preflight_checkpoint`: a schema version, exact site ID,
request SHA-256, closed phase/state, timestamps and optional selected-item slug.
It is stored under one fixed transient key for at most ten minutes. It contains
no raw request, receipt, backup nonce, exception text, download URL or credentials.
The separate existing refusal record is unchanged.

A checkpoint is written **before** remote backup proof, each inventory class,
offer refresh, package download and package validation. Abrupt worker death or
a proxy timeout can therefore leave an `in_progress` observation. It does not
prove the worker is still running, a check passed, a write happened or nothing
changed. Concurrent preflights can replace this diagnostic history. Match its
request digest and timestamps before using it to locate the last phase reached.
Never use this record for authorization, readiness, confirmation, stale-state
validation, receipt validation, retry or lock release. All those gates continue
to use their existing independent evidence. Execute/read operations do not
create these checkpoints. Reading the report performs no new scan or write.

`php tests/maintenance-checkpoints.php` terminates a disposable worker during
backup verification and verifies the surviving observation, closed metadata,
read-only reporting and unchanged target/core/backup refusal behavior. The
normal native WordPress maintenance fixture must also pass before a release.

### Core-file change observations

Version 0.8.8 adds `maintenance_file_diagnostics` to existing authenticated
`get_backup_policy_v1` inspection, including standalone sites without a full
Stack loader. It observes the existing `wp-admin` name/hash map only after the
site and remote-backup checks in a maintenance preflight. The original complete
tree hash and all stale-state refusals remain unchanged, including changes to
logs, hidden files and non-PHP files. No names are ignored to make an update pass.

One fixed ten-minute transient retains a bounded map of relative filenames and
SHA-256 values. Inspection returns only the component, site ID, timestamps,
current aggregate hash and the last observed difference: at most 64 relative
names with previous/current hashes, the exact changed count and a truncation
flag. Null hashes identify additions or deletions. It never returns the full
manifest, file contents, physical filesystem paths, raw logs, receipts or native
exception text. Invalid, stale, oversized or unsafe diagnostic data is withheld.
Inspection performs no scan or write; ordinary execute/read snapshots do not
create observations. Diagnostic history cannot authorize an update, retry,
ignore a changed file, release a lock or replace independent readback.

Match both fingerprints and timestamps to the refused preview/confirmation;
concurrent preflights can replace history. A first observation is a baseline,
and a null `last_change` does not prove no file ever changed. Use
`php tests/maintenance-file-diagnostics.php` for real disposable filesystem
coverage, and the unchanged Dashboard native maintenance fixture for update,
backup, authorization, activation, stale-state and core-refusal coverage.
