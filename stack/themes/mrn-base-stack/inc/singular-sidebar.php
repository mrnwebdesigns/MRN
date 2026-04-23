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
	$layout_builder_enabled = function_exists( 'mrn_base_stack_is_layout_builder_enabled' ) && mrn_base_stack_is_layout_builder_enabled();
	$fallback_post_types    = $layout_builder_enabled
		? array( 'page_with_sidebars', 'post_with_sidebars', 'blog', 'gallery', 'case_study', 'testimonial' )
		: array( 'page_with_sidebars', 'post_with_sidebars' );

	$post_types = function_exists( 'mrn_base_stack_get_singular_shell_post_types' ) ? mrn_base_stack_get_singular_shell_post_types() : $fallback_post_types;

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

	if ( $layout_builder_enabled ) {
		$post_types = array_values(
			array_diff(
				$post_types,
				array( 'page', 'post' )
			)
		);
	} else {
		$post_types = array_values(
			array_intersect(
				$post_types,
				array( 'page_with_sidebars', 'post_with_sidebars' )
			)
		);
	}

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
 * Register theme-owned singular sidebar fields.
 *
 * @return void
 */
function mrn_base_stack_register_singular_sidebar_field_group() {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	$layout_builder_enabled = function_exists( 'mrn_base_stack_is_layout_builder_enabled' ) && mrn_base_stack_is_layout_builder_enabled();
	$content_builder_fields = function_exists( 'acf_get_fields' ) ? acf_get_fields( 'group_mrn_content_builder' ) : array();
	$sidebar_layouts        = array();
	$fields                 = array(
		array(
			'key'           => 'field_mrn_sidebar_layout',
			'label'         => 'Sidebar Position',
			'name'          => 'sidebar_layout',
			'aria-label'    => '',
			'type'          => 'button_group',
			'choices'       => array(
				'left'  => 'Left Sidebar',
				'right' => 'Right Sidebar',
			),
			'default_value' => 'right',
			'layout'        => 'horizontal',
			'return_format' => 'value',
			'instructions'  => 'Choose where the sidebar sits when this entry needs a two-column singular layout.',
		),
	);

	if ( is_array( $content_builder_fields ) && ! empty( $content_builder_fields[0]['layouts'] ) && is_array( $content_builder_fields[0]['layouts'] ) ) {
		$sidebar_layouts = mrn_base_stack_clone_acf_keys_with_prefix( $content_builder_fields[0]['layouts'], 'sidebar_' );
	}

	if ( $layout_builder_enabled && ! empty( $sidebar_layouts ) ) {
		$fields[] = array(
			'key'               => 'field_mrn_sidebar_rows',
			'label'             => 'Sidebar Rows',
			'name'              => 'page_sidebar_rows',
			'aria-label'        => '',
			'type'              => 'flexible_content',
			'button_label'      => 'Add Sidebar Row',
			'layouts'           => $sidebar_layouts,
			'instructions'      => 'Build the sidebar with the same row layouts available in the main Content area.',
		);
	} else {
		$fields[] = array(
			'key'           => 'field_mrn_sidebar_content',
			'label'         => 'Sidebar Content',
			'name'          => 'sidebar_content',
			'aria-label'    => '',
			'type'          => 'wysiwyg',
			'tabs'          => 'all',
			'toolbar'       => 'full',
			'media_upload'  => 1,
			'instructions'  => 'Add the content that should appear in this entry sidebar.',
		);
	}

	acf_add_local_field_group(
		array(
			'key'                   => 'group_mrn_singular_sidebar',
			'title'                 => 'Sidebar',
			'fields'                => $fields,
			'location'              => mrn_base_stack_get_sidebar_location_rules(),
			'menu_order'            => 30,
			'position'              => 'acf_after_title',
			'style'                 => 'default',
			'label_placement'       => 'top',
			'instruction_placement' => 'label',
			'active'                => true,
			'description'           => $layout_builder_enabled
				? 'Theme-owned singular sidebar controls and builder rows for builder-supported singular content types.'
				: 'Theme-owned singular sidebar controls for entries that use the with-sidebars shell.',
			'show_in_rest'          => 1,
		)
	);
}
add_action( 'acf/init', 'mrn_base_stack_register_singular_sidebar_field_group' );

/**
 * Get the current entry's sidebar settings.
 *
 * @param int|null $post_id Post ID to inspect.
 * @return array<string, mixed>
 */
function mrn_base_stack_get_singular_sidebar_settings( $post_id = null ) {
	$post_id = $post_id ? (int) $post_id : get_the_ID();

	$settings = array(
		'layout' => 'right',
	);

	if ( ! $post_id || ! function_exists( 'get_field' ) ) {
		return $settings;
	}

	$post_type = sanitize_key( (string) get_post_type( $post_id ) );
	if ( '' === $post_type || ! in_array( $post_type, mrn_base_stack_get_sidebar_supported_post_types(), true ) ) {
		return $settings;
	}

	$layout = get_field( 'sidebar_layout', $post_id );
	$layout = is_string( $layout ) ? sanitize_key( $layout ) : 'right';

	if ( ! in_array( $layout, array( 'left', 'right' ), true ) ) {
		$layout = 'right';
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
