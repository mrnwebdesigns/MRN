<?php
/**
 * Reversible, review-only native metadata index experiment. Never autoloaded.
 *
 * Run with wp eval-file FILE [plan|apply|rollback]. Mutations require the
 * existing review helper's fresh verified remote database backup receipt.
 * No values, callbacks, core files, original indexes or server settings change.
 *
 * @package MRN
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI || 'admin-editor-pilot.mrndev.io' !== wp_parse_url( home_url(), PHP_URL_HOST )
	|| 'development' !== wp_get_environment_type() || ! is_file( ABSPATH . '.mrn-pilot-review' )
	|| ! function_exists( 'posix_geteuid' ) || 'mrndev-admin-editor' !== ( posix_getpwuid( posix_geteuid() )['name'] ?? '' ) ) {
	throw new RuntimeException( 'Exact review host, marker, development environment and site owner required.' );
}

$mode = $args[0] ?? 'plan';
if ( ! in_array( $mode, array( 'plan', 'apply', 'rollback' ), true ) ) {
	throw new RuntimeException( 'Expected plan, apply or rollback.' );
}
global $wpdb;
$index = 'mrn_pilot_post_id_meta_key';
$read_indexes = static function () use ( $wpdb ) {
	return $wpdb->get_results( $wpdb->prepare( 'SHOW INDEX FROM %i', $wpdb->postmeta ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Explicit review-only CLI qualification.
};
$indexes = $read_indexes();
$owned = array_values( array_filter( $indexes, static fn( $row ) => $index === $row['Key_name'] ) );
$expected = count( $owned ) === 2 && 'post_id' === $owned[0]['Column_name'] && null === $owned[0]['Sub_part']
	&& 'meta_key' === $owned[1]['Column_name'] && '191' === (string) $owned[1]['Sub_part']
	&& '1' === (string) $owned[0]['Non_unique'] && '1' === (string) $owned[1]['Non_unique'];
if ( $owned && ! $expected ) {
	throw new RuntimeException( 'Named index has an unexpected definition; no mutation allowed.' );
}
$status = $wpdb->get_row( $wpdb->prepare( 'SHOW TABLE STATUS LIKE %s', $wpdb->esc_like( $wpdb->postmeta ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Exact table status, no schema wildcard.
if ( 'InnoDB' !== ( $status['Engine'] ?? '' ) || version_compare( $wpdb->db_version(), '8.0', '<' ) ) {
	throw new RuntimeException( 'This experiment requires the qualified MySQL 8 InnoDB runtime.' );
}
foreach ( array( 2523, 2524 ) as $post_id ) {
	if ( 'draft' !== get_post_status( $post_id ) ) {
		throw new RuntimeException( 'Review fixture must remain a draft.' );
	}
}
$change = ( 'apply' === $mode && ! $owned ) || ( 'rollback' === $mode && $owned );
$started = microtime( true );
if ( $change ) {
	$receipt_path = dirname( ABSPATH, 2 ) . '/review-private/verified-backup.json';
	$receipt = is_readable( $receipt_path ) ? json_decode( file_get_contents( $receipt_path ), true ) : null;
	if ( ! is_array( $receipt ) || true !== ( $receipt['valid'] ?? false ) || 'admin-editor-pilot.mrndev.io' !== ( $receipt['host'] ?? '' )
		|| 0 > time() - (int) ( $receipt['checked_at'] ?? 0 ) || 300 <= time() - (int) ( $receipt['checked_at'] ?? 0 )
		|| ! str_starts_with( $receipt['label'] ?? '', 'pre-admin-pilot-postmeta-index-' )
		|| ! str_starts_with( $receipt['key'] ?? '', 'sites/admin-editor-pilot-mrndev-io/' ) ) {
		throw new RuntimeException( 'Fresh labeled verified remote database backup required.' );
	}
	$backup = UpdraftPlus_Backup_History::get_backup_set_by_nonce( $receipt['nonce'] );
	if ( ( $backup['checksums']['sha256']['db0'] ?? '' ) !== $receipt['sha256'] || ( $backup['label'] ?? '' ) !== $receipt['label']
		|| ! in_array( 's3', (array) ( $backup['service'] ?? array() ), true ) ) {
		throw new RuntimeException( 'Backup receipt does not match native Updraft history.' );
	}
	// Session-only lock limits. Unsupported online DDL fails; never fall back to a blocking table copy.
	$wpdb->query( 'SET SESSION lock_wait_timeout = 5' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Bounded online DDL session.
	$wpdb->query( 'SET SESSION innodb_lock_wait_timeout = 5' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Bounded online DDL session.
	$sql = 'apply' === $mode
		? $wpdb->prepare( 'ALTER TABLE %i ADD INDEX %i (post_id, meta_key(191)), ALGORITHM=INPLACE, LOCK=NONE', $wpdb->postmeta, $index )
		: $wpdb->prepare( 'ALTER TABLE %i DROP INDEX %i, ALGORITHM=INPLACE, LOCK=NONE', $wpdb->postmeta, $index );
	if ( false === $wpdb->query( $sql ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared -- Prepared identifiers and fixed DDL above.
		throw new RuntimeException( 'Online index change failed; inspect database state before continuing.' );
	}
}
$change_seconds = microtime( true ) - $started;
$plans = array();
foreach ( array( 2523, 2524 ) as $post_id ) {
	if ( 'draft' !== get_post_status( $post_id ) ) {
		throw new RuntimeException( 'Review fixture must remain a draft.' );
	}
	$keys = array_keys( get_post_meta( $post_id ) );
	$sample = array_slice( $keys, 0, 100 );
	$begin = microtime( true );
	foreach ( $sample as $key ) {
		$wpdb->get_col( $wpdb->prepare( 'SELECT meta_id FROM %i WHERE meta_key = %s AND post_id = %d', $wpdb->postmeta, $key, $post_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Bounded native-query benchmark; values are not logged.
	}
	$plans[ $post_id ] = array(
		'lookups' => count( $sample ),
		'lookup_ms' => ( microtime( true ) - $begin ) * 1000,
		'plan' => $wpdb->get_results( $wpdb->prepare( 'EXPLAIN SELECT meta_id FROM %i WHERE meta_key = %s AND post_id = %d', $wpdb->postmeta, '_page_content_rows', $post_id ), ARRAY_A ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Read-only plan.
	);
}
$result = array( 'mode' => $mode, 'changed' => (bool) $change, 'change_seconds' => $change_seconds,
	'host' => 'admin-editor-pilot.mrndev.io', 'indexes' => $read_indexes(), 'fixtures' => $plans );
WP_CLI::line( wp_json_encode( $result, JSON_PRETTY_PRINT ) );
