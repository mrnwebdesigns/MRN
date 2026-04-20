<?php
/**
 * MRN Google Fonts plugin bootstrap and settings.
 */

if (!defined('ABSPATH')) {
	exit;
}

final class MRN_Google_Fonts {
	const VERSION = '0.4.14';
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
		add_filter('tiny_mce_before_init', array(__CLASS__, 'inject_tinymce_content_style'), 20);
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
			'body_font_weights' => '400',
			'heading_font_weights' => '600,700',
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
		$sanitized['body_font_weights'] = self::sanitize_font_weights_value($input['body_font_weights'] ?? $defaults['body_font_weights']);
		$sanitized['heading_font_weights'] = self::sanitize_font_weights_value($input['heading_font_weights'] ?? $defaults['heading_font_weights']);

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
				'.mrn-google-fonts-tabs{margin-top:16px}.mrn-google-fonts-panel{max-width:980px;padding:16px 20px;background:#fff;border:1px solid #dcdcde;border-top:none}.mrn-google-fonts-field{margin:0 0 14px}.mrn-google-fonts-field label{display:block;margin-bottom:6px;font-weight:600}.mrn-google-fonts-field .description{margin-top:4px;color:#50575e}.mrn-google-fonts-status{margin:0 0 14px;padding:12px;border-left:4px solid #2271b1;background:#f0f6fc}.mrn-google-fonts-chooser-grid{display:grid;grid-template-columns:repeat(2,minmax(220px,1fr));gap:14px}.mrn-google-fonts-chooser-grid input[type="text"]{width:100%}@media (max-width:782px){.mrn-google-fonts-chooser-grid{grid-template-columns:1fr}}'
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

		$css = ':root{--mrn-font-body:' . $body_stack . ';--mrn-font-heading:' . $heading_stack . ';}';
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

		$body_stack = self::build_font_stack((string) $plugin_settings['body_font_family']);
		$heading_stack = self::build_font_stack((string) $plugin_settings['heading_font_family']);
		$css = ':root{--mrn-font-body:' . $body_stack . ';--mrn-font-heading:' . $heading_stack . ';}.mce-content-body{font-family:var(--mrn-font-body)}.mce-content-body h1,.mce-content-body h2,.mce-content-body h3,.mce-content-body h4,.mce-content-body h5,.mce-content-body h6{font-family:var(--mrn-font-heading)}';

		$existing = isset($settings['content_style']) ? (string) $settings['content_style'] : '';
		$settings['content_style'] = trim($existing . ' ' . $css);

		return $settings;
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
			foreach (array('body_font_family', 'heading_font_family', 'body_font_weights', 'heading_font_weights') as $field_key) {
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
		$is_site_styles_context = ('site_styles' === $context);

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

		$catalog = self::get_google_font_family_catalog();
		$body_family = self::normalize_primary_family_name((string) ($settings['body_font_family'] ?? 'system-ui'));
		$heading_family = self::normalize_primary_family_name((string) ($settings['heading_font_family'] ?? 'system-ui'));
		$body_weights = self::sanitize_font_weights_value((string) ($settings['body_font_weights'] ?? '400'));
		$heading_weights = self::sanitize_font_weights_value((string) ($settings['heading_font_weights'] ?? '600,700'));

		if ('' === $body_family) {
			$body_family = 'system-ui';
		}
		if ('' === $heading_family) {
			$heading_family = 'system-ui';
		}

		if (!self::is_system_font_family($body_family) && !in_array($body_family, $catalog, true)) {
			$catalog[] = $body_family;
		}
		if (!self::is_system_font_family($heading_family) && !in_array($heading_family, $catalog, true)) {
			$catalog[] = $heading_family;
		}

		natcasesort($catalog);
		$catalog = array_values(array_unique(array_filter(array_map('strval', $catalog))));
		$initial_catalog = array_values(array_unique(array_merge(array($body_family, $heading_family), $catalog)));

		$context_slug = sanitize_html_class($context);
		$chooser_id = 'mrn-google-fonts-chooser-' . $context_slug;
		$datalist_id = 'mrn-google-fonts-family-catalog-' . $context_slug;
		$body_family_input_id = 'mrn-google-fonts-builder-body-family-' . $context_slug;
		$heading_family_input_id = 'mrn-google-fonts-builder-heading-family-' . $context_slug;
		$body_weights_input_id = 'mrn-google-fonts-builder-body-weights-' . $context_slug;
		$heading_weights_input_id = 'mrn-google-fonts-builder-heading-weights-' . $context_slug;
		$search_url = admin_url('admin-ajax.php');
		$search_nonce = wp_create_nonce('mrn_google_fonts_search_families');

		$body_family_name = $option_name . '[body_font_family]';
		$heading_family_name = $option_name . '[heading_font_family]';
		$body_weights_name = $option_name . '[body_font_weights]';
		$heading_weights_name = $option_name . '[heading_font_weights]';
		?>
		<div
			id="<?php echo esc_attr($chooser_id); ?>"
			class="mrn-google-fonts-status"
			data-mrn-google-fonts-search-url="<?php echo esc_url($search_url); ?>"
			data-mrn-google-fonts-search-nonce="<?php echo esc_attr($search_nonce); ?>"
			data-mrn-google-fonts-datalist-id="<?php echo esc_attr($datalist_id); ?>"
		>
			<p><strong>Google Font Chooser</strong></p>
			<div class="mrn-google-fonts-chooser-grid">
				<div class="mrn-google-fonts-field" style="margin:0;">
					<label for="<?php echo esc_attr($body_family_input_id); ?>">Body family</label>
					<input
						type="text"
						class="regular-text"
						id="<?php echo esc_attr($body_family_input_id); ?>"
						list="<?php echo esc_attr($datalist_id); ?>"
						value="<?php echo esc_attr($body_family); ?>"
						placeholder="system-ui or Google family"
						data-mrn-google-fonts-family-input="1"
						<?php if ('' !== $body_family_name) : ?>
							name="<?php echo esc_attr($body_family_name); ?>"
						<?php endif; ?>
					/>
					<p class="description" style="margin:4px 0 0;">Set to <code>system-ui</code> to avoid remote font loading for body text.</p>
				</div>

				<div class="mrn-google-fonts-field" style="margin:0;">
					<label for="<?php echo esc_attr($heading_family_input_id); ?>">Heading family</label>
					<input
						type="text"
						class="regular-text"
						id="<?php echo esc_attr($heading_family_input_id); ?>"
						list="<?php echo esc_attr($datalist_id); ?>"
						value="<?php echo esc_attr($heading_family); ?>"
						placeholder="system-ui or Google family"
						data-mrn-google-fonts-family-input="1"
						<?php if ('' !== $heading_family_name) : ?>
							name="<?php echo esc_attr($heading_family_name); ?>"
						<?php endif; ?>
					/>
					<p class="description" style="margin:4px 0 0;">Keep body + heading to a maximum of two families for performance.</p>
				</div>

				<div class="mrn-google-fonts-field" style="margin:0;">
					<label for="<?php echo esc_attr($body_weights_input_id); ?>">Body weights</label>
					<input
						type="text"
						class="regular-text code"
						id="<?php echo esc_attr($body_weights_input_id); ?>"
						value="<?php echo esc_attr($body_weights); ?>"
						placeholder="400"
						<?php if ('' !== $body_weights_name) : ?>
							name="<?php echo esc_attr($body_weights_name); ?>"
						<?php endif; ?>
					/>
					<p class="description" style="margin:4px 0 0;">Comma-separated numeric weights, for example <code>400,500</code>.</p>
				</div>

				<div class="mrn-google-fonts-field" style="margin:0;">
					<label for="<?php echo esc_attr($heading_weights_input_id); ?>">Heading weights</label>
					<input
						type="text"
						class="regular-text code"
						id="<?php echo esc_attr($heading_weights_input_id); ?>"
						value="<?php echo esc_attr($heading_weights); ?>"
						placeholder="600,700"
						<?php if ('' !== $heading_weights_name) : ?>
							name="<?php echo esc_attr($heading_weights_name); ?>"
						<?php endif; ?>
					/>
					<p class="description" style="margin:4px 0 0;">Use only weights needed by the design to keep file size low.</p>
				</div>
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

		$css_url = isset($manifest['css_url']) ? esc_url_raw((string) $manifest['css_url']) : '';
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
		$manifest_signature = isset($manifest['signature']) ? (string) $manifest['signature'] : '';
		if ('' === $signature || '' === $manifest_signature || !hash_equals($manifest_signature, $signature)) {
			return false;
		}

		$css_path = isset($manifest['css_path']) ? (string) $manifest['css_path'] : '';
		if ('' !== $css_path && !file_exists($css_path)) {
			return false;
		}

		return !empty($manifest['css_url']);
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
		$response = wp_remote_get(
			$request_url,
			array(
				'timeout' => 20,
				'redirection' => 3,
				'reject_unsafe_urls' => true,
				'headers' => array(
					'User-Agent' => 'Mozilla/5.0 (WordPress; MRN Google Fonts Local Builder)',
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
				<p>Font families and weights are edited in <strong>Font Builder</strong> to avoid duplicate save fields in Site Styles.</p>
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
		foreach (array('body_font_family', 'heading_font_family', 'body_font_weights', 'heading_font_weights') as $field_key) {
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
					<li>Set families and weights in <strong>Font Settings</strong>.</li>
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
			<div class="mrn-google-fonts-field">
				<label for="mrn-google-fonts-body-family">Body font family</label>
				<input type="text" class="regular-text" id="mrn-google-fonts-body-family" name="<?php echo esc_attr($option_name); ?>[body_font_family]" value="<?php echo esc_attr((string) $settings['body_font_family']); ?>" />
			</div>
			<div class="mrn-google-fonts-field">
				<label for="mrn-google-fonts-heading-family">Heading font family</label>
				<input type="text" class="regular-text" id="mrn-google-fonts-heading-family" name="<?php echo esc_attr($option_name); ?>[heading_font_family]" value="<?php echo esc_attr((string) $settings['heading_font_family']); ?>" />
			</div>
			<div class="mrn-google-fonts-field">
				<label for="mrn-google-fonts-body-weights">Body weights</label>
				<input type="text" class="regular-text" id="mrn-google-fonts-body-weights" name="<?php echo esc_attr($option_name); ?>[body_font_weights]" value="<?php echo esc_attr((string) $settings['body_font_weights']); ?>" />
			</div>
			<div class="mrn-google-fonts-field">
				<label for="mrn-google-fonts-heading-weights">Heading weights</label>
				<input type="text" class="regular-text" id="mrn-google-fonts-heading-weights" name="<?php echo esc_attr($option_name); ?>[heading_font_weights]" value="<?php echo esc_attr((string) $settings['heading_font_weights']); ?>" />
			</div>
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
	 * Build Google Fonts CSS2 request data for configured families.
	 *
	 * @param array<string, mixed> $settings Plugin settings.
	 * @return array<string, mixed>
	 */
	private static function build_google_fonts_request(array $settings): array {
		$families = array();

		self::collect_google_font_family_request(
			(string) ($settings['body_font_family'] ?? ''),
			(string) ($settings['body_font_weights'] ?? '400'),
			$families
		);
		self::collect_google_font_family_request(
			(string) ($settings['heading_font_family'] ?? ''),
			(string) ($settings['heading_font_weights'] ?? '700'),
			$families
		);

		if (empty($families)) {
			return array(
				'url' => '',
				'families' => array(),
			);
		}

		$families = array_slice($families, 0, 2, true);
		$query_parts = array();

		foreach ($families as $family => $weights) {
			$weight_values = array_slice(array_values(array_unique(array_filter($weights))), 0, 4);
			sort($weight_values, SORT_NUMERIC);
			if (empty($weight_values)) {
				$weight_values = array('400');
			}

			$family_param = str_replace('%20', '+', rawurlencode(trim((string) $family)));
			$query_parts[] = 'family=' . $family_param . ':wght@' . implode(';', $weight_values);
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
	 * @param string                       $font_family Raw family setting.
	 * @param string                       $weights_raw Raw weights setting.
	 * @param array<string, array<string>> $families Aggregated map.
	 */
	private static function collect_google_font_family_request(string $font_family, string $weights_raw, array &$families): void {
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
			$families[$family] = array();
		}

		foreach ($weights as $weight) {
			$families[$family][] = $weight;
		}
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
		foreach ($hints as $hint) {
			if (is_string($hint) && $hint === $url) {
				return true;
			}

			if (is_array($hint) && isset($hint['href']) && $hint['href'] === $url) {
				return true;
			}
		}

		return false;
	}
}
