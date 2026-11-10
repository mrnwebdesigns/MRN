<?php
/**
 * Verify the required PreviewShare installation and Stack configuration.
 *
 * Run with `wp eval-file stack/scripts/verify-previewshare.php` on a child
 * site after its plugin/configuration deployment. Exit status is non-zero on
 * missing activation, a wrong default TTL, or an unsafe post-type selection.
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "This script must run through wp eval-file.\n" );
	exit( 1 );
}

require_once ABSPATH . 'wp-admin/includes/plugin.php';

$expected_types = getenv( 'STACK_PREVIEWSHARE_POST_TYPES' );
if ( false === $expected_types || '' === trim( $expected_types ) ) {
	$expected_types = 'post,page';
}
$expected_types = array_values(
	array_unique(
		array_filter(
			array_map( 'sanitize_key', explode( ',', $expected_types ) )
		)
	)
);

$expected_ttl = getenv( 'STACK_PREVIEWSHARE_DEFAULT_TTL_HOURS' );
if ( false === $expected_ttl || '' === trim( $expected_ttl ) ) {
	$expected_ttl = '6';
}

$errors = array();
if ( ! is_plugin_active( 'previewshare/previewshare.php' ) ) {
	$errors[] = 'PreviewShare is not active';
}

$has_previewshare_robots_filter = false;
global $wp_filter;
if ( isset( $wp_filter['wp_robots'] ) && isset( $wp_filter['wp_robots']->callbacks ) ) {
	foreach ( $wp_filter['wp_robots']->callbacks as $callbacks ) {
		foreach ( $callbacks as $callback ) {
			$function = isset( $callback['function'] ) ? $callback['function'] : null;
			if ( is_array( $function )
				&& isset( $function[0], $function[1] )
				&& $function[0] instanceof \PreviewShare\Admin\Actions
				&& 'filter_preview_robots' === $function[1] ) {
				$has_previewshare_robots_filter = true;
				break 2;
			}
		}
	}
}
if ( ! $has_previewshare_robots_filter ) {
	$errors[] = 'PreviewShare noindex/nofollow robots protection is not registered';
}
if ( ! preg_match( '/^\d+$/', $expected_ttl ) || (int) get_option( 'previewshare_default_ttl_hours', -1 ) !== (int) $expected_ttl ) {
	$errors[] = 'PreviewShare default expiry does not match the configured value';
}

$configured_types = get_option( 'previewshare_post_types', array() );
if ( ! is_array( $configured_types ) || $configured_types !== $expected_types ) {
	$errors[] = 'PreviewShare enabled post types do not match the site configuration';
}

foreach ( (array) $configured_types as $slug ) {
	$post_type = get_post_type_object( $slug );
	if ( in_array( $slug, array( 'attachment', 'revision' ), true ) || ! $post_type || ! $post_type->public || ! is_post_type_viewable( $post_type ) ) {
		$errors[] = 'PreviewShare includes a non-public, non-viewable, attachment, or revision type: ' . $slug;
	}
}

if ( ! empty( $errors ) ) {
	fwrite( STDERR, implode( "\n", array_unique( $errors ) ) . "\n" );
	exit( 1 );
}

echo wp_json_encode(
	array(
		'plugin'           => 'previewshare',
		'active'           => true,
		'default_ttl_hours' => (int) $expected_ttl,
		'post_types'        => $configured_types,
		'robots_protection' => 'PreviewShare-managed noindex/nofollow registered',
	)
) . PHP_EOL;
