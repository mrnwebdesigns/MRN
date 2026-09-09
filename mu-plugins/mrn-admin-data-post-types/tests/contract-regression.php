<?php
/**
 * Lightweight regression test for the admin/data-only CPT contract.
 *
 * Run: php tests/contract-regression.php
 */

declare(strict_types=1);

define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );

$GLOBALS['mrn_test_hooks'] = array();
$GLOBALS['mrn_test_type']  = 'testimonial';

function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ): bool {
	$GLOBALS['mrn_test_hooks'][ $hook ][] = compact( 'callback', 'priority', 'accepted_args' );
	return true;
}

function apply_filters( $hook, $value, ...$args ) {
	if ( isset( $GLOBALS['mrn_test_hooks'][ $hook ] ) ) {
		$callbacks = $GLOBALS['mrn_test_hooks'][ $hook ];

		usort(
			$callbacks,
			static function ( $a, $b ): int {
				return $a['priority'] <=> $b['priority'];
			}
		);

		foreach ( $callbacks as $callback ) {
			$accepted_args = max( 1, (int) $callback['accepted_args'] );
			$call_args     = array_merge( array( $value ), array_slice( $args, 0, $accepted_args - 1 ) );
			$value         = call_user_func_array( $callback['callback'], $call_args );
		}

		return $value;
	}

	if ( 'mrn_admin_data_post_types' === $hook ) {
		return array( 'testimonial', 'announcement' => array( 'admin_cleanup' => false ) );
	}

	return $value;
}

function sanitize_key( $key ): string {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
}

function get_option( $option, $default = array() ) {
	unset( $option );

	return $default;
}

function update_option( $option, $value ) {
	unset( $option, $value );

	return true;
}

function get_post_type( $post_id ): string {
	unset( $post_id );
	return $GLOBALS['mrn_test_type'];
}

function assert_contract( bool $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

require dirname( __DIR__ ) . '/mrn-admin-data-post-types.php';

$args = mrn_admin_data_post_types_filter_registration_args(
	array( 'show_in_rest' => true, 'supports' => array( 'title', 'editor' ) ),
	'testimonial'
);

foreach ( array( 'public', 'publicly_queryable', 'show_in_nav_menus', 'has_archive', 'rewrite', 'query_var' ) as $key ) {
	assert_contract( false === $args[ $key ], "{$key} must be false." );
}
assert_contract( true === $args['exclude_from_search'], 'CPT must be excluded from search.' );
assert_contract( true === $args['show_ui'] && true === $args['show_in_menu'], 'Admin UI must remain available.' );
assert_contract( true === $args['show_in_rest'], 'Unrelated registration args must remain intact.' );
assert_contract( array( 'title', 'editor' ) === $args['supports'], 'Editor support must remain intact.' );

$sitemap_types = mrn_admin_data_post_types_filter_sitemap_post_types(
	array( 'post' => (object) array(), 'testimonial' => (object) array() )
);
assert_contract( ! isset( $sitemap_types['testimonial'] ), 'Selected CPT must be excluded from core sitemaps.' );

$registered_hooks = array_keys( $GLOBALS['mrn_test_hooks'] );
assert_contract( in_array( 'seopress_post_types', $registered_hooks, true ), 'SEOPress post-type filtering must be registered.' );

$seopress_associative = array(
	'gallery' => (object) array( 'post_type' => 'gallery', 'label' => 'Gallery' ),
	'testimonial' => (object) array( 'post_type' => 'testimonial', 'label' => 'Testimonials' ),
	'case_study' => array( 'value' => 'case_study', 'label' => 'Case Studies' ),
	'service' => array( 'name' => 'service', 'label' => 'Services' ),
	'internal_report' => (object) array( 'slug' => 'internal_report', 'label' => 'Internal Report' ),
	'announcement' => array( 'value' => 'announcement', 'label' => 'Announcements' ),
);
$filtered_associative = apply_filters( 'seopress_post_types', $seopress_associative );
assert_contract( ! isset( $filtered_associative['testimonial'], $filtered_associative['announcement'] ), 'Configured content-only CPTs must be removed from SEOPress associative collections.' );
assert_contract( isset( $filtered_associative['gallery'], $filtered_associative['case_study'], $filtered_associative['service'], $filtered_associative['internal_report'] ), 'Public and unrelated CPTs must remain in SEOPress associative collections.' );
assert_contract( array_keys( $filtered_associative ) === array( 'gallery', 'case_study', 'service', 'internal_report' ), 'Associative SEOPress collections must preserve unrelated keys.' );
assert_contract( $filtered_associative['gallery'] === $seopress_associative['gallery'], 'Associative SEOPress entries must remain untouched.' );

$seopress_numeric = array(
	0  => (object) array( 'post_type' => 'gallery', 'label' => 'Gallery' ),
	2  => array( 'value' => 'testimonial', 'label' => 'Testimonials' ),
	5  => (object) array( 'slug' => 'case_study', 'label' => 'Case Studies' ),
	8  => array( 'name' => 'service', 'label' => 'Services' ),
	13 => (object) array( 'value' => 'internal_report', 'label' => 'Internal Report' ),
);
$filtered_numeric = apply_filters( 'seopress_post_types', $seopress_numeric );
assert_contract( ! isset( $filtered_numeric[2] ), 'Configured content-only CPTs must be removed from SEOPress numeric collections.' );
assert_contract( isset( $filtered_numeric[0], $filtered_numeric[5], $filtered_numeric[8], $filtered_numeric[13] ), 'Public and unrelated CPTs must remain in SEOPress numeric collections.' );
assert_contract( array_keys( $filtered_numeric ) === array( 0, 5, 8, 13 ), 'Numeric SEOPress collections must preserve unrelated indexes.' );

$post    = (object) array( 'post_type' => 'testimonial' );
$actions = mrn_admin_data_post_types_filter_row_actions(
	array( 'edit' => 'Edit', 'view' => 'View', 'preview' => 'Preview' ),
	$post
);
assert_contract( isset( $actions['edit'] ) && ! isset( $actions['view'], $actions['preview'] ), 'Only public row actions should be removed.' );
assert_contract( '' === mrn_admin_data_post_types_filter_preview_link( '/preview/', $post ), 'Preview URL must be disabled.' );
assert_contract( '' === mrn_admin_data_post_types_filter_sample_permalink( '<span>URL</span>', 1 ), 'Sample permalink must be hidden.' );

assert_contract( ! in_array( 'pre_get_posts', $registered_hooks, true ), 'Programmatic queries must not be intercepted.' );
assert_contract( ! in_array( 'posts_where', $registered_hooks, true ), 'SQL queries must not be altered.' );

$announcement = (object) array( 'post_type' => 'announcement' );
assert_contract( '/preview/' === mrn_admin_data_post_types_filter_preview_link( '/preview/', $announcement ), 'Admin cleanup must be optional.' );

fwrite( STDOUT, "PASS: selected CPTs are admin/data-only while explicit data queries remain untouched.\n" );
