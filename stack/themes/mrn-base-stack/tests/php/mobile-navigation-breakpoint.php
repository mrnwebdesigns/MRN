<?php
// phpcs:ignoreFile -- Standalone WordPress stub harness for mobile navigation settings.
/**
 * Regression coverage for the configurable mobile navigation breakpoint.
 *
 * @package mrn-base-stack
 */

function absint( $value ) {
	return abs( (int) $value );
}

require dirname( __DIR__, 2 ) . '/inc/mobile-navigation.php';

$default_options = mrn_base_stack_get_mobile_navigation_options();
if ( 'overlay' !== $default_options['drawer_mode'] ) {
	throw new RuntimeException( 'Default mobile navigation drawer interaction was not preserved.' );
}

$configured_style = mrn_base_stack_get_mobile_navigation_style(
	array(
		'breakpoint' => 900,
	)
);

if ( false === strpos( $configured_style, '--mrn-mobile-menu-breakpoint:900px' ) ) {
	throw new RuntimeException( 'Configured mobile navigation breakpoint was not rendered.' );
}

$default_style = mrn_base_stack_get_mobile_navigation_style( array() );
if ( false === strpos( $default_style, '--mrn-mobile-menu-breakpoint:1199px' ) ) {
	throw new RuntimeException( 'Default mobile navigation breakpoint was not preserved.' );
}

$minimum_style = mrn_base_stack_get_mobile_navigation_style(
	array(
		'breakpoint' => 200,
	)
);
if ( false === strpos( $minimum_style, '--mrn-mobile-menu-breakpoint:320px' ) ) {
	throw new RuntimeException( 'Mobile navigation breakpoint minimum was not enforced.' );
}

$maximum_style = mrn_base_stack_get_mobile_navigation_style(
	array(
		'breakpoint' => 2000,
	)
);
if ( false === strpos( $maximum_style, '--mrn-mobile-menu-breakpoint:1600px' ) ) {
	throw new RuntimeException( 'Mobile navigation breakpoint maximum was not enforced.' );
}

echo "Mobile navigation breakpoint tests passed.\n";
