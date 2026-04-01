# Front-End Implementation Guide

## Purpose

This guide is the stack-level reference for front-end developers working in the MRN base theme.

Use it when you need to:

- pull shared site styles into PHP templates
- consume those styles in Sass/CSS
- render business information in header, footer, or custom template parts
- use the stack Motion `inView` integration for active-section behavior

## Source Of Truth

Theme source:

- `/Users/khofmeyer/Development/MRN/stack/themes/mrn-base-stack`

Business information helpers:

- `/Users/khofmeyer/Development/MRN/stack/themes/mrn-base-stack/inc/theme-options.php`
- `/Users/khofmeyer/Development/MRN/stack/themes/mrn-base-stack/inc/template-tags.php`

Site styles documentation:

- `/Users/khofmeyer/Development/MRN/stack/plugin-docs/mrn-site-styles.md`

## Motion InView In The Stack

The base theme now vendors Motion and exposes a small stack helper layer.

Files:

- `/Users/khofmeyer/Development/MRN/stack/themes/mrn-base-stack/js/vendor/motion.js`
- `/Users/khofmeyer/Development/MRN/stack/themes/mrn-base-stack/js/front-end-effects.js`
- `/Users/khofmeyer/Development/MRN/stack/themes/mrn-base-stack/functions.php`

Default behavior:

- Motion assets are enqueued on singular `post` and `page` requests.
- Raw Motion access is available at `window.Motion`.
- Stack convenience access is available at `window.mrnBaseStack.inView`.
- The theme helper `mrn_base_stack_enqueue_motion_assets()` can be used if a custom template needs Motion on another front-end request type.

Builder authoring behavior:

- Builder layouts now include a shared `Motion Effects` config group.
- Editors can enable motion per layout row instead of hand-authoring data attributes.
- The editor UI is intentionally preset-driven:
  - `Enable Row Effects`
  - `Effect Style`
  - `Start Effect`
  - `Surface Look` when relevant
  - `Effect Preset` when relevant
- Current supported effects are:
  - `Switch Light/Dark Surface`
  - `Mark Row As Active`
  - `Darken Card On Scroll`
- Effect-specific controls appear conditionally based on the selected effect.

The stack also includes a built-in active-surface pattern:

- add `data-mrn-surface="light"` or `data-mrn-surface="dark"` to a section
- optionally add `data-mrn-surface-margin="-35% 0px -35% 0px"` to tune when it becomes active
- when that section is active, `body[data-mrn-surface="..."]` is updated
- the active section also receives `.is-mrn-active-surface`

Example:

```php
<section
	class="mrn-basic-row"
	data-mrn-surface="<?php echo esc_attr( $surface_mode ); ?>"
	data-mrn-surface-margin="-30% 0px -30% 0px"
>
	...
</section>
```

Example CSS:

```css
body[data-mrn-surface="dark"] .site-header {
	background: #111;
	color: #fff;
}

.mrn-basic-row.is-mrn-active-surface {
	position: relative;
}
```

If you need custom behavior instead of the built-in data-attribute pattern:

```js
window.mrnBaseStack.inView( '.my-selector', function( element ) {
	element.classList.add( 'is-active' );

	return function() {
		element.classList.remove( 'is-active' );
	};
}, { margin: '-25% 0px -25% 0px' } );
```

Current builder output contract:

- `Switch Site Surface` renders:
- `Switch Light/Dark Surface` renders:
  - `data-mrn-surface="light|dark"`
  - `data-mrn-surface-margin="..."`
- `Mark Row As Active` renders:
  - class: `mrn-motion-effect--active-class`
  - `data-mrn-motion-effect="active-class"`
  - `data-mrn-motion-class="is-mrn-in-view"`
  - `data-mrn-motion-margin="..."`
- `Darken Card On Scroll` renders:
  - class: `mrn-motion-effect--dark-scroll-card`
  - `data-mrn-motion-effect="dark-scroll-card"`
  - `data-mrn-effect-preset="slug"` when a Site Styles preset is chosen
  - `data-mrn-motion-margin="..."`

Class and attribute reference:

- `mrn-motion-effect--active-class`
  - marker class for rows using the active-class effect
  - gives front-enders a stable styling hook for effect-specific CSS
- `mrn-motion-effect--dark-scroll-card`
  - marker class for rows using the scroll-darkening card treatment
  - intended to pair with Motion-driven inline style updates on the row surface and child content
- `data-mrn-surface`
  - tells the runtime to update `body[data-mrn-surface]` while the row is active
- `data-mrn-surface-margin`
  - controls the in-view threshold used for the surface effect
- `data-mrn-motion-effect`
  - declares which runtime behavior should run for that row
- `data-mrn-motion-class`
  - stable class added and removed while the row is active for the `active-class` effect
  - current builder-controlled value is `is-mrn-in-view`
- `data-mrn-motion-margin`
  - in-view threshold for non-surface effects
- `data-mrn-effect-preset`
  - names the Site Styles preset that should skin the effect
  - currently used by `Darken Card On Scroll`

## Site Styles Effect Presets

The first Site Styles-backed motion family is `Darken Card On Scroll`.

- Site Styles owns the preset definitions.
- The theme owns activation timing and Motion runtime behavior.
- The builder only stores which preset the row should use.

Current helper:

- `mrn_site_styles_get_dark_scroll_card_preset_choices()`

Current front-end contract:

- Site Styles prints CSS custom properties for rows matching:
  - `[data-mrn-motion-effect="dark-scroll-card"][data-mrn-effect-preset="your-preset"]`
- The Motion runtime reads those custom properties and animates toward them while the row scrolls into the active range.

Current custom properties emitted by Site Styles:

- `--mrn-dark-scroll-card-bg-rgb`
- `--mrn-dark-scroll-card-text-rgb`
- `--mrn-dark-scroll-card-muted-rgb`
- `--mrn-dark-scroll-card-button-bg-rgb`
- `--mrn-dark-scroll-card-button-text-rgb`
- `--mrn-dark-scroll-card-border-alpha`
- `--mrn-dark-scroll-card-shadow-alpha`
- `--mrn-dark-scroll-card-image-brightness`
- `--mrn-dark-scroll-card-image-saturation`

## Site Styles In PHP Templates

Use the Site Styles plugin as the source of truth for shared color tokens.

Preferred helper:

- `mrn_site_colors_get_css_var( $slug )`

Preferred pattern:

1. Read the configured slug from ACF or block data.
2. Convert that slug to the shared CSS variable name in PHP.
3. Assign a local component variable on the wrapper.
4. Consume the local variable in Sass/CSS.

Example:

```php
<?php
$section_styles = array();

if ( '' !== $background_color && function_exists( 'mrn_site_colors_get_css_var' ) ) {
	$section_styles[] = '--mrn-basic-row-bg: var(' . mrn_site_colors_get_css_var( $background_color ) . ')';
}

if ( '' !== $link_color && function_exists( 'mrn_site_colors_get_css_var' ) ) {
	$section_styles[] = '--mrn-basic-row-link-color: var(' . mrn_site_colors_get_css_var( $link_color ) . ')';
}
?>

<section
	class="mrn-basic-row"
	<?php if ( ! empty( $section_styles ) ) : ?>
		style="<?php echo esc_attr( implode( '; ', $section_styles ) ); ?>"
	<?php endif; ?>
>
	...
</section>
```

Why this pattern is preferred:

- the Site Styles plugin owns the site-wide token registry
- templates stay decoupled from raw hex values
- components can define their own local variable names without redefining the shared token system

## Site Styles In Sass/CSS

Once PHP sets the component-scoped variable, use that variable in Sass/CSS.

Example:

```scss
.mrn-basic-row {
	background: var(--mrn-basic-row-bg, transparent);
}

.mrn-basic-row a {
	color: var(--mrn-basic-row-link-color, currentColor);
}
```

Recommended rules:

- prefer `var(--mrn-component-token, fallback)` instead of hardcoded colors
- treat `--site-color-*` as the global registry and `--mrn-*` as local component aliases
- do not hardcode duplicate token names in theme CSS when Site Styles already owns the value

## Business Information In PHP Templates

Use the theme-owned helpers instead of calling ACF fields directly inside templates.

Primary helpers:

- `mrn_base_stack_get_business_information()`
- `mrn_base_stack_get_business_logo( $context )`
- `mrn_base_stack_get_business_address_lines()`
- `mrn_base_stack_get_business_hours_display_rows()`

Preferred pattern:

1. Fetch the business payload once near the top of the template.
2. Use the specialized helper for logos, address lines, and hours rows.
3. Escape at output time.

Example:

```php
<?php
$business_information = function_exists( 'mrn_base_stack_get_business_information' ) ? mrn_base_stack_get_business_information() : array();
$business_logo        = function_exists( 'mrn_base_stack_get_business_logo' ) ? mrn_base_stack_get_business_logo( 'footer' ) : null;
$address_lines        = function_exists( 'mrn_base_stack_get_business_address_lines' ) ? mrn_base_stack_get_business_address_lines() : array();
?>

<?php if ( ! empty( $business_logo['ID'] ) ) : ?>
	<a class="mrn-site-logo-link" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
		<?php echo wp_get_attachment_image( (int) $business_logo['ID'], 'full', false, array( 'class' => 'mrn-site-logo', 'alt' => get_bloginfo( 'name' ) ) ); ?>
	</a>
<?php endif; ?>

<?php if ( ! empty( $business_information['phone'] ) && ! empty( $business_information['phone_uri'] ) ) : ?>
	<a href="<?php echo esc_url( $business_information['phone_uri'] ); ?>">
		<?php echo esc_html( $business_information['phone'] ); ?>
	</a>
<?php endif; ?>

<?php if ( ! empty( $address_lines ) ) : ?>
	<address>
		<?php foreach ( $address_lines as $address_line ) : ?>
			<div><?php echo esc_html( $address_line ); ?></div>
		<?php endforeach; ?>
	</address>
<?php endif; ?>
```

Why this pattern is preferred:

- theme templates consume one canonical payload shape
- formatting logic stays centralized
- header, footer, schema, and custom templates stay aligned

## Business Information In Sass/CSS

Business information is still rendered as HTML first. Sass/CSS should style stable classes and semantic markup, not re-create the data model.

Recommended approach:

- give business-information areas component classes such as `.mrn-site-footer__contact`
- keep addresses in `<address>`
- keep phones and SMS links as anchors
- style the wrapper and rows, not the raw ACF field names

Example:

```scss
.mrn-site-footer__contact {
	display: grid;
	gap: 0.75rem;
}

.mrn-site-footer__address {
	font-style: normal;
}

.mrn-site-footer__hours-row {
	display: flex;
	justify-content: space-between;
	gap: 1rem;
}
```

## Singular Sidebar Shell

The base theme now supports a builder-driven singular sidebar shell for posts and pages.

Current authoring model:

- Editors control the shell with the theme-owned `Sidebar` field group.
- `Sidebar Layout` decides whether the singular shell stays single-column or becomes a two-column layout.
- Sidebar content is authored with flexible rows stored in `page_sidebar_rows`.
- The sidebar uses the same layout set as the main `Content` builder, so reusable content should still flow through the existing `Reusable Block` layout.
- The stack does not use classic WordPress widgets for this feature.

Current front-end class contract:

- `mrn-singular-shell`
  - base wrapper around the singular content area
- `mrn-singular-shell--page`
  - page-specific shell marker
- `mrn-singular-shell--post`
  - post-specific shell marker
- `mrn-singular-shell--has-sidebar`
  - added only when a sidebar is enabled and there is sidebar markup to render
- `mrn-singular-shell--sidebar-right`
  - right-sidebar modifier
- `mrn-singular-shell--sidebar-left`
  - left-sidebar modifier
- `mrn-singular-shell__main`
  - main narrative column
- `mrn-singular-shell__sidebar`
  - sidebar column wrapper
- `mrn-singular-sidebar`
  - sidebar content wrapper
- `mrn-content-builder--sidebar`
  - builder wrapper for sidebar rows

Behavior rules:

- The main singular title, featured image, `Content`, and `After Content` flow stays in `mrn-singular-shell__main`.
- Sidebar rows render only when `Sidebar Layout` is not `No Sidebar` and `page_sidebar_rows` contains valid rows.
- Small-screen behavior should collapse back to a single column.
- Sidebar styling should treat the area as a compact companion column, not as a second full-bleed page canvas.
- Sidebar layouts should prefer contained internal spacing and should avoid assuming full-width media behavior.

Example shell:

```php
<?php
$sidebar_settings = function_exists( 'mrn_base_stack_get_singular_sidebar_settings' ) ? mrn_base_stack_get_singular_sidebar_settings( get_the_ID() ) : array( 'layout' => 'none' );
$sidebar_markup   = function_exists( 'mrn_base_stack_get_singular_sidebar_markup' ) ? mrn_base_stack_get_singular_sidebar_markup( get_the_ID() ) : '';
$has_sidebar      = 'none' !== ( $sidebar_settings['layout'] ?? 'none' ) && '' !== $sidebar_markup;
?>

<div class="mrn-singular-shell <?php echo $has_sidebar ? 'mrn-singular-shell--has-sidebar mrn-singular-shell--sidebar-right' : ''; ?>">
	<div class="mrn-singular-shell__main">
		...
	</div>

	<?php if ( $has_sidebar ) : ?>
		<div class="mrn-singular-shell__sidebar">
			<?php echo $sidebar_markup; ?>
		</div>
	<?php endif; ?>
</div>
```

Example CSS direction:

```css
.mrn-singular-shell--has-sidebar {
	display: grid;
	grid-template-columns: minmax(0, 1fr) minmax(18rem, 22rem);
	gap: clamp(2rem, 4vw, 4rem);
}

.mrn-content-builder--sidebar {
	gap: 1.5rem;
}

@media (max-width: 782px) {
	.mrn-singular-shell--has-sidebar {
		grid-template-columns: 1fr;
	}
}
```

## Implementation Rules

- Prefer theme helper functions over direct `get_field()` calls in templates.
- Prefer Site Styles tokens over hardcoded shared colors.
- Prefer local component CSS variables derived from shared site tokens.
- Escape output at render time.
- Keep front-end behavior declarative when possible using data attributes and CSS classes.
- Use Motion for viewport-state behavior; do not bolt on one-off scroll scripts per template if the shared helper can handle it.

## Good Reference Files

- `/Users/khofmeyer/Development/MRN/stack/themes/mrn-base-stack/header.php`
- `/Users/khofmeyer/Development/MRN/stack/themes/mrn-base-stack/footer.php`
- `/Users/khofmeyer/Development/MRN/stack/themes/mrn-base-stack/inc/singular-sidebar.php`
- `/Users/khofmeyer/Development/MRN/stack/themes/mrn-base-stack/template-parts/builder/basic.php`
- `/Users/khofmeyer/Development/MRN/stack/themes/mrn-base-stack/template-parts/builder/showcase.php`
- `/Users/khofmeyer/Development/MRN/stack/themes/mrn-base-stack/template-parts/builder/image-content.php`
- `/Users/khofmeyer/Development/MRN/stack/themes/mrn-base-stack/template-parts/content-page.php`
- `/Users/khofmeyer/Development/MRN/stack/themes/mrn-base-stack/template-parts/content.php`
