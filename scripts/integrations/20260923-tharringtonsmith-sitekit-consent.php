<?php
/**
 * Exact-target migration v1. Run with wp eval-file <file> plan|apply|rollback
 * <fresh-backup-nonce>. Never loaded by a plugin or run automatically.
 * rollback keeps tracking off; restore-prior additionally requires the literal
 * acknowledge-preconsent-tracking argument and restores the known-defective state.
 */
if (!defined('WP_CLI') || !WP_CLI) {
  throw new RuntimeException('WP-CLI required.');
}
$mode = isset($args[0]) ? $args[0] : 'plan';
if (!in_array($mode, array('plan', 'apply', 'rollback', 'restore-prior'), true)) {
  WP_CLI::error('Unknown mode.');
}
if (untrailingslashit(home_url()) !== 'https://tharringtonsmith.com' || untrailingslashit(site_url()) !== 'https://tharringtonsmith.com' || is_multisite()) {
  WP_CLI::error('Exact production single-site URL precondition failed.');
}
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$plugins = get_plugins();
$cookie_file = 'mrn-cookie-consent/mrn-cookie-consent.php';
$sitekit_file = 'google-site-kit/google-site-kit.php';
if (!is_plugin_active($cookie_file) || !is_plugin_active($sitekit_file) || !isset($plugins[$sitekit_file]) || $plugins[$sitekit_file]['Version'] !== '1.160.1') {
  WP_CLI::error('Qualified plugin activity/version changed.');
}
$cookie_version = isset($plugins[$cookie_file]) ? $plugins[$cookie_file]['Version'] : '';
if (!in_array($cookie_version, array('1.1.45', '1.1.46'), true) || ($mode === 'apply' && $cookie_version !== '1.1.46')) {
  WP_CLI::error('Install the reviewed 1.1.46 package before applying the migration.');
}
$before = array('enabled' => 1);
$after = array('enabled' => 1, 'sitekit_analytics_gate' => 1);
$consent = get_option('mrn_silktide_consent_settings');
$analytics = get_option('googlesitekit_analytics-4_settings');
$modules = get_option('googlesitekit_active_modules');
$gateway = get_option('googlesitekit_google_tag_gateway');
if (($consent !== $before && $consent !== $after) || !is_array($analytics) || !is_array($modules)) {
  WP_CLI::error('Raw consent or Site Kit settings drifted; review new values.');
}
$expected = array('measurementID'=>'G-0DPCZE00EQ','googleTagID'=>'GT-WPD79HLV','propertyID'=>'504163756','webDataStreamID'=>'12132291507','adsConversionID'=>'','trackingDisabled'=>array('loggedinUsers'));
foreach ($expected as $key => $value) {
  if (!array_key_exists($key, $analytics) || $analytics[$key] !== $value) {
    WP_CLI::error('Site Kit destination/exclusion precondition failed: ' . $key);
  }
}
sort($modules);
if ($modules !== array('analytics-4', 'pagespeed-insights') || !empty($gateway['isEnabled']) || function_exists('mrn_gtm_get_container_id') || !isset($analytics['useSnippet']) || !is_bool($analytics['useSnippet'])) {
  WP_CLI::error('Qualified tracking ownership changed.');
}
if ($mode === 'apply' && $analytics['useSnippet'] !== true) {
  WP_CLI::error('Site Kit snippet placement must match the reviewed enabled state.');
}
$desired_consent = $mode === 'apply' ? $after : $before;
$desired_snippet = $mode === 'rollback' ? false : true;
if ($mode === 'restore-prior' && (!isset($args[2]) || $args[2] !== 'acknowledge-preconsent-tracking')) {
  WP_CLI::error('Restoring prior snippet placement reintroduces the observed tracking defect; explicit acknowledgment required.');
}
if ($mode === 'plan') {
  WP_CLI::line(wp_json_encode(array('migration'=>'20260923-tharringtonsmith-sitekit-consent-v1','site'=>home_url(),'cookieVersion'=>$cookie_version,'currentConsent'=>$consent,'applyConsent'=>$after,'sitekitUseSnippet'=>array('before'=>true,'apply'=>true,'safeRollback'=>false,'exactPriorRestore'=>true),'retainedDestination'=>$expected,'changesOnly'=>'Cookie Consent opt-in flag; Site Kit reporting, destinations and snippet settings remain intact on apply.')));
  return;
}
if ($consent === $desired_consent && $analytics['useSnippet'] === $desired_snippet) {
  WP_CLI::success('Already in requested state; no changes.');
  return;
}
$nonce = isset($args[1]) ? $args[1] : '';
if (!preg_match('/^[a-f0-9]{12}$/D', $nonce) || !class_exists('UpdraftPlus_Backup_History')) {
  WP_CLI::error('Fresh verified labeled remote database backup required.');
}
$history = UpdraftPlus_Backup_History::get_history();
$backup = null;
foreach ($history as $timestamp => $set) {
  if (isset($set['nonce']) && $set['nonce'] === $nonce && (int) $timestamp >= time() - 1800 && (int) $timestamp <= time()) {
    $backup = $set;
    break;
  }
}
$services = is_array($backup) && isset($backup['service']) ? array_values(array_filter((array) $backup['service'])) : array();
$sha = isset($backup['checksums']['sha256']['db0']) ? $backup['checksums']['sha256']['db0'] : '';
if (!is_array($backup) || empty($backup['db']) || empty($backup['db-size']) || empty($backup['label']) || strpos($backup['label'], 'pre-sitekit-consent-1.1.46-') !== 0 || !preg_match('/^[a-f0-9]{64}$/D', $sha) || !$services || in_array('none', $services, true)) {
  WP_CLI::error('Backup history is missing, stale, unlabeled or not remote database data.');
}
foreach (array('plugins','themes','uploads','others','wpcore','more') as $entity) {
  if (!empty($backup[$entity])) WP_CLI::error('Database-only backup required.');
}
global $updraftplus;
$log_path = trailingslashit($updraftplus->backups_dir_location()) . 'log.' . $nonce . '.txt';
// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Read-only local Updraft log; never returned.
$log = is_readable($log_path) ? file_get_contents($log_path) : '';
if (stripos($log, 'The backup succeeded and is now complete') === false || stripos($log, 'Recording as successfully uploaded') === false || preg_match('/Backup aborted|Backup failed|apparently unsuccessfully|errors occurred/i', $log)) {
  WP_CLI::error('Backup completion and remote upload are unverified.');
}
// Fail-safe rollback disables the original emitter before removing the gate.
if ($mode === 'rollback') {
  $analytics['useSnippet'] = false;
  update_option('googlesitekit_analytics-4_settings', $analytics);
}
update_option('mrn_silktide_consent_settings', $desired_consent);
if ($mode === 'restore-prior') {
  $analytics['useSnippet'] = true;
  update_option('googlesitekit_analytics-4_settings', $analytics);
}
if (get_option('mrn_silktide_consent_settings') !== $desired_consent || get_option('googlesitekit_analytics-4_settings') !== $analytics) {
  WP_CLI::error('Readback mismatch; inspect before retrying.');
}
WP_CLI::success('Verified migration state. Purge HTML/assets caches and run browser verification.');
