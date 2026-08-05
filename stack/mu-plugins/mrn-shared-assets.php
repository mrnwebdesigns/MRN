<?php
/**
 * Plugin Name: MRN Shared Assets
 * Description: Loads the MRN Shared Assets MU plugin from its subfolder.
 * Version: 0.1.3
 *
 * Bootstrap loader for the Shared Assets MU plugin.
 */

if (!defined('ABSPATH')) {
    exit;
}

$mrn_shared_assets_main = __DIR__ . '/mrn-shared-assets/mrn-shared-assets.php';

if (file_exists($mrn_shared_assets_main)) {
    require_once $mrn_shared_assets_main;
}
