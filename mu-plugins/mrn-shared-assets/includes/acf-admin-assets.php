<?php
/**
 * Discover fields that can appear without reloading a post editor.
 *
 * Other ACF forms enqueue helpers as their fields render. Post editors also
 * need assets for groups that ACF can insert after a template/status change.
 * This uses ACF's cached group inventory only on post edit screens, and loads
 * field definitions only for groups whose remaining location rules match.
 *
 * @package MRN_Shared_Assets
 */

defined( 'ABSPATH' ) || exit;

/**
 * Include editable post-location conditions in asset discovery only.
 *
 * @param bool  $match Current location result.
 * @param array $rule  Location rule.
 * @return bool
 */
function mrn_shared_assets_acf_asset_location_match( $match, $rule ) {
	$editable = array( 'page_template', 'post_template', 'post_status', 'post_format', 'post_category', 'post_taxonomy', 'page_parent', 'page_type' );
	return in_array( $rule['param'] ?? '', $editable, true ) ? true : $match;
}

/**
 * Check a field tree, including empty repeater/flexible-content templates.
 *
 * ACF expands clone definitions in acf_get_fields(). Do not replace their
 * keys/names: consumers must match the same identifiers as the rendered DOM.
 *
 * @param array    $fields    Field definitions.
 * @param callable $predicate Consumer's field matcher.
 * @return bool
 */
function mrn_shared_assets_acf_field_tree_matches( $fields, $predicate ) {
	foreach ( (array) $fields as $field ) {
		if ( ! is_array( $field ) ) {
			continue;
		}
		if ( $predicate( $field ) || mrn_shared_assets_acf_field_tree_matches( $field['sub_fields'] ?? array(), $predicate ) ) {
			return true;
		}
		foreach ( (array) ( $field['layouts'] ?? array() ) as $layout ) {
			if ( mrn_shared_assets_acf_field_tree_matches( $layout['sub_fields'] ?? array(), $predicate ) ) {
				return true;
			}
		}
	}
	return false;
}

/**
 * Check registered local records before expanding dynamic editor field trees.
 *
 * A positive match conservatively enqueues an asset. Unknown/dynamic/clone
 * definitions still use the fully loaded discovery fallback below.
 *
 * @param string   $parent    Local group or field key.
 * @param callable $predicate Consumer's field matcher.
 * @param array    $visited   Already visited parent keys.
 * @return bool
 */
function mrn_shared_assets_acf_local_tree_matches( $parent, $predicate, &$visited ) {
	if ( ! function_exists( 'acf_get_local_fields' ) || isset( $visited[ $parent ] ) ) {
		return false;
	}
	$visited[ $parent ] = true;
	foreach ( (array) acf_get_local_fields( $parent ) as $field ) {
		if ( ! is_array( $field ) ) {
			continue;
		}
		if ( mrn_shared_assets_acf_field_tree_matches( array( $field ), $predicate )
			|| ( ! empty( $field['key'] ) && mrn_shared_assets_acf_local_tree_matches( $field['key'], $predicate, $visited ) ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Does this post editor contain (or allow ACF to insert) a matching field?
 *
 * @param callable $predicate Consumer's field matcher.
 * @return bool
 */
function mrn_shared_assets_acf_editor_has_field( $predicate ) {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! is_admin() || ! $screen instanceof WP_Screen || ! in_array( $screen->base, array( 'post', 'post-new' ), true ) ) {
		return false;
	}
	if ( in_array( $screen->post_type, array( 'acf-field', 'acf-field-group', 'acf-post-type', 'acf-taxonomy', 'acf-ui-options-page' ), true ) || ! function_exists( 'acf_get_field_groups' ) || ! function_exists( 'acf_get_fields' ) ) {
		return false;
	}
	// ACF block edit forms arrive over AJAX, including newly inserted blocks.
	// Keep existing plugin support there; the parent theme excludes block editors.
	if ( method_exists( $screen, 'is_block_editor' ) && $screen->is_block_editor() ) {
		return true;
	}

	global $post;
	$post_id = $post instanceof WP_Post ? $post->ID : 0;
	$context = array( 'post_type' => $screen->post_type, 'post_id' => $post_id );
	static $field_trees = array();
	static $matching_groups = array();
	$cache_key = $screen->id . ':' . $post_id;
	if ( ! isset( $matching_groups[ $cache_key ] ) ) {
		// Never leave the broader asset-only location match active for rendering.
		add_filter( 'acf/location/rule_match', 'mrn_shared_assets_acf_asset_location_match', PHP_INT_MAX, 2 );
		try {
			$matching_groups[ $cache_key ] = acf_get_field_groups( $context );
		} finally {
			remove_filter( 'acf/location/rule_match', 'mrn_shared_assets_acf_asset_location_match', PHP_INT_MAX );
		}
	}
	$visited = array();
	foreach ( $matching_groups[ $cache_key ] as $group ) {
		if ( mrn_shared_assets_acf_local_tree_matches( $group['key'], $predicate, $visited ) ) {
			return true;
		}
	}
	foreach ( $matching_groups[ $cache_key ] as $group ) {
		$key = $cache_key . ':' . $group['key'];
		if ( ! isset( $field_trees[ $key ] ) ) {
			$field_trees[ $key ] = (array) acf_get_fields( $group );
		}
		if ( mrn_shared_assets_acf_field_tree_matches( $field_trees[ $key ], $predicate ) ) {
			return true;
		}
	}
	return false;
}
