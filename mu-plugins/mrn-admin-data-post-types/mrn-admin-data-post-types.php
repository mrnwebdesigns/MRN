<?php
/**
 * Plugin Name: MRN Admin Data Post Types
 * Description: Makes selected custom post types admin/data-only without blocking programmatic queries.
 * Version: 0.2.0
 * Author: MRN
 */

defined( 'ABSPATH' ) || exit;

const MRN_ADMIN_DATA_POST_TYPES_OPTION = 'mrn_admin_data_post_types_manual';
const MRN_ADMIN_DATA_POST_TYPES_SEARCH_DESTINATIONS_OPTION = 'mrn_admin_data_post_types_search_destinations';

/**
 * Normalize a raw `mrn_admin_data_post_types` filter payload into config shape.
 *
 * A plain key uses the defaults; an associative entry may override
 * `show_ui`, `show_in_menu`, and `admin_cleanup`.
 *
 * @param array $requested Raw filter/option payload.
 * @return array<string, array<string, bool|string>>
 */
function mrn_admin_data_post_types_normalize_config( $requested ) {
	$config = array();

	if ( ! is_array( $requested ) ) {
		return $config;
	}

	foreach ( $requested as $key => $options ) {
		if ( is_int( $key ) ) {
			$post_type = is_string( $options ) ? sanitize_key( $options ) : '';
			$options   = array();
		} else {
			$post_type = sanitize_key( $key );
			$options   = is_array( $options ) ? $options : array();
		}

		if ( '' === $post_type ) {
			continue;
		}

		$config[ $post_type ] = array(
			'show_ui'       => array_key_exists( 'show_ui', $options ) ? (bool) $options['show_ui'] : true,
			'show_in_menu'  => array_key_exists( 'show_in_menu', $options ) ? $options['show_in_menu'] : true,
			'admin_cleanup' => array_key_exists( 'admin_cleanup', $options ) ? (bool) $options['admin_cleanup'] : true,
		);
	}

	return $config;
}

/**
 * Get the CPT keys declared in code via the `mrn_admin_data_post_types`
 * filter, independent of any admin-selected manual selection.
 *
 * @return string[]
 */
function mrn_admin_data_post_types_get_code_declared() {
	return array_keys( mrn_admin_data_post_types_normalize_config( apply_filters( 'mrn_admin_data_post_types', array() ) ) );
}

/**
 * Get the CPT keys an administrator selected through Site Configurations,
 * stored independently of the code-level filter contract.
 *
 * @return string[]
 */
function mrn_admin_data_post_types_get_manual_selection() {
	$stored = get_option( MRN_ADMIN_DATA_POST_TYPES_OPTION, array() );

	if ( ! is_array( $stored ) ) {
		return array();
	}

	return array_values( array_unique( array_filter( array_map( 'sanitize_key', $stored ) ) ) );
}

/**
 * Store the admin-selected CPT list from Site Configurations.
 *
 * Callers are responsible for their own capability checks; this only
 * sanitizes and persists the selection.
 *
 * @param array $post_types Post type keys.
 * @return void
 */
function mrn_admin_data_post_types_set_manual_selection( array $post_types ) {
	$sanitized = array_values( array_unique( array_filter( array_map( 'sanitize_key', $post_types ) ) ) );

	update_option( MRN_ADMIN_DATA_POST_TYPES_OPTION, $sanitized );
}

/**
 * Get the stored post-type -> destination-page-ID search map.
 *
 * @return array<string, int> Post type key => page ID (always > 0).
 */
function mrn_admin_data_post_types_get_search_destinations() {
	$stored = get_option( MRN_ADMIN_DATA_POST_TYPES_SEARCH_DESTINATIONS_OPTION, array() );

	if ( ! is_array( $stored ) ) {
		return array();
	}

	$destinations = array();

	foreach ( $stored as $post_type => $page_id ) {
		$post_type = sanitize_key( (string) $post_type );
		$page_id   = absint( $page_id );

		if ( '' === $post_type || $page_id <= 0 ) {
			continue;
		}

		$destinations[ $post_type ] = $page_id;
	}

	return $destinations;
}

/**
 * Set or clear one CPT's search-result destination page.
 *
 * Callers are responsible for their own capability checks; this only
 * sanitizes and persists the mapping. Pass `$page_id` as `0` to clear an
 * existing entry.
 *
 * @param string $post_type Post type key.
 * @param int    $page_id   Destination page ID, or 0 to clear.
 * @return void
 */
function mrn_admin_data_post_types_set_search_destination( $post_type, $page_id ) {
	$post_type = sanitize_key( (string) $post_type );
	$page_id   = absint( $page_id );

	if ( '' === $post_type ) {
		return;
	}

	$destinations = mrn_admin_data_post_types_get_search_destinations();

	if ( $page_id > 0 ) {
		$destinations[ $post_type ] = $page_id;
	} else {
		unset( $destinations[ $post_type ] );
	}

	update_option( MRN_ADMIN_DATA_POST_TYPES_SEARCH_DESTINATIONS_OPTION, $destinations );
}

/**
 * Whether a CPT has a configured search-result destination.
 *
 * Cheap lookup (no `get_permalink()` call) safe to use during CPT
 * registration.
 *
 * @param string $post_type Post type key.
 * @return bool
 */
function mrn_admin_data_post_types_has_search_destination( $post_type ) {
	$destinations = mrn_admin_data_post_types_get_search_destinations();

	return ! empty( $destinations[ sanitize_key( (string) $post_type ) ] );
}

/**
 * Resolve the URL a search result for this CPT should link to.
 *
 * This is the engine-agnostic integration point: any search implementation
 * (a theme template, a SearchWP-specific filter, Relevanssi, a REST search
 * endpoint, etc.) can call this to get the right link for a content-only
 * post type instead of a broken/absent single URL. Nothing here assumes or
 * wires up a specific search plugin.
 *
 * @param string $post_type Post type key.
 * @return string Destination URL, or '' if none is configured or usable.
 */
function mrn_admin_data_post_types_get_search_destination_url( $post_type ) {
	$post_type = sanitize_key( (string) $post_type );
	$page_id   = mrn_admin_data_post_types_get_search_destinations()[ $post_type ] ?? 0;
	$url       = '';

	if ( $page_id > 0 && 'page' === get_post_type( $page_id ) && 'publish' === get_post_status( $page_id ) ) {
		$url = (string) get_permalink( $page_id );
	}

	/**
	 * Filter the resolved search-result destination URL for a content-only post type.
	 *
	 * @param string $url       Resolved URL, possibly empty.
	 * @param string $post_type Post type key.
	 * @param int    $page_id   Configured destination page ID (0 if none).
	 */
	return (string) apply_filters( 'mrn_admin_data_post_types_search_destination_url', $url, $post_type, $page_id );
}

/**
 * Return the normalized admin/data-only CPT configuration.
 *
 * Theme and plugin code should add CPT keys with the
 * `mrn_admin_data_post_types` filter; administrators may also select CPTs
 * through Site Configurations -> Admin -> Content Types. Code-declared
 * entries always take precedence over a manually-selected entry for the
 * same post type.
 *
 * @return array<string, array<string, bool|string>>
 */
function mrn_admin_data_post_types_get_config() {
	$manual_config = mrn_admin_data_post_types_normalize_config( array_fill_keys( mrn_admin_data_post_types_get_manual_selection(), array() ) );
	$code_config   = mrn_admin_data_post_types_normalize_config( apply_filters( 'mrn_admin_data_post_types', array() ) );

	return array_merge( $manual_config, $code_config );
}

/**
 * Get one selected CPT's configuration.
 *
 * @param string $post_type Post type key.
 * @return array<string, bool|string>|null
 */
function mrn_admin_data_post_types_get_post_type_config( $post_type ) {
	$config = mrn_admin_data_post_types_get_config();

	return isset( $config[ $post_type ] ) ? $config[ $post_type ] : null;
}

/**
 * Enforce the non-public registration contract for selected CPTs.
 *
 * @param array  $args      Registration arguments.
 * @param string $post_type Post type key.
 * @return array
 */
function mrn_admin_data_post_types_filter_registration_args( $args, $post_type ) {
	$config = mrn_admin_data_post_types_get_post_type_config( $post_type );

	if ( null === $config ) {
		return $args;
	}

	return array_merge(
		$args,
		array(
			'public'              => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => ! mrn_admin_data_post_types_has_search_destination( $post_type ),
			'show_in_nav_menus'   => false,
			'has_archive'         => false,
			'rewrite'             => false,
			'query_var'           => false,
			'show_ui'             => $config['show_ui'],
			'show_in_menu'        => $config['show_in_menu'],
		)
	);
}
add_filter( 'register_post_type_args', 'mrn_admin_data_post_types_filter_registration_args', 100, 2 );

/**
 * Whether optional admin cleanup is enabled for a post type.
 *
 * @param string $post_type Post type key.
 * @return bool
 */
function mrn_admin_data_post_types_should_clean_admin( $post_type ) {
	$config = mrn_admin_data_post_types_get_post_type_config( $post_type );

	return null !== $config && ! empty( $config['admin_cleanup'] );
}

/**
 * Remove public View and Preview row actions.
 *
 * @param array    $actions Row actions.
 * @param WP_Post $post    Current post.
 * @return array
 */
function mrn_admin_data_post_types_filter_row_actions( $actions, $post ) {
	if ( isset( $post->post_type ) && mrn_admin_data_post_types_should_clean_admin( $post->post_type ) ) {
		unset( $actions['view'], $actions['preview'] );
	}

	return $actions;
}
add_filter( 'post_row_actions', 'mrn_admin_data_post_types_filter_row_actions', 100, 2 );

/**
 * Disable preview URLs for selected data CPTs.
 *
 * @param string  $preview_link Preview URL.
 * @param WP_Post $post         Current post.
 * @return string
 */
function mrn_admin_data_post_types_filter_preview_link( $preview_link, $post ) {
	if ( isset( $post->post_type ) && mrn_admin_data_post_types_should_clean_admin( $post->post_type ) ) {
		return '';
	}

	return $preview_link;
}
add_filter( 'preview_post_link', 'mrn_admin_data_post_types_filter_preview_link', 100, 2 );

/**
 * Hide sample permalink markup for selected data CPTs.
 *
 * @param string $html    Sample permalink HTML.
 * @param int    $post_id Post ID.
 * @return string
 */
function mrn_admin_data_post_types_filter_sample_permalink( $html, $post_id ) {
	$post_type = get_post_type( $post_id );

	return $post_type && mrn_admin_data_post_types_should_clean_admin( $post_type ) ? '' : $html;
}
add_filter( 'get_sample_permalink_html', 'mrn_admin_data_post_types_filter_sample_permalink', 100, 2 );

/**
 * Explicitly remove selected CPTs from the core XML sitemap registry.
 *
 * @param array $post_types Sitemap post type objects.
 * @return array
 */
function mrn_admin_data_post_types_filter_sitemap_post_types( $post_types ) {
	foreach ( array_keys( mrn_admin_data_post_types_get_config() ) as $post_type ) {
		unset( $post_types[ $post_type ] );
	}

	return $post_types;
}
add_filter( 'wp_sitemaps_post_types', 'mrn_admin_data_post_types_filter_sitemap_post_types', 100 );

/**
 * Exclude every admin/data-only CPT from the SEO Helper plugin's fields.
 *
 * A content-only CPT has no public page, so SEO Helper's title/description/
 * focus-keyword fields have nothing to describe. This covers every current
 * and future content-only CPT automatically, with no per-CPT code needed.
 *
 * @param array $excluded Post type keys already excluded from SEO Helper.
 * @return array
 */
function mrn_admin_data_post_types_filter_seo_helper_excluded_post_types( $excluded ) {
	$excluded = is_array( $excluded ) ? $excluded : array();

	return array_values( array_unique( array_merge( $excluded, array_keys( mrn_admin_data_post_types_get_config() ) ) ) );
}
add_filter( 'mrn_seo_helper_excluded_post_types', 'mrn_admin_data_post_types_filter_seo_helper_excluded_post_types' );
