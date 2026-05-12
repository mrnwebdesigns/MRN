<?php
/**
 * Plugin Name: Shared Assets (MU)
 * Description: Provides shared runtime assets and metadata for stack-wide consumers.
 * Author: MRN Web Designs
 * Version: 0.1.1
 */

defined('ABSPATH') || exit;

/**
 * Get the bundled Font Awesome version.
 */
function mrn_shared_assets_fontawesome_version(): string {
    return '7.1.0';
}

/**
 * Get the Font Awesome root path.
 */
function mrn_shared_assets_fontawesome_path(): string {
    return trailingslashit(plugin_dir_path(__FILE__)) . 'assets/fontawesome';
}

/**
 * Get the Font Awesome root URL.
 */
function mrn_shared_assets_fontawesome_url(): string {
    return content_url('mu-plugins/mrn-shared-assets/assets/fontawesome');
}

/**
 * Get the Font Awesome CSS URL.
 *
 * @param string $context Optional loading context.
 */
function mrn_shared_assets_fontawesome_css_url(string $context = 'runtime'): string {
    $default_url = trailingslashit(mrn_shared_assets_fontawesome_url()) . 'css/all.min.css';

    /**
     * Filter the Font Awesome stylesheet URL used by shared-asset consumers.
     *
     * @param string $default_url Default shared-asset stylesheet URL.
     * @param string $context     Optional loading context.
     */
    $filtered = (string) apply_filters('mrn_shared_assets_fontawesome_css_url', $default_url, $context);
    $filtered = esc_url_raw($filtered);

    if ('' === $filtered) {
        return $default_url;
    }

    return $filtered;
}

/**
 * Enqueue the shared Font Awesome bundle.
 *
 * @param string $handle  Optional style handle.
 * @param string $context Optional loading context.
 */
function mrn_shared_assets_enqueue_fontawesome(string $handle = 'mrn-shared-fontawesome', string $context = 'runtime'): void {
    wp_enqueue_style(
        $handle,
        mrn_shared_assets_fontawesome_css_url($context),
        array(),
        mrn_shared_assets_fontawesome_version()
    );
}

/**
 * Load the shared Font Awesome icon metadata.
 *
 * @return array<string, mixed>
 */
function mrn_shared_assets_get_fontawesome_icons(): array {
    $path = trailingslashit(mrn_shared_assets_fontawesome_path()) . 'icons.json';
    if (!file_exists($path)) {
        return array();
    }

    $contents = file_get_contents($path);
    if ($contents === false) {
        return array();
    }

    $data = json_decode($contents, true);
    return is_array($data) ? $data : array();
}

/**
 * Load the available WordPress Dashicon names from core.
 *
 * @return array<int, string>
 */
function mrn_shared_assets_get_dashicons(): array {
    $css_path = ABSPATH . WPINC . '/css/dashicons.css';
    if (!file_exists($css_path)) {
        return array();
    }

    $contents = file_get_contents($css_path);
    if ($contents === false) {
        return array();
    }

    preg_match_all('/\\.dashicons-([a-z0-9-]+):before\\s*\\{/i', $contents, $matches);
    if (empty($matches[1])) {
        return array();
    }

    $icons = array_unique($matches[1]);
    sort($icons);

    return array_values($icons);
}

/**
 * Get the shared admin icon chooser CSS URL.
 */
function mrn_shared_assets_icon_chooser_css_url(): string {
    return trailingslashit(content_url('mu-plugins/mrn-shared-assets/assets/icon-chooser')) . 'admin-icon-chooser.css';
}

/**
 * Get the shared admin icon chooser JS URL.
 */
function mrn_shared_assets_icon_chooser_js_url(): string {
    return trailingslashit(content_url('mu-plugins/mrn-shared-assets/assets/icon-chooser')) . 'admin-icon-chooser.js';
}

/**
 * Enqueue the shared admin icon chooser assets.
 *
 * @param string $script_handle Optional script handle.
 * @param string $style_handle Optional style handle.
 */
function mrn_shared_assets_enqueue_admin_icon_chooser(string $script_handle = 'mrn-shared-icon-chooser', string $style_handle = 'mrn-shared-icon-chooser'): void {
    wp_enqueue_style(
        $style_handle,
        mrn_shared_assets_icon_chooser_css_url(),
        array(),
        '0.1.3'
    );

    wp_enqueue_script(
        $script_handle,
        mrn_shared_assets_icon_chooser_js_url(),
        array('jquery'),
        '0.1.3',
        true
    );

    wp_enqueue_style('dashicons');
    mrn_shared_assets_enqueue_fontawesome($style_handle . '-fontawesome', 'icon-chooser');
    wp_enqueue_media();

    $fontawesome_catalog = (array) apply_filters(
        'mrn_shared_assets_fontawesome_icon_catalog',
        mrn_shared_assets_get_fontawesome_icons(),
        'admin_icon_chooser'
    );

    $fontawesome_picker_endpoint = array();
    if (has_action('wp_ajax_mrn_fapm_register_icon_selection')) {
        $fontawesome_picker_endpoint = array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'action' => 'mrn_fapm_register_icon_selection',
            'nonce' => wp_create_nonce('mrn_fapm_register_icon_selection'),
        );
    }

    $fontawesome_picker_endpoint = (array) apply_filters(
        'mrn_shared_assets_fontawesome_picker_endpoint',
        $fontawesome_picker_endpoint
    );

    $fontawesome_style_labels = (array) apply_filters(
        'mrn_shared_assets_fontawesome_style_labels',
        array(
            'solid' => __('Solid'),
            'regular' => __('Regular'),
            'brands' => __('Brands'),
            'light' => __('Light'),
            'thin' => __('Thin'),
            'duotone' => __('Duotone'),
            'sharp-solid' => __('Sharp Solid'),
            'sharp-regular' => __('Sharp Regular'),
            'sharp-light' => __('Sharp Light'),
            'sharp-thin' => __('Sharp Thin'),
            'sharp-duotone' => __('Sharp Duotone'),
        ),
        'admin_icon_chooser'
    );

    $sanitized_style_labels = array();
    foreach ($fontawesome_style_labels as $style_key => $style_label) {
        $style_key = sanitize_key((string) $style_key);
        if ('' === $style_key) {
            continue;
        }

        $sanitized_style_labels[$style_key] = sanitize_text_field((string) $style_label);
    }

    wp_localize_script(
        $script_handle,
        'mrnSharedIconChooserData',
        array(
            'dashicons' => mrn_shared_assets_get_dashicons(),
            'fontawesome' => $fontawesome_catalog,
            'fontawesomeStyleLabels' => $sanitized_style_labels,
            'fontawesomePickerEndpoint' => $fontawesome_picker_endpoint,
            'strings' => array(
                'chooseIcon' => __('Choose Icon'),
                'dashicons' => __('Dashicons'),
                'fontAwesome' => __('Font Awesome'),
                'image' => __('Image'),
                'clear' => __('Clear'),
                'searchDashicons' => __('Search dashicons...'),
                'searchFontAwesome' => __('Search Font Awesome...'),
                'chooseImage' => __('Choose Image'),
                'useImage' => __('Use this image'),
                'selectImage' => __('Select Icon Image'),
                'noIconsFound' => __('No icons found.'),
                'close' => __('Close'),
                'faTrackingEnabled' => __('Local pack tracking is on.'),
                'faTrackingUnavailable' => __('Local pack tracking is unavailable on this screen.'),
                'faTrackingSaving' => __('Saving icon to local pack list...'),
                'faTrackingAdded' => __('Added to local pack list.'),
                'faTrackingAlready' => __('Icon already tracked for local pack.'),
                'faTrackingError' => __('Could not register icon right now.'),
                'faTrackingAllowlistLabel' => __('Allowlist'),
                'faTrackingSessionLabel' => __('Session Adds'),
            ),
        )
    );
}
