<?php
/**
 * Services CPT registration, field groups, and rendering helpers.
 *
 * @package mrn-base-stack
 */

/**
 * Register the theme-owned Service custom post type.
 *
 * @return void
 */
function mrn_base_stack_register_service_post_type() {
	$show_ui = function_exists( 'mrn_base_stack_is_admin_cpt_visible' ) ? mrn_base_stack_is_admin_cpt_visible( 'service' ) : true;

	$labels = array(
		'name'                  => __( 'Services', 'mrn-base-stack' ),
		'singular_name'         => __( 'Service', 'mrn-base-stack' ),
		'menu_name'             => __( 'Services', 'mrn-base-stack' ),
		'name_admin_bar'        => __( 'Service', 'mrn-base-stack' ),
		'add_new'               => __( 'Add New', 'mrn-base-stack' ),
		'add_new_item'          => __( 'Add New Service', 'mrn-base-stack' ),
		'new_item'              => __( 'New Service', 'mrn-base-stack' ),
		'edit_item'             => __( 'Edit Service', 'mrn-base-stack' ),
		'view_item'             => __( 'View Service', 'mrn-base-stack' ),
		'view_items'            => __( 'View Services', 'mrn-base-stack' ),
		'all_items'             => __( 'All Services', 'mrn-base-stack' ),
		'search_items'          => __( 'Search Services', 'mrn-base-stack' ),
		'parent_item_colon'     => __( 'Parent Services:', 'mrn-base-stack' ),
		'not_found'             => __( 'No services found.', 'mrn-base-stack' ),
		'not_found_in_trash'    => __( 'No services found in Trash.', 'mrn-base-stack' ),
		'archives'              => __( 'Services', 'mrn-base-stack' ),
		'attributes'            => __( 'Service Attributes', 'mrn-base-stack' ),
		'insert_into_item'      => __( 'Insert into service', 'mrn-base-stack' ),
		'uploaded_to_this_item' => __( 'Uploaded to this service', 'mrn-base-stack' ),
		'featured_image'        => __( 'Featured image', 'mrn-base-stack' ),
		'set_featured_image'    => __( 'Set featured image', 'mrn-base-stack' ),
		'remove_featured_image' => __( 'Remove featured image', 'mrn-base-stack' ),
		'use_featured_image'    => __( 'Use as featured image', 'mrn-base-stack' ),
		'filter_items_list'     => __( 'Filter services list', 'mrn-base-stack' ),
		'items_list_navigation' => __( 'Services list navigation', 'mrn-base-stack' ),
		'items_list'            => __( 'Services list', 'mrn-base-stack' ),
		'item_published'        => __( 'Service published.', 'mrn-base-stack' ),
		'item_updated'          => __( 'Service updated.', 'mrn-base-stack' ),
	);

	register_post_type(
		'service',
		array(
			'labels'              => $labels,
			'public'              => true,
			'show_ui'             => $show_ui,
			'show_in_menu'        => $show_ui,
			'show_in_rest'        => true,
			'has_archive'         => 'services',
			'rewrite'             => array(
				'slug'       => 'services',
				'with_front' => false,
			),
			'menu_position'       => 11,
			'menu_icon'           => 'dashicons-admin-tools',
			'supports'            => array( 'title', 'excerpt', 'thumbnail', 'revisions' ),
			'taxonomies'          => array( 'category', 'post_tag' ),
			'publicly_queryable'  => true,
			'show_in_nav_menus'   => true,
			'show_in_admin_bar'   => $show_ui,
			'exclude_from_search' => false,
			'hierarchical'        => false,
			'query_var'           => true,
		)
	);
}
add_action( 'init', 'mrn_base_stack_register_service_post_type' );

/**
 * Register service-specific ACF fields.
 *
 * @return void
 */
function mrn_base_stack_register_service_field_group() {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_local_field_group(
		array(
			'key'                   => 'group_mrn_service',
			'title'                 => 'Service',
			'menu_order'            => 10,
			'fields'                => array(
				mrn_base_stack_get_inline_text_field( 'field_mrn_service_label', 'Label', 'service_label' ),
				mrn_base_stack_get_label_tag_field( 'field_mrn_service_label_tag', 'service_label_tag' ),
				mrn_base_stack_get_inline_text_field( 'field_mrn_service_heading', 'Heading', 'service_heading' ),
				mrn_base_stack_get_text_tag_field( 'field_mrn_service_heading_tag', 'service_heading_tag', 'h2', 'Heading Tag' ),
				mrn_base_stack_get_inline_text_field( 'field_mrn_service_subheading', 'Subheading', 'service_subheading' ),
				mrn_base_stack_get_text_tag_field( 'field_mrn_service_subheading_tag', 'service_subheading_tag', 'p', 'Subheading Tag' ),
				array(
					'key'          => 'field_mrn_service_summary',
					'label'        => 'Service Summary',
					'name'         => 'service_summary',
					'aria-label'   => '',
					'type'         => 'wysiwyg',
					'tabs'         => 'all',
					'toolbar'      => 'full',
					'media_upload' => 1,
					'delay'        => 0,
				),
				array(
					'key'          => 'field_mrn_service_audience',
					'label'        => 'Who This Is For',
					'name'         => 'service_audience',
					'aria-label'   => '',
					'type'         => 'wysiwyg',
					'tabs'         => 'all',
					'toolbar'      => 'full',
					'media_upload' => 1,
					'delay'        => 0,
				),
				array(
					'key'          => 'field_mrn_service_features',
					'label'        => 'What\'s Included',
					'name'         => 'service_features',
					'aria-label'   => '',
					'type'         => 'wysiwyg',
					'tabs'         => 'all',
					'toolbar'      => 'full',
					'media_upload' => 1,
					'delay'        => 0,
				),
				array(
					'key'          => 'field_mrn_service_process',
					'label'        => 'Process / Approach',
					'name'         => 'service_process',
					'aria-label'   => '',
					'type'         => 'wysiwyg',
					'tabs'         => 'all',
					'toolbar'      => 'full',
					'media_upload' => 1,
					'delay'        => 0,
				),
				array(
					'key'          => 'field_mrn_service_benefits',
					'label'        => 'Benefits',
					'name'         => 'service_benefits',
					'aria-label'   => '',
					'type'         => 'wysiwyg',
					'tabs'         => 'all',
					'toolbar'      => 'full',
					'media_upload' => 1,
					'delay'        => 0,
				),
				array(
					'key'          => 'field_mrn_service_area',
					'label'        => 'Service Area',
					'name'         => 'service_area',
					'aria-label'   => '',
					'type'         => 'text',
					'instructions' => 'Use a concise geography or audience label, such as Raleigh, NC; Nationwide; or Homeowners.',
					'wrapper'      => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_service_location_record',
					'label'         => 'Linked Location',
					'name'          => 'service_location_record',
					'aria-label'    => '',
					'type'          => 'post_object',
					'post_type'     => array( 'location' ),
					'return_format' => 'id',
					'allow_null'    => 1,
					'ui'            => 1,
					'instructions'  => 'Optional stack Location record for services tied to a reusable business location.',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'          => 'field_mrn_service_pricing_note',
					'label'        => 'Pricing Note',
					'name'         => 'service_pricing_note',
					'aria-label'   => '',
					'type'         => 'text',
					'instructions' => 'Example: Free consultation, Starting at $99, or Custom quote.',
					'wrapper'      => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_service_primary_link',
					'label'         => 'Primary CTA',
					'name'          => 'service_primary_link',
					'aria-label'    => '',
					'type'          => 'link',
					'return_format' => 'array',
					'instructions'  => 'Optional primary action for this service.',
					'wrapper'       => array(
						'width' => '50',
					),
				),
			),
			'location'              => array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => 'service',
					),
				),
			),
			'position'              => 'acf_after_title',
			'style'                 => 'default',
			'label_placement'       => 'top',
			'instruction_placement' => 'label',
			'active'                => true,
			'description'           => 'Theme-owned service fields.',
			'show_in_rest'          => 1,
		)
	);
}
add_action( 'acf/init', 'mrn_base_stack_register_service_field_group' );

/**
 * Normalize an ACF link field for service rendering.
 *
 * @param mixed $link Saved ACF link value.
 * @return array{url:string,title:string,target:string}
 */
function mrn_base_stack_normalize_service_link( $link ) {
	$data = array(
		'url'    => '',
		'title'  => '',
		'target' => '',
	);

	if ( is_array( $link ) ) {
		$data['url']    = isset( $link['url'] ) ? esc_url_raw( (string) $link['url'] ) : '';
		$data['title']  = isset( $link['title'] ) ? trim( wp_strip_all_tags( (string) $link['title'] ) ) : '';
		$data['target'] = isset( $link['target'] ) && '_blank' === $link['target'] ? '_blank' : '';
	} elseif ( is_string( $link ) ) {
		$data['url'] = esc_url_raw( $link );
	}

	if ( '' !== $data['url'] && '' === $data['title'] ) {
		$data['title'] = __( 'Learn More', 'mrn-base-stack' );
	}

	return $data;
}

/**
 * Get public service data for a post.
 *
 * @param int|null $post_id Post ID to inspect.
 * @return array<string, mixed>
 */
function mrn_base_stack_get_service_data( $post_id = null ) {
	$post_id = $post_id ? (int) $post_id : get_the_ID();

	$data = array(
		'label'           => '',
		'heading'         => '',
		'subheading'      => '',
		'summary'         => '',
		'audience'        => '',
		'features'        => '',
		'process'         => '',
		'benefits'        => '',
		'area'            => '',
		'location_record' => 0,
		'pricing_note'    => '',
		'primary_link'    => array(
			'url'    => '',
			'title'  => '',
			'target' => '',
		),
	);

	if ( ! $post_id || ! function_exists( 'get_field' ) ) {
		return $data;
	}

	foreach ( array( 'label', 'heading', 'subheading' ) as $key ) {
		$value = get_field( 'service_' . $key, $post_id );
		if ( is_string( $value ) ) {
			$data[ $key ] = trim( $value );
		}
	}

	foreach ( array( 'summary', 'audience', 'features', 'process', 'benefits' ) as $key ) {
		$value = get_field( 'service_' . $key, $post_id );
		if ( is_string( $value ) ) {
			$data[ $key ] = $value;
		}
	}

	foreach ( array( 'area', 'pricing_note' ) as $key ) {
		$value = get_field( 'service_' . $key, $post_id );
		if ( is_string( $value ) ) {
			$data[ $key ] = trim( $value );
		}
	}

	$data['location_record'] = absint( get_field( 'service_location_record', $post_id ) );
	$data['primary_link']    = mrn_base_stack_normalize_service_link( get_field( 'service_primary_link', $post_id ) );

	return $data;
}

/**
 * Get a compact service excerpt.
 *
 * @param int|null $post_id Post ID to inspect.
 * @param int      $length  Maximum word count.
 * @return string
 */
function mrn_base_stack_get_service_excerpt( $post_id = null, $length = 32 ) {
	$post_id = $post_id ? (int) $post_id : get_the_ID();

	if ( ! $post_id ) {
		return '';
	}

	$excerpt = get_the_excerpt( $post_id );
	if ( '' !== trim( (string) $excerpt ) ) {
		return wp_trim_words( wp_strip_all_tags( (string) $excerpt ), $length );
	}

	$data = mrn_base_stack_get_service_data( $post_id );
	foreach ( array( 'summary', 'features', 'benefits' ) as $key ) {
		if ( ! empty( $data[ $key ] ) ) {
			return wp_trim_words( wp_strip_all_tags( (string) $data[ $key ] ), $length );
		}
	}

	return '';
}
