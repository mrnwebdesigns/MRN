<?php
/**
 * Builder row: Slider.
 *
 * @package mrn-base-stack
 */

$context          = is_array( $args ?? null ) ? $args : array();
$row              = isset( $context['row'] ) && is_array( $context['row'] ) ? $context['row'] : array();
$row_index        = isset( $context['index'] ) ? (int) $context['index'] : 0;
$label            = isset( $row['label'] ) ? trim( (string) $row['label'] ) : '';
$label_tag        = function_exists( 'mrn_base_stack_normalize_text_tag' ) ? mrn_base_stack_normalize_text_tag( $row['label_tag'] ?? '', 'p' ) : 'p';
$heading          = isset( $row['heading'] ) ? trim( (string) $row['heading'] ) : '';
$heading_tag      = isset( $row['heading_tag'] ) ? strtolower( (string) $row['heading_tag'] ) : 'h2';
$subheading       = isset( $row['subheading'] ) ? trim( (string) $row['subheading'] ) : '';
$subheading_tag   = isset( $row['subheading_tag'] ) ? strtolower( (string) $row['subheading_tag'] ) : 'p';
$items            = isset( $row['slider_items'] ) && is_array( $row['slider_items'] ) ? $row['slider_items'] : array();
$background_color = isset( $row['background_color'] ) ? trim( (string) $row['background_color'] ) : '';
$bottom_accent    = ! empty( $row['bottom_accent'] );
$accent_slug      = isset( $row['bottom_accent_style'] ) ? (string) $row['bottom_accent_style'] : '';
$width_layers     = function_exists( 'mrn_base_stack_get_section_width_layers' )
	? mrn_base_stack_get_section_width_layers( $row['section_width'] ?? '', 'wide', 'wide' )
	: array(
		'width'           => 'wide',
		'section_class'   => 'mrn-layout-section--contained',
		'container_class' => 'mrn-layout-container--wide',
	);
$slides_per_page  = isset( $row['per_page'] ) ? max( 1, min( 3, (int) $row['per_page'] ) ) : 1;
$show_arrows      = ! empty( $row['show_arrows'] );
$show_pagination  = ! empty( $row['show_pagination'] );
$pause_on_hover   = ! array_key_exists( 'pause_on_hover', $row ) || ! empty( $row['pause_on_hover'] );
$autoplay         = ! empty( $row['autoplay'] );
$delay_start      = isset( $row['delay_start'] ) ? max( 0, (float) $row['delay_start'] ) : 0;
$delay_time       = isset( $row['delay_time'] ) ? max( 1, (float) $row['delay_time'] ) : 5;
$time_on_slide    = isset( $row['time_on_slide'] ) ? max( 100, (int) $row['time_on_slide'] ) : 600;

$allowed_tags = array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'span', 'div' );
if ( ! in_array( $heading_tag, $allowed_tags, true ) ) {
	$heading_tag = 'h2';
}

if ( ! in_array( $subheading_tag, $allowed_tags, true ) ) {
	$subheading_tag = 'p';
}

$legacy_link_style = isset( $row['link_style'] ) ? sanitize_key( (string) $row['link_style'] ) : 'link';
if ( ! in_array( $legacy_link_style, array( 'link', 'button' ), true ) ) {
	$legacy_link_style = 'link';
}
$legacy_link_icon_fields = array(
	'link_icon_source'     => isset( $row['link_icon_source'] ) ? sanitize_key( (string) $row['link_icon_source'] ) : '',
	'link_icon_dashicon'   => isset( $row['link_icon_dashicon'] ) ? trim( (string) $row['link_icon_dashicon'] ) : '',
	'link_icon_fa_class'   => isset( $row['link_icon_fa_class'] ) ? trim( (string) $row['link_icon_fa_class'] ) : '',
	'link_icon_media_icon' => $row['link_icon_media_icon'] ?? null,
	'link_icon_position'   => isset( $row['link_icon_position'] ) ? sanitize_key( (string) $row['link_icon_position'] ) : '',
	'link_icon_gap'        => isset( $row['link_icon_gap'] ) ? $row['link_icon_gap'] : '',
);

$has_items      = false;
$has_slide_data = false;
foreach ( $items as $item ) {
	if ( ! is_array( $item ) ) {
		continue;
	}

	$item_label   = isset( $item['label'] ) ? (string) $item['label'] : '';
	$item_heading = isset( $item['heading'] ) ? (string) $item['heading'] : '';
	$item_subhead = isset( $item['subheading'] ) ? (string) $item['subheading'] : '';
	$item_content = isset( $item['content'] ) ? (string) $item['content'] : '';
	$item_link    = function_exists( 'mrn_base_stack_get_repeater_item_primary_link' )
		? mrn_base_stack_get_repeater_item_primary_link(
			$item,
			array(
				'fallback_link_style'  => $legacy_link_style,
				'fallback_icon_fields' => $legacy_link_icon_fields,
			)
		)
		: array();
	$item_image   = $item['image'] ?? null;
	$item_has_data = (
		'' !== trim( wp_strip_all_tags( $item_label ) ) ||
		'' !== trim( wp_strip_all_tags( $item_heading ) ) ||
		'' !== trim( wp_strip_all_tags( $item_subhead ) ) ||
		'' !== trim( wp_strip_all_tags( $item_content ) ) ||
		! empty( $item_link['url'] )
	);

	if (
		$item_has_data ||
		( function_exists( 'mrn_base_stack_image_has_content' ) && mrn_base_stack_image_has_content( $item_image ) )
	) {
		$has_items = true;
		if ( $item_has_data ) {
			$has_slide_data = true;
			break;
		}
	}
}

if ( '' === $label && '' === $heading && '' === $subheading && ! $has_items ) {
	return;
}

$slider_id       = 'mrn-slider-' . $row_index . '-' . wp_generate_password( 6, false, false );
$section_classes = array(
	'mrn-content-builder__row',
	'mrn-content-builder__row--slider',
	'mrn-content-builder__row--slider-link-' . sanitize_html_class( $legacy_link_style ),
);
$section_styles  = array();

if ( $has_items && ! $has_slide_data ) {
	$section_classes[] = 'mrn-content-builder__row--slider-images-only';
}

if ( '' !== $background_color && function_exists( 'mrn_site_colors_get_css_var' ) ) {
	$section_styles[] = '--mrn-slider-row-bg: var(' . mrn_site_colors_get_css_var( $background_color ) . ')';
}

$display_contract  = function_exists( 'mrn_base_stack_get_builder_display_contract' ) ? mrn_base_stack_get_builder_display_contract( $row, 'slider' ) : array(
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
	<div class="mrn-layout-section mrn-layout-section--slider <?php echo esc_attr( $width_layers['section_class'] ); ?><?php echo $is_full_width ? ' mrn-layout-surface' : ''; ?>"<?php echo $is_full_width && '' !== $surface_style ? ' style="' . esc_attr( $surface_style ) . '"' : ''; ?>>
		<div class="mrn-layout-container <?php echo esc_attr( $width_layers['container_class'] ); ?><?php echo ! $is_full_width ? ' mrn-layout-surface' : ''; ?>"<?php echo ! $is_full_width && '' !== $surface_style ? ' style="' . esc_attr( $surface_style ) . '"' : ''; ?>>
			<div class="mrn-layout-grid mrn-layout-grid--slider mrn-layout-grid--slider-shell mrn-ui__body">
		<?php if ( '' !== $label || '' !== $heading || '' !== $subheading ) : ?>
				<header class="mrn-layout-content mrn-layout-content--text mrn-slider-row__header mrn-slider-row__header--slider-shell mrn-ui__head">
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

		<?php if ( $has_items ) : ?>
			<div
				id="<?php echo esc_attr( $slider_id ); ?>"
				class="splide mrn-splide mrn-slider-row__splide mrn-slider-row__splide--slider-shell"
				aria-label="<?php echo esc_attr( '' !== $heading ? wp_strip_all_tags( $heading ) : 'Content slider' ); ?>"
				data-per-page="<?php echo esc_attr( (string) $slides_per_page ); ?>"
				data-arrows="<?php echo esc_attr( $show_arrows ? 'true' : 'false' ); ?>"
				data-pagination="<?php echo esc_attr( $show_pagination ? 'true' : 'false' ); ?>"
				data-pause-on-hover="<?php echo esc_attr( $pause_on_hover ? 'true' : 'false' ); ?>"
				data-autoplay="<?php echo esc_attr( $autoplay ? 'true' : 'false' ); ?>"
				data-delay-start="<?php echo esc_attr( (string) $delay_start ); ?>"
				data-delay-time="<?php echo esc_attr( (string) $delay_time ); ?>"
				data-time-on-slide="<?php echo esc_attr( (string) $time_on_slide ); ?>"
			>
				<div class="splide__track">
						<ul class="splide__list mrn-ui__items">
						<?php foreach ( $items as $item ) : ?>
							<?php
							if ( ! is_array( $item ) ) {
								continue;
							}

								$item_label     = isset( $item['label'] ) ? trim( (string) $item['label'] ) : '';
								$item_label_tag = function_exists( 'mrn_base_stack_normalize_text_tag' ) ? mrn_base_stack_normalize_text_tag( $item['label_tag'] ?? '', 'p' ) : 'p';
								$item_heading   = isset( $item['heading'] ) ? trim( (string) $item['heading'] ) : '';
								$item_tag       = isset( $item['heading_tag'] ) ? strtolower( (string) $item['heading_tag'] ) : 'h3';
								$item_subheading = isset( $item['subheading'] ) ? trim( (string) $item['subheading'] ) : '';
								$item_subheading_tag = isset( $item['subheading_tag'] ) ? strtolower( (string) $item['subheading_tag'] ) : 'p';
								$item_content   = isset( $item['content'] ) ? (string) $item['content'] : '';
								$item_link_data = function_exists( 'mrn_base_stack_get_repeater_item_primary_link' )
									? mrn_base_stack_get_repeater_item_primary_link(
										$item,
										array(
											'fallback_link_style'  => $legacy_link_style,
											'fallback_icon_fields' => $legacy_link_icon_fields,
										)
									)
									: array(
										'url'        => '',
										'text'       => '',
										'target'     => '',
										'link_style' => $legacy_link_style,
									);
								$item_link_url  = isset( $item_link_data['url'] ) ? trim( (string) $item_link_data['url'] ) : '';
								$item_image     = $item['image'] ?? null;
								$item_has_image = function_exists( 'mrn_base_stack_image_has_content' ) ? mrn_base_stack_image_has_content( $item_image ) : false;
								$item_link_display = isset( $item['link_display'] ) ? sanitize_key( (string) $item['link_display'] ) : 'visible';
								$item_classes   = array(
									'mrn-slider-row__slide',
									'mrn-slider-row__slide--slider-shell',
									'mrn-ui__item',
								);

								if ( ! in_array( $item_tag, $allowed_tags, true ) ) {
									$item_tag = 'h3';
								}

								if ( ! in_array( $item_subheading_tag, $allowed_tags, true ) ) {
									$item_subheading_tag = 'p';
								}

								if ( ! in_array( $item_link_display, array( 'visible', 'full_slide' ), true ) ) {
									$item_link_display = 'visible';
								}

								if (
									'' === $item_label &&
									'' === $item_heading &&
									'' === $item_subheading &&
									'' === trim( wp_strip_all_tags( $item_content ) ) &&
									'' === $item_link_url &&
									! $item_has_image
								) {
									continue;
								}

								$use_full_slide_link = 'full_slide' === $item_link_display && '' !== $item_link_url;
								if ( $use_full_slide_link ) {
									$item_classes[] = 'mrn-slider-row__slide--full-link';
								}
								?>
							<li class="splide__slide">
									<article class="<?php echo esc_attr( implode( ' ', $item_classes ) ); ?>">
										<?php if ( $item_has_image ) : ?>
											<div class="mrn-slider-row__media mrn-ui__media">
											<?php echo function_exists( 'mrn_base_stack_get_attachment_image' ) ? mrn_base_stack_get_attachment_image( $item_image, 'mrn-content-media' ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
										</div>
									<?php endif; ?>

										<div class="mrn-slider-row__slide-content mrn-ui__body">
											<?php if ( '' !== $item_label || '' !== $item_heading || '' !== $item_subheading ) : ?>
												<div class="mrn-ui__head">
													<?php if ( '' !== $item_label ) : ?>
														<<?php echo esc_html( $item_label_tag ); ?> class="mrn-ui__label"><?php echo function_exists( 'mrn_base_stack_format_heading_inline_html' ) ? mrn_base_stack_format_heading_inline_html( $item_label ) : esc_html( $item_label ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></<?php echo esc_html( $item_label_tag ); ?>>
													<?php endif; ?>

													<?php if ( '' !== $item_heading ) : ?>
														<<?php echo esc_html( $item_tag ); ?> class="mrn-ui__heading"><?php echo function_exists( 'mrn_base_stack_format_heading_inline_html' ) ? mrn_base_stack_format_heading_inline_html( $item_heading ) : esc_html( $item_heading ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></<?php echo esc_html( $item_tag ); ?>>
													<?php endif; ?>

													<?php if ( '' !== $item_subheading ) : ?>
														<<?php echo esc_html( $item_subheading_tag ); ?> class="mrn-ui__sub"><?php echo function_exists( 'mrn_base_stack_format_heading_inline_html' ) ? mrn_base_stack_format_heading_inline_html( $item_subheading ) : esc_html( $item_subheading ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></<?php echo esc_html( $item_subheading_tag ); ?>>
													<?php endif; ?>
												</div>
											<?php endif; ?>

										<?php if ( '' !== trim( $item_content ) ) : ?>
												<div class="mrn-ui__text">
												<?php echo apply_filters( 'the_content', $item_content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
											</div>
										<?php endif; ?>

											<?php if ( '' !== $item_link_url && ! $use_full_slide_link ) : ?>
												<p class="mrn-slider-row__slide-link-wrap">
													<?php
													$item_link_style         = isset( $item_link_data['link_style'] ) && in_array( $item_link_data['link_style'], array( 'link', 'button' ), true )
														? (string) $item_link_data['link_style']
														: 'link';
													$item_link_tag           = function_exists( 'mrn_rbl_get_content_link_tag_name' ) ? mrn_rbl_get_content_link_tag_name( $item_link_data ) : 'a';
													$item_link_attr_html     = function_exists( 'mrn_rbl_get_content_link_html_attributes' ) ? mrn_rbl_get_content_link_html_attributes( $item_link_data ) : '';
													$item_link_class_names   = 'mrn-ui__link ' . ( 'button' === $item_link_style ? 'mrn-ui__link--button' : 'mrn-ui__link--text' );
													$item_link_icon_markup   = function_exists( 'mrn_base_stack_get_button_link_icon_markup' )
														? mrn_base_stack_get_button_link_icon_markup( $item_link_data )
														: '';
													$item_link_icon_position = function_exists( 'mrn_base_stack_get_button_link_icon_position' )
														? mrn_base_stack_get_button_link_icon_position( $item_link_data )
														: 'left';

													if ( function_exists( 'mrn_rbl_get_content_link_custom_class_names' ) ) {
														$item_link_custom_classes = mrn_rbl_get_content_link_custom_class_names( $item_link_data );
														if ( '' !== $item_link_custom_classes ) {
															$item_link_class_names .= ' ' . $item_link_custom_classes;
														}
													}
													?>
													<<?php echo esc_html( $item_link_tag ); ?>
														class="<?php echo esc_attr( trim( $item_link_class_names ) ); ?>"
														<?php echo '' !== $item_link_attr_html ? $item_link_attr_html : 'href="' . esc_url( $item_link_url ) . '"'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
													>
														<?php
														$link_label = isset( $item_link_data['text'] ) && is_string( $item_link_data['text'] ) && '' !== $item_link_data['text']
															? $item_link_data['text']
															: 'Learn More';
															echo wp_kses_post(
																function_exists( 'mrn_base_stack_get_compact_link_label_markup' )
																	? mrn_base_stack_get_compact_link_label_markup( $link_label, $item_link_icon_markup, $item_link_icon_position )
																	: esc_html( $link_label )
															);
														?>
													</<?php echo esc_html( $item_link_tag ); ?>>
												</p>
											<?php endif; ?>
										</div>
										<?php if ( $use_full_slide_link ) : ?>
											<?php
											$item_link_target     = isset( $item_link_data['target'] ) ? trim( (string) $item_link_data['target'] ) : '';
											$item_link_label      = isset( $item_link_data['text'] ) && is_string( $item_link_data['text'] ) && '' !== trim( $item_link_data['text'] )
												? trim( $item_link_data['text'] )
												: '';
											$item_link_aria_label = '' !== $item_link_label
												? $item_link_label
												: ( '' !== $item_heading ? $item_heading : __( 'View slide', 'mrn-base-stack' ) );
											?>
											<a class="mrn-slider-row__slide-overlay-link" href="<?php echo esc_url( $item_link_url ); ?>"<?php echo '' !== $item_link_target ? ' target="' . esc_attr( $item_link_target ) . '"' : ''; ?><?php echo '_blank' === $item_link_target ? ' rel="noopener noreferrer"' : ''; ?>>
												<span class="screen-reader-text"><?php echo esc_html( $item_link_aria_label ); ?></span>
											</a>
										<?php endif; ?>
								</article>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			</div>
		<?php endif; ?>
			</div>
		</div>
	</div>
</section>
