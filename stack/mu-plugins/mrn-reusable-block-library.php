<?php
/**
 * Bootstrap loader for the Reusable Block Library MU plugin.
 */

if (!defined('ABSPATH')) {
    exit;
}

$mrn_reusable_block_library_main = __DIR__ . '/mrn-reusable-block-library/mrn-reusable-block-library.php';

if (file_exists($mrn_reusable_block_library_main)) {
    require_once $mrn_reusable_block_library_main;
}
