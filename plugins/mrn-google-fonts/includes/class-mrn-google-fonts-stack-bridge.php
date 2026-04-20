<?php
/**
 * MRN Google Fonts stack bridge helpers.
 */

if (!defined('ABSPATH')) {
	exit;
}

final class MRN_Google_Fonts_Stack_Bridge {
	/**
	 * Detect whether MRN stack contracts appear available.
	 */
	public static function is_stack_available(): bool {
		return function_exists('mrn_site_colors_get_all')
			|| function_exists('mrn_site_styles_get_graphic_elements')
			|| function_exists('mrn_base_stack_get_singular_shell_post_types');
	}

	/**
	 * Detect whether Site Styles exposes extension hooks for third-party tabs.
	 */
	public static function supports_site_styles_tab_extension(): bool {
		if (!function_exists('mrn_site_styles_get_admin_tabs')) {
			return false;
		}

		return has_filter('mrn_site_styles_tabs') || has_action('mrn_site_styles_register_tab');
	}

	/**
	 * Resolve runtime mode from settings and environment.
	 *
	 * @param string $bridge_mode Selected bridge mode.
	 */
	public static function get_runtime_mode(string $bridge_mode): string {
		$allowed_modes = array('auto', 'standalone', 'force_stack');
		$bridge_mode = sanitize_key($bridge_mode);

		if (!in_array($bridge_mode, $allowed_modes, true)) {
			$bridge_mode = 'auto';
		}

		if ('standalone' === $bridge_mode) {
			return 'standalone';
		}

		if ('force_stack' === $bridge_mode) {
			return self::is_stack_available() ? 'stack' : 'standalone';
		}

		return self::is_stack_available() ? 'stack' : 'standalone';
	}

	/**
	 * Build a status payload for settings-page display.
	 *
	 * @param string $bridge_mode Selected bridge mode.
	 * @return array<string, mixed>
	 */
	public static function get_status(string $bridge_mode): array {
		$runtime_mode = self::get_runtime_mode($bridge_mode);

		return array(
			'stack_available' => self::is_stack_available(),
			'site_styles_tab_extension_available' => self::supports_site_styles_tab_extension(),
			'runtime_mode' => $runtime_mode,
			'summary' => ('stack' === $runtime_mode)
				? 'Stack mode active: runtime can follow stack contracts when integrations are available.'
				: 'Standalone mode active: plugin runs independently without stack contracts.',
		);
	}
}
