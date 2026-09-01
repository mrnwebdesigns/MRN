<?php
/**
 * Front-end asset loading helpers.
 *
 * @package mrn-base-stack
 */

/**
 * Get conditional layout stylesheet files.
 *
 * Keys map to builder layout/style contexts. Additional display styles can add
 * entries through the filter without changing the enqueue contract.
 *
 * @return array<string, array{handle:string,path:string}>
 */
function mrn_base_stack_get_layout_style_manifest() {
	$manifest = array(
		'row_background_media' => array(
			'handle' => 'mrn-base-stack-row-background-media',
			'path'   => 'css/layouts/row-background-media.css',
		),
		'hero'                 => array(
			'handle' => 'mrn-base-stack-layout-hero',
			'path'   => 'css/layouts/hero.css',
		),
	);

	/**
	 * Filter the conditional layout stylesheet manifest.
	 *
	 * @param array<string, array{handle:string,path:string}> $manifest Layout stylesheet manifest.
	 */
	return (array) apply_filters( 'mrn_base_stack_layout_style_manifest', $manifest );
}

/**
 * Check whether a builder row includes background media.
 *
 * @param array<string, mixed> $row Flexible content row.
 * @return bool
 */
function mrn_base_stack_builder_row_has_background_media( array $row ) {
	foreach ( array( 'background_image', 'background_video', 'background_video_upload' ) as $field_name ) {
		if ( ! empty( $row[ $field_name ] ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Collect conditional layout stylesheet keys from builder rows.
 *
 * @param mixed               $rows Builder rows.
 * @param array<string, bool> $style_keys Collected style keys.
 * @param string              $field_context Current ACF field context.
 * @return array<string, bool>
 */
function mrn_base_stack_collect_layout_style_keys_from_rows( $rows, array $style_keys = array(), $field_context = '' ) {
	if ( ! is_array( $rows ) ) {
		return $style_keys;
	}

	foreach ( $rows as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}

		$layout       = isset( $row['acf_fc_layout'] ) ? sanitize_key( (string) $row['acf_fc_layout'] ) : '';
		$style_layout = $layout;

		if ( '' !== $layout ) {
			if ( 'page_hero_rows' === $field_context ) {
				$style_keys['hero'] = true;
				$style_layout       = 'hero';
			} else {
				$style_keys[ $layout ] = true;
			}
		}

		if ( '' !== $style_layout ) {
			$display_mode  = mrn_base_stack_normalize_builder_layout_display_mode( $row['display_mode'] ?? '', $style_layout );
			$display_style = mrn_base_stack_normalize_builder_layout_display_style( $row['display_style'] ?? '', $style_layout, $display_mode, 'default' );
			$style_configs = mrn_base_stack_get_builder_layout_display_styles( $style_layout, $display_mode );

			if ( '' !== $display_style && isset( $style_configs[ $display_style ] ) && is_array( $style_configs[ $display_style ] ) ) {
				$style_config = $style_configs[ $display_style ];

				if ( ! empty( $style_config['asset_key'] ) && is_scalar( $style_config['asset_key'] ) ) {
					$style_keys[ sanitize_key( (string) $style_config['asset_key'] ) ] = true;
				}

				if ( ! empty( $style_config['asset_keys'] ) && is_array( $style_config['asset_keys'] ) ) {
					foreach ( $style_config['asset_keys'] as $asset_key ) {
						if ( is_scalar( $asset_key ) ) {
							$style_keys[ sanitize_key( (string) $asset_key ) ] = true;
						}
					}
				}
			}
		}

		if ( mrn_base_stack_builder_row_has_background_media( $row ) ) {
			$style_keys['row_background_media'] = true;
		}

		foreach ( array( 'left_column_rows', 'right_column_rows', 'panel_rows' ) as $child_key ) {
			if ( ! empty( $row[ $child_key ] ) && is_array( $row[ $child_key ] ) ) {
				$style_keys = mrn_base_stack_collect_layout_style_keys_from_rows( $row[ $child_key ], $style_keys, $child_key );
			}
		}

		foreach ( array( 'tabs', 'tab_items' ) as $tabs_key ) {
			if ( empty( $row[ $tabs_key ] ) || ! is_array( $row[ $tabs_key ] ) ) {
				continue;
			}

			foreach ( $row[ $tabs_key ] as $tab_item ) {
				if ( is_array( $tab_item ) && ! empty( $tab_item['panel_rows'] ) && is_array( $tab_item['panel_rows'] ) ) {
					$style_keys = mrn_base_stack_collect_layout_style_keys_from_rows( $tab_item['panel_rows'], $style_keys, 'panel_rows' );
				}
			}
		}
	}

	return $style_keys;
}

/**
 * Get conditional layout stylesheet keys for a post.
 *
 * @param int $post_id Post ID.
 * @return array<string, bool>
 */
function mrn_base_stack_get_layout_style_keys_for_post( $post_id ) {
	$post_id = (int) $post_id;

	if ( ! $post_id || ! function_exists( 'get_post_meta' ) ) {
		return array();
	}

	// phpcs:ignore Generic.Commenting.DocComment.MissingShort -- PHPStan local type assertion.
	/** @var array<string, bool> $style_keys */
	$style_keys = array();
	$post_meta  = get_post_meta( $post_id, '', false );

	if ( is_array( $post_meta ) ) {
		foreach ( $post_meta as $meta_key => $meta_values ) {
			if ( ! is_string( $meta_key ) || '' === $meta_key || '_' === $meta_key[0] || ! is_array( $meta_values ) ) {
				continue;
			}

			if ( preg_match( '/_(?:background_image|background_video|background_video_upload)$/', $meta_key ) ) {
				foreach ( $meta_values as $meta_value ) {
					$meta_value = function_exists( 'maybe_unserialize' ) ? maybe_unserialize( $meta_value ) : $meta_value;
					if ( ( is_scalar( $meta_value ) && '' !== trim( (string) $meta_value ) && '0' !== (string) $meta_value ) || ( is_array( $meta_value ) && ! empty( $meta_value ) ) ) {
						$style_keys['row_background_media'] = true;
						break;
					}
				}
			}

			if ( ! preg_match( '/(?:^|_)rows$/', $meta_key ) ) {
				continue;
			}

			foreach ( $meta_values as $meta_value ) {
				$layout_names = function_exists( 'maybe_unserialize' ) ? maybe_unserialize( $meta_value ) : $meta_value;
				if ( ! is_array( $layout_names ) ) {
					continue;
				}

				foreach ( $layout_names as $layout_name ) {
					if ( ! is_scalar( $layout_name ) ) {
						continue;
					}

					$layout_name = sanitize_key( (string) $layout_name );
					if ( '' === $layout_name ) {
						continue;
					}

					$style_key                = 'page_hero_rows' === $meta_key ? 'hero' : $layout_name;
					$style_keys[ $style_key ] = true;
				}
			}
		}
	}

	/**
	 * Filter the conditional layout stylesheet keys needed for a post.
	 *
	 * @param array<string, bool> $style_keys Style keys keyed by manifest key.
	 * @param int                 $post_id    Post ID.
	 */
	return (array) apply_filters( 'mrn_base_stack_layout_style_keys_for_post', $style_keys, $post_id );
}

/**
 * Collect icon-font needs from rendered social links.
 *
 * The caller must pass the slot visibility state that matches the current
 * request. When the social menu is disabled, configured rows should not
 * trigger unused icon-font requests.
 *
 * @param bool                    $should_render    Whether the social slot will render.
 * @param mixed                   $social_links     Social link rows from Config Helper.
 * @param bool                    $needs_fontawesome Whether Font Awesome is needed.
 * @param bool                    $needs_dashicons   Whether Dashicons is needed.
 * @return void
 */
function mrn_base_stack_collect_rendered_social_link_asset_needs( $should_render, $social_links, &$needs_fontawesome, &$needs_dashicons ) {
	if ( ! $should_render || ! is_array( $social_links ) ) {
		return;
	}

	foreach ( $social_links as $social_link ) {
		if ( ! is_array( $social_link ) || ! isset( $social_link['icon_type'] ) ) {
			continue;
		}

		$icon_type = sanitize_key( (string) $social_link['icon_type'] );

		if ( 'fontawesome' === $icon_type && '' !== trim( isset( $social_link['fa_class'] ) ? (string) $social_link['fa_class'] : '' ) ) {
			$needs_fontawesome = true;
		}

		if ( 'dashicons' === $icon_type && '' !== trim( isset( $social_link['dashicon'] ) ? (string) $social_link['dashicon'] : '' ) ) {
			$needs_dashicons = true;
		}

		if ( $needs_fontawesome && $needs_dashicons ) {
			return;
		}
	}
}

/**
 * Collect builder link-icon asset needs from raw post meta.
 *
 * ACF stores each link icon control under a shared meta-key prefix. Reading
 * those small scalar values avoids formatting every builder row solely to
 * decide whether Font Awesome or Dashicons should enqueue.
 *
 * @param int  $post_id Post ID.
 * @param bool $needs_fontawesome Whether Font Awesome is needed.
 * @param bool $needs_dashicons Whether Dashicons are needed.
 * @return void
 */
function mrn_base_stack_collect_builder_link_icon_asset_needs_from_post_meta( $post_id, &$needs_fontawesome, &$needs_dashicons ) {
	$post_id = absint( $post_id );
	if ( $post_id < 1 || ! function_exists( 'get_post_meta' ) ) {
		return;
	}

	$post_meta = get_post_meta( $post_id, '', false );
	if ( ! is_array( $post_meta ) ) {
		return;
	}

	$icon_records = array();
	foreach ( $post_meta as $meta_key => $meta_values ) {
		if ( ! is_string( $meta_key ) || '' === $meta_key || '_' === $meta_key[0] || ! is_array( $meta_values ) ) {
			continue;
		}

		if ( ! preg_match( '/^(.*)link_icon_(source|fa_class|dashicon)$/', $meta_key, $matches ) ) {
			continue;
		}

		$value = reset( $meta_values );
		if ( ! is_scalar( $value ) ) {
			continue;
		}

		$icon_records[ $matches[1] ][ $matches[2] ] = trim( (string) $value );
	}

	foreach ( $icon_records as $record ) {
		$source   = isset( $record['source'] ) ? sanitize_key( $record['source'] ) : '';
		$fa_class = isset( $record['fa_class'] ) ? trim( $record['fa_class'] ) : '';
		$dashicon = isset( $record['dashicon'] ) ? trim( $record['dashicon'] ) : '';

		if ( ( '' === $source || 'fontawesome' === $source ) && '' !== $fa_class ) {
			$needs_fontawesome = true;
		}
		if ( ( '' === $source || 'dashicons' === $source ) && '' !== $dashicon && 'dashicons' !== strtolower( $dashicon ) ) {
			$needs_dashicons = true;
		}

		if ( $needs_fontawesome && $needs_dashicons ) {
			return;
		}
	}
}

/**
 * Enqueue conditional layout styles for a post.
 *
 * @param int $post_id Post ID.
 * @return void
 */
function mrn_base_stack_enqueue_layout_styles_for_post( $post_id ) {
	$style_keys = mrn_base_stack_get_layout_style_keys_for_post( $post_id );

	if ( empty( $style_keys ) ) {
		return;
	}

	$manifest = mrn_base_stack_get_layout_style_manifest();

	foreach ( array_keys( $style_keys ) as $style_key ) {
		if ( empty( $manifest[ $style_key ]['handle'] ) || empty( $manifest[ $style_key ]['path'] ) ) {
			continue;
		}

		$relative_path = ltrim( (string) $manifest[ $style_key ]['path'], '/' );
		$file_path     = trailingslashit( get_template_directory() ) . $relative_path;

		if ( ! file_exists( $file_path ) ) {
			continue;
		}

		$version = _S_VERSION;
		$mtime   = filemtime( $file_path );

		if ( false !== $mtime ) {
			$version .= '.' . $mtime;
		}

		wp_enqueue_style(
			(string) $manifest[ $style_key ]['handle'],
			trailingslashit( get_template_directory_uri() ) . $relative_path,
			array( 'mrn-base-stack-style' ),
			$version
		);
	}
}
