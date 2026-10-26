<?php
/** Native quotation semantics, nested persistence and scoped typography safety. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || home_url() !== getenv( 'MRN_STACK_TEST_HOME' ) || ( '127.0.0.1' !== wp_parse_url( home_url(), PHP_URL_HOST ) && ! str_ends_with( (string) wp_parse_url( home_url(), PHP_URL_HOST ), '.localhost' ) ) || ! current_user_can( 'manage_options' ) ) { throw new RuntimeException( 'Requires the explicitly selected local administrator runtime.' ); }
$checks = array(); $expect = static function ( $ok, $message ) use ( &$checks ) { if ( ! $ok ) { throw new RuntimeException( esc_html( $message ) ); } $checks[] = $message; };
$layouts = array_column( acf_get_field( 'field_mrn_page_content_rows' )['layouts'], null, 'name' );
$fields = array_column( $layouts['body_text']['sub_fields'], null, 'name' );
$nested = array_column( array_column( $layouts['two_column_split']['sub_fields'], null, 'name' )['right_column_rows']['layouts'], null, 'name' );
$nested_fields = array_column( $nested['body_text']['sub_fields'], null, 'name' );
$props = array( 'body_text_semantics' => 'quote', 'body_quote_mark' => 'opening', 'body_quote_mark_width' => '33px', 'body_quote_mark_gap' => '16px', 'body_text_weight' => '500', 'body_text_size_mobile' => 'var(--site-text-body-3-size)', 'body_text_line_height_mobile' => '24px', 'body_text_mobile_breakpoint' => '600' );
foreach ( $props as $name => $value ) { $expect( isset( $fields[ $name ], $nested_fields[ $name ] ) && $fields[ $name ]['key'] !== $nested_fields[ $name ]['key'], 'Native root/nested quotation field keys are isolated: ' . $name ); }
$zero = array(); foreach ( array( 'margin', 'padding' ) as $kind ) { foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) { $zero[ 'row_spacing_' . $kind . '_' . $side . '_preset' ] = '0px'; } }
$row = array_merge( $zero, $props, array( 'acf_fc_layout' => 'body_text', 'body_frame' => 'contained', 'body_text' => '<p>Preserve the <em>quotation</em> and <a href="/our-work/">link</a>.</p>', 'body_paragraph_space_after' => '20px' ) );
$id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'Owned native quotation proof' ), true );
$expect( ! is_wp_error( $id ) && $id > 0, 'Owned native quotation draft created' );
try {
	$split = array_merge( $zero, array( 'acf_fc_layout' => 'two_column_split', 'column_rows_mode' => 'stack', 'left_column_rows' => array(), 'right_column_rows' => array( $row ) ) );
	update_field( 'field_mrn_page_content_rows', wp_slash( array( $row, $split ) ), $id ); acf_flush_value_cache( $id, 'page_content_rows' ); $saved = get_field( 'page_content_rows', $id );
	foreach ( $props as $name => $value ) { $expect( $value === $saved[0][ $name ] && $value === $saved[1]['right_column_rows'][0][ $name ], 'ACF retains root and nested quotation value: ' . $name ); }
	$expect( $row['body_text'] === get_post_meta( $id, 'page_content_rows_0_body_text', true ), 'Stored rich quotation and link remain exact' );
	ob_start(); mrn_base_stack_render_builder_row( $saved[0], $id, 0 ); $html = ob_get_clean();
	$expect( 1 === substr_count( $html, '<blockquote ' ) && str_contains( $html, '<em>quotation</em>' ) && str_contains( $html, 'href="/our-work/"' ), 'One semantic quotation retains emphasis and link markup' );
	$expect( str_contains( $html, 'class="mrn-body-quote__mark" aria-hidden="true" focusable="false"' ) && ! str_contains( $html, '<img ' ), 'Decorative vector mark adds no media request or focus stop' );
	foreach ( array( '--mrn-body-frame-quote-mark-width:33px', '--mrn-body-frame-quote-mark-gap:16px', '--mrn-body-frame-text-weight:500', 'body-text-mobile-600' ) as $fragment ) { $expect( str_contains( $html, $fragment ), 'Native quotation output contains ' . $fragment ); }
	$plain = $row; $plain['body_text_semantics'] = '';
	ob_start(); mrn_base_stack_render_builder_row( $plain, $id, 1 ); $html = ob_get_clean();
	$expect( ! str_contains( $html, '<blockquote' ) && ! str_contains( $html, 'mrn-body-quote__mark' ) && ! str_contains( $html, '--mrn-body-frame-quote-mark-width' ), 'Unselected quotation preserves standard text and ignores dormant mark controls' );
	$plain['body_frame'] = ''; $plain['body_text_semantics'] = 'quote';
	ob_start(); mrn_base_stack_render_builder_row( $plain, $id, 2 ); $html = ob_get_clean();
	$expect( ! str_contains( $html, '<blockquote' ) && ! str_contains( $html, 'has-body-frame' ), 'Quote semantics and typography require the selected native frame' );
	$empty = $row; $empty['body_text'] = '';
	ob_start(); mrn_base_stack_render_builder_row( $empty, $id, 3 ); $html = ob_get_clean();
	$expect( '' === $html, 'Empty quote does not create a decorative-only section' );
	foreach ( array( '-1px', '1px;color:red', 'var(--unknown)', array( '33px' ) ) as $bad ) {
		$unsafe = array_merge( $row, array( 'body_quote_mark_width' => $bad, 'body_quote_mark_gap' => $bad, 'body_text_size_mobile' => $bad, 'body_quote_mark_color' => $bad, 'body_text_weight' => $bad ) );
		$contract = mrn_base_stack_get_content_frame_contract( $unsafe, 'body_text' ); $style = $contract['attributes']['style'] ?? '';
		$expect( ! str_contains( $style, 'quote-mark-width:' ) && ! str_contains( $style, 'quote-mark-gap:' ) && ! str_contains( $style, 'quote-mark-color:' ) && ! str_contains( $style, 'text-size-mobile:' ) && ! str_contains( $style, 'text-weight:' ), 'Unsafe quotation dimensions, weight and palette cannot emit CSS' );
	}
	$expect( '' === mrn_base_stack_get_content_frame_dimension( 'var(--site-text-body-3-line)', 'text_size_mobile' ) && '' === mrn_base_stack_get_content_frame_dimension( 'var(--site-text-body-3-size)', 'text_line_height_mobile' ), 'Responsive text size and line tokens cannot be interchanged' );
} finally { wp_delete_post( $id, true ); }
$expect( null === get_post( $id ), 'Owned native quotation draft removed' );
WP_CLI::line( wp_json_encode( array( 'result' => 'passed', 'count' => count( $checks ), 'checks' => $checks, 'candidate_only' => true ), JSON_PRETTY_PRINT ) );
