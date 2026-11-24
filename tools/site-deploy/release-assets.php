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
 * Read the immutable asset manifest once per request.
 *
 * @return array<string, mixed>
 */
function mrn_site_release_asset_manifest() {
	static $manifest = null;
	if ( null === $manifest ) {
		$manifest = json_decode( file_get_contents( __DIR__ . '/mrn-assets.json' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local build artifact, read once per request.
	}
	if ( ! is_array( $manifest ) || 1 !== (int) ( $manifest['schema'] ?? 0 ) || empty( $manifest['assets'] ) || empty( $manifest['public_path'] ) ) {
		throw new RuntimeException( 'The release asset manifest is unavailable.' );
	}
	return $manifest;
}

/**
 * Check an opt-in stylesheet's reviewed WordPress body context.
 *
 * A site still owns its route, dependency and interaction qualification. This
 * helper does not infer component absence from initial browser coverage.
 *
 * @param string   $name Logical stylesheet name.
 * @param string[] $body_classes Current WordPress body classes.
 * @return bool
 */
function mrn_site_release_stylesheet_matches_body( $name, array $body_classes ) {
	$manifest = mrn_site_release_asset_manifest();
	$context  = $manifest['stylesheet_contexts'][ $name ] ?? null;
	if ( empty( $manifest['assets'][ $name ] ) || ! is_array( $context )
		|| empty( $context['required_body_classes'] ) || ! is_array( $context['required_body_classes'] )
		|| empty( $context['absent_body_classes'] ) || ! is_array( $context['absent_body_classes'] ) ) {
		return false;
	}
	return ! array_diff( $context['required_body_classes'], $body_classes )
		&& ! array_intersect( $context['absent_body_classes'], $body_classes );
}

/**
 * Resolve an enqueued child asset against this code generation's manifest.
 *
 * @param string $src Original WordPress asset URL.
 * @return string
 */
function mrn_site_release_asset_url( $src ) {
	$manifest = mrn_site_release_asset_manifest();
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
		throw new RuntimeException( 'An enqueued child asset is absent from the release manifest.' );
	}
	return content_url( '/' . $manifest['public_path'] . '/' . $asset['file'] );
}

add_filter( 'style_loader_src', 'mrn_site_release_asset_url', PHP_INT_MAX );
add_filter( 'script_loader_src', 'mrn_site_release_asset_url', PHP_INT_MAX );

/**
 * Preserve immutable child assets when a host optimizer rewrites queued handles.
 *
 * @param string[]    $excluded Existing provider exclusions.
 * @param object|null $registry WordPress scripts or styles registry.
 * @return string[]
 */
function mrn_site_release_optimizer_exclusions( $excluded, $registry ) {
	$excluded = is_array( $excluded ) ? $excluded : array();
	$manifest = mrn_site_release_asset_manifest();
	$base     = wp_parse_url( trailingslashit( get_stylesheet_directory_uri() ) );
	if ( ! is_array( $base ) || empty( $base['host'] ) || empty( $base['path'] ) ) {
		throw new RuntimeException( 'Immutable child assets require an absolute theme URL.' );
	}
	foreach ( $registry->registered ?? array() as $handle => $asset ) {
		if ( ! isset( $asset->src ) || ! is_string( $asset->src ) ) {
			continue;
		}
		$url = wp_parse_url( $asset->src );
		if ( ( $url['host'] ?? null ) !== $base['host']
			|| 0 !== strpos( $url['path'] ?? '', $base['path'] ) ) {
			continue;
		}
		$name = rawurldecode( substr( $url['path'], strlen( $base['path'] ) ) );
		if ( isset( $manifest['assets'][ $name ] ) ) {
			$excluded[] = $handle;
		}
	}
	return array_values( array_unique( $excluded ) );
}

/**
 * Exclude build-owned CSS from native runtime minification and combination.
 *
 * @param string[] $excluded Existing provider exclusions.
 * @return string[]
 */
function mrn_site_release_style_exclusions( $excluded ) {
	global $wp_styles;
	return mrn_site_release_optimizer_exclusions( $excluded, $wp_styles );
}

/**
 * Exclude build-owned scripts from native runtime minification and combination.
 *
 * @param string[] $excluded Existing provider exclusions.
 * @return string[]
 */
function mrn_site_release_script_exclusions( $excluded ) {
	global $wp_scripts;
	return mrn_site_release_optimizer_exclusions( $excluded, $wp_scripts );
}

// These native hooks have no effect on hosts without Speed Optimizer. Preserve
// parent/plugin assets and HTML-cache behavior; only this manifest owns builds.
add_filter( 'sgo_css_minify_exclude', 'mrn_site_release_style_exclusions', PHP_INT_MAX );
add_filter( 'sgo_css_combine_exclude', 'mrn_site_release_style_exclusions', PHP_INT_MAX );
add_filter( 'sgo_js_minify_exclude', 'mrn_site_release_script_exclusions', PHP_INT_MAX );
add_filter( 'sgo_javascript_combine_exclude', 'mrn_site_release_script_exclusions', PHP_INT_MAX );
