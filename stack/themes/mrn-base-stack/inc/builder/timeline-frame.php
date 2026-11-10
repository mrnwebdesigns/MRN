<?php
/** Optional contained timeline geometry; the native row presets own outer spacing. */
function mrn_base_stack_get_timeline_frame_fields( $seed ) {
	$fields = array(
		array( 'key' => $seed . '_timeline_frame', 'name' => 'timeline_frame', 'label' => 'Timeline Frame', 'type' => 'select', 'choices' => array( '' => 'Use Existing Frame', 'contained' => 'Contained Timeline' ), 'default_value' => '' ),
		array( 'key' => $seed . '_timeline_skip_position', 'name' => 'timeline_skip_position', 'label' => 'Timeline Skip Link Position', 'type' => 'select', 'choices' => array( '' => 'Before Timeline', 'media' => 'Below Timeline Image' ), 'default_value' => '' ),
		array( 'key' => $seed . '_timeline_step_style', 'name' => 'timeline_step_style', 'label' => 'Timeline Step Style', 'type' => 'select', 'choices' => array( '' => 'Use Existing Steps', 'numbered' => 'Numbered Steps with Inline Copy' ), 'default_value' => '' ),
		array( 'key' => $seed . '_timeline_step_action_icon', 'name' => 'timeline_step_action_icon', 'label' => 'Timeline Step Action Icon', 'type' => 'select', 'choices' => array( '' => 'No Icon', 'arrow' => 'Decorative Arrow' ), 'default_value' => '' ),
	);
	$dimensions = array(
		'content_width' => 'Timeline Maximum Width',
		'inner_gutter' => 'Timeline Inner Gutter',
		'inner_gutter_tablet' => 'Timeline Inner Gutter at 1199px',
		'column_gap' => 'Space Between Timeline Columns',
		'column_gap_tablet' => 'Space Between Columns at 1199px',
		'media_width' => 'Timeline Image Column Width',
		'media_width_tablet' => 'Timeline Image Column Width at 1199px',
		'skip_gap' => 'Space Above Skip Link',
		'step_inset' => 'Space Reserved for Step Number',
		'step_inset_mobile' => 'Space Reserved for Number on Phone',
		'step_gap' => 'Space Between Steps',
		'step_action_gap' => 'Space Before Step Action',
		'step_plain_copy_space' => 'Space After a Step Without an Action',
		'step_mobile_offset' => 'Space Before Steps on Phone',
		'step_icon_gap' => 'Space Before Step Action Icon',
		'heading_space_after' => 'Space After Timeline Heading',
		'subheading_width' => 'Timeline Introduction Maximum Width',
		'header_space_after' => 'Space After Timeline Introduction',
	);
	foreach ( $dimensions as $name => $label ) {
		$fields[] = array( 'key' => $seed . '_timeline_' . $name, 'name' => 'timeline_' . $name, 'label' => $label, 'type' => 'text', 'instructions' => 'Optional non-negative CSS length or clamp(). Outer section spacing uses native row presets.' );
	}
	foreach ( $fields as &$field ) {
		$conditions = array( array( 'field' => $seed . '_card_presentation', 'operator' => '==', 'value' => 'timeline' ) );
		if ( 'timeline_frame' !== $field['name'] ) {
			$conditions[] = array( 'field' => $seed . '_timeline_frame', 'operator' => '==', 'value' => 'contained' );
		}
		if ( str_starts_with( $field['name'], 'timeline_step_' ) && 'timeline_step_style' !== $field['name'] ) {
			$conditions[] = array( 'field' => $seed . '_timeline_step_style', 'operator' => '==', 'value' => 'numbered' );
		}
		$field['conditional_logic'] = array( $conditions );
	}
	unset( $field );
	return $fields;
}

/** Reject dormant controls and CSS injection before adding editable dimensions. */
function mrn_base_stack_get_timeline_frame_contract( array $row ) {
	$result = array( 'active' => false, 'media_skip' => false, 'numbered_steps' => false, 'arrow_actions' => false, 'styles' => array() );
	if ( 'timeline' !== ( $row['card_presentation'] ?? '' ) || 'contained' !== ( $row['timeline_frame'] ?? '' ) ) {
		return $result;
	}
	$result['active'] = true;
	$result['media_skip'] = 'media' === ( $row['timeline_skip_position'] ?? '' ) && ! empty( $row['timeline_image'] );
	$result['numbered_steps'] = 'numbered' === ( $row['timeline_step_style'] ?? '' );
	$result['arrow_actions'] = $result['numbered_steps'] && 'arrow' === ( $row['timeline_step_action_icon'] ?? '' );
	foreach ( array( 'content_width', 'inner_gutter', 'inner_gutter_tablet', 'column_gap', 'column_gap_tablet', 'media_width', 'media_width_tablet', 'skip_gap', 'step_inset', 'step_inset_mobile', 'step_gap', 'step_action_gap', 'step_plain_copy_space', 'step_mobile_offset', 'step_icon_gap', 'heading_space_after', 'subheading_width', 'header_space_after' ) as $name ) {
		if ( str_starts_with( $name, 'step_' ) && ! $result['numbered_steps'] ) { continue; }
		if ( 'step_icon_gap' === $name && ! $result['arrow_actions'] ) { continue; }
		$value = mrn_base_stack_validate_card_frame_dimension( $row[ 'timeline_' . $name ] ?? '' );
		if ( '' !== $value ) {
			$result['styles'][] = '--mrn-timeline-' . str_replace( '_', '-', $name ) . ':' . $value;
		}
	}
	return $result;
}
