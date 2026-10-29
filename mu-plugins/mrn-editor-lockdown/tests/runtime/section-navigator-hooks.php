<?php
// Test-only hooks in the isolated Local Hub clone; never distributed.
defined( 'ABSPATH' ) || exit;
if ('admin-editor-pilot.localhost' !== wp_parse_url(home_url(), PHP_URL_HOST)) { return; }
add_filter('acf/validate_value', function ($valid, $value) {
 if (is_string($value) && 'MRN_PILOT_INVALID' === $value) { return 'Pilot validation: replace the sentinel with a valid section name.'; }
 return $valid;
}, 10, 2);
add_action('admin_notices', function () {
 if (isset($_GET['mrn_pilot_notice'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only local notice fixture; no submitted value is used.
  echo '<div id="mrn-pilot-context-notice" class="notice notice-error"><p>Pilot test: actionable warning. <a href="#title">Review title</a> <button type="button" class="button">Review details</button></p></div>';
 }
});

add_action('after_setup_theme', function () {
 if (!function_exists('mrn_base_stack_editor_save_guard_start')) { require_once dirname(ABSPATH) . '/editor-save-integrity-baseline.php'; }
}, 99);
