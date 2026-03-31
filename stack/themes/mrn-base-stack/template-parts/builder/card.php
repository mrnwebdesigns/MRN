<?php
/**
 * Builder row: Card.
 *
 * @package mrn-base-stack
 */

$context          = is_array( $args ?? null ) ? $args : array();
$row              = isset( $context['row'] ) && is_array( $context['row'] ) ? $context['row'] : array();
$label            = isset( $row['label'] ) ? trim( (string) $row['label'] ) : '';
$heading          = isset( $row['text_field'] ) ? trim( (string) $row['text_field'] ) : '';
$heading_tag      = isset( $row['text_field_tag'] ) ? strtolower( (string) $row['text_field_tag'] ) : 'h2';
$items            = isset( $row['card_items'] ) && is_array( $row['card_items'] ) ? $row['card_items'] : array();
$section_link     = isset( $row['link'] ) && is_array( $row['link'] ) ? $row['link'] : array();
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

$has_items = false;
foreach ( $items as $item ) {
	if ( ! is_array( $item ) ) {
		continue;
	}

	$item_text = isset( $item['text'] ) ? (string) $item['text'] : '';
	$item_link = isset( $item['link'] ) && is_array( $item['link'] ) ? $item['link'] : array();
	$item_img  = isset( $item['image'] ) && is_array( $item['image'] ) ? $item['image'] : array();

	if ( '' !== trim( wp_strip_all_tags( $item_text ) ) || ! empty( $item_link['url'] ) || ! empty( $item_img['ID'] ) || ! empty( $item_img['url'] ) ) {
		$has_items = true;
		break;
	}
}

if ( '' === $label && '' === $heading && ! $has_items && empty( $section_link['url'] ) ) {
	return;
}

$section_styles = array();
if ( '' !== $background_color && function_exists( 'mrn_site_colors_get_css_var' ) ) {
	$section_styles[] = '--mrn-card-row-bg: var(' . mrn_site_colors_get_css_var( $background_color ) . ')';
}

$section_classes = array(
	'mrn-content-builder__row',
	'mrn-content-builder__row--card',
);
$accent_contract = function_exists( 'mrn_base_stack_get_builder_accent_contract' ) ? mrn_base_stack_get_builder_accent_contract( $bottom_accent, $accent_slug ) : array(
	'classes'    => $bottom_accent ? array( 'has-bottom-accent' ) : array(),
	'attributes' => array(),
);
$section_classes = function_exists( 'mrn_base_stack_merge_builder_section_classes' ) ? mrn_base_stack_merge_builder_section_classes( $section_classes, $accent_contract ) : $section_classes;
$section_attrs   = isset( $accent_contract['attributes'] ) && is_array( $accent_contract['attributes'] ) ? $accent_contract['attributes'] : array();
$section_attr_html = function_exists( 'mrn_base_stack_get_html_attributes' ) ? mrn_base_stack_get_html_attributes( $section_attrs ) : '';
$surface_style     = function_exists( 'mrn_base_stack_get_inline_style_attribute' ) ? mrn_base_stack_get_inline_style_attribute( $section_styles ) : implode( '; ', $section_styles );
$is_full_width     = 'full-width' === ( $width_layers['width'] ?? '' );
?>
<section class="<?php echo esc_attr( implode( ' ', $section_classes ) ); ?>"<?php echo '' !== $section_attr_html ? ' ' . $section_attr_html : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="mrn-layout-section mrn-layout-section--card <?php echo esc_attr( $width_layers['section_class'] ); ?><?php echo $is_full_width ? ' mrn-layout-surface' : ''; ?>"<?php echo $is_full_width && '' !== $surface_style ? ' style="' . esc_attr( $surface_style ) . '"' : ''; ?>>
		<div class="mrn-layout-container <?php echo esc_attr( $width_layers['container_class'] ); ?><?php echo ! $is_full_width ? ' mrn-layout-surface' : ''; ?>"<?php echo ! $is_full_width && '' !== $surface_style ? ' style="' . esc_attr( $surface_style ) . '"' : ''; ?>>
			<div class="mrn-layout-grid mrn-layout-grid--card">
		<?php if ( '' !== $label ) : ?>
			<div class="mrn-layout-content mrn-layout-content--text mrn-card-row__label"><?php echo function_exists( 'mrn_base_stack_format_heading_inline_html' ) ? mrn_base_stack_format_heading_inline_html( $label ) : esc_html( $label ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
		<?php endif; ?>

		<?php if ( '' !== $heading ) : ?>
			<<?php echo esc_html( $heading_tag ); ?> class="mrn-layout-content mrn-layout-content--text mrn-card-row__heading"><?php echo function_exists( 'mrn_base_stack_format_heading_inline_html' ) ? mrn_base_stack_format_heading_inline_html( $heading ) : esc_html( $heading ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></<?php echo esc_html( $heading_tag ); ?>>
		<?php endif; ?>

		<?php if ( $has_items ) : ?>
			<div class="mrn-card-row__grid mrn-card-row__grid--card-deck">
				<?php foreach ( $items as $item ) : ?>
					<?php
					if ( ! is_array( $item ) ) {
						continue;
					}

					$item_text  = isset( $item['text'] ) ? (string) $item['text'] : '';
					$item_link  = isset( $item['link'] ) && is_array( $item['link'] ) ? $item['link'] : array();
					$item_image = isset( $item['image'] ) && is_array( $item['image'] ) ? $item['image'] : array();

					if ( '' === trim( wp_strip_all_tags( $item_text ) ) && empty( $item_link['url'] ) && empty( $item_image['ID'] ) && empty( $item_image['url'] ) ) {
						continue;
					}
					?>
					<article class="mrn-card-row__item mrn-card-row__item--card-deck">
						<?php if ( ! empty( $item_image['ID'] ) ) : ?>
							<div class="mrn-card-row__image">
								<?php echo wp_get_attachment_image( (int) $item_image['ID'], 'large' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</div>
						<?php elseif ( ! empty( $item_image['url'] ) ) : ?>
							<div class="mrn-card-row__image">
								<img src="<?php echo esc_url( $item_image['url'] ); ?>" alt="<?php echo esc_attr( $item_image['alt'] ?? '' ); ?>">
							</div>
						<?php endif; ?>

						<?php if ( '' !== trim( wp_strip_all_tags( $item_text ) ) ) : ?>
							<div class="mrn-card-row__text">
								<?php echo apply_filters( 'the_content', $item_text ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</div>
						<?php endif; ?>

						<?php if ( ! empty( $item_link['url'] ) ) : ?>
							<p class="mrn-card-row__item-link">
								<a href="<?php echo esc_url( $item_link['url'] ); ?>"<?php echo ! empty( $item_link['target'] ) ? ' target="' . esc_attr( $item_link['target'] ) . '"' : ''; ?><?php echo ! empty( $item_link['target'] ) && '_blank' === $item_link['target'] ? ' rel="noopener noreferrer"' : ''; ?>>
									<?php echo esc_html( $item_link['title'] ?? 'Learn More' ); ?>
								</a>
							</p>
						<?php endif; ?>
					</article>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $section_link['url'] ) ) : ?>
			<p class="mrn-card-row__link">
				<a href="<?php echo esc_url( $section_link['url'] ); ?>"<?php echo ! empty( $section_link['target'] ) ? ' target="' . esc_attr( $section_link['target'] ) . '"' : ''; ?><?php echo ! empty( $section_link['target'] ) && '_blank' === $section_link['target'] ? ' rel="noopener noreferrer"' : ''; ?>>
					<?php echo esc_html( $section_link['title'] ?? 'Learn More' ); ?>
				</a>
			</p>
		<?php endif; ?>
			</div>
		</div>
	</div>
</section>
