<?php
/**
 * Plugin Name: MRN Mega Menu
 * Description: A focused, accessible mega menu builder with first-class WooCommerce support.
 * Version: 0.16.16
 * Author: MRN Web Designs
 * Text Domain: mrn-mega-menu
 * Requires at least: 6.0
 * Requires PHP: 8.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/class-plugin.php';
require_once __DIR__ . '/includes/class-stack-integration.php';
require_once __DIR__ . '/includes/class-admin.php';
require_once __DIR__ . '/includes/class-menu-admin.php';
require_once __DIR__ . '/includes/class-renderer.php';

register_activation_hook( __FILE__, array( 'MRN_Mega_Menu\\Plugin', 'activate' ) );

MRN_Mega_Menu\Plugin::init();
