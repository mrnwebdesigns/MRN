<?php
// phpcs:ignoreFile -- Explicit provider HTTP fixture, never installed on a site.
/** Offline provider responses for the disposable Resource poster integration tests. */
if ( 'local' !== wp_get_environment_type() || ! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( '127.0.0.1', 'localhost' ), true ) ) {
	throw new RuntimeException( 'Provider response fixtures require a disposable loopback runtime.' );
}
add_filter( 'pre_http_request', static function ( $pre, $args, $url ) {
	$host = wp_parse_url( $url, PHP_URL_HOST );
	if ( ! in_array( $host, array( 'www.youtube.com', 'vimeo.com', 'i.ytimg.com', 'i.vimeocdn.com' ), true ) ) {
		return $pre;
	}
	update_option( '_mrn_resource_poster_http_count', (int) get_option( '_mrn_resource_poster_http_count', 0 ) + 1, false );
	$mode = get_option( '_mrn_resource_poster_response', 'valid' );
	$body = '';
	if ( str_contains( $url, 'oembed' ) ) {
		$thumbnail = 'https://' . ( 'vimeo.com' === $host ? 'i.vimeocdn.com' : 'i.ytimg.com' ) . '/resource-poster-fixture.png';
		if ( 'unsafe' === $mode ) {
			$thumbnail = 'http://127.0.0.1/private.png';
		} elseif ( 'wrong_host' === $mode ) {
			$thumbnail = 'https://i.ytimg.com.example.invalid/private.png';
		}
		$data = array( 'type' => 'video', 'thumbnail_url' => $thumbnail );
		if ( 'missing' === $mode ) {
			unset( $data['thumbnail_url'] );
		}
		$body = wp_json_encode( $data );
	} else {
		if ( 'download_fail' === $mode ) {
			return new WP_Error( 'explicit_provider_download_failure' );
		}
		update_option( '_mrn_resource_poster_download_args', array_intersect_key( $args, array_flip( array( 'timeout', 'redirection', 'limit_response_size' ) ) ), false );
		$body = 'bad_image' === $mode ? '<html>Not an image</html>' : file_get_contents( get_attached_file( (int) get_option( '_mrn_resource_poster_fixture_image' ) ) );
		if ( ! empty( $args['stream'] ) ) {
			file_put_contents( $args['filename'], $body );
			$body = '';
		}
	}
	return array( 'headers' => array(), 'body' => $body, 'response' => array( 'code' => 200, 'message' => 'Explicit test fixture' ), 'cookies' => array() );
}, 20, 3 );
