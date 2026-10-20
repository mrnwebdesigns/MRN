# Doster MRNDev owner-managed backups

On October 20, 2026 the owner explicitly waived automatic backup enforcement for
Doster, accepted responsibility for manual Live/staging backups, and authorized
this limited shared-tooling change in the site chat. Completed native snapshots
were observed in MyKinsta: MRNDev at 09:26 EDT and Live at 09:28 EDT (14-day retention).
Those snapshots are historical evidence, not a promise of future recovery points.

Only the `mrnwebdesigns/doster-and-brown-child-theme` repository's `dev` target can
set `DEPLOY_KINSTA_MANUAL_BACKUP_APPROVAL=doster-owner-manual-2026-10-20`. The exact
site/environment IDs, URL, SSH host/port/user and physical root are allowlisted in
`kinsta.py`. This does not enable Live deployments or an exception for other sites.
Keep `DEPLOY_BACKUP_PROVIDER=kinsta` and the existing explicit transaction-policy
approval. An actual noninteractive SSH key and pinned known-hosts are required.

Receipts identify `provider=owner-manual-exception` and
`automatic_backup_created=false`; they never report a fresh verified Kinsta
snapshot. API credentials/password lookup are unavailable in this mode. Fresh
WordPress identity, physical/private-path checks, immutable artifacts, source QA,
ordering, private rollback storage, recovery exercises and public acceptance remain
required. First Dev adoption uses the existing exact-main manual qualification
workflow, then enables routine deployment only after that qualification passes.

To revoke, clear the approval environment variable. Normal native API identity and
fresh verified transaction snapshots become mandatory again. Do not extend the
allowlist as a convenience for another site or a changed endpoint.
