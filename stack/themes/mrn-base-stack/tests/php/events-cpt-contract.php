<?php
// phpcs:ignoreFile -- Standalone WordPress stub harness for the Events CPT contract.
/**
 * Regression coverage for the Events CPT and ACF field model.
 *
 * @package mrn-base-stack
 */

define( 'ABSPATH', __DIR__ );

$GLOBALS['mrn_events_test_registered_post_types'] = array();
$GLOBALS['mrn_events_test_field_groups']          = array();

function add_action( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
	unset( $hook_name, $callback, $priority, $accepted_args );

	return true;
}

function __( $text, $domain = 'default' ) {
	unset( $domain );

	return $text;
}

function register_post_type( $post_type, $args ) {
	$GLOBALS['mrn_events_test_registered_post_types'][ $post_type ] = $args;
}

function acf_add_local_field_group( $field_group ) {
	$GLOBALS['mrn_events_test_field_groups'][ $field_group['key'] ] = $field_group;
}

function mrn_events_test_assert( $condition, $message ) {
	if ( $condition ) {
		return;
	}

	fwrite( STDERR, "FAIL: {$message}\n" );
	exit( 1 );
}

/**
 * Find a field by name in a flat field list.
 *
 * @param array<int, array<string, mixed>> $fields Fields to search.
 * @param string                           $name   Field name.
 * @return array<string, mixed>|null
 */
function mrn_events_test_find_field( array $fields, $name ) {
	foreach ( $fields as $field ) {
		if ( $name === ( $field['name'] ?? '' ) ) {
			return $field;
		}
	}

	return null;
}

require dirname( __DIR__, 2 ) . '/inc/events.php';

mrn_base_stack_register_event_post_type();
mrn_base_stack_register_event_field_group();

$event_args_raw = $GLOBALS['mrn_events_test_registered_post_types']['event'] ?? null;
mrn_events_test_assert( is_array( $event_args_raw ), 'Event CPT must register.' );
/** @var array<string, mixed> $event_args */
$event_args = is_array( $event_args_raw ) ? $event_args_raw : array();
mrn_events_test_assert( true === ( $event_args['public'] ?? false ), 'Event CPT must be public.' );
mrn_events_test_assert( true === ( $event_args['show_in_rest'] ?? false ), 'Event CPT must be available to the REST API.' );
mrn_events_test_assert( 'events' === ( $event_args['rewrite']['slug'] ?? '' ), 'Event CPT must use the events rewrite slug.' );

$supports = $event_args['supports'] ?? array();
mrn_events_test_assert( in_array( 'title', $supports, true ), 'Event CPT must use the standard title field.' );
mrn_events_test_assert( in_array( 'editor', $supports, true ), 'Event CPT must use the standard body editor.' );
mrn_events_test_assert( in_array( 'revisions', $supports, true ), 'Event CPT must support revisions.' );

$field_group_raw = $GLOBALS['mrn_events_test_field_groups']['group_mrn_event'] ?? null;
mrn_events_test_assert( is_array( $field_group_raw ), 'Event Details ACF field group must register.' );
/** @var array<string, mixed> $field_group */
$field_group = is_array( $field_group_raw ) ? $field_group_raw : array();
mrn_events_test_assert( 1 === ( $field_group['show_in_rest'] ?? 0 ), 'Event fields must be available to the REST API.' );

$location_rules = is_array( $field_group['location'] ?? null ) ? $field_group['location'] : array();
$location_group = is_array( $location_rules[0] ?? null ) ? $location_rules[0] : array();
$location_rule  = is_array( $location_group[0] ?? null ) ? $location_group[0] : array();
mrn_events_test_assert( 'event' === ( $location_rule['value'] ?? '' ), 'Event fields must target the Event CPT.' );

$fields = $field_group['fields'] ?? array();

foreach ( array( 'event_start_date', 'event_end_date', 'event_image', 'event_image_link', 'event_location_type', 'event_location_text', 'event_location_record', 'event_booth', 'event_sponsors' ) as $field_name ) {
	mrn_events_test_assert( null !== mrn_events_test_find_field( $fields, $field_name ), "Event field {$field_name} must register." );
}

$start_date = mrn_events_test_find_field( $fields, 'event_start_date' );
$end_date   = mrn_events_test_find_field( $fields, 'event_end_date' );
mrn_events_test_assert( 'date_time_picker' === ( $start_date['type'] ?? '' ), 'Start date must include date and time.' );
mrn_events_test_assert( 1 === ( $start_date['required'] ?? 0 ), 'Start date must be required.' );
mrn_events_test_assert( 'date_time_picker' === ( $end_date['type'] ?? '' ), 'End date must include date and time.' );

$event_image      = mrn_events_test_find_field( $fields, 'event_image' );
$event_image_link = mrn_events_test_find_field( $fields, 'event_image_link' );
mrn_events_test_assert( 'image' === ( $event_image['type'] ?? '' ), 'Event image must use an ACF image field.' );
mrn_events_test_assert( 'link' === ( $event_image_link['type'] ?? '' ), 'Event image destination must use an ACF link field.' );

$location_record = mrn_events_test_find_field( $fields, 'event_location_record' );
mrn_events_test_assert( array( 'location' ) === ( $location_record['post_type'] ?? array() ), 'Linked location must be limited to Location records.' );
mrn_events_test_assert( 'id' === ( $location_record['return_format'] ?? '' ), 'Linked location must store a post ID.' );

$sponsors = mrn_events_test_find_field( $fields, 'event_sponsors' );
mrn_events_test_assert( 'repeater' === ( $sponsors['type'] ?? '' ), 'Sponsors must be repeatable.' );

$sponsor_fields = $sponsors['sub_fields'] ?? array();
mrn_events_test_assert( 'text' === ( mrn_events_test_find_field( $sponsor_fields, 'text' )['type'] ?? '' ), 'Each sponsor must support text.' );
mrn_events_test_assert( 'image' === ( mrn_events_test_find_field( $sponsor_fields, 'image' )['type'] ?? '' ), 'Each sponsor must support an image.' );
mrn_events_test_assert( 'link' === ( mrn_events_test_find_field( $sponsor_fields, 'link' )['type'] ?? '' ), 'Each sponsor must support a link.' );

echo "PASS: Events CPT and ACF field contract are registered.\n";
