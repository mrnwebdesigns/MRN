<?php
/** Optional Cards content frame; outer section spacing remains in row presets. */
function mrn_base_stack_get_card_frame_fields( $seed ) {
	$fields = array( array( 'key' => $seed . '_card_frame', 'name' => 'card_frame', 'label' => 'Cards Frame', 'type' => 'select', 'choices' => array( '' => 'Use Existing Frame', 'contained' => 'Contained Content' ), 'default_value' => '', 'instructions' => 'Keeps section spacing on the outer row and applies inner gutters once. Blank preserves existing output.' ) );
	foreach ( array( 'content_width' => 'Content Maximum Width', 'inner_gutter' => 'Inner Horizontal Gutter', 'inner_gutter_mobile' => 'Small-Screen Inner Gutter (1199px)', 'header_space_after' => 'Space After Section Heading', 'intro_width' => 'Introduction Maximum Width', 'cards_space_before' => 'Space Before Cards', 'label_size' => 'Section Label Size', 'label_line_height' => 'Section Label Line Height', 'label_space_after' => 'Space After Section Label', 'label_space_after_mobile' => 'Space After Label (560px)', 'grid_column_gap' => 'Grid Column Gap', 'grid_column_gap_tablet' => 'Grid Column Gap (1024px)', 'grid_column_gap_medium' => 'Grid Column Gap (900px)', 'grid_row_gap' => 'Grid Row Gap', 'grid_row_gap_medium' => 'Grid Row Gap (900px)', 'grid_row_gap_mobile' => 'Grid Row Gap (560px)' ) as $name => $label ) {
		$fields[] = array( 'key' => $seed . '_card_' . $name, 'name' => 'card_' . $name, 'label' => $label, 'type' => 'text', 'instructions' => 'Optional non-negative CSS length or clamp(). Maximum widths also accept none. Section spacing uses row presets.', 'wrapper' => array( 'width' => '50' ) );
	}
	$fields[] = array( 'key' => $seed . '_card_header_alignment', 'name' => 'card_header_alignment', 'label' => 'Section Heading and Introduction Alignment', 'type' => 'select', 'choices' => array( '' => 'Use Existing Alignment', 'start' => 'Start', 'center' => 'Center', 'end' => 'End' ), 'default_value' => '' );
	$fields[] = array( 'key' => $seed . '_card_grid_breakpoints', 'name' => 'card_grid_breakpoints', 'label' => 'Grid Responsive Views', 'type' => 'select', 'choices' => array( '' => 'Use Existing Views (1199px / 767px)', '900-560' => 'Two Columns at 900px; One at 560px' ), 'default_value' => '' );
	$fields[] = array( 'key' => $seed . '_card_label_weight', 'name' => 'card_label_weight', 'label' => 'Section Label Weight', 'type' => 'select', 'choices' => array( '' => 'Use Existing Weight' ) + array_combine( range( 100, 900, 100 ), range( 100, 900, 100 ) ), 'default_value' => '' );
	$fields[] = array( 'key' => $seed . '_card_label_transform', 'name' => 'card_label_transform', 'label' => 'Section Label Case', 'type' => 'select', 'choices' => array( '' => 'Use Existing Case', 'none' => 'As Entered', 'uppercase' => 'Uppercase' ), 'default_value' => '' );
	$fields[] = array( 'key' => $seed . '_card_label_letter_spacing', 'name' => 'card_label_letter_spacing', 'label' => 'Section Label Letter Spacing', 'type' => 'text', 'instructions' => 'Optional bounded signed length: -1em to 1em, or -10px to 10px.' );
	$color = mrn_base_stack_get_background_color_field( $seed . '_card_label_color' ); $color['name'] = 'card_label_color'; $color['label'] = 'Section Label Color'; $fields[] = $color;
	foreach ( $fields as &$field ) {
		if ( 'card_frame' !== $field['name'] ) { $field['conditional_logic'] = array( array( array( 'field' => $seed . '_card_frame', 'operator' => '==', 'value' => 'contained' ) ) ); }
	}
	unset( $field );
	return $fields;
}

/** Bounded styles activate only on a selected frame; no arbitrary CSS values. */
function mrn_base_stack_validate_card_frame_dimension( $raw, $allow_none = false ) {
	$value = mrn_base_stack_validate_hero_frame_dimension( $raw, $allow_none );
	if ( '' !== $value || ! is_scalar( $raw ) || strlen( (string) $raw ) > 96 ) { return $value; }
	// Preserve a positive two-term fluid preference without accepting arbitrary calc().
	if ( ! preg_match( '/^clamp\(\s*([^,()]+),\s*([^,()]+)\s+\+\s+([^,()]+),\s*([^,()]+)\s*\)$/D', strtolower( trim( (string) $raw ) ), $parts ) ) { return ''; }
	$values = array_map( static function ( $part ) { return mrn_base_stack_validate_hero_frame_dimension( trim( $part ) ); }, array_slice( $parts, 1 ) );
	if ( in_array( '', $values, true ) ) { return ''; }
	return 'clamp(' . $values[0] . ', ' . $values[1] . ' + ' . $values[2] . ', ' . $values[3] . ')';
}

/** Resolve the selected Cards frame with existing authored column choices. */
function mrn_base_stack_get_card_frame_contract( array $row ) {
	$result = array( 'classes' => array(), 'attributes' => array() );
	if ( 'contained' !== ( $row['card_frame'] ?? '' ) || 'timeline' === ( $row['card_presentation'] ?? '' ) ) { return $result; }
	$result['classes'][] = 'has-card-frame'; $styles = array();
	foreach ( array( 'content_width', 'inner_gutter', 'inner_gutter_mobile', 'header_space_after', 'intro_width', 'cards_space_before', 'label_size', 'label_line_height', 'label_space_after', 'label_space_after_mobile', 'grid_column_gap', 'grid_column_gap_tablet', 'grid_column_gap_medium', 'grid_row_gap', 'grid_row_gap_medium', 'grid_row_gap_mobile' ) as $name ) {
		$value = in_array( $name, array( 'label_size', 'label_line_height' ), true ) ? mrn_base_stack_get_content_frame_dimension( $row[ 'card_' . $name ] ?? '', $name ) : mrn_base_stack_validate_card_frame_dimension( $row[ 'card_' . $name ] ?? '', in_array( $name, array( 'content_width', 'intro_width' ), true ) );
		if ( '' !== $value ) { $styles[] = '--mrn-card-frame-' . str_replace( '_', '-', $name ) . ':' . $value; $result['classes'][] = 'has-card-' . str_replace( '_', '-', $name ); }
	}
	if ( '900-560' === ( $row['card_grid_breakpoints'] ?? '' ) ) { $result['classes'][] = 'has-card-grid-900-560'; }
	$weight = $row['card_label_weight'] ?? ''; if ( is_scalar( $weight ) && in_array( (string) $weight, array_map( 'strval', range( 100, 900, 100 ) ), true ) ) { $styles[] = '--mrn-card-frame-label-weight:' . $weight; $result['classes'][] = 'has-card-label-weight'; }
	$transform = $row['card_label_transform'] ?? ''; if ( in_array( $transform, array( 'none', 'uppercase' ), true ) ) { $styles[] = '--mrn-card-frame-label-transform:' . $transform; $result['classes'][] = 'has-card-label-transform'; }
	$tracking = mrn_base_stack_get_frame_letter_spacing( $row['card_label_letter_spacing'] ?? '' ); if ( '' !== $tracking ) { $styles[] = '--mrn-card-frame-label-letter-spacing:' . $tracking; $result['classes'][] = 'has-card-label-letter-spacing'; }
	$color = mrn_base_stack_get_frame_palette_color( $row['card_label_color'] ?? '' ); if ( '' !== $color ) { $styles[] = '--mrn-card-frame-label-color:' . $color; $result['classes'][] = 'has-card-label-color'; }
	if ( in_array( $row['card_header_alignment'] ?? '', array( 'start', 'center', 'end' ), true ) ) { $styles[] = '--mrn-card-frame-header-alignment:' . $row['card_header_alignment']; }
	$background = mrn_base_stack_get_frame_palette_color( $row['background_color'] ?? '' );
	if ( '' !== $background ) { $styles[] = '--mrn-card-row-bg:' . $background; }
	$columns = mrn_base_stack_normalize_card_per_row( $row['cards_per_row'] ?? 3 );
	$styles[] = '--mrn-card-frame-columns:' . $columns;
	$styles[] = '--mrn-card-frame-tablet-columns:' . min( 2, $columns );
	$result['attributes']['style'] = implode( ';', $styles );
	return $result;
}
