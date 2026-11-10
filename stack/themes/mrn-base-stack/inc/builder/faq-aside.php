<?php
/** Optional native FAQ typography and Split aside geometry. */

/** Editable FAQ fields; blank frame preserves existing output. */
function mrn_base_stack_get_faq_frame_fields( $seed ) {
	$fields = array( array( 'key' => $seed . '_faq_frame', 'name' => 'faq_frame', 'label' => 'FAQ Frame', 'type' => 'select', 'choices' => array( '' => 'Use Existing Frame', 'editorial' => 'Editorial Questions' ), 'default_value' => '' ) );
	foreach ( array( 'question_size' => 'Question Font Size', 'question_size_mobile' => 'Small-Screen Question Font Size', 'question_line_height' => 'Question Line Height', 'answer_size' => 'Answer Font Size', 'answer_size_mobile' => 'Small-Screen Answer Font Size', 'answer_line_height' => 'Answer Line Height', 'answer_paragraph_space_after' => 'Space After Answer Paragraphs', 'item_padding' => 'Vertical Space Inside Each Item', 'item_padding_mobile' => 'Small-Screen Item Space', 'answer_space_before' => 'Space Before Answers', 'icon_gap' => 'Space Between Icon and Question', 'icon_width' => 'Question Icon Width', 'item_gap' => 'Space Between Questions', 'inner_gutter' => 'Inner Horizontal Gutter' ) as $name => $label ) {
		$fields[] = array( 'key' => $seed . '_faq_' . $name, 'name' => 'faq_' . $name, 'label' => $label, 'type' => 'text', 'default_value' => '', 'instructions' => 'Optional non-negative CSS length or clamp(). Line heights also accept unitless numbers. Small-screen values apply at 767px; section spacing uses row presets.', 'wrapper' => array( 'width' => '50' ) );
	}
	$fields[] = array( 'key' => $seed . '_faq_question_letter_spacing', 'name' => 'faq_question_letter_spacing', 'label' => 'Question Letter Spacing', 'type' => 'text', 'default_value' => '', 'instructions' => 'Optional bounded letter spacing, including negative em/px values. Blank retains existing tracking.' );
	$fields[] = array( 'key' => $seed . '_faq_question_weight', 'name' => 'faq_question_weight', 'label' => 'Question Font Weight', 'type' => 'select', 'choices' => array( '' => 'Use Existing Weight' ) + array_combine( range( 100, 900, 100 ), range( 100, 900, 100 ) ), 'default_value' => '' );
	$fields[] = array( 'key' => $seed . '_faq_question_font', 'name' => 'faq_question_font', 'label' => 'Question Font', 'type' => 'select', 'choices' => array( '' => 'Use Existing Font', 'heading' => 'Site Heading Font', 'body' => 'Site Body Font' ), 'default_value' => '' );
	$fields[] = array( 'key' => $seed . '_faq_icon_style', 'name' => 'faq_icon_style', 'label' => 'Question Icon', 'type' => 'select', 'choices' => array( '' => 'Use Existing Icon', 'chevron' => 'Chevron' ), 'default_value' => '' );
	$fields[] = array( 'key' => $seed . '_faq_item_border', 'name' => 'faq_item_border', 'label' => 'Question Dividers', 'type' => 'select', 'choices' => array( '' => 'Use Existing Dividers', 'none' => 'None' ), 'default_value' => '' );
	$fields[] = array( 'key' => $seed . '_faq_exclusive', 'name' => 'faq_exclusive', 'label' => 'Keep One Answer Open', 'type' => 'true_false', 'ui' => 1, 'default_value' => 0 );
	foreach ( array( 'question', 'question_open', 'answer', 'icon', 'icon_open' ) as $part ) {
		$field = mrn_base_stack_get_background_color_field( $seed . '_faq_' . $part . '_color' );
		$field['name'] = 'faq_' . $part . '_color'; $field['label'] = ucwords( str_replace( '_', ' ', $part ) ) . ' Color'; $fields[] = $field;
	}
	foreach ( $fields as &$field ) {
		if ( $field['name'] !== $fields[0]['name'] ) { $field['conditional_logic'] = array( array( array( 'field' => $fields[0]['key'], 'operator' => '==', 'value' => 'faq_frame' === $fields[0]['name'] ? 'editorial' : 'aside' ) ) ); }
	}
	unset( $field );
	return array_merge( $fields, mrn_base_stack_get_faq_scroll_fields( $seed ) );
}

/** Palette lookup is explicit; an unknown slug cannot create a CSS variable. */
function mrn_base_stack_get_frame_palette_color( $slug ) {
	if ( ! is_string( $slug ) || '' === $slug || ! function_exists( 'mrn_site_colors_get_all' ) || ! function_exists( 'mrn_site_colors_get_css_var' ) ) { return ''; }
	foreach ( mrn_site_colors_get_all() as $color ) {
		if ( $slug === ( $color['slug'] ?? '' ) ) { return 'var(' . mrn_site_colors_get_css_var( $slug ) . ')'; }
	}
	return '';
}

/** Validated native FAQ styles, scoped to an explicitly selected frame. */
function mrn_base_stack_get_faq_frame_contract( array $row ) {
	$result = array( 'classes' => array(), 'attributes' => array() );
	if ( 'editorial' !== ( $row['faq_frame'] ?? '' ) ) { return $result; }
	$result['classes'][] = 'has-faq-frame'; $styles = array();
	foreach ( array( 'question_size', 'question_size_mobile', 'question_line_height', 'answer_size', 'answer_size_mobile', 'answer_line_height', 'answer_paragraph_space_after', 'item_padding', 'item_padding_mobile', 'answer_space_before', 'icon_gap', 'icon_width', 'item_gap', 'inner_gutter' ) as $name ) {
		$value = mrn_base_stack_get_content_frame_dimension( $row[ 'faq_' . $name ] ?? '', $name );
		if ( '' !== $value ) {
			$styles[] = '--mrn-faq-frame-' . str_replace( '_', '-', $name ) . ':' . $value;
			if ( in_array( $name, array( 'answer_size', 'answer_size_mobile', 'answer_paragraph_space_after' ), true ) ) { $result['classes'][] = 'has-faq-' . str_replace( '_', '-', $name ); }
		}
	}
	$tracking = mrn_base_stack_get_frame_letter_spacing( $row['faq_question_letter_spacing'] ?? '' );
	if ( '' !== $tracking ) { $styles[] = '--mrn-faq-frame-question-letter-spacing:' . $tracking; $result['classes'][] = 'has-faq-question-tracking'; }
	$weight = $row['faq_question_weight'] ?? '';
	if ( is_scalar( $weight ) && in_array( (string) $weight, array_map( 'strval', range( 100, 900, 100 ) ), true ) ) { $styles[] = '--mrn-faq-frame-question-weight:' . $weight; }
	foreach ( array( 'question', 'question_open', 'answer', 'icon', 'icon_open' ) as $part ) {
		$value = mrn_base_stack_get_frame_palette_color( $row[ 'faq_' . $part . '_color' ] ?? '' );
		if ( '' !== $value ) { $styles[] = '--mrn-faq-frame-' . str_replace( '_', '-', $part ) . '-color:' . $value; }
	}
	if ( in_array( $row['faq_question_font'] ?? '', array( 'heading', 'body' ), true ) ) { $result['classes'][] = 'has-faq-question-font-' . $row['faq_question_font']; }
	foreach ( array( 'heading_size', 'heading_line_height', 'column_gap' ) as $name ) {
		$value = mrn_base_stack_get_content_frame_dimension( $row[ 'faq_' . $name ] ?? '', $name );
		if ( '' !== $value ) { $styles[] = '--mrn-faq-frame-' . str_replace( '_', '-', $name ) . ':' . $value; $result['classes'][] = 'has-faq-frame-heading'; }
	}
	$heading_weight = $row['faq_heading_weight'] ?? '';
	if ( is_scalar( $heading_weight ) && in_array( (string) $heading_weight, array_map( 'strval', range( 100, 900, 100 ) ), true ) ) { $styles[] = '--mrn-faq-frame-heading-weight:' . $heading_weight; $result['classes'][] = 'has-faq-frame-heading'; }
	$heading_color = mrn_base_stack_get_frame_palette_color( $row['faq_heading_color'] ?? '' );
	if ( '' !== $heading_color ) { $styles[] = '--mrn-faq-frame-heading-color:' . $heading_color; $result['classes'][] = 'has-faq-frame-heading'; }
	if ( 'chevron' === ( $row['faq_icon_style'] ?? '' ) ) { $result['classes'][] = 'has-faq-chevron'; }
	if ( 'none' === ( $row['faq_item_border'] ?? '' ) ) { $result['classes'][] = 'has-faq-no-dividers'; }
	if ( $styles ) { $result['attributes']['style'] = implode( ';', $styles ); }
	return $result;
}

/** Optional finite Split geometry for a main column and a right aside. */
function mrn_base_stack_get_split_aside_fields( $seed ) {
	$fields = array( array( 'key' => $seed . '_split_frame', 'name' => 'split_frame', 'label' => 'Split Frame', 'type' => 'select', 'choices' => array( '' => 'Use Existing Frame', 'aside' => 'Main Content with Aside', 'columns' => 'Contained Columns', 'bounded' => 'Bounded Columns' ), 'default_value' => '', 'instructions' => 'Aside uses a fixed right column. Contained Columns uses the selected ratio. Bounded Columns uses editable minimum/maximum widths. Blank retains existing geometry.' ) );
	foreach ( array( 'content_width' => 'Content Maximum Width', 'inner_gutter' => 'Inner Horizontal Gutter', 'inner_gutter_mobile' => 'Small-Screen Inner Gutter (1199px)', 'aside_width' => 'Aside Width', 'column_gap' => 'Space Between Columns', 'column_gap_mobile' => 'Space Between Stacked Columns', 'aside_sticky_top' => 'Sticky Aside Top Offset', 'heading_space_after' => 'Space After Section Heading', 'heading_size' => 'Section Heading Font Size', 'heading_line_height' => 'Section Heading Line Height' ) as $name => $label ) {
		$fields[] = array( 'key' => $seed . '_split_' . $name, 'name' => 'split_' . $name, 'label' => $label, 'type' => 'text', 'default_value' => '', 'instructions' => 'Optional non-negative CSS length or clamp(). Width accepts none; line height accepts unitless numbers. Outer section spacing uses row presets.', 'wrapper' => array( 'width' => '50' ) );
	}
	foreach ( array( 'first_column_min' => 'First Column Minimum Width', 'first_column_max' => 'First Column Maximum Width', 'second_column_max' => 'Second Column Maximum Width', 'column_gap_tablet' => 'Column Gap at 1024px and Below', 'first_column_max_tablet' => 'First Column Maximum at 1024px', 'second_column_max_tablet' => 'Second Column Maximum at 1024px' ) as $name => $label ) {
		$fields[] = array( 'key' => $seed . '_split_' . $name, 'name' => 'split_' . $name, 'label' => $label, 'type' => 'text', 'default_value' => '', 'instructions' => 'Optional non-negative length or clamp(). Column bounds also accept calc(45% - 40px), with 0–100% and 0–200px. Only applies to Bounded Columns; outer spacing remains in row presets.', 'wrapper' => array( 'width' => '50' ) );
	}
	$fields[] = array( 'key' => $seed . '_split_stack_breakpoint', 'name' => 'split_stack_breakpoint', 'label' => 'Columns Stack Below', 'type' => 'select', 'choices' => array( '767' => '768px', '900' => '901px', '991' => '992px' ), 'default_value' => '991' );
	$fields[] = array( 'key' => $seed . '_split_column_order', 'name' => 'split_column_order', 'label' => 'Desktop Column Order', 'type' => 'select', 'choices' => array( '' => 'Authored Order', 'reverse' => 'Reverse Above Stack Breakpoint' ), 'default_value' => '', 'instructions' => 'Use for media and text when the first authored column should remain first on small screens. Avoid changing the visual order of keyboard-interactive columns.' );
	$fields[] = array( 'key' => $seed . '_split_mobile_action_after_columns', 'name' => 'split_mobile_action_after_columns', 'label' => 'Place Last Left Action After Columns on Small Screens', 'type' => 'true_false', 'ui' => 1, 'default_value' => 0, 'instructions' => 'For a simple final CTA in the left column, show it after the right column below the selected stack breakpoint. The action is rendered once per view; blank preserves its current position.' );
	$fields[] = array( 'key' => $seed . '_split_mobile_action_space_before', 'name' => 'split_mobile_action_space_before', 'label' => 'Space Before Small-Screen Action', 'type' => 'text', 'default_value' => '', 'instructions' => 'Optional non-negative CSS length added after the stacked column gap. Outer section spacing remains in row presets.' );
	$fields[] = array( 'key' => $seed . '_split_row_vertical_padding', 'name' => 'split_row_vertical_padding', 'label' => 'Use Row Vertical Padding', 'type' => 'true_false', 'ui' => 1, 'default_value' => 0, 'instructions' => 'Apply the native Row Spacing top and bottom padding presets to this framed Split. Blank retains existing spacing.' );
	$fields[] = array( 'key' => $seed . '_split_row_horizontal_padding', 'name' => 'split_row_horizontal_padding', 'label' => 'Use Row Side Padding', 'type' => 'true_false', 'ui' => 1, 'default_value' => 0, 'instructions' => 'Apply the native Row Spacing left and right padding inside this framed Split, in addition to its frame gutter.' );
	$fields[] = array( 'key' => $seed . '_split_row_spacing_mobile_breakpoint', 'name' => 'split_row_spacing_mobile_breakpoint', 'label' => 'Row Spacing Mobile Breakpoint', 'type' => 'select', 'choices' => array( '' => 'Site Default (782px)', '767' => '767px' ), 'default_value' => '' );
	$fields[] = array( 'key' => $seed . '_split_stacked_column_min', 'name' => 'split_stacked_column_min', 'label' => 'Small-Screen Stacked Column Minimum Width', 'type' => 'text', 'default_value' => '', 'instructions' => 'Optional minimum width at 560px and below when inner row padding narrows the content canvas. Keep the rendered result within the viewport.', 'wrapper' => array( 'width' => '50' ) );
	$fields[] = array( 'key' => $seed . '_split_heading_alignment', 'name' => 'split_heading_alignment', 'label' => 'Section Heading Alignment', 'type' => 'select', 'choices' => array( '' => 'Use Existing Alignment', 'start' => 'Start', 'center' => 'Center', 'end' => 'End' ), 'default_value' => '' );
	$fields[] = array( 'key' => $seed . '_split_heading_space_after_mobile', 'name' => 'split_heading_space_after_mobile', 'label' => 'Small-Screen Space After Section Heading', 'type' => 'text', 'instructions' => 'Optional non-negative CSS length at 767px and below.' );
	$field = mrn_base_stack_get_background_color_field( $seed . '_split_heading_color' ); $field['name'] = 'split_heading_color'; $field['label'] = 'Section Heading Color'; $fields[] = $field;
	foreach ( $fields as &$field ) {
		if ( 'split_frame' === $field['name'] ) { continue; }
		if ( in_array( $field['name'], array( 'split_first_column_min', 'split_first_column_max', 'split_second_column_max', 'split_column_gap_tablet', 'split_first_column_max_tablet', 'split_second_column_max_tablet' ), true ) ) { $modes = array( 'bounded' ); }
		elseif ( in_array( $field['name'], array( 'split_stack_breakpoint', 'split_column_order', 'split_mobile_action_after_columns', 'split_mobile_action_space_before', 'split_row_horizontal_padding', 'split_row_spacing_mobile_breakpoint', 'split_stacked_column_min' ), true ) ) { $modes = array( 'columns', 'bounded' ); }
		elseif ( in_array( $field['name'], array( 'split_aside_width', 'split_aside_sticky_top' ), true ) ) { $modes = array( 'aside' ); }
		else { $modes = array( 'aside', 'columns', 'bounded' ); }
		$field['conditional_logic'] = array();
		foreach ( $modes as $mode ) { $field['conditional_logic'][] = array( array( 'field' => $fields[0]['key'], 'operator' => '==', 'value' => $mode ) ); }
	}
	unset( $field );
	return $fields;
}

/** Resolve opt-in Split geometry; no arbitrary CSS or media queries. */
function mrn_base_stack_get_split_aside_contract( array $row ) {
	$result = array( 'classes' => array(), 'attributes' => array() );
	if ( ! in_array( $row['split_frame'] ?? '', array( 'aside', 'columns', 'bounded' ), true ) ) { return $result; }
	$result['classes'][] = 'aside' === $row['split_frame'] ? 'has-split-aside-frame' : 'has-split-columns-frame';
	if ( 'aside' !== $row['split_frame'] ) { $result['classes'][] = 'split-stack-' . ( in_array( $row['split_stack_breakpoint'] ?? '', array( '767', '900' ), true ) ? $row['split_stack_breakpoint'] : '991' ); }
	if ( 'bounded' === $row['split_frame'] ) { $result['classes'][] = 'has-split-bounded-columns'; }
	if ( is_scalar( $row['split_row_vertical_padding'] ?? null ) && '1' === (string) $row['split_row_vertical_padding'] ) { $result['classes'][] = 'has-split-row-vertical-padding'; }
	if ( 'aside' !== $row['split_frame'] && is_scalar( $row['split_row_horizontal_padding'] ?? null ) && '1' === (string) $row['split_row_horizontal_padding'] ) { $result['classes'][] = 'has-split-row-horizontal-padding'; }
	if ( 'aside' !== $row['split_frame'] && '767' === ( $row['split_row_spacing_mobile_breakpoint'] ?? '' ) ) { $result['classes'][] = 'has-split-row-spacing-at-767'; }
	if ( 'aside' !== $row['split_frame'] && '' !== mrn_base_stack_validate_hero_frame_dimension( $row['split_stacked_column_min'] ?? '', false, false ) ) { $result['classes'][] = 'has-split-stacked-column-min'; }
	if ( 'aside' !== $row['split_frame'] && 'reverse' === ( $row['split_column_order'] ?? '' ) ) { $result['classes'][] = 'has-split-reverse-columns'; }
	if ( 'aside' !== $row['split_frame'] && ! empty( $row['split_mobile_action_after_columns'] ) ) {
		$result['classes'][] = 'has-split-mobile-action-after-columns';
		$action_gap = mrn_base_stack_validate_hero_frame_dimension( $row['split_mobile_action_space_before'] ?? '' );
		if ( '' !== $action_gap ) { $result['attributes']['style'] = '--mrn-split-mobile-action-space-before:' . $action_gap; }
	}
	$styles = array();
	foreach ( array( 'content_width', 'inner_gutter', 'inner_gutter_mobile', 'aside_width', 'column_gap', 'column_gap_mobile', 'stacked_column_min', 'aside_sticky_top', 'heading_space_after', 'heading_space_after_mobile', 'heading_size', 'heading_line_height' ) as $name ) {
		$value = mrn_base_stack_validate_hero_frame_dimension( $row[ 'split_' . $name ] ?? '', 'content_width' === $name, 'heading_line_height' === $name );
		if ( '' !== $value ) {
			$styles[] = '--mrn-split-frame-' . str_replace( '_', '-', $name ) . ':' . $value;
			if ( in_array( $name, array( 'heading_size', 'heading_line_height', 'heading_space_after', 'heading_space_after_mobile' ), true ) ) { $result['classes'][] = 'has-split-' . str_replace( '_', '-', $name ); }
		}
	}
	if ( 'bounded' === $row['split_frame'] ) {
		foreach ( array( 'first_column_min', 'first_column_max', 'second_column_max', 'column_gap_tablet', 'first_column_max_tablet', 'second_column_max_tablet' ) as $name ) {
			$value = mrn_base_stack_validate_hero_frame_dimension( $row[ 'split_' . $name ] ?? '', false, false );
			if ( '' === $value && 'column_gap_tablet' !== $name ) { $value = mrn_base_stack_validate_split_column_bound( $row[ 'split_' . $name ] ?? '' ); }
			if ( '' !== $value ) { $styles[] = '--mrn-split-frame-' . str_replace( '_', '-', $name ) . ':' . $value; }
		}
	}
	if ( in_array( $row['split_heading_alignment'] ?? '', array( 'start', 'center', 'end' ), true ) ) { $styles[] = '--mrn-split-frame-heading-alignment:' . $row['split_heading_alignment']; $result['classes'][] = 'has-split-heading-alignment'; }
	$color = mrn_base_stack_get_frame_palette_color( $row['split_heading_color'] ?? '' );
	if ( '' !== $color ) { $styles[] = '--mrn-split-frame-heading-color:' . $color; $result['classes'][] = 'has-split-heading-color'; }
	if ( $styles ) { $result['attributes']['style'] = trim( ( $result['attributes']['style'] ?? '' ) . ';' . implode( ';', $styles ), ';' ); }
	return $result;
}

/** Permit one bounded percentage subtraction for proportional column widths. */
function mrn_base_stack_validate_split_column_bound( $raw ) {
	if ( ! is_string( $raw ) || strlen( $raw ) > 64 || ! preg_match( '/^calc\(\s*([0-9]+(?:\.[0-9]+)?)%\s+-\s+([0-9]+(?:\.[0-9]+)?)px\s*\)$/D', $raw, $parts ) || (float) $parts[1] > 100 || (float) $parts[2] > 200 ) { return ''; }
	return 'calc(' . (float) $parts[1] . '% - ' . (float) $parts[2] . 'px)';
}
