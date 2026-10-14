<?php
// phpcs:ignoreFile -- Native WordPress integration in a disposable loopback runtime.
/** Run after resource-video.php setup, with the same private state path. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || 'local' !== wp_get_environment_type() || ! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( '127.0.0.1', 'localhost' ), true ) ) {
	throw new RuntimeException( 'Resource poster qualification requires a disposable loopback runtime.' );
}
$state = json_decode( file_get_contents( getenv( 'MRN_RESOURCE_VIDEO_STATE' ) ), true );
$admin = get_user_by( 'login', 'fleet-admin' );
wp_set_current_user( $admin->ID );
update_option( '_mrn_resource_poster_fixture_image', $state['attachments']['poster'], false );
update_option( '_mrn_resource_poster_response', 'valid', false );
require_once __DIR__ . '/resource-video-posters-fixture.php';
$checks = 0;
$assert = static function ( $condition, $message ) use ( &$checks ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
	++$checks;
};
$save = static function ( $id, $enabled ) {
	update_field( 'field_mrn_resource_use_provider_poster', $enabled, $id );
	do_action( 'acf/save_post', $id );
};
$id = $state['posts']['youtube'];
$manual = $state['attachments']['poster'];
foreach ( array( $id, $state['posts']['vimeo'] ) as $fixture_id ) {
	delete_post_meta( $fixture_id, '_mrn_resource_provider_poster' );
	delete_post_meta( $fixture_id, '_mrn_resource_provider_poster_active' );
	delete_post_meta( $fixture_id, '_mrn_resource_manual_thumbnail' );
	update_field( 'field_mrn_resource_use_provider_poster', 0, $fixture_id );
	update_field( 'field_mrn_resource_source', 'remote_video', $fixture_id );
	set_post_thumbnail( $fixture_id, $manual );
}
update_field( 'field_mrn_resource_video_remote', 'https://youtu.be/aqz-KE-bpKQ', $id );
$assert( ! get_field( 'resource_use_provider_poster', $id ), 'Unset toggle defaults off.' );
$count = (int) get_option( '_mrn_resource_poster_http_count', 0 );
$save( $id, 0 );
$assert( $manual === (int) get_post_thumbnail_id( $id ), 'Off preserves manual featured image.' );
$assert( $count === (int) get_option( '_mrn_resource_poster_http_count', 0 ), 'Off makes no provider requests.' );
$save( $id, 1 );
$auto = (int) get_post_thumbnail_id( $id );
$assert( $auto && $auto !== $manual && wp_attachment_is_image( $auto ), 'YouTube poster is a real image attachment.' );
$assert( wp_get_attachment_metadata( $auto )['width'] > 0, 'Native media metadata generated.' );
$assert( get_post_meta( $auto, '_wp_attachment_image_alt', true ) === get_the_title( $id ), 'Imported image has Resource title alt text.' );
$assert( str_contains( get_the_post_thumbnail( $id, 'medium_large' ), 'srcset=' ), 'Featured image supports responsive sources.' );
$limits = get_option( '_mrn_resource_poster_download_args' );
$assert( 8 === $limits['timeout'] && 0 === $limits['redirection'] && 5 * MB_IN_BYTES === $limits['limit_response_size'], 'Poster download has bounded time, size and redirects.' );
$count = (int) get_option( '_mrn_resource_poster_http_count' );
$save( $id, 1 );
$assert( $auto === (int) get_post_thumbnail_id( $id ), 'Repeat save reuses poster.' );
$assert( $count === (int) get_option( '_mrn_resource_poster_http_count' ), 'Cached poster needs no further HTTP requests.' );
$save( $id, 0 );
$assert( $manual === (int) get_post_thumbnail_id( $id ), 'Turning off restores manual image.' );
$assert( 'inherit' === get_post_field( 'post_status', $auto ), 'Turning off preserves imported attachment.' );
set_post_thumbnail( $id, $auto );
$save( $id, 0 );
$assert( $auto === (int) get_post_thumbnail_id( $id ), 'Off also permits manually selecting a previously imported provider attachment.' );
set_post_thumbnail( $id, $manual );
$save( $id, 1 );
$assert( $auto === (int) get_post_thumbnail_id( $id ), 'Turning on again reuses imported image.' );
delete_post_thumbnail( $id );
$save( $id, 1 );
$save( $id, 0 );
$assert( ! has_post_thumbnail( $id ), 'Removing manual selection is preserved when automatic mode is disabled.' );
set_post_thumbnail( $id, $manual );
$save( $id, 1 );
$new_url = 'https://www.youtube.com/embed/dQw4w9WgXcQ';
update_field( 'field_mrn_resource_video_remote', $new_url, $id );
$save( $id, 1 );
$changed = (int) get_post_thumbnail_id( $id );
$assert( $changed !== $auto && $changed !== $manual, 'Changed video fetches its own poster, including YouTube embed URL.' );
update_option( '_mrn_resource_poster_response', 'missing', false );
update_field( 'field_mrn_resource_video_remote', 'https://youtu.be/aaaaaaaaaaa', $id );
$save( $id, 1 );
$assert( $manual === (int) get_post_thumbnail_id( $id ), 'Unavailable new video restores manual image instead of the previous video poster.' );
foreach ( array( 'unsafe', 'wrong_host', 'bad_image', 'download_fail' ) as $mode ) {
	update_option( '_mrn_resource_poster_response', $mode, false );
	$save( $id, 1 );
	$assert( $manual === (int) get_post_thumbnail_id( $id ), $mode . ' preserves manual image.' );
}
update_option( '_mrn_resource_poster_response', 'valid', false );
foreach ( array( 'https://drive.google.com/file/d/resourcePosterFixture/preview', 'https://example.invalid/video.mp4', 'https://example.invalid/video.mp4?next=https://youtu.be/aqz-KE-bpKQ' ) as $url ) {
	update_field( 'field_mrn_resource_video_remote', $url, $id );
	$count = (int) get_option( '_mrn_resource_poster_http_count' );
	$save( $id, 1 );
	$assert( $manual === (int) get_post_thumbnail_id( $id ), 'Providers without poster metadata use manual image.' );
	$assert( $count === (int) get_option( '_mrn_resource_poster_http_count' ), 'Unsupported poster provider makes no HTTP requests.' );
}
$vimeo = $state['posts']['vimeo'];
$save( $vimeo, 1 );
$assert( $manual !== (int) get_post_thumbnail_id( $vimeo ) && wp_attachment_is_image( get_post_thumbnail_id( $vimeo ) ), 'Vimeo thumbnail imports through native media.' );
update_field( 'field_mrn_resource_source', 'file', $vimeo );
$save( $vimeo, 1 );
$assert( $manual === (int) get_post_thumbnail_id( $vimeo ), 'Switching to uploaded source restores manual image.' );
update_field( 'field_mrn_resource_video_remote', 'https://youtu.be/aqz-KE-bpKQ', $id );
$save( $id, 0 );
$count = (int) get_option( '_mrn_resource_poster_http_count' );
wp_set_current_user( 0 );
$save( $id, 1 );
$assert( $manual === (int) get_post_thumbnail_id( $id ), 'Unauthenticated user cannot alter featured image.' );
$assert( $count === (int) get_option( '_mrn_resource_poster_http_count' ), 'Unauthenticated save makes no provider request.' );
wp_set_current_user( $admin->ID );
update_field( 'field_mrn_resource_use_provider_poster', 0, $id );
// Exercise the existing authenticated WordPress REST update, after ACF writes.
rest_get_server();
$type = get_post_type_object( 'resource' );
$route = '/wp/v2/' . ( $type->rest_base ?: 'resource' ) . '/' . $id;
$request = new WP_REST_Request( 'POST', $route );
$request->set_body_params( array( 'acf' => array( 'resource_source' => 'remote_video', 'resource_video_remote' => 'https://youtu.be/ccccccccccc', 'resource_use_provider_poster' => true ) ) );
$response = rest_do_request( $request );
$assert( 200 === $response->get_status(), 'Authenticated existing Resource REST update succeeds.' );
$assert( get_field( 'resource_use_provider_poster', $id ) && 'https://youtu.be/ccccccccccc' === get_field( 'resource_video_remote', $id ), 'REST persists the requested ACF toggle and video URL.' );
$assert( $manual !== (int) get_post_thumbnail_id( $id ), 'REST update synchronizes the provider featured image after ACF saves.' );
$count = (int) get_option( '_mrn_resource_poster_http_count' );
mrn_base_stack_render_content_list_item( get_post( $id ), array( 'link_items' => 1, 'show_featured_image' => 1 ) );
$assert( $count === (int) get_option( '_mrn_resource_poster_http_count' ), 'Frontend rendering makes no provider requests.' );
wp_set_current_user( 0 );
$request = new WP_REST_Request( 'POST', $route );
$request->set_body_params( array( 'acf' => array( 'resource_use_provider_poster' => false ) ) );
$assert( rest_do_request( $request )->get_status() >= 400, 'Existing REST authorization rejects unauthenticated updates.' );
wp_set_current_user( $admin->ID );
$save( $id, 0 );
update_field( 'field_mrn_resource_source', 'remote_video', $vimeo );
$save( $vimeo, 0 );
echo wp_json_encode( array( 'status' => 'pass', 'checks' => $checks, 'provider_responses' => 'explicit offline fixtures; not a provider availability claim' ) );
