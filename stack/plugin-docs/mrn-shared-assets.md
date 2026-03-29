# Shared Assets (MU)

## Summary

- Name: `Shared Assets (MU)`
- Slug: `mrn-shared-assets`
- Type: MU plugin
- Current version: `0.1.0`
- Source path:
  - `/Users/khofmeyer/Development/MRN/mu-plugins/mrn-shared-assets`

## Purpose

This plugin owns shared runtime asset bundles that should not belong to one feature plugin.

Current responsibility:

- Font Awesome runtime CSS and icon metadata

The goal is to let multiple consumers use the same asset source without coupling front-end or theme behavior to `Editor Enhancements`.

## Current Asset Surface

Current bundled assets:

- Font Awesome CSS bundle
- Font Awesome webfonts
- Font Awesome icon metadata in `icons.json`

Current asset location:

- `/Users/khofmeyer/Development/MRN/mu-plugins/mrn-shared-assets/assets/fontawesome`

## Front-End / Theming Behavior

This plugin does not automatically print Font Awesome everywhere.

Instead, it provides stable helper functions so themes, admin plugins, and future stack features can enqueue the shared asset intentionally.

Current helper surface:

- `mrn_shared_assets_fontawesome_version()`
- `mrn_shared_assets_fontawesome_path()`
- `mrn_shared_assets_fontawesome_url()`
- `mrn_shared_assets_fontawesome_css_url()`
- `mrn_shared_assets_enqueue_fontawesome()`
- `mrn_shared_assets_get_fontawesome_icons()`

## Consumer Rules

Use this plugin when a feature needs Font Awesome as a stack-level asset.

Current consumer:

- `Editor Enhancements`

Expected future consumers:

- `Config Helper` social icon configuration
- theme-level social/icon rendering where Font Awesome is intentionally chosen

Rules:

- do not make front-end icon rendering depend on `Editor Enhancements`
- prefer the shared helper functions instead of reaching into another plugin’s asset paths
- keep rendering and styling decisions in the theme layer unless a plugin truly owns the UI

## Rollout / Packaging Notes

- This is a stack MU plugin and should ship through the stack MU plugin deployment path.
- It also requires the root MU loader wiring in:
  - `/Users/khofmeyer/Development/MRN/stack/mu-plugins/mrn-loader.php`
  - `/Users/khofmeyer/Development/MRN/stack/mu-plugins/mrn-shared-assets.php`

## Risks / Gotchas

- If the shared MU plugin is unavailable, consumers need a graceful fallback or a clear failure mode.
- Keep this plugin focused on shared asset ownership, not general UI behavior.
