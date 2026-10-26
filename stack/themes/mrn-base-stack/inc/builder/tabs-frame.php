<?php
/** Native Tabs framing and optional progressive mobile accordion. */
function mrn_base_stack_get_tabs_frame_fields( $seed ) {
	$fields = array(
		array( 'key' => $seed . '_tab_frame', 'name' => 'tab_frame', 'label' => 'Tabs Frame', 'type' => 'select', 'choices' => array( '' => 'Use Existing Frame', 'contained' => 'Contained Content' ), 'default_value' => '' ),
		array( 'key' => $seed . '_tab_mobile_behavior', 'name' => 'tab_mobile_behavior', 'label' => 'Small-Screen Tabs', 'type' => 'select', 'choices' => array( '' => 'Keep Tabs', 'accordion' => 'Accordion' ), 'default_value' => '', 'instructions' => 'Accordion uses native details without JavaScript and instant desktop switching. Each panel has one copy of its content and a meaningfully named control.' ),
		array( 'key' => $seed . '_tab_mobile_breakpoint', 'name' => 'tab_mobile_breakpoint', 'label' => 'Accordion Maximum Width', 'type' => 'select', 'choices' => array( '767' => '767px', '991' => '991px' ), 'default_value' => '767' ),
	);
	foreach ( array( 'content_width', 'inner_gutter', 'inner_gutter_mobile', 'panel_space_before', 'logo_height', 'nav_padding_top', 'nav_padding_inline', 'nav_padding_bottom', 'drawer_padding', 'panel_space_after_mobile' ) as $name ) {
		$fields[] = array( 'key' => $seed . '_tab_' . $name, 'name' => 'tab_' . $name, 'label' => ucwords( str_replace( '_', ' ', $name ) ), 'type' => 'text', 'instructions' => 'Optional non-negative CSS length or clamp(). Maximum content width accepts none. Small-screen inner gutter applies at 1199px; outer section spacing uses row presets.', 'wrapper' => array( 'width' => '50' ), 'conditional_logic' => array( array( array( 'field' => $seed . '_tab_frame', 'operator' => '==', 'value' => 'contained' ) ) ) );
	}
	$fields[] = array( 'key' => $seed . '_tab_inactive_icon_filter', 'name' => 'tab_inactive_icon_filter', 'label' => 'Inactive Logo Treatment', 'type' => 'select', 'choices' => array( '' => 'Keep Original Colors', 'muted' => 'Palette Tint' ), 'default_value' => '' );
	foreach ( array( 'inactive_icon_color', 'active_color' ) as $part ) { $field = mrn_base_stack_get_background_color_field( $seed . '_tab_' . $part ); $field['name'] = 'tab_' . $part; $field['label'] = ucwords( str_replace( '_', ' ', $part ) ); $fields[] = $field; }
	$fields[2]['conditional_logic'] = array( array( array( 'field' => $seed . '_tab_mobile_behavior', 'operator' => '==', 'value' => 'accordion' ) ) );
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
