<?php
/**
 * Native WordPress integration checks. Run locally with wp eval-file.
 *
 * Loads the candidate modules into this request only; creates and removes one
 * local fixture record and its terms. Never run against a hosted site.
 *
 * @package mrn-base-stack
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) ) {
	throw new RuntimeException( 'Run this fixture only in local/development WordPress.' );
}

$mrn_fixture_theme = dirname( __DIR__, 2 );
if ( ! function_exists( 'mrn_admin_data_post_types_get_state' ) ) {
	require dirname( __DIR__, 5 ) . '/mu-plugins/mrn-admin-data-post-types/mrn-admin-data-post-types.php';
}
if ( ! function_exists( 'mrn_base_stack_register_landing_page_post_type' ) ) {
	require $mrn_fixture_theme . '/inc/landing-pages.php';
}

$mrn_fixture_options = array();
$mrn_fixture_filters = array();
foreach ( array( 'mrn_landing_page_archives_enabled', 'mrn_admin_data_post_types_manual', 'mrn_disabled_post_types' ) as $mrn_fixture_option ) {
	$mrn_fixture_filters[ $mrn_fixture_option ] = static function () use ( &$mrn_fixture_options, $mrn_fixture_option ) {
		$value = $mrn_fixture_options[ $mrn_fixture_option ] ?? array();

		return false === $value ? 0 : $value;
	};
	add_filter( 'pre_option_' . $mrn_fixture_option, $mrn_fixture_filters[ $mrn_fixture_option ] );
}

$mrn_fixture_assert = static function ( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( esc_html( $message ) );
	}
};
$mrn_fixture_post   = 0;
$mrn_fixture_terms  = array();
$mrn_fixture_query  = $GLOBALS['wp_query'];

try {
	foreach ( array( 'public-no-archives', 'public-archives', 'content-only', 'disabled', 'restored' ) as $mrn_fixture_state ) {
		$mrn_fixture_options = array(
			'mrn_landing_page_archives_enabled'   => 'public-no-archives' !== $mrn_fixture_state,
			'mrn_admin_data_post_types_manual'    => 'content-only' === $mrn_fixture_state ? array( 'landing_page' ) : array(),
			'mrn_disabled_post_types'             => 'disabled' === $mrn_fixture_state ? array( 'landing_page' ) : array(),
		);
		foreach ( array( 'landing_page_category', 'landing_page_tag' ) as $mrn_fixture_taxonomy ) {
			if ( taxonomy_exists( $mrn_fixture_taxonomy ) ) {
				unregister_taxonomy( $mrn_fixture_taxonomy );
			}
		}
		if ( post_type_exists( 'landing_page' ) ) {
			unregister_post_type( 'landing_page' );
		}
		mrn_base_stack_register_landing_page_post_type();
		$mrn_fixture_type     = get_post_type_object( 'landing_page' );
		$mrn_fixture_public   = ! in_array( $mrn_fixture_state, array( 'content-only', 'disabled' ), true );
		$mrn_fixture_archives = $mrn_fixture_public && 'public-no-archives' !== $mrn_fixture_state;

		$mrn_fixture_assert( $mrn_fixture_public === $mrn_fixture_type->publicly_queryable, 'Single-page exposure: ' . $mrn_fixture_state );
		$mrn_fixture_assert( $mrn_fixture_archives === (bool) $mrn_fixture_type->has_archive, 'Archive exposure: ' . $mrn_fixture_state );
		$mrn_fixture_assert( ( 'disabled' !== $mrn_fixture_state ) === $mrn_fixture_type->show_ui, 'Editing UI: ' . $mrn_fixture_state );
		$mrn_fixture_assert( ( 'disabled' !== $mrn_fixture_state ) === $mrn_fixture_type->show_in_rest, 'REST registration: ' . $mrn_fixture_state );
		$mrn_fixture_assert( array( 'landing_page_category', 'landing_page_tag' ) === get_object_taxonomies( 'landing_page' ), 'Taxonomies must not share blog categories/tags.' );
		$mrn_fixture_assert( get_taxonomy( 'landing_page_category' )->hierarchical && ! get_taxonomy( 'landing_page_tag' )->hierarchical, 'Category/tag editor semantics.' );
		foreach ( array( 'landing_page_category', 'landing_page_tag' ) as $mrn_fixture_taxonomy ) {
			$mrn_fixture_assert( $mrn_fixture_archives === get_taxonomy( $mrn_fixture_taxonomy )->publicly_queryable, 'Taxonomy exposure: ' . $mrn_fixture_state );
		}

		if ( ! $mrn_fixture_post ) {
			$mrn_fixture_post = wp_insert_post( array( 'post_type' => 'landing_page', 'post_status' => 'draft', 'post_title' => 'MRN Landing Page qualification fixture' ), true );
			$mrn_fixture_assert( ! is_wp_error( $mrn_fixture_post ), 'Fixture record creation.' );
			foreach ( array( 'landing_page_category', 'landing_page_tag' ) as $mrn_fixture_taxonomy ) {
				$mrn_fixture_term = wp_insert_term( 'MRN Landing qualification ' . wp_generate_uuid4(), $mrn_fixture_taxonomy );
				$mrn_fixture_assert( ! is_wp_error( $mrn_fixture_term ), 'Fixture term creation.' );
				$mrn_fixture_terms[ $mrn_fixture_taxonomy ] = (int) $mrn_fixture_term['term_id'];
				wp_set_object_terms( $mrn_fixture_post, array( (int) $mrn_fixture_term['term_id'] ), $mrn_fixture_taxonomy );
			}
		}
		$mrn_fixture_found = get_posts( array( 'post_type' => 'landing_page', 'post_status' => 'draft', 'include' => array( $mrn_fixture_post ), 'fields' => 'ids' ) );
		$mrn_fixture_assert( array( $mrn_fixture_post ) === $mrn_fixture_found, 'Explicit component queries retain content: ' . $mrn_fixture_state );
		foreach ( $mrn_fixture_terms as $mrn_fixture_taxonomy => $mrn_fixture_term ) {
			$mrn_fixture_assert( has_term( $mrn_fixture_term, $mrn_fixture_taxonomy, $mrn_fixture_post ), 'Terms survive exposure changes: ' . $mrn_fixture_state );
		}

		$GLOBALS['wp_query']                       = new WP_Query();
		$GLOBALS['wp_query']->is_post_type_archive = true;
		$GLOBALS['wp_query']->is_archive           = true;
		$GLOBALS['wp_query']->set( 'post_type', 'landing_page' );
		mrn_base_stack_guard_landing_page_archives();
		$mrn_fixture_assert( ! $mrn_fixture_archives === $GLOBALS['wp_query']->is_404(), 'Query-string archive guard: ' . $mrn_fixture_state );
		WP_CLI::log( 'PASS: ' . $mrn_fixture_state );
	}
	$mrn_fixture_assert( in_array( 'landing_page', mrn_base_stack_get_builder_supported_post_types(), true ), 'Content builder registration.' );
	$mrn_fixture_assert( in_array( 'landing_page', mrn_base_stack_get_hero_supported_post_types(), true ), 'Hero registration.' );
	$mrn_fixture_assert( in_array( 'landing_page', mrn_base_stack_get_after_content_supported_post_types(), true ), 'After Content registration.' );
	WP_CLI::success( 'Landing Page native CPT, taxonomy, exposure, REST, retention and builder checks passed.' );
} finally {
	$GLOBALS['wp_query'] = $mrn_fixture_query;
	if ( is_int( $mrn_fixture_post ) && $mrn_fixture_post > 0 ) {
		wp_delete_post( $mrn_fixture_post, true );
	}
	foreach ( $mrn_fixture_terms as $mrn_fixture_taxonomy => $mrn_fixture_term ) {
		wp_delete_term( $mrn_fixture_term, $mrn_fixture_taxonomy );
	}
	foreach ( $mrn_fixture_filters as $mrn_fixture_option => $mrn_fixture_filter ) {
		remove_filter( 'pre_option_' . $mrn_fixture_option, $mrn_fixture_filter );
	}
}
