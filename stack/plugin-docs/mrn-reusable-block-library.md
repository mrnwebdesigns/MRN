# Reusable Block Library (MU)

## Summary

- Name: `Reusable Block Library (MU)`
- Slug: `mrn-reusable-block-library`
- Type: MU plugin
- Current version: `0.1.6`
- Source path:
  - `/Users/khofmeyer/Development/MRN/mu-plugins/mrn-reusable-block-library`

## Purpose

This plugin provides the stack's typed reusable content system.

It exists so editors can create centrally managed content blocks once and reuse them across pages without duplicating data inside the page builder. It is one of the core architecture plugins in the stack.

## Ownership Boundary

This plugin owns:

- reusable block post types
- reusable block field groups
- reusable block admin UX
- reusable block render templates
- reusable block title guidance and validation
- reusable block library menu structure

This plugin does not own:

- the main page/post `Content` builder
- page/post composition
- theme-owned builder template parts

Those responsibilities live in the stack theme.

## Current Reusable Block Types

The typed block definitions are centralized in:

- `mrn_rbl_get_post_type_definitions()`

Current built-in types:

- `mrn_reusable_cta`
- `mrn_reusable_basic`
- `mrn_reusable_faq`
- `mrn_reusable_grid`
- `mrn_reusable_list`

## Admin Surface Area

The plugin:

- registers reusable block CPTs
- creates a unified library admin menu
- honors Config Helper back-end visibility toggles for reusable block post types
- hides unnecessary default metaboxes
- customizes parent/submenu highlighting
- enforces title guidance and required-title behavior
- adds slug columns to reusable block admin lists
- removes ordering interfaces that are not part of the intended workflow
- ensures starter blocks exist

Relevant functions include:

- `mrn_rbl_register_post_types()`
- `mrn_rbl_register_admin_menu()`
- `mrn_rbl_remove_unneeded_metaboxes()`
- `mrn_rbl_render_title_guidance()`
- `mrn_rbl_require_title_on_save()`
- `mrn_rbl_register_admin_columns()`
- `mrn_rbl_remove_ordering_submenus()`
- `mrn_rbl_ensure_starter_blocks()`

## ACF / Content Model

The plugin registers local ACF field groups for the reusable block types in:

- `mrn_rbl_register_acf_field_groups()`

The current reusable block contracts mirror the stack’s standard layout patterns as closely as possible.

Examples:

- CTA:
  - `label`
  - `text_field`
  - `text_field_tag`
  - `content`
  - `link`
  - config fields for link presentation, background, accent
- Basic:
  - `label`
  - `text_field`
  - `text_field_tag`
  - `content`
  - `image`
  - `link`
  - config fields for link presentation, image placement, background, accent
- Grid:
  - `label`
  - `text_field`
  - `text_field_tag`
  - repeater items with label/title/tag/content/link
  - config fields for link presentation, background, accent

## Rendering

The reusable library renders blocks through its own template system.

Key functions:

- `mrn_rbl_get_template_slug_for_post_type()`
- `mrn_rbl_locate_template()`
- `mrn_rbl_get_render_context()`
- `mrn_rbl_render_context()`
- `mrn_rbl_render_block()`
- `mrn_rbl_render_fields_as_block()`
- `mrn_rbl_shortcode()`

## Template Override Contract

Theme override path:

- `wp-content/themes/{active-theme}/mrn-blocks/{template}.php`

If no theme override exists, the plugin falls back to:

- `/Users/khofmeyer/Development/MRN/mu-plugins/mrn-reusable-block-library/templates/`

Current built-in template mapping:

- `mrn_reusable_basic` -> `basic-block`
- `mrn_reusable_cta` -> `cta`
- `mrn_reusable_faq` -> `faq`
- `mrn_reusable_grid` -> `content-grid`

## Front-End / Theming Behavior

This plugin does affect front-end rendering.

Important theming-related facts:

- it outputs reusable block markup through templates
- its templates may call the theme helper `mrn_base_stack_format_heading_inline_html()` when available
- it uses Site Styles color and accent helpers where applicable
- it participates in the shared bottom-accent contract

It is not the design-token source of truth. Site Styles owns shared colors and graphic elements.

## Public Hooks / Extension Points

Known custom extension points:

- `mrn_rbl_post_type_definitions`

This filter allows new reusable block post types to be registered without rewriting the base registration flow.

Useful helper functions for integration:

- `mrn_rbl_get_post_types()`
- `mrn_rbl_get_site_color_choices()`
- `mrn_rbl_get_heading_tag_choices()`
- `mrn_rbl_get_link_style_choices()`
- `mrn_rbl_render_fields_as_block()`
- `mrn_rbl_render_block()`

## Data / Storage

The plugin stores data primarily in:

- reusable block custom post types
- ACF-managed post meta for each reusable block type

It also manages starter-block provisioning and admin presentation data indirectly through CPT and menu registration.

## Special Rules

- Reusable blocks are admin-managed content primitives, not direct substitutes for the theme’s page/post builder.
- The theme should not register a duplicate page/post builder field group inside this plugin.
- Reusable block CPT requests are intentionally non-public.
- The reusable block picker in the theme should show published reusable blocks only.
- Page-specific conversion targets exist, but they are conversion-only and should stay hidden in the normal picker.

## Rollout / Packaging Notes

- This plugin rolls out through stack MU source sync, not through the standard plugin manifest.
- It is part of the stack baseline.
- Current packaged baseline in memory: `0.1.3`

## Risks / Gotchas

- Do not let this plugin grow into a second page/post builder system.
- Keep ownership boundaries clear between reusable data models and theme composition.
- Template changes here can affect both reusable block rendering and theme-level conversion/rendering flows that call `mrn_rbl_render_fields_as_block()`.
