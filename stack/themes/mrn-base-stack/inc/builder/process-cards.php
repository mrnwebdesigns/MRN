<?php
/** Native process Cards share ordered labels between an overview and detail panels. */

/** Process headings reuse the existing card label; rich detail copy stays editable in ACF. */
function mrn_base_stack_get_process_card_item_fields( $seed, $presentation_key ) {
	$fields = array(
		array( 'key' => $seed . '_process_item_display', 'name' => 'process_item_display', 'label' => 'Process Step Display', 'type' => 'select', 'choices' => array( '' => 'Overview and Detail', 'overview-only' => 'Overview Only' ), 'default_value' => '', 'instructions' => 'Overview Only keeps this step in the overview without creating a detail panel. Existing detail content remains stored.' ),
		array( 'key' => $seed . '_process_body', 'name' => 'process_body', 'label' => 'Process Detail', 'type' => 'wysiwyg', 'tabs' => 'all', 'toolbar' => 'full', 'media_upload' => 0 ),
		array( 'key' => $seed . '_process_item_tone', 'name' => 'process_item_tone', 'label' => 'Process Step Tone', 'type' => 'select', 'choices' => array( '' => 'Primary', 'muted' => 'Muted' ), 'default_value' => '' ),
	);
	foreach ( $fields as &$field ) { $field['conditional_logic'] = array( array( array( 'field' => $presentation_key, 'operator' => '==', 'value' => 'process' ) ) ); if ( 'process_body' === $field['name'] ) { $field['conditional_logic'][0][] = array( 'field' => $seed . '_process_item_display', 'operator' => '!=', 'value' => 'overview-only' ); } }
	unset( $field );
	return $fields;
}

/** Bounded geometry, Site Text tokens and Site Colors; outer spacing uses row presets. */
function mrn_base_stack_get_process_card_fields( $seed ) {
	$fields = array();
	$geometry = array(
		'overview_space_after' => 'Space After Overview', 'question_min_height' => 'Detail Heading Minimum Height', 'step_diameter' => 'Step Circle Diameter', 'step_diameter_mobile' => 'Step Diameter (Small-Screen Circle View)', 'step_overlap' => 'Circle Overlap', 'step_label_width' => 'Step Label Maximum Width', 'step_label_width_mobile' => 'Step Label Width (Small-Screen Circle View)', 'aside_width' => 'Introduction Column Width', 'column_gap' => 'Introduction and Details Gap', 'sticky_top' => 'Sticky Introduction Top', 'detail_indent' => 'Detail Number Gutter', 'item_space' => 'Detail Vertical Padding', 'item_space_mobile' => 'Detail Padding (767px)', 'answer_gap' => 'Space Before Detail Content', 'answer_max_width' => 'Detail Copy Maximum Width', 'list_column_gap' => 'List Column Gap', 'list_space_before' => 'Space Before Lists', 'column_gap_mobile' => 'Stacked Introduction and Details Gap', 'aside_divider_gap' => 'Space Before Introduction Divider', 'aside_divider_inset' => 'Introduction Divider Inset', 'step_padding_inline' => 'Circle Horizontal Padding', 'step_padding_inline_mobile' => 'Circle Horizontal Padding (Small Screen)', 'step_muted_border_width' => 'Muted Circle Border Width', 'step_number_bleed_block' => 'Circle Number Bottom Bleed', 'paragraph_max_width' => 'Detail Paragraph Maximum Width', 'paragraph_space_before' => 'Detail Paragraph Space Before', 'paragraph_space_after' => 'Detail Paragraph Space After', 'intro_paragraph_space_after' => 'Introduction Paragraph Space After', 'list_space_after' => 'Detail List Space After', 'list_item_space_after' => 'Detail List Item Space After', 'list_padding_before' => 'Detail List Top Padding', 'list_margin_inline_start' => 'Detail List Start Margin', 'detail_number_offset' => 'Detail Number Top Offset', 'detail_number_offset_mobile' => 'Detail Number Top Offset (767px)',
	);
	foreach ( $geometry as $name => $label ) { $fields[] = array( 'key' => $seed . '_process_' . $name, 'name' => 'process_' . $name, 'label' => $label, 'type' => 'text', 'instructions' => 'Optional non-negative length or bounded clamp(). Outer section spacing stays in row presets.' ); }
	foreach ( array( 'step_label', 'step_number', 'intro', 'detail_number', 'detail_heading', 'answer', 'list' ) as $part ) {
		foreach ( array( 'size', 'line_height' ) as $measure ) { $name = $part . '_' . $measure; $fields[] = array( 'key' => $seed . '_process_' . $name, 'name' => 'process_' . $name, 'label' => ucwords( str_replace( '_', ' ', $name ) ), 'type' => 'text', 'instructions' => 'Optional bounded dimension or Site Text token. Line heights also accept positive unitless values.' ); }
		$fields[] = array( 'key' => $seed . '_process_' . $part . '_size_mobile', 'name' => 'process_' . $part . '_size_mobile', 'label' => ucwords( str_replace( '_', ' ', $part ) ) . ( in_array( $part, array( 'step_label', 'step_number' ), true ) ? ' Size (Small-Screen Circle View)' : ' Size (767px)' ), 'type' => 'text', 'instructions' => 'Optional bounded dimension or Site Text size token.' );
	}
	foreach ( array( 'step_background', 'step_text', 'muted_background', 'muted_text', 'step_number', 'muted_number', 'intro', 'detail_heading', 'detail_heading_open', 'answer', 'divider', 'list' ) as $part ) {
		$field = mrn_base_stack_get_background_color_field( $seed . '_process_' . $part . '_color' ); $field['name'] = 'process_' . $part . '_color'; $field['label'] = ucwords( str_replace( '_', ' ', $part ) ) . ' Color'; $fields[] = $field;
	}
	foreach ( array( 'step_number' => 'Circle Number', 'detail_heading' => 'Detail Heading' ) as $part => $label ) { $fields[] = array( 'key' => $seed . '_process_' . $part . '_font', 'name' => 'process_' . $part . '_font', 'label' => $label . ' Font', 'type' => 'select', 'choices' => array( '' => 'Use Existing Font', 'body' => 'Site Body Font', 'heading' => 'Site Heading Font' ), 'default_value' => '' ); }
	foreach ( array( 'detail_heading' => 'Detail Heading', 'list' => 'Detail List Item' ) as $part => $label ) { $fields[] = array( 'key' => $seed . '_process_' . $part . '_weight', 'name' => 'process_' . $part . '_weight', 'label' => $label . ' Weight', 'type' => 'select', 'choices' => array( '' => 'Use Existing Weight' ) + array_combine( range( 100, 900, 100 ), range( 100, 900, 100 ) ), 'default_value' => '' ); }
	$fields[] = array( 'key' => $seed . '_process_detail_heading_line_height_mobile', 'name' => 'process_detail_heading_line_height_mobile', 'label' => 'Detail Heading Line Height (767px)', 'type' => 'text', 'instructions' => 'Optional bounded dimension, positive unitless line height or Site Text line token.' );
	$fields[] = array( 'key' => $seed . '_process_step_mobile_breakpoint', 'name' => 'process_step_mobile_breakpoint', 'label' => 'Small-Screen Circle View', 'type' => 'select', 'choices' => array( '' => 'At 767px and Below', '768' => 'At 768px and Below' ), 'default_value' => '', 'instructions' => 'Controls only circle diameter, inner padding, label width and overview typography. Introduction stacking and detail disclosure retain their own selections.' );
	$fields[] = array( 'key' => $seed . '_process_aside_sizing', 'name' => 'process_aside_sizing', 'label' => 'Introduction Column Sizing', 'type' => 'select', 'choices' => array( '' => 'Use Introduction Width', 'proportional' => 'Share Space Remaining After Gap' ), 'default_value' => '', 'instructions' => 'Proportional sizing divides the available content width after the column gap; the existing introduction width is used otherwise.' );
	foreach ( array( 'aside_share' => 'Introduction Share (%)', 'aside_share_tablet' => 'Introduction Share at 1199px (%)' ) as $name => $label ) { $fields[] = array( 'key' => $seed . '_process_' . $name, 'name' => 'process_' . $name, 'label' => $label, 'type' => 'number', 'min' => 1, 'max' => 99, 'step' => 1, 'default_value' => 'aside_share' === $name ? 25 : '', 'instructions' => 'Used only with proportional sizing. Whole percentage of the space remaining after the gap. Blank tablet share retains the desktop share.' ); }
	$fields[] = array( 'key' => $seed . '_process_columns_breakpoint', 'name' => 'process_columns_breakpoint', 'label' => 'Stack Introduction Above Details', 'type' => 'select', 'choices' => array( '' => 'At 991px and Below', '767' => 'At 767px and Below', '768' => 'At 768px and Below' ), 'default_value' => '', 'instructions' => 'The introduction becomes static and its trailing divider is hidden when columns stack.' );
	$fields[] = array( 'key' => $seed . '_process_aside_minimum', 'name' => 'process_aside_minimum', 'label' => 'Introduction Minimum Width', 'type' => 'select', 'choices' => array( '' => 'Allow Wrapping to Fit', 'content' => 'Preserve Whole Words' ), 'default_value' => '', 'instructions' => 'Optional minimum word width for proportional columns. Other Process rows retain their existing wrapping.' );
	$fields[] = array( 'key' => $seed . '_process_aside_divider', 'name' => 'process_aside_divider', 'label' => 'Introduction Divider', 'type' => 'select', 'choices' => array( '' => 'No Divider', 'trailing' => 'Trailing Line Below Introduction' ), 'default_value' => '', 'instructions' => 'Decorative line follows the sticky introduction and ends at the details container boundary. Uses the existing Divider Color and native gap/inset fields.' );
	$fields[] = array( 'key' => $seed . '_process_list_columns', 'name' => 'process_list_columns', 'label' => 'Detail List Columns', 'type' => 'select', 'choices' => array( '1' => 'One Column', '2' => 'Two Columns; One at 767px' ), 'default_value' => '1' );
	$fields[] = array( 'key' => $seed . '_process_list_columns_mobile', 'name' => 'process_list_columns_mobile', 'label' => 'Detail List Columns (767px)', 'type' => 'select', 'choices' => array( '' => 'Use Existing Small-Screen View', '1' => 'One Column', '2' => 'Two Columns' ), 'default_value' => '', 'instructions' => 'Optional small-screen choice. Blank preserves the existing one-column mobile view.' );
	$fields[] = array( 'key' => $seed . '_process_list_style', 'name' => 'process_list_style', 'label' => 'Detail List Markers', 'type' => 'select', 'choices' => array( '' => 'Use Existing Markers', 'none' => 'No Markers' ), 'default_value' => '' );
	$fields[] = array( 'key' => $seed . '_process_number_start', 'name' => 'process_number_start', 'label' => 'First Step Number', 'type' => 'number', 'default_value' => 1, 'min' => 0, 'max' => 999, 'step' => 1 );
	$fields[] = array( 'key' => $seed . '_process_activation_ratio', 'name' => 'process_activation_ratio', 'label' => 'Detail Activation Position', 'type' => 'number', 'default_value' => 0.45, 'min' => 0.1, 'max' => 0.9, 'step' => 0.05, 'instructions' => 'Fraction of viewport height that activates the next detail. Keyboard focus always takes priority.' );
	$fields[] = array( 'key' => $seed . '_process_mobile_expand_all', 'name' => 'process_mobile_expand_all', 'label' => 'Start All Details Open at 767px and Below', 'type' => 'true_false', 'ui' => 1, 'default_value' => 1 );
	$fields[] = array( 'key' => $seed . '_process_toggle_icon', 'name' => 'process_toggle_icon', 'label' => 'Detail Toggle Icon', 'type' => 'select', 'choices' => array( '' => 'Plus and Minus', 'none' => 'No Icon' ), 'default_value' => '' );
	foreach ( $fields as &$field ) { $field['conditional_logic'] = array( array( array( 'field' => $seed . '_card_presentation', 'operator' => '==', 'value' => 'process' ) ) ); }
	unset( $field );
	return $fields;
}

/** Share the existing focus-safe scroll controller without changing ordinary FAQs. */
function mrn_base_stack_get_process_card_contract( array $row ) {
	$out = array( 'classes' => array(), 'attributes' => array() );
	if ( 'process' !== ( $row['card_presentation'] ?? '' ) ) { return $out; }
	$out['classes'][] = 'has-process-cards';
	if ( '768' === ( $row['process_step_mobile_breakpoint'] ?? '' ) ) { $out['classes'][] = 'has-process-step-768'; }
	foreach ( array( 'step_number', 'detail_heading' ) as $part ) { if ( in_array( $row[ 'process_' . $part . '_font' ] ?? '', array( 'body', 'heading' ), true ) ) { $out['classes'][] = 'has-process-' . str_replace( '_', '-', $part ) . '-font-' . $row[ 'process_' . $part . '_font' ]; } }

	if ( in_array( $row['process_columns_breakpoint'] ?? '', array( '767', '768' ), true ) ) { $out['classes'][] = 'has-process-columns-' . $row['process_columns_breakpoint']; }
	if ( 'content' === ( $row['process_aside_minimum'] ?? '' ) && 'proportional' === ( $row['process_aside_sizing'] ?? '' ) ) { $out['classes'][] = 'has-process-aside-min-content'; }
	if ( 'trailing' === ( $row['process_aside_divider'] ?? '' ) ) { $out['classes'][] = 'has-process-aside-divider'; }
	if ( 'none' === ( $row['process_toggle_icon'] ?? '' ) ) { $out['classes'][] = 'has-process-no-icon'; }
	if ( in_array( $row['process_list_columns'] ?? '', array( 2, '2' ), true ) ) { $out['classes'][] = 'has-process-list-columns'; }
	if ( in_array( $row['process_list_columns_mobile'] ?? '', array( '1', '2' ), true ) ) { $out['classes'][] = 'has-process-list-columns-mobile-' . $row['process_list_columns_mobile']; }
	if ( 'none' === ( $row['process_list_style'] ?? '' ) ) { $out['classes'][] = 'has-process-no-list-markers'; }
	$scroll = mrn_base_stack_get_faq_scroll_contract( array( 'faq_presentation' => 'scroll-active', 'faq_activation_ratio' => $row['process_activation_ratio'] ?? 0.45, 'faq_mobile_expand_all' => $row['process_mobile_expand_all'] ?? 1 ) );
	$out['attributes'] = $scroll['attributes']; $styles = array();
	if ( 'proportional' === ( $row['process_aside_sizing'] ?? '' ) ) {
		foreach ( array( 'aside_share' => '', 'aside_share_tablet' => '-tablet' ) as $name => $suffix ) {
			$share = $row[ 'process_' . $name ] ?? '';
			$valid = ( is_int( $share ) || ( is_string( $share ) && ctype_digit( $share ) ) ) && (int) $share >= 1 && (int) $share <= 99;
			if ( ! $valid && $suffix ) { continue; }
			$share = $valid ? (int) $share : 25;
			$styles[] = '--mrn-process-aside-track' . $suffix . ':' . $share . 'fr';
			$styles[] = '--mrn-process-details-track' . $suffix . ':' . ( 100 - $share ) . 'fr';
		}
	}

	foreach ( array( 'detail_heading', 'list' ) as $part ) { $weight = $row[ 'process_' . $part . '_weight' ] ?? ''; if ( is_scalar( $weight ) && in_array( (string) $weight, array_map( 'strval', range( 100, 900, 100 ) ), true ) ) { $styles[] = '--mrn-process-' . str_replace( '_', '-', $part ) . '-weight:' . $weight; $out['classes'][] = 'has-process-' . str_replace( '_', '-', $part ) . '-weight'; } }
	foreach ( mrn_base_stack_get_process_card_fields( 'field_mrn_process_styles' ) as $field ) {
		$name = $field['name'];
		if ( in_array( $name, array( 'process_number_start', 'process_activation_ratio', 'process_mobile_expand_all', 'process_toggle_icon', 'process_list_columns', 'process_list_style', 'process_aside_sizing', 'process_aside_share', 'process_aside_share_tablet', 'process_columns_breakpoint', 'process_aside_divider', 'process_aside_minimum', 'process_list_columns_mobile', 'process_step_mobile_breakpoint', 'process_step_number_font', 'process_detail_heading_font', 'process_detail_heading_weight', 'process_list_weight' ), true ) ) { continue; }
		$value = 'process_paragraph_max_width' === $name ? mrn_base_stack_validate_card_frame_dimension( $row[ $name ] ?? '', true ) : ( str_ends_with( $name, '_color' ) ? mrn_base_stack_get_frame_palette_color( $row[ $name ] ?? '' ) : mrn_base_stack_get_content_frame_dimension( $row[ $name ] ?? '', $name ) );
		if ( '' !== $value ) {
			$styles[] = '--mrn-' . str_replace( '_', '-', $name ) . ':' . $value;
			if ( in_array( $name, array( 'process_intro_size', 'process_intro_size_mobile', 'process_intro_line_height', 'process_intro_color', 'process_answer_size', 'process_answer_size_mobile', 'process_answer_line_height', 'process_answer_color', 'process_list_margin_inline_start', 'process_step_muted_border_width', 'process_paragraph_max_width', 'process_paragraph_space_before', 'process_paragraph_space_after', 'process_intro_paragraph_space_after', 'process_list_space_after', 'process_list_item_space_after', 'process_list_padding_before', 'process_list_size', 'process_list_size_mobile', 'process_list_line_height', 'process_list_color', 'process_detail_heading_line_height_mobile' ), true ) ) { $out['classes'][] = 'has-' . str_replace( '_', '-', $name ); }
		}
	}
	if ( $styles ) { $out['attributes']['style'] = implode( ';', $styles ); }
	return $out;
}

/** Preserve overview-only steps explicitly rather than guessing from empty content. */
function mrn_base_stack_get_process_card_items( array $row ) {
	if ( 'process' !== ( $row['card_presentation'] ?? '' ) || ! is_array( $row['card_items'] ?? null ) ) { return array(); }
	$start = $row['process_number_start'] ?? 1;
	$start = ( is_int( $start ) || ( is_string( $start ) && ctype_digit( $start ) ) ) && (int) $start >= 0 && (int) $start <= 999 ? (int) $start : 1;
	$out = array(); $position = 0;
	foreach ( $row['card_items'] as $item ) {
		$number = str_pad( (string) ( $start + $position++ ), 2, '0', STR_PAD_LEFT );
		if ( ! is_array( $item ) ) { continue; }
		$label = is_string( $item['card_label'] ?? null ) ? trim( $item['card_label'] ) : '';
		$body = is_string( $item['process_body'] ?? null ) ? $item['process_body'] : '';
		if ( '' === $label && '' === trim( $body ) ) { continue; }
		if ( '' === $label ) { $label = sprintf( __( 'Step %s', 'mrn-base-stack' ), $number ); }
		$out[] = array( 'number' => $number, 'label' => $label, 'body' => $body, 'overview_only' => 'overview-only' === ( $item['process_item_display'] ?? '' ), 'muted' => 'muted' === ( $item['process_item_tone'] ?? '' ) );
	}
	return $out;
}
