<?php
/**
 * Avoid empty builder hydration and bound the editor cost of repeaters.
 *
 * @package mrn-base-stack
 */

/**
 * Recognize an empty public builder without constructing its derived catalog.
 *
 * This is deliberately conservative: custom ACF or metadata callbacks, previews,
 * defaults, alternate references, and cached values all retain native get_field().
 * Only the two derived post fields whose factories were measured are eligible.
 *
 * @param string $name Field name.
 * @param int    $post_id Explicit post ID.
 * @return bool True only when the native field cannot supply rows.
 */
function mrn_base_stack_builder_field_is_empty( $name, $post_id ) {
	if ( ! in_array( $name, array( 'page_hero_rows', 'page_after_content_rows' ), true ) || is_admin() || wp_doing_ajax() || is_preview() || wp_is_post_revision( $post_id ) || ! empty( $GLOBALS['acf_blocks_doing_auto_inline_editing'] ) || ! function_exists( 'acf_get_local_field' ) || ! function_exists( 'acf_get_store' ) ) {
		return false;
	}

	$key   = 'field_mrn_' . $name;
	$field = acf_get_local_field( $key );
	$alias = acf_get_local_field( $name );
	if ( ! is_array( $field ) || ( $alias['key'] ?? '' ) !== $key || 'flexible_content' !== ( $field['type'] ?? '' ) || ( $field['name'] ?? '' ) !== $name || ! empty( $field['default_value'] ) ) {
		return false;
	}

	// These native/Stack callbacks only normalize or populate these definitions.
	$known = array(
		'_acf_apply_hook_variations',
		'_acf_apply_deprecated_hook',
		'_acf_apply_is_local_field_key',
		'acf_revisions::acf_validate_post_id',
		'acf_translate_field',
		'acf_field_repeater::validate_any_field',
		'acf_field_flexible_content::validate_any_field',
		'acf_field_flexible_content::validate_field',
		'acf_field_flexible_content::translate_field',
		'ACF_Compatibility::validate_field',
		'mrn_base_stack_normalize_acf_defaults_in_field_tree',
		'acf_field_flexible_content::load_field',
		'acf_field_flexible_content::load_value',
		'acf_field_flexible_content::format_value',
		'ACF\\Blocks\\AutoInlineEditing\\populate_auto_inline_editing_values',
		'mrn_base_stack_prepare_dynamic_content_list_select_fields',
		'mrn_layout_import_export_add_layout_export_fields',
		'mrn_base_stack_populate_hero_builder_field',
		'mrn_base_stack_populate_after_content_builder_field',
		'mrn_base_stack_filter_builder_layout_allowlist_field_layouts',
		'mrn_base_stack_finalize_acf_builder_field_tree',
		'mrn_base_stack_apply_primary_layout_contract_on_flexible_load',
		'mrn_base_stack_dedupe_flexible_layout_effects_on_load',
	);
	$hooks = array( 'acf/pre_load_post_id', 'acf/validate_post_id', 'acf/get_post_id_info', 'acf/pre_load_reference', 'acf/load_reference', 'acf/pre_load_metadata', 'acf/load_metadata', 'acf/get_valid_post_id', 'acf/get_field_reference', 'acf/get_valid_field', 'acf/is_field_key' );
	foreach ( array( 'pre_load_field', 'validate_field', 'translate_field', 'load_field', 'pre_load_value', 'load_value', 'pre_format_value', 'format_value' ) as $hook ) {
		foreach ( array( '', '/type=flexible_content', '/name=' . $name, '/key=' . $key ) as $variation ) {
			$hooks[] = 'acf/' . $hook . $variation;
		}
	}
	foreach ( $hooks as $hook ) {
		foreach ( $GLOBALS['wp_filter'][ $hook ]->callbacks ?? array() as $callbacks ) {
			foreach ( $callbacks as $callback ) {
				$callable = $callback['function'];
				if ( is_array( $callable ) ) {
					$callable = ( is_object( $callable[0] ) ? get_class( $callable[0] ) : $callable[0] ) . '::' . $callable[1];
				}
				if ( ! is_string( $callable ) || ! in_array( $callable, $known, true ) ) {
					return false;
				}
			}
		}
	}

	$reference = get_post_meta( $post_id, '_' . $name, true );
	if ( $key !== $reference ) {
		return false;
	}
	foreach ( array( '', ':formatted', ':escaped' ) as $suffix ) {
		if ( ! empty( acf_get_store( 'values' )->get( $post_id . ':' . $name . $suffix ) ) ) {
			return false;
		}
	}
	$loaded = acf_get_store( 'fields' )->get( $key );
	if ( is_array( $loaded ) && ! empty( $loaded['default_value'] ) ) {
		return false;
	}

	return in_array( get_post_meta( $post_id, $name, true ), array( '', false, null, array(), 0, '0' ), true );
}
