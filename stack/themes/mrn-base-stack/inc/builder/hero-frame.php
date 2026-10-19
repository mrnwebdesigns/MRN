<?php
/**
 * Optional native Hero frame controls; empty fields retain existing output.
 *
 * @package mrn-base-stack
 */

/** Return optional controls for basic Heroes, never body layouts. */
function mrn_base_stack_get_hero_frame_fields() {
	$labels = array(
		'inner_gutter' => 'Inner Horizontal Gutter',
		'inner_gutter_mobile' => 'Small-Screen Inner Horizontal Gutter',
		'content_width' => 'Content Maximum Width',
		'content_width_mobile' => 'Small-Screen Content Maximum Width',
		'heading_width' => 'Heading Maximum Width',
		'heading_width_mobile' => 'Small-Screen Heading Maximum Width',
		'text_width' => 'Body Maximum Width',
		'content_gap' => 'Space Between Content Elements',
		'heading_space_after' => 'Space After Heading',
		'heading_space_after_mobile' => 'Small-Screen Space After Heading',
		'actions_space_before' => 'Space Before Actions',
		'actions_row_gap' => 'Space Between Action Rows',
		'actions_column_gap' => 'Space Between Actions',
		'action_icon_height' => 'Action Media Icon Height',
		'heading_size' => 'Heading Font Size',
		'heading_line_height' => 'Heading Line Height',
		'text_size' => 'Body Font Size',
		'text_size_mobile' => 'Small-Screen Body Font Size',
		'text_line_height' => 'Body Line Height',
		'text_line_height_mobile' => 'Small-Screen Body Line Height',
		'paragraph_space_after' => 'Space After Body Paragraphs',
	);
	$fields = array();
	foreach ( $labels as $suffix => $label ) {
		$fields[] = array(
			'key' => 'field_mrn_hero_basic_block_frame_' . $suffix,
			'name' => 'hero_' . $suffix, 'label' => $label, 'type' => 'text',
			'default_value' => '', 'wrapper' => array( 'width' => '50' ),
			'instructions' => 'Optional; blank retains existing formatting. Use a non-negative CSS length or clamp(minimum, preferred, maximum). Widths accept none; line heights accept unitless numbers. Small-screen widths apply at 960px, heading spacing at 767px.',
		);
	}
	$fields[] = array(
		'key' => 'field_mrn_hero_basic_block_frame_heading_weight',
		'name' => 'hero_heading_weight', 'label' => 'Heading Font Weight', 'type' => 'select',
		'choices' => array( '' => 'Use Existing Weight' ) + array_combine( range( 100, 900, 100 ), range( 100, 900, 100 ) ),
		'default_value' => '', 'wrapper' => array( 'width' => '50' ),
	);
	foreach ( array( 'heading' => 'Heading Color', 'text' => 'Body Paragraph Color' ) as $part => $label ) {
		$field = mrn_base_stack_get_background_color_field( 'field_mrn_hero_basic_block_frame_' . $part . '_color' );
		$field['name'] = 'hero_' . $part . '_color';
		$field['label'] = $label;
		$fields[] = $field;
	}
	return $fields;
}

/** Validate lengths and simple fluid clamps without accepting arbitrary CSS. */
function mrn_base_stack_validate_hero_frame_dimension( $raw, $allow_none = false, $allow_unitless = false ) {
	if ( ! is_scalar( $raw ) ) { return ''; }
	$value = strtolower( trim( (string) $raw ) );
	if ( strlen( $value ) > 96 ) { return ''; }
	if ( $allow_none && 'none' === $value ) { return $value; }
	$atomic = static function ( $part ) use ( $allow_unitless ) {
		if ( function_exists( 'mrn_site_styles_sanitize_typography_dimension' ) ) {
			return mrn_site_styles_sanitize_typography_dimension( $part, false, $allow_unitless );
		}
		return preg_match( '/^(?:0|(?:\d+(?:\.\d+)?|\.\d+)' . ( $allow_unitless ? '(?:px|rem|em|%|vw|vh|ch)?' : '(?:px|rem|em|%|vw|vh|ch)' ) . ')$/D', $part ) ? $part : '';
	};
	if ( preg_match( '/^clamp\(([^(),]+),([^(),]+),([^(),]+)\)$/D', $value, $parts ) ) {
		$values = array_map( static function ( $part ) use ( $atomic ) { return $atomic( trim( $part ) ); }, array_slice( $parts, 1 ) );
		return in_array( '', $values, true ) ? '' : 'clamp(' . implode( ', ', $values ) . ')';
	}
	return $atomic( $value );
}

/** Resolve only explicitly authored, validated presentation properties. */
function mrn_base_stack_get_hero_frame_contract( array $row ) {
	$result = array( 'styles' => array(), 'heading_styles' => array(), 'classes' => array() );
	$widths = array( 'content_width', 'content_width_mobile', 'heading_width', 'heading_width_mobile', 'text_width' );
	foreach ( array_merge( $widths, array( 'inner_gutter', 'inner_gutter_mobile', 'content_gap', 'heading_space_after', 'heading_space_after_mobile', 'actions_space_before', 'actions_row_gap', 'actions_column_gap', 'action_icon_height' ) ) as $name ) {
		$value = mrn_base_stack_validate_hero_frame_dimension( $row[ 'hero_' . $name ] ?? '', in_array( $name, $widths, true ) );
		if ( '' !== $value ) {
			$result['styles'][] = '--mrn-hero-' . str_replace( '_', '-', $name ) . ':' . $value;
			if ( 'action_icon_height' === $name ) { $result['classes'][] = 'has-hero-action-icon-height'; }
		}
	}
	foreach ( array( 'heading_size' => 'font-size', 'heading_line_height' => 'line-height' ) as $name => $property ) {
		$value = mrn_base_stack_validate_hero_frame_dimension( $row[ 'hero_' . $name ] ?? '', false, 'line-height' === $property );
		if ( '' !== $value ) { $result['heading_styles'][] = $property . ':' . $value; }
	}
	$weight = $row['hero_heading_weight'] ?? '';
	if ( is_scalar( $weight ) && in_array( (string) $weight, array_map( 'strval', range( 100, 900, 100 ) ), true ) ) {
		$result['heading_styles'][] = 'font-weight:' . (string) $weight;
	}
	foreach ( array( 'heading', 'text' ) as $part ) {
		$slug = $row[ 'hero_' . $part . '_color' ] ?? '';
		if ( is_string( $slug ) && '' !== $slug && function_exists( 'mrn_site_colors_get_value' ) && '' !== mrn_site_colors_get_value( $slug ) ) {
			$color = 'var(' . mrn_site_colors_get_css_var( $slug ) . ')';
			if ( 'heading' === $part ) { $result['heading_styles'][] = 'color:' . $color; }
			else { $result['styles'][] = '--mrn-hero-text-color:' . $color; $result['classes'][] = 'has-hero-text-color'; }
		}
	}
	foreach ( array( 'text_size', 'text_size_mobile', 'text_line_height', 'text_line_height_mobile', 'paragraph_space_after' ) as $name ) {
		$value = mrn_base_stack_validate_hero_frame_dimension( $row[ 'hero_' . $name ] ?? '', false, str_starts_with( $name, 'text_line_height' ) );
		if ( '' !== $value ) {
			$result['styles'][] = '--mrn-hero-' . str_replace( '_', '-', $name ) . ':' . $value;
			$result['classes'][] = 'has-hero-' . str_replace( '_', '-', $name );
		}
	}
	return $result;
}
