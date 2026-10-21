<?php
/**
 * Focused regression test for Updraft schedule repair.
 */

declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');
define('WP_CONTENT_DIR', __DIR__);
define('HOUR_IN_SECONDS', 3600);

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
$mrn_test_environment = 'production';
$mrn_test_host_signal = 'production';
$mrn_test_backup_hostname = getenv('MRN_TEST_BACKUP_HOSTNAME');
$mrn_test_history = array();
$mrn_test_s3_filename = '';
$mrn_test_s3_delete_calls = 0;
if (false !== $mrn_test_backup_hostname) {
	define('MRN_UPDRAFT_BACKUP_HOSTNAME', $mrn_test_backup_hostname);
}

function trailingslashit(string $value): string { return rtrim($value, '/') . '/'; }

function wp_get_environment_type(): string {
	global $mrn_test_environment;
	return $mrn_test_environment;
}

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

function update_option(string $name, $value, $autoload = null): bool {
	global $mrn_test_intervals;
	$mrn_test_intervals[$name] = $value;
	return true;
}

function add_option(string $name, $value, $unused = '', $autoload = null): bool {
	global $mrn_test_intervals;
	if (array_key_exists($name, $mrn_test_intervals)) {
		return false;
	}
	$mrn_test_intervals[$name] = $value;
	return true;
}

function delete_option(string $name): bool {
	global $mrn_test_intervals;
	unset($mrn_test_intervals[$name]);
	return true;
}

function wp_unslash(string $value): string {
	return $value;
}

function wp_json_encode($value, int $flags = 0) {
	return json_encode($value, $flags);
}

final class WP_Error {
	private string $error_code;

	public function __construct(string $error_code, string $message = '') {
		$this->error_code = $error_code;
	}

	public function get_error_code(): string {
		return $this->error_code;
	}
}

function is_wp_error($value): bool {
	return $value instanceof WP_Error;
}

final class UpdraftPlus_Backup_History {
	public static function get_history() {
		global $mrn_test_history;
		return $mrn_test_history;
	}
}

final class UpdraftPlus_Storage_Methods_Interface {
	public static function get_storage_objects_and_ids(array $services): array {
		global $mrn_test_s3_storage;
		return in_array('s3', $services, true) ? array('s3' => array('object' => $mrn_test_s3_storage, 'instance_settings' => array('instance' => array(
			'path' => 'mrn-backups/sites/' . mrn_updraft_backup_policy_get_sanitized_hostname(),
			'accesskey' => 'fixture-access-key',
			'secretkey' => 'fixture-secret-key',
		)))) : array();
	}
}

final class MRN_Test_S3_Client {
	public function setSSL($ssl, $verify = true): void {}
	public function setSSLAuth($cert = null, $key = null, $ca = null): void {}
	public function setExceptions($enabled = true): void {}
	public function setRegion($region): void {}
	public function setEndpoint($host): void {}
	public function setSignatureVersion($version): void {}
	public function getBucketLocation($bucket): string { return 'US'; }
	public function getBucket(string $bucket, string $prefix, $marker, int $limit): array {
		global $mrn_test_s3_filename;
		return array(array('name' => 'sites/' . mrn_updraft_backup_policy_get_sanitized_hostname() . '/' . $mrn_test_s3_filename, 'size' => 1));
	}
}
class_alias(MRN_Test_S3_Client::class, 'UpdraftPlus_S3');

final class MRN_Test_S3_Storage {
	public function set_options(array $settings, bool $use_settings, string $instance_id): void {}
	public function delete(array $files, bool $use_settings, array $options): bool {
		global $mrn_test_s3_delete_calls;
		++$mrn_test_s3_delete_calls;
		return true;
	}
	public function getS3(...$arguments): MRN_Test_S3_Client { return new MRN_Test_S3_Client(); }
}

function wp_clear_scheduled_hook(string $hook): void {
	global $mrn_test_events;
	unset($mrn_test_events[$hook]);
}

function mrn_environment_runtime_host_signal($host = null): string {
	global $mrn_test_host_signal;
	return $mrn_test_host_signal;
}

function sanitize_key(string $key): string {
	return strtolower((string) preg_replace('/[^a-z0-9_\-]/', '', $key));
}

function sanitize_text_field(string $value): string {
	return trim($value);
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

if ('https://invalid.example/path' === $mrn_test_backup_hostname) {
	if ('' !== mrn_updraft_backup_policy_get_hostname() || mrn_updraft_backup_policy_get_remote_status()['isolated']) {
		fwrite(STDERR, "Invalid hostname override did not fail closed.\n");
		exit(1);
	}
	echo "Invalid backup hostname rejected.\n";
	exit(0);
}
$expected_slug = false === $mrn_test_backup_hostname ? 'trilliant-mrndev-io' : 'swccarepartners-org';
if ($expected_slug !== mrn_updraft_backup_policy_get_sanitized_hostname()) {
	fwrite(STDERR, "The S3 path did not use the full approved hostname.\n");
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
		'instance' => array('path' => 'mrn-backups/sites/' . $expected_slug, 'instance_enabled' => '1'),
	),
);

$report = mrn_updraft_backup_policy_add_runtime_report(array('ok' => true));
if (empty($report['backup_policy']['compliant'])) {
	fwrite(STDERR, "The MainWP runtime report did not expose a compliant backup policy.\n");
	exit(1);
}

$valid_storage = $mrn_test_intervals['updraft_s3'];
$mrn_test_intervals['updraft_s3']['settings']['instance']['instance_enabled'] = '0';
$mrn_test_intervals['updraft_s3']['settings']['legacy'] = array('path' => 'mrn-backups', 'instance_enabled' => '1');
if (mrn_updraft_backup_policy_get_remote_status()['isolated']) {
	fwrite(STDERR, "A disabled correct prefix concealed an active shared destination.\n");
	exit(1);
}
$mrn_test_intervals['updraft_s3']['settings']['instance']['instance_enabled'] = '1';
if (mrn_updraft_backup_policy_get_remote_status()['isolated']) {
	fwrite(STDERR, "An extra active shared destination passed isolation.\n");
	exit(1);
}
$mrn_test_intervals['updraft_s3']['settings']['legacy']['instance_enabled'] = '0';
if (!mrn_updraft_backup_policy_get_remote_status()['isolated']) {
	fwrite(STDERR, "Disabled historical storage incorrectly blocked isolation.\n");
	exit(1);
}
$mrn_test_intervals['updraft_s3'] = $valid_storage;
$mrn_test_filters['updraftplus_group_backups_for_pruning'] = false;
if (mrn_updraft_backup_policy_get_retention_status()['compliant']) {
	fwrite(STDERR, "Missing Premium retention was reported compliant.\n");
	exit(1);
}
$mrn_test_filters['updraftplus_group_backups_for_pruning'] = true;

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

foreach (array(
	array('staging', 'non_production', 'daily'),
	array('development', 'production', 'manual'),
	array('local', 'production', 'manual'),
	array('production', 'non_production', 'manual'),
	array('production', 'production', 'daily'),
) as $case) {
	list($mrn_test_environment, $mrn_test_host_signal, $expected_interval) = $case;
	mrn_updraft_backup_policy_enforce_settings();
	if ($expected_interval !== $mrn_test_intervals['updraft_interval'] || $expected_interval !== $mrn_test_intervals['updraft_interval_database']) {
		fwrite(STDERR, "Environment scheduling policy mismatch.\n");
		exit(1);
	}
}
foreach (array('plugins', 'themes', 'uploads', 'others', 'mu-plugins') as $entity) {
	if ('1' !== ($mrn_test_intervals['updraft_include_' . $entity] ?? null)) {
		fwrite(STDERR, "Required backup entity is not included.\n");
		exit(1);
	}
}

$mrn_test_intervals[MRN_UPDRAFT_BACKUP_POLICY_EXCLUDED_OPTION] = '1';
$mrn_test_intervals['updraft_interval'] = 'weekly';
$mrn_test_intervals['updraft_interval_database'] = 'weekly';
mrn_updraft_backup_policy_enforce_settings();
if ('weekly' !== $mrn_test_intervals['updraft_interval'] || 'weekly' !== $mrn_test_intervals['updraft_interval_database']) {
	fwrite(STDERR, "An excluded site had its Updraft schedule changed.\n");
	exit(1);
}
$excluded_status = mrn_updraft_backup_policy_get_retention_status();
if (empty($excluded_status['excluded']) || 'excluded' !== ($excluded_status['management_mode'] ?? '') || !empty($excluded_status['compliant'])) {
	fwrite(STDERR, "The runtime report did not identify an explicit policy exclusion.\n");
	exit(1);
}
if (mrn_updraft_backup_policy_prune_expired_routine_backup(false, 'files', $expired_timestamp, 'plugins', 0, array(), 1)) {
	fwrite(STDERR, "An excluded site's backup history was pruned by the MRN policy.\n");
	exit(1);
}
if (123 !== mrn_updraft_local_retention_filter_files_start_time(123)) {
	fwrite(STDERR, "An excluded site's backup start time was rewritten by the MRN policy.\n");
	exit(1);
}

$mrn_test_intervals['updraft_service'] = array('s3', 'dropbox');
$mrn_test_intervals['updraft_interval'] = 'daily';
$mrn_test_intervals['updraft_interval_database'] = 'daily';
$preview_request = array(
	'protocol'     => 1,
	'operation'    => 'preview_decommission',
	'expected_host' => 'trilliant.mrndev.io',
	'request_ref'  => null,
	'payload'      => array(),
);
$preview = MRN_Updraft_MainWP_Policy::dispatch(
	array(),
	array(
		'function' => 'extra_execution',
		MRN_Updraft_MainWP_Policy::REQUEST_KEY => wp_json_encode($preview_request, JSON_UNESCAPED_SLASHES),
	)
);
if (empty($preview['ok']) || array('s3', 'dropbox') !== ($preview['data']['remote_services_before'] ?? null) || array() !== ($preview['data']['remote_services_after'] ?? null) || false !== ($preview['data']['historical_remote_objects_deleted'] ?? null)) {
	fwrite(STDERR, "The MainWP preview did not accurately show remote decommission scope.\n");
	exit(1);
}

$valid_s3_settings = $mrn_test_intervals['updraft_s3'] ?? array();
$mrn_test_intervals['updraft_s3'] = array('settings' => 'invalid-shape');
$invalid_preview = MRN_Updraft_MainWP_Policy::dispatch(array(), array(
	'function' => 'extra_execution',
	MRN_Updraft_MainWP_Policy::REQUEST_KEY => wp_json_encode($preview_request, JSON_UNESCAPED_SLASHES),
));
if (!empty($invalid_preview['ok']) || 'mrn_updraft_decommission_state_unavailable' !== ($invalid_preview['error_code'] ?? '')) {
	fwrite(STDERR, "The decommission preview accepted an unknown S3 storage shape.\n");
	exit(1);
}
$mrn_test_intervals['updraft_s3'] = $valid_s3_settings;

$mrn_test_intervals[MRN_UPDRAFT_BACKUP_POLICY_EXCLUDED_OPTION] = '0';
$mrn_test_intervals['updraft_interval'] = 'daily';
$mrn_test_intervals['updraft_interval_database'] = 'daily';
$mrn_test_intervals['updraft_service'] = array('s3', 'dropbox');
$mrn_test_intervals['updraft_s3'] = array('settings' => array('instance' => array(
	'path' => 'mrn-backups/sites/' . $expected_slug,
	'accesskey' => 'fixture-access-key',
	'secretkey' => 'fixture-secret-key',
	'instance_enabled' => '1',
)));
$mrn_test_s3_storage = new MRN_Test_S3_Storage();
$nonce = 'abcdef123456';
$mrn_test_s3_filename = 'backup_' . gmdate('Y-m-d-H-i') . '_trilliant_' . $nonce . '-db.gz';
$backup_timestamp = time();
$mrn_test_history = array($backup_timestamp => array(
	'nonce' => $nonce,
	'db' => array($mrn_test_s3_filename),
	'label' => 'MRN MainWP database-only backup',
	'service' => array('s3'),
	'service_instance_ids' => array('s3' => array('instance')),
	'native' => true,
));
// Exercise the new maintenance proof using the actual policy adapter and bounded log reader.
$maintenance_logs = sys_get_temp_dir() . '/mrn-maintenance-test-' . bin2hex(random_bytes(8));
mkdir($maintenance_logs, 0700);
$updraftplus = new class($maintenance_logs) {
	private $directory;
	public function __construct($directory) { $this->directory = $directory; }
	public function backups_dir_location() { return $this->directory; }
};
$maintenance_log = $maintenance_logs . '/log.' . $nonce . '.txt';
$complete_log = 'Recording as successfully uploaded: ' . $mrn_test_s3_filename . "\nThe backup succeeded and is now complete\n";
$maintenance_check = static function ($ok, $label) {
	if (!$ok) { fwrite(STDERR, 'Maintenance backup regression: ' . $label . "\n"); exit(1); }
};
$maintenance_check(is_wp_error(MRN_Updraft_MainWP_Policy::maintenance_backup($nonce)), 'missing log');
file_put_contents($maintenance_log, 'Backup apparently succeeded');
$maintenance_check(is_wp_error(MRN_Updraft_MainWP_Policy::maintenance_backup($nonce)), 'generic success is insufficient');
file_put_contents($maintenance_log, $complete_log);
$proof = MRN_Updraft_MainWP_Policy::maintenance_backup($nonce);
$maintenance_check(is_array($proof) && true === $proof['remote_verified'] && $proof['expires_at'] === ($backup_timestamp + 7200) * 1000, 'remote proof with history expiry');
file_put_contents($maintenance_log, $complete_log . "Warning: incomplete archive\n");
$maintenance_check(is_wp_error(MRN_Updraft_MainWP_Policy::maintenance_backup($nonce)), 'warning refused');
file_put_contents($maintenance_log, $complete_log);
$saved_filename = $mrn_test_s3_filename;
$mrn_test_s3_filename = 'absent-remote-object';
$maintenance_check(is_wp_error(MRN_Updraft_MainWP_Policy::maintenance_backup($nonce)), 'missing remote archive');
$mrn_test_s3_filename = $saved_filename;
$saved_history = $mrn_test_history;
$mrn_test_history = array(time() - 7201 => reset($saved_history));
$maintenance_check(is_wp_error(MRN_Updraft_MainWP_Policy::maintenance_backup($nonce)), 'expiry cannot renew on status read');
$mrn_test_history = $saved_history;
$mrn_test_history[$backup_timestamp]['label'] = 'unrelated backup';
$maintenance_check(is_wp_error(MRN_Updraft_MainWP_Policy::maintenance_backup($nonce)), 'exact deployment label');
$mrn_test_history = $saved_history;
unlink($maintenance_log);
rmdir($maintenance_logs);
$preview_request['payload'] = array();
$preview_request['request_ref'] = null;
$preview_request['operation'] = 'preview_decommission';
$preview = MRN_Updraft_MainWP_Policy::dispatch(array(), array(
	'function' => 'extra_execution',
	MRN_Updraft_MainWP_Policy::REQUEST_KEY => wp_json_encode($preview_request, JSON_UNESCAPED_SLASHES),
));
if (empty($preview['ok'])) {
	fwrite(STDERR, "The backup-backed decommission preview failed.\n");
	exit(1);
}
$write_request = array(
	'protocol' => 1,
	'operation' => 'decommission_policy',
	'expected_host' => 'trilliant.mrndev.io',
	'request_ref' => '00000000-0000-4000-8000-000000000001',
	'payload' => array(
		'plan_sha256' => $preview['data']['plan_sha256'],
		'as_of' => $preview['data']['as_of'],
		'backup_nonce' => $nonce,
	),
);
$decommissioned = MRN_Updraft_MainWP_Policy::dispatch(array(), array(
	'function' => 'extra_execution',
	MRN_Updraft_MainWP_Policy::REQUEST_KEY => wp_json_encode($write_request, JSON_UNESCAPED_SLASHES),
));
if (
	empty($decommissioned['ok']) ||
	'decommissioned' !== ($decommissioned['data']['status'] ?? '') ||
	array() !== $mrn_test_intervals['updraft_service'] ||
	'0' !== $mrn_test_intervals['updraft_s3']['settings']['instance']['instance_enabled'] ||
	'manual' !== $mrn_test_intervals['updraft_interval'] ||
	'manual' !== $mrn_test_intervals['updraft_interval_database'] ||
	'1' !== $mrn_test_intervals[MRN_UPDRAFT_BACKUP_POLICY_EXCLUDED_OPTION] ||
	false !== $decommissioned['data']['historical_remote_objects_deleted'] ||
	1 !== count($mrn_test_history) ||
	0 !== $mrn_test_s3_delete_calls ||
	array() !== $mrn_test_events
) {
	fwrite(STDERR, "The backup-gated decommission did not disable destinations and schedules without deleting history.\n");
	exit(1);
}

echo "Updraft retention, isolation, expiry, and schedule regression passed.\n";
