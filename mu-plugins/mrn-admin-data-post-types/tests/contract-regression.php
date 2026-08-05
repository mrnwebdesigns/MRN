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

function apply_filters( $hook, $value ) {
	if ( 'mrn_admin_data_post_types' === $hook ) {
		return array( 'testimonial', 'announcement' => array( 'admin_cleanup' => false ) );
	}
	return $value;
}

function sanitize_key( $key ): string {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
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

$post    = (object) array( 'post_type' => 'testimonial' );
$actions = mrn_admin_data_post_types_filter_row_actions(
	array( 'edit' => 'Edit', 'view' => 'View', 'preview' => 'Preview' ),
	$post
);
assert_contract( isset( $actions['edit'] ) && ! isset( $actions['view'], $actions['preview'] ), 'Only public row actions should be removed.' );
assert_contract( '' === mrn_admin_data_post_types_filter_preview_link( '/preview/', $post ), 'Preview URL must be disabled.' );
assert_contract( '' === mrn_admin_data_post_types_filter_sample_permalink( '<span>URL</span>', 1 ), 'Sample permalink must be hidden.' );

$registered_hooks = array_keys( $GLOBALS['mrn_test_hooks'] );
assert_contract( ! in_array( 'pre_get_posts', $registered_hooks, true ), 'Programmatic queries must not be intercepted.' );
assert_contract( ! in_array( 'posts_where', $registered_hooks, true ), 'SQL queries must not be altered.' );

$announcement = (object) array( 'post_type' => 'announcement' );
assert_contract( '/preview/' === mrn_admin_data_post_types_filter_preview_link( '/preview/', $announcement ), 'Admin cleanup must be optional.' );

fwrite( STDOUT, "PASS: selected CPTs are admin/data-only while explicit data queries remain untouched.\n" );
