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

function esc_html( $text ) {
	return $text;
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

// --- Host signal classification -------------------------------------------

$host_cases = array(
	// Production domains.
	'mrnwebdesigns.com'              => 'production',
	'freedomhouserecovery.org'       => 'production',
	'devon-smith.com'                => 'production',
	'demoulas-market.com'            => 'production',
	'www.thecaryingplace.org'        => 'production',
	// Local, review, and provider temporary hosts.
	'platform.localhost'             => 'non_production',
	'localhost'                      => 'non_production',
	'quantumbloom.mrndev.io'         => 'non_production',
	'runcloudtest-staging.mrndev.io' => 'non_production',
	'eloghomes.tempurl.host'         => 'non_production',
	'example.wpengine.com'           => 'non_production',
	'example.sg-host.com'            => 'non_production',
	'192.168.1.10'                   => 'non_production',
	// Environment-token labels on otherwise real domains.
	'staging3.bestdaypsych.com'      => 'non_production',
	'bdp.dev-humbird.com'            => 'non_production',
	'qa.example.com'                 => 'non_production',
	'demo.example.com'               => 'non_production',
);

foreach ( $host_cases as $host => $expected ) {
	$actual = mrn_environment_runtime_host_signal( $host );
	if ( $expected !== $actual ) {
		throw new RuntimeException( esc_html( "Host signal for {$host} expected {$expected}, got {$actual}." ) );
	}
}

if ( 'unknown' !== mrn_environment_runtime_host_signal( '' ) ) {
	throw new RuntimeException( 'Empty host should be unknown.' );
}

if ( 'mrnwebdesigns.com' !== mrn_environment_runtime_site_host( 'https://www.mrnwebdesigns.com:8443/path' ) ) {
	throw new RuntimeException( 'Host normalization failed.' );
}

// --- Environment alignment -------------------------------------------------

// Declared development (see wp_get_environment_type stub) on a live domain.
$live = mrn_environment_runtime_environment_alignment( 'freedomhouserecovery.org' );
if ( 'live_without_production_policy' !== $live['status'] ) {
	throw new RuntimeException( 'Live domain with development policy was not flagged.' );
}
if ( ! in_array( 'SEO indexing policy is disabled', $live['risks'], true ) ) {
	throw new RuntimeException( 'Disabled SEO indexing risk was not reported.' );
}
if ( ! in_array( 'the SearchWP index is paused, so site search serves stale results', $live['risks'], true ) ) {
	throw new RuntimeException( 'Paused SearchWP index risk was not reported.' );
}

// Same declared policy on a review host is the expected, aligned case.
$review = mrn_environment_runtime_environment_alignment( 'quantumbloom.mrndev.io' );
if ( 'aligned' !== $review['status'] ) {
	throw new RuntimeException( 'Review host with development policy should be aligned.' );
}

if ( 'unknown' !== mrn_environment_runtime_environment_alignment( '' )['status'] ) {
	throw new RuntimeException( 'Unresolvable host should report unknown alignment.' );
}

echo "Environment runtime policy tests passed.\n";
