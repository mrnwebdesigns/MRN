<?php
// phpcs:ignoreFile -- Standalone read-only discovery harness.
define( 'ABSPATH', __DIR__ );
class WP_Screen {
	public $base = 'post';
	public $post_type = 'page';
	public $id = 'page';
}
class WP_Post { public $ID = 41; }
function is_admin() { return true; }
function get_current_screen() { return new WP_Screen(); }
function add_filter( ...$args ) {}
function remove_filter( ...$args ) {}
function acf_get_field_groups( $context ) { return array( array( 'key' => 'group_local' ), array( 'key' => 'group_dynamic' ) ); }
function acf_get_local_fields( $parent ) {
	return array(
		'group_local' => array( array( 'key' => 'field_root', 'type' => 'flexible_content' ) ),
		'field_root' => array( array( 'key' => 'field_nested', 'type' => 'repeater' ) ),
		'field_nested' => array( array( 'key' => 'field_root', 'type' => 'text' ) ),
	)[ $parent ] ?? array();
}
function acf_get_fields( $group ) {
	$GLOBALS['loads'][] = $group['key'];
	return 'group_dynamic' === $group['key'] ? array( array( 'key' => 'expanded_clone', 'type' => 'image' ) ) : array();
}
require dirname( __DIR__, 2 ) . '/mu-plugins/mrn-shared-assets/includes/acf-admin-assets.php';
$GLOBALS['post'] = new WP_Post();
$GLOBALS['loads'] = array();
foreach ( array( 'flexible_content', 'repeater' ) as $type ) {
	assert( mrn_shared_assets_acf_editor_has_field( static fn( $field ) => $field['type'] === $type ) );
}
if ( $GLOBALS['loads'] ) { throw new RuntimeException( 'Local matches must not expand editor catalogs.' ); }
if ( ! mrn_shared_assets_acf_editor_has_field( static fn( $field ) => 'expanded_clone' === $field['key'] ) ) {
	throw new RuntimeException( 'Dynamic/clone fields still require native discovery.' );
}
if ( mrn_shared_assets_acf_editor_has_field( static fn( $field ) => 'missing' === $field['key'] ) || count( $GLOBALS['loads'] ) !== 2 ) {
	throw new RuntimeException( 'Missing fields stay absent and hydrated groups are cached.' );
}
echo "PASS: shallow local matches, nested/cyclic records, dynamic clone fallback and cached negative discovery.\n";
