<?php
/**
 * Builder row: Video.
 *
 * @package mrn-base-stack
 */

$context             = is_array( $args ?? null ) ? $args : array();
$row                 = isset( $context['row'] ) && is_array( $context['row'] ) ? $context['row'] : array();
$label               = isset( $row['label'] ) ? trim( (string) $row['label'] ) : '';
$label_tag           = function_exists( 'mrn_base_stack_normalize_text_tag' ) ? mrn_base_stack_normalize_text_tag( $row['label_tag'] ?? '', 'p' ) : 'p';
$heading             = isset( $row['heading'] ) ? trim( (string) $row['heading'] ) : '';
$heading_tag         = isset( $row['heading_tag'] ) ? strtolower( (string) $row['heading_tag'] ) : 'h2';
$subheading          = isset( $row['subheading'] ) ? trim( (string) $row['subheading'] ) : '';
$subheading_tag      = isset( $row['subheading_tag'] ) ? strtolower( (string) $row['subheading_tag'] ) : 'p';
$content             = isset( $row['content'] ) ? (string) $row['content'] : '';
$remote_video        = isset( $row['video_remote'] ) ? (string) $row['video_remote'] : '';
$upload_video        = isset( $row['video_upload'] ) && is_array( $row['video_upload'] ) ? $row['video_upload'] : array();
$background_color    = isset( $row['background_color'] ) ? trim( (string) $row['background_color'] ) : '';
$bottom_accent       = ! empty( $row['bottom_accent'] );
$accent_slug         = isset( $row['bottom_accent_style'] ) ? (string) $row['bottom_accent_style'] : '';
$width_layers        = function_exists( 'mrn_base_stack_get_section_width_layers' )
	? mrn_base_stack_get_section_width_layers( $row['section_width'] ?? '', 'wide', 'wide' )
	: array(
		'width'           => 'wide',
		'section_class'   => 'mrn-layout-section--contained',
		'container_class' => 'mrn-layout-container--wide',
	);
$local_video_url     = isset( $upload_video['url'] ) ? (string) $upload_video['url'] : '';
$local_video_mime    = isset( $upload_video['mime_type'] ) ? (string) $upload_video['mime_type'] : '';
$remote_video_embed  = function_exists( 'mrn_base_stack_get_video_embed' ) ? mrn_base_stack_get_video_embed(
	$remote_video,
	array(
		'autoplay'   => false,
		'muted'      => false,
		'loop'       => false,
		'controls'   => true,
		'background' => false,
	)
) : array(
	'provider'  => '',
	'embed_url' => '',
);
$remote_video_url    = isset( $remote_video_embed['embed_url'] ) ? (string) $remote_video_embed['embed_url'] : '';
$resolved_video_kind = '';
$resolved_video_url  = '';
$resolved_video_mime = '';

if ( '' !== $local_video_url ) {
	$resolved_video_kind = 'local';
	$resolved_video_url  = $local_video_url;
	$resolved_video_mime = $local_video_mime;
} elseif ( '' !== $remote_video_url ) {
	$resolved_video_kind = 'remote';
	$resolved_video_url  = $remote_video_url;
}

$allowed_tags = array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'span', 'div' );
if ( ! in_array( $heading_tag, $allowed_tags, true ) ) {
	$heading_tag = 'h2';
}

if ( ! in_array( $subheading_tag, $allowed_tags, true ) ) {
	$subheading_tag = 'p';
}

if ( '' === $label && '' === $heading && '' === $subheading && '' === trim( wp_strip_all_tags( $content ) ) && '' === $resolved_video_url ) {
	return;
}

$section_classes = array(
	'mrn-content-builder__row',
	'mrn-content-builder__row--video',
);
$section_styles  = array();

if ( '' !== $background_color && function_exists( 'mrn_site_colors_get_css_var' ) ) {
	$section_styles[] = '--mrn-video-row-bg: var(' . mrn_site_colors_get_css_var( $background_color ) . ')';
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
echo function_exists( 'mrn_base_stack_get_builder_anchor_markup' ) ? mrn_base_stack_get_builder_anchor_markup( $row ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Anchor markup is escaped in the helper.
?>
<section class="<?php echo esc_attr( implode( ' ', $section_classes ) ); ?>"<?php echo '' !== $section_attr_html ? ' ' . $section_attr_html : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="mrn-layout-section mrn-layout-section--video <?php echo esc_attr( $width_layers['section_class'] ); ?><?php echo $is_full_width ? ' mrn-layout-surface' : ''; ?>"<?php echo $is_full_width && '' !== $surface_style ? ' style="' . esc_attr( $surface_style ) . '"' : ''; ?>>
		<div class="mrn-layout-container <?php echo esc_attr( $width_layers['container_class'] ); ?><?php echo ! $is_full_width ? ' mrn-layout-surface' : ''; ?>"<?php echo ! $is_full_width && '' !== $surface_style ? ' style="' . esc_attr( $surface_style ) . '"' : ''; ?>>
			<div class="mrn-layout-grid mrn-layout-grid--video mrn-video-row mrn-layout-grid--video-feature">
			<?php if ( '' !== $label || '' !== $heading || '' !== $subheading || '' !== trim( wp_strip_all_tags( $content ) ) ) : ?>
				<div class="mrn-layout-content mrn-layout-content--text mrn-video-row__header mrn-video-row__header--video-feature">
					<?php if ( '' !== $label ) : ?>
						<<?php echo esc_html( $label_tag ); ?> class="mrn-video-row__label"><?php echo function_exists( 'mrn_base_stack_format_heading_inline_html' ) ? mrn_base_stack_format_heading_inline_html( $label ) : esc_html( $label ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></<?php echo esc_html( $label_tag ); ?>>
					<?php endif; ?>

					<?php if ( '' !== $heading ) : ?>
						<<?php echo esc_html( $heading_tag ); ?> class="mrn-video-row__heading"><?php echo function_exists( 'mrn_base_stack_format_heading_inline_html' ) ? mrn_base_stack_format_heading_inline_html( $heading ) : esc_html( $heading ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></<?php echo esc_html( $heading_tag ); ?>>
					<?php endif; ?>

					<?php if ( '' !== $subheading ) : ?>
						<<?php echo esc_html( $subheading_tag ); ?> class="mrn-video-row__subheading"><?php echo function_exists( 'mrn_base_stack_format_heading_inline_html' ) ? mrn_base_stack_format_heading_inline_html( $subheading ) : esc_html( $subheading ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></<?php echo esc_html( $subheading_tag ); ?>>
					<?php endif; ?>

					<?php if ( '' !== trim( $content ) ) : ?>
						<div class="mrn-video-row__text">
							<?php echo apply_filters( 'the_content', $content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</div>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php if ( '' !== $resolved_video_url ) : ?>
				<div
					class="mrn-layout-content mrn-layout-content--media mrn-video-row__media mrn-video-row__media--video-feature"
					data-video-src="<?php echo esc_url( $resolved_video_url ); ?>"
					data-video-kind="<?php echo esc_attr( $resolved_video_kind ); ?>"
					<?php if ( 'local' === $resolved_video_kind && '' !== $resolved_video_mime ) : ?>
						data-video-mime="<?php echo esc_attr( $resolved_video_mime ); ?>"
					<?php endif; ?>
					data-video-background="false"
					data-video-autoplay="false"
					data-video-muted="false"
					data-video-loop="false"
					data-video-controls="true"
					data-video-delay="250"
					aria-hidden="false"
				></div>
			<?php endif; ?>
			</div>
		</div>
	</div>
</section>
