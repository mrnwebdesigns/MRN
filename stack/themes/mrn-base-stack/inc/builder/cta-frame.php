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
		'paragraph_space_after' => 'Space After Body Paragraphs',
		'actions_space_before' => 'Space Before Actions',
		'action_min_width' => 'Action Minimum Width',
	) as $name => $label ) {
		$fields[] = array(
			'key' => $seed . '_cta_' . $name, 'name' => 'cta_' . $name, 'label' => $label, 'type' => 'text',
			'default_value' => '', 'wrapper' => array( 'width' => '50' ),
			'instructions' => 'Optional non-negative CSS length or clamp(minimum, preferred, maximum). Width accepts none. Blank retains the stacked frame default. Paragraph spacing includes the last paragraph.',
		);
	}
	$fields[] = array(
		'key' => $seed . '_cta_inner_gutter_mobile_breakpoint', 'name' => 'cta_inner_gutter_mobile_breakpoint',
		'label' => 'Small-Screen Gutter Breakpoint', 'type' => 'select',
		'choices' => array( '' => '767px (Default)', '767' => '767px', '960' => '960px', '1199' => '1199px' ),
		'default_value' => '', 'wrapper' => array( 'width' => '50' ),
	);
	$fields[] = array(
		'key' => $seed . '_cta_content_alignment', 'name' => 'cta_content_alignment', 'label' => 'Content and Action Alignment',
		'type' => 'select', 'choices' => array( '' => 'Start (Default)', 'start' => 'Start', 'center' => 'Center', 'end' => 'End' ),
		'default_value' => '', 'wrapper' => array( 'width' => '50' ),
	);
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
	foreach ( array( 'inner_gutter', 'inner_gutter_mobile', 'content_width', 'content_gap', 'text_space_before', 'paragraph_space_after', 'actions_space_before', 'action_min_width' ) as $name ) {
		$value = mrn_base_stack_validate_hero_frame_dimension( $row[ 'cta_' . $name ] ?? '', 'content_width' === $name );
		if ( '' !== $value ) { $styles[] = '--mrn-cta-frame-' . str_replace( '_', '-', $name ) . ':' . $value; }
	}
	$breakpoint = $row['cta_inner_gutter_mobile_breakpoint'] ?? '';
	if ( in_array( $breakpoint, array( '960', '1199' ), true ) ) { $result['classes'][] = 'has-cta-gutter-at-' . $breakpoint; }
	$alignment = $row['cta_content_alignment'] ?? '';
	if ( in_array( $alignment, array( 'start', 'center', 'end' ), true ) ) {
		$styles[] = '--mrn-cta-frame-alignment:' . $alignment;
		$styles[] = '--mrn-cta-frame-actions-alignment:' . array( 'start' => 'flex-start', 'center' => 'center', 'end' => 'flex-end' )[ $alignment ];
	}
	$slug = $row['bg_color'] ?? '';
	if ( is_string( $slug ) && function_exists( 'mrn_site_colors_get_all' ) && function_exists( 'mrn_site_colors_get_css_var' ) ) {
		foreach ( mrn_site_colors_get_all() as $color ) {
			if ( '' !== $slug && $slug === ( $color['slug'] ?? '' ) ) { $styles[] = '--mrn-cta-frame-bg:var(' . mrn_site_colors_get_css_var( $slug ) . ')'; break; }
		}
	}
	if ( $styles ) { $result['attributes']['style'] = implode( ';', $styles ); }
	return $result;
}
