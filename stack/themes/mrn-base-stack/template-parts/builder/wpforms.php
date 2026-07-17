<?php
/**
 * Builder row: WPForms.
 *
 * @package mrn-base-stack
 */

$context          = is_array( $args ?? null ) ? $args : array();
$row              = isset( $context['row'] ) && is_array( $context['row'] ) ? $context['row'] : array();
$label            = isset( $row['label'] ) ? trim( (string) $row['label'] ) : '';
$label_tag        = function_exists( 'mrn_base_stack_normalize_text_tag' ) ? mrn_base_stack_normalize_text_tag( $row['label_tag'] ?? '', 'p' ) : 'p';
$heading          = isset( $row['heading'] ) ? trim( (string) $row['heading'] ) : '';
$heading_tag      = function_exists( 'mrn_base_stack_normalize_text_tag' ) ? mrn_base_stack_normalize_text_tag( $row['heading_tag'] ?? '', 'h2' ) : 'h2';
$subheading       = isset( $row['subheading'] ) ? trim( (string) $row['subheading'] ) : '';
$subheading_tag   = function_exists( 'mrn_base_stack_normalize_text_tag' ) ? mrn_base_stack_normalize_text_tag( $row['subheading_tag'] ?? '', 'p' ) : 'p';
$intro            = isset( $row['intro'] ) ? (string) $row['intro'] : '';
$form_layout      = isset( $row['form_layout'] ) ? sanitize_key( (string) $row['form_layout'] ) : 'stacked';
$background_color = isset( $row['background_color'] ) ? trim( (string) $row['background_color'] ) : '';
$bottom_accent    = ! empty( $row['bottom_accent'] );
$accent_slug      = isset( $row['bottom_accent_style'] ) ? (string) $row['bottom_accent_style'] : '';
$width_layers     = function_exists( 'mrn_base_stack_get_section_width_layers' )
	? mrn_base_stack_get_section_width_layers( $row['section_width'] ?? '', 'content', 'full-width' )
	: array(
		'width'           => 'content',
		'section_class'   => 'mrn-layout-section--contained',
		'container_class' => 'mrn-layout-container--content',
	);
$form             = isset( $row['form'] ) ? $row['form'] : null;
$form_id          = 0;

if ( $form instanceof WP_Post ) {
	$form_id = (int) $form->ID;
} elseif ( is_numeric( $form ) ) {
	$form_id = (int) $form;
}

if ( ! in_array( $form_layout, array( 'stacked', 'form-right', 'form-left' ), true ) ) {
	$form_layout = 'stacked';
}

if ( $form_id <= 0 || ! shortcode_exists( 'wpforms' ) ) {
	return;
}

$section_classes  = array(
	'mrn-content-builder__row',
	'mrn-content-builder__row--wpforms',
	'mrn-content-builder__row--wpforms-layout-' . sanitize_html_class( $form_layout ),
);
$section_styles   = array();
$display_contract = function_exists( 'mrn_base_stack_get_builder_display_contract' ) ? mrn_base_stack_get_builder_display_contract( $row, 'wpforms' ) : array(
	'classes'    => array(),
	'attributes' => array(),
);
$accent_contract  = function_exists( 'mrn_base_stack_get_builder_accent_contract' ) ? mrn_base_stack_get_builder_accent_contract( $bottom_accent, $accent_slug ) : array(
	'classes'    => $bottom_accent ? array( 'has-bottom-accent' ) : array(),
	'attributes' => array(),
);
$motion_contract  = function_exists( 'mrn_base_stack_get_builder_motion_contract' ) ? mrn_base_stack_get_builder_motion_contract( $row ) : array(
	'classes'    => array(),
	'attributes' => array(),
);
$section_classes  = function_exists( 'mrn_base_stack_merge_builder_section_classes' ) ? mrn_base_stack_merge_builder_section_classes( $section_classes, $display_contract ) : $section_classes;
$section_classes  = function_exists( 'mrn_base_stack_merge_builder_section_classes' ) ? mrn_base_stack_merge_builder_section_classes( $section_classes, $accent_contract ) : $section_classes;
$section_classes  = function_exists( 'mrn_base_stack_merge_builder_section_classes' ) ? mrn_base_stack_merge_builder_section_classes( $section_classes, $motion_contract ) : $section_classes;
$section_attrs    = isset( $display_contract['attributes'] ) && is_array( $display_contract['attributes'] ) ? $display_contract['attributes'] : array();
$section_attrs    = function_exists( 'mrn_base_stack_merge_builder_attributes' ) ? mrn_base_stack_merge_builder_attributes( $section_attrs, isset( $accent_contract['attributes'] ) && is_array( $accent_contract['attributes'] ) ? $accent_contract['attributes'] : array() ) : array_merge( $section_attrs, isset( $accent_contract['attributes'] ) && is_array( $accent_contract['attributes'] ) ? $accent_contract['attributes'] : array() );
$section_attrs    = function_exists( 'mrn_base_stack_merge_builder_attributes' ) ? mrn_base_stack_merge_builder_attributes( $section_attrs, isset( $motion_contract['attributes'] ) && is_array( $motion_contract['attributes'] ) ? $motion_contract['attributes'] : array() ) : array_merge( $section_attrs, isset( $motion_contract['attributes'] ) && is_array( $motion_contract['attributes'] ) ? $motion_contract['attributes'] : array() );

if ( '' !== $background_color && function_exists( 'mrn_site_colors_get_css_var' ) ) {
	$section_styles[] = '--mrn-wpforms-row-bg: var(' . mrn_site_colors_get_css_var( $background_color ) . ')';
}

$section_attr_html = function_exists( 'mrn_base_stack_get_html_attributes' ) ? mrn_base_stack_get_html_attributes( $section_attrs ) : '';
$surface_style     = function_exists( 'mrn_base_stack_get_inline_style_attribute' ) ? mrn_base_stack_get_inline_style_attribute( $section_styles ) : implode( '; ', $section_styles );
$is_full_width     = 'full-width' === ( $width_layers['width'] ?? '' );
$shortcode         = sprintf( '[wpforms id="%d" title="false" description="false"]', $form_id );
$has_intro_content = '' !== $label || '' !== $heading || '' !== $subheading || '' !== trim( wp_strip_all_tags( $intro ) );

echo function_exists( 'mrn_base_stack_get_builder_anchor_markup' ) ? mrn_base_stack_get_builder_anchor_markup( $row ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Anchor markup is escaped in the helper.
?>
<section class="<?php echo esc_attr( implode( ' ', $section_classes ) ); ?>"<?php echo '' !== $section_attr_html ? ' ' . $section_attr_html : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="mrn-layout-section mrn-layout-section--wpforms <?php echo esc_attr( $width_layers['section_class'] ); ?><?php echo $is_full_width ? ' mrn-layout-surface' : ''; ?>"<?php echo $is_full_width && '' !== $surface_style ? ' style="' . esc_attr( $surface_style ) . '"' : ''; ?>>
		<div class="mrn-layout-container <?php echo esc_attr( $width_layers['container_class'] ); ?><?php echo ! $is_full_width ? ' mrn-layout-surface' : ''; ?>"<?php echo ! $is_full_width && '' !== $surface_style ? ' style="' . esc_attr( $surface_style ) . '"' : ''; ?>>
			<div class="mrn-layout-grid mrn-layout-grid--wpforms mrn-layout-grid--form-shell">
				<?php if ( $has_intro_content ) : ?>
					<div class="mrn-layout-content mrn-layout-content--text mrn-layout-content--form-shell-text mrn-wpforms-row__content mrn-ui__body">
						<?php if ( '' !== $label || '' !== $heading || '' !== $subheading ) : ?>
							<div class="mrn-ui__head">
								<?php if ( '' !== $label ) : ?>
									<<?php echo esc_html( $label_tag ); ?> class="mrn-ui__label"><?php echo function_exists( 'mrn_base_stack_format_heading_inline_html' ) ? mrn_base_stack_format_heading_inline_html( $label ) : esc_html( $label ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></<?php echo esc_html( $label_tag ); ?>>
							<?php endif; ?>

							<?php if ( '' !== $heading ) : ?>
								<<?php echo esc_html( $heading_tag ); ?> class="mrn-ui__heading"><?php echo function_exists( 'mrn_base_stack_format_heading_inline_html' ) ? mrn_base_stack_format_heading_inline_html( $heading ) : esc_html( $heading ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></<?php echo esc_html( $heading_tag ); ?>>
							<?php endif; ?>

							<?php if ( '' !== $subheading ) : ?>
								<<?php echo esc_html( $subheading_tag ); ?> class="mrn-ui__sub"><?php echo function_exists( 'mrn_base_stack_format_heading_inline_html' ) ? mrn_base_stack_format_heading_inline_html( $subheading ) : esc_html( $subheading ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></<?php echo esc_html( $subheading_tag ); ?>>
							<?php endif; ?>
							</div>
						<?php endif; ?>

						<?php if ( '' !== trim( $intro ) ) : ?>
							<div class="mrn-ui__text">
								<?php echo apply_filters( 'the_content', $intro ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</div>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<div class="mrn-layout-content mrn-layout-content--form mrn-layout-content--form-shell-form mrn-wpforms-row__form">
					<?php echo do_shortcode( $shortcode ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
			</div>
		</div>
	</div>
</section>
