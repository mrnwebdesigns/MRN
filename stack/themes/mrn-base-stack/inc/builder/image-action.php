<?php
/** Optional native action on image media, with a meaningful editable name. */
function mrn_base_stack_get_image_action_fields( $seed ) {
	$fields = array(
		array( 'key' => $seed . '_image_action', 'name' => 'image_action', 'label' => 'Image Action', 'type' => 'link', 'return_format' => 'array' ),
		array( 'key' => $seed . '_image_action_label', 'name' => 'image_action_label', 'label' => 'Image Action Accessible Name', 'type' => 'text', 'instructions' => 'Describe the destination when the action has no visible label. Blank uses its link title or image alternative text; an unnamed action is not rendered.' ),
		array( 'key' => $seed . '_image_action_marker', 'name' => 'image_action_marker', 'label' => 'Image Action Marker', 'type' => 'true_false', 'ui' => 1, 'default_value' => 0 ),
	);
	foreach ( array( 'offset_inline' => 'Marker Horizontal Offset', 'offset_block' => 'Marker Bottom Offset', 'padding_inline' => 'Marker Horizontal Padding', 'padding_block' => 'Marker Vertical Padding', 'radius' => 'Marker Corner Radius', 'offset_inline_mobile' => 'Small-Screen Horizontal Offset (1199px)', 'offset_block_mobile' => 'Small-Screen Bottom Offset (1199px)', 'padding_block_mobile' => 'Mobile Vertical Padding (767px)' ) as $name => $label ) {
		$fields[] = array( 'key' => $seed . '_image_action_' . $name, 'name' => 'image_action_' . $name, 'label' => $label, 'type' => 'text', 'instructions' => 'Optional non-negative CSS length. Applies to the marker inside the media, not section spacing.', 'wrapper' => array( 'width' => '50' ) );
	}
	$fields[] = array( 'key' => $seed . '_image_action_follow_pointer', 'name' => 'image_action_follow_pointer', 'label' => 'Follow Pointer with Marker', 'type' => 'true_false', 'ui' => 1, 'default_value' => 0, 'instructions' => 'Decorative marker follows fine hover pointers only. Keyboard, touch, reduced-motion and no-script use the fixed marker; the full image remains the link.' );
	$fields[] = array( 'key' => $seed . '_image_action_follow_inset', 'name' => 'image_action_follow_inset', 'label' => 'Pointer Marker Edge Inset', 'type' => 'number', 'min' => 0, 'max' => 100, 'step' => 1, 'default_value' => 18, 'append' => 'px' );
	foreach ( array( 'text', 'background', 'hover_background' ) as $part ) { $field = mrn_base_stack_get_background_color_field( $seed . '_image_action_' . $part . '_color' ); $field['name'] = 'image_action_' . $part . '_color'; $field['label'] = 'Marker ' . ucwords( str_replace( '_', ' ', $part ) ) . ' Color'; $fields[] = $field; }
	$fields[] = array( 'key' => $seed . '_image_action_background_opacity', 'name' => 'image_action_background_opacity', 'label' => 'Marker Background Opacity', 'type' => 'number', 'min' => 0, 'max' => 100, 'step' => 1, 'default_value' => '', 'append' => '%' );
	return $fields;
}

/** Wrap already-escaped Stack image markup without introducing unnamed links. */
function mrn_base_stack_wrap_image_action( $markup, array $row, $image ) {
	$link = is_array( $row['image_action'] ?? null ) ? $row['image_action'] : array();
	$url = is_string( $link['url'] ?? null ) ? esc_url( $link['url'] ) : '';
	if ( '' === $url || '#' === $url ) { return $markup; }
	$label = is_string( $row['image_action_label'] ?? null ) ? trim( $row['image_action_label'] ) : '';
	$title = is_string( $link['title'] ?? null ) ? trim( $link['title'] ) : '';
	if ( '' === $label ) { $label = $title; }
	if ( '' === $label ) { $label = trim( mrn_base_stack_get_image_alt( $image ) ); }
	if ( '' === $label ) { return $markup; }
	$styles = array();
	foreach ( array( 'offset_inline', 'offset_block', 'padding_inline', 'padding_block', 'radius', 'offset_inline_mobile', 'offset_block_mobile', 'padding_block_mobile' ) as $name ) {
		$value = mrn_base_stack_validate_hero_frame_dimension( $row[ 'image_action_' . $name ] ?? '' );
		if ( '' !== $value ) { $styles[] = '--mrn-image-action-' . str_replace( '_', '-', $name ) . ':' . $value; }
	}
	foreach ( array( 'text', 'background', 'hover_background' ) as $part ) { $value = mrn_base_stack_get_frame_palette_color( $row[ 'image_action_' . $part . '_color' ] ?? '' ); if ( '' !== $value ) { $styles[] = '--mrn-image-action-' . str_replace( '_', '-', $part ) . '-color:' . $value; } }
	$opacity = mrn_base_stack_get_image_action_integer( $row['image_action_background_opacity'] ?? '', null );
	if ( null !== $opacity ) { $styles[] = '--mrn-image-action-background-opacity:' . $opacity . '%'; }
	$follow = ! empty( $row['image_action_marker'] ) && in_array( $row['image_action_follow_pointer'] ?? false, array( true, 1, '1' ), true );
	$follow_attrs = $follow ? ' data-mrn-image-action-follow="' . esc_attr( (string) mrn_base_stack_get_image_action_integer( $row['image_action_follow_inset'] ?? '', 18 ) ) . '"' : '';
	$blank = '_blank' === ( $link['target'] ?? '' );
	return '<a class="mrn-image-action"' . $follow_attrs . ' href="' . $url . '" aria-label="' . esc_attr( $label ) . '"' . ( $blank ? ' target="_blank" rel="noopener noreferrer"' : '' ) . ( $styles ? ' style="' . esc_attr( implode( ';', $styles ) ) . '"' : '' ) . '>' . $markup . ( ! empty( $row['image_action_marker'] ) ? '<span class="mrn-image-action__marker" aria-hidden="true">' . esc_html( $title ) . ' <svg width="12" height="10" viewBox="0 0 12 10" aria-hidden="true" focusable="false"><path d="M5 1l4 4-4 4M0 5h9" fill="none" stroke="currentColor" stroke-width="1.5"/></svg></span>' : '' ) . '</a>';
}

/** Bounded whole percentages/pixel inset; arbitrary CSS cannot reach runtime data. */
function mrn_base_stack_get_image_action_integer( $value, $fallback ) {
	if ( ! is_int( $value ) && ! ( is_string( $value ) && preg_match( '/^(?:0|[1-9][0-9]?|100)$/D', $value ) ) ) { return $fallback; }
	return (int) $value >= 0 && (int) $value <= 100 ? (int) $value : $fallback;
}
