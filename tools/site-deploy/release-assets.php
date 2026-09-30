<?php
/**
 * Build-owned immutable child-theme asset URL adapter.
 *
 * Included from the child theme only in a qualified release artifact.
 *
 * @package MRNSiteRelease
 */

defined( 'ABSPATH' ) || exit;

/**
 * Resolve an enqueued child asset against this code generation's manifest.
 *
 * @param string $src Original WordPress asset URL.
 * @return string
 */
function mrn_site_release_asset_url( $src ) {
	static $manifest = null;
	if ( null === $manifest ) {
		$manifest = json_decode( file_get_contents( __DIR__ . '/mrn-assets.json' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local build artifact, read once per request.
	}
	if ( ! is_array( $manifest ) || 1 !== (int) ( $manifest['schema'] ?? 0 ) ) {
		return $src;
	}
	$source_path = wp_parse_url( $src, PHP_URL_PATH );
	$theme_path  = wp_parse_url( get_stylesheet_directory_uri(), PHP_URL_PATH );
	$source_host = wp_parse_url( $src, PHP_URL_HOST );
	$theme_host  = wp_parse_url( get_stylesheet_directory_uri(), PHP_URL_HOST );
	if ( ! is_string( $source_path ) || ! is_string( $theme_path ) || $source_host !== $theme_host ) {
		return $src;
	}
	$prefix = trailingslashit( $theme_path );
	if ( 0 !== strpos( $source_path, $prefix ) ) {
		return $src;
	}
	$name  = rawurldecode( substr( $source_path, strlen( $prefix ) ) );
	$asset = $manifest['assets'][ $name ] ?? null;
	if ( ! is_array( $asset ) || empty( $asset['file'] ) ) {
		return $src;
	}
	return content_url( '/' . $manifest['public_path'] . '/' . $asset['file'] );
}

add_filter( 'style_loader_src', 'mrn_site_release_asset_url', PHP_INT_MAX );
add_filter( 'script_loader_src', 'mrn_site_release_asset_url', PHP_INT_MAX );
