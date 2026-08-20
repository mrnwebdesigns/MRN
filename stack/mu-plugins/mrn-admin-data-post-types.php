<?php
/**
 * Plugin Name: MRN Admin Data Post Types
 * Description: Loads the MRN Admin Data Post Types MU plugin from its subfolder.
 * Version: 0.2.0
 */

defined( 'ABSPATH' ) || exit;


if ( file_exists( __DIR__ . '/mrn-admin-data-post-types/mrn-admin-data-post-types.php' ) ) {
	require_once __DIR__ . '/mrn-admin-data-post-types/mrn-admin-data-post-types.php';
}
