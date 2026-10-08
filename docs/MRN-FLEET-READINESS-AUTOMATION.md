# Automatic source-to-Fleet readiness

The owner's routine work ends with accepted Stack/plugin source committed and
pushed to its merged default branch. The release process must then qualify,
package, lock, and publish that source for Fleet without a separate release
request or manual bookkeeping action from the owner.

Site backup, deployment, and installed verification are outside this automation.
They remain explicitly initiated, exactly targeted, backup-gated operations.
Publishing a release must never invoke a site installer, change a site's release
selection, run a remote content migration, or clear an existing site hold.

## Trigger and ownership

- A push to accepted MRN `main`, including a PR merge, starts reconciliation.
- Merged default-branch changes in participating plugin repositories also start
  reconciliation. A periodic scan covers missing or delayed cross-repository
  events, so the owner need not remember to dispatch a Stack workflow.
- Local-only commits and unmerged feature branches remain acceptance/review
  inputs. They must not become the distributed default merely because someone
  committed or pushed a feature branch.
- One release coordinator serializes publication and coalesces concurrent
  changes. It starts from clean merged repositories and records the exact source
  commit vector. It never imports another task's dirty worktree.
- Recheck accepted source before changing the current distribution pointer.
  Newer merges queue another reconciliation; do not silently substitute their
  bytes into an already qualified artifact or cancel a publication mid-write.

## Required automatic work

1. Inventory changed deployable source across MRN and its participating plugins.
   Keep optional components optional and preserve retired-plugin exclusions.
2. Reconcile component versions, catalog and registry records, release metadata,
   exact source commits, and dependency contracts. Missing version bumps,
   unqualified dependencies, or an ambiguous package are actionable failures,
   not permission to invent qualification evidence.
3. Run required MRN source, contract, installed fixture/reference runtime,
   browser/editor, API, accessibility, and performance qualification. Use
   isolated local qualification environments; do not run speed tests on Dev.
   Include applicable WordPress-without-WooCommerce coverage. Site-specific
   production adoption checks belong to the later rollout.
4. Generate the immutable release lock from final clean accepted source. Build
   deterministic platform and Fleet payloads, bootstrap packages/theme archives,
   and selective plugin releases where applicable. Preserve existing release
   IDs, exact historical lock bytes, checksums, and recovery artifacts.
5. Prove that all applicable distributions and default installation inputs
   contain the same qualified source. A local ZIP or a merge is insufficient.
6. Publish through the configured source-distribution route, read back hashes,
   and advance the current Fleet distribution only after every required source,
   package, and publication check passes. Licensed/private inputs remain private.
7. Retain evidence binding each qualification result and artifact checksum to
   its exact source vector. Record release state independently of site state.

The job stays quiet on successful routine reconciliation. On failure it keeps
the previous qualified distribution current and reports the component, failed
check, evidence, and concrete correction. It must not ask for a canary URL just
to complete a source release. A required source check that fails or is skipped
still blocks Fleet readiness; automating the process does not waive QA.

## Readiness and rollout are separate

**Fleet ready** means the immutable release is qualified, published, and usable
by Fleet. It makes no claim that any client site has installed it.

**Site current** means an explicitly selected release has been applied to a
named site and its installed inventory and applicable user-visible checks pass.
Intentional rollout lag, a disconnected site, its backup-policy deficiency, or
an owner hold blocks that site's rollout; it does not invalidate an independently
qualified and published source distribution. A new adapter's genuine provider
and recovery qualification remains required before use of that adapter.

## Current implementation boundary

`.github/workflows/stack-promotion-drift.yml` currently checks MRN `main` plus six
platform-required standalone default branches. It runs after MRN main pushes,
daily, or on dispatch and preserves an inventory report. It is read-only and
does not build, qualify, or publish a release. It also does not yet reconcile all
independently released plugin sources.

Existing lock, assembly, Fleet plan, package, and distribution verification
tools remain the implementation authority. The automatic coordinator, qualified
runtime runner, private artifact publisher, cross-repository triggers, and
failure reporting must be connected and tested before automatic readiness can
be claimed. Updating these workflow rules alone does not activate that pipeline.

Until that implementation is operational, agents carrying out authorized Stack
release work must continue the source/package/publication steps themselves and
report any concrete remaining integration blocker. Do not replace the missing
automation with a recurring request for owner release bookkeeping or a site
deployment approval.
