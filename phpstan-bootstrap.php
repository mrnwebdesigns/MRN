<?php
/**
 * Project-local PHPStan bootstrap for WordPress-specific constants and common plugin APIs.
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

if ( ! defined( 'WPINC' ) ) {
	define( 'WPINC', 'wp-includes' );
}

if ( ! defined( 'WPMU_PLUGIN_URL' ) ) {
	define( 'WPMU_PLUGIN_URL', '' );
}

if ( ! function_exists( 'get_field' ) ) {
	/**
	 * @param mixed $selector
	 * @param mixed $post_id
	 * @return mixed
	 */
	function get_field( $selector, $post_id = false, bool $format_value = true, bool $escape_html = false ) {
		return null;
	}
}

if ( ! function_exists( 'get_sub_field' ) ) {
	/**
	 * @param mixed $selector
	 * @return mixed
	 */
	function get_sub_field( $selector, bool $format_value = true, bool $escape_html = false ) {
		return null;
	}
}

if ( ! function_exists( 'have_rows' ) ) {
	/**
	 * @param mixed $selector
	 * @param mixed $post_id
	 */
	function have_rows( $selector, $post_id = false ): bool {
		return false;
	}
}

if ( ! function_exists( 'the_row' ) ) {
	function the_row(): void {}
}

if ( ! function_exists( 'get_row_layout' ) ) {
	function get_row_layout(): ?string {
		return null;
	}
}

if ( ! function_exists( 'mrn_rbl_get_content_link_repeater_field' ) ) {
	/**
	 * @return array<string, mixed>
	 */
	function mrn_rbl_get_content_link_repeater_field( string $key, string $label = 'Links', string $name = 'links', int $max = 0, ?string $instructions = null ): array {
		unset( $key, $label, $name, $max, $instructions );

		return array();
	}
}
