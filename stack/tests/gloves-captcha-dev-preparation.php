<?php
/** Run the one-shot preparation only inside marked disposable filesystem state. */
$root = sys_get_temp_dir() . '/mrn-gloves-preparation-' . bin2hex( random_bytes( 8 ) );
mkdir( $root . '/public/wp-content/mu-plugins/mrn-updraft-local-retention', 0700, true );
mkdir( $root . '/backups', 0700, true );
define( 'ABSPATH', $root . '/public/' );
define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
define( 'WP_CLI', true );
$GLOBALS['fixture_home'] = 'https://gloves.mrndev.io';
$GLOBALS['fixture_admin'] = true;
$GLOBALS['fixture_options'] = array( 'updraft_service' => array( 's3' ), 'updraft_s3' => array( 'version' => 1, 'settings' => array( 'old-instance' => array( 'instance_enabled' => 1, 'path' => 'mrn-webdesigns-backups/sites/gloves.mrndev.io', 'accesskey' => 'fixture-access', 'secretkey' => 'fixture-secret' ) ) ) );
function current_user_can( $capability ) { return $GLOBALS['fixture_admin']; }
function home_url() { return $GLOBALS['fixture_home']; }
function untrailingslashit( $text ) { return rtrim( $text, '/' ); }
function trailingslashit( $text ) { return rtrim( $text, '/' ) . '/'; }
function wp_json_encode( $value ) { return json_encode( $value ); }
function get_option( $name, $default = false ) { return $GLOBALS['fixture_options'][ $name ] ?? $default; }
function update_option( $name, $value, $autoload = null ) { $GLOBALS['fixture_options'][ $name ] = $value; }
function wp_generate_uuid4() { return bin2hex( random_bytes( 16 ) ); }
function wp_mkdir_p( $path ) { return is_dir( $path ) || mkdir( $path, 0700, true ); }
class UpdraftPlus_Backup_History {
	public static function get_history() { return $GLOBALS['fixture_history']; }
}
$updraftplus = new class( $root ) {
	private $root;
	public function __construct( $root ) { $this->root = $root; }
	public function backups_dir_location() { return $this->root . '/backups'; }
};
$nonce = 'abcdef012345';
$GLOBALS['fixture_history'] = array( time() => array( 'nonce' => $nonce, 'label' => 'pre-gloves-captcha-dev-20261006', 'db' => 'fixture-db.gz', 'db-size' => 123, 'service' => array( 's3' ), 'checksums' => array( 'sha256' => array( 'db0' => str_repeat( 'a', 64 ) ) ) ) );
$log = $root . '/backups/log.' . $nonce . '.txt';
file_put_contents( $log, 'Recording as successfully uploaded. The backup succeeded and is now complete.' );
$old_path = WP_CONTENT_DIR . '/mu-plugins/mrn-updraft-local-retention/mrn-updraft-local-retention.php';
file_put_contents( $old_path, '<?php /* Version: 0.5.1 */' );
$new_code = '<?php /* Version: 0.6.1 */';
// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize,WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Synthetic local option digest and public artifact transport fixture; no deserialize or eval.
$base = array( 'nonce' => $nonce, 'prior_s3_sha256' => hash( 'sha256', serialize( get_option( 'updraft_s3' ) ) ), 'prior_policy_sha256' => hash_file( 'sha256', $old_path ), 'policy_base64' => base64_encode( $new_code ), 'policy_sha256' => hash( 'sha256', $new_code ) );
$script = dirname( __DIR__, 2 ) . '/scripts/integrations/20261006-gloves-captcha-dev.php';
$count = 0;
function expect_rejection( $script, $input ) {
	global $count;
	$args = array( json_encode( $input ) );
	try { require __DIR__ . '/../../scripts/integrations/20261006-gloves-captcha-dev.php'; } catch ( RuntimeException $error ) { ++$count; return; }
	throw new RuntimeException( 'Unsafe preparation was permitted.' );
}
try {
	$GLOBALS['fixture_admin'] = false;
	expect_rejection( $script, $base );
	$GLOBALS['fixture_admin'] = true;
	$GLOBALS['fixture_home'] = 'https://gloves-online.com';
	expect_rejection( $script, $base );
	$GLOBALS['fixture_home'] = 'https://gloves.mrndev.io';
	expect_rejection( $script, array_merge( $base, array( 'nonce' => '000000000000' ) ) );
	expect_rejection( $script, array_merge( $base, array( 'prior_s3_sha256' => str_repeat( 'b', 64 ) ) ) );
	expect_rejection( $script, array_merge( $base, array( 'policy_sha256' => str_repeat( 'b', 64 ) ) ) );
	file_put_contents( $log, 'Warning: upload failed. The backup succeeded and is now complete.' );
	expect_rejection( $script, $base );
	file_put_contents( $log, 'Recording as successfully uploaded. The backup succeeded and is now complete.' );
	if ( file_get_contents( $old_path ) !== '<?php /* Version: 0.5.1 */' ) { throw new RuntimeException( 'Rejected preparation wrote policy.' ); }
	$args = array( json_encode( $base ) );
	ob_start();
	require __DIR__ . '/../../scripts/integrations/20261006-gloves-captcha-dev.php';
	$result = ob_get_clean();
	$current = get_option( 'updraft_s3' );
	if ( $current['settings']['old-instance']['instance_enabled'] !== 0 || $current['settings']['old-instance']['path'] !== 'mrn-webdesigns-backups/sites/gloves.mrndev.io' || $current['settings']['s-mrn-gloves-captcha-dev']['path'] !== 'mrn-webdesigns-backups/sites/gloves-mrndev-io' || $current['settings']['s-mrn-gloves-captcha-dev']['secretkey'] !== 'fixture-secret' || file_get_contents( $old_path ) !== $new_code || strpos( $result, 'fixture-secret' ) !== false || ! get_option( 'mrn_gloves_captcha_qualification' ) ) { throw new RuntimeException( 'Exact isolation, credential preservation or output privacy failed.' ); }
	if ( strpos( file_get_contents( $root . '/.mrn-gloves-captcha-dev-recovery/preparation.json' ), 'fixture-secret' ) !== false ) { throw new RuntimeException( 'Private recovery evidence included a secret.' ); }
	echo "Gloves Dev preparation rejection and exact successful preparation tests passed.\n";
} finally {
	$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
	foreach ( $iterator as $file ) { $file->isDir() ? rmdir( $file->getPathname() ) : unlink( $file->getPathname() ); }
	rmdir( $root );
}
