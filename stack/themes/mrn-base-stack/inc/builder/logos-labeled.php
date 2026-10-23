<?php
/** Optional labeled logo relationships and editable responsive framing. */
function mrn_base_stack_get_labeled_logos_fields( $seed ) {
	$fields = array(
		array( 'key' => $seed . '_logos_presentation', 'name' => 'logos_presentation', 'label' => 'Logo Presentation', 'type' => 'select', 'choices' => array( '' => 'Use Existing Grid or Slider', 'labeled' => 'Labeled Logos', 'icons' => 'Icon Strip' ), 'default_value' => '', 'instructions' => 'Labeled Logos keeps captions beside images. Icon Strip preserves a fixed-size row of optional linked icons. These presentations do not use a slider.' ),
		array( 'key' => $seed . '_logos_description', 'name' => 'logos_description', 'label' => 'Description After Logos', 'type' => 'wysiwyg', 'tabs' => 'all', 'toolbar' => 'full', 'media_upload' => 0 ),
	);
	$frame = mrn_base_stack_get_content_frame_fields( 'logos', $seed ); array_shift( $frame );
	$fields = array_merge( $fields, $frame );
	foreach ( $fields as &$field ) {
		if ( 'logos_presentation' === $field['name'] ) { continue; }
		$field['conditional_logic'] = array( array( array( 'field' => $seed . '_logos_presentation', 'operator' => '==', 'value' => 'labeled' ) ) );
		if ( 'logos_inner_gutter_compact' === $field['name'] ) { $field['instructions'] = 'Optional inner horizontal gutter at 1199px and below. Outer section padding stays in row presets.'; }
		elseif ( str_ends_with( $field['name'], '_tablet' ) ) { $field['instructions'] = 'Optional override at 1024px and below; section gap and heading size apply at 900px.'; }
		elseif ( str_ends_with( $field['name'], '_medium' ) ) { $field['instructions'] = 'Optional override at 900px and below.'; }
		elseif ( str_ends_with( $field['name'], '_mobile' ) ) { $field['instructions'] = 'Optional override at 560px and below. Logos stack vertically at 640px.'; }
	}
	unset( $field );
	foreach ( array( 'size' => 'Icon Size', 'gap' => 'Space Between Icons', 'space_before' => 'Space Before Icon Strip' ) as $name => $label ) {
		$fields[] = array( 'key' => $seed . '_logos_icon_' . $name, 'name' => 'logos_icon_' . $name, 'label' => $label, 'type' => 'text', 'instructions' => 'Optional non-negative CSS length. Outer section spacing stays in row presets.', 'conditional_logic' => array( array( array( 'field' => $seed . '_logos_presentation', 'operator' => '==', 'value' => 'icons' ) ) ) );
	}
	return $fields;
}

function mrn_base_stack_get_labeled_logos_contract( array $row ) {
	if ( 'icons' === ( $row['logos_presentation'] ?? '' ) ) {
		$styles = array();
		foreach ( array( 'size', 'gap', 'space_before' ) as $name ) {
			$value = mrn_base_stack_validate_hero_frame_dimension( $row[ 'logos_icon_' . $name ] ?? '' );
			if ( '' !== $value ) { $styles[] = '--mrn-logos-icon-' . str_replace( '_', '-', $name ) . ':' . $value; }
		}
		return array( 'classes' => array( 'has-icon-logos' ), 'attributes' => $styles ? array( 'style' => implode( ';', $styles ) ) : array() );
	}
	if ( 'labeled' !== ( $row['logos_presentation'] ?? '' ) ) { return array( 'classes' => array(), 'attributes' => array() ); }
	$contract = mrn_base_stack_get_content_frame_contract( array_merge( $row, array( 'logos_frame' => 'contained' ) ), 'logos' );
	$contract['classes'][] = 'has-labeled-logos'; return $contract;
}
