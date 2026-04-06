<?php
/**
 * Plugin Name: Dashboard Support (MU)
 * Description: Adds a fixed, non-collapsible, non-movable MRN Web Designs support widget pinned to the top-left of the WP dashboard.
 * Author: MRN Web Designs
 * Version: 1.0.3
 *
 * INSTALL (MU-Plugin):
 * 1) Save as: /wp-content/mu-plugins/mrn-dashboard-support.php
 * 2) Optional logo: /wp-content/mu-plugins/mrn-logo.png
 */

defined('ABSPATH') || exit;

/**
 * Optional manual override for launch date.
 *
 * Set to YYYY-MM-DD (example: '2026-02-13') to force the displayed date from code.
 * Leave as '' to use the one-time saved dashboard value.
 */
if (!defined('MRN_LAUNCH_DATE_OVERRIDE')) {
    define('MRN_LAUNCH_DATE_OVERRIDE', '');
}

/**
 * Register the dashboard widget.
 */
add_action('wp_dashboard_setup', function () {

    wp_add_dashboard_widget(
        'mrn_support_widget',
        'MRN Web Designs - Website Support',
        'mrn_render_support_widget'
    );
});

/**
 * Lock down the widget UI completely (CSS).
 *
 * - Removes collapse arrow
 * - Removes hamburger menu
 * - Removes move arrows
 * - Prevents dragging visuals
 */
add_action('admin_head', function () {

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->id !== 'dashboard') {
        return;
    }
    ?>
    <style>
        /* --------------------------------------------------
         * MRN Support Widget - HARD UI LOCKDOWN
         * -------------------------------------------------- */

        /* Remove collapse toggle */
        #mrn_support_widget .handlediv {
            display: none !important;
        }

        /* Remove hamburger (three-dot) menu */
        #mrn_support_widget .postbox-header .handle-actions {
            display: none !important;
        }

        /* Disable header interaction entirely */
        #mrn_support_widget .hndle {
            cursor: default !important;
            pointer-events: none !important;
        }

        /* Prevent closed state */
        #mrn_support_widget.postbox.closed {
            display: block !important;
        }

        /* Kill sortable visuals */
        #mrn_support_widget.ui-sortable-handle {
            cursor: default !important;
        }
    </style>
    <?php
});

/**
 * Disable dashboard sorting behavior for this widget (JS).
 *
 * This prevents WordPress from re-enabling move arrows
 * via the handle-actions menu.
 */
add_action('admin_footer', function () {

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->id !== 'dashboard') {
        return;
    }
    ?>
    <script>
        (function () {
            if (typeof jQuery === 'undefined') return;

            jQuery(function ($) {

                var $widget = $('#mrn_support_widget');
                if (!$widget.length) return;

                // Remove sortable behavior entirely for this widget
                $widget.removeClass('ui-sortable-handle');

                // Disable dashboard sortable instance (safe no-op if missing)
                try {
                    $('#dashboard-widgets').sortable('disable');
                } catch (e) {}

                // Move widget to top-left column once
                var $leftColumn = $('#dashboard-widgets .postbox-container').first();
                if ($leftColumn.length && !$widget.is(':first-child')) {
                    $leftColumn.prepend($widget);
                }
            });
        })();
    </script>
    <?php
});

/**
 * Save launch date once; ignore future updates.
 */
add_action('admin_init', function () {

    if (!is_admin()) {
        return;
    }

    if (!isset($_SERVER['REQUEST_METHOD']) || strtoupper((string) $_SERVER['REQUEST_METHOD']) !== 'POST') {
        return;
    }

    if (!isset($_POST['mrn_launch_date_nonce']) || !isset($_POST['mrn_launch_date'])) {
        return;
    }

    if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mrn_launch_date_nonce'])), 'mrn_save_launch_date')) {
        return;
    }

    if (!current_user_can('manage_options')) {
        return;
    }

    $launch_date_override = trim((string) MRN_LAUNCH_DATE_OVERRIDE);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $launch_date_override)) {
        return;
    }

    // Once set, the launch date is immutable.
    if (get_option('mrn_launch_date')) {
        return;
    }

    $launch_date = sanitize_text_field(wp_unslash($_POST['mrn_launch_date']));

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $launch_date)) {
        return;
    }

    update_option('mrn_launch_date', $launch_date);
});

/**
 * Render the dashboard widget content.
 */
function mrn_render_support_widget() {

    $company_name  = 'MRN Web Designs';
    $support_email = 'maintenance@mrnwebdesigns.com';

    $site_url    = site_url();
    $wp_version  = get_bloginfo('version');

    $launch_date_override = trim((string) MRN_LAUNCH_DATE_OVERRIDE);
    $has_valid_override = preg_match('/^\d{4}-\d{2}-\d{2}$/', $launch_date_override) === 1;
    $launch_date_saved = get_option('mrn_launch_date', '');
    $launch_date = $has_valid_override ? $launch_date_override : $launch_date_saved;

    $site_age_text = '';
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $launch_date) === 1) {
        $launch_timestamp = strtotime($launch_date . ' 00:00:00');
        if ($launch_timestamp !== false) {
            $now_timestamp = current_time('timestamp');
            $age_days = max(0, (int) floor(($now_timestamp - $launch_timestamp) / DAY_IN_SECONDS));
            $age_weeks = (int) floor($age_days / 7);
            $age_years = (int) floor($age_days / 365.2425);

            $site_age_text = sprintf(
                'This site is now %1$d day%2$s old (%3$d week%4$s, %5$d year%6$s).',
                $age_days,
                $age_days === 1 ? '' : 's',
                $age_weeks,
                $age_weeks === 1 ? '' : 's',
                $age_years,
                $age_years === 1 ? '' : 's'
            );
        }
    }

    $logo_file = __DIR__ . '/mrn-logo.png';
    $logo_url  = content_url('mu-plugins/mrn-dashboard-support/mrn-logo.png');

    // Fallback for non-standard setups where content_url does not map as expected.
    if (!empty($_SERVER['HTTP_HOST'])) {
        $host = preg_replace('/:\d+$/', '', (string) $_SERVER['HTTP_HOST']);
        if (is_string($host) && $host !== '') {
            $fallback_url = (is_ssl() ? 'https://' : 'http://') . $host . '/wp-content/mu-plugins/mrn-dashboard-support/mrn-logo.png';
            $logo_url = is_string($logo_url) && $logo_url !== '' ? $logo_url : $fallback_url;
        }
    }

    // Email construction
    $email_subject = rawurlencode('WordPress Support Request - ' . $site_url);
    $email_body = rawurlencode(
        "\n\n\n" .
        "----------------------------------\n" .
        "Site Details (auto-generated)\n" .
        "----------------------------------\n" .
        "Site URL:\n{$site_url}\n\n" .
        "WordPress Version:\n{$wp_version}\n"
    );

    $mailto_link = "mailto:{$support_email}?subject={$email_subject}&body={$email_body}";

    echo '<div style="text-align:left;">';

    if (file_exists($logo_file)) {
        echo '<p style="margin:0 0 12px 0;">';
        echo '<img src="' . esc_url($logo_url) . '" alt="' . esc_attr($company_name) . ' Logo" style="max-width:220px;height:auto;">';
        echo '</p>';
    }

    echo '<p style="margin:0 0 8px 0;"><strong>' . esc_html($company_name) . '</strong></p>';

    echo '<p style="margin:0;font-size:13px;"><strong>Site URL:</strong><br>' . esc_html($site_url) . '</p>';
    echo '<p style="margin:6px 0 12px 0;font-size:13px;"><strong>WordPress Version:</strong><br>' . esc_html($wp_version) . '</p>';

    echo '<form method="post" style="margin:0 0 12px 0;">';
    wp_nonce_field('mrn_save_launch_date', 'mrn_launch_date_nonce');

    echo '<label for="mrn_launch_date" style="display:block;margin:0 0 4px 0;font-size:13px;"><strong>Launch Date:</strong></label>';

    if (!empty($launch_date)) {
        echo '<input id="mrn_launch_date" type="date" value="' . esc_attr($launch_date) . '" disabled style="margin:0 0 4px 0;max-width:220px;width:100%;">';
        if ($has_valid_override) {
            echo '<p style="margin:0;font-size:12px;color:#555;">Date is set from MRN_LAUNCH_DATE_OVERRIDE in this file.</p>';
        } else {
        }
        if (!empty($site_age_text)) {
            echo '<p style="margin:4px 0 0 0;font-size:12px;color:#555;">' . esc_html($site_age_text) . '</p>';
        }
    } else {
        echo '<input id="mrn_launch_date" name="mrn_launch_date" type="date" required style="margin:0 0 8px 0;max-width:220px;width:100%">';
        submit_button('Save Date', 'secondary small', 'submit', false);
    }

    echo '</form>';

    echo '<p style="margin:0;">';
    echo '<a href="' . esc_url($mailto_link) . '"
        style="
            display:inline-block;
            padding:8px 14px;
            background:#2271b1;
            color:#fff;
            text-decoration:none;
            border-radius:4px;
            font-size:13px;
        ">
        Contact MRN Support
    </a>';
    echo '</p>';

    echo '<p style="margin-top:10px;font-size:12px;color:#555;">
        Clicking the button opens your email client with site details added below
        so you can start typing your message right away.
    </p>';

    echo '</div>';
}
