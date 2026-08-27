<?php
/**
 * Plugin Name: MRN Public Security Hardening
 * Description: Shared public hardening for MRN brochure/client sites.
 * Version: 0.4.0
 * Author: MRN
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'MRN_PUBLIC_SECURITY_HARDENING_VERSION' ) ) {
	define( 'MRN_PUBLIC_SECURITY_HARDENING_VERSION', '0.4.0' );
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
		$matched = preg_match( '/^User-agent:[\t ]*\*[\t ]*\R/im', $output, $matches, PREG_OFFSET_CAPTURE );

		if ( 1 === $matched && isset( $matches[0][0], $matches[0][1] ) ) {
			$insert_at = (int) $matches[0][1] + strlen( $matches[0][0] );
			$output    = substr( $output, 0, $insert_at ) . $directive . "\n" . substr( $output, $insert_at );
		} else {
			$output = "User-agent: *\n" . $directive . "\n" . ltrim( $output );
		}
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
 * Get the option name used for the login slug setting.
 *
 * @return string
 */
function mrn_public_security_get_login_slug_option_name() {
	return 'mrn_public_security_login_slug';
}

/**
 * Get known plugins that take ownership of the WordPress login URL.
 *
 * @return array<string,string>
 */
function mrn_public_security_get_login_conflict_plugins() {
	$plugins = array(
		'wps-hide-login/wps-hide-login.php'                    => 'WPS Hide Login',
		'change-wp-admin-login/change-wp-admin-login.php'      => 'Change WP Admin Login',
		'rename-wp-login/rename-wp-login.php'                  => 'Rename wp-login.php',
		'hide-my-wp/inc/core.php'                              => 'Hide My WP',
		'wp-hide-security-enhancer/wp-hide.php'                => 'WP Hide & Security Enhancer',
	);

	$plugins = apply_filters( 'mrn_public_security_login_conflict_plugins', $plugins );

	return is_array( $plugins ) ? $plugins : array();
}

/**
 * Find an active plugin that already controls the login URL.
 *
 * This intentionally disables this feature instead of deactivating another
 * plugin, because silent plugin deactivation can break site authentication.
 *
 * @return array<string,string>|null
 */
function mrn_public_security_get_active_login_conflict_plugin() {
	$active_plugins = get_option( 'active_plugins', array() );

	if ( function_exists( 'get_site_option' ) ) {
		$network_plugins = get_site_option( 'active_sitewide_plugins', array() );

		if ( is_array( $network_plugins ) ) {
			$active_plugins = array_merge( is_array( $active_plugins ) ? $active_plugins : array(), array_keys( $network_plugins ) );
		}
	}

	if ( ! is_array( $active_plugins ) ) {
		return null;
	}

	$active_plugins = array_map( 'strval', $active_plugins );

	foreach ( mrn_public_security_get_login_conflict_plugins() as $plugin_file => $plugin_name ) {
		if ( ! is_string( $plugin_file ) || ! in_array( $plugin_file, $active_plugins, true ) ) {
			continue;
		}

		return array(
			'file' => $plugin_file,
			'name' => is_scalar( $plugin_name ) ? (string) $plugin_name : $plugin_file,
		);
	}

	return null;
}

/**
 * Determine whether another active plugin owns login URL protection.
 *
 * @return bool
 */
function mrn_public_security_login_protection_is_conflicted() {
	return null !== mrn_public_security_get_active_login_conflict_plugin();
}

/**
 * Normalize a login slug candidate before validation or storage.
 *
 * @param mixed $slug Candidate slug.
 * @return string
 */
function mrn_public_security_normalize_login_slug( $slug ) {
	if ( ! is_scalar( $slug ) ) {
		return '';
	}

	$slug = strtolower( trim( wp_unslash( (string) $slug ) ) );
	$slug = rawurldecode( $slug );

	return trim( $slug );
}

/**
 * Check whether a login slug is structurally valid.
 *
 * @param mixed $slug Candidate slug.
 * @return bool
 */
function mrn_public_security_is_structurally_valid_login_slug( $slug ) {
	if ( ! is_string( $slug ) ) {
		return false;
	}

	$slug = trim( $slug );

	if ( '' === $slug || preg_match( '/[\/?#\s]/', $slug ) ) {
		return false;
	}

	return 1 === preg_match( '/^[a-z0-9-]*[a-z0-9][a-z0-9-]*$/', $slug );
}

/**
 * Get the default login slug for sites that do not store an override.
 *
 * @return string
 */
function mrn_public_security_get_default_login_slug() {
	$slug = apply_filters( 'mrn_public_security_login_slug_default', 'site-login' );
	$slug = mrn_public_security_normalize_login_slug( $slug );

	if ( ! mrn_public_security_is_structurally_valid_login_slug( $slug ) ) {
		return 'site-login';
	}

	return $slug;
}

/**
 * Get the login slug currently stored in the database.
 *
 * @return string
 */
function mrn_public_security_get_login_slug_raw() {
	$slug = get_option( mrn_public_security_get_login_slug_option_name(), '' );

	if ( ! is_scalar( $slug ) ) {
		return '';
	}

	return mrn_public_security_normalize_login_slug( $slug );
}

/**
 * Get the active login slug for this site.
 *
 * Invalid stored values fall back to the default so administrators cannot
 * permanently lock themselves out.
 *
 * @return string
 */
function mrn_public_security_get_login_slug() {
	$slug = mrn_public_security_get_login_slug_raw();

	if ( '' === $slug ) {
		return mrn_public_security_get_default_login_slug();
	}

	if ( ! mrn_public_security_is_structurally_valid_login_slug( $slug ) ) {
		return mrn_public_security_get_default_login_slug();
	}

	return $slug;
}

/**
 * Get the state of the current login slug setting.
 *
 * @return array<string,mixed>
 */
function mrn_public_security_get_login_slug_state() {
	$raw          = mrn_public_security_get_login_slug_raw();
	$effective     = mrn_public_security_get_login_slug();
	$stored_valid  = '' !== $raw && mrn_public_security_is_structurally_valid_login_slug( $raw );
	$stored_exists = '' !== $raw;
	$conflict      = $stored_valid ? mrn_public_security_login_slug_conflicts_with_existing_route( $raw ) : false;
	$plugin        = mrn_public_security_get_active_login_conflict_plugin();

	return array(
		'raw'           => $raw,
		'effective'     => $effective,
		'display_value' => '' !== $raw ? $raw : $effective,
		'stored_valid'  => $stored_valid,
		'stored_exists' => $stored_exists,
		'conflict'     => $conflict,
		'plugin'       => $plugin,
		'source'       => null !== $plugin ? 'plugin-conflict' : ( ! $stored_exists ? 'default' : ( $stored_valid ? ( $conflict ? 'conflict' : 'custom' ) : 'invalid' ) ),
	);
}

/**
 * Get the reserved login slugs.
 *
 * @return array
 */
function mrn_public_security_get_reserved_login_slugs() {
	return array(
		'admin',
		'checkemail',
		'confirmaction',
		'confirm_admin_email',
		'embed',
		'feed',
		'html',
		'login',
		'logout',
		'lostpassword',
		'postpass',
		'register',
		'resetpass',
		'rp',
		'search',
		'trackback',
		'wp-activate',
		'wp-admin',
		'wp-content',
		'wp-includes',
		'wp-json',
		'wp-login',
		'wp-cron',
		'wp-sitemap',
		'wp-signup',
		'author',
		'category',
		'comments',
		'atom',
		'rss',
		'xmlrpc',
	);
}

/**
 * Check whether the provided slug is reserved.
 *
 * @param mixed $slug Candidate slug.
 * @return bool
 */
function mrn_public_security_is_reserved_login_slug( $slug ) {
	if ( ! is_string( $slug ) || '' === $slug ) {
		return false;
	}

	return in_array( $slug, mrn_public_security_get_reserved_login_slugs(), true );
}

/**
 * Resolve the path to the WordPress login endpoint.
 *
 * @return string
 */
function mrn_public_security_get_wp_login_path() {
	$site_url_path = mrn_public_security_get_site_url_path_root();

	return '' !== $site_url_path ? $site_url_path . '/wp-login.php' : '/wp-login.php';
}

/**
 * Get the path root for the current site URL.
 *
 * @return string
 */
function mrn_public_security_get_site_url_path_root() {
	$site_url = get_option( 'siteurl' );

	if ( ! is_string( $site_url ) || '' === trim( $site_url ) ) {
		return '';
	}

	$path = mrn_public_security_parse_url( $site_url, PHP_URL_PATH );

	if ( ! is_string( $path ) || '' === $path ) {
		return '';
	}

	return untrailingslashit( rawurldecode( $path ) );
}

/**
 * Get the custom login path for the configured slug.
 *
 * @param string|null $slug Optional slug override.
 * @param bool        $include_site_root Whether to include the site URL path root.
 * @return string
 */
function mrn_public_security_get_custom_login_path( $slug = null, $include_site_root = true ) {
	if ( null === $slug ) {
		$slug = mrn_public_security_get_login_slug();
	} else {
		$slug = mrn_public_security_normalize_login_slug( $slug );

		if ( ! mrn_public_security_is_structurally_valid_login_slug( $slug ) ) {
			$slug = mrn_public_security_get_default_login_slug();
		}
	}

	$slug          = trim( (string) $slug, '/' );
	$path          = '/' . $slug . '/';

	if ( ! $include_site_root ) {
		return $path;
	}

	$site_url_path = mrn_public_security_get_site_url_path_root();

	return '' !== $site_url_path ? $site_url_path . $path : $path;
}

/**
 * Get the custom login URL for the configured slug.
 *
 * @param string|null $slug Optional slug override.
 * @param array       $args Optional query arguments.
 * @return string
 */
function mrn_public_security_get_custom_login_url( $slug = null, $args = array() ) {
	$url = site_url( trailingslashit( mrn_public_security_get_custom_login_path( $slug, false ) ), 'login' );

	if ( is_array( $args ) && ! empty( $args ) ) {
		$url = add_query_arg( $args, $url );
	}

	return $url;
}

/**
 * Get the custom login URL for the active slug.
 *
 * @param array $args Optional query arguments.
 * @return string
 */
function mrn_public_security_get_login_url( $args = array() ) {
	return mrn_public_security_get_custom_login_url( null, $args );
}

/**
 * Replace wp-login.php URLs with the configured custom login URL.
 *
 * @param mixed $url Candidate URL.
 * @return mixed
 */
function mrn_public_security_replace_wp_login_path_in_url( $url ) {
	if ( mrn_public_security_login_protection_is_conflicted() ) {
		return $url;
	}

	if ( ! is_string( $url ) || '' === trim( $url ) ) {
		return $url;
	}

	$parts = mrn_public_security_parse_url( $url );

	if ( ! is_array( $parts ) || ! isset( $parts['path'] ) || ! is_string( $parts['path'] ) ) {
		return $url;
	}

	$current_path = untrailingslashit( rawurldecode( $parts['path'] ) );
	$login_path   = untrailingslashit( mrn_public_security_get_wp_login_path() );

	if ( $current_path !== $login_path ) {
		return $url;
	}

	$parts['path'] = mrn_public_security_get_custom_login_path();

	return mrn_public_security_unparse_url( $parts );
}

/**
 * Rebuild a URL from parsed parts.
 *
 * @param array $parts Parsed URL parts.
 * @return string
 */
function mrn_public_security_unparse_url( $parts ) {
	if ( ! is_array( $parts ) ) {
		return '';
	}

	$scheme   = isset( $parts['scheme'] ) && '' !== $parts['scheme'] ? $parts['scheme'] . '://' : '';
	$user     = isset( $parts['user'] ) ? $parts['user'] : '';
	$pass     = isset( $parts['pass'] ) && '' !== $parts['pass'] ? ':' . $parts['pass'] : '';
	$auth     = '' !== $user ? $user . $pass . '@' : '';
	$host     = isset( $parts['host'] ) ? $parts['host'] : '';
	$port     = isset( $parts['port'] ) ? ':' . $parts['port'] : '';
	$path     = isset( $parts['path'] ) ? $parts['path'] : '';
	$query    = isset( $parts['query'] ) && '' !== $parts['query'] ? '?' . $parts['query'] : '';
	$fragment = isset( $parts['fragment'] ) && '' !== $parts['fragment'] ? '#' . $parts['fragment'] : '';

	if ( '' === $scheme && '' === $host && '' !== $path && '/' !== substr( $path, 0, 1 ) ) {
		$path = '/' . $path;
	}

	return $scheme . $auth . $host . $port . $path . $query . $fragment;
}

/**
 * Replace wp-login.php URLs inside plain-text messages.
 *
 * @param mixed $text Candidate text.
 * @return mixed
 */
function mrn_public_security_replace_wp_login_path_in_text( $text ) {
	if ( mrn_public_security_login_protection_is_conflicted() ) {
		return $text;
	}

	if ( ! is_string( $text ) || '' === trim( $text ) ) {
		return $text;
	}

	return preg_replace_callback( '#https?://[^\s<>"\']+#', 'mrn_public_security_replace_wp_login_path_in_text_callback', $text );
}

/**
 * Callback used when rewriting wp-login.php URLs in plain text.
 *
 * @param array $matches Regex matches.
 * @return string
 */
function mrn_public_security_replace_wp_login_path_in_text_callback( $matches ) {
	if ( ! is_array( $matches ) || ! isset( $matches[0] ) ) {
		return '';
	}

	return mrn_public_security_replace_wp_login_path_in_url( $matches[0] );
}

/**
 * Filter login-related URLs to use the configured slug.
 *
 * @param mixed $url Candidate URL.
 * @return mixed
 */
function mrn_public_security_filter_login_related_url( $url, ...$unused ) {
	unset( $unused );

	return mrn_public_security_replace_wp_login_path_in_url( $url );
}

/**
 * Filter password-reset email messages to point at the custom login URL.
 *
 * @param mixed  $message    Message body.
 * @param string $key        Reset key.
 * @param string $user_login User login.
 * @param mixed  $user_data  User data.
 * @return mixed
 */
function mrn_public_security_filter_retrieve_password_message( $message, $key, $user_login, $user_data ) {
	unset( $key, $user_login, $user_data );

	return mrn_public_security_replace_wp_login_path_in_text( $message );
}

/**
 * Log login slug configuration changes without exposing the slug itself.
 *
 * @param string $event Event label.
 */
function mrn_public_security_log_login_slug_change( $event ) {
	if ( ! is_string( $event ) || '' === trim( $event ) ) {
		$event = 'updated';
	}

	$enabled = apply_filters( 'mrn_public_security_log_login_slug_changes', true, $event );

	if ( ! $enabled ) {
		return;
	}

	error_log( 'MRN Public Security Hardening: login slug configuration ' . sanitize_key( $event ) . '.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional audit trail for state-changing security settings.
}

/**
 * Check whether the login slug conflicts with an existing route or content.
 *
 * @param string $slug Login slug candidate.
 * @return bool
 */
function mrn_public_security_login_slug_conflicts_with_existing_route( $slug ) {
	if ( ! is_string( $slug ) || '' === $slug ) {
		return false;
	}

	if ( mrn_public_security_is_reserved_login_slug( $slug ) ) {
		return true;
	}

	if ( function_exists( 'url_to_postid' ) ) {
		$post_id = absint( url_to_postid( home_url( '/' . trim( $slug, '/' ) . '/' ) ) );

		if ( 0 !== $post_id ) {
			return true;
		}
	}

	if ( function_exists( 'get_page_by_path' ) && function_exists( 'get_post_types' ) ) {
		$post_types = get_post_types( array( 'public' => true ), 'names' );

		if ( is_array( $post_types ) && ! empty( $post_types ) ) {
			$page = get_page_by_path( trim( $slug, '/' ), OBJECT, $post_types );

			if ( $page instanceof WP_Post ) {
				return true;
			}
		}
	}

	$rules = get_option( 'rewrite_rules' );

	if ( ! is_array( $rules ) ) {
		return false;
	}

	$prefix = '^' . $slug;

	foreach ( array_keys( $rules ) as $regex ) {
		if ( ! is_string( $regex ) || '' === $regex ) {
			continue;
		}

		if ( 0 !== strpos( $regex, $prefix ) ) {
			continue;
		}

		$next_character = substr( $regex, strlen( $prefix ), 1 );

		if ( '' === $next_character ) {
			return true;
		}

		if ( in_array( $next_character, array( '/', '$', '(', '?', '[', '.' ), true ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Validate a login slug candidate.
 *
 * @param mixed $candidate Candidate value.
 * @return string|WP_Error
 */
function mrn_public_security_validate_login_slug( $candidate ) {
	$slug = mrn_public_security_normalize_login_slug( $candidate );

	if ( '' === $slug ) {
		return new WP_Error(
			'mrn_public_security_login_slug_empty',
			__( 'Enter a login URL slug made of lowercase letters, numbers, and hyphens.', 'mrn-public-security-hardening' )
		);
	}

	if ( ! mrn_public_security_is_structurally_valid_login_slug( $slug ) ) {
		return new WP_Error(
			'mrn_public_security_login_slug_invalid',
			__( 'Use one path segment with lowercase letters, numbers, and hyphens only. Do not include spaces, slashes, or query strings.', 'mrn-public-security-hardening' )
		);
	}

	if ( mrn_public_security_is_reserved_login_slug( $slug ) ) {
		return new WP_Error(
			'mrn_public_security_login_slug_reserved',
			__( 'That login slug is reserved by WordPress or this plugin and cannot be used.', 'mrn-public-security-hardening' )
		);
	}

	if ( mrn_public_security_login_slug_conflicts_with_existing_route( $slug ) ) {
		return new WP_Error(
			'mrn_public_security_login_slug_conflict',
			__( 'That login slug already matches an existing page, post, rewrite rule, or endpoint.', 'mrn-public-security-hardening' )
		);
	}

	return $slug;
}

/**
 * Save a validated login slug.
 *
 * @param mixed $candidate Candidate slug.
 * @return array|WP_Error
 */
function mrn_public_security_process_login_slug_save( $candidate ) {
	$validated = mrn_public_security_validate_login_slug( $candidate );

	if ( is_wp_error( $validated ) ) {
		return $validated;
	}

	$raw_current = mrn_public_security_get_login_slug_raw();
	$changed     = $raw_current !== $validated;

	if ( $changed ) {
		update_option( mrn_public_security_get_login_slug_option_name(), $validated );
		mrn_public_security_log_login_slug_change( 'updated' );
	}

	return array(
		'changed' => $changed,
		'slug'    => $validated,
	);
}

/**
 * Reset the login slug back to the default site-level behavior.
 *
 * @return array
 */
function mrn_public_security_reset_login_slug_option() {
	delete_option( mrn_public_security_get_login_slug_option_name() );
	mrn_public_security_log_login_slug_change( 'reset' );

	return array(
		'changed' => true,
		'slug'    => mrn_public_security_get_default_login_slug(),
	);
}

/**
 * Determine the login request action.
 *
 * @param array|null $request Optional request data for testing.
 * @return string
 */
function mrn_public_security_get_login_request_action( $request = null ) {
	if ( ! is_array( $request ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only request inspection for login routing.
		$request = $_REQUEST;
	}

	if ( ! isset( $request['action'] ) || ! is_scalar( $request['action'] ) ) {
		return '';
	}

	return sanitize_key( wp_unslash( (string) $request['action'] ) );
}

/**
 * Determine whether the request is using a legacy login-only action.
 *
 * @param array|null $request Optional request data for testing.
 * @return bool
 */
function mrn_public_security_should_allow_legacy_login_request( $request = null ) {
	if ( ! is_array( $request ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only request inspection for login routing.
		$request = $_REQUEST;
	}

	$action  = mrn_public_security_get_login_request_action( $request );
	$allowed = apply_filters(
		'mrn_public_security_allowed_legacy_login_actions',
		array(
			'confirmaction',
			'confirm_admin_email',
			'logout',
			'lostpassword',
			'postpass',
			'register',
			'retrievepassword',
			'resetpass',
			'rp',
		)
	);

	if ( is_string( $action ) && '' !== $action && is_array( $allowed ) && in_array( $action, $allowed, true ) ) {
		return true;
	}

	if ( isset( $request['checkemail'] ) && is_scalar( $request['checkemail'] ) && '' !== trim( (string) $request['checkemail'] ) ) {
		return true;
	}

	if ( isset( $request['loggedout'] ) && is_scalar( $request['loggedout'] ) && '' !== trim( (string) $request['loggedout'] ) ) {
		return true;
	}

	if ( isset( $request['reauth'] ) && is_scalar( $request['reauth'] ) && '' !== trim( (string) $request['reauth'] ) ) {
		return true;
	}

	if ( isset( $request['interim-login'] ) && is_scalar( $request['interim-login'] ) && '' !== trim( (string) $request['interim-login'] ) ) {
		return true;
	}

	return false;
}

/**
 * Check whether the current request path points to the custom login slug.
 *
 * @param string|null $request_path Optional path for testing.
 * @return bool
 */
function mrn_public_security_is_custom_login_request( $request_path = null ) {
	if ( mrn_public_security_login_protection_is_conflicted() ) {
		return false;
	}

	if ( null === $request_path ) {
		$request_path = mrn_public_security_get_request_path();
	}

	if ( ! is_string( $request_path ) || '' === $request_path ) {
		return false;
	}

	return untrailingslashit( rawurldecode( $request_path ) ) === untrailingslashit( mrn_public_security_get_custom_login_path() );
}

/**
 * Check whether the current request path points to wp-login.php.
 *
 * @param string|null $request_path Optional path for testing.
 * @return bool
 */
function mrn_public_security_is_wp_login_request( $request_path = null ) {
	if ( null === $request_path ) {
		$request_path = mrn_public_security_get_request_path();
	}

	if ( ! is_string( $request_path ) || '' === $request_path ) {
		return false;
	}

	return untrailingslashit( rawurldecode( $request_path ) ) === untrailingslashit( mrn_public_security_get_wp_login_path() );
}

/**
 * Check whether a direct wp-login.php request should be blocked.
 *
 * @param string|null $request_path Optional path for testing.
 * @param array|null  $request      Optional request data for testing.
 * @return bool
 */
function mrn_public_security_should_block_default_login_request( $request_path = null, $request = null ) {
	if ( mrn_public_security_login_protection_is_conflicted() ) {
		return false;
	}

	if ( ! mrn_public_security_is_wp_login_request( $request_path ) ) {
		return false;
	}

	if ( mrn_public_security_should_allow_legacy_login_request( $request ) ) {
		return false;
	}

	return true;
}

/**
 * Block direct requests to wp-login.php before WordPress can redirect them.
 *
 * @param bool $return_only Optional test mode that returns the response data.
 * @return array|null
 */
function mrn_public_security_block_default_login_request( $return_only = false ) {
	if ( ! mrn_public_security_should_block_default_login_request() ) {
		return null;
	}

	$response = array(
		'status'  => 404,
		'message' => __( 'Not found.', 'mrn-public-security-hardening' ),
	);

	if ( $return_only ) {
		return $response;
	}

	nocache_headers();
	status_header( 404 );
	wp_die( esc_html( $response['message'] ), '', array( 'response' => absint( $response['status'] ) ) );
}
// The callback returns response data only in its explicit test mode.
// @phpstan-ignore return.void
add_action( 'init', 'mrn_public_security_block_default_login_request', 0 );

/**
 * Load the normal WordPress login screen for the custom slug.
 *
 * @param bool $exit Optional test mode that skips the final exit.
 * @return bool
 */
function mrn_public_security_maybe_serve_custom_login_request( $exit = true ) {
	if ( ! mrn_public_security_is_custom_login_request() ) {
		return false;
	}

	require ABSPATH . 'wp-login.php'; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingCustomConstant -- Core login screen handoff for the custom login slug.

	if ( $exit ) {
		exit;
	}

	return true;
}
// The callback returns a boolean only in its explicit test mode.
// @phpstan-ignore return.void
add_action( 'parse_request', 'mrn_public_security_maybe_serve_custom_login_request', 0 );

/**
 * Handle login slug save requests from the admin screen.
 *
 * @param bool $exit Optional test mode that skips the final exit.
 * @return string|null
 */
function mrn_public_security_handle_login_slug_save( $exit = true ) {
	if ( ! current_user_can( mrn_public_security_admin_capability() ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'mrn-public-security-hardening' ) );
	}

	check_admin_referer( 'mrn_public_security_save_login_slug', 'mrn_public_security_login_slug_nonce' );

	$submitted_slug = isset( $_POST['mrn_public_security_login_slug'] ) ? sanitize_text_field( wp_unslash( $_POST['mrn_public_security_login_slug'] ) ) : '';
	$result         = mrn_public_security_process_login_slug_save( $submitted_slug );

	if ( is_wp_error( $result ) ) {
		add_settings_error(
			mrn_public_security_get_login_slug_option_name(),
			$result->get_error_code(),
			$result->get_error_message(),
			'error'
		);
	} else {
		add_settings_error(
			mrn_public_security_get_login_slug_option_name(),
			'mrn_public_security_login_slug_updated',
			$result['changed'] ? __( 'Login URL updated.', 'mrn-public-security-hardening' ) : __( 'Login URL already used that slug.', 'mrn-public-security-hardening' ),
			'updated'
		);
	}

	set_transient( 'settings_errors', get_settings_errors(), 30 );

	$redirect_to = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : '';
	$fallback    = function_exists( 'menu_page_url' ) ? menu_page_url( 'mrn-public-security-hardening', false ) : '';

	if ( ! is_string( $fallback ) || '' === trim( $fallback ) ) {
		$fallback = admin_url( 'admin.php?page=mrn-public-security-hardening' );
	}

	$target = wp_validate_redirect( $redirect_to, $fallback );

	wp_safe_redirect( $target );

	if ( $exit ) {
		exit;
	}

	return $target;
}
// The callback returns a redirect only in its explicit test mode.
// @phpstan-ignore return.void
add_action( 'admin_post_mrn_public_security_save_login_slug', 'mrn_public_security_handle_login_slug_save' );

add_filter( 'site_url', 'mrn_public_security_filter_login_related_url', 10, 4 );
add_filter( 'network_site_url', 'mrn_public_security_filter_login_related_url', 10, 4 );
add_filter( 'login_url', 'mrn_public_security_filter_login_related_url', 10, 3 );
add_filter( 'lostpassword_url', 'mrn_public_security_filter_login_related_url', 10, 2 );
add_filter( 'register_url', 'mrn_public_security_filter_login_related_url', 10, 1 );
add_filter( 'retrieve_password_message', 'mrn_public_security_filter_retrieve_password_message', 10, 4 );

if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( 'WP_CLI' ) ) {
	\WP_CLI::add_command( 'mrn public-security reset-login-slug', 'mrn_public_security_cli_reset_login_slug' );
}

/**
 * Reset the login slug via WP-CLI.
 */
function mrn_public_security_cli_reset_login_slug() {
	mrn_public_security_reset_login_slug_option();

	if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( 'WP_CLI' ) ) {
		\WP_CLI::success( 'Login URL reset to the default slug.' );
	}
}

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
 * Find an Admin Menu Editor top-level parent by menu title.
 *
 * @param string $title       Menu title to match.
 * @param string $config_area AME config area.
 * @return string
 */
function mrn_public_security_find_ame_parent_slug_by_title( $title, $config_area ) {
	$config = get_option( 'ws_menu_editor_pro' );

	if ( ! is_array( $config ) || empty( $config[ $config_area ]['tree'] ) || ! is_array( $config[ $config_area ]['tree'] ) ) {
		return '';
	}

	foreach ( $config[ $config_area ]['tree'] as $slug => $item ) {
		if ( ! is_array( $item ) || ! is_string( $slug ) || '' === $slug ) {
			continue;
		}

		$menu_title = isset( $item['menu_title'] ) && is_string( $item['menu_title'] )
			? $item['menu_title']
			: '';

		if ( '' === $menu_title && isset( $item['defaults']['menu_title'] ) && is_string( $item['defaults']['menu_title'] ) ) {
			$menu_title = $item['defaults']['menu_title'];
		}

		if ( strtolower( wp_strip_all_tags( $menu_title ) ) === strtolower( $title ) ) {
			return $slug;
		}
	}

	return '';
}

/**
 * Find a currently registered top-level admin parent by menu title.
 *
 * @param string $title Menu title to match.
 * @return string
 */
function mrn_public_security_find_registered_parent_slug_by_title( $title ) {
	global $menu;

	if ( ! is_array( $menu ) ) {
		return '';
	}

	foreach ( $menu as $item ) {
		if ( ! is_array( $item ) || ! isset( $item[0], $item[2] ) || ! is_string( $item[2] ) ) {
			continue;
		}

		if ( strtolower( wp_strip_all_tags( (string) $item[0] ) ) === strtolower( $title ) ) {
			return $item[2];
		}
	}

	return '';
}

/**
 * Get the parent admin menu slug for the status page.
 *
 * @return string
 */
function mrn_public_security_admin_parent_slug() {
	$is_network = function_exists( 'is_network_admin' ) && is_network_admin();
	$advanced   = mrn_public_security_find_ame_parent_slug_by_title( 'Advanced', $is_network ? 'custom_network_menu' : 'custom_menu' );

	if ( '' === $advanced ) {
		$advanced = mrn_public_security_find_registered_parent_slug_by_title( 'Advanced' );
	}

	$default    = '' !== $advanced ? $advanced : ( $is_network ? 'settings.php' : 'tools.php' );
	$parent     = apply_filters( 'mrn_public_security_admin_parent_slug', $default, $advanced, $is_network );

	return is_string( $parent ) && '' !== $parent ? $parent : $default;
}

/**
 * Get the admin page title.
 *
 * @return string
 */
function mrn_public_security_admin_page_title() {
	$title = apply_filters( 'mrn_public_security_admin_page_title', __( 'Public Security Hardening', 'mrn-public-security-hardening' ) );

	return is_string( $title ) && '' !== $title ? $title : __( 'Public Security Hardening', 'mrn-public-security-hardening' );
}

/**
 * Get the admin menu title.
 *
 * @return string
 */
function mrn_public_security_admin_menu_title() {
	$title = apply_filters( 'mrn_public_security_admin_menu_title', __( 'Public Security', 'mrn-public-security-hardening' ) );

	return is_string( $title ) && '' !== $title ? $title : __( 'Public Security', 'mrn-public-security-hardening' );
}

/**
 * Register the site admin status page.
 */
function mrn_public_security_register_admin_page() {
	$hook = add_submenu_page(
		mrn_public_security_admin_parent_slug(),
		mrn_public_security_admin_page_title(),
		mrn_public_security_admin_menu_title(),
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
		mrn_public_security_admin_parent_slug(),
		mrn_public_security_admin_page_title(),
		mrn_public_security_admin_menu_title(),
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
			'.mrn-public-security-wrap{max-width:1120px}.mrn-public-security-intro{color:#50575e;max-width:760px}.mrn-public-security-panel{background:#fff;border:1px solid #c3c4c7;margin-top:18px}.mrn-public-security-panel__header{align-items:center;border-bottom:1px solid #dcdcde;display:flex;gap:12px;justify-content:space-between;padding:12px 14px}.mrn-public-security-panel__header h2{font-size:14px;line-height:1.4;margin:0}.mrn-public-security-table{border:0}.mrn-public-security-table th,.mrn-public-security-table td{padding:12px 14px;vertical-align:top}.mrn-public-security-table th{font-weight:600;width:210px}.mrn-public-security-table td:nth-child(2){width:190px}.mrn-public-security-table code{word-break:break-word}.mrn-public-security-status{border:1px solid transparent;border-radius:4px;display:inline-block;font-size:12px;font-weight:600;line-height:1.5;padding:1px 7px;white-space:nowrap}.mrn-public-security-status--good{background:#edfaef;border-color:#b8e6bf;color:#0a6b22}.mrn-public-security-status--warn{background:#fcf9e8;border-color:#e6d88a;color:#755100}.mrn-public-security-status--off{background:#f6f7f7;border-color:#dcdcde;color:#50575e}.mrn-public-security-status--info{background:#eef5fb;border-color:#b8d6ee;color:#0a4b78}.mrn-public-security-detail{color:#3c434a}.mrn-public-security-muted{color:#646970}.mrn-public-security-actions{align-items:center;display:flex;gap:10px;justify-content:flex-end}.mrn-public-security-copy-status{color:#2271b1;min-height:20px}.mrn-public-security-login-current{margin:0 0 14px}.mrn-public-security-login-current code{word-break:break-word}.mrn-public-security-prompt-panel{padding:14px}.mrn-public-security-prompt-details{margin-top:12px}.mrn-public-security-prompt-details summary{cursor:pointer;font-weight:600}.mrn-public-security-prompt{font-family:Menlo,Consolas,monospace;font-size:12px;line-height:1.5;margin-top:10px;min-height:260px;width:100%}@media screen and (max-width:782px){.mrn-public-security-panel__header,.mrn-public-security-actions{align-items:flex-start;display:block}.mrn-public-security-actions .button{margin-bottom:8px}.mrn-public-security-table thead{display:none}.mrn-public-security-table tr{display:block}.mrn-public-security-table th,.mrn-public-security-table td{box-sizing:border-box;display:block;width:100%!important}.mrn-public-security-table td{padding-top:4px}.mrn-public-security-table th{padding-bottom:4px}}'
		);

	wp_register_script( 'mrn-public-security-admin', '', array(), MRN_PUBLIC_SECURITY_HARDENING_VERSION, true );
	wp_enqueue_script( 'mrn-public-security-admin' );
	wp_add_inline_script(
		'mrn-public-security-admin',
		"(function(){function setStatus(message){var status=document.getElementById('mrn-public-security-copy-status');if(status){status.textContent=message;}}function fallbackCopy(target,message){var details=target.closest?target.closest('details'):null;if(details){details.open=true;}target.focus();target.select();document.execCommand('copy');setStatus(message);}document.addEventListener('click',function(event){var button=event.target.closest ? event.target.closest('[data-mrn-public-security-copy]') : null;if(!button){return;}var targetId=button.getAttribute('data-mrn-public-security-copy');var target=document.getElementById(targetId);if(!target){return;}var message=button.getAttribute('data-success-message') || 'Copied.';var text=target.value || target.textContent || '';if(window.navigator && window.navigator.clipboard && window.navigator.clipboard.writeText){window.navigator.clipboard.writeText(text).then(function(){setStatus(message);},function(){fallbackCopy(target,message);});return;}fallbackCopy(target,message);});}());"
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
 * Render a row in the admin status table.
 *
 * @param string $label        Feature label.
 * @param string $status       Status key.
 * @param string $status_label Status label.
 * @param string $detail       Escaped detail HTML.
 */
function mrn_public_security_render_admin_status_row( $label, $status, $status_label, $detail ) {
	$allowed_html = array(
		'a'    => array(
			'href'   => true,
			'rel'    => true,
			'target' => true,
		),
		'br'   => array(),
		'code' => array(),
		'span' => array(
			'class' => true,
		),
	);
	?>
	<tr>
		<th scope="row"><?php echo esc_html( $label ); ?></th>
		<td><?php mrn_public_security_render_admin_status_badge( $status, $status_label ); ?></td>
		<td class="mrn-public-security-detail"><?php echo wp_kses( $detail, $allowed_html ); ?></td>
	</tr>
	<?php
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

	$security_txt_fields   = mrn_public_security_get_security_txt_fields();
	$policy_url            = isset( $security_txt_fields['Policy'] ) && is_string( $security_txt_fields['Policy'] ) ? $security_txt_fields['Policy'] : '';
	$contact               = isset( $security_txt_fields['Contact'] ) && is_string( $security_txt_fields['Contact'] ) ? $security_txt_fields['Contact'] : '';
	$expires               = isset( $security_txt_fields['Expires'] ) && is_string( $security_txt_fields['Expires'] ) ? $security_txt_fields['Expires'] : '';
	$canonical             = isset( $security_txt_fields['Canonical'] ) && is_string( $security_txt_fields['Canonical'] ) ? $security_txt_fields['Canonical'] : '';
	$prompt                = mrn_public_security_get_site_completion_prompt();
	$health_slug           = mrn_public_security_get_uptime_robot_check_slug();
	$health_page           = function_exists( 'get_page_by_path' ) ? get_page_by_path( $health_slug, OBJECT, 'page' ) : null;
	$security_txt_url      = home_url( '/.well-known/security.txt' );
	$author_redirect       = mrn_public_security_author_archive_redirect_enabled();
	$author_noindex        = mrn_public_security_author_archive_noindex_enabled();
	$author_redirect_target = apply_filters( 'mrn_public_security_author_archive_redirect_target', home_url( '/' ) );
	$oembed_enabled        = mrn_public_security_oembed_strip_author_enabled();
	$rest_guard_enabled    = mrn_public_security_rest_guard_enabled();
	$security_txt_enabled  = mrn_public_security_security_txt_enabled();
	$login_state           = mrn_public_security_get_login_slug_state();
	$login_slug            = $login_state['effective'];
	$login_path            = mrn_public_security_get_custom_login_path( $login_slug );
	$login_default_slug    = mrn_public_security_get_default_login_slug();
	$login_input_value     = isset( $login_state['display_value'] ) && is_string( $login_state['display_value'] ) ? $login_state['display_value'] : $login_slug;
	$login_page_url        = function_exists( 'menu_page_url' ) ? menu_page_url( 'mrn-public-security-hardening', false ) : '';

	if ( ! is_string( $author_redirect_target ) || '' === $author_redirect_target ) {
		$author_redirect_target = home_url( '/' );
	}

	if ( ! is_string( $login_page_url ) || '' === trim( $login_page_url ) ) {
		$login_page_url = admin_url( 'admin.php?page=mrn-public-security-hardening' );
	}

	if ( $author_redirect ) {
		$author_status       = 'good';
		$author_status_label = __( 'Redirecting', 'mrn-public-security-hardening' );
	} elseif ( $author_noindex ) {
		$author_status       = 'warn';
		$author_status_label = __( 'Noindex only', 'mrn-public-security-hardening' );
	} else {
		$author_status       = 'off';
		$author_status_label = __( 'Disabled', 'mrn-public-security-hardening' );
	}

	$plugin_detail = sprintf(
		/* translators: 1: plugin version, 2: plugin file path. */
		esc_html__( 'Version %1$s', 'mrn-public-security-hardening' ) . '<br><span class="mrn-public-security-muted">%2$s</span>',
		'<code>' . esc_html( MRN_PUBLIC_SECURITY_HARDENING_VERSION ) . '</code>',
		esc_html( plugin_basename( __FILE__ ) )
	);

	$author_detail = sprintf(
		/* translators: 1: redirect target, 2: noindex state. */
		esc_html__( 'Target: %1$s', 'mrn-public-security-hardening' ) . '<br>' . esc_html__( 'Noindex fallback: %2$s', 'mrn-public-security-hardening' ),
		'<code>' . esc_html( esc_url( $author_redirect_target ) ) . '</code>',
		'<code>' . esc_html( $author_noindex ? __( 'Enabled', 'mrn-public-security-hardening' ) : __( 'Disabled', 'mrn-public-security-hardening' ) ) . '</code>'
	);

	$oembed_detail = esc_html__( 'Removed fields:', 'mrn-public-security-hardening' ) . ' <code>author_name</code>, <code>author_url</code>';

	$rest_detail = sprintf(
		/* translators: 1: guarded routes, 2: guarded methods, 3: allowed capabilities. */
		esc_html__( 'Routes: %1$s', 'mrn-public-security-hardening' ) . '<br>' . esc_html__( 'Methods: %2$s', 'mrn-public-security-hardening' ) . '<br>' . esc_html__( 'Allowed caps: %3$s', 'mrn-public-security-hardening' ),
		'<code>' . esc_html( mrn_public_security_format_admin_list_value( mrn_public_security_get_guarded_rest_routes() ) ) . '</code>',
		'<code>' . esc_html( mrn_public_security_format_admin_list_value( mrn_public_security_get_guarded_rest_methods() ) ) . '</code>',
		'<code>' . esc_html( mrn_public_security_format_admin_list_value( mrn_public_security_get_guarded_rest_capabilities() ) ) . '</code>'
	);

	$security_policy_detail = '' !== $policy_url
		? '<a href="' . esc_url( $policy_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $policy_url ) . '</a>'
		: '<span class="mrn-public-security-muted">' . esc_html__( 'Omitted until /privacy-center/ exists or a filter supplies a URL.', 'mrn-public-security-hardening' ) . '</span>';

	$security_txt_detail = sprintf(
		/* translators: 1: security.txt URL, 2: contact field, 3: canonical field, 4: policy field, 5: expires field. */
		esc_html__( 'URL: %1$s', 'mrn-public-security-hardening' ) . '<br>' . esc_html__( 'Contact: %2$s', 'mrn-public-security-hardening' ) . '<br>' . esc_html__( 'Canonical: %3$s', 'mrn-public-security-hardening' ) . '<br>' . esc_html__( 'Policy: %4$s', 'mrn-public-security-hardening' ) . '<br>' . esc_html__( 'Expires: %5$s', 'mrn-public-security-hardening' ),
		'<a href="' . esc_url( $security_txt_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $security_txt_url ) . '</a>',
		'<code>' . esc_html( '' !== $contact ? $contact : __( 'Not set', 'mrn-public-security-hardening' ) ) . '</code>',
		'<code>' . esc_html( '' !== $canonical ? $canonical : __( 'Not set', 'mrn-public-security-hardening' ) ) . '</code>',
		$security_policy_detail,
		'<code>' . esc_html( '' !== $expires ? $expires : __( 'Not set', 'mrn-public-security-hardening' ) ) . '</code>'
	);

		$health_detail = sprintf(
			/* translators: 1: health check slug, 2: page state. */
			esc_html__( 'Slug: %1$s', 'mrn-public-security-hardening' ) . '<br>' . esc_html__( 'Page: %2$s', 'mrn-public-security-hardening' ) . '<br><span class="mrn-public-security-muted">' . esc_html__( 'Robots handling: noindex, nofollow, noarchive; robots.txt disallow; sitemap exclusion.', 'mrn-public-security-hardening' ) . '</span>',
			'<code>' . esc_html( $health_slug ) . '</code>',
			'<code>' . esc_html( $health_page ? __( 'Found', 'mrn-public-security-hardening' ) : __( 'Not found', 'mrn-public-security-hardening' ) ) . '</code>'
		);

	$login_status_label = __( 'Default', 'mrn-public-security-hardening' );
	$login_status       = 'info';

	switch ( $login_state['source'] ) {
		case 'plugin-conflict':
			$login_status_label = __( 'Disabled: competing plugin', 'mrn-public-security-hardening' );
			$login_status       = 'warn';
			break;
		case 'custom':
			$login_status_label = __( 'Saved', 'mrn-public-security-hardening' );
			$login_status       = 'good';
			break;
		case 'conflict':
			$login_status_label = __( 'Conflict', 'mrn-public-security-hardening' );
			$login_status       = 'warn';
			break;
		case 'invalid':
			$login_status_label = __( 'Fallback', 'mrn-public-security-hardening' );
			$login_status       = 'warn';
			break;
	}

	$login_detail = sprintf(
		/* translators: 1: login path, 2: active slug source, 3: default login slug. */
		esc_html__( 'Current path: %1$s', 'mrn-public-security-hardening' ) . '<br>' . esc_html__( 'Source: %2$s', 'mrn-public-security-hardening' ) . '<br>' . esc_html__( 'Default: %3$s', 'mrn-public-security-hardening' ),
		'<code>' . esc_html( $login_path ) . '</code>',
		'<code>' . esc_html( $login_status_label ) . '</code>',
		'<code>' . esc_html( $login_default_slug ) . '</code>'
	);

	if ( ! empty( $login_state['conflict'] ) ) {
		$login_detail .= '<br><span class="mrn-public-security-muted">' . esc_html__( 'This slug currently overlaps with existing content or a rewrite rule. Choose a different slug to avoid ambiguity.', 'mrn-public-security-hardening' ) . '</span>';
	}

	if ( ! empty( $login_state['plugin'] ) && is_array( $login_state['plugin'] ) ) {
		$plugin_name = isset( $login_state['plugin']['name'] ) ? (string) $login_state['plugin']['name'] : '';
		$plugin_file = isset( $login_state['plugin']['file'] ) ? (string) $login_state['plugin']['file'] : '';
		$login_detail .= '<br><span class="mrn-public-security-muted">' . sprintf(
			/* translators: 1: plugin name, 2: plugin file. */
			esc_html__( 'Login URL protection is disabled while %1$s is active (%2$s). Deactivate the competing plugin before enabling this feature.', 'mrn-public-security-hardening' ),
			'<strong>' . esc_html( $plugin_name ) . '</strong>',
			'<code>' . esc_html( $plugin_file ) . '</code>'
		) . '</span>';
	}

	if ( ! empty( $login_state['stored_exists'] ) && empty( $login_state['stored_valid'] ) ) {
		$login_detail .= '<br><span class="mrn-public-security-muted">' . esc_html__( 'The stored value is invalid and the plugin is falling back to the default.', 'mrn-public-security-hardening' ) . '</span>';
	}
		?>
		<div class="wrap mrn-public-security-wrap">
			<h1><?php echo esc_html( mrn_public_security_admin_page_title() ); ?></h1>
			<p class="mrn-public-security-intro">
				<?php esc_html_e( 'Status for shared public hardening behavior and the site-specific login URL setting.', 'mrn-public-security-hardening' ); ?>
			</p>

		<div class="mrn-public-security-panel">
			<div class="mrn-public-security-panel__header">
				<h2><?php esc_html_e( 'Current Status', 'mrn-public-security-hardening' ); ?></h2>
				<?php mrn_public_security_render_admin_status_badge( 'info', __( 'Read-only', 'mrn-public-security-hardening' ) ); ?>
			</div>
			<table class="widefat striped mrn-public-security-table">
				<caption class="screen-reader-text"><?php esc_html_e( 'Current public security hardening status', 'mrn-public-security-hardening' ); ?></caption>
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Area', 'mrn-public-security-hardening' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Status', 'mrn-public-security-hardening' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Details', 'mrn-public-security-hardening' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					mrn_public_security_render_admin_status_row(
						__( 'Plugin', 'mrn-public-security-hardening' ),
						'good',
						__( 'Loaded', 'mrn-public-security-hardening' ),
						$plugin_detail
					);

					mrn_public_security_render_admin_status_row(
						__( 'Author archives', 'mrn-public-security-hardening' ),
						$author_status,
						$author_status_label,
						$author_detail
					);

					mrn_public_security_render_admin_status_row(
						__( 'oEmbed', 'mrn-public-security-hardening' ),
						$oembed_enabled ? 'good' : 'off',
						$oembed_enabled ? __( 'Stripping', 'mrn-public-security-hardening' ) : __( 'Disabled', 'mrn-public-security-hardening' ),
						$oembed_detail
					);

					mrn_public_security_render_admin_status_row(
						__( 'REST scanner guard', 'mrn-public-security-hardening' ),
						$rest_guard_enabled ? 'good' : 'off',
						$rest_guard_enabled ? __( 'Enabled', 'mrn-public-security-hardening' ) : __( 'Disabled', 'mrn-public-security-hardening' ),
						$rest_detail
					);

					mrn_public_security_render_admin_status_row(
						__( 'security.txt', 'mrn-public-security-hardening' ),
						$security_txt_enabled ? 'good' : 'off',
						$security_txt_enabled ? __( 'Serving', 'mrn-public-security-hardening' ) : __( 'Disabled', 'mrn-public-security-hardening' ),
						$security_txt_detail
					);

					mrn_public_security_render_admin_status_row(
						__( 'UptimeRobot page', 'mrn-public-security-hardening' ),
						$health_page ? 'good' : 'warn',
						$health_page ? __( 'Found', 'mrn-public-security-hardening' ) : __( 'Missing', 'mrn-public-security-hardening' ),
						$health_detail
					);
					?>
					</tbody>
				</table>
			</div>

			<div class="mrn-public-security-panel">
				<div class="mrn-public-security-panel__header">
					<h2><?php esc_html_e( 'Login URL', 'mrn-public-security-hardening' ); ?></h2>
					<?php mrn_public_security_render_admin_status_badge( $login_status, $login_status_label ); ?>
				</div>
				<div class="mrn-public-security-prompt-panel">
					<?php settings_errors( mrn_public_security_get_login_slug_option_name() ); ?>
					<p class="description"><?php esc_html_e( 'Set the custom login path slug for this site. WordPress-generated login, logout, lost password, registration, and reset links will use it.', 'mrn-public-security-hardening' ); ?></p>
					<p class="mrn-public-security-login-current"><?php echo wp_kses_post( $login_detail ); ?></p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'mrn_public_security_save_login_slug', 'mrn_public_security_login_slug_nonce' ); ?>
						<input type="hidden" name="action" value="mrn_public_security_save_login_slug" />
						<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $login_page_url ); ?>" />
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row">
									<label for="mrn-public-security-login-slug"><?php esc_html_e( 'Path slug', 'mrn-public-security-hardening' ); ?></label>
								</th>
								<td>
									<input
										type="text"
										class="regular-text code"
										id="mrn-public-security-login-slug"
										name="mrn_public_security_login_slug"
										value="<?php echo esc_attr( $login_input_value ); ?>"
										autocomplete="off"
										autocapitalize="none"
										spellcheck="false"
										pattern="[a-z0-9-]+"
										aria-describedby="mrn-public-security-login-slug-help"
									/>
									<p id="mrn-public-security-login-slug-help" class="description">
										<?php esc_html_e( 'Use one lowercase path segment made of letters, numbers, and hyphens only. Do not use spaces, slashes, query strings, or reserved WordPress paths.', 'mrn-public-security-hardening' ); ?>
									</p>
									<p class="description">
										<?php
										printf(
											/* translators: %s: WP-CLI command to reset the login slug. */
											esc_html__( 'Recovery: run %s if you ever need to restore the default slug.', 'mrn-public-security-hardening' ),
											'<code>wp mrn public-security reset-login-slug</code>'
										);
										?>
									</p>
								</td>
							</tr>
						</table>
						<?php submit_button( __( 'Save Login URL', 'mrn-public-security-hardening' ) ); ?>
					</form>
				</div>
			</div>

				<div class="mrn-public-security-panel">
					<div class="mrn-public-security-panel__header">
						<h2><?php esc_html_e( 'Per-site Rollout Prompt', 'mrn-public-security-hardening' ); ?></h2>
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
					</div>
					<div class="mrn-public-security-prompt-panel">
					<p class="description"><?php esc_html_e( 'Use this to finish site-specific setup and runtime validation.', 'mrn-public-security-hardening' ); ?></p>
					<details class="mrn-public-security-prompt-details">
						<summary><?php esc_html_e( 'Preview prompt', 'mrn-public-security-hardening' ); ?></summary>
						<label class="screen-reader-text" for="mrn-public-security-prompt"><?php esc_html_e( 'Per-site rollout prompt', 'mrn-public-security-hardening' ); ?></label>
						<textarea id="mrn-public-security-prompt" class="large-text code mrn-public-security-prompt" readonly><?php echo esc_textarea( $prompt ); ?></textarea>
					</details>
				</div>
			</div>
		</div>
	<?php
}
