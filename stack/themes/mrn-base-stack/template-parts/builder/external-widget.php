<?php
/**
 * Builder row: External Widget/iFrame.
 *
 * @package mrn-base-stack
 */

$context          = is_array( $args ?? null ) ? $args : array();
$row              = isset( $context['row'] ) && is_array( $context['row'] ) ? $context['row'] : array();
$embed_code       = isset( $row['embed_code'] ) ? trim( (string) $row['embed_code'] ) : '';
$background_color = isset( $row['background_color'] ) ? trim( (string) $row['background_color'] ) : '';
$bottom_accent    = ! empty( $row['bottom_accent'] );
$accent_slug      = isset( $row['bottom_accent_style'] ) ? (string) $row['bottom_accent_style'] : '';

if ( '' === $embed_code ) {
	return;
}

$section_classes = array(
	'mrn-content-builder__row',
	'mrn-content-builder__row--external-widget',
);
$section_attrs   = array();
$section_styles  = array();

if ( '' !== $background_color && function_exists( 'mrn_site_colors_get_css_var' ) ) {
	$section_styles[] = '--mrn-external-widget-row-bg: var(' . mrn_site_colors_get_css_var( $background_color ) . ')';
}

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
	<div class="mrn-shell-section mrn-shell-section--external-widget">
		<div class="mrn-external-widget-row__content">
			<?php echo do_shortcode( $embed_code ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
	</div>
</section>
