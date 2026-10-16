<?php
/**
 * Cards timeline, preserving the native nested Card Row content.
 *
 * @package mrn-base-stack
 */

$context = is_array( $args ?? null ) ? $args : array();
$row     = $context['row'] ?? array();
$items   = is_array( $row['card_items'] ?? null ) ? $row['card_items'] : array();
if ( ! $items ) {
	return;
}
mrn_base_stack_enqueue_presentation();
$end_id      = wp_unique_id( 'mrn-timeline-end-' );
$heading_tag = mrn_base_stack_normalize_text_tag( $row['heading_tag'] ?? 'h2', 'h2' );
$skip        = trim( (string) ( $row['timeline_skip_label'] ?? '' ) );
$skip        = '' !== $skip ? $skip : __( 'Skip timeline', 'mrn-base-stack' );
ob_start();
?>
<div class="mrn-timeline" data-mrn-timeline>
	<?php if ( ! empty( $row['heading'] ) ) : ?>
		<<?php echo esc_html( $heading_tag ); ?> class="mrn-ui__heading"><?php echo mrn_base_stack_format_heading_inline_html( $row['heading'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shared escaped formatter. ?></<?php echo esc_html( $heading_tag ); ?>>
	<?php endif; ?>
	<?php
	if ( ! empty( $row['subheading'] ) ) :
		?>
		<p><?php echo wp_kses_post( $row['subheading'] ); ?></p><?php endif; ?>
	<a class="mrn-timeline__skip" href="#<?php echo esc_attr( $end_id ); ?>"><?php echo esc_html( $skip ); ?></a>
	<div class="mrn-timeline__range"><div class="mrn-timeline__stage">
		<?php if ( ! empty( $row['timeline_image'] ) ) : ?>
			<div class="mrn-timeline__image"><?php echo mrn_base_stack_get_attachment_image( $row['timeline_image'], 'mrn-content-media', array( 'loading' => 'lazy' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped image helper. ?></div>
		<?php endif; ?>
		<ol class="mrn-timeline__steps">
		<?php foreach ( $items as $step_index => $item ) : ?>
			<li><details open>
				<summary>
				<?php
				/* translators: %d: The step number in the timeline. */
				echo esc_html( ! empty( $item['card_label'] ) ? $item['card_label'] : sprintf( __( 'Step %d', 'mrn-base-stack' ), $step_index + 1 ) );
				?>
				</summary>
				<div class="mrn-timeline__body">
				<?php
				foreach ( (array) ( $item['card_rows'] ?? array() ) as $child ) {
					if ( is_array( $child ) ) {
						mrn_base_stack_render_builder_row( $child, (int) $context['post_id'], $step_index );
					}
				}
				?>
				</div>
			</details></li>
		<?php endforeach; ?>
		</ol>
	</div></div>
	<div class="mrn-timeline__end" id="<?php echo esc_attr( $end_id ); ?>" tabindex="-1"></div>
</div>
<?php
$markup = ob_get_clean();
echo mrn_base_stack_wrap_cloned_reusable_builder_markup( $markup, $row, 'mrn-content-builder__row--card', 'mrn-layout-section--card', 'wide' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shared native width/spacing/anchor wrapper.
