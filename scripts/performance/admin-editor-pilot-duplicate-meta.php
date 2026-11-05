<?php
/**
 * Repair identical duplicate builder metadata on two owned review drafts only.
 *
 * wp eval-file FILE [plan|apply|rollback]
 * Uses native per-ID deletion/addition; retains the oldest value and all other
 * metadata. A private snapshot and a fresh remote Updraft backup precede writes.
 * This is not a plugin, upgrade routine, or production migration.
 *
 * @package MRN
 */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || 'admin-editor-pilot.mrndev.io' !== wp_parse_url( home_url(), PHP_URL_HOST )
	|| 'development' !== wp_get_environment_type() || ! is_file( ABSPATH . '.mrn-pilot-review' )
	|| ! function_exists( 'posix_geteuid' ) || 'mrndev-admin-editor' !== ( posix_getpwuid( posix_geteuid() )['name'] ?? '' ) ) {
	throw new RuntimeException( 'Exact owned review runtime required.' );
}
$mode = $args[0] ?? 'plan';
if ( ! in_array( $mode, array( 'plan', 'apply', 'rollback' ), true ) ) {
	throw new RuntimeException( 'Expected plan, apply or rollback.' );
}
global $wpdb;
$private = dirname( ABSPATH, 2 ) . '/review-private/';
$snapshot = $private . 'builder-duplicate-meta-20261105.json';
$read = static function ( $id ) use ( $wpdb ) {
	$groups = array();
	$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT meta_id, meta_key, meta_value FROM %i WHERE post_id = %d ORDER BY meta_id', $wpdb->postmeta, $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Exact review fixture; raw storage comparison.
	foreach ( $rows as $row ) {
		$groups[ $row['meta_key'] ][] = $row;
	}
	return $groups;
};
$logical_hash = static function ( $groups ) {
	$values = array_map( static fn( $rows ) => $rows[0]['meta_value'], $groups );
	ksort( $values );
	return hash( 'sha256', serialize( $values ) );
};
$plans = array();
foreach ( array( 2523 => 19, 2524 => 32 ) as $id => $sections ) {
	if ( 'draft' !== get_post_status( $id ) || 'page' !== get_post_type( $id )
		|| count( (array) get_post_meta( $id, 'page_content_rows', true ) ) !== $sections ) {
		throw new RuntimeException( 'Expected owned draft and section count.' );
	}
	$groups = $read( $id );
	$duplicates = array();
	foreach ( $groups as $key => $rows ) {
		if ( count( $rows ) < 2 || ! preg_match( '/^_?page_(hero|content|after_content)_rows(?:_|$)/', $key ) ) {
			continue;
		}
		$base = str_starts_with( $key, '_' ) ? substr( $key, 1 ) : $key;
		$reference = $groups[ '_' . $base ][0]['meta_value'] ?? '';
		if ( ! isset( $groups[ $base ] ) || ! str_contains( $reference, 'field_mrn_' ) ) {
			continue;
		}
		foreach ( $rows as $row ) {
			if ( $row['meta_value'] !== $rows[0]['meta_value'] ) {
				throw new RuntimeException( 'Conflicting builder duplicates require manual review; nothing removed.' );
			}
		}
		$duplicates[ $key ] = $rows;
	}
	$plans[ $id ] = array( 'logical_hash' => $logical_hash( $groups ), 'duplicates' => $duplicates );
}
if ( 'plan' !== $mode ) {
	$receipt = json_decode( file_get_contents( $private . 'verified-backup.json' ), true );
	if ( true !== ( $receipt['valid'] ?? false ) || 'admin-editor-pilot.mrndev.io' !== ( $receipt['host'] ?? '' )
		|| 0 > time() - (int) ( $receipt['checked_at'] ?? 0 ) || 300 <= time() - (int) ( $receipt['checked_at'] ?? 0 )
		|| ! str_starts_with( $receipt['label'] ?? '', 'pre-admin-pilot-postmeta-duplicates-' )
		|| ! str_starts_with( $receipt['key'] ?? '', 'sites/admin-editor-pilot-mrndev-io/' ) ) {
		throw new RuntimeException( 'Fresh verified duplicate-repair database backup required.' );
	}
	$backup = UpdraftPlus_Backup_History::get_backup_set_by_nonce( $receipt['nonce'] );
	if ( ( $backup['checksums']['sha256']['db0'] ?? '' ) !== $receipt['sha256'] || ( $backup['label'] ?? '' ) !== $receipt['label']
		|| ! in_array( 's3', (array) ( $backup['service'] ?? array() ), true ) ) {
		throw new RuntimeException( 'Backup receipt must match native Updraft history.' );
	}
}
$changed = 0;
if ( 'apply' === $mode && array_filter( array_column( $plans, 'duplicates' ) ) ) {
	if ( is_file( $snapshot ) ) {
		throw new RuntimeException( 'Preserve the existing repair snapshot; inspect before another apply.' );
	}
	$stream = fopen( $snapshot, 'x' );
	if ( ! $stream || ! chmod( $snapshot, 0600 ) ) {
		throw new RuntimeException( 'Private recovery snapshot could not be created.' );
	}
	$encoded = wp_json_encode( $plans );
	if ( fwrite( $stream, $encoded ) !== strlen( $encoded ) || ! fflush( $stream ) ) {
		throw new RuntimeException( 'Recovery snapshot incomplete; no deletion allowed.' );
	}
	fclose( $stream );
	foreach ( $plans as $id => $plan ) {
		foreach ( $plan['duplicates'] as $rows ) {
			foreach ( array_slice( $rows, 1 ) as $row ) {
				$current = get_metadata_by_mid( 'post', $row['meta_id'] );
				if ( ! $current || (int) $current->post_id !== (int) $id || $current->meta_key !== $row['meta_key']
					|| maybe_serialize( $current->meta_value ) !== $row['meta_value'] || ! delete_metadata_by_mid( 'post', $row['meta_id'] ) ) {
					throw new RuntimeException( 'Metadata changed or native deletion failed; stop and use the private snapshot.' );
				}
				++$changed;
			}
		}
		if ( $logical_hash( $read( $id ) ) !== $plan['logical_hash'] ) {
			throw new RuntimeException( 'Logical metadata changed; stop and recover from the private snapshot.' );
		}
	}
} elseif ( 'rollback' === $mode ) {
	$saved = is_file( $snapshot ) ? json_decode( file_get_contents( $snapshot ), true ) : null;
	if ( ! is_array( $saved ) || array_keys( $saved ) !== array_keys( $plans ) ) {
		throw new RuntimeException( 'Exact private repair snapshot required.' );
	}
	foreach ( $saved as $id => $plan ) {
		$groups = $read( $id );
		foreach ( $plan['duplicates'] as $key => $rows ) {
			$current = $groups[ $key ] ?? array();
			if ( ! $current || count( $current ) > count( $rows ) ) {
				throw new RuntimeException( 'Current metadata no longer matches repair scope.' );
			}
			foreach ( $current as $row ) {
				if ( $row['meta_value'] !== $rows[0]['meta_value'] ) {
					throw new RuntimeException( 'Edited values must not be overwritten by rollback.' );
				}
			}
			for ( $i = count( $current ); $i < count( $rows ); ++$i ) {
				if ( ! add_metadata( 'post', $id, $key, wp_slash( maybe_unserialize( $rows[0]['meta_value'] ) ) ) ) {
					throw new RuntimeException( 'Native duplicate restoration failed.' );
				}
				++$changed;
			}
		}
	}
}
$summary = array();
foreach ( $plans as $id => $plan ) {
	$summary[ $id ] = array( 'duplicate_keys' => count( $plan['duplicates'] ), 'redundant_rows' => array_sum( array_map( static fn( $rows ) => count( $rows ) - 1, $plan['duplicates'] ) ),
		'logical_hash_before' => $plan['logical_hash'], 'logical_hash_after' => $logical_hash( $read( $id ) ) );
}
WP_CLI::line( wp_json_encode( array( 'mode' => $mode, 'changed_rows' => $changed, 'fixtures' => $summary ), JSON_PRETTY_PRINT ) );
