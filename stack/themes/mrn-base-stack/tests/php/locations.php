<?php
// phpcs:ignoreFile -- Standalone WordPress/ACF stub harness.
/**
 * Location inheritance, custom-value retention and stable Business Information fields.
 *
 * @package mrn-base-stack
 */

define( 'ABSPATH', __DIR__ );

function add_action( ...$args ) {}
function add_filter( ...$args ) {}
function __( $text, $domain = '' ) { return $text; }
function absint( $value ) { return abs( (int) $value ); }
function wp_parse_args( $args, $defaults ) { return array_merge( $defaults, $args ); }
function get_post( $id ) { return 12 === $id ? (object) array( 'ID' => 12, 'post_type' => 'location' ) : null; }
function get_field( $name, $context = false ) { return $GLOBALS['fields'][ $context ][ $name ] ?? false; }
function acf_add_local_field_group( $group ) { $GLOBALS['groups'][ $group['key'] ] = $group; }
function mrn_config_helper_get_social_links() { return array( array( 'name' => 'Shared profile', 'url' => 'https://example.test/shared' ) ); }
function check( $condition, $message ) {
	if ( ! $condition ) { throw new RuntimeException( $message ); }
}

require dirname( __DIR__, 2 ) . '/inc/theme-options.php';
require dirname( __DIR__, 2 ) . '/inc/locations.php';

$GLOBALS['fields'] = array(
	'option' => array(
		'phone' => '9195551111', 'text_phone' => '9195552222', 'fax' => '9195553333',
		'hours_monday_open' => '8:00 am', 'hours_monday_close' => '4:00 pm',
	),
	12 => array(
		'location_address' => array( 'city' => 'Raleigh' ), 'location_image' => 42,
		'location_phone' => '9195554444', 'location_text_phone' => '9195555555', 'location_fax' => '9195556666',
		'location_hours_monday_open' => '9:00 am', 'location_hours_monday_close' => '5:00 pm',
		'location_social_links' => array( array( 'name' => 'Local profile', 'url' => 'https://example.test/local' ) ),
	),
);
$saved = $GLOBALS['fields'][12];
$local = mrn_base_stack_get_location_information( 12 );
check( '(919) 555-4444' === $local['phone'] && 'tel:9195554444' === $local['phone_uri'], 'Custom phone must resolve and format.' );
check( '(919) 555-5555' === $local['text_phone'] && '(919) 555-6666' === $local['fax'], 'Text and fax must remain independent.' );
check( '9:00 am' === $local['business_hours']['monday']['open'], 'Custom hours must resolve.' );
check( 'Local profile' === $local['social_links'][0]['name'], 'Custom social profiles must resolve.' );
check( 'Raleigh' === $local['address']['city'] && 42 === $local['image'], 'Location and image must remain location-owned.' );

foreach ( array( 'hours', 'social_links', 'phone', 'text_phone', 'fax' ) as $name ) {
	$GLOBALS['fields'][12]['location_use_business_' . $name] = 1;
}
$shared = mrn_base_stack_get_location_information( 12 );
check( '(919) 555-1111' === $shared['phone'] && '(919) 555-2222' === $shared['text_phone'] && '(919) 555-3333' === $shared['fax'], 'Each shared contact must resolve from Business Information.' );
check( '8:00 am' === $shared['business_hours']['monday']['open'], 'Shared hours must resolve.' );
check( 'Shared profile' === $shared['social_links'][0]['name'], 'Shared social profiles must use Config Helper.' );
check( $local['address'] === $shared['address'] && $local['image'] === $shared['image'], 'Inheritance must not change location or image.' );
foreach ( $saved as $name => $value ) {
	check( $value === $GLOBALS['fields'][12][$name], 'Reading inherited content must not overwrite saved custom values.' );
}
$GLOBALS['fields'][12]['location_use_business_phone'] = 0;
$mixed = mrn_base_stack_get_location_information( 12 );
check( $local['phone'] === $mixed['phone'] && $shared['fax'] === $mixed['fax'], 'Inheritance controls must be independent and reversible.' );
$GLOBALS['fields'][12] = $saved;
check( $local === mrn_base_stack_get_location_information( 12 ), 'Turning inheritance off must recover custom values.' );
check( '' === mrn_base_stack_get_location_information( 99 )['phone'], 'Invalid records must not disclose global values.' );
check( true !== mrn_base_stack_validate_business_phone_field( true, '123', array() ), 'Contact validation must reject incomplete numbers.' );
check( true === mrn_base_stack_validate_business_phone_field( true, '(919) 555-1234', array() ), 'Formatted contact numbers must validate.' );

mrn_base_stack_register_location_field_group();
$group = $GLOBALS['groups']['group_mrn_location'];
check( 'location' === $group['location'][0][0]['value'], 'Location fields must be scoped to Locations.' );
$fields = array_column( $group['fields'], null, 'key' );
foreach ( array( 'hours', 'social_links', 'phone', 'text_phone', 'fax' ) as $name ) {
	$toggle = $fields['field_mrn_location_use_business_' . $name];
	check( 0 === $toggle['default_value'], 'Inheritance must be explicit opt-in.' );
	check( 'true_false' === $toggle['type'] && 0 === $toggle['ui'], 'Use a native checkbox.' );
}
foreach ( mrn_base_stack_get_business_hours_fields() as $index => $field ) {
	$custom = mrn_base_stack_get_business_hours_fields( 'location' )[$index];
	check( $field['type'] === $custom['type'] && $field['wrapper'] === $custom['wrapper'], 'Both hours editors must share their field layout.' );
	check( 'field_mrn_business_hours_' === substr( $field['key'], 0, 25 ), 'Business Information field keys must remain stable.' );
	check( isset( $fields[$custom['key']]['conditional_logic'] ), 'Every custom hours control must follow the checkbox.' );
}
check( 'image' === $fields['field_mrn_location_image']['type'], 'Image must use Media Library integration.' );
echo "PASS: Location fields, independent inheritance and retained custom values.\n";
