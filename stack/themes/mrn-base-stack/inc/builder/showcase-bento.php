<?php
/** Optional image footprints and responsive geometry for native Showcase. */
function mrn_base_stack_get_showcase_bento_fields( $seed ) {
	$fields = array();
	return $fields;
}

function mrn_base_stack_get_showcase_bento_contract( array $row ) {
	$result = array( 'classes' => array(), 'attributes' => array() );
	if ( 'bento' !== ( $row['showcase_presentation'] ?? '' ) ) { return $result; }
	$result['classes'][] = 'has-bento-showcase'; $styles = array();
	foreach ( array( 'content_width', 'inner_gutter', 'gap', 'gap_tablet', 'gap_medium', 'gap_mobile', 'row_height', 'row_height_tablet', 'row_height_medium' ) as $part ) {
		$value = mrn_base_stack_validate_card_frame_dimension( $row[ 'bento_' . $part ] ?? '', 'content_width' === $part );
		if ( '' !== $value ) { $styles[] = '--mrn-bento-' . str_replace( '_', '-', $part ) . ':' . $value; }
	}
	$ratios = array( '4-3' => '4/3', '1-1' => '1/1', '16-9' => '16/9' );
	if ( is_string( $row['bento_mobile_ratio'] ?? null ) && isset( $ratios[ $row['bento_mobile_ratio'] ] ) ) { $styles[] = '--mrn-bento-mobile-ratio:' . $ratios[ $row['bento_mobile_ratio'] ]; }
	if ( in_array( $row['bento_hover_scale'] ?? '', array( '1.02', '1.05' ), true ) ) { $styles[] = '--mrn-bento-hover-scale:' . $row['bento_hover_scale']; }
	if ( $styles ) { $result['attributes']['style'] = implode( ';', $styles ); }
	return $result;
}

/** Invalid renderer input is harmless; migration rejects populated invalid spans. */
function mrn_base_stack_get_showcase_span( $value ) {
	return in_array( $value, array( 1, 2, 3, '1', '2', '3' ), true ) ? (int) $value : 1;
}
