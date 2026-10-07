<?php
/**
 * Prepare the named Gloves Dev CAPTCHA pilot after a verified remote DB backup.
 *
 * Execute through its dedicated site-owner WP-CLI session as mrn-developer.
 * $args[0] is a JSON object with nonce, prior_s3_sha256, prior_policy_sha256,
 * policy_base64 and policy_sha256. These contain no credentials.
 * Never install this one-shot script as a plugin or run it on production.
 *
 * @package MRN
 */

// phpcs:disable WordPress.WP.AlternativeFunctions -- Exact local recovery files require exclusive creation and same-directory atomic replacement.
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! current_user_can( 'manage_options' ) || 'https://gloves.mrndev.io' !== untrailingslashit( home_url() ) ) {
	throw new RuntimeException( 'The named Dev site and authenticated maintainer are required.' );
}
$plan = json_decode( (string) ( $args[0] ?? '' ), true );
if ( ! is_array( $plan ) || ! preg_match( '/^[a-f0-9]{12}$/D', (string) ( $plan['nonce'] ?? '' ) ) ) {
	throw new RuntimeException( 'A specific verified backup nonce is required.' );
}
global $updraftplus;
$backup = null;
foreach ( UpdraftPlus_Backup_History::get_history() as $time => $set ) {
	if ( $plan['nonce'] === ( $set['nonce'] ?? '' ) && $time >= time() - 1800 && $time <= time() ) {
		$backup = $set;
		break;
	}
}
$sha = $backup['checksums']['sha256']['db0'] ?? '';
if ( ! is_array( $backup ) || empty( $backup['db'] ) || empty( $backup['db-size'] ) || 'pre-gloves-captcha-dev-20261006' !== ( $backup['label'] ?? '' ) || array( 's3' ) !== ( $backup['service'] ?? array() ) || ! preg_match( '/^[a-f0-9]{64}$/D', $sha ) ) {
	throw new RuntimeException( 'Fresh labeled remote database backup evidence is incomplete.' );
}
foreach ( array( 'plugins', 'themes', 'uploads', 'others', 'wpcore', 'more' ) as $entity ) {
	if ( ! empty( $backup[ $entity ] ) ) {
		throw new RuntimeException( 'The backup must be database-only.' );
	}
}
$log_path = trailingslashit( $updraftplus->backups_dir_location() ) . 'log.' . $plan['nonce'] . '.txt';
$log = is_readable( $log_path ) ? file_get_contents( $log_path ) : '';
if ( ! preg_match( '/backup (apparently )?succeeded|backup succeeded and is now complete|the backup is finished/i', $log ) || false === stripos( $log, 'Recording as successfully uploaded' ) || preg_match( '/\b(error|warning):|backup failed|fatal error|errors have occurred|failed to upload|storage method not found|wp_mail_failed/i', $log ) ) {
	throw new RuntimeException( 'Remote upload and backup completion are unverified.' );
}
$s3 = get_option( 'updraft_s3', array() );
// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Hash trusted stored options; never deserialize.
if ( ! hash_equals( (string) ( $plan['prior_s3_sha256'] ?? '' ), hash( 'sha256', serialize( $s3 ) ) ) || array( 's3' ) !== get_option( 'updraft_service' ) || ! is_array( $s3['settings'] ?? null ) ) {
	throw new RuntimeException( 'The reviewed S3 configuration has changed.' );
}
$active = array_filter( $s3['settings'], static function ( $setting ) { return ! isset( $setting['instance_enabled'] ) || '1' === (string) $setting['instance_enabled']; } );
if ( 1 !== count( $active ) ) {
	throw new RuntimeException( 'One exact existing S3 destination is required.' );
}
$old_id = array_key_first( $active );
$old = $active[ $old_id ];
if ( 'mrn-webdesigns-backups/sites/gloves.mrndev.io' !== ( $old['path'] ?? '' ) || empty( $old['accesskey'] ) || empty( $old['secretkey'] ) ) {
	throw new RuntimeException( 'The named historical Dev destination did not match.' );
}
$new_id = 's-mrn-gloves-captcha-dev';
if ( isset( $s3['settings'][ $new_id ] ) ) {
	throw new RuntimeException( 'The new storage identity already exists; inspect before retrying.' );
}
$desired = $s3;
$desired['settings'][ $old_id ]['instance_enabled'] = 0;
$desired['settings'][ $new_id ] = $old;
$desired['settings'][ $new_id ]['instance_enabled'] = 1;
$desired['settings'][ $new_id ]['instance_label'] = 'Gloves Dev isolated backups';
$desired['settings'][ $new_id ]['path'] = 'mrn-webdesigns-backups/sites/gloves-mrndev-io';
$policy_path = WP_CONTENT_DIR . '/mu-plugins/mrn-updraft-local-retention/mrn-updraft-local-retention.php';
$mail_path = WP_CONTENT_DIR . '/mu-plugins/mrn-gloves-captcha-qualification-mail.php';
// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Transport encoding for the checksum-verified public source artifact; no eval.
$policy = base64_decode( (string) ( $plan['policy_base64'] ?? '' ), true );
if ( ! is_string( $policy ) || ! hash_equals( (string) ( $plan['policy_sha256'] ?? '' ), hash( 'sha256', $policy ) ) || ! preg_match( '/Version:\s*0\.6\.1\b/', $policy ) || ! is_file( $policy_path ) || is_link( $policy_path ) || ! hash_equals( (string) ( $plan['prior_policy_sha256'] ?? '' ), hash_file( 'sha256', $policy_path ) ) || file_exists( $mail_path ) ) {
	throw new RuntimeException( 'The exact qualified policy or prior runtime differs.' );
}
$mail = <<<'MAIL'
<?php
/** Temporary Gloves Dev qualification mail interception; contains no addresses. */
defined( 'ABSPATH' ) || exit;
if ( 'https://gloves.mrndev.io' === untrailingslashit( home_url() ) && get_option( 'mrn_gloves_captcha_qualification', false ) ) {
 add_filter( 'pre_wp_mail', static function ( $result, $attributes ) {
  $GLOBALS['mrn_gloves_qualification_mail_intercepted'] = 1 + ( $GLOBALS['mrn_gloves_qualification_mail_intercepted'] ?? 0 );
  return true;
 }, PHP_INT_MAX, 2 );
}
MAIL;
$private = dirname( untrailingslashit( ABSPATH ) ) . '/.mrn-gloves-captcha-dev-recovery';
if ( is_link( $private ) || ( ! is_dir( $private ) && ! wp_mkdir_p( $private ) ) || ! chmod( $private, 0700 ) ) {
	throw new RuntimeException( 'Private recovery storage is unavailable.' );
}
$prior_code = $private . '/policy-' . $plan['prior_policy_sha256'] . '.php';
$handle = fopen( $prior_code, 'x' );
$prior = file_get_contents( $policy_path );
if ( ! $handle || ! chmod( $prior_code, 0600 ) || strlen( $prior ) !== fwrite( $handle, $prior ) || ! fflush( $handle ) ) {
	throw new RuntimeException( 'Exact prior policy recovery could not be retained.' );
}
fclose( $handle );
$evidence = array( 'backup_nonce' => $plan['nonce'], 'backup_db_sha256' => $sha, 'old_storage_id' => $old_id, 'old_path' => $old['path'], 'new_storage_id' => $new_id, 'new_path' => $desired['settings'][ $new_id ]['path'], 'prior_s3_sha256' => $plan['prior_s3_sha256'], 'prior_policy_sha256' => $plan['prior_policy_sha256'], 'policy_sha256' => $plan['policy_sha256'], 'mail_sha256' => hash( 'sha256', $mail ), 'history_rows_removed' => 0, 'remote_objects_removed' => 0 );
$receipt = $private . '/preparation.json';
$handle = fopen( $receipt, 'x' );
$bytes = wp_json_encode( $evidence );
if ( ! $handle || ! chmod( $receipt, 0600 ) || strlen( $bytes ) !== fwrite( $handle, $bytes ) || ! fflush( $handle ) ) {
	throw new RuntimeException( 'Non-secret preparation evidence could not be retained.' );
}
fclose( $handle );
$publish = static function ( $path, $bytes ) {
	$temp = dirname( $path ) . '/.mrn-prepare-' . wp_generate_uuid4();
	if ( false === file_put_contents( $temp, $bytes, LOCK_EX ) || ! chmod( $temp, 0644 ) || ! hash_equals( hash( 'sha256', $bytes ), hash_file( 'sha256', $temp ) ) || ! rename( $temp, $path ) ) {
		throw new RuntimeException( 'An exact preparation file could not be selected atomically.' );
	}
	if ( function_exists( 'opcache_invalidate' ) ) { opcache_invalidate( $path, true ); }
};
$publish( $policy_path, $policy );
$publish( $mail_path, $mail );
update_option( 'updraft_s3', $desired );
update_option( 'mrn_gloves_captcha_qualification', true, false );
if ( get_option( 'updraft_s3' ) !== $desired || ! get_option( 'mrn_gloves_captcha_qualification' ) ) {
	throw new RuntimeException( 'Preparation readback failed; inspect private recovery before retrying.' );
}
echo wp_json_encode( array( 'phase' => 'prepared_requires_fresh_request_verification', 'home' => home_url(), 'evidence' => $evidence ) );
