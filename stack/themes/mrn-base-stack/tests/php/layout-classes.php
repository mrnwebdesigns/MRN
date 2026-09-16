<?php
// phpcs:ignoreFile -- Standalone WordPress stub harness for layout-class regression coverage.
/**
 * Focused checks for builder Layout Class fields and front-end contracts.
 *
 * Run with:
 * php stack/themes/mrn-base-stack/tests/php/layout-classes.php
 *
 * @package mrn-base-stack
 */

define( 'ABSPATH', __DIR__ );

function sanitize_key( $value ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) );
}

function sanitize_title( $value ) {
	$value = strtolower( trim( (string) $value ) );
	$value = preg_replace( '/[^a-z0-9_\-]+/', '-', $value );

	return trim( (string) $value, '-' );
}

function sanitize_html_class( $value ) {
	return preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $value );
}

function add_filter( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
	return true;
}

function add_action( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
	return true;
}

function apply_filters( $hook_name, $value, ...$args ) {
	return $value;
}

function __( $text, $domain = '' ) {
	return $text;
}

require_once __DIR__ . '/../../inc/builder/helpers.php';
require_once __DIR__ . '/../../inc/display-styles.php';

function mrn_layout_class_test_assert( $condition, $message ) {
	if ( $condition ) {
		return;
	}

	fwrite( STDERR, "FAIL: {$message}\n" );
	exit( 1 );
}

function mrn_layout_class_test_assert_same( $expected, $actual, $message ) {
	if ( $expected === $actual ) {
		return;
	}

	fwrite( STDERR, "FAIL: {$message}\nExpected: " . var_export( $expected, true ) . "\nActual: " . var_export( $actual, true ) . "\n" );
	exit( 1 );
}

$fields = mrn_base_stack_ensure_layout_class_field(
	array(
		array(
			'key'  => 'field_test_anchor',
			'name' => 'anchor',
			'type' => 'text',
		),
		array(
			'key'  => 'field_test_background',
			'name' => 'background_color',
			'type' => 'select',
		),
	)
);

mrn_layout_class_test_assert_same(
	array( 'anchor', 'layout_class', 'background_color' ),
	array_column( $fields, 'name' ),
	'Layout Class is inserted directly after Anchor ID.'
);
mrn_layout_class_test_assert_same( 'Layout Class', $fields[1]['label'], 'The generated field uses the requested label.' );
mrn_layout_class_test_assert_same( 'layout', mrn_base_stack_get_main_config_field_group_key( $fields[1] ), 'Layout Class belongs to Basic Setting.' );

$fields = mrn_base_stack_ensure_layout_class_field( $fields );
mrn_layout_class_test_assert_same( 1, count( array_filter( array_column( $fields, 'name' ), static function ( $name ) {
	return 'layout_class' === $name;
} ) ), 'Repeated contract passes do not duplicate Layout Class.' );

mrn_layout_class_test_assert_same(
	array( 'hero-row', 'featured', 'badclass', 'cta--wide' ),
	mrn_base_stack_normalize_layout_classes( '.hero-row, featured, .hero-row, bad class, ., .cta--wide' ),
	'Leading periods, comma-separated values, invalid characters, and duplicates are normalized.'
);
mrn_layout_class_test_assert_same( array(), mrn_base_stack_normalize_layout_classes( array( 'not', 'text' ) ), 'Non-string values are ignored.' );

$contract = mrn_base_stack_get_builder_display_contract(
	array(
		'layout_class' => '.hero-row, featured',
	),
	'basic'
);
mrn_layout_class_test_assert( in_array( 'hero-row', $contract['classes'], true ), 'The first custom class reaches the row display contract.' );
mrn_layout_class_test_assert( in_array( 'featured', $contract['classes'], true ), 'Every comma-separated class reaches the row display contract.' );

echo "Builder layout-class tests passed.\n";
