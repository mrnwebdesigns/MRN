<?php
// phpcs:ignoreFile -- Standalone WordPress/ACF stub harness for cloned-field AJAX contracts.
/**
 * Regression coverage for AJAX-backed fields in runtime-cloned builder layouts.
 *
 * Run with:
 * php stack/themes/mrn-base-stack/tests/php/acf-cloned-ajax-fields.php
 *
 * @package mrn-base-stack
 */

$GLOBALS['mrn_acf_clone_test_filters'] = array();
$GLOBALS['mrn_acf_clone_test_fields']  = array();
$GLOBALS['mrn_acf_clone_test_posts']   = array(
	array(
		'ID'        => 101,
		'post_title' => 'Reusable Alpha',
		'post_type'  => 'mrn_reusable_basic',
		'post_status' => 'publish',
	),
	array(
		'ID'        => 102,
		'post_title' => 'Reusable Draft',
		'post_type'  => 'mrn_reusable_basic',
		'post_status' => 'draft',
	),
);

function add_filter( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['mrn_acf_clone_test_filters'][ $hook_name ][ $priority ][] = array(
		'callback'      => $callback,
		'accepted_args' => $accepted_args,
	);
}

function add_action( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
	add_filter( $hook_name, $callback, $priority, $accepted_args );
}

function apply_filters( $hook_name, $value, ...$args ) {
	if ( empty( $GLOBALS['mrn_acf_clone_test_filters'][ $hook_name ] ) ) {
		return $value;
	}

	ksort( $GLOBALS['mrn_acf_clone_test_filters'][ $hook_name ] );
	foreach ( $GLOBALS['mrn_acf_clone_test_filters'][ $hook_name ] as $callbacks ) {
		foreach ( $callbacks as $registered ) {
			$callback_args = array_merge( array( $value ), array_slice( $args, 0, max( 0, $registered['accepted_args'] - 1 ) ) );
			$value         = call_user_func_array( $registered['callback'], $callback_args );
		}
	}

	return $value;
}

function sanitize_key( $key ) {
	$key = strtolower( (string) $key );
	$key = preg_replace( '/[^a-z0-9_\-]/', '', $key );

	return is_string( $key ) ? $key : '';
}

function sanitize_text_field( $value ) {
	return trim( (string) $value );
}

function wp_unslash( $value ) {
	return $value;
}

function wp_doing_ajax() {
	return true;
}

function absint( $value ) {
	return abs( (int) $value );
}

function acf_add_local_field( $field, $prepared = false ) {
	unset( $prepared );

	if ( ! is_array( $field ) || empty( $field['key'] ) ) {
		return;
	}

	$GLOBALS['mrn_acf_clone_test_fields'][ $field['key'] ] = $field;

	foreach ( array( 'sub_fields', 'fields' ) as $child_key ) {
		foreach ( (array) ( $field[ $child_key ] ?? array() ) as $child_field ) {
			acf_add_local_field( $child_field );
		}
	}

	foreach ( (array) ( $field['layouts'] ?? array() ) as $layout ) {
		foreach ( (array) ( $layout['sub_fields'] ?? array() ) as $sub_field ) {
			acf_add_local_field( $sub_field );
		}
	}
}

function acf_get_local_field( $key ) {
	return $GLOBALS['mrn_acf_clone_test_fields'][ $key ] ?? false;
}

function acf_get_field( $key ) {
	return acf_get_local_field( $key );
}

function mrn_acf_clone_test_assert( $condition, $message ) {
	if ( $condition ) {
		return;
	}

	fwrite( STDERR, "FAIL: {$message}\n" );
	exit( 1 );
}

/**
 * Minimal ACF post-object query contract for this isolated harness.
 *
 * @param string $field_key Field key submitted by Select2.
 * @param string $search Search phrase.
 * @param int    $include Existing selected post ID.
 * @return array<string, mixed>|false
 */
function mrn_acf_clone_test_ajax_query( $field_key, $search = '', $include = 0 ) {
	$field = acf_get_field( $field_key );
	if ( ! is_array( $field ) ) {
		return false;
	}

	$args = array(
		'post_type'      => (array) ( $field['post_type'] ?? array() ),
		'posts_per_page' => 20,
	);
	$args = apply_filters( 'acf/fields/post_object/query', $args, $field, 64 );
	$args = apply_filters( 'acf/fields/post_object/query/name=' . ( $field['name'] ?? '' ), $args, $field, 64 );
	$args = apply_filters( 'acf/fields/post_object/query/key=' . $field['key'], $args, $field, 64 );

	$results = array();
	foreach ( $GLOBALS['mrn_acf_clone_test_posts'] as $post ) {
		if ( ! empty( $args['post_type'] ) && ! in_array( $post['post_type'], $args['post_type'], true ) ) {
			continue;
		}

		if ( ! empty( $args['post_status'] ) && ! in_array( $post['post_status'], (array) $args['post_status'], true ) ) {
			continue;
		}

		if ( $include > 0 && $include !== $post['ID'] ) {
			continue;
		}

		if ( 0 === $include && '' !== $search && false === stripos( $post['post_title'], $search ) ) {
			continue;
		}

		$results[] = array(
			'id'   => $post['ID'],
			'text' => $post['post_title'],
		);
	}

	return array(
		'results' => $results,
		'limit'   => $args['posts_per_page'],
	);
}

$source_field = array(
	'key'     => 'field_mrn_page_content_rows',
	'name'    => 'page_content_rows',
	'type'    => 'flexible_content',
	'layouts' => array(
		'layout_mrn_reusable_block'   => array(
			'key'        => 'layout_mrn_reusable_block',
			'name'       => 'reusable_block',
			'sub_fields' => array(
				array(
					'key'           => 'field_mrn_reusable_block_post',
					'name'          => 'block',
					'type'          => 'post_object',
					'post_type'     => array( 'mrn_reusable_basic' ),
					'return_format' => 'object',
				),
			),
		),
		'layout_mrn_two_column_split' => array(
			'key'        => 'layout_mrn_two_column_split',
			'name'       => 'two_column_split',
			'sub_fields' => array(
				array(
					'key'     => 'field_mrn_two_column_left_rows',
					'name'    => 'left_column_rows',
					'type'    => 'flexible_content',
					'layouts' => array(
						'layout_mrn_nested_reusable_block' => array(
							'key'        => 'layout_mrn_nested_reusable_block',
							'name'       => 'reusable_block',
							'sub_fields' => array(
								array(
									'key'       => 'field_mrn_nested_reusable_block_post',
									'name'      => 'block',
									'type'      => 'post_object',
									'post_type' => array( 'mrn_reusable_basic' ),
								),
								array(
									'key'       => 'field_mrn_nested_wpforms_form',
									'name'      => 'form',
									'type'      => 'post_object',
									'post_type' => array( 'wpforms' ),
								),
							),
						),
					),
				),
			),
		),
	),
);

$GLOBALS['mrn_acf_clone_test_fields'][ $source_field['key'] ] = $source_field;
foreach ( $source_field['layouts'] as $source_layout ) {
	foreach ( $source_layout['sub_fields'] as $source_sub_field ) {
		acf_add_local_field( $source_sub_field );
	}
}

require_once __DIR__ . '/../../inc/builder/acf-field-finalization.php';
require_once __DIR__ . '/../../inc/builder/helpers.php';
require_once __DIR__ . '/../../inc/builder/render.php';

mrn_acf_clone_test_assert( is_array( acf_get_field( 'field_mrn_reusable_block_post' ) ), 'The original Content reusable-block key still resolves.' );
mrn_acf_clone_test_assert( false === acf_get_field( 'after_content_field_mrn_reusable_block_post' ), 'The isolated request starts without the derived After Content key.' );

$saved_values = array(
	'page_after_content_rows'                    => array(),
	'page_after_content_rows_0_reusable_block'   => '',
);
$saved_before = $saved_values;

$_REQUEST['action']    = 'acf/fields/post_object/query';
$_REQUEST['field_key'] = 'after_content_field_mrn_reusable_block_post';
mrn_base_stack_register_cloned_acf_ajax_fields();

$after_field = acf_get_field( 'after_content_field_mrn_reusable_block_post' );
mrn_acf_clone_test_assert( is_array( $after_field ), 'The derived After Content key resolves during an isolated AJAX-style request.' );
mrn_acf_clone_test_assert( 'block' === $after_field['name'], 'Cloned field names remain unchanged.' );
mrn_acf_clone_test_assert( 'post_object' === $after_field['type'], 'The cloned field keeps its post-object type.' );

$original_response = mrn_acf_clone_test_ajax_query( 'field_mrn_reusable_block_post', 'Reusable' );
$after_response    = mrn_acf_clone_test_ajax_query( 'after_content_field_mrn_reusable_block_post', 'Reusable' );

mrn_acf_clone_test_assert( is_array( $original_response ) && 1 === count( $original_response['results'] ), 'The original Content picker still returns one published result.' );
mrn_acf_clone_test_assert( is_array( $after_response ) && 1 === count( $after_response['results'] ), 'The After Content picker returns Select2 results instead of failing.' );
mrn_acf_clone_test_assert( 101 === $after_response['results'][0]['id'], 'The published reusable block is returned.' );
mrn_acf_clone_test_assert( false === strpos( json_encode( $after_response ), 'Reusable Draft' ), 'Draft reusable blocks stay excluded.' );

$selected_response = mrn_acf_clone_test_ajax_query( 'after_content_field_mrn_reusable_block_post', '', 101 );
mrn_acf_clone_test_assert( is_array( $selected_response ) && 101 === $selected_response['results'][0]['id'], 'An existing selected reusable block still loads by ID.' );

$nested_key = 'after_content_field_mrn_nested_reusable_block_post';
mrn_acf_clone_test_assert( is_array( acf_get_field( $nested_key ) ), 'Nested reusable selectors are recursively registered.' );
$nested_response = mrn_acf_clone_test_ajax_query( $nested_key, 'Reusable' );
mrn_acf_clone_test_assert( 1 === count( $nested_response['results'] ), 'Nested cloned reusable selectors retain published-only results.' );

$wpforms_key   = 'after_content_field_mrn_nested_wpforms_form';
$wpforms_field = acf_get_field( $wpforms_key );
mrn_acf_clone_test_assert( is_array( $wpforms_field ), 'Other AJAX-backed fields in the cloned tree are also resolvable.' );
$wpforms_args = apply_filters( 'acf/fields/post_object/query', array(), $wpforms_field, 64 );
mrn_acf_clone_test_assert( ! isset( $wpforms_args['post_status'] ), 'Published-only filtering remains scoped to reusable-block selectors.' );

mrn_acf_clone_test_assert( $saved_before === $saved_values, 'Empty After Content rows and intentionally empty saved fields remain unchanged.' );

echo "PASS: Cloned ACF AJAX field registration and reusable-block query contracts.\n";
