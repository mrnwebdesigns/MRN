<?php
/** Content Lists Section Look: a theme-registered row look separate from the item Display Style; read-only. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || home_url() !== getenv( 'MRN_STACK_TEST_HOME' ) || ! str_ends_with( (string) wp_parse_url( home_url(), PHP_URL_HOST ), '.localhost' ) || ! current_user_can( 'manage_options' ) ) {
	throw new RuntimeException( 'An explicitly selected local administrator runtime is required.' );
}
$checks = array();
$expect = static function ( $condition, $message ) use ( &$checks ) {
	if ( ! $condition ) { throw new RuntimeException( esc_html( $message ) ); }
	$checks[] = $message;
};
$look_field = static function () {
	$found = array();
	foreach ( mrn_base_stack_ensure_builder_layout_display_style_fields( mrn_base_stack_ensure_builder_layout_display_style_fields( array(), 'content_lists', 'field_mrn_test_list_display' ), 'content_lists', 'field_mrn_test_list_display' ) as $field ) {
		if ( 'row_look' === ( $field['name'] ?? '' ) ) { $found[] = $field; }
	}
	return 1 === count( $found ) ? $found[0] : ( $found ? 'duplicate' : null );
};
$render = static function ( $look ) {
	$row = array( 'acf_fc_layout' => 'content_lists', 'heading' => 'Owned list', 'list_post_type' => 'page', 'posts_per_page' => 1, 'row_look' => $look );
	ob_start(); mrn_base_stack_render_builder_row( $row, 0, 0 ); return ob_get_clean();
};
$registered = has_filter( 'mrn_base_stack_display_styles' );
$fixture = static function ( $styles ) {
	$styles['fixture-list-look'] = array( 'entity_type' => 'builder_layout', 'entity_subtype' => 'content_lists', 'label' => 'Fixture List Look', 'display_modes' => array( '*' ) );
	return $styles;
};
add_filter( 'mrn_base_stack_display_styles', $fixture, 99 );
$expect( isset( mrn_base_stack_get_content_list_look_choices()['fixture-list-look'] ) && ! isset( mrn_base_stack_get_content_list_look_choices()['default'] ), 'Registered Content Lists looks are offered; Default is not a look' );
$field = $look_field();
$expect( is_array( $field ) && '' === $field['default_value'] && isset( $field['choices']['fixture-list-look'], $field['choices'][''] ), 'One Section Look field offers None plus the registered looks' );
$html = $render( 'fixture-list-look' );
$expect( str_contains( $html, 'mrn-content-builder__row--content_lists-style-fixture-list-look' ), 'A registered look adds its row class to the Content Lists section' );
$expect( ! str_contains( $render( 'unknown" onclick="x' ), 'content_lists-style-' ) && ! str_contains( $render( '' ), 'content_lists-style-' ), 'Unknown and empty looks add nothing' );
remove_filter( 'mrn_base_stack_display_styles', $fixture, 99 );
$expect( ! isset( mrn_base_stack_get_content_list_look_choices()['fixture-list-look'] ), 'Looks disappear with their registration' );
WP_CLI::line( wp_json_encode( array( 'checks' => $checks, 'count' => count( $checks ), 'result' => 'passed', 'theme_display_styles' => (bool) $registered, 'source_version' => _S_VERSION ), JSON_PRETTY_PRINT ) );
