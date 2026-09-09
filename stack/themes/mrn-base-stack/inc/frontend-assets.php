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
 * @param bool  $should_render     Whether the social slot will render.
 * @param mixed $social_links      Social link rows from Config Helper.
 * @param bool  $needs_fontawesome Whether Font Awesome is needed.
 * @param bool  $needs_dashicons    Whether Dashicons is needed.
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

/**
 * Get the default front-end component requirement flags.
 *
 * @return array<string, bool>
 */
function mrn_base_stack_get_default_front_end_component_needs() {
	return array(
		'deferred_media' => false,
		'faq'            => false,
		'gallery'        => false,
		'motion'         => false,
		'slider'         => false,
		'tabs'           => false,
		'video_modal'    => false,
	);
}

/**
 * Get the component flags equivalent to the pre-1.3.3 shared runtime bundle.
 *
 * Gallery behavior was historically loaded separately on gallery singles, so
 * it is not part of this compatibility set.
 *
 * @return array<string, bool>
 */
function mrn_base_stack_get_legacy_front_end_component_needs() {
	$needs = mrn_base_stack_get_default_front_end_component_needs();

	foreach ( array( 'deferred_media', 'faq', 'motion', 'slider', 'tabs', 'video_modal' ) as $component ) {
		$needs[ $component ] = true;
	}

	return $needs;
}

/**
 * Mark one front-end component as required.
 *
 * @param array<string, bool> $needs Component requirement flags.
 * @param string              $component Component key.
 * @return array<string, bool>
 */
function mrn_base_stack_require_front_end_component( array $needs, $component ) {
	$component = sanitize_key( (string) $component );
	if ( '' !== $component ) {
		$needs[ $component ] = true;
	}

	return $needs;
}

/**
 * Get a scalar value from the raw post-meta map.
 *
 * @param array<string, mixed> $post_meta Raw post meta.
 * @param string               $meta_key Meta key.
 * @return string
 */
function mrn_base_stack_get_front_end_meta_scalar( array $post_meta, $meta_key ) {
	if ( empty( $post_meta[ $meta_key ] ) || ! is_array( $post_meta[ $meta_key ] ) ) {
		return '';
	}

	$value = reset( $post_meta[ $meta_key ] );
	return is_scalar( $value ) ? trim( (string) $value ) : '';
}

/**
 * Determine whether a raw saved value is enabled or meaningfully populated.
 *
 * @param mixed $value Raw value.
 * @return bool
 */
function mrn_base_stack_front_end_meta_value_is_enabled( $value ) {
	$value = function_exists( 'maybe_unserialize' ) ? maybe_unserialize( $value ) : $value;

	if ( is_array( $value ) ) {
		return ! empty( $value );
	}

	if ( ! is_scalar( $value ) ) {
		return false;
	}

	$value = strtolower( trim( (string) $value ) );
	return ! in_array( $value, array( '', '0', 'false', 'off', 'none' ), true );
}

/**
 * Add component requirements found in already-authored HTML or shortcode text.
 *
 * @param string              $content Authored content.
 * @param array<string, bool> $needs Component requirement flags.
 * @return array<string, bool>
 */
function mrn_base_stack_collect_front_end_component_needs_from_content( $content, array $needs ) {
	$content = (string) $content;
	$markers = array(
		'deferred_media' => array( 'data-video-src' ),
		'faq'            => array( 'mrn-faq__item' ),
		'gallery'        => array( 'data-gallery-root' ),
		'motion'         => array( 'data-mrn-motion-effect', 'data-mrn-surface' ),
		'slider'         => array( 'mrn-splide' ),
		'tabs'           => array( 'data-mrn-tabbed-layout' ),
		'video_modal'    => array( 'mrn-video-row__trigger', 'data-glightbox' ),
	);

	foreach ( $markers as $component => $component_markers ) {
		foreach ( $component_markers as $marker ) {
			if ( false !== stripos( $content, $marker ) ) {
				$needs[ $component ] = true;
				break;
			}
		}
	}

	return $needs;
}

/**
 * Collect reusable-block requirements from mrn_block shortcodes.
 *
 * Unresolvable legacy shortcodes retain the former complete runtime as a safe
 * compatibility fallback. Resolved blocks are inspected recursively.
 *
 * @param string              $content Authored content.
 * @param array<string, bool> $needs Component requirement flags.
 * @param array<int, bool>    $visited_post_ids Recursion guard.
 * @return array<string, bool>
 */
function mrn_base_stack_collect_reusable_shortcode_component_needs( $content, array $needs, array &$visited_post_ids ) {
	if ( false === stripos( (string) $content, '[mrn_block' ) ) {
		return $needs;
	}

	$matched = preg_match_all( '/\[mrn_block\b([^\]]*)\]/i', (string) $content, $matches );
	if ( ! $matched || empty( $matches[1] ) ) {
		return mrn_base_stack_get_legacy_front_end_component_needs();
	}

	foreach ( $matches[1] as $attribute_text ) {
		$attributes = function_exists( 'shortcode_parse_atts' ) ? shortcode_parse_atts( $attribute_text ) : array();
		$identifier = '';

		if ( is_array( $attributes ) && ! empty( $attributes['id'] ) ) {
			$identifier = absint( $attributes['id'] );
		} elseif ( is_array( $attributes ) && ! empty( $attributes['slug'] ) ) {
			$identifier = sanitize_title( (string) $attributes['slug'] );
		}

		$block = null;
		if ( '' !== $identifier && function_exists( 'mrn_rbl_get_block_post' ) ) {
			$block = mrn_rbl_get_block_post( $identifier );
		} elseif ( is_int( $identifier ) && $identifier > 0 && function_exists( 'get_post' ) ) {
			$block = get_post( $identifier );
		}

		if ( is_object( $block ) && ! empty( $block->ID ) ) {
			$needs = mrn_base_stack_collect_front_end_component_needs_for_post( (int) $block->ID, $needs, $visited_post_ids );
			continue;
		}

		// Unknown legacy renderers may emit any of the historical runtime features.
		$needs = mrn_base_stack_get_legacy_front_end_component_needs();
	}

	return $needs;
}

/**
 * Collect front-end component requirements for one post and referenced blocks.
 *
 * @param int                 $post_id Post ID.
 * @param array<string, bool> $needs Component requirement flags.
 * @param array<int, bool>    $visited_post_ids Recursion guard.
 * @return array<string, bool>
 */
function mrn_base_stack_collect_front_end_component_needs_for_post( $post_id, array $needs, array &$visited_post_ids ) {
	$post_id = absint( $post_id );
	if ( $post_id < 1 || isset( $visited_post_ids[ $post_id ] ) ) {
		return $needs;
	}

	$visited_post_ids[ $post_id ] = true;
	$post_content                 = function_exists( 'get_post_field' ) ? (string) get_post_field( 'post_content', $post_id ) : '';
	$needs                        = mrn_base_stack_collect_front_end_component_needs_from_content( $post_content, $needs );
	$needs                        = mrn_base_stack_collect_reusable_shortcode_component_needs( $post_content, $needs, $visited_post_ids );
	$post_meta                    = function_exists( 'get_post_meta' ) ? get_post_meta( $post_id, '', false ) : array();

	if ( ! is_array( $post_meta ) ) {
		return $needs;
	}

	foreach ( $post_meta as $meta_key => $meta_values ) {
		if ( ! is_string( $meta_key ) || '' === $meta_key || '_' === $meta_key[0] || ! is_array( $meta_values ) ) {
			continue;
		}

		foreach ( $meta_values as $meta_value ) {
			$needs = mrn_base_stack_collect_front_end_component_needs_from_content( is_scalar( $meta_value ) ? (string) $meta_value : '', $needs );
		}

		if ( preg_match( '/_motion_settings_enabled$/', $meta_key ) ) {
			foreach ( $meta_values as $meta_value ) {
				if ( mrn_base_stack_front_end_meta_value_is_enabled( $meta_value ) ) {
					$needs['motion'] = true;
					break;
				}
			}
		}

		if ( preg_match( '/_(?:background_video|background_video_upload)$/', $meta_key ) ) {
			foreach ( $meta_values as $meta_value ) {
				if ( mrn_base_stack_front_end_meta_value_is_enabled( $meta_value ) ) {
					$needs['deferred_media'] = true;
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

			foreach ( $layout_names as $row_index => $layout_name ) {
				if ( ! is_scalar( $layout_name ) ) {
					continue;
				}

				$layout_name = sanitize_key( (string) $layout_name );
				$row_prefix  = $meta_key . '_' . (int) $row_index . '_';

				if ( 'slider' === $layout_name && mrn_base_stack_front_end_meta_value_is_enabled( mrn_base_stack_get_front_end_meta_scalar( $post_meta, $row_prefix . 'slider_items' ) ) ) {
					$needs['slider'] = true;
				} elseif (
					'logos' === $layout_name
					&& 'slider' === sanitize_key( mrn_base_stack_get_front_end_meta_scalar( $post_meta, $row_prefix . 'display_mode' ) )
					&& mrn_base_stack_front_end_meta_value_is_enabled( mrn_base_stack_get_front_end_meta_scalar( $post_meta, $row_prefix . 'logo_items' ) )
				) {
					$needs['slider'] = true;
				} elseif ( 'tabbed_layout' === $layout_name && mrn_base_stack_front_end_meta_value_is_enabled( mrn_base_stack_get_front_end_meta_scalar( $post_meta, $row_prefix . 'tabs' ) ) ) {
					$needs['tabs'] = true;
				} elseif ( 'video' === $layout_name ) {
					$has_video = mrn_base_stack_front_end_meta_value_is_enabled( mrn_base_stack_get_front_end_meta_scalar( $post_meta, $row_prefix . 'video_remote' ) )
						|| mrn_base_stack_front_end_meta_value_is_enabled( mrn_base_stack_get_front_end_meta_scalar( $post_meta, $row_prefix . 'video_upload' ) );

					if ( $has_video ) {
						$video_mode               = sanitize_key( mrn_base_stack_get_front_end_meta_scalar( $post_meta, $row_prefix . 'video_display_mode' ) );
						$has_thumbnail            = mrn_base_stack_front_end_meta_value_is_enabled( mrn_base_stack_get_front_end_meta_scalar( $post_meta, $row_prefix . 'video_thumbnail' ) );
						$component_name           = 'modal' === $video_mode && $has_thumbnail ? 'video_modal' : 'deferred_media';
						$needs[ $component_name ] = true;
					}
				} elseif ( in_array( $layout_name, array( 'faq', 'faq_block' ), true ) ) {
					$needs['faq'] = true;
				} elseif ( 'content_lists' === $layout_name && 'testimonial' === sanitize_key( mrn_base_stack_get_front_end_meta_scalar( $post_meta, $row_prefix . 'list_post_type' ) ) ) {
					// Content-list items can include deferred testimonial videos.
					$needs['deferred_media'] = true;
				} elseif ( 'reusable_block' === $layout_name ) {
					$block_id = absint( mrn_base_stack_get_front_end_meta_scalar( $post_meta, $row_prefix . 'block' ) );
					if ( $block_id > 0 ) {
						$needs = mrn_base_stack_collect_front_end_component_needs_for_post( $block_id, $needs, $visited_post_ids );
					}
				}

				if ( mrn_base_stack_front_end_meta_value_is_enabled( mrn_base_stack_get_front_end_meta_scalar( $post_meta, $row_prefix . 'motion_settings_enabled' ) ) ) {
					$needs['motion'] = true;
				}
			}
		}
	}

	$post_type = function_exists( 'get_post_type' ) ? sanitize_key( (string) get_post_type( $post_id ) ) : '';
	if ( 'mrn_reusable_faq' === $post_type ) {
		$needs['faq'] = true;
	} elseif (
		'mrn_reusable_partner' === $post_type
		&& 'slider' === sanitize_key( mrn_base_stack_get_front_end_meta_scalar( $post_meta, 'display_mode' ) )
		&& mrn_base_stack_front_end_meta_value_is_enabled( mrn_base_stack_get_front_end_meta_scalar( $post_meta, 'logo_items' ) )
	) {
		$needs['slider'] = true;
	}

	return $needs;
}

/**
 * Get normalized component requirements for a singular post.
 *
 * @param int $post_id Post ID.
 * @return array<string, bool>
 */
function mrn_base_stack_get_front_end_component_needs_for_post( $post_id ) {
	$needs            = mrn_base_stack_get_default_front_end_component_needs();
	$visited_post_ids = array();
	$needs            = mrn_base_stack_collect_front_end_component_needs_for_post( $post_id, $needs, $visited_post_ids );

	/**
	 * Filter the component requirements discovered for a singular post.
	 *
	 * Extensions that render Stack-compatible markup can declare only the
	 * components they need instead of forcing the complete historical bundle.
	 *
	 * @param array<string, bool> $needs Component requirement flags.
	 * @param int                 $post_id Post ID.
	 */
	$needs = apply_filters( 'mrn_base_stack_front_end_component_needs', $needs, absint( $post_id ) );

	return is_array( $needs ) ? $needs : mrn_base_stack_get_default_front_end_component_needs();
}

/**
 * Get the registered component asset manifest.
 *
 * @return array<string, array<string, mixed>>
 */
function mrn_base_stack_get_front_end_component_asset_manifest() {
	$manifest = array(
		'deferred_media' => array(
			'scripts' => array( 'mrn-base-stack-front-end-deferred-media' ),
		),
		'faq'            => array(
			'scripts' => array( 'mrn-base-stack-front-end-faq' ),
		),
		'gallery'        => array(
			'styles'  => array( 'mrn-base-stack-glightbox' ),
			'scripts' => array( 'mrn-base-stack-front-end-gallery' ),
		),
		'motion'         => array(
			'scripts' => array( 'mrn-base-stack-front-end-effects' ),
		),
		'slider'         => array(
			'styles'  => array( 'mrn-base-stack-splide' ),
			'scripts' => array( 'mrn-base-stack-front-end-slider' ),
		),
		'tabs'           => array(
			'styles'  => array( 'mrn-base-stack-splide' ),
			'scripts' => array( 'mrn-base-stack-front-end-tabs' ),
		),
		'video_modal'    => array(
			'styles'  => array( 'mrn-base-stack-glightbox' ),
			'scripts' => array( 'mrn-base-stack-front-end-video-modal' ),
		),
	);

	/**
	 * Filter the mapping from component keys to registered asset handles.
	 *
	 * @param array<string, array<string, mixed>> $manifest Component manifest.
	 */
	return (array) apply_filters( 'mrn_base_stack_front_end_component_asset_manifest', $manifest );
}

/**
 * Register all shared component assets without enqueueing them.
 *
 * @return void
 */
function mrn_base_stack_register_front_end_component_assets() {
	$theme_uri = trailingslashit( get_template_directory_uri() );
	$theme_dir = trailingslashit( get_template_directory() );
	$version   = static function ( $relative_path, $fallback ) use ( $theme_dir ) {
		$file_path = $theme_dir . ltrim( $relative_path, '/' );
		$mtime     = file_exists( $file_path ) ? filemtime( $file_path ) : false;
		return false !== $mtime ? _S_VERSION . '-' . (string) $mtime : $fallback;
	};

	wp_register_style( 'mrn-base-stack-splide', $theme_uri . 'css/vendor/splide.min.css', array(), '4.1.4' );
	wp_register_style( 'mrn-base-stack-glightbox', $theme_uri . 'css/vendor/glightbox.min.css', array(), '3.3.1' );

	wp_register_script( 'mrn-base-stack-motion', $theme_uri . 'js/vendor/motion.js', array(), '12.38.0', true );
	wp_register_script( 'mrn-base-stack-splide', $theme_uri . 'js/vendor/splide.min.js', array(), '4.1.4', true );
	wp_register_script( 'mrn-base-stack-glightbox', $theme_uri . 'js/vendor/glightbox.min.js', array(), '3.3.1', true );
	wp_register_script( 'mrn-base-stack-front-end-effects', $theme_uri . 'js/front-end-effects.js', array( 'mrn-base-stack-motion' ), $version( 'js/front-end-effects.js', _S_VERSION ), true );
	wp_register_script( 'mrn-base-stack-front-end-slider', $theme_uri . 'js/front-end-slider.js', array( 'mrn-base-stack-splide' ), $version( 'js/front-end-slider.js', _S_VERSION ), true );
	wp_register_script( 'mrn-base-stack-front-end-tabs', $theme_uri . 'js/front-end-tabs.js', array( 'mrn-base-stack-splide' ), $version( 'js/front-end-tabs.js', _S_VERSION ), true );
	wp_register_script( 'mrn-base-stack-front-end-video-modal', $theme_uri . 'js/front-end-video-modal.js', array( 'mrn-base-stack-glightbox' ), $version( 'js/front-end-video-modal.js', _S_VERSION ), true );
	wp_register_script( 'mrn-base-stack-front-end-gallery', $theme_uri . 'js/front-end-gallery.js', array( 'mrn-base-stack-glightbox' ), $version( 'js/front-end-gallery.js', _S_VERSION ), true );
	wp_register_script( 'mrn-base-stack-front-end-deferred-media', $theme_uri . 'js/front-end-deferred-media.js', array(), $version( 'js/front-end-deferred-media.js', _S_VERSION ), true );
	wp_register_script( 'mrn-base-stack-front-end-faq', $theme_uri . 'js/front-end-faq.js', array(), $version( 'js/front-end-faq.js', _S_VERSION ), true );
}

/**
 * Enqueue only the assets required by rendered Stack components.
 *
 * @param array<string, bool> $needs Component requirement flags.
 * @return void
 */
function mrn_base_stack_enqueue_front_end_component_assets( array $needs ) {
	mrn_base_stack_register_front_end_component_assets();
	$manifest = mrn_base_stack_get_front_end_component_asset_manifest();

	foreach ( $needs as $component => $is_required ) {
		if ( ! $is_required || empty( $manifest[ $component ] ) || ! is_array( $manifest[ $component ] ) ) {
			continue;
		}

		foreach ( array( 'styles', 'scripts' ) as $asset_type ) {
			if ( empty( $manifest[ $component ][ $asset_type ] ) || ! is_array( $manifest[ $component ][ $asset_type ] ) ) {
				continue;
			}

			foreach ( $manifest[ $component ][ $asset_type ] as $handle ) {
				if ( 'styles' === $asset_type ) {
					wp_enqueue_style( (string) $handle );
				} else {
					wp_enqueue_script( (string) $handle );
				}
			}
		}
	}
}

/**
 * Add one explicitly declared critical CSS-backed image to WordPress preloads.
 *
 * Child themes may return `array( 'url' => ..., 'type' => 'image/svg+xml' )`
 * from `mrn_base_stack_critical_css_image`. Attachment IDs are also supported.
 * The default is deliberately empty so background images are never guessed.
 *
 * @param array<int, array<string, mixed>> $preloads Existing preload resources.
 * @return array<int, array<string, mixed>>
 */
function mrn_base_stack_add_critical_css_image_preload( $preloads ) {
	$preloads    = is_array( $preloads ) ? $preloads : array();
	$declaration = apply_filters( 'mrn_base_stack_critical_css_image', array() );

	if ( ! is_array( $declaration ) || empty( $declaration ) ) {
		return $preloads;
	}

	$attachment_id = ! empty( $declaration['attachment_id'] ) ? absint( $declaration['attachment_id'] ) : 0;
	$size          = ! empty( $declaration['size'] ) ? $declaration['size'] : 'full';
	$url           = ! empty( $declaration['url'] ) && is_scalar( $declaration['url'] ) ? esc_url_raw( (string) $declaration['url'] ) : '';
	$type          = ! empty( $declaration['type'] ) && is_scalar( $declaration['type'] ) ? sanitize_mime_type( (string) $declaration['type'] ) : '';

	if ( $attachment_id > 0 ) {
		$attachment_url = wp_get_attachment_image_url( $attachment_id, $size );
		if ( is_string( $attachment_url ) && '' !== $attachment_url ) {
			$url = esc_url_raw( $attachment_url );
		}
		if ( '' === $type ) {
			$type = sanitize_mime_type( (string) get_post_mime_type( $attachment_id ) );
		}
	}

	if ( '' === $url || 0 !== strpos( $type, 'image/' ) ) {
		return $preloads;
	}

	foreach ( $preloads as $preload ) {
		if ( is_array( $preload ) && isset( $preload['href'] ) && $url === (string) $preload['href'] ) {
			return $preloads;
		}
	}

	$resource = array(
		'href'          => $url,
		'as'            => 'image',
		'type'          => $type,
		'fetchpriority' => 'high',
	);

	foreach ( array( 'media', 'imagesrcset', 'imagesizes' ) as $attribute ) {
		if ( ! empty( $declaration[ $attribute ] ) && is_scalar( $declaration[ $attribute ] ) ) {
			$resource[ $attribute ] = trim( (string) $declaration[ $attribute ] );
		}
	}

	$preloads[] = $resource;
	return $preloads;
}
add_filter( 'wp_preload_resources', 'mrn_base_stack_add_critical_css_image_preload' );
