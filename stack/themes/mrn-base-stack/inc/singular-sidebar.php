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
	$post_types = array( 'page', 'post' );

	/**
	 * Filter the post types that can opt into the singular sidebar shell.
	 *
	 * @param array<int, string> $post_types Supported post types.
	 */
	$post_types = apply_filters( 'mrn_base_stack_sidebar_supported_post_types', $post_types );

	if ( ! is_array( $post_types ) ) {
		return array( 'page', 'post' );
	}

	$post_types = array_values(
		array_unique(
			array_filter(
				array_map( 'sanitize_key', $post_types )
			)
		)
	);

	return ! empty( $post_types ) ? $post_types : array( 'page', 'post' );
}

/**
 * Build ACF location rules for the supported singular sidebar post types.
 *
 * @return array<int, array<int, array<string, string>>>
 */
function mrn_base_stack_get_sidebar_location_rules() {
	$locations = array();

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

	$content_builder_fields = function_exists( 'acf_get_fields' ) ? acf_get_fields( 'group_mrn_content_builder' ) : array();
	$sidebar_layouts        = array();

	if ( is_array( $content_builder_fields ) && ! empty( $content_builder_fields[0]['layouts'] ) && is_array( $content_builder_fields[0]['layouts'] ) ) {
		$sidebar_layouts = mrn_base_stack_clone_acf_keys_with_prefix( $content_builder_fields[0]['layouts'], 'sidebar_' );
	}

	acf_add_local_field_group(
		array(
			'key'                   => 'group_mrn_singular_sidebar',
			'title'                 => 'Sidebar',
			'fields'                => array(
				array(
					'key'           => 'field_mrn_sidebar_layout',
					'label'         => 'Sidebar Layout',
					'name'          => 'sidebar_layout',
					'aria-label'    => '',
					'type'          => 'button_group',
					'choices'       => array(
						'none'  => 'No Sidebar',
						'right' => 'Right Sidebar',
						'left'  => 'Left Sidebar',
					),
					'default_value' => 'none',
					'layout'        => 'horizontal',
					'return_format' => 'value',
					'instructions'  => 'Wrap the singular content area in a two-column shell when this entry needs a sidebar.',
				),
				array(
					'key'               => 'field_mrn_sidebar_rows',
					'label'             => 'Sidebar',
					'name'              => 'page_sidebar_rows',
					'aria-label'        => '',
					'type'              => 'flexible_content',
					'button_label'      => 'Add Sidebar Row',
					'layouts'           => $sidebar_layouts,
					'conditional_logic' => array(
						array(
							array(
								'field'    => 'field_mrn_sidebar_layout',
								'operator' => '!=',
								'value'    => 'none',
							),
						),
					),
					'instructions'      => 'Use the same builder-style rows in the sidebar that you use in the main Content area.',
				),
			),
			'location'              => mrn_base_stack_get_sidebar_location_rules(),
			'menu_order'            => 30,
			'position'              => 'acf_after_title',
			'style'                 => 'default',
			'label_placement'       => 'top',
			'instruction_placement' => 'label',
			'active'                => true,
			'description'           => 'Theme-owned singular sidebar controls and builder rows for posts and pages.',
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
		'layout' => 'none',
	);

	if ( ! $post_id || ! function_exists( 'get_field' ) ) {
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
	$settings = mrn_base_stack_get_singular_sidebar_settings( $post_id );

	if ( 'none' === $settings['layout'] ) {
		return '';
	}

	$markup = function_exists( 'mrn_base_stack_get_builder_markup' ) ? mrn_base_stack_get_builder_markup( 'page_sidebar_rows', $post_id, 'mrn-content-builder mrn-content-builder--sidebar' ) : '';

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
	$settings = mrn_base_stack_get_singular_sidebar_settings( $post_id );

	if ( 'none' === $settings['layout'] ) {
		return false;
	}

	return '' !== mrn_base_stack_get_singular_sidebar_markup( $post_id );
}
