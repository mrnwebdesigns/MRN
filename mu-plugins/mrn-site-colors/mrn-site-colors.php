<?php
/**
 * Plugin Name: Site Styles (MU)
 * Description: Adds a Site Styles configuration page for shared color variables, graphic elements, and usage helpers.
 * Author: MRN Web Designs
 * Version: 0.1.7
 */

defined('ABSPATH') || exit;

/**
 * Option key for stored site colors.
 */
function mrn_site_colors_option_key(): string {
    return 'mrn_site_colors';
}

/**
 * Option key for stored graphic element definitions.
 */
function mrn_site_styles_graphic_elements_option_key(): string {
    return 'mrn_site_graphic_elements';
}

/**
 * Option key for stored dark scroll card motion presets.
 */
function mrn_site_styles_dark_scroll_card_presets_option_key(): string {
    return 'mrn_site_dark_scroll_card_presets';
}

/**
 * Return the supported Site Styles transfer sections.
 *
 * @return array<string, string>
 */
function mrn_site_styles_get_transfer_sections(): array {
    $sections = array(
        'colors' => 'Site Colors',
        'graphic_elements' => 'Graphic Elements',
        'dark_scroll_card_presets' => 'Motion Presets',
    );

    $filtered_sections = apply_filters('mrn_site_styles_transfer_sections', $sections);
    if (!is_array($filtered_sections)) {
        return $sections;
    }

    $normalized = array();
    foreach ($filtered_sections as $section_key => $section_label) {
        $normalized_key = sanitize_key((string) $section_key);
        $normalized_label = sanitize_text_field((string) $section_label);

        if ('' === $normalized_key || '' === $normalized_label || isset($normalized[$normalized_key])) {
            continue;
        }

        $normalized[$normalized_key] = $normalized_label;
    }

    return $normalized;
}

/**
 * Return the core Site Styles admin tabs.
 *
 * @return array<int, array<string, string>>
 */
function mrn_site_styles_get_core_tabs(): array {
    return array(
        array(
            'key' => 'colors',
            'label' => 'Site Colors',
            'icon' => 'dashicons-art',
        ),
        array(
            'key' => 'graphic-elements',
            'label' => 'Graphic Elements',
            'icon' => 'dashicons-format-image',
        ),
        array(
            'key' => 'motion-presets',
            'label' => 'Motion Presets',
            'icon' => 'dashicons-controls-repeat',
        ),
    );
}

/**
 * Return Site Styles admin tabs including extension tabs.
 *
 * @return array<int, array<string, string>>
 */
function mrn_site_styles_get_admin_tabs(): array {
    $core_tabs = mrn_site_styles_get_core_tabs();
    $filtered_tabs = apply_filters('mrn_site_styles_tabs', $core_tabs);

    if (!is_array($filtered_tabs)) {
        $filtered_tabs = $core_tabs;
    }

    $normalized_by_key = array();

    foreach ($filtered_tabs as $tab) {
        if (!is_array($tab)) {
            continue;
        }

        $key = isset($tab['key']) ? sanitize_key((string) $tab['key']) : '';
        $label = isset($tab['label']) ? sanitize_text_field((string) $tab['label']) : '';
        $icon = isset($tab['icon']) ? sanitize_html_class((string) $tab['icon']) : '';

        if ('' === $key || '' === $label || isset($normalized_by_key[$key])) {
            continue;
        }

        if ('' === $icon) {
            $icon = 'dashicons-admin-generic';
        }

        $normalized_by_key[$key] = array(
            'key' => $key,
            'label' => $label,
            'icon' => $icon,
        );
    }

    $ordered_tabs = array();

    foreach ($core_tabs as $core_tab) {
        $core_key = (string) $core_tab['key'];

        if (isset($normalized_by_key[$core_key])) {
            $ordered_tabs[] = $normalized_by_key[$core_key];
            unset($normalized_by_key[$core_key]);
            continue;
        }

        $ordered_tabs[] = $core_tab;
    }

    foreach ($normalized_by_key as $tab) {
        $ordered_tabs[] = $tab;
    }

    return $ordered_tabs;
}

/**
 * Return known Site Styles tab keys.
 *
 * @return array<int, string>
 */
function mrn_site_styles_get_admin_tab_keys(): array {
    $keys = array();

    foreach (mrn_site_styles_get_admin_tabs() as $tab) {
        $key = isset($tab['key']) ? sanitize_key((string) $tab['key']) : '';

        if ('' !== $key) {
            $keys[] = $key;
        }
    }

    return array_values(array_unique($keys));
}

/**
 * Normalize selected Site Styles transfer sections.
 *
 * @param mixed $sections
 * @return array<string>
 */
function mrn_site_styles_normalize_transfer_sections($sections): array {
    if (!is_array($sections)) {
        return array();
    }

    $allowed = mrn_site_styles_get_transfer_sections();
    $normalized = array();

    foreach ($sections as $section) {
        $key = sanitize_key((string) $section);

        if (isset($allowed[$key])) {
            $normalized[] = $key;
        }
    }

    return array_values(array_unique($normalized));
}

/**
 * Load the shared sticky toolbar helper when available.
 *
 * @return bool
 */
function mrn_site_styles_load_sticky_toolbar_helper(): bool {
    static $loaded = false;

    if ($loaded || function_exists('mrn_sticky_toolbar_render')) {
        $loaded = true;
        return true;
    }

    $candidates = array(
        defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR . '/shared/mrn-sticky-settings-toolbar.php' : '',
        dirname(__DIR__, 2) . '/shared/mrn-sticky-settings-toolbar.php',
    );

    foreach ($candidates as $candidate) {
        if ($candidate && file_exists($candidate)) {
            require_once $candidate;
            $loaded = function_exists('mrn_sticky_toolbar_render');
            if ($loaded) {
                return true;
            }
        }
    }

    return false;
}

/**
 * Normalize a color slug for safe reuse in CSS and PHP.
 *
 * @param string $value
 * @return string
 */
function mrn_site_colors_normalize_slug(string $value): string {
    $slug = sanitize_title($value);
    return $slug !== '' ? $slug : 'color';
}

/**
 * Normalize and validate a hex color value.
 *
 * @param string $value
 * @return string
 */
function mrn_site_colors_normalize_hex(string $value): string {
    $value = trim($value);

    if ($value !== '' && strpos($value, '#') !== 0) {
        $value = '#' . $value;
    }

    $hex = sanitize_hex_color($value);
    return is_string($hex) && $hex !== '' ? strtoupper($hex) : '';
}

/**
 * Normalize a decimal value used by motion preset controls.
 *
 * @param mixed  $value
 * @param float  $default
 * @param float  $min
 * @param float  $max
 * @return string
 */
function mrn_site_styles_normalize_decimal_string($value, float $default, float $min, float $max): string {
    if (!is_scalar($value) || '' === trim((string) $value)) {
        $number = $default;
    } else {
        $number = (float) $value;
    }

    $number = max($min, min($max, $number));
    $string = number_format($number, 2, '.', '');

    return preg_replace('/\.?0+$/', '', $string) ?: (string) $default;
}

/**
 * Convert a hex color into a space-separated RGB triplet for CSS vars.
 *
 * @param string $hex
 * @return string
 */
function mrn_site_styles_hex_to_rgb_triplet(string $hex): string {
    $hex = mrn_site_colors_normalize_hex($hex);

    if ('' === $hex) {
        return '';
    }

    $value = ltrim($hex, '#');

    if (3 === strlen($value)) {
        $value = $value[0] . $value[0] . $value[1] . $value[1] . $value[2] . $value[2];
    }

    if (6 !== strlen($value)) {
        return '';
    }

    return sprintf(
        '%d %d %d',
        hexdec(substr($value, 0, 2)),
        hexdec(substr($value, 2, 2)),
        hexdec(substr($value, 4, 2))
    );
}

/**
 * Sanitize stored color rows.
 *
 * @param mixed $rows
 * @return array<int, array<string, string>>
 */
function mrn_site_colors_sanitize_rows($rows): array {
    $prepared = mrn_site_colors_prepare_rows($rows);
    return $prepared['sanitized'];
}

/**
 * Prepare site color rows for saving and admin feedback.
 *
 * @param mixed $rows
 * @return array{sanitized: array<int, array<string, string>>, display_rows: array<int, array<string, string>>, invalid_count: int}
 */
function mrn_site_colors_prepare_rows($rows): array {
    if (!is_array($rows)) {
        return array(
            'sanitized'    => array(),
            'display_rows' => array(),
            'invalid_count' => 0,
        );
    }

    $sanitized = array();
    $display_rows = array();
    $used_slugs = array();
    $invalid_count = 0;

    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $name = isset($row['name']) ? sanitize_text_field((string) $row['name']) : '';
        $submitted_value = isset($row['value']) ? strtoupper(trim(sanitize_text_field((string) $row['value']))) : '';
        $hex  = '' !== $submitted_value ? mrn_site_colors_normalize_hex($submitted_value) : '';
        $slug_source = isset($row['slug']) && (string) $row['slug'] !== ''
            ? (string) $row['slug']
            : $name;
        $slug = '' !== $slug_source ? mrn_site_colors_normalize_slug($slug_source) : '';

        if ($name === '' && $submitted_value === '') {
            continue;
        }

        if ($name === '' || $hex === '') {
            $invalid_count++;
            $display_rows[] = array(
                'slug'   => $slug,
                'name'   => $name,
                'value'  => $submitted_value,
                '_error' => $name === ''
                    ? 'Add a color name before saving.'
                    : 'Enter a valid hex color like #5D6180.',
            );
            continue;
        }

        $base_slug = $slug;
        $suffix = 2;

        while (isset($used_slugs[$slug])) {
            $slug = $base_slug . '-' . $suffix;
            $suffix++;
        }

        $used_slugs[$slug] = true;

        $row_data = array(
            'slug'  => $slug,
            'name'  => $name,
            'value' => $hex,
        );

        $sanitized[] = $row_data;
        $display_rows[] = $row_data;
    }

    return array(
        'sanitized'     => $sanitized,
        'display_rows'  => $display_rows,
        'invalid_count' => $invalid_count,
    );
}

/**
 * Get the transient key used for one-time Site Colors admin feedback.
 *
 * @param int $user_id
 * @return string
 */
function mrn_site_colors_feedback_transient_key(int $user_id): string {
    return 'mrn_site_colors_feedback_' . $user_id;
}

/**
 * Get the transient key used for one-time Site Styles transfer feedback.
 *
 * @param int $user_id
 * @return string
 */
function mrn_site_styles_transfer_feedback_transient_key(int $user_id): string {
    return 'mrn_site_styles_transfer_feedback_' . $user_id;
}

/**
 * Fetch and clear pending Site Colors admin feedback.
 *
 * @return array{display_rows?: array<int, array<string, string>>, invalid_count?: int}
 */
function mrn_site_colors_consume_feedback(): array {
    $user_id = get_current_user_id();

    if ($user_id <= 0) {
        return array();
    }

    $feedback = get_transient(mrn_site_colors_feedback_transient_key($user_id));

    if (!is_array($feedback)) {
        return array();
    }

    delete_transient(mrn_site_colors_feedback_transient_key($user_id));

    return $feedback;
}

/**
 * Store one-time Site Styles transfer feedback for the current user.
 *
 * @param array<string,string> $feedback
 * @return void
 */
function mrn_site_styles_set_transfer_feedback(array $feedback): void {
    $user_id = get_current_user_id();

    if ($user_id <= 0) {
        return;
    }

    set_transient(
        mrn_site_styles_transfer_feedback_transient_key($user_id),
        $feedback,
        5 * MINUTE_IN_SECONDS
    );
}

/**
 * Fetch and clear pending Site Styles transfer feedback.
 *
 * @return array<string,string>
 */
function mrn_site_styles_consume_transfer_feedback(): array {
    $user_id = get_current_user_id();

    if ($user_id <= 0) {
        return array();
    }

    $feedback = get_transient(mrn_site_styles_transfer_feedback_transient_key($user_id));

    if (!is_array($feedback)) {
        return array();
    }

    delete_transient(mrn_site_styles_transfer_feedback_transient_key($user_id));

    return $feedback;
}

/**
 * Build the current Site Styles export payload.
 *
 * @param array<string> $sections
 * @return array<string,mixed>
 */
function mrn_site_styles_build_export_payload(array $sections = array()): array {
    if ($sections === array()) {
        $sections = array_keys(mrn_site_styles_get_transfer_sections());
    }

    $data = array();

    if (in_array('colors', $sections, true)) {
        $data['colors'] = mrn_site_colors_sanitize_rows(get_option(mrn_site_colors_option_key(), array()));
    }

    if (in_array('graphic_elements', $sections, true)) {
        $data['graphic_elements'] = mrn_site_styles_sanitize_graphic_element_rows(get_option(mrn_site_styles_graphic_elements_option_key(), array()));
    }

    if (in_array('dark_scroll_card_presets', $sections, true)) {
        $data['dark_scroll_card_presets'] = mrn_site_styles_sanitize_dark_scroll_card_preset_rows(get_option(mrn_site_styles_dark_scroll_card_presets_option_key(), array()));
    }

    $extension_data = apply_filters('mrn_site_styles_export_data', $data, $sections);
    if (is_array($extension_data)) {
        $data = $extension_data;
    }

    return array(
        'tool'        => 'mrn-site-styles',
        'version'     => 1,
        'exported_at' => gmdate('c'),
        'site_url'    => home_url('/'),
        'data'        => $data,
    );
}

/**
 * Handle Site Styles JSON export.
 *
 * @return void
 */
function mrn_site_styles_handle_export(): void {
    if (!is_admin() || !isset($_POST['mrn_site_styles_export_submit'])) {
        return;
    }

    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You are not allowed to export Site Styles.', 'mrn'));
    }

    check_admin_referer('mrn_site_styles_export', 'mrn_site_styles_export_nonce');

    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Values are normalized against the allowed transfer section keys.
    $sections = isset($_POST['mrn_site_styles_sections']) ? mrn_site_styles_normalize_transfer_sections(wp_unslash($_POST['mrn_site_styles_sections'])) : array();

    if ($sections === array()) {
        mrn_site_styles_set_transfer_feedback(array(
            'type'    => 'error',
            'message' => 'Select at least one Site Styles section to export.',
        ));
        wp_safe_redirect(
            add_query_arg(
                array(
                    'page' => 'mrn-site-styles',
                ),
                admin_url('options-general.php')
            )
        );
        exit;
    }

    $payload = mrn_site_styles_build_export_payload($sections);
    $filename = sprintf('mrn-site-styles-%s.json', gmdate('Y-m-d-His'));

    nocache_headers();
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    echo wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}
add_action('admin_init', 'mrn_site_styles_handle_export');

/**
 * Handle Site Styles JSON import.
 *
 * @return void
 */
function mrn_site_styles_handle_import(): void {
    if (!is_admin() || !isset($_POST['mrn_site_styles_import_submit'])) {
        return;
    }

    if (!current_user_can('manage_options')) {
        return;
    }

    check_admin_referer('mrn_site_styles_import', 'mrn_site_styles_import_nonce');

    $redirect = add_query_arg(
        array(
            'page' => 'mrn-site-styles',
        ),
        admin_url('options-general.php')
    );

    if (
        !isset($_FILES['mrn_site_styles_import_file'])
        || !is_array($_FILES['mrn_site_styles_import_file'])
        || !isset($_FILES['mrn_site_styles_import_file']['tmp_name'])
    ) {
        mrn_site_styles_set_transfer_feedback(array(
            'type'    => 'error',
            'message' => 'Choose a Site Styles export file to import.',
        ));
        wp_safe_redirect($redirect);
        exit;
    }

    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- File metadata is validated via upload checks before use.
    $upload = $_FILES['mrn_site_styles_import_file'];
    $tmp_name = isset($upload['tmp_name']) ? (string) $upload['tmp_name'] : '';
    $error_code = isset($upload['error']) ? (int) $upload['error'] : UPLOAD_ERR_OK;

    if (UPLOAD_ERR_OK !== $error_code || '' === $tmp_name || !is_uploaded_file($tmp_name)) {
        mrn_site_styles_set_transfer_feedback(array(
            'type'    => 'error',
            'message' => 'The Site Styles import file could not be uploaded.',
        ));
        wp_safe_redirect($redirect);
        exit;
    }

    $raw = file_get_contents($tmp_name);

    if (!is_string($raw) || '' === $raw) {
        mrn_site_styles_set_transfer_feedback(array(
            'type'    => 'error',
            'message' => 'The Site Styles import file was empty.',
        ));
        wp_safe_redirect($redirect);
        exit;
    }

    $decoded = json_decode($raw, true);

    if (
        !is_array($decoded)
        || ('mrn-site-styles' !== ($decoded['tool'] ?? ''))
        || !isset($decoded['data'])
        || !is_array($decoded['data'])
    ) {
        mrn_site_styles_set_transfer_feedback(array(
            'type'    => 'error',
            'message' => 'That file is not a valid Site Styles export.',
        ));
        wp_safe_redirect($redirect);
        exit;
    }

    $data = $decoded['data'];
    $imported_sections = array();

    if (array_key_exists('colors', $data)) {
        $colors = mrn_site_colors_sanitize_rows($data['colors']);
        update_option(mrn_site_colors_option_key(), $colors, false);
        $imported_sections[] = 'Site Colors';
    }

    if (array_key_exists('graphic_elements', $data)) {
        $graphic_elements = mrn_site_styles_sanitize_graphic_element_rows($data['graphic_elements']);
        update_option(mrn_site_styles_graphic_elements_option_key(), $graphic_elements, false);
        $imported_sections[] = 'Graphic Elements';
    }

    if (array_key_exists('dark_scroll_card_presets', $data)) {
        $motion_presets = mrn_site_styles_sanitize_dark_scroll_card_preset_rows($data['dark_scroll_card_presets']);
        update_option(mrn_site_styles_dark_scroll_card_presets_option_key(), $motion_presets, false);
        $imported_sections[] = 'Motion Presets';
    }

    $extension_imported_sections = apply_filters('mrn_site_styles_import_data', $imported_sections, $data);
    if (is_array($extension_imported_sections)) {
        $imported_sections = $extension_imported_sections;
    }

    $imported_sections = array_values(
        array_unique(
            array_filter(
                array_map(
                    'sanitize_text_field',
                    array_map('strval', $imported_sections)
                )
            )
        )
    );

    if ($imported_sections === array()) {
        mrn_site_styles_set_transfer_feedback(array(
            'type'    => 'error',
            'message' => 'That file did not contain any importable Site Styles sections.',
        ));
        wp_safe_redirect($redirect);
        exit;
    }

    delete_transient(mrn_site_colors_feedback_transient_key(get_current_user_id()));

    $source_site = isset($decoded['site_url']) ? esc_url_raw((string) $decoded['site_url']) : '';
    $message = sprintf('Imported: %s.', implode(', ', $imported_sections));

    if ('' !== $source_site) {
        $message .= ' Source: ' . $source_site;
    }

    mrn_site_styles_set_transfer_feedback(array(
        'type'    => 'success',
        'message' => $message,
    ));

    wp_safe_redirect($redirect);
    exit;
}
add_action('admin_init', 'mrn_site_styles_handle_import');

/**
 * Get all configured site colors.
 *
 * @return array<int, array<string, string>>
 */
function mrn_site_colors_get_all(): array {
    static $rows = null;

    if (null !== $rows) {
        return $rows;
    }

    $rows = mrn_site_colors_sanitize_rows(get_option(mrn_site_colors_option_key(), array()));

    return $rows;
}

/**
 * Get a single site color value by slug.
 *
 * @param string $slug
 * @return string
 */
function mrn_site_colors_get_value(string $slug): string {
    $normalized_slug = mrn_site_colors_normalize_slug($slug);

    foreach (mrn_site_colors_get_all() as $row) {
        if (($row['slug'] ?? '') === $normalized_slug) {
            return (string) ($row['value'] ?? '');
        }
    }

    return '';
}

/**
 * Get the CSS variable name for a configured color slug.
 *
 * @param string $slug
 * @return string
 */
function mrn_site_colors_get_css_var(string $slug): string {
    return '--site-color-' . mrn_site_colors_normalize_slug($slug);
}

/**
 * Get colors keyed by slug for easier lookup in templates.
 *
 * @return array<string, array<string, string>>
 */
function mrn_site_colors_get_map(): array {
    $map = array();

    foreach (mrn_site_colors_get_all() as $row) {
        $slug = (string) ($row['slug'] ?? '');
        if ($slug === '') {
            continue;
        }

        $map[$slug] = $row;
    }

    return $map;
}

/**
 * Sanitize stored graphic element rows.
 *
 * @param mixed $rows
 * @return array<int, array<string, string>>
 */
function mrn_site_styles_sanitize_graphic_element_rows($rows): array {
    if (!is_array($rows)) {
        return array();
    }

    $sanitized  = array();
    $used_slugs = array();

    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $name        = isset($row['name']) ? sanitize_text_field((string) $row['name']) : '';
        $slug_source = isset($row['slug']) && (string) $row['slug'] !== ''
            ? (string) $row['slug']
            : $name;
        $slug        = mrn_site_colors_normalize_slug($slug_source);
        $css         = isset($row['css']) ? (string) $row['css'] : '';
        $space       = isset($row['space']) ? sanitize_text_field((string) $row['space']) : '';
        $css         = preg_replace('#</?style[^>]*>#i', '', $css);
        $css         = is_string($css) ? trim(wp_kses_no_null($css, array('slash_zero' => 'keep'))) : '';
        $space       = preg_replace('/[^a-zA-Z0-9.%()\\-+\\s]/', '', $space);
        $space       = is_string($space) ? trim($space) : '';

        if ($name === '' || $css === '') {
            continue;
        }

        $base_slug = $slug;
        $suffix    = 2;

        while (isset($used_slugs[$slug])) {
            $slug = $base_slug . '-' . $suffix;
            $suffix++;
        }

        $used_slugs[$slug] = true;

        $sanitized[] = array(
            'slug' => $slug,
            'name' => $name,
            'css'  => $css,
            'space' => $space,
        );
    }

    return $sanitized;
}

/**
 * Get all configured graphic elements.
 *
 * @return array<int, array<string, string>>
 */
function mrn_site_styles_get_graphic_elements(): array {
    static $rows = null;

    if (null !== $rows) {
        return $rows;
    }

    $rows = mrn_site_styles_sanitize_graphic_element_rows(get_option(mrn_site_styles_graphic_elements_option_key(), array()));

    return $rows;
}

/**
 * Get graphic elements keyed by slug for easier lookup in templates.
 *
 * @return array<string, array<string, string>>
 */
function mrn_site_styles_get_graphic_element_map(): array {
    static $map = null;

    if (null !== $map) {
        return $map;
    }

    $map = array();

    foreach (mrn_site_styles_get_graphic_elements() as $row) {
        $slug = isset($row['slug']) ? (string) $row['slug'] : '';
        if ($slug === '') {
            continue;
        }

        $map[$slug] = $row;
    }

    return $map;
}

/**
 * Get graphic element choices for admin selects.
 *
 * @return array<string, string>
 */
function mrn_site_styles_get_graphic_element_choices(): array {
    static $choices = null;

    if (null !== $choices) {
        return $choices;
    }

    $choices = array(
        '' => 'Select a Graphic Element',
    );

    foreach (mrn_site_styles_get_graphic_elements() as $row) {
        $slug = isset($row['slug']) ? (string) $row['slug'] : '';
        $name = isset($row['name']) ? (string) $row['name'] : '';

        if ($slug === '' || $name === '') {
            continue;
        }

        $choices[$slug] = $name;
    }

    return $choices;
}

/**
 * Normalize a selected bottom accent slug against saved graphic elements.
 *
 * @param string $slug
 * @return string
 */
function mrn_site_styles_get_bottom_accent_slug(string $slug): string {
    $normalized_slug = mrn_site_colors_normalize_slug($slug);

    if ($normalized_slug === '') {
        return '';
    }

    $graphic_elements = mrn_site_styles_get_graphic_element_map();

    return isset($graphic_elements[$normalized_slug]) ? $normalized_slug : '';
}

/**
 * Build shared bottom accent classes and attributes for templates.
 *
 * @param bool   $enabled
 * @param string $accent_slug
 * @return array{classes: array<int, string>, attributes: array<string, string>}
 */
function mrn_site_styles_get_bottom_accent_contract(bool $enabled, string $accent_slug = ''): array {
    if (!$enabled) {
        return array(
            'classes'    => array(),
            'attributes' => array(),
        );
    }

    $classes = array('has-bottom-accent');
    $attributes = array();
    $normalized_slug = mrn_site_styles_get_bottom_accent_slug($accent_slug);

    if ($normalized_slug !== '') {
        $attributes['data-bottom-accent'] = $normalized_slug;
    }

    return array(
        'classes'    => $classes,
        'attributes' => $attributes,
    );
}

/**
 * Sanitize stored dark scroll card preset rows.
 *
 * @param mixed $rows
 * @return array<int, array<string, string>>
 */
function mrn_site_styles_sanitize_dark_scroll_card_preset_rows($rows): array {
    if (!is_array($rows)) {
        return array();
    }

    $sanitized = array();
    $used_slugs = array();

    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $name = isset($row['name']) ? sanitize_text_field((string) $row['name']) : '';
        $slug_source = isset($row['slug']) && '' !== (string) $row['slug']
            ? (string) $row['slug']
            : $name;
        $slug = mrn_site_colors_normalize_slug($slug_source);

        $background = isset($row['background']) ? mrn_site_colors_normalize_hex((string) $row['background']) : '';
        $text = isset($row['text']) ? mrn_site_colors_normalize_hex((string) $row['text']) : '';
        $muted_text = isset($row['muted_text']) ? mrn_site_colors_normalize_hex((string) $row['muted_text']) : '';
        $button_background = isset($row['button_background']) ? mrn_site_colors_normalize_hex((string) $row['button_background']) : '';
        $button_text = isset($row['button_text']) ? mrn_site_colors_normalize_hex((string) $row['button_text']) : '';
        $border_alpha = mrn_site_styles_normalize_decimal_string($row['border_alpha'] ?? '', 0.12, 0, 1);
        $shadow_alpha = mrn_site_styles_normalize_decimal_string($row['shadow_alpha'] ?? '', 0.35, 0, 1);
        $image_brightness = mrn_site_styles_normalize_decimal_string($row['image_brightness'] ?? '', 0.72, 0, 2);
        $image_saturation = mrn_site_styles_normalize_decimal_string($row['image_saturation'] ?? '', 0.85, 0, 3);

        if ('' === $name || '' === $background || '' === $text || '' === $muted_text || '' === $button_background || '' === $button_text) {
            continue;
        }

        $base_slug = $slug;
        $suffix = 2;

        while (isset($used_slugs[$slug])) {
            $slug = $base_slug . '-' . $suffix;
            $suffix++;
        }

        $used_slugs[$slug] = true;

        $sanitized[] = array(
            'name' => $name,
            'slug' => $slug,
            'background' => $background,
            'text' => $text,
            'muted_text' => $muted_text,
            'button_background' => $button_background,
            'button_text' => $button_text,
            'border_alpha' => $border_alpha,
            'shadow_alpha' => $shadow_alpha,
            'image_brightness' => $image_brightness,
            'image_saturation' => $image_saturation,
        );
    }

    return $sanitized;
}

/**
 * Get all configured dark scroll card presets.
 *
 * @return array<int, array<string, string>>
 */
function mrn_site_styles_get_dark_scroll_card_presets(): array {
    static $presets = null;

    if (null !== $presets) {
        return $presets;
    }

    $rows = get_option(mrn_site_styles_dark_scroll_card_presets_option_key(), array());
    $sanitized = mrn_site_styles_sanitize_dark_scroll_card_preset_rows($rows);

    if ($sanitized !== array()) {
        $presets = $sanitized;
        return $presets;
    }

    $presets = array(
        array(
            'name' => 'Brand Dark Card',
            'slug' => 'brand-dark-card',
            'background' => '#0F0F15',
            'text' => '#F5F5F5',
            'muted_text' => '#B6BEC9',
            'button_background' => '#FFFFFF',
            'button_text' => '#111111',
            'border_alpha' => '0.12',
            'shadow_alpha' => '0.35',
            'image_brightness' => '0.72',
            'image_saturation' => '0.85',
        ),
    );

    return $presets;
}

/**
 * Get dark scroll card presets keyed by slug.
 *
 * @return array<string, array<string, string>>
 */
function mrn_site_styles_get_dark_scroll_card_preset_map(): array {
    static $map = null;

    if (null !== $map) {
        return $map;
    }

    $map = array();

    foreach (mrn_site_styles_get_dark_scroll_card_presets() as $row) {
        $slug = isset($row['slug']) ? (string) $row['slug'] : '';

        if ('' === $slug) {
            continue;
        }

        $map[$slug] = $row;
    }

    return $map;
}

/**
 * Get admin choices for the dark scroll card preset select.
 *
 * @return array<string, string>
 */
function mrn_site_styles_get_dark_scroll_card_preset_choices(): array {
    static $choices = null;

    if (null !== $choices) {
        return $choices;
    }

    $choices = array(
        '' => 'Default Dark Card',
    );

    foreach (mrn_site_styles_get_dark_scroll_card_presets() as $row) {
        $slug = isset($row['slug']) ? (string) $row['slug'] : '';
        $name = isset($row['name']) ? (string) $row['name'] : '';

        if ('' === $slug || '' === $name) {
            continue;
        }

        $choices[$slug] = $name;
    }

    return $choices;
}

/**
 * Persist settings form submission.
 */
function mrn_site_colors_handle_save(): void {
    if (!is_admin()) {
        return;
    }

    $request_action = isset($_REQUEST['action'])
        ? sanitize_key(wp_unslash((string) $_REQUEST['action']))
        : '';

    // Do not intercept explicit admin-post actions (for example, Google Fonts local build actions).
    if ('' !== $request_action) {
        return;
    }

    $submitted_section = isset($_POST['mrn_site_styles_section'])
        ? sanitize_key(wp_unslash((string) $_POST['mrn_site_styles_section']))
        : '';

    if ('' === $submitted_section && isset($_POST['mrn_site_colors_submit'])) {
        $submitted_section = 'colors';
    } elseif ('' === $submitted_section && isset($_POST['mrn_site_graphic_elements_submit'])) {
        $submitted_section = 'graphic-elements';
    } elseif ('' === $submitted_section && isset($_POST['mrn_site_dark_scroll_card_presets_submit'])) {
        $submitted_section = 'motion-presets';
    }

    if (!in_array($submitted_section, mrn_site_styles_get_admin_tab_keys(), true)) {
        return;
    }

    if (!current_user_can('manage_options')) {
        return;
    }

    check_admin_referer('mrn_site_colors_save', 'mrn_site_colors_nonce');

    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Rows are unslashed here and sanitized in mrn_site_colors_prepare_rows().
    $rows = isset($_POST['mrn_site_colors']) ? wp_unslash($_POST['mrn_site_colors']) : array();
    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Rows are unslashed here and sanitized in mrn_site_styles_sanitize_graphic_element_rows().
    $graphic_element_rows = isset($_POST['mrn_site_graphic_elements']) ? wp_unslash($_POST['mrn_site_graphic_elements']) : array();
    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Rows are unslashed here and sanitized in mrn_site_styles_sanitize_dark_scroll_card_preset_rows().
    $dark_scroll_card_rows = isset($_POST['mrn_site_dark_scroll_card_presets']) ? wp_unslash($_POST['mrn_site_dark_scroll_card_presets']) : array();

    $prepared = mrn_site_colors_prepare_rows($rows);
    $sanitized = $prepared['sanitized'];
    $sanitized_graphic_items = mrn_site_styles_sanitize_graphic_element_rows($graphic_element_rows);
    $sanitized_motion_presets = mrn_site_styles_sanitize_dark_scroll_card_preset_rows($dark_scroll_card_rows);

    update_option(mrn_site_colors_option_key(), $sanitized, false);
    update_option(mrn_site_styles_graphic_elements_option_key(), $sanitized_graphic_items, false);
    update_option(mrn_site_styles_dark_scroll_card_presets_option_key(), $sanitized_motion_presets, false);

    /**
     * Let extension tabs persist their own section data.
     *
     * @param string $submitted_section Active Site Styles tab key.
     */
    do_action('mrn_site_styles_handle_save', $submitted_section);

    if ((int) $prepared['invalid_count'] > 0) {
        set_transient(
            mrn_site_colors_feedback_transient_key(get_current_user_id()),
            array(
                'display_rows'  => $prepared['display_rows'],
                'invalid_count' => (int) $prepared['invalid_count'],
            ),
            5 * MINUTE_IN_SECONDS
        );
    } else {
        delete_transient(mrn_site_colors_feedback_transient_key(get_current_user_id()));
    }

    $redirect = add_query_arg(
        array(
            'page'    => 'mrn-site-styles',
            'updated' => $submitted_section,
        ),
        admin_url('options-general.php')
    );

    wp_safe_redirect($redirect);
    exit;
}
add_action('admin_init', 'mrn_site_colors_handle_save');

/**
 * Register the Site Styles settings page.
 */
function mrn_site_colors_register_menu(): void {
    add_options_page(
        'Site Styles',
        'Site Styles',
        'manage_options',
        'mrn-site-styles',
        'mrn_site_colors_render_page'
    );
}
add_action('admin_menu', 'mrn_site_colors_register_menu');

/**
 * Render a single color row.
 *
 * @param int                  $index
 * @param array<string,string> $row
 */
function mrn_site_colors_render_row(int $index, array $row): void {
    $name  = isset($row['name']) ? (string) $row['name'] : '';
    $slug  = isset($row['slug']) ? (string) $row['slug'] : '';
    $value = isset($row['value']) ? (string) $row['value'] : '';
    $error = isset($row['_error']) ? (string) $row['_error'] : '';
    $picker_value = mrn_site_colors_normalize_hex($value);

    if ('' === $picker_value) {
        $picker_value = '#000000';
    }
    ?>
    <tr class="mrn-site-colors-row">
        <td>
            <input type="text" class="regular-text mrn-site-colors-name" name="mrn_site_colors[<?php echo esc_attr((string) $index); ?>][name]" value="<?php echo esc_attr($name); ?>" />
            <input type="hidden" class="mrn-site-colors-slug" name="mrn_site_colors[<?php echo esc_attr((string) $index); ?>][slug]" value="<?php echo esc_attr($slug); ?>" />
        </td>
        <td>
            <input type="text" class="regular-text code mrn-site-colors-value" name="mrn_site_colors[<?php echo esc_attr((string) $index); ?>][value]" value="<?php echo esc_attr($value); ?>" />
            <?php if ('' !== $error) : ?>
                <p class="description" style="margin:6px 0 0;color:#b32d2e;"><?php echo esc_html($error); ?></p>
            <?php endif; ?>
        </td>
        <td>
            <input type="color" class="mrn-site-colors-picker" value="<?php echo esc_attr($picker_value); ?>" />
        </td>
        <td>
            <code class="mrn-site-colors-var"><?php echo esc_html($slug !== '' ? mrn_site_colors_get_css_var($slug) : '--site-color-your-slug'); ?></code>
        </td>
        <td>
            <button type="button" class="button-link-delete mrn-site-colors-remove">Remove</button>
        </td>
    </tr>
    <?php
}

/**
 * Render a single graphic element row.
 *
 * @param int                  $index
 * @param array<string,string> $row
 */
function mrn_site_styles_render_graphic_element_row(int $index, array $row): void {
    $name  = isset($row['name']) ? (string) $row['name'] : '';
    $slug  = isset($row['slug']) ? (string) $row['slug'] : '';
    $css   = isset($row['css']) ? (string) $row['css'] : '';
    $space = isset($row['space']) ? (string) $row['space'] : '';
    ?>
    <tr class="mrn-site-styles-graphic-row">
        <td style="vertical-align:top;">
            <input type="text" class="regular-text mrn-site-styles-graphic-name" name="mrn_site_graphic_elements[<?php echo esc_attr((string) $index); ?>][name]" value="<?php echo esc_attr($name); ?>" />
            <input type="hidden" class="mrn-site-styles-graphic-slug" name="mrn_site_graphic_elements[<?php echo esc_attr((string) $index); ?>][slug]" value="<?php echo esc_attr($slug); ?>" />
        </td>
        <td style="vertical-align:top;">
            <input type="text" class="regular-text code mrn-site-styles-graphic-space" name="mrn_site_graphic_elements[<?php echo esc_attr((string) $index); ?>][space]" value="<?php echo esc_attr($space); ?>" />
            <p class="description" style="margin:6px 0 0;">Optional bottom spacing override.</p>
        </td>
        <td style="vertical-align:top;">
            <textarea class="large-text code mrn-site-styles-graphic-css" name="mrn_site_graphic_elements[<?php echo esc_attr((string) $index); ?>][css]" rows="8"><?php echo esc_textarea($css); ?></textarea>
        </td>
        <td style="vertical-align:top;">
            <code class="mrn-site-styles-graphic-token"><?php echo esc_html($slug !== '' ? $slug : 'graphic-element'); ?></code>
        </td>
        <td style="vertical-align:top;">
            <button type="button" class="button-link-delete mrn-site-styles-graphic-remove">Remove</button>
        </td>
    </tr>
    <?php
}

/**
 * Render a single dark scroll card preset row.
 *
 * @param int                  $index
 * @param array<string,string> $row
 */
function mrn_site_styles_render_dark_scroll_card_preset_row(int $index, array $row): void {
    $name             = isset($row['name']) ? (string) $row['name'] : '';
    $slug             = isset($row['slug']) ? (string) $row['slug'] : '';
    $background       = isset($row['background']) ? (string) $row['background'] : '';
    $text             = isset($row['text']) ? (string) $row['text'] : '';
    $muted_text       = isset($row['muted_text']) ? (string) $row['muted_text'] : '';
    $button_background = isset($row['button_background']) ? (string) $row['button_background'] : '';
    $button_text      = isset($row['button_text']) ? (string) $row['button_text'] : '';
    $border_alpha     = isset($row['border_alpha']) ? (string) $row['border_alpha'] : '';
    $shadow_alpha     = isset($row['shadow_alpha']) ? (string) $row['shadow_alpha'] : '';
    $image_brightness = isset($row['image_brightness']) ? (string) $row['image_brightness'] : '';
    $image_saturation = isset($row['image_saturation']) ? (string) $row['image_saturation'] : '';
    ?>
    <tr class="mrn-site-styles-motion-row">
        <td style="vertical-align:top;">
            <input type="text" class="regular-text mrn-site-styles-motion-name" name="mrn_site_dark_scroll_card_presets[<?php echo esc_attr((string) $index); ?>][name]" value="<?php echo esc_attr($name); ?>" />
            <input type="hidden" class="mrn-site-styles-motion-slug" name="mrn_site_dark_scroll_card_presets[<?php echo esc_attr((string) $index); ?>][slug]" value="<?php echo esc_attr($slug); ?>" />
            <p class="description" style="margin:6px 0 0;">Shown to editors as the effect preset name.</p>
        </td>
        <td style="vertical-align:top;">
            <div class="mrn-site-styles-motion-fields">
                <label>
                    <span>Background</span>
                    <input type="text" class="regular-text code" name="mrn_site_dark_scroll_card_presets[<?php echo esc_attr((string) $index); ?>][background]" value="<?php echo esc_attr($background); ?>" />
                </label>
                <label>
                    <span>Text</span>
                    <input type="text" class="regular-text code" name="mrn_site_dark_scroll_card_presets[<?php echo esc_attr((string) $index); ?>][text]" value="<?php echo esc_attr($text); ?>" />
                </label>
                <label>
                    <span>Muted Text</span>
                    <input type="text" class="regular-text code" name="mrn_site_dark_scroll_card_presets[<?php echo esc_attr((string) $index); ?>][muted_text]" value="<?php echo esc_attr($muted_text); ?>" />
                </label>
                <label>
                    <span>Button Background</span>
                    <input type="text" class="regular-text code" name="mrn_site_dark_scroll_card_presets[<?php echo esc_attr((string) $index); ?>][button_background]" value="<?php echo esc_attr($button_background); ?>" />
                </label>
                <label>
                    <span>Button Text</span>
                    <input type="text" class="regular-text code" name="mrn_site_dark_scroll_card_presets[<?php echo esc_attr((string) $index); ?>][button_text]" value="<?php echo esc_attr($button_text); ?>" />
                </label>
                <label>
                    <span>Border Alpha</span>
                    <input type="text" class="regular-text code" name="mrn_site_dark_scroll_card_presets[<?php echo esc_attr((string) $index); ?>][border_alpha]" value="<?php echo esc_attr($border_alpha); ?>" />
                </label>
                <label>
                    <span>Shadow Alpha</span>
                    <input type="text" class="regular-text code" name="mrn_site_dark_scroll_card_presets[<?php echo esc_attr((string) $index); ?>][shadow_alpha]" value="<?php echo esc_attr($shadow_alpha); ?>" />
                </label>
                <label>
                    <span>Image Brightness</span>
                    <input type="text" class="regular-text code" name="mrn_site_dark_scroll_card_presets[<?php echo esc_attr((string) $index); ?>][image_brightness]" value="<?php echo esc_attr($image_brightness); ?>" />
                </label>
                <label>
                    <span>Image Saturation</span>
                    <input type="text" class="regular-text code" name="mrn_site_dark_scroll_card_presets[<?php echo esc_attr((string) $index); ?>][image_saturation]" value="<?php echo esc_attr($image_saturation); ?>" />
                </label>
            </div>
        </td>
        <td style="vertical-align:top;">
            <code class="mrn-site-styles-motion-token"><?php echo esc_html('' !== $slug ? $slug : 'dark-card-preset'); ?></code>
        </td>
        <td style="vertical-align:top;">
            <button type="button" class="button-link-delete mrn-site-styles-motion-remove">Remove</button>
        </td>
    </tr>
    <?php
}

/**
 * Render the Site Styles settings page.
 */
function mrn_site_colors_render_page(): void {
    if (!current_user_can('manage_options')) {
        return;
    }

    $rows             = mrn_site_colors_get_all();
    $graphic_elements = mrn_site_styles_get_graphic_elements();
    $dark_scroll_card_presets = mrn_site_styles_sanitize_dark_scroll_card_preset_rows(
        get_option(mrn_site_styles_dark_scroll_card_presets_option_key(), array())
    );
    $color_feedback = mrn_site_colors_consume_feedback();
    $transfer_feedback = mrn_site_styles_consume_transfer_feedback();
    $color_invalid_count = isset($color_feedback['invalid_count']) ? (int) $color_feedback['invalid_count'] : 0;

    if (!empty($color_feedback['display_rows']) && is_array($color_feedback['display_rows'])) {
        $rows = $color_feedback['display_rows'];
    }

    $updated_notice = '';
    $tabs = mrn_site_styles_get_admin_tabs();
    $tab_keys = mrn_site_styles_get_admin_tab_keys();
    $default_tab = !empty($tab_keys) ? (string) $tab_keys[0] : 'colors';
    $active_tab = $default_tab;
    $has_sticky_toolbar = mrn_site_styles_load_sticky_toolbar_helper();

    // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only tab/notice state from our own redirect query arg.
    if (isset($_GET['updated'])) {
        $updated_notice = sanitize_key(wp_unslash((string) $_GET['updated']));
    }
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    if (in_array($updated_notice, $tab_keys, true)) {
        $active_tab = $updated_notice;
    }

    $separator_site_colors = array();

    foreach ($rows as $row) {
        $color_name = isset($row['name']) ? sanitize_text_field((string) $row['name']) : '';
        $color_value = isset($row['value']) ? mrn_site_colors_normalize_hex((string) $row['value']) : '';

        if ('' === $color_name || '' === $color_value) {
            continue;
        }

        $separator_site_colors[] = array(
            'label' => $color_name . ' (' . $color_value . ')',
            'value' => $color_value,
        );
    }

    if (array() === $separator_site_colors) {
        $separator_site_colors = array(
            array(
                'label' => 'White (#FFFFFF)',
                'value' => '#FFFFFF',
            ),
            array(
                'label' => 'Slate 800 (#1F2937)',
                'value' => '#1F2937',
            ),
        );
    }

    ?>
    <div class="wrap">
        <style>
            .mrn-site-styles-panel[hidden] {
                display: none;
            }

            .mrn-site-styles-panels {
                max-width: 1100px;
                margin-top: 20px;
            }

            .mrn-site-styles-card {
                background: #fff;
                border: 1px solid #dcdcde;
                box-shadow: 0 1px 1px rgba(0, 0, 0, 0.04);
                padding: 24px;
            }

            .mrn-site-styles-card + .mrn-site-styles-card {
                margin-top: 16px;
            }

            .mrn-site-styles-motion-table {
                max-width: 1100px;
                table-layout: fixed;
            }

            .mrn-site-styles-motion-table th,
            .mrn-site-styles-motion-table td {
                vertical-align: top;
            }

            .mrn-site-styles-motion-table .regular-text,
            .mrn-site-styles-motion-table .code {
                width: 100%;
                max-width: none;
                box-sizing: border-box;
            }

            .mrn-site-styles-motion-fields {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 10px 14px;
            }

            .mrn-site-styles-motion-fields label {
                display: block;
                font-weight: 600;
            }

            .mrn-site-styles-motion-fields label span {
                display: block;
                margin-bottom: 4px;
                font-weight: 600;
            }

            .mrn-site-styles-motion-fields input {
                width: 100%;
                min-width: 0;
            }

            .mrn-site-styles-transfer-box {
                max-width: 1100px;
                margin-top: 20px;
                padding: 16px 20px;
                background: #fff;
                border: 1px solid #dcdcde;
                border-radius: 8px;
            }

            .mrn-site-styles-transfer-actions {
                display: flex;
                flex-wrap: wrap;
                gap: 16px 24px;
                align-items: end;
            }

            .mrn-site-styles-transfer-actions form {
                margin: 0;
            }

            .mrn-site-styles-transfer-actions label {
                display: block;
                font-weight: 600;
                margin-bottom: 6px;
            }

            .mrn-site-styles-transfer-sections {
                display: grid;
                gap: 6px;
                min-width: 220px;
            }

            .mrn-site-styles-transfer-sections label {
                display: flex;
                align-items: center;
                gap: 8px;
                margin: 0;
                font-weight: 400;
            }

            .mrn-site-styles-panel-actions {
                margin-top: 16px;
            }

            .mrn-site-styles-separator-generator {
                display: grid;
                grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr);
                align-items: start;
                gap: 20px;
                margin-top: 18px;
            }

            .mrn-site-styles-separator-fields {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 12px 16px;
            }

            .mrn-site-styles-separator-field {
                display: block;
                min-width: 0;
            }

            .mrn-site-styles-separator-field span {
                display: flex;
                align-items: baseline;
                justify-content: space-between;
                gap: 10px;
                margin-bottom: 4px;
                font-weight: 600;
                color: #2c3338;
            }

            .mrn-site-styles-separator-field .description {
                margin: 6px 0 0;
                color: #50575e;
                font-size: 12px;
            }

            .mrn-site-styles-separator-field input,
            .mrn-site-styles-separator-field select,
            .mrn-site-styles-separator-field textarea {
                width: 100%;
                max-width: none;
                box-sizing: border-box;
            }

            .mrn-site-styles-separator-field .button {
                width: 100%;
                text-align: center;
            }

            .mrn-site-styles-separator-field .button.is-primary {
                color: #fff;
                background: #2271b1;
                border-color: #2271b1;
            }

            .mrn-site-styles-separator-field textarea {
                min-height: 120px;
                resize: vertical;
            }

            .mrn-site-styles-separator-field--full {
                grid-column: 1 / -1;
            }

            .mrn-site-styles-separator-preview-shell {
                position: relative;
                border: 1px solid #d0d4d9;
                border-radius: 10px;
                overflow: hidden;
                background: #fff;
            }

            .mrn-site-styles-separator-preview-current,
            .mrn-site-styles-separator-preview-next {
                position: relative;
                padding: 20px;
                color: #111827;
                background: transparent;
                isolation: isolate;
            }

            .mrn-site-styles-separator-preview-current::before,
            .mrn-site-styles-separator-preview-next::before {
                content: "";
                position: absolute;
                inset: 0;
                background: var(--mrn-preview-row-bg, #FFFFFF);
                z-index: 0;
            }

            .mrn-site-styles-separator-preview-current {
                min-height: 168px;
                z-index: 1;
            }

            .mrn-site-styles-separator-preview-current > *,
            .mrn-site-styles-separator-preview-next > * {
                position: relative;
                z-index: 2;
            }

            .mrn-site-styles-separator-preview-next {
                min-height: 128px;
                border-top: 1px solid #d6dae0;
                z-index: 1;
            }

            .mrn-site-styles-separator-preview-chip {
                display: inline-flex;
                align-items: center;
                border: 1px solid currentColor;
                border-radius: 999px;
                padding: 4px 10px;
                font-size: 12px;
                font-weight: 700;
                letter-spacing: 0.08em;
                text-transform: uppercase;
                line-height: 1;
                opacity: 0.92;
            }

            .mrn-site-styles-separator-preview-current h3,
            .mrn-site-styles-separator-preview-next h3 {
                margin: 12px 0 8px;
                font-size: 36px;
                line-height: 1.15;
                letter-spacing: -0.02em;
            }

            .mrn-site-styles-separator-preview-current p {
                margin: 0 0 14px;
                max-width: 30ch;
                font-size: 24px;
                line-height: 1.32;
            }

            .mrn-site-styles-separator-preview-next p {
                margin: 0;
                font-size: 16px;
                line-height: 1.45;
            }

            .mrn-site-styles-separator-preview-cta {
                display: inline-flex;
                align-items: center;
                border: 1px solid currentColor;
                border-radius: 8px;
                padding: 8px 14px;
                font-size: 14px;
                font-weight: 600;
            }

            .mrn-site-styles-separator-preview-current h3,
            .mrn-site-styles-separator-preview-current p,
            .mrn-site-styles-separator-preview-current .mrn-site-styles-separator-preview-chip,
            .mrn-site-styles-separator-preview-current .mrn-site-styles-separator-preview-cta,
            .mrn-site-styles-separator-preview-next h3,
            .mrn-site-styles-separator-preview-next p,
            .mrn-site-styles-separator-preview-next .mrn-site-styles-separator-preview-chip {
                color: inherit;
            }

            .mrn-site-styles-separator-preview-accent {
                position: absolute;
                left: 0;
                right: 0;
                top: 0;
                height: 96px;
                pointer-events: none;
                margin-top: 0;
                overflow: hidden;
                z-index: 1;
            }

            .mrn-site-styles-separator-preview-accent svg {
                display: block;
                width: 100%;
                height: 100%;
            }

            .mrn-site-styles-separator-preview-note {
                margin-top: 10px;
                color: #50575e;
                font-size: 12px;
            }

            .mrn-site-styles-separator-actions {
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
                align-items: center;
            }

            @media (max-width: 1100px) {
                .mrn-site-styles-motion-fields {
                    grid-template-columns: 1fr;
                }

                .mrn-site-styles-separator-generator {
                    grid-template-columns: 1fr;
                }

                .mrn-site-styles-separator-fields {
                    grid-template-columns: 1fr;
                }
            }
        </style>
        <?php if ($has_sticky_toolbar && function_exists('mrn_sticky_toolbar_render_css')) : ?>
            <?php
            mrn_sticky_toolbar_render_css(
                array(
                    'toolbar_id' => 'mrn-site-styles-toolbar',
                    'page_class' => 'settings_page_mrn-site-styles',
                    'desktop_left' => 196,
                    'desktop_right' => 0,
                    'mobile_left' => 10,
                    'mobile_right' => 10,
                    'spacer_height' => 88,
                    'spacer_height_mobile' => 120,
                )
            );
            ?>
        <?php endif; ?>
        <h1>Site Styles</h1>
        <p>Define shared site color variables and reusable graphic elements for themes, plugins, and admin UI usage.</p>
        <?php if ('colors' === $updated_notice) : ?>
            <div class="notice notice-success is-dismissible"><p>Site colors saved.</p></div>
        <?php elseif ('graphic-elements' === $updated_notice) : ?>
            <div class="notice notice-success is-dismissible"><p>Graphic elements saved.</p></div>
        <?php elseif ('motion-presets' === $updated_notice) : ?>
            <div class="notice notice-success is-dismissible"><p>Motion presets saved.</p></div>
        <?php endif; ?>
        <?php do_action('mrn_site_styles_render_notices', $updated_notice); ?>
        <?php if (!empty($transfer_feedback['message'])) : ?>
            <div class="notice notice-<?php echo esc_attr(('error' === ($transfer_feedback['type'] ?? '')) ? 'error' : 'success'); ?> is-dismissible">
                <p><?php echo esc_html((string) $transfer_feedback['message']); ?></p>
            </div>
        <?php endif; ?>
        <?php if ($color_invalid_count > 0) : ?>
            <div class="notice notice-warning is-dismissible">
                <p>
                    <?php
                    echo esc_html(
                        sprintf(
                            _n(
                                '%d Site Color row needs attention. Invalid rows were kept below so you can correct or remove them and save again.',
                                '%d Site Color rows need attention. Invalid rows were kept below so you can correct or remove them and save again.',
                                $color_invalid_count
                            ),
                            $color_invalid_count
                        )
                    );
                    ?>
                </p>
            </div>
        <?php endif; ?>

        <?php if ($has_sticky_toolbar && function_exists('mrn_sticky_toolbar_render')) : ?>
            <?php
            $toolbar_tabs = array();

            foreach ($tabs as $tab) {
                if (!is_array($tab) || empty($tab['key']) || empty($tab['label'])) {
                    continue;
                }

                $toolbar_tabs[] = array(
                    'key' => sanitize_key((string) $tab['key']),
                    'label' => sanitize_text_field((string) $tab['label']),
                    'active' => $active_tab === sanitize_key((string) $tab['key']),
                    'icon' => sanitize_html_class((string) ($tab['icon'] ?? '')),
                );
            }

            mrn_sticky_toolbar_render(
                array(
                    'toolbar_id' => 'mrn-site-styles-toolbar',
                    'form_id' => 'mrn-site-styles-form',
                    'title' => 'Site Styles',
                    'aria_label' => 'Site Styles tabs',
                    'save_label' => 'Save Site Styles',
                    'tabs' => $toolbar_tabs,
                )
            );
            ?>
        <?php endif; ?>

        <form id="mrn-site-styles-form" method="post" action="">
            <?php wp_nonce_field('mrn_site_colors_save', 'mrn_site_colors_nonce'); ?>
            <input type="hidden" name="mrn_site_styles_section" value="<?php echo esc_attr($active_tab); ?>" />

            <div class="mrn-site-styles-panels" data-mrn-site-styles-default-tab="<?php echo esc_attr($active_tab); ?>">
                <section class="mrn-site-styles-panel" data-mrn-site-styles-panel="colors" <?php echo 'colors' === $active_tab ? '' : 'hidden'; ?>>
                    <div class="mrn-site-styles-card">
                        <h2 style="margin-top:0;">Site Colors</h2>
                        <table class="widefat striped" style="max-width:1100px;margin-top:20px;">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Hex Value</th>
                                    <th>Picker</th>
                                    <th>CSS Variable</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="mrn-site-colors-rows">
                                <?php foreach (array_values($rows) as $index => $row) : ?>
                                    <?php mrn_site_colors_render_row((int) $index, $row); ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <p class="mrn-site-styles-panel-actions">
                            <button type="button" class="button" id="mrn-site-colors-add">Add Color</button>
                        </p>
                    </div>

                    <div class="mrn-site-styles-card">
                        <h2 style="margin-top:0;">How To Use</h2>
                        <p><strong>CSS:</strong> Use <code>var(--site-color-your-slug)</code></p>
                        <p><strong>PHP value:</strong> Use <code>mrn_site_colors_get_value('your-slug')</code></p>
                        <p><strong>PHP variable name:</strong> Use <code>mrn_site_colors_get_css_var('your-slug')</code></p>
                        <p><strong>Full map:</strong> Use <code>mrn_site_colors_get_map()</code></p>
                        <p><strong>Graphic elements:</strong> Use <code>mrn_site_styles_get_graphic_elements()</code> or <code>mrn_site_styles_get_graphic_element_choices()</code></p>
                        <p><strong>Dark scroll card presets:</strong> Use <code>mrn_site_styles_get_dark_scroll_card_preset_choices()</code> in the builder and style against <code>data-mrn-effect-preset</code>.</p>
                    </div>
                </section>

                <section class="mrn-site-styles-panel" data-mrn-site-styles-panel="graphic-elements" <?php echo 'graphic-elements' === $active_tab ? '' : 'hidden'; ?>>
                    <div class="mrn-site-styles-card">
                        <h2 style="margin-top:0;">Graphic Elements</h2>
                        <p>Paste reusable CSS snippets for accent shapes and decorative elements. These definitions will feed future accent-element dropdowns.</p>
                        <div
                            class="mrn-site-styles-separator-generator"
                            id="mrn-site-styles-separator-generator"
                            data-site-colors="<?php echo esc_attr((string) wp_json_encode($separator_site_colors)); ?>"
                        >
                            <div class="mrn-site-styles-separator-fields">
                                <label class="mrn-site-styles-separator-field mrn-site-styles-separator-field--full" for="mrn-site-styles-separator-name">
                                    <span>Graphic Element Name</span>
                                    <input type="text" class="regular-text" id="mrn-site-styles-separator-name" value="Wave Separator" />
                                </label>

                                <label class="mrn-site-styles-separator-field" for="mrn-site-styles-separator-preset">
                                    <span>Shape Preset</span>
                                    <select id="mrn-site-styles-separator-preset">
                                        <option value="curve">Curve</option>
                                        <option value="wave">Wave</option>
                                        <option value="arc">Arc</option>
                                        <option value="tilt">Tilt</option>
                                        <option value="zigzag">Zigzag</option>
                                        <option value="steps">Steps</option>
                                        <option value="notch">Notch</option>
                                    </select>
                                </label>

                                <label class="mrn-site-styles-separator-field" for="mrn-site-styles-separator-space">
                                    <span>Space Override <strong id="mrn-site-styles-separator-space-value">0px</strong></span>
                                    <input id="mrn-site-styles-separator-space" type="range" min="-240" max="240" step="1" value="0" />
                                    <p class="description">Offset from the baseline position. Negative pulls upward, positive pushes downward.</p>
                                </label>

                                <label class="mrn-site-styles-separator-field" for="mrn-site-styles-separator-top-height">
                                    <span>Top Height Offset <strong id="mrn-site-styles-separator-top-height-value">0px</strong></span>
                                    <input id="mrn-site-styles-separator-top-height" type="range" min="-320" max="320" step="1" value="0" />
                                    <p class="description">`0` keeps the preset baseline. Positive grows upward into Current Row.</p>
                                </label>

                                <label class="mrn-site-styles-separator-field" for="mrn-site-styles-separator-bottom-height">
                                    <span>Bottom Height Offset <strong id="mrn-site-styles-separator-bottom-height-value">0px</strong></span>
                                    <input id="mrn-site-styles-separator-bottom-height" type="range" min="-320" max="320" step="1" value="0" />
                                    <p class="description">`0` keeps the preset baseline. Positive extends into Next Row.</p>
                                </label>

                                <label class="mrn-site-styles-separator-field" for="mrn-site-styles-separator-intensity">
                                    <span>Intensity Offset <strong id="mrn-site-styles-separator-intensity-value">0%</strong></span>
                                    <input id="mrn-site-styles-separator-intensity" type="range" min="-300" max="300" step="1" value="0" />
                                    <p class="description">`0` keeps the preset baseline. Positive bends convex, negative bends concave.</p>
                                </label>

                                <div class="mrn-site-styles-separator-field">
                                    <span>Curve Direction</span>
                                    <button type="button" class="button" id="mrn-site-styles-separator-invert" aria-pressed="true">Invert Curve: On</button>
                                    <p class="description">On fills into Current Row. Off fills into Next Row.</p>
                                </div>

                                <label class="mrn-site-styles-separator-field" for="mrn-site-styles-separator-frequency">
                                    <span>Frequency Offset <strong id="mrn-site-styles-separator-frequency-value">0</strong></span>
                                    <input id="mrn-site-styles-separator-frequency" type="range" min="-11" max="11" step="1" value="0" />
                                    <p class="description">`0` keeps the preset baseline frequency.</p>
                                </label>

                                <label class="mrn-site-styles-separator-field" for="mrn-site-styles-separator-left-depth">
                                    <span>Left Curve Depth Offset <strong id="mrn-site-styles-separator-left-depth-value">0%</strong></span>
                                    <input id="mrn-site-styles-separator-left-depth" type="range" min="-300" max="300" step="1" value="0" />
                                </label>

                                <label class="mrn-site-styles-separator-field" for="mrn-site-styles-separator-right-depth">
                                    <span>Right Curve Depth Offset <strong id="mrn-site-styles-separator-right-depth-value">0%</strong></span>
                                    <input id="mrn-site-styles-separator-right-depth" type="range" min="-300" max="300" step="1" value="0" />
                                    <p class="description">Offsets apply from each preset baseline. Negative pushes downward, positive lifts upward.</p>
                                </label>

                                <label class="mrn-site-styles-separator-field" for="mrn-site-styles-separator-fill-color">
                                    <span>Effects Fill</span>
                                    <select id="mrn-site-styles-separator-fill-color"></select>
                                </label>

                                <label class="mrn-site-styles-separator-field" for="mrn-site-styles-separator-fill-opacity">
                                    <span>Effects Fill Opacity <strong id="mrn-site-styles-separator-fill-opacity-value">100%</strong></span>
                                    <input id="mrn-site-styles-separator-fill-opacity" type="range" min="0" max="100" step="1" value="100" />
                                </label>

                                <label class="mrn-site-styles-separator-field" for="mrn-site-styles-separator-bg-color">
                                    <span>Effects Background</span>
                                    <select id="mrn-site-styles-separator-bg-color"></select>
                                </label>

                                <label class="mrn-site-styles-separator-field" for="mrn-site-styles-separator-bg-opacity">
                                    <span>Effects Background Opacity <strong id="mrn-site-styles-separator-bg-opacity-value">100%</strong></span>
                                    <input id="mrn-site-styles-separator-bg-opacity" type="range" min="0" max="100" step="1" value="100" />
                                </label>

                                <label class="mrn-site-styles-separator-field" for="mrn-site-styles-separator-current-color">
                                    <span>Current Row Color</span>
                                    <select id="mrn-site-styles-separator-current-color"></select>
                                </label>

                                <label class="mrn-site-styles-separator-field" for="mrn-site-styles-separator-next-color">
                                    <span>Next Row Color</span>
                                    <select id="mrn-site-styles-separator-next-color"></select>
                                </label>

                                <label class="mrn-site-styles-separator-field mrn-site-styles-separator-field--full" for="mrn-site-styles-separator-css">
                                    <span>Generated CSS</span>
                                    <textarea id="mrn-site-styles-separator-css" class="large-text code" readonly></textarea>
                                </label>

                                <div class="mrn-site-styles-separator-field mrn-site-styles-separator-field--full">
                                    <div class="mrn-site-styles-separator-actions">
                                        <button type="button" class="button button-primary" id="mrn-site-styles-separator-add">Add As Graphic Element</button>
                                        <p class="description" style="margin:0;">Adds a new row below with generated CSS and the computed Bottom Space.</p>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <div class="mrn-site-styles-separator-preview-shell">
                                    <div class="mrn-site-styles-separator-preview-current" id="mrn-site-styles-separator-preview-current">
                                        <span class="mrn-site-styles-separator-preview-chip">Current Row</span>
                                        <h3>Sample Current Row Heading</h3>
                                        <p>This helps you preview how the separator exits below live row content and transitions into the next row.</p>
                                        <span class="mrn-site-styles-separator-preview-cta">Sample CTA</span>
                                    </div>
                                    <div class="mrn-site-styles-separator-preview-next" id="mrn-site-styles-separator-preview-next">
                                        <span class="mrn-site-styles-separator-preview-chip">Next Row</span>
                                        <h3>Sample Next Row Heading</h3>
                                        <p>Preview mirrors the current separator output from Current Row into Next Row.</p>
                                    </div>
                                    <div class="mrn-site-styles-separator-preview-accent" id="mrn-site-styles-separator-preview-accent">
                                        <svg id="mrn-site-styles-separator-preview-accent-svg" viewBox="0 0 1000 96" preserveAspectRatio="none" aria-hidden="true" focusable="false">
                                            <path id="mrn-site-styles-separator-preview-accent-path" d="M 0 56 L 1000 56 L 1000 96 L 0 96 Z"></path>
                                        </svg>
                                    </div>
                                </div>
                                <p class="mrn-site-styles-separator-preview-note">Preview updates live without reflowing the panel layout.</p>
                            </div>
                        </div>
                        <table class="widefat striped" style="max-width:1100px;margin-top:20px;">
                            <thead>
                                <tr>
                                    <th style="width:18%;">Name</th>
                                    <th style="width:12%;">Space Override</th>
                                    <th style="width:50%;">CSS</th>
                                    <th style="width:15%;">Slug</th>
                                    <th style="width:5%;"></th>
                                </tr>
                            </thead>
                            <tbody id="mrn-site-styles-graphic-rows">
                                <?php foreach (array_values($graphic_elements) as $index => $row) : ?>
                                    <?php mrn_site_styles_render_graphic_element_row((int) $index, $row); ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <p class="mrn-site-styles-panel-actions">
                            <button type="button" class="button" id="mrn-site-styles-graphic-add">Add Graphic Element</button>
                        </p>
                    </div>
                </section>

                <section class="mrn-site-styles-panel" data-mrn-site-styles-panel="motion-presets" <?php echo 'motion-presets' === $active_tab ? '' : 'hidden'; ?>>
                    <div class="mrn-site-styles-card">
                        <h2 style="margin-top:0;">Motion Effect Presets</h2>
                        <p>Define named visual presets for row effects. The first preset family powers the <code>Darken Card On Scroll</code> row effect.</p>
                        <table class="widefat striped mrn-site-styles-motion-table" style="margin-top:20px;">
                            <thead>
                                <tr>
                                    <th style="width:22%;">Preset Name</th>
                                    <th style="width:58%;">Visual Settings</th>
                                    <th style="width:14%;">Slug</th>
                                    <th style="width:6%;"></th>
                                </tr>
                            </thead>
                            <tbody id="mrn-site-styles-motion-rows">
                                <?php foreach (array_values($dark_scroll_card_presets) as $index => $row) : ?>
                                    <?php mrn_site_styles_render_dark_scroll_card_preset_row((int) $index, $row); ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <p class="mrn-site-styles-panel-actions">
                            <button type="button" class="button" id="mrn-site-styles-motion-add">Add Motion Preset</button>
                        </p>
                    </div>
                </section>

                <?php foreach ($tabs as $tab) : ?>
                    <?php
                    if (!is_array($tab)) {
                        continue;
                    }

                    $tab_key = isset($tab['key']) ? sanitize_key((string) $tab['key']) : '';
                    if ('' === $tab_key || in_array($tab_key, array('colors', 'graphic-elements', 'motion-presets'), true)) {
                        continue;
                    }
                    ?>
                    <section class="mrn-site-styles-panel" data-mrn-site-styles-panel="<?php echo esc_attr($tab_key); ?>" <?php echo $tab_key === $active_tab ? '' : 'hidden'; ?>>
                        <?php
                        ob_start();
                        do_action('mrn_site_styles_render_tab_panel', $tab_key, $tab);
                        $extension_panel_markup = trim((string) ob_get_clean());

                        if ('' !== $extension_panel_markup) {
                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Tab panel markup is rendered by extension callbacks.
                            echo $extension_panel_markup;
                        } else {
                            ?>
                            <div class="mrn-site-styles-card">
                                <h2 style="margin-top:0;"><?php echo esc_html(isset($tab['label']) ? (string) $tab['label'] : 'Extension'); ?></h2>
                                <p>No settings are currently registered for this tab.</p>
                            </div>
                            <?php
                        }
                        ?>
                    </section>
                <?php endforeach; ?>
            </div>
        </form>

    </div>

    <script>
        (function () {
            const toolbar = document.getElementById('mrn-site-styles-toolbar');
            const tabButtons = toolbar ? Array.from(toolbar.querySelectorAll('[data-mrn-tab]')) : [];
            const tabPanels = Array.from(document.querySelectorAll('[data-mrn-site-styles-panel]'));
            const tabPanelsWrapper = document.querySelector('[data-mrn-site-styles-default-tab]');
            const settingsForm = document.getElementById('mrn-site-styles-form');
            const activeTabInput = settingsForm ? settingsForm.querySelector('input[name="mrn_site_styles_section"]') : null;
            const rowsContainer = document.getElementById('mrn-site-colors-rows');
            const addButton = document.getElementById('mrn-site-colors-add');
            const graphicRowsContainer = document.getElementById('mrn-site-styles-graphic-rows');
            const addGraphicButton = document.getElementById('mrn-site-styles-graphic-add');
            const motionRowsContainer = document.getElementById('mrn-site-styles-motion-rows');
            const addMotionButton = document.getElementById('mrn-site-styles-motion-add');
            let refreshSeparatorSiteColorOptions = function () {};
            let scheduleSeparatorRender = function () {};

            function syncFormState(tabName) {
                if (activeTabInput) {
                    activeTabInput.value = tabName;
                }
            }

            function notifyFormChanged() {
                if (!settingsForm) {
                    return;
                }

                settingsForm.dispatchEvent(new Event('input', { bubbles: true }));
                settingsForm.dispatchEvent(new Event('change', { bubbles: true }));
            }

            function isKnownTab(tabName) {
                return tabPanels.some(function (panel) {
                    return panel.getAttribute('data-mrn-site-styles-panel') === tabName;
                });
            }

            function setActiveTab(tabName, shouldUpdateHash) {
                if (!isKnownTab(tabName)) {
                    tabName = tabPanelsWrapper ? (tabPanelsWrapper.getAttribute('data-mrn-site-styles-default-tab') || 'colors') : 'colors';
                }

                tabButtons.forEach(function (button) {
                    const isActive = button.getAttribute('data-mrn-tab') === tabName;
                    button.classList.toggle('is-active', isActive);
                    button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
                });

                tabPanels.forEach(function (panel) {
                    const isActive = panel.getAttribute('data-mrn-site-styles-panel') === tabName;
                    panel.hidden = !isActive;
                });

                syncFormState(tabName);

                if (shouldUpdateHash && window.location.hash !== '#tab-' + tabName) {
                    window.history.replaceState(null, '', '#tab-' + tabName);
                }
            }

            if (tabButtons.length && tabPanels.length && tabPanelsWrapper) {
                const defaultTab = tabPanelsWrapper.getAttribute('data-mrn-site-styles-default-tab') || 'colors';
                const hashTab = window.location.hash ? window.location.hash.replace(/^#tab-/, '').replace(/^#/, '') : '';
                const initialTab = isKnownTab(hashTab) ? hashTab : defaultTab;

                tabButtons.forEach(function (button) {
                    button.addEventListener('click', function () {
                        setActiveTab(button.getAttribute('data-mrn-tab') || 'colors', true);
                    });
                });

                window.addEventListener('hashchange', function () {
                    const nextTab = window.location.hash ? window.location.hash.replace(/^#tab-/, '').replace(/^#/, '') : defaultTab;
                    setActiveTab(nextTab, false);
                });

                setActiveTab(initialTab, false);
            }

            if (!rowsContainer || !addButton || !graphicRowsContainer || !addGraphicButton || !motionRowsContainer || !addMotionButton) {
                return;
            }

            function clamp(value, min, max) {
                return Math.min(Math.max(value, min), max);
            }

            function slugify(value) {
                return value
                    .toLowerCase()
                    .trim()
                    .replace(/[^a-z0-9]+/g, '-')
                    .replace(/^-+|-+$/g, '') || 'color';
            }

            function normalizeHexValue(value) {
                const trimmed = String(value || '').trim();
                const raw = trimmed.replace(/^#/, '').toUpperCase();

                if (!/^[0-9A-F]{3}([0-9A-F]{3})?$/.test(raw)) {
                    return '';
                }

                if (3 === raw.length) {
                    return '#' + raw.split('').map(function (character) {
                        return character + character;
                    }).join('');
                }

                return '#' + raw;
            }

            function readNumberValue(input, fallback) {
                const parsed = Number.parseFloat(input.value);
                return Number.isFinite(parsed) ? parsed : fallback;
            }

            function colorFromHexWithOpacity(colorValue, opacityPercent) {
                if ('transparent' === colorValue) {
                    return 'rgba(0, 0, 0, 0)';
                }

                const normalized = normalizeHexValue(colorValue);
                if ('' === normalized) {
                    return 'rgba(0, 0, 0, 0)';
                }

                const channels = normalized.slice(1);
                const red = Number.parseInt(channels.slice(0, 2), 16);
                const green = Number.parseInt(channels.slice(2, 4), 16);
                const blue = Number.parseInt(channels.slice(4, 6), 16);
                const alpha = clamp(opacityPercent, 0, 100) / 100;
                const alphaValue = alpha.toFixed(3).replace(/0+$/, '').replace(/\.$/, '');

                return 'rgba(' + red + ', ' + green + ', ' + blue + ', ' + ('' === alphaValue ? '0' : alphaValue) + ')';
            }

            function getContrastTextColor(colorValue) {
                const normalized = normalizeHexValue(colorValue);
                if ('' === normalized) {
                    return '#111827';
                }

                const channels = normalized.slice(1);
                const red = Number.parseInt(channels.slice(0, 2), 16);
                const green = Number.parseInt(channels.slice(2, 4), 16);
                const blue = Number.parseInt(channels.slice(4, 6), 16);
                const luminance = ((red * 299) + (green * 587) + (blue * 114)) / 1000;

                return luminance > 150 ? '#111827' : '#FFFFFF';
            }

            function updateRow(row) {
                const nameInput = row.querySelector('.mrn-site-colors-name');
                const slugInput = row.querySelector('.mrn-site-colors-slug');
                const valueInput = row.querySelector('.mrn-site-colors-value');
                const picker = row.querySelector('.mrn-site-colors-picker');
                const varOutput = row.querySelector('.mrn-site-colors-var');
                const slug = slugify(nameInput.value || slugInput.value);

                function getPickerValue(value) {
                    const normalized = normalizeHexValue(value);
                    if ('' !== normalized) {
                        return normalized;
                    }

                    return '#000000';
                }

                slugInput.value = slug;
                valueInput.value = '' !== normalizeHexValue(valueInput.value)
                    ? normalizeHexValue(valueInput.value)
                    : String(valueInput.value).trim().toUpperCase();
                picker.value = getPickerValue(valueInput.value);
                varOutput.textContent = '--site-color-' + slug;
                refreshSeparatorSiteColorOptions();
                scheduleSeparatorRender();
            }

            function updateGraphicRow(row) {
                const nameInput = row.querySelector('.mrn-site-styles-graphic-name');
                const slugInput = row.querySelector('.mrn-site-styles-graphic-slug');
                const tokenOutput = row.querySelector('.mrn-site-styles-graphic-token');
                const slug = slugify(nameInput.value || slugInput.value || 'graphic-element');

                slugInput.value = slug;
                tokenOutput.textContent = slug;
            }

            function bindRow(row) {
                const nameInput = row.querySelector('.mrn-site-colors-name');
                const valueInput = row.querySelector('.mrn-site-colors-value');
                const picker = row.querySelector('.mrn-site-colors-picker');
                const removeButton = row.querySelector('.mrn-site-colors-remove');

                nameInput.addEventListener('input', function () {
                    updateRow(row);
                });

                valueInput.addEventListener('input', function () {
                    updateRow(row);
                });

                picker.addEventListener('input', function () {
                    valueInput.value = picker.value.toUpperCase();
                    updateRow(row);
                });

                removeButton.addEventListener('click', function () {
                    row.remove();
                    notifyFormChanged();
                    refreshSeparatorSiteColorOptions();
                    scheduleSeparatorRender();
                });

                updateRow(row);
            }

            function bindGraphicRow(row) {
                const nameInput = row.querySelector('.mrn-site-styles-graphic-name');
                const removeButton = row.querySelector('.mrn-site-styles-graphic-remove');

                nameInput.addEventListener('input', function () {
                    updateGraphicRow(row);
                });

                removeButton.addEventListener('click', function () {
                    row.remove();
                    notifyFormChanged();
                });

                updateGraphicRow(row);
            }

            function updateMotionRow(row) {
                const nameInput = row.querySelector('.mrn-site-styles-motion-name');
                const slugInput = row.querySelector('.mrn-site-styles-motion-slug');
                const tokenOutput = row.querySelector('.mrn-site-styles-motion-token');
                const slug = slugify(nameInput.value || slugInput.value || 'dark-card-preset');

                slugInput.value = slug;
                tokenOutput.textContent = slug;
            }

            function bindMotionRow(row) {
                const nameInput = row.querySelector('.mrn-site-styles-motion-name');
                const removeButton = row.querySelector('.mrn-site-styles-motion-remove');

                nameInput.addEventListener('input', function () {
                    updateMotionRow(row);
                });

                removeButton.addEventListener('click', function () {
                    row.remove();
                    notifyFormChanged();
                });

                updateMotionRow(row);
            }

            Array.from(rowsContainer.querySelectorAll('.mrn-site-colors-row')).forEach(bindRow);
            Array.from(graphicRowsContainer.querySelectorAll('.mrn-site-styles-graphic-row')).forEach(bindGraphicRow);
            Array.from(motionRowsContainer.querySelectorAll('.mrn-site-styles-motion-row')).forEach(bindMotionRow);

            let nextColorIndex = rowsContainer.querySelectorAll('.mrn-site-colors-row').length;
            let nextGraphicIndex = graphicRowsContainer.querySelectorAll('.mrn-site-styles-graphic-row').length;
            let nextMotionIndex = motionRowsContainer.querySelectorAll('.mrn-site-styles-motion-row').length;

            function createGraphicRow(rowData) {
                const index = nextGraphicIndex;
                nextGraphicIndex += 1;

                const row = document.createElement('tr');
                row.className = 'mrn-site-styles-graphic-row';
                row.innerHTML = `
                    <td style="vertical-align:top;">
                        <input type="text" class="regular-text mrn-site-styles-graphic-name" name="mrn_site_graphic_elements[${index}][name]" value="" />
                        <input type="hidden" class="mrn-site-styles-graphic-slug" name="mrn_site_graphic_elements[${index}][slug]" value="" />
                    </td>
                    <td style="vertical-align:top;">
                        <input type="text" class="regular-text code mrn-site-styles-graphic-space" name="mrn_site_graphic_elements[${index}][space]" value="" />
                        <p class="description" style="margin:6px 0 0;">Optional bottom spacing override.</p>
                    </td>
                    <td style="vertical-align:top;">
                        <textarea class="large-text code mrn-site-styles-graphic-css" name="mrn_site_graphic_elements[${index}][css]" rows="8"></textarea>
                    </td>
                    <td style="vertical-align:top;">
                        <code class="mrn-site-styles-graphic-token">graphic-element</code>
                    </td>
                    <td style="vertical-align:top;">
                        <button type="button" class="button-link-delete mrn-site-styles-graphic-remove">Remove</button>
                    </td>
                `;

                const nameInput = row.querySelector('.mrn-site-styles-graphic-name');
                const spaceInput = row.querySelector('.mrn-site-styles-graphic-space');
                const cssInput = row.querySelector('.mrn-site-styles-graphic-css');

                if (rowData && 'string' === typeof rowData.name) {
                    nameInput.value = rowData.name;
                }

                if (rowData && 'string' === typeof rowData.space) {
                    spaceInput.value = rowData.space;
                }

                if (rowData && 'string' === typeof rowData.css) {
                    cssInput.value = rowData.css;
                }

                graphicRowsContainer.appendChild(row);
                bindGraphicRow(row);
                notifyFormChanged();

                return row;
            }

            addButton.addEventListener('click', function () {
                const index = nextColorIndex;
                nextColorIndex += 1;
                const row = document.createElement('tr');
                row.className = 'mrn-site-colors-row';
                row.innerHTML = `
                    <td>
                        <input type="text" class="regular-text mrn-site-colors-name" name="mrn_site_colors[${index}][name]" value="" />
                        <input type="hidden" class="mrn-site-colors-slug" name="mrn_site_colors[${index}][slug]" value="" />
                    </td>
                    <td>
                        <input type="text" class="regular-text code mrn-site-colors-value" name="mrn_site_colors[${index}][value]" value="" />
                    </td>
                    <td>
                        <input type="color" class="mrn-site-colors-picker" value="#000000" />
                    </td>
                    <td>
                        <code class="mrn-site-colors-var">--site-color-color</code>
                    </td>
                    <td>
                        <button type="button" class="button-link-delete mrn-site-colors-remove">Remove</button>
                    </td>
                `;

                rowsContainer.appendChild(row);
                bindRow(row);
                notifyFormChanged();
                refreshSeparatorSiteColorOptions();
                scheduleSeparatorRender();
            });

            addGraphicButton.addEventListener('click', function () {
                createGraphicRow({
                    name: '',
                    space: '',
                    css: '',
                });
            });

            addMotionButton.addEventListener('click', function () {
                const index = nextMotionIndex;
                nextMotionIndex += 1;
                const row = document.createElement('tr');
                row.className = 'mrn-site-styles-motion-row';
                row.innerHTML = `
                    <td style="vertical-align:top;">
                        <input type="text" class="regular-text mrn-site-styles-motion-name" name="mrn_site_dark_scroll_card_presets[${index}][name]" value="" />
                        <input type="hidden" class="mrn-site-styles-motion-slug" name="mrn_site_dark_scroll_card_presets[${index}][slug]" value="" />
                        <p class="description" style="margin:6px 0 0;">Shown to editors as the effect preset name.</p>
                    </td>
                    <td style="vertical-align:top;">
                        <div class="mrn-site-styles-motion-fields">
                            <label><span>Background</span><input type="text" class="regular-text code" name="mrn_site_dark_scroll_card_presets[${index}][background]" value="" /></label>
                            <label><span>Text</span><input type="text" class="regular-text code" name="mrn_site_dark_scroll_card_presets[${index}][text]" value="" /></label>
                            <label><span>Muted Text</span><input type="text" class="regular-text code" name="mrn_site_dark_scroll_card_presets[${index}][muted_text]" value="" /></label>
                            <label><span>Button Background</span><input type="text" class="regular-text code" name="mrn_site_dark_scroll_card_presets[${index}][button_background]" value="" /></label>
                            <label><span>Button Text</span><input type="text" class="regular-text code" name="mrn_site_dark_scroll_card_presets[${index}][button_text]" value="" /></label>
                            <label><span>Border Alpha</span><input type="text" class="regular-text code" name="mrn_site_dark_scroll_card_presets[${index}][border_alpha]" value="" /></label>
                            <label><span>Shadow Alpha</span><input type="text" class="regular-text code" name="mrn_site_dark_scroll_card_presets[${index}][shadow_alpha]" value="" /></label>
                            <label><span>Image Brightness</span><input type="text" class="regular-text code" name="mrn_site_dark_scroll_card_presets[${index}][image_brightness]" value="" /></label>
                            <label><span>Image Saturation</span><input type="text" class="regular-text code" name="mrn_site_dark_scroll_card_presets[${index}][image_saturation]" value="" /></label>
                        </div>
                    </td>
                    <td style="vertical-align:top;">
                        <code class="mrn-site-styles-motion-token">dark-card-preset</code>
                    </td>
                    <td style="vertical-align:top;">
                        <button type="button" class="button-link-delete mrn-site-styles-motion-remove">Remove</button>
                    </td>
                `;

                motionRowsContainer.appendChild(row);
                bindMotionRow(row);
                notifyFormChanged();
            });

            function initSeparatorGenerator() {
                const generator = document.getElementById('mrn-site-styles-separator-generator');
                if (!generator) {
                    return;
                }

                const separatorName = document.getElementById('mrn-site-styles-separator-name');
                const separatorPreset = document.getElementById('mrn-site-styles-separator-preset');
                const separatorSpace = document.getElementById('mrn-site-styles-separator-space');
                const separatorTopHeight = document.getElementById('mrn-site-styles-separator-top-height');
                const separatorBottomHeight = document.getElementById('mrn-site-styles-separator-bottom-height');
                const separatorIntensity = document.getElementById('mrn-site-styles-separator-intensity');
                const separatorInvertToggle = document.getElementById('mrn-site-styles-separator-invert');
                const separatorFrequency = document.getElementById('mrn-site-styles-separator-frequency');
                const separatorLeftDepth = document.getElementById('mrn-site-styles-separator-left-depth');
                const separatorRightDepth = document.getElementById('mrn-site-styles-separator-right-depth');
                const separatorFillColor = document.getElementById('mrn-site-styles-separator-fill-color');
                const separatorFillOpacity = document.getElementById('mrn-site-styles-separator-fill-opacity');
                const separatorBackgroundColor = document.getElementById('mrn-site-styles-separator-bg-color');
                const separatorBackgroundOpacity = document.getElementById('mrn-site-styles-separator-bg-opacity');
                const separatorCurrentColor = document.getElementById('mrn-site-styles-separator-current-color');
                const separatorNextColor = document.getElementById('mrn-site-styles-separator-next-color');
                const separatorCssOutput = document.getElementById('mrn-site-styles-separator-css');
                const separatorAddButton = document.getElementById('mrn-site-styles-separator-add');
                const previewCurrent = document.getElementById('mrn-site-styles-separator-preview-current');
                const previewNext = document.getElementById('mrn-site-styles-separator-preview-next');
                const previewAccent = document.getElementById('mrn-site-styles-separator-preview-accent');
                const previewAccentSvg = document.getElementById('mrn-site-styles-separator-preview-accent-svg');
                const previewAccentPath = document.getElementById('mrn-site-styles-separator-preview-accent-path');

                const separatorSpaceValue = document.getElementById('mrn-site-styles-separator-space-value');
                const separatorTopHeightValue = document.getElementById('mrn-site-styles-separator-top-height-value');
                const separatorBottomHeightValue = document.getElementById('mrn-site-styles-separator-bottom-height-value');
                const separatorIntensityValue = document.getElementById('mrn-site-styles-separator-intensity-value');
                const separatorFrequencyValue = document.getElementById('mrn-site-styles-separator-frequency-value');
                const separatorLeftDepthValue = document.getElementById('mrn-site-styles-separator-left-depth-value');
                const separatorRightDepthValue = document.getElementById('mrn-site-styles-separator-right-depth-value');
                const separatorFillOpacityValue = document.getElementById('mrn-site-styles-separator-fill-opacity-value');
                const separatorBackgroundOpacityValue = document.getElementById('mrn-site-styles-separator-bg-opacity-value');

                if (
                    !separatorName
                    || !separatorPreset
                    || !separatorSpace
                    || !separatorTopHeight
                    || !separatorBottomHeight
                    || !separatorIntensity
                    || !separatorInvertToggle
                    || !separatorFrequency
                    || !separatorLeftDepth
                    || !separatorRightDepth
                    || !separatorFillColor
                    || !separatorFillOpacity
                    || !separatorBackgroundColor
                    || !separatorBackgroundOpacity
                    || !separatorCurrentColor
                    || !separatorNextColor
                    || !separatorCssOutput
                    || !separatorAddButton
                    || !previewCurrent
                    || !previewNext
                    || !previewAccent
                    || !previewAccentSvg
                    || !previewAccentPath
                ) {
                    return;
                }

                let datasetSiteColors = [];
                let separatorCurveInverted = true;

                function formatSignedValue(value, suffix) {
                    const rounded = Math.round(Number(value) || 0);

                    if (0 === rounded) {
                        return '0' + suffix;
                    }

                    return (rounded > 0 ? '+' : '') + String(rounded) + suffix;
                }

                function getSeparatorPresetDefaults(preset) {
                    const defaults = {
                        curve: {
                            topHeight: 96,
                            bottomHeight: 0,
                            intensity: 100,
                            frequency: 1,
                            leftDepth: 0,
                            rightDepth: 0,
                        },
                        wave: {
                            topHeight: 96,
                            bottomHeight: 0,
                            intensity: 100,
                            frequency: 3,
                            leftDepth: 0,
                            rightDepth: 0,
                        },
                        arc: {
                            topHeight: 96,
                            bottomHeight: 0,
                            intensity: 100,
                            frequency: 1,
                            leftDepth: 0,
                            rightDepth: 0,
                        },
                        tilt: {
                            topHeight: 96,
                            bottomHeight: 0,
                            intensity: 0,
                            frequency: 1,
                            leftDepth: -120,
                            rightDepth: 120,
                        },
                        zigzag: {
                            topHeight: 96,
                            bottomHeight: 0,
                            intensity: 100,
                            frequency: 4,
                            leftDepth: 0,
                            rightDepth: 0,
                        },
                        steps: {
                            topHeight: 96,
                            bottomHeight: 0,
                            intensity: 100,
                            frequency: 4,
                            leftDepth: 0,
                            rightDepth: 0,
                        },
                        notch: {
                            topHeight: 96,
                            bottomHeight: 0,
                            intensity: 100,
                            frequency: 2,
                            leftDepth: 0,
                            rightDepth: 0,
                        },
                    };

                    if (Object.prototype.hasOwnProperty.call(defaults, preset)) {
                        return defaults[preset];
                    }

                    return defaults.curve;
                }

                function resetSeparatorOffsetsToZero() {
                    separatorSpace.value = '0';
                    separatorTopHeight.value = '0';
                    separatorBottomHeight.value = '0';
                    separatorIntensity.value = '0';
                    separatorFrequency.value = '0';
                    separatorLeftDepth.value = '0';
                    separatorRightDepth.value = '0';
                }

                try {
                    const parsed = JSON.parse(generator.getAttribute('data-site-colors') || '[]');
                    if (Array.isArray(parsed)) {
                        datasetSiteColors = parsed.filter(function (item) {
                            return item && 'string' === typeof item.label && 'string' === typeof item.value;
                        }).map(function (item) {
                            return {
                                label: item.label,
                                value: normalizeHexValue(item.value),
                            };
                        }).filter(function (item) {
                            return '' !== item.value;
                        });
                    }
                } catch (error) {
                    datasetSiteColors = [];
                }

                function getSiteColorOptionsFromRows() {
                    const options = [];
                    const used = {};

                    Array.from(rowsContainer.querySelectorAll('.mrn-site-colors-row')).forEach(function (row) {
                        const nameInput = row.querySelector('.mrn-site-colors-name');
                        const valueInput = row.querySelector('.mrn-site-colors-value');
                        const name = nameInput ? String(nameInput.value || '').trim() : '';
                        const value = valueInput ? normalizeHexValue(valueInput.value) : '';

                        if ('' === name || '' === value || used[value]) {
                            return;
                        }

                        used[value] = true;
                        options.push({
                            label: name + ' (' + value + ')',
                            value: value,
                        });
                    });

                    if (options.length > 0) {
                        return options;
                    }

                    if (datasetSiteColors.length > 0) {
                        return datasetSiteColors;
                    }

                    return [
                        { label: 'White (#FFFFFF)', value: '#FFFFFF' },
                        { label: 'Slate 800 (#1F2937)', value: '#1F2937' },
                    ];
                }

                function getLightestColor(options) {
                    let best = options[0] ? options[0].value : '#FFFFFF';
                    let bestValue = -1;

                    options.forEach(function (item) {
                        const normalized = normalizeHexValue(item.value);
                        if ('' === normalized) {
                            return;
                        }

                        const channels = normalized.slice(1);
                        const red = Number.parseInt(channels.slice(0, 2), 16);
                        const green = Number.parseInt(channels.slice(2, 4), 16);
                        const blue = Number.parseInt(channels.slice(4, 6), 16);
                        const brightness = (red * 299) + (green * 587) + (blue * 114);

                        if (brightness > bestValue) {
                            bestValue = brightness;
                            best = normalized;
                        }
                    });

                    return best;
                }

                function getDarkestColor(options) {
                    let best = options[0] ? options[0].value : '#1F2937';
                    let bestValue = Infinity;

                    options.forEach(function (item) {
                        const normalized = normalizeHexValue(item.value);
                        if ('' === normalized) {
                            return;
                        }

                        const channels = normalized.slice(1);
                        const red = Number.parseInt(channels.slice(0, 2), 16);
                        const green = Number.parseInt(channels.slice(2, 4), 16);
                        const blue = Number.parseInt(channels.slice(4, 6), 16);
                        const brightness = (red * 299) + (green * 587) + (blue * 114);

                        if (brightness < bestValue) {
                            bestValue = brightness;
                            best = normalized;
                        }
                    });

                    return best;
                }

                function populateColorSelect(select, options, selectedValue, includeTransparent) {
                    const fragment = document.createDocumentFragment();
                    const normalizedSelected = 'transparent' === selectedValue ? 'transparent' : normalizeHexValue(selectedValue);
                    const optionValues = {};

                    if (includeTransparent) {
                        const transparentOption = document.createElement('option');
                        transparentOption.value = 'transparent';
                        transparentOption.textContent = 'Transparent';
                        fragment.appendChild(transparentOption);
                    }

                    options.forEach(function (item) {
                        if ('' === item.value || optionValues[item.value]) {
                            return;
                        }

                        optionValues[item.value] = true;
                        const option = document.createElement('option');
                        option.value = item.value;
                        option.textContent = item.label;
                        fragment.appendChild(option);
                    });

                    select.innerHTML = '';
                    select.appendChild(fragment);

                    if ('transparent' === normalizedSelected && includeTransparent) {
                        select.value = 'transparent';
                        return;
                    }

                    if (normalizedSelected && optionValues[normalizedSelected]) {
                        select.value = normalizedSelected;
                        return;
                    }

                    if (includeTransparent) {
                        select.value = 'transparent';
                    } else if (options[0] && options[0].value) {
                        select.value = options[0].value;
                    }
                }

                function limitY(value, height) {
                    return clamp(value, 0, height);
                }

                function getShapeMetrics(config, height) {
                    const intensity = clamp(config.intensity, -300, 300);
                    const scale = clamp(Math.abs(intensity) / 100, 0, 3);

                    const leftNorm = clamp(config.leftDepth / 200, -1.5, 1.5);
                    const rightNorm = clamp(config.rightDepth / 200, -1.5, 1.5);
                    const fallbackTopHeight = height * 0.35;
                    const safeTopHeight = clamp(
                        Number.isFinite(config.topHeight) ? Number(config.topHeight) : fallbackTopHeight,
                        0,
                        height
                    );
                    const safeBottomHeight = clamp(
                        Number.isFinite(config.bottomHeight) ? Number(config.bottomHeight) : (height - safeTopHeight),
                        0,
                        height
                    );
                    const baseline = clamp(safeTopHeight, 0, height);
                    const upRange = Math.max(safeTopHeight, 1);
                    const downRange = Math.max(safeBottomHeight, 1);
                    const effectiveUpRange = upRange * scale;
                    const effectiveDownRange = downRange * scale;
                    const sideScale = 0.95;

                    function resolveSideY(norm) {
                        if (norm >= 0) {
                            return baseline - (effectiveUpRange * sideScale * norm);
                        }

                        return baseline + (effectiveDownRange * sideScale * Math.abs(norm));
                    }

                    const startY = resolveSideY(leftNorm);
                    const endY = resolveSideY(rightNorm);
                    const bendDirection = intensity >= 0 ? -1 : 1;
                    const bendRange = Math.max(effectiveUpRange, effectiveDownRange);
                    const bendOffset = bendRange * 0.9 * bendDirection;

                    return {
                        scale: scale,
                        leftNorm: leftNorm,
                        rightNorm: rightNorm,
                        baseline: baseline,
                        amplitude: Math.min(effectiveUpRange, effectiveDownRange),
                        upRange: upRange,
                        downRange: downRange,
                        effectiveUpRange: effectiveUpRange,
                        effectiveDownRange: effectiveDownRange,
                        bendOffset: bendOffset,
                        startY: startY,
                        endY: endY,
                    };
                }

                function buildCurveLine(metrics, width, height) {
                    const yStart = limitY(metrics.startY, height);
                    const yEnd = limitY(metrics.endY, height);
                    const midpoint = (yStart + yEnd) * 0.5;
                    const maxOffset = Math.max(height * 0.9, Math.abs(yEnd - yStart) * 0.65);
                    const controlY = limitY(midpoint + clamp(metrics.bendOffset, -maxOffset, maxOffset), height);
                    const steps = 96;
                    let path = 'M 0 ' + yStart.toFixed(2);

                    for (let step = 1; step <= steps; step += 1) {
                        const t = step / steps;
                        const inverse = 1 - t;
                        const x = width * t;
                        const y = (inverse * inverse * yStart)
                            + (2 * inverse * t * controlY)
                            + (t * t * yEnd);

                        path += ' L ' + x.toFixed(2) + ' ' + limitY(y, height).toFixed(2);
                    }

                    return path;
                }

                function buildWaveLine(metrics, config, width, height) {
                    const frequency = clamp(Math.round(config.frequency), 1, 12);
                    const waveAmplitude = height * 0.16 * metrics.scale;
                    const steps = 80;
                    let path = 'M 0 ' + limitY(metrics.startY, height).toFixed(2);

                    for (let step = 1; step <= steps; step += 1) {
                        const t = step / steps;
                        const x = width * t;
                        const linearY = metrics.startY + ((metrics.endY - metrics.startY) * t);
                        const waveY = linearY + (Math.sin((t * frequency * Math.PI * 2) - (Math.PI / 2)) * waveAmplitude);
                        path += ' L ' + x.toFixed(2) + ' ' + limitY(waveY, height).toFixed(2);
                    }

                    return path;
                }

                function buildTiltLine(metrics, width, height) {
                    return 'M 0 ' + limitY(metrics.startY, height).toFixed(2)
                        + ' L ' + width.toFixed(2) + ' ' + limitY(metrics.endY, height).toFixed(2);
                }

                function buildArcLine(metrics, width, height) {
                    const controlPull = (metrics.leftNorm + metrics.rightNorm) * 0.5;
                    const controlY = ((metrics.startY + metrics.endY) * 0.5) - (metrics.amplitude * controlPull * 0.8);
                    const steps = 52;
                    let path = 'M 0 ' + limitY(metrics.startY, height).toFixed(2);

                    for (let step = 1; step <= steps; step += 1) {
                        const t = step / steps;
                        const x = width * t;
                        const inverse = 1 - t;
                        const y = (inverse * inverse * metrics.startY)
                            + (2 * inverse * t * controlY)
                            + (t * t * metrics.endY);
                        path += ' L ' + x.toFixed(2) + ' ' + limitY(y, height).toFixed(2);
                    }

                    return path;
                }

                function buildZigzagLine(metrics, config, width, height) {
                    const segments = clamp(Math.round(config.frequency) * 6, 6, 72);
                    const zigAmplitude = height * 0.12 * Math.max(metrics.scale, 0.45);
                    let path = 'M 0 ' + limitY(metrics.startY, height).toFixed(2);

                    for (let segment = 1; segment <= segments; segment += 1) {
                        const t = segment / segments;
                        const x = width * t;
                        const linearY = metrics.startY + ((metrics.endY - metrics.startY) * t);
                        const direction = segment % 2 === 0 ? 1 : -1;
                        const y = linearY + (direction * zigAmplitude);
                        path += ' L ' + x.toFixed(2) + ' ' + limitY(y, height).toFixed(2);
                    }

                    return path;
                }

                function buildStepsLine(metrics, config, width, height) {
                    const steps = clamp(Math.round(config.frequency) * 3, 2, 36);
                    let path = 'M 0 ' + limitY(metrics.startY, height).toFixed(2);
                    let lastY = metrics.startY;

                    for (let index = 1; index <= steps; index += 1) {
                        const t = index / steps;
                        const x = width * t;
                        const y = metrics.startY + ((metrics.endY - metrics.startY) * t);
                        path += ' L ' + x.toFixed(2) + ' ' + limitY(lastY, height).toFixed(2);
                        path += ' L ' + x.toFixed(2) + ' ' + limitY(y, height).toFixed(2);
                        lastY = y;
                    }

                    return path;
                }

                function buildNotchLine(metrics, config, width, height) {
                    const notches = clamp(Math.round(config.frequency), 1, 8);
                    const notchDepth = height * 0.22 * Math.max(metrics.scale, 0.35);
                    const span = width / notches;
                    let path = 'M 0 ' + limitY(metrics.startY, height).toFixed(2);

                    for (let index = 1; index <= notches; index += 1) {
                        const fromX = (index - 1) * span;
                        const toX = index * span;
                        const midX = fromX + (span / 2);
                        const fromY = metrics.startY + ((metrics.endY - metrics.startY) * ((index - 1) / notches));
                        const toY = metrics.startY + ((metrics.endY - metrics.startY) * (index / notches));
                        const midY = ((fromY + toY) * 0.5) + notchDepth;

                        path += ' L ' + midX.toFixed(2) + ' ' + limitY(midY, height).toFixed(2);
                        path += ' L ' + toX.toFixed(2) + ' ' + limitY(toY, height).toFixed(2);
                    }

                    return path;
                }

                function buildSeparatorPath(config) {
                    const width = 1000;
                    const height = clamp(Math.round(config.height), 12, 640);
                    const metrics = getShapeMetrics(config, height);
                    let topLine = '';

                    switch (config.preset) {
                        case 'wave':
                            topLine = buildWaveLine(metrics, config, width, height);
                            break;
                        case 'arc':
                            topLine = buildArcLine(metrics, width, height);
                            break;
                        case 'tilt':
                            topLine = buildTiltLine(metrics, width, height);
                            break;
                        case 'zigzag':
                            topLine = buildZigzagLine(metrics, config, width, height);
                            break;
                        case 'steps':
                            topLine = buildStepsLine(metrics, config, width, height);
                            break;
                        case 'notch':
                            topLine = buildNotchLine(metrics, config, width, height);
                            break;
                        case 'curve':
                        default:
                            topLine = buildCurveLine(metrics, width, height);
                            break;
                    }

                    if (config.invertCurve) {
                        return topLine + ' L ' + width.toFixed(2) + ' 0.00 L 0 0.00 Z';
                    }

                    return topLine + ' L ' + width.toFixed(2) + ' ' + height.toFixed(2) + ' L 0 ' + height.toFixed(2) + ' Z';
                }

                function buildSeparatorSvgDataUri(config) {
                    const fillColor = colorFromHexWithOpacity(config.fillColor, config.fillOpacity);
                    const path = buildSeparatorPath(config);
                    const svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 '
                        + clamp(Math.round(config.height), 12, 640)
                        + '" preserveAspectRatio="none"><path fill="'
                        + fillColor
                        + '" d="'
                        + path
                        + '"/></svg>';

                    return 'data:image/svg+xml;utf8,' + encodeURIComponent(svg);
                }

                function buildSeparatorCss(config, slug) {
                    const safeTopHeight = clamp(Math.round(config.topHeight), 0, 320);
                    const safeBottomHeight = clamp(Math.round(config.bottomHeight), 0, 320);
                    const safeHeight = clamp(safeTopHeight + safeBottomHeight, 12, 640);
                    const safeSpaceOffset = clamp(Math.round(config.space), -240, 240);
                    const accentTranslate = safeBottomHeight + safeSpaceOffset;
                    const reservedSpace = Math.max(accentTranslate, 0);
                    const backgroundColor = colorFromHexWithOpacity(config.backgroundColor, config.backgroundOpacity);
                    const svgDataUri = buildSeparatorSvgDataUri({
                        preset: config.preset,
                        height: safeHeight,
                        topHeight: safeTopHeight,
                        bottomHeight: safeBottomHeight,
                        intensity: config.intensity,
                        frequency: config.frequency,
                        leftDepth: config.leftDepth,
                        rightDepth: config.rightDepth,
                        invertCurve: config.invertCurve,
                        fillColor: config.fillColor,
                        fillOpacity: config.fillOpacity,
                    });
                    const selector = '.has-bottom-accent[data-bottom-accent="' + slug + '"]';

                    return [
                        selector + '{--mrn-accent-space:' + reservedSpace + 'px;position:relative;overflow:visible;}',
                        selector + ' > *{position:relative;z-index:1;}',
                        selector + '::after{content:"";position:absolute;left:0;right:0;bottom:0;height:' + safeHeight + 'px;transform:translateY(' + accentTranslate + 'px);pointer-events:none;z-index:0;background-color:' + backgroundColor + ';background-image:url("' + svgDataUri + '");background-repeat:no-repeat;background-position:50% 50%;background-size:100% 100%;}',
                    ].join('\n');
                }

                function readGeneratorConfig() {
                    const preset = String(separatorPreset.value || 'curve');
                    const defaults = getSeparatorPresetDefaults(preset);
                    const topOffset = clamp(readNumberValue(separatorTopHeight, 0), -320, 320);
                    const bottomOffset = clamp(readNumberValue(separatorBottomHeight, 0), -320, 320);
                    const intensityOffset = clamp(readNumberValue(separatorIntensity, 0), -300, 300);
                    const frequencyOffset = clamp(readNumberValue(separatorFrequency, 0), -11, 11);
                    const leftDepthOffset = clamp(readNumberValue(separatorLeftDepth, 0), -300, 300);
                    const rightDepthOffset = clamp(readNumberValue(separatorRightDepth, 0), -300, 300);
                    const topHeight = clamp(defaults.topHeight + topOffset, 0, 320);
                    const bottomHeight = clamp(defaults.bottomHeight + bottomOffset, 0, 320);
                    const intensity = clamp(defaults.intensity + intensityOffset, -300, 300);
                    const frequency = clamp(defaults.frequency + frequencyOffset, 1, 12);
                    const leftDepth = clamp(defaults.leftDepth + leftDepthOffset, -300, 300);
                    const rightDepth = clamp(defaults.rightDepth + rightDepthOffset, -300, 300);

                    return {
                        name: String(separatorName.value || '').trim() || 'Wave Separator',
                        preset: preset,
                        space: clamp(readNumberValue(separatorSpace, 0), -240, 240),
                        topOffset: topOffset,
                        bottomOffset: bottomOffset,
                        intensityOffset: intensityOffset,
                        frequencyOffset: frequencyOffset,
                        leftDepthOffset: leftDepthOffset,
                        rightDepthOffset: rightDepthOffset,
                        topHeight: topHeight,
                        bottomHeight: bottomHeight,
                        height: clamp(topHeight + bottomHeight, 12, 640),
                        intensity: intensity,
                        invertCurve: separatorCurveInverted,
                        frequency: frequency,
                        leftDepth: leftDepth,
                        rightDepth: rightDepth,
                        fillColor: String(separatorFillColor.value || '#FFFFFF'),
                        fillOpacity: clamp(readNumberValue(separatorFillOpacity, 100), 0, 100),
                        backgroundColor: String(separatorBackgroundColor.value || 'transparent'),
                        backgroundOpacity: clamp(readNumberValue(separatorBackgroundOpacity, 100), 0, 100),
                        currentColor: String(separatorCurrentColor.value || '#1F2937'),
                        nextColor: String(separatorNextColor.value || '#FFFFFF'),
                    };
                }

                let renderPending = false;

                function renderSeparatorGenerator() {
                    const config = readGeneratorConfig();
                    const normalizedName = String(separatorName.value || '').trim();
                    const slug = slugify('' === normalizedName ? 'wave-separator' : normalizedName);
                    const safeTopHeight = clamp(Math.round(config.topHeight), 0, 320);
                    const safeBottomHeight = clamp(Math.round(config.bottomHeight), 0, 320);
                    const safeHeight = clamp(Math.round(config.height), 12, 640);
                    const safeSpaceOffset = clamp(Math.round(config.space), -240, 240);
                    const accentTopOffset = safeSpaceOffset - safeTopHeight;
                    const boundaryOffset = previewCurrent.offsetHeight;
                    const backgroundColor = colorFromHexWithOpacity(config.backgroundColor, config.backgroundOpacity);
                    const fillColor = colorFromHexWithOpacity(config.fillColor, config.fillOpacity);
                    const separatorPath = buildSeparatorPath(config);

                    if (separatorSpaceValue) {
                        separatorSpaceValue.textContent = formatSignedValue(safeSpaceOffset, 'px');
                    }

                    if (separatorTopHeightValue) {
                        separatorTopHeightValue.textContent = formatSignedValue(config.topOffset, 'px');
                    }

                    if (separatorBottomHeightValue) {
                        separatorBottomHeightValue.textContent = formatSignedValue(config.bottomOffset, 'px');
                    }

                    if (separatorIntensityValue) {
                        separatorIntensityValue.textContent = formatSignedValue(config.intensityOffset, '%');
                    }

                    if (separatorFrequencyValue) {
                        separatorFrequencyValue.textContent = formatSignedValue(config.frequencyOffset, '');
                    }

                    if (separatorLeftDepthValue) {
                        separatorLeftDepthValue.textContent = formatSignedValue(config.leftDepthOffset, '%');
                    }

                    if (separatorRightDepthValue) {
                        separatorRightDepthValue.textContent = formatSignedValue(config.rightDepthOffset, '%');
                    }

                    if (separatorFillOpacityValue) {
                        separatorFillOpacityValue.textContent = Math.round(config.fillOpacity) + '%';
                    }

                    if (separatorBackgroundOpacityValue) {
                        separatorBackgroundOpacityValue.textContent = Math.round(config.backgroundOpacity) + '%';
                    }

                    separatorInvertToggle.setAttribute('aria-pressed', config.invertCurve ? 'true' : 'false');
                    separatorInvertToggle.classList.toggle('is-primary', config.invertCurve);
                    separatorInvertToggle.textContent = config.invertCurve ? 'Invert Curve: On' : 'Invert Curve: Off';

                    previewCurrent.style.setProperty('--mrn-preview-row-bg', colorFromHexWithOpacity(config.currentColor, 100));
                    previewCurrent.style.color = getContrastTextColor(config.currentColor);
                    previewNext.style.setProperty('--mrn-preview-row-bg', colorFromHexWithOpacity(config.nextColor, 100));
                    previewNext.style.color = getContrastTextColor(config.nextColor);

                    previewAccent.style.height = safeHeight + 'px';
                    previewAccent.style.top = (boundaryOffset + accentTopOffset) + 'px';
                    previewAccent.style.marginTop = '0';
                    previewAccent.style.transform = 'none';
                    previewAccent.style.backgroundColor = backgroundColor;
                    previewAccent.style.backgroundImage = 'none';
                    previewAccent.style.display = 'block';
                    previewAccentSvg.style.width = '100%';
                    previewAccentSvg.style.height = safeHeight + 'px';
                    previewAccentSvg.style.marginTop = '0';
                    previewAccentSvg.style.backgroundColor = 'transparent';
                    previewAccentSvg.setAttribute('viewBox', '0 0 1000 ' + safeHeight);
                    previewAccentPath.setAttribute('d', separatorPath);
                    previewAccentPath.setAttribute('fill', fillColor);

                    separatorCssOutput.value = buildSeparatorCss(config, slug);
                }

                scheduleSeparatorRender = function () {
                    if (renderPending) {
                        return;
                    }

                    renderPending = true;
                    window.requestAnimationFrame(function () {
                        renderPending = false;
                        renderSeparatorGenerator();
                    });
                };

                refreshSeparatorSiteColorOptions = function () {
                    const colorOptions = getSiteColorOptionsFromRows();
                    const lightestColor = getLightestColor(colorOptions);
                    const darkestColor = getDarkestColor(colorOptions);

                    populateColorSelect(separatorFillColor, colorOptions, separatorFillColor.value || lightestColor, false);
                    populateColorSelect(separatorBackgroundColor, colorOptions, separatorBackgroundColor.value || 'transparent', true);
                    populateColorSelect(separatorCurrentColor, colorOptions, separatorCurrentColor.value || darkestColor, false);
                    populateColorSelect(separatorNextColor, colorOptions, separatorNextColor.value || lightestColor, false);
                };

                refreshSeparatorSiteColorOptions();

                const separatorInputs = [
                    separatorName,
                    separatorPreset,
                    separatorSpace,
                    separatorTopHeight,
                    separatorBottomHeight,
                    separatorIntensity,
                    separatorFrequency,
                    separatorLeftDepth,
                    separatorRightDepth,
                    separatorFillColor,
                    separatorFillOpacity,
                    separatorBackgroundColor,
                    separatorBackgroundOpacity,
                    separatorCurrentColor,
                    separatorNextColor,
                ];

                separatorInputs.forEach(function (input) {
                    input.addEventListener('input', scheduleSeparatorRender);
                    input.addEventListener('change', scheduleSeparatorRender);
                });

                separatorPreset.addEventListener('change', function () {
                    resetSeparatorOffsetsToZero();
                    separatorCurveInverted = true;
                    scheduleSeparatorRender();
                });

                separatorInvertToggle.addEventListener('click', function () {
                    separatorCurveInverted = !separatorCurveInverted;
                    scheduleSeparatorRender();
                });

                separatorAddButton.addEventListener('click', function () {
                    const config = readGeneratorConfig();
                    const normalizedName = String(separatorName.value || '').trim();
                    const safeName = '' === normalizedName ? 'Wave Separator' : normalizedName;
                    const slug = slugify(safeName);
                    const safeBottomHeight = clamp(Math.round(config.bottomHeight), 0, 320);
                    const safeSpaceOffset = clamp(Math.round(config.space), -240, 240);
                    const reservedSpace = Math.max(safeBottomHeight + safeSpaceOffset, 0);
                    const css = buildSeparatorCss(config, slug);
                    const row = createGraphicRow({
                        name: safeName,
                        space: reservedSpace + 'px',
                        css: css,
                    });

                    const cssField = row.querySelector('.mrn-site-styles-graphic-css');
                    if (cssField) {
                        cssField.focus();
                    }

                    scheduleSeparatorRender();
                });

                scheduleSeparatorRender();
            }

            initSeparatorGenerator();
        }());
    </script>
    <?php
}

/**
 * Print site style CSS variables and graphic elements for front-end, admin, and login screens.
 */
function mrn_site_colors_print_css_variables(): void {
    $rows             = mrn_site_colors_get_all();
    $graphic_elements = mrn_site_styles_get_graphic_elements();
    $dark_scroll_card_presets = mrn_site_styles_get_dark_scroll_card_presets();

    echo "<style id='mrn-site-styles-accent-base'>.has-bottom-accent[data-bottom-accent]{position:relative;margin-bottom:var(--mrn-accent-space,3em);}.has-bottom-accent[data-bottom-accent]::after{content:\"\";position:absolute;left:0;right:0;bottom:0;pointer-events:none;}</style>";

    if ($rows !== array()) {
        echo "<style id='mrn-site-colors-vars'>:root{";

        foreach ($rows as $row) {
            $slug  = isset($row['slug']) ? (string) $row['slug'] : '';
            $value = isset($row['value']) ? (string) $row['value'] : '';

            if ($slug === '' || $value === '') {
                continue;
            }

            echo esc_html(mrn_site_colors_get_css_var($slug) . ':' . $value . ';');
        }

        echo '}</style>';
    }

    if ($graphic_elements !== array()) {
        echo "<style id='mrn-site-styles-graphic-elements'>";

        foreach ($graphic_elements as $row) {
            $slug  = isset($row['slug']) ? (string) $row['slug'] : '';
            $css = isset($row['css']) ? (string) $row['css'] : '';
            $space = isset($row['space']) ? (string) $row['space'] : '';

            if ($slug !== '' && $space !== '') {
                echo '.has-bottom-accent[data-bottom-accent="' . esc_attr($slug) . '"]{--mrn-accent-space:' . esc_attr($space) . ';}';
                echo "\n";
            }

            if ($css === '') {
                continue;
            }

            echo trim($css); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo "\n";
        }

        echo '</style>';
    }

    if ($dark_scroll_card_presets !== array()) {
        echo "<style id='mrn-site-styles-motion-presets'>";

        foreach ($dark_scroll_card_presets as $row) {
            $slug = isset($row['slug']) ? (string) $row['slug'] : '';

            if ('' === $slug) {
                continue;
            }

            $background_rgb = mrn_site_styles_hex_to_rgb_triplet((string) ($row['background'] ?? ''));
            $text_rgb = mrn_site_styles_hex_to_rgb_triplet((string) ($row['text'] ?? ''));
            $muted_rgb = mrn_site_styles_hex_to_rgb_triplet((string) ($row['muted_text'] ?? ''));
            $button_background_rgb = mrn_site_styles_hex_to_rgb_triplet((string) ($row['button_background'] ?? ''));
            $button_text_rgb = mrn_site_styles_hex_to_rgb_triplet((string) ($row['button_text'] ?? ''));
            $border_alpha = isset($row['border_alpha']) ? (string) $row['border_alpha'] : '0.12';
            $shadow_alpha = isset($row['shadow_alpha']) ? (string) $row['shadow_alpha'] : '0.35';
            $image_brightness = isset($row['image_brightness']) ? (string) $row['image_brightness'] : '0.72';
            $image_saturation = isset($row['image_saturation']) ? (string) $row['image_saturation'] : '0.85';

            echo '[data-mrn-motion-effect="dark-scroll-card"][data-mrn-effect-preset="' . esc_attr($slug) . '"]{';

            if ('' !== $background_rgb) {
                echo '--mrn-dark-scroll-card-bg-rgb:' . esc_attr($background_rgb) . ';';
            }

            if ('' !== $text_rgb) {
                echo '--mrn-dark-scroll-card-text-rgb:' . esc_attr($text_rgb) . ';';
            }

            if ('' !== $muted_rgb) {
                echo '--mrn-dark-scroll-card-muted-rgb:' . esc_attr($muted_rgb) . ';';
            }

            if ('' !== $button_background_rgb) {
                echo '--mrn-dark-scroll-card-button-bg-rgb:' . esc_attr($button_background_rgb) . ';';
            }

            if ('' !== $button_text_rgb) {
                echo '--mrn-dark-scroll-card-button-text-rgb:' . esc_attr($button_text_rgb) . ';';
            }

            echo '--mrn-dark-scroll-card-border-alpha:' . esc_attr($border_alpha) . ';';
            echo '--mrn-dark-scroll-card-shadow-alpha:' . esc_attr($shadow_alpha) . ';';
            echo '--mrn-dark-scroll-card-image-brightness:' . esc_attr($image_brightness) . ';';
            echo '--mrn-dark-scroll-card-image-saturation:' . esc_attr($image_saturation) . ';';
            echo '}';
            echo "\n";
        }

        echo '</style>';
    }
}
add_action('wp_head', 'mrn_site_colors_print_css_variables', 5);
add_action('admin_head', 'mrn_site_colors_print_css_variables', 5);
add_action('login_head', 'mrn_site_colors_print_css_variables', 5);
