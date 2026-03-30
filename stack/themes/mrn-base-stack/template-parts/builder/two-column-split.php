<?php
/**
 * Builder row: Two Column Split.
 *
 * @package mrn-base-stack
 */

$context       = is_array( $args ?? null ) ? $args : array();
$row           = isset( $context['row'] ) && is_array( $context['row'] ) ? $context['row'] : array();
$post_id       = isset( $context['post_id'] ) ? (int) $context['post_id'] : get_the_ID();
$left_rows     = isset( $row['left_column_rows'] ) && is_array( $row['left_column_rows'] ) ? $row['left_column_rows'] : array();
$right_rows    = isset( $row['right_column_rows'] ) && is_array( $row['right_column_rows'] ) ? $row['right_column_rows'] : array();
$column_ratio  = isset( $row['column_ratio'] ) ? (string) $row['column_ratio'] : '50-50';
$background_color = isset( $row['background_color'] ) ? trim( (string) $row['background_color'] ) : '';
$background_image = isset( $row['background_image'] ) && is_array( $row['background_image'] ) ? $row['background_image'] : array();
$background_video = isset( $row['background_video'] ) ? (string) $row['background_video'] : '';
$background_video_upload = isset( $row['background_video_upload'] ) && is_array( $row['background_video_upload'] ) ? $row['background_video_upload'] : array();
$width_layers    = function_exists( 'mrn_base_stack_get_section_width_layers' )
	? mrn_base_stack_get_section_width_layers( $row['section_width'] ?? '', 'wide', 'wide' )
	: array(
		'width'           => 'wide',
		'section_class'   => 'mrn-layout-section--contained',
		'container_class' => 'mrn-layout-container--wide',
	);
$ratio_map     = array(
	'50-50' => 'minmax(0, 1fr) minmax(0, 1fr)',
	'60-40' => 'minmax(0, 3fr) minmax(0, 2fr)',
	'40-60' => 'minmax(0, 2fr) minmax(0, 3fr)',
	'67-33' => 'minmax(0, 2fr) minmax(0, 1fr)',
	'33-67' => 'minmax(0, 1fr) minmax(0, 2fr)',
);
$left_row      = ! empty( $left_rows[0] ) && is_array( $left_rows[0] ) ? $left_rows[0] : array();
$right_row     = ! empty( $right_rows[0] ) && is_array( $right_rows[0] ) ? $right_rows[0] : array();

if ( empty( $left_row ) && empty( $right_row ) ) {
	return;
}

$grid_template = isset( $ratio_map[ $column_ratio ] ) ? $ratio_map[ $column_ratio ] : $ratio_map['50-50'];
$section_styles = array( '--mrn-two-column-template: ' . $grid_template );

if ( '' !== $background_color && function_exists( 'mrn_site_colors_get_css_var' ) ) {
	$section_styles[] = '--mrn-two-column-bg: var(' . mrn_site_colors_get_css_var( $background_color ) . ')';
}

$background_image_style = function_exists( 'mrn_base_stack_get_background_image_style' )
	? mrn_base_stack_get_background_image_style( $background_image, '--mrn-two-column-bg-image' )
	: '';

if ( '' !== $background_image_style ) {
	$section_styles[] = $background_image_style;
}

$background_video_data = function_exists( 'mrn_base_stack_get_video_embed' ) ? mrn_base_stack_get_video_embed(
	$background_video,
	array(
		'autoplay'   => true,
		'muted'      => true,
		'loop'       => true,
		'controls'   => false,
		'background' => true,
	)
) : array(
	'provider'  => '',
	'embed_url' => '',
);
$background_video_url  = isset( $background_video_data['embed_url'] ) ? (string) $background_video_data['embed_url'] : '';
$local_video_url       = isset( $background_video_upload['url'] ) ? (string) $background_video_upload['url'] : '';
$local_video_mime      = isset( $background_video_upload['mime_type'] ) ? (string) $background_video_upload['mime_type'] : '';
$background_video_kind = '';

if ( '' !== $local_video_url ) {
	$background_video_kind = 'local';
	$background_video_url  = $local_video_url;
} elseif ( '' !== $background_video_url ) {
	$background_video_kind = 'remote';
}

$section_classes = array(
	'mrn-content-builder__row',
	'mrn-content-builder__row--two-column-split',
);

if ( '' !== $background_image_style ) {
	$section_classes[] = 'has-background-image';
}

if ( '' !== $background_video_url ) {
	$section_classes[] = 'has-background-video';
}

$surface_style = function_exists( 'mrn_base_stack_get_inline_style_attribute' ) ? mrn_base_stack_get_inline_style_attribute( $section_styles ) : implode( '; ', $section_styles );
$is_full_width = 'full-width' === ( $width_layers['width'] ?? '' );
?>
<section class="<?php echo esc_attr( implode( ' ', $section_classes ) ); ?>">
	<?php if ( '' !== $background_video_url ) : ?>
		<div class="mrn-section-background-media mrn-two-column-split__background-media" data-video-src="<?php echo esc_url( $background_video_url ); ?>" data-video-kind="<?php echo esc_attr( $background_video_kind ); ?>"<?php if ( 'local' === $background_video_kind && '' !== $local_video_mime ) : ?> data-video-mime="<?php echo esc_attr( $local_video_mime ); ?>"<?php endif; ?> data-video-background="true" data-video-autoplay="true" data-video-muted="true" data-video-loop="true" data-video-controls="false" data-video-delay="2000" data-video-desktop-only="true" aria-hidden="true"></div>
	<?php endif; ?>
	<div class="mrn-layout-section mrn-layout-section--two-column-split <?php echo esc_attr( $width_layers['section_class'] ); ?><?php echo $is_full_width ? ' mrn-layout-surface' : ''; ?>"<?php echo $is_full_width && '' !== $surface_style ? ' style="' . esc_attr( $surface_style ) . '"' : ''; ?>>
		<div class="mrn-layout-container <?php echo esc_attr( $width_layers['container_class'] ); ?><?php echo ! $is_full_width ? ' mrn-layout-surface' : ''; ?>"<?php echo ! $is_full_width && '' !== $surface_style ? ' style="' . esc_attr( $surface_style ) . '"' : ''; ?>>
			<div class="mrn-layout-grid mrn-layout-grid--two-column-split mrn-two-column-split">
			<div class="mrn-layout-content mrn-layout-content--column mrn-two-column-split__column mrn-two-column-split__column--left">
				<?php
				if ( ! empty( $left_row ) && function_exists( 'mrn_base_stack_render_builder_row' ) ) {
					mrn_base_stack_render_builder_row(
						$left_row,
						$post_id,
						0
					);
				}
				?>
			</div>
			<div class="mrn-layout-content mrn-layout-content--column mrn-two-column-split__column mrn-two-column-split__column--right">
				<?php
				if ( ! empty( $right_row ) && function_exists( 'mrn_base_stack_render_builder_row' ) ) {
					mrn_base_stack_render_builder_row(
						$right_row,
						$post_id,
						0
					);
				}
				?>
			</div>
			</div>
		</div>
	</div>
</section>
