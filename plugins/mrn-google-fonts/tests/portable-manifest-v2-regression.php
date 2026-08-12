<?php
/** Regression checks for portable manifest schema, checksums, and migration. */

declare(strict_types=1);

define('ABSPATH', dirname(__DIR__, 3) . '/');

class WP_Error {
	private $message;
	public function __construct($code = '', $message = '') { unset($code); $this->message = (string) $message; }
	public function get_error_message(): string { return $this->message; }
}
function is_wp_error($value): bool { return $value instanceof WP_Error; }
function sanitize_key($value): string { return preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $value)); }
function sanitize_text_field($value): string { return trim(strip_tags((string) $value)); }
function esc_url_raw($value): string { return (string) $value; }
function absint($value): int { return abs((int) $value); }
function wp_normalize_path($path): string { return str_replace('\\', '/', (string) $path); }
function trailingslashit($value): string { return rtrim((string) $value, '/') . '/'; }
function untrailingslashit($value): string { return rtrim((string) $value, '/'); }
function wp_http_validate_url($url): bool { return false !== filter_var($url, FILTER_VALIDATE_URL); }
function wp_parse_url($url, $component = -1) { return -1 === $component ? parse_url((string) $url) : parse_url((string) $url, $component); }
function update_option($key, $value, $autoload = null): bool { unset($autoload); $GLOBALS['mrn_v2_options'][$key] = $value; return true; }
function get_option($key, $default = false) { return $GLOBALS['mrn_v2_options'][$key] ?? $default; }
function wp_upload_dir($time = null, $create = true): array { unset($time, $create); return $GLOBALS['mrn_v2_uploads']; }

require dirname(__DIR__) . '/includes/class-mrn-google-fonts.php';

function assert_manifest(bool $condition, string $message): void {
	if (!$condition) {
		fwrite(STDERR, "FAIL: {$message}\n");
		exit(1);
	}
}

$temp = sys_get_temp_dir() . '/mrn-gf-v2-' . uniqid('', true);
$uploads = $temp . '/uploads';
$signature = str_repeat('b', 40);
$build = $uploads . '/mrn-google-fonts/' . $signature;
mkdir($build, 0777, true);
file_put_contents($build . '/font-1.woff2', str_repeat('fontdata', 32));
file_put_contents($build . '/local-fonts.css', "@font-face{font-family:'Test';src:url(font-1.woff2) format('woff2');font-style:normal;font-weight:400;}");
$GLOBALS['mrn_v2_uploads'] = array('basedir' => $uploads, 'baseurl' => 'https://one.example/subdir/wp-content/uploads', 'error' => false);
$manifest = array(
	'schema_version' => 2,
	'signature' => $signature,
	'css_relative_path' => $signature . '/local-fonts.css',
	'build_relative_directory' => $signature,
	'generated_at' => time(),
	'validated_at' => time(),
	'file_count' => 1,
	'family_count' => 1,
	'families' => array('Test'),
	'faces' => array('Test' => array('normal' => array(400), 'italic' => array())),
	'formats' => array('woff2'),
	'files' => array(array('relative_path' => $signature . '/font-1.woff2', 'checksum' => hash_file('sha256', $build . '/font-1.woff2'), 'format' => 'woff2', 'size' => filesize($build . '/font-1.woff2'))),
	'css_checksum' => hash_file('sha256', $build . '/local-fonts.css'),
);

assert_manifest(true === MRN_Google_Fonts::validate_local_manifest($manifest), 'A valid schema-v2 manifest failed validation.');
$GLOBALS['mrn_v2_uploads']['baseurl'] = 'https://two.example/network/site-2/uploads';
assert_manifest(true === MRN_Google_Fonts::validate_local_manifest($manifest), 'A domain/subdirectory change invalidated portable files.');
file_put_contents($build . '/font-1.woff2', 'corrupt');
assert_manifest(is_wp_error(MRN_Google_Fonts::validate_local_manifest($manifest)), 'A corrupt font checksum was accepted.');
file_put_contents($build . '/font-1.woff2', str_repeat('fontdata', 32));

$legacy_css = "@font-face{src:url(https://old.example/wp-content/uploads/mrn-google-fonts/{$signature}/font-1.woff2) format('woff2');}";
file_put_contents($build . '/local-fonts.css', $legacy_css);
$GLOBALS['mrn_v2_options'][MRN_Google_Fonts::LOCAL_OPTION_KEY] = array(
	'signature' => $signature,
	'css_url' => 'https://old.example/wp-content/uploads/mrn-google-fonts/' . $signature . '/local-fonts.css',
	'css_path' => '/obsolete/server/uploads/mrn-google-fonts/' . $signature . '/local-fonts.css',
	'directory' => '/obsolete/server/uploads/mrn-google-fonts/' . $signature,
	'generated_at' => time(),
	'file_count' => 1,
	'family_count' => 1,
	'subset' => 'latin-ext',
	'font_display' => 'optional',
);
$dry = MRN_Google_Fonts::migrate_local_manifest(true);
assert_manifest(!is_wp_error($dry) && !empty($dry['changed']), 'Legacy migration dry-run did not report a safe conversion.');
$result = MRN_Google_Fonts::migrate_local_manifest(false);
assert_manifest(!is_wp_error($result), 'Legacy migration failed.');
$saved = $GLOBALS['mrn_v2_options'][MRN_Google_Fonts::LOCAL_OPTION_KEY];
assert_manifest(2 === $saved['schema_version'], 'Migrated manifest schema is not v2.');
assert_manifest('latin-ext' === $saved['subset'] && 'optional' === $saved['font_display'], 'Migrated manifest did not preserve subset/display metadata.');
assert_manifest(!isset($saved['css_url'], $saved['css_path'], $saved['directory']), 'Migrated manifest retained environment-specific fields.');
assert_manifest(false === strpos((string) file_get_contents($build . '/local-fonts.css'), 'old.example'), 'Migrated CSS retained an obsolete environment URL.');
$repeat = MRN_Google_Fonts::migrate_local_manifest(false);
assert_manifest(!is_wp_error($repeat) && empty($repeat['changed']), 'Repeated migration was not idempotent.');

unlink($build . '/font-1.woff2');
foreach (glob($build . '/*') as $file) { unlink($file); }
rmdir($build);
rmdir(dirname($build));
rmdir($uploads);
rmdir($temp);

fwrite(STDOUT, "PASS: portable schema-v2 manifests validate, survive environment changes, detect corruption, and migrate idempotently.\n");
