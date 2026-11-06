<?php
// phpcs:ignoreFile -- Standalone public login boundary regression harness, no WordPress runtime writes.
define( 'ABSPATH', __DIR__ );
$actions = array();
$filters = array();
$enabled = true;
$authenticated = false;
function add_action( $name, $callback ) { $GLOBALS['actions'][ $name ][] = $callback; }
function add_filter( $name, $callback ) { $GLOBALS['filters'][ $name ][] = $callback; }
function mrn_editor_experience_enabled() { return $GLOBALS['enabled']; }
function is_user_logged_in() { return $GLOBALS['authenticated']; }
function mrn_admin_appearance_preference() { return 'dark'; }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) ); }
function wp_unslash( $value ) { return stripslashes( $value ); }
function esc_attr( $value ) { return htmlspecialchars( $value, ENT_QUOTES ); }
require __DIR__ . '/../login.php';
foreach ( array( 'light' => 'light', 'dark' => 'dark', 'system' => 'system', 'invalid' => 'system', '" onclick="x' => 'system' ) as $value => $expected ) {
	$_COOKIE['mrn_login_appearance'] = $value;
	if ( $expected !== mrn_admin_login_preference() ) { throw new RuntimeException( 'Unexpected display preference.' ); }
}
$_COOKIE['mrn_login_appearance'] = array( 'dark' );
if ( 'system' !== mrn_admin_login_preference() ) { throw new RuntimeException( 'Array cookie accepted.' ); }
unset( $_COOKIE['mrn_login_appearance'] );
if ( 'system' !== mrn_admin_login_preference() ) { throw new RuntimeException( 'Anonymous default differs.' ); }
$authenticated = true;
$_COOKIE['mrn_login_appearance'] = 'light';
if ( 'dark' !== mrn_admin_login_preference() ) { throw new RuntimeException( 'Cookie overrode the signed-in account.' ); }
$enabled = false;
$actions['login_init'][0]();
if ( $filters ) { throw new RuntimeException( 'Emergency switch did not preserve native login.' ); }
$enabled = true;
$actions['login_init'][0]();
if ( 'lang="en-US" data-mrn-appearance="dark"' !== $filters['language_attributes'][0]( 'lang="en-US"' ) ) { throw new RuntimeException( 'Opening HTML preference missing.' ); }
if ( array( 'login', 'mrn-login' ) !== $filters['login_body_class'][0]( array( 'login' ) ) ) { throw new RuntimeException( 'Native body classes not preserved.' ); }
echo "PASS: anonymous fallback, untrusted cookie shapes, account precedence, first-paint attributes and emergency disable.\n";
