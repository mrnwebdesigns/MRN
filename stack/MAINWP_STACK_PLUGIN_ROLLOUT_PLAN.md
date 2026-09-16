# MainWP Selective Stack Plugin Rollout Plan

## Purpose

Use this contract when one platform-required MRN standard plugin needs to move
forward on one fully bootstrapped Stack site without deploying every Stack
component. It fills the gap between the immutable full Stack release and the
separate optional-plugin release process.

The first supported examples are `mrn-config-helper` `0.1.59` through `0.1.62`
to `0.1.63`, `mrn-stack-deployment-agent` `0.2.2` to `0.2.3`, and
`mrn-universal-sticky-bar` `1.1.8` or `1.1.9` to `1.1.10`.
Eligibility is not hardcoded to Config Helper: a fresh signed runtime report
must prove the requested plugin is exactly one `standard-plugin` component in
the site's reviewed immutable Stack release.

This process never changes the active child theme. It also does not update an
MU component, `wp-content/shared`, or the parent theme. Those remain coupled
schema-2 full Stack targets until an equally safe atomic contract is designed
for them.

## Release Identity

A selective update does not rewrite `mrn-stack-release.lock.json` or claim that
the entire site is on a new Stack release. The effective site state is:

1. the exact immutable release ID and lock SHA already deployed; plus
2. one checksum- and tree-locked component overlay recorded by the generated
   selective plan and fresh post-write runtime report.

The Stack runtime report will normally show `matches_release=false` for that
one updated plugin. That is expected and disclosed, not hidden. The selective
plan proves the intentional target version, `sha256-tree-v1` digest, file count,
source commit, ZIP checksum, site, and rollback artifact. A later full Stack
promotion should absorb the new plugin version into a new immutable release and
remove the overlay condition.

## Release Units

- `manifests/stack-plugin-releases.json` registers every approved forward and
  rollback version. Multiple versions per slug are intentional.
- `manifests/stack-plugin-update-plan.schema.json` defines the generated plan.
- `scripts/build-mainwp-stack-plugin-plan.py` validates one fresh site
  inventory, immutable release identity, exact committed sources, target and
  rollback packages, runtime tree, and backup readiness.
- `mrn-mainwp-operations-api` `0.9.4` provides the matching Dashboard abilities:
  - `mrn-mainwp/preflight-stack-plugin-update-v1`
  - `mrn-mainwp/update-stack-plugin-v1`
  - `mrn-mainwp/rollback-stack-plugin-v1`

## Artifact Preparation

Build new target ZIPs from their registered commits. The ZIP must have one
exact plugin directory root and the conventional main file
`<slug>/<slug>.php`. For example:

```bash
mkdir -p releases/stack-plugins
git -C /Users/khofmeyer/Development/MRN-plugins/mrn-config-helper archive \
  --format=zip \
  --prefix=mrn-config-helper/ \
  124dcfd22ce867900b878f5b82c5854f5f93a029 \
  -o releases/stack-plugins/mrn-config-helper-0.1.63.zip
```

The release registry records each ZIP's exact byte checksum and size plus the
deployable source tree checksum and file count. The plan builder independently
proves that:

- the target commit is clean and equals `origin/main`;
- the rollback commit is already contained in `origin/main`;
- the registered tree equals the exact Git commit;
- the ZIP contains that exact deployable tree and embedded version; and
- neither package contains unsafe, symlink, duplicate, or excluded paths.

Config Helper `0.1.59` and `0.1.60` were previously packaged with two ignored
`ai-data` files. Those packages are retained solely as exact rollback material,
and the registry binds each supplemental path, size, and checksum explicitly.
They can never be selected as a new target. Versions `0.1.61` and later use the
clean, commit-reproducible package boundary, and the shared release generator
excludes `ai-data` from every future deployable tree.

Universal Sticky Bar `1.1.8` likewise contained one tracked internal `ai-data`
note. Its exact package is registered only as legacy rollback material;
`1.1.9` removes that file from tracked release source and is the clean forward
target.

Generated ZIPs remain ignored build artifacts. Retain exact registered files
with the rollout evidence; rebuilding is allowed only when the resulting size
and SHA-256 still match the registry.

## Fresh One-Site Inventory

Resolve the exact site URL through MainWP and run a fresh targeted sync. The
inventory document passed to the plan builder has `schema_version=1` and one
`site` object containing:

- exact `site_id`, credential-free HTTPS `site_url`, and
  `inventory_synced_at`;
- the signed runtime `release` identity: `present`, `valid`, `release_id`, and
  `lock_sha256`;
- one `plugin` record with directory slug, exact main file, installed/active/
  loaded booleans, version, `standard-plugin` runtime type, deployed path,
  `sha256-tree-v1` digest, and file count; and
- current Updraft remote database-backup readiness.

The builder rejects stale inventory, an absent/inactive/unloaded plugin, a
release-lock mismatch, a component outside the immutable baseline, a no-op or
downgrade, unregistered current code, missing rollback bytes, or unavailable
remote backup execution.

The current lock and every superseded lock used by an eligible site are retained
under `manifests/release-locks/<release-id>.json`. By default the builder selects
the exact current or archived lock whose release ID and byte checksum match the
site's signed runtime report. It fails closed when the exact historical lock is
missing or altered. `--release-lock` remains available when an operator needs to
name one reviewed lock explicitly; it never weakens the identity check.

## Build The Plan

```bash
python3 stack/scripts/build-mainwp-stack-plugin-plan.py \
  --inventory /absolute/path/to/fresh-site-inventory.json \
  --plugin-slug mrn-config-helper \
  --plan-id config-helper-0.1.63-site-115 \
  --output releases/stack-plugins/config-helper-0.1.63-site-115.plan.json
```

The current catalog version is the default and only legal target. Optional
`--target-artifact` and `--rollback-artifact` arguments may point to retained
copies, but their names, sizes, byte checksums, versions, and tree checksums
must still match the registry.

## One-Site State Machine

| State | Evidence required before advancing |
| --- | --- |
| Resolved | Exact URL maps to one MainWP site and a fresh targeted sync completed |
| Baseline verified | Signed runtime report matches the reviewed release ID and lock SHA |
| Component verified | Installed, active, and loaded standard plugin exactly matches a registered rollback version/tree |
| Plan built | Clean committed target, exact forward/rollback ZIPs, fresh inventory, and backup readiness pass |
| Preflighted | Dashboard repeats live inventory/runtime/package checks and returns `ready=true` plus the precondition hash |
| Backed up | A new remote database-only Updraft backup produces a valid one-use receipt for this exact site/write |
| Applied | Explicitly confirmed update uses the exact reviewed package and preserves active state |
| Verified | Fresh MainWP inventory and signed runtime report match target version/tree/file count and every unrelated target stayed outside the operation |

Every site advances independently. A failure on one site does not authorize or
attempt another site.

## Dashboard Preflight And Update

Encode the exact target and rollback ZIP bytes only for transport to the
Dashboard ability. Map the generated plan to the ability input:

- `plan_id`, `site_id`, `site_url`, and `baseline` come directly from the plan;
- `plugin_slug` is the plan's exact `main_file`;
- `target` and `rollback` each include version, filename, package SHA-256,
  base64 ZIP bytes, tree SHA-256, and file count.

Call `mrn-mainwp/preflight-stack-plugin-update-v1` with
`operation=update`. Review the exact site, baseline, installed state, target,
rollback readiness, backup readiness, blockers, and `precondition_hash`.

Immediately before the write, create and verify a fresh remote database-only
Updraft backup. Only after separate owner authorization, call
`mrn-mainwp/update-stack-plugin-v1` with `confirm=true`, the exact preflight
hash, and that site's one-use backup receipt.

The Dashboard re-runs preflight before writing. It installs through MainWP
Child's authenticated package path, consumes the receipt after the mutation,
then verifies both fresh plugin inventory and the signed runtime component
version, tree digest, file count, loaded state, and preserved active state. A
successful ZIP installation with failed readback is reported as a failed
verification after a completed write; do not retry blindly.

## Rollback

Rollback is explicit and independently backup-gated:

1. Call selective preflight with `operation=rollback` against the exact updated
   target tree.
2. Review the new rollback precondition hash.
3. Create and verify a new remote database-only Updraft backup.
4. Obtain separate rollback authorization.
5. Call `mrn-mainwp/rollback-stack-plugin-v1` with `confirm=true`, the new
   precondition hash, and the new receipt.
6. Require exact inventory and runtime-tree readback of the registered prior
   version.

## Control-Plane Gate

Source readiness is not Dashboard deployment. Before the first selective site
operation, package and deploy `mrn-mainwp-operations-api` `0.9.4` to
`wpcontrol.mrndev.io` through its separately authorized, backup-gated Dashboard
workflow. Reconnect the configured `mainwp` MCP adapter and require the exact
Dashboard host plus all three selective abilities. Do not substitute SSH,
browser automation, another Dashboard, or the generic package installer.

## Non-Negotiable Boundaries

- one exact site and one exact Stack plugin per plan;
- installed, active, loaded, platform-required `standard-plugin` only;
- immutable baseline identity retained and disclosed;
- current runtime must match a registered rollback artifact exactly;
- forward and rollback source, ZIP, version, tree, and file-count verification;
- no new installation, no no-op, and no downgrade through update;
- no child-theme, parent-theme, MU, shared-runtime, or arbitrary-path target;
- read-only preflight, explicit confirmation, and fresh backup receipt for
  every mutation;
- receipt consumption after any successful child write; and
- exact post-write inventory and signed runtime verification.
