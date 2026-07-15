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
 * Return the available Dashicon names from core.
 *
 * @return array<int, string>
 */
function mrn_base_stack_get_dashicons() {
	$css_path = ABSPATH . WPINC . '/css/dashicons.css';

	if ( ! file_exists( $css_path ) ) {
		return array();
	}

	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads a local core CSS file from disk.
	$contents = file_get_contents( $css_path );

	if ( false === $contents ) {
		return array();
	}

	preg_match_all( '/\\.dashicons-([a-z0-9-]+):before\\s*\\{/i', $contents, $matches );

	if ( empty( $matches[1] ) ) {
		return array();
	}

	$icons = array_unique( $matches[1] );
	sort( $icons );

	return $icons;
}

/**
 * Return the shared Font Awesome metadata payload.
 *
 * @return array<string, mixed>
 */
function mrn_base_stack_get_fontawesome_icons() {
	if ( function_exists( 'mrn_shared_assets_get_fontawesome_icons' ) ) {
		return mrn_shared_assets_get_fontawesome_icons();
	}

	$fallback_path = WP_CONTENT_DIR . '/mu-plugins/mrn-shared-assets/assets/fontawesome/icons.json';

	if ( ! file_exists( $fallback_path ) ) {
		return array();
	}

	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads a local bundled JSON fallback from disk.
	$contents = file_get_contents( $fallback_path );

	if ( false === $contents ) {
		return array();
	}

	$data = json_decode( $contents, true );

	return is_array( $data ) ? $data : array();
}

/**
 * Return the built-in header search Dashicon choices.
 *
 * @return array<string, string>
 */
function mrn_base_stack_get_header_search_standard_icon_choices() {
	$choices = array();
	$icons   = mrn_base_stack_get_dashicons();

	foreach ( $icons as $icon ) {
		$key             = 'dashicons-' . $icon;
		$choices[ $key ] = ucwords( str_replace( '-', ' ', $icon ) );
	}

	return $choices;
}

/**
 * Return the built-in Font Awesome header search icon choices.
 *
 * @return array<string, string>
 */
function mrn_base_stack_get_header_search_fontawesome_choices() {
	$choices = array();
	$icons   = mrn_base_stack_get_fontawesome_icons();
	$styles  = array(
		'solid'   => 'fa-solid',
		'regular' => 'fa-regular',
		'brands'  => 'fa-brands',
	);

	foreach ( $styles as $style => $class_prefix ) {
		if ( empty( $icons[ $style ] ) || ! is_array( $icons[ $style ] ) ) {
			continue;
		}

		foreach ( $icons[ $style ] as $icon ) {
			if ( ! is_array( $icon ) || empty( $icon['name'] ) ) {
				continue;
			}

			$name  = sanitize_key( (string) $icon['name'] );
			$label = ! empty( $icon['label'] ) ? (string) $icon['label'] : ucwords( str_replace( '-', ' ', $name ) );

			if ( '' === $name ) {
				continue;
			}

			$choices[ $class_prefix . ' fa-' . $name ] = $label . ' (' . ucfirst( $style ) . ')';
		}
	}

	return $choices;
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
 * Append classes to an ACF field wrapper definition.
 *
 * @param array<string, mixed> $field         ACF field definition.
 * @param string               $wrapper_class Wrapper class(es) to append.
 * @return array<string, mixed>
 */
function mrn_base_stack_append_field_wrapper_class( array $field, $wrapper_class ) {
	$wrapper_class = trim( (string) $wrapper_class );
	if ( '' === $wrapper_class ) {
		return $field;
	}

	if ( ! isset( $field['wrapper'] ) || ! is_array( $field['wrapper'] ) ) {
		$field['wrapper'] = array();
	}

	$existing = isset( $field['wrapper']['class'] ) ? (string) $field['wrapper']['class'] : '';
	$classes  = preg_split( '/\s+/', trim( $existing . ' ' . $wrapper_class ) );
	$classes  = array_filter( is_array( $classes ) ? $classes : array() );
	$classes  = array_values( array_unique( $classes ) );

	$field['wrapper']['class'] = implode( ' ', $classes );

	return $field;
}

/**
 * Return the wrapper class contract for a Header/Footer sub-tab panel.
 *
 * @param string $section Section key.
 * @param string $subtab  Sub-tab key.
 * @return string
 */
function mrn_base_stack_get_theme_header_footer_subtab_panel_class( $section, $subtab ) {
	$section = sanitize_html_class( (string) $section );
	$subtab  = sanitize_html_class( (string) $subtab );

	return 'mrn-theme-hf-subtab-panel mrn-theme-hf-subtab-section--' . $section . ' mrn-theme-hf-subtab--' . $subtab;
}

/**
 * Return the shared row width choices for Header/Footer content width controls.
 *
 * @return array<string, string>
 */
function mrn_base_stack_get_theme_header_footer_content_width_choices() {
	if ( function_exists( 'mrn_base_stack_get_section_width_choices' ) ) {
		return mrn_base_stack_get_section_width_choices();
	}

	return array(
		'content'    => __( 'Content', 'mrn-base-stack' ),
		'wide'       => __( 'Wide', 'mrn-base-stack' ),
		'full-width' => __( 'Full Width', 'mrn-base-stack' ),
	);
}

/**
 * Normalize a Header/Footer content width value.
 *
 * @param mixed  $value Raw stored value.
 * @param string $default_width Default width choice.
 * @return string
 */
function mrn_base_stack_normalize_theme_header_footer_content_width( $value, $default_width = 'wide' ) {
	if ( function_exists( 'mrn_base_stack_normalize_section_width' ) ) {
		return mrn_base_stack_normalize_section_width( $value, $default_width );
	}

	$width = is_string( $value ) ? sanitize_key( $value ) : '';

	if ( in_array( $value, array( 1, '1', true, 'true' ), true ) ) {
		$width = 'full-width';
	}

	if ( ! in_array( $width, array( 'content', 'wide', 'full-width' ), true ) ) {
		$width = $default_width;
	}

	return $width;
}

/**
 * Build a Header/Footer content-width ACF field.
 *
 * @param string $section Section key.
 * @return array<string, mixed>
 */
function mrn_base_stack_get_theme_header_footer_content_width_field( $section ) {
	$section = sanitize_key( (string) $section );

	return array(
		'key'               => 'field_mrn_theme_' . $section . '_content_width',
		'label'             => __( 'Content Width', 'mrn-base-stack' ),
		'name'              => $section . '_content_width',
		'type'              => 'select',
		'choices'           => mrn_base_stack_get_theme_header_footer_content_width_choices(),
		'default_value'     => 'wide',
		'allow_null'        => 0,
		'multiple'          => 0,
		'ui'                => 1,
		'return_format'     => 'value',
		'instructions'      => __( 'Uses the same width options as builder rows.', 'mrn-base-stack' ),
		'wrapper'           => array(
			'width' => '50',
		),
	);
}

/**
 * Convert a Header/Footer content width into a grid modifier class.
 *
 * @param mixed  $value Raw stored value.
 * @param string $default_width Default width choice.
 * @return string
 */
function mrn_base_stack_get_theme_header_footer_content_width_class( $value, $default_width = 'wide' ) {
	$width = mrn_base_stack_normalize_theme_header_footer_content_width( $value, $default_width );
	$slug  = 'full-width' === $width ? 'full' : $width;

	return 'mrn-theme-hf-layout-grid--width-' . sanitize_html_class( $slug );
}

/**
 * Return Site Styles color choices with a theme-default option.
 *
 * @return array<string, string>
 */
function mrn_base_stack_get_theme_header_footer_color_choices() {
	return array_merge(
		array( '' => __( 'Theme Default', 'mrn-base-stack' ) ),
		mrn_base_stack_get_site_color_choices()
	);
}

/**
 * Return basic web-safe font choices for Header/Footer typography.
 *
 * @return array<string, string>
 */
function mrn_base_stack_get_theme_header_footer_basic_font_choices() {
	return array(
		''                                       => __( 'Theme Default', 'mrn-base-stack' ),
		'system-ui'                              => __( 'System UI', 'mrn-base-stack' ),
		'Arial, Helvetica, sans-serif'           => __( 'Arial', 'mrn-base-stack' ),
		'Helvetica, Arial, sans-serif'           => __( 'Helvetica', 'mrn-base-stack' ),
		'Georgia, serif'                         => __( 'Georgia', 'mrn-base-stack' ),
		'"Times New Roman", Times, serif'        => __( 'Times New Roman', 'mrn-base-stack' ),
		'Verdana, Geneva, sans-serif'            => __( 'Verdana', 'mrn-base-stack' ),
		'Tahoma, Geneva, sans-serif'             => __( 'Tahoma', 'mrn-base-stack' ),
		'"Trebuchet MS", Arial, sans-serif'      => __( 'Trebuchet MS', 'mrn-base-stack' ),
		'"Courier New", Courier, monospace'      => __( 'Courier New', 'mrn-base-stack' ),
		'serif'                                  => __( 'Generic Serif', 'mrn-base-stack' ),
		'sans-serif'                             => __( 'Generic Sans Serif', 'mrn-base-stack' ),
		'monospace'                              => __( 'Generic Monospace', 'mrn-base-stack' ),
	);
}

/**
 * Sanitize a font family or font stack value.
 *
 * @param mixed $value Raw font family value.
 * @return string
 */
function mrn_base_stack_sanitize_theme_header_footer_font_family( $value ) {
	$value = is_scalar( $value ) ? trim( (string) $value ) : '';
	if ( '' === $value ) {
		return '';
	}

	$value = str_replace( array( "\r", "\n", "\t" ), ' ', $value );
	$value = preg_replace( '/[^a-zA-Z0-9 ,._"\'-]/', '', $value );
	$value = is_string( $value ) ? preg_replace( '/\s+/', ' ', $value ) : '';
	$value = is_string( $value ) ? trim( $value ) : '';

	return substr( $value, 0, 120 );
}

/**
 * Return configured Google Fonts families plus basic web defaults.
 *
 * @return array<string, string>
 */
function mrn_base_stack_get_theme_header_footer_font_choices() {
	$choices  = mrn_base_stack_get_theme_header_footer_basic_font_choices();
	$settings = array();

	if ( class_exists( 'MRN_Google_Fonts' ) && is_callable( array( 'MRN_Google_Fonts', 'get_settings' ) ) ) {
		$settings = MRN_Google_Fonts::get_settings();
	} else {
		$settings = get_option( 'mrn_google_fonts_settings', array() );
	}

	if ( ! is_array( $settings ) ) {
		return $choices;
	}

	$families = array();
	foreach ( array( 'body_font_family', 'heading_font_family', 'accent_font_family' ) as $setting_key ) {
		if ( ! empty( $settings[ $setting_key ] ) ) {
			$families[] = $settings[ $setting_key ];
		}
	}

	if ( ! empty( $settings['font_faces'] ) && is_array( $settings['font_faces'] ) ) {
		foreach ( $settings['font_faces'] as $family_key => $face_config ) {
			if ( is_string( $family_key ) && '' !== trim( $family_key ) ) {
				$families[] = $family_key;
				continue;
			}

			if ( is_array( $face_config ) && ! empty( $face_config['family'] ) ) {
				$families[] = $face_config['family'];
			}
		}
	}

	foreach ( $families as $family ) {
		$family = mrn_base_stack_sanitize_theme_header_footer_font_family( $family );
		$family = trim( str_replace( array( '"', "'" ), '', $family ) );
		$family = is_string( $family ) ? preg_replace( '/\s+/', ' ', $family ) : '';
		$family = is_string( $family ) ? trim( $family ) : '';

		if ( '' === $family || isset( $choices[ $family ] ) ) {
			continue;
		}

		$choices[ $family ] = $family;
	}

	return $choices;
}

/**
 * Normalize a Header/Footer font family option to an allowed value.
 *
 * @param mixed $value Raw font family value.
 * @return string
 */
function mrn_base_stack_normalize_theme_header_footer_font_family( $value ) {
	$value = mrn_base_stack_sanitize_theme_header_footer_font_family( $value );
	if ( '' === $value ) {
		return '';
	}

	$choices = mrn_base_stack_get_theme_header_footer_font_choices();

	return isset( $choices[ $value ] ) ? $value : '';
}

/**
 * Convert a font family choice into a CSS font stack.
 *
 * @param mixed $value Raw font family value.
 * @return string
 */
function mrn_base_stack_get_theme_header_footer_font_stack( $value ) {
	$value = mrn_base_stack_normalize_theme_header_footer_font_family( $value );
	if ( '' === $value ) {
		return '';
	}

	$format_family = static function ( $family ) {
		$family = trim( str_replace( array( '"', "'" ), '', (string) $family ) );
		if ( '' === $family ) {
			return '';
		}

		$lower = strtolower( $family );
		if ( in_array( $lower, array( 'system-ui', '-apple-system', 'blinkmacsystemfont', 'sans-serif', 'serif', 'monospace', 'cursive', 'fantasy', 'inherit' ), true ) ) {
			return $lower;
		}

		if ( preg_match( '/^[a-zA-Z0-9_-]+$/', $family ) ) {
			return $family;
		}

		return '"' . str_replace( '"', '', $family ) . '"';
	};

	if ( false !== strpos( $value, ',' ) ) {
		$parts = array();
		foreach ( explode( ',', $value ) as $part ) {
			$part = $format_family( $part );
			if ( '' !== $part ) {
				$parts[] = $part;
			}
		}

		return implode( ', ', $parts );
	}

	if ( 'system-ui' === strtolower( $value ) ) {
		return 'system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif';
	}

	return $format_family( $value ) . ', system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif';
}

/**
 * Build a Header/Footer Site Styles color select.
 *
 * @param string $section Section key.
 * @param string $suffix  Field suffix.
 * @param string $label   Field label.
 * @return array<string, mixed>
 */
function mrn_base_stack_get_theme_header_footer_color_field( $section, $suffix, $label ) {
	$section = sanitize_key( (string) $section );
	$suffix  = sanitize_key( (string) $suffix );

	return array(
		'key'           => 'field_mrn_theme_' . $section . '_' . $suffix,
		'label'         => $label,
		'name'          => $section . '_' . $suffix,
		'type'          => 'select',
		'choices'       => mrn_base_stack_get_theme_header_footer_color_choices(),
		'default_value' => '',
		'allow_null'    => 1,
		'ui'            => 1,
		'return_format' => 'value',
	);
}

/**
 * Build a Header/Footer font-family select.
 *
 * @param string $section Section key.
 * @return array<string, mixed>
 */
function mrn_base_stack_get_theme_header_footer_font_family_field( $section ) {
	$section = sanitize_key( (string) $section );

	return array(
		'key'           => 'field_mrn_theme_' . $section . '_font_family',
		'label'         => __( 'Font Family', 'mrn-base-stack' ),
		'name'          => $section . '_font_family',
		'type'          => 'select',
		'choices'       => mrn_base_stack_get_theme_header_footer_font_choices(),
		'default_value' => '',
		'allow_null'    => 1,
		'ui'            => 1,
		'return_format' => 'value',
		'instructions'  => __( 'Includes basic web defaults plus Google Fonts configured in Site Styles.', 'mrn-base-stack' ),
	);
}

/**
 * Return Header/Footer spacing selector definitions.
 *
 * @return array<int, array<string, string>>
 */
function mrn_base_stack_get_theme_header_footer_spacing_selector_definitions() {
	if ( function_exists( 'mrn_base_stack_get_row_spacing_side_selector_definitions' ) ) {
		return mrn_base_stack_get_row_spacing_side_selector_definitions();
	}

	if ( function_exists( 'mrn_base_stack_get_disabled_builder_row_spacing_side_selector_definitions' ) ) {
		return mrn_base_stack_get_disabled_builder_row_spacing_side_selector_definitions();
	}

	return array(
		array(
			'name'  => 'row_spacing_margin_top_preset',
			'label' => 'Margin Top',
			'scope' => 'margin-top',
		),
		array(
			'name'  => 'row_spacing_margin_right_preset',
			'label' => 'Margin Right',
			'scope' => 'margin-right',
		),
		array(
			'name'  => 'row_spacing_margin_bottom_preset',
			'label' => 'Margin Bottom',
			'scope' => 'margin-bottom',
		),
		array(
			'name'  => 'row_spacing_margin_left_preset',
			'label' => 'Margin Left',
			'scope' => 'margin-left',
		),
		array(
			'name'  => 'row_spacing_padding_top_preset',
			'label' => 'Padding Top',
			'scope' => 'padding-top',
		),
		array(
			'name'  => 'row_spacing_padding_right_preset',
			'label' => 'Padding Right',
			'scope' => 'padding-right',
		),
		array(
			'name'  => 'row_spacing_padding_bottom_preset',
			'label' => 'Padding Bottom',
			'scope' => 'padding-bottom',
		),
		array(
			'name'  => 'row_spacing_padding_left_preset',
			'label' => 'Padding Left',
			'scope' => 'padding-left',
		),
	);
}

/**
 * Return spacing preset choices for a Header/Footer spacing selector.
 *
 * @param string $scope Selector scope.
 * @return array<string, string>
 */
function mrn_base_stack_get_theme_header_footer_spacing_preset_choices( $scope = '' ) {
	if ( function_exists( 'mrn_base_stack_get_row_spacing_preset_choices' ) ) {
		return mrn_base_stack_get_row_spacing_preset_choices( $scope );
	}

	if ( function_exists( 'mrn_base_stack_get_disabled_builder_row_spacing_choices' ) ) {
		return mrn_base_stack_get_disabled_builder_row_spacing_choices( $scope );
	}

	return array( '' => __( 'Site Default', 'mrn-base-stack' ) );
}

/**
 * Convert a row-spacing selector name to a Header/Footer option field name.
 *
 * @param string $section Section key.
 * @param string $row_selector_name Row-spacing selector field name.
 * @return string
 */
function mrn_base_stack_get_theme_header_footer_spacing_option_name( $section, $row_selector_name ) {
	$section           = sanitize_key( (string) $section );
	$row_selector_name = sanitize_key( (string) $row_selector_name );

	return str_replace( 'row_spacing_', $section . '_spacing_', $row_selector_name );
}

/**
 * Return Header/Footer spacing option maps keyed by row-spacing selector name.
 *
 * @param string $section Section key.
 * @return array<string, string>
 */
function mrn_base_stack_get_theme_header_footer_spacing_option_map( $section ) {
	$map = array();

	foreach ( mrn_base_stack_get_theme_header_footer_spacing_selector_definitions() as $definition ) {
		$row_selector_name = isset( $definition['name'] ) ? sanitize_key( (string) $definition['name'] ) : '';
		if ( '' === $row_selector_name ) {
			continue;
		}

		$map[ $row_selector_name ] = mrn_base_stack_get_theme_header_footer_spacing_option_name( $section, $row_selector_name );
	}

	return $map;
}

/**
 * Return default Header/Footer spacing option values.
 *
 * @param string $section Section key.
 * @return array<string, string>
 */
function mrn_base_stack_get_default_theme_header_footer_spacing_options( $section ) {
	$defaults = array();

	foreach ( mrn_base_stack_get_theme_header_footer_spacing_option_map( $section ) as $option_name ) {
		$defaults[ $option_name ] = '';
	}

	return $defaults;
}

/**
 * Build Header/Footer spacing fields.
 *
 * @param string $section Section key.
 * @return array<int, array<string, mixed>>
 */
function mrn_base_stack_get_theme_header_footer_spacing_fields( $section ) {
	$section = sanitize_key( (string) $section );
	$fields  = array();

	foreach ( mrn_base_stack_get_theme_header_footer_spacing_selector_definitions() as $definition ) {
		$row_selector_name = isset( $definition['name'] ) ? sanitize_key( (string) $definition['name'] ) : '';
		$label             = isset( $definition['label'] ) ? sanitize_text_field( (string) $definition['label'] ) : '';
		$scope             = isset( $definition['scope'] ) ? sanitize_key( (string) $definition['scope'] ) : '';

		if ( '' === $row_selector_name || '' === $label || '' === $scope ) {
			continue;
		}

		$option_name = mrn_base_stack_get_theme_header_footer_spacing_option_name( $section, $row_selector_name );
		$fields[]    = array(
			'key'           => 'field_mrn_theme_' . $option_name,
			'label'         => $label,
			'name'          => $option_name,
			'type'          => 'select',
			'choices'       => mrn_base_stack_get_theme_header_footer_spacing_preset_choices( $scope ),
			'default_value' => '',
			'allow_null'    => 1,
			'ui'            => 1,
			'return_format' => 'value',
			'wrapper'       => array(
				'width' => '25',
			),
		);
	}

	return $fields;
}

/**
 * Return saved Header/Footer spacing options.
 *
 * @param string $section Section key.
 * @return array<string, string>
 */
function mrn_base_stack_get_theme_header_footer_spacing_options( $section ) {
	$options = mrn_base_stack_get_default_theme_header_footer_spacing_options( $section );

	if ( ! function_exists( 'get_field' ) ) {
		return $options;
	}

	foreach ( array_keys( $options ) as $option_name ) {
		$value = get_field( $option_name, 'option' );
		$value = is_scalar( $value ) ? trim( sanitize_text_field( (string) $value ) ) : '';

		$options[ $option_name ] = $value;
	}

	return $options;
}

/**
 * Convert saved Header/Footer spacing options into a row-spacing payload.
 *
 * @param string               $section Section key.
 * @param array<string, mixed> $options Header/Footer options.
 * @return array<string, string>
 */
function mrn_base_stack_get_theme_header_footer_spacing_row( $section, array $options ) {
	$row = array();

	foreach ( mrn_base_stack_get_theme_header_footer_spacing_option_map( $section ) as $row_selector_name => $option_name ) {
		$value = isset( $options[ $option_name ] ) && is_scalar( $options[ $option_name ] ) ? trim( (string) $options[ $option_name ] ) : '';

		$row[ $row_selector_name ] = $value;
	}

	return $row;
}

/**
 * Return the row-spacing contract for Header/Footer shell spacing.
 *
 * @param string               $section Section key.
 * @param array<string, mixed> $options Header/Footer options.
 * @return array{classes:array<int,string>,attributes:array<string,string>}
 */
function mrn_base_stack_get_theme_header_footer_spacing_contract( $section, array $options ) {
	if ( ! function_exists( 'mrn_base_stack_get_row_spacing_contract' ) ) {
		return array(
			'classes'    => array(),
			'attributes' => array(),
		);
	}

	return mrn_base_stack_get_row_spacing_contract( mrn_base_stack_get_theme_header_footer_spacing_row( $section, $options ) );
}

/**
 * Return Header/Footer appearance styles as an inline style value.
 *
 * @param string               $section Section key.
 * @param array<string, mixed> $options Header/Footer options.
 * @return string
 */
function mrn_base_stack_get_theme_header_footer_appearance_style( $section, array $options ) {
	$section = sanitize_key( (string) $section );
	$styles  = array();

	if ( ! in_array( $section, array( 'header', 'footer' ), true ) ) {
		return '';
	}

	$color_vars = array(
		'background_color' => '--mrn-theme-hf-bg-color',
		'font_color'       => '--mrn-theme-hf-font-color',
		'link_color'       => '--mrn-theme-hf-link-color',
		'link_hover_color' => '--mrn-theme-hf-link-hover-color',
	);

	foreach ( $color_vars as $suffix => $css_var ) {
		$option_name = $section . '_' . $suffix;
		$color_value = isset( $options[ $option_name ] ) ? mrn_base_stack_get_site_color_css_value( $options[ $option_name ] ) : '';

		if ( '' !== $color_value ) {
			$styles[] = $css_var . ': ' . $color_value;
		}
	}

	$font_stack = isset( $options[ $section . '_font_family' ] ) ? mrn_base_stack_get_theme_header_footer_font_stack( $options[ $section . '_font_family' ] ) : '';
	if ( '' !== $font_stack ) {
		$styles[] = '--mrn-theme-hf-font-family: ' . $font_stack;
	}

	if ( empty( $styles ) ) {
		return '';
	}

	return function_exists( 'mrn_base_stack_get_inline_style_attribute' )
		? mrn_base_stack_get_inline_style_attribute( $styles )
		: implode( '; ', array_values( array_filter( array_map( 'trim', $styles ), 'strlen' ) ) );
}

/**
 * Merge Header/Footer HTML attributes with special handling for style attributes.
 *
 * @param array<string, string> $attributes Existing attributes.
 * @param array<string, string> $extra_attributes Extra attributes.
 * @return array<string, string>
 */
function mrn_base_stack_merge_theme_header_footer_attributes( array $attributes, array $extra_attributes ) {
	if ( function_exists( 'mrn_base_stack_merge_builder_attributes' ) ) {
		return mrn_base_stack_merge_builder_attributes( $attributes, $extra_attributes );
	}

	foreach ( $extra_attributes as $attribute_name => $attribute_value ) {
		$attribute_name  = is_string( $attribute_name ) ? trim( $attribute_name ) : '';
		$attribute_value = is_scalar( $attribute_value ) ? trim( (string) $attribute_value ) : '';

		if ( '' === $attribute_name || '' === $attribute_value ) {
			continue;
		}

		if ( isset( $attributes[ $attribute_name ] ) && 'style' === strtolower( $attribute_name ) ) {
			$existing_style = is_scalar( $attributes[ $attribute_name ] ) ? trim( (string) $attributes[ $attribute_name ] ) : '';
			if ( '' !== $existing_style ) {
				$attribute_value = $existing_style . '; ' . $attribute_value;
			}
		}

		$attributes[ $attribute_name ] = $attribute_value;
	}

	return $attributes;
}

/**
 * Convert Header/Footer attributes into escaped HTML.
 *
 * @param array<string, string> $attributes Attribute map.
 * @return string
 */
function mrn_base_stack_get_theme_header_footer_html_attributes( array $attributes ) {
	if ( function_exists( 'mrn_base_stack_get_html_attributes' ) ) {
		return mrn_base_stack_get_html_attributes( $attributes );
	}

	$parts = array();
	foreach ( $attributes as $attribute_name => $attribute_value ) {
		$attribute_name  = is_string( $attribute_name ) ? trim( $attribute_name ) : '';
		$attribute_value = is_scalar( $attribute_value ) ? trim( (string) $attribute_value ) : '';

		if ( '' === $attribute_name || '' === $attribute_value ) {
			continue;
		}

		$parts[] = sprintf( '%s="%s"', esc_attr( $attribute_name ), esc_attr( $attribute_value ) );
	}

	return implode( ' ', $parts );
}

/**
 * Build front-end attributes for the Header/Footer layout grid shell.
 *
 * @param string               $section Section key.
 * @param array<string, mixed> $options Header/Footer options.
 * @param array<string, mixed> $layout  Layout grid contract.
 * @return array<string, string>
 */
function mrn_base_stack_get_theme_header_footer_shell_attributes( $section, array $options, array $layout ) {
	$attributes = array();
	$grid_style = mrn_base_stack_get_theme_header_footer_layout_grid_shell_style( $layout );
	$grid_style = is_string( $grid_style ) ? rtrim( trim( $grid_style ), ';' ) : '';
	if ( '' !== $grid_style ) {
		$attributes['style'] = $grid_style;
	}

	$appearance_style = mrn_base_stack_get_theme_header_footer_appearance_style( $section, $options );
	if ( '' !== $appearance_style ) {
		$attributes = mrn_base_stack_merge_theme_header_footer_attributes(
			$attributes,
			array( 'style' => $appearance_style )
		);
	}

	$spacing_contract = mrn_base_stack_get_theme_header_footer_spacing_contract( $section, $options );
	if ( ! empty( $spacing_contract['attributes'] ) && is_array( $spacing_contract['attributes'] ) ) {
		$attributes = mrn_base_stack_merge_theme_header_footer_attributes(
			$attributes,
			$spacing_contract['attributes']
		);
	}

	return $attributes;
}

/**
 * Return grouped Config field order for Header/Footer options.
 *
 * @param string $section Section key.
 * @return array<string, array{label:string, fields:array<int,string>}>
 */
function mrn_base_stack_get_theme_header_footer_config_field_groups( $section ) {
	$section = sanitize_key( (string) $section );

	if ( 'header' === $section ) {
		return array(
			'appearance' => array(
				'label'  => __( 'Appearance', 'mrn-base-stack' ),
				'fields' => array(
					'field_mrn_theme_header_background_color',
					'field_mrn_theme_header_font_color',
					'field_mrn_theme_header_link_color',
					'field_mrn_theme_header_link_hover_color',
					'field_mrn_theme_header_font_family',
				),
			),
			'navigation' => array(
				'label'  => __( 'Navigation', 'mrn-base-stack' ),
				'fields' => array(
					'field_mrn_theme_header_show_utility_menu',
					'field_mrn_theme_header_show_tertiary_menu',
					'field_mrn_theme_header_primary_nav_inherit_header_settings',
				),
			),
			'business' => array(
				'label'  => __( 'Business Info', 'mrn-base-stack' ),
				'fields' => array(
					'field_mrn_theme_header_show_business_profile',
					'field_mrn_theme_header_show_business_phone',
				),
			),
			'search' => array(
				'label'  => __( 'Search', 'mrn-base-stack' ),
				'fields' => array(
					'field_mrn_theme_header_show_search',
					'field_mrn_theme_header_searchwp_form_id',
					'field_mrn_theme_header_search_style',
					'field_mrn_theme_header_search_icon_source',
					'field_mrn_theme_header_search_standard_icon',
					'field_mrn_theme_header_search_fa_class',
					'field_mrn_theme_header_search_media_icon',
				),
			),
		);
	}

	if ( 'footer' === $section ) {
		return array(
			'appearance' => array(
				'label'  => __( 'Appearance', 'mrn-base-stack' ),
				'fields' => array(
					'field_mrn_theme_footer_background_color',
					'field_mrn_theme_footer_font_color',
					'field_mrn_theme_footer_link_color',
					'field_mrn_theme_footer_link_hover_color',
					'field_mrn_theme_footer_font_family',
				),
			),
			'navigation' => array(
				'label'  => __( 'Navigation', 'mrn-base-stack' ),
				'fields' => array(
					'field_mrn_theme_footer_show_footer_menu',
					'field_mrn_theme_footer_show_secondary_menu',
					'field_mrn_theme_footer_show_tertiary_menu',
					'field_mrn_theme_footer_show_privacy_center_links',
				),
			),
			'business'   => array(
				'label'  => __( 'Business Info', 'mrn-base-stack' ),
				'fields' => array(
					'field_mrn_theme_footer_show_business_profile',
					'field_mrn_theme_footer_show_business_phone',
					'field_mrn_theme_footer_show_text_phone',
					'field_mrn_theme_footer_show_address',
					'field_mrn_theme_footer_show_business_hours',
				),
			),
			'social'     => array(
				'label'  => __( 'Social', 'mrn-base-stack' ),
				'fields' => array(
					'field_mrn_theme_footer_show_social_menu',
					'field_mrn_theme_footer_social_icon_color',
					'field_mrn_theme_footer_social_icon_hover_color',
				),
			),
			'text'       => array(
				'label'  => __( 'Footer Text', 'mrn-base-stack' ),
				'fields' => array(
					'field_mrn_theme_footer_copyright_text',
					'field_mrn_theme_footer_legal_text',
				),
			),
		);
	}

	return array();
}

/**
 * Build a Config heading field.
 *
 * @param string $section Section key.
 * @param string $slug    Heading slug.
 * @param string $label   Heading label.
 * @return array<string, mixed>
 */
function mrn_base_stack_get_theme_header_footer_config_heading_field( $section, $slug, $label ) {
	$section = sanitize_key( (string) $section );
	$slug    = sanitize_key( (string) $slug );

	return array(
		'key'       => 'field_mrn_theme_' . $section . '_config_heading_' . $slug,
		'label'     => '',
		'name'      => '',
		'type'      => 'message',
		'message'   => '<h3 class="mrn-theme-hf-config-heading__title">' . esc_html( $label ) . '</h3>',
		'esc_html'  => 0,
		'new_lines' => '',
		'wrapper'   => array(
			'class' => 'mrn-theme-hf-config-heading',
		),
	);
}

/**
 * Apply Config field grouping and order without changing option names.
 *
 * @param string            $section Section key.
 * @param array<int, array> $fields  Raw section fields.
 * @return array<int, array>
 */
function mrn_base_stack_order_theme_header_footer_config_fields( $section, array $fields ) {
	$groups        = mrn_base_stack_get_theme_header_footer_config_field_groups( $section );
	$fields_by_key = array();
	$used_keys     = array();
	$ordered       = array();

	foreach ( $fields as $field ) {
		if ( ! is_array( $field ) || empty( $field['key'] ) ) {
			continue;
		}

		$fields_by_key[ (string) $field['key'] ] = $field;
	}

	foreach ( $groups as $group_slug => $group ) {
		$group_fields = array();
		$field_keys   = isset( $group['fields'] ) && is_array( $group['fields'] ) ? $group['fields'] : array();

		foreach ( $field_keys as $field_key ) {
			if ( ! isset( $fields_by_key[ $field_key ] ) ) {
				continue;
			}

			$group_fields[]          = $fields_by_key[ $field_key ];
			$used_keys[ $field_key ] = true;
		}

		if ( empty( $group_fields ) ) {
			continue;
		}

		$ordered[] = mrn_base_stack_get_theme_header_footer_config_heading_field(
			$section,
			$group_slug,
			isset( $group['label'] ) ? (string) $group['label'] : ''
		);
		$ordered   = array_merge( $ordered, $group_fields );
	}

	foreach ( $fields as $field ) {
		if ( ! is_array( $field ) || empty( $field['key'] ) || isset( $used_keys[ (string) $field['key'] ] ) ) {
			continue;
		}

		$ordered[] = $field;
	}

	return $ordered;
}

/**
 * Reorder Header/Footer Config fields while preserving top-level ACF tabs.
 *
 * @param array<int, array> $fields Raw field group fields.
 * @return array<int, array>
 */
function mrn_base_stack_reorder_theme_header_footer_config_fields( array $fields ) {
	$ordered        = array();
	$section        = '';
	$section_fields = array();

	$flush_section = static function () use ( &$ordered, &$section, &$section_fields ) {
		if ( '' !== $section ) {
			$ordered = array_merge( $ordered, mrn_base_stack_order_theme_header_footer_config_fields( $section, $section_fields ) );
		}

		$section        = '';
		$section_fields = array();
	};

	foreach ( $fields as $field ) {
		if ( ! is_array( $field ) ) {
			continue;
		}

		$field_key = isset( $field['key'] ) ? (string) $field['key'] : '';

		if ( 'field_mrn_theme_header_tab' === $field_key || 'field_mrn_theme_footer_tab' === $field_key ) {
			$flush_section();
			$ordered[] = $field;
			$section   = 'field_mrn_theme_header_tab' === $field_key ? 'header' : 'footer';
			continue;
		}

		if ( '' !== $section ) {
			$section_fields[] = $field;
			continue;
		}

		$ordered[] = $field;
	}

	$flush_section();

	return $ordered;
}

/**
 * Apply admin layout hints to Header/Footer Config fields.
 *
 * @param array<string, mixed> $field Field config.
 * @return array<string, mixed>
 */
function mrn_base_stack_prepare_theme_header_footer_config_field( array $field ) {
	$field_type = isset( $field['type'] ) ? sanitize_key( (string) $field['type'] ) : '';

	if ( ! isset( $field['wrapper'] ) || ! is_array( $field['wrapper'] ) ) {
		$field['wrapper'] = array();
	}

	if ( 'message' === $field_type && ! empty( $field['wrapper']['class'] ) && false !== strpos( (string) $field['wrapper']['class'], 'mrn-theme-hf-config-heading' ) ) {
		$field['wrapper']['width'] = '100';
		return $field;
	}

	if ( 'true_false' === $field_type ) {
		$field['wrapper']['width'] = '50';
		$field                     = mrn_base_stack_append_field_wrapper_class( $field, 'mrn-theme-hf-config-toggle' );
	}

	if ( in_array( $field_type, array( 'select', 'button_group', 'image' ), true ) ) {
		$field['wrapper']['width'] = '50';
		$field                     = mrn_base_stack_append_field_wrapper_class( $field, 'mrn-theme-hf-config-detail' );
	}

	if ( in_array( $field_type, array( 'text', 'textarea' ), true ) ) {
		$field['wrapper']['width'] = '100';
		$field                     = mrn_base_stack_append_field_wrapper_class( $field, 'mrn-theme-hf-config-detail' );
	}

	return $field;
}

/**
 * Return the universal sub-tab contract for Theme Header/Footer options.
 *
 * @return array<string, mixed>
 */
function mrn_base_stack_get_theme_header_footer_subtab_contract() {
	return array(
		'default'    => 'configs',
		'tabs'       => array(
			'configs' => __( 'Configs', 'mrn-base-stack' ),
			'effects' => __( 'Effects', 'mrn-base-stack' ),
			'layout'  => __( 'Layout', 'mrn-base-stack' ),
			'spacing' => __( 'Spacing', 'mrn-base-stack' ),
		),
		'appearance' => array(
			'tab_set_gap_px'      => 10,
			'panel_gap_px'        => 12,
			'tab_border_width_px' => 1,
			'tab_border_color'    => '#c3c4c7',
		),
	);
}

/**
 * Resolve normalized appearance settings for Header/Footer tab contracts.
 *
 * @return array<string, int|string>
 */
function mrn_base_stack_get_theme_header_footer_subtab_appearance() {
	$contract   = mrn_base_stack_get_theme_header_footer_subtab_contract();
	$appearance = isset( $contract['appearance'] ) && is_array( $contract['appearance'] ) ? $contract['appearance'] : array();

	$tab_set_gap_px = isset( $appearance['tab_set_gap_px'] ) ? max( 0, absint( $appearance['tab_set_gap_px'] ) ) : 10;
	$panel_gap_px   = isset( $appearance['panel_gap_px'] ) ? max( 0, absint( $appearance['panel_gap_px'] ) ) : 12;
	$tab_border_px  = isset( $appearance['tab_border_width_px'] ) ? max( 1, absint( $appearance['tab_border_width_px'] ) ) : 1;
	$tab_border_hex = isset( $appearance['tab_border_color'] ) ? sanitize_hex_color( (string) $appearance['tab_border_color'] ) : '';

	if ( '' === $tab_border_hex ) {
		$tab_border_hex = '#c3c4c7';
	}

	return array(
		'tab_set_gap_px'      => $tab_set_gap_px,
		'panel_gap_px'        => $panel_gap_px,
		'tab_border_width_px' => $tab_border_px,
		'tab_border_color'    => $tab_border_hex,
	);
}

/**
 * Return layout-grid item labels for a Header/Footer section.
 *
 * @param string $section Section key.
 * @return array<string, string>
 */
function mrn_base_stack_get_theme_header_footer_layout_items( $section ) {
	$section = sanitize_key( (string) $section );

	if ( 'header' === $section ) {
		return array(
			'secondary_menu'        => __( 'Secondary Menu', 'mrn-base-stack' ),
			'header_brand'          => __( 'Header', 'mrn-base-stack' ),
			'header_tertiary_menu'  => __( 'Header Tertiary Menu', 'mrn-base-stack' ),
			'business_profile'      => __( 'Business Profile', 'mrn-base-stack' ),
			'business_phone'        => __( 'Business Phone', 'mrn-base-stack' ),
			'search'                => __( 'Search', 'mrn-base-stack' ),
		);
	}

	if ( 'footer' === $section ) {
		return array(
			'footer_brand'          => __( 'Footer', 'mrn-base-stack' ),
			'business_profile'      => __( 'Business Profile', 'mrn-base-stack' ),
			'business_phone'        => __( 'Business Phone', 'mrn-base-stack' ),
			'text_phone'            => __( 'Text / SMS / RCS', 'mrn-base-stack' ),
			'address'               => __( 'Address', 'mrn-base-stack' ),
			'business_hours'        => __( 'Business Hours', 'mrn-base-stack' ),
			'footer_primary_menu'   => __( 'Footer Primary Menu', 'mrn-base-stack' ),
			'footer_secondary_menu' => __( 'Footer Secondary Menu', 'mrn-base-stack' ),
			'footer_tertiary_menu'  => __( 'Footer Tertiary Menu', 'mrn-base-stack' ),
			'social_media'          => __( 'Social Media', 'mrn-base-stack' ),
			'privacy_center_links'  => __( 'Privacy Center Links', 'mrn-base-stack' ),
			'copyright'             => __( 'Copyright', 'mrn-base-stack' ),
		);
	}

	return array();
}

/**
 * Return layout-grid items controlled by Header/Footer config toggles.
 *
 * Items omitted from this map are structural and stay available whenever their
 * section exists.
 *
 * @param string $section Section key.
 * @return array<string, string>
 */
function mrn_base_stack_get_theme_header_footer_layout_item_toggle_fields( $section ) {
	$section = sanitize_key( (string) $section );

	if ( 'header' === $section ) {
		return array(
			'secondary_menu'       => 'header_show_utility_menu',
			'header_tertiary_menu' => 'header_show_tertiary_menu',
			'business_profile'     => 'header_show_business_profile',
			'business_phone'       => 'header_show_business_phone',
			'search'               => 'header_show_search',
		);
	}

	if ( 'footer' === $section ) {
		return array(
			'business_profile'      => 'footer_show_business_profile',
			'business_phone'        => 'footer_show_business_phone',
			'text_phone'            => 'footer_show_text_phone',
			'address'               => 'footer_show_address',
			'business_hours'        => 'footer_show_business_hours',
			'footer_primary_menu'   => 'footer_show_footer_menu',
			'footer_secondary_menu' => 'footer_show_secondary_menu',
			'footer_tertiary_menu'  => 'footer_show_tertiary_menu',
			'social_media'          => 'footer_show_social_menu',
			'privacy_center_links'  => 'footer_show_privacy_center_links',
		);
	}

	return array();
}

/**
 * Determine whether a layout-grid item is enabled by current config values.
 *
 * @param string              $section  Section key.
 * @param string              $item_key Layout item key.
 * @param array<string,mixed> $options Optional normalized options.
 * @return bool
 */
function mrn_base_stack_is_theme_header_footer_layout_item_enabled( $section, $item_key, $options = null ) {
	$section       = sanitize_key( (string) $section );
	$item_key      = sanitize_key( (string) $item_key );
	$toggle_fields = mrn_base_stack_get_theme_header_footer_layout_item_toggle_fields( $section );

	if ( empty( $toggle_fields[ $item_key ] ) ) {
		return true;
	}

	$field_name = $toggle_fields[ $item_key ];
	$field_key  = 'field_mrn_theme_' . $field_name;

	if ( isset( $_POST['acf'] ) && is_array( $_POST['acf'] ) && array_key_exists( $field_key, $_POST['acf'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$value = sanitize_text_field( wp_unslash( $_POST['acf'][ $field_key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		return ! empty( $value ) && '0' !== (string) $value;
	}

	if ( is_array( $options ) && array_key_exists( $field_name, $options ) ) {
		return ! empty( $options[ $field_name ] );
	}

	if ( function_exists( 'get_field' ) ) {
		return (bool) get_field( $field_name, 'option' );
	}

	return false;
}

/**
 * Return layout-grid item labels filtered to enabled config components.
 *
 * @param string              $section Section key.
 * @param array<string,mixed> $options Optional normalized options.
 * @return array<string, string>
 */
function mrn_base_stack_get_theme_header_footer_active_layout_items( $section, $options = null ) {
	$items  = mrn_base_stack_get_theme_header_footer_layout_items( $section );
	$active = array();

	foreach ( $items as $item_key => $item_label ) {
		if ( mrn_base_stack_is_theme_header_footer_layout_item_enabled( $section, $item_key, $options ) ) {
			$active[ $item_key ] = $item_label;
		}
	}

	return $active;
}

/**
 * Return the default grid contract for a Header/Footer section.
 *
 * @param string $section Section key.
 * @return array<string, mixed>
 */
function mrn_base_stack_get_default_theme_header_footer_layout_grid( $section ) {
	$section = sanitize_key( (string) $section );

	if ( 'header' === $section ) {
		return array(
			'columns' => 3,
			'rows'    => 4,
			'items'   => array(
				'secondary_menu' => array(
					'row'         => 1,
					'column'      => 1,
					'columnSpan'  => 3,
				),
				'header_brand' => array(
					'row'        => 2,
					'column'     => 1,
					'columnSpan' => 1,
				),
				'business_profile' => array(
					'row'        => 2,
					'column'     => 2,
					'columnSpan' => 1,
				),
				'business_phone' => array(
					'row'        => 2,
					'column'     => 3,
					'columnSpan' => 1,
				),
				'search'               => array(
					'row'        => 3,
					'column'     => 3,
					'columnSpan' => 1,
				),
				'header_tertiary_menu' => array(
					'row'        => 4,
					'column'     => 1,
					'columnSpan' => 3,
				),
			),
		);
	}

	if ( 'footer' === $section ) {
		return array(
			'columns' => 3,
			'rows'    => 5,
			'items'   => array(
				'footer_brand'          => array(
					'row'        => 1,
					'column'     => 1,
					'columnSpan' => 1,
				),
				'business_profile' => array(
					'row'        => 1,
					'column'     => 2,
					'columnSpan' => 1,
				),
				'business_phone' => array(
					'row'        => 1,
					'column'     => 3,
					'columnSpan' => 1,
				),
				'text_phone' => array(
					'row'        => 2,
					'column'     => 1,
					'columnSpan' => 1,
				),
				'address' => array(
					'row'        => 2,
					'column'     => 2,
					'columnSpan' => 1,
				),
				'business_hours' => array(
					'row'        => 2,
					'column'     => 3,
					'columnSpan' => 1,
				),
				'footer_primary_menu' => array(
					'row'        => 3,
					'column'     => 1,
					'columnSpan' => 1,
				),
				'footer_secondary_menu' => array(
					'row'        => 3,
					'column'     => 2,
					'columnSpan' => 1,
				),
				'footer_tertiary_menu' => array(
					'row'        => 4,
					'column'     => 1,
					'columnSpan' => 1,
				),
				'social_media' => array(
					'row'        => 4,
					'column'     => 2,
					'columnSpan' => 1,
				),
				'privacy_center_links' => array(
					'row'        => 4,
					'column'     => 3,
					'columnSpan' => 1,
				),
				'copyright' => array(
					'row'        => 5,
					'column'     => 1,
					'columnSpan' => 3,
				),
			),
		);
	}

	return array(
		'columns' => 1,
		'rows'    => 1,
		'items'   => array(),
	);
}

/**
 * Normalize a Header/Footer grid layout contract.
 *
 * @param mixed                     $layout  Raw layout data.
 * @param string                    $section Section key.
 * @param array<string, mixed>|null $items Optional layout items.
 * @return array<string, mixed>
 */
function mrn_base_stack_normalize_theme_header_footer_layout_grid( $layout, $section, ?array $items = null ) {
	$section = sanitize_key( (string) $section );
	if ( is_string( $layout ) && '' !== trim( $layout ) ) {
		$decoded = json_decode( $layout, true );
		$layout  = is_array( $decoded ) ? $decoded : array();
	}

	if ( ! is_array( $layout ) ) {
		$layout = array();
	}

	$default       = mrn_base_stack_get_default_theme_header_footer_layout_grid( $section );
	$items         = null !== $items ? $items : mrn_base_stack_get_theme_header_footer_layout_items( $section );
	$raw_items     = isset( $layout['items'] ) && is_array( $layout['items'] ) ? $layout['items'] : array();
	$default_items = isset( $default['items'] ) && is_array( $default['items'] ) ? $default['items'] : array();
	$columns       = isset( $layout['columns'] ) ? absint( $layout['columns'] ) : absint( $default['columns'] );
	$rows          = isset( $layout['rows'] ) ? absint( $layout['rows'] ) : absint( $default['rows'] );

	foreach ( $default_items as $item_key => $item_default ) {
		if ( isset( $raw_items[ $item_key ] ) || ! is_array( $item_default ) || empty( $item_default['row'] ) ) {
			continue;
		}

		$rows = max( $rows, absint( $item_default['row'] ) );
	}

	$columns = min( 6, max( 1, $columns ) );
	$rows    = min( 12, max( 1, $rows ) );

	$normalized_items = array();

	foreach ( $items as $item_key => $item_label ) {
		$item_default = isset( $default_items[ $item_key ] ) && is_array( $default_items[ $item_key ] ) ? $default_items[ $item_key ] : array();
		$item         = isset( $raw_items[ $item_key ] ) && is_array( $raw_items[ $item_key ] ) ? $raw_items[ $item_key ] : $item_default;
		$row          = isset( $item['row'] ) ? absint( $item['row'] ) : absint( isset( $item_default['row'] ) ? $item_default['row'] : 1 );
		$column       = isset( $item['column'] ) ? absint( $item['column'] ) : absint( isset( $item_default['column'] ) ? $item_default['column'] : 1 );
		$column_span  = isset( $item['columnSpan'] ) ? absint( $item['columnSpan'] ) : absint( isset( $item_default['columnSpan'] ) ? $item_default['columnSpan'] : 1 );

		$row         = min( $rows, max( 1, $row ) );
		$column_span = min( $columns, max( 1, $column_span ) );
		$column      = min( max( 1, $columns - $column_span + 1 ), max( 1, $column ) );

		$normalized_items[ $item_key ] = array(
			'row'        => $row,
			'column'     => $column,
			'columnSpan' => $column_span,
		);
	}

	return array(
		'columns' => $columns,
		'rows'    => $rows,
		'items'   => $normalized_items,
	);
}

/**
 * Return saved Header/Footer grid layout for theme consumers.
 *
 * @param string $section Section key.
 * @return array<string, mixed>
 */
function mrn_base_stack_get_theme_header_footer_layout_grid( $section ) {
	$section = sanitize_key( (string) $section );
	$field   = $section . '_layout_grid';
	$value   = function_exists( 'get_field' ) ? get_field( $field, 'option' ) : array();
	$items   = mrn_base_stack_get_theme_header_footer_active_layout_items( $section );

	return mrn_base_stack_normalize_theme_header_footer_layout_grid( $value, $section, $items );
}

/**
 * Return inline CSS variables for a Header/Footer layout grid shell.
 *
 * @param array<string, mixed> $layout Normalized layout data.
 * @return string
 */
function mrn_base_stack_get_theme_header_footer_layout_grid_shell_style( array $layout ) {
	$columns = isset( $layout['columns'] ) ? min( 6, max( 1, absint( $layout['columns'] ) ) ) : 1;

	return '--mrn-hf-layout-columns: repeat(' . $columns . ', minmax(0, 1fr));';
}

/**
 * Return inline grid placement CSS for one Header/Footer layout item.
 *
 * @param array<string, mixed> $layout   Normalized layout data.
 * @param string               $item_key Layout item key.
 * @return string
 */
function mrn_base_stack_get_theme_header_footer_layout_grid_item_style( array $layout, $item_key ) {
	$item_key = sanitize_key( (string) $item_key );
	$items    = isset( $layout['items'] ) && is_array( $layout['items'] ) ? $layout['items'] : array();

	if ( ! isset( $items[ $item_key ] ) || ! is_array( $items[ $item_key ] ) ) {
		return '';
	}

	$item        = $items[ $item_key ];
	$columns     = isset( $layout['columns'] ) ? min( 6, max( 1, absint( $layout['columns'] ) ) ) : 1;
	$row         = isset( $item['row'] ) ? min( 12, max( 1, absint( $item['row'] ) ) ) : 1;
	$column_span = isset( $item['columnSpan'] ) ? min( $columns, max( 1, absint( $item['columnSpan'] ) ) ) : 1;
	$column      = isset( $item['column'] ) ? min( max( 1, $columns - $column_span + 1 ), max( 1, absint( $item['column'] ) ) ) : 1;
	$style       = 'grid-column: ' . $column . ' / span ' . $column_span . '; grid-row: ' . $row . ';';

	if ( $column > 1 && $column + $column_span - 1 >= $columns ) {
		$style .= ' --mrn-hf-item-inline-align: flex-end; --mrn-hf-item-text-align: right;';
	}

	return $style;
}

/**
 * Return serialized default layout JSON for an ACF field.
 *
 * @param string $section Section key.
 * @return string
 */
function mrn_base_stack_get_default_theme_header_footer_layout_grid_json( $section ) {
	$json = wp_json_encode( mrn_base_stack_get_default_theme_header_footer_layout_grid( $section ) );

	return is_string( $json ) ? $json : '{}';
}

/**
 * Build the Header/Footer layout editor markup.
 *
 * @param string $section Section key.
 * @return string
 */
function mrn_base_stack_get_theme_header_footer_layout_editor_markup( $section ) {
	$section = sanitize_key( (string) $section );
	$items   = mrn_base_stack_get_theme_header_footer_layout_items( $section );

	if ( empty( $items ) ) {
		return '';
	}

	$section_label  = 'header' === $section ? __( 'Header', 'mrn-base-stack' ) : __( 'Footer', 'mrn-base-stack' );
	$default_layout = mrn_base_stack_get_default_theme_header_footer_layout_grid( $section );
	$toggle_fields  = mrn_base_stack_get_theme_header_footer_layout_item_toggle_fields( $section );
	$items_json     = wp_json_encode( $items );
	$layout_json    = wp_json_encode( $default_layout );
	$toggles_json   = wp_json_encode( $toggle_fields );

	if ( ! is_string( $items_json ) ) {
		$items_json = '{}';
	}

	if ( ! is_string( $layout_json ) ) {
		$layout_json = '{}';
	}

	if ( ! is_string( $toggles_json ) ) {
		$toggles_json = '{}';
	}

	ob_start();
	?>
	<div
		class="mrn-theme-hf-layout-grid-editor"
		data-mrn-theme-hf-layout-editor
		data-section="<?php echo esc_attr( $section ); ?>"
		data-storage-name="<?php echo esc_attr( $section . '_layout_grid' ); ?>"
		data-items="<?php echo esc_attr( $items_json ); ?>"
		data-toggle-fields="<?php echo esc_attr( $toggles_json ); ?>"
		data-default-layout="<?php echo esc_attr( $layout_json ); ?>"
	>
		<div class="mrn-theme-hf-layout-grid-editor__toolbar">
			<h3 class="mrn-theme-hf-layout-grid-editor__title"><?php echo esc_html( $section_label ); ?> <?php esc_html_e( 'Grid', 'mrn-base-stack' ); ?></h3>
			<label>
				<span><?php esc_html_e( 'Columns', 'mrn-base-stack' ); ?></span>
				<input type="number" min="1" max="6" step="1" data-mrn-layout-columns>
			</label>
			<label>
				<span><?php esc_html_e( 'Rows', 'mrn-base-stack' ); ?></span>
				<input type="number" min="1" max="12" step="1" data-mrn-layout-rows>
			</label>
			<button type="button" class="button" data-mrn-layout-reset><?php esc_html_e( 'Reset', 'mrn-base-stack' ); ?></button>
		</div>
		<div class="mrn-theme-hf-layout-grid-editor__selected" data-mrn-layout-selected aria-live="polite"></div>
		<div class="mrn-theme-hf-layout-grid-editor__canvas" data-mrn-layout-canvas></div>
	</div>
	<?php

	return trim( (string) ob_get_clean() );
}

/**
 * Sanitize saved Header/Footer layout-grid fields.
 *
 * @param mixed $value   Submitted field value.
 * @param mixed $post_id ACF post ID.
 * @param array $field   ACF field definition.
 * @return string
 */
function mrn_base_stack_sanitize_theme_header_footer_layout_grid_value( $value, $post_id, $field ) {
	unset( $post_id );

	$field_name = isset( $field['name'] ) ? sanitize_key( (string) $field['name'] ) : '';
	$section    = 0 === strpos( $field_name, 'footer_' ) ? 'footer' : 'header';
	$items      = mrn_base_stack_get_theme_header_footer_layout_items( $section );
	$layout     = mrn_base_stack_normalize_theme_header_footer_layout_grid( $value, $section, $items );
	$json       = wp_json_encode( $layout );

	return is_string( $json ) ? $json : mrn_base_stack_get_default_theme_header_footer_layout_grid_json( $section );
}
add_filter( 'acf/update_value/name=header_layout_grid', 'mrn_base_stack_sanitize_theme_header_footer_layout_grid_value', 10, 3 );
add_filter( 'acf/update_value/name=footer_layout_grid', 'mrn_base_stack_sanitize_theme_header_footer_layout_grid_value', 10, 3 );

/**
 * Persist layout-grid JSON posted by the custom Header/Footer grid editor.
 *
 * The grid editor mirrors its state into a dedicated request key because the
 * visual editor lives beside ACF message fields and the storage textarea is
 * intentionally hidden from authors.
 *
 * @param mixed  $post_id   ACF options page post id.
 * @param string $menu_slug ACF options page slug.
 * @return void
 */
function mrn_base_stack_save_theme_header_footer_layout_grid_request( $post_id, $menu_slug ) {
	unset( $post_id );

	if ( 'mrn-theme-header-footer' !== $menu_slug || empty( $_POST['mrn_theme_hf_layout_grid'] ) || ! is_array( $_POST['mrn_theme_hf_layout_grid'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		return;
	}

	if ( ! current_user_can( 'edit_theme_options' ) ) {
		return;
	}

	$request = map_deep( wp_unslash( $_POST['mrn_theme_hf_layout_grid'] ), 'sanitize_textarea_field' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	foreach ( array( 'header', 'footer' ) as $section ) {
		if ( ! isset( $request[ $section ] ) || ! is_string( $request[ $section ] ) ) {
			continue;
		}

		$items  = mrn_base_stack_get_theme_header_footer_layout_items( $section );
		$layout = mrn_base_stack_normalize_theme_header_footer_layout_grid( $request[ $section ], $section, $items );
		$json   = wp_json_encode( $layout );
		if ( ! is_string( $json ) ) {
			continue;
		}

		$field_name = $section . '_layout_grid';
		$field_key  = 'field_mrn_theme_' . $field_name;

		if ( function_exists( 'update_field' ) ) {
			update_field( $field_key, $json, 'option' );
			continue;
		}

		update_option( 'options_' . $field_name, $json, false );
		update_option( '_options_' . $field_name, $field_key, false );
	}
}
add_action( 'acf/options_page/save', 'mrn_base_stack_save_theme_header_footer_layout_grid_request', 20, 2 );

/**
 * Build section-specific sub-tab fields for Theme Header/Footer options.
 *
 * @param string $section Section key.
 * @return array<int, array<string, mixed>>
 */
function mrn_base_stack_get_theme_header_footer_subtab_fields( $section ) {
	$section = sanitize_key( (string) $section );

	if ( ! in_array( $section, array( 'header', 'footer' ), true ) ) {
		return array();
	}

	$section_label = 'header' === $section ? __( 'Header', 'mrn-base-stack' ) : __( 'Footer', 'mrn-base-stack' );
	$contract      = mrn_base_stack_get_theme_header_footer_subtab_contract();
	$tabs          = isset( $contract['tabs'] ) && is_array( $contract['tabs'] ) ? $contract['tabs'] : array();
	$default_tab   = isset( $contract['default'] ) ? sanitize_key( (string) $contract['default'] ) : 'configs';
	$buttons_html  = '';

	if ( empty( $tabs ) ) {
		return array();
	}

	if ( ! isset( $tabs[ $default_tab ] ) ) {
		$first_key = array_key_first( $tabs );
		if ( is_string( $first_key ) && '' !== $first_key ) {
			$default_tab = $first_key;
		}
	}

	foreach ( $tabs as $tab_key => $tab_label ) {
		$tab_key    = sanitize_key( (string) $tab_key );
		$is_default = $default_tab === $tab_key;
		$classes    = 'nav-tab';
		$tab_href   = '#mrn-theme-hf-' . $section . '-' . $tab_key;
		if ( $is_default ) {
			$classes .= ' nav-tab-active';
		}

		$buttons_html .= sprintf(
			'<a href="%1$s" class="%2$s" data-mrn-theme-hf-subtab="%3$s" role="tab" aria-selected="%4$s" tabindex="%5$s">%6$s</a>',
			esc_url( $tab_href ),
			esc_attr( $classes ),
			esc_attr( $tab_key ),
			$is_default ? 'true' : 'false',
			$is_default ? '0' : '-1',
			esc_html( $tab_label )
		);
	}

	$subtab_nav = array(
		'key'       => 'field_mrn_theme_' . $section . '_subtabs_nav',
		'label'     => '',
		'name'      => '',
		'type'      => 'message',
		'message'   => sprintf(
			'<div class="mrn-theme-hf-subtabs" data-mrn-theme-hf-section="%1$s" data-mrn-theme-hf-default="%4$s"><nav class="nav-tab-wrapper wp-clearfix" role="tablist" aria-label="%2$s">%3$s</nav></div>',
			esc_attr( $section ),
			/* translators: %s: Header or Footer section label. */
			esc_attr( sprintf( __( '%s option sub-tabs', 'mrn-base-stack' ), $section_label ) ),
			$buttons_html,
			esc_attr( $default_tab )
		),
		'esc_html'  => 0,
		'new_lines' => 'br',
	);

	$layout_storage = array(
		'key'           => 'field_mrn_theme_' . $section . '_layout_grid',
		'label'         => '',
		'name'          => $section . '_layout_grid',
		'type'          => 'textarea',
		'default_value' => mrn_base_stack_get_default_theme_header_footer_layout_grid_json( $section ),
		'rows'          => 3,
		'new_lines'     => '',
	);

	$content_width  = mrn_base_stack_get_theme_header_footer_content_width_field( $section );
	$spacing_fields = mrn_base_stack_get_theme_header_footer_spacing_fields( $section );

	$layout_editor = array(
		'key'       => 'field_mrn_theme_' . $section . '_layout_grid_editor',
		'label'     => __( 'Layout', 'mrn-base-stack' ),
		'name'      => '',
		'type'      => 'message',
		'message'   => mrn_base_stack_get_theme_header_footer_layout_editor_markup( $section ),
		'esc_html'  => 0,
		'new_lines' => '',
	);

	$effects_placeholder = array(
		'key'       => 'field_mrn_theme_' . $section . '_effects_placeholder',
		'label'     => __( 'Effects', 'mrn-base-stack' ),
		'name'      => '',
		'type'      => 'message',
		'message'   => __( 'Effects controls will be added in a follow-up update.', 'mrn-base-stack' ),
		'esc_html'  => 1,
		'new_lines' => 'br',
	);

	$subtab_nav          = mrn_base_stack_append_field_wrapper_class( $subtab_nav, 'mrn-theme-hf-subtabs-nav' );
	$layout_storage      = mrn_base_stack_append_field_wrapper_class(
		$layout_storage,
		'mrn-theme-hf-layout-grid-storage-field'
	);
	$content_width       = mrn_base_stack_append_field_wrapper_class(
		$content_width,
		mrn_base_stack_get_theme_header_footer_subtab_panel_class( $section, 'layout' ) . ' mrn-theme-hf-layout-width-field'
	);
	$layout_editor       = mrn_base_stack_append_field_wrapper_class(
		$layout_editor,
		mrn_base_stack_get_theme_header_footer_subtab_panel_class( $section, 'layout' ) . ' mrn-theme-hf-layout-grid-editor-field'
	);
	$effects_placeholder = mrn_base_stack_append_field_wrapper_class(
		$effects_placeholder,
		mrn_base_stack_get_theme_header_footer_subtab_panel_class( $section, 'effects' )
	);
	foreach ( $spacing_fields as $spacing_index => $spacing_field ) {
		$spacing_fields[ $spacing_index ] = mrn_base_stack_append_field_wrapper_class(
			$spacing_field,
			mrn_base_stack_get_theme_header_footer_subtab_panel_class( $section, 'spacing' ) . ' mrn-theme-hf-spacing-field'
		);
	}

	return array_merge(
		array(
			$subtab_nav,
			$layout_storage,
			$content_width,
			$layout_editor,
		),
		$spacing_fields,
		array(
			$effects_placeholder,
		)
	);
}

/**
 * Apply Header/Footer sub-tab panel classes to options fields.
 *
 * @param array<int, mixed> $fields Field definitions.
 * @return array<int, mixed>
 */
function mrn_base_stack_prepare_theme_header_footer_subtab_fields( array $fields ) {
	$fields          = mrn_base_stack_reorder_theme_header_footer_config_fields( $fields );
	$prepared        = array();
	$current_section = '';

	foreach ( $fields as $field ) {
		if ( ! is_array( $field ) ) {
			continue;
		}

		$field_key  = isset( $field['key'] ) ? (string) $field['key'] : '';
		$field_type = isset( $field['type'] ) ? sanitize_key( (string) $field['type'] ) : '';

		if ( 'field_mrn_theme_header_tab' === $field_key ) {
			$current_section = 'header';
			$prepared[]      = $field;
			$prepared        = array_merge( $prepared, mrn_base_stack_get_theme_header_footer_subtab_fields( $current_section ) );
			continue;
		}

		if ( 'field_mrn_theme_footer_tab' === $field_key ) {
			$current_section = 'footer';
			$prepared[]      = $field;
			$prepared        = array_merge( $prepared, mrn_base_stack_get_theme_header_footer_subtab_fields( $current_section ) );
			continue;
		}

		if ( '' !== $current_section && 'tab' !== $field_type ) {
			$field = mrn_base_stack_prepare_theme_header_footer_config_field( $field );
			$field = mrn_base_stack_append_field_wrapper_class(
				$field,
				mrn_base_stack_get_theme_header_footer_subtab_panel_class( $current_section, 'configs' )
			);
		}

		$prepared[] = $field;
	}

	return $prepared;
}

/**
 * Return Site Styles color choices for theme-owned controls.
 *
 * @return array<string, string>
 */
function mrn_base_stack_get_site_color_choices() {
	$choices = array();

	foreach ( mrn_site_colors_get_all() as $row ) {
		$slug  = isset( $row['slug'] ) ? (string) $row['slug'] : '';
		$name  = isset( $row['name'] ) ? (string) $row['name'] : '';
		$value = isset( $row['value'] ) ? (string) $row['value'] : '';

		if ( '' === $slug || '' === $name ) {
			continue;
		}

		$choices[ $slug ] = '' !== $value ? $name . ' (' . $value . ')' : $name;
	}

	return $choices;
}

/**
 * Return the first configured Site Styles color slug.
 *
 * @return string
 */
function mrn_base_stack_get_default_site_color_slug() {
	$choices = mrn_base_stack_get_site_color_choices();
	$slug    = array_key_first( $choices );

	return is_string( $slug ) ? $slug : '';
}

/**
 * Normalize a Site Styles color slug for theme option usage.
 *
 * @param mixed $slug Raw color slug.
 * @return string
 */
function mrn_base_stack_normalize_site_color_slug( $slug ) {
	$slug = is_scalar( $slug ) ? (string) $slug : '';
	$slug = mrn_site_colors_normalize_slug( $slug );

	if ( '' === $slug ) {
		return '';
	}

	$choices = mrn_base_stack_get_site_color_choices();

	return isset( $choices[ $slug ] ) ? $slug : '';
}

/**
 * Return a CSS-safe value for a configured Site Styles color.
 *
 * @param mixed $slug Raw color slug.
 * @return string
 */
function mrn_base_stack_get_site_color_css_value( $slug ) {
	$slug = mrn_base_stack_normalize_site_color_slug( $slug );

	if ( '' === $slug ) {
		return '';
	}

	return 'var(' . mrn_site_colors_get_css_var( $slug ) . ')';
}

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
			'fields'                => mrn_base_stack_prepare_theme_header_footer_subtab_fields(
				array(
					array(
						'key'       => 'field_mrn_theme_header_tab',
						'label'     => __( 'Header', 'mrn-base-stack' ),
						'name'      => '',
						'type'      => 'tab',
						'placement' => 'top',
						'endpoint'  => 0,
					),
					mrn_base_stack_get_theme_header_footer_color_field( 'header', 'background_color', __( 'Background Color', 'mrn-base-stack' ) ),
					mrn_base_stack_get_theme_header_footer_color_field( 'header', 'font_color', __( 'Font Color', 'mrn-base-stack' ) ),
					mrn_base_stack_get_theme_header_footer_color_field( 'header', 'link_color', __( 'Link Color', 'mrn-base-stack' ) ),
					mrn_base_stack_get_theme_header_footer_color_field( 'header', 'link_hover_color', __( 'Link Hover Color', 'mrn-base-stack' ) ),
					mrn_base_stack_get_theme_header_footer_font_family_field( 'header' ),
					array(
						'key'           => 'field_mrn_theme_header_show_utility_menu',
						'label'         => __( 'Show Secondary Menu', 'mrn-base-stack' ),
						'name'          => 'header_show_utility_menu',
						'type'          => 'true_false',
						'instructions'  => __( 'Uses the Header Secondary menu location.', 'mrn-base-stack' ),
						'required'      => 0,
						'default_value' => 0,
						'ui'            => 1,
					),
					array(
						'key'           => 'field_mrn_theme_header_show_tertiary_menu',
						'label'         => __( 'Show Tertiary Menu', 'mrn-base-stack' ),
						'name'          => 'header_show_tertiary_menu',
						'type'          => 'true_false',
						'instructions'  => __( 'Uses the Header Tertiary menu location.', 'mrn-base-stack' ),
						'required'      => 0,
						'default_value' => 0,
						'ui'            => 1,
					),
					array(
						'key'           => 'field_mrn_theme_header_primary_nav_inherit_header_settings',
						'label'         => __( 'Primary Menu Matches Header', 'mrn-base-stack' ),
						'name'          => 'header_primary_nav_inherit_header_settings',
						'type'          => 'true_false',
						'instructions'  => __( 'Applies the Header colors, font, and content width to the standalone Primary menu row.', 'mrn-base-stack' ),
						'required'      => 0,
						'default_value' => 1,
						'ui'            => 1,
					),
					array(
						'key'           => 'field_mrn_theme_header_show_search',
						'label'         => __( 'Show Search', 'mrn-base-stack' ),
						'name'          => 'header_show_search',
						'type'          => 'true_false',
						'instructions'  => __( 'Shows the stack SearchWP form area.', 'mrn-base-stack' ),
						'required'      => 0,
						'default_value' => 0,
						'ui'            => 1,
					),
					array(
						'key'               => 'field_mrn_theme_header_searchwp_form_id',
						'label'             => __( 'SearchWP Form', 'mrn-base-stack' ),
						'name'              => 'header_searchwp_form_id',
						'type'              => 'select',
						'choices'           => function_exists( 'mrn_base_stack_get_searchwp_form_choices' ) ? mrn_base_stack_get_searchwp_form_choices() : array(),
						'default_value'     => function_exists( 'mrn_base_stack_get_default_searchwp_form_id' ) ? (string) mrn_base_stack_get_default_searchwp_form_id() : '',
						'allow_null'        => 0,
						'multiple'          => 0,
						'required'          => 1,
						'ui'                => 1,
						'ajax'              => 0,
						'return_format'     => 'value',
						'instructions'      => __( 'Uses the stack SearchWP form for the header search area.', 'mrn-base-stack' ),
						'conditional_logic' => array(
							array(
								array(
									'field'    => 'field_mrn_theme_header_show_search',
									'operator' => '==',
									'value'    => '1',
								),
							),
						),
					),
					array(
						'key'               => 'field_mrn_theme_header_search_style',
						'label'             => __( 'Search Display', 'mrn-base-stack' ),
						'name'              => 'header_search_style',
						'type'              => 'button_group',
						'choices'           => array(
							'full'      => __( 'Full Form', 'mrn-base-stack' ),
							'icon_only' => __( 'Icon Only', 'mrn-base-stack' ),
						),
						'default_value'     => 'full',
						'layout'            => 'horizontal',
						'return_format'     => 'value',
						'instructions'      => __( 'Icon Only starts as a search icon and expands the search field after click.', 'mrn-base-stack' ),
						'conditional_logic' => array(
							array(
								array(
									'field'    => 'field_mrn_theme_header_show_search',
									'operator' => '==',
									'value'    => '1',
								),
							),
						),
					),
					array(
						'key'               => 'field_mrn_theme_header_search_icon_source',
						'label'             => __( 'Icon Source', 'mrn-base-stack' ),
						'name'              => 'header_search_icon_source',
						'type'              => 'button_group',
						'choices'           => array(
							'dashicons'   => __( 'Dashicons', 'mrn-base-stack' ),
							'fontawesome' => __( 'Font Awesome', 'mrn-base-stack' ),
							'media'       => __( 'Media', 'mrn-base-stack' ),
						),
						'default_value'     => 'dashicons',
						'layout'            => 'horizontal',
						'return_format'     => 'value',
						'wrapper'           => array(
							'class' => 'mrn-icon-chooser-field mrn-icon-chooser-field--source',
						),
						'conditional_logic' => array(
							array(
								array(
									'field'    => 'field_mrn_theme_header_show_search',
									'operator' => '==',
									'value'    => '1',
								),
								array(
									'field'    => 'field_mrn_theme_header_search_style',
									'operator' => '==',
									'value'    => 'icon_only',
								),
							),
						),
					),
					array(
						'key'               => 'field_mrn_theme_header_search_standard_icon',
						'label'             => __( 'Dashicon', 'mrn-base-stack' ),
						'name'              => 'header_search_standard_icon',
						'type'              => 'select',
						'choices'           => mrn_base_stack_get_header_search_standard_icon_choices(),
						'default_value'     => 'dashicons-search',
						'return_format'     => 'value',
						'ui'                => 1,
						'wrapper'           => array(
							'class' => 'mrn-icon-chooser-field mrn-icon-chooser-field--dashicons',
						),
						'conditional_logic' => array(
							array(
								array(
									'field'    => 'field_mrn_theme_header_show_search',
									'operator' => '==',
									'value'    => '1',
								),
								array(
									'field'    => 'field_mrn_theme_header_search_style',
									'operator' => '==',
									'value'    => 'icon_only',
								),
								array(
									'field'    => 'field_mrn_theme_header_search_icon_source',
									'operator' => '==',
									'value'    => 'dashicons',
								),
							),
						),
					),
					array(
						'key'               => 'field_mrn_theme_header_search_fa_class',
						'label'             => __( 'Font Awesome Icon', 'mrn-base-stack' ),
						'name'              => 'header_search_fa_class',
						'type'              => 'select',
						'choices'           => mrn_base_stack_get_header_search_fontawesome_choices(),
						'default_value'     => 'fa-solid fa-magnifying-glass',
						'return_format'     => 'value',
						'ui'                => 1,
						'wrapper'           => array(
							'class' => 'mrn-icon-chooser-field mrn-icon-chooser-field--fontawesome',
						),
						'conditional_logic' => array(
							array(
								array(
									'field'    => 'field_mrn_theme_header_show_search',
									'operator' => '==',
									'value'    => '1',
								),
								array(
									'field'    => 'field_mrn_theme_header_search_style',
									'operator' => '==',
									'value'    => 'icon_only',
								),
								array(
									'field'    => 'field_mrn_theme_header_search_icon_source',
									'operator' => '==',
									'value'    => 'fontawesome',
								),
							),
						),
					),
					array(
						'key'               => 'field_mrn_theme_header_search_media_icon',
						'label'             => __( 'Media Icon', 'mrn-base-stack' ),
						'name'              => 'header_search_media_icon',
						'type'              => 'image',
						'return_format'     => 'id',
						'preview_size'      => 'thumbnail',
						'library'           => 'all',
						'mime_types'        => 'jpg,jpeg,png,gif,webp,svg',
						'instructions'      => __( 'Upload or choose the icon image to use for the icon-only search trigger.', 'mrn-base-stack' ),
						'wrapper'           => array(
							'class' => 'mrn-icon-chooser-field mrn-icon-chooser-field--media',
						),
						'conditional_logic' => array(
							array(
								array(
									'field'    => 'field_mrn_theme_header_show_search',
									'operator' => '==',
									'value'    => '1',
								),
								array(
									'field'    => 'field_mrn_theme_header_search_style',
									'operator' => '==',
									'value'    => 'icon_only',
								),
								array(
									'field'    => 'field_mrn_theme_header_search_icon_source',
									'operator' => '==',
									'value'    => 'media',
								),
							),
						),
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
					mrn_base_stack_get_theme_header_footer_color_field( 'footer', 'background_color', __( 'Background Color', 'mrn-base-stack' ) ),
					mrn_base_stack_get_theme_header_footer_color_field( 'footer', 'font_color', __( 'Font Color', 'mrn-base-stack' ) ),
					mrn_base_stack_get_theme_header_footer_color_field( 'footer', 'link_color', __( 'Link Color', 'mrn-base-stack' ) ),
					mrn_base_stack_get_theme_header_footer_color_field( 'footer', 'link_hover_color', __( 'Link Hover Color', 'mrn-base-stack' ) ),
					mrn_base_stack_get_theme_header_footer_font_family_field( 'footer' ),
					array(
						'key'           => 'field_mrn_theme_footer_show_social_menu',
						'label'         => __( 'Show Social Icons', 'mrn-base-stack' ),
						'name'          => 'footer_show_social_menu',
						'type'          => 'true_false',
						'instructions'  => __( 'Uses Social Site Configuration. Icons still render when a configured social row has no URL.', 'mrn-base-stack' ),
						'default_value' => 0,
						'ui'            => 1,
					),
					array(
						'key'               => 'field_mrn_theme_footer_social_icon_color',
						'label'             => __( 'Social Icon Color', 'mrn-base-stack' ),
						'name'              => 'footer_social_icon_color',
						'type'              => 'select',
						'choices'           => mrn_base_stack_get_site_color_choices(),
						'default_value'     => mrn_base_stack_get_default_site_color_slug(),
						'allow_null'        => 0,
						'multiple'          => 0,
						'required'          => 1,
						'ui'                => 1,
						'ajax'              => 0,
						'return_format'     => 'value',
						'instructions'      => __( 'Uses a Site Styles color for Social Site Configuration icons when rendered in the Footer.', 'mrn-base-stack' ),
						'conditional_logic' => array(
							array(
								array(
									'field'    => 'field_mrn_theme_footer_show_social_menu',
									'operator' => '==',
									'value'    => '1',
								),
							),
						),
					),
					array(
						'key'               => 'field_mrn_theme_footer_social_icon_hover_color',
						'label'             => __( 'Social Icon Hover Color', 'mrn-base-stack' ),
						'name'              => 'footer_social_icon_hover_color',
						'type'              => 'select',
						'choices'           => mrn_base_stack_get_site_color_choices(),
						'default_value'     => mrn_base_stack_get_default_site_color_slug(),
						'allow_null'        => 0,
						'multiple'          => 0,
						'required'          => 1,
						'ui'                => 1,
						'ajax'              => 0,
						'return_format'     => 'value',
						'instructions'      => __( 'Uses a Site Styles color for Footer social icon hover and focus states.', 'mrn-base-stack' ),
						'conditional_logic' => array(
							array(
								array(
									'field'    => 'field_mrn_theme_footer_show_social_menu',
									'operator' => '==',
									'value'    => '1',
								),
							),
						),
					),
					array(
						'key'           => 'field_mrn_theme_footer_show_tertiary_menu',
						'label'         => __( 'Show Tertiary Menu', 'mrn-base-stack' ),
						'name'          => 'footer_show_tertiary_menu',
						'type'          => 'true_false',
						'instructions'  => __( 'Uses the Footer Tertiary menu location.', 'mrn-base-stack' ),
						'default_value' => 0,
						'ui'            => 1,
					),
					array(
						'key'           => 'field_mrn_theme_footer_show_privacy_center_links',
						'label'         => __( 'Show Privacy Center Links', 'mrn-base-stack' ),
						'name'          => 'footer_show_privacy_center_links',
						'type'          => 'true_false',
						'instructions'  => __( 'Uses the Privacy Center Links menu location.', 'mrn-base-stack' ),
						'default_value' => 0,
						'ui'            => 1,
					),
					array(
						'key'           => 'field_mrn_theme_footer_show_secondary_menu',
						'label'         => __( 'Show Secondary Menu', 'mrn-base-stack' ),
						'name'          => 'footer_show_secondary_menu',
						'type'          => 'true_false',
						'instructions'  => __( 'Uses the Footer Secondary menu location.', 'mrn-base-stack' ),
						'default_value' => 0,
						'ui'            => 1,
					),
					array(
						'key'           => 'field_mrn_theme_footer_show_footer_menu',
						'label'         => __( 'Show Primary Menu', 'mrn-base-stack' ),
						'name'          => 'footer_show_footer_menu',
						'type'          => 'true_false',
						'instructions'  => __( 'Uses the Footer Primary menu location.', 'mrn-base-stack' ),
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
				)
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
					'return_format' => 'id',
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
					'return_format' => 'id',
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
					'return_format' => 'id',
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
					'return_format' => 'id',
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

	$business_logo_url = function_exists( 'mrn_base_stack_get_attachment_image_url' ) ? mrn_base_stack_get_attachment_image_url( $business_logo, 'mrn-logo' ) : '';
	if ( '' !== $business_logo_url ) {
		$schema['logo'] = esc_url_raw( $business_logo_url );
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
 * @return mixed|null
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
		if ( ! empty( $business_information[ $key ] ) && function_exists( 'mrn_base_stack_image_has_content' ) && mrn_base_stack_image_has_content( $business_information[ $key ] ) ) {
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
	$default_site_color_slug = mrn_base_stack_get_default_site_color_slug();
	$defaults                = array_merge(
		array(
			'header_background_color'                    => '',
			'header_font_color'                          => '',
			'header_link_color'                          => '',
			'header_link_hover_color'                    => '',
			'header_font_family'                         => '',
			'header_show_social_menu'                    => false,
			'header_show_tertiary_menu'                  => false,
			'header_show_secondary_menu'                 => false,
			'header_show_utility_menu'                   => false,
			'header_primary_nav_inherit_header_settings' => true,
			'header_show_search'                         => false,
			'header_searchwp_form_id'                    => 0,
			'header_search_style'                        => 'full',
			'header_search_icon_source'                  => 'dashicons',
			'header_search_standard_icon'                => 'dashicons-search',
			'header_search_fa_class'                     => 'fa-solid fa-magnifying-glass',
			'header_search_media_icon'                   => null,
			'header_show_business_phone'                 => false,
			'header_show_business_profile'               => false,
			'header_content_width'                       => 'wide',
			'header_layout_grid'                         => mrn_base_stack_get_default_theme_header_footer_layout_grid( 'header' ),
			'footer_background_color'                    => '',
			'footer_font_color'                          => '',
			'footer_link_color'                          => '',
			'footer_link_hover_color'                    => '',
			'footer_font_family'                         => '',
			'footer_show_social_menu'                    => false,
			'footer_social_icon_color'                   => $default_site_color_slug,
			'footer_social_icon_hover_color'             => $default_site_color_slug,
			'footer_show_tertiary_menu'                  => false,
			'footer_show_secondary_menu'                 => false,
			'footer_show_primary_menu'                   => false,
			'footer_show_footer_menu'                    => false,
			'footer_show_privacy_center_links'           => false,
			'footer_show_business_profile'               => false,
			'footer_show_business_phone'                 => false,
			'footer_show_text_phone'                     => false,
			'footer_show_address'                        => false,
			'footer_show_business_hours'                 => false,
			'footer_show_social_links'                   => false,
			'footer_copyright_text'                      => '',
			'footer_legal_text'                          => '',
			'footer_content_width'                       => 'wide',
			'footer_layout_grid'                         => mrn_base_stack_get_default_theme_header_footer_layout_grid( 'footer' ),
		),
		mrn_base_stack_get_default_theme_header_footer_spacing_options( 'header' ),
		mrn_base_stack_get_default_theme_header_footer_spacing_options( 'footer' )
	);

	if ( ! function_exists( 'get_field' ) ) {
		return $defaults;
	}

	$header_search_style         = (string) get_field( 'header_search_style', 'option' );
	$header_search_icon_source   = (string) get_field( 'header_search_icon_source', 'option' );
	$header_search_standard_icon = (string) get_field( 'header_search_standard_icon', 'option' );
	$header_search_fa_class      = (string) get_field( 'header_search_fa_class', 'option' );
	$header_search_media_icon    = get_field( 'header_search_media_icon', 'option' );
	$header_searchwp_form_id     = absint( get_field( 'header_searchwp_form_id', 'option' ) );
	$header_content_width        = mrn_base_stack_normalize_theme_header_footer_content_width( get_field( 'header_content_width', 'option' ), 'wide' );
	$header_background_color     = mrn_base_stack_normalize_site_color_slug( get_field( 'header_background_color', 'option' ) );
	$header_font_color           = mrn_base_stack_normalize_site_color_slug( get_field( 'header_font_color', 'option' ) );
	$header_link_color           = mrn_base_stack_normalize_site_color_slug( get_field( 'header_link_color', 'option' ) );
	$header_link_hover_color     = mrn_base_stack_normalize_site_color_slug( get_field( 'header_link_hover_color', 'option' ) );
	$header_font_family          = mrn_base_stack_normalize_theme_header_footer_font_family( get_field( 'header_font_family', 'option' ) );
	$searchwp_forms              = function_exists( 'mrn_base_stack_get_searchwp_forms' ) ? mrn_base_stack_get_searchwp_forms() : array();
	$default_searchwp_form_id    = function_exists( 'mrn_base_stack_get_default_searchwp_form_id' ) ? mrn_base_stack_get_default_searchwp_form_id() : 0;
	$standard_icon_choices       = array_keys( mrn_base_stack_get_header_search_standard_icon_choices() );
	$fontawesome_choices         = array_keys( mrn_base_stack_get_header_search_fontawesome_choices() );

	if ( ! in_array( $header_search_style, array( 'full', 'icon_only' ), true ) ) {
		$header_search_style = 'full';
	}

	if ( 'standard' === $header_search_icon_source ) {
		$header_search_icon_source = 'dashicons';
	}

	if ( ! in_array( $header_search_icon_source, array( 'dashicons', 'fontawesome', 'media' ), true ) ) {
		$header_search_icon_source = 'dashicons';
	}

	if ( ! in_array( $header_search_standard_icon, $standard_icon_choices, true ) ) {
		$header_search_standard_icon = 'dashicons-search';
	}

	if ( ! in_array( $header_search_fa_class, $fontawesome_choices, true ) ) {
		$header_search_fa_class = 'fa-solid fa-magnifying-glass';
	}

	if ( ! function_exists( 'mrn_base_stack_image_has_content' ) || ! mrn_base_stack_image_has_content( $header_search_media_icon ) ) {
		$header_search_media_icon = null;
	}

	if ( $header_searchwp_form_id < 1 || ! isset( $searchwp_forms[ $header_searchwp_form_id ] ) ) {
		$header_searchwp_form_id = absint( $default_searchwp_form_id );
	}

	$header_show_secondary_menu     = (bool) get_field( 'header_show_utility_menu', 'option' );
	$header_primary_nav_inherit_raw = get_option( 'options_header_primary_nav_inherit_header_settings', '__mrn_missing__' );
	$header_primary_nav_inherit     = '__mrn_missing__' === $header_primary_nav_inherit_raw ? true : (bool) get_field( 'header_primary_nav_inherit_header_settings', 'option' );
	$footer_show_primary_menu       = (bool) get_field( 'footer_show_footer_menu', 'option' );
	$footer_show_tertiary_menu      = (bool) get_field( 'footer_show_tertiary_menu', 'option' );
	$footer_show_social_menu        = (bool) get_field( 'footer_show_social_menu', 'option' );
	$footer_social_icon_color       = mrn_base_stack_normalize_site_color_slug( get_field( 'footer_social_icon_color', 'option' ) );
	$footer_social_icon_hover_color = mrn_base_stack_normalize_site_color_slug( get_field( 'footer_social_icon_hover_color', 'option' ) );
	$footer_content_width           = mrn_base_stack_normalize_theme_header_footer_content_width( get_field( 'footer_content_width', 'option' ), 'wide' );
	$footer_background_color        = mrn_base_stack_normalize_site_color_slug( get_field( 'footer_background_color', 'option' ) );
	$footer_font_color              = mrn_base_stack_normalize_site_color_slug( get_field( 'footer_font_color', 'option' ) );
	$footer_link_color              = mrn_base_stack_normalize_site_color_slug( get_field( 'footer_link_color', 'option' ) );
	$footer_link_hover_color        = mrn_base_stack_normalize_site_color_slug( get_field( 'footer_link_hover_color', 'option' ) );
	$footer_font_family             = mrn_base_stack_normalize_theme_header_footer_font_family( get_field( 'footer_font_family', 'option' ) );
	$footer_social_icon_color       = '' !== $footer_social_icon_color ? $footer_social_icon_color : $default_site_color_slug;
	$footer_social_icon_hover_color = '' !== $footer_social_icon_hover_color ? $footer_social_icon_hover_color : $default_site_color_slug;

	$options = array(
		'header_background_color'                    => $header_background_color,
		'header_font_color'                          => $header_font_color,
		'header_link_color'                          => $header_link_color,
		'header_link_hover_color'                    => $header_link_hover_color,
		'header_font_family'                         => $header_font_family,
		'header_show_social_menu'                    => (bool) get_field( 'header_show_social_menu', 'option' ),
		'header_show_tertiary_menu'                  => (bool) get_field( 'header_show_tertiary_menu', 'option' ),
		'header_show_secondary_menu'                 => $header_show_secondary_menu,
		'header_show_utility_menu'                   => (bool) get_field( 'header_show_utility_menu', 'option' ),
		'header_primary_nav_inherit_header_settings' => $header_primary_nav_inherit,
		'header_show_search'                         => (bool) get_field( 'header_show_search', 'option' ),
		'header_searchwp_form_id'                    => $header_searchwp_form_id,
		'header_search_style'                        => $header_search_style,
		'header_search_icon_source'                  => $header_search_icon_source,
		'header_search_standard_icon'                => $header_search_standard_icon,
		'header_search_fa_class'                     => $header_search_fa_class,
		'header_search_media_icon'                   => $header_search_media_icon,
		'header_show_business_phone'                 => (bool) get_field( 'header_show_business_phone', 'option' ),
		'header_show_business_profile'               => (bool) get_field( 'header_show_business_profile', 'option' ),
		'header_content_width'                       => $header_content_width,
		'header_layout_grid'                         => mrn_base_stack_get_theme_header_footer_layout_grid( 'header' ),
		'footer_background_color'                    => $footer_background_color,
		'footer_font_color'                          => $footer_font_color,
		'footer_link_color'                          => $footer_link_color,
		'footer_link_hover_color'                    => $footer_link_hover_color,
		'footer_font_family'                         => $footer_font_family,
		'footer_show_social_menu'                    => $footer_show_social_menu,
		'footer_social_icon_color'                   => $footer_social_icon_color,
		'footer_social_icon_hover_color'             => $footer_social_icon_hover_color,
		'footer_show_tertiary_menu'                  => $footer_show_tertiary_menu,
		'footer_show_secondary_menu'                 => (bool) get_field( 'footer_show_secondary_menu', 'option' ),
		'footer_show_primary_menu'                   => $footer_show_primary_menu,
		'footer_show_footer_menu'                    => $footer_show_primary_menu,
		'footer_show_privacy_center_links'           => (bool) get_field( 'footer_show_privacy_center_links', 'option' ),
		'footer_show_business_profile'               => (bool) get_field( 'footer_show_business_profile', 'option' ),
		'footer_show_business_phone'                 => (bool) get_field( 'footer_show_business_phone', 'option' ),
		'footer_show_text_phone'                     => (bool) get_field( 'footer_show_text_phone', 'option' ),
		'footer_show_address'                        => (bool) get_field( 'footer_show_address', 'option' ),
		'footer_show_business_hours'                 => (bool) get_field( 'footer_show_business_hours', 'option' ),
		'footer_show_social_links'                   => (bool) get_field( 'footer_show_social_links', 'option' ),
		'footer_copyright_text'                      => (string) get_field( 'footer_copyright_text', 'option' ),
		'footer_legal_text'                          => (string) get_field( 'footer_legal_text', 'option' ),
		'footer_content_width'                       => $footer_content_width,
		'footer_layout_grid'                         => mrn_base_stack_get_theme_header_footer_layout_grid( 'footer' ),
	);

	$options = array_merge(
		$options,
		mrn_base_stack_get_theme_header_footer_spacing_options( 'header' ),
		mrn_base_stack_get_theme_header_footer_spacing_options( 'footer' )
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
 * Load the shared sticky toolbar helper when available.
 *
 * @return bool
 */
function mrn_base_stack_load_sticky_toolbar_helper() {
	static $loaded = false;

	if ( $loaded || function_exists( 'mrn_sticky_toolbar_render' ) ) {
		$loaded = true;
		return true;
	}

	if ( defined( 'WP_CONTENT_DIR' ) ) {
		$content_toolbar_helper = WP_CONTENT_DIR . '/shared/mrn-sticky-settings-toolbar.php';

		if ( file_exists( $content_toolbar_helper ) ) {
			require_once WP_CONTENT_DIR . '/shared/mrn-sticky-settings-toolbar.php';
			$loaded = function_exists( 'mrn_sticky_toolbar_render' );

			if ( $loaded ) {
				return true;
			}
		}
	}

	$repo_toolbar_helper = dirname( __DIR__, 4 ) . '/shared/mrn-sticky-settings-toolbar.php';

	if ( file_exists( $repo_toolbar_helper ) ) {
		require_once dirname( __DIR__, 4 ) . '/shared/mrn-sticky-settings-toolbar.php';
		$loaded = function_exists( 'mrn_sticky_toolbar_render' );

		if ( $loaded ) {
			return true;
		}
	}

	return false;
}

/**
 * Return sticky-toolbar configuration for supported theme option screens.
 *
 * @param string $screen_id Current screen id.
 * @return array<string, string>|null
 */
function mrn_base_stack_get_theme_options_toolbar_config( $screen_id ) {
	$configs = array(
		'toplevel_page_mrn-theme-header-footer' => array(
			'toolbar_id' => 'mrn-theme-header-footer-toolbar',
			'title'      => __( 'Theme Header/Footer', 'mrn-base-stack' ),
			'page_class' => 'toplevel_page_mrn-theme-header-footer',
		),
		'toplevel_page_mrn-business-information' => array(
			'toolbar_id' => 'mrn-business-information-toolbar',
			'title'      => __( 'Business Information', 'mrn-base-stack' ),
			'page_class' => 'toplevel_page_mrn-business-information',
		),
	);

	return isset( $configs[ $screen_id ] ) ? $configs[ $screen_id ] : null;
}

/**
 * Register shared toolbar hooks for theme-owned options pages.
 *
 * @param WP_Screen $screen Current screen.
 * @return void
 */
function mrn_base_stack_setup_theme_options_toolbar( $screen ) {
	if ( ! $screen instanceof WP_Screen ) {
		return;
	}

	$config = mrn_base_stack_get_theme_options_toolbar_config( $screen->id );
	if ( ! is_array( $config ) || ! mrn_base_stack_load_sticky_toolbar_helper() ) {
		return;
	}

	add_action(
		'all_admin_notices',
		static function () use ( $config ) {
			if ( ! function_exists( 'mrn_sticky_toolbar_render' ) ) {
				return;
			}

			mrn_sticky_toolbar_render(
				array(
					'toolbar_id' => $config['toolbar_id'],
					'form_id'    => 'post',
					'title'      => $config['title'],
					'save_label' => __( 'Save Settings', 'mrn-base-stack' ),
					'aria_label' => $config['title'] . ' ' . __( 'actions', 'mrn-base-stack' ),
					'tabs'       => array(
						array(
							'key'    => 'general',
							'label'  => $config['title'],
							'active' => true,
						),
					),
				)
			);
		},
		1
	);

	add_action(
		'admin_head',
		static function () use ( $config ) {
			if ( ! function_exists( 'mrn_sticky_toolbar_render_css' ) ) {
				return;
			}

			ob_start();
			mrn_sticky_toolbar_render_css(
				array(
					'toolbar_id'           => $config['toolbar_id'],
					'page_class'           => $config['page_class'],
					'desktop_left'         => 196,
					'desktop_right'        => 0,
					'mobile_left'          => 10,
					'mobile_right'         => 10,
					'spacer_height'        => 96,
					'spacer_height_mobile' => 116,
				)
			);
			$toolbar_css = trim( (string) ob_get_clean() );
			if ( '' !== $toolbar_css ) {
				echo $toolbar_css; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			?>
			<style>
				body.<?php echo esc_html( $config['page_class'] ); ?> #screen-meta-links,
				body.<?php echo esc_html( $config['page_class'] ); ?> #submitdiv,
				body.<?php echo esc_html( $config['page_class'] ); ?> #side-sortables,
				body.<?php echo esc_html( $config['page_class'] ); ?> #postbox-container-1 {
					display: none !important;
				}
				body.<?php echo esc_html( $config['page_class'] ); ?> #poststuff {
					padding-top: 0;
				}
				body.<?php echo esc_html( $config['page_class'] ); ?> #post-body,
				body.<?php echo esc_html( $config['page_class'] ); ?> #post-body.columns-2,
				body.<?php echo esc_html( $config['page_class'] ); ?> #post-body-content {
					margin-right: 0 !important;
					width: 100% !important;
					float: none !important;
				}
				body.<?php echo esc_html( $config['page_class'] ); ?> .wrap.acf-settings-wrap,
				body.<?php echo esc_html( $config['page_class'] ); ?> .wrap {
					max-width: none;
				}
			</style>
			<?php
		}
	);
}
add_action( 'current_screen', 'mrn_base_stack_setup_theme_options_toolbar' );

/**
 * Check whether the current admin screen is Theme Header/Footer options.
 *
 * @return bool
 */
function mrn_base_stack_is_theme_header_footer_options_screen() {
	if ( ! function_exists( 'get_current_screen' ) ) {
		return false;
	}

	$screen = get_current_screen();
	return $screen instanceof WP_Screen && 'toplevel_page_mrn-theme-header-footer' === $screen->id;
}

/**
 * Keep the Header/Footer settings screen from serving stale admin HTML/assets.
 *
 * @return void
 */
function mrn_base_stack_send_theme_header_footer_options_no_store_headers() {
	if ( ! is_admin() || empty( $_GET['page'] ) || 'mrn-theme-header-footer' !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}

	nocache_headers();
}
add_action( 'admin_init', 'mrn_base_stack_send_theme_header_footer_options_no_store_headers', 0 );

/**
 * Enqueue Header/Footer layout editor assets.
 *
 * @param string $hook_suffix Current admin hook suffix.
 * @return void
 */
function mrn_base_stack_enqueue_theme_header_footer_layout_assets( $hook_suffix ) {
	if ( 'toplevel_page_mrn-theme-header-footer' !== $hook_suffix ) {
		return;
	}

	$style_path  = get_template_directory() . '/css/admin-header-footer-layout.css';
	$script_path = get_template_directory() . '/js/admin-header-footer-layout.js';
	$style_ver   = file_exists( $style_path ) ? _S_VERSION . '-' . (string) filemtime( $style_path ) : _S_VERSION;
	$script_ver  = file_exists( $script_path ) ? _S_VERSION . '-' . (string) filemtime( $script_path ) : _S_VERSION;

	wp_enqueue_style(
		'mrn-base-stack-admin-header-footer-layout',
		get_template_directory_uri() . '/css/admin-header-footer-layout.css',
		array(),
		$style_ver
	);

	wp_enqueue_script(
		'mrn-base-stack-admin-header-footer-layout',
		get_template_directory_uri() . '/js/admin-header-footer-layout.js',
		array( 'jquery' ),
		$script_ver,
		true
	);

	wp_add_inline_script(
		'mrn-base-stack-admin-header-footer-layout',
		'window.requestAnimationFrame(function(){ if (window.mrnThemeHeaderFooterLayout && typeof window.mrnThemeHeaderFooterLayout.initialize === "function") { window.mrnThemeHeaderFooterLayout.initialize(); } });',
		'after'
	);
}
add_action( 'admin_enqueue_scripts', 'mrn_base_stack_enqueue_theme_header_footer_layout_assets' );

/**
 * Print structural CSS so Header/Footer sub-tabs render with native WP tab look.
 *
 * @return void
 */
function mrn_base_stack_print_theme_header_footer_subtab_layout_css() {
	if ( ! mrn_base_stack_is_theme_header_footer_options_screen() ) {
		return;
	}

	$appearance          = mrn_base_stack_get_theme_header_footer_subtab_appearance();
	$tab_set_gap_px      = isset( $appearance['tab_set_gap_px'] ) ? absint( $appearance['tab_set_gap_px'] ) : 10;
	$panel_gap_px        = isset( $appearance['panel_gap_px'] ) ? absint( $appearance['panel_gap_px'] ) : 12;
	$tab_border_width_px = isset( $appearance['tab_border_width_px'] ) ? absint( $appearance['tab_border_width_px'] ) : 1;
	$tab_border_color    = isset( $appearance['tab_border_color'] ) ? (string) $appearance['tab_border_color'] : '#c3c4c7';
	?>
		<style>
			body.toplevel_page_mrn-theme-header-footer {
				--mrn-theme-hf-tabset-gap: <?php echo esc_html( (string) $tab_set_gap_px ); ?>px;
				--mrn-theme-hf-panel-gap: <?php echo esc_html( (string) $panel_gap_px ); ?>px;
				--mrn-theme-hf-tab-border-width: <?php echo esc_html( (string) $tab_border_width_px ); ?>px;
				--mrn-theme-hf-tab-border-color: <?php echo esc_html( $tab_border_color ); ?>;
			}

			body.toplevel_page_mrn-theme-header-footer .acf-tab-wrap.-top {
				border-bottom: var(--mrn-theme-hf-tab-border-width, 1px) solid var(--mrn-theme-hf-tab-border-color, #c3c4c7);
				margin-bottom: 0;
				padding-bottom: 0;
			}

			body.toplevel_page_mrn-theme-header-footer .acf-tab-wrap.-top .acf-tab-group {
				border-bottom: 0;
				margin-bottom: 0;
			}

			body.toplevel_page_mrn-theme-header-footer .acf-field.mrn-theme-hf-subtabs-nav {
				border-top: 0;
				padding: 0;
			}

			body.toplevel_page_mrn-theme-header-footer .acf-field.mrn-theme-hf-subtabs-nav > .acf-label {
				display: none;
			}

			body.toplevel_page_mrn-theme-header-footer .acf-field.mrn-theme-hf-subtabs-nav > .acf-input {
				margin: 0;
				width: 100%;
			}

			body.toplevel_page_mrn-theme-header-footer .acf-field.mrn-theme-hf-subtabs-nav .acf-input > .acf-message {
				margin: 0;
				padding: 0;
			}

			body.toplevel_page_mrn-theme-header-footer .mrn-theme-hf-subtabs {
				padding-top: var(--mrn-theme-hf-tabset-gap, 10px);
			}

			body.toplevel_page_mrn-theme-header-footer .mrn-theme-hf-subtabs .nav-tab-wrapper {
				border-bottom: var(--mrn-theme-hf-tab-border-width, 1px) solid var(--mrn-theme-hf-tab-border-color, #c3c4c7);
				margin: 0 0 var(--mrn-theme-hf-panel-gap, 12px);
				padding-top: 0;
			}

			body.toplevel_page_mrn-theme-header-footer .acf-field.mrn-theme-hf-subtab-panel {
				border-top: 0;
				padding-top: 0;
			}

			body.toplevel_page_mrn-theme-header-footer .acf-field.mrn-theme-hf-subtab-panel[hidden] {
				display: none !important;
			}

			body.toplevel_page_mrn-theme-header-footer .acf-field.mrn-theme-hf-config-heading {
				border-top: 1px solid #dcdcde;
				clear: both;
				margin-top: 12px;
				padding: 18px 12px 8px;
			}

			body.toplevel_page_mrn-theme-header-footer .acf-field.mrn-theme-hf-config-heading:first-of-type {
				margin-top: 0;
			}

			body.toplevel_page_mrn-theme-header-footer .acf-field.mrn-theme-hf-config-heading > .acf-label {
				display: none;
			}

			body.toplevel_page_mrn-theme-header-footer .acf-field.mrn-theme-hf-config-heading > .acf-input {
				margin: 0;
				width: 100%;
			}

			body.toplevel_page_mrn-theme-header-footer .acf-field.mrn-theme-hf-config-heading .acf-input > .acf-message {
				margin: 0;
				padding: 0;
			}

			body.toplevel_page_mrn-theme-header-footer .mrn-theme-hf-config-heading__title {
				font-size: 14px;
				line-height: 1.4;
				margin: 0;
			}

			body.toplevel_page_mrn-theme-header-footer .acf-field.mrn-theme-hf-config-toggle,
			body.toplevel_page_mrn-theme-header-footer .acf-field.mrn-theme-hf-config-detail {
				min-height: 92px;
			}

			@media (max-width: 782px) {
				body.toplevel_page_mrn-theme-header-footer .acf-field.mrn-theme-hf-config-toggle,
				body.toplevel_page_mrn-theme-header-footer .acf-field.mrn-theme-hf-config-detail {
					width: 100% !important;
				}
			}
		</style>
		<?php
}
add_action( 'admin_head', 'mrn_base_stack_print_theme_header_footer_subtab_layout_css' );

/**
 * Print Header/Footer sub-tab behavior.
 *
 * @return void
 */
function mrn_base_stack_print_theme_header_footer_subtab_script() {
	if ( ! mrn_base_stack_is_theme_header_footer_options_screen() ) {
		return;
	}
	?>
	<script>
		(function ($, window) {
			'use strict';

			var tabSelector = '[data-mrn-theme-hf-subtab]';
			var hashPrefix = 'mrn-theme-hf-';

			function getHashTabForSection(section) {
				var hash = String(window.location.hash || '').replace(/^#/, '').toLowerCase();
				var prefix = hashPrefix + String(section || '').toLowerCase() + '-';

				if (hash.indexOf(prefix) !== 0) {
					return '';
				}

				return hash.substring(prefix.length);
			}

			function updateSectionHash(section, tab) {
				if (!window.history || typeof window.history.replaceState !== 'function') {
					return;
				}

				var normalizedSection = String(section || '').toLowerCase();
				var normalizedTab = String(tab || '').toLowerCase();

				if (!normalizedSection || !normalizedTab) {
					return;
				}

				var url = window.location.pathname + window.location.search + '#' + hashPrefix + normalizedSection + '-' + normalizedTab;
				window.history.replaceState(null, '', url);
			}

				function getPanels($nav, section) {
					var $fieldContainer = $nav.closest('.acf-fields');
					if (!$fieldContainer.length) {
						return $();
					}

					return $fieldContainer.children('.acf-field.mrn-theme-hf-subtab-panel.mrn-theme-hf-subtab-section--' + section);
				}

				function activateSubtab($nav, requestedTab, shouldFocus, shouldUpdateHash) {
					var section = String($nav.attr('data-mrn-theme-hf-section') || '').toLowerCase();
					if (!section) {
						return;
					}

					var $tabs = $nav.find(tabSelector);
					if (!$tabs.length) {
						return;
				}

				var tab = String(requestedTab || '').toLowerCase();
				var $activeTab = $tabs.filter('[data-mrn-theme-hf-subtab="' + tab + '"]').first();

				if (!$activeTab.length) {
					$activeTab = $tabs.first();
					tab = String($activeTab.attr('data-mrn-theme-hf-subtab') || '').toLowerCase();
				}

				$tabs.removeClass('nav-tab-active').attr('aria-selected', 'false').attr('tabindex', '-1');
				$activeTab.addClass('nav-tab-active').attr('aria-selected', 'true').attr('tabindex', '0');

				if (shouldFocus) {
					$activeTab.trigger('focus');
				}

				var $panels = getPanels($nav, section);
				if ($panels.length) {
					$panels.prop('hidden', true).attr('aria-hidden', 'true').removeClass('is-active');
					$panels.filter('.mrn-theme-hf-subtab--' + tab).prop('hidden', false).attr('aria-hidden', 'false').addClass('is-active');
					}

					$nav.attr('data-mrn-theme-hf-active', tab);
					if (shouldUpdateHash) {
						updateSectionHash(section, tab);
					}
				}

			function initializeSubtabs(context) {
				var $scope = context && context.jquery ? context : $(context || document);

				$scope.find('.mrn-theme-hf-subtabs').each(function () {
					var $nav = $(this);
					var defaultTab = String($nav.attr('data-mrn-theme-hf-default') || 'configs').toLowerCase();
					var section = String($nav.attr('data-mrn-theme-hf-section') || '').toLowerCase();
					var hashTab = getHashTabForSection(section);
					var activeTab = String($nav.attr('data-mrn-theme-hf-active') || hashTab || defaultTab).toLowerCase();

						activateSubtab($nav, activeTab, false, false);
					});
				}

				$(document).on('click', '.mrn-theme-hf-subtabs ' + tabSelector, function (event) {
					event.preventDefault();
					var $tab = $(this);
					activateSubtab($tab.closest('.mrn-theme-hf-subtabs'), String($tab.attr('data-mrn-theme-hf-subtab') || ''), true, true);
				});

			$(document).on('keydown', '.mrn-theme-hf-subtabs ' + tabSelector, function (event) {
				var key = event.key || '';
				if (key !== 'ArrowLeft' && key !== 'ArrowRight' && key !== 'Home' && key !== 'End') {
					return;
				}

				event.preventDefault();

				var $tab = $(this);
				var $nav = $tab.closest('.mrn-theme-hf-subtabs');
				var $tabs = $nav.find(tabSelector);

				if (!$tabs.length) {
					return;
				}

				var currentIndex = $tabs.index($tab);
				var nextIndex = currentIndex;

				if (key === 'Home') {
					nextIndex = 0;
				} else if (key === 'End') {
					nextIndex = $tabs.length - 1;
					} else if (key === 'ArrowRight') {
						nextIndex = (currentIndex + 1) % $tabs.length;
					} else if (key === 'ArrowLeft') {
						nextIndex = (currentIndex - 1 + $tabs.length) % $tabs.length;
					}

					var $nextTab = $tabs.eq(nextIndex);
					activateSubtab($nav, String($nextTab.attr('data-mrn-theme-hf-subtab') || ''), true, true);
				});

			$(function () {
				initializeSubtabs(document);
			});

			if (window.acf && typeof window.acf.addAction === 'function') {
				window.acf.addAction('ready', function ($el) {
					initializeSubtabs($el || document);
				});

				window.acf.addAction('append', function ($el) {
					initializeSubtabs($el || document);
				});
			}
		})(jQuery, window);
	</script>
	<?php
}
add_action( 'acf/input/admin_footer', 'mrn_base_stack_print_theme_header_footer_subtab_script' );

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
