<?php
// phpcs:ignoreFile -- Standalone WordPress/ACF hook harness; actual hydration is checked at runtime.
/** Editor startup hooks must not disable conservative empty public builders. */
$GLOBALS['wp_filter'] = array();
$GLOBALS['empty_fields'] = array();
$GLOBALS['empty_meta'] = array();
$GLOBALS['empty_stores'] = array();
$GLOBALS['empty_admin'] = false;
$GLOBALS['empty_ajax'] = false;
$GLOBALS['empty_preview'] = false;
function add_filter( $name, $callback, $priority = 10, $args = 1 ) {
 if ( ! isset( $GLOBALS['wp_filter'][ $name ] ) ) { $GLOBALS['wp_filter'][ $name ] = (object) array( 'callbacks' => array() ); }
 $GLOBALS['wp_filter'][ $name ]->callbacks[ $priority ][] = array( 'function' => $callback, 'accepted_args' => $args );
}
function add_action( ...$args ) {}
function apply_filters( $hook, $value, ...$args ) { return $value; }
function is_admin() { return $GLOBALS['empty_admin']; }
function wp_doing_ajax() { return $GLOBALS['empty_ajax']; }
function is_preview() { return $GLOBALS['empty_preview']; }
function wp_is_post_revision( $id ) { return false; }
function acf_get_local_field( $key ) { return $GLOBALS['empty_fields'][ str_replace( 'field_mrn_', '', $key ) ] ?? false; }
function get_post_meta( $id, $key, $single = false ) { return $GLOBALS['empty_meta'][ $key ] ?? ''; }
class MrnEmptyBuilderStore {
 public $data = array();
 public function get( $key ) { return $this->data[ $key ] ?? null; }
}
function acf_get_store( $name ) {
 if ( ! isset( $GLOBALS['empty_stores'][ $name ] ) ) { $GLOBALS['empty_stores'][ $name ] = new MrnEmptyBuilderStore(); }
 return $GLOBALS['empty_stores'][ $name ];
}
require dirname( __DIR__, 2 ) . '/inc/builder/editor-startup.php';
require dirname( __DIR__, 2 ) . '/inc/builder/performance.php';
$assertions = 0;
$expect = static function ( $condition, $label ) use ( &$assertions ) { ++$assertions; if ( ! $condition ) { throw new RuntimeException( $label ); } };
foreach ( array( 'page_hero_rows', 'page_after_content_rows' ) as $name ) {
 $field = array( 'key' => 'field_mrn_' . $name, 'name' => $name, 'type' => 'flexible_content', 'layouts' => array() );
 $GLOBALS['empty_fields'][ $name ] = $field;
 $GLOBALS['empty_meta'][ '_' . $name ] = $field['key'];
 $expect( $field === mrn_base_stack_stage_initial_builder_catalog( $field ) && $field === mrn_base_stack_restore_initial_builder_catalog( $field ), $name . ': actual startup hooks are identity operations' );
 $expect( mrn_base_stack_builder_field_is_empty( $name, 7 ), $name . ': actual registered startup hooks retain empty fast path' );
 $GLOBALS['empty_meta'][ $name ] = array( 'body_text' );
 $expect( ! mrn_base_stack_builder_field_is_empty( $name, 7 ), $name . ': populated builders retain native loading' ); unset( $GLOBALS['empty_meta'][ $name ] );
 $GLOBALS['empty_fields'][ $name ]['default_value'] = array( array( 'acf_fc_layout' => 'body_text' ) );
 $expect( ! mrn_base_stack_builder_field_is_empty( $name, 7 ), $name . ': defaults retain native loading' ); $GLOBALS['empty_fields'][ $name ] = $field;
 acf_get_store( 'values' )->data[ '7:' . $name . ':formatted' ] = array( 'body_text' );
 $expect( ! mrn_base_stack_builder_field_is_empty( $name, 7 ), $name . ': formatted cached values retain native loading' ); acf_get_store( 'values' )->data = array();
 $GLOBALS['empty_meta'][ '_' . $name ] = 'field_foreign_builder';
 $expect( ! mrn_base_stack_builder_field_is_empty( $name, 7 ), $name . ': alternate references retain native loading' ); $GLOBALS['empty_meta'][ '_' . $name ] = $field['key'];
 $unknown = static function ( $value ) { return $value; }; add_filter( 'acf/pre_load_value', $unknown );
 $expect( ! mrn_base_stack_builder_field_is_empty( $name, 7 ), $name . ': unknown callbacks still retain native loading' ); unset( $GLOBALS['wp_filter']['acf/pre_load_value'] );
 foreach ( array( 'empty_admin', 'empty_ajax', 'empty_preview' ) as $flag ) {
  $GLOBALS[ $flag ] = true; $expect( ! mrn_base_stack_builder_field_is_empty( $name, 7 ), $name . ': ' . $flag . ' retains native loading' ); $GLOBALS[ $flag ] = false;
 }
}
$expect( ! mrn_base_stack_builder_field_is_empty( 'page_content_rows', 7 ), 'Other builders are not admitted' );
echo 'PASS: ' . $assertions . " empty-builder/startup regression assertions.\n";
