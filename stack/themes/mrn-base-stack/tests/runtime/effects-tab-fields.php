<?php
/** Motion and behavior toggles sit in the Effects tab, keep their keys, and leave content in Content; read-only. */
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

$names = mrn_base_stack_get_effects_tab_field_names();
foreach ( array( 'image_drift_speed', 'timeline_pin_tablet', 'project_carousel_autoplay', 'text_presentation', 'strip_behavior' ) as $name ) {
	$expect( in_array( $name, $names, true ), "$name is an Effects field" );
}

$fixture = array(
	array( 'key' => 'field_fx_content_tab', 'label' => 'Content', 'name' => '', 'type' => 'tab' ),
	array( 'key' => 'field_fx_heading', 'label' => 'Heading', 'name' => 'heading', 'type' => 'text' ),
	array( 'key' => 'field_fx_drift', 'label' => 'Scroll Drift Speed', 'name' => 'image_drift_speed', 'type' => 'number' ),
	array( 'key' => 'field_fx_layout_tab', 'label' => 'Layout', 'name' => '', 'type' => 'tab' ),
	array( 'key' => 'field_fx_width', 'label' => 'Width', 'name' => 'content_width', 'type' => 'text' ),
);
$moved = $tab_of( mrn_base_stack_relocate_effect_fields( $fixture ) );
$expect( array( 'effects', 'field_fx_drift' ) === $moved['image_drift_speed'], 'A listed field moves to Effects with its key unchanged' );
$expect( 'content' === $moved['heading'][0] && 'layout' === $moved['content_width'][0], 'Unlisted fields keep their tabs' );

$layouts = array();
foreach ( acf_get_field_groups() as $group ) {
	foreach ( acf_get_fields( $group ) as $field ) {
		foreach ( $field['layouts'] ?? array() as $layout ) {
			$layouts[ $layout['name'] ] = $layouts[ $layout['name'] ] ?? $tab_of( $layout['sub_fields'] ?? array() );
		}
	}
}
foreach ( array( 'image_content' => 'image_drift_speed', 'card' => 'timeline_pin_tablet', 'body_text' => 'text_presentation', 'showcase' => 'strip_behavior', 'video' => 'video_playback' ) as $layout => $name ) {
	$expect( 'effects' === ( $layouts[ $layout ][ $name ][0] ?? '' ), "$layout: $name is registered in the Effects tab" );
}
$expect( 'content' === ( $layouts['image_content']['image_presentation'][0] ?? '' ), 'The media presentation select, which also chooses layouts, stays in Content' );

WP_CLI::line( wp_json_encode( array( 'checks' => $checks, 'count' => count( $checks ), 'result' => 'passed', 'source_version' => _S_VERSION ), JSON_PRETTY_PRINT ) );
