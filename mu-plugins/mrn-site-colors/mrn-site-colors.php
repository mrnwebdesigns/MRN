<?php
/**
 * Plugin Name: Site Styles (MU)
 * Description: Adds a Site Styles configuration page for shared color variables, graphic elements, and usage helpers.
 * Author: MRN Web Designs
 * Version: 0.1.6
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
    return array(
        'colors' => 'Site Colors',
        'graphic_elements' => 'Graphic Elements',
        'dark_scroll_card_presets' => 'Motion Presets',
    );
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
    $rows = get_option(mrn_site_colors_option_key(), array());
    return mrn_site_colors_sanitize_rows($rows);
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
    $rows = get_option(mrn_site_styles_graphic_elements_option_key(), array());
    return mrn_site_styles_sanitize_graphic_element_rows($rows);
}

/**
 * Get graphic elements keyed by slug for easier lookup in templates.
 *
 * @return array<string, array<string, string>>
 */
function mrn_site_styles_get_graphic_element_map(): array {
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
    $rows = get_option(mrn_site_styles_dark_scroll_card_presets_option_key(), array());
    $sanitized = mrn_site_styles_sanitize_dark_scroll_card_preset_rows($rows);

    if ($sanitized !== array()) {
        return $sanitized;
    }

    return array(
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
}

/**
 * Get dark scroll card presets keyed by slug.
 *
 * @return array<string, array<string, string>>
 */
function mrn_site_styles_get_dark_scroll_card_preset_map(): array {
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

    if (!in_array($submitted_section, array('colors', 'graphic-elements', 'motion-presets'), true)) {
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
    $active_tab = 'colors';
    $has_sticky_toolbar = mrn_site_styles_load_sticky_toolbar_helper();

    // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only tab/notice state from our own redirect query arg.
    if (isset($_GET['updated'])) {
        $updated_notice = sanitize_key(wp_unslash((string) $_GET['updated']));
    }
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    if (in_array($updated_notice, array('colors', 'graphic-elements', 'motion-presets'), true)) {
        $active_tab = $updated_notice;
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

            @media (max-width: 1100px) {
                .mrn-site-styles-motion-fields {
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
            mrn_sticky_toolbar_render(
                array(
                    'toolbar_id' => 'mrn-site-styles-toolbar',
                    'form_id' => 'mrn-site-styles-form',
                    'title' => 'Site Styles',
                    'aria_label' => 'Site Styles tabs',
                    'save_label' => 'Save Site Styles',
                    'tabs' => array(
                        array(
                            'key' => 'colors',
                            'label' => 'Site Colors',
                            'active' => 'colors' === $active_tab,
                            'icon' => 'dashicons-art',
                        ),
                        array(
                            'key' => 'graphic-elements',
                            'label' => 'Graphic Elements',
                            'active' => 'graphic-elements' === $active_tab,
                            'icon' => 'dashicons-format-image',
                        ),
                        array(
                            'key' => 'motion-presets',
                            'label' => 'Motion Presets',
                            'active' => 'motion-presets' === $active_tab,
                            'icon' => 'dashicons-controls-repeat',
                        ),
                    ),
                )
            );
            ?>
        <?php endif; ?>

        <div class="mrn-site-styles-transfer-box">
            <h2 style="margin-top:0;">Import / Export</h2>
            <p>Export selected Site Styles sections for this site to a JSON file, or import any Site Styles sections present in a previously exported bundle.</p>
            <div class="mrn-site-styles-transfer-actions">
                <form method="post" action="">
                    <?php wp_nonce_field('mrn_site_styles_export', 'mrn_site_styles_export_nonce'); ?>
                    <div class="mrn-site-styles-transfer-sections">
                        <strong>Export Sections</strong>
                        <?php foreach (mrn_site_styles_get_transfer_sections() as $section_key => $section_label) : ?>
                            <label>
                                <input type="checkbox" name="mrn_site_styles_sections[]" value="<?php echo esc_attr($section_key); ?>" checked />
                                <span><?php echo esc_html($section_label); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <button type="submit" name="mrn_site_styles_export_submit" class="button">Export Site Styles</button>
                </form>

                <form method="post" action="" enctype="multipart/form-data">
                    <?php wp_nonce_field('mrn_site_styles_import', 'mrn_site_styles_import_nonce'); ?>
                    <label for="mrn-site-styles-import-file">Import JSON</label>
                    <input type="file" id="mrn-site-styles-import-file" name="mrn_site_styles_import_file" accept="application/json,.json" />
                    <button type="submit" name="mrn_site_styles_import_submit" class="button button-secondary">Import Site Styles</button>
                    <p class="description" style="margin:6px 0 0;">Only the sections present in the JSON will be imported. Missing sections are left unchanged.</p>
                </form>
            </div>
        </div>

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

            function slugify(value) {
                return value
                    .toLowerCase()
                    .trim()
                    .replace(/[^a-z0-9]+/g, '-')
                    .replace(/^-+|-+$/g, '') || 'color';
            }

            function updateRow(row) {
                const nameInput = row.querySelector('.mrn-site-colors-name');
                const slugInput = row.querySelector('.mrn-site-colors-slug');
                const valueInput = row.querySelector('.mrn-site-colors-value');
                const picker = row.querySelector('.mrn-site-colors-picker');
                const varOutput = row.querySelector('.mrn-site-colors-var');
                const slug = slugify(nameInput.value || slugInput.value);

                function normalizeHexInput(value) {
                    const trimmed = value.trim();
                    const raw = trimmed.replace(/^#/, '').toUpperCase();

                    if (/^[0-9A-F]{3}([0-9A-F]{3})?$/.test(raw)) {
                        return '#' + raw;
                    }

                    return trimmed.toUpperCase();
                }

                function getPickerValue(value) {
                    const raw = value.trim().replace(/^#/, '').toUpperCase();

                    if (/^[0-9A-F]{3}([0-9A-F]{3})?$/.test(raw)) {
                        return '#' + raw;
                    }

                    return '#000000';
                }

                slugInput.value = slug;
                valueInput.value = normalizeHexInput(valueInput.value);
                picker.value = getPickerValue(valueInput.value);
                varOutput.textContent = '--site-color-' + slug;
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
            });

            addGraphicButton.addEventListener('click', function () {
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

                graphicRowsContainer.appendChild(row);
                bindGraphicRow(row);
                notifyFormChanged();
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
