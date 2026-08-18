<?php
/**
 * Plugin Name: MRN Shared Assets
 * Description: Loads the MRN Shared Assets MU plugin from its subfolder.
 * Version: 0.2.0
 *
 * Bootstrap loader for the Shared Assets MU plugin.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (file_exists(__DIR__ . '/mrn-shared-assets/mrn-shared-assets.php')) {
    require_once __DIR__ . '/mrn-shared-assets/mrn-shared-assets.php';
}
