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

$post         = isset( $context['post'] ) && $context['post'] instanceof WP_Post ? $context['post'] : null;
$post_id      = isset( $context['post_id'] ) ? (int) $context['post_id'] : 0;
$post_name    = isset( $context['post_name'] ) ? (string) $context['post_name'] : ( $post instanceof WP_Post ? (string) $post->post_name : '' );
$fields       = isset( $context['fields'] ) && is_array( $context['fields'] ) ? $context['fields'] : array();
$label        = isset( $fields['label'] ) ? trim( (string) $fields['label'] ) : '';
$label_tag    = function_exists( 'mrn_rbl_normalize_text_tag' ) ? mrn_rbl_normalize_text_tag( $fields['label_tag'] ?? '', 'p' ) : 'p';
$heading      = isset( $fields['text_field'] ) ? trim( (string) $fields['text_field'] ) : '';
$heading_tag  = isset( $fields['text_field_tag'] ) ? strtolower( (string) $fields['text_field_tag'] ) : 'h2';
$content      = isset( $fields['content'] ) ? (string) $fields['content'] : '';
$image        = isset( $fields['image'] ) && is_array( $fields['image'] ) ? $fields['image'] : array();
$image_url    = isset( $image['url'] ) ? (string) $image['url'] : '';
$image_alt    = isset( $image['alt'] ) ? (string) $image['alt'] : '';
$link         = isset( $fields['link'] ) && is_array( $fields['link'] ) ? $fields['link'] : array();
$link_url     = isset( $link['url'] ) ? (string) $link['url'] : '';
$link_title   = isset( $link['title'] ) ? (string) $link['title'] : '';
$link_target  = isset( $link['target'] ) ? (string) $link['target'] : '';
$bg_color     = isset( $fields['bg_color'] ) ? (string) $fields['bg_color'] : '';
$link_style   = isset( $fields['link_style'] ) ? sanitize_key( (string) $fields['link_style'] ) : 'link';
$link_color   = isset( $fields['link_color'] ) ? (string) $fields['link_color'] : '';
$image_place  = isset( $fields['image_placement'] ) ? sanitize_key( (string) $fields['image_placement'] ) : 'left';
$accent       = ! empty( $fields['bottom_accent'] );
$accent_slug  = isset( $fields['bottom_accent_style'] ) ? (string) $fields['bottom_accent_style'] : '';

if ( '' === $label && '' === $heading && '' === trim( wp_strip_all_tags( $content ) ) && '' === $image_url && '' === $link_url ) {
	return;
}

$allowed_tags = array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'div', 'span' );
if ( ! in_array( $heading_tag, $allowed_tags, true ) ) {
	$heading_tag = 'h2';
}

if ( ! in_array( $link_style, array( 'link', 'button' ), true ) ) {
	$link_style = 'link';
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

$inline_styles = array();

if ( '' !== $bg_color && function_exists( 'mrn_site_colors_get_css_var' ) ) {
	$inline_styles[] = '--mrn-basic-block-bg-color: var(' . mrn_site_colors_get_css_var( $bg_color ) . ')';
}

if ( '' !== $link_color && function_exists( 'mrn_site_colors_get_css_var' ) ) {
	$inline_styles[] = '--mrn-basic-block-link-color: var(' . mrn_site_colors_get_css_var( $link_color ) . ')';
}
?>
<section
	class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
	data-block-id="<?php echo esc_attr( (string) $post_id ); ?>"
	data-block-slug="<?php echo esc_attr( $post_name ); ?>"
	<?php if ( isset( $accent_contract['attributes'] ) && is_array( $accent_contract['attributes'] ) ) : ?>
		<?php foreach ( $accent_contract['attributes'] as $attribute_name => $attribute_value ) : ?>
			<?php if ( '' !== $attribute_name && '' !== $attribute_value ) : ?>
				<?php echo ' ' . esc_attr( $attribute_name ) . '="' . esc_attr( $attribute_value ) . '"'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php endif; ?>
		<?php endforeach; ?>
	<?php endif; ?>
	<?php if ( $inline_styles !== array() ) : ?>
		style="<?php echo esc_attr( implode( '; ', $inline_styles ) ); ?>"
	<?php endif; ?>
>
	<div class="mrn-reusable-block__inner">
		<div class="mrn-reusable-block__basic-inner mrn-layout-grid--media-stack">
			<?php if ( '' !== $image_url ) : ?>
				<div class="mrn-reusable-block__media mrn-layout-content--media-stack-media">
					<img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $image_alt ); ?>" />
				</div>
			<?php endif; ?>

			<div class="mrn-reusable-block__content mrn-layout-content--media-stack-text">
				<?php if ( '' !== $label ) : ?>
					<<?php echo esc_html( $label_tag ); ?> class="mrn-reusable-block__label">
						<?php echo function_exists( 'mrn_base_stack_format_heading_inline_html' ) ? mrn_base_stack_format_heading_inline_html( $label ) : esc_html( $label ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</<?php echo esc_html( $label_tag ); ?>>
				<?php endif; ?>

				<?php if ( '' !== $heading ) : ?>
					<<?php echo esc_html( $heading_tag ); ?> class="mrn-reusable-block__heading">
						<?php echo function_exists( 'mrn_base_stack_format_heading_inline_html' ) ? mrn_base_stack_format_heading_inline_html( $heading ) : esc_html( $heading ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</<?php echo esc_html( $heading_tag ); ?>>
				<?php endif; ?>

				<?php if ( '' !== trim( $content ) ) : ?>
					<div class="mrn-reusable-block__text">
						<?php echo apply_filters( 'the_content', $content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
				<?php endif; ?>

				<?php if ( '' !== $link_url ) : ?>
					<div class="mrn-reusable-block__link-wrap">
						<a
							class="mrn-reusable-block__link <?php echo 'button' === $link_style ? 'mrn-reusable-block__link--button' : 'mrn-reusable-block__link--text'; ?>"
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
</section>
