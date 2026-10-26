<?php
/**
 * Builder row: Body Text.
 *
 * @package mrn-base-stack
 */

$context          = is_array( $args ?? null ) ? $args : array();
$row              = isset( $context['row'] ) && is_array( $context['row'] ) ? $context['row'] : array();
$label            = isset( $row['label'] ) ? trim( (string) $row['label'] ) : '';
$label_tag        = mrn_base_stack_normalize_text_tag( $row['label_tag'] ?? '', 'p' );
$heading          = isset( $row['heading'] ) ? trim( (string) $row['heading'] ) : '';
$heading_tag      = isset( $row['heading_tag'] ) ? strtolower( (string) $row['heading_tag'] ) : 'h2';
$subheading       = isset( $row['subheading'] ) ? trim( (string) $row['subheading'] ) : '';
$subheading_tag   = isset( $row['subheading_tag'] ) ? strtolower( (string) $row['subheading_tag'] ) : 'p';
$is_quote         = 'contained' === ( $row['body_frame'] ?? '' ) && 'quote' === ( $row['body_text_semantics'] ?? '' );
$text_tag         = $is_quote ? 'blockquote' : 'div';
$body_text        = isset( $row['body_text'] ) ? (string) $row['body_text'] : '';
$background_color = isset( $row['background_color'] ) ? trim( (string) $row['background_color'] ) : '';
$bottom_accent    = ! empty( $row['bottom_accent'] );
$accent_slug      = isset( $row['bottom_accent_style'] ) ? (string) $row['bottom_accent_style'] : '';
$width_layers     = mrn_base_stack_get_section_width_layers( $row['section_width'] ?? '', 'content', 'full-width' );
$list_contract    = mrn_base_stack_get_body_list_contract( $row );
$tags_markup      = mrn_base_stack_get_text_tags_markup( $row );
$list_style       = mrn_base_stack_get_inline_style_attribute( $list_contract['styles'] );
$word_heading_attrs = mrn_base_stack_get_html_attributes( mrn_base_stack_get_scroll_word_attributes( $row, 'heading' ) );
$word_text_attrs = mrn_base_stack_get_html_attributes( mrn_base_stack_get_scroll_word_attributes( $row, 'text' ) );

$allowed_tags = array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'span', 'div' );
if ( ! in_array( $heading_tag, $allowed_tags, true ) ) {
	$heading_tag = 'h2';
}

if ( ! in_array( $subheading_tag, $allowed_tags, true ) ) {
	$subheading_tag = 'p';
}

if ( '' === $label && '' === $heading && '' === $subheading && '' === trim( wp_strip_all_tags( $body_text ) ) && '' === $tags_markup ) {
	return;
}

$section_classes = array(
	'mrn-content-builder__row',
	'mrn-content-builder__row--body-text',
);
$section_styles  = array();

if ( '' !== $background_color && function_exists( 'mrn_site_colors_get_css_var' ) ) {
	$section_styles[] = '--mrn-body-text-row-bg: var(' . mrn_site_colors_get_css_var( $background_color ) . ')';
}
$display_contract = mrn_base_stack_get_builder_display_contract( $row, 'body_text' );
$accent_contract  = mrn_base_stack_get_builder_accent_contract( $bottom_accent, $accent_slug );
$motion_contract  = mrn_base_stack_get_builder_motion_contract( $row );
$section_classes  = mrn_base_stack_merge_builder_section_classes( $section_classes, $display_contract );
$section_classes  = mrn_base_stack_merge_builder_section_classes( $section_classes, $accent_contract );
$section_classes  = mrn_base_stack_merge_builder_section_classes( $section_classes, $motion_contract );
$section_attrs    = $display_contract['attributes'];
$section_attrs    = mrn_base_stack_merge_builder_attributes( $section_attrs, isset( $accent_contract['attributes'] ) && is_array( $accent_contract['attributes'] ) ? $accent_contract['attributes'] : array() );
$section_attrs    = mrn_base_stack_merge_builder_attributes( $section_attrs, isset( $motion_contract['attributes'] ) && is_array( $motion_contract['attributes'] ) ? $motion_contract['attributes'] : array() );

$frame_contract = mrn_base_stack_get_content_frame_contract( $row, 'body_text' );
$section_classes = mrn_base_stack_merge_builder_section_classes( $section_classes, $frame_contract );
$section_attrs = mrn_base_stack_merge_builder_attributes( $section_attrs, $frame_contract['attributes'] );
$section_attr_html = mrn_base_stack_get_html_attributes( $section_attrs );
$surface_style     = mrn_base_stack_get_inline_style_attribute( $section_styles );
$is_full_width     = 'full-width' === ( $width_layers['width'] ?? '' );
echo mrn_base_stack_get_builder_anchor_markup( $row ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Anchor markup is escaped in the helper.
?>
<section class="<?php echo esc_attr( implode( ' ', $section_classes ) ); ?>"<?php echo '' !== $section_attr_html ? ' ' . $section_attr_html : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="mrn-layout-section mrn-layout-section--text <?php echo esc_attr( $width_layers['section_class'] ); ?><?php echo $is_full_width ? ' mrn-layout-surface' : ''; ?>"<?php echo $is_full_width && '' !== $surface_style ? ' style="' . esc_attr( $surface_style ) . '"' : ''; ?>>
		<div class="mrn-layout-container <?php echo esc_attr( $width_layers['container_class'] ); ?><?php echo ! $is_full_width ? ' mrn-layout-surface' : ''; ?>"<?php echo ! $is_full_width && '' !== $surface_style ? ' style="' . esc_attr( $surface_style ) . '"' : ''; ?>>
			<div class="mrn-layout-grid mrn-layout-grid--text mrn-layout-grid--text-shell">
				<div class="mrn-layout-content mrn-layout-content--text mrn-layout-content--text-shell mrn-ui__body">
					<?php if ( '' !== $label || '' !== $heading || '' !== $subheading ) : ?>
						<div class="mrn-ui__head">
							<?php if ( '' !== $label ) : ?>
								<<?php echo esc_html( $label_tag ); ?> class="mrn-ui__label"><?php echo mrn_base_stack_format_heading_inline_html( $label ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></<?php echo esc_html( $label_tag ); ?>>
							<?php endif; ?>

							<?php if ( '' !== $heading ) : ?>
								<<?php echo esc_html( $heading_tag ); ?> class="mrn-ui__heading"<?php echo '' !== $word_heading_attrs ? ' ' . $word_heading_attrs : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped native attribute helper. ?>><?php echo mrn_base_stack_get_body_heading_markup( $heading, $row['heading_link'] ?? array(), $row ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></<?php echo esc_html( $heading_tag ); ?>>
							<?php endif; ?>

							<?php if ( '' !== $subheading ) : ?>
								<<?php echo esc_html( $subheading_tag ); ?> class="mrn-ui__sub"><?php echo mrn_base_stack_format_heading_inline_html( $subheading ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></<?php echo esc_html( $subheading_tag ); ?>>
							<?php endif; ?>
						</div>
					<?php endif; ?>

					<?php if ( '' !== trim( $body_text ) ) : ?>
<?php if ( $is_quote ) : ?><div class="mrn-body-quote"><?php if ( 'opening' === ( $row['body_quote_mark'] ?? '' ) ) : ?>
							<svg class="mrn-body-quote__mark" aria-hidden="true" focusable="false" viewBox="0 0 34 28" fill="none" xmlns="http://www.w3.org/2000/svg">
<path d="M26.3932 14.2393C28.4672 14.3989 30.1425 15.0769 31.4188 16.2735C32.6952 17.4701 33.3333 19.0655 33.3333 21.0598C33.3333 23.2934 32.6553 25.0086 31.2991 26.2051C29.943 27.4017 28.2678 28 26.2735 28C23.5613 27.8405 21.6068 26.8433 20.4103 25.0085C19.2137 23.1738 18.6154 21.0997 18.6154 18.7863C18.6154 16.5527 18.9345 14.4786 19.5727 12.5641C20.2906 10.6496 21.208 8.8547 22.3248 7.17949C23.4416 5.50428 24.7179 4.02849 26.1538 2.75214C27.6695 1.47579 29.2251 0.558407 30.8205 0L33.6923 3.94873C32.0969 5.06554 30.5812 6.58121 29.1453 8.49573C27.7892 10.4103 26.8718 12.3248 26.3932 14.2393ZM7.77778 14.2393C9.85185 14.3989 11.5271 15.0769 12.8034 16.2735C14.0798 17.4701 14.7179 19.0655 14.7179 21.0598C14.7179 23.2934 14 25.0086 12.5641 26.2051C11.208 27.4017 9.61254 28 7.77778 28C4.98575 27.9202 2.99145 26.963 1.79487 25.1282C0.59829 23.2137 0 21.0997 0 18.7863C0 16.5527 0.319088 14.4786 0.957264 12.5641C1.67521 10.6496 2.59259 8.8547 3.7094 7.17949C4.82621 5.50428 6.10256 4.02849 7.53846 2.75214C9.05413 1.47579 10.6097 0.558407 12.2051 0L15.0769 3.94873C13.4815 5.06554 11.9658 6.58121 10.5299 8.49573C9.17379 10.4103 8.25641 12.3248 7.77778 14.2393Z" fill="currentColor"/>
</svg>
						<?php endif; ?><?php endif; ?>
						<<?php echo esc_html( $text_tag ); ?> class="<?php echo esc_attr( implode( ' ', array_merge( array( 'mrn-ui__text' ), $list_contract['classes'] ) ) ); ?>"<?php echo '' !== $list_style ? ' style="' . esc_attr( $list_style ) . '"' : ''; ?><?php echo '' !== $word_text_attrs ? ' ' . $word_text_attrs : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped native attribute helper. ?>>
							<?php echo apply_filters( 'the_content', $body_text ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</<?php echo esc_html( $text_tag ); ?>>
<?php if ( $is_quote ) : ?></div><?php endif; ?>
					<?php endif; ?>
<?php echo $tags_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Structured tags are escaped in the helper. ?>
				</div>
			</div>
		</div>
	</div>
</section>
