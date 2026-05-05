<?php
/**
 * Lightweight regression checks for TinyMCE font format ordering.
 *
 * Run:
 *   php plugins/mrn-google-fonts/tests/tinymce-font-formats-regression.php
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	define('ABSPATH', dirname(__DIR__, 3) . '/');
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
 * @return array<int, string>
 */
function extract_labels(string $font_formats): array {
	$labels = array();

	foreach (array_filter(array_map('trim', explode(';', $font_formats))) as $pair) {
		$parts = explode('=', $pair, 2);
		if (count($parts) !== 2) {
			continue;
		}

		$labels[] = trim((string) $parts[0]);
	}

	return $labels;
}

$method = new ReflectionMethod('MRN_Google_Fonts', 'inject_tinymce_font_formats');

// Case 1: no existing format string, plugin should include custom fonts and keep alpha ordering.
$case_one = (string) $method->invoke(
	null,
	'',
	array(
		'body_font_family' => 'Public Sans',
		'heading_font_family' => 'Geist',
	)
);

$case_one_labels = extract_labels($case_one);
$sorted_case_one = $case_one_labels;
natcasesort($sorted_case_one);
assert_or_exit(array_values($sorted_case_one) === $case_one_labels, 'Case 1 is not alphabetically sorted.');
assert_or_exit(in_array('Public Sans', $case_one_labels, true), 'Case 1 missing Public Sans label.');
assert_or_exit(in_array('Geist', $case_one_labels, true), 'Case 1 missing Geist label.');

// Case 2: existing formats already contain custom labels in non-alpha order.
$case_two_existing = 'Public Sans=Public Sans,sans-serif;Geist=Geist,sans-serif;Arial=arial,helvetica,sans-serif';
$case_two = (string) $method->invoke(
	null,
	$case_two_existing,
	array(
		'body_font_family' => 'Public Sans',
		'heading_font_family' => 'Geist',
	)
);

$case_two_labels = extract_labels($case_two);
$sorted_case_two = $case_two_labels;
natcasesort($sorted_case_two);
assert_or_exit(array_values($sorted_case_two) === $case_two_labels, 'Case 2 is not alphabetically sorted.');
assert_or_exit(count($case_two_labels) === count(array_unique($case_two_labels)), 'Case 2 contains duplicate labels.');

fwrite(STDOUT, "PASS: TinyMCE font formats are alphabetical and de-duplicated.\n");
