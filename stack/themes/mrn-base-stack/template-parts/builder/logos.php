<?php
/**
 * Builder row: Logos.
 *
 * @package mrn-base-stack
 */

$context          = is_array( $args ?? null ) ? $args : array();
$row              = isset( $context['row'] ) && is_array( $context['row'] ) ? $context['row'] : array();
$row_index        = isset( $context['index'] ) ? (int) $context['index'] : 0;
$label            = isset( $row['label'] ) ? trim( (string) $row['label'] ) : '';
$heading          = isset( $row['heading'] ) ? trim( (string) $row['heading'] ) : '';
$heading_tag      = isset( $row['heading_tag'] ) ? strtolower( (string) $row['heading_tag'] ) : 'h2';
$items            = isset( $row['logo_items'] ) && is_array( $row['logo_items'] ) ? $row['logo_items'] : array();
$display_mode     = isset( $row['display_mode'] ) ? sanitize_key( (string) $row['display_mode'] ) : 'grid';
$per_page         = isset( $row['per_page'] ) ? max( 3, min( 6, (int) $row['per_page'] ) ) : 6;
$show_arrows      = ! empty( $row['show_arrows'] );
$show_pagination  = ! empty( $row['show_pagination'] );
$pause_on_hover   = ! array_key_exists( 'pause_on_hover', $row ) || ! empty( $row['pause_on_hover'] );
$autoplay         = ! empty( $row['autoplay'] );
$delay_start      = isset( $row['delay_start'] ) ? max( 0, (float) $row['delay_start'] ) : 0;
$delay_time       = isset( $row['delay_time'] ) ? max( 1, (float) $row['delay_time'] ) : 5;
$time_on_slide    = isset( $row['time_on_slide'] ) ? max( 100, (int) $row['time_on_slide'] ) : 600;
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

$allowed_tags = array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'span', 'div' );
if ( ! in_array( $heading_tag, $allowed_tags, true ) ) {
	$heading_tag = 'h2';
}

if ( ! in_array( $display_mode, array( 'grid', 'slider' ), true ) ) {
	$display_mode = 'grid';
}

$valid_items = array();
foreach ( $items as $item ) {
	if ( ! is_array( $item ) ) {
		continue;
	}

	$image = isset( $item['image'] ) && is_array( $item['image'] ) ? $item['image'] : array();
	$link  = isset( $item['link'] ) && is_array( $item['link'] ) ? $item['link'] : array();

	if ( empty( $image['ID'] ) && empty( $image['url'] ) ) {
		continue;
	}

	$valid_items[] = array(
		'image' => $image,
		'link'  => $link,
	);
}

if ( '' === $label && '' === $heading && empty( $valid_items ) ) {
	return;
}

$section_classes = array(
	'mrn-content-builder__row',
	'mrn-content-builder__row--logos',
	'mrn-content-builder__row--logos-' . sanitize_html_class( $display_mode ),
);
$section_styles  = array();

if ( '' !== $background_color && function_exists( 'mrn_site_colors_get_css_var' ) ) {
	$section_styles[] = '--mrn-logos-row-bg: var(' . mrn_site_colors_get_css_var( $background_color ) . ')';
}

$accent_contract = function_exists( 'mrn_base_stack_get_builder_accent_contract' ) ? mrn_base_stack_get_builder_accent_contract( $bottom_accent, $accent_slug ) : array(
	'classes'    => $bottom_accent ? array( 'has-bottom-accent' ) : array(),
	'attributes' => array(),
);
$section_classes = function_exists( 'mrn_base_stack_merge_builder_section_classes' ) ? mrn_base_stack_merge_builder_section_classes( $section_classes, $accent_contract ) : $section_classes;
$section_attrs   = isset( $accent_contract['attributes'] ) && is_array( $accent_contract['attributes'] ) ? $accent_contract['attributes'] : array();
$slider_id       = 'mrn-logos-' . $row_index . '-' . wp_generate_password( 6, false, false );
$section_attr_html = function_exists( 'mrn_base_stack_get_html_attributes' ) ? mrn_base_stack_get_html_attributes( $section_attrs ) : '';
$surface_style     = function_exists( 'mrn_base_stack_get_inline_style_attribute' ) ? mrn_base_stack_get_inline_style_attribute( $section_styles ) : implode( '; ', $section_styles );
$is_full_width     = 'full-width' === ( $width_layers['width'] ?? '' );
?>
<section class="<?php echo esc_attr( implode( ' ', $section_classes ) ); ?>"<?php echo '' !== $section_attr_html ? ' ' . $section_attr_html : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="mrn-layout-section mrn-layout-section--logos <?php echo esc_attr( $width_layers['section_class'] ); ?><?php echo $is_full_width ? ' mrn-layout-surface' : ''; ?>"<?php echo $is_full_width && '' !== $surface_style ? ' style="' . esc_attr( $surface_style ) . '"' : ''; ?>>
		<div class="mrn-layout-container <?php echo esc_attr( $width_layers['container_class'] ); ?><?php echo ! $is_full_width ? ' mrn-layout-surface' : ''; ?>"<?php echo ! $is_full_width && '' !== $surface_style ? ' style="' . esc_attr( $surface_style ) . '"' : ''; ?>>
			<div class="mrn-layout-grid mrn-layout-grid--logos">
		<?php if ( '' !== $label || '' !== $heading ) : ?>
			<header class="mrn-layout-content mrn-layout-content--text mrn-logos-row__header">
				<?php if ( '' !== $label ) : ?>
					<div class="mrn-logos-row__label"><?php echo function_exists( 'mrn_base_stack_format_heading_inline_html' ) ? mrn_base_stack_format_heading_inline_html( $label ) : esc_html( $label ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
				<?php endif; ?>
				<?php if ( '' !== $heading ) : ?>
					<<?php echo esc_html( $heading_tag ); ?> class="mrn-logos-row__heading"><?php echo function_exists( 'mrn_base_stack_format_heading_inline_html' ) ? mrn_base_stack_format_heading_inline_html( $heading ) : esc_html( $heading ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></<?php echo esc_html( $heading_tag ); ?>>
				<?php endif; ?>
			</header>
		<?php endif; ?>

		<?php if ( 'slider' === $display_mode ) : ?>
			<div
				id="<?php echo esc_attr( $slider_id ); ?>"
				class="splide mrn-splide mrn-logos-row__splide"
				aria-label="<?php echo esc_attr( '' !== $heading ? wp_strip_all_tags( $heading ) : 'Logo slider' ); ?>"
				data-per-page="<?php echo esc_attr( (string) $per_page ); ?>"
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
						<?php foreach ( $valid_items as $item ) : ?>
							<?php
							$image = $item['image'];
							$link  = $item['link'];
							$url   = isset( $link['url'] ) ? (string) $link['url'] : '';
							$target = isset( $link['target'] ) ? (string) $link['target'] : '';
							?>
							<li class="splide__slide">
								<div class="mrn-logos-row__item">
									<?php if ( '' !== $url ) : ?>
										<a class="mrn-logos-row__link" href="<?php echo esc_url( $url ); ?>"<?php if ( '' !== $target ) : ?> target="<?php echo esc_attr( $target ); ?>"<?php endif; ?><?php if ( '_blank' === $target ) : ?> rel="noopener noreferrer"<?php endif; ?>>
									<?php endif; ?>
									<?php if ( ! empty( $image['ID'] ) ) : ?>
										<?php echo wp_get_attachment_image( (int) $image['ID'], 'medium' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
									<?php else : ?>
										<img src="<?php echo esc_url( $image['url'] ); ?>" alt="<?php echo esc_attr( $image['alt'] ?? '' ); ?>">
									<?php endif; ?>
									<?php if ( '' !== $url ) : ?>
										</a>
									<?php endif; ?>
								</div>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			</div>
		<?php else : ?>
			<div class="mrn-logos-row__grid mrn-logos-row__grid--columns-<?php echo esc_attr( (string) $per_page ); ?>">
				<?php foreach ( $valid_items as $item ) : ?>
					<?php
					$image  = $item['image'];
					$link   = $item['link'];
					$url    = isset( $link['url'] ) ? (string) $link['url'] : '';
					$target = isset( $link['target'] ) ? (string) $link['target'] : '';
					?>
					<div class="mrn-logos-row__item">
						<?php if ( '' !== $url ) : ?>
							<a class="mrn-logos-row__link" href="<?php echo esc_url( $url ); ?>"<?php if ( '' !== $target ) : ?> target="<?php echo esc_attr( $target ); ?>"<?php endif; ?><?php if ( '_blank' === $target ) : ?> rel="noopener noreferrer"<?php endif; ?>>
						<?php endif; ?>
						<?php if ( ! empty( $image['ID'] ) ) : ?>
							<?php echo wp_get_attachment_image( (int) $image['ID'], 'medium' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php else : ?>
							<img src="<?php echo esc_url( $image['url'] ); ?>" alt="<?php echo esc_attr( $image['alt'] ?? '' ); ?>">
						<?php endif; ?>
						<?php if ( '' !== $url ) : ?>
							</a>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
			</div>
		</div>
	</div>
</section>
