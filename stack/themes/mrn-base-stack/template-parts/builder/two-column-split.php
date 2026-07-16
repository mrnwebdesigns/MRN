<?php
/**
 * Builder row: Two Column Split.
 *
 * @package mrn-base-stack
 */

$context                 = is_array( $args ?? null ) ? $args : array();
$row                     = isset( $context['row'] ) && is_array( $context['row'] ) ? $context['row'] : array();
$context_post_id         = isset( $context['post_id'] ) ? (int) $context['post_id'] : get_the_ID();
$is_hero_context         = isset( $row['__mrn_builder_field_name'] ) && 'page_hero_rows' === (string) $row['__mrn_builder_field_name'];
$label                   = isset( $row['label'] ) ? trim( (string) $row['label'] ) : '';
$label_tag               = mrn_base_stack_normalize_text_tag( $row['label_tag'] ?? '', 'p' );
$heading                 = isset( $row['heading'] ) ? trim( (string) $row['heading'] ) : '';
$heading_tag             = isset( $row['heading_tag'] ) ? strtolower( (string) $row['heading_tag'] ) : 'h2';
$hero_page_title         = '';
$subheading              = isset( $row['subheading'] ) ? trim( (string) $row['subheading'] ) : '';
$subheading_tag          = isset( $row['subheading_tag'] ) ? strtolower( (string) $row['subheading_tag'] ) : 'p';
$left_rows               = isset( $row['left_column_rows'] ) && is_array( $row['left_column_rows'] ) ? $row['left_column_rows'] : array();
$right_rows              = isset( $row['right_column_rows'] ) && is_array( $row['right_column_rows'] ) ? $row['right_column_rows'] : array();
$column_ratio            = isset( $row['column_ratio'] ) ? (string) $row['column_ratio'] : '50-50';
$background_color        = isset( $row['background_color'] ) ? trim( (string) $row['background_color'] ) : '';
$background_image        = $row['background_image'] ?? null;
$background_video        = isset( $row['background_video'] ) ? (string) $row['background_video'] : '';
$background_video_upload = isset( $row['background_video_upload'] ) && is_array( $row['background_video_upload'] ) ? $row['background_video_upload'] : array();
$hero_min_height         = $is_hero_context && isset( $row['hero_min_height'] ) && is_scalar( $row['hero_min_height'] )
	? mrn_base_stack_sanitize_spacing_dimension_value( (string) $row['hero_min_height'] )
	: '';
$hero_vertical_padding   = $is_hero_context && isset( $row['hero_vertical_padding'] ) && is_scalar( $row['hero_vertical_padding'] )
	? mrn_base_stack_sanitize_spacing_dimension_value( (string) $row['hero_vertical_padding'] )
	: '';
$hero_content_alignment  = $is_hero_context
	? mrn_base_stack_normalize_hero_content_alignment( $row['hero_content_alignment'] ?? '' )
	: '';
$hero_vertical_alignment = $is_hero_context
	? mrn_base_stack_normalize_hero_vertical_alignment( $row['hero_vertical_alignment'] ?? '' )
	: '';
$width_layers            = mrn_base_stack_get_section_width_layers( $row['section_width'] ?? '', 'wide', 'wide' );
$ratio_map               = array(
	'50-50' => 'minmax(0, 1fr) minmax(0, 1fr)',
	'60-40' => 'minmax(0, 3fr) minmax(0, 2fr)',
	'40-60' => 'minmax(0, 2fr) minmax(0, 3fr)',
	'67-33' => 'minmax(0, 2fr) minmax(0, 1fr)',
	'33-67' => 'minmax(0, 1fr) minmax(0, 2fr)',
);
$left_row                = ! empty( $left_rows[0] ) && is_array( $left_rows[0] ) ? $left_rows[0] : array();
$right_row               = ! empty( $right_rows[0] ) && is_array( $right_rows[0] ) ? $right_rows[0] : array();

$allowed_custom_heading_tags = array( 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'span', 'div' );
if ( $is_hero_context ) {
	$heading_contract = mrn_base_stack_get_hero_heading_contract( $row, $context_post_id, 'h2' );
	$hero_page_title  = isset( $heading_contract['page_title'] ) ? trim( (string) $heading_contract['page_title'] ) : '';
	$heading          = isset( $heading_contract['custom_heading'] ) ? trim( (string) $heading_contract['custom_heading'] ) : '';
	$heading_tag      = isset( $heading_contract['custom_heading_tag'] ) ? strtolower( (string) $heading_contract['custom_heading_tag'] ) : 'h2';
}

$allowed_tags = array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'span', 'div' );
if ( ! in_array( $heading_tag, $is_hero_context ? $allowed_custom_heading_tags : $allowed_tags, true ) ) {
	$heading_tag = 'h2';
}

if ( ! in_array( $subheading_tag, $allowed_tags, true ) ) {
	$subheading_tag = 'p';
}

if ( empty( $left_row ) && empty( $right_row ) ) {
	return;
}

$grid_template  = isset( $ratio_map[ $column_ratio ] ) ? $ratio_map[ $column_ratio ] : $ratio_map['50-50'];
$section_styles = array( '--mrn-two-column-template: ' . $grid_template );

if ( '' !== $background_color && function_exists( 'mrn_site_colors_get_css_var' ) ) {
	$section_styles[] = '--mrn-two-column-bg: var(' . mrn_site_colors_get_css_var( $background_color ) . ')';
}

$background_gradient_style = mrn_base_stack_get_background_gradient_style_declaration( $row, '--mrn-two-column-bg-gradient' );

if ( '' !== $background_gradient_style ) {
	$section_styles[] = $background_gradient_style;
}

$background_image_markup = mrn_base_stack_get_background_image_markup( $background_image );
$background_video_markup = mrn_base_stack_get_background_video_markup(
	$background_video,
	$background_video_upload,
	array(
		'poster_image' => $background_image,
	)
);

$section_classes = array(
	'mrn-content-builder__row',
	'mrn-content-builder__row--two-column-split',
);
$section_attrs   = array();

if ( $is_hero_context ) {
	$section_classes[] = 'mrn-content-builder__row--hero-context';
	$section_classes[] = 'mrn-content-builder__row--hero-content-align-' . sanitize_html_class( $hero_content_alignment );
	$section_classes[] = 'mrn-content-builder__row--hero-vertical-align-' . sanitize_html_class( $hero_vertical_alignment );
}

if ( '' !== $background_image_markup ) {
	$section_classes[] = 'has-background-image';
}

if ( '' !== $background_video_markup ) {
	$section_classes[] = 'has-background-video';
}

if ( '' !== $background_gradient_style ) {
	$section_classes[] = 'has-background-gradient';
}

$display_contract = mrn_base_stack_get_builder_display_contract( $row, 'two_column_split' );
$motion_contract  = mrn_base_stack_get_builder_motion_contract( $row );
$section_classes  = mrn_base_stack_merge_builder_section_classes( $section_classes, $display_contract );
$section_classes  = mrn_base_stack_merge_builder_section_classes( $section_classes, $motion_contract );
$section_attrs    = mrn_base_stack_merge_builder_attributes( $section_attrs, $display_contract['attributes'] );
$section_attrs    = mrn_base_stack_merge_builder_attributes( $section_attrs, isset( $motion_contract['attributes'] ) && is_array( $motion_contract['attributes'] ) ? $motion_contract['attributes'] : array() );

$surface_style = mrn_base_stack_get_inline_style_attribute( $section_styles );
$grid_styles   = array();
if ( '' !== $hero_min_height ) {
	$grid_styles[] = 'min-height: ' . $hero_min_height;
}
if ( '' !== $hero_vertical_padding ) {
	$grid_styles[] = 'padding-block: ' . $hero_vertical_padding;
}
$grid_style         = mrn_base_stack_get_inline_style_attribute( $grid_styles );
$section_attr_html  = mrn_base_stack_get_html_attributes( $section_attrs );
$is_full_width      = 'full-width' === ( $width_layers['width'] ?? '' );
$surface_class      = ( '' !== $background_image_markup ? ' has-row-background-media' : '' ) . ( '' !== $background_video_markup ? ' has-row-background-video' : '' ) . ( '' !== $background_gradient_style ? ' has-background-gradient' : '' );
$has_header_content = '' !== $label || '' !== $hero_page_title || '' !== $heading || '' !== $subheading;
echo mrn_base_stack_get_builder_anchor_markup( $row ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Anchor markup is escaped in the helper.
?>
<section class="<?php echo esc_attr( implode( ' ', $section_classes ) ); ?>"<?php echo '' !== $section_attr_html ? ' ' . $section_attr_html : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="mrn-layout-section mrn-layout-section--two-column-split <?php echo esc_attr( $width_layers['section_class'] ); ?><?php echo $is_full_width ? ' mrn-layout-surface' . esc_attr( $surface_class ) : ''; ?>"<?php echo $is_full_width && '' !== $surface_style ? ' style="' . esc_attr( $surface_style ) . '"' : ''; ?>>
		<?php echo $is_full_width ? $background_image_markup : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Decorative image markup is escaped in the helper. ?>
		<?php echo $is_full_width ? $background_video_markup : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Decorative video markup is escaped in the helper. ?>
		<div class="mrn-layout-container <?php echo esc_attr( $width_layers['container_class'] ); ?><?php echo ! $is_full_width ? ' mrn-layout-surface' . esc_attr( $surface_class ) : ''; ?>"<?php echo ! $is_full_width && '' !== $surface_style ? ' style="' . esc_attr( $surface_style ) . '"' : ''; ?>>
			<?php echo ! $is_full_width ? $background_image_markup : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Decorative image markup is escaped in the helper. ?>
			<?php echo ! $is_full_width ? $background_video_markup : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Decorative video markup is escaped in the helper. ?>
			<div class="mrn-layout-grid mrn-layout-grid--two-column-split mrn-two-column-split mrn-layout-grid--split-shell mrn-ui__body"<?php echo '' !== $grid_style ? ' style="' . esc_attr( $grid_style ) . '"' : ''; ?>>
			<?php if ( $has_header_content ) : ?>
					<header class="mrn-layout-content mrn-layout-content--text mrn-two-column-split__header mrn-two-column-split__header--split-shell mrn-ui__head">
					<?php if ( '' !== $label ) : ?>
							<<?php echo esc_html( $label_tag ); ?> class="mrn-ui__label"><?php echo mrn_base_stack_format_heading_inline_html( $label ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></<?php echo esc_html( $label_tag ); ?>>
					<?php endif; ?>
					<?php if ( '' !== $hero_page_title ) : ?>
							<h1 class="mrn-ui__heading mrn-ui__heading--page-title"><?php echo mrn_base_stack_format_heading_inline_html( $hero_page_title ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></h1>
					<?php endif; ?>
					<?php if ( '' !== $heading ) : ?>
							<<?php echo esc_html( $heading_tag ); ?> class="mrn-ui__heading"><?php echo mrn_base_stack_format_heading_inline_html( $heading ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></<?php echo esc_html( $heading_tag ); ?>>
					<?php endif; ?>
					<?php if ( '' !== $subheading ) : ?>
							<<?php echo esc_html( $subheading_tag ); ?> class="mrn-ui__sub"><?php echo mrn_base_stack_format_heading_inline_html( $subheading ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></<?php echo esc_html( $subheading_tag ); ?>>
					<?php endif; ?>
				</header>
			<?php endif; ?>
			<div class="mrn-layout-content mrn-layout-content--column mrn-two-column-split__column mrn-two-column-split__column--left mrn-two-column-split__column--split-shell">
				<?php
				if ( ! empty( $left_row ) ) {
					mrn_base_stack_render_builder_row(
						$left_row,
						$context_post_id,
						0
					);
				}
				?>
			</div>
			<div class="mrn-layout-content mrn-layout-content--column mrn-two-column-split__column mrn-two-column-split__column--right mrn-two-column-split__column--split-shell">
				<?php
				if ( ! empty( $right_row ) ) {
					mrn_base_stack_render_builder_row(
						$right_row,
						$context_post_id,
						0
					);
				}
				?>
			</div>
			</div>
		</div>
	</div>
</section>
