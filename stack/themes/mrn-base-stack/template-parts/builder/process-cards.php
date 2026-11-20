<?php
/** Native process overview with explicitly selected detail panels. */
$context = is_array( $args ?? null ) ? $args : array();
$row = is_array( $context['row'] ?? null ) ? $context['row'] : array();
$items = mrn_base_stack_get_process_card_items( $row );
$classes = array( 'mrn-content-builder__row', 'mrn-content-builder__row--card' );
$attributes = array();
foreach ( array( mrn_base_stack_get_builder_display_contract( $row, 'card' ), mrn_base_stack_get_builder_motion_contract( $row ), mrn_base_stack_get_card_frame_contract( $row ), mrn_base_stack_get_process_card_contract( $row ), mrn_base_stack_get_builder_accent_contract( ! empty( $row['bottom_accent'] ), $row['bottom_accent_style'] ?? '' ) ) as $contract ) {
	$classes = mrn_base_stack_merge_builder_section_classes( $classes, $contract );
	$attributes = mrn_base_stack_merge_builder_attributes( $attributes, $contract['attributes'] ?? array() );
}
$width = mrn_base_stack_get_section_width_layers( $row['section_width'] ?? '', 'wide', 'wide' );
$full_width = 'full-width' === ( $width['width'] ?? '' );
$surface = mrn_base_stack_get_frame_palette_color( $row['background_color'] ?? '' );
$heading_tag = mrn_base_stack_normalize_text_tag( $row['heading_tag'] ?? '', 'h2' );
$links = function_exists( 'mrn_rbl_get_content_links' ) ? mrn_rbl_get_content_links( $row, array( 'max' => 4 ) ) : array();
echo mrn_base_stack_get_builder_anchor_markup( $row ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shared escaped anchor.
?>
<section class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" <?php echo mrn_base_stack_get_html_attributes( $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shared escaped attributes. ?>>
	<div class="mrn-layout-section mrn-layout-section--card <?php echo esc_attr( $width['section_class'] ); ?><?php echo $full_width ? ' mrn-layout-surface' : ''; ?>"<?php echo $full_width && '' !== $surface ? ' style="background-color:' . esc_attr( $surface ) . '"' : ''; ?>>
		<div class="mrn-layout-container <?php echo esc_attr( $width['container_class'] ); ?><?php echo ! $full_width ? ' mrn-layout-surface' : ''; ?>"<?php echo ! $full_width && '' !== $surface ? ' style="background-color:' . esc_attr( $surface ) . '"' : ''; ?>>
			<div class="mrn-process">
				<?php if ( ! empty( $row['heading'] ) || ! empty( $row['label'] ) || ! empty( $row['subheading'] ) ) : ?>
					<header class="mrn-card-row__head mrn-ui__head">
						<?php if ( ! empty( $row['label'] ) ) : ?><p class="mrn-ui__label"><?php echo esc_html( $row['label'] ); ?></p><?php endif; ?>
						<?php if ( ! empty( $row['heading'] ) ) : ?><<?php echo esc_html( $heading_tag ); ?> class="mrn-ui__heading"><?php echo esc_html( $row['heading'] ); ?></<?php echo esc_html( $heading_tag ); ?>><?php endif; ?>
						<?php if ( ! empty( $row['subheading'] ) ) : ?><p class="mrn-ui__text"><?php echo esc_html( $row['subheading'] ); ?></p><?php endif; ?>
					</header>
				<?php endif; ?>
				<?php if ( $items ) : ?>
					<ol class="mrn-process__overview" role="list">
					<?php foreach ( $items as $item ) : ?>
						<li class="mrn-process__step<?php echo $item['muted'] ? ' is-muted' : ''; ?>"><span class="mrn-process__step-number" aria-hidden="true"><?php echo esc_html( $item['number'] ); ?></span><span class="mrn-process__step-label"><?php echo esc_html( $item['label'] ); ?></span></li>
					<?php endforeach; ?>
					</ol>
				<?php endif; ?>
				<div class="mrn-process__body">
					<div class="mrn-process__aside">
						<?php if ( ! empty( $row['card_intro'] ) ) : ?><div class="mrn-process__intro"><?php echo wp_kses_post( $row['card_intro'] ); ?></div><?php endif; ?>
						<?php foreach ( $links as $link ) : ?>
							<?php $target = '_blank' === ( $link['target'] ?? '' ) ? '_blank' : '_self'; ?>
							<?php if ( ! empty( $link['url'] ) && ! empty( $link['text'] ) ) : ?><a href="<?php echo esc_url( $link['url'] ); ?>" target="<?php echo esc_attr( $target ); ?>"<?php echo '_blank' === $target ? ' rel="noopener noreferrer"' : ''; ?>><?php echo esc_html( $link['text'] ); ?></a><?php endif; ?>
						<?php endforeach; ?>
					</div>
					<div class="mrn-process__details">
					<?php foreach ( $items as $item ) : ?>
						<?php if ( $item['overview_only'] ) { continue; } ?>
						<details class="mrn-process__item" open>
							<summary class="mrn-process__question"><span class="mrn-process__detail-number" aria-hidden="true"><?php echo esc_html( $item['number'] ); ?></span><h3><?php echo esc_html( $item['label'] ); ?></h3></summary>
							<div class="mrn-process__answer">
							<?php echo wp_kses_post( $item['body'] ); ?>
							</div>
						</details>
					<?php endforeach; ?>
					</div>
				</div>
			</div>
		</div>
	</div>
</section>
