<?php
// phpcs:ignoreFile -- Standalone WordPress/ACF stub harness for business-information contracts.
/**
 * Focused regression coverage for request-scoped business-information memoization.
 *
 * Run with:
 * php stack/themes/mrn-base-stack/tests/php/business-information-memoization.php
 *
 * @package mrn-base-stack
 */

define( 'ABSPATH', __DIR__ );

$request_value = getenv( 'MRN_BUSINESS_TEST_VALUE' );
$request_value = false === $request_value || '' === $request_value ? 'Alpha' : $request_value;

$GLOBALS['mrn_business_test_get_field_calls'] = 0;
$GLOBALS['mrn_business_test_fields']          = array(
	'business_profile'      => '<p>' . $request_value . ' profile</p>',
	'years_in_business'     => '12',
	'logo'                  => array( 'url' => 'https://example.test/header.png' ),
	'logo_inverted'         => array( 'url' => 'https://example.test/header-inverted.png' ),
	'logo_footer'           => array( 'url' => 'https://example.test/footer.png' ),
	'logo_footer_inverted'  => array( 'url' => 'https://example.test/footer-inverted.png' ),
	'phone'                 => '9195551234',
	'text_phone'            => '9195559876',
	'address_line_1'        => '123 Main Street',
	'address_line_2'        => 'Suite 4',
	'address_city'          => 'Raleigh',
	'address_state'         => 'NC',
	'address_postal_code'   => '27601',
	'address_country'       => 'US',
	'hours_monday_open'     => '9:00 am',
	'hours_monday_close'    => '5:00 pm',
	'hours_tuesday_open'    => '',
	'hours_tuesday_close'   => '',
	'hours_wednesday_open'  => '',
	'hours_wednesday_close' => '',
	'hours_thursday_open'   => '',
	'hours_thursday_close'  => '',
	'hours_friday_open'     => '',
	'hours_friday_close'    => '',
	'holiday_hours'         => array(),
	'schema_organization_type' => 'LocalBusiness',
	'schema_legal_name'        => 'Alpha LLC',
	'schema_alternate_name'    => 'Alpha',
	'schema_email'             => 'hello@example.test',
	'schema_area_served'       => 'Triangle',
	'schema_latitude'          => '35.7796',
	'schema_longitude'         => '-78.6382',
);

function add_action( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
	return true;
}

function add_filter( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
	return true;
}

function get_field( $field_name, $context = false ) {
	++$GLOBALS['mrn_business_test_get_field_calls'];

	return array_key_exists( $field_name, $GLOBALS['mrn_business_test_fields'] ) ? $GLOBALS['mrn_business_test_fields'][ $field_name ] : null;
}

function wp_parse_args( $args, $defaults = array() ) {
	return array_merge( $defaults, is_array( $args ) ? $args : array() );
}

function sanitize_text_field( $value ) {
	return trim( strip_tags( (string) $value ) );
}

function sanitize_email( $value ) {
	return filter_var( $value, FILTER_SANITIZE_EMAIL );
}

function esc_url_raw( $value ) {
	return (string) $value;
}

function trailingslashit( $value ) {
	return rtrim( (string) $value, '/' ) . '/';
}

function home_url( $path = '' ) {
	return 'https://example.test' . (string) $path;
}

function get_bloginfo( $show = '', $filter = 'raw' ) {
	return 'Example Business';
}

function wp_strip_all_tags( $value ) {
	return strip_tags( (string) $value );
}

function wp_json_encode( $value, $flags = 0, $depth = 512 ) {
	return json_encode( $value, $flags, $depth );
}

function __( $text, $domain = 'default' ) {
	return $text;
}

function mrn_config_helper_get_social_links() {
	return array(
		array( 'url' => 'https://social.example.test/alpha' ),
		array( 'url' => 'https://social.example.test/alpha' ),
	);
}

function mrn_base_stack_get_attachment_image_url( $image, $size = 'full' ) {
	return is_array( $image ) && isset( $image['url'] ) ? $image['url'] : '';
}

function mrn_base_stack_image_has_content( $image ) {
	return is_array( $image ) && ! empty( $image['url'] );
}

function mrn_business_test_assert( $condition, $message ) {
	if ( $condition ) {
		return;
	}

	fwrite( STDERR, "FAIL: {$message}\n" );
	exit( 1 );
}

require dirname( __DIR__, 2 ) . '/inc/theme-options.php';

$first_payload = mrn_base_stack_get_business_information();
$acf_calls     = $GLOBALS['mrn_business_test_get_field_calls'];

mrn_business_test_assert( 27 === $acf_calls, 'the initial payload performs the expected 27 ACF field reads' );
mrn_business_test_assert( '(919) 555-1234' === $first_payload['phone'], 'header/footer phone display value is unchanged' );
mrn_business_test_assert( 'tel:9195551234' === $first_payload['phone_uri'], 'header/footer phone URI is unchanged' );
mrn_business_test_assert( 'Raleigh' === $first_payload['address']['city'], 'template consumer address value is unchanged' );
mrn_business_test_assert( '9:00 am' === $first_payload['business_hours']['monday']['open'], 'template consumer hours value is unchanged' );

$first_payload['phone']             = 'mutated';
$first_payload['address']['city']   = 'mutated';
$second_payload                     = mrn_base_stack_get_business_information();

mrn_business_test_assert( $acf_calls === $GLOBALS['mrn_business_test_get_field_calls'], 'multiple loader calls read ACF only once per request' );
mrn_business_test_assert( '(919) 555-1234' === $second_payload['phone'], 'top-level consumer mutation does not alter the request cache' );
mrn_business_test_assert( 'Raleigh' === $second_payload['address']['city'], 'nested array mutation does not alter the request cache' );

$preloaded_logo = array(
	'logo'                 => null,
	'logo_inverted'        => array( 'url' => 'https://preloaded.test/inverted.png' ),
	'logo_footer'          => null,
	'logo_footer_inverted' => array( 'url' => 'https://preloaded.test/footer-inverted.png' ),
);

mrn_business_test_assert( $preloaded_logo['logo_inverted'] === mrn_base_stack_get_business_logo( 'header', $preloaded_logo ), 'preloaded header payload uses the existing fallback order' );
mrn_business_test_assert( $preloaded_logo['logo_inverted'] === mrn_base_stack_get_business_logo( 'header_inverted', $preloaded_logo ), 'preloaded inverted header payload is accepted' );
mrn_business_test_assert( $preloaded_logo['logo_footer_inverted'] === mrn_base_stack_get_business_logo( 'footer', $preloaded_logo ), 'preloaded footer payload uses the existing fallback order' );
mrn_business_test_assert( $preloaded_logo['logo_footer_inverted'] === mrn_base_stack_get_business_logo( 'footer_inverted', $preloaded_logo ), 'preloaded inverted footer payload uses the existing fallback order' );
mrn_business_test_assert( $preloaded_logo['logo_inverted'] === mrn_base_stack_get_business_logo( 'unknown', $preloaded_logo ), 'unknown logo contexts retain the header fallback order' );
mrn_business_test_assert( null === mrn_base_stack_get_business_logo( 'header', array() ), 'an explicitly preloaded empty payload does not trigger a reload' );
mrn_business_test_assert( $acf_calls === $GLOBALS['mrn_business_test_get_field_calls'], 'preloaded logo selection performs no ACF reads' );

$schema = mrn_base_stack_get_business_schema_data();

mrn_business_test_assert( 'LocalBusiness' === $schema['@type'], 'schema organization type is unchanged' );
mrn_business_test_assert( $request_value . ' profile' === $schema['description'], 'schema description is unchanged' );
mrn_business_test_assert( 'https://example.test/header.png' === $schema['logo'], 'schema logo is unchanged' );
mrn_business_test_assert( '(919) 555-1234' === $schema['telephone'], 'schema telephone is unchanged' );
mrn_business_test_assert( array( 'https://social.example.test/alpha' ) === $schema['sameAs'], 'schema social links remain unique and unchanged' );
mrn_business_test_assert( '123 Main Street, Suite 4' === $schema['address']['streetAddress'], 'schema address is unchanged' );
mrn_business_test_assert( '09:00' === $schema['openingHoursSpecification'][0]['opens'], 'schema opening time is unchanged' );
mrn_business_test_assert( '17:00' === $schema['openingHoursSpecification'][0]['closes'], 'schema closing time is unchanged' );
mrn_business_test_assert( 34 === $GLOBALS['mrn_business_test_get_field_calls'], 'schema reuses the business payload and reads only its seven schema-specific ACF fields' );

ob_start();
mrn_base_stack_print_business_schema();
$schema_markup = ob_get_clean();
preg_match( '/<script[^>]*>(.*)<\/script>/s', $schema_markup, $schema_match );
$schema_json = isset( $schema_match[1] ) ? json_decode( $schema_match[1], true ) : null;

mrn_business_test_assert( $schema === $schema_json, 'business schema JSON-LD is semantically identical to the schema payload' );

$theme_options_source = file_get_contents( dirname( __DIR__, 2 ) . '/inc/theme-options.php' );
$header_source        = file_get_contents( dirname( __DIR__, 2 ) . '/header.php' );
$footer_source        = file_get_contents( dirname( __DIR__, 2 ) . '/footer.php' );
$loader_start         = strpos( $theme_options_source, 'function mrn_base_stack_get_business_information()' );
$loader_end           = strpos( $theme_options_source, 'function mrn_base_stack_get_business_opening_hours_schema', $loader_start );
$loader_source        = substr( $theme_options_source, $loader_start, $loader_end - $loader_start );

mrn_business_test_assert( false === strpos( $loader_source, 'set_transient(' ), 'the loader does not use a persistent transient' );
mrn_business_test_assert( false === strpos( $loader_source, 'wp_cache_set(' ), 'the loader does not write persistent object cache data' );
mrn_business_test_assert( false !== strpos( $header_source, "mrn_base_stack_get_business_logo( 'header', \$mrn_business_information )" ), 'header passes its unchanged preloaded payload to the logo helper' );
mrn_business_test_assert( false !== strpos( $footer_source, "mrn_base_stack_get_business_logo( 'footer', \$mrn_business_information )" ), 'footer passes its unchanged preloaded payload to the logo helper' );

if ( isset( $argv[1] ) && '--fingerprint' === $argv[1] ) {
	echo $second_payload['business_profile'] . "\n";
	exit( 0 );
}

echo "PASS: Business information memoization and schema contracts.\n";
