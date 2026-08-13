<?php
/** Regression checks for the authenticated MainWP Child execution hook. */

declare(strict_types=1);

define('ABSPATH', dirname(__DIR__, 3) . '/');

class WP_Error {
	private $code;
	private $message;
	public function __construct($code = '', $message = '') { $this->code = (string) $code; $this->message = (string) $message; }
	public function get_error_code(): string { return $this->code; }
	public function get_error_message(): string { return $this->message; }
}

function sanitize_key($value): string { return preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $value)); }
function sanitize_text_field($value): string { return trim(strip_tags((string) $value)); }
function wp_unslash($value) { return $value; }

require dirname(__DIR__) . '/includes/class-mrn-google-fonts-stack-bridge.php';
require dirname(__DIR__) . '/includes/class-mrn-google-fonts.php';

function assert_mainwp_execution(bool $condition, string $message): void {
	if (!$condition) {
		fwrite(STDERR, "FAIL: {$message}\n");
		exit(1);
	}
}

$existing = array('another_extension' => array('status' => 'ok'));
$untouched = MRN_Google_Fonts::handle_mainwp_child_execution($existing, array());
assert_mainwp_execution($existing === $untouched, 'Requests without the plugin-specific action changed another extension response.');

$invalid = MRN_Google_Fonts::handle_mainwp_child_execution(
	$existing,
	array('mrn_google_fonts_action' => 'delete_everything')
);
assert_mainwp_execution(false === ($invalid['success'] ?? true), 'An unsupported operation was not rejected.');
assert_mainwp_execution('mrn_google_fonts_mainwp_action' === ($invalid['error_code'] ?? ''), 'The unsupported operation returned the wrong error code.');
assert_mainwp_execution('1.0.7' === ($invalid['plugin_version'] ?? ''), 'The MainWP response did not identify the expected plugin version.');

fwrite(STDOUT, "PASS: MainWP Child execution is isolated to the plugin action key and rejects unsupported operations.\n");
