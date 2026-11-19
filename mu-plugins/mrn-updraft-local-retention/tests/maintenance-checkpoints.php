<?php
/** Abrupt worker termination must retain only a bounded phase, never authority. */

if ( 'cli' !== PHP_SAPI ) {
	http_response_code( 404 );
	exit;
}

$worker = '--interrupt' === ( $argv[1] ?? '' );
$fixture = sys_get_temp_dir() . '/mrn-checkpoint-' . bin2hex( random_bytes( 8 ) );
mkdir( $fixture . '/wp-admin/includes', 0700, true );
foreach ( array( 'plugin.php', 'file.php', 'update.php', 'class-wp-upgrader.php' ) as $name ) {
	file_put_contents( $fixture . '/wp-admin/includes/' . $name, '<?php' );
}
register_shutdown_function( static function () use ( $fixture ) {
	foreach ( glob( $fixture . '/wp-admin/includes/*' ) as $file ) { unlink( $file ); }
	rmdir( $fixture . '/wp-admin/includes' ); rmdir( $fixture . '/wp-admin' ); rmdir( $fixture );
} );
define( 'ABSPATH', $fixture . '/' );
$store = $worker ? $argv[2] : tempnam( sys_get_temp_dir(), 'mrn-checkpoint-store-' );
chmod( $store, 0600 );
$transients = array(); $writes = 0; $backup_calls = 0;
function wp_unslash( $value ) { return stripslashes( $value ); }
function untrailingslashit( $value ) { return rtrim( $value, '/' ); }
function wp_parse_url( $value ) { return parse_url( $value ); }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
function home_url( $path = '' ) { return 'https://example.com/'; }
function is_multisite() { return false; }
function get_filesystem_method() { return 'direct'; }
function esc_html( $value ) { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
function get_transient( $key ) { return $GLOBALS['transients'][ $key ] ?? false; }
function set_transient( $key, $value, $ttl ) {
	if ( 600 !== $ttl ) { throw new RuntimeException( 'Unexpected checkpoint lifetime.' ); }
	$GLOBALS['transients'][ $key ] = $value; ++$GLOBALS['writes'];
	file_put_contents( $GLOBALS['store'], json_encode( $GLOBALS['transients'] ), LOCK_EX ); return true;
}
function delete_transient( $key ) { unset( $GLOBALS['transients'][ $key ] ); }
class WP_Error {
	public function get_error_code() { return 'mrn_updraft_backup_remote_verification_failed'; }
}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
class MRN_Updraft_MainWP_Policy {
	public static function maintenance_backup( $nonce ) {
		++$GLOBALS['backup_calls'];
		if ( $GLOBALS['worker'] ) { exit( 17 ); }
		return new WP_Error();
	}
}
require dirname( __DIR__ ) . '/includes/class-mrn-updraft-mainwp-maintenance.php';
$input = array( 'protocol' => 1, 'operation' => 'preflight', 'site_id' => 17, 'site_url' => 'https://example.com', 'kind' => 'plugin', 'items' => array( array( 'slug' => 'fixture/fixture.php', 'from' => '1.0', 'to' => '1.1' ) ), 'major_overrides' => array(), 'backup_nonce' => 'abcdef123456' );
$post = array( 'function' => 'extra_execution', 'mrn_maintenance_request' => json_encode( $input ) );
if ( $worker ) { MRN_Updraft_MainWP_Maintenance::dispatch( array(), $post ); exit( 1 ); }
$checks = array();
function checkpoint_check( $ok, $label ) {
	if ( ! $ok ) { throw new RuntimeException( 'Checkpoint regression: ' . esc_html( $label ) ); }
	$GLOBALS['checks'][] = $label;
}
function checkpoint_report( $post = null, $info = null ) {
	return MRN_Updraft_MainWP_Maintenance::dispatch( $info ?? array( 'success' => true, 'report' => array( 'unrelated' => 'preserved' ) ), $post ?? array( 'function' => 'extra_execution', 'mrn_stack_report_action' => 'report' ) );
}
try {
	$process = proc_open( array( PHP_BINARY, __FILE__, '--interrupt', $store ), array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_proc_open -- Disposable CLI-only worker; fixed executable/script, never a runtime route.
	checkpoint_check( is_resource( $process ), 'disposable worker starts' );
	foreach ( $pipes as $pipe ) { fclose( $pipe ); }
	checkpoint_check( 17 === proc_close( $process ), 'worker terminates during native backup verification before a return or catch' );
	$transients = json_decode( file_get_contents( $store ), true );
	$report = checkpoint_report(); $record = $report['report']['last_maintenance_preflight_checkpoint'] ?? null;
	checkpoint_check( is_array( $record ) && 'child_backup' === $record['stage'] && 'in_progress' === $record['state'], 'last reached phase survives abrupt termination without claiming completion' );
	checkpoint_check( 17 === $record['site_id'] && MRN_Updraft_MainWP_Maintenance::digest( $input ) === $record['request_sha256'], 'observation is bound to the exact site and request digest' );
	checkpoint_check( ! isset( $report['report']['last_maintenance_preflight_refusal'] ) && 'preserved' === $report['report']['unrelated'], 'interruption does not fabricate an error reason and preserves unrelated report data' );
	checkpoint_check( false === strpos( file_get_contents( $store ), $input['backup_nonce'] ), 'stored record contains no raw backup nonce or request' );
	$previous = file_get_contents( $store ); checkpoint_report();
	checkpoint_check( 0 === $writes && $previous === file_get_contents( $store ) && 0 === $backup_calls, 'reading a checkpoint performs no writes, network or backup work' );
	foreach ( array( array( 'function' => 'other', 'mrn_stack_report_action' => 'report' ), array( 'function' => 'extra_execution', 'unrelated' => 'report' ) ) as $other ) {
		checkpoint_check( ! isset( checkpoint_report( $other )['report']['last_maintenance_preflight_checkpoint'] ), 'unrelated dispatch exposes no checkpoint' );
	}
	checkpoint_check( ! isset( checkpoint_report( null, array( 'success' => false, 'report' => array() ) )['report']['last_maintenance_preflight_checkpoint'] ), 'failed runtime reports gain no checkpoint' );
	$variants = array( $record + array( 'private' => 'fixture-secret' ), array_replace( $record, array( 'stage' => 'unknown' ) ), array_replace( $record, array( 'state' => 'executed' ) ), array_replace( $record, array( 'site_id' => '17' ) ), array_replace( $record, array( 'request_sha256' => 'invalid' ) ), array_replace( $record, array( 'updated_at' => time() + 60 ) ), array_replace( $record, array( 'started_at' => time() - 600 ) ), array_replace( $record, array( 'item_slug' => 'https://private.invalid/?secret' ) ), array_replace( $record, array( 'item_slug' => str_repeat( 'a', 201 ) ) ) );
	foreach ( $variants as $variant ) {
		$transients[ MRN_Updraft_MainWP_Maintenance::CHECKPOINT_KEY ] = $variant;
		checkpoint_check( ! isset( checkpoint_report()['report']['last_maintenance_preflight_checkpoint'] ), 'tampered, future, expired or unbounded metadata is suppressed' );
	}
	$transients[ MRN_Updraft_MainWP_Maintenance::CHECKPOINT_KEY ] = $record;
	$bad = $input; $bad['site_url'] = 'https://wrong.invalid';
	MRN_Updraft_MainWP_Maintenance::dispatch( array(), array( 'function' => 'extra_execution', 'mrn_maintenance_request' => json_encode( $bad ) ) );
	checkpoint_check( 0 === $writes && $record === $transients[ MRN_Updraft_MainWP_Maintenance::CHECKPOINT_KEY ], 'wrong site cannot overwrite the observation' );
	$bad = $input; $bad['kind'] = 'core';
	MRN_Updraft_MainWP_Maintenance::dispatch( array(), array( 'function' => 'extra_execution', 'mrn_maintenance_request' => json_encode( $bad ) ) );
	checkpoint_check( 0 === $backup_calls && $record === $transients[ MRN_Updraft_MainWP_Maintenance::CHECKPOINT_KEY ], 'core remains refused before backup work or a new checkpoint' );
	MRN_Updraft_MainWP_Maintenance::dispatch( array(), $post ); $report = checkpoint_report();
	checkpoint_check( 'refused' === $report['report']['last_maintenance_preflight_checkpoint']['state'] && 'child_backup' === $report['report']['last_maintenance_preflight_checkpoint']['stage'] && 'backup_remote_verification_failed' === $report['report']['last_maintenance_preflight_refusal']['code'], 'ordinary refusal keeps the original code and observed phase without bypassing the backup gate' );
	echo json_encode( array( 'status' => 'pass', 'checks' => $checks, 'network_requests' => 0, 'production_writes' => 0 ), JSON_PRETTY_PRINT ) . "\n";
} finally {
	unlink( $store );
}
