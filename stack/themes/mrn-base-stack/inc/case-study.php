<?php
/**
 * Case Study CPT registration, field groups, and rendering helpers.
 *
 * @package mrn-base-stack
 */

/**
 * Register the theme-owned Case Study custom post type.
 *
 * @return void
 */
function mrn_base_stack_register_case_study_post_type() {
	$show_ui = function_exists( 'mrn_base_stack_is_admin_cpt_visible' ) ? mrn_base_stack_is_admin_cpt_visible( 'case_study' ) : true;

	$labels = array(
		'name'                  => __( 'Case Studies', 'mrn-base-stack' ),
		'singular_name'         => __( 'Case Study', 'mrn-base-stack' ),
		'menu_name'             => __( 'Case Studies', 'mrn-base-stack' ),
		'name_admin_bar'        => __( 'Case Study', 'mrn-base-stack' ),
		'add_new'               => __( 'Add New', 'mrn-base-stack' ),
		'add_new_item'          => __( 'Add New Case Study', 'mrn-base-stack' ),
		'new_item'              => __( 'New Case Study', 'mrn-base-stack' ),
		'edit_item'             => __( 'Edit Case Study', 'mrn-base-stack' ),
		'view_item'             => __( 'View Case Study', 'mrn-base-stack' ),
		'view_items'            => __( 'View Case Studies', 'mrn-base-stack' ),
		'all_items'             => __( 'All Case Studies', 'mrn-base-stack' ),
		'search_items'          => __( 'Search Case Studies', 'mrn-base-stack' ),
		'parent_item_colon'     => __( 'Parent Case Studies:', 'mrn-base-stack' ),
		'not_found'             => __( 'No case studies found.', 'mrn-base-stack' ),
		'not_found_in_trash'    => __( 'No case studies found in Trash.', 'mrn-base-stack' ),
		'archives'              => __( 'Case Study Archives', 'mrn-base-stack' ),
		'attributes'            => __( 'Case Study Attributes', 'mrn-base-stack' ),
		'insert_into_item'      => __( 'Insert into case study', 'mrn-base-stack' ),
		'uploaded_to_this_item' => __( 'Uploaded to this case study', 'mrn-base-stack' ),
		'featured_image'        => __( 'Featured image', 'mrn-base-stack' ),
		'set_featured_image'    => __( 'Set featured image', 'mrn-base-stack' ),
		'remove_featured_image' => __( 'Remove featured image', 'mrn-base-stack' ),
		'use_featured_image'    => __( 'Use as featured image', 'mrn-base-stack' ),
		'filter_items_list'     => __( 'Filter case studies list', 'mrn-base-stack' ),
		'items_list_navigation' => __( 'Case studies list navigation', 'mrn-base-stack' ),
		'items_list'            => __( 'Case studies list', 'mrn-base-stack' ),
		'item_published'        => __( 'Case Study published.', 'mrn-base-stack' ),
		'item_updated'          => __( 'Case Study updated.', 'mrn-base-stack' ),
	);

	register_post_type(
		'case_study',
		array(
			'labels'              => $labels,
			'public'              => true,
			'show_ui'             => $show_ui,
			'show_in_menu'        => $show_ui,
			'show_in_rest'        => true,
			'has_archive'         => true,
			'rewrite'             => array(
				'slug'       => 'case-studies',
				'with_front' => false,
			),
			'menu_position'       => 9,
			'menu_icon'           => 'dashicons-portfolio',
			'supports'            => array( 'title', 'revisions' ),
			'publicly_queryable'  => true,
			'show_in_nav_menus'   => true,
			'show_in_admin_bar'   => $show_ui,
			'exclude_from_search' => false,
			'hierarchical'        => false,
			'query_var'           => true,
		)
	);
}
add_action( 'init', 'mrn_base_stack_register_case_study_post_type' );

/**
 * Register case-study-specific ACF fields.
 *
 * @return void
 */
function mrn_base_stack_register_case_study_field_group() {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_local_field_group(
		array(
			'key'                   => 'group_mrn_case_study',
			'title'                 => 'Case Study',
			'menu_order'            => 10,
			'fields'                => array(
				array(
					'key'          => 'field_mrn_case_study_label',
					'label'        => 'Label',
					'name'         => 'case_study_label',
					'aria-label'   => '',
					'type'         => 'text',
					'instructions' => 'Limited inline HTML allowed: span, strong, em, br.',
					'wrapper'      => array(
						'width' => '33',
					),
				),
				array(
					'key'          => 'field_mrn_case_study_heading',
					'label'        => 'Heading',
					'name'         => 'case_study_heading',
					'aria-label'   => '',
					'type'         => 'text',
					'instructions' => 'Limited inline HTML allowed: span, strong, em, br.',
					'wrapper'      => array(
						'width' => '75',
					),
				),
				array(
					'key'          => 'field_mrn_case_study_subheading',
					'label'        => 'Subheading',
					'name'         => 'case_study_subheading',
					'aria-label'   => '',
					'type'         => 'text',
					'instructions' => 'Limited inline HTML allowed: span, strong, em, br.',
					'wrapper'      => array(
						'width' => '75',
					),
				),
				array(
					'key'          => 'field_mrn_case_study_client_overview',
					'label'        => 'Client Overview',
					'name'         => 'case_study_client_overview',
					'aria-label'   => '',
					'type'         => 'wysiwyg',
					'tabs'         => 'all',
					'toolbar'      => 'full',
					'media_upload' => 1,
					'delay'        => 0,
				),
				array(
					'key'          => 'field_mrn_case_study_challenge',
					'label'        => 'The Challenge',
					'name'         => 'case_study_challenge',
					'aria-label'   => '',
					'type'         => 'wysiwyg',
					'tabs'         => 'all',
					'toolbar'      => 'full',
					'media_upload' => 1,
					'delay'        => 0,
				),
				array(
					'key'          => 'field_mrn_case_study_services',
					'label'        => 'Services We Provided',
					'name'         => 'case_study_services',
					'aria-label'   => '',
					'type'         => 'repeater',
					'layout'       => 'row',
					'button_label' => 'Add Service',
					'collapsed'    => 'field_mrn_case_study_services_text',
					'sub_fields'   => array(
						array(
							'key'          => 'field_mrn_case_study_services_text',
							'label'        => 'Text',
							'name'         => 'text',
							'aria-label'   => '',
							'type'         => 'wysiwyg',
							'tabs'         => 'visual',
							'toolbar'      => 'basic',
							'media_upload' => 0,
							'delay'        => 0,
							'wrapper'      => array(
								'width' => '60',
							),
						),
						array(
							'key'           => 'field_mrn_case_study_services_image',
							'label'         => 'Image',
							'name'          => 'image',
							'aria-label'    => '',
							'type'          => 'image',
							'return_format' => 'array',
							'preview_size'  => 'medium',
							'library'       => 'all',
							'mime_types'    => 'jpg,jpeg,png,gif,webp,svg',
							'wrapper'       => array(
								'width' => '40',
							),
						),
						array(
							'key'           => 'field_mrn_case_study_services_image_alignment',
							'label'         => 'Image Position',
							'name'          => 'image_position',
							'aria-label'    => '',
							'type'          => 'button_group',
							'choices'       => array(
								'left'  => 'Image Left',
								'right' => 'Image Right',
							),
							'default_value' => 'right',
							'layout'        => 'horizontal',
							'return_format' => 'value',
							'wrapper'       => array(
								'width' => '100',
							),
						),
					),
				),
				array(
					'key'          => 'field_mrn_case_study_strategy_content',
					'label'        => 'Strategy and Approach',
					'name'         => 'case_study_strategy_content',
					'aria-label'   => '',
					'type'         => 'wysiwyg',
					'tabs'         => 'all',
					'toolbar'      => 'full',
					'media_upload' => 1,
					'delay'        => 0,
					'wrapper'      => array(
						'width' => '60',
					),
				),
				array(
					'key'           => 'field_mrn_case_study_strategy_image',
					'label'         => 'Image',
					'name'          => 'case_study_strategy_image',
					'aria-label'    => '',
					'type'          => 'image',
					'return_format' => 'array',
					'preview_size'  => 'medium',
					'library'       => 'all',
					'mime_types'    => 'jpg,jpeg,png,gif,webp,svg',
					'wrapper'       => array(
						'width' => '40',
					),
				),
				array(
					'key'           => 'field_mrn_case_study_strategy_image_alignment',
					'label'         => 'Image Position',
					'name'          => 'case_study_strategy_image_position',
					'aria-label'    => '',
					'type'          => 'button_group',
					'choices'       => array(
						'left'  => 'Image Left',
						'right' => 'Image Right',
					),
					'default_value' => 'right',
					'layout'        => 'horizontal',
					'return_format' => 'value',
				),
			),
			'location'              => array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => 'case_study',
					),
				),
			),
			'position'              => 'acf_after_title',
			'style'                 => 'default',
			'label_placement'       => 'top',
			'instruction_placement' => 'label',
			'active'                => true,
			'description'           => 'Theme-owned case study fields.',
			'show_in_rest'          => 1,
		)
	);
}
add_action( 'acf/init', 'mrn_base_stack_register_case_study_field_group' );

/**
 * Get the public case study data for a post.
 *
 * @param int|null $post_id Post ID to inspect.
 * @return array<string, mixed>
 */
function mrn_base_stack_get_case_study_data( $post_id = null ) {
	$post_id = $post_id ? (int) $post_id : get_the_ID();

	$data = array(
		'label'                   => '',
		'heading'                 => '',
		'subheading'              => '',
		'client_overview'         => '',
		'challenge'               => '',
		'services'                => array(),
		'strategy_content'        => '',
		'strategy_image'          => null,
		'strategy_image_position' => 'right',
	);

	if ( ! $post_id || ! function_exists( 'get_field' ) ) {
		return $data;
	}

	$label = get_field( 'case_study_label', $post_id );
	if ( is_string( $label ) ) {
		$data['label'] = trim( $label );
	}

	$heading = get_field( 'case_study_heading', $post_id );
	if ( is_string( $heading ) && '' !== trim( $heading ) ) {
		$data['heading'] = trim( $heading );
	}

	$subheading = get_field( 'case_study_subheading', $post_id );
	if ( is_string( $subheading ) ) {
		$data['subheading'] = trim( $subheading );
	}

	$client_overview = get_field( 'case_study_client_overview', $post_id );
	if ( is_string( $client_overview ) ) {
		$data['client_overview'] = $client_overview;
	}

	$challenge = get_field( 'case_study_challenge', $post_id );
	if ( is_string( $challenge ) ) {
		$data['challenge'] = $challenge;
	}

	$services = get_field( 'case_study_services', $post_id );
	if ( is_array( $services ) ) {
		foreach ( $services as $service ) {
			if ( ! is_array( $service ) ) {
				continue;
			}

			$text           = isset( $service['text'] ) && is_string( $service['text'] ) ? $service['text'] : '';
			$image          = isset( $service['image'] ) && is_array( $service['image'] ) ? $service['image'] : null;
			$image_position = isset( $service['image_position'] ) && is_string( $service['image_position'] ) ? sanitize_key( $service['image_position'] ) : 'right';

			if ( ! in_array( $image_position, array( 'left', 'right' ), true ) ) {
				$image_position = 'right';
			}

			if ( '' === trim( wp_strip_all_tags( $text ) ) && ! is_array( $image ) ) {
				continue;
			}

			$data['services'][] = array(
				'text'           => $text,
				'image'          => $image,
				'image_position' => $image_position,
			);
		}
	}

	$strategy_content = get_field( 'case_study_strategy_content', $post_id );
	if ( is_string( $strategy_content ) ) {
		$data['strategy_content'] = $strategy_content;
	}

	$strategy_image = get_field( 'case_study_strategy_image', $post_id );
	if ( is_array( $strategy_image ) ) {
		$data['strategy_image'] = $strategy_image;
	}

	$strategy_image_position = get_field( 'case_study_strategy_image_position', $post_id );
	if ( is_string( $strategy_image_position ) ) {
		$strategy_image_position = sanitize_key( $strategy_image_position );
		if ( in_array( $strategy_image_position, array( 'left', 'right' ), true ) ) {
			$data['strategy_image_position'] = $strategy_image_position;
		}
	}

	return $data;
}

/**
 * Build a short plain-text excerpt from case-study body fields.
 *
 * @param int|null $post_id Post ID to inspect.
 * @param int      $length  Excerpt length in words.
 * @return string
 */
function mrn_base_stack_get_case_study_excerpt( $post_id = null, $length = 32 ) {
	$data = mrn_base_stack_get_case_study_data( $post_id );

	$segments = array(
		isset( $data['client_overview'] ) && is_string( $data['client_overview'] ) ? $data['client_overview'] : '',
		isset( $data['challenge'] ) && is_string( $data['challenge'] ) ? $data['challenge'] : '',
		isset( $data['strategy_content'] ) && is_string( $data['strategy_content'] ) ? $data['strategy_content'] : '',
	);

	$content = implode( ' ', array_filter( $segments ) );
	$content = wp_strip_all_tags( $content );
	$content = preg_replace( '/\s+/', ' ', $content );
	$content = is_string( $content ) ? trim( $content ) : '';

	if ( '' === $content ) {
		return '';
	}

	return wp_trim_words( $content, (int) $length, '...' );
}

/**
 * Get case-study markup adapted for SmartCrawl content analysis.
 *
 * @param int|null $post_id Post ID to inspect.
 * @return string
 */
function mrn_base_stack_get_case_study_smartcrawl_markup( $post_id = null ) {
	$post_id = $post_id ? (int) $post_id : get_the_ID();
	if ( ! $post_id ) {
		return '';
	}

	$data  = mrn_base_stack_get_case_study_data( $post_id );
	$parts = array();

	if ( ! empty( $data['client_overview'] ) && is_string( $data['client_overview'] ) ) {
		$parts[] = '<h2>' . esc_html__( 'Client Overview', 'mrn-base-stack' ) . '</h2>' . wp_kses_post( $data['client_overview'] );
	}

	if ( ! empty( $data['challenge'] ) && is_string( $data['challenge'] ) ) {
		$parts[] = '<h2>' . esc_html__( 'The Challenge', 'mrn-base-stack' ) . '</h2>' . wp_kses_post( $data['challenge'] );
	}

	if ( ! empty( $data['services'] ) && is_array( $data['services'] ) ) {
		$service_markup = array();

		foreach ( $data['services'] as $service ) {
			if ( ! is_array( $service ) ) {
				continue;
			}

			$text = isset( $service['text'] ) && is_string( $service['text'] ) ? $service['text'] : '';
			if ( '' === trim( wp_strip_all_tags( $text ) ) ) {
				continue;
			}

			$service_markup[] = '<li>' . wp_kses_post( $text ) . '</li>';
		}

		if ( ! empty( $service_markup ) ) {
			$parts[] = '<h2>' . esc_html__( 'Services We Provided', 'mrn-base-stack' ) . '</h2><ul>' . implode( '', $service_markup ) . '</ul>';
		}
	}

	if ( ! empty( $data['strategy_content'] ) && is_string( $data['strategy_content'] ) ) {
		$parts[] = '<h2>' . esc_html__( 'Strategy and Approach', 'mrn-base-stack' ) . '</h2>' . wp_kses_post( $data['strategy_content'] );
	}

	return trim( implode( "\n", $parts ) );
}
