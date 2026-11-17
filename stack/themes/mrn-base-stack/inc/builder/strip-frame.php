<?php
/** Optional responsive horizontal gallery; its sizes and spacing come from the theme's CSS, section spacing from row presets. */
function mrn_base_stack_get_strip_frame_fields( $seed ) {
 $fields = array(
  array( 'key' => $seed . '_strip_frame', 'name' => 'strip_frame', 'label' => 'Strip Frame', 'type' => 'select', 'choices' => array( '' => 'Use Existing Frame', 'responsive' => 'Responsive Media Strip' ), 'default_value' => '' ),
  array( 'key' => $seed . '_strip_behavior', 'name' => 'strip_behavior', 'label' => 'Strip Scroll Behavior', 'type' => 'select', 'choices' => array( '' => 'Existing Pinned Scroll', 'flow' => 'Flow with Page Scroll' ), 'default_value' => '' ),
  array( 'key' => $seed . '_strip_scroll_pace', 'name' => 'strip_scroll_pace', 'label' => 'Page Scroll Pixels per Horizontal Pixel', 'type' => 'number', 'min' => 0.1, 'max' => 10, 'step' => 0.1, 'default_value' => 1.6, 'instructions' => 'Flow scroll only, above 768px. Keyboard focus, reduced motion and small screens use ordinary horizontal scrolling.' ),
  array( 'key' => $seed . '_strip_header_layout', 'name' => 'strip_header_layout', 'label' => 'Strip Header Layout', 'type' => 'select', 'choices' => array( '' => 'Standard', 'inline-action' => 'Heading and Action Before Images' ), 'default_value' => '' ),
  array( 'key' => $seed . '_strip_stagger', 'name' => 'strip_stagger', 'label' => 'Strip Image Stagger', 'type' => 'select', 'choices' => array( '' => 'Flat', 'alternating' => 'Offset Even Images' ), 'default_value' => '' ),
 );
 foreach ( $fields as &$field ) {
  $conditions = array( array( 'field' => $seed . '_showcase_presentation', 'operator' => '==', 'value' => 'scroll-strip' ) );
  if ( 'strip_frame' !== $field['name'] ) { $conditions[] = array( 'field' => $seed . '_strip_frame', 'operator' => '==', 'value' => 'responsive' ); }
  $field['conditional_logic'] = array( $conditions );
 }
 unset( $field ); return $fields;
}

/** Ignore dormant controls; only reviewed choices emit classes. */
function mrn_base_stack_get_strip_frame_contract( array $row ) {
 $out = array( 'classes' => array(), 'attributes' => array() );
 if ( 'scroll-strip' !== ( $row['showcase_presentation'] ?? '' ) || 'responsive' !== ( $row['strip_frame'] ?? '' ) ) { return $out; }
 $out['classes'][] = 'has-strip-frame';
 if ( 'inline-action' === ( $row['strip_header_layout'] ?? '' ) ) { $out['classes'][] = 'has-strip-inline-action'; }
 if ( 'alternating' === ( $row['strip_stagger'] ?? '' ) ) { $out['classes'][] = 'has-strip-alternating-stagger'; }
 return $out;
}

/** Flow is explicit; unset/invalid controls retain the existing pinned behavior. */
function mrn_base_stack_get_strip_behavior_attributes( array $row ) {
 if ( 'scroll-strip' !== ( $row['showcase_presentation'] ?? '' ) || 'responsive' !== ( $row['strip_frame'] ?? '' ) || 'flow' !== ( $row['strip_behavior'] ?? '' ) ) { return array(); }
 $pace = $row['strip_scroll_pace'] ?? 1.6;
 if ( ! is_numeric( $pace ) || (float) $pace < 0.1 || (float) $pace > 10 ) { $pace = 1.6; }
 return array( 'data-mrn-strip-behavior' => 'flow', 'data-mrn-strip-pace' => (string) (float) $pace );
}
