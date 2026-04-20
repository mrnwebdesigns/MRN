<?php
/**
 * Plugin Name: SearchWP Editor Performance
 * Description: Local/development SearchWP indexer override to reduce editor load/save latency.
 * Version: 1.0.0
 * Author: MRN
 */

if (!defined('ABSPATH')) {
	exit;
}

final class MRN_SearchWP_Editor_Performance {
	const VERSION = '1.0.0';

	public static function init() {
		if (!self::should_enable()) {
			return;
		}

		// Skip SearchWP loopback communication checks that can cost about 1s per save in local/dev.
		add_filter('searchwp\indexer\alternate', '__return_true', PHP_INT_MAX);
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
