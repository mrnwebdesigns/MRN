<?php
/**
 * Builder row: Card.
 *
 * @package mrn-base-stack
 */

$context               = is_array( $args ?? null ) ? $args : array();
$row                   = isset( $context['row'] ) && is_array( $context['row'] ) ? $context['row'] : array();
$context_post_id       = isset( $context['post_id'] ) ? (int) $context['post_id'] : get_the_ID();
$row_index             = isset( $context['index'] ) ? (int) $context['index'] : 0;
$label                 = isset( $row['label'] ) ? trim( (string) $row['label'] ) : '';
$label_tag             = function_exists( 'mrn_base_stack_normalize_text_tag' ) ? mrn_base_stack_normalize_text_tag( $row['label_tag'] ?? '', 'p' ) : 'p';
$heading               = isset( $row['heading'] ) ? trim( (string) $row['heading'] ) : '';
$heading_tag           = function_exists( 'mrn_base_stack_normalize_text_tag' ) ? mrn_base_stack_normalize_text_tag( $row['heading_tag'] ?? '', 'h2' ) : 'h2';
$subheading            = isset( $row['subheading'] ) ? trim( (string) $row['subheading'] ) : '';
$subheading_tag        = function_exists( 'mrn_base_stack_normalize_text_tag' ) ? mrn_base_stack_normalize_text_tag( $row['subheading_tag'] ?? '', 'p' ) : 'p';
$items                 = isset( $row['card_items'] ) && is_array( $row['card_items'] ) ? $row['card_items'] : array();
$card_layout           = function_exists( 'mrn_base_stack_normalize_builder_layout_mode' )
	? mrn_base_stack_normalize_builder_layout_mode( $row['card_layout'] ?? '', 'card' )
	: sanitize_key( (string) ( $row['card_layout'] ?? 'grid' ) );
$card_stack_alignment  = function_exists( 'mrn_base_stack_normalize_card_stack_alignment' )
	? mrn_base_stack_normalize_card_stack_alignment( $row['card_stack_alignment'] ?? '' )
	: sanitize_key( (string) ( $row['card_stack_alignment'] ?? 'left' ) );
$cards_per_row         = function_exists( 'mrn_base_stack_normalize_card_per_row' )
	? mrn_base_stack_normalize_card_per_row( $row['cards_per_row'] ?? 3 )
	: absint( $row['cards_per_row'] ?? 3 );
$background_color      = isset( $row['background_color'] ) ? trim( (string) $row['background_color'] ) : '';
$bottom_accent         = ! empty( $row['bottom_accent'] );
$accent_slug           = isset( $row['bottom_accent_style'] ) ? (string) $row['bottom_accent_style'] : '';
$width_layers          = function_exists( 'mrn_base_stack_get_section_width_layers' )
	? mrn_base_stack_get_section_width_layers( $row['section_width'] ?? '', 'wide', 'wide' )
	: array(
		'width'           => 'wide',
		'section_class'   => 'mrn-layout-section--contained',
		'container_class' => 'mrn-layout-container--wide',
	);

$rendered_cards = array();
$has_card_links = false;
foreach ( $items as $item_index => $item ) {
	if ( ! is_array( $item ) ) {
		continue;
	}

	$card_label       = isset( $item['card_label'] ) ? trim( (string) $item['card_label'] ) : '';
	$icon_markup      = function_exists( 'mrn_base_stack_get_button_link_icon_markup' ) ? mrn_base_stack_get_button_link_icon_markup( $item ) : '';
	$icon_position    = function_exists( 'mrn_base_stack_get_button_link_icon_position' ) ? mrn_base_stack_get_button_link_icon_position( $item ) : 'left';
	$item_link_data   = function_exists( 'mrn_base_stack_get_repeater_item_primary_link' )
		? mrn_base_stack_get_repeater_item_primary_link( $item )
		: array();
	$item_link_url    = isset( $item_link_data['url'] ) ? (string) $item_link_data['url'] : '';
	$item_link_target = isset( $item_link_data['target'] ) ? (string) $item_link_data['target'] : '';
	$card_rows        = isset( $item['card_rows'] ) && is_array( $item['card_rows'] ) ? $item['card_rows'] : array();
	$card_row         = ! empty( $card_rows[0] ) && is_array( $card_rows[0] ) ? $card_rows[0] : array();
	$card_markup      = '';

	if ( ! empty( $card_row ) ) {
		$card_row_index = function_exists( 'mrn_base_stack_get_nested_builder_row_index' )
			? mrn_base_stack_get_nested_builder_row_index( $row_index, $item_index, 0 )
			: ( ( absint( $row_index ) + 1 ) * 10000 ) + ( ( absint( $item_index ) + 1 ) * 100 );

		ob_start();
		if ( function_exists( 'mrn_base_stack_render_builder_row' ) ) {
			mrn_base_stack_render_builder_row( $card_row, $context_post_id, $card_row_index );
		}
		$card_markup = trim( (string) ob_get_clean() );
	}

	if ( '' === $card_label && '' === $icon_markup && '' === $item_link_url && '' === $card_markup ) {
		continue;
	}

	$item_background_color = isset( $item['background_color'] ) ? trim( (string) $item['background_color'] ) : '';
	$item_styles           = array();
	$item_class_names      = array(
		'mrn-card-row__item',
		'mrn-card-row__item--card-deck',
		'mrn-ui__item',
	);

	if ( '' !== $item_link_url ) {
		$item_class_names[] = 'mrn-card-row__item--full-link';
		$has_card_links    = true;
	}

	if ( '' !== $item_background_color && function_exists( 'mrn_site_colors_get_css_var' ) ) {
		$item_styles[] = '--mrn-card-item-bg: var(' . mrn_site_colors_get_css_var( $item_background_color ) . ')';
	}

	$item_link_label      = isset( $item_link_data['text'] ) && '' !== trim( (string) $item_link_data['text'] )
		? (string) $item_link_data['text']
		: ( '' !== $card_label ? $card_label : __( 'View Card', 'mrn-base-stack' ) );
	$item_link_aria_label = '' !== $card_label ? $card_label : $item_link_label;
	$item_style_attr      = function_exists( 'mrn_base_stack_get_inline_style_attribute' ) ? mrn_base_stack_get_inline_style_attribute( $item_styles ) : implode( '; ', $item_styles );

	$rendered_cards[] = array(
		'classes'         => $item_class_names,
		'style'           => $item_style_attr,
		'label'           => $card_label,
		'icon_markup'     => $icon_markup,
		'icon_position'   => $icon_position,
		'is_icon_only'    => '' !== $icon_markup && '' === $card_label,
		'markup'          => $card_markup,
		'link_url'        => $item_link_url,
		'link_target'     => $item_link_target,
		'link_label'      => $item_link_label,
		'link_aria_label' => $item_link_aria_label,
	);
}

$section_links = function_exists( 'mrn_rbl_get_content_links' )
	? mrn_rbl_get_content_links(
		$row,
		array(
			'max'          => 4,
		)
	)
	: array();
if ( '' === $label && '' === $heading && '' === $subheading && empty( $rendered_cards ) && empty( $section_links ) ) {
	return;
}

$section_styles = array();
if ( '' !== $background_color && function_exists( 'mrn_site_colors_get_css_var' ) ) {
	$section_styles[] = '--mrn-card-row-bg: var(' . mrn_site_colors_get_css_var( $background_color ) . ')';
}
$section_styles[] = '--mrn-card-columns: ' . max( 1, min( 6, $cards_per_row ) );

$section_classes = array(
	'mrn-content-builder__row',
	'mrn-content-builder__row--card',
	'mrn-content-builder__row--card-layout-' . sanitize_html_class( $card_layout ),
	'mrn-content-builder__row--card-columns-' . sanitize_html_class( (string) $cards_per_row ),
	'mrn-content-builder__row--card-stack-align-' . sanitize_html_class( $card_stack_alignment ),
);
if ( $has_card_links ) {
	$section_classes[] = 'mrn-content-builder__row--card-full-link';
}
$display_contract  = function_exists( 'mrn_base_stack_get_builder_display_contract' ) ? mrn_base_stack_get_builder_display_contract( $row, 'card' ) : array(
	'classes'    => array(),
	'attributes' => array(),
);
$accent_contract   = function_exists( 'mrn_base_stack_get_builder_accent_contract' ) ? mrn_base_stack_get_builder_accent_contract( $bottom_accent, $accent_slug ) : array(
	'classes'    => $bottom_accent ? array( 'has-bottom-accent' ) : array(),
	'attributes' => array(),
);
$motion_contract   = function_exists( 'mrn_base_stack_get_builder_motion_contract' ) ? mrn_base_stack_get_builder_motion_contract( $row ) : array(
	'classes'    => array(),
	'attributes' => array(),
);
$section_classes   = function_exists( 'mrn_base_stack_merge_builder_section_classes' ) ? mrn_base_stack_merge_builder_section_classes( $section_classes, $display_contract ) : $section_classes;
$section_classes   = function_exists( 'mrn_base_stack_merge_builder_section_classes' ) ? mrn_base_stack_merge_builder_section_classes( $section_classes, $accent_contract ) : $section_classes;
$section_classes   = function_exists( 'mrn_base_stack_merge_builder_section_classes' ) ? mrn_base_stack_merge_builder_section_classes( $section_classes, $motion_contract ) : $section_classes;
$section_attrs     = isset( $display_contract['attributes'] ) && is_array( $display_contract['attributes'] ) ? $display_contract['attributes'] : array();
$section_attrs     = function_exists( 'mrn_base_stack_merge_builder_attributes' ) ? mrn_base_stack_merge_builder_attributes( $section_attrs, isset( $accent_contract['attributes'] ) && is_array( $accent_contract['attributes'] ) ? $accent_contract['attributes'] : array() ) : array_merge( $section_attrs, isset( $accent_contract['attributes'] ) && is_array( $accent_contract['attributes'] ) ? $accent_contract['attributes'] : array() );
$section_attrs     = function_exists( 'mrn_base_stack_merge_builder_attributes' ) ? mrn_base_stack_merge_builder_attributes( $section_attrs, isset( $motion_contract['attributes'] ) && is_array( $motion_contract['attributes'] ) ? $motion_contract['attributes'] : array() ) : array_merge( $section_attrs, isset( $motion_contract['attributes'] ) && is_array( $motion_contract['attributes'] ) ? $motion_contract['attributes'] : array() );
$section_attr_html = function_exists( 'mrn_base_stack_get_html_attributes' ) ? mrn_base_stack_get_html_attributes( $section_attrs ) : '';
$surface_style     = function_exists( 'mrn_base_stack_get_inline_style_attribute' ) ? mrn_base_stack_get_inline_style_attribute( $section_styles ) : implode( '; ', $section_styles );
$is_full_width     = 'full-width' === ( $width_layers['width'] ?? '' );
echo function_exists( 'mrn_base_stack_get_builder_anchor_markup' ) ? mrn_base_stack_get_builder_anchor_markup( $row ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Anchor markup is escaped in the helper.
?>
<section class="<?php echo esc_attr( implode( ' ', $section_classes ) ); ?>"<?php echo '' !== $section_attr_html ? ' ' . $section_attr_html : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="mrn-layout-section mrn-layout-section--card <?php echo esc_attr( $width_layers['section_class'] ); ?><?php echo $is_full_width ? ' mrn-layout-surface' : ''; ?>"<?php echo $is_full_width && '' !== $surface_style ? ' style="' . esc_attr( $surface_style ) . '"' : ''; ?>>
		<div class="mrn-layout-container <?php echo esc_attr( $width_layers['container_class'] ); ?><?php echo ! $is_full_width ? ' mrn-layout-surface' : ''; ?>"<?php echo ! $is_full_width && '' !== $surface_style ? ' style="' . esc_attr( $surface_style ) . '"' : ''; ?>>
			<div class="mrn-layout-grid mrn-layout-grid--card mrn-ui__body">
		<?php if ( '' !== $label || '' !== $heading || '' !== $subheading ) : ?>
				<div class="mrn-layout-content mrn-layout-content--text mrn-card-row__head mrn-ui__head">
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

		<?php if ( ! empty( $rendered_cards ) ) : ?>
				<div class="mrn-card-row__grid mrn-card-row__grid--card-deck mrn-card-row__grid--layout-<?php echo esc_attr( $card_layout ); ?> mrn-ui__items">
				<?php foreach ( $rendered_cards as $rendered_card ) : ?>
					<article class="<?php echo esc_attr( implode( ' ', $rendered_card['classes'] ) ); ?>"<?php echo '' !== $rendered_card['style'] ? ' style="' . esc_attr( $rendered_card['style'] ) . '"' : ''; ?>>
						<?php if ( '' !== $rendered_card['label'] || '' !== $rendered_card['icon_markup'] ) : ?>
							<header class="mrn-card-row__item-head">
								<h3 class="mrn-card-row__item-title">
									<?php if ( '' !== $rendered_card['label'] ) : ?>
										<?php
										echo function_exists( 'mrn_base_stack_get_compact_link_label_markup' )
											? mrn_base_stack_get_compact_link_label_markup( $rendered_card['label'], (string) $rendered_card['icon_markup'], (string) $rendered_card['icon_position'] ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icon helper returns escaped markup and label helper escapes the label.
											: esc_html( $rendered_card['label'] );
										?>
									<?php else : ?>
										<?php echo $rendered_card['icon_markup']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icon markup is escaped in shared helper output. ?>
										<span class="screen-reader-text"><?php echo esc_html( $rendered_card['link_aria_label'] ); ?></span>
									<?php endif; ?>
								</h3>
							</header>
						<?php endif; ?>

						<?php if ( '' !== $rendered_card['markup'] ) : ?>
							<div class="mrn-card-row__content">
								<?php echo $rendered_card['markup']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Nested builder rows escape their own output. ?>
							</div>
						<?php endif; ?>

						<?php if ( '' !== $rendered_card['link_url'] ) : ?>
							<a class="mrn-card-row__item-overlay-link" href="<?php echo esc_url( $rendered_card['link_url'] ); ?>"<?php echo '' !== $rendered_card['link_target'] ? ' target="' . esc_attr( $rendered_card['link_target'] ) . '"' : ''; ?><?php echo '_blank' === $rendered_card['link_target'] ? ' rel="noopener noreferrer"' : ''; ?>>
								<span class="screen-reader-text"><?php echo esc_html( $rendered_card['link_aria_label'] ); ?></span>
							</a>
						<?php endif; ?>
					</article>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $section_links ) ) : ?>
				<div class="mrn-card-row__link">
				<?php foreach ( $section_links as $section_link ) : ?>
					<?php
					if ( ! is_array( $section_link ) ) {
						continue;
					}

					$section_link_url           = isset( $section_link['url'] ) ? (string) $section_link['url'] : '';
					$section_link_text          = isset( $section_link['text'] ) ? (string) $section_link['text'] : '';
					$section_link_style         = isset( $section_link['link_style'] ) && in_array( $section_link['link_style'], array( 'link', 'button' ), true ) ? (string) $section_link['link_style'] : 'link';
					$section_link_tag           = function_exists( 'mrn_rbl_get_content_link_tag_name' ) ? mrn_rbl_get_content_link_tag_name( $section_link ) : 'a';
					$section_link_attr_html     = function_exists( 'mrn_rbl_get_content_link_html_attributes' ) ? mrn_rbl_get_content_link_html_attributes( $section_link ) : '';
					$section_link_class_names   = 'mrn-ui__link' . ( 'button' === $section_link_style ? ' mrn-ui__link--button' : '' );
					$section_link_icon_markup   = function_exists( 'mrn_base_stack_get_button_link_icon_markup' )
						? mrn_base_stack_get_button_link_icon_markup( $section_link )
						: '';
					$section_link_icon_position = function_exists( 'mrn_base_stack_get_button_link_icon_position' )
						? mrn_base_stack_get_button_link_icon_position( $section_link )
						: 'left';

					if ( function_exists( 'mrn_rbl_get_content_link_custom_class_names' ) ) {
						$section_link_custom_classes = mrn_rbl_get_content_link_custom_class_names( $section_link );
						if ( '' !== $section_link_custom_classes ) {
							$section_link_class_names .= ' ' . $section_link_custom_classes;
						}
					}
					?>
					<<?php echo esc_html( $section_link_tag ); ?>
						class="<?php echo esc_attr( trim( $section_link_class_names ) ); ?>"
						<?php echo '' !== $section_link_attr_html ? $section_link_attr_html : 'href="' . esc_url( $section_link_url ) . '"'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					>
					<?php
					$section_link_label = '' !== $section_link_text ? $section_link_text : $section_link_url;
						echo wp_kses_post(
							function_exists( 'mrn_base_stack_get_compact_link_label_markup' )
								? mrn_base_stack_get_compact_link_label_markup( $section_link_label, $section_link_icon_markup, $section_link_icon_position )
								: esc_html( $section_link_label )
						);
					?>
				</<?php echo esc_html( $section_link_tag ); ?>>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
			</div>
		</div>
	</div>
</section>
