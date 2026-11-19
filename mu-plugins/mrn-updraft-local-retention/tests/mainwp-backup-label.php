<?php
/** Regression for MainWP Child's native Updraft backup options contract. */

define( 'ABSPATH', __DIR__ . '/' );

/** @param mixed $value Input. @return mixed */
function wp_unslash( $value ) {
	return is_array( $value ) ? array_map( 'wp_unslash', $value ) : stripslashes( $value );
}

/** Native class bootstrap registers unrelated updater filters. @return void */
function add_filter() {}

require dirname( __DIR__ ) . '/includes/class-mrn-updraft-mainwp-policy.php';

$options = array( 'nocloud' => false, 'use_nonce' => '123456abcdef', 'unrelated' => array( 'preserve' => true ) );
$post    = array( 'function' => 'updraftplus', 'mwp_action' => 'backup_now', 'mainwpsignature' => 'fixture-signature', 'backupnow_nofiles' => '1', 'backupnow_label' => 'MRN MainWP database-only backup' );
$checks  = 0;

/** @param bool $condition Assertion. @return void */
function check_label( $condition ) {
	global $checks;
	if ( ! $condition ) {
		throw new RuntimeException( 'Backup label contract regression.' );
	}
	++$checks;
}

$_POST = $post;
check_label( MRN_Updraft_MainWP_Policy::mainwp_backup_options( $options, array() ) === $options + array( 'label' => 'MRN MainWP database-only backup' ) );
check_label( MRN_Updraft_MainWP_Policy::mainwp_backup_options( $options, $post ) === $options );
check_label( MRN_Updraft_MainWP_Policy::mainwp_backup_options( $options + array( 'label' => 'Native label' ), array() ) === $options + array( 'label' => 'Native label' ) );
$_POST['package_base64'] = new stdClass(); // Sentinel: unrelated upload payloads must never be traversed.
check_label( MRN_Updraft_MainWP_Policy::mainwp_backup_options( $options, array() ) === $options + array( 'label' => 'MRN MainWP database-only backup' ) );

foreach ( array( 'function', 'mwp_action', 'mainwpsignature', 'backupnow_nofiles', 'backupnow_label' ) as $key ) {
	$_POST = $post;
	unset( $_POST[ $key ] );
	check_label( MRN_Updraft_MainWP_Policy::mainwp_backup_options( $options, array() ) === $options );
}
foreach ( array( 'backupnow_nodb', 'backupnow_nocloud', 'onlythisfileentity', 'onlythesetableentities' ) as $key ) {
	$_POST         = $post;
	$_POST[ $key ] = '1';
	check_label( MRN_Updraft_MainWP_Policy::mainwp_backup_options( $options, array() ) === $options );
}
$_POST = $post;
foreach ( array( array( 'use_nonce' => '../invalid' ), array( 'nocloud' => true ), array( 'use_nonce' => array() ) ) as $change ) {
	$invalid = array_replace( $options, $change );
	check_label( MRN_Updraft_MainWP_Policy::mainwp_backup_options( $invalid, array() ) === $invalid );
}
$_POST['backupnow_label'] = 'Different label';
check_label( MRN_Updraft_MainWP_Policy::mainwp_backup_options( $options, array() ) === $options );

/** @param string $text Text. @return string */
function esc_html__( $text ) {
	return $text;
}

/** @param string $hook Hook. @param array $value Value. @param array $request Request. @return array */
function apply_filters( $hook, $value, $request ) {
	check_label( 'updraft_backupnow_options' === $hook );
	return MRN_Updraft_MainWP_Policy::mainwp_backup_options( $value, $request );
}

/** @param string $event Native action. @param array $value Native options. @return void */
function do_action( $event, $value ) {
	check_label( 'updraft_backupnow_backup_database' === $event );
	check_label( 'MRN MainWP database-only backup' === $value['label'] );
	check_label( false === $value['nocloud'] );
	check_label( '123456abcdef' === $value['use_nonce'] );
	throw new RuntimeException( 'native-backup-observed' );
}

if ( isset( $argv[1] ) && is_file( $argv[1] ) ) {
	require $argv[1];
	class MRN_Label_Native_MainWP extends MainWP\Child\MainWP_Child_Updraft_Plus_Backups {
		/** @param array $message Native start response. @return void */
		public function close_browser_connection( $message = '' ) {
			check_label( '123456abcdef' === $message['nonce'] );
		}
	}
	$updraftplus = new class() {
		/** @return string */
		public function backup_time_nonce() {
			return '123456abcdef';
		}
	};
	$_POST       = $post;
	$_REQUEST    = $post;
	$native      = ( new ReflectionClass( 'MRN_Label_Native_MainWP' ) )->newInstanceWithoutConstructor();
	try {
		$native->backup_now();
	} catch ( RuntimeException $error ) {
		check_label( 'native-backup-observed' === $error->getMessage() );
	}
}

echo 'PASS backup label checks: ' . (int) $checks . "\n";
