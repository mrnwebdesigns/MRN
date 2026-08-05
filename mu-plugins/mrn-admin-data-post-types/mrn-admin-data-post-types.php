<?php
/**
 * Plugin Name: MRN Admin Data Post Types
 * Description: Makes selected custom post types admin/data-only without blocking programmatic queries.
 * Version: 0.1.0
 * Author: MRN
 */

defined( 'ABSPATH' ) || exit;

/**
 * Return the normalized admin/data-only CPT configuration.
 *
 * Theme and plugin code should add CPT keys with the
 * `mrn_admin_data_post_types` filter. A plain key uses the defaults; an
 * associative entry may override `show_ui`, `show_in_menu`, and
 * `admin_cleanup`.
 *
 * @return array<string, array<string, bool|string>>
 */
function mrn_admin_data_post_types_get_config() {
	$requested = apply_filters( 'mrn_admin_data_post_types', array() );
	$config    = array();

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
			'exclude_from_search' => true,
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
