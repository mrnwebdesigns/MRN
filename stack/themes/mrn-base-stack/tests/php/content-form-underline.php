<?php
// phpcs:ignoreFile -- Isolated WordPress boundary stubs exercise the actual carousel, filter, query and render helpers.
if ( 'cli' !== PHP_SAPI ) { exit; }
class WP_Post { public $ID = 27; public $post_type = 'case_study'; public $post_status = 'publish'; }
class WP_Error {}
function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $v ) { return esc_html( $v ); }
function esc_attr__( $v, $domain = '' ) { return esc_attr( $v ); }
function esc_html__( $v, $domain = '' ) { return esc_html( $v ); }
function __( $v, $domain = '' ) { return $v; }
function esc_url_raw( $v, $schemes = array() ) { return is_string( $v ) && preg_match( '/^https?:\/\//', $v ) ? $v : ''; }
function esc_url( $v, $schemes = array() ) { return esc_attr( esc_url_raw( $v, $schemes ) ); }
function absint( $v ) { return abs( (int) $v ); }
function sanitize_key( $v ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $v ) ); }
function sanitize_title( $v ) { return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $v ) ), '-' ); }
function sanitize_html_class( $v ) { return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $v ); }
function wp_unique_id( $prefix = '' ) { static $n = 0; return $prefix . ++$n; }
function is_wp_error( $v ) { return $v instanceof WP_Error; }
function mrn_base_stack_get_background_color_field( $key ) { return array( 'key' => $key, 'name' => 'background_color', 'type' => 'select' ); }
function mrn_rbl_get_content_link_fields( $seed, $label, $name, $max ) { return array( array( 'key' => $seed, 'name' => $name, 'type' => 'repeater', 'max' => $max ) ); }
function mrn_base_stack_enqueue_presentation() { $GLOBALS['enqueued'] = true; }
function mrn_site_colors_get_all() { return array( array( 'slug' => 'ink' ) ); }
function mrn_site_colors_get_css_var( $slug ) { return '--site-color-' . $slug; }
function get_field( $field, $id ) { return false; }
function get_post_meta( $id, $field, $single ) { return ''; }
function get_post_thumbnail_id( $post ) { return 101; }
function wp_attachment_is_image( $id ) { return 101 === $id; }
function mrn_base_stack_get_image_attachment_id( $value ) { return is_numeric( $value ) ? (int) $value : 0; }
function taxonomy_exists( $tax ) { return in_array( $tax, array( 'service', 'industry', 'feature' ), true ); }
function is_object_in_taxonomy( $type, $tax ) { return 'case_study' === $type; }
function get_the_terms( $id, $tax ) { return $GLOBALS['terms'][ $tax ] ?? false; }
function get_terms( $args ) { return $GLOBALS['all_terms'][ $args['taxonomy'] ] ?? new WP_Error(); }
function get_the_title( $post ) { return $GLOBALS['title']; }
function get_permalink( $post ) { return 'https://example.test/project/'; }
function wp_get_attachment_url( $id ) { return false; }
function mrn_base_stack_get_attachment_image( $id, $size, $attr ) { return '<img src="https://example.test/' . $id . '.jpg" alt="' . esc_attr( $attr['alt'] ) . '">'; }
require_once dirname( __DIR__, 2 ) . '/inc/builder/hero-frame.php';
require_once dirname( __DIR__, 2 ) . '/inc/builder/card-frame.php';
require_once dirname( __DIR__, 2 ) . '/inc/builder/content-frame.php';
require_once dirname( __DIR__, 2 ) . '/inc/builder/faq-aside.php';
$checks = array(); $expect = static function ( $ok, $message ) use ( &$checks ) { if ( ! $ok ) { throw new RuntimeException( $message ); } $checks[] = $message; };
$base = array( 'form_frame' => 'contained', 'form_style' => 'underline' );
$out = mrn_base_stack_get_content_frame_contract( $base, 'wpforms' );
$expect( in_array( 'has-form-underline', $out['classes'], true ) && '1' === $out['attributes']['data-mrn-underline-form'], 'The underline style adds its class and script hook' );
foreach ( array( '', 'rounded', 'underline ', array( 'underline' ), true ) as $i => $bad ) { $expect( ! in_array( 'has-form-underline', mrn_base_stack_get_content_frame_contract( array( 'form_style' => $bad ) + $base, 'wpforms' )['classes'], true ), 'Unknown form style ' . $i . ' keeps the existing form' ); }
$expect( array() === mrn_base_stack_get_content_frame_contract( array( 'form_style' => 'underline' ), 'wpforms' )['classes'], 'The style needs the contained frame' );
$expect( ! in_array( 'has-form-underline', mrn_base_stack_get_content_frame_contract( $base, 'body_text' )['classes'], true ), 'Other layouts never take the form style' );
$fields = mrn_base_stack_get_content_frame_fields( 'wpforms', 'field_form' ); $names = array_column( $fields, 'name' );
$style = $fields[ array_search( 'form_style', $names, true ) ];
$expect( in_array( 'form_style', $names, true ) && isset( $style['choices']['underline'] ) && '' === $style['default_value'], 'The style is a registered select that defaults to the existing form' );
$expect( count( $names ) === count( array_unique( $names ) ) && count( array_column( $fields, 'key' ) ) === count( array_unique( array_column( $fields, 'key' ) ) ), 'Form frame fields keep unique names and keys' );
echo json_encode( array( 'result' => 'passed', 'assertions' => $checks, 'scope' => 'Real source helpers with isolated WordPress boundaries; registered runtime, browser and complete-page acceptance remain separate.' ), JSON_PRETTY_PRINT ) . "\n";
