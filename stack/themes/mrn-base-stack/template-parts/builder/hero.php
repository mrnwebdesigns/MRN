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
$heading_contract        = mrn_base_stack_get_hero_heading_contract( $row, $context_post_id, 'h2' );
$page_title              = isset( $heading_contract['page_title'] ) ? trim( (string) $heading_contract['page_title'] ) : '';
$heading                 = isset( $heading_contract['custom_heading'] ) ? trim( (string) $heading_contract['custom_heading'] ) : '';
$heading_tag             = isset( $heading_contract['custom_heading_tag'] ) ? strtolower( (string) $heading_contract['custom_heading_tag'] ) : 'h2';
$subheading              = isset( $row['subheading'] ) ? trim( (string) $row['subheading'] ) : '';
$subheading_tag          = isset( $row['subheading_tag'] ) ? strtolower( (string) $row['subheading_tag'] ) : 'p';
$content                 = isset( $row['content'] ) ? (string) $row['content'] : '';
$image                   = $row['image'] ?? null;
$background_image        = $row['background_image'] ?? null;
$background_video        = isset( $row['background_video'] ) ? (string) $row['background_video'] : '';
$background_video_upload = isset( $row['background_video_upload'] ) && is_array( $row['background_video_upload'] ) ? $row['background_video_upload'] : array();
$background_color        = isset( $row['background_color'] ) ? trim( (string) $row['background_color'] ) : '';
$bottom_accent           = ! empty( $row['bottom_accent'] );
$accent_slug             = isset( $row['bottom_accent_style'] ) ? (string) $row['bottom_accent_style'] : '';
$hero_min_height         = isset( $row['hero_min_height'] ) && is_scalar( $row['hero_min_height'] )
	? mrn_base_stack_sanitize_spacing_dimension_value( (string) $row['hero_min_height'] )
	: '';
$hero_vertical_padding   = isset( $row['hero_vertical_padding'] ) && is_scalar( $row['hero_vertical_padding'] )
	? mrn_base_stack_sanitize_spacing_dimension_value( (string) $row['hero_vertical_padding'] )
	: '';
$hero_content_alignment  = function_exists( 'mrn_base_stack_normalize_hero_content_alignment' )
	? mrn_base_stack_normalize_hero_content_alignment( $row['hero_content_alignment'] ?? '' )
	: 'left';
$hero_vertical_alignment = function_exists( 'mrn_base_stack_normalize_hero_vertical_alignment' )
	? mrn_base_stack_normalize_hero_vertical_alignment( $row['hero_vertical_alignment'] ?? '' )
	: 'center';
$section_width           = function_exists( 'mrn_base_stack_normalize_section_width' )
	? mrn_base_stack_normalize_section_width( $row['section_width'] ?? '', 'wide' )
	: 'wide';

$allowed_custom_heading_tags = array( 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'span', 'div' );
if ( ! in_array( $heading_tag, $allowed_custom_heading_tags, true ) ) {
	$heading_tag = 'h2';
}

$allowed_tags = array_merge( array( 'h1' ), $allowed_custom_heading_tags );
if ( ! in_array( $subheading_tag, $allowed_tags, true ) ) {
	$subheading_tag = 'p';
}

$links = function_exists( 'mrn_rbl_get_content_links' )
	? mrn_rbl_get_content_links(
		$row,
		array(
			'max'          => 4,
		)
	)
	: array();

$hero_links      = array();
$hero_link_slots = array( 'primary', 'secondary', 'tertiary', 'quaternary' );

foreach ( $links as $index => $hero_link_source ) {
	if ( ! is_array( $hero_link_source ) ) {
		continue;
	}

	$link_url           = isset( $hero_link_source['url'] ) ? (string) $hero_link_source['url'] : '';
	$link_text          = isset( $hero_link_source['text'] ) ? (string) $hero_link_source['text'] : '';
	$link_style         = isset( $hero_link_source['link_style'] ) && in_array( $hero_link_source['link_style'], array( 'link', 'button' ), true ) ? (string) $hero_link_source['link_style'] : 'link';
	$link_tag           = function_exists( 'mrn_rbl_get_content_link_tag_name' ) ? mrn_rbl_get_content_link_tag_name( $hero_link_source ) : 'a';
	$link_attr_html     = function_exists( 'mrn_rbl_get_content_link_html_attributes' ) ? mrn_rbl_get_content_link_html_attributes( $hero_link_source ) : '';
	$link_class_names   = 'mrn-hero__link mrn-hero__link--' . sanitize_html_class( $hero_link_slots[ $index ] ?? 'secondary' ) . ( 'button' === $link_style ? ' mrn-ui__link mrn-ui__link--button' : '' );
	$link_icon_markup   = function_exists( 'mrn_base_stack_get_button_link_icon_markup' )
		? mrn_base_stack_get_button_link_icon_markup( $hero_link_source )
		: '';
	$link_icon_position = function_exists( 'mrn_base_stack_get_button_link_icon_position' )
		? mrn_base_stack_get_button_link_icon_position( $hero_link_source )
		: 'left';

	if ( function_exists( 'mrn_rbl_get_content_link_custom_class_names' ) ) {
		$link_custom_classes = mrn_rbl_get_content_link_custom_class_names( $hero_link_source );
		if ( '' !== $link_custom_classes ) {
			$link_class_names .= ' ' . $link_custom_classes;
		}
	}

	$hero_links[] = array(
		'url'           => $link_url,
		'text'          => $link_text,
		'tag'           => $link_tag,
		'attr_html'     => $link_attr_html,
		'class_names'   => $link_class_names,
		'icon_markup'   => $link_icon_markup,
		'icon_position' => $link_icon_position,
	);
}
$has_image = function_exists( 'mrn_base_stack_image_has_content' ) ? mrn_base_stack_image_has_content( $image ) : false;

if ( '' === $label && '' === $page_title && '' === $heading && '' === $subheading && '' === trim( wp_strip_all_tags( $content ) ) && empty( $hero_links ) && ! $has_image ) {
	return;
}

$section_classes = array(
	'mrn-hero',
	'mrn-hero--default',
	'mrn-hero--width-' . sanitize_html_class( 'full-width' === $section_width ? 'full' : $section_width ),
	'mrn-hero--content-align-' . sanitize_html_class( $hero_content_alignment ),
	'mrn-hero--vertical-align-' . sanitize_html_class( $hero_vertical_alignment ),
	'mrn-content-builder__row--hero-content-align-' . sanitize_html_class( $hero_content_alignment ),
	'mrn-content-builder__row--hero-vertical-align-' . sanitize_html_class( $hero_vertical_alignment ),
);
$section_styles  = array();
$section_attrs   = array();

if ( '' !== $background_color && function_exists( 'mrn_site_colors_get_css_var' ) ) {
	$section_styles[] = '--mrn-hero-bg: var(' . mrn_site_colors_get_css_var( $background_color ) . ')';
}

$background_gradient_style = function_exists( 'mrn_base_stack_get_background_gradient_style_declaration' )
	? mrn_base_stack_get_background_gradient_style_declaration( $row, '--mrn-hero-bg-gradient' )
	: '';

if ( '' !== $background_gradient_style ) {
	$section_styles[]  = $background_gradient_style;
	$section_classes[] = 'has-background-gradient';
}

$background_image_markup = function_exists( 'mrn_base_stack_get_background_image_markup' )
	? mrn_base_stack_get_background_image_markup(
		$background_image,
		array(
			'class'         => 'mrn-row-background-media mrn-hero__background-image',
			'loading'       => 'eager',
			'fetchpriority' => 'high',
		)
	)
	: '';

if ( '' !== $background_image_markup ) {
	$section_classes[] = 'has-background-image';
	$section_classes[] = 'has-row-background-media';
}

$background_video_markup = function_exists( 'mrn_base_stack_get_background_video_markup' )
	? mrn_base_stack_get_background_video_markup(
		$background_video,
		$background_video_upload,
		array(
			'class'        => 'mrn-section-background-media mrn-row-background-video mrn-hero__background-media',
			'poster_image' => $background_image ? $background_image : $image,
		)
	)
	: '';

if ( '' !== $background_video_markup ) {
	$section_classes[] = 'has-background-video';
	$section_classes[] = 'has-row-background-video';
}

if ( $has_image ) {
	$section_classes[] = 'has-hero-media';
}

$display_contract = mrn_base_stack_get_builder_display_contract( $row, 'hero' );
$accent_contract  = function_exists( 'mrn_site_styles_get_bottom_accent_contract' )
	? mrn_site_styles_get_bottom_accent_contract( $bottom_accent, $accent_slug )
	: array(
		'classes'    => $bottom_accent ? array( 'has-bottom-accent' ) : array(),
		'attributes' => array(),
	);
$motion_contract  = function_exists( 'mrn_base_stack_get_builder_motion_contract' ) ? mrn_base_stack_get_builder_motion_contract( $row ) : array(
	'classes'    => array(),
	'attributes' => array(),
);

$section_classes = array_merge( $section_classes, $display_contract['classes'] );

if ( isset( $accent_contract['classes'] ) && is_array( $accent_contract['classes'] ) ) {
	$section_classes = array_merge( $section_classes, $accent_contract['classes'] );
}

if ( isset( $motion_contract['classes'] ) && is_array( $motion_contract['classes'] ) ) {
	$section_classes = array_merge( $section_classes, $motion_contract['classes'] );
}

$section_attrs = $display_contract['attributes'];

if ( isset( $accent_contract['attributes'] ) && is_array( $accent_contract['attributes'] ) ) {
	$section_attrs = function_exists( 'mrn_base_stack_merge_builder_attributes' )
		? mrn_base_stack_merge_builder_attributes( $section_attrs, $accent_contract['attributes'] )
		: array_merge( $section_attrs, $accent_contract['attributes'] );
}

$inner_styles = array();
if ( '' !== $hero_min_height ) {
	$inner_styles[] = 'min-height: ' . $hero_min_height;
}
if ( '' !== $hero_vertical_padding ) {
	$inner_styles[] = 'padding-block: ' . $hero_vertical_padding;
}
$inner_style = function_exists( 'mrn_base_stack_get_inline_style_attribute' ) ? mrn_base_stack_get_inline_style_attribute( $inner_styles ) : implode( '; ', $inner_styles );

if ( function_exists( 'mrn_base_stack_merge_builder_attributes' ) ) {
	$section_attrs = mrn_base_stack_merge_builder_attributes(
		$section_attrs,
		isset( $motion_contract['attributes'] ) && is_array( $motion_contract['attributes'] ) ? $motion_contract['attributes'] : array()
	);
} elseif ( isset( $motion_contract['attributes'] ) && is_array( $motion_contract['attributes'] ) ) {
	$section_attrs = array_merge( $section_attrs, $motion_contract['attributes'] );
}

$section_style = function_exists( 'mrn_base_stack_get_inline_style_attribute' ) ? mrn_base_stack_get_inline_style_attribute( $section_styles ) : implode( '; ', $section_styles );

if ( '' !== $section_style ) {
	if ( function_exists( 'mrn_base_stack_merge_builder_attributes' ) ) {
		$section_attrs = mrn_base_stack_merge_builder_attributes(
			$section_attrs,
			array(
				'style' => $section_style,
			)
		);
	} else {
		$existing_section_style = isset( $section_attrs['style'] ) && is_scalar( $section_attrs['style'] ) ? trim( (string) $section_attrs['style'] ) : '';
		$section_attrs['style'] = '' !== $existing_section_style ? $existing_section_style . '; ' . $section_style : $section_style;
	}
}

$section_attr_html = function_exists( 'mrn_base_stack_get_html_attributes' ) ? mrn_base_stack_get_html_attributes( $section_attrs ) : '';
echo function_exists( 'mrn_base_stack_get_builder_anchor_markup' ) ? mrn_base_stack_get_builder_anchor_markup( $row ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Anchor markup is escaped in the helper.
?>
<section class="<?php echo esc_attr( implode( ' ', $section_classes ) ); ?>"<?php echo '' !== $section_attr_html ? ' ' . $section_attr_html : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php echo $background_image_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Decorative image markup is escaped in the helper. ?>
	<?php echo $background_video_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Decorative video markup is escaped in the helper. ?>
	<div class="mrn-hero__inner"<?php echo '' !== $inner_style ? ' style="' . esc_attr( $inner_style ) . '"' : ''; ?>>
		<div class="mrn-hero__content mrn-hero__content--hero-shell">
			<?php if ( '' !== $label ) : ?>
				<<?php echo esc_html( $label_tag ); ?> class="mrn-hero__label"><?php echo function_exists( 'mrn_base_stack_format_heading_inline_html' ) ? mrn_base_stack_format_heading_inline_html( $label ) : esc_html( $label ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></<?php echo esc_html( $label_tag ); ?>>
			<?php endif; ?>

			<?php if ( '' !== $page_title ) : ?>
				<h1 class="mrn-hero__heading mrn-hero__page-title"><?php echo function_exists( 'mrn_base_stack_format_heading_inline_html' ) ? mrn_base_stack_format_heading_inline_html( $page_title ) : esc_html( $page_title ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></h1>
			<?php endif; ?>

			<?php if ( '' !== $heading ) : ?>
				<<?php echo esc_html( $heading_tag ); ?> class="mrn-hero__heading mrn-hero__heading--custom"><?php echo function_exists( 'mrn_base_stack_format_heading_inline_html' ) ? mrn_base_stack_format_heading_inline_html( $heading ) : esc_html( $heading ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></<?php echo esc_html( $heading_tag ); ?>>
			<?php endif; ?>

			<?php if ( '' !== $subheading ) : ?>
				<<?php echo esc_html( $subheading_tag ); ?> class="mrn-hero__subheading"><?php echo function_exists( 'mrn_base_stack_format_heading_inline_html' ) ? mrn_base_stack_format_heading_inline_html( $subheading ) : esc_html( $subheading ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></<?php echo esc_html( $subheading_tag ); ?>>
			<?php endif; ?>

			<?php if ( '' !== trim( $content ) ) : ?>
				<div class="mrn-hero__text">
					<?php echo apply_filters( 'the_content', $content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
			<?php endif; ?>

				<?php if ( ! empty( $hero_links ) ) : ?>
					<div class="mrn-hero__link-wrap">
						<?php foreach ( $hero_links as $hero_link ) : ?>
							<<?php echo esc_html( $hero_link['tag'] ); ?>
								class="<?php echo esc_attr( trim( (string) $hero_link['class_names'] ) ); ?>"
								<?php echo '' !== $hero_link['attr_html'] ? $hero_link['attr_html'] : 'href="' . esc_url( (string) $hero_link['url'] ) . '"'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							>
							<?php
							$hero_link_label = '' !== $hero_link['text'] ? (string) $hero_link['text'] : (string) $hero_link['url'];
								echo wp_kses_post(
									function_exists( 'mrn_base_stack_get_compact_link_label_markup' )
										? mrn_base_stack_get_compact_link_label_markup( $hero_link_label, (string) $hero_link['icon_markup'], (string) $hero_link['icon_position'] )
										: esc_html( $hero_link_label )
								);
							?>
							</<?php echo esc_html( $hero_link['tag'] ); ?>>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
		</div>

		<?php if ( $has_image ) : ?>
			<div class="mrn-hero__media mrn-hero__media--hero-shell">
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shared image helper returns escaped wp_get_attachment_image markup.
				echo function_exists( 'mrn_base_stack_get_attachment_image' ) ? mrn_base_stack_get_attachment_image(
					$image,
					'mrn-hero',
					array(
						'loading'       => false,
						'fetchpriority' => 'high',
					)
				) : '';
				?>
			</div>
		<?php endif; ?>
	</div>
</section>
