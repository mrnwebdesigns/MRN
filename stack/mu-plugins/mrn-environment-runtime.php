<?php
/**
 * Plugin Name: MRN Environment Runtime
 * Description: Loads the MRN environment runtime MU plugin from its subfolder.
 * Version: 0.5.0
 */

defined( 'ABSPATH' ) || exit;


if ( file_exists( __DIR__ . '/mrn-environment-runtime/mrn-environment-runtime.php' ) ) {
	// nosemgrep: semgrep.php-dynamic-include -- Fixed plugin path is built only from __DIR__ and a literal suffix.
	require_once __DIR__ . '/mrn-environment-runtime/mrn-environment-runtime.php';
}
