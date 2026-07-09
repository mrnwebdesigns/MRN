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
 * @param mixed                $rows Builder rows.
 * @param array<string, bool>  $style_keys Collected style keys.
 * @param string               $field_context Current ACF field context.
 * @return void
 */
function mrn_base_stack_collect_layout_style_keys_from_rows( $rows, array &$style_keys, $field_context = '' ) {
	if ( ! is_array( $rows ) ) {
		return;
	}

	foreach ( $rows as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}

		$layout       = isset( $row['acf_fc_layout'] ) ? sanitize_key( (string) $row['acf_fc_layout'] ) : '';
		$style_layout = $layout;

		if ( '' !== $layout ) {
			if ( 'page_hero_rows' === $field_context && 'basic' === $layout ) {
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
				mrn_base_stack_collect_layout_style_keys_from_rows( $row[ $child_key ], $style_keys, $child_key );
			}
		}

		foreach ( array( 'tabs', 'tab_items' ) as $tabs_key ) {
			if ( empty( $row[ $tabs_key ] ) || ! is_array( $row[ $tabs_key ] ) ) {
				continue;
			}

			foreach ( $row[ $tabs_key ] as $tab_item ) {
				if ( is_array( $tab_item ) && ! empty( $tab_item['panel_rows'] ) && is_array( $tab_item['panel_rows'] ) ) {
					mrn_base_stack_collect_layout_style_keys_from_rows( $tab_item['panel_rows'], $style_keys, 'panel_rows' );
				}
			}
		}
	}
}

/**
 * Get conditional layout stylesheet keys for a post.
 *
 * @param int $post_id Post ID.
 * @return array<string, bool>
 */
function mrn_base_stack_get_layout_style_keys_for_post( $post_id ) {
	$post_id = (int) $post_id;

	if ( ! $post_id || ! function_exists( 'get_field' ) ) {
		return array();
	}

	$style_keys = array();
	$field_names = array(
		'page_hero_rows',
		'page_content_rows',
		'page_after_content_rows',
		'page_sidebar_rows',
	);

	foreach ( $field_names as $field_name ) {
		mrn_base_stack_collect_layout_style_keys_from_rows( get_field( $field_name, $post_id ), $style_keys, $field_name );
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
