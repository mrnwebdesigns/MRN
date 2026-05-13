<?php
/**
 * Plugin Name: Updraft Local Retention (MU)
 * Description: Keeps only a limited number of local Updraft backup sets while leaving remote retention untouched.
 * Author: MRN Web Designs
 * Version: 0.1.0
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
