<?php
/**
 * REST API routes for registry, mapping, import, and rollback.
 *
 * @package mrn-figma-sync
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST controller.
 */
final class MRN_Figma_Sync_REST_Controller {
	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public static function register_routes() {
		register_rest_route(
			'mrn-figma-sync/v1',
			'/registry',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => array( __CLASS__, 'can_view_registry' ),
				'callback'            => array( __CLASS__, 'get_registry' ),
			)
		);

		register_rest_route(
			'mrn-figma-sync/v1',
			'/map',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'permission_callback' => array( __CLASS__, 'can_edit_content' ),
				'callback'            => array( __CLASS__, 'map_payload' ),
			)
		);

		register_rest_route(
			'mrn-figma-sync/v1',
			'/import',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'permission_callback' => array( __CLASS__, 'can_edit_content' ),
				'callback'            => array( __CLASS__, 'import_payload' ),
			)
		);

		register_rest_route(
			'mrn-figma-sync/v1',
			'/rollback',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'permission_callback' => array( __CLASS__, 'can_edit_content' ),
				'callback'            => array( __CLASS__, 'rollback_payload' ),
			)
		);
	}

	/**
	 * Permission callback for registry reads.
	 *
	 * @return bool
	 */
	public static function can_view_registry() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Permission callback for write operations.
	 *
	 * @return bool
	 */
	public static function can_edit_content() {
		return current_user_can( 'edit_posts' ) || current_user_can( 'edit_pages' );
	}

	/**
	 * Return the live registry.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_registry() {
		$registry = MRN_Figma_Sync_Registry::get_registry();
		if ( is_wp_error( $registry ) ) {
			return $registry;
		}

		return rest_ensure_response( $registry );
	}

	/**
	 * Map a Figma payload.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function map_payload( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			return new WP_Error( 'invalid_payload', 'Expected a JSON object request body.', array( 'status' => 400 ) );
		}

		$result = MRN_Figma_Sync_Mapper::map_payload( $params );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Import a payload.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function import_payload( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			return new WP_Error( 'invalid_payload', 'Expected a JSON object request body.', array( 'status' => 400 ) );
		}

		$payload_type = isset( $params['payload_type'] ) ? sanitize_key( (string) $params['payload_type'] ) : 'layout';
		$dry_run      = ! empty( $params['dry_run'] );
		$payload      = isset( $params['payload'] ) && is_array( $params['payload'] ) ? $params['payload'] : $params;

		if ( 'figma' === $payload_type ) {
			$mapped = MRN_Figma_Sync_Mapper::map_payload( $payload );
			if ( is_wp_error( $mapped ) ) {
				return $mapped;
			}

			if ( empty( $mapped['ok'] ) ) {
				return new WP_Error( 'mapping_failed', 'The Figma payload did not pass mapping/validation.', array( 'status' => 400, 'result' => $mapped ) );
			}

			if ( empty( $mapped['layout_payload'] ) || ! is_array( $mapped['layout_payload'] ) ) {
				return new WP_Error( 'mapping_failed', 'The Figma payload did not produce a layout payload.', array( 'status' => 400, 'result' => $mapped ) );
			}

			$payload = $mapped['layout_payload'];
		}

		$post_id = isset( $payload['target']['post_id'] ) ? absint( $payload['target']['post_id'] ) : 0;
		if ( $post_id && ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error( 'forbidden', 'You do not have permission to edit the target post.', array( 'status' => 403 ) );
		}

		$result = MRN_Figma_Sync_Importer::import_layout_payload(
			$payload,
			array(
				'dry_run' => $dry_run,
			)
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Roll back to a snapshot.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rollback_payload( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			return new WP_Error( 'invalid_payload', 'Expected a JSON object request body.', array( 'status' => 400 ) );
		}

		$post_id     = isset( $params['post_id'] ) ? absint( $params['post_id'] ) : 0;
		$snapshot_id = isset( $params['snapshot_id'] ) ? sanitize_text_field( (string) $params['snapshot_id'] ) : '';

		if ( ! $post_id ) {
			return new WP_Error( 'missing_post_id', 'A post_id is required for rollback.', array( 'status' => 400 ) );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error( 'forbidden', 'You do not have permission to edit the target post.', array( 'status' => 403 ) );
		}

		$result = MRN_Figma_Sync_Importer::rollback( $post_id, $snapshot_id );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}
}
