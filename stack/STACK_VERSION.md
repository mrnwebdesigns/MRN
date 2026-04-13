# Stack Version

## Current Release
- Stack release: `2026.04.13-page-edit-speed`
- Release date: `2026-04-13`
- Status: `current baseline`

## Included MRN-Owned Components
- Theme:
  - `mrn-base-stack` `1.1.21`
- MU plugins:
  - `mrn-admin-ui-css` `3.1.12`
  - `mrn-shared-assets` `0.1.1`
  - `mrn-editor-lockdown` `1.0.7`
  - `mrn-reusable-block-library` `0.1.11`
  - `mrn-site-colors` / `Site Styles` `0.1.4`
- Standard plugins:
  - `mrn-config-helper` `0.1.35`
  - `mrn-dummy-content` `0.1.9`
  - `mrn-editor-tools` `1.8.17`
  - `mrn-seo-helper` `0.2.9`
  - `mrn-universal-sticky-bar` `1.0.9`

## Stack Manifests
- Plugins manifest: [`manifests/plugins.txt`](/Users/khofmeyer/Development/MRN/stack/manifests/plugins.txt)
- Theme manifest: [`manifests/themes.txt`](/Users/khofmeyer/Development/MRN/stack/manifests/themes.txt)
- License manifest: [`manifests/licenses.txt`](/Users/khofmeyer/Development/MRN/stack/manifests/licenses.txt)
- Importer manifest: [`manifests/importers.txt`](/Users/khofmeyer/Development/MRN/stack/manifests/importers.txt)

## Notes
- This file tracks the current stack baseline, not every historical package ever shipped.
- Third-party packages in `manifests/plugins.txt` keep their own upstream versions and package filenames.
- Current baseline keeps the canonical AME export payloads, importer/manifests, bootstrap helper, shared shim, and stack MU wrapper loaders tracked in the main repo so release/deploy flows can verify and sync them consistently.
- Use [`CHANGELOG.md`](/Users/khofmeyer/Development/MRN/stack/CHANGELOG.md) for release notes.
