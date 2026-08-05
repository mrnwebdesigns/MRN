<?php
/**
 * Plugin Name: MRN Public Security Hardening
 * Description: Loads the MRN Public Security Hardening MU plugin from its subfolder.
 * Version: 0.3.3
 *
 * Bootstrap loader for the MRN Public Security Hardening MU plugin.
 */

defined( 'ABSPATH' ) || exit;

if ( file_exists( __DIR__ . '/mrn-public-security-hardening/mrn-public-security-hardening.php' ) ) {
	require_once __DIR__ . '/mrn-public-security-hardening/mrn-public-security-hardening.php';
}
