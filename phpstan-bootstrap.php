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

// ACF runtime APIs used by the Stack repeater write policy.
if ( ! function_exists( 'acf_get_metadata_by_field' ) ) {
	/**
	 * @param int|string           $post_id
	 * @param array<string, mixed> $field
	 * @return mixed
	 */
	function acf_get_metadata_by_field( $post_id, array $field, bool $hidden = false ) {
		return null;
	}
}

if ( ! function_exists( 'acf_get_form_data' ) ) {
	/** @return mixed */
	function acf_get_form_data( string $name = '' ) {
		return null;
	}
}

if ( ! function_exists( 'acf_request_arg' ) ) {
	/**
	 * @param mixed $default
	 * @return mixed
	 */
	function acf_request_arg( string $name, $default = false ) {
		return $default;
	}
}

if ( ! function_exists( 'acf_get_valid_post_id' ) ) {
	/**
	 * @param mixed $post_id
	 * @return int|string
	 */
	function acf_get_valid_post_id( $post_id = 0, bool $allow_revision = true ) {
		return 0;
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

if ( ! function_exists( 'mrn_site_colors_get_all' ) ) {
	/**
	 * @return array<int, array<string, mixed>>
	 */
	function mrn_site_colors_get_all(): array {
		return array();
	}
}

if ( ! function_exists( 'mrn_site_colors_normalize_slug' ) ) {
	function mrn_site_colors_normalize_slug( string $value ): string {
		return $value;
	}
}

if ( ! function_exists( 'mrn_site_colors_get_css_var' ) ) {
	function mrn_site_colors_get_css_var( string $slug ): string {
		return '--mrn-site-color-' . $slug;
	}
}

// Runtime-defined plugin paths and wp-config values for static analysis only.
foreach ( array(
	'DB_NAME' => 'static-analysis',
	'MRN_LAYOUT_IMPORT_EXPORT_DIR' => __DIR__ . '/plugins/mrn-layout-import-export/',
	'MRN_LAYOUT_IMPORT_EXPORT_URL' => '',
	'MRN_TOKENS_DIR' => __DIR__ . '/plugins/mrn-tokens/',
) as $mrn_analysis_constant => $mrn_analysis_value ) {
	if ( ! defined( $mrn_analysis_constant ) ) {
		define( $mrn_analysis_constant, $mrn_analysis_value );
	}
}
