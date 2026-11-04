<?php
/** Optional Cards content frame; outer section spacing remains in row presets. */
require_once __DIR__ . '/frame-spacing.php';
function mrn_base_stack_get_card_frame_fields( $seed ) {
	$fields = array( array( 'key' => $seed . '_card_frame', 'name' => 'card_frame', 'label' => 'Cards Frame', 'type' => 'select', 'choices' => array( '' => 'Use Existing Frame', 'contained' => 'Contained Content' ), 'default_value' => '', 'instructions' => 'Keeps section spacing on the outer row and applies inner gutters once. Blank preserves existing output.' ) );
	foreach ( array( 'content_width' => 'Content Maximum Width', 'inner_gutter' => 'Inner Horizontal Gutter', 'inner_gutter_mobile' => 'Small-Screen Inner Gutter (1199px)', 'header_space_after' => 'Space After Section Heading', 'intro_width' => 'Introduction Maximum Width', 'cards_space_before' => 'Space Before Cards', 'label_size' => 'Section Label Size', 'label_line_height' => 'Section Label Line Height', 'label_space_after' => 'Space After Section Label', 'label_space_after_mobile' => 'Space After Label (560px)', 'grid_column_gap' => 'Grid Column Gap', 'grid_column_gap_tablet' => 'Grid Column Gap (Tablet)', 'grid_column_gap_medium' => 'Grid Column Gap (Two-Column View)', 'grid_row_gap' => 'Grid Row Gap', 'grid_row_gap_medium' => 'Grid Row Gap (Two-Column View)', 'grid_row_gap_mobile' => 'Grid Row Gap (One-Column View)', 'grid_row_gap_tablet' => 'Grid Row Gap (Tablet)', 'heading_size' => 'Section Heading Size', 'heading_line_height' => 'Section Heading Line Height', 'heading_size_tablet' => 'Section Heading Size (Tablet)', 'heading_line_height_tablet' => 'Section Heading Line Height (Tablet)', 'heading_size_mobile' => 'Section Heading Size (Mobile)', 'heading_line_height_mobile' => 'Section Heading Line Height (Mobile)', 'header_space_after_tablet' => 'Space After Heading (Tablet)', 'header_space_after_mobile' => 'Space After Heading (Mobile)' ) as $name => $label ) {
		$fields[] = array( 'key' => $seed . '_card_' . $name, 'name' => 'card_' . $name, 'label' => $label, 'type' => 'text', 'instructions' => 'Optional non-negative CSS length or clamp(). Maximum widths also accept none. Section spacing uses row presets.', 'wrapper' => array( 'width' => '50' ) );
	}
	$fields[] = array( 'key' => $seed . '_card_header_alignment', 'name' => 'card_header_alignment', 'label' => 'Section Heading and Introduction Alignment', 'type' => 'select', 'choices' => array( '' => 'Use Existing Alignment', 'start' => 'Start', 'center' => 'Center', 'end' => 'End' ), 'default_value' => '' );
	$fields[] = array( 'key' => $seed . '_card_grid_breakpoints', 'name' => 'card_grid_breakpoints', 'label' => 'Grid Responsive Views', 'type' => 'select', 'choices' => array( '' => 'Use Existing Views (1199px / 767px)', '900-560' => 'Two Columns at 900px; One at 560px', '991-600' => 'Two Columns at 991px; One at 600px (Tablet Gaps at 1199px)', '900-single' => 'One Column at 900px', '1199-900' => 'Two Columns at 1199px; One at 900px' ), 'default_value' => '' );
	$fields[] = array( 'key' => $seed . '_card_label_weight', 'name' => 'card_label_weight', 'label' => 'Section Label Weight', 'type' => 'select', 'choices' => array( '' => 'Use Existing Weight' ) + array_combine( range( 100, 900, 100 ), range( 100, 900, 100 ) ), 'default_value' => '' );
	$fields[] = array( 'key' => $seed . '_card_label_transform', 'name' => 'card_label_transform', 'label' => 'Section Label Case', 'type' => 'select', 'choices' => array( '' => 'Use Existing Case', 'none' => 'As Entered', 'uppercase' => 'Uppercase' ), 'default_value' => '' );
	$fields[] = array( 'key' => $seed . '_card_label_letter_spacing', 'name' => 'card_label_letter_spacing', 'label' => 'Section Label Letter Spacing', 'type' => 'text', 'instructions' => 'Optional bounded signed length: -1em to 1em, or -10px to 10px.' );
	$color = mrn_base_stack_get_background_color_field( $seed . '_card_label_color' ); $color['name'] = 'card_label_color'; $color['label'] = 'Section Label Color'; $fields[] = $color;
	$fields[] = array( 'key' => $seed . '_card_heading_weight', 'name' => 'card_heading_weight', 'label' => 'Section Heading Weight', 'type' => 'select', 'choices' => array( '' => 'Use Existing Weight' ) + array_combine( range( 100, 900, 100 ), range( 100, 900, 100 ) ), 'default_value' => '' );
	$color = mrn_base_stack_get_background_color_field( $seed . '_card_heading_color' ); $color['name'] = 'card_heading_color'; $color['label'] = 'Section Heading Color'; $fields[] = $color;
	foreach ( array( 'heading_width' => 'Section Heading Maximum Width', 'heading_width_tablet' => 'Section Heading Maximum Width at 900px', 'intro_size' => 'Introduction Font Size', 'intro_line_height' => 'Introduction Line Height', 'intro_paragraph_space_after' => 'Introduction Paragraph Spacing', 'cards_space_before_tablet' => 'Space Before Cards at 900px' ) as $name => $label ) {
		$fields[] = array( 'key' => $seed . '_card_' . $name, 'name' => 'card_' . $name, 'label' => $label, 'type' => 'text', 'default_value' => '', 'instructions' => 'Optional non-negative length or clamp; maximum widths also accept none. Introduction typography accepts Site Text size/line tokens. Blank retains existing output.' );
	}
	$fields[] = array( 'key' => $seed . '_card_intro_alignment', 'name' => 'card_intro_alignment', 'label' => 'Introduction Alignment', 'type' => 'select', 'choices' => array( '' => 'Use Existing Alignment', 'start' => 'Start', 'center' => 'Center', 'end' => 'End' ), 'default_value' => '', 'instructions' => 'Explicit alignment fills the available introduction width independently of the heading; its maximum width still applies.' );
	$fields[] = array( 'key' => $seed . '_card_intro_lead_style', 'name' => 'card_intro_lead_style', 'label' => 'First Introduction Paragraph', 'type' => 'select', 'choices' => array( '' => 'Use Existing Typography', 'label' => 'Use Section Label Typography' ), 'default_value' => '', 'instructions' => 'Uses existing section label size, line height, weight, color, case, tracking and spacing controls without moving or changing the authored introduction HTML.' );
	$fields[] = array( 'key' => $seed . '_card_equal_height_items', 'name' => 'card_equal_height_items', 'label' => 'Fill Equal-Height Card Items', 'type' => 'true_false', 'ui' => 1, 'default_value' => 0, 'instructions' => 'Stretch items and their native content to the tallest item in each grid row. Combine with a filled CTA to align its actions at the bottom.' );
	$fields = array_merge( $fields, mrn_base_stack_get_frame_spacing_fields( $seed, 'card' ) );
	foreach ( $fields as &$field ) {
		if ( in_array( $field['name'], array( 'card_grid_column_gap_tablet', 'card_grid_row_gap_tablet' ), true ) ) { $field['instructions'] = 'Tablet override at 1024px, or 1199px when the 991px/600px responsive view is selected.'; }
		elseif ( in_array( $field['name'], array( 'card_grid_column_gap_medium', 'card_grid_row_gap_medium', 'card_grid_row_gap_mobile', 'card_header_space_after_tablet', 'card_header_space_after_mobile' ), true ) ) { $field['instructions'] = 'Uses the selected responsive view: 900px/560px by default, or 991px/600px. Outer section spacing stays in row presets.'; }
		elseif ( str_starts_with( $field['name'], 'card_heading_' ) ) { $field['instructions'] = 'Optional section heading control. Sizes/line heights accept Site Text tokens; tablet/mobile follow the selected 900px/560px or 991px/600px view.'; }
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
	foreach ( array( 'content_width', 'inner_gutter', 'inner_gutter_mobile', 'header_space_after', 'intro_width', 'cards_space_before', 'label_size', 'label_line_height', 'label_space_after', 'label_space_after_mobile', 'grid_column_gap', 'grid_column_gap_tablet', 'grid_column_gap_medium', 'grid_row_gap', 'grid_row_gap_medium', 'grid_row_gap_mobile', 'grid_row_gap_tablet', 'heading_size', 'heading_line_height', 'heading_size_tablet', 'heading_line_height_tablet', 'heading_size_mobile', 'heading_line_height_mobile', 'header_space_after_tablet', 'header_space_after_mobile' ) as $name ) {
		$value = ( in_array( $name, array( 'label_size', 'label_line_height' ), true ) || str_starts_with( $name, 'heading_' ) ) ? mrn_base_stack_get_content_frame_dimension( $row[ 'card_' . $name ] ?? '', $name ) : mrn_base_stack_validate_card_frame_dimension( $row[ 'card_' . $name ] ?? '', in_array( $name, array( 'content_width', 'intro_width' ), true ) );
		if ( '' !== $value ) { $styles[] = '--mrn-card-frame-' . str_replace( '_', '-', $name ) . ':' . $value; $result['classes'][] = 'has-card-' . str_replace( '_', '-', $name ); }
	}
	if ( in_array( $row['card_grid_breakpoints'] ?? '', array( '900-560', '991-600', '900-single', '1199-900' ), true ) ) { $result['classes'][] = 'has-card-grid-' . $row['card_grid_breakpoints']; }
	$weight = $row['card_label_weight'] ?? ''; if ( is_scalar( $weight ) && in_array( (string) $weight, array_map( 'strval', range( 100, 900, 100 ) ), true ) ) { $styles[] = '--mrn-card-frame-label-weight:' . $weight; $result['classes'][] = 'has-card-label-weight'; }
	$transform = $row['card_label_transform'] ?? ''; if ( in_array( $transform, array( 'none', 'uppercase' ), true ) ) { $styles[] = '--mrn-card-frame-label-transform:' . $transform; $result['classes'][] = 'has-card-label-transform'; }
	$tracking = mrn_base_stack_get_frame_letter_spacing( $row['card_label_letter_spacing'] ?? '' ); if ( '' !== $tracking ) { $styles[] = '--mrn-card-frame-label-letter-spacing:' . $tracking; $result['classes'][] = 'has-card-label-letter-spacing'; }
	$color = mrn_base_stack_get_frame_palette_color( $row['card_label_color'] ?? '' ); if ( '' !== $color ) { $styles[] = '--mrn-card-frame-label-color:' . $color; $result['classes'][] = 'has-card-label-color'; }
	if ( in_array( $row['card_header_alignment'] ?? '', array( 'start', 'center', 'end' ), true ) ) { $styles[] = '--mrn-card-frame-header-alignment:' . $row['card_header_alignment']; }
	$weight = $row['card_heading_weight'] ?? ''; if ( is_scalar( $weight ) && in_array( (string) $weight, array_map( 'strval', range( 100, 900, 100 ) ), true ) ) { $styles[] = '--mrn-card-frame-heading-weight:' . $weight; $result['classes'][] = 'has-card-heading-weight'; }
	$color = mrn_base_stack_get_frame_palette_color( $row['card_heading_color'] ?? '' ); if ( '' !== $color ) { $styles[] = '--mrn-card-frame-heading-color:' . $color; $result['classes'][] = 'has-card-heading-color'; }
	$background = mrn_base_stack_get_frame_palette_color( $row['background_color'] ?? '' );
	if ( '' !== $background ) { $styles[] = '--mrn-card-row-bg:' . $background; }
	foreach ( array( 'heading_width', 'heading_width_tablet', 'intro_size', 'intro_line_height', 'intro_paragraph_space_after', 'cards_space_before_tablet' ) as $name ) {
		$value = in_array( $name, array( 'intro_size', 'intro_line_height' ), true ) ? mrn_base_stack_get_content_frame_dimension( $row[ 'card_' . $name ] ?? '', $name ) : mrn_base_stack_validate_card_frame_dimension( $row[ 'card_' . $name ] ?? '', str_starts_with( $name, 'heading_width' ) );
		if ( '' !== $value ) { $styles[] = '--mrn-card-frame-' . str_replace( '_', '-', $name ) . ':' . $value; $result['classes'][] = 'has-card-' . str_replace( '_', '-', $name ); }
	}
	if ( in_array( $row['card_intro_alignment'] ?? '', array( 'start', 'center', 'end' ), true ) ) { $result['classes'][] = 'has-card-intro-alignment'; $styles[] = '--mrn-card-frame-intro-alignment:' . $row['card_intro_alignment']; }
	if ( 'label' === ( $row['card_intro_lead_style'] ?? '' ) ) { $result['classes'][] = 'has-card-intro-lead-label'; }
	if ( in_array( $row['card_equal_height_items'] ?? 0, array( true, 1, '1' ), true ) ) { $result['classes'][] = 'has-card-equal-height-items'; }
	$columns = mrn_base_stack_normalize_card_per_row( $row['cards_per_row'] ?? 3 );
	$styles[] = '--mrn-card-frame-columns:' . $columns;
	$styles[] = '--mrn-card-frame-tablet-columns:' . min( 2, $columns );
	$spacing = mrn_base_stack_get_frame_spacing_contract( $row, 'card' );
	$result['classes'] = array_merge( $result['classes'], $spacing['classes'] );
	$styles = array_merge( $styles, $spacing['styles'] );
	$result['attributes']['style'] = implode( ';', $styles );
	return $result;
}
