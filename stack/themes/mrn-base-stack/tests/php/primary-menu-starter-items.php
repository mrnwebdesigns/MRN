<?php
// phpcs:ignoreFile -- Standalone WordPress stub harness for primary menu starter items.
/**
 * Regression coverage for primary menu starter anchors.
 *
 * @package mrn-base-stack
 */

define( 'ABSPATH', __DIR__ );

$GLOBALS['mrn_primary_menu_test_state'] = array(
	'menu_items' => array(),
	'meta'       => array(),
);

function add_action() {}
function add_filter() {}
function apply_filters( $hook, $value, ...$args ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
	return $value;
}
function __( $text, $domain = 'default' ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
	return $text;
}
function home_url( $path = '/' ) {
	$path = (string) $path;
	if ( '' === $path ) {
		return 'https://example.test/';
	}

	return 0 === strpos( $path, '/' ) ? 'https://example.test' . $path : 'https://example.test/' . $path;
}
function esc_url_raw( $url ) {
	return (string) $url;
}
function absint( $value ) {
	return abs( (int) $value );
}
function sanitize_text_field( $value ) {
	return trim( (string) $value );
}
function wp_get_nav_menu_object( $menu_id ) {
	return (object) array( 'term_id' => (int) $menu_id );
}
function get_term_meta( $menu_id, $meta_key, $single = false ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
	return '';
}
function update_term_meta( $menu_id, $meta_key, $value ) {
	$GLOBALS['mrn_primary_menu_test_state']['meta'][] = array(
		'menu_id'  => (int) $menu_id,
		'meta_key' => (string) $meta_key,
		'value'    => (string) $value,
	);
}
function wp_update_nav_menu_item( $menu_id, $menu_item_db_id, $menu_item_data ) {
	unset( $menu_item_db_id );
	$GLOBALS['mrn_primary_menu_test_state']['menu_items'][] = array(
		'menu_id' => (int) $menu_id,
		'data'    => $menu_item_data,
	);

	return count( $GLOBALS['mrn_primary_menu_test_state']['menu_items'] );
}
function is_wp_error( $thing ) {
	return false;
}

require dirname( __DIR__, 2 ) . '/inc/primary-menu.php';

$items = mrn_base_stack_get_primary_menu_starter_items();
if ( 5 !== count( $items ) ) {
	throw new RuntimeException( 'Primary menu starter items were not generated.' );
}

$expected = array(
	'Home'     => 'https://example.test/',
	'About'    => 'https://example.test/#about',
	'Services' => 'https://example.test/#services',
	'FAQ'      => 'https://example.test/#faq',
	'Contact'  => 'https://example.test/#contact',
);

foreach ( $items as $item ) {
	if ( empty( $item['title'] ) || empty( $item['url'] ) ) {
		throw new RuntimeException( 'Primary menu starter item is missing a title or URL.' );
	}

	if ( ! isset( $expected[ $item['title'] ] ) ) {
		throw new RuntimeException( 'Unexpected primary menu starter item: ' . $item['title'] );
	}

	if ( $expected[ $item['title'] ] !== $item['url'] ) {
		throw new RuntimeException( 'Primary menu starter item URL mismatch for ' . $item['title'] );
	}
}

$result = mrn_base_stack_seed_primary_menu_items( 123 );
if ( 'seeded' !== $result ) {
	throw new RuntimeException( 'Primary menu starter items were not seeded.' );
}

if ( 5 !== count( $GLOBALS['mrn_primary_menu_test_state']['menu_items'] ) ) {
	throw new RuntimeException( 'Primary menu starter items did not seed the expected number of menu items.' );
}

if ( 1 !== count( $GLOBALS['mrn_primary_menu_test_state']['meta'] ) ) {
	throw new RuntimeException( 'Primary menu starter seeding did not record completion metadata.' );
}

echo "Primary menu starter item tests passed.\n";
