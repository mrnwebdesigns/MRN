<?php
/**
 * Location details and optional inheritance from Business Information.
 *
 * @package mrn-base-stack
 */

/**
 * Build a location's opt-in Business Information checkbox.
 *
 * @param string $name Field section.
 * @return array<string, mixed>
 */
function mrn_base_stack_get_location_inheritance_field( $name ) {
	$labels = array(
		'hours'        => __( 'Business Hours', 'mrn-base-stack' ),
		'social_links' => __( 'Social Media', 'mrn-base-stack' ),
		'phone'        => __( 'Phone', 'mrn-base-stack' ),
		'text_phone'   => __( 'Text / SMS / RCS', 'mrn-base-stack' ),
		'fax'          => __( 'Fax', 'mrn-base-stack' ),
	);
	return array(
		'key'           => 'field_mrn_location_use_business_' . $name,
		'label'         => $labels[ $name ],
		'name'          => 'location_use_business_' . $name,
		'type'          => 'true_false',
		'message'       => __( 'Use from Business Information', 'mrn-base-stack' ),
		'instructions'  => 'social_links' === $name
			? __( 'Use the shared Social Media profiles configured in Site Configurations. Location-specific profiles are retained while checked.', 'mrn-base-stack' )
			: __( 'Use the current Business Information value. Location-specific values are retained while checked.', 'mrn-base-stack' ),
		'default_value' => 0,
		'ui'            => 0,
	);
}

/**
 * Display custom fields only when their inheritance checkbox is unchecked.
 *
 * @param string $name Field section.
 * @return array<int, array<int, array<string, string>>>
 */
function mrn_base_stack_get_location_custom_field_condition( $name ) {
	return array(
		array(
			array(
				'field'    => 'field_mrn_location_use_business_' . $name,
				'operator' => '!=',
				'value'    => '1',
			),
		),
	);
}

/**
 * Register the Location editor fields, preserving the Content Only CPT.
 *
 * @return void
 */
function mrn_base_stack_register_location_field_group() {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	$fields = array(
		array(
			'key'   => 'field_mrn_location_hours_tab',
			'label' => __( 'Business Hours', 'mrn-base-stack' ),
			'type'  => 'tab',
		),
		mrn_base_stack_get_location_inheritance_field( 'hours' ),
	);
	foreach ( mrn_base_stack_get_business_hours_fields( 'location' ) as $field ) {
		$field['conditional_logic'] = mrn_base_stack_get_location_custom_field_condition( 'hours' );
		$fields[]                   = $field;
	}

	$fields[] = array(
		'key'   => 'field_mrn_location_details_tab',
		'label' => __( 'Location', 'mrn-base-stack' ),
		'type'  => 'tab',
	);
	$address_fields = array();
	foreach ( array(
		'line_1'      => __( 'Address Line 1', 'mrn-base-stack' ),
		'line_2'      => __( 'Address Line 2', 'mrn-base-stack' ),
		'city'        => __( 'City', 'mrn-base-stack' ),
		'state'       => __( 'State / Region', 'mrn-base-stack' ),
		'postal_code' => __( 'Postal Code', 'mrn-base-stack' ),
		'country'     => __( 'Country', 'mrn-base-stack' ),
	) as $name => $label ) {
		$address_fields[] = array(
			'key'           => 'field_mrn_location_address_' . $name,
			'label'         => $label,
			'name'          => $name,
			'type'          => 'text',
			'default_value' => 'country' === $name ? 'United States' : '',
			'wrapper'       => array( 'width' => '50' ),
		);
	}
	$fields[] = array(
		'key'        => 'field_mrn_location_address',
		'label'      => __( 'Location', 'mrn-base-stack' ),
		'name'       => 'location_address',
		'type'       => 'group',
		'layout'     => 'block',
		'sub_fields' => $address_fields,
	);
	$fields[] = array(
		'key'           => 'field_mrn_location_image',
		'label'         => __( 'Image', 'mrn-base-stack' ),
		'name'          => 'location_image',
		'type'          => 'image',
		'instructions'  => __( 'Choose the location image and set its meaningful alt text in the Media Library.', 'mrn-base-stack' ),
		'return_format' => 'id',
		'preview_size'  => 'medium',
		'library'       => 'all',
	);
	$fields[] = array(
		'key'   => 'field_mrn_location_social_tab',
		'label' => __( 'Social Media', 'mrn-base-stack' ),
		'type'  => 'tab',
	);
	$fields[] = mrn_base_stack_get_location_inheritance_field( 'social_links' );
	$fields[] = array(
		'key'               => 'field_mrn_location_social_links',
		'label'             => __( 'Social Media', 'mrn-base-stack' ),
		'name'              => 'location_social_links',
		'type'              => 'repeater',
		'layout'            => 'row',
		'button_label'      => __( 'Add Social Profile', 'mrn-base-stack' ),
		'conditional_logic' => mrn_base_stack_get_location_custom_field_condition( 'social_links' ),
		'sub_fields'        => array(
			array(
				'key'      => 'field_mrn_location_social_name',
				'label'    => __( 'Profile Name', 'mrn-base-stack' ),
				'name'     => 'name',
				'type'     => 'text',
				'required' => 1,
			),
			array(
				'key'      => 'field_mrn_location_social_url',
				'label'    => __( 'Profile URL', 'mrn-base-stack' ),
				'name'     => 'url',
				'type'     => 'url',
				'required' => 1,
			),
			array(
				'key'           => 'field_mrn_location_social_icon',
				'label'         => __( 'Icon', 'mrn-base-stack' ),
				'name'          => 'icon_id',
				'type'          => 'image',
				'return_format' => 'id',
				'preview_size'  => 'thumbnail',
				'library'       => 'all',
			),
		),
	);
	$fields[] = array(
		'key'   => 'field_mrn_location_contact_tab',
		'label' => __( 'Contact', 'mrn-base-stack' ),
		'type'  => 'tab',
	);
	foreach ( array(
		'phone'      => __( 'Phone', 'mrn-base-stack' ),
		'text_phone' => __( 'Text / SMS / RCS', 'mrn-base-stack' ),
		'fax'        => __( 'Fax', 'mrn-base-stack' ),
	) as $name => $label ) {
		$fields[]                   = mrn_base_stack_get_location_inheritance_field( $name );
		$field                      = mrn_base_stack_get_contact_number_field( 'location', $name, $label, __( 'Enter the number for this location.', 'mrn-base-stack' ) );
		$field['conditional_logic'] = mrn_base_stack_get_location_custom_field_condition( $name );
		$fields[]                   = $field;
	}

	acf_add_local_field_group(
		array(
			'key'                   => 'group_mrn_location',
			'title'                 => __( 'Location Details', 'mrn-base-stack' ),
			'fields'                => $fields,
			'location'              => array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => 'location',
					),
				),
			),
			'position'              => 'acf_after_title',
			'style'                 => 'default',
			'label_placement'       => 'top',
			'instruction_placement' => 'label',
		)
	);
}
add_action( 'acf/init', 'mrn_base_stack_register_location_field_group', 20 );

/**
 * Resolve effective Location values without copying shared data into post meta.
 *
 * Values remain content; consumers must escape them for their output context.
 *
 * @param int|WP_Post|null $post Location post or ID.
 * @return array<string, mixed>
 */
function mrn_base_stack_get_location_information( $post = null ) {
	$information = array(
		'address'        => array(),
		'image'          => 0,
		'business_hours' => array(),
		'social_links'   => array(),
		'phone'          => '',
		'phone_uri'      => '',
		'text_phone'     => '',
		'text_phone_uri' => '',
		'fax'            => '',
	);
	$post = get_post( $post );
	if ( ! $post || 'location' !== $post->post_type || ! function_exists( 'get_field' ) ) {
		return $information;
	}

	$address                       = get_field( 'location_address', $post->ID );
	$information['address']        = is_array( $address ) ? $address : array();
	$information['image']          = absint( get_field( 'location_image', $post->ID ) );
	$business                      = mrn_base_stack_get_business_information();
	$information['business_hours'] = get_field( 'location_use_business_hours', $post->ID )
		? $business['business_hours']
		: mrn_base_stack_get_business_hours( $post->ID, 'location_' );

	if ( get_field( 'location_use_business_social_links', $post->ID ) ) {
		$social_links = function_exists( 'mrn_config_helper_get_social_links' ) ? mrn_config_helper_get_social_links() : array();
	} else {
		$social_links = get_field( 'location_social_links', $post->ID );
	}
	$information['social_links'] = is_array( $social_links ) ? $social_links : array();
	foreach ( array( 'phone', 'text_phone', 'fax' ) as $name ) {
		$inherit = (bool) get_field( 'location_use_business_' . $name, $post->ID );
		$value   = $inherit ? $business[ $name ] : get_field( 'location_' . $name, $post->ID );
		$information[ $name ] = mrn_base_stack_format_phone_number( $value );
		if ( 'fax' !== $name ) {
			$information[ $name . '_uri' ] = $inherit ? $business[ $name . '_uri' ] : mrn_base_stack_get_phone_uri( $value );
		}
	}

	return $information;
}
