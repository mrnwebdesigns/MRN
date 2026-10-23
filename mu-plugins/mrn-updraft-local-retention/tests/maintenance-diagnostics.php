<?php
/** Bounded, secret-free diagnostics on the existing authenticated report. */

$fixture = sys_get_temp_dir() . '/mrn-diagnostic-' . bin2hex( random_bytes( 8 ) );
mkdir( $fixture . '/wp-admin/includes', 0700, true );
foreach ( array( 'plugin.php', 'file.php', 'update.php', 'class-wp-upgrader.php' ) as $name ) {
	file_put_contents( $fixture . '/wp-admin/includes/' . $name, '<?php' );
}
define( 'ABSPATH', $fixture . '/' );
$transients = array(); $writes = array(); $backup_calls = 0; $runtime = 'direct'; $backup_error = 'mrn_updraft_backup_remote_verification_failed'; $throw_private = false;
function wp_unslash( $value ) { return stripslashes( $value ); }
function untrailingslashit( $value ) { return rtrim( $value, '/' ); }
function wp_parse_url( $value ) { return parse_url( $value ); }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
function home_url( $path = '' ) { return 'https://example.com/'; }
function is_multisite() { return false; }
function get_filesystem_method() { return $GLOBALS['runtime']; }
function esc_html( $value ) { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
function get_transient( $key ) { return $GLOBALS['transients'][ $key ] ?? false; }
function set_transient( $key, $value, $ttl ) { $GLOBALS['transients'][ $key ] = $value; $GLOBALS['writes'][] = array( $key, $ttl ); return true; }
function delete_transient( $key ) { unset( $GLOBALS['transients'][ $key ] ); }
class WP_Error {
	public $code;
	public function __construct( $code ) { $this->code = $code; }
	public function get_error_code() { return $this->code; }
}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
class MRN_Updraft_MainWP_Policy {
	public static function maintenance_backup( $nonce ) {
		++$GLOBALS['backup_calls'];
		if ( $GLOBALS['throw_private'] ) { throw new RuntimeException( 'fixture-private-provider-message' ); }
		return new WP_Error( $GLOBALS['backup_error'] );
	}
}
require dirname( __DIR__ ) . '/includes/class-mrn-updraft-mainwp-maintenance.php';
$checks = array();
function check_diagnostic( $ok, $label ) {
	global $checks;
	if ( ! $ok ) { throw new RuntimeException( 'Diagnostic regression.' ); }
	$checks[] = $label;
}
function diagnostic_report( $post = null, $information = null ) {
	return MRN_Updraft_MainWP_Maintenance::dispatch( $information ?? array( 'success' => true, 'report' => array( 'preserved' => 'unchanged' ) ), $post ?? array( 'function' => 'extra_execution', 'mrn_stack_report_action' => 'report' ) );
}
$input = array( 'protocol' => 1, 'operation' => 'preflight', 'site_id' => 17, 'site_url' => 'https://example.com', 'kind' => 'plugin', 'items' => array( array( 'slug' => 'fixture/fixture.php', 'from' => '1.0', 'to' => '1.1' ) ), 'major_overrides' => array(), 'backup_nonce' => 'abcdef123456' );
$post = array( 'function' => 'extra_execution', 'mrn_maintenance_request' => json_encode( $input ) );
try {
	$response = MRN_Updraft_MainWP_Maintenance::dispatch( array(), $post );
	check_diagnostic( false === $response['success'] && 'mrn_maintenance_refused' === $response['error_code'], 'original refusal contract retained' );
	$report = diagnostic_report(); $diagnostic = $report['report']['last_maintenance_preflight_refusal'];
	check_diagnostic( 'backup_remote_verification_failed' === $diagnostic['code'] && array( 'schema_version', 'code', 'recorded_at' ) === array_keys( $diagnostic ), 'closed backup reason with fixed metadata only' );
	check_diagnostic( 'unchanged' === $report['report']['preserved'] && 1 === $backup_calls, 'report preserves unrelated data and performs no backup check' );
	$count = count( $writes ); diagnostic_report();
	check_diagnostic( count( $writes ) === $count, 'report is read-only' );
	$throw_private = true; MRN_Updraft_MainWP_Maintenance::dispatch( array(), $post ); $throw_private = false;
	$report = diagnostic_report();
	check_diagnostic( 'internal_error' === $report['report']['last_maintenance_preflight_refusal']['code'] && false === strpos( json_encode( $report ), 'fixture-private-provider-message' ), 'native exception text never returned or retained' );
	$backup_error = 'mrn_updraft_unrecognized_provider_detail'; MRN_Updraft_MainWP_Maintenance::dispatch( array(), $post );
	check_diagnostic( 'backup_unverified' === diagnostic_report()['report']['last_maintenance_preflight_refusal']['code'], 'unknown provider code remains generic' );
	$runtime = 'ftpext'; MRN_Updraft_MainWP_Maintenance::dispatch( array(), $post ); $runtime = 'direct';
	check_diagnostic( 'unsupported_runtime' === diagnostic_report()['report']['last_maintenance_preflight_refusal']['code'], 'unsupported runtime remains refused and diagnosable' );
	check_diagnostic( count( $transients ) === 2 && in_array( array( MRN_Updraft_MainWP_Maintenance::DIAGNOSTIC_KEY, 600 ), $writes, true ) && in_array( array( MRN_Updraft_MainWP_Maintenance::CHECKPOINT_KEY, 600 ), $writes, true ), 'two fixed diagnostic keys with ten-minute TTLs' );
	$original = $transients; $bad = $input; $bad['site_url'] = 'https://wrong.invalid';
	MRN_Updraft_MainWP_Maintenance::dispatch( array(), array( 'function' => 'extra_execution', 'mrn_maintenance_request' => json_encode( $bad ) ) );
	check_diagnostic( $original === $transients, 'wrong site cannot overwrite diagnostics' );
	foreach ( array( array( 'function' => 'other', 'mrn_stack_report_action' => 'report' ), array( 'function' => 'extra_execution', 'unrelated' => 'report' ) ) as $other ) {
		check_diagnostic( ! isset( diagnostic_report( $other )['report']['last_maintenance_preflight_refusal'] ), 'unrelated request gains no diagnostic data' );
	}
	$valid = $transients[ MRN_Updraft_MainWP_Maintenance::DIAGNOSTIC_KEY ];
	foreach ( array( $valid + array( 'secret' => 'fixture-private-provider-message' ), array_replace( $valid, array( 'code' => 'unknown' ) ), array_replace( $valid, array( 'recorded_at' => time() + 60 ) ), array_replace( $valid, array( 'recorded_at' => time() - 600 ) ) ) as $tampered ) {
		$transients[ MRN_Updraft_MainWP_Maintenance::DIAGNOSTIC_KEY ] = $tampered;
		check_diagnostic( ! isset( diagnostic_report()['report']['last_maintenance_preflight_refusal'] ), 'tampered/future/expired diagnostic suppressed' );
	}
	echo json_encode( array( 'status' => 'pass', 'checks' => $checks, 'network_requests' => 0, 'production_writes' => 0 ), JSON_PRETTY_PRINT ) . "\n";
} finally {
	foreach ( glob( $fixture . '/wp-admin/includes/*' ) as $file ) { unlink( $file ); }
	rmdir( $fixture . '/wp-admin/includes' ); rmdir( $fixture . '/wp-admin' ); rmdir( $fixture );
}
