<?php
/**
 * Standard mobile navigation settings and rendering helpers.
 *
 * @package mrn-base-stack
 */

/**
 * Build a Site Styles color field for the mobile menu tab.
 *
 * @param string $key_suffix Field key suffix.
 * @param string $label      Field label.
 * @param string $name       Field name.
 * @return array<string, mixed>
 */
function mrn_base_stack_get_mobile_navigation_color_field( $key_suffix, $label, $name ) {
	return array(
		'key'           => 'field_mrn_mobile_menu_' . sanitize_key( (string) $key_suffix ),
		'label'         => $label,
		'name'          => $name,
		'type'          => 'select',
		'choices'       => function_exists( 'mrn_base_stack_get_site_color_choices' ) ? mrn_base_stack_get_site_color_choices() : array(),
		'allow_null'    => 1,
		'multiple'      => 0,
		'required'      => 0,
		'ui'            => 1,
		'ajax'          => 0,
		'return_format' => 'value',
		'instructions'  => __( 'Leave blank to use the base theme default.', 'mrn-base-stack' ),
		'wrapper'       => array(
			'width' => '50',
		),
	);
}

/**
 * Return MRN Token choices for the full-screen drawer header.
 *
 * @return array<string, string>
 */
function mrn_base_stack_get_mobile_navigation_token_choices() {
	if ( ! function_exists( 'mrn_tokens_get_all' ) ) {
		return array();
	}

	$choices = array();
	foreach ( mrn_tokens_get_all() as $name => $definition ) {
		$name  = sanitize_key( (string) $name );
		$label = is_array( $definition ) && ! empty( $definition['label'] ) ? (string) $definition['label'] : $name;

		if ( '' !== $name ) {
			$choices[ $name ] = sprintf( '%1$s (%2$s)', $label, $name );
		}
	}

	return $choices;
}

/**
 * Return the Mobile Menu tab fields for the Theme Header/Footer options page.
 *
 * @return array<int, array<string, mixed>>
 */
function mrn_base_stack_get_mobile_navigation_fields() {
	$reusable_post_types = function_exists( 'mrn_rbl_get_post_types' ) ? mrn_rbl_get_post_types() : array();
	$bottom_sources      = array(
		'none' => __( 'None', 'mrn-base-stack' ),
	);

	if ( ! empty( $reusable_post_types ) ) {
		$bottom_sources['reusable_block'] = __( 'Reusable Block', 'mrn-base-stack' );
	}

	$fields = array(
		array(
			'key'       => 'field_mrn_theme_mobile_menu_tab',
			'label'     => __( 'Mobile Menu', 'mrn-base-stack' ),
			'name'      => '',
			'type'      => 'tab',
			'placement' => 'top',
			'endpoint'  => 0,
		),
		array(
			'key'           => 'field_mrn_mobile_menu_enabled',
			'label'         => __( 'Enable Standard Mobile/Tablet Menu', 'mrn-base-stack' ),
			'name'          => 'mobile_menu_enabled',
			'type'          => 'true_false',
			'instructions'  => __( 'Uses the Primary menu as a full-height mobile/tablet drawer. Choose the activation breakpoint below, then choose whether the drawer uses the site header or covers the full viewport.', 'mrn-base-stack' ),
			'default_value' => 1,
			'ui'            => 1,
		),
		array(
			'key'           => 'field_mrn_mobile_menu_breakpoint',
			'label'         => __( 'Mobile Menu Breakpoint', 'mrn-base-stack' ),
			'name'          => 'mobile_menu_breakpoint',
			'type'          => 'range',
			'instructions'  => __( 'The mobile drawer is active at and below this viewport width. The default is 1199px.', 'mrn-base-stack' ),
			'default_value' => 1199,
			'min'           => 320,
			'max'           => 1600,
			'step'          => 1,
			'append'        => 'px',
			'wrapper'       => array(
				'width' => '50',
			),
		),
		array(
			'key'           => 'field_mrn_mobile_menu_use_site_header',
			'label'         => __( 'Use Site Header', 'mrn-base-stack' ),
			'name'          => 'mobile_menu_use_site_header',
			'type'          => 'true_false',
			'instructions'  => __( 'When enabled, the drawer begins below the site header. Disable this to cover the full viewport and show the site logo inside the drawer.', 'mrn-base-stack' ),
			'default_value' => 1,
			'ui'            => 1,
		),
		array(
			'key'           => 'field_mrn_mobile_menu_drawer_mode',
			'label'         => __( 'Drawer Interaction', 'mrn-base-stack' ),
			'name'          => 'mobile_menu_drawer_mode',
			'type'          => 'button_group',
			'choices'       => array(
				'overlay' => __( 'Overlay Page', 'mrn-base-stack' ),
				'push'    => __( 'Push Page', 'mrn-base-stack' ),
			),
			'default_value' => 'overlay',
			'layout'        => 'horizontal',
			'return_format' => 'value',
			'instructions'  => __( 'Overlay keeps the page in place. Push slides the drawer in from the right while moving the page to the left.', 'mrn-base-stack' ),
		),
		array(
			'key'               => 'field_mrn_mobile_menu_full_screen_heading',
			'label'             => '',
			'name'              => '',
			'type'              => 'message',
			'message'           => '<h3>' . esc_html__( 'Full-Screen Header', 'mrn-base-stack' ) . '</h3>',
			'esc_html'          => 0,
			'new_lines'         => '',
			'conditional_logic' => array(
				array(
					array(
						'field'    => 'field_mrn_mobile_menu_use_site_header',
						'operator' => '==',
						'value'    => '0',
					),
				),
			),
		),
		array(
			'key'               => 'field_mrn_mobile_menu_logo',
			'label'             => __( 'Mobile Menu Logo', 'mrn-base-stack' ),
			'name'              => 'mobile_menu_logo',
			'type'              => 'image',
			'instructions'      => __( 'Optional logo used only in the full-screen mobile menu. Leave blank to use the normal site logo.', 'mrn-base-stack' ),
			'return_format'     => 'id',
			'preview_size'      => 'medium',
			'library'           => 'all',
			'mime_types'        => 'jpg,jpeg,png,gif,webp,svg',
			'wrapper'           => array(
				'width' => '50',
			),
			'conditional_logic' => array(
				array(
					array(
						'field'    => 'field_mrn_mobile_menu_use_site_header',
						'operator' => '==',
						'value'    => '0',
					),
				),
			),
		),
		array(
			'key'               => 'field_mrn_mobile_menu_logo_max_height',
			'label'             => __( 'Logo Maximum Height', 'mrn-base-stack' ),
			'name'              => 'mobile_menu_logo_max_height',
			'type'              => 'range',
			'default_value'     => 48,
			'min'               => 24,
			'max'               => 120,
			'step'              => 1,
			'append'            => 'px',
			'wrapper'           => array(
				'width' => '50',
			),
			'conditional_logic' => array(
				array(
					array(
						'field'    => 'field_mrn_mobile_menu_use_site_header',
						'operator' => '==',
						'value'    => '0',
					),
				),
			),
		),
		array(
			'key'               => 'field_mrn_mobile_menu_header_action_type',
			'label'             => __( 'Right-Side Content', 'mrn-base-stack' ),
			'name'              => 'mobile_menu_header_action_type',
			'type'              => 'button_group',
			'choices'           => array(
				'none'           => __( 'None', 'mrn-base-stack' ),
				'contact_button' => __( 'Contact Button', 'mrn-base-stack' ),
				'token'          => __( 'Token', 'mrn-base-stack' ),
			),
			'default_value'     => 'none',
			'layout'            => 'horizontal',
			'return_format'     => 'value',
			'instructions'      => __( 'Displays beside the logo in the full-screen drawer header.', 'mrn-base-stack' ),
			'conditional_logic' => array(
				array(
					array(
						'field'    => 'field_mrn_mobile_menu_use_site_header',
						'operator' => '==',
						'value'    => '0',
					),
				),
			),
		),
		array(
			'key'               => 'field_mrn_mobile_menu_contact_button',
			'label'             => __( 'Contact Button', 'mrn-base-stack' ),
			'name'              => 'mobile_menu_contact_button',
			'type'              => 'link',
			'return_format'     => 'array',
			'conditional_logic' => array(
				array(
					array(
						'field'    => 'field_mrn_mobile_menu_use_site_header',
						'operator' => '==',
						'value'    => '0',
					),
					array(
						'field'    => 'field_mrn_mobile_menu_header_action_type',
						'operator' => '==',
						'value'    => 'contact_button',
					),
				),
			),
		),
		array(
			'key'               => 'field_mrn_mobile_menu_header_token',
			'label'             => __( 'Token', 'mrn-base-stack' ),
			'name'              => 'mobile_menu_header_token',
			'type'              => 'select',
			'choices'           => mrn_base_stack_get_mobile_navigation_token_choices(),
			'allow_null'        => 1,
			'ui'                => 1,
			'return_format'     => 'value',
			'instructions'      => __( 'Tokens with a URL render as links; other tokens render as text.', 'mrn-base-stack' ),
			'conditional_logic' => array(
				array(
					array(
						'field'    => 'field_mrn_mobile_menu_use_site_header',
						'operator' => '==',
						'value'    => '0',
					),
					array(
						'field'    => 'field_mrn_mobile_menu_header_action_type',
						'operator' => '==',
						'value'    => 'token',
					),
				),
			),
		),
		array(
			'key'       => 'field_mrn_mobile_menu_appearance_heading',
			'label'     => '',
			'name'      => '',
			'type'      => 'message',
			'message'   => '<h3>' . esc_html__( 'Appearance', 'mrn-base-stack' ) . '</h3>',
			'esc_html'  => 0,
			'new_lines' => '',
		),
		mrn_base_stack_get_mobile_navigation_color_field( 'background_color', __( 'Drawer Background', 'mrn-base-stack' ), 'mobile_menu_background_color' ),
		array(
			'key'           => 'field_mrn_mobile_menu_background_transparency_enabled',
			'label'         => __( 'Transparent Drawer Background', 'mrn-base-stack' ),
			'name'          => 'mobile_menu_background_transparency_enabled',
			'type'          => 'true_false',
			'instructions'  => __( 'Allow page content and the header to show through the drawer background.', 'mrn-base-stack' ),
			'default_value' => 1,
			'ui'            => 1,
			'wrapper'       => array(
				'width' => '50',
			),
		),
		array(
			'key'               => 'field_mrn_mobile_menu_background_opacity',
			'label'             => __( 'Drawer Background Opacity', 'mrn-base-stack' ),
			'name'              => 'mobile_menu_background_opacity',
			'type'              => 'range',
			'instructions'      => __( 'Higher values make the drawer background more solid.', 'mrn-base-stack' ),
			'default_value'     => 94,
			'min'               => 0,
			'max'               => 100,
			'step'              => 1,
			'append'            => '%',
			'wrapper'           => array(
				'width' => '50',
			),
			'conditional_logic' => array(
				array(
					array(
						'field'    => 'field_mrn_mobile_menu_background_transparency_enabled',
						'operator' => '==',
						'value'    => '1',
					),
				),
			),
		),
		array(
			'key'           => 'field_mrn_mobile_menu_blur_enabled',
			'label'         => __( 'Blur Content Behind Drawer', 'mrn-base-stack' ),
			'name'          => 'mobile_menu_blur_enabled',
			'type'          => 'true_false',
			'instructions'  => __( 'Apply a backdrop blur behind transparent drawer surfaces.', 'mrn-base-stack' ),
			'default_value' => 1,
			'ui'            => 1,
			'wrapper'       => array(
				'width' => '50',
			),
		),
		array(
			'key'               => 'field_mrn_mobile_menu_blur_amount',
			'label'             => __( 'Blur Strength', 'mrn-base-stack' ),
			'name'              => 'mobile_menu_blur_amount',
			'type'              => 'range',
			'default_value'     => 16,
			'min'               => 0,
			'max'               => 40,
			'step'              => 1,
			'append'            => 'px',
			'wrapper'           => array(
				'width' => '50',
			),
			'conditional_logic' => array(
				array(
					array(
						'field'    => 'field_mrn_mobile_menu_blur_enabled',
						'operator' => '==',
						'value'    => '1',
					),
				),
			),
		),
		mrn_base_stack_get_mobile_navigation_color_field( 'link_color', __( 'Link Color', 'mrn-base-stack' ), 'mobile_menu_link_color' ),
		mrn_base_stack_get_mobile_navigation_color_field( 'link_hover_color', __( 'Link Hover / Focus Color', 'mrn-base-stack' ), 'mobile_menu_link_hover_color' ),
		mrn_base_stack_get_mobile_navigation_color_field( 'submenu_background_color', __( 'Submenu Background', 'mrn-base-stack' ), 'mobile_menu_submenu_background_color' ),
		array(
			'key'           => 'field_mrn_mobile_menu_submenu_transparency_enabled',
			'label'         => __( 'Transparent Submenu Background', 'mrn-base-stack' ),
			'name'          => 'mobile_menu_submenu_transparency_enabled',
			'type'          => 'true_false',
			'instructions'  => __( 'Use a translucent background for submenus and the bottom-content area.', 'mrn-base-stack' ),
			'default_value' => 1,
			'ui'            => 1,
			'wrapper'       => array(
				'width' => '50',
			),
		),
		array(
			'key'               => 'field_mrn_mobile_menu_submenu_opacity',
			'label'             => __( 'Submenu Background Opacity', 'mrn-base-stack' ),
			'name'              => 'mobile_menu_submenu_opacity',
			'type'              => 'range',
			'instructions'      => __( 'Higher values make submenu and bottom-content backgrounds more solid.', 'mrn-base-stack' ),
			'default_value'     => 8,
			'min'               => 0,
			'max'               => 100,
			'step'              => 1,
			'append'            => '%',
			'wrapper'           => array(
				'width' => '50',
			),
			'conditional_logic' => array(
				array(
					array(
						'field'    => 'field_mrn_mobile_menu_submenu_transparency_enabled',
						'operator' => '==',
						'value'    => '1',
					),
				),
			),
		),
		mrn_base_stack_get_mobile_navigation_color_field( 'divider_color', __( 'Divider Color', 'mrn-base-stack' ), 'mobile_menu_divider_color' ),
		array(
			'key'       => 'field_mrn_mobile_menu_bottom_content_heading',
			'label'     => '',
			'name'      => '',
			'type'      => 'message',
			'message'   => '<h3>' . esc_html__( 'Bottom Content', 'mrn-base-stack' ) . '</h3>',
			'esc_html'  => 0,
			'new_lines' => '',
		),
		array(
			'key'           => 'field_mrn_mobile_menu_bottom_content_source',
			'label'         => __( 'Content Source', 'mrn-base-stack' ),
			'name'          => 'mobile_menu_bottom_content_source',
			'type'          => 'button_group',
			'choices'       => $bottom_sources,
			'default_value' => 'none',
			'layout'        => 'horizontal',
			'return_format' => 'value',
			'instructions'  => __( 'Bottom content is pushed to the bottom of the drawer when the menu is shorter than the screen.', 'mrn-base-stack' ),
		),
	);

	if ( ! empty( $reusable_post_types ) ) {
		$fields[] = array(
			'key'               => 'field_mrn_mobile_menu_reusable_block',
			'label'             => __( 'Reusable Block', 'mrn-base-stack' ),
			'name'              => 'mobile_menu_reusable_block',
			'type'              => 'post_object',
			'post_type'         => $reusable_post_types,
			'post_status'       => array( 'publish' ),
			'return_format'     => 'id',
			'ui'                => 1,
			'allow_null'        => 1,
			'multiple'          => 0,
			'instructions'      => __( 'Choose a published reusable block. Child themes may provide another source through the mobile-menu bottom-content filters.', 'mrn-base-stack' ),
			'conditional_logic' => array(
				array(
					array(
						'field'    => 'field_mrn_mobile_menu_bottom_content_source',
						'operator' => '==',
						'value'    => 'reusable_block',
					),
				),
			),
		);
	}

	return $fields;
}

/**
 * Return normalized mobile menu settings.
 *
 * @return array<string, mixed>
 */
function mrn_base_stack_get_mobile_navigation_options() {
	$defaults = array(
		'enabled'                  => true,
		'breakpoint'               => 1199,
		'use_site_header'          => true,
		'drawer_mode'              => 'overlay',
		'mobile_logo_id'           => 0,
		'logo_max_height'          => 48,
		'header_action_type'       => 'none',
		'contact_button'           => array(),
		'header_token'             => '',
		'background_color'         => '',
		'background_transparency'  => true,
		'background_opacity'       => 94,
		'blur_enabled'             => true,
		'blur_amount'              => 16,
		'link_color'               => '',
		'link_hover_color'         => '',
		'submenu_background_color' => '',
		'submenu_transparency'     => true,
		'submenu_opacity'          => 8,
		'divider_color'            => '',
		'bottom_content_source'    => 'none',
		'reusable_block_id'        => 0,
	);

	if ( ! function_exists( 'get_field' ) ) {
		return $defaults;
	}

	$source = sanitize_key( (string) get_field( 'mobile_menu_bottom_content_source', 'option' ) );
	if ( ! in_array( $source, array( 'none', 'reusable_block' ), true ) ) {
		$source = 'none';
	}
	$header_action_type = sanitize_key( (string) get_field( 'mobile_menu_header_action_type', 'option' ) );
	if ( ! in_array( $header_action_type, array( 'none', 'contact_button', 'token' ), true ) ) {
		$header_action_type = 'none';
	}
	$drawer_mode = sanitize_key( (string) get_field( 'mobile_menu_drawer_mode', 'option' ) );
	if ( ! in_array( $drawer_mode, array( 'overlay', 'push' ), true ) ) {
		$drawer_mode = 'overlay';
	}
	$contact_button = get_field( 'mobile_menu_contact_button', 'option' );
	if ( ! is_array( $contact_button ) || empty( $contact_button['url'] ) || empty( $contact_button['title'] ) ) {
		$contact_button = array();
	} else {
		$contact_button = array(
			'url'    => esc_url_raw( (string) $contact_button['url'] ),
			'title'  => sanitize_text_field( (string) $contact_button['title'] ),
			'target' => '_blank' === ( $contact_button['target'] ?? '' ) ? '_blank' : '_self',
		);
	}

	$stored_enabled                 = get_option( 'options_mobile_menu_enabled', null );
	$stored_breakpoint              = get_option( 'options_mobile_menu_breakpoint', null );
	$stored_use_site_header         = get_option( 'options_mobile_menu_use_site_header', null );
	$stored_logo_max_height         = get_option( 'options_mobile_menu_logo_max_height', null );
	$stored_background_transparency = get_option( 'options_mobile_menu_background_transparency_enabled', null );
	$stored_background_opacity      = get_option( 'options_mobile_menu_background_opacity', null );
	$stored_blur_enabled            = get_option( 'options_mobile_menu_blur_enabled', null );
	$stored_blur_amount             = get_option( 'options_mobile_menu_blur_amount', null );
	$stored_submenu_transparency    = get_option( 'options_mobile_menu_submenu_transparency_enabled', null );
	$stored_submenu_opacity         = get_option( 'options_mobile_menu_submenu_opacity', null );
	$breakpoint                     = null === $stored_breakpoint ? 1199 : absint( get_field( 'mobile_menu_breakpoint', 'option' ) );
	$background_opacity             = null === $stored_background_opacity ? 94 : absint( get_field( 'mobile_menu_background_opacity', 'option' ) );
	$blur_amount                    = null === $stored_blur_amount ? 16 : absint( get_field( 'mobile_menu_blur_amount', 'option' ) );
	$submenu_opacity                = null === $stored_submenu_opacity ? 8 : absint( get_field( 'mobile_menu_submenu_opacity', 'option' ) );
	$logo_max_height                = null === $stored_logo_max_height ? 48 : absint( get_field( 'mobile_menu_logo_max_height', 'option' ) );
	$options                        = array(
		'enabled'                  => null === $stored_enabled ? true : (bool) get_field( 'mobile_menu_enabled', 'option' ),
		'breakpoint'               => min( 1600, max( 320, $breakpoint ) ),
		'use_site_header'          => null === $stored_use_site_header ? true : (bool) get_field( 'mobile_menu_use_site_header', 'option' ),
		'drawer_mode'              => $drawer_mode,
		'mobile_logo_id'           => absint( get_field( 'mobile_menu_logo', 'option' ) ),
		'logo_max_height'          => min( 120, max( 24, $logo_max_height ) ),
		'header_action_type'       => $header_action_type,
		'contact_button'           => $contact_button,
		'header_token'             => sanitize_key( (string) get_field( 'mobile_menu_header_token', 'option' ) ),
		'background_color'         => mrn_base_stack_normalize_site_color_slug( get_field( 'mobile_menu_background_color', 'option' ) ),
		'background_transparency'  => null === $stored_background_transparency ? true : (bool) get_field( 'mobile_menu_background_transparency_enabled', 'option' ),
		'background_opacity'       => $background_opacity <= 100 ? $background_opacity : 94,
		'blur_enabled'             => null === $stored_blur_enabled ? true : (bool) get_field( 'mobile_menu_blur_enabled', 'option' ),
		'blur_amount'              => $blur_amount <= 40 ? $blur_amount : 16,
		'link_color'               => mrn_base_stack_normalize_site_color_slug( get_field( 'mobile_menu_link_color', 'option' ) ),
		'link_hover_color'         => mrn_base_stack_normalize_site_color_slug( get_field( 'mobile_menu_link_hover_color', 'option' ) ),
		'submenu_background_color' => mrn_base_stack_normalize_site_color_slug( get_field( 'mobile_menu_submenu_background_color', 'option' ) ),
		'submenu_transparency'     => null === $stored_submenu_transparency ? true : (bool) get_field( 'mobile_menu_submenu_transparency_enabled', 'option' ),
		'submenu_opacity'          => $submenu_opacity <= 100 ? $submenu_opacity : 8,
		'divider_color'            => mrn_base_stack_normalize_site_color_slug( get_field( 'mobile_menu_divider_color', 'option' ) ),
		'bottom_content_source'    => $source,
		'reusable_block_id'        => absint( get_field( 'mobile_menu_reusable_block', 'option' ) ),
	);

	/**
	 * Filter the standard mobile menu settings.
	 *
	 * @param array<string, mixed> $options Normalized settings.
	 */
	return (array) apply_filters( 'mrn_base_stack_mobile_navigation_options', wp_parse_args( $options, $defaults ) );
}

/**
 * Build mobile menu CSS custom properties from Site Styles choices.
 *
 * @param array<string, mixed> $options Mobile menu settings.
 * @return string
 */
function mrn_base_stack_get_mobile_navigation_style( array $options ) {
	$property_map = array(
		'background_color'         => '--mrn-mobile-menu-background-base',
		'link_color'               => '--mrn-mobile-menu-link',
		'link_hover_color'         => '--mrn-mobile-menu-link-hover',
		'submenu_background_color' => '--mrn-mobile-menu-submenu-background-base',
		'divider_color'            => '--mrn-mobile-menu-divider',
	);
	$styles       = array();

	foreach ( $property_map as $option_key => $property_name ) {
		$value = function_exists( 'mrn_base_stack_get_site_color_css_value' ) ? mrn_base_stack_get_site_color_css_value( $options[ $option_key ] ?? '' ) : '';
		if ( '' !== $value ) {
			$styles[] = $property_name . ':' . $value;
		}
	}

	$background_opacity = ! empty( $options['background_transparency'] ) ? absint( $options['background_opacity'] ?? 94 ) : 100;
	$submenu_opacity    = ! empty( $options['submenu_transparency'] ) ? absint( $options['submenu_opacity'] ?? 8 ) : 100;
	$blur_amount        = ! empty( $options['blur_enabled'] ) ? absint( $options['blur_amount'] ?? 16 ) : 0;

	$styles[] = '--mrn-mobile-menu-background-opacity:' . min( 100, $background_opacity ) . '%';
	$styles[] = '--mrn-mobile-menu-submenu-opacity:' . min( 100, $submenu_opacity ) . '%';
	$styles[] = '--mrn-mobile-menu-blur:' . min( 40, $blur_amount ) . 'px';
	$styles[] = '--mrn-mobile-menu-logo-max-height:' . min( 120, max( 24, absint( $options['logo_max_height'] ?? 48 ) ) ) . 'px';
	$styles[] = '--mrn-mobile-menu-breakpoint:' . min( 1600, max( 320, absint( $options['breakpoint'] ?? 1199 ) ) ) . 'px';

	return implode( ';', $styles );
}

/**
 * Return the optional right-side content for the full-screen drawer header.
 *
 * @param array<string, mixed> $options Mobile menu settings.
 * @return string
 */
function mrn_base_stack_get_mobile_navigation_header_action_markup( array $options ) {
	$type   = sanitize_key( (string) ( $options['header_action_type'] ?? 'none' ) );
	$markup = '';

	if ( 'contact_button' === $type && ! empty( $options['contact_button'] ) && is_array( $options['contact_button'] ) ) {
		$link   = $options['contact_button'];
		$url    = esc_url( (string) ( $link['url'] ?? '' ) );
		$title  = sanitize_text_field( (string) ( $link['title'] ?? '' ) );
		$target = '_blank' === ( $link['target'] ?? '' ) ? '_blank' : '_self';

		if ( '' !== $url && '' !== $title ) {
			$markup = sprintf(
				'<a class="mrn-mobile-navigation__header-action-link mrn-mobile-navigation__header-action-link--button mrn-ui__link mrn-ui__link--button" href="%1$s" target="%2$s"%3$s>%4$s</a>',
				$url,
				esc_attr( $target ),
				'_blank' === $target ? ' rel="noopener noreferrer"' : '',
				esc_html( $title )
			);
		}
	} elseif ( 'token' === $type && ! empty( $options['header_token'] ) && function_exists( 'mrn_tokens_get' ) ) {
		$token = mrn_tokens_get( sanitize_key( (string) $options['header_token'] ) );

		if ( is_array( $token ) ) {
			$value = sanitize_text_field( (string) ( $token['value'] ?? '' ) );
			$url   = esc_url( (string) ( $token['url'] ?? '' ) );

			if ( '' !== $value && '' !== $url ) {
				$markup = sprintf( '<a class="mrn-mobile-navigation__header-action-link mrn-mobile-navigation__header-action-link--token" href="%1$s">%2$s</a>', $url, esc_html( $value ) );
			} elseif ( '' !== $value ) {
				$markup = sprintf( '<span class="mrn-mobile-navigation__header-action-text">%s</span>', esc_html( $value ) );
			}
		}
	}

	/**
	 * Filter the optional full-screen drawer header action markup.
	 *
	 * @param string               $markup  Escaped action markup.
	 * @param string               $type    Configured action type.
	 * @param array<string, mixed> $options Mobile menu settings.
	 */
	return (string) apply_filters( 'mrn_base_stack_mobile_navigation_header_action_markup', $markup, $type, $options );
}

/**
 * Return the configured bottom content markup.
 *
 * @param array<string, mixed> $options Mobile menu settings.
 * @return string
 */
function mrn_base_stack_get_mobile_navigation_bottom_markup( array $options ) {
	$source = sanitize_key( (string) ( $options['bottom_content_source'] ?? 'none' ) );

	/**
	 * Filter the bottom-content source before the base theme resolves it.
	 *
	 * Child themes can introduce a future ACF-row source without changing the
	 * drawer markup contract.
	 *
	 * @param string               $source  Source slug.
	 * @param array<string, mixed> $options Mobile menu settings.
	 */
	$source = sanitize_key( (string) apply_filters( 'mrn_base_stack_mobile_navigation_bottom_source', $source, $options ) );
	$markup = '';

	if (
		'reusable_block' === $source
		&& ! empty( $options['reusable_block_id'] )
		&& function_exists( 'mrn_rbl_get_block_post' )
		&& function_exists( 'mrn_rbl_get_render_context' )
		&& function_exists( 'mrn_rbl_render_context' )
	) {
		$block = mrn_rbl_get_block_post( (int) $options['reusable_block_id'] );

		if ( $block instanceof WP_Post ) {
			$markup = mrn_rbl_render_context(
				mrn_rbl_get_render_context(
					$block,
					array(
						'host_post_id'    => get_queried_object_id(),
						'host_row_index'  => 0,
						'suppress_anchor' => true,
					)
				)
			);
		}
	}

	/**
	 * Filter the final mobile-menu bottom markup.
	 *
	 * @param string               $markup  Rendered markup.
	 * @param string               $source  Source slug.
	 * @param array<string, mixed> $options Mobile menu settings.
	 */
	$markup = (string) apply_filters( 'mrn_base_stack_mobile_navigation_bottom_markup', $markup, $source, $options );

	ob_start();
	do_action( 'mrn_base_stack_mobile_navigation_bottom', $options, $source );
	$action_markup = (string) ob_get_clean();

	return trim( $markup . $action_markup );
}
