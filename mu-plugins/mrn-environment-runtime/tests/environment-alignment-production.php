<?php
/**
 * Alignment tests for a production-declared site.
 *
 * The primary policy test declares a development environment, so the reverse
 * branch - production policy running on a review host - needs its own stubs.
 */

define( 'ABSPATH', __DIR__ );
define( 'MRN_SITE_PROFILE', 'stack' );
define( 'MRN_RELEVANSSI_POLICY', 'configured' );
define( 'MRN_SEO_INDEXING_POLICY', 'configured' );

function wp_get_environment_type() {
	return 'production';
}

function is_admin() {
	return false;
}

function get_option( $option, $default = false ) {
	if ( 'active_plugins' === $option ) {
		return array( 'relevanssi/relevanssi.php' );
	}
	if ( 'blog_public' === $option ) {
		return '1';
	}

	return $default;
}

function is_multisite() {
	return false;
}

require dirname( __DIR__ ) . '/mrn-environment-runtime.php';

// A production contract on a real domain is the aligned, expected case.
$live = mrn_environment_runtime_environment_alignment( 'freedomhouserecovery.org' );
if ( 'aligned' !== $live['status'] ) {
	throw new RuntimeException( 'Production policy on a production domain should be aligned.' );
}

// The same contract on a review host risks indexing a non-public site.
$review = mrn_environment_runtime_environment_alignment( 'quantumbloom.mrndev.io' );
if ( 'production_policy_on_non_production_host' !== $review['status'] ) {
	throw new RuntimeException( 'Production policy on a review host was not flagged.' );
}
if ( ! in_array( 'a review host is open to search engines', $review['risks'], true ) ) {
	throw new RuntimeException( 'Open-to-search-engines risk was not reported.' );
}
if ( ! in_array( 'SEO indexing is configured on a review host', $review['risks'], true ) ) {
	throw new RuntimeException( 'Configured SEO indexing risk was not reported.' );
}

// A local runtime is also a review host for this purpose.
$local = mrn_environment_runtime_environment_alignment( 'platform.localhost' );
if ( 'production_policy_on_non_production_host' !== $local['status'] ) {
	throw new RuntimeException( 'Production policy on a local host was not flagged.' );
}

echo "Environment alignment production tests passed.\n";
