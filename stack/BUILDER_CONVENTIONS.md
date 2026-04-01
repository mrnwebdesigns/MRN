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
- Hero remains a separate contract from the body-section width system.
- Default hero layouts can start from the hero-shell classes:
  - `mrn-hero__content--hero-shell`
  - `mrn-hero__media--hero-shell`

### After Content

- `After Content` is a separate theme-owned field group that renders after the main `Content` builder on posts and pages.
- For now, `After Content` intentionally exposes the same layout set as `Content`.
- This is a placement distinction, not a new layout contract.
- Use `Content` for the main narrative body flow.
- Use `After Content` for sections that should land after the main body flow.

### Content Lists

- `Content Lists` is a theme-owned query layout for listing WordPress content inside the builder.
- It is the preferred theme layout when a page needs a post/query-driven list rather than hand-authored static rows.
- Current control set includes:
  - content type
  - list style
  - ordering and count
  - offset and pagination
  - excerpt and read-more display
  - empty-state message or full-row suppression
  - contextual or manual taxonomy filtering
- The first contextual filter contract is:
  - use the current page/post terms from a selected taxonomy to filter the queried content list
- Taxonomy and term controls should stay aligned with the selected content type so authors are not shown irrelevant filter options.

### Section Width

- Theme-owned layouts now use a shared `Section Width` setting where width matters visually.
- Current choices are:
  - `Content`
  - `Wide`
  - `Full Width`
- `Content` is for tighter reading-width sections.
- `Wide` is for centered sections that should breathe more than body copy.
- `Full Width` is for intentional edge-to-edge section treatments.
- `Image Content` still honors older saved `Full width` values as a legacy fallback, but the current contract should use `Section Width`.
- Theme-owned builder templates should resolve width classes, accent attributes, and inline style serialization through shared helper functions rather than rebuilding that wrapper logic per layout.
- This keeps shell behavior consistent and makes the builder easier to extend without layout-by-layout drift.

Width is expressed through wrapper classes added by the theme:

- `mrn-shell-section--width-content`
- `mrn-shell-section--width-wide`
- `mrn-shell-section--width-full`

Layout templates should not hardcode one-off max-width containers. Instead, they should rely on those shell classes and then add layout-specific internal structure so the difference between `Wide` and `Full Width` is visually meaningful.

### Width Family Rule

- Width behavior should be normalized by layout family, not by one-off template exceptions.
- The builder should follow a 4-layer wrapper model:
  - `Section`
  - `Container`
  - `Grid`
  - `Content`
- Responsibilities by layer:
  - `Section`
    - outer visual band
    - background and accent behavior
    - full-bleed vs contained shell behavior
  - `Container`
    - horizontal max-width and gutters
  - `Grid`
    - internal layout relationship between content pieces
  - `Content`
    - the actual payload blocks inside the grid
- Current family grouping:
  - text-led layouts:
    - `Text`
    - `External Widget`
  - media/content layouts:
    - `Basic`
    - `Image Content`
    - `Video`
    - `Slider`
  - collection/grid layouts:
    - `Card`
    - `Logos`
    - `Stats`
    - `Showcase`
  - reusable block layouts rendered through theme shell helpers:
    - direct `Reusable Block`
    - page-only `Basic Block`
    - page-only `CTA Block`
    - page-only `Content Grid`
    - page-only `FAQ Block`
- This 4-layer wrapper model is now the required default for all new theme layouts and all new reusable-block builder wrappers going forward.
- The shell owns the width contract.
- Each family should then use its own internal grid, padding, and media rules so:
  - `Content` reads tighter
  - `Wide` uses a larger contained working width
  - `Full Width` is behaviorally different because the section shell goes full bleed
- Current width-expression baseline for media/content families:
  - `Basic` and `Image Content` should keep `Content` single-column and readable
  - `Wide` should become a contained split composition rather than a full-bleed band
  - `Full Width` can let media/shell behavior go edge to edge, but readable text should stay constrained
  - layered `Wide` and `Full Width` variants must still collapse back to one column on small screens
  - when adding a new media/content family, start by reusing the shared layered media-stack shell classes:
    - `mrn-layout-grid--media-stack`
    - `mrn-layout-content--media-stack-media`
    - `mrn-layout-content--media-stack-text`
  - this shared starter contract now also applies to the layered `Reusable Basic` shell path
  - CTA rows now also have a reusable callout-inner contract for width expression:
    - `mrn-reusable-block__inner--callout`
    - `mrn-reusable-block__actions--callout`
  - collection/grid-style reusable layouts can now start from the collection-shell classes:
    - `mrn-content-grid--collection-shell`
    - `mrn-content-grid__items--collection-shell`
    - `mrn-content-grid__item--collection-shell`
  - FAQ rows now use an editorial-shell class so width modes can shift between constrained and split layouts without changing the accordion model:
    - `mrn-faq--editorial-shell`
  - image-led showcase/collage rows can start from the gallery-shell classes:
    - `mrn-showcase-row__grid--gallery-shell`
    - `mrn-showcase-row__item--gallery-shell`
  - stats/countup-style rows can start from the metrics-shell classes:
    - `mrn-stats-row__grid--metrics-shell`
    - `mrn-stats-row__item--metrics-shell`
  - logo-wall rows can start from the logo-wall classes:
    - `mrn-logos-row__grid--logo-wall`
    - `mrn-logos-row__item--logo-wall`
  - card collection rows can start from the card-deck classes:
    - `mrn-card-row__grid--card-deck`
    - `mrn-card-row__item--card-deck`
  - slider/carousel rows can start from the slider-shell classes:
    - `mrn-layout-grid--slider-shell`
    - `mrn-slider-row__header--slider-shell`
    - `mrn-slider-row__splide--slider-shell`
    - `mrn-slider-row__slide--slider-shell`
  - feature-video rows can start from the video-feature classes:
    - `mrn-layout-grid--video-feature`
    - `mrn-video-row__header--video-feature`
    - `mrn-video-row__media--video-feature`
  - embed/external-widget rows can start from the embed-shell classes:
    - `mrn-layout-grid--embed-shell`
    - `mrn-external-widget-row__content--embed-shell`
  - two-column composition rows can start from the split-shell classes:
    - `mrn-layout-grid--split-shell`
    - `mrn-two-column-split__column--split-shell`
  - editorial text rows can start from the text-shell classes:
    - `mrn-layout-grid--text-shell`
    - `mrn-layout-content--text-shell`
- In the base theme, `Content` and `Wide` can be subtly different.
- The fallback responsibility is structural first and visual second.
- The base theme should keep fallback styling minimal and readable, not over-design every width mode.
- Hero layouts are still a separate contract and should not be forced into the body-section width model just to match naming.

### Width Dropdown Meaning

- The existing `Section Width` dropdown should not try to fully style a layout by itself.
- In the 4-layer model it should primarily control `Section` and `Container`.
- Current intended meaning:
  - `Content`
    - contained section
    - content-width container
  - `Wide`
    - contained section
    - wide container
  - `Full Width`
    - full-bleed section
    - layout-owned inner container choice
- `Full Width` should not be interpreted as “all inner content stretches forever.”
- `Wide` should not paint its background like a full-bleed band.
- Only `Full Width` should get true edge-to-edge section/background behavior.
- A full-width section can still contain:
  - a wide inner container
  - a reading-width text wrapper
  - a full-bleed media track

### Reusable Block Width Rule

- Reusable block markup rendered inside the page/post builder should be wrapped in the same theme shell classes as native layouts.
- Reusable block wrapper helpers should assign reusable-block family section/container modifiers by reusable block post type, instead of each render path inventing its own wrapper.
- Reusable blocks should use the same layered width contract as native layouts:
  - `Content`
    - contained section
    - content-width container
  - `Wide`
    - contained section
    - wide container
  - `Full Width`
    - full-bleed section
    - reusable block gets a full-width container so its own background can go edge to edge
    - inner content remains the reusable block template's responsibility
- The direct `Reusable Block` builder layout now has its own `Section Width` control.
- Page-only reusable block clones should expose `Section Width` anywhere the visual shell matters, including:
  - `Basic Block`
  - `CTA Block`
  - `Content Grid`
  - `FAQ Block`

### QA Harness

- Local QA acceptance pages on the stack test site should cover every width-sensitive layout family and reusable block family.
- Current local QA page slugs include:
  - `qa-hero`
  - `qa-text-widths`
  - `qa-basic-widths`
  - `qa-image-content-widths`
  - `qa-card-widths`
  - `qa-logos-widths`
  - `qa-stats-widths`
  - `qa-showcase-widths`
  - `qa-slider-widths`
  - `qa-video-widths`
  - `qa-two-column-split-widths`
  - `qa-external-widget-widths`
  - `qa-reusable-basic-widths`
  - `qa-reusable-cta-widths`
  - `qa-reusable-grid-widths`
  - `qa-reusable-faq-widths`
  - `qa-after-content-widths`
- Current seeded reusable block fixtures for the reusable-block QA pages:
  - `qa-reusable-basic-block`
  - `qa-reusable-cta-block`
  - `qa-reusable-grid-block`
  - `qa-reusable-faq-block`

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

## QA Harness Pages (Local Only)

To make `Section Width` QA repeatable, the current workflow uses a set of Local-only QA pages (not production content).

- Each QA page repeats the same layout three times:
  - `Content`
  - `Wide`
  - `Full Width`
- Purpose:
  - verify the width classes render correctly
  - verify the layout *visually expresses* the difference between modes
  - verify mobile behavior, background colors, and accents

This supports the agreed architecture direction:

- treat width-field rendering as solved
- normalize layouts in batches by family
- rely on stable wrapper classes:
  - `mrn-shell-section--width-content`
  - `mrn-shell-section--width-wide`
  - `mrn-shell-section--width-full`

### Developer reference: layouts with width-mode CSS

**Front-end:** theme CSS under `stack/themes/mrn-base-stack/style.css` adds layout-specific rules scoped to those width classes so `Wide` and `Full Width` read clearly on QA pages. Stable inner shells include:

- `mrn-shell-section--text` (Body Text)
- `mrn-shell-section--basic`, `mrn-shell-section--image-content`, `mrn-shell-section--card`
- `mrn-shell-section--logos`, `mrn-shell-section--stats`, `mrn-shell-section--showcase`
- `mrn-shell-section--video`, `mrn-shell-section--external-widget`
- `mrn-shell-section--two-column-split`
- `mrn-shell-section--reusable-cta` — theme-owned **`CTA`**, **`CTA (Page Only)`**, and nested **`CTA`** inside **Two Column Split**. The flexible row adds `section_width`; `render.php` wraps the cloned reusable output in `.mrn-content-builder__row--cta` (or `--cta-block`) + this shell + width class. Inner markup remains `.mrn-reusable-block--cta`.
- `mrn-shell-section--reusable-grid` — theme-owned **`Grid`**, **`Content Grid (Page Only)`**, and nested **`Grid`** in Two Column Split. Same wrapping pattern; inner markup remains `.mrn-reusable-block--content-grid`.
- Slider uses `.mrn-slider-row__splide` inside `mrn-shell-section--slider` (width classes on the same shell as other layouts).

**Back-end / ACF:** the field remains `section_width` (plus legacy `Image Content` `full_width` fallback). No separate width vocabulary per layout. **CTA** and **Grid** (including page-only clones and nested column variants) now include **`Section Width`** on the flexible row alongside the cloned reusable fields.

**Front-end / PHP:** wrapping is implemented in `mrn_base_stack_wrap_cloned_reusable_builder_markup()` in `stack/themes/mrn-base-stack/inc/builder/render.php`. Do not duplicate that wrapper in templates.

**Not width-normalized in this pass:** layouts that still render a reusable template without this shell (e.g. **`Reusable Block`** picker row, **`Basic Block (Page Only)`**, **`FAQ`** / **`FAQs/Accordion (Page Only)`** until a future decision). Treat those separately in QA.
