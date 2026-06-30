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
	$fallback_post_types = array( 'post', 'page', 'case_study', 'testimonial' );
	$post_types          = $fallback_post_types;

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
 * Build ACF location rules for the supported singular sidebar post types.
 *
 * @return array<int, array<int, array<string, string>>>
 */
function mrn_base_stack_get_sidebar_location_rules() {
	$locations = array();

	if ( function_exists( 'mrn_base_stack_build_post_type_location_rules' ) ) {
		return mrn_base_stack_build_post_type_location_rules( mrn_base_stack_get_sidebar_supported_post_types() );
	}

	foreach ( mrn_base_stack_get_sidebar_supported_post_types() as $post_type ) {
		$locations[] = array(
			array(
				'param'    => 'post_type',
				'operator' => '==',
				'value'    => $post_type,
			),
		);
	}

	return $locations;
}

/**
 * Get top-level layout names that can be added to singular sidebars.
 *
 * This intentionally excludes nested/composite builder layouts so sidebar
 * fields do not become another full recursive builder surface.
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
	);

	$names = function_exists( 'mrn_base_stack_normalize_builder_layout_source_names' )
		? mrn_base_stack_normalize_builder_layout_source_names(
			apply_filters( 'mrn_base_stack_sidebar_layout_source_names', $defaults ),
			$defaults
		)
		: $defaults;

	return function_exists( 'mrn_base_stack_filter_hidden_builder_layout_source_names' )
		? mrn_base_stack_filter_hidden_builder_layout_source_names( $names )
		: $names;
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
	if ( ! function_exists( 'mrn_base_stack_clone_acf_keys_with_prefix' ) ) {
		return array();
	}

	$post_id         = absint( $post_id );
	$content_layouts = function_exists( 'mrn_base_stack_get_content_builder_source_layouts' )
		? mrn_base_stack_get_content_builder_source_layouts()
		: array();

	if ( empty( $content_layouts ) ) {
		return array();
	}

	if ( $post_id < 1 && function_exists( 'mrn_base_stack_get_builder_layout_allowlist_post_id' ) ) {
		$post_id = mrn_base_stack_get_builder_layout_allowlist_post_id();
	}

	$allowed_names       = mrn_base_stack_get_sidebar_layout_source_names();
	$existing_only_names = array();

	if ( $post_id > 0 && function_exists( 'mrn_base_stack_get_builder_layout_allowlist_used_layout_names' ) ) {
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

/**
 * Register theme-owned singular sidebar fields.
 *
 * @return void
 */
function mrn_base_stack_register_singular_sidebar_field_group() {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	$location_rules = mrn_base_stack_get_sidebar_location_rules();
	if ( empty( $location_rules ) ) {
		return;
	}

	$layout_builder_enabled = function_exists( 'mrn_base_stack_is_layout_builder_enabled' ) && mrn_base_stack_is_layout_builder_enabled();
	$fields                 = array(
		array(
			'key'           => 'field_mrn_sidebar_layout',
			'label'         => 'Sidebar Position',
			'name'          => 'sidebar_layout',
			'aria-label'    => '',
			'type'          => 'button_group',
			'choices'       => array(
				'none'  => 'None',
				'left'  => 'Left Sidebar',
				'right' => 'Right Sidebar',
			),
			'default_value' => 'none',
			'layout'        => 'horizontal',
			'return_format' => 'value',
			'instructions'  => 'Choose whether this entry renders a sidebar, and where it sits when enabled.',
		),
	);

	if ( $layout_builder_enabled ) {
		$fields[] = array(
			'key'               => 'field_mrn_sidebar_rows',
			'label'             => 'Sidebar Rows',
			'name'              => 'page_sidebar_rows',
			'aria-label'        => '',
			'type'              => 'flexible_content',
			'button_label'      => 'Add Sidebar Row',
			'layouts'           => array(),
			'conditional_logic' => array(
				array(
					array(
						'field'    => 'field_mrn_sidebar_layout',
						'operator' => '!=',
						'value'    => 'none',
					),
				),
			),
			'instructions'      => 'Build the sidebar with safe non-recursive row layouts from the main Content area.',
		);
	} else {
		$fields[] = array(
			'key'               => 'field_mrn_sidebar_content',
			'label'             => 'Sidebar Content',
			'name'              => 'sidebar_content',
			'aria-label'        => '',
			'type'              => 'wysiwyg',
			'tabs'              => 'all',
			'toolbar'           => 'full',
			'media_upload'      => 1,
			'conditional_logic' => array(
				array(
					array(
						'field'    => 'field_mrn_sidebar_layout',
						'operator' => '!=',
						'value'    => 'none',
					),
				),
			),
			'instructions'      => 'Add the content that should appear in this entry sidebar.',
		);
	}

	acf_add_local_field_group(
		array(
			'key'                   => 'group_mrn_singular_sidebar',
			'title'                 => 'Sidebar',
			'fields'                => $fields,
			'location'              => $location_rules,
			'menu_order'            => 30,
			'position'              => 'acf_after_title',
			'style'                 => 'default',
			'label_placement'       => 'top',
			'instruction_placement' => 'label',
			'active'                => true,
			'description'           => $layout_builder_enabled
				? 'Theme-owned singular sidebar controls and builder rows for sidebar-enabled singular content types.'
				: 'Theme-owned singular sidebar controls for sidebar-enabled singular content types.',
			'show_in_rest'          => 1,
		)
	);
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

	if ( ! $post_id || ! function_exists( 'get_field' ) ) {
		return $settings;
	}

	$post_type = sanitize_key( (string) get_post_type( $post_id ) );
	if ( '' === $post_type || ! in_array( $post_type, mrn_base_stack_get_sidebar_supported_post_types(), true ) ) {
		return $settings;
	}

	$layout = get_field( 'sidebar_layout', $post_id );
	$layout = is_string( $layout ) ? sanitize_key( $layout ) : 'none';

	if ( ! in_array( $layout, array( 'none', 'left', 'right' ), true ) ) {
		$layout = 'none';
	}

	$settings['layout'] = $layout;

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
	$layout_builder_enabled = function_exists( 'mrn_base_stack_is_layout_builder_enabled' ) && mrn_base_stack_is_layout_builder_enabled();
	$markup                 = '';

	if ( 'none' === ( $settings['layout'] ?? 'none' ) ) {
		return '';
	}

	if ( $layout_builder_enabled && function_exists( 'mrn_base_stack_get_builder_markup' ) ) {
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
