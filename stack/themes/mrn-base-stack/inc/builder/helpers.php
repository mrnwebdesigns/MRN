<?php

/**

 * Builder helpers and nested layout definitions.

 *

 * @package mrn-base-stack

 */

function mrn_base_stack_get_builder_row_context( array $row, $post_id, $index ) {
	return array(
		'row'     => $row,
		'post_id' => (int) $post_id,
		'index'   => (int) $index,
	);
}

/**
 * Clone an ACF layout tree while making every `key` unique.
 *
 * @param array<string, mixed> $value ACF layout or field tree.
 * @param string               $prefix Prefix to prepend to each ACF key.
 * @return array<string, mixed>
 */
function mrn_base_stack_clone_acf_keys_with_prefix( array $value, $prefix ) {
	foreach ( $value as $item_key => $item_value ) {
		if ( 'key' === $item_key && is_string( $item_value ) ) {
			$value[ $item_key ] = $prefix . $item_value;
			continue;
		}

		if ( is_array( $item_value ) ) {
			$value[ $item_key ] = mrn_base_stack_clone_acf_keys_with_prefix( $item_value, $prefix );
		}
	}

	return $value;
}

/**
 * Shared section-width choices for theme-owned builder layouts.
 *
 * @return array<string, string>
 */
function mrn_base_stack_get_section_width_choices() {
	return array(
		'content'    => 'Content',
		'wide'       => 'Wide',
		'full-width' => 'Full Width',
	);
}

/**
 * Build a standard section-width ACF field definition.
 *
 * @param string $key Unique ACF field key.
 * @param string $name Field name.
 * @param string $default Default width choice.
 * @param string $label Field label.
 * @return array<string, mixed>
 */
function mrn_base_stack_get_section_width_field( $key, $name = 'section_width', $default = 'wide', $label = 'Section Width' ) {
	return array(
		'key'           => $key,
		'label'         => $label,
		'name'          => $name,
		'aria-label'    => '',
		'type'          => 'select',
		'choices'       => mrn_base_stack_get_section_width_choices(),
		'default_value' => $default,
		'ui'            => 1,
		'wrapper'       => array(
			'width' => '50',
		),
	);
}

/**
 * Shared motion effect choices for builder layouts.
 *
 * @return array<string, string>
 */
function mrn_base_stack_get_motion_effect_choices() {
	return array(
		'surface'          => 'Switch Light/Dark Surface',
		'active-class'     => 'Mark Row As Active',
		'dark-scroll-card' => 'Darken Card On Scroll',
	);
}

/**
 * Get beginner-friendly motion preset choices for a supported effect.
 *
 * @param string $effect Effect key.
 * @return array<string, string>
 */
function mrn_base_stack_get_motion_preset_choices( $effect ) {
	$effect = sanitize_key( (string) $effect );

	if ( 'dark-scroll-card' === $effect ) {
		if ( function_exists( 'mrn_site_styles_get_dark_scroll_card_preset_choices' ) ) {
			return mrn_site_styles_get_dark_scroll_card_preset_choices();
		}

		return array(
			'' => 'Default Dark Card',
		);
	}

	return array(
		'' => 'Default',
	);
}

/**
 * Shared beginner-friendly trigger choices for motion effects.
 *
 * @return array<string, string>
 */
function mrn_base_stack_get_motion_trigger_choices() {
	return array(
		'early'  => 'Early',
		'center' => 'Center',
		'late'   => 'Late',
	);
}

/**
 * Convert a stored trigger position into a Motion margin string.
 *
 * @param mixed $value Raw stored trigger value.
 * @return string
 */
function mrn_base_stack_get_motion_margin_for_trigger( $value ) {
	$trigger = is_string( $value ) ? sanitize_key( $value ) : '';

	if ( 'early' === $trigger ) {
		return '-20% 0px -20% 0px';
	}

	if ( 'late' === $trigger ) {
		return '-45% 0px -10% 0px';
	}

	return '-35% 0px -35% 0px';
}

/**
 * Build the standard motion-settings ACF group field definition.
 *
 * @param string $key Unique ACF field key.
 * @param string $name Field name.
 * @param string $label Field label.
 * @return array<string, mixed>
 */
function mrn_base_stack_get_motion_group_field( $key, $name = 'motion_settings', $label = 'Motion Effects' ) {
	$enabled_key   = $key . '_enabled';
	$effect_key    = $key . '_effect';
	$preset_key    = $key . '_preset';
	$surface_key   = $key . '_surface';
	$enabled_logic = array(
		array(
			array(
				'field'    => $enabled_key,
				'operator' => '==',
				'value'    => '1',
			),
		),
	);

	return array(
		'key'        => $key,
		'label'      => $label,
		'name'       => $name,
		'aria-label' => '',
		'type'       => 'group',
		'layout'     => 'block',
		'sub_fields' => array(
			array(
				'key'           => $enabled_key,
				'label'         => 'Enable Row Effects',
				'name'          => 'enabled',
				'aria-label'    => '',
				'type'          => 'true_false',
				'ui'            => 1,
				'default_value' => 0,
				'ui_on_text'    => 'On',
				'ui_off_text'   => 'Off',
				'wrapper'       => array(
					'width' => '33',
				),
			),
			array(
				'key'               => $effect_key,
				'label'             => 'Effect Style',
				'name'              => 'effect',
				'aria-label'        => '',
				'type'              => 'select',
				'choices'           => mrn_base_stack_get_motion_effect_choices(),
				'default_value'     => 'surface',
				'ui'                => 1,
				'wrapper'           => array(
					'width' => '34',
				),
				'conditional_logic' => $enabled_logic,
			),
			array(
				'key'               => $key . '_trigger_position',
				'label'             => 'Start Effect',
				'name'              => 'trigger_position',
				'aria-label'        => '',
				'type'              => 'select',
				'choices'           => mrn_base_stack_get_motion_trigger_choices(),
				'default_value'     => 'center',
				'ui'                => 1,
				'instructions'      => 'Choose where in the viewport the effect should become noticeable.',
				'wrapper'           => array(
					'width' => '33',
				),
				'conditional_logic' => $enabled_logic,
			),
			array(
				'key'               => $surface_key,
				'label'             => 'Surface Look',
				'name'              => 'surface',
				'aria-label'        => '',
				'type'              => 'select',
				'choices'           => array(
					'light' => 'Light',
					'dark'  => 'Dark',
				),
				'default_value'     => 'dark',
				'ui'                => 1,
				'wrapper'           => array(
					'width' => '50',
				),
				'conditional_logic' => array(
					array(
						array(
							'field'    => $enabled_key,
							'operator' => '==',
							'value'    => '1',
						),
						array(
							'field'    => $effect_key,
							'operator' => '==',
							'value'    => 'surface',
						),
					),
				),
			),
			array(
				'key'               => $preset_key,
				'label'             => 'Effect Preset',
				'name'              => 'preset',
				'aria-label'        => '',
				'type'              => 'select',
				'choices'           => mrn_base_stack_get_motion_preset_choices( 'dark-scroll-card' ),
				'default_value'     => '',
				'ui'                => 1,
				'instructions'      => 'Choose a saved visual preset from Site Styles.',
				'wrapper'           => array(
					'width' => '50',
				),
				'conditional_logic' => array(
					array(
						array(
							'field'    => $enabled_key,
							'operator' => '==',
							'value'    => '1',
						),
						array(
							'field'    => $effect_key,
							'operator' => '==',
							'value'    => 'dark-scroll-card',
						),
					),
				),
			),
		),
	);
}

/**
 * Normalize a raw section-width setting to a supported value.
 *
 * @param mixed  $value Raw stored value.
 * @param string $default Default width.
 * @return string
 */
function mrn_base_stack_normalize_section_width( $value, $default = 'wide' ) {
	$width = is_string( $value ) ? sanitize_key( $value ) : '';

	if ( in_array( $value, array( 1, '1', true, 'true' ), true ) ) {
		$width = 'full-width';
	}

	if ( ! in_array( $width, array( 'content', 'wide', 'full-width' ), true ) ) {
		$width = $default;
	}

	return $width;
}

/**
 * Convert a section-width setting into a shell modifier class.
 *
 * @param mixed  $value Raw stored value.
 * @param string $default Default width.
 * @return string
 */
function mrn_base_stack_get_section_width_class( $value, $default = 'wide' ) {
	$width = mrn_base_stack_normalize_section_width( $value, $default );

	if ( 'content' === $width ) {
		return 'mrn-shell-section--width-content';
	}

	if ( 'full-width' === $width ) {
		return 'mrn-shell-section--width-full';
	}

	return 'mrn-shell-section--width-wide';
}

/**
 * Resolve section-width UI choice into section and container layer classes.
 *
 * `content` and `wide` are container-width choices inside a contained section.
 * `full-width` is a full-bleed section with a layout-owned inner container.
 *
 * @param mixed  $value Raw stored value.
 * @param string $default Default width choice.
 * @param string $full_container_width Inner container width to use when the section is full bleed.
 * @return array{width:string,section_class:string,container_class:string}
 */
function mrn_base_stack_get_section_width_layers( $value, $default = 'wide', $full_container_width = 'wide' ) {
	$width                = mrn_base_stack_normalize_section_width( $value, $default );
	$full_container_width = mrn_base_stack_normalize_section_width( $full_container_width, 'wide' );

	$section_class = 'mrn-layout-section--contained';
	$container_map = array(
		'content'    => 'mrn-layout-container--content',
		'wide'       => 'mrn-layout-container--wide',
		'full-width' => 'mrn-layout-container--full',
	);
	$container_key = $width;

	if ( 'full-width' === $width ) {
		$section_class = 'mrn-layout-section--full';
		$container_key = $full_container_width;
	}

	return array(
		'width'           => $width,
		'section_class'   => $section_class,
		'container_class' => $container_map[ $container_key ] ?? $container_map['wide'],
	);
}

/**
 * Resolve a builder row width setting into the shell modifier class.
 *
 * Supports legacy boolean full-width fields when requested.
 *
 * @param array<string, mixed> $row Builder row data.
 * @param string               $default Default width choice.
 * @param string               $legacy_full_width_key Optional legacy field name.
 * @return string
 */
function mrn_base_stack_get_row_section_width_class( array $row, $default = 'wide', $legacy_full_width_key = '' ) {
	$value = $row['section_width'] ?? '';

	if ( '' === $value && '' !== $legacy_full_width_key && ! empty( $row[ $legacy_full_width_key ] ) ) {
		$value = 'full-width';
	}

	return mrn_base_stack_get_section_width_class( $value, $default );
}

/**
 * Get the standard accent contract for a builder section.
 *
 * @param bool   $enabled Whether the bottom accent is enabled.
 * @param string $accent_slug Optional accent style slug.
 * @return array{classes:array<int,string>,attributes:array<string,string>}
 */
function mrn_base_stack_get_builder_accent_contract( $enabled, $accent_slug = '' ) {
	if ( function_exists( 'mrn_site_styles_get_bottom_accent_contract' ) ) {
		$contract = mrn_site_styles_get_bottom_accent_contract( (bool) $enabled, (string) $accent_slug );
		$classes  = isset( $contract['classes'] ) && is_array( $contract['classes'] ) ? array_values( $contract['classes'] ) : array();
		$attrs    = isset( $contract['attributes'] ) && is_array( $contract['attributes'] ) ? $contract['attributes'] : array();

		return array(
			'classes'    => $classes,
			'attributes' => $attrs,
		);
	}

	return array(
		'classes'    => $enabled ? array( 'has-bottom-accent' ) : array(),
		'attributes' => array(),
	);
}

/**
 * Append accent classes to a builder section class list.
 *
 * @param array<int, string>                 $classes Existing section classes.
 * @param array{classes?:array<int,string>}  $accent_contract Accent contract array.
 * @return array<int, string>
 */
function mrn_base_stack_merge_builder_section_classes( array $classes, array $accent_contract ) {
	if ( ! empty( $accent_contract['classes'] ) && is_array( $accent_contract['classes'] ) ) {
		$classes = array_merge( $classes, $accent_contract['classes'] );
	}

	return array_values( array_unique( array_filter( $classes, 'strlen' ) ) );
}

/**
 * Merge a builder attribute contract into an existing attribute map.
 *
 * @param array<string, string> $attributes Existing attributes.
 * @param array<string, string> $extra_attributes Additional attributes.
 * @return array<string, string>
 */
function mrn_base_stack_merge_builder_attributes( array $attributes, array $extra_attributes ) {
	foreach ( $extra_attributes as $attribute_name => $attribute_value ) {
		$attribute_name  = is_string( $attribute_name ) ? trim( $attribute_name ) : '';
		$attribute_value = is_scalar( $attribute_value ) ? trim( (string) $attribute_value ) : '';

		if ( '' === $attribute_name || '' === $attribute_value ) {
			continue;
		}

		$attributes[ $attribute_name ] = $attribute_value;
	}

	return $attributes;
}

/**
 * Normalize a builder motion-settings payload.
 *
 * @param mixed $value Raw motion settings.
 * @return array<string, string|bool>
 */
function mrn_base_stack_normalize_motion_settings( $value ) {
	$settings = is_array( $value ) ? $value : array();

	return array(
		'enabled'          => ! empty( $settings['enabled'] ),
		'effect'           => sanitize_key( (string) ( $settings['effect'] ?? '' ) ),
		'preset'           => sanitize_key( (string) ( $settings['preset'] ?? '' ) ),
		'trigger_position' => sanitize_key( (string) ( $settings['trigger_position'] ?? '' ) ),
		'surface'          => sanitize_key( (string) ( $settings['surface'] ?? '' ) ),
		'active_class'     => sanitize_html_class( (string) ( $settings['active_class'] ?? '' ) ),
		'margin'           => is_string( $settings['margin'] ?? null ) ? trim( $settings['margin'] ) : '',
	);
}

/**
 * Build the motion contract for a builder row.
 *
 * @param array<string, mixed> $row Builder row data.
 * @return array{classes:array<int,string>,attributes:array<string,string>}
 */
function mrn_base_stack_get_builder_motion_contract( array $row ) {
	$settings = mrn_base_stack_normalize_motion_settings( $row['motion_settings'] ?? array() );

	if ( empty( $settings['enabled'] ) ) {
		return array(
			'classes'    => array(),
			'attributes' => array(),
		);
	}

	$effect = $settings['effect'];
	$margin = '' !== $settings['margin'] ? $settings['margin'] : mrn_base_stack_get_motion_margin_for_trigger( $settings['trigger_position'] ?? '' );

	if ( 'surface' === $effect ) {
		$surface = $settings['surface'];

		if ( ! in_array( $surface, array( 'light', 'dark' ), true ) ) {
			$surface = 'dark';
		}

		return array(
			'classes'    => array(),
			'attributes' => array(
				'data-mrn-surface'        => $surface,
				'data-mrn-surface-margin' => $margin,
			),
		);
	}

	if ( 'active-class' === $effect ) {
		$active_class = 'is-mrn-in-view';

		return array(
			'classes'    => array( 'mrn-motion-effect--active-class' ),
			'attributes' => array(
				'data-mrn-motion-effect' => 'active-class',
				'data-mrn-motion-class'  => $active_class,
				'data-mrn-motion-margin' => $margin,
			),
		);
	}

	if ( 'dark-scroll-card' === $effect ) {
		$preset = $settings['preset'];

		return array(
			'classes'    => array( 'mrn-motion-effect--dark-scroll-card' ),
			'attributes' => array(
				'data-mrn-motion-effect' => 'dark-scroll-card',
				'data-mrn-effect-preset' => $preset,
				'data-mrn-motion-margin' => $margin,
			),
		);
	}

	return array(
		'classes'    => array(),
		'attributes' => array(),
	);
}

/**
 * Convert an array of CSS declarations into a style attribute value.
 *
 * @param array<int, string> $styles CSS declarations.
 * @return string
 */
function mrn_base_stack_get_inline_style_attribute( array $styles ) {
	$styles = array_values(
		array_filter(
			array_map( 'trim', $styles ),
			'strlen'
		)
	);

	return implode( '; ', $styles );
}

/**
 * Convert an associative array into escaped HTML attributes.
 *
 * @param array<string, scalar> $attributes Associative attribute map.
 * @return string
 */
function mrn_base_stack_get_html_attributes( array $attributes ) {
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
 * Shared HTML tag choices for heading-style text fields.
 *
 * @return array<string, string>
 */
function mrn_base_stack_get_text_tag_choices() {
	return array(
		'h1'   => 'H1',
		'h2'   => 'H2',
		'h3'   => 'H3',
		'h4'   => 'H4',
		'h5'   => 'H5',
		'h6'   => 'H6',
		'p'    => 'Paragraph',
		'span' => 'Span',
		'div'  => 'Div',
	);
}

/**
 * Normalize a requested HTML tag to the supported text-tag set.
 *
 * @param mixed  $value Raw tag value.
 * @param string $default Default tag value.
 * @return string
 */
function mrn_base_stack_normalize_text_tag( $value, $default = 'p' ) {
	$tag          = is_string( $value ) ? sanitize_key( $value ) : '';
	$default_tag  = is_string( $default ) ? sanitize_key( $default ) : 'p';
	$allowed_tags = array_keys( mrn_base_stack_get_text_tag_choices() );

	if ( ! in_array( $default_tag, $allowed_tags, true ) ) {
		$default_tag = 'p';
	}

	if ( ! in_array( $tag, $allowed_tags, true ) ) {
		$tag = $default_tag;
	}

	return $tag;
}

/**
 * Build a standard label-tag ACF field definition.
 *
 * @param string $key Unique ACF field key.
 * @param string $name Field name.
 * @param string $default Default tag choice.
 * @param string $label Field label.
 * @return array<string, mixed>
 */
function mrn_base_stack_get_label_tag_field( $key, $name = 'label_tag', $default = 'p', $label = 'HTML Tag for Label' ) {
	return array(
		'key'           => $key,
		'label'         => $label,
		'name'          => $name,
		'aria-label'    => '',
		'type'          => 'select',
		'choices'       => mrn_base_stack_get_text_tag_choices(),
		'default_value' => mrn_base_stack_normalize_text_tag( $default, 'p' ),
		'ui'            => 1,
		'wrapper'       => array(
			'width' => '25',
		),
	);
}

/**
 * Allow a small, intentional inline HTML subset for heading-style fields.
 *
 * @param string $value Raw heading text value.
 * @return string
 */
function mrn_base_stack_format_heading_inline_html( $value ) {
	$allowed_tags = array(
		'span'   => array(
			'class' => true,
		),
		'strong' => array(),
		'em'     => array(),
		'br'     => array(),
	);

	return wp_kses( (string) $value, $allowed_tags );
}

/**
 * Build a CSS custom-property declaration for a selected background image.
 *
 * @param mixed  $image ACF image field value.
 * @param string $css_var CSS custom property name.
 * @return string
 */
function mrn_base_stack_get_background_image_style( $image, $css_var ) {
	$image_url = '';

	if ( is_array( $image ) && ! empty( $image['url'] ) ) {
		$image_url = (string) $image['url'];
	} elseif ( is_string( $image ) ) {
		$image_url = $image;
	}

	$image_url = trim( $image_url );
	$css_var   = trim( (string) $css_var );

	if ( '' === $image_url || '' === $css_var ) {
		return '';
	}

	return $css_var . ": url('" . esc_url_raw( $image_url ) . "')";
}

/**
 * Normalize a YouTube or Vimeo URL into an embed URL.
 *
 * @param mixed                $url Raw video field value.
 * @param array<string, mixed> $options Embed behavior options.
 * @return array{provider:string,embed_url:string}
 */
function mrn_base_stack_get_video_embed( $url, array $options = array() ) {
	$raw_url = is_string( $url ) ? trim( $url ) : '';
	$options = wp_parse_args(
		$options,
		array(
			'autoplay' => false,
			'muted'    => false,
			'loop'     => false,
			'controls' => true,
			'background' => false,
		)
	);

	if ( '' === $raw_url ) {
		return array(
			'provider'  => '',
			'embed_url' => '',
		);
	}

	$sanitized_url = esc_url_raw( $raw_url );
	if ( '' === $sanitized_url ) {
		return array(
			'provider'  => '',
			'embed_url' => '',
		);
	}

	if ( preg_match( '~(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{11})~', $sanitized_url, $matches ) ) {
		$video_id = $matches[1];
		$query    = array(
			'autoplay'       => ! empty( $options['autoplay'] ) ? '1' : '0',
			'mute'           => ! empty( $options['muted'] ) ? '1' : '0',
			'controls'       => ! empty( $options['controls'] ) ? '1' : '0',
			'loop'           => ! empty( $options['loop'] ) ? '1' : '0',
			'playlist'       => ! empty( $options['loop'] ) ? $video_id : '',
			'playsinline'    => '1',
			'rel'            => '0',
			'modestbranding' => '1',
		);

		return array(
			'provider'  => 'youtube',
			'embed_url' => sprintf( 'https://www.youtube.com/embed/%s?%s', rawurlencode( $video_id ), http_build_query( array_filter( $query, 'strlen' ), '', '&', PHP_QUERY_RFC3986 ) ),
		);
	}

	if ( preg_match( '~vimeo\.com/(?:video/)?([0-9]+)~', $sanitized_url, $matches ) ) {
		$video_id = $matches[1];
		$query    = array(
			'autoplay'  => ! empty( $options['autoplay'] ) ? '1' : '0',
			'muted'     => ! empty( $options['muted'] ) ? '1' : '0',
			'loop'      => ! empty( $options['loop'] ) ? '1' : '0',
			'background'=> ! empty( $options['background'] ) ? '1' : '0',
			'autopause' => ! empty( $options['background'] ) ? '0' : '1',
			'controls'  => ! empty( $options['controls'] ) ? '1' : '0',
			'byline'    => '0',
			'title'     => '0',
		);

		return array(
			'provider'  => 'vimeo',
			'embed_url' => sprintf( 'https://player.vimeo.com/video/%s?%s', rawurlencode( $video_id ), http_build_query( array_filter( $query, 'strlen' ), '', '&', PHP_QUERY_RFC3986 ) ),
		);
	}

	return array(
		'provider'  => '',
		'embed_url' => '',
	);
}

/**
 * Nested layouts available inside the Two Column Split builder row.
 *
 * @return array<string, array<string, mixed>>
 */
function mrn_base_stack_get_two_column_nested_layouts() {
	return array(
		'layout_mrn_nested_body_text'      => array(
			'key'        => 'layout_mrn_nested_body_text',
			'name'       => 'body_text',
			'label'      => 'Body Text',
			'display'    => 'block',
			'sub_fields' => array(
				array(
					'key'          => 'field_mrn_nested_body_text_content',
					'label'        => 'Body Text',
					'name'         => 'body_text',
					'aria-label'   => '',
					'type'         => 'wysiwyg',
					'tabs'         => 'all',
					'toolbar'      => 'full',
					'media_upload' => 1,
					'delay'        => 0,
				),
				array(
					'key'           => 'field_mrn_nested_body_text_bottom_accent',
					'label'         => 'Bottom Accent',
					'name'          => 'bottom_accent',
					'aria-label'    => '',
					'type'          => 'true_false',
					'ui'            => 1,
					'default_value' => 0,
					'ui_on_text'    => 'On',
					'ui_off_text'   => 'Off',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_body_text_bottom_accent_style',
					'label'         => 'Bottom Accent Style',
					'name'          => 'bottom_accent_style',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => function_exists( 'mrn_site_styles_get_graphic_element_choices' ) ? mrn_site_styles_get_graphic_element_choices() : array( '' => 'Select a Graphic Element' ),
					'default_value' => '',
					'ui'            => 1,
					'allow_null'    => 1,
					'instructions'  => 'Choose a saved graphic element from Site Styles.',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				mrn_base_stack_get_motion_group_field( 'field_mrn_nested_body_text_motion_settings' ),
			),
		),
		'layout_mrn_nested_basic'          => array(
			'key'        => 'layout_mrn_nested_basic',
			'name'       => 'basic',
			'label'      => 'Basic - label|title|text with editor|image|link',
			'display'    => 'block',
			'sub_fields' => array(
				array(
					'key'        => 'field_mrn_nested_basic_content_tab',
					'label'      => 'Content',
					'name'       => '',
					'aria-label' => '',
					'type'       => 'tab',
					'placement'  => 'top',
				),
				array(
					'key'           => 'field_mrn_nested_basic_label',
					'label'         => 'Label',
					'name'          => 'label',
					'aria-label'    => '',
					'type'          => 'text',
					'instructions'  => 'Limited inline HTML allowed: span, strong, em, br.',
					'wrapper'       => array(
						'width' => '75',
					),
				),
				mrn_base_stack_get_label_tag_field( 'field_mrn_nested_basic_label_tag' ),
				array(
					'key'           => 'field_mrn_nested_basic_heading',
					'label'         => 'Title field',
					'name'          => 'text_field',
					'aria-label'    => '',
					'type'          => 'text',
					'instructions'  => 'Limited inline HTML allowed: span, strong, em, br.',
					'wrapper'       => array(
						'width' => '75',
					),
				),
				array(
					'key'           => 'field_mrn_nested_basic_heading_tag',
					'label'         => 'HTML Tag for Text Field',
					'name'          => 'text_field_tag',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => array(
						'h1'   => 'H1',
						'h2'   => 'H2',
						'h3'   => 'H3',
						'h4'   => 'H4',
						'h5'   => 'H5',
						'h6'   => 'H6',
						'p'    => 'Paragraph',
						'span' => 'Span',
						'div'  => 'Div',
					),
					'default_value' => 'h2',
					'ui'            => 1,
					'wrapper'       => array(
						'width' => '25',
					),
				),
				array(
					'key'          => 'field_mrn_nested_basic_content',
					'label'        => 'Text area with editor',
					'name'         => 'content',
					'aria-label'   => '',
					'type'         => 'wysiwyg',
					'tabs'         => 'all',
					'toolbar'      => 'full',
					'media_upload' => 1,
					'delay'        => 0,
				),
				array(
					'key'           => 'field_mrn_nested_basic_image',
					'label'         => 'Image',
					'name'          => 'image',
					'aria-label'    => '',
					'type'          => 'image',
					'return_format' => 'array',
					'preview_size'  => 'medium',
					'library'       => 'all',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_basic_link',
					'label'         => 'Link',
					'name'          => 'link',
					'aria-label'    => '',
					'type'          => 'link',
					'return_format' => 'array',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'        => 'field_mrn_nested_basic_config_tab',
					'label'      => 'Configs',
					'name'       => '',
					'aria-label' => '',
					'type'       => 'tab',
					'placement'  => 'top',
					'endpoint'   => 0,
				),
				array(
					'key'           => 'field_mrn_nested_basic_link_style',
					'label'         => 'Link style',
					'name'          => 'link_style',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => function_exists( 'mrn_rbl_get_link_style_choices' )
						? mrn_rbl_get_link_style_choices()
						: array(
							'link'   => 'Link',
							'button' => 'Button',
						),
					'default_value' => 'link',
					'ui'            => 1,
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_basic_link_color',
					'label'         => 'Link color',
					'name'          => 'link_color',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => function_exists( 'mrn_rbl_get_site_color_choices' ) ? mrn_rbl_get_site_color_choices() : array(),
					'ui'            => 1,
					'allow_null'    => 1,
					'instructions'  => 'Select from Site Colors when available.',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_basic_image_placement',
					'label'         => 'Image placement',
					'name'          => 'image_placement',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => array(
						'left'  => 'Left',
						'right' => 'Right',
					),
					'default_value' => 'left',
					'ui'            => 1,
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_basic_background_color',
					'label'         => 'Background color',
					'name'          => 'background_color',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => function_exists( 'mrn_rbl_get_site_color_choices' ) ? mrn_rbl_get_site_color_choices() : array(),
					'ui'            => 1,
					'allow_null'    => 1,
					'instructions'  => 'Select from Site Colors when available.',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_basic_background_image',
					'label'         => 'Background image',
					'name'          => 'background_image',
					'aria-label'    => '',
					'type'          => 'image',
					'return_format' => 'array',
					'preview_size'  => 'medium',
					'library'       => 'all',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_basic_bottom_accent',
					'label'         => 'Accent',
					'name'          => 'bottom_accent',
					'aria-label'    => '',
					'type'          => 'true_false',
					'ui'            => 1,
					'default_value' => 0,
					'ui_on_text'    => 'On',
					'ui_off_text'   => 'Off',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_basic_bottom_accent_style',
					'label'         => 'Accent Style',
					'name'          => 'bottom_accent_style',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => function_exists( 'mrn_site_styles_get_graphic_element_choices' ) ? mrn_site_styles_get_graphic_element_choices() : array( '' => 'Select a Graphic Element' ),
					'default_value' => '',
					'ui'            => 1,
					'allow_null'    => 1,
					'instructions'  => 'Choose a saved graphic element from Site Styles.',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				mrn_base_stack_get_motion_group_field( 'field_mrn_nested_basic_motion_settings' ),
			),
		),
		'layout_mrn_nested_card'           => array(
			'key'        => 'layout_mrn_nested_card',
			'name'       => 'card',
			'label'      => 'Card - image|text|link',
			'display'    => 'block',
			'sub_fields' => array(
				array(
					'key'        => 'field_mrn_nested_card_content_tab',
					'label'      => 'Content',
					'name'       => '',
					'aria-label' => '',
					'type'       => 'tab',
					'placement'  => 'top',
				),
				array(
					'key'           => 'field_mrn_nested_card_heading',
					'label'         => 'Text Field',
					'name'          => 'text_field',
					'aria-label'    => '',
					'type'          => 'text',
					'instructions'  => 'Limited inline HTML allowed: span, strong, em, br.',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_card_heading_tag',
					'label'         => 'HTML Tag for Text Field',
					'name'          => 'text_field_tag',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => array(
						'h1'   => 'H1',
						'h2'   => 'H2',
						'h3'   => 'H3',
						'h4'   => 'H4',
						'h5'   => 'H5',
						'h6'   => 'H6',
						'p'    => 'Paragraph',
						'span' => 'Span',
						'div'  => 'Div',
					),
					'default_value' => 'h2',
					'ui'            => 1,
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'          => 'field_mrn_nested_card_items',
					'label'        => 'Cards',
					'name'         => 'card_items',
					'aria-label'   => '',
					'type'         => 'repeater',
					'layout'       => 'row',
					'collapsed'    => 'field_mrn_nested_card_item_text',
					'button_label' => 'Add Card',
					'min'          => 1,
					'sub_fields'   => array(
						array(
							'key'           => 'field_mrn_nested_card_item_image',
							'label'         => 'Image',
							'name'          => 'image',
							'aria-label'    => '',
							'type'          => 'image',
							'return_format' => 'array',
							'preview_size'  => 'medium',
							'library'       => 'all',
							'wrapper'       => array(
								'width' => '33',
							),
						),
						array(
							'key'          => 'field_mrn_nested_card_item_text',
							'label'        => 'Text',
							'name'         => 'text',
							'aria-label'   => '',
							'type'         => 'wysiwyg',
							'tabs'         => 'all',
							'toolbar'      => 'full',
							'media_upload' => 1,
							'delay'        => 0,
							'wrapper'      => array(
								'width' => '34',
							),
						),
						array(
							'key'           => 'field_mrn_nested_card_item_link',
							'label'         => 'Link',
							'name'          => 'link',
							'aria-label'    => '',
							'type'          => 'link',
							'return_format' => 'array',
							'wrapper'       => array(
								'width' => '33',
							),
						),
					),
				),
				array(
					'key'           => 'field_mrn_nested_card_link',
					'label'         => 'Link',
					'name'          => 'link',
					'aria-label'    => '',
					'type'          => 'link',
					'return_format' => 'array',
				),
				array(
					'key'        => 'field_mrn_nested_card_config_tab',
					'label'      => 'Configs',
					'name'       => '',
					'aria-label' => '',
					'type'       => 'tab',
					'placement'  => 'top',
				),
				array(
					'key'           => 'field_mrn_nested_card_background_color',
					'label'         => 'Background Color',
					'name'          => 'background_color',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => function_exists( 'mrn_rbl_get_site_color_choices' ) ? mrn_rbl_get_site_color_choices() : array(),
					'ui'            => 1,
					'allow_null'    => 1,
					'instructions'  => 'Select from Site Colors when available.',
				),
				array(
					'key'           => 'field_mrn_nested_card_bottom_accent',
					'label'         => 'Accent',
					'name'          => 'bottom_accent',
					'aria-label'    => '',
					'type'          => 'true_false',
					'ui'            => 1,
					'default_value' => 0,
					'ui_on_text'    => 'On',
					'ui_off_text'   => 'Off',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_card_bottom_accent_style',
					'label'         => 'Bottom Accent Style',
					'name'          => 'bottom_accent_style',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => function_exists( 'mrn_site_styles_get_graphic_element_choices' ) ? mrn_site_styles_get_graphic_element_choices() : array( '' => 'Select a Graphic Element' ),
					'default_value' => '',
					'ui'            => 1,
					'allow_null'    => 1,
					'instructions'  => 'Choose a saved graphic element from Site Styles.',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				mrn_base_stack_get_motion_group_field( 'field_mrn_nested_card_motion_settings' ),
			),
		),
		'layout_mrn_nested_cta'            => array(
			'key'        => 'layout_mrn_nested_cta',
			'name'       => 'cta',
			'label'      => 'CTA - label|title|text with editor|link',
			'display'    => 'block',
			'sub_fields' => array(
				array(
					'key'          => 'field_mrn_nested_cta_fields',
					'label'        => 'CTA',
					'name'         => '',
					'aria-label'   => '',
					'type'         => 'clone',
					'clone'        => array( 'group_mrn_reusable_cta' ),
					'display'      => 'seamless',
					'layout'       => 'block',
					'prefix_label' => 0,
					'prefix_name'  => 0,
				),
				mrn_base_stack_get_section_width_field( 'field_mrn_nested_cta_section_width' ),
				mrn_base_stack_get_motion_group_field( 'field_mrn_nested_cta_motion_settings' ),
			),
		),
		'layout_mrn_nested_grid'           => array(
			'key'        => 'layout_mrn_nested_grid',
			'name'       => 'grid',
			'label'      => 'Grid - label|title|repeater',
			'display'    => 'block',
			'sub_fields' => array(
				array(
					'key'          => 'field_mrn_nested_grid_fields',
					'label'        => 'Grid',
					'name'         => '',
					'aria-label'   => '',
					'type'         => 'clone',
					'clone'        => array( 'group_mrn_reusable_content_grid' ),
					'display'      => 'seamless',
					'layout'       => 'block',
					'prefix_label' => 0,
					'prefix_name'  => 0,
				),
				mrn_base_stack_get_section_width_field( 'field_mrn_nested_grid_section_width' ),
				mrn_base_stack_get_motion_group_field( 'field_mrn_nested_grid_motion_settings' ),
			),
		),
		'layout_mrn_nested_image_content'  => array(
			'key'        => 'layout_mrn_nested_image_content',
			'name'       => 'image_content',
			'label'      => 'Image - label|title|text with editor',
			'display'    => 'block',
			'sub_fields' => array(
				array(
					'key'           => 'field_mrn_nested_image_content_content_tab',
					'label'         => 'Content',
					'name'          => '',
					'aria-label'    => '',
					'type'          => 'tab',
					'placement'     => 'top',
				),
				array(
					'key'           => 'field_mrn_nested_image_content_image',
					'label'         => 'Image',
					'name'          => 'image',
					'aria-label'    => '',
					'type'          => 'image',
					'return_format' => 'array',
					'preview_size'  => 'medium',
					'library'       => 'all',
				),
				array(
					'key'           => 'field_mrn_nested_image_content_label',
					'label'         => 'Label',
					'name'          => 'label',
					'aria-label'    => '',
					'type'          => 'text',
					'instructions'  => 'Limited inline HTML allowed: span, strong, em, br.',
					'wrapper'       => array(
						'width' => '75',
					),
				),
				mrn_base_stack_get_label_tag_field( 'field_mrn_nested_image_content_label_tag' ),
				array(
					'key'           => 'field_mrn_nested_image_content_heading',
					'label'         => 'Title field',
					'name'          => 'text_field',
					'aria-label'    => '',
					'type'          => 'text',
					'instructions'  => 'Limited inline HTML allowed: span, strong, em, br.',
					'wrapper'       => array(
						'width' => '75',
					),
				),
				array(
					'key'           => 'field_mrn_nested_image_content_heading_tag',
					'label'         => 'HTML Tag for Text Field',
					'name'          => 'text_field_tag',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => array(
						'h1'   => 'H1',
						'h2'   => 'H2',
						'h3'   => 'H3',
						'h4'   => 'H4',
						'h5'   => 'H5',
						'h6'   => 'H6',
						'p'    => 'Paragraph',
						'span' => 'Span',
						'div'  => 'Div',
					),
					'default_value' => 'h2',
					'ui'            => 1,
					'wrapper'       => array(
						'width' => '25',
					),
				),
				array(
					'key'          => 'field_mrn_nested_image_content_copy',
					'label'        => 'Text area with editor',
					'name'         => 'content',
					'aria-label'   => '',
					'type'         => 'wysiwyg',
					'tabs'         => 'all',
					'toolbar'      => 'full',
					'media_upload' => 1,
					'delay'        => 0,
				),
				array(
					'key'        => 'field_mrn_nested_image_content_config_tab',
					'label'      => 'Configs',
					'name'       => '',
					'aria-label' => '',
					'type'       => 'tab',
					'placement'  => 'top',
					'endpoint'   => 0,
				),
				array(
					'key'           => 'field_mrn_nested_image_content_background_color',
					'label'         => 'Background color',
					'name'          => 'background_color',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => function_exists( 'mrn_rbl_get_site_color_choices' ) ? mrn_rbl_get_site_color_choices() : array(),
					'ui'            => 1,
					'allow_null'    => 1,
					'instructions'  => 'Select from Site Colors when available.',
				),
				array(
					'key'           => 'field_mrn_nested_image_content_bottom_accent',
					'label'         => 'Accent',
					'name'          => 'bottom_accent',
					'aria-label'    => '',
					'type'          => 'true_false',
					'ui'            => 1,
					'default_value' => 0,
					'ui_on_text'    => 'On',
					'ui_off_text'   => 'Off',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_image_content_bottom_accent_style',
					'label'         => 'Accent Style',
					'name'          => 'bottom_accent_style',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => function_exists( 'mrn_site_styles_get_graphic_element_choices' ) ? mrn_site_styles_get_graphic_element_choices() : array( '' => 'Select a Graphic Element' ),
					'default_value' => '',
					'ui'            => 1,
					'allow_null'    => 1,
					'instructions'  => 'Choose a saved graphic element from Site Styles.',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_image_content_full_width',
					'label'         => 'Full width',
					'name'          => 'full_width',
					'aria-label'    => '',
					'type'          => 'true_false',
					'ui'            => 1,
					'default_value' => 0,
					'ui_on_text'    => 'On',
					'ui_off_text'   => 'Off',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_image_content_position',
					'label'         => 'Image position',
					'name'          => 'image_position',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => array(
						'top'    => 'Top',
						'bottom' => 'Bottom',
					),
					'default_value' => 'top',
					'ui'            => 1,
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_image_content_size',
					'label'         => 'Image size',
					'name'          => 'image_size',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => array(
						'contained' => 'Contained',
						'cover'     => 'Cover',
					),
					'default_value' => 'contained',
					'ui'            => 1,
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_image_content_alignment',
					'label'         => 'Image alignment',
					'name'          => 'image_alignment',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => array(
						'left'   => 'Left',
						'center' => 'Center',
						'right'  => 'Right',
					),
					'default_value' => 'center',
					'ui'            => 1,
				),
				mrn_base_stack_get_motion_group_field( 'field_mrn_nested_image_content_motion_settings' ),
			),
		),
		'layout_mrn_nested_video'          => array(
			'key'        => 'layout_mrn_nested_video',
			'name'       => 'video',
			'label'      => 'Video - remote|upload',
			'display'    => 'block',
			'sub_fields' => array(
				array(
					'key'        => 'field_mrn_nested_video_content_tab',
					'label'      => 'Content',
					'name'       => '',
					'aria-label' => '',
					'type'       => 'tab',
					'placement'  => 'top',
				),
				array(
					'key'           => 'field_mrn_nested_video_label',
					'label'         => 'Label',
					'name'          => 'label',
					'aria-label'    => '',
					'type'          => 'text',
					'instructions'  => 'Limited inline HTML allowed: span, strong, em, br.',
					'wrapper'       => array(
						'width' => '75',
					),
				),
				mrn_base_stack_get_label_tag_field( 'field_mrn_nested_video_label_tag' ),
				array(
					'key'           => 'field_mrn_nested_video_heading',
					'label'         => 'Title field',
					'name'          => 'text_field',
					'aria-label'    => '',
					'type'          => 'text',
					'instructions'  => 'Limited inline HTML allowed: span, strong, em, br.',
					'wrapper'       => array(
						'width' => '75',
					),
				),
				array(
					'key'           => 'field_mrn_nested_video_heading_tag',
					'label'         => 'HTML Tag for Text Field',
					'name'          => 'text_field_tag',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => array(
						'h1'   => 'H1',
						'h2'   => 'H2',
						'h3'   => 'H3',
						'h4'   => 'H4',
						'h5'   => 'H5',
						'h6'   => 'H6',
						'p'    => 'Paragraph',
						'span' => 'Span',
						'div'  => 'Div',
					),
					'default_value' => 'h2',
					'ui'            => 1,
					'wrapper'       => array(
						'width' => '25',
					),
				),
				array(
					'key'          => 'field_mrn_nested_video_content',
					'label'        => 'Text area with editor',
					'name'         => 'content',
					'aria-label'   => '',
					'type'         => 'wysiwyg',
					'tabs'         => 'all',
					'toolbar'      => 'full',
					'media_upload' => 1,
					'delay'        => 0,
				),
				array(
					'key'          => 'field_mrn_nested_video_remote',
					'label'        => 'Remote video URL',
					'name'         => 'video_remote',
					'aria-label'   => '',
					'type'         => 'url',
					'instructions' => 'Paste a YouTube or Vimeo URL.',
					'wrapper'      => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_video_upload',
					'label'         => 'Video upload',
					'name'          => 'video_upload',
					'aria-label'    => '',
					'type'          => 'file',
					'return_format' => 'array',
					'library'       => 'all',
					'mime_types'    => 'mp4,webm,mov',
					'instructions'  => 'Optional local upload. When both upload and remote URL are set, the upload is used first.',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'        => 'field_mrn_nested_video_config_tab',
					'label'      => 'Configs',
					'name'       => '',
					'aria-label' => '',
					'type'       => 'tab',
					'placement'  => 'top',
					'endpoint'   => 0,
				),
				array(
					'key'           => 'field_mrn_nested_video_background_color',
					'label'         => 'Background color',
					'name'          => 'background_color',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => function_exists( 'mrn_rbl_get_site_color_choices' ) ? mrn_rbl_get_site_color_choices() : array(),
					'ui'            => 1,
					'allow_null'    => 1,
					'instructions'  => 'Select from Site Colors when available.',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_video_bottom_accent',
					'label'         => 'Accent',
					'name'          => 'bottom_accent',
					'aria-label'    => '',
					'type'          => 'true_false',
					'ui'            => 1,
					'default_value' => 0,
					'ui_on_text'    => 'On',
					'ui_off_text'   => 'Off',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_video_bottom_accent_style',
					'label'         => 'Accent Style',
					'name'          => 'bottom_accent_style',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => function_exists( 'mrn_site_styles_get_graphic_element_choices' ) ? mrn_site_styles_get_graphic_element_choices() : array( '' => 'Select a Graphic Element' ),
					'default_value' => '',
					'ui'            => 1,
					'allow_null'    => 1,
					'instructions'  => 'Choose a saved graphic element from Site Styles.',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				mrn_base_stack_get_motion_group_field( 'field_mrn_nested_video_motion_settings' ),
			),
		),
		'layout_mrn_nested_logos'          => array(
			'key'        => 'layout_mrn_nested_logos',
			'name'       => 'logos',
			'label'      => 'Logos - label|heading|image|link',
			'display'    => 'block',
			'sub_fields' => array(
				array(
					'key'        => 'field_mrn_nested_logos_content_tab',
					'label'      => 'Content',
					'name'       => '',
					'aria-label' => '',
					'type'       => 'tab',
					'placement'  => 'top',
				),
				array(
					'key'           => 'field_mrn_nested_logos_label',
					'label'         => 'Label',
					'name'          => 'label',
					'aria-label'    => '',
					'type'          => 'text',
					'instructions'  => 'Limited inline HTML allowed: span, strong, em, br.',
					'wrapper'       => array(
						'width' => '75',
					),
				),
				mrn_base_stack_get_label_tag_field( 'field_mrn_nested_logos_label_tag' ),
				array(
					'key'           => 'field_mrn_nested_logos_heading',
					'label'         => 'Heading',
					'name'          => 'heading',
					'aria-label'    => '',
					'type'          => 'text',
					'instructions'  => 'Limited inline HTML allowed: span, strong, em, br.',
					'wrapper'       => array(
						'width' => '75',
					),
				),
				array(
					'key'           => 'field_mrn_nested_logos_heading_tag',
					'label'         => 'HTML tag for heading',
					'name'          => 'heading_tag',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => array(
						'h1'   => 'H1',
						'h2'   => 'H2',
						'h3'   => 'H3',
						'h4'   => 'H4',
						'h5'   => 'H5',
						'h6'   => 'H6',
						'p'    => 'Paragraph',
						'span' => 'Span',
						'div'  => 'Div',
					),
					'default_value' => 'h2',
					'ui'            => 1,
					'wrapper'       => array(
						'width' => '25',
					),
				),
				array(
					'key'          => 'field_mrn_nested_logos_items',
					'label'        => 'Logos',
					'name'         => 'logo_items',
					'aria-label'   => '',
					'type'         => 'repeater',
					'layout'       => 'row',
					'button_label' => 'Add Logo',
					'min'          => 1,
					'sub_fields'   => array(
						array(
							'key'           => 'field_mrn_nested_logos_item_image',
							'label'         => 'Image',
							'name'          => 'image',
							'aria-label'    => '',
							'type'          => 'image',
							'return_format' => 'array',
							'preview_size'  => 'medium',
							'library'       => 'all',
							'wrapper'       => array(
								'width' => '50',
							),
						),
						array(
							'key'           => 'field_mrn_nested_logos_item_link',
							'label'         => 'Link',
							'name'          => 'link',
							'aria-label'    => '',
							'type'          => 'link',
							'return_format' => 'array',
							'wrapper'       => array(
								'width' => '50',
							),
						),
					),
				),
				array(
					'key'        => 'field_mrn_nested_logos_config_tab',
					'label'      => 'Configs',
					'name'       => '',
					'aria-label' => '',
					'type'       => 'tab',
					'placement'  => 'top',
					'endpoint'   => 0,
				),
				array(
					'key'           => 'field_mrn_nested_logos_display_mode',
					'label'         => 'Display mode',
					'name'          => 'display_mode',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => array(
						'grid'   => 'Grid',
						'slider' => 'Slider',
					),
					'default_value' => 'grid',
					'ui'            => 1,
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_logos_per_page',
					'label'         => 'Logos per row/view',
					'name'          => 'per_page',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => array(
						'3' => '3',
						'4' => '4',
						'5' => '5',
						'6' => '6',
					),
					'default_value' => '4',
					'ui'            => 1,
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_logos_arrows',
					'label'         => 'Show arrows',
					'name'          => 'show_arrows',
					'aria-label'    => '',
					'type'          => 'true_false',
					'ui'            => 1,
					'default_value' => 0,
					'ui_on_text'    => 'On',
					'ui_off_text'   => 'Off',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_logos_pagination',
					'label'         => 'Show pagination',
					'name'          => 'show_pagination',
					'aria-label'    => '',
					'type'          => 'true_false',
					'ui'            => 1,
					'default_value' => 0,
					'ui_on_text'    => 'On',
					'ui_off_text'   => 'Off',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_logos_autoplay',
					'label'         => 'Autoplay',
					'name'          => 'autoplay',
					'aria-label'    => '',
					'type'          => 'true_false',
					'ui'            => 1,
					'default_value' => 0,
					'ui_on_text'    => 'On',
					'ui_off_text'   => 'Off',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_logos_pause_on_hover',
					'label'         => 'Pause on hover',
					'name'          => 'pause_on_hover',
					'aria-label'    => '',
					'type'          => 'true_false',
					'ui'            => 1,
					'default_value' => 1,
					'ui_on_text'    => 'On',
					'ui_off_text'   => 'Off',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_logos_delay_start',
					'label'         => 'Delay start',
					'name'          => 'delay_start',
					'aria-label'    => '',
					'type'          => 'number',
					'default_value' => 0,
					'step'          => 0.1,
					'min'           => 0,
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_logos_delay_time',
					'label'         => 'Delay time',
					'name'          => 'delay_time',
					'aria-label'    => '',
					'type'          => 'number',
					'default_value' => 5,
					'step'          => 0.1,
					'min'           => 0,
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_logos_time_on_slide',
					'label'         => 'Time on slide',
					'name'          => 'time_on_slide',
					'aria-label'    => '',
					'type'          => 'number',
					'default_value' => 600,
					'step'          => 10,
					'min'           => 100,
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_logos_background_color',
					'label'         => 'Background color',
					'name'          => 'background_color',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => function_exists( 'mrn_rbl_get_site_color_choices' ) ? mrn_rbl_get_site_color_choices() : array(),
					'ui'            => 1,
					'allow_null'    => 1,
					'instructions'  => 'Select from Site Colors when available.',
				),
				array(
					'key'           => 'field_mrn_nested_logos_bottom_accent',
					'label'         => 'Accent',
					'name'          => 'bottom_accent',
					'aria-label'    => '',
					'type'          => 'true_false',
					'ui'            => 1,
					'default_value' => 0,
					'ui_on_text'    => 'On',
					'ui_off_text'   => 'Off',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_logos_bottom_accent_style',
					'label'         => 'Accent Style',
					'name'          => 'bottom_accent_style',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => function_exists( 'mrn_site_styles_get_graphic_element_choices' ) ? mrn_site_styles_get_graphic_element_choices() : array( '' => 'Select a Graphic Element' ),
					'default_value' => '',
					'ui'            => 1,
					'allow_null'    => 1,
					'instructions'  => 'Choose a saved graphic element from Site Styles.',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				mrn_base_stack_get_motion_group_field( 'field_mrn_nested_logos_motion_settings' ),
			),
		),
		'layout_mrn_nested_external_widget' => array(
			'key'        => 'layout_mrn_nested_external_widget',
			'name'       => 'external_widget',
			'label'      => 'External - widget/iFrame',
			'display'    => 'block',
			'sub_fields' => array(
				array(
					'key'        => 'field_mrn_nested_external_widget_content_tab',
					'label'      => 'Content',
					'name'       => '',
					'aria-label' => '',
					'type'       => 'tab',
					'placement'  => 'top',
				),
				array(
					'key'          => 'field_mrn_nested_external_widget_code',
					'label'        => 'Snippet/Code',
					'name'         => 'code',
					'aria-label'   => '',
					'type'         => 'textarea',
					'rows'         => 8,
				),
				array(
					'key'        => 'field_mrn_nested_external_widget_config_tab',
					'label'      => 'Configs',
					'name'       => '',
					'aria-label' => '',
					'type'       => 'tab',
					'placement'  => 'top',
					'endpoint'   => 0,
				),
				array(
					'key'           => 'field_mrn_nested_external_widget_background_color',
					'label'         => 'Background color',
					'name'          => 'background_color',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => function_exists( 'mrn_rbl_get_site_color_choices' ) ? mrn_rbl_get_site_color_choices() : array(),
					'ui'            => 1,
					'allow_null'    => 1,
					'instructions'  => 'Select from Site Colors when available.',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_external_widget_bottom_accent',
					'label'         => 'Accent',
					'name'          => 'bottom_accent',
					'aria-label'    => '',
					'type'          => 'true_false',
					'ui'            => 1,
					'default_value' => 0,
					'ui_on_text'    => 'On',
					'ui_off_text'   => 'Off',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				array(
					'key'           => 'field_mrn_nested_external_widget_bottom_accent_style',
					'label'         => 'Accent Style',
					'name'          => 'bottom_accent_style',
					'aria-label'    => '',
					'type'          => 'select',
					'choices'       => function_exists( 'mrn_site_styles_get_graphic_element_choices' ) ? mrn_site_styles_get_graphic_element_choices() : array( '' => 'Select a Graphic Element' ),
					'default_value' => '',
					'ui'            => 1,
					'allow_null'    => 1,
					'instructions'  => 'Choose a saved graphic element from Site Styles.',
					'wrapper'       => array(
						'width' => '50',
					),
				),
				mrn_base_stack_get_motion_group_field( 'field_mrn_nested_external_widget_motion_settings' ),
			),
		),
		'layout_mrn_nested_reusable_block' => array(
			'key'        => 'layout_mrn_nested_reusable_block',
			'name'       => 'reusable_block',
			'label'      => 'Reusable Block',
			'display'    => 'block',
			'sub_fields' => array(
				array(
					'key'           => 'field_mrn_nested_reusable_block_post',
					'label'         => 'Block',
					'name'          => 'block',
					'aria-label'    => '',
					'type'          => 'post_object',
					'post_type'     => function_exists( 'mrn_rbl_get_post_types' ) ? mrn_rbl_get_post_types() : array(),
					'return_format' => 'object',
					'ui'            => 1,
					'allow_null'    => 0,
					'multiple'      => 0,
					'instructions'  => 'Choose a reusable block from the library. Editing that block updates it everywhere it is used.',
				),
			),
		),
	);
}
