<?php
/**
 * Plugin Name: MRN Loader
 * Description: Loads MRN MU plugins from known subfolders in /wp-content/mu-plugins.
 * Version: 1.5.0
 */

defined('ABSPATH') || exit;

define('MRN_LOADER_VERSION', '1.5.0');
define('MRN_LOADER_RUNTIME_REPORT_SCHEMA_VERSION', 1);
define('MRN_LOADER_HASH_ALGORITHM', 'sha256-tree-v1');

/**
 * Return the only MU component entrypoints the loader may execute.
 *
 * @return array
 */
function mrn_loader_known_entries() {
    return array(
        'mrn-admin-data-post-types'       => WP_CONTENT_DIR . '/mu-plugins/mrn-admin-data-post-types/mrn-admin-data-post-types.php',
        'mrn-admin-ui-css'                => WP_CONTENT_DIR . '/mu-plugins/mrn-admin-ui-css/mrn-admin-ui-css.php',
        'mrn-dashboard-support'           => WP_CONTENT_DIR . '/mu-plugins/mrn-dashboard-support/mrn-dashboard-support.php',
        'mrn-disable-comments'            => WP_CONTENT_DIR . '/mu-plugins/mrn-disable-comments/mrn-disable-comments.php',
        'mrn-editor-lockdown'             => WP_CONTENT_DIR . '/mu-plugins/mrn-editor-lockdown/mrn-editor-lockdown.php',
        'mrn-environment-runtime'         => WP_CONTENT_DIR . '/mu-plugins/mrn-environment-runtime/mrn-environment-runtime.php',
        'mrn-public-security-hardening'   => WP_CONTENT_DIR . '/mu-plugins/mrn-public-security-hardening/mrn-public-security-hardening.php',
        'mrn-schema-bridge'               => WP_CONTENT_DIR . '/mu-plugins/mrn-schema-bridge/mrn-schema-bridge.php',
        'mrn-shared-assets'               => WP_CONTENT_DIR . '/mu-plugins/mrn-shared-assets/mrn-shared-assets.php',
        'mrn-site-colors'                 => WP_CONTENT_DIR . '/mu-plugins/mrn-site-colors/mrn-site-colors.php',
        'mrn-updraft-local-retention'     => WP_CONTENT_DIR . '/mu-plugins/mrn-updraft-local-retention/mrn-updraft-local-retention.php',
        'mrn-active-style-guide'          => WP_CONTENT_DIR . '/mu-plugins/mrn-active-style-guide/mrn-active-style-guide.php',
    );
}

/**
 * Include a plugin bootstrap file once, only if it has not already been loaded.
 *
 * @param mixed $file Candidate file path.
 * @return bool
 */
function mrn_loader_include_once_if_needed($file) {
    if (!is_string($file) || $file === '' || !file_exists($file)) {
        return false;
    }

    $target_realpath = realpath($file);
    if ($target_realpath === false) {
        return false;
    }

    $allowed_realpaths = array_filter(array_map('realpath', mrn_loader_known_entries()));
    if (!in_array($target_realpath, $allowed_realpaths, true)) {
        return false;
    }

    foreach (get_included_files() as $included_file) {
        $included_realpath = realpath($included_file);
        if ($included_realpath !== false && $included_realpath === $target_realpath) {
            return true;
        }
    }

    try {
        // nosemgrep: semgrep.php-dynamic-include -- Realpath must match the immutable allowlist above.
        require_once $target_realpath;
        return true;
    } catch (Throwable $e) {
        error_log(
            sprintf(
                '[MRN Loader] Failed loading %s: %s',
                $target_realpath,
                $e->getMessage()
            )
        );

        return false;
    }
}

/**
 * Known MRN plugin entrypoints under /wp-content/mu-plugins/<slug>/<entry-file>.
 */
$mrn_loader_entries = mrn_loader_known_entries();

$mrn_loader_runtime_components = array();

foreach ($mrn_loader_entries as $component_slug => $entry_file) {
    if (!is_string($entry_file) || $entry_file === '') {
        continue;
    }

    $mrn_loader_runtime_components[$component_slug] = array(
        'entry_file' => $entry_file,
        'loaded'     => mrn_loader_include_once_if_needed($entry_file),
    );
}

/**
 * Read a WordPress plugin or theme Version header without exposing its path.
 *
 * @param string $file Header file.
 * @return string
 */
function mrn_loader_read_version_header($file) {
    if (!is_string($file) || !is_readable($file)) {
        return '';
    }

    $contents = file_get_contents($file, false, null, 0, 16384);
    if (!is_string($contents)) {
        return '';
    }

    if (!preg_match('/^[ \\t\\/*#]*Version:[ \\t]*([^\\r\\n*]+)/mi', $contents, $matches)) {
        return '';
    }

    return trim($matches[1]);
}

/**
 * Collect deployable files for the cross-runtime sha256-tree-v1 digest.
 *
 * @param string $source   Source file or directory.
 * @param string $relative Relative path inside the source.
 * @param array  $files    Collected files keyed by portable relative path.
 * @return bool
 */
function mrn_loader_collect_digest_files($source, $relative, &$files) {
    $excluded_directories = array(
        '.git',
        '.tmp',
        'node_modules',
        'playwright-report',
        'test-results',
        'zip',
    );

    if (is_link($source)) {
        return false;
    }

    if (is_file($source)) {
        $files[$relative !== '' ? $relative : basename($source)] = $source;
        return true;
    }

    if (!is_dir($source)) {
        return false;
    }

    $entries = scandir($source);
    if (!is_array($entries)) {
        return false;
    }

    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..' || $entry === '.DS_Store') {
            continue;
        }

        $path = $source . DIRECTORY_SEPARATOR . $entry;
        $child_relative = $relative === '' ? $entry : $relative . '/' . $entry;
        if (is_dir($path) && in_array($entry, $excluded_directories, true)) {
            continue;
        }
        if (!mrn_loader_collect_digest_files($path, $child_relative, $files)) {
            return false;
        }
    }

    return true;
}

/**
 * Calculate the same deterministic digest used by the release-lock generator.
 *
 * @param string $source Source file or directory.
 * @return array|null
 */
function mrn_loader_tree_hash($source) {
    $files = array();
    if (!mrn_loader_collect_digest_files($source, '', $files) || empty($files)) {
        return null;
    }

    ksort($files, SORT_STRING);
    $context = hash_init('sha256');
    foreach ($files as $relative => $path) {
        $file_hash = hash_file('sha256', $path);
        $file_size = filesize($path);
        if (!is_string($file_hash) || $file_size === false) {
            return null;
        }
        hash_update($context, $relative . "\0" . $file_hash . "\0" . $file_size . "\n");
    }

    return array(
        'algorithm'  => MRN_LOADER_HASH_ALGORITHM,
        'sha256'     => hash_final($context),
        'file_count' => count($files),
    );
}

/**
 * Convert an absolute runtime path into a wp-content-relative path.
 *
 * @param string $path Absolute path.
 * @return string
 */
function mrn_loader_relative_runtime_path($path) {
    $content_root = wp_normalize_path(WP_CONTENT_DIR);
    $normalized = wp_normalize_path($path);
    if (strpos($normalized, $content_root . '/') === 0) {
        return substr($normalized, strlen($content_root) + 1);
    }

    return basename($normalized);
}

/**
 * Determine whether a root MU file is the expected subdirectory bootstrap.
 *
 * @param string $file Root MU file.
 * @param string $slug Component slug.
 * @return bool
 */
function mrn_loader_is_subdirectory_wrapper($file, $slug) {
    if (!is_readable($file)) {
        return false;
    }

    $contents = file_get_contents($file, false, null, 0, 16384);
    if (!is_string($contents)) {
        return false;
    }

    $needle = '/' . $slug . '/' . $slug . '.php';
    return strpos($contents, $needle) !== false && strpos($contents, 'require') !== false;
}

/**
 * Read the deployed immutable release lock when present and valid.
 *
 * @return array
 */
function mrn_loader_read_release_lock() {
    $path = __DIR__ . '/mrn-stack-release.lock.json';
    $result = array(
        'present'        => false,
        'valid'          => false,
        'release_id'     => null,
        'schema_version' => null,
        'sha256'         => null,
        'payload'        => null,
    );

    if (!is_readable($path)) {
        return $result;
    }

    $result['present'] = true;
    $result['sha256'] = hash_file('sha256', $path);
    $contents = file_get_contents($path);
    $payload = is_string($contents) ? json_decode($contents, true) : null;
    if (
        !is_array($payload) ||
        !isset($payload['schema_version'], $payload['release_id'], $payload['components'], $payload['themes']) ||
        !is_array($payload['components']) ||
        !is_array($payload['themes'])
    ) {
        return $result;
    }

    $result['valid'] = true;
    $result['release_id'] = (string) $payload['release_id'];
    $result['schema_version'] = (int) $payload['schema_version'];
    $result['payload'] = $payload;
    return $result;
}

/**
 * Compare a runtime component to its release-lock record.
 *
 * @param array $runtime  Runtime record.
 * @param array $expected Expected lock record.
 * @return bool|null
 */
function mrn_loader_component_matches_release($runtime, $expected) {
    if (empty($expected['sha256']) || !isset($runtime['sha256'])) {
        return null;
    }

    return hash_equals((string) $expected['sha256'], (string) $runtime['sha256']) &&
        (string) ($expected['version'] ?? '') === (string) ($runtime['version'] ?? '');
}

/**
 * Shape one component record without exposing an absolute server path.
 *
 * @param string     $slug         Component slug.
 * @param string     $runtime_type Runtime type.
 * @param string     $source       Runtime source path.
 * @param string     $entry_file   Version header file.
 * @param bool       $loaded       Whether WordPress loaded the component.
 * @param array|null $expected     Release-lock record.
 * @return array
 */
function mrn_loader_shape_runtime_component($slug, $runtime_type, $source, $entry_file, $loaded, $expected) {
    $hash = file_exists($source) ? mrn_loader_tree_hash($source) : null;
    $record = array(
        'slug'            => $slug,
        'runtime_type'    => $runtime_type,
        'version'         => mrn_loader_read_version_header($entry_file),
        'loaded'          => (bool) $loaded,
        'path'            => mrn_loader_relative_runtime_path($source),
        'hash_algorithm'  => $hash['algorithm'] ?? MRN_LOADER_HASH_ALGORITHM,
        'sha256'          => $hash['sha256'] ?? null,
        'file_count'      => $hash['file_count'] ?? 0,
        'matches_release' => null,
    );
    if (is_array($expected)) {
        $record['matches_release'] = mrn_loader_component_matches_release($record, $expected);
    }
    return $record;
}

/**
 * Find a standard plugin's main file from its directory slug.
 *
 * @param string $slug Plugin directory slug.
 * @return array|null
 */
function mrn_loader_find_standard_plugin($slug) {
    $source = WP_PLUGIN_DIR . '/' . $slug;
    if (!is_dir($source)) {
        return null;
    }

    $entry_files = glob($source . '/*.php');
    if (!is_array($entry_files)) {
        return null;
    }

    $active_plugins = (array) get_option('active_plugins', array());
    $network_active = function_exists('get_site_option')
        ? (array) get_site_option('active_sitewide_plugins', array())
        : array();

    foreach ($entry_files as $entry_file) {
        $contents = file_get_contents($entry_file, false, null, 0, 16384);
        if (!is_string($contents) || strpos($contents, 'Plugin Name:') === false) {
            continue;
        }
        $plugin_file = $slug . '/' . basename($entry_file);
        return array(
            'plugin_file' => $plugin_file,
            'entry_file'  => $entry_file,
            'source'      => $source,
            'loaded'      => in_array($plugin_file, $active_plugins, true) || isset($network_active[$plugin_file]),
        );
    }

    return null;
}

/**
 * Build the capability-protected runtime report.
 *
 * @return array
 */
function mrn_loader_get_runtime_report() {
    global $mrn_loader_runtime_components;

    $lock = mrn_loader_read_release_lock();
    $payload = is_array($lock['payload']) ? $lock['payload'] : array();
    $expected_components = array();
    foreach (($payload['components'] ?? array()) as $component) {
        if (is_array($component) && !empty($component['slug'])) {
            $expected_components[(string) $component['slug']] = $component;
        }
    }

    if (empty($expected_components)) {
        $expected_components['mrn-loader'] = array(
            'slug'         => 'mrn-loader',
            'runtime_type' => 'mu-loader',
            'required'     => true,
        );
        foreach ($mrn_loader_runtime_components as $slug => $component) {
            $expected_components[$slug] = array(
                'slug'         => $slug,
                'runtime_type' => 'mu-component',
                'required'     => true,
            );
        }
    }

    $components = array();
    $legacy_collisions = array();
    foreach ($expected_components as $slug => $expected) {
        $runtime_type = (string) ($expected['runtime_type'] ?? 'unknown');
        if ($runtime_type === 'mu-loader') {
            $components[] = mrn_loader_shape_runtime_component(
                $slug,
                $runtime_type,
                __FILE__,
                __FILE__,
                true,
                $expected
            );
            continue;
        }

        if ($runtime_type === 'mu-component') {
            $runtime = $mrn_loader_runtime_components[$slug] ?? array();
            $entry_file = (string) ($runtime['entry_file'] ?? WP_CONTENT_DIR . '/mu-plugins/' . $slug . '/' . $slug . '.php');
            $source = dirname($entry_file);
            $components[] = mrn_loader_shape_runtime_component(
                $slug,
                $runtime_type,
                $source,
                $entry_file,
                !empty($runtime['loaded']),
                $expected
            );
            $flat_file = WP_CONTENT_DIR . '/mu-plugins/' . $slug . '.php';
            if (is_file($flat_file) && !mrn_loader_is_subdirectory_wrapper($flat_file, $slug)) {
                $legacy_collisions[] = $slug;
            }
            continue;
        }

        if ($runtime_type === 'standard-plugin') {
            $plugin = mrn_loader_find_standard_plugin($slug);
            $components[] = mrn_loader_shape_runtime_component(
                $slug,
                $runtime_type,
                $plugin['source'] ?? WP_PLUGIN_DIR . '/' . $slug,
                $plugin['entry_file'] ?? '',
                !empty($plugin['loaded']),
                $expected
            );
        }
    }

    usort(
        $components,
        static function ($left, $right) {
            return strcmp((string) $left['slug'], (string) $right['slug']);
        }
    );

    $themes = array();
    foreach (($payload['themes'] ?? array()) as $expected_theme) {
        if (!is_array($expected_theme) || empty($expected_theme['slug'])) {
            continue;
        }
        $slug = (string) $expected_theme['slug'];
        $source = WP_CONTENT_DIR . '/themes/' . $slug;
        $hash = is_dir($source) ? mrn_loader_tree_hash($source) : null;
        $runtime_theme = array(
            'slug'            => $slug,
            'version'         => mrn_loader_read_version_header($source . '/style.css'),
            'active'          => get_option('stylesheet') === $slug,
            'path'            => 'themes/' . $slug,
            'hash_algorithm'  => $hash['algorithm'] ?? MRN_LOADER_HASH_ALGORITHM,
            'sha256'          => $hash['sha256'] ?? null,
            'file_count'      => $hash['file_count'] ?? 0,
            'matches_release' => null,
        );
        $runtime_theme['matches_release'] = mrn_loader_component_matches_release($runtime_theme, $expected_theme);
        $themes[] = $runtime_theme;
    }

    $missing_required = array();
    $drifted_required = array();
    foreach ($components as $component) {
        if (!$component['loaded']) {
            $missing_required[] = $component['slug'];
        } elseif ($component['matches_release'] === false) {
            $drifted_required[] = $component['slug'];
        }
    }

    $public_lock = $lock;
    unset($public_lock['payload']);
    $report = array(
        'schema_version'         => MRN_LOADER_RUNTIME_REPORT_SCHEMA_VERSION,
        'generated_at_utc'       => gmdate('Y-m-d\\TH:i:s\\Z'),
        'loader_version'         => MRN_LOADER_VERSION,
        'release_lock'           => $public_lock,
        'site'                   => array(
            'host'             => wp_parse_url(home_url('/'), PHP_URL_HOST),
            'environment_type' => function_exists('wp_get_environment_type') ? wp_get_environment_type() : 'unknown',
            'template'         => get_option('template'),
            'stylesheet'       => get_option('stylesheet'),
        ),
        'components'             => $components,
        'themes'                 => $themes,
        'missing_required'       => array_values(array_unique($missing_required)),
        'drifted_required'       => array_values(array_unique($drifted_required)),
        'legacy_flat_collisions' => array_values(array_unique($legacy_collisions)),
    );

    return apply_filters('mrn_loader_runtime_report', $report);
}

/**
 * Restrict runtime-report access to trusted administrators by default.
 *
 * @return bool
 */
function mrn_loader_can_view_runtime_report() {
    $capability = apply_filters('mrn_loader_runtime_report_capability', 'manage_options');
    return is_string($capability) && $capability !== '' && current_user_can($capability);
}

/**
 * Register the read-only runtime-report route.
 *
 * @return void
 */
function mrn_loader_register_runtime_report_route() {
    register_rest_route(
        'mrn/v1',
        '/stack-report',
        array(
            'methods'             => 'GET',
            'callback'            => static function () {
                return rest_ensure_response(mrn_loader_get_runtime_report());
            },
            'permission_callback' => 'mrn_loader_can_view_runtime_report',
        )
    );
}
add_action('rest_api_init', 'mrn_loader_register_runtime_report_route');

if (defined('WP_CLI') && WP_CLI) {
    /**
     * Print the report for site-owner and QA automation.
     *
     * @return void
     */
    function mrn_loader_wp_cli_stack_report() {
        WP_CLI::line(wp_json_encode(mrn_loader_get_runtime_report(), JSON_PRETTY_PRINT));
    }

    WP_CLI::add_command('mrn stack-report', 'mrn_loader_wp_cli_stack_report');
}
