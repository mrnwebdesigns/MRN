<?php
/**
 * Minimal standalone policy tests.
 */

define( 'ABSPATH', __DIR__ );
define( 'MRN_SITE_PROFILE', 'stack' );
define( 'MRN_WORKLOAD_CLASS', 'dynamic' );
define( 'MRN_PAGE_CACHE_POLICY', 'disabled' );
define( 'MRN_OBJECT_CACHE_POLICY', 'review_required' );
define( 'MRN_SEARCHWP_POLICY', 'frontend_only' );
define( 'MRN_SEO_INDEXING_POLICY', 'disabled' );
define( 'MRN_IMPORT_TOOLS_POLICY', 'temporary' );
define( 'MRN_DEPLOY_CACHE_PURGE', 'object' );
define( 'MRN_ASSET_VERSION_SOURCE', 'commit_sha' );
define( 'MRN_DEPLOYMENT_REF', 'abc123_feature/unsafe' );

function wp_get_environment_type() {
	return 'development';
}

function is_admin() {
	return false;
}

function get_option( $option, $default = false ) {
	if ( 'active_plugins' === $option ) {
		return array(
			'searchwp/index.php',
			'searchwp-live-ajax-search/searchwp-live-ajax-search.php',
			'searchwp-editor-performance/mrn-searchwp-editor-performance.php',
		);
	}
	if ( 'searchwp_indexer_paused' === $option ) {
		return '1';
	}

	return $default;
}

function is_multisite() {
	return false;
}

require dirname( __DIR__ ) . '/mrn-environment-runtime.php';

$contract = mrn_environment_runtime_contract();

if ( 'development' !== $contract['environment'] ) {
	throw new RuntimeException( 'Environment resolution failed.' );
}
if ( 'dynamic' !== $contract['workload_class'] || 'disabled' !== $contract['page_cache'] ) {
	throw new RuntimeException( 'Performance policy resolution failed.' );
}
if ( 'frontend_only' !== $contract['searchwp'] || 'disabled' !== $contract['seo_indexing'] || 'temporary' !== $contract['import_tools'] ) {
	throw new RuntimeException( 'Plugin feature policy resolution failed.' );
}
if ( ! mrn_environment_runtime_searchwp_core_is_active() ) {
	throw new RuntimeException( 'Frontend-only SearchWP policy did not recognize active SearchWP core.' );
}
if ( ! mrn_environment_runtime_searchwp_indexer_is_paused() ) {
	throw new RuntimeException( 'Frontend-only SearchWP policy did not recognize the paused indexer.' );
}
if ( 'abc123_featureunsafe' !== $contract['deployment_ref'] ) {
	throw new RuntimeException( 'Deployment reference sanitization failed.' );
}

$opcache = mrn_environment_runtime_opcache_information(
	array(
		'opcache_enabled' => true,
		'cache_full'      => false,
		'memory_usage'    => array(
			'used_memory'   => 805306368,
			'free_memory'   => 805306368,
			'wasted_memory' => 0,
		),
		'opcache_statistics' => array(
			'opcache_hit_rate'  => 97.25,
			'num_cached_scripts' => 24500,
		),
	)
);

if ( 'yes' !== $opcache['enabled'] || 'no' !== $opcache['cache_full'] || '1536' !== $opcache['memory_mb'] ) {
	throw new RuntimeException( 'OPcache capacity reporting failed.' );
}
if ( '50.0' !== $opcache['free_percent'] || '97.3' !== $opcache['hit_rate'] || '24500' !== $opcache['cached_scripts'] ) {
	throw new RuntimeException( 'OPcache health calculation failed.' );
}

echo "Environment runtime policy tests passed.\n";
