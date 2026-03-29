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
- For background images on section-style layouts:
  - `background_image`
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

### Hero

- Current hero layouts:
  - `Basic - label|heading|text with editor|link|image`
  - `Two Column Split`
- Hero layouts are theme-owned and render above the main `Content` builder.
- Hero currently supports:
  - `Background color`
  - `Background image`
  - `Background video`
  - accent controls when the specific hero layout exposes them

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
  - Background image
  - Accent

### CTA

- Label
- Title field
- HTML tag for text field
- Text area with editor
- Primary Link
- Secondary Link
- Configs:
  - Link style
  - Link color
  - Background color
  - Background image
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

### Logos

- Label
- Heading
- HTML tag for heading
- Logos repeater:
  - Image
  - Link
- Configs:
  - Display mode
  - Logos per row/view
  - Show arrows
  - Show pagination
  - Autoplay
  - Pause on hover
  - Delay start
  - Delay time
  - Time on slide
  - Background color
  - Accent

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

### Video

- Label
- Title field
- HTML tag for text field
- Text area with editor
- Remote video URL
- Video upload
- Configs:
  - Background color
  - Accent

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
- Use the theme-owned `Slider - label|title|slides` layout when slides need:
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

## Logos Rule

- Use `Logos - label|heading|image|link` for logo rails, partner lists, or trust/association sections.
- This layout intentionally supports two display modes:
  - `Grid`
  - `Slider`
- Use `Grid` for static logo clouds like sponsor or partner sections.
- Use `Slider` when the logo count is large or motion is preferred.

### Logo Control Meanings

- `Display mode`
  - Switches between static grid rendering and Splide slider rendering
- `Logos per row/view`
  - In grid mode, controls the intended column count on large screens
  - In slider mode, controls visible logos per view on large screens
- `Show arrows`
  - Slider mode only
- `Show pagination`
  - Slider mode only
- `Autoplay`
  - Slider mode only
- `Pause on hover`
  - Slider mode only
- `Delay start`
  - Slider mode only
- `Delay time`
  - Slider mode only
- `Time on slide`
  - Slider mode only

### Logo Front-End Contract

Front-enders should rely on these hooks:

- wrapper classes:
  - `mrn-content-builder__row--logos`
  - `mrn-content-builder__row--logos-grid`
  - `mrn-content-builder__row--logos-slider`
- content hooks:
  - `.mrn-logos-row__label`
  - `.mrn-logos-row__heading`
  - `.mrn-logos-row__grid`
  - `.mrn-logos-row__item`
  - `.mrn-logos-row__link`
- slider hook when in slider mode:
  - `.mrn-logos-row__splide`
  - `.mrn-splide`

### Logo Token And Styling Rules

- Background color should come from Site Styles color choices.
- Accent should use the shared bottom accent contract.
- Logo items stay intentionally simple:
  - image
  - optional link
- This layout does not add per-logo presentation controls beyond the chosen display mode.

## Video Rule

- Use `Video - remote|upload` for a normal foreground video section, not for arbitrary snippet embeds.
- This is a theme-owned page/post layout.
- It reuses the stack’s deferred video-loading path, but it is not a background-video layout.

### Video Source Contract

- remote field:
  - `video_remote`
- local upload field:
  - `video_upload`
- precedence:
  - if both are set, `video_upload` wins over `video_remote`

### Video Rendering Rule

- Remote URLs support:
  - YouTube
  - Vimeo
- Uploaded files support:
  - `mp4`
  - `webm`
  - `mov`
- This layout renders video as normal foreground media inside the content section.
- Unlike hero background video, it does not autoplay by default.

### Video Front-End Contract

Front-enders should rely on:

- wrapper classes:
  - `mrn-content-builder__row--video`
- content hooks:
  - `.mrn-video-row__label`
  - `.mrn-video-row__heading`
  - `.mrn-video-row__text`
  - `.mrn-video-row__media`
- deferred media frame:
  - `.mrn-deferred-media__frame`

### Video Performance Rule

- Video layout reuses the deferred-loading path:
  - wait until the section is in or near view
  - then inject the real iframe or `<video>` element
- Current defaults:
  - no autoplay
  - no loop
  - controls enabled
  - uploaded local files use `preload=\"metadata\"`

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

## Background Image Rule

Background images are intentionally limited to the strong single-section layouts:

- Hero
- Basic
- CTA

These are the current “strong yes” layouts for background-image support because they read as one narrative section and do not compete with collection-style content models.

### Field Contract

- field name:
  - `background_image`
- current behavior:
  - the selected image is rendered as a CSS background image on the outer section container
  - the section still keeps its background color token as a fallback/base layer

### Front-End Contract

Front-enders should rely on:

- shared class:
  - `has-background-image`
- section-specific CSS variables:
  - Hero:
    - `--mrn-hero-bg-image`
  - Basic:
    - `--mrn-basic-row-bg-image`
  - CTA:
    - `--mrn-cta-bg-image`

### Current Rendering Defaults

The current stack theme applies these defaults when a background image is present:

- `background-position: center`
- `background-repeat: no-repeat`
- `background-size: cover`

### Color + Image Layering Rule

- Keep using `Background color` even when a background image is set.
- Treat the background color token as the fallback/base layer.
- Treat the background image as presentation on top of that token-driven base.
- Do not invent separate overlay fields unless a future thread establishes a more advanced background-image system.

## Hero Background Video Rule

Background video is intentionally limited to the current hero layouts only:

- `Basic - label|heading|text with editor|link|image`
- `Two Column Split`

This is not a generic section/video system. It is currently a hero-only presentation feature.

### Field Contract

- remote field:
  - `background_video`
- local upload field:
  - `background_video_upload`
- remote supported providers:
  - YouTube
  - Vimeo
- local supported formats:
  - `mp4`
  - `webm`
  - `mov`
- precedence:
  - if both are set, `background_video_upload` wins over `background_video`

### Rendering Rule

- Background video is decorative.
- It renders as a deferred normalized background `<iframe>` embed behind the hero content.
- Local uploaded video renders as a deferred background `<video>` element behind the hero content.
- It should not be treated as editorial foreground media.
- Hero content remains the semantic/interactive layer above the video.

### Front-End Contract

Front-enders should rely on:

- shared class:
  - `has-background-video`
- shared media class:
  - `.mrn-section-background-media`
- injected iframe class:
  - `.mrn-section-background-media__frame`
- hero-specific hooks:
  - `.mrn-hero__background-media`
  - `.mrn-hero__inner`
- two-column hero hooks:
  - `.mrn-two-column-split__background-media`
  - `.mrn-two-column-split`

### Current Rendering Defaults

- normalized provider embed URL for remote sources
- autoplaying, muted, looping provider params
- local uploads render with:
  - `autoplay`
  - `muted`
  - `loop`
  - `playsinline`
  - `preload="none"`
- absolute full-bleed positioning inside the hero section
- image-first behavior:
  - background image remains visible on initial paint
  - background media is not inserted immediately
- current performance guards:
  - wait until the hero is near/in view
  - defer iframe insertion by about `2000ms`
  - skip on `prefers-reduced-motion: reduce`
  - skip on small screens by default
  - skip when `Save-Data` is enabled

### Layering Rule

- Background color remains the base layer.
- Background image remains an optional image layer.
- Background video sits as the decorative moving media layer behind the hero content.
- The content wrapper is positioned above the video so links and editor content remain readable and interactive.

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
- FAQs/Accordion

The hidden-layout filtering in admin JS must only affect popup menu items, not full builder row DOM.

## FAQs/Accordion Rule

Do not create a second accordion content system when the FAQ model already fits the behavior.

Use the shared pattern:

- reusable block name:
  - `FAQs/Accordion`
- visible content layout:
  - `FAQs/Accordion - label|title|items`

Current field treatment:

- `Label`
- `Title field`
- `HTML tag for text field`
- `Items` repeater
  - `Question / Heading`
  - `Answer / Text`

Current configs that make sense for this pattern:

- `Background color`
- `First Item Open`
- `Accent`

This keeps FAQ and collapsible/accordion content under one rendering model instead of splitting them into separate near-duplicate systems.

## Stats Layout Rule

Use a dedicated stats layout when the content pattern is:

- repeated metrics
- large values
- short supporting labels
- simple repeated presentation

Current visible layout:

- `Stats - label|heading|items`

Current field treatment:

- `Label`
- `Heading`
- `HTML tag for heading`
- `Items`
  - `Stat`
  - `Label`

Current configs:

- `Columns`
- `Show dividers`
- `Background color`
- `Accent`

Do not force this into `Grid` just because both use repeaters. `Stats` is a specific content pattern with a clearer front-end contract.

## Showcase Layout Rule

Use `Showcase` for screenshot-driven collage sections with hover interaction.

Current visible layout:

- `Showcase - label|heading|image|link`

Current field treatment:

- `Label`
- `Heading`
- `HTML tag for heading`
- `Items`
  - `Image`
  - `Link`

Current configs:

- `Hover effect`
- `Stagger style`
- `Background color`
- `Accent`

This is not a carousel, slider, or gallery. It is a fixed repeated showcase composition for featured screenshots/work examples.

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
  - includes `Background color` as a standard section-level config
  - should still not add extra presentation controls to the `Reusable Block` wrapper used inside the columns
  - current allowed nested layout set:
    - `Text - label|title|text with editor`
    - `Basic - label|title|text with editor|image|link`
    - `Card - image|text|link`
    - `CTA - label|title|text with editor|link`
    - `Grid - label|title|repeater`
    - `Image - label|title|text with editor`
    - `Video - remote|upload`
    - `Logos - label|heading|image|link`
    - `External - widget/iFrame`
    - `Reusable Block`

Avoid turning nested columns into full multi-row mini-builders unless there is a strong reason.
Do not add recursive split-inside-split layouts unless a future thread makes that an explicit decision.

## Documentation Habit

When a durable content-model rule changes:

1. update this file
2. update `/Users/khofmeyer/Development/MRN/THREAD_MEMORY.md`
3. if it affects release expectations, update stack release notes too
