<?php
/**
 * Apply versioned, non-secret Wordfence defaults through the vendor settings API.
 * Usage: wp eval-file configure-wordfence.php <cloudflare|standalone> <exact-host> [preview|apply]
 * Remote execution belongs to the authorized, backup-gated deployment workflow.
 */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( 1 );
}

$profile = $args[0] ?? '';
$host    = $args[1] ?? '';
$mode    = $args[2] ?? 'preview';
if ( ! in_array( $profile, array( 'cloudflare', 'standalone' ), true ) || ! in_array( $mode, array( 'preview', 'apply' ), true ) ) {
	WP_CLI::error( 'Expected a profile, exact host and preview/apply mode.' );
}
if ( ! $host || strtolower( $host ) !== strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) ) {
	WP_CLI::error( 'Target host does not match this WordPress runtime.' );
}
if ( ! class_exists( 'wfConfig' ) || ! defined( 'WORDFENCE_VERSION' ) ) {
	WP_CLI::error( 'Wordfence must be installed and active before configuration.' );
}
$path   = dirname( __DIR__ ) . '/configs/wordfence/' . $profile . '.json';
$preset = json_decode( (string) file_get_contents( $path ), true );
if ( ! is_array( $preset ) || 1 !== ( $preset['schema_version'] ?? null ) || $profile !== ( $preset['profile'] ?? null ) || WORDFENCE_VERSION !== ( $preset['vendor_version'] ?? null ) || ! is_array( $preset['settings'] ?? null ) ) {
	WP_CLI::error( 'Preset is invalid or the installed Wordfence version needs qualification.' );
}
$settings = $preset['settings'];
$changes  = array();
foreach ( $settings as $key => $value ) {
	// These files never carry credentials, alert recipients or site allowlists.
	if ( ! isset( wfConfig::$defaultConfig['checkboxes'][ $key ] ) && ! isset( wfConfig::$defaultConfig['otherParams'][ $key ] ) ) {
		WP_CLI::error( 'Unsupported Wordfence option: ' . $key );
	}
	$current = is_bool( $value ) ? wfConfig::getBool( $key ) : wfConfig::get( $key );
	if ( (string) $current !== (string) $value ) {
		$changes[ $key ] = $value;
	}
}
if ( true !== wfConfig::validate( $settings ) ) {
	WP_CLI::error( 'Vendor validation rejected the Wordfence preset.' );
}
if ( 'apply' === $mode && $changes ) {
	wfConfig::save( $changes );
	foreach ( $changes as $key => $value ) {
		$current = is_bool( $value ) ? wfConfig::getBool( $key ) : wfConfig::get( $key );
		if ( (string) $current !== (string) $value ) {
			WP_CLI::error( 'Wordfence setting readback failed: ' . $key );
		}
	}
}
WP_CLI::log(
	wp_json_encode(
		array(
			'profile'      => $profile,
			'mode'         => $mode,
			'changed_keys' => array_keys( $changes ),
			'launch_gates' => array( 'license-and-terms', 'visitor-ip-verification', 'firewall-optimization-and-learning-review', 'admin-2fa-enrollment', 'alert-delivery', 'mainwp-settings-readback' ),
		)
	)
);
