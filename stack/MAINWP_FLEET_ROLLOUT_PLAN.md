# MainWP Full Stack Fleet Rollout Plan

## Purpose

Use MainWP as the provider-independent control plane for bringing an existing,
fully bootstrapped MRN Stack site to one exact immutable platform release. The
initial operating mode is deliberately one named site at a time. Batch support
exists for later expansion, but no inventory result or parity finding authorizes
an automatic write.

The canonical full-platform package contains every locked MRN MU component,
required MRN standard plugin, `wp-content/shared`, the `mrn-base-stack` parent
theme, and the runtime release lock. It never contains or changes the active
`mrn-base-stack-child` theme. The deployment agent is also excluded from its own
package and must be installed and verified separately before preflight.

Schema-1 MU-only packages remain supported for approved site forks and legacy
rollouts. Schema 2 is the full Stack contract described here.

## Qualification And Fleet Membership

`Full Stack` is an owner-approved MainWP cohort, not a guess derived from a
billing field, plugin count, or successful HTTP response. `Platform & Plugins`
and its frequency/tags describe maintenance inclusion; they do not prove that a
site has the canonical Stack runtime.

As of the 2026-09-10 read-only Dashboard audit, the `Full Stack` tag exists but
has no members. Sites must be qualified before that tag is populated.

For each proposed member:

1. Resolve the exact site by canonical URL through MainWP. Never reuse a
   remembered site ID and never pass an empty site list.
2. Run a fresh targeted MainWP sync.
3. Call `mrn-mainwp/qualify-stack-site-v1` to read the Stack deployment-agent
   status, canonical theme shape, storage readiness, incomplete-rollout state,
   and Stack runtime report as one sanitized classification.
4. Require a connected child site and the canonical theme shape:
   `template=mrn-base-stack` and `stylesheet=mrn-base-stack-child`.
5. Confirm that the child stylesheet differs from the parent template and is
   outside every package target.
6. Record missing prerequisites separately from runtime drift. A missing
   deployment agent or required MRN standard plugin is a seeding task; a renamed
   parent, renamed child, clone-style site, protected fork, or ambiguous runtime
   shape does not qualify for schema 2.
7. Add the `Full Stack` tag only after the owner approves the exact qualified
   site. The deployed `release_id` and lock SHA, not the tag, prove which release
   the site is running.

Sites with a site-specific child on a renamed parent remain supported by the
site-resolved direct deployment process in `SITE_UPDATE_PROCESS.md`. Extending
the fleet package to those shapes requires a separately designed, exact target
mapping; do not weaken the canonical schema-2 checks to make one pass.

## Release Units

- `stack/manifests/stack-release.lock.json`: immutable release identity and
  exact component/theme tree hashes.
- `stack/scripts/assemble-stack-release.py`: reconstructs the complete release
  from the commits recorded in the lock and verifies every assembled tree.
- `stack/scripts/build-mainwp-stack-release.py`: creates deterministic schema-2
  `plan.json`, ZIP, and `checksums.json` artifacts from that assembled release.
- `mrn-stack-deployment-agent`: zero-frontend child plugin that independently
  verifies the plan, embedded lock, archive paths, per-file hashes, complete
  release projection, target readiness, and child-theme contract before atomic
  promotion. It retains checksum-verified rollback material outside the public
  document root.
- `mrn-mainwp-operations-api`: Dashboard-only controller that validates the same
  package contract, enforces confirmation and fresh backup receipts, sends the
  signed child request, and performs the post-write runtime readback.

## Control-Plane Release Gate

Before any site rollout:

1. Complete focused and MRN release QA for the deployment agent, operations API,
   and Stack release tooling.
   Run the shared builder-to-controller-to-child contract check so all three
   repositories accept one identical generated artifact:

   ```bash
   MRN_STACK_AGENT_ROOT=<deployment-agent-root> \
   MRN_MAINWP_OPERATIONS_ROOT=<operations-api-root> \
   python3 -m unittest stack.tests.test_build_mainwp_stack_release -v
   ```
2. Commit and push the two standalone plugins and merge all Stack source to the
   approved default branches.
3. Promote a new immutable release lock from clean, current, merged `main` using
   `RELEASE_VERSIONING_STRATEGY.md`. The lock must record the exact deployment
   agent release that supports schema 2.
4. Build checksum-recorded ZIPs from those committed sources.
5. Through a separately authorized, backup-gated Dashboard change, install the
   approved operations API on `wpcontrol.mrndev.io`.
6. Reconnect the configured `mainwp` MCP adapter and require `mainwp://status` to
   report `connected: true`, `dashboardHost: wpcontrol.mrndev.io`, and nonzero
   abilities. Require these abilities before proceeding:
   - `mrn-mainwp/qualify-stack-site-v1`
   - `mrn-mainwp/preflight-stack-release-v1`
   - `mrn-mainwp/install-stack-release-v1`
   - `mrn-mainwp/rollback-stack-release-v1`
   - `mrn-mainwp/get-stack-runtime-report-v1`
7. Stop if the Dashboard host, authentication, permissions, or ability set does
   not match. Do not substitute browser automation, SSH, or another credential.

## Build The Immutable Fleet Package

Build only from a clean release lock whose source commits are already final:

```bash
python3 stack/scripts/assemble-stack-release.py \
  --release-lock stack/manifests/stack-release.lock.json \
  --output releases/assembled/<release-id>

python3 stack/scripts/build-mainwp-stack-release.py \
  --release-lock stack/manifests/stack-release.lock.json \
  --artifact-root releases/assembled/<release-id> \
  --rollout-id <unique-rollout-id> \
  --output-dir releases/mainwp-stack/<unique-rollout-id>
```

The builder fails if an assembled component differs from the lock, the canonical
parent/site-derived child contract is missing, the deployment agent is not an
exact prerequisite, a target leaves the fixed allowlist, or an artifact would
exceed the child agent's archive limits. Record and retain the exact
`checksums.json`, `plan.json`, and ZIP. Never hand-edit them.

## One-Site Rollout State Machine

Every site advances independently. A failure leaves later sites untouched.

| State | Required evidence before advancing |
| --- | --- |
| Qualified | Exact URL resolution, fresh sync, canonical parent/child shape, owner-approved membership |
| Seeded | Exact deployment-agent version/tree hash and every locked MRN standard plugin installed and active |
| Preflighted | Schema-2 plan accepted; all targets writable and same-device promotable; no incomplete rollout marker; child protected |
| Backed up | Fresh successful remote database-only Updraft receipt for this site and this write |
| Applied | Child reports the exact rollout/release IDs and durable rollback readiness |
| Runtime verified | Fresh report has the exact release ID/lock SHA, no missing or drifted required components, no legacy collisions, and matching parent |
| User-visible verified | Public health plus release-specific frontend, admin, accessibility, and interaction smoke checks pass |

### 1. Seed Missing Prerequisites

Build the deployment-agent ZIP from its exact committed release. If the named
site lacks that version/tree, create and verify a fresh remote database backup,
then use `mrn-mainwp/install-plugin-package-v1` with `confirm=true` for that one
site. Read status back and require the exact version, SHA-256 tree digest, file
count, schema support, ZIP availability, target writability, and private/atomic
storage readiness.

Use the same separately backup-gated plugin installer for any locked required
MRN standard plugin that is missing. Re-read inventory and require it to be
active before full Stack preflight. Each successful mutation consumes its
receipt, so obtain a new receipt before the next write.

### 2. Preflight

Call `mrn-mainwp/preflight-stack-release-v1` with the exact one-element site ID
list, generated `plan.json`, and plan SHA. Review every returned target,
prerequisite, incomplete-rollout count, site contract field, and readiness flag.
The preflight is read-only but uses POST transport because the plan may exceed a
safe GET URL length.

Stop on any mismatch. Do not create a backup or attempt apply merely because the
site is connected.

### 3. Backup And Apply

Immediately before the write:

1. Start a database-only Updraft backup using the child site's configured remote
   storage.
2. Poll the exact backup nonce until the operations API returns a successful,
   fresh deployment receipt. An archive upload without the completed receipt is
   not sufficient.
3. Review the exact site, rollout ID, release ID, plan SHA, package SHA, and
   preflight result.
4. Call `mrn-mainwp/install-stack-release-v1` with `confirm=true`, the exact
   generated artifacts, and that site's receipt.

The child stages every component in a protected same-device workspace, moves
the old target trees into local and durable rollback storage, promotes the
complete locked release, and writes the runtime release lock last. The active
child theme is never a legal target.

### 4. Verify

The Dashboard immediately requests a fresh runtime report after a successful
filesystem write. Require:

- exact `release_id` and lock SHA;
- no missing or drifted required component;
- no legacy flat-file collision;
- exact `template=mrn-base-stack` and
  `stylesheet=mrn-base-stack-child` readback;
- exact parent-theme match;
- successful child result and `rollback_ready=true`.

Then verify the public site, WordPress admin, and release-specific behavior.
HTTP 200 alone is not acceptance. Clear only the relevant caches before a final
rendered check. Record the result as successful, failed, or skipped with the
reason.

If the filesystem write succeeds but runtime verification fails, the operation
is reported failed with `write_succeeded=true`, the backup receipt remains
consumed because a mutation occurred, and the named rollback record remains
available. Do not retry apply blindly.

## Rollback

1. Identify the exact rollout ID and retained rollback evidence.
2. Obtain and verify a new remote database-only backup receipt.
3. Review the one-site rollback target and call
   `mrn-mainwp/rollback-stack-release-v1` with `confirm=true`.
4. Require successful restoration of every recorded target and removal of the
   used rollback record.
5. Re-run runtime, public, admin, and release-specific verification.

If restoration cannot complete, the agent retains its durable record and reports
failure. Stop and diagnose; do not delete recovery evidence.

## Later Cohort Expansion

After at least one owner-approved production canary completes the entire state
machine, the same immutable artifacts may be applied to additional qualified
`Full Stack` members. Keep each site independently preflighted, backed up,
applied, and verified. The API limit is 25 sites, but operational batch size
should remain one until the owner explicitly approves broader cohorts and the
result log can represent partial failures without ambiguity.

## Non-Negotiable Gates

- Exact MainWP Dashboard origin and exact site URL resolution with a fresh sync.
- Owner-approved fleet membership; `Platform & Plugins` is not membership.
- Clean committed sources and an immutable release lock.
- Exact plan/package/per-file/tree checksums verified by both Dashboard and
  child, including completeness against the embedded lock.
- Fixed platform allowlist; no arbitrary plugin, theme, or filesystem target.
- Deployment agent seeded separately; no self-replacement during a request.
- Canonical parent plus preserved child theme only for schema 2.
- Read-only preflight before explicit confirmation.
- Fresh verified remote database-only backup before every mutation.
- Runtime and user-visible verification after every site.
- No automatic mass, parity-driven, or inventory-driven writes.
