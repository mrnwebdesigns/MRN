<?php
/**
 * Plugin Name: MRN Environment Runtime
 * Description: Reports the deployment-managed environment and performance policy without adding frontend work.
 * Version: 0.1.0
 * Author: MRN Web Designs
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'MRN_ENVIRONMENT_RUNTIME_VERSION' ) ) {
	define( 'MRN_ENVIRONMENT_RUNTIME_VERSION', '0.1.0' );
}

/**
 * Return an allowed constant value or its safe default.
 *
 * @param string $constant_name Constant name.
 * @param array  $allowed       Allowed string values.
 * @param string $default       Default value.
 * @return string
 */
function mrn_environment_runtime_constant( $constant_name, $allowed, $default ) {
	if ( ! is_string( $constant_name ) || ! defined( $constant_name ) ) {
		return $default;
	}

	$value = constant( $constant_name );
	if ( ! is_string( $value ) ) {
		return $default;
	}

	$value = strtolower( trim( $value ) );

	return in_array( $value, $allowed, true ) ? $value : $default;
}

/**
 * Resolve the canonical WordPress environment type.
 *
 * @return string
 */
function mrn_environment_runtime_environment_type() {
	$environment = function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production';

	return in_array( $environment, array( 'local', 'development', 'staging', 'production' ), true )
		? $environment
		: 'production';
}

/**
 * Return the complete, deployment-managed runtime contract.
 *
 * @return array<string, string>
 */
function mrn_environment_runtime_contract() {
	return array(
		'environment'          => mrn_environment_runtime_environment_type(),
		'site_profile'        => mrn_environment_runtime_constant( 'MRN_SITE_PROFILE', array( 'plain', 'stack' ), 'stack' ),
		'workload_class'      => mrn_environment_runtime_constant( 'MRN_WORKLOAD_CLASS', array( 'standard', 'dynamic' ), 'standard' ),
		'page_cache'          => mrn_environment_runtime_constant( 'MRN_PAGE_CACHE_POLICY', array( 'disabled', 'server_native', 'redis' ), 'disabled' ),
		'object_cache'        => mrn_environment_runtime_constant( 'MRN_OBJECT_CACHE_POLICY', array( 'disabled', 'review_required', 'enabled' ), 'disabled' ),
		'deploy_cache_purge'  => mrn_environment_runtime_constant( 'MRN_DEPLOY_CACHE_PURGE', array( 'object', 'all' ), 'object' ),
		'searchwp'            => mrn_environment_runtime_constant( 'MRN_SEARCHWP_POLICY', array( 'disabled', 'configured' ), 'disabled' ),
		'import_tools'        => mrn_environment_runtime_constant( 'MRN_IMPORT_TOOLS_POLICY', array( 'disabled', 'temporary' ), 'disabled' ),
		'asset_version_source'=> mrn_environment_runtime_constant( 'MRN_ASSET_VERSION_SOURCE', array( 'commit_sha' ), 'commit_sha' ),
		'deployment_ref'      => mrn_environment_runtime_deployment_ref(),
	);
}

/**
 * Return a display-safe deployment reference.
 *
 * @return string
 */
function mrn_environment_runtime_deployment_ref() {
	if ( ! defined( 'MRN_DEPLOYMENT_REF' ) || ! is_string( MRN_DEPLOYMENT_REF ) ) {
		return 'not-set';
	}

	$value = preg_replace( '/[^a-zA-Z0-9._-]/', '', MRN_DEPLOYMENT_REF );

	return is_string( $value ) && '' !== $value ? substr( $value, 0, 80 ) : 'not-set';
}

/**
 * Find active SearchWP components for policy diagnostics.
 *
 * This only runs in wp-admin Site Health/admin-notice requests.
 *
 * @return array<int, string>
 */
function mrn_environment_runtime_active_searchwp_plugins() {
	$active = get_option( 'active_plugins', array() );
	$active = is_array( $active ) ? $active : array();

	if ( is_multisite() ) {
		$network_active = get_site_option( 'active_sitewide_plugins', array() );
		if ( is_array( $network_active ) ) {
			$active = array_merge( $active, array_keys( $network_active ) );
		}
	}

	$searchwp_plugins = array_filter(
		$active,
		static function ( $plugin ) {
			return is_string( $plugin ) && (
				0 === strpos( $plugin, 'searchwp/' ) ||
				0 === strpos( $plugin, 'searchwp-live-ajax-search/' ) ||
				0 === strpos( $plugin, 'searchwp-editor-performance/' )
			);
		}
	);

	return array_values( $searchwp_plugins );
}

/**
 * Add the contract to WordPress Site Health information.
 *
 * @param array $information Existing Site Health information.
 * @return array
 */
function mrn_environment_runtime_debug_information( $information ) {
	$contract = mrn_environment_runtime_contract();
	$fields   = array();

	foreach ( $contract as $key => $value ) {
		$fields[ $key ] = array(
			'label' => ucwords( str_replace( '_', ' ', $key ) ),
			'value' => $value,
		);
	}

	$fields['searchwp_policy_match'] = array(
		'label' => 'SearchWP policy match',
		'value' => 'disabled' === $contract['searchwp'] && ! empty( mrn_environment_runtime_active_searchwp_plugins() ) ? 'no' : 'yes',
	);

	$information['mrn-environment-runtime'] = array(
		'label'  => 'MRN Environment Runtime',
		'fields' => $fields,
	);

	return $information;
}

/**
 * Warn administrators when SearchWP conflicts with environment policy.
 */
function mrn_environment_runtime_searchwp_notice() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$contract = mrn_environment_runtime_contract();
	$active   = mrn_environment_runtime_active_searchwp_plugins();
	if ( 'disabled' !== $contract['searchwp'] || empty( $active ) ) {
		return;
	}

	printf(
		'<div class="notice notice-warning"><p>%s</p></div>',
		esc_html__( 'MRN environment policy disables SearchWP here, but one or more SearchWP components are active. Run the environment-policy reconciliation before performance testing.', 'mrn-environment-runtime' )
	);
}

if ( function_exists( 'is_admin' ) && is_admin() ) {
	add_filter( 'debug_information', 'mrn_environment_runtime_debug_information' );
	add_action( 'admin_notices', 'mrn_environment_runtime_searchwp_notice' );
}
