<?php
// phpcs:ignoreFile -- Local-only real WordPress/ACF integration fixture.
/**
 * Run with wp eval-file .../event-content-links.php in an isolated local runtime.
 * Creates only fixture-owned records and removes them after the checks.
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI || 'local' !== wp_get_environment_type() || ! str_ends_with( (string) wp_parse_url( home_url(), PHP_URL_HOST ), '.localhost' ) ) {
	throw new RuntimeException( 'Requires an isolated local .localhost WordPress runtime.' );
}

function mrn_event_links_assert( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
	echo 'PASS: ' . $message . PHP_EOL;
}

$event_id = wp_insert_post( array( 'post_type' => 'event', 'post_status' => 'publish', 'post_title' => 'Event link regression fixture' ), true );
if ( is_wp_error( $event_id ) ) {
	throw new RuntimeException( $event_id->get_error_message() );
}
$event = get_post( $event_id );
$type = get_post_type_object( 'event' );
$was_queryable = $type->publicly_queryable;
$destination = 'https://example.org/conference/?track=energy&year=2026';

try {
	$type->publicly_queryable = false;
	mrn_event_links_assert( mrn_base_stack_get_content_list_link_support_map()['event'], 'Content Only Events are eligible in the editor and renderer' );
	mrn_event_links_assert( '' === mrn_base_stack_get_content_list_item_permalink( $event ), 'Content Only Event without a destination stays unlinked' );
	update_field( 'field_mrn_event_image_link', array( 'url' => $destination, 'title' => 'Conference website', 'target' => '_blank' ), $event_id );
	mrn_event_links_assert( $destination === get_field( 'event_image_link', $event_id )['url'], 'The existing ACF field saves and reloads the destination' );
	mrn_event_links_assert( $destination === mrn_base_stack_get_content_list_item_permalink( $event, array( 'link_items' => 1 ) ), 'Content Only Event resolves its saved external destination' );
	$attributes = mrn_base_stack_get_content_list_item_link_attributes( $event );
	mrn_event_links_assert( str_contains( $attributes, 'target="_blank"' ) && str_contains( $attributes, 'noopener' ), 'New-tab preference includes noopener' );
	foreach ( array( false, 0, '0' ) as $off ) {
		mrn_event_links_assert( '' === mrn_base_stack_get_content_list_item_permalink( $event, array( 'link_items' => $off ) ), 'Row link-off preference takes precedence' );
	}
	foreach ( array( 1, 0 ) as $enabled ) {
		$html = mrn_base_stack_render_content_list_item( $event, array( 'link_items' => $enabled, 'show_read_more' => true ) );
		mrn_event_links_assert( (bool) $enabled === str_contains( $html, 'href="' . esc_url( $destination ) . '"' ), 'Real title/read-more markup respects link setting ' . $enabled );
		mrn_event_links_assert( ! str_contains( $html, 'href=""' ), 'Renderer never creates empty links' );
	}
	update_field( 'field_mrn_event_image_link', array( 'url' => $destination, 'target' => '' ), $event_id );
	mrn_event_links_assert( '' === mrn_base_stack_get_content_list_item_link_attributes( $event ), 'Same-tab preference is preserved' );
	update_field( 'field_mrn_event_image_link', array( 'url' => $destination, 'target' => '" onmouseover="alert(1)' ), $event_id );
	mrn_event_links_assert( '' === mrn_base_stack_get_content_list_item_link_attributes( $event ), 'Invalid target attributes are rejected' );
	foreach ( array( array( 'url' => 'javascript:alert(1)' ), array( 'url' => 'data:text/html,unsafe' ), array( 'url' => '' ), array( 'url' => array() ), 'invalid' ) as $invalid ) {
		update_post_meta( $event_id, 'event_image_link', $invalid );
		acf_flush_value_cache( $event_id, 'event_image_link' );
		mrn_event_links_assert( '' === mrn_base_stack_get_content_list_item_permalink( $event ), 'Unsafe, missing, or malformed Content Only destinations remain unlinked' );
	}
	$type->publicly_queryable = true;
	mrn_event_links_assert( get_permalink( $event ) === mrn_base_stack_get_content_list_item_permalink( $event ), 'Public Event without a saved link retains its normal permalink' );
	update_field( 'field_mrn_event_image_link', array( 'url' => $destination, 'target' => '_blank' ), $event_id );
	mrn_event_links_assert( $destination === mrn_base_stack_get_content_list_item_permalink( $event ), 'A saved Event destination also works for public Events' );
	$other = clone $event;
	$other->post_type = 'post';
	mrn_event_links_assert( '/unchanged/' === mrn_base_stack_filter_event_content_list_permalink( '/unchanged/', $other ), 'Other post types keep their own destination' );
	mrn_event_links_assert( array( 'rel' => 'nofollow' ) === mrn_base_stack_filter_event_content_list_link_attributes( array( 'rel' => 'nofollow' ), $other ), 'Other post types keep their link attributes' );
	$attributes = mrn_base_stack_filter_event_content_list_link_attributes( array( 'rel' => 'nofollow' ), $event );
	mrn_event_links_assert( 'nofollow noopener' === $attributes['rel'], 'Existing relationship attributes survive the Event new-tab preference' );
} finally {
	$type->publicly_queryable = $was_queryable;
	wp_delete_post( $event_id, true );
}

echo 'Event Reference Content runtime regression checks passed.' . PHP_EOL;
