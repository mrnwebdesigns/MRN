<?php
/**
 * Standalone tests for the safety-critical disable/enable plugin logic.
 *
 * Verifies: (1) disabling flips the active_plugins option directly rather
 * than calling deactivate_plugin() — proven here by never defining
 * deactivate_plugin() at all, so any code path that called it would fatal
 * with "call to undefined function" rather than silently passing; (2) the
 * flip is trivially reversible; (3) a slug with no matching active entry is
 * a no-op, not an error.
 *
 * @package MRN_Recovery_Agent
 */

define( 'ABSPATH', __DIR__ );

$GLOBALS['__mrn_test_options'] = array(
	'active_plugins' => array(
		'broken-plugin/broken-plugin.php',
		'other-plugin/other-plugin.php',
	),
);

/**
 * Read a stubbed option value.
 *
 * @param string $option  Option name.
 * @param mixed  $default Default value.
 * @return mixed
 */
function get_option( $option, $default = false ) {
	return $GLOBALS['__mrn_test_options'][ $option ] ?? $default;
}

/**
 * Write a stubbed option value.
 *
 * @param string $option   Option name.
 * @param mixed  $value    New value.
 * @param mixed  $autoload Unused; present only to match get_option()'s signature.
 * @return bool
 */
function update_option( $option, $value, $autoload = null ) {
	$GLOBALS['__mrn_test_options'][ $option ] = $value;
	return true;
}

/**
 * Stub: this test's site is always single-site.
 *
 * @return bool
 */
function is_multisite() {
	return false;
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
 * Stub for the plugin's top-level add_action( 'rest_api_init', ... ) call.
 * Deliberately a no-op — this test targets the plugin/disable logic
 * directly, not REST route registration, so the callback never needs to
 * actually fire.
 *
 * @return void
 */
function add_action() {}

// Intentionally NOT defined: deactivate_plugin(), activate_plugin(),
// get_site_option(), update_site_option(). If mrn_recovery_agent_disable_plugin()
// or mrn_recovery_agent_enable_plugin() ever calls any of these, this script
// fatals with "Call to undefined function" — that IS the test for the
// bypass-the-hook-chain requirement.

require dirname( __DIR__ ) . '/mrn-recovery-agent.php';

// --- disable: matching active plugin is removed ---
$result = mrn_recovery_agent_disable_plugin( 'broken-plugin', 'test' );
if ( empty( $result['ok'] ) ) {
	throw new RuntimeException( 'Expected disable_plugin to report ok=true.' );
}
$active = get_option( 'active_plugins' );
if ( in_array( 'broken-plugin/broken-plugin.php', $active, true ) ) {
	throw new RuntimeException( 'broken-plugin was not removed from active_plugins.' );
}
if ( ! in_array( 'other-plugin/other-plugin.php', $active, true ) ) {
	throw new RuntimeException( 'other-plugin was incorrectly removed from active_plugins.' );
}

// --- disable: no matching active plugin is a no-op, not an error ---
$result = mrn_recovery_agent_disable_plugin( 'not-installed', 'test' );
if ( empty( $result['ok'] ) ) {
	throw new RuntimeException( 'Expected no-op disable_plugin to still report ok=true.' );
}

// --- enable: re-adds the previously disabled entry ---
$result = mrn_recovery_agent_enable_plugin( 'broken-plugin' );
if ( empty( $result['ok'] ) ) {
	throw new RuntimeException( 'Expected enable_plugin to report ok=true.' );
}
$active = get_option( 'active_plugins' );
if ( ! in_array( 'broken-plugin/broken-plugin.php', $active, true ) ) {
	throw new RuntimeException( 'broken-plugin was not restored to active_plugins.' );
}

echo "disable-plugin-action: OK\n";
