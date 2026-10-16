<?php
/** Native finite Tabs/Split composition qualification on an owned local draft. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || home_url() !== getenv( 'MRN_STACK_TEST_HOME' ) || ! str_ends_with( (string) wp_parse_url( home_url(), PHP_URL_HOST ), '.localhost' ) || ! current_user_can( 'manage_options' ) ) {
	throw new RuntimeException( 'An explicitly selected local administrator runtime is required.' );
}
$checks = array();
$expect = static function ( $condition, $message ) use ( &$checks ) {
	if ( ! $condition ) { throw new RuntimeException( esc_html( $message ) ); }
	$checks[] = $message;
};
$id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'Native Tabs and Split qualification' ), true );
$expect( ! is_wp_error( $id ) && $id > 0, 'Owned local draft created' );
try {
	$root = array_column( acf_get_field( 'field_mrn_page_content_rows' )['layouts'], null, 'name' );
	$split_fields = array_column( $root['two_column_split']['sub_fields'], null, 'name' );
	$expect( isset( $split_fields['column_rows_mode']['choices']['stack'] ) && 10 === $split_fields['left_column_rows']['max'] && 10 === $split_fields['right_column_rows']['max'], 'Split exposes opt-in stacks bounded to ten children per column' );
	$nested = array_column( $split_fields['left_column_rows']['layouts'], null, 'name' );
	$expect( isset( $nested['stats'] ) && ! isset( $nested['two_column_split'], $nested['tabbed_layout'] ), 'Split offers native Stats without recursive containers' );
	$root_stats = array_column( $root['stats']['sub_fields'], null, 'name' );
	$nested_stats = array_column( $nested['stats']['sub_fields'], null, 'name' );
	$expect( $root_stats['stat_items']['key'] !== $nested_stats['stat_items']['key'] && 'field_mrn_stats_items' === $root_stats['stat_items']['key'], 'Stats cloning preserves root keys and isolates nested keys' );
	$expect( isset( $root_stats['display_style'], $root_stats['section_width'], $root_stats['sub_content_width'] ), 'Root Stats retain Display Styles and width controls after nested registration' );
	$expect( isset( $nested_stats['display_style'] ) && $nested_stats['display_style']['key'] !== $root_stats['display_style']['key'], 'Generated nested controls have isolated ACF keys' );
	$tab_fields = array_column( $root['tabbed_layout']['sub_fields'], null, 'name' );
	$tab_item_fields = array_column( $tab_fields['tabs']['sub_fields'], null, 'name' );
	$expect( isset( $tab_item_fields['tab_accessible_label'] ), 'Icon-only tabs expose an editable accessible name' );
	$panels = array_column( $tab_item_fields['panel_rows']['layouts'], null, 'name' );
	$expect( isset( $panels['two_column_split'] ) && ! isset( $panels['tabbed_layout'] ) && 1 === $tab_item_fields['panel_rows']['max'], 'Tabs offer one finite Split panel rather than a recursive builder' );
	$panel_split_fields = array_column( $panels['two_column_split']['sub_fields'], null, 'name' );
	$expect( $panel_split_fields['column_rows_mode']['key'] !== $split_fields['column_rows_mode']['key'], 'Cloned Split presentation keys are isolated in Tabs' );
	$zero = array();
	foreach ( array( 'margin', 'padding' ) as $dimension ) {
		foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) { $zero[ 'row_spacing_' . $dimension . '_' . $side . '_preset' ] = '0px'; }
	}
	$split = array_merge( $zero, array(
		'acf_fc_layout' => 'two_column_split', 'column_rows_mode' => 'stack', 'column_content_gap' => '24px', 'column_alignment' => 'center',
		'left_column_rows' => array(
			array_merge( $zero, array( 'acf_fc_layout' => 'body_text', 'heading' => 'Editable panel title', 'heading_tag' => 'h2', 'body_text' => '<p>First component.</p>' ) ),
			array_merge( $zero, array( 'acf_fc_layout' => 'stats', 'stat_items' => array( array( 'value' => '175%', 'item_label' => 'More qualified traffic', 'animate_value' => 0 ) ), 'show_dividers' => 0 ) ),
			array_merge( $zero, array( 'acf_fc_layout' => 'body_text', 'body_text' => '<blockquote><p>Rich copy after the metric.</p></blockquote>' ) ),
			array_merge( $zero, array( 'acf_fc_layout' => 'cta', 'links' => array( array( 'link' => array( 'url' => home_url( '/contact-us/?first=1&second=2' ), 'title' => 'Last column action', 'target' => '_blank' ), 'is_button' => 1 ) ) ) ),
		),
		'right_column_rows' => array( array_merge( $zero, array( 'acf_fc_layout' => 'body_text', 'heading' => 'Right column content', 'heading_tag' => 'h3' ) ) ),
	) );
	$tabs = array_merge( $zero, array( 'acf_fc_layout' => 'tabbed_layout', 'heading' => 'Native tabbed composition', 'tab_switch_effect' => 'instant', 'tabs' => array(
		array( 'tab_label' => '', 'tab_accessible_label' => 'First client results', 'link_icon_source' => 'dashicons', 'link_icon_dashicon' => 'dashicons-chart-line', 'panel_rows' => array( $split ) ),
		array( 'tab_label' => 'Visible tab name', 'tab_accessible_label' => 'Ignored conflicting name', 'panel_rows' => array( array_merge( $zero, array( 'acf_fc_layout' => 'body_text', 'heading' => 'Second panel content', 'heading_tag' => 'h2' ) ) ) ),
	) ) );
	update_field( 'field_mrn_page_content_rows', array( $tabs ), $id );
	$readback = get_field( 'page_content_rows', $id )[0];
	$loaded_split = $readback['tabs'][0]['panel_rows'][0];
	$expect( 4 === count( $loaded_split['left_column_rows'] ) && 'stats' === $loaded_split['left_column_rows'][1]['acf_fc_layout'], 'Native ACF roundtrip retains every nested child in authored order' );
	$expect( '175%' === $loaded_split['left_column_rows'][1]['stat_items'][0]['value'] && 'More qualified traffic' === $loaded_split['left_column_rows'][1]['stat_items'][0]['item_label'], 'Nested Stats preserve metric and label through ACF' );
	$expect( 'First client results' === $readback['tabs'][0]['tab_accessible_label'] && '24px' === $loaded_split['column_content_gap'], 'Native ACF retains accessible name and authored internal gap' );
	ob_start(); mrn_base_stack_render_builder_row( $readback, $id, 3 ); $html = ob_get_clean();
	foreach ( array( 'Editable panel title', '175%', 'More qualified traffic', 'Rich copy after the metric.', 'Last column action', 'Right column content', 'Second panel content' ) as $copy ) {
		$expect( str_contains( $html, $copy ), 'Native render retains ' . $copy );
	}
	$expect( str_contains( html_entity_decode( $html, ENT_QUOTES | ENT_HTML5, 'UTF-8' ), 'href="' . home_url( '/contact-us/?first=1&second=2' ) . '"' ) && str_contains( $html, 'rel="noopener noreferrer"' ), 'Final child action renders its complete query URL and safe target' );
	$expect( str_contains( $html, '<span class="screen-reader-text">First client results</span>' ) && ! str_contains( $html, 'Ignored conflicting name' ), 'Icon-only naming works and visible names retain label-in-name' );
	$expect( str_contains( $html, '--mrn-split-content-gap: 24px' ) && str_contains( $html, 'align-items: center' ), 'Authored native gap and alignment control composition geometry' );
	preg_match_all( '/\bid="([^"]+)"/', $html, $ids );
	$expect( count( $ids[1] ) === count( array_unique( $ids[1] ) ), 'Nested composition has no duplicate HTML IDs' );
	$single = $split; unset( $single['column_rows_mode'] );
	ob_start(); mrn_base_stack_render_builder_row( $single, $id, 4 ); $single_html = ob_get_clean();
	$expect( str_contains( $single_html, 'Editable panel title' ) && ! str_contains( $single_html, '175%' ) && ! str_contains( $single_html, 'Last column action' ) && ! str_contains( $single_html, '--mrn-split-content-gap' ), 'Unset mode retains existing first-child rendering without activating dormant children' );
	$unsafe = $split; $unsafe['column_content_gap'] = '1px; color:red'; $unsafe['column_alignment'] = 'center; color:red';
	ob_start(); mrn_base_stack_render_builder_row( $unsafe, $id, 5 ); $unsafe_html = ob_get_clean();
	$expect( ! str_contains( $unsafe_html, 'color:red' ) && ! str_contains( $unsafe_html, '--mrn-split-content-gap' ), 'Malformed gap and alignment cannot inject styles' );
	$proof = getenv( 'MRN_SPLIT_PROOF_PATH' );
	if ( $proof ) {
		$expect( str_ends_with( $proof, '.html' ) && is_dir( dirname( $proof ) ), 'Explicit source proof destination exists' );
		$document = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Native Tabs and Split qualification</title><link rel="stylesheet" href="' . esc_url( includes_url( 'css/dashicons.min.css' ) ) . '"><link rel="stylesheet" href="style.css"><script defer src="front-end-tabs.js"></script></head><body><main><h1>Native Tabs and Split preview</h1>' . $html . '</main></body></html>';
		$expect( false !== file_put_contents( $proof, $document ), 'Static proof saved without modifying authored pages' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Explicit local CLI proof.
	}
} finally {
	wp_delete_post( $id, true );
}
$expect( null === get_post( $id ), 'Owned fixture removed' );
WP_CLI::line( wp_json_encode( array( 'checks' => $checks, 'count' => count( $checks ), 'result' => 'passed', 'source_version' => _S_VERSION ), JSON_PRETTY_PRINT ) );
