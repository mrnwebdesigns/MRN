<?php
/** Native editing must not reactivate tracking, indexing, or disabled cron. */
// phpcs:disable WordPress.Security.EscapeOutput -- CLI-only fixture messages never render in WordPress.
define( 'ABSPATH', __DIR__ );
define( 'MRN_SEO_INDEXING_POLICY', 'disabled' );
$environment = $argv[1] ?? 'development';
$admin = false;
$hooks = array();
function wp_get_environment_type() { global $environment; return $environment; }
function is_admin() { global $admin; return $admin; }
function add_action( $hook, $callback, $priority = 10, $args = 1 ) {}
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) { global $hooks; $hooks[$hook] = $callback; }
function check( $condition, $message ) { if ( ! $condition ) { throw new RuntimeException( $message ); } }
require dirname( __DIR__ ) . '/mrn-environment-runtime.php';
if ( 'production' === $environment ) {
	check( empty( $hooks ), 'Production output must remain unchanged.' );
} else {
	check( isset( $hooks['pre_option_blog_public'], $hooks['pre_option_seopress_google_analytics_option_name'] ), 'Development protection missing.' );
	check( array() === mrn_environment_runtime_seopress_tracking( false ), 'Frontend tracking configuration leaked.' );
	check( '0' === mrn_environment_runtime_blog_public( false ), 'Frontend indexing was enabled.' );
	$admin = true;
	check( false === mrn_environment_runtime_seopress_tracking( false ), 'Admin launch settings were hidden.' );
	check( false === mrn_environment_runtime_blog_public( false ), 'Admin stored indexing preference was hidden.' );
	check( false === mrn_environment_runtime_seopress_schedule( null, (object) array( 'hook' => 'seopress_site_audit_run_task_cron' ) ), 'External audit job rearmed.' );
	check( null === mrn_environment_runtime_seopress_schedule( null, (object) array( 'hook' => 'updraft_backup' ) ), 'Unrelated cron was disabled.' );
	$admin = false;
	define( 'WP_CLI', true );
	check( false === mrn_environment_runtime_seopress_tracking( false ), 'CLI migrations cannot read original settings.' );
}
echo "PASS: SEOPress editing policy ($environment)\n";
