<?php
/**
 * Plugin Name: MRN Site Styles
 * Description: Loads the MRN Site Styles MU plugin from its subfolder.
 * Version: 0.1.38
 *
 * Bootstrap loader for the Site Colors MU plugin.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (file_exists(__DIR__ . '/mrn-site-colors/mrn-site-colors.php')) {
    require_once __DIR__ . '/mrn-site-colors/mrn-site-colors.php';
}
