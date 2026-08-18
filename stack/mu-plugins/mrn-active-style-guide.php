<?php
/**
 * Plugin Name: MRN Active Style Guide
 * Description: Loads the MRN Active Style Guide MU plugin from its subfolder.
 * Version: 0.1.6
 *
 * Bootstrap loader for the Active Style Guide MU plugin.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (file_exists(__DIR__ . '/mrn-active-style-guide/mrn-active-style-guide.php')) {
    require_once __DIR__ . '/mrn-active-style-guide/mrn-active-style-guide.php';
}
