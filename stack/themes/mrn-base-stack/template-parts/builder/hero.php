<?php
/**
 * Builder row: Hero.
 *
 * @package mrn-base-stack
 */

$context                 = is_array( $args ?? null ) ? $args : array();
$row                     = isset( $context['row'] ) && is_array( $context['row'] ) ? $context['row'] : array();
$context_post_id         = isset( $context['post_id'] ) ? (int) $context['post_id'] : get_the_ID();
$label                   = isset( $row['label'] ) ? trim( (string) $row['label'] ) : '';
$label_tag               = function_exists( 'mrn_base_stack_normalize_text_tag' ) ? mrn_base_stack_normalize_text_tag( $row['label_tag'] ?? '', 'p' ) : 'p';
$heading                 = isset( $row['heading'] ) ? trim( (string) $row['heading'] ) : '';
$heading_tag             = isset( $row['heading_tag'] ) ? strtolower( (string) $row['heading_tag'] ) : 'h1';
$subheading              = isset( $row['subheading'] ) ? trim( (string) $row['subheading'] ) : '';
$subheading_tag          = isset( $row['subheading_tag'] ) ? strtolower( (string) $row['subheading_tag'] ) : 'p';
$content                 = isset( $row['content'] ) ? (string) $row['content'] : '';
$primary_link            = isset( $row['primary_link'] ) && is_array( $row['primary_link'] ) ? $row['primary_link'] : ( isset( $row['link'] ) && is_array( $row['link'] ) ? $row['link'] : array() );
$secondary_link          = isset( $row['secondary_link'] ) && is_array( $row['secondary_link'] ) ? $row['secondary_link'] : array();
$image                   = isset( $row['image'] ) && is_array( $row['image'] ) ? $row['image'] : array();
$background_image        = isset( $row['background_image'] ) && is_array( $row['background_image'] ) ? $row['background_image'] : array();
$background_video        = isset( $row['background_video'] ) ? (string) $row['background_video'] : '';
$background_video_upload = isset( $row['background_video_upload'] ) && is_array( $row['background_video_upload'] ) ? $row['background_video_upload'] : array();
$background_color        = isset( $row['background_color'] ) ? trim( (string) $row['background_color'] ) : '';
$bottom_accent           = ! empty( $row['bottom_accent'] );
$accent_slug             = isset( $row['bottom_accent_style'] ) ? (string) $row['bottom_accent_style'] : '';

if ( '' === $heading && $context_post_id ) {
	$heading = get_the_title( $context_post_id );
}

$allowed_tags = array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'span', 'div' );
if ( ! in_array( $heading_tag, $allowed_tags, true ) ) {
	$heading_tag = 'h1';
}

if ( ! in_array( $subheading_tag, $allowed_tags, true ) ) {
	$subheading_tag = 'p';
}

$primary_link_url      = isset( $primary_link['url'] ) ? (string) $primary_link['url'] : '';
$primary_link_title    = isset( $primary_link['title'] ) ? (string) $primary_link['title'] : '';
$primary_link_target   = isset( $primary_link['target'] ) ? (string) $primary_link['target'] : '';
$secondary_link_url    = isset( $secondary_link['url'] ) ? (string) $secondary_link['url'] : '';
$secondary_link_title  = isset( $secondary_link['title'] ) ? (string) $secondary_link['title'] : '';
$secondary_link_target = isset( $secondary_link['target'] ) ? (string) $secondary_link['target'] : '';
$image_url             = isset( $image['url'] ) ? (string) $image['url'] : '';
$image_alt             = isset( $image['alt'] ) ? (string) $image['alt'] : '';
$video_embed           = function_exists( 'mrn_base_stack_get_video_embed' ) ? mrn_base_stack_get_video_embed(
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
$video_url             = isset( $video_embed['embed_url'] ) ? (string) $video_embed['embed_url'] : '';
$local_video_url       = isset( $background_video_upload['url'] ) ? (string) $background_video_upload['url'] : '';
$local_video_mime      = isset( $background_video_upload['mime_type'] ) ? (string) $background_video_upload['mime_type'] : '';
$video_kind            = '';

if ( '' !== $local_video_url ) {
	$video_kind = 'local';
	$video_url  = $local_video_url;
} elseif ( '' !== $video_url ) {
	$video_kind = 'remote';
}

if ( '' === $label && '' === $heading && '' === $subheading && '' === trim( wp_strip_all_tags( $content ) ) && '' === $primary_link_url && '' === $secondary_link_url && '' === $image_url ) {
	return;
}

$section_classes = array(
	'mrn-hero',
	'mrn-hero--default',
);
$section_styles  = array();
$section_attrs   = array();

if ( '' !== $background_color && function_exists( 'mrn_site_colors_get_css_var' ) ) {
	$section_styles[] = '--mrn-hero-bg: var(' . mrn_site_colors_get_css_var( $background_color ) . ')';
}

$background_image_style = function_exists( 'mrn_base_stack_get_background_image_style' )
	? mrn_base_stack_get_background_image_style( $background_image, '--mrn-hero-bg-image' )
	: '';

if ( '' !== $background_image_style ) {
	$section_styles[]  = $background_image_style;
	$section_classes[] = 'has-background-image';
}

if ( '' !== $video_url ) {
	$section_classes[] = 'has-background-video';
}

if ( '' !== $image_url ) {
	$section_classes[] = 'has-hero-media';
}

$accent_contract = function_exists( 'mrn_site_styles_get_bottom_accent_contract' )
	? mrn_site_styles_get_bottom_accent_contract( $bottom_accent, $accent_slug )
	: array(
		'classes'    => $bottom_accent ? array( 'has-bottom-accent' ) : array(),
		'attributes' => array(),
	);
$motion_contract = function_exists( 'mrn_base_stack_get_builder_motion_contract' ) ? mrn_base_stack_get_builder_motion_contract( $row ) : array(
	'classes'    => array(),
	'attributes' => array(),
);

if ( isset( $accent_contract['classes'] ) && is_array( $accent_contract['classes'] ) ) {
	$section_classes = array_merge( $section_classes, $accent_contract['classes'] );
}

if ( isset( $motion_contract['classes'] ) && is_array( $motion_contract['classes'] ) ) {
	$section_classes = array_merge( $section_classes, $motion_contract['classes'] );
}

if ( isset( $accent_contract['attributes'] ) && is_array( $accent_contract['attributes'] ) ) {
	$section_attrs = $accent_contract['attributes'];
}

if ( function_exists( 'mrn_base_stack_merge_builder_attributes' ) ) {
	$section_attrs = mrn_base_stack_merge_builder_attributes(
		$section_attrs,
		isset( $motion_contract['attributes'] ) && is_array( $motion_contract['attributes'] ) ? $motion_contract['attributes'] : array()
	);
} elseif ( isset( $motion_contract['attributes'] ) && is_array( $motion_contract['attributes'] ) ) {
	$section_attrs = array_merge( $section_attrs, $motion_contract['attributes'] );
}

$section_attr_html = function_exists( 'mrn_base_stack_get_html_attributes' ) ? mrn_base_stack_get_html_attributes( $section_attrs ) : '';
echo function_exists( 'mrn_base_stack_get_builder_anchor_markup' ) ? mrn_base_stack_get_builder_anchor_markup( $row ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Anchor markup is escaped in the helper.
?>
<section class="<?php echo esc_attr( implode( ' ', $section_classes ) ); ?>"<?php echo '' !== $section_attr_html ? ' ' . $section_attr_html : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo ! empty( $section_styles ) ? ' style="' . esc_attr( implode( '; ', $section_styles ) ) . '"' : ''; ?>>
	<?php if ( '' !== $video_url ) : ?>
		<div class="mrn-section-background-media mrn-hero__background-media" data-video-src="<?php echo esc_url( $video_url ); ?>" data-video-kind="<?php echo esc_attr( $video_kind ); ?>"
		<?php
		if ( 'local' === $video_kind && '' !== $local_video_mime ) :
			?>
			data-video-mime="<?php echo esc_attr( $local_video_mime ); ?>"<?php endif; ?>
			<?php
			if ( '' !== $image_url ) :
				?>
			data-video-poster="<?php echo esc_url( $image_url ); ?>"<?php endif; ?> data-video-background="true" data-video-autoplay="true" data-video-muted="true" data-video-loop="true" data-video-controls="false" data-video-delay="2000" data-video-desktop-only="true" aria-hidden="true"></div>
	<?php endif; ?>
	<div class="mrn-hero__inner">
		<div class="mrn-hero__content mrn-hero__content--hero-shell">
			<?php if ( '' !== $label ) : ?>
				<<?php echo esc_html( $label_tag ); ?> class="mrn-hero__label"><?php echo function_exists( 'mrn_base_stack_format_heading_inline_html' ) ? mrn_base_stack_format_heading_inline_html( $label ) : esc_html( $label ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></<?php echo esc_html( $label_tag ); ?>>
			<?php endif; ?>

			<?php if ( '' !== $heading ) : ?>
				<<?php echo esc_html( $heading_tag ); ?> class="mrn-hero__heading"><?php echo function_exists( 'mrn_base_stack_format_heading_inline_html' ) ? mrn_base_stack_format_heading_inline_html( $heading ) : esc_html( $heading ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></<?php echo esc_html( $heading_tag ); ?>>
			<?php endif; ?>

			<?php if ( '' !== $subheading ) : ?>
				<<?php echo esc_html( $subheading_tag ); ?> class="mrn-hero__subheading"><?php echo function_exists( 'mrn_base_stack_format_heading_inline_html' ) ? mrn_base_stack_format_heading_inline_html( $subheading ) : esc_html( $subheading ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></<?php echo esc_html( $subheading_tag ); ?>>
			<?php endif; ?>

			<?php if ( '' !== trim( $content ) ) : ?>
				<div class="mrn-hero__text">
					<?php echo apply_filters( 'the_content', $content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
			<?php endif; ?>

			<?php if ( '' !== $primary_link_url || '' !== $secondary_link_url ) : ?>
				<div class="mrn-hero__link-wrap">
					<?php if ( '' !== $primary_link_url ) : ?>
						<a
							class="mrn-hero__link mrn-hero__link--primary"
							href="<?php echo esc_url( $primary_link_url ); ?>"
							<?php if ( '' !== $primary_link_target ) : ?>
								target="<?php echo esc_attr( $primary_link_target ); ?>"
							<?php endif; ?>
							<?php if ( '_blank' === $primary_link_target ) : ?>
								rel="noopener noreferrer"
							<?php endif; ?>
						>
							<?php echo esc_html( '' !== $primary_link_title ? $primary_link_title : $primary_link_url ); ?>
						</a>
					<?php endif; ?>
					<?php if ( '' !== $secondary_link_url ) : ?>
						<a
							class="mrn-hero__link mrn-hero__link--secondary"
							href="<?php echo esc_url( $secondary_link_url ); ?>"
							<?php if ( '' !== $secondary_link_target ) : ?>
								target="<?php echo esc_attr( $secondary_link_target ); ?>"
							<?php endif; ?>
							<?php if ( '_blank' === $secondary_link_target ) : ?>
								rel="noopener noreferrer"
							<?php endif; ?>
						>
							<?php echo esc_html( '' !== $secondary_link_title ? $secondary_link_title : $secondary_link_url ); ?>
						</a>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>

		<?php if ( '' !== $image_url ) : ?>
			<div class="mrn-hero__media mrn-hero__media--hero-shell">
				<img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $image_alt ); ?>" />
			</div>
		<?php endif; ?>
	</div>
</section>
