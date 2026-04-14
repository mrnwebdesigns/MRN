# MRN Figma Sync

`mrn-figma-sync` is a first-pass integration layer for a deterministic Figma-to-WordPress workflow on the MRN stack.

It does not generate arbitrary front-end code. It sits above the existing theme builder, reusable block library, Site Styles tokens, and ACF contracts, then maps structured payloads into the same WordPress fields the platform already renders.

## First-Pass Goals

- discover the live MRN builder contract from ACF
- export a machine-readable component registry
- map a normalized Figma payload into MRN layout rows
- validate the result against the live builder schema
- support dry-run, import, snapshot, and rollback
- expose the foundation through WP-CLI and authenticated REST routes

## Current Architecture

The plugin treats the pipeline as:

```text
normalized Figma payload
-> component match + field aliasing
-> token resolution
-> WordPress layout payload
-> live ACF validation
-> update_field() into existing builder buckets
```

Managed builder buckets:

- `page_hero_rows`
- `page_content_rows`
- `page_after_content_rows`
- `sidebar_layout`
- `page_sidebar_rows`

The live registry is discovered from:

- `mrn-base-stack` ACF field groups
- `mrn-reusable-block-library` field groups
- Site Styles color and graphic-element helpers
- stack helper functions such as hidden builder layouts and section-width choices

## Why This Fits The MRN Platform

- It reuses the existing builder fields and template parts instead of inventing a parallel rendering system.
- It respects site-level token choices from Site Styles.
- It can discover hidden or disabled layouts from the live platform rather than assuming every layout is available.
- It keeps the fuzzy part narrow: source component matching and alias resolution. Once the layout target is chosen, the import path is strict and schema-driven.

## Schemas

The plugin ships lightweight schema definitions for:

- component registry
- normalized Figma export payload
- WordPress layout payload
- component mapping definitions
- token mapping definitions
- validation issues

Schema provider:

- [class-mrn-figma-sync-schema.php](/Users/khofmeyer/Development/MRN/plugins/mrn-figma-sync/includes/class-mrn-figma-sync-schema.php)

Example payloads:

- [figma-export.sample.json](/Users/khofmeyer/Development/MRN/plugins/mrn-figma-sync/examples/figma-export.sample.json)
- [wp-layout.sample.json](/Users/khofmeyer/Development/MRN/plugins/mrn-figma-sync/examples/wp-layout.sample.json)

## Registry Shape

The exported registry includes:

- `field_groups`
- `components`
- `reusable_blocks`
- `tokens`
- `constraints`

Each component entry is keyed by `{field_name}:{layout}` and includes:

- `field_name`
- `bucket`
- `layout`
- `label`
- `is_hidden`
- `fields`

Each field is normalized from the live ACF definition and preserves:

- field type
- required flag
- default value
- choices
- nested sub-fields
- nested flexible-content layouts

Clone fields are expanded when they use seamless clone groups, which lets the registry describe page-level CTA/Grid/FAQ blocks in terms of their real reusable-block subfields.

## Mapping Strategy

The current mapping layer is intentionally conservative.

It expects a normalized Figma payload that already contains structured component data, not screenshots or arbitrary HTML.

Component matching order:

1. exact target override in the payload
2. explicit component mapping definitions in [component-mappings.php](/Users/khofmeyer/Development/MRN/plugins/mrn-figma-sync/config/component-mappings.php)
3. exact target layout fallback when the payload already knows the layout name

Field mapping behavior:

- if a Figma prop key matches a WordPress field name, it is used directly
- otherwise configured field aliases are checked
- slot names can feed nested flexible-content fields
- token-like select fields such as `background_color`, `bottom_accent_style`, and `section_width` resolve through explicit token mappings first, then through live Site Styles choices
- unsupported components, layouts, and invalid values return explicit validation issues instead of being guessed

## Validation / Safety

The importer validates:

- payload shape
- target post existence and post-type match
- allowed builder buckets
- allowed layouts inside each bucket
- required fields
- select/button-group choices
- link array shape
- WordPress reference IDs for image/file/post-object fields
- nested repeater and flexible-content structures

If validation fails, import stops.

Before every import, the plugin stores a snapshot in post meta so rollback can restore the previous builder state.

## REST Endpoints

Authenticated routes:

- `GET /wp-json/mrn-figma-sync/v1/registry`
- `POST /wp-json/mrn-figma-sync/v1/map`
- `POST /wp-json/mrn-figma-sync/v1/import`
- `POST /wp-json/mrn-figma-sync/v1/rollback`

Write routes require editor-level capabilities and additionally check `edit_post` on the target post where relevant.

## WP-CLI Commands

Export registry:

```bash
wp mrn-figma-sync registry
```

Map a Figma payload:

```bash
wp mrn-figma-sync map --file=figma-export.json
```

Validate a payload:

```bash
wp mrn-figma-sync validate --type=figma --file=figma-export.json
wp mrn-figma-sync validate --type=layout --file=layout-payload.json
```

Dry-run an import:

```bash
wp mrn-figma-sync import --type=figma --file=figma-export.json --dry-run
```

Import:

```bash
wp mrn-figma-sync import --type=layout --file=layout-payload.json
```

Rollback:

```bash
wp mrn-figma-sync rollback --post=123
```

## Recommended Production Path

This first pass is the platform foundation, not the whole pipeline.

Recommended next layers:

1. Add a small external Figma export service that emits the normalized payload shape used here.
2. Promote component mappings from seed config into a versioned site/platform registry.
3. Add asset ingestion so image/file fields can resolve from exported Figma assets into WordPress attachment IDs.
4. Add an admin preview UI that calls the map/import REST endpoints in dry-run mode and shows diff + validation results before save.
5. Add automated QA around a fixture payload set so the registry and importer stay aligned with future ACF/theme changes.
