<?php
/**
 * MRN Google Fonts plugin bootstrap and settings.
 */

if (!defined('ABSPATH')) {
	exit;
}

final class MRN_Google_Fonts {
	const VERSION = '0.5.3';
	const OPTION_KEY = 'mrn_google_fonts_settings';
	const LOCAL_OPTION_KEY = 'mrn_google_fonts_local_manifest';
	const PAGE_SLUG = 'google-fonts';
	const SITE_STYLES_TAB_KEY = 'google-fonts';
	const SITE_STYLES_TRANSFER_SECTION_KEY = 'google_fonts';
	const BUILD_LOCAL_ACTION = 'mrn_google_fonts_build_local_assets';
	const CLEAR_LOCAL_ACTION = 'mrn_google_fonts_clear_local_assets';
	const FONT_CATALOG_TRANSIENT = 'mrn_google_fonts_catalog_v2';
	const FONT_CATALOG_FALLBACK_TTL = 15 * MINUTE_IN_SECONDS;
	const FONT_CATALOG_URL = 'https://fonts.google.com/metadata/fonts';
	const GOOGLE_FONTS_CSS_USER_AGENT = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_4_0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';

	/**
	 * Register plugin hooks.
	 */
	public static function init(): void {
		add_action('admin_menu', array(__CLASS__, 'register_settings_page'));
		add_action('admin_init', array(__CLASS__, 'register_settings'));
		add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_admin_assets'));
		add_action('wp_ajax_mrn_google_fonts_search_families', array(__CLASS__, 'ajax_search_families'));
		add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_frontend_assets'), 20);
		add_filter('wp_resource_hints', array(__CLASS__, 'filter_resource_hints'), 10, 2);
		add_filter('mce_css', array(__CLASS__, 'append_editor_css'));
		// Run late so our configured Google font list survives downstream TinyMCE init filters.
		add_filter('tiny_mce_before_init', array(__CLASS__, 'inject_tinymce_content_style'), 1000);
		add_filter('mrn_site_styles_tabs', array(__CLASS__, 'filter_site_styles_tabs'));
		add_filter('mrn_site_styles_transfer_sections', array(__CLASS__, 'filter_site_styles_transfer_sections'));
		add_filter('mrn_site_styles_export_data', array(__CLASS__, 'filter_site_styles_export_data'), 10, 2);
		add_filter('mrn_site_styles_import_data', array(__CLASS__, 'filter_site_styles_import_data'), 10, 2);
		add_action('mrn_site_styles_render_tab_panel', array(__CLASS__, 'render_site_styles_tab_panel'), 10, 2);
		add_action('mrn_site_styles_handle_save', array(__CLASS__, 'handle_site_styles_save'));
		add_action('mrn_site_styles_render_notices', array(__CLASS__, 'render_site_styles_notice'));
		add_action('admin_post_' . self::BUILD_LOCAL_ACTION, array(__CLASS__, 'handle_build_local_assets'));
		add_action('admin_post_' . self::CLEAR_LOCAL_ACTION, array(__CLASS__, 'handle_clear_local_assets'));
	}

	/**
	 * Register settings option.
	 */
	public static function register_settings(): void {
		register_setting(
			'mrn_google_fonts',
			self::OPTION_KEY,
			array(
				'type' => 'array',
				'sanitize_callback' => array(__CLASS__, 'sanitize_settings'),
				'default' => self::default_settings(),
			)
		);
	}

	/**
	 * Return default settings payload.
	 *
	 * @return array<string, mixed>
	 */
	public static function default_settings(): array {
		return array(
			'enabled' => 0,
			'load_on_frontend' => 1,
			'frontend_load_scope' => 'all',
			'load_in_classic_editor' => 1,
			'body_font_family' => 'system-ui',
			'heading_font_family' => 'system-ui',
			'accent_font_family' => 'system-ui',
			'body_font_weights' => '400',
			'heading_font_weights' => '600,700',
			'accent_font_weights' => '400',
			'body_font_italics' => 0,
			'heading_font_italics' => 0,
			'accent_font_italics' => 0,
			'font_faces' => array(),
			'body_font_targets' => array('body_text', 'form_controls', 'buttons'),
			'heading_font_targets' => array('headings'),
			'accent_font_targets' => array(),
			'subset' => 'latin',
			'font_display' => 'swap',
			'stack_bridge_mode' => 'auto',
			'designer_notes' => '',
		);
	}

	/**
	 * Get merged runtime settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_settings(): array {
		$defaults = self::default_settings();
		$saved = get_option(self::OPTION_KEY, array());

		if (!is_array($saved)) {
			return $defaults;
		}

		return array_replace($defaults, $saved);
	}

	/**
	 * Whether Site Styles owns tag/selector typography assignments.
	 *
	 * @param array<string, mixed> $settings Plugin settings.
	 */
	private static function site_styles_owns_typography(array $settings): bool {
		if (!function_exists('mrn_site_styles_get_typography')) {
			return false;
		}

		return 'stack' === MRN_Google_Fonts_Stack_Bridge::get_runtime_mode(
			(string) ($settings['stack_bridge_mode'] ?? 'auto')
		);
	}

	/**
	 * Sanitize settings before storage.
	 *
	 * @param mixed $input Raw input.
	 * @return array<string, mixed>
	 */
	public static function sanitize_settings($input): array {
		$defaults = self::default_settings();
		$input = is_array($input) ? $input : array();

		$sanitized = $defaults;
		$sanitized['enabled'] = !empty($input['enabled']) ? 1 : 0;
		$sanitized['load_on_frontend'] = !empty($input['load_on_frontend']) ? 1 : 0;
		$sanitized['load_in_classic_editor'] = !empty($input['load_in_classic_editor']) ? 1 : 0;
		$sanitized['frontend_load_scope'] = self::sanitize_frontend_load_scope_value($input['frontend_load_scope'] ?? $defaults['frontend_load_scope']);

		$sanitized['body_font_family'] = self::sanitize_font_family_value($input['body_font_family'] ?? $defaults['body_font_family']);
		$sanitized['heading_font_family'] = self::sanitize_font_family_value($input['heading_font_family'] ?? $defaults['heading_font_family']);
		$sanitized['accent_font_family'] = self::sanitize_font_family_value($input['accent_font_family'] ?? $defaults['accent_font_family']);
		$sanitized['body_font_weights'] = self::sanitize_font_weights_value($input['body_font_weights'] ?? $defaults['body_font_weights']);
		$sanitized['heading_font_weights'] = self::sanitize_font_weights_value($input['heading_font_weights'] ?? $defaults['heading_font_weights']);
		$sanitized['accent_font_weights'] = self::sanitize_font_weights_value($input['accent_font_weights'] ?? $defaults['accent_font_weights']);
		$sanitized['body_font_italics'] = !empty($input['body_font_italics']) ? 1 : 0;
		$sanitized['heading_font_italics'] = !empty($input['heading_font_italics']) ? 1 : 0;
		$sanitized['accent_font_italics'] = !empty($input['accent_font_italics']) ? 1 : 0;
		$sanitized['font_faces'] = self::sanitize_google_font_faces_config($input['font_faces'] ?? $defaults['font_faces'], false);
		$existing = get_option(self::OPTION_KEY, array());
		$existing = is_array($existing) ? $existing : array();
		$ownership_settings = array_replace($defaults, $existing, $input);
		foreach (array('body_font_targets', 'heading_font_targets', 'accent_font_targets') as $targets_key) {
			$fallback_targets = self::site_styles_owns_typography($ownership_settings)
				? ($existing[$targets_key] ?? $defaults[$targets_key])
				: $defaults[$targets_key];
			$sanitized[$targets_key] = self::sanitize_font_targets_value($input[$targets_key] ?? $fallback_targets);
		}

		$allowed_subsets = array('latin', 'latin-ext');
		$subset = sanitize_key((string) ($input['subset'] ?? $defaults['subset']));
		$sanitized['subset'] = in_array($subset, $allowed_subsets, true) ? $subset : $defaults['subset'];

		$allowed_displays = array('swap', 'optional');
		$font_display = sanitize_key((string) ($input['font_display'] ?? $defaults['font_display']));
		$sanitized['font_display'] = in_array($font_display, $allowed_displays, true) ? $font_display : $defaults['font_display'];

		$allowed_bridge_modes = array('auto', 'standalone', 'force_stack');
		$bridge_mode = sanitize_key((string) ($input['stack_bridge_mode'] ?? $defaults['stack_bridge_mode']));
		$sanitized['stack_bridge_mode'] = in_array($bridge_mode, $allowed_bridge_modes, true) ? $bridge_mode : $defaults['stack_bridge_mode'];

		$sanitized['designer_notes'] = sanitize_textarea_field((string) ($input['designer_notes'] ?? ''));

		return $sanitized;
	}

	/**
	 * Register settings page.
	 */
	public static function register_settings_page(): void {
		// When Site Styles is available, this plugin is managed from that tabbed surface.
		if (MRN_Google_Fonts_Stack_Bridge::supports_site_styles_tab_extension()) {
			return;
		}

		add_options_page(
			'Google Fonts',
			'Google Fonts',
			'manage_options',
			self::PAGE_SLUG,
			array(__CLASS__, 'render_settings_page')
		);
	}

	/**
	 * Enqueue page-local admin styles.
	 *
	 * @param string $hook Current admin hook.
	 */
	public static function enqueue_admin_assets(string $hook): void {
		if ('settings_page_' . self::PAGE_SLUG !== $hook) {
			return;
		}

		wp_register_style('mrn-google-fonts-admin', false, array(), self::VERSION);
		wp_enqueue_style('mrn-google-fonts-admin');
			wp_add_inline_style(
				'mrn-google-fonts-admin',
				'.mrn-google-fonts-tabs{margin-top:16px}.mrn-google-fonts-panel{max-width:980px;padding:16px 20px;background:#fff;border:1px solid #dcdcde;border-top:none}.mrn-google-fonts-field{margin:0 0 14px}.mrn-google-fonts-field label{display:block;margin-bottom:6px;font-weight:600}.mrn-google-fonts-field .description{margin-top:4px;color:#50575e}.mrn-google-fonts-status{margin:0 0 14px;padding:12px;border-left:4px solid #2271b1;background:#f0f6fc}.mrn-google-fonts-slot-grid{display:grid;grid-template-columns:repeat(3,minmax(240px,1fr));gap:14px}.mrn-google-fonts-slot-card{border:1px solid #dcdcde;border-radius:4px;background:#fff;padding:12px}.mrn-google-fonts-slot-title{margin:0 0 8px;font-size:14px;line-height:1.4}.mrn-google-fonts-slot-help{margin:0 0 12px;color:#50575e}.mrn-google-fonts-slot-card .mrn-google-fonts-field{margin:0 0 10px}.mrn-google-fonts-slot-card .mrn-google-fonts-field:last-child{margin-bottom:0}.mrn-google-fonts-slot-card input[type="text"],.mrn-google-fonts-slot-card select{width:100%}.mrn-google-fonts-inline-check{display:flex;align-items:center;gap:8px}.mrn-google-fonts-inline-check label{margin:0;font-weight:400}@media (max-width:1100px){.mrn-google-fonts-slot-grid{grid-template-columns:repeat(2,minmax(240px,1fr))}}@media (max-width:782px){.mrn-google-fonts-slot-grid{grid-template-columns:1fr}}'
			);
		}

	/**
	 * Enqueue frontend stylesheet and Google Fonts request when configured.
	 */
	public static function enqueue_frontend_assets(): void {
		$settings = self::get_settings();
		if (!self::should_load_frontend_runtime($settings)) {
			return;
		}

		$runtime_mode = MRN_Google_Fonts_Stack_Bridge::get_runtime_mode((string) $settings['stack_bridge_mode']);
		$google_request = self::build_google_fonts_request($settings);
		$deps = array();
		$local_css_url = self::get_local_css_url_for_request($settings, $google_request);

		if ('' !== $local_css_url) {
			wp_enqueue_style(
				'mrn-google-fonts-local',
				$local_css_url,
				array(),
				self::VERSION
			);
			$deps[] = 'mrn-google-fonts-local';
		} elseif (!empty($google_request['url']) && is_string($google_request['url'])) {
			wp_enqueue_style(
				'mrn-google-fonts-remote',
				$google_request['url'],
				array(),
				null
			);
			$deps[] = 'mrn-google-fonts-remote';
		}

		wp_enqueue_style(
			'mrn-google-fonts-frontend',
			MRN_GOOGLE_FONTS_URL . 'assets/css/frontend-fonts.css',
			$deps,
			self::VERSION
		);

		$font_face_css = apply_filters('mrn_google_fonts_font_face_css', '', $settings, $runtime_mode);
		if (is_string($font_face_css) && '' !== trim($font_face_css)) {
			wp_add_inline_style('mrn-google-fonts-frontend', $font_face_css);
		}

		$body_stack = self::build_font_stack((string) $settings['body_font_family']);
		$heading_stack = self::build_font_stack((string) $settings['heading_font_family']);
		$accent_stack = self::build_font_stack((string) ($settings['accent_font_family'] ?? 'system-ui'));

		$css = ':root{--mrn-font-body:' . $body_stack . ';--mrn-font-heading:' . $heading_stack . ';--mrn-font-accent:' . $accent_stack . ';}';
		$css .= self::build_font_target_css($settings, 'frontend');
		if ('stack' === $runtime_mode) {
			$css .= '/* Stack bridge active: runtime can be extended via mrn_google_fonts_font_face_css filter. */';
		}

		wp_add_inline_style('mrn-google-fonts-frontend', $css);
	}

	/**
	 * Add resource hints for Google Fonts origins.
	 *
	 * @param array<int|string, mixed> $hints Existing hints.
	 * @param string                   $relation_type Resource hint relation.
	 * @return array<int|string, mixed>
	 */
	public static function filter_resource_hints(array $hints, string $relation_type): array {
		$settings = self::get_settings();
		if (!self::should_load_frontend_runtime($settings)) {
			return $hints;
		}

		$google_request = self::build_google_fonts_request($settings);
		if ('' !== self::get_local_css_url_for_request($settings, $google_request)) {
			return $hints;
		}

		if (empty($google_request['url'])) {
			return $hints;
		}

		if ('preconnect' === $relation_type) {
			if (!self::hints_contain_url($hints, 'https://fonts.googleapis.com')) {
				$hints[] = 'https://fonts.googleapis.com';
			}

			if (!self::hints_contain_url($hints, 'https://fonts.gstatic.com')) {
				$hints[] = array(
					'href' => 'https://fonts.gstatic.com',
					'crossorigin' => 'anonymous',
				);
			}
		}

		if ('dns-prefetch' === $relation_type) {
			if (!self::hints_contain_url($hints, 'https://fonts.googleapis.com')) {
				$hints[] = 'https://fonts.googleapis.com';
			}
			if (!self::hints_contain_url($hints, 'https://fonts.gstatic.com')) {
				$hints[] = 'https://fonts.gstatic.com';
			}
		}

		return $hints;
	}

	/**
	 * Append editor stylesheet URL for TinyMCE iframe content.
	 *
	 * @param string $styles Existing editor CSS list.
	 */
	public static function append_editor_css(string $styles): string {
		$settings = self::get_settings();
		if (empty($settings['enabled']) || empty($settings['load_in_classic_editor'])) {
			return $styles;
		}

		$google_request = self::build_google_fonts_request($settings);
		$local_css_url = self::get_local_css_url_for_request($settings, $google_request);
		if ('' !== $local_css_url) {
			if ('' !== $styles) {
				$styles .= ',';
			}
			$styles .= esc_url_raw($local_css_url);
		} elseif (!empty($google_request['url']) && is_string($google_request['url'])) {
			if ('' !== $styles) {
				$styles .= ',';
			}
			$styles .= esc_url_raw($google_request['url']);
		}

		$editor_css_url = MRN_GOOGLE_FONTS_URL . 'assets/css/editor-fonts.css';
		if ('' !== $styles) {
			$styles .= ',';
		}

		return $styles . esc_url_raw($editor_css_url);
	}

	/**
	 * Add CSS variable values to TinyMCE content_style.
	 *
	 * @param array<string, mixed> $settings TinyMCE settings array.
	 * @return array<string, mixed>
	 */
	public static function inject_tinymce_content_style(array $settings): array {
		$plugin_settings = self::get_settings();
		if (empty($plugin_settings['enabled']) || empty($plugin_settings['load_in_classic_editor'])) {
			return $settings;
		}

		// _WP_Editors::_parse_init() wraps string values in double quotes without escaping.
		// Keep TinyMCE content_style JS-safe by avoiding unescaped double quotes here.
		$body_stack = str_replace('"', "'", self::build_font_stack((string) $plugin_settings['body_font_family']));
		$heading_stack = str_replace('"', "'", self::build_font_stack((string) $plugin_settings['heading_font_family']));
		$accent_stack = str_replace('"', "'", self::build_font_stack((string) ($plugin_settings['accent_font_family'] ?? 'system-ui')));
		$css = ':root{--mrn-font-body:' . $body_stack . ';--mrn-font-heading:' . $heading_stack . ';--mrn-font-accent:' . $accent_stack . ';}';
		$css .= self::build_font_target_css($plugin_settings, 'editor');

		$existing = isset($settings['content_style']) ? (string) $settings['content_style'] : '';
		$settings['content_style'] = trim($existing . ' ' . $css);
		$settings['font_formats'] = self::inject_tinymce_font_formats(
			isset($settings['font_formats']) ? (string) $settings['font_formats'] : '',
			$plugin_settings
		);

		return $settings;
	}

	/**
	 * Merge configured Google font families into TinyMCE font formats.
	 *
	 * @param string               $existing_formats Existing TinyMCE font_formats string.
	 * @param array<string, mixed> $plugin_settings Plugin settings.
	 */
	private static function inject_tinymce_font_formats(string $existing_formats, array $plugin_settings): string {
		$custom_families = array();
		$body_family = self::normalize_primary_family_name((string) ($plugin_settings['body_font_family'] ?? ''));
		$heading_family = self::normalize_primary_family_name((string) ($plugin_settings['heading_font_family'] ?? ''));
		$accent_family = self::normalize_primary_family_name((string) ($plugin_settings['accent_font_family'] ?? ''));

		foreach (array($body_family, $heading_family, $accent_family) as $family) {
			if ('' === $family || self::is_system_font_family($family)) {
				continue;
			}

			$family = trim(preg_replace('/\s+/', ' ', str_replace(array('"', "'"), '', (string) $family)));
			if ('' === $family || in_array($family, $custom_families, true)) {
				continue;
			}

			$custom_families[] = $family;
		}

		if (empty($custom_families)) {
			return $existing_formats;
		}

		$format_map = array();

		foreach (array_filter(array_map('trim', explode(';', $existing_formats))) as $pair) {
			$parts = array_map('trim', explode('=', (string) $pair, 2));
			if (2 !== count($parts) || '' === $parts[0] || '' === $parts[1]) {
				continue;
			}

			$label = trim((string) $parts[0]);
			$value = trim((string) $parts[1]);
			$label_key = strtolower($label);
			if ('' === $label || '' === $value || isset($format_map[ $label_key ])) {
				continue;
			}

			$format_map[ $label_key ] = array(
				'label' => $label,
				'value' => $value,
			);
		}

		if (empty($format_map)) {
			foreach (self::get_default_tinymce_font_format_pairs() as $pair) {
				$parts = array_map('trim', explode('=', (string) $pair, 2));
				if (2 !== count($parts) || '' === $parts[0] || '' === $parts[1]) {
					continue;
				}

				$label = trim((string) $parts[0]);
				$value = trim((string) $parts[1]);
				$label_key = strtolower($label);
				if ('' === $label || '' === $value || isset($format_map[ $label_key ])) {
					continue;
				}

				$format_map[ $label_key ] = array(
					'label' => $label,
					'value' => $value,
				);
			}
		}

		foreach ($custom_families as $family) {
			$label_key = strtolower($family);
			if (isset($format_map[ $label_key ])) {
				continue;
			}

			$format_map[ $label_key ] = array(
				'label' => $family,
				'value' => $family . ',sans-serif',
			);
		}

		$sorted_entries = array_values($format_map);
		usort(
			$sorted_entries,
			static function (array $a, array $b): int {
				return strnatcasecmp((string) ($a['label'] ?? ''), (string) ($b['label'] ?? ''));
			}
		);

		$final_pairs = array();
		foreach ($sorted_entries as $entry) {
			if (!isset($entry['label'], $entry['value']) || '' === (string) $entry['label'] || '' === (string) $entry['value']) {
				continue;
			}

			$final_pairs[] = $entry['label'] . '=' . $entry['value'];
		}

		return implode(';', $final_pairs);
	}

	/**
	 * Return the TinyMCE legacy default font format pairs.
	 *
	 * @return array<int, string>
	 */
	private static function get_default_tinymce_font_format_pairs(): array {
		return array(
			'Andale Mono=andale mono,times',
			'Arial=arial,helvetica,sans-serif',
			'Arial Black=arial black,avant garde',
			'Book Antiqua=book antiqua,palatino',
			'Comic Sans MS=comic sans ms,sans-serif',
			'Courier New=courier new,courier',
			'Georgia=georgia,palatino',
			'Helvetica=helvetica',
			'Impact=impact,chicago',
			'Symbol=symbol',
			'Tahoma=tahoma,arial,helvetica,sans-serif',
			'Terminal=terminal,monaco',
			'Times New Roman=times new roman,times',
			'Trebuchet MS=trebuchet ms,geneva',
			'Verdana=verdana,geneva',
			'Webdings=webdings',
			'Wingdings=wingdings,zapf dingbats',
		);
	}

	/**
	 * Build local font files from current settings and store a local manifest.
	 */
	public static function handle_build_local_assets(): void {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You are not allowed to build local fonts.', 'mrn-google-fonts'));
		}

		$fallback_redirect = self::get_default_builder_redirect();
		$nonce = isset($_POST['mrn_google_fonts_local_assets_nonce'])
			? sanitize_text_field((string) wp_unslash($_POST['mrn_google_fonts_local_assets_nonce']))
			: '';

		if ('' === $nonce || !wp_verify_nonce($nonce, 'mrn_google_fonts_local_assets')) {
			self::redirect_with_notice(
				$fallback_redirect,
				'Security check failed while building local fonts. Refresh the page and try again.',
				'error'
			);
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Redirect target is sanitized by esc_url_raw + wp_validate_redirect().
		$requested_redirect = isset($_POST['mrn_google_fonts_redirect_to']) ? wp_unslash($_POST['mrn_google_fonts_redirect_to']) : '';
		$redirect_to = self::resolve_redirect_target((string) $requested_redirect, $fallback_redirect);

		$settings_for_build = self::get_settings();
		$has_posted_settings = isset($_POST[self::OPTION_KEY]) && is_array($_POST[self::OPTION_KEY]);
		$has_legacy_builder_payload = isset($_POST['mrn_google_fonts_builder']) && is_array($_POST['mrn_google_fonts_builder']);

		if ($has_posted_settings || $has_legacy_builder_payload) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Raw settings are sanitized by self::sanitize_settings().
			$raw_input = $has_posted_settings ? wp_unslash($_POST[self::OPTION_KEY]) : array();
			$merged = array_replace($settings_for_build, $raw_input);

			// Backward-compatible fallback for already-open admin pages posting legacy builder fields.
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Builder payload is sanitized by self::sanitize_settings().
			$builder_input = $has_legacy_builder_payload ? wp_unslash($_POST['mrn_google_fonts_builder']) : array();
			foreach (array('body_font_family', 'heading_font_family', 'accent_font_family', 'body_font_weights', 'heading_font_weights', 'accent_font_weights', 'body_font_italics', 'heading_font_italics', 'accent_font_italics', 'body_font_targets', 'heading_font_targets', 'accent_font_targets') as $field_key) {
				if (array_key_exists($field_key, $builder_input)) {
					$merged[$field_key] = $builder_input[$field_key];
				}
			}

			$settings_for_build = self::sanitize_settings($merged);
			update_option(self::OPTION_KEY, $settings_for_build, false);
		}

		$auto_enabled_runtime = false;
		if (empty($settings_for_build['enabled']) || empty($settings_for_build['load_on_frontend'])) {
			$settings_for_build['enabled'] = 1;
			$settings_for_build['load_on_frontend'] = 1;
			$settings_for_build = self::sanitize_settings($settings_for_build);
			update_option(self::OPTION_KEY, $settings_for_build, false);
			$auto_enabled_runtime = true;
		}

		$build_result = self::build_local_assets($settings_for_build);

		if (is_wp_error($build_result)) {
			self::redirect_with_notice($redirect_to, $build_result->get_error_message(), 'error');
		}

		$file_count = isset($build_result['file_count']) ? (int) $build_result['file_count'] : 0;
		$family_count = isset($build_result['family_count']) ? (int) $build_result['family_count'] : 0;
		$message = sprintf(
			'Local font build complete. %1$d font file%2$s ready for local serving.',
			$file_count,
			1 === $file_count ? '' : 's'
		);

		if ($family_count > 0) {
			$message .= ' ' . sprintf(
				'Source request includes %1$d famil%2$s.',
				$family_count,
				1 === $family_count ? 'y' : 'ies'
			);
		}
		if ($auto_enabled_runtime) {
			$message .= ' Frontend Google Fonts runtime was enabled automatically.';
		}

		self::redirect_with_notice(
			$redirect_to,
			$message,
			'success'
		);
	}

	/**
	 * Clear previously built local font files.
	 */
	public static function handle_clear_local_assets(): void {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You are not allowed to clear local fonts.', 'mrn-google-fonts'));
		}

		$fallback_redirect = self::get_default_builder_redirect();
		$nonce = isset($_POST['mrn_google_fonts_local_assets_nonce'])
			? sanitize_text_field((string) wp_unslash($_POST['mrn_google_fonts_local_assets_nonce']))
			: '';

		if ('' === $nonce || !wp_verify_nonce($nonce, 'mrn_google_fonts_local_assets')) {
			self::redirect_with_notice(
				$fallback_redirect,
				'Security check failed while clearing local fonts. Refresh the page and try again.',
				'error'
			);
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Redirect target is sanitized by esc_url_raw + wp_validate_redirect().
		$requested_redirect = isset($_POST['mrn_google_fonts_redirect_to']) ? wp_unslash($_POST['mrn_google_fonts_redirect_to']) : '';
		$redirect_to = self::resolve_redirect_target((string) $requested_redirect, $fallback_redirect);

		$manifest = self::get_local_manifest();
		self::maybe_delete_manifest_directory($manifest);
		delete_option(self::LOCAL_OPTION_KEY);

		self::redirect_with_notice(
			$redirect_to,
			'Local font cache cleared. Runtime will use Google CSS2 until local assets are rebuilt.',
			'success'
		);
	}

	/**
	 * Register Google Fonts as an option in Site Styles transfer sections.
	 *
	 * @param mixed $sections Existing transfer sections.
	 * @return array<string, string>
	 */
	public static function filter_site_styles_transfer_sections($sections): array {
		if (!is_array($sections)) {
			$sections = array();
		}

		$sections[self::SITE_STYLES_TRANSFER_SECTION_KEY] = 'Google Fonts';

		return $sections;
	}

	/**
	 * Add Google Fonts settings to Site Styles export payload when selected.
	 *
	 * @param array<string, mixed> $data Export data map.
	 * @param array<string>        $sections Selected section keys.
	 * @return array<string, mixed>
	 */
	public static function filter_site_styles_export_data(array $data, array $sections): array {
		if (!in_array(self::SITE_STYLES_TRANSFER_SECTION_KEY, $sections, true)) {
			return $data;
		}

		$data['google_fonts'] = self::get_settings();

		return $data;
	}

	/**
	 * Import Google Fonts settings from a Site Styles import payload.
	 *
	 * @param mixed                    $imported_sections Existing imported section labels.
	 * @param array<string, mixed>     $data Imported Site Styles payload data.
	 * @return array<int, string>
	 */
	public static function filter_site_styles_import_data($imported_sections, array $data): array {
		$imported_sections = is_array($imported_sections) ? $imported_sections : array();

		if (!array_key_exists('google_fonts', $data) || !is_array($data['google_fonts'])) {
			return $imported_sections;
		}

		$sanitized = self::sanitize_settings($data['google_fonts']);
		update_option(self::OPTION_KEY, $sanitized, false);
		$imported_sections[] = 'Google Fonts';

		return array_values(array_unique(array_map('strval', $imported_sections)));
	}

	/**
	 * Render query-arg-based request notice for build/clear actions.
	 */
	private static function render_request_notice(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only query args from our own admin redirect notices.
		$notice_message = isset($_GET['mrn_google_fonts_notice'])
			? sanitize_text_field((string) wp_unslash($_GET['mrn_google_fonts_notice']))
			: '';
		$notice_type = isset($_GET['mrn_google_fonts_notice_type'])
			? sanitize_key((string) wp_unslash($_GET['mrn_google_fonts_notice_type']))
			: 'success';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ('' === $notice_message) {
			return;
		}

		if (!in_array($notice_type, array('success', 'error', 'warning'), true)) {
			$notice_type = 'success';
		}
		?>
		<div class="notice notice-<?php echo esc_attr($notice_type); ?> is-dismissible"><p><?php echo esc_html($notice_message); ?></p></div>
		<?php
	}

	/**
	 * Render local build controls in settings and Site Styles contexts.
	 *
	 * @param array<string, mixed> $settings Plugin settings.
	 * @param string               $context  Render context.
	 */
	private static function render_local_builder_controls(array $settings, string $context = 'settings'): void {
		$status = self::get_local_asset_status($settings);
		$option_name = self::OPTION_KEY;

		if ('site_styles' === $context) {
			$redirect_to = add_query_arg(
				array(
					'page' => 'mrn-site-styles',
					'updated' => self::SITE_STYLES_TAB_KEY,
				),
				admin_url('options-general.php')
			);
		} elseif ('builder' === $context) {
			$redirect_to = add_query_arg(
				array(
					'page' => self::PAGE_SLUG,
					'tab' => 'font-builder',
				),
				admin_url('options-general.php')
			);
		} else {
			$redirect_to = add_query_arg(
				array(
					'page' => self::PAGE_SLUG,
					'tab' => 'font-builder',
				),
				admin_url('options-general.php')
			);
		}
		$build_action_url = add_query_arg('action', self::BUILD_LOCAL_ACTION, admin_url('admin-post.php'));
		$clear_action_url = add_query_arg('action', self::CLEAR_LOCAL_ACTION, admin_url('admin-post.php'));
		$target_options = self::get_font_target_options();
		$defaults = self::default_settings();
		$site_styles_owns_typography = self::site_styles_owns_typography($settings);
		$font_slots = array(
			'body' => array(
				'label' => 'Body',
				'family_key' => 'body_font_family',
				'weights_key' => 'body_font_weights',
				'italics_key' => 'body_font_italics',
				'targets_key' => 'body_font_targets',
				'family_description' => 'Set to <code>system-ui</code> to avoid remote font loading for body text.',
				'weights_description' => 'Comma-separated numeric weights, for example <code>400,500</code>.',
				'italics_description' => 'Include italic styles for body weights.',
			),
			'heading' => array(
				'label' => 'Heading',
				'family_key' => 'heading_font_family',
				'weights_key' => 'heading_font_weights',
				'italics_key' => 'heading_font_italics',
				'targets_key' => 'heading_font_targets',
				'family_description' => 'Use for headings, hero text, and other display typography.',
				'weights_description' => 'Use only weights needed by the design to keep file size low.',
				'italics_description' => 'Include italic styles for heading weights.',
			),
			'accent' => array(
				'label' => 'Accent',
				'family_key' => 'accent_font_family',
				'weights_key' => 'accent_font_weights',
				'italics_key' => 'accent_font_italics',
				'targets_key' => 'accent_font_targets',
				'family_description' => 'Optional third family for callouts, quotes, labels, or navigation accents.',
				'weights_description' => 'Keep accent weights minimal to avoid unnecessary downloads.',
				'italics_description' => 'Include italic styles for accent weights.',
			),
		);
		if ($site_styles_owns_typography) {
			$font_slots['body']['label'] = 'Primary';
			$font_slots['heading']['label'] = 'Secondary';
			$font_slots['accent']['label'] = 'Accent';
		}

		$catalog = self::get_google_font_family_catalog();
		$configured_families = array();
		$slot_values = array();

		foreach ($font_slots as $slot_key => $slot_meta) {
			$family_key = (string) $slot_meta['family_key'];
			$weights_key = (string) $slot_meta['weights_key'];
			$italics_key = (string) $slot_meta['italics_key'];
			$targets_key = (string) $slot_meta['targets_key'];

			$family = self::normalize_primary_family_name((string) ($settings[$family_key] ?? ($defaults[$family_key] ?? 'system-ui')));
			if ('' === $family) {
				$family = 'system-ui';
			}

			$weights = self::sanitize_font_weights_value((string) ($settings[$weights_key] ?? ($defaults[$weights_key] ?? '400')));
			$italics = !empty($settings[$italics_key]);
			$targets = self::sanitize_font_targets_value($settings[$targets_key] ?? ($defaults[$targets_key] ?? array()));

			$slot_values[$slot_key] = array(
				'family' => $family,
				'weights' => $weights,
				'italics' => $italics,
				'targets' => $targets,
				'family_name' => $option_name . '[' . $family_key . ']',
				'weights_name' => $option_name . '[' . $weights_key . ']',
				'italics_name' => $option_name . '[' . $italics_key . ']',
				'targets_name' => $option_name . '[' . $targets_key . '][]',
			);

			$configured_families[] = $family;

			if (!self::is_system_font_family($family) && !in_array($family, $catalog, true)) {
				$catalog[] = $family;
			}
		}

		natcasesort($catalog);
		$catalog = array_values(array_unique(array_filter(array_map('strval', $catalog))));
		// Keep initial datalist payload compact for browser stability; live search fills the rest.
		$initial_catalog = array_values(
			array_unique(
				array_merge(
					$configured_families,
					array_slice($catalog, 0, 250)
				)
			)
		);

		$context_slug = sanitize_html_class($context);
		$chooser_id = 'mrn-google-fonts-chooser-' . $context_slug;
		$datalist_id = 'mrn-google-fonts-family-catalog-' . $context_slug;
		$search_url = admin_url('admin-ajax.php');
		$search_nonce = wp_create_nonce('mrn_google_fonts_search_families');
		?>
		<div
			id="<?php echo esc_attr($chooser_id); ?>"
			class="mrn-google-fonts-status"
			data-mrn-google-fonts-search-url="<?php echo esc_url($search_url); ?>"
			data-mrn-google-fonts-search-nonce="<?php echo esc_attr($search_nonce); ?>"
			data-mrn-google-fonts-datalist-id="<?php echo esc_attr($datalist_id); ?>"
		>
			<p><strong>Google Font Chooser</strong></p>
			<p class="description" style="margin:0 0 12px;">
				<?php if ($site_styles_owns_typography) : ?>
					Choose the font families and variants to load. Assign families to tags in <strong>Site Styles → Typography</strong>.
				<?php else : ?>
					Each card below controls one font. Assign selectors per card. When selector targets overlap, Accent overrides Heading and Heading overrides Body.
				<?php endif; ?>
			</p>
			<?php
			$target_groups = array();
			foreach ($target_options as $target_key => $target_meta) {
				$group = isset($target_meta['group']) ? (string) $target_meta['group'] : 'Other';
				if (!isset($target_groups[$group])) {
					$target_groups[$group] = array();
				}
				$target_groups[$group][$target_key] = $target_meta;
			}
			$slot_index = 0;
			?>
			<div class="mrn-google-fonts-slot-grid">
				<?php foreach ($font_slots as $slot_key => $slot_meta) : ?>
					<?php
					$slot_index++;
					$slot = $slot_values[$slot_key];
					$family_input_id = 'mrn-google-fonts-builder-' . $slot_key . '-family-' . $context_slug;
					$weights_input_id = 'mrn-google-fonts-builder-' . $slot_key . '-weights-' . $context_slug;
					$italics_input_id = 'mrn-google-fonts-builder-' . $slot_key . '-italics-' . $context_slug;
					$targets_input_id = 'mrn-google-fonts-builder-' . $slot_key . '-targets-' . $context_slug;
					?>
					<section class="mrn-google-fonts-slot-card" aria-labelledby="<?php echo esc_attr($slot_key . '-font-slot-title-' . $context_slug); ?>">
						<h3 id="<?php echo esc_attr($slot_key . '-font-slot-title-' . $context_slug); ?>" class="mrn-google-fonts-slot-title"><?php echo esc_html((string) $slot_index); ?>. <?php echo esc_html((string) $slot_meta['label']); ?> Font</h3>
						<p class="mrn-google-fonts-slot-help"><?php echo wp_kses_post((string) $slot_meta['family_description']); ?></p>

						<div class="mrn-google-fonts-field">
							<label for="<?php echo esc_attr($family_input_id); ?>">Font family</label>
							<input
								type="text"
								class="regular-text"
								id="<?php echo esc_attr($family_input_id); ?>"
								list="<?php echo esc_attr($datalist_id); ?>"
								value="<?php echo esc_attr((string) $slot['family']); ?>"
								placeholder="system-ui or Google family"
								data-mrn-google-fonts-family-input="1"
								name="<?php echo esc_attr((string) $slot['family_name']); ?>"
							/>
						</div>

						<div class="mrn-google-fonts-field">
							<label for="<?php echo esc_attr($weights_input_id); ?>">Weights</label>
							<input
								type="text"
								class="regular-text code"
								id="<?php echo esc_attr($weights_input_id); ?>"
								value="<?php echo esc_attr((string) $slot['weights']); ?>"
								placeholder="400"
								name="<?php echo esc_attr((string) $slot['weights_name']); ?>"
							/>
							<p class="description"><?php echo wp_kses_post((string) $slot_meta['weights_description']); ?></p>
						</div>

						<div class="mrn-google-fonts-field">
							<span style="display:block;margin-bottom:6px;font-weight:600;">Italics</span>
							<input type="hidden" name="<?php echo esc_attr((string) $slot['italics_name']); ?>" value="0" />
							<div class="mrn-google-fonts-inline-check">
								<input
									type="checkbox"
									id="<?php echo esc_attr($italics_input_id); ?>"
									value="1"
									name="<?php echo esc_attr((string) $slot['italics_name']); ?>"
									<?php checked(!empty($slot['italics'])); ?>
								/>
								<label for="<?php echo esc_attr($italics_input_id); ?>"><?php echo esc_html((string) $slot_meta['italics_description']); ?></label>
							</div>
							<p class="description">Uses Google CSS2 <code>ital,wght</code> tuples for variable-font-safe local builds.</p>
						</div>

						<?php if (!$site_styles_owns_typography) : ?>
						<div class="mrn-google-fonts-field">
							<label for="<?php echo esc_attr($targets_input_id); ?>">Assign target selectors</label>
							<input type="hidden" name="<?php echo esc_attr((string) $slot['targets_name']); ?>" value="" />
							<select
								id="<?php echo esc_attr($targets_input_id); ?>"
								name="<?php echo esc_attr((string) $slot['targets_name']); ?>"
								multiple
								size="12"
							>
								<?php foreach ($target_groups as $target_group_label => $group_targets) : ?>
									<optgroup label="<?php echo esc_attr($target_group_label); ?>">
										<?php foreach ($group_targets as $target_key => $target_meta) : ?>
											<option value="<?php echo esc_attr($target_key); ?>" <?php selected(in_array($target_key, (array) $slot['targets'], true)); ?>>
												<?php echo esc_html((string) $target_meta['label']); ?>
											</option>
										<?php endforeach; ?>
									</optgroup>
								<?php endforeach; ?>
							</select>
							<p class="description">Use Cmd/Ctrl-click for multi-select. Individual <code>H1</code>-<code>H6</code> targets are in the Headings group.</p>
						</div>
						<?php endif; ?>
					</section>
				<?php endforeach; ?>
			</div>

			<datalist id="<?php echo esc_attr($datalist_id); ?>">
				<option value="system-ui"></option>
				<?php foreach ($initial_catalog as $catalog_family) : ?>
					<?php if (self::is_system_font_family((string) $catalog_family)) : ?>
						<?php continue; ?>
					<?php endif; ?>
					<option value="<?php echo esc_attr((string) $catalog_family); ?>"></option>
				<?php endforeach; ?>
			</datalist>

			<p class="description" style="margin-top:10px;">Save settings, then run <strong>Build Local Fonts</strong> to self-host and avoid Google CDN requests on frontend pages.</p>
			<script src="<?php echo esc_url(MRN_GOOGLE_FONTS_URL . 'assets/js/admin-chooser.js?ver=' . rawurlencode((string) self::VERSION)); ?>"></script>
		</div>

		<div class="mrn-google-fonts-status">
			<p><strong>Local Font Builder</strong></p>
			<?php if (empty($status['request_url'])) : ?>
				<p>No Google request is configured yet. Set at least one non-system family before building local files.</p>
			<?php else : ?>
				<p>Current request signature: <code><?php echo esc_html((string) $status['request_signature']); ?></code></p>
				<?php if (!empty($status['active'])) : ?>
					<p>Local status: <strong>active</strong></p>
					<p>Local CSS URL: <code><?php echo esc_html((string) $status['css_url']); ?></code></p>
				<?php elseif (!empty($status['has_manifest'])) : ?>
					<p>Local status: <strong>stale</strong> (built files do not match current family/weight settings)</p>
				<?php else : ?>
					<p>Local status: <strong>not built</strong></p>
				<?php endif; ?>
				<?php if (!empty($status['generated_at'])) : ?>
					<p>Last build: <code><?php echo esc_html(wp_date('Y-m-d H:i:s', (int) $status['generated_at'])); ?></code></p>
				<?php endif; ?>
				<?php if (!empty($status['family_count'])) : ?>
					<p>Configured families: <code><?php echo esc_html((string) $status['family_count']); ?></code></p>
				<?php endif; ?>
				<p>Downloaded files: <code><?php echo esc_html((string) $status['file_count']); ?></code></p>
			<?php endif; ?>
			<p class="description">Build local files to serve fonts from your domain and avoid Google CDN requests on stack-owned pages.</p>
			<?php wp_nonce_field('mrn_google_fonts_local_assets', 'mrn_google_fonts_local_assets_nonce'); ?>
			<input type="hidden" name="mrn_google_fonts_redirect_to" value="<?php echo esc_url($redirect_to); ?>" />
			<p style="margin-top:10px;">
				<button
					type="submit"
					class="button button-secondary"
					formaction="<?php echo esc_url($build_action_url); ?>"
					formmethod="post"
				>
					Build Local Fonts
				</button>
				<?php if (!empty($status['has_manifest'])) : ?>
					<button
						type="submit"
						class="button"
						formaction="<?php echo esc_url($clear_action_url); ?>"
						formmethod="post"
					>
						Clear Local Build
					</button>
				<?php endif; ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Return local-build status for current settings.
	 *
	 * @param array<string, mixed> $settings Plugin settings.
	 * @return array<string, mixed>
	 */
	private static function get_local_asset_status(array $settings): array {
		$google_request = self::build_google_fonts_request($settings);
		$request_signature = self::get_request_signature($google_request, $settings);
		$manifest = self::get_local_manifest();
		$active = self::local_manifest_matches_signature($manifest, $request_signature);

		return array(
			'active' => $active,
			'has_manifest' => !empty($manifest['signature']) && !empty($manifest['css_url']),
			'generated_at' => isset($manifest['generated_at']) ? (int) $manifest['generated_at'] : 0,
			'file_count' => isset($manifest['file_count']) ? (int) $manifest['file_count'] : 0,
			'family_count' => isset($manifest['family_count']) ? (int) $manifest['family_count'] : 0,
			'css_url' => isset($manifest['css_url']) ? (string) $manifest['css_url'] : '',
			'request_signature' => $request_signature,
			'request_url' => isset($google_request['url']) && is_string($google_request['url']) ? (string) $google_request['url'] : '',
		);
	}

	/**
	 * Return local CSS URL when a matching local build exists.
	 *
	 * @param array<string, mixed> $settings Plugin settings.
	 * @param array<string, mixed> $google_request Google request payload.
	 */
	private static function get_local_css_url_for_request(array $settings, array $google_request): string {
		$manifest = self::get_local_manifest();
		$request_signature = self::get_request_signature($google_request, $settings);

		if (!self::local_manifest_matches_signature($manifest, $request_signature)) {
			return '';
		}

		$css_url = self::resolve_local_manifest_css_url($manifest);
		if ('' === $css_url || !wp_http_validate_url($css_url)) {
			return '';
		}

		return $css_url;
	}

	/**
	 * Get sanitized local manifest.
	 *
	 * @return array<string, mixed>
	 */
	private static function get_local_manifest(): array {
		$saved = get_option(self::LOCAL_OPTION_KEY, array());
		if (!is_array($saved)) {
			return array();
		}

		return array(
			'signature' => isset($saved['signature']) ? sanitize_text_field((string) $saved['signature']) : '',
			'css_url' => isset($saved['css_url']) ? esc_url_raw((string) $saved['css_url']) : '',
			'css_path' => isset($saved['css_path']) ? (string) $saved['css_path'] : '',
			'directory' => isset($saved['directory']) ? (string) $saved['directory'] : '',
			'generated_at' => isset($saved['generated_at']) ? absint($saved['generated_at']) : 0,
			'file_count' => isset($saved['file_count']) ? absint($saved['file_count']) : 0,
			'family_count' => isset($saved['family_count']) ? absint($saved['family_count']) : 0,
			'request_url' => isset($saved['request_url']) ? esc_url_raw((string) $saved['request_url']) : '',
		);
	}

	/**
	 * Check whether saved local manifest matches the current request signature.
	 *
	 * @param array<string, mixed> $manifest Saved local manifest.
	 * @param string               $signature Current request signature.
	 */
	private static function local_manifest_matches_signature(array $manifest, string $signature): bool {
		$manifest_signature = self::sanitize_local_manifest_signature($manifest['signature'] ?? '');
		$signature = self::sanitize_local_manifest_signature($signature);
		if ('' === $signature || '' === $manifest_signature || !hash_equals($manifest_signature, $signature)) {
			return false;
		}

		$css_path = self::resolve_local_manifest_css_path($manifest);
		if ('' === $css_path || !file_exists($css_path)) {
			return false;
		}

		return '' !== self::resolve_local_manifest_css_url($manifest);
	}

	/**
	 * Resolve a local manifest CSS path against the current uploads directory.
	 *
	 * Manifest paths are absolute and can become stale after a site pull, hosting
	 * migration, or container path change. The request signature is stable, so use
	 * it to rediscover the local build before falling back to remote Google CSS.
	 *
	 * @param array<string, mixed> $manifest Saved local manifest.
	 */
	private static function resolve_local_manifest_css_path(array $manifest): string {
		$css_path = isset($manifest['css_path']) ? wp_normalize_path((string) $manifest['css_path']) : '';
		if ('' !== $css_path && file_exists($css_path)) {
			return $css_path;
		}

		$signature = self::sanitize_local_manifest_signature($manifest['signature'] ?? '');
		if ('' === $signature) {
			return '';
		}

		$root = self::get_local_assets_root();
		if (empty($root['basedir'])) {
			return '';
		}

		$derived_path = trailingslashit((string) $root['basedir']) . $signature . '/local-fonts.css';

		return file_exists($derived_path) ? wp_normalize_path($derived_path) : '';
	}

	/**
	 * Resolve a local manifest CSS URL against the current uploads URL.
	 *
	 * @param array<string, mixed> $manifest Saved local manifest.
	 */
	private static function resolve_local_manifest_css_url(array $manifest): string {
		$signature = self::sanitize_local_manifest_signature($manifest['signature'] ?? '');
		if ('' !== $signature) {
			$root = self::get_local_assets_root();
			if (!empty($root['basedir']) && !empty($root['baseurl'])) {
				$derived_path = trailingslashit((string) $root['basedir']) . $signature . '/local-fonts.css';
				if (file_exists($derived_path)) {
					return esc_url_raw(trailingslashit((string) $root['baseurl']) . $signature . '/local-fonts.css');
				}
			}
		}

		$css_url = isset($manifest['css_url']) ? esc_url_raw((string) $manifest['css_url']) : '';

		return wp_http_validate_url($css_url) ? $css_url : '';
	}

	/**
	 * Sanitize and validate a local-build request signature.
	 *
	 * @param mixed $signature Raw signature value.
	 */
	private static function sanitize_local_manifest_signature($signature): string {
		$signature = strtolower(sanitize_text_field((string) $signature));

		return preg_match('/^[a-f0-9]{40}$/', $signature) ? $signature : '';
	}

	/**
	 * Build deterministic request signature for local build matching.
	 *
	 * @param array<string, mixed> $google_request Google request payload.
	 * @param array<string, mixed> $settings Plugin settings.
	 */
	private static function get_request_signature(array $google_request, array $settings): string {
		$request_url = isset($google_request['url']) && is_string($google_request['url']) ? (string) $google_request['url'] : '';
		if ('' === $request_url) {
			return '';
		}

		$subset = sanitize_key((string) ($settings['subset'] ?? 'latin'));
		return sha1($request_url . '|subset=' . $subset);
	}

	/**
	 * Resolve safe redirect target from request payload.
	 */
	private static function resolve_redirect_target(string $requested_redirect, string $fallback): string {
		$requested_redirect = esc_url_raw($requested_redirect);
		return wp_validate_redirect($requested_redirect, $fallback);
	}

	/**
	 * Get default builder redirect target based on Site Styles availability.
	 */
	private static function get_default_builder_redirect(): string {
		if (MRN_Google_Fonts_Stack_Bridge::supports_site_styles_tab_extension()) {
			return self::get_site_styles_redirect();
		}

		return add_query_arg(
			array(
				'page' => self::PAGE_SLUG,
				'tab' => 'font-builder',
			),
			admin_url('options-general.php')
		);
	}

	/**
	 * Return Site Styles Google Fonts tab URL.
	 */
	private static function get_site_styles_redirect(): string {
		return add_query_arg(
			array(
				'page' => 'mrn-site-styles',
				'updated' => self::SITE_STYLES_TAB_KEY,
			),
			admin_url('options-general.php')
		);
	}

	/**
	 * Redirect to target with user-facing notice query args.
	 */
	private static function redirect_with_notice(string $redirect_to, string $message, string $type): void {
		if (!in_array($type, array('success', 'error', 'warning'), true)) {
			$type = 'success';
		}

		$target = add_query_arg(
			array(
				'mrn_google_fonts_notice' => $message,
				'mrn_google_fonts_notice_type' => $type,
			),
			$redirect_to
		);

		wp_safe_redirect($target);
		exit;
	}

	/**
	 * Build local font assets from Google CSS2 response.
	 *
	 * @param array<string, mixed> $settings Plugin settings.
	 * @return array<string, mixed>|\WP_Error
	 */
	private static function build_local_assets(array $settings) {
		$google_request = self::build_google_fonts_request($settings);
		$request_url = isset($google_request['url']) && is_string($google_request['url']) ? (string) $google_request['url'] : '';
		$family_count = !empty($google_request['families']) && is_array($google_request['families']) ? count($google_request['families']) : 0;

		if ('' === $request_url) {
			return new WP_Error('mrn_google_fonts_no_request', 'No eligible Google font families are configured. Choose at least one non-system family first.');
		}

		$request_signature = self::get_request_signature($google_request, $settings);
		if ('' === $request_signature) {
			return new WP_Error('mrn_google_fonts_no_signature', 'Could not build a local signature for the selected font request.');
		}

		$css = self::fetch_google_fonts_css($request_url);
		if (is_wp_error($css)) {
			return $css;
		}

		$font_urls = self::extract_google_font_file_urls($css);
		if (empty($font_urls)) {
			$configured_families = !empty($google_request['families']) && is_array($google_request['families'])
				? implode(', ', array_map('strval', $google_request['families']))
				: 'none';
			return new WP_Error(
				'mrn_google_fonts_no_font_files',
				'Google CSS2 response did not include downloadable font files. Confirm the selected family names are valid Google Fonts and try again. Configured families: ' . $configured_families
			);
		}

		$root = self::get_local_assets_root();
		if (empty($root['basedir']) || empty($root['baseurl'])) {
			return new WP_Error('mrn_google_fonts_upload_root', 'Upload directory is not available for local font storage.');
		}

		$root_basedir = (string) $root['basedir'];
		$root_baseurl = (string) $root['baseurl'];
		if (!is_dir($root_basedir) && !wp_mkdir_p($root_basedir)) {
			return new WP_Error('mrn_google_fonts_upload_create', 'Could not create the local font storage directory.');
		}

		$target_dir = trailingslashit($root_basedir) . $request_signature;
		$target_url = trailingslashit($root_baseurl) . $request_signature;
		$working_dir = trailingslashit($root_basedir) . $request_signature . '-tmp-' . wp_generate_password(6, false, false);

		if (!wp_mkdir_p($working_dir)) {
			return new WP_Error('mrn_google_fonts_working_create', 'Could not create a temporary build directory for local fonts.');
		}

		$replace_map = array();
		$file_count = 0;

		foreach ($font_urls as $index => $font_url) {
			$filename = self::build_local_font_filename($font_url, $index + 1);
			$file_path = trailingslashit($working_dir) . $filename;

			$response = wp_remote_get(
				$font_url,
				array(
					'timeout' => 30,
					'redirection' => 3,
					'reject_unsafe_urls' => true,
					'headers' => array(
						'User-Agent' => 'Mozilla/5.0 (WordPress; MRN Google Fonts Local Builder)',
						'Accept' => '*/*',
					),
				)
			);

			if (is_wp_error($response)) {
				self::delete_directory_recursive($working_dir, $root_basedir);
				return new WP_Error('mrn_google_fonts_download_failed', 'A font file download failed: ' . $response->get_error_message());
			}

			$status_code = (int) wp_remote_retrieve_response_code($response);
			if (200 !== $status_code) {
				self::delete_directory_recursive($working_dir, $root_basedir);
				return new WP_Error('mrn_google_fonts_download_status', 'A font file download returned an unexpected status: ' . $status_code);
			}

			$body = (string) wp_remote_retrieve_body($response);
			if ('' === $body) {
				self::delete_directory_recursive($working_dir, $root_basedir);
				return new WP_Error('mrn_google_fonts_download_empty', 'A downloaded font file was empty.');
			}

			if (false === @file_put_contents($file_path, $body, LOCK_EX)) {
				self::delete_directory_recursive($working_dir, $root_basedir);
				return new WP_Error('mrn_google_fonts_write_failed', 'Could not write a downloaded font file to local storage.');
			}

			$replace_map[$font_url] = trailingslashit($target_url) . $filename;
			$file_count++;
		}

		$local_css = str_replace(array_keys($replace_map), array_values($replace_map), $css);
		$local_css = "/* Local Google Fonts build generated " . gmdate('c') . " */\n" . $local_css;
		$working_css_path = trailingslashit($working_dir) . 'local-fonts.css';

		if (false === @file_put_contents($working_css_path, $local_css, LOCK_EX)) {
			self::delete_directory_recursive($working_dir, $root_basedir);
			return new WP_Error('mrn_google_fonts_css_write_failed', 'Could not write local font-face CSS.');
		}

		if (is_dir($target_dir)) {
			self::delete_directory_recursive($target_dir, $root_basedir);
		}

		if (!@rename($working_dir, $target_dir)) {
			self::delete_directory_recursive($working_dir, $root_basedir);
			return new WP_Error('mrn_google_fonts_finalize_failed', 'Could not finalize local font build directory.');
		}

		$previous_manifest = self::get_local_manifest();
		if (!empty($previous_manifest['signature']) && (string) $previous_manifest['signature'] !== $request_signature) {
			self::maybe_delete_manifest_directory($previous_manifest);
		}

		$manifest = array(
			'signature' => $request_signature,
			'css_url' => trailingslashit($target_url) . 'local-fonts.css',
			'css_path' => trailingslashit($target_dir) . 'local-fonts.css',
			'directory' => $target_dir,
			'generated_at' => time(),
			'file_count' => $file_count,
			'family_count' => $family_count,
			'request_url' => $request_url,
		);

		update_option(self::LOCAL_OPTION_KEY, $manifest, false);

		return $manifest;
	}

	/**
	 * Fetch CSS2 response body from Google Fonts.
	 *
	 * @return string|\WP_Error
	 */
	private static function fetch_google_fonts_css(string $request_url) {
		$user_agent = self::get_google_fonts_css_user_agent();
		$response = wp_remote_get(
			$request_url,
			array(
				'timeout' => 20,
				'redirection' => 3,
				'reject_unsafe_urls' => true,
				'headers' => array(
					'User-Agent' => $user_agent,
					'Accept' => 'text/css,*/*;q=0.1',
				),
			)
		);

		if (is_wp_error($response)) {
			return new WP_Error('mrn_google_fonts_css_fetch_failed', 'Could not fetch Google CSS2 stylesheet: ' . $response->get_error_message());
		}

		$status_code = (int) wp_remote_retrieve_response_code($response);
		if (200 !== $status_code) {
			return new WP_Error('mrn_google_fonts_css_status', 'Google CSS2 stylesheet returned an unexpected status: ' . $status_code);
		}

		$css = (string) wp_remote_retrieve_body($response);
		if ('' === trim($css)) {
			return new WP_Error('mrn_google_fonts_css_empty', 'Google CSS2 stylesheet response was empty.');
		}

		return $css;
	}

	/**
	 * Return User-Agent used when requesting Google CSS2.
	 */
	private static function get_google_fonts_css_user_agent(): string {
		/**
		 * Filter CSS2 request User-Agent for local Google Font builds.
		 *
		 * @param string $user_agent Default modern-browser user agent.
		 */
		$user_agent = apply_filters('mrn_google_fonts_css_user_agent', self::GOOGLE_FONTS_CSS_USER_AGENT);
		$user_agent = trim((string) $user_agent);

		if ('' === $user_agent) {
			$user_agent = self::GOOGLE_FONTS_CSS_USER_AGENT;
		}

		return $user_agent;
	}

	/**
	 * Extract unique fonts.gstatic.com woff2 URLs from CSS.
	 *
	 * @return array<int, string>
	 */
	private static function extract_google_font_file_urls(string $css): array {
		$matches = array();
		$urls = array();

		if (!preg_match_all('/url\(([^)]+)\)/i', $css, $matches) || empty($matches[1])) {
			return $urls;
		}

		foreach ($matches[1] as $raw_url) {
			$font_url = trim((string) $raw_url, " \t\n\r\0\x0B'\"");
			if ('' === $font_url) {
				continue;
			}

			if (0 === strpos($font_url, '//')) {
				$font_url = 'https:' . $font_url;
			}

			$parsed = wp_parse_url($font_url);
			if (!is_array($parsed) || empty($parsed['host']) || 'fonts.gstatic.com' !== strtolower((string) $parsed['host'])) {
				continue;
			}

			$scheme = isset($parsed['scheme']) ? strtolower((string) $parsed['scheme']) : 'https';
			if ('https' !== $scheme) {
				continue;
			}

			$urls[] = $font_url;
		}

		return array_values(array_unique($urls));
	}

	/**
	 * Return local-build storage root paths.
	 *
	 * @return array<string, string>
	 */
	private static function get_local_assets_root(): array {
		$uploads = wp_upload_dir(null, false);
		if (!is_array($uploads) || !empty($uploads['error'])) {
			return array();
		}

		$base_dir = trailingslashit((string) $uploads['basedir']) . 'mrn-google-fonts';
		$base_url = trailingslashit((string) $uploads['baseurl']) . 'mrn-google-fonts';

		return array(
			'basedir' => wp_normalize_path($base_dir),
			'baseurl' => untrailingslashit($base_url),
		);
	}

	/**
	 * Build deterministic local filename for a downloaded font file.
	 */
	private static function build_local_font_filename(string $font_url, int $index): string {
		$path = (string) wp_parse_url($font_url, PHP_URL_PATH);
		$extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
		if ('' === $extension) {
			$extension = 'woff2';
		}

		return 'font-' . $index . '-' . substr(md5($font_url), 0, 12) . '.' . $extension;
	}

	/**
	 * Delete local-build directory from manifest if it exists.
	 *
	 * @param array<string, mixed> $manifest Saved local manifest.
	 */
	private static function maybe_delete_manifest_directory(array $manifest): void {
		$directory = isset($manifest['directory']) ? (string) $manifest['directory'] : '';
		if ('' === $directory || !is_dir($directory)) {
			return;
		}

		$root = self::get_local_assets_root();
		if (empty($root['basedir'])) {
			return;
		}

		self::delete_directory_recursive($directory, (string) $root['basedir']);
	}

	/**
	 * Recursively delete directory, constrained to the local font root.
	 */
	private static function delete_directory_recursive(string $directory, string $root_directory): bool {
		$directory = wp_normalize_path($directory);
		$root_directory = trailingslashit(wp_normalize_path($root_directory));

		if ('' === $directory || 0 !== strpos(trailingslashit($directory), $root_directory)) {
			return false;
		}

		if (!is_dir($directory)) {
			return true;
		}

		$entries = scandir($directory);
		if (false === $entries) {
			return false;
		}

		foreach ($entries as $entry) {
			if ('.' === $entry || '..' === $entry) {
				continue;
			}

			$entry_path = $directory . '/' . $entry;
			if (is_dir($entry_path)) {
				self::delete_directory_recursive($entry_path, $root_directory);
			} else {
				@unlink($entry_path);
			}
		}

		return @rmdir($directory);
	}

	/**
	 * Register Google Fonts as a Site Styles extension tab.
	 *
	 * @param mixed $tabs Existing Site Styles tab definitions.
	 * @return array<int, array<string, string>>
	 */
	public static function filter_site_styles_tabs($tabs): array {
		if (!is_array($tabs)) {
			$tabs = array();
		}

		foreach ($tabs as $tab) {
			if (!is_array($tab)) {
				continue;
			}

			$key = isset($tab['key']) ? sanitize_key((string) $tab['key']) : '';
			if (self::SITE_STYLES_TAB_KEY === $key) {
				return $tabs;
			}
		}

		$tabs[] = array(
			'key' => self::SITE_STYLES_TAB_KEY,
			'label' => 'Google Fonts',
			'icon' => 'dashicons-editor-textcolor',
		);

		return $tabs;
	}

	/**
	 * Render the Site Styles extension panel for Google Fonts.
	 *
	 * @param string               $tab_key Active Site Styles tab key.
	 * @param array<string, mixed> $tab Tab metadata.
	 */
	public static function render_site_styles_tab_panel(string $tab_key, array $tab = array()): void {
		unset($tab);
		$tab_key = sanitize_key($tab_key);

		if (self::SITE_STYLES_TAB_KEY !== $tab_key) {
			return;
		}

		$settings = self::get_settings();
		$stack_status = MRN_Google_Fonts_Stack_Bridge::get_status((string) $settings['stack_bridge_mode']);
		$option_name = self::OPTION_KEY;
		$transfer_sections = function_exists('mrn_site_styles_get_transfer_sections')
			? mrn_site_styles_get_transfer_sections()
			: array(
				self::SITE_STYLES_TRANSFER_SECTION_KEY => 'Google Fonts',
			);

		if (!is_array($transfer_sections) || array() === $transfer_sections) {
			$transfer_sections = array(
				self::SITE_STYLES_TRANSFER_SECTION_KEY => 'Google Fonts',
			);
		}
		?>
		<div class="mrn-site-styles-card mrn-google-fonts-site-tabs" data-mrn-google-fonts-site-default="font-builder">
			<style>
				.mrn-google-fonts-site-tabs .nav-tab-wrapper {
					margin: 12px 0 16px;
				}
				.mrn-google-fonts-site-tab-panel[hidden] {
					display: none;
				}
			</style>
			<h2 class="nav-tab-wrapper" role="tablist" aria-label="Google Fonts options">
				<a href="#" class="nav-tab nav-tab-active" data-mrn-google-fonts-site-tab-trigger="font-builder" role="tab" aria-selected="true">Font Builder</a>
				<a href="#" class="nav-tab" data-mrn-google-fonts-site-tab-trigger="font-settings" role="tab" aria-selected="false">Font Settings</a>
				<a href="#" class="nav-tab" data-mrn-google-fonts-site-tab-trigger="stack-status" role="tab" aria-selected="false">Stack Status</a>
				<a href="#" class="nav-tab" data-mrn-google-fonts-site-tab-trigger="import-export" role="tab" aria-selected="false">Import|Export</a>
			</h2>

				<div class="mrn-google-fonts-site-tab-panel" data-mrn-google-fonts-site-tab-panel="font-builder">
					<p>Build local font files here after choosing families/weights.</p>
					<?php self::render_local_builder_controls($settings, 'site_styles'); ?>
				</div>

			<div class="mrn-google-fonts-site-tab-panel" data-mrn-google-fonts-site-tab-panel="font-settings" hidden>
				<p>Font families, weights, italics, and local font files are managed in <strong>Font Builder</strong>. Tag assignments are managed in <strong>Site Styles → Typography</strong>.</p>
				<p>
					<label>
						<input type="checkbox" name="<?php echo esc_attr($option_name); ?>[enabled]" value="1" <?php checked(!empty($settings['enabled'])); ?> />
						Enable Google Fonts runtime
					</label>
				</p>
				<p>
					<label>
						<input type="checkbox" name="<?php echo esc_attr($option_name); ?>[load_on_frontend]" value="1" <?php checked(!empty($settings['load_on_frontend'])); ?> />
						Load frontend typography runtime
					</label>
				</p>
				<p>
					<label>
						<input type="checkbox" name="<?php echo esc_attr($option_name); ?>[load_in_classic_editor]" value="1" <?php checked(!empty($settings['load_in_classic_editor'])); ?> />
						Load in Classic Editor / TinyMCE
					</label>
				</p>
				<p>
					<label for="mrn-site-styles-google-fonts-front-scope"><strong>Frontend load scope</strong></label><br />
					<select id="mrn-site-styles-google-fonts-front-scope" name="<?php echo esc_attr($option_name); ?>[frontend_load_scope]">
						<option value="all" <?php selected('all', (string) $settings['frontend_load_scope']); ?>>All frontend requests</option>
						<option value="front_page" <?php selected('front_page', (string) $settings['frontend_load_scope']); ?>>Front page only</option>
						<option value="singular" <?php selected('singular', (string) $settings['frontend_load_scope']); ?>>Singular content only</option>
						<option value="archive" <?php selected('archive', (string) $settings['frontend_load_scope']); ?>>Archive/search/posts index only</option>
						<option value="posts_page" <?php selected('posts_page', (string) $settings['frontend_load_scope']); ?>>Posts index only</option>
					</select>
				</p>
				<p>
					<label for="mrn-site-styles-google-fonts-display"><strong>Font display strategy</strong></label><br />
					<select id="mrn-site-styles-google-fonts-display" name="<?php echo esc_attr($option_name); ?>[font_display]">
						<option value="swap" <?php selected('swap', (string) $settings['font_display']); ?>>swap</option>
						<option value="optional" <?php selected('optional', (string) $settings['font_display']); ?>>optional</option>
					</select>
				</p>
				<p>
					<label for="mrn-site-styles-google-fonts-subset"><strong>Subset</strong></label><br />
					<select id="mrn-site-styles-google-fonts-subset" name="<?php echo esc_attr($option_name); ?>[subset]">
						<option value="latin" <?php selected('latin', (string) $settings['subset']); ?>>latin</option>
						<option value="latin-ext" <?php selected('latin-ext', (string) $settings['subset']); ?>>latin-ext</option>
					</select>
				</p>
				<p>
					<label for="mrn-site-styles-google-fonts-bridge"><strong>Bridge mode</strong></label><br />
					<select id="mrn-site-styles-google-fonts-bridge" name="<?php echo esc_attr($option_name); ?>[stack_bridge_mode]">
						<option value="auto" <?php selected('auto', (string) $settings['stack_bridge_mode']); ?>>Auto detect stack</option>
						<option value="standalone" <?php selected('standalone', (string) $settings['stack_bridge_mode']); ?>>Force standalone</option>
						<option value="force_stack" <?php selected('force_stack', (string) $settings['stack_bridge_mode']); ?>>Force stack mode when available</option>
					</select>
				</p>
				<p>
					<label for="mrn-site-styles-google-fonts-notes"><strong>Designer handoff notes</strong></label><br />
					<textarea id="mrn-site-styles-google-fonts-notes" class="large-text" rows="5" name="<?php echo esc_attr($option_name); ?>[designer_notes]"><?php echo esc_textarea((string) $settings['designer_notes']); ?></textarea>
				</p>
			</div>

			<div class="mrn-google-fonts-site-tab-panel" data-mrn-google-fonts-site-tab-panel="stack-status" hidden>
				<p><strong><?php echo esc_html((string) $stack_status['summary']); ?></strong></p>
				<p>Stack detected: <code><?php echo !empty($stack_status['stack_available']) ? 'yes' : 'no'; ?></code></p>
				<p>Site Styles tab extension hook detected: <code><?php echo !empty($stack_status['site_styles_tab_extension_available']) ? 'yes' : 'no'; ?></code></p>
				<p>Runtime mode: <code><?php echo esc_html((string) $stack_status['runtime_mode']); ?></code></p>
			</div>

				<div class="mrn-google-fonts-site-tab-panel" data-mrn-google-fonts-site-tab-panel="import-export" hidden>
					<div class="mrn-site-styles-transfer-box" style="margin-top: 0;">
						<h3 style="margin-top:0;">Import / Export</h3>
						<p>Export selected Site Styles sections for this site to a JSON file, or import any Site Styles sections present in a previously exported bundle.</p>
						<div class="mrn-site-styles-transfer-actions">
							<div>
								<?php wp_nonce_field('mrn_site_styles_export', 'mrn_site_styles_export_nonce'); ?>
								<div class="mrn-site-styles-transfer-sections">
									<strong>Export Sections</strong>
									<?php foreach ($transfer_sections as $section_key => $section_label) : ?>
										<label>
											<input type="checkbox" name="mrn_site_styles_sections[]" value="<?php echo esc_attr((string) $section_key); ?>" checked />
											<span><?php echo esc_html((string) $section_label); ?></span>
										</label>
									<?php endforeach; ?>
								</div>
								<button type="submit" name="mrn_site_styles_export_submit" class="button">Export Site Styles</button>
							</div>

							<div>
								<?php wp_nonce_field('mrn_site_styles_import', 'mrn_site_styles_import_nonce'); ?>
								<label for="mrn-site-styles-import-file">Import JSON</label>
								<input type="file" id="mrn-site-styles-import-file" name="mrn_site_styles_import_file" accept="application/json,.json" />
								<button type="submit" name="mrn_site_styles_import_submit" class="button button-secondary" formenctype="multipart/form-data">Import Site Styles</button>
								<p class="description" style="margin:6px 0 0;">Only the sections present in the JSON will be imported. Missing sections are left unchanged.</p>
							</div>
						</div>
						<p class="description" style="margin-top:10px;">Local built files are not transferred and should be rebuilt from Font Builder after import.</p>
					</div>
				</div>
			<script>
				(function() {
					const root = document.querySelector('.mrn-google-fonts-site-tabs');
					if (!root || root.dataset.mrnGoogleFontsSiteReady === '1') {
						return;
					}
					root.dataset.mrnGoogleFontsSiteReady = '1';

					const triggers = Array.from(root.querySelectorAll('[data-mrn-google-fonts-site-tab-trigger]'));
					const panels = Array.from(root.querySelectorAll('[data-mrn-google-fonts-site-tab-panel]'));
					if (!triggers.length || !panels.length) {
						return;
					}

					const activate = function(tabName) {
						const fallback = root.getAttribute('data-mrn-google-fonts-site-default') || 'font-builder';
						const target = tabName || fallback;

						triggers.forEach(function(trigger) {
							const isActive = trigger.getAttribute('data-mrn-google-fonts-site-tab-trigger') === target;
							trigger.classList.toggle('nav-tab-active', isActive);
							trigger.setAttribute('aria-selected', isActive ? 'true' : 'false');
						});

						panels.forEach(function(panel) {
							const isActive = panel.getAttribute('data-mrn-google-fonts-site-tab-panel') === target;
							panel.hidden = !isActive;
						});
					};

					triggers.forEach(function(trigger) {
						trigger.addEventListener('click', function(event) {
							event.preventDefault();
							activate(trigger.getAttribute('data-mrn-google-fonts-site-tab-trigger'));
						});
					});

					activate(root.getAttribute('data-mrn-google-fonts-site-default') || 'font-builder');
				})();
			</script>
		</div>
		<?php
	}

	/**
	 * Persist Google Fonts settings when saved from the Site Styles tab.
	 *
	 * @param string $submitted_section Active Site Styles tab key.
	 */
	public static function handle_site_styles_save(string $submitted_section): void {
		if (self::SITE_STYLES_TAB_KEY !== sanitize_key($submitted_section)) {
			return;
		}

		$nonce = isset($_POST['mrn_site_colors_nonce'])
			? sanitize_text_field((string) wp_unslash($_POST['mrn_site_colors_nonce']))
			: '';
		if ('' === $nonce || !wp_verify_nonce($nonce, 'mrn_site_colors_save')) {
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Raw settings are sanitized by self::sanitize_settings().
		$raw_input = isset($_POST[self::OPTION_KEY]) && is_array($_POST[self::OPTION_KEY]) ? wp_unslash($_POST[self::OPTION_KEY]) : array();
		$current = self::get_settings();
		$merged = array_replace($current, $raw_input);

		// Backward-compatible fallback for already-open admin pages that still post legacy builder fields.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Builder payload is sanitized by self::sanitize_settings().
		$builder_input = isset($_POST['mrn_google_fonts_builder']) && is_array($_POST['mrn_google_fonts_builder']) ? wp_unslash($_POST['mrn_google_fonts_builder']) : array();
		foreach (array('body_font_family', 'heading_font_family', 'accent_font_family', 'body_font_weights', 'heading_font_weights', 'accent_font_weights', 'body_font_italics', 'heading_font_italics', 'accent_font_italics', 'body_font_targets', 'heading_font_targets', 'accent_font_targets') as $field_key) {
			if (array_key_exists($field_key, $builder_input)) {
				$merged[$field_key] = $builder_input[$field_key];
			}
		}

		$sanitized = self::sanitize_settings($merged);

		update_option(self::OPTION_KEY, $sanitized, false);
	}

	/**
	 * Render a save notice for Site Styles integration.
	 */
	public static function render_site_styles_notice(string $updated_notice): void {
		self::render_request_notice();

		if (self::SITE_STYLES_TAB_KEY !== sanitize_key($updated_notice)) {
			return;
		}
		?>
		<div class="notice notice-success is-dismissible"><p>Google Fonts settings saved.</p></div>
		<?php
	}

	/**
	 * Render settings page.
	 */
	public static function render_settings_page(): void {
		if (!current_user_can('manage_options')) {
			return;
		}

		$settings = self::get_settings();
		$stack_status = MRN_Google_Fonts_Stack_Bridge::get_status((string) $settings['stack_bridge_mode']);
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only tab selection from page URL.
		$tab = isset($_GET['tab']) ? sanitize_key((string) wp_unslash($_GET['tab'])) : 'font-builder';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$tabs = array(
			'font-builder' => 'Font Builder',
			'font-settings' => 'Font Settings',
			'stack-status' => 'Stack Status',
			'import-export' => 'Import|Export',
		);
		if (!isset($tabs[$tab])) {
			$tab = 'font-builder';
		}
		?>
		<div class="wrap">
			<?php self::render_request_notice(); ?>
			<h1>Google Fonts</h1>
			<p>Performance-first Google Fonts runtime across frontend and Classic Editor.</p>
			<h2 class="nav-tab-wrapper mrn-google-fonts-tabs">
				<?php foreach ($tabs as $tab_key => $label) : ?>
					<?php $tab_url = add_query_arg(array('page' => self::PAGE_SLUG, 'tab' => $tab_key), admin_url('options-general.php')); ?>
					<a href="<?php echo esc_url($tab_url); ?>" class="nav-tab <?php echo $tab === $tab_key ? 'nav-tab-active' : ''; ?>">
						<?php echo esc_html($label); ?>
					</a>
				<?php endforeach; ?>
			</h2>
			<div class="mrn-google-fonts-panel">
				<form method="post" action="options.php">
					<?php settings_fields('mrn_google_fonts'); ?>
					<?php self::render_tab_content($tab, $settings, $stack_status); ?>
					<?php if ('import-export' !== $tab) : ?>
						<?php submit_button('Save Settings'); ?>
					<?php endif; ?>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Render tab content.
	 *
	 * @param string               $tab Active tab key.
	 * @param array<string, mixed> $settings Settings payload.
	 * @param array<string, mixed> $stack_status Stack status payload.
	 */
	private static function render_tab_content(string $tab, array $settings, array $stack_status): void {
		$option_name = self::OPTION_KEY;
		$site_styles_url = add_query_arg(
			array(
				'page' => 'mrn-site-styles',
				'updated' => self::SITE_STYLES_TAB_KEY,
			),
			admin_url('options-general.php')
		);

		if ('font-builder' === $tab) {
			?>
			<div class="mrn-google-fonts-status">
				<p><strong>Build Local Fonts for Frontend + Classic Editor</strong></p>
				<ol>
					<li>Set families, weights, and selector assignments in <strong>Google Font Chooser</strong>.</li>
					<li>Save settings.</li>
					<li>Build local files and confirm status is <strong>active</strong>.</li>
				</ol>
				<p class="description">Use <a href="<?php echo esc_url($site_styles_url); ?>">Site Styles -> Google Fonts</a> when working inside stack settings workflows.</p>
			</div>
			<?php
			$google_request = self::build_google_fonts_request($settings);
			if (!empty($google_request['url']) && is_string($google_request['url'])) {
				echo '<div class="mrn-google-fonts-status">';
				echo '<p><strong>Active Google Fonts Request</strong></p>';
				echo '<p><code>' . esc_html($google_request['url']) . '</code></p>';
				echo '</div>';
			}

			self::render_local_builder_controls($settings, 'builder');
			return;
		}

		if ('font-settings' === $tab) {
			?>
			<p class="mrn-google-fonts-field">
				<label>
					<input type="checkbox" name="<?php echo esc_attr($option_name); ?>[enabled]" value="1" <?php checked(!empty($settings['enabled'])); ?> />
					Enable Google Fonts runtime
				</label>
			</p>
			<p class="mrn-google-fonts-field">
				<label>
					<input type="checkbox" name="<?php echo esc_attr($option_name); ?>[load_on_frontend]" value="1" <?php checked(!empty($settings['load_on_frontend'])); ?> />
					Load frontend typography runtime
				</label>
			</p>
			<div class="mrn-google-fonts-field">
				<label>
					<input type="checkbox" name="<?php echo esc_attr($option_name); ?>[load_in_classic_editor]" value="1" <?php checked(!empty($settings['load_in_classic_editor'])); ?> />
					Load in Classic Editor / TinyMCE
				</label>
			</div>
			<div class="mrn-google-fonts-field">
				<label for="mrn-google-fonts-frontend-load-scope">Frontend load scope</label>
				<select id="mrn-google-fonts-frontend-load-scope" name="<?php echo esc_attr($option_name); ?>[frontend_load_scope]">
					<option value="all" <?php selected('all', (string) $settings['frontend_load_scope']); ?>>All frontend requests</option>
					<option value="front_page" <?php selected('front_page', (string) $settings['frontend_load_scope']); ?>>Front page only</option>
					<option value="singular" <?php selected('singular', (string) $settings['frontend_load_scope']); ?>>Singular content only</option>
					<option value="archive" <?php selected('archive', (string) $settings['frontend_load_scope']); ?>>Archive/search/posts index only</option>
					<option value="posts_page" <?php selected('posts_page', (string) $settings['frontend_load_scope']); ?>>Posts index only</option>
				</select>
			</div>
			<?php
			$target_options = self::get_font_target_options();
			$site_styles_owns_typography = self::site_styles_owns_typography($settings);
			$font_slots = array(
				'body' => array(
					'label' => 'Body',
					'family_key' => 'body_font_family',
					'weights_key' => 'body_font_weights',
					'italics_key' => 'body_font_italics',
					'targets_key' => 'body_font_targets',
				),
				'heading' => array(
					'label' => 'Heading',
					'family_key' => 'heading_font_family',
					'weights_key' => 'heading_font_weights',
					'italics_key' => 'heading_font_italics',
					'targets_key' => 'heading_font_targets',
				),
				'accent' => array(
					'label' => 'Accent',
					'family_key' => 'accent_font_family',
					'weights_key' => 'accent_font_weights',
					'italics_key' => 'accent_font_italics',
					'targets_key' => 'accent_font_targets',
				),
			);
			?>
			<?php foreach ($font_slots as $slot_key => $slot_meta) : ?>
				<?php
				$family_key = (string) $slot_meta['family_key'];
				$weights_key = (string) $slot_meta['weights_key'];
				$italics_key = (string) $slot_meta['italics_key'];
				$targets_key = (string) $slot_meta['targets_key'];
				$selected_targets = self::sanitize_font_targets_value($settings[$targets_key] ?? array());
				?>
				<div class="mrn-google-fonts-field">
					<label for="mrn-google-fonts-<?php echo esc_attr($slot_key); ?>-family"><?php echo esc_html((string) $slot_meta['label']); ?> font family</label>
					<input type="text" class="regular-text" id="mrn-google-fonts-<?php echo esc_attr($slot_key); ?>-family" name="<?php echo esc_attr($option_name); ?>[<?php echo esc_attr($family_key); ?>]" value="<?php echo esc_attr((string) ($settings[$family_key] ?? 'system-ui')); ?>" />
				</div>
				<div class="mrn-google-fonts-field">
					<label for="mrn-google-fonts-<?php echo esc_attr($slot_key); ?>-weights"><?php echo esc_html((string) $slot_meta['label']); ?> weights</label>
					<input type="text" class="regular-text" id="mrn-google-fonts-<?php echo esc_attr($slot_key); ?>-weights" name="<?php echo esc_attr($option_name); ?>[<?php echo esc_attr($weights_key); ?>]" value="<?php echo esc_attr((string) ($settings[$weights_key] ?? '400')); ?>" />
				</div>
				<div class="mrn-google-fonts-field">
					<label>
						<input type="hidden" name="<?php echo esc_attr($option_name); ?>[<?php echo esc_attr($italics_key); ?>]" value="0" />
						<input type="checkbox" name="<?php echo esc_attr($option_name); ?>[<?php echo esc_attr($italics_key); ?>]" value="1" <?php checked(!empty($settings[$italics_key])); ?> />
						Include <?php echo esc_html(strtolower((string) $slot_meta['label'])); ?> italic styles
					</label>
				</div>
				<?php if (!$site_styles_owns_typography) : ?>
				<div class="mrn-google-fonts-field">
					<label for="mrn-google-fonts-<?php echo esc_attr($slot_key); ?>-targets"><?php echo esc_html((string) $slot_meta['label']); ?> target selectors</label>
					<input type="hidden" name="<?php echo esc_attr($option_name); ?>[<?php echo esc_attr($targets_key); ?>][]" value="" />
					<select id="mrn-google-fonts-<?php echo esc_attr($slot_key); ?>-targets" name="<?php echo esc_attr($option_name); ?>[<?php echo esc_attr($targets_key); ?>][]" multiple size="6" style="min-width: 320px;">
						<?php foreach ($target_options as $target_key => $target_meta) : ?>
							<option value="<?php echo esc_attr($target_key); ?>" <?php selected(in_array($target_key, $selected_targets, true)); ?>><?php echo esc_html((string) $target_meta['label']); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description">Use Cmd/Ctrl-click to choose multiple selector groups.</p>
				</div>
				<?php endif; ?>
			<?php endforeach; ?>
			<div class="mrn-google-fonts-field">
				<label for="mrn-google-fonts-display">Font display strategy</label>
				<select id="mrn-google-fonts-display" name="<?php echo esc_attr($option_name); ?>[font_display]">
					<option value="swap" <?php selected('swap', (string) $settings['font_display']); ?>>swap</option>
					<option value="optional" <?php selected('optional', (string) $settings['font_display']); ?>>optional</option>
				</select>
			</div>
			<div class="mrn-google-fonts-field">
				<label for="mrn-google-fonts-subset">Subset</label>
				<select id="mrn-google-fonts-subset" name="<?php echo esc_attr($option_name); ?>[subset]">
					<option value="latin" <?php selected('latin', (string) $settings['subset']); ?>>latin</option>
					<option value="latin-ext" <?php selected('latin-ext', (string) $settings['subset']); ?>>latin-ext</option>
				</select>
			</div>
			<div class="mrn-google-fonts-field">
				<label for="mrn-google-fonts-designer-notes">Designer handoff notes</label>
				<textarea class="large-text" rows="6" id="mrn-google-fonts-designer-notes" name="<?php echo esc_attr($option_name); ?>[designer_notes]"><?php echo esc_textarea((string) $settings['designer_notes']); ?></textarea>
			</div>
			<?php
			return;
		}

		if ('stack-status' === $tab) {
			?>
			<p><strong><?php echo esc_html((string) $stack_status['summary']); ?></strong></p>
			<p>Stack detected: <code><?php echo !empty($stack_status['stack_available']) ? 'yes' : 'no'; ?></code></p>
			<p>Site Styles tab extension hook detected: <code><?php echo !empty($stack_status['site_styles_tab_extension_available']) ? 'yes' : 'no'; ?></code></p>
			<p>Runtime mode: <code><?php echo esc_html((string) $stack_status['runtime_mode']); ?></code></p>
		<div class="mrn-google-fonts-field">
			<label for="mrn-google-fonts-bridge-mode">Bridge mode</label>
			<select id="mrn-google-fonts-bridge-mode" name="<?php echo esc_attr($option_name); ?>[stack_bridge_mode]">
				<option value="auto" <?php selected('auto', (string) $settings['stack_bridge_mode']); ?>>Auto detect stack</option>
				<option value="standalone" <?php selected('standalone', (string) $settings['stack_bridge_mode']); ?>>Force standalone</option>
				<option value="force_stack" <?php selected('force_stack', (string) $settings['stack_bridge_mode']); ?>>Force stack mode when available</option>
			</select>
			<p class="description">Use auto for mixed stack/non-stack environments.</p>
		</div>
		<?php
			return;
		}

		?>
		<div class="mrn-google-fonts-status">
			<p><strong>Import|Export is handled in Site Styles.</strong></p>
			<p>Open <a href="<?php echo esc_url($site_styles_url); ?>">Settings -> Site Styles -> Google Fonts</a>, then use the <strong>Import|Export</strong> subtab.</p>
			<p>Select <code>Google Fonts</code> in export sections to include these settings.</p>
			<p class="description">Local built font files are not transferred; rebuild from the Font Builder tab after import.</p>
		</div>
		<?php
	}

	/**
	 * Sanitize font family configuration field.
	 *
	 * @param mixed $value Raw field value.
	 */
	private static function sanitize_font_family_value($value): string {
		$value = trim((string) $value);
		if ('' === $value) {
			return 'system-ui';
		}

		$value = preg_replace('/[^A-Za-z0-9\-\s,_]/', '', $value);
		$value = trim((string) $value);

		return '' === $value ? 'system-ui' : $value;
	}

	/**
	 * Sanitize weights field.
	 *
	 * @param mixed $value Raw field value.
	 */
	private static function sanitize_font_weights_value($value): string {
		$raw_weights = explode(',', (string) $value);
		$weights = array();

		foreach ($raw_weights as $weight) {
			$weight = trim($weight);
			if ('' === $weight) {
				continue;
			}

			$weight = preg_replace('/[^0-9]/', '', $weight);
			if ('' === $weight) {
				continue;
			}

			$weight_int = (int) $weight;
			if ($weight_int < 100 || $weight_int > 900) {
				continue;
			}

			$weights[] = (string) $weight_int;
		}

		$weights = array_values(array_unique($weights));
		if (empty($weights)) {
			return '400';
		}

		return implode(',', $weights);
	}

	/**
	 * Sanitize per-font selector target values.
	 *
	 * @param mixed $value Raw selector targets field.
	 * @return array<int, string>
	 */
	private static function sanitize_font_targets_value($value): array {
		$allowed_targets = array_keys(self::get_font_target_options());
		$targets = array();

		if (is_string($value)) {
			$targets = explode(',', $value);
		} elseif (is_array($value)) {
			$targets = $value;
		}

		$sanitized = array();
		foreach ($targets as $target) {
			$target = sanitize_key((string) $target);
			if ('' === $target || !in_array($target, $allowed_targets, true)) {
				continue;
			}

			$sanitized[] = $target;
		}

		return array_values(array_unique($sanitized));
	}

	/**
	 * Return selector target options for font assignment controls.
	 *
	 * @return array<string, array<string, string>>
	 */
	private static function get_font_target_options(): array {
		return array(
			'body_text' => array(
				'label' => 'Body Text',
				'group' => 'Text',
				'frontend_selector' => 'body',
				'editor_selector' => '.mce-content-body',
			),
			'headings' => array(
				'label' => 'All Headings (H1-H6)',
				'group' => 'Headings',
				'frontend_selector' => 'h1,h2,h3,h4,h5,h6',
				'editor_selector' => '.mce-content-body h1,.mce-content-body h2,.mce-content-body h3,.mce-content-body h4,.mce-content-body h5,.mce-content-body h6',
			),
			'heading_h1' => array(
				'label' => 'H1 only',
				'group' => 'Headings',
				'frontend_selector' => 'h1',
				'editor_selector' => '.mce-content-body h1',
			),
			'heading_h2' => array(
				'label' => 'H2 only',
				'group' => 'Headings',
				'frontend_selector' => 'h2',
				'editor_selector' => '.mce-content-body h2',
			),
			'heading_h3' => array(
				'label' => 'H3 only',
				'group' => 'Headings',
				'frontend_selector' => 'h3',
				'editor_selector' => '.mce-content-body h3',
			),
			'heading_h4' => array(
				'label' => 'H4 only',
				'group' => 'Headings',
				'frontend_selector' => 'h4',
				'editor_selector' => '.mce-content-body h4',
			),
			'heading_h5' => array(
				'label' => 'H5 only',
				'group' => 'Headings',
				'frontend_selector' => 'h5',
				'editor_selector' => '.mce-content-body h5',
			),
			'heading_h6' => array(
				'label' => 'H6 only',
				'group' => 'Headings',
				'frontend_selector' => 'h6',
				'editor_selector' => '.mce-content-body h6',
			),
			'buttons' => array(
				'label' => 'Buttons',
				'group' => 'Interface',
				'frontend_selector' => 'button,.button,input[type="button"],input[type="submit"],input[type="reset"],.wp-block-button__link',
				'editor_selector' => '.mce-content-body .wp-block-button__link,.mce-content-body button',
			),
			'form_controls' => array(
				'label' => 'Form Controls',
				'group' => 'Interface',
				'frontend_selector' => 'input,select,optgroup,textarea',
				'editor_selector' => '.mce-content-body input,.mce-content-body select,.mce-content-body optgroup,.mce-content-body textarea',
			),
			'quotes' => array(
				'label' => 'Blockquotes & Citations',
				'group' => 'Text',
				'frontend_selector' => 'blockquote,blockquote p,blockquote cite',
				'editor_selector' => '.mce-content-body blockquote,.mce-content-body blockquote p,.mce-content-body blockquote cite',
			),
			'navigation' => array(
				'label' => 'Navigation Links',
				'group' => 'Interface',
				'frontend_selector' => '.menu a,.nav a,.wp-block-navigation a',
				'editor_selector' => '.mce-content-body .wp-block-navigation a',
			),
		);
	}

	/**
	 * Sanitize frontend load scope field.
	 *
	 * @param mixed $value Raw scope value.
	 */
	private static function sanitize_frontend_load_scope_value($value): string {
		$scope = sanitize_key((string) $value);
		$allowed_scopes = array('all', 'front_page', 'singular', 'archive', 'posts_page');

		if (!in_array($scope, $allowed_scopes, true)) {
			$scope = 'all';
		}

		return $scope;
	}

	/**
	 * Return a Google Fonts family catalog for admin chooser inputs.
	 *
	 * @return array<int, string>
	 */
	private static function get_google_font_family_catalog(): array {
		$cached = get_transient(self::FONT_CATALOG_TRANSIENT);
		if (is_array($cached) && !empty($cached)) {
			return array_values(array_unique(array_map('strval', $cached)));
		}

		$fetched = self::fetch_google_font_family_catalog();
		if (!empty($fetched)) {
			$fetched = array_values(array_unique(array_filter(array_map('strval', $fetched))));
			set_transient(self::FONT_CATALOG_TRANSIENT, $fetched, DAY_IN_SECONDS);
			return $fetched;
		}

		$fallback = self::get_google_font_fallback_catalog();
		$fallback = array_values(array_unique(array_filter(array_map('strval', $fallback))));
		if (empty($fallback)) {
			$fallback = array('Open Sans', 'Roboto', 'Lato', 'Montserrat', 'Poppins');
		}

		// Keep fallback catalog cache short so temporary fetch failures self-heal quickly.
		set_transient(self::FONT_CATALOG_TRANSIENT, $fallback, self::FONT_CATALOG_FALLBACK_TTL);

		return $fallback;
	}

	/**
	 * Fetch Google Fonts family metadata from the public catalog endpoint.
	 *
	 * @return array<int, string>
	 */
	private static function fetch_google_font_family_catalog(): array {
		$response = wp_remote_get(
			self::FONT_CATALOG_URL,
			array(
				'timeout' => 5,
				'redirection' => 2,
				'headers' => array(
					'User-Agent' => 'Mozilla/5.0 (WordPress; MRN Google Fonts Catalog)',
				),
			)
		);

		if (is_wp_error($response)) {
			return array();
		}

		$status_code = (int) wp_remote_retrieve_response_code($response);
		if ($status_code < 200 || $status_code >= 300) {
			return array();
		}

		$raw_body = (string) wp_remote_retrieve_body($response);
		if ('' === $raw_body) {
			return array();
		}

		$raw_body = ltrim($raw_body);
		if (0 === strpos($raw_body, ")]}'")) {
			$parts = preg_split("/\r\n|\r|\n/", $raw_body, 2);
			$raw_body = isset($parts[1]) ? (string) $parts[1] : '';
		}

		$payload = json_decode($raw_body, true);
		if (!is_array($payload) || empty($payload['familyMetadataList']) || !is_array($payload['familyMetadataList'])) {
			return array();
		}

		$families = array();
		foreach ($payload['familyMetadataList'] as $family_meta) {
			if (!is_array($family_meta) || empty($family_meta['family'])) {
				continue;
			}

			$family = sanitize_text_field((string) $family_meta['family']);
			$family = trim(preg_replace('/\s+/', ' ', $family));
			if ('' === $family || self::is_system_font_family($family)) {
				continue;
			}

			$families[] = $family;
		}

		natcasesort($families);

		return array_values(array_unique(array_filter(array_map('strval', $families))));
	}

	/**
	 * Return family matches for a given query.
	 *
	 * @param array<int, string> $catalog Font family catalog.
	 * @param string             $query Search query.
	 * @param int                $limit Maximum matches.
	 * @return array<int, string>
	 */
	private static function find_font_family_matches(array $catalog, string $query, int $limit = 20): array {
		$query = trim(preg_replace('/\s+/', ' ', $query));
		$query_lower = strtolower($query);
		$starts_with = array();
		$contains = array();
		$limit = max(1, min(100, $limit));

		foreach ($catalog as $family) {
			$family = trim((string) $family);
			if ('' === $family) {
				continue;
			}

			if ('' === $query_lower) {
				$starts_with[] = $family;
				if (count($starts_with) >= $limit) {
					break;
				}
				continue;
			}

			$family_lower = strtolower($family);
			if (0 === strpos($family_lower, $query_lower)) {
				$starts_with[] = $family;
			} elseif (false !== strpos($family_lower, $query_lower)) {
				$contains[] = $family;
			}

			if ((count($starts_with) + count($contains)) >= ($limit * 2)) {
				break;
			}
		}

		$matches = array_merge($starts_with, $contains);
		$matches = array_values(array_unique(array_filter(array_map('strval', $matches))));

		return array_slice($matches, 0, $limit);
	}

	/**
	 * AJAX handler: search Google font families for chooser typeahead.
	 */
	public static function ajax_search_families(): void {
		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => 'Not allowed.'), 403);
		}

		check_ajax_referer('mrn_google_fonts_search_families');

		$query = '';
		if (isset($_POST['q'])) {
			$query = sanitize_text_field((string) wp_unslash($_POST['q']));
		} elseif (isset($_GET['q'])) {
			$query = sanitize_text_field((string) wp_unslash($_GET['q']));
		}
		$catalog = self::get_google_font_family_catalog();
		$matches = self::find_font_family_matches($catalog, $query, 25);

		wp_send_json_success(
			array(
				'families' => $matches,
			)
		);
	}

	/**
	 * Fallback family list used when the live Google catalog is unavailable.
	 *
	 * @return array<int, string>
	 */
	private static function get_google_font_fallback_catalog(): array {
		return array(
			'Open Sans',
			'Roboto',
			'Lato',
			'Montserrat',
			'Poppins',
			'Source Sans 3',
			'Nunito',
			'Inter',
			'Raleway',
			'Work Sans',
			'Oswald',
			'Merriweather',
			'Playfair Display',
			'PT Sans',
			'Rubik',
			'Nunito Sans',
			'Fira Sans',
			'Manrope',
			'Cabin',
			'Barlow',
			'Archivo',
			'Bebas Neue',
			'DM Sans',
			'Karla',
			'Libre Baskerville',
			'Lora',
			'Crimson Pro',
			'Cormorant Garamond',
			'Prompt',
			'Mulish',
			'Noto Sans',
			'Noto Serif',
			'Quicksand',
			'Space Grotesk',
			'Space Mono',
			'IBM Plex Sans',
			'IBM Plex Serif',
			'Inconsolata',
			'Bitter',
			'Anton',
			'Yanone Kaffeesatz',
			'Overpass',
			'Asap',
			'Heebo',
			'Titillium Web',
			'Ubuntu',
			'Josefin Sans',
			'Public Sans',
		);
	}

	/**
	 * Determine if the configured frontend runtime should load for this request.
	 *
	 * @param array<string, mixed> $settings Plugin settings.
	 */
	private static function should_load_frontend_runtime(array $settings): bool {
		if (is_admin() || empty($settings['enabled']) || empty($settings['load_on_frontend'])) {
			return false;
		}

		$scope = self::sanitize_frontend_load_scope_value($settings['frontend_load_scope'] ?? 'all');
		$should_load = self::is_frontend_scope_match($scope);

		/**
		 * Filter whether Google Fonts should load on the current frontend request.
		 *
		 * @param bool                $should_load Whether runtime should load.
		 * @param string              $scope       Configured frontend scope.
		 * @param array<string, mixed> $settings   Plugin settings.
		 */
		return (bool) apply_filters('mrn_google_fonts_should_load_frontend', $should_load, $scope, $settings);
	}

	/**
	 * Match request context against configured frontend scope.
	 */
	private static function is_frontend_scope_match(string $scope): bool {
		if ('front_page' === $scope) {
			return is_front_page();
		}

		if ('singular' === $scope) {
			return is_singular();
		}

		if ('archive' === $scope) {
			return is_archive() || is_home() || is_search();
		}

		if ('posts_page' === $scope) {
			return is_home();
		}

		return true;
	}

	/**
	 * Build escaped font stack for CSS insertion.
	 */
	private static function build_font_stack(string $font_family): string {
		$font_family = trim($font_family);
		if ('' === $font_family || 'system-ui' === strtolower($font_family)) {
			return 'system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif';
		}

		// Single custom family plus system fallbacks.
		$family = str_replace(array('"', "'"), '', $font_family);
		$family = trim($family);
		$family = preg_replace('/\s+/', ' ', $family);

		if (false !== strpos($family, ',')) {
			$parts = array_filter(array_map('trim', explode(',', $family)));
			$family = (string) reset($parts);
		}

		return '"' . $family . '",system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif';
	}

	/**
	 * Build runtime CSS that maps configured font slots to selector targets.
	 *
	 * @param array<string, mixed> $settings Plugin settings.
	 * @param string               $context  Target context: frontend|editor.
	 */
	private static function build_font_target_css(array $settings, string $context): string {
		if (self::site_styles_owns_typography($settings)) {
			return '';
		}

		$context = ('editor' === $context) ? 'editor' : 'frontend';
		$selector_key = ('editor' === $context) ? 'editor_selector' : 'frontend_selector';
		$defaults = self::default_settings();
		$target_options = self::get_font_target_options();
		$font_slots = array(
			array(
				'targets_key' => 'body_font_targets',
				'css_var' => '--mrn-font-body',
			),
			array(
				'targets_key' => 'heading_font_targets',
				'css_var' => '--mrn-font-heading',
			),
			array(
				'targets_key' => 'accent_font_targets',
				'css_var' => '--mrn-font-accent',
			),
		);

		$css = '';
		foreach ($font_slots as $slot) {
			$targets_key = (string) $slot['targets_key'];
			$targets = self::sanitize_font_targets_value($settings[$targets_key] ?? ($defaults[$targets_key] ?? array()));
			if (empty($targets)) {
				continue;
			}

			$selectors = array();
			foreach ($targets as $target_key) {
				if (!isset($target_options[$target_key][$selector_key])) {
					continue;
				}

				$selector = trim((string) $target_options[$target_key][$selector_key]);
				if ('' === $selector) {
					continue;
				}

				$selectors[] = $selector;
			}

			if (empty($selectors)) {
				continue;
			}

			$css .= implode(',', array_values(array_unique($selectors))) . '{font-family:var(' . $slot['css_var'] . ');}';
		}

		return $css;
	}

	/**
	 * Build Google Fonts CSS2 request data for configured families.
	 *
	 * @param array<string, mixed> $settings Plugin settings.
	 * @return array<string, mixed>
	 */
	private static function build_google_fonts_request(array $settings): array {
		$families = array();
		$defaults = self::default_settings();

		$slot_configs = array(
			array(
				'family_key' => 'body_font_family',
				'weights_key' => 'body_font_weights',
				'italics_key' => 'body_font_italics',
				'targets_key' => 'body_font_targets',
				'default_weights' => '400',
			),
			array(
				'family_key' => 'heading_font_family',
				'weights_key' => 'heading_font_weights',
				'italics_key' => 'heading_font_italics',
				'targets_key' => 'heading_font_targets',
				'default_weights' => '700',
			),
			array(
				'family_key' => 'accent_font_family',
				'weights_key' => 'accent_font_weights',
				'italics_key' => 'accent_font_italics',
				'targets_key' => 'accent_font_targets',
				'default_weights' => '400',
			),
		);

		foreach ($slot_configs as $slot) {
			$targets_key = (string) $slot['targets_key'];
			$targets = self::sanitize_font_targets_value($settings[$targets_key] ?? ($defaults[$targets_key] ?? array()));
			if (empty($targets)) {
				continue;
			}

			$family_key = (string) $slot['family_key'];
			$weights_key = (string) $slot['weights_key'];
			$italics_key = (string) $slot['italics_key'];
			$default_weights = (string) $slot['default_weights'];

			self::collect_google_font_family_request(
				(string) ($settings[$family_key] ?? ''),
				(string) ($settings[$weights_key] ?? $default_weights),
				!empty($settings[$italics_key]),
				$families
			);
		}

		$families = self::apply_configured_google_font_faces($families, $settings);

		/**
		 * Filter normalized Google Fonts face requests before the CSS2 URL is built.
		 *
		 * Return an array keyed by family name. Each family can define normal and italic
		 * weight lists, for example:
		 *
		 * array(
		 *     'Source Sans 3' => array(
		 *         'normal' => array(300, 400, 600, 700),
		 *         'italic' => array(),
		 *     ),
		 *     'Lora' => array(
		 *         'normal' => array(600, 700),
		 *         'italic' => array(600, 700),
		 *     ),
		 * )
		 *
		 * Existing weight and italic settings are converted into this shape first, so
		 * sites that do not use the filter keep the previous request behavior.
		 *
		 * @param array<string, array<string, array<int, string>>> $families Normalized family face map.
		 * @param array<string, mixed>                             $settings Plugin settings.
		 */
		$families = apply_filters('mrn_google_fonts_family_faces', $families, $settings);
		$families = self::sanitize_google_font_faces_config($families, true);

		if (empty($families)) {
			return array(
				'url' => '',
				'families' => array(),
			);
		}

		$families = array_slice($families, 0, 3, true);
		$query_parts = array();

		foreach ($families as $family => $family_config) {
			$normal_weights = self::normalize_google_font_weight_values($family_config['normal'] ?? array(), 4, true);
			$italic_weights = self::normalize_google_font_weight_values($family_config['italic'] ?? array(), 4, true);
			if (empty($normal_weights) && empty($italic_weights)) {
				$normal_weights = array('400');
			}

			$family_param = str_replace('%20', '+', rawurlencode(trim((string) $family)));
			if (!empty($italic_weights)) {
				$tuples = array();
				foreach ($normal_weights as $weight_value) {
					$tuples[] = '0,' . $weight_value;
				}
				foreach ($italic_weights as $weight_value) {
					$tuples[] = '1,' . $weight_value;
				}
				$query_parts[] = 'family=' . $family_param . ':ital,wght@' . implode(';', $tuples);
			} else {
				$query_parts[] = 'family=' . $family_param . ':wght@' . implode(';', $normal_weights);
			}
		}

		$display = sanitize_key((string) ($settings['font_display'] ?? 'swap'));
		if (!in_array($display, array('swap', 'optional'), true)) {
			$display = 'swap';
		}

		$query_parts[] = 'display=' . rawurlencode($display);
		$url = 'https://fonts.googleapis.com/css2?' . implode('&', $query_parts);

		return array(
			'url' => $url,
			'families' => array_keys($families),
		);
	}

	/**
	 * Collect one family request into a normalized map.
	 *
	 * @param string                                                                                     $font_family Raw family setting.
	 * @param string                                                                                     $weights_raw Raw weights setting.
	 * @param bool                                                                                       $include_italics Whether to include italic tuples for this family.
	 * @param array<string, array{normal: array<int, string>, italic: array<int, string>}> $families Aggregated map.
	 */
	private static function collect_google_font_family_request(string $font_family, string $weights_raw, bool $include_italics, array &$families): void {
		$family = self::normalize_primary_family_name($font_family);
		if ('' === $family || self::is_system_font_family($family)) {
			return;
		}
		$family = self::resolve_catalog_family_name($family);

		$weights = array_filter(array_map('trim', explode(',', self::sanitize_font_weights_value($weights_raw))));
		if (empty($weights)) {
			$weights = array('400');
		}

		if (!isset($families[$family])) {
			$families[$family] = array(
				'normal' => array(),
				'italic' => array(),
			);
		}

		foreach ($weights as $weight) {
			$families[$family]['normal'][] = $weight;
		}

		if ($include_italics) {
			foreach ($weights as $weight) {
				$families[$family]['italic'][] = $weight;
			}
		}
	}

	/**
	 * Apply optional settings-level family face overrides.
	 *
	 * @param array<string, array<string, array<int, string>>> $families Aggregated family face map.
	 * @param array<string, mixed>                             $settings Plugin settings.
	 * @return array<string, array<string, array<int, string>>>
	 */
	private static function apply_configured_google_font_faces(array $families, array $settings): array {
		$configured_faces = self::sanitize_google_font_faces_config($settings['font_faces'] ?? array(), true);
		if (empty($configured_faces)) {
			return $families;
		}

		foreach ($configured_faces as $family => $faces) {
			$existing_key = self::find_google_font_family_key($families, (string) $family);
			$target_key = ('' !== $existing_key) ? $existing_key : (string) $family;
			$families[$target_key] = $faces;
		}

		return $families;
	}

	/**
	 * Sanitize a family keyed face map.
	 *
	 * @param mixed $value Raw face config.
	 * @param bool  $resolve_catalog Whether to resolve family names against the Google catalog.
	 * @return array<string, array{normal: array<int, string>, italic: array<int, string>}>
	 */
	private static function sanitize_google_font_faces_config($value, bool $resolve_catalog = true): array {
		if (!is_array($value)) {
			return array();
		}

		$families = array();
		foreach ($value as $family_key => $face_config) {
			$family = is_string($family_key) ? $family_key : '';
			if ('' === $family && is_array($face_config) && isset($face_config['family'])) {
				$family = (string) $face_config['family'];
			}

			$family = self::normalize_primary_family_name(self::sanitize_font_family_value($family));
			if ('' === $family || self::is_system_font_family($family)) {
				continue;
			}

			if ($resolve_catalog) {
				$family = self::resolve_catalog_family_name($family);
			}

			$faces = self::normalize_google_font_faces_value($face_config);
			if (empty($faces['normal']) && empty($faces['italic'])) {
				continue;
			}

			$existing_key = self::find_google_font_family_key($families, $family);
			$target_key = ('' !== $existing_key) ? $existing_key : $family;
			if (!isset($families[$target_key])) {
				$families[$target_key] = array(
					'normal' => array(),
					'italic' => array(),
				);
			}

			$families[$target_key]['normal'] = array_merge($families[$target_key]['normal'], $faces['normal']);
			$families[$target_key]['italic'] = array_merge($families[$target_key]['italic'], $faces['italic']);
		}

		foreach ($families as $family => $faces) {
			$families[$family]['normal'] = self::normalize_google_font_weight_values($faces['normal'] ?? array(), 0, false);
			$families[$family]['italic'] = self::normalize_google_font_weight_values($faces['italic'] ?? array(), 0, false);
		}

		return $families;
	}

	/**
	 * Normalize one family's face configuration.
	 *
	 * @param mixed $value Raw per-family face config.
	 * @return array{normal: array<int, string>, italic: array<int, string>}
	 */
	private static function normalize_google_font_faces_value($value): array {
		$faces = array(
			'normal' => array(),
			'italic' => array(),
		);

		if (is_string($value) || is_int($value)) {
			$faces['normal'] = self::normalize_google_font_weight_values($value, 0, false);
			return $faces;
		}

		if (!is_array($value)) {
			return $faces;
		}

		if (isset($value['normal'])) {
			$faces['normal'] = array_merge($faces['normal'], self::normalize_google_font_weight_values($value['normal'], 0, false));
		}

		if (isset($value['italic'])) {
			$faces['italic'] = array_merge($faces['italic'], self::normalize_google_font_weight_values($value['italic'], 0, false));
		}

		if (isset($value['weights'])) {
			$weights = self::normalize_google_font_weight_values($value['weights'], 0, false);
			$faces['normal'] = array_merge($faces['normal'], $weights);
			if (!empty($value['italics'])) {
				$faces['italic'] = array_merge($faces['italic'], $weights);
			}
		}

		if (isset($value['faces']) && is_array($value['faces'])) {
			foreach ($value['faces'] as $face) {
				if (!is_array($face)) {
					continue;
				}

				$style = isset($face['style']) ? strtolower(trim((string) $face['style'])) : 'normal';
				$style = ('italic' === $style || '1' === $style) ? 'italic' : 'normal';
				$weight = self::normalize_google_font_weight_values($face['weight'] ?? '', 1, false);
				if (empty($weight)) {
					continue;
				}

				$faces[$style][] = $weight[0];
			}
		}

		// Numeric arrays like array(300, 400) are treated as normal weights.
		if (!isset($value['normal'], $value['italic'], $value['weights'], $value['faces'])) {
			$faces['normal'] = array_merge($faces['normal'], self::normalize_google_font_weight_values($value, 0, false));
		}

		$faces['normal'] = self::normalize_google_font_weight_values($faces['normal'], 0, false);
		$faces['italic'] = self::normalize_google_font_weight_values($faces['italic'], 0, false);

		return $faces;
	}

	/**
	 * Normalize weight lists while preserving the caller's first-seen order.
	 *
	 * @param mixed $value Raw weight list.
	 * @param int   $limit Maximum number of weights to return. Zero means no limit.
	 * @param bool  $sort Whether to sort numerically after applying uniqueness and limit.
	 * @return array<int, string>
	 */
	private static function normalize_google_font_weight_values($value, int $limit = 0, bool $sort = false): array {
		if (is_string($value)) {
			$raw_weights = preg_split('/[\s,;]+/', $value);
		} elseif (is_array($value)) {
			$raw_weights = $value;
		} else {
			$raw_weights = array($value);
		}

		$weights = array();
		foreach ((array) $raw_weights as $weight) {
			if (is_array($weight)) {
				continue;
			}

			$weight = preg_replace('/[^0-9]/', '', (string) $weight);
			if ('' === $weight) {
				continue;
			}

			$weight_int = (int) $weight;
			if ($weight_int < 100 || $weight_int > 900) {
				continue;
			}

			$weight = (string) $weight_int;
			if (!in_array($weight, $weights, true)) {
				$weights[] = $weight;
			}
		}

		if ($limit > 0) {
			$weights = array_slice($weights, 0, $limit);
		}

		if ($sort) {
			sort($weights, SORT_NUMERIC);
		}

		return $weights;
	}

	/**
	 * Find an existing family map key by normalized family name.
	 *
	 * @param array<string, mixed> $families Family map.
	 */
	private static function find_google_font_family_key(array $families, string $family): string {
		$needle = self::normalize_google_font_family_lookup_key($family);
		if ('' === $needle) {
			return '';
		}

		foreach (array_keys($families) as $existing_family) {
			if ($needle === self::normalize_google_font_family_lookup_key((string) $existing_family)) {
				return (string) $existing_family;
			}
		}

		return '';
	}

	/**
	 * Normalize family names for case-insensitive matching.
	 */
	private static function normalize_google_font_family_lookup_key(string $family): string {
		$family = strtolower(str_replace(array('-', '_'), ' ', $family));
		return trim(preg_replace('/\s+/', ' ', $family));
	}

	/**
	 * Resolve a typed family name to the catalog canonical family when possible.
	 */
	private static function resolve_catalog_family_name(string $family): string {
		$family = trim(preg_replace('/\s+/', ' ', $family));
		if ('' === $family) {
			return '';
		}

		$catalog = self::get_google_font_family_catalog();
		$candidate = strtolower(str_replace(array('-', '_'), ' ', $family));
		$candidate = trim(preg_replace('/\s+/', ' ', $candidate));

		foreach ($catalog as $catalog_family) {
			$catalog_name = trim((string) $catalog_family);
			if ('' === $catalog_name) {
				continue;
			}

			$normalized_catalog = strtolower(str_replace(array('-', '_'), ' ', $catalog_name));
			$normalized_catalog = trim(preg_replace('/\s+/', ' ', $normalized_catalog));
			if ($candidate === $normalized_catalog) {
				return $catalog_name;
			}
		}

		return $family;
	}

	/**
	 * Normalize configured family value to a single primary family.
	 */
	private static function normalize_primary_family_name(string $font_family): string {
		$font_family = trim(str_replace(array('"', "'"), '', $font_family));
		if ('' === $font_family) {
			return '';
		}

		if (false !== strpos($font_family, ',')) {
			$parts = array_filter(array_map('trim', explode(',', $font_family)));
			$font_family = (string) reset($parts);
		}

		return trim(preg_replace('/\s+/', ' ', $font_family));
	}

	/**
	 * Determine whether a family should be treated as local/system fallback.
	 */
	private static function is_system_font_family(string $family): bool {
		$system_aliases = array(
			'system-ui',
			'-apple-system',
			'blinkmacsystemfont',
			'sans-serif',
			'serif',
			'monospace',
			'cursive',
			'fantasy',
			'inherit',
		);

		return in_array(strtolower(trim($family)), $system_aliases, true);
	}

	/**
	 * Check resource hint arrays for existing URL entries.
	 *
	 * @param array<int|string, mixed> $hints Hints list.
	 * @param string                   $url URL to test.
	 */
	private static function hints_contain_url(array $hints, string $url): bool {
		$target = self::normalize_resource_hint_origin($url);
		if ('' === $target) {
			return false;
		}

		foreach ($hints as $hint) {
			$href = '';
			if (is_string($hint)) {
				$href = $hint;
			} elseif (is_array($hint) && isset($hint['href'])) {
				$href = (string) $hint['href'];
			}

			if ('' === $href) {
				continue;
			}

			if (self::normalize_resource_hint_origin($href) === $target) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Normalize a resource hint URL to a comparable origin.
	 */
	private static function normalize_resource_hint_origin(string $url): string {
		$url = trim($url);
		if ('' === $url) {
			return '';
		}

		if (0 === strpos($url, '//')) {
			$url = 'https:' . $url;
		} elseif (false === strpos($url, '://')) {
			$url = 'https://' . ltrim($url, '/');
		}

		$parts = wp_parse_url($url);
		if (!is_array($parts) || empty($parts['host'])) {
			return '';
		}

		$scheme = isset($parts['scheme']) ? strtolower((string) $parts['scheme']) : 'https';
		$host = strtolower((string) $parts['host']);
		$port = isset($parts['port']) ? ':' . (string) $parts['port'] : '';

		return $scheme . '://' . $host . $port;
	}
}
