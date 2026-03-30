# Developer Handoff

This document is the curated developer handoff for the MRN WordPress stack.

Use it when handing the stack to:

- backend developers
- frontend developers
- project leads translating Figma into implementation work

This is the best single-source handoff document to move into Google Docs. The source-controlled detailed references still live in:

- `/Users/khofmeyer/Development/MRN/stack/BUILDER_CONVENTIONS.md`
- `/Users/khofmeyer/Development/MRN/stack/THEME_ROADMAP.md`
- `/Users/khofmeyer/Development/MRN/stack/THEME_TASKLIST.md`
- `/Users/khofmeyer/Development/MRN/stack/STACK_OPERATIONS.md`
- `/Users/khofmeyer/Development/MRN/stack/PLUGIN_CATALOG.md`
- `/Users/khofmeyer/Development/MRN/stack/plugin-docs/`

## Stack Summary

The MRN stack is a controlled WordPress starter platform for new sites.

It includes:

- a canonical MRN starter theme
- a reusable content system
- shared design-token management
- admin/editor enhancement plugins
- stack bootstrap and rollout tooling
- source-controlled documentation of the architecture and conventions

The goal is not to hand a team a bare WordPress install. The goal is to hand a team:

- a stable content-model foundation
- a stronger starter theme/framework
- clear ownership boundaries
- a predictable rollout model

## Canonical Source Layout

Workspace root:

- `/Users/khofmeyer/Development/MRN`

Important directories:

- `plugins/`
  - canonical standard plugin source
- `mu-plugins/`
  - canonical MU plugin source
- `stack/`
  - stack orchestration, manifests, docs, scripts, theme source, stack loader files
- `stack/themes/mrn-base-stack/`
  - canonical stack theme source
- `releases/`
  - build artifacts only, not source of truth

## Ownership Model

### Theme Owns

- page/post composition
- theme-owned ACF layouts
- hero rendering
- header/footer rendering
- front-end layout presentation
- archive/single/search/404 template structure
- theme CSS/JS

### Reusable Block Library Owns

- reusable block post types
- reusable block field groups
- reusable block admin/editor UX
- reusable block templates

### Site Styles Owns

- shared site colors
- accent/graphic elements
- front-end design tokens

### Config Helper Owns

- site-wide configuration
- social links
- admin workflow helpers
- certain cross-plugin settings sync behavior

## Theme Direction

The stack theme is:

- `mrn-base-stack`
- a controlled MRN starter theme
- the canonical source for theme behavior across sites

The stack does not pull a fresh `_s` theme on each rollout.

Each site may still end up with its own deployed theme identity, but the source of truth remains:

- `/Users/khofmeyer/Development/MRN/stack/themes/mrn-base-stack`

### Theme Goals

- complete enough that a team is not rebuilding the basics every time
- neutral enough that sites can still get distinct front-end treatment
- strong enough to work as the theme framework for builder-driven sites

### Page Shell Direction

- singular page/post templates should use a centered container system rather than a permanently boxed page shell
- the overall page flow should stay full width
- most content should sit inside centered max-width containers
- selected sections can intentionally go wide or full bleed when the layout calls for it
- mobile spacing should come from shared shell gutters instead of one-off per-template padding
- theme-owned layouts should prefer the shared `Section Width` contract:
  - `Content`
  - `Wide`
  - `Full Width`

### Theme Architecture

The builder/theme system should not grow as one giant `functions.php`.

Current builder split:

- `inc/builder/boot.php`
- `inc/builder/admin.php`
- `inc/builder/helpers.php`
- `inc/builder/render.php`
- `inc/builder/field-groups.php`
- `inc/theme-options.php`

## Content Model

### Core Rule

- use a theme layout when content belongs only to the current page/post
- use a reusable block when content should be shared and edited once

### Theme-Owned Builder

The universal content-builder experience for `post` and `page` lives in the theme.

Current standard layouts include:

- `Text - label|title|text with editor`
- `Basic - label|title|text with editor|image|link`
- `CTA - label|title|text with editor|link`
- `Grid - label|title|repeater`
- `Card - image|text|link`
- `Slider - label|title|slides`
- `Image - label|title|text with editor`
- `Video - remote|upload`
- `Logos - label|heading|image|link`
- `Stats - label|heading|items`
- `Showcase - label|heading|image|link`
- `FAQs/Accordion - label|title|items`
- `External - widget/iFrame`
- `Two Column Split`

Theme-owned builder templates should use the shared helper layer for section width resolution, accent attributes, and inline style serialization so wrapper behavior stays consistent across layouts.

Width-mode QA note:

- `Section Width` (`Content`, `Wide`, `Full Width`) is treated as a solved field/rendering contract.
- The ongoing theme work is to ensure layouts *visually express* the width modes (through internal grids/wrappers), using the seeded local QA pages as the acceptance harness.
- Canonical developer detail for which layouts have width-scoped CSS and how QA works: `/Users/khofmeyer/Development/MRN/stack/BUILDER_CONVENTIONS.md` (sections **Section Width** and **QA Harness Pages**).
- As of the current theme pass, width-mode visual normalization includes: Body Text, Basic, Image Content, Card, Logos, Stats, Showcase, Slider, External, Video, Two Column Split, plus **CTA** and **Grid** (including **Content Grid (Page Only)** / **CTA (Page Only)** and the same layouts nested in **Two Column Split** via a reusable wrapper + `section_width` on the flexible row). See `BUILDER_CONVENTIONS.md` for shells (`mrn-shell-section--reusable-cta`, `mrn-shell-section--reusable-grid`) and for layouts still outside this pattern (e.g. **Reusable Block** picker).

Hero is a separate field group above Content and currently supports:

- `Basic - label|heading|text with editor|link|image`
- `Two Column Split`

After Content is a separate field group that renders after the main Content builder.

- For now, it intentionally exposes the same layout set as `Content`.
- Treat it as a placement bucket, not as a separate layout system.

### Reusable Blocks

Reusable blocks are centrally managed content patterns such as:

- Basic
- CTA
- Grid
- FAQs/Accordion

The theme can convert supported reusable blocks into page-specific content, but those page-only conversion targets are intentionally hidden from the standard ACF picker.

## Header And Footer Contract

### Theme-Owned Options

Theme-owned options live in:

- `/Users/khofmeyer/Development/MRN/stack/themes/mrn-base-stack/inc/theme-options.php`

Current options pages:

- `Theme Header/Footer`
- `Business Information`

These are source-controlled theme options and should not live only in a site database.

### Business Information

This is the canonical site/business data source for theme use.

Current contract includes:

- `business_profile`
- `years_in_business`
- business logo variants:
  - `logo`
  - `logo_inverted`
  - `logo_footer`
  - `logo_footer_inverted`
- contact fields:
  - `phone`
  - `text_phone`
- address fields:
  - `address_line_1`
  - `address_line_2`
  - `address_city`
  - `address_state`
  - `address_postal_code`
  - `address_country`
- weekday business hours:
  - Monday through Friday
  - each with `Open` and `Close`
- holiday hours repeater:
  - `name`
  - `date`
  - `status`
  - `open`
  - `close`
  - `note`

Theme helpers:

- `mrn_base_stack_get_business_information()`
- `mrn_base_stack_get_business_logo( $context )`
- `mrn_base_stack_get_business_schema_data()`

### Phone-Field Rule

ACF does not have a native phone field in this stack.

Current business phone fields are source-controlled `text` fields upgraded with:

- `type="tel"` admin behavior
- live formatting while typing
- 10-digit US-style input limiting
- validation on save
- theme helpers returning:
  - display-formatted values
  - normalized `tel:` URIs

### Header

Current menu locations:

- `menu-1` = Primary
- `menu-2` = Utility

Current header option toggles:

- `header_show_utility_menu`
- `header_show_search`
- `header_show_business_phone`
- `header_show_business_profile`

Header logo priority:

1. `Business Information` logo
2. WordPress custom logo
3. site title

Header search contract:

- theme hook: `mrn_base_stack_header_search`
- current default implementation is a SearchWP-friendly search form
- this is intentionally not the old starter-theme bare search fallback

### Footer

Current menu locations:

- `menu-3` = Footer
- `menu-4` = Legal

Current footer option toggles:

- `footer_show_footer_menu`
- `footer_show_legal_menu`
- `footer_show_business_profile`
- `footer_show_business_phone`
- `footer_show_text_phone`
- `footer_show_address`
- `footer_show_business_hours`
- `footer_show_social_links`

Current footer text fields:

- `footer_copyright_text`
- `footer_legal_text`

Footer data sources:

- logo variants from `Business Information`
- business/contact/address/hours from `Business Information`
- social links from `Config Helper`

Footer helpers:

- `mrn_base_stack_get_business_address_lines()`
- `mrn_base_stack_get_business_hours_display_rows()`
- `mrn_base_stack_get_footer_copyright_text()`
- `mrn_base_stack_render_social_links()`

## Site Styles And Tokens

`Site Styles` is the shared front-end token source of truth.

Use it for:

- site colors
- accent elements
- graphic elements

Front-end code should prefer Site Styles tokens instead of inventing duplicate values.

Current shared color variable contract:

- `--site-color-{slug}`

Accent contract:

- class: `has-bottom-accent`
- data attribute: `data-bottom-accent="slug"`

Graphic elements are managed in Site Styles and then referenced by theme/reusable layouts through that shared contract.

## Social Links

Social links live in `Config Helper`, not in theme options.

Current public helper:

- `mrn_config_helper_get_social_links()`

Current social row contract:

- `icon_type`
- `icon_id`
- `icon_url`
- `fa_style`
- `fa_name`
- `fa_class`
- `url`

Front-end renderers must branch on `icon_type`:

- `media`
- `fontawesome`

The theme already has a starter social renderer for footer use.

## Shared Assets

Font Awesome is now a shared stack asset, not conceptually owned by `Editor Enhancements`.

Canonical owner:

- `/Users/khofmeyer/Development/MRN/mu-plugins/mrn-shared-assets`

Current shared helpers:

- `mrn_shared_assets_fontawesome_css_url()`
- `mrn_shared_assets_enqueue_fontawesome()`
- `mrn_shared_assets_get_fontawesome_icons()`

If front-end code needs Font Awesome because configured social icons use it, the theme should enqueue the shared asset intentionally.

## Business Schema

The theme currently prints a JSON-LD business schema block in `wp_head`.

Current schema source:

- `Business Information`
- social URLs from `Config Helper`

Current enrichment can include:

- business profile
- logo
- phone
- text/SMS contact point
- address
- weekday opening hours
- social `sameAs` links

Current helper/output:

- `mrn_base_stack_get_business_schema_data()`
- script id: `mrn-business-schema`

Important QA note:

- SmartCrawl Pro may also output organization schema
- schema duplication/conflict should be reviewed during site QA

## Search

Current search stack:

- SearchWP
- SearchWP Live Ajax Search

The header now has a SearchWP-friendly baseline implementation, but this area is still a valid place for future refinement if the team wants a stronger branded/modal search experience.

## Plugin Set

The stack includes a set of standard plugins and MU plugins.

Important plugin roles:

- `mrn-config-helper`
  - site configuration, social links, admin helpers
- `mrn-editor-tools`
  - classic editor + ACF WYSIWYG enhancements
- `mrn-reusable-block-library`
  - reusable content primitives
- `mrn-site-colors` / Site Styles
  - site tokens and accent/graphic definitions
- `mrn-shared-assets`
  - shared runtime assets like Font Awesome
- `mrn-active-style-guide`
  - logged-in front-end style guide reference

Use the detailed docs in `plugin-docs/` for plugin-specific behavior.

## Local Development

The preferred local workflow uses symlinks from the test site back to canonical source.

The local active stack theme slug should symlink to:

- `/Users/khofmeyer/Development/MRN/stack/themes/mrn-base-stack`

Do not treat copied local site theme/plugin folders as source of truth.

## Rollout Model

The stack theme is rolled out as a controlled packaged MRN starter theme.

It is not a fresh `_s` pull and not a raw wp.org theme slug install.

Key rollout rule:

- define option pages and field groups in canonical source
- do not leave theme/business/header/footer ACF only in a site database

If a site should receive the same fields during bootstrap, those fields must be source-controlled in the stack.

## What Front-End Developers Should Rely On

- theme/layout class contracts emitted by the builder
- Site Styles tokens
- source-controlled header/footer/business information helpers
- SearchWP-friendly header search baseline
- reusable block data coming through the theme/reusable rendering system

Do not assume:

- ad hoc values stored only in a local site DB
- theme templates should reach directly into plugin internals when a wrapper helper already exists
- one-site styling belongs in the base theme

## What Backend Developers Should Rely On

- canonical source in the workspace repo
- builder registration in the split theme builder files
- theme-owned options in `inc/theme-options.php`
- reusable block models in the reusable block library
- stack docs as the source-controlled implementation guide

## Handoff QA Checklist

- menus assigned to the intended theme locations
- logo fallback order works as expected
- header/footer toggles behave correctly
- business information renders correctly
- social icons render correctly for both media and Font Awesome sources
- SearchWP header search works on the site
- schema output reviewed for duplication/conflict
- standard layouts tested responsively
- accent/background behavior tested where used

## Related Docs

- Theme direction:
  - `/Users/khofmeyer/Development/MRN/stack/THEME_ROADMAP.md`
- Theme task breakdown:
  - `/Users/khofmeyer/Development/MRN/stack/THEME_TASKLIST.md`
- Builder rules:
  - `/Users/khofmeyer/Development/MRN/stack/BUILDER_CONVENTIONS.md`
- Stack operations:
  - `/Users/khofmeyer/Development/MRN/stack/STACK_OPERATIONS.md`
- Plugin inventory:
  - `/Users/khofmeyer/Development/MRN/stack/PLUGIN_CATALOG.md`
