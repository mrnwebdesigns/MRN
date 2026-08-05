<?php
/**
 * Focused regression test for Updraft schedule repair.
 */

declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');
define('WP_CONTENT_DIR', __DIR__);

$mrn_test_actions   = array();
$mrn_test_events    = array();
$mrn_test_intervals = array(
	'updraft_interval'          => 'daily',
	'updraft_interval_database' => 'daily',
	'updraft_starttime_files'   => '04:17',
	'updraft_starttime_db'      => '04:17',
);

function add_action(string $hook, callable $callback, int $priority = 10): void {
	global $mrn_test_actions;
	$mrn_test_actions[] = array($hook, $callback, $priority);
}

function add_filter(string $hook, callable $callback, int $priority = 10): void {
	add_action($hook, $callback, $priority);
}

function get_option(string $name, $default = false) {
	global $mrn_test_intervals;
	return $mrn_test_intervals[$name] ?? $default;
}

function sanitize_key(string $key): string {
	return strtolower((string) preg_replace('/[^a-z0-9_\-]/', '', $key));
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
if (!is_int($next_start) || '04:17' !== gmdate('H:i', $next_start) || $next_start <= time()) {
	fwrite(STDERR, "Configured Updraft start time was not enforced.\n");
	exit(1);
}

echo "Updraft schedule repair regression passed.\n";
