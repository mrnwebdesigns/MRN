<?php
/**
 * Paid Landing Pages stay available to ad crawlers but outside organic discovery.
 *
 * @package mrn-base-stack
 */

/**
 * Apply the paid-page policy in SEOPress's own settings and editor UI.
 *
 * @param mixed $options SEOPress title settings.
 * @return array
 */
function mrn_base_stack_landing_page_seo_title_defaults( $options ) {
	$options = is_array( $options ) ? $options : array();
	$options['seopress_titles_single_titles']['landing_page']['noindex']  = '1';
	$options['seopress_titles_archive_titles']['landing_page']['noindex'] = '1';
	foreach ( array( 'landing_page_category', 'landing_page_tag' ) as $taxonomy ) {
		$options['seopress_titles_tax_titles'][ $taxonomy ]['noindex'] = '1';
	}
	return $options;
}
add_filter( 'option_seopress_titles_option_name', 'mrn_base_stack_landing_page_seo_title_defaults' );
add_filter( 'default_option_seopress_titles_option_name', 'mrn_base_stack_landing_page_seo_title_defaults' );

/**
 * Exclude the paid CPT and its terms from SEOPress XML and derived HTML maps.
 *
 * @param mixed $options SEOPress sitemap settings.
 * @return array
 */
function mrn_base_stack_landing_page_seo_sitemap_defaults( $options ) {
	$options = is_array( $options ) ? $options : array();
	$groups = array(
		'seopress_xml_sitemap_post_types_list' => array( 'landing_page' ),
		'seopress_xml_sitemap_taxonomies_list' => array( 'landing_page_category', 'landing_page_tag' ),
	);
	foreach ( $groups as $group => $types ) {
		foreach ( $types as $type ) {
			if ( ! is_array( $options[ $group ][ $type ] ?? null ) ) {
				$options[ $group ][ $type ] = array();
			}
			$options[ $group ][ $type ]['include'] = '';
		}
	}
	return $options;
}
add_filter( 'option_seopress_xml_sitemap_option_name', 'mrn_base_stack_landing_page_seo_sitemap_defaults' );
add_filter( 'default_option_seopress_xml_sitemap_option_name', 'mrn_base_stack_landing_page_seo_sitemap_defaults' );

/**
 * Identify only Landing Page documents and their dedicated archives.
 *
 * @return bool
 */
function mrn_base_stack_is_landing_page_seo_request() {
	return is_singular( 'landing_page' ) || is_post_type_archive( 'landing_page' ) || is_tax( array( 'landing_page_category', 'landing_page_tag' ) );
}

/**
 * Keep noindex in place if SEOPress is unavailable; retain stricter local rules.
 *
 * @param array $robots WordPress robots directives.
 * @return array
 */
function mrn_base_stack_landing_page_wp_robots( $robots ) {
	if ( mrn_base_stack_is_landing_page_seo_request() ) {
		unset( $robots['index'] );
		$robots['noindex'] = true;
	}
	return $robots;
}
add_filter( 'wp_robots', 'mrn_base_stack_landing_page_wp_robots', 20 );

/** Send the same index policy for HTML and feeds without blocking AdsBot. */
function mrn_base_stack_landing_page_robots_header() {
	if ( ! is_admin() && mrn_base_stack_is_landing_page_seo_request() && ! headers_sent() ) {
		header( 'X-Robots-Tag: noindex', false );
	}
}
add_action( 'template_redirect', 'mrn_base_stack_landing_page_robots_header', 1 );

/**
 * Keep core sitemaps unlisted when SEOPress is inactive.
 *
 * @param array $types Core sitemap post types or taxonomies.
 * @return array
 */
function mrn_base_stack_landing_page_core_sitemap_types( $types ) {
	unset( $types['landing_page'], $types['landing_page_category'], $types['landing_page_tag'] );
	return $types;
}
add_filter( 'wp_sitemaps_post_types', 'mrn_base_stack_landing_page_core_sitemap_types' );
add_filter( 'wp_sitemaps_taxonomies', 'mrn_base_stack_landing_page_core_sitemap_types' );

/**
 * Also respect exclusion when an HTML sitemap explicitly names this CPT.
 *
 * @param array  $query Query arguments.
 * @param string $post_type Requested sitemap post type.
 * @return array
 */
function mrn_base_stack_landing_page_html_sitemap_query( $query, $post_type ) {
	if ( 'landing_page' === $post_type ) {
		$query['post__in'] = array( 0 );
	}
	return $query;
}
add_filter( 'seopress_sitemaps_html_query', 'mrn_base_stack_landing_page_html_sitemap_query', 10, 2 );

/**
 * Do not automatically submit paid pages to IndexNow when their status changes.
 * Keep the CPT available to SEOPress's normal editor/settings interfaces.
 *
 * @param array $post_types SEOPress post type objects.
 * @return array
 */
function mrn_base_stack_landing_page_auto_indexing_types( $post_types ) {
	if ( doing_action( 'transition_post_status' ) ) {
		unset( $post_types['landing_page'] );
	}
	return $post_types;
}
add_filter( 'seopress_post_types', 'mrn_base_stack_landing_page_auto_indexing_types' );
