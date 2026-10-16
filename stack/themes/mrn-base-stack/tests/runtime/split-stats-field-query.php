<?php
/** Read-only field-query recovery for native Stats in each Split context. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || home_url() !== getenv( 'MRN_STACK_TEST_HOME' ) || ! str_ends_with( (string) wp_parse_url( home_url(), PHP_URL_HOST ), '.localhost' ) || ! current_user_can( 'manage_options' ) ) {
	throw new RuntimeException( 'An explicitly selected local administrator runtime is required.' );
}
$checks = array();
foreach ( array( 'field_mrn_two_column_split_left_column', 'field_mrn_two_column_split_right_column', 'field_mrn_tabbed_panel_field_mrn_two_column_split_left_column' ) as $column_key ) {
	mrn_base_stack_get_tabbed_layout_nested_layouts();
	$column = acf_get_field( $column_key );
	if ( ! is_array( $column ) ) { throw new RuntimeException( esc_html( 'Missing column: ' . $column_key ) ); }
	$layouts = array_column( $column['layouts'], null, 'name' );
	$fields = array_column( $layouts['stats']['sub_fields'], null, 'name' );
	$items = array_column( $fields['stat_items']['sub_fields'], null, 'name' );
	$key = $items['link_icon_media_icon']['key'];
	acf_remove_local_field( $key );
	acf_get_store( 'fields' )->remove( $key );
	mrn_base_stack_register_cloned_acf_field_key( $key );
	$recovered = acf_get_field( $key );
	if ( ! is_array( $recovered ) || 'image' !== $recovered['type'] || 'link_icon_media_icon' !== $recovered['name'] ) {
		throw new RuntimeException( esc_html( 'Isolated ACF media field did not recover: ' . $column_key ) );
	}
	$checks[] = 'Isolated native icon/media selector resolves through its column factory: ' . $column_key;
}
WP_CLI::line( wp_json_encode( array( 'checks' => $checks, 'count' => count( $checks ), 'result' => 'passed', 'writes' => false ), JSON_PRETTY_PRINT ) );
