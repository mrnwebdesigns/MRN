# MainWP Full Stack Development Canary

Use this checklist to prove one schema-2 Fleet rollout on an explicitly named
development or review site before any production canary. It supplements
`MAINWP_FLEET_ROLLOUT_PLAN.md`; every backup, confirmation, and verification gate
in that plan still applies.

## Candidate Record

- Exact site URL:
- Freshly resolved MainWP site ID:
- Environment/provider:
- Current `template`:
- Current `stylesheet`:
- Current release ID and lock SHA:
- Target release ID and lock SHA:
- Deployment-agent version/tree SHA/file count:
- Approved test pages and admin screens:
- Site-owned layout contract and pinned QA Engine ref:
- Rollback owner and test window:

Do not populate an ID from memory. Resolve the exact URL through MainWP and run
a fresh, site-scoped sync. The candidate must already be an approved MainWP child
or receive separate approval to be added; this checklist does not authorize site
registration.

## No-Write Readiness

1. Require `mainwp://status` to report the exact MRN Dashboard host and a nonzero
   ability count.
2. Require `mrn-mainwp/qualify-stack-site-v1` to return
   `classification=ready_for_release_preflight`, schema-2 support, canonical
   `mrn-base-stack` plus `mrn-base-stack-child`, ready storage, and zero incomplete
   rollouts. Runtime drift is recorded but is not update authorization.
3. Verify the release was built from clean committed sources and retain its exact
   `checksums.json`, `plan.json`, and ZIP.
4. Run `mrn-mainwp/preflight-stack-release-v1` for a one-element site ID list.
   Require every target and prerequisite to match and `ready=true`.
5. Capture baseline public pages, the selected admin screens, console/network
   errors, accessibility results, and release-specific interactions. When the
   site repository provides `qa/layout-contracts.json`, run it with the pinned
   MRN QA Engine and retain its viewport screenshots plus JSON result as the
   known-good baseline.

Stop on any missing evidence. Qualification and preflight are read-only; neither
step may seed a plugin, assign the `Full Stack` tag, or reconcile a rollout marker.

## Apply Proof

1. Obtain explicit approval for this exact canary and release.
2. Create and verify a fresh remote database-only Updraft backup receipt.
3. Call `mrn-mainwp/install-stack-release-v1` once with the exact site, plan,
   package, checksums, receipt, and `confirm=true`.
4. Require child apply success, `rollback_ready=true`, and the Dashboard's fresh
   runtime verification of release ID, lock SHA, required components, parent
   theme, and preserved child stylesheet.
5. Clear only relevant caches and repeat the baseline public/admin checks. Run
   accessibility, interaction, API, and performance checks applicable to the
   release. Re-run the exact site-owned layout contract when one is configured
   and require every viewport to pass. HTTP 200 or generic browser smoke alone
   is not acceptance.

If a filesystem write succeeded but runtime or rendered verification failed, do
not reapply. Preserve the rollout evidence and move to the rollback decision.

## Rollback Drill

For the first development canary, prove rollback before production:

1. Obtain a new verified database-only backup receipt.
2. Call `mrn-mainwp/rollback-stack-release-v1` for the exact rollout ID with
   `confirm=true`.
3. Require every target to restore successfully and the used rollback record to
   close normally.
4. Re-run runtime, public, admin, accessibility, interaction, API, and performance
   checks against the restored release, including the same site-owned layout
   contract when one is configured.

Record the canary as passed only when both apply and rollback evidence are
complete. A passed development canary does not authorize a production rollout.
