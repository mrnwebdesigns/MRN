<?php
// phpcs:ignoreFile -- Standalone CLI regression script that intentionally uses direct filesystem helpers and assertions.
/**
 * Regression coverage for configurable login URL protection.
 */

declare( strict_types=1 );

$mrn_test_tmp_dir = rtrim( sys_get_temp_dir(), DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR . 'mrn-public-security-hardening-' . bin2hex( random_bytes( 4 ) );

if ( ! mkdir( $mrn_test_tmp_dir, 0777, true ) && ! is_dir( $mrn_test_tmp_dir ) ) {
	fwrite( STDERR, "FAIL: unable to create the temporary test directory.\n" );
	exit( 1 );
}

file_put_contents(
	$mrn_test_tmp_dir . DIRECTORY_SEPARATOR . 'wp-login.php',
	<<<'PHP'
<?php
$GLOBALS['mrn_test_login_screen_loaded'] = true;
PHP
);

define( 'ABSPATH', $mrn_test_tmp_dir . DIRECTORY_SEPARATOR );
define( 'OBJECT', 'OBJECT' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'YEAR_IN_SECONDS', 31536000 );

$GLOBALS['mrn_test_state'] = array(
		'options'            => array(
			'siteurl'       => 'https://example.test/blog',
			'home'          => 'https://example.test/blog',
			'active_plugins' => array(),
			'active_sitewide_plugins' => array(),
			'rewrite_rules' => array(),
	),
	'filters'            => array(),
	'capabilities'       => array(
		'manage_options'         => true,
		'manage_network_options' => true,
	),
	'page_paths'         => array(),
	'url_to_postid'      => array(),
	'settings_errors'    => array(),
	'transients'         => array(),
	'redirects'          => array(),
	'nonce_checks'       => array(),
	'current_user_can'   => array(),
	'login_screen_loaded' => false,
);

function mrn_test_assert( $condition, $message ) {
	if ( $condition ) {
		return;
	}

	fwrite( STDERR, 'FAIL: ' . $message . "\n" );
	exit( 1 );
}

function mrn_test_same( $expected, $actual, $message ) {
	if ( $expected === $actual ) {
		return;
	}

	fwrite(
		STDERR,
		'FAIL: ' . $message . "\nExpected: " . var_export( $expected, true ) . "\nActual: " . var_export( $actual, true ) . "\n"
	);
	exit( 1 );
}

function mrn_test_contains( $needle, $haystack, $message ) {
	if ( is_string( $haystack ) && false !== strpos( $haystack, $needle ) ) {
		return;
	}

	fwrite( STDERR, 'FAIL: ' . $message . "\n" );
	exit( 1 );
}

function mrn_test_reset_notices() {
	$GLOBALS['mrn_test_state']['settings_errors'] = array();
	$GLOBALS['mrn_test_state']['transients']      = array();
	$GLOBALS['mrn_test_state']['redirects']       = array();
	$GLOBALS['mrn_test_state']['nonce_checks']    = array();
	$GLOBALS['mrn_test_state']['wp_die']          = null;
}

function mrn_test_cleanup_directory( $directory ) {
	if ( ! is_dir( $directory ) ) {
		return;
	}

	$items = scandir( $directory );

	if ( false === $items ) {
		return;
	}

	foreach ( $items as $item ) {
		if ( '.' === $item || '..' === $item ) {
			continue;
		}

		$path = $directory . DIRECTORY_SEPARATOR . $item;

		if ( is_dir( $path ) ) {
			mrn_test_cleanup_directory( $path );
			continue;
		}

		@unlink( $path );
	}

	@rmdir( $directory );
}

register_shutdown_function(
	static function () use ( $mrn_test_tmp_dir ) {
		mrn_test_cleanup_directory( $mrn_test_tmp_dir );
	}
);

function mrn_test_build_url( $base, $path = '' ) {
	$base = rtrim( (string) $base, '/' );
	$path = (string) $path;

	if ( '' === $path || '/' === $path ) {
		return $base . '/';
	}

	return $base . '/' . ltrim( $path, '/' );
}

function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['mrn_test_state']['filters'][ $hook ][] = array(
		'callback'      => $callback,
		'priority'      => (int) $priority,
		'accepted_args' => (int) $accepted_args,
	);

	return true;
}

function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	return add_filter( $hook, $callback, $priority, $accepted_args );
}

function apply_filters( $hook, $value ) {
	$args = array_slice( func_get_args(), 2 );

	if ( empty( $GLOBALS['mrn_test_state']['filters'][ $hook ] ) ) {
		return $value;
	}

	usort(
		$GLOBALS['mrn_test_state']['filters'][ $hook ],
		static function ( $left, $right ) {
			return $left['priority'] <=> $right['priority'];
		}
	);

	foreach ( $GLOBALS['mrn_test_state']['filters'][ $hook ] as $entry ) {
		$call_args = array_merge( array( $value ), $args );
		$call_args = array_slice( $call_args, 0, $entry['accepted_args'] );
		$value     = call_user_func_array( $entry['callback'], $call_args );
	}

	return $value;
}

function current_user_can( $capability ) {
	$GLOBALS['mrn_test_state']['current_user_can'][] = $capability;

	return ! empty( $GLOBALS['mrn_test_state']['capabilities'][ $capability ] );
}

function is_network_admin() {
	return false;
}

function is_admin() {
	return false;
}

function get_option( $option, $default = false ) {
	if ( array_key_exists( $option, $GLOBALS['mrn_test_state']['options'] ) ) {
		return $GLOBALS['mrn_test_state']['options'][ $option ];
	}

	return $default;
}

function get_site_option( $option, $default = false ) {
	return get_option( $option, $default );
}

function update_option( $option, $value ) {
	$GLOBALS['mrn_test_state']['options'][ $option ] = $value;

	return true;
}

function delete_option( $option ) {
	unset( $GLOBALS['mrn_test_state']['options'][ $option ] );

	return true;
}

function sanitize_key( $key ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
}

function sanitize_title( $title ) {
	$title = strtolower( (string) $title );
	$title = preg_replace( '/[^a-z0-9]+/', '-', $title );

	return trim( (string) $title, '-' );
}

function sanitize_text_field( $text ) {
	$text = (string) $text;
	$text = strip_tags( $text );
	$text = preg_replace( '/[\r\n\t ]+/', ' ', $text );

	return trim( $text );
}

function wp_unslash( $value ) {
	return $value;
}

function trailingslashit( $value ) {
	return rtrim( (string) $value, '/' ) . '/';
}

function untrailingslashit( $value ) {
	return rtrim( (string) $value, '/' );
}

function home_url( $path = '', $scheme = null ) {
	unset( $scheme );

	return mrn_test_build_url( $GLOBALS['mrn_test_state']['options']['home'], $path );
}

function site_url( $path = '', $scheme = null ) {
	unset( $scheme );

	return mrn_test_build_url( $GLOBALS['mrn_test_state']['options']['siteurl'], $path );
}

function admin_url( $path = '', $scheme = 'admin' ) {
	unset( $scheme );

	return mrn_test_build_url( $GLOBALS['mrn_test_state']['options']['siteurl'] . '/wp-admin', $path );
}

function menu_page_url( $slug, $echo = true ) {
	unset( $echo );

	return mrn_test_build_url( $GLOBALS['mrn_test_state']['options']['siteurl'] . '/wp-admin', 'admin.php?page=' . $slug );
}

function wp_validate_redirect( $location, $fallback = '' ) {
	if ( ! is_string( $location ) || '' === trim( $location ) ) {
		return $fallback;
	}

	$parts = parse_url( $location );

	if ( false === $parts ) {
		return $fallback;
	}

	if ( isset( $parts['scheme'] ) && ! in_array( $parts['scheme'], array( 'http', 'https' ), true ) ) {
		return $fallback;
	}

	if ( isset( $parts['host'] ) && 'example.test' !== $parts['host'] ) {
		return $fallback;
	}

	return $location;
}

function wp_safe_redirect( $location, $status = 302, $x_redirect_by = '' ) {
	$GLOBALS['mrn_test_state']['redirects'][] = array(
		'location'      => $location,
		'status'        => $status,
		'x_redirect_by'  => $x_redirect_by,
	);

	return true;
}

function check_admin_referer( $action, $query_arg = '_wpnonce' ) {
	$GLOBALS['mrn_test_state']['nonce_checks'][] = array(
		'action'    => $action,
		'query_arg' => $query_arg,
		'value'     => isset( $_POST[ $query_arg ] ) ? $_POST[ $query_arg ] : null,
	);

	if ( ! isset( $_POST[ $query_arg ] ) ) {
		throw new RuntimeException( 'Nonce check did not receive a value.' );
	}

	return 1;
}

function add_settings_error( $setting, $code, $message, $type = 'error' ) {
	$GLOBALS['mrn_test_state']['settings_errors'][] = array(
		'setting' => $setting,
		'code'    => $code,
		'message' => $message,
		'type'    => $type,
	);
}

function get_settings_errors( $setting = '', $sanitize = false ) {
	unset( $sanitize );

	if ( '' === $setting ) {
		return $GLOBALS['mrn_test_state']['settings_errors'];
	}

	return array_values(
		array_filter(
			$GLOBALS['mrn_test_state']['settings_errors'],
			static function ( $error ) use ( $setting ) {
				return isset( $error['setting'] ) && $setting === $error['setting'];
			}
		)
	);
}

function set_transient( $transient, $value, $expiration = 0 ) {
	unset( $expiration );

	$GLOBALS['mrn_test_state']['transients'][ $transient ] = $value;

	return true;
}

function get_transient( $transient ) {
	return $GLOBALS['mrn_test_state']['transients'][ $transient ] ?? false;
}

function add_query_arg( $args, $url = '' ) {
	if ( ! is_array( $args ) || empty( $args ) ) {
		return $url;
	}

	$fragment = '';

	if ( false !== strpos( (string) $url, '#' ) ) {
		list( $url, $fragment ) = explode( '#', (string) $url, 2 );
		$fragment               = '#' . $fragment;
	}

	$query = http_build_query( $args, '', '&', PHP_QUERY_RFC3986 );

	if ( '' === $url ) {
		$url = '?';
	} elseif ( false === strpos( $url, '?' ) ) {
		$url .= '?';
	} else {
		$url .= '&';
	}

	return $url . $query . $fragment;
}

function remove_query_arg( $key, $query = false ) {
	unset( $key, $query );

	return '';
}

function esc_html( $text ) {
	return (string) $text;
}

function esc_html__( $text, $domain = null ) {
	unset( $domain );

	return (string) $text;
}

function esc_attr( $text ) {
	return (string) $text;
}

function esc_attr__( $text, $domain = null ) {
	unset( $domain );

	return (string) $text;
}

function esc_textarea( $text ) {
	return (string) $text;
}

function wp_kses_post( $text ) {
	return (string) $text;
}

function __( $text, $domain = null ) {
	unset( $domain );

	return (string) $text;
}

function esc_html_e( $text, $domain = null ) {
	unset( $domain );

	echo (string) $text;
}

function is_email( $email ) {
	return false !== filter_var( $email, FILTER_VALIDATE_EMAIL );
}

function sanitize_email( $email ) {
	$email = trim( (string) $email );

	return false !== filter_var( $email, FILTER_VALIDATE_EMAIL ) ? $email : '';
}

function esc_url_raw( $url ) {
	return trim( (string) $url );
}

function absint( $value ) {
	return abs( (int) $value );
}

class WP_Error {
	public $errors = array();
	public $error_data = array();

	public function __construct( $code = '', $message = '', $data = '' ) {
		if ( '' !== $code ) {
			$this->errors[ $code ][] = $message;
			$this->error_data[ $code ] = $data;
		}
	}

	public function get_error_code() {
		return array_key_first( $this->errors );
	}

	public function get_error_message() {
		$code = $this->get_error_code();

		return $code ? $this->errors[ $code ][0] : '';
	}
}

function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}

class WP_Post {
	public $ID;
	public $post_status = 'publish';
	public $post_type = 'page';

	public function __construct( $id ) {
		$this->ID = (int) $id;
	}
}

function get_page_by_path( $path, $output = OBJECT, $post_type = 'page' ) {
	unset( $output, $post_type );

	$path = trim( (string) $path, '/' );

	if ( ! isset( $GLOBALS['mrn_test_state']['page_paths'][ $path ] ) ) {
		return null;
	}

	return new WP_Post( $GLOBALS['mrn_test_state']['page_paths'][ $path ] );
}

function get_post_types( $args = array(), $output = 'names' ) {
	unset( $args, $output );

	return array( 'page', 'post', 'product' );
}

function get_post_status( $post ) {
	if ( is_object( $post ) && isset( $post->post_status ) ) {
		return $post->post_status;
	}

	return 'publish';
}

function url_to_postid( $url ) {
	return $GLOBALS['mrn_test_state']['url_to_postid'][ (string) $url ] ?? 0;
}

require_once __DIR__ . '/../mrn-public-security-hardening.php';

add_filter(
	'mrn_public_security_log_login_slug_changes',
	static function ( $enabled, $event ) {
		unset( $enabled, $event );

		return false;
	},
	10,
	2
);

mrn_test_assert( 'uptimerobot-check' === mrn_public_security_get_uptime_robot_check_slug(), 'UptimeRobot slug default changed unexpectedly.' );
mrn_test_assert( true === mrn_public_security_author_archive_redirect_enabled(), 'Author archive redirect should remain enabled by default.' );
mrn_test_assert( true === mrn_public_security_rest_guard_enabled(), 'REST guard should remain enabled by default.' );
mrn_test_assert( true === mrn_public_security_oembed_strip_author_enabled(), 'oEmbed author stripping should remain enabled by default.' );

mrn_test_same( 'site-login', mrn_public_security_get_default_login_slug(), 'Default login slug should be site-login.' );
mrn_test_same( '/blog/site-login/', mrn_public_security_get_custom_login_path(), 'Default custom login path should include the site path root.' );
mrn_test_same( 'https://example.test/blog/site-login/', mrn_public_security_get_custom_login_url(), 'Default custom login URL should use the configured slug.' );

$GLOBALS['mrn_test_state']['filters']['mrn_public_security_login_slug_default'] = array();

add_filter(
	'mrn_public_security_login_slug_default',
	static function ( $slug ) {
		unset( $slug );

		return 'client-login';
	}
);

mrn_test_same( 'client-login', mrn_public_security_get_default_login_slug(), 'Login slug default override filter was ignored.' );

$GLOBALS['mrn_test_state']['filters']['mrn_public_security_login_slug_default'] = array();

$valid_cases = array(
	'team-login'      => 'team-login',
	' Team-Login '    => 'team-login',
	'client-login-2'  => 'client-login-2',
);

foreach ( $valid_cases as $candidate => $expected ) {
	$validated = mrn_public_security_validate_login_slug( $candidate );
	mrn_test_same( $expected, $validated, 'Expected valid slug ' . $candidate . ' to normalize and save.' );
}

$invalid_cases = array(
	''              => 'mrn_public_security_login_slug_empty',
	'foo bar'       => 'mrn_public_security_login_slug_invalid',
	'foo/bar'       => 'mrn_public_security_login_slug_invalid',
	'foo?bar'       => 'mrn_public_security_login_slug_invalid',
	'wp-login'      => 'mrn_public_security_login_slug_reserved',
	'wp-admin'      => 'mrn_public_security_login_slug_reserved',
	'admin'         => 'mrn_public_security_login_slug_reserved',
	'login'         => 'mrn_public_security_login_slug_reserved',
	'wp-json'       => 'mrn_public_security_login_slug_reserved',
	'confirm_admin_email' => 'mrn_public_security_login_slug_invalid',
);

foreach ( $invalid_cases as $candidate => $expected_code ) {
	$validated = mrn_public_security_validate_login_slug( $candidate );
	mrn_test_assert( is_wp_error( $validated ), 'Expected ' . $candidate . ' to be rejected.' );
	mrn_test_same( $expected_code, $validated->get_error_code(), 'Unexpected error code for ' . $candidate . '.' );
}

$GLOBALS['mrn_test_state']['url_to_postid'] = array(
	'https://example.test/blog/existing-post/' => 101,
);
$GLOBALS['mrn_test_state']['page_paths'] = array(
	'existing-page' => 202,
);
$GLOBALS['mrn_test_state']['options']['rewrite_rules'] = array(
	'^existing-rewrite/?$' => 'index.php?pagename=existing-rewrite',
);

mrn_test_assert( true === mrn_public_security_login_slug_conflicts_with_existing_route( 'existing-post' ), 'Post permalink conflict was not detected.' );
mrn_test_assert( true === mrn_public_security_login_slug_conflicts_with_existing_route( 'existing-page' ), 'Page path conflict was not detected.' );
mrn_test_assert( true === mrn_public_security_login_slug_conflicts_with_existing_route( 'existing-rewrite' ), 'Rewrite rule conflict was not detected.' );
mrn_test_assert( false === mrn_public_security_login_slug_conflicts_with_existing_route( 'available-slug' ), 'Available slug was reported as conflicting.' );

$GLOBALS['mrn_test_state']['url_to_postid'] = array();
$GLOBALS['mrn_test_state']['page_paths']    = array();
$GLOBALS['mrn_test_state']['options']['rewrite_rules'] = array();

$saved = mrn_public_security_process_login_slug_save( '  Team-Login  ' );
mrn_test_assert( ! is_wp_error( $saved ), 'Valid login slug was not saved.' );
mrn_test_same( array( 'changed' => true, 'slug' => 'team-login' ), $saved, 'Valid login slug save returned the wrong data.' );
mrn_test_same( 'team-login', get_option( mrn_public_security_get_login_slug_option_name() ), 'Login slug option was not stored.' );
mrn_test_same( 'team-login', mrn_public_security_get_login_slug(), 'Saved login slug was not used as the active slug.' );

$repeat = mrn_public_security_process_login_slug_save( 'team-login' );
mrn_test_assert( ! is_wp_error( $repeat ), 'Duplicate login slug save should be harmless.' );
mrn_test_same( array( 'changed' => false, 'slug' => 'team-login' ), $repeat, 'Duplicate login slug save should report no change.' );

mrn_test_same( '/blog/team-login/', mrn_public_security_get_custom_login_path(), 'Saved login path was not updated.' );
mrn_test_same( 'https://example.test/blog/team-login/', mrn_public_security_get_custom_login_url(), 'Saved login URL was not updated.' );

$GLOBALS['mrn_test_state']['options']['active_plugins'] = array( 'wps-hide-login/wps-hide-login.php' );
$login_conflict = mrn_public_security_get_active_login_conflict_plugin();
mrn_test_same( 'WPS Hide Login', $login_conflict['name'], 'Active login URL plugin was not detected.' );
mrn_test_same( false, mrn_public_security_is_custom_login_request( '/blog/team-login/' ), 'Custom route remained active during a plugin conflict.' );
mrn_test_same( false, mrn_public_security_should_block_default_login_request( '/blog/wp-login.php' ), 'Default endpoint was blocked during a plugin conflict.' );
mrn_test_same( 'https://example.test/blog/wp-login.php?action=logout', mrn_public_security_replace_wp_login_path_in_url( 'https://example.test/blog/wp-login.php?action=logout' ), 'Login URL was rewritten during a plugin conflict.' );
$conflict_state = mrn_public_security_get_login_slug_state();
mrn_test_same( 'plugin-conflict', $conflict_state['source'], 'Admin login state did not report the plugin conflict.' );

$GLOBALS['mrn_test_state']['options']['active_plugins']       = array();
$GLOBALS['mrn_test_state']['options']['active_sitewide_plugins'] = array( 'wps-hide-login/wps-hide-login.php' => 1 );
mrn_test_assert( null !== mrn_public_security_get_active_login_conflict_plugin(), 'Network-active login URL plugin was not detected.' );
$GLOBALS['mrn_test_state']['options']['active_sitewide_plugins'] = array();

$login_url = apply_filters(
	'login_url',
	site_url( 'wp-login.php?redirect_to=' . rawurlencode( 'https://example.test/blog/wp-admin/' ), 'login' ),
	'/blog/wp-admin/',
	false
);
mrn_test_contains( '/blog/team-login/', $login_url, 'login_url filter did not rewrite the login path.' );
mrn_test_contains( 'redirect_to=', $login_url, 'login_url filter dropped the redirect target.' );

$logout_url = apply_filters(
	'site_url',
	site_url( 'wp-login.php?action=logout', 'login' ),
	'wp-login.php?action=logout',
	'login',
	null
);
mrn_test_contains( '/blog/team-login/', $logout_url, 'site_url filter did not rewrite the logout path.' );
mrn_test_contains( 'action=logout', $logout_url, 'Logout action was not preserved.' );

$lost_password_url = apply_filters(
	'lostpassword_url',
	site_url( 'wp-login.php?action=lostpassword', 'login' ),
	'https://example.test/blog/wp-login.php?action=lostpassword'
);
mrn_test_contains( '/blog/team-login/', $lost_password_url, 'lostpassword_url filter did not rewrite the login path.' );

$register_url = apply_filters(
	'register_url',
	site_url( 'wp-login.php?action=register', 'login' )
);
mrn_test_contains( '/blog/team-login/', $register_url, 'register_url filter did not rewrite the login path.' );

$password_reset_message = apply_filters(
	'retrieve_password_message',
	"Password reset: https://example.test/blog/wp-login.php?action=rp&key=abc&login=user\n",
	'abc',
	'user',
	(object) array()
);
mrn_test_contains( '/blog/team-login/', $password_reset_message, 'Password reset email text did not use the custom login URL.' );

$request_uri = '/blog/team-login/?redirect_to=%2Fblog%2Fwp-admin%2F';
$_SERVER['REQUEST_URI'] = $request_uri;

mrn_test_assert( true === mrn_public_security_is_custom_login_request(), 'Custom login request was not detected.' );

$GLOBALS['mrn_test_login_screen_loaded'] = false;
$served = mrn_public_security_maybe_serve_custom_login_request( false );
mrn_test_assert( true === $served, 'Custom login route did not load the login screen.' );
mrn_test_assert( true === $GLOBALS['mrn_test_login_screen_loaded'], 'Custom login screen file was not required.' );

mrn_test_assert( true === mrn_public_security_should_block_default_login_request( '/blog/wp-login.php' ), 'Bare wp-login.php should be blocked.' );

$allowed_requests = array(
	array( 'action' => 'logout' ),
	array( 'action' => 'lostpassword' ),
	array( 'action' => 'resetpass' ),
	array( 'action' => 'rp' ),
	array( 'action' => 'register' ),
	array( 'action' => 'confirmaction' ),
	array( 'action' => 'confirm_admin_email' ),
	array( 'action' => 'retrievepassword' ),
	array( 'action' => 'postpass' ),
	array( 'checkemail' => 'confirm' ),
	array( 'loggedout' => 'true' ),
	array( 'reauth' => '1' ),
	array( 'interim-login' => '1' ),
);

foreach ( $allowed_requests as $request ) {
	mrn_test_assert(
		false === mrn_public_security_should_block_default_login_request( '/blog/wp-login.php', $request ),
		'Allowed login action was blocked: ' . json_encode( $request )
	);
}

$_SERVER['REQUEST_URI'] = '/blog/wp-login.php';
$blocked = mrn_public_security_block_default_login_request( true );
mrn_test_same(
	array(
		'status'  => 404,
		'message' => 'Not found.',
	),
	$blocked,
	'Default login block should return a generic 404 response.'
);

mrn_test_assert( false === strpos( $blocked['message'], 'team-login' ), 'Blocked login response leaked the custom slug.' );

mrn_test_reset_notices();
$_POST = array(
	'mrn_public_security_login_slug_nonce' => 'nonce-value',
	'redirect_to'                          => 'https://example.test/blog/wp-admin/admin.php?page=mrn-public-security-hardening',
	'mrn_public_security_login_slug'       => 'client-login',
);

$redirect_target = mrn_public_security_handle_login_slug_save( false );
mrn_test_same( 'https://example.test/blog/wp-admin/admin.php?page=mrn-public-security-hardening', $redirect_target, 'Valid login slug save redirected unexpectedly.' );
mrn_test_same( 'client-login', get_option( mrn_public_security_get_login_slug_option_name() ), 'Handler did not save the valid login slug.' );
mrn_test_same( 'manage_options', end( $GLOBALS['mrn_test_state']['current_user_can'] ), 'Handler did not check the admin capability.' );
mrn_test_same(
	array(
		'action'    => 'mrn_public_security_save_login_slug',
		'query_arg' => 'mrn_public_security_login_slug_nonce',
		'value'     => 'nonce-value',
	),
	end( $GLOBALS['mrn_test_state']['nonce_checks'] ),
	'Handler did not verify the nonce.'
);
$_notice = $GLOBALS['mrn_test_state']['settings_errors'][0] ?? null;
if ( ! is_array( $_notice ) ) {
	fwrite( STDERR, "FAIL: success notice was not recorded.\n" );
	exit( 1 );
}
mrn_test_same( 'mrn_public_security_login_slug_updated', $_notice['code'], 'Success notice was not recorded.' );
mrn_test_same( 'updated', $_notice['type'], 'Success notice type was not recorded.' );
mrn_test_same( $GLOBALS['mrn_test_state']['settings_errors'], get_transient( 'settings_errors' ), 'Success notice was not persisted for the redirected page.' );

mrn_test_reset_notices();
$_POST = array(
	'mrn_public_security_login_slug_nonce' => 'nonce-value',
	'redirect_to'                          => 'https://example.test/blog/wp-admin/admin.php?page=mrn-public-security-hardening',
	'mrn_public_security_login_slug'       => 'wp-login',
);

$redirect_target = mrn_public_security_handle_login_slug_save( false );
mrn_test_same( 'https://example.test/blog/wp-admin/admin.php?page=mrn-public-security-hardening', $redirect_target, 'Invalid login slug save redirected unexpectedly.' );
mrn_test_same( 'client-login', get_option( mrn_public_security_get_login_slug_option_name() ), 'Rejected login slug should not replace the saved option.' );
$_notice = $GLOBALS['mrn_test_state']['settings_errors'][0] ?? null;
if ( ! is_array( $_notice ) ) {
	fwrite( STDERR, "FAIL: rejected login slug notice was not recorded.\n" );
	exit( 1 );
}
mrn_test_same( 'mrn_public_security_login_slug_reserved', $_notice['code'], 'Rejected login slug notice was not recorded.' );
mrn_test_same( 'error', $_notice['type'], 'Rejected login slug notice type was not recorded.' );
mrn_test_same( $GLOBALS['mrn_test_state']['settings_errors'], get_transient( 'settings_errors' ), 'Rejected login slug notice was not persisted for the redirected page.' );

update_option( mrn_public_security_get_login_slug_option_name(), 'not a valid slug' );
mrn_test_same( 'site-login', mrn_public_security_get_login_slug(), 'Malformed stored login slug should fall back to the default.' );

$state = mrn_public_security_get_login_slug_state();
mrn_test_same( 'invalid', $state['source'], 'Malformed stored login slug should be reported as invalid.' );
mrn_test_same( 'site-login', $state['effective'], 'Malformed stored login slug should not change the effective slug.' );

$recovered = mrn_public_security_reset_login_slug_option();
mrn_test_same( array( 'changed' => true, 'slug' => 'site-login' ), $recovered, 'Recovery did not restore the default login slug.' );
mrn_test_assert( ! array_key_exists( mrn_public_security_get_login_slug_option_name(), $GLOBALS['mrn_test_state']['options'] ), 'Recovery did not remove the stored login slug option.' );
mrn_test_same( 'site-login', mrn_public_security_get_login_slug(), 'Recovery did not restore the active default login slug.' );

echo "PASS: login URL protection regression checks completed.\n";
