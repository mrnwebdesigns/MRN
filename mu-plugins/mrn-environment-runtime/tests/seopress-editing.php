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
$consent = array(
	'seopress_google_analytics_disable' => '1',
	'seopress_google_analytics_hook' => 'wp_footer',
	'seopress_google_analytics_opt_out_msg' => 'Saved consent message',
	'seopress_google_analytics_opt_out_msg_ok' => 'Accept all',
	'seopress_google_analytics_opt_out_msg_close' => 'Reject optional',
	'seopress_google_analytics_opt_out_edit_choice' => '1',
	'seopress_google_analytics_opt_out_msg_edit' => 'Cookie preferences',
	'seopress_google_analytics_cb_exp_date' => '30',
	'seopress_google_analytics_cb_bg' => '#FFFFFF',
	'seopress_google_analytics_cb_backdrop' => '1',
);
$tracking = array(
	'seopress_google_analytics_enable' => '1',
	'seopress_google_analytics_ga4' => 'G-TEST000000',
	'seopress_google_analytics_half_disable' => '1',
	'seopress_google_analytics_gtm' => 'GTM-TEST0000',
	'seopress_google_analytics_matomo_enable' => '1',
	'seopress_google_analytics_matomo_id' => 'https://invalid.example/',
	'seopress_google_analytics_clarity_enable' => '1',
	'seopress_google_analytics_clarity_project_id' => 'test-project',
	'seopress_google_analytics_other_tracking' => '<script>test()</script>',
	'seopress_google_analytics_other_tracking_body' => '<script>test()</script>',
	'seopress_google_analytics_other_tracking_footer' => '<script>test()</script>',
	'seopress_google_analytics_future_tracker' => '1',
);
$stored = $consent + $tracking;
if ( 'production' === $environment ) {
	check( empty( $hooks ), 'Production output must remain unchanged.' );
} else {
	check( isset( $hooks['pre_option_blog_public'], $hooks['option_seopress_google_analytics_option_name'] ), 'Development protection missing.' );
	check( ! isset( $hooks['pre_option_seopress_google_analytics_option_name'] ), 'Banner configuration is still short-circuited.' );
	check( $consent === $hooks['option_seopress_google_analytics_option_name']( $stored ), 'Consent was hidden or tracking leaked.' );
	check( $stored === $consent + $tracking, 'Stored launch settings changed.' );
	check( array() === mrn_environment_runtime_seopress_tracking( false ), 'Frontend tracking configuration leaked.' );
	check( '0' === mrn_environment_runtime_blog_public( false ), 'Frontend indexing was enabled.' );
	$admin = true;
	check( $stored === mrn_environment_runtime_seopress_tracking( $stored ), 'Admin launch settings were hidden.' );
	check( false === mrn_environment_runtime_blog_public( false ), 'Admin stored indexing preference was hidden.' );
	check( false === mrn_environment_runtime_seopress_schedule( null, (object) array( 'hook' => 'seopress_site_audit_run_task_cron' ) ), 'External audit job rearmed.' );
	check( null === mrn_environment_runtime_seopress_schedule( null, (object) array( 'hook' => 'updraft_backup' ) ), 'Unrelated cron was disabled.' );
	$admin = false;
	define( 'WP_CLI', true );
	check( $stored === mrn_environment_runtime_seopress_tracking( $stored ), 'CLI migrations cannot read original settings.' );
}
echo "PASS: SEOPress editing policy ($environment)\n";
