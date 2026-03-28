# Builder Conventions

This document explains how the MRN stack models page content, reusable content, and shared presentation rules.

## Core Model

- The theme owns page and post composition.
- Reusable blocks are shared content primitives managed in the reusable block library.
- Use a theme ACF layout when content belongs only to the current page or post.
- Use a reusable block when content should be edited once and reused in multiple places.
- Converting a reusable block into page-specific content is supported, but that is an editor action, not the default authoring model.

## Ownership

- Theme builder source:
  - `/Users/khofmeyer/Development/MRN/stack/themes/mrn-base-stack/functions.php`
  - `/Users/khofmeyer/Development/MRN/stack/themes/mrn-base-stack/template-parts/builder/`
- Reusable block source:
  - `/Users/khofmeyer/Development/MRN/mu-plugins/mrn-reusable-block-library/mrn-reusable-block-library.php`
  - `/Users/khofmeyer/Development/MRN/mu-plugins/mrn-reusable-block-library/templates/`
- Shared style token source:
  - `/Users/khofmeyer/Development/MRN/mu-plugins/mrn-site-colors/mrn-site-colors.php`

### Theme Ownership Rule

- The stack theme is `mrn-base-stack`.
- It is based on Underscores, but the stack does not pull fresh `_s` on each rollout.
- The stack rolls out a controlled packaged MRN starter theme, then bootstrap clones/renames it into the site-specific theme.
- The theme owns:
  - page/post composition
  - builder-aware rendering
  - builder template parts
  - hero rendering
  - front-end presentation of builder rows
- The reusable block library owns:
  - reusable block post types
  - reusable block field groups
  - reusable block admin UX
  - reusable block render templates, with theme override support when needed

### No Child Theme Assumption

- The current stack baseline does not assume a parent-theme plus child-theme model for builder work.
- Shared stack behavior should live in:
  - the stack theme
  - MU plugins
  - normal plugins
- Do not assume a separate child theme layer exists for content-model features unless a future thread establishes one.

## Field Naming Patterns

Use consistent names whenever possible.

- For section headings:
  - `label`
  - `text_field`
  - `text_field_tag`
- For rich body copy:
  - `content`
- For section links:
  - `link`
- For background token selection:
  - `background_color` in theme layouts
  - `bg_color` in reusable blocks where that field name already exists
- For accent controls:
  - `bottom_accent`
  - `bottom_accent_style`
- For link presentation:
  - `link_style`
  - `link_color`

When a new layout or reusable block can follow an existing field contract, prefer cloning or mirroring the existing shape instead of inventing a new one.

## Site Styles And Front-End Token Rule

`Site Styles` is the shared design-token source of truth for stack-driven front-end work.

### What belongs in Site Styles

- shared site colors
- graphic elements
- accent definitions
- other future cross-site design tokens that should be managed centrally

### Front-End Rule

When front-end code needs a shared color token, it should come from Site Styles rather than hardcoded ad hoc values.

That means front-end/theme work should prefer:

- CSS variables emitted by Site Styles
- background/color selectors populated from Site Styles choices
- accent selectors populated from Site Styles graphic elements

Do not treat Site Styles as only an admin settings page. It is the token registry for front-end presentation too.

### Color Variable Contract

Site Styles emits shared color variables using the pattern:

- `--site-color-{slug}`

Front-end/theme code should reference those variables instead of inventing duplicate token names when a shared site color is intended.

### Graphic Element Contract

Graphic elements are stored in Site Styles with:

- `name`
- `slug`
- raw `CSS`
- optional spacing override

Those graphic elements feed accent dropdowns and render through the shared accent contract.

## Current Standard Layout Patterns

These are the current preferred content patterns in the stack.

### Text

- Label
- Title field
- HTML tag for text field
- Text area with editor
- Configs:
  - Background color
  - Accent

### Basic

- Label
- Title field
- HTML tag for text field
- Text area with editor
- Image
- Link
- Configs:
  - Link style
  - Link color
  - Image placement
  - Background color
  - Accent

### CTA

- Label
- Title field
- HTML tag for text field
- Text area with editor
- Link
- Configs:
  - Link style
  - Link color
  - Background color
  - Accent

### Grid

- Label
- Title field
- HTML tag for text field
- Repeater items:
  - Label
  - Title field
  - HTML tag for title field
  - Text area with editor
  - Link
- Configs:
  - Link style
  - Link color
  - Background color
  - Accent

### Slider

- Label
- Title field
- HTML tag for text field
- Slides repeater:
  - Image
  - Label
  - Title field
  - HTML tag for title field
  - Text area with editor
  - Link
- Configs:
  - Link style
  - Link color
  - Background color
  - Accent
  - Slides per view
  - Show arrows
  - Show pagination
  - Autoplay
  - Pause on hover
  - Delay start
  - Delay time
  - Time on slide

### Image

- Label
- Title field
- HTML tag for text field
- Text area with editor
- Image
- Configs:
  - Background color
  - Accent
  - Full width
  - Image position
  - Image size
  - Image alignment

### Image Layout Rule

- Use `Image - label|title|text with editor` when the section is primarily a single image paired with optional heading and body copy.
- This is a theme-owned page/post layout, not a reusable block by default.
- The image field is intentionally first in the content fields because the image is the primary content object for this layout.

### Image Control Meanings

- `Full width`
  - Expands the section shell so the image layout can span the full available width
- `Image position`
  - Controls whether the image renders before or after the text content
  - Current values:
    - `Top`
    - `Bottom`
- `Image size`
  - Controls how the image should fill its media area
  - Current values:
    - `Contained`
    - `Cover`
- `Image alignment`
  - Controls the horizontal alignment of the image inside its media area
  - Current values:
    - `Left`
    - `Center`
    - `Right`

### Image Front-End Contract

Front-enders should rely on these hooks:

- wrapper classes:
  - `mrn-content-builder__row--image-content`
  - `mrn-content-builder__row--image-content-position-top`
  - `mrn-content-builder__row--image-content-position-bottom`
  - `mrn-content-builder__row--image-content-size-contained`
  - `mrn-content-builder__row--image-content-size-cover`
  - `mrn-content-builder__row--image-content-align-left`
  - `mrn-content-builder__row--image-content-align-center`
  - `mrn-content-builder__row--image-content-align-right`
  - `mrn-content-builder__row--image-content-full-width`
- content hooks:
  - `.mrn-image-content-row__label`
  - `.mrn-image-content-row__heading`
  - `.mrn-image-content-row__text`
  - `.mrn-image-content-row__media`

### Image Token And Styling Rules

- Background color should come from Site Styles color choices.
- Accent should use the shared bottom accent contract.
- Heading-style fields use the shared limited inline HTML contract.

### External - widget/iFrame

- Snippet/Code
- Configs:
  - Background color
  - Accent

### External Embed Rule

- Use `External - widget/iFrame` when the content is a trusted third-party embed, widget snippet, or iframe that should render directly in the page layout.
- This layout is theme-owned and page/post-specific. It is not a reusable block by default.
- The snippet/code field is intentionally raw embed markup, not a structured ACF subfield set.
- The same shared presentation rules still apply:
  - background color comes from Site Styles color choices
  - accent uses the shared bottom accent contract

## Slider Rule

- Phase 1 sliders should stay structured, not fully generic.
- The current stack slider uses `Splide` and a slide repeater rather than nested layouts inside each slide.
- Use the theme-owned `Slider - repeater` layout when slides need:
  - image
  - label
  - title
  - rich text
  - link
- Do not jump to “any layout inside any slide” unless a future thread makes that an intentional phase 2 decision.

### Slider Framework

- Current slider framework: `Splide`
- Current pinned version in the stack theme: `4.1.4`
- Vendored asset paths:
  - `/Users/khofmeyer/Development/MRN/stack/themes/mrn-base-stack/js/vendor/splide.min.js`
  - `/Users/khofmeyer/Development/MRN/stack/themes/mrn-base-stack/css/vendor/splide.min.css`

### Slider Control Meanings

- `Slides per view`
  - Number of slides visible at once on larger screens
  - Current supported values:
    - `1`
    - `2`
    - `3`
- `Show arrows`
  - Toggles previous/next controls
- `Show pagination`
  - Toggles slider dots/pagination
- `Autoplay`
  - Enables automatic slide advancement
- `Pause on hover`
  - When enabled, autoplay pauses while the pointer is over the slider
- `Delay start`
  - Seconds to wait before autoplay begins
- `Delay time`
  - Seconds each slide remains visible during autoplay
- `Time on slide`
  - Transition speed in milliseconds

### Slider Front-End Contract

Front-enders should treat the slider as a structured section with these main hooks:

- wrapper classes:
  - `mrn-content-builder__row--slider`
  - `mrn-content-builder__row--slider-link-link`
  - `mrn-content-builder__row--slider-link-button`
- slider root:
  - `.mrn-slider-row__splide`
- slide items:
  - `.mrn-slider-row__slide`
- slide content hooks:
  - `.mrn-slider-row__slide-label`
  - `.mrn-slider-row__slide-heading`
  - `.mrn-slider-row__slide-text`
  - `.mrn-slider-row__slide-link`

### Slider Data Attributes

The slider root currently carries:

- `data-per-page`
- `data-arrows`
- `data-pagination`
- `data-pause-on-hover`
- `data-autoplay`
- `data-delay-start`
- `data-delay-time`
- `data-time-on-slide`

These are the JS initialization contract for the current phase-1 slider.

### Slider Token And Styling Rules

- Background color should come from Site Styles color choices.
- Link color should come from Site Styles color choices.
- Accent should use the shared bottom accent contract.
- Link presentation follows the same rule as other layouts:
  - destination stays in the slide `link` field
  - visual treatment is controlled at the section level by `link_style` and `link_color`

## Heading Markup Rule

Heading-style text fields intentionally support a limited inline HTML subset.

Allowed tags:

- `span`
- `strong`
- `em`
- `br`

This is for controlled inline heading styling, not full rich-text editing.

### Help Text Rule

Heading-style fields should include this help text:

`Limited inline HTML allowed: span, strong, em, br.`

### Rendering Rule

Do not render heading-style fields with plain `esc_html()` if the field is intended to support the inline heading contract.

Use:

- `mrn_base_stack_format_heading_inline_html()`

Defined in:

- `/Users/khofmeyer/Development/MRN/stack/themes/mrn-base-stack/functions.php`

Reusable templates may call the helper when it exists, since the helper is theme-owned.

## Link Presentation Rule

Link data and link presentation are separate concerns.

- The actual destination should be stored in a `link` field.
- Presentation should usually be configured at the section/block level with:
  - `link_style`
  - `link_color`

### How it works

- The link remains an anchor element.
- Style is changed by CSS class.
- Color is usually passed through a CSS variable on the wrapper.

Typical pattern:

- wrapper class indicates style mode
  - example: `...--link-link`
  - example: `...--link-button`
- wrapper style exposes the chosen color token
  - example: `--mrn-cta-link-color: var(--site-color-brand-primary)`

This lets one layout-level decision style all links inside that section consistently.

### Data vs Presentation Rule

- Store destination data in a `link` field.
- Do not invent separate “button URL” vs “link URL” fields when one destination concept is enough.
- A “button” in this system is usually still an anchor element styled with a button class.
- Presentation should generally be decided by section-level config, not repeated item-by-item unless there is a clear need.

## Accent Rule

Bottom accents are a shared stack concept across theme layouts and reusable blocks.

### Field contract

- `bottom_accent`
- `bottom_accent_style`

### Render contract

- class: `has-bottom-accent`
- data attribute: `data-bottom-accent="slug"`

### Style source

Accent definitions come from Site Styles graphic elements.

### Base implementation

- Site Styles prints the shared base CSS contract.
- Individual accent visuals are implemented with CSS, typically using `::after`.
- The default spacing uses a shared margin contract and can be overridden per accent with `Space Override`.

### Wrapper Rule

Do not double-wrap accents.

- If a reusable block already owns its own accent contract, a parent `Reusable Block` picker row in the theme should not add another accent wrapper around it.
- Accent support should be added only to layouts/blocks that render their own outer section markup.

## Reusable Block Picker Rule

The page/post builder reusable block selector should show published reusable blocks only.

Do not show:

- draft reusable blocks
- private reusable blocks

## Conversion Rule

Hidden page-only conversion targets exist for current reusable types, but they should not appear in the normal Add Content Row picker.

Current conversion targets:

- CTA
- Basic Block
- Content Grid
- FAQ

The hidden-layout filtering in admin JS must only affect popup menu items, not full builder row DOM.

### Direct Picker Rule

Hidden page-only conversion targets exist for conversion only.

- Do not offer them in the normal Add Content Row menu.
- Do not use them as the primary authoring path.
- Visible layouts and reusable blocks should stay clean and human-readable in the picker.

## Builder UI Rule

Use ACF defaults wherever possible.

Prefer:

- better field naming
- tabs
- grouping
- native ACF filters

Avoid:

- heavy custom admin CSS for cosmetic reasons
- deep one-off selector hacks unless they solve a repeated usability problem

### Admin UX Priority Rule

The team preference is:

- clean naming
- clear grouping
- predictable tabs
- useful collapsed titles

before:

- pixel-perfect custom admin styling

If a UX issue can be solved with better field architecture instead of CSS, prefer the architectural fix.

## Collapsed Title Rule

Use ACF's native flexible content title filter where possible:

- `acf/fields/flexible_content/layout_title`

Prefer meaningful collapsed titles such as:

- `Reusable Block: Frog`
- `Basic: Welcome to Our Practice`
- `CTA: Book a Visit`
- `Grid: Services`

Avoid building a heavy JS layer for this unless the ACF-native path is insufficient.

## Nested Builder Rule

Nested builder patterns are allowed, but should stay constrained.

Current approved advanced pattern:

- `Two Column Split`
  - one nested layout in the left column
  - one nested layout in the right column

Avoid turning nested columns into full multi-row mini-builders unless there is a strong reason.

## Documentation Habit

When a durable content-model rule changes:

1. update this file
2. update `/Users/khofmeyer/Development/MRN/THREAD_MEMORY.md`
3. if it affects release expectations, update stack release notes too
