<?php
/**
 * Configure PreviewShare using Stack environment configuration.
 *
 * Run with `wp eval-file stack/scripts/configure-previewshare.php`. The
 * bootstrap exports STACK_PREVIEWSHARE_POST_TYPES and
 * STACK_PREVIEWSHARE_DEFAULT_TTL_HOURS for this process. The script validates
 * the configured content types against the site's registered public, viewable
 * post types, so an attachment, revision, internal type, or stale site-specific
 * type can never be enabled accidentally.
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "This script must run through wp eval-file.\n" );
	exit( 1 );
}

require_once ABSPATH . 'wp-admin/includes/plugin.php';

if ( ! is_plugin_active( 'previewshare/previewshare.php' ) ) {
	fwrite( STDERR, "PreviewShare must be installed and active before configuration.\n" );
	exit( 1 );
}

$configured_types = getenv( 'STACK_PREVIEWSHARE_POST_TYPES' );
if ( false === $configured_types || '' === trim( $configured_types ) ) {
	$configured_types = 'post,page';
}

$ttl_hours = getenv( 'STACK_PREVIEWSHARE_DEFAULT_TTL_HOURS' );
if ( false === $ttl_hours || '' === trim( $ttl_hours ) ) {
	$ttl_hours = '6';
}

if ( ! preg_match( '/^\d+$/', $ttl_hours ) ) {
	fwrite( STDERR, "STACK_PREVIEWSHARE_DEFAULT_TTL_HOURS must be a non-negative integer.\n" );
	exit( 1 );
}

$available = array();
foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $slug => $post_type ) {
	if ( in_array( $slug, array( 'attachment', 'revision' ), true ) || ! is_post_type_viewable( $post_type ) ) {
		continue;
	}
	$available[ $slug ] = true;
}

$requested = array_values(
	array_unique(
		array_filter(
			array_map( 'sanitize_key', explode( ',', $configured_types ) )
		)
	)
);

if ( empty( $requested ) ) {
	fwrite( STDERR, "PreviewShare requires at least one configured post type.\n" );
	exit( 1 );
}

$unsupported = array_values( array_diff( $requested, array_keys( $available ) ) );
if ( ! empty( $unsupported ) ) {
	fwrite(
		STDERR,
		'PreviewShare post types must be public and viewable (attachments and revisions are never allowed): ' . implode( ', ', $unsupported ) . "\n"
	);
	exit( 1 );
}

update_option( 'previewshare_default_ttl_hours', (int) $ttl_hours, false );
update_option( 'previewshare_post_types', $requested, false );

echo wp_json_encode(
	array(
		'plugin'            => 'previewshare',
		'configured'         => true,
		'default_ttl_hours'  => (int) $ttl_hours,
		'post_types'         => $requested,
		'per_item_overrides' => true,
		'revocation'         => true,
	)
) . PHP_EOL;
