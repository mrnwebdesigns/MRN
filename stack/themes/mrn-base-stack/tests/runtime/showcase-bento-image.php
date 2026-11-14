<?php
/** Bento Showcase tiles use an uncropped image size; other Showcase presentations keep their sizes. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || home_url() !== getenv( 'MRN_STACK_TEST_HOME' ) || ! str_ends_with( (string) wp_parse_url( home_url(), PHP_URL_HOST ), '.localhost' ) || ! current_user_can( 'manage_options' ) ) { throw new RuntimeException( 'An explicitly selected local administrator runtime is required.' ); }
$checks = array(); $expect = static function ( $ok, $label ) use ( &$checks ) { if ( ! $ok ) { throw new RuntimeException( esc_html( $label ) ); } $checks[] = $label; };

// A local image large enough that WordPress generated the hard-cropped 900x675 gallery size.
$image = 0;
foreach ( get_posts( array( 'post_type' => 'attachment', 'post_mime_type' => 'image', 'post_status' => 'inherit', 'numberposts' => 200, 'fields' => 'ids' ) ) as $candidate ) {
	$meta = wp_get_attachment_metadata( $candidate );
	if ( isset( $meta['sizes']['mrn-gallery'], $meta['sizes']['mrn-content-media'] ) && abs( $meta['width'] / $meta['height'] - 4 / 3 ) > 0.1 ) { $image = (int) $candidate; break; }
}
$expect( $image > 0, 'A non-4:3 local image with gallery and content-media sizes exists' );
$meta    = wp_get_attachment_metadata( $image );
$cropped = $meta['sizes']['mrn-gallery']['file'];
$natural = $meta['sizes']['mrn-content-media']['file'];

$render = static function ( $presentation ) use ( $image ) {
	$row = array( 'acf_fc_layout' => 'showcase', 'showcase_presentation' => $presentation, 'showcase_items' => array( array( 'image' => $image, 'column_span' => '1', 'row_span' => '2', 'image_alt_override' => '' ) ) );
	ob_start(); mrn_base_stack_render_builder_row( $row, 0, 0 ); return (string) ob_get_clean();
};
$bento = $render( 'bento' );
$expect( str_contains( $bento, 'mrn-bento-r2' ), 'Bento tile renders with its row span' );
$expect( str_contains( $bento, $natural ) && ! preg_match( '/\ssrc="[^"]*' . preg_quote( $cropped, '/' ) . '"/', $bento ), 'Bento tile source keeps the image aspect (no 4:3 hard crop)' );
$standard = $render( 'standard' );
$expect( str_contains( $standard, $cropped ), 'Standard Showcase keeps the 4:3 gallery size' );

WP_CLI::line( wp_json_encode( array( 'result' => 'passed', 'count' => count( $checks ), 'checks' => $checks, 'candidate_only' => true ), JSON_PRETTY_PRINT ) );
