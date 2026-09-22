<?php
/**
 * Focused regression test for Updraft schedule repair.
 */

declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');
define('WP_CONTENT_DIR', __DIR__);

$mrn_test_actions   = array();
$mrn_test_events    = array();
$mrn_test_filters   = array(
	'updraftplus_group_backups_for_pruning' => true,
);
$mrn_test_intervals = array(
	'updraft_interval'          => 'daily',
	'updraft_interval_database' => 'daily',
	'updraft_starttime_files'   => '04:17',
	'updraft_starttime_db'      => '04:17',
);
$mrn_test_home_url = 'https://trilliant.mrndev.io/';

function home_url(string $path = ''): string {
	global $mrn_test_home_url;
	return rtrim($mrn_test_home_url, '/') . '/' . ltrim($path, '/');
}

function wp_parse_url(string $url, int $component = -1) {
	return parse_url($url, $component);
}

function add_action(string $hook, callable $callback, int $priority = 10, int $accepted_args = 1): void {
	global $mrn_test_actions;
	$mrn_test_actions[] = array($hook, $callback, $priority, $accepted_args);
}

function add_filter(string $hook, callable $callback, int $priority = 10, int $accepted_args = 1): void {
	add_action($hook, $callback, $priority, $accepted_args);
}

function has_filter(string $hook) {
	global $mrn_test_filters;
	return !empty($mrn_test_filters[$hook]) ? 10 : false;
}

function get_option(string $name, $default = false) {
	global $mrn_test_intervals;
	return $mrn_test_intervals[$name] ?? $default;
}

function update_option(string $name, $value): bool {
	global $mrn_test_intervals;
	$mrn_test_intervals[$name] = $value;
	return true;
}

function wp_clear_scheduled_hook(string $hook): void {
	global $mrn_test_events;
	unset($mrn_test_events[$hook]);
}

function mrn_environment_runtime_host_signal(): string {
	return 'production';
}

function sanitize_key(string $key): string {
	return strtolower((string) preg_replace('/[^a-z0-9_\-]/', '', $key));
}

function untrailingslashit(string $value): string {
	return rtrim($value, '/\\');
}

function apply_filters(string $hook, $value) {
	return $value;
}

function wp_next_scheduled(string $hook) {
	global $mrn_test_events;
	return $mrn_test_events[$hook] ?? false;
}

function wp_timezone(): DateTimeZone {
	return new DateTimeZone('UTC');
}

final class MRN_Test_Updraftplus {
	public array $scheduled = array();

	public function schedule_backup(string $interval): void {
		$this->scheduled['files'] = $interval;
	}

	public function schedule_backup_database(string $interval): void {
		$this->scheduled['database'] = $interval;
	}
}

require dirname(__DIR__) . '/mrn-updraft-local-retention.php';

if ('trilliant' !== mrn_updraft_backup_policy_get_sanitized_hostname()) {
	fwrite(STDERR, "The S3 path did not use the stable first-label site slug.\n");
	exit(1);
}

mrn_updraft_backup_policy_enforce_settings();
$expected_rules = mrn_updraft_backup_policy_get_retention_rules();

if (
	'23' !== ($mrn_test_intervals['updraft_retain'] ?? null) ||
	'100' !== ($mrn_test_intervals['updraft_retain_db'] ?? null) ||
	$expected_rules !== ($mrn_test_intervals['updraft_retain_extrarules'] ?? null)
) {
	fwrite(STDERR, "The 7/4/12 Updraft retention settings were not enforced.\n");
	exit(1);
}

$mrn_test_intervals['updraft_service'] = array('s3');
$mrn_test_intervals['updraft_s3'] = array(
	'settings' => array(
		'instance' => array('path' => 'mrn-backups/sites/trilliant'),
	),
);

$report = mrn_updraft_backup_policy_add_runtime_report(array('ok' => true));
if (empty($report['backup_policy']['compliant'])) {
	fwrite(STDERR, "The MainWP runtime report did not expose a compliant backup policy.\n");
	exit(1);
}

$expired_timestamp = time() - ((MRN_UPDRAFT_ROUTINE_RETENTION_MAX_DAYS + 1) * 86400);
if (!mrn_updraft_backup_policy_prune_expired_routine_backup(false, 'files', $expired_timestamp, 'plugins', 0, array(), 1)) {
	fwrite(STDERR, "Routine backups older than the policy boundary were not pruned.\n");
	exit(1);
}

$retained_timestamp = time() - ((MRN_UPDRAFT_ROUTINE_RETENTION_MAX_DAYS - 1) * 86400);
if (mrn_updraft_backup_policy_prune_expired_routine_backup(false, 'files', $retained_timestamp, 'plugins', 0, array(), 1)) {
	fwrite(STDERR, "Routine backups inside the policy boundary were pruned unexpectedly.\n");
	exit(1);
}

$updraftplus = new MRN_Test_Updraftplus();
mrn_updraft_local_retention_repair_backup_schedules();

if (array('files' => 'daily', 'database' => 'daily') !== $updraftplus->scheduled) {
	fwrite(STDERR, "Missing Updraft schedules were not repaired.\n");
	exit(1);
}

$updraftplus     = new MRN_Test_Updraftplus();
$mrn_test_events = array(
	'updraft_backup'          => 123,
	'updraft_backup_database' => 456,
);
mrn_updraft_local_retention_repair_backup_schedules();

if (array() !== $updraftplus->scheduled) {
	fwrite(STDERR, "Existing Updraft schedules were changed unexpectedly.\n");
	exit(1);
}

$next_start = mrn_updraft_local_retention_filter_files_start_time(false);
if (!is_int($next_start) || mrn_updraft_backup_policy_get_start_time() !== gmdate('H:i', $next_start) || $next_start <= time()) {
	fwrite(STDERR, "Configured Updraft start time was not enforced.\n");
	exit(1);
}

echo "Updraft schedule repair regression passed.\n";
