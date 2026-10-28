<?php
/** Optional native Showcase records with image, rich copy, tags and an action. */
function mrn_base_stack_get_showcase_record_item_fields( $seed, $presentation_key ) {
	$tags = array_values( array_filter( mrn_base_stack_get_text_tag_fields( $seed ), static function ( $field ) { return 'text_tags' === $field['name']; } ) )[0];
	$tags['max'] = 30;
	$fields = array(
		array( 'key' => $seed . '_record_heading', 'name' => 'record_heading', 'label' => 'Record Heading', 'type' => 'text' ),
		mrn_base_stack_get_text_tag_field( $seed . '_record_heading_tag', 'record_heading_tag', 'h3', 'Record Heading Tag' ),
		array( 'key' => $seed . '_record_content', 'name' => 'record_content', 'label' => 'Record Text', 'type' => 'wysiwyg', 'tabs' => 'all', 'toolbar' => 'full', 'media_upload' => 0 ),
		$tags,
	);
	foreach ( $fields as &$field ) { $field['conditional_logic'] = array( array( array( 'field' => $presentation_key, 'operator' => '==', 'value' => 'split-records' ) ) ); }
	unset( $field );
	return $fields;
}

/** Collection geometry is editable; outer section margin/padding stays in row presets. */
function mrn_base_stack_get_showcase_record_fields( $seed ) {
	$fields = array();
	foreach ( array( 'content_width', 'gutter', 'gutter_tablet', 'gutter_mobile', 'first_column_width', 'first_column_width_compact', 'column_gap', 'column_gap_compact', 'column_gap_tablet', 'column_gap_mobile', 'item_padding', 'item_padding_tablet', 'item_padding_mobile', 'content_gap', 'content_gap_tablet', 'content_gap_mobile', 'title_size', 'title_line_height', 'title_size_tablet', 'title_line_height_tablet', 'title_size_mobile', 'title_line_height_mobile', 'text_size', 'text_line_height', 'text_size_tablet', 'text_line_height_tablet', 'text_size_mobile', 'text_line_height_mobile', 'tag_gap_mobile', 'tag_font_size_mobile', 'tag_line_height_mobile', 'tag_padding_inline_mobile', 'tag_padding_block_mobile', 'button_size', 'button_line_height' ) as $part ) {
		$fields[] = array( 'key' => $seed . '_records_' . $part, 'name' => 'records_' . $part, 'label' => 'Records ' . ucwords( str_replace( '_', ' ', $part ) ), 'type' => 'text', 'instructions' => 'Optional bounded length, clamp() or Site Text size/line token. Compact: 1199px; stacked/tablet: 991px; mobile: 600px. Section spacing uses row presets.' );
	}
	foreach ( array( 'title', 'text', 'divider', 'button', 'button_hover', 'button_text' ) as $part ) { $field = mrn_base_stack_get_background_color_field( $seed . '_records_' . $part . '_color' ); $field['name'] = 'records_' . $part . '_color'; $field['label'] = 'Records ' . ucwords( str_replace( '_', ' ', $part ) ) . ' Color'; $fields[] = $field; }
	foreach ( mrn_base_stack_get_text_tag_fields( $seed . '_record_tags_style' ) as $field ) { if ( str_starts_with( $field['name'], 'tag_' ) || 'list_presentation' === $field['name'] ) { $fields[] = $field; } }
	foreach ( $fields as &$field ) { $field['conditional_logic'] = array( array( array( 'field' => $seed . '_showcase_presentation', 'operator' => '==', 'value' => 'split-records' ) ) ); }
	unset( $field );
	return $fields;
}

/** Unselected or malformed values cannot add styles to an existing presentation. */
function mrn_base_stack_get_showcase_record_contract( array $row ) {
	$result = array( 'classes' => array(), 'attributes' => array() );
	if ( 'split-records' !== ( $row['showcase_presentation'] ?? '' ) ) { return $result; }
	$result['classes'][] = 'has-showcase-records'; $styles = array();
	foreach ( mrn_base_stack_get_showcase_record_fields( 'field_mrn_record_styles' ) as $field ) {
		$name = $field['name']; if ( ! str_starts_with( $name, 'records_' ) ) { continue; }
		$raw = $row[ $name ] ?? '';
		$value = str_ends_with( $name, '_color' ) ? mrn_base_stack_get_frame_palette_color( $raw ) : mrn_base_stack_get_content_frame_dimension( $raw, $name );
		if ( '' !== $value ) { $styles[] = '--mrn-' . str_replace( '_', '-', $name ) . ':' . $value; }
	}
	$background = mrn_base_stack_get_frame_palette_color( $row['background_color'] ?? '' ); if ( '' !== $background ) { $styles[] = '--mrn-showcase-row-bg:' . $background; }
	$result['attributes']['style'] = implode( ';', $styles );
	return $result;
}
