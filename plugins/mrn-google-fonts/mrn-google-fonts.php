<?php
/**
 * Plugin Name: Google Fonts
 * Description: Performance-first Google Fonts runtime for frontend and Classic Editor, with optional MRN stack bridge support.
 * Author: MRN Web Designs
 * Version: 0.4.10
 */

if (!defined('ABSPATH')) {
	exit;
}

define('MRN_GOOGLE_FONTS_FILE', __FILE__);
define('MRN_GOOGLE_FONTS_DIR', plugin_dir_path(__FILE__));
define('MRN_GOOGLE_FONTS_URL', plugin_dir_url(__FILE__));

require_once MRN_GOOGLE_FONTS_DIR . 'includes/class-mrn-google-fonts-stack-bridge.php';
require_once MRN_GOOGLE_FONTS_DIR . 'includes/class-mrn-google-fonts.php';

MRN_Google_Fonts::init();
