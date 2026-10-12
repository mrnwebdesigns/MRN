<?php
// phpcs:ignoreFile -- Standalone hook harness; real rendering/save is covered by the local fixture.
/**
 * Guard template suppression boundaries, especially nested/repeater clones.
 *
 * Run: php stack/themes/mrn-base-stack/tests/php/lazy-layouts.php
 *
 * @package mrn-base-stack
 */

function add_action( ...$args ) {}
function add_filter( ...$args ) {}
function wp_doing_ajax() { return false; }
function wp_script_is( ...$args ) { return $GLOBALS['mrn_lazy_enqueued']; }

require dirname( __DIR__, 2 ) . '/inc/builder/lazy-layouts.php';

function mrn_lazy_expect( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

$GLOBALS['mrn_lazy_enqueued'] = true;
$root = array(
	'type' => 'flexible_content',
	'key' => 'field_mrn_page_content_rows',
	'name' => 'acf[field_mrn_page_content_rows]',
	'layouts' => array( array( 'name' => 'basic', 'sub_fields' => array( array( 'key' => 'field_child', 'required' => 1 ) ) ) ),
	'value' => array( array( 'acf_fc_layout' => 'basic', 'field_child' => 'saved value' ) ),
	'wrapper' => array( 'class' => 'existing-style' ),
);
$unused = array( 'name' => 'unused', 'label' => 'Unused', 'min' => 0, 'max' => 5, 'sub_fields' => array( array( 'key' => 'field_unused', 'required' => 1 ) ) );
$root['layouts'][] = $unused;
$catalog = mrn_base_stack_prepare_builder_picker_catalog( $root );
mrn_lazy_expect( $catalog['layouts'][0] === $root['layouts'][0] && $catalog['value'] === $root['value'], 'Saved row definitions and values must remain complete.' );
mrn_lazy_expect( array() === $catalog['layouts'][1]['sub_fields'] && 'Unused' === $catalog['layouts'][1]['label'] && 5 === $catalog['layouts'][1]['max'], 'Unused forms retain picker metadata and limits without their control trees.' );
$prepared = mrn_base_stack_prepare_lazy_builder_layout( $root );
mrn_lazy_expect( $prepared['layouts'] === $root['layouts'] && $prepared['value'] === $root['value'], 'Catalog definitions and saved values must remain unchanged.' );
mrn_lazy_expect( false !== strpos( $prepared['wrapper']['class'], 'existing-style' ), 'Stable wrapper classes must survive.' );

$child = array( 'type' => 'text', 'key' => 'field_child', 'name' => 'child', 'prefix' => $root['name'] . '[acfcloneindex]', 'required' => 1, 'default_value' => 'default' );
mrn_lazy_expect( false === mrn_base_stack_prepare_lazy_builder_layout( $child ), 'Unused flexible templates should defer fields.' );
$child['prefix'] = $root['name'] . '[row-0]';
mrn_lazy_expect( $child === mrn_base_stack_prepare_lazy_builder_layout( $child ), 'Saved row fields must render immediately.' );
$child['prefix'] .= '[field_repeater][acfcloneindex]';
mrn_lazy_expect( $child === mrn_base_stack_prepare_lazy_builder_layout( $child ), 'Repeater templates in saved rows must keep their controls/defaults.' );

$unrelated = array( 'type' => 'flexible_content', 'key' => 'field_client', 'name' => 'acf[field_client]' );
mrn_lazy_expect( $unrelated === mrn_base_stack_prepare_lazy_builder_layout( $unrelated ), 'Other flexible-content owners must retain native behavior.' );

$GLOBALS['mrn_builder_requested_template_prefix'] = $root['name'] . '[acfcloneindex]';
$child['prefix'] = $GLOBALS['mrn_builder_requested_template_prefix'];
mrn_lazy_expect( $child === mrn_base_stack_prepare_lazy_builder_layout( $child ), 'The requested empty layout must receive its real controls/defaults.' );
$nested = $root;
$nested['key'] = 'after_content_field_mrn_card_item_rows';
$nested['name'] = $child['prefix'] . '[' . $nested['key'] . ']';
mrn_base_stack_prepare_lazy_builder_layout( $nested );
$child['prefix'] = $nested['name'] . '[acfcloneindex]';
mrn_lazy_expect( false === mrn_base_stack_prepare_lazy_builder_layout( $child ), 'Nested unused flexible templates remain deferred inside a requested layout.' );

$GLOBALS['mrn_lazy_enqueued'] = false;
mrn_lazy_expect( $root === mrn_base_stack_prepare_lazy_builder_layout( $root ), 'Frontend, REST, CLI, and unsupported admin screens must retain native behavior.' );
echo "PASS: saved rows, repeater templates, unrelated fields, nested requests, defaults, and non-editor contexts.\n";
