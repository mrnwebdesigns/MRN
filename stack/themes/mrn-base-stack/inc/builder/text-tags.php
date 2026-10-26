<?php
/** Native structured text tags and optional linked Body Text headings. */
function mrn_base_stack_get_text_tag_fields( $seed ) {
	$fields = array(
		array( 'key' => $seed . '_heading_link', 'name' => 'heading_link', 'label' => 'Heading Link', 'type' => 'link', 'return_format' => 'array', 'instructions' => 'Optional destination for the existing Heading. The heading text remains its accessible link name.' ),
		array( 'key' => $seed . '_list_presentation', 'name' => 'list_presentation', 'label' => 'Tag Presentation', 'type' => 'select', 'choices' => array( '' => 'Plain Tags', 'pills' => 'Pills' ), 'default_value' => '' ),
		array( 'key' => $seed . '_text_tags', 'name' => 'text_tags', 'label' => 'Tags', 'type' => 'repeater', 'max' => 5, 'layout' => 'row', 'button_label' => 'Add Tag', 'sub_fields' => array(
			array( 'key' => $seed . '_tag_text', 'name' => 'text', 'label' => 'Text', 'type' => 'text', 'required' => 1 ),
			array( 'key' => $seed . '_tag_url', 'name' => 'url', 'label' => 'Optional URL', 'type' => 'text', 'instructions' => 'Use a complete or site-relative URL. Blank or # renders a text tag. Unsafe URL protocols are never rendered as links.' ),
		) ),
	);
	$fields[] = array( 'key' => $seed . '_heading_link_icon', 'name' => 'heading_link_icon', 'label' => 'Heading Action Treatment', 'type' => 'select', 'choices' => array( '' => 'Use Existing Link Style', 'arrow-reveal' => 'Reveal Arrow on Hover or Focus', 'arrow' => 'Always Show Arrow' ), 'default_value' => '' );
	foreach ( array( 'size', 'gap' ) as $part ) { $fields[] = array( 'key' => $seed . '_heading_link_icon_' . $part, 'name' => 'heading_link_icon_' . $part, 'label' => 'Heading Action Icon ' . ucfirst( $part ), 'type' => 'text', 'instructions' => 'Optional non-negative CSS length.' ); }
	$field = mrn_base_stack_get_background_color_field( $seed . '_heading_link_hover_color' ); $field['name'] = 'heading_link_hover_color'; $field['label'] = 'Heading Action Hover/Focus Color'; $fields[] = $field;
	foreach ( array( 'gap' => 'Space Between Tags', 'space_before' => 'Space Before Tags', 'padding_inline' => 'Tag Horizontal Padding', 'padding_block' => 'Tag Vertical Padding', 'radius' => 'Tag Corner Radius', 'line_height' => 'Tag Line Height', 'font_size' => 'Tag Font Size', 'font_size_mobile' => 'Small-Screen Tag Font Size (767px)' ) as $name => $label ) {
		$fields[] = array( 'key' => $seed . '_tag_' . $name, 'name' => 'tag_' . $name, 'label' => $label, 'type' => 'text', 'instructions' => 'Optional non-negative CSS length or clamp(). Section spacing uses row presets.', 'wrapper' => array( 'width' => '50' ) );
	}
	foreach ( array( 'text', 'background' ) as $part ) {
		$field = mrn_base_stack_get_background_color_field( $seed . '_tag_' . $part . '_color' );
		$field['name'] = 'tag_' . $part . '_color'; $field['label'] = 'Tag ' . ucfirst( $part ) . ' Color'; $fields[] = $field;
	}
	return $fields;
}

/** Format a heading using its existing text, keeping optional destinations separate. */
function mrn_base_stack_get_body_heading_markup( $heading, $link, array $row = array() ) {
	$html = mrn_base_stack_format_heading_inline_html( $heading );
	$url = is_array( $link ) && is_string( $link['url'] ?? null ) ? esc_url( $link['url'] ) : '';
	if ( '' === $url || '#' === $url ) { return $html; }
	$blank = '_blank' === ( $link['target'] ?? '' );
	$styles = array(); $icon = ''; $class = '';
	if ( in_array( $row['heading_link_icon'] ?? '', array( 'arrow-reveal', 'arrow' ), true ) ) {
		$class = ' class="mrn-heading-action mrn-heading-action--' . ( 'arrow' === $row['heading_link_icon'] ? 'always' : 'reveal' ) . '"';
		foreach ( array( 'size', 'gap' ) as $part ) { $value = mrn_base_stack_validate_hero_frame_dimension( $row[ 'heading_link_icon_' . $part ] ?? '' ); if ( '' !== $value ) { $styles[] = '--mrn-heading-action-icon-' . $part . ':' . $value; } }
		$color = mrn_base_stack_get_frame_palette_color( $row['heading_link_hover_color'] ?? '' ); if ( '' !== $color ) { $styles[] = '--mrn-heading-action-hover-color:' . $color; }
		$icon = '<svg class="mrn-heading-action__icon" width="12" height="10" viewBox="0 0 12 10" aria-hidden="true" focusable="false"><path d="M4.608 9.952L9.584 4.96 4.624 0H6.4L11.36 4.976 6.384 9.952ZM0 4.32H10.56V5.632H0Z" fill="currentColor"/></svg>';
	}
	return '<a' . $class . ' href="' . $url . '"' . ( $blank ? ' target="_blank" rel="noopener noreferrer"' : '' ) . ( $styles ? ' style="' . esc_attr( implode( ';', $styles ) ) . '"' : '' ) . '>' . $html . $icon . '</a>';
}

/** Render ordered/repeated structured tags without turning empty targets into links. */
function mrn_base_stack_get_text_tags_markup( array $row ) {
	$items = is_array( $row['text_tags'] ?? null ) ? $row['text_tags'] : array();
	$html = '';
	foreach ( $items as $item ) {
		if ( ! is_array( $item ) || ! is_string( $item['text'] ?? null ) || '' === trim( $item['text'] ) ) { continue; }
		$text = esc_html( $item['text'] );
		$url = is_string( $item['url'] ?? null ) ? esc_url( trim( $item['url'] ) ) : '';
		$html .= '<li>' . ( '' !== $url && '#' !== $url ? '<a href="' . $url . '">' . $text . '</a>' : '<span>' . $text . '</span>' ) . '</li>';
	}
	if ( '' === $html ) { return ''; }
	$styles = array();
	foreach ( array( 'gap', 'space_before', 'padding_inline', 'padding_block', 'radius', 'line_height', 'font_size', 'font_size_mobile' ) as $name ) {
		$value = mrn_base_stack_get_content_frame_dimension( $row[ 'tag_' . $name ] ?? '', str_starts_with( $name, 'font_size' ) ? 'text_size' : $name );
		if ( '' !== $value ) { $styles[] = '--mrn-tag-' . str_replace( '_', '-', $name ) . ':' . $value; }
	}
	foreach ( array( 'text', 'background' ) as $part ) {
		$value = mrn_base_stack_get_frame_palette_color( $row[ 'tag_' . $part . '_color' ] ?? '' );
		if ( '' !== $value ) { $styles[] = '--mrn-tag-' . $part . '-color:' . $value; }
	}
	return '<ul class="mrn-text-tags' . ( 'pills' === ( $row['list_presentation'] ?? '' ) ? ' mrn-text-tags--pills' : '' ) . '"' . ( $styles ? ' style="' . esc_attr( implode( ';', $styles ) ) . '"' : '' ) . '>' . $html . '</ul>';
}
