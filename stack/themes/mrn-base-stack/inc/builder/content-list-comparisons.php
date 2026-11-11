<?php
/**
 * Optional stacked-card frame for Before / After comparison lists.
 *
 * The source post model is unchanged: each record keeps its title, tags,
 * before/after images and card colours. The frame only adds bounded layout,
 * typography and palette controls plus the scroll-stacking behavior.
 *
 * @package mrn-base-stack
 */

/** Whether the row selected the stacked-card frame. */
function mrn_base_stack_is_comparison_frame( array $row ) {
	return 'comparisons' === ( $row['record_presentation'] ?? '' ) && 'stack' === ( $row['comparison_frame'] ?? '' );
}

/** Bounded dimension names (Site Text tokens, px, rem, em, %, vw). */
function mrn_base_stack_get_comparison_dimensions() {
	return explode( ' ', 'content_width inner_gutter card_margin_inline card_margin_inline_mobile card_gap card_radius inner_padding_top inner_padding_top_mobile media_gap media_gap_mobile media_space_before media_space_before_mobile image_width image_radius title_size title_size_mobile title_line_height tags_gap tags_space_before tags_space_before_mobile tag_padding_inline tag_padding_block tag_radius tag_size tag_line_height stack_peek' );
}

/** Editor fields, shown only for the Comparison Stack presentation. */
function mrn_base_stack_get_comparison_list_fields( $seed ) {
	$make   = static function ( $name, $type, array $extra = array() ) use ( $seed ) {
		return array_merge(
			array(
				'key'           => $seed . '_comparison_' . $name,
				'name'          => 'comparison_' . $name,
				'label'         => 'Comparison ' . ucwords( str_replace( '_', ' ', $name ) ),
				'type'          => $type,
				'default_value' => '',
			),
			$extra
		);
	};
	$fields = array(
		$make(
			'frame',
			'select',
			array(
				'label'        => 'Comparison Card Frame',
				'choices'      => array(
					''      => 'Use Existing Comparison Styles',
					'stack' => 'Stacked Cards',
				),
				'instructions' => 'Stacked Cards draw each record as a rounded, sticky card with a centered title, tag pills and side-by-side before/after images. Cards pin below the site header and recede as the next card stacks over them; the motion stops for reduced-motion visitors and on narrow screens.',
			)
		),
		$make(
			'heading_tag',
			'select',
			array(
				'choices'       => array(
					'h2' => 'H2',
					'h3' => 'H3',
					'h4' => 'H4',
				),
				'default_value' => 'h2',
			)
		),
	);
	foreach ( mrn_base_stack_get_comparison_dimensions() as $name ) {
		$fields[] = $make(
			$name,
			'text',
			array(
				'instructions' => 'Optional bounded dimension or Site Text size/line token.',
				'wrapper'      => array( 'width' => '50' ),
			)
		);
	}
	foreach ( array( 'title', 'tag' ) as $part ) {
		$fields[] = $make( $part . '_weight', 'select', array( 'choices' => array( '' => 'Use Existing Weight' ) + array_combine( range( 100, 900, 100 ), range( 100, 900, 100 ) ) ) );
	}
	foreach ( array( 'card_surface', 'tag_text', 'tag_surface' ) as $part ) {
		$field          = mrn_base_stack_get_background_color_field( $seed . '_comparison_' . $part . '_color' );
		$field['name']  = 'comparison_' . $part . '_color';
		$field['label'] = 'Comparison ' . ucwords( str_replace( '_', ' ', $part ) ) . ' Color';
		$fields[]       = $field;
	}
	foreach ( $fields as &$field ) {
		$field['conditional_logic'] = array( array( array( 'field' => $seed . '_record_presentation', 'operator' => '==', 'value' => 'comparisons' ) ) );
	}
	unset( $field );
	return $fields;
}

/** Section classes and custom properties for the selected frame. */
function mrn_base_stack_get_comparison_list_contract( array $row ) {
	$out = array(
		'classes'    => array(),
		'attributes' => array(),
	);
	if ( ! mrn_base_stack_is_comparison_frame( $row ) ) {
		return $out;
	}
	mrn_base_stack_enqueue_presentation();
	$out['classes'][] = 'has-comparison-frame';
	$styles           = array();
	foreach ( mrn_base_stack_get_comparison_dimensions() as $name ) {
		$value = mrn_base_stack_get_content_frame_dimension( $row[ 'comparison_' . $name ] ?? '', $name );
		if ( '' !== $value ) {
			$styles[] = '--mrn-comparison-' . str_replace( '_', '-', $name ) . ':' . $value;
		}
	}
	foreach ( array( 'title', 'tag' ) as $part ) {
		$value = $row[ 'comparison_' . $part . '_weight' ] ?? '';
		if ( is_scalar( $value ) && in_array( (string) $value, array_map( 'strval', range( 100, 900, 100 ) ), true ) ) {
			$styles[] = '--mrn-comparison-' . $part . '-weight:' . $value;
		}
	}
	foreach ( array( 'card_surface', 'tag_text', 'tag_surface' ) as $part ) {
		$value = mrn_base_stack_get_frame_palette_color( $row[ 'comparison_' . $part . '_color' ] ?? '' );
		if ( '' !== $value ) {
			$styles[] = '--mrn-comparison-' . str_replace( '_', '-', $part ) . ':' . $value;
		}
	}
	$out['attributes']['style'] = implode( ';', $styles );
	return $out;
}

/**
 * Render one stacked comparison card from the current record.
 *
 * @param WP_Post $post Source record.
 * @param array   $args Render arguments.
 * @return string Escaped markup.
 */
function mrn_base_stack_render_comparison_stack_item( WP_Post $post, array $args ) {
	if ( 'publish' !== $post->post_status ) {
		return '';
	}
	$row   = is_array( $args['comparison_settings'] ?? null ) ? $args['comparison_settings'] : array();
	$data  = mrn_base_stack_get_record_presentation_data( $post, 'comparisons' );
	$title = get_the_title( $post );
	$style = array();
	$bg    = mrn_base_stack_get_record_color( $data['background_color'] ?? null );
	if ( '' !== $bg ) {
		$style[] = '--mrn-comparison-surface:' . $bg;
	}
	$ink = mrn_base_stack_get_record_color( $data['title_color'] ?? null );
	if ( '' !== $ink ) {
		$style[] = '--mrn-comparison-text:' . $ink;
	}
	$tag_name = in_array( $row['comparison_heading_tag'] ?? '', array( 'h2', 'h3', 'h4' ), true ) ? $row['comparison_heading_tag'] : 'h2';
	$tags     = '';
	foreach ( is_array( $data['tags'] ?? null ) ? $data['tags'] : array() as $tag ) {
		$label = is_scalar( $tag ) ? (string) $tag : ( is_array( $tag ) && is_scalar( $tag['label'] ?? null ) ? (string) $tag['label'] : '' );
		if ( '' !== trim( $label ) ) {
			$tags .= '<span class="mrn-record__tag">' . esc_html( $label ) . '</span>';
		}
	}
	$media = '';
	foreach ( array(
		'before_image' => __( 'before', 'mrn-base-stack' ),
		'after_image'  => __( 'after', 'mrn-base-stack' ),
	) as $key => $suffix ) {
		$image  = $data[ $key ] ?? null;
		$id     = mrn_base_stack_get_image_attachment_id( $image );
		$alt    = $id > 0 ? trim( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) ) : '';
		$markup = $id > 0 ? mrn_base_stack_get_attachment_image( $image, 'full', array( 'alt' => '' !== $alt ? $alt : $title . ' — ' . $suffix ) ) : '<span class="mrn-comparison__placeholder" aria-hidden="true"></span>';
		$media .= '<figure class="mrn-comparison__figure">' . $markup . '</figure>';
	}
	return '<li class="mrn-content-list-row__item mrn-record mrn-record--comparisons mrn-record--comparison-stack" data-record-id="' . esc_attr( (string) $post->ID ) . '" data-mrn-comparison-card style="' . esc_attr( implode( ';', $style ) ) . '"><article><div class="mrn-comparison__inner"><' . $tag_name . ' class="mrn-comparison__title">' . esc_html( $title ) . '</' . $tag_name . '>' . ( '' !== $tags ? '<div class="mrn-comparison__tags">' . $tags . '</div>' : '' ) . '<div class="mrn-comparison__media">' . $media . '</div></div></article></li>';
}
