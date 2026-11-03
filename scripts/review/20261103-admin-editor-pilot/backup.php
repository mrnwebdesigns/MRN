<?php
/** Exact-target adapter for the canonical Updraft database backup/verify gate. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || 'admin-editor-pilot.mrndev.io' !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
	throw new RuntimeException( 'Wrong review target.' );
}
$label = $args[0] ?? '';
if ( ! preg_match( '/^pre-admin-pilot-[a-z0-9-]+$/', $label ) ) {
	throw new RuntimeException( 'Expected labeled review backup.' );
}
$s3 = get_option( 'updraft_s3', array() );
if ( array( 's3' ) !== get_option( 'updraft_service' ) || empty( $s3['settings'] ) ) {
	throw new RuntimeException( 'Isolated S3 backup service unavailable.' );
}
foreach ( $s3['settings'] as $instance ) {
	if ( ! empty( $instance['instance_enabled'] ) && 'mrn-webdesigns-backups/sites/admin-editor-pilot-mrndev-io' !== ( $instance['path'] ?? '' ) ) {
		throw new RuntimeException( 'Backup prefix mismatch.' );
	}
}
global $updraftplus;
if ( ! is_object( $updraftplus ) || ! is_callable( array( $updraftplus, 'backupnow_database' ) ) ) {
	throw new RuntimeException( 'Installed Updraft API unavailable.' );
}
$result = $updraftplus->backupnow_database( array( 'label' => $label, 'nocloud' => false ) );
$nonce = $updraftplus->file_nonce;
$backup = UpdraftPlus_Backup_History::get_backup_set_by_nonce( $nonce );
$log_file = trailingslashit( $updraftplus->backups_dir_location() ) . 'log.' . $nonce . '.txt';
$log = is_readable( $log_file ) ? file_get_contents( $log_file ) : '';
$files = array_filter( array_intersect_key( (array) $backup, array_flip( array( 'plugins', 'themes', 'uploads', 'others', 'wpcore', 'more' ) ) ) );
$sha = $backup['checksums']['sha256']['db0'] ?? '';
$valid = true === $result && is_array( $backup ) && $nonce === ( $backup['nonce'] ?? '' )
	&& $label === ( $backup['label'] ?? '' ) && ! empty( $backup['db'] ) && (int) ( $backup['db-size'] ?? 0 ) > 0
	&& preg_match( '/^[0-9a-f]{64}$/', $sha ) && ! $files && in_array( 's3', (array) ( $backup['service'] ?? array() ), true )
	&& false !== stripos( $log, 'The backup succeeded and is now complete' )
	&& false !== stripos( $log, 'Recording as successfully uploaded' )
	&& ! preg_match( '/Backup aborted|Backup failed|apparently unsuccessfully|errors occurred/i', $log );
if ( ! $valid ) {
	throw new RuntimeException( 'Remote backup did not verify; stop before import. Job: ' . esc_html( $nonce ) );
}
$receipt = array( 'valid' => true, 'host' => 'admin-editor-pilot.mrndev.io', 'checked_at' => time(), 'label' => $label, 'nonce' => $nonce,
	'key' => 'sites/admin-editor-pilot-mrndev-io/' . $backup['db'], 'size' => (int) $backup['db-size'], 'sha256' => $sha );
$path = dirname( ABSPATH, 2 ) . '/review-private/verified-backup.json';
file_put_contents( $path, wp_json_encode( $receipt, JSON_PRETTY_PRINT ) );
chmod( $path, 0600 );
echo wp_json_encode( $receipt, JSON_PRETTY_PRINT ) . "\n";
