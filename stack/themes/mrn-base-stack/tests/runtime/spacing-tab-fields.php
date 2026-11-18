<?php
/** Per-row spacing values an editor still sets sit in the Spacing tab and keep their keys; read-only. */
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

$expect( array( 'slider_gap', 'split_row_vertical_padding', 'split_row_horizontal_padding' ) === mrn_base_stack_get_spacing_tab_field_names(), 'The Spacing tab list names the slider gap and the split row padding switches' );

$fixture = array(
	array( 'key' => 'field_sp_content_tab', 'label' => 'Content', 'name' => '', 'type' => 'tab' ),
	array( 'key' => 'field_sp_gap', 'label' => 'Space Between Logos', 'name' => 'slider_gap', 'type' => 'text' ),
	array( 'key' => 'field_sp_heading', 'label' => 'Heading', 'name' => 'heading', 'type' => 'text' ),
	array( 'key' => 'field_sp_spacing_tab', 'label' => 'Spacing', 'name' => '', 'type' => 'tab' ),
	array( 'key' => 'field_sp_preset', 'label' => 'Top Margin', 'name' => 'row_spacing_margin_top_preset', 'type' => 'select' ),
	array( 'key' => 'field_sp_layout_tab', 'label' => 'Layout', 'name' => '', 'type' => 'tab' ),
	array( 'key' => 'field_sp_width', 'label' => 'Width', 'name' => 'section_width', 'type' => 'select' ),
);
$moved = mrn_base_stack_relocate_effect_fields( $fixture );
$tabs  = $tab_of( $moved );
$expect( array( 'spacing', 'field_sp_gap' ) === $tabs['slider_gap'], 'A listed field moves to the Spacing tab with its key unchanged' );
$expect( array( 'heading', 'row_spacing_margin_top_preset', 'slider_gap', 'section_width' ) === array_values( array_filter( array_column( $moved, 'name' ) ) ), 'It lands after the row presets and before the next tab' );
$expect( 'content' === $tabs['heading'][0] && 'layout' === $tabs['section_width'][0], 'Unlisted fields keep their tabs' );
$untabbed = array( array( 'key' => 'field_sp_gap_2', 'label' => 'Space Between Logos', 'name' => 'slider_gap', 'type' => 'text' ), array( 'key' => 'field_sp_heading_2', 'label' => 'Heading', 'name' => 'heading', 'type' => 'text' ) );
$expect( $untabbed === mrn_base_stack_relocate_effect_fields( $untabbed ), 'A list without a Spacing tab is left as it is' );

$layouts = array();
foreach ( acf_get_field_groups() as $group ) {
	foreach ( acf_get_fields( $group ) as $field ) {
		foreach ( $field['layouts'] ?? array() as $layout ) {
			$layouts[ $layout['name'] ] = $layouts[ $layout['name'] ] ?? $tab_of( $layout['sub_fields'] ?? array() );
		}
	}
}
foreach ( array( 'logos' => 'slider_gap', 'two_column_split' => 'split_row_vertical_padding' ) as $layout => $name ) {
	$expect( 'spacing' === ( $layouts[ $layout ][ $name ][0] ?? '' ), "$layout: $name is registered in the Spacing tab" );
}
$expect( 'effects' === ( $layouts['video']['video_follow_color'][0] ?? '' ), 'video: the pointer follow color is registered in the Effects tab' );
foreach ( array( 'image_content' => 'image_inner_gutter', 'two_column_split' => 'split_first_column_max', 'card' => 'card_content_width' ) as $layout => $name ) {
	$expect( ! isset( $layouts[ $layout ][ $name ] ), "$layout: retired styling field $name is not registered" );
}

WP_CLI::line( wp_json_encode( array( 'checks' => $checks, 'count' => count( $checks ), 'result' => 'passed', 'source_version' => _S_VERSION ), JSON_PRETTY_PRINT ) );
