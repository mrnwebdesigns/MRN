<?php
/**
 * Plugin Name: MRN Loader (MU)
 * Description: Loads MRN MU plugins from known subfolders in /wp-content/mu-plugins.
 * Version: 1.3.1
 */

defined('ABSPATH') || exit;

/**
 * Include a plugin bootstrap file once, only if it has not already been loaded.
 *
 * @param mixed $file Candidate file path.
 * @return bool
 */
function mrn_loader_include_once_if_needed($file) {
    if (!is_string($file) || $file === '' || !file_exists($file)) {
        return false;
    }

    $target_realpath = realpath($file);
    if ($target_realpath === false) {
        return false;
    }

    foreach (get_included_files() as $included_file) {
        $included_realpath = realpath($included_file);
        if ($included_realpath !== false && $included_realpath === $target_realpath) {
            return true;
        }
    }

    try {
        require_once $target_realpath;
        return true;
    } catch (Throwable $e) {
        error_log(
            sprintf(
                '[MRN Loader] Failed loading %s: %s',
                $target_realpath,
                $e->getMessage()
            )
        );

        return false;
    }
}

/**
 * Known MRN plugin entrypoints under /wp-content/mu-plugins/<slug>/<entry-file>.
 */
$mrn_loader_entries = array(
    WP_CONTENT_DIR . '/mu-plugins/mrn-admin-ui-css/mrn-admin-ui-css.php',
    WP_CONTENT_DIR . '/mu-plugins/mrn-dashboard-support/mrn-dashboard-support.php',
    WP_CONTENT_DIR . '/mu-plugins/mrn-disable-comments/mrn-disable-comments.php',
    WP_CONTENT_DIR . '/mu-plugins/mrn-duplicate-enhance/mrn-duplicate-enhance.php',
    WP_CONTENT_DIR . '/mu-plugins/mrn-editor-lockdown/mrn-editor-lockdown.php',
    WP_CONTENT_DIR . '/mu-plugins/mrn-editor-ui-css/mrn-editor-ui-css.php',
    WP_CONTENT_DIR . '/mu-plugins/mrn-reusable-block-library/mrn-reusable-block-library.php',
    WP_CONTENT_DIR . '/mu-plugins/mrn-shared-assets/mrn-shared-assets.php',
    WP_CONTENT_DIR . '/mu-plugins/mrn-site-colors/mrn-site-colors.php',
    WP_CONTENT_DIR . '/mu-plugins/mrn-updraft-local-retention/mrn-updraft-local-retention.php',
    WP_CONTENT_DIR . '/mu-plugins/mrn-active-style-guide/mrn-active-style-guide.php',
    WP_CONTENT_DIR . '/mu-plugins/mrn-svg-support/mrn-svg-support.php',
);

foreach ($mrn_loader_entries as $entry_file) {
    if (!is_string($entry_file) || $entry_file === '') {
        continue;
    }

    mrn_loader_include_once_if_needed($entry_file);
}
