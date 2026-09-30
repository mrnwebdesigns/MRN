<?php
/** Local fixture for the real backup verifier; never connects to WordPress. */
define( 'WP_CLI', true );

class WP_CLI {

	public static function error( $message ) {
		fwrite( STDERR, $message . "\n" );
		exit( 1 );
	}

	public static function line( $message ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture emits a machine-readable receipt, not HTML.
		echo $message . "\n";
	}
}

function trailingslashit( $path ) {
	return rtrim( $path, '/' ) . '/';
}

function wp_json_encode( $data ) {
	return json_encode( $data );
}

class Fixture_Updraft {
	public $file_nonce = '123456789abc';

	public function backupnow_database( $options ) {
		return false === $options['nocloud'] && getenv( 'MRN_BACKUP_LABEL' ) === $options['label'];
	}

	public function backups_dir_location() {
		return getenv( 'FIXTURE_DIR' );
	}
}

class UpdraftPlus_Backup_History {
	public static function get_backup_set_by_nonce( $nonce ) {
		$backup = array(
			'nonce'     => $nonce,
			'label'     => getenv( 'MRN_BACKUP_LABEL' ),
			'db'        => 'database.gz',
			'db-size'   => 123,
			'checksums' => array( 'sha256' => array( 'db0' => str_repeat( 'a', 64 ) ) ),
			'service'   => array( 's3' ),
		);
		switch ( getenv( 'FIXTURE_CASE' ) ) {
			case 'wrong-label':
				$backup['label'] = 'another-backup';
				break;
			case 'local-only':
				$backup['service'] = array( 'none' );
				break;
			case 'file-backup':
				$backup['themes'] = 'theme.zip';
				break;
			case 'always-keep':
				$backup['always_keep'] = true;
				break;
		}
		return $backup;
	}
}

$updraftplus = new Fixture_Updraft();
require dirname( __DIR__ ) . '/backup.php';
