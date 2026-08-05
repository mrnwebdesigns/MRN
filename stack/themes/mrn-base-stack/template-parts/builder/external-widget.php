<?php
/**
 * Builder row: External Widget/iFrame.
 *
 * @package mrn-base-stack
 */

if ( ! function_exists( 'mrn_base_stack_prepare_external_widget_markup' ) ) {
	/**
	 * Add baseline performance and accessibility attributes to trusted iframe embeds.
	 *
	 * @param string $markup        Trusted embed markup.
	 * @param string $embed_title   Fallback iframe title.
	 * @param string $iframe_border Iframe border treatment.
	 * @return string
	 */
	function mrn_base_stack_prepare_external_widget_markup( $markup, $embed_title = '', $iframe_border = 'none' ) {
		$markup        = (string) $markup;
		$embed_title   = trim( wp_strip_all_tags( (string) $embed_title ) );
		$iframe_border = sanitize_key( (string) $iframe_border );

		if ( '' === trim( $markup ) ) {
			return $markup;
		}

		$processor = new WP_HTML_Tag_Processor( $markup );

		while ( $processor->next_tag( array( 'tag_name' => 'IFRAME' ) ) ) {
			$loading = $processor->get_attribute( 'loading' );
			if ( ! is_string( $loading ) || '' === trim( $loading ) ) {
				$processor->set_attribute( 'loading', 'lazy' );
			}

			$referrerpolicy = $processor->get_attribute( 'referrerpolicy' );
			if ( ! is_string( $referrerpolicy ) || '' === trim( $referrerpolicy ) ) {
				$processor->set_attribute( 'referrerpolicy', 'strict-origin-when-cross-origin' );
			}

			$title = $processor->get_attribute( 'title' );
			if ( ! is_string( $title ) || '' === trim( $title ) ) {
				$processor->set_attribute( 'title', '' !== $embed_title ? $embed_title : __( 'Embedded content', 'mrn-base-stack' ) );
			}

			if ( 'none' === $iframe_border ) {
				$processor->set_attribute( 'frameborder', '0' );
			}
		}

		return $processor->get_updated_html();
	}
}

if ( ! function_exists( 'mrn_base_stack_kses_external_widget_markup' ) ) {
	/**
	 * Limit external widget output to embed-safe markup.
	 *
	 * @param string $markup Trusted embed or shortcode output.
	 * @return string
	 */
	function mrn_base_stack_kses_external_widget_markup( $markup ) {
		return wp_kses(
			(string) $markup,
			array(
				'a'      => array(
					'aria-label' => true,
					'class'      => true,
					'href'       => true,
					'rel'        => true,
					'target'     => true,
					'title'      => true,
				),
				'div'    => array(
					'aria-label' => true,
					'class'      => true,
					'id'         => true,
					'role'       => true,
				),
				'embed'  => array(
					'aria-label' => true,
					'class'      => true,
					'height'     => true,
					'src'        => true,
					'title'      => true,
					'type'       => true,
					'width'      => true,
				),
				'iframe' => array(
					'allow'           => true,
					'allowfullscreen' => true,
					'aria-label'      => true,
					'class'           => true,
					'frameborder'     => true,
					'height'          => true,
					'loading'         => true,
					'name'            => true,
					'referrerpolicy'  => true,
					'sandbox'         => true,
					'src'             => true,
					'title'           => true,
					'width'           => true,
				),
				'object' => array(
					'aria-label' => true,
					'class'      => true,
					'data'       => true,
					'height'     => true,
					'name'       => true,
					'title'      => true,
					'type'       => true,
					'width'      => true,
				),
				'p'      => array(
					'class' => true,
				),
				'param'  => array(
					'name'  => true,
					'value' => true,
				),
				'span'   => array(
					'aria-label' => true,
					'class'      => true,
				),
			)
		);
	}
}

$context          = is_array( $args ?? null ) ? $args : array();
$row              = isset( $context['row'] ) && is_array( $context['row'] ) ? $context['row'] : array();
$label            = isset( $row['label'] ) ? trim( (string) $row['label'] ) : '';
$label_tag        = function_exists( 'mrn_base_stack_normalize_text_tag' ) ? mrn_base_stack_normalize_text_tag( $row['label_tag'] ?? '', 'p' ) : 'p';
$heading          = isset( $row['heading'] ) ? trim( (string) $row['heading'] ) : '';
$heading_tag      = function_exists( 'mrn_base_stack_normalize_text_tag' ) ? mrn_base_stack_normalize_text_tag( $row['heading_tag'] ?? '', 'h2' ) : 'h2';
$subheading       = isset( $row['subheading'] ) ? trim( (string) $row['subheading'] ) : '';
$subheading_tag   = function_exists( 'mrn_base_stack_normalize_text_tag' ) ? mrn_base_stack_normalize_text_tag( $row['subheading_tag'] ?? '', 'p' ) : 'p';
$intro            = isset( $row['intro'] ) ? (string) $row['intro'] : '';
$embed_code       = isset( $row['embed_code'] ) ? trim( (string) $row['embed_code'] ) : ( isset( $row['code'] ) ? trim( (string) $row['code'] ) : '' );
$embed_title      = isset( $row['embed_title'] ) ? trim( (string) $row['embed_title'] ) : '';
$embed_layout     = isset( $row['embed_layout'] ) ? sanitize_key( (string) $row['embed_layout'] ) : 'natural';
$aspect_ratio     = isset( $row['embed_aspect_ratio'] ) ? sanitize_key( (string) $row['embed_aspect_ratio'] ) : '16-9';
$embed_min_height = isset( $row['embed_min_height'] ) ? absint( $row['embed_min_height'] ) : 0;
$iframe_border    = isset( $row['iframe_border'] ) ? sanitize_key( (string) $row['iframe_border'] ) : 'none';
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

$has_intro_content = '' !== $label || '' !== $heading || '' !== $subheading || '' !== trim( wp_strip_all_tags( $intro ) );

if ( '' === $embed_code && ! $has_intro_content ) {
	return;
}

if ( ! in_array( $embed_layout, array( 'natural', 'ratio' ), true ) ) {
	$embed_layout = 'natural';
}

if ( ! in_array( $aspect_ratio, array( '16-9', '4-3', '1-1', '21-9' ), true ) ) {
	$aspect_ratio = '16-9';
}

if ( ! in_array( $iframe_border, array( 'none', 'theme' ), true ) ) {
	$iframe_border = 'none';
}

$aspect_ratio_css = array(
	'16-9' => '16 / 9',
	'4-3'  => '4 / 3',
	'1-1'  => '1 / 1',
	'21-9' => '21 / 9',
);

$section_classes = array(
	'mrn-content-builder__row',
	'mrn-content-builder__row--external-widget',
	'mrn-content-builder__row--external-widget-layout-' . sanitize_html_class( $embed_layout ),
	'mrn-content-builder__row--external-widget-iframe-border-' . sanitize_html_class( $iframe_border ),
);
$section_styles  = array();

if ( $has_intro_content ) {
	$section_classes[] = 'has-external-widget-intro';
}

if ( '' !== $background_color && function_exists( 'mrn_site_colors_get_css_var' ) ) {
	$section_styles[] = '--mrn-external-widget-row-bg: var(' . mrn_site_colors_get_css_var( $background_color ) . ')';
}

if ( 'ratio' === $embed_layout ) {
	$section_styles[] = '--mrn-external-widget-ratio: ' . $aspect_ratio_css[ $aspect_ratio ];
}

if ( $embed_min_height > 0 ) {
	$section_styles[] = '--mrn-external-widget-min-height: ' . $embed_min_height . 'px';
}

$display_contract  = function_exists( 'mrn_base_stack_get_builder_display_contract' ) ? mrn_base_stack_get_builder_display_contract( $row, 'external_widget' ) : array(
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
$iframe_title      = '' !== $embed_title ? $embed_title : ( '' !== $heading ? $heading : $label );
$embed_markup      = '' !== $embed_code ? mrn_base_stack_kses_external_widget_markup( do_shortcode( $embed_code ) ) : '';
$embed_markup      = mrn_base_stack_prepare_external_widget_markup( $embed_markup, $iframe_title, $iframe_border );

if ( '' === trim( $embed_markup ) && ! $has_intro_content ) {
	return;
}

echo function_exists( 'mrn_base_stack_get_builder_anchor_markup' ) ? mrn_base_stack_get_builder_anchor_markup( $row ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Anchor markup is escaped in the helper.
?>
<section class="<?php echo esc_attr( implode( ' ', $section_classes ) ); ?>"<?php echo '' !== $section_attr_html ? ' ' . $section_attr_html : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="mrn-layout-section mrn-layout-section--external-widget <?php echo esc_attr( $width_layers['section_class'] ); ?><?php echo $is_full_width ? ' mrn-layout-surface' : ''; ?>"<?php echo $is_full_width && '' !== $surface_style ? ' style="' . esc_attr( $surface_style ) . '"' : ''; ?>>
		<div class="mrn-layout-container <?php echo esc_attr( $width_layers['container_class'] ); ?><?php echo ! $is_full_width ? ' mrn-layout-surface' : ''; ?>"<?php echo ! $is_full_width && '' !== $surface_style ? ' style="' . esc_attr( $surface_style ) . '"' : ''; ?>>
			<div class="mrn-layout-grid mrn-layout-grid--external-widget mrn-layout-grid--embed-shell">
				<?php if ( $has_intro_content ) : ?>
					<div class="mrn-layout-content mrn-layout-content--text mrn-layout-content--embed-shell-text mrn-external-widget-row__intro mrn-ui__body">
						<?php if ( '' !== $label || '' !== $heading || '' !== $subheading ) : ?>
							<div class="mrn-ui__head">
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

						<?php if ( '' !== trim( $intro ) ) : ?>
							<div class="mrn-external-widget-row__text mrn-ui__text">
								<?php echo apply_filters( 'the_content', $intro ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</div>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<?php if ( '' !== trim( $embed_markup ) ) : ?>
					<div class="mrn-layout-content mrn-layout-content--embed mrn-layout-content--embed-shell-embed mrn-external-widget-row__content mrn-external-widget-row__content--embed-shell mrn-ui__body">
						<?php echo $embed_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>
</section>
