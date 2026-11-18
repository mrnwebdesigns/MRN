<?php
/** Structure and arrangement choices sit in the Layout tab, keep their keys, and leave content in Content; read-only. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || home_url() !== getenv( 'MRN_STACK_TEST_HOME' ) || ! str_ends_with( (string) wp_parse_url( home_url(), PHP_URL_HOST ), '.localhost' ) || ! current_user_can( 'manage_options' ) ) {
	throw new RuntimeException( 'An explicitly selected local administrator runtime is required.' );
}
$checks = array();
$expect = static function ( $condition, $message ) use ( &$checks ) {
	if ( ! $condition ) { throw new RuntimeException( esc_html( $message ) ); }
	$checks[] = $message;
};
$tab_of = static function ( array $fields ) {
	$tabs = array();
	$tab  = '';
	foreach ( $fields as $field ) {
		if ( 'tab' === ( $field['type'] ?? '' ) ) { $tab = sanitize_title( (string) ( $field['label'] ?? '' ) ); continue; }
		if ( '' !== ( $field['name'] ?? '' ) ) { $tabs[ $field['name'] ] = array( $tab, $field['key'] ?? '' ); }
	}
	return $tabs;
};

$names = mrn_base_stack_get_layout_tab_field_names();
foreach ( array( 'split_frame', 'split_stack_breakpoint', 'column_alignment', 'cta_frame', 'image_frame', 'card_frame', 'project_columns' ) as $name ) {
	$expect( in_array( $name, $names, true ), "$name is a Layout field" );
}

$fixture = array(
	array( 'key' => 'field_ly_content_tab', 'label' => 'Content', 'name' => '', 'type' => 'tab' ),
	array( 'key' => 'field_ly_heading', 'label' => 'Heading', 'name' => 'heading', 'type' => 'text' ),
	array( 'key' => 'field_ly_frame', 'label' => 'Split Frame', 'name' => 'split_frame', 'type' => 'select' ),
	array( 'key' => 'field_ly_layout_tab', 'label' => 'Layout', 'name' => '', 'type' => 'tab' ),
	array( 'key' => 'field_ly_width', 'label' => 'Width', 'name' => 'section_width', 'type' => 'select' ),
	array( 'key' => 'field_ly_effects_tab', 'label' => 'Effects', 'name' => '', 'type' => 'tab' ),
);
$tabs = $tab_of( mrn_base_stack_relocate_effect_fields( $fixture ) );
$expect( array( 'layout', 'field_ly_frame' ) === $tabs['split_frame'], 'A listed field moves to the Layout tab with its key unchanged' );
$expect( 'content' === $tabs['heading'][0], 'Content stays in Content' );
$no_layout = array( array( 'key' => 'field_ly_c', 'label' => 'Content', 'name' => '', 'type' => 'tab' ), array( 'key' => 'field_ly_frame_2', 'label' => 'Split Frame', 'name' => 'split_frame', 'type' => 'select' ) );
$expect( $no_layout === mrn_base_stack_relocate_effect_fields( $no_layout ), 'A list without a Layout tab keeps its fields in place' );

$layouts = array();
foreach ( array( 'field_mrn_page_content_rows' ) as $root ) {
	$field = acf_get_field( $root );
	foreach ( $field['layouts'] ?? array() as $layout ) {
		$layouts[ $layout['name'] ] = $layouts[ $layout['name'] ] ?? $tab_of( $layout['sub_fields'] ?? array() );
	}
}
foreach ( array( 'two_column_split' => array( 'split_frame', 'split_stack_breakpoint', 'column_rows_mode', 'column_alignment' ), 'cta' => array( 'cta_frame', 'cta_content_alignment' ), 'image_content' => array( 'image_frame', 'image_header_layout' ), 'card' => array( 'card_frame', 'card_equal_height_items' ), 'content_lists' => array( 'project_layout', 'people_frame' ) ) as $layout => $fields ) {
	foreach ( $fields as $name ) {
		$expect( 'layout' === ( $layouts[ $layout ][ $name ][0] ?? '' ), "$layout: $name is registered in the Layout tab" );
	}
}
foreach ( array( 'two_column_split' => 'heading', 'cta' => 'heading', 'content_lists' => 'list_count_mode' ) as $layout => $name ) {
	$expect( 'content' === ( $layouts[ $layout ][ $name ][0] ?? '' ), "$layout: $name stays in Content" );
}

WP_CLI::line( wp_json_encode( array( 'checks' => $checks, 'count' => count( $checks ), 'result' => 'passed', 'source_version' => _S_VERSION ), JSON_PRETTY_PRINT ) );
