<?php
/** Closed download refusals; no real network, credentials or WordPress database. */

define( 'ABSPATH', __DIR__ . '/' );
class WP_Error {
	private $code;
	private $message;
	public function __construct( $code, $message = '' ) { $this->code = $code; $this->message = $message; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function esc_html( $value ) { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
function wp_tempnam( $name ) { return tempnam( sys_get_temp_dir(), 'mrn-download-' ); }
function wp_delete_file( $name ) { unlink( $name ); }
function wp_remote_retrieve_response_code( $response ) { return $response['response']['code']; }
function wp_safe_remote_get( $url, $args ) {
	if ( 'https://downloads.wordpress.org/plugin/fixture.1.1.zip' !== $url || true !== $args['stream'] || true !== $args['sslverify'] || 30 !== $args['timeout'] || 3 !== $args['redirection'] || 52428801 !== $args['limit_response_size'] ) {
		throw new RuntimeException( 'Download controls changed.' );
	}
	$GLOBALS['file'] = $args['filename'];
	$stream = fopen( $args['filename'], 'wb' ); ftruncate( $stream, $GLOBALS['size'] ); fclose( $stream );
	return $GLOBALS['response'];
}
require dirname( __DIR__ ) . '/includes/class-mrn-updraft-mainwp-maintenance.php';
$method = new ReflectionMethod( MRN_Updraft_MainWP_Maintenance::class, 'download' );
$checks = array();
function download_case( $response, $size, $expected ) {
	global $method, $checks;
	$GLOBALS['response'] = $response; $GLOBALS['size'] = $size;
	try {
		$file = $method->invoke( null, 'https://downloads.wordpress.org/plugin/fixture.1.1.zip' );
		if ( 'pass' !== $expected || ! is_file( $file ) || filesize( $file ) !== $size ) { throw new RuntimeException( 'Unexpected download acceptance.' ); }
		wp_delete_file( $file );
	} catch ( RuntimeException $error ) {
		if ( $error->getMessage() !== $expected || is_file( $GLOBALS['file'] ) || ! in_array( $expected, MRN_Updraft_MainWP_Maintenance::DIAGNOSTIC_CODES, true ) ) { throw new RuntimeException( 'Download refusal or cleanup regression.' ); }
	}
	$checks[] = $expected;
}
download_case( new WP_Error( 'http_request_not_executed', 'fixture-private-provider-detail' ), 0, 'download_http_blocked' );
foreach ( array( 6 => 'dns_failed', 7 => 'connection_failed', 18 => 'incomplete', 23 => 'stream_failed', 28 => 'timeout', 35 => 'tls_failed', 52 => 'empty_reply', 60 => 'certificate_failed' ) as $number => $suffix ) {
	download_case( new WP_Error( 'http_request_failed', 'cURL error ' . $number . ': fixture-private-provider-detail' ), 7, 'download_' . $suffix );
}
foreach ( array( 'fixture-private-provider-detail', 'prefix cURL error 60: fixture-private-provider-detail', 'cURL error 123: fixture-private-provider-detail' ) as $message ) {
	download_case( new WP_Error( 'http_request_failed', $message ), 0, 'download_transport_failed' );
}
download_case( new WP_Error( 'unknown', 'cURL error 60: fixture-private-provider-detail' ), 0, 'download_transport_failed' );
foreach ( array( 401 => 'denied', 403 => 'denied', 407 => 'denied', 404 => 'unavailable', 410 => 'unavailable', 429 => 'limited', 500 => 'server_error', 503 => 'server_error', 302 => 'status', 0 => 'status' ) as $status => $suffix ) {
	download_case( array( 'response' => array( 'code' => $status ) ), 7, 'download_http_' . $suffix );
}
download_case( array( 'response' => array( 'code' => '200' ) ), 7, 'download_http_status' );
download_case( array( 'response' => array( 'code' => 200 ) ), 0, 'download_empty' );
download_case( array( 'response' => array( 'code' => 200 ) ), 52428801, 'download_size_limit' );
download_case( array( 'response' => array( 'code' => 200 ) ), 7, 'pass' );
download_case( array( 'response' => array( 'code' => 200 ) ), 52428800, 'pass' );
echo json_encode( array( 'status' => 'pass', 'checks' => $checks, 'network_requests' => 0, 'production_writes' => 0, 'private_messages_retained' => false ), JSON_PRETTY_PRINT ) . "\n";
