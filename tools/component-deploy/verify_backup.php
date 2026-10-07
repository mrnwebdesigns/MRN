<?php
/** Read-only verification of the exact MainWP-verified remote database backup. */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( 1 );
}
$mrn_nonce = getenv( 'MRN_PARENT_BACKUP_NONCE' );
if ( ! is_string( $mrn_nonce ) || ! preg_match( '/^[a-f0-9]{12}$/D', $mrn_nonce ) ) {
	WP_CLI::error( 'An exact MainWP backup nonce is required.' );
}
global $updraftplus;
$mrn_backup = UpdraftPlus_Backup_History::get_backup_set_by_nonce( $mrn_nonce );
$mrn_log    = trailingslashit( $updraftplus->backups_dir_location() ) . 'log.' . $mrn_nonce . '.txt';
// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Bounded local Updraft completion evidence.
$mrn_text = is_file( $mrn_log ) && filesize( $mrn_log ) < 8388608 ? file_get_contents( $mrn_log ) : '';
$mrn_files = false;
foreach ( array( 'plugins', 'themes', 'uploads', 'others', 'wpcore', 'more' ) as $mrn_entity ) {
	$mrn_files = $mrn_files || ! empty( $mrn_backup[ $mrn_entity ] );
}
$mrn_services = array_values( array_filter( (array) ( $mrn_backup['service'] ?? array() ) ) );
$mrn_checksum = $mrn_backup['checksums']['sha256']['db0'] ?? '';
$mrn_valid = is_array( $mrn_backup ) && ( $mrn_backup['nonce'] ?? '' ) === $mrn_nonce
	&& ! empty( $mrn_backup['label'] ) && ! empty( $mrn_backup['db'] ) && (int) ( $mrn_backup['db-size'] ?? 0 ) > 0
	&& is_string( $mrn_checksum ) && preg_match( '/^[a-f0-9]{64}$/D', $mrn_checksum )
	&& ! $mrn_files && empty( $mrn_backup['always_keep'] ) && $mrn_services && ! in_array( 'none', $mrn_services, true )
	&& false !== stripos( $mrn_text, 'The backup succeeded and is now complete' )
	&& preg_match( '/Recording as successfully uploaded: [^\r\n]+(?<!more services to follow\))$/m', $mrn_text )
	&& ! preg_match( '/Backup aborted|Backup failed|apparently unsuccessfully|errors occurred/i', $mrn_text )
	&& filemtime( $mrn_log ) <= time() && time() - filemtime( $mrn_log ) < 900;
WP_CLI::line( 'MRN_RESULT=' . wp_json_encode( array( 'valid' => (bool) $mrn_valid, 'nonce' => $mrn_nonce,
	'label' => $mrn_backup['label'] ?? '', 'sha256' => $mrn_checksum, 'database_size' => (int) ( $mrn_backup['db-size'] ?? 0 ) ) ) );
if ( ! $mrn_valid ) {
	WP_CLI::error( 'The MainWP backup is not fresh, remotely complete and database-only.' );
}
unset( $mrn_nonce, $mrn_backup, $mrn_log, $mrn_text, $mrn_files, $mrn_services, $mrn_checksum, $mrn_valid, $mrn_entity );
