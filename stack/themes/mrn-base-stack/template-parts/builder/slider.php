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
$link_style       = isset( $row['link_style'] ) ? sanitize_key( (string) $row['link_style'] ) : 'link';
$link_color       = isset( $row['link_color'] ) ? trim( (string) $row['link_color'] ) : '';
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

if ( ! in_array( $link_style, array( 'link', 'button' ), true ) ) {
	$link_style = 'link';
}

$has_items = false;
foreach ( $items as $item ) {
	if ( ! is_array( $item ) ) {
		continue;
	}

	$item_label   = isset( $item['label'] ) ? (string) $item['label'] : '';
	$item_heading = isset( $item['heading'] ) ? (string) $item['heading'] : '';
	$item_content = isset( $item['content'] ) ? (string) $item['content'] : '';
	$item_link    = isset( $item['link'] ) && is_array( $item['link'] ) ? $item['link'] : array();
	$item_image   = isset( $item['image'] ) && is_array( $item['image'] ) ? $item['image'] : array();

	if (
		'' !== trim( wp_strip_all_tags( $item_label ) ) ||
		'' !== trim( wp_strip_all_tags( $item_heading ) ) ||
		'' !== trim( wp_strip_all_tags( $item_content ) ) ||
		! empty( $item_link['url'] ) ||
		! empty( $item_image['ID'] ) ||
		! empty( $item_image['url'] )
	) {
		$has_items = true;
		break;
	}
}

if ( '' === $label && '' === $heading && '' === $subheading && ! $has_items ) {
	return;
}

$slider_id       = 'mrn-slider-' . $row_index . '-' . wp_generate_password( 6, false, false );
$section_classes = array(
	'mrn-content-builder__row',
	'mrn-content-builder__row--slider',
	'mrn-content-builder__row--slider-link-' . sanitize_html_class( $link_style ),
);
$section_styles  = array();

if ( '' !== $background_color && function_exists( 'mrn_site_colors_get_css_var' ) ) {
	$section_styles[] = '--mrn-slider-row-bg: var(' . mrn_site_colors_get_css_var( $background_color ) . ')';
}

if ( '' !== $link_color && function_exists( 'mrn_site_colors_get_css_var' ) ) {
	$section_styles[] = '--mrn-slider-row-link-color: var(' . mrn_site_colors_get_css_var( $link_color ) . ')';
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
?>
<section class="<?php echo esc_attr( implode( ' ', $section_classes ) ); ?>"<?php echo '' !== $section_attr_html ? ' ' . $section_attr_html : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="mrn-layout-section mrn-layout-section--slider <?php echo esc_attr( $width_layers['section_class'] ); ?><?php echo $is_full_width ? ' mrn-layout-surface' : ''; ?>"<?php echo $is_full_width && '' !== $surface_style ? ' style="' . esc_attr( $surface_style ) . '"' : ''; ?>>
		<div class="mrn-layout-container <?php echo esc_attr( $width_layers['container_class'] ); ?><?php echo ! $is_full_width ? ' mrn-layout-surface' : ''; ?>"<?php echo ! $is_full_width && '' !== $surface_style ? ' style="' . esc_attr( $surface_style ) . '"' : ''; ?>>
			<div class="mrn-layout-grid mrn-layout-grid--slider mrn-layout-grid--slider-shell">
		<?php if ( '' !== $label || '' !== $heading || '' !== $subheading ) : ?>
			<header class="mrn-layout-content mrn-layout-content--text mrn-slider-row__header mrn-slider-row__header--slider-shell">
				<?php if ( '' !== $label ) : ?>
					<<?php echo esc_html( $label_tag ); ?> class="mrn-slider-row__label"><?php echo function_exists( 'mrn_base_stack_format_heading_inline_html' ) ? mrn_base_stack_format_heading_inline_html( $label ) : esc_html( $label ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></<?php echo esc_html( $label_tag ); ?>>
				<?php endif; ?>
				<?php if ( '' !== $heading ) : ?>
					<<?php echo esc_html( $heading_tag ); ?> class="mrn-slider-row__heading"><?php echo function_exists( 'mrn_base_stack_format_heading_inline_html' ) ? mrn_base_stack_format_heading_inline_html( $heading ) : esc_html( $heading ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></<?php echo esc_html( $heading_tag ); ?>>
				<?php endif; ?>
				<?php if ( '' !== $subheading ) : ?>
					<<?php echo esc_html( $subheading_tag ); ?> class="mrn-slider-row__subheading"><?php echo function_exists( 'mrn_base_stack_format_heading_inline_html' ) ? mrn_base_stack_format_heading_inline_html( $subheading ) : esc_html( $subheading ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></<?php echo esc_html( $subheading_tag ); ?>>
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
					<ul class="splide__list">
						<?php foreach ( $items as $item ) : ?>
							<?php
							if ( ! is_array( $item ) ) {
								continue;
							}

							$item_label     = isset( $item['label'] ) ? trim( (string) $item['label'] ) : '';
							$item_label_tag = function_exists( 'mrn_base_stack_normalize_text_tag' ) ? mrn_base_stack_normalize_text_tag( $item['label_tag'] ?? '', 'p' ) : 'p';
							$item_heading   = isset( $item['heading'] ) ? trim( (string) $item['heading'] ) : '';
							$item_tag       = isset( $item['heading_tag'] ) ? strtolower( (string) $item['heading_tag'] ) : 'h3';
							$item_content   = isset( $item['content'] ) ? (string) $item['content'] : '';
							$item_link      = isset( $item['link'] ) && is_array( $item['link'] ) ? $item['link'] : array();
							$item_image     = isset( $item['image'] ) && is_array( $item['image'] ) ? $item['image'] : array();

							if ( ! in_array( $item_tag, $allowed_tags, true ) ) {
								$item_tag = 'h3';
							}

							if (
								'' === $item_label &&
								'' === $item_heading &&
								'' === trim( wp_strip_all_tags( $item_content ) ) &&
								empty( $item_link['url'] ) &&
								empty( $item_image['ID'] ) &&
								empty( $item_image['url'] )
							) {
								continue;
							}
							?>
							<li class="splide__slide">
								<article class="mrn-slider-row__slide mrn-slider-row__slide--slider-shell">
									<?php if ( ! empty( $item_image['ID'] ) ) : ?>
										<div class="mrn-slider-row__media">
											<?php echo wp_get_attachment_image( (int) $item_image['ID'], 'large' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
										</div>
									<?php elseif ( ! empty( $item_image['url'] ) ) : ?>
										<div class="mrn-slider-row__media">
											<img src="<?php echo esc_url( $item_image['url'] ); ?>" alt="<?php echo esc_attr( $item_image['alt'] ?? '' ); ?>">
										</div>
									<?php endif; ?>

									<div class="mrn-slider-row__slide-content">
										<?php if ( '' !== $item_label ) : ?>
											<<?php echo esc_html( $item_label_tag ); ?> class="mrn-slider-row__slide-label"><?php echo function_exists( 'mrn_base_stack_format_heading_inline_html' ) ? mrn_base_stack_format_heading_inline_html( $item_label ) : esc_html( $item_label ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></<?php echo esc_html( $item_label_tag ); ?>>
										<?php endif; ?>

										<?php if ( '' !== $item_heading ) : ?>
											<<?php echo esc_html( $item_tag ); ?> class="mrn-slider-row__slide-heading"><?php echo function_exists( 'mrn_base_stack_format_heading_inline_html' ) ? mrn_base_stack_format_heading_inline_html( $item_heading ) : esc_html( $item_heading ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></<?php echo esc_html( $item_tag ); ?>>
										<?php endif; ?>

										<?php if ( '' !== trim( $item_content ) ) : ?>
											<div class="mrn-slider-row__slide-text">
												<?php echo apply_filters( 'the_content', $item_content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
											</div>
										<?php endif; ?>

										<?php if ( ! empty( $item_link['url'] ) ) : ?>
											<p class="mrn-slider-row__slide-link-wrap">
												<a
													class="mrn-slider-row__slide-link <?php echo 'button' === $link_style ? 'mrn-slider-row__slide-link--button' : 'mrn-slider-row__slide-link--text'; ?>"
													href="<?php echo esc_url( $item_link['url'] ); ?>"
													<?php if ( ! empty( $item_link['target'] ) ) : ?>
														target="<?php echo esc_attr( $item_link['target'] ); ?>"
													<?php endif; ?>
													<?php if ( ! empty( $item_link['target'] ) && '_blank' === $item_link['target'] ) : ?>
														rel="noopener noreferrer"
													<?php endif; ?>
												>
													<?php echo esc_html( $item_link['title'] ?? 'Learn More' ); ?>
												</a>
											</p>
										<?php endif; ?>
									</div>
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
