<?php
/**
 * Plugin Name: MRN Public Security Hardening
 * Description: Shared public hardening for MRN brochure/client sites.
 * Version: 0.3.0
 * Author: MRN
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'MRN_PUBLIC_SECURITY_HARDENING_VERSION' ) ) {
	define( 'MRN_PUBLIC_SECURITY_HARDENING_VERSION', '0.3.0' );
}

/**
 * Parse a URL with WordPress normalization when available.
 *
 * @param string $url       URL to parse.
 * @param int    $component Optional parse component.
 * @return mixed
 */
function mrn_public_security_parse_url( $url, $component = -1 ) {
	if ( function_exists( 'wp_parse_url' ) ) {
		return wp_parse_url( $url, $component );
	}

	return parse_url( $url, $component ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Fallback for very old WordPress installs without wp_parse_url().
}

/**
 * Get the stack-owned UptimeRobot health-check page slug.
 *
 * @return string
 */
function mrn_public_security_get_uptime_robot_check_slug() {
	$slug = apply_filters( 'mrn_public_security_uptime_robot_check_slug', 'uptimerobot-check' );

	if ( ! is_string( $slug ) ) {
		return 'uptimerobot-check';
	}

	$slug = sanitize_title( $slug );

	return '' !== $slug ? $slug : 'uptimerobot-check';
}

/**
 * Check whether the current request is for the stack health-check page.
 *
 * @return bool
 */
function mrn_public_security_is_uptime_robot_check_request() {
	return ! is_admin() && function_exists( 'is_page' ) && is_page( mrn_public_security_get_uptime_robot_check_slug() );
}

/**
 * Prevent the health-check page from being indexed or followed.
 *
 * @param array $robots Existing robots directives.
 * @return array
 */
function mrn_public_security_uptime_robot_check_robots( $robots ) {
	if ( ! is_array( $robots ) ) {
		$robots = array();
	}

	if ( mrn_public_security_is_uptime_robot_check_request() ) {
		unset( $robots['index'], $robots['follow'] );
		$robots['noindex']   = true;
		$robots['nofollow']  = true;
		$robots['noarchive'] = true;
	}

	return $robots;
}

/**
 * Add an HTTP robots directive for the health-check response.
 *
 * @param array $headers Existing response headers.
 * @return array
 */
function mrn_public_security_uptime_robot_check_headers( $headers ) {
	if ( mrn_public_security_is_uptime_robot_check_request() ) {
		$headers['X-Robots-Tag'] = 'noindex, nofollow, noarchive';
	}

	return $headers;
}
add_filter( 'wp_headers', 'mrn_public_security_uptime_robot_check_headers' );

/**
 * Keep compliant crawlers away from the health-check path.
 *
 * @param string $output Existing robots.txt content.
 * @return string
 */
function mrn_public_security_disallow_uptime_robot_check_crawling( $output ) {
	$directive = 'Disallow: /' . mrn_public_security_get_uptime_robot_check_slug();

	if ( false === strpos( $output, $directive ) ) {
		$output = rtrim( $output ) . "\n" . $directive . "\n";
	}

	return $output;
}
add_filter( 'robots_txt', 'mrn_public_security_disallow_uptime_robot_check_crawling' );

/**
 * Exclude the health-check page from the core WordPress page sitemap.
 *
 * @param array  $args      Sitemap query arguments.
 * @param string $post_type Sitemap post type.
 * @return array
 */
function mrn_public_security_exclude_uptime_robot_check_from_sitemap( $args, $post_type ) {
	if ( 'page' !== $post_type ) {
		return $args;
	}

	$page = get_page_by_path( mrn_public_security_get_uptime_robot_check_slug(), OBJECT, 'page' );

	if ( $page instanceof WP_Post ) {
		$excluded            = isset( $args['post__not_in'] ) && is_array( $args['post__not_in'] ) ? $args['post__not_in'] : array();
		$excluded[]          = $page->ID;
		$args['post__not_in'] = array_values( array_unique( array_map( 'absint', $excluded ) ) );
	}

	return $args;
}
add_filter( 'wp_sitemaps_posts_query_args', 'mrn_public_security_exclude_uptime_robot_check_from_sitemap', 10, 2 );

/**
 * Render legacy robots metadata when the WordPress robots API is unavailable.
 */
function mrn_public_security_render_uptime_robot_check_noindex_meta() {
	if ( mrn_public_security_is_uptime_robot_check_request() ) {
		echo '<meta name="robots" content="noindex, nofollow, noarchive" />' . "\n";
	}
}

if ( function_exists( 'wp_robots' ) ) {
	add_filter( 'wp_robots', 'mrn_public_security_uptime_robot_check_robots', 20 );
} else {
	add_action( 'wp_head', 'mrn_public_security_render_uptime_robot_check_noindex_meta', 1 );
}

/**
 * Check whether author archive redirects are enabled.
 *
 * @return bool
 */
function mrn_public_security_author_archive_redirect_enabled() {
	return (bool) apply_filters( 'mrn_public_security_author_archive_redirect_enabled', true );
}

/**
 * Check whether the current request is a public author archive request.
 *
 * @return bool
 */
function mrn_public_security_is_author_archive_request() {
	if ( is_admin() || ! function_exists( 'is_author' ) ) {
		return false;
	}

	return is_author();
}

/**
 * Redirect public author archives away from username-based archive URLs.
 */
function mrn_public_security_redirect_author_archives() {
	if ( ! mrn_public_security_author_archive_redirect_enabled() || ! mrn_public_security_is_author_archive_request() ) {
		return;
	}

	$target = apply_filters( 'mrn_public_security_author_archive_redirect_target', home_url( '/' ) );

	if ( ! is_string( $target ) || '' === trim( $target ) ) {
		$target = home_url( '/' );
	}

	$status = (int) apply_filters( 'mrn_public_security_author_archive_redirect_status', 301 );

	if ( $status < 300 || $status > 399 ) {
		$status = 301;
	}

	wp_safe_redirect( $target, $status, 'MRN Public Security Hardening' );
	exit;
}
add_action( 'template_redirect', 'mrn_public_security_redirect_author_archives', 1 );

/**
 * Check whether author archive noindex fallback is enabled.
 *
 * @return bool
 */
function mrn_public_security_author_archive_noindex_enabled() {
	$default = ! mrn_public_security_author_archive_redirect_enabled();

	return (bool) apply_filters( 'mrn_public_security_author_archive_noindex_enabled', $default );
}

/**
 * Check whether the current author archive request should receive noindex.
 *
 * @return bool
 */
function mrn_public_security_should_noindex_author_archive() {
	if ( mrn_public_security_author_archive_redirect_enabled() || ! mrn_public_security_is_author_archive_request() ) {
		return false;
	}

	return mrn_public_security_author_archive_noindex_enabled();
}

/**
 * Add robots directives for author archives when redirecting is disabled.
 *
 * @param array $robots Existing robots directives.
 * @return array
 */
function mrn_public_security_author_archive_robots( $robots ) {
	if ( ! is_array( $robots ) ) {
		$robots = array();
	}

	if ( mrn_public_security_should_noindex_author_archive() ) {
		$robots['noindex'] = true;
		$robots['follow']  = true;
	}

	return $robots;
}

/**
 * Render legacy noindex meta when the WP robots API is unavailable.
 */
function mrn_public_security_render_author_archive_noindex_meta() {
	if ( ! mrn_public_security_should_noindex_author_archive() ) {
		return;
	}

	echo '<meta name="robots" content="noindex, follow" />' . "\n";
}

if ( function_exists( 'wp_robots' ) ) {
	add_filter( 'wp_robots', 'mrn_public_security_author_archive_robots' );
} else {
	add_action( 'wp_head', 'mrn_public_security_render_author_archive_noindex_meta', 1 );
}

/**
 * Strip author data from public oEmbed response payloads.
 *
 * @param array $data   oEmbed response data.
 * @param mixed $post   Embedded post object.
 * @param int   $width  Requested width.
 * @param int   $height Requested height.
 * @return array
 */
function mrn_public_security_strip_oembed_author_data( $data, $post = null, $width = 0, $height = 0 ) {
	$enabled = mrn_public_security_oembed_strip_author_enabled( $data, $post, $width, $height );

	if ( ! $enabled || ! is_array( $data ) ) {
		return $data;
	}

	unset( $data['author_name'], $data['author_url'] );

	return $data;
}
add_filter( 'oembed_response_data', 'mrn_public_security_strip_oembed_author_data', 20, 4 );

/**
 * Check whether oEmbed author stripping is enabled.
 *
 * @param array $data   oEmbed response data.
 * @param mixed $post   Embedded post object.
 * @param int   $width  Requested width.
 * @param int   $height Requested height.
 * @return bool
 */
function mrn_public_security_oembed_strip_author_enabled( $data = array(), $post = null, $width = 0, $height = 0 ) {
	return (bool) apply_filters( 'mrn_public_security_oembed_strip_author_enabled', true, $data, $post, $width, $height );
}

/**
 * Normalize a REST route string for comparison.
 *
 * @param mixed $route Candidate route.
 * @return string
 */
function mrn_public_security_normalize_rest_route( $route ) {
	if ( ! is_string( $route ) || '' === trim( $route ) ) {
		return '';
	}

	$path = mrn_public_security_parse_url( $route, PHP_URL_PATH );

	if ( is_string( $path ) && '' !== $path ) {
		$route = $path;
	}

	$route = rawurldecode( $route );
	$route = '/' . trim( $route, "/ \t\n\r\0\x0B" );
	$route = untrailingslashit( $route );

	return '/' === $route ? '' : $route;
}

/**
 * Get guarded REST routes.
 *
 * @return array
 */
function mrn_public_security_get_guarded_rest_routes() {
	$routes = apply_filters(
		'mrn_public_security_guarded_rest_routes',
		array(
			'/smartcrawl/v1/instant-indexing',
			'/wpmudev-dashboard/v1/plugins/action',
		)
	);

	if ( ! is_array( $routes ) ) {
		return array();
	}

	$normalized = array();

	foreach ( $routes as $route ) {
		$route = mrn_public_security_normalize_rest_route( $route );

		if ( '' !== $route ) {
			$normalized[] = $route;
		}
	}

	return array_values( array_unique( $normalized ) );
}

/**
 * Get the current request URI.
 *
 * @return string
 */
function mrn_public_security_get_request_uri() {
	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';

	if ( ! is_string( $request_uri ) || '' === $request_uri ) {
		return '';
	}

	return $request_uri;
}

/**
 * Get the current request path.
 *
 * @return string
 */
function mrn_public_security_get_request_path() {
	$request_uri = mrn_public_security_get_request_uri();

	if ( '' === $request_uri ) {
		return '';
	}

	$path = mrn_public_security_parse_url( $request_uri, PHP_URL_PATH );

	if ( ! is_string( $path ) || '' === $path ) {
		return '';
	}

	return rawurldecode( $path );
}

/**
 * Get the current REST route from pretty URLs or ?rest_route= requests.
 *
 * @return string
 */
function mrn_public_security_get_request_rest_route() {
	$rest_route = filter_input( INPUT_GET, 'rest_route', FILTER_UNSAFE_RAW );

	if ( is_string( $rest_route ) && '' !== $rest_route ) {
		return mrn_public_security_normalize_rest_route( sanitize_text_field( wp_unslash( $rest_route ) ) );
	}

	$request_uri = mrn_public_security_get_request_uri();
	$query       = '' !== $request_uri ? mrn_public_security_parse_url( $request_uri, PHP_URL_QUERY ) : '';

	if ( is_string( $query ) && '' !== $query ) {
		$query_vars = array();
		parse_str( $query, $query_vars );

		if ( isset( $query_vars['rest_route'] ) && is_string( $query_vars['rest_route'] ) ) {
			return mrn_public_security_normalize_rest_route( sanitize_text_field( wp_unslash( $query_vars['rest_route'] ) ) );
		}
	}

	$request_path = mrn_public_security_get_request_path();

	if ( '' === $request_path ) {
		return '';
	}

	$rest_prefix = function_exists( 'rest_get_url_prefix' ) ? rest_get_url_prefix() : 'wp-json';
	$rest_prefix = trim( (string) $rest_prefix, '/' );
	$rest_path   = mrn_public_security_parse_url( home_url( '/' . $rest_prefix ), PHP_URL_PATH );

	if ( is_string( $rest_path ) && '' !== $rest_path ) {
		$rest_path = untrailingslashit( rawurldecode( $rest_path ) );

		if ( $request_path === $rest_path || 0 === strpos( $request_path, $rest_path . '/' ) ) {
			return mrn_public_security_normalize_rest_route( substr( $request_path, strlen( $rest_path ) ) );
		}
	}

	$marker   = '/' . $rest_prefix . '/';
	$position = strpos( $request_path, $marker );

	if ( false === $position ) {
		return '';
	}

	return mrn_public_security_normalize_rest_route( substr( $request_path, $position + strlen( $marker ) - 1 ) );
}

/**
 * Check whether the current REST request targets a guarded route.
 *
 * @return bool
 */
function mrn_public_security_is_guarded_rest_request() {
	$route = mrn_public_security_get_request_rest_route();

	if ( '' === $route ) {
		return false;
	}

	return in_array( $route, mrn_public_security_get_guarded_rest_routes(), true );
}

/**
 * Get guarded REST write methods.
 *
 * @return array
 */
function mrn_public_security_get_guarded_rest_methods() {
	$methods = apply_filters( 'mrn_public_security_guarded_rest_methods', array( 'POST', 'PUT', 'PATCH', 'DELETE' ) );

	if ( ! is_array( $methods ) ) {
		return array();
	}

	$normalized = array();

	foreach ( $methods as $method ) {
		if ( ! is_string( $method ) ) {
			continue;
		}

		$method = strtoupper( trim( $method ) );

		if ( '' !== $method ) {
			$normalized[] = $method;
		}
	}

	return array_values( array_unique( $normalized ) );
}

/**
 * Check whether the current request method is guarded.
 *
 * @return bool
 */
function mrn_public_security_is_guarded_rest_method() {
	$method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : 'GET';

	if ( ! is_string( $method ) ) {
		$method = 'GET';
	}

	return in_array( strtoupper( trim( $method ) ), mrn_public_security_get_guarded_rest_methods(), true );
}

/**
 * Check whether the current user may access guarded REST routes.
 *
 * @return bool
 */
function mrn_public_security_current_user_can_guarded_rest_route() {
	foreach ( mrn_public_security_get_guarded_rest_capabilities() as $capability ) {
		if ( is_string( $capability ) && '' !== $capability && current_user_can( $capability ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Get capabilities allowed through guarded REST routes.
 *
 * @return array
 */
function mrn_public_security_get_guarded_rest_capabilities() {
	$capabilities = apply_filters(
		'mrn_public_security_guarded_rest_capabilities',
		array(
			'manage_options',
			'manage_network_options',
		)
	);

	if ( ! is_array( $capabilities ) ) {
		return array();
	}

	$normalized = array();

	foreach ( $capabilities as $capability ) {
		if ( is_string( $capability ) && '' !== trim( $capability ) ) {
			$normalized[] = trim( $capability );
		}
	}

	return array_values( array_unique( $normalized ) );
}

/**
 * Check whether the REST scanner-noise guard is enabled.
 *
 * @return bool
 */
function mrn_public_security_rest_guard_enabled() {
	return (bool) apply_filters( 'mrn_public_security_rest_guard_enabled', true );
}

/**
 * Guard known admin-only vendor REST routes before required-param validation.
 *
 * @param mixed $result Existing authentication result.
 * @return mixed
 */
function mrn_public_security_guard_rest_routes_before_validation( $result ) {
	if ( is_wp_error( $result ) ) {
		return $result;
	}

	if ( ! mrn_public_security_rest_guard_enabled() ) {
		return $result;
	}

	if ( ! mrn_public_security_is_guarded_rest_method() || ! mrn_public_security_is_guarded_rest_request() ) {
		return $result;
	}

	if ( mrn_public_security_current_user_can_guarded_rest_route() ) {
		return $result;
	}

	$status  = function_exists( 'rest_authorization_required_code' ) ? rest_authorization_required_code() : ( is_user_logged_in() ? 403 : 401 );
	$message = apply_filters(
		'mrn_public_security_rest_guard_error_message',
		__( 'Sorry, you are not allowed to do that.', 'mrn-public-security-hardening' )
	);

	return new WP_Error(
		'rest_forbidden',
		is_string( $message ) && '' !== $message ? $message : __( 'Sorry, you are not allowed to do that.', 'mrn-public-security-hardening' ),
		array(
			'status' => $status,
		)
	);
}
add_filter( 'rest_authentication_errors', 'mrn_public_security_guard_rest_routes_before_validation', 101 );

/**
 * Check whether security.txt handling is enabled.
 *
 * @return bool
 */
function mrn_public_security_security_txt_enabled() {
	return (bool) apply_filters( 'mrn_public_security_security_txt_enabled', true );
}

/**
 * Check whether the current request is for /.well-known/security.txt.
 *
 * @return bool
 */
function mrn_public_security_is_security_txt_request() {
	$request_path = mrn_public_security_get_request_path();

	if ( '' === $request_path ) {
		return false;
	}

	$target_path = mrn_public_security_parse_url( home_url( '/.well-known/security.txt' ), PHP_URL_PATH );

	if ( ! is_string( $target_path ) || '' === $target_path ) {
		$target_path = '/.well-known/security.txt';
	}

	return untrailingslashit( rawurldecode( $request_path ) ) === untrailingslashit( rawurldecode( $target_path ) );
}

/**
 * Get the default security.txt contact email.
 *
 * @return string
 */
function mrn_public_security_get_default_security_txt_contact_email() {
	$email = get_option( 'admin_email' );

	return is_string( $email ) && is_email( $email ) ? $email : '';
}

/**
 * Get the default security.txt policy URL.
 *
 * @return string
 */
function mrn_public_security_get_default_security_txt_policy_url() {
	$policy_path = apply_filters( 'mrn_public_security_security_txt_default_policy_path', 'privacy-center' );

	if ( ! is_string( $policy_path ) || '' === trim( $policy_path ) ) {
		return '';
	}

	$policy_path = trim( $policy_path, '/' );

	if ( ! function_exists( 'get_page_by_path' ) ) {
		return '';
	}

	$page = get_page_by_path( $policy_path, OBJECT, 'page' );

	if ( ! $page || 'publish' !== get_post_status( $page ) ) {
		return '';
	}

	return home_url( '/' . $policy_path . '/' );
}

/**
 * Get the default security.txt expiration timestamp.
 *
 * @return string
 */
function mrn_public_security_get_default_security_txt_expires() {
	$day  = defined( 'DAY_IN_SECONDS' ) ? DAY_IN_SECONDS : 86400;
	$year = defined( 'YEAR_IN_SECONDS' ) ? YEAR_IN_SECONDS : 365 * $day;

	return gmdate( 'Y-m-d\TH:i:s\Z', time() + $year );
}

/**
 * Sanitize a security.txt field value.
 *
 * @param string $field Field name.
 * @param mixed  $value Field value.
 * @return string
 */
function mrn_public_security_sanitize_security_txt_field_value( $field, $value ) {
	if ( ! is_scalar( $value ) ) {
		return '';
	}

	$value = trim( (string) $value );

	if ( '' === $value ) {
		return '';
	}

	if ( in_array( $field, array( 'Contact', 'Canonical', 'Policy' ), true ) ) {
		return esc_url_raw( $value );
	}

	return sanitize_text_field( $value );
}

/**
 * Build security.txt fields.
 *
 * @return array
 */
function mrn_public_security_get_security_txt_fields() {
	$contact_email = apply_filters(
		'mrn_public_security_security_txt_contact_email',
		mrn_public_security_get_default_security_txt_contact_email()
	);

	$contact = '';

	if ( is_string( $contact_email ) && is_email( $contact_email ) ) {
		$contact = 'mailto:' . sanitize_email( $contact_email );
	}

	$fields = array(
		'Contact'   => apply_filters( 'mrn_public_security_security_txt_contact', $contact ),
		'Expires'   => apply_filters( 'mrn_public_security_security_txt_expires', mrn_public_security_get_default_security_txt_expires() ),
		'Canonical' => apply_filters( 'mrn_public_security_security_txt_canonical', home_url( '/.well-known/security.txt' ) ),
		'Policy'    => apply_filters( 'mrn_public_security_security_txt_policy_url', mrn_public_security_get_default_security_txt_policy_url() ),
	);

	$fields = apply_filters( 'mrn_public_security_security_txt_fields', $fields );

	return is_array( $fields ) ? $fields : array();
}

/**
 * Build security.txt body content.
 *
 * @return string
 */
function mrn_public_security_build_security_txt() {
	$fields = mrn_public_security_get_security_txt_fields();
	$lines  = array();

	foreach ( $fields as $field => $values ) {
		$field = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $field );

		if ( '' === $field ) {
			continue;
		}

		if ( ! is_array( $values ) ) {
			$values = array( $values );
		}

		foreach ( $values as $value ) {
			$value = mrn_public_security_sanitize_security_txt_field_value( $field, $value );

			if ( '' !== $value ) {
				$lines[] = $field . ': ' . $value;
			}
		}
	}

	return implode( "\n", $lines ) . "\n";
}

/**
 * Serve /.well-known/security.txt from WordPress.
 */
function mrn_public_security_maybe_serve_security_txt() {
	if ( ! mrn_public_security_security_txt_enabled() || ! mrn_public_security_is_security_txt_request() ) {
		return;
	}

	$content = apply_filters( 'mrn_public_security_security_txt_content', mrn_public_security_build_security_txt() );

	if ( ! is_string( $content ) ) {
		$content = '';
	}

	status_header( 200 );
	nocache_headers();
	header( 'Content-Type: text/plain; charset=' . get_option( 'blog_charset' ) );
	header( 'X-Content-Type-Options: nosniff' );

	echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Plain text content is built from sanitized fields and filterable for site overrides.
	exit;
}
add_action( 'parse_request', 'mrn_public_security_maybe_serve_security_txt', 0 );

/**
 * Get the admin capability required to view the status page.
 *
 * @return string
 */
function mrn_public_security_admin_capability() {
	$default = function_exists( 'is_network_admin' ) && is_network_admin() ? 'manage_network_options' : 'manage_options';
	$cap     = apply_filters( 'mrn_public_security_admin_capability', $default );

	return is_string( $cap ) && '' !== $cap ? $cap : $default;
}

/**
 * Register the site admin status page.
 */
function mrn_public_security_register_admin_page() {
	$hook = add_management_page(
		__( 'MRN Public Security', 'mrn-public-security-hardening' ),
		__( 'MRN Public Security', 'mrn-public-security-hardening' ),
		mrn_public_security_admin_capability(),
		'mrn-public-security-hardening',
		'mrn_public_security_render_admin_page'
	);

	if ( $hook ) {
		$GLOBALS['mrn_public_security_admin_page_hooks'][] = $hook;
	}
}
add_action( 'admin_menu', 'mrn_public_security_register_admin_page' );

/**
 * Register the network admin status page.
 */
function mrn_public_security_register_network_admin_page() {
	$hook = add_submenu_page(
		'settings.php',
		__( 'MRN Public Security', 'mrn-public-security-hardening' ),
		__( 'MRN Public Security', 'mrn-public-security-hardening' ),
		mrn_public_security_admin_capability(),
		'mrn-public-security-hardening',
		'mrn_public_security_render_admin_page'
	);

	if ( $hook ) {
		$GLOBALS['mrn_public_security_admin_page_hooks'][] = $hook;
	}
}

if ( function_exists( 'is_multisite' ) && is_multisite() ) {
	add_action( 'network_admin_menu', 'mrn_public_security_register_network_admin_page' );
}

/**
 * Enqueue assets for the status page.
 *
 * @param string $hook_suffix Current admin page hook.
 */
function mrn_public_security_enqueue_admin_assets( $hook_suffix ) {
	$hooks = isset( $GLOBALS['mrn_public_security_admin_page_hooks'] ) && is_array( $GLOBALS['mrn_public_security_admin_page_hooks'] )
		? $GLOBALS['mrn_public_security_admin_page_hooks']
		: array();

	if ( ! in_array( $hook_suffix, $hooks, true ) ) {
		return;
	}

	wp_register_style( 'mrn-public-security-admin', false, array(), MRN_PUBLIC_SECURITY_HARDENING_VERSION );
	wp_enqueue_style( 'mrn-public-security-admin' );
	wp_add_inline_style(
		'mrn-public-security-admin',
		'.mrn-public-security-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px;margin:18px 0}.mrn-public-security-card{background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:16px}.mrn-public-security-card h2{font-size:16px;margin:0 0 12px}.mrn-public-security-status{display:inline-flex;align-items:center;gap:6px;border-radius:999px;padding:3px 8px;font-size:12px;font-weight:600}.mrn-public-security-status--good{background:#edfaef;color:#0a6b22}.mrn-public-security-status--warn{background:#fcf9e8;color:#755100}.mrn-public-security-status--off{background:#f6f7f7;color:#50575e}.mrn-public-security-status--info{background:#eef5fb;color:#0a4b78}.mrn-public-security-list{margin:8px 0 0 18px}.mrn-public-security-list li{margin:3px 0}.mrn-public-security-prompt{width:100%;min-height:320px;font-family:Menlo,Consolas,monospace;font-size:12px}.mrn-public-security-actions{display:flex;align-items:center;gap:10px;margin:10px 0}.mrn-public-security-copy-status{min-height:20px}.mrn-public-security-meta{margin:6px 0;color:#50575e}.mrn-public-security-table th{width:180px}'
	);

	wp_register_script( 'mrn-public-security-admin', '', array(), MRN_PUBLIC_SECURITY_HARDENING_VERSION, true );
	wp_enqueue_script( 'mrn-public-security-admin' );
	wp_add_inline_script(
		'mrn-public-security-admin',
		"(function(){function setStatus(message){var status=document.getElementById('mrn-public-security-copy-status');if(status){status.textContent=message;}}document.addEventListener('click',function(event){var button=event.target.closest ? event.target.closest('[data-mrn-public-security-copy]') : null;if(!button){return;}var targetId=button.getAttribute('data-mrn-public-security-copy');var target=document.getElementById(targetId);if(!target){return;}var text=target.value || target.textContent || '';if(window.navigator && window.navigator.clipboard && window.navigator.clipboard.writeText){window.navigator.clipboard.writeText(text).then(function(){setStatus(button.getAttribute('data-success-message') || 'Copied.');},function(){target.focus();target.select();document.execCommand('copy');setStatus(button.getAttribute('data-success-message') || 'Copied.');});return;}target.focus();target.select();document.execCommand('copy');setStatus(button.getAttribute('data-success-message') || 'Copied.');});}());"
	);
}
add_action( 'admin_enqueue_scripts', 'mrn_public_security_enqueue_admin_assets' );

/**
 * Render a status badge.
 *
 * @param string $status Status key.
 * @param string $label  Label.
 */
function mrn_public_security_render_admin_status_badge( $status, $label ) {
	$allowed = array( 'good', 'warn', 'off', 'info' );

	if ( ! in_array( $status, $allowed, true ) ) {
		$status = 'info';
	}

	printf(
		'<span class="mrn-public-security-status mrn-public-security-status--%1$s">%2$s</span>',
		esc_attr( $status ),
		esc_html( $label )
	);
}

/**
 * Format an array for admin display.
 *
 * @param array $items Items to display.
 * @return string
 */
function mrn_public_security_format_admin_list_value( $items ) {
	if ( empty( $items ) ) {
		return __( 'None', 'mrn-public-security-hardening' );
	}

	$items = array_map( 'sanitize_text_field', array_map( 'strval', $items ) );

	return implode( ', ', $items );
}

/**
 * Check whether the default policy page exists.
 *
 * @return bool
 */
function mrn_public_security_security_txt_default_policy_page_exists() {
	$policy_path = apply_filters( 'mrn_public_security_security_txt_default_policy_path', 'privacy-center' );

	if ( ! is_string( $policy_path ) || '' === trim( $policy_path ) || ! function_exists( 'get_page_by_path' ) ) {
		return false;
	}

	$page = get_page_by_path( trim( $policy_path, '/' ), OBJECT, 'page' );

	return $page && 'publish' === get_post_status( $page );
}

/**
 * Build the copyable per-site rollout prompt.
 *
 * @return string
 */
function mrn_public_security_get_site_completion_prompt() {
	$prompt = <<<PROMPT
Review and finish MRN Public Security Hardening rollout for this site.

What the shared MU plugin does:
- Redirects public author archives like /author/{username}/ to home_url('/') by default.
- Removes author_name and author_url from oEmbed responses.
- Guards known admin-only vendor REST write routes so unauthenticated scanner requests get 401 rest_forbidden before required-parameter validation:
  - /smartcrawl/v1/instant-indexing
  - /wpmudev-dashboard/v1/plugins/action
- Serves /.well-known/security.txt through WordPress/plugin logic.
- Keeps behavior filterable per site for author redirects/noindex, oEmbed stripping, guarded REST routes/methods/capabilities, and all security.txt fields.

What still needs to be done per site:
1. Confirm the shared MU plugin is present and loaded by the MRN MU loader.
2. Remove any older site-specific hardening MU plugin that duplicates this behavior.
3. Decide the site-specific security.txt contact:
   - Default is the site admin_email.
   - Prefer adding a per-site filter for a real monitored mailbox if needed.
4. Decide the security.txt Policy URL:
   - The plugin only uses /privacy-center/ if that page exists.
   - Create that page or filter/omit the policy URL per site.
5. Confirm the site canonical/home URL is correct, since security.txt Canonical uses home_url().
6. Add per-site filters for any extra scanner-noise REST routes if needed.
7. If the site intentionally uses public author archives, disable redirects and rely on/filter the noindex fallback.
8. Runtime verify on the actual site:
   - /.well-known/security.txt returns 200
   - /author/{username}/ redirects by default
   - oEmbed output does not expose author fields
   - unauthenticated POST {} to guarded REST routes returns 401, not 400
   - authenticated admins are not blocked
9. If deploying to production/staging, follow MRN deployment standards, including a DB-only UpdraftPlus backup before any SSH deploy.

Do not hardcode client domains in the shared plugin. Use filters or site-local configuration only.
PROMPT;

	return (string) apply_filters( 'mrn_public_security_admin_site_completion_prompt', $prompt );
}

/**
 * Render the read-only admin status page.
 */
function mrn_public_security_render_admin_page() {
	if ( ! current_user_can( mrn_public_security_admin_capability() ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'mrn-public-security-hardening' ) );
	}

	$security_txt_fields = mrn_public_security_get_security_txt_fields();
	$policy_url          = isset( $security_txt_fields['Policy'] ) && is_string( $security_txt_fields['Policy'] ) ? $security_txt_fields['Policy'] : '';
	$contact             = isset( $security_txt_fields['Contact'] ) && is_string( $security_txt_fields['Contact'] ) ? $security_txt_fields['Contact'] : '';
	$expires             = isset( $security_txt_fields['Expires'] ) && is_string( $security_txt_fields['Expires'] ) ? $security_txt_fields['Expires'] : '';
	$canonical           = isset( $security_txt_fields['Canonical'] ) && is_string( $security_txt_fields['Canonical'] ) ? $security_txt_fields['Canonical'] : '';
	$prompt              = mrn_public_security_get_site_completion_prompt();
	$health_slug         = mrn_public_security_get_uptime_robot_check_slug();
	$health_page         = function_exists( 'get_page_by_path' ) ? get_page_by_path( $health_slug, OBJECT, 'page' ) : null;
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'MRN Public Security Hardening', 'mrn-public-security-hardening' ); ?></h1>
		<p class="description">
			<?php esc_html_e( 'Read-only status for shared public hardening behavior. Configure site exceptions with filters, not stored options.', 'mrn-public-security-hardening' ); ?>
		</p>

		<div class="mrn-public-security-grid">
			<div class="mrn-public-security-card">
				<h2><?php esc_html_e( 'Plugin', 'mrn-public-security-hardening' ); ?></h2>
				<?php mrn_public_security_render_admin_status_badge( 'good', __( 'Loaded', 'mrn-public-security-hardening' ) ); ?>
				<p class="mrn-public-security-meta">
					<?php
					printf(
						/* translators: %s: plugin version. */
						esc_html__( 'Version %s', 'mrn-public-security-hardening' ),
						esc_html( MRN_PUBLIC_SECURITY_HARDENING_VERSION )
					);
					?>
				</p>
				<p class="mrn-public-security-meta"><?php echo esc_html( plugin_basename( __FILE__ ) ); ?></p>
			</div>

			<div class="mrn-public-security-card">
				<h2><?php esc_html_e( 'Author Archives', 'mrn-public-security-hardening' ); ?></h2>
				<?php
				if ( mrn_public_security_author_archive_redirect_enabled() ) {
					mrn_public_security_render_admin_status_badge( 'good', __( 'Redirecting', 'mrn-public-security-hardening' ) );
				} elseif ( mrn_public_security_author_archive_noindex_enabled() ) {
					mrn_public_security_render_admin_status_badge( 'warn', __( 'Noindex fallback', 'mrn-public-security-hardening' ) );
				} else {
					mrn_public_security_render_admin_status_badge( 'off', __( 'Disabled', 'mrn-public-security-hardening' ) );
				}
				?>
				<table class="widefat striped mrn-public-security-table">
					<tbody>
						<tr>
							<th scope="row"><?php esc_html_e( 'Redirect target', 'mrn-public-security-hardening' ); ?></th>
							<td><?php echo esc_url( apply_filters( 'mrn_public_security_author_archive_redirect_target', home_url( '/' ) ) ); ?></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Noindex fallback', 'mrn-public-security-hardening' ); ?></th>
							<td><?php echo esc_html( mrn_public_security_author_archive_noindex_enabled() ? __( 'Enabled', 'mrn-public-security-hardening' ) : __( 'Disabled', 'mrn-public-security-hardening' ) ); ?></td>
						</tr>
					</tbody>
				</table>
			</div>

			<div class="mrn-public-security-card">
				<h2><?php esc_html_e( 'oEmbed', 'mrn-public-security-hardening' ); ?></h2>
				<?php
				mrn_public_security_render_admin_status_badge(
					mrn_public_security_oembed_strip_author_enabled() ? 'good' : 'off',
					mrn_public_security_oembed_strip_author_enabled() ? __( 'Author fields stripped', 'mrn-public-security-hardening' ) : __( 'Disabled', 'mrn-public-security-hardening' )
				);
				?>
				<p class="mrn-public-security-meta"><?php esc_html_e( 'Removes author_name and author_url from oEmbed response data.', 'mrn-public-security-hardening' ); ?></p>
			</div>

			<div class="mrn-public-security-card">
				<h2><?php esc_html_e( 'REST Scanner Guard', 'mrn-public-security-hardening' ); ?></h2>
				<?php
				mrn_public_security_render_admin_status_badge(
					mrn_public_security_rest_guard_enabled() ? 'good' : 'off',
					mrn_public_security_rest_guard_enabled() ? __( 'Enabled', 'mrn-public-security-hardening' ) : __( 'Disabled', 'mrn-public-security-hardening' )
				);
				?>
				<table class="widefat striped mrn-public-security-table">
					<tbody>
						<tr>
							<th scope="row"><?php esc_html_e( 'Guarded routes', 'mrn-public-security-hardening' ); ?></th>
							<td><?php echo esc_html( mrn_public_security_format_admin_list_value( mrn_public_security_get_guarded_rest_routes() ) ); ?></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Guarded methods', 'mrn-public-security-hardening' ); ?></th>
							<td><?php echo esc_html( mrn_public_security_format_admin_list_value( mrn_public_security_get_guarded_rest_methods() ) ); ?></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Allowed capabilities', 'mrn-public-security-hardening' ); ?></th>
							<td><?php echo esc_html( mrn_public_security_format_admin_list_value( mrn_public_security_get_guarded_rest_capabilities() ) ); ?></td>
						</tr>
					</tbody>
				</table>
			</div>

			<div class="mrn-public-security-card">
				<h2><?php esc_html_e( 'security.txt', 'mrn-public-security-hardening' ); ?></h2>
				<?php
				mrn_public_security_render_admin_status_badge(
					mrn_public_security_security_txt_enabled() ? 'good' : 'off',
					mrn_public_security_security_txt_enabled() ? __( 'Served by WordPress', 'mrn-public-security-hardening' ) : __( 'Disabled', 'mrn-public-security-hardening' )
				);
				?>
				<table class="widefat striped mrn-public-security-table">
					<tbody>
						<tr>
							<th scope="row"><?php esc_html_e( 'URL', 'mrn-public-security-hardening' ); ?></th>
							<td><a href="<?php echo esc_url( home_url( '/.well-known/security.txt' ) ); ?>"><?php echo esc_html( home_url( '/.well-known/security.txt' ) ); ?></a></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Contact', 'mrn-public-security-hardening' ); ?></th>
							<td><?php echo esc_html( '' !== $contact ? $contact : __( 'Not set', 'mrn-public-security-hardening' ) ); ?></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Canonical', 'mrn-public-security-hardening' ); ?></th>
							<td><?php echo esc_html( '' !== $canonical ? $canonical : __( 'Not set', 'mrn-public-security-hardening' ) ); ?></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Policy', 'mrn-public-security-hardening' ); ?></th>
							<td>
								<?php
								if ( '' !== $policy_url ) {
									echo esc_html( $policy_url );
								} elseif ( mrn_public_security_security_txt_default_policy_page_exists() ) {
									esc_html_e( 'Available through filter', 'mrn-public-security-hardening' );
								} else {
									esc_html_e( 'Omitted until /privacy-center/ exists or a filter supplies a URL', 'mrn-public-security-hardening' );
								}
								?>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Expires', 'mrn-public-security-hardening' ); ?></th>
							<td><?php echo esc_html( '' !== $expires ? $expires : __( 'Not set', 'mrn-public-security-hardening' ) ); ?></td>
						</tr>
					</tbody>
				</table>
			</div>

			<div class="mrn-public-security-card">
				<h2><?php esc_html_e( 'UptimeRobot Check Page', 'mrn-public-security-hardening' ); ?></h2>
				<?php
				mrn_public_security_render_admin_status_badge(
					$health_page ? 'good' : 'warn',
					$health_page ? __( 'Page found', 'mrn-public-security-hardening' ) : __( 'Page not found', 'mrn-public-security-hardening' )
				);
				?>
				<table class="widefat striped mrn-public-security-table">
					<tbody>
						<tr>
							<th scope="row"><?php esc_html_e( 'Slug', 'mrn-public-security-hardening' ); ?></th>
							<td><?php echo esc_html( $health_slug ); ?></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Robots handling', 'mrn-public-security-hardening' ); ?></th>
							<td><?php esc_html_e( 'noindex, nofollow, noarchive when requested', 'mrn-public-security-hardening' ); ?></td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>

		<div class="mrn-public-security-card">
			<h2><?php esc_html_e( 'Per-site Rollout Prompt', 'mrn-public-security-hardening' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Copy this into a site-specific work thread to finish runtime setup and validation.', 'mrn-public-security-hardening' ); ?></p>
			<div class="mrn-public-security-actions">
				<button
					type="button"
					class="button button-primary"
					data-mrn-public-security-copy="mrn-public-security-prompt"
					data-success-message="<?php echo esc_attr__( 'Prompt copied.', 'mrn-public-security-hardening' ); ?>"
				>
					<?php esc_html_e( 'Copy prompt', 'mrn-public-security-hardening' ); ?>
				</button>
				<span id="mrn-public-security-copy-status" class="mrn-public-security-copy-status" aria-live="polite"></span>
			</div>
			<label class="screen-reader-text" for="mrn-public-security-prompt"><?php esc_html_e( 'Per-site rollout prompt', 'mrn-public-security-hardening' ); ?></label>
			<textarea id="mrn-public-security-prompt" class="large-text code mrn-public-security-prompt" readonly><?php echo esc_textarea( $prompt ); ?></textarea>
		</div>
	</div>
	<?php
}
