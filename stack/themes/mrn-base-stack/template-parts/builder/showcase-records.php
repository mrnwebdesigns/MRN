<?php
/** Native Showcase records; all authored content remains in native ACF fields. */
$context = is_array( $args ?? null ) ? $args : array();
$row = is_array( $context['row'] ?? null ) ? $context['row'] : array();
$classes = array( 'mrn-content-builder__row', 'mrn-content-builder__row--showcase' ); $attributes = array();
foreach ( array( mrn_base_stack_get_builder_display_contract( $row, 'showcase' ), mrn_base_stack_get_builder_motion_contract( $row ), mrn_base_stack_get_showcase_record_contract( $row ), mrn_base_stack_get_builder_accent_contract( ! empty( $row['bottom_accent'] ), $row['bottom_accent_style'] ?? '' ) ) as $contract ) {
	$classes = mrn_base_stack_merge_builder_section_classes( $classes, $contract ); $attributes = mrn_base_stack_merge_builder_attributes( $attributes, $contract['attributes'] ?? array() );
}
$width = mrn_base_stack_get_section_width_layers( $row['section_width'] ?? '', 'wide', 'wide' ); $full = 'full-width' === ( $width['width'] ?? '' );
$surface = mrn_base_stack_get_frame_palette_color( $row['background_color'] ?? '' ); $items = is_array( $row['showcase_items'] ?? null ) ? $row['showcase_items'] : array();
echo mrn_base_stack_get_builder_anchor_markup( $row ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shared escaped anchor.
?>
<section class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" <?php echo mrn_base_stack_get_html_attributes( $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shared escaped attributes. ?>>
	<div class="mrn-layout-section mrn-layout-section--showcase <?php echo esc_attr( $width['section_class'] ); ?><?php echo $full ? ' mrn-layout-surface' : ''; ?>"<?php echo $full && '' !== $surface ? ' style="background-color:' . esc_attr( $surface ) . '"' : ''; ?>>
		<div class="mrn-layout-container <?php echo esc_attr( $width['container_class'] ); ?><?php echo ! $full ? ' mrn-layout-surface' : ''; ?>"<?php echo ! $full && '' !== $surface ? ' style="background-color:' . esc_attr( $surface ) . '"' : ''; ?>>
			<?php if ( ! empty( $row['heading'] ) ) : $tag = mrn_base_stack_normalize_text_tag( $row['heading_tag'] ?? '', 'h2' ); ?>
				<<?php echo esc_html( $tag ); ?>><?php echo mrn_base_stack_format_heading_inline_html( $row['heading'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shared escaped heading. ?></<?php echo esc_html( $tag ); ?>>
			<?php endif; ?>
			<?php foreach ( $items as $item ) : ?>
				<?php
				if ( ! is_array( $item ) ) { continue; }
				$tag = mrn_base_stack_normalize_text_tag( $item['record_heading_tag'] ?? '', 'h3' );
				$image_attrs = array( 'loading' => 'lazy', 'sizes' => '(max-width: 991px) 100vw, 40vw' );
				if ( is_string( $item['image_alt_override'] ?? null ) && '' !== $item['image_alt_override'] ) { $image_attrs['alt'] = $item['image_alt_override']; }
				$image = ! empty( $item['image'] ) ? mrn_base_stack_get_attachment_image( $item['image'], 'full', $image_attrs ) : '';
				$tags = mrn_base_stack_get_text_tags_markup( array_merge( $row, $item ) ); $action = mrn_base_stack_get_repeater_item_primary_link( $item );
				$url = esc_url( $action['url'] ?? '' ); $target = in_array( $action['target'] ?? '', array( '_blank', '_self', '_parent', '_top' ), true ) ? $action['target'] : '';
				?>
				<article class="mrn-showcase-record">
					<div class="mrn-showcase-record__media-column">
						<?php if ( ! empty( $item['record_heading'] ) ) : ?><<?php echo esc_html( $tag ); ?> class="mrn-showcase-record__heading"><?php echo esc_html( $item['record_heading'] ); ?></<?php echo esc_html( $tag ); ?>><?php endif; ?>
						<?php if ( '' !== $image ) : ?><div class="mrn-showcase-record__image"><?php echo $image; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shared attachment helper. ?></div><?php endif; ?>
					</div>
					<div class="mrn-showcase-record__copy-column">
						<?php if ( ! empty( $item['record_content'] ) ) : ?><div class="mrn-showcase-record__copy"><?php echo apply_filters( 'the_content', $item['record_content'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Native editable WYSIWYG content contract. ?></div><?php endif; ?>
						<?php echo $tags; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shared escaped tag helper. ?>
						<?php if ( '' !== $url && ! empty( $action['text'] ) ) : ?><div class="mrn-showcase-record__action"><a href="<?php echo esc_url( $url ); ?>"<?php echo '' !== $target ? ' target="' . esc_attr( $target ) . '"' : ''; ?><?php echo '_blank' === $target ? ' rel="noopener noreferrer"' : ''; ?>><?php echo esc_html( $action['text'] ); ?><svg width="12" height="10" viewBox="0 0 12 10" aria-hidden="true" focusable="false"><path d="M4.60803 9.95201L9.58403 4.96001L4.62403 0H6.40003L11.36 4.97601L6.38403 9.95201H4.60803ZM0 4.32001H10.56V5.63201H0V4.32001Z" fill="currentColor"/></svg></a></div><?php endif; ?>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
	</div>
</section>
