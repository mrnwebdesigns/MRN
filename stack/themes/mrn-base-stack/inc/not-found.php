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
	);

	foreach ( $field_map as $option_key => $field_name ) {
		if ( '__mrn_missing__' === get_option( 'options_' . $field_name, '__mrn_missing__' ) ) {
			continue;
		}

		$options[ $option_key ] = get_field( $field_name, 'option' );
	}

	$options['show_search']   = (bool) $options['show_search'];
	$options['helpful_links'] = is_array( $options['helpful_links'] ) ? $options['helpful_links'] : array();

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
