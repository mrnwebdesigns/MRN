<?php
/** Qualify optional frame fields, input bounds and single-copy disclosure fallback. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || home_url() !== getenv( 'MRN_STACK_TEST_HOME' ) || ( '127.0.0.1' !== wp_parse_url( home_url(), PHP_URL_HOST ) && ! str_ends_with( (string) wp_parse_url( home_url(), PHP_URL_HOST ), '.localhost' ) ) || ! current_user_can( 'manage_options' ) ) { throw new RuntimeException( 'Requires the explicitly selected local administrator runtime.' ); }
$checks = array();
$expect = static function ( $ok, $message ) use ( &$checks ) { if ( ! $ok ) { throw new RuntimeException( esc_html( $message ) ); } $checks[] = $message; };
$layouts = array_column( acf_get_field( 'field_mrn_page_content_rows' )['layouts'], null, 'name' );
foreach ( array( 'body_text', 'stats', 'image_content' ) as $layout ) {
	$fields = array_column( $layouts[ $layout ]['sub_fields'], null, 'name' );
	foreach ( mrn_base_stack_get_content_frame_fields( $layout, 'fixture' ) as $field ) { $expect( isset( $fields[ $field['name'] ] ), 'Registered ' . $layout . ' field: ' . $field['name'] ); }
	$expect( array( 'classes' => array(), 'attributes' => array() ) === mrn_base_stack_get_content_frame_contract( array(), $layout ), 'Unset ' . $layout . ' frame retains existing output' );
}
$fields = array_column( $layouts['tabbed_layout']['sub_fields'], null, 'name' );
foreach ( mrn_base_stack_get_tabs_frame_fields( 'fixture' ) as $field ) { $expect( isset( $fields[ $field['name'] ] ), 'Registered Tabs field: ' . $field['name'] ); }
foreach ( array( 'url(bad)', 'var(--unknown-size)', '-4px', '1px;color:red', array( '12px' ) ) as $bad ) {
	$contract = mrn_base_stack_get_content_frame_contract( array( 'body_frame' => 'contained', 'body_heading_size' => $bad, 'body_heading_color' => $bad, 'body_label_weight' => $bad ), 'body_text' );
	$expect( empty( $contract['attributes'] ), 'Unsafe typography rejected: ' . wp_json_encode( $bad ) );
}
$expect( '' === mrn_base_stack_get_content_frame_dimension( 'var(--site-text-h3-size)', 'text_line_height' ) && '' === mrn_base_stack_get_content_frame_dimension( 'var(--site-text-h3-line)', 'heading_size' ), 'Font and line-height tokens cannot cross property kinds' );
$expect( 'var(--site-text-body-3-size)' === mrn_base_stack_get_content_frame_dimension( 'var(--site-text-body-3-size)', 'text_size' ), 'Known Site Text token accepted' );
$panels = array();
for ( $i = 1; $i <= 4; ++$i ) { $panels[] = array( 'tab_label' => 'Panel ' . $i, 'panel_rows' => array( array( 'acf_fc_layout' => 'body_text', 'body_text' => '<p>Unique copy ' . $i . '</p>' ) ) ); }
$id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'Owned responsive Tabs frame proof' ), true );
$expect( ! is_wp_error( $id ) && $id > 0, 'Owned draft created' );
try {
	$row = array( 'acf_fc_layout' => 'tabbed_layout', 'tabs' => $panels, 'tab_frame' => 'contained', 'tab_mobile_behavior' => 'accordion', 'tab_mobile_breakpoint' => '767', 'tab_content_width' => '1200px', 'tab_inner_gutter' => '30px', 'tab_inner_gutter_mobile' => '15px', 'tab_panel_space_before' => 'clamp(28px, 4vw, 54px)', 'tab_switch_effect' => 'slide' );
	update_field( 'field_mrn_page_content_rows', array( $row ), $id ); acf_flush_value_cache( $id, 'page_content_rows' ); $saved = get_field( 'page_content_rows', $id )[0];
	foreach ( array( 'tab_frame', 'tab_mobile_behavior', 'tab_mobile_breakpoint', 'tab_panel_space_before' ) as $name ) { $expect( $row[ $name ] === $saved[ $name ], 'Native ACF retains ' . $name ); }
	ob_start(); mrn_base_stack_render_builder_row( $saved, $id, 0 ); $html = ob_get_clean();
	$expect( 4 === substr_count( $html, '<details ' ) && 4 === substr_count( $html, '<summary ' ) && 1 === substr_count( $html, 'data-mrn-tab-drawer open' ), 'Without JavaScript, four disclosures and exactly one initially open panel' );
	$expect( ! str_contains( $html, 'splide__slide' ) && str_contains( $html, 'mrn-tabbed-layout--transition-instant' ) && 'slide' === $saved['tab_switch_effect'], 'Responsive mode uses instant switching without overwriting saved animation choice' );
	$expect( 4 === substr_count( $html, 'role="region"' ) && str_contains( $html, 'data-mrn-tab-accordion="767"' ), 'Fallback regions and bounded responsive breakpoint render' );
	for ( $i = 1; $i <= 4; ++$i ) { $expect( 1 === substr_count( $html, 'Unique copy ' . $i ), 'Panel ' . $i . ' content renders once' ); }
	preg_match_all( '/\bid="([^"]+)"/', $html, $ids );
	$expect( count( $ids[1] ) === count( array_unique( $ids[1] ) ), 'Desktop and disclosure controls have unique IDs' );
	$saved['tab_mobile_behavior'] = ''; $saved['tab_switch_effect'] = 'instant'; $saved['tab_frame'] = '';
	ob_start(); mrn_base_stack_render_builder_row( $saved, $id, 0 ); $plain = ob_get_clean();
	$expect( ! str_contains( $plain, '<details ' ) && ! str_contains( $plain, 'has-tab-frame' ) && 4 === substr_count( $plain, 'role="tabpanel"' ), 'Unselected fields preserve ordinary Tabs markup' );
} finally { wp_delete_post( $id, true ); }
$expect( null === get_post( $id ), 'Owned draft removed' );
WP_CLI::line( wp_json_encode( array( 'result' => 'passed', 'count' => count( $checks ), 'checks' => $checks, 'candidate_only' => true ), JSON_PRETTY_PRINT ) );
