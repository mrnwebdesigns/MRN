<?php
// phpcs:ignoreFile -- Owned fixtures in a disposable loopback WordPress database.
/** Resource video integration coverage; run through wp eval-file setup|check. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || 'local' !== wp_get_environment_type() || ! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( '127.0.0.1', 'localhost' ), true ) ) {
	throw new RuntimeException( 'Resource video qualification requires a disposable local loopback runtime.' );
}
$state_path = getenv( 'MRN_RESOURCE_VIDEO_STATE' );
if ( ! $state_path || '/' !== $state_path[0] ) {
	throw new RuntimeException( 'Set a private absolute MRN_RESOURCE_VIDEO_STATE path.' );
}
function mrn_resource_video_assert( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}
if ( 'setup' === ( $args[0] ?? 'check' ) ) {
	mrn_resource_video_assert( ! file_exists( $state_path ), 'Refuse to overwrite an existing fixture.' );
	$state = array( 'posts' => array(), 'attachments' => array() );
	foreach ( array( 'video' => 'video/mp4', 'poster' => 'image/png' ) as $key => $mime ) {
		$source = getenv( 'MRN_RESOURCE_VIDEO_' . strtoupper( $key ) );
		mrn_resource_video_assert( $source && is_file( $source ), 'Missing local fixture media: ' . $key );
		$upload = wp_upload_bits( 'mrn-resource-video-' . basename( $source ), null, file_get_contents( $source ) );
		mrn_resource_video_assert( empty( $upload['error'] ), 'Fixture media upload failed.' );
		$id = wp_insert_attachment( array( 'post_title' => 'Resource video fixture ' . $key, 'post_mime_type' => $mime ), $upload['file'], 0, true );
		mrn_resource_video_assert( ! is_wp_error( $id ), 'Fixture attachment failed.' );
		$state['attachments'][ $key ] = $id;
		update_post_meta( $id, '_mrn_resource_video_fixture', '1' );
		if ( 'poster' === $key ) {
			update_post_meta( $id, '_wp_attachment_image_alt', 'Resource preview' );
			require_once ABSPATH . 'wp-admin/includes/image.php';
			wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $upload['file'] ) );
		}
	}
	foreach ( array( 'local', 'remote', 'youtube', 'vimeo', 'drive', 'missing', 'pdf' ) as $key ) {
		$id = wp_insert_post( array( 'post_type' => 'resource', 'post_status' => 'publish', 'post_title' => 'Resource video ' . $key, 'post_excerpt' => 'Video resource qualification.' ), true );
		mrn_resource_video_assert( ! is_wp_error( $id ), 'Fixture Resource failed.' );
		$state['posts'][ $key ] = $id;
		update_post_meta( $id, '_mrn_resource_video_fixture', '1' );
		if ( 'pdf' !== $key ) {
			set_post_thumbnail( $id, $state['attachments']['poster'] );
		}
	}
	update_field( 'field_mrn_resource_file', $state['attachments']['video'], $state['posts']['local'] );
	// No source metadata on the uploaded Resource: prove retained file compatibility.
	$state['remote_url'] = str_replace( '127.0.0.1', 'localhost', wp_get_attachment_url( $state['attachments']['video'] ) );
	foreach ( array( 'remote' => $state['remote_url'], 'youtube' => 'https://youtu.be/aqz-KE-bpKQ', 'vimeo' => 'https://vimeo.com/76979871', 'drive' => 'https://drive.google.com/file/d/resourceVideoFixture/preview' ) as $key => $url ) {
		update_field( 'field_mrn_resource_source', 'remote_video', $state['posts'][ $key ] );
		update_field( 'field_mrn_resource_video_remote', $url, $state['posts'][ $key ] );
		// Retained upload must not leak through when the selected source changes.
		update_field( 'field_mrn_resource_file', $state['attachments']['video'], $state['posts'][ $key ] );
	}
	update_field( 'field_mrn_resource_source', 'remote_video', $state['posts']['missing'] );
	$pdf = wp_upload_bits( 'mrn-resource-video.pdf', null, "%PDF-1.4\n%%EOF\n" );
	$state['attachments']['pdf'] = wp_insert_attachment( array( 'post_title' => 'Resource PDF', 'post_mime_type' => 'application/pdf' ), $pdf['file'] );
	update_field( 'field_mrn_resource_file', $state['attachments']['pdf'], $state['posts']['pdf'] );
	$state['page'] = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Resource Video Qualification', 'post_name' => 'resource-video-qualification' ) );
	update_post_meta( $state['page'], '_mrn_resource_video_fixture', '1' );
	$row = array( 'acf_fc_layout' => 'content_lists', 'heading' => 'Video Resources', 'heading_tag' => 'h2', 'list_post_type' => 'resource', 'filter_source' => 'manual_posts', 'filter_posts' => array_values( $state['posts'] ), 'posts_per_page' => 20, 'link_items' => 1, 'show_featured_image' => 1, 'show_excerpt' => 1, 'show_read_more' => 1, 'read_more_label' => 'View Resource' );
	$video_row = array( 'acf_fc_layout' => 'video', 'heading' => 'Stack Video Row', 'video_upload' => $state['attachments']['video'], 'video_display_mode' => 'modal', 'video_thumbnail' => $state['attachments']['poster'] );
	update_field( 'field_mrn_page_content_rows', array( $video_row, $row ), $state['page'] );
	$state['url'] = get_permalink( $state['page'] );
	file_put_contents( $state_path, wp_json_encode( $state ) );
	echo wp_json_encode( array( 'page' => $state['page'], 'url' => $state['url'] ) );
	return;
}
$state = json_decode( file_get_contents( $state_path ), true );
$checks = 0;
$assert = static function ( $condition, $message ) use ( &$checks ) {
	mrn_resource_video_assert( $condition, $message );
	++$checks;
};
$resource = get_post_type_object( 'resource' );
$assert( ! $resource->public && ! $resource->publicly_queryable && ! $resource->has_archive, 'Resource stays Content Only.' );
foreach ( array( 'local', 'remote', 'youtube', 'vimeo', 'drive' ) as $key ) {
	$post = get_post( $state['posts'][ $key ] );
	$video = mrn_base_stack_get_resource_video( $post->ID );
	$assert( ! empty( $video['url'] ), $key . ' resolves a video.' );
	$assert( ( 'drive' === $key ? 'external' : 'video' ) === $video['type'], $key . ' has the correct modal type.' );
	$markup = mrn_base_stack_render_content_list_item( $post, array( 'link_items' => 1, 'show_featured_image' => 1, 'show_read_more' => 1 ) );
	$assert( 3 === substr_count( $markup, 'data-mrn-video-modal="1"' ), $key . ' poster, title and CTA open the modal.' );
	$assert( str_contains( $markup, 'mrn-video-row__play' ) && str_contains( $markup, 'aria-label="Play video:' ), $key . ' has a play overlay and accessible link name.' );
	$off = mrn_base_stack_render_content_list_item( $post, array( 'link_items' => 0, 'show_featured_image' => 1 ) );
	$assert( ! str_contains( $off, '<a ' ) && ! str_contains( $off, 'mrn-video-row__play' ), $key . ' respects row links off.' );
	$title_only = mrn_base_stack_render_content_list_item( $post, array( 'link_items' => 1, 'display_mode' => 'title_only' ) );
	$assert( str_contains( $title_only, 'data-mrn-video-modal="1"' ), $key . ' title-only mode opens the modal without a poster.' );
}
$assert( null === mrn_base_stack_get_resource_file( $state['posts']['remote'] ), 'Remote mode ignores retained upload.' );
$assert( '' === mrn_base_stack_get_content_list_item_permalink( get_post( $state['posts']['missing'] ) ), 'Missing remote video stays unlinked.' );
$pdf = mrn_base_stack_render_content_list_item( get_post( $state['posts']['pdf'] ), array( 'link_items' => 1 ) );
$assert( str_contains( $pdf, 'target="_blank"' ) && ! str_contains( $pdf, 'data-mrn-video-modal' ), 'PDF behavior remains unchanged.' );
foreach ( array( 'javascript:alert(1)', 'https://youtube.com.example.invalid/watch?v=aqz-KE-bpKQ', 'https://example.invalid/?next=https://youtube.com/watch?v=aqz-KE-bpKQ', 'https://example.invalid/page', 'ftp://example.invalid/video.mp4' ) as $url ) {
	$assert( ! mrn_base_stack_get_resource_remote_video( $url ), 'Unsupported or unsafe remote URL rejected.' );
	$assert( true !== mrn_base_stack_validate_resource_remote_video( true, $url ), 'Editor validation rejects unsupported URL.' );
}
$assert( mrn_base_stack_get_resource_remote_video( 'https://cdn.example.invalid/movie.webm?token=test' ), 'Direct video URL supports query parameters.' );
$assert( true === mrn_base_stack_validate_resource_remote_video( true, 'https://www.youtube.com/shorts/aqz-KE-bpKQ' ), 'YouTube Shorts supported.' );
$assert( ! empty( mrn_base_stack_get_front_end_component_needs_for_post( $state['page'] )['video_modal'] ), 'Resource lists declare modal assets before wp_head.' );
echo wp_json_encode( array( 'checks' => $checks, 'status' => 'pass' ) );
