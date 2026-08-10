<?php
/**
 * Plugin Name: MRN Hierarchical Menu Taxonomies
 * Description: Shows complete parent/child product-category trees in the WordPress Menu Builder.
 * Version: 0.1.0
 * Author: MRN Web Designs
 * Text Domain: mrn-hierarchical-menu-taxonomies
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class MRN_Hierarchical_Menu_Taxonomies {
	/**
	 * Plugin version.
	 */
	const VERSION = '0.1.0';

	/**
	 * Register the admin-only integration.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'get_terms_args', array( __CLASS__, 'expand_menu_taxonomy_query' ), 20, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_menu_builder_styles' ) );
	}

	/**
	 * Return taxonomies that should use a complete hierarchy in Appearance > Menus.
	 *
	 * @return string[]
	 */
	private static function get_supported_taxonomies() {
		$taxonomies = apply_filters(
			'mrn_hierarchical_menu_taxonomies',
			array( 'product_cat' )
		);

		if ( ! is_array( $taxonomies ) ) {
			return array();
		}

		return array_values( array_filter( array_map( 'sanitize_key', $taxonomies ) ) );
	}

	/**
	 * Determine whether the current request is the classic WordPress Menu Builder.
	 *
	 * @return bool
	 */
	private static function is_menu_builder_request() {
		global $pagenow;

		return is_admin() && 'nav-menus.php' === $pagenow;
	}

	/**
	 * Expand the core View All taxonomy query before WordPress builds its checklist.
	 *
	 * WordPress limits this query to 50 globally alphabetized terms. That can put
	 * a child on a different result page from its parent, which makes the child
	 * look like a top-level item. Returning the full result lets the existing
	 * Walker_Nav_Menu_Checklist render the saved taxonomy hierarchy accurately.
	 *
	 * @param array    $args       WP_Term_Query arguments.
	 * @param string[] $taxonomies Queried taxonomies.
	 * @return array
	 */
	public static function expand_menu_taxonomy_query( $args, $taxonomies ) {
		if ( ! self::is_menu_builder_request() || ! is_array( $args ) ) {
			return $args;
		}

		$taxonomies = (array) $taxonomies;
		$supported  = array_intersect( self::get_supported_taxonomies(), $taxonomies );

		if ( empty( $supported ) ) {
			return $args;
		}

		$is_core_view_all_query = ! empty( $args['hierarchical'] )
			&& isset( $args['number'] )
			&& 50 === (int) $args['number']
			&& empty( $args['search'] )
			&& empty( $args['name__like'] )
			&& ( ! isset( $args['fields'] ) || 'all' === $args['fields'] );

		if ( ! $is_core_view_all_query ) {
			return $args;
		}

		$args['number'] = 0;
		$args['offset'] = 0;

		return $args;
	}

	/**
	 * Hide pagination that no longer applies to expanded taxonomy panels.
	 *
	 * @param string $hook_suffix Current admin hook suffix.
	 * @return void
	 */
	public static function enqueue_menu_builder_styles( $hook_suffix ) {
		if ( 'nav-menus.php' !== $hook_suffix ) {
			return;
		}

		$selectors = array();

		foreach ( self::get_supported_taxonomies() as $taxonomy ) {
			$selectors[] = '#taxonomy-' . sanitize_html_class( $taxonomy ) . ' .add-menu-item-pagelinks';
		}

		if ( empty( $selectors ) ) {
			return;
		}

		wp_add_inline_style(
			'nav-menus',
			implode( ",\n", $selectors ) . " {\n\tdisplay: none;\n}"
		);
	}
}

MRN_Hierarchical_Menu_Taxonomies::init();
