<?php
/**
 * Builder row: Showcase.
 *
 * @package mrn-base-stack
 */

$context          = is_array( $args ?? null ) ? $args : array();
$row              = isset( $context['row'] ) && is_array( $context['row'] ) ? $context['row'] : array();
$label            = isset( $row['label'] ) ? trim( (string) $row['label'] ) : '';
$heading          = isset( $row['heading'] ) ? trim( (string) $row['heading'] ) : '';
$heading_tag      = isset( $row['heading_tag'] ) ? strtolower( (string) $row['heading_tag'] ) : 'h2';
$items            = isset( $row['showcase_items'] ) && is_array( $row['showcase_items'] ) ? $row['showcase_items'] : array();
$hover_effect     = isset( $row['hover_effect'] ) ? sanitize_key( (string) $row['hover_effect'] ) : 'lift';
$stagger_style    = isset( $row['stagger_style'] ) ? sanitize_key( (string) $row['stagger_style'] ) : 'collage';
$background_color = isset( $row['background_color'] ) ? trim( (string) $row['background_color'] ) : '';
$bottom_accent    = ! empty( $row['bottom_accent'] );
$accent_slug      = isset( $row['bottom_accent_style'] ) ? (string) $row['bottom_accent_style'] : '';
$width_layers     = function_exists( 'mrn_base_stack_get_section_width_layers' )
	? mrn_base_stack_get_section_width_layers( $row['section_width'] ?? '', 'wide', 'wide' )
	: array(
		'width'           => 'wide',
		'section_class'   => 'mrn-layout-section--contained',
		'container_class' => 'mrn-layout-container--wide',
	);

$allowed_tags = array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'span', 'div' );
if ( ! in_array( $heading_tag, $allowed_tags, true ) ) {
	$heading_tag = 'h2';
}

if ( ! in_array( $hover_effect, array( 'lift', 'scale', 'none' ), true ) ) {
	$hover_effect = 'lift';
}

if ( ! in_array( $stagger_style, array( 'collage', 'stacked', 'flat' ), true ) ) {
	$stagger_style = 'collage';
}

$valid_items = array();
foreach ( $items as $item ) {
	if ( ! is_array( $item ) ) {
		continue;
	}

	$image = isset( $item['image'] ) && is_array( $item['image'] ) ? $item['image'] : array();
	$link  = isset( $item['link'] ) && is_array( $item['link'] ) ? $item['link'] : array();

	if ( empty( $image['ID'] ) && empty( $image['url'] ) ) {
		continue;
	}

	$valid_items[] = array(
		'image' => $image,
		'link'  => $link,
	);
}

if ( '' === $label && '' === $heading && empty( $valid_items ) ) {
	return;
}

$section_classes = array(
	'mrn-content-builder__row',
	'mrn-content-builder__row--showcase',
	'mrn-content-builder__row--showcase-hover-' . sanitize_html_class( $hover_effect ),
	'mrn-content-builder__row--showcase-stagger-' . sanitize_html_class( $stagger_style ),
);
$section_styles = array();

if ( '' !== $background_color && function_exists( 'mrn_site_colors_get_css_var' ) ) {
	$section_styles[] = '--mrn-showcase-row-bg: var(' . mrn_site_colors_get_css_var( $background_color ) . ')';
}

$accent_contract = function_exists( 'mrn_base_stack_get_builder_accent_contract' ) ? mrn_base_stack_get_builder_accent_contract( $bottom_accent, $accent_slug ) : array(
	'classes'    => $bottom_accent ? array( 'has-bottom-accent' ) : array(),
	'attributes' => array(),
);
$section_classes = function_exists( 'mrn_base_stack_merge_builder_section_classes' ) ? mrn_base_stack_merge_builder_section_classes( $section_classes, $accent_contract ) : $section_classes;
$section_attrs   = isset( $accent_contract['attributes'] ) && is_array( $accent_contract['attributes'] ) ? $accent_contract['attributes'] : array();
$section_attr_html = function_exists( 'mrn_base_stack_get_html_attributes' ) ? mrn_base_stack_get_html_attributes( $section_attrs ) : '';
$surface_style     = function_exists( 'mrn_base_stack_get_inline_style_attribute' ) ? mrn_base_stack_get_inline_style_attribute( $section_styles ) : implode( '; ', $section_styles );
$is_full_width     = 'full-width' === ( $width_layers['width'] ?? '' );
?>
<section class="<?php echo esc_attr( implode( ' ', $section_classes ) ); ?>"<?php echo '' !== $section_attr_html ? ' ' . $section_attr_html : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="mrn-layout-section mrn-layout-section--showcase <?php echo esc_attr( $width_layers['section_class'] ); ?><?php echo $is_full_width ? ' mrn-layout-surface' : ''; ?>"<?php echo $is_full_width && '' !== $surface_style ? ' style="' . esc_attr( $surface_style ) . '"' : ''; ?>>
		<div class="mrn-layout-container <?php echo esc_attr( $width_layers['container_class'] ); ?><?php echo ! $is_full_width ? ' mrn-layout-surface' : ''; ?>"<?php echo ! $is_full_width && '' !== $surface_style ? ' style="' . esc_attr( $surface_style ) . '"' : ''; ?>>
			<div class="mrn-layout-grid mrn-layout-grid--showcase">
		<?php if ( '' !== $label || '' !== $heading ) : ?>
			<header class="mrn-layout-content mrn-layout-content--text mrn-showcase-row__header">
				<?php if ( '' !== $label ) : ?>
					<div class="mrn-showcase-row__label"><?php echo function_exists( 'mrn_base_stack_format_heading_inline_html' ) ? mrn_base_stack_format_heading_inline_html( $label ) : esc_html( $label ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
				<?php endif; ?>
				<?php if ( '' !== $heading ) : ?>
					<<?php echo esc_html( $heading_tag ); ?> class="mrn-showcase-row__heading"><?php echo function_exists( 'mrn_base_stack_format_heading_inline_html' ) ? mrn_base_stack_format_heading_inline_html( $heading ) : esc_html( $heading ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></<?php echo esc_html( $heading_tag ); ?>>
				<?php endif; ?>
			</header>
		<?php endif; ?>

		<?php if ( ! empty( $valid_items ) ) : ?>
			<div class="mrn-showcase-row__grid mrn-showcase-row__grid--gallery-shell">
				<?php foreach ( $valid_items as $index => $item ) : ?>
					<?php
					$image  = $item['image'];
					$link   = $item['link'];
					$url    = isset( $link['url'] ) ? (string) $link['url'] : '';
					$target = isset( $link['target'] ) ? (string) $link['target'] : '';
					?>
					<figure class="mrn-showcase-row__item mrn-showcase-row__item--gallery-shell mrn-showcase-row__item--<?php echo esc_attr( (string) ( $index + 1 ) ); ?>">
						<?php if ( '' !== $url ) : ?>
							<a class="mrn-showcase-row__link" href="<?php echo esc_url( $url ); ?>"<?php if ( '' !== $target ) : ?> target="<?php echo esc_attr( $target ); ?>"<?php endif; ?><?php if ( '_blank' === $target ) : ?> rel="noopener noreferrer"<?php endif; ?>>
						<?php endif; ?>
						<?php if ( ! empty( $image['ID'] ) ) : ?>
							<?php echo wp_get_attachment_image( (int) $image['ID'], 'large' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php else : ?>
							<img src="<?php echo esc_url( $image['url'] ); ?>" alt="<?php echo esc_attr( $image['alt'] ?? '' ); ?>">
						<?php endif; ?>
						<?php if ( '' !== $url ) : ?>
							</a>
						<?php endif; ?>
					</figure>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
			</div>
		</div>
	</div>
</section>
