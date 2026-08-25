<?php
/**
 * Plugin Name: MRN Updraft Backup Policy
 * Description: Enforces the MRN Updraft backup policy, limits local backup sets, and repairs missing scheduled events.
 * Author: MRN Web Designs
 * Version: 0.5.0
 */

defined('ABSPATH') || exit;

/**
 * Cron hook used for scheduled local backup cleanup.
 */
const MRN_UPDRAFT_LOCAL_RETENTION_CRON_HOOK = 'mrn_updraft_local_retention_cleanup';

/**
 * Default number of local backup sets to retain.
 */
const MRN_UPDRAFT_LOCAL_RETENTION_MAX_SETS = 4;

/**
 * Resolve the canonical site hostname used for deterministic scheduling and
 * remote-prefix validation.
 */
function mrn_updraft_backup_policy_get_hostname(): string {
	$hostname = wp_parse_url(home_url('/'), PHP_URL_HOST);
	$hostname = is_string($hostname) ? strtolower(rtrim($hostname, '.')) : '';

	return preg_match('/^[a-z0-9.-]+$/', $hostname) ? $hostname : '';
}

/**
 * Resolve the stable, environment-independent site slug used for the
 * BACKUP_POLICY.md `sites/<slug>` S3 convention: the hostname's first label
 * (dots/other separators sanitized to hyphens within that label only).
 *
 * This is deliberately NOT the full hostname. The same logical site moves
 * across environments with different hosts (`trilliant.localhost` locally,
 * `trilliant.mrndev.io` in review, a custom domain in production) and must
 * keep one stable S3 prefix throughout rather than fragmenting per host.
 */
function mrn_updraft_backup_policy_get_sanitized_hostname(): string {
	$hostname = mrn_updraft_backup_policy_get_hostname();
	if ('' === $hostname) {
		return '';
	}

	$labels = explode('.', $hostname);
	$slug   = $labels[0];

	$sanitized = preg_replace('/[^a-z0-9]+/', '-', $slug);

	return is_string($sanitized) ? trim($sanitized, '-') : '';
}

/**
 * Determine whether the current site is a development/review environment
 * (for example `*.mrndev.io`) rather than staging or production.
 *
 * Reuses mrn-environment-runtime's conservative host classification instead
 * of duplicating suffix-matching logic. Defaults to false (production-like)
 * when that component is unavailable, matching its own conservative default.
 */
function mrn_updraft_backup_policy_is_dev_environment(): bool {
	if (!function_exists('mrn_environment_runtime_host_signal')) {
		return false;
	}

	return 'non_production' === mrn_environment_runtime_host_signal();
}

/**
 * Calculate a stable daily backup time between 01:00 and 04:59.
 */
function mrn_updraft_backup_policy_get_start_time(): string {
	$hostname = mrn_updraft_backup_policy_get_hostname();
	if ('' === $hostname) {
		return '03:00';
	}

	$minute_index = (int) ((int) sprintf('%u', crc32($hostname)) % 240);
	$hour = 1 + intdiv($minute_index, 60);
	$minute = $minute_index % 60;

	return sprintf('%02d:%02d', $hour, $minute);
}

/**
 * Read an Updraft option through its own options layer when available.
 *
 * @param string $name    Option name.
 * @param mixed  $default Default value.
 * @return mixed
 */
function mrn_updraft_backup_policy_get_option(string $name, $default = false) {
	if (class_exists('UpdraftPlus_Options') && method_exists('UpdraftPlus_Options', 'get_updraft_option')) {
		return UpdraftPlus_Options::get_updraft_option($name, $default);
	}

	return get_option($name, $default);
}

/**
 * Update an Updraft option through its own options layer when available.
 *
 * @param string $name  Option name.
 * @param mixed  $value New value.
 */
function mrn_updraft_backup_policy_update_option(string $name, $value): void {
	if (class_exists('UpdraftPlus_Options') && method_exists('UpdraftPlus_Options', 'update_updraft_option')) {
		UpdraftPlus_Options::update_updraft_option($name, $value);
		return;
	}

	update_option($name, $value);
}

/**
 * Enforce the non-secret MRN backup policy on every stack runtime.
 *
 * Remote storage credentials and destinations are deliberately excluded. They
 * must be provisioned separately and are validated below without being changed.
 */
function mrn_updraft_backup_policy_enforce_settings(): void {
	$start_time = mrn_updraft_backup_policy_get_start_time();
	$desired = array(
		'updraft_interval'          => 'daily',
		'updraft_interval_database' => 'daily',
		'updraft_retain'            => '4',
		'updraft_retain_db'         => '4',
		'updraft_delete_local'      => '1',
		'updraft_include_wpcore'    => '0',
		'updraft_starttime_files'   => $start_time,
		'updraft_starttime_db'      => $start_time,
	);
	$schedule_changed = false;

	foreach ($desired as $name => $value) {
		if ((string) mrn_updraft_backup_policy_get_option($name, '') === (string) $value) {
			continue;
		}

		mrn_updraft_backup_policy_update_option($name, $value);
		if (in_array($name, array('updraft_interval', 'updraft_interval_database', 'updraft_starttime_files', 'updraft_starttime_db'), true)) {
			$schedule_changed = true;
		}
	}

	if ($schedule_changed) {
		wp_clear_scheduled_hook('updraft_backup');
		wp_clear_scheduled_hook('updraft_backup_database');
	}
}
add_action('init', 'mrn_updraft_backup_policy_enforce_settings', 15);

/**
 * Inspect S3 configuration without returning or changing credential values.
 *
 * @return array{configured:bool,isolated:bool,expected_suffix:string}
 */
function mrn_updraft_backup_policy_get_remote_status(): array {
	$hostname = mrn_updraft_backup_policy_get_hostname();
	$expected_suffix = '' !== $hostname ? 'sites/' . $hostname : '';
	$services = array_values((array) mrn_updraft_backup_policy_get_option('updraft_service', array()));
	$status = array(
		'configured'      => in_array('s3', $services, true),
		'isolated'        => false,
		'expected_suffix' => $expected_suffix,
	);

	if (!$status['configured'] || '' === $expected_suffix) {
		return $status;
	}

	$s3 = mrn_updraft_backup_policy_get_option('updraft_s3', array());
	$settings_list = is_array($s3) && isset($s3['settings']) && is_array($s3['settings'])
		? $s3['settings']
		: array($s3);

	foreach ($settings_list as $settings) {
		if (!is_array($settings) || empty($settings['path']) || !is_string($settings['path'])) {
			continue;
		}

		$path = trim($settings['path'], '/');
		$suffix_with_separator = '/' . $expected_suffix;
		if ($path === $expected_suffix || substr($path, -strlen($suffix_with_separator)) === $suffix_with_separator) {
			$status['isolated'] = true;
			break;
		}
	}

	return $status;
}

/**
 * Warn administrators when remote backups are missing or share a bucket root.
 */
function mrn_updraft_backup_policy_remote_notice(): void {
	if (!current_user_can('manage_options')) {
		return;
	}

	$status = mrn_updraft_backup_policy_get_remote_status();
	if ($status['configured'] && $status['isolated']) {
		return;
	}

	$message = !$status['configured']
		? __('MRN backup policy: Amazon S3 remote storage is not configured.', 'mrn-updraft-local-retention')
		: sprintf(
			/* translators: %s is the required non-secret S3 path suffix. */
			__('MRN backup policy: the S3 destination must use a unique path ending in %s. Remote retention is unsafe while sites share a bucket root.', 'mrn-updraft-local-retention'),
			$status['expected_suffix']
		);

	printf(
		'<div class="notice notice-error"><p>%s</p></div>',
		esc_html($message)
	);
}
add_action('admin_notices', 'mrn_updraft_backup_policy_remote_notice');

/**
 * Resolve the next occurrence of a configured Updraft HH:MM start time.
 *
 * @param int|false $default_timestamp Updraft's proposed first run.
 * @param string    $option_name       Start-time option name.
 * @return int|false
 */
function mrn_updraft_local_retention_get_next_start_time($default_timestamp, string $option_name) {
	$start_time = get_option($option_name, '');
	if (!is_string($start_time) || 1 !== preg_match('/^(\d{2}):(\d{2})$/', $start_time, $matches)) {
		return $default_timestamp;
	}

	$hour   = (int) $matches[1];
	$minute = (int) $matches[2];
	if ($hour > 23 || $minute > 59) {
		return $default_timestamp;
	}

	$now      = new DateTimeImmutable('now', wp_timezone());
	$next_run = $now->setTime($hour, $minute);
	if ($next_run <= $now) {
		$next_run = $next_run->modify('+1 day');
	}

	return $next_run->getTimestamp();
}

/**
 * Enforce the configured file backup start time after all Updraft filters.
 *
 * @param int|false $default_timestamp Updraft's proposed first run.
 * @return int|false
 */
function mrn_updraft_local_retention_filter_files_start_time($default_timestamp) {
	return mrn_updraft_local_retention_get_next_start_time($default_timestamp, 'updraft_starttime_files');
}
add_filter('updraftplus_schedule_firsttime_files', 'mrn_updraft_local_retention_filter_files_start_time', 999);

/**
 * Enforce the configured database backup start time after all Updraft filters.
 *
 * @param int|false $default_timestamp Updraft's proposed first run.
 * @return int|false
 */
function mrn_updraft_local_retention_filter_database_start_time($default_timestamp) {
	return mrn_updraft_local_retention_get_next_start_time($default_timestamp, 'updraft_starttime_db');
}
add_filter('updraftplus_schedule_firsttime_db', 'mrn_updraft_local_retention_filter_database_start_time', 999);

/**
 * Ensure the recurring cleanup event exists.
 */
function mrn_updraft_local_retention_schedule_cleanup(): void {
	$event = function_exists('wp_get_scheduled_event')
		? wp_get_scheduled_event(MRN_UPDRAFT_LOCAL_RETENTION_CRON_HOOK)
		: false;

	if ($event && isset($event->schedule) && 'daily' === $event->schedule) {
		$event_hour = (int) wp_date('G', (int) $event->timestamp, wp_timezone());
		$event_minute = (int) wp_date('i', (int) $event->timestamp, wp_timezone());

		if (3 === $event_hour && 0 === $event_minute) {
			return;
		}
	}

	if ($event) {
		wp_clear_scheduled_hook(MRN_UPDRAFT_LOCAL_RETENTION_CRON_HOOK);
	}

	$timezone = wp_timezone();
	$now = new DateTimeImmutable('now', $timezone);
	$next_run = $now->setTime(3, 0);

	if ($next_run <= $now) {
		$next_run = $next_run->modify('+1 day');
	}

	wp_schedule_event($next_run->getTimestamp(), 'daily', MRN_UPDRAFT_LOCAL_RETENTION_CRON_HOOK);
}
add_action('init', 'mrn_updraft_local_retention_schedule_cleanup');

/**
 * Restore Updraft's file and database cron events when a database restore leaves
 * the saved schedule settings in place but omits the corresponding WP-Cron rows.
 */
function mrn_updraft_local_retention_repair_backup_schedules(): void {
	global $updraftplus;

	if (!is_object($updraftplus)) {
		return;
	}

	$schedules = array(
		array(
			'hook'     => 'updraft_backup',
			'option'   => 'updraft_interval',
			'scheduler' => 'schedule_backup',
		),
		array(
			'hook'     => 'updraft_backup_database',
			'option'   => 'updraft_interval_database',
			'scheduler' => 'schedule_backup_database',
		),
	);

	foreach ($schedules as $schedule) {
		$interval = get_option($schedule['option'], 'manual');
		$interval = is_string($interval) ? sanitize_key($interval) : 'manual';

		if ('manual' === $interval || '' === $interval) {
			continue;
		}

		if (false !== wp_next_scheduled($schedule['hook'])) {
			continue;
		}

		if (!method_exists($updraftplus, $schedule['scheduler'])) {
			continue;
		}

		$updraftplus->{$schedule['scheduler']}($interval);
	}
}
add_action('init', 'mrn_updraft_local_retention_repair_backup_schedules', 20);

/**
 * Determine the local Updraft storage directory.
 */
function mrn_updraft_local_retention_get_directory(): string {
	$directory = defined('UPDRAFT_DIR') && is_string(UPDRAFT_DIR) && '' !== UPDRAFT_DIR
		? UPDRAFT_DIR
		: WP_CONTENT_DIR . '/updraft';

	$directory = apply_filters('mrn_updraft_local_retention_directory', $directory);

	if (!is_string($directory) || '' === $directory) {
		return '';
	}

	return untrailingslashit($directory) . '/';
}

/**
 * Resolve the retention limit.
 */
function mrn_updraft_local_retention_get_max_sets(): int {
	$max_sets = (int) apply_filters('mrn_updraft_local_retention_max_sets', MRN_UPDRAFT_LOCAL_RETENTION_MAX_SETS);

	return max(0, $max_sets);
}

/**
 * Convert a local Updraft backup filename to a backup-set key.
 */
function mrn_updraft_local_retention_get_set_key(string $filename): string {
	$name = strtolower($filename);
	if (0 !== strpos($name, 'backup_')) {
		return '';
	}

	$basename = preg_replace('/\.(?:zip|gz|bz2|crypt|tar)(?:\.\d+)?$/i', '', $filename);
	if (!is_string($basename) || '' === $basename) {
		return '';
	}

	$dash_pos = strrpos($basename, '-');
	if (false === $dash_pos || 0 === $dash_pos) {
		return '';
	}

	return substr($basename, 0, $dash_pos);
}

/**
 * Clean local Updraft backups down to the configured number of sets.
 */
function mrn_updraft_local_retention_cleanup_local_backups(): void {
	$directory = mrn_updraft_local_retention_get_directory();
	if ('' === $directory || !is_dir($directory)) {
		return;
	}

	$max_sets = mrn_updraft_local_retention_get_max_sets();
	$candidates = glob($directory . 'backup_*');
	if (!is_array($candidates) || empty($candidates)) {
		return;
	}

	$sets = array();

	foreach ($candidates as $candidate) {
		if (!is_string($candidate) || !is_file($candidate)) {
			continue;
		}

		$key = mrn_updraft_local_retention_get_set_key((string) basename($candidate));
		if ('' === $key) {
			continue;
		}

		if (!isset($sets[$key])) {
			$sets[$key] = array(
				'mtime' => filemtime($candidate) ?: 0,
				'files' => array(),
			);
		}

		$mtime = filemtime($candidate) ?: 0;
		if ($mtime > $sets[$key]['mtime']) {
			$sets[$key]['mtime'] = $mtime;
		}

		$sets[$key]['files'][] = $candidate;
	}

	if (count($sets) <= $max_sets) {
		return;
	}

	uasort(
		$sets,
		static function (array $a, array $b): int {
			return $b['mtime'] <=> $a['mtime'];
		}
	);

	$set_keys = array_keys($sets);
	$keys_to_delete = array_slice($set_keys, $max_sets);

	foreach ($keys_to_delete as $key_to_delete) {
		if (!isset($sets[$key_to_delete]['files']) || !is_array($sets[$key_to_delete]['files'])) {
			continue;
		}

		foreach ($sets[$key_to_delete]['files'] as $file_to_delete) {
			if (is_string($file_to_delete) && is_file($file_to_delete)) {
				wp_delete_file($file_to_delete);
			}
		}
	}
}

add_action(MRN_UPDRAFT_LOCAL_RETENTION_CRON_HOOK, 'mrn_updraft_local_retention_cleanup_local_backups');
add_action('updraftplus_backup_complete', 'mrn_updraft_local_retention_cleanup_local_backups');
add_action('updraft_backup_complete', 'mrn_updraft_local_retention_cleanup_local_backups');
