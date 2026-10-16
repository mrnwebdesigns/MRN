<?php
/** Native Body Text list controls; creates and removes one local draft. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || home_url() !== getenv( 'MRN_STACK_TEST_HOME' ) || ! str_ends_with( (string) wp_parse_url( home_url(), PHP_URL_HOST ), '.localhost' ) || ! current_user_can( 'manage_options' ) ) {
	throw new RuntimeException( 'An explicitly selected local administrator runtime is required.' );
}
$checks = array();
$expect = static function ( $condition, $message ) use ( &$checks ) {
	if ( ! $condition ) { throw new RuntimeException( esc_html( $message ) ); }
	$checks[] = $message;
};
$id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'Native Body Text list qualification' ), true );
$expect( ! is_wp_error( $id ) && $id > 0, 'Owned local draft created' );
try {
	$layouts = array_column( acf_get_field( 'field_mrn_page_content_rows' )['layouts'], null, 'name' );
	$fields = array_column( $layouts['body_text']['sub_fields'], null, 'name' );
	$nested = mrn_base_stack_get_two_column_nested_layouts();
	$nested_fields = array_column( mrn_base_stack_ensure_presentation_fields( $nested['layout_mrn_nested_body_text']['sub_fields'], 'body_text', 'field_mrn_nested_body_text' ), null, 'name' );
	foreach ( array( 'list_columns', 'list_columns_mobile', 'list_flow', 'list_markers', 'list_alignment', 'list_gap' ) as $name ) {
		$expect( isset( $fields[ $name ], $nested_fields[ $name ] ) && $fields[ $name ]['key'] !== $nested_fields[ $name ]['key'], 'Root/nested unique editable field: ' . $name );
	}
	$expect( $fields['list_columns']['key'] === $fields['list_flow']['conditional_logic'][0][0]['field'] && $nested_fields['list_columns']['key'] === $nested_fields['list_flow']['conditional_logic'][0][0]['field'], 'Root/nested conditional controls reference their own opt-in selector' );
	$expect( array( '', 1, 2, 3 ) === array_keys( $fields['list_columns']['choices'] ), 'List columns are bounded and existing formatting remains the default' );
	$row = array( 'acf_fc_layout' => 'body_text', 'heading' => 'Choose your services', 'heading_tag' => 'h2', 'body_text' => '<ul><li><a href="/first/?a=1&amp;b=2">First service</a></li><li>Second service<ul><li>Nested detail</li></ul></li><li>Third service</li><li>Fourth service</li><li>Fifth service</li><li>Sixth service</li></ul>', 'list_columns' => '2', 'list_columns_mobile' => '2', 'list_flow' => 'columns', 'list_markers' => 'plain', 'list_alignment' => 'end-start', 'list_gap' => '30px' );
	foreach ( array( 'margin', 'padding' ) as $dimension ) {
		foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
			$row[ 'row_spacing_' . $dimension . '_' . $side . '_preset' ] = '0px';
		}
	}
	$split = array( 'acf_fc_layout' => 'two_column_split', 'column_ratio' => '50-50', 'left_column_rows' => array( array( 'acf_fc_layout' => 'body_text', 'body_text' => '<p>Existing formatting.</p>' ) ), 'right_column_rows' => array( $row ) );
	update_field( 'field_mrn_page_content_rows', array( $row, $split ), $id );
	$readback = get_field( 'page_content_rows', $id );
	$expect( '2' === $readback[0]['list_columns'] && 'columns' === $readback[1]['right_column_rows'][0]['list_flow'], 'Actual ACF root and nested write/readback retains list settings' );
	$expect( str_contains( $readback[0]['body_text'], 'Nested detail' ) && str_contains( $readback[0]['body_text'], '/first/?a=1&amp;b=2' ), 'Native WYSIWYG retains nested content and query links' );
	ob_start(); mrn_base_stack_render_builder_row( $readback[0], $id, 0 ); $html = ob_get_clean();
	$expect( str_contains( $html, 'mrn-ui__text--list-flow-columns' ) && str_contains( $html, 'mrn-ui__text--list-align-end-start' ) && str_contains( $html, '--mrn-list-columns:2' ) && str_contains( $html, '--mrn-list-column-gap:30px' ), 'Renderer emits only bounded native classes and styles' );
	$expect( str_contains( $html, '<ul>' ) && str_contains( $html, '<li>' ), 'Output keeps semantic ordered/unordered list markup' );
	ob_start(); mrn_base_stack_render_builder_row( $readback[1], $id, 1 ); $split_html = ob_get_clean();
	$expect( str_contains( $split_html, 'mrn-ui__text--list-flow-columns' ), 'Actual nested Split renderer uses native list controls' );
	$expect( array( 'classes' => array(), 'styles' => array() ) === mrn_base_stack_get_body_list_contract( array( 'list_columns' => '99', 'list_gap' => '2px; color:red' ) ), 'Invalid/unset column counts retain prior formatting' );
	$unsafe = mrn_base_stack_get_body_list_contract( array( 'list_columns' => '2', 'list_columns_mobile' => 'bad', 'list_alignment' => 'bad"', 'list_flow' => 'bad', 'list_gap' => '2px; color:red' ) );
	$expect( ! str_contains( implode( ' ', $unsafe['styles'] ), 'red' ) && in_array( '--mrn-list-columns-mobile:1', $unsafe['styles'], true ) && in_array( 'mrn-ui__text--list-align-start', $unsafe['classes'], true ), 'Malformed optional settings cannot escape into CSS or attributes' );
	$default = array( 'acf_fc_layout' => 'body_text', 'body_text' => '<p>Prior content.</p><ul><li>Original list</li></ul>' );
	ob_start(); mrn_base_stack_render_builder_row( $default, $id, 2 ); $default_html = ob_get_clean();
	$expect( ! str_contains( $default_html, 'mrn-ui__text--list-' ) && ! str_contains( $default_html, '--mrn-list-' ), 'Unset controls leave the existing renderer contract unchanged' );
	$rows = $row; $rows['heading'] = 'Across rows'; $rows['list_flow'] = 'rows';
	ob_start(); mrn_base_stack_render_builder_row( $rows, $id, 3 ); $rows_html = ob_get_clean();
	$expect( str_contains( $rows_html, 'mrn-ui__text--list-flow-rows' ), 'Row-major flow is explicitly selectable' );
	$proof = getenv( 'MRN_BODY_LIST_PROOF_PATH' );
	if ( $proof ) {
		$expect( str_ends_with( $proof, '.html' ) && is_dir( dirname( $proof ) ), 'Explicit proof destination exists outside authored content' );
		$document = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Native list qualification</title><link rel="stylesheet" href="style.css"></head><body><main><h1>Native list layout preview</h1>' . $html . $rows_html . $default_html . '</main></body></html>';
		$expect( false !== file_put_contents( $proof, $document ), 'Source proof written without changing an authored page' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Explicit CLI-only local proof.
	}
} finally {
	wp_delete_post( $id, true );
}
$expect( null === get_post( $id ), 'Owned fixture removed' );
WP_CLI::line( wp_json_encode( array( 'checks' => $checks, 'count' => count( $checks ), 'result' => 'passed', 'source_version' => _S_VERSION ), JSON_PRETTY_PRINT ) );
