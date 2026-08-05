<?php
/**
 * Plugin Name: MRN Site Styles
 * Description: Loads the MRN Site Styles MU plugin from its subfolder.
 * Version: 0.1.15
 *
 * Bootstrap loader for the Site Colors MU plugin.
 */

if (!defined('ABSPATH')) {
    exit;
}

$mrn_site_colors_main = __DIR__ . '/mrn-site-colors/mrn-site-colors.php';

if (file_exists($mrn_site_colors_main)) {
    require_once $mrn_site_colors_main;
}
