<?php
/** Native Tabs framing and optional progressive mobile accordion. */
function mrn_base_stack_get_tabs_frame_fields( $seed ) {
	$fields = array(
		array( 'key' => $seed . '_tab_frame', 'name' => 'tab_frame', 'label' => 'Tabs Frame', 'type' => 'select', 'choices' => array( '' => 'Use Existing Frame', 'contained' => 'Contained Content' ), 'default_value' => '' ),
		array( 'key' => $seed . '_tab_mobile_behavior', 'name' => 'tab_mobile_behavior', 'label' => 'Small-Screen Tabs', 'type' => 'select', 'choices' => array( '' => 'Keep Tabs', 'accordion' => 'Accordion' ), 'default_value' => '', 'instructions' => 'Accordion uses native details without JavaScript and instant desktop switching. Each panel has one copy of its content and a meaningfully named control.' ),
	);
	$fields[] = array( 'key' => $seed . '_tab_inactive_icon_filter', 'name' => 'tab_inactive_icon_filter', 'label' => 'Inactive Logo Treatment', 'type' => 'select', 'choices' => array( '' => 'Keep Original Colors', 'muted' => 'Palette Tint' ), 'default_value' => '' );
	return $fields;
}

function mrn_base_stack_get_tabs_frame_contract( array $row ) {
	$result = array( 'classes' => array(), 'attributes' => array() );
	if ( 'contained' !== ( $row['tab_frame'] ?? '' ) ) { return $result; }
	$result['classes'][] = 'has-tab-frame'; $styles = array();
	foreach ( array( 'content_width', 'inner_gutter', 'inner_gutter_mobile', 'panel_space_before', 'logo_height', 'nav_padding_top', 'nav_padding_inline', 'nav_padding_bottom', 'drawer_padding', 'panel_space_after_mobile' ) as $name ) {
		$value = mrn_base_stack_validate_card_frame_dimension( $row[ 'tab_' . $name ] ?? '', 'content_width' === $name );
		if ( '' !== $value ) { $styles[] = '--mrn-tab-frame-' . str_replace( '_', '-', $name ) . ':' . $value; }
	}
	if ( 'muted' === ( $row['tab_inactive_icon_filter'] ?? '' ) ) { $result['classes'][] = 'has-muted-tab-icons'; }
	foreach ( array( 'inactive_icon_color', 'active_color' ) as $part ) { $value = mrn_base_stack_get_frame_palette_color( $row[ 'tab_' . $part ] ?? '' ); if ( '' !== $value ) { $styles[] = '--mrn-tab-' . str_replace( '_', '-', $part ) . ':' . $value; } }
	$background = mrn_base_stack_get_frame_palette_color( $row['background_color'] ?? '' );
	if ( '' !== $background ) { $styles[] = '--mrn-tabbed-layout-row-bg:' . $background; }
	if ( $styles ) { $result['attributes']['style'] = implode( ';', $styles ); }
	return $result;
}
