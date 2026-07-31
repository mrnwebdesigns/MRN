<?php
// phpcs:ignoreFile -- Standalone WordPress/ACF stub harness for builder field-tree regression.
/**
 * Focused runtime check for late ACF select-field finalization.
 *
 * Run with:
 * php stack/themes/mrn-base-stack/tests/php/acf-select-field-finalization.php
 *
 * @package mrn-base-stack
 */

$GLOBALS['mrn_acf_finalization_test_hooks'] = array();

function sanitize_key( $key ) {
	$key = strtolower( (string) $key );
	$key = preg_replace( '/[^a-z0-9_\-]/', '', $key );

	return is_string( $key ) ? $key : '';
}

function add_filter( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['mrn_acf_finalization_test_hooks'][] = array(
		'hook'          => $hook_name,
		'callback'      => $callback,
		'priority'      => $priority,
		'accepted_args' => $accepted_args,
	);
}

require_once __DIR__ . '/../../inc/builder/acf-field-finalization.php';

function mrn_acf_finalization_test_assert( $condition, $message ) {
	if ( $condition ) {
		return;
	}

	fwrite( STDERR, "FAIL: {$message}\n" );
	exit( 1 );
}

function mrn_acf_finalization_test_assert_selects_complete( $field, $path = 'root' ) {
	if ( ! is_array( $field ) ) {
		return;
	}

	if ( 'select' === ( $field['type'] ?? '' ) ) {
		mrn_acf_finalization_test_assert( array_key_exists( 'multiple', $field ), "{$path} includes multiple" );
		mrn_acf_finalization_test_assert( array_key_exists( 'return_format', $field ), "{$path} includes return_format" );
	}

	foreach ( array( 'sub_fields', 'fields' ) as $child_key ) {
		foreach ( (array) ( $field[ $child_key ] ?? array() ) as $index => $child_field ) {
			mrn_acf_finalization_test_assert_selects_complete( $child_field, "{$path}.{$child_key}.{$index}" );
		}
	}

	foreach ( (array) ( $field['layouts'] ?? array() ) as $layout_key => $layout ) {
		foreach ( (array) ( $layout['sub_fields'] ?? array() ) as $index => $sub_field ) {
			mrn_acf_finalization_test_assert_selects_complete( $sub_field, "{$path}.layouts.{$layout_key}.{$index}" );
		}
	}
}

$builder_tree = array(
	'key'     => 'field_test_builder',
	'name'    => 'page_content_rows',
	'type'    => 'flexible_content',
	'layouts' => array(
		'layout_test' => array(
			'key'        => 'layout_test',
			'name'       => 'test',
			'sub_fields' => array(
				array(
					'key'     => 'field_test_spacing',
					'name'    => 'row_spacing_margin_top_preset',
					'type'    => 'select',
					'choices' => array( 'large' => 'Large' ),
				),
				array(
					'key'        => 'field_test_items',
					'name'       => 'items',
					'type'       => 'repeater',
					'sub_fields' => array(
						array(
							'key'           => 'field_test_icon_position',
							'name'          => 'link_icon_position',
							'type'          => 'select',
							'multiple'      => 1,
							'return_format' => 'label',
						),
						array(
							'key'           => 'field_test_invalid_format',
							'name'          => 'display_style',
							'type'          => 'select',
							'return_format' => 'unsupported',
						),
					),
				),
			),
		),
	),
);

$finalized = mrn_base_stack_finalize_acf_builder_field_tree( $builder_tree );
mrn_acf_finalization_test_assert_selects_complete( $finalized );

$spacing_field = $finalized['layouts']['layout_test']['sub_fields'][0];
mrn_acf_finalization_test_assert( 0 === $spacing_field['multiple'], 'missing multiple defaults to zero' );
mrn_acf_finalization_test_assert( 'value' === $spacing_field['return_format'], 'missing return_format defaults to value' );

$icon_field = $finalized['layouts']['layout_test']['sub_fields'][1]['sub_fields'][0];
mrn_acf_finalization_test_assert( 1 === $icon_field['multiple'], 'valid multiple configuration is preserved' );
mrn_acf_finalization_test_assert( 'label' === $icon_field['return_format'], 'valid return_format is preserved' );

$invalid_field = $finalized['layouts']['layout_test']['sub_fields'][1]['sub_fields'][1];
mrn_acf_finalization_test_assert( 'value' === $invalid_field['return_format'], 'invalid return_format is normalized' );

$cloned_layouts = mrn_base_stack_finalize_cloned_acf_layouts(
	array(
		'layout_clone' => array(
			'key'        => 'layout_clone',
			'name'       => 'clone',
			'sub_fields' => array(
				array(
					'key'  => 'field_cloned_section_width',
					'name' => 'section_width',
					'type' => 'select',
				),
			),
		),
	)
);
mrn_acf_finalization_test_assert_selects_complete( $cloned_layouts['layout_clone'], 'cloned_layouts.layout_clone' );
mrn_acf_finalization_test_assert(
	0 === $cloned_layouts['layout_clone']['sub_fields'][0]['multiple'],
	'clone factories finalize select fields before caching layouts'
);

$late_hooks = array_filter(
	$GLOBALS['mrn_acf_finalization_test_hooks'],
	static function ( $hook ) {
		return 'mrn_base_stack_finalize_acf_builder_field_tree' === $hook['callback'];
	}
);

mrn_acf_finalization_test_assert( 4 === count( $late_hooks ), 'four targeted builder finalization hooks are registered' );
foreach ( $late_hooks as $hook ) {
	mrn_acf_finalization_test_assert( 999 === $hook['priority'], $hook['hook'] . ' runs after contract mutations' );
	mrn_acf_finalization_test_assert(
		false !== strpos( $hook['hook'], 'type=flexible_content' ) || false !== strpos( $hook['hook'], 'type=repeater' ),
		$hook['hook'] . ' stays scoped to builder tree types'
	);
}

echo "PASS: ACF builder select-field finalization regression.\n";
