<?php
/**
 * Builder row: Image content.
 *
 * @package mrn-base-stack
 */

$context           = is_array( $args ?? null ) ? $args : array();
$row               = isset( $context['row'] ) && is_array( $context['row'] ) ? $context['row'] : array();
$label             = isset( $row['label'] ) ? trim( (string) $row['label'] ) : '';
$label_tag         = function_exists( 'mrn_base_stack_normalize_text_tag' ) ? mrn_base_stack_normalize_text_tag( $row['label_tag'] ?? '', 'p' ) : 'p';
$heading           = isset( $row['heading'] ) ? trim( (string) $row['heading'] ) : '';
$heading_tag       = isset( $row['heading_tag'] ) ? strtolower( (string) $row['heading_tag'] ) : 'h2';
$subheading        = isset( $row['subheading'] ) ? trim( (string) $row['subheading'] ) : '';
$subheading_tag    = isset( $row['subheading_tag'] ) ? strtolower( (string) $row['subheading_tag'] ) : 'p';
$content           = isset( $row['content'] ) ? (string) $row['content'] : '';
$image             = isset( $row['image'] ) && is_array( $row['image'] ) ? $row['image'] : array();
$background_color  = isset( $row['background_color'] ) ? trim( (string) $row['background_color'] ) : '';
$bottom_accent     = ! empty( $row['bottom_accent'] );
$accent_slug       = isset( $row['bottom_accent_style'] ) ? (string) $row['bottom_accent_style'] : '';
$legacy_full_width = ! empty( $row['full_width'] );
$image_position    = isset( $row['image_position'] ) ? sanitize_key( (string) $row['image_position'] ) : 'top';
$image_size        = isset( $row['image_size'] ) ? sanitize_key( (string) $row['image_size'] ) : 'contained';
$image_alignment   = isset( $row['image_alignment'] ) ? sanitize_key( (string) $row['image_alignment'] ) : 'center';
$width_value       = $row['section_width'] ?? ( $legacy_full_width ? 'full-width' : '' );
$width_layers      = function_exists( 'mrn_base_stack_get_section_width_layers' )
	? mrn_base_stack_get_section_width_layers( $width_value, 'wide', 'full-width' )
	: array(
		'width'           => $legacy_full_width ? 'full-width' : 'wide',
		'section_class'   => $legacy_full_width ? 'mrn-layout-section--full' : 'mrn-layout-section--contained',
		'container_class' => $legacy_full_width ? 'mrn-layout-container--full' : 'mrn-layout-container--wide',
	);

$allowed_tags = array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'span', 'div' );
if ( ! in_array( $heading_tag, $allowed_tags, true ) ) {
	$heading_tag = 'h2';
}

if ( ! in_array( $subheading_tag, $allowed_tags, true ) ) {
	$subheading_tag = 'p';
}

if ( ! in_array( $image_position, array( 'top', 'bottom' ), true ) ) {
	$image_position = 'top';
}

if ( ! in_array( $image_size, array( 'contained', 'cover' ), true ) ) {
	$image_size = 'contained';
}

if ( ! in_array( $image_alignment, array( 'left', 'center', 'right' ), true ) ) {
	$image_alignment = 'center';
}

$has_image = ! empty( $image['ID'] ) || ! empty( $image['url'] );
$links     = function_exists( 'mrn_rbl_get_content_links' )
	? mrn_rbl_get_content_links(
		$row,
		array(
			'max'          => 1,
		)
	)
	: array();
$content_link = isset( $links[0] ) && is_array( $links[0] ) ? $links[0] : array();

$link_url           = isset( $content_link['url'] ) ? (string) $content_link['url'] : '';
$link_text          = isset( $content_link['text'] ) ? (string) $content_link['text'] : '';
$link_style         = isset( $content_link['link_style'] ) && in_array( $content_link['link_style'], array( 'link', 'button' ), true ) ? (string) $content_link['link_style'] : 'link';
$link_tag           = function_exists( 'mrn_rbl_get_content_link_tag_name' ) ? mrn_rbl_get_content_link_tag_name( $content_link ) : 'a';
$link_attr_html     = function_exists( 'mrn_rbl_get_content_link_html_attributes' ) ? mrn_rbl_get_content_link_html_attributes( $content_link ) : '';
$link_class_names   = 'mrn-ui__link ' . ( 'button' === $link_style ? 'mrn-ui__link--button' : 'mrn-ui__link--text' );
$link_icon_markup   = 'button' === $link_style && function_exists( 'mrn_base_stack_get_button_link_icon_markup' )
	? mrn_base_stack_get_button_link_icon_markup( $content_link )
	: '';
$link_icon_position = 'button' === $link_style && function_exists( 'mrn_base_stack_get_button_link_icon_position' )
	? mrn_base_stack_get_button_link_icon_position( $content_link )
	: 'left';

if ( function_exists( 'mrn_rbl_get_content_link_custom_class_names' ) ) {
	$link_custom_classes = mrn_rbl_get_content_link_custom_class_names( $content_link );
	if ( '' !== $link_custom_classes ) {
		$link_class_names .= ' ' . $link_custom_classes;
	}
}
if ( '' === $label && '' === $heading && '' === $subheading && '' === trim( wp_strip_all_tags( $content ) ) && '' === $link_url && ! $has_image ) {
	return;
}

$section_classes = array(
	'mrn-content-builder__row',
	'mrn-content-builder__row--image-content',
	'mrn-content-builder__row--image-content-position-' . sanitize_html_class( $image_position ),
	'mrn-content-builder__row--image-content-size-' . sanitize_html_class( $image_size ),
	'mrn-content-builder__row--image-content-align-' . sanitize_html_class( $image_alignment ),
);

$section_styles = array();
if ( '' !== $background_color && function_exists( 'mrn_site_colors_get_css_var' ) ) {
	$section_styles[] = '--mrn-image-content-row-bg: var(' . mrn_site_colors_get_css_var( $background_color ) . ')';
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
	<div class="mrn-layout-section mrn-layout-section--image-content <?php echo esc_attr( $width_layers['section_class'] ); ?><?php echo $is_full_width ? ' mrn-layout-surface' : ''; ?>"<?php echo $is_full_width && '' !== $surface_style ? ' style="' . esc_attr( $surface_style ) . '"' : ''; ?>>
		<div class="mrn-layout-container <?php echo esc_attr( $width_layers['container_class'] ); ?><?php echo ! $is_full_width ? ' mrn-layout-surface' : ''; ?>"<?php echo ! $is_full_width && '' !== $surface_style ? ' style="' . esc_attr( $surface_style ) . '"' : ''; ?>>
			<div class="mrn-layout-grid mrn-layout-grid--image-content mrn-layout-grid--media-stack mrn-image-content-row__inner">
				<div class="mrn-layout-content mrn-layout-content--text mrn-layout-content--media-stack-text mrn-image-content-row__content mrn-ui__body">
					<div class="mrn-image-content-row__content-inner">
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

					<?php if ( '' !== trim( $content ) ) : ?>
							<div class="mrn-image-content-row__text mrn-ui__text">
							<?php echo apply_filters( 'the_content', $content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</div>
					<?php endif; ?>

					<?php if ( '' !== $link_url ) : ?>
							<div class="mrn-image-content-row__link-wrap">
								<<?php echo esc_html( $link_tag ); ?>
									class="<?php echo esc_attr( trim( $link_class_names ) ); ?>"
									<?php echo '' !== $link_attr_html ? $link_attr_html : 'href="' . esc_url( $link_url ) . '"'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								>
								<?php if ( 'left' === $link_icon_position ) : ?>
									<?php echo $link_icon_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icon markup is escaped in the helper. ?>
								<?php endif; ?>
								<?php echo esc_html( '' !== $link_text ? $link_text : $link_url ); ?>
								<?php if ( 'right' === $link_icon_position ) : ?>
									<?php echo $link_icon_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icon markup is escaped in the helper. ?>
								<?php endif; ?>
							</<?php echo esc_html( $link_tag ); ?>>
						</div>
					<?php endif; ?>
				</div>
			</div>

			<?php if ( $has_image ) : ?>
					<div class="mrn-layout-content mrn-layout-content--media mrn-layout-content--media-stack-media mrn-image-content-row__media mrn-ui__media">
					<?php if ( ! empty( $image['ID'] ) ) : ?>
						<?php echo wp_get_attachment_image( (int) $image['ID'], 'large' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php else : ?>
						<img src="<?php echo esc_url( $image['url'] ); ?>" alt="<?php echo esc_attr( $image['alt'] ?? '' ); ?>">
					<?php endif; ?>
				</div>
			<?php endif; ?>
			</div>
		</div>
	</div>
</section>
