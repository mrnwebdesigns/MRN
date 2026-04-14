<?php
/**
 * Shared plugin helpers and bootstrap hooks.
 *
 * @package mrn-figma-sync
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared plugin helpers.
 */
final class MRN_Figma_Sync_Plugin {
	/**
	 * Bootstrap the plugin.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'rest_api_init', array( 'MRN_Figma_Sync_REST_Controller', 'register_routes' ) );

		if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( 'MRN_Figma_Sync_CLI' ) ) {
			MRN_Figma_Sync_CLI::register();
		}
	}

	/**
	 * Load component mapping definitions.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_component_mappings() {
		static $mappings = null;

		if ( null !== $mappings ) {
			return $mappings;
		}

		$path = MRN_FIGMA_SYNC_PATH . 'config/component-mappings.php';
		$data = file_exists( $path ) ? require $path : array();

		$mappings = is_array( $data ) ? $data : array();

		/**
		 * Filter component mapping definitions.
		 *
		 * @param array<string, array<string, mixed>> $mappings Mapping definitions.
		 */
		$mappings = apply_filters( 'mrn_figma_sync_component_mappings', $mappings );

		return is_array( $mappings ) ? $mappings : array();
	}

	/**
	 * Load token mapping definitions.
	 *
	 * @return array<string, array<string, string>>
	 */
	public static function get_token_mappings() {
		static $mappings = null;

		if ( null !== $mappings ) {
			return $mappings;
		}

		$path = MRN_FIGMA_SYNC_PATH . 'config/token-mappings.php';
		$data = file_exists( $path ) ? require $path : array();

		$mappings = is_array( $data ) ? $data : array();

		/**
		 * Filter token mapping definitions.
		 *
		 * @param array<string, array<string, string>> $mappings Token mapping definitions.
		 */
		$mappings = apply_filters( 'mrn_figma_sync_token_mappings', $mappings );

		return is_array( $mappings ) ? $mappings : array();
	}

	/**
	 * Normalize a lookup key for deterministic matching.
	 *
	 * @param mixed $value Candidate value.
	 * @return string
	 */
	public static function normalize_lookup_key( $value ) {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$value = strtolower( trim( (string) $value ) );
		$value = preg_replace( '/[^a-z0-9]+/', '-', $value );

		return trim( (string) $value, '-' );
	}

	/**
	 * Read a nested value via dot notation.
	 *
	 * @param array<string, mixed> $data Source array.
	 * @param string               $path Dot-notated path.
	 * @return mixed|null
	 */
	public static function get_path_value( array $data, $path ) {
		if ( '' === trim( (string) $path ) ) {
			return null;
		}

		$segments = explode( '.', (string) $path );
		$current  = $data;

		foreach ( $segments as $segment ) {
			if ( ! is_array( $current ) || ! array_key_exists( $segment, $current ) ) {
				return null;
			}

			$current = $current[ $segment ];
		}

		return $current;
	}

	/**
	 * Convert values into a consistent list of lookup candidates.
	 *
	 * @param mixed $value Candidate input.
	 * @return array<int, string>
	 */
	public static function get_lookup_candidates( $value ) {
		$candidates = array();

		if ( is_scalar( $value ) ) {
			$candidates[] = trim( (string) $value );
		} elseif ( is_array( $value ) ) {
			foreach ( array( 'key', 'name', 'slug', 'token', 'value', 'label', 'id' ) as $candidate_key ) {
				if ( isset( $value[ $candidate_key ] ) && is_scalar( $value[ $candidate_key ] ) ) {
					$candidates[] = trim( (string) $value[ $candidate_key ] );
				}
			}
		}

		return array_values( array_filter( array_unique( $candidates ) ) );
	}

	/**
	 * Build a validation issue.
	 *
	 * @param string               $code Issue code.
	 * @param string               $message Human-readable message.
	 * @param string               $path Dot-notated payload path.
	 * @param array<string, mixed> $context Extra context.
	 * @return array<string, mixed>
	 */
	public static function build_issue( $code, $message, $path, array $context = array() ) {
		return array_merge(
			array(
				'code'    => sanitize_key( (string) $code ),
				'message' => (string) $message,
				'path'    => (string) $path,
			),
			$context
		);
	}
}
