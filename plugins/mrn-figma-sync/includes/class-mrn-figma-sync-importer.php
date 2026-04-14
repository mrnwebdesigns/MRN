<?php
/**
 * Import, validation, snapshot, and rollback support.
 *
 * @package mrn-figma-sync
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Validates and imports WordPress layout payloads.
 */
final class MRN_Figma_Sync_Importer {
	/**
	 * Snapshot meta key.
	 *
	 * @var string
	 */
	const SNAPSHOT_META_KEY = '_mrn_figma_sync_snapshot';

	/**
	 * Last import meta key.
	 *
	 * @var string
	 */
	const LAST_IMPORT_META_KEY = '_mrn_figma_sync_last_import';

	/**
	 * Validate a layout payload against the live registry.
	 *
	 * @param array<string, mixed>     $payload Layout payload.
	 * @param array<string, mixed>|null $registry Optional live registry.
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	public static function validate_layout_payload( array $payload, $registry = null ) {
		$registry = $registry ? $registry : MRN_Figma_Sync_Registry::get_registry();
		$issues   = array(
			'errors'   => MRN_Figma_Sync_Validator::validate( $payload, MRN_Figma_Sync_Schema::get_wp_layout_payload_schema() ),
			'warnings' => array(),
		);

		if ( is_wp_error( $registry ) || ! empty( $issues['errors'] ) ) {
			return $issues;
		}

		$post_id = isset( $payload['target']['post_id'] ) ? absint( $payload['target']['post_id'] ) : 0;
		if ( $post_id ) {
			$post = get_post( $post_id );
			if ( ! ( $post instanceof WP_Post ) ) {
				$issues['errors'][] = MRN_Figma_Sync_Plugin::build_issue(
					'invalid_target_post',
					sprintf( 'Post %d does not exist.', $post_id ),
					'target.post_id'
				);
			} elseif ( ! empty( $payload['target']['post_type'] ) && sanitize_key( (string) $payload['target']['post_type'] ) !== sanitize_key( (string) $post->post_type ) ) {
				$issues['errors'][] = MRN_Figma_Sync_Plugin::build_issue(
					'target_post_type_mismatch',
					'The target post type does not match the resolved post.',
					'target.post_type'
				);
			}
		}

		$field_groups = isset( $registry['field_groups'] ) && is_array( $registry['field_groups'] ) ? $registry['field_groups'] : array();
		$fields       = isset( $payload['fields'] ) && is_array( $payload['fields'] ) ? $payload['fields'] : array();

		foreach ( $fields as $field_name => $field_value ) {
			if ( ! isset( $field_groups[ $field_name ] ) || ! is_array( $field_groups[ $field_name ] ) ) {
				$issues['errors'][] = MRN_Figma_Sync_Plugin::build_issue(
					'unsupported_field',
					sprintf( 'Field %1$s is not managed by the MRN builder contract.', $field_name ),
					'fields.' . $field_name
				);
				continue;
			}

			$field_definition = $field_groups[ $field_name ];
			$field_type       = isset( $field_definition['type'] ) ? (string) $field_definition['type'] : '';

			if ( 'flexible_content' === $field_type ) {
				if ( ! is_array( $field_value ) ) {
					$issues['errors'][] = MRN_Figma_Sync_Plugin::build_issue(
						'invalid_flexible_content',
						sprintf( 'Field %1$s must contain a row array.', $field_name ),
						'fields.' . $field_name
					);
					continue;
				}

				foreach ( array_values( $field_value ) as $row_index => $row ) {
					$row_path = 'fields.' . $field_name . '.' . $row_index;
					$row_issues = self::validate_row(
						is_array( $row ) ? $row : array(),
						$field_name,
						$registry,
						$row_path
					);

					$issues['errors']   = array_merge( $issues['errors'], $row_issues['errors'] );
					$issues['warnings'] = array_merge( $issues['warnings'], $row_issues['warnings'] );
				}

				continue;
			}

			if ( 'button_group' === $field_type || 'select' === $field_type ) {
				$choices = isset( $field_definition['choices'] ) && is_array( $field_definition['choices'] ) ? $field_definition['choices'] : array();
				if ( ! is_scalar( $field_value ) || ! isset( $choices[ (string) $field_value ] ) ) {
					$issues['errors'][] = MRN_Figma_Sync_Plugin::build_issue(
						'invalid_choice',
						sprintf( 'Field %1$s contains an unsupported value.', $field_name ),
						'fields.' . $field_name,
						array(
							'allowed' => array_keys( $choices ),
						)
					);
				}
			}
		}

		return $issues;
	}

	/**
	 * Import a layout payload.
	 *
	 * @param array<string, mixed>     $payload Layout payload.
	 * @param array<string, mixed>     $options Import options.
	 * @param array<string, mixed>|null $registry Optional live registry.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function import_layout_payload( array $payload, array $options = array(), $registry = null ) {
		$registry = $registry ? $registry : MRN_Figma_Sync_Registry::get_registry();

		if ( is_wp_error( $registry ) ) {
			return $registry;
		}

		if ( ! function_exists( 'get_field' ) || ! function_exists( 'update_field' ) ) {
			return new WP_Error( 'acf_missing', 'ACF is required to import MRN builder payloads.' );
		}

		$validation = self::validate_layout_payload( $payload, $registry );
		if ( ! empty( $validation['errors'] ) ) {
			return new WP_Error( 'validation_failed', 'The layout payload did not pass validation.', $validation );
		}

		$post_id = isset( $payload['target']['post_id'] ) ? absint( $payload['target']['post_id'] ) : 0;
		if ( ! $post_id ) {
			return new WP_Error( 'missing_target_post', 'A target post ID is required for import.' );
		}

		$dry_run  = ! empty( $options['dry_run'] );
		$before   = self::get_current_builder_state( $post_id, $registry );
		$changes  = self::build_change_summary( $before, isset( $payload['fields'] ) && is_array( $payload['fields'] ) ? $payload['fields'] : array() );
		$snapshot = self::create_snapshot( $post_id, $before );

		if ( $dry_run ) {
			return array(
				'ok'          => true,
				'dry_run'     => true,
				'post_id'     => $post_id,
				'snapshot_id' => $snapshot['snapshot_id'],
				'changes'     => $changes,
				'validation'  => $validation,
			);
		}

		$field_groups = isset( $registry['field_groups'] ) && is_array( $registry['field_groups'] ) ? $registry['field_groups'] : array();
		$fields       = isset( $payload['fields'] ) && is_array( $payload['fields'] ) ? $payload['fields'] : array();

		foreach ( $fields as $field_name => $field_value ) {
			if ( ! isset( $field_groups[ $field_name ] ) || ! is_array( $field_groups[ $field_name ] ) ) {
				continue;
			}

			$field_key = isset( $field_groups[ $field_name ]['field_key'] ) ? (string) $field_groups[ $field_name ]['field_key'] : '';
			$target    = '' !== $field_key ? $field_key : $field_name;

			update_field( $target, $field_value, $post_id );
		}

		update_post_meta(
			$post_id,
			self::LAST_IMPORT_META_KEY,
			array(
				'snapshot_id' => $snapshot['snapshot_id'],
				'imported_at' => gmdate( 'c' ),
				'changes'     => $changes,
			)
		);

		return array(
			'ok'          => true,
			'dry_run'     => false,
			'post_id'     => $post_id,
			'snapshot_id' => $snapshot['snapshot_id'],
			'changes'     => $changes,
			'validation'  => $validation,
		);
	}

	/**
	 * Roll back the most recent or a specific snapshot.
	 *
	 * @param int         $post_id Post ID.
	 * @param string|null $snapshot_id Snapshot ID.
	 * @param array<string, mixed>|null $registry Optional live registry.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function rollback( $post_id, $snapshot_id = null, $registry = null ) {
		$registry = $registry ? $registry : MRN_Figma_Sync_Registry::get_registry();

		if ( is_wp_error( $registry ) ) {
			return $registry;
		}

		if ( ! function_exists( 'update_field' ) ) {
			return new WP_Error( 'acf_missing', 'ACF is required to roll back MRN builder payloads.' );
		}

		$post_id   = absint( $post_id );
		$snapshots = get_post_meta( $post_id, self::SNAPSHOT_META_KEY );

		if ( empty( $snapshots ) || ! is_array( $snapshots ) ) {
			return new WP_Error( 'missing_snapshot', 'No MRN Figma Sync snapshots are available for this post.' );
		}

		$snapshot = null;

		if ( $snapshot_id ) {
			foreach ( array_reverse( $snapshots ) as $candidate ) {
				if ( is_array( $candidate ) && isset( $candidate['snapshot_id'] ) && (string) $candidate['snapshot_id'] === (string) $snapshot_id ) {
					$snapshot = $candidate;
					break;
				}
			}
		}

		if ( ! $snapshot ) {
			$latest = end( $snapshots );
			$snapshot = is_array( $latest ) ? $latest : null;
		}

		if ( ! is_array( $snapshot ) || empty( $snapshot['fields'] ) || ! is_array( $snapshot['fields'] ) ) {
			return new WP_Error( 'invalid_snapshot', 'The requested snapshot is not usable.' );
		}

		$field_groups = isset( $registry['field_groups'] ) && is_array( $registry['field_groups'] ) ? $registry['field_groups'] : array();

		foreach ( $snapshot['fields'] as $field_name => $field_value ) {
			if ( ! isset( $field_groups[ $field_name ] ) || ! is_array( $field_groups[ $field_name ] ) ) {
				continue;
			}

			$field_key = isset( $field_groups[ $field_name ]['field_key'] ) ? (string) $field_groups[ $field_name ]['field_key'] : '';
			$target    = '' !== $field_key ? $field_key : $field_name;

			update_field( $target, $field_value, $post_id );
		}

		return array(
			'ok'          => true,
			'post_id'     => $post_id,
			'snapshot_id' => isset( $snapshot['snapshot_id'] ) ? $snapshot['snapshot_id'] : '',
		);
	}

	/**
	 * Validate a row against the live component schema.
	 *
	 * @param array<string, mixed> $row Row payload.
	 * @param string               $field_name Field name.
	 * @param array<string, mixed> $registry Live registry.
	 * @param string               $path Validation path.
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	private static function validate_row( array $row, $field_name, array $registry, $path ) {
		$issues = array(
			'errors'   => array(),
			'warnings' => array(),
		);

		$layout = isset( $row['acf_fc_layout'] ) ? sanitize_key( (string) $row['acf_fc_layout'] ) : '';
		if ( '' === $layout ) {
			$issues['errors'][] = MRN_Figma_Sync_Plugin::build_issue(
				'missing_layout_name',
				'Every row must include acf_fc_layout.',
				$path . '.acf_fc_layout'
			);

			return $issues;
		}

		$component = MRN_Figma_Sync_Registry::get_component_by_field_and_layout( $registry, $field_name, $layout );
		if ( ! is_array( $component ) ) {
			$issues['errors'][] = MRN_Figma_Sync_Plugin::build_issue(
				'unsupported_layout',
				sprintf( 'Layout %1$s is not supported in %2$s.', $layout, $field_name ),
				$path . '.acf_fc_layout'
			);

			return $issues;
		}

		$fields       = isset( $component['fields'] ) && is_array( $component['fields'] ) ? $component['fields'] : array();
		$allowed_keys = array( 'acf_fc_layout' );

		foreach ( $fields as $field_definition ) {
			if ( ! is_array( $field_definition ) ) {
				continue;
			}

			$sub_name = isset( $field_definition['name'] ) ? (string) $field_definition['name'] : '';
			if ( '' === $sub_name ) {
				continue;
			}

			$allowed_keys[] = $sub_name;

			if ( ! array_key_exists( $sub_name, $row ) ) {
				if ( ! empty( $field_definition['required'] ) ) {
					$issues['errors'][] = MRN_Figma_Sync_Plugin::build_issue(
						'missing_required_field',
						sprintf( 'Field %1$s is required for layout %2$s.', $sub_name, $layout ),
						$path . '.' . $sub_name
					);
				}
				continue;
			}

			$field_issues = self::validate_field_value( $row[ $sub_name ], $field_definition, $path . '.' . $sub_name, $registry, $field_name );
			$issues['errors']   = array_merge( $issues['errors'], $field_issues['errors'] );
			$issues['warnings'] = array_merge( $issues['warnings'], $field_issues['warnings'] );
		}

		foreach ( array_keys( $row ) as $row_key ) {
			if ( ! in_array( $row_key, $allowed_keys, true ) ) {
				$issues['warnings'][] = MRN_Figma_Sync_Plugin::build_issue(
					'unexpected_field',
					sprintf( 'Field %1$s is not part of the live layout schema.', $row_key ),
					$path . '.' . $row_key
				);
			}
		}

		return $issues;
	}

	/**
	 * Validate an individual field value.
	 *
	 * @param mixed                $value Field value.
	 * @param array<string, mixed> $field_definition Field definition.
	 * @param string               $path Validation path.
	 * @param array<string, mixed> $registry Live registry.
	 * @param string               $field_name Parent field name for nested layouts.
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	private static function validate_field_value( $value, array $field_definition, $path, array $registry, $field_name ) {
		$issues = array(
			'errors'   => array(),
			'warnings' => array(),
		);

		$type    = isset( $field_definition['type'] ) ? (string) $field_definition['type'] : '';
		$name    = isset( $field_definition['name'] ) ? (string) $field_definition['name'] : '';
		$choices = isset( $field_definition['choices'] ) && is_array( $field_definition['choices'] ) ? $field_definition['choices'] : array();

		switch ( $type ) {
			case 'text':
			case 'textarea':
			case 'wysiwyg':
			case 'url':
			case 'oembed':
			case 'color_picker':
			case 'email':
				if ( ! is_scalar( $value ) ) {
					$issues['errors'][] = MRN_Figma_Sync_Plugin::build_issue( 'invalid_scalar', sprintf( 'Field %1$s must be scalar.', $name ), $path );
				}
				break;

			case 'number':
			case 'range':
				if ( ! is_numeric( $value ) ) {
					$issues['errors'][] = MRN_Figma_Sync_Plugin::build_issue( 'invalid_number', sprintf( 'Field %1$s must be numeric.', $name ), $path );
				}
				break;

			case 'true_false':
				if ( ! in_array( $value, array( 0, 1, true, false, '0', '1' ), true ) ) {
					$issues['errors'][] = MRN_Figma_Sync_Plugin::build_issue( 'invalid_boolean', sprintf( 'Field %1$s must be boolean-like.', $name ), $path );
				}
				break;

			case 'button_group':
			case 'radio':
			case 'select':
				if ( ! is_scalar( $value ) || ! isset( $choices[ (string) $value ] ) ) {
					$issues['errors'][] = MRN_Figma_Sync_Plugin::build_issue(
						'invalid_choice',
						sprintf( 'Field %1$s must use a registered choice.', $name ),
						$path,
						array(
							'allowed' => array_keys( $choices ),
						)
					);
				}
				break;

			case 'checkbox':
				if ( ! is_array( $value ) ) {
					$issues['errors'][] = MRN_Figma_Sync_Plugin::build_issue( 'invalid_checkbox', sprintf( 'Field %1$s must be an array.', $name ), $path );
				}
				break;

			case 'link':
				if ( ! is_array( $value ) || empty( $value['url'] ) ) {
					$issues['errors'][] = MRN_Figma_Sync_Plugin::build_issue( 'invalid_link', sprintf( 'Field %1$s must contain a link array.', $name ), $path );
				}
				break;

			case 'image':
			case 'file':
			case 'post_object':
				if ( ! is_numeric( $value ) ) {
					$issues['errors'][] = MRN_Figma_Sync_Plugin::build_issue( 'invalid_reference', sprintf( 'Field %1$s must be a WordPress reference ID.', $name ), $path );
				}
				break;

			case 'gallery':
			case 'relationship':
				if ( ! is_array( $value ) ) {
					$issues['errors'][] = MRN_Figma_Sync_Plugin::build_issue( 'invalid_reference_list', sprintf( 'Field %1$s must be an array of WordPress IDs.', $name ), $path );
				}
				break;

			case 'group':
			case 'repeater':
				if ( ! is_array( $value ) ) {
					$issues['errors'][] = MRN_Figma_Sync_Plugin::build_issue( 'invalid_object', sprintf( 'Field %1$s must be structured data.', $name ), $path );
					break;
				}

				$sub_fields = isset( $field_definition['fields'] ) && is_array( $field_definition['fields'] ) ? $field_definition['fields'] : array();
				$rows       = 'repeater' === $type ? array_values( $value ) : array( $value );

				foreach ( $rows as $row_index => $row ) {
					if ( ! is_array( $row ) ) {
						$issues['errors'][] = MRN_Figma_Sync_Plugin::build_issue( 'invalid_nested_row', sprintf( 'Field %1$s contains an invalid nested row.', $name ), $path . '.' . $row_index );
						continue;
					}

					foreach ( $sub_fields as $sub_field ) {
						if ( ! is_array( $sub_field ) ) {
							continue;
						}

						$sub_name = isset( $sub_field['name'] ) ? (string) $sub_field['name'] : '';
						if ( '' === $sub_name ) {
							continue;
						}

						if ( ! array_key_exists( $sub_name, $row ) ) {
							if ( ! empty( $sub_field['required'] ) ) {
								$issues['errors'][] = MRN_Figma_Sync_Plugin::build_issue(
									'missing_required_sub_field',
									sprintf( 'Field %1$s is required.', $sub_name ),
									'repeater' === $type ? $path . '.' . $row_index . '.' . $sub_name : $path . '.' . $sub_name
								);
							}
							continue;
						}

						$sub_path = 'repeater' === $type ? $path . '.' . $row_index . '.' . $sub_name : $path . '.' . $sub_name;
						$sub_issues = self::validate_field_value( $row[ $sub_name ], $sub_field, $sub_path, $registry, $field_name );
						$issues['errors']   = array_merge( $issues['errors'], $sub_issues['errors'] );
						$issues['warnings'] = array_merge( $issues['warnings'], $sub_issues['warnings'] );
					}
				}
				break;

			case 'flexible_content':
				if ( ! is_array( $value ) ) {
					$issues['errors'][] = MRN_Figma_Sync_Plugin::build_issue( 'invalid_nested_flexible_content', sprintf( 'Field %1$s must contain rows.', $name ), $path );
					break;
				}

				foreach ( array_values( $value ) as $row_index => $row ) {
					$row_issues = self::validate_row(
						is_array( $row ) ? $row : array(),
						$field_name,
						$registry,
						$path . '.' . $row_index
					);
					$issues['errors']   = array_merge( $issues['errors'], $row_issues['errors'] );
					$issues['warnings'] = array_merge( $issues['warnings'], $row_issues['warnings'] );
				}
				break;
		}

		return $issues;
	}

	/**
	 * Get the current managed builder state for a post.
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $registry Live registry.
	 * @return array<string, mixed>
	 */
	private static function get_current_builder_state( $post_id, array $registry ) {
		$fields = array();

		foreach ( $registry['field_groups'] as $field_name => $field_definition ) {
			if ( ! is_array( $field_definition ) ) {
				continue;
			}

			$fields[ $field_name ] = get_field( $field_name, $post_id );
		}

		return $fields;
	}

	/**
	 * Create a rollback snapshot.
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $fields Current field values.
	 * @return array<string, mixed>
	 */
	private static function create_snapshot( $post_id, array $fields ) {
		$snapshot = array(
			'snapshot_id' => function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( 'mrn_figma_', true ),
			'created_at'  => gmdate( 'c' ),
			'fields'      => $fields,
		);

		add_post_meta( $post_id, self::SNAPSHOT_META_KEY, $snapshot );

		return $snapshot;
	}

	/**
	 * Build a small import summary.
	 *
	 * @param array<string, mixed> $before Existing values.
	 * @param array<string, mixed> $after Incoming values.
	 * @return array<string, array<string, int>>
	 */
	private static function build_change_summary( array $before, array $after ) {
		$summary = array();

		foreach ( $after as $field_name => $field_value ) {
			$before_count = is_array( $before[ $field_name ] ?? null ) ? count( $before[ $field_name ] ) : ( empty( $before[ $field_name ] ) ? 0 : 1 );
			$after_count  = is_array( $field_value ) ? count( $field_value ) : ( empty( $field_value ) ? 0 : 1 );

			$summary[ $field_name ] = array(
				'before' => $before_count,
				'after'  => $after_count,
			);
		}

		return $summary;
	}
}
