<?php
/** Optional native image annotations and attribution, independent of row spacing. */

/** Explicit presentation fields keep existing images and actions unchanged. */
function mrn_base_stack_get_image_overlay_fields( $seed ) {
	$make = static function ( $name, $type, array $extra = array() ) use ( $seed ) {
		return array_merge( array( 'key' => $seed . '_' . $name, 'name' => $name, 'label' => ucwords( str_replace( '_', ' ', $name ) ), 'type' => $type, 'default_value' => '' ), $extra );
	};
	$fields = array(
		$make( 'image_action_scope', 'select', array( 'choices' => array( '' => 'Link Whole Image', 'marker-only' => 'Link Visible Action Only' ), 'instructions' => 'Action Only requires the visible Image Action Marker. The surrounding image stays unlinked.' ) ),
		$make( 'image_action_alignment', 'select', array( 'choices' => array( '' => 'Use Existing Alignment', 'left' => 'Left', 'right' => 'Right' ) ) ),
		$make( 'image_action_vertical_alignment', 'select', array( 'choices' => array( '' => 'Use Existing Position', 'top' => 'Top', 'bottom' => 'Bottom' ) ) ),
		$make( 'image_action_icon_style', 'select', array( 'choices' => array( '' => 'Use Existing Outline Arrow', 'solid' => 'Solid Arrow' ) ) ),
		$make( 'image_action_font_weight', 'select', array( 'choices' => array( '' => 'Use Existing Weight' ) + array_combine( range( 100, 900, 100 ), range( 100, 900, 100 ) ) ) ),
		$make( 'image_caption_presentation', 'select', array( 'choices' => array( '' => 'Use Existing Caption', 'attribution' => 'Small Image Attribution' ), 'instructions' => 'Optional attribution uses the existing Inside Image caption and its complete editable caption text.' ) ),
		$make( 'image_caption_font', 'select', array( 'choices' => array( '' => 'Use Existing Font', 'body' => 'Site Body', 'heading' => 'Site Heading', 'map' => 'Map Credit (Roboto / Arial)' ) ) ),
		$make( 'image_caption_color', 'color_picker', array( 'instructions' => 'Optional opaque credit color; does not change Site Colors.' ) ),
	);
	foreach ( array( 'image_action_font_size', 'image_action_line_height', 'image_action_backdrop_blur', 'image_caption_offset_inline', 'image_caption_offset_block', 'image_caption_size', 'image_caption_line_height' ) as $name ) { $fields[] = $make( $name, 'text', array( 'instructions' => 'Optional validated length or Site Text token. Unitless values are accepted for line heights. Outer spacing stays in row presets.' ) ); }
	$fields[] = $make( 'image_markers', 'repeater', array( 'min' => 0, 'max' => 30, 'layout' => 'block', 'button_label' => 'Add Image Marker', 'default_value' => array(), 'sub_fields' => array(
		$make( 'marker_media', 'image', array( 'return_format' => 'id', 'required' => 1, 'preview_size' => 'thumbnail' ) ),
		$make( 'position_x', 'number', array( 'min' => 0, 'max' => 100, 'step' => 0.1, 'default_value' => 50, 'append' => '%' ) ),
		$make( 'position_y', 'number', array( 'min' => 0, 'max' => 100, 'step' => 0.1, 'default_value' => 50, 'append' => '%' ) ),
		$make( 'marker_size', 'text', array( 'instructions' => 'Optional non-negative width. Marker media retains its square annotation box.' ) ),
		$make( 'marker_anchor', 'select', array( 'choices' => array( 'center' => 'Center', 'tip' => 'Bottom Center' ), 'default_value' => 'center' ) ),
		$make( 'decorative', 'true_false', array( 'ui' => 1, 'default_value' => 0, 'instructions' => 'Decorative markers are hidden from assistive technology. Informative markers require an alternative text override or attachment alt.' ) ),
		$make( 'marker_alt', 'text' ),
		$make( 'marker_shadow', 'true_false', array( 'ui' => 1, 'default_value' => 0 ) ),
		$make( 'marker_hover_scale', 'number', array( 'min' => 1, 'max' => 1.5, 'step' => 0.05, 'default_value' => 1, 'instructions' => 'Optional fine-pointer hover scale; reduced motion stays still.' ) ),
	) ) );
	return $fields;
}

/** Accept decimal percentages without admitting arbitrary CSS. */
function mrn_base_stack_get_image_marker_number( $value, $minimum, $maximum, $fallback ) {
	return is_scalar( $value ) && is_numeric( $value ) && (float) $value >= $minimum && (float) $value <= $maximum ? (string) (float) $value : (string) $fallback;
}

/** Render only valid media; decorative and informative markers retain their semantics. */
function mrn_base_stack_get_image_markers_markup( array $row ) {
	$items = $row['image_markers'] ?? array();
	if ( ! is_array( $items ) || ! array_is_list( $items ) || count( $items ) > 30 ) { return ''; }
	$out = '';
	foreach ( $items as $item ) {
		if ( ! is_array( $item ) || ! mrn_base_stack_image_has_content( $item['marker_media'] ?? null ) ) { continue; }
		$media = $item['marker_media'];
		$decorative = in_array( $item['decorative'] ?? false, array( true, 1, '1' ), true );
		$alt = is_string( $item['marker_alt'] ?? null ) ? trim( $item['marker_alt'] ) : '';
		if ( '' === $alt ) { $alt = mrn_base_stack_get_image_alt( $media ); }
		if ( ! $decorative && '' === $alt ) { continue; }
		$styles = array( '--mrn-image-marker-x:' . mrn_base_stack_get_image_marker_number( $item['position_x'] ?? '', 0, 100, 50 ) . '%', '--mrn-image-marker-y:' . mrn_base_stack_get_image_marker_number( $item['position_y'] ?? '', 0, 100, 50 ) . '%', '--mrn-image-marker-hover-scale:' . mrn_base_stack_get_image_marker_number( $item['marker_hover_scale'] ?? '', 1, 1.5, 1 ) );
		$size = mrn_base_stack_validate_hero_frame_dimension( $item['marker_size'] ?? '' );
		if ( '' !== $size ) { $styles[] = '--mrn-image-marker-size:' . $size; }
		$classes = 'mrn-image-marker';
		if ( 'tip' === ( $item['marker_anchor'] ?? '' ) ) { $classes .= ' mrn-image-marker--tip'; }
		if ( in_array( $item['marker_shadow'] ?? false, array( true, 1, '1' ), true ) ) { $classes .= ' has-soft-shadow'; }
		$image = mrn_base_stack_get_attachment_image( $media, 'full', array( 'class' => 'mrn-image-marker__image', 'alt' => $decorative ? '' : $alt ) );
		if ( '' === $image ) { continue; }
		$out .= '<span class="' . esc_attr( $classes ) . '" style="' . esc_attr( implode( ';', $styles ) ) . '"' . ( $decorative ? ' aria-hidden="true"' : '' ) . '>' . $image . '</span>';
	}
	return $out;
}

/** Caption defaults remain untouched unless the editor selects attribution. */
function mrn_base_stack_get_image_attribution_contract( array $row ) {
	$out = array( 'classes' => array(), 'attributes' => array() );
	if ( 'attribution' !== ( $row['image_caption_presentation'] ?? '' ) || 'inside' !== ( $row['image_caption_style'] ?? '' ) ) { return $out; }
	$out['classes'][] = 'has-image-attribution'; $styles = array();
	foreach ( array( 'offset_inline', 'offset_block', 'size', 'line_height' ) as $part ) {
		$value = mrn_base_stack_get_content_frame_dimension( $row[ 'image_caption_' . $part ] ?? '', 'caption_' . $part );
		if ( '' !== $value ) { $styles[] = '--mrn-image-caption-' . str_replace( '_', '-', $part ) . ':' . $value; }
	}
	$color = mrn_base_stack_get_record_color( $row['image_caption_color'] ?? '' );
	if ( '' !== $color ) { $styles[] = '--mrn-image-caption-color:' . $color; }
	$font = $row['image_caption_font'] ?? '';
	if ( in_array( $font, array( 'body', 'heading' ), true ) ) { $styles[] = '--mrn-image-caption-font:var(--site-font-' . $font . ')'; }
	if ( 'map' === $font ) { $styles[] = '--mrn-image-caption-font:Roboto,Arial,sans-serif'; }
	if ( $styles ) { $out['attributes']['style'] = implode( ';', $styles ); }
	return $out;
}

/** Native visible-only action keeps the surrounding image outside the link. */
function mrn_base_stack_render_marker_only_image_action( $markup, array $row, $url, $label, $blank, array $styles, $follow_attrs ) {
	if ( empty( $row['image_action_marker'] ) ) { return $markup; }
	$classes = 'mrn-image-action mrn-image-action--marker-only';
	foreach ( array( 'alignment' => array( 'left', 'right' ), 'vertical_alignment' => array( 'top', 'bottom' ) ) as $part => $choices ) { if ( in_array( $row[ 'image_action_' . $part ] ?? '', $choices, true ) ) { $classes .= ' image-action-' . $row[ 'image_action_' . $part ]; } }
	foreach ( array( 'font_size', 'line_height', 'backdrop_blur' ) as $part ) {
		$value = mrn_base_stack_get_content_frame_dimension( $row[ 'image_action_' . $part ] ?? '', $part );
		if ( '' !== $value ) { $styles[] = '--mrn-image-action-' . str_replace( '_', '-', $part ) . ':' . $value; }
	}
	$weight = $row['image_action_font_weight'] ?? '';
	if ( is_scalar( $weight ) && in_array( (string) $weight, array_map( 'strval', range( 100, 900, 100 ) ), true ) ) { $styles[] = '--mrn-image-action-font-weight:' . $weight; }
	$title = is_string( $row['image_action']['title'] ?? null ) ? trim( $row['image_action']['title'] ) : '';
	if ( '' === $title ) { $title = $label; }
	$path = 'solid' === ( $row['image_action_icon_style'] ?? '' ) ? '<path d="M4.60803 9.95201L9.58403 4.96001L4.62403 0H6.40003L11.36 4.97601L6.38403 9.95201H4.60803ZM0 4.32001H10.56V5.63201H0V4.32001Z" fill="currentColor"/>' : '<path d="M5 1l4 4-4 4M0 5h9" fill="none" stroke="currentColor" stroke-width="1.5"/>';
	$icon = '<svg width="12" height="10" viewBox="0 0 12 10" aria-hidden="true" focusable="false">' . $path . '</svg>';
	return '<div class="' . esc_attr( $classes ) . '"' . $follow_attrs . ( $styles ? ' style="' . esc_attr( implode( ';', $styles ) ) . '"' : '' ) . '>' . $markup . '<a class="mrn-image-action__marker" href="' . $url . '" aria-label="' . esc_attr( $label ) . '"' . ( $blank ? ' target="_blank" rel="noopener noreferrer"' : '' ) . '>' . '<span class="mrn-image-action__label">' . esc_html( $title ) . '</span> ' . $icon . '</a></div>';
}
