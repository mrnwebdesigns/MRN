<?php
/**
 * Stable, site-owned release loader. Installed once in the child functions.php.
 *
 * Old public assets stay unchanged; code/manifest live in private releases.
 * The deployment installer supplies the private state path, never user input.
 *
 * @package MRNSiteRelease
 */

defined( 'ABSPATH' ) || exit;

$mrn_release_state_root = '__MRN_STATE_PATH__';
$mrn_release_pointer    = $mrn_release_state_root . '/current.json';
// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Private atomic deployment pointer.
$mrn_release_selection = json_decode( file_get_contents( $mrn_release_pointer ), true );
if ( ! is_array( $mrn_release_selection ) || 1 !== (int) ( $mrn_release_selection['schema'] ?? 0 ) ) {
	throw new RuntimeException( 'MRN release pointer is invalid.' );
}
$mrn_release_id   = $mrn_release_selection['release_id'] ?? '';
$mrn_release_slug = $mrn_release_selection['slug'] ?? '';
if ( ! is_string( $mrn_release_id ) || ! preg_match( '/^[a-f0-9]{64}$/', $mrn_release_id ) || get_stylesheet() !== $mrn_release_slug ) {
	throw new RuntimeException( 'MRN release identity does not match this child theme.' );
}
$mrn_release_directory = $mrn_release_state_root . '/releases/' . $mrn_release_id . '/theme';
$mrn_release_realpath  = realpath( $mrn_release_directory );
if ( $mrn_release_realpath !== $mrn_release_directory || ! is_readable( $mrn_release_directory . '/functions.php' ) ) {
	throw new RuntimeException( 'MRN release directory is unavailable or aliased.' );
}

// Capture a physical immutable path once. A later pointer change cannot mix
// this request's templates, includes, or manifest with another release.
add_filter(
	'stylesheet_directory',
	static function ( $directory, $stylesheet ) use ( $mrn_release_directory, $mrn_release_slug ) {
		return $stylesheet === $mrn_release_slug ? $mrn_release_directory : $directory;
	},
	PHP_INT_MAX,
	2
);

$mrn_release_public_path = $mrn_release_selection['public_path'] ?? null;
if ( null !== $mrn_release_public_path ) {
	$mrn_release_pattern = '~^mrn-assets/' . preg_quote( $mrn_release_slug, '~' ) . '/[a-f0-9]{64}$~';
	if ( ! is_string( $mrn_release_public_path ) || ! preg_match( $mrn_release_pattern, $mrn_release_public_path ) ) {
		throw new RuntimeException( 'MRN release asset path is invalid.' );
	}
	add_filter(
		'stylesheet_directory_uri',
		static function ( $uri, $stylesheet ) use ( $mrn_release_public_path, $mrn_release_slug ) {
			return $stylesheet === $mrn_release_slug ? content_url( '/' . $mrn_release_public_path ) : $uri;
		},
		PHP_INT_MAX,
		2
	);
}

// WordPress initializes template globals before loading child functions.php.
// Refresh only these request-local paths after installing the directory filter.
wp_set_template_globals();
if ( ! headers_sent() ) {
	header( 'X-MRN-Site-Release: ' . $mrn_release_id );
}
require $mrn_release_directory . '/functions.php';
unset( $mrn_release_selection, $mrn_release_directory, $mrn_release_realpath, $mrn_release_pointer,
	$mrn_release_state_root, $mrn_release_slug, $mrn_release_id, $mrn_release_public_path, $mrn_release_pattern );
