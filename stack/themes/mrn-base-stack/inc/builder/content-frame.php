<?php
/** Optional editable frames for composed text, metrics and image rows. */
require_once __DIR__ . '/frame-spacing.php';
function mrn_base_stack_get_content_frame_config( $layout ) {
	$configs = array(
		'wpforms' => array( 'prefix' => 'form', 'dimensions' => array( 'canvas_width', 'content_width', 'inner_gutter', 'inner_gutter_compact', 'inner_gutter_mobile', 'heading_size', 'heading_line_height', 'heading_space_after' ), 'weights' => array( 'heading_weight' ), 'colors' => array( 'heading_color' ), 'transforms' => array() ),
		'logos' => array( 'prefix' => 'logos', 'dimensions' => array( 'content_width', 'inner_gutter', 'inner_gutter_compact', 'heading_width', 'heading_size', 'heading_line_height', 'heading_size_tablet', 'heading_size_mobile', 'heading_line_height_mobile', 'label_size', 'label_line_height', 'label_line_height_mobile', 'text_width', 'text_size', 'text_line_height', 'text_size_mobile', 'text_line_height_mobile', 'paragraph_space_after', 'section_gap', 'section_gap_tablet', 'section_gap_mobile', 'item_gap', 'item_gap_tablet', 'item_gap_medium', 'item_gap_mobile', 'label_gap', 'label_gap_mobile' ), 'weights' => array( 'heading_weight', 'label_weight', 'text_weight' ), 'colors' => array( 'heading_color', 'label_color', 'text_color' ), 'transforms' => array( 'label_transform' ) ),
		'body_text' => array( 'prefix' => 'body', 'dimensions' => array( 'content_width', 'inner_gutter', 'label_size', 'label_line_height', 'label_space_after', 'heading_size', 'heading_line_height', 'heading_space_after', 'heading_space_after_mobile', 'heading_space_before', 'heading_space_before_mobile', 'heading_width', 'heading_width_compact', 'inner_gutter_compact', 'text_size', 'text_line_height', 'text_space_before', 'text_space_after', 'heading_size_tablet', 'heading_line_height_tablet', 'heading_size_mobile', 'heading_line_height_mobile', 'text_space_before_tablet', 'text_space_before_mobile', 'paragraph_space_after' ), 'weights' => array( 'label_weight', 'heading_weight', 'link_weight' ), 'colors' => array( 'label_color', 'heading_color', 'text_color', 'link_color', 'link_hover_color' ), 'transforms' => array( 'label_transform' ) ),
		'stats' => array( 'prefix' => 'stats', 'dimensions' => array( 'content_width', 'inner_gutter', 'inner_gutter_compact', 'value_size', 'value_line_height', 'label_size', 'label_line_height', 'item_gap', 'column_gap' ), 'weights' => array( 'value_weight', 'label_weight' ), 'colors' => array( 'value_color', 'label_color', 'divider_color' ), 'transforms' => array( 'label_transform' ) ),
		'video' => array( 'prefix' => 'video', 'dimensions' => array( 'inner_gutter', 'inner_gutter_compact' ), 'weights' => array(), 'colors' => array(), 'transforms' => array() ),
		'image_content' => array( 'prefix' => 'image', 'dimensions' => array( 'content_width', 'inner_gutter', 'height', 'height_tablet', 'height_mobile', 'media_max_width', 'media_max_height_mobile', 'inner_gutter_compact' ), 'weights' => array(), 'colors' => array(), 'transforms' => array() ),
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
	if ( 'wpforms' === $layout ) { $fields[] = array( 'key' => $seed . '_form_content_alignment', 'name' => 'form_content_alignment', 'label' => 'Content Alignment in Canvas', 'type' => 'select', 'choices' => array( '' => 'Leading Edge', 'center' => 'Center', 'end' => 'Trailing Edge' ), 'default_value' => '', 'instructions' => 'Aligns the bounded heading/form group inside its canvas. Section margins and padding remain in row presets.' ); }
	if ( 'wpforms' === $layout ) { $fields[] = array( 'key' => $seed . '_form_heading_mobile', 'name' => 'form_heading_mobile', 'label' => 'Small-Screen Heading', 'type' => 'select', 'choices' => array( '' => 'Visible', 'screen-reader' => 'Visually Hidden, Available to Screen Readers' ), 'default_value' => '', 'instructions' => 'Optional at 767px and below. The semantic heading remains available to assistive technology.' ); }
	if ( 'stats' === $layout ) {
		$fields[] = array( 'key' => $seed . '_stats_value_font', 'name' => 'stats_value_font', 'label' => 'Value Font', 'type' => 'select', 'choices' => array( '' => 'Use Existing Font', 'heading' => 'Site Heading Font', 'body' => 'Site Body Font' ), 'default_value' => '' );
		$fields[] = array( 'key' => $seed . '_stats_label_letter_spacing', 'name' => 'stats_label_letter_spacing', 'label' => 'Label Letter Spacing', 'type' => 'text', 'instructions' => 'Optional tracking from -1em to 1em, or -10px to 10px.' );
	}
	if ( 'body_text' === $layout ) {
		$fields = array_merge( $fields, mrn_base_stack_get_frame_spacing_fields( $seed, 'body' ) );
		$fields[] = array( 'key' => $seed . '_body_content_sizing', 'name' => 'body_content_sizing', 'label' => 'Content Group Width', 'type' => 'select', 'choices' => array( '' => 'Use Existing Width', 'fit' => 'Fit Content Within Canvas' ), 'default_value' => '', 'instructions' => 'Keep the heading and body together in a bounded, naturally sized group. Outer section spacing remains in row presets.' );
		$fields[] = array( 'key' => $seed . '_body_lead_style', 'name' => 'body_lead_style', 'label' => 'First Body Paragraph Style', 'type' => 'select', 'choices' => array( '' => 'Use Existing Paragraph Style', 'label' => 'Use Label Typography' ), 'default_value' => '', 'instructions' => 'Apply this row’s Label typography and spacing to the first direct body paragraph without changing its editable rich text.' );
		$fields[] = array( 'key' => $seed . '_body_link_decoration', 'name' => 'body_link_decoration', 'label' => 'Body Link Decoration', 'type' => 'select', 'choices' => array( '' => 'Use Existing Decoration', 'none' => 'None', 'underline' => 'Underline' ), 'default_value' => '', 'instructions' => 'Optional for links inside this row’s body. Keyboard focus indicators remain available.' );
		$fields[] = array( 'key' => $seed . '_body_frame_breakpoints', 'name' => 'body_frame_breakpoints', 'label' => 'Heading and Text Spacing Breakpoints', 'type' => 'select', 'choices' => array( '' => 'Use Existing (900px / 560px)', '991-600' => 'Tablet at 991px; Mobile at 600px' ), 'default_value' => '', 'instructions' => 'Controls heading size/line height and space before text. Body text size keeps its separate small-screen breakpoint.' );
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
		foreach ( $fields as &$field ) { if ( in_array( $field['name'], array( 'body_heading_space_before_mobile', 'body_heading_space_after_mobile' ), true ) ) { $field['instructions'] = 'Optional heading space at 767px and below; blank preserves the desktop value.'; } elseif ( str_ends_with( $field['name'], '_tablet' ) ) { $field['instructions'] = 'Optional override at 900px and below; blank preserves the desktop value. Existing Site Text size/line tokens are accepted.'; } elseif ( str_ends_with( $field['name'], '_mobile' ) ) { $field['instructions'] = 'Optional override at 560px and below; blank preserves the larger-screen value.'; } } unset( $field );
	}
	if ( 'image_content' === $layout ) { $fields[] = array( 'key' => $seed . '_image_media_sizing', 'name' => 'image_media_sizing', 'label' => 'Image Proportions', 'type' => 'select', 'choices' => array( '' => 'Use Existing Sizing', 'natural' => 'Original Proportions', 'cover-frame' => 'Fill Declared Frame' ), 'default_value' => '', 'instructions' => 'For Standard or Scroll Drift media, retain original attachment proportions without the default height cap. Responsive image sources remain enabled. Fixed-height media stages keep their own sizing.' ); }
	foreach ( $fields as &$field ) { if ( $prefix . '_inner_gutter_compact' === $field['name'] ) { $field['instructions'] = 'Optional inner gutter at 1199px and below; blank keeps the desktop gutter. Outer spacing stays in row presets.'; } } unset( $field );
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
	if ( 'wpforms' === $layout && in_array( $row['form_content_alignment'] ?? '', array( 'center', 'end' ), true ) ) { $result['classes'][] = 'has-form-content-align-' . $row['form_content_alignment']; }
	if ( 'wpforms' === $layout && 'screen-reader' === ( $row['form_heading_mobile'] ?? '' ) ) { $result['classes'][] = 'has-form-heading-mobile-sr'; }
	if ( 'stats' === $layout ) {
		if ( in_array( $row['stats_value_font'] ?? '', array( 'heading', 'body' ), true ) ) { $result['classes'][] = 'has-stats-value-font-' . $row['stats_value_font']; }
		$tracking = mrn_base_stack_get_frame_letter_spacing( $row['stats_label_letter_spacing'] ?? '' );
		if ( '' !== $tracking ) { $styles[] = '--mrn-stats-frame-label-letter-spacing:' . $tracking; $result['classes'][] = 'has-stats-label-letter-spacing'; }
	}
	if ( 'body_text' === $layout ) {
		$spacing = mrn_base_stack_get_frame_spacing_contract( $row, 'body' );
		$result['classes'] = array_merge( $result['classes'], $spacing['classes'] );
		$styles = array_merge( $styles, $spacing['styles'] );
		if ( 'fit' === ( $row['body_content_sizing'] ?? '' ) ) { $result['classes'][] = 'has-body-content-fit'; }
		if ( 'label' === ( $row['body_lead_style'] ?? '' ) ) { $result['classes'][] = 'has-body-lead-label'; }
		if ( in_array( $row['body_link_decoration'] ?? '', array( 'none', 'underline' ), true ) ) { $result['classes'][] = 'has-body-link-decoration'; $styles[] = '--mrn-body-frame-link-decoration:' . $row['body_link_decoration']; }
		if ( '991-600' === ( $row['body_frame_breakpoints'] ?? '' ) ) { $result['classes'][] = 'has-body-breakpoints-991-600'; }
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
	if ( 'image_content' === $layout && 'cover-frame' === ( $row['image_media_sizing'] ?? '' ) ) { $result['classes'][] = 'has-image-fill-media'; }
	if ( 'image_content' === $layout && 'natural' === ( $row['image_media_sizing'] ?? '' ) && in_array( $row['image_presentation'] ?? 'standard', array( '', 'standard', 'scroll-drift' ), true ) ) { $result['classes'][] = 'has-image-natural-media'; }
	if ( $styles ) { $result['attributes']['style'] = implode( ';', $styles ); }
	return $result;
}

/** A bounded signed length for tracking, without accepting arbitrary CSS. */
function mrn_base_stack_get_frame_letter_spacing( $raw ) {
	if ( ! is_string( $raw ) || strlen( $raw ) > 32 || ! preg_match( '/^(-?(?:[0-9]+(?:\.[0-9]+)?|\.[0-9]+))(em|px)$/D', $raw, $match ) || abs( (float) $match[1] ) > ( 'em' === $match[2] ? 1 : 10 ) ) { return ''; }
	return $raw;
}
