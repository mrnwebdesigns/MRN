<?php
/**
 * Focused regression coverage for plugin path and nav-menu item helpers.
 */

define( 'ABSPATH', __DIR__ );

if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) {
		return abs( (int) $value );
	}
}

if ( ! function_exists( 'plugins_url' ) ) {
	function plugins_url( $path, $plugin_file ) {
		unset( $plugin_file );

		return 'https://example.test/wp-content/plugins/mrn-mega-menu/' . ltrim( (string) $path, '/' );
	}
}

if ( ! function_exists( 'trailingslashit' ) ) {
	function trailingslashit( $value ) {
		return rtrim( (string) $value, '/\\' ) . '/';
	}
}

require_once dirname( __DIR__, 2 ) . '/includes/class-plugin.php';

use MRN_Mega_Menu\Plugin;

if ( '0.16.16' !== Plugin::VERSION ) {
	throw new RuntimeException( 'Plugin runtime version is not synchronized.' );
}

$menu_item = (object) array( 'menu_item_parent' => '42' );
if ( 42 !== Plugin::get_menu_item_parent_id( $menu_item ) ) {
	throw new RuntimeException( 'Dynamic nav-menu parent properties are not normalized.' );
}

if ( 0 !== Plugin::get_menu_item_parent_id( new stdClass() ) || 0 !== Plugin::get_menu_item_parent_id( null ) ) {
	throw new RuntimeException( 'Missing nav-menu parent properties must resolve to zero.' );
}

$asset_url = Plugin::asset_url( '/assets/js/mega-menu.js' );
if ( 'https://example.test/wp-content/plugins/mrn-mega-menu/assets/js/mega-menu.js' !== $asset_url ) {
	throw new RuntimeException( 'Plugin asset URLs are not normalized.' );
}

$expected_path = dirname( __DIR__, 2 ) . '/includes/class-renderer.php';
if ( $expected_path !== Plugin::path( '/includes/class-renderer.php' ) ) {
	throw new RuntimeException( 'Plugin filesystem paths are not normalized.' );
}

echo "Mega Menu runtime contract tests passed.\n";
