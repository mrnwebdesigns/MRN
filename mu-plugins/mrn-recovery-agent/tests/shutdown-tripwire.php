<?php
/**
 * Standalone tests for the shutdown-tripwire's scoping logic.
 *
 * PHP's error_get_last() is a real builtin that cannot be overridden from
 * userland, so the full mrn_recovery_agent_shutdown_tripwire() dispatch
 * (which reads it directly) is not unit-testable here — that path is
 * covered by the plan's end-to-end verification step (deliberately
 * injecting a real fatal error on a disposable staging site). What IS
 * unit-testable, and is exactly where the "narrow scope, not every plugin
 * fatal" decision lives, is the pure logic this file exercises directly:
 * resolving a slug from a file path, and matching it against the pending-
 * update marker.
 *
 * @package MRN_Recovery_Agent
 */

define( 'ABSPATH', __DIR__ );
define( 'WP_PLUGIN_DIR', '/var/www/html/wp-content/plugins' );

$GLOBALS['__mrn_test_transients'] = array();

/**
 * Read a stubbed transient value.
 *
 * @param string $key Transient key.
 * @return mixed
 */
function get_transient( $key ) {
	return $GLOBALS['__mrn_test_transients'][ $key ] ?? false;
}

/**
 * Write a stubbed transient value.
 *
 * @param string $key        Transient key.
 * @param mixed  $value      Value to store.
 * @param int    $expiration Unused; present only to match the real signature.
 * @return bool
 */
function set_transient( $key, $value, $expiration = 0 ) {
	$GLOBALS['__mrn_test_transients'][ $key ] = $value;
	return true;
}

/**
 * Normalize path separators to forward slashes.
 *
 * @param string $path Raw path.
 * @return string
 */
function wp_normalize_path( $path ) {
	return str_replace( '\\', '/', (string) $path );
}

/**
 * Ensure a path ends with a single trailing slash.
 *
 * @param string $path Raw path.
 * @return string
 */
function trailingslashit( $path ) {
	return rtrim( (string) $path, '/' ) . '/';
}

/**
 * Strip a raw filename/slug down to safe characters.
 *
 * @param string $name Raw filename/slug.
 * @return string
 */
function sanitize_file_name( $name ) {
	return preg_replace( '/[^a-zA-Z0-9._-]/', '', (string) $name );
}

/**
 * Stub: this test never seeds real options, so always return the default.
 *
 * @param string $option  Option name.
 * @param mixed  $default Default value.
 * @return mixed
 */
function get_option( $option, $default = false ) {
	return $default;
}

/**
 * Stub: this test does not assert on option writes.
 *
 * @return bool
 */
function update_option() {
	return true;
}

/**
 * Stub for the plugin's top-level add_action( 'rest_api_init', ... ) call.
 * Deliberately a no-op — this test targets slug-resolution and
 * pending-update-marker logic directly, not REST route registration.
 *
 * @return void
 */
function add_action() {}

require dirname( __DIR__ ) . '/mrn-recovery-agent.php';

// --- slug resolution: file under the plugins dir resolves its folder ---
$slug = mrn_recovery_agent_slug_from_error_file( '/var/www/html/wp-content/plugins/broken-plugin/broken-plugin.php' );
if ( 'broken-plugin' !== $slug ) {
	throw new RuntimeException( 'Expected \'broken-plugin\', got \'' . $slug . '\'.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message in a CLI test script, never rendered to a browser.
}

// --- slug resolution: file outside the plugins dir resolves to '' ---
$slug = mrn_recovery_agent_slug_from_error_file( '/var/www/html/wp-content/themes/some-theme/functions.php' );
if ( '' !== $slug ) {
	throw new RuntimeException( 'Expected empty string for a non-plugin file, got \'' . $slug . '\'.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message in a CLI test script, never rendered to a browser.
}

// --- pending-update marker: unset by default ---
if ( '' !== mrn_recovery_agent_pending_update_slug() ) {
	throw new RuntimeException( 'Expected no pending-update marker by default.' );
}

// --- pending-update marker: matches what was set via /mark-pending's handler ---
set_transient( 'mrn_recovery_agent_pending_update', 'broken-plugin', 300 );
if ( 'broken-plugin' !== mrn_recovery_agent_pending_update_slug() ) {
	throw new RuntimeException( 'Pending-update marker did not round-trip.' );
}

echo "shutdown-tripwire: OK\n";
