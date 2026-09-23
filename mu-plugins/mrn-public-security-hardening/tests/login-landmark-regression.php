<?php
// phpcs:ignoreFile -- WordPress-loaded regression script invoked by the disposable HTTP suite.
/**
 * Exercise the real WordPress HTML processor: wp eval-file <this file>.
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! function_exists( 'mrn_public_security_add_login_landmark' ) ) {
	throw new RuntimeException( 'Run through WP-CLI with the component loaded.' );
}

$cases = array(
	array( '<div id="login"><form></form></div>', '<div role="main" id="login"><form></form></div>' ),
	array( '<main><div id="login"></div></main>', '<main><div id="login"></div></main>' ),
	array( '<section role="main"></section><div id="login"></div>', '<section role="main"></section><div id="login"></div>' ),
	array( '<div id="login" role="form"></div>', '<div id="login" role="form"></div>' ),
	array( '<div id="login" role="main"></div>', '<div id="login" role="main"></div>' ),
	array( '<div id="other"></div>', '<div id="other"></div>' ),
	array( '', '' ),
);
foreach ( $cases as $index => $case ) {
	if ( mrn_public_security_add_login_landmark( $case[0] ) !== $case[1] ) {
		throw new RuntimeException( 'Login landmark case failed: ' . $index );
	}
}

$source = '<script>const html = \'<div id="login">\';</script><div id="login" class="unchanged" data-value="a&amp;b"><input name="rp_key" value="test-only"></div>';
$result = mrn_public_security_add_login_landmark( $source );
if ( 1 !== substr_count( $result, 'role="main"' ) || str_replace( ' role="main"', '', $result ) !== $source ) {
	throw new RuntimeException( 'Landmark must only add one attribute without changing script, styling, or form data.' );
}

echo "PASS: real WordPress login landmark preservation checks.\n";
