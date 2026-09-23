# Stack Version

## Current Release
- Stack release: `2026.09.23-reference-content-fleet`
- Release date: `2026-09-23`
- Status: `qualification pending; rollout withheld; no remote deployment`

## Included MRN-Owned Components
- Theme:
  - `mrn-base-stack` `1.4.1`
  - `mrn-base-stack-child` `1.1.0`
- MU plugins:
  - `mrn-loader` `1.6.1`
  - `mrn-shared-runtime` `1.0.0`
  - `mrn-active-style-guide` `0.1.7`
  - `mrn-admin-data-post-types` `0.2.1`
  - `mrn-admin-ui-css` `3.2.4`
  - `mrn-dashboard-support` `1.3.0`
  - `mrn-disable-comments` `1.2.5`
  - `mrn-shared-assets` `0.2.0`
  - `mrn-editor-lockdown` `1.0.33`
  - `mrn-environment-runtime` `0.5.1`
  - `mrn-public-security-hardening` `0.4.2`
  - `mrn-schema-bridge` `0.6.0`
  - `mrn-site-colors` / `Site Styles` `0.1.39`
  - `mrn-updraft-backup-policy-loader` `0.5.1`
  - `mrn-updraft-local-retention` `0.5.1`
- Standard plugins:
  - `background-video-popout-disabler` `1.0.2`
  - `mrn-acf-character-count` `1.1.8`
  - `mrn-acf-focal-point` `1.1.2`
  - `mrn-ai-assist` `2.0.14`
  - `mrn-announcements` `1.8.2`
  - `mrn-comment-management` `1.1.7`
  - `mrn-config-helper` `0.1.63`
  - `mrn-editor-tools` `1.8.25`
  - `mrn-fontawesome-profile-manager` `0.5.1`
  - `mrn-google-fonts` `1.0.7`
  - `mrn-hierarchical-menu-taxonomies` `0.1.0`
  - `mrn-layout-import-export` `0.1.2`
  - `mrn-media-bulk-tools` `0.13.1`
  - `mrn-mega-menu` `0.17.2`
  - `mrn-recaptcha-enterprise-manager` `0.1.2`
  - `mrn-sendgrid-provisioning` `0.1.0`
  - `mrn-seo-helper` `0.5.0`
  - `mrn-stack-deployment-agent` `0.2.3`
  - `mrn-template-inspector` `0.2.7`
  - `mrn-tokens` `0.1.3`
  - `mrn-universal-sticky-bar` `1.1.10`

- Profile-gated standard plugins:
  - `mrn-reusable-block-library` `0.2.0` (`MRN_SITE_PROFILE=stack`)

## Stack Manifests
- Plugins manifest: [`manifests/plugins.txt`](/Users/khofmeyer/Development/MRN/stack/manifests/plugins.txt)
- Theme manifest: [`manifests/themes.txt`](/Users/khofmeyer/Development/MRN/stack/manifests/themes.txt)
- License manifest: [`manifests/licenses.txt`](/Users/khofmeyer/Development/MRN/stack/manifests/licenses.txt)
- Importer manifest: [`manifests/importers.txt`](/Users/khofmeyer/Development/MRN/stack/manifests/importers.txt)

## Notes
- Parent theme `1.4.1` restores the registered Reference Content taxonomy map and
  shared editor/renderer destination support. Resources retain Content Only
  behavior and link to valid files only when item links are enabled; missing
  files remain unlinked. Existing ACF keys, clones and styling hooks are retained.
- Source/task checks pass. Required exact-candidate release QA and canary
  qualification are separate gates; local full-site contrast/timing findings and
  MainWP sync timeouts observed during task QA require resolution or verified
  requalification before rollout. See `docs/releases/2026.09.23-reference-content-fleet.md`.
- Public Security Hardening `0.4.2` canonicalizes relative WordPress redirects
  while serving the configured custom login route, including subdirectory and
  split home/siteurl installs. Default endpoint protection and encoded query
  arguments are preserved. The existing login container gains a main landmark
  without altering its structure. Component release QA and real HTTP flows pass.
- This candidate retains all other component versions and exact source trees
  from the prior complete Fleet release. Site qualification, prerequisites,
  fresh remote backup and post-deployment verification remain required.
- This file tracks the current stack baseline, not every historical package ever shipped.
- Third-party packages in `manifests/plugins.txt` keep their own upstream versions and package filenames.
- Current baseline keeps the canonical AME export payloads, importer/manifests, bootstrap helper, shared shim, and stack MU wrapper loaders tracked in the main repo so release/deploy flows can verify and sync them consistently.
- Current baseline includes bounded recovery inventory and guarded reconciliation for exact, unchanged incomplete rollout markers that are physically empty or contain only recognized empty apply scaffolding, without introducing recursive deletion.
- `mrn-database-retention` is not part of the platform baseline. Its independently released `1.1.1` package is catalog-only and available solely through the one-site, upgrade-only optional-plugin plan.
- This baseline includes `mrn-media-bulk-tools` `0.13.1` as a platform-required standard plugin and binds its exact standalone `main` commit and tree hash.
- Current candidate explicitly locks every tracked MU wrapper at its real deployed filename, including the Updraft backup-policy wrapper at `mrn-updraft-local-retention.php`.
- The Dashboard-only `mrn-mainwp-operations-api` release advances to `0.9.4`.
  It requires the deployment agent's secret-free managed-credential readiness
  report before a full Stack preflight can be considered ready and includes the
  exact Reusable Block Library main file in the optional-plugin upgrade
  allowlist.
  It remains a MainWP control-plane component and is never installed on child
  sites.
- No site deployment is performed by this release preparation; backup, approval, canary, and runtime readback remain separate gates.
- `mrn-config-helper` is locked to standalone `0.1.63`, preserves an existing
  UptimeRobot credential when its field is absent from a settings submission,
  and exposes the capability- and nonce-gated Content Types extension hook used
  by Stack-owned admin integrations.
- `mrn-stack-deployment-agent` is locked to standalone `0.2.3`; its authenticated
  status reports only presence/readiness booleans for managed credentials and
  never exposes their values.
- `mrn-recaptcha-enterprise-manager` `0.1.2` provides idempotent, fail-closed
  WPForms reCAPTCHA provisioning for Stack bootstrap and is available only as
  an upgrade of an existing installation through its checksum-locked Fleet
  record.
- The selective Stack-plugin registry retains exact Config Helper `0.1.59` and
  `0.1.60` rollback packages so an eligible site on a prior reviewed baseline
  can update only Config Helper and restore its precise prior tree if rollback
  is authorized.
- Universal Sticky Bar `1.1.10` adds complete WordPress release metadata and
  deterministic package exclusions without changing runtime behavior. Its exact
  `1.1.8` and `1.1.9` trees remain checksum-bound rollback material for eligible
  sites.
- SEO Helper `0.5.0` adds an administrator-managed post-type allow-list under
  Site Configurations while preserving existing behavior until the setting is
  first saved. WooCommerce internal order and coupon types remain
  hard-excluded. Its Fleet record remains upgrade-only and never installs the
  plugin when it is absent.
- Reusable Block Library `0.2.0` is retained as a Stack-profile bootstrap
  component and is also registered as an exact one-site, upgrade-only Fleet
  release. Missing installations remain a hard stop rather than an implicit
  install request.
- Parent theme `1.4.1` retains the shared Layout Class field and outer-element
  rendering contract on top of the Events and cloned-ACF-AJAX baseline.
- Selective Stack planning retains the exact signed locks reported by currently
  qualified Fleet sites, plus the subsequent reviewed baselines, and
  automatically selects the matching immutable lock by release ID and byte
  checksum. Missing or altered historical locks fail closed.
- Cookie Consent and GTM Injector are catalog-only optional integrations rather
  than required baseline components. Existing installations are unchanged and
  may move only through their checksum-locked optional-plugin plans.
- `mrn-loader` `1.6.1` aligns runtime-report hashing with that same release-generator walk order, retains loaded-component state in global scope, and reports each legacy MU wrapper from its exact locked path.
- Use [`CHANGELOG.md`](/Users/khofmeyer/Development/MRN/stack/CHANGELOG.md) for release notes.
