<?php
/** Optional structured pricing presentation for native Cards. */

/** Per-plan content stays in ACF; no price, plan or feature copy lives in the theme. */
function mrn_base_stack_get_pricing_item_fields( $seed, $presentation_key ) {
	$fields = array(
		array( 'key' => $seed . '_pricing_badge', 'name' => 'pricing_badge', 'label' => 'Price or Badge', 'type' => 'text' ),
		array( 'key' => $seed . '_pricing_description', 'name' => 'pricing_description', 'label' => 'Plan Description', 'type' => 'textarea', 'rows' => 3, 'new_lines' => '' ),
		array( 'key' => $seed . '_pricing_highlight', 'name' => 'pricing_highlight', 'label' => 'Highlight Plan', 'type' => 'true_false', 'ui' => 1, 'default_value' => 0 ),
		array( 'key' => $seed . '_pricing_features', 'name' => 'pricing_features', 'label' => 'Plan Features', 'type' => 'repeater', 'layout' => 'table', 'min' => 0, 'max' => 30, 'button_label' => 'Add Feature', 'sub_fields' => array( array( 'key' => $seed . '_pricing_feature_text', 'name' => 'text', 'label' => 'Feature', 'type' => 'text', 'required' => 1 ) ) ),
		array( 'key' => $seed . '_pricing_action', 'name' => 'pricing_action', 'label' => 'Plan Action', 'type' => 'link', 'return_format' => 'array', 'instructions' => 'Optional independent action button. Enter its visible label and destination.' ),
	);
	foreach ( $fields as &$field ) { $field['conditional_logic'] = array( array( array( 'field' => $presentation_key, 'operator' => '==', 'value' => 'pricing' ) ) ); }
	unset( $field );
	return $fields;
}

/** Optional bounded presentation controls; outer section spacing uses row presets. */
function mrn_base_stack_get_pricing_fields( $seed ) {
	$fields = array();
	foreach ( $fields as &$field ) { $field['conditional_logic'] = array( array( array( 'field' => $seed . '_card_presentation', 'operator' => '==', 'value' => 'pricing' ) ) ); }
	unset( $field );
	return $fields;
}

/** Selected values only; unknown palette slugs and arbitrary CSS cannot emit styles. */
function mrn_base_stack_get_pricing_styles( array $row ) {
	if ( 'pricing' !== ( $row['card_presentation'] ?? '' ) ) { return array(); }
	$styles = array();
	foreach ( mrn_base_stack_get_pricing_fields( 'field_mrn_pricing_styles' ) as $field ) {
		$name = $field['name']; $raw = $row[ $name ] ?? '';
		$value = str_ends_with( $name, '_color' ) ? mrn_base_stack_get_frame_palette_color( $raw ) : mrn_base_stack_get_content_frame_dimension( $raw, $name );
		if ( '' !== $value ) { $styles[] = '--mrn-' . str_replace( '_', '-', $name ) . ':' . $value; }
	}
	return $styles;
}
