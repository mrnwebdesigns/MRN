<?php
// phpcs:ignoreFile -- Standalone WordPress/ACF stub harness for lazy builder regression.
/**
 * Regression coverage for lazy 404 builder hydration.
 *
 * @package mrn-base-stack
 */

define( 'ABSPATH', __DIR__ );

$GLOBALS['mrn_test_actions']      = array();
$GLOBALS['mrn_test_filters']      = array();
$GLOBALS['mrn_test_field_groups'] = array();
$GLOBALS['mrn_test_source_calls'] = 0;

/** Record an action registered by the tested file. */
function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['mrn_test_actions'][] = array( $hook, $callback, $priority, $accepted_args );
}

/** Record a filter registered by the tested file. */
function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['mrn_test_filters'][] = array( $hook, $callback, $priority, $accepted_args );
}

/** Return the supplied value for standalone filter testing. */
function apply_filters( $hook, $value, ...$args ) {
	return $value;
}

/** Return untranslated standalone test text. */
function __( $text, $domain = 'default' ) {
	return $text;
}

/** Sanitize a standalone test key. */
function sanitize_key( $value ) {
	return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $value ) );
}

/** Sanitize standalone test text. */
function sanitize_text_field( $value ) {
	return trim( (string) $value );
}

/** Capture a registered local field group. */
function acf_add_local_field_group( $group ) {
	$GLOBALS['mrn_test_field_groups'][] = $group;
}

/** Return representative Content builder layouts. */
function mrn_base_stack_get_content_builder_source_layouts() {
	++$GLOBALS['mrn_test_source_calls'];

	return array(
		'layout_body_text' => array(
			'key'        => 'layout_body_text',
			'name'       => 'body_text',
			'sub_fields' => array(
				array(
					'key'  => 'field_body_text',
					'name' => 'text',
				),
			),
		),
		'layout_hero'      => array(
			'key'        => 'layout_hero',
			'name'       => 'hero',
			'sub_fields' => array(),
		),
	);
}

/** Clone representative ACF keys with the supplied prefix. */
function mrn_base_stack_clone_acf_keys_with_prefix( array $value, $prefix ) {
	foreach ( $value as $item_key => $item_value ) {
		if ( 'key' === $item_key && is_string( $item_value ) ) {
			$value[ $item_key ] = $prefix . $item_value;
		} elseif ( is_array( $item_value ) ) {
			$value[ $item_key ] = mrn_base_stack_clone_acf_keys_with_prefix( $item_value, $prefix );
		}
	}

	return $value;
}

require dirname( __DIR__, 2 ) . '/inc/not-found.php';

mrn_base_stack_register_not_found_field_group();
/** @var array<int, array{fields: array<int, array<string, mixed>>}> $registered_groups */
$registered_groups = $GLOBALS['mrn_test_field_groups'];
if ( empty( $registered_groups[0]['fields'][8] ) ) {
	throw new RuntimeException( '404 field group was not registered.' );
}

/** @var array<string, mixed> $field */
$field = $registered_groups[0]['fields'][8];

if ( ! array_key_exists( 'layouts', $field ) || array() !== $field['layouts'] ) {
	throw new RuntimeException( '404 layouts were hydrated during field-group registration.' );
}
if ( 0 !== $GLOBALS['mrn_test_source_calls'] ) {
	throw new RuntimeException( 'Content layouts were loaded during field-group registration.' );
}

$hydrated = mrn_base_stack_populate_not_found_builder_field( $field );
$second   = mrn_base_stack_populate_not_found_builder_field( $field );

if ( empty( $hydrated['layouts']['layout_body_text'] ) || isset( $hydrated['layouts']['layout_hero'] ) ) {
	throw new RuntimeException( '404 layout allowlist was not preserved during lazy hydration.' );
}
if ( 'not_found_layout_body_text' !== $hydrated['layouts']['layout_body_text']['key'] ) {
	throw new RuntimeException( '404 layout keys were not cloned safely.' );
}
if ( 1 !== $GLOBALS['mrn_test_source_calls'] || $hydrated['layouts'] !== $second['layouts'] ) {
	throw new RuntimeException( 'Lazy 404 layouts were not cached for the request.' );
}

echo "Lazy 404 builder layout tests passed.\n";
