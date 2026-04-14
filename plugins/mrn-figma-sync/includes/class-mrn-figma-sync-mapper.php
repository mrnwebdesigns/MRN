<?php
/**
 * Deterministic Figma payload mapper.
 *
 * @package mrn-figma-sync
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps normalized Figma payloads into WordPress layout payloads.
 */
final class MRN_Figma_Sync_Mapper {
	/**
	 * Map a normalized Figma payload into a layout payload.
	 *
	 * @param array<string, mixed> $payload Figma payload.
	 * @param array<string, mixed>|null $registry Optional live registry.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function map_payload( array $payload, $registry = null ) {
		$registry = $registry ? $registry : MRN_Figma_Sync_Registry::get_registry();

		if ( is_wp_error( $registry ) ) {
			return $registry;
		}

		$schema_issues = MRN_Figma_Sync_Validator::validate( $payload, MRN_Figma_Sync_Schema::get_figma_export_schema() );
		$result        = array(
			'ok'             => empty( $schema_issues ),
			'layout_payload' => self::build_empty_layout_payload( $payload ),
			'errors'         => $schema_issues,
			'warnings'       => array(),
			'matches'        => array(),
		);

		if ( ! empty( $schema_issues ) ) {
			return $result;
		}

		$sections = isset( $payload['sections'] ) && is_array( $payload['sections'] ) ? $payload['sections'] : array();

		foreach ( $sections as $index => $section ) {
			if ( ! is_array( $section ) ) {
				continue;
			}

			$mapped = self::map_section(
				$section,
				$registry,
				array(
					'path'             => 'sections.' . $index,
					'allowed_layouts'  => array(),
					'default_field'    => '',
				)
			);

			if ( is_wp_error( $mapped ) ) {
				foreach ( $mapped->get_error_data() as $issue ) {
					$result['errors'][] = $issue;
				}
				continue;
			}

			$field_name = isset( $mapped['field_name'] ) ? (string) $mapped['field_name'] : '';
			$row        = isset( $mapped['row'] ) && is_array( $mapped['row'] ) ? $mapped['row'] : array();

			if ( '' === $field_name || empty( $row ) ) {
				continue;
			}

			if ( ! isset( $result['layout_payload']['fields'][ $field_name ] ) || ! is_array( $result['layout_payload']['fields'][ $field_name ] ) ) {
				$result['layout_payload']['fields'][ $field_name ] = array();
			}

			$result['layout_payload']['fields'][ $field_name ][] = $row;
			$result['matches'][] = array(
				'path'        => 'sections.' . $index,
				'field_name'  => $field_name,
				'layout'      => isset( $row['acf_fc_layout'] ) ? $row['acf_fc_layout'] : '',
			);
		}

		$sidebar = isset( $payload['sidebar'] ) && is_array( $payload['sidebar'] ) ? $payload['sidebar'] : array();

		if ( isset( $sidebar['layout'] ) ) {
			$result['layout_payload']['fields']['sidebar_layout'] = sanitize_key( (string) $sidebar['layout'] );
		}

		if ( ! empty( $sidebar['sections'] ) && is_array( $sidebar['sections'] ) ) {
			foreach ( $sidebar['sections'] as $index => $section ) {
				if ( ! is_array( $section ) ) {
					continue;
				}

				$mapped = self::map_section(
					$section,
					$registry,
					array(
						'path'             => 'sidebar.sections.' . $index,
						'allowed_layouts'  => array(),
						'default_field'    => 'page_sidebar_rows',
					)
				);

				if ( is_wp_error( $mapped ) ) {
					foreach ( $mapped->get_error_data() as $issue ) {
						$result['errors'][] = $issue;
					}
					continue;
				}

				$row = isset( $mapped['row'] ) && is_array( $mapped['row'] ) ? $mapped['row'] : array();
				if ( ! empty( $row ) ) {
					$result['layout_payload']['fields']['page_sidebar_rows'][] = $row;
				}
			}

			if ( empty( $result['layout_payload']['fields']['sidebar_layout'] ) || 'none' === $result['layout_payload']['fields']['sidebar_layout'] ) {
				$result['layout_payload']['fields']['sidebar_layout'] = 'right';
			}
		}

		$validation = MRN_Figma_Sync_Importer::validate_layout_payload( $result['layout_payload'], $registry );

		$result['errors']   = array_merge( $result['errors'], $validation['errors'] );
		$result['warnings'] = array_merge( $result['warnings'], $validation['warnings'] );
		$result['ok']       = empty( $result['errors'] );

		return $result;
	}

	/**
	 * Build the empty layout payload structure.
	 *
	 * @param array<string, mixed> $payload Source payload.
	 * @return array<string, mixed>
	 */
	private static function build_empty_layout_payload( array $payload ) {
		return array(
			'schema_version' => '1.0.0',
			'source'         => array(
				'document_id' => isset( $payload['document_id'] ) ? (string) $payload['document_id'] : '',
				'page_name'   => isset( $payload['page_name'] ) ? (string) $payload['page_name'] : '',
			),
			'target'         => isset( $payload['target'] ) && is_array( $payload['target'] ) ? $payload['target'] : array(),
			'fields'         => array(
				'page_hero_rows'          => array(),
				'page_content_rows'       => array(),
				'page_after_content_rows' => array(),
				'sidebar_layout'          => 'none',
				'page_sidebar_rows'       => array(),
			),
		);
	}

	/**
	 * Map a single section into a WordPress row.
	 *
	 * @param array<string, mixed> $section Section payload.
	 * @param array<string, mixed> $registry Live registry.
	 * @param array<string, mixed> $context Mapping context.
	 * @return array<string, mixed>|WP_Error
	 */
	private static function map_section( array $section, array $registry, array $context ) {
		$match = self::resolve_component_match( $section, $registry, $context );
		if ( is_wp_error( $match ) ) {
			return $match;
		}

		$field_name = isset( $match['field_name'] ) ? (string) $match['field_name'] : '';
		$layout     = isset( $match['layout'] ) ? (string) $match['layout'] : '';
		$mapping    = isset( $match['mapping'] ) && is_array( $match['mapping'] ) ? $match['mapping'] : array();
		$component  = MRN_Figma_Sync_Registry::get_component_by_field_and_layout( $registry, $field_name, $layout );

		if ( ! is_array( $component ) ) {
			return new WP_Error(
				'unknown_component',
				'The resolved component does not exist in the live registry.',
				array(
					MRN_Figma_Sync_Plugin::build_issue(
						'unknown_component',
						sprintf( 'The component %1$s/%2$s is not registered in the live builder contract.', $field_name, $layout ),
						(string) $context['path']
					),
				)
			);
		}

		$row        = array(
			'acf_fc_layout' => $layout,
		);
		$fields     = isset( $component['fields'] ) && is_array( $component['fields'] ) ? $component['fields'] : array();
		$issues     = array();
		$section_props = isset( $section['props'] ) && is_array( $section['props'] ) ? $section['props'] : array();

		foreach ( $fields as $field_definition ) {
			if ( ! is_array( $field_definition ) ) {
				continue;
			}

			$field_name_key = isset( $field_definition['name'] ) ? (string) $field_definition['name'] : '';
			if ( '' === $field_name_key ) {
				continue;
			}

			$source_value = self::extract_source_value( $section, $mapping, $field_definition );

			if ( null === $source_value && array_key_exists( $field_name_key, $section_props ) ) {
				$source_value = $section_props[ $field_name_key ];
			}

			if ( null === $source_value && isset( $mapping['defaults'][ $field_name_key ] ) ) {
				$source_value = $mapping['defaults'][ $field_name_key ];
			}

			if ( null === $source_value && isset( $mapping['static_values'][ $field_name_key ] ) ) {
				$source_value = $mapping['static_values'][ $field_name_key ];
			}

			if ( null === $source_value ) {
				if ( ! empty( $field_definition['required'] ) ) {
					$issues[] = MRN_Figma_Sync_Plugin::build_issue(
						'missing_required_field',
						sprintf( 'Field %1$s is required for %2$s.', $field_name_key, $layout ),
						(string) $context['path'] . '.props.' . $field_name_key
					);
				}
				continue;
			}

			$normalized = self::normalize_field_value(
				$source_value,
				$field_definition,
				$registry,
				array(
					'path'          => (string) $context['path'] . '.props.' . $field_name_key,
					'allowed_field' => $field_name,
				)
			);

			if ( is_wp_error( $normalized ) ) {
				foreach ( $normalized->get_error_data() as $issue ) {
					$issues[] = $issue;
				}
				continue;
			}

			$row[ $field_name_key ] = $normalized;
		}

		if ( ! empty( $issues ) ) {
			return new WP_Error( 'mapping_failed', 'The section could not be normalized.', $issues );
		}

		return array(
			'field_name' => $field_name,
			'row'        => $row,
		);
	}

	/**
	 * Resolve a section to a registry component.
	 *
	 * @param array<string, mixed> $section Section payload.
	 * @param array<string, mixed> $registry Live registry.
	 * @param array<string, mixed> $context Mapping context.
	 * @return array<string, mixed>|WP_Error
	 */
	private static function resolve_component_match( array $section, array $registry, array $context ) {
		$target_field  = isset( $section['target']['field_name'] ) ? sanitize_key( (string) $section['target']['field_name'] ) : '';
		$target_layout = isset( $section['target']['layout'] ) ? sanitize_key( (string) $section['target']['layout'] ) : '';
		$default_field = isset( $context['default_field'] ) ? sanitize_key( (string) $context['default_field'] ) : '';
		$allowed       = isset( $context['allowed_layouts'] ) && is_array( $context['allowed_layouts'] ) ? array_values( array_filter( array_map( 'sanitize_key', $context['allowed_layouts'] ) ) ) : array();

		if ( '' === $target_field && '' !== $default_field ) {
			$target_field = $default_field;
		}

		if ( '' !== $target_field && '' !== $target_layout ) {
			$component = MRN_Figma_Sync_Registry::get_component_by_field_and_layout( $registry, $target_field, $target_layout );

			if ( is_array( $component ) ) {
				return array(
					'field_name' => $target_field,
					'layout'     => $target_layout,
					'mapping'    => array(),
				);
			}
		}

		$candidates = array();

		if ( isset( $section['source_component'] ) && is_array( $section['source_component'] ) ) {
			$candidates = array_merge( $candidates, MRN_Figma_Sync_Plugin::get_lookup_candidates( $section['source_component'] ) );
		}

		if ( isset( $section['component'] ) ) {
			$candidates = array_merge( $candidates, MRN_Figma_Sync_Plugin::get_lookup_candidates( $section['component'] ) );
		}

		$candidates = array_values( array_filter( array_unique( $candidates ) ) );
		$mappings   = MRN_Figma_Sync_Plugin::get_component_mappings();

		foreach ( $mappings as $mapping ) {
			if ( ! is_array( $mapping ) ) {
				continue;
			}

			$mapping_field  = isset( $mapping['target_field'] ) ? sanitize_key( (string) $mapping['target_field'] ) : '';
			$mapping_layout = isset( $mapping['target_layout'] ) ? sanitize_key( (string) $mapping['target_layout'] ) : '';

			if ( '' !== $target_field && $mapping_field !== $target_field ) {
				continue;
			}

			if ( ! empty( $allowed ) && ! in_array( $mapping_layout, $allowed, true ) ) {
				continue;
			}

			if ( ! empty( $target_layout ) && $mapping_layout !== $target_layout ) {
				continue;
			}

			$match_values = isset( $mapping['match'] ) && is_array( $mapping['match'] ) ? $mapping['match'] : array();
			foreach ( $match_values as $match_value ) {
				$normalized_match = MRN_Figma_Sync_Plugin::normalize_lookup_key( $match_value );
				foreach ( $candidates as $candidate ) {
					if ( $normalized_match === MRN_Figma_Sync_Plugin::normalize_lookup_key( $candidate ) ) {
						return array(
							'field_name' => $mapping_field,
							'layout'     => $mapping_layout,
							'mapping'    => $mapping,
						);
					}
				}
			}
		}

		if ( '' !== $target_layout ) {
			$field_groups = isset( $registry['field_groups'] ) && is_array( $registry['field_groups'] ) ? $registry['field_groups'] : array();
			$fields       = '' !== $target_field ? array( $target_field ) : array_keys( $field_groups );

			foreach ( $fields as $field_name ) {
				$component = MRN_Figma_Sync_Registry::get_component_by_field_and_layout( $registry, $field_name, $target_layout );
				if ( is_array( $component ) && ( empty( $allowed ) || in_array( $target_layout, $allowed, true ) ) ) {
					return array(
						'field_name' => $field_name,
						'layout'     => $target_layout,
						'mapping'    => array(),
					);
				}
			}
		}

		return new WP_Error(
			'unsupported_component',
			'The section did not match a supported MRN component.',
			array(
				MRN_Figma_Sync_Plugin::build_issue(
					'unsupported_component',
					'No builder mapping matched this Figma component.',
					(string) $context['path'],
					array(
						'candidates' => $candidates,
						'allowed_layouts' => $allowed,
					)
				),
			)
		);
	}

	/**
	 * Extract a source value for a field from props, slots, tokens, or aliases.
	 *
	 * @param array<string, mixed> $section Section payload.
	 * @param array<string, mixed> $mapping Mapping definition.
	 * @param array<string, mixed> $field_definition Registry field definition.
	 * @return mixed|null
	 */
	private static function extract_source_value( array $section, array $mapping, array $field_definition ) {
		$field_name = isset( $field_definition['name'] ) ? (string) $field_definition['name'] : '';
		$props      = isset( $section['props'] ) && is_array( $section['props'] ) ? $section['props'] : array();
		$slots      = isset( $section['slots'] ) && is_array( $section['slots'] ) ? $section['slots'] : array();
		$tokens     = isset( $section['tokens'] ) && is_array( $section['tokens'] ) ? $section['tokens'] : array();

		if ( array_key_exists( $field_name, $props ) ) {
			return $props[ $field_name ];
		}

		if ( array_key_exists( $field_name, $slots ) ) {
			return $slots[ $field_name ];
		}

		$field_aliases = isset( $mapping['field_aliases'] ) && is_array( $mapping['field_aliases'] ) ? $mapping['field_aliases'] : array();

		if ( isset( $field_aliases[ $field_name ] ) ) {
			$aliases = is_array( $field_aliases[ $field_name ] ) ? $field_aliases[ $field_name ] : array( $field_aliases[ $field_name ] );

			foreach ( $aliases as $alias ) {
				$alias = (string) $alias;
				if ( array_key_exists( $alias, $props ) ) {
					return $props[ $alias ];
				}

				if ( array_key_exists( $alias, $slots ) ) {
					return $slots[ $alias ];
				}
			}
		}

		if ( in_array( $field_name, array( 'background_color', 'link_color' ), true ) ) {
			foreach ( array( 'background_color', 'background', 'surface', 'color' ) as $token_key ) {
				if ( array_key_exists( $token_key, $tokens ) ) {
					return $tokens[ $token_key ];
				}
			}
		}

		if ( 'bottom_accent_style' === $field_name ) {
			foreach ( array( 'bottom_accent_style', 'graphic_element', 'accent' ) as $token_key ) {
				if ( array_key_exists( $token_key, $tokens ) ) {
					return $tokens[ $token_key ];
				}
			}
		}

		return null;
	}

	/**
	 * Normalize a source value for a specific field schema.
	 *
	 * @param mixed                $value Raw source value.
	 * @param array<string, mixed> $field_definition Registry field definition.
	 * @param array<string, mixed> $registry Live registry.
	 * @param array<string, mixed> $context Normalization context.
	 * @return mixed|WP_Error
	 */
	private static function normalize_field_value( $value, array $field_definition, array $registry, array $context ) {
		$type = isset( $field_definition['type'] ) ? (string) $field_definition['type'] : '';
		$name = isset( $field_definition['name'] ) ? (string) $field_definition['name'] : '';

		switch ( $type ) {
			case 'text':
			case 'textarea':
			case 'wysiwyg':
			case 'url':
			case 'oembed':
			case 'color_picker':
			case 'message':
			case 'email':
				if ( is_scalar( $value ) ) {
					return (string) $value;
				}
				break;

			case 'number':
			case 'range':
				if ( is_numeric( $value ) ) {
					return false !== strpos( (string) $value, '.' ) ? (float) $value : (int) $value;
				}
				break;

			case 'true_false':
				if ( is_bool( $value ) ) {
					return $value ? 1 : 0;
				}

				if ( in_array( $value, array( 1, '1', 'true', 'yes', 'on' ), true ) ) {
					return 1;
				}

				return 0;

			case 'button_group':
			case 'radio':
			case 'select':
				$resolved = self::resolve_choice_value( $value, $field_definition, $registry );
				if ( null !== $resolved ) {
					return $resolved;
				}
				break;

			case 'checkbox':
				if ( ! is_array( $value ) ) {
					return array();
				}

				$values = array();
				foreach ( $value as $item ) {
					$resolved = self::resolve_choice_value( $item, $field_definition, $registry );
					if ( null !== $resolved ) {
						$values[] = $resolved;
					}
				}

				return array_values( array_unique( $values ) );

			case 'link':
				return self::normalize_link_value( $value );

			case 'image':
			case 'file':
			case 'post_object':
				return self::normalize_reference_value( $value, $context );

			case 'gallery':
			case 'relationship':
				if ( ! is_array( $value ) ) {
					return array();
				}

				$values = array();
				foreach ( $value as $index => $item ) {
					$normalized = self::normalize_reference_value(
						$item,
						array_merge(
							$context,
							array(
								'path' => $context['path'] . '.' . $index,
							)
						)
					);

					if ( is_wp_error( $normalized ) ) {
						return $normalized;
					}

					$values[] = $normalized;
				}

				return $values;

			case 'group':
				return self::normalize_group_or_repeater_item( $value, $field_definition, $registry, $context, false );

			case 'repeater':
				if ( ! is_array( $value ) ) {
					break;
				}

				$rows = array();
				foreach ( array_values( $value ) as $index => $row ) {
					$normalized = self::normalize_group_or_repeater_item(
						$row,
						$field_definition,
						$registry,
						array_merge(
							$context,
							array(
								'path' => $context['path'] . '.' . $index,
							)
						),
						true
					);

					if ( is_wp_error( $normalized ) ) {
						return $normalized;
					}

					$rows[] = $normalized;
				}

				return $rows;

			case 'flexible_content':
				if ( ! is_array( $value ) ) {
					break;
				}

				return self::normalize_flexible_rows( $value, $field_definition, $registry, $context );
		}

		return new WP_Error(
			'invalid_field_value',
			'The field value did not match the expected shape.',
			array(
				MRN_Figma_Sync_Plugin::build_issue(
					'invalid_field_value',
					sprintf( 'Field %1$s could not be normalized as %2$s.', $name, $type ),
					(string) $context['path']
				),
			)
		);
	}

	/**
	 * Normalize a group or repeater item.
	 *
	 * @param mixed                $value Raw field value.
	 * @param array<string, mixed> $field_definition Registry field definition.
	 * @param array<string, mixed> $registry Live registry.
	 * @param array<string, mixed> $context Normalization context.
	 * @param bool                 $is_repeater Whether the shape is a repeater row.
	 * @return array<string, mixed>|WP_Error
	 */
	private static function normalize_group_or_repeater_item( $value, array $field_definition, array $registry, array $context, $is_repeater ) {
		if ( ! is_array( $value ) ) {
			return new WP_Error(
				'invalid_group_value',
				'Expected an object-like field payload.',
				array(
					MRN_Figma_Sync_Plugin::build_issue(
						'invalid_group_value',
						'Expected an object-like field payload.',
						(string) $context['path']
					),
				)
			);
		}

		$sub_fields = isset( $field_definition['fields'] ) && is_array( $field_definition['fields'] ) ? $field_definition['fields'] : array();
		$item       = array();
		$issues     = array();

		foreach ( $sub_fields as $sub_field ) {
			if ( ! is_array( $sub_field ) ) {
				continue;
			}

			$sub_name = isset( $sub_field['name'] ) ? (string) $sub_field['name'] : '';
			if ( '' === $sub_name ) {
				continue;
			}

			if ( ! array_key_exists( $sub_name, $value ) ) {
				if ( ! empty( $sub_field['required'] ) ) {
					$issues[] = MRN_Figma_Sync_Plugin::build_issue(
						'missing_required_sub_field',
						sprintf( 'Field %1$s is required.', $sub_name ),
						(string) $context['path'] . '.' . $sub_name
					);
				}
				continue;
			}

			$normalized = self::normalize_field_value(
				$value[ $sub_name ],
				$sub_field,
				$registry,
				array_merge(
					$context,
					array(
						'path' => (string) $context['path'] . '.' . $sub_name,
					)
				)
			);

			if ( is_wp_error( $normalized ) ) {
				foreach ( $normalized->get_error_data() as $issue ) {
					$issues[] = $issue;
				}
				continue;
			}

			$item[ $sub_name ] = $normalized;
		}

		if ( ! empty( $issues ) ) {
			return new WP_Error( 'invalid_group_or_repeater_item', 'One or more nested fields were invalid.', $issues );
		}

		return $item;
	}

	/**
	 * Normalize flexible-content rows or nested component sections.
	 *
	 * @param array<int, mixed>    $value Raw row list.
	 * @param array<string, mixed> $field_definition Registry field definition.
	 * @param array<string, mixed> $registry Live registry.
	 * @param array<string, mixed> $context Normalization context.
	 * @return array<int, array<string, mixed>>|WP_Error
	 */
	private static function normalize_flexible_rows( array $value, array $field_definition, array $registry, array $context ) {
		$allowed_layouts = isset( $field_definition['layouts'] ) && is_array( $field_definition['layouts'] ) ? array_keys( $field_definition['layouts'] ) : array();
		$rows            = array();

		foreach ( array_values( $value ) as $index => $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			if ( isset( $item['acf_fc_layout'] ) ) {
				$row = $item;
				if ( ! empty( $allowed_layouts ) && ! in_array( sanitize_key( (string) $item['acf_fc_layout'] ), $allowed_layouts, true ) ) {
					return new WP_Error(
						'invalid_nested_layout',
						'Nested flexible content used an unsupported layout.',
						array(
							MRN_Figma_Sync_Plugin::build_issue(
								'invalid_nested_layout',
								sprintf( 'Layout %1$s is not allowed in this slot.', (string) $item['acf_fc_layout'] ),
								(string) $context['path'] . '.' . $index . '.acf_fc_layout',
								array(
									'allowed_layouts' => $allowed_layouts,
								)
							),
						)
					);
				}
			} else {
				$mapped = self::map_section(
					$item,
					$registry,
					array(
						'path'            => (string) $context['path'] . '.' . $index,
						'allowed_layouts' => $allowed_layouts,
						'default_field'   => isset( $context['allowed_field'] ) ? (string) $context['allowed_field'] : '',
					)
				);

				if ( is_wp_error( $mapped ) ) {
					return $mapped;
				}

				$row = isset( $mapped['row'] ) && is_array( $mapped['row'] ) ? $mapped['row'] : array();
			}

			$rows[] = $row;
		}

		return $rows;
	}

	/**
	 * Resolve a choice value directly or via tokens.
	 *
	 * @param mixed                $value Raw value.
	 * @param array<string, mixed> $field_definition Field definition.
	 * @param array<string, mixed> $registry Live registry.
	 * @return string|null
	 */
	private static function resolve_choice_value( $value, array $field_definition, array $registry ) {
		$choices = isset( $field_definition['choices'] ) && is_array( $field_definition['choices'] ) ? $field_definition['choices'] : array();
		$name    = isset( $field_definition['name'] ) ? (string) $field_definition['name'] : '';

		if ( is_scalar( $value ) ) {
			$value = (string) $value;
			if ( isset( $choices[ $value ] ) ) {
				return $value;
			}

			$normalized = MRN_Figma_Sync_Plugin::normalize_lookup_key( $value );
			foreach ( $choices as $choice_value => $choice_label ) {
				if ( MRN_Figma_Sync_Plugin::normalize_lookup_key( $choice_value ) === $normalized || MRN_Figma_Sync_Plugin::normalize_lookup_key( $choice_label ) === $normalized ) {
					return (string) $choice_value;
				}
			}
		}

		if ( in_array( $name, array( 'background_color', 'link_color' ), true ) ) {
			return self::resolve_token_value( $value, 'site_colors', $registry );
		}

		if ( 'bottom_accent_style' === $name ) {
			return self::resolve_token_value( $value, 'graphic_elements', $registry );
		}

		if ( 'section_width' === $name ) {
			return self::resolve_token_value( $value, 'section_widths', $registry );
		}

		return null;
	}

	/**
	 * Resolve a token value from explicit mappings or the live registry.
	 *
	 * @param mixed                $value Raw value.
	 * @param string               $token_type Token type.
	 * @param array<string, mixed> $registry Live registry.
	 * @return string|null
	 */
	private static function resolve_token_value( $value, $token_type, array $registry ) {
		$definitions = MRN_Figma_Sync_Plugin::get_token_mappings();
		$mappings    = isset( $definitions[ $token_type ] ) && is_array( $definitions[ $token_type ] ) ? $definitions[ $token_type ] : array();
		$registry_map = isset( $registry['tokens'][ $token_type ] ) && is_array( $registry['tokens'][ $token_type ] ) ? $registry['tokens'][ $token_type ] : array();
		$candidates   = MRN_Figma_Sync_Plugin::get_lookup_candidates( $value );

		foreach ( $candidates as $candidate ) {
			$normalized = MRN_Figma_Sync_Plugin::normalize_lookup_key( $candidate );

			foreach ( $mappings as $source => $target ) {
				if ( MRN_Figma_Sync_Plugin::normalize_lookup_key( $source ) === $normalized && isset( $registry_map[ $target ] ) ) {
					return (string) $target;
				}
			}

			foreach ( $registry_map as $slug => $token ) {
				if ( is_array( $token ) ) {
					$token_name  = isset( $token['name'] ) ? (string) $token['name'] : $slug;
					$token_value = isset( $token['value'] ) ? (string) $token['value'] : '';
				} else {
					$token_name  = is_scalar( $token ) ? (string) $token : $slug;
					$token_value = '';
				}

				if ( MRN_Figma_Sync_Plugin::normalize_lookup_key( $slug ) === $normalized || MRN_Figma_Sync_Plugin::normalize_lookup_key( $token_name ) === $normalized || ( '' !== $token_value && strtoupper( $token_value ) === strtoupper( $candidate ) ) ) {
					return (string) $slug;
				}
			}
		}

		return null;
	}

	/**
	 * Normalize a link field.
	 *
	 * @param mixed $value Raw link value.
	 * @return array<string, string>
	 */
	private static function normalize_link_value( $value ) {
		if ( is_scalar( $value ) ) {
			$url = esc_url_raw( (string) $value );

			return array(
				'url'    => $url,
				'title'  => $url,
				'target' => '',
			);
		}

		if ( is_array( $value ) ) {
			$url = '';
			if ( isset( $value['url'] ) && is_scalar( $value['url'] ) ) {
				$url = esc_url_raw( (string) $value['url'] );
			} elseif ( isset( $value['href'] ) && is_scalar( $value['href'] ) ) {
				$url = esc_url_raw( (string) $value['href'] );
			}

			return array(
				'url'    => $url,
				'title'  => isset( $value['title'] ) && is_scalar( $value['title'] )
					? (string) $value['title']
					: ( isset( $value['label'] ) && is_scalar( $value['label'] ) ? (string) $value['label'] : $url ),
				'target' => isset( $value['target'] ) && is_scalar( $value['target'] ) ? (string) $value['target'] : '',
			);
		}

		return array(
			'url'    => '',
			'title'  => '',
			'target' => '',
		);
	}

	/**
	 * Normalize an image/file/post object style reference.
	 *
	 * @param mixed                $value Raw value.
	 * @param array<string, mixed> $context Normalization context.
	 * @return int|WP_Error
	 */
	private static function normalize_reference_value( $value, array $context ) {
		if ( is_numeric( $value ) ) {
			return (int) $value;
		}

		if ( is_array( $value ) ) {
			foreach ( array( 'attachment_id', 'id', 'ID', 'post_id' ) as $reference_key ) {
				if ( isset( $value[ $reference_key ] ) && is_numeric( $value[ $reference_key ] ) ) {
					return (int) $value[ $reference_key ];
				}
			}
		}

		return new WP_Error(
			'invalid_reference',
			'Expected a WordPress attachment/post reference.',
			array(
				MRN_Figma_Sync_Plugin::build_issue(
					'invalid_reference',
					'Expected a WordPress attachment/post reference.',
					(string) $context['path']
				),
			)
		);
	}
}
