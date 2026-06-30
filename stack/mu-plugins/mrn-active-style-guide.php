<?php
/**
 * Plugin Name: MRN Active Style Guide
 * Description: Loads the MRN Active Style Guide MU plugin from its subfolder.
 * Version: 0.1.5
 *
 * Bootstrap loader for the Active Style Guide MU plugin.
 */

if (!defined('ABSPATH')) {
    exit;
}

$mrn_active_style_guide_main = __DIR__ . '/mrn-active-style-guide/mrn-active-style-guide.php';

if (file_exists($mrn_active_style_guide_main)) {
    require_once $mrn_active_style_guide_main;
}
