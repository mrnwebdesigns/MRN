<?php
/**
 * Testimonial CPT registration and rendering helpers.
 *
 * @package mrn-base-stack
 */

/**
 * Register the theme-owned Testimonial custom post type.
 *
 * @return void
 */
function mrn_base_stack_register_testimonial_post_type() {
	$show_ui = function_exists( 'mrn_base_stack_is_admin_cpt_visible' ) ? mrn_base_stack_is_admin_cpt_visible( 'testimonial' ) : true;

	$labels = array(
		'name'                  => __( 'Testimonials', 'mrn-base-stack' ),
		'singular_name'         => __( 'Testimonial', 'mrn-base-stack' ),
		'menu_name'             => __( 'Testimonials', 'mrn-base-stack' ),
		'name_admin_bar'        => __( 'Testimonial', 'mrn-base-stack' ),
		'add_new'               => __( 'Add New', 'mrn-base-stack' ),
		'add_new_item'          => __( 'Add New Testimonial', 'mrn-base-stack' ),
		'new_item'              => __( 'New Testimonial', 'mrn-base-stack' ),
		'edit_item'             => __( 'Edit Testimonial', 'mrn-base-stack' ),
		'view_item'             => __( 'View Testimonial', 'mrn-base-stack' ),
		'view_items'            => __( 'View Testimonials', 'mrn-base-stack' ),
		'all_items'             => __( 'All Testimonials', 'mrn-base-stack' ),
		'search_items'          => __( 'Search Testimonials', 'mrn-base-stack' ),
		'parent_item_colon'     => __( 'Parent Testimonials:', 'mrn-base-stack' ),
		'not_found'             => __( 'No testimonials found.', 'mrn-base-stack' ),
		'not_found_in_trash'    => __( 'No testimonials found in Trash.', 'mrn-base-stack' ),
		'archives'              => __( 'Testimonial Archives', 'mrn-base-stack' ),
		'attributes'            => __( 'Testimonial Attributes', 'mrn-base-stack' ),
		'insert_into_item'      => __( 'Insert into testimonial', 'mrn-base-stack' ),
		'uploaded_to_this_item' => __( 'Uploaded to this testimonial', 'mrn-base-stack' ),
		'featured_image'        => __( 'Featured image', 'mrn-base-stack' ),
		'set_featured_image'    => __( 'Set featured image', 'mrn-base-stack' ),
		'remove_featured_image' => __( 'Remove featured image', 'mrn-base-stack' ),
		'use_featured_image'    => __( 'Use as featured image', 'mrn-base-stack' ),
		'filter_items_list'     => __( 'Filter testimonials list', 'mrn-base-stack' ),
		'items_list_navigation' => __( 'Testimonials list navigation', 'mrn-base-stack' ),
		'items_list'            => __( 'Testimonials list', 'mrn-base-stack' ),
		'item_published'        => __( 'Testimonial published.', 'mrn-base-stack' ),
		'item_updated'          => __( 'Testimonial updated.', 'mrn-base-stack' ),
	);

	register_post_type(
		'testimonial',
		array(
			'labels'              => $labels,
			'public'              => true,
			'show_ui'             => $show_ui,
			'show_in_menu'        => $show_ui,
			'show_in_rest'        => true,
			'has_archive'         => true,
			'rewrite'             => array(
				'slug'       => 'testimonials',
				'with_front' => false,
			),
			'menu_position'       => 8,
			'menu_icon'           => 'dashicons-format-quote',
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
add_action( 'init', 'mrn_base_stack_register_testimonial_post_type' );

/**
 * Register testimonial-specific ACF fields.
 *
 * @return void
 */
function mrn_base_stack_register_testimonial_field_group() {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_local_field_group(
		array(
			'key'                   => 'group_mrn_testimonial',
			'title'                 => 'Testimonial',
			'menu_order'            => 10,
			'fields'                => array(
				mrn_base_stack_get_inline_text_field( 'field_mrn_testimonial_label', 'Label', 'testimonial_label' ),
				mrn_base_stack_get_label_tag_field( 'field_mrn_testimonial_label_tag', 'testimonial_label_tag' ),
				mrn_base_stack_get_inline_text_field( 'field_mrn_testimonial_heading', 'Heading', 'testimonial_heading' ),
				mrn_base_stack_get_text_tag_field( 'field_mrn_testimonial_heading_tag', 'testimonial_heading_tag', 'h2', 'Heading Tag' ),
				mrn_base_stack_get_inline_text_field( 'field_mrn_testimonial_subheading', 'Subheading', 'testimonial_subheading' ),
				mrn_base_stack_get_text_tag_field( 'field_mrn_testimonial_subheading_tag', 'testimonial_subheading_tag', 'p', 'Subheading Tag' ),
				array(
					'key'          => 'field_mrn_testimonial_name',
					'label'        => 'Name',
					'name'         => 'testimonial_name',
					'aria-label'   => '',
					'type'         => 'text',
					'instructions' => 'First/last name',
					'required'     => 1,
					'wrapper'      => array(
						'width' => '50',
					),
				),
				array(
					'key'        => 'field_mrn_testimonial_company',
					'label'      => 'Company',
					'name'       => 'testimonial_company',
					'aria-label' => '',
					'type'       => 'text',
					'wrapper'    => array(
						'width' => '50',
					),
				),
				array(
					'key'        => 'field_mrn_testimonial_position',
					'label'      => 'Position',
					'name'       => 'testimonial_position',
					'aria-label' => '',
					'type'       => 'text',
					'wrapper'    => array(
						'width' => '50',
					),
				),
				array(
					'key'        => 'field_mrn_testimonial_website_url',
					'label'      => 'Website URL',
					'name'       => 'testimonial_website_url',
					'aria-label' => '',
					'type'       => 'url',
					'wrapper'    => array(
						'width' => '50',
					),
				),
				array(
					'key'          => 'field_mrn_testimonial_content',
					'label'        => 'Testimonial',
					'name'         => 'testimonial_content',
					'aria-label'   => '',
					'type'         => 'wysiwyg',
					'tabs'         => 'all',
					'toolbar'      => 'full',
					'media_upload' => 1,
					'delay'        => 0,
					'wrapper'      => array(
						'width' => '100',
					),
				),
				array(
					'key'           => 'field_mrn_testimonial_image_logo',
					'label'         => 'Image/Logo',
					'name'          => 'testimonial_image_logo',
					'aria-label'    => '',
					'type'          => 'image',
					'return_format' => 'array',
					'preview_size'  => 'medium',
					'library'       => 'all',
					'mime_types'    => 'jpg,jpeg,png,gif,webp,svg',
				),
			),
			'location'              => array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => 'testimonial',
					),
				),
			),
			'position'              => 'acf_after_title',
			'style'                 => 'default',
			'label_placement'       => 'top',
			'instruction_placement' => 'label',
			'active'                => true,
			'description'           => 'Theme-owned testimonial fields.',
			'show_in_rest'          => 1,
		)
	);
}
add_action( 'acf/init', 'mrn_base_stack_register_testimonial_field_group' );

/**
 * Get the public testimonial data for a post.
 *
 * @param int|null $post_id Post ID to inspect.
 * @return array<string, mixed>
 */
function mrn_base_stack_get_testimonial_data( $post_id = null ) {
	$post_id = $post_id ? (int) $post_id : get_the_ID();

	$data = array(
		'label'       => '',
		'heading'     => '',
		'subheading'  => '',
		'name'        => get_the_title( $post_id ),
		'company'     => '',
		'position'    => '',
		'website_url' => '',
		'content'     => '',
		'image_logo'  => null,
	);

	if ( ! $post_id || ! function_exists( 'get_field' ) ) {
		return $data;
	}

	$label = get_field( 'testimonial_label', $post_id );
	if ( is_string( $label ) ) {
		$data['label'] = trim( $label );
	}

	$heading = get_field( 'testimonial_heading', $post_id );
	if ( is_string( $heading ) ) {
		$data['heading'] = trim( $heading );
	}

	$subheading = get_field( 'testimonial_subheading', $post_id );
	if ( is_string( $subheading ) ) {
		$data['subheading'] = trim( $subheading );
	}

	$name = get_field( 'testimonial_name', $post_id );
	if ( is_string( $name ) && '' !== trim( $name ) ) {
		$data['name'] = trim( $name );
	}

	$company = get_field( 'testimonial_company', $post_id );
	if ( is_string( $company ) ) {
		$data['company'] = trim( $company );
	}

	$position = get_field( 'testimonial_position', $post_id );
	if ( is_string( $position ) ) {
		$data['position'] = trim( $position );
	}

	$website_url = get_field( 'testimonial_website_url', $post_id );
	if ( is_string( $website_url ) ) {
		$data['website_url'] = trim( $website_url );
	}

	$content = get_field( 'testimonial_content', $post_id );
	if ( is_string( $content ) ) {
		$data['content'] = $content;
	}

	$image_logo = get_field( 'testimonial_image_logo', $post_id );
	if ( is_array( $image_logo ) ) {
		$data['image_logo'] = $image_logo;
	}

	return $data;
}

/**
 * Build a short plain-text excerpt from the testimonial body field.
 *
 * @param int|null $post_id Post ID to inspect.
 * @param int      $length  Excerpt length in words.
 * @return string
 */
function mrn_base_stack_get_testimonial_excerpt( $post_id = null, $length = 28 ) {
	$data     = mrn_base_stack_get_testimonial_data( $post_id );
	$segments = array(
		isset( $data['subheading'] ) && is_string( $data['subheading'] ) ? $data['subheading'] : '',
		isset( $data['content'] ) && is_string( $data['content'] ) ? $data['content'] : '',
	);
	$content  = wp_strip_all_tags( implode( ' ', array_filter( $segments ) ) );
	$content  = preg_replace( '/\s+/', ' ', $content );
	$content  = is_string( $content ) ? trim( $content ) : '';

	if ( '' === $content ) {
		return '';
	}

	return wp_trim_words( $content, $length );
}
