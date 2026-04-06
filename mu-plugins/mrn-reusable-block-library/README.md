# MRN Reusable Block Library

`mrn-reusable-block-library` is a reusable content system for MRN sites.

It can be used without `mrn-base-stack`, but it does not bring the full `mrn-base-stack` front-end layout system with it. The plugin owns the reusable block content models and rendering entry points. The active theme owns the final presentation.

## What The Plugin Owns

- reusable block post types and ACF field groups
- reusable block render context and template lookup
- default plugin templates for:
  - `mrn_reusable_basic`
  - `mrn_reusable_cta`
  - `mrn_reusable_faq`
  - `mrn_reusable_grid`
- a fallback generic template
- shortcode rendering via `[mrn_block id="123"]` or `[mrn_block slug="my-block"]`

## What The Plugin Does Not Own

- the `Section Width` shell system used by `mrn-base-stack`
- site-wide spacing, type, color, surface, or layout tokens
- the polished family-level CSS that lives in `mrn-base-stack`
- builder wrappers for posts/pages outside the reusable block itself

If you activate this plugin under another theme, the blocks will still render, but they will only look “finished” if that theme provides compatible markup styling.

## Template Override Path

The plugin prefers theme overrides before falling back to plugin templates.

Override path:

```text
wp-content/themes/{active-theme}/mrn-blocks/{template-slug}.php
```

Current template slugs:

- `basic-block`
- `cta`
- `faq`
- `content-grid`
- `generic-block`

The plugin lookup is implemented in `mrn_rbl_locate_template()` in [mrn-reusable-block-library.php](/Users/khofmeyer/Development/MRN/mu-plugins/mrn-reusable-block-library/mrn-reusable-block-library.php).

## Minimum Theme Integration

To use this plugin outside `mrn-base-stack`, a receiving theme should do at least these things:

1. Add CSS for the base plugin block classes:
   - `.mrn-reusable-block`
   - `.mrn-reusable-block--basic`
   - `.mrn-reusable-block--cta`
   - `.mrn-reusable-block--faq`
   - `.mrn-reusable-block--content-grid`
2. Decide whether to use the plugin templates as-is or override them in `mrn-blocks/`.
3. Provide styling for the shared family classes used by the current templates if you want behavior close to `mrn-base-stack`.

## Shared Family Classes Used By Current Templates

These classes come from the theme-side layout family work. Another theme does not have to use the same CSS, but if it does not style these classes, the rendered output will be much more bare.

### Basic Block

- `mrn-layout-grid--media-stack`
- `mrn-layout-content--media-stack-media`
- `mrn-layout-content--media-stack-text`

### CTA

- `mrn-reusable-block__inner--callout`
- `mrn-reusable-block__actions--callout`

### Content Grid

- `mrn-content-grid--collection-shell`
- `mrn-content-grid__items--collection-shell`
- `mrn-content-grid__item--collection-shell`

### FAQ

- `mrn-faq--editorial-shell`

## Integration Options

### Option 1: Use The Plugin With `mrn-base-stack`

This is the easiest path. The plugin templates and the theme CSS are already aligned.

### Option 2: Use The Plugin With Another Theme And Keep Plugin Templates

Do this if you want the plugin to keep rendering its own markup, but you are willing to style the emitted classes in the receiving theme.

This is usually the fastest path for adoption.

### Option 3: Use The Plugin With Another Theme And Override Templates

Do this if the receiving theme has its own component system and you want tighter control over markup.

In that case:

- copy the needed plugin template into `wp-content/themes/{active-theme}/mrn-blocks/`
- adapt the markup to the receiving theme
- keep the plugin field names and render context intact

## Render Context Available In Templates

Plugin templates receive a `$context` array. Common keys include:

- `$context['post']`
- `$context['post_id']`
- `$context['post_name']`
- `$context['fields']`
- `$context['block_name']`

See the header comments in the templates inside [templates](/Users/khofmeyer/Development/MRN/mu-plugins/mrn-reusable-block-library/templates).

## Practical Recommendation

If you hand this plugin to another developer:

- tell them the plugin is portable
- tell them the polished visual system is theme-owned
- point them to this README first
- then have them choose between:
  - styling the existing plugin markup in their theme
  - overriding the plugin templates in `mrn-blocks/`

If they want the quickest route to parity with the MRN stack, they should recreate the matching theme-side CSS contracts in their theme rather than editing plugin internals.
