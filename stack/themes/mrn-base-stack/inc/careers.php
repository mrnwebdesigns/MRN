<?php
/**
 * Careers CPT registration, field groups, and rendering helpers.
 *
 * @package mrn-base-stack
 */

/**
 * Register the theme-owned Job Posting custom post type.
 *
 * The admin menu uses "Careers" while each record is a "Job Posting".
 *
 * @return void
 */
function mrn_base_stack_register_job_posting_post_type() {
	$show_ui = function_exists( 'mrn_base_stack_is_admin_cpt_visible' ) ? mrn_base_stack_is_admin_cpt_visible( 'job_posting' ) : true;

	$labels = array(
		'name'                  => __( 'Careers', 'mrn-base-stack' ),
		'singular_name'         => __( 'Job Posting', 'mrn-base-stack' ),
		'menu_name'             => __( 'Careers', 'mrn-base-stack' ),
		'name_admin_bar'        => __( 'Job Posting', 'mrn-base-stack' ),
		'add_new'               => __( 'Add New', 'mrn-base-stack' ),
		'add_new_item'          => __( 'Add New Job Posting', 'mrn-base-stack' ),
		'new_item'              => __( 'New Job Posting', 'mrn-base-stack' ),
		'edit_item'             => __( 'Edit Job Posting', 'mrn-base-stack' ),
		'view_item'             => __( 'View Job Posting', 'mrn-base-stack' ),
		'view_items'            => __( 'View Job Postings', 'mrn-base-stack' ),
		'all_items'             => __( 'All Job Postings', 'mrn-base-stack' ),
		'search_items'          => __( 'Search Job Postings', 'mrn-base-stack' ),
		'parent_item_colon'     => __( 'Parent Job Postings:', 'mrn-base-stack' ),
		'not_found'             => __( 'No job postings found.', 'mrn-base-stack' ),
		'not_found_in_trash'    => __( 'No job postings found in Trash.', 'mrn-base-stack' ),
		'archives'              => __( 'Careers', 'mrn-base-stack' ),
		'attributes'            => __( 'Job Posting Attributes', 'mrn-base-stack' ),
		'insert_into_item'      => __( 'Insert into job posting', 'mrn-base-stack' ),
		'uploaded_to_this_item' => __( 'Uploaded to this job posting', 'mrn-base-stack' ),
		'featured_image'        => __( 'Featured image', 'mrn-base-stack' ),
		'set_featured_image'    => __( 'Set featured image', 'mrn-base-stack' ),
		'remove_featured_image' => __( 'Remove featured image', 'mrn-base-stack' ),
		'use_featured_image'    => __( 'Use as featured image', 'mrn-base-stack' ),
		'filter_items_list'     => __( 'Filter job postings list', 'mrn-base-stack' ),
		'items_list_navigation' => __( 'Job postings list navigation', 'mrn-base-stack' ),
		'items_list'            => __( 'Job postings list', 'mrn-base-stack' ),
		'item_published'        => __( 'Job Posting published.', 'mrn-base-stack' ),
		'item_updated'          => __( 'Job Posting updated.', 'mrn-base-stack' ),
	);

	register_post_type(
		'job_posting',
		array(
			'labels'              => $labels,
			'public'              => true,
			'show_ui'             => $show_ui,
			'show_in_menu'        => $show_ui,
			'show_in_rest'        => true,
			'has_archive'         => 'careers',
			'rewrite'             => array(
				'slug'       => 'careers',
				'with_front' => false,
			),
			'menu_position'       => 10,
			'menu_icon'           => 'dashicons-id-alt',
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
add_action( 'init', 'mrn_base_stack_register_job_posting_post_type' );

/**
 * Register job-posting-specific ACF fields.
 *
 * @return void
 */
function mrn_base_stack_register_job_posting_field_group() {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_local_field_group(
		array(
			'key'                   => 'group_mrn_job_posting',
			'title'                 => 'Job Posting',
			'menu_order'            => 10,
			'fields'                => array(
				mrn_base_stack_get_inline_text_field( 'field_mrn_job_posting_label', 'Label', 'job_posting_label' ),
				mrn_base_stack_get_label_tag_field( 'field_mrn_job_posting_label_tag', 'job_posting_label_tag' ),
				mrn_base_stack_get_inline_text_field( 'field_mrn_job_posting_heading', 'Heading', 'job_posting_heading' ),
				mrn_base_stack_get_text_tag_field( 'field_mrn_job_posting_heading_tag', 'job_posting_heading_tag', 'h2', 'Heading Tag' ),
				mrn_base_stack_get_inline_text_field( 'field_mrn_job_posting_subheading', 'Subheading', 'job_posting_subheading' ),
				mrn_base_stack_get_text_tag_field( 'field_mrn_job_posting_subheading_tag', 'job_posting_subheading_tag', 'p', 'Subheading Tag' ),
				array(
					'key'          => 'field_mrn_job_posting_summary',
					'label'        => 'Job Summary',
					'name'         => 'job_posting_summary',
					'aria-label'   => '',
					'type'         => 'wysiwyg',
					'tabs'         => 'all',
					'toolbar'      => 'full',
					'media_upload' => 1,
					'delay'        => 0,
				),
				array(
					'key'        => 'field_mrn_job_posting_department',
					'label'      => 'Department',
					'name'       => 'job_posting_department',
					'aria-label' => '',
					'type'       => 'text',
					'wrapper'    => array(
						'width' => '33',
					),
				),
				array(
					'key'           => 'field_mrn_job_posting_employment_type',
					'label'         => 'Employment Type',
					'name'          => 'job_posting_employment_type',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => mrn_base_stack_get_job_posting_employment_type_choices(),
					'default_value' => 'full_time',
					'ui'            => 1,
					'allow_null'    => 1,
					'wrapper'       => array(
						'width' => '33',
					),
				),
				array(
					'key'           => 'field_mrn_job_posting_workplace_type',
					'label'         => 'Workplace Type',
					'name'          => 'job_posting_workplace_type',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => mrn_base_stack_get_job_posting_workplace_type_choices(),
					'default_value' => '',
					'ui'            => 1,
					'allow_null'    => 1,
					'wrapper'       => array(
						'width' => '34',
					),
				),
				array(
					'key'          => 'field_mrn_job_posting_location',
					'label'        => 'Job Location',
					'name'         => 'job_posting_location',
					'aria-label'   => '',
					'type'         => 'text',
					'instructions' => 'Use a concise location label, such as Raleigh, NC; Remote; or Hybrid - Cary, NC.',
					'wrapper'      => array(
						'width' => '50',
					),
				),
				array(
					'key'          => 'field_mrn_job_posting_area',
					'label'        => 'Area',
					'name'         => 'job_posting_area',
					'aria-label'   => '',
					'type'         => 'text',
					'instructions' => 'Optional metro or service area, such as Raleigh - Durham.',
					'wrapper'      => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_job_posting_location_record',
					'label'         => 'Linked Location',
					'name'          => 'job_posting_location_record',
					'aria-label'    => '',
					'type'          => 'post_object',
					'post_type'     => array( 'location' ),
					'return_format' => 'id',
					'allow_null'    => 1,
					'ui'            => 1,
					'instructions'  => 'Optional stack Location record for sites that manage reusable business locations.',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'          => 'field_mrn_job_posting_compensation_note',
					'label'        => 'Compensation Note',
					'name'         => 'job_posting_compensation_note',
					'aria-label'   => '',
					'type'         => 'text',
					'instructions' => 'Example: $55,000-$70,000 annually, $22-$28/hour, or Competitive pay.',
					'wrapper'      => array(
						'width' => '50',
					),
				),
				array(
					'key'            => 'field_mrn_job_posting_deadline',
					'label'          => 'Application Deadline',
					'name'           => 'job_posting_application_deadline',
					'aria-label'     => '',
					'type'           => 'date_picker',
					'display_format' => 'F j, Y',
					'return_format'  => 'Y-m-d',
					'first_day'      => 0,
					'wrapper'        => array(
						'width' => '50',
					),
				),
				array(
					'key'          => 'field_mrn_job_posting_application_url',
					'label'        => 'Application URL',
					'name'         => 'job_posting_application_url',
					'aria-label'   => '',
					'type'         => 'url',
					'instructions' => 'Use when applicants should apply through a form, HR portal, or external listing.',
					'wrapper'      => array(
						'width' => '50',
					),
				),
				array(
					'key'          => 'field_mrn_job_posting_application_email',
					'label'        => 'Application Email',
					'name'         => 'job_posting_application_email',
					'aria-label'   => '',
					'type'         => 'email',
					'instructions' => 'Use when applicants should email a resume or inquiry.',
					'wrapper'      => array(
						'width' => '50',
					),
				),
				array(
					'key'          => 'field_mrn_job_posting_responsibilities',
					'label'        => 'Responsibilities',
					'name'         => 'job_posting_responsibilities',
					'aria-label'   => '',
					'type'         => 'wysiwyg',
					'tabs'         => 'all',
					'toolbar'      => 'full',
					'media_upload' => 1,
					'delay'        => 0,
				),
				array(
					'key'          => 'field_mrn_job_posting_qualifications',
					'label'        => 'Qualifications',
					'name'         => 'job_posting_qualifications',
					'aria-label'   => '',
					'type'         => 'wysiwyg',
					'tabs'         => 'all',
					'toolbar'      => 'full',
					'media_upload' => 1,
					'delay'        => 0,
				),
				array(
					'key'          => 'field_mrn_job_posting_benefits',
					'label'        => 'Benefits',
					'name'         => 'job_posting_benefits',
					'aria-label'   => '',
					'type'         => 'wysiwyg',
					'tabs'         => 'all',
					'toolbar'      => 'full',
					'media_upload' => 1,
					'delay'        => 0,
				),
			),
			'location'              => array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => 'job_posting',
					),
				),
			),
			'position'              => 'acf_after_title',
			'style'                 => 'default',
			'label_placement'       => 'top',
			'instruction_placement' => 'label',
			'active'                => true,
			'description'           => 'Theme-owned job posting fields.',
			'show_in_rest'          => 1,
		)
	);
}
add_action( 'acf/init', 'mrn_base_stack_register_job_posting_field_group' );

/**
 * Get employment type choices for job postings.
 *
 * @return array<string, string>
 */
function mrn_base_stack_get_job_posting_employment_type_choices() {
	return array(
		'full_time'  => __( 'Full Time', 'mrn-base-stack' ),
		'part_time'  => __( 'Part Time', 'mrn-base-stack' ),
		'contract'   => __( 'Contract', 'mrn-base-stack' ),
		'temporary'  => __( 'Temporary', 'mrn-base-stack' ),
		'internship' => __( 'Internship', 'mrn-base-stack' ),
		'seasonal'   => __( 'Seasonal', 'mrn-base-stack' ),
		'volunteer'  => __( 'Volunteer', 'mrn-base-stack' ),
	);
}

/**
 * Get workplace type choices for job postings.
 *
 * @return array<string, string>
 */
function mrn_base_stack_get_job_posting_workplace_type_choices() {
	return array(
		'on_site' => __( 'On-site', 'mrn-base-stack' ),
		'remote'  => __( 'Remote', 'mrn-base-stack' ),
		'hybrid'  => __( 'Hybrid', 'mrn-base-stack' ),
	);
}

/**
 * Get a human-readable select choice label.
 *
 * @param string               $value   Saved choice key.
 * @param array<string,string> $choices Choice map.
 * @return string
 */
function mrn_base_stack_get_job_posting_choice_label( $value, array $choices ) {
	$value = sanitize_key( (string) $value );

	return isset( $choices[ $value ] ) ? (string) $choices[ $value ] : '';
}

/**
 * Get public job posting data for a post.
 *
 * @param int|null $post_id Post ID to inspect.
 * @return array<string, mixed>
 */
function mrn_base_stack_get_job_posting_data( $post_id = null ) {
	$post_id = $post_id ? (int) $post_id : get_the_ID();

	$data = array(
		'label'                => '',
		'heading'              => '',
		'subheading'           => '',
		'summary'              => '',
		'department'           => '',
		'employment_type'      => '',
		'employment_type_key'  => '',
		'workplace_type'       => '',
		'workplace_type_key'   => '',
		'location'             => '',
		'area'                 => '',
		'location_record'      => 0,
		'compensation_note'    => '',
		'application_deadline' => '',
		'application_url'      => '',
		'application_email'    => '',
		'responsibilities'     => '',
		'qualifications'       => '',
		'benefits'             => '',
	);

	if ( ! $post_id || ! function_exists( 'get_field' ) ) {
		return $data;
	}

	foreach ( array( 'label', 'heading', 'subheading' ) as $key ) {
		$value = get_field( 'job_posting_' . $key, $post_id );
		if ( is_string( $value ) ) {
			$data[ $key ] = trim( $value );
		}
	}

	foreach ( array( 'summary', 'responsibilities', 'qualifications', 'benefits' ) as $key ) {
		$value = get_field( 'job_posting_' . $key, $post_id );
		if ( is_string( $value ) ) {
			$data[ $key ] = $value;
		}
	}

	foreach ( array( 'department', 'location', 'area', 'compensation_note', 'application_deadline', 'application_url', 'application_email' ) as $key ) {
		$value = get_field( 'job_posting_' . $key, $post_id );
		if ( is_string( $value ) ) {
			$data[ $key ] = trim( $value );
		}
	}

	$employment_type_key          = sanitize_key( (string) get_field( 'job_posting_employment_type', $post_id ) );
	$data['employment_type_key']  = $employment_type_key;
	$data['employment_type']      = mrn_base_stack_get_job_posting_choice_label( $employment_type_key, mrn_base_stack_get_job_posting_employment_type_choices() );
	$workplace_type_key           = sanitize_key( (string) get_field( 'job_posting_workplace_type', $post_id ) );
	$data['workplace_type_key']   = $workplace_type_key;
	$data['workplace_type']       = mrn_base_stack_get_job_posting_choice_label( $workplace_type_key, mrn_base_stack_get_job_posting_workplace_type_choices() );
	$data['location_record']      = absint( get_field( 'job_posting_location_record', $post_id ) );
	$data['application_url']      = esc_url_raw( (string) $data['application_url'] );
	$data['application_email']    = sanitize_email( (string) $data['application_email'] );
	$data['application_deadline'] = preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $data['application_deadline'] ) ? $data['application_deadline'] : '';

	return $data;
}

/**
 * Get a compact job posting excerpt.
 *
 * @param int|null $post_id Post ID to inspect.
 * @param int      $length  Maximum word count.
 * @return string
 */
function mrn_base_stack_get_job_posting_excerpt( $post_id = null, $length = 32 ) {
	$post_id = $post_id ? (int) $post_id : get_the_ID();

	if ( ! $post_id ) {
		return '';
	}

	$excerpt = get_the_excerpt( $post_id );
	if ( '' !== trim( (string) $excerpt ) ) {
		return wp_trim_words( wp_strip_all_tags( (string) $excerpt ), $length );
	}

	$data = mrn_base_stack_get_job_posting_data( $post_id );
	foreach ( array( 'summary', 'responsibilities', 'qualifications' ) as $key ) {
		if ( ! empty( $data[ $key ] ) ) {
			return wp_trim_words( wp_strip_all_tags( (string) $data[ $key ] ), $length );
		}
	}

	return '';
}
