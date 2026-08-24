<?php
/**
 * Primary menu starter content helpers.
 *
 * @package mrn-base-stack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Return the starter items for the primary menu.
 *
 * These entries are intended to line up with the common one-page builder
 * anchors used throughout the stack: `about`, `services`, `faq`, and
 * `contact`.
 *
 * @return array<int, array<string, string>>
 */
function mrn_base_stack_get_primary_menu_starter_items() {
	$starter_items = array(
		array(
			'title' => __( 'Home', 'mrn-base-stack' ),
			'url'   => home_url( '/' ),
		),
		array(
			'title' => __( 'About', 'mrn-base-stack' ),
			'url'   => home_url( '/#about' ),
		),
		array(
			'title' => __( 'Services', 'mrn-base-stack' ),
			'url'   => home_url( '/#services' ),
		),
		array(
			'title' => __( 'FAQ', 'mrn-base-stack' ),
			'url'   => home_url( '/#faq' ),
		),
		array(
			'title' => __( 'Contact', 'mrn-base-stack' ),
			'url'   => home_url( '/#contact' ),
		),
	);

	/**
	 * Filter the starter items assigned to an empty primary menu.
	 *
	 * @param array<int, array<string, string>> $starter_items Starter menu items.
	 */
	$starter_items = apply_filters( 'mrn_base_stack_primary_menu_starter_items', $starter_items );

	return is_array( $starter_items ) ? array_values( $starter_items ) : array();
}

/**
 * Seed a starter primary menu structure when the primary menu is empty.
 *
 * @param int|string $menu_id Nav menu term ID.
 * @return string 'seeded', 'already_seeded', or 'failed'.
 */
function mrn_base_stack_seed_primary_menu_items( $menu_id ) {
	$menu_id = absint( $menu_id );

	if ( $menu_id <= 0 || ! wp_get_nav_menu_object( $menu_id ) ) {
		return 'failed';
	}

	$meta_key = '_mrn_base_stack_primary_menu_seeded';
	$seeded   = get_term_meta( $menu_id, $meta_key, true );

	if ( '' !== (string) $seeded ) {
		return 'already_seeded';
	}

	if ( function_exists( 'mrn_base_stack_nav_menu_has_items' ) && mrn_base_stack_nav_menu_has_items( $menu_id ) ) {
		update_term_meta( $menu_id, $meta_key, '1' );
		return 'already_seeded';
	}

	$starter_items = mrn_base_stack_get_primary_menu_starter_items();
	if ( empty( $starter_items ) ) {
		return 'failed';
	}

	foreach ( $starter_items as $position => $starter_item ) {
		if ( ! is_array( $starter_item ) ) {
			continue;
		}

		$title = isset( $starter_item['title'] ) ? sanitize_text_field( (string) $starter_item['title'] ) : '';
		$url   = isset( $starter_item['url'] ) ? esc_url_raw( (string) $starter_item['url'] ) : '';

		if ( '' === $title || '' === $url ) {
			continue;
		}

		$menu_item_id = wp_update_nav_menu_item(
			$menu_id,
			0,
			array(
				'menu-item-title'     => $title,
				'menu-item-url'       => $url,
				'menu-item-status'    => 'publish',
				'menu-item-type'      => 'custom',
				'menu-item-object'    => 'custom',
				'menu-item-object-id' => 0,
				'menu-item-parent-id' => 0,
				'menu-item-position'  => (int) $position + 1,
			)
		);

		if ( is_wp_error( $menu_item_id ) ) {
			return 'failed';
		}
	}

	update_term_meta( $menu_id, $meta_key, '1' );

	return 'seeded';
}
