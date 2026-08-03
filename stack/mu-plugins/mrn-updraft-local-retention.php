<?php
/**
 * Plugin Name: MRN Updraft Backup Policy
 * Description: Loads the MRN Updraft backup policy MU plugin from its subfolder.
 * Version: 0.2.0
 *
 * Bootstrap loader for the Updraft local retention MU plugin.
 */

if (!defined('ABSPATH')) {
	exit;
}

$mrn_updraft_local_retention_main = __DIR__ . '/mrn-updraft-local-retention/mrn-updraft-local-retention.php';

if (file_exists($mrn_updraft_local_retention_main)) {
	// nosemgrep: semgrep.php-dynamic-include -- Fixed plugin path is built only from __DIR__ and a literal suffix.
	require_once $mrn_updraft_local_retention_main;
}
