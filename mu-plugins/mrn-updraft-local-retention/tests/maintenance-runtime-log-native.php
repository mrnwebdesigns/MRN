<?php
/** Extend the existing disposable native-maintenance fixture; never load on a site. */

$log_fixture_slug = 'runtime-log-fixture/runtime-log-fixture.php';
wp_mkdir_p( WP_PLUGIN_DIR . '/runtime-log-fixture' );
file_put_contents( WP_PLUGIN_DIR . '/' . $log_fixture_slug, "<?php\n/*\nPlugin Name: Runtime Log Fixture\nVersion: 1.0\n*/\n" );
$log_fixture_zip = mrn_zip( 'runtime-log-fixture', '1.1' );
$log_fixture_path = ABSPATH . 'wp-admin/php_errorlog';
$log_fixture_original = ini_get( 'error_log' ); $log_fixture_enabled = ini_get( 'log_errors' );
$log_fixture_code = ABSPATH . 'wp-admin/index.php'; $log_fixture_code_source = file_get_contents( $log_fixture_code );
$log_fixture_download = static function ( $pre, $args, $url ) use ( &$log_fixture_zip ) {
	if ( ! empty( $args['stream'] ) && in_array( $url, array( 'https://downloads.wordpress.org/plugin/runtime-log-fixture.1.1.zip', 'https://downloads.wordpress.org/plugin/runtime-log-fixture.1.2.zip' ), true ) ) {
		copy( $log_fixture_zip, $args['filename'] );
		return array( 'response' => array( 'code' => 200 ), 'headers' => array(), 'body' => '' );
	}
	return $pre;
};
add_filter( 'pre_http_request', $log_fixture_download, 20, 3 );
try {
	// phpcs:disable WordPress.PHP.IniSet -- Disposable native fixture only; restored below.
	ini_set( 'error_log', $log_fixture_path ); ini_set( 'log_errors', '1' );
	// phpcs:enable
	file_put_contents( $log_fixture_path, 'private synthetic native log' );
	$mrn_backup_time = time() - 30;
	$mrn_offers['plugin']->response[ $log_fixture_slug ] = (object) array( 'slug' => 'runtime-log-fixture', 'plugin' => $log_fixture_slug, 'new_version' => '1.1', 'package' => 'https://downloads.wordpress.org/plugin/runtime-log-fixture.1.1.zip' );
	$request = array( 'kind' => 'plugin', 'site_id' => 17, 'site_url' => 'https://maintenance.example.org', 'items' => array( array( 'slug' => $log_fixture_slug, 'from' => '1.0', 'to' => '1.1' ) ), 'major_overrides' => array(), 'backup_receipt' => array( 'site_id' => 17, 'nonce' => '008900000001', 'receipt' => str_repeat( 'r', 32 ) ) );
	set_transient( MRN_MainWP_Operations_API::receipt_key( 17, '008900000001' ), $stored_receipt, 7200 );
	$preview = MRN_MainWP_Maintenance::preflight( $request );
	mrn_assert( ! is_wp_error( $preview ) && $preview['ready'], 'native configured-log preview passes all provider and child gates' );
	file_put_contents( $log_fixture_path, '\nlog growth between previews', FILE_APPEND );
	$again = MRN_MainWP_Maintenance::preflight( $request );
	mrn_assert( ! is_wp_error( $again ) && $preview['plan_sha256'] === $again['plan_sha256'], 'native growing-log preview preserves exact stable plan identity' );
	file_put_contents( $log_fixture_code, '\n// synthetic actual core-code change', FILE_APPEND );
	$operation = $request + array( 'confirm' => true, 'request_id' => wp_generate_uuid4(), 'plan_sha256' => $preview['plan_sha256'] );
	$refused = MRN_MainWP_Maintenance::execute( $operation );
	wp_clean_plugins_cache( false );
	mrn_assert( ! is_wp_error( $refused ) && 'outcome_unknown' === $refused['status'] && 'stale_plan' === $refused['failure']['code'] && 'child_snapshot' === $refused['failure']['stage'] && '1.0' === get_plugins()[ $log_fixture_slug ]['Version'], 'native actual core-code drift returns exact child stale-plan refusal before changing selected plugin' );
	file_put_contents( $log_fixture_code, $log_fixture_code_source );
	$request['backup_receipt']['nonce'] = '008900000002';
	set_transient( MRN_MainWP_Operations_API::receipt_key( 17, '008900000002' ), $stored_receipt, 7200 );
	$preview = MRN_MainWP_Maintenance::preflight( $request );
	mrn_assert( ! is_wp_error( $preview ) && $preview['ready'], 'restored native code requires and receives a fresh reviewed plan' );
	$before = mrn_private( MRN_Updraft_MainWP_Maintenance::class, 'state' );
	file_put_contents( $log_fixture_path, '\nlog growth before apply', FILE_APPEND );
	$operation = $request + array( 'confirm' => true, 'request_id' => wp_generate_uuid4(), 'plan_sha256' => $preview['plan_sha256'] );
	$result = MRN_MainWP_Maintenance::execute( $operation );
	mrn_assert( ! is_wp_error( $result ) && 'complete' === $result['status'], 'native confirm applies exact approved update despite configured log growth' );
	file_put_contents( $log_fixture_path, '\nlog growth before independent readback', FILE_APPEND );
	$proof = MRN_MainWP_Maintenance::read( array( 'kind' => 'plugin', 'site_id' => 17, 'site_url' => $request['site_url'], 'slugs' => array( $log_fixture_slug ), 'request_id' => $operation['request_id'], 'plan_sha256' => $preview['plan_sha256'] ) );
	mrn_assert( ! is_wp_error( $proof ) && 'complete' === $proof['provider_status'] && '1.1' === $proof['items'][0]['version'] && $proof['unrelated_unchanged'] && $proof['core_unchanged'], 'native independent readback verifies exact version and unchanged protected state despite later log growth' );
	$after = mrn_private( MRN_Updraft_MainWP_Maintenance::class, 'state' );
	mrn_assert( $before['core'] === $after['core'] && $before['themes'] === $after['themes'] && $before['plugins'][ $log_fixture_slug ]['active'] === $after['plugins'][ $log_fixture_slug ]['active'], 'native log policy preserves core-code digest, theme selection and activation' );
	$calls = $mrn_calls;
	mrn_assert( MRN_MainWP_Maintenance::execute( $operation ) === $result && $calls === $mrn_calls, 'native retry uses existing ledger without a second update' );
	unlink( $log_fixture_zip ); $log_fixture_zip = mrn_zip( 'runtime-log-fixture', '1.2' );
	$mrn_offers['plugin']->response[ $log_fixture_slug ]->new_version = '1.2';
	$mrn_offers['plugin']->response[ $log_fixture_slug ]->package = 'https://downloads.wordpress.org/plugin/runtime-log-fixture.1.2.zip';
	$request['items'][0]['from'] = '1.1'; $request['items'][0]['to'] = '1.2';
	$request['backup_receipt']['nonce'] = '008900000003';
	set_transient( MRN_MainWP_Operations_API::receipt_key( 17, '008900000003' ), $stored_receipt, 7200 );
	$valid_again = MRN_MainWP_Maintenance::preflight( $request );
	mrn_assert( ! is_wp_error( $valid_again ) && $valid_again['ready'], 'native negative cases start from valid fresh receipt and pinned offer' );
	foreach ( array( 'core', 'major', 'wrong_site', 'missing_receipt', 'remote_unverified' ) as $case ) {
		$bad = $request; $bad['items'][0]['from'] = '1.1'; $bad['items'][0]['to'] = '1.2';
		if ( 'core' === $case ) { $bad['kind'] = 'core'; }
		if ( 'major' === $case ) { $bad['items'][0]['to'] = '2.0'; }
		if ( 'wrong_site' === $case ) { $bad['site_url'] = 'https://wrong.example.org'; }
		if ( 'missing_receipt' === $case ) { $bad['backup_receipt']['receipt'] = str_repeat( 'x', 32 ); }
		if ( 'remote_unverified' === $case ) { $mrn_remote = false; }
		mrn_assert( mrn_is_refusal( MRN_MainWP_Maintenance::preflight( $bad ) ), 'native configured-log exception cannot bypass ' . $case . ' refusal' );
		$mrn_remote = true;
	}
} finally {
	// phpcs:disable WordPress.PHP.IniSet -- Restore fixture-only settings.
	ini_set( 'error_log', $log_fixture_original ); ini_set( 'log_errors', $log_fixture_enabled );
	// phpcs:enable
	file_put_contents( $log_fixture_code, $log_fixture_code_source );
	if ( is_file( $log_fixture_path ) ) { unlink( $log_fixture_path ); }
	remove_filter( 'pre_http_request', $log_fixture_download, 20 );
	if ( is_file( $log_fixture_zip ) ) { unlink( $log_fixture_zip ); }
}
