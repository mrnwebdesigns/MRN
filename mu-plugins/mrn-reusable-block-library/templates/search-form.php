<?php
/**
 * Search Form reusable block template.
 *
 * Theme override path:
 * wp-content/themes/{active-theme}/mrn-blocks/search-form.php
 *
 * @var array<string, mixed> $context
 */

if ( ! isset( $context ) || ! is_array( $context ) ) {
	return;
}

$post             = isset( $context['post'] ) && $context['post'] instanceof WP_Post ? $context['post'] : null;
$post_id          = isset( $context['post_id'] ) ? (int) $context['post_id'] : 0;
$post_name        = isset( $context['post_name'] ) ? (string) $context['post_name'] : ( $post instanceof WP_Post ? (string) $post->post_name : '' );
$fields           = isset( $context['fields'] ) && is_array( $context['fields'] ) ? $context['fields'] : array();
$label            = isset( $fields['label'] ) ? trim( (string) $fields['label'] ) : '';
$label_tag        = function_exists( 'mrn_rbl_normalize_text_tag' ) ? mrn_rbl_normalize_text_tag( $fields['label_tag'] ?? '', 'p' ) : 'p';
$heading          = isset( $fields['heading'] ) ? trim( (string) $fields['heading'] ) : '';
$heading_tag      = function_exists( 'mrn_rbl_normalize_text_tag' ) ? mrn_rbl_normalize_text_tag( $fields['heading_tag'] ?? '', 'h2' ) : 'h2';
$subheading       = isset( $fields['subheading'] ) ? trim( (string) $fields['subheading'] ) : '';
$subheading_tag   = function_exists( 'mrn_rbl_normalize_text_tag' ) ? mrn_rbl_normalize_text_tag( $fields['subheading_tag'] ?? '', 'p' ) : 'p';
$intro            = isset( $fields['intro'] ) ? (string) $fields['intro'] : '';
$background_color = isset( $fields['background_color'] ) ? trim( (string) $fields['background_color'] ) : '';
$bottom_accent    = ! empty( $fields['bottom_accent'] );
$accent_slug      = isset( $fields['bottom_accent_style'] ) ? (string) $fields['bottom_accent_style'] : '';
$form_id          = absint( $fields['searchwp_form_id'] ?? 0 );
$form_markup      = function_exists( 'mrn_base_stack_get_searchwp_form_markup' ) ? mrn_base_stack_get_searchwp_form_markup( $form_id ) : '';

if ( '' === trim( $form_markup ) ) {
	return;
}

$classes = array(
	'mrn-reusable-block',
	'mrn-reusable-block--search-form',
);

$accent_contract = function_exists( 'mrn_site_styles_get_bottom_accent_contract' )
	? mrn_site_styles_get_bottom_accent_contract( $bottom_accent, $accent_slug )
	: array(
		'classes'    => $bottom_accent ? array( 'has-bottom-accent' ) : array(),
		'attributes' => array(),
	);

if ( isset( $accent_contract['classes'] ) && is_array( $accent_contract['classes'] ) ) {
	$classes = array_merge( $classes, $accent_contract['classes'] );
}

$motion_contract = function_exists( 'mrn_rbl_get_motion_contract' ) ? mrn_rbl_get_motion_contract( $fields, $context ) : array(
	'classes'    => array(),
	'attributes' => array(),
);

if ( isset( $motion_contract['classes'] ) && is_array( $motion_contract['classes'] ) ) {
	$classes = array_merge( $classes, $motion_contract['classes'] );
}

$inline_styles = array();
if ( '' !== $background_color && function_exists( 'mrn_site_colors_get_css_var' ) ) {
	$inline_styles[] = '--mrn-searchwp-form-row-bg: var(' . mrn_site_colors_get_css_var( $background_color ) . ')';
}

$section_attrs     = isset( $accent_contract['attributes'] ) && is_array( $accent_contract['attributes'] ) ? $accent_contract['attributes'] : array();
$section_attrs     = function_exists( 'mrn_rbl_merge_attributes' ) ? mrn_rbl_merge_attributes( $section_attrs, isset( $motion_contract['attributes'] ) && is_array( $motion_contract['attributes'] ) ? $motion_contract['attributes'] : array() ) : array_merge( $section_attrs, isset( $motion_contract['attributes'] ) && is_array( $motion_contract['attributes'] ) ? $motion_contract['attributes'] : array() );
$section_attr_html = function_exists( 'mrn_rbl_get_html_attributes' ) ? mrn_rbl_get_html_attributes( $section_attrs ) : '';

echo function_exists( 'mrn_rbl_get_anchor_markup' ) ? mrn_rbl_get_anchor_markup( $context ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Anchor markup is escaped in the helper.
?>
<section
	class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
	data-block-id="<?php echo esc_attr( (string) $post_id ); ?>"
	data-block-slug="<?php echo esc_attr( $post_name ); ?>"
	<?php echo '' !== $section_attr_html ? ' ' . $section_attr_html : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<?php if ( $inline_styles !== array() ) : ?>
		style="<?php echo esc_attr( implode( '; ', $inline_styles ) ); ?>"
	<?php endif; ?>
>
	<div class="mrn-reusable-block__inner mrn-reusable-block__inner--search-form mrn-ui__body">
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
			<div class="mrn-ui__text">
				<?php echo apply_filters( 'the_content', $intro ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		<?php endif; ?>

		<div class="mrn-searchwp-form-block__form">
			<?php echo $form_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
	</div>
</section>
