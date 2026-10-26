<?php
/** Optional labeled logo relationships and editable responsive framing. */
function mrn_base_stack_get_labeled_logos_fields( $seed ) {
	$fields = array(
		array( 'key' => $seed . '_logos_presentation', 'name' => 'logos_presentation', 'label' => 'Logo Presentation', 'type' => 'select', 'choices' => array( '' => 'Use Existing Grid or Slider', 'labeled' => 'Labeled Logos', 'icons' => 'Icon Strip', 'auto-grid' => 'Adaptive Logo Grid' ), 'default_value' => '', 'instructions' => 'Labeled Logos keeps captions beside images. Icon Strip preserves a fixed-size row of optional linked icons. Adaptive Logo Grid fills the available width using a minimum column size. These presentations do not use a slider.' ),
		array( 'key' => $seed . '_logos_description', 'name' => 'logos_description', 'label' => 'Description After Logos', 'type' => 'wysiwyg', 'tabs' => 'all', 'toolbar' => 'full', 'media_upload' => 0 ),
	);
	$frame = mrn_base_stack_get_content_frame_fields( 'logos', $seed ); array_shift( $frame );
	$fields = array_merge( $fields, $frame );
	$grid_frame_fields = array( 'logos_content_width', 'logos_inner_gutter', 'logos_inner_gutter_compact', 'logos_heading_width', 'logos_heading_size', 'logos_heading_line_height', 'logos_heading_weight', 'logos_heading_color', 'logos_heading_size_tablet', 'logos_heading_size_mobile', 'logos_heading_line_height_mobile', 'logos_section_gap', 'logos_section_gap_tablet', 'logos_section_gap_mobile' );
	foreach ( $fields as &$field ) {
		if ( 'logos_presentation' === $field['name'] ) { continue; }
		$field['conditional_logic'] = array( array( array( 'field' => $seed . '_logos_presentation', 'operator' => '==', 'value' => 'labeled' ) ) );
		if ( in_array( $field['name'], $grid_frame_fields, true ) ) { $field['conditional_logic'][] = array( array( 'field' => $seed . '_logos_presentation', 'operator' => '==', 'value' => 'auto-grid' ) ); }
		if ( 'logos_inner_gutter_compact' === $field['name'] ) { $field['instructions'] = 'Optional inner horizontal gutter at 1199px and below. Outer section padding stays in row presets.'; }
		elseif ( str_ends_with( $field['name'], '_tablet' ) ) { $field['instructions'] = 'Optional override at 1024px and below; section gap and heading size apply at 900px.'; }
		elseif ( str_ends_with( $field['name'], '_medium' ) ) { $field['instructions'] = 'Optional override at 900px and below.'; }
		elseif ( str_ends_with( $field['name'], '_mobile' ) ) { $field['instructions'] = 'Optional override at 560px and below. Logos stack vertically at 640px.'; }
	}
	unset( $field );
	foreach ( array( 'size' => 'Icon Size', 'gap' => 'Space Between Icons', 'space_before' => 'Space Before Icon Strip' ) as $name => $label ) {
		$fields[] = array( 'key' => $seed . '_logos_icon_' . $name, 'name' => 'logos_icon_' . $name, 'label' => $label, 'type' => 'text', 'instructions' => 'Optional non-negative CSS length. Outer section spacing stays in row presets.', 'conditional_logic' => array( array( array( 'field' => $seed . '_logos_presentation', 'operator' => '==', 'value' => 'icons' ) ) ) );
	}
	foreach ( array( 'min_width' => 'Minimum Logo Column Width', 'column_gap' => 'Space Between Logo Columns', 'row_gap' => 'Space Between Logo Rows', 'image_max_width' => 'Maximum Logo Image Width' ) as $name => $label ) {
		$fields[] = array( 'key' => $seed . '_logos_grid_' . $name, 'name' => 'logos_grid_' . $name, 'label' => $label, 'type' => 'text', 'instructions' => 'Optional non-negative CSS length. Outer section spacing stays in row presets.', 'conditional_logic' => array( array( array( 'field' => $seed . '_logos_presentation', 'operator' => '==', 'value' => 'auto-grid' ) ) ) );
	}
	$fields[] = array( 'key' => $seed . '_logos_heading_font', 'name' => 'logos_heading_font', 'label' => 'Logo Heading Font', 'type' => 'select', 'choices' => array( '' => 'Use Existing Font', 'body' => 'Site Body Font', 'heading' => 'Site Heading Font' ), 'default_value' => '', 'conditional_logic' => array( array( array( 'field' => $seed . '_logos_presentation', 'operator' => '==', 'value' => 'auto-grid' ) ) ) );
	return $fields;
}

function mrn_base_stack_get_labeled_logos_contract( array $row ) {
	if ( 'auto-grid' === ( $row['logos_presentation'] ?? '' ) ) {
		$contract = mrn_base_stack_get_content_frame_contract( array_merge( $row, array( 'logos_frame' => 'contained' ) ), 'logos' );
		$contract['classes'][] = 'has-auto-grid-logos'; $styles = array();
		foreach ( array( 'min_width', 'column_gap', 'row_gap', 'image_max_width' ) as $name ) {
			$value = mrn_base_stack_validate_hero_frame_dimension( $row[ 'logos_grid_' . $name ] ?? '' );
			if ( '' !== $value ) { $styles[] = '--mrn-logos-grid-' . str_replace( '_', '-', $name ) . ':' . $value; }
		}
		if ( in_array( $row['logos_heading_font'] ?? '', array( 'body', 'heading' ), true ) ) { $contract['classes'][] = 'has-logos-heading-font-' . $row['logos_heading_font']; }
		if ( $styles ) { $contract['attributes']['style'] = trim( ( $contract['attributes']['style'] ?? '' ) . ';' . implode( ';', $styles ), ';' ); }
		return $contract;
	}
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
