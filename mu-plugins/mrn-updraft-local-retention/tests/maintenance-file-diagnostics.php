<?php
/** Real bounded filesystem observations; no network, site or backup credentials. */

$fixture = sys_get_temp_dir() . '/mrn-admin-files-' . bin2hex( random_bytes( 8 ) );
mkdir( $fixture . '/wp-admin/includes', 0700, true );
define( 'ABSPATH', $fixture . '/' );
$transients = array(); $writes = array(); $checks = array();
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
function esc_html( $value ) { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
function get_transient( $key ) { return $GLOBALS['transients'][ $key ] ?? false; }
function set_transient( $key, $value, $ttl ) { $GLOBALS['transients'][ $key ] = $value; $GLOBALS['writes'][] = array( $key, $ttl ); return true; }
require dirname( __DIR__ ) . '/includes/class-mrn-updraft-mainwp-maintenance.php';
$class = MRN_Updraft_MainWP_Maintenance::class;
function file_check( $ok, $label ) {
	if ( ! $ok ) { throw new RuntimeException( 'File diagnostic regression: ' . esc_html( $label ) ); }
	$GLOBALS['checks'][] = $label;
}
function file_private( $method, ...$args ) { return ( new ReflectionMethod( $GLOBALS['class'], $method ) )->invoke( null, ...$args ); }
function file_recording( $enabled ) {
	( new ReflectionProperty( $GLOBALS['class'], 'record_checkpoint' ) )->setValue( null, $enabled );
	( new ReflectionProperty( $GLOBALS['class'], 'checkpoint' ) )->setValue( null, array( 'site_id' => 17, 'request_sha256' => hash( 'sha256', 'fixture-request-only-no-receipt' ) ) );
}
function file_observe() {
	$files = null;
	$hash = ( new ReflectionMethod( $GLOBALS['class'], 'tree' ) )->invokeArgs( null, array( ABSPATH . 'wp-admin', &$files ) );
	file_private( 'observe_admin_files', $files, $hash );
	return $hash;
}
try {
	file_put_contents( ABSPATH . 'wp-admin/admin.php', '<?php /* original fixture */' );
	file_put_contents( ABSPATH . 'wp-admin/includes/file.php', '<?php /* nested fixture */' );
	file_recording( false ); $first_hash = file_observe();
	file_check( array() === $writes && null === MRN_Updraft_MainWP_Maintenance::file_diagnostics(), 'ordinary tree reads and execute paths do not create diagnostic observations' );
	file_recording( true ); $recorded_hash = file_observe();
	$expected = array( 'admin.php' => hash( 'sha256', '<?php /* original fixture */' ), 'includes/file.php' => hash( 'sha256', '<?php /* nested fixture */' ) );
	file_check( $first_hash === $recorded_hash && $recorded_hash === hash( 'sha256', json_encode( $expected, JSON_UNESCAPED_SLASHES ) ), 'original complete tree digest is identical with and without observation' );
	$baseline = MRN_Updraft_MainWP_Maintenance::file_diagnostics();
	file_check( 'core:admin' === $baseline['component'] && 17 === $baseline['site_id'] && null === $baseline['last_change'], 'first observation is a baseline, not an invented change' );
	touch( ABSPATH . 'wp-admin/admin.php', time() - 500 ); clearstatcache();
	file_check( file_observe() === $first_hash && null === MRN_Updraft_MainWP_Maintenance::file_diagnostics()['last_change'], 'modification time alone never changes the guarded fingerprint' );
	file_put_contents( ABSPATH . 'wp-admin/error_log', 'fixture-log-body-must-never-escape' );
	file_put_contents( ABSPATH . 'wp-admin/admin.php', '<?php /* changed fixture, unchanged version */' );
	unlink( ABSPATH . 'wp-admin/includes/file.php' );
	file_put_contents( ABSPATH . 'wp-admin/.htaccess', '# fixture rules' );
	$changed_hash = file_observe(); $report = MRN_Updraft_MainWP_Maintenance::file_diagnostics();
	file_check( $changed_hash !== $first_hash && 4 === $report['last_change']['changed_count'], 'same-version code, new log, rule file and removed code all remain material changes' );
	$changed = array_column( $report['last_change']['changes'], null, 'path' );
	file_check( array_keys( $changed ) === array( '.htaccess', 'admin.php', 'error_log', 'includes/file.php' ), 'reports exact sorted core-relative filenames including hidden rule files' );
	file_check( null === $changed['error_log']['previous_sha256'] && hash( 'sha256', 'fixture-log-body-must-never-escape' ) === $changed['error_log']['current_sha256'] && null === $changed['includes/file.php']['current_sha256'], 'addition, content replacement and deletion have precise hashes' );
	$before_read = $writes; $encoded = json_encode( $report );
	file_check( false === strpos( $encoded, 'fixture-log-body-must-never-escape' ) && false === strpos( $encoded, $fixture ) && ! isset( $report['files'] ) && false === strpos( $encoded, 'backup_nonce' ), 'public metadata contains neither file bodies, physical paths, complete manifests nor receipts' );
	file_check( $report === MRN_Updraft_MainWP_Maintenance::file_diagnostics() && $writes === $before_read, 'report read performs no scan or diagnostic write' );
	file_observe();
	file_check( $report['last_change'] === MRN_Updraft_MainWP_Maintenance::file_diagnostics()['last_change'], 'second stable snapshot preserves the observed preview-to-confirm change' );
	file_put_contents( ABSPATH . 'wp-admin/error_log', '\nmore fixture log', FILE_APPEND ); file_observe();
	$report = MRN_Updraft_MainWP_Maintenance::file_diagnostics();
	file_check( 1 === $report['last_change']['changed_count'] && 'error_log' === $report['last_change']['changes'][0]['path'], 'a growing log is identified precisely and is never excluded from snapshot validation' );
	$key = MRN_Updraft_MainWP_Maintenance::FILE_DIAGNOSTIC_KEY; $valid = $transients[ $key ];
	$invalid = array();
	$bad = $valid; $bad['unexpected'] = 'private'; $invalid[] = $bad;
	$bad = $valid; $bad['recorded_at'] = time() + 60; $invalid[] = $bad;
	$bad = $valid; $bad['recorded_at'] = time() - 600; $invalid[] = $bad;
	$bad = $valid; $bad['files']['admin.php'] = str_repeat( '0', 64 ); $invalid[] = $bad;
	$bad = $valid; $bad['last_change']['changes'][0]['path'] = '../wp-config.php'; $invalid[] = $bad;
	$bad = $valid; $bad['last_change']['changes'][0]['current_sha256'] = str_repeat( '0', 64 ); $invalid[] = $bad;
	$bad = $valid; $bad['last_change']['truncated'] = true; $invalid[] = $bad;
	$bad = $valid; $bad['files']['/private/path'] = str_repeat( '0', 64 ); $invalid[] = $bad;
	foreach ( $invalid as $bad ) {
		$transients[ $key ] = $bad;
		file_check( null === MRN_Updraft_MainWP_Maintenance::file_diagnostics(), 'tampered, future, expired, inconsistent or unsafe observations are withheld' );
	}
	$transients[ $key ] = $valid;
	foreach ( array( '/tmp/private', '../wp-config.php', 'a//b', './admin.php', 'a/../b', "a\nprivate", 'C:/private' ) as $path ) {
		file_check( ! file_private( 'diagnostic_path', $path ), 'absolute, traversal, control and drive names refused' );
	}
	for ( $i = 0; $i < 70; ++$i ) { file_put_contents( ABSPATH . 'wp-admin/item-' . $i . '.php', '<?php /* bounded fixture */' ); }
	$all_hash = file_observe(); $report = MRN_Updraft_MainWP_Maintenance::file_diagnostics();
	file_check( 70 === $report['last_change']['changed_count'] && true === $report['last_change']['truncated'] && 64 === count( $report['last_change']['changes'] ), 'large changes report exact total and explicit bounded truncation' );
	file_put_contents( ABSPATH . 'wp-admin/item-9.php', '<?php /* material outside earlier returned prefix */' );
	file_check( file_observe() !== $all_hash, 'bounding diagnostic output never omits files from the security fingerprint' );
	$stored = $transients; $before_write = $writes;
	$large = array(); for ( $i = 0; $i < 3000; ++$i ) { $large['long-fixture-path-' . $i . '.php'] = str_repeat( 'a', 64 ); }
	file_private( 'observe_admin_files', $large, MRN_Updraft_MainWP_Maintenance::digest( $large ) );
	file_check( $stored === $transients && $before_write === $writes, 'oversized diagnostic manifest is not retained or returned' );
	file_recording( false ); file_observe();
	file_check( $stored === $transients && $before_write === $writes, 'execute/read operations cannot overwrite diagnostic history' );
	file_check( array_unique( array_column( $writes, 0 ) ) === array( $key ) && array_unique( array_column( $writes, 1 ) ) === array( 600 ), 'one fixed bounded transient with ten-minute retention only' );
	echo json_encode( array( 'status' => 'pass', 'checks' => $checks, 'network_requests' => 0, 'production_writes' => 0 ), JSON_PRETTY_PRINT ) . "\n";
} finally {
	$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $fixture, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
	foreach ( $iterator as $item ) { $item->isDir() ? rmdir( $item->getPathname() ) : unlink( $item->getPathname() ); }
	rmdir( $fixture );
}
