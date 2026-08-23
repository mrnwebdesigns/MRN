# Release Versioning Strategy

## Goal
- Keep version bumps consistent across stack theme, MU plugins, and standard plugins.
- Ensure every release artifact can be traced back to an exact source commit and version.

## Scope Rules
- Each repo/release unit versions independently.
- In this workspace, release units can include:
  - main repo stack theme and in-repo plugins/MU plugins
  - nested plugin repos under `plugins/*/.git` (independent)
- Bump only the components that actually changed in the approved release scope.

## Bump Rules
- `patch` bump:
  - bug fixes
  - internal refactors with no new public behavior
  - contract-compatible admin/frontend/runtime adjustments
- `minor` bump:
  - additive features that are backward-compatible
  - new config options or new layouts that do not break existing contracts
- `major` bump:
  - intentionally breaking contract changes
  - removed compatibility behavior that can change existing saved-data handling

## Required Version Sync Points
- Stack theme:
  - `stack/themes/mrn-base-stack/style.css` (`Version:`)
  - `stack/themes/mrn-base-stack/functions.php` (`_S_VERSION`)
- Standard plugins:
  - plugin header `Version:` in main plugin file
  - plugin runtime constant (for example `const VERSION`) when present
- MU plugins:
  - plugin header and runtime constants where the component defines them

## Release Flow (Going Forward)
1. Create a dedicated release worktree from clean, current, merged `main`. Do
   not promote from a feature worktree, a dirty canonical checkout, or an
   unmerged branch.
2. Identify the complete release scope by comparing `main` with the previous
   immutable release lock and inventorying every changed deployable component.
   `git diff --name-only origin/main..HEAD` is useful for a feature task, but it
   is not sufficient for promotion after several branches have merged.
3. Detect changed deployable components (theme/plugin/MU/runtime/deployment
   contract).
4. Apply required version bumps using the bump rules above.
5. Verify header/runtime version consistency for each changed component.
6. Rebuild release artifacts:
   - `stack/scripts/build-release-zips.sh theme`
   - `stack/scripts/build-release-zips.sh plugins <slug ...>`
   - `stack/scripts/build-release-zips.sh mu-plugins <slug ...>`
   - Named plugin builds resolve canonical in-repo sources first and then the sibling `MRN-plugins` workspace. Set `MRN_STANDALONE_PLUGINS_ROOT` when the standalone repositories are checked out elsewhere.
7. Run release QA (theme/security/smoke/perf/accessibility/API/parity/rollout
   contract as applicable). Feature-gate isolation does not waive promotion QA.
8. Record release notes in:
   - `stack/CHANGELOG.md`
   - `stack/STACK_VERSION.md`
9. Generate the immutable release lock after all runtime source commits are final:
   - `python3 stack/scripts/generate-stack-release-lock.py --output stack/manifests/stack-release.lock.json`
   - commit the generated lock separately so its recorded source commits remain exact
10. Build a deterministic MainWP MU package from that exact lock when preparing an existing-site fleet rollout:
   - `python3 stack/scripts/build-mainwp-mu-release.py --rollout-id <unique-rollout-id> --output-dir releases/mainwp-mu/<unique-rollout-id>`
   - use the generated `checksums.json`, exact `plan.json`, and ZIP as the preflight/apply identity; never hand-author the plan or package
11. Deploy in stack-first order for stack-owned runtime changes, then rollout
    surfaces.
12. Read back target component versions/hashes and compare them with the release
    lock. Do not mark the stack current until source, catalog, lock, artifacts,
    deployment evidence, and target inventory agree.

## Release Lock

`stack/manifests/stack-release.lock.json` is generated from the component
catalog, theme manifest, version headers, and exact Git commits. It uses the
`sha256-tree-v1` digest: SHA-256 over sorted records containing each deployable
file's relative path, SHA-256, and byte size. Git metadata, local caches,
`node_modules`, Playwright output, and packaging scratch directories are
excluded. Symlinks are rejected so a digest cannot silently include content
outside the declared release source.

The generator fails closed on missing components, duplicate slugs, dirty
external component repositories, missing version headers, or catalog/header
version drift. Do not hand-edit the generated lock.

Theme records declare their verification mode. The parent theme is `exact` and
must match its release hash. The generic active child in the bootstrap manifest
is `site-derived`: it is the source template for a site-owned, renamed child
theme and must not be treated as an exact fleet runtime slug or hash.

## Enforcement Baseline
- No release should be marked ready when:
  - version sync points are inconsistent
  - release artifacts were not rebuilt for changed deployables
  - required QA scripts for the affected surfaces were skipped without explicit reason
  - the release worktree is dirty or is not based on current merged `main`
  - a deployable change since the prior lock is missing from the release inventory
  - runtime/fleet inventory differs from the generated release lock

Feature acceptance and stack promotion follow
`docs/MRN-CONCURRENT-DEVELOPMENT-POLICY.md`. An unrelated baseline finding does
not block an isolated feature commit, but unresolved release-scope or runtime
drift blocks promotion.
