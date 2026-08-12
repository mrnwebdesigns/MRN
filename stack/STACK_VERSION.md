# Stack Version

## Current Release
- Stack release: `2026.08.11-shortcode-form-output`
- Release date: `2026-08-11`
- Status: `current baseline`

## Included MRN-Owned Components
- Theme:
  - `mrn-base-stack` `1.2.103`
  - `mrn-base-stack-child` `1.0.1`
- MU plugins:
  - `mrn-active-style-guide` `0.1.5`
  - `mrn-admin-ui-css` `3.2.3`
  - `mrn-shared-assets` `0.1.3`
  - `mrn-editor-lockdown` `1.0.32`
  - `mrn-environment-runtime` `0.2.0`
  - `mrn-reusable-block-library` `0.1.28`
  - `mrn-schema-bridge` `0.4.1`
  - `mrn-site-colors` / `Site Styles` `0.1.38`
- Standard plugins:
  - `mrn-acf-focal-point` `1.1.2`
  - `mrn-announcements` `1.6.1`
  - `mrn-config-helper` `0.1.53`
  - `mrn-seo-helper` `0.3.4`
  - `mrn-editor-tools` `1.8.24`
  - `mrn-template-inspector` `0.2.7`
  - `mrn-universal-sticky-bar` `1.1.3`
  - `searchwp-editor-performance` `1.0.6`

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
