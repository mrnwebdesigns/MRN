# MainWP Fleet Rollout Plan

## Purpose

Use MainWP as the control plane for distributing an exact MRN MU release to
existing managed sites without manually copying MU plugins. This flow complements
the canonical stack build and source deployment; it does not create a second
source of truth.

## Release Units

- `mrn-stack-deployment-agent`: persistent, zero-frontend standard plugin on
  child sites. It accepts only authenticated MainWP Child extra-execution actions
  and only targets declared `wp-content/mu-plugins/mrn-*` paths.
- `mrn-mainwp-operations-api`: Dashboard-only controller. It validates release
  plans and packages, enforces confirmation and fresh backup receipts, limits
  batches, and sends signed child requests.
- `stack/manifests/stack-release.lock.json`: immutable release identity used to
  generate the exact MU release plan and component hashes.

## Canary

The first production canary for this rollout is `mrnwebdesigns.com`. Do not use a
development site as a substitute. A different canary requires an explicit owner
decision recorded in the rollout notes.

## Phase 1: Prepare The Control Plane

1. Complete MRN QA for both release units.
2. Commit and push both standalone repositories.
3. Build checksum-recorded plugin ZIPs from the committed sources.
4. Install or update `mrn-mainwp-operations-api` only on the MainWP Dashboard.
5. Reconnect or restart the MainWP MCP adapter if needed so the four stack
   deployment abilities are available.

## Phase 2: Seed The Child Agent

1. Build `mrn-stack-deployment-agent.zip` from its committed source.
2. Use MainWP inventory to identify sites missing the agent.
3. Start and verify a remote database-only Updraft backup for each target.
4. Use `mrn-mainwp/install-plugin-package-v1` with a maximum of 25 sites per
   confirmed batch to install and activate the agent.
5. Call `mrn-mainwp/get-stack-deployment-agent-status-v1` for every target.
6. Stop if any site does not report the expected agent version, writable content
   path, private storage readiness, and ZIP support.

The bootstrap manifest includes the agent so newly created stack sites receive it
without this seeding step.

## Phase 3: Build An Immutable MU Release

1. Finalize and commit all in-scope MRN source repositories.
2. Generate the stack release lock from clean, exact commits.
3. Run `python3 stack/scripts/build-mainwp-mu-release.py --rollout-id
   <unique-rollout-id> --output-dir releases/mainwp-mu/<unique-rollout-id>`.
   The builder verifies source trees against the lock, derives exact MU targets,
   includes the runtime release lock, and emits deterministic plan/package bytes.
4. Record the generated `checksums.json`, exact `plan.json`, and package filename
   in the rollout notes. Never hand-author or modify these artifacts.
5. Validate the package independently on the Dashboard and child agent before
   any promotion.

## Phase 4: Canary Preflight And Apply

1. Run `mrn-mainwp/preflight-mu-release-v1` against `mrnwebdesigns.com`.
2. Review the exact target, legacy-path, protected-path, writable-storage, and
   release identity results.
3. Create and verify a fresh remote database-only Updraft backup.
4. Run `mrn-mainwp/install-mu-release-v1` with `confirm=true` and the fresh
   backup receipt.
5. Verify the child result, rollback readiness, stack runtime report, public
   frontend, WordPress admin, and any release-specific smoke checks.
6. Stop and diagnose on any mismatch. Do not expand the rollout merely because
   the child request returned HTTP success.

## Phase 5: Fleet Expansion

1. Partition approved sites into batches of no more than 25.
2. For each batch, run preflight and retain the per-site results.
3. Create and verify a fresh remote database-only backup for every site in that
   batch immediately before the write.
4. Review the exact batch preview, then run the confirmed apply.
5. Verify runtime parity and public/admin smoke checks before starting the next
   batch.
6. Record successful, failed, skipped, and drifted sites. Never silently treat a
   partial batch as complete.

## Rollback

1. Identify the exact rollout ID retained by the child agent.
2. Create and verify a new remote database-only backup before rollback.
3. Review the named target and run `mrn-mainwp/rollback-mu-release-v1` with
   `confirm=true` and the fresh receipt.
4. The child restores from private storage using same-filesystem moves. If any
   restore step fails, it retains the rollback record and reports failure.
5. Re-run runtime and browser verification after rollback.

## Non-Negotiable Gates

- MainWP authenticated child transport only; no public deployment endpoint.
- Exact committed sources, release lock, plan checksum, package checksum, and
  per-file checksums.
- MU allowlist only. The agent is not a general plugin or filesystem installer.
- Maximum 25 sites per operation.
- Read-only preflight before confirmation.
- Fresh verified remote database-only backup immediately before every apply or
  rollback.
- Receipt consumption only after a successful child write.
- Per-site results and explicit handling of partial failures.
