<?php
/**
 * Plugin Name: MRN Schema Bridge
 * Description: Loads the MRN Schema Bridge MU plugin from its subfolder.
 * Version: 0.4.6
 *
 * Bootstrap loader for the MRN Schema Bridge MU plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


if ( file_exists( __DIR__ . '/mrn-schema-bridge/mrn-schema-bridge.php' ) ) {
	require_once __DIR__ . '/mrn-schema-bridge/mrn-schema-bridge.php';
}
