<?php
/** Isolated drafts for native/core and ACF content-routing save verification. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( 'admin-editor-pilot.localhost', 'admin-editor-pilot.mrndev.io' ), true ) ) {
	throw new RuntimeException( 'Only the authorized pilot fixtures are supported.' );
}
$mode = $args[0] ?? 'readback';
$ids  = get_option( 'mrn_editor_content_routing_fixtures', array() );
foreach ( array( 'team_member', 'post' ) as $type ) {
	if ( 'setup' === $mode && empty( $ids[ $type ] ) ) {
		$id = wp_insert_post( array( 'post_type' => $type, 'post_status' => 'draft', 'post_title' => 'Review fixture — Content routing — ' . $type ), true );
		if ( is_wp_error( $id ) ) {
			throw new RuntimeException( esc_html( $id->get_error_message() ) );
		}
		update_post_meta( $id, '_mrn_content_routing_fixture', $type );
		$ids[ $type ] = $id;
		update_option( 'mrn_editor_content_routing_fixtures', $ids, false );
	}
	$id = (int) ( $ids[ $type ] ?? 0 );
	if ( ! $id || $type !== get_post_meta( $id, '_mrn_content_routing_fixture', true ) || 'draft' !== get_post_status( $id ) || $type !== get_post_type( $id ) ) {
		throw new RuntimeException( 'Expected owned draft fixture is unavailable; refusing to continue.' );
	}
}
$report = array();
foreach ( $ids as $type => $id ) {
	$report[ $type ] = array(
		'id' => (int) $id,
		'status' => get_post_status( $id ),
		'content' => get_post_field( 'post_content', $id ),
		'excerpt' => get_post_field( 'post_excerpt', $id ),
		'job_title' => get_post_meta( $id, 'team_member_position', true ),
		'bio' => get_post_meta( $id, 'team_member_bio', true ),
	);
}
echo wp_json_encode( $report, JSON_PRETTY_PRINT ) . "\n";
