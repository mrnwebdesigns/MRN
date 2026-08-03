<?php
// phpcs:ignoreFile -- Standalone WordPress stub harness for frontend asset discovery.
/**
 * Regression coverage for raw builder asset discovery.
 *
 * @package mrn-base-stack
 */

$GLOBALS['mrn_asset_test_meta'] = array(
	'page_hero_rows'                             => array( array( 'basic', 'two_column_split' ) ),
	'page_content_rows'                          => array( array( 'logos', 'two_column_split' ) ),
	'page_content_rows_0_left_column_rows'       => array( array( 'body_text' ) ),
	'page_content_rows_0_background_image'       => array( '42' ),
	'page_content_rows_0_background_video'       => array( '' ),
	'_page_content_rows_0_background_image'      => array( 'field_reference' ),
	'page_content_rows_1_background_video_upload'=> array( '0' ),
	'page_content_rows_0_link_icon_source'       => array( 'fontawesome' ),
	'page_content_rows_0_link_icon_fa_class'     => array( 'fa-solid fa-arrow-right' ),
	'page_content_rows_1_link_icon_source'       => array( 'dashicons' ),
	'page_content_rows_1_link_icon_dashicon'     => array( 'dashicons-arrow-right-alt2' ),
);

function get_post_meta( $post_id, $key = '', $single = false ) {
	return $GLOBALS['mrn_asset_test_meta'];
}

function sanitize_key( $value ) {
	return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $value ) );
}

function absint( $value ) {
	return abs( (int) $value );
}

function maybe_unserialize( $value ) {
	return $value;
}

function apply_filters( $hook, $value, ...$args ) {
	return $value;
}

require dirname( __DIR__, 2 ) . '/inc/frontend-assets.php';

$keys = mrn_base_stack_get_layout_style_keys_for_post( 16 );

foreach ( array( 'hero', 'two_column_split', 'logos', 'body_text', 'row_background_media' ) as $required_key ) {
	if ( empty( $keys[ $required_key ] ) ) {
		throw new RuntimeException( "Missing frontend asset key: {$required_key}" );
	}
}
if ( isset( $keys['basic'] ) ) {
	throw new RuntimeException( 'Top-level basic Hero layout did not map to the hero stylesheet key.' );
}

$needs_fontawesome = false;
$needs_dashicons   = false;
mrn_base_stack_collect_builder_link_icon_asset_needs_from_post_meta( 16, $needs_fontawesome, $needs_dashicons );
if ( ! $needs_fontawesome || ! $needs_dashicons ) {
	throw new RuntimeException( 'Builder link-icon asset requirements were not detected from raw meta.' );
}

echo "Frontend asset meta-index tests passed.\n";
