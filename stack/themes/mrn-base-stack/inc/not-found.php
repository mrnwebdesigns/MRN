<?php
/**
 * Editable 404 page settings.
 *
 * @package mrn-base-stack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the theme-owned 404 options page.
 */
function mrn_base_stack_register_not_found_options_page() {
	if ( ! function_exists( 'acf_add_options_page' ) ) {
		return;
	}

	acf_add_options_page(
		array(
			'page_title' => __( '404 Page', 'mrn-base-stack' ),
			'menu_title' => __( '404 Page', 'mrn-base-stack' ),
			'menu_slug'  => 'mrn-404-page',
			'capability' => 'edit_theme_options',
			'redirect'   => false,
			'position'   => 63,
			'icon_url'   => 'dashicons-location-alt',
		)
	);
}
add_action( 'acf/init', 'mrn_base_stack_register_not_found_options_page' );

/**
 * Return the builder layouts that make sense on a 404 page.
 *
 * @return array<int, string>
 */
function mrn_base_stack_get_not_found_builder_layout_source_names() {
	$defaults = array( 'body_text', 'content_lists', 'wpforms' );

	/**
	 * Filter the builder layouts available on the 404 options page.
	 *
	 * Layouts are also checked against this list during rendering.
	 *
	 * @param array<int, string> $layouts Allowed builder layout names.
	 */
	$layouts = apply_filters( 'mrn_base_stack_not_found_builder_layout_source_names', $defaults );
	$layouts = is_array( $layouts ) ? $layouts : $defaults;

	return array_values(
		array_unique(
			array_filter(
				array_map( 'sanitize_key', $layouts )
			)
		)
	);
}

/**
 * Clone the safe 404 layouts from the canonical Content builder.
 *
 * @return array<string, array<string, mixed>>
 */
function mrn_base_stack_get_not_found_builder_layouts() {
	if ( ! function_exists( 'mrn_base_stack_get_content_builder_source_layouts' ) || ! function_exists( 'mrn_base_stack_clone_acf_keys_with_prefix' ) ) {
		return array();
	}

	$source_layouts = mrn_base_stack_get_content_builder_source_layouts();
	$allowed_lookup = array_fill_keys( mrn_base_stack_get_not_found_builder_layout_source_names(), true );
	$layouts        = array();

	foreach ( $source_layouts as $layout_key => $layout ) {
		if ( ! is_array( $layout ) ) {
			continue;
		}

		$layout_name = isset( $layout['name'] ) ? sanitize_key( (string) $layout['name'] ) : '';
		if ( '' === $layout_name || ! isset( $allowed_lookup[ $layout_name ] ) ) {
			continue;
		}

		if ( 'content_lists' === $layout_name && ! empty( $layout['sub_fields'] ) && is_array( $layout['sub_fields'] ) ) {
			$layout['sub_fields'] = array_values(
				array_filter(
					$layout['sub_fields'],
					static function ( $field ) {
						return ! is_array( $field ) || 'enable_pagination' !== ( $field['name'] ?? '' );
					}
				)
			);
		}

		$layouts[ $layout_key ] = $layout;
	}

	return mrn_base_stack_clone_acf_keys_with_prefix( $layouts, 'not_found_' );
}

/**
 * Register editable 404 page fields.
 */
function mrn_base_stack_register_not_found_field_group() {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_local_field_group(
		array(
			'key'                   => 'group_mrn_not_found_page',
			'title'                 => __( '404 Page Content', 'mrn-base-stack' ),
			'fields'                => array(
				array(
					'key'           => 'field_mrn_404_eyebrow',
					'label'         => __( 'Eyebrow', 'mrn-base-stack' ),
					'name'          => 'not_found_eyebrow',
					'type'          => 'text',
					'default_value' => __( 'Well, this is awkward.', 'mrn-base-stack' ),
				),
				array(
					'key'           => 'field_mrn_404_title',
					'label'         => __( 'Heading', 'mrn-base-stack' ),
					'name'          => 'not_found_title',
					'type'          => 'text',
					'required'      => 1,
					'default_value' => __( 'This page wandered off.', 'mrn-base-stack' ),
				),
				array(
					'key'           => 'field_mrn_404_message',
					'label'         => __( 'Message', 'mrn-base-stack' ),
					'name'          => 'not_found_message',
					'type'          => 'wysiwyg',
					'default_value' => __( '<p>The link may be outdated, or the page may have moved. Let&rsquo;s get you pointed in the right direction.</p>', 'mrn-base-stack' ),
					'tabs'          => 'visual',
					'toolbar'       => 'basic',
					'media_upload'  => 0,
				),
				array(
					'key'           => 'field_mrn_404_home_label',
					'label'         => __( 'Home Button Label', 'mrn-base-stack' ),
					'name'          => 'not_found_home_label',
					'type'          => 'text',
					'required'      => 1,
					'default_value' => __( 'Take me home', 'mrn-base-stack' ),
				),
				array(
					'key'           => 'field_mrn_404_show_search',
					'label'         => __( 'Show Search', 'mrn-base-stack' ),
					'name'          => 'not_found_show_search',
					'type'          => 'true_false',
					'default_value' => 1,
					'ui'            => 1,
				),
				array(
					'key'               => 'field_mrn_404_search_heading',
					'label'             => __( 'Search Heading', 'mrn-base-stack' ),
					'name'              => 'not_found_search_heading',
					'type'              => 'text',
					'required'          => 1,
					'default_value'     => __( 'Search for what you need', 'mrn-base-stack' ),
					'conditional_logic' => array(
						array(
							array(
								'field'    => 'field_mrn_404_show_search',
								'operator' => '==',
								'value'    => '1',
							),
						),
					),
				),
				array(
					'key'           => 'field_mrn_404_links_heading',
					'label'         => __( 'Helpful Links Heading', 'mrn-base-stack' ),
					'name'          => 'not_found_links_heading',
					'type'          => 'text',
					'required'      => 1,
					'default_value' => __( 'Or try one of these', 'mrn-base-stack' ),
				),
				array(
					'key'          => 'field_mrn_404_helpful_links',
					'label'        => __( 'Helpful Links', 'mrn-base-stack' ),
					'name'         => 'not_found_helpful_links',
					'type'         => 'repeater',
					'instructions' => __( 'Add a few high-value destinations, such as About, Services, or Contact.', 'mrn-base-stack' ),
					'layout'       => 'table',
					'button_label' => __( 'Add Helpful Link', 'mrn-base-stack' ),
					'sub_fields'   => array(
						array(
							'key'      => 'field_mrn_404_helpful_link',
							'label'    => __( 'Link', 'mrn-base-stack' ),
							'name'     => 'link',
							'type'     => 'link',
							'required' => 1,
						),
					),
				),
				array(
					'key'          => 'field_mrn_404_content_rows',
					'label'        => __( 'Additional Content', 'mrn-base-stack' ),
					'name'         => 'not_found_content_rows',
					'type'         => 'flexible_content',
					'instructions' => __( 'Add focused recovery content below the 404 panel. Available layouts are limited to Text, Content listings, and WPForms.', 'mrn-base-stack' ),
					'button_label' => __( 'Add Content Row', 'mrn-base-stack' ),
					'layouts'      => mrn_base_stack_get_not_found_builder_layouts(),
				),
			),
			'location'              => array(
				array(
					array(
						'param'    => 'options_page',
						'operator' => '==',
						'value'    => 'mrn-404-page',
					),
				),
			),
			'menu_order'            => 0,
			'position'              => 'normal',
			'style'                 => 'default',
			'label_placement'       => 'top',
			'instruction_placement' => 'label',
			'active'                => true,
		)
	);
}
add_action( 'acf/init', 'mrn_base_stack_register_not_found_field_group', 20 );

/**
 * Return the editable 404 page content with resilient theme defaults.
 *
 * @return array<string, mixed>
 */
function mrn_base_stack_get_not_found_options() {
	$options = array(
		'eyebrow'        => __( 'Well, this is awkward.', 'mrn-base-stack' ),
		'title'          => __( 'This page wandered off.', 'mrn-base-stack' ),
		'message'        => __( '<p>The link may be outdated, or the page may have moved. Let&rsquo;s get you pointed in the right direction.</p>', 'mrn-base-stack' ),
		'home_label'     => __( 'Take me home', 'mrn-base-stack' ),
		'show_search'    => true,
		'search_heading' => __( 'Search for what you need', 'mrn-base-stack' ),
		'links_heading'  => __( 'Or try one of these', 'mrn-base-stack' ),
		'helpful_links'  => array(),
		'content_rows'   => array(),
	);

	if ( ! function_exists( 'get_field' ) ) {
		return $options;
	}

	$field_map = array(
		'eyebrow'        => 'not_found_eyebrow',
		'title'          => 'not_found_title',
		'message'        => 'not_found_message',
		'home_label'     => 'not_found_home_label',
		'show_search'    => 'not_found_show_search',
		'search_heading' => 'not_found_search_heading',
		'links_heading'  => 'not_found_links_heading',
		'helpful_links'  => 'not_found_helpful_links',
		'content_rows'   => 'not_found_content_rows',
	);

	foreach ( $field_map as $option_key => $field_name ) {
		if ( '__mrn_missing__' === get_option( 'options_' . $field_name, '__mrn_missing__' ) ) {
			continue;
		}

		$options[ $option_key ] = get_field( $field_name, 'option' );
	}

	$options['show_search']   = (bool) $options['show_search'];
	$options['helpful_links'] = is_array( $options['helpful_links'] ) ? $options['helpful_links'] : array();
	$options['content_rows']  = is_array( $options['content_rows'] ) ? $options['content_rows'] : array();

	$required_defaults = array(
		'title'          => __( 'This page wandered off.', 'mrn-base-stack' ),
		'home_label'     => __( 'Take me home', 'mrn-base-stack' ),
		'search_heading' => __( 'Search for what you need', 'mrn-base-stack' ),
		'links_heading'  => __( 'Or try one of these', 'mrn-base-stack' ),
	);

	foreach ( $required_defaults as $option_key => $default_value ) {
		if ( ! is_string( $options[ $option_key ] ) || '' === trim( $options[ $option_key ] ) ) {
			$options[ $option_key ] = $default_value;
		}
	}

	return $options;
}

/**
 * Render allowlisted builder rows beneath the 404 recovery panel.
 *
 * @param array<int, array<string, mixed>> $rows Builder rows from theme options.
 * @return bool True when at least one row was rendered.
 */
function mrn_base_stack_render_not_found_content( array $rows ) {
	if ( empty( $rows ) || ! function_exists( 'mrn_base_stack_render_builder_rows' ) ) {
		return false;
	}

	$allowed_lookup = array_fill_keys( mrn_base_stack_get_not_found_builder_layout_source_names(), true );
	$allowed_rows   = array();

	foreach ( $rows as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}

		$layout_name = isset( $row['acf_fc_layout'] ) ? sanitize_key( (string) $row['acf_fc_layout'] ) : '';
		if ( '' === $layout_name || ! isset( $allowed_lookup[ $layout_name ] ) ) {
			continue;
		}

		if ( 'content_lists' === $layout_name ) {
			$row['enable_pagination'] = false;
		}

		$allowed_rows[] = $row;
	}

	return mrn_base_stack_render_builder_rows(
		$allowed_rows,
		0,
		'not_found_content_rows',
		'mrn-content-builder mrn-content-builder--404'
	);
}
