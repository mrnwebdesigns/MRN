<?php
/**
 * Builder helpers and nested layout definitions.
 *
 * @package mrn-base-stack
 */

/**
 * Build shared context passed into row template parts.
 *
 * @param array<string, mixed> $row Builder row data.
 * @param int|string           $post_id Current post ID.
 * @param int|string           $index Row index.
 * @return array<string, mixed>
 */
function mrn_base_stack_get_builder_row_context( array $row, $post_id, $index ) {
	return array(
		'row'     => $row,
		'post_id' => (int) $post_id,
		'index'   => (int) $index,
	);
}

/**
 * Clone an ACF layout tree while making every `key` unique.
 *
 * @param array<string, mixed> $value ACF layout or field tree.
 * @param string               $prefix Prefix to prepend to each ACF key.
 * @return array<string, mixed>
 */
function mrn_base_stack_clone_acf_keys_with_prefix( array $value, $prefix ) {
	foreach ( $value as $item_key => $item_value ) {
		if ( 'key' === $item_key && is_string( $item_value ) ) {
			$value[ $item_key ] = $prefix . $item_value;
			continue;
		}

		if ( 'field' === $item_key && is_string( $item_value ) && 0 === strpos( $item_value, 'field_' ) ) {
			$value[ $item_key ] = $prefix . $item_value;
			continue;
		}

		if ( is_array( $item_value ) ) {
			$value[ $item_key ] = mrn_base_stack_clone_acf_keys_with_prefix( $item_value, $prefix );
		}
	}

	return $value;
}

/**
 * Build a stable derived row index for nested builder content.
 *
 * Some row templates use the index for DOM IDs and query-string pagination.
 * Nested tab panels need their own deterministic index space so they do not
 * collide with sibling top-level rows on the same page.
 *
 * @param int $parent_index Parent row index.
 * @param int $group_index Nested group index.
 * @param int $row_index Nested row index.
 * @return int
 */
function mrn_base_stack_get_nested_builder_row_index( $parent_index, $group_index, $row_index ) {
	$parent_index = max( 0, (int) $parent_index );
	$group_index  = max( 0, (int) $group_index );
	$row_index    = max( 0, (int) $row_index );

	return ( ( $parent_index + 1 ) * 10000 ) + ( ( $group_index + 1 ) * 100 ) + $row_index;
}

/**
 * Get nested tab-panel layout names already saved in post meta.
 *
 * @param int $post_id Post ID.
 * @return array<int, string>
 */
function mrn_base_stack_get_tabbed_layout_used_nested_layout_names( $post_id ) {
	static $cache = array();

	$post_id = absint( $post_id );

	if ( $post_id < 1 ) {
		return array();
	}

	if ( isset( $cache[ $post_id ] ) ) {
		return $cache[ $post_id ];
	}

	$meta         = get_post_meta( $post_id );
	$layout_names = array();

	if ( ! is_array( $meta ) ) {
		$cache[ $post_id ] = array();
		return $cache[ $post_id ];
	}

	foreach ( $meta as $meta_key => $values ) {
		if ( ! is_string( $meta_key ) || 0 !== strpos( $meta_key, 'page_content_rows_' ) ) {
			continue;
		}

		if ( false === strpos( $meta_key, '_panel_rows_' ) || 0 !== substr_compare( $meta_key, '_acf_fc_layout', -14 ) ) {
			continue;
		}

		$raw_value = '';

		if ( is_array( $values ) && ! empty( $values ) ) {
			$raw_value = (string) $values[ count( $values ) - 1 ];
		} elseif ( is_scalar( $values ) ) {
			$raw_value = (string) $values;
		}

		$layout_name = sanitize_key( $raw_value );
		if ( '' !== $layout_name ) {
			$layout_names[] = $layout_name;
		}
	}

	$cache[ $post_id ] = array_values( array_unique( $layout_names ) );

	return $cache[ $post_id ];
}

/**
 * Check whether a flexible-content field contains complete layout sub-fields.
 *
 * @param mixed $field Field definition candidate.
 * @return bool
 */
function mrn_base_stack_builder_field_has_complete_layouts( $field ) {
	if ( ! is_array( $field ) || empty( $field['layouts'] ) || ! is_array( $field['layouts'] ) ) {
		return false;
	}

	foreach ( $field['layouts'] as $layout ) {
		if ( ! is_array( $layout ) || ! array_key_exists( 'sub_fields', $layout ) || ! is_array( $layout['sub_fields'] ) ) {
			return false;
		}
	}

	return true;
}

/**
 * Get content layout names that should be available in Hero.
 *
 * @return array<int, string>
 */
function mrn_base_stack_get_hero_builder_layout_source_names() {
	$defaults = array( 'basic', 'two_column_split' );
	$names    = apply_filters( 'mrn_base_stack_hero_builder_layout_source_names', $defaults );

	if ( ! is_array( $names ) ) {
		return $defaults;
	}

	$names = array_values(
		array_unique(
			array_filter(
				array_map( 'sanitize_key', $names )
			)
		)
	);

	return ! empty( $names ) ? $names : $defaults;
}

/**
 * Clone selected top-level Content layouts for Hero field usage.
 *
 * @return array<string, array<string, mixed>>
 */
function mrn_base_stack_get_hero_builder_layouts() {
	static $layouts_cache = null;
	static $loading       = false;

	if ( is_array( $layouts_cache ) ) {
		return $layouts_cache;
	}

	if ( $loading || ! function_exists( 'acf_get_field' ) ) {
		return array();
	}

	$field = function_exists( 'mrn_base_stack_get_builder_layout_allowlist_field_definition' )
		? mrn_base_stack_get_builder_layout_allowlist_field_definition( 'page_content_rows' )
		: array();

	$has_complete_layouts = mrn_base_stack_builder_field_has_complete_layouts( $field );

	if ( ! $has_complete_layouts ) {
		$loading = true;
		$field   = acf_get_field( 'field_mrn_page_content_rows' );
		$loading = false;
	}

	$has_complete_layouts = mrn_base_stack_builder_field_has_complete_layouts( $field );

	if ( ! $has_complete_layouts ) {
		$layouts_cache = array();
		return $layouts_cache;
	}

	$allowed_names  = mrn_base_stack_get_hero_builder_layout_source_names();
	$allowed_lookup = array_fill_keys( $allowed_names, true );
	$layouts        = array();

	foreach ( $field['layouts'] as $layout ) {
		if ( ! is_array( $layout ) ) {
			continue;
		}

		$layout_name = isset( $layout['name'] ) ? sanitize_key( (string) $layout['name'] ) : '';
		if ( '' === $layout_name || ! isset( $allowed_lookup[ $layout_name ] ) ) {
			continue;
		}

		if ( ! isset( $layout['sub_fields'] ) || ! is_array( $layout['sub_fields'] ) ) {
			$layout['sub_fields'] = array();
		}

		$cloned_layout        = mrn_base_stack_clone_acf_keys_with_prefix( $layout, 'field_mrn_hero_' );
		$cloned_key           = 'layout_mrn_hero_' . $layout_name;
		$cloned_layout['key'] = $cloned_key;
		$layouts[ $cloned_key ] = $cloned_layout;
	}

	$layouts_cache = $layouts;

	return $layouts_cache;
}

/**
 * Populate the top-level Hero flexible-content field with cloned Content layouts.
 *
 * @param array<string, mixed> $field ACF field definition.
 * @return array<string, mixed>
 */
function mrn_base_stack_populate_hero_builder_field( $field ) {
	if ( ! is_array( $field ) ) {
		return $field;
	}

	$field['layouts'] = mrn_base_stack_get_hero_builder_layouts();

	return $field;
}
add_filter( 'acf/load_field/key=field_mrn_page_hero_rows', 'mrn_base_stack_populate_hero_builder_field', 15 );
add_filter( 'acf/prepare_field/key=field_mrn_page_hero_rows', 'mrn_base_stack_populate_hero_builder_field', 15 );

/**
 * Clone the page-builder layouts for use inside tab panels.
 *
 * The cloned layouts retain their original `name` values so the existing
 * renderers and admin title filters keep working, but each ACF `key` gets a
 * new prefix so the nested flexible-content field is isolated from the top-level
 * builder field.
 *
 * @return array<string, array<string, mixed>>
 */
function mrn_base_stack_get_tabbed_layout_nested_layouts() {
	static $layouts_cache = array();
	static $loading       = false;

	$post_id          = function_exists( 'mrn_base_stack_get_builder_layout_allowlist_post_id' ) ? mrn_base_stack_get_builder_layout_allowlist_post_id() : 0;
	$allowlist_active = $post_id > 0
		&& function_exists( 'mrn_base_stack_is_builder_layout_allowlist_context' )
		&& mrn_base_stack_is_builder_layout_allowlist_context( $post_id );
	$cache_key        = $allowlist_active ? 'post_' . $post_id : 'global';

	if ( isset( $layouts_cache[ $cache_key ] ) ) {
		return $layouts_cache[ $cache_key ];
	}

	if ( $loading || ! function_exists( 'acf_get_field' ) ) {
		return array();
	}

	$field = function_exists( 'mrn_base_stack_get_builder_layout_allowlist_field_definition' )
		? mrn_base_stack_get_builder_layout_allowlist_field_definition( 'page_content_rows' )
		: array();

	$has_complete_layouts = mrn_base_stack_builder_field_has_complete_layouts( $field );

	if ( ! $has_complete_layouts ) {
		$loading = true;
		$field   = acf_get_field( 'field_mrn_page_content_rows' );
		$loading = false;
	}

	$has_complete_layouts = mrn_base_stack_builder_field_has_complete_layouts( $field );

	if ( ! $has_complete_layouts ) {
		$layouts_cache[ $cache_key ] = array();
		return $layouts_cache[ $cache_key ];
	}

	$allowed_names = array();
	if (
		$allowlist_active
		&& function_exists( 'mrn_base_stack_get_builder_layout_allowlist_catalog_from_field' )
		&& function_exists( 'mrn_base_stack_get_builder_layout_allowlist_effective_names' )
	) {
		$catalog = mrn_base_stack_get_builder_layout_allowlist_catalog_from_field( $field );

		if ( ! empty( $catalog ) ) {
			$allowed_names = mrn_base_stack_get_builder_layout_allowlist_effective_names( $post_id, 'page_content_rows', $catalog );
		}

		$allowed_names = array_values(
			array_unique(
				array_merge(
					$allowed_names,
					mrn_base_stack_get_tabbed_layout_used_nested_layout_names( $post_id )
				)
			)
		);
	}

	$allowed_names  = array_values(
		array_diff(
			array_values(
				array_unique(
					array_filter(
						array_map( 'sanitize_key', $allowed_names )
					)
				)
			),
			array( 'tabbed_layout' )
		)
	);
	$allowed_lookup = ! empty( $allowed_names ) ? array_fill_keys( $allowed_names, true ) : array();
	$layouts        = array();

	foreach ( $field['layouts'] as $layout_key => $layout ) {
		if ( ! is_array( $layout ) ) {
			continue;
		}

		if ( ! isset( $layout['sub_fields'] ) || ! is_array( $layout['sub_fields'] ) ) {
			$layout['sub_fields'] = array();
		}

		$layout_name = isset( $layout['name'] ) ? sanitize_key( (string) $layout['name'] ) : '';
		if ( '' === $layout_name || 'tabbed_layout' === $layout_name ) {
			continue;
		}

		if ( ! empty( $allowed_lookup ) && ! isset( $allowed_lookup[ $layout_name ] ) ) {
			continue;
		}

		$cloned_layout        = mrn_base_stack_clone_acf_keys_with_prefix( $layout, 'field_mrn_tabbed_panel_' );
		$cloned_key           = 'layout_mrn_tabbed_panel_' . $layout_name;
		$cloned_layout['key'] = $cloned_key;

		$layouts[ $cloned_key ] = $cloned_layout;
	}

	$layouts_cache[ $cache_key ] = $layouts;

	return $layouts_cache[ $cache_key ];
}

/**
 * Populate the nested tab-panel flexible-content field with builder layouts.
 *
 * @param array<string, mixed> $field ACF field definition.
 * @return array<string, mixed>
 */
function mrn_base_stack_populate_tabbed_layout_panel_field( $field ) {
	if ( ! is_array( $field ) ) {
		return $field;
	}

	$field['layouts'] = mrn_base_stack_get_tabbed_layout_nested_layouts();

	return $field;
}
add_filter( 'acf/load_field/key=field_mrn_tabbed_layout_panel_rows', 'mrn_base_stack_populate_tabbed_layout_panel_field', 20 );
add_filter( 'acf/prepare_field/key=field_mrn_tabbed_layout_panel_rows', 'mrn_base_stack_populate_tabbed_layout_panel_field', 20 );

/**
 * Shared section-width choices for theme-owned builder layouts.
 *
 * @return array<string, string>
 */
function mrn_base_stack_get_section_width_choices() {
	return array(
		'content'    => 'Content',
		'wide'       => 'Wide',
		'full-width' => 'Full Width',
	);
}

/**
 * Shared post-type choices for query-driven builder layouts.
 *
 * @return array<string, string>
 */
function mrn_base_stack_get_content_list_post_type_choices() {
	static $cache     = null;
	static $resolving = false;

	if ( is_array( $cache ) ) {
		return $cache;
	}

	if ( $resolving ) {
		return array( 'post' => 'Posts' );
	}

	$resolving = true;

	try {
		$post_types = get_post_types(
			array(
				'public'  => true,
				'show_ui' => true,
			),
			'objects'
		);
		$choices    = array();
		$excluded   = array(
			'attachment',
			'wp_block',
			'wp_font_face',
			'wp_font_family',
			'wp_global_styles',
			'wp_navigation',
			'wp_template',
			'wp_template_part',
			'acf-field',
			'acf-field-group',
		);

		foreach ( $post_types as $post_type => $post_type_object ) {
			if ( ! $post_type_object instanceof WP_Post_Type ) {
				continue;
			}

			if ( in_array( $post_type, $excluded, true ) ) {
				continue;
			}

			$label = isset( $post_type_object->labels->name ) ? trim( (string) $post_type_object->labels->name ) : '';
			if ( '' === $label ) {
				$label = ucfirst( str_replace( array( '-', '_' ), ' ', $post_type ) );
			}

			$choices[ $post_type ] = $label;
		}

		if ( empty( $choices['post'] ) ) {
			$choices = array_merge( array( 'post' => 'Posts' ), $choices );
		}

		$cache = $choices;

		return $cache;
	} finally {
		$resolving = false;
	}
}

/**
 * Load live post-type choices into the Content Lists builder field.
 *
 * This keeps the row selector aligned with the currently registered public
 * content types instead of only the choices present when the field group was
 * registered.
 *
 * @param array<string, mixed> $field ACF field definition.
 * @return array<string, mixed>
 */
function mrn_base_stack_load_content_list_post_type_field_choices( $field ) {
	if ( ! is_array( $field ) ) {
		return $field;
	}

	$field['choices'] = mrn_base_stack_get_content_list_post_type_choices();

	return $field;
}
add_filter( 'acf/load_field/key=field_mrn_content_lists_post_type', 'mrn_base_stack_load_content_list_post_type_field_choices' );
add_filter( 'acf/load_field/name=list_post_type', 'mrn_base_stack_load_content_list_post_type_field_choices' );
add_filter( 'acf/prepare_field/key=field_mrn_content_lists_post_type', 'mrn_base_stack_load_content_list_post_type_field_choices' );
add_filter( 'acf/prepare_field/name=list_post_type', 'mrn_base_stack_load_content_list_post_type_field_choices' );

/**
 * Determine whether a builder value contains meaningful content.
 *
 * @param mixed $value Candidate value.
 * @return bool
 */
function mrn_base_stack_builder_value_has_content( $value ) {
	if ( is_array( $value ) ) {
		foreach ( $value as $nested_key => $nested_value ) {
			if ( is_string( $nested_key ) ) {
				$normalized_key = sanitize_key( $nested_key );
				if (
					'' !== $normalized_key
					&& (
						0 === strpos( $normalized_key, '_' )
						|| 0 === strpos( $normalized_key, 'field_' )
						|| 'acfcloneindex' === $normalized_key
						|| 'acf_fc_layout' === $normalized_key
					)
				) {
					continue;
				}
			}

			if ( mrn_base_stack_builder_value_has_content( $nested_value ) ) {
				return true;
			}
		}

		return false;
	}

	if ( is_object( $value ) ) {
		return mrn_base_stack_builder_value_has_content( (array) $value );
	}

	if ( is_string( $value ) ) {
		return '' !== trim( $value );
	}

	if ( is_bool( $value ) ) {
		return $value;
	}

	if ( is_numeric( $value ) ) {
		return 0.0 !== (float) $value;
	}

	return ! empty( $value );
}

/**
 * Determine whether a showcase image value references media.
 *
 * @param mixed $image Candidate image value.
 * @return bool
 */
function mrn_base_stack_showcase_image_has_content( $image ) {
	if ( is_numeric( $image ) ) {
		return absint( $image ) > 0;
	}

	if ( is_string( $image ) ) {
		return '' !== trim( $image );
	}

	if ( ! is_array( $image ) ) {
		return false;
	}

	if ( isset( $image['ID'] ) && absint( $image['ID'] ) > 0 ) {
		return true;
	}

	if ( isset( $image['id'] ) && absint( $image['id'] ) > 0 ) {
		return true;
	}

	if ( isset( $image['url'] ) && is_string( $image['url'] ) && '' !== trim( $image['url'] ) ) {
		return true;
	}

	return false;
}

/**
 * Determine whether a showcase link payload contains an actionable link target.
 *
 * @param mixed $value Candidate link payload.
 * @return bool
 */
function mrn_base_stack_showcase_link_value_has_content( $value ) {
	if ( is_array( $value ) ) {
		$normalized_link = array();
		foreach ( $value as $link_key => $link_value ) {
			$normalized_key = is_string( $link_key ) ? sanitize_key( $link_key ) : '';
			if (
				'' !== $normalized_key
				&& (
					0 === strpos( $normalized_key, '_' )
					|| 0 === strpos( $normalized_key, 'field_' )
					|| 'acfcloneindex' === $normalized_key
					|| 'acf_fc_layout' === $normalized_key
				)
			) {
				continue;
			}

			$normalized_link[ $link_key ] = $link_value;
		}

		$has_link_shape = isset( $normalized_link['url'] ) || isset( $normalized_link['title'] ) || isset( $normalized_link['ID'] ) || isset( $normalized_link['id'] );

		if ( $has_link_shape ) {
			if ( isset( $normalized_link['url'] ) && is_string( $normalized_link['url'] ) && '' !== trim( $normalized_link['url'] ) ) {
				return true;
			}

			if ( isset( $normalized_link['title'] ) && is_string( $normalized_link['title'] ) && '' !== trim( $normalized_link['title'] ) ) {
				return true;
			}

			if ( isset( $normalized_link['ID'] ) && absint( $normalized_link['ID'] ) > 0 ) {
				return true;
			}

			if ( isset( $normalized_link['id'] ) && absint( $normalized_link['id'] ) > 0 ) {
				return true;
			}

			return false;
		}

		foreach ( $normalized_link as $nested_value ) {
			if ( mrn_base_stack_showcase_link_value_has_content( $nested_value ) ) {
				return true;
			}
		}

		return false;
	}

	if ( is_string( $value ) ) {
		return '' !== trim( $value );
	}

	if ( is_numeric( $value ) ) {
		return absint( $value ) > 0;
	}

	return ! empty( $value );
}

/**
 * Determine whether a showcase repeater row has meaningful editor content.
 *
 * @param mixed $row Candidate repeater row.
 * @return bool
 */
function mrn_base_stack_showcase_item_row_has_content( $row ) {
	if ( ! is_array( $row ) ) {
		return mrn_base_stack_builder_value_has_content( $row );
	}

	if ( isset( $row['image'] ) && mrn_base_stack_showcase_image_has_content( $row['image'] ) ) {
		return true;
	}

	if ( isset( $row['links'] ) && mrn_base_stack_showcase_link_value_has_content( $row['links'] ) ) {
		return true;
	}

	if ( isset( $row['link'] ) && mrn_base_stack_showcase_link_value_has_content( $row['link'] ) ) {
		return true;
	}

	if ( isset( $row['background_color'] ) && is_string( $row['background_color'] ) && '' !== trim( $row['background_color'] ) ) {
		return true;
	}

	if ( ! empty( $row['enable_row_effects'] ) ) {
		return true;
	}

	$ignored_keys = array(
		'acfcloneindex',
		'image',
		'links',
		'link',
		'background_color',
		'enable_row_effects',
	);

	foreach ( $row as $key => $value ) {
		$key = is_string( $key ) ? sanitize_key( $key ) : '';
		if ( '' !== $key && ( 0 === strpos( $key, '_' ) || 0 === strpos( $key, 'field_' ) || 'acf_fc_layout' === $key ) ) {
			continue;
		}

		if ( in_array( $key, $ignored_keys, true ) ) {
			continue;
		}

		if ( mrn_base_stack_builder_value_has_content( $value ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Prevent empty showcase repeater placeholders from bloating postmeta on save.
 *
 * @param mixed                $value Submitted ACF value.
 * @param int|string           $_post_id ACF object identifier.
 * @param array<string, mixed> $_field ACF field definition.
 * @return mixed
 */
function mrn_base_stack_prune_empty_showcase_items_on_save( $value, $_post_id, array $_field ) {
	unset( $_post_id, $_field );

	if ( ! is_array( $value ) ) {
		return $value;
	}

	$filtered_rows = array();

	foreach ( $value as $row_index => $row ) {
		if ( 'acfcloneindex' === ( is_string( $row_index ) ? sanitize_key( $row_index ) : '' ) ) {
			continue;
		}

		if ( ! mrn_base_stack_showcase_item_row_has_content( $row ) ) {
			continue;
		}

		$filtered_rows[] = $row;
	}

	return array_values( $filtered_rows );
}
add_filter( 'acf/update_value/name=showcase_items', 'mrn_base_stack_prune_empty_showcase_items_on_save', 20, 3 );
add_filter( 'acf/update_value/key=field_mrn_showcase_items', 'mrn_base_stack_prune_empty_showcase_items_on_save', 20, 3 );

/**
 * Remove fully-empty showcase repeater payloads that ACF may persist on save.
 *
 * Some classic-editor save flows can still store placeholder rows (for example
 * minimum-row enforcement and internal field-key transport) even after
 * `acf/update_value` filtering. This post-save guard inspects each top-level
 * `showcase_items` repeater and resets it to zero rows when every persisted row
 * is empty.
 *
 * @param int|string $post_id ACF object identifier.
 * @return void
 */
function mrn_base_stack_cleanup_empty_showcase_repeater_meta_on_save( $post_id ) {
	$post_id = is_numeric( $post_id ) ? absint( $post_id ) : 0;
	if ( $post_id <= 0 ) {
		return;
	}

	global $wpdb;
	if ( ! isset( $wpdb ) || ! ( $wpdb instanceof wpdb ) ) {
		return;
	}

	$count_key_rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Targeted post-save cleanup query over dynamic repeater keys.
		$wpdb->prepare(
			"SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key REGEXP %s",
			$post_id,
			'^page_content_rows_[0-9]+_showcase_items$'
		),
		ARRAY_A
	);

	if ( ! is_array( $count_key_rows ) || empty( $count_key_rows ) ) {
		return;
	}

	foreach ( $count_key_rows as $count_row ) {
		if ( ! is_array( $count_row ) ) {
			continue;
		}

		$count_key = isset( $count_row['meta_key'] ) ? sanitize_key( (string) $count_row['meta_key'] ) : '';
		$row_count = isset( $count_row['meta_value'] ) ? absint( $count_row['meta_value'] ) : 0;

		if ( '' === $count_key || $row_count < 1 ) {
			continue;
		}

			$row_value_rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Targeted post-save cleanup query over dynamic child meta rows.
				$wpdb->prepare(
					"SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key LIKE %s",
					$post_id,
					$count_key . '\\_%'
				),
				ARRAY_A
			);

		if ( ! is_array( $row_value_rows ) || empty( $row_value_rows ) ) {
			continue;
		}

		$rows_by_index = array();

		foreach ( $row_value_rows as $value_row ) {
			if ( ! is_array( $value_row ) ) {
				continue;
			}

			$meta_key = isset( $value_row['meta_key'] ) ? (string) $value_row['meta_key'] : '';
			if ( '' === $meta_key ) {
				continue;
			}

			if ( 1 !== preg_match( '/^' . preg_quote( $count_key, '/' ) . '_([0-9]+)_([a-z0-9_]+)$/', $meta_key, $matches ) ) {
				continue;
			}

			$row_index = absint( $matches[1] );
			$field_key = sanitize_key( $matches[2] );
			if ( '' === $field_key ) {
				continue;
			}

			$meta_value = isset( $value_row['meta_value'] ) ? $value_row['meta_value'] : '';
			if ( 'links' === $field_key ) {
				$meta_value = maybe_unserialize( $meta_value );
			}

			if ( ! isset( $rows_by_index[ $row_index ] ) || ! is_array( $rows_by_index[ $row_index ] ) ) {
				$rows_by_index[ $row_index ] = array();
			}

			$rows_by_index[ $row_index ][ $field_key ] = $meta_value;
		}

		if ( empty( $rows_by_index ) ) {
			continue;
		}

		$all_rows_empty = true;

		foreach ( $rows_by_index as $row_data ) {
			if ( mrn_base_stack_showcase_item_row_has_content( $row_data ) ) {
				$all_rows_empty = false;
				break;
			}
		}

		if ( ! $all_rows_empty ) {
			continue;
		}

		$child_pattern         = '^' . preg_quote( $count_key, '/' ) . '_[0-9]+_';
		$child_reference_regex = '^_' . preg_quote( $count_key, '/' ) . '_[0-9]+_';

			$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Targeted delete of orphaned/empty showcase child meta rows.
				$wpdb->prepare(
					"DELETE FROM {$wpdb->postmeta} WHERE post_id = %d AND (meta_key REGEXP %s OR meta_key REGEXP %s)",
					$post_id,
					$child_pattern,
					$child_reference_regex
				)
			);

		update_post_meta( $post_id, $count_key, '0' );
	}
}
add_action( 'acf/save_post', 'mrn_base_stack_cleanup_empty_showcase_repeater_meta_on_save', 30 );

/**
 * Shared list-style choices for query-driven builder layouts.
 *
 * @return array<string, string>
 */
function mrn_base_stack_get_content_list_style_choices() {
	return array(
		'unordered' => 'Unordered List',
		'ordered'   => 'Ordered List',
	);
}

/**
 * Shared display-mode choices for query-driven builder layouts.
 *
 * @return array<string, string>
 */
function mrn_base_stack_get_content_list_display_mode_choices() {
	$choices = array(
		'' => 'Use Row Settings',
	);

	foreach ( mrn_base_stack_get_content_list_display_mode_choice_map() as $post_type => $post_type_choices ) {
		if ( ! is_array( $post_type_choices ) ) {
			continue;
		}

		foreach ( $post_type_choices as $mode => $label ) {
			$label = trim( (string) $label );
			if ( '' === $label || isset( $choices[ $mode ] ) ) {
				continue;
			}

			$choices[ $mode ] = $label;
		}
	}

	return $choices;
}

/**
 * Load live display-mode choices into the Content Lists builder field.
 *
 * The field group registers a baseline set of choices, but the actual options
 * need to reflect client-managed Display Modes from Config Helper each time the
 * builder form loads.
 *
 * @param array<string, mixed> $field ACF field definition.
 * @return array<string, mixed>
 */
function mrn_base_stack_load_content_list_display_mode_field_choices( $field ) {
	if ( ! is_array( $field ) ) {
		return $field;
	}

	$field['choices']    = mrn_base_stack_get_content_list_display_mode_choices();
	$field['allow_null'] = 1;
	$field['ui']         = 0;

	return $field;
}
add_filter( 'acf/load_field/key=field_mrn_content_lists_display_mode', 'mrn_base_stack_load_content_list_display_mode_field_choices' );
add_filter( 'acf/load_field/name=display_mode', 'mrn_base_stack_load_content_list_display_mode_field_choices' );
add_filter( 'acf/prepare_field/key=field_mrn_content_lists_display_mode', 'mrn_base_stack_load_content_list_display_mode_field_choices' );
add_filter( 'acf/prepare_field/name=display_mode', 'mrn_base_stack_load_content_list_display_mode_field_choices' );

/**
 * Recursively normalize select defaults on a full ACF field tree.
 *
 * @param mixed $field Field or layout field definition.
 * @return mixed
 */
function mrn_base_stack_normalize_select_defaults_in_field_tree( $field ) {
	if ( ! is_array( $field ) ) {
		return $field;
	}

	// ACF core validators assume this key exists across field types.
	if ( ! array_key_exists( 'required', $field ) ) {
		$field['required'] = 0;
	}

	$field_type = isset( $field['type'] ) ? sanitize_key( (string) $field['type'] ) : '';
	if ( 'select' === $field_type ) {
		if ( ! array_key_exists( 'multiple', $field ) ) {
			$field['multiple'] = 0;
		}

		$return_format = isset( $field['return_format'] ) ? sanitize_key( (string) $field['return_format'] ) : '';
		if ( '' === $return_format || ! in_array( $return_format, array( 'value', 'label', 'array' ), true ) ) {
			$field['return_format'] = 'value';
		}
	}

	if ( isset( $field['sub_fields'] ) && is_array( $field['sub_fields'] ) ) {
		foreach ( $field['sub_fields'] as $index => $sub_field ) {
			$field['sub_fields'][ $index ] = mrn_base_stack_normalize_select_defaults_in_field_tree( $sub_field );
		}
	}

	if ( isset( $field['fields'] ) && is_array( $field['fields'] ) ) {
		foreach ( $field['fields'] as $index => $child_field ) {
			$field['fields'][ $index ] = mrn_base_stack_normalize_select_defaults_in_field_tree( $child_field );
		}
	}

	if ( isset( $field['layouts'] ) && is_array( $field['layouts'] ) ) {
		foreach ( $field['layouts'] as $layout_key => $layout ) {
			if ( ! is_array( $layout ) ) {
				continue;
			}

			if ( isset( $layout['sub_fields'] ) && is_array( $layout['sub_fields'] ) ) {
				foreach ( $layout['sub_fields'] as $sub_index => $sub_field ) {
					$layout['sub_fields'][ $sub_index ] = mrn_base_stack_normalize_select_defaults_in_field_tree( $sub_field );
				}
			}

			$field['layouts'][ $layout_key ] = $layout;
		}
	}

	return $field;
}
add_filter( 'acf/validate_field', 'mrn_base_stack_normalize_select_defaults_in_field_tree', 20 );
add_filter( 'acf/load_field', 'mrn_base_stack_normalize_select_defaults_in_field_tree', 20 );
add_filter( 'acf/prepare_field', 'mrn_base_stack_normalize_select_defaults_in_field_tree', 20 );

/**
 * Ensure select fields always include required core defaults.
 *
 * @param array<string, mixed> $field ACF field definition.
 * @return array<string, mixed>
 */
function mrn_base_stack_normalize_select_field_defaults( $field ) {
	if ( ! is_array( $field ) ) {
		return $field;
	}

	$field = mrn_base_stack_normalize_select_defaults_in_field_tree( $field );

	return $field;
}
add_filter( 'acf/validate_field/type=select', 'mrn_base_stack_normalize_select_field_defaults', 20 );
add_filter( 'acf/load_field/type=select', 'mrn_base_stack_normalize_select_field_defaults', 20 );
add_filter( 'acf/prepare_field/type=select', 'mrn_base_stack_normalize_select_field_defaults', 20 );

/**
 * Robustly normalize dynamic choices for Content Lists select subfields.
 *
 * Some builder contexts can bypass the narrower ACF key/name hooks depending on
 * how the flexible-content row is prepared. This catches the rendered field
 * instance itself and reapplies the dynamic choice sources when the field is a
 * Content Lists subfield.
 *
 * @param array<string, mixed> $field ACF field definition.
 * @return array<string, mixed>
 */
function mrn_base_stack_prepare_dynamic_content_list_select_fields( $field ) {
	if ( ! is_array( $field ) ) {
		return $field;
	}

	$field_type    = isset( $field['type'] ) ? sanitize_key( (string) $field['type'] ) : '';
	$field_name    = isset( $field['name'] ) ? sanitize_key( (string) $field['name'] ) : '';
	$field_origin  = isset( $field['_name'] ) ? sanitize_key( (string) $field['_name'] ) : $field_name;
	$parent_layout = isset( $field['parent_layout'] ) ? sanitize_key( (string) $field['parent_layout'] ) : '';

	if ( 'select' !== $field_type ) {
		return $field;
	}

	$field = mrn_base_stack_normalize_select_field_defaults( $field );

	$is_content_list_layout = false !== strpos( $parent_layout, 'content_lists' );

	if ( $is_content_list_layout && in_array( $field_origin, array( 'list_post_type', 'display_mode' ), true ) ) {
		if ( 'list_post_type' === $field_origin ) {
			$field['choices'] = mrn_base_stack_get_content_list_post_type_choices();
		}

		if ( 'display_mode' === $field_origin ) {
			$field['choices']    = mrn_base_stack_get_content_list_display_mode_choices();
			$field['allow_null'] = 1;
			$field['ui']         = 0;
		}
	}

	return $field;
}
add_filter( 'acf/load_field', 'mrn_base_stack_prepare_dynamic_content_list_select_fields', 20 );
add_filter( 'acf/prepare_field', 'mrn_base_stack_prepare_dynamic_content_list_select_fields', 20 );

/**
 * Get display-mode choices for a specific content-list post type.
 *
 * @param string $post_type Post type slug.
 * @return array<string, array<string, mixed>>
 */
function mrn_base_stack_get_content_list_display_modes_for_post_type( $post_type = 'post' ) {
	$post_type = sanitize_key( (string) $post_type );
	$modes     = mrn_base_stack_get_content_list_display_modes();
	$filtered  = array();

	foreach ( $modes as $mode_key => $mode_config ) {
		if ( ! is_array( $mode_config ) ) {
			continue;
		}

		$entity_type    = isset( $mode_config['entity_type'] ) ? sanitize_key( (string) $mode_config['entity_type'] ) : 'post_type';
		$entity_subtype = isset( $mode_config['entity_subtype'] ) ? sanitize_key( (string) $mode_config['entity_subtype'] ) : 'post';

		if ( 'post_type' !== $entity_type || $entity_subtype !== $post_type ) {
			continue;
		}

		$filtered[ $mode_key ] = $mode_config;
	}

	if ( empty( $filtered ) && 'post' !== $post_type ) {
		return mrn_base_stack_get_content_list_display_modes_for_post_type( 'post' );
	}

	return $filtered;
}

/**
 * Get display-mode labels grouped by post type for builder-admin filtering.
 *
 * @return array<string, array<string, string>>
 */
function mrn_base_stack_get_content_list_display_mode_choice_map() {
	$map = array();

	foreach ( mrn_base_stack_get_content_list_post_type_choices() as $post_type => $label ) {
		$choices = array();

		foreach ( mrn_base_stack_get_content_list_display_modes_for_post_type( $post_type ) as $mode_key => $mode_config ) {
			if ( ! is_array( $mode_config ) ) {
				continue;
			}

			$mode_label = isset( $mode_config['label'] ) ? trim( (string) $mode_config['label'] ) : '';
			if ( '' === $mode_label ) {
				continue;
			}

			$choices[ $mode_key ] = $mode_label;
		}

		$map[ $post_type ] = $choices;
	}

	return $map;
}

/**
 * Shared display-mode registry for query-driven builder layouts.
 *
 * This intentionally starts small, but the contract is filterable so future
 * list-capable layouts can reuse the same mode vocabulary without rewriting the
 * builder field schema.
 *
 * @return array<string, array<string, mixed>>
 */
function mrn_base_stack_get_content_list_display_modes() {
	$modes = array(
		'standard'   => array(
			'entity_type'      => 'post_type',
			'entity_subtype'   => 'post',
			'label'            => 'Standard',
			'fields'           => array( 'title', 'featured_image', 'publish_date', 'excerpt', 'read_more' ),
			'allows_image'     => true,
			'allows_date'      => true,
			'allows_excerpt'   => true,
			'allows_read_more' => true,
		),
		'title_only' => array(
			'entity_type'      => 'post_type',
			'entity_subtype'   => 'post',
			'label'            => 'Title Only',
			'fields'           => array( 'title' ),
			'allows_image'     => false,
			'allows_date'      => false,
			'allows_excerpt'   => false,
			'allows_read_more' => false,
		),
	);

	if ( function_exists( 'mrn_config_helper_get_display_modes' ) ) {
		$saved_modes = mrn_config_helper_get_display_modes();

		if ( is_array( $saved_modes ) ) {
			foreach ( $saved_modes as $saved_mode ) {
				if ( ! is_array( $saved_mode ) ) {
					continue;
				}

				$mode_key       = isset( $saved_mode['mode_key'] ) ? sanitize_key( (string) $saved_mode['mode_key'] ) : '';
				$label          = isset( $saved_mode['label'] ) ? trim( (string) $saved_mode['label'] ) : '';
				$entity_type    = isset( $saved_mode['entity_type'] ) ? sanitize_key( (string) $saved_mode['entity_type'] ) : 'post_type';
				$entity_subtype = isset( $saved_mode['entity_subtype'] ) ? sanitize_key( (string) $saved_mode['entity_subtype'] ) : 'post';
				$fields         = isset( $saved_mode['fields'] ) && is_array( $saved_mode['fields'] ) ? array_values( array_unique( array_map( 'sanitize_key', $saved_mode['fields'] ) ) ) : array();

				if ( '' === $mode_key || '' === $label ) {
					continue;
				}

				$modes[ $mode_key ] = array(
					'entity_type'      => $entity_type,
					'entity_subtype'   => $entity_subtype,
					'label'            => $label,
					'fields'           => $fields,
					'allows_image'     => in_array( 'featured_image', $fields, true ) || in_array( 'image', $fields, true ),
					'allows_date'      => in_array( 'publish_date', $fields, true ),
					'allows_excerpt'   => in_array( 'excerpt', $fields, true ) || in_array( 'body', $fields, true ),
					'allows_read_more' => in_array( 'read_more', $fields, true ) || in_array( 'link', $fields, true ),
				);
			}
		}
	}

	return apply_filters( 'mrn_base_stack_content_list_display_modes', $modes );
}

/**
 * Normalize a content-list display mode to a supported key.
 *
 * @param string $mode Candidate display-mode key.
 * @return string
 */
function mrn_base_stack_normalize_content_list_display_mode( $mode ) {
	$mode  = sanitize_key( (string) $mode );
	$modes = mrn_base_stack_get_content_list_display_modes();

	if ( '' === $mode ) {
		return '';
	}

	if ( isset( $modes[ $mode ] ) ) {
		return $mode;
	}

	return '';
}

/**
 * Get the configuration for one content-list display mode.
 *
 * @param string $mode Display-mode key.
 * @return array<string, mixed>
 */
function mrn_base_stack_get_content_list_display_mode_config( $mode ) {
	$modes = mrn_base_stack_get_content_list_display_modes();
	$mode  = mrn_base_stack_normalize_content_list_display_mode( $mode );

	return isset( $modes[ $mode ] ) && is_array( $modes[ $mode ] ) ? $modes[ $mode ] : array();
}

/**
 * Build the legacy row-settings display contract for Content Lists.
 *
 * @param array<string, mixed> $args Render arguments.
 * @return array<string, mixed>
 */
function mrn_base_stack_get_content_list_legacy_mode_config( array $args = array() ) {
	$fields = array( 'title' );

	if ( ! empty( $args['show_featured_image'] ) ) {
		$fields[] = 'featured_image';
	}

	if ( ! empty( $args['show_publish_date'] ) ) {
		$fields[] = 'publish_date';
	}

	if ( ! empty( $args['show_excerpt'] ) ) {
		$fields[] = 'excerpt';
	}

	if ( ! empty( $args['show_read_more'] ) ) {
		$fields[] = 'read_more';
	}

	return array(
		'label'            => 'Row Settings',
		'fields'           => $fields,
		'allows_image'     => ! empty( $args['show_featured_image'] ),
		'allows_date'      => ! empty( $args['show_publish_date'] ),
		'allows_excerpt'   => ! empty( $args['show_excerpt'] ),
		'allows_read_more' => ! empty( $args['show_read_more'] ),
	);
}

/**
 * Render one query result item for the Content Lists layout.
 *
 * @param WP_Post              $item_post Post to render.
 * @param array<string, mixed> $args Render arguments.
 * @return string
 */
function mrn_base_stack_render_content_list_item( WP_Post $item_post, array $args = array() ) {
	$display_mode      = mrn_base_stack_normalize_content_list_display_mode( $args['display_mode'] ?? '' );
	$mode_config       = '' !== $display_mode ? mrn_base_stack_get_content_list_display_mode_config( $display_mode ) : mrn_base_stack_get_content_list_legacy_mode_config( $args );
	$permalink         = get_permalink( $item_post );
	$item_title        = get_the_title( $item_post );
	$uses_row_settings = '' === $display_mode;
	$show_date         = ( ! $uses_row_settings || ! empty( $args['show_publish_date'] ) ) && ! empty( $mode_config['allows_date'] );
	$show_excerpt      = ( ! $uses_row_settings || ! empty( $args['show_excerpt'] ) ) && ! empty( $mode_config['allows_excerpt'] );
	$show_read_more    = ( ! $uses_row_settings || ! empty( $args['show_read_more'] ) ) && ! empty( $mode_config['allows_read_more'] ) && '' !== $permalink;
	$show_image        = ( ! $uses_row_settings || ! empty( $args['show_featured_image'] ) ) && ! empty( $mode_config['allows_image'] ) && has_post_thumbnail( $item_post );
	$excerpt_length    = max( 5, absint( $args['excerpt_length'] ?? 24 ) );
	$read_more_label   = isset( $args['read_more_label'] ) ? trim( (string) $args['read_more_label'] ) : 'Read More';
	$item_excerpt      = $show_excerpt && function_exists( 'mrn_base_stack_get_content_list_excerpt' ) ? mrn_base_stack_get_content_list_excerpt( $item_post, $excerpt_length ) : '';
	$fields            = isset( $mode_config['fields'] ) && is_array( $mode_config['fields'] ) ? array_values( array_unique( array_map( 'sanitize_key', $mode_config['fields'] ) ) ) : array();
	$variant           = array( 'title' ) === $fields ? 'title_only' : 'card';
	$image_first       = ! empty( $fields ) && 'featured_image' === $fields[0];
	$item_classes      = array(
		'mrn-content-list-row__item',
		'mrn-ui__item',
		'mrn-content-list-row__item--display-' . ( '' !== $display_mode ? $display_mode : 'row-settings' ),
		'mrn-content-list-row__item--variant-' . $variant,
	);

	if ( $show_image ) {
		$item_classes[] = 'mrn-content-list-row__item--has-image';
		if ( $image_first ) {
			$item_classes[] = 'mrn-content-list-row__item--image-leading';
		}
	}

	ob_start();
	?>
	<li class="<?php echo esc_attr( implode( ' ', $item_classes ) ); ?>">
		<?php if ( 'title_only' === $variant ) : ?>
			<div class="mrn-content-list-row__body mrn-ui__body">
				<div class="mrn-content-list-row__head mrn-ui__head">
					<span class="mrn-content-list-row__title mrn-content-list-row__title--only mrn-ui__heading">
						<?php if ( '' !== $permalink ) : ?>
							<a class="mrn-ui__link" href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( $item_title ); ?></a>
						<?php else : ?>
							<?php echo esc_html( $item_title ); ?>
						<?php endif; ?>
					</span>
				</div>
			</div>
		<?php else : ?>
				<article class="mrn-content-list-row__card">
					<div class="mrn-content-list-row__body mrn-ui__body">
						<?php $head_open = false; ?>
						<?php foreach ( $fields as $field_key ) : ?>
							<?php
							$is_head_field = (
								( 'publish_date' === $field_key && $show_date ) ||
								( 'title' === $field_key && '' !== $item_title )
							);
							?>
							<?php if ( $is_head_field && ! $head_open ) : ?>
								<div class="mrn-content-list-row__head mrn-ui__head">
								<?php $head_open = true; ?>
							<?php elseif ( ! $is_head_field && $head_open ) : ?>
								</div>
								<?php $head_open = false; ?>
							<?php endif; ?>
							<?php if ( 'featured_image' === $field_key && $show_image && '' !== $permalink ) : ?>
								<a class="mrn-content-list-row__media mrn-ui__media mrn-ui__link" href="<?php echo esc_url( $permalink ); ?>">
								<?php echo get_the_post_thumbnail( $item_post, 'medium_large' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</a>
						<?php elseif ( 'featured_image' === $field_key && $show_image ) : ?>
								<div class="mrn-content-list-row__media mrn-ui__media">
								<?php echo get_the_post_thumbnail( $item_post, 'medium_large' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</div>
						<?php elseif ( 'publish_date' === $field_key && $show_date ) : ?>
							<p class="mrn-content-list-row__meta"><?php echo esc_html( get_the_date( '', $item_post ) ); ?></p>
						<?php elseif ( 'title' === $field_key && '' !== $item_title ) : ?>
								<h3 class="mrn-content-list-row__title mrn-ui__heading">
									<?php if ( '' !== $permalink ) : ?>
										<a class="mrn-ui__link" href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( $item_title ); ?></a>
								<?php else : ?>
									<?php echo esc_html( $item_title ); ?>
								<?php endif; ?>
							</h3>
						<?php elseif ( 'excerpt' === $field_key && '' !== $item_excerpt ) : ?>
								<p class="mrn-content-list-row__excerpt mrn-ui__text"><?php echo esc_html( $item_excerpt ); ?></p>
							<?php elseif ( 'read_more' === $field_key && $show_read_more ) : ?>
									<p class="mrn-content-list-row__link">
										<a class="mrn-ui__link" href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( '' !== $read_more_label ? $read_more_label : 'Read More' ); ?></a>
							</p>
						<?php endif; ?>
					<?php endforeach; ?>
						<?php if ( $head_open ) : ?>
							</div>
						<?php endif; ?>
				</div>
			</article>
		<?php endif; ?>
	</li>
	<?php

	return (string) ob_get_clean();
}

/**
 * Shared order-by choices for query-driven builder layouts.
 *
 * @return array<string, string>
 */
function mrn_base_stack_get_content_list_orderby_choices() {
	return array(
		'date'          => 'Publish Date',
		'modified'      => 'Modified Date',
		'title'         => 'Title',
		'menu_order'    => 'Menu Order',
		'comment_count' => 'Comment Count',
		'rand'          => 'Random',
	);
}

/**
 * Shared taxonomy choices for query-driven builder layouts.
 *
 * @return array<string, string>
 */
function mrn_base_stack_get_content_list_taxonomy_choices() {
	$taxonomies = get_taxonomies(
		array(
			'public'  => true,
			'show_ui' => true,
		),
		'objects'
	);
	$choices    = array();
	$excluded   = array(
		'nav_menu',
		'link_category',
		'post_format',
	);

	foreach ( $taxonomies as $taxonomy => $taxonomy_object ) {
		if ( ! $taxonomy_object instanceof WP_Taxonomy ) {
			continue;
		}

		if ( in_array( $taxonomy, $excluded, true ) ) {
			continue;
		}

		$label = isset( $taxonomy_object->labels->name ) ? trim( (string) $taxonomy_object->labels->name ) : '';
		if ( '' === $label ) {
			$label = ucfirst( str_replace( array( '-', '_' ), ' ', $taxonomy ) );
		}

		$choices[ $taxonomy ] = $label;
	}

	if ( empty( $choices['category'] ) && taxonomy_exists( 'category' ) ) {
		$choices = array_merge( array( 'category' => 'Categories' ), $choices );
	}

	return $choices;
}

/**
 * Shared filter source choices for query-driven builder layouts.
 *
 * @return array<string, string>
 */
function mrn_base_stack_get_content_list_filter_source_choices() {
	return array(
		'none'               => 'No Filter',
		'current_post_terms' => 'Use Current Page/Post Terms',
		'manual_terms'       => 'Use Specific Terms',
	);
}

/**
 * Shared term matching choices for query-driven builder layouts.
 *
 * @return array<string, string>
 */
function mrn_base_stack_get_content_list_filter_match_choices() {
	return array(
		'any' => 'Match Any Selected Term',
		'all' => 'Match All Selected Terms',
	);
}

/**
 * Build a standard section-width ACF field definition.
 *
 * @param string $key Unique ACF field key.
 * @param string $name Field name.
 * @param string $default_width Default width choice.
 * @param string $label Field label.
 * @return array<string, mixed>
 */
function mrn_base_stack_get_section_width_field( $key, $name = 'section_width', $default_width = 'wide', $label = 'Section Width' ) {
	return array(
		'key'               => $key,
		'label'             => $label,
		'name'              => $name,
		'aria-label'        => '',
		'type'              => 'select',
		'choices'           => mrn_base_stack_get_section_width_choices(),
		'default_value'     => $default_width,
		'ui'                => 1,
		'wrapper'           => array(
			'width' => '50',
		),
	);
}

/**
 * Build a standard sub-content width ACF field definition for repeater wrappers.
 *
 * @param string $key Unique ACF field key.
 * @param string $name Field name.
 * @param string $label Field label.
 * @return array<string, mixed>
 */
function mrn_base_stack_get_sub_content_width_field( $key, $name = 'sub_content_width', $label = 'Section Width (Sub-content)' ) {
	return array(
		'key'               => $key,
		'label'             => $label,
		'name'              => $name,
		'aria-label'        => '',
		'type'              => 'select',
		'choices'           => mrn_base_stack_get_section_width_choices(),
		'default_value'     => 'content',
		'ui'                => 1,
		'allow_null'        => 0,
		'wrapper'           => array(
			'width' => '50',
		),
	);
}

/**
 * Build the standard anchor ACF field definition for builder rows.
 *
 * @param string $key Unique ACF field key.
 * @param string $name Field name.
 * @param string $label Field label.
 * @return array<string, mixed>
 */
function mrn_base_stack_get_anchor_field( $key, $name = 'anchor', $label = 'Anchor ID' ) {
	return array(
		'key'          => $key,
		'label'        => $label,
		'name'         => $name,
		'aria-label'   => '',
		'type'         => 'text',
		'instructions' => 'Optional anchor slug for one-page links. Enter the value without #.',
		'wrapper'      => array(
			'width' => '50',
		),
	);
}

/**
 * Shared motion effect choices for builder layouts.
 *
 * @return array<string, string>
 */
function mrn_base_stack_get_motion_effect_choices() {
	return array(
		'surface'          => 'Switch Light/Dark Surface',
		'active-class'     => 'Mark Row As Active',
		'dark-scroll-card' => 'Darken Card On Scroll',
	);
}

/**
 * Get beginner-friendly motion preset choices for a supported effect.
 *
 * @param string $effect Effect key.
 * @return array<string, string>
 */
function mrn_base_stack_get_motion_preset_choices( $effect ) {
	$effect = sanitize_key( (string) $effect );

	if ( 'dark-scroll-card' === $effect ) {
		if ( function_exists( 'mrn_site_styles_get_dark_scroll_card_preset_choices' ) ) {
			return mrn_site_styles_get_dark_scroll_card_preset_choices();
		}

		return array(
			'' => 'Default Dark Card',
		);
	}

	return array(
		'' => 'Default',
	);
}

/**
 * Shared beginner-friendly trigger choices for motion effects.
 *
 * @return array<string, string>
 */
function mrn_base_stack_get_motion_trigger_choices() {
	return array(
		'early'  => 'Early',
		'center' => 'Center',
		'late'   => 'Late',
	);
}

/**
 * Get field names that belong in the shared Effects tab.
 *
 * @return array<int, string>
 */
function mrn_base_stack_get_effects_tab_field_names() {
	return array(
		'enable_row_effects',
		'tab_switch_effect',
	);
}

/**
 * Shared tab-switch animation choices for tabbed layouts.
 *
 * @return array<string, string>
 */
function mrn_base_stack_get_tab_switch_effect_choices() {
	return array(
		'instant' => 'Instant',
		'fade'    => 'Fade',
		'slide'   => 'Slide',
	);
}

/**
 * Shared target choices for non-surface motion effects.
 *
 * @return array<string, string>
 */
function mrn_base_stack_get_motion_target_choices() {
	return array(
		'row'          => 'Entire Layout',
		'surface'      => 'Inner Surface',
		'content'      => 'Text / Content Area',
		'media'        => 'Image / Media',
		'header'       => 'Heading Area',
		'items'        => 'Items / Grid',
		'left-column'  => 'Left Sub-Layout',
		'right-column' => 'Right Sub-Layout',
	);
}

/**
 * Normalize a stored motion target to a supported value.
 *
 * @param mixed $value Raw stored target value.
 * @return string
 */
function mrn_base_stack_normalize_motion_target( $value ) {
	$target  = sanitize_key( (string) $value );
	$choices = mrn_base_stack_get_motion_target_choices();

	if ( ! isset( $choices[ $target ] ) ) {
		return 'row';
	}

	return $target;
}

/**
 * Convert a stored trigger position into a Motion margin string.
 *
 * @param mixed $value Raw stored trigger value.
 * @return string
 */
function mrn_base_stack_get_motion_margin_for_trigger( $value ) {
	$trigger = is_string( $value ) ? sanitize_key( $value ) : '';

	if ( 'early' === $trigger ) {
		return '-20% 0px -20% 0px';
	}

	if ( 'late' === $trigger ) {
		return '-45% 0px -10% 0px';
	}

	return '-35% 0px -35% 0px';
}

/**
 * Build the standard motion-settings ACF group field definition.
 *
 * @param string $key Unique ACF field key.
 * @param string $name Field name.
 * @param string $label Field label.
 * @return array<string, mixed>
 */
function mrn_base_stack_get_motion_group_field( $key, $name = 'motion_settings', $label = 'Motion Effects' ) {
	$enabled_key   = $key . '_enabled';
	$effect_key    = $key . '_effect';
	$preset_key    = $key . '_preset';
	$surface_key   = $key . '_surface';
	$target_key    = $key . '_target';
	$enabled_logic = array(
		array(
			array(
				'field'    => $enabled_key,
				'operator' => '==',
				'value'    => '1',
			),
		),
	);

	return array(
		'key'        => $key,
		'label'      => $label,
		'name'       => $name,
		'aria-label' => '',
		'type'       => 'group',
		'layout'     => 'block',
		'sub_fields' => array(
			array(
				'key'           => $enabled_key,
				'label'         => 'Enable Row Effects',
				'name'          => 'enabled',
				'aria-label'    => '',
				'type'          => 'true_false',
				'ui'            => 1,
				'default_value' => 0,
				'ui_on_text'    => 'On',
				'ui_off_text'   => 'Off',
				'wrapper'       => array(
					'width' => '33',
				),
			),
			array(
				'key'               => $effect_key,
				'label'             => 'Effect Style',
				'name'              => 'effect',
				'aria-label'        => '',
				'type'              => 'select',
				'choices'           => mrn_base_stack_get_motion_effect_choices(),
				'default_value'     => 'surface',
				'ui'                => 1,
				'wrapper'           => array(
					'width' => '34',
				),
				'conditional_logic' => $enabled_logic,
			),
			array(
				'key'               => $key . '_trigger_position',
				'label'             => 'Start Effect',
				'name'              => 'trigger_position',
				'aria-label'        => '',
				'type'              => 'select',
				'choices'           => mrn_base_stack_get_motion_trigger_choices(),
				'default_value'     => 'center',
				'ui'                => 1,
				'instructions'      => 'Choose where in the viewport the effect should become noticeable.',
				'wrapper'           => array(
					'width' => '33',
				),
				'conditional_logic' => $enabled_logic,
			),
			array(
				'key'               => $target_key,
				'label'             => 'Apply To',
				'name'              => 'target',
				'aria-label'        => '',
				'type'              => 'select',
				'choices'           => mrn_base_stack_get_motion_target_choices(),
				'default_value'     => 'row',
				'ui'                => 1,
				'instructions'      => 'Choose which part of the layout should receive the effect.',
				'wrapper'           => array(
					'width' => '33',
				),
				'conditional_logic' => array(
					array(
						array(
							'field'    => $enabled_key,
							'operator' => '==',
							'value'    => '1',
						),
						array(
							'field'    => $effect_key,
							'operator' => '!=',
							'value'    => 'surface',
						),
					),
				),
			),
			array(
				'key'               => $surface_key,
				'label'             => 'Surface Look',
				'name'              => 'surface',
				'aria-label'        => '',
				'type'              => 'select',
				'choices'           => array(
					'light' => 'Light',
					'dark'  => 'Dark',
				),
				'default_value'     => 'dark',
				'ui'                => 1,
				'wrapper'           => array(
					'width' => '50',
				),
				'conditional_logic' => array(
					array(
						array(
							'field'    => $enabled_key,
							'operator' => '==',
							'value'    => '1',
						),
						array(
							'field'    => $effect_key,
							'operator' => '==',
							'value'    => 'surface',
						),
					),
				),
			),
			array(
				'key'               => $preset_key,
				'label'             => 'Effect Preset',
				'name'              => 'preset',
				'aria-label'        => '',
				'type'              => 'select',
				'choices'           => mrn_base_stack_get_motion_preset_choices( 'dark-scroll-card' ),
				'default_value'     => '',
				'ui'                => 1,
				'instructions'      => 'Choose a saved visual preset from Site Styles.',
				'wrapper'           => array(
					'width' => '50',
				),
				'conditional_logic' => array(
					array(
						array(
							'field'    => $enabled_key,
							'operator' => '==',
							'value'    => '1',
						),
						array(
							'field'    => $effect_key,
							'operator' => '==',
							'value'    => 'dark-scroll-card',
						),
					),
				),
			),
		),
	);
}

/**
 * Build the standard Effects tab field definition for builder layouts.
 *
 * @param string $key Unique ACF field key.
 * @param string $label Tab label.
 * @return array<string, mixed>
 */
function mrn_base_stack_get_effects_tab_field( $key, $label = 'Effects' ) {
	return array(
		'key'        => $key,
		'label'      => $label,
		'name'       => '',
		'aria-label' => '',
		'type'       => 'tab',
		'placement'  => 'top',
		'endpoint'   => 0,
	);
}

/**
 * Build the standard internal layout name field for editor-only row labels.
 *
 * @param string $key Unique ACF field key.
 * @return array<string, mixed>
 */
function mrn_base_stack_get_internal_layout_name_field( $key ) {
	return array(
		'key'          => $key,
		'label'        => 'Name (admin use only)',
		'name'         => 'internal_name',
		'aria-label'   => '',
		'type'         => 'text',
		'instructions' => 'Optional editor-only row name used in the layout list. This is not rendered on the front end.',
		'wrapper'      => array(
			'width' => '50',
		),
	);
}

/**
 * Normalize one field label to the shared primary-layout contract.
 *
 * @param array<string, mixed> $field ACF field definition.
 * @return array<string, mixed>
 */
function mrn_base_stack_normalize_primary_layout_field( array $field ) {
	$field_type           = isset( $field['type'] ) ? sanitize_key( (string) $field['type'] ) : '';
	$field_name           = isset( $field['name'] ) ? sanitize_key( (string) $field['name'] ) : '';
	$is_tag_chooser_field = ( 'select' === $field_type ) && ( 1 === preg_match( '/(^|_)(label|heading|subheading|text_field)_tag$/', $field_name ) );

	if ( 'internal_name' === $field_name ) {
		$field['label'] = 'Name (admin use only)';
	}

	if ( in_array( $field_name, array( 'label', 'tab_label' ), true ) && 'text' === $field_type ) {
		$field['label'] = 'Label';
		if ( ! isset( $field['wrapper'] ) || ! is_array( $field['wrapper'] ) ) {
			$field['wrapper'] = array();
		}
		$field['wrapper']['width'] = '75';

		if ( 'tab_label' === $field_name ) {
			$field['instructions'] = '';
		}
	}

	if ( $is_tag_chooser_field ) {
		$field['label'] = 'Tag';
		if ( ! isset( $field['wrapper'] ) || ! is_array( $field['wrapper'] ) ) {
			$field['wrapper'] = array();
		}
		$field['wrapper']['width'] = '25';
	}

	if ( 'heading' === $field_name && 'text' === $field_type ) {
		$field['label'] = 'Heading';
		if ( ! isset( $field['wrapper'] ) || ! is_array( $field['wrapper'] ) ) {
			$field['wrapper'] = array();
		}
		$field['wrapper']['width'] = '75';
	}

	if ( 'subheading' === $field_name && 'text' === $field_type ) {
		$field['label'] = 'Subheading';
		if ( ! isset( $field['wrapper'] ) || ! is_array( $field['wrapper'] ) ) {
			$field['wrapper'] = array();
		}
		$field['wrapper']['width'] = '75';
	}

	if ( 'wysiwyg' === $field_type && in_array( $field_name, array( 'content', 'body_text', 'intro' ), true ) ) {
		$field['label'] = 'Text';
	}

	if ( 'repeater' === $field_type && 'links' !== $field_name ) {
		$field['layout'] = 'block';

		/*
		 * Keep showcase contract repeaters expanded by default so their shared
		 * Content|Configs|Effects tabs are immediately visible.
		 */
		if ( 'showcase_items' === $field_name ) {
			$field['collapsed'] = '';
		}
	}

	if ( 'links' === $field_name && 'repeater' === $field_type ) {
		$field['label'] = 'Link repeater';
	}

	if ( 'background_color' === $field_name && 'select' === $field_type ) {
		$field['label'] = 'Background Color';
	}

	if ( 'anchor' === $field_name ) {
		$field['label'] = 'Anchor ID';
	}

	if ( 'section_width' === $field_name && 'select' === $field_type ) {
		$field['label'] = 'Section Width (Content)';
	}

	if ( 'sub_content_width' === $field_name && 'select' === $field_type ) {
		$field['label'] = 'Section Width (Sub-content)';
	}

	if ( 'bottom_accent' === $field_name && 'true_false' === $field_type ) {
		$field['label'] = 'Accent';
	}

	if ( 'bottom_accent_style' === $field_name && 'select' === $field_type ) {
		$field['label'] = 'Bottom accent style';
	}

	return $field;
}

/**
 * Keep Label/Heading/Subheading text fields at 75% when paired with *_tag fields.
 *
 * This supports nested repeater naming patterns like `item_label` + `item_label_tag`.
 *
 * @param array<int, mixed> $fields Flexible-content field definitions.
 * @return array<int, mixed>
 */
function mrn_base_stack_apply_tag_field_column_layout( array $fields ) {
	$text_field_indexes_by_name = array();

	foreach ( $fields as $index => $field ) {
		if ( ! is_array( $field ) ) {
			continue;
		}

		$field_type = isset( $field['type'] ) ? sanitize_key( (string) $field['type'] ) : '';
		$field_name = isset( $field['name'] ) ? sanitize_key( (string) $field['name'] ) : '';

		if ( 'text' !== $field_type || '' === $field_name ) {
			continue;
		}

		if ( ! isset( $text_field_indexes_by_name[ $field_name ] ) ) {
			$text_field_indexes_by_name[ $field_name ] = array();
		}

		$text_field_indexes_by_name[ $field_name ][] = $index;
	}

	foreach ( $fields as $field ) {
		if ( ! is_array( $field ) ) {
			continue;
		}

		$field_type = isset( $field['type'] ) ? sanitize_key( (string) $field['type'] ) : '';
		$field_name = isset( $field['name'] ) ? sanitize_key( (string) $field['name'] ) : '';

		if ( 'select' !== $field_type || '' === $field_name ) {
			continue;
		}

		if ( 1 !== preg_match( '/^(.*?)(label|heading|subheading)_tag$/', $field_name, $matches ) ) {
			continue;
		}

		$companion_name = sanitize_key( $matches[1] . $matches[2] );
		if ( '' === $companion_name || ! isset( $text_field_indexes_by_name[ $companion_name ] ) ) {
			continue;
		}

		foreach ( $text_field_indexes_by_name[ $companion_name ] as $text_index ) {
			if ( ! isset( $fields[ $text_index ] ) || ! is_array( $fields[ $text_index ] ) ) {
				continue;
			}

			if ( ! isset( $fields[ $text_index ]['wrapper'] ) || ! is_array( $fields[ $text_index ]['wrapper'] ) ) {
				$fields[ $text_index ]['wrapper'] = array();
			}

			$fields[ $text_index ]['wrapper']['width'] = '75';
		}
	}

	return $fields;
}

/**
 * Ensure non-link repeater sub-fields include a Subheading + Tag pair.
 *
 * When a repeater row already follows the heading/tag pattern but is missing
 * subheading fields, inject them in-place without adding internal-name fields.
 *
 * @param array<int, mixed> $fields Repeater sub-fields.
 * @param string            $repeater_key Parent repeater field key.
 * @param string            $repeater_name Parent repeater field name.
 * @return array<int, mixed>
 */
function mrn_base_stack_ensure_repeater_subheading_contract( array $fields, $repeater_key = '', $repeater_name = '' ) {
	$repeater_name = sanitize_key( (string) $repeater_name );
	if ( in_array( $repeater_name, array( 'tabs', 'stat_items', 'showcase_items' ), true ) ) {
		return $fields;
	}

	foreach ( $fields as $index => $field ) {
		if ( ! is_array( $field ) ) {
			continue;
		}

		$field_name = isset( $field['name'] ) ? sanitize_key( (string) $field['name'] ) : '';
		if ( '' === $field_name ) {
			continue;
		}

		$is_subsubheading_field = false;
		if ( strlen( $field_name ) >= 13 && 'subsubheading' === substr( $field_name, -13 ) ) {
			$is_subsubheading_field = true;
		}
		if ( strlen( $field_name ) >= 17 && 'subsubheading_tag' === substr( $field_name, -17 ) ) {
			$is_subsubheading_field = true;
		}

		if ( $is_subsubheading_field ) {
			unset( $fields[ $index ] );
		}
	}
	$fields = array_values( $fields );

	$heading_index        = null;
	$heading_tag_index    = null;
	$subheading_index     = null;
	$subheading_tag_index = null;
	$prefix               = null;
	$heading_key          = '';

	foreach ( $fields as $index => $field ) {
		if ( ! is_array( $field ) ) {
			continue;
		}

		$field_type = isset( $field['type'] ) ? sanitize_key( (string) $field['type'] ) : '';
		$field_name = isset( $field['name'] ) ? sanitize_key( (string) $field['name'] ) : '';
		$field_key  = isset( $field['key'] ) && is_string( $field['key'] ) ? trim( $field['key'] ) : '';

		if ( '' === $field_name ) {
			continue;
		}

		$is_subheading_seed = strlen( $field_name ) >= 10 && 'subheading' === substr( $field_name, -10 );
		if ( null === $heading_index && 'text' === $field_type && ! $is_subheading_seed && 1 === preg_match( '/^(.*)heading$/', $field_name, $heading_match ) ) {
			$heading_index = $index;
			$prefix        = $heading_match[1];
			$heading_key   = $field_key;
			continue;
		}

		if ( null === $prefix ) {
			continue;
		}

		if ( null === $heading_tag_index && 'select' === $field_type && in_array( $field_name, array( $prefix . 'heading_tag', $prefix . 'text_field_tag' ), true ) ) {
			$heading_tag_index = $index;
			continue;
		}

		if ( null === $subheading_index && 'text' === $field_type && $field_name === $prefix . 'subheading' ) {
			$subheading_index = $index;
			continue;
		}

		if ( null === $subheading_tag_index && 'select' === $field_type && $field_name === $prefix . 'subheading_tag' ) {
			$subheading_tag_index = $index;
		}
	}

	if ( null === $heading_index || null === $prefix ) {
		$fallback_anchor_index = null;
		$fallback_anchor_name  = '';
		$fallback_anchor_key   = '';
		$fallback_prefix       = '';

		foreach ( $fields as $index => $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}

			$field_type = isset( $field['type'] ) ? sanitize_key( (string) $field['type'] ) : '';
			$field_name = isset( $field['name'] ) ? sanitize_key( (string) $field['name'] ) : '';
			$field_key  = isset( $field['key'] ) && is_string( $field['key'] ) ? trim( $field['key'] ) : '';

			if ( '' === $field_name ) {
				continue;
			}

			if ( null === $fallback_anchor_index && 'tab' !== $field_type && 'accordion' !== $field_type && 'links' !== $field_name ) {
				$fallback_anchor_index = $index;
				$fallback_anchor_name  = $field_name;
				$fallback_anchor_key   = $field_key;
			}

			if ( '' === $fallback_prefix && 1 === preg_match( '/^(.*?)(heading|label|text|content)$/', $field_name, $fallback_match ) ) {
				$fallback_prefix = $fallback_match[1];
			}
		}

		if ( null === $fallback_anchor_index ) {
			return $fields;
		}

		$heading_index = $fallback_anchor_index;
		$heading_key   = $fallback_anchor_key;
		$prefix        = $fallback_prefix;

		foreach ( $fields as $index => $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}

			$field_type = isset( $field['type'] ) ? sanitize_key( (string) $field['type'] ) : '';
			$field_name = isset( $field['name'] ) ? sanitize_key( (string) $field['name'] ) : '';

			if ( '' === $field_name ) {
				continue;
			}

			if ( null === $heading_tag_index && 'select' === $field_type && in_array( $field_name, array( $prefix . 'heading_tag', $prefix . 'text_field_tag' ), true ) ) {
				$heading_tag_index = $index;
				continue;
			}

			if ( null === $subheading_index && 'text' === $field_type && $field_name === $prefix . 'subheading' ) {
				$subheading_index = $index;
				continue;
			}

			if ( null === $subheading_tag_index && 'select' === $field_type && $field_name === $prefix . 'subheading_tag' ) {
				$subheading_tag_index = $index;
			}
		}
	}

	$needs_subheading     = null === $subheading_index;
	$needs_subheading_tag = null === $subheading_tag_index;

	if ( ! $needs_subheading && ! $needs_subheading_tag ) {
		return $fields;
	}

	$subheading_name = $prefix . 'subheading';
	$tag_name        = $prefix . 'subheading_tag';
	$key_seed        = '' !== $heading_key ? sanitize_key( $heading_key ) : sanitize_key( (string) $repeater_key );
	if ( '' === $key_seed ) {
		$key_seed = 'field_mrn_subfield_heading';
	}

	$subheading_key     = $key_seed . '_subheading';
	$subheading_tag_key = $key_seed . '_subheading_tag';
	$new_fields         = array();

	if ( $needs_subheading ) {
		$new_fields[] = mrn_base_stack_get_inline_text_field( $subheading_key, 'Subheading', $subheading_name );
	}

	if ( $needs_subheading_tag ) {
		$new_fields[] = mrn_base_stack_get_text_tag_field( $subheading_tag_key, $tag_name, 'p', 'Tag' );
	}

	if ( empty( $new_fields ) ) {
		return $fields;
	}

	if ( $needs_subheading && ! $needs_subheading_tag ) {
		$insert_at = null !== $subheading_tag_index ? $subheading_tag_index : ( null !== $heading_tag_index ? $heading_tag_index + 1 : $heading_index + 1 );
		array_splice( $fields, $insert_at, 0, array( $new_fields[0] ) );
		return $fields;
	}

	if ( ! $needs_subheading && $needs_subheading_tag ) {
		$insert_at = null !== $subheading_index ? $subheading_index + 1 : ( null !== $heading_tag_index ? $heading_tag_index + 1 : $heading_index + 1 );
		array_splice( $fields, $insert_at, 0, array( $new_fields[0] ) );
		return $fields;
	}

	$insert_at = null !== $heading_tag_index ? $heading_tag_index + 1 : $heading_index + 1;
	array_splice( $fields, $insert_at, 0, $new_fields );

	return $fields;
}

/**
 * Ensure tabbed-content repeater items start with the primary content contract.
 *
 * Tab items keep their saved-data key (`tab_label`) for backward compatibility,
 * while exposing the standard `Name`, `Label`, `Heading`, and `Subheading`
 * experience in a predictable order at the top of the Content tab.
 *
 * @param array<int, mixed> $fields Repeater sub-fields.
 * @param string            $repeater_name Parent repeater name.
 * @param string            $repeater_key Parent repeater key.
 * @return array<int, mixed>
 */
function mrn_base_stack_ensure_tabs_repeater_primary_content_contract( array $fields, $repeater_name, $repeater_key = '' ) {
	$repeater_name = sanitize_key( (string) $repeater_name );
	if ( 'tabs' !== $repeater_name ) {
		return $fields;
	}

	$contract_indexes = array(
		'internal_name'   => null,
		'tab_label'       => null,
		'heading'         => null,
		'heading_tag'     => null,
		'subheading'      => null,
		'subheading_tag'  => null,
	);

	foreach ( $fields as $index => $field ) {
		if ( ! is_array( $field ) ) {
			continue;
		}

		$field_name = isset( $field['name'] ) ? sanitize_key( (string) $field['name'] ) : '';

		if ( array_key_exists( $field_name, $contract_indexes ) && null === $contract_indexes[ $field_name ] ) {
			$contract_indexes[ $field_name ] = $index;
		}
	}

	if ( null === $contract_indexes['tab_label'] ) {
		return $fields;
	}

	$tab_label_field = null;
	if ( null !== $contract_indexes['tab_label'] && isset( $fields[ $contract_indexes['tab_label'] ] ) && is_array( $fields[ $contract_indexes['tab_label'] ] ) ) {
		$tab_label_field = $fields[ $contract_indexes['tab_label'] ];
	}

	if ( ! is_array( $tab_label_field ) ) {
		return $fields;
	}

	$key_seed = sanitize_key( (string) $repeater_key );
	if ( '' === $key_seed ) {
		$key_seed = 'field_mrn_tab_item';
	}

	$internal_name_field = null;
	if ( null !== $contract_indexes['internal_name'] && isset( $fields[ $contract_indexes['internal_name'] ] ) && is_array( $fields[ $contract_indexes['internal_name'] ] ) ) {
		$internal_name_field = $fields[ $contract_indexes['internal_name'] ];
	}
	if ( ! is_array( $internal_name_field ) ) {
		$internal_name_field = mrn_base_stack_get_internal_layout_name_field( $key_seed . '_internal_name' );
	}

	$heading_field = null;
	if ( null !== $contract_indexes['heading'] && isset( $fields[ $contract_indexes['heading'] ] ) && is_array( $fields[ $contract_indexes['heading'] ] ) ) {
		$heading_field = $fields[ $contract_indexes['heading'] ];
	}
	if ( ! is_array( $heading_field ) ) {
		$heading_field = mrn_base_stack_get_inline_text_field( $key_seed . '_heading', 'Heading', 'heading' );
	}

	$heading_tag_field = null;
	if ( null !== $contract_indexes['heading_tag'] && isset( $fields[ $contract_indexes['heading_tag'] ] ) && is_array( $fields[ $contract_indexes['heading_tag'] ] ) ) {
		$heading_tag_field = $fields[ $contract_indexes['heading_tag'] ];
	}
	if ( ! is_array( $heading_tag_field ) ) {
		$heading_tag_field = mrn_base_stack_get_text_tag_field( $key_seed . '_heading_tag', 'heading_tag', 'h3', 'Tag' );
	}

	$subheading_field = null;
	if ( null !== $contract_indexes['subheading'] && isset( $fields[ $contract_indexes['subheading'] ] ) && is_array( $fields[ $contract_indexes['subheading'] ] ) ) {
		$subheading_field = $fields[ $contract_indexes['subheading'] ];
	}
	if ( ! is_array( $subheading_field ) ) {
		$subheading_field = mrn_base_stack_get_inline_text_field( $key_seed . '_subheading', 'Subheading', 'subheading' );
	}

	$subheading_tag_field = null;
	if ( null !== $contract_indexes['subheading_tag'] && isset( $fields[ $contract_indexes['subheading_tag'] ] ) && is_array( $fields[ $contract_indexes['subheading_tag'] ] ) ) {
		$subheading_tag_field = $fields[ $contract_indexes['subheading_tag'] ];
	}
	if ( ! is_array( $subheading_tag_field ) ) {
		$subheading_tag_field = mrn_base_stack_get_text_tag_field( $key_seed . '_subheading_tag', 'subheading_tag', 'p', 'Tag' );
	}

	/*
	 * Remove existing contract fields so they can be re-inserted in one stable
	 * order directly after the Content tab.
	 */
	$kept_fields = array();
	foreach ( $fields as $field ) {
		if ( ! is_array( $field ) ) {
			$kept_fields[] = $field;
			continue;
		}

		$field_name = isset( $field['name'] ) ? sanitize_key( (string) $field['name'] ) : '';
		if ( in_array( $field_name, array( 'internal_name', 'tab_label', 'heading', 'heading_tag', 'subheading', 'subheading_tag' ), true ) ) {
			continue;
		}

		$kept_fields[] = $field;
	}

	$insert_index = 0;
	foreach ( $kept_fields as $index => $field ) {
		if ( ! is_array( $field ) ) {
			continue;
		}

		$field_type  = isset( $field['type'] ) ? sanitize_key( (string) $field['type'] ) : '';
		$field_label = isset( $field['label'] ) ? sanitize_title( (string) $field['label'] ) : '';
		if ( 'tab' === $field_type && 'content' === $field_label ) {
			$insert_index = $index + 1;
			break;
		}
	}

	$contract_segment = array(
		$internal_name_field,
		$tab_label_field,
		$heading_field,
		$heading_tag_field,
		$subheading_field,
		$subheading_tag_field,
	);

	array_splice( $kept_fields, $insert_index, 0, $contract_segment );

	return $kept_fields;
}

/**
 * Check whether a repeater should receive the shared item-level contract tabs.
 *
 * @param string $repeater_name Repeater field name.
 * @return bool
 */
function mrn_base_stack_repeater_uses_primary_item_contract( $repeater_name ) {
	$repeater_name = sanitize_key( (string) $repeater_name );

	return in_array(
		$repeater_name,
		array(
			'grid_items',
			'card_items',
			'showcase_items',
			'slider_items',
			'tabs',
			'logo_items',
		),
		true
	);
}

/**
 * Ensure injected/normalized ACF fields retain required runtime keys.
 *
 * ACF runtime expects non-empty-name sub-fields to include an `_name` key.
 * Repeater table rendering also expects wrapper keys such as `class`/`id` on
 * collapsed targets. Contract-generated fields can miss these keys when
 * inserted during `acf/load_field` filters, which produces undefined-index
 * warnings in ACF Pro.
 *
 * @param array<int, mixed> $fields Field definitions.
 * @return array<int, mixed>
 */
function mrn_base_stack_ensure_acf_field_origin_names( array $fields ) {
	foreach ( $fields as $index => $field ) {
		if ( ! is_array( $field ) ) {
			continue;
		}

		if ( isset( $field['name'] ) && is_string( $field['name'] ) && '' !== trim( $field['name'] ) && ! isset( $field['_name'] ) ) {
			$field['_name'] = $field['name'];
		}

		if ( ! isset( $field['wrapper'] ) || ! is_array( $field['wrapper'] ) ) {
			$field['wrapper'] = array();
		}

		if ( ! array_key_exists( 'width', $field['wrapper'] ) ) {
			$field['wrapper']['width'] = '';
		}

		if ( ! array_key_exists( 'class', $field['wrapper'] ) ) {
			$field['wrapper']['class'] = '';
		}

		if ( ! array_key_exists( 'id', $field['wrapper'] ) ) {
			$field['wrapper']['id'] = '';
		}

		if ( isset( $field['sub_fields'] ) && is_array( $field['sub_fields'] ) ) {
			$field['sub_fields'] = mrn_base_stack_ensure_acf_field_origin_names( $field['sub_fields'] );
		}

		if ( isset( $field['fields'] ) && is_array( $field['fields'] ) ) {
			$field['fields'] = mrn_base_stack_ensure_acf_field_origin_names( $field['fields'] );
		}

		if ( isset( $field['layouts'] ) && is_array( $field['layouts'] ) ) {
			foreach ( $field['layouts'] as $layout_key => $layout ) {
				if ( ! is_array( $layout ) || ! isset( $layout['sub_fields'] ) || ! is_array( $layout['sub_fields'] ) ) {
					continue;
				}

				$layout['sub_fields']            = mrn_base_stack_ensure_acf_field_origin_names( $layout['sub_fields'] );
				$field['layouts'][ $layout_key ] = $layout;
			}
		}

		$fields[ $index ] = $field;
	}

	return $fields;
}

/**
 * Resolve the functionality group for a repeater config field.
 *
 * @param array<string, mixed> $field ACF field definition.
 * @return string
 */
function mrn_base_stack_get_repeater_config_field_group_key( array $field ) {
	$field_name = isset( $field['name'] ) ? sanitize_key( (string) $field['name'] ) : '';

	if ( '' === $field_name ) {
		return '';
	}

	if ( 0 === strpos( $field_name, 'link_icon_' ) ) {
		return 'icons';
	}

	if ( in_array( $field_name, array( 'is_button', 'target', 'download' ), true ) ) {
		return 'behavior';
	}

	if ( in_array( $field_name, array( 'rel', 'title_attribute', 'hreflang', 'media' ), true ) ) {
		return 'attributes';
	}

	if ( in_array( $field_name, array( 'css_classes', 'background_color' ), true ) ) {
		return 'appearance';
	}

	return 'advanced';
}

/**
 * Build the shared "future use" message field for empty contract groups.
 *
 * @param string $key Unique ACF field key.
 * @return array<string, mixed>
 */
function mrn_base_stack_get_contract_future_use_message_field( $key ) {
	return array(
		'key'        => $key,
		'label'      => '',
		'name'       => '',
		'aria-label' => '',
		'type'       => 'message',
		'message'    => 'Future Use, Stay Tuned...',
		'new_lines'  => 'wpautop',
		'esc_html'   => 1,
		'wrapper'    => array(
			'width' => '100',
		),
	);
}

/**
 * Group repeater config controls by functionality within the Configs tab.
 *
 * @param array<int, mixed> $fields Repeater sub-fields.
 * @param string            $key_seed Repeater key seed.
 * @return array<int, mixed>
 */
function mrn_base_stack_group_repeater_config_fields_by_functionality( array $fields, $key_seed ) {
	$config_tab_index  = null;
	$next_tab_index    = null;
	$total_fields      = count( $fields );
	$config_candidates = array();

	foreach ( $fields as $index => $field ) {
		if ( ! is_array( $field ) ) {
			continue;
		}

		$field_type  = isset( $field['type'] ) ? sanitize_key( (string) $field['type'] ) : '';
		$field_label = isset( $field['label'] ) ? sanitize_title( (string) $field['label'] ) : '';
		$field_key   = isset( $field['key'] ) ? sanitize_key( (string) $field['key'] ) : '';

		if ( 'tab' !== $field_type ) {
			continue;
		}

		if ( 'configs' === $field_label ) {
			$config_candidates[] = array(
				'index' => $index,
				'key'   => $field_key,
			);
		}
	}

	if ( empty( $config_candidates ) ) {
		return $fields;
	}

	foreach ( $config_candidates as $candidate ) {
		$candidate_key = isset( $candidate['key'] ) ? (string) $candidate['key'] : '';
		if ( '' !== $candidate_key && false !== strpos( $candidate_key, 'link_configs_tab_contract' ) ) {
			continue;
		}

		$config_tab_index = isset( $candidate['index'] ) ? (int) $candidate['index'] : null;
		break;
	}

	if ( null === $config_tab_index ) {
		$config_tab_index = (int) $config_candidates[0]['index'];
	}

	foreach ( $fields as $index => $field ) {
		if ( $index <= $config_tab_index || ! is_array( $field ) ) {
			continue;
		}

		$field_type = isset( $field['type'] ) ? sanitize_key( (string) $field['type'] ) : '';
		if ( 'tab' === $field_type ) {
			$next_tab_index = $index;
			break;
		}
	}

	$segment_start = $config_tab_index + 1;
	$segment_end   = null !== $next_tab_index ? $next_tab_index : $total_fields;
	$segment_len   = max( 0, $segment_end - $segment_start );

	if ( $segment_len < 1 ) {
		return $fields;
	}

	$config_fields = array_slice( $fields, $segment_start, $segment_len );
	$group_prefix  = sanitize_key( (string) $key_seed ) . '_cfg_group_';
	$sanitized     = array();

	foreach ( $config_fields as $field ) {
		if ( ! is_array( $field ) ) {
			continue;
		}

		$field_key  = isset( $field['key'] ) ? sanitize_key( (string) $field['key'] ) : '';
		$field_type = isset( $field['type'] ) ? sanitize_key( (string) $field['type'] ) : '';

		if ( 'accordion' === $field_type && '' !== $field_key && 0 === strpos( $field_key, $group_prefix ) ) {
			continue;
		}

		if ( 'message' === $field_type && $group_prefix . 'advanced_future_use' === $field_key ) {
			continue;
		}

		$sanitized[] = $field;
	}

	$group_order = array(
		'behavior'   => 'Link behavior',
		'attributes' => 'Link attributes',
		'icons'      => 'Icon settings',
		'appearance' => 'Appearance',
		'advanced'   => 'Additional settings',
	);
	$grouped     = array();

	foreach ( array_keys( $group_order ) as $group_key ) {
		$grouped[ $group_key ] = array();
	}

	foreach ( $sanitized as $field ) {
		if ( ! is_array( $field ) ) {
			$grouped['advanced'][] = $field;
			continue;
		}

		$field_type = isset( $field['type'] ) ? sanitize_key( (string) $field['type'] ) : '';
		if ( 'tab' === $field_type ) {
			continue;
		}

		$group_key = mrn_base_stack_get_repeater_config_field_group_key( $field );
		if ( '' === $group_key || ! isset( $grouped[ $group_key ] ) ) {
			$group_key = 'advanced';
		}

		$grouped[ $group_key ][] = $field;
	}

	$has_group_content = false;
	foreach ( $grouped as $group_fields ) {
		if ( ! empty( $group_fields ) ) {
			$has_group_content = true;
			break;
		}
	}

	if ( empty( $grouped['advanced'] ) ) {
		$grouped['advanced'][] = mrn_base_stack_get_contract_future_use_message_field( $group_prefix . 'advanced_future_use' );
		$has_group_content     = true;
	}

	if ( ! $has_group_content ) {
		return $fields;
	}

	$grouped_segment = array();
	$is_first_group  = true;
	foreach ( $group_order as $group_key => $group_label ) {
		$group_fields = $grouped[ $group_key ];
		if ( empty( $group_fields ) ) {
			continue;
		}

		$grouped_segment[] = array(
			'key'          => $group_prefix . $group_key,
			'label'        => $group_label,
			'name'         => '',
			'aria-label'   => '',
			'type'         => 'accordion',
			'open'         => $is_first_group ? 1 : 0,
			'multi_expand' => 1,
			'endpoint'     => 0,
		);

		foreach ( $group_fields as $group_field ) {
			$grouped_segment[] = $group_field;
		}

		$is_first_group = false;
	}

	$grouped_segment[] = array(
		'key'          => $group_prefix . 'end',
		'label'        => '',
		'name'         => '',
		'aria-label'   => '',
		'type'         => 'accordion',
		'endpoint'     => 1,
		'multi_expand' => 1,
	);

	array_splice( $fields, $segment_start, $segment_len, $grouped_segment );

	return $fields;
}

/**
 * Expand legacy bare repeater `link` fields into the shared link contract.
 *
 * This keeps the original ACF key for the `link` field where possible so any
 * existing collapse targets and editor state continue to work.
 *
 * @param array<int, mixed> $fields Repeater sub-fields.
 * @param string            $repeater_key Repeater ACF key.
 * @return array<int, mixed>
 */
function mrn_base_stack_expand_repeater_legacy_link_to_contract( array $fields, $repeater_key = '' ) {
	if ( ! function_exists( 'mrn_rbl_get_content_link_contract_sub_fields' ) ) {
		return $fields;
	}

	$has_link_contract_fields = false;
	$legacy_link_index        = null;
	$legacy_link_field        = array();

	foreach ( $fields as $index => $field ) {
		if ( ! is_array( $field ) ) {
			continue;
		}

		$field_name = isset( $field['name'] ) ? sanitize_key( (string) $field['name'] ) : '';
		$field_type = isset( $field['type'] ) ? sanitize_key( (string) $field['type'] ) : '';

		if ( in_array( $field_name, array( 'is_button', 'css_classes', 'target', 'rel', 'title_attribute', 'download', 'hreflang', 'media' ), true ) || 0 === strpos( $field_name, 'link_icon_' ) ) {
			$has_link_contract_fields = true;
		}

		if ( null !== $legacy_link_index ) {
			continue;
		}

		if ( 'link' === $field_type && 'link' === $field_name ) {
			$legacy_link_index = $index;
			$legacy_link_field = $field;
		}
	}

	if ( $has_link_contract_fields || null === $legacy_link_index ) {
		return $fields;
	}

	$key_seed = sanitize_key( (string) $repeater_key );
	if ( '' === $key_seed ) {
		$key_seed = 'field_mrn_repeater_item';
	}

	$link_field_key = isset( $legacy_link_field['key'] ) && is_string( $legacy_link_field['key'] ) ? trim( $legacy_link_field['key'] ) : '';
	if ( '' === $link_field_key ) {
		$link_field_key = $key_seed . '_link';
	}

	$contract_fields = mrn_rbl_get_content_link_contract_sub_fields(
		$key_seed . '_link_contract',
		array(
			'include_tabs'   => true,
			'link_field_key' => $link_field_key,
			'link_tab_key'   => $key_seed . '_link_tab_contract',
			'config_tab_key' => $key_seed . '_link_configs_tab_contract',
		)
	);

	if ( empty( $contract_fields ) ) {
		return $fields;
	}

	array_splice( $fields, $legacy_link_index, 1, $contract_fields );

	return $fields;
}

/**
 * Check whether a field name belongs to the shared flat link contract.
 *
 * @param string $field_name Field name.
 * @return bool
 */
function mrn_base_stack_is_flat_link_contract_field_name( $field_name ) {
	$field_name = sanitize_key( (string) $field_name );

	if ( '' === $field_name ) {
		return false;
	}

	if ( 0 === strpos( $field_name, 'link_icon_' ) ) {
		return true;
	}

	return in_array(
		$field_name,
		array(
			'link',
			'is_button',
			'css_classes',
			'target',
			'rel',
			'title_attribute',
			'download',
			'hreflang',
			'media',
		),
		true
	);
}

/**
 * Normalize repeater-item link UI to a single links repeater at the end of Content.
 *
 * Legacy flat link contract fields are removed from the row-level tab strip and
 * replaced by a `links` repeater so link-specific tabs remain scoped to the
 * link item UI rather than appearing as top-level row tabs.
 *
 * @param array<int, mixed> $fields Repeater sub-fields.
 * @param string            $key_seed Repeater key seed.
 * @param string            $repeater_name Repeater field name.
 * @return array<int, mixed>
 */
function mrn_base_stack_ensure_repeater_item_links_repeater_contract( array $fields, $key_seed, $repeater_name = '' ) {
	$repeater_name = sanitize_key( (string) $repeater_name );

	if ( ! function_exists( 'mrn_rbl_get_content_link_repeater_field' ) ) {
		return $fields;
	}

	$key_seed               = sanitize_key( (string) $key_seed );
	$normalized             = array();
	$links_field            = null;
	$has_flat_link_contract = false;
	$in_link_tab_segment    = false;

	foreach ( $fields as $field ) {
		if ( ! is_array( $field ) ) {
			$normalized[] = $field;
			continue;
		}

		$field_type  = isset( $field['type'] ) ? sanitize_key( (string) $field['type'] ) : '';
		$field_name  = isset( $field['name'] ) ? sanitize_key( (string) $field['name'] ) : '';
		$field_label = isset( $field['label'] ) ? sanitize_title( (string) $field['label'] ) : '';

		if ( 'repeater' === $field_type && 'links' === $field_name ) {
			$links_field = $field;
			continue;
		}

		if ( 'tab' === $field_type ) {
			if ( 'link' === $field_label ) {
				$in_link_tab_segment    = true;
				$has_flat_link_contract = true;
				continue;
			}

			if ( $in_link_tab_segment && 'configs' === $field_label ) {
				$has_flat_link_contract = true;
				continue;
			}

			$in_link_tab_segment = false;
		}

		if ( mrn_base_stack_is_flat_link_contract_field_name( $field_name ) ) {
			$has_flat_link_contract = true;
			continue;
		}

		$normalized[] = $field;
	}

	if ( null === $links_field && ! $has_flat_link_contract ) {
		return $fields;
	}

	if ( null === $links_field ) {
		$links_field = mrn_rbl_get_content_link_repeater_field( $key_seed . '_links', 'Link repeater', 'links', 1 );
	}

	if ( ! is_array( $links_field ) ) {
		return $normalized;
	}

	$links_field['label']  = 'Link repeater';
	$links_field['name']   = 'links';
	$links_field['layout'] = 'block';
	$links_field['max']    = 1;
	if ( 'showcase_items' === $repeater_name ) {
		$links_field['label'] = 'Link';
	}

	$content_tab_index = null;
	$insert_index      = null;

	foreach ( $normalized as $index => $field ) {
		if ( ! is_array( $field ) ) {
			continue;
		}

		$field_type  = isset( $field['type'] ) ? sanitize_key( (string) $field['type'] ) : '';
		$field_label = isset( $field['label'] ) ? sanitize_title( (string) $field['label'] ) : '';

		if ( 'tab' !== $field_type ) {
			continue;
		}

		if ( null === $content_tab_index && 'content' === $field_label ) {
			$content_tab_index = $index;
			continue;
		}

		if ( null !== $content_tab_index && $index > $content_tab_index ) {
			$insert_index = $index;
			break;
		}
	}

	if ( null === $insert_index ) {
		$normalized[] = $links_field;
	} else {
		array_splice( $normalized, $insert_index, 0, array( $links_field ) );
	}

	return $normalized;
}

/**
 * Resolve the functionality group for a main-row config field.
 *
 * @param array<string, mixed> $field ACF field definition.
 * @return string
 */
function mrn_base_stack_get_main_config_field_group_key( array $field ) {
	$field_name = isset( $field['name'] ) ? sanitize_key( (string) $field['name'] ) : '';
	$field_type = isset( $field['type'] ) ? sanitize_key( (string) $field['type'] ) : '';

	if ( '' === $field_name ) {
		return '';
	}

	if ( in_array( $field_name, array( 'section_width', 'sub_content_width' ), true ) ) {
		return 'appearance';
	}

	if ( in_array( $field_name, array( 'anchor', 'anchor_id' ), true ) ) {
		return 'layout';
	}

	if ( in_array( $field_name, array( 'background_color', 'bg_color' ), true ) || 0 === strpos( $field_name, 'background_' ) ) {
		return 'appearance';
	}

	if ( in_array( $field_name, array( 'accent', 'bottom_accent', 'bottom_accent_style' ), true ) || 0 === strpos( $field_name, 'accent_' ) ) {
		return 'accent';
	}

	if ( 0 === strpos( $field_name, 'link_' ) || in_array( $field_name, array( 'is_button', 'css_classes', 'target', 'rel', 'title_attribute', 'download', 'hreflang', 'media' ), true ) ) {
		return 'links';
	}

	if (
		false !== strpos( $field_name, 'column' )
		|| false !== strpos( $field_name, 'ratio' )
		|| false !== strpos( $field_name, 'orientation' )
		|| false !== strpos( $field_name, 'autoplay' )
		|| false !== strpos( $field_name, 'delay' )
		|| false !== strpos( $field_name, 'time_on_slide' )
		|| false !== strpos( $field_name, 'hover' )
		|| false !== strpos( $field_name, 'stagger' )
		|| false !== strpos( $field_name, 'display_mode' )
		|| false !== strpos( $field_name, 'equal' )
		|| false !== strpos( $field_name, 'full' )
		|| false !== strpos( $field_name, 'position' )
		|| false !== strpos( $field_name, 'size' )
		|| false !== strpos( $field_name, 'alignment' )
		|| false !== strpos( $field_name, 'per_page' )
		|| 0 === strpos( $field_name, 'show_' )
	) {
		return 'layout';
	}

	if ( in_array( $field_type, array( 'true_false', 'select', 'number', 'range', 'radio', 'button_group' ), true ) ) {
		return 'layout';
	}

	return 'advanced';
}

/**
 * Determine whether an ACF field is a row-width control.
 *
 * @param array<string, mixed> $field ACF field definition.
 * @return bool
 */
function mrn_base_stack_is_row_width_control_field( array $field ) {
	$field_name = isset( $field['name'] ) ? sanitize_key( (string) $field['name'] ) : '';

	return in_array( $field_name, array( 'section_width', 'sub_content_width', 'full_width' ), true );
}

/**
 * Ensure layouts with row-level width controls expose sub-content width.
 *
 * @param array<int, mixed> $fields Layout/main field definitions.
 * @return array<int, mixed>
 */
function mrn_base_stack_ensure_sub_content_width_field( array $fields ) {
	$has_sub_content_width = false;
	$has_section_width     = false;
	$insert_after_index    = null;
	$config_tab_index      = null;
	$next_tab_index        = null;
	$seed                  = '';
	$total_fields          = count( $fields );

	foreach ( $fields as $index => $field ) {
		if ( ! is_array( $field ) ) {
			continue;
		}

		$field_key   = isset( $field['key'] ) ? sanitize_key( (string) $field['key'] ) : '';
		$field_name  = isset( $field['name'] ) ? sanitize_key( (string) $field['name'] ) : '';
		$field_type  = isset( $field['type'] ) ? sanitize_key( (string) $field['type'] ) : '';
		$field_label = isset( $field['label'] ) ? sanitize_title( (string) $field['label'] ) : '';

		if ( '' === $seed && '' !== $field_key ) {
			$seed = $field_key;
		}

		if ( 'sub_content_width' === $field_name ) {
			$has_sub_content_width = true;
			break;
		}

		if ( 'section_width' === $field_name ) {
			$has_section_width  = true;
			$insert_after_index = $index;
			if ( '' !== $field_key ) {
				$seed = $field_key;
			}
		}

		if ( 'tab' === $field_type && 'configs' === $field_label && null === $config_tab_index ) {
			$config_tab_index = $index;
			continue;
		}

		if ( null !== $config_tab_index && 'tab' === $field_type && $index > $config_tab_index ) {
			$next_tab_index = $index;
			break;
		}
	}

	if ( $has_sub_content_width ) {
		return $fields;
	}

	if ( ! $has_section_width ) {
		return $fields;
	}

	if ( '' === $seed ) {
		$seed = 'field_mrn_layout_sub_content_width';
	}

	if ( null === $insert_after_index ) {
		if ( null !== $config_tab_index ) {
			$insert_after_index = $config_tab_index;
		} else {
			$insert_after_index = $total_fields - 1;
		}
	}

	if ( null !== $next_tab_index && $insert_after_index >= $next_tab_index ) {
		$insert_after_index = $next_tab_index - 1;
	}

	array_splice(
		$fields,
		$insert_after_index + 1,
		0,
		array(
			mrn_base_stack_get_sub_content_width_field(
				$seed . '_sub_content_width',
				'sub_content_width',
				'Section Width (Sub-content)'
			),
		)
	);

	return $fields;
}

/**
 * Ensure row-width controls always have a Configs tab anchor.
 *
 * Some cloned layouts surface row-level controls (for example section width)
 * without defining a local Configs tab, which can cause those controls to
 * inherit the prior tab context visually.
 *
 * @param array<int, mixed> $fields Layout/main field definitions.
 * @param string            $key_seed Optional key seed for generated field keys.
 * @return array<int, mixed>
 */
function mrn_base_stack_ensure_main_configs_tab_for_row_width_fields( array $fields, $key_seed = '' ) {
	$has_configs_tab         = false;
	$first_width_field_index = null;
	$seed                    = sanitize_key( (string) $key_seed );

	foreach ( $fields as $index => $field ) {
		if ( ! is_array( $field ) ) {
			continue;
		}

		$field_key   = isset( $field['key'] ) ? sanitize_key( (string) $field['key'] ) : '';
		$field_type  = isset( $field['type'] ) ? sanitize_key( (string) $field['type'] ) : '';
		$field_label = isset( $field['label'] ) ? sanitize_title( (string) $field['label'] ) : '';

		if ( '' === $seed && '' !== $field_key ) {
			$seed = $field_key;
		}

		if ( 'tab' === $field_type && 'configs' === $field_label ) {
			$has_configs_tab = true;
			break;
		}

		if ( null === $first_width_field_index && mrn_base_stack_is_row_width_control_field( $field ) ) {
			$first_width_field_index = $index;
		}
	}

	if ( $has_configs_tab || null === $first_width_field_index ) {
		return $fields;
	}

	if ( '' === $seed ) {
		$seed = 'field_mrn_layout_config';
	}

	array_splice(
		$fields,
		$first_width_field_index,
		0,
		array(
			array(
				'key'        => $seed . '_configs_tab_contract',
				'label'      => 'Configs',
				'name'       => '',
				'aria-label' => '',
				'type'       => 'tab',
				'placement'  => 'top',
				'endpoint'   => 0,
			),
		)
	);

	return $fields;
}

/**
 * Move row-width controls into the Configs segment when they drift outside it.
 *
 * @param array<int, mixed> $fields Layout/main field definitions.
 * @return array<int, mixed>
 */
function mrn_base_stack_move_row_width_fields_into_configs_segment( array $fields ) {
	$config_tab_index = null;
	$next_tab_index   = null;
	$total_fields     = count( $fields );

	foreach ( $fields as $index => $field ) {
		if ( ! is_array( $field ) ) {
			continue;
		}

		$field_type  = isset( $field['type'] ) ? sanitize_key( (string) $field['type'] ) : '';
		$field_label = isset( $field['label'] ) ? sanitize_title( (string) $field['label'] ) : '';

		if ( 'tab' !== $field_type ) {
			continue;
		}

		if ( null === $config_tab_index && 'configs' === $field_label ) {
			$config_tab_index = $index;
			continue;
		}

		if ( null !== $config_tab_index && $index > $config_tab_index ) {
			$next_tab_index = $index;
			break;
		}
	}

	if ( null === $config_tab_index ) {
		return $fields;
	}

	$segment_start = $config_tab_index + 1;
	$segment_end   = null !== $next_tab_index ? $next_tab_index : $total_fields;
	$width_fields  = array();

	foreach ( $fields as $index => $field ) {
		if ( ! is_array( $field ) || ! mrn_base_stack_is_row_width_control_field( $field ) ) {
			continue;
		}

		if ( $index >= $segment_start && $index < $segment_end ) {
			continue;
		}

		$width_fields[] = $field;
		unset( $fields[ $index ] );
	}

	if ( empty( $width_fields ) ) {
		return $fields;
	}

	$fields           = array_values( $fields );
	$config_tab_index = null;

	foreach ( $fields as $index => $field ) {
		if ( ! is_array( $field ) ) {
			continue;
		}

		$field_type  = isset( $field['type'] ) ? sanitize_key( (string) $field['type'] ) : '';
		$field_label = isset( $field['label'] ) ? sanitize_title( (string) $field['label'] ) : '';
		if ( 'tab' === $field_type && 'configs' === $field_label ) {
			$config_tab_index = $index;
			break;
		}
	}

	if ( null === $config_tab_index ) {
		return $fields;
	}

	array_splice( $fields, $config_tab_index + 1, 0, $width_fields );

	return $fields;
}

/**
 * Group main-row Configs controls by functionality with collapsed accordions.
 *
 * @param array<int, mixed> $fields Layout/main field definitions.
 * @param string            $key_seed Optional key seed for generated accordion keys.
 * @return array<int, mixed>
 */
function mrn_base_stack_group_main_config_fields_by_functionality( array $fields, $key_seed = '' ) {
	$fields = mrn_base_stack_ensure_main_configs_tab_for_row_width_fields( $fields, $key_seed );
	$fields = mrn_base_stack_move_row_width_fields_into_configs_segment( $fields );

	$config_tab_index = null;
	$next_tab_index   = null;
	$total_fields     = count( $fields );
	$seed             = sanitize_key( (string) $key_seed );

	foreach ( $fields as $index => $field ) {
		if ( ! is_array( $field ) ) {
			continue;
		}

		$field_type  = isset( $field['type'] ) ? sanitize_key( (string) $field['type'] ) : '';
		$field_label = isset( $field['label'] ) ? sanitize_title( (string) $field['label'] ) : '';
		$field_key   = isset( $field['key'] ) ? sanitize_key( (string) $field['key'] ) : '';

		if ( '' === $seed && '' !== $field_key ) {
			$seed = $field_key;
		}

		if ( 'tab' !== $field_type ) {
			continue;
		}

		if ( null === $config_tab_index && 'configs' === $field_label ) {
			$config_tab_index = $index;

			if ( '' !== $field_key ) {
				$seed = $field_key;
			}
			continue;
		}

		if ( null !== $config_tab_index && $index > $config_tab_index ) {
			$next_tab_index = $index;
			break;
		}
	}

	if ( null === $config_tab_index ) {
		return $fields;
	}

	if ( '' === $seed ) {
		$seed = 'field_mrn_layout_config';
	}

	$segment_start = $config_tab_index + 1;
	$segment_end   = null !== $next_tab_index ? $next_tab_index : $total_fields;
	$segment_len   = max( 0, $segment_end - $segment_start );

	if ( $segment_len < 1 ) {
		return $fields;
	}

	$config_fields = array_slice( $fields, $segment_start, $segment_len );
	$group_prefix  = $seed . '_cfg_main_group_';
	$sanitized     = array();

	foreach ( $config_fields as $field ) {
		if ( ! is_array( $field ) ) {
			continue;
		}

		$field_key  = isset( $field['key'] ) ? sanitize_key( (string) $field['key'] ) : '';
		$field_type = isset( $field['type'] ) ? sanitize_key( (string) $field['type'] ) : '';

		if ( 'accordion' === $field_type && '' !== $field_key && 0 === strpos( $field_key, $group_prefix ) ) {
			continue;
		}

		if ( 'message' === $field_type && $group_prefix . 'advanced_future_use' === $field_key ) {
			continue;
		}

		$sanitized[] = $field;
	}

	$group_order = array(
		'layout'     => 'Basic Setting',
		'appearance' => 'Appearance',
		'accent'     => 'Accent settings',
		'links'      => 'Link settings',
		'advanced'   => 'Additional settings',
	);
	$grouped     = array();

	foreach ( array_keys( $group_order ) as $group_key ) {
		$grouped[ $group_key ] = array();
	}

	foreach ( $sanitized as $field ) {
		if ( ! is_array( $field ) ) {
			$grouped['advanced'][] = $field;
			continue;
		}

		$field_type = isset( $field['type'] ) ? sanitize_key( (string) $field['type'] ) : '';
		if ( in_array( $field_type, array( 'tab', 'accordion' ), true ) ) {
			continue;
		}

		$group_key = mrn_base_stack_get_main_config_field_group_key( $field );
		if ( '' === $group_key || ! isset( $grouped[ $group_key ] ) ) {
			$group_key = 'advanced';
		}

		$grouped[ $group_key ][] = $field;
	}

	$has_group_content = false;
	foreach ( $grouped as $group_fields ) {
		if ( ! empty( $group_fields ) ) {
			$has_group_content = true;
			break;
		}
	}

	if ( empty( $grouped['advanced'] ) ) {
		$grouped['advanced'][] = mrn_base_stack_get_contract_future_use_message_field( $group_prefix . 'advanced_future_use' );
		$has_group_content     = true;
	}

	if ( ! $has_group_content ) {
		return $fields;
	}

	$grouped_segment = array();
	foreach ( $group_order as $group_key => $group_label ) {
		$group_fields = $grouped[ $group_key ];
		if ( empty( $group_fields ) ) {
			continue;
		}

		$grouped_segment[] = array(
			'key'          => $group_prefix . $group_key,
			'label'        => $group_label,
			'name'         => '',
			'aria-label'   => '',
			'type'         => 'accordion',
			'open'         => 0,
			'multi_expand' => 1,
			'endpoint'     => 0,
		);

		foreach ( $group_fields as $group_field ) {
			$grouped_segment[] = $group_field;
		}
	}

	$grouped_segment[] = array(
		'key'          => $group_prefix . 'end',
		'label'        => '',
		'name'         => '',
		'aria-label'   => '',
		'type'         => 'accordion',
		'endpoint'     => 1,
		'multi_expand' => 1,
	);

	array_splice( $fields, $segment_start, $segment_len, $grouped_segment );

	return $fields;
}

/**
 * Ensure target repeater items use shared Content|Configs tabs and config controls.
 *
 * @param array<int, mixed> $fields Repeater sub-fields.
 * @param string            $repeater_name Repeater field name.
 * @param string            $repeater_key Repeater field key.
 * @return array<int, mixed>
 */
function mrn_base_stack_apply_repeater_item_tabs_and_config_contract( array $fields, $repeater_name, $repeater_key = '' ) {
	if ( ! mrn_base_stack_repeater_uses_primary_item_contract( $repeater_name ) ) {
		return $fields;
	}

	$key_seed = sanitize_key( (string) $repeater_key );
	if ( '' === $key_seed ) {
		$key_seed = 'field_mrn_repeater_item';
	}

	$fields = mrn_base_stack_expand_repeater_legacy_link_to_contract( $fields, $repeater_key );
	$fields = mrn_base_stack_ensure_repeater_item_links_repeater_contract( $fields, $key_seed, $repeater_name );
	$fields = mrn_base_stack_ensure_tabs_repeater_primary_content_contract( $fields, $repeater_name, $repeater_key );

	$background_field = null;
	foreach ( $fields as $index => $field ) {
		if ( ! is_array( $field ) ) {
			continue;
		}

		$field_type = isset( $field['type'] ) ? sanitize_key( (string) $field['type'] ) : '';
		$field_name = isset( $field['name'] ) ? sanitize_key( (string) $field['name'] ) : '';

		if ( 'background_color' !== $field_name || 'select' !== $field_type ) {
			continue;
		}

		if ( null === $background_field ) {
			$background_field = $field;
		}

		unset( $fields[ $index ] );
	}
	$fields = array_values( $fields );

	$content_tab_index    = null;
	$row_config_tab_index = null;

	foreach ( $fields as $index => $field ) {
		if ( ! is_array( $field ) ) {
			continue;
		}

		$field_type  = isset( $field['type'] ) ? sanitize_key( (string) $field['type'] ) : '';
		$field_label = isset( $field['label'] ) ? sanitize_title( (string) $field['label'] ) : '';
		$field_key   = isset( $field['key'] ) ? sanitize_key( (string) $field['key'] ) : '';

		if ( 'tab' !== $field_type ) {
			continue;
		}

		if ( null === $content_tab_index && 'content' === $field_label ) {
			$content_tab_index = $index;
		}

		if ( null === $row_config_tab_index && 'configs' === $field_label && false === strpos( $field_key, 'link_configs_tab_contract' ) ) {
			$row_config_tab_index = $index;
		}
	}

	if ( null === $content_tab_index ) {
		array_unshift(
			$fields,
			array(
				'key'       => $key_seed . '_content_tab',
				'label'     => 'Content',
				'name'      => '',
				'type'      => 'tab',
				'placement' => 'top',
				'endpoint'  => 0,
			)
		);
	}

	if ( null === $row_config_tab_index ) {
		$fields[] = array(
			'key'       => $key_seed . '_config_tab',
			'label'     => 'Configs',
			'name'      => '',
			'type'      => 'tab',
			'placement' => 'top',
			'endpoint'  => 0,
		);
	}

	if ( null === $background_field ) {
		$background_field = array(
			'key'          => $key_seed . '_background_color',
			'label'        => 'Background Color',
			'name'         => 'background_color',
			'aria-label'   => '',
			'type'         => 'select',
			'choices'      => function_exists( 'mrn_rbl_get_site_color_choices' ) ? mrn_rbl_get_site_color_choices() : array(),
			'ui'           => 1,
			'allow_null'   => 1,
			'instructions' => 'Select from Site Colors when available.',
			'wrapper'      => array(
				'width' => '50',
			),
		);
	}

	$row_config_tab_index = null;
	foreach ( $fields as $index => $field ) {
		if ( ! is_array( $field ) ) {
			continue;
		}

		$field_type  = isset( $field['type'] ) ? sanitize_key( (string) $field['type'] ) : '';
		$field_label = isset( $field['label'] ) ? sanitize_title( (string) $field['label'] ) : '';
		$field_key   = isset( $field['key'] ) ? sanitize_key( (string) $field['key'] ) : '';

		if ( 'tab' === $field_type && 'configs' === $field_label && false === strpos( $field_key, 'link_configs_tab_contract' ) ) {
			$row_config_tab_index = $index;
			break;
		}
	}

	if ( null === $row_config_tab_index ) {
		$fields[] = $background_field;
	} else {
		array_splice( $fields, $row_config_tab_index + 1, 0, array( $background_field ) );
	}

	/*
	 * Repeater-item contracts keep row effects in a dedicated Effects tab.
	 */
	$effects_tab   = null;
	$effect_fields = array();
	foreach ( $fields as $index => $field ) {
		if ( ! is_array( $field ) ) {
			continue;
		}

		$field_type  = isset( $field['type'] ) ? sanitize_key( (string) $field['type'] ) : '';
		$field_name  = isset( $field['name'] ) ? sanitize_key( (string) $field['name'] ) : '';
		$field_label = isset( $field['label'] ) ? sanitize_title( (string) $field['label'] ) : '';

		if ( null === $effects_tab && 'tab' === $field_type && 'effects' === $field_label ) {
			$effects_tab = $field;
			unset( $fields[ $index ] );
			continue;
		}

		if ( 'enable_row_effects' === $field_name ) {
			if ( empty( $effect_fields ) ) {
				$effect_fields[] = $field;
			}
			unset( $fields[ $index ] );
		}
	}
	$fields = array_values( $fields );

	if ( null === $effects_tab ) {
		$effects_tab = array(
			'key'        => $key_seed . '_effects_tab',
			'label'      => 'Effects',
			'name'       => '',
			'aria-label' => '',
			'type'       => 'tab',
			'placement'  => 'top',
			'endpoint'   => 0,
		);
	}

	if ( empty( $effect_fields ) ) {
		$effect_fields[] = array(
			'key'           => $key_seed . '_enable_row_effects',
			'label'         => 'Enable Row Effects',
			'name'          => 'enable_row_effects',
			'aria-label'    => '',
			'type'          => 'true_false',
			'ui'            => 1,
			'default_value' => 0,
			'ui_on_text'    => 'On',
			'ui_off_text'   => 'Off',
			'wrapper'       => array(
				'width' => '50',
			),
		);
	}

	$fields[] = $effects_tab;
	array_splice( $fields, count( $fields ), 0, $effect_fields );

	$fields = mrn_base_stack_group_repeater_config_fields_by_functionality( $fields, $key_seed );
	$fields = mrn_base_stack_ensure_acf_field_origin_names( $fields );

	return $fields;
}

/**
 * Apply the primary repeater-item contract when ACF loads repeater fields.
 *
 * This ensures clone-derived repeater fields receive the same item contract
 * normalization as directly registered layout fields.
 *
 * @param array<string, mixed>|mixed $field ACF field definition.
 * @return array<string, mixed>|mixed
 */
function mrn_base_stack_apply_primary_repeater_item_contract_on_load( $field ) {
	if ( ! is_array( $field ) ) {
		return $field;
	}

	$field_type = isset( $field['type'] ) ? sanitize_key( (string) $field['type'] ) : '';
	$field_name = isset( $field['name'] ) ? sanitize_key( (string) $field['name'] ) : '';

	if ( 'repeater' !== $field_type || ! mrn_base_stack_repeater_uses_primary_item_contract( $field_name ) ) {
		return $field;
	}

	if ( ! isset( $field['sub_fields'] ) || ! is_array( $field['sub_fields'] ) ) {
		return $field;
	}

	$field_key = isset( $field['key'] ) && is_string( $field['key'] ) ? trim( $field['key'] ) : '';

	$field['sub_fields'] = mrn_base_stack_apply_primary_layout_field_contract( $field['sub_fields'], false );
	$field['sub_fields'] = mrn_base_stack_ensure_repeater_subheading_contract( $field['sub_fields'], $field_key, $field_name );
	$field['sub_fields'] = mrn_base_stack_apply_repeater_item_tabs_and_config_contract( $field['sub_fields'], $field_name, $field_key );
	$field['sub_fields'] = mrn_base_stack_ensure_acf_field_origin_names( $field['sub_fields'] );

	return $field;
}
add_filter( 'acf/load_field/type=repeater', 'mrn_base_stack_apply_primary_repeater_item_contract_on_load', 30 );

/**
 * Recursively apply the shared primary-layout field contract.
 *
 * @param array<int, mixed> $fields ACF field definitions.
 * @param bool              $inject_internal_name Whether to inject the editor-only internal name field.
 * @return array<int, mixed>
 */
function mrn_base_stack_apply_primary_layout_field_contract( array $fields, $inject_internal_name = true ) {
	$normalized_fields = array();

	foreach ( $fields as $field ) {
		if ( ! is_array( $field ) ) {
			$normalized_fields[] = $field;
			continue;
		}

		if ( isset( $field['sub_fields'] ) && is_array( $field['sub_fields'] ) ) {
			$field['sub_fields'] = mrn_base_stack_apply_primary_layout_field_contract( $field['sub_fields'], false );

			$field_type = isset( $field['type'] ) ? sanitize_key( (string) $field['type'] ) : '';
			$field_name = isset( $field['name'] ) ? sanitize_key( (string) $field['name'] ) : '';
			$field_key  = isset( $field['key'] ) && is_string( $field['key'] ) ? trim( $field['key'] ) : '';

			if ( 'clone' === $field_type ) {
				$field['sub_fields'] = mrn_base_stack_ensure_sub_content_width_field( $field['sub_fields'] );
				$field['sub_fields'] = mrn_base_stack_group_main_config_fields_by_functionality( $field['sub_fields'], $field_key );
			}

			if ( 'repeater' === $field_type && 'links' !== $field_name ) {
				$field['sub_fields'] = mrn_base_stack_ensure_repeater_subheading_contract( $field['sub_fields'], $field_key, $field_name );
				$field['sub_fields'] = mrn_base_stack_apply_repeater_item_tabs_and_config_contract( $field['sub_fields'], $field_name, $field_key );
			}
		}

		if ( isset( $field['fields'] ) && is_array( $field['fields'] ) ) {
			$field['fields'] = mrn_base_stack_apply_primary_layout_field_contract( $field['fields'], false );
		}

		if ( isset( $field['layouts'] ) && is_array( $field['layouts'] ) ) {
			foreach ( $field['layouts'] as $layout_key => $layout ) {
				if ( ! is_array( $layout ) ) {
					continue;
				}

				if ( isset( $layout['sub_fields'] ) && is_array( $layout['sub_fields'] ) ) {
					$layout['sub_fields'] = mrn_base_stack_apply_primary_layout_field_contract( $layout['sub_fields'], true );
				}

				$field['layouts'][ $layout_key ] = $layout;
			}
		}

		$normalized_fields[] = mrn_base_stack_normalize_primary_layout_field( $field );
	}

	$normalized_fields = mrn_base_stack_apply_tag_field_column_layout( $normalized_fields );
	if ( $inject_internal_name ) {
		$normalized_fields = mrn_base_stack_ensure_sub_content_width_field( $normalized_fields );
		$normalized_fields = mrn_base_stack_group_main_config_fields_by_functionality( $normalized_fields );
	}

	if ( ! $inject_internal_name ) {
		return mrn_base_stack_ensure_acf_field_origin_names( $normalized_fields );
	}

	$contains_reusable_group_clone = false;

	foreach ( $normalized_fields as $field ) {
		if ( ! is_array( $field ) ) {
			continue;
		}

		$field_type = isset( $field['type'] ) ? sanitize_key( (string) $field['type'] ) : '';
		if ( 'clone' !== $field_type || ! isset( $field['clone'] ) || ! is_array( $field['clone'] ) ) {
			continue;
		}

		foreach ( $field['clone'] as $clone_target ) {
			$clone_key = is_string( $clone_target ) ? sanitize_key( $clone_target ) : '';
			if ( '' !== $clone_key && 0 === strpos( $clone_key, 'group_mrn_reusable_' ) ) {
				$contains_reusable_group_clone = true;
				break 2;
			}
		}
	}

	if ( $contains_reusable_group_clone ) {
		return mrn_base_stack_ensure_acf_field_origin_names( $normalized_fields );
	}

	$content_tab_index = null;
	$first_field_index = null;
	$internal_name_key = 'field_mrn_layout_internal_name';
	$has_internal_name = false;

	foreach ( $normalized_fields as $index => $field ) {
		if ( ! is_array( $field ) ) {
			continue;
		}

		$field_name = isset( $field['name'] ) ? sanitize_key( (string) $field['name'] ) : '';
		$field_type = isset( $field['type'] ) ? sanitize_key( (string) $field['type'] ) : '';
		$field_key  = isset( $field['key'] ) && is_string( $field['key'] ) ? trim( $field['key'] ) : '';

		if ( null === $first_field_index ) {
			$first_field_index = $index;

			if ( '' !== $field_key ) {
				$internal_name_key = sanitize_key( $field_key ) . '_internal_name';
			}
		}

		if ( 'internal_name' === $field_name ) {
			$has_internal_name = true;
		}

		if ( null !== $content_tab_index || 'tab' !== $field_type ) {
			continue;
		}

		$field_label = isset( $field['label'] ) ? sanitize_title( (string) $field['label'] ) : '';
		if ( 'content' !== $field_label ) {
			continue;
		}

		$content_tab_index = $index;

		if ( '' !== $field_key ) {
			$internal_name_key = sanitize_key( $field_key ) . '_internal_name';
		}
	}

	if ( ! $has_internal_name ) {
		$insert_index = null !== $content_tab_index ? $content_tab_index + 1 : ( null !== $first_field_index ? $first_field_index : 0 );
		array_splice( $normalized_fields, $insert_index, 0, array( mrn_base_stack_get_internal_layout_name_field( $internal_name_key ) ) );
	}

	return mrn_base_stack_ensure_acf_field_origin_names( $normalized_fields );
}

/**
 * Recursively move row effect controls into a dedicated Effects tab.
 *
 * This preserves existing motion field keys/names and only changes their tab
 * placement in row editors that already use top-level tabs.
 *
 * @param array<int, mixed> $fields Field definitions.
 * @return array<int, mixed>
 */
function mrn_base_stack_relocate_effect_fields( array $fields ) {
	$processed_fields = array();

	foreach ( $fields as $field ) {
		if ( ! is_array( $field ) ) {
			$processed_fields[] = $field;
			continue;
		}

		if ( isset( $field['sub_fields'] ) && is_array( $field['sub_fields'] ) ) {
			$field['sub_fields'] = mrn_base_stack_relocate_effect_fields( $field['sub_fields'] );
		}

		if ( isset( $field['fields'] ) && is_array( $field['fields'] ) ) {
			$field['fields'] = mrn_base_stack_relocate_effect_fields( $field['fields'] );
		}

		if ( isset( $field['layouts'] ) && is_array( $field['layouts'] ) ) {
			foreach ( $field['layouts'] as $layout_key => $layout ) {
				if ( ! is_array( $layout ) ) {
					continue;
				}

				if ( isset( $layout['sub_fields'] ) && is_array( $layout['sub_fields'] ) ) {
					$layout['sub_fields'] = mrn_base_stack_relocate_effect_fields( $layout['sub_fields'] );
				}

				$field['layouts'][ $layout_key ] = $layout;
			}
		}

		$processed_fields[] = $field;
	}

	$has_tabs       = false;
	$effects_fields = array();
	$remaining      = array();

	foreach ( $processed_fields as $field ) {
		if ( ! is_array( $field ) ) {
			$remaining[] = $field;
			continue;
		}

		$field_type  = isset( $field['type'] ) ? (string) $field['type'] : '';
		$field_name  = isset( $field['name'] ) ? (string) $field['name'] : '';
		$field_label = isset( $field['label'] ) ? (string) $field['label'] : '';

		if ( 'tab' === $field_type ) {
			$has_tabs = true;

			if ( 'effects' === sanitize_title( $field_label ) ) {
				continue;
			}
		}

		if ( 'motion_settings' === $field_name || in_array( $field_name, mrn_base_stack_get_effects_tab_field_names(), true ) ) {
			$effects_fields[] = $field;
			continue;
		}

		$remaining[] = $field;
	}

	if ( ! $has_tabs || empty( $effects_fields ) ) {
		return $remaining;
	}

	$effects_tab_key = 'field_mrn_effects_tab';

	if ( isset( $effects_fields[0]['key'] ) && is_string( $effects_fields[0]['key'] ) && '' !== $effects_fields[0]['key'] ) {
		$effects_tab_key = $effects_fields[0]['key'] . '_effects_tab';
	}

	$remaining[] = mrn_base_stack_get_effects_tab_field( $effects_tab_key );

	return array_merge( $remaining, $effects_fields );
}

/**
 * Apply the builder Effects tab transform to an ACF field group.
 *
 * @param array<string, mixed> $field_group Field group config.
 * @return array<string, mixed>
 */
function mrn_base_stack_with_effects_tabs( array $field_group ) {
	if ( isset( $field_group['fields'] ) && is_array( $field_group['fields'] ) ) {
		$field_group['fields'] = mrn_base_stack_apply_primary_layout_field_contract( $field_group['fields'], false );
		$field_group['fields'] = mrn_base_stack_relocate_effect_fields( $field_group['fields'] );

		/*
		 * Re-run the contract pass after Effects relocation so empty
		 * "Additional settings" groups always receive their placeholder message.
		 */
		$field_group['fields'] = mrn_base_stack_apply_primary_layout_field_contract( $field_group['fields'], false );
	}

	return $field_group;
}

/**
 * Normalize a raw section-width setting to a supported value.
 *
 * @param mixed  $value Raw stored value.
 * @param string $default_width Default width.
 * @return string
 */
function mrn_base_stack_normalize_section_width( $value, $default_width = 'wide' ) {
	$width = is_string( $value ) ? sanitize_key( $value ) : '';

	if ( in_array( $value, array( 1, '1', true, 'true' ), true ) ) {
		$width = 'full-width';
	}

	if ( ! in_array( $width, array( 'content', 'wide', 'full-width' ), true ) ) {
		$width = $default_width;
	}

	return $width;
}

/**
 * Convert a section-width setting into a shell modifier class.
 *
 * @param mixed  $value Raw stored value.
 * @param string $default_width Default width.
 * @return string
 */
function mrn_base_stack_get_section_width_class( $value, $default_width = 'wide' ) {
	$width = mrn_base_stack_normalize_section_width( $value, $default_width );

	if ( 'content' === $width ) {
		return 'mrn-shell-section--width-content';
	}

	if ( 'full-width' === $width ) {
		return 'mrn-shell-section--width-full';
	}

	return 'mrn-shell-section--width-wide';
}

/**
 * Resolve section-width UI choice into section and container layer classes.
 *
 * `content` and `wide` are container-width choices inside a contained section.
 * `full-width` is a full-bleed section with a layout-owned inner container.
 *
 * @param mixed  $value Raw stored value.
 * @param string $default_width Default width choice.
 * @param string $full_container_width Inner container width to use when the section is full bleed.
 * @return array{width:string,section_class:string,container_class:string}
 */
function mrn_base_stack_get_section_width_layers( $value, $default_width = 'wide', $full_container_width = 'wide' ) {
	$width                = mrn_base_stack_normalize_section_width( $value, $default_width );
	$full_container_width = mrn_base_stack_normalize_section_width( $full_container_width, 'wide' );

	$section_class = 'mrn-layout-section--contained';
	$container_map = array(
		'content'    => 'mrn-layout-container--content',
		'wide'       => 'mrn-layout-container--wide',
		'full-width' => 'mrn-layout-container--full',
	);
	$container_key = $width;

	if ( 'full-width' === $width ) {
		$section_class = 'mrn-layout-section--full';
		$container_key = $full_container_width;
	}

	return array(
		'width'           => $width,
		'section_class'   => $section_class,
		'container_class' => $container_map[ $container_key ] ?? $container_map['wide'],
	);
}

/**
 * Resolve a builder row width setting into the shell modifier class.
 *
 * Supports legacy boolean full-width fields when requested.
 *
 * @param array<string, mixed> $row Builder row data.
 * @param string               $default_width Default width choice.
 * @param string               $legacy_full_width_key Optional legacy field name.
 * @return string
 */
function mrn_base_stack_get_row_section_width_class( array $row, $default_width = 'wide', $legacy_full_width_key = '' ) {
	$value = $row['section_width'] ?? '';

	if ( '' === $value && '' !== $legacy_full_width_key && ! empty( $row[ $legacy_full_width_key ] ) ) {
		$value = 'full-width';
	}

	return mrn_base_stack_get_section_width_class( $value, $default_width );
}

/**
 * Resolve a row's repeater-wrapper width with safe fallback behavior.
 *
 * `sub_content_width` defaults to the row's `section_width` when unset so
 * existing content keeps its prior visual width until explicitly changed.
 *
 * @param array<string, mixed> $row Builder row data.
 * @param string               $default_width Default width when both values are empty.
 * @return string
 */
function mrn_base_stack_get_row_sub_content_width( array $row, $default_width = 'wide' ) {
	$sub_content_width = isset( $row['sub_content_width'] ) ? $row['sub_content_width'] : '';
	$section_width     = isset( $row['section_width'] ) ? $row['section_width'] : '';

	$resolved = is_scalar( $sub_content_width ) ? trim( (string) $sub_content_width ) : '';
	if ( '' === $resolved ) {
		$resolved = is_scalar( $section_width ) ? trim( (string) $section_width ) : '';
	}

	return mrn_base_stack_normalize_section_width( $resolved, $default_width );
}

/**
 * Build class/attribute contract for repeater-wrapper width controls.
 *
 * @param array<string, mixed> $row Builder row data.
 * @return array{classes:array<int,string>,attributes:array<string,string>}
 */
function mrn_base_stack_get_builder_sub_content_width_contract( array $row ) {
	$width      = mrn_base_stack_get_row_sub_content_width( $row, 'wide' );
	$width_slug = 'wide';

	if ( 'content' === $width ) {
		$width_slug = 'content';
	} elseif ( 'full-width' === $width ) {
		$width_slug = 'full';
	}

	$contract = array(
		'classes'    => array( 'mrn-content-builder__row--sub-content-width-' . $width_slug ),
		'attributes' => array(),
	);

	/**
	 * Filter repeater-wrapper width class/attribute contract per row.
	 *
	 * @param array{classes:array<int,string>,attributes:array<string,string>} $contract Width contract.
	 * @param string                                                            $width Resolved width value.
	 * @param array<string, mixed>                                              $row Builder row data.
	 */
	$contract = apply_filters( 'mrn_base_stack_builder_sub_content_width_contract', $contract, $width, $row );

	return is_array( $contract ) ? $contract : array(
		'classes'    => array(),
		'attributes' => array(),
	);
}

/**
 * Normalize a builder anchor ID for safe front-end output.
 *
 * @param mixed $value Raw stored anchor value.
 * @return string
 */
function mrn_base_stack_normalize_anchor_id( $value ) {
	if ( ! is_string( $value ) ) {
		return '';
	}

	$value = trim( $value );
	if ( '' === $value ) {
		return '';
	}

	$value = ltrim( $value, "# \t\n\r\0\x0B" );

	return sanitize_title( $value );
}

/**
 * Ensure builder anchor IDs stay unique in the current request.
 *
 * @param string $anchor_id Normalized anchor ID.
 * @return string
 */
function mrn_base_stack_get_unique_builder_anchor_id( $anchor_id ) {
	static $seen_anchor_ids = array();

	$anchor_id = mrn_base_stack_normalize_anchor_id( $anchor_id );
	if ( '' === $anchor_id ) {
		return '';
	}

	if ( ! isset( $seen_anchor_ids[ $anchor_id ] ) ) {
		$seen_anchor_ids[ $anchor_id ] = 1;
		return $anchor_id;
	}

	$seen_anchor_ids[ $anchor_id ] = (int) $seen_anchor_ids[ $anchor_id ] + 1;

	return $anchor_id . '-' . $seen_anchor_ids[ $anchor_id ];
}

/**
 * Build the top-of-row anchor markup for a builder row.
 *
 * @param array<string, mixed> $row Builder row data.
 * @param string               $fallback_anchor Optional fallback anchor when the row does not store one.
 * @return string
 */
function mrn_base_stack_get_builder_anchor_markup( array $row, $fallback_anchor = '' ) {
	$anchor_id = mrn_base_stack_normalize_anchor_id( $row['anchor'] ?? '' );

	if ( '' === $anchor_id && '' !== $fallback_anchor ) {
		$anchor_id = mrn_base_stack_normalize_anchor_id( $fallback_anchor );
	}

	if ( '' === $anchor_id ) {
		return '';
	}

	/**
	 * Filter whether duplicate builder anchor IDs should be de-duplicated.
	 *
	 * @param bool                 $should_dedupe True to enforce unique IDs.
	 * @param string               $anchor_id Normalized anchor ID before de-duplication.
	 * @param array<string, mixed> $row Builder row data.
	 */
	$should_dedupe = (bool) apply_filters( 'mrn_base_stack_dedupe_builder_anchor_ids', true, $anchor_id, $row );
	if ( $should_dedupe ) {
		$anchor_id = mrn_base_stack_get_unique_builder_anchor_id( $anchor_id );
	}

	return sprintf(
		'<div id="%1$s" class="mrn-content-builder__anchor" aria-hidden="true"></div>',
		esc_attr( $anchor_id )
	);
}

/**
 * Get the standard accent contract for a builder section.
 *
 * @param bool   $enabled Whether the bottom accent is enabled.
 * @param string $accent_slug Optional accent style slug.
 * @return array{classes:array<int,string>,attributes:array<string,string>}
 */
function mrn_base_stack_get_builder_accent_contract( $enabled, $accent_slug = '' ) {
	if ( function_exists( 'mrn_site_styles_get_bottom_accent_contract' ) ) {
		$contract = mrn_site_styles_get_bottom_accent_contract( (bool) $enabled, (string) $accent_slug );
		$classes  = isset( $contract['classes'] ) && is_array( $contract['classes'] ) ? array_values( $contract['classes'] ) : array();
		$attrs    = isset( $contract['attributes'] ) && is_array( $contract['attributes'] ) ? $contract['attributes'] : array();

		return array(
			'classes'    => $classes,
			'attributes' => $attrs,
		);
	}

	return array(
		'classes'    => $enabled ? array( 'has-bottom-accent' ) : array(),
		'attributes' => array(),
	);
}

/**
 * Append accent classes to a builder section class list.
 *
 * @param array<int, string>                $classes Existing section classes.
 * @param array{classes?:array<int,string>} $accent_contract Accent contract array.
 * @return array<int, string>
 */
function mrn_base_stack_merge_builder_section_classes( array $classes, array $accent_contract ) {
	if ( ! empty( $accent_contract['classes'] ) && is_array( $accent_contract['classes'] ) ) {
		$classes = array_merge( $classes, $accent_contract['classes'] );
	}

	return array_values( array_unique( array_filter( $classes, 'strlen' ) ) );
}

/**
 * Merge a builder attribute contract into an existing attribute map.
 *
 * @param array<string, string> $attributes Existing attributes.
 * @param array<string, string> $extra_attributes Additional attributes.
 * @return array<string, string>
 */
function mrn_base_stack_merge_builder_attributes( array $attributes, array $extra_attributes ) {
	foreach ( $extra_attributes as $attribute_name => $attribute_value ) {
		$attribute_name  = is_string( $attribute_name ) ? trim( $attribute_name ) : '';
		$attribute_value = is_scalar( $attribute_value ) ? trim( (string) $attribute_value ) : '';

		if ( '' === $attribute_name || '' === $attribute_value ) {
			continue;
		}

		if ( isset( $attributes[ $attribute_name ] ) && 'style' === strtolower( $attribute_name ) ) {
			$existing_style = is_scalar( $attributes[ $attribute_name ] ) ? trim( (string) $attributes[ $attribute_name ] ) : '';
			if ( '' !== $existing_style ) {
				$attribute_value = $existing_style . '; ' . $attribute_value;
			}
		}

		$attributes[ $attribute_name ] = $attribute_value;
	}

	return $attributes;
}

/**
 * Get the post-meta key used for row-level flex settings.
 *
 * @return string
 */
function mrn_base_stack_get_builder_row_flex_meta_key() {
	return '_mrn_builder_row_flex_settings';
}

/**
 * Get the flexible-content field names that support row-level flex settings.
 *
 * @return array<int, string>
 */
function mrn_base_stack_get_builder_row_flex_supported_fields() {
	$fields = array(
		'page_content_rows',
		'page_after_content_rows',
		'page_hero_rows',
		'page_sidebar_rows',
	);

	/**
	 * Filter builder flexible-content fields that can use row-level flex settings.
	 *
	 * @param array<int, string> $fields Supported field names.
	 */
	$fields = apply_filters( 'mrn_base_stack_builder_row_flex_supported_fields', $fields );
	$fields = is_array( $fields ) ? $fields : array();

	$normalized = array();
	foreach ( $fields as $field_name ) {
		$field_name = is_string( $field_name ) ? sanitize_key( $field_name ) : '';
		if ( '' === $field_name ) {
			continue;
		}

		$normalized[] = $field_name;
	}

	return array_values( array_unique( $normalized ) );
}

/**
 * Normalize one row-level flex settings payload.
 *
 * @param mixed $settings Raw settings array.
 * @return array{enabled:bool,scope:string,direction:string,justify:string,align:string,wrap:string,gap:string}
 */
function mrn_base_stack_normalize_builder_row_flex_settings( $settings ) {
	$settings = is_array( $settings ) ? $settings : array();

	$scope = sanitize_key( (string) ( $settings['scope'] ?? 'row' ) );
	if ( ! in_array( $scope, array( 'row', 'repeaters' ), true ) ) {
		$scope = 'row';
	}

	$direction = sanitize_key( (string) ( $settings['direction'] ?? 'row' ) );
	if ( ! in_array( $direction, array( 'row', 'row-reverse', 'column', 'column-reverse' ), true ) ) {
		$direction = 'row';
	}

	$justify = sanitize_key( (string) ( $settings['justify'] ?? 'flex-start' ) );
	if ( ! in_array( $justify, array( 'flex-start', 'center', 'flex-end', 'space-between', 'space-around', 'space-evenly' ), true ) ) {
		$justify = 'flex-start';
	}

	$align = sanitize_key( (string) ( $settings['align'] ?? 'stretch' ) );
	if ( ! in_array( $align, array( 'stretch', 'flex-start', 'center', 'flex-end', 'baseline' ), true ) ) {
		$align = 'stretch';
	}

	$wrap = sanitize_key( (string) ( $settings['wrap'] ?? 'nowrap' ) );
	if ( ! in_array( $wrap, array( 'nowrap', 'wrap', 'wrap-reverse' ), true ) ) {
		$wrap = 'nowrap';
	}

	$gap_raw = $settings['gap'] ?? '0';
	$gap     = is_numeric( $gap_raw ) ? (float) $gap_raw : 0.0;
	$gap     = max( 0, min( 160, $gap ) );
	$gap     = rtrim( rtrim( sprintf( '%.2F', $gap ), '0' ), '.' );
	if ( '' === $gap ) {
		$gap = '0';
	}

	return array(
		'enabled'   => ! empty( $settings['enabled'] ),
		'scope'     => $scope,
		'direction' => $direction,
		'justify'   => $justify,
		'align'     => $align,
		'wrap'      => $wrap,
		'gap'       => $gap,
	);
}

/**
 * Sanitize a row-level flex payload map keyed by field name + row index.
 *
 * @param mixed $payload Raw payload.
 * @return array<string, array<string, array{enabled:bool,scope:string,direction:string,justify:string,align:string,wrap:string,gap:string}>>
 */
function mrn_base_stack_sanitize_builder_row_flex_payload( $payload ) {
	$payload = is_array( $payload ) ? $payload : array();
	$allowed = mrn_base_stack_get_builder_row_flex_supported_fields();
	$allowed = array_flip( $allowed );

	$sanitized = array();

	foreach ( $payload as $field_name => $rows ) {
		$field_name = is_string( $field_name ) ? sanitize_key( $field_name ) : '';
		if ( '' === $field_name || ! isset( $allowed[ $field_name ] ) || ! is_array( $rows ) ) {
			continue;
		}

		$sanitized_rows = array();
		foreach ( $rows as $row_index => $settings ) {
			if ( ! is_numeric( $row_index ) ) {
				continue;
			}

			$row_index = max( 0, (int) $row_index );
			$settings  = mrn_base_stack_normalize_builder_row_flex_settings( $settings );

			if ( empty( $settings['enabled'] ) ) {
				continue;
			}

			$sanitized_rows[ (string) $row_index ] = $settings;
		}

		if ( ! empty( $sanitized_rows ) ) {
			ksort( $sanitized_rows, SORT_NATURAL );
			$sanitized[ $field_name ] = $sanitized_rows;
		}
	}

	return $sanitized;
}

/**
 * Read sanitized row-level flex settings for a post.
 *
 * @param int $post_id Post ID.
 * @return array<string, array<string, array{enabled:bool,scope:string,direction:string,justify:string,align:string,wrap:string,gap:string}>>
 */
function mrn_base_stack_get_builder_row_flex_payload( $post_id ) {
	static $cache = array();

	$post_id = absint( $post_id );
	if ( $post_id < 1 ) {
		return array();
	}

	if ( isset( $cache[ $post_id ] ) ) {
		return $cache[ $post_id ];
	}

	$meta_key = mrn_base_stack_get_builder_row_flex_meta_key();
	$raw      = get_post_meta( $post_id, $meta_key, true );
	$payload  = mrn_base_stack_sanitize_builder_row_flex_payload( $raw );

	/**
	 * Filter the row-level flex payload before it is used in admin and frontend render paths.
	 *
	 * Child themes can use this to force defaults or remap per-row settings without changing
	 * saved post meta directly.
	 *
	 * @param array<string, array<string, array{enabled:bool,scope:string,direction:string,justify:string,align:string,wrap:string,gap:string}>> $payload Sanitized payload.
	 * @param int                                                                                                                     $post_id Post ID.
	 * @param mixed                                                                                                                   $raw Raw post-meta value before sanitization.
	 */
	$payload = apply_filters( 'mrn_base_stack_builder_row_flex_payload', $payload, $post_id, $raw );

	$cache[ $post_id ] = mrn_base_stack_sanitize_builder_row_flex_payload( $payload );

	return $cache[ $post_id ];
}

/**
 * Resolve row-level flex settings for a rendered builder row.
 *
 * @param array<string, mixed> $row Builder row payload with render context keys.
 * @return array{enabled:bool,scope:string,direction:string,justify:string,align:string,wrap:string,gap:string}
 */
function mrn_base_stack_get_builder_row_flex_settings( array $row ) {
	$defaults = mrn_base_stack_normalize_builder_row_flex_settings( array() );
	$settings = $defaults;

	$post_id    = isset( $row['__mrn_builder_post_id'] ) ? absint( $row['__mrn_builder_post_id'] ) : 0;
	$field_name = isset( $row['__mrn_builder_field_name'] ) ? sanitize_key( (string) $row['__mrn_builder_field_name'] ) : '';
	$row_index  = isset( $row['__mrn_builder_row_index'] ) ? (int) $row['__mrn_builder_row_index'] : -1;

	if ( $post_id > 0 && '' !== $field_name && $row_index >= 0 ) {
		$payload      = mrn_base_stack_get_builder_row_flex_payload( $post_id );
		$settings_key = (string) $row_index;

		if (
			! empty( $payload[ $field_name ] ) &&
			is_array( $payload[ $field_name ] ) &&
			isset( $payload[ $field_name ][ $settings_key ] ) &&
			is_array( $payload[ $field_name ][ $settings_key ] )
		) {
			$settings = mrn_base_stack_normalize_builder_row_flex_settings( $payload[ $field_name ][ $settings_key ] );
		}
	}

	/**
	 * Filter resolved row-level flex settings for one rendered row.
	 *
	 * @param array{enabled:bool,scope:string,direction:string,justify:string,align:string,wrap:string,gap:string} $settings Resolved settings.
	 * @param array<string, mixed>                                                                      $row Builder row payload with render context.
	 * @param int                                                                                       $post_id Host post ID.
	 * @param string                                                                                    $field_name Flexible-content field name.
	 * @param int                                                                                       $row_index Row index in the flexible-content field.
	 */
	$settings = apply_filters( 'mrn_base_stack_builder_row_flex_settings', $settings, $row, $post_id, $field_name, $row_index );

	return mrn_base_stack_normalize_builder_row_flex_settings( $settings );
}

/**
 * Build the row-level flex class/attribute contract for one builder row.
 *
 * @param array<string, mixed> $row Builder row data.
 * @return array{classes:array<int,string>,attributes:array<string,string>}
 */
function mrn_base_stack_get_builder_flex_contract( array $row ) {
	$settings = mrn_base_stack_get_builder_row_flex_settings( $row );
	$contract = array(
		'classes'    => array(),
		'attributes' => array(),
	);

	if ( ! empty( $settings['enabled'] ) ) {
		$style_declarations = array(
			'--mrn-row-flex-direction: ' . $settings['direction'],
			'--mrn-row-flex-justify: ' . $settings['justify'],
			'--mrn-row-flex-align: ' . $settings['align'],
			'--mrn-row-flex-wrap: ' . $settings['wrap'],
			'--mrn-row-flex-gap: ' . $settings['gap'] . 'px',
		);

		if ( 'repeaters' === $settings['scope'] ) {
			$style_declarations = array(
				'--mrn-repeater-flex-direction: ' . $settings['direction'],
				'--mrn-repeater-flex-justify: ' . $settings['justify'],
				'--mrn-repeater-flex-align: ' . $settings['align'],
				'--mrn-repeater-flex-wrap: ' . $settings['wrap'],
				'--mrn-repeater-flex-gap: ' . $settings['gap'] . 'px',
			);
		}

		$style = mrn_base_stack_get_inline_style_attribute( $style_declarations );

		$attributes = array();
		if ( '' !== $style ) {
			$attributes['style'] = $style;
		}

		$classes = array( 'mrn-content-builder__row--layout-flex' );
		if ( 'repeaters' === $settings['scope'] ) {
			$classes = array( 'mrn-content-builder__row--layout-flex-repeaters' );
		}

		$contract = array(
			'classes'    => $classes,
			'attributes' => $attributes,
		);
	}

	/**
	 * Filter the row-level flex frontend contract so child themes can override classes/attributes.
	 *
	 * @param array{classes:array<int,string>,attributes:array<string,string>}                           $contract Row contract.
	 * @param array{enabled:bool,scope:string,direction:string,justify:string,align:string,wrap:string,gap:string} $settings Resolved flex settings.
	 * @param array<string, mixed>                                                                         $row Builder row payload with render context.
	 */
	$contract = apply_filters( 'mrn_base_stack_builder_flex_contract', $contract, $settings, $row );
	$contract = is_array( $contract ) ? $contract : array();

	$classes = array();
	if ( ! empty( $contract['classes'] ) && is_array( $contract['classes'] ) ) {
		foreach ( $contract['classes'] as $class_name ) {
			$class_name = is_scalar( $class_name ) ? sanitize_html_class( (string) $class_name ) : '';
			if ( '' === $class_name ) {
				continue;
			}

			$classes[] = $class_name;
		}
	}
	$classes = array_values( array_unique( $classes ) );

	$attributes = array();
	if ( ! empty( $contract['attributes'] ) && is_array( $contract['attributes'] ) ) {
		foreach ( $contract['attributes'] as $attribute_name => $attribute_value ) {
			$attribute_name  = is_scalar( $attribute_name ) ? strtolower( trim( (string) $attribute_name ) ) : '';
			$attribute_value = is_scalar( $attribute_value ) ? trim( (string) $attribute_value ) : '';

			if ( '' === $attribute_name || '' === $attribute_value ) {
				continue;
			}

			$attribute_name = preg_replace( '/[^a-z0-9_:\-]/', '', $attribute_name );
			if ( '' === $attribute_name ) {
				continue;
			}

			$attributes[ $attribute_name ] = $attribute_value;
		}
	}

	return array(
		'classes'    => $classes,
		'attributes' => $attributes,
	);
}

/**
 * Normalize a builder motion-settings payload.
 *
 * @param mixed $value Raw motion settings.
 * @return array<string, string|bool>
 */
function mrn_base_stack_normalize_motion_settings( $value ) {
	$settings = is_array( $value ) ? $value : array();

	return array(
		'enabled'          => ! empty( $settings['enabled'] ),
		'effect'           => sanitize_key( (string) ( $settings['effect'] ?? '' ) ),
		'preset'           => sanitize_key( (string) ( $settings['preset'] ?? '' ) ),
		'trigger_position' => sanitize_key( (string) ( $settings['trigger_position'] ?? '' ) ),
		'target'           => mrn_base_stack_normalize_motion_target( $settings['target'] ?? 'row' ),
		'surface'          => sanitize_key( (string) ( $settings['surface'] ?? '' ) ),
		'active_class'     => sanitize_html_class( (string) ( $settings['active_class'] ?? '' ) ),
		'margin'           => is_string( $settings['margin'] ?? null ) ? trim( $settings['margin'] ) : '',
	);
}

/**
 * Build the motion contract for a normalized motion-settings payload.
 *
 * @param mixed $settings Raw motion settings.
 * @return array{classes:array<int,string>,attributes:array<string,string>}
 */
function mrn_base_stack_get_motion_contract_for_settings( $settings ) {
	$settings = mrn_base_stack_normalize_motion_settings( $settings );

	if ( empty( $settings['enabled'] ) ) {
		return array(
			'classes'    => array(),
			'attributes' => array(),
		);
	}

	$effect = $settings['effect'];
	$margin = '' !== $settings['margin'] ? $settings['margin'] : mrn_base_stack_get_motion_margin_for_trigger( $settings['trigger_position'] ?? '' );
	$target = mrn_base_stack_normalize_motion_target( $settings['target'] ?? 'row' );

	if ( 'surface' === $effect ) {
		$surface = $settings['surface'];

		if ( ! in_array( $surface, array( 'light', 'dark' ), true ) ) {
			$surface = 'dark';
		}

		return array(
			'classes'    => array(),
			'attributes' => array(
				'data-mrn-surface'        => $surface,
				'data-mrn-surface-margin' => $margin,
			),
		);
	}

	if ( 'active-class' === $effect ) {
		$active_class = 'is-mrn-in-view';

		return array(
			'classes'    => array( 'mrn-motion-effect--active-class' ),
			'attributes' => array(
				'data-mrn-motion-effect' => 'active-class',
				'data-mrn-motion-class'  => $active_class,
				'data-mrn-motion-margin' => $margin,
				'data-mrn-motion-target' => $target,
			),
		);
	}

	if ( 'dark-scroll-card' === $effect ) {
		$preset = $settings['preset'];

		return array(
			'classes'    => array( 'mrn-motion-effect--dark-scroll-card' ),
			'attributes' => array(
				'data-mrn-motion-effect' => 'dark-scroll-card',
				'data-mrn-effect-preset' => $preset,
				'data-mrn-motion-margin' => $margin,
				'data-mrn-motion-target' => $target,
			),
		);
	}

	return array(
		'classes'    => array(),
		'attributes' => array(),
	);
}

/**
 * Build the motion contract for a builder row.
 *
 * @param array<string, mixed> $row Builder row data.
 * @return array{classes:array<int,string>,attributes:array<string,string>}
 */
function mrn_base_stack_get_builder_motion_contract( array $row ) {
	$motion_contract      = mrn_base_stack_get_motion_contract_for_settings( $row['motion_settings'] ?? array() );
	$flex_contract        = mrn_base_stack_get_builder_flex_contract( $row );
	$sub_content_contract = mrn_base_stack_get_builder_sub_content_width_contract( $row );

	if ( ! empty( $flex_contract['classes'] ) && is_array( $flex_contract['classes'] ) ) {
		$motion_contract['classes'] = array_values(
			array_unique(
				array_filter(
					array_merge(
						isset( $motion_contract['classes'] ) && is_array( $motion_contract['classes'] ) ? $motion_contract['classes'] : array(),
						$flex_contract['classes']
					),
					'strlen'
				)
			)
		);
	}

	if ( ! empty( $flex_contract['attributes'] ) && is_array( $flex_contract['attributes'] ) ) {
		$motion_contract['attributes'] = mrn_base_stack_merge_builder_attributes(
			isset( $motion_contract['attributes'] ) && is_array( $motion_contract['attributes'] ) ? $motion_contract['attributes'] : array(),
			$flex_contract['attributes']
		);
	}

	if ( ! empty( $sub_content_contract['classes'] ) && is_array( $sub_content_contract['classes'] ) ) {
		$motion_contract['classes'] = array_values(
			array_unique(
				array_filter(
					array_merge(
						isset( $motion_contract['classes'] ) && is_array( $motion_contract['classes'] ) ? $motion_contract['classes'] : array(),
						$sub_content_contract['classes']
					),
					'strlen'
				)
			)
		);
	}

	if ( ! empty( $sub_content_contract['attributes'] ) && is_array( $sub_content_contract['attributes'] ) ) {
		$motion_contract['attributes'] = mrn_base_stack_merge_builder_attributes(
			isset( $motion_contract['attributes'] ) && is_array( $motion_contract['attributes'] ) ? $motion_contract['attributes'] : array(),
			$sub_content_contract['attributes']
		);
	}

	return $motion_contract;
}

/**
 * Convert an array of CSS declarations into a style attribute value.
 *
 * @param array<int, string> $styles CSS declarations.
 * @return string
 */
function mrn_base_stack_get_inline_style_attribute( array $styles ) {
	$styles = array_values(
		array_filter(
			array_map( 'trim', $styles ),
			'strlen'
		)
	);

	return implode( '; ', $styles );
}

/**
 * Convert an associative array into escaped HTML attributes.
 *
 * @param array<string, scalar> $attributes Associative attribute map.
 * @return string
 */
function mrn_base_stack_get_html_attributes( array $attributes ) {
	$parts = array();

	foreach ( $attributes as $attribute_name => $attribute_value ) {
		$attribute_name  = is_string( $attribute_name ) ? trim( $attribute_name ) : '';
		$attribute_value = is_scalar( $attribute_value ) ? trim( (string) $attribute_value ) : '';

		if ( '' === $attribute_name || '' === $attribute_value ) {
			continue;
		}

		$parts[] = sprintf( '%s="%s"', esc_attr( $attribute_name ), esc_attr( $attribute_value ) );
	}

	return implode( ' ', $parts );
}

/**
 * Shared HTML tag choices for heading-style text fields.
 *
 * @return array<string, string>
 */
function mrn_base_stack_get_text_tag_choices() {
	return array(
		'h1'   => 'H1',
		'h2'   => 'H2',
		'h3'   => 'H3',
		'h4'   => 'H4',
		'h5'   => 'H5',
		'h6'   => 'H6',
		'p'    => 'Paragraph',
		'span' => 'Span',
		'div'  => 'Div',
	);
}

/**
 * Normalize a requested HTML tag to the supported text-tag set.
 *
 * @param mixed  $value Raw tag value.
 * @param string $default_tag Default tag value.
 * @return string
 */
function mrn_base_stack_normalize_text_tag( $value, $default_tag = 'p' ) {
	$tag          = is_string( $value ) ? sanitize_key( $value ) : '';
	$default_tag  = is_string( $default_tag ) ? sanitize_key( $default_tag ) : 'p';
	$allowed_tags = array_keys( mrn_base_stack_get_text_tag_choices() );

	if ( ! in_array( $default_tag, $allowed_tags, true ) ) {
		$default_tag = 'p';
	}

	if ( ! in_array( $tag, $allowed_tags, true ) ) {
		$tag = $default_tag;
	}

	return $tag;
}

/**
 * Build a standard inline-HTML-enabled text field definition.
 *
 * @param string $key Unique ACF field key.
 * @param string $label Field label.
 * @param string $name Field name.
 * @param string $instructions Field instructions.
 * @param string $width Wrapper width percentage.
 * @return array<string, mixed>
 */
function mrn_base_stack_get_inline_text_field( $key, $label, $name, $instructions = 'Limited inline HTML allowed: span, strong, em, br.', $width = '75' ) {
	return array(
		'key'           => $key,
		'label'         => $label,
		'name'          => $name,
		'aria-label'    => '',
		'type'          => 'text',
		'instructions'  => $instructions,
		'wrapper'       => array(
			'width' => $width,
		),
	);
}

/**
 * Build a standard label-tag ACF field definition.
 *
 * @param string $key Unique ACF field key.
 * @param string $name Field name.
 * @param string $default_tag Default tag choice.
 * @param string $label Field label.
 * @return array<string, mixed>
 */
function mrn_base_stack_get_label_tag_field( $key, $name = 'label_tag', $default_tag = 'p', $label = 'Tag' ) {
	unset( $label );

	return array(
		'key'               => $key,
		'label'             => 'Tag',
		'name'              => $name,
		'aria-label'        => '',
		'type'              => 'select',
		'choices'           => mrn_base_stack_get_text_tag_choices(),
		'default_value'     => mrn_base_stack_normalize_text_tag( $default_tag, 'p' ),
		'multiple'          => 0,
		'return_format'     => 'value',
		'ui'                => 0,
		'wrapper'           => array(
			'width' => '25',
		),
	);
}

/**
 * Build a standard heading/subheading tag ACF field definition.
 *
 * @param string $key Unique ACF field key.
 * @param string $name Field name.
 * @param string $default_tag Default tag choice.
 * @param string $label Field label.
 * @return array<string, mixed>
 */
function mrn_base_stack_get_text_tag_field( $key, $name = 'heading_tag', $default_tag = 'h2', $label = 'Tag' ) {
	unset( $label );

	return array(
		'key'               => $key,
		'label'             => 'Tag',
		'name'              => $name,
		'aria-label'        => '',
		'type'              => 'select',
		'choices'           => mrn_base_stack_get_text_tag_choices(),
		'default_value'     => mrn_base_stack_normalize_text_tag( $default_tag, 'h2' ),
		'multiple'          => 0,
		'return_format'     => 'value',
		'ui'                => 0,
		'wrapper'           => array(
			'width' => '25',
		),
	);
}

/**
 * Get Font Awesome choices for builder link icon fields.
 *
 * If the Font Awesome profile manager is active and has an allowlist, prefer
 * those classes so editor choices match the profile configuration.
 *
 * @return array<string, string>
 */
function mrn_base_stack_get_builder_link_fontawesome_choices() {
	$choices = function_exists( 'mrn_base_stack_get_header_search_fontawesome_choices' )
		? mrn_base_stack_get_header_search_fontawesome_choices()
		: array();

	if ( ! is_array( $choices ) ) {
		$choices = array();
	}

	if ( ! function_exists( 'mrn_fapm_get_icon_allowlist' ) ) {
		return $choices;
	}

	$allowlist = mrn_fapm_get_icon_allowlist();
	if ( ! is_array( $allowlist ) || empty( $allowlist ) ) {
		return $choices;
	}

	$filtered = array();

	foreach ( $allowlist as $icon_class ) {
		$icon_class = trim( (string) $icon_class );
		if ( '' === $icon_class ) {
			continue;
		}

		if ( isset( $choices[ $icon_class ] ) ) {
			$filtered[ $icon_class ] = $choices[ $icon_class ];
			continue;
		}

		$filtered[ $icon_class ] = $icon_class;
	}

	return ! empty( $filtered ) ? $filtered : $choices;
}

/**
 * Build shared manual icon fields for builder links.
 *
 * @param string $key_prefix Unique ACF key prefix for this field set.
 * @param string $link_style_field_key Unused legacy arg kept for call-site compatibility.
 * @return array<int, array<string, mixed>>
 */
function mrn_base_stack_get_button_link_icon_fields( $key_prefix, $link_style_field_key ) {
	unset( $link_style_field_key );

	return array(
		array(
			'key'           => $key_prefix . '_source',
			'label'         => 'Icon Source',
			'name'          => 'link_icon_source',
			'aria-label'    => '',
			'type'          => 'button_group',
			'choices'       => array(
				'dashicons'   => 'Dashicons',
				'fontawesome' => 'Font Awesome',
				'media'       => 'Media',
			),
			'default_value' => '',
			'layout'        => 'horizontal',
			'return_format' => 'value',
			'wrapper'       => array(
				'width' => '100',
				'class' => 'mrn-icon-chooser-field mrn-icon-chooser-field--source mrn-icon-chooser-field--allow-empty',
			),
		),
		array(
			'key'         => $key_prefix . '_dashicons',
			'label'       => 'Dashicon',
			'name'        => 'link_icon_dashicon',
			'aria-label'  => '',
			'type'        => 'text',
			'placeholder' => 'dashicons-arrow-right-alt2',
			'wrapper'     => array(
				'width' => '50',
				'class' => 'mrn-icon-chooser-field mrn-icon-chooser-field--dashicons',
			),
		),
		array(
			'key'         => $key_prefix . '_fontawesome',
			'label'       => 'Font Awesome',
			'name'        => 'link_icon_fa_class',
			'aria-label'  => '',
			'type'        => 'text',
			'placeholder' => 'fa-solid fa-arrow-right',
			'wrapper'     => array(
				'width' => '50',
				'class' => 'mrn-icon-chooser-field mrn-icon-chooser-field--fontawesome',
			),
		),
		array(
			'key'           => $key_prefix . '_media',
			'label'         => 'Media',
			'name'          => 'link_icon_media_icon',
			'aria-label'    => '',
			'type'          => 'image',
			'return_format' => 'array',
			'preview_size'  => 'thumbnail',
			'library'       => 'all',
			'mime_types'    => 'jpg,jpeg,png,gif,webp,svg',
			'wrapper'       => array(
				'width' => '50',
				'class' => 'mrn-icon-chooser-field mrn-icon-chooser-field--media',
			),
		),
		array(
			'key'           => $key_prefix . '_position',
			'label'         => 'Icon Position',
			'name'          => 'link_icon_position',
			'aria-label'    => '',
			'type'          => 'select',
			'choices'       => array(
				'left'  => 'Left',
				'right' => 'Right',
			),
			'default_value' => 'left',
			'return_format' => 'value',
			'ui'            => 1,
			'wrapper'       => array(
				'width' => '50',
			),
		),
		array(
			'key'           => $key_prefix . '_gap',
			'label'         => 'Icon Gap',
			'name'          => 'link_icon_gap',
			'aria-label'    => '',
			'type'          => 'number',
			'default_value' => 10,
			'min'           => 0,
			'step'          => 1,
			'append'        => 'px',
			'wrapper'       => array(
				'width' => '50',
			),
		),
	);
}

/**
 * Resolve the chosen icon source for a builder button link.
 *
 * @param array<string, mixed> $row Builder row data.
 * @return string
 */
function mrn_base_stack_get_button_link_icon_source( array $row ) {
	$icon_source = isset( $row['link_icon_source'] ) ? sanitize_key( (string) $row['link_icon_source'] ) : '';
	$media_icon  = isset( $row['link_icon_media_icon'] ) && is_array( $row['link_icon_media_icon'] ) ? $row['link_icon_media_icon'] : array();
	$media_id    = isset( $media_icon['ID'] ) ? absint( $media_icon['ID'] ) : 0;
	$media_url   = isset( $media_icon['url'] ) ? esc_url_raw( (string) $media_icon['url'] ) : '';
	$fa_class    = isset( $row['link_icon_fa_class'] ) ? trim( (string) $row['link_icon_fa_class'] ) : '';
	$dashicon    = mrn_base_stack_normalize_link_dashicon_class( isset( $row['link_icon_dashicon'] ) ? (string) $row['link_icon_dashicon'] : '' );

	if ( in_array( $icon_source, array( 'dashicons', 'fontawesome', 'media' ), true ) ) {
		return $icon_source;
	}

	if ( $media_id > 0 || '' !== $media_url ) {
		return 'media';
	}

	if ( '' !== $fa_class ) {
		return 'fontawesome';
	}

	if ( '' !== $dashicon ) {
		return 'dashicons';
	}

	return '';
}

/**
 * Normalize a Dashicon class from manual editor input.
 *
 * Accepts either `dashicons-arrow-right` or `dashicons dashicons-arrow-right`.
 *
 * @param string $dashicon_raw Raw dashicon field input.
 * @return string
 */
function mrn_base_stack_normalize_link_dashicon_class( $dashicon_raw ) {
	$dashicon_raw = trim( (string) $dashicon_raw );

	if ( '' === $dashicon_raw || 'dashicons' === strtolower( $dashicon_raw ) ) {
		return '';
	}

	if ( preg_match( '/dashicons-[a-z0-9-]+/i', $dashicon_raw, $matches ) ) {
		return sanitize_html_class( strtolower( (string) $matches[0] ) );
	}

	$dashicon = sanitize_html_class( $dashicon_raw );

	if ( '' === $dashicon || 'dashicons' === $dashicon ) {
		return '';
	}

	if ( 0 === strpos( $dashicon, 'dashicons-' ) ) {
		return strlen( $dashicon ) > strlen( 'dashicons-' ) && 'dashicons-dashicons' !== $dashicon ? $dashicon : '';
	}

	$dashicon = 'dashicons-' . $dashicon;

	return 'dashicons-dashicons' === $dashicon ? '' : $dashicon;
}

/**
 * Resolve the chosen icon position for a builder button link.
 *
 * @param array<string, mixed> $row Builder row data.
 * @return string
 */
function mrn_base_stack_get_button_link_icon_position( array $row ) {
	$position = isset( $row['link_icon_position'] ) ? sanitize_key( (string) $row['link_icon_position'] ) : 'left';

	return in_array( $position, array( 'left', 'right' ), true ) ? $position : 'left';
}

/**
 * Resolve the chosen icon gap for a builder button link.
 *
 * @param array<string, mixed> $row Builder row data.
 * @return string
 */
function mrn_base_stack_get_button_link_icon_gap( array $row ) {
	if ( ! array_key_exists( 'link_icon_gap', $row ) || '' === (string) $row['link_icon_gap'] ) {
		return '';
	}

	$gap = is_numeric( $row['link_icon_gap'] ) ? (float) $row['link_icon_gap'] : 10.0;
	$gap = max( 0, $gap );

	if ( 0.0 === fmod( $gap, 1.0 ) ) {
		return (string) (int) $gap . 'px';
	}

	return rtrim( rtrim( sprintf( '%.2f', $gap ), '0' ), '.' ) . 'px';
}

/**
 * Build the frontend icon markup for builder button links.
 *
 * @param array<string, mixed> $row Builder row data.
 * @return string
 */
function mrn_base_stack_get_button_link_icon_markup( array $row ) {
	$icon_source = mrn_base_stack_get_button_link_icon_source( $row );
	$position    = mrn_base_stack_get_button_link_icon_position( $row );
	$gap         = mrn_base_stack_get_button_link_icon_gap( $row );
	$style_attr  = '' !== $gap ? ' style="--mrn-link-icon-gap:' . esc_attr( $gap ) . ';"' : '';

	if ( '' === $icon_source ) {
		return '';
	}

	if ( 'fontawesome' === $icon_source ) {
		$fa_class = isset( $row['link_icon_fa_class'] ) ? trim( (string) $row['link_icon_fa_class'] ) : '';

		if ( '' === $fa_class ) {
			return '';
		}

		if ( function_exists( 'mrn_fapm_icon_is_allowed' ) && ! mrn_fapm_icon_is_allowed( $fa_class ) ) {
			return '';
		}

		return '<span class="mrn-ui__link-icon mrn-ui__link-icon--' . esc_attr( $position ) . ' mrn-ui__link-icon--fontawesome" aria-hidden="true"' . $style_attr . '><i class="' . esc_attr( $fa_class ) . '"></i></span>';
	}

	if ( 'dashicons' === $icon_source ) {
		$dashicon = mrn_base_stack_normalize_link_dashicon_class( isset( $row['link_icon_dashicon'] ) ? (string) $row['link_icon_dashicon'] : '' );

		if ( '' === $dashicon ) {
			return '';
		}

		return '<span class="mrn-ui__link-icon mrn-ui__link-icon--' . esc_attr( $position ) . ' mrn-ui__link-icon--dashicons" aria-hidden="true"' . $style_attr . '><span class="dashicons ' . esc_attr( $dashicon ) . '"></span></span>';
	}

	$media_icon = isset( $row['link_icon_media_icon'] ) && is_array( $row['link_icon_media_icon'] ) ? $row['link_icon_media_icon'] : array();
	$media_id   = isset( $media_icon['ID'] ) ? absint( $media_icon['ID'] ) : 0;
	$media_url  = isset( $media_icon['url'] ) ? esc_url( (string) $media_icon['url'] ) : '';

	if ( $media_id > 0 ) {
		$image_markup = wp_get_attachment_image(
			$media_id,
			'thumbnail',
			false,
			array(
				'class'       => 'mrn-ui__link-icon-image',
				'alt'         => '',
				'aria-hidden' => 'true',
			)
		);

		if ( '' !== $image_markup ) {
			return '<span class="mrn-ui__link-icon mrn-ui__link-icon--' . esc_attr( $position ) . ' mrn-ui__link-icon--media" aria-hidden="true"' . $style_attr . '>' . $image_markup . '</span>';
		}
	}

	if ( '' === $media_url ) {
		return '';
	}

	return '<span class="mrn-ui__link-icon mrn-ui__link-icon--' . esc_attr( $position ) . ' mrn-ui__link-icon--media" aria-hidden="true"' . $style_attr . '><img class="mrn-ui__link-icon-image" src="' . esc_url( $media_url ) . '" alt="" /></span>';
}

/**
 * Build compact link label markup so links do not render stray whitespace.
 *
 * Template indentation can introduce visible leading/trailing spaces when
 * optional icon markup is empty, so this helper composes output in one string.
 *
 * @param string $label Link label.
 * @param string $icon_markup Optional escaped icon markup.
 * @param string $icon_position Icon position.
 * @return string
 */
function mrn_base_stack_get_compact_link_label_markup( $label, $icon_markup = '', $icon_position = 'left' ) {
	$label_markup = esc_html( (string) $label );
	$icon_markup  = (string) $icon_markup;
	$position     = sanitize_key( (string) $icon_position );

	if ( '' === $icon_markup ) {
		return $label_markup;
	}

	if ( 'right' === $position ) {
		return $label_markup . $icon_markup;
	}

	return $icon_markup . $label_markup;
}

/**
 * Resolve one normalized link from a repeater item using new and legacy shapes.
 *
 * New contract: `links` repeater (max 1 row).
 * Legacy contract: flat `link` + link-config fields on the item itself.
 *
 * @param array<string, mixed> $item Repeater item data.
 * @param array<string, mixed> $args Optional normalize args.
 * @return array<string, mixed>
 */
function mrn_base_stack_get_repeater_item_primary_link( array $item, array $args = array() ) {
	$link_args = $args;
	if ( ! isset( $link_args['max'] ) ) {
		$link_args['max'] = 1;
	}

	if ( function_exists( 'mrn_rbl_get_content_links' ) ) {
		$links = mrn_rbl_get_content_links( $item, $link_args );
		if ( ! empty( $links ) && isset( $links[0] ) && is_array( $links[0] ) ) {
			return $links[0];
		}
	}

	if ( function_exists( 'mrn_rbl_normalize_content_link' ) ) {
		$legacy_item = $item;

		if ( isset( $legacy_item['link'] ) && is_string( $legacy_item['link'] ) && '' !== trim( $legacy_item['link'] ) ) {
			$legacy_item['url'] = trim( $legacy_item['link'] );
		}

		return mrn_rbl_normalize_content_link( $legacy_item, $args );
	}

	return array(
		'url'        => '',
		'text'       => '',
		'target'     => '',
		'link_style' => 'link',
	);
}

/**
 * Recursively collect builder button-link icon asset requirements.
 *
 * @param mixed $value Builder field data.
 * @param bool  $needs_fontawesome Whether Font Awesome is needed.
 * @param bool  $needs_dashicons Whether Dashicons are needed.
 * @return void
 */
function mrn_base_stack_collect_builder_link_icon_asset_needs( $value, &$needs_fontawesome, &$needs_dashicons ) {
	if ( ! is_array( $value ) ) {
		return;
	}

	$icon_source = mrn_base_stack_get_button_link_icon_source( $value );

	if ( 'fontawesome' === $icon_source ) {
		$needs_fontawesome = true;
	}

	if ( 'dashicons' === $icon_source ) {
		$needs_dashicons = true;
	}

	foreach ( $value as $child ) {
		if ( is_array( $child ) ) {
			mrn_base_stack_collect_builder_link_icon_asset_needs( $child, $needs_fontawesome, $needs_dashicons );
		}

		if ( $needs_fontawesome && $needs_dashicons ) {
			return;
		}
	}
}

/**
 * Allow a small, intentional inline HTML subset for heading-style fields.
 *
 * @param string $value Raw heading text value.
 * @return string
 */
function mrn_base_stack_format_heading_inline_html( $value ) {
	$allowed_tags = array(
		'span'   => array(
			'class' => true,
		),
		'strong' => array(),
		'em'     => array(),
		'br'     => array(),
	);

	return wp_kses( (string) $value, $allowed_tags );
}

/**
 * Build a row-specific pagination query arg for content-list builder rows.
 *
 * @param int $post_id Current post ID.
 * @param int $index Zero-based row index.
 * @return string
 */
function mrn_base_stack_get_content_list_pagination_query_arg( $post_id, $index ) {
	return sanitize_key( sprintf( 'mrn_list_page_%d_%d', absint( $post_id ), absint( $index ) ) );
}

/**
 * Resolve the requested content-list page from the current query string.
 *
 * @param int $post_id Current post ID.
 * @param int $index Zero-based row index.
 * @return int
 */
function mrn_base_stack_get_content_list_current_page( $post_id, $index ) {
	$query_arg = mrn_base_stack_get_content_list_pagination_query_arg( $post_id, $index );

	if ( isset( $_GET[ $query_arg ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended,Generic.WhiteSpace.ScopeIndent.IncorrectExact
		return max( 1, absint( wp_unslash( $_GET[ $query_arg ] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended,Generic.WhiteSpace.ScopeIndent.IncorrectExact
	}

	return 1;
}

/**
 * Build a trimmed excerpt for content-list rows.
 *
 * @param WP_Post $post Current listing post.
 * @param int     $word_count Desired word count.
 * @return string
 */
function mrn_base_stack_get_content_list_excerpt( WP_Post $post, $word_count = 24 ) {
	$word_count = max( 1, absint( $word_count ) );
	$excerpt    = trim( (string) get_the_excerpt( $post ) );

	if ( '' === $excerpt ) {
		$excerpt = trim( wp_strip_all_tags( (string) $post->post_content ) );
	}

	if ( '' === $excerpt ) {
		return '';
	}

	return wp_trim_words( $excerpt, $word_count, '...' );
}

/**
 * Build a taxonomy filter query for a content-list row.
 *
 * @param array<string, mixed> $row Content-list row settings.
 * @param int                  $context_post_id Current page/post ID.
 * @param string               $target_post_type Queried post type.
 * @return array<int, array<string, mixed>>
 */
function mrn_base_stack_get_content_list_tax_query( array $row, $context_post_id, $target_post_type ) {
	$filter_source = isset( $row['filter_source'] ) ? sanitize_key( (string) $row['filter_source'] ) : 'none';
	$taxonomy      = isset( $row['filter_taxonomy'] ) ? sanitize_key( (string) $row['filter_taxonomy'] ) : '';
	$match_mode    = isset( $row['filter_match'] ) ? sanitize_key( (string) $row['filter_match'] ) : 'any';

	if ( 'none' === $filter_source || '' === $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
		return array();
	}

	if ( '' !== $target_post_type && ! is_object_in_taxonomy( $target_post_type, $taxonomy ) ) {
		return array();
	}

	$operator = 'all' === $match_mode ? 'AND' : 'IN';

	if ( 'current_post_terms' === $filter_source ) {
		$term_ids = wp_get_post_terms(
			absint( $context_post_id ),
			$taxonomy,
			array(
				'fields' => 'ids',
			)
		);

		if ( is_wp_error( $term_ids ) || empty( $term_ids ) ) {
			return array(
				array(
					'taxonomy' => $taxonomy,
					'field'    => 'term_id',
					'terms'    => array( 0 ),
					'operator' => 'IN',
				),
			);
		}

		return array(
			array(
				'taxonomy' => $taxonomy,
				'field'    => 'term_id',
				'terms'    => array_map( 'absint', $term_ids ),
				'operator' => $operator,
			),
		);
	}

	if ( 'manual_terms' === $filter_source ) {
		$raw_terms  = isset( $row['filter_term_slugs'] ) ? (string) $row['filter_term_slugs'] : '';
		$term_slugs = array_values(
			array_filter(
				array_map(
					'sanitize_title',
					false !== preg_split( '/[\s,]+/', $raw_terms ) ? preg_split( '/[\s,]+/', $raw_terms ) : array()
				),
				'strlen'
			)
		);

		if ( empty( $term_slugs ) ) {
			return array();
		}

		return array(
			array(
				'taxonomy' => $taxonomy,
				'field'    => 'slug',
				'terms'    => $term_slugs,
				'operator' => $operator,
			),
		);
	}

	return array();
}

/**
 * Build a CSS custom-property declaration for a selected background image.
 *
 * @param mixed  $image ACF image field value.
 * @param string $css_var CSS custom property name.
 * @return string
 */
function mrn_base_stack_get_background_image_style( $image, $css_var ) {
	$image_url = '';

	if ( is_array( $image ) && ! empty( $image['url'] ) ) {
		$image_url = (string) $image['url'];
	} elseif ( is_string( $image ) ) {
		$image_url = $image;
	}

	$image_url = trim( $image_url );
	$css_var   = trim( (string) $css_var );

	if ( '' === $image_url || '' === $css_var ) {
		return '';
	}

	return $css_var . ": url('" . esc_url_raw( $image_url ) . "')";
}

/**
 * Normalize a YouTube or Vimeo URL into an embed URL.
 *
 * @param mixed                $url Raw video field value.
 * @param array<string, mixed> $options Embed behavior options.
 * @return array{provider:string,embed_url:string}
 */
function mrn_base_stack_get_video_embed( $url, array $options = array() ) {
	$raw_url = is_string( $url ) ? trim( $url ) : '';
	$options = wp_parse_args(
		$options,
		array(
			'autoplay'   => false,
			'muted'      => false,
			'loop'       => false,
			'controls'   => true,
			'background' => false,
		)
	);

	if ( '' === $raw_url ) {
		return array(
			'provider'  => '',
			'embed_url' => '',
		);
	}

	$sanitized_url = esc_url_raw( $raw_url );
	if ( '' === $sanitized_url ) {
		return array(
			'provider'  => '',
			'embed_url' => '',
		);
	}

	if ( preg_match( '~(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{11})~', $sanitized_url, $matches ) ) {
		$video_id = $matches[1];
		$query    = array(
			'autoplay'       => ! empty( $options['autoplay'] ) ? '1' : '0',
			'mute'           => ! empty( $options['muted'] ) ? '1' : '0',
			'controls'       => ! empty( $options['controls'] ) ? '1' : '0',
			'loop'           => ! empty( $options['loop'] ) ? '1' : '0',
			'playlist'       => ! empty( $options['loop'] ) ? $video_id : '',
			'playsinline'    => '1',
			'rel'            => '0',
			'modestbranding' => '1',
		);

		return array(
			'provider'  => 'youtube',
			'embed_url' => sprintf( 'https://www.youtube.com/embed/%s?%s', rawurlencode( $video_id ), http_build_query( array_filter( $query, 'strlen' ), '', '&', PHP_QUERY_RFC3986 ) ),
		);
	}

	if ( preg_match( '~vimeo\.com/(?:video/)?([0-9]+)~', $sanitized_url, $matches ) ) {
		$video_id = $matches[1];
		$query    = array(
			'autoplay'   => ! empty( $options['autoplay'] ) ? '1' : '0',
			'muted'      => ! empty( $options['muted'] ) ? '1' : '0',
			'loop'       => ! empty( $options['loop'] ) ? '1' : '0',
			'background' => ! empty( $options['background'] ) ? '1' : '0',
			'autopause'  => ! empty( $options['background'] ) ? '0' : '1',
			'controls'   => ! empty( $options['controls'] ) ? '1' : '0',
			'byline'     => '0',
			'title'      => '0',
		);

		return array(
			'provider'  => 'vimeo',
			'embed_url' => sprintf( 'https://player.vimeo.com/video/%s?%s', rawurlencode( $video_id ), http_build_query( array_filter( $query, 'strlen' ), '', '&', PHP_QUERY_RFC3986 ) ),
		);
	}

	return array(
		'provider'  => '',
		'embed_url' => '',
	);
}

/**
 * Nested layouts available inside the Two Column Split builder row.
 *
 * @return array<string, array<string, mixed>>
 */
function mrn_base_stack_get_two_column_nested_layouts() {
	return array(
		'layout_mrn_nested_body_text' => array(
			'key'        => 'layout_mrn_nested_body_text',
			'name'       => 'body_text',
			'label'      => 'Text - rich text',
			'display'    => 'block',
			'sub_fields' => array(
				array(
					'key'        => 'field_mrn_nested_body_text_content_tab',
					'label'      => 'Content',
					'name'       => '',
					'aria-label' => '',
					'type'       => 'tab',
					'placement'  => 'top',
				),
				array(
					'key'          => 'field_mrn_nested_body_text_content',
					'label'        => 'Body Text',
					'name'         => 'body_text',
					'aria-label'   => '',
					'type'         => 'wysiwyg',
					'tabs'         => 'all',
					'toolbar'      => 'full',
					'media_upload' => 1,
					'delay'        => 0,
				),
				array(
					'key'        => 'field_mrn_nested_body_text_config_tab',
					'label'      => 'Configs',
					'name'       => '',
					'aria-label' => '',
					'type'       => 'tab',
					'placement'  => 'top',
					'endpoint'   => 0,
				),
				array(
					'key'           => 'field_mrn_nested_body_text_bottom_accent',
					'label'         => 'Bottom Accent',
					'name'          => 'bottom_accent',
					'aria-label'    => '',
					'type'          => 'true_false',
					'ui'            => 1,
					'default_value' => 0,
					'ui_on_text'    => 'On',
					'ui_off_text'   => 'Off',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_body_text_bottom_accent_style',
					'label'         => 'Bottom Accent Style',
					'name'          => 'bottom_accent_style',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => function_exists( 'mrn_site_styles_get_graphic_element_choices' ) ? mrn_site_styles_get_graphic_element_choices() : array( '' => 'Select a Graphic Element' ),
					'default_value' => '',
					'ui'            => 1,
					'allow_null'    => 1,
					'instructions'  => 'Choose a saved graphic element from Site Styles.',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				mrn_base_stack_get_anchor_field( 'field_mrn_nested_body_text_anchor' ),
				mrn_base_stack_get_motion_group_field( 'field_mrn_nested_body_text_motion_settings' ),
			),
		),
		'layout_mrn_nested_basic' => array(
			'key'        => 'layout_mrn_nested_basic',
			'name'       => 'basic',
			'label'      => 'Basic - label|heading|subheading|text with editor|image|link',
			'display'    => 'block',
			'sub_fields' => array(
				array(
					'key'        => 'field_mrn_nested_basic_content_tab',
					'label'      => 'Content',
					'name'       => '',
					'aria-label' => '',
					'type'       => 'tab',
					'placement'  => 'top',
				),
				mrn_base_stack_get_inline_text_field( 'field_mrn_nested_basic_label', 'Label', 'label' ),
				mrn_base_stack_get_label_tag_field( 'field_mrn_nested_basic_label_tag' ),
				mrn_base_stack_get_inline_text_field( 'field_mrn_nested_basic_heading', 'Heading', 'heading' ),
				mrn_base_stack_get_text_tag_field( 'field_mrn_nested_basic_heading_tag', 'heading_tag', 'h2', 'Heading Tag' ),
				mrn_base_stack_get_inline_text_field( 'field_mrn_nested_basic_subheading', 'Subheading', 'subheading' ),
				mrn_base_stack_get_text_tag_field( 'field_mrn_nested_basic_subheading_tag', 'subheading_tag', 'p', 'Subheading Tag' ),
				array(
					'key'          => 'field_mrn_nested_basic_content',
					'label'        => 'Text area with editor',
					'name'         => 'content',
					'aria-label'   => '',
					'type'         => 'wysiwyg',
					'tabs'         => 'all',
					'toolbar'      => 'full',
					'media_upload' => 1,
					'delay'        => 0,
				),
				array(
					'key'           => 'field_mrn_nested_basic_image',
					'label'         => 'Image',
					'name'          => 'image',
					'aria-label'    => '',
					'type'          => 'image',
					'return_format' => 'array',
					'preview_size'  => 'medium',
					'library'       => 'all',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				...mrn_rbl_get_content_link_fields( 'field_mrn_nested_basic_links', 'Links', 'links', 1 ),
				array(
					'key'        => 'field_mrn_nested_basic_config_tab',
					'label'      => 'Configs',
					'name'       => '',
					'aria-label' => '',
					'type'       => 'tab',
					'placement'  => 'top',
					'endpoint'   => 0,
				),
				array(
					'key'           => 'field_mrn_nested_basic_link_style',
					'label'         => 'Link style',
					'name'          => 'link_style',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => function_exists( 'mrn_rbl_get_link_style_choices' )
						? mrn_rbl_get_link_style_choices()
						: array(
							'link'   => 'Link',
							'button' => 'Button',
						),
					'default_value' => 'link',
					'ui'            => 1,
					'wrapper'       => array(
						'width' => '50',
					),
				),
				...mrn_base_stack_get_button_link_icon_fields( 'field_mrn_nested_basic_link_icon', 'field_mrn_nested_basic_link_style' ),
				array(
					'key'           => 'field_mrn_nested_basic_link_color',
					'label'         => 'Link color',
					'name'          => 'link_color',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => function_exists( 'mrn_rbl_get_site_color_choices' ) ? mrn_rbl_get_site_color_choices() : array(),
					'ui'            => 1,
					'allow_null'    => 1,
					'instructions'  => 'Select from Site Colors when available.',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_basic_image_placement',
					'label'         => 'Image placement',
					'name'          => 'image_placement',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => array(
						'left'  => 'Left',
						'right' => 'Right',
					),
					'default_value' => 'left',
					'ui'            => 1,
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_basic_background_color',
					'label'         => 'Background color',
					'name'          => 'background_color',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => function_exists( 'mrn_rbl_get_site_color_choices' ) ? mrn_rbl_get_site_color_choices() : array(),
					'ui'            => 1,
					'allow_null'    => 1,
					'instructions'  => 'Select from Site Colors when available.',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_basic_background_image',
					'label'         => 'Background image',
					'name'          => 'background_image',
					'aria-label'    => '',
					'type'          => 'image',
					'return_format' => 'array',
					'preview_size'  => 'medium',
					'library'       => 'all',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_basic_bottom_accent',
					'label'         => 'Accent',
					'name'          => 'bottom_accent',
					'aria-label'    => '',
					'type'          => 'true_false',
					'ui'            => 1,
					'default_value' => 0,
					'ui_on_text'    => 'On',
					'ui_off_text'   => 'Off',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_basic_bottom_accent_style',
					'label'         => 'Accent Style',
					'name'          => 'bottom_accent_style',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => function_exists( 'mrn_site_styles_get_graphic_element_choices' ) ? mrn_site_styles_get_graphic_element_choices() : array( '' => 'Select a Graphic Element' ),
					'default_value' => '',
					'ui'            => 1,
					'allow_null'    => 1,
					'instructions'  => 'Choose a saved graphic element from Site Styles.',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				mrn_base_stack_get_anchor_field( 'field_mrn_nested_basic_anchor' ),
				mrn_base_stack_get_motion_group_field( 'field_mrn_nested_basic_motion_settings' ),
			),
		),
		'layout_mrn_nested_card' => array(
			'key'        => 'layout_mrn_nested_card',
			'name'       => 'card',
			'label'      => 'Card - image|text|link',
			'display'    => 'block',
			'sub_fields' => array(
				array(
					'key'        => 'field_mrn_nested_card_content_tab',
					'label'      => 'Content',
					'name'       => '',
					'aria-label' => '',
					'type'       => 'tab',
					'placement'  => 'top',
				),
				mrn_base_stack_get_inline_text_field( 'field_mrn_nested_card_heading', 'Heading', 'heading' ),
				mrn_base_stack_get_text_tag_field( 'field_mrn_nested_card_heading_tag', 'heading_tag', 'h2', 'Heading Tag' ),
				mrn_base_stack_get_inline_text_field( 'field_mrn_nested_card_subheading', 'Subheading', 'subheading' ),
				mrn_base_stack_get_text_tag_field( 'field_mrn_nested_card_subheading_tag', 'subheading_tag', 'p', 'Subheading Tag' ),
				array(
					'key'           => 'field_mrn_nested_card_link',
					'label'         => 'Link',
					'name'          => 'link',
					'aria-label'    => '',
					'type'          => 'link',
					'return_format' => 'array',
				),
				array(
					'key'          => 'field_mrn_nested_card_items',
					'label'        => 'Cards',
					'name'         => 'card_items',
					'aria-label'   => '',
					'type'         => 'repeater',
					'layout'       => 'row',
					'collapsed'    => 'field_mrn_nested_card_item_text',
					'button_label' => 'Add Card',
					'min'          => 1,
					'sub_fields'   => array(
						array(
							'key'           => 'field_mrn_nested_card_item_image',
							'label'         => 'Image',
							'name'          => 'image',
							'aria-label'    => '',
							'type'          => 'image',
							'return_format' => 'array',
							'preview_size'  => 'medium',
							'library'       => 'all',
							'wrapper'       => array(
								'width' => '33',
							),
						),
						array(
							'key'          => 'field_mrn_nested_card_item_text',
							'label'        => 'Text',
							'name'         => 'text',
							'aria-label'   => '',
							'type'         => 'wysiwyg',
							'tabs'         => 'all',
							'toolbar'      => 'full',
							'media_upload' => 1,
							'delay'        => 0,
							'wrapper'      => array(
								'width' => '34',
							),
						),
						array(
							'key'           => 'field_mrn_nested_card_item_link',
							'label'         => 'Link',
							'name'          => 'link',
							'aria-label'    => '',
							'type'          => 'link',
							'return_format' => 'array',
							'wrapper'       => array(
								'width' => '33',
							),
						),
					),
				),
				array(
					'key'        => 'field_mrn_nested_card_config_tab',
					'label'      => 'Configs',
					'name'       => '',
					'aria-label' => '',
					'type'       => 'tab',
					'placement'  => 'top',
				),
				array(
					'key'           => 'field_mrn_nested_card_background_color',
					'label'         => 'Background Color',
					'name'          => 'background_color',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => function_exists( 'mrn_rbl_get_site_color_choices' ) ? mrn_rbl_get_site_color_choices() : array(),
					'ui'            => 1,
					'allow_null'    => 1,
					'instructions'  => 'Select from Site Colors when available.',
				),
				array(
					'key'           => 'field_mrn_nested_card_bottom_accent',
					'label'         => 'Accent',
					'name'          => 'bottom_accent',
					'aria-label'    => '',
					'type'          => 'true_false',
					'ui'            => 1,
					'default_value' => 0,
					'ui_on_text'    => 'On',
					'ui_off_text'   => 'Off',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_card_bottom_accent_style',
					'label'         => 'Bottom Accent Style',
					'name'          => 'bottom_accent_style',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => function_exists( 'mrn_site_styles_get_graphic_element_choices' ) ? mrn_site_styles_get_graphic_element_choices() : array( '' => 'Select a Graphic Element' ),
					'default_value' => '',
					'ui'            => 1,
					'allow_null'    => 1,
					'instructions'  => 'Choose a saved graphic element from Site Styles.',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				mrn_base_stack_get_anchor_field( 'field_mrn_nested_card_anchor' ),
				mrn_base_stack_get_motion_group_field( 'field_mrn_nested_card_motion_settings' ),
			),
		),
		'layout_mrn_nested_cta' => array(
			'key'        => 'layout_mrn_nested_cta',
			'name'       => 'cta',
			'label'      => 'CTA - label|heading|subheading|text with editor|link',
			'display'    => 'block',
			'sub_fields' => array(
				array(
					'key'          => 'field_mrn_nested_cta_fields',
					'label'        => 'CTA',
					'name'         => '',
					'aria-label'   => '',
					'type'         => 'clone',
					'clone'        => array( 'group_mrn_reusable_cta' ),
					'display'      => 'seamless',
					'layout'       => 'block',
					'prefix_label' => 0,
					'prefix_name'  => 0,
				),
				mrn_base_stack_get_motion_group_field( 'field_mrn_nested_cta_motion_settings' ),
			),
		),
		'layout_mrn_nested_grid' => array(
			'key'        => 'layout_mrn_nested_grid',
			'name'       => 'grid',
			'label'      => 'Grid - label|heading|subheading|repeater',
			'display'    => 'block',
			'sub_fields' => array(
				array(
					'key'          => 'field_mrn_nested_grid_fields',
					'label'        => 'Grid',
					'name'         => '',
					'aria-label'   => '',
					'type'         => 'clone',
					'clone'        => array( 'group_mrn_reusable_content_grid' ),
					'display'      => 'seamless',
					'layout'       => 'block',
					'prefix_label' => 0,
					'prefix_name'  => 0,
				),
				mrn_base_stack_get_motion_group_field( 'field_mrn_nested_grid_motion_settings' ),
			),
		),
		'layout_mrn_nested_image_content' => array(
			'key'        => 'layout_mrn_nested_image_content',
			'name'       => 'image_content',
			'label'      => 'Image - label|heading|subheading|text with editor',
			'display'    => 'block',
			'sub_fields' => array(
				array(
					'key'           => 'field_mrn_nested_image_content_content_tab',
					'label'         => 'Content',
					'name'          => '',
					'aria-label'    => '',
					'type'          => 'tab',
					'placement'     => 'top',
				),
				array(
					'key'           => 'field_mrn_nested_image_content_image',
					'label'         => 'Image',
					'name'          => 'image',
					'aria-label'    => '',
					'type'          => 'image',
					'return_format' => 'array',
					'preview_size'  => 'medium',
					'library'       => 'all',
				),
				mrn_base_stack_get_inline_text_field( 'field_mrn_nested_image_content_label', 'Label', 'label' ),
				mrn_base_stack_get_label_tag_field( 'field_mrn_nested_image_content_label_tag' ),
				mrn_base_stack_get_inline_text_field( 'field_mrn_nested_image_content_heading', 'Heading', 'heading' ),
				mrn_base_stack_get_text_tag_field( 'field_mrn_nested_image_content_heading_tag', 'heading_tag', 'h2', 'Heading Tag' ),
				mrn_base_stack_get_inline_text_field( 'field_mrn_nested_image_content_subheading', 'Subheading', 'subheading' ),
				mrn_base_stack_get_text_tag_field( 'field_mrn_nested_image_content_subheading_tag', 'subheading_tag', 'p', 'Subheading Tag' ),
				array(
					'key'          => 'field_mrn_nested_image_content_copy',
					'label'        => 'Text area with editor',
					'name'         => 'content',
					'aria-label'   => '',
					'type'         => 'wysiwyg',
					'tabs'         => 'all',
					'toolbar'      => 'full',
					'media_upload' => 1,
					'delay'        => 0,
				),
				array(
					'key'        => 'field_mrn_nested_image_content_config_tab',
					'label'      => 'Configs',
					'name'       => '',
					'aria-label' => '',
					'type'       => 'tab',
					'placement'  => 'top',
					'endpoint'   => 0,
				),
				array(
					'key'           => 'field_mrn_nested_image_content_background_color',
					'label'         => 'Background color',
					'name'          => 'background_color',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => function_exists( 'mrn_rbl_get_site_color_choices' ) ? mrn_rbl_get_site_color_choices() : array(),
					'ui'            => 1,
					'allow_null'    => 1,
					'instructions'  => 'Select from Site Colors when available.',
				),
				array(
					'key'           => 'field_mrn_nested_image_content_bottom_accent',
					'label'         => 'Accent',
					'name'          => 'bottom_accent',
					'aria-label'    => '',
					'type'          => 'true_false',
					'ui'            => 1,
					'default_value' => 0,
					'ui_on_text'    => 'On',
					'ui_off_text'   => 'Off',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_image_content_bottom_accent_style',
					'label'         => 'Accent Style',
					'name'          => 'bottom_accent_style',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => function_exists( 'mrn_site_styles_get_graphic_element_choices' ) ? mrn_site_styles_get_graphic_element_choices() : array( '' => 'Select a Graphic Element' ),
					'default_value' => '',
					'ui'            => 1,
					'allow_null'    => 1,
					'instructions'  => 'Choose a saved graphic element from Site Styles.',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_image_content_full_width',
					'label'         => 'Full width',
					'name'          => 'full_width',
					'aria-label'    => '',
					'type'          => 'true_false',
					'ui'            => 1,
					'default_value' => 0,
					'ui_on_text'    => 'On',
					'ui_off_text'   => 'Off',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_image_content_position',
					'label'         => 'Image position',
					'name'          => 'image_position',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => array(
						'top'    => 'Top',
						'bottom' => 'Bottom',
					),
					'default_value' => 'top',
					'ui'            => 1,
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_image_content_size',
					'label'         => 'Image size',
					'name'          => 'image_size',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => array(
						'contained' => 'Contained',
						'cover'     => 'Cover',
					),
					'default_value' => 'contained',
					'ui'            => 1,
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_image_content_alignment',
					'label'         => 'Image alignment',
					'name'          => 'image_alignment',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => array(
						'left'   => 'Left',
						'center' => 'Center',
						'right'  => 'Right',
					),
					'default_value' => 'center',
					'ui'            => 1,
				),
				mrn_base_stack_get_anchor_field( 'field_mrn_nested_image_content_anchor' ),
				mrn_base_stack_get_motion_group_field( 'field_mrn_nested_image_content_motion_settings' ),
			),
		),
		'layout_mrn_nested_video' => array(
			'key'        => 'layout_mrn_nested_video',
			'name'       => 'video',
			'label'      => 'Video - remote|upload',
			'display'    => 'block',
			'sub_fields' => array(
				array(
					'key'        => 'field_mrn_nested_video_content_tab',
					'label'      => 'Content',
					'name'       => '',
					'aria-label' => '',
					'type'       => 'tab',
					'placement'  => 'top',
				),
				mrn_base_stack_get_inline_text_field( 'field_mrn_nested_video_label', 'Label', 'label' ),
				mrn_base_stack_get_label_tag_field( 'field_mrn_nested_video_label_tag' ),
				mrn_base_stack_get_inline_text_field( 'field_mrn_nested_video_heading', 'Heading', 'heading' ),
				mrn_base_stack_get_text_tag_field( 'field_mrn_nested_video_heading_tag', 'heading_tag', 'h2', 'Heading Tag' ),
				mrn_base_stack_get_inline_text_field( 'field_mrn_nested_video_subheading', 'Subheading', 'subheading' ),
				mrn_base_stack_get_text_tag_field( 'field_mrn_nested_video_subheading_tag', 'subheading_tag', 'p', 'Subheading Tag' ),
				array(
					'key'          => 'field_mrn_nested_video_content',
					'label'        => 'Text area with editor',
					'name'         => 'content',
					'aria-label'   => '',
					'type'         => 'wysiwyg',
					'tabs'         => 'all',
					'toolbar'      => 'full',
					'media_upload' => 1,
					'delay'        => 0,
				),
				array(
					'key'          => 'field_mrn_nested_video_remote',
					'label'        => 'Remote video URL',
					'name'         => 'video_remote',
					'aria-label'   => '',
					'type'         => 'url',
					'instructions' => 'Paste a YouTube or Vimeo URL.',
					'wrapper'      => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_video_upload',
					'label'         => 'Video upload',
					'name'          => 'video_upload',
					'aria-label'    => '',
					'type'          => 'file',
					'return_format' => 'array',
					'library'       => 'all',
					'mime_types'    => 'mp4,webm,mov',
					'instructions'  => 'Optional local upload. When both upload and remote URL are set, the upload is used first.',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'        => 'field_mrn_nested_video_config_tab',
					'label'      => 'Configs',
					'name'       => '',
					'aria-label' => '',
					'type'       => 'tab',
					'placement'  => 'top',
					'endpoint'   => 0,
				),
				array(
					'key'           => 'field_mrn_nested_video_background_color',
					'label'         => 'Background color',
					'name'          => 'background_color',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => function_exists( 'mrn_rbl_get_site_color_choices' ) ? mrn_rbl_get_site_color_choices() : array(),
					'ui'            => 1,
					'allow_null'    => 1,
					'instructions'  => 'Select from Site Colors when available.',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_video_bottom_accent',
					'label'         => 'Accent',
					'name'          => 'bottom_accent',
					'aria-label'    => '',
					'type'          => 'true_false',
					'ui'            => 1,
					'default_value' => 0,
					'ui_on_text'    => 'On',
					'ui_off_text'   => 'Off',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_video_bottom_accent_style',
					'label'         => 'Accent Style',
					'name'          => 'bottom_accent_style',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => function_exists( 'mrn_site_styles_get_graphic_element_choices' ) ? mrn_site_styles_get_graphic_element_choices() : array( '' => 'Select a Graphic Element' ),
					'default_value' => '',
					'ui'            => 1,
					'allow_null'    => 1,
					'instructions'  => 'Choose a saved graphic element from Site Styles.',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				mrn_base_stack_get_anchor_field( 'field_mrn_nested_video_anchor' ),
				mrn_base_stack_get_motion_group_field( 'field_mrn_nested_video_motion_settings' ),
			),
		),
		'layout_mrn_nested_logos' => array(
			'key'        => 'layout_mrn_nested_logos',
			'name'       => 'logos',
			'label'      => 'Logos - label|heading|image|link',
			'display'    => 'block',
			'sub_fields' => array(
				array(
					'key'        => 'field_mrn_nested_logos_content_tab',
					'label'      => 'Content',
					'name'       => '',
					'aria-label' => '',
					'type'       => 'tab',
					'placement'  => 'top',
				),
				mrn_base_stack_get_inline_text_field( 'field_mrn_nested_logos_label', 'Label', 'label' ),
				mrn_base_stack_get_label_tag_field( 'field_mrn_nested_logos_label_tag' ),
				mrn_base_stack_get_inline_text_field( 'field_mrn_nested_logos_heading', 'Heading', 'heading' ),
				mrn_base_stack_get_text_tag_field( 'field_mrn_nested_logos_heading_tag', 'heading_tag', 'h2', 'Heading Tag' ),
				mrn_base_stack_get_inline_text_field( 'field_mrn_nested_logos_subheading', 'Subheading', 'subheading' ),
				mrn_base_stack_get_text_tag_field( 'field_mrn_nested_logos_subheading_tag', 'subheading_tag', 'p', 'Subheading Tag' ),
				array(
					'key'          => 'field_mrn_nested_logos_items',
					'label'        => 'Logos',
					'name'         => 'logo_items',
					'aria-label'   => '',
					'type'         => 'repeater',
					'layout'       => 'row',
					'button_label' => 'Add Logo',
					'min'          => 1,
					'sub_fields'   => array(
						array(
							'key'           => 'field_mrn_nested_logos_item_image',
							'label'         => 'Image',
							'name'          => 'image',
							'aria-label'    => '',
							'type'          => 'image',
							'return_format' => 'array',
							'preview_size'  => 'medium',
							'library'       => 'all',
							'wrapper'       => array(
								'width' => '50',
							),
						),
						array(
							'key'           => 'field_mrn_nested_logos_item_link',
							'label'         => 'Link',
							'name'          => 'link',
							'aria-label'    => '',
							'type'          => 'link',
							'return_format' => 'array',
							'wrapper'       => array(
								'width' => '50',
							),
						),
					),
				),
				array(
					'key'        => 'field_mrn_nested_logos_config_tab',
					'label'      => 'Configs',
					'name'       => '',
					'aria-label' => '',
					'type'       => 'tab',
					'placement'  => 'top',
					'endpoint'   => 0,
				),
				array(
					'key'           => 'field_mrn_nested_logos_display_mode',
					'label'         => 'Display mode',
					'name'          => 'display_mode',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => array(
						'grid'   => 'Grid',
						'slider' => 'Slider',
					),
					'default_value' => 'grid',
					'ui'            => 1,
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_logos_per_page',
					'label'         => 'Logos per row/view',
					'name'          => 'per_page',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => array(
						'3' => '3',
						'4' => '4',
						'5' => '5',
						'6' => '6',
					),
					'default_value' => '4',
					'ui'            => 1,
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_logos_arrows',
					'label'         => 'Show arrows',
					'name'          => 'show_arrows',
					'aria-label'    => '',
					'type'          => 'true_false',
					'ui'            => 1,
					'default_value' => 0,
					'ui_on_text'    => 'On',
					'ui_off_text'   => 'Off',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_logos_pagination',
					'label'         => 'Show pagination',
					'name'          => 'show_pagination',
					'aria-label'    => '',
					'type'          => 'true_false',
					'ui'            => 1,
					'default_value' => 0,
					'ui_on_text'    => 'On',
					'ui_off_text'   => 'Off',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_logos_autoplay',
					'label'         => 'Autoplay',
					'name'          => 'autoplay',
					'aria-label'    => '',
					'type'          => 'true_false',
					'ui'            => 1,
					'default_value' => 0,
					'ui_on_text'    => 'On',
					'ui_off_text'   => 'Off',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_logos_pause_on_hover',
					'label'         => 'Pause on hover',
					'name'          => 'pause_on_hover',
					'aria-label'    => '',
					'type'          => 'true_false',
					'ui'            => 1,
					'default_value' => 1,
					'ui_on_text'    => 'On',
					'ui_off_text'   => 'Off',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_logos_delay_start',
					'label'         => 'Delay start',
					'name'          => 'delay_start',
					'aria-label'    => '',
					'type'          => 'number',
					'default_value' => 0,
					'step'          => 0.1,
					'min'           => 0,
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_logos_delay_time',
					'label'         => 'Delay time',
					'name'          => 'delay_time',
					'aria-label'    => '',
					'type'          => 'number',
					'default_value' => 5,
					'step'          => 0.1,
					'min'           => 0,
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_logos_time_on_slide',
					'label'         => 'Time on slide',
					'name'          => 'time_on_slide',
					'aria-label'    => '',
					'type'          => 'number',
					'default_value' => 600,
					'step'          => 10,
					'min'           => 100,
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_logos_background_color',
					'label'         => 'Background color',
					'name'          => 'background_color',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => function_exists( 'mrn_rbl_get_site_color_choices' ) ? mrn_rbl_get_site_color_choices() : array(),
					'ui'            => 1,
					'allow_null'    => 1,
					'instructions'  => 'Select from Site Colors when available.',
				),
				array(
					'key'           => 'field_mrn_nested_logos_bottom_accent',
					'label'         => 'Accent',
					'name'          => 'bottom_accent',
					'aria-label'    => '',
					'type'          => 'true_false',
					'ui'            => 1,
					'default_value' => 0,
					'ui_on_text'    => 'On',
					'ui_off_text'   => 'Off',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_logos_bottom_accent_style',
					'label'         => 'Accent Style',
					'name'          => 'bottom_accent_style',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => function_exists( 'mrn_site_styles_get_graphic_element_choices' ) ? mrn_site_styles_get_graphic_element_choices() : array( '' => 'Select a Graphic Element' ),
					'default_value' => '',
					'ui'            => 1,
					'allow_null'    => 1,
					'instructions'  => 'Choose a saved graphic element from Site Styles.',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				mrn_base_stack_get_anchor_field( 'field_mrn_nested_logos_anchor' ),
				mrn_base_stack_get_motion_group_field( 'field_mrn_nested_logos_motion_settings' ),
			),
		),
		'layout_mrn_nested_external_widget' => array(
			'key'        => 'layout_mrn_nested_external_widget',
			'name'       => 'external_widget',
			'label'      => 'External - widget/iFrame',
			'display'    => 'block',
			'sub_fields' => array(
				array(
					'key'        => 'field_mrn_nested_external_widget_content_tab',
					'label'      => 'Content',
					'name'       => '',
					'aria-label' => '',
					'type'       => 'tab',
					'placement'  => 'top',
				),
				array(
					'key'          => 'field_mrn_nested_external_widget_code',
					'label'        => 'Snippet/Code',
					'name'         => 'code',
					'aria-label'   => '',
					'type'         => 'textarea',
					'rows'         => 8,
				),
				array(
					'key'        => 'field_mrn_nested_external_widget_config_tab',
					'label'      => 'Configs',
					'name'       => '',
					'aria-label' => '',
					'type'       => 'tab',
					'placement'  => 'top',
					'endpoint'   => 0,
				),
				array(
					'key'           => 'field_mrn_nested_external_widget_background_color',
					'label'         => 'Background color',
					'name'          => 'background_color',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => function_exists( 'mrn_rbl_get_site_color_choices' ) ? mrn_rbl_get_site_color_choices() : array(),
					'ui'            => 1,
					'allow_null'    => 1,
					'instructions'  => 'Select from Site Colors when available.',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_external_widget_bottom_accent',
					'label'         => 'Accent',
					'name'          => 'bottom_accent',
					'aria-label'    => '',
					'type'          => 'true_false',
					'ui'            => 1,
					'default_value' => 0,
					'ui_on_text'    => 'On',
					'ui_off_text'   => 'Off',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_external_widget_bottom_accent_style',
					'label'         => 'Accent Style',
					'name'          => 'bottom_accent_style',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => function_exists( 'mrn_site_styles_get_graphic_element_choices' ) ? mrn_site_styles_get_graphic_element_choices() : array( '' => 'Select a Graphic Element' ),
					'default_value' => '',
					'ui'            => 1,
					'allow_null'    => 1,
					'instructions'  => 'Choose a saved graphic element from Site Styles.',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				mrn_base_stack_get_anchor_field( 'field_mrn_nested_external_widget_anchor' ),
				mrn_base_stack_get_motion_group_field( 'field_mrn_nested_external_widget_motion_settings' ),
			),
		),
		'layout_mrn_nested_wpforms' => array(
			'key'        => 'layout_mrn_nested_wpforms',
			'name'       => 'wpforms',
			'label'      => 'WPForms - label|heading|subheading|rich text|form',
			'display'    => 'block',
			'sub_fields' => array(
				array(
					'key'        => 'field_mrn_nested_wpforms_content_tab',
					'label'      => 'Content',
					'name'       => '',
					'aria-label' => '',
					'type'       => 'tab',
					'placement'  => 'top',
				),
				mrn_base_stack_get_inline_text_field( 'field_mrn_nested_wpforms_label', 'Label', 'label' ),
				mrn_base_stack_get_label_tag_field( 'field_mrn_nested_wpforms_label_tag' ),
				mrn_base_stack_get_inline_text_field( 'field_mrn_nested_wpforms_heading', 'Heading', 'heading' ),
				mrn_base_stack_get_text_tag_field( 'field_mrn_nested_wpforms_heading_tag', 'heading_tag', 'h2', 'Heading Tag' ),
				mrn_base_stack_get_inline_text_field( 'field_mrn_nested_wpforms_subheading', 'Subheading', 'subheading' ),
				mrn_base_stack_get_text_tag_field( 'field_mrn_nested_wpforms_subheading_tag', 'subheading_tag', 'p', 'Subheading Tag' ),
				array(
					'key'          => 'field_mrn_nested_wpforms_intro',
					'label'        => 'Text area with editor',
					'name'         => 'intro',
					'aria-label'   => '',
					'type'         => 'wysiwyg',
					'tabs'         => 'all',
					'toolbar'      => 'full',
					'media_upload' => 1,
					'delay'        => 0,
				),
				array(
					'key'           => 'field_mrn_nested_wpforms_form',
					'label'         => 'Form',
					'name'          => 'form',
					'aria-label'    => '',
					'type'          => 'post_object',
					'post_type'     => array( 'wpforms' ),
					'return_format' => 'object',
					'ui'            => 1,
					'allow_null'    => 0,
					'multiple'      => 0,
					'instructions'  => 'Choose from the WPForms forms available on this site.',
				),
				array(
					'key'        => 'field_mrn_nested_wpforms_config_tab',
					'label'      => 'Configs',
					'name'       => '',
					'aria-label' => '',
					'type'       => 'tab',
					'placement'  => 'top',
					'endpoint'   => 0,
				),
				array(
					'key'           => 'field_mrn_nested_wpforms_background_color',
					'label'         => 'Background color',
					'name'          => 'background_color',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => function_exists( 'mrn_rbl_get_site_color_choices' ) ? mrn_rbl_get_site_color_choices() : array(),
					'ui'            => 1,
					'allow_null'    => 1,
					'instructions'  => 'Select from Site Colors when available.',
				),
				array(
					'key'           => 'field_mrn_nested_wpforms_bottom_accent',
					'label'         => 'Bottom Accent',
					'name'          => 'bottom_accent',
					'aria-label'    => '',
					'type'          => 'true_false',
					'ui'            => 1,
					'default_value' => 0,
					'ui_on_text'    => 'On',
					'ui_off_text'   => 'Off',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_wpforms_bottom_accent_style',
					'label'         => 'Bottom Accent Style',
					'name'          => 'bottom_accent_style',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => function_exists( 'mrn_site_styles_get_graphic_element_choices' ) ? mrn_site_styles_get_graphic_element_choices() : array( '' => 'Select a Graphic Element' ),
					'default_value' => '',
					'ui'            => 1,
					'allow_null'    => 1,
					'instructions'  => 'Choose a saved graphic element from Site Styles.',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				mrn_base_stack_get_anchor_field( 'field_mrn_nested_wpforms_anchor' ),
				mrn_base_stack_get_motion_group_field( 'field_mrn_nested_wpforms_motion_settings' ),
			),
		),
		'layout_mrn_nested_reusable_block' => array(
			'key'        => 'layout_mrn_nested_reusable_block',
			'name'       => 'reusable_block',
			'label'      => 'Reusable Block',
			'display'    => 'block',
			'sub_fields' => array(
				array(
					'key'           => 'field_mrn_nested_reusable_block_post',
					'label'         => 'Block',
					'name'          => 'block',
					'aria-label'    => '',
					'type'          => 'post_object',
					'post_type'     => function_exists( 'mrn_rbl_get_post_types' ) ? mrn_rbl_get_post_types() : array(),
					'return_format' => 'object',
					'ui'            => 1,
					'allow_null'    => 0,
					'multiple'      => 0,
					'instructions'  => 'Choose a reusable block from the library. Editing that block updates it everywhere it is used.',
				),
				mrn_base_stack_get_anchor_field( 'field_mrn_nested_reusable_block_anchor' ),
			),
		),
	);
}
