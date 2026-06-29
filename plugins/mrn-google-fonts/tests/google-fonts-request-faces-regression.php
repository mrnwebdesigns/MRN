<?php
/**
 * Lightweight regression checks for Google Fonts CSS2 face requests.
 *
 * Run:
 *   php plugins/mrn-google-fonts/tests/google-fonts-request-faces-regression.php
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	define('ABSPATH', dirname(__DIR__, 3) . '/');
}

if (!function_exists('sanitize_key')) {
	function sanitize_key($key): string {
		$key = strtolower((string) $key);
		return preg_replace('/[^a-z0-9_\-]/', '', $key);
	}
}

if (!function_exists('get_transient')) {
	function get_transient($transient) {
		unset($transient);
		return array('Source Sans 3', 'Lora');
	}
}

if (!function_exists('wp_parse_url')) {
	function wp_parse_url($url, $component = -1) {
		if (-1 === $component) {
			return parse_url((string) $url);
		}

		return parse_url((string) $url, $component);
	}
}

if (!function_exists('add_filter')) {
	$GLOBALS['mrn_google_fonts_test_filters'] = array();

	function add_filter($hook_name, $callback, $priority = 10, $accepted_args = 1): bool {
		$GLOBALS['mrn_google_fonts_test_filters'][$hook_name][$priority][] = array(
			'callback' => $callback,
			'accepted_args' => $accepted_args,
		);

		return true;
	}

	function apply_filters($hook_name, $value) {
		$args = func_get_args();
		$filters = $GLOBALS['mrn_google_fonts_test_filters'][$hook_name] ?? array();
		if (empty($filters)) {
			return $value;
		}

		ksort($filters);
		foreach ($filters as $callbacks) {
			foreach ($callbacks as $filter) {
				$accepted_args = max(1, (int) $filter['accepted_args']);
				$callback_args = array_slice($args, 1, $accepted_args);
				$value = call_user_func_array($filter['callback'], $callback_args);
				$args[1] = $value;
			}
		}

		return $value;
	}
}

require_once dirname(__DIR__) . '/includes/class-mrn-google-fonts.php';

/**
 * Fail-fast assertion helper.
 */
function assert_or_exit(bool $condition, string $message): void {
	if ($condition) {
		return;
	}

	fwrite(STDERR, "FAIL: {$message}\n");
	exit(1);
}

/**
 * @param array<string, mixed> $settings Request settings.
 */
function build_request_url(array $settings): string {
	$method = new ReflectionMethod('MRN_Google_Fonts', 'build_google_fonts_request');
	$request = $method->invoke(null, $settings);

	return isset($request['url']) ? (string) $request['url'] : '';
}

$base_settings = array(
	'body_font_family' => 'Source Sans 3',
	'heading_font_family' => 'Lora',
	'accent_font_family' => 'system-ui',
	'body_font_weights' => '300,400,600,700',
	'heading_font_weights' => '500,600,700',
	'accent_font_weights' => '400',
	'body_font_italics' => 1,
	'heading_font_italics' => 1,
	'accent_font_italics' => 0,
	'body_font_targets' => array('body_text'),
	'heading_font_targets' => array('headings'),
	'accent_font_targets' => array(),
	'font_display' => 'swap',
);

$legacy_url = build_request_url($base_settings);
$expected_legacy_url = 'https://fonts.googleapis.com/css2?family=Source+Sans+3:ital,wght@0,300;0,400;0,600;0,700;1,300;1,400;1,600;1,700&family=Lora:ital,wght@0,500;0,600;0,700;1,500;1,600;1,700&display=swap';
assert_or_exit($expected_legacy_url === $legacy_url, 'Legacy italics request changed unexpectedly.');

$configured_settings = $base_settings;
$configured_settings['font_faces'] = array(
	'Source Sans 3' => array(
		'normal' => array(300, 400, 600, 700),
		'italic' => array(),
	),
	'Lora' => array(
		'normal' => array(600, 700),
		'italic' => array(600, 700),
	),
);

$configured_url = build_request_url($configured_settings);
$expected_lean_url = 'https://fonts.googleapis.com/css2?family=Source+Sans+3:wght@300;400;600;700&family=Lora:ital,wght@0,600;0,700;1,600;1,700&display=swap';
assert_or_exit($expected_lean_url === $configured_url, 'Settings-level font_faces contract did not produce the lean Freedom House URL.');

add_filter(
	'mrn_google_fonts_family_faces',
	static function (array $families): array {
		$families['Source Sans 3'] = array(
			'normal' => array(300, 400, 600, 700),
			'italic' => array(),
		);
		$families['Lora'] = array(
			'normal' => array(600, 700),
			'italic' => array(600, 700),
		);

		return $families;
	},
	10,
	2
);

$filtered_url = build_request_url($base_settings);
assert_or_exit($expected_lean_url === $filtered_url, 'mrn_google_fonts_family_faces filter did not produce the lean Freedom House URL.');

$hints_method = new ReflectionMethod('MRN_Google_Fonts', 'hints_contain_url');
assert_or_exit(
	(bool) $hints_method->invoke(null, array('//fonts.googleapis.com'), 'https://fonts.googleapis.com'),
	'Resource hint comparison missed protocol-relative Google APIs hint.'
);
assert_or_exit(
	(bool) $hints_method->invoke(null, array(array('href' => 'https://fonts.gstatic.com/')), 'https://fonts.gstatic.com'),
	'Resource hint comparison missed trailing-slash Google Static hint.'
);
assert_or_exit(
	!(bool) $hints_method->invoke(null, array('https://example.com'), 'https://fonts.gstatic.com'),
	'Resource hint comparison produced a false positive.'
);

fwrite(STDOUT, "PASS: Google Fonts face requests support lean per-family normal/italic weights.\n");
