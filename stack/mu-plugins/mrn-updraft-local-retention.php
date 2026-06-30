<?php
/**
 * Plugin Name: MRN Updraft Local Retention
 * Description: Loads the MRN Updraft Local Retention MU plugin from its subfolder.
 * Version: 0.1.0
 *
 * Bootstrap loader for the Updraft local retention MU plugin.
 */

if (!defined('ABSPATH')) {
	exit;
}

$mrn_updraft_local_retention_main = __DIR__ . '/mrn-updraft-local-retention/mrn-updraft-local-retention.php';

if (file_exists($mrn_updraft_local_retention_main)) {
	require_once $mrn_updraft_local_retention_main;
}
