<?php
/**
 * Basic Block template.
 *
 * Theme override path:
 * wp-content/themes/{active-theme}/mrn-blocks/basic-block.php
 *
 * Available context:
 * - $context['post']
 * - $context['post_id']
 * - $context['fields']
 * - $context['block_name']
 */

if ( ! isset( $context ) || ! is_array( $context ) ) {
	return;
}

$block_post     = isset( $context['post'] ) && $context['post'] instanceof WP_Post ? $context['post'] : null;
$block_post_id  = isset( $context['post_id'] ) ? (int) $context['post_id'] : 0;
$post_name      = isset( $context['post_name'] ) ? (string) $context['post_name'] : ( $block_post instanceof WP_Post ? (string) $block_post->post_name : '' );
$fields         = isset( $context['fields'] ) && is_array( $context['fields'] ) ? $context['fields'] : array();
$label          = isset( $fields['label'] ) ? trim( (string) $fields['label'] ) : '';
$label_tag      = function_exists( 'mrn_rbl_normalize_text_tag' ) ? mrn_rbl_normalize_text_tag( $fields['label_tag'] ?? '', 'p' ) : 'p';
$heading        = isset( $fields['heading'] ) ? trim( (string) $fields['heading'] ) : '';
$heading_tag    = isset( $fields['heading_tag'] ) ? strtolower( (string) $fields['heading_tag'] ) : 'h2';
$subheading     = isset( $fields['subheading'] ) ? trim( (string) $fields['subheading'] ) : '';
$subheading_tag = isset( $fields['subheading_tag'] ) ? strtolower( (string) $fields['subheading_tag'] ) : 'p';
$content      = isset( $fields['content'] ) ? (string) $fields['content'] : '';
$image        = isset( $fields['image'] ) && is_array( $fields['image'] ) ? $fields['image'] : array();
$image_url    = isset( $image['url'] ) ? (string) $image['url'] : '';
$image_alt    = isset( $image['alt'] ) ? (string) $image['alt'] : '';
$bg_color     = isset( $fields['bg_color'] ) ? (string) $fields['bg_color'] : '';
$link_color   = isset( $fields['link_color'] ) ? (string) $fields['link_color'] : '';
$image_place  = isset( $fields['image_placement'] ) ? sanitize_key( (string) $fields['image_placement'] ) : 'left';
$accent       = ! empty( $fields['bottom_accent'] );
$accent_slug  = isset( $fields['bottom_accent_style'] ) ? (string) $fields['bottom_accent_style'] : '';

$allowed_tags = array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'div', 'span' );
if ( ! in_array( $heading_tag, $allowed_tags, true ) ) {
	$heading_tag = 'h2';
}
if ( ! in_array( $subheading_tag, $allowed_tags, true ) ) {
	$subheading_tag = 'p';
}

$links = function_exists( 'mrn_rbl_get_content_links' )
	? mrn_rbl_get_content_links(
		$fields,
		array(
			'max' => 1,
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

if ( '' === $label && '' === $heading && '' === $subheading && '' === trim( wp_strip_all_tags( $content ) ) && '' === $image_url && '' === $link_url ) {
	return;
}

if ( ! in_array( $image_place, array( 'left', 'right' ), true ) ) {
	$image_place = 'left';
}

$classes = array(
	'mrn-reusable-block',
	'mrn-reusable-block--basic',
	'mrn-reusable-block--basic-link-' . sanitize_html_class( $link_style ),
	'mrn-reusable-block--basic-image-' . sanitize_html_class( $image_place ),
);

$accent_contract = function_exists( 'mrn_site_styles_get_bottom_accent_contract' )
	? mrn_site_styles_get_bottom_accent_contract( $accent, $accent_slug )
	: array(
		'classes'    => $accent ? array( 'has-bottom-accent' ) : array(),
		'attributes' => array(),
	);

if ( isset( $accent_contract['classes'] ) && is_array( $accent_contract['classes'] ) ) {
	$classes = array_merge( $classes, $accent_contract['classes'] );
}

$motion_contract = function_exists( 'mrn_rbl_get_motion_contract' ) ? mrn_rbl_get_motion_contract( $fields, $context ) : array(
	'classes'    => array(),
	'attributes' => array(),
);

if ( isset( $motion_contract['classes'] ) && is_array( $motion_contract['classes'] ) ) {
	$classes = array_merge( $classes, $motion_contract['classes'] );
}

$inline_styles = array();

if ( '' !== $bg_color && function_exists( 'mrn_site_colors_get_css_var' ) ) {
	$inline_styles[] = '--mrn-basic-block-bg-color: var(' . mrn_site_colors_get_css_var( $bg_color ) . ')';
}

if ( '' !== $link_color && function_exists( 'mrn_site_colors_get_css_var' ) ) {
	$inline_styles[] = '--mrn-basic-block-link-color: var(' . mrn_site_colors_get_css_var( $link_color ) . ')';
}

$section_attrs     = isset( $accent_contract['attributes'] ) && is_array( $accent_contract['attributes'] ) ? $accent_contract['attributes'] : array();
$section_attrs     = function_exists( 'mrn_rbl_merge_attributes' ) ? mrn_rbl_merge_attributes( $section_attrs, isset( $motion_contract['attributes'] ) && is_array( $motion_contract['attributes'] ) ? $motion_contract['attributes'] : array() ) : array_merge( $section_attrs, isset( $motion_contract['attributes'] ) && is_array( $motion_contract['attributes'] ) ? $motion_contract['attributes'] : array() );
$section_attr_html = function_exists( 'mrn_rbl_get_html_attributes' ) ? mrn_rbl_get_html_attributes( $section_attrs ) : '';

echo function_exists( 'mrn_rbl_get_anchor_markup' ) ? mrn_rbl_get_anchor_markup( $context ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Anchor markup is escaped in the helper.
?>
	<section
		class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
		data-block-id="<?php echo esc_attr( (string) $block_post_id ); ?>"
		data-block-slug="<?php echo esc_attr( $post_name ); ?>"
		<?php echo '' !== $section_attr_html ? ' ' . $section_attr_html : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php if ( array() !== $inline_styles ) : ?>
			style="<?php echo esc_attr( implode( '; ', $inline_styles ) ); ?>"
		<?php endif; ?>
>
		<div class="mrn-reusable-block__inner">
			<div class="mrn-reusable-block__basic-inner mrn-layout-grid--media-stack">
				<?php if ( '' !== $image_url ) : ?>
					<div class="mrn-reusable-block__media mrn-layout-content--media-stack-media mrn-ui__media">
					<img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $image_alt ); ?>" />
				</div>
			<?php endif; ?>

				<div class="mrn-reusable-block__content mrn-layout-content--media-stack-text mrn-ui__body">
					<?php if ( '' !== $label || '' !== $heading || '' !== $subheading ) : ?>
						<div class="mrn-ui__head">
							<?php if ( '' !== $label ) : ?>
								<<?php echo esc_html( $label_tag ); ?> class="mrn-ui__label">
								<?php echo function_exists( 'mrn_base_stack_format_heading_inline_html' ) ? mrn_base_stack_format_heading_inline_html( $label ) : esc_html( $label ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</<?php echo esc_html( $label_tag ); ?>>
							<?php endif; ?>

							<?php if ( '' !== $heading ) : ?>
								<<?php echo esc_html( $heading_tag ); ?> class="mrn-ui__heading">
								<?php echo function_exists( 'mrn_base_stack_format_heading_inline_html' ) ? mrn_base_stack_format_heading_inline_html( $heading ) : esc_html( $heading ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</<?php echo esc_html( $heading_tag ); ?>>
							<?php endif; ?>

							<?php if ( '' !== $subheading ) : ?>
								<<?php echo esc_html( $subheading_tag ); ?> class="mrn-ui__sub">
								<?php echo function_exists( 'mrn_base_stack_format_heading_inline_html' ) ? mrn_base_stack_format_heading_inline_html( $subheading ) : esc_html( $subheading ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</<?php echo esc_html( $subheading_tag ); ?>>
							<?php endif; ?>
						</div>
					<?php endif; ?>

				<?php if ( '' !== trim( $content ) ) : ?>
						<div class="mrn-ui__text">
						<?php echo apply_filters( 'the_content', $content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
				<?php endif; ?>

				<?php if ( '' !== $link_url ) : ?>
						<div class="mrn-reusable-block__link-wrap">
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
	</div>
</section>
