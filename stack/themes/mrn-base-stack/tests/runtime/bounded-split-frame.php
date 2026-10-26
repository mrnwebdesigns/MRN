<?php
/** Native bounded columns: persistence, empty-column behavior and input scoping. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || home_url() !== getenv( 'MRN_STACK_TEST_HOME' ) || ( '127.0.0.1' !== wp_parse_url( home_url(), PHP_URL_HOST ) && ! str_ends_with( (string) wp_parse_url( home_url(), PHP_URL_HOST ), '.localhost' ) ) || ! current_user_can( 'manage_options' ) ) { throw new RuntimeException( 'An explicitly selected local administrator runtime is required.' ); }
$checks = array();
$expect = static function ( $condition, $message ) use ( &$checks ) { if ( ! $condition ) { throw new RuntimeException( esc_html( $message ) ); } $checks[] = $message; };
$layouts = array_column( acf_get_field( 'field_mrn_page_content_rows' )['layouts'], null, 'name' );
$fields = array_column( $layouts['two_column_split']['sub_fields'], null, 'name' );
$expect( isset( $fields['split_frame']['choices']['bounded'], $fields['split_stack_breakpoint']['choices']['900'] ), 'Native bounded columns and 900px stacking are selectable' );
$props = array( 'split_first_column_min' => '120px', 'split_first_column_max' => '240px', 'split_second_column_max' => '750px', 'split_column_gap_tablet' => '64px' );
foreach ( $props as $name => $value ) { $expect( isset( $fields[ $name ] ) && 'bounded' === $fields[ $name ]['conditional_logic'][0][0]['value'], 'Bounded-only native field: ' . $name ); }
$zero = array(); foreach ( array( 'margin', 'padding' ) as $kind ) { foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) { $zero[ 'row_spacing_' . $kind . '_' . $side . '_preset' ] = '0px'; } }
$text = array_merge( $zero, array( 'acf_fc_layout' => 'body_text', 'body_frame' => 'contained', 'body_text' => '<p>Editable bounded content.</p>', 'body_text_letter_spacing' => '-0.4px', 'body_text_letter_spacing_mobile' => '-0.36px' ) );
$split = array_merge( $zero, $props, array( 'acf_fc_layout' => 'two_column_split', 'split_frame' => 'bounded', 'split_stack_breakpoint' => '900', 'split_column_gap' => 'clamp(40px, 8vw, 127px)', 'split_column_gap_mobile' => '40px', 'left_column_rows' => array( $text ), 'right_column_rows' => array( $text ) ) );
$id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'Owned bounded columns proof' ), true );
$expect( ! is_wp_error( $id ) && $id > 0, 'Owned native draft created' );
try {
	update_field( 'field_mrn_page_content_rows', array( $split ), $id ); acf_flush_value_cache( $id, 'page_content_rows' ); $saved = get_field( 'page_content_rows', $id )[0];
	foreach ( $props as $name => $value ) { $expect( $value === $saved[ $name ], 'Native ACF retains ' . $name ); }
	$expect( '-0.36px' === $saved['right_column_rows'][0]['body_text_letter_spacing_mobile'], 'Nested native mobile tracking persists' );
	ob_start(); mrn_base_stack_render_builder_row( $saved, $id, 0 ); $html = ob_get_clean();
	foreach ( array( 'has-split-bounded-columns', 'split-stack-900', '--mrn-split-frame-first-column-min:120px', '--mrn-split-frame-first-column-max:240px', '--mrn-split-frame-second-column-max:750px', '--mrn-split-frame-column-gap-tablet:64px', '--mrn-body-frame-text-letter-spacing-mobile:-0.36px' ) as $fragment ) { $expect( str_contains( $html, $fragment ), 'Native output includes ' . $fragment ); }
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
	$expect( array( 'classes' => array(), 'attributes' => array() ) === mrn_base_stack_get_split_aside_contract( $props ), 'Unselected bounded controls preserve existing rows' );
	$expect( empty( mrn_base_stack_get_split_aside_contract( array_merge( $props, array( 'split_frame' => 'columns' ) ) )['attributes'] ), 'Bounded controls cannot alter ordinary ratio columns' );
	$expect( array( 'has-split-columns-frame', 'split-stack-991' ) === mrn_base_stack_get_split_aside_contract( array( 'split_frame' => 'columns' ) )['classes'], 'Existing columns retain their 991px default' );
} finally { wp_delete_post( $id, true ); }
$expect( null === get_post( $id ), 'Owned bounded columns draft removed' );
WP_CLI::line( wp_json_encode( array( 'result' => 'passed', 'count' => count( $checks ), 'checks' => $checks, 'candidate_only' => true ), JSON_PRETTY_PRINT ) );
