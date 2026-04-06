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

$extra_context = array(
	'host_post_id'   => isset( $context['post_id'] ) ? (int) $context['post_id'] : 0,
	'host_row_index' => isset( $context['index'] ) ? (int) $context['index'] : 0,
	'suppress_anchor' => true,
);
$markup        = function_exists( 'mrn_rbl_render_block_with_context' )
	? mrn_rbl_render_block_with_context( $block, $extra_context )
	: mrn_rbl_render_block( $block );
if ( '' === trim( $markup ) ) {
	return;
}

$fallback_anchor = '';
if ( function_exists( 'get_field' ) ) {
	$fallback_anchor = (string) get_field( 'anchor', $block->ID );
}

if ( array_key_exists( 'section_width', $row ) && function_exists( 'mrn_base_stack_wrap_reusable_builder_markup' ) ) {
	$wrapped_row = $row;
	if ( function_exists( 'mrn_base_stack_normalize_anchor_id' ) && '' === mrn_base_stack_normalize_anchor_id( $wrapped_row['anchor'] ?? '' ) && '' !== $fallback_anchor ) {
		$wrapped_row['anchor'] = $fallback_anchor;
	}

	echo mrn_base_stack_wrap_reusable_builder_markup( $markup, $wrapped_row, $block->post_type, 'wide' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	return;
}
?>
<?php echo function_exists( 'mrn_base_stack_get_builder_anchor_markup' ) ? mrn_base_stack_get_builder_anchor_markup( $row, $fallback_anchor ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Anchor markup is escaped in the helper. ?>
<section class="mrn-content-builder__row mrn-content-builder__row--reusable-block">
	<?php echo $markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
</section>
