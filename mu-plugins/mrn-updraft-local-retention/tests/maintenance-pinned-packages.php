<?php
/** Changing vendor downloads cannot replace an approved archive; no network or WordPress database. */

if ( 'cli' !== PHP_SAPI ) {
	http_response_code( 404 );
	exit;
}

$fixture_root = sys_get_temp_dir() . '/mrn-pin-fixture-' . bin2hex( random_bytes( 8 ) );
mkdir( $fixture_root . '/public', 0700, true );
define( 'ABSPATH', $fixture_root . '/public/' );
define( 'WP_CONTENT_DIR', ABSPATH );
$GLOBALS['pin_salt'] = $fixture_root;
$GLOBALS['downloads'] = 0;
$GLOBALS['scheduled'] = array();
$checks = array();
function wp_salt( $scheme ) { return $GLOBALS['pin_salt']; }
function wp_json_encode( $value ) { return json_encode( $value ); }
function wp_tempnam( $name ) { return tempnam( sys_get_temp_dir(), 'mrn-pin-working-' ); }
function wp_delete_file( $file ) { unlink( $file ); }
function wp_next_scheduled( $hook ) { return $GLOBALS['scheduled'][ $hook ] ?? false; }
function wp_schedule_single_event( $time, $hook ) { $GLOBALS['scheduled'][ $hook ] = $time; return true; }
require dirname( __DIR__ ) . '/includes/class-mrn-updraft-mainwp-maintenance.php';

function pin_check( $condition, $label ) {
	global $checks;
	// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- CLI-only assertion label; no HTTP output.
	if ( ! $condition ) { throw new RuntimeException( 'Pinned package regression: ' . $label ); }
	$checks[] = $label;
}
function pin_root() {
	$method = new ReflectionMethod( MRN_Maintenance_Package_Cache::class, 'root' );
	return $method->invoke( null, true );
}
function pin_fetch() {
	++$GLOBALS['downloads'];
	$file = wp_tempnam( 'fixture.zip' );
	$zip = new ZipArchive();
	$zip->open( $file, ZipArchive::OVERWRITE );
	$zip->addFromString( 'fixture/fixture.php', "<?php\n/* Plugin Name: Fixture\nVersion: 1.1\n*/\n" );
	$zip->setArchiveComment( 'Vendor download ' . $GLOBALS['downloads'] );
	$zip->close();
	return $file;
}
function pin_validate( $file ) {
	$method = new ReflectionMethod( MRN_Updraft_MainWP_Maintenance::class, 'package' );
	return $method->invoke( null, $file, array( 'slug' => 'fixture/fixture.php', 'to' => '1.1' ), 'plugin', array() );
}
function pin_obtain( $identity ) {
	return MRN_Maintenance_Package_Cache::obtain( $identity, 'pin_fetch', 'pin_validate' );
}
function pin_refused( $callback, $code, $label ) {
	try { $callback(); } catch ( RuntimeException $error ) {
		pin_check( $code === $error->getMessage(), $label );
		return;
	}
	// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- CLI-only assertion label; no HTTP output.
	throw new RuntimeException( 'Expected pinned package refusal: ' . $label );
}
function pin_expire() {
	foreach ( glob( pin_root() . '/*' ) as $file ) {
		if ( 'writer.lock' !== basename( $file ) ) { touch( $file, time() - MRN_Maintenance_Package_Cache::TTL - 1 ); }
	}
	MRN_Maintenance_Package_Cache::cleanup();
}

$root = null;
try {
	$identity = array( 'site_id' => 16, 'backup_nonce' => 'fixture-nonce', 'before' => 'fixture-state' );
	$first = pin_obtain( $identity );
	$manifest = pin_validate( $first );
	unlink( $first );
	$second = pin_obtain( $identity );
	pin_check( $manifest === pin_validate( $second ) && 1 === $GLOBALS['downloads'], 'Preview and execution reuse the exact raw ZIP although the vendor returns changing bytes' );
	unlink( $second );
	$root = pin_root();
	pin_check( 0700 === ( fileperms( $root ) & 0777 ) && isset( $GLOBALS['scheduled'][ MRN_Maintenance_Package_Cache::CLEANUP_HOOK ] ), 'Private storage is owner-only and expiry cleanup is scheduled' );
	$archive = glob( $root . '/*.zip' )[0];
	file_put_contents( $archive, 'tampered archive' );
	pin_refused( static function () use ( $identity ) { pin_obtain( $identity ); }, 'package_invalid', 'Tampering is refused without silently downloading replacement bytes' );
	pin_check( 1 === $GLOBALS['downloads'], 'A failed cache verification never falls back to the vendor' );
	pin_expire();
	$third = pin_obtain( $identity );
	pin_check( $manifest !== pin_validate( $third ) && 2 === $GLOBALS['downloads'], 'Only an expired pin permits a new artifact and therefore a new approval checksum' );
	unlink( $third );
	$other = pin_obtain( array_merge( $identity, array( 'site_id' => 17 ) ) );
	unlink( $other );
	$other = pin_obtain( array_merge( $identity, array( 'backup_nonce' => 'different-nonce' ) ) );
	unlink( $other );
	$other = pin_obtain( array_merge( $identity, array( 'before' => 'different-state' ) ) );
	unlink( $other );
	pin_check( 5 === $GLOBALS['downloads'], 'Site, backup nonce and installed-state identities cannot reuse one another\'s pin' );

	$archive = glob( $root . '/*.zip' )[0];
	$record = substr( $archive, 0, -4 ) . '.json';
	unlink( $archive );
	$missing_key = basename( $archive, '.zip' );
	$matching_identity = null;
	foreach ( array( $identity, array_merge( $identity, array( 'site_id' => 17 ) ), array_merge( $identity, array( 'backup_nonce' => 'different-nonce' ) ), array_merge( $identity, array( 'before' => 'different-state' ) ) ) as $candidate ) {
		if ( hash( 'sha256', wp_json_encode( $candidate ) ) === $missing_key ) { $matching_identity = $candidate; }
	}
	pin_refused( static function () use ( $matching_identity ) { pin_obtain( $matching_identity ); }, 'package_changed', 'A missing unexpired archive is refused instead of regenerated' );
	pin_expire();

	$lock = fopen( $root . '/writer.lock', 'c+b' );
	flock( $lock, LOCK_EX );
	pin_refused( static function () use ( $identity ) { pin_obtain( $identity ); }, 'operation_locked', 'A concurrent writer is neither waited on nor stolen' );
	flock( $lock, LOCK_UN ); fclose( $lock );

	$outside = $fixture_root . '/outside';
	file_put_contents( $outside, 'must remain unchanged' );
	$linked = $root . '/' . str_repeat( 'a', 64 ) . '.zip';
	symlink( $outside, $linked );
	pin_refused( static function () use ( $identity ) { pin_obtain( $identity ); }, 'download_unavailable', 'Symlinked cache entries are refused' );
	MRN_Maintenance_Package_Cache::cleanup();
	pin_check( 'must remain unchanged' === file_get_contents( $outside ), 'Cleanup never follows an unsafe link' );
	unlink( $linked );
	$_SERVER['DOCUMENT_ROOT'] = sys_get_temp_dir();
	pin_refused( static function () use ( $identity ) { pin_obtain( $identity ); }, 'download_unavailable', 'A system temp directory inside the web root is refused' );
	unset( $_SERVER['DOCUMENT_ROOT'] );
	chmod( $root, 0755 );
	pin_refused( static function () use ( $identity ) { pin_obtain( $identity ); }, 'download_unavailable', 'A cache directory with broader permissions is refused' );
	chmod( $root, 0700 );

	for ( $i = 0; $i < MRN_Maintenance_Package_Cache::MAX_ENTRIES; ++$i ) {
		$path = $root . '/' . hash( 'sha256', 'capacity-' . $i ) . '.zip';
		file_put_contents( $path, 'capacity fixture' ); chmod( $path, 0600 );
	}
	$before_downloads = $GLOBALS['downloads'];
	pin_refused( static function () use ( $identity ) { pin_obtain( $identity ); }, 'package_limit', 'A full cache fails closed without downloading or evicting live pins' );
	pin_check( $before_downloads === $GLOBALS['downloads'], 'Capacity refusal performs no vendor request' );
	pin_expire();
	pin_check( array() === glob( $root . '/*.zip' ) && array() === glob( $root . '/*.json' ), 'Expired temporary archives and manifests are removed' );

	$archive = $root . '/' . str_repeat( 'b', 64 ) . '.zip';
	$stream = fopen( $archive, 'wb' ); ftruncate( $stream, MRN_Maintenance_Package_Cache::MAX_BYTES ); fclose( $stream ); chmod( $archive, 0600 );
	pin_refused( static function () use ( $identity ) { pin_obtain( $identity ); }, 'package_limit', 'Total pinned archive bytes are bounded' );
	pin_expire();
	$consumed = pin_obtain( $identity );
	unlink( $consumed );
	MRN_Maintenance_Package_Cache::release( $identity );
	pin_check( array() === glob( $root . '/*.zip' ) && array() === glob( $root . '/*.json' ), 'Consumed operations release their pinned archive and manifest immediately' );
	echo json_encode( array( 'status' => 'pass', 'checks' => $checks, 'network_requests' => 0, 'production_writes' => 0 ), JSON_PRETTY_PRINT ) . "\n";
} finally {
	if ( is_string( $root ) && is_dir( $root ) ) {
		chmod( $root, 0700 );
		foreach ( glob( $root . '/*' ) as $file ) { unlink( $file ); }
		rmdir( $root );
	}
	foreach ( glob( $fixture_root . '/*' ) as $file ) { is_dir( $file ) ? rmdir( $file ) : unlink( $file ); }
	rmdir( $fixture_root );
}
