# MainWP Backup Retention Rollout Plan

## Status and Scope

This is an execution plan, not deployment authorization. The policy source and
MainWP verification contract are prepared in the repository, but no child site
is changed by this plan or its feature commit.

The read-only Dashboard snapshot taken on 2026-10-14 showed 104 registered
sites: 102 connected and two disconnected. This count is diagnostic only; the
execution task must re-query the Dashboard and resolve every target from its
exact current URL instead of reusing this snapshot. The P&P source then had 90
Yes, 16 No, and 16 blank records. Seventy-seven MainWP sites matched a Yes row
by exact URL. Sites with blank source URLs, environment aliases, or no exact
match require explicit reconciliation before a write.

The exact-match breakdown from that snapshot is 77 P&P=Yes sites, five sites
whose source row says No (MainWP IDs 75, 12, 28, 38, and 48), one site with a
blank P&P value (ID 55), and 21 MainWP sites without an exact source URL. The
No sites are Artisan Quality Roofing, Bassett CPAs, First Choice Roofing, Jon
Parker Real Estate, and Oasis Mental Wellness; ID 55 is Quality Seamless
Gutters. Do not treat the 21 unmatched URLs as Yes by inference.

Potential aliases requiring identity confirmation include IDs 34, 36, 46, 50,
84, and 86, which correspond by domain/name to Yes rows with blank source URLs;
ID 116 (`dosterandbrown.mrndev.io`), which appears to be the Yes production
site's development environment; and IDs 13 and 58, which appear related to the
Yes Best Day Psych production site. Six Yes source rows have no exact MainWP
site: Citadel Contractors, eLoghomes, ezReconcile, Gray Flex, SnapRite, and
Strategic Revenue Group. Reconcile every alias and missing target against the
current source and MainWP identity before a write.

## Target Contract

- Apply the remote backup profile only to sites with a verified **Yes** for
  P&P. Use the current MRN Website Information source and exact MainWP URLs;
  do not use the legacy overlapping MainWP tags as the selector.
- For a site without **Yes**, disable all Updraft remote destinations, set
  routine file and database schedules to `manual`, and mark the site as
  excluded from the MRN routine policy. Preserve disabled credentials and
  historical remote objects; deleting historical objects is a separate
  reviewed operation requiring its own explicit approval.
- Staging and production run one combined file/database backup daily.
- Development/review environments use manual scheduling but retain the same
  mandatory database-only backup gate before every remote write.
- Remote history keeps seven daily, four weekly, and twelve 30-day recovery
  points, with routine history ending after day 395.
- File history uses a 23-set numerical safety cap. Database history uses a
  100-set cap so recent labeled pre-deploy backups do not crowd out scheduled
  recovery points before time-bucket pruning applies.
- Local archives are deleted after successful remote transfer; no more than
  four stranded complete sets remain on disk.
- Routine backups never use **Always Keep**. Only an explicitly named and
  documented milestone may use it temporarily.
- Every remote destination ends in the site's unique `sites/<sanitized-hostname>`
  prefix. Never scan or prune a shared bucket root.

## Promotion Preconditions

1. Merge the feature commit to clean current `main` through the normal review
   path. Do not promote directly from this feature worktree.
2. In a dedicated Stack promotion task, reconcile the component catalog,
   generate and commit a new immutable release lock, and build the
   checksum-locked release artifact.
3. Run MRN release QA and `stack/scripts/qa-stack-promotion.py` audit/candidate
   gates against that merged candidate. A source commit is not a release.
4. Confirm `mainwp://status` is connected to `wpcontrol.mrndev.io` with nonzero
   abilities. Require the site-list, exact-site, sync, runtime-report,
   preflight, backup, and guarded install abilities used below.
5. Confirm each target is connected, UpdraftPlus is active, S3 is configured
   to a unique site prefix, UpdraftPlus advanced retention is active, and the
   deployment agent supports the selected release route.

## Site Cohorts

- **Canonical Stack sites:** use the checksum-locked full Stack release after
  promotion.
- **Approved legacy or protected-fork sites:** use the approved MU-only release
  path only when its schema, deployment agent, and rollback artifact are
  explicitly supported.
- **Blocked sites:** do not write to disconnected sites, sites without the
  deployment agent, sites without UpdraftPlus advanced retention, sites with a
  shared or ambiguous S3 prefix, or non-Stack sites without an approved
  seeding/manual path. Remediate and requalify them; never silently bypass a
  prerequisite.

## One-Site State Machine

Process one site at a time unless the owner separately approves a larger
operational batch. Follow the shared state machine in
`stack/MAINWP_FLEET_ROLLOUT_PLAN.md` and record evidence at every transition.

1. Resolve the exact current URL through MainWP; do not reuse a remembered ID.
2. Fresh-sync only that site. Never pass an empty target list.
3. Read the current Stack runtime report and inventory.
4. Qualify the site into one cohort and confirm the expected release route,
   package checksum, rollback artifact, and write access.
5. Run the matching read-only release preflight. Require backup readiness,
   unique remote prefix, advanced-retention readiness, clean Git/deployment
   state, and a ready deployment agent.
6. Immediately before the first write, create a labeled database-only Updraft
   backup, send it to remote storage, and require the successful remote receipt.
7. Submit the exact guarded install confirmation for that one site and release.
8. Read the runtime report again. Require plugin and loader version `0.7.0`,
   `backup_policy.compliant=true`, daily or manual scheduling appropriate to the
   environment, file/database caps `23`/`100`, the exact advanced rules, local
   deletion, core exclusion, unique S3 prefix, and advanced-retention activity.
9. Verify the first full scheduled backup after the change and confirm the
   expected site-owned remote objects. Do not use a shared-root remote scan.
10. Run the applicable public/admin smoke checks and MRN QA, then store the
    preflight, backup receipt, release result, readback, and verification result.

For an excluded site, verify the runtime reports `management_mode=excluded`,
both routine schedules are `manual`, the Updraft remote-service list is empty,
and all S3 instances are inactive. Confirm the operation reports that existing
remote objects were not deleted.

## Canary and Expansion

Start with one low-risk connected production site whose UpdraftPlus Premium,
unique S3 prefix, deployment agent, backup receipt path, and rollback package
are already proven. Observe its next full scheduled backup and remote pruning
behavior before expanding. If the owner authorizes batching after the canary,
use cohorts of five, then ten, while preserving the one-site state machine and
per-site receipt. A failure stops the cohort; it never triggers a blind retry.

## Rollback

Before rollback, create and verify a new labeled database-only remote backup.
Apply the checksum-locked rollback release through the same guarded one-site
route, then verify the prior component versions, prior settings, runtime health,
and backup-policy status. Preserve all receipts and error output. A timeout is
an unknown state: read back the site before deciding whether another operation
is safe.

## Completion Criteria

The rollout is complete only when every registered target is either verified
compliant because P&P is **Yes**, verified excluded because P&P is not **Yes**,
or explicitly recorded as blocked with an owner, reason, and next action.
Dashboard connectivity, a successful source merge, or a successful package
install alone is not compliance proof.
