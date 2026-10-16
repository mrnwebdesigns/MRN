<?php
/** Native responsive Logos qualification; owned local fixture only. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || home_url() !== getenv( 'MRN_STACK_TEST_HOME' ) || ! str_ends_with( (string) wp_parse_url( home_url(), PHP_URL_HOST ), '.localhost' ) || ! current_user_can( 'manage_options' ) ) {
	throw new RuntimeException( 'An explicitly selected local administrator runtime is required.' );
}
$checks = array();
$expect = static function ( $condition, $message ) use ( &$checks ) {
	if ( ! $condition ) { throw new RuntimeException( esc_html( $message ) ); }
	$checks[] = $message;
};
$id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'Native responsive Logos qualification' ), true );
$expect( ! is_wp_error( $id ) && $id > 0, 'Owned local draft created' );
try {
	$root = acf_get_field( 'field_mrn_page_content_rows' );
	$layouts = array_column( $root['layouts'], null, 'name' );
	$fields = array_column( $layouts['logos']['sub_fields'], null, 'name' );
	$expect( isset( $fields['per_page']['choices'][7] ), 'Root Logos exposes seven views' );
	$expect( 4 === $fields['slider_breakpoints']['max'], 'Responsive view count is bounded' );
	$nested = mrn_base_stack_get_two_column_nested_layouts();
	$nested_fields = array_column( mrn_base_stack_ensure_presentation_fields( $nested['layout_mrn_nested_logos']['sub_fields'], 'logos', 'field_mrn_nested_logos' ), null, 'name' );
	$expect( isset( $nested_fields['per_page']['choices'][7] ), 'Nested Logos exposes seven views' );
	$expect( isset( $nested_fields['slider_breakpoints'] ) && $fields['slider_breakpoints']['key'] !== $nested_fields['slider_breakpoints']['key'], 'Root and nested controls have unique native field keys' );
	$images = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'post_mime_type' => 'image', 'numberposts' => 6, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC' ) );
	$selected = getenv( 'MRN_LOGOS_TEST_IMAGE_IDS' );
	if ( $selected ) {
		$expect( (bool) preg_match( '/^\d+(?:,\d+){5}$/D', $selected ), 'Explicit fixture media selection contains six attachment IDs' );
		$images = array_map( 'intval', explode( ',', $selected ) );
		foreach ( $images as $image ) {
			$expect( 'attachment' === get_post_type( $image ) && str_starts_with( (string) get_post_mime_type( $image ), 'image/' ) && is_file( (string) get_attached_file( $image ) ), 'Selected fixture image exists locally: ' . $image );
		}
	}
	$expect( 6 === count( $images ), 'Six existing images selected without importing media' );
	$row = array(
		'acf_fc_layout' => 'logos', 'heading' => 'Native responsive logo qualification', 'heading_tag' => 'h2',
		'logo_items' => array_map( static function ( $image ) { return array( 'image' => $image ); }, $images ),
		'display_mode' => 'slider', 'per_page' => '7', 'autoplay' => 1, 'autoplay_max_width' => 1199,
		'continuous_scroll' => 1, 'slider_gap' => '30px', 'delay_time' => 4, 'time_on_slide' => 4000,
		'slider_breakpoints' => array( array( 'max_width' => 1199, 'per_page' => '5' ), array( 'max_width' => 767, 'per_page' => '3' ), array( 'max_width' => 479, 'per_page' => '2' ) ),
	);
	foreach ( array( 'margin', 'padding' ) as $dimension ) {
		foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
			$row[ 'row_spacing_' . $dimension . '_' . $side . '_preset' ] = '0px';
		}
	}
	update_field( 'field_mrn_page_content_rows', array( $row ), $id );
	$readback = get_field( 'page_content_rows', $id );
	$expect( '7' === (string) $readback[0]['per_page'] && 3 === count( $readback[0]['slider_breakpoints'] ), 'Native ACF write/readback retains seven and all responsive views' );
	$expect( 1199 === (int) $readback[0]['autoplay_max_width'] && '30px' === $readback[0]['slider_gap'], 'Native fields retain the viewport condition and gap' );
	ob_start(); mrn_base_stack_render_builder_row( $readback[0], $id, 0 ); $html = ob_get_clean();
	$expect( str_contains( $html, 'data-per-page="7"' ) && str_contains( $html, 'data-autoplay-max-width="1199"' ), 'Renderer emits the authored desktop and autoplay conditions' );
	$expect( str_contains( $html, 'data-slider-gap="30px"' ) && str_contains( $html, 'data-continuous-scroll="true"' ), 'Renderer emits native gap and continuous mode' );
	$expect( str_contains( $html, 'data-mrn-slider-toggle hidden' ), 'Server provides a named pause control, hidden until JavaScript qualifies movement' );
	$normalized = mrn_base_stack_get_logos_responsive_config( $readback[0] );
	$expect( array( 1199, 767, 479 ) === array_keys( $normalized['breakpoints'] ), 'All authored breakpoint widths retain their values' );
	$invalid = mrn_base_stack_get_logos_responsive_config( array( 'slider_gap' => '10px; color:red', 'autoplay_max_width' => 99999, 'slider_breakpoints' => array( array( 'max_width' => 1, 'per_page' => 99 ) ) ) );
	$expect( '' === $invalid['gap'] && 0 === $invalid['autoplay_max_width'] && array() === $invalid['breakpoints'], 'Invalid optional presentation values cannot escape into output' );
	$legacy = $row;
	foreach ( array( 'slider_breakpoints', 'autoplay_max_width', 'continuous_scroll', 'slider_gap' ) as $key ) { unset( $legacy[ $key ] ); }
	$legacy['per_page'] = '6';
	ob_start(); mrn_base_stack_render_builder_row( $legacy, $id, 1 ); $legacy_html = ob_get_clean();
	$expect( ! str_contains( $legacy_html, 'data-logos-responsive' ) && ! str_contains( $legacy_html, 'data-mrn-slider-toggle' ), 'Rows without new settings retain the existing renderer contract' );
	$proof = getenv( 'MRN_LOGOS_PROOF_PATH' );
	if ( $proof ) {
		$expect( str_ends_with( $proof, '.html' ) && is_dir( dirname( $proof ) ), 'Explicit proof destination is an existing directory' );
		$document = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Native responsive Logos qualification</title><link rel="stylesheet" href="style.css"><link rel="stylesheet" href="splide.min.css"></head><body><main><h1>Responsive logo preview</h1>' . $html . '</main><script src="splide.min.js"></script><script src="front-end-slider.js"></script></body></html>';
		$expect( false !== file_put_contents( $proof, $document ), 'Native proof document saved outside authored content' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Explicit local CLI proof path.
	}
} finally {
	wp_delete_post( $id, true );
}
$expect( null === get_post( $id ), 'Owned fixture removed' );
WP_CLI::line( wp_json_encode( array( 'checks' => $checks, 'count' => count( $checks ), 'result' => 'passed', 'source_version' => _S_VERSION ), JSON_PRETTY_PRINT ) );
