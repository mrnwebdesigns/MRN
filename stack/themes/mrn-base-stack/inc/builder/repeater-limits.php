<?php
/**
 * Enforce a five-item ceiling for Stack repeaters without discarding legacy data.
 *
 * @package mrn-base-stack
 */

/**
 * Return a repeater's Stack ceiling, preserving existing tighter limits.
 *
 * @param array<string, mixed> $field Field definition.
 * @return int Zero for fields outside this policy.
 */
function mrn_base_stack_repeater_limit( $field ) {
	if ( 'repeater' !== ( $field['type'] ?? '' ) || false === strpos( (string) ( $field['key'] ?? '' ), 'field_mrn_' ) ) {
		return 0;
	}
	$max = (int) ( $field['_mrn_repeater_limit'] ?? $field['max'] ?? 0 );
	return $max > 0 ? min( 5, $max ) : 5;
}

/**
 * Set native editor and REST-schema limits. Five-item fields need no pagination.
 *
 * @param array<string, mixed> $field Field definition.
 * @return array<string, mixed>
 */
function mrn_base_stack_limit_repeater_definition( $field ) {
	$limit = mrn_base_stack_repeater_limit( $field );
	if ( $limit ) {
		$field['_mrn_repeater_limit'] = $limit;
		$field['max']                 = $limit;
		$field['min']                 = min( (int) ( $field['min'] ?? 0 ), $limit );
		$field['pagination']          = 0;
	}
	return $field;
}
add_filter( 'acf/load_field/type=repeater', 'mrn_base_stack_limit_repeater_definition', 30 );

/**
 * Keep existing oversized collections editable, with no further growth.
 *
 * @param array<string, mixed> $field Prepared field with its saved value.
 * @return array<string, mixed>
 */
function mrn_base_stack_bound_repeater_editor( $field ) {
	$limit = mrn_base_stack_repeater_limit( $field );
	if ( $limit ) {
		$field['max'] = max( $limit, is_array( $field['value'] ?? null ) ? count( $field['value'] ) : 0 );
	}
	return $field;
}
add_filter( 'acf/prepare_field/type=repeater', 'mrn_base_stack_bound_repeater_editor', 30 );

/**
 * Identify an exact, prevalidated child write while ACF saves its parent tree.
 *
 * @param mixed                $value Submitted value.
 * @param int|string           $post_id ACF storage target.
 * @param array<string, mixed> $field Field with the destination metadata name.
 * @return string
 */
function mrn_base_stack_repeater_write_token( $value, $post_id, $field ) {
	return hash( 'sha256', wp_json_encode( array( $post_id, $field['key'], $field['name'], $value ) ) );
}

/**
 * Check the entire submitted tree before any of its subfields are written.
 *
 * Editor row-N keys retain the original index during reorder. New editor rows
 * cannot inherit another row's grandfathered allowance. API arrays use indices.
 * Destination tokens let native child writes retain that allowance after a move.
 *
 * @param mixed                $value Submitted value, keyed by field key or name.
 * @param int|string           $post_id ACF storage target.
 * @param array<string, mixed> $field Field with the destination metadata name.
 * @param string               $old_name Original metadata name, before reorder.
 * @param array<string, bool>  $tokens Exact child writes checked by this walk.
 * @return string Empty when valid, otherwise an editor-safe error message.
 */
function mrn_base_stack_check_repeater_tree( $value, $post_id, $field, $old_name, &$tokens ) {
	$type = $field['type'] ?? '';
	if ( ! is_array( $value ) || ! in_array( $type, array( 'repeater', 'flexible_content', 'group' ), true ) ) {
		return '';
	}
	unset( $value['acfcloneindex'] );
	$limit = mrn_base_stack_repeater_limit( $field );
	if ( $limit ) {
		$stored         = $field;
		$stored['name'] = $old_name;
		$maximum        = max( $limit, (int) acf_get_metadata_by_field( $post_id, $stored ) );
		if ( count( $value ) > $maximum ) {
			/* translators: 1: Field label, 2: Maximum rows allowed. */
			return sprintf( __( '%1$s allows at most %2$d rows. Remove the extra rows before saving.', 'mrn-base-stack' ), esc_html( $field['label'] ?? $field['name'] ), $maximum );
		}
	}
	$rows     = 'group' === $type ? array( $value ) : $value;
	$position = -1;
	foreach ( $rows as $row_key => $row ) {
		++$position;
		if ( ! is_array( $row ) ) {
			continue;
		}
		$children = $field['sub_fields'] ?? array();
		if ( 'flexible_content' === $type ) {
			$children = array();
			foreach ( $field['layouts'] ?? array() as $layout ) {
				if ( ( $row['acf_fc_layout'] ?? '' ) === $layout['name'] ) {
					$children = $layout['sub_fields'];
					break;
				}
			}
		}
		$old_index = is_int( $row_key ) ? $row_key : ( preg_match( '/^row-([0-9]+)$/D', $row_key, $match ) ? (int) $match[1] : 'new' );
		foreach ( $children as $child ) {
			$name     = $child['name'];
			$selector = array_key_exists( $child['key'], $row ) ? $child['key'] : $name;
			if ( ! $name || ! array_key_exists( $selector, $row ) ) {
				continue;
			}
			if ( ! is_array( $row[ $selector ] ) || ! in_array( $child['type'] ?? '', array( 'repeater', 'flexible_content', 'group' ), true ) ) {
				continue;
			}
			$child['name'] = $field['name'] . ( 'group' === $type ? '_' : '_' . $position . '_' ) . $name;
			$old_child     = $old_name . ( 'group' === $type ? '_' : '_' . $old_index . '_' ) . $name;
			$error         = mrn_base_stack_check_repeater_tree( $row[ $selector ], $post_id, $child, $old_child, $tokens );
			if ( $error ) {
				return $error;
			}
			$tokens[ mrn_base_stack_repeater_write_token( $row[ $selector ], $post_id, $child ) ] = true;
		}
	}
	return '';
}

/**
 * Show a normal ACF validation error before an editor submits oversized content.
 *
 * @param bool|string          $valid Previous validation result.
 * @param mixed                $value Submitted root field value.
 * @param array<string, mixed> $field Root field definition.
 * @param string               $input Input name.
 * @return bool|string
 */
function mrn_base_stack_validate_repeater_limits( $valid, $value, $field, $input ) {
	if ( true !== $valid || 'acf[' . $field['key'] . ']' !== $input || false === strpos( (string) $field['key'], 'field_mrn_' ) ) {
		return $valid;
	}
	// ACF verifies the form nonce before running its validation filters.
	$target  = acf_get_form_data( 'post_id' );
	$target  = $target ? $target : acf_request_arg( '_acf_post_id', 0 );
	$post_id = is_scalar( $target ) ? acf_get_valid_post_id( sanitize_text_field( (string) $target ) ) : 0;
	$tokens  = array();
	$error   = mrn_base_stack_check_repeater_tree( $value, $post_id, $field, $field['name'], $tokens );
	return $error ? $error : $valid;
}
add_filter( 'acf/validate_value', 'mrn_base_stack_validate_repeater_limits', 20, 4 );

/**
 * Refuse oversized ACF API writes before native repeater updates touch metadata.
 *
 * The update_field()/update_sub_field() APIs return false on rejection. No rows are
 * sliced or silently dropped; the complete submitted field remains unchanged.
 * Direct SQL and direct WordPress metadata writes are outside ACF's API.
 *
 * @param mixed                $check Previous short-circuit result.
 * @param mixed                $value Submitted value.
 * @param int|string           $post_id ACF storage target.
 * @param array<string, mixed> $field Field definition.
 * @return mixed
 */
function mrn_base_stack_guard_repeater_write( $check, $value, $post_id, $field ) {
	if ( null !== $check || ! is_array( $value ) || ! in_array( $field['type'] ?? '', array( 'repeater', 'flexible_content', 'group' ), true ) || false === strpos( (string) $field['key'], 'field_mrn_' ) ) {
		return $check;
	}
	$token = mrn_base_stack_repeater_write_token( $value, $post_id, $field );
	if ( isset( $GLOBALS['mrn_repeater_checked_children'][ $token ] ) ) {
		unset( $GLOBALS['mrn_repeater_checked_children'][ $token ] );
		return $check;
	}
	$tokens = array();
	if ( mrn_base_stack_check_repeater_tree( $value, $post_id, $field, $field['name'], $tokens ) ) {
		return false;
	}
	if ( $tokens ) {
		$GLOBALS['mrn_repeater_checked_roots'][ $token ] = $tokens;
		$GLOBALS['mrn_repeater_checked_children']        = ( $GLOBALS['mrn_repeater_checked_children'] ?? array() ) + $tokens;
	}
	return $check;
}
add_filter( 'acf/pre_update_value', 'mrn_base_stack_guard_repeater_write', 5, 4 );

/**
 * Expire child allowances when the synchronous native parent update finishes.
 *
 * @param mixed                $value Native stored value.
 * @param int|string           $post_id ACF storage target.
 * @param array<string, mixed> $field Field definition.
 * @param mixed                $original Original parent value.
 * @return mixed
 */
function mrn_base_stack_clear_repeater_write( $value, $post_id, $field, $original ) {
	if ( is_array( $original ) && ! empty( $GLOBALS['mrn_repeater_checked_roots'] ) ) {
		$token = mrn_base_stack_repeater_write_token( $original, $post_id, $field );
		foreach ( $GLOBALS['mrn_repeater_checked_roots'][ $token ] ?? array() as $child => $checked ) {
			unset( $GLOBALS['mrn_repeater_checked_children'][ $child ] );
		}
		unset( $GLOBALS['mrn_repeater_checked_roots'][ $token ] );
	}
	return $value;
}
add_filter( 'acf/update_value', 'mrn_base_stack_clear_repeater_write', PHP_INT_MAX, 4 );
