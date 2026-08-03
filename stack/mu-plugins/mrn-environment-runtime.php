<?php
/**
 * Plugin Name: MRN Environment Runtime
 * Description: Loads the MRN environment runtime MU plugin from its subfolder.
 * Version: 0.1.0
 */

defined( 'ABSPATH' ) || exit;

$mrn_environment_runtime_main = __DIR__ . '/mrn-environment-runtime/mrn-environment-runtime.php';

if ( file_exists( $mrn_environment_runtime_main ) ) {
	// nosemgrep: semgrep.php-dynamic-include -- Fixed plugin path is built only from __DIR__ and a literal suffix.
	require_once $mrn_environment_runtime_main;
}
