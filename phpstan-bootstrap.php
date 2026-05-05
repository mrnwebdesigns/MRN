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

if ( ! defined( 'MRN_GOOGLE_FONTS_URL' ) ) {
	define( 'MRN_GOOGLE_FONTS_URL', '' );
}

if ( ! class_exists( 'MRN_Google_Fonts_Stack_Bridge' ) ) {
	final class MRN_Google_Fonts_Stack_Bridge {
		public static function supports_site_styles_tab_extension(): bool {
			return false;
		}

		public static function get_runtime_mode( string $bridge_mode ): string {
			return 'standalone';
		}

		/**
		 * @return array<string, mixed>
		 */
		public static function get_status( string $bridge_mode ): array {
			unset( $bridge_mode );

			return array(
				'stack_available' => false,
				'site_styles_tab_extension_available' => false,
				'runtime_mode' => 'standalone',
				'summary' => '',
			);
		}
	}
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

if ( ! function_exists( 'mrn_rbl_get_content_link_fields' ) ) {
	/**
	 * @return array<int, array<string, mixed>>
	 */
	function mrn_rbl_get_content_link_fields( string $key, string $label = 'Links', string $name = 'links', int $max = 0, ?string $instructions = null ): array {
		unset( $key, $label, $name, $max, $instructions );

		return array();
	}
}
