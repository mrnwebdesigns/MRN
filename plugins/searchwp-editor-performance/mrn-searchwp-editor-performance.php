<?php
/**
 * Plugin Name: SearchWP Editor Performance
 * Description: Local/development SearchWP indexer override to reduce editor load/save latency.
 * Version: 1.0.1
 * Author: MRN
 */

if (!defined('ABSPATH')) {
	exit;
}

final class MRN_SearchWP_Editor_Performance {
	const VERSION = '1.0.1';

	public static function init() {
		if (!self::should_enable()) {
			return;
		}

		// Skip SearchWP loopback communication checks that can cost about 1s per save in local/dev.
		add_filter('searchwp\indexer\alternate', '__return_true', PHP_INT_MAX);

		// Prevent SearchWP from repeatedly reprocessing post/meta drops during full editor save requests.
		if (self::should_skip_drop_callbacks()) {
			add_action('init', array(__CLASS__, 'maybe_disable_drop_callbacks'), PHP_INT_MAX);
		}
	}

	public static function maybe_disable_drop_callbacks() {
		if (!self::is_editor_save_request()) {
			return;
		}

		self::remove_searchwp_post_callbacks('save_post', 'drop_post');
		self::remove_searchwp_post_callbacks('updated_post_meta', 'updated_post_meta');
		self::remove_searchwp_post_callbacks('deleted_post_meta', 'updated_post_meta');
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

		if (!isset($_POST['_wpnonce'])) {
			return false;
		}

		$nonce = sanitize_text_field(wp_unslash($_POST['_wpnonce']));
		if (!wp_verify_nonce($nonce, 'update-post_' . $post_id)) {
			return false;
		}

		return true;
	}

	private static function remove_searchwp_post_callbacks($hook_name, $method_name) {
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

				if ($function[0] instanceof SearchWP\Sources\Post) {
					remove_action($hook_name, $function, $priority);
				}
			}
		}
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
