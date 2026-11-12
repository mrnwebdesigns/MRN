<?php
// phpcs:ignoreFile -- Isolated WordPress boundary stubs exercise the actual split contract and field registration.
if ( 'cli' !== PHP_SAPI ) { exit; }
function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $v ) { return esc_html( $v ); }
function absint( $v ) { return abs( (int) $v ); }
function sanitize_key( $v ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $v ) ); }
function mrn_base_stack_get_background_color_field( $key ) { return array( 'key' => $key, 'name' => 'background_color', 'type' => 'select' ); }
function mrn_site_colors_get_all() { return array(); }
require_once dirname( __DIR__, 2 ) . '/inc/builder/hero-frame.php';
require_once dirname( __DIR__, 2 ) . '/inc/builder/card-frame.php';
require_once dirname( __DIR__, 2 ) . '/inc/builder/content-frame.php';
require_once dirname( __DIR__, 2 ) . '/inc/builder/faq-aside.php';
$checks = array(); $expect = static function ( $ok, $message ) use ( &$checks ) { if ( ! $ok ) { throw new RuntimeException( $message ); } $checks[] = $message; };
foreach ( array( 'columns', 'bounded' ) as $frame ) {
	$out = mrn_base_stack_get_split_aside_contract( array( 'split_frame' => $frame, 'split_row_gap' => '30px' ) );
	$expect( in_array( 'has-split-row-gap', $out['classes'], true ) && str_contains( $out['attributes']['style'] ?? '', '--mrn-split-frame-row-gap:30px' ), "The {$frame} frame emits the row gap and its hook class" );
}
$aside = mrn_base_stack_get_split_aside_contract( array( 'split_frame' => 'aside', 'split_row_gap' => '30px' ) );
$expect( ! in_array( 'has-split-row-gap', $aside['classes'], true ) && ! str_contains( $aside['attributes']['style'] ?? '', 'row-gap' ), 'The aside frame ignores the row gap' );
foreach ( array( '-1px', '30px;color:red', 'var(--x)', array( '30px' ), '' ) as $i => $bad ) {
	$out = mrn_base_stack_get_split_aside_contract( array( 'split_frame' => 'columns', 'split_row_gap' => $bad ) );
	$expect( ! in_array( 'has-split-row-gap', $out['classes'], true ) && ! str_contains( $out['attributes']['style'] ?? '', 'row-gap' ), "Unsafe or blank row gap {$i} emits nothing" );
}
$fields = mrn_base_stack_get_split_aside_fields( 'field_split' ); $by = array_column( $fields, null, 'name' );
$modes = array_map( static fn( $c ) => $c[0]['value'], $by['split_row_gap']['conditional_logic'] ?? array() );
$expect( isset( $by['split_row_gap'] ) && 'text' === $by['split_row_gap']['type'] && array( 'columns', 'bounded' ) === array_column( array_map( static fn( $c ) => $c[0], $by['split_row_gap']['conditional_logic'] ), 'value' ), 'The field is registered for the columns and bounded frames only' );
$expect( count( array_column( $fields, 'name' ) ) === count( array_unique( array_column( $fields, 'name' ) ) ) && count( array_column( $fields, 'key' ) ) === count( array_unique( array_column( $fields, 'key' ) ) ), 'Split field names and keys stay unique' );
echo json_encode( array( 'result' => 'passed', 'assertions' => $checks ), JSON_PRETTY_PRINT ) . "\n";
