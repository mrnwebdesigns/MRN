<?php
// phpcs:ignoreFile -- Standalone asset-registration and dependency-contract harness.
/** Verify opt-in, fallback and legacy consumer behavior without a database. */
define( 'ABSPATH', __DIR__ );
define( '_S_VERSION', 'fixture' );
$theme = dirname( __DIR__, 2 );
$GLOBALS['motion_test_directory'] = $theme;
$GLOBALS['motion_test_selection'] = 'full';
function get_template_directory() { return $GLOBALS['motion_test_directory']; }
function get_template_directory_uri() { return 'https://fixture.invalid/theme'; }
function apply_filters( $name, $value ) { return $GLOBALS['motion_test_selection']; }
function add_action( ...$args ) {}
function wp_scripts() { return $GLOBALS['motion_test_scripts']; }
function wp_register_script( $handle, $src, $deps, $version, $footer ) {
	$scripts = wp_scripts();
	if ( ! isset( $scripts->registered[ $handle ] ) ) {
		$scripts->registered[ $handle ] = (object) compact( 'src', 'deps', 'version', 'footer' );
	}
}
function wp_enqueue_script( $handle, $src = null, $deps = array(), $version = null, $footer = false ) {
	if ( null !== $src ) { wp_register_script( $handle, $src, $deps, $version, $footer ); }
	wp_scripts()->queue[] = $handle;
}
function motion_fixture( $selection = 'full' ) {
	$GLOBALS['motion_test_selection'] = $selection;
	$GLOBALS['motion_test_scripts'] = (object) array( 'registered' => array(), 'queue' => array(), 'to_do' => array(), 'done' => array() );
	mrn_base_stack_enqueue_motion_assets();
	return wp_scripts();
}
function motion_assert( $value, $message ) {
	if ( ! $value ) { throw new RuntimeException( $message ); }
	$GLOBALS['motion_test_assertions'] = ( $GLOBALS['motion_test_assertions'] ?? 0 ) + 1;
}
require $theme . '/inc/motion-assets.php';
$full = 'mrn-base-stack-motion';
$core = 'mrn-base-stack-motion-core';
$effects = 'mrn-base-stack-front-end-effects';
$scripts = motion_fixture();
motion_assert( array( $full ) === $scripts->registered[ $effects ]->deps && in_array( $full, $scripts->queue, true ), 'Default must retain the full legacy enqueue.' );
motion_assert( '12.38.0' === $scripts->registered[ $full ]->version && $scripts->registered[ $full ]->footer, 'Legacy version/footer contract changed.' );
$scripts = motion_fixture( 'unknown' );
motion_assert( array( $full ) === $scripts->registered[ $effects ]->deps, 'Unknown selection must use full.' );
$scripts = motion_fixture( 'core' );
mrn_base_stack_resolve_motion_consumers();
motion_assert( array( $core ) === $scripts->registered[ $effects ]->deps && ! in_array( $full, $scripts->queue, true ), 'Core-only page must not enqueue the full download.' );
motion_assert( isset( $scripts->registered[ $full ] ), 'Legacy handle must remain registered.' );
wp_enqueue_script( 'extension', '/extension.js', array( $full ), '1', true );
mrn_base_stack_resolve_motion_consumers();
motion_assert( array( $full ) === $scripts->registered[ $effects ]->deps, 'Queued legacy dependency must retain one full runtime.' );
motion_assert( array( $full ) === $scripts->registered['extension']->deps, 'Do not rewrite an extension dependency.' );
$scripts = motion_fixture( 'core' );
wp_enqueue_script( 'wrapper', '/wrapper.js', array( 'nested' ), '1', true );
wp_register_script( 'nested', '/nested.js', array( $full ), '1', true );
mrn_base_stack_resolve_motion_consumers();
motion_assert( array( $full ) === $scripts->registered[ $effects ]->deps, 'Nested consumer must retain full.' );
$scripts = motion_fixture( 'core' );
wp_register_script( 'not-enqueued', '/unused.js', array( $full ), '1', true );
wp_enqueue_script( 'wrapper', '/wrapper.js', array( $effects ), '1', true );
mrn_base_stack_resolve_motion_consumers();
motion_assert( array( $core ) === $scripts->registered[ $effects ]->deps, 'Unused registrations and effects wrappers do not require full.' );
$scripts = motion_fixture( 'core' );
wp_enqueue_script( 'cycle-a', '/a.js', array( 'cycle-b' ), '1', true );
wp_register_script( 'cycle-b', '/b.js', array( 'cycle-a' ), '1', true );
mrn_base_stack_resolve_motion_consumers();
motion_assert( array( $core ) === $scripts->registered[ $effects ]->deps, 'Unrelated dependency cycles must not recurse indefinitely.' );
$scripts = motion_fixture( 'core' );
$scripts->registered[ $effects ]->deps = array( 'custom-runtime' );
wp_enqueue_script( $full );
mrn_base_stack_resolve_motion_consumers();
motion_assert( array( 'custom-runtime' ) === $scripts->registered[ $effects ]->deps, 'Preserve an overridden effects dependency contract.' );
$scripts = motion_fixture( 'core' );
$scripts->done = array( $effects );
wp_enqueue_script( $full );
mrn_base_stack_resolve_motion_consumers();
motion_assert( array( $core ) === $scripts->registered[ $effects ]->deps, 'Do not rewrite already printed effects.' );
$scripts = motion_fixture( 'core' );
$scripts->done = array( $full );
mrn_base_stack_resolve_motion_consumers();
motion_assert( array( $full ) === $scripts->registered[ $effects ]->deps, 'Already printed full runtime must be reused.' );
$GLOBALS['motion_test_directory'] = __DIR__; // No vendor core file in this fixture directory.
$scripts = motion_fixture( 'core' );
motion_assert( array( $full ) === $scripts->registered[ $effects ]->deps, 'Missing core distribution must fall back to full.' );
echo 'PASS ' . $GLOBALS['motion_test_assertions'] . " Motion asset contracts\n";
