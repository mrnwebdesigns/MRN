<?php
/**
 * Builder row: FAQ Jump Nav.
 *
 * @package mrn-base-stack
 */

$context         = is_array( $args ?? null ) ? $args : array();
$row             = isset( $context['row'] ) && is_array( $context['row'] ) ? $context['row'] : array();
$builder_post_id = isset( $context['post_id'] ) ? (int) $context['post_id'] : get_the_ID();
$row_index       = isset( $context['index'] ) ? (int) $context['index'] : 0;
$entries         = function_exists( 'mrn_base_stack_get_faq_jump_nav_entries' ) ? mrn_base_stack_get_faq_jump_nav_entries( $builder_post_id ) : array();
$label           = isset( $row['label'] ) ? trim( (string) $row['label'] ) : '';
$label_tag       = function_exists( 'mrn_base_stack_normalize_text_tag' ) ? mrn_base_stack_normalize_text_tag( $row['label_tag'] ?? '', 'p' ) : 'p';
$heading         = isset( $row['heading'] ) ? trim( (string) $row['heading'] ) : '';
$heading_tag     = function_exists( 'mrn_base_stack_normalize_text_tag' ) ? mrn_base_stack_normalize_text_tag( $row['heading_tag'] ?? '', 'h2' ) : 'h2';
$alignment       = function_exists( 'mrn_base_stack_normalize_faq_jump_nav_alignment' ) ? mrn_base_stack_normalize_faq_jump_nav_alignment( $row['jump_nav_alignment'] ?? '' ) : sanitize_key( (string) ( $row['jump_nav_alignment'] ?? 'left' ) );
$wrap            = function_exists( 'mrn_base_stack_normalize_faq_jump_nav_wrap' ) ? mrn_base_stack_normalize_faq_jump_nav_wrap( $row['jump_nav_wrap'] ?? '' ) : sanitize_key( (string) ( $row['jump_nav_wrap'] ?? 'wrap' ) );

if ( empty( $entries ) ) {
	return;
}

$width_layers = function_exists( 'mrn_base_stack_get_section_width_layers' )
	? mrn_base_stack_get_section_width_layers( $row['section_width'] ?? '', 'content', 'full-width' )
	: array(
		'width'           => 'content',
		'section_class'   => 'mrn-layout-section--contained',
		'container_class' => 'mrn-layout-container--content',
	);

$section_classes   = array(
	'mrn-content-builder__row',
	'mrn-content-builder__row--faq-jump-nav',
	'mrn-content-builder__row--faq-jump-nav-align-' . sanitize_html_class( $alignment ),
	'mrn-content-builder__row--faq-jump-nav-wrap-' . sanitize_html_class( $wrap ),
);
$display_contract  = function_exists( 'mrn_base_stack_get_builder_display_contract' ) ? mrn_base_stack_get_builder_display_contract( $row, 'faq_jump_nav' ) : array(
	'classes'    => array(),
	'attributes' => array(),
);
$motion_contract   = function_exists( 'mrn_base_stack_get_builder_motion_contract' ) ? mrn_base_stack_get_builder_motion_contract( $row ) : array(
	'classes'    => array(),
	'attributes' => array(),
);
$section_classes   = function_exists( 'mrn_base_stack_merge_builder_section_classes' ) ? mrn_base_stack_merge_builder_section_classes( $section_classes, $display_contract ) : $section_classes;
$section_classes   = function_exists( 'mrn_base_stack_merge_builder_section_classes' ) ? mrn_base_stack_merge_builder_section_classes( $section_classes, $motion_contract ) : $section_classes;
$section_attrs     = isset( $display_contract['attributes'] ) && is_array( $display_contract['attributes'] ) ? $display_contract['attributes'] : array();
$section_attrs     = function_exists( 'mrn_base_stack_merge_builder_attributes' ) ? mrn_base_stack_merge_builder_attributes( $section_attrs, isset( $motion_contract['attributes'] ) && is_array( $motion_contract['attributes'] ) ? $motion_contract['attributes'] : array() ) : array_merge( $section_attrs, isset( $motion_contract['attributes'] ) && is_array( $motion_contract['attributes'] ) ? $motion_contract['attributes'] : array() );
$section_attr_html = function_exists( 'mrn_base_stack_get_html_attributes' ) ? mrn_base_stack_get_html_attributes( $section_attrs ) : '';
$heading_id        = 'mrn-faq-jump-nav-heading-' . absint( $builder_post_id ) . '-' . absint( $row_index );
$nav_attrs         = '' !== $heading ? ' aria-labelledby="' . esc_attr( $heading_id ) . '"' : ' aria-label="' . esc_attr__( 'FAQ sections', 'mrn-base-stack' ) . '"';

echo function_exists( 'mrn_base_stack_get_builder_anchor_markup' ) ? mrn_base_stack_get_builder_anchor_markup( $row ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Anchor markup is escaped in the helper.
?>
<section class="<?php echo esc_attr( implode( ' ', $section_classes ) ); ?>"<?php echo '' !== $section_attr_html ? ' ' . $section_attr_html : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="mrn-layout-section mrn-layout-section--faq-jump-nav <?php echo esc_attr( $width_layers['section_class'] ); ?>">
		<div class="mrn-layout-container <?php echo esc_attr( $width_layers['container_class'] ); ?>">
			<nav class="mrn-faq-jump-nav" <?php echo $nav_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
				<?php if ( '' !== $label || '' !== $heading ) : ?>
					<div class="mrn-faq-jump-nav__head mrn-ui__head">
						<?php if ( '' !== $label ) : ?>
							<<?php echo esc_html( $label_tag ); ?> class="mrn-faq-jump-nav__label mrn-ui__label"><?php echo function_exists( 'mrn_base_stack_format_heading_inline_html' ) ? mrn_base_stack_format_heading_inline_html( $label ) : esc_html( $label ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></<?php echo esc_html( $label_tag ); ?>>
						<?php endif; ?>

						<?php if ( '' !== $heading ) : ?>
							<<?php echo esc_html( $heading_tag ); ?> id="<?php echo esc_attr( $heading_id ); ?>" class="mrn-faq-jump-nav__heading mrn-ui__heading"><?php echo function_exists( 'mrn_base_stack_format_heading_inline_html' ) ? mrn_base_stack_format_heading_inline_html( $heading ) : esc_html( $heading ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></<?php echo esc_html( $heading_tag ); ?>>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<ul class="mrn-faq-jump-nav__list">
					<?php foreach ( $entries as $entry ) : ?>
						<?php
						$anchor = isset( $entry['anchor'] ) ? (string) $entry['anchor'] : '';
						$text   = isset( $entry['label'] ) ? (string) $entry['label'] : '';
						if ( '' === $anchor || '' === $text ) {
							continue;
						}
						?>
						<li class="mrn-faq-jump-nav__item">
							<a class="mrn-faq-jump-nav__link" href="<?php echo esc_url( '#' . $anchor ); ?>"><?php echo esc_html( $text ); ?></a>
						</li>
					<?php endforeach; ?>
				</ul>
			</nav>
		</div>
	</div>
</section>
