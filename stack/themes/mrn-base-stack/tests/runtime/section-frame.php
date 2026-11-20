<?php
/** Theme-registered Section Frames in the Spacing tab: field and row class appear only when a theme registers frames; read-only. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || home_url() !== getenv( 'MRN_STACK_TEST_HOME' ) || ! str_ends_with( (string) wp_parse_url( home_url(), PHP_URL_HOST ), '.localhost' ) || ! current_user_can( 'manage_options' ) ) {
	throw new RuntimeException( 'An explicitly selected local administrator runtime is required.' );
}
$checks = array();
$expect = static function ( $condition, $message ) use ( &$checks ) {
	if ( ! $condition ) { throw new RuntimeException( esc_html( $message ) ); }
	$checks[] = $message;
};
$frame_field = static function ( $seed ) {
	$found = array();
	foreach ( mrn_base_stack_ensure_row_spacing_preset_field( mrn_base_stack_ensure_row_spacing_preset_field( array(), $seed ), $seed ) as $field ) {
		if ( 'section_frame' === ( $field['name'] ?? '' ) ) { $found[] = $field; }
	}
	return 1 === count( $found ) ? $found[0] : ( $found ? 'duplicate' : null );
};
$classes_for = static function ( $frame ) {
	return implode( ' ', mrn_base_stack_get_builder_row_spacing_contract( array( 'row_spacing_margin_top_preset' => 'Small', 'section_frame' => $frame ) )['classes'] ?? array() );
};
$registered = has_filter( 'mrn_base_stack_section_frames' );
remove_all_filters( 'mrn_base_stack_section_frames' );
$expect( array() === mrn_base_stack_get_section_frames(), 'No frames unless a theme registers them' );
$expect( null === $frame_field( 'field_mrn_test_cta' ), 'No Section Frame field without registered frames' );
$expect( ! str_contains( $classes_for( 'band' ), 'mrn-section-frame--' ), 'A stored frame adds no class without a registration' );
$fixture = static function () { return array( 'band' => 'Band', 'Bad Key!' => 'Bad', 'empty' => '', 'array' => array( 'x' ) ); };
add_filter( 'mrn_base_stack_section_frames', $fixture );
$frames = mrn_base_stack_get_section_frames();
$expect( array( 'band' => 'Band', 'badkey' => 'Bad' ) === $frames, 'Frame keys are sanitized and empty or non-scalar labels dropped' );
$field = $frame_field( 'field_mrn_test_cta' );
$expect( is_array( $field ) && array( '' => 'None', 'band' => 'Band', 'badkey' => 'Bad' ) === $field['choices'] && '' === $field['default_value'], 'The Spacing tab offers one Section Frame field: None plus the registered frames' );
$expect( str_contains( $classes_for( 'band' ), 'mrn-section-frame--band' ), 'A registered frame adds its row class through the spacing contract' );
$expect( ! str_contains( $classes_for( 'nope" onclick="x' ), 'mrn-section-frame--' ), 'An unregistered value adds no class' );
$expect( ! str_contains( $classes_for( '' ), 'mrn-section-frame--' ), 'No frame, no class' );
remove_filter( 'mrn_base_stack_section_frames', $fixture );
WP_CLI::line( wp_json_encode( array( 'checks' => $checks, 'count' => count( $checks ), 'result' => 'passed', 'theme_registered_frames' => (bool) $registered, 'source_version' => _S_VERSION ), JSON_PRETTY_PRINT ) );
