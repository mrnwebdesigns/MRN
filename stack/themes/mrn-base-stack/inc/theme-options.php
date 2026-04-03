<?php
/**
 * Theme options integration.
 *
 * @package mrn-base-stack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Return the canonical United States state choices.
 *
 * @return array<string, string>
 */
function mrn_base_stack_get_us_state_choices() {
	return array(
		'AL' => __( 'Alabama', 'mrn-base-stack' ),
		'AK' => __( 'Alaska', 'mrn-base-stack' ),
		'AZ' => __( 'Arizona', 'mrn-base-stack' ),
		'AR' => __( 'Arkansas', 'mrn-base-stack' ),
		'CA' => __( 'California', 'mrn-base-stack' ),
		'CO' => __( 'Colorado', 'mrn-base-stack' ),
		'CT' => __( 'Connecticut', 'mrn-base-stack' ),
		'DE' => __( 'Delaware', 'mrn-base-stack' ),
		'FL' => __( 'Florida', 'mrn-base-stack' ),
		'GA' => __( 'Georgia', 'mrn-base-stack' ),
		'HI' => __( 'Hawaii', 'mrn-base-stack' ),
		'ID' => __( 'Idaho', 'mrn-base-stack' ),
		'IL' => __( 'Illinois', 'mrn-base-stack' ),
		'IN' => __( 'Indiana', 'mrn-base-stack' ),
		'IA' => __( 'Iowa', 'mrn-base-stack' ),
		'KS' => __( 'Kansas', 'mrn-base-stack' ),
		'KY' => __( 'Kentucky', 'mrn-base-stack' ),
		'LA' => __( 'Louisiana', 'mrn-base-stack' ),
		'ME' => __( 'Maine', 'mrn-base-stack' ),
		'MD' => __( 'Maryland', 'mrn-base-stack' ),
		'MA' => __( 'Massachusetts', 'mrn-base-stack' ),
		'MI' => __( 'Michigan', 'mrn-base-stack' ),
		'MN' => __( 'Minnesota', 'mrn-base-stack' ),
		'MS' => __( 'Mississippi', 'mrn-base-stack' ),
		'MO' => __( 'Missouri', 'mrn-base-stack' ),
		'MT' => __( 'Montana', 'mrn-base-stack' ),
		'NE' => __( 'Nebraska', 'mrn-base-stack' ),
		'NV' => __( 'Nevada', 'mrn-base-stack' ),
		'NH' => __( 'New Hampshire', 'mrn-base-stack' ),
		'NJ' => __( 'New Jersey', 'mrn-base-stack' ),
		'NM' => __( 'New Mexico', 'mrn-base-stack' ),
		'NY' => __( 'New York', 'mrn-base-stack' ),
		'NC' => __( 'North Carolina', 'mrn-base-stack' ),
		'ND' => __( 'North Dakota', 'mrn-base-stack' ),
		'OH' => __( 'Ohio', 'mrn-base-stack' ),
		'OK' => __( 'Oklahoma', 'mrn-base-stack' ),
		'OR' => __( 'Oregon', 'mrn-base-stack' ),
		'PA' => __( 'Pennsylvania', 'mrn-base-stack' ),
		'RI' => __( 'Rhode Island', 'mrn-base-stack' ),
		'SC' => __( 'South Carolina', 'mrn-base-stack' ),
		'SD' => __( 'South Dakota', 'mrn-base-stack' ),
		'TN' => __( 'Tennessee', 'mrn-base-stack' ),
		'TX' => __( 'Texas', 'mrn-base-stack' ),
		'UT' => __( 'Utah', 'mrn-base-stack' ),
		'VT' => __( 'Vermont', 'mrn-base-stack' ),
		'VA' => __( 'Virginia', 'mrn-base-stack' ),
		'WA' => __( 'Washington', 'mrn-base-stack' ),
		'WV' => __( 'West Virginia', 'mrn-base-stack' ),
		'WI' => __( 'Wisconsin', 'mrn-base-stack' ),
		'WY' => __( 'Wyoming', 'mrn-base-stack' ),
		'DC' => __( 'District of Columbia', 'mrn-base-stack' ),
	);
}

/**
 * Register theme-owned ACF options pages.
 */
function mrn_base_stack_register_theme_options_pages() {
	if ( ! function_exists( 'acf_add_options_page' ) ) {
		return;
	}

	acf_add_options_page(
		array(
			'page_title' => __( 'Theme Header/Footer', 'mrn-base-stack' ),
			'menu_title' => __( 'Theme Header/Footer', 'mrn-base-stack' ),
			'menu_slug'  => 'mrn-theme-header-footer',
			'capability' => 'edit_theme_options',
			'redirect'   => false,
			'position'   => 61,
			'icon_url'   => 'dashicons-layout',
		)
	);

	acf_add_options_page(
		array(
			'page_title' => __( 'Business Information', 'mrn-base-stack' ),
			'menu_title' => __( 'Business Information', 'mrn-base-stack' ),
			'menu_slug'  => 'mrn-business-information',
			'capability' => 'edit_theme_options',
			'redirect'   => false,
			'position'   => 62,
			'icon_url'   => 'dashicons-building',
		)
	);
}
add_action( 'acf/init', 'mrn_base_stack_register_theme_options_pages' );

/**
 * Register theme-owned options field groups.
 */
function mrn_base_stack_register_theme_options_field_groups() {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_local_field_group(
		array(
			'key'                   => 'group_mrn_theme_header_footer',
			'title'                 => __( 'Theme Header/Footer', 'mrn-base-stack' ),
			'fields'                => array(
				array(
					'key'       => 'field_mrn_theme_header_tab',
					'label'     => __( 'Header', 'mrn-base-stack' ),
					'name'      => '',
					'type'      => 'tab',
					'placement' => 'top',
					'endpoint'  => 0,
				),
				array(
					'key'           => 'field_mrn_theme_header_show_utility_menu',
					'label'         => __( 'Show Utility Menu', 'mrn-base-stack' ),
					'name'          => 'header_show_utility_menu',
					'type'          => 'true_false',
					'instructions'  => __( 'Uses the native Utility menu location registered in the theme.', 'mrn-base-stack' ),
					'required'      => 0,
					'default_value' => 0,
					'ui'            => 1,
				),
				array(
					'key'           => 'field_mrn_theme_header_show_search',
					'label'         => __( 'Show Search', 'mrn-base-stack' ),
					'name'          => 'header_show_search',
					'type'          => 'true_false',
					'instructions'  => __( 'Shows the stack search trigger area. This is intended for the stack search experience, not the default WordPress search form.', 'mrn-base-stack' ),
					'required'      => 0,
					'default_value' => 0,
					'ui'            => 1,
				),
				array(
					'key'           => 'field_mrn_theme_header_show_business_phone',
					'label'         => __( 'Show Business Phone', 'mrn-base-stack' ),
					'name'          => 'header_show_business_phone',
					'type'          => 'true_false',
					'instructions'  => __( 'Pulls the display value from Business Information.', 'mrn-base-stack' ),
					'required'      => 0,
					'default_value' => 0,
					'ui'            => 1,
				),
				array(
					'key'           => 'field_mrn_theme_header_show_business_profile',
					'label'         => __( 'Show Business Profile', 'mrn-base-stack' ),
					'name'          => 'header_show_business_profile',
					'type'          => 'true_false',
					'instructions'  => __( 'Pulls the Business Profile value from Business Information.', 'mrn-base-stack' ),
					'required'      => 0,
					'default_value' => 0,
					'ui'            => 1,
				),
				array(
					'key'       => 'field_mrn_theme_footer_tab',
					'label'     => __( 'Footer', 'mrn-base-stack' ),
					'name'      => '',
					'type'      => 'tab',
					'placement' => 'top',
					'endpoint'  => 0,
				),
				array(
					'key'           => 'field_mrn_theme_footer_show_footer_menu',
					'label'         => __( 'Show Footer Menu', 'mrn-base-stack' ),
					'name'          => 'footer_show_footer_menu',
					'type'          => 'true_false',
					'instructions'  => __( 'Uses the native Footer menu location registered in the theme.', 'mrn-base-stack' ),
					'default_value' => 0,
					'ui'            => 1,
				),
				array(
					'key'           => 'field_mrn_theme_footer_show_legal_menu',
					'label'         => __( 'Show Legal Menu', 'mrn-base-stack' ),
					'name'          => 'footer_show_legal_menu',
					'type'          => 'true_false',
					'instructions'  => __( 'Uses the native Legal menu location registered in the theme.', 'mrn-base-stack' ),
					'default_value' => 0,
					'ui'            => 1,
				),
				array(
					'key'           => 'field_mrn_theme_footer_show_business_profile',
					'label'         => __( 'Show Business Profile', 'mrn-base-stack' ),
					'name'          => 'footer_show_business_profile',
					'type'          => 'true_false',
					'instructions'  => __( 'Pulls the Business Profile value from Business Information.', 'mrn-base-stack' ),
					'default_value' => 0,
					'ui'            => 1,
				),
				array(
					'key'           => 'field_mrn_theme_footer_show_business_phone',
					'label'         => __( 'Show Business Phone', 'mrn-base-stack' ),
					'name'          => 'footer_show_business_phone',
					'type'          => 'true_false',
					'instructions'  => __( 'Pulls the Business Phone value from Business Information.', 'mrn-base-stack' ),
					'default_value' => 0,
					'ui'            => 1,
				),
				array(
					'key'           => 'field_mrn_theme_footer_show_text_phone',
					'label'         => __( 'Show Text / SMS / RCS', 'mrn-base-stack' ),
					'name'          => 'footer_show_text_phone',
					'type'          => 'true_false',
					'instructions'  => __( 'Pulls the text support number from Business Information.', 'mrn-base-stack' ),
					'default_value' => 0,
					'ui'            => 1,
				),
				array(
					'key'           => 'field_mrn_theme_footer_show_address',
					'label'         => __( 'Show Address', 'mrn-base-stack' ),
					'name'          => 'footer_show_address',
					'type'          => 'true_false',
					'instructions'  => __( 'Pulls the address block from Business Information.', 'mrn-base-stack' ),
					'default_value' => 0,
					'ui'            => 1,
				),
				array(
					'key'           => 'field_mrn_theme_footer_show_business_hours',
					'label'         => __( 'Show Business Hours', 'mrn-base-stack' ),
					'name'          => 'footer_show_business_hours',
					'type'          => 'true_false',
					'instructions'  => __( 'Shows weekday hours from Business Information.', 'mrn-base-stack' ),
					'default_value' => 0,
					'ui'            => 1,
				),
				array(
					'key'           => 'field_mrn_theme_footer_show_social_links',
					'label'         => __( 'Show Social Links', 'mrn-base-stack' ),
					'name'          => 'footer_show_social_links',
					'type'          => 'true_false',
					'instructions'  => __( 'Pulls social icons from Config Helper.', 'mrn-base-stack' ),
					'default_value' => 0,
					'ui'            => 1,
				),
				array(
					'key'           => 'field_mrn_theme_footer_copyright_text',
					'label'         => __( 'Copyright Text', 'mrn-base-stack' ),
					'name'          => 'footer_copyright_text',
					'type'          => 'text',
					'instructions'  => __( 'Optional custom copyright line. Leave blank to use the site name and current year.', 'mrn-base-stack' ),
					'default_value' => '',
				),
				array(
					'key'           => 'field_mrn_theme_footer_legal_text',
					'label'         => __( 'Legal Text', 'mrn-base-stack' ),
					'name'          => 'footer_legal_text',
					'type'          => 'textarea',
					'instructions'  => __( 'Optional legal/supporting text below the footer menus.', 'mrn-base-stack' ),
					'rows'          => 3,
					'new_lines'     => 'br',
					'default_value' => '',
				),
			),
			'location'              => array(
				array(
					array(
						'param'    => 'options_page',
						'operator' => '==',
						'value'    => 'mrn-theme-header-footer',
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

	acf_add_local_field_group(
		array(
			'key'                   => 'group_mrn_business_information',
			'title'                 => __( 'Business Information', 'mrn-base-stack' ),
			'fields'                => array(
				array(
					'key'           => 'field_mrn_business_profile',
					'label'         => __( 'Business Profile', 'mrn-base-stack' ),
					'name'          => 'business_profile',
					'type'          => 'text',
					'instructions'  => __( 'Short descriptive line for the business or organization.', 'mrn-base-stack' ),
					'required'      => 0,
					'wrapper'       => array(
						'width' => '50',
					),
					'default_value' => '',
				),
				array(
					'key'           => 'field_mrn_business_years_in_business',
					'label'         => __( 'Years In Business', 'mrn-base-stack' ),
					'name'          => 'years_in_business',
					'type'          => 'number',
					'instructions'  => __( 'Use for trust bars, stats, or footer/header support copy.', 'mrn-base-stack' ),
					'required'      => 0,
					'wrapper'       => array(
						'width' => '50',
					),
					'default_value' => '',
					'min'           => 0,
				),
				array(
					'key'           => 'field_mrn_business_logo',
					'label'         => __( 'Logo', 'mrn-base-stack' ),
					'name'          => 'logo',
					'type'          => 'image',
					'instructions'  => __( 'Optional business-specific logo override for theme sections that should not use the site logo.', 'mrn-base-stack' ),
					'required'      => 0,
					'wrapper'       => array(
						'width' => '50',
					),
					'return_format' => 'array',
					'preview_size'  => 'medium',
					'library'       => 'all',
				),
				array(
					'key'           => 'field_mrn_business_logo_inverted',
					'label'         => __( 'Logo Inverted', 'mrn-base-stack' ),
					'name'          => 'logo_inverted',
					'type'          => 'image',
					'instructions'  => __( 'Optional inverted version for dark or high-contrast backgrounds.', 'mrn-base-stack' ),
					'required'      => 0,
					'wrapper'       => array(
						'width' => '50',
					),
					'return_format' => 'array',
					'preview_size'  => 'medium',
					'library'       => 'all',
				),
				array(
					'key'           => 'field_mrn_business_logo_footer',
					'label'         => __( 'Footer Logo', 'mrn-base-stack' ),
					'name'          => 'logo_footer',
					'type'          => 'image',
					'instructions'  => __( 'Optional footer-specific logo override.', 'mrn-base-stack' ),
					'required'      => 0,
					'wrapper'       => array(
						'width' => '50',
					),
					'return_format' => 'array',
					'preview_size'  => 'medium',
					'library'       => 'all',
				),
				array(
					'key'           => 'field_mrn_business_logo_footer_inverted',
					'label'         => __( 'Footer Logo Inverted', 'mrn-base-stack' ),
					'name'          => 'logo_footer_inverted',
					'type'          => 'image',
					'instructions'  => __( 'Optional inverted footer logo for dark footer treatments.', 'mrn-base-stack' ),
					'required'      => 0,
					'wrapper'       => array(
						'width' => '50',
					),
					'return_format' => 'array',
					'preview_size'  => 'medium',
					'library'       => 'all',
				),
				array(
					'key'           => 'field_mrn_business_phone',
					'label'         => __( 'Business Phone', 'mrn-base-stack' ),
					'name'          => 'phone',
					'type'          => 'text',
					'instructions'  => __( 'Primary voice phone number for header/footer or contact callouts.', 'mrn-base-stack' ),
					'required'      => 0,
					'wrapper'       => array(
						'width' => '50',
					),
					'default_value' => '',
					'placeholder'   => '(919) 555-1234',
				),
				array(
					'key'           => 'field_mrn_business_text_phone',
					'label'         => __( 'Text / SMS / RCS', 'mrn-base-stack' ),
					'name'          => 'text_phone',
					'type'          => 'text',
					'instructions'  => __( 'Optional messaging number if text support differs from the primary business phone.', 'mrn-base-stack' ),
					'required'      => 0,
					'wrapper'       => array(
						'width' => '50',
					),
					'default_value' => '',
					'placeholder'   => '(919) 555-5678',
				),
				array(
					'key'          => 'field_mrn_business_address_tab',
					'label'        => __( 'Address', 'mrn-base-stack' ),
					'name'         => '',
					'type'         => 'tab',
					'placement'    => 'top',
					'endpoint'     => 0,
				),
				array(
					'key'           => 'field_mrn_business_address_line_1',
					'label'         => __( 'Address Line 1', 'mrn-base-stack' ),
					'name'          => 'address_line_1',
					'type'          => 'text',
					'required'      => 0,
					'wrapper'       => array(
						'width' => '50',
					),
					'default_value' => '',
				),
				array(
					'key'           => 'field_mrn_business_address_line_2',
					'label'         => __( 'Address Line 2', 'mrn-base-stack' ),
					'name'          => 'address_line_2',
					'type'          => 'text',
					'required'      => 0,
					'wrapper'       => array(
						'width' => '50',
					),
					'default_value' => '',
				),
				array(
					'key'           => 'field_mrn_business_address_city',
					'label'         => __( 'City', 'mrn-base-stack' ),
					'name'          => 'address_city',
					'type'          => 'text',
					'required'      => 0,
					'wrapper'       => array(
						'width' => '34',
					),
					'default_value' => '',
				),
				array(
					'key'           => 'field_mrn_business_address_state',
					'label'         => __( 'State / Region', 'mrn-base-stack' ),
					'name'          => 'address_state',
					'type'          => 'select',
					'required'      => 0,
					'wrapper'       => array(
						'width' => '33',
					),
					'choices'       => mrn_base_stack_get_us_state_choices(),
					'default_value' => '',
					'allow_null'    => 1,
					'ui'            => 1,
					'return_format' => 'value',
				),
				array(
					'key'           => 'field_mrn_business_address_postal_code',
					'label'         => __( 'Postal Code', 'mrn-base-stack' ),
					'name'          => 'address_postal_code',
					'type'          => 'text',
					'required'      => 0,
					'wrapper'       => array(
						'width' => '33',
					),
					'default_value' => '',
				),
				array(
					'key'           => 'field_mrn_business_address_country',
					'label'         => __( 'Country', 'mrn-base-stack' ),
					'name'          => 'address_country',
					'type'          => 'text',
					'required'      => 0,
					'default_value' => 'United States',
				),
				array(
					'key'          => 'field_mrn_business_hours_tab',
					'label'        => __( 'Business Hours', 'mrn-base-stack' ),
					'name'         => '',
					'type'         => 'tab',
					'placement'    => 'top',
					'endpoint'     => 0,
				),
				array(
					'key'             => 'field_mrn_business_hours_monday_label',
					'label'           => '',
					'name'            => '',
					'type'            => 'message',
					'message'         => __( 'Monday', 'mrn-base-stack' ),
					'new_lines'       => 'wpautop',
					'esc_html'        => 1,
					'wrapper'         => array(
						'width' => '34',
					),
				),
				array(
					'key'            => 'field_mrn_business_hours_monday_open',
					'label'          => __( 'Open', 'mrn-base-stack' ),
					'name'           => 'hours_monday_open',
					'type'           => 'time_picker',
					'required'       => 0,
					'wrapper'        => array(
						'width' => '33',
					),
					'display_format' => 'g:i a',
					'return_format'  => 'g:i a',
				),
				array(
					'key'            => 'field_mrn_business_hours_monday_close',
					'label'          => __( 'Close', 'mrn-base-stack' ),
					'name'           => 'hours_monday_close',
					'type'           => 'time_picker',
					'required'       => 0,
					'wrapper'        => array(
						'width' => '33',
					),
					'display_format' => 'g:i a',
					'return_format'  => 'g:i a',
				),
				array(
					'key'             => 'field_mrn_business_hours_tuesday_label',
					'label'           => '',
					'name'            => '',
					'type'            => 'message',
					'message'         => __( 'Tuesday', 'mrn-base-stack' ),
					'new_lines'       => 'wpautop',
					'esc_html'        => 1,
					'wrapper'         => array(
						'width' => '34',
					),
				),
				array(
					'key'            => 'field_mrn_business_hours_tuesday_open',
					'label'          => __( 'Open', 'mrn-base-stack' ),
					'name'           => 'hours_tuesday_open',
					'type'           => 'time_picker',
					'required'       => 0,
					'wrapper'        => array(
						'width' => '33',
					),
					'display_format' => 'g:i a',
					'return_format'  => 'g:i a',
				),
				array(
					'key'            => 'field_mrn_business_hours_tuesday_close',
					'label'          => __( 'Close', 'mrn-base-stack' ),
					'name'           => 'hours_tuesday_close',
					'type'           => 'time_picker',
					'required'       => 0,
					'wrapper'        => array(
						'width' => '33',
					),
					'display_format' => 'g:i a',
					'return_format'  => 'g:i a',
				),
				array(
					'key'             => 'field_mrn_business_hours_wednesday_label',
					'label'           => '',
					'name'            => '',
					'type'            => 'message',
					'message'         => __( 'Wednesday', 'mrn-base-stack' ),
					'new_lines'       => 'wpautop',
					'esc_html'        => 1,
					'wrapper'         => array(
						'width' => '34',
					),
				),
				array(
					'key'            => 'field_mrn_business_hours_wednesday_open',
					'label'          => __( 'Open', 'mrn-base-stack' ),
					'name'           => 'hours_wednesday_open',
					'type'           => 'time_picker',
					'required'       => 0,
					'wrapper'        => array(
						'width' => '33',
					),
					'display_format' => 'g:i a',
					'return_format'  => 'g:i a',
				),
				array(
					'key'            => 'field_mrn_business_hours_wednesday_close',
					'label'          => __( 'Close', 'mrn-base-stack' ),
					'name'           => 'hours_wednesday_close',
					'type'           => 'time_picker',
					'required'       => 0,
					'wrapper'        => array(
						'width' => '33',
					),
					'display_format' => 'g:i a',
					'return_format'  => 'g:i a',
				),
				array(
					'key'             => 'field_mrn_business_hours_thursday_label',
					'label'           => '',
					'name'            => '',
					'type'            => 'message',
					'message'         => __( 'Thursday', 'mrn-base-stack' ),
					'new_lines'       => 'wpautop',
					'esc_html'        => 1,
					'wrapper'         => array(
						'width' => '34',
					),
				),
				array(
					'key'            => 'field_mrn_business_hours_thursday_open',
					'label'          => __( 'Open', 'mrn-base-stack' ),
					'name'           => 'hours_thursday_open',
					'type'           => 'time_picker',
					'required'       => 0,
					'wrapper'        => array(
						'width' => '33',
					),
					'display_format' => 'g:i a',
					'return_format'  => 'g:i a',
				),
				array(
					'key'            => 'field_mrn_business_hours_thursday_close',
					'label'          => __( 'Close', 'mrn-base-stack' ),
					'name'           => 'hours_thursday_close',
					'type'           => 'time_picker',
					'required'       => 0,
					'wrapper'        => array(
						'width' => '33',
					),
					'display_format' => 'g:i a',
					'return_format'  => 'g:i a',
				),
				array(
					'key'             => 'field_mrn_business_hours_friday_label',
					'label'           => '',
					'name'            => '',
					'type'            => 'message',
					'message'         => __( 'Friday', 'mrn-base-stack' ),
					'new_lines'       => 'wpautop',
					'esc_html'        => 1,
					'wrapper'         => array(
						'width' => '34',
					),
				),
				array(
					'key'            => 'field_mrn_business_hours_friday_open',
					'label'          => __( 'Open', 'mrn-base-stack' ),
					'name'           => 'hours_friday_open',
					'type'           => 'time_picker',
					'required'       => 0,
					'wrapper'        => array(
						'width' => '33',
					),
					'display_format' => 'g:i a',
					'return_format'  => 'g:i a',
				),
				array(
					'key'            => 'field_mrn_business_hours_friday_close',
					'label'          => __( 'Close', 'mrn-base-stack' ),
					'name'           => 'hours_friday_close',
					'type'           => 'time_picker',
					'required'       => 0,
					'wrapper'        => array(
						'width' => '33',
					),
					'display_format' => 'g:i a',
					'return_format'  => 'g:i a',
				),
				array(
					'key'          => 'field_mrn_business_holidays_tab',
					'label'        => __( 'Holidays', 'mrn-base-stack' ),
					'name'         => '',
					'type'         => 'tab',
					'placement'    => 'top',
					'endpoint'     => 0,
				),
				array(
					'key'          => 'field_mrn_business_holidays',
					'label'        => __( 'Holiday Hours', 'mrn-base-stack' ),
					'name'         => 'holiday_hours',
					'type'         => 'repeater',
					'instructions' => __( 'Add only the holidays you need. Use Closed for full closure or Modified Hours for special open/close times.', 'mrn-base-stack' ),
					'required'     => 0,
					'layout'       => 'row',
					'button_label' => __( 'Add Holiday', 'mrn-base-stack' ),
					'sub_fields'   => array(
						array(
							'key'           => 'field_mrn_business_holiday_name',
							'label'         => __( 'Holiday Name', 'mrn-base-stack' ),
							'name'          => 'name',
							'type'          => 'text',
							'required'      => 1,
							'wrapper'       => array(
								'width' => '24',
							),
							'default_value' => '',
						),
						array(
							'key'            => 'field_mrn_business_holiday_date',
							'label'          => __( 'Date', 'mrn-base-stack' ),
							'name'           => 'date',
							'type'           => 'date_picker',
							'required'       => 1,
							'wrapper'        => array(
								'width' => '18',
							),
							'display_format' => 'F j, Y',
							'return_format'  => 'Y-m-d',
							'first_day'      => 0,
						),
						array(
							'key'           => 'field_mrn_business_holiday_status',
							'label'         => __( 'Status', 'mrn-base-stack' ),
							'name'          => 'status',
							'type'          => 'select',
							'required'      => 1,
							'wrapper'       => array(
								'width' => '18',
							),
							'choices'       => array(
								'closed'         => __( 'Closed', 'mrn-base-stack' ),
								'modified_hours' => __( 'Modified Hours', 'mrn-base-stack' ),
							),
							'default_value' => 'closed',
							'ui'            => 0,
							'allow_null'    => 0,
						),
						array(
							'key'            => 'field_mrn_business_holiday_open',
							'label'          => __( 'Open', 'mrn-base-stack' ),
							'name'           => 'open',
							'type'           => 'time_picker',
							'required'       => 0,
							'wrapper'        => array(
								'width' => '14',
							),
							'display_format' => 'g:i a',
							'return_format'  => 'g:i a',
						),
						array(
							'key'            => 'field_mrn_business_holiday_close',
							'label'          => __( 'Close', 'mrn-base-stack' ),
							'name'           => 'close',
							'type'           => 'time_picker',
							'required'       => 0,
							'wrapper'        => array(
								'width' => '14',
							),
							'display_format' => 'g:i a',
							'return_format'  => 'g:i a',
						),
						array(
							'key'           => 'field_mrn_business_holiday_note',
							'label'         => __( 'Note', 'mrn-base-stack' ),
							'name'          => 'note',
							'type'          => 'text',
							'required'      => 0,
							'wrapper'       => array(
								'width' => '12',
							),
							'default_value' => '',
						),
					),
				),
			),
			'location'              => array(
				array(
					array(
						'param'    => 'options_page',
						'operator' => '==',
						'value'    => 'mrn-business-information',
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
add_action( 'acf/init', 'mrn_base_stack_register_theme_options_field_groups', 20 );

/**
 * Return the canonical business information payload for theme consumers.
 *
 * @return array<string, mixed>
 */
function mrn_base_stack_get_business_information() {
	$defaults = array(
		'business_profile'     => '',
		'years_in_business'    => '',
		'logo'                 => null,
		'logo_inverted'        => null,
		'logo_footer'          => null,
		'logo_footer_inverted' => null,
		'phone'                => '',
		'phone_uri'            => '',
		'text_phone'           => '',
		'text_phone_uri'       => '',
		'address'              => array(),
		'business_hours'       => array(),
		'holiday_hours'        => array(),
	);

	if ( ! function_exists( 'get_field' ) ) {
		return $defaults;
	}

	$business_information = array(
		'business_profile'     => get_field( 'business_profile', 'option' ),
		'years_in_business'    => get_field( 'years_in_business', 'option' ),
		'logo'                 => get_field( 'logo', 'option' ),
		'logo_inverted'        => get_field( 'logo_inverted', 'option' ),
		'logo_footer'          => get_field( 'logo_footer', 'option' ),
		'logo_footer_inverted' => get_field( 'logo_footer_inverted', 'option' ),
		'phone'                => mrn_base_stack_format_phone_number( get_field( 'phone', 'option' ) ),
		'phone_uri'            => mrn_base_stack_get_phone_uri( get_field( 'phone', 'option' ) ),
		'text_phone'           => mrn_base_stack_format_phone_number( get_field( 'text_phone', 'option' ) ),
		'text_phone_uri'       => mrn_base_stack_get_phone_uri( get_field( 'text_phone', 'option' ) ),
		'address'              => array(
			'line_1'      => get_field( 'address_line_1', 'option' ),
			'line_2'      => get_field( 'address_line_2', 'option' ),
			'city'        => get_field( 'address_city', 'option' ),
			'state'       => get_field( 'address_state', 'option' ),
			'postal_code' => get_field( 'address_postal_code', 'option' ),
			'country'     => get_field( 'address_country', 'option' ),
		),
		'business_hours'       => array(
			'monday'    => array(
				'open'  => get_field( 'hours_monday_open', 'option' ),
				'close' => get_field( 'hours_monday_close', 'option' ),
			),
			'tuesday'   => array(
				'open'  => get_field( 'hours_tuesday_open', 'option' ),
				'close' => get_field( 'hours_tuesday_close', 'option' ),
			),
			'wednesday' => array(
				'open'  => get_field( 'hours_wednesday_open', 'option' ),
				'close' => get_field( 'hours_wednesday_close', 'option' ),
			),
			'thursday'  => array(
				'open'  => get_field( 'hours_thursday_open', 'option' ),
				'close' => get_field( 'hours_thursday_close', 'option' ),
			),
			'friday'    => array(
				'open'  => get_field( 'hours_friday_open', 'option' ),
				'close' => get_field( 'hours_friday_close', 'option' ),
			),
		),
		'holiday_hours'        => get_field( 'holiday_hours', 'option' ),
	);

	return wp_parse_args( $business_information, $defaults );
}

/**
 * Convert theme business hours into schema.org openingHoursSpecification values.
 *
 * @param array<string, mixed> $business_information Business information payload.
 * @return array<int, array<string, mixed>>
 */
function mrn_base_stack_get_business_opening_hours_schema( $business_information ) {
	$day_map        = array(
		'monday'    => 'https://schema.org/Monday',
		'tuesday'   => 'https://schema.org/Tuesday',
		'wednesday' => 'https://schema.org/Wednesday',
		'thursday'  => 'https://schema.org/Thursday',
		'friday'    => 'https://schema.org/Friday',
	);
	$hours_payload  = array();
	$business_hours = isset( $business_information['business_hours'] ) && is_array( $business_information['business_hours'] ) ? $business_information['business_hours'] : array();

	foreach ( $day_map as $day => $schema_day ) {
		$hours = isset( $business_hours[ $day ] ) && is_array( $business_hours[ $day ] ) ? $business_hours[ $day ] : array();
		$open  = isset( $hours['open'] ) ? mrn_base_stack_get_schema_time( $hours['open'] ) : '';
		$close = isset( $hours['close'] ) ? mrn_base_stack_get_schema_time( $hours['close'] ) : '';

		if ( '' === $open || '' === $close ) {
			continue;
		}

		$hours_payload[] = array(
			'@type'     => 'OpeningHoursSpecification',
			'dayOfWeek' => $schema_day,
			'opens'     => $open,
			'closes'    => $close,
		);
	}

	return $hours_payload;
}

/**
 * Normalize a stored time string to schema.org time format.
 *
 * @param mixed $value Raw time value.
 * @return string
 */
function mrn_base_stack_get_schema_time( $value ) {
	$value = is_scalar( $value ) ? trim( (string) $value ) : '';

	if ( '' === $value ) {
		return '';
	}

	$timestamp = strtotime( $value );

	if ( false === $timestamp ) {
		return '';
	}

	return gmdate( 'H:i', $timestamp );
}

/**
 * Build the canonical business schema payload.
 *
 * @return array<string, mixed>
 */
function mrn_base_stack_get_business_schema_data() {
	$business_information = mrn_base_stack_get_business_information();
	$business_logo        = mrn_base_stack_get_business_logo( 'header' );
	$social_links         = function_exists( 'mrn_config_helper_get_social_links' ) ? mrn_config_helper_get_social_links() : array();
	$same_as              = array();

	if ( is_array( $social_links ) ) {
		foreach ( $social_links as $row ) {
			if ( ! is_array( $row ) || empty( $row['url'] ) ) {
				continue;
			}

			$same_as[] = esc_url_raw( $row['url'] );
		}
	}

	$same_as = array_values( array_filter( array_unique( $same_as ) ) );
	$address = isset( $business_information['address'] ) && is_array( $business_information['address'] ) ? $business_information['address'] : array();

	$schema = array(
		'@context' => 'https://schema.org',
		'@type'    => 'Organization',
		'@id'      => trailingslashit( home_url( '/' ) ) . '#organization',
		'name'     => get_bloginfo( 'name' ),
		'url'      => home_url( '/' ),
	);

	if ( ! empty( $business_information['business_profile'] ) ) {
		$schema['description'] = wp_strip_all_tags( (string) $business_information['business_profile'] );
	}

	if ( ! empty( $business_logo['url'] ) ) {
		$schema['logo'] = esc_url_raw( $business_logo['url'] );
	}

	if ( ! empty( $business_information['phone'] ) ) {
		$schema['telephone'] = (string) $business_information['phone'];
	}

	if ( ! empty( $same_as ) ) {
		$schema['sameAs'] = $same_as;
	}

	if ( ! empty( $address['line_1'] ) || ! empty( $address['city'] ) || ! empty( $address['state'] ) || ! empty( $address['postal_code'] ) || ! empty( $address['country'] ) ) {
		$schema['address'] = array_filter(
			array(
				'@type'           => 'PostalAddress',
				'streetAddress'   => trim( implode( ', ', array_filter( array( (string) $address['line_1'], (string) $address['line_2'] ) ) ) ),
				'addressLocality' => isset( $address['city'] ) ? (string) $address['city'] : '',
				'addressRegion'   => isset( $address['state'] ) ? (string) $address['state'] : '',
				'postalCode'      => isset( $address['postal_code'] ) ? (string) $address['postal_code'] : '',
				'addressCountry'  => isset( $address['country'] ) ? (string) $address['country'] : '',
			)
		);
	}

	$opening_hours = mrn_base_stack_get_business_opening_hours_schema( $business_information );

	if ( ! empty( $opening_hours ) ) {
		$schema['openingHoursSpecification'] = $opening_hours;
	}

	$contact_points = array();

	if ( ! empty( $business_information['phone'] ) ) {
		$contact_points[] = array_filter(
			array(
				'@type'          => 'ContactPoint',
				'contactType'    => 'customer support',
				'telephone'      => (string) $business_information['phone'],
				'hoursAvailable' => $opening_hours,
			)
		);
	}

	if ( ! empty( $business_information['text_phone'] ) ) {
		$contact_points[] = array_filter(
			array(
				'@type'       => 'ContactPoint',
				'contactType' => 'text support',
				'telephone'   => (string) $business_information['text_phone'],
			)
		);
	}

	if ( ! empty( $contact_points ) ) {
		$schema['contactPoint'] = $contact_points;
	}

	return $schema;
}

/**
 * Print business schema in the document head.
 */
function mrn_base_stack_print_business_schema() {
	$schema = mrn_base_stack_get_business_schema_data();

	if ( empty( $schema['name'] ) ) {
		return;
	}

	echo '<script type="application/ld+json" id="mrn-business-schema">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
add_action( 'wp_head', 'mrn_base_stack_print_business_schema', 40 );

/**
 * Return the preferred business logo payload for a given theme context.
 *
 * Supported contexts:
 * - header
 * - header_inverted
 * - footer
 * - footer_inverted
 *
 * @param string $context Logo context.
 * @return array<string, mixed>|null
 */
function mrn_base_stack_get_business_logo( $context = 'header' ) {
	$business_information = mrn_base_stack_get_business_information();

	$map = array(
		'header'          => array( 'logo', 'logo_inverted' ),
		'header_inverted' => array( 'logo_inverted', 'logo' ),
		'footer'          => array( 'logo_footer', 'logo', 'logo_footer_inverted', 'logo_inverted' ),
		'footer_inverted' => array( 'logo_footer_inverted', 'logo_footer', 'logo_inverted', 'logo' ),
	);

	$keys = isset( $map[ $context ] ) ? $map[ $context ] : $map['header'];

	foreach ( $keys as $key ) {
		if ( ! empty( $business_information[ $key ] ) && is_array( $business_information[ $key ] ) ) {
			return $business_information[ $key ];
		}
	}

	return null;
}

/**
 * Return the canonical header/footer options payload for theme consumers.
 *
 * @return array<string, mixed>
 */
function mrn_base_stack_get_theme_header_footer_options() {
	$defaults = array(
		'header_show_utility_menu'     => false,
		'header_show_search'           => false,
		'header_show_business_phone'   => false,
		'header_show_business_profile' => false,
		'footer_show_footer_menu'      => false,
		'footer_show_legal_menu'       => false,
		'footer_show_business_profile' => false,
		'footer_show_business_phone'   => false,
		'footer_show_text_phone'       => false,
		'footer_show_address'          => false,
		'footer_show_business_hours'   => false,
		'footer_show_social_links'     => false,
		'footer_copyright_text'        => '',
		'footer_legal_text'            => '',
	);

	if ( ! function_exists( 'get_field' ) ) {
		return $defaults;
	}

	$options = array(
		'header_show_utility_menu'     => (bool) get_field( 'header_show_utility_menu', 'option' ),
		'header_show_search'           => (bool) get_field( 'header_show_search', 'option' ),
		'header_show_business_phone'   => (bool) get_field( 'header_show_business_phone', 'option' ),
		'header_show_business_profile' => (bool) get_field( 'header_show_business_profile', 'option' ),
		'footer_show_footer_menu'      => (bool) get_field( 'footer_show_footer_menu', 'option' ),
		'footer_show_legal_menu'       => (bool) get_field( 'footer_show_legal_menu', 'option' ),
		'footer_show_business_profile' => (bool) get_field( 'footer_show_business_profile', 'option' ),
		'footer_show_business_phone'   => (bool) get_field( 'footer_show_business_phone', 'option' ),
		'footer_show_text_phone'       => (bool) get_field( 'footer_show_text_phone', 'option' ),
		'footer_show_address'          => (bool) get_field( 'footer_show_address', 'option' ),
		'footer_show_business_hours'   => (bool) get_field( 'footer_show_business_hours', 'option' ),
		'footer_show_social_links'     => (bool) get_field( 'footer_show_social_links', 'option' ),
		'footer_copyright_text'        => (string) get_field( 'footer_copyright_text', 'option' ),
		'footer_legal_text'            => (string) get_field( 'footer_legal_text', 'option' ),
	);

	return wp_parse_args( $options, $defaults );
}

/**
 * Normalize a phone number into a tel: URI-safe string.
 *
 * @param mixed $value Raw phone number.
 * @return string
 */
function mrn_base_stack_get_phone_uri( $value ) {
	$value = is_scalar( $value ) ? (string) $value : '';
	$value = trim( $value );

	if ( '' === $value ) {
		return '';
	}

	$normalized = preg_replace( '/\D+/', '', $value );

	if ( ! is_string( $normalized ) || '' === $normalized ) {
		return '';
	}

	$normalized = substr( $normalized, 0, 10 );

	return 'tel:' . $normalized;
}

/**
 * Format a phone number for display.
 *
 * @param mixed $value Raw phone number.
 * @return string
 */
function mrn_base_stack_format_phone_number( $value ) {
	$value = is_scalar( $value ) ? trim( (string) $value ) : '';

	if ( '' === $value ) {
		return '';
	}

	$digits = preg_replace( '/\D+/', '', $value );

	if ( ! is_string( $digits ) || '' === $digits ) {
		return trim( (string) $value );
	}

	$local_digits = substr( $digits, 0, 10 );

	if ( strlen( $local_digits ) <= 3 ) {
		$formatted = $local_digits;
	} elseif ( strlen( $local_digits ) <= 6 ) {
		$formatted = '(' . substr( $local_digits, 0, 3 ) . ') ' . substr( $local_digits, 3 );
	} else {
		$formatted = '(' . substr( $local_digits, 0, 3 ) . ') ' . substr( $local_digits, 3, 3 ) . '-' . substr( $local_digits, 6, 4 );
	}

	return $formatted;
}

/**
 * Validate theme business contact fields.
 *
 * @param bool|string $valid Whether the value is valid.
 * @param mixed       $value Field value.
 * @param array       $field ACF field config.
 * @return bool|string
 */
function mrn_base_stack_validate_business_phone_field( $valid, $value, $field ) {
	unset( $field );

	if ( true !== $valid ) {
		return $valid;
	}

	$value = is_scalar( $value ) ? trim( (string) $value ) : '';

	if ( '' === $value ) {
		return true;
	}

	if ( ! preg_match( '/^[\d\s().\-]+$/', $value ) ) {
		return __( 'Use a valid 10-digit phone number, for example (919) 555-1234.', 'mrn-base-stack' );
	}

	$digits = preg_replace( '/\D+/', '', $value );

	if ( ! is_string( $digits ) || 10 !== strlen( $digits ) ) {
		return __( 'Use a valid 10-digit phone number, for example (919) 555-1234.', 'mrn-base-stack' );
	}

	return true;
}
add_filter( 'acf/validate_value/key=field_mrn_business_phone', 'mrn_base_stack_validate_business_phone_field', 10, 3 );
add_filter( 'acf/validate_value/key=field_mrn_business_text_phone', 'mrn_base_stack_validate_business_phone_field', 10, 3 );

/**
 * Validate holiday hour rows.
 *
 * @param bool|string $valid Whether the value is valid.
 * @param mixed       $value Field value.
 * @return bool|string
 */
function mrn_base_stack_validate_holiday_hours_field( $valid, $value ) {
	if ( true !== $valid || ! is_array( $value ) ) {
		return $valid;
	}

	foreach ( $value as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}

		$status = isset( $row['field_mrn_business_holiday_status'] ) ? (string) $row['field_mrn_business_holiday_status'] : '';
		$open   = isset( $row['field_mrn_business_holiday_open'] ) ? trim( (string) $row['field_mrn_business_holiday_open'] ) : '';
		$close  = isset( $row['field_mrn_business_holiday_close'] ) ? trim( (string) $row['field_mrn_business_holiday_close'] ) : '';

		if ( 'modified_hours' === $status && ( '' === $open || '' === $close ) ) {
			return __( 'Holiday rows with Modified Hours must include both Open and Close times.', 'mrn-base-stack' );
		}
	}

	return true;
}
add_filter( 'acf/validate_value/key=field_mrn_business_holidays', 'mrn_base_stack_validate_holiday_hours_field', 10, 2 );

/**
 * Convert business phone fields into tel-style inputs in ACF.
 */
function mrn_base_stack_print_business_phone_input_script() {
	?>
	<script>
		(function () {
			var formatPhoneValue = function (value) {
				var digits = String(value || '').replace(/\D/g, '').slice(0, 10);

				if (!digits) {
					return '';
				}

				var formatted = '';

				if (digits.length <= 3) {
					formatted = digits;
				} else if (digits.length <= 6) {
					formatted = '(' + digits.slice(0, 3) + ') ' + digits.slice(3);
				} else {
					formatted = '(' + digits.slice(0, 3) + ') ' + digits.slice(3, 6) + '-' + digits.slice(6, 10);
				}

				return formatted;
			};

			var bindPhoneInput = function (input) {
				if (!input || input.dataset.mrnPhoneBound === 'yes') {
					return;
				}

				input.dataset.mrnPhoneBound = 'yes';
				input.setAttribute('type', 'tel');
				input.setAttribute('inputmode', 'tel');
				input.setAttribute('autocomplete', 'tel');
				input.value = formatPhoneValue(input.value);
			};

			var initPhoneInputs = function (context) {
				var scope = context && context.querySelectorAll ? context : document;

				['field_mrn_business_phone', 'field_mrn_business_text_phone'].forEach(function (fieldKey) {
					scope.querySelectorAll('.acf-field[data-key="' + fieldKey + '"] input').forEach(bindPhoneInput);
				});
			};

			document.addEventListener('DOMContentLoaded', function () {
				initPhoneInputs(document);
			});

			document.addEventListener('input', function (event) {
				var input = event.target;

				if (!input || !input.closest) {
					return;
				}

				var field = input.closest('.acf-field[data-key="field_mrn_business_phone"], .acf-field[data-key="field_mrn_business_text_phone"]');

				if (!field) {
					return;
				}

				bindPhoneInput(input);

				var selectionStart = input.selectionStart;
				var previousLength = input.value.length;
				var formatted = formatPhoneValue(input.value);

				if (formatted === input.value) {
					return;
				}

				input.value = formatted;

				if (typeof selectionStart === 'number' && typeof input.setSelectionRange === 'function') {
					var nextPosition = Math.max(0, selectionStart + (formatted.length - previousLength));
					input.setSelectionRange(nextPosition, nextPosition);
				}
			});

			document.addEventListener('blur', function (event) {
				var input = event.target;

				if (!input || !input.closest) {
					return;
				}

				var field = input.closest('.acf-field[data-key="field_mrn_business_phone"], .acf-field[data-key="field_mrn_business_text_phone"]');

				if (!field) {
					return;
				}

				bindPhoneInput(input);
				input.value = formatPhoneValue(input.value);
			}, true);

			if (window.acf && typeof window.acf.addAction === 'function') {
				window.acf.addAction('ready', function ($el) {
					initPhoneInputs($el && $el[0] ? $el[0] : document);
				});
				window.acf.addAction('append', function ($el) {
					initPhoneInputs($el && $el[0] ? $el[0] : document);
				});
			}
		})();
	</script>
	<?php
}
add_action( 'acf/input/admin_footer', 'mrn_base_stack_print_business_phone_input_script' );
