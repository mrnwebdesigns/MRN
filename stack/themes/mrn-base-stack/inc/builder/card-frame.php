<?php
/** Optional Cards content frame; outer section spacing remains in row presets. */
require_once __DIR__ . '/frame-spacing.php';
function mrn_base_stack_get_card_frame_fields( $seed ) {
	$fields = array( array( 'key' => $seed . '_card_frame', 'name' => 'card_frame', 'label' => 'Cards Frame', 'type' => 'select', 'choices' => array( '' => 'Use Existing Frame', 'contained' => 'Contained Content' ), 'default_value' => '', 'instructions' => 'Contained Content turns on the options below. Sizes and typography come from the selected look, and section spacing from the Spacing tab. Blank preserves existing output.' ) );
	$fields[] = array( 'key' => $seed . '_card_header_alignment', 'name' => 'card_header_alignment', 'label' => 'Section Heading and Introduction Alignment', 'type' => 'select', 'choices' => array( '' => 'Use Existing Alignment', 'start' => 'Start', 'center' => 'Center', 'end' => 'End' ), 'default_value' => '' );
	$fields[] = array( 'key' => $seed . '_card_intro_alignment', 'name' => 'card_intro_alignment', 'label' => 'Introduction Alignment', 'type' => 'select', 'choices' => array( '' => 'Use Existing Alignment', 'start' => 'Start', 'center' => 'Center', 'end' => 'End' ), 'default_value' => '', 'instructions' => 'Explicit alignment fills the available introduction width independently of the heading; its maximum width still applies.' );
	$fields[] = array( 'key' => $seed . '_card_intro_lead_style', 'name' => 'card_intro_lead_style', 'label' => 'First Introduction Paragraph', 'type' => 'select', 'choices' => array( '' => 'Use Existing Typography', 'label' => 'Use Section Label Typography' ), 'default_value' => '', 'instructions' => 'Uses existing section label size, line height, weight, color, case, tracking and spacing controls without moving or changing the authored introduction HTML.' );
	$fields[] = array( 'key' => $seed . '_card_equal_height_items', 'name' => 'card_equal_height_items', 'label' => 'Fill Equal-Height Card Items', 'type' => 'true_false', 'ui' => 1, 'default_value' => 0, 'instructions' => 'Stretch items and their native content to the tallest item in each grid row. Combine with a filled CTA to align its actions at the bottom.' );
	$fields[] = array( 'key' => $seed . '_card_heading_breaks_mobile', 'name' => 'card_heading_breaks_mobile', 'label' => 'Hide Heading Line Breaks on Small Screens', 'type' => 'true_false', 'ui' => 1, 'default_value' => 0, 'instructions' => 'At 767px and below, allow the heading to wrap naturally instead of forcing authored line breaks. The words remain unchanged.' );
	$fields[] = array( 'key' => $seed . '_card_item_heading_breaks_mobile', 'name' => 'card_item_heading_breaks_mobile', 'label' => 'Hide Card Title Line Breaks on Small Screens', 'type' => 'true_false', 'ui' => 1, 'default_value' => 0, 'instructions' => 'At 767px and below, let headings inside card items wrap naturally instead of forcing authored line breaks. The words remain unchanged.' );
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
	foreach ( array( 'item_padding', 'item_radius', 'item_lift', 'content_width', 'inner_gutter', 'inner_gutter_mobile', 'header_space_after', 'intro_width', 'cards_space_before', 'label_size', 'label_line_height', 'label_space_after', 'label_space_after_mobile', 'grid_column_gap', 'grid_column_gap_tablet', 'grid_column_gap_medium', 'grid_row_gap', 'grid_row_gap_medium', 'grid_row_gap_mobile', 'grid_row_gap_tablet', 'heading_size', 'heading_line_height', 'heading_size_tablet', 'heading_line_height_tablet', 'heading_size_mobile', 'heading_line_height_mobile', 'header_space_after_tablet', 'header_space_after_mobile' ) as $name ) {
		$value = ( in_array( $name, array( 'label_size', 'label_line_height' ), true ) || str_starts_with( $name, 'heading_' ) ) ? mrn_base_stack_get_content_frame_dimension( $row[ 'card_' . $name ] ?? '', $name ) : mrn_base_stack_validate_card_frame_dimension( $row[ 'card_' . $name ] ?? '', in_array( $name, array( 'content_width', 'intro_width' ), true ) );
		if ( '' !== $value ) { $styles[] = '--mrn-card-frame-' . str_replace( '_', '-', $name ) . ':' . $value; $result['classes'][] = 'has-card-' . str_replace( '_', '-', $name ); }
	}
	$item_min = mrn_base_stack_validate_card_frame_dimension( $row['card_item_min_width_mobile'] ?? '' );
	if ( '' !== $item_min ) { $styles[] = '--mrn-card-frame-item-min-width-mobile:' . $item_min; $result['classes'][] = 'has-card-item-min-width-mobile'; }
	if ( in_array( $row['card_grid_breakpoints'] ?? '', array( '900-560', '991-600', '767-single', '900-single', '1199-900' ), true ) ) { $result['classes'][] = 'has-card-grid-' . $row['card_grid_breakpoints']; }
	if ( '991-576' === ( $row['card_grid_breakpoints'] ?? '' ) ) { $result['classes'][] = 'has-card-grid-991-600'; $result['classes'][] = 'has-card-grid-991-576'; }
	$weight = $row['card_label_weight'] ?? ''; if ( is_scalar( $weight ) && in_array( (string) $weight, array_map( 'strval', range( 100, 900, 100 ) ), true ) ) { $styles[] = '--mrn-card-frame-label-weight:' . $weight; $result['classes'][] = 'has-card-label-weight'; }
	$transform = $row['card_label_transform'] ?? ''; if ( in_array( $transform, array( 'none', 'uppercase' ), true ) ) { $styles[] = '--mrn-card-frame-label-transform:' . $transform; $result['classes'][] = 'has-card-label-transform'; }
	$tracking = mrn_base_stack_get_frame_letter_spacing( $row['card_label_letter_spacing'] ?? '' ); if ( '' !== $tracking ) { $styles[] = '--mrn-card-frame-label-letter-spacing:' . $tracking; $result['classes'][] = 'has-card-label-letter-spacing'; }
	$color = mrn_base_stack_get_frame_palette_color( $row['card_label_color'] ?? '' ); if ( '' !== $color ) { $styles[] = '--mrn-card-frame-label-color:' . $color; $result['classes'][] = 'has-card-label-color'; }
	if ( in_array( $row['card_header_alignment'] ?? '', array( 'start', 'center', 'end' ), true ) ) { $styles[] = '--mrn-card-frame-header-alignment:' . $row['card_header_alignment']; }
	foreach ( array( 'item_title_size', 'item_title_line_height', 'item_title_icon_size', 'item_title_icon_gap' ) as $name ) {
		$value = mrn_base_stack_get_content_frame_dimension( $row[ 'card_' . $name ] ?? '', $name );
		if ( '' !== $value ) { $styles[] = '--mrn-card-frame-' . str_replace( '_', '-', $name ) . ':' . $value; $result['classes'][] = 'has-card-' . str_replace( '_', '-', $name ); }
	}
	$weight = $row['card_item_title_weight'] ?? ''; if ( is_scalar( $weight ) && in_array( (string) $weight, array_map( 'strval', range( 100, 900, 100 ) ), true ) ) { $styles[] = '--mrn-card-frame-item-title-weight:' . $weight; $result['classes'][] = 'has-card-item-title-weight'; }
	$color = mrn_base_stack_get_frame_palette_color( $row['card_item_title_color'] ?? '' ); if ( '' !== $color ) { $styles[] = '--mrn-card-frame-item-title-color:' . $color; $result['classes'][] = 'has-card-item-title-color'; }
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
	if ( in_array( $row['card_heading_breaks_mobile'] ?? 0, array( true, 1, '1' ), true ) ) { $result['classes'][] = 'has-card-heading-breaks-mobile'; }
	if ( in_array( $row['card_item_heading_breaks_mobile'] ?? 0, array( true, 1, '1' ), true ) ) { $result['classes'][] = 'has-card-item-heading-breaks-mobile'; }
	$columns = mrn_base_stack_normalize_card_per_row( $row['cards_per_row'] ?? 3 );
	$styles[] = '--mrn-card-frame-columns:' . $columns;
	$styles[] = '--mrn-card-frame-tablet-columns:' . min( 2, $columns );
	$spacing = mrn_base_stack_get_frame_spacing_contract( $row, 'card' );
	$result['classes'] = array_merge( $result['classes'], $spacing['classes'] );
	$styles = array_merge( $styles, $spacing['styles'] );
	$result['attributes']['style'] = implode( ';', $styles );
	return $result;
}
