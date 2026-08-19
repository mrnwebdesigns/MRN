# MRN WordPress Component Governance

This document defines how MRN-owned WordPress plugins and MU components are classified, supported, packaged, and reviewed.

The authoritative machine-readable inventory is [`manifests/component-catalog.json`](./manifests/component-catalog.json). The human-readable [`PLUGIN_CATALOG.md`](./PLUGIN_CATALOG.md) summarizes it.

## Product Principle

The MRN stack is a curated website platform plus a catalog of independently selectable capabilities. A plugin being owned or supported by MRN does not mean it belongs on every site.

The production baseline should contain only mandatory platform behavior. Shared features and integration adapters are installed only when a site's requirements call for them.

## Classification Layers

### Platform Required

Mandatory, stack-owned behavior installed for production MRN-stack websites. Platform modules may be released as one compatible artifact while remaining separate internal modules.

### Optional Shared

Stack-agnostic or broadly reusable features installed deliberately per site. These components retain independent versions and release boundaries.

### Optional Integration

Adapters for a third-party plugin, vendor, API, or service. They are installed only when that dependency and use case are present.

### Dashboard Only

Operational components intended for the MainWP or management runtime rather than client websites.

### Development Only

Diagnostics, fixture generation, inspection, or local-development behavior. These components are excluded from production defaults.

### Maintenance Only

Tools installed temporarily or deliberately for migration, cleanup, retention, or repair. They are not persistent production defaults unless an approved policy explicitly requires them.

### Review and Archive Candidate

`review` means an ownership, product-boundary, or support decision remains open. `archive-candidate` is a recommendation only; it does not authorize deletion, repository archival, manifest changes, or runtime changes.

## Distribution Is Not Classification

The catalog records both:

- `current_distribution`: what today's bootstrap or MU loader actually does
- `target_tier`: the intended product classification

These values are deliberately separate. Phase 1 documentation does not change installation behavior. Changes to bootstrap profiles, the MU loader, packaging, or deployment require a later approved phase.

## Source and Release Ownership

1. Each runtime slug must have exactly one canonical source.
2. A repository boundary should correspond to an independently releasable component.
3. The MRN repo may use a symlink to a standalone plugin source; it must not maintain a divergent copied implementation under the same slug.
4. A release artifact must be traceable to its canonical source commit and declared version.
5. Duplicate, missing, or ambiguous sources block production release until reconciled.

## Component Catalog Requirements

Each catalog entry records:

- slug, name, version, runtime type, classification, and lifecycle
- current distribution and target tier
- canonical repository/path and source confidence
- hard and soft dependencies
- coordinated-release group when contracts span components
- major persistent data, cron, routes/commands, and external services
- a concise responsibility statement

The catalog is updated in the same change as any component addition, removal, rename, version change, ownership change, or public contract change.

## Release Boundaries

- Platform modules may be packaged together because they are installed and tested as a compatible unit.
- Optional shared features and integration adapters remain independently installable unless consolidation demonstrably reduces duplicate execution, ambiguous data ownership, or mandatory coordinated releases.
- Plugin count alone is never a consolidation reason.
- Development and maintenance tools do not enter a production-default profile.

## Compatibility Contracts

Existing hooks, option names, database tables, post types, metadata, routes, commands, asset handles, theme functions, and rendered markup may be consumed outside the owning component. Before changing them:

1. identify consumers
2. define the replacement contract
3. provide a compatibility shim or migration where required
4. document rollback behavior
5. test the coordinated-release group

## Approval Gates

Cleanup work proceeds one approved phase at a time:

1. catalog and product classification
2. canonical source reconciliation
3. sunset and quarantine decisions
4. installer and package profiles
5. shared infrastructure consolidation
6. Config Helper decomposition
7. production QA and release gates

Classification does not authorize archive, deletion, packaging changes, deployment, activation, deactivation, or migration. Each later phase requires a reviewed change list and explicit approval.

## Current Governance Decisions

- `mrn-mega-menu` is canonical in `MRN/plugins/mrn-mega-menu`; its version and release artifact follow the MRN stack repository.
- `searchwp-editor-performance` is canonical in its standalone repository under `MRN-plugins` and remains a supported development-only dependency. It is installed with the current shared bundle so development sites can keep SearchWP frontend search enabled without its expensive editor/indexing behavior.
- `MRN-disable-core-auto-updates` was approved for sunset on 2026-08-14 and is excluded from the target product catalog. This does not authorize changes to existing sites.
- `mrn-contextual-content-editor` was removed from `manifests/plugins.txt` on 2026-08-19 because it is not production ready. It stays `catalog-only` with lifecycle `review`; this does not change existing sites.
- `mrn-duplicate-enhance`, `mrn-hierarchical-menu-taxonomies`, `mrn-database-retention`, `mrn-layout-import-export`, and `mrn-tokens` were added to `manifests/plugins.txt` on 2026-08-19 as required components for all rollouts. Their target tiers are unchanged; the bootstrap now installs them regardless of tier.
- `mrn-schema-bridge` and `mrn-active-style-guide` were reclassified to `platform-required` on 2026-08-19. Both were already loaded by the MU loader on every site, so this records intent rather than changing installation.
- `mrn-license-vault` and `mrn-unified-exporter` were archived on 2026-08-19. Neither has a canonical source; the only artifacts are packaged zips on the stack manager, which are retained and not deleted. Neither is referenced by `manifests/plugins.txt`.
- `mrn-google-reviews` is held out of the stack as of 2026-08-17 because the plugin is incomplete. It stays `catalog-only` and outside `manifests/plugins.txt`; its target tier remains `review` until the source is committed and secret-management, QA, and release-readiness review are complete.
- `mrn-editor-ui-css` was archived on 2026-08-14 after confirming it was superseded by `mrn-admin-ui-css` and absent from both the MU loader and runtime sync source.
- `mrn-duplicate-enhance` is a Post Duplicator integration adapter under `plugins/`. It is not part of the MU platform. It was added to the default plugin manifest on 2026-08-19; before that date it was catalog-only.
- `mrn-universal-sticky-bar` is required on every MRN Stack site but remains an independently released standard plugin. Its off-stack utility is a valid release boundary, while `manifests/plugins.txt` and the rollout-contract gate enforce the Stack requirement. Moving it to MU code would add a coordinated migration and double-load risk without reducing responsibility.
- the Stack copy of `shared/mrn-sticky-settings-toolbar.php` is canonical for Stack runtime behavior. The standalone sticky-bar repository retains a byte-identical bundled fallback for non-Stack installs; parity is a release gate.
- environment selection remains owned by the future hosting platform's existing `stack`/`plain` site profiles and `dev`/`staging`/`production` environment policy. The MRN repository must not introduce a competing environment-profile vocabulary.
- the future hosting policy currently disables SearchWP in development; that conflicts with the approved MRN development contract and must be reconciled before the hosting platform consumes this release. Staging remains a separate policy decision.
- several optional and maintenance components remain in the standard bootstrap. Removing them requires feature-selection support in the hosting platform so site capabilities can be declared without duplicating environment policy.
