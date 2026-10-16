<?php
/**
 * SEOPress discovery, output and saved-setting preservation boundaries.
 *
 * @package MRN_Admin_Data_Post_Types
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'seopress_get_taxonomies_list', 'mrn_admin_data_post_types_filter_taxonomies', 100 );
add_filter( 'seopress_sitemaps_cpt', 'mrn_admin_data_post_types_filter_seopress_post_types', 100 );
add_filter( 'seopress_sitemaps_tax', 'mrn_admin_data_post_types_filter_taxonomies', 100 );
add_filter( 'seopress_sitemaps_html_cpt', 'mrn_admin_data_post_types_filter_seopress_post_types', 100 );

/**
 * Mask saved include lists at read time, including direct XML sitemap requests.
 * This option is read independently of WordPressData by Free and PRO renderers.
 *
 * @param mixed $value Sitemap options.
 * @return mixed
 */
function mrn_admin_data_post_types_filter_sitemap_options( $value ) {
	if ( is_array( $value ) ) {
		if ( isset( $value['seopress_xml_sitemap_post_types_list'] ) ) {
			$value['seopress_xml_sitemap_post_types_list'] = mrn_admin_data_post_types_filter_seopress_post_types( $value['seopress_xml_sitemap_post_types_list'] );
		}
		if ( isset( $value['seopress_xml_sitemap_taxonomies_list'] ) ) {
			$value['seopress_xml_sitemap_taxonomies_list'] = mrn_admin_data_post_types_filter_taxonomies( $value['seopress_xml_sitemap_taxonomies_list'] );
		}
	}
	return $value;
}
add_filter( 'option_seopress_xml_sitemap_option_name', 'mrn_admin_data_post_types_filter_sitemap_options', 100 );

/**
 * Keep hidden settings across native settings saves, without filtering titles.
 * WordPress passes a filtered old value to pre_update_option; temporarily remove
 * our sitemap mask to recover exactly the stored include entries for that save.
 *
 * @param mixed  $value New option value.
 * @param mixed  $old   Previous value.
 * @param string $name  Option name.
 * @return mixed
 */
function mrn_admin_data_post_types_preserve_seopress_settings( $value, $old, $name ) {
	if ( ! is_array( $value ) ) {
		return $value;
	}
	if ( 'seopress_xml_sitemap_option_name' === $name ) {
		remove_filter( 'option_seopress_xml_sitemap_option_name', 'mrn_admin_data_post_types_filter_sitemap_options', 100 );
		try {
			$old = get_option( $name, array() );
		} finally {
			add_filter( 'option_seopress_xml_sitemap_option_name', 'mrn_admin_data_post_types_filter_sitemap_options', 100 );
		}
	}
	$groups = array(
		'seopress_titles_single_titles'        => mrn_admin_data_post_types_get_excluded_post_types(),
		'seopress_titles_archive_titles'       => mrn_admin_data_post_types_get_excluded_post_types(),
		'seopress_titles_tax_titles'           => mrn_admin_data_post_types_get_excluded_taxonomies(),
		'seopress_xml_sitemap_post_types_list' => mrn_admin_data_post_types_get_excluded_post_types(),
		'seopress_xml_sitemap_taxonomies_list' => mrn_admin_data_post_types_get_excluded_taxonomies(),
		'seopress_news_name_post_types_list'   => mrn_admin_data_post_types_get_excluded_post_types(),
	);
	foreach ( $groups as $group => $excluded ) {
		foreach ( $excluded as $key ) {
			if ( isset( $old[ $group ] ) && is_array( $old[ $group ] ) && array_key_exists( $key, $old[ $group ] ) ) {
				if ( ! isset( $value[ $group ] ) || ! is_array( $value[ $group ] ) ) {
					$value[ $group ] = array();
				}
				$value[ $group ][ $key ] = $old[ $group ][ $key ];
			}
		}
	}
	return $value;
}
add_filter( 'pre_update_option_seopress_titles_option_name', 'mrn_admin_data_post_types_preserve_seopress_settings', 100, 3 );
add_filter( 'pre_update_option_seopress_xml_sitemap_option_name', 'mrn_admin_data_post_types_preserve_seopress_settings', 100, 3 );
add_filter( 'pre_update_option_seopress_pro_option_name', 'mrn_admin_data_post_types_preserve_seopress_settings', 100, 3 );

/**
 * Guard audit/broken-link worker overrides containing stale saved selections.
 * Empty WP_Query post_type lists broaden discovery, so force zero matches.
 *
 * @param array $args Query arguments.
 * @return array
 */
function mrn_admin_data_post_types_filter_seopress_query( $args ) {
	if ( empty( $args['post_type'] ) ) {
		return $args;
	}
	$types = (array) $args['post_type'];
	if ( in_array( 'any', $types, true ) ) {
		$types = array_values( get_post_types( array( 'public' => true ) ) );
	}
	$types = array_values( array_diff( $types, mrn_admin_data_post_types_get_excluded_post_types() ) );
	if ( empty( $types ) ) {
		$args['post__in'] = array( 0 );
	} else {
		$args['post_type'] = $types;
	}
	return $args;
}
add_filter( 'seopress_site_audit_query', 'mrn_admin_data_post_types_filter_seopress_query', 100 );
add_filter( 'seopress_bot_query', 'mrn_admin_data_post_types_filter_seopress_query', 100 );
// News has a separate saved type list; Video normally uses the XML include list.
add_filter( 'seopress_sitemaps_single_gnews_query', 'mrn_admin_data_post_types_filter_seopress_query', 100 );
add_filter( 'seopress_sitemaps_video_query', 'mrn_admin_data_post_types_filter_seopress_query', 100 );
