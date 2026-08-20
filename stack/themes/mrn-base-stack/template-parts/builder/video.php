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
$video_position      = function_exists( 'mrn_base_stack_normalize_video_position' ) ? mrn_base_stack_normalize_video_position( $row['video_position'] ?? '' ) : sanitize_key( (string) ( $row['video_position'] ?? 'bottom' ) );
$video_aspect_ratio  = function_exists( 'mrn_base_stack_normalize_video_aspect_ratio' ) ? mrn_base_stack_normalize_video_aspect_ratio( $row['video_aspect_ratio'] ?? '' ) : sanitize_key( (string) ( $row['video_aspect_ratio'] ?? '16-9' ) );
$display_mode        = function_exists( 'mrn_base_stack_normalize_video_display_mode' ) ? mrn_base_stack_normalize_video_display_mode( $row['video_display_mode'] ?? '' ) : sanitize_key( (string) ( $row['video_display_mode'] ?? 'inline' ) );
$thumbnail_image     = isset( $row['video_thumbnail'] ) ? $row['video_thumbnail'] : 0;
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

$has_text_content = '' !== $label || '' !== $heading || '' !== $subheading || '' !== trim( wp_strip_all_tags( $content ) );
$has_video_media  = '' !== $resolved_video_url;
$has_thumbnail    = function_exists( 'mrn_base_stack_image_has_content' ) ? mrn_base_stack_image_has_content( $thumbnail_image ) : ! empty( $thumbnail_image );
$is_modal_mode    = 'modal' === $display_mode && $has_video_media && $has_thumbnail;
$remote_provider  = isset( $remote_video_embed['provider'] ) ? (string) $remote_video_embed['provider'] : '';
$modal_lightbox_type = 'google_drive' === $remote_provider ? 'iframe' : 'video';
$modal_href       = 'local' === $resolved_video_kind ? $local_video_url : ( 'google_drive' === $remote_provider ? $remote_video_url : $remote_video );

if ( ! $has_text_content && ! $has_video_media ) {
	return;
}

$aspect_ratio_css = array(
	'16-9' => '16 / 9',
	'4-3'  => '4 / 3',
	'1-1'  => '1 / 1',
	'21-9' => '21 / 9',
);
$video_title      = '' !== $heading ? wp_strip_all_tags( $heading ) : __( 'Embedded video', 'mrn-base-stack' );
$section_classes  = array(
	'mrn-content-builder__row',
	'mrn-content-builder__row--video',
	'mrn-content-builder__row--video-position-' . sanitize_html_class( $video_position ),
	'mrn-content-builder__row--video-ratio-' . sanitize_html_class( $video_aspect_ratio ),
	'mrn-content-builder__row--video-display-' . sanitize_html_class( $is_modal_mode ? 'modal' : 'inline' ),
);
$section_styles   = array();

if ( $has_text_content ) {
	$section_classes[] = 'has-video-content';
}

if ( $has_video_media ) {
	$section_classes[] = 'has-video-media';
}

if ( '' !== $background_color && function_exists( 'mrn_site_colors_get_css_var' ) ) {
	$section_styles[] = '--mrn-video-row-bg: var(' . mrn_site_colors_get_css_var( $background_color ) . ')';
}

$section_styles[] = '--mrn-video-aspect-ratio: ' . $aspect_ratio_css[ $video_aspect_ratio ];

$display_contract  = function_exists( 'mrn_base_stack_get_builder_display_contract' ) ? mrn_base_stack_get_builder_display_contract( $row, 'video' ) : array(
	'classes'    => array(),
	'attributes' => array(),
);
$accent_contract   = function_exists( 'mrn_base_stack_get_builder_accent_contract' ) ? mrn_base_stack_get_builder_accent_contract( $bottom_accent, $accent_slug ) : array(
	'classes'    => $bottom_accent ? array( 'has-bottom-accent' ) : array(),
	'attributes' => array(),
);
$motion_contract   = function_exists( 'mrn_base_stack_get_builder_motion_contract' ) ? mrn_base_stack_get_builder_motion_contract( $row ) : array(
	'classes'    => array(),
	'attributes' => array(),
);
$section_classes   = function_exists( 'mrn_base_stack_merge_builder_section_classes' ) ? mrn_base_stack_merge_builder_section_classes( $section_classes, $display_contract ) : $section_classes;
$section_classes   = function_exists( 'mrn_base_stack_merge_builder_section_classes' ) ? mrn_base_stack_merge_builder_section_classes( $section_classes, $accent_contract ) : $section_classes;
$section_classes   = function_exists( 'mrn_base_stack_merge_builder_section_classes' ) ? mrn_base_stack_merge_builder_section_classes( $section_classes, $motion_contract ) : $section_classes;
$section_attrs     = isset( $display_contract['attributes'] ) && is_array( $display_contract['attributes'] ) ? $display_contract['attributes'] : array();
$section_attrs     = function_exists( 'mrn_base_stack_merge_builder_attributes' ) ? mrn_base_stack_merge_builder_attributes( $section_attrs, isset( $accent_contract['attributes'] ) && is_array( $accent_contract['attributes'] ) ? $accent_contract['attributes'] : array() ) : array_merge( $section_attrs, isset( $accent_contract['attributes'] ) && is_array( $accent_contract['attributes'] ) ? $accent_contract['attributes'] : array() );
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
			<?php if ( $has_text_content ) : ?>
					<div class="mrn-layout-content mrn-layout-content--text mrn-video-row__content mrn-video-row__content--video-feature mrn-ui__body">
					<?php if ( '' !== $label || '' !== $heading || '' !== $subheading ) : ?>
							<div class="mrn-video-row__header mrn-video-row__header--video-feature mrn-ui__head">
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

					<?php if ( '' !== trim( $content ) ) : ?>
							<div class="mrn-video-row__text mrn-ui__text">
							<?php echo apply_filters( 'the_content', $content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</div>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php if ( $is_modal_mode ) : ?>
				<div class="mrn-layout-content mrn-layout-content--media mrn-video-row__media mrn-video-row__media--video-feature mrn-video-row__media--modal mrn-ui__media">
					<a
						class="mrn-video-row__trigger glightbox"
						href="<?php echo esc_url( $modal_href ); ?>"
						data-type="<?php echo esc_attr( $modal_lightbox_type ); ?>"
						aria-label="<?php echo esc_attr( sprintf( /* translators: %s: video heading. */ __( 'Play video: %s', 'mrn-base-stack' ), $video_title ) ); ?>"
					>
						<?php echo function_exists( 'mrn_base_stack_get_attachment_image' ) ? mrn_base_stack_get_attachment_image( $thumbnail_image, 'mrn-content-media', array( 'class' => 'mrn-video-row__thumbnail' ) ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<span class="mrn-video-row__play" aria-hidden="true">
							<svg viewBox="0 0 24 24" focusable="false"><path d="M8 5v14l11-7z"></path></svg>
						</span>
					</a>
				</div>
			<?php elseif ( $has_video_media ) : ?>
				<div
						class="mrn-layout-content mrn-layout-content--media mrn-video-row__media mrn-video-row__media--video-feature mrn-ui__media"
					data-video-src="<?php echo esc_url( $resolved_video_url ); ?>"
					data-video-kind="<?php echo esc_attr( $resolved_video_kind ); ?>"
					data-video-title="<?php echo esc_attr( $video_title ); ?>"
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
