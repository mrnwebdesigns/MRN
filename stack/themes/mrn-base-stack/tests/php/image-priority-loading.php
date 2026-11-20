<?php
// phpcs:ignoreFile -- Standalone WordPress stub harness for image-loading contracts.
/**
 * Regression coverage for hero priority and ordinary image lazy loading.
 *
 * @package mrn-base-stack
 */

$GLOBALS['mrn_image_test_attachment_attrs'] = array();
$GLOBALS['mrn_image_test_thumbnail_attrs']  = array();

function absint( $value ) {
	return abs( (int) $value );
}

function esc_url_raw( $value ) {
	return (string) $value;
}

function attachment_url_to_postid( $url ) {
	return 42;
}

function get_post_meta( $post_id, $key = '', $single = false ) {
	return '';
}

function wp_parse_args( $args, $defaults = array() ) {
	return array_merge( $defaults, $args );
}

function wp_get_attachment_image( $attachment_id, $size, $icon = false, $attr = array() ) {
	$GLOBALS['mrn_image_test_attachment_attrs'] = $attr;
	return '<img src="https://cdn.example.test/image.jpg" width="800" height="600">';
}

function get_the_post_thumbnail( $post = null, $size = 'post-thumbnail', $attr = array() ) {
	$GLOBALS['mrn_image_test_thumbnail_attrs'] = $attr;
	return '<img src="https://cdn.example.test/thumbnail.jpg" width="600" height="400">';
}

function mrn_image_test_assert( $condition, $message ) {
	if ( $condition ) {
		return;
	}

	fwrite( STDERR, "FAIL: {$message}\n" );
	exit( 1 );
}

require dirname( __DIR__, 2 ) . '/inc/image-helpers.php';
require dirname( __DIR__, 2 ) . '/inc/builder/hero-frame.php';

$hero_attributes = mrn_base_stack_get_hero_image_attributes();
mrn_image_test_assert( 'eager' === $hero_attributes['loading'], 'Hero media is explicitly eager.' );
mrn_image_test_assert( 'high' === $hero_attributes['fetchpriority'], 'Hero media has high fetch priority.' );

mrn_base_stack_get_attachment_image( 42, 'large' );
mrn_image_test_assert( 'lazy' === $GLOBALS['mrn_image_test_attachment_attrs']['loading'], 'Ordinary Stack attachment images are explicitly lazy.' );
mrn_image_test_assert( 'async' === $GLOBALS['mrn_image_test_attachment_attrs']['decoding'], 'Ordinary Stack attachment images decode asynchronously.' );

mrn_base_stack_get_lazy_post_thumbnail( 7, 'medium_large' );
mrn_image_test_assert( 'lazy' === $GLOBALS['mrn_image_test_thumbnail_attrs']['loading'], 'Ordinary Stack post thumbnails are explicitly lazy.' );
mrn_image_test_assert( 'async' === $GLOBALS['mrn_image_test_thumbnail_attrs']['decoding'], 'Ordinary Stack post thumbnails decode asynchronously.' );

$hero_source = file_get_contents( dirname( __DIR__, 2 ) . '/template-parts/builder/hero.php' );
mrn_image_test_assert( false !== strpos( $hero_source, "'mrn-hero'" ), 'Hero uses the registered responsive image size.' );
mrn_image_test_assert( false !== strpos( $hero_source, '? mrn_base_stack_get_hero_image_attributes' ) && false !== strpos( $hero_source, 'mrn_base_stack_get_hero_media_image_attributes( $row )' ), 'Background and content media both use native priority helpers.' );
$bounded = mrn_base_stack_get_hero_media_image_attributes( array( 'hero_media_width' => '422px', 'hero_media_max_share' => '40', 'hero_media_stack_breakpoint' => '900' ) );
mrn_base_stack_get_attachment_image( 42, 'full', $bounded );
mrn_image_test_assert( 'eager' === $GLOBALS['mrn_image_test_attachment_attrs']['loading'] && 'high' === $GLOBALS['mrn_image_test_attachment_attrs']['fetchpriority'] && '(max-width: 900px) 100vw, min(422px, 40vw)' === $GLOBALS['mrn_image_test_attachment_attrs']['sizes'], 'Bounded responsive content media reaches the native attachment renderer with Hero priority.' );
mrn_image_test_assert( $hero_attributes === mrn_base_stack_get_hero_media_image_attributes( array() ) && $hero_attributes === mrn_base_stack_get_hero_media_image_attributes( array( 'hero_media_width' => '1px;bad', 'hero_media_max_share' => '40' ) ), 'Default and malformed bounded image hints retain the original priority contract.' );

echo "PASS: Image priority and lazy-loading contracts.\n";
