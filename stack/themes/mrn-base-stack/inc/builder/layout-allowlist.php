<?php
/**
 * Builder layout allowlist controls for classic editor screens.
 *
 * @package mrn-base-stack
 */

/**
 * Get builder flexible-content areas with site/section eligibility rules.
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
			'label'     => 'Sidebar',
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

	if ( 'page_sidebar_rows' === $field_name && function_exists( 'mrn_base_stack_get_sidebar_supported_post_types' ) ) {
		return in_array( $post_type, mrn_base_stack_get_sidebar_supported_post_types(), true );
	}

	return true;
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
		'acf/load_field/key=field_mrn_page_template_sidebar_rows',
		'acf/load_field/type=flexible_content',
		'acf/prepare_field/key=field_mrn_page_hero_rows',
		'acf/prepare_field/key=field_mrn_page_content_rows',
		'acf/prepare_field/key=field_mrn_page_after_content_rows',
		'acf/prepare_field/key=field_mrn_sidebar_rows',
		'acf/prepare_field/key=field_mrn_page_template_sidebar_rows',
		'acf/prepare_field/type=flexible_content',
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
	remove_filter( 'acf/load_fields', 'mrn_base_stack_filter_builder_layout_allowlist_fields', 20 );

	try {
		return $callback();
	} finally {
		foreach ( $hooks as $hook_name ) {
			add_filter( $hook_name, 'mrn_base_stack_filter_builder_layout_allowlist_field_layouts', 20 );
		}
		add_filter( 'acf/load_fields', 'mrn_base_stack_filter_builder_layout_allowlist_fields', 20, 2 );
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
		$catalog            = mrn_base_stack_get_builder_layout_allowlist_catalog( $field_name );
		$configurable_names = mrn_base_stack_get_builder_layout_allowlist_configurable_names( $catalog, $field_name );
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

	if ( '' === $post_type && isset( $_REQUEST['post_ID'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only context lookup.
		$post_type = sanitize_key( (string) get_post_type( absint( wp_unslash( $_REQUEST['post_ID'] ) ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only context lookup.
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
 * sets before site/section eligibility filtering runs.
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

		$is_page_only = ! empty( $layout['mrn_is_page_only'] ) || false !== stripos( $label, '(Page Only)' );

		$catalog[ $name ] = array(
			'label'        => $label,
			'is_page_only' => $is_page_only,
		);
	}

	return $catalog;
}

/**
 * Resolve a lightweight layout catalog for an allowlist target.
 *
 * @param string $field_name Flexible-content field name.
 * @return array<string, array<string, mixed>>
 */
function mrn_base_stack_get_builder_layout_allowlist_catalog( $field_name ) {
	static $cache = array();

	$field_name = sanitize_key( (string) $field_name );
	if ( isset( $cache[ $field_name ] ) ) {
		return $cache[ $field_name ];
	}

	$content_catalog = array();
	// ACF's shallow local layout records already contain names and labels.
	// Do not hydrate thousands of child controls just to build a picker catalog.
	if ( function_exists( 'acf_get_local_field' ) ) {
		$content_field   = acf_get_local_field( 'field_mrn_page_content_rows' );
		$content_catalog = is_array( $content_field )
			? mrn_base_stack_get_builder_layout_allowlist_catalog_from_field( $content_field )
			: array();
	}
	if ( empty( $content_catalog ) && function_exists( 'mrn_base_stack_get_content_builder_source_layouts' ) ) {
		$content_catalog = mrn_base_stack_get_builder_layout_allowlist_catalog_from_field(
			array(
				'layouts' => mrn_base_stack_get_content_builder_source_layouts(),
			)
		);
	}
	if ( 'page_content_rows' === $field_name && ! empty( $content_catalog ) ) {
		$cache[ $field_name ] = $content_catalog;
		return $cache[ $field_name ];
	}

	$source_names = array();
	if ( 'page_hero_rows' === $field_name && function_exists( 'mrn_base_stack_get_hero_builder_layout_source_names' ) ) {
		$source_names = mrn_base_stack_get_hero_builder_layout_source_names();
	} elseif ( 'page_after_content_rows' === $field_name && function_exists( 'mrn_base_stack_get_after_content_layout_source_names' ) ) {
		$source_names = mrn_base_stack_get_after_content_layout_source_names();
	} elseif ( 'page_sidebar_rows' === $field_name && function_exists( 'mrn_base_stack_get_sidebar_layout_source_names' ) ) {
		$source_names = mrn_base_stack_get_sidebar_layout_source_names();
	}

	if ( empty( $content_catalog ) || empty( $source_names ) ) {
		$cache[ $field_name ] = mrn_base_stack_get_builder_layout_allowlist_catalog_from_field(
			mrn_base_stack_get_builder_layout_allowlist_field_definition( $field_name )
		);

		return $cache[ $field_name ];
	}

	$catalog = array();
	foreach ( $source_names as $layout_name ) {
		$layout_name = sanitize_key( (string) $layout_name );
		if ( isset( $content_catalog[ $layout_name ] ) ) {
			$catalog[ $layout_name ] = $content_catalog[ $layout_name ];
		}
	}

	$cache[ $field_name ] = $catalog;
	return $cache[ $field_name ];
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
 * @param string                              $field_name Optional flexible-content field name.
 * @return array<int, string>
 */
function mrn_base_stack_get_builder_layout_allowlist_configurable_names( array $catalog, $field_name = '' ) {
	$field_name = sanitize_key( (string) $field_name );
	$names      = array();

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

	$names                   = array_values( array_unique( $names ) );
	$default_removed_names   = array();
	$removed_names           = apply_filters( 'mrn_base_stack_builder_layout_allowlist_removed_layout_names', $default_removed_names, $field_name, $catalog );
	$removed_names           = is_array( $removed_names )
		? array_values(
			array_unique(
				array_filter(
					array_map( 'sanitize_key', $removed_names )
				)
			)
		)
		: $default_removed_names;
	$sitewide_allowed_names  = mrn_base_stack_get_sitewide_allowed_builder_layout_names();
	$sitewide_hidden_names   = mrn_base_stack_get_sitewide_hidden_builder_layout_names();
	$post_type_allowed_names = mrn_base_stack_get_post_type_allowed_builder_layout_names();

	if ( ! empty( $removed_names ) ) {
		$names = array_values( array_diff( $names, $removed_names ) );
	}

	if ( is_array( $sitewide_allowed_names ) ) {
		$names = array_values( array_intersect( $names, $sitewide_allowed_names ) );
	}

	if ( ! empty( $sitewide_hidden_names ) ) {
		$names = array_values( array_diff( $names, $sitewide_hidden_names ) );
	}

	if ( is_array( $post_type_allowed_names ) ) {
		$names = array_values( array_intersect( $names, $post_type_allowed_names ) );
	}

	return mrn_base_stack_sort_builder_layout_allowlist_names_by_label( $names, $catalog );
}

/**
 * Sort allowlist layout names by their editor-facing labels.
 *
 * @param array<int, string>                  $names Layout names.
 * @param array<string, array<string, mixed>> $catalog Layout catalog.
 * @return array<int, string>
 */
function mrn_base_stack_sort_builder_layout_allowlist_names_by_label( array $names, array $catalog ) {
	$names  = array_values( array_unique( array_filter( array_map( 'sanitize_key', $names ) ) ) );
	$labels = array();

	foreach ( $names as $name ) {
		$label = isset( $catalog[ $name ]['label'] ) ? (string) $catalog[ $name ]['label'] : $name;
		$label = trim( wp_strip_all_tags( $label ) );

		$labels[ $name ] = '' !== $label ? $label : $name;
	}

	usort(
		$names,
		static function ( $left, $right ) use ( $labels ) {
			$left_label  = isset( $labels[ $left ] ) ? $labels[ $left ] : $left;
			$right_label = isset( $labels[ $right ] ) ? $labels[ $right ] : $right;
			$comparison  = strnatcasecmp( $left_label, $right_label );

			if ( 0 !== $comparison ) {
				return $comparison;
			}

			return strnatcasecmp( $left, $right );
		}
	);

	return $names;
}

/**
 * Get raw site-wide hidden builder layout names from the Config Helper option.
 *
 * Returns `null` when the option has not been saved so callers can distinguish
 * between "no Config Helper setting yet" and an explicit empty hidden list.
 *
 * @return array<int, string>|null
 */
function mrn_base_stack_get_raw_sitewide_hidden_builder_layout_names() {
	$settings = get_option( 'mrn_helper_settings', null );

	if ( ! is_array( $settings ) || ! array_key_exists( 'disabled_builder_layouts', $settings ) ) {
		return null;
	}

	$hidden_names = is_array( $settings['disabled_builder_layouts'] )
		? $settings['disabled_builder_layouts']
		: array();

	return array_values(
		array_unique(
			array_filter(
				array_map( 'sanitize_key', $hidden_names )
			)
		)
	);
}

/**
 * Get the site-wide hidden builder layout names.
 *
 * @return array<int, string>
 */
function mrn_base_stack_get_sitewide_hidden_builder_layout_names() {
	$raw_hidden_names = mrn_base_stack_get_raw_sitewide_hidden_builder_layout_names();

	if ( is_array( $raw_hidden_names ) ) {
		$hidden_names = $raw_hidden_names;
	} elseif ( function_exists( 'mrn_base_stack_get_hidden_builder_layouts' ) ) {
		$hidden_names = mrn_base_stack_get_hidden_builder_layouts();
	} elseif ( function_exists( 'mrn_config_helper_get_hidden_builder_layouts' ) ) {
		$hidden_names = mrn_config_helper_get_hidden_builder_layouts();
	} else {
		$hidden_names = array();
	}

	if ( ! is_array( $hidden_names ) ) {
		return array();
	}

	return array_values(
		array_unique(
			array_filter(
				array_map( 'sanitize_key', $hidden_names )
			)
		)
	);
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
	$has_saved_hidden_builder_layouts  = is_array( $settings ) && array_key_exists( 'disabled_builder_layouts', $settings ) && is_array( $settings['disabled_builder_layouts'] );

	$allowed_names = is_array( $allowed_names )
		? array_values(
			array_unique(
				array_filter(
					array_map( 'sanitize_key', $allowed_names )
				)
			)
		)
		: array();

	if ( $has_saved_hidden_builder_layouts ) {
		return $allowed_names;
	}

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
 * Get Config Helper allowed builder layout names for the current post type.
 *
 * Returns `null` when Config Helper does not expose CPT-aware settings so
 * callers can keep the older site-wide behavior.
 *
 * @param string $post_type Optional post type slug. Current editor context is used when omitted.
 * @return array<int, string>|null
 */
function mrn_base_stack_get_post_type_allowed_builder_layout_names( $post_type = '' ) {
	if ( ! function_exists( 'mrn_config_helper_get_allowed_builder_layouts_for_post_type' ) ) {
		return null;
	}

	$post_type = sanitize_key( (string) $post_type );
	if ( '' === $post_type ) {
		$post_type = mrn_base_stack_get_builder_layout_allowlist_context_post_type( mrn_base_stack_get_builder_layout_allowlist_post_id() );
	}

	if ( '' === $post_type ) {
		return null;
	}

	$allowed_names = mrn_config_helper_get_allowed_builder_layouts_for_post_type( $post_type );
	if ( ! is_array( $allowed_names ) ) {
		return null;
	}

	return array_values(
		array_unique(
			array_filter(
				array_map( 'sanitize_key', $allowed_names )
			)
		)
	);
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
 * Determine whether a saved allowlist payload actually contains a field.
 *
 * This lets newly introduced allowlist targets keep using defaults until an
 * editor explicitly saves choices for that target.
 *
 * @param int    $post_id Post ID.
 * @param string $field_name Flexible-content field name.
 * @return bool
 */
function mrn_base_stack_builder_layout_allowlist_saved_field_exists( $post_id, $field_name ) {
	$post_id    = absint( $post_id );
	$field_name = sanitize_key( (string) $field_name );

	if ( $post_id < 1 || '' === $field_name ) {
		return false;
	}

	$raw = get_post_meta( $post_id, mrn_base_stack_get_builder_layout_allowlist_meta_key(), true );

	return is_array( $raw ) && array_key_exists( $field_name, $raw );
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
		'page_sidebar_rows'       => 6,
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
	$configurable_names = mrn_base_stack_get_builder_layout_allowlist_configurable_names( $catalog, $field_name );
	$default_map        = array(
		'page_hero_rows'          => array( 'basic_block', 'two_column_split' ),
		'page_content_rows'       => array( 'basic_block', 'image_content', 'two_column_split', 'reusable_block', 'grid', 'external_widget', 'faq_jump_nav' ),
		'page_after_content_rows' => array( 'basic_block', 'two_column_split', 'logos', 'reusable_block', 'cta', 'external_widget', 'faq_jump_nav' ),
		'page_sidebar_rows'       => array( 'body_text', 'content_lists', 'basic_block', 'cta', 'image_content', 'external_widget', 'faq_jump_nav' ),
	);
	$alias_map          = array(
		'basic_block'      => array( 'basic_block' ),
		'image_content'    => array( 'image_content' ),
		'two_column_split' => array( 'two_column_split' ),
		'reusable_block'   => array( 'reusable_block' ),
		'grid'             => array( 'grid' ),
		'logos'            => array( 'logos' ),
		'cta'              => array( 'cta' ),
		'searchwp_form'    => array( 'searchwp_form' ),
		'body_text'        => array( 'body_text' ),
		'content_lists'    => array( 'content_lists' ),
		'external_widget'  => array( 'external_widget' ),
		'wpforms'          => array( 'wpforms' ),
		'video'            => array( 'video' ),
		'faq'              => array( 'faq' ),
		'faq_jump_nav'     => array( 'faq_jump_nav' ),
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
	// Per-entry selections are retired; Add Row exposes every eligible layout.
	// Retain stored metadata for rollback without reading or rewriting it.
	return mrn_base_stack_get_builder_layout_allowlist_configurable_names( $catalog, $field_name );
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
	$configurable_names = mrn_base_stack_get_builder_layout_allowlist_configurable_names( $catalog, $field_name );

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
 * Apply site/section eligibility once, retaining existing excluded rows.
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

	// Resolve policy/catalog consumers once instead of repeating them for each
	// configured/effective/existing-only calculation. Entry metadata is retired.
	$eligible_names       = mrn_base_stack_get_builder_layout_allowlist_configurable_names( $catalog, $field_name );
	$used_names           = $post_id > 0 ? mrn_base_stack_get_builder_layout_allowlist_used_layout_names( $post_id, $field_name ) : array();
	$existing_only_names  = array_diff( $used_names, $eligible_names );
	$existing_only_lookup = array_fill_keys( $existing_only_names, true );
	$effective_lookup     = array_fill_keys( array_merge( $eligible_names, $used_names ), true );
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

	// An explicit empty site policy must not fall back to offering all layouts.
	$field['layouts'] = $filtered_layouts;

	return $field;
}

/**
 * Apply site/section eligibility to a loaded ACF field-group field list.
 *
 * Local field groups can hand ACF a full flexible-content source field before
 * individual field filters hydrate the render-time clone store. This pass keeps
 * the rendered admin clone templates aligned with the same allowlist contract.
 *
 * @param array<int|string, mixed>|mixed $fields Loaded field group fields.
 * @param mixed                          $acf_parent ACF parent field/group reference.
 * @return array<int|string, mixed>|mixed
 */
function mrn_base_stack_filter_builder_layout_allowlist_fields( $fields, $acf_parent = null ) {
	unset( $acf_parent );

	if ( ! is_array( $fields ) ) {
		return $fields;
	}

	foreach ( $fields as $index => $field ) {
		if ( is_array( $field ) ) {
			$fields[ $index ] = mrn_base_stack_filter_builder_layout_allowlist_field_layouts( $field );
		}
	}

	return $fields;
}
add_filter( 'acf/load_field/key=field_mrn_page_hero_rows', 'mrn_base_stack_filter_builder_layout_allowlist_field_layouts', 20 );
add_filter( 'acf/load_field/key=field_mrn_page_content_rows', 'mrn_base_stack_filter_builder_layout_allowlist_field_layouts', 20 );
add_filter( 'acf/load_field/key=field_mrn_page_after_content_rows', 'mrn_base_stack_filter_builder_layout_allowlist_field_layouts', 20 );
add_filter( 'acf/load_field/key=field_mrn_sidebar_rows', 'mrn_base_stack_filter_builder_layout_allowlist_field_layouts', 20 );
add_filter( 'acf/load_field/key=field_mrn_page_template_sidebar_rows', 'mrn_base_stack_filter_builder_layout_allowlist_field_layouts', 20 );
add_filter( 'acf/load_fields', 'mrn_base_stack_filter_builder_layout_allowlist_fields', 20, 2 );
add_filter( 'acf/prepare_field/key=field_mrn_page_hero_rows', 'mrn_base_stack_filter_builder_layout_allowlist_field_layouts', 20 );
add_filter( 'acf/prepare_field/key=field_mrn_page_content_rows', 'mrn_base_stack_filter_builder_layout_allowlist_field_layouts', 20 );
add_filter( 'acf/prepare_field/key=field_mrn_page_after_content_rows', 'mrn_base_stack_filter_builder_layout_allowlist_field_layouts', 20 );
add_filter( 'acf/prepare_field/key=field_mrn_sidebar_rows', 'mrn_base_stack_filter_builder_layout_allowlist_field_layouts', 20 );
add_filter( 'acf/prepare_field/key=field_mrn_page_template_sidebar_rows', 'mrn_base_stack_filter_builder_layout_allowlist_field_layouts', 20 );
add_filter( 'acf/load_field/type=flexible_content', 'mrn_base_stack_filter_builder_layout_allowlist_field_layouts', 20 );
add_filter( 'acf/prepare_field/type=flexible_content', 'mrn_base_stack_filter_builder_layout_allowlist_field_layouts', 20 );

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
