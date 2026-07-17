<?php
/**
 * Plugin Name: MRN Public Security Hardening
 * Description: Loads the MRN Public Security Hardening MU plugin from its subfolder.
 * Version: 0.2.0
 *
 * Bootstrap loader for the MRN Public Security Hardening MU plugin.
 */

defined( 'ABSPATH' ) || exit;

$mrn_public_security_hardening_main = __DIR__ . '/mrn-public-security-hardening/mrn-public-security-hardening.php';

if ( file_exists( $mrn_public_security_hardening_main ) ) {
	require_once $mrn_public_security_hardening_main;
}
