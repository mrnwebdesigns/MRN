<?php
/**
 * Render Showcase section actions at the selected visual and DOM position.
 *
 * @package mrn-base-stack
 */

$section_links = isset( $args['section_links'] ) && is_array( $args['section_links'] ) ? $args['section_links'] : array();
if ( ! $section_links ) {
	return;
}
?>
<div class="mrn-layout-content mrn-layout-content--text mrn-showcase-row__link-wrap">
	<?php foreach ( $section_links as $section_link ) : ?>
		<?php
		if ( ! is_array( $section_link ) ) {
			continue;
		}
		$section_link_url           = isset( $section_link['url'] ) ? (string) $section_link['url'] : '';
		$section_link_text          = isset( $section_link['text'] ) ? (string) $section_link['text'] : '';
		$section_link_style         = isset( $section_link['link_style'] ) && in_array( $section_link['link_style'], array( 'link', 'button' ), true ) ? (string) $section_link['link_style'] : 'link';
		$section_link_tag           = function_exists( 'mrn_rbl_get_content_link_tag_name' ) ? mrn_rbl_get_content_link_tag_name( $section_link ) : 'a';
		$section_link_attr_html     = function_exists( 'mrn_rbl_get_content_link_html_attributes' ) ? mrn_rbl_get_content_link_html_attributes( $section_link ) : '';
		$section_link_class_names   = 'mrn-ui__link ' . ( 'button' === $section_link_style ? 'mrn-ui__link--button' : 'mrn-ui__link--text' );
		$section_link_icon_markup   = function_exists( 'mrn_base_stack_get_button_link_icon_markup' )
			? mrn_base_stack_get_button_link_icon_markup( $section_link )
			: '';
		$section_link_icon_position = function_exists( 'mrn_base_stack_get_button_link_icon_position' )
			? mrn_base_stack_get_button_link_icon_position( $section_link )
			: 'left';
		if ( function_exists( 'mrn_rbl_get_content_link_custom_class_names' ) ) {
			$section_link_custom_classes = mrn_rbl_get_content_link_custom_class_names( $section_link );
			if ( '' !== $section_link_custom_classes ) {
				$section_link_class_names .= ' ' . $section_link_custom_classes;
			}
		}
		?>
		<<?php echo esc_html( $section_link_tag ); ?>
			class="<?php echo esc_attr( trim( $section_link_class_names ) ); ?>"
			<?php echo '' !== $section_link_attr_html ? $section_link_attr_html : 'href="' . esc_url( $section_link_url ) . '"'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shared escaped link attributes. ?>
		>
			<?php
			$section_link_label = '' !== $section_link_text ? $section_link_text : $section_link_url;
			echo wp_kses_post(
				function_exists( 'mrn_base_stack_get_compact_link_label_markup' )
					? mrn_base_stack_get_compact_link_label_markup( $section_link_label, $section_link_icon_markup, $section_link_icon_position )
					: esc_html( $section_link_label )
			);
			?>
		</<?php echo esc_html( $section_link_tag ); ?>>
	<?php endforeach; ?>
</div>
