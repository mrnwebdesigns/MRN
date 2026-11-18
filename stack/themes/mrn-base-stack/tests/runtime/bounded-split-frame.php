<?php
/** Native bounded columns: persistence, empty-column behavior and input scoping. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || home_url() !== getenv( 'MRN_STACK_TEST_HOME' ) || ! str_ends_with( (string) wp_parse_url( home_url(), PHP_URL_HOST ), '.localhost' ) || ! current_user_can( 'manage_options' ) ) { throw new RuntimeException( 'An explicitly selected local administrator runtime is required.' ); }
$checks = array();
$expect = static function ( $condition, $message ) use ( &$checks ) { if ( ! $condition ) { throw new RuntimeException( esc_html( $message ) ); } $checks[] = $message; };
$layouts = array_column( acf_get_field( 'field_mrn_page_content_rows' )['layouts'], null, 'name' );
$fields = array_column( $layouts['two_column_split']['sub_fields'], null, 'name' );
$expect( isset( $fields['split_frame']['choices']['bounded'], $fields['split_stack_breakpoint']['choices']['900'], $fields['split_row_vertical_padding'] ), 'Native bounded columns, 900px stacking and optional vertical row spacing are selectable' );
$props = array( 'split_first_column_min' => '120px', 'split_first_column_max' => '240px', 'split_second_column_max' => '750px', 'split_column_gap_tablet' => '64px' );
$expect( ! array_intersect( array_merge( array_keys( $props ), array( 'split_column_gap', 'split_column_gap_mobile' ) ), array_keys( $fields ) ), 'Bounded column widths and column gaps come from theme CSS, not row fields (the contract still validates values passed directly)' );
$nested_body = array_column( array_column( $fields['right_column_rows']['layouts'], null, 'name' )['body_text']['sub_fields'], null, 'name' );
$expect( ! isset( $nested_body['body_text_letter_spacing'] ) && ! isset( $nested_body['body_text_letter_spacing_mobile'] ), 'Nested Body Text tracking comes from theme CSS, not row fields' );
$zero = array(); foreach ( array( 'margin', 'padding' ) as $kind ) { foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) { $zero[ 'row_spacing_' . $kind . '_' . $side . '_preset' ] = '0px'; } }
$text = array_merge( $zero, array( 'acf_fc_layout' => 'body_text', 'body_frame' => 'contained', 'body_text' => '<p>Editable bounded content.</p>' ) );
$split = array_merge( $zero, array( 'acf_fc_layout' => 'two_column_split', 'split_frame' => 'bounded', 'split_stack_breakpoint' => '900', 'left_column_rows' => array( $text ), 'right_column_rows' => array( $text ) ) );
$id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'Owned bounded columns proof' ), true );
$expect( ! is_wp_error( $id ) && $id > 0, 'Owned native draft created' );
try {
	update_field( 'field_mrn_page_content_rows', array( $split ), $id ); acf_flush_value_cache( $id, 'page_content_rows' ); $saved = get_field( 'page_content_rows', $id )[0];
	$expect( 'bounded' === $saved['split_frame'] && '900' === $saved['split_stack_breakpoint'] && 1 === count( $saved['right_column_rows'] ), 'Native ACF retains the bounded frame, stacking breakpoint and nested rows' );
	ob_start(); mrn_base_stack_render_builder_row( $saved, $id, 0 ); $html = ob_get_clean();
	foreach ( array( 'has-split-bounded-columns', 'split-stack-900' ) as $fragment ) { $expect( str_contains( $html, $fragment ), 'Native output includes ' . $fragment ); }
	foreach ( array( '--mrn-split-frame-first-column-min', '--mrn-split-frame-first-column-max', '--mrn-split-frame-second-column-max', '--mrn-split-frame-column-gap', '--mrn-body-frame-text-letter-spacing' ) as $fragment ) { $expect( ! str_contains( $html, $fragment ), 'Saved bounded columns add no per-row geometry: ' . $fragment ); }
	foreach ( array( 'left', 'right' ) as $side ) {
		$empty = $saved; $empty[ $side . '_column_rows' ] = array();
		ob_start(); mrn_base_stack_render_builder_row( $empty, $id, 1 ); $output = ob_get_clean();
		$expect( str_contains( $output, 'has-empty-' . $side . '-column' ), 'Empty ' . $side . ' column receives a scoped marker' );
	}
	foreach ( array( '-1px', '1px;color:red', 'var(--unapproved)', array( '120px' ) ) as $bad ) {
		$contract = mrn_base_stack_get_split_aside_contract( array_merge( array( 'split_frame' => 'bounded' ), array_fill_keys( array_keys( $props ), $bad ) ) );
		$expect( empty( $contract['attributes'] ), 'Unsafe bounded lengths cannot emit styles' );
		$tracking = mrn_base_stack_get_content_frame_contract( array( 'body_frame' => 'contained', 'body_text_letter_spacing_mobile' => '-1px' === $bad ? '-11px' : $bad ), 'body_text' );
		$expect( empty( $tracking['attributes'] ), 'Unsafe mobile tracking cannot emit styles' );
	}
	$heading_tracking = mrn_base_stack_get_content_frame_contract( array( 'body_frame' => 'contained', 'body_heading_letter_spacing' => '-0.32px' ), 'body_text' );
	$expect( in_array( 'has-body-heading-letter-spacing', $heading_tracking['classes'], true ) && str_contains( $heading_tracking['attributes']['style'], '--mrn-body-frame-heading-letter-spacing:-0.32px' ), 'Heading tracking is independently editable' );
	$expect( empty( mrn_base_stack_get_content_frame_contract( array( 'body_frame' => 'contained', 'body_heading_letter_spacing' => '0px;color:red' ), 'body_text' )['attributes'] ), 'Unsafe heading tracking cannot emit styles' );
	$expect( array( 'classes' => array(), 'attributes' => array() ) === mrn_base_stack_get_split_aside_contract( $props ), 'Unselected bounded controls preserve existing rows' );
	$expect( empty( mrn_base_stack_get_split_aside_contract( array_merge( $props, array( 'split_frame' => 'columns' ) ) )['attributes'] ), 'Bounded controls cannot alter ordinary ratio columns' );
	$expect( array( 'has-split-columns-frame', 'split-stack-991' ) === mrn_base_stack_get_split_aside_contract( array( 'split_frame' => 'columns' ) )['classes'], 'Existing columns retain their 991px default' );
	$expect( in_array( 'has-split-row-vertical-padding', mrn_base_stack_get_split_aside_contract( array( 'split_frame' => 'bounded', 'split_row_vertical_padding' => 1 ) )['classes'], true ), 'Selected bounded columns use saved row padding' );
	$expect( in_array( 'has-split-row-vertical-padding', mrn_base_stack_get_split_aside_contract( array( 'split_frame' => 'aside', 'split_row_vertical_padding' => 1 ) )['classes'], true ), 'Selected aside frames use saved row padding' );
	$expect( ! in_array( 'has-split-row-vertical-padding', mrn_base_stack_get_split_aside_contract( array( 'split_frame' => 'aside' ) )['classes'], true ), 'Unselected aside spacing remains unchanged' );
} finally { wp_delete_post( $id, true ); }
$expect( null === get_post( $id ), 'Owned bounded columns draft removed' );
WP_CLI::line( wp_json_encode( array( 'result' => 'passed', 'count' => count( $checks ), 'checks' => $checks, 'candidate_only' => true ), JSON_PRETTY_PRINT ) );
