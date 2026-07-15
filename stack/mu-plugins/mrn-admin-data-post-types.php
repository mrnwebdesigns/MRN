<?php
/**
 * Plugin Name: MRN Admin Data Post Types
 * Description: Loads the MRN Admin Data Post Types MU plugin from its subfolder.
 * Version: 0.1.0
 */

defined( 'ABSPATH' ) || exit;

$mrn_admin_data_post_types_main = __DIR__ . '/mrn-admin-data-post-types/mrn-admin-data-post-types.php';

if ( file_exists( $mrn_admin_data_post_types_main ) ) {
	require_once $mrn_admin_data_post_types_main;
}
