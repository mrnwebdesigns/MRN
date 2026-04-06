<?php
/**
 * Bootstrap loader for the Active Style Guide MU plugin.
 */

if (!defined('ABSPATH')) {
    exit;
}

$mrn_active_style_guide_main = __DIR__ . '/mrn-active-style-guide/mrn-active-style-guide.php';

if (file_exists($mrn_active_style_guide_main)) {
    require_once $mrn_active_style_guide_main;
}
