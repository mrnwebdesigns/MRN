<?php
/**
 * Bootstrap loader for the Shared Assets MU plugin.
 */

if (!defined('ABSPATH')) {
    exit;
}

$mrn_shared_assets_main = __DIR__ . '/mrn-shared-assets/mrn-shared-assets.php';

if (file_exists($mrn_shared_assets_main)) {
    require_once $mrn_shared_assets_main;
}
