<?php
/**
 * Builder row: Tabbed Layout.
 *
 * @package mrn-base-stack
 */

$context         = is_array( $args ?? null ) ? $args : array();
$row             = isset( $context['row'] ) && is_array( $context['row'] ) ? $context['row'] : array();
$context_post_id = isset( $context['post_id'] ) ? (int) $context['post_id'] : get_the_ID();
$row_index       = isset( $context['index'] ) ? (int) $context['index'] : 0;
$label           = isset( $row['label'] ) ? trim( (string) $row['label'] ) : '';
$label_tag       = function_exists( 'mrn_base_stack_normalize_text_tag' ) ? mrn_base_stack_normalize_text_tag( $row['label_tag'] ?? '', 'p' ) : 'p';
$heading         = isset( $row['heading'] ) ? trim( (string) $row['heading'] ) : '';
$heading_tag     = function_exists( 'mrn_base_stack_normalize_text_tag' ) ? mrn_base_stack_normalize_text_tag( $row['heading_tag'] ?? '', 'h2' ) : 'h2';
$subheading      = isset( $row['subheading'] ) ? trim( (string) $row['subheading'] ) : '';
$subheading_tag  = function_exists( 'mrn_base_stack_normalize_text_tag' ) ? mrn_base_stack_normalize_text_tag( $row['subheading_tag'] ?? '', 'p' ) : 'p';
$tab_items       = isset( $row['tabs'] ) && is_array( $row['tabs'] ) ? $row['tabs'] : array();
$tab_orientation = isset( $row['tab_orientation'] ) ? sanitize_key( (string) $row['tab_orientation'] ) : 'horizontal';
$equal_heights   = ! empty( $row['equal_panel_heights'] );
$switch_effect   = isset( $row['tab_switch_effect'] ) ? sanitize_key( (string) $row['tab_switch_effect'] ) : 'instant';
$uses_slider     = 'slide' === $switch_effect;
$bottom_accent   = ! empty( $row['bottom_accent'] );
$accent_slug     = isset( $row['bottom_accent_style'] ) ? (string) $row['bottom_accent_style'] : '';
$width_layers    = function_exists( 'mrn_base_stack_get_section_width_layers' )
	? mrn_base_stack_get_section_width_layers( $row['section_width'] ?? '', 'wide', 'wide' )
	: array(
		'width'           => 'wide',
		'section_class'   => 'mrn-layout-section--contained',
		'container_class' => 'mrn-layout-container--wide',
	);
$layout_uid      = function_exists( 'wp_unique_id' )
	? wp_unique_id( 'mrn-tabbed-layout-' . absint( $context_post_id ) . '-' . absint( $row_index ) . '-' )
	: 'mrn-tabbed-layout-' . absint( $context_post_id ) . '-' . absint( $row_index ) . '-' . wp_generate_password( 6, false, false );
$rendered_tabs   = array();
$has_tab_images  = false;

foreach ( $tab_items as $tab_index => $tab_item ) {
	if ( ! is_array( $tab_item ) ) {
		continue;
	}

	$tab_label  = isset( $tab_item['tab_label'] ) ? trim( (string) $tab_item['tab_label'] ) : '';
	$tab_image  = isset( $tab_item['tab_image'] ) && is_array( $tab_item['tab_image'] ) ? $tab_item['tab_image'] : array();
	$panel_rows = isset( $tab_item['panel_rows'] ) && is_array( $tab_item['panel_rows'] ) ? $tab_item['panel_rows'] : array();
	$panel_row  = ! empty( $panel_rows[0] ) && is_array( $panel_rows[0] ) ? $panel_rows[0] : array();

	if ( empty( $panel_row ) ) {
		continue;
	}

	$panel_index = function_exists( 'mrn_base_stack_get_nested_builder_row_index' )
		? mrn_base_stack_get_nested_builder_row_index( $row_index, $tab_index, 0 )
		: ( ( absint( $row_index ) + 1 ) * 10000 ) + ( ( absint( $tab_index ) + 1 ) * 100 );

	ob_start();
	if ( function_exists( 'mrn_base_stack_render_builder_row' ) ) {
		mrn_base_stack_render_builder_row( $panel_row, $context_post_id, $panel_index );
	}
	$panel_markup = trim( (string) ob_get_clean() );

	if ( '' === $panel_markup ) {
		continue;
	}

	$accessible_label = $tab_label;
	if ( '' === $accessible_label ) {
		$accessible_label = isset( $tab_image['alt'] ) ? trim( (string) $tab_image['alt'] ) : '';
	}

	if ( '' === $accessible_label ) {
		/* translators: %d: Tab number. */
		$accessible_label = sprintf( esc_html__( 'Tab %d', 'mrn-base-stack' ), (int) $tab_index + 1 );
	}

	$has_image = ! empty( $tab_image['ID'] ) || ! empty( $tab_image['url'] );
	if ( $has_image ) {
		$has_tab_images = true;
	}

	$rendered_tabs[] = array(
		'button_id'        => $layout_uid . 'tab-' . ( (int) $tab_index + 1 ),
		'panel_id'         => $layout_uid . 'panel-' . ( (int) $tab_index + 1 ),
		'label'            => $tab_label,
		'accessible_label' => $accessible_label,
		'image'            => $tab_image,
		'has_image'        => $has_image,
		'is_image_only'    => $has_image && '' === $tab_label,
		'markup'           => $panel_markup,
	);
}

if ( empty( $rendered_tabs ) ) {
	return;
}

$section_classes = array(
	'mrn-content-builder__row',
	'mrn-content-builder__row--tabbed-layout',
);

if ( ! in_array( $tab_orientation, array( 'horizontal', 'vertical' ), true ) ) {
	$tab_orientation = 'horizontal';
}

if ( ! in_array( $switch_effect, array( 'instant', 'fade', 'slide' ), true ) ) {
	$switch_effect = 'instant';
}

if ( $has_tab_images ) {
	$section_classes[] = 'mrn-content-builder__row--tabbed-layout-has-images';
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
$section_attrs     = function_exists( 'mrn_base_stack_merge_builder_attributes' )
	? mrn_base_stack_merge_builder_attributes( $section_attrs, isset( $motion_contract['attributes'] ) && is_array( $motion_contract['attributes'] ) ? $motion_contract['attributes'] : array() )
	: array_merge( $section_attrs, isset( $motion_contract['attributes'] ) && is_array( $motion_contract['attributes'] ) ? $motion_contract['attributes'] : array() );
$section_attr_html = function_exists( 'mrn_base_stack_get_html_attributes' ) ? mrn_base_stack_get_html_attributes( $section_attrs ) : '';
$tablist_label     = '' !== $heading ? wp_strip_all_tags( $heading ) : esc_html__( 'Tabbed content', 'mrn-base-stack' );
$root_classes      = array(
	'mrn-tabbed-layout',
	'mrn-tabbed-layout--orientation-' . sanitize_html_class( $tab_orientation ),
	'mrn-tabbed-layout--transition-' . sanitize_html_class( $switch_effect ),
);

if ( $equal_heights ) {
	$root_classes[] = 'mrn-tabbed-layout--equal-heights';
}

echo function_exists( 'mrn_base_stack_get_builder_anchor_markup' ) ? mrn_base_stack_get_builder_anchor_markup( $row ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Anchor markup is escaped in the helper.
?>
<section class="<?php echo esc_attr( implode( ' ', $section_classes ) ); ?>"<?php echo '' !== $section_attr_html ? ' ' . $section_attr_html : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="<?php echo esc_attr( implode( ' ', $root_classes ) ); ?>" data-mrn-tabbed-layout data-mrn-equal-panel-heights="<?php echo $equal_heights ? 'true' : 'false'; ?>">
		<div class="mrn-layout-section mrn-layout-section--tabbed-layout <?php echo esc_attr( $width_layers['section_class'] ); ?>">
			<div class="mrn-layout-container <?php echo esc_attr( $width_layers['container_class'] ); ?>">
				<div class="mrn-layout-grid mrn-layout-grid--tabbed-layout mrn-ui__body">
					<?php if ( '' !== $label || '' !== $heading || '' !== $subheading ) : ?>
						<header class="mrn-layout-content mrn-layout-content--text mrn-tabbed-layout__header mrn-ui__head">
							<?php if ( '' !== $label ) : ?>
								<<?php echo esc_html( $label_tag ); ?> class="mrn-ui__label"><?php echo function_exists( 'mrn_base_stack_format_heading_inline_html' ) ? mrn_base_stack_format_heading_inline_html( $label ) : esc_html( $label ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></<?php echo esc_html( $label_tag ); ?>>
							<?php endif; ?>
							<?php if ( '' !== $heading ) : ?>
								<<?php echo esc_html( $heading_tag ); ?> class="mrn-ui__heading"><?php echo function_exists( 'mrn_base_stack_format_heading_inline_html' ) ? mrn_base_stack_format_heading_inline_html( $heading ) : esc_html( $heading ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></<?php echo esc_html( $heading_tag ); ?>>
							<?php endif; ?>
							<?php if ( '' !== $subheading ) : ?>
								<<?php echo esc_html( $subheading_tag ); ?> class="mrn-ui__sub"><?php echo function_exists( 'mrn_base_stack_format_heading_inline_html' ) ? mrn_base_stack_format_heading_inline_html( $subheading ) : esc_html( $subheading ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></<?php echo esc_html( $subheading_tag ); ?>>
							<?php endif; ?>
						</header>
					<?php endif; ?>

					<div class="mrn-tabbed-layout__body">
							<div class="mrn-tabbed-layout__nav-wrap">
								<div class="mrn-tabbed-layout__nav" role="tablist" aria-label="<?php echo esc_attr( $tablist_label ); ?>">
									<?php foreach ( $rendered_tabs as $tab_index => $tab_item ) : ?>
										<?php
										$button_classes = array( 'mrn-tabbed-layout__tab' );

										if ( ! empty( $tab_item['has_image'] ) ) {
											$button_classes[] = 'mrn-tabbed-layout__tab--has-image';
										}

										if ( ! empty( $tab_item['is_image_only'] ) ) {
											$button_classes[] = 'mrn-tabbed-layout__tab--image-only';
										}

										if ( 0 === $tab_index ) {
											$button_classes[] = 'is-active';
										}
										?>
										<button
											id="<?php echo esc_attr( $tab_item['button_id'] ); ?>"
											class="<?php echo esc_attr( implode( ' ', $button_classes ) ); ?>"
											type="button"
											role="tab"
											aria-selected="<?php echo 0 === $tab_index ? 'true' : 'false'; ?>"
											aria-controls="<?php echo esc_attr( $tab_item['panel_id'] ); ?>"
											tabindex="<?php echo 0 === $tab_index ? '0' : '-1'; ?>"
											data-mrn-tab-button
										>
											<?php if ( ! empty( $tab_item['has_image'] ) ) : ?>
												<span class="mrn-tabbed-layout__tab-media" aria-hidden="true">
													<?php if ( ! empty( $tab_item['image']['ID'] ) ) : ?>
														<?php
														echo wp_get_attachment_image(
															(int) $tab_item['image']['ID'],
															'medium',
															false,
															array(
																'class' => 'mrn-tabbed-layout__tab-image',
																'alt'   => '',
															)
														); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
														?>
													<?php elseif ( ! empty( $tab_item['image']['url'] ) ) : ?>
														<img class="mrn-tabbed-layout__tab-image" src="<?php echo esc_url( $tab_item['image']['url'] ); ?>" alt="">
													<?php endif; ?>
												</span>
											<?php endif; ?>

											<?php if ( '' !== $tab_item['label'] ) : ?>
												<span class="mrn-tabbed-layout__tab-label"><?php echo esc_html( $tab_item['label'] ); ?></span>
											<?php else : ?>
												<span class="screen-reader-text"><?php echo esc_html( $tab_item['accessible_label'] ); ?></span>
											<?php endif; ?>
										</button>
									<?php endforeach; ?>
								</div>
							</div>

							<div class="mrn-tabbed-layout__panels<?php echo $uses_slider ? ' mrn-tabbed-layout__panels--slider splide' : ''; ?>"<?php echo $uses_slider ? ' data-mrn-tab-slider' : ''; ?>>
								<?php if ( $uses_slider ) : ?>
									<div class="splide__track mrn-tabbed-layout__panel-track">
										<ul class="splide__list mrn-tabbed-layout__panel-list">
								<?php endif; ?>

								<?php foreach ( $rendered_tabs as $tab_index => $tab_item ) : ?>
									<?php if ( $uses_slider ) : ?>
										<li
											class="mrn-tabbed-layout__panel splide__slide<?php echo 0 === $tab_index ? ' is-active' : ''; ?>"
											data-mrn-tab-panel
										>
											<div
												id="<?php echo esc_attr( $tab_item['panel_id'] ); ?>"
												class="mrn-tabbed-layout__panel-body"
												role="tabpanel"
												aria-labelledby="<?php echo esc_attr( $tab_item['button_id'] ); ?>"
												aria-hidden="<?php echo 0 === $tab_index ? 'false' : 'true'; ?>"
												data-mrn-tab-panel-content
												>
													<?php echo $tab_item['markup']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Nested builder rows escape their own output. ?>
												</div>
										</li>
									<?php else : ?>
										<div
											id="<?php echo esc_attr( $tab_item['panel_id'] ); ?>"
											class="mrn-tabbed-layout__panel<?php echo 0 === $tab_index ? ' is-active' : ''; ?>"
											role="tabpanel"
											aria-labelledby="<?php echo esc_attr( $tab_item['button_id'] ); ?>"
											data-mrn-tab-panel
										>
											<?php echo $tab_item['markup']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Nested builder rows escape their own output. ?>
										</div>
									<?php endif; ?>
								<?php endforeach; ?>

								<?php if ( $uses_slider ) : ?>
										</ul>
									</div>
								<?php endif; ?>
							</div>
						</div>
				</div>
			</div>
		</div>
	</div>
</section>
