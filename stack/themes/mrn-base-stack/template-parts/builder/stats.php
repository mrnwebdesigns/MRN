<?php
/**
 * Builder row: Stats.
 *
 * @package mrn-base-stack
 */

$context          = is_array( $args ?? null ) ? $args : array();
$row              = isset( $context['row'] ) && is_array( $context['row'] ) ? $context['row'] : array();
$label            = isset( $row['label'] ) ? trim( (string) $row['label'] ) : '';
$label_tag        = function_exists( 'mrn_base_stack_normalize_text_tag' ) ? mrn_base_stack_normalize_text_tag( $row['label_tag'] ?? '', 'p' ) : 'p';
$heading          = isset( $row['heading'] ) ? trim( (string) $row['heading'] ) : '';
$heading_tag      = isset( $row['heading_tag'] ) ? strtolower( (string) $row['heading_tag'] ) : 'h2';
$subheading       = isset( $row['subheading'] ) ? trim( (string) $row['subheading'] ) : '';
$subheading_tag   = isset( $row['subheading_tag'] ) ? strtolower( (string) $row['subheading_tag'] ) : 'p';
$items            = isset( $row['stat_items'] ) && is_array( $row['stat_items'] ) ? $row['stat_items'] : array();
$columns          = isset( $row['columns'] ) ? max( 2, min( 4, (int) $row['columns'] ) ) : 2;
$show_dividers    = ! array_key_exists( 'show_dividers', $row ) || ! empty( $row['show_dividers'] );
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

if ( ! in_array( $subheading_tag, $allowed_tags, true ) ) {
	$subheading_tag = 'p';
}

$valid_items = array();
foreach ( $items as $item ) {
	if ( ! is_array( $item ) ) {
		continue;
	}

	$value      = isset( $item['value'] ) ? trim( (string) $item['value'] ) : '';
	$item_label = isset( $item['item_label'] ) ? trim( (string) $item['item_label'] ) : '';

	if ( '' === $value && '' === $item_label ) {
		continue;
	}

	$valid_items[] = array(
		'value'          => $value,
		'item_label'     => $item_label,
		'item_label_tag' => function_exists( 'mrn_base_stack_normalize_text_tag' ) ? mrn_base_stack_normalize_text_tag( $item['item_label_tag'] ?? '', 'p' ) : 'p',
	);
}

if ( '' === $label && '' === $heading && '' === $subheading && empty( $valid_items ) ) {
	return;
}

$section_classes = array(
	'mrn-content-builder__row',
	'mrn-content-builder__row--stats',
	'mrn-content-builder__row--stats-columns-' . sanitize_html_class( (string) $columns ),
);

if ( $show_dividers ) {
	$section_classes[] = 'mrn-content-builder__row--stats-has-dividers';
}

$section_styles = array();

if ( '' !== $background_color && function_exists( 'mrn_site_colors_get_css_var' ) ) {
	$section_styles[] = '--mrn-stats-row-bg: var(' . mrn_site_colors_get_css_var( $background_color ) . ')';
}

$accent_contract   = function_exists( 'mrn_base_stack_get_builder_accent_contract' ) ? mrn_base_stack_get_builder_accent_contract( $bottom_accent, $accent_slug ) : array(
	'classes'    => $bottom_accent ? array( 'has-bottom-accent' ) : array(),
	'attributes' => array(),
);
$motion_contract   = function_exists( 'mrn_base_stack_get_builder_motion_contract' ) ? mrn_base_stack_get_builder_motion_contract( $row ) : array(
	'classes'    => array(),
	'attributes' => array(),
);
$section_classes   = function_exists( 'mrn_base_stack_merge_builder_section_classes' ) ? mrn_base_stack_merge_builder_section_classes( $section_classes, $accent_contract ) : $section_classes;
$section_classes   = function_exists( 'mrn_base_stack_merge_builder_section_classes' ) ? mrn_base_stack_merge_builder_section_classes( $section_classes, $motion_contract ) : $section_classes;
$section_attrs     = isset( $accent_contract['attributes'] ) && is_array( $accent_contract['attributes'] ) ? $accent_contract['attributes'] : array();
$section_attrs     = function_exists( 'mrn_base_stack_merge_builder_attributes' ) ? mrn_base_stack_merge_builder_attributes( $section_attrs, isset( $motion_contract['attributes'] ) && is_array( $motion_contract['attributes'] ) ? $motion_contract['attributes'] : array() ) : array_merge( $section_attrs, isset( $motion_contract['attributes'] ) && is_array( $motion_contract['attributes'] ) ? $motion_contract['attributes'] : array() );
$section_attr_html = function_exists( 'mrn_base_stack_get_html_attributes' ) ? mrn_base_stack_get_html_attributes( $section_attrs ) : '';
$surface_style     = function_exists( 'mrn_base_stack_get_inline_style_attribute' ) ? mrn_base_stack_get_inline_style_attribute( $section_styles ) : implode( '; ', $section_styles );
$is_full_width     = 'full-width' === ( $width_layers['width'] ?? '' );
?>
<section class="<?php echo esc_attr( implode( ' ', $section_classes ) ); ?>"<?php echo '' !== $section_attr_html ? ' ' . $section_attr_html : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="mrn-layout-section mrn-layout-section--stats <?php echo esc_attr( $width_layers['section_class'] ); ?><?php echo $is_full_width ? ' mrn-layout-surface' : ''; ?>"<?php echo $is_full_width && '' !== $surface_style ? ' style="' . esc_attr( $surface_style ) . '"' : ''; ?>>
		<div class="mrn-layout-container <?php echo esc_attr( $width_layers['container_class'] ); ?><?php echo ! $is_full_width ? ' mrn-layout-surface' : ''; ?>"<?php echo ! $is_full_width && '' !== $surface_style ? ' style="' . esc_attr( $surface_style ) . '"' : ''; ?>>
			<div class="mrn-layout-grid mrn-layout-grid--stats">
		<?php if ( '' !== $label || '' !== $heading || '' !== $subheading ) : ?>
			<header class="mrn-layout-content mrn-layout-content--text mrn-stats-row__header">
				<?php if ( '' !== $label ) : ?>
					<<?php echo esc_html( $label_tag ); ?> class="mrn-stats-row__label"><?php echo function_exists( 'mrn_base_stack_format_heading_inline_html' ) ? mrn_base_stack_format_heading_inline_html( $label ) : esc_html( $label ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></<?php echo esc_html( $label_tag ); ?>>
				<?php endif; ?>
				<?php if ( '' !== $heading ) : ?>
					<<?php echo esc_html( $heading_tag ); ?> class="mrn-stats-row__heading"><?php echo function_exists( 'mrn_base_stack_format_heading_inline_html' ) ? mrn_base_stack_format_heading_inline_html( $heading ) : esc_html( $heading ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></<?php echo esc_html( $heading_tag ); ?>>
				<?php endif; ?>
				<?php if ( '' !== $subheading ) : ?>
					<<?php echo esc_html( $subheading_tag ); ?> class="mrn-stats-row__subheading"><?php echo function_exists( 'mrn_base_stack_format_heading_inline_html' ) ? mrn_base_stack_format_heading_inline_html( $subheading ) : esc_html( $subheading ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></<?php echo esc_html( $subheading_tag ); ?>>
				<?php endif; ?>
			</header>
		<?php endif; ?>

		<?php if ( ! empty( $valid_items ) ) : ?>
			<div class="mrn-stats-row__grid mrn-stats-row__grid--metrics-shell">
				<?php foreach ( $valid_items as $item ) : ?>
					<div class="mrn-stats-row__item mrn-stats-row__item--metrics-shell">
						<?php if ( '' !== $item['value'] ) : ?>
							<div class="mrn-stats-row__value"><?php echo function_exists( 'mrn_base_stack_format_heading_inline_html' ) ? mrn_base_stack_format_heading_inline_html( $item['value'] ) : esc_html( $item['value'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
						<?php endif; ?>
						<?php if ( '' !== $item['item_label'] ) : ?>
							<<?php echo esc_html( $item['item_label_tag'] ); ?> class="mrn-stats-row__item-label"><?php echo function_exists( 'mrn_base_stack_format_heading_inline_html' ) ? mrn_base_stack_format_heading_inline_html( $item['item_label'] ) : esc_html( $item['item_label'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></<?php echo esc_html( $item['item_label_tag'] ); ?>>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
			</div>
		</div>
	</div>
</section>
