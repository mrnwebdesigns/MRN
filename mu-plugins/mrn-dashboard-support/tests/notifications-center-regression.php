<?php
// phpcs:ignoreFile -- Standalone WordPress stub harness for the notice-channel contract.
/**
 * Regression coverage for Notifications Center notice capture and shared toolbar output.
 *
 * @package mrn-dashboard-support
 */

define( 'ABSPATH', __DIR__ );

$GLOBALS['mrn_notifications_test_actions'] = array();
$GLOBALS['mrn_notifications_test_meta']    = array();

function add_action( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['mrn_notifications_test_actions'][ $hook_name ][ $priority ][] = $callback;
	return true;
}

function add_filter( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
	return true;
}

function do_action( $hook_name ) {
	if ( empty( $GLOBALS['mrn_notifications_test_actions'][ $hook_name ] ) ) {
		return;
	}

	ksort( $GLOBALS['mrn_notifications_test_actions'][ $hook_name ] );
	foreach ( $GLOBALS['mrn_notifications_test_actions'][ $hook_name ] as $callbacks ) {
		foreach ( $callbacks as $callback ) {
			call_user_func( $callback );
		}
	}
}

function apply_filters( $hook_name, $value ) {
	return $value;
}

function is_admin() {
	return true;
}

function is_multisite() {
	return false;
}

function is_network_admin() {
	return false;
}

function current_user_can( $capability ) {
	return 'manage_options' === $capability;
}

function is_user_logged_in() {
	return true;
}

function get_current_user_id() {
	return 42;
}

function get_user_meta( $user_id, $key, $single = false ) {
	return isset( $GLOBALS['mrn_notifications_test_meta'][ $key ] )
		? $GLOBALS['mrn_notifications_test_meta'][ $key ]
		: '';
}

function update_user_meta( $user_id, $key, $value ) {
	$GLOBALS['mrn_notifications_test_meta'][ $key ] = $value;
}

function sanitize_key( $value ) {
	return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $value ) );
}

function absint( $value ) {
	return abs( (int) $value );
}

function wp_strip_all_tags( $value ) {
	return strip_tags( $value );
}

function esc_url_raw( $value ) {
	return (string) $value;
}

require dirname( __DIR__ ) . '/mrn-dashboard-support.php';

mrn_dashboard_support_register_admin_notice_capture();

if ( empty( $GLOBALS['mrn_notifications_test_actions']['admin_notices'] ) ) {
	throw new RuntimeException( 'Standard admin notice capture was not registered.' );
}

if ( ! empty( $GLOBALS['mrn_notifications_test_actions']['all_admin_notices'] ) ) {
	throw new RuntimeException( 'Notifications Center must not capture all_admin_notices.' );
}

add_action(
	'all_admin_notices',
	static function () {
		echo '<div class="mrn-sticky-save-bar">Toolbar actions</div>';
	},
	20
);

ob_start();
do_action( 'all_admin_notices' );
$toolbar_markup = ob_get_clean();

if ( false === strpos( $toolbar_markup, 'mrn-sticky-save-bar' ) ) {
	throw new RuntimeException( 'Universal Sticky Bar markup was swallowed from all_admin_notices.' );
}

add_action(
	'admin_notices',
	static function () {
		echo '<div class="notice notice-warning"><p>Plugin warning for the administrator.</p></div>';
	},
	10
);

ob_start();
do_action( 'admin_notices' );
$notice_markup = ob_get_clean();

if ( '' !== $notice_markup ) {
	throw new RuntimeException( 'Captured standard admin notices leaked into the response.' );
}

$captured = mrn_dashboard_support_get_captured_notifications();
if ( 1 !== count( $captured ) || 'Plugin warning for the administrator.' !== $captured[0]['message'] ) {
	throw new RuntimeException( 'A standard plugin notice was not captured exactly once.' );
}

echo "Notifications Center regression tests passed.\n";
