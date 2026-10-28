<?php
/** Opt-in responsive frame controls, safe dimensions and unchanged dormant defaults. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || home_url() !== getenv( 'MRN_STACK_TEST_HOME' ) || ! str_ends_with( (string) wp_parse_url( home_url(), PHP_URL_HOST ), '.localhost' ) || ! current_user_can( 'manage_options' ) ) { throw new RuntimeException( 'An explicitly selected local administrator runtime is required.' ); }
$checks = array(); $expect = static function ( $ok, $label ) use ( &$checks ) { if ( ! $ok ) { throw new RuntimeException( esc_html( $label ) ); } $checks[] = $label; };
foreach ( array( 'body_text' => array( 'body_inner_gutter_compact', 'body_heading_width', 'body_heading_space_before_mobile' ), 'image_content' => array( 'image_inner_gutter_compact' ) ) as $layout => $names ) {
	$fields = array_column( mrn_base_stack_get_content_frame_fields( $layout, 'field_mrn_proof_first' ), null, 'name' ); $prefix = mrn_base_stack_get_content_frame_config( $layout )['prefix'];
	$expect( '' === $fields[ $prefix . '_frame' ]['default_value'], 'Existing default remains dormant: ' . $layout );
	$first = array_column( $fields, 'key' ); $second = array_column( mrn_base_stack_get_content_frame_fields( $layout, 'field_mrn_proof_second' ), 'key' );
	$expect( count( $first ) === count( array_unique( $first ) ) && ! array_intersect( $first, $second ), 'Root and nested field keys are isolated: ' . $layout );
	foreach ( $names as $name ) {
		$expect( isset( $fields[ $name ]['conditional_logic'] ), 'Native conditional control exists: ' . $name );
		foreach ( array( '0px', '15px', 'clamp(15px, 2vw, 30px)' ) as $value ) { $row = array( $prefix . '_frame' => 'contained', $name => $value ); $style = mrn_base_stack_get_content_frame_contract( $row, $layout )['attributes']['style']; $expect( str_contains( $style, ':' . $value ), 'Valid bounded dimension: ' . $name . '/' . $value ); }
		foreach ( array( '-1px', '15px;color:red', 'var(--unknown)', array( '15px' ) ) as $value ) { $row = array( $prefix . '_frame' => 'contained', $name => $value ); $contract = mrn_base_stack_get_content_frame_contract( $row, $layout ); $expect( empty( $contract['attributes'] ), 'Invalid dimension cannot emit CSS: ' . $name ); }
		$expect( array( 'classes' => array(), 'attributes' => array() ) === mrn_base_stack_get_content_frame_contract( array( $name => '15px' ), $layout ), 'Unselected frame ignores dormant input: ' . $name );
	}
}
$image_fields = array_column( mrn_base_stack_get_content_frame_fields( 'image_content', 'field_mrn_natural_proof' ), null, 'name' );
$expect( '' === $image_fields['image_media_sizing']['default_value'] && isset( $image_fields['image_media_sizing']['choices']['natural'] ), 'Original proportions are optional and editable' );
foreach ( array( 'standard', 'scroll-drift' ) as $presentation ) { $contract = mrn_base_stack_get_content_frame_contract( array( 'image_frame' => 'contained', 'image_media_sizing' => 'natural', 'image_presentation' => $presentation ), 'image_content' ); $expect( in_array( 'has-image-natural-media', $contract['classes'], true ), 'Natural sizing supports ' . $presentation ); }
foreach ( array( '', 'scroll-zoom', 'tilted-gallery', 'unknown' ) as $presentation ) { $contract = mrn_base_stack_get_content_frame_contract( array( 'image_frame' => '' === $presentation ? '' : 'contained', 'image_media_sizing' => 'natural', 'image_presentation' => $presentation ), 'image_content' ); $expect( ! in_array( 'has-image-natural-media', $contract['classes'], true ), 'Dormant or fixed-height stage keeps existing sizing: ' . $presentation ); }
WP_CLI::line( wp_json_encode( array( 'result' => 'passed', 'count' => count( $checks ), 'checks' => $checks, 'candidate_only' => true ), JSON_PRETTY_PRINT ) );
