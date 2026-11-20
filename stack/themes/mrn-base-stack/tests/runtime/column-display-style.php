<?php
/** Two Column Split column layouts each expose exactly one Display Style field; read-only. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || home_url() !== getenv( 'MRN_STACK_TEST_HOME' ) || ! str_ends_with( (string) wp_parse_url( home_url(), PHP_URL_HOST ), '.localhost' ) || ! current_user_can( 'manage_options' ) ) {
	throw new RuntimeException( 'An explicitly selected local administrator runtime is required.' );
}
$checks = array();
$expect = static function ( $condition, $message ) use ( &$checks ) {
	if ( ! $condition ) { throw new RuntimeException( esc_html( $message ) ); }
	$checks[] = $message;
};
$count = static function ( $fields ) use ( &$count ) {
	$n = 0;
	foreach ( (array) $fields as $field ) {
		if ( 'display_style' === ( $field['name'] ?? '' ) ) { ++$n; }
		if ( 'clone' === ( $field['type'] ?? '' ) && ! empty( $field['sub_fields'] ) ) { $n += $count( $field['sub_fields'] ); }
	}
	return $n;
};
$layouts = array_column( acf_get_field( 'field_mrn_page_content_rows' )['layouts'], null, 'name' );
foreach ( $layouts['two_column_split']['sub_fields'] as $column ) {
	if ( 'flexible_content' !== ( $column['type'] ?? '' ) ) { continue; }
	foreach ( $column['layouts'] as $layout ) {
		$expect( 1 === $count( $layout['sub_fields'] ), "{$column['name']} {$layout['name']}: one Display Style field" );
	}
}
$expect( count( $checks ) > 10, 'Column layouts were inspected' );
WP_CLI::line( wp_json_encode( array( 'checks' => $checks, 'count' => count( $checks ), 'result' => 'passed', 'source_version' => _S_VERSION ), JSON_PRETTY_PRINT ) );
