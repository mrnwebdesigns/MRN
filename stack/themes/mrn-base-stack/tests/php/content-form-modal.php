<?php
// phpcs:ignoreFile -- Isolated WordPress boundary stubs exercise the actual validator, field registration and dialog template.
if ( 'cli' !== PHP_SAPI ) { exit; }
class WP_Post { public $ID = 0; }
function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $v ) { return esc_html( $v ); }
function esc_attr__( $v, $domain = '' ) { return esc_attr( $v ); }
function __( $v, $domain = '' ) { return $v; }
function absint( $v ) { return abs( (int) $v ); }
function sanitize_key( $v ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $v ) ); }
function sanitize_html_class( $v ) { return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $v ); }
function wp_strip_all_tags( $v ) { return strip_tags( (string) $v ); }
function apply_filters( $tag, $v ) { return '<p>' . $v . '</p>'; }
function shortcode_exists( $tag ) { return 'wpforms' === $tag; }
function do_shortcode( $code ) { return '<form class="wpforms-form" data-code="' . esc_attr( $code ) . '"></form>'; }
function mrn_base_stack_get_background_color_field( $key ) { return array( 'key' => $key, 'name' => 'background_color', 'type' => 'select' ); }
function mrn_base_stack_enqueue_presentation() { $GLOBALS['enqueued'] = true; }
function mrn_base_stack_normalize_text_tag( $tag, $fallback ) { return in_array( $tag, array( 'h2', 'h3', 'p' ), true ) ? $tag : $fallback; }
function mrn_base_stack_merge_builder_section_classes( array $classes, array $contract ) { return array_values( array_unique( array_merge( $classes, $contract['classes'] ?? array() ) ) ); }
function mrn_base_stack_merge_builder_attributes( array $a, array $b ) { return array_merge( $a, $b ); }
function mrn_base_stack_get_html_attributes( array $attrs ) { $out = array(); foreach ( $attrs as $k => $v ) { $out[] = $k . '="' . esc_attr( $v ) . '"'; } return implode( ' ', $out ); }
require_once dirname( __DIR__, 2 ) . '/inc/builder/hero-frame.php';
require_once dirname( __DIR__, 2 ) . '/inc/builder/card-frame.php';
require_once dirname( __DIR__, 2 ) . '/inc/builder/content-frame.php';
$checks = array(); $expect = static function ( $ok, $message ) use ( &$checks ) { if ( ! $ok ) { throw new RuntimeException( $message ); } $checks[] = $message; };
foreach ( array( 'audit-modal', 'a', 'form-1' ) as $good ) { $expect( $good === mrn_base_stack_get_form_modal_id( array( 'form_modal_id' => $good ) ), "Anchor ID {$good} is accepted" ); }
foreach ( array( '', 'Audit', '1modal', 'a b', '#audit', 'a"onclick', array( 'a' ), null, true, str_repeat( 'a', 64 ), "audit\n" ) as $i => $bad ) { $expect( '' === mrn_base_stack_get_form_modal_id( array( 'form_modal_id' => $bad ) ), "Unsafe anchor ID {$i} is refused" ); }
$expect( '' === mrn_base_stack_get_form_modal_id( array() ), 'A row without the field is never a dialog' );
$fields = mrn_base_stack_get_content_frame_fields( 'wpforms', 'field_form' ); $names = array_column( $fields, 'name' );
$field = $fields[ array_search( 'form_modal_id', $names, true ) ] ?? null;
$expect( $field && 'text' === $field['type'] && '' === $field['default_value'] && empty( $field['conditional_logic'] ), 'The field is registered, blank by default, and does not depend on the contained frame' );
$expect( ! in_array( 'form_modal_id', array_column( mrn_base_stack_get_content_frame_fields( 'body_text', 'field_body' ), 'name' ), true ), 'Other layouts never get the field' );
$expect( count( $names ) === count( array_unique( $names ) ) && count( array_column( $fields, 'key' ) ) === count( array_unique( array_column( $fields, 'key' ) ) ), 'Form frame fields keep unique names and keys' );
$render = static function ( array $row ) { $args = array( 'row' => $row ); ob_start(); include dirname( __DIR__, 2 ) . '/template-parts/builder/wpforms.php'; return ob_get_clean(); };
$form = new WP_Post(); $form->ID = 804;
$html = $render( array( 'form' => $form, 'form_modal_id' => 'audit-modal', 'heading' => 'Free, Custom Website Audit' ) );
$expect( str_contains( $html, 'id="audit-modal"' ) && str_contains( $html, 'role="dialog"' ) && str_contains( $html, 'aria-modal="true"' ) && str_contains( $html, 'aria-labelledby="audit-modal-title"' ) && str_contains( $html, 'id="audit-modal-title"' ), 'The dialog is named by its heading' );
$expect( 1 === preg_match( '/<div[^>]*id="audit-modal"[^>]*>/', $html, $tag ) && ! str_contains( $tag[0], 'aria-hidden' ), 'The dialog is never aria-hidden, so the no-script :target state stays accessible' );
$expect( str_contains( $html, '[wpforms id=&quot;804&quot; title=&quot;false&quot; description=&quot;false&quot;]' ) && 2 === substr_count( $html, 'data-mrn-form-modal-close' ), 'The selected form is embedded with a backdrop and a close control' );
$expect( ! str_contains( $html, 'mrn-content-builder__row--wpforms' ) && ! empty( $GLOBALS['enqueued'] ), 'The dialog replaces the in-page section and loads the presentation assets' );
$plain = $render( array( 'form' => $form, 'heading' => 'Contact' ) );
$expect( str_contains( $plain, 'mrn-content-builder__row--wpforms' ) && ! str_contains( $plain, 'role="dialog"' ), 'Rows without the field render exactly as before' );
$bad = $render( array( 'form' => $form, 'form_modal_id' => 'Bad Id', 'heading' => 'Contact' ) );
$expect( str_contains( $bad, 'mrn-content-builder__row--wpforms' ) && ! str_contains( $bad, 'role="dialog"' ), 'An invalid ID falls back to the in-page form' );
$unnamed = $render( array( 'form' => $form, 'form_modal_id' => 'quick-form' ) );
$expect( str_contains( $unnamed, 'aria-label="Form"' ) && ! str_contains( $unnamed, 'aria-labelledby' ), 'A dialog without a heading still has an accessible name' );
echo json_encode( array( 'result' => 'passed', 'assertions' => $checks, 'scope' => 'Real source helpers and template with isolated WordPress boundaries; browser keyboard behavior is verified separately.' ), JSON_PRETTY_PRINT ) . "\n";
