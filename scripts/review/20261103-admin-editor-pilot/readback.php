<?php
/** Private WP-CLI readback/auth fixture helper for the isolated review site. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || 'admin-editor-pilot.mrndev.io' !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
	throw new RuntimeException( 'Wrong review target.' );
}
$mode = $args[0] ?? 'readback';
if ( 'auth' === $mode ) {
	$user = get_user_by( 'login', 'mrn-admin' );
	if ( ! $user || ! user_can( $user, 'manage_options' ) ) { throw new RuntimeException( 'Expected pilot administrator unavailable.' ); }
	$expires = time() + 2 * HOUR_IN_SECONDS;
	$token = WP_Session_Tokens::get_instance( $user->ID )->create( $expires );
	$cookies = array();
	foreach ( array( 'logged_in' => LOGGED_IN_COOKIE, 'secure_auth' => SECURE_AUTH_COOKIE ) as $scheme => $name ) {
		$cookies[] = array( 'name' => $name, 'value' => wp_generate_auth_cookie( $user->ID, $expires, $scheme, $token ), 'domain' => 'admin-editor-pilot.mrndev.io', 'path' => '/', 'secure' => true, 'httpOnly' => true, 'sameSite' => 'Lax' );
	}
	$path = dirname( ABSPATH, 2 ) . '/review-private/administrator-auth.json';
	file_put_contents( $path, wp_json_encode( $cookies ) ); chmod( $path, 0600 );
	echo "Temporary native administrator session written privately.\n";
	return;
}
$canonical = static function ( $value ) use ( &$canonical ) {
	if ( is_array( $value ) ) { return array_map( $canonical, $value ); }
	return is_string( $value ) ? str_replace( 'https://admin-editor-pilot.mrndev.io', 'https://admin-editor-pilot.localhost', $value ) : $value;
};
$pages = array();
foreach ( array( 2523 => 19, 2524 => 32 ) as $id => $count ) {
	$rows = get_field( 'page_content_rows', $id, false );
	if ( 'draft' !== get_post_status( $id ) || count( (array) $rows ) !== $count ) { throw new RuntimeException( 'Pilot fixture changed.' ); }
	$pages[ $id ] = array( 'rows' => count( $rows ), 'status' => get_post_status( $id ),
		'content_hash' => hash( 'sha256', wp_json_encode( $canonical( $rows ) ) ),
		'native_acf_hash' => hash( 'sha256', wp_json_encode( $canonical( get_fields( $id, false ) ) ) ),
		'post_content_hash' => hash( 'sha256', $canonical( get_post_field( 'post_content', $id ) ) ) );
}
echo wp_json_encode( array( 'host' => wp_parse_url( home_url(), PHP_URL_HOST ), 'wp' => get_bloginfo( 'version' ), 'acf' => ACF_VERSION,
	'php' => PHP_VERSION, 'stylesheet' => get_stylesheet(), 'template' => get_template(), 'pilot_enabled' => mrn_editor_experience_enabled(),
	'save_guard' => function_exists( 'mrn_base_stack_editor_save_guard_start' ), 'pages' => $pages,
	'cron_disabled' => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON, 'indexing' => get_option( 'blog_public' ),
	'backup_intervals' => array( get_option( 'updraft_interval' ), get_option( 'updraft_interval_database' ) ) ), JSON_PRETTY_PRINT ) . "\n";
