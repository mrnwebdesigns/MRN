<?php
/**
 * Native CTA frame contract, persistence and single-owner spacing regression.
 *
 * @package mrn-base-stack
 */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || home_url() !== getenv( 'MRN_STACK_TEST_HOME' ) || ( '127.0.0.1' !== wp_parse_url( home_url(), PHP_URL_HOST ) && ! str_ends_with( (string) wp_parse_url( home_url(), PHP_URL_HOST ), '.localhost' ) ) || ! current_user_can( 'manage_options' ) ) {
	throw new RuntimeException( 'An explicitly selected local administrator runtime is required.' );
}
$checks = array();
$expect = static function ( $condition, $message ) use ( &$checks ) {
	if ( ! $condition ) { throw new RuntimeException( esc_html( $message ) ); }
	$checks[] = $message;
};
$id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'Native CTA frame qualification' ), true );
$expect( ! is_wp_error( $id ) && $id > 0, 'Owned local draft created' );
try {
	$layouts = array_column( acf_get_field( 'field_mrn_page_content_rows' )['layouts'], null, 'name' );
	$fields = array_column( $layouts['cta']['sub_fields'], null, 'name' );
	$names = array_column( mrn_base_stack_get_cta_frame_fields( 'fixture' ), 'name' );
	foreach ( $names as $name ) { $expect( isset( $fields[ $name ] ), 'Native editable CTA field: ' . $name ); }
	$expect( ! in_array( 'cta_frame', array_column( $layouts['basic_block']['sub_fields'], 'name' ), true ), 'CTA frame does not leak into Basic rows' );
	$split_fields = array_column( $layouts['two_column_split']['sub_fields'], null, 'name' );
	$nested = array_column( $split_fields['left_column_rows']['layouts'], null, 'name' );
	$nested_fields = array_column( $nested['cta']['sub_fields'], null, 'name' );
	$expect( isset( $nested_fields['cta_frame'] ) && $nested_fields['cta_frame']['key'] !== $fields['cta_frame']['key'], 'Nested CTA frame controls have isolated ACF keys' );
	$again = mrn_base_stack_ensure_presentation_fields( $layouts['cta']['sub_fields'], 'cta', 'fixture' );
	$expect( 1 === count( array_filter( $again, static function ( $field ) { return 'cta_frame' === ( $field['name'] ?? '' ); } ) ), 'Repeated layout normalization does not duplicate frame controls' );
	$empty = array( 'classes' => array(), 'attributes' => array() );
	$expect( $empty === mrn_base_stack_get_cta_frame_contract( array() ) && $empty === mrn_base_stack_get_cta_frame_contract( array( 'cta_frame' => array( 'stacked' ), 'cta_inner_gutter' => '30px' ) ), 'Blank and malformed frame selections retain existing output' );
	$expect( $empty === mrn_base_stack_get_cta_frame_contract( array( 'cta_inner_gutter' => '30px' ) ), 'Unselected optional values cannot change an existing CTA' );
	$bad = array( 'cta_frame' => 'stacked', 'cta_content_alignment' => 'center;position:fixed', 'cta_inner_gutter_mobile_breakpoint' => '1199;bad', 'bg_color' => 'unregistered' );
	foreach ( array( '-10px', '1px;position:fixed', 'url(https://invalid.test)', array( '2rem' ), 'clamp(1px, calc(2px + 3px), 4px)' ) as $value ) {
		$bad['cta_content_gap'] = $value;
		$expect( array( 'classes' => array( 'has-cta-frame' ), 'attributes' => array() ) === mrn_base_stack_get_cta_frame_contract( $bad ), 'Unsafe presentation data cannot inject CSS: ' . wp_json_encode( $value ) );
	}
	$row = array(
		'acf_fc_layout' => 'cta', 'heading' => 'Native CTA heading', 'heading_tag' => 'h2', 'content' => '<p>First paragraph.</p><p>Last paragraph.</p>',
		'cta_frame' => 'stacked', 'cta_inner_gutter' => '30px', 'cta_inner_gutter_mobile' => '15px', 'cta_inner_gutter_mobile_breakpoint' => '1199',
		'cta_content_width' => '1280px', 'cta_content_gap' => '0px', 'cta_text_space_before' => '32px', 'cta_paragraph_space_after' => '30px',
		'cta_actions_space_before' => '0px', 'cta_action_min_width' => '200px', 'cta_content_alignment' => 'center',
		'links' => array( array( 'link' => array( 'title' => 'Exact action', 'url' => home_url( '/?first=1&second=2' ), 'target' => '_blank' ), 'is_button' => 1 ) ),
	);
	foreach ( array( 'margin', 'padding' ) as $kind ) {
		foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) { $row[ 'row_spacing_' . $kind . '_' . $side . '_preset' ] = '0px'; }
	}
	update_field( 'field_mrn_page_content_rows', array( $row ), $id );
	$readback = get_field( 'page_content_rows', $id )[0];
	foreach ( array_intersect( $names, array_keys( $row ) ) as $name ) { $expect( $row[ $name ] === $readback[ $name ], 'ACF persistence preserves ' . $name ); }
	$render = static function ( $value ) use ( $id ) { ob_start(); mrn_base_stack_render_builder_row( $value, $id, 0 ); return ob_get_clean(); };
	$html = $render( $readback );
	$expect( str_contains( $html, 'has-cta-frame' ) && str_contains( $html, 'has-cta-gutter-at-1199' ) && str_contains( $html, '--mrn-cta-frame-content-gap:0px' ), 'Saved native frame and responsive values reach the wrapper' );
	$expect( 1 === substr_count( $html, 'data-mrn-row-spacing=' ), 'Spacing is emitted once on the outer native row' );
	$expect( str_contains( $html, 'Last paragraph.' ) && str_contains( $html, 'second=2' ) && str_contains( $html, 'target="_blank"' ) && 1 === substr_count( $html, '<h2 ' ), 'Rich copy, complete action and semantic heading survive rendering' );
	$default = $row; foreach ( $names as $name ) { unset( $default[ $name ] ); }
	$old = $render( $default );
	$expect( ! str_contains( $old, 'has-cta-frame' ) && ! str_contains( $old, '--mrn-cta-frame-' ) && 1 === substr_count( $old, 'data-mrn-row-spacing=' ), 'Unconfigured CTA retains its existing wrapper and reusable contracts' );
	$alias = $row; $alias['acf_fc_layout'] = 'cta_block';
	$expect( str_contains( $render( $alias ), 'has-cta-frame' ) && 1 === substr_count( $render( $alias ), 'data-mrn-row-spacing=' ), 'Compatibility CTA layout uses the same optional single-owner contract' );
} finally { wp_delete_post( $id, true ); }
$expect( null === get_post( $id ), 'Owned fixture removed' );
WP_CLI::line( wp_json_encode( array( 'checks' => $checks, 'count' => count( $checks ), 'result' => 'passed', 'source_version' => _S_VERSION ), JSON_PRETTY_PRINT ) );
