# Verified Platform Release Deployment

This workflow deploys only the platform-required components and theme parent in
`stack-release.lock.json`. Optional and profile-gated plugins remain separate
release programs.

CSS/JS changes also require the
[MRN asset release standard](../docs/MRN-ASSET-RELEASE-STANDARD.md), including
automatic build hashes, synchronized minified outputs, immutable URLs, atomic
runtime activation, scoped HTML refresh, and public checksum verification.
These are additional promotion gates: the commands below and an assembled
artifact alone do not prove them. Qualify the adapter, including the Gloves
stale-CSS regression, before using it for an asset release.

## Contract

- `assemble-stack-release.py` creates a complete artifact tree from the exact
  repositories and source hashes named by the immutable lock.
- `deploy-stack-release-to-site.py` defaults to `--dry-run` only when that flag
  is explicitly supplied by the operator or rollout control plane.
- A non-dry-run deploy creates and verifies a remote database-only Updraft
  backup before its first WordPress runtime write.
- The live `template` receives the exact locked parent theme. The live
  `stylesheet` is site-owned and is never overwritten by this updater.
- `wp-content/shared` is a first-class, versioned `shared-runtime` component in
  the lock and runtime report.
- A remote code archive is created and SHA-256 verified before sync. The local
  receipt records the old release identity and exact rollback archive.
- The deployed loader report must match the selected lock, all required
  components, and the exact parent hash before success is reported.
- For an asset release, stage all hashed assets before atomically activating
  their matching code and manifests. Retain previous assets for cached HTML and
  rollback. Refresh only HTML consuming changed assets, preserving unrelated
  caches. Normal public pages must reference the release manifest's asset URLs,
  and decoded public asset bytes must match its SHA-256 checksums.

## Assemble

Run from a clean release worktree. Standalone required plugin repositories must
also be clean and at the commits recorded by the lock.

```bash
python3 stack/scripts/assemble-stack-release.py \
  --release-lock stack/manifests/stack-release.lock.json \
  --output releases/assembled/2026.08.22-verified-release-deploy
```

The output mirrors deployed WordPress paths:

```text
mu-plugins/
plugins/
shared/
themes/
```

## Dry Run

Dry run validates the release, site-owner path, and live theme shape without
syncing runtime files or creating a deployment receipt:

```bash
python3 stack/scripts/deploy-stack-release-to-site.py \
  --release-lock stack/manifests/stack-release.lock.json \
  --artifact-root releases/assembled/2026.08.22-verified-release-deploy \
  --site-hostname example.mrndev.io \
  --receipt-out /tmp/example-release-receipt.json \
  --dry-run
```

## Confirmed Deploy

The rollout control plane owns operator confirmation. For CSS/JS, first record
adapter qualification against the asset release standard; a legacy in-place
sync or broad purge cannot substitute for it. Once all applicable gates are
met and the rollout is confirmed, omit `--dry-run`. The helper performs its own
backup and verification gates:

```bash
python3 stack/scripts/deploy-stack-release-to-site.py \
  --release-lock stack/manifests/stack-release.lock.json \
  --artifact-root releases/assembled/2026.08.22-verified-release-deploy \
  --site-hostname example.mrndev.io \
  --receipt-out outputs/example-release-receipt.json
```

Receipts contain no credentials and are mode `0600`. Preserve the receipt with
the rollout ledger while its remote archive remains useful.

## Rollback

Rollback is a separate confirmed write. It creates another verified database
backup, verifies the code archive checksum, restores only rollout-owned paths,
and requires the restored loader report to match the previous release identity.
For CSS/JS, restore the matching code and manifest atomically, retain both asset
generations, refresh only affected HTML, and reverify public URLs and checksums.

```bash
python3 stack/scripts/rollback-stack-release-on-site.py \
  --receipt outputs/example-release-receipt.json \
  --confirm 'ROLLBACK example.mrndev.io 2026.08.22-verified-release-deploy'
```

Do not use database restore as an automatic response to a code verification
failure. The database backup is protection for state/schema side effects and is
restored only through a separately approved recovery decision.
