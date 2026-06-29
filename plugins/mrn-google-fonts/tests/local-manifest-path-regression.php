<?php
/**
 * Lightweight regression checks for portable local font manifests.
 *
 * Run:
 *   php plugins/mrn-google-fonts/tests/local-manifest-path-regression.php
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	define('ABSPATH', dirname(__DIR__, 3) . '/');
}

if (!function_exists('sanitize_text_field')) {
	function sanitize_text_field($value): string {
		return trim(strip_tags((string) $value));
	}
}

if (!function_exists('esc_url_raw')) {
	function esc_url_raw($url): string {
		return filter_var((string) $url, FILTER_SANITIZE_URL);
	}
}

if (!function_exists('wp_http_validate_url')) {
	function wp_http_validate_url($url): bool {
		return (bool) filter_var((string) $url, FILTER_VALIDATE_URL);
	}
}

if (!function_exists('trailingslashit')) {
	function trailingslashit($value): string {
		return rtrim((string) $value, "/\\") . '/';
	}
}

if (!function_exists('untrailingslashit')) {
	function untrailingslashit($value): string {
		return rtrim((string) $value, "/\\");
	}
}

if (!function_exists('wp_normalize_path')) {
	function wp_normalize_path($path): string {
		return str_replace('\\', '/', (string) $path);
	}
}

if (!function_exists('wp_upload_dir')) {
	function wp_upload_dir($time = null, $create_dir = true): array {
		unset($time, $create_dir);
		return $GLOBALS['mrn_google_fonts_test_uploads'];
	}
}

require_once dirname(__DIR__) . '/includes/class-mrn-google-fonts.php';

function assert_or_exit(bool $condition, string $message): void {
	if ($condition) {
		return;
	}

	fwrite(STDERR, "FAIL: {$message}\n");
	exit(1);
}

$signature = str_repeat('a', 40);
$temp_root = sys_get_temp_dir() . '/mrn-google-fonts-local-manifest-' . uniqid('', true);
$uploads_dir = $temp_root . '/uploads';
$current_css_dir = $uploads_dir . '/mrn-google-fonts/' . $signature;
$current_css_path = $current_css_dir . '/local-fonts.css';

mkdir($current_css_dir, 0777, true);
file_put_contents($current_css_path, '/* test local css */');

$GLOBALS['mrn_google_fonts_test_uploads'] = array(
	'basedir' => $uploads_dir,
	'baseurl' => 'https://current.example/wp-content/uploads',
	'error' => false,
);

$manifest = array(
	'signature' => strtoupper($signature),
	'css_url' => 'https://old.example/wp-content/uploads/mrn-google-fonts/' . $signature . '/local-fonts.css',
	'css_path' => '/stale/server/path/wp-content/uploads/mrn-google-fonts/' . $signature . '/local-fonts.css',
);

$matches_method = new ReflectionMethod('MRN_Google_Fonts', 'local_manifest_matches_signature');
assert_or_exit(
	(bool) $matches_method->invoke(null, $manifest, $signature),
	'Local manifest should remain active when the saved absolute path is stale but the current uploads path exists.'
);

$url_method = new ReflectionMethod('MRN_Google_Fonts', 'resolve_local_manifest_css_url');
$resolved_url = (string) $url_method->invoke(null, $manifest);
assert_or_exit(
	'https://current.example/wp-content/uploads/mrn-google-fonts/' . $signature . '/local-fonts.css' === $resolved_url,
	'Local manifest should resolve CSS URL from the current uploads base URL instead of a stale saved domain.'
);

unlink($current_css_path);
rmdir($current_css_dir);
rmdir(dirname($current_css_dir));
rmdir($uploads_dir);
rmdir($temp_root);

fwrite(STDOUT, "PASS: Local Google Fonts manifests survive path/domain changes.\n");
