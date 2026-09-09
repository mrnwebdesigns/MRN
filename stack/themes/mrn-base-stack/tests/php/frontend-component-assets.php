<?php
// phpcs:ignoreFile -- Standalone WordPress stub harness for component asset discovery.
/**
 * Regression coverage for conditional front-end component assets and preloads.
 *
 * @package mrn-base-stack
 */

define( 'ABSPATH', __DIR__ );
define( '_S_VERSION', 'test' );

$GLOBALS['mrn_component_test_meta'] = array(
	10 => array(
		'page_content_rows' => array( array( 'basic_block' ) ),
	),
	11 => array(
		'page_content_rows'                => array( array( 'slider' ) ),
		'page_content_rows_0_slider_items' => array( '2' ),
	),
	12 => array(
		'page_content_rows'         => array( array( 'tabbed_layout' ) ),
		'page_content_rows_0_tabs'  => array( '2' ),
	),
	13 => array(
		'page_content_rows'                      => array( array( 'video' ) ),
		'page_content_rows_0_video_display_mode' => array( 'modal' ),
		'page_content_rows_0_video_remote'       => array( 'https://video.example.test/modal' ),
		'page_content_rows_0_video_thumbnail'    => array( '123' ),
	),
	14 => array(
		'page_content_rows' => array( array( 'faq' ) ),
	),
	15 => array(
		'page_content_rows'                           => array( array( 'basic_block' ) ),
		'page_content_rows_0_motion_settings_enabled' => array( '1' ),
	),
	16 => array(
		'page_content_rows'                    => array( array( 'basic_block' ) ),
		'page_content_rows_0_background_video' => array( 'https://video.example.test/hero' ),
	),
	17 => array(
		'page_content_rows'                => array( array( 'logos' ) ),
		'page_content_rows_0_display_mode' => array( 'slider' ),
		'page_content_rows_0_logo_items'   => array( '3' ),
	),
	18 => array(
		'page_content_rows'                  => array( array( 'content_lists' ) ),
		'page_content_rows_0_list_post_type' => array( 'testimonial' ),
	),
	19 => array(
		'page_content_rows'                => array( array( 'video' ) ),
		'page_content_rows_0_video_upload' => array( '456' ),
	),
	21 => array(
		'page_content_rows' => array( array( 'slider', 'tabbed_layout', 'video', 'logos', 'content_lists' ) ),
	),
	22 => array(
		'page_content_rows' => array( array( 'faq_block' ) ),
	),
	23 => array(
		'page_content_rows'         => array( array( 'reusable_block' ) ),
		'page_content_rows_0_block' => array( '24' ),
	),
	24 => array(
		'faq_items' => array( '2' ),
	),
	25 => array(
		'display_mode' => array( 'slider' ),
		'logo_items'   => array( '4' ),
	),
);
$GLOBALS['mrn_component_test_content'] = array(
	20 => '<div data-gallery-root></div>',
);
$GLOBALS['mrn_component_test_registered_styles']  = array();
$GLOBALS['mrn_component_test_registered_scripts'] = array();
$GLOBALS['mrn_component_test_enqueued_styles']    = array();
$GLOBALS['mrn_component_test_enqueued_scripts']   = array();
$GLOBALS['mrn_component_test_filters']            = array();
$GLOBALS['mrn_component_test_post_types']         = array(
	24 => 'mrn_reusable_faq',
	25 => 'mrn_reusable_partner',
);

function add_filter( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
	return true;
}

function apply_filters( $hook_name, $value, ...$args ) {
	return array_key_exists( $hook_name, $GLOBALS['mrn_component_test_filters'] )
		? $GLOBALS['mrn_component_test_filters'][ $hook_name ]
		: $value;
}

function sanitize_key( $value ) {
	return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $value ) );
}

function sanitize_title( $value ) {
	return sanitize_key( str_replace( ' ', '-', (string) $value ) );
}

function sanitize_mime_type( $value ) {
	return strtolower( preg_replace( '/[^a-z0-9+\.\-\/]/', '', (string) $value ) );
}

function absint( $value ) {
	return abs( (int) $value );
}

function maybe_unserialize( $value ) {
	return $value;
}

function esc_url_raw( $value ) {
	return (string) $value;
}

function trailingslashit( $value ) {
	return rtrim( (string) $value, '/\\' ) . '/';
}

function get_post_meta( $post_id, $key = '', $single = false ) {
	return $GLOBALS['mrn_component_test_meta'][ (int) $post_id ] ?? array();
}

function get_post_field( $field, $post_id ) {
	return $GLOBALS['mrn_component_test_content'][ (int) $post_id ] ?? '';
}

function get_post_type( $post_id ) {
	return $GLOBALS['mrn_component_test_post_types'][ (int) $post_id ] ?? 'page';
}

function get_template_directory() {
	return dirname( __DIR__, 2 );
}

function get_template_directory_uri() {
	return 'https://stack.example.test/wp-content/themes/mrn-base-stack';
}

function wp_register_style( $handle, $src, $deps = array(), $version = false ) {
	$GLOBALS['mrn_component_test_registered_styles'][ $handle ] = array(
		'src'  => $src,
		'deps' => $deps,
		'ver'  => $version,
	);
}

function wp_register_script( $handle, $src, $deps = array(), $version = false, $footer = false ) {
	$GLOBALS['mrn_component_test_registered_scripts'][ $handle ] = array(
		'src'    => $src,
		'deps'   => $deps,
		'ver'    => $version,
		'footer' => $footer,
	);
}

function wp_enqueue_style( $handle, $src = '', $deps = array(), $version = false ) {
	$GLOBALS['mrn_component_test_enqueued_styles'][ $handle ] = true;
}

function wp_enqueue_script( $handle, $src = '', $deps = array(), $version = false, $footer = false ) {
	$GLOBALS['mrn_component_test_enqueued_scripts'][ $handle ] = true;
}

function wp_get_attachment_image_url( $attachment_id, $size ) {
	return 'https://cdn.example.test/critical-wordmark.svg';
}

function get_post_mime_type( $attachment_id ) {
	return 'image/svg+xml';
}

function mrn_component_test_assert( $condition, $message ) {
	if ( $condition ) {
		return;
	}

	fwrite( STDERR, "FAIL: {$message}\n" );
	exit( 1 );
}

function mrn_component_test_assert_only_need( $post_id, $expected_component ) {
	$needs = mrn_base_stack_get_front_end_component_needs_for_post( $post_id );

	foreach ( mrn_base_stack_get_default_front_end_component_needs() as $component => $unused ) {
		mrn_component_test_assert(
			( $component === $expected_component ) === ! empty( $needs[ $component ] ),
			"Post {$post_id} component {$component} requirement mismatch."
		);
	}
}

require dirname( __DIR__, 2 ) . '/inc/frontend-assets.php';

$simple_needs = mrn_base_stack_get_front_end_component_needs_for_post( 10 );
mrn_component_test_assert( empty( array_filter( $simple_needs ) ), 'Simple pages do not request component assets.' );
$legacy_needs = mrn_base_stack_get_legacy_front_end_component_needs();
mrn_component_test_assert( ! empty( $legacy_needs['slider'] ) && empty( $legacy_needs['gallery'] ), 'Legacy fallback preserves the historical non-gallery runtime.' );

mrn_component_test_assert_only_need( 11, 'slider' );
mrn_component_test_assert_only_need( 12, 'tabs' );
mrn_component_test_assert_only_need( 13, 'video_modal' );
mrn_component_test_assert_only_need( 14, 'faq' );
mrn_component_test_assert_only_need( 15, 'motion' );
mrn_component_test_assert_only_need( 16, 'deferred_media' );
mrn_component_test_assert_only_need( 17, 'slider' );
mrn_component_test_assert_only_need( 18, 'deferred_media' );
mrn_component_test_assert_only_need( 19, 'deferred_media' );
mrn_component_test_assert_only_need( 20, 'gallery' );
mrn_component_test_assert( empty( array_filter( mrn_base_stack_get_front_end_component_needs_for_post( 21 ) ) ), 'Empty component rows do not request unused assets.' );
mrn_component_test_assert_only_need( 22, 'faq' );
mrn_component_test_assert_only_need( 23, 'faq' );
mrn_component_test_assert_only_need( 25, 'slider' );

$expected_assets = array(
	'slider'         => array( 'style' => 'mrn-base-stack-splide', 'script' => 'mrn-base-stack-front-end-slider', 'dependency' => 'mrn-base-stack-splide' ),
	'tabs'           => array( 'style' => 'mrn-base-stack-splide', 'script' => 'mrn-base-stack-front-end-tabs', 'dependency' => 'mrn-base-stack-splide' ),
	'video_modal'    => array( 'style' => 'mrn-base-stack-glightbox', 'script' => 'mrn-base-stack-front-end-video-modal', 'dependency' => 'mrn-base-stack-glightbox' ),
	'gallery'        => array( 'style' => 'mrn-base-stack-glightbox', 'script' => 'mrn-base-stack-front-end-gallery', 'dependency' => 'mrn-base-stack-glightbox' ),
	'motion'         => array( 'script' => 'mrn-base-stack-front-end-effects', 'dependency' => 'mrn-base-stack-motion' ),
	'faq'            => array( 'script' => 'mrn-base-stack-front-end-faq' ),
	'deferred_media' => array( 'script' => 'mrn-base-stack-front-end-deferred-media' ),
);

foreach ( $expected_assets as $component => $assets ) {
	$GLOBALS['mrn_component_test_enqueued_styles']  = array();
	$GLOBALS['mrn_component_test_enqueued_scripts'] = array();
	mrn_base_stack_enqueue_front_end_component_assets( array( $component => true ) );

	if ( isset( $assets['style'] ) ) {
		mrn_component_test_assert( isset( $GLOBALS['mrn_component_test_enqueued_styles'][ $assets['style'] ] ), "{$component} style remains enqueued." );
	}
	mrn_component_test_assert( isset( $GLOBALS['mrn_component_test_enqueued_scripts'][ $assets['script'] ] ), "{$component} script remains enqueued." );

	if ( isset( $assets['dependency'] ) ) {
		$dependencies = $GLOBALS['mrn_component_test_registered_scripts'][ $assets['script'] ]['deps'] ?? array();
		mrn_component_test_assert( in_array( $assets['dependency'], $dependencies, true ), "{$component} dependency ordering remains explicit." );
	}
}

$GLOBALS['mrn_component_test_enqueued_styles']  = array();
$GLOBALS['mrn_component_test_enqueued_scripts'] = array();
mrn_base_stack_enqueue_front_end_component_assets( $simple_needs );
mrn_component_test_assert( empty( $GLOBALS['mrn_component_test_enqueued_styles'] ), 'Simple fixture enqueues no component styles.' );
mrn_component_test_assert( empty( $GLOBALS['mrn_component_test_enqueued_scripts'] ), 'Simple fixture enqueues no component scripts.' );

$GLOBALS['mrn_component_test_filters']['mrn_base_stack_critical_css_image'] = array(
	'url'  => 'https://cdn.example.test/critical-wordmark.svg',
	'type' => 'image/svg+xml',
);
$preloads = mrn_base_stack_add_critical_css_image_preload( array() );
$preloads = mrn_base_stack_add_critical_css_image_preload( $preloads );
mrn_component_test_assert( 1 === count( $preloads ), 'Critical CSS-backed image is preloaded once.' );
mrn_component_test_assert( 'https://cdn.example.test/critical-wordmark.svg' === $preloads[0]['href'], 'Critical preload uses the declared URL.' );
mrn_component_test_assert( 'image/svg+xml' === $preloads[0]['type'], 'Critical preload uses the declared MIME type.' );
mrn_component_test_assert( 'image' === $preloads[0]['as'] && 'high' === $preloads[0]['fetchpriority'], 'Critical preload uses image/high priority attributes.' );

echo "PASS: Front-end component assets and critical preload.\n";
