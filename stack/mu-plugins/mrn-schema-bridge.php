<?php
/**
 * Plugin Name: MRN Schema Bridge
 * Description: Loads the MRN Schema Bridge MU plugin from its subfolder.
 * Version: 0.3.0
 *
 * Bootstrap loader for the MRN Schema Bridge MU plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$mrn_schema_bridge_main = __DIR__ . '/mrn-schema-bridge/mrn-schema-bridge.php';

if ( file_exists( $mrn_schema_bridge_main ) ) {
	require_once $mrn_schema_bridge_main;
}
