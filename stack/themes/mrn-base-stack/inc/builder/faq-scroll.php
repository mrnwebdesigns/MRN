<?php
/** Optional native FAQ heading-aside and scroll-active presentation. */

/** Geometry remains editable in native fields; outer spacing uses row presets. */
function mrn_base_stack_get_faq_scroll_dimensions() {
	return array( 'heading_aside_width', 'heading_aside_width_compact', 'heading_sticky_top', 'content_width', 'inner_gutter', 'inner_gutter_compact', 'column_gap', 'column_gap_compact', 'column_gap_stacked', 'heading_size', 'heading_line_height', 'heading_size_tablet', 'heading_line_height_tablet', 'item_space_before', 'item_space_after', 'item_space_mobile', 'answer_space_before_mobile', 'answer_content_gap', 'answer_max_width', 'features_column_gap', 'features_row_gap' );
}

/** Default disclosure behavior is unchanged unless this presentation is selected. */
function mrn_base_stack_get_faq_scroll_fields( $seed ) {
	$make = static function ( $name, $type, array $extra = array() ) use ( $seed ) {
		return array_merge( array( 'key' => $seed . '_faq_' . $name, 'name' => 'faq_' . $name, 'label' => 'FAQ ' . ucwords( str_replace( '_', ' ', $name ) ), 'type' => $type, 'default_value' => '' ), $extra );
	};
	$fields = array(
		$make( 'presentation', 'select', array( 'choices' => array( '' => 'Use Existing Presentation', 'scroll-active' => 'Scroll-Active Answers with Heading Aside' ), 'instructions' => 'One answer follows scrolling on desktop. Native summaries remain keyboard accessible. Mobile/reduced-motion views start expanded and retain manual disclosure controls. Select Editorial Questions for additional question/answer typography controls.' ) ),
		$make( 'mobile_expand_all', 'true_false', array( 'ui' => 1, 'default_value' => 1, 'label' => 'Start All Answers Open at 767px and Below' ) ),
		$make( 'activation_ratio', 'number', array( 'default_value' => 0.45, 'min' => 0.1, 'max' => 0.9, 'step' => 0.05, 'instructions' => 'Fraction of the viewport height where the next answer becomes active.' ) ),
		$make( 'features_color', 'color_picker', array( 'instructions' => 'Optional opaque color for answer lists. Does not modify Site Colors.' ) ),
		$make( 'heading_letter_spacing', 'text', array( 'instructions' => 'Optional bounded letter spacing, including negative em/px values. Blank preserves the existing heading tracking.' ) ),
		$make( 'heading_weight', 'select', array( 'choices' => array( '' => 'Use Existing Weight' ) + array_combine( range( 100, 900, 100 ), range( 100, 900, 100 ) ) ) ),
	);
	$color = mrn_base_stack_get_background_color_field( $seed . '_faq_heading_color' );
	$color['name'] = 'faq_heading_color'; $color['label'] = 'FAQ Heading Color'; $fields[] = $color;
	foreach ( mrn_base_stack_get_faq_scroll_dimensions() as $name ) {
		if ( 'inner_gutter' === $name ) { continue; } // Reuse the existing editorial FAQ gutter field.
		$fields[] = $make( $name, 'text', array( 'instructions' => 'Optional non-negative length or bounded clamp(). Type controls accept Site Text tokens. Compact values apply at 1199px, stacked heading values at 991px, mobile values at 767px. Outer spacing stays in row presets.', 'wrapper' => array( 'width' => '50' ) ) );
	}
	foreach ( $fields as &$field ) {
		if ( 'faq_presentation' !== $field['name'] ) { $field['conditional_logic'] = array( array( array( 'field' => $seed . '_faq_presentation', 'operator' => '==', 'value' => 'scroll-active' ) ) ); }
		// Stacked editorial FAQ rows share the same heading typography controls.
		if ( in_array( $field['name'], array( 'faq_heading_size', 'faq_heading_line_height', 'faq_heading_weight', 'faq_heading_color', 'faq_column_gap' ), true ) ) { $field['conditional_logic'][] = array( array( 'field' => $seed . '_faq_frame', 'operator' => '==', 'value' => 'editorial' ) ); }
	}
	unset( $field );
	return $fields;
}

/** Validate every style and behavior value before producing wrapper attributes. */
function mrn_base_stack_get_faq_scroll_contract( array $row ) {
	$out = array( 'classes' => array(), 'attributes' => array() );
	if ( 'scroll-active' !== ( $row['faq_presentation'] ?? '' ) ) { return $out; }
	$out['classes'][] = 'has-faq-scroll-active';
	$ratio = $row['faq_activation_ratio'] ?? 0.45;
	$ratio = is_scalar( $ratio ) && is_numeric( $ratio ) && (float) $ratio >= 0.1 && (float) $ratio <= 0.9 ? (string) (float) $ratio : '0.45';
	$out['attributes']['data-mrn-faq-scroll'] = $ratio;
	$out['attributes']['data-mrn-faq-mobile-all'] = in_array( $row['faq_mobile_expand_all'] ?? 1, array( true, 1, '1' ), true ) ? 'true' : 'false';
	$styles = array();
	foreach ( mrn_base_stack_get_faq_scroll_dimensions() as $name ) {
		$value = mrn_base_stack_get_content_frame_dimension( $row[ 'faq_' . $name ] ?? '', $name );
		if ( '' !== $value ) { $styles[] = '--mrn-faq-scroll-' . str_replace( '_', '-', $name ) . ':' . $value; }
	}
	$color = mrn_base_stack_get_record_color( $row['faq_features_color'] ?? '' );
	if ( '' !== $color ) { $styles[] = '--mrn-faq-scroll-features-color:' . $color; }
	$color = mrn_base_stack_get_frame_palette_color( $row['faq_heading_color'] ?? '' );
	if ( '' !== $color ) { $styles[] = '--mrn-faq-scroll-heading-color:' . $color; }
	$tracking = mrn_base_stack_get_frame_letter_spacing( $row['faq_heading_letter_spacing'] ?? '' );
	if ( '' !== $tracking ) { $styles[] = '--mrn-faq-scroll-heading-letter-spacing:' . $tracking; }
	$weight = $row['faq_heading_weight'] ?? '';
	if ( is_scalar( $weight ) && in_array( (string) $weight, array_map( 'strval', range( 100, 900, 100 ) ), true ) ) { $styles[] = '--mrn-faq-scroll-heading-weight:' . $weight; }
	if ( $styles ) { $out['attributes']['style'] = implode( ';', $styles ); }
	return $out;
}

/** No-script fallback exposes all copy and keeps ordinary FAQ groups unchanged. */
function mrn_base_stack_prepare_faq_scroll_markup( $html ) {
	if ( ! class_exists( 'WP_HTML_Tag_Processor' ) ) { return $html; }
	$tags = new WP_HTML_Tag_Processor( $html );
	while ( $tags->next_tag( array( 'tag_name' => 'DETAILS', 'class_name' => 'mrn-faq__item' ) ) ) {
		$tags->set_attribute( 'open', true );
		$tags->remove_attribute( 'name' );
	}
	return $tags->get_updated_html();
}
