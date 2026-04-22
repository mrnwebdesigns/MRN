<?php
/**
 * Builder layout allowlist controls for classic editor screens.
 *
 * @package mrn-base-stack
 */

/**
 * Get builder flexible-content fields that use per-entry allowlists.
 *
 * @return array<string, array<string, string>>
 */
function mrn_base_stack_get_builder_layout_allowlist_targets() {
	return array(
		'page_hero_rows' => array(
			'field_key' => 'field_mrn_page_hero_rows',
			'group_key' => 'group_mrn_hero_builder',
			'label'     => 'Hero',
		),
		'page_content_rows' => array(
			'field_key' => 'field_mrn_page_content_rows',
			'group_key' => 'group_mrn_content_builder',
			'label'     => 'Content',
		),
		'page_after_content_rows' => array(
			'field_key' => 'field_mrn_page_after_content_rows',
			'group_key' => 'group_mrn_after_content_builder',
			'label'     => 'After Content',
		),
		'page_sidebar_rows' => array(
			'field_key' => 'field_mrn_sidebar_rows',
			'group_key' => 'group_mrn_singular_sidebar',
			'label'     => 'Sidebars',
		),
	);
}

/**
 * Determine whether an allowlist target should be shown for a given post type.
 *
 * @param string $field_name Flexible-content field name.
 * @param string $post_type  Current post type.
 * @return bool
 */
function mrn_base_stack_should_render_builder_layout_allowlist_target( $field_name, $post_type ) {
	$field_name = sanitize_key( (string) $field_name );
	$post_type  = sanitize_key( (string) $post_type );

	if ( 'page_sidebar_rows' !== $field_name ) {
		return true;
	}

	if ( ! function_exists( 'mrn_base_stack_get_sidebar_supported_post_types' ) ) {
		return true;
	}

	return in_array( $post_type, mrn_base_stack_get_sidebar_supported_post_types(), true );
}

/**
 * Get the registered ACF hooks used by builder layout allowlist filtering.
 *
 * @return array<int, string>
 */
function mrn_base_stack_get_builder_layout_allowlist_filter_hooks() {
	return array(
		'acf/load_field/key=field_mrn_page_hero_rows',
		'acf/load_field/key=field_mrn_page_content_rows',
		'acf/load_field/key=field_mrn_page_after_content_rows',
		'acf/load_field/key=field_mrn_sidebar_rows',
		'acf/prepare_field/key=field_mrn_page_hero_rows',
		'acf/prepare_field/key=field_mrn_page_content_rows',
		'acf/prepare_field/key=field_mrn_page_after_content_rows',
		'acf/prepare_field/key=field_mrn_sidebar_rows',
	);
}

/**
 * Run a callback with allowlist ACF filters temporarily disabled.
 *
 * @param callable $callback Callback to execute.
 * @return mixed
 */
function mrn_base_stack_run_without_builder_layout_allowlist_filters( callable $callback ) {
	$hooks = mrn_base_stack_get_builder_layout_allowlist_filter_hooks();

	foreach ( $hooks as $hook_name ) {
		remove_filter( $hook_name, 'mrn_base_stack_filter_builder_layout_allowlist_field_layouts', 20 );
	}

	try {
		return $callback();
	} finally {
		foreach ( $hooks as $hook_name ) {
			add_filter( $hook_name, 'mrn_base_stack_filter_builder_layout_allowlist_field_layouts', 20 );
		}
	}
}

/**
 * Get the post-meta key used for per-entry builder layout allowlists.
 *
 * @return string
 */
function mrn_base_stack_get_builder_layout_allowlist_meta_key() {
	return '_mrn_builder_layout_allowlist';
}

/**
 * Get the post-meta key used to mark explicit allowlist saves.
 *
 * @return string
 */
function mrn_base_stack_get_builder_layout_allowlist_initialized_meta_key() {
	return '_mrn_builder_layout_allowlist_initialized';
}

/**
 * Build sanitized allowlist payload from submitted form data.
 *
 * @param array<string, mixed> $input Raw allowlist checkbox input.
 * @param array<string, mixed> $catalog_input Raw catalog helper input.
 * @return array<string, array<int, string>>
 */
function mrn_base_stack_build_sanitized_builder_layout_allowlist_payload( array $input, array $catalog_input ) {
	$targets    = mrn_base_stack_get_builder_layout_allowlist_targets();
	$allowlists = array();

	foreach ( $targets as $field_name => $target ) {
		$catalog            = mrn_base_stack_get_builder_layout_allowlist_catalog_from_field( mrn_base_stack_get_builder_layout_allowlist_field_definition( $field_name ) );
		$configurable_names = mrn_base_stack_get_builder_layout_allowlist_configurable_names( $catalog );
		if ( isset( $catalog_input[ $field_name ] ) ) {
			$posted_catalog_names = array_values(
				array_unique(
					array_filter(
						array_map(
							'sanitize_key',
							explode( ',', (string) $catalog_input[ $field_name ] )
						)
					)
				)
			);

			if ( ! empty( $posted_catalog_names ) ) {
				$configurable_names = $posted_catalog_names;
			}
		}

		$selected = isset( $input[ $field_name ] ) && is_array( $input[ $field_name ] )
			? array_filter(
				array_map(
					'sanitize_key',
					$input[ $field_name ]
				)
			)
			: array();

		$allowlists[ $field_name ] = array_values(
			array_unique(
				array_intersect(
					$selected,
					$configurable_names
				)
			)
		);
	}

	return $allowlists;
}

/**
 * Parse a WordPress post reference into a numeric post ID.
 *
 * @param mixed $reference Raw post reference.
 * @return int
 */
function mrn_base_stack_parse_builder_layout_allowlist_post_id_reference( $reference ) {
	if ( is_numeric( $reference ) ) {
		return absint( $reference );
	}

	if ( is_string( $reference ) && preg_match( '/^post_(\d+)$/', $reference, $matches ) ) {
		return absint( $matches[1] );
	}

	return 0;
}

/**
 * Resolve the active editor post ID for ACF field allowlist filtering.
 *
 * @return int
 */
function mrn_base_stack_get_builder_layout_allowlist_post_id() {
	$post_id = 0;

	if ( function_exists( 'acf_get_form_data' ) ) {
		$post_id = mrn_base_stack_parse_builder_layout_allowlist_post_id_reference( acf_get_form_data( 'post_id' ) );
	}

	$post_reference = filter_input( INPUT_POST, 'post_ID', FILTER_SANITIZE_NUMBER_INT );
	if ( $post_id < 1 && is_string( $post_reference ) && '' !== $post_reference ) {
		$post_id = mrn_base_stack_parse_builder_layout_allowlist_post_id_reference( $post_reference );
	}

	$get_post_reference = filter_input( INPUT_GET, 'post', FILTER_SANITIZE_NUMBER_INT );
	if ( $post_id < 1 && is_string( $get_post_reference ) && '' !== $get_post_reference ) {
		$post_id = mrn_base_stack_parse_builder_layout_allowlist_post_id_reference( $get_post_reference );
	}

	if ( $post_id < 1 ) {
		$post = get_post();
		if ( $post instanceof WP_Post ) {
			$post_id = (int) $post->ID;
		}
	}

	return max( 0, (int) $post_id );
}

/**
 * Resolve the current admin post type for builder allowlist usage.
 *
 * @param int $post_id Optional post ID.
 * @return string
 */
function mrn_base_stack_get_builder_layout_allowlist_context_post_type( $post_id = 0 ) {
	$post_type = '';
	$screen    = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

	if ( $screen instanceof WP_Screen ) {
		$post_type = sanitize_key( (string) $screen->post_type );
	}

	if ( '' === $post_type && $post_id > 0 ) {
		$post_type = sanitize_key( (string) get_post_type( $post_id ) );
	}

	if ( '' === $post_type && isset( $_REQUEST['post_type'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only context lookup.
		$post_type = sanitize_key( (string) wp_unslash( $_REQUEST['post_type'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only context lookup.
	}

	if ( '' === $post_type && isset( $_REQUEST['post'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only context lookup.
		$post_type = sanitize_key( (string) get_post_type( absint( wp_unslash( $_REQUEST['post'] ) ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only context lookup.
	}

	return $post_type;
}

/**
 * Determine whether the current request should apply builder allowlist logic.
 *
 * @param int $post_id Optional post ID.
 * @return bool
 */
function mrn_base_stack_is_builder_layout_allowlist_context( $post_id = 0 ) {
	if ( ! is_admin() ) {
		return false;
	}

	$post_type = mrn_base_stack_get_builder_layout_allowlist_context_post_type( $post_id );
	if ( '' === $post_type ) {
		if ( isset( $GLOBALS['pagenow'] ) && 'post-new.php' === $GLOBALS['pagenow'] ) {
			$post_type = 'post';
		}
	}

	if ( '' === $post_type ) {
		return false;
	}

	return in_array( $post_type, mrn_base_stack_get_singular_shell_post_types(), true );
}

/**
 * Clear cached top-level builder field definitions for editor screens.
 *
 * This prevents pre-screen ACF cache warm-ups from locking in stale layout
 * sets before per-entry allowlist filtering runs.
 *
 * @param WP_Screen $screen Current screen.
 * @return void
 */
function mrn_base_stack_reset_builder_layout_allowlist_field_cache( $screen ) {
	if ( ! $screen instanceof WP_Screen ) {
		return;
	}

	if ( ! in_array( $screen->base, array( 'post', 'post-new' ), true ) ) {
		return;
	}

	if ( ! in_array( sanitize_key( (string) $screen->post_type ), mrn_base_stack_get_singular_shell_post_types(), true ) ) {
		return;
	}

	if ( ! function_exists( 'acf_get_store' ) ) {
		return;
	}

	$store = acf_get_store( 'fields' );
	if ( ! is_object( $store ) || ! method_exists( $store, 'remove' ) ) {
		return;
	}

	foreach ( mrn_base_stack_get_builder_layout_allowlist_targets() as $target ) {
		$field_key = isset( $target['field_key'] ) ? (string) $target['field_key'] : '';
		if ( '' !== $field_key ) {
			$store->remove( $field_key );
		}
	}
}
add_action( 'current_screen', 'mrn_base_stack_reset_builder_layout_allowlist_field_cache', 20 );

/**
 * Build a catalog of flexible-content layouts from an ACF field definition.
 *
 * @param array<string, mixed> $field Field definition.
 * @return array<string, array<string, mixed>>
 */
function mrn_base_stack_get_builder_layout_allowlist_catalog_from_field( array $field ) {
	if ( empty( $field['layouts'] ) || ! is_array( $field['layouts'] ) ) {
		return array();
	}

	$catalog = array();

	foreach ( $field['layouts'] as $layout ) {
		if ( ! is_array( $layout ) ) {
			continue;
		}

		$name  = isset( $layout['name'] ) ? sanitize_key( (string) $layout['name'] ) : '';
		$label = isset( $layout['label'] ) ? trim( wp_strip_all_tags( (string) $layout['label'] ) ) : '';

		if ( '' === $name ) {
			continue;
		}

		if ( '' === $label ) {
			$label = ucfirst( str_replace( array( '-', '_' ), ' ', $name ) );
		}

		$catalog[ $name ] = array(
			'label'        => $label,
			'layout'       => $layout,
			'is_page_only' => false !== stripos( $label, '(Page Only)' ),
		);
	}

	return $catalog;
}

/**
 * Resolve the unfiltered ACF field definition for a builder allowlist target.
 *
 * @param string $field_name Flexible-content field name.
 * @return array<string, mixed>
 */
function mrn_base_stack_get_builder_layout_allowlist_field_definition( $field_name ) {
	static $cache = array();

	$field_name = sanitize_key( (string) $field_name );
	if ( isset( $cache[ $field_name ] ) ) {
		return $cache[ $field_name ];
	}

	$targets = mrn_base_stack_get_builder_layout_allowlist_targets();
	if ( ! isset( $targets[ $field_name ] ) || ! is_array( $targets[ $field_name ] ) ) {
		$cache[ $field_name ] = array();
		return $cache[ $field_name ];
	}

	$target    = $targets[ $field_name ];
	$field_key = isset( $target['field_key'] ) ? (string) $target['field_key'] : '';
	$group_key = isset( $target['group_key'] ) ? (string) $target['group_key'] : '';

	if ( function_exists( 'acf_get_local_field' ) && '' !== $field_key ) {
		$field = acf_get_local_field( $field_key );
		if ( is_array( $field ) && ! empty( $field['layouts'] ) && is_array( $field['layouts'] ) ) {
			$cache[ $field_name ] = $field;
			return $cache[ $field_name ];
		}
	}

	$resolved_field = mrn_base_stack_run_without_builder_layout_allowlist_filters(
		static function () use ( $group_key, $field_key, $field_name ) {
			if ( function_exists( 'acf_get_fields' ) && '' !== $group_key ) {
				$fields = acf_get_fields( $group_key );
				if ( is_array( $fields ) ) {
					foreach ( $fields as $field ) {
						if ( ! is_array( $field ) ) {
							continue;
						}

						$current_key  = isset( $field['key'] ) ? (string) $field['key'] : '';
						$current_name = isset( $field['name'] ) ? sanitize_key( (string) $field['name'] ) : '';

						if ( $current_key === $field_key || $current_name === $field_name ) {
							return $field;
						}
					}
				}
			}

			if ( function_exists( 'acf_get_field' ) && '' !== $field_key ) {
				$field = acf_get_field( $field_key );
				if ( is_array( $field ) ) {
					return $field;
				}
			}

			return array();
		}
	);

	if ( is_array( $resolved_field ) && ! empty( $resolved_field ) ) {
		$cache[ $field_name ] = $resolved_field;
		return $cache[ $field_name ];
	}

	$cache[ $field_name ] = array();
	return $cache[ $field_name ];
}

/**
 * Get layout names currently used by a flexible-content field on a post.
 *
 * @param int    $post_id Post ID.
 * @param string $field_name Flexible-content field name.
 * @return array<int, string>
 */
function mrn_base_stack_get_builder_layout_allowlist_used_layout_names( $post_id, $field_name ) {
	$post_id    = absint( $post_id );
	$field_name = sanitize_key( (string) $field_name );

	if ( $post_id < 1 || '' === $field_name ) {
		return array();
	}

	$raw_value = get_post_meta( $post_id, $field_name, true );

	// ACF flexible content may store row layouts directly as an array of slugs.
	if ( is_array( $raw_value ) ) {
		$layout_names = array_values(
			array_unique(
				array_filter(
					array_map( 'sanitize_key', $raw_value )
				)
			)
		);

		if ( ! empty( $layout_names ) ) {
			return $layout_names;
		}
	}

	$row_count = absint( $raw_value );
	if ( $row_count < 1 ) {
		return array();
	}

	$layout_names = array();

	for ( $index = 0; $index < $row_count; $index++ ) {
		$layout_name = sanitize_key( (string) get_post_meta( $post_id, $field_name . '_' . $index . '_acf_fc_layout', true ) );
		if ( '' !== $layout_name ) {
			$layout_names[] = $layout_name;
		}
	}

	return array_values( array_unique( $layout_names ) );
}

/**
 * Get configurable layout names from a field catalog.
 *
 * Configurable names exclude internal page-only rows.
 *
 * @param array<string, array<string, mixed>> $catalog Field catalog.
 * @return array<int, string>
 */
function mrn_base_stack_get_builder_layout_allowlist_configurable_names( array $catalog ) {
	$names = array();

	foreach ( $catalog as $name => $layout_meta ) {
		$name = sanitize_key( (string) $name );
		if ( '' === $name ) {
			continue;
		}

		if ( ! empty( $layout_meta['is_page_only'] ) ) {
			continue;
		}

		$names[] = $name;
	}

	$names                  = array_values( array_unique( $names ) );
	$removed_names          = apply_filters( 'mrn_base_stack_builder_layout_allowlist_removed_layout_names', array( 'body_text' ) );
	$removed_names          = is_array( $removed_names )
		? array_values(
			array_unique(
				array_filter(
					array_map( 'sanitize_key', $removed_names )
				)
			)
		)
		: array( 'body_text' );
	$sitewide_allowed_names = mrn_base_stack_get_sitewide_allowed_builder_layout_names();

	if ( ! empty( $removed_names ) ) {
		$names = array_values( array_diff( $names, $removed_names ) );
	}

	if ( is_array( $sitewide_allowed_names ) ) {
		$names = array_values( array_intersect( $names, $sitewide_allowed_names ) );
	}

	return $names;
}

/**
 * Get the site-wide allowed builder layout names.
 *
 * Returns `null` when site-wide allow settings are unavailable so callers can
 * skip global gating and behave as before.
 *
 * @return array<int, string>|null
 */
function mrn_base_stack_get_sitewide_allowed_builder_layout_names() {
	if ( ! function_exists( 'mrn_config_helper_get_allowed_builder_layouts' ) ) {
		return null;
	}

	$allowed_names                     = mrn_config_helper_get_allowed_builder_layouts();
	$settings                          = get_option( 'mrn_helper_settings', null );
	$has_saved_allowed_builder_layouts = is_array( $settings ) && array_key_exists( 'allowed_builder_layouts', $settings ) && is_array( $settings['allowed_builder_layouts'] );

	$allowed_names = is_array( $allowed_names )
		? array_values(
			array_unique(
				array_filter(
					array_map( 'sanitize_key', $allowed_names )
				)
			)
		)
		: array();

	/**
	 * Keep sitewide-post handoff stable when Config Helper resolves a partial
	 * runtime list (for example, timing/context during ACF field introspection).
	 * The raw saved option is the canonical admin selection source.
	 */
	if ( $has_saved_allowed_builder_layouts ) {
		$saved_allowed_names = array_values(
			array_unique(
				array_filter(
					array_map( 'sanitize_key', $settings['allowed_builder_layouts'] )
				)
			)
		);

		$allowed_names = array_values(
			array_unique(
				array_merge( $allowed_names, $saved_allowed_names )
			)
		);

		if ( ! empty( $allowed_names ) ) {
			return $allowed_names;
		}

		// Preserve explicit "allow none" saves as an empty allowlist.
		return $saved_allowed_names;
	}

	/*
	 * Default to "all layouts on" when no explicit site-wide allowlist has
	 * been saved. This keeps new/dynamic layouts available by default.
	 */
	return null;
}

/**
 * Get saved allowlist settings for a post.
 *
 * @param int $post_id Post ID.
 * @return array<string, array<int, string>>
 */
function mrn_base_stack_get_builder_layout_allowlist_saved_settings( $post_id ) {
	$post_id = absint( $post_id );
	$targets = mrn_base_stack_get_builder_layout_allowlist_targets();
	$saved   = array();

	foreach ( $targets as $field_name => $target ) {
		$saved[ $field_name ] = array();
	}

	if ( $post_id < 1 ) {
		return $saved;
	}

	$raw = get_post_meta( $post_id, mrn_base_stack_get_builder_layout_allowlist_meta_key(), true );
	if ( ! is_array( $raw ) ) {
		return $saved;
	}

	foreach ( $targets as $field_name => $target ) {
		if ( empty( $raw[ $field_name ] ) || ! is_array( $raw[ $field_name ] ) ) {
			continue;
		}

		$saved[ $field_name ] = array_values(
			array_unique(
				array_filter(
					array_map( 'sanitize_key', $raw[ $field_name ] )
				)
			)
		);
	}

	return $saved;
}

/**
 * Determine whether saved allowlist settings should be used for a post.
 *
 * New auto-draft posts should always start from defaults.
 *
 * @param int $post_id Post ID.
 * @return bool
 */
function mrn_base_stack_builder_layout_allowlist_should_use_saved_settings( $post_id ) {
	$post_id = absint( $post_id );

	if ( $post_id < 1 ) {
		return false;
	}

	$post = get_post( $post_id );
	if ( ! $post instanceof WP_Post ) {
		return false;
	}

	if ( 'auto-draft' !== $post->post_status ) {
		return true;
	}

	return metadata_exists( 'post', $post_id, mrn_base_stack_get_builder_layout_allowlist_initialized_meta_key() );
}

/**
 * Get default layout limits for unsaved allowlists.
 *
 * @return array<string, int>
 */
function mrn_base_stack_get_builder_layout_allowlist_default_limits() {
	$defaults = array(
		'page_hero_rows'          => 4,
		'page_content_rows'       => 8,
		'page_after_content_rows' => 6,
		'page_sidebar_rows'       => 1,
	);
	$limits   = apply_filters( 'mrn_base_stack_builder_layout_allowlist_default_limits', $defaults );

	if ( ! is_array( $limits ) ) {
		return $defaults;
	}

	$normalized = array();

	foreach ( $defaults as $field_name => $fallback ) {
		$normalized[ $field_name ] = isset( $limits[ $field_name ] ) ? max( 0, absint( $limits[ $field_name ] ) ) : $fallback;
	}

	return $normalized;
}

/**
 * Get default allowlist values for a flexible-content field.
 *
 * @param string                              $field_name Flexible-content field name.
 * @param array<string, array<string, mixed>> $catalog Layout catalog.
 * @return array<int, string>
 */
function mrn_base_stack_get_builder_layout_allowlist_default_names( $field_name, array $catalog ) {
	$field_name         = sanitize_key( (string) $field_name );
	$configurable_names = mrn_base_stack_get_builder_layout_allowlist_configurable_names( $catalog );
	$default_map        = array(
		'page_hero_rows'          => array( 'basic', 'two_column_split' ),
		'page_content_rows'       => array( 'basic', 'image_content', 'two_column_split', 'reusable_block', 'grid' ),
		'page_after_content_rows' => array( 'basic', 'two_column_split', 'logos', 'reusable_block', 'cta' ),
		'page_sidebar_rows'       => array( 'basic', 'image_content', 'searchwp_form' ),
	);
	$alias_map          = array(
		'basic'            => array( 'basic' ),
		'image_content'    => array( 'image_content' ),
		'two_column_split' => array( 'two_column_split' ),
		'reusable_block'   => array( 'reusable_block' ),
		'grid'             => array( 'grid' ),
		'logos'            => array( 'logos' ),
		'cta'              => array( 'cta' ),
		'searchwp_form'    => array( 'searchwp_form' ),
	);
	$defaults           = array();
	$requested_defaults = isset( $default_map[ $field_name ] ) && is_array( $default_map[ $field_name ] )
		? $default_map[ $field_name ]
		: $configurable_names;

	foreach ( $requested_defaults as $requested_name ) {
		$requested_name = sanitize_key( (string) $requested_name );
		if ( '' === $requested_name ) {
			continue;
		}

		$candidates = isset( $alias_map[ $requested_name ] ) && is_array( $alias_map[ $requested_name ] )
			? $alias_map[ $requested_name ]
			: array( $requested_name );

		foreach ( $candidates as $candidate_name ) {
			$candidate_name = sanitize_key( (string) $candidate_name );
			if ( '' === $candidate_name || ! in_array( $candidate_name, $configurable_names, true ) ) {
				continue;
			}

			$defaults[] = $candidate_name;
			break;
		}
	}

	if ( empty( $defaults ) ) {
		$defaults = $configurable_names;
	}

	$defaults = apply_filters(
		'mrn_base_stack_builder_layout_allowlist_defaults',
		$defaults,
		$field_name,
		$configurable_names,
		$catalog
	);

	if ( ! is_array( $defaults ) ) {
		return $configurable_names;
	}

	return array_values(
		array_unique(
			array_intersect(
				array_filter(
					array_map( 'sanitize_key', $defaults )
				),
				$configurable_names
			)
		)
	);
}

/**
 * Get configured (selectable/addable) layout names for a post field.
 *
 * @param int                                 $post_id Post ID.
 * @param string                              $field_name Flexible-content field name.
 * @param array<string, array<string, mixed>> $catalog Layout catalog.
 * @return array<int, string>
 */
function mrn_base_stack_get_builder_layout_allowlist_configured_names( $post_id, $field_name, array $catalog ) {
	$post_id            = absint( $post_id );
	$field_name         = sanitize_key( (string) $field_name );
	$configurable_names = mrn_base_stack_get_builder_layout_allowlist_configurable_names( $catalog );
	$configured_names   = mrn_base_stack_get_builder_layout_allowlist_default_names( $field_name, $catalog );

	if ( $post_id > 0 && mrn_base_stack_builder_layout_allowlist_should_use_saved_settings( $post_id ) && metadata_exists( 'post', $post_id, mrn_base_stack_get_builder_layout_allowlist_meta_key() ) ) {
		$saved_settings   = mrn_base_stack_get_builder_layout_allowlist_saved_settings( $post_id );
		$configured_names = isset( $saved_settings[ $field_name ] ) && is_array( $saved_settings[ $field_name ] )
			? $saved_settings[ $field_name ]
			: array();
	}

	return array_values(
		array_unique(
			array_intersect(
				array_filter(
					array_map( 'sanitize_key', $configured_names )
				),
				$configurable_names
			)
		)
	);
}

/**
 * Get used layout names that should stay editable but not be addable as new rows.
 *
 * @param int                                 $post_id Post ID.
 * @param string                              $field_name Flexible-content field name.
 * @param array<string, array<string, mixed>> $catalog Layout catalog.
 * @return array<int, string>
 */
function mrn_base_stack_get_builder_layout_allowlist_existing_only_names( $post_id, $field_name, array $catalog ) {
	$post_id          = absint( $post_id );
	$field_name       = sanitize_key( (string) $field_name );
	$all_layout_names = array_keys( $catalog );
	$configured_names = mrn_base_stack_get_builder_layout_allowlist_configured_names( $post_id, $field_name, $catalog );
	$used_names       = $post_id > 0 ? mrn_base_stack_get_builder_layout_allowlist_used_layout_names( $post_id, $field_name ) : array();

	if ( empty( $used_names ) ) {
		return array();
	}

	return array_values(
		array_unique(
			array_intersect(
				array_diff( $used_names, $configured_names ),
				$all_layout_names
			)
		)
	);
}

/**
 * Get the effective layout allowlist for a post and flexible-content field.
 *
 * @param int                                 $post_id Post ID.
 * @param string                              $field_name Flexible-content field name.
 * @param array<string, array<string, mixed>> $catalog Layout catalog.
 * @return array<int, string>
 */
function mrn_base_stack_get_builder_layout_allowlist_effective_names( $post_id, $field_name, array $catalog ) {
	$post_id            = absint( $post_id );
	$field_name         = sanitize_key( (string) $field_name );
	$all_layout_names   = array_keys( $catalog );
	$configured_names   = mrn_base_stack_get_builder_layout_allowlist_configured_names( $post_id, $field_name, $catalog );
	$configurable_names = mrn_base_stack_get_builder_layout_allowlist_configurable_names( $catalog );

	$used_names = $post_id > 0 ? mrn_base_stack_get_builder_layout_allowlist_used_layout_names( $post_id, $field_name ) : array();
	$effective  = array_values(
		array_unique(
			array_merge( $configured_names, $used_names )
		)
	);

	$effective = array_values(
		array_unique(
			array_intersect(
				array_filter(
					array_map( 'sanitize_key', $effective )
				),
				$all_layout_names
			)
		)
	);

	if ( ! empty( $effective ) ) {
		return $effective;
	}

	if ( ! empty( $used_names ) ) {
		return array_values(
			array_unique(
				array_intersect( $used_names, $all_layout_names )
			)
		);
	}

	if ( ! empty( $configurable_names ) ) {
		return array( $configurable_names[0] );
	}

	return ! empty( $all_layout_names ) ? array( $all_layout_names[0] ) : array();
}

/**
 * Apply per-entry allowlist filtering to builder flexible-content field layouts.
 *
 * @param array<string, mixed> $field ACF field definition.
 * @return array<string, mixed>
 */
function mrn_base_stack_filter_builder_layout_allowlist_field_layouts( $field ) {
	if ( ! is_array( $field ) ) {
		return $field;
	}

	$field_name = isset( $field['name'] ) ? sanitize_key( (string) $field['name'] ) : '';
	$targets    = mrn_base_stack_get_builder_layout_allowlist_targets();

	if ( '' === $field_name || ! isset( $targets[ $field_name ] ) ) {
		return $field;
	}

	if ( empty( $field['layouts'] ) || ! is_array( $field['layouts'] ) ) {
		return $field;
	}

	$post_id = mrn_base_stack_get_builder_layout_allowlist_post_id();
	if ( ! mrn_base_stack_is_builder_layout_allowlist_context( $post_id ) ) {
		return $field;
	}

	$catalog = mrn_base_stack_get_builder_layout_allowlist_catalog_from_field( $field );
	if ( empty( $catalog ) ) {
		return $field;
	}

	$existing_only_names  = mrn_base_stack_get_builder_layout_allowlist_existing_only_names( $post_id, $field_name, $catalog );
	$existing_only_lookup = ! empty( $existing_only_names ) ? array_fill_keys( $existing_only_names, true ) : array();

	$effective_names = mrn_base_stack_get_builder_layout_allowlist_effective_names( $post_id, $field_name, $catalog );
	if ( empty( $effective_names ) ) {
		return $field;
	}

	$effective_lookup = array_fill_keys( $effective_names, true );
	$filtered_layouts = array();

	foreach ( $field['layouts'] as $layout_key => $layout ) {
		if ( ! is_array( $layout ) ) {
			continue;
		}

		$layout_name = isset( $layout['name'] ) ? sanitize_key( (string) $layout['name'] ) : '';
		if ( '' === $layout_name || ! isset( $effective_lookup[ $layout_name ] ) ) {
			continue;
		}

		// Keep existing rows editable while preventing new rows from this layout.
		if ( isset( $existing_only_lookup[ $layout_name ] ) ) {
			$layout['max'] = -1;
		}

		$filtered_layouts[ $layout_key ] = $layout;
	}

	if ( ! empty( $filtered_layouts ) ) {
		$field['layouts'] = $filtered_layouts;
	}

	return $field;
}
add_filter( 'acf/load_field/key=field_mrn_page_hero_rows', 'mrn_base_stack_filter_builder_layout_allowlist_field_layouts', 20 );
add_filter( 'acf/load_field/key=field_mrn_page_content_rows', 'mrn_base_stack_filter_builder_layout_allowlist_field_layouts', 20 );
add_filter( 'acf/load_field/key=field_mrn_page_after_content_rows', 'mrn_base_stack_filter_builder_layout_allowlist_field_layouts', 20 );
add_filter( 'acf/load_field/key=field_mrn_sidebar_rows', 'mrn_base_stack_filter_builder_layout_allowlist_field_layouts', 20 );
add_filter( 'acf/prepare_field/key=field_mrn_page_hero_rows', 'mrn_base_stack_filter_builder_layout_allowlist_field_layouts', 20 );
add_filter( 'acf/prepare_field/key=field_mrn_page_content_rows', 'mrn_base_stack_filter_builder_layout_allowlist_field_layouts', 20 );
add_filter( 'acf/prepare_field/key=field_mrn_page_after_content_rows', 'mrn_base_stack_filter_builder_layout_allowlist_field_layouts', 20 );
add_filter( 'acf/prepare_field/key=field_mrn_sidebar_rows', 'mrn_base_stack_filter_builder_layout_allowlist_field_layouts', 20 );

/**
 * Hide non-addable existing-only layout choices in ACF popup menus.
 *
 * @return void
 */
function mrn_base_stack_print_builder_layout_allowlist_existing_only_styles() {
	$post_id = mrn_base_stack_get_builder_layout_allowlist_post_id();
	if ( ! mrn_base_stack_is_builder_layout_allowlist_context( $post_id ) ) {
		return;
	}
	?>
	<style>
	.acf-fc-popup li:has(> a[data-max="-1"]) {
		display: none !important;
	}

	/* Fallback for browsers without :has() support to avoid large visual gaps. */
	.acf-fc-popup a[data-max="-1"] {
		display: block !important;
		height: 0 !important;
		margin: 0 !important;
		padding: 0 !important;
		border: 0 !important;
		font-size: 0 !important;
		line-height: 0 !important;
		overflow: hidden !important;
	}
	</style>
	<?php
}
add_action( 'acf/input/admin_head', 'mrn_base_stack_print_builder_layout_allowlist_existing_only_styles' );

/**
 * Register a classic-editor metabox for per-entry builder layout allowlists.
 *
 * @return void
 */
function mrn_base_stack_register_builder_layout_allowlist_meta_box() {
	if ( ! is_admin() || ! function_exists( 'add_meta_box' ) ) {
		return;
	}

	foreach ( mrn_base_stack_get_singular_shell_post_types() as $post_type ) {
		add_meta_box(
			'mrn-builder-layout-allowlist',
			'Available Builder Layout Types',
			'mrn_base_stack_render_builder_layout_allowlist_meta_box',
			$post_type,
			'side',
			'default'
		);
	}
}
add_action( 'add_meta_boxes', 'mrn_base_stack_register_builder_layout_allowlist_meta_box' );

/**
 * Render the per-entry builder layout allowlist metabox.
 *
 * @param WP_Post $post Current post object.
 * @return void
 */
function mrn_base_stack_render_builder_layout_allowlist_meta_box( $post ) {
	if ( ! $post instanceof WP_Post ) {
		return;
	}

	$post_id                = (int) $post->ID;
	$post_type              = sanitize_key( (string) $post->post_type );
	$targets                = mrn_base_stack_get_builder_layout_allowlist_targets();
	$has_saved              = mrn_base_stack_builder_layout_allowlist_should_use_saved_settings( $post_id ) && metadata_exists( 'post', $post_id, mrn_base_stack_get_builder_layout_allowlist_meta_key() );
	$saved                  = mrn_base_stack_get_builder_layout_allowlist_saved_settings( $post_id );
	$sitewide_allowed_names = mrn_base_stack_get_sitewide_allowed_builder_layout_names();

	wp_nonce_field( 'mrn_base_stack_builder_layout_allowlist_save', 'mrn_base_stack_builder_layout_allowlist_nonce' );
	?>
	<p>Choose which layout types are available in Add Row for this entry.</p>
	<input type="hidden" class="mrn-builder-layout-allowlist-payload" value="" />
	<?php

	foreach ( $targets as $field_name => $target ) {
		if ( ! mrn_base_stack_should_render_builder_layout_allowlist_target( $field_name, $post_type ) ) {
			continue;
		}

		$catalog            = mrn_base_stack_get_builder_layout_allowlist_catalog_from_field( mrn_base_stack_get_builder_layout_allowlist_field_definition( $field_name ) );
		$configurable_names = mrn_base_stack_get_builder_layout_allowlist_configurable_names( $catalog );
		$label              = isset( $target['label'] ) ? (string) $target['label'] : ucfirst( str_replace( array( '-', '_' ), ' ', $field_name ) );

		if ( empty( $catalog ) ) {
			continue;
		}

		$selected       = $has_saved
			? ( isset( $saved[ $field_name ] ) && is_array( $saved[ $field_name ] ) ? $saved[ $field_name ] : array() )
			: mrn_base_stack_get_builder_layout_allowlist_default_names( $field_name, $catalog );
		$selected       = array_fill_keys(
			array_values(
				array_unique(
					array_intersect(
						array_filter(
							array_map( 'sanitize_key', $selected )
						),
						$configurable_names
					)
				)
			),
			true
		);
		$selected_count = count( $selected );
		$total_count    = count( $configurable_names );

		$used_names         = mrn_base_stack_get_builder_layout_allowlist_used_layout_names( $post_id, $field_name );
		$used_nonselectable = array();

		foreach ( $used_names as $used_name ) {
			if ( is_array( $sitewide_allowed_names ) && ! in_array( $used_name, $sitewide_allowed_names, true ) ) {
				continue;
			}

			if ( in_array( $used_name, $configurable_names, true ) ) {
				continue;
			}

			$used_nonselectable[] = $used_name;
		}
		?>
		<hr />
		<p><strong><?php echo esc_html( $label ); ?></strong></p>
		<?php if ( empty( $configurable_names ) ) : ?>
			<p><em>No selectable layouts are available for this section.</em></p>
		<?php else : ?>
			<input
				type="hidden"
				name="mrn_builder_layout_allowlist_catalog[<?php echo esc_attr( $field_name ); ?>]"
				value="<?php echo esc_attr( implode( ',', $configurable_names ) ); ?>"
			/>
			<p style="margin: 0 0 8px;">
				<em><?php echo esc_html( sprintf( 'Selected %1$d of %2$d layouts. Scroll to view all.', $selected_count, $total_count ) ); ?></em>
			</p>
			<div style="max-height: 180px; overflow: auto; border: 1px solid #dcdcde; padding: 8px;">
				<?php foreach ( $configurable_names as $layout_name ) : ?>
					<?php
					$layout_label = isset( $catalog[ $layout_name ]['label'] ) ? (string) $catalog[ $layout_name ]['label'] : $layout_name;
					?>
					<label style="display:block; margin: 0 0 6px;">
						<input
							type="checkbox"
							name="mrn_builder_layout_allowlist[<?php echo esc_attr( $field_name ); ?>][]"
							value="<?php echo esc_attr( $layout_name ); ?>"
							<?php checked( isset( $selected[ $layout_name ] ) ); ?>
						/>
						<?php echo esc_html( $layout_label ); ?>
					</label>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $used_nonselectable ) ) : ?>
			<p style="margin-top:8px;"><em>Already used and forced-available:</em> <?php echo esc_html( implode( ', ', $used_nonselectable ) ); ?></p>
		<?php endif; ?>
		<?php
	}
	?>
	<script>
		( function( $, window, document ) {
			'use strict';

			$( function() {
				var payloadFieldName = 'mrn_builder_layout_allowlist_payload';
				var $metabox = $( '#mrn-builder-layout-allowlist' );
				var $postForm = $( '#post' );

				function getMetaboxPayloadField() {
					var $payloadField = $metabox.find( '.mrn-builder-layout-allowlist-payload' ).first();

					if ( ! $payloadField.length ) {
						$payloadField = $( '<input />', {
							type: 'hidden',
							'class': 'mrn-builder-layout-allowlist-payload',
							value: ''
						} );
						$metabox.prepend( $payloadField );
					}

					return $payloadField;
				}

				function getTopLevelPayloadField() {
					var $payloadField = $postForm.find( '#mrn-builder-allowlist-payload' ).first();

					if ( ! $payloadField.length && $postForm.length ) {
						$payloadField = $( '<input />', {
							type: 'hidden',
							name: payloadFieldName,
							id: 'mrn-builder-allowlist-payload',
							value: ''
						} );
						$postForm.prepend( $payloadField );
					}

					return $payloadField;
				}

				function collectAllowlistPayload() {
					var payload = {
						allowlist: {},
						catalog: {}
					};
					var $scope = $metabox.length ? $metabox : $( document );

					$scope.find( 'input[name^="mrn_builder_layout_allowlist_catalog["]' ).each( function() {
						var fieldName = '';
						var nameMatch = ( $( this ).attr( 'name' ) || '' ).match( /^mrn_builder_layout_allowlist_catalog\[([^\]]+)\]$/ );
						if ( nameMatch && nameMatch[1] ) {
							fieldName = nameMatch[1];
						}

						if ( ! fieldName ) {
							return;
						}

						payload.catalog[ fieldName ] = $( this ).val() || '';
						payload.allowlist[ fieldName ] = [];
					} );

					$scope.find( 'input[type="checkbox"][name^="mrn_builder_layout_allowlist["]:checked' ).each( function() {
						var fieldName = '';
						var nameMatch = ( $( this ).attr( 'name' ) || '' ).match( /^mrn_builder_layout_allowlist\[([^\]]+)\]\[\]$/ );
						if ( nameMatch && nameMatch[1] ) {
							fieldName = nameMatch[1];
						}

						if ( ! fieldName ) {
							return;
						}

						if ( ! Object.prototype.hasOwnProperty.call( payload.allowlist, fieldName ) ) {
							payload.allowlist[ fieldName ] = [];
						}

						payload.allowlist[ fieldName ].push( $( this ).val() || '' );
					} );

					return JSON.stringify( payload );
				}

				function syncPayloadField() {
					if ( ! $metabox.length && ! $postForm.length ) {
						return;
					}

					var payloadValue = collectAllowlistPayload();
					getMetaboxPayloadField().val( payloadValue );
					getTopLevelPayloadField().val( payloadValue );
				}

				// Keep payload current for save flows that gather metabox FormData directly.
				$( document ).on( 'change', '#mrn-builder-layout-allowlist input[type="checkbox"][name^="mrn_builder_layout_allowlist["]', syncPayloadField );
				$( document ).on( 'submit', '#post, .metabox-base-form, #metaboxes .metabox-location-normal, #metaboxes .metabox-location-side, #metaboxes .metabox-location-advanced', syncPayloadField );
				$( document ).on( 'click', '#save-post, #publish, .editor-post-save-draft, .editor-post-publish-button, .editor-post-publish-panel__toggle, .editor-post-publish-button__button', syncPayloadField );

				syncPayloadField();
			} );
		} )( jQuery, window, document );
	</script>
	<?php
}

/**
 * Capture one raw request field for allowlist parsing.
 *
 * @param array<string, mixed> $parsed Parsed payload accumulator.
 * @param string               $name Field name.
 * @param string               $value Field value.
 * @return array<string, mixed>
 */
function mrn_base_stack_capture_builder_layout_allowlist_raw_request_field( array $parsed, $name, $value ) {
	$name  = (string) $name;
	$value = (string) $value;

	if ( 'mrn_builder_layout_allowlist_payload' === $name ) {
		$parsed['payload'] = $value;
		return $parsed;
	}

	if ( preg_match( '/^mrn_builder_layout_allowlist_catalog\[([^\]]+)\]$/', $name, $catalog_match ) ) {
		$field_name = sanitize_key( (string) $catalog_match[1] );
		if ( '' !== $field_name ) {
			$parsed['catalog'][ $field_name ] = $value;
		}

		return $parsed;
	}

	if ( preg_match( '/^mrn_builder_layout_allowlist\[([^\]]+)\]\[\]$/', $name, $allowlist_match ) ) {
		$field_name = sanitize_key( (string) $allowlist_match[1] );
		if ( '' === $field_name ) {
			return $parsed;
		}

		if ( ! isset( $parsed['allowlist'][ $field_name ] ) || ! is_array( $parsed['allowlist'][ $field_name ] ) ) {
			$parsed['allowlist'][ $field_name ] = array();
		}

		$parsed['allowlist'][ $field_name ][] = $value;
	}

	return $parsed;
}

/**
 * Parse builder allowlist fields from a raw request body.
 *
 * @param string $raw_body Raw request body.
 * @param string $content_type Request content type.
 * @return array<string, mixed>
 */
function mrn_base_stack_parse_builder_layout_allowlist_raw_body( $raw_body, $content_type ) {
	$parsed = array(
		'allowlist' => array(),
		'catalog'   => array(),
		'payload'   => '',
	);

	$raw_body         = is_string( $raw_body ) ? $raw_body : '';
	$content_type_raw = is_string( $content_type ) ? $content_type : '';
	$content_type_lc  = strtolower( $content_type_raw );

	if ( '' === $raw_body ) {
		return $parsed;
	}

	if ( false !== strpos( $content_type_lc, 'multipart/form-data' ) ) {
		$boundary = '';
		if ( preg_match( '/boundary=(?:"([^"]+)"|([^;]+))/i', $content_type_raw, $boundary_match ) ) {
			$boundary = isset( $boundary_match[1] ) && '' !== $boundary_match[1]
				? (string) $boundary_match[1]
				: ( isset( $boundary_match[2] ) ? (string) $boundary_match[2] : '' );
		}

		if ( '' === $boundary ) {
			return $parsed;
		}

		$parts = explode( '--' . $boundary, $raw_body );
		foreach ( $parts as $part ) {
			$part = ltrim( (string) $part, "\r\n" );
			if ( '' === $part || '--' === $part ) {
				continue;
			}

			$segments = explode( "\r\n\r\n", $part, 2 );
			if ( 2 !== count( $segments ) ) {
				continue;
			}

			$headers = (string) $segments[0];
			$body    = (string) $segments[1];
			$body    = (string) preg_replace( "/\r\n$/", '', $body );
			$body    = (string) preg_replace( "/\r\n--$/", '', $body );
			$name    = '';

			foreach ( explode( "\r\n", $headers ) as $header_line ) {
				$header_line = trim( (string) $header_line );
				if ( 0 === stripos( $header_line, 'content-disposition:' ) && preg_match( '/name="([^"]+)"/', $header_line, $name_match ) ) {
					$name = isset( $name_match[1] ) ? (string) $name_match[1] : '';
					break;
				}
			}

			if ( '' === $name ) {
				continue;
			}

			$parsed = mrn_base_stack_capture_builder_layout_allowlist_raw_request_field( $parsed, $name, $body );
		}

		return $parsed;
	}

	foreach ( explode( '&', $raw_body ) as $pair ) {
		if ( '' === $pair ) {
			continue;
		}

		$segments = explode( '=', $pair, 2 );
		$name     = urldecode( str_replace( '+', ' ', (string) $segments[0] ) );
		$value    = urldecode( str_replace( '+', ' ', isset( $segments[1] ) ? (string) $segments[1] : '' ) );

		if ( '' === $name ) {
			continue;
		}

		$parsed = mrn_base_stack_capture_builder_layout_allowlist_raw_request_field( $parsed, $name, $value );
	}

	return $parsed;
}

/**
 * Get builder allowlist inputs parsed from the raw request body.
 *
 * @return array<string, mixed>
 */
function mrn_base_stack_get_builder_layout_allowlist_raw_request_payload() {
	static $parsed = null;

	if ( null !== $parsed ) {
		return $parsed;
	}

	$content_type = isset( $_SERVER['CONTENT_TYPE'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized immediately.
		? sanitize_text_field( wp_unslash( (string) $_SERVER['CONTENT_TYPE'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Read-only server context.
		: '';
	$raw_body     = file_get_contents( 'php://input' );

	$parsed = mrn_base_stack_parse_builder_layout_allowlist_raw_body(
		is_string( $raw_body ) ? $raw_body : '',
		$content_type
	);

	return $parsed;
}

/**
 * Get a sanitized allowlist request array from POST.
 *
 * @param string $key POST field key.
 * @return array<string, mixed>
 */
function mrn_base_stack_get_builder_layout_allowlist_post_array( $key ) {
	if ( ! isset( $_POST[ $key ] ) || ! is_array( $_POST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce/capability verified by caller.
		return array();
	}

	$input = wp_unslash( $_POST[ $key ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce/capability verified by caller; values sanitized before use.
	if ( ! is_array( $input ) ) {
		return array();
	}

	return map_deep( $input, 'sanitize_text_field' );
}

/**
 * Get a sanitized allowlist request string from POST.
 *
 * @param string $key POST field key.
 * @return string
 */
function mrn_base_stack_get_builder_layout_allowlist_post_string( $key ) {
	if ( ! isset( $_POST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce/capability verified by caller.
		return '';
	}

	$input = wp_unslash( $_POST[ $key ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce/capability verified by caller; value sanitized before use.
	if ( is_array( $input ) ) {
		$input = reset( $input );
	}

	return is_string( $input ) ? sanitize_textarea_field( $input ) : '';
}

/**
 * Save per-entry builder layout allowlist selections.
 *
 * @param int     $post_id Post ID.
 * @param WP_Post $post Post object.
 * @return void
 */
function mrn_base_stack_save_builder_layout_allowlist_meta_box( $post_id, $post ) {
	$post_id = absint( $post_id );

	if ( $post_id < 1 || ! $post instanceof WP_Post ) {
		return;
	}

	$has_allowlist_nonce = isset( $_POST['mrn_base_stack_builder_layout_allowlist_nonce'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verification handled inline.
		&& wp_verify_nonce(
			sanitize_text_field( wp_unslash( $_POST['mrn_base_stack_builder_layout_allowlist_nonce'] ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verification handled inline.
			'mrn_base_stack_builder_layout_allowlist_save'
		);
	$has_core_post_nonce = isset( $_POST['_wpnonce'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verification handled inline.
		&& (
			wp_verify_nonce(
				sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verification handled inline.
				'update-post_' . $post_id
			)
			|| wp_verify_nonce(
				sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verification handled inline.
				'add-post'
			)
		);

	if ( ! $has_allowlist_nonce && ! $has_core_post_nonce ) {
		return;
	}

	if ( wp_is_post_revision( $post_id ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
		return;
	}

	$post_type = sanitize_key( (string) $post->post_type );
	if ( '' === $post_type || ! in_array( $post_type, mrn_base_stack_get_singular_shell_post_types(), true ) ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$has_allowlist_payload_inputs = isset( $_POST['mrn_builder_layout_allowlist'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce/capability verified above.
		|| isset( $_POST['mrn_builder_layout_allowlist_catalog'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce/capability verified above.
		|| isset( $_POST['mrn_builder_layout_allowlist_payload'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce/capability verified above.

	if ( ! $has_allowlist_nonce && ! $has_allowlist_payload_inputs ) {
		return;
	}

	$input           = mrn_base_stack_get_builder_layout_allowlist_post_array( 'mrn_builder_layout_allowlist' );
	$catalog_input   = mrn_base_stack_get_builder_layout_allowlist_post_array( 'mrn_builder_layout_allowlist_catalog' );
	$payload_input   = mrn_base_stack_get_builder_layout_allowlist_post_string( 'mrn_builder_layout_allowlist_payload' );
	$decoded_payload = null;

	if ( '' !== $payload_input ) {
		$decoded_payload = json_decode( $payload_input, true );
	}

	$needs_raw_fallback = (
		( ! is_array( $decoded_payload ) && '' !== $payload_input )
		|| ( '' === $payload_input && ( empty( $input ) || empty( $catalog_input ) ) )
	);

	if ( $needs_raw_fallback ) {
		/*
		 * Raw-body parsing is a fallback for max_input_vars truncation recovery.
		 *
		 * Parsing full php://input on every save is expensive on large builder
		 * submissions, so only do it when the compact payload is missing/invalid
		 * or the expected allowlist/catalog arrays are absent.
		 */
		$raw_payload = mrn_base_stack_get_builder_layout_allowlist_raw_request_payload();

		if ( isset( $raw_payload['allowlist'] ) && is_array( $raw_payload['allowlist'] ) && ! empty( $raw_payload['allowlist'] ) ) {
			$input = $raw_payload['allowlist'];
		}

		if ( isset( $raw_payload['catalog'] ) && is_array( $raw_payload['catalog'] ) && ! empty( $raw_payload['catalog'] ) ) {
			$catalog_input = $raw_payload['catalog'];
		}

		if ( isset( $raw_payload['payload'] ) && is_string( $raw_payload['payload'] ) && '' !== $raw_payload['payload'] ) {
			$payload_input = $raw_payload['payload'];
			$decoded       = json_decode( $payload_input, true );

			if ( is_array( $decoded ) ) {
				$decoded_payload = $decoded;
			}
		}
	}

	if ( is_array( $decoded_payload ) ) {
		/*
		 * Prefer payload data when available.
		 *
		 * Large classic-editor submissions can hit max_input_vars and deliver
		 * partially truncated checkbox arrays. The payload is a compact snapshot
		 * captured at submit time and avoids per-field truncation loss.
		 */
		if ( isset( $decoded_payload['allowlist'] ) && is_array( $decoded_payload['allowlist'] ) ) {
			$input = $decoded_payload['allowlist'];
		}

		if ( isset( $decoded_payload['catalog'] ) && is_array( $decoded_payload['catalog'] ) ) {
			$catalog_input = $decoded_payload['catalog'];
		}
	}

	$allowlists = mrn_base_stack_build_sanitized_builder_layout_allowlist_payload( $input, $catalog_input );
	$targets    = mrn_base_stack_get_builder_layout_allowlist_targets();
	$existing   = mrn_base_stack_get_builder_layout_allowlist_saved_settings( $post_id );
	$active     = array();

	foreach ( $targets as $field_name => $target ) {
		if ( mrn_base_stack_should_render_builder_layout_allowlist_target( $field_name, $post_type ) ) {
			$active[ $field_name ] = true;
			continue;
		}

		unset( $allowlists[ $field_name ] );
	}

	/*
	 * Preserve previously saved field selections when a field-specific catalog
	 * marker is missing from POST. This avoids accidental clears when large
	 * editor payloads omit trailing metabox arrays.
	 */
	foreach ( $targets as $field_name => $target ) {
		if ( ! isset( $active[ $field_name ] ) ) {
			continue;
		}

		$has_catalog_marker = array_key_exists( $field_name, $catalog_input );
		$has_field_input    = isset( $input[ $field_name ] ) && is_array( $input[ $field_name ] );

		if ( $has_catalog_marker || $has_field_input ) {
			continue;
		}

		$allowlists[ $field_name ] = isset( $existing[ $field_name ] ) && is_array( $existing[ $field_name ] )
			? array_values(
				array_unique(
					array_filter(
						array_map( 'sanitize_key', $existing[ $field_name ] )
					)
				)
			)
			: array();
	}

	update_post_meta( $post_id, mrn_base_stack_get_builder_layout_allowlist_meta_key(), $allowlists );
	update_post_meta( $post_id, mrn_base_stack_get_builder_layout_allowlist_initialized_meta_key(), 1 );
}
add_action( 'save_post', 'mrn_base_stack_save_builder_layout_allowlist_meta_box', 10, 2 );
