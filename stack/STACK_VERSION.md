# Stack Version

## Current Release
- Stack release: `2026.09.11-mainwp-full-stack-fleet-canary-fix`
- Release date: `2026-09-11`
- Status: `release candidate; deployment verification pending`

## Included MRN-Owned Components
- Theme:
  - `mrn-base-stack` `1.3.3`
  - `mrn-base-stack-child` `1.1.0`
- MU plugins:
  - `mrn-loader` `1.6.0`
  - `mrn-shared-runtime` `1.0.0`
  - `mrn-active-style-guide` `0.1.7`
  - `mrn-admin-data-post-types` `0.2.1`
  - `mrn-admin-ui-css` `3.2.4`
  - `mrn-dashboard-support` `1.3.0`
  - `mrn-disable-comments` `1.2.5`
  - `mrn-shared-assets` `0.2.0`
  - `mrn-editor-lockdown` `1.0.33`
  - `mrn-environment-runtime` `0.5.1`
  - `mrn-public-security-hardening` `0.4.1`
  - `mrn-schema-bridge` `0.6.0`
  - `mrn-site-colors` / `Site Styles` `0.1.39`
  - `mrn-updraft-backup-policy-loader` `0.5.1`
  - `mrn-updraft-local-retention` `0.5.1`
- Standard plugins:
  - `background-video-popout-disabler` `1.0.1`
  - `mrn-acf-character-count` `1.1.8`
  - `mrn-acf-focal-point` `1.1.2`
  - `mrn-ai-assist` `2.0.14`
  - `mrn-announcements` `1.8.1`
  - `mrn-comment-management` `1.1.7`
  - `mrn-config-helper` `0.1.59`
  - `mrn-cookie-consent` `1.1.42`
  - `mrn-database-retention` `1.1.0`
  - `mrn-editor-tools` `1.8.25`
  - `mrn-fontawesome-profile-manager` `0.5.0`
  - `mrn-google-fonts` `1.0.7`
  - `mrn-gtm-injector` `1.0.13`
  - `mrn-hierarchical-menu-taxonomies` `0.1.0`
  - `mrn-layout-import-export` `0.1.2`
  - `mrn-media-bulk-tools` `0.12.1`
  - `mrn-mega-menu` `0.17.2`
  - `mrn-recaptcha-enterprise-manager` `0.1.1`
  - `mrn-sendgrid-provisioning` `0.1.0`
  - `mrn-seo-helper` `0.4.0`
  - `mrn-stack-deployment-agent` `0.2.1`
  - `mrn-template-inspector` `0.2.7`
  - `mrn-tokens` `0.1.3`
  - `mrn-universal-sticky-bar` `1.1.8`

- Profile-gated standard plugins:
  - `mrn-reusable-block-library` `0.1.28` (`MRN_SITE_PROFILE=stack`)

## Stack Manifests
- Plugins manifest: [`manifests/plugins.txt`](/Users/khofmeyer/Development/MRN/stack/manifests/plugins.txt)
- Theme manifest: [`manifests/themes.txt`](/Users/khofmeyer/Development/MRN/stack/manifests/themes.txt)
- License manifest: [`manifests/licenses.txt`](/Users/khofmeyer/Development/MRN/stack/manifests/licenses.txt)
- Importer manifest: [`manifests/importers.txt`](/Users/khofmeyer/Development/MRN/stack/manifests/importers.txt)

## Notes
- This file tracks the current stack baseline, not every historical package ever shipped.
- Third-party packages in `manifests/plugins.txt` keep their own upstream versions and package filenames.
- Current baseline keeps the canonical AME export payloads, importer/manifests, bootstrap helper, shared shim, and stack MU wrapper loaders tracked in the main repo so release/deploy flows can verify and sync them consistently.
- Current baseline includes bounded recovery inventory and guarded reconciliation for exact, unchanged incomplete rollout markers that are physically empty or contain only recognized empty apply scaffolding, without introducing recursive deletion.
- Current candidate explicitly locks every tracked MU wrapper at its real deployed filename, including the Updraft backup-policy wrapper at `mrn-updraft-local-retention.php`.
- The Dashboard-only `mrn-mainwp-operations-api` `0.8.0` controller exposes schema-2 one-site qualification, preview, backup-gated full Stack rollout, rollback, recovery, and runtime-proof abilities as an independently released MainWP control-plane component; it is not installed on child sites.
- No site deployment is performed by this release preparation; backup, approval, canary, and runtime readback remain separate gates.
- `mrn-config-helper` is locked to standalone `0.1.59`, and `mrn-stack-deployment-agent` is locked to standalone `0.2.1` with installed prerequisite hashing aligned to the release generator's deterministic directory-walk order.
- Use [`CHANGELOG.md`](/Users/khofmeyer/Development/MRN/stack/CHANGELOG.md) for release notes.
