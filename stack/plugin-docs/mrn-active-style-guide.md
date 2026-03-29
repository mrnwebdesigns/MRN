# Style Guide (MU)

## Summary

- Name: `Style Guide (MU)`
- Slug: `mrn-active-style-guide`
- Type: MU plugin
- Current version: `0.1.2`
- Source path:
  - `/Users/khofmeyer/Development/MRN/mu-plugins/mrn-active-style-guide`

## Purpose

This plugin provides a logged-in-only front-end style guide for reviewing live site styling in the context of the active theme.

It is meant to help developers, designers, and internal editors inspect:

- live theme typography
- shared color tokens
- basic button and link treatments
- simple form styling
- component rhythm and spacing in real site context

It is not a replacement for Site Styles. Site Styles owns design tokens. Style Guide is the inspection and reference surface.

## Ownership Boundary

This plugin owns:

- the logged-in front-end quick panel
- the dedicated full style guide page
- style guide sample rendering for colors, typography, buttons, links, and form controls
- the route and access rules for the style guide page

This plugin does not own:

- the actual token registry
- site color definitions
- reusable block rendering
- builder field configuration

Those responsibilities live elsewhere, especially in:

- `mrn-site-colors` / `Site Styles`
- the stack theme
- `mrn-reusable-block-library`

## Admin Surface Area

The plugin adds:

- `Appearance -> Style Guide`
- a front-end admin bar entry labeled `Style Guide`

The wp-admin menu item is mainly a redirect entry. The real guide lives on the front end.

## Front-End Surface Area

The plugin exposes two main experiences:

1. A quick panel on the front end for logged-in users
2. A full dedicated style guide page rendered through a bundled template

Current routing behavior:

- front-end panel opens via `?mrn-style-guide=open`
- full page is available at the dedicated style guide route
- the plugin also supports `?mrn-style-guide-page=1`

Current default slug helper:

- `mrn_active_style_guide_slug()`
- returns `style-guide`

## Access Model

This plugin is intentionally logged-in only.

Current access rules:

- front-end panel only appears when `is_user_logged_in()`
- the dedicated guide page returns a 404 for non-logged-in visitors
- wp-admin menu access requires `edit_theme_options`

That means the guide is an internal review tool, not public site content.

## Dependency / Integration Notes

This plugin integrates with Site Styles when available.

Current integration:

- `mrn_active_style_guide_get_colors()`
- uses `mrn_site_colors_get_all()` when that function exists

So the color section of the guide automatically reflects the shared Site Styles token registry.

If Site Styles is empty or unavailable, the color section falls back to an informative empty-state message.

## Sample Content Areas

Current built-in sample areas include:

- color swatches
- typography samples
- button treatments
- inline link treatments
- light and dark surface blocks
- form control samples

The full page template lives at:

- `/Users/khofmeyer/Development/MRN/mu-plugins/mrn-active-style-guide/templates/style-guide-page.php`

That page is intentionally front-end oriented and uses the live theme header/footer context rather than a disconnected admin-only view.

## Rendering Model

The plugin renders its own lightweight CSS and JS inline rather than relying on a separate asset build.

Current render hooks:

- `admin_bar_menu`
- `admin_menu`
- `template_redirect`
- `template_include`
- `wp_footer`

This makes the plugin easy to move with the stack, but it also means front-end behavior is embedded directly in the MU plugin source.

## Developer-Facing Integration Surface

This plugin is more function-oriented than hook-oriented.

Useful internal functions include:

- `mrn_active_style_guide_is_available()`
- `mrn_active_style_guide_slug()`
- `mrn_active_style_guide_get_page_url()`
- `mrn_active_style_guide_get_panel_url()`
- `mrn_active_style_guide_is_page_request()`
- `mrn_active_style_guide_get_colors()`
- `mrn_active_style_guide_render_color_grid()`
- `mrn_active_style_guide_render_typography_samples()`
- `mrn_active_style_guide_render_button_samples()`
- `mrn_active_style_guide_render_form_samples()`

There is not currently a strong custom action/filter API for third-party extension.

If the team wants to make it more extensible later, likely candidates would be:

- section registration filters
- sample row filters for typography/buttons/forms
- a filter for the style guide slug or page title

## Intended Usage By Developers

Use this plugin when you need to:

- check whether the active theme is styling core elements consistently
- review token usage from Site Styles on a live site
- sanity-check button, link, and form treatments before building more templates
- compare surface/background combinations in real context

Do not use it as:

- the source of truth for color/token storage
- a page-builder feature
- a replacement for component documentation

## Theming Notes

This plugin is front-end adjacent, but it should not become the owner of theme styling rules.

The correct relationship is:

- theme and Site Styles define the real presentation system
- Style Guide reflects and demonstrates that system

If the guide starts inventing its own visual language instead of exposing the live one, it stops being useful.

## Rollout / Packaging Notes

- This is a stack MU plugin.
- It rolls out through stack MU source sync, not the normal plugin manifest.
- Durable memory records it as the preferred user-facing name `Style Guide`.
- Current baseline in memory:
  - `0.1.2`

## Risks / Gotchas

- Because it is front-end rendered, careless expansion can add too much UI or CSS noise to logged-in browsing sessions.
- It should stay an internal review utility, not a second design system.
- It currently depends on live theme output, which is a strength, but it also means broken theme CSS can make the guide look misleading.
- If Site Styles changes its helper contract later, the color section here should be updated to follow that contract cleanly.
