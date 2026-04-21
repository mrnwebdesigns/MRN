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
6. Run release QA (theme/security/smoke/perf/rollout-contract as applicable).
7. Record release notes in:
   - `stack/CHANGELOG.md`
   - `stack/STACK_VERSION.md`
8. Deploy in stack-first order for stack-owned runtime changes, then rollout surfaces.

## Enforcement Baseline
- No release should be marked ready when:
  - version sync points are inconsistent
  - release artifacts were not rebuilt for changed deployables
  - required QA scripts for the affected surfaces were skipped without explicit reason
