<?php
/**
 * Plugin Name: SearchWP Editor Performance
 * Description: Local/development SearchWP indexer override to reduce editor load/save latency.
 * Version: 1.0.6
 * Author: MRN
 */

if (!defined('ABSPATH')) {
	exit;
}

final class MRN_SearchWP_Editor_Performance {
	const VERSION = '1.0.6';

	public static function init() {
		if (!self::should_enable()) {
			return;
		}

		// Disable SearchWP bootstrap entirely for classic post editor requests.
		add_action('plugins_loaded', array(__CLASS__, 'maybe_disable_searchwp_bootstrap'), PHP_INT_MAX);
		self::maybe_disable_searchwp_bootstrap();

		/*
		 * Prevent SearchWP source hook registration during classic editor saves.
		 *
		 * On ACF-heavy pages this avoids attaching high-volume meta/save callbacks
		 * that can repeatedly drop index entries during one publish request.
		 */
		if (self::should_short_circuit_source_hooks()) {
			add_filter('searchwp\index\source\add_hooks', '__return_false', PHP_INT_MAX);
		}

		// Skip SearchWP loopback communication checks that can cost about 1s per save in local/dev.
		add_filter('searchwp\indexer\alternate', '__return_true', PHP_INT_MAX);

		// Prevent SearchWP from repeatedly reprocessing post/meta drops during full editor save requests.
		if (self::should_skip_drop_callbacks()) {
			add_action('init', array(__CLASS__, 'maybe_disable_drop_callbacks'), PHP_INT_MAX);
			add_action('admin_init', array(__CLASS__, 'maybe_disable_drop_callbacks'), PHP_INT_MAX);
		}
	}

	public static function maybe_disable_searchwp_bootstrap() {
		if (!self::is_classic_editor_request()) {
			return;
		}

		self::disable_searchwp_init_callbacks();
	}

	public static function maybe_disable_drop_callbacks() {
		if (!self::is_editor_save_request()) {
			return;
		}

		self::remove_searchwp_source_callbacks('save_post', 'drop_post');
		self::remove_searchwp_source_callbacks('updated_post_meta', 'updated_post_meta');
		self::remove_searchwp_source_callbacks('deleted_post_meta', 'updated_post_meta');
	}

	private static function should_enable() {
		if (defined('MRN_SEARCHWP_EDITOR_PERF_DISABLE') && MRN_SEARCHWP_EDITOR_PERF_DISABLE) {
			return false;
		}

		if (defined('MRN_SEARCHWP_EDITOR_PERF_FORCE') && MRN_SEARCHWP_EDITOR_PERF_FORCE) {
			return true;
		}

		return in_array(self::get_environment_type(), array('local', 'development'), true);
	}

	private static function should_skip_drop_callbacks() {
		if (defined('MRN_SEARCHWP_EDITOR_PERF_SKIP_DROP')) {
			return (bool) MRN_SEARCHWP_EDITOR_PERF_SKIP_DROP;
		}

		return in_array(self::get_environment_type(), array('local', 'development'), true);
	}

	private static function should_short_circuit_source_hooks() {
		if (!self::should_skip_drop_callbacks()) {
			return false;
		}

		return self::is_editor_save_request();
	}

	private static function is_classic_editor_request() {
		if (!is_admin()) {
			return false;
		}

		$pagenow = isset($GLOBALS['pagenow']) ? strtolower((string) $GLOBALS['pagenow']) : '';

		if ('' === $pagenow && isset($_SERVER['SCRIPT_NAME'])) {
			$pagenow = strtolower((string) wp_basename(wp_unslash((string) $_SERVER['SCRIPT_NAME'])));
		}

		return in_array($pagenow, array('post.php', 'post-new.php'), true);
	}

	private static function disable_searchwp_init_callbacks() {
		global $wp_filter;

		if (empty($wp_filter['init']) || !($wp_filter['init'] instanceof WP_Hook)) {
			return;
		}

		$hook = $wp_filter['init'];
		if (empty($hook->callbacks) || !is_array($hook->callbacks)) {
			return;
		}

		foreach ($hook->callbacks as $priority => $callbacks) {
			foreach ($callbacks as $callback) {
				if (empty($callback['function']) || !is_array($callback['function'])) {
					continue;
				}

				$function = $callback['function'];
				if (
					2 !== count($function)
					|| !is_object($function[0])
					|| !is_string($function[1])
					|| 'init' !== $function[1]
				) {
					continue;
				}

				if ('SearchWP' === get_class($function[0])) {
					remove_action('init', $function, $priority);
				}
			}
		}
	}

	private static function is_editor_save_request() {
		if (!is_admin()) {
			return false;
		}

		if (function_exists('wp_doing_ajax') && wp_doing_ajax()) {
			return false;
		}

		if (!isset($_POST['action'], $_POST['post_ID'])) {
			return false;
		}

		$post_id = absint($_POST['post_ID']);
		if ($post_id <= 0) {
			return false;
		}

		$action = sanitize_key(wp_unslash($_POST['action']));
		if ('editpost' !== $action) {
			return false;
		}

		/*
		 * Keep nonce checks as a best-effort guard, but do not require them.
		 *
		 * Some stacked editor flows (custom metabox orchestration and large
		 * multipart submissions) can omit or alter nonce transport details while
		 * still routing through the classic editpost save path. A strict nonce
		 * requirement can produce false negatives and leave SearchWP drop hooks
		 * enabled on the slowest save requests.
		 */
		if (
			isset($_POST['_wpnonce'])
			&& function_exists('wp_verify_nonce')
		) {
			$nonce = sanitize_text_field(wp_unslash($_POST['_wpnonce']));
			if (
				'' !== $nonce
				&& (
					wp_verify_nonce($nonce, 'update-post_' . $post_id)
					|| wp_verify_nonce($nonce, 'add-post')
				)
			) {
				return true;
			}
		}

		return true;
	}

	private static function remove_searchwp_source_callbacks($hook_name, $method_name) {
		global $wp_filter;

		if (empty($wp_filter[$hook_name]) || !($wp_filter[$hook_name] instanceof WP_Hook)) {
			return;
		}

		$hook = $wp_filter[$hook_name];
		if (empty($hook->callbacks) || !is_array($hook->callbacks)) {
			return;
		}

		foreach ($hook->callbacks as $priority => $callbacks) {
			foreach ($callbacks as $callback) {
				if (empty($callback['function']) || !is_array($callback['function'])) {
					continue;
				}

				$function = $callback['function'];
				if (
					2 !== count($function)
					|| !is_object($function[0])
					|| !is_string($function[1])
					|| $method_name !== $function[1]
				) {
					continue;
				}

				if (self::is_supported_searchwp_source($function[0])) {
					remove_action($hook_name, $function, $priority);
				}
			}
		}
	}

	private static function is_supported_searchwp_source($target) {
		if (!is_object($target)) {
			return false;
		}

		$class_name = get_class($target);

		return 0 === strpos($class_name, 'SearchWP\\Sources\\Post')
			|| 0 === strpos($class_name, 'SearchWP\\Sources\\Attachment');
	}

	private static function get_environment_type() {
		$environment = '';

		if (function_exists('wp_get_environment_type')) {
			$environment = (string) wp_get_environment_type();
		} elseif (defined('WP_ENV')) {
			$environment = (string) WP_ENV;
		}

		$environment = strtolower(trim($environment));

		return '' !== $environment ? $environment : 'production';
	}
}

MRN_SearchWP_Editor_Performance::init();
