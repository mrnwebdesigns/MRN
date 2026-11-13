# MRN Active Style Guide

WordPress plugin release unit for `mrn-active-style-guide`.

## QA Engine

Run plugin-scoped QA with full static analysis:

```bash
MRN_QA_CODE_ANALYSIS_SCOPE=all mrn-qa run --project-root /Users/khofmeyer/Development/MRN/mu-plugins/mrn-active-style-guide
```

Runtime browser, accessibility, API, and performance checks should be run separately against an explicit target site when this plugin change affects rendered output or live WordPress behavior.

## Curated Layout Library preview profiles

The Layout Library creates **private** fixture pages and private demo assets solely
for its admin cards. It never selects a recent client upload. The generic profile
ships with abstract, text-free artwork and editorial copy; a site can supply a
curated profile without changing the shared Stack plugin:

```php
add_filter( 'mrn_active_style_guide_layout_preview_profile', function( array $profile ): array {
    $profile['id'] = 'example-v1';
    $profile['copy']['heading'] = 'A clear next step';
    $profile['assets']['feature'] = array(
        'kind' => 'local',
        // Must be a readable file below the active child or parent theme.
        'path' => get_stylesheet_directory() . '/preview-assets/example.svg',
        'alt'  => 'Abstract editorial artwork',
    );
    $profile['layouts']['showcase'] = array( 'showcase_presentation' => 'bento' );
    return $profile;
} );
```

The contract is versioned (`version: 1`) and supports `copy`,
`presentation_defaults`, `assets`, and per-layout `layouts` overrides. An asset
descriptor is either `local` (`path`, `alt`) or `generated` (`prompt`, `alt`).
Generated descriptors resolve to Stack-owned, abstract demo artwork only; do not
include logos, claims, readable text, or watermarks. Profile blueprints use the
layout's field names for readability, but fixture persistence resolves every root,
repeater, group, and flexible-content segment by its ACF field key.

Trilliant local adoption: add an opt-in profile through its local child-theme or
site-owned plugin, review the private cards at Appearance → Layout Library, then
regenerate. This is a local-only validation step; it neither changes client pages
nor authorizes Dev/Live deployment.
