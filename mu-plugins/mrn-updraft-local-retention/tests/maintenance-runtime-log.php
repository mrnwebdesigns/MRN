<?php
/** Real filesystem regression for the owner-approved single native runtime log. */

$fixture = sys_get_temp_dir() . '/mrn-runtime-log-' . bin2hex( random_bytes( 8 ) );
mkdir( $fixture . '/wp-admin/includes', 0700, true );
define( 'ABSPATH', realpath( $fixture ) . '/' );
$transients = array(); $writes = array(); $checks = array();
$original_error_log = ini_get( 'error_log' ); $original_log_errors = ini_get( 'log_errors' );
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
function esc_html( $value ) { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
function get_transient( $key ) { return $GLOBALS['transients'][ $key ] ?? false; }
function set_transient( $key, $value, $ttl ) { $GLOBALS['transients'][ $key ] = $value; $GLOBALS['writes'][] = array( $key, $ttl ); return true; }
require dirname( __DIR__ ) . '/includes/class-mrn-updraft-mainwp-maintenance.php';
$class = MRN_Updraft_MainWP_Maintenance::class;
function log_check( $ok, $label ) {
	if ( ! $ok ) { throw new RuntimeException( 'Runtime log regression: ' . esc_html( $label ) ); }
	$GLOBALS['checks'][] = $label;
}
function log_private( $method, ...$args ) { return ( new ReflectionMethod( $GLOBALS['class'], $method ) )->invoke( null, ...$args ); }
function log_config( $path, $enabled = '1' ) {
	// phpcs:disable WordPress.PHP.IniSet -- Disposable CLI fixture only; restored in finally.
	ini_set( 'error_log', $path ); ini_set( 'log_errors', $enabled );
	// phpcs:enable
}
function log_refuses( $label ) {
	try { log_private( 'admin_tree' ); } catch ( RuntimeException $error ) {
		log_check( 'tree_unsafe' === $error->getMessage(), $label ); return;
	}
	throw new RuntimeException( 'Unsafe log unexpectedly accepted.' );
}
try {
	file_put_contents( ABSPATH . 'wp-admin/admin.php', '<?php /* guarded fixture */' );
	file_put_contents( ABSPATH . 'wp-admin/includes/file.php', '<?php /* nested guarded fixture */' );
	$path = ABSPATH . 'wp-admin/php_errorlog';
	log_config( 'php_errorlog' );
	$absent = log_private( 'admin_tree' );
	log_check( 'log_absent' === MRN_Updraft_MainWP_Maintenance::runtime_log_policy()['reason'], 'configured missing log is explicitly reported' );
	file_put_contents( $path, 'fixture-private-log-body' );
	$first = log_private( 'admin_tree' ); $full = log_private( 'tree', ABSPATH . 'wp-admin' );
	log_check( $first !== $absent && $first !== $full, 'log creation remains material and policy digest differs from full diagnostic digest' );
	( new ReflectionProperty( $class, 'record_checkpoint' ) )->setValue( null, true );
	( new ReflectionProperty( $class, 'checkpoint' ) )->setValue( null, array( 'site_id' => 17, 'request_sha256' => hash( 'sha256', 'fixture-request' ) ) );
	log_private( 'admin_tree' );
	file_put_contents( $path, '\nadditional fixture log', FILE_APPEND );
	$grown = log_private( 'admin_tree' ); $full_grown = log_private( 'tree', ABSPATH . 'wp-admin' );
	log_check( $full !== $full_grown, 'reproduces original all-file false stale state from log growth alone' );
	log_check( $first === $grown, 'configured native log growth preserves exact code-protection digest' );
	$report = MRN_Updraft_MainWP_Maintenance::file_diagnostics();
	log_check( $report['current_sha256'] === $full_grown && 1 === $report['last_change']['changed_count'] && 'php_errorlog' === $report['last_change']['changes'][0]['path'], 'full diagnostic digest and exact changing log remain independently observable' );
	$before = $writes; $policy = MRN_Updraft_MainWP_Maintenance::runtime_log_policy();
	log_check( true === $policy['applied'] && true === $policy['presence_bound'] && true === $policy['full_hash_diagnostics'] && $writes === $before, 'inspection explicitly reports exception and performs no writes' );
	$encoded = json_encode( array( $report, $policy ) );
	log_check( false === strpos( $encoded, 'fixture-private-log-body' ) && false === strpos( $encoded, ABSPATH ) && ! isset( $policy['identity'] ) && ! isset( $policy['configuration_sha256'] ), 'inspection exposes no contents, absolute path, identity or configuration value' );
	file_put_contents( ABSPATH . 'wp-admin/admin.php', '\n// real code change', FILE_APPEND );
	log_check( log_private( 'admin_tree' ) !== $grown, 'actual PHP content remains material' );
	$original = file_get_contents( ABSPATH . 'wp-admin/includes/file.php' ); $before = log_private( 'admin_tree' );
	file_put_contents( ABSPATH . 'wp-admin/includes/file.php', '\n// nested real code change', FILE_APPEND );
	log_check( $before !== log_private( 'admin_tree' ), 'nested code remains material' );
	file_put_contents( ABSPATH . 'wp-admin/includes/file.php', $original );
	foreach ( array( 'error_log', 'includes/php_errorlog', '.htaccess' ) as $name ) {
		file_put_contents( ABSPATH . 'wp-admin/' . $name, 'fixture' ); $before = log_private( 'admin_tree' );
		file_put_contents( ABSPATH . 'wp-admin/' . $name, 'change', FILE_APPEND );
		log_check( $before !== log_private( 'admin_tree' ), 'other log, nested same name and hidden rule contents remain material: ' . $name );
	}
	$before = log_private( 'admin_tree' ); unlink( $path );
	log_check( $before !== log_private( 'admin_tree' ), 'log removal remains material' );
	file_put_contents( $path, 'fixture' );
	foreach ( array( '', 'syslog', 'error_log', './php_errorlog', 'includes/php_errorlog', ABSPATH . 'php_errorlog' ) as $setting ) {
		log_config( $setting ); $before = log_private( 'admin_tree' ); file_put_contents( $path, 'more', FILE_APPEND );
		log_check( $before !== log_private( 'admin_tree' ) && 'native_configuration_unproven' === MRN_Updraft_MainWP_Maintenance::runtime_log_policy()['reason'], 'unproven or aliased configuration keeps literal hashing' );
	}
	log_config( 'php_errorlog', '0' ); $before = log_private( 'admin_tree' ); file_put_contents( $path, 'more', FILE_APPEND );
	log_check( $before !== log_private( 'admin_tree' ), 'disabled native logging keeps literal hashing' );
	log_config( $path ); $absolute = log_private( 'admin_tree' ); file_put_contents( $path, 'more', FILE_APPEND );
	log_check( $absolute === log_private( 'admin_tree' ), 'exact absolute native log configuration is supported' );
	log_config( 'php_errorlog' );
	log_check( $absolute !== log_private( 'admin_tree' ), 'qualifying configuration changes remain material' );
	link( $path, ABSPATH . 'hardlink-alias' ); log_refuses( 'hardlinked native log refuses instead of being excluded' ); unlink( ABSPATH . 'hardlink-alias' );
	unlink( $path ); symlink( ABSPATH . 'wp-admin/admin.php', $path ); log_refuses( 'symlinked native log refuses' ); unlink( $path );
	mkdir( $path ); log_refuses( 'directory at log location refuses' ); rmdir( $path );
	file_put_contents( $path, 'fixture' ); chmod( $path, 0000 );
	if ( ! is_readable( $path ) ) { log_refuses( 'unreadable log refuses' ); } else { log_check( 0 === posix_geteuid(), 'unreadability test cannot be simulated for root' ); }
	chmod( $path, 0600 );
	$handle = fopen( $path, 'c' ); ftruncate( $handle, MRN_Updraft_MainWP_Maintenance::MAX_TREE + 1 ); fclose( $handle ); clearstatcache();
	try { log_private( 'admin_tree' ); throw new LogicException( 'Unbounded log accepted.' ); } catch ( RuntimeException $error ) { log_check( 'tree_limit' === $error->getMessage(), 'excluded log still obeys full aggregate byte limit' ); }
	file_put_contents( $path, 'fixture' );
	$other = ABSPATH . 'other'; mkdir( $other ); file_put_contents( $other . '/php_errorlog', 'fixture' ); $before = log_private( 'tree', $other ); file_put_contents( $other . '/php_errorlog', 'more', FILE_APPEND );
	log_check( $before !== log_private( 'tree', $other ), 'ordinary plugin/theme/includes tree never applies this exception' );
	echo json_encode( array( 'status' => 'pass', 'checks' => $checks, 'network_requests' => 0, 'production_writes' => 0 ), JSON_PRETTY_PRINT ) . "\n";
} finally {
	log_config( $original_error_log, $original_log_errors );
	$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $fixture, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
	foreach ( $iterator as $item ) { $item->isDir() && ! $item->isLink() ? rmdir( $item->getPathname() ) : unlink( $item->getPathname() ); }
	rmdir( $fixture );
}
