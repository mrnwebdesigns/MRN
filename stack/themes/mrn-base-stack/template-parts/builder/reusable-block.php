<?php
/**
 * Builder row: Reusable Block.
 *
 * @package mrn-base-stack
 */

$context = is_array( $args ?? null ) ? $args : array();
$row     = isset( $context['row'] ) && is_array( $context['row'] ) ? $context['row'] : array();
$block   = $row['block'] ?? null;

if ( ! function_exists( 'mrn_rbl_render_block' ) || ! ( $block instanceof WP_Post ) ) {
	return;
}

$markup = mrn_rbl_render_block( $block );
if ( '' === trim( $markup ) ) {
	return;
}

if ( array_key_exists( 'section_width', $row ) && function_exists( 'mrn_base_stack_wrap_reusable_builder_markup' ) ) {
	echo mrn_base_stack_wrap_reusable_builder_markup( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		$markup,
		$row,
		$block->post_type,
		'wide'
	);
	return;
}
?>
<section class="mrn-content-builder__row mrn-content-builder__row--reusable-block">
	<?php echo $markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
</section>
