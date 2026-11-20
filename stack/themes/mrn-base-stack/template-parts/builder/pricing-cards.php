<?php
/** Native structured pricing Cards; no nested link overlays or JavaScript. */
$context = is_array( $args ?? null ) ? $args : array();
$row = is_array( $context['row'] ?? null ) ? $context['row'] : array();
$items = is_array( $row['card_items'] ?? null ) ? $row['card_items'] : array();
$classes = array( 'mrn-content-builder__row', 'mrn-content-builder__row--card', 'has-pricing-cards' );
$attributes = array();
foreach ( array( mrn_base_stack_get_builder_display_contract( $row, 'card' ), mrn_base_stack_get_builder_motion_contract( $row ), mrn_base_stack_get_card_frame_contract( $row ), mrn_base_stack_get_builder_accent_contract( ! empty( $row['bottom_accent'] ), $row['bottom_accent_style'] ?? '' ) ) as $contract ) {
	$classes = mrn_base_stack_merge_builder_section_classes( $classes, $contract );
	$attributes = mrn_base_stack_merge_builder_attributes( $attributes, $contract['attributes'] ?? array() );
}
$attributes = mrn_base_stack_merge_builder_attributes( $attributes, array( 'style' => implode( ';', mrn_base_stack_get_pricing_styles( $row ) ) ) );
$width = mrn_base_stack_get_section_width_layers( $row['section_width'] ?? '', 'wide', 'wide' );
$full_width = 'full-width' === ( $width['width'] ?? '' );
$surface = mrn_base_stack_get_frame_palette_color( $row['background_color'] ?? '' );
$heading_tag = mrn_base_stack_normalize_text_tag( $row['heading_tag'] ?? '', 'h2' );
echo mrn_base_stack_get_builder_anchor_markup( $row ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shared escaped anchor.
?>
<section class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" <?php echo mrn_base_stack_get_html_attributes( $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shared escaped attributes. ?>>
	<div class="mrn-layout-section mrn-layout-section--card <?php echo esc_attr( $width['section_class'] ); ?><?php echo $full_width ? ' mrn-layout-surface' : ''; ?>"<?php echo $full_width && '' !== $surface ? ' style="background-color:' . esc_attr( $surface ) . '"' : ''; ?>>
		<div class="mrn-layout-container <?php echo esc_attr( $width['container_class'] ); ?><?php echo ! $full_width ? ' mrn-layout-surface' : ''; ?>"<?php echo ! $full_width && '' !== $surface ? ' style="background-color:' . esc_attr( $surface ) . '"' : ''; ?>>
			<div class="mrn-pricing-cards">
				<?php if ( ! empty( $row['heading'] ) || ! empty( $row['subheading'] ) ) : ?>
					<header class="mrn-pricing-cards__head">
						<?php if ( ! empty( $row['heading'] ) ) : ?><<?php echo esc_html( $heading_tag ); ?> class="mrn-pricing-cards__heading"><?php echo esc_html( $row['heading'] ); ?></<?php echo esc_html( $heading_tag ); ?>><?php endif; ?>
						<?php if ( ! empty( $row['subheading'] ) ) : ?><p class="mrn-pricing-cards__subtitle"><?php echo esc_html( $row['subheading'] ); ?></p><?php endif; ?>
					</header>
				<?php endif; ?>
				<div class="mrn-pricing-cards__grid">
				<?php foreach ( $items as $item ) : ?>
					<?php
					if ( ! is_array( $item ) ) { continue; }
					$highlight = ! empty( $item['pricing_highlight'] );
					$features = is_array( $item['pricing_features'] ?? null ) ? $item['pricing_features'] : array();
					$action = mrn_base_stack_get_repeater_item_primary_link( array( 'link' => $item['pricing_action'] ?? array() ) );
					$target = in_array( $action['target'] ?? '', array( '_blank', '_self', '_parent', '_top' ), true ) ? $action['target'] : '';
					?>
					<article class="mrn-pricing-cards__plan<?php echo $highlight ? ' is-highlighted' : ''; ?>">
						<?php if ( ! empty( $item['card_label'] ) ) : ?><h3 class="mrn-pricing-cards__name"><?php echo esc_html( $item['card_label'] ); ?></h3><?php endif; ?>
						<?php if ( ! empty( $item['pricing_badge'] ) ) : ?><span class="mrn-pricing-cards__badge"><?php echo esc_html( $item['pricing_badge'] ); ?></span><?php endif; ?>
						<?php if ( ! empty( $item['pricing_description'] ) ) : ?><p class="mrn-pricing-cards__description"><?php echo esc_html( $item['pricing_description'] ); ?></p><?php endif; ?>
						<?php if ( $features ) : ?>
							<ul class="mrn-pricing-cards__features">
							<?php foreach ( $features as $feature ) : ?>
								<?php if ( ! is_array( $feature ) || ! is_string( $feature['text'] ?? null ) || '' === $feature['text'] ) { continue; } ?>
								<li><svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M9.54961 17.9996L3.84961 12.2996L5.27461 10.8746L9.54961 15.1496L18.7246 5.97461L20.1496 7.39961L9.54961 17.9996Z" fill="currentColor"/></svg><span><?php echo esc_html( $feature['text'] ); ?></span></li>
							<?php endforeach; ?>
							</ul>
						<?php endif; ?>
						<?php if ( ! empty( $action['url'] ) && ! empty( $action['text'] ) ) : ?>
							<a class="mrn-pricing-cards__button" href="<?php echo esc_url( $action['url'] ); ?>"<?php echo '' !== $target ? ' target="' . esc_attr( $target ) . '"' : ''; ?><?php echo '_blank' === $target ? ' rel="noopener noreferrer"' : ''; ?>>
								<span><?php echo esc_html( $action['text'] ); ?></span>
								<?php if ( $highlight ) : ?><svg width="16" height="12" viewBox="0 0 16 12" fill="none" aria-hidden="true" focusable="false"><path d="M1 6H14M14 6L9.5 1.5M14 6L9.5 10.5" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg><?php endif; ?>
							</a>
						<?php endif; ?>
					</article>
				<?php endforeach; ?>
				</div>
			</div>
		</div>
	</div>
</section>
