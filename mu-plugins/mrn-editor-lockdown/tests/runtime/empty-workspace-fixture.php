<?php
/** Isolated, reusable empty-page fixture for the approved Local Hub review. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || 'admin-editor-pilot.localhost' !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
	throw new RuntimeException( 'Only the authorized Local Hub fixture is supported.' );
}
$mode = $args[0] ?? 'readback';
$id   = (int) get_option( 'mrn_editor_round2_empty_fixture', 0 );
if ( 'setup' === $mode && ! $id ) {
	$id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'Local pilot — Empty page workflow' ), true );
	if ( is_wp_error( $id ) ) {
		throw new RuntimeException( esc_html( $id->get_error_message() ) );
	}
	update_post_meta( $id, '_mrn_round2_fixture', 'empty-workspace' );
	update_option( 'mrn_editor_round2_empty_fixture', $id, false );
}
if ( ! $id || 'empty-workspace' !== get_post_meta( $id, '_mrn_round2_fixture', true ) || 'draft' !== get_post_status( $id ) || 'page' !== get_post_type( $id ) ) {
	throw new RuntimeException( 'Expected owned draft fixture is unavailable; refusing to continue.' );
}
$counts = array();
foreach ( array( 'page_hero_rows', 'page_content_rows', 'page_after_content_rows' ) as $name ) {
	$value           = get_field( $name, $id, false );
	$counts[ $name ] = is_array( $value ) ? count( $value ) : 0;
}
echo wp_json_encode( array( 'id' => $id, 'status' => get_post_status( $id ), 'counts' => $counts, 'acf_hash' => hash( 'sha256', wp_json_encode( get_fields( $id, false ) ) ) ), JSON_PRETTY_PRINT ) . "\n";
