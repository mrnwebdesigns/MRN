# Doster owner-managed backups

On October 20, 2026 the owner explicitly waived automatic backup enforcement for
Doster, accepted responsibility for manual Live/staging backups, and authorized
this limited shared-tooling change in the site chat. Completed native snapshots
were observed in MyKinsta: MRNDev at 09:26 EDT and Live at 09:28 EDT (14-day retention).
Those snapshots are historical evidence, not a promise of future recovery points.

The owner separately authorized scoped Live deployment tooling in the same chat
on October 20 after the native MRNDev-to-Live push and production verification.

Only the `mrnwebdesigns/doster-and-brown-child-theme` repository's exact `dev` and
`live` targets can
set `DEPLOY_KINSTA_MANUAL_BACKUP_APPROVAL=doster-owner-manual-2026-10-20`. The exact
site/environment IDs, URL, SSH host/port/user and physical root are allowlisted in
`kinsta.py`. The targets have distinct environment IDs, canonical origins, SSH
ports and backup receipt identities. A Dev receipt cannot authorize a Live write.
This does not itself enable a Live workflow or an exception for other sites.
Keep `DEPLOY_BACKUP_PROVIDER=kinsta` and the existing explicit transaction-policy
approval. An actual noninteractive SSH key and pinned known-hosts are required.

Receipts identify `provider=owner-manual-exception` and
`automatic_backup_created=false`; they never report a fresh verified Kinsta
snapshot. API credentials/password lookup are unavailable in this mode. Fresh
WordPress identity, physical/private-path checks, immutable artifacts, source QA,
ordering, private rollback storage, recovery exercises and public acceptance remain
required. First Dev adoption uses the existing exact-main manual qualification
workflow, then enables routine deployment only after that qualification passes.
Live still requires separate production access authorization, actual target
qualification, rollback/reactivation evidence and explicit manual release intent.

## One-time copied Live release qualification

The October 20 native push copied MRNDev's private release store and ordered
deployment history to Live. The owner authorized a guarded Live onboarding in
this site chat. `promoted_store.py` accepts a schema 2 manifest carrying
`doster_cutover=doster-live-20261020` only for this exact Live repository, slug,
provider, origin, root and SSH identity. It also pins the copied public-tree hash,
original source, artifact pointer and successful qualification run 39438246725.
Every changed target/source/order field is rejected. Ordinary schema 1 staging
promotion keeps its native API identity and prohibition on ordered source stores.

This one-time path reuses the normal promotion controller to import the verified
original archive into `/www/lowwd_975/deployments/mrn-site-deploy/live`, preserving
the copied `dev` store, its original ordering, and the old public loader. It
requires a distinct candidate, an unused Live store, production WordPress mode,
canonical loader/public snapshot, verified private code and public asset hashes,
the exact owner-manual Live adapter and rollback/reactivation. Qualification
cannot run as an automatic deployment. GitHub qualification permits only its
matching trusted manual workflow order; the copied Dev order remains pinned and
unchanged. A failed import restores the
original loader and quarantines Live metadata; no copied source history is
deleted or reclassified as Live history. Routine Live Actions releases use the
separately bound Live store only after qualification enables readiness.

The reusable workflow's `qualify_doster_live` input is off by default and accepts
only an explicit Doster manual Live request from main. Before SSH configuration,
it retrieves exact artifact 12272493310 from run 39438246725, verifies current-main
source QA and the original successful run/artifact provenance, then verifies the
original archive against the independently pinned checksum. The usual deployment
controller, browser/assets checks and final runtime QA still apply. After
qualification, `DEPLOY_READY=1` disables this first-use path; ordinary manual Live
releases use no promotion flag.

The backing manual snapshot observed before this onboarding is historical
recovery evidence, not an automatic-backup capability. Deployment receipts must
continue to report the exception honestly on every later release.

To revoke, clear the approval environment variable. Normal native API identity and
fresh verified transaction snapshots become mandatory again. Do not extend the
allowlist as a convenience for another site or a changed endpoint.
