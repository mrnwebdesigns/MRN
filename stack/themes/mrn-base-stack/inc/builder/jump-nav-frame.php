<?php
/** Optional native inline section navigation; its sizes, spacing and colors come from the theme's CSS. */
function mrn_base_stack_get_jump_nav_frame_fields( $seed ) {
	$fields = array(
		array( 'key' => $seed . '_jump_nav_aria_label', 'name' => 'jump_nav_aria_label', 'label' => 'Navigation Accessible Name', 'type' => 'text', 'instructions' => 'Optional explicit navigation name. Leave empty to use the visible heading or the existing section label.' ),
		array( 'key' => $seed . '_jump_nav_presentation', 'name' => 'jump_nav_presentation', 'label' => 'Navigation Presentation', 'type' => 'select', 'choices' => array( 'standard' => 'Standard', 'inline-pills' => 'Inline Heading and Pills' ), 'default_value' => 'standard', 'multiple' => 0, 'allow_null' => 0, 'return_format' => 'value' ),
	);
	return $fields;
}

/** Inline Pills marks the row; a theme's CSS styles it. Other presentations add nothing. */
function mrn_base_stack_get_jump_nav_frame_contract( array $row ) {
	$result = array( 'classes' => array(), 'attributes' => array() );
	if ( 'inline-pills' === ( $row['jump_nav_presentation'] ?? '' ) ) { $result['classes'][] = 'has-inline-jump-nav'; }
	return $result;
}
