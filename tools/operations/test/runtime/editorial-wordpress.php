<?php
/** Exercise the pinned Child adapter against scratch WordPress/MySQL.
 * This is an adapter regression, never an MCP/QA/recovery acceptance run.
 */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || getenv( 'MRN_EDITORIAL_WORDPRESS_FIXTURE' ) !== '1'
	|| wp_get_environment_type() !== 'local' || home_url() !== 'https://child.fixture.example.invalid'
	|| DB_NAME !== 'mrn_editorial' || ! preg_match( '#^localhost:/tmp/mrn-editorial-wp\.[A-Za-z0-9]+/db\.sock$#D', DB_HOST )
	|| ! defined( 'WP_HTTP_BLOCK_EXTERNAL' ) || ! WP_HTTP_BLOCK_EXTERNAL ) {
	throw new RuntimeException( 'The scratch editorial WordPress fixture is required.' );
}
$provider = getenv( 'MRN_EDITORIAL_PROVIDER' );
$mrn_content_acf = require $provider . '/child/content-acf.php';
$mrn_content_fields = require $provider . '/child/content-fields.php';
$adapter = require $provider . '/child/content-records.php';
$count = 0;
$check = static function ( $condition, $step ) use ( &$count ) {
	++$count;
	if ( ! $condition ) {
		throw new RuntimeException( esc_html( $step ) );
	}
};
$admin = get_user_by( 'login', 'editorial-fixture' );
$check( $admin && user_can( $admin, 'manage_options' ), 'Scratch admin required' );
wp_set_current_user( $admin->ID );
$contributor = wp_insert_user( array( 'user_login' => 'editorial-contributor', 'user_pass' => wp_generate_password(), 'role' => 'contributor' ) );
$check( is_int( $contributor ) && $contributor > 0, 'Scratch contributor setup failed' );
$sibling = wp_insert_post( array( 'post_title' => 'Unrelated', 'post_content' => 'Must remain unchanged', 'post_status' => 'draft' ), true );
$check( is_int( $sibling ) && $sibling > 0, 'Unrelated scratch post setup failed' );
$site = array( 'site_id' => 1, 'site_url' => home_url() );
$change = static function ( $target, $changes ) use ( $adapter, $check ) {
	$before = $adapter( $target + array( 'mode' => 'read' ) );
	$check( $before['success'], 'Baseline read failed' );
	$input = $target + array( 'changes' => $changes, 'before_sha256' => $before['snapshot_sha256'] );
	$plan = $adapter( $input + array( 'mode' => 'preview' ) );
	$check( $plan['success'], 'Preview failed: ' . ( $plan['error_code'] ?? '' ) );
	$check( $adapter( $target + array( 'mode' => 'read' ) ) === $before, 'Preview wrote content' );
	$apply = $input + array( 'mode' => 'apply', 'candidate_sha256' => $plan['candidate_sha256'], 'operation_id' => wp_generate_uuid4() );
	$added = array();
	$observe_meta = static function ( $meta_id, $post_id, $key ) use ( &$added, $target ) {
		if ( (int) $post_id === $target['post_id'] ) {
			$added[] = $key;
		}
	};
	add_action( 'added_post_meta', $observe_meta, 10, 3 );
	try {
		$result = $adapter( $apply );
	} finally {
		remove_action( 'added_post_meta', $observe_meta, 10 );
	}
	if ( ! $result['success'] ) {
		$check( $adapter( $target + array( 'mode' => 'read' ) ) === $before, 'Failed operation did not restore the original snapshot' );
		$check( get_option( 'mrn_content_record_' . $apply['operation_id'], null ) === null, 'Failed operation retained an execution ledger' );
		WP_CLI::error( sprintf( '%s %s: %s; added metadata before rollback: %s; original snapshot verified after rollback.',
			$target['post_type'], $changes['post_status'] ?? 'core edit', $result['error_code'] ?? 'unknown', implode( ', ', $added ) ) );
	}
	$check( $result['success'], 'Apply failed' );
	$installed_target = array_replace( $target, array( 'post_id' => $result['post_id'] ) );
	clean_post_cache( $result['post_id'] );
	$installed = $adapter( $installed_target + array( 'mode' => 'read' ) );
	$check( $installed['success'] && $installed['snapshot_sha256'] === $result['installed_sha256'], 'Independent installed snapshot differs' );
	$check( $installed['snapshot']['core'] === $plan['candidate_snapshot']['core'], 'Native core readback differs' );
	$check( empty( $adapter( $apply )['success'] ), 'Duplicate adapter apply accepted' );
	return $installed;
};
foreach ( array( 'page', 'post' ) as $type ) {
	$target = $site + array( 'post_type' => $type, 'post_id' => 0 );
	$draft = $change( $target, array( 'post_title' => 'Editorial fixture', 'post_name' => 'editorial-' . $type, 'post_status' => 'draft' ) );
	$target['post_id'] = $draft['snapshot']['post_id'];
	$check( $target['post_id'] > 0 && get_post_status( $target['post_id'] ) === 'draft', 'Creation was not a draft' );
	update_post_meta( $target['post_id'], 'editorial_unrelated_meta', 'Keep' );
	$edited = $change( $target, array( 'post_content' => '<p>Reviewed copy.</p>' ) );
	$check( $edited['snapshot']['core']['post_status'] === 'draft', 'Core edit published the draft' );
	WP_CLI::log( 'Verified ' . $type . ' draft creation and core edit.' );
	if ( 'post' === $type ) {
		// The contributor may edit this exact own draft, but may not publish it.
		wp_update_post( array( 'ID' => $target['post_id'], 'post_author' => $contributor ) );
		wp_set_current_user( $contributor );
		$baseline = $adapter( $target + array( 'mode' => 'read' ) );
		$check( $baseline['success'], 'Contributor must be able to read the own draft' );
		$denied = $adapter( $target + array( 'mode' => 'preview', 'changes' => array( 'post_status' => 'publish' ), 'before_sha256' => $baseline['snapshot_sha256'] ) );
		$check( empty( $denied['success'] ) && $denied['error_code'] === 'mrn_content_permission', 'WordPress allowed publication without publish_posts' );
		wp_set_current_user( $admin->ID );
	}
	$published = $change( $target, array( 'post_status' => 'publish' ) );
	$check( $published['snapshot']['core']['post_status'] === 'publish', 'Explicit publication did not become public' );
	$returned = $change( $target, array( 'post_status' => 'draft' ) );
	$check( $returned['snapshot']['core']['post_status'] === 'draft', 'Explicit return to draft did not persist' );
	$check( $returned['snapshot']['core']['post_content'] === '<p>Reviewed copy.</p>'
		&& get_post_meta( $target['post_id'], 'editorial_unrelated_meta', true ) === 'Keep'
		&& $returned['snapshot']['unrelated_meta_sha256'] === $edited['snapshot']['unrelated_meta_sha256'], 'Status changes modified unrelated content' );
	WP_CLI::log( 'Verified ' . $type . ' publication, return to draft and unrelated metadata.' );
}
$check( get_post_field( 'post_content', $sibling ) === 'Must remain unchanged' && get_post_status( $sibling ) === 'draft', 'A sibling record changed' );
echo wp_json_encode( array( 'result' => 'PASS', 'assertions' => $count, 'wordpress' => get_bloginfo( 'version' ), 'php' => PHP_VERSION, 'qa_or_recovery_qualified' => false ) ) . PHP_EOL;
