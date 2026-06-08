<?php
/**
 * Row-spacing helpers for flexible-content row meta.
 *
 * @package mrn-base-stack
 */

if ( ! function_exists( 'mrn_base_stack_sanitize_meta_key_fragment' ) ) {
	/**
	 * Sanitize one flexible-content meta-key fragment.
	 *
	 * @param mixed $value Raw key fragment.
	 * @return string
	 */
	function mrn_base_stack_sanitize_meta_key_fragment( $value ) {
		$value = is_scalar( $value ) ? trim( (string) $value ) : '';

		if ( '' === $value ) {
			return '';
		}

		if ( function_exists( 'sanitize_key' ) ) {
			return sanitize_key( $value );
		}

		$value = strtolower( $value );
		$value = preg_replace( '/[^a-z0-9_\-]/', '', $value );

		return is_string( $value ) ? $value : '';
	}
}

if ( ! function_exists( 'mrn_base_stack_get_row_spacing_selector_field_names' ) ) {
	/**
	 * Get the list of row-spacing selector field names.
	 *
	 * The full layout-builder runtime defines this helper in builder helpers.
	 * This fallback keeps child templates on the same public contract when the
	 * layout builder is disabled.
	 *
	 * @return array<int, string>
	 */
	function mrn_base_stack_get_row_spacing_selector_field_names() {
		$names = array(
			'row_spacing_preset',
			'row_spacing_margin_preset',
			'row_spacing_padding_preset',
		);

		$definitions = array();
		if ( function_exists( 'mrn_base_stack_get_row_spacing_side_selector_definitions' ) ) {
			$definitions = mrn_base_stack_get_row_spacing_side_selector_definitions();
		} elseif ( function_exists( 'mrn_base_stack_get_disabled_builder_row_spacing_side_selector_definitions' ) ) {
			$definitions = mrn_base_stack_get_disabled_builder_row_spacing_side_selector_definitions();
		}

		if ( is_array( $definitions ) ) {
			foreach ( $definitions as $definition ) {
				if ( ! is_array( $definition ) ) {
					continue;
				}

				$selector_name = isset( $definition['name'] ) ? mrn_base_stack_sanitize_meta_key_fragment( $definition['name'] ) : '';
				if ( '' === $selector_name ) {
					continue;
				}

				$names[] = $selector_name;
			}
		}

		if ( 3 === count( $names ) ) {
			$names = array_merge(
				$names,
				array(
					'row_spacing_margin_top_preset',
					'row_spacing_margin_right_preset',
					'row_spacing_margin_bottom_preset',
					'row_spacing_margin_left_preset',
					'row_spacing_padding_top_preset',
					'row_spacing_padding_right_preset',
					'row_spacing_padding_bottom_preset',
					'row_spacing_padding_left_preset',
				)
			);
		}

		return array_values( array_unique( $names ) );
	}
}

if ( ! function_exists( 'mrn_base_stack_get_flex_row_layout_name' ) ) {
	/**
	 * Get a normalized flexible-content layout name from one row array.
	 *
	 * @param mixed $row Flexible-content row.
	 * @return string
	 */
	function mrn_base_stack_get_flex_row_layout_name( $row ) {
		if ( ! is_array( $row ) || ! isset( $row['acf_fc_layout'] ) || ! is_scalar( $row['acf_fc_layout'] ) ) {
			return '';
		}

		return mrn_base_stack_sanitize_meta_key_fragment( $row['acf_fc_layout'] );
	}
}

if ( ! function_exists( 'mrn_base_stack_flex_row_matches_layout_name' ) ) {
	/**
	 * Check whether a raw flexible-content row matches the current ACF layout.
	 *
	 * @param mixed  $row            Raw flexible-content row.
	 * @param string $current_layout Current visible row layout.
	 * @return bool
	 */
	function mrn_base_stack_flex_row_matches_layout_name( $row, $current_layout ) {
		$current_layout = mrn_base_stack_sanitize_meta_key_fragment( $current_layout );
		if ( '' === $current_layout ) {
			return true;
		}

		$row_layout = mrn_base_stack_get_flex_row_layout_name( $row );

		return '' === $row_layout || $current_layout === $row_layout;
	}
}

if ( ! function_exists( 'mrn_base_stack_get_current_flex_row_meta_index' ) ) {
	/**
	 * Resolve the current visible ACF flexible-content row to its raw meta index.
	 *
	 * ACF may skip disabled rows in frontend loops while preserving the raw row
	 * indexes in post meta. This maps the current visible position through the
	 * raw row keys returned by get_field( $flex_field, $post_id, false ).
	 *
	 * @param string $flex_field Flexible-content field name.
	 * @param int    $post_id    Post ID. Defaults to the current post.
	 * @return int Raw flexible-content meta index, or -1 when it cannot be resolved safely.
	 */
	function mrn_base_stack_get_current_flex_row_meta_index( $flex_field = 'page_builder_fields', $post_id = 0 ) {
		$flex_field = mrn_base_stack_sanitize_meta_key_fragment( $flex_field );
		if ( '' === $flex_field || ! function_exists( 'get_row_index' ) || ! function_exists( 'get_field' ) ) {
			return -1;
		}

		$acf_row_index = get_row_index();
		if ( ! is_numeric( $acf_row_index ) ) {
			return -1;
		}

		$acf_row_index    = (int) $acf_row_index;
		$row_index_offset = 1;
		if ( function_exists( 'acf_get_setting' ) ) {
			$acf_row_index_offset = acf_get_setting( 'row_index_offset' );
			if ( is_numeric( $acf_row_index_offset ) ) {
				$row_index_offset = (int) $acf_row_index_offset;
			}
		}
		$visible_position = $acf_row_index - $row_index_offset;
		if ( $visible_position < 0 ) {
			return -1;
		}

		$post_id = $post_id ? absint( $post_id ) : 0;
		if ( ! $post_id && function_exists( 'get_the_ID' ) ) {
			$post_id = absint( get_the_ID() );
		}
		if ( ! $post_id && function_exists( 'get_queried_object_id' ) ) {
			$post_id = absint( get_queried_object_id() );
		}
		if ( ! $post_id ) {
			return -1;
		}

		$rows = get_field( $flex_field, $post_id, false );
		if ( ! is_array( $rows ) || empty( $rows ) ) {
			return -1;
		}

		$current_layout = function_exists( 'get_row_layout' ) ? mrn_base_stack_sanitize_meta_key_fragment( get_row_layout() ) : '';
		$row_keys       = array_values( array_keys( $rows ) );
		$candidates     = array();

		if ( array_key_exists( $visible_position, $row_keys ) && is_numeric( $row_keys[ $visible_position ] ) ) {
			$candidates[] = (int) $row_keys[ $visible_position ];
		}

		$candidates[] = $visible_position;
		$candidates[] = $acf_row_index - 1;
		$candidates[] = $acf_row_index - $row_index_offset;
		$candidates   = array_values( array_unique( array_filter( $candidates, 'is_int' ) ) );

		foreach ( $candidates as $candidate_index ) {
			if ( $candidate_index < 0 || ! array_key_exists( $candidate_index, $rows ) ) {
				continue;
			}

			if ( ! mrn_base_stack_flex_row_matches_layout_name( $rows[ $candidate_index ], $current_layout ) ) {
				continue;
			}

			return $candidate_index;
		}

		if ( '' !== $current_layout ) {
			$raw_key_count = count( $row_keys );
			for ( $position = $visible_position; $position < $raw_key_count; $position++ ) {
				if ( ! isset( $row_keys[ $position ] ) || ! is_numeric( $row_keys[ $position ] ) ) {
					continue;
				}

				$candidate_index = (int) $row_keys[ $position ];
				if ( $candidate_index < 0 || ! array_key_exists( $candidate_index, $rows ) ) {
					continue;
				}

				if ( mrn_base_stack_flex_row_matches_layout_name( $rows[ $candidate_index ], $current_layout ) ) {
					return $candidate_index;
				}
			}
		}

		return -1;
	}
}

if ( ! function_exists( 'mrn_base_stack_get_builder_row_meta_index_from_row' ) ) {
	/**
	 * Resolve a raw row index from explicit builder context attached to a row.
	 *
	 * @param array<string, mixed> $row        Flexible-content row.
	 * @param string               $flex_field Flexible-content field name.
	 * @param int                  $post_id    Post ID.
	 * @return int Raw flexible-content meta index, or -1 when unavailable.
	 */
	function mrn_base_stack_get_builder_row_meta_index_from_row( array $row, $flex_field, $post_id ) {
		if ( ! isset( $row['__mrn_builder_row_index'] ) || ! is_numeric( $row['__mrn_builder_row_index'] ) ) {
			return -1;
		}

		$row_index = (int) $row['__mrn_builder_row_index'];
		if ( $row_index < 0 ) {
			return -1;
		}

		$current_layout = mrn_base_stack_get_flex_row_layout_name( $row );
		if ( '' === $current_layout ) {
			return $row_index;
		}

		if ( function_exists( 'get_field' ) && $post_id ) {
			$rows = get_field( $flex_field, $post_id, false );
			if ( is_array( $rows ) && ! empty( $rows ) ) {
				$candidate_index = $row_index;
				if ( ! array_key_exists( $candidate_index, $rows ) ) {
					$row_keys = array_values( array_keys( $rows ) );
					if ( ! array_key_exists( $row_index, $row_keys ) || ! is_numeric( $row_keys[ $row_index ] ) ) {
						return -1;
					}

					$candidate_index = (int) $row_keys[ $row_index ];
				}

				if ( ! array_key_exists( $candidate_index, $rows ) || ! mrn_base_stack_flex_row_matches_layout_name( $rows[ $candidate_index ], $current_layout ) ) {
					return -1;
				}

				return $candidate_index;
			}
		}

		if ( function_exists( 'get_post_meta' ) && $post_id ) {
			$meta_layout = get_post_meta( $post_id, $flex_field . '_' . $row_index . '_acf_fc_layout', true );
			$meta_layout = is_scalar( $meta_layout ) ? mrn_base_stack_sanitize_meta_key_fragment( $meta_layout ) : '';
			if ( '' !== $meta_layout && $current_layout !== $meta_layout ) {
				return -1;
			}
		}

		return $row_index;
	}
}

if ( ! function_exists( 'mrn_base_stack_hydrate_row_spacing_selectors_from_meta' ) ) {
	/**
	 * Hydrate missing row-spacing selector fields from raw flexible-content meta.
	 *
	 * @param array<string, mixed> $row        Row-like payload.
	 * @param string               $flex_field Flexible-content field name.
	 * @param int                  $post_id    Post ID. Defaults to the current post.
	 * @param int|null             $row_index  Optional raw flexible-content meta index.
	 * @return array<string, mixed>
	 */
	function mrn_base_stack_hydrate_row_spacing_selectors_from_meta( array $row, $flex_field = 'page_builder_fields', $post_id = 0, $row_index = null ) {
		$flex_field = mrn_base_stack_sanitize_meta_key_fragment( $flex_field );
		if ( '' === $flex_field || ! function_exists( 'get_post_meta' ) ) {
			return $row;
		}

		if ( ! $post_id && isset( $row['__mrn_builder_post_id'] ) && is_numeric( $row['__mrn_builder_post_id'] ) ) {
			$post_id = (int) $row['__mrn_builder_post_id'];
		}

		$post_id = $post_id ? absint( $post_id ) : 0;
		if ( ! $post_id && function_exists( 'get_the_ID' ) ) {
			$post_id = absint( get_the_ID() );
		}
		if ( ! $post_id && function_exists( 'get_queried_object_id' ) ) {
			$post_id = absint( get_queried_object_id() );
		}
		if ( ! $post_id ) {
			return $row;
		}

		if ( null === $row_index ) {
			$row_index = mrn_base_stack_get_current_flex_row_meta_index( $flex_field, $post_id );
			if ( $row_index < 0 ) {
				$row_index = mrn_base_stack_get_builder_row_meta_index_from_row( $row, $flex_field, $post_id );
			}
		} elseif ( is_numeric( $row_index ) ) {
			$row_index = (int) $row_index;
		} else {
			$row_index = -1;
		}

		if ( $row_index < 0 ) {
			return $row;
		}

		foreach ( mrn_base_stack_get_row_spacing_selector_field_names() as $selector_name ) {
			$selector_name = mrn_base_stack_sanitize_meta_key_fragment( $selector_name );
			if ( '' === $selector_name ) {
				continue;
			}

			$has_value = isset( $row[ $selector_name ] ) && is_scalar( $row[ $selector_name ] ) && '' !== trim( (string) $row[ $selector_name ] );
			if ( $has_value ) {
				continue;
			}

			$meta_value = get_post_meta( $post_id, $flex_field . '_' . $row_index . '_' . $selector_name, true );
			if ( ! is_scalar( $meta_value ) ) {
				continue;
			}

			$meta_value = trim( (string) $meta_value );
			if ( '' !== $meta_value ) {
				$row[ $selector_name ] = $meta_value;
			}
		}

		return $row;
	}
}

if ( ! function_exists( 'mrn_base_stack_get_row_spacing_attr_html_for_current_row' ) ) {
	/**
	 * Get escaped row-spacing attribute HTML for the current flexible-content row.
	 *
	 * @param array<string, mixed> $row        Row-like payload.
	 * @param string               $flex_field Flexible-content field name.
	 * @param int                  $post_id    Post ID. Defaults to the current post.
	 * @return string
	 */
	function mrn_base_stack_get_row_spacing_attr_html_for_current_row( array $row = array(), $flex_field = 'page_builder_fields', $post_id = 0 ) {
		$row = mrn_base_stack_hydrate_row_spacing_selectors_from_meta( $row, $flex_field, $post_id );

		$contract = function_exists( 'mrn_base_stack_get_row_spacing_contract' )
			? mrn_base_stack_get_row_spacing_contract( $row )
			: array( 'attributes' => array() );

		$attributes = array();
		if ( ! empty( $contract['attributes'] ) && is_array( $contract['attributes'] ) ) {
			$attributes = function_exists( 'mrn_base_stack_merge_builder_attributes' )
				? mrn_base_stack_merge_builder_attributes( $attributes, $contract['attributes'] )
				: array_merge( $attributes, $contract['attributes'] );
		}

		if ( empty( $attributes ) ) {
			return '';
		}

		if ( function_exists( 'mrn_base_stack_get_html_attributes' ) ) {
			return mrn_base_stack_get_html_attributes( $attributes );
		}

		$parts = array();
		foreach ( $attributes as $attribute_name => $attribute_value ) {
			$attribute_name  = is_string( $attribute_name ) ? trim( $attribute_name ) : '';
			$attribute_value = is_scalar( $attribute_value ) ? trim( (string) $attribute_value ) : '';

			if ( '' === $attribute_name || '' === $attribute_value ) {
				continue;
			}

			$escaped_name  = function_exists( 'esc_attr' ) ? esc_attr( $attribute_name ) : htmlspecialchars( $attribute_name, ENT_QUOTES, 'UTF-8' );
			$escaped_value = function_exists( 'esc_attr' ) ? esc_attr( $attribute_value ) : htmlspecialchars( $attribute_value, ENT_QUOTES, 'UTF-8' );
			$parts[]       = sprintf( '%s="%s"', $escaped_name, $escaped_value );
		}

		return implode( ' ', $parts );
	}
}
