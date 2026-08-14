<?php
/**
 * Plugin Name: MRN Environment Runtime
 * Description: Reports the deployment-managed environment and performance policy without adding frontend work.
 * Version: 0.3.1
 * Author: MRN Web Designs
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'MRN_ENVIRONMENT_RUNTIME_VERSION' ) ) {
	define( 'MRN_ENVIRONMENT_RUNTIME_VERSION', '0.3.1' );
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
		'searchwp'            => mrn_environment_runtime_constant( 'MRN_SEARCHWP_POLICY', array( 'disabled', 'frontend_only', 'configured' ), 'disabled' ),
		'seo_indexing'        => mrn_environment_runtime_constant( 'MRN_SEO_INDEXING_POLICY', array( 'disabled', 'configured' ), 'disabled' ),
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
 * Return display-safe PHP OPcache health information.
 *
 * This is called only from wp-admin Site Health and admin notices. Passing a
 * status array keeps the calculation independently testable.
 *
 * @param array<string, mixed>|false|null $status Optional OPcache status.
 * @return array<string, string>
 */
function mrn_environment_runtime_opcache_information( $status = null ) {
	if ( null === $status ) {
		$status = function_exists( 'opcache_get_status' ) ? opcache_get_status( false ) : false;
	}

	if ( ! is_array( $status ) || empty( $status['opcache_enabled'] ) ) {
		return array(
			'enabled'        => 'no',
			'cache_full'     => 'unknown',
			'memory_mb'      => '0',
			'free_percent'   => '0.0',
			'hit_rate'       => '0.0',
			'cached_scripts' => '0',
		);
	}

	$memory       = isset( $status['memory_usage'] ) && is_array( $status['memory_usage'] ) ? $status['memory_usage'] : array();
	$statistics   = isset( $status['opcache_statistics'] ) && is_array( $status['opcache_statistics'] ) ? $status['opcache_statistics'] : array();
	$used_memory  = max( 0, (int) ( $memory['used_memory'] ?? 0 ) );
	$free_memory  = max( 0, (int) ( $memory['free_memory'] ?? 0 ) );
	$total_memory = $used_memory + $free_memory;
	$free_percent = $total_memory > 0 ? ( $free_memory / $total_memory ) * 100 : 0.0;
	$hit_rate     = max( 0.0, min( 100.0, (float) ( $statistics['opcache_hit_rate'] ?? 0.0 ) ) );

	return array(
		'enabled'        => 'yes',
		'cache_full'     => ! empty( $status['cache_full'] ) ? 'yes' : 'no',
		'memory_mb'      => number_format( $total_memory / 1048576, 0, '.', '' ),
		'free_percent'   => number_format( $free_percent, 1, '.', '' ),
		'hit_rate'       => number_format( $hit_rate, 1, '.', '' ),
		'cached_scripts' => (string) max( 0, (int) ( $statistics['num_cached_scripts'] ?? 0 ) ),
	);
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
 * Determine whether SearchWP core is active.
 *
 * This only runs in wp-admin Site Health/admin-notice requests.
 *
 * @return bool
 */
function mrn_environment_runtime_searchwp_core_is_active() {
	foreach ( mrn_environment_runtime_active_searchwp_plugins() as $plugin ) {
		if ( 0 === strpos( $plugin, 'searchwp/' ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Determine whether SearchWP's persistent indexer pause is enabled.
 *
 * This only runs in wp-admin Site Health/admin-notice requests.
 *
 * @return bool
 */
function mrn_environment_runtime_searchwp_indexer_is_paused() {
	$value = get_option( 'searchwp_indexer_paused', '0' );

	return in_array( $value, array( true, 1, '1' ), true );
}

/**
 * Find active SEO indexing plugins for policy diagnostics.
 *
 * This only runs in wp-admin Site Health/admin-notice requests.
 *
 * @return array<int, string>
 */
function mrn_environment_runtime_active_seo_indexing_plugins() {
	$active = get_option( 'active_plugins', array() );
	$active = is_array( $active ) ? $active : array();

	if ( is_multisite() ) {
		$network_active = get_site_option( 'active_sitewide_plugins', array() );
		if ( is_array( $network_active ) ) {
			$active = array_merge( $active, array_keys( $network_active ) );
		}
	}

	$seo_plugins = array_filter(
		$active,
		static function ( $plugin ) {
			return is_string( $plugin ) && (
				0 === strpos( $plugin, 'wpmu-dev-seo/' ) ||
				0 === strpos( $plugin, 'smartcrawl-seo/' )
			);
		}
	);

	return array_values( $seo_plugins );
}

/**
 * Add the contract to WordPress Site Health information.
 *
 * @param array $information Existing Site Health information.
 * @return array
 */
function mrn_environment_runtime_debug_information( $information ) {
	$contract = mrn_environment_runtime_contract();
	$opcache  = mrn_environment_runtime_opcache_information();
	$fields   = array();

	foreach ( $contract as $key => $value ) {
		$fields[ $key ] = array(
			'label' => ucwords( str_replace( '_', ' ', $key ) ),
			'value' => $value,
		);
	}

	$searchwp_active = mrn_environment_runtime_active_searchwp_plugins();
	$searchwp_core   = mrn_environment_runtime_searchwp_core_is_active();
	$indexer_paused  = mrn_environment_runtime_searchwp_indexer_is_paused();

	if ( 'disabled' === $contract['searchwp'] ) {
		$searchwp_match = empty( $searchwp_active );
	} elseif ( 'frontend_only' === $contract['searchwp'] ) {
		$searchwp_match = $searchwp_core && $indexer_paused;
	} else {
		$searchwp_match = $searchwp_core && ! $indexer_paused;
	}

	$fields['searchwp_policy_match'] = array(
		'label' => 'SearchWP policy match',
		'value' => $searchwp_match ? 'yes' : 'no',
	);
	$fields['searchwp_indexer_paused'] = array(
		'label' => 'SearchWP indexer paused',
		'value' => $indexer_paused ? 'yes' : 'no',
	);
	$fields['seo_indexing_policy_match'] = array(
		'label' => 'SEO indexing policy match',
		'value' => 'disabled' === $contract['seo_indexing'] && ! empty( mrn_environment_runtime_active_seo_indexing_plugins() ) ? 'no' : 'yes',
	);
	$fields['opcache_enabled'] = array(
		'label' => 'PHP OPcache enabled',
		'value' => $opcache['enabled'],
	);
	$fields['opcache_cache_full'] = array(
		'label' => 'PHP OPcache full',
		'value' => $opcache['cache_full'],
	);
	$fields['opcache_memory_mb'] = array(
		'label' => 'PHP OPcache capacity (MB)',
		'value' => $opcache['memory_mb'],
	);
	$fields['opcache_free_percent'] = array(
		'label' => 'PHP OPcache free memory (%)',
		'value' => $opcache['free_percent'],
	);
	$fields['opcache_hit_rate'] = array(
		'label' => 'PHP OPcache hit rate (%)',
		'value' => $opcache['hit_rate'],
	);
	$fields['opcache_cached_scripts'] = array(
		'label' => 'PHP OPcache cached scripts',
		'value' => $opcache['cached_scripts'],
	);

	$information['mrn-environment-runtime'] = array(
		'label'  => 'MRN Environment Runtime',
		'fields' => $fields,
	);

	return $information;
}

/**
 * Warn administrators when PHP OPcache cannot cache additional scripts.
 */
function mrn_environment_runtime_opcache_notice() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$opcache = mrn_environment_runtime_opcache_information();
	if ( 'yes' !== $opcache['cache_full'] ) {
		return;
	}

	printf(
		'<div class="notice notice-error"><p>%s</p></div>',
		esc_html__( 'PHP OPcache is full. Increase server-level OPcache capacity and verify healthy free memory before performance testing or production rollout.', 'mrn-environment-runtime' )
	);
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
	if ( 'disabled' === $contract['searchwp'] && ! empty( $active ) ) {
		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			esc_html__( 'MRN environment policy disables SearchWP here, but one or more SearchWP components are active. Run the environment-policy reconciliation before performance testing.', 'mrn-environment-runtime' )
		);
		return;
	}

	if ( in_array( $contract['searchwp'], array( 'frontend_only', 'configured' ), true ) && ! mrn_environment_runtime_searchwp_core_is_active() ) {
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__( 'MRN environment policy requires SearchWP forms and frontend search, but SearchWP core is inactive. Activate SearchWP or change the environment policy.', 'mrn-environment-runtime' )
		);
		return;
	}

	if ( 'frontend_only' === $contract['searchwp'] && ! mrn_environment_runtime_searchwp_indexer_is_paused() ) {
		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			esc_html__( 'MRN SearchWP policy is frontend-only, but the SearchWP indexer is not paused. Run development environment-policy reconciliation before performance testing.', 'mrn-environment-runtime' )
		);
		return;
	}

	if ( 'configured' === $contract['searchwp'] && mrn_environment_runtime_searchwp_indexer_is_paused() ) {
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__( 'MRN SearchWP policy is configured for full search, but the SearchWP indexer is still paused. Unpause and rebuild the index before production release.', 'mrn-environment-runtime' )
		);
		return;
	}
}

/**
 * Warn administrators when SEO indexing conflicts with environment policy.
 */
function mrn_environment_runtime_seo_indexing_notice() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$contract = mrn_environment_runtime_contract();
	$active   = mrn_environment_runtime_active_seo_indexing_plugins();
	if ( 'disabled' !== $contract['seo_indexing'] || empty( $active ) ) {
		return;
	}

	printf(
		'<div class="notice notice-warning"><p>%s</p></div>',
		esc_html__( 'MRN environment policy disables SEO indexing here, but an SEO indexing plugin is active. Run the environment-policy reconciliation before performance testing.', 'mrn-environment-runtime' )
	);
}

if ( function_exists( 'is_admin' ) && is_admin() ) {
	add_filter( 'debug_information', 'mrn_environment_runtime_debug_information' );
	add_action( 'admin_notices', 'mrn_environment_runtime_searchwp_notice' );
	add_action( 'admin_notices', 'mrn_environment_runtime_seo_indexing_notice' );
	add_action( 'admin_notices', 'mrn_environment_runtime_opcache_notice' );
}
