<?php
/**
 * Optional native Hero frame controls; empty fields retain existing output.
 *
 * @package mrn-base-stack
 */

/** Return optional controls for basic Heroes, never body layouts. */
function mrn_base_stack_get_hero_frame_fields() {
	$labels = array(
		'media_width' => 'Media Column Maximum Width',
	);
	$fields = array();
	foreach ( $labels as $suffix => $label ) {
		$fields[] = array(
			'key' => 'field_mrn_hero_basic_block_frame_' . $suffix,
			'name' => 'hero_' . $suffix, 'label' => $label, 'type' => 'text',
			'default_value' => '', 'wrapper' => array( 'width' => '50' ),
			'instructions' => 'inner_max_width' === $suffix ? 'Optional cap for the inner canvas of Wide/Full Heroes. Use a non-negative CSS length or clamp; blank retains 78rem. Content-width Heroes keep their existing separate cap.' : 'Optional; blank retains existing formatting. Use a non-negative CSS length or clamp(minimum, preferred, maximum). Widths accept none; line heights accept unitless numbers. Small-screen widths apply at the selected media breakpoint (default 960px); mobile heading spacing follows the typography selector. Narrow heading fields use the selected heading breakpoint (default 900px); mobile heading/body typography and heading spacing use the selected typography breakpoint (default 767px). Narrow gutters/actions remain at 900px; small gutters/actions at 480px. Other gutters have their own breakpoint selector.',
		);
	}
	$fields[] = array(
		'key' => 'field_mrn_hero_basic_block_frame_heading_entrance',
		'name' => 'hero_heading_entrance', 'label' => 'Heading Entrance', 'type' => 'select',
		'choices' => array( '' => 'None', 'gentle-rise' => 'Gentle Rise on Load' ),
		'default_value' => '', 'wrapper' => array( 'width' => '50' ),
		'instructions' => 'Optional 700ms fade and short rise on page load. Respects reduced motion and is inactive while Row Effects are enabled. Applies only to the main Hero heading.',
	);
	$fields[] = array( 'key' => 'field_mrn_hero_basic_block_frame_content_fill_mobile', 'name' => 'hero_content_fill_mobile', 'label' => 'Fill Small-Screen Content Width', 'type' => 'true_false', 'ui' => 1, 'default_value' => 0, 'instructions' => 'At 767px and below, let direct Hero content and its list items wrap within the available column instead of preserving intrinsic minimum widths.' );
	$fields[] = array(
		'key' => 'field_mrn_hero_basic_block_frame_actions_mobile_flow',
		'name' => 'hero_actions_mobile_flow', 'label' => 'Small-Screen Action Flow', 'type' => 'select',
		'choices' => array( '' => 'Use Existing Wrapping', 'paired-until-375' => 'Pair from 376px; Stack at 375px' ),
		'default_value' => '', 'wrapper' => array( 'width' => '50' ),
		'instructions' => 'Optional. At 376–767px, keep two actions on one row and let the second wrap its text. At 375px and below, each action fills its own row.',
	);
	$fields[] = array(
		'key' => 'field_mrn_hero_basic_block_frame_rotation_control_placement',
		'name' => 'hero_rotation_control_placement', 'label' => 'Rotating Heading Control Placement', 'type' => 'select',
		'choices' => array( '' => 'In the Content Flow', 'overlay' => 'Compact Control in Bottom Padding' ),
		'default_value' => '', 'wrapper' => array( 'width' => '50' ),
		'instructions' => 'Optional. Keep the accessible pause and resume control while moving it out of the heading, text and action flow. Reserve bottom row padding through native Row Spacing.',
	);
	foreach ( array(
		'padding_bottom_mobile_source' => array( 'Small-Screen Bottom Padding', array( '' => 'Use Preset Mobile Dimension', 'desktop' => 'Keep Preset Desktop Dimension' ) ),
		'media_max_share' => array( 'Media Maximum Share of Inner Canvas', array( '' => 'Use Existing Grid', '40' => '40%', '50' => '50%', '60' => '60%' ) ),
		'media_drift' => array( 'Media Scroll Drift', array( '' => 'None', 'scroll' => 'Drift With Scroll (0.08 speed, 36px maximum)' ) ),
		'media_size' => array( 'Media Image Source', array( '' => 'Use Existing Hero Crop', 'full' => 'Original Aspect with Responsive Sources' ) ),
		'actions_small_layout' => array( 'Actions at 480px', array( '' => 'Use Existing Wrapping', 'full-column' => 'Full-Width Column' ) ),
	) as $suffix => $config ) {
		$fields[] = array( 'key' => 'field_mrn_hero_basic_block_frame_' . $suffix, 'name' => 'hero_' . $suffix, 'label' => $config[0], 'type' => 'select', 'choices' => $config[1], 'default_value' => '', 'wrapper' => array( 'width' => '50' ), 'instructions' => 'Optional; blank retains existing output. Outer spacing dimensions remain in native row presets. Media widths shrink to fit the canvas before stacking.' );
	}
	$fields[] = array(
		'key' => 'field_mrn_hero_basic_block_frame_body_h2_style',
		'name' => 'hero_body_h2_style', 'label' => 'Body H2 Typography', 'type' => 'select',
		'choices' => array( '' => 'Use Existing H2 Typography', 'h3' => 'Use H3 Typography' ),
		'default_value' => '', 'wrapper' => array( 'width' => '50' ),
		'instructions' => 'Keep semantic H2 headings while choosing the existing H3 type scale. This does not change heading levels or other headings.',
	);
	return $fields;
}

/** Validate lengths and simple fluid clamps without accepting arbitrary CSS. */
function mrn_base_stack_validate_hero_frame_dimension( $raw, $allow_none = false, $allow_unitless = false ) {
	if ( ! is_scalar( $raw ) ) { return ''; }
	$value = strtolower( trim( (string) $raw ) );
	if ( strlen( $value ) > 96 ) { return ''; }
	if ( $allow_none && 'none' === $value ) { return $value; }
	$atomic = static function ( $part ) use ( $allow_unitless ) {
		if ( function_exists( 'mrn_site_styles_sanitize_typography_dimension' ) ) {
			return mrn_site_styles_sanitize_typography_dimension( $part, false, $allow_unitless );
		}
		return preg_match( '/^(?:0|(?:\d+(?:\.\d+)?|\.\d+)' . ( $allow_unitless ? '(?:px|rem|em|%|vw|vh|ch)?' : '(?:px|rem|em|%|vw|vh|ch)' ) . ')$/D', $part ) ? $part : '';
	};
	if ( preg_match( '/^clamp\(([^(),]+),([^(),]+),([^(),]+)\)$/D', $value, $parts ) ) {
		$values = array_map( static function ( $part ) use ( $atomic ) { return $atomic( trim( $part ) ); }, array_slice( $parts, 1 ) );
		return in_array( '', $values, true ) ? '' : 'clamp(' . implode( ', ', $values ) . ')';
	}
	return $atomic( $value );
}

/** Resolve only explicitly authored, validated presentation properties. */
function mrn_base_stack_get_hero_frame_contract( array $row ) {
	$result = array( 'styles' => array(), 'heading_styles' => array(), 'classes' => array() );
	$widths = array( 'content_width', 'content_width_mobile', 'heading_width', 'heading_width_mobile', 'text_width' );
	foreach ( array_merge( $widths, array( 'inner_gutter', 'inner_gutter_mobile', 'inner_max_width', 'content_gap', 'heading_space_after', 'heading_space_after_mobile', 'actions_space_before', 'actions_row_gap', 'actions_column_gap', 'action_icon_height', 'action_line_height', 'media_space_after', 'body_h2_space_before', 'body_h2_space_after' ) ) as $name ) {
		$value = mrn_base_stack_validate_hero_frame_dimension( $row[ 'hero_' . $name ] ?? '', in_array( $name, $widths, true ) );
		if ( '' !== $value ) {
			$result['styles'][] = '--mrn-hero-' . str_replace( '_', '-', $name ) . ':' . $value;
			if ( 'action_icon_height' === $name ) { $result['classes'][] = 'has-hero-action-icon-height'; }
			if ( 'action_line_height' === $name ) { $result['classes'][] = 'has-hero-action-line-height'; }
			if ( 'media_space_after' === $name ) { $result['classes'][] = 'has-hero-media-space-after'; }
			if ( str_starts_with( $name, 'body_h2_space_' ) ) { $result['classes'][] = 'has-hero-' . str_replace( '_', '-', $name ); }
		}
	}
	$motion = is_array( $row['motion_settings'] ?? null ) ? $row['motion_settings'] : array();
	if ( 'gentle-rise' === ( $row['hero_heading_entrance'] ?? '' ) && empty( $motion['enabled'] ) ) {
		$result['classes'][] = 'has-hero-heading-entrance-gentle-rise';
	}
	if ( '767' === ( $row['hero_row_spacing_mobile_breakpoint'] ?? '' ) ) { $result['classes'][] = 'has-hero-row-spacing-at-767'; }
	if ( 'overlay' === ( $row['hero_rotation_control_placement'] ?? '' ) ) { $result['classes'][] = 'has-hero-rotation-control-overlay'; }
	if ( 'paired-until-375' === ( $row['hero_actions_mobile_flow'] ?? '' ) ) { $result['classes'][] = 'has-hero-actions-paired-until-375'; }
	if ( in_array( $row['hero_content_fill_mobile'] ?? 0, array( true, 1, '1' ), true ) ) { $result['classes'][] = 'has-hero-content-fill-mobile'; }
	$breakpoint = $row['hero_inner_gutter_mobile_breakpoint'] ?? '';
	if ( is_scalar( $breakpoint ) && in_array( (string) $breakpoint, array( '960', '1199' ), true ) ) {
		$result['classes'][] = 'has-hero-gutter-at-' . (string) $breakpoint;
	}
	if ( 'h3' === ( $row['hero_body_h2_style'] ?? '' ) ) { $result['classes'][] = 'has-hero-body-h2-style-h3'; }
	foreach ( array( 'heading_size' => 'font-size', 'heading_line_height' => 'line-height' ) as $name => $property ) {
		$value = mrn_base_stack_validate_hero_frame_dimension( $row[ 'hero_' . $name ] ?? '', false, 'line-height' === $property );
		if ( '' !== $value ) { $result['heading_styles'][] = $property . ':' . $value; }
	}
	$weight = $row['hero_heading_weight'] ?? '';
	if ( is_scalar( $weight ) && in_array( (string) $weight, array_map( 'strval', range( 100, 900, 100 ) ), true ) ) {
		$result['heading_styles'][] = 'font-weight:' . (string) $weight;
	}
	foreach ( array( 'heading', 'phrase', 'text' ) as $part ) {
		$slug = $row[ 'hero_' . $part . '_color' ] ?? '';
		if ( is_string( $slug ) && '' !== $slug && function_exists( 'mrn_site_colors_get_value' ) && '' !== mrn_site_colors_get_value( $slug ) ) {
			$color = 'var(' . mrn_site_colors_get_css_var( $slug ) . ')';
			if ( 'heading' === $part ) { $result['heading_styles'][] = 'color:' . $color; }
			elseif ( 'phrase' === $part ) { $result['styles'][] = '--mrn-hero-phrase-color:' . $color; $result['classes'][] = 'has-hero-phrase-color'; }
			else { $result['styles'][] = '--mrn-hero-text-color:' . $color; $result['classes'][] = 'has-hero-text-color'; }
		}
	}
	foreach ( array( 'text_size', 'text_size_mobile', 'text_line_height', 'text_line_height_mobile', 'paragraph_space_after' ) as $name ) {
		$value = mrn_base_stack_validate_hero_frame_dimension( $row[ 'hero_' . $name ] ?? '', false, str_starts_with( $name, 'text_line_height' ) );
		if ( '' !== $value ) {
			$result['styles'][] = '--mrn-hero-' . str_replace( '_', '-', $name ) . ':' . $value;
			$result['classes'][] = 'has-hero-' . str_replace( '_', '-', $name );
		}
	}
	return mrn_base_stack_add_hero_media_frame_contract( $row, $result );
}

/** Resolve only explicitly selected, validated responsive Hero properties. */
function mrn_base_stack_add_hero_media_frame_contract( array $row, array $result ) {
	foreach ( array( 'inner_gutter_narrow', 'inner_gutter_small', 'media_width', 'media_column_gap', 'media_row_gap', 'media_radius', 'heading_space_after_narrow', 'actions_space_before_narrow', 'actions_space_before_mobile', 'actions_primary_min_width_mobile', 'actions_gap_narrow', 'paragraph_space_before', 'lead_size', 'lead_line_height', 'lead_size_mobile', 'lead_line_height_mobile', 'lead_space_after' ) as $name ) {
		$value = mrn_base_stack_validate_hero_frame_dimension( $row[ 'hero_' . $name ] ?? '', false, in_array( $name, array( 'lead_line_height', 'lead_line_height_mobile' ), true ) );
		if ( '' !== $value ) { $result['styles'][] = '--mrn-hero-' . str_replace( '_', '-', $name ) . ':' . $value; $result['classes'][] = 'has-hero-' . str_replace( '_', '-', $name ); }
	}
	foreach ( array( 'padding_mobile_breakpoint' => array( '900', '640' ), 'heading_narrow_breakpoint' => array( '1100' ), 'typography_mobile_breakpoint' => array( '640', '560' ), 'padding_bottom_mobile_source' => array( 'desktop' ), 'media_stack_breakpoint' => array( '900', '767' ), 'media_shadow' => array( 'none' ), 'actions_small_layout' => array( 'full-column' ) ) as $name => $choices ) {
		$value = $row[ 'hero_' . $name ] ?? '';
		if ( is_string( $value ) && in_array( $value, $choices, true ) ) { $result['classes'][] = 'has-hero-' . str_replace( '_', '-', $name ) . '-' . $value; }
	}
	$share = $row['hero_media_max_share'] ?? '';
	if ( is_scalar( $share ) && in_array( (string) $share, array( '40', '50', '60' ), true ) ) { $result['classes'][] = 'has-hero-media-max-share'; $result['styles'][] = '--mrn-hero-media-max-share:' . (string) $share . '%'; }
	$ratio = $row['hero_media_ratio'] ?? '';
	if ( is_string( $ratio ) && preg_match( '/^\s*(\d{1,5}(?:\.\d{1,4})?)\s*\/\s*(\d{1,5}(?:\.\d{1,4})?)\s*$/D', $ratio, $parts ) && min( (float) $parts[1], (float) $parts[2] ) >= 1 && max( (float) $parts[1], (float) $parts[2] ) <= 10000 ) { $result['styles'][] = '--mrn-hero-media-ratio:' . $parts[1] . ' / ' . $parts[2]; $result['classes'][] = 'has-hero-media-ratio'; }
	foreach ( array( 'heading_size' => 'font-size', 'heading_line_height' => 'line-height' ) as $name => $property ) {
		$value = mrn_base_stack_validate_hero_frame_dimension( $row[ 'hero_' . $name . '_narrow' ] ?? '', false, 'line-height' === $property );
		if ( '' === $value ) { continue; }
		$variable = '--mrn-hero-current-' . str_replace( '_', '-', $name );
		$result['styles'][] = '--mrn-hero-' . str_replace( '_', '-', $name ) . '-narrow:' . $value;
		$result['classes'][] = 'has-hero-' . str_replace( '_', '-', $name ) . '-narrow';
		// Preserve literal inline output for all Heroes without a narrow override.
		foreach ( $result['heading_styles'] as &$style ) { if ( str_starts_with( $style, $property . ':' ) ) { $style = $property . ':var(' . $variable . ', ' . substr( $style, strlen( $property ) + 1 ) . ')'; } } unset( $style );
	}
	foreach ( array( 'heading_size' => 'font-size', 'heading_line_height' => 'line-height' ) as $name => $property ) {
		$value = mrn_base_stack_validate_hero_frame_dimension( $row[ 'hero_' . $name . '_mobile' ] ?? '', false, 'line-height' === $property );
		if ( '' === $value ) { continue; }
		$variable = '--mrn-hero-current-' . str_replace( '_', '-', $name );
		$result['styles'][] = '--mrn-hero-' . str_replace( '_', '-', $name ) . '-mobile:' . $value;
		$result['classes'][] = 'has-hero-' . str_replace( '_', '-', $name ) . '-mobile';
		foreach ( $result['heading_styles'] as &$style ) { if ( str_starts_with( $style, $property . ':' ) && ! str_contains( $style, ':var(' . $variable . ',' ) ) { $style = $property . ':var(' . $variable . ', ' . substr( $style, strlen( $property ) + 1 ) . ')'; } } unset( $style );
	}
	$weight = $row['hero_lead_weight'] ?? '';
	if ( is_scalar( $weight ) && in_array( (string) $weight, array_map( 'strval', range( 100, 900, 100 ) ), true ) ) { $result['classes'][] = 'has-hero-lead-weight'; $result['styles'][] = '--mrn-hero-lead-weight:' . (string) $weight; }
	$transform = $row['hero_lead_transform'] ?? '';
	if ( is_string( $transform ) && in_array( $transform, array( 'uppercase', 'none' ), true ) ) { $result['classes'][] = 'has-hero-lead-transform'; $result['styles'][] = '--mrn-hero-lead-transform:' . $transform; }
	$slug = $row['hero_lead_color'] ?? '';
	if ( is_string( $slug ) && '' !== $slug && function_exists( 'mrn_site_colors_get_value' ) && '' !== mrn_site_colors_get_value( $slug ) ) { $result['classes'][] = 'has-hero-lead-color'; $result['styles'][] = '--mrn-hero-lead-color:var(' . mrn_site_colors_get_css_var( $slug ) . ')'; }
	return $result;
}


/** Preserve priority and give bounded media an upper-bound responsive image hint. */
function mrn_base_stack_get_hero_media_image_attributes( array $row ) {
	$attributes = function_exists( 'mrn_base_stack_get_hero_image_attributes' ) ? mrn_base_stack_get_hero_image_attributes() : array( 'loading' => 'eager', 'fetchpriority' => 'high', 'decoding' => 'async' );
	$width = $row['hero_media_width'] ?? ''; $share = $row['hero_media_max_share'] ?? '';
	if ( is_string( $width ) && preg_match( '/^(\d{1,4}(?:\.\d{1,4})?)px$/D', $width ) && (float) $width > 0 && is_scalar( $share ) && in_array( (string) $share, array( '40', '50', '60' ), true ) ) {
		$breakpoint = $row['hero_media_stack_breakpoint'] ?? '';
		$breakpoint = in_array( $breakpoint, array( '900', '767' ), true ) ? $breakpoint : '960';
		$attributes['sizes'] = '(max-width: ' . $breakpoint . 'px) 100vw, min(' . $width . ', ' . (string) $share . 'vw)';
	}
	return $attributes;
}
