<?php
/**
 * Live registry discovery against ACF, the theme builder, and Site Styles.
 *
 * @package mrn-figma-sync
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Discovers the current layout contract from the running stack.
 */
final class MRN_Figma_Sync_Registry {
	/**
	 * Get the live registry.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public static function get_registry() {
		static $registry = null;

		if ( null !== $registry ) {
			return $registry;
		}

		if ( ! function_exists( 'acf_get_field' ) ) {
			return new WP_Error( 'acf_missing', 'ACF is required to build the live MRN builder registry.' );
		}

		$field_groups         = array();
		$components           = array();
		$hidden_builder_names = function_exists( 'mrn_base_stack_get_hidden_builder_layouts' ) ? mrn_base_stack_get_hidden_builder_layouts() : array();

		foreach ( self::get_builder_bucket_definitions() as $field_name => $bucket_definition ) {
			$field = self::load_builder_field( $field_name, $bucket_definition );

			if ( ! is_array( $field ) ) {
				continue;
			}

			$normalized_field = self::normalize_field_definition(
				$field,
				array(
					'path'   => $field_name,
					'clones' => array(),
				)
			);

			$field_groups[ $field_name ] = array(
				'field_name' => $field_name,
				'field_key'  => isset( $field['key'] ) ? (string) $field['key'] : '',
				'label'      => isset( $field['label'] ) ? trim( wp_strip_all_tags( (string) $field['label'] ) ) : ucfirst( str_replace( '_', ' ', $field_name ) ),
				'bucket'     => $bucket_definition['bucket'],
				'type'       => isset( $field['type'] ) ? (string) $field['type'] : '',
				'choices'    => isset( $normalized_field['choices'] ) ? $normalized_field['choices'] : array(),
				'layouts'    => isset( $normalized_field['layouts'] ) ? $normalized_field['layouts'] : array(),
			);

			if ( empty( $field_groups[ $field_name ]['layouts'] ) ) {
				continue;
			}

			foreach ( $field_groups[ $field_name ]['layouts'] as $layout_name => $layout_definition ) {
				$component_key = self::get_component_key( $field_name, $layout_name );

				$components[ $component_key ] = array(
					'component_key' => $component_key,
					'field_name'    => $field_name,
					'field_key'     => $field_groups[ $field_name ]['field_key'],
					'bucket'        => $bucket_definition['bucket'],
					'layout'        => $layout_name,
					'label'         => isset( $layout_definition['label'] ) ? $layout_definition['label'] : ucfirst( str_replace( '_', ' ', $layout_name ) ),
					'is_hidden'     => in_array( $layout_name, $hidden_builder_names, true ),
					'fields'        => isset( $layout_definition['fields'] ) ? $layout_definition['fields'] : array(),
					'raw_label'     => isset( $layout_definition['raw_label'] ) ? $layout_definition['raw_label'] : '',
				);
			}
		}

		$registry = array(
			'schema_version' => '1.0.0',
			'generated_at'   => gmdate( 'c' ),
			'field_groups'   => $field_groups,
			'components'     => $components,
			'reusable_blocks' => self::get_reusable_block_registry(),
			'tokens'         => self::get_token_registry(),
			'constraints'    => array(
				'hidden_builder_layouts' => array_values(
					array_filter(
						array_map( 'sanitize_key', is_array( $hidden_builder_names ) ? $hidden_builder_names : array() )
					)
				),
				'builder_supported_post_types' => function_exists( 'mrn_base_stack_get_builder_supported_post_types' ) ? mrn_base_stack_get_builder_supported_post_types() : array(),
				'hero_supported_post_types' => function_exists( 'mrn_base_stack_get_hero_supported_post_types' ) ? mrn_base_stack_get_hero_supported_post_types() : array(),
				'after_content_supported_post_types' => function_exists( 'mrn_base_stack_get_after_content_supported_post_types' ) ? mrn_base_stack_get_after_content_supported_post_types() : array(),
			),
		);

		return $registry;
	}

	/**
	 * Get builder bucket definitions.
	 *
	 * @return array<string, array<string, string>>
	 */
	private static function get_builder_bucket_definitions() {
		return array(
			'page_hero_rows'          => array(
				'field_key' => 'field_mrn_page_hero_rows',
				'bucket'    => 'hero',
			),
			'page_content_rows'       => array(
				'field_key' => 'field_mrn_page_content_rows',
				'bucket'    => 'content',
			),
			'page_after_content_rows' => array(
				'field_key' => 'field_mrn_page_after_content_rows',
				'bucket'    => 'after_content',
			),
			'page_sidebar_rows'       => array(
				'field_key' => 'field_mrn_sidebar_rows',
				'bucket'    => 'sidebar',
			),
			'sidebar_layout'          => array(
				'field_key' => 'field_mrn_sidebar_layout',
				'bucket'    => 'sidebar_setting',
			),
		);
	}

	/**
	 * Load a builder field from ACF.
	 *
	 * @param string               $field_name Field name.
	 * @param array<string, mixed> $bucket_definition Bucket definition.
	 * @return array<string, mixed>|null
	 */
	private static function load_builder_field( $field_name, array $bucket_definition ) {
		$field_key = isset( $bucket_definition['field_key'] ) ? (string) $bucket_definition['field_key'] : '';

		if ( '' !== $field_key ) {
			$field = acf_get_field( $field_key );

			if ( is_array( $field ) ) {
				return $field;
			}
		}

		$field_groups = function_exists( 'acf_get_field_groups' )
			? acf_get_field_groups( array( 'post_type' => 'page' ) )
			: array();

		if ( ! is_array( $field_groups ) ) {
			return null;
		}

		foreach ( $field_groups as $field_group ) {
			$group_key = isset( $field_group['key'] ) ? (string) $field_group['key'] : '';
			if ( '' === $group_key || ! function_exists( 'acf_get_fields' ) ) {
				continue;
			}

			$fields = acf_get_fields( $group_key );

			if ( ! is_array( $fields ) ) {
				continue;
			}

			foreach ( $fields as $field ) {
				if ( ! is_array( $field ) ) {
					continue;
				}

				if ( $field_name === (string) ( $field['name'] ?? '' ) ) {
					return $field;
				}
			}
		}

		return null;
	}

	/**
	 * Normalize an ACF field definition.
	 *
	 * @param array<string, mixed> $field ACF field definition.
	 * @param array<string, mixed> $context Recursion context.
	 * @return array<string, mixed>
	 */
	private static function normalize_field_definition( array $field, array $context ) {
		$type = isset( $field['type'] ) ? (string) $field['type'] : '';
		$name = isset( $field['name'] ) ? (string) $field['name'] : '';

		$definition = array(
			'key'           => isset( $field['key'] ) ? (string) $field['key'] : '',
			'name'          => $name,
			'label'         => isset( $field['label'] ) ? trim( wp_strip_all_tags( (string) $field['label'] ) ) : '',
			'type'          => $type,
			'required'      => ! empty( $field['required'] ),
			'instructions'  => isset( $field['instructions'] ) ? trim( wp_strip_all_tags( (string) $field['instructions'] ) ) : '',
			'default_value' => isset( $field['default_value'] ) ? $field['default_value'] : null,
			'return_format' => isset( $field['return_format'] ) ? (string) $field['return_format'] : '',
			'choices'       => self::normalize_choice_map( isset( $field['choices'] ) && is_array( $field['choices'] ) ? $field['choices'] : array() ),
		);

		if ( ! empty( $field['min'] ) || 0 === $field['min'] ) {
			$definition['min'] = $field['min'];
		}

		if ( ! empty( $field['max'] ) || 0 === $field['max'] ) {
			$definition['max'] = $field['max'];
		}

		if ( in_array( $type, array( 'group', 'repeater' ), true ) ) {
			$definition['fields'] = self::normalize_child_fields(
				isset( $field['sub_fields'] ) && is_array( $field['sub_fields'] ) ? $field['sub_fields'] : array(),
				$context
			);
		}

		if ( 'flexible_content' === $type ) {
			$definition['layouts'] = self::normalize_layouts(
				isset( $field['layouts'] ) && is_array( $field['layouts'] ) ? $field['layouts'] : array(),
				$context
			);
		}

		if ( 'clone' === $type ) {
			$definition['fields'] = self::resolve_clone_fields( $field, $context );
			$definition['flatten'] = 'seamless' === (string) ( $field['display'] ?? '' ) && '' === $name;
		}

		return $definition;
	}

	/**
	 * Normalize ACF layouts.
	 *
	 * @param array<string, array<string, mixed>> $layouts Layout definitions.
	 * @param array<string, mixed>                 $context Recursion context.
	 * @return array<string, array<string, mixed>>
	 */
	private static function normalize_layouts( array $layouts, array $context ) {
		$normalized = array();

		foreach ( $layouts as $layout ) {
			if ( ! is_array( $layout ) ) {
				continue;
			}

			$layout_name = isset( $layout['name'] ) ? sanitize_key( (string) $layout['name'] ) : '';
			if ( '' === $layout_name ) {
				continue;
			}

			$normalized[ $layout_name ] = array(
				'key'       => isset( $layout['key'] ) ? (string) $layout['key'] : '',
				'name'      => $layout_name,
				'label'     => isset( $layout['label'] ) ? trim( wp_strip_all_tags( (string) $layout['label'] ) ) : ucfirst( str_replace( '_', ' ', $layout_name ) ),
				'raw_label' => isset( $layout['label'] ) ? (string) $layout['label'] : '',
				'fields'    => self::normalize_child_fields(
					isset( $layout['sub_fields'] ) && is_array( $layout['sub_fields'] ) ? $layout['sub_fields'] : array(),
					array_merge(
						$context,
						array(
							'path' => isset( $context['path'] ) ? $context['path'] . '.layouts.' . $layout_name : 'layouts.' . $layout_name,
						)
					)
				),
			);
		}

		return $normalized;
	}

	/**
	 * Normalize child fields while flattening seamless clone groups.
	 *
	 * @param array<int, array<string, mixed>> $fields Child field definitions.
	 * @param array<string, mixed>             $context Recursion context.
	 * @return array<string, array<string, mixed>>
	 */
	private static function normalize_child_fields( array $fields, array $context ) {
		$normalized = array();

		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) || ! self::should_expose_field( $field ) ) {
				continue;
			}

			$field_definition = self::normalize_field_definition( $field, $context );
			$field_type       = isset( $field_definition['type'] ) ? (string) $field_definition['type'] : '';
			$field_name       = isset( $field_definition['name'] ) ? (string) $field_definition['name'] : '';

			if ( 'clone' === $field_type && ! empty( $field_definition['flatten'] ) && ! empty( $field_definition['fields'] ) && is_array( $field_definition['fields'] ) ) {
				foreach ( $field_definition['fields'] as $clone_name => $clone_definition ) {
					$normalized[ $clone_name ] = $clone_definition;
				}
				continue;
			}

			if ( '' === $field_name ) {
				continue;
			}

			$normalized[ $field_name ] = $field_definition;
		}

		return $normalized;
	}

	/**
	 * Resolve clone targets into real field definitions.
	 *
	 * @param array<string, mixed> $field Clone field definition.
	 * @param array<string, mixed> $context Recursion context.
	 * @return array<string, array<string, mixed>>
	 */
	private static function resolve_clone_fields( array $field, array $context ) {
		$targets = isset( $field['clone'] ) && is_array( $field['clone'] ) ? $field['clone'] : array();
		$seen    = isset( $context['clones'] ) && is_array( $context['clones'] ) ? $context['clones'] : array();
		$fields  = array();

		foreach ( $targets as $target ) {
			$target = (string) $target;
			if ( '' === $target || in_array( $target, $seen, true ) ) {
				continue;
			}

			$target_fields = array();

			if ( 0 === strpos( $target, 'group_' ) && function_exists( 'acf_get_fields' ) ) {
				$target_fields = acf_get_fields( $target );
			} elseif ( 0 === strpos( $target, 'field_' ) ) {
				$target_field = acf_get_field( $target );
				if ( is_array( $target_field ) ) {
					$target_fields = isset( $target_field['sub_fields'] ) && is_array( $target_field['sub_fields'] )
						? $target_field['sub_fields']
						: array( $target_field );
				}
			}

			if ( ! is_array( $target_fields ) ) {
				continue;
			}

			foreach ( $target_fields as $target_field ) {
				if ( ! is_array( $target_field ) || ! self::should_expose_field( $target_field ) ) {
					continue;
				}

				$normalized_target = self::normalize_field_definition(
					$target_field,
					array_merge(
						$context,
						array(
							'clones' => array_merge( $seen, array( $target ) ),
						)
					)
				);

				$target_name = isset( $normalized_target['name'] ) ? (string) $normalized_target['name'] : '';

				if ( '' !== $target_name ) {
					$fields[ $target_name ] = $normalized_target;
				}
			}
		}

		return $fields;
	}

	/**
	 * Determine whether a field should be exposed in the registry.
	 *
	 * @param array<string, mixed> $field ACF field definition.
	 * @return bool
	 */
	private static function should_expose_field( array $field ) {
		$type = isset( $field['type'] ) ? (string) $field['type'] : '';

		return ! in_array( $type, array( 'tab', 'accordion', 'message' ), true );
	}

	/**
	 * Normalize ACF choices to a consistent string map.
	 *
	 * @param array<mixed, mixed> $choices Choice map.
	 * @return array<string, string>
	 */
	private static function normalize_choice_map( array $choices ) {
		$normalized = array();

		foreach ( $choices as $value => $label ) {
			if ( ! is_scalar( $value ) || ! is_scalar( $label ) ) {
				continue;
			}

			$normalized[ (string) $value ] = trim( (string) $label );
		}

		return $normalized;
	}

	/**
	 * Build a stable component key.
	 *
	 * @param string $field_name Bucket field name.
	 * @param string $layout_name Layout name.
	 * @return string
	 */
	private static function get_component_key( $field_name, $layout_name ) {
		return sanitize_key( (string) $field_name ) . ':' . sanitize_key( (string) $layout_name );
	}

	/**
	 * Get registry entry for a specific field/layout pair.
	 *
	 * @param array<string, mixed> $registry Live registry.
	 * @param string               $field_name Bucket field name.
	 * @param string               $layout_name Layout name.
	 * @return array<string, mixed>|null
	 */
	public static function get_component_by_field_and_layout( array $registry, $field_name, $layout_name ) {
		$key = self::get_component_key( $field_name, $layout_name );

		return isset( $registry['components'][ $key ] ) && is_array( $registry['components'][ $key ] )
			? $registry['components'][ $key ]
			: null;
	}

	/**
	 * Get the live reusable block registry.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function get_reusable_block_registry() {
		$registry = array();

		if ( ! function_exists( 'mrn_rbl_get_post_type_definitions' ) || ! function_exists( 'acf_get_field_groups' ) ) {
			return $registry;
		}

		$definitions = mrn_rbl_get_post_type_definitions();
		if ( ! is_array( $definitions ) ) {
			return $registry;
		}

		foreach ( $definitions as $post_type => $definition ) {
			$post_type = sanitize_key( (string) $post_type );
			if ( '' === $post_type ) {
				continue;
			}

			$field_groups = acf_get_field_groups( array( 'post_type' => $post_type ) );
			$fields       = array();

			if ( is_array( $field_groups ) ) {
				foreach ( $field_groups as $field_group ) {
					$group_key = isset( $field_group['key'] ) ? (string) $field_group['key'] : '';
					if ( '' === $group_key || ! function_exists( 'acf_get_fields' ) ) {
						continue;
					}

					$group_fields = acf_get_fields( $group_key );
					if ( ! is_array( $group_fields ) ) {
						continue;
					}

					$fields = array_merge(
						$fields,
						self::normalize_child_fields(
							$group_fields,
							array(
								'path'   => $post_type . '.' . $group_key,
								'clones' => array(),
							)
						)
					);
				}
			}

			$registry[ $post_type ] = array(
				'post_type'     => $post_type,
				'singular'      => isset( $definition['singular'] ) ? (string) $definition['singular'] : ucfirst( str_replace( '_', ' ', $post_type ) ),
				'template_slug' => function_exists( 'mrn_rbl_get_template_slug_for_post_type' ) ? mrn_rbl_get_template_slug_for_post_type( $post_type ) : '',
				'fields'        => $fields,
			);
		}

		return $registry;
	}

	/**
	 * Get live token registry entries.
	 *
	 * @return array<string, mixed>
	 */
	private static function get_token_registry() {
		$site_colors       = array();
		$graphic_elements  = array();
		$section_widths    = function_exists( 'mrn_base_stack_get_section_width_choices' ) ? mrn_base_stack_get_section_width_choices() : array();
		$link_styles       = function_exists( 'mrn_rbl_get_link_style_choices' ) ? mrn_rbl_get_link_style_choices() : array();
		$display_modes     = function_exists( 'mrn_base_stack_get_content_list_display_mode_choice_map' ) ? mrn_base_stack_get_content_list_display_mode_choice_map() : array();

		if ( function_exists( 'mrn_site_colors_get_all' ) ) {
			foreach ( mrn_site_colors_get_all() as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}

				$slug = isset( $row['slug'] ) ? sanitize_key( (string) $row['slug'] ) : '';
				if ( '' === $slug ) {
					continue;
				}

				$site_colors[ $slug ] = array(
					'slug'  => $slug,
					'name'  => isset( $row['name'] ) ? (string) $row['name'] : $slug,
					'value' => isset( $row['value'] ) ? strtoupper( (string) $row['value'] ) : '',
				);
			}
		}

		if ( function_exists( 'mrn_site_styles_get_graphic_elements' ) ) {
			foreach ( mrn_site_styles_get_graphic_elements() as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}

				$slug = isset( $row['slug'] ) ? sanitize_key( (string) $row['slug'] ) : '';
				if ( '' === $slug ) {
					continue;
				}

				$graphic_elements[ $slug ] = array(
					'slug'  => $slug,
					'name'  => isset( $row['name'] ) ? (string) $row['name'] : $slug,
					'css'   => isset( $row['css'] ) ? (string) $row['css'] : '',
					'space' => isset( $row['space'] ) ? (string) $row['space'] : '',
				);
			}
		}

		return array(
			'site_colors'       => $site_colors,
			'graphic_elements' => $graphic_elements,
			'section_widths'   => self::normalize_choice_map( is_array( $section_widths ) ? $section_widths : array() ),
			'link_styles'      => self::normalize_choice_map( is_array( $link_styles ) ? $link_styles : array() ),
			'display_modes'    => self::normalize_choice_map( is_array( $display_modes ) ? $display_modes : array() ),
		);
	}
}
