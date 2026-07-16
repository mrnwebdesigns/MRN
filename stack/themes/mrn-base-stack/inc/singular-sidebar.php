<?php
/**
 * Singular sidebar settings and rendering helpers.
 *
 * @package mrn-base-stack
 */

/**
 * Get the post types that should expose the theme singular sidebar controls.
 *
 * @return array<int, string>
 */
function mrn_base_stack_get_sidebar_supported_post_types() {
	$fallback_post_types = array( 'post', 'page' );
	$registered_cpts     = get_post_types(
		array(
			'_builtin' => false,
			'public'   => true,
			'show_ui'  => true,
		),
		'names'
	);
	$post_types          = array_merge( $fallback_post_types, array_values( $registered_cpts ) );

	/**
	 * Filter the post types that can opt into the singular sidebar shell.
	 *
	 * @param array<int, string> $post_types Supported post types.
	 */
	$post_types = apply_filters( 'mrn_base_stack_sidebar_supported_post_types', $post_types );

	if ( ! is_array( $post_types ) ) {
		return $fallback_post_types;
	}

	$post_types = array_values(
		array_unique(
			array_filter(
				array_map( 'sanitize_key', $post_types )
			)
		)
	);

	return ! empty( $post_types ) ? $post_types : $fallback_post_types;
}

/**
 * Determine whether a post type should receive the shared sidebar templates.
 *
 * @param string $post_type Post type slug.
 * @return bool
 */
function mrn_base_stack_post_type_supports_sidebar_templates( $post_type ) {
	return in_array( sanitize_key( (string) $post_type ), mrn_base_stack_get_sidebar_supported_post_types(), true );
}

/**
 * Expose the sidebar templates in Page/Post Attributes for every editorial CPT.
 *
 * The generic theme_templates filter is intentionally used so CPTs registered
 * after the theme ships receive the templates without a static header update.
 *
 * @param array<string, string> $templates Available templates.
 * @param WP_Theme              $theme     Active theme object.
 * @param WP_Post|null          $post      Post being edited, when available.
 * @param string                $post_type Post type slug.
 * @return array<string, string>
 */
function mrn_base_stack_add_sidebar_templates_to_post_type( $templates, $theme, $post, $post_type ) {
	unset( $theme, $post );

	if ( ! mrn_base_stack_post_type_supports_sidebar_templates( $post_type ) ) {
		return $templates;
	}

	$templates['page-sidebar-left.php']  = __( 'Sidebar Left', 'mrn-base-stack' );
	$templates['page-sidebar-right.php'] = __( 'Sidebar Right', 'mrn-base-stack' );

	return $templates;
}
add_filter( 'theme_templates', 'mrn_base_stack_add_sidebar_templates_to_post_type', 10, 4 );

/**
 * Enable the template selector for newly registered editorial CPTs.
 *
 * @param string       $post_type        Post type slug.
 * @param WP_Post_Type $post_type_object Registered post type object.
 * @return void
 */
function mrn_base_stack_enable_sidebar_template_selector( $post_type, $post_type_object ) {
	if ( ! $post_type_object instanceof WP_Post_Type || ! $post_type_object->public || ! $post_type_object->show_ui ) {
		return;
	}

	if ( mrn_base_stack_post_type_supports_sidebar_templates( $post_type ) ) {
		add_post_type_support( $post_type, 'page-attributes' );
	}
}
add_action( 'registered_post_type', 'mrn_base_stack_enable_sidebar_template_selector', 10, 2 );

/**
 * Cover built-in post types, which register before the theme is loaded.
 *
 * @return void
 */
function mrn_base_stack_enable_existing_sidebar_template_selectors() {
	foreach ( mrn_base_stack_get_sidebar_supported_post_types() as $post_type ) {
		add_post_type_support( $post_type, 'page-attributes' );
	}
}
add_action( 'init', 'mrn_base_stack_enable_existing_sidebar_template_selectors', 100 );

/**
 * Get page templates that render a sidebar shell.
 *
 * @return array<string, string>
 */
function mrn_base_stack_get_sidebar_page_template_layouts() {
	$templates = array(
		'page-sidebar-left.php'  => 'left',
		'page-sidebar-right.php' => 'right',
	);

	/**
	 * Filter page template sidebar layouts.
	 *
	 * @param array<string, string> $templates Template slug to sidebar layout.
	 */
	$templates  = apply_filters( 'mrn_base_stack_sidebar_page_template_layouts', $templates );
	$normalized = array();

	if ( ! is_array( $templates ) ) {
		return array();
	}

	foreach ( $templates as $template_slug => $layout ) {
		$template_slug = is_string( $template_slug ) ? trim( $template_slug ) : '';
		$layout        = is_string( $layout ) ? sanitize_key( $layout ) : '';

		if ( '' === $template_slug || ! in_array( $layout, array( 'left', 'right' ), true ) ) {
			continue;
		}

		$normalized[ $template_slug ] = $layout;
	}

	return $normalized;
}

/**
 * Get the sidebar layout for a page template slug.
 *
 * @param string $template_slug Page template slug.
 * @return string
 */
function mrn_base_stack_get_sidebar_layout_for_page_template( $template_slug ) {
	$template_slug = is_string( $template_slug ) ? trim( $template_slug ) : '';
	$templates     = mrn_base_stack_get_sidebar_page_template_layouts();

	return isset( $templates[ $template_slug ] ) ? $templates[ $template_slug ] : 'none';
}

/**
 * Get the page template slug for a sidebar layout.
 *
 * @param string $layout Sidebar layout.
 * @return string
 */
function mrn_base_stack_get_page_template_for_sidebar_layout( $layout ) {
	$layout    = is_string( $layout ) ? sanitize_key( $layout ) : '';
	$templates = mrn_base_stack_get_sidebar_page_template_layouts();

	foreach ( $templates as $template_slug => $template_layout ) {
		if ( $layout === $template_layout ) {
			return $template_slug;
		}
	}

	return '';
}

/**
 * Convert saved sidebar-position settings to Post/Page Attributes templates.
 *
 * @return void
 */
function mrn_base_stack_migrate_page_sidebar_layouts_to_templates() {
	$migration_key     = 'mrn_base_stack_page_sidebar_template_migration';
	$migration_version = '2026-07-16-v2';

	if ( get_option( $migration_key, '' ) === $migration_version ) {
		return;
	}

	$post_ids = get_posts(
		array(
			'post_type'              => mrn_base_stack_get_sidebar_supported_post_types(),
			'post_status'            => 'any',
			'fields'                 => 'ids',
			'posts_per_page'         => -1,
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- One-time sidebar template migration needs a targeted legacy meta lookup.
			'meta_query'             => array(
				array(
					'key'     => 'sidebar_layout',
					'value'   => array( 'left', 'right' ),
					'compare' => 'IN',
				),
			),
		)
	);

	foreach ( $post_ids as $post_id ) {
		$post_id          = absint( $post_id );
		$current_template = get_page_template_slug( $post_id );

		if ( '' !== $current_template && 'default' !== $current_template ) {
			continue;
		}

		$layout        = sanitize_key( (string) get_post_meta( $post_id, 'sidebar_layout', true ) );
		$template_slug = mrn_base_stack_get_page_template_for_sidebar_layout( $layout );

		if ( '' !== $template_slug ) {
			update_post_meta( $post_id, '_wp_page_template', $template_slug );
		}
	}

	update_option( $migration_key, $migration_version, false );
}
add_action( 'init', 'mrn_base_stack_migrate_page_sidebar_layouts_to_templates', 30 );

/**
 * Get sidebar-supported post types that still use ACF position controls.
 *
 * Pages use Page Attributes templates for the shell choice.
 *
 * @return array<int, string>
 */
function mrn_base_stack_get_sidebar_position_supported_post_types() {
	return array_values( array_diff( mrn_base_stack_get_sidebar_supported_post_types(), array( 'page' ) ) );
}

/**
 * Build ACF location rules for non-page singular sidebar position controls.
 *
 * @return array<int, array<int, array<string, string>>>
 */
function mrn_base_stack_get_sidebar_location_rules() {
	return mrn_base_stack_build_post_type_location_rules( mrn_base_stack_get_sidebar_position_supported_post_types() );
}

/**
 * Build ACF location rules for page-template sidebar content.
 *
 * @return array<int, array<int, array<string, string>>>
 */
function mrn_base_stack_get_page_template_sidebar_location_rules() {
	$locations = array();

	foreach ( array_keys( mrn_base_stack_get_sidebar_page_template_layouts() ) as $template_slug ) {
		$locations[] = array(
			array(
				'param'    => 'page_template',
				'operator' => '==',
				'value'    => $template_slug,
			),
		);
	}

	return $locations;
}

/**
 * Get top-level layout names that can be added to singular sidebars.
 *
 * This intentionally excludes nested/composite builder layouts so sidebar
 * fields do not become another full recursive builder surface. FAQ Jump Nav is
 * a utility layout and remains safe because it only reads FAQ placements from
 * the current entry.
 *
 * @return array<int, string>
 */
function mrn_base_stack_get_sidebar_layout_source_names() {
	$defaults = array(
		'body_text',
		'content_lists',
		'basic',
		'cta',
		'image_content',
		'faq_jump_nav',
	);

	$names = mrn_base_stack_normalize_builder_layout_source_names(
		apply_filters( 'mrn_base_stack_sidebar_layout_source_names', $defaults ),
		$defaults
	);

	return mrn_base_stack_filter_hidden_builder_layout_source_names( $names );
}

/**
 * Clone safe Content builder layouts for singular sidebar rows.
 *
 * Already-saved sidebar rows stay available as existing-only layouts so old
 * content remains editable without widening the add-row menu.
 *
 * @param int $post_id Optional post ID for existing-row compatibility.
 * @return array<string, array<string, mixed>>
 */
function mrn_base_stack_get_sidebar_builder_layouts( $post_id = 0 ) {
	$post_id         = absint( $post_id );
	$content_layouts = mrn_base_stack_get_content_builder_source_layouts();

	if ( empty( $content_layouts ) ) {
		return array();
	}

	if ( $post_id < 1 ) {
		$post_id = mrn_base_stack_get_builder_layout_allowlist_post_id();
	}

	$allowed_names       = mrn_base_stack_get_sidebar_layout_source_names();
	$existing_only_names = array();

	if ( $post_id > 0 ) {
		$used_names          = mrn_base_stack_get_builder_layout_allowlist_used_layout_names( $post_id, 'page_sidebar_rows' );
		$base_allowed_lookup = ! empty( $allowed_names ) ? array_fill_keys( $allowed_names, true ) : array();
		$existing_only_names = array_values(
			array_diff(
				array_filter(
					array_map( 'sanitize_key', $used_names )
				),
				array_keys( $base_allowed_lookup )
			)
		);
		$allowed_names       = array_values(
			array_unique(
				array_merge(
					$allowed_names,
					$used_names
				)
			)
		);
	}

	$allowed_lookup       = ! empty( $allowed_names ) ? array_fill_keys( $allowed_names, true ) : array();
	$existing_only_lookup = ! empty( $existing_only_names ) ? array_fill_keys( $existing_only_names, true ) : array();
	$sidebar_layouts      = array();

	foreach ( $content_layouts as $layout_key => $layout ) {
		if ( ! is_array( $layout ) ) {
			continue;
		}

		$layout_name = isset( $layout['name'] ) ? sanitize_key( (string) $layout['name'] ) : '';
		if ( '' === $layout_name || ! isset( $allowed_lookup[ $layout_name ] ) ) {
			continue;
		}

		if ( isset( $existing_only_lookup[ $layout_name ] ) ) {
			$layout['max'] = -1;
		}

		if ( isset( $layout['sub_fields'] ) && is_array( $layout['sub_fields'] ) && function_exists( 'mrn_base_stack_apply_primary_layout_field_contract' ) ) {
			$layout['sub_fields'] = mrn_base_stack_apply_primary_layout_field_contract( $layout['sub_fields'], true, $layout_name );
		}

		$sidebar_layouts[ $layout_key ] = $layout;
	}

	return ! empty( $sidebar_layouts ) ? mrn_base_stack_clone_acf_keys_with_prefix( $sidebar_layouts, 'sidebar_' ) : array();
}

/**
 * Populate singular sidebar flexible-content layouts dynamically.
 *
 * @param array<string, mixed> $field ACF field definition.
 * @return array<string, mixed>
 */
function mrn_base_stack_populate_sidebar_builder_field( $field ) {
	if ( ! is_array( $field ) ) {
		return $field;
	}

	$field['layouts'] = mrn_base_stack_get_sidebar_builder_layouts();

	return $field;
}
add_filter( 'acf/load_field/key=field_mrn_sidebar_rows', 'mrn_base_stack_populate_sidebar_builder_field', 15 );
add_filter( 'acf/prepare_field/key=field_mrn_sidebar_rows', 'mrn_base_stack_populate_sidebar_builder_field', 15 );
add_filter( 'acf/load_field/key=field_mrn_page_template_sidebar_rows', 'mrn_base_stack_populate_sidebar_builder_field', 15 );
add_filter( 'acf/prepare_field/key=field_mrn_page_template_sidebar_rows', 'mrn_base_stack_populate_sidebar_builder_field', 15 );

/**
 * Get the sidebar content field for a sidebar field group.
 *
 * @param bool                                          $layout_builder_enabled Whether builder rows should be used.
 * @param string                                        $field_key Field key.
 * @param array<int, array<int, array<string, string>>> $conditional_logic Optional conditional logic.
 * @return array<string, mixed>
 */
function mrn_base_stack_get_sidebar_content_field( $layout_builder_enabled, $field_key, array $conditional_logic = array() ) {
	if ( $layout_builder_enabled ) {
		$field = array(
			'key'          => $field_key,
			'label'        => 'Sidebar Rows',
			'name'         => 'page_sidebar_rows',
			'aria-label'   => '',
			'type'         => 'flexible_content',
			'button_label' => 'Add Sidebar Row',
			'layouts'      => array(),
			'instructions' => 'Build the sidebar with safe non-recursive row layouts from the main Content area.',
		);
	} else {
		$field = array(
			'key'          => $field_key,
			'label'        => 'Sidebar Content',
			'name'         => 'sidebar_content',
			'aria-label'   => '',
			'type'         => 'wysiwyg',
			'tabs'         => 'all',
			'toolbar'      => 'full',
			'media_upload' => 1,
			'instructions' => 'Add the content that should appear in this entry sidebar.',
		);
	}

	if ( ! empty( $conditional_logic ) ) {
		$field['conditional_logic'] = $conditional_logic;
	}

	return $field;
}

/**
 * Register theme-owned singular sidebar fields.
 *
 * @return void
 */
function mrn_base_stack_register_singular_sidebar_field_group() {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	$layout_builder_enabled       = mrn_base_stack_is_layout_builder_enabled();
	$page_template_location_rules = mrn_base_stack_get_page_template_sidebar_location_rules();

	if ( ! empty( $page_template_location_rules ) ) {
		acf_add_local_field_group(
			array(
				'key'                   => 'group_mrn_page_template_sidebar',
				'title'                 => 'Sidebar',
				'fields'                => array(
					mrn_base_stack_get_sidebar_content_field(
						$layout_builder_enabled,
						$layout_builder_enabled ? 'field_mrn_page_template_sidebar_rows' : 'field_mrn_page_template_sidebar_content'
					),
				),
				'location'              => $page_template_location_rules,
				'menu_order'            => 30,
				'position'              => 'acf_after_title',
				'style'                 => 'default',
				'label_placement'       => 'top',
				'instruction_placement' => 'label',
				'active'                => true,
				'description'           => $layout_builder_enabled
					? 'Theme-owned sidebar builder rows for sidebar page templates.'
					: 'Theme-owned sidebar content for sidebar page templates.',
				'show_in_rest'          => 1,
			)
		);
	}
}
add_action( 'acf/init', 'mrn_base_stack_register_singular_sidebar_field_group', 20 );

/**
 * Get the current entry's sidebar settings.
 *
 * @param int|null $post_id Post ID to inspect.
 * @return array<string, mixed>
 */
function mrn_base_stack_get_singular_sidebar_settings( $post_id = null ) {
	$post_id = $post_id ? (int) $post_id : get_the_ID();

	$settings = array(
		'layout' => 'none',
	);

	if ( ! $post_id ) {
		return $settings;
	}

	$post_type = sanitize_key( (string) get_post_type( $post_id ) );
	if ( '' === $post_type || ! in_array( $post_type, mrn_base_stack_get_sidebar_supported_post_types(), true ) ) {
		return $settings;
	}

	$settings['layout'] = mrn_base_stack_get_sidebar_layout_for_page_template( get_page_template_slug( $post_id ) );

	return $settings;
}

/**
 * Get rendered sidebar markup for a singular entry.
 *
 * @param int|null $post_id Post ID to inspect.
 * @return string
 */
function mrn_base_stack_get_singular_sidebar_markup( $post_id = null ) {
	$settings               = mrn_base_stack_get_singular_sidebar_settings( $post_id );
	$layout_builder_enabled = mrn_base_stack_is_layout_builder_enabled();
	$markup                 = '';

	if ( 'none' === ( $settings['layout'] ?? 'none' ) ) {
		return '';
	}

	if ( $layout_builder_enabled ) {
		$markup = mrn_base_stack_get_builder_markup( 'page_sidebar_rows', $post_id, 'mrn-content-builder mrn-content-builder--sidebar' );
	} elseif ( function_exists( 'get_field' ) ) {
		$content = get_field( 'sidebar_content', $post_id );
		$content = is_string( $content ) ? trim( $content ) : '';

		if ( '' !== $content ) {
			$markup = sprintf(
				'<div class="mrn-singular-sidebar__content">%s</div>',
				wp_kses_post( $content )
			);
		}
	}

	if ( '' === $markup ) {
		return '';
	}

	return sprintf(
		'<aside class="mrn-singular-sidebar" aria-label="%1$s">%2$s</aside>',
		esc_attr__( 'Sidebar', 'mrn-base-stack' ),
		$markup
	);
}

/**
 * Determine whether the current singular entry should render the sidebar shell.
 *
 * @param int|null $post_id Post ID to inspect.
 * @return bool
 */
function mrn_base_stack_has_singular_sidebar( $post_id = null ) {
	return '' !== mrn_base_stack_get_singular_sidebar_markup( $post_id );
}
