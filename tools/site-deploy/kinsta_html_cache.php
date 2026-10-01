<?php
/**
 * Kinsta's local v2 exact-URL purge protocol, without installing a runtime plugin.
 *
 * Contract: official kinsta-mu-plugins/cache/class-cache-purge.php,
 * convert_purge_list_to_request() and send_cache_purge_request().
 * Only the provider's "single" keys are sent; never group/all/CDN/object purges.
 *
 * @package MRNSiteDeploy
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( 1 );
}

/** Refresh already-validated, site-owned HTML URLs. */
function mrn_deploy_kinsta_html( array $urls ): void {
	foreach ( array_chunk( $urls, 40 ) as $chunk ) {
		$body = array();
		foreach ( $chunk as $index => $url ) {
			$body[ 'single|' . $index ] = substr( $url, strlen( 'https://' ) );
		}
		$response = wp_remote_post(
			'https://localhost/kinsta-clear-cache/v2/immediate',
			array(
				'body'        => $body,
				'timeout'     => 30,
				'redirection' => 0,
				// Provider-local TLS terminates with the site's certificate.
				// This fixed loopback endpoint carries no credentials and never redirects.
				'sslverify'   => false,
			)
		);
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			WP_CLI::error( 'Kinsta exact-URL HTML invalidation failed.' );
		}
	}
}
