<?php
/** Optional editable frames for composed text, metrics and image rows. */
function mrn_base_stack_get_content_frame_config( $layout ) {
	$configs = array(
		'logos' => array( 'prefix' => 'logos', 'dimensions' => array( 'content_width', 'inner_gutter', 'inner_gutter_compact', 'heading_width', 'heading_size', 'heading_line_height', 'heading_size_tablet', 'heading_size_mobile', 'heading_line_height_mobile', 'label_size', 'label_line_height', 'label_line_height_mobile', 'text_width', 'text_size', 'text_line_height', 'text_size_mobile', 'text_line_height_mobile', 'paragraph_space_after', 'section_gap', 'section_gap_tablet', 'section_gap_mobile', 'item_gap', 'item_gap_tablet', 'item_gap_medium', 'item_gap_mobile', 'label_gap', 'label_gap_mobile' ), 'weights' => array( 'heading_weight', 'label_weight', 'text_weight' ), 'colors' => array( 'heading_color', 'label_color', 'text_color' ), 'transforms' => array( 'label_transform' ) ),
		'body_text' => array( 'prefix' => 'body', 'dimensions' => array( 'content_width', 'inner_gutter', 'label_size', 'label_line_height', 'label_space_after', 'heading_size', 'heading_line_height', 'heading_space_after', 'heading_space_after_mobile', 'heading_space_before', 'text_size', 'text_line_height', 'text_space_before', 'text_space_after', 'heading_size_tablet', 'heading_line_height_tablet', 'heading_size_mobile', 'heading_line_height_mobile', 'text_space_before_tablet', 'paragraph_space_after' ), 'weights' => array( 'label_weight', 'heading_weight' ), 'colors' => array( 'label_color', 'heading_color', 'text_color' ), 'transforms' => array( 'label_transform' ) ),
		'stats' => array( 'prefix' => 'stats', 'dimensions' => array( 'content_width', 'inner_gutter', 'value_size', 'value_line_height', 'label_size', 'label_line_height', 'item_gap' ), 'weights' => array( 'value_weight', 'label_weight' ), 'colors' => array( 'value_color', 'label_color' ), 'transforms' => array( 'label_transform' ) ),
		'image_content' => array( 'prefix' => 'image', 'dimensions' => array( 'content_width', 'inner_gutter', 'height', 'height_mobile', 'media_max_width', 'media_max_height_mobile' ), 'weights' => array(), 'colors' => array(), 'transforms' => array() ),
	);
	return $configs[ $layout ] ?? array();
}

/** Keep all values in native fields, with isolated keys from the existing factory. */
function mrn_base_stack_get_content_frame_fields( $layout, $seed ) {
	$config = mrn_base_stack_get_content_frame_config( $layout );
	if ( ! $config ) { return array(); }
	$prefix = $config['prefix'];
	$fields = array( array( 'key' => $seed . '_' . $prefix . '_frame', 'name' => $prefix . '_frame', 'label' => 'Content Frame', 'type' => 'select', 'choices' => array( '' => 'Use Existing Frame', 'contained' => 'Contained Content' ), 'default_value' => '', 'instructions' => 'Optional inner geometry and typography. Outer section spacing stays in row presets. Blank preserves existing output.' ) );
	foreach ( $config['dimensions'] as $name ) {
		$fields[] = array( 'key' => $seed . '_' . $prefix . '_' . $name, 'name' => $prefix . '_' . $name, 'label' => ucwords( str_replace( '_', ' ', $name ) ), 'type' => 'text', 'wrapper' => array( 'width' => '50' ), 'instructions' => 'Optional non-negative CSS length or clamp(). Content width accepts none. Font sizes/line heights also accept existing Site Text tokens, such as var(--site-text-body-3-size) or var(--site-text-h3-line). Small-screen image height applies at 767px.' );
	}
	foreach ( $config['weights'] as $name ) { $fields[] = array( 'key' => $seed . '_' . $prefix . '_' . $name, 'name' => $prefix . '_' . $name, 'label' => ucwords( str_replace( '_', ' ', $name ) ), 'type' => 'select', 'choices' => array( '' => 'Use Existing Weight' ) + array_combine( range( 100, 900, 100 ), range( 100, 900, 100 ) ), 'default_value' => '' ); }
	foreach ( $config['transforms'] as $name ) { $fields[] = array( 'key' => $seed . '_' . $prefix . '_' . $name, 'name' => $prefix . '_' . $name, 'label' => ucwords( str_replace( '_', ' ', $name ) ), 'type' => 'select', 'choices' => array( '' => 'Use Existing Case', 'none' => 'As Entered', 'uppercase' => 'Uppercase' ), 'default_value' => '' ); }
	foreach ( $config['colors'] as $name ) { $field = mrn_base_stack_get_background_color_field( $seed . '_' . $prefix . '_' . $name ); $field['name'] = $prefix . '_' . $name; $field['label'] = ucwords( str_replace( '_', ' ', $name ) ); $fields[] = $field; }
	if ( 'body_text' === $layout ) {
		$fields[] = array( 'key' => $seed . '_body_heading_font', 'name' => 'body_heading_font', 'label' => 'Heading Font', 'type' => 'select', 'choices' => array( '' => 'Use Existing Font', 'heading' => 'Site Heading Font', 'body' => 'Site Body Font' ), 'default_value' => '' );
		foreach ( array( 'text_size_mobile', 'text_line_height_mobile' ) as $name ) { $fields[] = array( 'key' => $seed . '_body_' . $name, 'name' => 'body_' . $name, 'label' => ucwords( str_replace( '_', ' ', $name ) ), 'type' => 'text' ); }
		$fields[] = array( 'key' => $seed . '_body_text_mobile_breakpoint', 'name' => 'body_text_mobile_breakpoint', 'label' => 'Small-Screen Text Breakpoint', 'type' => 'select', 'choices' => array( '560' => '560px', '600' => '600px', '767' => '767px' ), 'default_value' => '560' );
		$fields[] = array( 'key' => $seed . '_body_text_weight', 'name' => 'body_text_weight', 'label' => 'Text Weight', 'type' => 'select', 'choices' => array( '' => 'Use Existing Weight' ) + array_combine( range( 100, 900, 100 ), range( 100, 900, 100 ) ), 'default_value' => '' );
		$fields[] = array( 'key' => $seed . '_body_text_semantics', 'name' => 'body_text_semantics', 'label' => 'Text Semantics', 'type' => 'select', 'choices' => array( '' => 'Standard Text', 'quote' => 'Quotation' ), 'default_value' => '', 'instructions' => 'Quotation uses a semantic blockquote. Keep attribution in editable content alongside the quotation.' );
		$fields[] = array( 'key' => $seed . '_body_quote_mark', 'name' => 'body_quote_mark', 'label' => 'Quotation Mark', 'type' => 'select', 'choices' => array( '' => 'None', 'opening' => 'Opening Mark' ), 'default_value' => '' );
		foreach ( array( 'quote_mark_width', 'quote_mark_gap' ) as $name ) { $fields[] = array( 'key' => $seed . '_body_' . $name, 'name' => 'body_' . $name, 'label' => ucwords( str_replace( '_', ' ', $name ) ), 'type' => 'text', 'instructions' => 'Optional non-negative CSS length. The mark is decorative and does not add spoken text.' ); }
		$field = mrn_base_stack_get_background_color_field( $seed . '_body_quote_mark_color' ); $field['name'] = 'body_quote_mark_color'; $field['label'] = 'Quotation Mark Color'; $fields[] = $field;
		foreach ( array( 'label_letter_spacing', 'text_letter_spacing', 'text_letter_spacing_mobile' ) as $name ) { $fields[] = array( 'key' => $seed . '_body_' . $name, 'name' => 'body_' . $name, 'label' => ucwords( str_replace( '_', ' ', $name ) ), 'type' => 'text', 'instructions' => 'Optional letter spacing from -1em to 1em, or -10px to 10px. Mobile applies at 560px and below.' ); }
		$fields[] = array( 'key' => $seed . '_body_heading_breaks', 'name' => 'body_heading_breaks', 'label' => 'Heading Line Breaks', 'type' => 'select', 'choices' => array( '' => 'Use Existing Formatting', 'preserve' => 'Preserve Entered Line Breaks' ), 'default_value' => '' );
		foreach ( $fields as &$field ) { if ( 'body_heading_space_after_mobile' === $field['name'] ) { $field['instructions'] = 'Optional heading space at 767px and below; blank preserves the desktop value.'; } elseif ( str_ends_with( $field['name'], '_tablet' ) ) { $field['instructions'] = 'Optional override at 900px and below; blank preserves the desktop value. Existing Site Text size/line tokens are accepted.'; } elseif ( str_ends_with( $field['name'], '_mobile' ) ) { $field['instructions'] = 'Optional override at 560px and below; blank preserves the larger-screen value.'; } } unset( $field );
	}
	foreach ( $fields as &$field ) { if ( $prefix . '_frame' !== $field['name'] ) { $field['conditional_logic'] = array( array( array( 'field' => $fields[0]['key'], 'operator' => '==', 'value' => 'contained' ) ) ); } }
	if ( 'body_text' === $layout ) { foreach ( $fields as &$field ) { if ( str_starts_with( $field['name'], 'body_quote_' ) ) { $field['conditional_logic'][0][] = array( 'field' => $seed . '_body_text_semantics', 'operator' => '==', 'value' => 'quote' ); } } }
	unset( $field );
	return $fields;
}

/** Typography may reference only the bounded existing Site Text token namespace. */
function mrn_base_stack_get_content_frame_dimension( $raw, $name ) {
	$name = preg_replace( '/_(?:tablet|mobile)$/D', '', $name );
	$line = str_ends_with( $name, 'line_height' );
	$font = str_ends_with( $name, '_size' );
	if ( is_string( $raw ) && ( $line || $font ) && preg_match( '/^var\(--site-text-(?:h[1-6]|body-[1-3]|label-[1-3])-' . ( $line ? 'line' : 'size' ) . '\)$/D', $raw ) ) { return $raw; }
	return $line ? mrn_base_stack_validate_hero_frame_dimension( $raw, false, true ) : mrn_base_stack_validate_card_frame_dimension( $raw, 'content_width' === $name );
}

/** An unset or invalid property cannot activate a typography override. */
function mrn_base_stack_get_content_frame_contract( array $row, $layout ) {
	$result = array( 'classes' => array(), 'attributes' => array() ); $config = mrn_base_stack_get_content_frame_config( $layout );
	if ( ! $config || 'contained' !== ( $row[ $config['prefix'] . '_frame' ] ?? '' ) ) { return $result; }
	$prefix = $config['prefix']; $result['classes'][] = 'has-' . $prefix . '-frame'; $styles = array();
	foreach ( $config['dimensions'] as $name ) {
		$value = mrn_base_stack_get_content_frame_dimension( $row[ $prefix . '_' . $name ] ?? '', $name );
		if ( '' !== $value ) { $styles[] = '--mrn-' . $prefix . '-frame-' . str_replace( '_', '-', $name ) . ':' . $value; $result['classes'][] = 'has-' . $prefix . '-' . str_replace( '_', '-', $name ); }
	}
	foreach ( $config['weights'] as $name ) { $value = $row[ $prefix . '_' . $name ] ?? ''; if ( is_scalar( $value ) && in_array( (string) $value, array_map( 'strval', range( 100, 900, 100 ) ), true ) ) { $styles[] = '--mrn-' . $prefix . '-frame-' . str_replace( '_', '-', $name ) . ':' . $value; $result['classes'][] = 'has-' . $prefix . '-' . str_replace( '_', '-', $name ); } }
	foreach ( $config['transforms'] as $name ) { $value = $row[ $prefix . '_' . $name ] ?? ''; if ( in_array( $value, array( 'none', 'uppercase' ), true ) ) { $styles[] = '--mrn-' . $prefix . '-frame-' . str_replace( '_', '-', $name ) . ':' . $value; $result['classes'][] = 'has-' . $prefix . '-' . str_replace( '_', '-', $name ); } }
	foreach ( $config['colors'] as $name ) { $value = mrn_base_stack_get_frame_palette_color( $row[ $prefix . '_' . $name ] ?? '' ); if ( '' !== $value ) { $styles[] = '--mrn-' . $prefix . '-frame-' . str_replace( '_', '-', $name ) . ':' . $value; $result['classes'][] = 'has-' . $prefix . '-' . str_replace( '_', '-', $name ); } }
	if ( 'body_text' === $layout ) {
		if ( in_array( $row['body_heading_font'] ?? '', array( 'heading', 'body' ), true ) ) { $result['classes'][] = 'has-body-heading-font-' . $row['body_heading_font']; }
		foreach ( array( 'text_size_mobile', 'text_line_height_mobile', 'quote_mark_width', 'quote_mark_gap' ) as $name ) {
			if ( str_starts_with( $name, 'quote_' ) && 'quote' !== ( $row['body_text_semantics'] ?? '' ) ) { continue; }
			$value = mrn_base_stack_get_content_frame_dimension( $row[ 'body_' . $name ] ?? '', $name );
			if ( '' !== $value ) { $styles[] = '--mrn-body-frame-' . str_replace( '_', '-', $name ) . ':' . $value; $result['classes'][] = 'has-body-' . str_replace( '_', '-', $name ); }
		}
		$weight = $row['body_text_weight'] ?? ''; if ( is_scalar( $weight ) && in_array( (string) $weight, array_map( 'strval', range( 100, 900, 100 ) ), true ) ) { $styles[] = '--mrn-body-frame-text-weight:' . $weight; $result['classes'][] = 'has-body-text-weight'; }
		if ( '600' === ( $row['body_text_mobile_breakpoint'] ?? '' ) ) { $result['classes'][] = 'body-text-mobile-600'; }
		if ( '767' === ( $row['body_text_mobile_breakpoint'] ?? '' ) ) { $result['classes'][] = 'body-text-mobile-767'; }
		if ( 'quote' === ( $row['body_text_semantics'] ?? '' ) && 'opening' === ( $row['body_quote_mark'] ?? '' ) ) {
			$result['classes'][] = 'has-body-quote-mark'; $color = mrn_base_stack_get_frame_palette_color( $row['body_quote_mark_color'] ?? '' );
			if ( '' !== $color ) { $styles[] = '--mrn-body-frame-quote-mark-color:' . $color; }
		}
		foreach ( array( 'label_letter_spacing', 'text_letter_spacing', 'text_letter_spacing_mobile' ) as $name ) { $value = mrn_base_stack_get_frame_letter_spacing( $row[ 'body_' . $name ] ?? '' ); if ( '' !== $value ) { $styles[] = '--mrn-body-frame-' . str_replace( '_', '-', $name ) . ':' . $value; $result['classes'][] = 'has-body-' . str_replace( '_', '-', $name ); } }
		if ( 'preserve' === ( $row['body_heading_breaks'] ?? '' ) ) { $result['classes'][] = 'has-body-heading-breaks'; }
	}
	if ( $styles ) { $result['attributes']['style'] = implode( ';', $styles ); }
	return $result;
}

/** A bounded signed length for tracking, without accepting arbitrary CSS. */
function mrn_base_stack_get_frame_letter_spacing( $raw ) {
	if ( ! is_string( $raw ) || strlen( $raw ) > 32 || ! preg_match( '/^(-?(?:[0-9]+(?:\.[0-9]+)?|\.[0-9]+))(em|px)$/D', $raw, $match ) || abs( (float) $match[1] ) > ( 'em' === $match[2] ? 1 : 10 ) ) { return ''; }
	return $raw;
}
