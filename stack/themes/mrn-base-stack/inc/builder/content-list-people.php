<?php
/** Optional editable framing for native People collections. */

/** Share the same field list between Classic Editor registration and output. */
function mrn_base_stack_get_people_frame_dimensions() {
	return array( 'content_width', 'inner_gutter', 'inner_gutter_compact', 'inner_gutter_mobile', 'heading_width', 'heading_width_compact', 'frame_gap', 'frame_gap_tablet', 'column_gap', 'column_gap_mobile', 'row_gap', 'row_gap_tablet', 'row_gap_mobile', 'media_space_after', 'media_space_after_mobile', 'heading_size', 'heading_line_height', 'heading_size_tablet', 'heading_size_mobile', 'name_size', 'name_line_height', 'name_size_tablet', 'name_line_height_tablet', 'name_space_after', 'designation_size', 'designation_line_height' );
}

/** Existing collections retain their frame unless an editor selects this one. */
function mrn_base_stack_get_people_frame_fields( $seed ) {
	$make = static function ( $name, $type, array $extra = array() ) use ( $seed ) {
		return array_merge( array( 'key' => $seed . '_people_' . $name, 'name' => 'people_' . $name, 'label' => 'People ' . ucwords( str_replace( '_', ' ', $name ) ), 'type' => $type, 'default_value' => '' ), $extra );
	};
	$fields = array(
		$make( 'frame', 'select', array( 'choices' => array( '' => 'Use Existing Frame', 'aside' => 'Heading Beside Collection', 'stacked' => 'Heading Above Collection' ), 'instructions' => 'Optional collection framing. Sources, selected records and order stay in Content Lists. Outer spacing stays in row presets.' ) ),
		$make( 'grid_breakpoints', 'select', array( 'choices' => array( '999-599' => '3 / 2 / 1 at 999px / 599px', '640-420' => '3 / 2 / 1 at 640px / 420px' ), 'default_value' => '999-599' ) ),
		$make( 'aside_breakpoint', 'select', array( 'choices' => array( '991' => 'Stack Heading at 991px', '767' => 'Stack Heading at 767px' ), 'default_value' => '991' ) ),
		$make( 'image_size', 'select', array( 'choices' => array( '' => 'Use Existing Image Size', 'medium_large' => 'Medium Large', 'large' => 'Large', 'full' => 'Full' ), 'instructions' => 'Keep the existing attachment and WordPress responsive-image attributes while choosing its image size.' ) ),
		$make( 'media_ratio', 'text', array( 'instructions' => 'Optional positive width/height ratio, for example 261/306.' ) ),
		$make( 'media_background_color', 'color_picker', array( 'instructions' => 'Optional opaque background behind portraits and empty media. Does not recolor images.' ) ),
	);
	foreach ( mrn_base_stack_get_people_frame_dimensions() as $name ) {
		$fields[] = $make( $name, 'text', array( 'instructions' => 'Optional non-negative length or bounded clamp(). Type controls also accept Site Text tokens. Compact gutters/aside width apply at 1199px, medium type/gaps at 991px, and mobile controls at the selected grid breakpoint.', 'wrapper' => array( 'width' => '50' ) ) );
	}
	foreach ( array( 'heading', 'name', 'designation' ) as $part ) {
		$fields[] = $make( $part . '_font', 'select', array( 'choices' => array( '' => 'Use Existing Font', 'heading' => 'Site Heading Font', 'body' => 'Site Body Font' ) ) );
		$fields[] = $make( $part . '_weight', 'select', array( 'choices' => array( '' => 'Use Existing Weight' ) + array_combine( range( 100, 900, 100 ), range( 100, 900, 100 ) ) ) );
		$fields[] = $make( $part . '_color', 'select', array( 'choices' => function_exists( 'mrn_rbl_get_site_color_choices' ) ? mrn_rbl_get_site_color_choices() : array(), 'allow_null' => 1, 'ui' => 1 ) );
	}
	foreach ( $fields as &$field ) {
		$field['conditional_logic'] = array( array( array( 'field' => $seed . '_record_presentation', 'operator' => '==', 'value' => 'people' ) ) );
		if ( 'people_frame' !== $field['name'] ) { $field['conditional_logic'][0][] = array( 'field' => $seed . '_people_frame', 'operator' => '!=', 'value' => '' ); }
	}
	unset( $field );
	return $fields;
}

/** Reuse bounded frame parsers and admit the existing fourth Site Body token. */
function mrn_base_stack_get_people_frame_dimension( $value, $name ) {
	$base = preg_replace( '/_(?:tablet|mobile)$/D', '', $name );
	$kind = str_ends_with( $base, 'line_height' ) ? 'line' : ( str_ends_with( $base, '_size' ) ? 'size' : '' );
	if ( $kind && is_string( $value ) && 'var(--site-text-body-4-' . $kind . ')' === $value ) { return $value; }
	return mrn_base_stack_get_content_frame_dimension( $value, $name );
}

/** Only known frames and bounded values may produce collection CSS variables. */
function mrn_base_stack_get_people_frame_contract( array $row ) {
	$out = array( 'classes' => array(), 'attributes' => array() );
	if ( 'people' !== ( $row['record_presentation'] ?? '' ) || ! in_array( $row['people_frame'] ?? '', array( 'aside', 'stacked' ), true ) ) { return $out; }
	$out['classes'][] = 'has-people-frame';
	if ( 'aside' === $row['people_frame'] ) { $out['classes'][] = 'has-people-aside'; }
	$out['classes'][] = 'people-aside-at-' . ( '767' === ( $row['people_aside_breakpoint'] ?? '' ) ? '767' : '991' );
	$out['classes'][] = 'people-grid-' . ( '640-420' === ( $row['people_grid_breakpoints'] ?? '' ) ? '640-420' : '999-599' );
	$styles = array();
	foreach ( mrn_base_stack_get_people_frame_dimensions() as $name ) {
		$value = mrn_base_stack_get_people_frame_dimension( $row[ 'people_' . $name ] ?? '', $name );
		if ( '' !== $value ) { $styles[] = '--mrn-people-' . str_replace( '_', '-', $name ) . ':' . $value; }
	}
	$ratio = $row['people_media_ratio'] ?? '';
	if ( is_string( $ratio ) && preg_match( '/^([0-9]{1,4}(?:\.[0-9]{1,4})?)\s*\/\s*([0-9]{1,4}(?:\.[0-9]{1,4})?)$/D', $ratio, $parts ) && (float) $parts[1] > 0 && (float) $parts[2] > 0 ) { $styles[] = '--mrn-people-media-ratio:' . $parts[1] . '/' . $parts[2]; }
	$background = mrn_base_stack_get_record_color( $row['people_media_background_color'] ?? '' );
	if ( '' !== $background ) { $styles[] = '--mrn-people-media-background-color:' . $background; }
	foreach ( array( 'heading', 'name', 'designation' ) as $part ) {
		$font = $row[ 'people_' . $part . '_font' ] ?? '';
		if ( in_array( $font, array( 'heading', 'body' ), true ) ) { $styles[] = '--mrn-people-' . $part . '-font:var(--site-font-' . $font . ')'; }
		$weight = $row[ 'people_' . $part . '_weight' ] ?? '';
		if ( is_scalar( $weight ) && in_array( (string) $weight, array_map( 'strval', range( 100, 900, 100 ) ), true ) ) { $styles[] = '--mrn-people-' . $part . '-weight:' . $weight; }
		$color = mrn_base_stack_get_frame_palette_color( $row[ 'people_' . $part . '_color' ] ?? '' );
		if ( '' !== $color ) { $styles[] = '--mrn-people-' . $part . '-color:' . $color; }
	}
	if ( $styles ) { $out['attributes']['style'] = implode( ';', $styles ); }
	return $out;
}

/** A selected frame may choose a core image size; older rows keep their size. */
function mrn_base_stack_get_people_image_size( array $row ) {
	return 'people' === ( $row['record_presentation'] ?? '' ) && in_array( $row['people_frame'] ?? '', array( 'aside', 'stacked' ), true ) && in_array( $row['people_image_size'] ?? '', array( 'medium_large', 'large', 'full' ), true ) ? $row['people_image_size'] : 'mrn-content-media';
}
