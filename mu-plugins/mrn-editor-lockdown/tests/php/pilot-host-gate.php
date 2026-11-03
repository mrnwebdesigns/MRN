<?php
// phpcs:ignoreFile -- Standalone opt-in/host boundary contract harness.
/** Run: php tests/php/pilot-host-gate.php [enabled|disabled|unset] */
define( 'ABSPATH', __DIR__ );
function add_action() {}
function add_filter() {}
function home_url() { return $GLOBALS['pilot_test_url']; }
function wp_parse_url( $url, $component ) { return parse_url( $url, $component ); }
$mode = $argv[1] ?? 'enabled';
if ( 'unset' !== $mode ) { define( 'MRN_EDITOR_EXPERIENCE_ENABLED', 'enabled' === $mode ); }
require __DIR__ . '/../../includes/section-navigator.php';
foreach ( array(
	'https://admin-editor-pilot.localhost' => true,
	'https://other.localhost' => true,
	'https://admin-editor-pilot.mrndev.io' => true,
	'https://trilliant.mrndev.io' => false,
	'https://admin-editor-pilot.mrndev.io.example.com' => false,
	'https://example.com' => false,
) as $url => $allowed ) {
	$GLOBALS['pilot_test_url'] = $url;
	if ( mrn_editor_experience_enabled() !== ( 'enabled' === $mode && $allowed ) ) {
		throw new RuntimeException( 'Pilot boundary failed: ' . $url . ' / ' . $mode );
	}
}
echo "PASS: exact review host, other-site exclusion and emergency switch ($mode).\n";
