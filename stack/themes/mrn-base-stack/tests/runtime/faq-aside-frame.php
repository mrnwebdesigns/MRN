<?php
/** Native FAQ/aside persistence, scoping and unsafe-value regression. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || home_url() !== getenv( 'MRN_STACK_TEST_HOME' ) || ! str_ends_with( (string) wp_parse_url( home_url(), PHP_URL_HOST ), '.localhost' ) || ! current_user_can( 'manage_options' ) ) { throw new RuntimeException( 'An explicitly selected local administrator runtime is required.' ); }
$checks = array();
$expect = static function ( $condition, $message ) use ( &$checks ) { if ( ! $condition ) { throw new RuntimeException( esc_html( $message ) ); } $checks[] = $message; };
$id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'Owned native FAQ aside frame proof' ), true );
$expect( ! is_wp_error( $id ) && $id > 0, 'Owned draft created' );
try {
	$layouts = array_column( acf_get_field( 'field_mrn_page_content_rows' )['layouts'], null, 'name' );
	$faq_fields = array_column( $layouts['faq']['sub_fields'], null, 'name' );
	foreach ( mrn_base_stack_get_faq_frame_fields( 'fixture' ) as $field ) { $expect( isset( $faq_fields[ $field['name'] ] ), 'Registered FAQ field: ' . $field['name'] ); }
	$split_fields = array_column( $layouts['two_column_split']['sub_fields'], null, 'name' );
	foreach ( mrn_base_stack_get_split_aside_fields( 'fixture' ) as $field ) { $expect( isset( $split_fields[ $field['name'] ] ), 'Registered Split field: ' . $field['name'] ); }
	$cta_fields = array_column( $layouts['cta']['sub_fields'], null, 'name' );
	$expect( ! array_intersect( array( 'faq_question_size', 'faq_question_size_mobile', 'faq_question_line_height', 'faq_answer_line_height', 'faq_item_padding', 'faq_item_padding_mobile', 'faq_answer_space_before', 'faq_icon_gap', 'faq_icon_width', 'faq_item_gap', 'faq_inner_gutter', 'faq_question_weight', 'faq_question_color' ), array_keys( $faq_fields ) ), 'FAQ geometry, typography and colors come from theme CSS, not row fields' );
	$expect( ! array_intersect( array( 'split_content_width', 'split_inner_gutter', 'split_inner_gutter_mobile', 'split_aside_width', 'split_column_gap', 'split_column_gap_mobile', 'split_aside_sticky_top', 'split_heading_space_after', 'split_heading_size' ), array_keys( $split_fields ) ), 'Split aside geometry and heading typography come from theme CSS, not row fields' );
	$expect( ! array_intersect( array( 'cta_heading_size', 'cta_heading_line_height', 'cta_heading_weight', 'cta_heading_space_after', 'cta_text_size', 'cta_text_line_height', 'cta_text_color', 'cta_content_gap', 'cta_inner_gutter' ), array_keys( $cta_fields ) ), 'CTA typography, colors and spacing come from theme CSS, not row fields' );
	$nested = array_column( $split_fields['left_column_rows']['layouts'], null, 'name' );
	$nested_faq = array_column( $nested['faq']['sub_fields'], null, 'name' );
	$expect( $nested_faq['faq_frame']['key'] !== $faq_fields['faq_frame']['key'], 'Nested FAQ controls have distinct native ACF keys' );
	$empty = array( 'classes' => array(), 'attributes' => array() );
	$expect( $empty === mrn_base_stack_get_faq_frame_contract( array( 'faq_question_size' => '24px' ) ) && $empty === mrn_base_stack_get_split_aside_contract( array( 'split_column_gap' => '127px' ) ), 'Unselected optional controls cannot change existing rows' );
	foreach ( array( '-1px', '1px;color:red', 'url(https://invalid.test)', array( '24px' ) ) as $bad ) {
		$faq_bad = mrn_base_stack_get_faq_frame_contract( array( 'faq_frame' => 'editorial', 'faq_question_size' => $bad, 'faq_question_weight' => $bad, 'faq_question_color' => $bad ) );
		$split_bad = mrn_base_stack_get_split_aside_contract( array( 'split_frame' => 'aside', 'split_column_gap' => $bad ) );
		$expect( empty( $faq_bad['attributes'] ) && empty( $split_bad['attributes'] ), 'Invalid FAQ/Split controls cannot emit styles: ' . wp_json_encode( $bad ) );
	}
	$zero = array(); foreach ( array( 'margin', 'padding' ) as $kind ) { foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) { $zero[ 'row_spacing_' . $kind . '_' . $side . '_preset' ] = '0px'; } }
	$faq = array_merge( $zero, array( 'acf_fc_layout' => 'faq', 'faq_frame' => 'editorial', 'faq_items' => array_fill( 0, 27, array( 'question' => 'An editable question?', 'answer' => '<p>Keep the complete answer.</p>' ) ), 'start_open' => 1 ) );
	$cta = array_merge( $zero, array( 'acf_fc_layout' => 'cta', 'heading' => 'Aside heading', 'heading_tag' => 'h2', 'content' => '<p>Aside content.</p>', 'cta_frame' => 'stacked', 'cta_content_alignment' => 'start' ) );
	$split = array_merge( $zero, array( 'acf_fc_layout' => 'two_column_split', 'split_frame' => 'aside', 'left_column_rows' => array( $faq ), 'right_column_rows' => array( $cta ) ) );
	update_field( 'field_mrn_page_content_rows', array( $split ), $id ); acf_flush_value_cache( $id, 'page_content_rows' );
	$saved = get_field( 'page_content_rows', $id )[0];
	$expect( 'aside' === $saved['split_frame'] && 'editorial' === $saved['left_column_rows'][0]['faq_frame'] && 'stacked' === $saved['right_column_rows'][0]['cta_frame'] && 'start' === $saved['right_column_rows'][0]['cta_content_alignment'], 'Native nested ACF save/readback retains the selected frames' );
	ob_start(); mrn_base_stack_render_builder_row( $saved, $id, 0 ); $html = ob_get_clean();
	$expect( 27 === substr_count( $html, '<details ' ) && 27 === substr_count( $html, 'Keep the complete answer.' ) && 1 === substr_count( $html, ' open' ), 'All 27 questions and answers render as native details; one starts open' );
	foreach ( array( 'has-split-aside-frame', 'has-faq-frame', 'Aside heading' ) as $fragment ) { $expect( str_contains( $html, $fragment ), 'Saved output includes ' . $fragment ); }
	foreach ( array( '--mrn-split-frame-column-gap', '--mrn-faq-frame-question-size', 'has-cta-heading-size', '--mrn-cta-frame-heading-size' ) as $fragment ) { $expect( ! str_contains( $html, $fragment ), 'Saved output adds no per-row geometry or typography: ' . $fragment ); }
	$expect( array( 'has-split-aside-frame' ) === mrn_base_stack_get_split_aside_contract( array( 'split_frame' => 'aside' ) )['classes'], 'Blank Split typography does not activate property selectors' );
	$expect( in_array( 'has-split-heading-size', mrn_base_stack_get_split_aside_contract( array( 'split_frame' => 'aside', 'split_heading_size' => '32px' ) )['classes'], true ), 'Validated Split typography activates only its own property selector' );
	$baseline = mrn_base_stack_get_cta_frame_contract( array( 'cta_frame' => 'stacked', 'cta_inner_gutter' => '30px' ) );
	$expect( array( 'has-cta-frame' ) === $baseline['classes'] && ! str_contains( $baseline['attributes']['style'] ?? '', 'heading-size' ), 'Existing stacked CTAs do not activate new typography selectors' );
	$cta_color = mrn_base_stack_get_cta_frame_contract( array( 'cta_frame' => 'stacked', 'cta_heading_size' => '1px;color:red', 'cta_heading_weight' => '400;bad', 'cta_text_color' => 'unknown' ) );
	$expect( array( 'has-cta-frame' ) === $cta_color['classes'] && empty( $cta_color['attributes'] ), 'Unsafe CTA typography cannot inject CSS or activate property selectors' );
} finally { wp_delete_post( $id, true ); }
$expect( null === get_post( $id ), 'Owned fixture removed' );
WP_CLI::line( wp_json_encode( array( 'result' => 'passed', 'count' => count( $checks ), 'checks' => $checks, 'source_version' => _S_VERSION, 'candidate_only' => true ), JSON_PRETTY_PRINT ) );
