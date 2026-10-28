<?php
/** Optional native inline section navigation; section spacing stays in row presets. */
function mrn_base_stack_get_jump_nav_frame_fields( $seed ) {
	$fields = array(
		array( 'key' => $seed . '_jump_nav_aria_label', 'name' => 'jump_nav_aria_label', 'label' => 'Navigation Accessible Name', 'type' => 'text', 'instructions' => 'Optional explicit navigation name. Leave empty to use the visible heading or the existing section label.' ),
		array( 'key' => $seed . '_jump_nav_presentation', 'name' => 'jump_nav_presentation', 'label' => 'Navigation Presentation', 'type' => 'select', 'choices' => array( 'standard' => 'Standard', 'inline-pills' => 'Inline Heading and Pills' ), 'default_value' => 'standard', 'multiple' => 0, 'allow_null' => 0, 'return_format' => 'value' ),
	);
	foreach ( array( 'content_width', 'gutter', 'gutter_mobile', 'heading_size', 'heading_line_height', 'link_size', 'link_line_height', 'link_min_height', 'link_padding_inline', 'link_padding_block', 'row_gap', 'column_gap', 'link_row_gap', 'link_column_gap', 'divider_space_before', 'accent_width' ) as $part ) {
		$fields[] = array( 'key' => $seed . '_jump_nav_' . $part, 'name' => 'jump_nav_' . $part, 'label' => 'Navigation ' . ucwords( str_replace( '_', ' ', $part ) ), 'type' => 'text', 'instructions' => 'Optional bounded length, clamp() or Site Text size/line token. Compact gutters: 1199px; stacked heading/wrapped links: 767px. Outer spacing uses row presets.', 'conditional_logic' => array( array( array( 'field' => $seed . '_jump_nav_presentation', 'operator' => '==', 'value' => 'inline-pills' ) ) ) );
	}
	foreach ( array( 'heading', 'text', 'background', 'hover_text', 'hover_background', 'divider', 'accent' ) as $part ) {
		$field = mrn_base_stack_get_background_color_field( $seed . '_jump_nav_' . $part . '_color' ); $field['name'] = 'jump_nav_' . $part . '_color'; $field['label'] = 'Navigation ' . ucwords( str_replace( '_', ' ', $part ) ) . ' Color';
		$field['conditional_logic'] = array( array( array( 'field' => $seed . '_jump_nav_presentation', 'operator' => '==', 'value' => 'inline-pills' ) ) ); $fields[] = $field;
	}
	return $fields;
}

/** Unselected or malformed controls cannot affect an existing navigation row. */
function mrn_base_stack_get_jump_nav_frame_contract( array $row ) {
	$result = array( 'classes' => array(), 'attributes' => array() );
	if ( 'inline-pills' !== ( $row['jump_nav_presentation'] ?? '' ) ) { return $result; }
	$result['classes'][] = 'has-inline-jump-nav'; $styles = array();
	foreach ( mrn_base_stack_get_jump_nav_frame_fields( 'field_mrn_jump_nav_contract' ) as $field ) {
		$name = $field['name']; if ( in_array( $name, array( 'jump_nav_aria_label', 'jump_nav_presentation' ), true ) ) { continue; }
		$raw = $row[ $name ] ?? '';
		$value = str_ends_with( $name, '_color' ) ? mrn_base_stack_get_frame_palette_color( $raw ) : mrn_base_stack_get_content_frame_dimension( $raw, $name );
		if ( '' !== $value ) { $styles[] = '--mrn-' . str_replace( '_', '-', $name ) . ':' . $value; }
	}
	$result['attributes']['style'] = implode( ';', $styles );
	return $result;
}
