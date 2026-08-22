<?php

$temporary_root = sys_get_temp_dir() . '/mrn-loader-report-' . bin2hex(random_bytes(4));
$content_root = $temporary_root . '/wp-content';
mkdir($content_root . '/mu-plugins', 0777, true);
mkdir($content_root . '/plugins', 0777, true);

define('ABSPATH', $temporary_root . '/');
define('WP_CONTENT_DIR', $content_root);
define('WP_PLUGIN_DIR', $content_root . '/plugins');

$mrn_test_actions = array();
$mrn_test_filters = array();
/** @var array<string,array<string,mixed>> $mrn_test_routes */
$mrn_test_routes = array();
$mrn_test_can_manage = false;

function add_action($hook, $callback) {
    global $mrn_test_actions;
    $mrn_test_actions[$hook][] = $callback;
}

function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {
    global $mrn_test_filters;
    $mrn_test_filters[$hook][] = array($callback, $priority, $accepted_args);
}

function apply_filters($hook, $value) {
    return $value;
}

function sanitize_key($value) {
    return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value));
}

function wp_unslash($value) {
    return $value;
}

function wp_parse_url($url, $component = -1) {
    return parse_url($url, $component);
}

function home_url($path = '/') {
    return 'https://example.test' . $path;
}

function wp_get_environment_type() {
    return 'local';
}

function get_option($name, $default = false) {
    $options = array(
        'active_plugins' => array(),
        'template'       => 'mrn-base-stack',
        'stylesheet'     => 'example-child',
    );
    return $options[$name] ?? $default;
}

function current_user_can($capability) {
    global $mrn_test_can_manage;
    return $capability === 'manage_options' && $mrn_test_can_manage;
}

function register_rest_route($namespace, $route, $arguments) {
    global $mrn_test_routes;
    $mrn_test_routes[$namespace . $route] = $arguments;
}

function wp_normalize_path($path) {
    return str_replace('\\', '/', $path);
}

require dirname(__DIR__, 2) . '/mu-plugins/mrn-loader.php';

function mrn_test_assert($condition, $message) {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$component = $content_root . '/mu-plugins/fixture';
mkdir($component, 0777, true);
file_put_contents($component . '/b.txt', "second\n");
file_put_contents($component . '/a.txt', "first\n");

$records = array();
foreach (array('a.txt', 'b.txt') as $relative) {
    $path = $component . '/' . $relative;
    $records[] = $relative . "\0" . hash_file('sha256', $path) . "\0" . filesize($path) . "\n";
}
$expected_hash = hash('sha256', implode('', $records));
$actual_hash = mrn_loader_tree_hash($component);
mrn_test_assert(is_array($actual_hash), 'tree hash should be available');
mrn_test_assert($actual_hash['sha256'] === $expected_hash, 'tree hash must match sha256-tree-v1');
mrn_test_assert($actual_hash['file_count'] === 2, 'tree hash should count deployable files');

$wrapper = $content_root . '/mu-plugins/fixture.php';
file_put_contents($wrapper, "<?php require_once __DIR__ . '/fixture/fixture.php';\n");
mrn_test_assert(
    mrn_loader_is_subdirectory_wrapper($wrapper, 'fixture'),
    'subdirectory bootstrap must not be classified as a legacy collision'
);
file_put_contents($wrapper, "<?php echo 'legacy implementation';\n");
mrn_test_assert(
    !mrn_loader_is_subdirectory_wrapper($wrapper, 'fixture'),
    'standalone flat implementation must be classified as a legacy collision'
);

mrn_loader_register_runtime_report_route();
$route = $mrn_test_routes['mrn/v1/stack-report'] ?? null;
mrn_test_assert(is_array($route), 'REST route should register');
mrn_test_assert(is_callable($route['permission_callback']), 'REST route needs a permission callback');
mrn_test_assert(!$route['permission_callback'](), 'anonymous runtime-report access must be denied');
$mrn_test_can_manage = true;
mrn_test_assert($route['permission_callback'](), 'manage_options access must be allowed');

$existing = array('another_extension' => true);
mrn_test_assert(
    mrn_loader_handle_mainwp_child_execution($existing, array()) === $existing,
    'unrelated MainWP child execution must remain untouched'
);
$mainwp_response = mrn_loader_handle_mainwp_child_execution(
    $existing,
    array('mrn_stack_report_action' => 'report')
);
mrn_test_assert(!empty($mainwp_response['success']), 'signed MainWP transport should succeed');
mrn_test_assert(
    isset($mainwp_response['report']['schema_version']),
    'signed MainWP transport should return the runtime report'
);

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($temporary_root, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST
);
foreach ($iterator as $path) {
    $path->isDir() ? rmdir($path->getPathname()) : unlink($path->getPathname());
}
rmdir($temporary_root);

echo "PASS: loader runtime-report contract\n";
