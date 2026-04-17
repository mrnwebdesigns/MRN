<?php
/**
 * Plugin Name: Dummy Content
 * Description: Generates sample entries for discovered custom post types and builds an all-layouts page from the current site's ACF setup.
 * Version: 0.1.12
 * Author: MRN
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once plugin_dir_path( __FILE__ ) . 'includes/class-mrn-dummy-content.php';

MRN_Dummy_Content::init();
