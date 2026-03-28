<?php
/**
 * Builder row: Image content.
 *
 * @package mrn-base-stack
 */

$context          = is_array( $args ?? null ) ? $args : array();
$row              = isset( $context['row'] ) && is_array( $context['row'] ) ? $context['row'] : array();
$label            = isset( $row['label'] ) ? trim( (string) $row['label'] ) : '';
$heading          = isset( $row['text_field'] ) ? trim( (string) $row['text_field'] ) : '';
$heading_tag      = isset( $row['text_field_tag'] ) ? strtolower( (string) $row['text_field_tag'] ) : 'h2';
$content          = isset( $row['content'] ) ? (string) $row['content'] : '';
$image            = isset( $row['image'] ) && is_array( $row['image'] ) ? $row['image'] : array();
$background_color = isset( $row['background_color'] ) ? trim( (string) $row['background_color'] ) : '';
$bottom_accent    = ! empty( $row['bottom_accent'] );
$accent_slug      = isset( $row['bottom_accent_style'] ) ? (string) $row['bottom_accent_style'] : '';
$full_width       = ! empty( $row['full_width'] );
$image_position   = isset( $row['image_position'] ) ? sanitize_key( (string) $row['image_position'] ) : 'top';
$image_size       = isset( $row['image_size'] ) ? sanitize_key( (string) $row['image_size'] ) : 'contained';
$image_alignment  = isset( $row['image_alignment'] ) ? sanitize_key( (string) $row['image_alignment'] ) : 'center';

$allowed_tags = array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'span', 'div' );
if ( ! in_array( $heading_tag, $allowed_tags, true ) ) {
	$heading_tag = 'h2';
}

if ( ! in_array( $image_position, array( 'top', 'bottom' ), true ) ) {
	$image_position = 'top';
}

if ( ! in_array( $image_size, array( 'contained', 'cover' ), true ) ) {
	$image_size = 'contained';
}

if ( ! in_array( $image_alignment, array( 'left', 'center', 'right' ), true ) ) {
	$image_alignment = 'center';
}

$has_image = ! empty( $image['ID'] ) || ! empty( $image['url'] );
if ( '' === $label && '' === $heading && '' === trim( wp_strip_all_tags( $content ) ) && ! $has_image ) {
	return;
}

$section_classes = array(
	'mrn-content-builder__row',
	'mrn-content-builder__row--image-content',
	'mrn-content-builder__row--image-content-position-' . sanitize_html_class( $image_position ),
	'mrn-content-builder__row--image-content-size-' . sanitize_html_class( $image_size ),
	'mrn-content-builder__row--image-content-align-' . sanitize_html_class( $image_alignment ),
);

if ( $full_width ) {
	$section_classes[] = 'mrn-content-builder__row--image-content-full-width';
}

$section_styles = array();
if ( '' !== $background_color && function_exists( 'mrn_site_colors_get_css_var' ) ) {
	$section_styles[] = '--mrn-image-content-row-bg: var(' . mrn_site_colors_get_css_var( $background_color ) . ')';
}

$section_attrs   = array();
$accent_contract = function_exists( 'mrn_site_styles_get_bottom_accent_contract' )
	? mrn_site_styles_get_bottom_accent_contract( $bottom_accent, $accent_slug )
	: array(
		'classes'    => $bottom_accent ? array( 'has-bottom-accent' ) : array(),
		'attributes' => array(),
	);

if ( isset( $accent_contract['classes'] ) && is_array( $accent_contract['classes'] ) ) {
	$section_classes = array_merge( $section_classes, $accent_contract['classes'] );
}

if ( isset( $accent_contract['attributes'] ) && is_array( $accent_contract['attributes'] ) ) {
	$section_attrs = $accent_contract['attributes'];
}
?>
<section class="<?php echo esc_attr( implode( ' ', $section_classes ) ); ?>"<?php foreach ( $section_attrs as $attribute_name => $attribute_value ) : ?><?php if ( '' !== $attribute_name && '' !== $attribute_value ) : ?> <?php echo esc_attr( $attribute_name ); ?>="<?php echo esc_attr( $attribute_value ); ?>"<?php endif; ?><?php endforeach; ?><?php echo ! empty( $section_styles ) ? ' style="' . esc_attr( implode( '; ', $section_styles ) ) . '"' : ''; ?>>
	<div class="mrn-shell-section <?php echo $full_width ? 'mrn-shell-section--full-width' : 'mrn-shell-section--image-content'; ?>">
		<div class="mrn-image-content-row__inner">
			<?php if ( 'top' === $image_position && $has_image ) : ?>
				<div class="mrn-image-content-row__media">
					<?php if ( ! empty( $image['ID'] ) ) : ?>
						<?php echo wp_get_attachment_image( (int) $image['ID'], 'large' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php else : ?>
						<img src="<?php echo esc_url( $image['url'] ); ?>" alt="<?php echo esc_attr( $image['alt'] ?? '' ); ?>">
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<div class="mrn-image-content-row__content">
				<?php if ( '' !== $label ) : ?>
					<div class="mrn-image-content-row__label"><?php echo function_exists( 'mrn_base_stack_format_heading_inline_html' ) ? mrn_base_stack_format_heading_inline_html( $label ) : esc_html( $label ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
				<?php endif; ?>

				<?php if ( '' !== $heading ) : ?>
					<<?php echo esc_html( $heading_tag ); ?> class="mrn-image-content-row__heading"><?php echo function_exists( 'mrn_base_stack_format_heading_inline_html' ) ? mrn_base_stack_format_heading_inline_html( $heading ) : esc_html( $heading ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></<?php echo esc_html( $heading_tag ); ?>>
				<?php endif; ?>

				<?php if ( '' !== trim( $content ) ) : ?>
					<div class="mrn-image-content-row__text">
						<?php echo apply_filters( 'the_content', $content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
				<?php endif; ?>
			</div>

			<?php if ( 'bottom' === $image_position && $has_image ) : ?>
				<div class="mrn-image-content-row__media">
					<?php if ( ! empty( $image['ID'] ) ) : ?>
						<?php echo wp_get_attachment_image( (int) $image['ID'], 'large' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php else : ?>
						<img src="<?php echo esc_url( $image['url'] ); ?>" alt="<?php echo esc_attr( $image['alt'] ?? '' ); ?>">
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
	</div>
</section>
