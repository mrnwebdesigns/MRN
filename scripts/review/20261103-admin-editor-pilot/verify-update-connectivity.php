<?php
/** Read-only metadata probes: no upgrader calls, licenses or response bodies logged. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || 'admin-editor-pilot.mrndev.io' !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
	throw new RuntimeException( 'Wrong review target.' );
}
$http = new WP_Http();
$result = array(
	'host' => wp_parse_url( home_url(), PHP_URL_HOST ),
	'wordpress' => get_bloginfo( 'version' ),
	'debug_enabled' => WP_DEBUG,
	'debug_display' => WP_DEBUG_DISPLAY,
	'debug_log_private' => dirname( ABSPATH, 2 ) . '/review-private/wordpress-debug.log' === WP_DEBUG_LOG,
	'external_block_enabled' => defined( 'WP_HTTP_BLOCK_EXTERNAL' ) && WP_HTTP_BLOCK_EXTERNAL,
	'unrelated_host_blocked' => $http->block_request( 'https://example.com/' ),
	'backup_host_allowed' => ! $http->block_request( 'https://mrn-webdesigns-backups.s3.us-east-2.amazonaws.com/' ),
	'cron_disabled' => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
	'indexing' => (int) get_option( 'blog_public' ),
	'mail_blocked' => false === apply_filters( 'pre_wp_mail', null, array() ),
	'metadata' => array(),
);
$endpoints = array(
	'wordpress' => 'https://api.wordpress.org/core/version-check/1.7/',
	'admin_menu_editor' => 'https://adminmenueditor.com/?get_metadata_for=admin-menu-editor-pro&installed_version=2.34',
	'updraftplus' => 'https://updraftplus.com/plugin-info/?udm_action=updateinfo&slug=updraftplus',
);
foreach ( $endpoints as $name => $url ) {
	$start = microtime( true );
	$response = wp_remote_get( $url, array( 'timeout' => 20, 'sslverify' => true, 'redirection' => 3, 'limit_response_size' => 1048576 ) );
	$row = array( 'host' => wp_parse_url( $url, PHP_URL_HOST ), 'blocked' => $http->block_request( $url ), 'milliseconds' => round( ( microtime( true ) - $start ) * 1000 ) );
	if ( is_wp_error( $response ) ) {
		$row['error_code'] = $response->get_error_code();
	} else {
		$row['http_status'] = wp_remote_retrieve_response_code( $response );
		$metadata = json_decode( wp_remote_retrieve_body( $response ), true );
		$row['json_metadata'] = is_array( $metadata );
		$row['version_present'] = ! empty( $metadata['version'] ) || ! empty( $metadata['offers'] );
	}
	$result['metadata'][ $name ] = $row;
}
echo wp_json_encode( $result, JSON_PRETTY_PRINT ) . "\n";
