<?php
// phpcs:ignoreFile -- Standalone WordPress stub harness for the resource featured-image contract.
/**
 * Regression coverage for the resource CPT's featured-image support.
 *
 * Resources are treated as admin/data-only content, so the registration
 * contract must keep thumbnail support even when the admin/data filter makes
 * the CPT non-archived.
 *
 * @package mrn-base-stack
 */

define( 'ABSPATH', __DIR__ );

$GLOBALS['mrn_resource_test_registered_post_types'] = array();

function add_action( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
	return true;
}

function add_filter( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
	return true;
}

function apply_filters( $hook_name, $value, ...$args ) {
	unset( $args );

	if ( 'mrn_admin_data_post_types' === $hook_name ) {
		return array(
			'resource' => array(
				'show_ui'       => true,
				'show_in_menu'  => true,
				'admin_cleanup' => true,
			),
		);
	}

	return $value;
}

function __( $text, $domain = 'default' ) {
	return $text;
}

function sanitize_key( $value ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) );
}

function get_option( $option, $default = array() ) {
	unset( $option );

	return $default;
}

function update_option( $option, $value ) {
	unset( $option, $value );

	return true;
}

function register_post_type( $post_type, $args ) {
	$GLOBALS['mrn_resource_test_registered_post_types'][ $post_type ] = $args;
}

function mrn_resource_featured_image_test_assert( $condition, $message ) {
	if ( $condition ) {
		return;
	}

	fwrite( STDERR, "FAIL: {$message}\n" );
	exit( 1 );
}

require dirname( __DIR__, 2 ) . '/inc/resources.php';

mrn_base_stack_register_resource_post_type();

$resource_args = $GLOBALS['mrn_resource_test_registered_post_types']['resource'] ?? null;
if ( ! is_array( $resource_args ) ) {
	fwrite( STDERR, "FAIL: Resource CPT must register.\n" );
	exit( 1 );
}

if ( ! isset( $resource_args['supports'] ) || ! is_array( $resource_args['supports'] ) ) {
	fwrite( STDERR, "FAIL: Resource CPT supports must be registered.\n" );
	exit( 1 );
}

mrn_resource_featured_image_test_assert( in_array( 'thumbnail', $resource_args['supports'], true ), 'Resource CPT must support featured images.' );

$non_archived_args               = $resource_args;
$non_archived_args['has_archive'] = false;

mrn_resource_featured_image_test_assert( false === $non_archived_args['has_archive'], 'A non-archived Resource CPT must remain non-archived.' );
mrn_resource_featured_image_test_assert( in_array( 'thumbnail', $non_archived_args['supports'], true ), 'Featured-image support must survive the non-archived shape.' );

echo "PASS: Resource CPT keeps featured-image support in admin/data-only mode.\n";
