<?php
/**
 * Events CPT registration and field groups.
 *
 * Event titles and body copy use the standard WordPress title and editor.
 * Event-specific structured data is stored in ACF for future templates and
 * integrations.
 *
 * @package mrn-base-stack
 */

/**
 * Register the theme-owned Event custom post type.
 *
 * @return void
 */
function mrn_base_stack_register_event_post_type() {
	$show_ui = function_exists( 'mrn_base_stack_is_admin_cpt_visible' ) ? mrn_base_stack_is_admin_cpt_visible( 'event' ) : true;

	$labels = array(
		'name'                  => __( 'Events', 'mrn-base-stack' ),
		'singular_name'         => __( 'Event', 'mrn-base-stack' ),
		'menu_name'             => __( 'Events', 'mrn-base-stack' ),
		'name_admin_bar'        => __( 'Event', 'mrn-base-stack' ),
		'add_new'               => __( 'Add New', 'mrn-base-stack' ),
		'add_new_item'          => __( 'Add New Event', 'mrn-base-stack' ),
		'new_item'              => __( 'New Event', 'mrn-base-stack' ),
		'edit_item'             => __( 'Edit Event', 'mrn-base-stack' ),
		'view_item'             => __( 'View Event', 'mrn-base-stack' ),
		'view_items'            => __( 'View Events', 'mrn-base-stack' ),
		'all_items'             => __( 'All Events', 'mrn-base-stack' ),
		'search_items'          => __( 'Search Events', 'mrn-base-stack' ),
		'parent_item_colon'     => __( 'Parent Events:', 'mrn-base-stack' ),
		'not_found'             => __( 'No events found.', 'mrn-base-stack' ),
		'not_found_in_trash'    => __( 'No events found in Trash.', 'mrn-base-stack' ),
		'archives'              => __( 'Event Archives', 'mrn-base-stack' ),
		'attributes'            => __( 'Event Attributes', 'mrn-base-stack' ),
		'insert_into_item'      => __( 'Insert into event', 'mrn-base-stack' ),
		'uploaded_to_this_item' => __( 'Uploaded to this event', 'mrn-base-stack' ),
		'featured_image'        => __( 'Featured image', 'mrn-base-stack' ),
		'set_featured_image'    => __( 'Set featured image', 'mrn-base-stack' ),
		'remove_featured_image' => __( 'Remove featured image', 'mrn-base-stack' ),
		'use_featured_image'    => __( 'Use as featured image', 'mrn-base-stack' ),
		'filter_items_list'     => __( 'Filter events list', 'mrn-base-stack' ),
		'items_list_navigation' => __( 'Events list navigation', 'mrn-base-stack' ),
		'items_list'            => __( 'Events list', 'mrn-base-stack' ),
		'item_published'        => __( 'Event published.', 'mrn-base-stack' ),
		'item_updated'          => __( 'Event updated.', 'mrn-base-stack' ),
	);

	register_post_type(
		'event',
		array(
			'labels'              => $labels,
			'public'              => true,
			'show_ui'             => $show_ui,
			'show_in_menu'        => $show_ui,
			'show_in_rest'        => true,
			'has_archive'         => true,
			'rewrite'             => array(
				'slug'       => 'events',
				'with_front' => false,
			),
			'menu_position'       => 11,
			'menu_icon'           => 'dashicons-calendar-alt',
			'supports'            => array( 'title', 'editor', 'excerpt', 'revisions' ),
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
add_action( 'init', 'mrn_base_stack_register_event_post_type' );

/**
 * Register event-specific ACF fields.
 *
 * @return void
 */
function mrn_base_stack_register_event_field_group() {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_local_field_group(
		array(
			'key'                   => 'group_mrn_event',
			'title'                 => 'Event Details',
			'menu_order'            => 10,
			'fields'                => array(
				array(
					'key'            => 'field_mrn_event_start_date',
					'label'          => 'Start Date and Time',
					'name'           => 'event_start_date',
					'aria-label'     => '',
					'type'           => 'date_time_picker',
					'display_format' => 'F j, Y g:i a',
					'return_format'  => 'Y-m-d H:i:s',
					'first_day'      => 0,
					'required'       => 1,
					'instructions'   => 'Enter the event start using the WordPress site timezone.',
					'wrapper'        => array(
						'width' => '50',
					),
				),
				array(
					'key'            => 'field_mrn_event_end_date',
					'label'          => 'End Date and Time',
					'name'           => 'event_end_date',
					'aria-label'     => '',
					'type'           => 'date_time_picker',
					'display_format' => 'F j, Y g:i a',
					'return_format'  => 'Y-m-d H:i:s',
					'first_day'      => 0,
					'instructions'   => 'Optional for an event with no distinct end time.',
					'wrapper'        => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_event_image',
					'label'         => 'Event Image or Banner',
					'name'          => 'event_image',
					'aria-label'    => '',
					'type'          => 'image',
					'return_format' => 'id',
					'preview_size'  => 'medium',
					'library'       => 'all',
					'mime_types'    => 'jpg,jpeg,png,gif,webp,svg',
					'instructions'  => 'Optional event artwork or banner. Add meaningful alternative text in the Media Library.',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_event_image_link',
					'label'         => 'Image Link',
					'name'          => 'event_image_link',
					'aria-label'    => '',
					'type'          => 'link',
					'return_format' => 'array',
					'instructions'  => 'Optional destination used when the event image or banner should be clickable.',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_event_location_type',
					'label'         => 'Location Source',
					'name'          => 'event_location_type',
					'aria-label'    => '',
					'type'          => 'button_group',
					'choices'       => array(
						'text'     => 'Enter a Location',
						'location' => 'Choose a Location Record',
					),
					'default_value' => 'text',
					'layout'        => 'horizontal',
					'return_format' => 'value',
					'instructions'  => 'Choose whether this event uses a one-off location or a reusable Locations record.',
				),
				array(
					'key'               => 'field_mrn_event_location_text',
					'label'             => 'Location',
					'name'              => 'event_location_text',
					'aria-label'        => '',
					'type'              => 'textarea',
					'rows'              => 3,
					'instructions'      => 'Enter the venue name, street address, virtual location, or other location details.',
					'conditional_logic' => array(
						array(
							array(
								'field'    => 'field_mrn_event_location_type',
								'operator' => '==',
								'value'    => 'text',
							),
						),
					),
				),
				array(
					'key'               => 'field_mrn_event_location_record',
					'label'             => 'Location Record',
					'name'              => 'event_location_record',
					'aria-label'        => '',
					'type'              => 'post_object',
					'post_type'         => array( 'location' ),
					'return_format'     => 'id',
					'allow_null'        => 1,
					'ui'                => 1,
					'instructions'      => 'Choose a reusable record from the Locations content type.',
					'conditional_logic' => array(
						array(
							array(
								'field'    => 'field_mrn_event_location_type',
								'operator' => '==',
								'value'    => 'location',
							),
						),
					),
				),
				array(
					'key'          => 'field_mrn_event_booth',
					'label'        => 'Booth',
					'name'         => 'event_booth',
					'aria-label'   => '',
					'type'         => 'text',
					'instructions' => 'Optional booth number, name, or location within the event.',
				),
				array(
					'key'          => 'field_mrn_event_sponsors',
					'label'        => 'Sponsors',
					'name'         => 'event_sponsors',
					'aria-label'   => '',
					'type'         => 'repeater',
					'layout'       => 'row',
					'button_label' => 'Add Sponsor',
					'collapsed'    => 'field_mrn_event_sponsor_text',
					'instructions' => 'Add one row per sponsor. Each sponsor may use text, an image, a link, or any combination of the three.',
					'sub_fields'   => array(
						array(
							'key'          => 'field_mrn_event_sponsor_text',
							'label'        => 'Text',
							'name'         => 'text',
							'aria-label'   => '',
							'type'         => 'text',
							'instructions' => 'Sponsor name or short supporting text.',
							'wrapper'      => array(
								'width' => '35',
							),
						),
						array(
							'key'           => 'field_mrn_event_sponsor_image',
							'label'         => 'Image',
							'name'          => 'image',
							'aria-label'    => '',
							'type'          => 'image',
							'return_format' => 'id',
							'preview_size'  => 'thumbnail',
							'library'       => 'all',
							'mime_types'    => 'jpg,jpeg,png,gif,webp,svg',
							'instructions'  => 'Optional sponsor logo or image. Add meaningful alternative text in the Media Library.',
							'wrapper'       => array(
								'width' => '30',
							),
						),
						array(
							'key'           => 'field_mrn_event_sponsor_link',
							'label'         => 'Link',
							'name'          => 'link',
							'aria-label'    => '',
							'type'          => 'link',
							'return_format' => 'array',
							'instructions'  => 'Optional sponsor destination.',
							'wrapper'       => array(
								'width' => '35',
							),
						),
					),
				),
			),
			'location'              => array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => 'event',
					),
				),
			),
			'position'              => 'acf_after_title',
			'style'                 => 'default',
			'label_placement'       => 'top',
			'instruction_placement' => 'label',
			'active'                => true,
			'description'           => 'Theme-owned event metadata. The standard WordPress editor stores the event body.',
			'show_in_rest'          => 1,
		)
	);
}
add_action( 'acf/init', 'mrn_base_stack_register_event_field_group' );
