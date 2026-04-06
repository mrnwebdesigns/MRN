<?php
/**
 * Builder row: Basic.
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
$content          = isset( $row['content'] ) ? (string) $row['content'] : '';
$image            = isset( $row['image'] ) && is_array( $row['image'] ) ? $row['image'] : array();
$image_url        = isset( $image['url'] ) ? (string) $image['url'] : '';
$image_alt        = isset( $image['alt'] ) ? (string) $image['alt'] : '';
$background_image = isset( $row['background_image'] ) && is_array( $row['background_image'] ) ? $row['background_image'] : array();
$row_link         = isset( $row['link'] ) && is_array( $row['link'] ) ? $row['link'] : array();
$link_url         = isset( $row_link['url'] ) ? (string) $row_link['url'] : '';
$link_title       = isset( $row_link['title'] ) ? (string) $row_link['title'] : '';
$link_target      = isset( $row_link['target'] ) ? (string) $row_link['target'] : '';
$link_style       = isset( $row['link_style'] ) ? sanitize_key( (string) $row['link_style'] ) : 'link';
$link_color       = isset( $row['link_color'] ) ? trim( (string) $row['link_color'] ) : '';
$image_placement  = isset( $row['image_placement'] ) ? sanitize_key( (string) $row['image_placement'] ) : 'left';
$background_color = isset( $row['background_color'] ) ? trim( (string) $row['background_color'] ) : '';
$bottom_accent    = ! empty( $row['bottom_accent'] );
$accent_slug      = isset( $row['bottom_accent_style'] ) ? (string) $row['bottom_accent_style'] : '';
$width_layers     = function_exists( 'mrn_base_stack_get_section_width_layers' )
	? mrn_base_stack_get_section_width_layers( $row['section_width'] ?? '', 'wide', 'full-width' )
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

$section_styles = array();
if ( '' !== $background_color && function_exists( 'mrn_site_colors_get_css_var' ) ) {
	$section_styles[] = '--mrn-basic-row-bg: var(' . mrn_site_colors_get_css_var( $background_color ) . ')';
}

$background_image_style = function_exists( 'mrn_base_stack_get_background_image_style' )
	? mrn_base_stack_get_background_image_style( $background_image, '--mrn-basic-row-bg-image' )
	: '';

if ( '' !== $background_image_style ) {
	$section_styles[] = $background_image_style;
}

if ( ! in_array( $link_style, array( 'link', 'button' ), true ) ) {
	$link_style = 'link';
}

if ( ! in_array( $image_placement, array( 'left', 'right' ), true ) ) {
	$image_placement = 'left';
}

if ( '' !== $link_color && function_exists( 'mrn_site_colors_get_css_var' ) ) {
	$section_styles[] = '--mrn-basic-row-link-color: var(' . mrn_site_colors_get_css_var( $link_color ) . ')';
}

if ( '' === $label && '' === $heading && '' === $subheading && '' === trim( wp_strip_all_tags( $content ) ) && '' === $image_url && '' === $link_url ) {
	return;
}

$section_classes = array(
	'mrn-content-builder__row',
	'mrn-content-builder__row--basic',
	'mrn-content-builder__row--basic-link-' . sanitize_html_class( $link_style ),
	'mrn-content-builder__row--basic-image-' . sanitize_html_class( $image_placement ),
);

if ( '' !== $background_image_style ) {
	$section_classes[] = 'has-background-image';
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
	<div class="mrn-layout-section mrn-layout-section--basic <?php echo esc_attr( $width_layers['section_class'] ); ?><?php echo $is_full_width ? ' mrn-layout-surface' : ''; ?>"<?php echo $is_full_width && '' !== $surface_style ? ' style="' . esc_attr( $surface_style ) . '"' : ''; ?>>
		<div class="mrn-layout-container <?php echo esc_attr( $width_layers['container_class'] ); ?><?php echo ! $is_full_width ? ' mrn-layout-surface' : ''; ?>"<?php echo ! $is_full_width && '' !== $surface_style ? ' style="' . esc_attr( $surface_style ) . '"' : ''; ?>>
			<div class="mrn-layout-grid mrn-layout-grid--basic mrn-layout-grid--media-stack mrn-basic-row__inner">
			<?php if ( '' !== $image_url ) : ?>
				<div class="mrn-layout-content mrn-layout-content--media mrn-layout-content--media-stack-media mrn-basic-row__media">
					<img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $image_alt ); ?>" />
				</div>
			<?php endif; ?>

			<div class="mrn-layout-content mrn-layout-content--text mrn-layout-content--media-stack-text mrn-basic-row__content-wrap">
				<?php if ( '' !== $label ) : ?>
					<<?php echo esc_html( $label_tag ); ?> class="mrn-basic-row__label"><?php echo function_exists( 'mrn_base_stack_format_heading_inline_html' ) ? mrn_base_stack_format_heading_inline_html( $label ) : esc_html( $label ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></<?php echo esc_html( $label_tag ); ?>>
				<?php endif; ?>

				<?php if ( '' !== $heading ) : ?>
					<<?php echo esc_html( $heading_tag ); ?> class="mrn-basic-row__heading"><?php echo function_exists( 'mrn_base_stack_format_heading_inline_html' ) ? mrn_base_stack_format_heading_inline_html( $heading ) : esc_html( $heading ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></<?php echo esc_html( $heading_tag ); ?>>
				<?php endif; ?>

				<?php if ( '' !== $subheading ) : ?>
					<<?php echo esc_html( $subheading_tag ); ?> class="mrn-basic-row__subheading"><?php echo function_exists( 'mrn_base_stack_format_heading_inline_html' ) ? mrn_base_stack_format_heading_inline_html( $subheading ) : esc_html( $subheading ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></<?php echo esc_html( $subheading_tag ); ?>>
				<?php endif; ?>

				<?php if ( '' !== trim( $content ) ) : ?>
					<div class="mrn-basic-row__content">
						<?php echo apply_filters( 'the_content', $content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
				<?php endif; ?>

				<?php if ( '' !== $link_url ) : ?>
					<div class="mrn-basic-row__link-wrap">
						<a
							class="mrn-basic-row__link <?php echo 'button' === $link_style ? 'mrn-basic-row__link--button' : 'mrn-basic-row__link--text'; ?>"
							href="<?php echo esc_url( $link_url ); ?>"
							<?php if ( '' !== $link_target ) : ?>
								target="<?php echo esc_attr( $link_target ); ?>"
							<?php endif; ?>
							<?php if ( '_blank' === $link_target ) : ?>
								rel="noopener noreferrer"
							<?php endif; ?>
						>
							<?php echo esc_html( '' !== $link_title ? $link_title : $link_url ); ?>
						</a>
					</div>
				<?php endif; ?>
			</div>
			</div>
		</div>
	</div>
</section>
