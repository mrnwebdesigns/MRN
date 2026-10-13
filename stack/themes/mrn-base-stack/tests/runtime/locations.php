<?php
/**
 * Real ACF persistence checks on a disposable loopback qualification database.
 *
 * @package mrn-base-stack
 */

if ( ! defined( 'MRN_LOCATIONS_FIXTURE' ) || true !== MRN_LOCATIONS_FIXTURE || 'local' !== wp_get_environment_type() || '127.0.0.1' !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
	throw new RuntimeException( 'Locations fixture requires a disposable loopback runtime.' );
}
if ( ! function_exists( 'update_field' ) || ! function_exists( 'mrn_base_stack_get_location_information' ) ) {
	throw new RuntimeException( 'Candidate parent theme and ACF are required.' );
}

$mrn_location_id = wp_insert_post( array( 'post_type' => 'location', 'post_status' => 'draft', 'post_title' => 'Locations qualification' ), true );
if ( is_wp_error( $mrn_location_id ) ) {
	throw new RuntimeException( 'Could not create the disposable location.' );
}
update_post_meta( $mrn_location_id, '_mrn_locations_fixture', '1' );
update_field( 'field_mrn_location_phone', '9195554444', $mrn_location_id );
update_field( 'field_mrn_location_text_phone', '9195555555', $mrn_location_id );
update_field( 'field_mrn_location_fax', '9195556666', $mrn_location_id );
update_field( 'field_mrn_location_hours_monday_open', '09:00:00', $mrn_location_id );
update_field( 'field_mrn_location_hours_monday_close', '17:00:00', $mrn_location_id );
update_field( 'field_mrn_location_address', array( 'line_1' => '123 Main Street', 'city' => 'Raleigh', 'state' => 'NC' ), $mrn_location_id );
update_field( 'field_mrn_location_social_links', array( array( 'name' => 'Local profile', 'url' => 'https://example.test/local' ) ), $mrn_location_id );

$mrn_local = mrn_base_stack_get_location_information( $mrn_location_id );
if ( '(919) 555-4444' !== $mrn_local['phone'] || '9:00 am' !== $mrn_local['business_hours']['monday']['open'] || 'Raleigh' !== $mrn_local['address']['city'] || 'Local profile' !== $mrn_local['social_links'][0]['name'] ) {
	throw new RuntimeException( 'Custom Location ACF values did not persist.' );
}
foreach ( array( 'hours', 'phone', 'text_phone', 'fax' ) as $mrn_name ) {
	update_field( 'field_mrn_location_use_business_' . $mrn_name, 1, $mrn_location_id );
}
$mrn_shared = mrn_base_stack_get_location_information( $mrn_location_id );
if ( '(919) 555-1111' !== $mrn_shared['phone'] || '(919) 555-2222' !== $mrn_shared['text_phone'] || '(919) 555-3333' !== $mrn_shared['fax'] || mrn_base_stack_get_business_information()['business_hours'] !== $mrn_shared['business_hours'] ) {
	throw new RuntimeException( 'Inherited Location ACF values did not resolve.' );
}
foreach ( array( 'hours', 'phone', 'text_phone', 'fax' ) as $mrn_name ) {
	update_field( 'field_mrn_location_use_business_' . $mrn_name, 0, $mrn_location_id );
}
if ( $mrn_local !== mrn_base_stack_get_location_information( $mrn_location_id ) ) {
	throw new RuntimeException( 'Custom values were lost when inheritance was enabled.' );
}

echo wp_json_encode( array( 'status' => 'pass', 'location_id' => $mrn_location_id, 'fields' => 'actual ACF values and field keys', 'custom_and_inherited' => true ) );
