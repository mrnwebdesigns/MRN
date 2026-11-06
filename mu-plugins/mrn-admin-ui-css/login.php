<?php
/** Native login presentation; AME retains branding and WordPress retains authentication. */
defined( 'ABSPATH' ) || exit;

/** Reuse the same pilot boundary and emergency disable switch before authentication. */
function mrn_admin_login_enabled() {
	return function_exists( 'mrn_editor_experience_enabled' ) && mrn_editor_experience_enabled();
}

/** A display-only browser hint never selects a user or grants authentication. */
function mrn_admin_login_preference() {
	if ( is_user_logged_in() ) {
		return mrn_admin_appearance_preference();
	}
	$value = isset( $_COOKIE['mrn_login_appearance'] ) && is_string( $_COOKIE['mrn_login_appearance'] )
		? sanitize_key( wp_unslash( $_COOKIE['mrn_login_appearance'] ) ) : '';
	return in_array( $value, array( 'light', 'dark', 'system' ), true ) ? $value : 'system';
}

/** Remember only the authenticated account's palette, never identity or credentials. */
function mrn_admin_login_remember_appearance() {
	if ( ! mrn_admin_appearance_enabled() || ! current_user_can( 'read' ) || headers_sent() ) {
		return;
	}
	$value = mrn_admin_appearance_preference();
	if ( isset( $_COOKIE['mrn_login_appearance'] ) && $_COOKIE['mrn_login_appearance'] === $value ) {
		return;
	}
	$saved = setcookie( 'mrn_login_appearance', $value, array(
		'expires' => time() + YEAR_IN_SECONDS,
		'path' => defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/',
		'secure' => is_ssl(),
		'httponly' => true,
		'samesite' => 'Lax',
	) );
	if ( $saved ) {
		// admin_init and a preference update can run in the same AJAX request.
		$_COOKIE['mrn_login_appearance'] = $value;
	}
}
add_action( 'admin_init', 'mrn_admin_login_remember_appearance' );

// Reuse the existing nonce/capability-checked preference handler, including AJAX.
// An update to another user's metadata cannot change this browser's login hint.
foreach ( array( 'added_user_meta', 'updated_user_meta' ) as $hook ) {
	add_action( $hook, static function ( $meta_id, $user_id, $key ) {
		if ( 'mrn_admin_appearance' === $key && get_current_user_id() === (int) $user_id ) {
			mrn_admin_login_remember_appearance();
		}
	}, 10, 3 );
}

add_action( 'login_init', static function () {
	if ( ! mrn_admin_login_enabled() ) {
		return;
	}
	// The palette is present before the first stylesheet/paint. System is CSS-only.
	add_filter( 'language_attributes', static function ( $attributes ) {
		return $attributes . ' data-mrn-appearance="' . esc_attr( mrn_admin_login_preference() ) . '"';
	} );
	add_filter( 'login_body_class', static function ( $classes ) {
		$classes[] = 'mrn-login';
		return $classes;
	} );
	add_action( 'login_enqueue_scripts', static function () {
		wp_enqueue_style( 'mrn-admin-tokens', content_url( 'mu-plugins/mrn-admin-ui-css/mrn-admin-tokens.css' ), array( 'login' ), (string) filemtime( __DIR__ . '/mrn-admin-tokens.css' ) );
		wp_enqueue_style( 'mrn-admin-login', content_url( 'mu-plugins/mrn-admin-ui-css/login.css' ), array( 'mrn-admin-tokens' ), (string) filemtime( __DIR__ . '/login.css' ) );
	} );
} );
