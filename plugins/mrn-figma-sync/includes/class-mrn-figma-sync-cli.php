<?php
/**
 * WP-CLI commands for registry export, mapping, import, and rollback.
 *
 * @package mrn-figma-sync
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'WP_CLI_Command' ) ) {
	/**
	 * WP-CLI integration.
	 */
	final class MRN_Figma_Sync_CLI extends WP_CLI_Command {
	/**
	 * Register the command namespace.
	 *
	 * @return void
	 */
	public static function register() {
		WP_CLI::add_command( 'mrn-figma-sync', __CLASS__ );
	}

	/**
	 * Export the live MRN builder registry.
	 *
	 * ## OPTIONS
	 *
	 * [--output=<file>]
	 * : Optional file path for the JSON output.
	 *
	 * ## EXAMPLES
	 *
	 *     wp mrn-figma-sync registry
	 *     wp mrn-figma-sync registry --output=registry.json
	 *
	 * @param array<int, string>   $args Positional args.
	 * @param array<string, mixed> $assoc_args Associative args.
	 * @return void
	 */
	public function registry( $args, $assoc_args ) {
		unset( $args );

		$registry = MRN_Figma_Sync_Registry::get_registry();
		if ( is_wp_error( $registry ) ) {
			WP_CLI::error( $registry->get_error_message() );
		}

		$this->output_json( $registry, isset( $assoc_args['output'] ) ? (string) $assoc_args['output'] : '' );
	}

	/**
	 * Map a normalized Figma payload into a WordPress layout payload.
	 *
	 * ## OPTIONS
	 *
	 * --file=<file>
	 * : JSON file containing the normalized Figma payload.
	 *
	 * [--output=<file>]
	 * : Optional output file path.
	 *
	 * ## EXAMPLES
	 *
	 *     wp mrn-figma-sync map --file=figma-export.json
	 *
	 * @param array<int, string>   $args Positional args.
	 * @param array<string, mixed> $assoc_args Associative args.
	 * @return void
	 */
	public function map( $args, $assoc_args ) {
		unset( $args );

		$payload = $this->read_json_file( $assoc_args, 'file' );
		$result  = MRN_Figma_Sync_Mapper::map_payload( $payload );

		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		}

		$this->output_json( $result, isset( $assoc_args['output'] ) ? (string) $assoc_args['output'] : '' );

		if ( empty( $result['ok'] ) ) {
			WP_CLI::warning( 'Mapping completed with errors. Inspect the JSON result.' );
		}
	}

	/**
	 * Validate a payload.
	 *
	 * ## OPTIONS
	 *
	 * --file=<file>
	 * : JSON file containing the payload.
	 *
	 * --type=<type>
	 * : figma or layout.
	 *
	 * ## EXAMPLES
	 *
	 *     wp mrn-figma-sync validate --type=figma --file=figma-export.json
	 *     wp mrn-figma-sync validate --type=layout --file=layout-payload.json
	 *
	 * @param array<int, string>   $args Positional args.
	 * @param array<string, mixed> $assoc_args Associative args.
	 * @return void
	 */
	public function validate( $args, $assoc_args ) {
		unset( $args );

		$payload = $this->read_json_file( $assoc_args, 'file' );
		$type    = isset( $assoc_args['type'] ) ? sanitize_key( (string) $assoc_args['type'] ) : 'figma';

		if ( 'layout' === $type ) {
			$result = MRN_Figma_Sync_Importer::validate_layout_payload( $payload );
		} else {
			$mapped = MRN_Figma_Sync_Mapper::map_payload( $payload );
			$result = is_wp_error( $mapped ) ? array(
				'errors'   => array(
					array(
						'code'    => $mapped->get_error_code(),
						'message' => $mapped->get_error_message(),
						'path'    => '$',
					),
				),
				'warnings' => array(),
			) : array(
				'errors'   => isset( $mapped['errors'] ) && is_array( $mapped['errors'] ) ? $mapped['errors'] : array(),
				'warnings' => isset( $mapped['warnings'] ) && is_array( $mapped['warnings'] ) ? $mapped['warnings'] : array(),
			);
		}

		$this->output_json( $result, '' );

		if ( ! empty( $result['errors'] ) ) {
			WP_CLI::error( sprintf( 'Validation failed with %d error(s).', count( $result['errors'] ) ), false );
			return;
		}

		WP_CLI::success( 'Validation passed.' );
	}

	/**
	 * Import a payload.
	 *
	 * ## OPTIONS
	 *
	 * --file=<file>
	 * : JSON file containing the payload.
	 *
	 * --type=<type>
	 * : figma or layout.
	 *
	 * [--dry-run]
	 * : Validate and snapshot without writing field values.
	 *
	 * ## EXAMPLES
	 *
	 *     wp mrn-figma-sync import --type=figma --file=figma-export.json --dry-run
	 *     wp mrn-figma-sync import --type=layout --file=layout-payload.json
	 *
	 * @param array<int, string>   $args Positional args.
	 * @param array<string, mixed> $assoc_args Associative args.
	 * @return void
	 */
	public function import( $args, $assoc_args ) {
		unset( $args );

		$payload = $this->read_json_file( $assoc_args, 'file' );
		$type    = isset( $assoc_args['type'] ) ? sanitize_key( (string) $assoc_args['type'] ) : 'figma';
		$dry_run = isset( $assoc_args['dry-run'] );

		if ( 'figma' === $type ) {
			$mapped = MRN_Figma_Sync_Mapper::map_payload( $payload );
			if ( is_wp_error( $mapped ) ) {
				WP_CLI::error( $mapped->get_error_message() );
			}

			if ( empty( $mapped['ok'] ) ) {
				$this->output_json( $mapped, '' );
				WP_CLI::error( 'The Figma payload did not pass mapping/validation.' );
			}

			if ( empty( $mapped['layout_payload'] ) || ! is_array( $mapped['layout_payload'] ) ) {
				WP_CLI::error( 'The Figma payload did not produce a valid layout payload.' );
			}

			$payload = $mapped['layout_payload'];
		}

		$result = MRN_Figma_Sync_Importer::import_layout_payload(
			$payload,
			array(
				'dry_run' => $dry_run,
			)
		);

		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		}

		$this->output_json( $result, '' );

		if ( $dry_run ) {
			WP_CLI::success( 'Dry run completed.' );
			return;
		}

		WP_CLI::success( 'Import completed.' );
	}

	/**
	 * Roll back a post to the most recent or a specific snapshot.
	 *
	 * ## OPTIONS
	 *
	 * --post=<id>
	 * : Target post ID.
	 *
	 * [--snapshot=<snapshot>]
	 * : Optional snapshot UUID.
	 *
	 * ## EXAMPLES
	 *
	 *     wp mrn-figma-sync rollback --post=123
	 *
	 * @param array<int, string>   $args Positional args.
	 * @param array<string, mixed> $assoc_args Associative args.
	 * @return void
	 */
	public function rollback( $args, $assoc_args ) {
		unset( $args );

		$post_id = isset( $assoc_args['post'] ) ? absint( $assoc_args['post'] ) : 0;
		if ( ! $post_id ) {
			WP_CLI::error( 'A --post=<id> value is required.' );
		}

		$result = MRN_Figma_Sync_Importer::rollback(
			$post_id,
			isset( $assoc_args['snapshot'] ) ? (string) $assoc_args['snapshot'] : ''
		);

		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		}

		$this->output_json( $result, '' );
		WP_CLI::success( 'Rollback completed.' );
	}

	/**
	 * Read a JSON file argument.
	 *
	 * @param array<string, mixed> $assoc_args CLI args.
	 * @param string               $key Required arg key.
	 * @return array<string, mixed>
	 */
	private function read_json_file( array $assoc_args, $key ) {
		if ( empty( $assoc_args[ $key ] ) ) {
			WP_CLI::error( sprintf( 'The --%1$s=<file> argument is required.', $key ) );
		}

		$file = (string) $assoc_args[ $key ];
		if ( ! file_exists( $file ) ) {
			WP_CLI::error( sprintf( 'File not found: %s', $file ) );
		}

		$contents = file_get_contents( $file );
		$data     = json_decode( (string) $contents, true );

		if ( ! is_array( $data ) ) {
			WP_CLI::error( sprintf( 'File %s did not contain a valid JSON object.', $file ) );
		}

		return $data;
	}

	/**
	 * Write or print JSON output.
	 *
	 * @param mixed  $data JSON-serializable data.
	 * @param string $output Optional file path.
	 * @return void
	 */
	private function output_json( $data, $output ) {
		$json = wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

		if ( ! is_string( $json ) ) {
			WP_CLI::error( 'The result could not be encoded as JSON.' );
		}

		if ( '' !== $output ) {
			file_put_contents( $output, $json . PHP_EOL );
			WP_CLI::log( sprintf( 'Wrote %s', $output ) );
			return;
		}

		WP_CLI::log( $json );
	}
}
}
