<?php
/**
 * Plugin Name: MRN Reusable Block Library
 * Description: Loads the MRN Reusable Block Library MU plugin from its subfolder.
 * Version: 0.1.25
 *
 * Bootstrap loader for the Reusable Block Library MU plugin.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (file_exists(__DIR__ . '/mrn-reusable-block-library/mrn-reusable-block-library.php')) {
    require_once __DIR__ . '/mrn-reusable-block-library/mrn-reusable-block-library.php';
}
