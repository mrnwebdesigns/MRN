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
 * Hydrate shallow ACF local flexible-content layouts with their local subfields.
 *
 * ACF stores local flexible-content subfields flattened under the parent field
 * and groups them by `parent_layout` during load. Repeating that grouping here
 * lets cloned builder areas reuse complete layouts without hydrating the full
 * field through the heavier runtime path.
 *
 * @param array<string, mixed> $field ACF flexible-content field definition.
 * @return array<string, array<string, mixed>>
 */
function mrn_base_stack_get_hydrated_local_flexible_layouts( array $field ) {
	if ( empty( $field['layouts'] ) || ! is_array( $field['layouts'] ) || ! function_exists( 'acf_get_fields' ) ) {
		return array();
	}

	$sub_fields = acf_get_fields( $field );
	if ( ! is_array( $sub_fields ) ) {
		return array();
	}

	$grouped = array();
	foreach ( $sub_fields as $sub_field ) {
		if ( ! is_array( $sub_field ) ) {
			continue;
		}

		$parent_layout = isset( $sub_field['parent_layout'] ) ? (string) $sub_field['parent_layout'] : '';
		if ( '' === $parent_layout ) {
			continue;
		}

		if ( ! isset( $grouped[ $parent_layout ] ) ) {
			$grouped[ $parent_layout ] = array();
		}

		$grouped[ $parent_layout ][] = $sub_field;
	}

	$layouts = array();
	foreach ( $field['layouts'] as $layout_key => $layout ) {
		if ( ! is_array( $layout ) ) {
			continue;
		}

		$key = isset( $layout['key'] ) ? (string) $layout['key'] : (string) $layout_key;
		if ( '' === $key || empty( $grouped[ $key ] ) ) {
			continue;
		}

		$layout['sub_fields']   = $grouped[ $key ];
		$layouts[ $layout_key ] = $layout;
	}

	return $layouts;
}

/**
 * Get the raw Content builder layout definitions for cloned builder areas.
 *
 * The per-entry layout allowlist filters intentionally narrow the Content
 * field on edit screens. After Content and Sidebar must clone from the full
 * registered Content catalog first, then apply their own target-specific
 * limits so defaults never accidentally shrink the source catalog.
 *
 * @return array<string, array<string, mixed>>
 */
function mrn_base_stack_get_content_builder_source_layouts() {
	if ( function_exists( 'acf_get_local_field' ) ) {
		$field = acf_get_local_field( 'field_mrn_page_content_rows' );
		if ( is_array( $field ) && mrn_base_stack_builder_field_has_complete_layouts( $field ) ) {
			return $field['layouts'];
		}

		if ( is_array( $field ) ) {
			$layouts = mrn_base_stack_get_hydrated_local_flexible_layouts( $field );
			if ( ! empty( $layouts ) ) {
				return $layouts;
			}
		}
	}

	$resolver = static function () {
		if ( ! function_exists( 'acf_get_fields' ) ) {
			return array();
		}

		$fields = acf_get_fields( 'group_mrn_content_builder' );
		if ( ! is_array( $fields ) || empty( $fields[0] ) || ! mrn_base_stack_builder_field_has_complete_layouts( $fields[0] ) ) {
			return array();
		}

		return $fields[0]['layouts'];
	};

	$layouts = function_exists( 'mrn_base_stack_run_without_builder_layout_allowlist_filters' )
		? mrn_base_stack_run_without_builder_layout_allowlist_filters( $resolver )
		: $resolver();

	return is_array( $layouts ) ? $layouts : array();
}

/**
 * Get top-level layout names that can be added to After Content rows.
 *
 * @return array<int, string>
 */
function mrn_base_stack_get_after_content_layout_source_names() {
	$defaults = array(
		'basic_block',
		'two_column_split',
		'logos',
		'reusable_block',
		'cta',
		'faq_jump_nav',
	);

	$names = mrn_base_stack_normalize_builder_layout_source_names(
		apply_filters( 'mrn_base_stack_after_content_layout_source_names', $defaults ),
		$defaults
	);

	return mrn_base_stack_filter_hidden_builder_layout_source_names( $names );
}

/**
 * Clone Content builder layouts for the After Content builder field.
 *
 * @param int $post_id Optional post ID for existing-row compatibility.
 * @return array<string, array<string, mixed>>
 */
function mrn_base_stack_get_after_content_builder_layouts( $post_id = 0 ) {
	static $layouts_cache = array();

	$post_id = absint( $post_id );
	if ( $post_id < 1 && function_exists( 'mrn_base_stack_get_builder_layout_allowlist_post_id' ) ) {
		$post_id = mrn_base_stack_get_builder_layout_allowlist_post_id();
	}

	$cache_key = $post_id > 0 ? 'post_' . $post_id : 'global';

	if ( isset( $layouts_cache[ $cache_key ] ) ) {
		return $layouts_cache[ $cache_key ];
	}

	if ( ! function_exists( 'mrn_base_stack_clone_acf_keys_with_prefix' ) ) {
		$layouts_cache[ $cache_key ] = array();
		return $layouts_cache[ $cache_key ];
	}

	$content_layouts = mrn_base_stack_get_content_builder_source_layouts();
	if ( empty( $content_layouts ) ) {
		$layouts_cache[ $cache_key ] = array();
		return $layouts_cache[ $cache_key ];
	}

	$allowed_names       = mrn_base_stack_get_after_content_layout_source_names();
	$existing_only_names = array();

	if ( $post_id > 0 && function_exists( 'mrn_base_stack_get_builder_layout_allowlist_used_layout_names' ) ) {
		$used_names          = mrn_base_stack_get_builder_layout_allowlist_used_layout_names( $post_id, 'page_after_content_rows' );
		$base_allowed_lookup = ! empty( $allowed_names ) ? array_fill_keys( $allowed_names, true ) : array();
		$existing_only_names = array_values(
			array_diff(
				array_filter(
					array_map( 'sanitize_key', $used_names )
				),
				array_keys( $base_allowed_lookup )
			)
		);
		$allowed_names       = array_values(
			array_unique(
				array_merge(
					$allowed_names,
					$used_names
				)
			)
		);
	}

	$allowed_lookup       = ! empty( $allowed_names ) ? array_fill_keys( $allowed_names, true ) : array();
	$existing_only_lookup = ! empty( $existing_only_names ) ? array_fill_keys( $existing_only_names, true ) : array();
	$after_layouts        = array();

	foreach ( $content_layouts as $layout_key => $layout ) {
		if ( ! is_array( $layout ) ) {
			continue;
		}

		$layout_name = isset( $layout['name'] ) ? sanitize_key( (string) $layout['name'] ) : '';
		if ( '' === $layout_name || ! isset( $allowed_lookup[ $layout_name ] ) ) {
			continue;
		}

		if ( isset( $existing_only_lookup[ $layout_name ] ) ) {
			$layout['max'] = -1;
		}

		$after_layouts[ $layout_key ] = $layout;
	}

	$layouts_cache[ $cache_key ] = ! empty( $after_layouts ) ? mrn_base_stack_clone_acf_keys_with_prefix( $after_layouts, 'after_content_' ) : array();

	return $layouts_cache[ $cache_key ];
}

/**
 * Populate After Content layouts lazily so registration stays lightweight.
 *
 * @param array<string, mixed>|mixed $field ACF field definition.
 * @return array<string, mixed>|mixed
 */
function mrn_base_stack_populate_after_content_builder_field( $field ) {
	if ( ! is_array( $field ) ) {
		return $field;
	}

	$field['layouts'] = mrn_base_stack_get_after_content_builder_layouts();
	$field            = mrn_base_stack_apply_primary_layout_contract_to_flexible_layouts( $field );

	return $field;
}
add_filter( 'acf/load_field/key=field_mrn_page_after_content_rows', 'mrn_base_stack_populate_after_content_builder_field', 15 );
add_filter( 'acf/prepare_field/key=field_mrn_page_after_content_rows', 'mrn_base_stack_populate_after_content_builder_field', 15 );

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

	$meta         = get_post_meta( $post_id, '', false );
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
 * Get nested Card item layout names already saved in post meta.
 *
 * @param int $post_id Post ID.
 * @return array<int, string>
 */
function mrn_base_stack_get_card_used_nested_layout_names( $post_id ) {
	static $cache = array();

	$post_id = absint( $post_id );

	if ( $post_id < 1 ) {
		return array();
	}

	if ( isset( $cache[ $post_id ] ) ) {
		return $cache[ $post_id ];
	}

	$meta         = get_post_meta( $post_id, '', false );
	$layout_names = array();

	if ( ! is_array( $meta ) ) {
		$cache[ $post_id ] = array();
		return $cache[ $post_id ];
	}

	foreach ( $meta as $meta_key => $values ) {
		if ( ! is_string( $meta_key ) || 0 !== strpos( $meta_key, 'page_content_rows_' ) ) {
			continue;
		}

		if ( false === strpos( $meta_key, '_card_rows_' ) || 0 !== substr_compare( $meta_key, '_acf_fc_layout', -14 ) ) {
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
 * Get nested two-column layout names already saved in post meta.
 *
 * @param int $post_id Post ID.
 * @return array<int, string>
 */
function mrn_base_stack_get_two_column_used_nested_layout_names( $post_id ) {
	static $cache = array();

	$post_id = absint( $post_id );

	if ( $post_id < 1 ) {
		return array();
	}

	if ( isset( $cache[ $post_id ] ) ) {
		return $cache[ $post_id ];
	}

	$meta         = get_post_meta( $post_id, '', false );
	$layout_names = array();

	if ( ! is_array( $meta ) ) {
		$cache[ $post_id ] = array();
		return $cache[ $post_id ];
	}

	foreach ( $meta as $meta_key => $values ) {
		if ( ! is_string( $meta_key ) ) {
			continue;
		}

		$is_column_layout_key = false !== strpos( $meta_key, '_left_column_rows_' )
			|| false !== strpos( $meta_key, '_right_column_rows_' );

		if ( ! $is_column_layout_key || 0 !== substr_compare( $meta_key, '_acf_fc_layout', -14 ) ) {
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
	$defaults = array( 'basic_block', 'two_column_split' );
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
 * Build the hero title mode field key for a cloned hero layout.
 *
 * @param string $layout_name ACF layout name.
 * @return string
 */
function mrn_base_stack_get_hero_heading_mode_field_key( $layout_name ) {
	$layout_name = sanitize_key( (string) $layout_name );
	if ( '' === $layout_name ) {
		$layout_name = 'row';
	}

	return 'field_mrn_hero_' . $layout_name . '_heading_mode';
}

/**
 * Build the hero title mode control for cloned Hero builder layouts.
 *
 * @param string $layout_name ACF layout name.
 * @return array<string, mixed>
 */
function mrn_base_stack_get_hero_heading_mode_field( $layout_name ) {
	return array(
		'key'           => mrn_base_stack_get_hero_heading_mode_field_key( $layout_name ),
		'label'         => 'Hero Title',
		'name'          => 'hero_heading_mode',
		'_name'         => 'hero_heading_mode',
		'aria-label'    => '',
		'type'          => 'button_group',
		'instructions'  => 'When this row is placed in the Hero area, the normal content title is hidden. Use Page Title renders the page/post title as the hero H1. Custom Title uses the Heading field as the hero H1. Page Title + Custom keeps the page/post title as the hero H1 and places the Heading field below it.',
		'choices'       => array(
			'title'        => 'Use Page Title',
			'custom_title' => 'Custom Title',
			'title_custom' => 'Page Title + Custom',
		),
		'default_value' => 'title',
		'return_format' => 'value',
		'layout'        => 'horizontal',
		'wrapper'       => array(
			'width' => '100',
		),
	);
}

/**
 * Add hero title behavior controls to cloned Hero layouts.
 *
 * @param array<string, mixed> $layout ACF layout definition.
 * @return array<string, mixed>
 */
function mrn_base_stack_add_hero_heading_fields_to_layout( array $layout ) {
	$layout_name = isset( $layout['name'] ) ? sanitize_key( (string) $layout['name'] ) : '';
	if ( ! isset( $layout['sub_fields'] ) || ! is_array( $layout['sub_fields'] ) ) {
		$layout['sub_fields'] = array();
	}

	$mode_field_key   = mrn_base_stack_get_hero_heading_mode_field_key( $layout_name );
	$has_mode_field   = false;
	$heading_index    = null;
	$heading_tag_keys = array();

	foreach ( $layout['sub_fields'] as $index => $field ) {
		if ( ! is_array( $field ) ) {
			continue;
		}

		$field_type = isset( $field['type'] ) ? sanitize_key( (string) $field['type'] ) : '';
		$field_name = isset( $field['name'] ) ? sanitize_key( (string) $field['name'] ) : '';
		$field_key  = isset( $field['key'] ) && is_string( $field['key'] ) ? trim( $field['key'] ) : '';

		if ( 'hero_heading_mode' === $field_name ) {
			$has_mode_field = true;
			if ( '' !== $field_key ) {
				$mode_field_key = $field_key;
			}
			continue;
		}

		if ( 'heading' === $field_name && 'text' === $field_type && null === $heading_index ) {
			$heading_index = (int) $index;
			continue;
		}

		if ( 'heading_tag' === $field_name && 'select' === $field_type ) {
			$heading_tag_keys[] = (int) $index;
		}
	}

	if ( null === $heading_index ) {
		return $layout;
	}

	if ( ! $has_mode_field ) {
		array_splice( $layout['sub_fields'], $heading_index, 0, array( mrn_base_stack_get_hero_heading_mode_field( $layout_name ) ) );
		++$heading_index;

		foreach ( $heading_tag_keys as $key_index => $field_index ) {
			if ( $field_index >= $heading_index ) {
				$heading_tag_keys[ $key_index ] = $field_index + 1;
			}
		}
	}

	$custom_title_logic          = array(
		array(
			array(
				'field'    => $mode_field_key,
				'operator' => '==',
				'value'    => 'custom_title',
			),
		),
		array(
			array(
				'field'    => $mode_field_key,
				'operator' => '==',
				'value'    => 'title_custom',
			),
		),
	);
	$secondary_heading_tag_logic = array(
		array(
			array(
				'field'    => $mode_field_key,
				'operator' => '==',
				'value'    => 'title_custom',
			),
		),
	);

	if ( isset( $layout['sub_fields'][ $heading_index ] ) && is_array( $layout['sub_fields'][ $heading_index ] ) ) {
		$layout['sub_fields'][ $heading_index ]['label']             = 'Heading';
		$layout['sub_fields'][ $heading_index ]['instructions']      = 'Required for Custom Title. For Page Title + Custom, this displays below the page/post title inside the hero.';
		$layout['sub_fields'][ $heading_index ]['conditional_logic'] = $custom_title_logic;
		if ( ! isset( $layout['sub_fields'][ $heading_index ]['wrapper'] ) || ! is_array( $layout['sub_fields'][ $heading_index ]['wrapper'] ) ) {
			$layout['sub_fields'][ $heading_index ]['wrapper'] = array();
		}
		$layout['sub_fields'][ $heading_index ]['wrapper']['width'] = '75';
	}

	foreach ( $heading_tag_keys as $heading_tag_index ) {
		if ( ! isset( $layout['sub_fields'][ $heading_tag_index ] ) || ! is_array( $layout['sub_fields'][ $heading_tag_index ] ) ) {
			continue;
		}

		$layout['sub_fields'][ $heading_tag_index ]['label']             = 'Custom Heading Tag';
		$layout['sub_fields'][ $heading_tag_index ]['instructions']      = 'Controls the Page Title + Custom secondary heading only. Custom Title renders as the hero H1.';
		$layout['sub_fields'][ $heading_tag_index ]['default_value']     = 'h2';
		$layout['sub_fields'][ $heading_tag_index ]['conditional_logic'] = $secondary_heading_tag_logic;

		if ( isset( $layout['sub_fields'][ $heading_tag_index ]['choices'] ) && is_array( $layout['sub_fields'][ $heading_tag_index ]['choices'] ) ) {
			unset( $layout['sub_fields'][ $heading_tag_index ]['choices']['h1'] );
		}

		if ( ! isset( $layout['sub_fields'][ $heading_tag_index ]['wrapper'] ) || ! is_array( $layout['sub_fields'][ $heading_tag_index ]['wrapper'] ) ) {
			$layout['sub_fields'][ $heading_tag_index ]['wrapper'] = array();
		}
		$layout['sub_fields'][ $heading_tag_index ]['wrapper']['width'] = '25';
	}

	return $layout;
}

/**
 * Resolve the hero page title and optional custom heading contract.
 *
 * @param array<string, mixed> $row Builder row data.
 * @param int|string           $post_id Current post ID.
 * @param string               $default_custom_tag Default tag for the optional custom heading.
 * @return array<string, string>
 */
function mrn_base_stack_get_hero_heading_contract( array $row, $post_id, $default_custom_tag = 'h2' ) {
	$post_id        = (int) $post_id;
	$page_title     = $post_id ? trim( (string) get_the_title( $post_id ) ) : '';
	$custom_heading = isset( $row['heading'] ) ? trim( (string) $row['heading'] ) : '';
	$raw_mode       = isset( $row['hero_heading_mode'] ) && is_scalar( $row['hero_heading_mode'] )
		? sanitize_key( (string) $row['hero_heading_mode'] )
		: '';
	$mode           = in_array( $raw_mode, array( 'title', 'custom_title', 'title_custom' ), true )
		? $raw_mode
		: 'title';

	if ( 'custom_title' === $mode ) {
		$page_title     = $custom_heading;
		$custom_heading = '';
	} elseif ( 'title_custom' !== $mode ) {
		$custom_heading = '';
	}

	$allowed_custom_tags = array( 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'span', 'div' );
	$custom_heading_tag  = isset( $row['heading_tag'] ) && is_scalar( $row['heading_tag'] )
		? strtolower( sanitize_key( (string) $row['heading_tag'] ) )
		: strtolower( sanitize_key( (string) $default_custom_tag ) );

	if ( ! in_array( $custom_heading_tag, $allowed_custom_tags, true ) ) {
		$default_custom_tag = strtolower( sanitize_key( (string) $default_custom_tag ) );
		$custom_heading_tag = in_array( $default_custom_tag, $allowed_custom_tags, true ) ? $default_custom_tag : 'h2';
	}

	return array(
		'mode'               => $mode,
		'page_title'         => $page_title,
		'custom_heading'     => $custom_heading,
		'custom_heading_tag' => $custom_heading_tag,
	);
}

/**
 * Normalize a filterable list of layout names.
 *
 * @param mixed              $names Layout names supplied by a filter.
 * @param array<int, string> $defaults Default layout names.
 * @return array<int, string>
 */
function mrn_base_stack_normalize_builder_layout_source_names( $names, array $defaults ) {
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
 * Remove site-wide hidden layouts from an add-row source list.
 *
 * @param array<int, string> $names Layout names.
 * @return array<int, string>
 */
function mrn_base_stack_filter_hidden_builder_layout_source_names( array $names ) {
	if ( ! function_exists( 'mrn_base_stack_get_raw_sitewide_hidden_builder_layout_names' ) ) {
		return $names;
	}

	$hidden_names = mrn_base_stack_get_raw_sitewide_hidden_builder_layout_names();
	if ( empty( $hidden_names ) || ! is_array( $hidden_names ) ) {
		return $names;
	}

	return array_values( array_diff( $names, $hidden_names ) );
}

/**
 * Remove layouts unavailable in the current editor context from a source list.
 *
 * @param array<int, string> $names Layout names.
 * @return array<int, string>
 */
function mrn_base_stack_filter_builder_layout_source_names_for_context( array $names ) {
	$names = mrn_base_stack_filter_hidden_builder_layout_source_names( $names );

	if ( ! function_exists( 'mrn_base_stack_get_post_type_allowed_builder_layout_names' ) ) {
		return $names;
	}

	$allowed_names = mrn_base_stack_get_post_type_allowed_builder_layout_names();
	if ( ! is_array( $allowed_names ) ) {
		return $names;
	}

	return array_values( array_intersect( $names, $allowed_names ) );
}

/**
 * Get content layout names that should be available inside Tabbed Layout panels.
 *
 * @return array<int, string>
 */
function mrn_base_stack_get_tabbed_layout_source_names() {
	$defaults = array(
		'body_text',
		'basic_block',
		'cta',
		'image_content',
		'external_widget',
		'wpforms',
		'searchwp_form',
		'video',
		'reusable_block',
	);

	$names = mrn_base_stack_normalize_builder_layout_source_names(
		apply_filters( 'mrn_base_stack_tabbed_layout_source_names', $defaults ),
		$defaults
	);

	return mrn_base_stack_filter_hidden_builder_layout_source_names( $names );
}

/**
 * Get content layout names that should be available inside Card items.
 *
 * @return array<int, string>
 */
function mrn_base_stack_get_card_layout_source_names() {
	$defaults = array(
		'body_text',
		'basic_block',
		'cta',
		'image_content',
		'external_widget',
		'wpforms',
		'searchwp_form',
		'video',
		'reusable_block',
	);

	$names = mrn_base_stack_normalize_builder_layout_source_names(
		apply_filters( 'mrn_base_stack_card_layout_source_names', $defaults ),
		$defaults
	);

	return mrn_base_stack_filter_hidden_builder_layout_source_names( $names );
}

/**
 * Get nested layout names that should be available inside Two Column Split columns.
 *
 * @return array<int, string>
 */
function mrn_base_stack_get_two_column_column_layout_source_names() {
	$defaults = array(
		'body_text',
		'basic',
		'cta',
		'image_content',
		'video',
		'external_widget',
		'wpforms',
		'reusable_block',
		'faq_jump_nav',
	);

	$names = mrn_base_stack_normalize_builder_layout_source_names(
		apply_filters( 'mrn_base_stack_two_column_column_layout_source_names', $defaults ),
		$defaults
	);

	return mrn_base_stack_filter_hidden_builder_layout_source_names( $names );
}

/**
 * Build hero-only sizing controls for cloned Hero builder layouts.
 *
 * @param string $layout_name Builder layout name.
 * @return array<int, array<string, mixed>>
 */
function mrn_base_stack_get_hero_sizing_fields( $layout_name = '' ) {
	$layout_name = sanitize_key( (string) $layout_name );
	$key_prefix  = 'field_mrn_hero';
	if ( '' !== $layout_name && 'basic' !== $layout_name ) {
		$key_prefix .= '_' . $layout_name;
	}

	return array(
		array(
			'key'           => $key_prefix . '_min_height',
			'label'         => 'Hero Minimum Height',
			'name'          => 'hero_min_height',
			'_name'         => 'hero_min_height',
			'aria-label'    => '',
			'type'          => 'text',
			'default_value' => '',
			'placeholder'   => 'Example: 28rem, 70vh, clamp(18rem, 42vw, 34rem)',
			'instructions'  => 'Optional. Leave blank for content-driven height.',
			'wrapper'       => array(
				'width' => '50',
			),
		),
		array(
			'key'           => $key_prefix . '_vertical_padding',
			'label'         => 'Hero Vertical Padding',
			'name'          => 'hero_vertical_padding',
			'_name'         => 'hero_vertical_padding',
			'aria-label'    => '',
			'type'          => 'text',
			'default_value' => '',
			'placeholder'   => 'Example: 4rem, 8vw, clamp(3rem, 8vw, 7rem)',
			'instructions'  => 'Optional. Applies top and bottom padding inside this hero only.',
			'wrapper'       => array(
				'width' => '50',
			),
		),
	);
}

/**
 * Get Hero content alignment choices.
 *
 * @return array<string, string>
 */
function mrn_base_stack_get_hero_content_alignment_choices() {
	return array(
		'left'   => __( 'Left', 'mrn-base-stack' ),
		'center' => __( 'Center', 'mrn-base-stack' ),
		'right'  => __( 'Right', 'mrn-base-stack' ),
	);
}

/**
 * Normalize Hero content alignment.
 *
 * @param string $alignment Candidate alignment.
 * @return string
 */
function mrn_base_stack_normalize_hero_content_alignment( $alignment ) {
	$alignment = sanitize_key( (string) $alignment );
	$choices   = mrn_base_stack_get_hero_content_alignment_choices();

	return isset( $choices[ $alignment ] ) ? $alignment : 'left';
}

/**
 * Get Hero vertical alignment choices.
 *
 * @return array<string, string>
 */
function mrn_base_stack_get_hero_vertical_alignment_choices() {
	return array(
		'top'    => __( 'Top', 'mrn-base-stack' ),
		'center' => __( 'Center', 'mrn-base-stack' ),
		'bottom' => __( 'Bottom', 'mrn-base-stack' ),
	);
}

/**
 * Normalize Hero vertical alignment.
 *
 * @param string $alignment Candidate alignment.
 * @return string
 */
function mrn_base_stack_normalize_hero_vertical_alignment( $alignment ) {
	$alignment = sanitize_key( (string) $alignment );
	$choices   = mrn_base_stack_get_hero_vertical_alignment_choices();

	return isset( $choices[ $alignment ] ) ? $alignment : 'center';
}

/**
 * Build hero-only Layout controls for cloned Hero builder layouts.
 *
 * @param string $layout_name Builder layout name.
 * @return array<int, array<string, mixed>>
 */
function mrn_base_stack_get_hero_layout_fields( $layout_name = '' ) {
	$layout_name = sanitize_key( (string) $layout_name );
	$key_prefix  = 'field_mrn_hero';
	if ( '' !== $layout_name && 'basic' !== $layout_name ) {
		$key_prefix .= '_' . $layout_name;
	}

	return array_merge(
		array(
			array(
				'key'           => $key_prefix . '_content_alignment',
				'label'         => 'Content Alignment',
				'name'          => 'hero_content_alignment',
				'_name'         => 'hero_content_alignment',
				'aria-label'    => '',
				'type'          => 'select',
				'choices'       => mrn_base_stack_get_hero_content_alignment_choices(),
				'default_value' => 'left',
				'allow_null'    => 0,
				'multiple'      => 0,
				'return_format' => 'value',
				'ui'            => 1,
				'instructions'  => 'Aligns the hero title, text, and actions within the hero content area.',
				'wrapper'       => array(
					'width' => '50',
				),
			),
			array(
				'key'           => $key_prefix . '_vertical_alignment',
				'label'         => 'Vertical Alignment',
				'name'          => 'hero_vertical_alignment',
				'_name'         => 'hero_vertical_alignment',
				'aria-label'    => '',
				'type'          => 'select',
				'choices'       => mrn_base_stack_get_hero_vertical_alignment_choices(),
				'default_value' => 'center',
				'allow_null'    => 0,
				'multiple'      => 0,
				'return_format' => 'value',
				'ui'            => 1,
				'instructions'  => 'Aligns hero content vertically when this hero has enough height for vertical positioning.',
				'wrapper'       => array(
					'width' => '50',
				),
			),
		),
		mrn_base_stack_get_background_capability_fields( $key_prefix . '_background' )
	);
}

/**
 * Place Hero-only controls inside the shared Layout tab.
 *
 * @param array<int, mixed> $fields Layout field definitions.
 * @param string            $layout_name Builder layout name.
 * @param bool              $add_missing Whether missing hero fields should be generated.
 * @return array<int, mixed>
 */
function mrn_base_stack_apply_hero_layout_tab_contract( array $fields, $layout_name, $add_missing = false ) {
	$layout_name = sanitize_key( (string) $layout_name );
	if ( ! mrn_base_stack_hero_layout_supports_sizing( $layout_name ) ) {
		return $fields;
	}

	$layout_field_map = array();
	foreach ( mrn_base_stack_get_hero_layout_fields( $layout_name ) as $layout_field ) {
		if ( isset( $layout_field['name'] ) ) {
			$layout_field_map[ sanitize_key( (string) $layout_field['name'] ) ] = $layout_field;
		}
	}

	$found_hero_layout_fields = false;
	$remaining_fields         = array();

	foreach ( $fields as $field ) {
		if ( ! is_array( $field ) ) {
			$remaining_fields[] = $field;
			continue;
		}

		$field_name = isset( $field['name'] ) ? sanitize_key( (string) $field['name'] ) : '';
		if ( '' !== $field_name && isset( $layout_field_map[ $field_name ] ) ) {
			if ( 0 === strpos( $field_name, 'hero_' ) ) {
				$found_hero_layout_fields = true;
			}
			foreach ( array( 'key', '_name', 'parent', 'parent_layout', 'default_value', 'conditional_logic' ) as $preserved_key ) {
				if ( array_key_exists( $preserved_key, $field ) ) {
					$layout_field_map[ $field_name ][ $preserved_key ] = $field[ $preserved_key ];
				}
			}
			continue;
		}

		$remaining_fields[] = $field;
	}

	if ( ! $add_missing && ! $found_hero_layout_fields ) {
		return $fields;
	}

	$layout_fields = array();
	foreach ( mrn_base_stack_get_hero_layout_fields( $layout_name ) as $field ) {
		$field_name = isset( $field['name'] ) ? sanitize_key( (string) $field['name'] ) : '';
		if ( '' !== $field_name && isset( $layout_field_map[ $field_name ] ) ) {
			$layout_fields[] = $layout_field_map[ $field_name ];
		}
	}

	$remaining_fields = mrn_base_stack_ensure_builder_layout_tab( array_values( $remaining_fields ), $layout_name, 'field_mrn_hero_' . $layout_name );

	$insert_index = count( $remaining_fields );
	foreach ( $remaining_fields as $index => $field ) {
		if ( ! is_array( $field ) ) {
			continue;
		}

		$field_type  = isset( $field['type'] ) ? sanitize_key( (string) $field['type'] ) : '';
		$field_label = isset( $field['label'] ) ? sanitize_title( (string) $field['label'] ) : '';

		if ( 'tab' === $field_type && 'layout' === $field_label ) {
			$insert_index = (int) $index + 1;
			continue;
		}

		if ( 'tab' === $field_type && 'effects' === $field_label ) {
			break;
		}
	}

	array_splice( $remaining_fields, $insert_index, 0, $layout_fields );

	return array_values( $remaining_fields );
}

/**
 * Determine whether a hero layout receives hero-only sizing controls.
 *
 * @param string $layout_name Builder layout name.
 * @return bool
 */
function mrn_base_stack_hero_layout_supports_sizing( $layout_name ) {
	return in_array( sanitize_key( (string) $layout_name ), array( 'basic_block', 'two_column_split' ), true );
}

/**
 * Build the hero-only Spacing accordion field.
 *
 * @param string $key Field key.
 * @return array<string, mixed>
 */
function mrn_base_stack_get_hero_spacing_accordion_field( $key ) {
	return array(
		'key'          => sanitize_key( (string) $key ),
		'label'        => 'Hero Specific',
		'name'         => '',
		'aria-label'   => '',
		'type'         => 'accordion',
		'open'         => 0,
		'multi_expand' => 1,
		'endpoint'     => 0,
	);
}

/**
 * Build the end marker for the hero-only Spacing accordion.
 *
 * @param string $key Field key.
 * @return array<string, mixed>
 */
function mrn_base_stack_get_hero_spacing_accordion_end_field( $key ) {
	return array(
		'key'          => sanitize_key( (string) $key ),
		'label'        => '',
		'name'         => '',
		'aria-label'   => '',
		'type'         => 'accordion',
		'endpoint'     => 1,
		'multi_expand' => 1,
	);
}

/**
 * Move Hero-only spacing controls into the existing Spacing tab.
 *
 * @param array<int, mixed> $fields Layout field definitions.
 * @param string            $layout_name Builder layout name.
 * @return array<int, mixed>
 */
function mrn_base_stack_apply_hero_spacing_tab_contract( array $fields, $layout_name ) {
	$layout_name = sanitize_key( (string) $layout_name );
	if ( ! mrn_base_stack_hero_layout_supports_sizing( $layout_name ) ) {
		return $fields;
	}

	$hero_spacing_fields = array();
	$remaining_fields    = array();
	$has_hero_sizing     = false;
	$tab_key_seed        = 'field_mrn_hero_' . $layout_name . '_spacing';

	foreach ( $fields as $field ) {
		if ( ! is_array( $field ) ) {
			$remaining_fields[] = $field;
			continue;
		}

		$field_type  = isset( $field['type'] ) ? sanitize_key( (string) $field['type'] ) : '';
		$field_name  = isset( $field['name'] ) ? sanitize_key( (string) $field['name'] ) : '';
		$field_label = isset( $field['label'] ) ? sanitize_title( (string) $field['label'] ) : '';
		$field_key   = isset( $field['key'] ) ? sanitize_key( (string) $field['key'] ) : '';

		if ( 'tab' === $field_type && 'hero-specific' === $field_label ) {
			continue;
		}

		if ( 'accordion' === $field_type && ( 'hero-specific' === $field_label || 0 === strpos( $field_key, $tab_key_seed ) ) ) {
			continue;
		}

		if ( in_array( $field_name, array( 'hero_min_height', 'hero_vertical_padding' ), true ) ) {
			$hero_spacing_fields[] = $field;
			$has_hero_sizing       = true;
			continue;
		}

		$remaining_fields[] = $field;
	}

	if ( ! $has_hero_sizing ) {
		return $fields;
	}

	$insert_index      = count( $remaining_fields );
	$spacing_tab_index = null;

	foreach ( $remaining_fields as $index => $field ) {
		if ( ! is_array( $field ) ) {
			continue;
		}

		$field_type  = isset( $field['type'] ) ? sanitize_key( (string) $field['type'] ) : '';
		$field_label = isset( $field['label'] ) ? sanitize_title( (string) $field['label'] ) : '';

		if ( 'tab' === $field_type && 'spacing' === $field_label ) {
			$spacing_tab_index = (int) $index;
			continue;
		}

		if ( 'tab' === $field_type && in_array( $field_label, array( 'layout', 'effects' ), true ) ) {
			$insert_index = (int) $index;
			break;
		}
	}

	if ( null !== $spacing_tab_index && $insert_index <= $spacing_tab_index ) {
		$insert_index = $spacing_tab_index + 1;
	}

	array_splice(
		$remaining_fields,
		$insert_index,
		0,
		array_merge(
			array( mrn_base_stack_get_hero_spacing_accordion_field( $tab_key_seed . '_hero_specific' ) ),
			$hero_spacing_fields,
			array( mrn_base_stack_get_hero_spacing_accordion_end_field( $tab_key_seed . '_hero_specific_end' ) )
		)
	);

	return mrn_base_stack_ensure_builder_layout_tab( array_values( $remaining_fields ), $layout_name );
}

/**
 * Add hero-only sizing controls to cloned Hero layouts.
 *
 * @param array<string, mixed> $layout ACF layout definition.
 * @return array<string, mixed>
 */
function mrn_base_stack_add_hero_sizing_fields_to_layout( array $layout ) {
	$layout_name = isset( $layout['name'] ) ? sanitize_key( (string) $layout['name'] ) : '';
	if ( ! mrn_base_stack_hero_layout_supports_sizing( $layout_name ) ) {
		return $layout;
	}

	if ( ! isset( $layout['sub_fields'] ) || ! is_array( $layout['sub_fields'] ) ) {
		$layout['sub_fields'] = array();
	}

	foreach ( $layout['sub_fields'] as $field ) {
		if ( ! is_array( $field ) ) {
			continue;
		}

		$field_name = isset( $field['name'] ) ? sanitize_key( (string) $field['name'] ) : '';
		if ( in_array( $field_name, array( 'hero_min_height', 'hero_vertical_padding' ), true ) ) {
			$layout['sub_fields'] = mrn_base_stack_apply_hero_spacing_tab_contract( $layout['sub_fields'], $layout_name );
			return $layout;
		}
	}

	$insert_index = count( $layout['sub_fields'] );
	foreach ( $layout['sub_fields'] as $index => $field ) {
		if ( ! is_array( $field ) ) {
			continue;
		}

		$field_name = isset( $field['name'] ) ? sanitize_key( (string) $field['name'] ) : '';
		if ( 'section_width' === $field_name ) {
			$insert_index = (int) $index + 1;
			break;
		}
	}

	array_splice( $layout['sub_fields'], $insert_index, 0, mrn_base_stack_get_hero_sizing_fields( $layout_name ) );
	$layout['sub_fields'] = mrn_base_stack_apply_hero_spacing_tab_contract( $layout['sub_fields'], $layout_name );

	return $layout;
}

/**
 * Add hero-only Layout controls to cloned Hero layouts.
 *
 * @param array<string, mixed> $layout ACF layout definition.
 * @return array<string, mixed>
 */
function mrn_base_stack_add_hero_layout_fields_to_layout( array $layout ) {
	$layout_name = isset( $layout['name'] ) ? sanitize_key( (string) $layout['name'] ) : '';
	if ( ! mrn_base_stack_hero_layout_supports_sizing( $layout_name ) ) {
		return $layout;
	}

	if ( ! isset( $layout['sub_fields'] ) || ! is_array( $layout['sub_fields'] ) ) {
		$layout['sub_fields'] = array();
	}

	$layout['sub_fields'] = mrn_base_stack_apply_hero_layout_tab_contract( $layout['sub_fields'], $layout_name, true );

	return $layout;
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

		$cloned_layout          = mrn_base_stack_clone_acf_keys_with_prefix( $layout, 'field_mrn_hero_' );
		$cloned_layout          = mrn_base_stack_add_hero_heading_fields_to_layout( $cloned_layout );
		$cloned_layout          = mrn_base_stack_add_hero_sizing_fields_to_layout( $cloned_layout );
		$cloned_layout          = mrn_base_stack_add_hero_layout_fields_to_layout( $cloned_layout );
		$cloned_key             = 'layout_mrn_hero_' . $layout_name;
		$cloned_layout['key']   = $cloned_key;
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

	$post_id   = function_exists( 'mrn_base_stack_get_builder_layout_allowlist_post_id' ) ? mrn_base_stack_get_builder_layout_allowlist_post_id() : 0;
	$cache_key = $post_id > 0 ? 'post_' . $post_id : 'global';

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

	$allowed_names       = mrn_base_stack_get_tabbed_layout_source_names();
	$base_allowed_lookup = ! empty( $allowed_names ) ? array_fill_keys( $allowed_names, true ) : array();
	$used_nested_names   = $post_id > 0 ? mrn_base_stack_get_tabbed_layout_used_nested_layout_names( $post_id ) : array();
	$existing_only_names = array_values(
		array_diff(
			array_filter(
				array_map( 'sanitize_key', $used_nested_names )
			),
			array_keys( $base_allowed_lookup )
		)
	);
	$allowed_names       = array_values(
		array_unique(
			array_merge(
				$allowed_names,
				$used_nested_names
			)
		)
	);

	$allowed_names        = array_values(
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
	$allowed_lookup       = ! empty( $allowed_names ) ? array_fill_keys( $allowed_names, true ) : array();
	$existing_only_lookup = ! empty( $existing_only_names ) ? array_fill_keys( $existing_only_names, true ) : array();
	$layouts              = array();

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

		if ( isset( $existing_only_lookup[ $layout_name ] ) ) {
			$layout['max'] = -1;
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
 * Clone page-builder layouts for use inside Card items.
 *
 * @return array<string, array<string, mixed>>
 */
function mrn_base_stack_get_card_nested_layouts() {
	static $layouts_cache = array();
	static $loading       = false;

	$post_id   = function_exists( 'mrn_base_stack_get_builder_layout_allowlist_post_id' ) ? mrn_base_stack_get_builder_layout_allowlist_post_id() : 0;
	$cache_key = $post_id > 0 ? 'post_' . $post_id : 'global';

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

	$allowed_names       = mrn_base_stack_get_card_layout_source_names();
	$base_allowed_lookup = ! empty( $allowed_names ) ? array_fill_keys( $allowed_names, true ) : array();
	$used_nested_names   = $post_id > 0 ? mrn_base_stack_get_card_used_nested_layout_names( $post_id ) : array();
	$existing_only_names = array_values(
		array_diff(
			array_filter(
				array_map( 'sanitize_key', $used_nested_names )
			),
			array_keys( $base_allowed_lookup )
		)
	);
	$allowed_names       = array_values(
		array_unique(
			array_merge(
				$allowed_names,
				$used_nested_names
			)
		)
	);

	$allowed_names        = array_values(
		array_diff(
			array_values(
				array_unique(
					array_filter(
						array_map( 'sanitize_key', $allowed_names )
					)
				)
			),
			array( 'card' )
		)
	);
	$allowed_lookup       = ! empty( $allowed_names ) ? array_fill_keys( $allowed_names, true ) : array();
	$existing_only_lookup = ! empty( $existing_only_names ) ? array_fill_keys( $existing_only_names, true ) : array();
	$layouts              = array();

	foreach ( $field['layouts'] as $layout_key => $layout ) {
		if ( ! is_array( $layout ) ) {
			continue;
		}

		if ( ! isset( $layout['sub_fields'] ) || ! is_array( $layout['sub_fields'] ) ) {
			$layout['sub_fields'] = array();
		}

		$layout_name = isset( $layout['name'] ) ? sanitize_key( (string) $layout['name'] ) : '';
		if ( '' === $layout_name || 'card' === $layout_name ) {
			continue;
		}

		if ( ! empty( $allowed_lookup ) && ! isset( $allowed_lookup[ $layout_name ] ) ) {
			continue;
		}

		if ( isset( $existing_only_lookup[ $layout_name ] ) ) {
			$layout['max'] = -1;
		}

		$cloned_layout        = mrn_base_stack_clone_acf_keys_with_prefix( $layout, 'field_mrn_card_item_row_' );
		$cloned_key           = 'layout_mrn_card_item_row_' . $layout_name;
		$cloned_layout['key'] = $cloned_key;

		$layouts[ $cloned_key ] = $cloned_layout;
	}

	$layouts_cache[ $cache_key ] = $layouts;

	return $layouts_cache[ $cache_key ];
}

/**
 * Populate Card item nested row fields with builder layouts.
 *
 * @param array<string, mixed> $field ACF field definition.
 * @return array<string, mixed>
 */
function mrn_base_stack_populate_card_item_rows_field( $field ) {
	if ( ! is_array( $field ) ) {
		return $field;
	}

	$field_name = isset( $field['name'] ) ? sanitize_key( (string) $field['name'] ) : '';
	if ( 'card_rows' !== $field_name ) {
		return $field;
	}

	$field['layouts'] = mrn_base_stack_get_card_nested_layouts();

	return $field;
}
add_filter( 'acf/load_field/name=card_rows', 'mrn_base_stack_populate_card_item_rows_field', 20 );
add_filter( 'acf/prepare_field/name=card_rows', 'mrn_base_stack_populate_card_item_rows_field', 20 );

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
	static $resolving = false;

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

		uasort(
			$choices,
			static function ( $left, $right ) {
				return strnatcasecmp( (string) $left, (string) $right );
			}
		);

		return $choices;
	} finally {
		$resolving = false;
	}
}

/**
 * Load live post-type choices into the Content builder field.
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

	$field['label']        = 'Content Source';
	$field['choices']      = mrn_base_stack_get_content_list_post_type_choices();
	$field['instructions'] = 'Choose the post type to query, such as Posts, Pages, Testimonials, Galleries, or Case Studies. Use Filter Source to narrow items within this source.';
	$field['ui']           = 1;

	return $field;
}
add_filter( 'acf/load_field/key=field_mrn_content_lists_post_type', 'mrn_base_stack_load_content_list_post_type_field_choices' );
add_filter( 'acf/load_field/name=list_post_type', 'mrn_base_stack_load_content_list_post_type_field_choices' );
add_filter( 'acf/prepare_field/key=field_mrn_content_lists_post_type', 'mrn_base_stack_load_content_list_post_type_field_choices' );
add_filter( 'acf/prepare_field/name=list_post_type', 'mrn_base_stack_load_content_list_post_type_field_choices' );

/**
 * Load live post-type choices into the Content builder manual post picker.
 *
 * @param array<string, mixed> $field ACF field definition.
 * @return array<string, mixed>
 */
function mrn_base_stack_load_content_list_filter_posts_field_choices( $field ) {
	if ( ! is_array( $field ) ) {
		return $field;
	}

	$field['post_type']    = array_keys( mrn_base_stack_get_content_list_post_type_choices() );
	$field['instructions'] = 'Choose exact items from the selected public content sources. The rendered query still respects the selected Content Source.';

	return $field;
}
add_filter( 'acf/load_field/key=field_mrn_content_lists_filter_posts', 'mrn_base_stack_load_content_list_filter_posts_field_choices' );
add_filter( 'acf/prepare_field/key=field_mrn_content_lists_filter_posts', 'mrn_base_stack_load_content_list_filter_posts_field_choices' );

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
	return function_exists( 'mrn_base_stack_image_has_content' ) ? mrn_base_stack_image_has_content( $image ) : ! empty( $image );
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
 * Get display-style choices for query-driven builder layouts.
 *
 * @return array<string, string>
 */
function mrn_base_stack_get_content_list_display_style_choices() {
	$choices = array(
		'' => 'Use Content Default',
	);

	foreach ( mrn_base_stack_get_content_list_display_style_choice_map() as $post_type_choices ) {
		if ( ! is_array( $post_type_choices ) ) {
			continue;
		}

		foreach ( $post_type_choices as $style => $label ) {
			$label = trim( (string) $label );
			if ( '' === $label || isset( $choices[ $style ] ) ) {
				continue;
			}

			$choices[ $style ] = $label;
		}
	}

	return $choices;
}

/**
 * Load live display-mode choices into the Content builder field.
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
add_filter( 'acf/prepare_field/key=field_mrn_content_lists_display_mode', 'mrn_base_stack_load_content_list_display_mode_field_choices' );

/**
 * Load live display-style choices into the Content builder field.
 *
 * @param array<string, mixed> $field ACF field definition.
 * @return array<string, mixed>
 */
function mrn_base_stack_load_content_list_display_style_field_choices( $field ) {
	if ( ! is_array( $field ) ) {
		return $field;
	}

	$field['choices']    = mrn_base_stack_get_content_list_display_style_choices();
	$field['allow_null'] = 1;
	$field['ui']         = 0;

	return $field;
}
add_filter( 'acf/load_field/key=field_mrn_content_lists_display_style', 'mrn_base_stack_load_content_list_display_style_field_choices' );
add_filter( 'acf/prepare_field/key=field_mrn_content_lists_display_style', 'mrn_base_stack_load_content_list_display_style_field_choices' );

/**
 * Robustly normalize dynamic choices for Content select subfields.
 *
 * Some builder contexts can bypass the narrower ACF key/name hooks depending on
 * how the flexible-content row is prepared. This catches the rendered field
 * instance itself and reapplies the dynamic choice sources when the field is a
 * Content subfield.
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

	if ( $is_content_list_layout && in_array( $field_origin, array( 'list_post_type', 'display_mode', 'display_style' ), true ) ) {
		if ( 'list_post_type' === $field_origin ) {
			$field['choices'] = mrn_base_stack_get_content_list_post_type_choices();
		}

		if ( 'display_mode' === $field_origin ) {
			$field['choices']    = mrn_base_stack_get_content_list_display_mode_choices();
			$field['allow_null'] = 1;
			$field['ui']         = 0;
		}

		if ( 'display_style' === $field_origin ) {
			$field['choices']    = mrn_base_stack_get_content_list_display_style_choices();
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
 * Get display-style choices for a specific Content post type.
 *
 * @param string $post_type Post type slug.
 * @return array<string, array<string, mixed>>
 */
function mrn_base_stack_get_content_list_display_styles_for_post_type( $post_type = 'post' ) {
	$post_type = sanitize_key( (string) $post_type );

	if ( ! function_exists( 'mrn_base_stack_get_display_styles_for_entity' ) ) {
		return array();
	}

	return mrn_base_stack_get_display_styles_for_entity( 'post_type', $post_type );
}

/**
 * Get display-style labels grouped by post type for builder-admin filtering.
 *
 * @return array<string, array<string, string>>
 */
function mrn_base_stack_get_content_list_display_style_choice_map() {
	$map = array();

	foreach ( mrn_base_stack_get_content_list_post_type_choices() as $post_type => $label ) {
		$choices = array();

		foreach ( mrn_base_stack_get_content_list_display_styles_for_post_type( $post_type ) as $style_key => $style_config ) {
			if ( ! is_array( $style_config ) ) {
				continue;
			}

			$style_label = isset( $style_config['label'] ) ? trim( (string) $style_config['label'] ) : '';
			if ( '' === $style_label ) {
				continue;
			}

			$choices[ $style_key ] = $style_label;
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
	$modes = array();

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
 * Normalize a Content row display style for a selected post type.
 *
 * Empty is meaningful: it lets each content item use its own default style.
 *
 * @param string $style     Candidate display-style key.
 * @param string $post_type Selected post type.
 * @return string
 */
function mrn_base_stack_normalize_content_list_display_style( $style, $post_type ) {
	$style     = sanitize_key( (string) $style );
	$post_type = sanitize_key( (string) $post_type );

	if ( '' === $style || '' === $post_type || ! function_exists( 'mrn_base_stack_normalize_display_style' ) ) {
		return '';
	}

	return mrn_base_stack_normalize_display_style( $style, 'post_type', $post_type, '' );
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
 * Build the legacy row-settings display contract for Content.
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
 * Prepare testimonial body HTML for Content row rendering.
 *
 * @param string $content Testimonial content.
 * @return string
 */
function mrn_base_stack_get_content_list_testimonial_body_html( $content ) {
	$content = trim( (string) $content );

	if ( '' === $content ) {
		return '';
	}

	if ( false === stripos( $content, '<p' ) && false === stripos( $content, '<br' ) ) {
		$content = wpautop( $content );
	}

	return wp_kses_post( $content );
}

/**
 * Render a testimonial video/media block for a Content row item.
 *
 * @param array<string, mixed> $testimonial Testimonial data.
 * @return string
 */
function mrn_base_stack_get_content_list_testimonial_media_markup( array $testimonial ) {
	$name        = isset( $testimonial['name'] ) ? wp_strip_all_tags( (string) $testimonial['name'] ) : __( 'testimonial', 'mrn-base-stack' );
	$video_url   = isset( $testimonial['video_url'] ) ? trim( (string) $testimonial['video_url'] ) : '';
	$video_kind  = isset( $testimonial['video_kind'] ) ? sanitize_key( (string) $testimonial['video_kind'] ) : '';
	$video_mime  = isset( $testimonial['video_mime'] ) ? trim( (string) $testimonial['video_mime'] ) : '';
	$image_logo  = $testimonial['image_logo'] ?? null;
	$has_image   = function_exists( 'mrn_base_stack_image_has_content' ) ? mrn_base_stack_image_has_content( $image_logo ) : false;
	$video_title = sprintf(
		/* translators: %s: testimonial author name. */
		__( 'Video testimonial from %s', 'mrn-base-stack' ),
		$name
	);

	ob_start();
	?>
	<?php if ( '' !== $video_url ) : ?>
		<div
			class="mrn-content-list-row__testimonial-video mrn-testimonial-video mrn-video-row__media mrn-ui__media"
			data-video-src="<?php echo esc_url( $video_url ); ?>"
			data-video-kind="<?php echo esc_attr( '' !== $video_kind ? $video_kind : 'remote' ); ?>"
			data-video-title="<?php echo esc_attr( $video_title ); ?>"
			<?php if ( 'local' === $video_kind && '' !== $video_mime ) : ?>
				data-video-mime="<?php echo esc_attr( $video_mime ); ?>"
			<?php endif; ?>
			data-video-background="false"
			data-video-autoplay="false"
			data-video-muted="false"
			data-video-loop="false"
			data-video-controls="true"
			data-video-delay="250"
			role="group"
			aria-label="<?php echo esc_attr( $video_title ); ?>"
		></div>
	<?php elseif ( $has_image ) : ?>
		<div class="mrn-content-list-row__testimonial-image mrn-ui__media">
			<?php echo function_exists( 'mrn_base_stack_get_attachment_image' ) ? mrn_base_stack_get_attachment_image( $image_logo, 'mrn-testimonial' ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
	<?php endif; ?>
	<?php

	return (string) ob_get_clean();
}

/**
 * Render one testimonial result item for the Content layout.
 *
 * @param WP_Post              $item_post Testimonial post to render.
 * @param array<string, mixed> $args      Render arguments.
 * @return string
 */
function mrn_base_stack_render_content_list_testimonial_item( WP_Post $item_post, array $args = array() ) {
	$display_mode = mrn_base_stack_normalize_content_list_display_mode( $args['display_mode'] ?? '' );
	$mode_config  = '' !== $display_mode ? mrn_base_stack_get_content_list_display_mode_config( $display_mode ) : mrn_base_stack_get_content_list_legacy_mode_config( $args );
	$fields       = isset( $mode_config['fields'] ) && is_array( $mode_config['fields'] ) ? array_values( array_unique( array_map( 'sanitize_key', $mode_config['fields'] ) ) ) : array();

	if ( empty( $fields ) ) {
		$fields = array( 'title' );
	}

	$uses_row_settings = '' === $display_mode;
	$display_mode_slug = '' !== $display_mode ? $display_mode : 'row-settings';
	$testimonial       = function_exists( 'mrn_base_stack_get_testimonial_data' ) ? mrn_base_stack_get_testimonial_data( $item_post->ID ) : array();
	$row_display_style = function_exists( 'mrn_base_stack_normalize_content_list_display_style' )
		? mrn_base_stack_normalize_content_list_display_style( $args['display_style'] ?? '', 'testimonial' )
		: '';
	$post_style        = isset( $testimonial['display_style'] ) ? sanitize_key( (string) $testimonial['display_style'] ) : '';
	$display_style     = '' !== $row_display_style ? $row_display_style : $post_style;
	$display_style     = function_exists( 'mrn_base_stack_normalize_display_style' )
		? mrn_base_stack_normalize_display_style( $display_style, 'post_type', 'testimonial', 'story' )
		: sanitize_key( '' !== $display_style ? $display_style : 'story' );
	$display_style     = '' !== $display_style ? $display_style : 'story';
	$permalink         = get_permalink( $item_post );
	$permalink         = is_string( $permalink ) ? $permalink : '';
	$item_title        = get_the_title( $item_post );
	$content           = isset( $testimonial['content'] ) ? (string) $testimonial['content'] : '';
	$quote_html        = mrn_base_stack_get_content_list_testimonial_body_html( $content );
	$show_media        = ( ! $uses_row_settings || ! empty( $args['show_featured_image'] ) ) && ! empty( $mode_config['allows_image'] );
	$show_date         = ( ! $uses_row_settings || ! empty( $args['show_publish_date'] ) ) && ! empty( $mode_config['allows_date'] );
	$show_quote        = ( ! $uses_row_settings || ! empty( $args['show_excerpt'] ) ) && ! empty( $mode_config['allows_excerpt'] ) && '' !== $quote_html;
	$show_read_more    = ( ! $uses_row_settings || ! empty( $args['show_read_more'] ) ) && ! empty( $mode_config['allows_read_more'] ) && '' !== $permalink;
	$read_more_label   = isset( $args['read_more_label'] ) ? trim( (string) $args['read_more_label'] ) : 'Read More';
	$media_markup      = $show_media ? mrn_base_stack_get_content_list_testimonial_media_markup( $testimonial ) : '';
	$variant           = array( 'title' ) === $fields ? 'title_only' : 'testimonial';
	$item_classes      = array(
		'mrn-content-list-row__item',
		'mrn-content-list-row__item--testimonial',
		'mrn-content-list-row__item--display-' . $display_mode_slug,
		'mrn-content-list-row__item--display-style-' . $display_style,
		'mrn-content-list-row__item--variant-' . $variant,
		'mrn-ui__item',
		'mrn-testimonial',
		'mrn-testimonial--display-' . $display_style,
	);

	if ( '' !== $media_markup ) {
		$item_classes[] = 'mrn-content-list-row__item--has-media';
	}

	ob_start();
	?>
	<li
		class="<?php echo esc_attr( implode( ' ', array_map( 'sanitize_html_class', $item_classes ) ) ); ?>"
		data-display-mode="<?php echo esc_attr( $display_mode_slug ); ?>"
		data-display-style="<?php echo esc_attr( $display_style ); ?>"
	>
		<article class="mrn-content-list-row__testimonial">
			<?php $body_open = false; ?>
			<?php foreach ( $fields as $field_key ) : ?>
				<?php if ( in_array( $field_key, array( 'featured_image', 'image' ), true ) && '' !== $media_markup ) : ?>
					<?php if ( $body_open ) : ?>
						</div>
						<?php $body_open = false; ?>
					<?php endif; ?>
					<?php echo $media_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prepared above with escaped attributes/media. ?>
					<?php continue; ?>
				<?php endif; ?>

				<?php
				$should_render_body_field = (
					( 'publish_date' === $field_key && $show_date ) ||
					( 'title' === $field_key && '' !== $item_title ) ||
					( in_array( $field_key, array( 'excerpt', 'body' ), true ) && $show_quote ) ||
					( in_array( $field_key, array( 'read_more', 'link' ), true ) && $show_read_more )
				);
				?>
				<?php if ( ! $should_render_body_field ) : ?>
					<?php continue; ?>
				<?php endif; ?>

				<?php if ( ! $body_open ) : ?>
					<div class="mrn-content-list-row__testimonial-body mrn-ui__body">
					<?php $body_open = true; ?>
				<?php endif; ?>

				<?php if ( 'publish_date' === $field_key ) : ?>
					<p class="mrn-content-list-row__meta"><?php echo esc_html( get_the_date( '', $item_post ) ); ?></p>
				<?php elseif ( 'title' === $field_key ) : ?>
					<h3 class="mrn-content-list-row__title mrn-ui__heading">
						<?php if ( '' !== $permalink ) : ?>
							<a class="mrn-ui__link" href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( $item_title ); ?></a>
						<?php else : ?>
							<?php echo esc_html( $item_title ); ?>
						<?php endif; ?>
					</h3>
				<?php elseif ( in_array( $field_key, array( 'excerpt', 'body' ), true ) ) : ?>
					<blockquote class="mrn-content-list-row__testimonial-quote mrn-ui__text">
						<?php echo $quote_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sanitized by mrn_base_stack_get_content_list_testimonial_body_html(). ?>
					</blockquote>
				<?php elseif ( in_array( $field_key, array( 'read_more', 'link' ), true ) ) : ?>
					<p class="mrn-content-list-row__link">
						<a class="mrn-ui__link" href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( '' !== $read_more_label ? $read_more_label : 'Read More' ); ?></a>
					</p>
				<?php endif; ?>
			<?php endforeach; ?>
			<?php if ( $body_open ) : ?>
				</div>
			<?php endif; ?>
		</article>
		<?php if ( $show_quote ) : ?>
			<?php do_action( 'mrn_base_stack_testimonial_rendered', $item_post, $testimonial ); ?>
		<?php endif; ?>
	</li>
	<?php

	return (string) ob_get_clean();
}

/**
 * Render one query result item for the Content layout.
 *
 * @param WP_Post              $item_post Post to render.
 * @param array<string, mixed> $args Render arguments.
 * @return string
 */
function mrn_base_stack_render_content_list_item( WP_Post $item_post, array $args = array() ) {
	if ( 'testimonial' === get_post_type( $item_post ) && function_exists( 'mrn_base_stack_render_content_list_testimonial_item' ) ) {
		return mrn_base_stack_render_content_list_testimonial_item( $item_post, $args );
	}

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
		'manual_posts'       => 'Choose Specific Content',
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
	unset( $default_width );
	return array(
		'key'               => $key,
		'label'             => $label,
		'name'              => $name,
		'aria-label'        => '',
		'type'              => 'select',
		'choices'           => array( '' => 'Default' ) + mrn_base_stack_get_section_width_choices(),
		'default_value'     => '',
		'instructions'      => 'Default uses the site-wide row width configured in Site Styles. Choose another value to override it for this row.',
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
 * Normalize a row-spacing selector scope key.
 *
 * @param mixed $scope Raw scope value.
 * @return string
 */
function mrn_base_stack_normalize_row_spacing_preset_scope( $scope ) {
	$scope = is_scalar( $scope ) ? strtolower( trim( (string) $scope ) ) : '';

	if ( in_array( $scope, array( 'margin', 'padding' ), true ) ) {
		return $scope;
	}

	if ( preg_match( '/^(margin|padding)\-(top|right|bottom|left)$/', $scope ) ) {
		return $scope;
	}

	return '';
}

/**
 * Get row-spacing side selector field definitions for ACF Spacing tabs.
 *
 * @return array<int, array<string, string>>
 */
function mrn_base_stack_get_row_spacing_side_selector_definitions() {
	return array(
		array(
			'name'  => 'row_spacing_margin_top_preset',
			'label' => 'Margin Top',
			'scope' => 'margin-top',
		),
		array(
			'name'  => 'row_spacing_margin_right_preset',
			'label' => 'Margin Right',
			'scope' => 'margin-right',
		),
		array(
			'name'  => 'row_spacing_margin_bottom_preset',
			'label' => 'Margin Bottom',
			'scope' => 'margin-bottom',
		),
		array(
			'name'  => 'row_spacing_margin_left_preset',
			'label' => 'Margin Left',
			'scope' => 'margin-left',
		),
		array(
			'name'  => 'row_spacing_padding_top_preset',
			'label' => 'Padding Top',
			'scope' => 'padding-top',
		),
		array(
			'name'  => 'row_spacing_padding_right_preset',
			'label' => 'Padding Right',
			'scope' => 'padding-right',
		),
		array(
			'name'  => 'row_spacing_padding_bottom_preset',
			'label' => 'Padding Bottom',
			'scope' => 'padding-bottom',
		),
		array(
			'name'  => 'row_spacing_padding_left_preset',
			'label' => 'Padding Left',
			'scope' => 'padding-left',
		),
	);
}

/**
 * Check whether an ACF field name is a row-spacing selector field.
 *
 * @param mixed $field_name Raw field name.
 * @return bool
 */
function mrn_base_stack_is_row_spacing_selector_field_name( $field_name ) {
	$field_name = is_scalar( $field_name ) ? sanitize_key( (string) $field_name ) : '';
	if ( '' === $field_name ) {
		return false;
	}

	if ( in_array( $field_name, array( 'row_spacing_preset', 'row_spacing_margin_preset', 'row_spacing_padding_preset' ), true ) ) {
		return true;
	}

	foreach ( mrn_base_stack_get_row_spacing_side_selector_definitions() as $definition ) {
		$selector_name = isset( $definition['name'] ) ? sanitize_key( (string) $definition['name'] ) : '';
		if ( '' !== $selector_name && $selector_name === $field_name ) {
			return true;
		}
	}

	return false;
}

/**
 * Map a row-spacing selector field name to its scope.
 *
 * @param mixed $field_name Raw field name.
 * @return string
 */
function mrn_base_stack_get_row_spacing_selector_scope_from_field_name( $field_name ) {
	$field_name = is_scalar( $field_name ) ? sanitize_key( (string) $field_name ) : '';
	if ( 'row_spacing_margin_preset' === $field_name ) {
		return 'margin';
	}

	if ( 'row_spacing_padding_preset' === $field_name ) {
		return 'padding';
	}

	foreach ( mrn_base_stack_get_row_spacing_side_selector_definitions() as $definition ) {
		$selector_name = isset( $definition['name'] ) ? sanitize_key( (string) $definition['name'] ) : '';
		$scope         = isset( $definition['scope'] ) ? mrn_base_stack_normalize_row_spacing_preset_scope( $definition['scope'] ) : '';
		if ( '' !== $selector_name && '' !== $scope && $selector_name === $field_name ) {
			return $scope;
		}
	}

	if ( 'row_spacing_preset' === $field_name ) {
		return '';
	}

	return '';
}

/**
 * Get the list of row-spacing selector field names.
 *
 * @return array<int, string>
 */
function mrn_base_stack_get_row_spacing_selector_field_names() {
	$names = array(
		'row_spacing_preset',
		'row_spacing_margin_preset',
		'row_spacing_padding_preset',
	);

	foreach ( mrn_base_stack_get_row_spacing_side_selector_definitions() as $definition ) {
		$selector_name = isset( $definition['name'] ) ? sanitize_key( (string) $definition['name'] ) : '';
		if ( '' === $selector_name ) {
			continue;
		}

		$names[] = $selector_name;
	}

	return array_values( array_unique( $names ) );
}

/**
 * Check whether one selector scope contains another.
 *
 * @param string $selector_scope Selector scope (`margin`, `padding`, or side scope).
 * @param string $target_scope Target scope (`margin`, `padding`, or side scope).
 * @return bool
 */
function mrn_base_stack_row_spacing_scope_contains_scope( $selector_scope, $target_scope ) {
	$selector_scope = mrn_base_stack_normalize_row_spacing_preset_scope( $selector_scope );
	$target_scope   = mrn_base_stack_normalize_row_spacing_preset_scope( $target_scope );
	if ( '' === $selector_scope || '' === $target_scope ) {
		return false;
	}

	if ( $selector_scope === $target_scope ) {
		return true;
	}

	if ( in_array( $selector_scope, array( 'margin', 'padding' ), true ) ) {
		return 0 === strpos( $target_scope, $selector_scope . '-' );
	}

	if ( in_array( $target_scope, array( 'margin', 'padding' ), true ) ) {
		return false;
	}

	return false;
}

/**
 * Check whether a row-spacing property belongs to a selector scope.
 *
 * @param mixed  $property Raw property key.
 * @param string $scope Selector scope (`margin`, `padding`, or empty for all).
 * @return bool
 */
function mrn_base_stack_row_spacing_property_matches_scope( $property, $scope = '' ) {
	$scope = mrn_base_stack_normalize_row_spacing_preset_scope( $scope );
	if ( '' === $scope ) {
		return true;
	}

	$target_properties = mrn_base_stack_expand_row_spacing_property_to_keys( $property );
	if ( empty( $target_properties ) ) {
		return false;
	}

	if ( in_array( $scope, array( 'margin', 'padding' ), true ) ) {
		foreach ( $target_properties as $target_property ) {
			if ( 0 === strpos( $target_property, $scope . '-' ) ) {
				return true;
			}
		}

		return false;
	}

	return in_array( $scope, $target_properties, true );
}

/**
 * Get row-spacing preset choices from Site Styles configuration.
 *
 * @param string $scope Optional selector scope (`margin`, `padding`, or empty for all).
 * @return array<string, string>
 */
function mrn_base_stack_get_row_spacing_preset_choices( $scope = '' ) {
	static $choices_cache = array();

	$scope = mrn_base_stack_normalize_row_spacing_preset_scope( $scope );
	if ( isset( $choices_cache[ $scope ] ) ) {
		return $choices_cache[ $scope ];
	}

	$choices = array(
		'' => 'Site Default',
	);

	if ( ! function_exists( 'mrn_site_styles_get_row_spacing_presets_resolved' ) ) {
		$choices_cache[ $scope ] = $choices;
		return $choices;
	}

	$rows = mrn_site_styles_get_row_spacing_presets_resolved();
	if ( ! is_array( $rows ) ) {
		$choices_cache[ $scope ] = $choices;
		return $choices;
	}

	foreach ( $rows as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}

		if ( ! mrn_base_stack_row_spacing_property_matches_scope( $row['property'] ?? '', $scope ) ) {
			continue;
		}

		$name = isset( $row['name'] ) && is_string( $row['name'] ) ? trim( $row['name'] ) : '';
		if ( '' === $name || isset( $choices[ $name ] ) ) {
			continue;
		}

		$choices[ $name ] = $name;
	}

	$choices_cache[ $scope ] = $choices;

	return $choices;
}

/**
 * Load live row-spacing preset choices into the shared row config select.
 *
 * @param array<string, mixed> $field ACF field definition.
 * @return array<string, mixed>
 */
function mrn_base_stack_load_row_spacing_preset_field_choices( $field ) {
	if ( ! is_array( $field ) ) {
		return $field;
	}

	$field_name = isset( $field['name'] ) ? sanitize_key( (string) $field['name'] ) : '';
	$scope      = mrn_base_stack_get_row_spacing_selector_scope_from_field_name( $field_name );

	$choices = mrn_base_stack_get_row_spacing_preset_choices( $scope );

	$current_value = isset( $field['value'] ) && is_scalar( $field['value'] ) ? trim( (string) $field['value'] ) : '';
	if ( '' !== $current_value && ! isset( $choices[ $current_value ] ) ) {
		$choices[ $current_value ] = $current_value . ' (Missing preset)';
	}

	$field['choices']       = $choices;
	$field['default_value'] = '';
	$field['allow_null']    = 1;
	$field['ui']            = 1;

	return $field;
}
add_filter( 'acf/load_field/name=row_spacing_preset', 'mrn_base_stack_load_row_spacing_preset_field_choices', 20 );
add_filter( 'acf/prepare_field/name=row_spacing_preset', 'mrn_base_stack_load_row_spacing_preset_field_choices', 20 );
add_filter( 'acf/load_field/name=row_spacing_margin_preset', 'mrn_base_stack_load_row_spacing_preset_field_choices', 20 );
add_filter( 'acf/prepare_field/name=row_spacing_margin_preset', 'mrn_base_stack_load_row_spacing_preset_field_choices', 20 );
add_filter( 'acf/load_field/name=row_spacing_padding_preset', 'mrn_base_stack_load_row_spacing_preset_field_choices', 20 );
add_filter( 'acf/prepare_field/name=row_spacing_padding_preset', 'mrn_base_stack_load_row_spacing_preset_field_choices', 20 );
add_filter( 'acf/load_field/name=row_spacing_margin_top_preset', 'mrn_base_stack_load_row_spacing_preset_field_choices', 20 );
add_filter( 'acf/prepare_field/name=row_spacing_margin_top_preset', 'mrn_base_stack_load_row_spacing_preset_field_choices', 20 );
add_filter( 'acf/load_field/name=row_spacing_margin_right_preset', 'mrn_base_stack_load_row_spacing_preset_field_choices', 20 );
add_filter( 'acf/prepare_field/name=row_spacing_margin_right_preset', 'mrn_base_stack_load_row_spacing_preset_field_choices', 20 );
add_filter( 'acf/load_field/name=row_spacing_margin_bottom_preset', 'mrn_base_stack_load_row_spacing_preset_field_choices', 20 );
add_filter( 'acf/prepare_field/name=row_spacing_margin_bottom_preset', 'mrn_base_stack_load_row_spacing_preset_field_choices', 20 );
add_filter( 'acf/load_field/name=row_spacing_margin_left_preset', 'mrn_base_stack_load_row_spacing_preset_field_choices', 20 );
add_filter( 'acf/prepare_field/name=row_spacing_margin_left_preset', 'mrn_base_stack_load_row_spacing_preset_field_choices', 20 );
add_filter( 'acf/load_field/name=row_spacing_padding_top_preset', 'mrn_base_stack_load_row_spacing_preset_field_choices', 20 );
add_filter( 'acf/prepare_field/name=row_spacing_padding_top_preset', 'mrn_base_stack_load_row_spacing_preset_field_choices', 20 );
add_filter( 'acf/load_field/name=row_spacing_padding_right_preset', 'mrn_base_stack_load_row_spacing_preset_field_choices', 20 );
add_filter( 'acf/prepare_field/name=row_spacing_padding_right_preset', 'mrn_base_stack_load_row_spacing_preset_field_choices', 20 );
add_filter( 'acf/load_field/name=row_spacing_padding_bottom_preset', 'mrn_base_stack_load_row_spacing_preset_field_choices', 20 );
add_filter( 'acf/prepare_field/name=row_spacing_padding_bottom_preset', 'mrn_base_stack_load_row_spacing_preset_field_choices', 20 );
add_filter( 'acf/load_field/name=row_spacing_padding_left_preset', 'mrn_base_stack_load_row_spacing_preset_field_choices', 20 );
add_filter( 'acf/prepare_field/name=row_spacing_padding_left_preset', 'mrn_base_stack_load_row_spacing_preset_field_choices', 20 );

/**
 * Build a standard row-spacing preset selector field.
 *
 * @param string $key Unique ACF field key.
 * @param string $name Field name.
 * @param string $label Field label.
 * @param string $scope Optional selector scope (`margin`, `padding`, or empty for all).
 * @param string $instructions Optional custom field instructions.
 * @param string $wrapper_width Wrapper width percentage.
 * @return array<string, mixed>
 */
function mrn_base_stack_get_row_spacing_preset_field( $key, $name = 'row_spacing_preset', $label = 'Row Spacing Preset', $scope = '', $instructions = '', $wrapper_width = '50' ) {
	$scope = mrn_base_stack_normalize_row_spacing_preset_scope( $scope );
	if ( '' === $instructions && 'row_spacing_preset' === $name ) {
		$instructions = 'Uses Site Styles defaults by default. Select a preset name to override those defaults for this row.';
	}

	return array(
		'key'           => $key,
		'label'         => $label,
		'name'          => $name,
		'aria-label'    => '',
		'type'          => 'select',
		'choices'       => array(
			'' => 'Site Default',
		),
		'default_value' => '',
		'ui'            => 1,
		'allow_null'    => 1,
		'instructions'  => $instructions,
		'wrapper'       => array(
			'width' => (string) $wrapper_width,
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
		'instructions' => 'Optional anchor slug for one-page links. Enter the value without #. When blank, Name (admin use only) becomes the default anchor.',
		'wrapper'      => array(
			'width' => '50',
		),
	);
}

/**
 * Build shared image caption controls for builder rows.
 *
 * @param string $key_prefix Unique ACF field key prefix.
 * @return array<int, array<string, mixed>>
 */
function mrn_base_stack_get_image_caption_fields( $key_prefix ) {
	$key_prefix       = trim( (string) $key_prefix );
	$source_field_key = $key_prefix . '_source';
	$caption_logic    = array(
		array(
			array(
				'field'    => $source_field_key,
				'operator' => '==',
				'value'    => 'attachment',
			),
		),
		array(
			array(
				'field'    => $source_field_key,
				'operator' => '==',
				'value'    => 'custom',
			),
		),
	);

	return array(
		array(
			'key'           => $source_field_key,
			'label'         => 'Image Caption',
			'name'          => 'image_caption_source',
			'aria-label'    => '',
			'type'          => 'select',
			'choices'       => array(
				'none'       => 'None',
				'attachment' => 'Media Library Caption',
				'custom'     => 'Custom Caption',
			),
			'default_value' => 'none',
			'allow_null'    => 0,
			'multiple'      => 0,
			'ui'            => 1,
			'instructions'  => 'Choose whether to render an image caption and where its text comes from.',
			'wrapper'       => array(
				'width' => '50',
			),
		),
		array(
			'key'               => $key_prefix . '_style',
			'label'             => 'Image Caption Style',
			'name'              => 'image_caption_style',
			'aria-label'        => '',
			'type'              => 'select',
			'choices'           => array(
				'under'  => 'Under Image',
				'inside' => 'Inside Image',
			),
			'default_value'     => 'under',
			'allow_null'        => 0,
			'multiple'          => 0,
			'ui'                => 1,
			'instructions'      => 'Inside image captions render as readable text over the image with a contrast overlay.',
			'conditional_logic' => $caption_logic,
			'wrapper'           => array(
				'width' => '50',
			),
		),
		array(
			'key'               => $key_prefix . '_custom',
			'label'             => 'Custom Caption',
			'name'              => 'image_caption',
			'aria-label'        => '',
			'type'              => 'textarea',
			'instructions'      => 'Limited inline HTML allowed: span, strong, em, br.',
			'rows'              => 2,
			'new_lines'         => 'br',
			'conditional_logic' => array(
				array(
					array(
						'field'    => $source_field_key,
						'operator' => '==',
						'value'    => 'custom',
					),
				),
			),
			'wrapper'           => array(
				'width' => '100',
			),
		),
	);
}

/**
 * Build shared decorative background video fields for builder rows.
 *
 * @param string $key_prefix Unique ACF key prefix.
 * @return array<int, array<string, mixed>>
 */
function mrn_base_stack_get_background_video_fields( $key_prefix ) {
	$key_prefix = trim( (string) $key_prefix );

	return array(
		array(
			'key'          => $key_prefix . '_remote',
			'label'        => 'Background video URL',
			'name'         => 'background_video',
			'aria-label'   => '',
			'type'         => 'url',
			'instructions' => 'Optional decorative YouTube or Vimeo background video. Ignored when a video upload is set.',
			'wrapper'      => array(
				'width' => '50',
			),
		),
		array(
			'key'           => $key_prefix . '_upload',
			'label'         => 'Background video upload',
			'name'          => 'background_video_upload',
			'aria-label'    => '',
			'type'          => 'file',
			'return_format' => 'array',
			'library'       => 'all',
			'mime_types'    => 'mp4,webm,mov',
			'instructions'  => 'Optional decorative video upload. Uses background image as the poster when one is set.',
			'wrapper'       => array(
				'width' => '50',
			),
		),
	);
}

/**
 * Build a shared row background color field.
 *
 * @param string $key Field key.
 * @return array<string, mixed>
 */
function mrn_base_stack_get_background_color_field( $key ) {
	return array(
		'key'          => sanitize_key( (string) $key ),
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

/**
 * Build a shared decorative background image field.
 *
 * @param string $key Field key.
 * @return array<string, mixed>
 */
function mrn_base_stack_get_background_image_field( $key ) {
	return array(
		'key'           => sanitize_key( (string) $key ),
		'label'         => 'Background image',
		'name'          => 'background_image',
		'aria-label'    => '',
		'type'          => 'image',
		'return_format' => 'id',
		'preview_size'  => 'medium',
		'library'       => 'all',
		'wrapper'       => array(
			'width' => '50',
		),
	);
}

/**
 * Build the full shared row background field set.
 *
 * @param string $key_prefix Base key prefix ending in `_background`.
 * @return array<int, array<string, mixed>>
 */
function mrn_base_stack_get_background_capability_fields( $key_prefix ) {
	$key_prefix = sanitize_key( (string) $key_prefix );
	if ( '' === $key_prefix ) {
		$key_prefix = 'field_mrn_background';
	}

	return array_merge(
		array(
			mrn_base_stack_get_background_color_field( $key_prefix . '_color' ),
		),
		mrn_base_stack_get_background_gradient_fields( $key_prefix . '_gradient' ),
		array(
			mrn_base_stack_get_background_image_field( $key_prefix . '_image' ),
		),
		mrn_base_stack_get_background_video_fields( $key_prefix . '_video' )
	);
}

/**
 * Build shared row background-gradient fields.
 *
 * @param string $key_prefix Unique ACF key prefix.
 * @return array<int, array<string, mixed>>
 */
function mrn_base_stack_get_background_gradient_fields( $key_prefix ) {
	$key_prefix        = trim( (string) $key_prefix );
	$enabled_field_key = $key_prefix . '_enabled';
	$color_choices     = function_exists( 'mrn_rbl_get_site_color_choices' ) ? mrn_rbl_get_site_color_choices() : array();
	$enabled_logic     = array(
		array(
			array(
				'field'    => $enabled_field_key,
				'operator' => '==',
				'value'    => '1',
			),
		),
	);

	return array(
		array(
			'key'           => $enabled_field_key,
			'label'         => 'Background gradient',
			'name'          => 'background_gradient_enabled',
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
			'key'               => $key_prefix . '_start_color',
			'label'             => 'Gradient start',
			'name'              => 'background_gradient_start_color',
			'aria-label'        => '',
			'type'              => 'select',
			'choices'           => $color_choices,
			'ui'                => 1,
			'allow_null'        => 1,
			'instructions'      => 'Select from Site Colors when available.',
			'conditional_logic' => $enabled_logic,
			'wrapper'           => array(
				'width' => '33',
			),
		),
		array(
			'key'               => $key_prefix . '_end_color',
			'label'             => 'Gradient end',
			'name'              => 'background_gradient_end_color',
			'aria-label'        => '',
			'type'              => 'select',
			'choices'           => $color_choices,
			'ui'                => 1,
			'allow_null'        => 1,
			'instructions'      => 'Select from Site Colors when available.',
			'conditional_logic' => $enabled_logic,
			'wrapper'           => array(
				'width' => '33',
			),
		),
		array(
			'key'               => $key_prefix . '_start_opacity',
			'label'             => 'Start opacity',
			'name'              => 'background_gradient_start_opacity',
			'aria-label'        => '',
			'type'              => 'range',
			'default_value'     => 100,
			'min'               => 0,
			'max'               => 100,
			'step'              => 1,
			'append'            => '%',
			'instructions'      => 'Drag to adjust transparency for the first color.',
			'conditional_logic' => $enabled_logic,
			'wrapper'           => array(
				'width' => '33',
			),
		),
		array(
			'key'               => $key_prefix . '_end_opacity',
			'label'             => 'End opacity',
			'name'              => 'background_gradient_end_opacity',
			'aria-label'        => '',
			'type'              => 'range',
			'default_value'     => 100,
			'min'               => 0,
			'max'               => 100,
			'step'              => 1,
			'append'            => '%',
			'instructions'      => 'Drag to adjust transparency for the second color.',
			'conditional_logic' => $enabled_logic,
			'wrapper'           => array(
				'width' => '33',
			),
		),
		array(
			'key'               => $key_prefix . '_angle',
			'label'             => 'Gradient angle',
			'name'              => 'background_gradient_angle',
			'aria-label'        => '',
			'type'              => 'range',
			'default_value'     => 180,
			'min'               => 0,
			'max'               => 360,
			'step'              => 1,
			'append'            => 'deg',
			'instructions'      => 'Drag to rotate the gradient.',
			'conditional_logic' => $enabled_logic,
			'wrapper'           => array(
				'width' => '34',
			),
		),
		array(
			'key'               => $key_prefix . '_start_position',
			'label'             => 'Start position',
			'name'              => 'background_gradient_start_position',
			'aria-label'        => '',
			'type'              => 'range',
			'default_value'     => 0,
			'min'               => 0,
			'max'               => 100,
			'step'              => 1,
			'append'            => '%',
			'instructions'      => 'Drag to place the first color stop.',
			'conditional_logic' => $enabled_logic,
			'wrapper'           => array(
				'width' => '50',
			),
		),
		array(
			'key'               => $key_prefix . '_end_position',
			'label'             => 'End position',
			'name'              => 'background_gradient_end_position',
			'aria-label'        => '',
			'type'              => 'range',
			'default_value'     => 100,
			'min'               => 0,
			'max'               => 100,
			'step'              => 1,
			'append'            => '%',
			'instructions'      => 'Drag to place the second color stop.',
			'conditional_logic' => $enabled_logic,
			'wrapper'           => array(
				'width' => '50',
			),
		),
	);
}

/**
 * Normalize a gradient stop percentage for row background gradients.
 *
 * @param mixed $value Raw ACF value.
 * @param float $default_value Default percentage.
 * @return string
 */
function mrn_base_stack_normalize_gradient_stop_value( $value, $default_value ) {
	$value = is_scalar( $value ) && is_numeric( $value ) ? (float) $value : (float) $default_value;
	$value = max( 0, min( 100, $value ) );

	return rtrim( rtrim( number_format( $value, 2, '.', '' ), '0' ), '.' );
}

/**
 * Resolve a Site Colors CSS variable as an optional transparent color stop.
 *
 * @param string $color_slug Site Colors slug.
 * @param mixed  $opacity Raw opacity percentage.
 * @return string
 */
function mrn_base_stack_get_gradient_color_stop_value( $color_slug, $opacity ) {
	$color_slug = trim( (string) $color_slug );
	if ( '' === $color_slug || ! function_exists( 'mrn_site_colors_get_css_var' ) ) {
		return '';
	}

	$opacity = mrn_base_stack_normalize_gradient_stop_value( $opacity, 100 );
	if ( '100' === $opacity ) {
		return 'var(' . mrn_site_colors_get_css_var( $color_slug ) . ')';
	}

	if ( '0' === $opacity ) {
		return 'transparent';
	}

	return sprintf(
		'color-mix(in srgb, var(%s) %s%%, transparent)',
		mrn_site_colors_get_css_var( $color_slug ),
		$opacity
	);
}

/**
 * Resolve a row background gradient angle, with compatibility for old direction values.
 *
 * @param array<string, mixed> $row Builder row data.
 * @return string
 */
function mrn_base_stack_get_background_gradient_angle_value( array $row ) {
	$angle = isset( $row['background_gradient_angle'] ) && is_scalar( $row['background_gradient_angle'] )
		? trim( (string) $row['background_gradient_angle'] )
		: '';

	if ( '' !== $angle && is_numeric( $angle ) ) {
		$angle_value = (float) $angle;
		$angle_value = fmod( $angle_value, 360.0 );

		if ( $angle_value < 0 ) {
			$angle_value += 360.0;
		}

		return rtrim( rtrim( number_format( $angle_value, 2, '.', '' ), '0' ), '.' ) . 'deg';
	}

	$direction_key = isset( $row['background_gradient_direction'] ) && is_scalar( $row['background_gradient_direction'] )
		? sanitize_key( (string) $row['background_gradient_direction'] )
		: 'to-bottom';
	$direction_map = array(
		'to-bottom'       => 'to bottom',
		'to-right'        => 'to right',
		'to-bottom-right' => 'to bottom right',
		'to-bottom-left'  => 'to bottom left',
	);

	return isset( $direction_map[ $direction_key ] ) ? $direction_map[ $direction_key ] : $direction_map['to-bottom'];
}

/**
 * Build a CSS custom property declaration for a row background gradient.
 *
 * @param array<string, mixed> $row Builder row data.
 * @param string               $css_variable CSS custom property name.
 * @return string
 */
function mrn_base_stack_get_background_gradient_style_declaration( array $row, $css_variable ) {
	$css_variable = trim( (string) $css_variable );
	if ( '' === $css_variable || 0 !== strpos( $css_variable, '--' ) ) {
		return '';
	}

	if ( empty( $row['background_gradient_enabled'] ) || ! function_exists( 'mrn_site_colors_get_css_var' ) ) {
		return '';
	}

	$start_color = isset( $row['background_gradient_start_color'] ) && is_scalar( $row['background_gradient_start_color'] )
		? trim( (string) $row['background_gradient_start_color'] )
		: '';
	$end_color   = isset( $row['background_gradient_end_color'] ) && is_scalar( $row['background_gradient_end_color'] )
		? trim( (string) $row['background_gradient_end_color'] )
		: '';

	if ( '' === $start_color || '' === $end_color ) {
		return '';
	}

	$angle          = mrn_base_stack_get_background_gradient_angle_value( $row );
	$start_position = mrn_base_stack_normalize_gradient_stop_value( $row['background_gradient_start_position'] ?? null, 0 );
	$end_position   = mrn_base_stack_normalize_gradient_stop_value( $row['background_gradient_end_position'] ?? null, 100 );
	$start_stop     = mrn_base_stack_get_gradient_color_stop_value( $start_color, $row['background_gradient_start_opacity'] ?? 100 );
	$end_stop       = mrn_base_stack_get_gradient_color_stop_value( $end_color, $row['background_gradient_end_opacity'] ?? 100 );

	if ( '' === $start_stop || '' === $end_stop ) {
		return '';
	}

	return sprintf(
		'%s: linear-gradient(%s, %s %s%%, %s %s%%)',
		$css_variable,
		$angle,
		$start_stop,
		$start_position,
		$end_stop,
		$end_position
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
		'hover_effect',
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
		'instructions' => 'Optional editor-only row name used in the layout list. Also becomes the default row anchor when Anchor ID is blank.',
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
			$field['label']            = 'Tab Name';
			$field['instructions']     = '';
			$field['required']         = 1;
			$field['wrapper']['width'] = '50';
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
		$field['label']         = 'Section Width (Content)';
		$field['choices']       = array( '' => 'Default' ) + mrn_base_stack_get_section_width_choices();
		$field['default_value'] = '';
		$field['instructions']  = 'Default uses the site-wide row width configured in Site Styles. Choose another value to override it for this row.';
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
	if ( in_array( $repeater_name, array( 'tabs', 'card_items', 'stat_items', 'showcase_items' ), true ) ) {
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
			'showcase_items',
			'slider_items',
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
 * @param int               $depth Current recursion depth.
 * @return array<int, mixed>
 */
function mrn_base_stack_ensure_acf_field_origin_names( array $fields, $depth = 0 ) {
	$depth = absint( $depth );
	if ( $depth > 20 ) {
		return $fields;
	}

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

		if ( function_exists( 'mrn_base_stack_field_is_reusable_group_clone' ) && mrn_base_stack_field_is_reusable_group_clone( $field ) ) {
			$fields[ $index ] = $field;
			continue;
		}

		if ( isset( $field['sub_fields'] ) && is_array( $field['sub_fields'] ) ) {
			$field['sub_fields'] = mrn_base_stack_ensure_acf_field_origin_names( $field['sub_fields'], $depth + 1 );
		}

		if ( isset( $field['fields'] ) && is_array( $field['fields'] ) ) {
			$field['fields'] = mrn_base_stack_ensure_acf_field_origin_names( $field['fields'], $depth + 1 );
		}

		if ( isset( $field['layouts'] ) && is_array( $field['layouts'] ) ) {
			foreach ( $field['layouts'] as $layout_key => $layout ) {
				if ( ! is_array( $layout ) || ! isset( $layout['sub_fields'] ) || ! is_array( $layout['sub_fields'] ) ) {
					continue;
				}

				$layout['sub_fields']            = mrn_base_stack_ensure_acf_field_origin_names( $layout['sub_fields'], $depth + 1 );
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

	if ( mrn_base_stack_is_row_spacing_selector_field_name( $field_name ) ) {
		return 'appearance';
	}

	if ( in_array( $field_name, array( 'anchor', 'anchor_id', 'include_in_faq_jump_nav', 'faq_jump_nav_label' ), true ) ) {
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
		|| false !== strpos( $field_name, 'filter_' )
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
 * Determine whether a layout has a real inner collection width target.
 *
 * Sub-content width is intentionally limited to collection-style layouts where
 * an inner repeated items wrapper can be narrower or wider than the outer
 * section shell. One-off text/media/form rows use section width plus spacing.
 *
 * @param string $layout_name Builder layout name.
 * @return bool
 */
function mrn_base_stack_layout_allows_sub_content_width( $layout_name ) {
	$layout_name = sanitize_key( (string) $layout_name );
	$allowed     = array(
		'card',
		'content_lists',
		'faq',
		'faq_block',
		'grid',
		'logos',
		'showcase',
		'slider',
		'stats',
		'tabbed_layout',
	);

	return in_array( $layout_name, $allowed, true );
}

/**
 * Remove sub-content width fields from layouts that should not expose them.
 *
 * @param array<int, mixed> $fields Layout/main field definitions.
 * @return array<int, mixed>
 */
function mrn_base_stack_remove_sub_content_width_field( array $fields ) {
	foreach ( $fields as $index => $field ) {
		if ( ! is_array( $field ) ) {
			continue;
		}

		$field_name = isset( $field['name'] ) ? sanitize_key( (string) $field['name'] ) : '';
		if ( 'sub_content_width' === $field_name ) {
			unset( $fields[ $index ] );
		}
	}

	return array_values( $fields );
}

/**
 * Ensure collection layouts with row-level width controls expose sub-content width.
 *
 * @param array<int, mixed> $fields Layout/main field definitions.
 * @param string            $layout_name Builder layout name.
 * @return array<int, mixed>
 */
function mrn_base_stack_ensure_sub_content_width_field( array $fields, $layout_name = '' ) {
	$layout_name = sanitize_key( (string) $layout_name );
	if ( '' === $layout_name || ! mrn_base_stack_layout_allows_sub_content_width( $layout_name ) ) {
		return mrn_base_stack_remove_sub_content_width_field( $fields );
	}

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
 * Determine whether a field clones one of the reusable block field groups.
 *
 * @param array<string, mixed> $field ACF field definition.
 * @return bool
 */
function mrn_base_stack_field_is_reusable_group_clone( array $field ) {
	$field_type = isset( $field['type'] ) ? sanitize_key( (string) $field['type'] ) : '';
	if ( 'clone' !== $field_type || ! isset( $field['clone'] ) || ! is_array( $field['clone'] ) ) {
		return false;
	}

	foreach ( $field['clone'] as $clone_target ) {
		$clone_key = is_string( $clone_target ) ? sanitize_key( $clone_target ) : '';
		if ( '' !== $clone_key && 0 === strpos( $clone_key, 'group_mrn_reusable_' ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Determine whether a field clones a reusable group that already owns the full tab contract.
 *
 * @param array<string, mixed> $field ACF field definition.
 * @return bool
 */
function mrn_base_stack_field_is_full_contract_reusable_group_clone( array $field ) {
	$field_type = isset( $field['type'] ) ? sanitize_key( (string) $field['type'] ) : '';
	if ( 'clone' !== $field_type || ! isset( $field['clone'] ) || ! is_array( $field['clone'] ) ) {
		return false;
	}

	$full_contract_groups = array(
		'group_mrn_reusable_basic_block',
		'group_mrn_reusable_content_grid',
		'group_mrn_reusable_content_lists',
		'group_mrn_reusable_cta',
		'group_mrn_reusable_faq',
		'group_mrn_reusable_partner',
		'group_mrn_reusable_search_form',
	);

	foreach ( $field['clone'] as $clone_target ) {
		$clone_key = is_string( $clone_target ) ? sanitize_key( $clone_target ) : '';
		if ( '' !== $clone_key && in_array( $clone_key, $full_contract_groups, true ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Determine whether a field list contains a full-contract seamless reusable clone.
 *
 * @param array<int, mixed> $fields Layout/main field definitions.
 * @return bool
 */
function mrn_base_stack_field_list_has_full_contract_reusable_group_clone( array $fields ) {
	foreach ( $fields as $field ) {
		if ( ! is_array( $field ) ) {
			continue;
		}

		if ( mrn_base_stack_field_is_full_contract_reusable_group_clone( $field ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Determine whether a field list contains a seamless reusable group clone.
 *
 * Reusable clone groups already define their own tab structure. Row contracts
 * should not inject a synthetic Content tab ahead of them because ACF flattens
 * seamless clone fields into the parent layout UI.
 *
 * @param array<int, mixed> $fields Layout/main field definitions.
 * @return bool
 */
function mrn_base_stack_field_list_has_reusable_group_clone( array $fields ) {
	foreach ( $fields as $field ) {
		if ( ! is_array( $field ) ) {
			continue;
		}

		if ( mrn_base_stack_field_is_reusable_group_clone( $field ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Remove parent motion settings when a seamless reusable clone owns the full contract.
 *
 * Full-contract reusable groups provide their own Effects tab and motion group.
 * Page-specific wrapper layouts may still contain legacy sibling motion groups,
 * which would render duplicate controls after ACF expands the seamless clone.
 *
 * @param array<int, mixed> $fields Layout/main field definitions.
 * @return array<int, mixed>
 */
function mrn_base_stack_remove_parent_motion_settings_for_full_contract_clone( array $fields ) {
	if ( ! mrn_base_stack_field_list_has_full_contract_reusable_group_clone( $fields ) ) {
		return $fields;
	}

	foreach ( $fields as $index => $field ) {
		if ( ! is_array( $field ) ) {
			continue;
		}

		$field_name = isset( $field['name'] ) ? sanitize_key( (string) $field['name'] ) : '';
		$field_type = isset( $field['type'] ) ? sanitize_key( (string) $field['type'] ) : '';

		if ( 'motion_settings' === $field_name && 'group' === $field_type ) {
			unset( $fields[ $index ] );
		}
	}

	return array_values( $fields );
}

/**
 * Build the shared Display Styles tab field.
 *
 * @param string $key Field key.
 * @return array<string, mixed>
 */
function mrn_base_stack_get_builder_display_styles_tab_field( $key ) {
	return array(
		'key'        => sanitize_key( (string) $key ),
		'label'      => 'Display Styles',
		'name'       => '',
		'aria-label' => '',
		'type'       => 'tab',
		'placement'  => 'top',
		'endpoint'   => 0,
	);
}

/**
 * Build the shared Layout tab field.
 *
 * @param string $key Field key.
 * @return array<string, mixed>
 */
function mrn_base_stack_get_builder_layout_tab_field( $key ) {
	return array(
		'key'        => sanitize_key( (string) $key ),
		'label'      => 'Layout',
		'name'       => '',
		'aria-label' => '',
		'type'       => 'tab',
		'placement'  => 'top',
		'endpoint'   => 0,
	);
}

/**
 * Determine whether a builder layout supports the row section-width contract.
 *
 * @param string $layout_name Builder layout name.
 * @return bool
 */
function mrn_base_stack_layout_allows_section_width( $layout_name ) {
	$layout_name = sanitize_key( (string) $layout_name );
	$allowed     = array(
		'basic',
		'basic_block',
		'body_text',
		'card',
		'content_lists',
		'cta',
		'cta_block',
		'external_widget',
		'faq',
		'faq_block',
		'faq_jump_nav',
		'grid',
		'image_content',
		'logos',
		'reusable_block',
		'searchwp_form',
		'showcase',
		'slider',
		'stats',
		'tabbed_layout',
		'two_column_split',
		'video',
		'wpforms',
	);

	return in_array( $layout_name, $allowed, true );
}

/**
 * Get shared width field names owned by the Layout tab.
 *
 * @param string $layout_name Builder layout name.
 * @return array<int, string>
 */
function mrn_base_stack_get_builder_layout_width_contract_field_names( $layout_name ) {
	$layout_name = sanitize_key( (string) $layout_name );
	$field_names = array();

	if ( mrn_base_stack_layout_allows_section_width( $layout_name ) ) {
		$field_names[] = 'section_width';
	}

	if ( mrn_base_stack_layout_allows_sub_content_width( $layout_name ) ) {
		$field_names[] = 'sub_content_width';
	}

	return $field_names;
}

/**
 * Get shared width fields owned by the Layout tab.
 *
 * @param string $layout_name Builder layout name.
 * @param string $key_seed Field key seed.
 * @return array<int, array<string, mixed>>
 */
function mrn_base_stack_get_builder_layout_width_contract_fields( $layout_name, $key_seed ) {
	$layout_name = sanitize_key( (string) $layout_name );
	$key_seed    = sanitize_key( (string) $key_seed );

	if ( '' === $key_seed ) {
		$key_seed = 'field_mrn_' . $layout_name;
	}

	$fields = array();

	if ( mrn_base_stack_layout_allows_section_width( $layout_name ) ) {
		$fields[] = mrn_base_stack_get_section_width_field(
			$key_seed . '_section_width',
			'section_width',
			'wide',
			'reusable_block' === $layout_name ? 'Block Width' : 'Section Width'
		);
	}

	if ( mrn_base_stack_layout_allows_sub_content_width( $layout_name ) ) {
		$fields[] = mrn_base_stack_get_sub_content_width_field(
			$key_seed . '_sub_content_width',
			'sub_content_width',
			'Section Width (Sub-content)'
		);
	}

	return $fields;
}

/**
 * Get row-flex control field names owned by the Layout tab.
 *
 * @param string $layout_name Builder layout name.
 * @return array<int, string>
 */
function mrn_base_stack_get_builder_layout_flex_contract_field_names( $layout_name ) {
	$layout_name = sanitize_key( (string) $layout_name );

	if ( '' === $layout_name ) {
		return array();
	}

	return array( 'row_flex_controls' );
}

/**
 * Check whether an ACF field is the row-flex controls UI.
 *
 * Older normalized fields could be left as nameless Flexbox message fields,
 * so identify the control by name, label, or panel markup before the Layout
 * contract inserts the current field.
 *
 * @param array<string, mixed> $field ACF field definition.
 * @return bool
 */
function mrn_base_stack_is_builder_layout_flex_controls_field( array $field ) {
	$field_name  = isset( $field['name'] ) ? sanitize_key( (string) $field['name'] ) : '';
	$field_type  = isset( $field['type'] ) ? sanitize_key( (string) $field['type'] ) : '';
	$field_label = isset( $field['label'] ) ? sanitize_title( (string) $field['label'] ) : '';
	$message     = isset( $field['message'] ) && is_string( $field['message'] ) ? $field['message'] : '';

	if ( 'row_flex_controls' === $field_name ) {
		return true;
	}

	if ( 'message' !== $field_type ) {
		return false;
	}

	return 'flexbox' === $field_label || false !== strpos( $message, 'data-mrn-row-flex-panel' );
}

/**
 * Get static row-flex admin controls markup.
 *
 * The controls are hydrated and saved by `admin-row-flex-layout.js`, but the
 * field should still render a complete UI shell before JavaScript runs.
 *
 * @return string
 */
function mrn_base_stack_get_builder_row_flex_controls_markup() {
	return '<div class="mrn-row-flex-panel" data-mrn-row-flex-panel="1">'
		. '<div class="mrn-row-flex-panel__content">'
		. '<p class="mrn-row-flex-panel__description">Configure a lightweight row-level flex wrapper without adding ACF fields.</p>'
		. '<div class="mrn-row-flex-panel__grid">'
		. '<div class="mrn-row-flex-panel__control">'
		. '<label class="mrn-row-flex-panel__checkbox">'
		. '<input type="checkbox" data-mrn-row-flex-control="enabled" />'
		. '<span>Enable Flexbox</span>'
		. '</label>'
		. '</div>'
		. '<div class="mrn-row-flex-panel__control">'
		. '<label class="mrn-row-flex-panel__control-label">Apply To</label>'
		. '<select data-mrn-row-flex-control="scope">'
		. '<option value="row">Row</option>'
		. '<option value="repeaters">Repeaters Only</option>'
		. '</select>'
		. '</div>'
		. '<div class="mrn-row-flex-panel__control">'
		. '<label class="mrn-row-flex-panel__control-label">Direction</label>'
		. '<select data-mrn-row-flex-control="direction">'
		. '<option value="row">Row</option>'
		. '<option value="row-reverse">Row Reverse</option>'
		. '<option value="column">Column</option>'
		. '<option value="column-reverse">Column Reverse</option>'
		. '</select>'
		. '</div>'
		. '<div class="mrn-row-flex-panel__control">'
		. '<label class="mrn-row-flex-panel__control-label">Justify Content</label>'
		. '<select data-mrn-row-flex-control="justify">'
		. '<option value="flex-start">Start</option>'
		. '<option value="center">Center</option>'
		. '<option value="flex-end">End</option>'
		. '<option value="space-between">Space Between</option>'
		. '<option value="space-around">Space Around</option>'
		. '<option value="space-evenly">Space Evenly</option>'
		. '</select>'
		. '</div>'
		. '<div class="mrn-row-flex-panel__control">'
		. '<label class="mrn-row-flex-panel__control-label">Align Items</label>'
		. '<select data-mrn-row-flex-control="align">'
		. '<option value="stretch">Stretch</option>'
		. '<option value="flex-start">Start</option>'
		. '<option value="center">Center</option>'
		. '<option value="flex-end">End</option>'
		. '<option value="baseline">Baseline</option>'
		. '</select>'
		. '</div>'
		. '<div class="mrn-row-flex-panel__control">'
		. '<label class="mrn-row-flex-panel__control-label">Wrap</label>'
		. '<select data-mrn-row-flex-control="wrap">'
		. '<option value="nowrap">No Wrap</option>'
		. '<option value="wrap">Wrap</option>'
		. '<option value="wrap-reverse">Wrap Reverse</option>'
		. '</select>'
		. '</div>'
		. '<div class="mrn-row-flex-panel__control">'
		. '<label class="mrn-row-flex-panel__control-label">Gap (px)</label>'
		. '<input type="number" min="0" max="160" step="0.5" data-mrn-row-flex-control="gap" />'
		. '</div>'
		. '</div>'
		. '</div>'
		. '</div>';
}

/**
 * Get the row-flex control mount field owned by the Layout tab.
 *
 * The controls save to a separate hidden JSON payload, so this ACF field only
 * gives ACF's tab system a stable place to render the admin UI.
 *
 * @param string $layout_name Builder layout name.
 * @param string $key_seed Field key seed.
 * @return array<int, array<string, mixed>>
 */
function mrn_base_stack_get_builder_layout_flex_contract_fields( $layout_name, $key_seed ) {
	$layout_name = sanitize_key( (string) $layout_name );
	$key_seed    = sanitize_key( (string) $key_seed );

	if ( '' === $layout_name ) {
		return array();
	}

	if ( '' === $key_seed ) {
		$key_seed = 'field_mrn_' . $layout_name;
	}

	return array(
		array(
			'key'       => $key_seed . '_row_flex_controls',
			'label'     => 'Flexbox',
			'name'      => 'row_flex_controls',
			'type'      => 'message',
			'message'   => mrn_base_stack_get_builder_row_flex_controls_markup(),
			'new_lines' => '',
			'esc_html'  => 0,
			'wrapper'   => array(
				'width' => '100',
			),
		),
	);
}

/**
 * Deduplicate generated Layout tab fields by ACF field name.
 *
 * @param array<int, mixed> $fields Layout contract fields.
 * @return array<int, mixed>
 */
function mrn_base_stack_dedupe_builder_layout_contract_fields( array $fields ) {
	$deduped    = array();
	$seen_names = array();

	foreach ( $fields as $field ) {
		if ( ! is_array( $field ) ) {
			$deduped[] = $field;
			continue;
		}

		$field_name = isset( $field['name'] ) ? sanitize_key( (string) $field['name'] ) : '';
		if ( '' !== $field_name ) {
			if ( isset( $seen_names[ $field_name ] ) ) {
				continue;
			}
			$seen_names[ $field_name ] = true;
		}

		$deduped[] = $field;
	}

	return $deduped;
}

/**
 * Get layout-specific field names owned by the shared Layout tab.
 *
 * @param string $layout_name Builder layout name.
 * @return array<int, string>
 */
function mrn_base_stack_get_builder_layout_contract_field_names( $layout_name ) {
	$layout_name    = sanitize_key( (string) $layout_name );
	$contract_names = array_merge(
		mrn_base_stack_get_builder_layout_width_contract_field_names( $layout_name ),
		mrn_base_stack_get_builder_layout_flex_contract_field_names( $layout_name )
	);

	if ( 'faq' === $layout_name || 'faq_block' === $layout_name ) {
		return array_merge( $contract_names, array( 'faq_layout' ) );
	}

	if ( 'faq_jump_nav' === $layout_name ) {
		return array_merge(
			$contract_names,
			array(
				'jump_nav_alignment',
				'jump_nav_wrap',
			)
		);
	}

	if ( in_array( $layout_name, array( 'basic', 'basic_block', 'cta', 'cta_block' ), true ) ) {
		return array_merge( $contract_names, array( 'image_placement' ) );
	}

	if ( 'card' === $layout_name ) {
		return array_merge(
			$contract_names,
			array(
				'card_layout',
				'cards_per_row',
				'card_stack_alignment',
			)
		);
	}

	if ( 'two_column_split' === $layout_name ) {
		return array_merge( $contract_names, array( 'column_ratio' ) );
	}

	if ( 'logos' === $layout_name ) {
		return array_merge(
			$contract_names,
			array(
				'display_mode',
				'per_page',
				'show_arrows',
				'show_pagination',
				'pause_on_hover',
				'autoplay',
				'delay_start',
				'delay_time',
				'time_on_slide',
			)
		);
	}

	if ( 'grid' === $layout_name ) {
		return array_merge(
			$contract_names,
			array(
				'columns',
				'equal_height',
				'enable_full_item_link',
				'hide_item_link',
			)
		);
	}

	if ( 'slider' === $layout_name ) {
		return array_merge(
			$contract_names,
			array(
				'per_page',
				'show_arrows',
				'show_pagination',
				'pause_on_hover',
				'autoplay',
				'delay_start',
				'delay_time',
				'time_on_slide',
			)
		);
	}

	if ( 'stats' === $layout_name ) {
		return array_merge(
			$contract_names,
			array(
				'columns',
				'show_dividers',
			)
		);
	}

	if ( 'showcase' === $layout_name ) {
		return array_merge(
			$contract_names,
			array(
				'stagger_style',
				'enable_full_item_link',
				'hide_item_link',
			)
		);
	}

	if ( 'tabbed_layout' === $layout_name ) {
		return array_merge(
			$contract_names,
			array(
				'tab_position',
				'tab_orientation',
				'equal_panel_heights',
			)
		);
	}

	if ( 'image_content' === $layout_name ) {
		return array_merge(
			$contract_names,
			array(
				'image_position',
				'image_size',
				'image_alignment',
			)
		);
	}

	if ( 'video' === $layout_name ) {
		return array_merge(
			$contract_names,
			array(
				'video_position',
				'video_aspect_ratio',
			)
		);
	}

	if ( in_array( $layout_name, array( 'wpforms', 'searchwp_form' ), true ) ) {
		return array_merge( $contract_names, array( 'form_layout' ) );
	}

	if ( 'external_widget' === $layout_name ) {
		return array_merge(
			$contract_names,
			array(
				'embed_layout',
				'embed_aspect_ratio',
				'embed_min_height',
			)
		);
	}

	return $contract_names;
}

/**
 * Get structural layout-mode choices for layouts that offer multiple shapes.
 *
 * Layout modes control arrangement/structure. Display styles control the visual
 * treatment applied to the chosen arrangement.
 *
 * @param string $layout_name Builder layout name.
 * @return array<string, string>
 */
function mrn_base_stack_get_builder_layout_mode_choices( $layout_name ) {
	$layout_name = sanitize_key( (string) $layout_name );

	if ( 'logos' === $layout_name ) {
		return array(
			'grid'   => __( 'Grid', 'mrn-base-stack' ),
			'slider' => __( 'Slider', 'mrn-base-stack' ),
		);
	}

	if ( 'card' === $layout_name ) {
		return array(
			'grid'     => __( 'Grid', 'mrn-base-stack' ),
			'list'     => __( 'List', 'mrn-base-stack' ),
			'featured' => __( 'Featured First Card', 'mrn-base-stack' ),
		);
	}

	if ( 'showcase' === $layout_name ) {
		return array(
			'flat'    => __( 'Grid', 'mrn-base-stack' ),
			'collage' => __( 'Collage', 'mrn-base-stack' ),
			'stacked' => __( 'Stacked', 'mrn-base-stack' ),
		);
	}

	return array();
}

/**
 * Get the stored field name for a layout's structural mode.
 *
 * Existing names are preserved so saved content remains stable.
 *
 * @param string $layout_name Builder layout name.
 * @return string
 */
function mrn_base_stack_get_builder_layout_mode_field_name( $layout_name ) {
	$layout_name = sanitize_key( (string) $layout_name );

	if ( 'logos' === $layout_name ) {
		return 'display_mode';
	}

	if ( 'showcase' === $layout_name ) {
		return 'stagger_style';
	}

	if ( 'card' === $layout_name ) {
		return 'card_layout';
	}

	return '';
}

/**
 * Get layout-mode field names used by one builder layout.
 *
 * @param string $layout_name Builder layout name.
 * @return array<int, string>
 */
function mrn_base_stack_get_builder_layout_mode_field_names( $layout_name ) {
	$field_name = mrn_base_stack_get_builder_layout_mode_field_name( $layout_name );

	return '' !== $field_name ? array( $field_name ) : array();
}

/**
 * Normalize a structural layout mode for a builder layout.
 *
 * @param string $mode        Candidate mode.
 * @param string $layout_name Builder layout name.
 * @return string
 */
function mrn_base_stack_normalize_builder_layout_mode( $mode, $layout_name ) {
	$mode    = sanitize_key( (string) $mode );
	$choices = mrn_base_stack_get_builder_layout_mode_choices( $layout_name );

	if ( '' !== $mode && isset( $choices[ $mode ] ) ) {
		return $mode;
	}

	$first_mode = array_key_first( $choices );

	return is_string( $first_mode ) ? $first_mode : '';
}

/**
 * Build the shared structural Layout Mode field.
 *
 * @param string $layout_name Builder layout name.
 * @param string $key_seed Field key seed.
 * @return array<string, mixed>|null
 */
function mrn_base_stack_get_builder_layout_mode_field( $layout_name, $key_seed ) {
	$layout_name = sanitize_key( (string) $layout_name );
	$key_seed    = sanitize_key( (string) $key_seed );
	$field_name  = mrn_base_stack_get_builder_layout_mode_field_name( $layout_name );
	$choices     = mrn_base_stack_get_builder_layout_mode_choices( $layout_name );

	if ( '' === $field_name || empty( $choices ) ) {
		return null;
	}

	$instructions = __( 'Controls the structural arrangement for this layout. Display Styles control visual treatment.', 'mrn-base-stack' );

	if ( 'showcase' === $layout_name ) {
		$instructions = __( 'Grid is the default for simple image groups. Collage is an editorial treatment for intentionally featured compositions.', 'mrn-base-stack' );
	}

	if ( 'logos' === $layout_name ) {
		$instructions = __( 'Choose whether logos render as a grid or a slider. Visual treatments belong in Display Styles.', 'mrn-base-stack' );
	}

	if ( 'card' === $layout_name ) {
		$instructions = __( 'Choose the structural card arrangement. Display Styles control visual treatment.', 'mrn-base-stack' );
	}

	return array(
		'key'           => $key_seed . '_layout_mode',
		'label'         => 'Layout Mode',
		'name'          => $field_name,
		'aria-label'    => '',
		'type'          => 'select',
		'choices'       => $choices,
		'default_value' => mrn_base_stack_normalize_builder_layout_mode( '', $layout_name ),
		'allow_null'    => 0,
		'ui'            => 1,
		'instructions'  => $instructions,
		'wrapper'       => array(
			'width' => '50',
		),
	);
}

/**
 * Get tab position choices for Tabbed Layout.
 *
 * @return array<string, string>
 */
function mrn_base_stack_get_tabbed_layout_position_choices() {
	return array(
		'top-left'      => __( 'Top - Left', 'mrn-base-stack' ),
		'top-center'    => __( 'Top - Center', 'mrn-base-stack' ),
		'top-right'     => __( 'Top - Right', 'mrn-base-stack' ),
		'left-top'      => __( 'Left of content - Top', 'mrn-base-stack' ),
		'left-center'   => __( 'Left of content - Center', 'mrn-base-stack' ),
		'left-bottom'   => __( 'Left of content - Bottom', 'mrn-base-stack' ),
	);
}

/**
 * Get Card stack alignment choices.
 *
 * @return array<string, string>
 */
function mrn_base_stack_get_card_stack_alignment_choices() {
	return array(
		'left'   => __( 'Left', 'mrn-base-stack' ),
		'center' => __( 'Center', 'mrn-base-stack' ),
		'right'  => __( 'Right', 'mrn-base-stack' ),
	);
}

/**
 * Get Card max-per-row choices.
 *
 * @return array<int, string>
 */
function mrn_base_stack_get_card_per_row_choices() {
	return array(
		'1' => __( '1', 'mrn-base-stack' ),
		'2' => __( '2', 'mrn-base-stack' ),
		'3' => __( '3', 'mrn-base-stack' ),
		'4' => __( '4', 'mrn-base-stack' ),
		'5' => __( '5', 'mrn-base-stack' ),
		'6' => __( '6', 'mrn-base-stack' ),
	);
}

/**
 * Normalize Card max-per-row.
 *
 * @param string|int $cards_per_row Candidate count.
 * @return int
 */
function mrn_base_stack_normalize_card_per_row( $cards_per_row ) {
	$cards_per_row = absint( $cards_per_row );

	if ( $cards_per_row < 1 || $cards_per_row > 6 ) {
		return 3;
	}

	return $cards_per_row;
}

/**
 * Normalize Card stack alignment.
 *
 * @param string $alignment Candidate alignment.
 * @return string
 */
function mrn_base_stack_normalize_card_stack_alignment( $alignment ) {
	$alignment = sanitize_key( (string) $alignment );
	$choices   = mrn_base_stack_get_card_stack_alignment_choices();

	return isset( $choices[ $alignment ] ) ? $alignment : 'left';
}

/**
 * Get Two Column Split ratio choices.
 *
 * @return array<string, string>
 */
function mrn_base_stack_get_two_column_ratio_choices() {
	return array(
		'50-50' => __( '50 / 50', 'mrn-base-stack' ),
		'60-40' => __( '60 / 40', 'mrn-base-stack' ),
		'40-60' => __( '40 / 60', 'mrn-base-stack' ),
		'67-33' => __( '67 / 33', 'mrn-base-stack' ),
		'33-67' => __( '33 / 67', 'mrn-base-stack' ),
	);
}

/**
 * Normalize Two Column Split ratio.
 *
 * @param string $ratio Candidate ratio.
 * @return string
 */
function mrn_base_stack_normalize_two_column_ratio( $ratio ) {
	$ratio   = sanitize_key( (string) $ratio );
	$choices = mrn_base_stack_get_two_column_ratio_choices();

	return isset( $choices[ $ratio ] ) ? $ratio : '50-50';
}

/**
 * Get Video position choices.
 *
 * @return array<string, string>
 */
function mrn_base_stack_get_video_position_choices() {
	return array(
		'bottom' => __( 'Video below content', 'mrn-base-stack' ),
		'top'    => __( 'Video above content', 'mrn-base-stack' ),
		'right'  => __( 'Video right of content', 'mrn-base-stack' ),
		'left'   => __( 'Video left of content', 'mrn-base-stack' ),
	);
}

/**
 * Normalize Video position.
 *
 * @param string $position Candidate position.
 * @return string
 */
function mrn_base_stack_normalize_video_position( $position ) {
	$position = sanitize_key( (string) $position );
	$choices  = mrn_base_stack_get_video_position_choices();

	return isset( $choices[ $position ] ) ? $position : 'bottom';
}

/**
 * Get Video aspect ratio choices.
 *
 * @return array<string, string>
 */
function mrn_base_stack_get_video_aspect_ratio_choices() {
	return array(
		'16-9' => __( '16:9', 'mrn-base-stack' ),
		'4-3'  => __( '4:3', 'mrn-base-stack' ),
		'1-1'  => __( '1:1', 'mrn-base-stack' ),
		'21-9' => __( '21:9', 'mrn-base-stack' ),
	);
}

/**
 * Normalize Video aspect ratio.
 *
 * @param string $ratio Candidate ratio.
 * @return string
 */
function mrn_base_stack_normalize_video_aspect_ratio( $ratio ) {
	$ratio   = sanitize_key( (string) $ratio );
	$choices = mrn_base_stack_get_video_aspect_ratio_choices();

	return isset( $choices[ $ratio ] ) ? $ratio : '16-9';
}

/**
 * Normalize a Tabbed Layout position.
 *
 * @param string $position Candidate position.
 * @param string $legacy_orientation Legacy orientation fallback.
 * @return string
 */
function mrn_base_stack_normalize_tabbed_layout_position( $position, $legacy_orientation = '' ) {
	$position = sanitize_key( (string) $position );
	$choices  = mrn_base_stack_get_tabbed_layout_position_choices();

	if ( '' !== $position && isset( $choices[ $position ] ) ) {
		return $position;
	}

	$legacy_orientation = sanitize_key( (string) $legacy_orientation );

	return 'vertical' === $legacy_orientation ? 'left-top' : 'top-left';
}

/**
 * Get tab style choices for Tabbed Layout.
 *
 * @return array<string, string>
 */
function mrn_base_stack_get_tabbed_layout_tab_style_choices() {
	return array(
		'link'             => __( 'Text', 'mrn-base-stack' ),
		'text-dividers'    => __( 'Text Dividers', 'mrn-base-stack' ),
		'underline'        => __( 'Underline', 'mrn-base-stack' ),
		'underline-track'  => __( 'Underline Track', 'mrn-base-stack' ),
		'pill'             => __( 'Outline Pill', 'mrn-base-stack' ),
		'soft-pill'        => __( 'Soft Pill', 'mrn-base-stack' ),
		'button'           => __( 'Button', 'mrn-base-stack' ),
		'segmented'        => __( 'Segmented', 'mrn-base-stack' ),
		'filled'           => __( 'Filled', 'mrn-base-stack' ),
		'filled-segmented' => __( 'Filled Segmented', 'mrn-base-stack' ),
		'tab'              => __( 'Tab', 'mrn-base-stack' ),
	);
}

/**
 * Normalize a Tabbed Layout tab style.
 *
 * @param string $style Candidate style.
 * @return string
 */
function mrn_base_stack_normalize_tabbed_layout_tab_style( $style ) {
	$style   = sanitize_key( (string) $style );
	$choices = mrn_base_stack_get_tabbed_layout_tab_style_choices();

	return isset( $choices[ $style ] ) ? $style : 'pill';
}

/**
 * Get FAQ Jump Nav alignment choices.
 *
 * @return array<string, string>
 */
function mrn_base_stack_get_faq_jump_nav_alignment_choices() {
	return array(
		'left'   => __( 'Left', 'mrn-base-stack' ),
		'center' => __( 'Center', 'mrn-base-stack' ),
		'right'  => __( 'Right', 'mrn-base-stack' ),
	);
}

/**
 * Normalize FAQ Jump Nav alignment.
 *
 * @param string $alignment Candidate alignment.
 * @return string
 */
function mrn_base_stack_normalize_faq_jump_nav_alignment( $alignment ) {
	$alignment = sanitize_key( (string) $alignment );
	$choices   = mrn_base_stack_get_faq_jump_nav_alignment_choices();

	return isset( $choices[ $alignment ] ) ? $alignment : 'left';
}

/**
 * Get FAQ Jump Nav wrapping choices.
 *
 * @return array<string, string>
 */
function mrn_base_stack_get_faq_jump_nav_wrap_choices() {
	return array(
		'wrap'   => __( 'Wrap to multiple lines', 'mrn-base-stack' ),
		'scroll' => __( 'Single-line horizontal scroll', 'mrn-base-stack' ),
	);
}

/**
 * Normalize FAQ Jump Nav wrapping.
 *
 * @param string $wrap Candidate wrapping behavior.
 * @return string
 */
function mrn_base_stack_normalize_faq_jump_nav_wrap( $wrap ) {
	$wrap    = sanitize_key( (string) $wrap );
	$choices = mrn_base_stack_get_faq_jump_nav_wrap_choices();

	return isset( $choices[ $wrap ] ) ? $wrap : 'wrap';
}

/**
 * Build layout-specific controls for the shared Layout tab.
 *
 * @param string $layout_name Builder layout name.
 * @param string $key_seed Field key seed.
 * @return array<int, array<string, mixed>>
 */
function mrn_base_stack_get_builder_layout_contract_fields( $layout_name, $key_seed ) {
	$layout_name = sanitize_key( (string) $layout_name );
	$key_seed    = sanitize_key( (string) $key_seed );

	if ( '' === $key_seed ) {
		$key_seed = 'field_mrn_' . $layout_name;
	}

	if ( 'faq' === $layout_name || 'faq_block' === $layout_name ) {
		return array(
			array(
				'key'           => $key_seed . '_faq_layout',
				'label'         => 'FAQ Layout',
				'name'          => 'faq_layout',
				'aria-label'    => '',
				'type'          => 'select',
				'choices'       => array(
					'stacked' => 'Stacked',
					'split'   => 'Split heading / items',
				),
				'default_value' => 'stacked',
				'allow_null'    => 0,
				'ui'            => 1,
				'instructions'  => 'Choose whether the section heading stacks above the accordion or sits beside the items.',
				'wrapper'       => array(
					'width' => '50',
				),
			),
		);
	}

	if ( 'faq_jump_nav' === $layout_name ) {
		return array(
			array(
				'key'           => $key_seed . '_jump_nav_alignment',
				'label'         => 'List Alignment',
				'name'          => 'jump_nav_alignment',
				'aria-label'    => '',
				'type'          => 'select',
				'choices'       => mrn_base_stack_get_faq_jump_nav_alignment_choices(),
				'default_value' => 'left',
				'allow_null'    => 0,
				'multiple'      => 0,
				'return_format' => 'value',
				'ui'            => 1,
				'instructions'  => 'Controls the alignment of the jump links within the selected section width.',
				'wrapper'       => array(
					'width' => '50',
				),
			),
			array(
				'key'           => $key_seed . '_jump_nav_wrap',
				'label'         => 'Link Wrapping',
				'name'          => 'jump_nav_wrap',
				'aria-label'    => '',
				'type'          => 'select',
				'choices'       => mrn_base_stack_get_faq_jump_nav_wrap_choices(),
				'default_value' => 'wrap',
				'allow_null'    => 0,
				'multiple'      => 0,
				'return_format' => 'value',
				'ui'            => 1,
				'instructions'  => 'Wrap is the default. Horizontal scroll preserves one line while remaining touch friendly on narrow screens.',
				'wrapper'       => array(
					'width' => '50',
				),
			),
		);
	}

	if ( 'reusable_block' === $layout_name ) {
		return array(
			mrn_base_stack_get_section_width_field(
				$key_seed . '_section_width',
				'section_width',
				'wide',
				'Block Width'
			),
		);
	}

	if ( 'content_lists' === $layout_name ) {
		return array(
			mrn_base_stack_get_section_width_field(
				$key_seed . '_section_width',
				'section_width',
				'wide',
				'Section Width'
			),
			mrn_base_stack_get_sub_content_width_field(
				$key_seed . '_sub_content_width',
				'sub_content_width',
				'Section Width (Sub-content)'
			),
		);
	}

	if ( in_array( $layout_name, array( 'basic', 'basic_block', 'cta', 'cta_block' ), true ) ) {
		return array(
			array(
				'key'           => $key_seed . '_image_placement',
				'label'         => 'Image Position',
				'name'          => 'image_placement',
				'aria-label'    => '',
				'type'          => 'select',
				'choices'       => array(
					'left'  => 'Left',
					'right' => 'Right',
				),
				'default_value' => 'left',
				'allow_null'    => 0,
				'multiple'      => 0,
				'ui'            => 1,
				'instructions'  => 'Controls whether the image sits left or right of the content on wide screens. Mobile stacks naturally.',
				'wrapper'       => array(
					'width' => '50',
				),
			),
		);
	}

	if ( 'card' === $layout_name ) {
		$layout_mode_field = mrn_base_stack_get_builder_layout_mode_field( 'card', $key_seed );

		$fields = is_array( $layout_mode_field ) ? array( $layout_mode_field ) : array();

		$fields[] = array(
			'key'           => $key_seed . '_cards_per_row',
			'label'         => 'Max Cards Per Row',
			'name'          => 'cards_per_row',
			'aria-label'    => '',
			'type'          => 'select',
			'choices'       => mrn_base_stack_get_card_per_row_choices(),
			'default_value' => '3',
			'allow_null'    => 0,
			'ui'            => 1,
			'instructions'  => 'Caps the number of cards per row on wide screens. Cards wrap naturally on smaller screens. List mode always renders one card per row.',
			'wrapper'       => array(
				'width' => '50',
			),
		);

		$fields[] = array(
			'key'           => $key_seed . '_card_stack_alignment',
			'label'         => 'Stack Alignment',
			'name'          => 'card_stack_alignment',
			'aria-label'    => '',
			'type'          => 'select',
			'choices'       => mrn_base_stack_get_card_stack_alignment_choices(),
			'default_value' => 'left',
			'allow_null'    => 0,
			'ui'            => 1,
			'instructions'  => 'Controls left, center, or right alignment when cards stack into one column.',
			'wrapper'       => array(
				'width' => '50',
			),
		);

		return $fields;
	}

	if ( 'two_column_split' === $layout_name ) {
		return array(
			array(
				'key'           => $key_seed . '_column_ratio',
				'label'         => 'Column Split',
				'name'          => 'column_ratio',
				'aria-label'    => '',
				'type'          => 'select',
				'choices'       => mrn_base_stack_get_two_column_ratio_choices(),
				'default_value' => '50-50',
				'allow_null'    => 0,
				'multiple'      => 0,
				'return_format' => 'value',
				'ui'            => 1,
				'instructions'  => 'Controls the wide-screen column widths. Columns stack to one column on smaller screens.',
				'wrapper'       => array(
					'width' => '50',
				),
			),
		);
	}

	if ( 'grid' === $layout_name ) {
		return array(
			array(
				'key'           => $key_seed . '_columns',
				'label'         => 'Max Items Per Row',
				'name'          => 'columns',
				'aria-label'    => '',
				'type'          => 'select',
				'choices'       => array(
					'2' => '2',
					'3' => '3',
					'4' => '4',
				),
				'default_value' => '3',
				'allow_null'    => 0,
				'multiple'      => 0,
				'return_format' => 'value',
				'ui'            => 1,
				'instructions'  => 'Caps the number of grid items per row on wide screens. Items shrink and wrap naturally on smaller screens.',
				'wrapper'       => array(
					'width' => '50',
				),
			),
			array(
				'key'           => $key_seed . '_equal_height',
				'label'         => 'Equal Height Items',
				'name'          => 'equal_height',
				'aria-label'    => '',
				'type'          => 'true_false',
				'ui'            => 1,
				'default_value' => 0,
				'ui_on_text'    => 'On',
				'ui_off_text'   => 'Off',
				'instructions'  => 'Keeps item bodies aligned when rows contain uneven content.',
				'wrapper'       => array(
					'width' => '50',
				),
			),
			array(
				'key'           => $key_seed . '_enable_full_item_link',
				'label'         => 'Full Item Link',
				'name'          => 'enable_full_item_link',
				'aria-label'    => '',
				'type'          => 'true_false',
				'ui'            => 1,
				'default_value' => 0,
				'ui_on_text'    => 'On',
				'ui_off_text'   => 'Off',
				'instructions'  => 'Uses the item link as the full grid-item click target when an item has a link.',
				'wrapper'       => array(
					'width' => '50',
				),
			),
			array(
				'key'               => $key_seed . '_hide_item_link',
				'label'             => 'Hide Visible Link',
				'name'              => 'hide_item_link',
				'aria-label'        => '',
				'type'              => 'true_false',
				'ui'                => 1,
				'default_value'     => 0,
				'ui_on_text'        => 'Hide',
				'ui_off_text'       => 'Show',
				'instructions'      => 'Keeps the full-item link active while hiding the visible link label.',
				'conditional_logic' => array(
					array(
						array(
							'field'    => $key_seed . '_enable_full_item_link',
							'operator' => '==',
							'value'    => '1',
						),
					),
				),
				'wrapper'           => array(
					'width' => '50',
				),
			),
		);
	}

	if ( 'logos' === $layout_name ) {
		$layout_mode_field = mrn_base_stack_get_builder_layout_mode_field( 'logos', $key_seed );
		$fields            = array();

		if ( is_array( $layout_mode_field ) ) {
			$fields[] = $layout_mode_field;
		}

		return array_merge(
			$fields,
			array(
				array(
					'key'           => $key_seed . '_per_page',
					'label'         => 'Logos per Row/View',
					'name'          => 'per_page',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => array(
						'3' => '3',
						'4' => '4',
						'5' => '5',
						'6' => '6',
					),
					'default_value' => '6',
					'allow_null'    => 0,
					'ui'            => 1,
					'instructions'  => 'Controls grid columns and slider slides per view.',
					'wrapper'       => array(
						'width' => '25',
					),
				),
				array(
					'key'           => $key_seed . '_show_arrows',
					'label'         => 'Show Arrows',
					'name'          => 'show_arrows',
					'aria-label'    => '',
					'type'          => 'true_false',
					'ui'            => 1,
					'default_value' => 0,
					'ui_on_text'    => 'On',
					'ui_off_text'   => 'Off',
					'wrapper'       => array(
						'width' => '25',
					),
				),
				array(
					'key'           => $key_seed . '_show_pagination',
					'label'         => 'Show Pagination',
					'name'          => 'show_pagination',
					'aria-label'    => '',
					'type'          => 'true_false',
					'ui'            => 1,
					'default_value' => 0,
					'ui_on_text'    => 'On',
					'ui_off_text'   => 'Off',
					'wrapper'       => array(
						'width' => '25',
					),
				),
				array(
					'key'           => $key_seed . '_pause_on_hover',
					'label'         => 'Pause on Hover',
					'name'          => 'pause_on_hover',
					'aria-label'    => '',
					'type'          => 'true_false',
					'ui'            => 1,
					'default_value' => 1,
					'ui_on_text'    => 'On',
					'ui_off_text'   => 'Off',
					'wrapper'       => array(
						'width' => '25',
					),
				),
				array(
					'key'           => $key_seed . '_autoplay',
					'label'         => 'Autoplay',
					'name'          => 'autoplay',
					'aria-label'    => '',
					'type'          => 'true_false',
					'ui'            => 1,
					'default_value' => 0,
					'ui_on_text'    => 'On',
					'ui_off_text'   => 'Off',
					'wrapper'       => array(
						'width' => '25',
					),
				),
				array(
					'key'           => $key_seed . '_delay_start',
					'label'         => 'Delay Start',
					'name'          => 'delay_start',
					'aria-label'    => '',
					'type'          => 'number',
					'default_value' => 0,
					'min'           => 0,
					'step'          => 0.5,
					'instructions'  => 'Seconds to wait before autoplay begins.',
					'wrapper'       => array(
						'width' => '33',
					),
				),
				array(
					'key'           => $key_seed . '_delay_time',
					'label'         => 'Delay Time',
					'name'          => 'delay_time',
					'aria-label'    => '',
					'type'          => 'number',
					'default_value' => 5,
					'min'           => 1,
					'step'          => 0.5,
					'instructions'  => 'Seconds each slide stays visible during autoplay.',
					'wrapper'       => array(
						'width' => '33',
					),
				),
				array(
					'key'           => $key_seed . '_time_on_slide',
					'label'         => 'Time on Slide',
					'name'          => 'time_on_slide',
					'aria-label'    => '',
					'type'          => 'number',
					'default_value' => 600,
					'min'           => 100,
					'step'          => 50,
					'instructions'  => 'Transition speed in milliseconds.',
					'wrapper'       => array(
						'width' => '34',
					),
				),
			)
		);
	}

	if ( 'slider' === $layout_name ) {
		return array(
			array(
				'key'           => $key_seed . '_per_page',
				'label'         => 'Slides per View',
				'name'          => 'per_page',
				'aria-label'    => '',
				'type'          => 'select',
				'choices'       => array(
					'1' => '1',
					'2' => '2',
					'3' => '3',
				),
				'default_value' => '1',
				'allow_null'    => 0,
				'ui'            => 1,
				'instructions'  => 'Controls how many slides are visible per view.',
				'wrapper'       => array(
					'width' => '25',
				),
			),
			array(
				'key'           => $key_seed . '_show_arrows',
				'label'         => 'Show Arrows',
				'name'          => 'show_arrows',
				'aria-label'    => '',
				'type'          => 'true_false',
				'ui'            => 1,
				'default_value' => 1,
				'ui_on_text'    => 'On',
				'ui_off_text'   => 'Off',
				'wrapper'       => array(
					'width' => '25',
				),
			),
			array(
				'key'           => $key_seed . '_show_pagination',
				'label'         => 'Show Pagination',
				'name'          => 'show_pagination',
				'aria-label'    => '',
				'type'          => 'true_false',
				'ui'            => 1,
				'default_value' => 1,
				'ui_on_text'    => 'On',
				'ui_off_text'   => 'Off',
				'wrapper'       => array(
					'width' => '25',
				),
			),
			array(
				'key'           => $key_seed . '_pause_on_hover',
				'label'         => 'Pause on Hover',
				'name'          => 'pause_on_hover',
				'aria-label'    => '',
				'type'          => 'true_false',
				'ui'            => 1,
				'default_value' => 1,
				'ui_on_text'    => 'On',
				'ui_off_text'   => 'Off',
				'wrapper'       => array(
					'width' => '25',
				),
			),
			array(
				'key'           => $key_seed . '_autoplay',
				'label'         => 'Autoplay',
				'name'          => 'autoplay',
				'aria-label'    => '',
				'type'          => 'true_false',
				'ui'            => 1,
				'default_value' => 0,
				'ui_on_text'    => 'On',
				'ui_off_text'   => 'Off',
				'wrapper'       => array(
					'width' => '25',
				),
			),
			array(
				'key'           => $key_seed . '_delay_start',
				'label'         => 'Delay Start',
				'name'          => 'delay_start',
				'aria-label'    => '',
				'type'          => 'number',
				'default_value' => 0,
				'min'           => 0,
				'step'          => 0.5,
				'instructions'  => 'Seconds to wait before autoplay begins.',
				'wrapper'       => array(
					'width' => '33',
				),
			),
			array(
				'key'           => $key_seed . '_delay_time',
				'label'         => 'Delay Time',
				'name'          => 'delay_time',
				'aria-label'    => '',
				'type'          => 'number',
				'default_value' => 5,
				'min'           => 1,
				'step'          => 0.5,
				'instructions'  => 'Seconds each slide stays visible during autoplay.',
				'wrapper'       => array(
					'width' => '33',
				),
			),
			array(
				'key'           => $key_seed . '_time_on_slide',
				'label'         => 'Time on Slide',
				'name'          => 'time_on_slide',
				'aria-label'    => '',
				'type'          => 'number',
				'default_value' => 600,
				'min'           => 100,
				'step'          => 50,
				'instructions'  => 'Transition speed in milliseconds.',
				'wrapper'       => array(
					'width' => '34',
				),
			),
		);
	}

	if ( 'stats' === $layout_name ) {
		return array(
			array(
				'key'           => $key_seed . '_columns',
				'label'         => 'Columns',
				'name'          => 'columns',
				'aria-label'    => '',
				'type'          => 'select',
				'choices'       => array(
					'2' => '2',
					'3' => '3',
					'4' => '4',
				),
				'default_value' => '2',
				'allow_null'    => 0,
				'ui'            => 1,
				'instructions'  => 'Controls the number of stat columns on wide screens. Columns collapse responsively on smaller screens.',
				'wrapper'       => array(
					'width' => '50',
				),
			),
			array(
				'key'           => $key_seed . '_show_dividers',
				'label'         => 'Show Dividers',
				'name'          => 'show_dividers',
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
		);
	}

	if ( 'showcase' === $layout_name ) {
		$layout_mode_field = mrn_base_stack_get_builder_layout_mode_field( 'showcase', $key_seed );
		$fields            = is_array( $layout_mode_field ) ? array( $layout_mode_field ) : array();

		return array_merge(
			$fields,
			array(
				array(
					'key'           => $key_seed . '_enable_full_item_link',
					'label'         => 'Make Entire Showcase Clickable',
					'name'          => 'enable_full_item_link',
					'aria-label'    => '',
					'type'          => 'true_false',
					'ui'            => 1,
					'default_value' => 0,
					'ui_on_text'    => 'On',
					'ui_off_text'   => 'Off',
					'wrapper'       => array(
						'width' => '25',
					),
				),
				array(
					'key'               => $key_seed . '_hide_item_link',
					'label'             => 'Hide Link Label',
					'name'              => 'hide_item_link',
					'aria-label'        => '',
					'type'              => 'true_false',
					'ui'                => 1,
					'default_value'     => 0,
					'ui_on_text'        => 'On',
					'ui_off_text'       => 'Off',
					'conditional_logic' => array(
						array(
							array(
								'field'    => $key_seed . '_enable_full_item_link',
								'operator' => '==',
								'value'    => '1',
							),
						),
					),
					'wrapper'           => array(
						'width' => '25',
					),
				),
			)
		);
	}

	if ( 'tabbed_layout' === $layout_name ) {
		return array(
			array(
				'key'           => $key_seed . '_tab_position',
				'label'         => 'Tab Position',
				'name'          => 'tab_position',
				'aria-label'    => '',
				'type'          => 'select',
				'choices'       => mrn_base_stack_get_tabbed_layout_position_choices(),
				'default_value' => 'top-left',
				'allow_null'    => 0,
				'multiple'      => 0,
				'ui'            => 1,
				'instructions'  => 'Controls where the tab controls sit relative to the panel content.',
				'wrapper'       => array(
					'width' => '50',
				),
			),
			array(
				'key'           => $key_seed . '_equal_panel_heights',
				'label'         => 'Equalize Panel Heights',
				'name'          => 'equal_panel_heights',
				'aria-label'    => '',
				'type'          => 'true_false',
				'ui'            => 1,
				'default_value' => 0,
				'ui_on_text'    => 'On',
				'ui_off_text'   => 'Off',
				'instructions'  => 'Match the active panel height to the tallest tab panel.',
				'wrapper'       => array(
					'width' => '50',
				),
			),
		);
	}

	if ( 'image_content' === $layout_name ) {
		return array(
			array(
				'key'           => $key_seed . '_image_position',
				'label'         => 'Image Position',
				'name'          => 'image_position',
				'aria-label'    => '',
				'type'          => 'select',
				'choices'       => array(
					'top'    => 'Top',
					'bottom' => 'Bottom',
				),
				'default_value' => 'top',
				'allow_null'    => 0,
				'multiple'      => 0,
				'ui'            => 1,
				'instructions'  => 'Controls whether the image renders before or after the text content.',
				'wrapper'       => array(
					'width' => '33',
				),
			),
			array(
				'key'           => $key_seed . '_image_size',
				'label'         => 'Image Size',
				'name'          => 'image_size',
				'aria-label'    => '',
				'type'          => 'select',
				'choices'       => array(
					'contained' => 'Contained',
					'cover'     => 'Cover',
				),
				'default_value' => 'contained',
				'allow_null'    => 0,
				'multiple'      => 0,
				'ui'            => 1,
				'instructions'  => 'Contained preserves the full media. Cover crops the image to fill its media area.',
				'wrapper'       => array(
					'width' => '33',
				),
			),
			array(
				'key'           => $key_seed . '_image_alignment',
				'label'         => 'Image Alignment',
				'name'          => 'image_alignment',
				'aria-label'    => '',
				'type'          => 'select',
				'choices'       => array(
					'left'   => 'Left',
					'center' => 'Center',
					'right'  => 'Right',
				),
				'default_value' => 'center',
				'allow_null'    => 0,
				'multiple'      => 0,
				'ui'            => 1,
				'instructions'  => 'Aligns contained images inside the media area.',
				'wrapper'       => array(
					'width' => '34',
				),
			),
		);
	}

	if ( 'video' === $layout_name ) {
		return array(
			array(
				'key'           => $key_seed . '_video_position',
				'label'         => 'Video Position',
				'name'          => 'video_position',
				'aria-label'    => '',
				'type'          => 'select',
				'choices'       => mrn_base_stack_get_video_position_choices(),
				'default_value' => 'bottom',
				'allow_null'    => 0,
				'multiple'      => 0,
				'ui'            => 1,
				'instructions'  => 'Controls where the video sits relative to the text content. Left and right collapse responsively on smaller screens.',
				'wrapper'       => array(
					'width' => '50',
				),
			),
			array(
				'key'           => $key_seed . '_video_aspect_ratio',
				'label'         => 'Aspect Ratio',
				'name'          => 'video_aspect_ratio',
				'aria-label'    => '',
				'type'          => 'select',
				'choices'       => mrn_base_stack_get_video_aspect_ratio_choices(),
				'default_value' => '16-9',
				'allow_null'    => 0,
				'multiple'      => 0,
				'ui'            => 1,
				'instructions'  => 'Controls the reserved media area so deferred video loading does not shift the layout.',
				'wrapper'       => array(
					'width' => '50',
				),
			),
		);
	}

	if ( in_array( $layout_name, array( 'wpforms', 'searchwp_form' ), true ) ) {
		return array(
			array(
				'key'           => $key_seed . '_form_layout',
				'label'         => 'Form Layout',
				'name'          => 'form_layout',
				'aria-label'    => '',
				'type'          => 'select',
				'choices'       => array(
					'stacked'    => 'Stacked',
					'form-right' => 'Content left / form right',
					'form-left'  => 'Form left / content right',
				),
				'default_value' => 'stacked',
				'allow_null'    => 0,
				'multiple'      => 0,
				'ui'            => 1,
				'instructions'  => 'Controls how the intro content and selected form are arranged.',
				'wrapper'       => array(
					'width' => '50',
				),
			),
		);
	}

	if ( 'external_widget' === $layout_name ) {
		$layout_field_key = $key_seed . '_embed_layout';

		return array(
			array(
				'key'           => $layout_field_key,
				'label'         => 'Embed Layout',
				'name'          => 'embed_layout',
				'aria-label'    => '',
				'type'          => 'select',
				'choices'       => array(
					'natural' => 'Natural embed size',
					'ratio'   => 'Fixed aspect ratio',
				),
				'default_value' => 'natural',
				'allow_null'    => 0,
				'multiple'      => 0,
				'ui'            => 1,
				'instructions'  => 'Use natural for widgets that provide their own height. Use fixed aspect ratio for maps, videos, and responsive iframe embeds.',
				'wrapper'       => array(
					'width' => '34',
				),
			),
			array(
				'key'               => $key_seed . '_embed_aspect_ratio',
				'label'             => 'Aspect Ratio',
				'name'              => 'embed_aspect_ratio',
				'aria-label'        => '',
				'type'              => 'select',
				'choices'           => array(
					'16-9' => '16:9',
					'4-3'  => '4:3',
					'1-1'  => '1:1',
					'21-9' => '21:9',
				),
				'default_value'     => '16-9',
				'allow_null'        => 0,
				'multiple'          => 0,
				'ui'                => 1,
				'conditional_logic' => array(
					array(
						array(
							'field'    => $layout_field_key,
							'operator' => '==',
							'value'    => 'ratio',
						),
					),
				),
				'wrapper'           => array(
					'width' => '33',
				),
			),
			array(
				'key'          => $key_seed . '_embed_min_height',
				'label'        => 'Minimum Height',
				'name'         => 'embed_min_height',
				'aria-label'   => '',
				'type'         => 'number',
				'append'       => 'px',
				'min'          => 0,
				'step'         => 1,
				'instructions' => 'Optional. Use when a widget needs extra vertical room.',
				'wrapper'      => array(
					'width' => '33',
				),
			),
		);
	}

	return array();
}

/**
 * Get layout-specific field names owned by the shared Display Styles tab.
 *
 * @param string $layout_name Builder layout name.
 * @return array<int, string>
 */
function mrn_base_stack_get_builder_display_styles_contract_field_names( $layout_name ) {
	$layout_name = sanitize_key( (string) $layout_name );

	if ( 'tabbed_layout' === $layout_name ) {
		return array( 'tab_style' );
	}

	if ( 'external_widget' === $layout_name ) {
		return array( 'iframe_border' );
	}

	return array();
}

/**
 * Build layout-specific controls for the shared Display Styles tab.
 *
 * @param string $layout_name Builder layout name.
 * @param string $key_seed Field key seed.
 * @return array<int, array<string, mixed>>
 */
function mrn_base_stack_get_builder_display_styles_contract_fields( $layout_name, $key_seed ) {
	$layout_name = sanitize_key( (string) $layout_name );
	$key_seed    = sanitize_key( (string) $key_seed );

	if ( '' === $key_seed ) {
		$key_seed = 'field_mrn_' . $layout_name . '_display';
	}

	if ( 'tabbed_layout' === $layout_name ) {
		return array(
			array(
				'key'           => $key_seed . '_tab_style',
				'label'         => 'Tab Style',
				'name'          => 'tab_style',
				'aria-label'    => '',
				'type'          => 'select',
				'choices'       => mrn_base_stack_get_tabbed_layout_tab_style_choices(),
				'default_value' => 'pill',
				'allow_null'    => 0,
				'multiple'      => 0,
				'ui'            => 1,
				'instructions'  => 'Controls the visual treatment of the tab controls.',
				'wrapper'       => array(
					'width' => '50',
				),
			),
		);
	}

	if ( 'external_widget' === $layout_name ) {
		return array(
			array(
				'key'           => $key_seed . '_iframe_border',
				'label'         => 'Iframe Border',
				'name'          => 'iframe_border',
				'aria-label'    => '',
				'type'          => 'select',
				'choices'       => array(
					'none'  => 'None',
					'theme' => 'Theme border',
				),
				'default_value' => 'none',
				'allow_null'    => 0,
				'multiple'      => 0,
				'ui'            => 1,
				'instructions'  => 'Controls the iframe element border when the embed outputs an iframe.',
				'wrapper'       => array(
					'width' => '50',
				),
			),
		);
	}

	return array();
}

/**
 * Build a builder layout Display Mode field.
 *
 * @param string $key Field key.
 * @param string $layout_name Builder layout name.
 * @return array<string, mixed>
 */
function mrn_base_stack_get_builder_layout_display_mode_field( $key, $layout_name ) {
	$choices       = mrn_base_stack_get_builder_layout_display_mode_choices( $layout_name );
	$default_value = mrn_base_stack_normalize_builder_layout_display_mode( '', $layout_name );

	return array(
		'key'           => sanitize_key( (string) $key ),
		'label'         => 'Display Mode',
		'name'          => 'display_mode',
		'aria-label'    => '',
		'type'          => 'select',
		'choices'       => $choices,
		'default_value' => $default_value,
		'allow_null'    => 0,
		'ui'            => 1,
		'wrapper'       => array(
			'width' => '50',
		),
	);
}

/**
 * Build a builder layout Display Style field.
 *
 * @param string $key Field key.
 * @param string $layout_name Builder layout name.
 * @return array<string, mixed>
 */
function mrn_base_stack_get_builder_layout_display_style_field( $key, $layout_name ) {
	$choices       = mrn_base_stack_get_builder_layout_display_style_choices( $layout_name );
	$default_value = mrn_base_stack_normalize_builder_layout_display_style( '', $layout_name, '', 'default' );

	return array(
		'key'           => sanitize_key( (string) $key ),
		'label'         => 'Display Style',
		'name'          => 'display_style',
		'aria-label'    => '',
		'type'          => 'select',
		'choices'       => $choices,
		'default_value' => $default_value,
		'allow_null'    => 0,
		'ui'            => 1,
		'wrapper'       => array(
			'width' => '50',
		),
	);
}

/**
 * Ensure a builder layout has a dedicated Display Styles tab.
 *
 * @param array<int, mixed> $fields Layout field definitions.
 * @param string            $layout_name Builder layout name.
 * @param string            $key_seed Optional key seed.
 * @return array<int, mixed>
 */
function mrn_base_stack_ensure_builder_layout_display_style_fields( array $fields, $layout_name, $key_seed = '' ) {
	$layout_name = sanitize_key( (string) $layout_name );

	if ( '' === $layout_name ) {
		return $fields;
	}

	$is_content_lists        = 'content_lists' === $layout_name;
	$mode_choices            = $is_content_lists ? mrn_base_stack_get_content_list_display_mode_choices() : mrn_base_stack_get_builder_layout_display_mode_choices( $layout_name );
	$style_choices           = $is_content_lists ? mrn_base_stack_get_content_list_display_style_choices() : mrn_base_stack_get_builder_layout_display_style_choices( $layout_name );
	$layout_mode_names       = function_exists( 'mrn_base_stack_get_builder_layout_mode_field_names' )
		? mrn_base_stack_get_builder_layout_mode_field_names( $layout_name )
		: array();
	$display_contract_names  = function_exists( 'mrn_base_stack_get_builder_display_styles_contract_field_names' )
		? mrn_base_stack_get_builder_display_styles_contract_field_names( $layout_name )
		: array();
	$display_contract_fields = function_exists( 'mrn_base_stack_get_builder_display_styles_contract_fields' )
		? mrn_base_stack_get_builder_display_styles_contract_fields( $layout_name, '' !== $key_seed ? $key_seed : 'field_mrn_' . $layout_name . '_display' )
		: array();

	if ( empty( $mode_choices ) && empty( $style_choices ) && empty( $display_contract_fields ) ) {
		return $fields;
	}

	$seed = sanitize_key( (string) $key_seed );
	if ( '' === $seed ) {
		$seed = 'field_mrn_' . $layout_name . '_display';
	}

	$display_mode_field  = null;
	$display_style_field = null;
	$remaining_fields    = array();

	foreach ( $fields as $field ) {
		if ( ! is_array( $field ) ) {
			$remaining_fields[] = $field;
			continue;
		}

		$field_type  = isset( $field['type'] ) ? sanitize_key( (string) $field['type'] ) : '';
		$field_name  = isset( $field['name'] ) ? sanitize_key( (string) $field['name'] ) : '';
		$field_label = isset( $field['label'] ) ? sanitize_title( (string) $field['label'] ) : '';

		if ( 'tab' === $field_type && 'display-styles' === $field_label ) {
			continue;
		}

		if ( 'display_mode' === $field_name && 'select' === $field_type ) {
			if ( in_array( $field_name, $layout_mode_names, true ) ) {
				$remaining_fields[] = $field;
				continue;
			}

			$display_mode_field = $field;
			continue;
		}

		if ( 'display_style' === $field_name && 'select' === $field_type ) {
			$display_style_field = $field;
			continue;
		}

		if ( '' !== $field_name && in_array( $field_name, $display_contract_names, true ) ) {
			continue;
		}

		$remaining_fields[] = $field;
	}

	if ( $is_content_lists ) {
		if ( is_array( $display_mode_field ) ) {
			$display_mode_field['label']      = 'Display Mode';
			$display_mode_field['choices']    = $mode_choices;
			$display_mode_field['allow_null'] = 1;
			$display_mode_field['ui']         = 0;
		}
	} elseif ( count( $mode_choices ) > 1 ) {
		if ( ! is_array( $display_mode_field ) ) {
			$display_mode_field = mrn_base_stack_get_builder_layout_display_mode_field( $seed . '_display_mode', $layout_name );
		} else {
			$display_mode_field['label']      = 'Display Mode';
			$display_mode_field['choices']    = $mode_choices;
			$display_mode_field['allow_null'] = 0;
			$display_mode_field['ui']         = 1;
		}
	} else {
		$display_mode_field = null;
	}

	if ( $is_content_lists && is_array( $display_style_field ) ) {
		$display_style_field['label']      = 'Display Style';
		$display_style_field['choices']    = $style_choices;
		$display_style_field['allow_null'] = 1;
		$display_style_field['ui']         = 0;
	} elseif ( ! is_array( $display_style_field ) ) {
		$display_style_field = mrn_base_stack_get_builder_layout_display_style_field( $seed . '_display_style', $layout_name );
	} else {
		$display_style_field['label']      = 'Display Style';
		$display_style_field['choices']    = $style_choices;
		$display_style_field['allow_null'] = 0;
		$display_style_field['ui']         = 1;
	}

	$display_fields = array( mrn_base_stack_get_builder_display_styles_tab_field( $seed . '_display_styles_tab_contract' ) );

	if ( is_array( $display_mode_field ) ) {
		$display_fields[] = $display_mode_field;
	}

	if ( is_array( $display_style_field ) ) {
		$display_fields[] = $display_style_field;
	}

	foreach ( $display_contract_fields as $display_contract_field ) {
		if ( is_array( $display_contract_field ) ) {
			$display_fields[] = $display_contract_field;
		}
	}

	$insert_index = count( $remaining_fields );
	foreach ( $remaining_fields as $index => $field ) {
		if ( ! is_array( $field ) ) {
			continue;
		}

		$field_type  = isset( $field['type'] ) ? sanitize_key( (string) $field['type'] ) : '';
		$field_label = isset( $field['label'] ) ? sanitize_title( (string) $field['label'] ) : '';

		if ( 'tab' === $field_type && in_array( $field_label, array( 'spacing', 'effects' ), true ) ) {
			$insert_index = $index;
			break;
		}
	}

	array_splice( $remaining_fields, $insert_index, 0, $display_fields );

	return array_values( $remaining_fields );
}

/**
 * Ensure the shared top-level Layout tab exists after Spacing and before Effects.
 *
 * @param array<int, mixed> $fields Layout field definitions.
 * @param string            $layout_name Builder layout name.
 * @param string            $key_seed Optional key seed.
 * @return array<int, mixed>
 */
function mrn_base_stack_ensure_builder_layout_tab( array $fields, $layout_name = '', $key_seed = '' ) {
	$layout_name = sanitize_key( (string) $layout_name );
	$seed        = sanitize_key( (string) $key_seed );
	if ( '' === $seed ) {
		$seed = '' !== $layout_name ? 'field_mrn_' . $layout_name : 'field_mrn_layout';
	}

	$remaining_fields       = array();
	$layout_tab             = null;
	$layout_contract_fields = mrn_base_stack_dedupe_builder_layout_contract_fields(
		array_merge(
			mrn_base_stack_get_builder_layout_width_contract_fields( $layout_name, $seed ),
			mrn_base_stack_get_builder_layout_contract_fields( $layout_name, $seed ),
			mrn_base_stack_get_builder_layout_flex_contract_fields( $layout_name, $seed )
		)
	);
	$layout_contract_names  = mrn_base_stack_get_builder_layout_contract_field_names( $layout_name );
	$existing_layout_fields = array();

	foreach ( $fields as $field ) {
		if ( ! is_array( $field ) ) {
			$remaining_fields[] = $field;
			continue;
		}

		$field_type  = isset( $field['type'] ) ? sanitize_key( (string) $field['type'] ) : '';
		$field_label = isset( $field['label'] ) ? sanitize_title( (string) $field['label'] ) : '';
		$field_name  = isset( $field['name'] ) ? sanitize_key( (string) $field['name'] ) : '';

		if ( mrn_base_stack_is_builder_layout_flex_controls_field( $field ) ) {
			if ( 'row_flex_controls' === $field_name && ! isset( $existing_layout_fields[ $field_name ] ) ) {
				$existing_layout_fields[ $field_name ] = $field;
			}
			continue;
		}

		if ( 'tab' === $field_type && 'layout' === $field_label ) {
			if ( null === $layout_tab ) {
				$layout_tab = $field;
			}
			continue;
		}

		if ( '' !== $field_name && in_array( $field_name, $layout_contract_names, true ) ) {
			if ( ! isset( $existing_layout_fields[ $field_name ] ) ) {
				$existing_layout_fields[ $field_name ] = $field;
			}
			continue;
		}

		$remaining_fields[] = $field;
	}

	if ( null === $layout_tab ) {
		$layout_tab = mrn_base_stack_get_builder_layout_tab_field( $seed . '_layout_tab_contract' );
	} else {
		$layout_tab['label']     = 'Layout';
		$layout_tab['type']      = 'tab';
		$layout_tab['placement'] = 'top';
		$layout_tab['endpoint']  = 0;
	}

	if ( ! empty( $layout_contract_fields ) && ! empty( $existing_layout_fields ) ) {
		$merged_layout_contract_fields = array();
		$used_existing_field_names     = array();
		$generated_layout_field_names  = array();

		foreach ( $layout_contract_fields as $layout_contract_field ) {
			if ( ! is_array( $layout_contract_field ) ) {
				$merged_layout_contract_fields[] = $layout_contract_field;
				continue;
			}

			$field_name = isset( $layout_contract_field['name'] ) ? sanitize_key( (string) $layout_contract_field['name'] ) : '';
			if ( '' !== $field_name ) {
				$generated_layout_field_names[] = $field_name;
			}

			if ( '' !== $field_name && isset( $existing_layout_fields[ $field_name ] ) && is_array( $existing_layout_fields[ $field_name ] ) ) {
				$existing_field = $existing_layout_fields[ $field_name ];
				foreach ( array( 'key', '_name', 'parent', 'parent_layout', 'default_value', 'conditional_logic' ) as $preserved_key ) {
					if ( array_key_exists( $preserved_key, $existing_field ) ) {
						$layout_contract_field[ $preserved_key ] = $existing_field[ $preserved_key ];
					}
				}
				$used_existing_field_names[] = $field_name;
			}

			$merged_layout_contract_fields[] = $layout_contract_field;
		}

		foreach ( $existing_layout_fields as $field_name => $existing_field ) {
			if ( ! in_array( $field_name, $generated_layout_field_names, true ) ) {
				continue;
			}

			if ( in_array( $field_name, $used_existing_field_names, true ) ) {
				continue;
			}

			$merged_layout_contract_fields[] = $existing_field;
		}

		$layout_contract_fields = $merged_layout_contract_fields;
	}

	$insert_index = count( $remaining_fields );
	foreach ( $remaining_fields as $index => $field ) {
		if ( ! is_array( $field ) ) {
			continue;
		}

		$field_type  = isset( $field['type'] ) ? sanitize_key( (string) $field['type'] ) : '';
		$field_label = isset( $field['label'] ) ? sanitize_title( (string) $field['label'] ) : '';

		if ( 'tab' === $field_type && 'effects' === $field_label ) {
			$insert_index = (int) $index;
			break;
		}
	}

	array_splice(
		$remaining_fields,
		$insert_index,
		0,
		array_merge( array( $layout_tab ), $layout_contract_fields )
	);

	return array_values( $remaining_fields );
}

/**
 * Ensure shared row-spacing preset controls live in the Configs segment.
 *
 * @param array<int, mixed> $fields Layout/main field definitions.
 * @param string            $key_seed Optional key seed for generated field keys.
 * @return array<int, mixed>
 */
function mrn_base_stack_ensure_row_spacing_preset_field( array $fields, $key_seed = '' ) {
	$seed                     = sanitize_key( (string) $key_seed );
	$content_tab_index        = null;
	$spacing_tab_index        = null;
	$effects_tab_index        = null;
	$has_reusable_group_clone = mrn_base_stack_field_list_has_reusable_group_clone( $fields );

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

		if ( mrn_base_stack_is_row_spacing_selector_field_name( $field_name ) && 'select' === $field_type ) {
			unset( $fields[ $index ] );
			continue;
		}

		if ( 'tab' !== $field_type ) {
			continue;
		}

		if ( null === $content_tab_index && 'content' === $field_label ) {
			$content_tab_index = $index;
			continue;
		}

		if ( null === $spacing_tab_index && 'spacing' === $field_label ) {
			$spacing_tab_index = $index;
			continue;
		}

		if ( null === $effects_tab_index && 'effects' === $field_label ) {
			$effects_tab_index = $index;
		}
	}

	$fields = array_values( $fields );

	if ( '' === $seed ) {
		$seed = 'field_mrn_layout_row_spacing';
	}

	$row_spacing_selector_fields = array();
	foreach ( mrn_base_stack_get_row_spacing_side_selector_definitions() as $definition ) {
		$selector_name  = isset( $definition['name'] ) ? sanitize_key( (string) $definition['name'] ) : '';
		$selector_label = isset( $definition['label'] ) ? sanitize_text_field( (string) $definition['label'] ) : '';
		$scope          = isset( $definition['scope'] ) ? mrn_base_stack_normalize_row_spacing_preset_scope( $definition['scope'] ) : '';

		if ( '' === $selector_name || '' === $selector_label || '' === $scope ) {
			continue;
		}

		$row_spacing_selector_fields[] = mrn_base_stack_get_row_spacing_preset_field(
			$seed . '_' . $selector_name,
			$selector_name,
			$selector_label,
			$scope,
			'',
			'25'
		);
	}

	$content_tab_index = null;
	$spacing_tab_index = null;
	$effects_tab_index = null;

	foreach ( $fields as $index => $field ) {
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
		}

		if ( null === $spacing_tab_index && 'spacing' === $field_label ) {
			$spacing_tab_index = $index;
		}

		if ( null === $effects_tab_index && 'effects' === $field_label ) {
			$effects_tab_index = $index;
		}
	}

	if ( null === $content_tab_index && ! $has_reusable_group_clone ) {
		array_unshift(
			$fields,
			array(
				'key'        => $seed . '_content_tab_contract',
				'label'      => 'Content',
				'name'       => '',
				'aria-label' => '',
				'type'       => 'tab',
				'placement'  => 'top',
				'endpoint'   => 0,
			)
		);
	}

	$spacing_tab = array(
		'key'        => $seed . '_spacing_tab_contract',
		'label'      => 'Spacing',
		'name'       => '',
		'aria-label' => '',
		'type'       => 'tab',
		'placement'  => 'top',
		'endpoint'   => 0,
	);

	$spacing_tab_index = null;
	$effects_tab_index = null;

	foreach ( $fields as $index => $field ) {
		if ( ! is_array( $field ) ) {
			continue;
		}

		$field_type  = isset( $field['type'] ) ? sanitize_key( (string) $field['type'] ) : '';
		$field_label = isset( $field['label'] ) ? sanitize_title( (string) $field['label'] ) : '';

		if ( 'tab' !== $field_type ) {
			continue;
		}

		if ( null === $spacing_tab_index && 'spacing' === $field_label ) {
			$spacing_tab_index = $index;
		}

		if ( null === $effects_tab_index && 'effects' === $field_label ) {
			$effects_tab_index = $index;
		}
	}

	if ( null === $spacing_tab_index ) {
		$insert_index = null !== $effects_tab_index ? $effects_tab_index : count( $fields );
		array_splice( $fields, $insert_index, 0, array( $spacing_tab ) );
		$spacing_tab_index = $insert_index;
	}

	foreach ( $fields as $index => $field ) {
		if ( ! is_array( $field ) ) {
			continue;
		}

		$field_name = isset( $field['name'] ) ? sanitize_key( (string) $field['name'] ) : '';
		$field_type = isset( $field['type'] ) ? sanitize_key( (string) $field['type'] ) : '';

		if ( mrn_base_stack_is_row_spacing_selector_field_name( $field_name ) && 'select' === $field_type ) {
			unset( $fields[ $index ] );
		}
	}
	$fields = array_values( $fields );

	$spacing_tab_index = null;
	foreach ( $fields as $index => $field ) {
		if ( ! is_array( $field ) ) {
			continue;
		}

		$field_type  = isset( $field['type'] ) ? sanitize_key( (string) $field['type'] ) : '';
		$field_label = isset( $field['label'] ) ? sanitize_title( (string) $field['label'] ) : '';
		if ( 'tab' === $field_type && 'spacing' === $field_label ) {
			$spacing_tab_index = $index;
			break;
		}
	}

	if ( null === $spacing_tab_index ) {
		$spacing_tab_index = count( $fields ) - 1;
	}

	if ( ! empty( $row_spacing_selector_fields ) ) {
		array_splice(
			$fields,
			$spacing_tab_index + 1,
			0,
			$row_spacing_selector_fields
		);
	}

	return array_values( $fields );
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
	$fields = mrn_base_stack_ensure_row_spacing_preset_field( $fields, $key_seed );
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
 * @param string            $layout_name Builder layout name.
 * @return array<int, mixed>
 */
function mrn_base_stack_apply_primary_layout_field_contract( array $fields, $inject_internal_name = true, $layout_name = '' ) {
	$layout_name       = sanitize_key( (string) $layout_name );
	$normalized_fields = array();

	foreach ( $fields as $field ) {
		if ( ! is_array( $field ) ) {
			$normalized_fields[] = $field;
			continue;
		}

		$field_type              = isset( $field['type'] ) ? sanitize_key( (string) $field['type'] ) : '';
		$field_name              = isset( $field['name'] ) ? sanitize_key( (string) $field['name'] ) : '';
		$field_key               = isset( $field['key'] ) && is_string( $field['key'] ) ? trim( $field['key'] ) : '';
		$is_reusable_group_clone = mrn_base_stack_field_is_reusable_group_clone( $field );

		if ( isset( $field['sub_fields'] ) && is_array( $field['sub_fields'] ) && ! $is_reusable_group_clone ) {
			$field['sub_fields'] = mrn_base_stack_apply_primary_layout_field_contract( $field['sub_fields'], false, '' );

			if ( 'clone' === $field_type ) {
				$field['sub_fields'] = mrn_base_stack_group_main_config_fields_by_functionality( $field['sub_fields'], $field_key );
			}

			if ( 'repeater' === $field_type && 'links' !== $field_name ) {
				$field['sub_fields'] = mrn_base_stack_ensure_repeater_subheading_contract( $field['sub_fields'], $field_key, $field_name );
				$field['sub_fields'] = mrn_base_stack_apply_repeater_item_tabs_and_config_contract( $field['sub_fields'], $field_name, $field_key );
			}
		}

		if ( isset( $field['fields'] ) && is_array( $field['fields'] ) ) {
			$field['fields'] = mrn_base_stack_apply_primary_layout_field_contract( $field['fields'], false, '' );
		}

		if ( isset( $field['layouts'] ) && is_array( $field['layouts'] ) ) {
			foreach ( $field['layouts'] as $layout_key => $layout ) {
				if ( ! is_array( $layout ) ) {
					continue;
				}

				if ( isset( $layout['sub_fields'] ) && is_array( $layout['sub_fields'] ) ) {
					$nested_layout_name   = isset( $layout['name'] ) ? sanitize_key( (string) $layout['name'] ) : sanitize_key( (string) $layout_key );
					$layout['sub_fields'] = mrn_base_stack_apply_primary_layout_field_contract( $layout['sub_fields'], true, $nested_layout_name );
				}

				$field['layouts'][ $layout_key ] = $layout;
			}
		}

		$normalized_fields[] = mrn_base_stack_normalize_primary_layout_field( $field );
	}

	$normalized_fields = mrn_base_stack_remove_parent_motion_settings_for_full_contract_clone( $normalized_fields );
	$normalized_fields = mrn_base_stack_apply_tag_field_column_layout( $normalized_fields );
	if ( $inject_internal_name ) {
		if ( ! mrn_base_stack_field_list_has_full_contract_reusable_group_clone( $normalized_fields ) ) {
			$normalized_fields = mrn_base_stack_ensure_sub_content_width_field( $normalized_fields, $layout_name );
			$normalized_fields = mrn_base_stack_group_main_config_fields_by_functionality( $normalized_fields );
			$normalized_fields = mrn_base_stack_ensure_builder_layout_display_style_fields( $normalized_fields, $layout_name );
			$normalized_fields = mrn_base_stack_apply_hero_spacing_tab_contract( $normalized_fields, $layout_name );
			$normalized_fields = mrn_base_stack_ensure_builder_layout_tab( $normalized_fields, $layout_name );
			$normalized_fields = mrn_base_stack_apply_hero_layout_tab_contract( $normalized_fields, $layout_name );
		}
	}

	if ( ! $inject_internal_name ) {
		return mrn_base_stack_ensure_acf_field_origin_names( $normalized_fields );
	}

	if ( mrn_base_stack_field_list_has_reusable_group_clone( $normalized_fields ) ) {
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
 * Get flexible-content field names that should receive the shared row contract.
 *
 * @return array<int, string>
 */
function mrn_base_stack_get_primary_builder_flexible_field_names() {
	$field_names = function_exists( 'mrn_base_stack_get_builder_row_flex_supported_fields' )
		? mrn_base_stack_get_builder_row_flex_supported_fields()
		: array(
			'page_content_rows',
			'page_after_content_rows',
			'page_hero_rows',
			'page_sidebar_rows',
		);

	/**
	 * Filter flexible-content field names that should receive shared row contracts
	 * when loaded from ACF UI field groups.
	 *
	 * @param array<int, string> $field_names Supported flexible-content field names.
	 */
	$field_names = apply_filters( 'mrn_base_stack_primary_builder_flexible_field_names', $field_names );

	if ( ! is_array( $field_names ) ) {
		return array();
	}

	$normalized_names = array();
	foreach ( $field_names as $field_name ) {
		$field_name = is_scalar( $field_name ) ? sanitize_key( (string) $field_name ) : '';
		if ( '' === $field_name ) {
			continue;
		}

		$normalized_names[] = $field_name;
	}

	return array_values( array_unique( $normalized_names ) );
}

/**
 * Determine whether a flexible-content field already carries row contracts.
 *
 * @param array<string, mixed> $field ACF field definition.
 * @return bool
 */
function mrn_base_stack_flexible_field_has_primary_layout_contract( array $field ) {
	if ( ! empty( $field['_mrn_base_stack_contract_applied'] ) ) {
		return true;
	}

	if ( ! isset( $field['layouts'] ) || ! is_array( $field['layouts'] ) ) {
		return false;
	}

	$checked_layouts = 0;

	foreach ( $field['layouts'] as $layout ) {
		if ( ! is_array( $layout ) || ! isset( $layout['sub_fields'] ) || ! is_array( $layout['sub_fields'] ) ) {
			continue;
		}

		++$checked_layouts;

		$has_internal_name      = false;
		$has_display_styles_tab = false;
		$has_layout_tab         = false;
		$effects_tab_count      = 0;

		foreach ( $layout['sub_fields'] as $sub_field ) {
			if ( ! is_array( $sub_field ) ) {
				continue;
			}

			$field_name  = isset( $sub_field['name'] ) ? sanitize_key( (string) $sub_field['name'] ) : '';
			$field_type  = isset( $sub_field['type'] ) ? sanitize_key( (string) $sub_field['type'] ) : '';
			$field_label = isset( $sub_field['label'] ) ? sanitize_title( (string) $sub_field['label'] ) : '';

			if ( 'internal_name' === $field_name ) {
				$has_internal_name = true;
			}

			if ( 'tab' === $field_type && 'display-styles' === $field_label ) {
				$has_display_styles_tab = true;
			}

			if ( 'tab' === $field_type && 'layout' === $field_label ) {
				$has_layout_tab = true;
			}

			if ( 'tab' === $field_type && 'effects' === $field_label ) {
				++$effects_tab_count;
			}
		}

		if ( ! $has_internal_name || ! $has_display_styles_tab || ! $has_layout_tab || $effects_tab_count > 1 ) {
			return false;
		}
	}

	return $checked_layouts > 0;
}

/**
 * Determine whether a flexible-content field should receive shared row contracts.
 *
 * @param array<string, mixed> $field ACF field definition.
 * @return bool
 */
function mrn_base_stack_should_apply_primary_layout_contract_to_flexible_field( array $field ) {
	$field_type = isset( $field['type'] ) ? sanitize_key( (string) $field['type'] ) : '';
	if ( 'flexible_content' !== $field_type ) {
		return false;
	}

	$field_name   = isset( $field['name'] ) ? sanitize_key( (string) $field['name'] ) : '';
	$field_names  = mrn_base_stack_get_primary_builder_flexible_field_names();
	$has_layouts  = isset( $field['layouts'] ) && is_array( $field['layouts'] ) && ! empty( $field['layouts'] );
	$should_apply = $has_layouts && '' !== $field_name && in_array( $field_name, $field_names, true );

	if ( $should_apply && mrn_base_stack_flexible_field_has_primary_layout_contract( $field ) ) {
		$should_apply = false;
	}

	/**
	 * Filter whether a flexible-content field should receive shared row contracts.
	 *
	 * @param bool                 $should_apply Default match result.
	 * @param array<string, mixed> $field ACF field definition.
	 */
	return (bool) apply_filters( 'mrn_base_stack_should_apply_contract_to_flexible_field', $should_apply, $field );
}

/**
 * Apply shared row contracts to every layout in a flexible-content field.
 *
 * @param array<string, mixed> $field ACF flexible-content field definition.
 * @return array<string, mixed>
 */
function mrn_base_stack_apply_primary_layout_contract_to_flexible_layouts( array $field ) {
	if ( ! isset( $field['layouts'] ) || ! is_array( $field['layouts'] ) ) {
		return $field;
	}

	foreach ( $field['layouts'] as $layout_key => $layout ) {
		if ( ! is_array( $layout ) || ! isset( $layout['sub_fields'] ) || ! is_array( $layout['sub_fields'] ) ) {
			continue;
		}

		$layout_name                     = isset( $layout['name'] ) ? sanitize_key( (string) $layout['name'] ) : sanitize_key( (string) $layout_key );
		$layout['sub_fields']            = mrn_base_stack_apply_primary_layout_field_contract( $layout['sub_fields'], true, $layout_name );
		$layout['sub_fields']            = mrn_base_stack_relocate_effect_fields( $layout['sub_fields'] );
		$layout['sub_fields']            = mrn_base_stack_apply_primary_layout_field_contract( $layout['sub_fields'], true, $layout_name );
		$layout['sub_fields']            = mrn_base_stack_dedupe_effects_tab_segments( $layout['sub_fields'] );
		$field['layouts'][ $layout_key ] = $layout;
	}

	$field['_mrn_base_stack_contract_applied'] = true;

	return $field;
}

/**
 * Apply shared row contracts to flexible-content fields loaded from ACF UI.
 *
 * This ensures UI-managed field groups receive the same row-level Configs
 * contract (tabs, shared controls, grouped settings) as code-registered groups.
 *
 * @param array<string, mixed>|mixed $field ACF field definition.
 * @return array<string, mixed>|mixed
 */
function mrn_base_stack_apply_primary_layout_contract_on_flexible_load( $field ) {
	if ( ! is_array( $field ) ) {
		return $field;
	}

	if ( ! mrn_base_stack_should_apply_primary_layout_contract_to_flexible_field( $field ) ) {
		return $field;
	}

	if ( ! isset( $field['layouts'] ) || ! is_array( $field['layouts'] ) ) {
		return $field;
	}

	return mrn_base_stack_apply_primary_layout_contract_to_flexible_layouts( $field );
}
add_filter( 'acf/load_field/type=flexible_content', 'mrn_base_stack_apply_primary_layout_contract_on_flexible_load', 30 );
add_filter( 'acf/prepare_field/type=flexible_content', 'mrn_base_stack_apply_primary_layout_contract_on_flexible_load', 30 );

/**
 * Final cleanup for duplicate Effects segments created by expanded clone fields.
 *
 * @param array<string, mixed>|mixed $field ACF field definition.
 * @return array<string, mixed>|mixed
 */
function mrn_base_stack_dedupe_flexible_layout_effects_on_load( $field ) {
	if ( ! is_array( $field ) || ! isset( $field['layouts'] ) || ! is_array( $field['layouts'] ) ) {
		return $field;
	}

	foreach ( $field['layouts'] as $layout_key => $layout ) {
		if ( ! is_array( $layout ) || ! isset( $layout['sub_fields'] ) || ! is_array( $layout['sub_fields'] ) ) {
			continue;
		}

		$layout_name                     = isset( $layout['name'] ) ? sanitize_key( (string) $layout['name'] ) : sanitize_key( (string) $layout_key );
		$layout['sub_fields']            = mrn_base_stack_dedupe_effects_tab_segments( $layout['sub_fields'] );
		$field['layouts'][ $layout_key ] = $layout;
	}

	return $field;
}
add_filter( 'acf/load_field/type=flexible_content', 'mrn_base_stack_dedupe_flexible_layout_effects_on_load', 200 );
add_filter( 'acf/prepare_field/type=flexible_content', 'mrn_base_stack_dedupe_flexible_layout_effects_on_load', 200 );

/**
 * Apply shared row contracts when ACF retrieves field definitions from storage.
 *
 * Some UI-managed field groups hydrate through definition lookups where
 * `acf/load_field` may not carry full layout structures yet. This ensures the
 * same contract is applied as soon as complete flexible layouts are available.
 *
 * @param array<string, mixed>|mixed $field ACF field definition.
 * @return array<string, mixed>|mixed
 */
function mrn_base_stack_apply_primary_layout_contract_on_flexible_get_field( $field ) {
	if ( ! is_array( $field ) ) {
		return $field;
	}

	if ( ! mrn_base_stack_should_apply_primary_layout_contract_to_flexible_field( $field ) ) {
		return $field;
	}

	if ( ! isset( $field['layouts'] ) || ! is_array( $field['layouts'] ) || empty( $field['layouts'] ) ) {
		return $field;
	}

	foreach ( $field['layouts'] as $layout_key => $layout ) {
		if ( ! is_array( $layout ) || ! isset( $layout['sub_fields'] ) || ! is_array( $layout['sub_fields'] ) ) {
			continue;
		}

		$layout_name                     = isset( $layout['name'] ) ? sanitize_key( (string) $layout['name'] ) : sanitize_key( (string) $layout_key );
		$layout['sub_fields']            = mrn_base_stack_apply_primary_layout_field_contract( $layout['sub_fields'], true, $layout_name );
		$layout['sub_fields']            = mrn_base_stack_relocate_effect_fields( $layout['sub_fields'] );
		$layout['sub_fields']            = mrn_base_stack_apply_primary_layout_field_contract( $layout['sub_fields'], true, $layout_name );
		$layout['sub_fields']            = mrn_base_stack_dedupe_effects_tab_segments( $layout['sub_fields'] );
		$field['layouts'][ $layout_key ] = $layout;
	}

	$field['_mrn_base_stack_contract_applied'] = true;

	return $field;
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
 * Keep one top-level Effects tab when cloned reusable fields bring their own copy.
 *
 * @param array<int, mixed> $fields Field definitions.
 * @return array<int, mixed>
 */
function mrn_base_stack_dedupe_effects_tab_segments( array $fields ) {
	$effects_tab_indexes = array();

	foreach ( $fields as $index => $field ) {
		if ( ! is_array( $field ) ) {
			continue;
		}

		$field_type  = isset( $field['type'] ) ? sanitize_key( (string) $field['type'] ) : '';
		$field_label = isset( $field['label'] ) ? sanitize_title( (string) $field['label'] ) : '';

		if ( 'tab' === $field_type && 'effects' === $field_label ) {
			$effects_tab_indexes[] = (int) $index;
		}
	}

	if ( count( $effects_tab_indexes ) < 2 ) {
		return $fields;
	}

	$keep_index   = (int) end( $effects_tab_indexes );
	$deduped      = array();
	$skip_segment = false;

	foreach ( $fields as $index => $field ) {
		if ( ! is_array( $field ) ) {
			if ( ! $skip_segment ) {
				$deduped[] = $field;
			}
			continue;
		}

		$field_type  = isset( $field['type'] ) ? sanitize_key( (string) $field['type'] ) : '';
		$field_label = isset( $field['label'] ) ? sanitize_title( (string) $field['label'] ) : '';

		if ( 'tab' === $field_type ) {
			if ( 'effects' === $field_label && (int) $index !== $keep_index ) {
				$skip_segment = true;
				continue;
			}

			$skip_segment = false;
		}

		if ( $skip_segment ) {
			continue;
		}

		$deduped[] = $field;
	}

	return array_values( $deduped );
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
 * Resolve the site-wide builder row width, retaining a layout fallback when
 * Site Styles is unavailable.
 *
 * @param string $fallback_width Layout fallback width.
 * @return string
 */
function mrn_base_stack_get_default_row_width( $fallback_width = 'wide' ) {
	$fallback_width = mrn_base_stack_normalize_section_width( $fallback_width, 'wide' );
	if ( ! function_exists( 'mrn_site_styles_get_row_width_default' ) ) {
		return $fallback_width;
	}
	return mrn_base_stack_normalize_section_width( mrn_site_styles_get_row_width_default(), $fallback_width );
}

/**
 * Resolve a builder row width through the site-wide default when unset.
 *
 * @param mixed  $value Raw stored row width.
 * @param string $fallback_width Layout fallback width.
 * @return string
 */
function mrn_base_stack_resolve_row_width( $value, $fallback_width = 'wide' ) {
	$width = is_scalar( $value ) ? sanitize_key( (string) $value ) : '';
	if ( '' === $width ) {
		return mrn_base_stack_get_default_row_width( $fallback_width );
	}
	return mrn_base_stack_normalize_section_width( $value, mrn_base_stack_get_default_row_width( $fallback_width ) );
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
	$width                = mrn_base_stack_resolve_row_width( $value, $default_width );
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

	return mrn_base_stack_get_section_width_class( $value, mrn_base_stack_get_default_row_width( $default_width ) );
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
	$layout_name = isset( $row['acf_fc_layout'] ) ? sanitize_key( (string) $row['acf_fc_layout'] ) : '';
	if ( '' === $layout_name || ! mrn_base_stack_layout_allows_sub_content_width( $layout_name ) ) {
		return array(
			'classes'    => array(),
			'attributes' => array(),
		);
	}

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
 * Get the default anchor fallback for a builder row.
 *
 * @param array<string, mixed> $row Builder row data.
 * @return string
 */
function mrn_base_stack_get_builder_row_default_anchor( array $row ) {
	$internal_name = isset( $row['internal_name'] ) ? trim( wp_strip_all_tags( (string) $row['internal_name'] ) ) : '';

	return '' !== $internal_name ? $internal_name : '';
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

	if ( '' === $anchor_id ) {
		$anchor_id = mrn_base_stack_normalize_anchor_id( mrn_base_stack_get_builder_row_default_anchor( $row ) );
	}

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
 * Get supported row-spacing property keys.
 *
 * @return array<int, string>
 */
function mrn_base_stack_get_row_spacing_property_keys() {
	static $properties = null;

	if ( is_array( $properties ) ) {
		return $properties;
	}

	$fallback = array(
		'margin-top',
		'margin-right',
		'margin-bottom',
		'margin-left',
		'padding-top',
		'padding-right',
		'padding-bottom',
		'padding-left',
	);

	if ( ! function_exists( 'mrn_site_styles_get_row_spacing_property_choices' ) ) {
		$properties = $fallback;
		return $properties;
	}

	$choices = mrn_site_styles_get_row_spacing_property_choices();
	if ( ! is_array( $choices ) ) {
		$properties = $fallback;
		return $properties;
	}

	$normalized = array();
	foreach ( array_keys( $choices ) as $property_key ) {
		$property = is_scalar( $property_key ) ? strtolower( trim( (string) $property_key ) ) : '';
		if ( '' === $property || ! preg_match( '/^(margin|padding)\-(top|right|bottom|left)$/', $property ) ) {
			continue;
		}

		$normalized[] = $property;
	}

	if ( empty( $normalized ) ) {
		$normalized = $fallback;
	}

	$properties = array_values( array_unique( $normalized ) );

	return $properties;
}

/**
 * Normalize one row-spacing property key.
 *
 * @param mixed $value Raw property key.
 * @return string
 */
function mrn_base_stack_normalize_row_spacing_property( $value ) {
	$property = is_scalar( $value ) ? strtolower( trim( (string) $value ) ) : '';
	if ( '' === $property ) {
		return '';
	}

	$allowed = mrn_base_stack_get_row_spacing_property_keys();
	if ( ! in_array( $property, $allowed, true ) ) {
		return '';
	}

	return $property;
}

/**
 * Expand alias row-spacing properties to side-specific keys.
 *
 * @param mixed $value Raw property key.
 * @return array<int, string>
 */
function mrn_base_stack_expand_row_spacing_property_to_keys( $value ) {
	$property = is_scalar( $value ) ? strtolower( trim( (string) $value ) ) : '';
	if ( '' === $property ) {
		return array();
	}

	if ( 'margin' === $property ) {
		return array(
			'margin-top',
			'margin-right',
			'margin-bottom',
			'margin-left',
		);
	}

	if ( 'padding' === $property ) {
		return array(
			'padding-top',
			'padding-right',
			'padding-bottom',
			'padding-left',
		);
	}

	$normalized = mrn_base_stack_normalize_row_spacing_property( $property );
	if ( '' === $normalized ) {
		return array();
	}

	return array( $normalized );
}

/**
 * Sanitize CSS spacing dimensions used by row-spacing contracts.
 *
 * @param mixed $value Raw spacing value.
 * @return string
 */
function mrn_base_stack_sanitize_spacing_dimension_value( $value ) {
	if ( function_exists( 'mrn_site_styles_sanitize_spacing_dimension' ) ) {
		return mrn_site_styles_sanitize_spacing_dimension( (string) $value );
	}

	$sanitized = preg_replace( '/[^a-zA-Z0-9.%(),\-+*\/\s]/', '', (string) $value );
	$sanitized = is_string( $sanitized ) ? trim( $sanitized ) : '';
	$sanitized = preg_replace( '/\s+/', ' ', $sanitized );
	$sanitized = is_string( $sanitized ) ? trim( $sanitized ) : '';

	if ( '' === $sanitized ) {
		return '';
	}

	return substr( $sanitized, 0, 64 );
}

/**
 * Check whether a row-spacing property supports shell gutter compensation.
 *
 * Compensation applies to horizontal margins only.
 *
 * @param mixed $property Property key.
 * @return bool
 */
function mrn_base_stack_row_spacing_property_supports_shell_compensation( $property ) {
	$property = mrn_base_stack_normalize_row_spacing_property( $property );

	return in_array( $property, array( 'margin-left', 'margin-right' ), true );
}

/**
 * Normalize a row-spacing compensation flag to boolean.
 *
 * @param mixed $value Raw flag value.
 * @return bool
 */
function mrn_base_stack_normalize_row_spacing_compensation_flag( $value ) {
	if ( is_bool( $value ) ) {
		return $value;
	}

	if ( ! is_scalar( $value ) ) {
		return false;
	}

	$normalized = strtolower( trim( (string) $value ) );

	return in_array( $normalized, array( '1', 'true', 'yes', 'on' ), true );
}

/**
 * Apply shell gutter compensation to one row-spacing value.
 *
 * @param string $value Spacing value.
 * @param string $property Property key.
 * @param bool   $should_compensate Whether compensation should be applied.
 * @return string
 */
function mrn_base_stack_apply_shell_compensation_to_row_spacing_value( $value, $property, $should_compensate ) {
	$value = mrn_base_stack_sanitize_spacing_dimension_value( $value );
	if ( '' === $value || ! $should_compensate ) {
		return $value;
	}

	$property = mrn_base_stack_normalize_row_spacing_property( $property );
	if ( ! mrn_base_stack_row_spacing_property_supports_shell_compensation( $property ) ) {
		return $value;
	}

	if ( 'auto' === strtolower( trim( $value ) ) ) {
		return $value;
	}

	return 'calc(' . $value . ' - var(--mrn-shell-gutter))';
}

/**
 * Normalize row-spacing preset names for matching.
 *
 * @param mixed $value Raw preset name.
 * @return string
 */
function mrn_base_stack_normalize_row_spacing_preset_name( $value ) {
	$name = is_scalar( $value ) ? trim( (string) $value ) : '';
	if ( '' === $name ) {
		return '';
	}

	$name = preg_replace( '/\s+/', ' ', $name );
	$name = is_string( $name ) ? trim( $name ) : '';

	return strtolower( $name );
}

/**
 * Get resolved row-spacing defaults from Site Styles.
 *
 * @return array{desktop:array<string,string>,mobile:array<string,string>,compensation:array<string,bool>}
 */
function mrn_base_stack_get_row_spacing_defaults_resolved_map() {
	$properties = mrn_base_stack_get_row_spacing_property_keys();
	$defaults   = array(
		'desktop'      => array_fill_keys( $properties, '' ),
		'mobile'       => array_fill_keys( $properties, '' ),
		'compensation' => array_fill_keys( $properties, false ),
	);

	if ( ! function_exists( 'mrn_site_styles_get_row_spacing_defaults_resolved' ) ) {
		return $defaults;
	}

	$configured_defaults = mrn_site_styles_get_row_spacing_defaults_resolved();
	if ( ! is_array( $configured_defaults ) ) {
		return $defaults;
	}

	foreach ( $configured_defaults as $property_key => $values ) {
		$target_properties = mrn_base_stack_expand_row_spacing_property_to_keys( $property_key );
		if ( empty( $target_properties ) || ! is_array( $values ) ) {
			continue;
		}

		$desktop          = mrn_base_stack_sanitize_spacing_dimension_value( $values['desktop'] ?? '' );
		$mobile           = mrn_base_stack_sanitize_spacing_dimension_value( $values['mobile'] ?? '' );
		$compensate_shell = mrn_base_stack_normalize_row_spacing_compensation_flag( $values['compensate_shell'] ?? false );

		foreach ( $target_properties as $property ) {
			$defaults['desktop'][ $property ]      = $desktop;
			$defaults['mobile'][ $property ]       = $mobile;
			$defaults['compensation'][ $property ] = $compensate_shell;
		}
	}

	return $defaults;
}

/**
 * Get row-spacing overrides for one selected preset.
 *
 * @param string $preset_name Preset name saved on the builder row.
 * @param string $scope Optional selector scope (`margin`, `padding`, or empty for all).
 * @return array{desktop:array<string,string>,mobile:array<string,string>,compensation:array<string,bool>}
 */
function mrn_base_stack_get_row_spacing_overrides_for_preset( $preset_name, $scope = '' ) {
	$scope           = mrn_base_stack_normalize_row_spacing_preset_scope( $scope );
	$normalized_name = mrn_base_stack_normalize_row_spacing_preset_name( $preset_name );
	$scope_is_side   = (bool) preg_match( '/^(margin|padding)\-(top|right|bottom|left)$/', $scope );
	$overrides       = array(
		'desktop'      => array(),
		'mobile'       => array(),
		'compensation' => array(),
	);

	if ( '' === $normalized_name || ! function_exists( 'mrn_site_styles_get_row_spacing_presets_resolved' ) ) {
		return $overrides;
	}

	$preset_rows = mrn_site_styles_get_row_spacing_presets_resolved();
	if ( ! is_array( $preset_rows ) ) {
		return $overrides;
	}

	foreach ( $preset_rows as $preset_row ) {
		if ( ! is_array( $preset_row ) ) {
			continue;
		}

		$row_name = mrn_base_stack_normalize_row_spacing_preset_name( $preset_row['name'] ?? '' );
		if ( '' === $row_name || $row_name !== $normalized_name ) {
			continue;
		}

		if ( ! mrn_base_stack_row_spacing_property_matches_scope( $preset_row['property'] ?? '', $scope ) ) {
			continue;
		}

		$target_properties = mrn_base_stack_expand_row_spacing_property_to_keys( $preset_row['property'] ?? '' );
		if ( $scope_is_side ) {
			$target_properties = in_array( $scope, $target_properties, true ) ? array( $scope ) : array();
		}
		if ( empty( $target_properties ) ) {
			continue;
		}

			$desktop          = mrn_base_stack_sanitize_spacing_dimension_value( $preset_row['desktop'] ?? '' );
			$mobile           = mrn_base_stack_sanitize_spacing_dimension_value( $preset_row['mobile'] ?? '' );
			$compensate_shell = mrn_base_stack_normalize_row_spacing_compensation_flag( $preset_row['compensate_shell'] ?? false );

		foreach ( $target_properties as $property ) {
			if ( '' !== $desktop ) {
				$overrides['desktop'][ $property ] = $desktop;
			}

			if ( '' !== $mobile ) {
				$overrides['mobile'][ $property ] = $mobile;
			}

			$overrides['compensation'][ $property ] = $compensate_shell;
		}
	}

	return $overrides;
}

/**
 * Resolve row-spacing values for one selected preset.
 *
 * @param string $preset_name Preset name saved on the builder row.
 * @param string $scope Optional selector scope (`margin`, `padding`, or empty for all).
 * @return array{desktop:array<string,string>,mobile:array<string,string>,compensation:array<string,bool>}
 */
function mrn_base_stack_get_row_spacing_values_for_preset( $preset_name, $scope = '' ) {
	$values    = mrn_base_stack_get_row_spacing_defaults_resolved_map();
	$overrides = mrn_base_stack_get_row_spacing_overrides_for_preset( $preset_name, $scope );

	foreach ( array( 'desktop', 'mobile' ) as $device_key ) {
		if ( ! isset( $overrides[ $device_key ] ) || ! is_array( $overrides[ $device_key ] ) ) {
			continue;
		}

		foreach ( $overrides[ $device_key ] as $property => $value ) {
			$property = mrn_base_stack_normalize_row_spacing_property( $property );
			$value    = mrn_base_stack_sanitize_spacing_dimension_value( $value );

			if ( '' === $property || '' === $value ) {
				continue;
			}

			$values[ $device_key ][ $property ] = $value;
		}
	}

	if ( isset( $overrides['compensation'] ) && is_array( $overrides['compensation'] ) ) {
		foreach ( $overrides['compensation'] as $property => $value ) {
			$property = mrn_base_stack_normalize_row_spacing_property( $property );
			if ( '' === $property ) {
				continue;
			}

			$values['compensation'][ $property ] = mrn_base_stack_normalize_row_spacing_compensation_flag( $value );
		}
	}

	return $values;
}

/**
 * Build the row-spacing contract for one builder row.
 *
 * @param array<string, mixed> $row Builder row data.
 * @return array{classes:array<int,string>,attributes:array<string,string>}
 */
function mrn_base_stack_get_builder_row_spacing_contract( array $row ) {
	$preset_name         = isset( $row['row_spacing_preset'] ) && is_scalar( $row['row_spacing_preset'] )
		? trim( (string) $row['row_spacing_preset'] )
		: '';
	$margin_preset_name  = isset( $row['row_spacing_margin_preset'] ) && is_scalar( $row['row_spacing_margin_preset'] )
		? trim( (string) $row['row_spacing_margin_preset'] )
		: '';
	$padding_preset_name = isset( $row['row_spacing_padding_preset'] ) && is_scalar( $row['row_spacing_padding_preset'] )
		? trim( (string) $row['row_spacing_padding_preset'] )
		: '';
	$side_preset_names   = array();

	foreach ( mrn_base_stack_get_row_spacing_side_selector_definitions() as $definition ) {
		$selector_name = isset( $definition['name'] ) ? sanitize_key( (string) $definition['name'] ) : '';
		$scope         = isset( $definition['scope'] ) ? mrn_base_stack_normalize_row_spacing_preset_scope( $definition['scope'] ) : '';
		if ( '' === $selector_name || '' === $scope ) {
			continue;
		}

		$side_preset_names[ $scope ] = isset( $row[ $selector_name ] ) && is_scalar( $row[ $selector_name ] )
			? trim( (string) $row[ $selector_name ] )
			: '';
	}

	$resolved_values = '' !== $preset_name
		? mrn_base_stack_get_row_spacing_values_for_preset( $preset_name )
		: mrn_base_stack_get_row_spacing_defaults_resolved_map();

	if ( '' !== $margin_preset_name ) {
		$margin_overrides = mrn_base_stack_get_row_spacing_overrides_for_preset( $margin_preset_name, 'margin' );
		foreach ( array( 'desktop', 'mobile' ) as $device_key ) {
			if ( ! isset( $margin_overrides[ $device_key ] ) || ! is_array( $margin_overrides[ $device_key ] ) ) {
				continue;
			}

			foreach ( $margin_overrides[ $device_key ] as $property => $value ) {
				$property = mrn_base_stack_normalize_row_spacing_property( $property );
				$value    = mrn_base_stack_sanitize_spacing_dimension_value( $value );
				if ( '' === $property || '' === $value ) {
					continue;
				}

				$resolved_values[ $device_key ][ $property ] = $value;
			}
		}

		if ( isset( $margin_overrides['compensation'] ) && is_array( $margin_overrides['compensation'] ) ) {
			foreach ( $margin_overrides['compensation'] as $property => $value ) {
				$property = mrn_base_stack_normalize_row_spacing_property( $property );
				if ( '' === $property ) {
					continue;
				}

				$resolved_values['compensation'][ $property ] = mrn_base_stack_normalize_row_spacing_compensation_flag( $value );
			}
		}
	}

	if ( '' !== $padding_preset_name ) {
		$padding_overrides = mrn_base_stack_get_row_spacing_overrides_for_preset( $padding_preset_name, 'padding' );
		foreach ( array( 'desktop', 'mobile' ) as $device_key ) {
			if ( ! isset( $padding_overrides[ $device_key ] ) || ! is_array( $padding_overrides[ $device_key ] ) ) {
				continue;
			}

			foreach ( $padding_overrides[ $device_key ] as $property => $value ) {
				$property = mrn_base_stack_normalize_row_spacing_property( $property );
				$value    = mrn_base_stack_sanitize_spacing_dimension_value( $value );
				if ( '' === $property || '' === $value ) {
					continue;
				}

				$resolved_values[ $device_key ][ $property ] = $value;
			}
		}

		if ( isset( $padding_overrides['compensation'] ) && is_array( $padding_overrides['compensation'] ) ) {
			foreach ( $padding_overrides['compensation'] as $property => $value ) {
				$property = mrn_base_stack_normalize_row_spacing_property( $property );
				if ( '' === $property ) {
					continue;
				}

				$resolved_values['compensation'][ $property ] = mrn_base_stack_normalize_row_spacing_compensation_flag( $value );
			}
		}
	}

	foreach ( $side_preset_names as $scope => $side_preset_name ) {
		if ( '' === $side_preset_name ) {
			continue;
		}

		$side_overrides = mrn_base_stack_get_row_spacing_overrides_for_preset( $side_preset_name, $scope );
		foreach ( array( 'desktop', 'mobile' ) as $device_key ) {
			if ( ! isset( $side_overrides[ $device_key ] ) || ! is_array( $side_overrides[ $device_key ] ) ) {
				continue;
			}

			foreach ( $side_overrides[ $device_key ] as $property => $value ) {
				$property = mrn_base_stack_normalize_row_spacing_property( $property );
				$value    = mrn_base_stack_sanitize_spacing_dimension_value( $value );
				if ( '' === $property || '' === $value ) {
					continue;
				}

				$resolved_values[ $device_key ][ $property ] = $value;
			}
		}

		if ( isset( $side_overrides['compensation'] ) && is_array( $side_overrides['compensation'] ) ) {
			foreach ( $side_overrides['compensation'] as $property => $value ) {
				$property = mrn_base_stack_normalize_row_spacing_property( $property );
				if ( '' === $property ) {
					continue;
				}

				$resolved_values['compensation'][ $property ] = mrn_base_stack_normalize_row_spacing_compensation_flag( $value );
			}
		}
	}

	$active_selector_label = $preset_name;
	if ( '' === $active_selector_label ) {
		$active_selector_parts = array();
		if ( '' !== $margin_preset_name ) {
			$active_selector_parts[] = 'margin-' . sanitize_title( $margin_preset_name );
		}
		if ( '' !== $padding_preset_name ) {
			$active_selector_parts[] = 'padding-' . sanitize_title( $padding_preset_name );
		}
		foreach ( $side_preset_names as $scope => $side_preset_name ) {
			if ( '' === $side_preset_name ) {
				continue;
			}

			$active_selector_parts[] = sanitize_title( $scope ) . '-' . sanitize_title( $side_preset_name );
		}
		$active_selector_label = implode( '_', $active_selector_parts );
	}

	$properties  = mrn_base_stack_get_row_spacing_property_keys();
	$styles      = array();
	$has_spacing = false;

	foreach ( $properties as $property ) {
			$desktop           = isset( $resolved_values['desktop'][ $property ] ) ? mrn_base_stack_sanitize_spacing_dimension_value( $resolved_values['desktop'][ $property ] ) : '';
			$mobile            = isset( $resolved_values['mobile'][ $property ] ) ? mrn_base_stack_sanitize_spacing_dimension_value( $resolved_values['mobile'][ $property ] ) : '';
			$should_compensate = isset( $resolved_values['compensation'][ $property ] ) && mrn_base_stack_normalize_row_spacing_compensation_flag( $resolved_values['compensation'][ $property ] );
			$desktop           = mrn_base_stack_apply_shell_compensation_to_row_spacing_value( $desktop, $property, $should_compensate );
			$mobile            = mrn_base_stack_apply_shell_compensation_to_row_spacing_value( $mobile, $property, $should_compensate );

		if ( '' !== $desktop ) {
			$styles[]    = '--mrn-row-' . $property . '-desktop: ' . $desktop;
			$has_spacing = true;
		}

		if ( '' !== $mobile ) {
			$styles[]    = '--mrn-row-' . $property . '-mobile: ' . $mobile;
			$has_spacing = true;
		}
	}

	if ( ! $has_spacing ) {
		return array(
			'classes'    => array(),
			'attributes' => array(),
		);
	}

	$attributes = array(
		'data-mrn-row-spacing' => '' !== $active_selector_label ? sanitize_title( $active_selector_label ) : 'defaults',
	);
	$style      = mrn_base_stack_get_inline_style_attribute( $styles );
	if ( '' !== $style ) {
		$attributes['style'] = $style;
	}

	$contract = array(
		'classes'    => array(),
		'attributes' => $attributes,
	);

	/**
	 * Filter the row-spacing frontend contract before it is merged on the row wrapper.
	 *
	 * @param array{classes:array<int,string>,attributes:array<string,string>} $contract Row spacing contract.
	 * @param array{desktop:array<string,string>,mobile:array<string,string>,compensation:array<string,bool>} $resolved_values Resolved desktop/mobile values.
	 * @param string                                                            $preset_name Selected preset name.
	 * @param array<string,mixed>                                               $row Builder row payload.
	 */
	$contract = apply_filters( 'mrn_base_stack_builder_row_spacing_contract', $contract, $resolved_values, $preset_name, $row );

	return is_array( $contract ) ? $contract : array(
		'classes'    => array(),
		'attributes' => array(),
	);
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
		'left_column_rows',
		'right_column_rows',
		'panel_rows',
		'card_rows',
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
	$row_spacing_contract = mrn_base_stack_get_builder_row_spacing_contract( $row );

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

	if ( ! empty( $row_spacing_contract['classes'] ) && is_array( $row_spacing_contract['classes'] ) ) {
		$motion_contract['classes'] = array_values(
			array_unique(
				array_filter(
					array_merge(
						isset( $motion_contract['classes'] ) && is_array( $motion_contract['classes'] ) ? $motion_contract['classes'] : array(),
						$row_spacing_contract['classes']
					),
					'strlen'
				)
			)
		);
	}

	if ( ! empty( $row_spacing_contract['attributes'] ) && is_array( $row_spacing_contract['attributes'] ) ) {
		$motion_contract['attributes'] = mrn_base_stack_merge_builder_attributes(
			isset( $motion_contract['attributes'] ) && is_array( $motion_contract['attributes'] ) ? $motion_contract['attributes'] : array(),
			$row_spacing_contract['attributes']
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
			'return_format' => 'id',
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
			'multiple'      => 0,
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
	$has_explicit_icon_source = array_key_exists( 'link_icon_source', $row );
	$icon_source              = $has_explicit_icon_source ? sanitize_key( (string) $row['link_icon_source'] ) : '';
	$media_icon               = $row['link_icon_media_icon'] ?? null;
	$media_id                 = function_exists( 'mrn_base_stack_get_image_attachment_id' ) ? mrn_base_stack_get_image_attachment_id( $media_icon ) : 0;
	$fa_class                 = isset( $row['link_icon_fa_class'] ) ? trim( (string) $row['link_icon_fa_class'] ) : '';
	$dashicon                 = mrn_base_stack_normalize_link_dashicon_class( isset( $row['link_icon_dashicon'] ) ? (string) $row['link_icon_dashicon'] : '' );

	if ( $has_explicit_icon_source ) {
		if ( 'media' === $icon_source && $media_id > 0 ) {
			return 'media';
		}

		if ( 'fontawesome' === $icon_source && '' !== $fa_class ) {
			return 'fontawesome';
		}

		if ( 'dashicons' === $icon_source && '' !== $dashicon ) {
			return 'dashicons';
		}

		return '';
	}

	if ( $media_id > 0 ) {
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
		$dashicon = sanitize_html_class( strtolower( (string) $matches[0] ) );
		return mrn_base_stack_link_dashicon_exists( $dashicon ) ? $dashicon : '';
	}

	$dashicon = strtolower( sanitize_html_class( $dashicon_raw ) );

	if ( '' === $dashicon || 'dashicons' === $dashicon ) {
		return '';
	}

	if ( 0 === strpos( $dashicon, 'dashicons-' ) ) {
		return strlen( $dashicon ) > strlen( 'dashicons-' ) && 'dashicons-dashicons' !== $dashicon && mrn_base_stack_link_dashicon_exists( $dashicon ) ? $dashicon : '';
	}

	$dashicon = 'dashicons-' . $dashicon;

	return 'dashicons-dashicons' === $dashicon || ! mrn_base_stack_link_dashicon_exists( $dashicon ) ? '' : $dashicon;
}

/**
 * Determine whether a normalized Dashicon class exists in WordPress core.
 *
 * @param string $dashicon Normalized Dashicon class.
 * @return bool
 */
function mrn_base_stack_link_dashicon_exists( $dashicon ) {
	$dashicon = strtolower( sanitize_html_class( (string) $dashicon ) );

	if ( '' === $dashicon || 0 !== strpos( $dashicon, 'dashicons-' ) ) {
		return false;
	}

	if ( ! function_exists( 'mrn_base_stack_get_dashicons' ) ) {
		return true;
	}

	$icons = mrn_base_stack_get_dashicons();
	if ( empty( $icons ) || ! is_array( $icons ) ) {
		return true;
	}

	return in_array( substr( $dashicon, strlen( 'dashicons-' ) ), $icons, true );
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

	$media_icon = $row['link_icon_media_icon'] ?? null;
	$media_id   = function_exists( 'mrn_base_stack_get_image_attachment_id' ) ? mrn_base_stack_get_image_attachment_id( $media_icon ) : 0;

	if ( $media_id > 0 ) {
		$image_markup = function_exists( 'mrn_base_stack_get_attachment_image' ) ? mrn_base_stack_get_attachment_image(
			$media_id,
			'mrn-icon',
			array(
				'class'       => 'mrn-ui__link-icon-image',
				'alt'         => '',
				'aria-hidden' => 'true',
			)
		) : '';

		if ( '' !== $image_markup ) {
			return '<span class="mrn-ui__link-icon mrn-ui__link-icon--' . esc_attr( $position ) . ' mrn-ui__link-icon--media" aria-hidden="true"' . $style_attr . '>' . $image_markup . '</span>';
		}
	}

	return '';
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
 * Resolve manually selected post IDs for content-list rows.
 *
 * @param array<string, mixed> $row Content-list row settings.
 * @param string               $target_post_type Queried post type.
 * @return array<int, int>
 */
function mrn_base_stack_get_content_list_manual_post_ids( array $row, $target_post_type = '' ) {
	$selected = isset( $row['filter_posts'] ) ? $row['filter_posts'] : array();
	$post_ids = array();

	if ( $selected instanceof WP_Post ) {
		$selected = array( $selected );
	} elseif ( is_scalar( $selected ) ) {
		$selected = array( $selected );
	} elseif ( ! is_array( $selected ) ) {
		$selected = array();
	}

	foreach ( $selected as $item ) {
		if ( $item instanceof WP_Post ) {
			$post_ids[] = absint( $item->ID );
			continue;
		}

		if ( is_array( $item ) && isset( $item['ID'] ) ) {
			$post_ids[] = absint( $item['ID'] );
			continue;
		}

		$post_ids[] = absint( $item );
	}

	$post_ids = array_values(
		array_filter(
			array_unique( array_map( 'absint', $post_ids ) )
		)
	);

	if ( empty( $post_ids ) ) {
		return array();
	}

	$target_post_type = sanitize_key( (string) $target_post_type );
	if ( '' === $target_post_type ) {
		return $post_ids;
	}

	return array_values(
		array_filter(
			$post_ids,
			static function ( $post_id ) use ( $target_post_type ) {
				$post = get_post( absint( $post_id ) );
				if ( ! $post instanceof WP_Post ) {
					return false;
				}

				if ( 'publish' !== $post->post_status ) {
					return false;
				}

				return sanitize_key( (string) $post->post_type ) === $target_post_type;
			}
		)
	);
}

if ( ! function_exists( 'mrn_base_stack_get_background_image_style' ) ) {
	/**
	 * Build a CSS custom-property declaration for a selected background image.
	 *
	 * @param mixed  $image ACF image field value.
	 * @param string $css_var CSS custom property name.
	 * @return string
	 */
	function mrn_base_stack_get_background_image_style( $image, $css_var ) {
		$image_url = function_exists( 'mrn_base_stack_get_attachment_image_url' ) ? mrn_base_stack_get_attachment_image_url( $image, 'mrn-background' ) : '';
		$css_var   = trim( (string) $css_var );

		if ( '' === $image_url || '' === $css_var ) {
			return '';
		}

		return $css_var . ": url('" . esc_url_raw( $image_url ) . "')";
	}
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
			'label'      => 'Text - label|heading|subheading|rich text',
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
				mrn_base_stack_get_inline_text_field( 'field_mrn_nested_body_text_label', 'Label', 'label' ),
				mrn_base_stack_get_label_tag_field( 'field_mrn_nested_body_text_label_tag' ),
				mrn_base_stack_get_inline_text_field( 'field_mrn_nested_body_text_heading', 'Heading', 'heading' ),
				mrn_base_stack_get_text_tag_field( 'field_mrn_nested_body_text_heading_tag', 'heading_tag', 'h2', 'Heading Tag' ),
				mrn_base_stack_get_inline_text_field( 'field_mrn_nested_body_text_subheading', 'Subheading', 'subheading' ),
				mrn_base_stack_get_text_tag_field( 'field_mrn_nested_body_text_subheading_tag', 'subheading_tag', 'p', 'Subheading Tag' ),
				array(
					'key'          => 'field_mrn_nested_body_text_content',
					'label'        => 'Text area with editor',
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
					'return_format' => 'id',
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
					'return_format' => 'id',
					'preview_size'  => 'medium',
					'library'       => 'all',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				...mrn_base_stack_get_background_video_fields( 'field_mrn_nested_basic_background_video' ),
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
							'return_format' => 'id',
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
			'label'      => 'Page Specific CTA',
			'display'    => 'block',
			'sub_fields' => array(
				array(
					'key'          => 'field_mrn_nested_cta_fields',
					'label'        => 'Page Specific CTA',
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
			'label'      => 'Image Content',
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
					'return_format' => 'id',
					'preview_size'  => 'medium',
					'library'       => 'all',
				),
				...mrn_base_stack_get_image_caption_fields( 'field_mrn_nested_image_content_image_caption' ),
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
					'multiple'      => 0,
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
					'multiple'      => 0,
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
					'allow_null'    => 0,
					'multiple'      => 0,
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
					'allow_null'    => 0,
					'multiple'      => 0,
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
					'allow_null'    => 0,
					'multiple'      => 0,
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
			'label'      => 'Page Specific Logos/Partners',
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
							'return_format' => 'id',
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
					'label'         => 'Layout Mode',
					'name'          => 'display_mode',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => mrn_base_stack_get_builder_layout_mode_choices( 'logos' ),
					'default_value' => 'grid',
					'ui'            => 1,
					'instructions'  => 'Choose whether logos render as a grid or a slider. Visual treatments belong in Display Styles.',
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
					'key'          => 'field_mrn_nested_external_widget_title',
					'label'        => 'Embed Title',
					'name'         => 'embed_title',
					'aria-label'   => '',
					'type'         => 'text',
					'instructions' => 'Used as the iframe title when the pasted embed does not include one.',
				),
				array(
					'key'          => 'field_mrn_nested_external_widget_code',
					'label'        => 'Snippet/Code',
					'name'         => 'embed_code',
					'aria-label'   => '',
					'type'         => 'textarea',
					'rows'         => 8,
					'instructions' => 'Paste a trusted iframe/embed/object snippet or shortcode. Script tags are not rendered.',
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
				array(
					'key'        => 'field_mrn_nested_reusable_block_config_tab',
					'label'      => 'Configs',
					'name'       => '',
					'aria-label' => '',
					'type'       => 'tab',
					'placement'  => 'top',
					'endpoint'   => 0,
				),
				mrn_base_stack_get_anchor_field( 'field_mrn_nested_reusable_block_anchor' ),
				array(
					'key'           => 'field_mrn_nested_reusable_block_include_in_faq_jump_nav',
					'label'         => 'Include FAQ Block in Jump Nav',
					'name'          => 'include_in_faq_jump_nav',
					'aria-label'    => '',
					'type'          => 'true_false',
					'instructions'  => 'Only used when the selected reusable block is a FAQ/Accordion. FAQ Jump Nav Label is required. Placement Anchor ID is optional and overrides the label-generated target.',
					'ui'            => 1,
					'default_value' => 0,
					'ui_on_text'    => 'Include',
					'ui_off_text'   => 'Omit',
					'wrapper'       => array(
						'width' => '33',
					),
				),
				array(
					'key'          => 'field_mrn_nested_reusable_block_faq_jump_nav_label',
					'label'        => 'FAQ Jump Nav Label',
					'name'         => 'faq_jump_nav_label',
					'aria-label'   => '',
					'type'         => 'text',
					'instructions' => 'Required when this FAQ placement should appear in a page FAQ Jump Nav. If Placement Anchor ID is blank, this label also generates the jump target.',
					'wrapper'      => array(
						'width' => '100',
					),
				),
				mrn_base_stack_get_motion_group_field( 'field_mrn_nested_reusable_block_motion_settings' ),
			),
		),
		'layout_mrn_nested_faq_jump_nav' => array(
			'key'        => 'layout_mrn_nested_faq_jump_nav',
			'name'       => 'faq_jump_nav',
			'label'      => 'FAQ Jump Nav',
			'display'    => 'block',
			'sub_fields' => array(
				array(
					'key'        => 'field_mrn_nested_faq_jump_nav_content_tab',
					'label'      => 'Content',
					'name'       => '',
					'aria-label' => '',
					'type'       => 'tab',
					'placement'  => 'top',
				),
				mrn_base_stack_get_inline_text_field( 'field_mrn_nested_faq_jump_nav_label', 'Label', 'label' ),
				mrn_base_stack_get_label_tag_field( 'field_mrn_nested_faq_jump_nav_label_tag' ),
				mrn_base_stack_get_inline_text_field( 'field_mrn_nested_faq_jump_nav_heading', 'Heading', 'heading' ),
				mrn_base_stack_get_text_tag_field( 'field_mrn_nested_faq_jump_nav_heading_tag', 'heading_tag', 'h2', 'Heading Tag' ),
				array(
					'key'        => 'field_mrn_nested_faq_jump_nav_config_tab',
					'label'      => 'Configs',
					'name'       => '',
					'aria-label' => '',
					'type'       => 'tab',
					'placement'  => 'top',
					'endpoint'   => 0,
				),
				mrn_base_stack_get_section_width_field( 'field_mrn_nested_faq_jump_nav_section_width', 'section_width', 'content' ),
				mrn_base_stack_get_anchor_field( 'field_mrn_nested_faq_jump_nav_anchor' ),
				mrn_base_stack_get_motion_group_field( 'field_mrn_nested_faq_jump_nav_motion_settings' ),
			),
		),
	);
}

/**
 * Nested layouts offered to authors inside Two Column Split columns.
 *
 * `mrn_base_stack_get_two_column_nested_layouts()` remains the complete
 * compatibility catalog. This helper is the authoring catalog for new column
 * rows, keeping split columns from becoming full recursive builders.
 *
 * @return array<string, array<string, mixed>>
 */
function mrn_base_stack_get_two_column_column_layouts() {
	$allowed_names       = mrn_base_stack_get_two_column_column_layout_source_names();
	$existing_only_names = array();
	$post_id             = function_exists( 'mrn_base_stack_get_builder_layout_allowlist_post_id' ) ? mrn_base_stack_get_builder_layout_allowlist_post_id() : 0;
	$allowlist_active    = $post_id > 0
		&& function_exists( 'mrn_base_stack_is_builder_layout_allowlist_context' )
		&& mrn_base_stack_is_builder_layout_allowlist_context( $post_id );

	if ( $allowlist_active ) {
		$base_allowed_lookup = ! empty( $allowed_names ) ? array_fill_keys( $allowed_names, true ) : array();
		$used_nested_names   = mrn_base_stack_get_two_column_used_nested_layout_names( $post_id );
		$existing_only_names = array_values(
			array_diff(
				array_filter(
					array_map( 'sanitize_key', $used_nested_names )
				),
				array_keys( $base_allowed_lookup )
			)
		);
		$allowed_names       = array_values(
			array_unique(
				array_merge(
					$allowed_names,
					$used_nested_names
				)
			)
		);
	}

	$allowed_lookup       = array_fill_keys( $allowed_names, true );
	$existing_only_lookup = ! empty( $existing_only_names ) ? array_fill_keys( $existing_only_names, true ) : array();
	$layouts              = array();

	foreach ( mrn_base_stack_get_two_column_nested_layouts() as $layout_key => $layout ) {
		if ( ! is_array( $layout ) ) {
			continue;
		}

		$layout_name = isset( $layout['name'] ) ? sanitize_key( (string) $layout['name'] ) : '';
		if ( '' === $layout_name || ! isset( $allowed_lookup[ $layout_name ] ) ) {
			continue;
		}

		if ( isset( $existing_only_lookup[ $layout_name ] ) ) {
			$layout['max'] = -1;
		}

		$layouts[ $layout_key ] = $layout;
	}

	return $layouts;
}
