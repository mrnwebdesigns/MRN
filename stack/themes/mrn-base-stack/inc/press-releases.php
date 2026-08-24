<?php
/**
 * Press Release CPT registration and field groups.
 *
 * @package mrn-base-stack
 */

/**
 * Register the theme-owned Press Release custom post type.
 *
 * @return void
 */
function mrn_base_stack_register_press_release_post_type() {
	$show_ui = function_exists( 'mrn_base_stack_is_admin_cpt_visible' ) ? mrn_base_stack_is_admin_cpt_visible( 'press_release' ) : true;

	$labels = array(
		'name'                  => __( 'Press Releases', 'mrn-base-stack' ),
		'singular_name'         => __( 'Press Release', 'mrn-base-stack' ),
		'menu_name'             => __( 'Press Releases', 'mrn-base-stack' ),
		'name_admin_bar'        => __( 'Press Release', 'mrn-base-stack' ),
		'add_new'               => __( 'Add New', 'mrn-base-stack' ),
		'add_new_item'          => __( 'Add New Press Release', 'mrn-base-stack' ),
		'new_item'              => __( 'New Press Release', 'mrn-base-stack' ),
		'edit_item'             => __( 'Edit Press Release', 'mrn-base-stack' ),
		'view_item'             => __( 'View Press Release', 'mrn-base-stack' ),
		'view_items'            => __( 'View Press Releases', 'mrn-base-stack' ),
		'all_items'             => __( 'All Press Releases', 'mrn-base-stack' ),
		'search_items'          => __( 'Search Press Releases', 'mrn-base-stack' ),
		'parent_item_colon'     => __( 'Parent Press Releases:', 'mrn-base-stack' ),
		'not_found'             => __( 'No press releases found.', 'mrn-base-stack' ),
		'not_found_in_trash'    => __( 'No press releases found in Trash.', 'mrn-base-stack' ),
		'archives'              => __( 'Press Release Archives', 'mrn-base-stack' ),
		'attributes'            => __( 'Press Release Attributes', 'mrn-base-stack' ),
		'insert_into_item'      => __( 'Insert into press release', 'mrn-base-stack' ),
		'uploaded_to_this_item' => __( 'Uploaded to this press release', 'mrn-base-stack' ),
		'featured_image'        => __( 'Featured image', 'mrn-base-stack' ),
		'set_featured_image'    => __( 'Set featured image', 'mrn-base-stack' ),
		'remove_featured_image' => __( 'Remove featured image', 'mrn-base-stack' ),
		'use_featured_image'    => __( 'Use as featured image', 'mrn-base-stack' ),
		'filter_items_list'     => __( 'Filter press releases list', 'mrn-base-stack' ),
		'items_list_navigation' => __( 'Press releases list navigation', 'mrn-base-stack' ),
		'items_list'            => __( 'Press releases list', 'mrn-base-stack' ),
		'item_published'        => __( 'Press release published.', 'mrn-base-stack' ),
		'item_updated'          => __( 'Press release updated.', 'mrn-base-stack' ),
	);

	register_post_type(
		'press_release',
		array(
			'labels'              => $labels,
			'public'              => true,
			'show_ui'             => $show_ui,
			'show_in_menu'        => $show_ui,
			'show_in_rest'        => true,
			'has_archive'         => true,
			'rewrite'             => array(
				'slug'       => 'press-releases',
				'with_front' => false,
			),
			'menu_position'       => 13,
			'menu_icon'           => 'dashicons-megaphone',
			'supports'            => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions' ),
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
add_action( 'init', 'mrn_base_stack_register_press_release_post_type' );

/**
 * Register Press Release-specific ACF fields.
 *
 * @return void
 */
function mrn_base_stack_register_press_release_field_group() {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_local_field_group(
		array(
			'key'                   => 'group_mrn_press_release',
			'title'                 => 'Press Release Details',
			'menu_order'            => 10,
			'fields'                => array(
				array(
					'key'            => 'field_mrn_press_release_date',
					'label'          => 'Release Date',
					'name'           => 'press_release_date',
					'aria-label'     => '',
					'type'           => 'date_picker',
					'display_format' => 'F j, Y',
					'return_format'  => 'Y-m-d',
					'first_day'      => 0,
					'required'       => 1,
					'instructions'   => 'The date shown with the release. This may differ from the WordPress publish date.',
				),
				array(
					'key'          => 'field_mrn_press_release_dateline',
					'label'        => 'Dateline Location',
					'name'         => 'press_release_dateline',
					'aria-label'   => '',
					'type'         => 'text',
					'instructions' => 'Optional location used at the beginning of the release, such as Singapore or Charlotte, NC.',
				),
				array(
					'key'          => 'field_mrn_press_release_subheadline',
					'label'        => 'Subheadline',
					'name'         => 'press_release_subheadline',
					'aria-label'   => '',
					'type'         => 'textarea',
					'rows'         => 3,
					'instructions' => 'Optional summary or deck displayed below the headline.',
				),
				array(
					'key'        => 'field_mrn_press_release_media_contact_name',
					'label'      => 'Media Contact Name',
					'name'       => 'press_release_media_contact_name',
					'aria-label' => '',
					'type'       => 'text',
				),
				array(
					'key'        => 'field_mrn_press_release_media_contact_email',
					'label'      => 'Media Contact Email',
					'name'       => 'press_release_media_contact_email',
					'aria-label' => '',
					'type'       => 'email',
				),
				array(
					'key'          => 'field_mrn_press_release_boilerplate',
					'label'        => 'About the Organization',
					'name'         => 'press_release_boilerplate',
					'aria-label'   => '',
					'type'         => 'wysiwyg',
					'tabs'         => 'visual',
					'toolbar'      => 'basic',
					'media_upload' => 0,
					'instructions' => 'Optional boilerplate shown after the release body, such as an About Trilliant section.',
				),
			),
			'location'              => array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => 'press_release',
					),
				),
			),
			'position'              => 'acf_after_title',
			'style'                 => 'default',
			'label_placement'       => 'top',
			'instruction_placement' => 'label',
			'active'                => true,
			'description'           => 'Theme-owned press release metadata.',
			'show_in_rest'          => 1,
		)
	);
}
add_action( 'acf/init', 'mrn_base_stack_register_press_release_field_group' );
