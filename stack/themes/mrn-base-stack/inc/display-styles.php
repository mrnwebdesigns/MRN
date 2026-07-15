<?php
/**
 * Shared display-style helpers.
 *
 * Display styles describe presentation variants for a specific renderable
 * entity. Unlike Display Modes, they do not define which fields render.
 *
 * @package mrn-base-stack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get the stack default display styles.
 *
 * @return array<string, array<string, mixed>>
 */
function mrn_base_stack_get_default_display_styles() {
	return array(
		'default' => array(
			'entity_type'    => 'builder_layout',
			'entity_subtype' => '*',
			'label'          => __( 'Default', 'mrn-base-stack' ),
			'display_modes'  => array( '*' ),
		),
		'story' => array(
			'entity_type'    => 'post_type',
			'entity_subtype' => 'testimonial',
			'label'          => __( 'Story', 'mrn-base-stack' ),
		),
		'quote' => array(
			'entity_type'    => 'post_type',
			'entity_subtype' => 'testimonial',
			'label'          => __( 'Quote', 'mrn-base-stack' ),
		),
	);
}

/**
 * Get display styles from stack defaults plus Config Helper when available.
 *
 * @return array<string, array<string, mixed>>
 */
function mrn_base_stack_get_display_styles() {
	$styles = mrn_base_stack_get_default_display_styles();

	if ( function_exists( 'mrn_config_helper_get_display_styles' ) ) {
		$saved_styles = mrn_config_helper_get_display_styles();

		if ( is_array( $saved_styles ) ) {
			foreach ( $saved_styles as $saved_style ) {
				if ( ! is_array( $saved_style ) ) {
					continue;
				}

				$style_key      = isset( $saved_style['style_key'] ) ? sanitize_key( (string) $saved_style['style_key'] ) : '';
				$label          = isset( $saved_style['label'] ) ? trim( (string) $saved_style['label'] ) : '';
				$entity_type    = isset( $saved_style['entity_type'] ) ? sanitize_key( (string) $saved_style['entity_type'] ) : 'post_type';
				$entity_subtype = isset( $saved_style['entity_subtype'] ) ? sanitize_key( (string) $saved_style['entity_subtype'] ) : '';

				if ( '' === $style_key || '' === $label || '' === $entity_subtype ) {
					continue;
				}

				$styles[ $style_key ] = array(
					'entity_type'    => $entity_type,
					'entity_subtype' => $entity_subtype,
					'label'          => $label,
				);
			}
		}
	}

	/**
	 * Filter stack display style definitions.
	 *
	 * @param array<string, array<string, mixed>> $styles Display styles keyed by style key.
	 */
	$styles = apply_filters( 'mrn_base_stack_display_styles', $styles );

	return is_array( $styles ) ? $styles : mrn_base_stack_get_default_display_styles();
}

/**
 * Get display style configs for one entity.
 *
 * @param string $entity_type    Entity type, such as post_type.
 * @param string $entity_subtype Entity subtype, such as testimonial.
 * @return array<string, array<string, mixed>>
 */
function mrn_base_stack_get_display_styles_for_entity( $entity_type, $entity_subtype ) {
	$entity_type    = sanitize_key( (string) $entity_type );
	$entity_subtype = sanitize_key( (string) $entity_subtype );
	$filtered       = array();

	foreach ( mrn_base_stack_get_display_styles() as $style_key => $style_config ) {
		if ( ! is_array( $style_config ) ) {
			continue;
		}

		$style_entity_type        = isset( $style_config['entity_type'] ) ? sanitize_key( (string) $style_config['entity_type'] ) : 'post_type';
		$style_entity_subtype_raw = isset( $style_config['entity_subtype'] ) ? trim( (string) $style_config['entity_subtype'] ) : '';
		$style_entity_subtype     = '*' === $style_entity_subtype_raw ? '*' : sanitize_key( $style_entity_subtype_raw );

		if ( $style_entity_type !== $entity_type || ( '*' !== $style_entity_subtype && $style_entity_subtype !== $entity_subtype ) ) {
			continue;
		}

		$style_key = sanitize_key( (string) $style_key );
		if ( '' === $style_key ) {
			continue;
		}

		$filtered[ $style_key ] = $style_config;
	}

	return $filtered;
}

/**
 * Get display style select choices for one entity.
 *
 * @param string $entity_type    Entity type.
 * @param string $entity_subtype Entity subtype.
 * @return array<string, string>
 */
function mrn_base_stack_get_display_style_choices_for_entity( $entity_type, $entity_subtype ) {
	$choices = array();

	foreach ( mrn_base_stack_get_display_styles_for_entity( $entity_type, $entity_subtype ) as $style_key => $style_config ) {
		$label = isset( $style_config['label'] ) ? trim( (string) $style_config['label'] ) : '';
		if ( '' === $label ) {
			continue;
		}

		$choices[ $style_key ] = $label;
	}

	return $choices;
}

/**
 * Normalize a display style key for one entity.
 *
 * @param string $style          Candidate style key.
 * @param string $entity_type    Entity type.
 * @param string $entity_subtype Entity subtype.
 * @param string $fallback       Optional fallback style key.
 * @return string
 */
function mrn_base_stack_normalize_display_style( $style, $entity_type, $entity_subtype, $fallback = '' ) {
	$style    = sanitize_key( (string) $style );
	$styles   = mrn_base_stack_get_display_styles_for_entity( $entity_type, $entity_subtype );
	$fallback = sanitize_key( (string) $fallback );

	if ( '' !== $style && isset( $styles[ $style ] ) ) {
		return $style;
	}

	if ( '' !== $fallback && isset( $styles[ $fallback ] ) ) {
		return $fallback;
	}

	$first_style = array_key_first( $styles );

	return is_string( $first_style ) ? $first_style : '';
}

/**
 * Get default display modes for builder layouts.
 *
 * Display modes describe the render structure. Display styles describe the
 * visual treatment applied to that structure.
 *
 * @return array<string, array<string, array<string, mixed>>>
 */
function mrn_base_stack_get_default_builder_layout_display_modes() {
	$single_mode_layouts = array(
		'basic',
		'body_text',
		'card',
		'cta',
		'external_widget',
		'grid',
		'hero',
		'image_content',
		'reusable_block',
		'searchwp_form',
		'showcase',
		'slider',
		'stats',
		'tabbed_layout',
		'two_column_split',
		'video',
		'wpforms',
	);
	$modes               = array();

	foreach ( $single_mode_layouts as $layout_name ) {
		$modes[ $layout_name ] = array(
			'default' => array(
				'label' => __( 'Default', 'mrn-base-stack' ),
			),
		);
	}

	$modes['logos'] = array(
		'grid'   => array(
			'label' => __( 'Grid', 'mrn-base-stack' ),
		),
		'slider' => array(
			'label' => __( 'Slider', 'mrn-base-stack' ),
		),
	);

	return $modes;
}

/**
 * Get display modes for one builder layout.
 *
 * @param string $layout_name Builder layout name.
 * @return array<string, array<string, mixed>>
 */
function mrn_base_stack_get_builder_layout_display_modes( $layout_name ) {
	$layout_name = sanitize_key( (string) $layout_name );
	$modes       = mrn_base_stack_get_default_builder_layout_display_modes();

	/**
	 * Filter builder layout display modes.
	 *
	 * @param array<string, array<string, array<string, mixed>>> $modes Display modes by layout.
	 */
	$modes = apply_filters( 'mrn_base_stack_builder_layout_display_modes', $modes );

	if ( ! is_array( $modes ) || ! isset( $modes[ $layout_name ] ) || ! is_array( $modes[ $layout_name ] ) ) {
		return array();
	}

	return $modes[ $layout_name ];
}

/**
 * Get display-mode choices for one builder layout.
 *
 * @param string $layout_name Builder layout name.
 * @return array<string, string>
 */
function mrn_base_stack_get_builder_layout_display_mode_choices( $layout_name ) {
	$choices = array();

	foreach ( mrn_base_stack_get_builder_layout_display_modes( $layout_name ) as $mode_key => $mode_config ) {
		$mode_key = sanitize_key( (string) $mode_key );
		$label    = isset( $mode_config['label'] ) ? trim( (string) $mode_config['label'] ) : '';

		if ( '' === $mode_key || '' === $label ) {
			continue;
		}

		$choices[ $mode_key ] = $label;
	}

	return $choices;
}

/**
 * Normalize a builder layout display mode.
 *
 * @param string $mode        Candidate mode.
 * @param string $layout_name Builder layout name.
 * @return string
 */
function mrn_base_stack_normalize_builder_layout_display_mode( $mode, $layout_name ) {
	$mode    = sanitize_key( (string) $mode );
	$choices = mrn_base_stack_get_builder_layout_display_mode_choices( $layout_name );

	if ( '' !== $mode && isset( $choices[ $mode ] ) ) {
		return $mode;
	}

	$first_mode = array_key_first( $choices );

	return is_string( $first_mode ) ? $first_mode : '';
}

/**
 * Determine whether a display style supports a display mode.
 *
 * @param array<string, mixed> $style_config Style config.
 * @param string               $display_mode Display mode key.
 * @return bool
 */
function mrn_base_stack_display_style_supports_mode( array $style_config, $display_mode ) {
	$display_mode = sanitize_key( (string) $display_mode );

	if ( empty( $style_config['display_modes'] ) || ! is_array( $style_config['display_modes'] ) ) {
		return true;
	}

	$style_modes = array();
	foreach ( $style_config['display_modes'] as $style_mode ) {
		$style_mode = is_scalar( $style_mode ) ? trim( (string) $style_mode ) : '';

		if ( '*' === $style_mode ) {
			$style_modes[] = '*';
			continue;
		}

		$style_mode = sanitize_key( $style_mode );
		if ( '' !== $style_mode ) {
			$style_modes[] = $style_mode;
		}
	}

	return in_array( '*', $style_modes, true ) || in_array( $display_mode, $style_modes, true );
}

/**
 * Get display-style configs for one builder layout.
 *
 * @param string $layout_name  Builder layout name.
 * @param string $display_mode Optional mode filter.
 * @return array<string, array<string, mixed>>
 */
function mrn_base_stack_get_builder_layout_display_styles( $layout_name, $display_mode = '' ) {
	$layout_name  = sanitize_key( (string) $layout_name );
	$display_mode = sanitize_key( (string) $display_mode );
	$styles       = mrn_base_stack_get_display_styles_for_entity( 'builder_layout', $layout_name );

	if ( '' === $display_mode ) {
		return $styles;
	}

	foreach ( $styles as $style_key => $style_config ) {
		if ( ! is_array( $style_config ) || ! mrn_base_stack_display_style_supports_mode( $style_config, $display_mode ) ) {
			unset( $styles[ $style_key ] );
		}
	}

	return $styles;
}

/**
 * Get display-style choices for one builder layout.
 *
 * @param string $layout_name  Builder layout name.
 * @param string $display_mode Optional mode filter.
 * @return array<string, string>
 */
function mrn_base_stack_get_builder_layout_display_style_choices( $layout_name, $display_mode = '' ) {
	$choices = array();

	foreach ( mrn_base_stack_get_builder_layout_display_styles( $layout_name, $display_mode ) as $style_key => $style_config ) {
		$style_key = sanitize_key( (string) $style_key );
		$label     = isset( $style_config['label'] ) ? trim( (string) $style_config['label'] ) : '';

		if ( '' === $style_key || '' === $label ) {
			continue;
		}

		$choices[ $style_key ] = $label;
	}

	return $choices;
}

/**
 * Normalize a builder layout display style.
 *
 * @param string $style        Candidate style.
 * @param string $layout_name  Builder layout name.
 * @param string $display_mode Optional mode filter.
 * @param string $fallback     Fallback style.
 * @return string
 */
function mrn_base_stack_normalize_builder_layout_display_style( $style, $layout_name, $display_mode = '', $fallback = 'default' ) {
	$style    = sanitize_key( (string) $style );
	$fallback = sanitize_key( (string) $fallback );
	$choices  = mrn_base_stack_get_builder_layout_display_style_choices( $layout_name, $display_mode );

	if ( '' !== $style && isset( $choices[ $style ] ) ) {
		return $style;
	}

	if ( '' !== $fallback && isset( $choices[ $fallback ] ) ) {
		return $fallback;
	}

	$first_style = array_key_first( $choices );

	return is_string( $first_style ) ? $first_style : '';
}

/**
 * Get the front-end display contract for a builder layout row.
 *
 * @param array<string, mixed> $row         Builder row.
 * @param string               $layout_name Builder layout name.
 * @return array{display_mode:string,display_style:string,classes:array<int,string>,attributes:array<string,string>}
 */
function mrn_base_stack_get_builder_display_contract( array $row, $layout_name ) {
	$layout_name   = sanitize_key( (string) $layout_name );
	$display_mode  = mrn_base_stack_normalize_builder_layout_display_mode( $row['display_mode'] ?? '', $layout_name );
	$display_style = mrn_base_stack_normalize_builder_layout_display_style( $row['display_style'] ?? '', $layout_name, $display_mode, 'default' );
	$classes       = array();
	$attributes    = array();

	if ( '' !== $display_mode ) {
		$classes[]                       = 'mrn-content-builder__row--display-mode-' . sanitize_html_class( $display_mode );
		$classes[]                       = 'mrn-content-builder__row--' . sanitize_html_class( $layout_name ) . '-mode-' . sanitize_html_class( $display_mode );
		$attributes['data-display-mode'] = $display_mode;
	}

	if ( '' !== $display_style ) {
		$classes[]                        = 'mrn-content-builder__row--display-style-' . sanitize_html_class( $display_style );
		$classes[]                        = 'mrn-content-builder__row--' . sanitize_html_class( $layout_name ) . '-style-' . sanitize_html_class( $display_style );
		$attributes['data-display-style'] = $display_style;
	}

	$contract = array(
		'display_mode'  => $display_mode,
		'display_style' => $display_style,
		'classes'       => $classes,
		'attributes'    => $attributes,
	);

	/**
	 * Filter the builder row display contract.
	 *
	 * @param array<string, mixed> $contract    Display contract.
	 * @param array<string, mixed> $row         Builder row.
	 * @param string               $layout_name Builder layout name.
	 */
	return (array) apply_filters( 'mrn_base_stack_builder_display_contract', $contract, $row, $layout_name );
}
