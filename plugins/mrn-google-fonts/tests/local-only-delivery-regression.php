<?php
/** Regression checks for strict local-only delivery. */

declare(strict_types=1);

define('ABSPATH', dirname(__DIR__, 3) . '/');
define('MRN_GOOGLE_FONTS_URL', 'https://site.example/wp-content/plugins/mrn-google-fonts/');
define('DAY_IN_SECONDS', 86400);

$GLOBALS['mrn_test_options'] = array();
$GLOBALS['mrn_test_styles'] = array();

class WP_Error {
	private $message;
	public function __construct($code = '', $message = '') { unset($code); $this->message = (string) $message; }
	public function get_error_message(): string { return $this->message; }
}
function is_wp_error($value): bool { return $value instanceof WP_Error; }
function get_option($key, $default = false) { return $GLOBALS['mrn_test_options'][$key] ?? $default; }
function sanitize_key($value): string { return preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $value)); }
function sanitize_text_field($value): string { return trim(strip_tags((string) $value)); }
function esc_url_raw($value): string { return (string) $value; }
function absint($value): int { return abs((int) $value); }
function is_admin(): bool { return false; }
function is_front_page(): bool { return true; }
function is_singular(): bool { return true; }
function is_archive(): bool { return false; }
function is_home(): bool { return false; }
function is_search(): bool { return false; }
function apply_filters($hook, $value) { unset($hook); return $value; }
function wp_enqueue_style($handle, $src = '', $deps = array(), $version = false): void { $GLOBALS['mrn_test_styles'][$handle] = array('src' => $src, 'deps' => $deps, 'version' => $version); }
function wp_add_inline_style($handle, $css): void { $GLOBALS['mrn_test_styles'][$handle]['inline'] = $css; }
function get_transient($key) { unset($key); return array('Lora'); }
function wp_parse_url($url, $component = -1) { return -1 === $component ? parse_url((string) $url) : parse_url((string) $url, $component); }
function wp_upload_dir($time = null, $create = true): array { unset($time, $create); return array('basedir' => sys_get_temp_dir(), 'baseurl' => 'https://site.example/uploads', 'error' => false); }
function wp_normalize_path($path): string { return str_replace('\\', '/', (string) $path); }
function trailingslashit($value): string { return rtrim((string) $value, '/') . '/'; }
function untrailingslashit($value): string { return rtrim((string) $value, '/'); }
function wp_http_validate_url($url): bool { return false !== filter_var($url, FILTER_VALIDATE_URL); }

require dirname(__DIR__) . '/includes/class-mrn-google-fonts-stack-bridge.php';
require dirname(__DIR__) . '/includes/class-mrn-google-fonts.php';

function assert_delivery(bool $condition, string $message): void {
	if (!$condition) {
		fwrite(STDERR, "FAIL: {$message}\n");
		exit(1);
	}
}

$settings = MRN_Google_Fonts::default_settings();
$settings['enabled'] = 1;
$settings['load_on_frontend'] = 1;
$settings['body_font_family'] = 'Lora';
$settings['body_font_targets'] = array('body_text');
$settings['delivery_mode'] = 'local_only';
$GLOBALS['mrn_test_options'][MRN_Google_Fonts::OPTION_KEY] = $settings;

MRN_Google_Fonts::enqueue_frontend_assets();
assert_delivery(!isset($GLOBALS['mrn_test_styles']['mrn-google-fonts-remote']), 'local_only enqueued a remote stylesheet.');
assert_delivery(isset($GLOBALS['mrn_test_styles']['mrn-google-fonts-frontend']), 'Fallback typography stylesheet was not enqueued.');
assert_delivery(false === strpos((string) ($GLOBALS['mrn_test_styles']['mrn-google-fonts-frontend']['inline'] ?? ''), 'fonts.googleapis.com'), 'Fallback CSS contains a Google Fonts URL.');

$hints = MRN_Google_Fonts::filter_resource_hints(array(), 'preconnect');
assert_delivery(array() === $hints, 'local_only emitted Google resource hints.');

$GLOBALS['mrn_test_styles'] = array();
$settings['delivery_mode'] = 'local_preferred';
$GLOBALS['mrn_test_options'][MRN_Google_Fonts::OPTION_KEY] = $settings;
MRN_Google_Fonts::enqueue_frontend_assets();
assert_delivery(isset($GLOBALS['mrn_test_styles']['mrn-google-fonts-remote']), 'local_preferred did not retain its explicit remote fallback.');

fwrite(STDOUT, "PASS: local_only never enqueues or hints Google-hosted resources and retains fallback CSS.\n");
