<?php
/**
 * Plugin Name: MRN Figma Sync
 * Description: Discovers the live MRN builder contract, maps structured Figma payloads into ACF layout data, validates imports, and supports dry-run/import/rollback workflows.
 * Version: 0.1.0
 * Author: MRN
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MRN_FIGMA_SYNC_VERSION', '0.1.0' );
define( 'MRN_FIGMA_SYNC_PATH', plugin_dir_path( __FILE__ ) );
define( 'MRN_FIGMA_SYNC_URL', plugin_dir_url( __FILE__ ) );

require_once MRN_FIGMA_SYNC_PATH . 'includes/class-mrn-figma-sync-plugin.php';
require_once MRN_FIGMA_SYNC_PATH . 'includes/class-mrn-figma-sync-schema.php';
require_once MRN_FIGMA_SYNC_PATH . 'includes/class-mrn-figma-sync-validator.php';
require_once MRN_FIGMA_SYNC_PATH . 'includes/class-mrn-figma-sync-registry.php';
require_once MRN_FIGMA_SYNC_PATH . 'includes/class-mrn-figma-sync-mapper.php';
require_once MRN_FIGMA_SYNC_PATH . 'includes/class-mrn-figma-sync-importer.php';
require_once MRN_FIGMA_SYNC_PATH . 'includes/class-mrn-figma-sync-rest-controller.php';
require_once MRN_FIGMA_SYNC_PATH . 'includes/class-mrn-figma-sync-cli.php';

MRN_Figma_Sync_Plugin::init();
