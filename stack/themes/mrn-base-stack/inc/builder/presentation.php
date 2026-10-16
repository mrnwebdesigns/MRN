<?php
/**
 * Optional native media, sequence, record and rotating-text presentations.
 *
 * @package mrn-base-stack
 */

/**
 * Get editable presentation fields for an existing builder layout.
 *
 * @param string $layout Layout name.
 * @param string $seed Unique field prefix.
 * @return array
 */
function mrn_base_stack_get_presentation_fields( $layout, $seed ) {
	$make = static function ( $name, $label, $type, $extra = array() ) use ( $seed ) {
		return array_merge(
			array(
				'key'   => $seed . '_' . $name,
				'name'  => $name,
				'label' => $label,
				'type'  => $type,
			),
			$extra
		);
	};
	if ( 'body_text' === $layout ) {
		$fields = array(
			$make( 'list_columns', 'List Columns', 'select', array( 'choices' => array( '' => 'Use Existing Formatting', '1' => 'One', '2' => 'Two', '3' => 'Three' ), 'default_value' => '', 'instructions' => 'Optional layout for top-level ordered and unordered lists in Body Text. Nested lists retain their formatting.' ) ),
			$make( 'list_columns_mobile', 'List Columns on Small Screens', 'select', array( 'choices' => array( '1' => 'One', '2' => 'Two', '3' => 'Three' ), 'default_value' => '1' ) ),
			$make( 'list_flow', 'List Reading Flow', 'select', array( 'choices' => array( 'rows' => 'Across Rows', 'columns' => 'Down Columns' ), 'default_value' => 'rows', 'instructions' => 'Down Columns keeps the authored order flowing down the first column, then the next.' ) ),
			$make( 'list_markers', 'List Markers', 'select', array( 'choices' => array( 'default' => 'Use Existing Markers', 'plain' => 'Hide Markers' ), 'default_value' => 'default' ) ),
			$make( 'list_alignment', 'List Alignment', 'select', array( 'choices' => array( 'start' => 'Start', 'end' => 'End', 'end-start' => 'End, Start on Small Screens' ), 'default_value' => 'start' ) ),
			$make( 'list_gap', 'Space Between List Columns', 'text', array( 'instructions' => 'Optional non-negative CSS length, for example 30px. Does not change row margin or padding.' ) ),
		);
		foreach ( $fields as &$field ) {
			if ( 'list_columns' !== $field['name'] ) {
				$field['conditional_logic'] = array( array( array( 'field' => $seed . '_list_columns', 'operator' => '!=', 'value' => '' ) ) );
			}
		}
		unset( $field );
		return $fields;
	}
	if ( 'logos' === $layout ) {
		return array(
			$make( 'slider_breakpoints', 'Responsive Logo Views', 'repeater', array(
				'max' => 4, 'layout' => 'row', 'button_label' => 'Add Viewport',
				'instructions' => 'Optional overrides at or below each viewport width. Leave empty for the existing responsive views.',
				'sub_fields' => array(
					$make( 'slider_breakpoint_width', 'Maximum Viewport Width (px)', 'number', array( 'name' => 'max_width', 'min' => 320, 'max' => 2560, 'required' => 1 ) ),
					$make( 'slider_breakpoint_count', 'Logos per View', 'select', array( 'name' => 'per_page', 'choices' => array_combine( range( 1, 7 ), range( 1, 7 ) ), 'required' => 1 ) ),
				),
			) ),
			$make( 'autoplay_max_width', 'Autoplay Maximum Viewport Width (px)', 'number', array( 'min' => 320, 'max' => 2560, 'instructions' => 'Optional. With Autoplay on, move logos only at or below this viewport width and when they overflow.' ) ),
			$make( 'continuous_scroll', 'Continuous Logo Scroll', 'true_false', array( 'ui' => 1, 'default_value' => 0, 'instructions' => 'Loop with linear movement. Autoplay still respects pause, keyboard focus, reduced motion and visibility.' ) ),
			$make( 'slider_gap', 'Space Between Logos', 'text', array( 'instructions' => 'Optional non-negative CSS length, for example 30px. Leave empty for the existing slider spacing.' ) ),
		);
	}
	if ( in_array( $layout, array( 'basic', 'basic_block', 'cta', 'cta_block' ), true ) ) {
		$fields = array();
		foreach ( array( 'one', 'two' ) as $slot ) {
			$fields[] = $make(
				'rotating_phrases_' . $slot,
				'Rotating Phrases ' . ucfirst( $slot ),
				'repeater',
				array(
					'layout'       => 'block',
					'max'          => 30,
					'button_label' => 'Add Phrase',
					'instructions' => 'Optional. Place {phrase_' . $slot . '} in the Heading. The first phrase remains readable without JavaScript or with reduced motion.',
					'sub_fields'   => array(
						$make(
							'phrase_' . $slot . '_text',
							'Phrase',
							'text',
							array(
								'name'     => 'text',
								'required' => 1,
							)
						),
					),
				)
			);
		}
		$fields[] = $make(
			'rotation_interval',
			'Phrase Interval (milliseconds)',
			'number',
			array(
				'min'           => 1500,
				'max'           => 30000,
				'default_value' => 4000,
			)
		);
		return $fields;
	}
	if ( 'image_content' === $layout ) {
		return array(
			$make(
				'image_presentation',
				'Image Presentation',
				'select',
				array(
					'choices'       => array(
						'standard'       => 'Standard',
						'scroll-zoom'    => 'Scroll Zoom',
						'tilted-gallery' => 'Rotated Column Gallery',
					),
					'default_value' => 'standard',
				)
			),
			$make( 'media_height', 'Media Height', 'text', array( 'instructions' => 'Optional CSS length, such as 52rem or 80vh. Applies to the media, not row spacing.' ) ),
			$make( 'media_height_mobile', 'Mobile Media Height', 'text', array( 'instructions' => 'Optional CSS length for narrow screens.' ) ),
			$make(
				'image_overlay',
				'Image Overlay',
				'true_false',
				array(
					'ui'            => 1,
					'default_value' => 0,
				)
			),
			$make(
				'gallery_columns',
				'Gallery Columns',
				'repeater',
				array(
					'max'          => 5,
					'layout'       => 'block',
					'button_label' => 'Add Column',
					'sub_fields'   => array(
						$make(
							'gallery_column_images',
							'Images',
							'gallery',
							array(
								'name'          => 'images',
								'return_format' => 'id',
								'min'           => 0,
								'max'           => 30,
								'preview_size'  => 'thumbnail',
							)
						),
					),
				)
			),
		);
	}
	if ( 'showcase' === $layout ) {
		return array(
			$make(
				'showcase_presentation',
				'Gallery Presentation',
				'select',
				array(
					'choices'       => array(
						'standard'     => 'Standard',
						'scroll-strip' => 'Scroll-driven Horizontal Strip',
					),
					'default_value' => 'standard',
				)
			),
		);
	}
	if ( 'card' === $layout ) {
		return array(
			$make(
				'card_presentation',
				'Cards Presentation',
				'select',
				array(
					'choices'       => array(
						'standard' => 'Standard',
						'timeline' => 'Step Timeline',
					),
					'default_value' => 'standard',
				)
			),
			$make(
				'timeline_image',
				'Timeline Image',
				'image',
				array(
					'return_format' => 'id',
					'preview_size'  => 'medium',
				)
			),
			$make( 'timeline_skip_label', 'Timeline Skip Link', 'text', array( 'default_value' => 'Skip timeline' ) ),
		);
	}
	if ( 'content_lists' === $layout ) {
		return array(
			$make(
				'record_presentation',
				'Record Presentation',
				'select',
				array(
					'choices'       => array(
						'standard'    => 'Standard',
						'people'      => 'People with Optional Hover Video',
						'comparisons' => 'Before / After Comparison Stack',
					),
					'default_value' => 'standard',
					'instructions'  => 'People use designation and hover_video. Comparisons use before_image, after_image and tags. Sources, selection, order and pagination remain controlled by Content Lists.',
				)
			),
		);
	}
	if ( 'video' === $layout ) {
		return array(
			$make(
				'video_presentation',
				'Video Presentation',
				'select',
				array(
					'choices'       => array(
						'standard'      => 'Standard',
						'scroll-expand' => 'Expand on Scroll',
					),
					'default_value' => 'standard',
				)
			),
			$make(
				'video_follow_color',
				'Following Section Transition Color',
				'select',
				array(
					'choices'    => function_exists( 'mrn_rbl_get_site_color_choices' ) ? mrn_rbl_get_site_color_choices() : array(),
					'allow_null' => 1,
					'ui'         => 1,
				)
			),
		);
	}
	return array();
}

/**
 * Normalize optional Logos settings without changing existing slider defaults.
 *
 * @param array $row Authored row.
 * @return array
 */
function mrn_base_stack_get_logos_responsive_config( array $row ) {
	$breakpoints = array();
	foreach ( array_slice( (array) ( $row['slider_breakpoints'] ?? array() ), 0, 4 ) as $view ) {
		if ( ! is_array( $view ) ) {
			continue;
		}
		$width = (int) ( $view['max_width'] ?? 0 );
		$count = (int) ( $view['per_page'] ?? 0 );
		if ( $width >= 320 && $width <= 2560 && $count >= 1 && $count <= 7 ) {
			$breakpoints[ $width ] = array( 'perPage' => $count );
		}
	}
	$maximum = (int) ( $row['autoplay_max_width'] ?? 0 );
	return array(
		'breakpoints' => $breakpoints,
		'autoplay_max_width' => $maximum >= 320 && $maximum <= 2560 ? $maximum : 0,
		'continuous' => ! empty( $row['continuous_scroll'] ),
		'gap' => mrn_base_stack_validate_media_height( (string) ( $row['slider_gap'] ?? '' ) ),
	);
}

/**
 * Insert optional authored fields in Content, preserving registered field keys.
 *
 * @param array  $fields Existing definitions.
 * @param string $layout Layout name.
 * @param string $seed Unique field prefix.
 * @return array
 */
function mrn_base_stack_ensure_presentation_fields( array $fields, $layout, $seed ) {
	// Nested compatibility layouts share names, but never their ACF field keys.
	if ( ! empty( $fields[0]['key'] ) ) {
		$seed = sanitize_key( $fields[0]['key'] ) . '_presentation';
	}
	$names = array_column( $fields, 'name' );
	$extra = array_filter(
		mrn_base_stack_get_presentation_fields( $layout, $seed ),
		static function ( $field ) use ( $names ) {
			return ! in_array( $field['name'], $names, true );
		}
	);
	if ( ! $extra ) {
		return $fields;
	}
	$at = count( $fields );
	foreach ( $fields as $index => $field ) {
		if ( 'tab' === ( $field['type'] ?? '' ) && 'content' !== sanitize_title( $field['label'] ?? '' ) ) {
			$at = $index;
			break;
		}
	}
	array_splice( $fields, $at, 0, array_values( $extra ) );
	return $fields;
}

/**
 * Build a bounded, opt-in Body Text list presentation.
 *
 * @param array $row Native row values.
 * @return array{classes: string[], styles: string[]}
 */
function mrn_base_stack_get_body_list_contract( array $row ) {
	$columns = (string) ( $row['list_columns'] ?? '' );
	if ( ! in_array( $columns, array( '1', '2', '3' ), true ) ) {
		return array( 'classes' => array(), 'styles' => array() );
	}
	$mobile = (string) ( $row['list_columns_mobile'] ?? '1' );
	$mobile = in_array( $mobile, array( '1', '2', '3' ), true ) ? $mobile : '1';
	$flow = 'columns' === ( $row['list_flow'] ?? '' ) ? 'columns' : 'rows';
	$alignment = (string) ( $row['list_alignment'] ?? 'start' );
	$alignment = in_array( $alignment, array( 'start', 'end', 'end-start' ), true ) ? $alignment : 'start';
	$classes = array( 'mrn-ui__text--list-layout', 'mrn-ui__text--list-flow-' . $flow, 'mrn-ui__text--list-align-' . $alignment );
	if ( 'plain' === ( $row['list_markers'] ?? '' ) ) {
		$classes[] = 'mrn-ui__text--list-plain';
	}
	$styles = array( '--mrn-list-columns:' . $columns, '--mrn-list-columns-mobile:' . $mobile );
	$gap = mrn_base_stack_validate_media_height( (string) ( $row['list_gap'] ?? '' ) );
	if ( '' !== $gap ) {
		$styles[] = '--mrn-list-column-gap:' . $gap;
	}
	return array( 'classes' => $classes, 'styles' => $styles );
}

/**
 * Register fields before ACF extracts the original nested layout definitions.
 *
 * @param array $fields Field tree.
 * @return array
 */
function mrn_base_stack_register_presentation_field_tree( array $fields ) {
	foreach ( $fields as &$field ) {
		foreach ( $field['layouts'] ?? array() as $key => $layout ) {
			$name = $layout['name'];
			if ( ! mrn_base_stack_field_list_has_full_contract_reusable_group_clone( $layout['sub_fields'] ?? array() ) ) {
				$field['layouts'][ $key ]['sub_fields'] = mrn_base_stack_ensure_presentation_fields( $layout['sub_fields'] ?? array(), $name, 'field_mrn_' . $name );
			}
		}
		if ( isset( $field['sub_fields'] ) && is_array( $field['sub_fields'] ) ) {
			$field['sub_fields'] = mrn_base_stack_register_presentation_field_tree( $field['sub_fields'] );
		}
	}
	unset( $field );
	return $fields;
}

/**
 * Enqueue the single optional presentation component.
 *
 * @return void
 */
function mrn_base_stack_enqueue_presentation() {
	mrn_base_stack_enqueue_front_end_component_assets( array( 'presentation' => true ) );
}

/**
 * Build safe rotating heading markup, retaining a stable accessible first phrase.
 *
 * @param array  $row Authored fields.
 * @param string $heading Heading text.
 * @return string Escaped markup.
 */
function mrn_base_stack_get_rotating_heading_markup( array $row, $heading ) {
	$html         = mrn_base_stack_format_heading_inline_html( $heading );
	$has_rotation = false;
	foreach ( array( 'one', 'two' ) as $slot ) {
		$phrases = array();
		foreach ( (array) ( $row[ 'rotating_phrases_' . $slot ] ?? array() ) as $phrase ) {
			if ( is_array( $phrase ) && is_scalar( $phrase['text'] ?? null ) && '' !== trim( (string) $phrase['text'] ) ) {
				$phrases[] = wp_strip_all_tags( (string) $phrase['text'] );
			}
		}
		if ( ! $phrases ) {
			continue;
		}
		$has_rotation = $has_rotation || count( $phrases ) > 1;
		$markup       = esc_html( $phrases[0] );
		if ( count( $phrases ) > 1 ) {
			$markup = '<span class="mrn-rotate" data-mrn-phrases="' . esc_attr( wp_json_encode( $phrases ) ) . '"><span class="screen-reader-text">' . esc_html( $phrases[0] ) . '</span><span class="mrn-rotate__visual" aria-hidden="true">' . esc_html( $phrases[0] ) . '</span>';
			foreach ( $phrases as $phrase ) {
				$markup .= '<span class="mrn-rotate__size" aria-hidden="true">' . esc_html( $phrase ) . '</span>';
			}
			$markup .= '</span>';
		}
		$tokens  = array( '{phrase_' . $slot . '}', '[phrase_' . $slot . ']' );
		$pattern = '/<span\b[^>]*class=["\'][^"\']*\bphrase_' . $slot . '\b[^"\']*["\'][^>]*>.*?<\/span>/s';
		if ( preg_match( $pattern, $html ) ) {
			$html = preg_replace_callback(
				$pattern,
				static function () use ( $markup ) {
					return $markup;
				},
				$html
			);
		} elseif ( str_contains( $html, $tokens[0] ) || str_contains( $html, $tokens[1] ) ) {
			$html = str_replace( $tokens, $markup, $html );
		} else {
			$html .= ' ' . $markup;
		}
	}
	if ( ! $has_rotation ) {
		return $html;
	}
	mrn_base_stack_enqueue_presentation();
	$interval = max( 1500, min( 30000, (int) ( $row['rotation_interval'] ?? 4000 ) ) );
	return '<span data-mrn-rotation-group data-interval="' . esc_attr( $interval ) . '" data-pause-label="' . esc_attr__( 'Pause rotating text', 'mrn-base-stack' ) . '" data-resume-label="' . esc_attr__( 'Resume rotating text', 'mrn-base-stack' ) . '">' . $html . '</span>';
}

/**
 * Validate a single non-negative CSS length for optional media sizing.
 *
 * @param string $value Authored value.
 * @return string Valid length, or empty when invalid.
 */
function mrn_base_stack_validate_media_height( $value ) {
	$value = strtolower( trim( $value ) );
	return strlen( $value ) <= 64 && preg_match( '/^(?:0|(?:\d+(?:\.\d+)?|\.\d+)(?:px|em|rem|vh|vw|vmin|vmax|svh|lvh|dvh|svw|lvw|dvw|%))$/D', $value ) ? $value : '';
}

/**
 * Render optional validated media sizing without changing row spacing.
 *
 * @param array $row Authored fields.
 * @return string Escaped attribute string.
 */
function mrn_base_stack_get_image_stage_attributes( array $row ) {
	$mode = $row['image_presentation'] ?? 'standard';
	if ( ! in_array( $mode, array( 'scroll-zoom', 'tilted-gallery' ), true ) ) {
		return '';
	}
	mrn_base_stack_enqueue_presentation();
	$styles = array();
	foreach ( array(
		'media_height'        => '--mrn-media-height',
		'media_height_mobile' => '--mrn-media-height-mobile',
	) as $name => $property ) {
		$raw   = is_scalar( $row[ $name ] ?? null ) ? (string) $row[ $name ] : '';
		$value = mrn_base_stack_validate_media_height( $raw );
		if ( '' !== $value ) {
			$styles[] = $property . ':' . $value;
		}
	}
	return mrn_base_stack_get_html_attributes(
		array(
			'data-mrn-image-stage' => $mode,
			'style'                => implode( ';', $styles ),
		)
	);
}

/**
 * Render authored column media without flattening its ordered image collections.
 *
 * @param array $row Authored row.
 * @return string Escaped markup.
 */
function mrn_base_stack_get_column_gallery_markup( array $row ) {
	if ( 'tilted-gallery' !== ( $row['image_presentation'] ?? '' ) ) {
		return '';
	}
	$columns = '';
	foreach ( (array) ( $row['gallery_columns'] ?? array() ) as $column ) {
		$images = '';
		foreach ( (array) ( ( is_array( $column ) ? ( $column['images'] ?? array() ) : array() ) ) as $image ) {
			$images .= mrn_base_stack_get_attachment_image(
				$image,
				'mrn-content-media',
				array(
					'loading' => 'lazy',
					'class'   => 'mrn-column-gallery__image',
				)
			);
		}
		$columns .= '<div class="mrn-column-gallery__column">' . $images . '</div>';
	}
	return '' !== $columns ? '<div class="mrn-column-gallery">' . $columns . '</div>' : '';
}

/**
 * Resolve optional people/comparison fields from any selected Content Lists type.
 *
 * Site integrations may adapt their metadata through this normalized contract.
 * No site-specific post type or record IDs are owned by the shared renderer.
 *
 * @param WP_Post $post Source record.
 * @param string  $profile Selected presentation.
 * @return array
 */
function mrn_base_stack_get_record_presentation_data( WP_Post $post, $profile ) {
	$names = 'people' === $profile ? array( 'designation', 'hover_video' ) : array( 'before_image', 'after_image', 'tags', 'background_color', 'title_color' );
	$data  = array();
	foreach ( $names as $name ) {
		$data[ $name ] = function_exists( 'get_field' ) ? get_field( $name, $post->ID ) : '';
	}
	$data = apply_filters( 'mrn_base_stack_content_list_item_presentation', $data, $post, $profile );
	return is_array( $data ) ? $data : array();
}

/**
 * Validate record colors, including the RGB values stored by older ACF pickers.
 *
 * @param mixed $value Color value.
 * @return string Safe CSS color.
 */
function mrn_base_stack_get_record_color( $value ) {
	if ( ! is_string( $value ) ) {
		return '';
	}
	$hex = sanitize_hex_color( trim( $value ) );
	if ( $hex ) {
		return $hex;
	}
	if ( preg_match( '/^rgb\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*\)$/iD', trim( $value ), $parts ) ) {
		$channels = array_map( 'intval', array_slice( $parts, 1 ) );
		return max( $channels ) <= 255 ? sprintf( '#%02x%02x%02x', ...$channels ) : '';
	}
	return '';
}

/**
 * Render one optional people/comparison item using existing list source/order.
 *
 * @param WP_Post $post Source record.
 * @param array   $args Render arguments.
 * @return string Escaped markup.
 */
function mrn_base_stack_render_record_presentation( WP_Post $post, array $args ) {
	$profile = $args['record_presentation'] ?? '';
	if ( ! in_array( $profile, array( 'people', 'comparisons' ), true ) ) {
		return '';
	}
	mrn_base_stack_enqueue_presentation();
	$data   = mrn_base_stack_get_record_presentation_data( $post, $profile );
	$colors = array();
	foreach ( array(
		'background_color' => '--mrn-comparison-surface',
		'title_color'      => '--mrn-comparison-text',
	) as $name => $property ) {
		$value = mrn_base_stack_get_record_color( $data[ $name ] ?? null );
		if ( $value ) {
			$colors[] = $property . ':' . $value; }
	}
	$attrs  = mrn_base_stack_get_html_attributes(
		array(
			'style'          => implode( ';', $colors ),
			'class'          => 'mrn-content-list-row__item mrn-record mrn-record--' . $profile,
			'data-record-id' => $post->ID,
		)
	);
	$title  = esc_html( get_the_title( $post ) );
	$media  = '';
	$detail = '';
	if ( 'people' === $profile ) {
		$media = get_the_post_thumbnail( $post, 'mrn-content-media', array( 'loading' => 'lazy' ) );
		$video = $data['hover_video'] ?? '';
		$video = is_array( $video ) ? ( $video['url'] ?? '' ) : ( is_numeric( $video ) ? wp_get_attachment_url( (int) $video ) : $video );
		if ( is_string( $video ) && '' !== esc_url( $video, array( 'http', 'https' ) ) ) {
			$media .= '<video data-mrn-hover-video data-src="' . esc_url( $video, array( 'http', 'https' ) ) . '" muted loop playsinline preload="none" aria-hidden="true" tabindex="-1"></video>';
		}
		$role   = is_scalar( $data['designation'] ?? null ) ? (string) $data['designation'] : '';
		$detail = '' !== $role ? '<p class="mrn-record__designation">' . esc_html( $role ) . '</p>' : '';
	} else {
		foreach ( array(
			'before_image' => __( 'Before', 'mrn-base-stack' ),
			'after_image'  => __( 'After', 'mrn-base-stack' ),
		) as $key => $label ) {
			$media .= '<figure><figcaption>' . esc_html( $label ) . '</figcaption>' . mrn_base_stack_get_attachment_image( $data[ $key ] ?? null, 'mrn-content-media', array( 'loading' => 'lazy' ) ) . '</figure>';
		}
		$tags = $data['tags'] ?? '';
		if ( is_string( $tags ) ) {
			$detail = '<p class="mrn-record__tags">' . esc_html( $tags ) . '</p>';
		} elseif ( is_array( $tags ) ) {
			foreach ( $tags as $tag ) {
				$label   = is_scalar( $tag ) ? (string) $tag : ( is_array( $tag ) && is_scalar( $tag['title'] ?? null ) ? (string) $tag['title'] : ( is_array( $tag ) && is_scalar( $tag['label'] ?? null ) ? (string) $tag['label'] : '' ) );
				$detail .= '' !== $label ? '<span class="mrn-record__tag">' . esc_html( $label ) . '</span>' : '';
			}
		}
	}
	$url = ! empty( $args['link_items'] ) ? mrn_base_stack_get_content_list_item_permalink( $post, $args ) : '';
	if ( '' !== $url ) {
		$title = '<a href="' . esc_url( $url ) . '">' . $title . '</a>';
	}
	$focus = 'people' === $profile && str_contains( $media, '<video ' ) ? ' tabindex="0" aria-label="' . esc_attr( get_the_title( $post ) ) . '"' : '';
	return '<li ' . $attrs . '><article' . $focus . '><div class="mrn-record__media">' . $media . '</div><h3>' . $title . '</h3>' . $detail . '</article></li>';
}
