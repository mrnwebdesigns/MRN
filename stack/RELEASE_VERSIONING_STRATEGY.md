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
1. Identify approved release scope from `git diff --name-only origin/main..HEAD`.
2. Detect changed deployable components (theme/plugin/MU).
3. Apply required version bumps using the bump rules above.
4. Verify header/runtime version consistency for each changed component.
5. Rebuild release artifacts:
   - `stack/scripts/build-release-zips.sh theme`
   - `stack/scripts/build-release-zips.sh plugins <slug ...>`
   - `stack/scripts/build-release-zips.sh mu-plugins <slug ...>`
   - Named plugin builds resolve canonical in-repo sources first and then the sibling `MRN-plugins` workspace. Set `MRN_STANDALONE_PLUGINS_ROOT` when the standalone repositories are checked out elsewhere.
6. Run release QA (theme/security/smoke/perf/rollout-contract as applicable).
7. Record release notes in:
   - `stack/CHANGELOG.md`
   - `stack/STACK_VERSION.md`
8. Generate the immutable release lock after all runtime source commits are final:
   - `python3 stack/scripts/generate-stack-release-lock.py --output stack/manifests/stack-release.lock.json`
   - commit the generated lock separately so its recorded source commits remain exact
9. Deploy in stack-first order for stack-owned runtime changes, then rollout surfaces.

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

## Enforcement Baseline
- No release should be marked ready when:
  - version sync points are inconsistent
  - release artifacts were not rebuilt for changed deployables
  - required QA scripts for the affected surfaces were skipped without explicit reason
