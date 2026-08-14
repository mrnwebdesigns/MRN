<?php
// phpcs:ignoreFile -- Standalone WordPress stub harness for the no-ACF defaults contract.
/**
 * Verify business-information defaults when ACF is unavailable.
 *
 * @package mrn-base-stack
 */

define( 'ABSPATH', __DIR__ );

function add_action( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
	return true;
}

function add_filter( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
	return true;
}

function __( $text, $domain = 'default' ) {
	return $text;
}

function mrn_business_defaults_test_assert( $condition, $message ) {
	if ( $condition ) {
		return;
	}

	fwrite( STDERR, "FAIL: {$message}\n" );
	exit( 1 );
}

require dirname( __DIR__, 2 ) . '/inc/theme-options.php';

$expected = array(
	'business_profile'     => '',
	'years_in_business'    => '',
	'logo'                 => null,
	'logo_inverted'        => null,
	'logo_footer'          => null,
	'logo_footer_inverted' => null,
	'phone'                => '',
	'phone_uri'            => '',
	'text_phone'           => '',
	'text_phone_uri'       => '',
	'address'              => array(),
	'business_hours'       => array(),
	'holiday_hours'        => array(),
);

$first = mrn_base_stack_get_business_information();
mrn_business_defaults_test_assert( $expected === $first, 'defaults remain unchanged when ACF is unavailable' );

$first['address']['city'] = 'mutated';
$second                   = mrn_base_stack_get_business_information();

mrn_business_defaults_test_assert( $expected === $second, 'cached defaults are isolated from consumer mutation' );

echo "PASS: Business information defaults without ACF.\n";
