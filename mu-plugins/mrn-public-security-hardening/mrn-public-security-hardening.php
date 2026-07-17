<?php
/**
 * Plugin Name: MRN Public Security Hardening
 * Description: Shared public hardening for MRN brochure/client sites.
 * Version: 0.2.0
 * Author: MRN
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'MRN_PUBLIC_SECURITY_HARDENING_VERSION' ) ) {
	define( 'MRN_PUBLIC_SECURITY_HARDENING_VERSION', '0.2.0' );
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
	$enabled = (bool) apply_filters( 'mrn_public_security_oembed_strip_author_enabled', true, $data, $post, $width, $height );

	if ( ! $enabled || ! is_array( $data ) ) {
		return $data;
	}

	unset( $data['author_name'], $data['author_url'] );

	return $data;
}
add_filter( 'oembed_response_data', 'mrn_public_security_strip_oembed_author_data', 20, 4 );

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

	$path = function_exists( 'wp_parse_url' ) ? wp_parse_url( $route, PHP_URL_PATH ) : parse_url( $route, PHP_URL_PATH );

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

	$path = function_exists( 'wp_parse_url' ) ? wp_parse_url( $request_uri, PHP_URL_PATH ) : parse_url( $request_uri, PHP_URL_PATH );

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
	$query       = '' !== $request_uri && function_exists( 'wp_parse_url' )
		? wp_parse_url( $request_uri, PHP_URL_QUERY )
		: parse_url( $request_uri, PHP_URL_QUERY );

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
	$rest_path   = function_exists( 'wp_parse_url' )
		? wp_parse_url( home_url( '/' . $rest_prefix ), PHP_URL_PATH )
		: parse_url( home_url( '/' . $rest_prefix ), PHP_URL_PATH );

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
	$capabilities = apply_filters(
		'mrn_public_security_guarded_rest_capabilities',
		array(
			'manage_options',
			'manage_network_options',
		)
	);

	if ( ! is_array( $capabilities ) ) {
		return false;
	}

	foreach ( $capabilities as $capability ) {
		if ( is_string( $capability ) && '' !== $capability && current_user_can( $capability ) ) {
			return true;
		}
	}

	return false;
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

	if ( ! (bool) apply_filters( 'mrn_public_security_rest_guard_enabled', true ) ) {
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

	$target_path = function_exists( 'wp_parse_url' )
		? wp_parse_url( home_url( '/.well-known/security.txt' ), PHP_URL_PATH )
		: parse_url( home_url( '/.well-known/security.txt' ), PHP_URL_PATH );

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
