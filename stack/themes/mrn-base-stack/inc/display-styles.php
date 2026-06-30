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

		$style_entity_type    = isset( $style_config['entity_type'] ) ? sanitize_key( (string) $style_config['entity_type'] ) : 'post_type';
		$style_entity_subtype = isset( $style_config['entity_subtype'] ) ? sanitize_key( (string) $style_config['entity_subtype'] ) : '';

		if ( $style_entity_type !== $entity_type || $style_entity_subtype !== $entity_subtype ) {
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
