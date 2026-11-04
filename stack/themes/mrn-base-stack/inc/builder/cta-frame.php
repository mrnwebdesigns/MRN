<?php
/**
 * Optional native stacked CTA frame; existing rows retain their presentation.
 *
 * @package mrn-base-stack
 */

/** Return native frame controls using each layout's existing unique field seed. */
function mrn_base_stack_get_cta_frame_fields( $seed ) {
	$fields = array(
		array(
			'key' => $seed . '_cta_frame', 'name' => 'cta_frame', 'label' => 'CTA Frame', 'type' => 'select',
			'choices' => array( '' => 'Use Existing Frame', 'stacked' => 'Stacked Content' ), 'default_value' => '',
			'instructions' => 'Stacked Content places content and actions in one column. The outer row owns margin, padding and motion once; use the row spacing presets for section spacing.',
		),
	);
	foreach ( array(
		'inner_gutter' => 'Inner Horizontal Gutter',
		'inner_gutter_mobile' => 'Small-Screen Inner Horizontal Gutter',
		'content_width' => 'Content Maximum Width',
		'content_gap' => 'Space Between Content Elements',
		'text_space_before' => 'Space Before Body Text',
		'text_space_after' => 'Body Text Bottom Padding',
		'text_space_after_narrow' => 'Small-Screen Body Text Bottom Padding',
		'heading_space_after_narrow' => 'Space After Heading at 900px',
		'action_border_width' => 'Button Border Width',
		'paragraph_space_after' => 'Space After Body Paragraphs',
		'actions_space_before' => 'Space Before Actions',
		'action_min_width' => 'Action Minimum Width',
		'action_icon_height' => 'Action Media Icon Height',
		'heading_size' => 'Heading Font Size',
		'heading_size_tablet' => 'Compact Heading Font Size (991px)',
		'heading_size_mobile' => 'Small Heading Font Size (600px)',
		'heading_line_height' => 'Heading Line Height',
		'heading_space_after' => 'Space After Heading',
		'heading_space_after_tablet' => 'Compact Space After Heading (991px)',
		'heading_space_after_mobile' => 'Small Space After Heading (600px)',
		'actions_gap' => 'Gap Between Actions',
		'actions_gap_tablet' => 'Compact Gap Between Actions (991px)',
		'actions_gap_mobile' => 'Small Gap Between Actions (600px)',
		'action_padding_inline' => 'Button Horizontal Padding',
		'action_padding_block' => 'Button Vertical Padding',
		'action_padding_inline_mobile' => 'Small Button Horizontal Padding (600px)',
		'action_padding_block_mobile' => 'Small Button Vertical Padding (600px)',
		'action_radius' => 'Button Corner Radius',
		'action_size' => 'Button Font Size',
		'action_size_mobile' => 'Small Button Font Size (600px)',
		'action_line_height' => 'Button Line Height',
		'action_icon_width' => 'Button Media Icon Width',
		'text_size' => 'Body Font Size',
		'text_line_height' => 'Body Line Height',
	) as $name => $label ) {
		$fields[] = array(
			'key' => $seed . '_cta_' . $name, 'name' => 'cta_' . $name, 'label' => $label, 'type' => 'text',
			'default_value' => '', 'wrapper' => array( 'width' => '50' ),
			'instructions' => 'Optional non-negative CSS length, clamp(minimum, preferred, maximum), or Site Text size/line token for typography. Button controls apply only with Custom Button Frame. Width accepts none; line heights accept unitless numbers. Blank retains the stacked frame default. Paragraph spacing includes the last paragraph.',
		);
	}
	$fields[] = array(
		'key' => $seed . '_cta_inner_gutter_mobile_breakpoint', 'name' => 'cta_inner_gutter_mobile_breakpoint',
		'label' => 'Small-Screen Gutter Breakpoint', 'type' => 'select',
		'choices' => array( '' => '767px (Default)', '600' => '600px', '767' => '767px', '960' => '960px', '1199' => '1199px' ),
		'default_value' => '', 'wrapper' => array( 'width' => '50' ),
	);
	$fields[] = array(
		'key' => $seed . '_cta_content_alignment', 'name' => 'cta_content_alignment', 'label' => 'Content and Action Alignment',
		'type' => 'select', 'choices' => array( '' => 'Start (Default)', 'start' => 'Start', 'center' => 'Center', 'end' => 'End' ),
		'default_value' => '', 'wrapper' => array( 'width' => '50' ),
	);
	$fields[] = array( 'key' => $seed . '_cta_text_space_after_narrow_breakpoint', 'name' => 'cta_text_space_after_narrow_breakpoint', 'label' => 'Body Bottom Padding Breakpoint', 'type' => 'select', 'choices' => array( '' => '900px (Default)', '1199' => '1199px' ), 'default_value' => '' );
	$fields[] = array( 'key' => $seed . '_cta_action_layout', 'name' => 'cta_action_layout', 'label' => 'Button Label and Icon Flow', 'type' => 'select', 'choices' => array( '' => 'Use Existing Flex Layout', 'inline' => 'Inline Text and Icon' ), 'default_value' => '', 'instructions' => 'Optional for Custom Button Frame. Inline flow keeps the icon with the final text line when a button wraps.' );
	$fields[] = array( 'key' => $seed . '_cta_paragraph_typography', 'name' => 'cta_paragraph_typography', 'label' => 'Body Paragraph Typography', 'type' => 'select', 'choices' => array( '' => 'Use Existing Paragraph Rules', 'inherit-body' => 'Apply This Body Typography' ), 'default_value' => '', 'instructions' => 'Apply the selected body size and line height to direct body paragraphs, including paragraphs created by the editor. Blank preserves existing paragraph rules.' );
	$fields[] = array( 'key' => $seed . '_cta_content_fill', 'name' => 'cta_content_fill', 'label' => 'Fill Parent and Align Actions at Bottom', 'type' => 'true_false', 'ui' => 1, 'default_value' => 0, 'instructions' => 'Fill an available parent height and align actions at the bottom. Space Before Actions remains their minimum spacing. Blank retains the current stacked flow.' );
	$fields[] = array( 'key' => $seed . '_cta_action_frame', 'name' => 'cta_action_frame', 'label' => 'Custom Button Frame', 'type' => 'true_false', 'default_value' => 0, 'ui' => 1, 'instructions' => 'Apply this row’s button dimensions, typography and Site Colors uniformly to button actions. Text links retain their own presentation.' );
	$fields[] = array( 'key' => $seed . '_cta_action_font', 'name' => 'cta_action_font', 'label' => 'Button Font Family', 'type' => 'select', 'choices' => array( '' => 'Inherit', 'heading' => 'Heading', 'body' => 'Body' ), 'default_value' => '' );
	$fields[] = array( 'key' => $seed . '_cta_action_weight', 'name' => 'cta_action_weight', 'label' => 'Button Font Weight', 'type' => 'select', 'choices' => array( '' => 'Inherit' ) + array_combine( range( 100, 900, 100 ), range( 100, 900, 100 ) ), 'default_value' => '' );
	$fields[] = array( 'key' => $seed . '_cta_heading_weight', 'name' => 'cta_heading_weight', 'label' => 'Heading Font Weight', 'type' => 'select', 'choices' => array( '' => 'Use Existing Weight' ) + array_combine( range( 100, 900, 100 ), range( 100, 900, 100 ) ), 'default_value' => '' );
	foreach ( array( 'heading', 'text', 'action_text', 'action_background', 'action_hover_background', 'action_border', 'action_hover_border' ) as $part ) {
		$field = mrn_base_stack_get_background_color_field( $seed . '_cta_' . $part . '_color' );
		$field['name'] = 'cta_' . $part . '_color'; $field['label'] = ucwords( str_replace( '_', ' ', $part ) ) . ' Color'; $fields[] = $field;
	}

	foreach ( $fields as &$field ) {
		if ( 'cta_frame' !== $field['name'] ) {
			$field['conditional_logic'] = array( array( array( 'field' => $seed . '_cta_frame', 'operator' => '==', 'value' => 'stacked' ) ) );
		}
	}
	unset( $field );
	return $fields;
}

/** Resolve only an explicitly selected frame and validated properties. */
function mrn_base_stack_get_cta_frame_contract( array $row ) {
	$result = array( 'classes' => array(), 'attributes' => array() );
	if ( 'stacked' !== ( $row['cta_frame'] ?? '' ) ) { return $result; }
	$result['classes'][] = 'has-cta-frame';
	$styles = array();
	foreach ( mrn_base_stack_get_cta_frame_fields( 'field_mrn_cta_contract' ) as $field ) {
		$name = substr( $field['name'], 4 );
		if ( 'text' !== $field['type'] ) { continue; }
		$raw = $row[ $field['name'] ] ?? '';
		$base = preg_replace( '/_(?:tablet|mobile)$/D', '', $name );
		$line = str_ends_with( $base, 'line_height' );
		$token = is_string( $raw ) && ( $line || str_ends_with( $base, '_size' ) ) && preg_match( '/^var\(--site-text-(?:h[1-6]|body-[1-4]|button-[1-3]|label-[1-3])-' . ( $line ? 'line' : 'size' ) . '\)$/D', $raw );
		$value = $token ? $raw : mrn_base_stack_validate_hero_frame_dimension( $raw, 'content_width' === $name, $line );
		if ( '' !== $value ) {
			$styles[] = '--mrn-cta-frame-' . str_replace( '_', '-', $name ) . ':' . $value;
			if ( in_array( $base, array( 'heading_size', 'heading_line_height', 'heading_space_after', 'text_size', 'text_line_height', 'actions_gap' ), true ) ) { $result['classes'][] = 'has-cta-' . str_replace( '_', '-', $base ); }
		}
	}
	foreach ( array( 'text_space_after', 'text_space_after_narrow', 'heading_space_after_narrow', 'action_border_width' ) as $name ) {
		if ( '' !== mrn_base_stack_validate_hero_frame_dimension( $row[ 'cta_' . $name ] ?? '' ) ) { $result['classes'][] = 'has-cta-' . str_replace( '_', '-', $name ); }
	}
	if ( '1199' === ( $row['cta_text_space_after_narrow_breakpoint'] ?? '' ) && '' !== mrn_base_stack_validate_hero_frame_dimension( $row['cta_text_space_after_narrow'] ?? '' ) ) { $result['classes'][] = 'has-cta-text-space-after-at-1199'; }
	if ( 'inline' === ( $row['cta_action_layout'] ?? '' ) ) { $result['classes'][] = 'has-cta-action-layout-inline'; }
	if ( 'inherit-body' === ( $row['cta_paragraph_typography'] ?? '' ) ) { $result['classes'][] = 'has-cta-paragraph-body-typography'; }
	if ( in_array( $row['cta_content_fill'] ?? 0, array( true, 1, '1' ), true ) ) { $result['classes'][] = 'has-cta-content-fill'; }
	if ( in_array( $row['cta_action_frame'] ?? 0, array( true, 1, '1' ), true ) ) { $result['classes'][] = 'has-cta-action-frame'; }
	$font = $row['cta_action_font'] ?? '';
	if ( in_array( $font, array( 'heading', 'body' ), true ) ) { $styles[] = '--mrn-cta-frame-action-font:var(--site-font-' . $font . ')'; }
	$action_weight = $row['cta_action_weight'] ?? '';
	if ( is_scalar( $action_weight ) && in_array( (string) $action_weight, array_map( 'strval', range( 100, 900, 100 ) ), true ) ) { $styles[] = '--mrn-cta-frame-action-weight:' . $action_weight; }
	$breakpoint = $row['cta_inner_gutter_mobile_breakpoint'] ?? '';
	if ( in_array( $breakpoint, array( '600', '960', '1199' ), true ) ) { $result['classes'][] = 'has-cta-gutter-at-' . $breakpoint; }
	$alignment = $row['cta_content_alignment'] ?? '';
	if ( in_array( $alignment, array( 'start', 'center', 'end' ), true ) ) {
		$styles[] = '--mrn-cta-frame-alignment:' . $alignment;
		$styles[] = '--mrn-cta-frame-actions-alignment:' . array( 'start' => 'flex-start', 'center' => 'center', 'end' => 'flex-end' )[ $alignment ];
	}
	$weight = $row['cta_heading_weight'] ?? '';
	if ( is_scalar( $weight ) && in_array( (string) $weight, array_map( 'strval', range( 100, 900, 100 ) ), true ) ) { $styles[] = '--mrn-cta-frame-heading-weight:' . $weight; $result['classes'][] = 'has-cta-heading-weight'; }
	foreach ( array( 'heading', 'text', 'action_text', 'action_background', 'action_hover_background', 'action_border', 'action_hover_border' ) as $part ) {
		$value = mrn_base_stack_get_frame_palette_color( $row[ 'cta_' . $part . '_color' ] ?? '' );
		if ( '' !== $value ) { $styles[] = '--mrn-cta-frame-' . str_replace( '_', '-', $part ) . '-color:' . $value; $result['classes'][] = 'has-cta-' . str_replace( '_', '-', $part ) . '-color'; }
	}

	$slug = $row['bg_color'] ?? '';
	if ( is_string( $slug ) && function_exists( 'mrn_site_colors_get_all' ) && function_exists( 'mrn_site_colors_get_css_var' ) ) {
		foreach ( mrn_site_colors_get_all() as $color ) {
			if ( '' !== $slug && $slug === ( $color['slug'] ?? '' ) ) { $styles[] = '--mrn-cta-frame-bg:var(' . mrn_site_colors_get_css_var( $slug ) . ')'; break; }
		}
	}
	$result['classes'] = array_values( array_unique( $result['classes'] ) );
	if ( $styles ) { $result['attributes']['style'] = implode( ';', $styles ); }
	return $result;
}
