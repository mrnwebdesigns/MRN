<?php
/** Restore only the reported review administrator's accidentally hidden panels. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || 'admin-editor-pilot.mrndev.io' !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
	throw new RuntimeException( 'Wrong review target.' );
}
$review_user = get_user_by( 'login', 'mrn-admin' );
if ( ! $review_user || ! user_can( $review_user, 'manage_options' ) ) {
	throw new RuntimeException( 'Expected review administrator unavailable.' );
}
$key = 'metaboxhidden_page';
$before = get_user_meta( $review_user->ID, $key, true );
if ( ! is_array( $before ) ) {
	throw new RuntimeException( 'Unexpected panel preferences; refusing to replace them.' );
}
$restore = array( 'postimagediv', 'pageparentdiv', 'authordiv', 'mrn-layout-import', 'seopress_metabox_opener' );
$after = array_values( array_diff( $before, $restore ) );
if ( $before !== $after ) {
	update_user_meta( $review_user->ID, $key, $after );
}
if ( get_user_meta( $review_user->ID, $key, true ) !== $after ) {
	throw new RuntimeException( 'Panel preference readback failed.' );
}
echo wp_json_encode( array( 'user' => $review_user->user_login, 'key' => $key, 'before' => $before, 'after' => $after, 'restored' => array_values( array_intersect( $before, $restore ) ) ), JSON_PRETTY_PRINT ) . "\n";
