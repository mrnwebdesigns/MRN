# Site Styles (MU)

## Summary

- Name: `Site Styles (MU)`
- Slug: `mrn-site-colors`
- Type: MU plugin
- Current version: `0.1.4`
- Source path:
  - `/Users/khofmeyer/Development/MRN/mu-plugins/mrn-site-colors`

## Purpose

This plugin is the stack’s shared design-token registry.

It started as Site Colors and evolved into Site Styles so it could own:

- shared site color variables
- graphic elements
- accent definitions
- motion effect presets
- shared token/helper APIs used by themes and plugins

## Ownership Boundary

This plugin owns:

- color token storage
- color helper functions
- graphic element storage
- bottom-accent contract helpers
- motion preset storage for row effects
- CSS variable output for front-end, admin, and login

It should be treated as the source of truth for shared design tokens in the stack.

## Admin Surface Area

The plugin adds:

- `Settings -> Site Styles`

The settings page allows editors/developers to manage:

- site colors
- graphic elements
- dark scroll card motion presets
- optional accent spacing overrides

## Data Model

Stored options include:

- `mrn_site_colors`
- `mrn_site_graphic_elements`
- `mrn_site_dark_scroll_card_presets`

Each color stores:

- `name`
- `slug`
- `value`

Each graphic element stores:

- `name`
- `slug`
- `css`
- optional `space`

Each dark scroll card preset stores:

- `name`
- `slug`
- `background`
- `text`
- `muted_text`
- `button_background`
- `button_text`
- `border_alpha`
- `shadow_alpha`
- `image_brightness`
- `image_saturation`

## Front-End / Theming Behavior

This plugin is explicitly a front-end and theming plugin.

It prints CSS variables and graphic-element CSS into:

- `wp_head`
- `admin_head`
- `login_head`

That means themes and plugins can rely on the same shared token output across:

- front-end pages
- admin screens
- login screen

## Color Variable Contract

Shared color variables are emitted using:

- `--site-color-{slug}`

Important helper functions:

- `mrn_site_colors_get_all()`
- `mrn_site_colors_get_value()`
- `mrn_site_colors_get_css_var()`
- `mrn_site_colors_get_map()`

## Graphic Element / Accent Contract

The plugin also owns the stack’s shared accent contract.

Key helper functions:

- `mrn_site_styles_get_graphic_elements()`
- `mrn_site_styles_get_graphic_element_map()`
- `mrn_site_styles_get_graphic_element_choices()`
- `mrn_site_styles_get_bottom_accent_slug()`
- `mrn_site_styles_get_bottom_accent_contract()`

Current shared accent contract:

- class: `has-bottom-accent`
- attribute: `data-bottom-accent="slug"`

The plugin also prints base accent CSS so those sections get a common `::after` hook and default spacing behavior.

## Intended Usage By Developers

When a front-end layout or reusable block needs a shared color token:

- prefer Site Styles
- do not invent one-off duplicate theme token names if the value is meant to be shared site-wide

When a layout or block needs selectable accent visuals:

- populate the selector from `mrn_site_styles_get_graphic_element_choices()`
- render the shared accent contract
- let Site Styles own the actual CSS definitions

## Public Hooks / Extension Points

This plugin is currently more helper-function oriented than hook oriented.

The main developer-facing integration surface is its function library rather than custom actions/filters.

Motion preset helpers:

- `mrn_site_styles_get_dark_scroll_card_presets()`
- `mrn_site_styles_get_dark_scroll_card_preset_map()`
- `mrn_site_styles_get_dark_scroll_card_preset_choices()`

## Rollout / Packaging Notes

- This is a stack MU plugin.
- It rolls out through stack MU source sync.
- It is part of the stack baseline.
- Current packaged baseline in memory: `0.1.2`

## Risks / Gotchas

- Do not treat Site Styles as “just an admin page.”
- If themes hardcode duplicate color systems instead of using Site Styles, token drift will follow.
- Accent behavior should stay shared and contract-driven, not recreated differently in each template.
