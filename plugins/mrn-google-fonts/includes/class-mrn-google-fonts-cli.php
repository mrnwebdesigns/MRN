<?php
/**
 * WP-CLI commands for MRN Google Fonts.
 */

if (!defined('ABSPATH')) {
	exit;
}

final class MRN_Google_Fonts_CLI {
	public static function init(): void {
		if (!defined('WP_CLI') || !WP_CLI || !class_exists('WP_CLI')) {
			return;
		}

		WP_CLI::add_command('mrn-google-fonts', __CLASS__);
	}

	/**
	 * Show current delivery and local-build status.
	 *
	 * ## OPTIONS
	 *
	 * [--check-frontend]
	 * : Fetch the selected site's homepage and detect Google-hosted font URLs.
	 *
	 * [--format=<format>]
	 * : Output format. Accepts table or json. Default: table.
	 *
	 * Use WP-CLI's global --url option to select a multisite site or site URL.
	 */
	public function status(array $args, array $assoc_args): void {
		unset($args);
		$status = MRN_Google_Fonts::get_runtime_status(isset($assoc_args['check-frontend']));
		self::render_status($status, (string) ($assoc_args['format'] ?? 'table'));
	}

	/** Build and validate local fonts from the current settings. */
	public function build(array $args, array $assoc_args): void {
		unset($args, $assoc_args);
		$result = MRN_Google_Fonts::build_local_assets(MRN_Google_Fonts::get_settings());
		if (is_wp_error($result)) {
			WP_CLI::error($result->get_error_message());
		}
		$validation = MRN_Google_Fonts::validate_local_manifest(MRN_Google_Fonts::get_local_manifest());
		if (is_wp_error($validation)) {
			WP_CLI::error('Build completed but validation failed: ' . $validation->get_error_message());
		}
		WP_CLI::success(sprintf('Local build valid: %d files, %d families, schema %d.', (int) ($result['file_count'] ?? 0), (int) ($result['family_count'] ?? 0), (int) ($result['schema_version'] ?? 0)));
	}

	/** Validate the current portable manifest and every referenced file. */
	public function validate(array $args, array $assoc_args): void {
		unset($args, $assoc_args);
		$result = MRN_Google_Fonts::validate_local_manifest(MRN_Google_Fonts::get_local_manifest());
		if (is_wp_error($result)) {
			WP_CLI::error($result->get_error_message());
		}
		WP_CLI::success('Manifest, stylesheet, URLs, and local font checksums are valid.');
	}

	/**
	 * Migrate a legacy absolute-path manifest to the portable schema.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Validate and report the migration without writing files or options.
	 */
	public function migrate(array $args, array $assoc_args): void {
		unset($args);
		$result = MRN_Google_Fonts::migrate_local_manifest(isset($assoc_args['dry-run']));
		if (is_wp_error($result)) {
			WP_CLI::error($result->get_error_message());
		}
		WP_CLI::success((string) ($result['message'] ?? 'Migration complete.'));
	}

	/** @param array<string, mixed> $status */
	private static function render_status(array $status, string $format): void {
		if ('json' === strtolower($format)) {
			WP_CLI::line((string) wp_json_encode($status));
			return;
		}

		$rows = array();
		foreach ($status as $key => $value) {
			if (is_bool($value)) {
				$value = $value ? 'yes' : 'no';
			} elseif (null === $value) {
				$value = 'not checked';
			} elseif (is_array($value)) {
				$value = implode(', ', array_map('strval', $value));
			}
			$rows[] = array('field' => $key, 'value' => (string) $value);
		}
		\WP_CLI\Utils\format_items('table', $rows, array('field', 'value'));
	}
}
