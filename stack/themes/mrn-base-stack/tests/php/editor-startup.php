<?php
// phpcs:ignoreFile -- Standalone hook harness; native form saves are covered by runtime QA.
/** Guard initial-state and deferred-widget ownership boundaries. */
class WP_Screen {
	public $base = 'post';
	public $post_type = 'page';
}
class MRN_Test_Flexible_Layout {}
class_alias( MRN_Test_Flexible_Layout::class, 'ACF\Pro\Fields\FlexibleContent\Layout' );
function add_action( ...$args ) {}
function add_filter( ...$args ) {}
function is_admin() { return $GLOBALS['admin']; }
function sanitize_text_field( $value ) { return $value; }
function wp_unslash( $value ) { return $value; }
function wp_doing_ajax() { return $GLOBALS['ajax']; }
function get_current_screen() { return $GLOBALS['screen']; }
function mrn_base_stack_admin_is_safe_acf_editor_helper_screen( $screen ) { return true; }
function mrn_base_stack_get_singular_shell_post_types() { return array( 'page', 'post' ); }
function mrn_base_stack_get_builder_layout_allowlist_targets() { return array( 'page_content_rows' => array() ); }
function mrn_base_stack_admin_is_initial_acf_row_collapse_enabled( ...$args ) { return $GLOBALS['collapse']; }
function mrn_base_stack_get_builder_layout_allowlist_post_id() { return 41; }
function mrn_base_stack_get_builder_layout_allowlist_used_layout_names( ...$args ) { return $GLOBALS['saved_names']; }
function acf_get_local_fields( $key ) {
	return array(
		array( 'key' => 'saved_clone', 'parent_layout' => 'layout_basic' ),
		array( 'key' => 'unused_nested_tree', 'parent_layout' => 'layout_complex' ),
	);
}
function acf_get_field( $key ) {
	if ( 'unused_nested_tree' === $key ) { throw new RuntimeException( 'Unused nested fields must not hydrate.' ); }
	return array( 'key' => $key, 'type' => 'clone', 'parent_layout' => 'layout_basic' );
}
class MRN_Test_Field_Type {
	public function load_field( $field ) {
		$field['native_loaded'] = true;
		return $field;
	}
}
function acf_get_field_type( $name ) { return $GLOBALS['field_type']; }
function apply_filters( $hook, $fields, $parent ) {
	if ( 'acf/load_fields' !== $hook ) { return $fields; }
	return array( array( 'key' => 'expanded_saved_text', 'type' => 'wysiwyg', 'parent_layout' => 'layout_basic' ) );
}
require dirname( __DIR__, 2 ) . '/inc/builder/editor-startup.php';
function mrn_startup_expect( $condition, $message ) {
	if ( ! $condition ) { throw new RuntimeException( $message ); }
}
$GLOBALS['admin'] = true;
$GLOBALS['ajax'] = false;
$GLOBALS['collapse'] = true;
$GLOBALS['screen'] = new WP_Screen();
$GLOBALS['saved_names'] = array( 'basic' );
$_SERVER['REQUEST_METHOD'] = 'GET';
$source = array( 'key' => 'field_mrn_page_content_rows', 'layouts' => array(
	array( 'key' => 'layout_basic', 'name' => 'basic', 'label' => 'Basic' ),
	array( 'key' => 'layout_complex', 'name' => 'tabbed_layout', 'label' => 'Tabs' ),
) );
$initial = mrn_base_stack_get_initial_builder_source_layouts( $source );
mrn_startup_expect( 'expanded_saved_text' === $initial[0]['sub_fields'][0]['key'], 'Native seamless clone expansion must preserve saved controls.' );
mrn_startup_expect( array() === $initial[1]['sub_fields'] && 'Tabs' === $initial[1]['label'], 'Unused complex layout metadata remains available without loading its fields.' );
$_SERVER['REQUEST_METHOD'] = 'POST';
mrn_startup_expect( null === mrn_base_stack_get_initial_builder_source_layouts( $source ), 'Native POST saves always use full definitions.' );
$_SERVER['REQUEST_METHOD'] = 'GET';
$GLOBALS['saved_names'] = array( 'tabbed_layout' );
mrn_startup_expect( null === mrn_base_stack_get_initial_builder_source_layouts( $source ), 'Existing complex/nested content retains native full discovery.' );
$staged = $source;
$staged['_mrn_initial_layouts'] = $source['layouts'];
$staged['layouts'] = array();
$GLOBALS['field_type'] = false;
mrn_startup_expect( $source === mrn_base_stack_restore_initial_builder_catalog( $staged ), 'Unavailable field types preserve original definitions without a fatal error.' );
$GLOBALS['field_type'] = new MRN_Test_Field_Type();
mrn_startup_expect( true === mrn_base_stack_restore_initial_builder_catalog( $staged )['native_loaded'], 'Complex layouts retain the registered native field loader.' );
$GLOBALS['saved_names'] = array( 'basic' );
$root = array( 'type' => 'flexible_content', 'key' => 'field_mrn_page_content_rows', '_name' => 'page_content_rows', 'prefix' => 'acf', 'value' => array( 'saved' ), 'wrapper' => array( 'class' => 'existing' ) );
$prepared = mrn_base_stack_prepare_initial_builder_rows( $root );
mrn_startup_expect( $prepared['value'] === $root['value'], 'Saved values must remain native.' );
mrn_startup_expect( 'existing mrn-acf-start-collapsed' === $prepared['wrapper']['class'], 'Top-level rows start closed without removing stable classes.' );
$nested = $root;
$nested['prefix'] .= '[field_mrn_page_content_rows][row-0]';
mrn_startup_expect( $nested === mrn_base_stack_prepare_initial_builder_rows( $nested ), 'Nested saved rows do not inherit the initial collapse.' );
$GLOBALS['collapse'] = false;
mrn_startup_expect( $root === mrn_base_stack_prepare_initial_builder_rows( $root ), 'Existing collapse opt-outs remain respected.' );
$GLOBALS['collapse'] = true;
$text = array( 'type' => 'wysiwyg', 'key' => 'field_mrn_block_text', 'value' => '<p>Saved text.</p>', 'delay' => 0 );
$delayed = mrn_base_stack_defer_builder_rich_text( $text );
mrn_startup_expect( 1 === $delayed['delay'] && $text['value'] === $delayed['value'], 'Defer the widget without losing the native textarea value.' );
$foreign = $text;
$foreign['key'] = 'field_client_text';
mrn_startup_expect( $foreign === mrn_base_stack_defer_builder_rich_text( $foreign ), 'Other field owners retain native initialization.' );
$GLOBALS['admin'] = false;
mrn_startup_expect( $root === mrn_base_stack_prepare_initial_builder_rows( $root ) && $text === mrn_base_stack_defer_builder_rich_text( $text ), 'Public/CLI reads remain unchanged.' );
$GLOBALS['mrn_builder_layout_request'] = true;
mrn_startup_expect( 1 === mrn_base_stack_defer_builder_rich_text( $text )['delay'], 'Authorized Add Row templates use native delayed widgets.' );
echo "PASS: ownership, saved values, nested rows, opt-outs, public reads and requested templates.\n";
