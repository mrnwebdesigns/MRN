<?php
/**
 * Local WordPress/SEOPress integration checks for the paid-page policy.
 * Run with wp --user=<administrator> eval-file; never run on a hosted site.
 *
 * @package mrn-base-stack
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI || 'local' !== wp_get_environment_type() || ! current_user_can( 'manage_options' ) ) {
	throw new RuntimeException( 'This fixture requires an administrator on a local runtime.' );
}
if ( ! function_exists( 'mrn_base_stack_landing_page_seo_title_defaults' ) || ! function_exists( 'seopress_get_service' ) ) {
	throw new RuntimeException( 'Install the candidate Landing Page module and native SEOPress first.' );
}
$check = static function ( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( esc_html( $message ) );
	}
};
$ids = array();
try {
	$ids[] = wp_insert_post( array( 'post_type' => 'landing_page', 'post_status' => 'publish', 'post_title' => 'MRN paid SEO fixture 20261106' ) );
	$ids[] = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'MRN organic SEO fixture 20261106' ) );
	$check( $ids[0] > 0 && $ids[1] > 0, 'Fixture creation failed.' );
	$check( '1' === seopress_get_service( 'TitleOption' )->getSingleCptNoIndex( $ids[0] ), 'A new paid page must inherit noindex without per-page metadata.' );
	$type = get_post_type_object( 'landing_page' );
	$check( $type->exclude_from_search && ! $type->show_in_nav_menus, 'Paid pages must be unlisted by default.' );
	$results = new WP_Query( array( 's' => 'MRN paid SEO fixture 20261106', 'fields' => 'ids' ) );
	$check( ! in_array( $ids[0], $results->posts, true ), 'Default site search exposed a paid page.' );
	$maps = seopress_get_service( 'SitemapOption' )->getPostTypesList();
	$check( empty( $maps['landing_page']['include'] ), 'SEOPress sitemap inclusion must be off.' );
	$html = do_shortcode( '[seopress_html_sitemap cpt="landing_page"]' );
	$check( false === strpos( $html, get_permalink( $ids[0] ) ), 'An explicit HTML sitemap exposed a paid URL.' );
	$core = new WP_Sitemaps_Posts();
	$check( ! isset( $core->get_object_subtypes()['landing_page'] ), 'Core sitemap exposed the paid CPT.' );
	$types = seopress_get_service( 'WordPressData' )->getPostTypes();
	$check( isset( $types['landing_page'] ), 'SEOPress normal editor/settings discovery must retain the CPT.' );
	// Exercise the actual native automatic submission callback with all HTTP intercepted.
	$requests = 0;
	$http = static function () use ( &$requests ) {
		++$requests;
		return array( 'headers' => array(), 'body' => '', 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array() );
	};
	$toggle = static function () { return array( 'toggle-instant-indexing' => '1' ); };
	$options = static function () { return array( 'seopress_instant_indexing_automate_submission' => '1', 'engines' => array( 'bing' => '1' ), 'seopress_instant_indexing_bing_api_key' => 'mrn-local-fixture-key-20261106' ); };
	add_filter( 'pre_http_request', $http, 1 );
	add_filter( 'pre_option_seopress_toggle', $toggle );
	add_filter( 'pre_option_seopress_instant_indexing_option_name', $options );
	$native = static function ( $new, $old, $post ) { seopress_instant_indexing_on_post_publish( $new, $old, $post ); };
	// Native transition context is required by the post-type exclusion.
	$auto = static function ( $new, $old, $post ) use ( $native ) { $native( $new, $old, $post ); };
	remove_action( 'transition_post_status', 'seopress_instant_indexing_on_post_publish', 10 );
	add_action( 'transition_post_status', $auto, 10, 3 );
	do_action( 'transition_post_status', 'publish', 'publish', get_post( $ids[0] ) );
	$check( 0 === $requests, 'Paid page triggered an automatic indexing request.' );
	do_action( 'transition_post_status', 'publish', 'publish', get_post( $ids[1] ) );
	$check( $requests > 0, 'Organic control did not exercise the intercepted native indexing transport.' );
	echo "PASS: new-page noindex, search/menu exclusion, XML/HTML/core sitemaps, SEOPress UI discovery and native automatic-indexing isolation.\n";
} finally {
	remove_filter( 'pre_http_request', $http ?? '__return_false', 1 );
	remove_filter( 'pre_option_seopress_toggle', $toggle ?? '__return_false' );
	remove_filter( 'pre_option_seopress_instant_indexing_option_name', $options ?? '__return_false' );
	remove_action( 'transition_post_status', $auto ?? '__return_false', 10 );
	add_action( 'transition_post_status', 'seopress_instant_indexing_on_post_publish', 10, 3 );
	foreach ( $ids as $id ) {
		wp_delete_post( $id, true );
	}
}
