# MRN Figma Sync

## Summary

- Name: `MRN Figma Sync`
- Slug: `mrn-figma-sync`
- Type:
  - standard plugin
- Source path:
  - `/Users/khofmeyer/Development/MRN/plugins/mrn-figma-sync`

## Purpose

- Provides the first-pass deterministic integration layer between structured Figma exports and the existing MRN builder architecture.
- Reuses the live ACF/page-builder contracts already owned by `mrn-base-stack` and `mrn-reusable-block-library`.
- Validates imports before writing to WordPress.

## Ownership Boundary

This plugin owns:

- live registry discovery for builder fields and reusable-block field groups
- normalized Figma payload mapping
- token resolution helpers for Site Styles-backed fields
- validation, snapshot, import, and rollback workflows
- authenticated REST and WP-CLI entry points for the sync pipeline

This plugin does not own:

- raw Figma extraction
- arbitrary front-end rendering
- theme template markup
- the underlying ACF field groups themselves

## Current Entry Points

- REST:
  - `GET /wp-json/mrn-figma-sync/v1/registry`
  - `POST /wp-json/mrn-figma-sync/v1/map`
  - `POST /wp-json/mrn-figma-sync/v1/import`
  - `POST /wp-json/mrn-figma-sync/v1/rollback`
- WP-CLI:
  - `wp mrn-figma-sync registry`
  - `wp mrn-figma-sync map`
  - `wp mrn-figma-sync validate`
  - `wp mrn-figma-sync import`
  - `wp mrn-figma-sync rollback`

## Platform Fit

- Uses `update_field()` to write into:
  - `page_hero_rows`
  - `page_content_rows`
  - `page_after_content_rows`
  - `sidebar_layout`
  - `page_sidebar_rows`
- Expands seamless ACF clone fields so CTA/Grid/FAQ-style page blocks can be described through their real subfields.
- Pulls Site Styles tokens from the live site via helper functions instead of duplicating a token registry.

## Recommended Rollout Path

1. Keep this as the canonical WordPress-side validator/importer.
2. Add a Figma-side or service-side normalizer that produces the plugin’s normalized payload shape.
3. Add media ingestion so design assets can become attachment IDs before import.
4. Add an admin preview/diff UI after the registry and CLI flows settle.
