<?php
/**
 * Create and verify the remote database backup required immediately before deploy.
 *
 * Run through WP-CLI with MRN_BACKUP_LABEL set in the process environment.
 *
 * @package MRNSiteDeploy
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( 1 );
}

if ( ! function_exists( 'mrn_site_run_verified_predeploy_backup' ) ) {
	/**
	 * Run the database-only backup and emit its verified receipt.
	 *
	 * @return void
	 */
	function mrn_site_run_verified_predeploy_backup() {
		$backup_label = getenv( 'MRN_BACKUP_LABEL' );
		if ( ! is_string( $backup_label ) || ! preg_match( '/^[A-Za-z0-9._-]{1,40}$/', $backup_label ) ) {
			WP_CLI::error( 'MRN_BACKUP_LABEL is missing or invalid.' );
		}

		if ( ! isset( $GLOBALS['_ENV'] ) || ! is_array( $GLOBALS['_ENV'] ) ) {
			$GLOBALS['_ENV'] = array();
		}

		global $updraftplus;
		if ( ! is_object( $updraftplus ) || ! is_callable( array( $updraftplus, 'backupnow_database' ) ) ) {
			WP_CLI::error( 'The UpdraftPlus database backup API is unavailable.' );
		}

		$backup_result = $updraftplus->backupnow_database(
			array(
				'nocloud' => false,
				'label'   => $backup_label,
			)
		);

		$backup_nonce = '';
		if ( isset( $updraftplus->file_nonce ) && is_string( $updraftplus->file_nonce ) ) {
			$backup_nonce = $updraftplus->file_nonce;
		} elseif ( isset( $updraftplus->nonce ) && is_string( $updraftplus->nonce ) ) {
			$backup_nonce = $updraftplus->nonce;
		}

		if ( true !== $backup_result || ! preg_match( '/^[0-9a-f]{12}$/', $backup_nonce ) ) {
			WP_CLI::error( 'UpdraftPlus did not return a successful database backup nonce.' );
		}

		$history_callback = array( 'UpdraftPlus_Backup_History', 'get_backup_set_by_nonce' );
		if ( ! is_callable( $history_callback ) ) {
			WP_CLI::error( 'The UpdraftPlus backup history API is unavailable.' );
		}

		$backup   = call_user_func( $history_callback, $backup_nonce );
		$services = is_array( $backup ) && isset( $backup['service'] )
			? array_values( array_filter( (array) $backup['service'] ) )
			: array();
		$sha256   = is_array( $backup ) && isset( $backup['checksums']['sha256']['db0'] )
			? $backup['checksums']['sha256']['db0']
			: '';

		$file_entities   = array( 'plugins', 'themes', 'uploads', 'others', 'wpcore', 'more' );
		$has_file_backup = false;
		foreach ( $file_entities as $entity ) {
			if ( ! empty( $backup[ $entity ] ) ) {
				$has_file_backup = true;
				break;
			}
		}

		$log_file = trailingslashit( $updraftplus->backups_dir_location() ) . 'log.' . $backup_nonce . '.txt';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- This is a local Updraft log, not a remote URL.
		$log_contents = is_readable( $log_file ) ? file_get_contents( $log_file ) : '';
		$log_complete = is_string( $log_contents ) && false !== stripos( $log_contents, 'The backup succeeded and is now complete' );
		$log_uploaded = is_string( $log_contents ) && 1 === preg_match( '/Recording as successfully uploaded: [^\r\n]+(?<!more services to follow\))$/m', $log_contents );
		$log_failed   = is_string( $log_contents ) && 1 === preg_match( '/Backup aborted|Backup failed|apparently unsuccessfully|errors occurred/i', $log_contents );

		$valid = is_array( $backup )
			&& isset( $backup['nonce'], $backup['label'], $backup['db'], $backup['db-size'] )
			&& $backup_nonce === $backup['nonce']
			&& $backup_label === $backup['label']
			&& is_string( $backup['db'] )
			&& '' !== $backup['db']
			&& (int) $backup['db-size'] > 0
			&& is_string( $sha256 )
			&& 1 === preg_match( '/^[0-9a-f]{64}$/', $sha256 )
			&& ! $has_file_backup
			&& empty( $backup['always_keep'] )
			&& ! empty( $services )
			&& ! in_array( 'none', $services, true )
			&& $log_complete
			&& $log_uploaded
			&& ! $log_failed;

		$receipt = array(
			'valid'           => $valid,
			'nonce'           => $backup_nonce,
			'label'           => $backup['label'] ?? null,
			'database_size'   => isset( $backup['db-size'] ) ? (int) $backup['db-size'] : 0,
			'sha256'          => $sha256,
			'services'        => $services,
			'has_file_backup' => $has_file_backup,
			'always_keep'     => ! empty( $backup['always_keep'] ),
			'log_complete'    => $log_complete,
			'log_uploaded'    => $log_uploaded,
			'log_failed'      => $log_failed,
		);

		WP_CLI::line( 'MRN_RESULT=' . wp_json_encode( $receipt ) );

		if ( ! $valid ) {
			WP_CLI::error( 'UpdraftPlus backup receipt verification failed.' );
		}
	}
}

mrn_site_run_verified_predeploy_backup();
