<?php
/** Offline S3 transport contract; optional native Updraft S3.php as argv[1]. */

define( 'ABSPATH', __DIR__ . '/' );

if ( isset( $argv[1] ) ) {
	require $argv[1];
} else {
	/** Minimal public native API for CI without a licensed plugin dependency. */
	class UpdraftPlus_S3 {
		public $useSSL = false;
		public $useSSLValidation = false;
		public $sslCACert = false;
		public $endpoint = 's3.amazonaws.com';
		public $region = 'us-east-1';
		public function __construct() {}
		public function setSSL( $ssl, $verify = true ) { $this->useSSL = $ssl; $this->useSSLValidation = $verify; }
		public function setSSLAuth( $cert = null, $key = null, $ca = null ) { $this->sslCACert = $ca; }
		public function setRegion( $region ) { $this->region = $region; }
		public function getRegion() { return $this->region; }
		public function setEndpoint( $host ) { $this->endpoint = $host; }
		public function setExceptions( $enabled = true ) {}
		public function setSignatureVersion( $version = 'v2' ) {}
	}
}

class WP_Error {
	public $code;
	public function __construct( $code, $message = '' ) { $this->code = $code; }
}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function mrn_updraft_backup_policy_get_option( $name, $default = false ) { return $default; }

/** Native client subclass: network reads are replaced, mutations always throw. */
class MRN_Regional_S3 extends UpdraftPlus_S3 {
	public $location = 'us-east-2';
	public $list = array();
	public $events = array();
	public $writes = 0;
	public function getBucketLocation( $bucket ) {
		$this->events[] = 'location';
		if ( ! $this->useSSL || ! $this->useSSLValidation || 'system' !== $this->sslCACert ) {
			throw new RuntimeException( 'TLS was not initialized.' );
		}
		return $this->location;
	}
	public function getBucket( $bucket, $prefix = null, $marker = null, $max_keys = null, $delimiter = null, $common = false ) {
		$this->events[] = 'list';
		$region = in_array( $this->location, array( null, '', 'US' ), true ) ? 'us-east-1' : ( 'EU' === $this->location ? 'eu-west-1' : $this->location );
		$domain = 0 === strpos( $region, 'cn-' ) ? 'amazonaws.com.cn' : 'amazonaws.com';
		if ( $region !== $this->getRegion() || $this->endpoint !== 's3.' . $region . '.' . $domain || ! $this->useSSLValidation || 'system' !== $this->sslCACert || 'backups' !== $bucket || 'sites/example-com/backup_2026_abcdef123456-' !== $prefix || 1000 !== $max_keys ) {
			throw new RuntimeException( 'Regional scoped listing contract failed.' );
		}
		return $this->list;
	}
	public function putBucket( $bucket, $acl = 'private', $location = false ) { ++$this->writes; throw new RuntimeException( 'Mutation forbidden.' ); }
}

/** No secrets; the native adapter returns a deterministic in-memory client. */
class MRN_Regional_Storage {
	public $client;
	public function getS3( ...$arguments ) { return $this->client; }
}

/** Compat's public endpoint setter also requires its region argument. */
class MRN_Regional_Compat extends MRN_Regional_S3 {
	public function setEndpoint( $host, $region = null ) {
		if ( null === $region ) { throw new RuntimeException( 'Compat region required.' ); }
		$this->endpoint = $host;
		$this->setRegion( $region );
	}
}
class_alias( MRN_Regional_Compat::class, 'UpdraftPlus_S3_Compat' );

require dirname( __DIR__ ) . '/includes/class-mrn-updraft-mainwp-policy.php';
$method = new ReflectionMethod( MRN_Updraft_MainWP_Policy::class, 'remote_files' );
if ( PHP_VERSION_ID < 80100 ) { $method->setAccessible( true ); }
$checks = array();
function check_s3( $ok, $label ) {
	global $checks;
	if ( ! $ok ) { throw new RuntimeException( 'S3 verification regression.' ); }
	$checks[] = $label;
}
function inspect_s3( $client ) {
	global $method;
	$storage = new MRN_Regional_Storage(); $storage->client = $client;
	return $method->invoke( null, array( 'object' => $storage, 'settings' => array( 'path' => 'backups/sites/example-com', 'accesskey' => 'fixture-key', 'secretkey' => 'fixture-secret' ) ), 'abcdef123456', 'backup_2026_abcdef123456-db.gz' );
}

foreach ( array( 'us-east-2', 'us-west-2', 'US', null, 'EU', 'cn-north-1' ) as $location ) {
	$client = new MRN_Regional_S3( 'fixture-key', 'fixture-secret', true, false );
	$client->location = $location;
	$client->list = array( array( 'name' => 'sites/example-com/backup_2026_abcdef123456-db.gz', 'size' => 42 ) );
	$result = inspect_s3( $client );
	check_s3( array( 'backup_2026_abcdef123456-db.gz' ) === $result && array( 'location', 'list' ) === $client->events && 0 === $client->writes, 'verified regional listing ' . ( $location ?? 'default' ) );
}
foreach ( array( false, 'https://attacker.invalid/', 'us-east-2.attacker.invalid', array( 'region' => 'us-east-2' ) ) as $location ) {
	$client = new MRN_Regional_S3(); $client->location = $location;
	check_s3( is_wp_error( inspect_s3( $client ) ) && ! in_array( 'list', $client->events, true ) && 0 === $client->writes, 'invalid/unavailable region refused without listing or mutation' );
}
$client = new MRN_Regional_S3(); $client->list = false;
check_s3( is_wp_error( inspect_s3( $client ) ), 'listing failure stays unverified' );
$client = new MRN_Regional_S3();
check_s3( array() === inspect_s3( $client ), 'successful empty listing remains distinguishable' );
$client = new MRN_Regional_S3(); $client->list = array( array( 'name' => 'sites/other-com/backup_2026_abcdef123456-db.gz', 'size' => 42 ) );
check_s3( is_wp_error( inspect_s3( $client ) ), 'unrelated prefix refused' );
$client = new MRN_Regional_Compat();
check_s3( array() === inspect_s3( $client ) && array( 'location', 'list' ) === $client->events, 'Compat public endpoint contract preserves TLS and region' );
check_s3( is_wp_error( inspect_s3( new stdClass() ) ), 'unknown client refused' );
$client = new MRN_Regional_S3(); $client->list = array_fill( 0, 1000, array( 'name' => 'sites/example-com/backup_2026_abcdef123456-db.gz', 'size' => 1 ) );
check_s3( is_wp_error( inspect_s3( $client ) ), 'truncated inventory refused' );
echo json_encode( array( 'status' => 'pass', 'checks' => $checks, 'native_client' => isset( $argv[1] ), 'network_requests' => 0, 'production_writes' => 0 ), JSON_PRETTY_PRINT ) . "\n";
