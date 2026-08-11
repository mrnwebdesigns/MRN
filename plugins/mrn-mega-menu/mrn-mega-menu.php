<?php
/**
 * Plugin Name: MRN Mega Menu
 * Description: A focused, accessible mega menu builder with first-class WooCommerce support.
 * Version: 0.16.12
 * Author: MRN Web Designs
 * Text Domain: mrn-mega-menu
 * Requires at least: 6.0
 * Requires PHP: 8.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MRN_MEGA_MENU_VERSION', '0.16.12' );
define( 'MRN_MEGA_MENU_FILE', __FILE__ );
define( 'MRN_MEGA_MENU_PATH', plugin_dir_path( __FILE__ ) );
define( 'MRN_MEGA_MENU_URL', plugin_dir_url( __FILE__ ) );

require_once MRN_MEGA_MENU_PATH . 'includes/class-plugin.php';
require_once MRN_MEGA_MENU_PATH . 'includes/class-stack-integration.php';
require_once MRN_MEGA_MENU_PATH . 'includes/class-admin.php';
require_once MRN_MEGA_MENU_PATH . 'includes/class-menu-admin.php';
require_once MRN_MEGA_MENU_PATH . 'includes/class-renderer.php';

register_activation_hook( MRN_MEGA_MENU_FILE, array( 'MRN_Mega_Menu\\Plugin', 'activate' ) );

MRN_Mega_Menu\Plugin::init();
