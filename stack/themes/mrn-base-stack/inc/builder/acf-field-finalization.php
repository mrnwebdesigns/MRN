<?php
/**
 * Finalize ACF builder field trees after MRN runtime transformations.
 *
 * @package mrn-base-stack
 */

/**
 * Recursively normalize required ACF runtime keys on a full field tree.
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

	// Flexible-content formatting indexes returned values by the original name.
	if ( isset( $field['name'] ) && is_string( $field['name'] ) && '' !== trim( $field['name'] ) && ! isset( $field['_name'] ) ) {
		$field['_name'] = $field['name'];
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

/**
 * Ensure an individual select field includes the ACF runtime defaults.
 *
 * @param array<string, mixed> $field ACF field definition.
 * @return array<string, mixed>
 */
function mrn_base_stack_normalize_select_field_defaults( $field ) {
	if ( ! is_array( $field ) ) {
		return $field;
	}

	return mrn_base_stack_normalize_select_defaults_in_field_tree( $field );
}
add_filter( 'acf/validate_field/type=select', 'mrn_base_stack_normalize_select_field_defaults', 20 );
add_filter( 'acf/load_field/type=select', 'mrn_base_stack_normalize_select_field_defaults', 20 );
add_filter( 'acf/prepare_field/type=select', 'mrn_base_stack_normalize_select_field_defaults', 20 );

/**
 * Finalize a completed builder field tree after nested contracts are injected.
 *
 * MRN adds fields to flexible-content and repeater trees after ACF has already
 * run type-specific select filters. Normalizing the completed tree prevents
 * those late fields from reaching ACF's value loaders without required keys.
 *
 * @param array<string, mixed>|mixed $field ACF field definition.
 * @return array<string, mixed>|mixed
 */
function mrn_base_stack_finalize_acf_builder_field_tree( $field ) {
	if ( ! is_array( $field ) ) {
		return $field;
	}

	return mrn_base_stack_normalize_select_defaults_in_field_tree( $field );
}

/**
 * Finalize layouts produced by MRN's runtime clone factories before caching.
 *
 * ACF can register cloned layout sub-fields independently of their containing
 * flexible-content field. Finalizing at the clone factory keeps those stored
 * definitions complete before ACF creates value-loading field instances.
 *
 * @param array<string|int, mixed> $layouts Cloned ACF layout definitions.
 * @return array<string|int, mixed>
 */
function mrn_base_stack_finalize_cloned_acf_layouts( array $layouts ) {
	foreach ( $layouts as $layout_key => $layout ) {
		if ( ! is_array( $layout ) ) {
			continue;
		}

		$layouts[ $layout_key ] = mrn_base_stack_normalize_select_defaults_in_field_tree( $layout );
	}

	return $layouts;
}

/**
 * Register cloned layout sub-fields in ACF's local field store.
 *
 * ACF's AJAX-backed field handlers receive only a field key. Runtime-cloned
 * layouts are available to the editor as nested arrays, but their derived
 * sub-field keys are not otherwise present in ACF's local store. Mirroring
 * ACF's flexible-content import preparation here makes those keys resolvable
 * without changing field names, stored values, or the returned layout tree.
 *
 * @param array<string|int, mixed> $layouts Cloned ACF layout definitions.
 * @param string                   $parent_field_key Flexible-content parent key.
 * @return void
 */
function mrn_base_stack_register_cloned_acf_layout_fields( array $layouts, $parent_field_key ) {
	$parent_field_key = is_string( $parent_field_key ) ? trim( $parent_field_key ) : '';
	if ( '' === $parent_field_key || ! function_exists( 'acf_add_local_field' ) ) {
		return;
	}

	foreach ( $layouts as $layout ) {
		if ( ! is_array( $layout ) || empty( $layout['key'] ) || empty( $layout['sub_fields'] ) || ! is_array( $layout['sub_fields'] ) ) {
			continue;
		}

		$layout_key = (string) $layout['key'];

		foreach ( $layout['sub_fields'] as $menu_order => $sub_field ) {
			if ( ! is_array( $sub_field ) || empty( $sub_field['key'] ) ) {
				continue;
			}

			$sub_field['parent']        = $parent_field_key;
			$sub_field['parent_layout'] = $layout_key;
			$sub_field['menu_order']    = (int) $menu_order;

			// ACF recursively extracts repeater, group, and flexible sub-fields.
			acf_add_local_field( $sub_field );
		}
	}
}

// Contract mutations run through priority 200; finalize only completed builder trees.
add_filter( 'acf/load_field/type=flexible_content', 'mrn_base_stack_finalize_acf_builder_field_tree', 999 );
add_filter( 'acf/prepare_field/type=flexible_content', 'mrn_base_stack_finalize_acf_builder_field_tree', 999 );
add_filter( 'acf/load_field/type=repeater', 'mrn_base_stack_finalize_acf_builder_field_tree', 999 );
add_filter( 'acf/prepare_field/type=repeater', 'mrn_base_stack_finalize_acf_builder_field_tree', 999 );

// Key-specific population can run after ACF's type hook; finalize that result too.
$mrn_base_stack_builder_field_keys = array(
	'field_mrn_page_hero_rows',
	'field_mrn_page_content_rows',
	'field_mrn_page_after_content_rows',
	'field_mrn_sidebar_rows',
	'field_mrn_page_template_sidebar_rows',
	'field_mrn_404_content_rows',
);

foreach ( $mrn_base_stack_builder_field_keys as $mrn_base_stack_builder_field_key ) {
	add_filter( 'acf/load_field/key=' . $mrn_base_stack_builder_field_key, 'mrn_base_stack_finalize_acf_builder_field_tree', 999 );
	add_filter( 'acf/prepare_field/key=' . $mrn_base_stack_builder_field_key, 'mrn_base_stack_finalize_acf_builder_field_tree', 999 );
}
