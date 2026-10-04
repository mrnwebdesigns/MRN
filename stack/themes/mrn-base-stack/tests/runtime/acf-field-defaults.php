<?php
/**
 * Read-only runtime regression for completed nested ACF builder field trees.
 *
 * Run with:
 * wp eval-file stack/themes/mrn-base-stack/tests/runtime/acf-field-defaults.php
 *
 * @package mrn-base-stack
 */

if ( ! defined( 'ABSPATH' ) || ! function_exists( 'acf_get_field' ) || ! function_exists( 'acf_get_field_type' ) ) {
	fwrite( STDERR, "FAIL: WordPress with ACF PRO is required.\n" );
	exit( 1 );
}

/**
 * Fail the runtime check with a precise message.
 *
 * @param bool   $condition Assertion result.
 * @param string $message Assertion message.
 * @return void
 */
function mrn_acf_field_defaults_runtime_assert( $condition, $message ) {
	if ( $condition ) {
		return;
	}

	fwrite( STDERR, "FAIL: {$message}\n" );
	exit( 1 );
}

/**
 * Find a real nested field by key within a completed ACF field tree.
 *
 * @param mixed  $field Field, layout, or field collection.
 * @param string $wanted_key Field key to find.
 * @return array<string, mixed>|false
 */
function mrn_acf_field_defaults_runtime_find( $field, $wanted_key ) {
	if ( ! is_array( $field ) ) {
		return false;
	}

	if ( isset( $field['key'] ) && $wanted_key === $field['key'] && isset( $field['type'] ) ) {
		return $field;
	}

	foreach ( array( 'sub_fields', 'fields' ) as $child_key ) {
		foreach ( (array) ( $field[ $child_key ] ?? array() ) as $child_field ) {
			$match = mrn_acf_field_defaults_runtime_find( $child_field, $wanted_key );
			if ( false !== $match ) {
				return $match;
			}
		}
	}

	foreach ( (array) ( $field['layouts'] ?? array() ) as $layout ) {
		$match = mrn_acf_field_defaults_runtime_find( $layout, $wanted_key );
		if ( false !== $match ) {
			return $match;
		}
	}

	return false;
}

$runtime_cases = array(
	array(
		'root_key'    => 'field_mrn_page_hero_rows',
		'field_key'   => 'field_mrn_hero_two_column_split_min_height',
		'type'        => 'text',
		'default_key' => 'maxlength',
		'value'       => '24rem',
	),
	array(
		'root_key'    => 'field_mrn_page_content_rows',
		'field_key'   => 'field_mrn_content_grid_items_links_icon_gap',
		'type'        => 'number',
		'default_key' => 'max',
		'value'       => '8',
	),
);

foreach ( $runtime_cases as $case ) {
	$root = acf_get_field( $case['root_key'] );
	mrn_acf_field_defaults_runtime_assert( is_array( $root ), $case['root_key'] . ' resolves from the active parent runtime' );

	$field = mrn_acf_field_defaults_runtime_find( $root, $case['field_key'] );
	mrn_acf_field_defaults_runtime_assert( is_array( $field ), $case['field_key'] . ' resolves in the completed nested field tree' );
	mrn_acf_field_defaults_runtime_assert( ( $field['type'] ?? '' ) === $case['type'], $case['field_key'] . ' keeps its field type' ); // phpcs:ignore WordPress.PHP.YodaConditions.NotYoda -- Both operands are dynamic field values.
	mrn_acf_field_defaults_runtime_assert( array_key_exists( $case['default_key'], $field ), $case['field_key'] . ' includes ' . $case['default_key'] );

	$warnings = array();
	set_error_handler(
		static function ( $severity, $message, $file, $line ) use ( &$warnings ) {
			$warnings[] = array(
				'severity' => $severity,
				'message'  => $message,
				'file'     => basename( $file ),
				'line'     => $line,
			);

			return true;
		}
	);

	$field_type = acf_get_field_type( $case['type'] );
	mrn_acf_field_defaults_runtime_assert( is_object( $field_type ), $case['type'] . ' validator resolves' );
	$valid = $field_type->validate_value( true, $case['value'], $field, 'acf[' . $case['field_key'] . ']' );
	restore_error_handler();

	mrn_acf_field_defaults_runtime_assert( true === $valid, $case['field_key'] . ' accepts its representative value' );
	mrn_acf_field_defaults_runtime_assert( array() === $warnings, $case['field_key'] . ' validates without PHP warnings' );

	$zero_limited                         = $field;
	$zero_limited[ $case['default_key'] ] = 0;
	$zero_finalized                       = mrn_base_stack_finalize_acf_builder_field_tree( $zero_limited );
	mrn_acf_field_defaults_runtime_assert( 0 === $zero_finalized[ $case['default_key'] ], $case['field_key'] . ' preserves an explicit zero limit' );
}

echo "PASS: Real nested ACF builder fields include validator defaults and validate without warnings.\n";
