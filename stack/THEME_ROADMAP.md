# Theme Roadmap

This document defines what the MRN stack theme is supposed to be for the development team.

Use it as the handoff reference for backend and frontend developers working from:

- the stack
- the active plugin and MU plugin set
- the Figma design/system files

## Core Direction

The stack theme is not a fresh `_s` theme on every rollout.

The stack theme is:

- `mrn-base-stack`
- a controlled MRN starter theme
- the canonical theme source for builder-aware page and post composition

Each site may still end up with a site-specific deployed theme identity, but the source of truth for stack theme behavior is:

- `/Users/khofmeyer/Development/MRN/stack/themes/mrn-base-stack`

## What The Theme Should Be

The goal is a robust MRN starter theme/framework, not a bare Underscores shell and not a one-off branded site theme.

The theme should:

- provide a dependable front-end framework for MRN sites
- feel complete enough that a dev team is not rebuilding the basics every time
- stay neutral enough that site-specific branding and styling can still be layered on intentionally
- work cleanly with the MRN builder, reusable blocks, and Site Styles token system

## What The Theme Should Not Be

The theme should not become:

- a dumping ground for site-specific styling
- a pseudo child-theme system
- a marketplace-style kitchen-sink theme
- a place where plugin-owned concerns are reimplemented

## Ownership Boundaries

### Theme Owns

- page and post composition
- theme-owned ACF builder layouts
- hero rendering
- front-end section/layout presentation
- template parts
- archive/single/search/404 template structure
- front-end CSS and JS needed to support the theme layouts

### Reusable Block Library Owns

- reusable block post types
- reusable block field groups
- reusable block editor/admin UX
- reusable block templates, with theme-aware rendering when needed

### Site Styles Owns

- shared site colors
- graphic elements
- accent definitions
- front-end design tokens meant to be reused across the site

### Config Helper Owns

- site configuration data
- social links and other site-level configuration values
- admin workflow helpers

## Current Theme Architecture

The theme builder is no longer meant to live as a monolithic `functions.php` implementation.

Current builder structure:

- `/Users/khofmeyer/Development/MRN/stack/themes/mrn-base-stack/functions.php`
- `/Users/khofmeyer/Development/MRN/stack/themes/mrn-base-stack/inc/builder/boot.php`
- `/Users/khofmeyer/Development/MRN/stack/themes/mrn-base-stack/inc/builder/admin.php`
- `/Users/khofmeyer/Development/MRN/stack/themes/mrn-base-stack/inc/builder/helpers.php`
- `/Users/khofmeyer/Development/MRN/stack/themes/mrn-base-stack/inc/builder/render.php`
- `/Users/khofmeyer/Development/MRN/stack/themes/mrn-base-stack/inc/builder/field-groups.php`

This is the pattern to continue. New theme builder work should extend that structure instead of pushing more system logic back into one giant file.

Theme-owned options now also live in:

- `/Users/khofmeyer/Development/MRN/stack/themes/mrn-base-stack/inc/theme-options.php`

Current theme-owned options model:

- `Theme Header/Footer`
  - theme-specific header/footer toggles and controls
- `Business Information`
  - shared business/contact/hours data used by theme areas like header and footer

The theme should prefer reading from those canonical helpers rather than duplicating site data in templates.

## Team Handoff Model

When the stack is handed to a development team, the expectation is:

- backend developers understand:
  - where builder layouts are registered
  - which concerns belong in theme vs plugin vs MU plugin
  - how rollout and site cloning work
- frontend developers understand:
  - the class and data-attribute contracts emitted by the builder
  - the Site Styles token rules
  - which layouts/components are standard in the stack
  - what they are expected to style vs what is owned elsewhere
- designers can hand over Figma files that map to a stable set of layouts, sections, tokens, and front-end rules

## The Base Theme Should Always Provide

These are the areas the base theme should cover well enough that a team is not rebuilding them from scratch on every site.

### 1. System Foundation

- container system
- spacing system
- typography defaults
- button and link styling primitives
- consistent image/media treatment
- consistent section wrappers
- responsive layout foundation

### 2. Template Coverage

- front page and page shell support
- single post shell
- archive templates
- search template
- 404 template
- basic navigation and pagination treatment
- header/footer starter structure

Current first-pass header contract:

- business information logo support with fallbacks
- primary menu location: `menu-1`
- utility menu location: `menu-2`
- theme-owned header toggles for:
  - utility menu
  - stack search area
  - business phone
  - business profile
- business phone/profile values sourced from `Business Information`
- header logo priority:
  - `Business Information` logo
  - WordPress custom logo
  - site title
- stack search integration expected through the theme hook:
  - `mrn_base_stack_header_search`
- current default implementation uses a SearchWP-friendly search form rather than the old starter-theme bare search pattern

Current business logo variants available in `Business Information`:

- `logo`
- `logo_inverted`
- `logo_footer`
- `logo_footer_inverted`

Theme helper for variant-aware consumption:

- `mrn_base_stack_get_business_logo( $context )`

Current business-information schema contract:

- the theme prints a JSON-LD business schema block in `wp_head`
- schema source is the canonical `Business Information` payload
- current schema enrichment can include:
  - business profile
  - logo
  - phone
  - text/SMS number as a contact point
  - address
  - weekday opening hours
  - social URLs from Config Helper as `sameAs`
- current schema helper source:
  - `mrn_base_stack_get_business_schema_data()`

QA note:

- the site may also have SEO-plugin-generated organization schema
- schema output should be checked for duplication/conflict during handoff QA

Current first-pass footer contract:

- footer menu location: `menu-3`
- legal menu location: `menu-4`
- theme-owned footer toggles currently live on `Theme Header/Footer`:
  - `footer_show_footer_menu`
  - `footer_show_legal_menu`
  - `footer_show_business_profile`
  - `footer_show_business_phone`
  - `footer_show_text_phone`
  - `footer_show_address`
  - `footer_show_business_hours`
  - `footer_show_social_links`
- theme-owned footer text fields:
  - `footer_copyright_text`
  - `footer_legal_text`
- footer data sources:
  - logo variants from `Business Information`
  - business/contact/address/hours from `Business Information`
  - social links from `Config Helper`
- current footer logo context uses:
  - `mrn_base_stack_get_business_logo( 'footer' )`

### 3. Builder Presentation

- clear front-end class contracts
- consistent section spacing behavior
- predictable row rendering
- good responsive behavior for all standard layouts
- support for shared accent and background token rules

### 4. Front-End Integration Layer

- Site Styles token consumption
- reusable block render compatibility
- media handling rules
- slider/video/background-media support where the stack layouts require it

## What Should Stay Neutral

The base theme should avoid locking in a specific site aesthetic.

Keep these neutral:

- brand voice
- brand colors beyond token defaults
- decorative motifs
- highly opinionated page chrome
- one-site-only component styles

The theme should feel like a strong framework, not like a finished branded website.

## Theme Versus Site Styling

Use this rule:

- if it is a system pattern likely to recur across many sites, it belongs in the base theme
- if it is a site-specific visual expression, it belongs in site styling, Site Styles configuration, or site-specific theme iteration

Examples of good base-theme concerns:

- consistent CTA spacing
- reasonable card/grid behavior
- default typography rhythm
- archive/post layout structure
- section wrapper logic

Examples of poor base-theme concerns:

- hardcoded brand treatments for one client
- niche component styling for one site only
- visual decisions that make every site feel the same

## Phase Roadmap

### Phase 1

Strengthen the system layer:

- formalize container and spacing utilities
- normalize button/link system
- improve typography defaults
- improve section/layout CSS consistency

### Phase 2

Strengthen template coverage:

- header/footer starter patterns
- archive/search/404 coverage
- pagination and empty-state patterns
- stronger single content shells

### Phase 3

Strengthen builder layout polish:

- responsive refinement for all standard layouts
- image/video/media consistency
- better default layout spacing and rhythm
- cleaner class contracts for front-end teams

### Phase 4

Strengthen handoff readiness:

- map Figma patterns to standard stack layouts
- document front-end expectations per layout
- document theme extension rules for the team
- reduce ambiguity about where custom work should live

## Practical Rule For New Work

Before adding a new theme feature, ask:

1. Is this a stack-level pattern or just one site’s design?
2. Does it belong in the theme, a normal plugin, an MU plugin, or Site Styles?
3. Will this make future handoff easier for backend and frontend developers?
4. Does this improve the starter framework without over-branding it?

If the answer is mostly yes, it is a good candidate for the base theme.
