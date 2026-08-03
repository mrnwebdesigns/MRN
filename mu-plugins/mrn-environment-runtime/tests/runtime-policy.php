<?php
/**
 * Minimal standalone policy tests.
 */

define( 'ABSPATH', __DIR__ );
define( 'MRN_SITE_PROFILE', 'stack' );
define( 'MRN_WORKLOAD_CLASS', 'dynamic' );
define( 'MRN_PAGE_CACHE_POLICY', 'disabled' );
define( 'MRN_OBJECT_CACHE_POLICY', 'review_required' );
define( 'MRN_SEARCHWP_POLICY', 'disabled' );
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

require dirname( __DIR__ ) . '/mrn-environment-runtime.php';

$contract = mrn_environment_runtime_contract();

if ( 'development' !== $contract['environment'] ) {
	throw new RuntimeException( 'Environment resolution failed.' );
}
if ( 'dynamic' !== $contract['workload_class'] || 'disabled' !== $contract['page_cache'] ) {
	throw new RuntimeException( 'Performance policy resolution failed.' );
}
if ( 'disabled' !== $contract['searchwp'] || 'temporary' !== $contract['import_tools'] ) {
	throw new RuntimeException( 'Plugin feature policy resolution failed.' );
}
if ( 'abc123_featureunsafe' !== $contract['deployment_ref'] ) {
	throw new RuntimeException( 'Deployment reference sanitization failed.' );
}

echo "Environment runtime policy tests passed.\n";
