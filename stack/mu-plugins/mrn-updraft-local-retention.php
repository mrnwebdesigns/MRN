<?php
/**
 * Bootstrap loader for the Updraft local retention MU plugin.
 */

if (!defined('ABSPATH')) {
	exit;
}

$mrn_updraft_local_retention_main = __DIR__ . '/mrn-updraft-local-retention/mrn-updraft-local-retention.php';

if (file_exists($mrn_updraft_local_retention_main)) {
	require_once $mrn_updraft_local_retention_main;
}
