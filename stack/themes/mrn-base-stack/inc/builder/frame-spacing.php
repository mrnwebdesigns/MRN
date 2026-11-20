<?php
/** Optional contained-frame breakpoints; native presets retain all outer dimensions. */
function mrn_base_stack_get_frame_spacing_fields( $seed, $prefix ) {
	$fields = array();
	foreach ( array( 'row_spacing_mobile_breakpoint' => array( 'Row Margin Breakpoint', array( '' => 'Use Existing (782px)', '767' => '767px' ) ), 'padding_mobile_breakpoint' => array( 'Row Padding Breakpoint', array( '' => 'Use Existing Row Breakpoint', '767' => '767px', '900' => '900px' ) ) ) as $suffix => $config ) {
		$fields[] = array( 'key' => $seed . '_' . $prefix . '_' . $suffix, 'name' => $prefix . '_' . $suffix, 'label' => $config[0], 'type' => 'select', 'choices' => $config[1], 'default_value' => '', 'instructions' => 'Optional breakpoint only. Select all margin and padding dimensions through the native row spacing presets.' );
	}
	foreach ( array( 'inner_gutter_narrow' => 'Inner Gutter at 900px', 'inner_gutter_small' => 'Inner Gutter at 480px' ) as $suffix => $label ) {
		$fields[] = array( 'key' => $seed . '_' . $prefix . '_' . $suffix, 'name' => $prefix . '_' . $suffix, 'label' => $label, 'type' => 'text', 'default_value' => '', 'instructions' => 'Optional non-negative CSS length or clamp(). Blank retains the existing larger-screen gutter. Outer padding remains in row presets.' );
	}
	return $fields;
}

/** Resolve only allow-listed views and bounded gutters; callers require an active frame. */
function mrn_base_stack_get_frame_spacing_contract( array $row, $prefix ) {
	$result = array( 'classes' => array(), 'styles' => array() );
	if ( ! in_array( $prefix, array( 'card', 'body' ), true ) ) { return $result; }
	if ( '767' === ( $row[ $prefix . '_row_spacing_mobile_breakpoint' ] ?? '' ) ) { $result['classes'][] = 'frame-row-spacing-at-767'; }
	if ( in_array( $row[ $prefix . '_padding_mobile_breakpoint' ] ?? '', array( '767', '900' ), true ) ) { $result['classes'][] = 'frame-padding-at-' . $row[ $prefix . '_padding_mobile_breakpoint']; }
	foreach ( array( 'narrow', 'small' ) as $view ) {
		$value = mrn_base_stack_validate_hero_frame_dimension( $row[ $prefix . '_inner_gutter_' . $view ] ?? '' );
		if ( '' !== $value ) { $result['classes'][] = 'frame-gutter-' . $view; $result['styles'][] = '--mrn-native-frame-gutter-' . $view . ':' . $value; }
	}
	return $result;
}
