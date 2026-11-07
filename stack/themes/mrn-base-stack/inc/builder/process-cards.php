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
		'overview_space_after' => 'Space After Overview', 'step_diameter' => 'Step Circle Diameter', 'step_diameter_mobile' => 'Step Diameter (767px)', 'step_overlap' => 'Circle Overlap', 'step_label_width' => 'Step Label Maximum Width', 'step_label_width_mobile' => 'Step Label Width (767px)', 'aside_width' => 'Introduction Column Width', 'column_gap' => 'Introduction and Details Gap', 'sticky_top' => 'Sticky Introduction Top', 'detail_indent' => 'Detail Number Gutter', 'item_space' => 'Detail Vertical Padding', 'item_space_mobile' => 'Detail Padding (767px)', 'answer_gap' => 'Space Before Detail Content', 'answer_max_width' => 'Detail Copy Maximum Width', 'list_column_gap' => 'List Column Gap', 'list_space_before' => 'Space Before Lists', 'column_gap_mobile' => 'Stacked Introduction and Details Gap', 'aside_divider_gap' => 'Space Before Introduction Divider', 'aside_divider_inset' => 'Introduction Divider Inset',
	);
	foreach ( $geometry as $name => $label ) { $fields[] = array( 'key' => $seed . '_process_' . $name, 'name' => 'process_' . $name, 'label' => $label, 'type' => 'text', 'instructions' => 'Optional non-negative length or bounded clamp(). Outer section spacing stays in row presets.' ); }
	foreach ( array( 'step_label', 'step_number', 'intro', 'detail_number', 'detail_heading', 'answer' ) as $part ) {
		foreach ( array( 'size', 'line_height' ) as $measure ) { $name = $part . '_' . $measure; $fields[] = array( 'key' => $seed . '_process_' . $name, 'name' => 'process_' . $name, 'label' => ucwords( str_replace( '_', ' ', $name ) ), 'type' => 'text', 'instructions' => 'Optional bounded dimension or Site Text token. Line heights also accept positive unitless values.' ); }
		$fields[] = array( 'key' => $seed . '_process_' . $part . '_size_mobile', 'name' => 'process_' . $part . '_size_mobile', 'label' => ucwords( str_replace( '_', ' ', $part ) ) . ' Size (767px)', 'type' => 'text', 'instructions' => 'Optional bounded dimension or Site Text size token.' );
	}
	foreach ( array( 'step_background', 'step_text', 'muted_background', 'muted_text', 'step_number', 'muted_number', 'intro', 'detail_heading', 'detail_heading_open', 'answer', 'divider' ) as $part ) {
		$field = mrn_base_stack_get_background_color_field( $seed . '_process_' . $part . '_color' ); $field['name'] = 'process_' . $part . '_color'; $field['label'] = ucwords( str_replace( '_', ' ', $part ) ) . ' Color'; $fields[] = $field;
	}
	$fields[] = array( 'key' => $seed . '_process_aside_sizing', 'name' => 'process_aside_sizing', 'label' => 'Introduction Column Sizing', 'type' => 'select', 'choices' => array( '' => 'Use Introduction Width', 'proportional' => 'Share Space Remaining After Gap' ), 'default_value' => '', 'instructions' => 'Proportional sizing divides the available content width after the column gap; the existing introduction width is used otherwise.' );
	foreach ( array( 'aside_share' => 'Introduction Share (%)', 'aside_share_tablet' => 'Introduction Share at 1199px (%)' ) as $name => $label ) { $fields[] = array( 'key' => $seed . '_process_' . $name, 'name' => 'process_' . $name, 'label' => $label, 'type' => 'number', 'min' => 1, 'max' => 99, 'step' => 1, 'default_value' => 'aside_share' === $name ? 25 : '', 'instructions' => 'Used only with proportional sizing. Whole percentage of the space remaining after the gap. Blank tablet share retains the desktop share.' ); }
	$fields[] = array( 'key' => $seed . '_process_columns_breakpoint', 'name' => 'process_columns_breakpoint', 'label' => 'Stack Introduction Above Details', 'type' => 'select', 'choices' => array( '' => 'At 991px and Below', '767' => 'At 767px and Below' ), 'default_value' => '', 'instructions' => 'The introduction becomes static and its trailing divider is hidden when columns stack.' );
	$fields[] = array( 'key' => $seed . '_process_aside_divider', 'name' => 'process_aside_divider', 'label' => 'Introduction Divider', 'type' => 'select', 'choices' => array( '' => 'No Divider', 'trailing' => 'Trailing Line Below Introduction' ), 'default_value' => '', 'instructions' => 'Decorative line follows the sticky introduction and ends at the details container boundary. Uses the existing Divider Color and native gap/inset fields.' );
	$fields[] = array( 'key' => $seed . '_process_list_columns', 'name' => 'process_list_columns', 'label' => 'Detail List Columns', 'type' => 'select', 'choices' => array( '1' => 'One Column', '2' => 'Two Columns; One at 767px' ), 'default_value' => '1' );
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
	if ( '767' === ( $row['process_columns_breakpoint'] ?? '' ) ) { $out['classes'][] = 'has-process-columns-767'; }
	if ( 'trailing' === ( $row['process_aside_divider'] ?? '' ) ) { $out['classes'][] = 'has-process-aside-divider'; }
	if ( 'none' === ( $row['process_toggle_icon'] ?? '' ) ) { $out['classes'][] = 'has-process-no-icon'; }
	if ( in_array( $row['process_list_columns'] ?? '', array( 2, '2' ), true ) ) { $out['classes'][] = 'has-process-list-columns'; }
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

	foreach ( mrn_base_stack_get_process_card_fields( 'field_mrn_process_styles' ) as $field ) {
		$name = $field['name'];
		if ( in_array( $name, array( 'process_number_start', 'process_activation_ratio', 'process_mobile_expand_all', 'process_toggle_icon', 'process_list_columns', 'process_list_style', 'process_aside_sizing', 'process_aside_share', 'process_aside_share_tablet', 'process_columns_breakpoint', 'process_aside_divider' ), true ) ) { continue; }
		$value = str_ends_with( $name, '_color' ) ? mrn_base_stack_get_frame_palette_color( $row[ $name ] ?? '' ) : mrn_base_stack_get_content_frame_dimension( $row[ $name ] ?? '', $name );
		if ( '' !== $value ) { $styles[] = '--mrn-' . str_replace( '_', '-', $name ) . ':' . $value; }
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
