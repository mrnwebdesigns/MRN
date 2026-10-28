<?php
/** Optional responsive horizontal gallery; row presets own all section spacing. */
function mrn_base_stack_get_strip_frame_fields( $seed ) {
 $fields = array(
  array( 'key' => $seed . '_strip_frame', 'name' => 'strip_frame', 'label' => 'Strip Frame', 'type' => 'select', 'choices' => array( '' => 'Use Existing Frame', 'responsive' => 'Responsive Media Strip' ), 'default_value' => '' ),
  array( 'key' => $seed . '_strip_behavior', 'name' => 'strip_behavior', 'label' => 'Strip Scroll Behavior', 'type' => 'select', 'choices' => array( '' => 'Existing Pinned Scroll', 'flow' => 'Flow with Page Scroll' ), 'default_value' => '' ),
  array( 'key' => $seed . '_strip_scroll_pace', 'name' => 'strip_scroll_pace', 'label' => 'Page Scroll Pixels per Horizontal Pixel', 'type' => 'number', 'min' => 0.1, 'max' => 10, 'step' => 0.1, 'default_value' => 1.6, 'instructions' => 'Flow scroll only, above 768px. Keyboard focus, reduced motion and small screens use ordinary horizontal scrolling.' ),
 );
 foreach ( array( '' => 'Default', '_wide' => 'At 1280px', '_tablet' => 'At 1024px', '_mobile' => 'At 768px', '_small' => 'At 480px' ) as $suffix => $label ) {
  foreach ( array( 'width' => 'Image Width', 'height' => 'Image Height', 'gap' => 'Gap Between Images', 'gutter' => 'Track Edge Gutter' ) as $name => $title ) { $fields[] = array( 'key' => $seed . '_strip_' . $name . $suffix, 'name' => 'strip_' . $name . $suffix, 'label' => $title . ' — ' . $label, 'type' => 'text', 'instructions' => 'Optional non-negative bounded length or clamp(). Blank inherits the larger viewport. Section margin/padding uses native row presets.' ); }
 }
 $fields[] = array( 'key' => $seed . '_strip_spacing_breakpoint', 'name' => 'strip_spacing_breakpoint', 'label' => 'Row Preset Mobile Breakpoint', 'type' => 'select', 'choices' => array( '' => 'Use Stack Breakpoint', '768' => '768px' ), 'default_value' => '', 'instructions' => 'Section spacing values still come from native row presets. Select 768px when their mobile values must change with this gallery.' );
 $fields[] = array( 'key' => $seed . '_strip_radius', 'name' => 'strip_radius', 'label' => 'Image Corner Radius', 'type' => 'text' );
 foreach ( $fields as &$field ) {
  $conditions = array( array( 'field' => $seed . '_showcase_presentation', 'operator' => '==', 'value' => 'scroll-strip' ) );
  if ( 'strip_frame' !== $field['name'] ) { $conditions[] = array( 'field' => $seed . '_strip_frame', 'operator' => '==', 'value' => 'responsive' ); }
  $field['conditional_logic'] = array( $conditions );
 }
 unset( $field ); return $fields;
}

/** Ignore dormant controls and reject CSS injection before emitting a contract. */
function mrn_base_stack_get_strip_frame_contract( array $row ) {
 $out = array( 'classes' => array(), 'attributes' => array() );
 if ( 'scroll-strip' !== ( $row['showcase_presentation'] ?? '' ) || 'responsive' !== ( $row['strip_frame'] ?? '' ) ) { return $out; }
 $out['classes'][] = 'has-strip-frame';
 if ( '768' === ( $row['strip_spacing_breakpoint'] ?? '' ) ) { $out['classes'][] = 'has-strip-spacing-at-768'; }
 $styles = array();
 foreach ( mrn_base_stack_get_strip_frame_fields( 'field_mrn_strip_contract' ) as $field ) {
  if ( 'text' !== $field['type'] ) { continue; }
  $value = mrn_base_stack_validate_hero_frame_dimension( $row[ $field['name'] ] ?? '' );
  if ( '' !== $value ) { $styles[] = '--mrn-' . str_replace( '_', '-', $field['name'] ) . ':' . $value; }
 }
 if ( $styles ) { $out['attributes']['style'] = implode( ';', $styles ); }
 return $out;
}

/** Flow is explicit; unset/invalid controls retain the existing pinned behavior. */
function mrn_base_stack_get_strip_behavior_attributes( array $row ) {
 if ( 'scroll-strip' !== ( $row['showcase_presentation'] ?? '' ) || 'responsive' !== ( $row['strip_frame'] ?? '' ) || 'flow' !== ( $row['strip_behavior'] ?? '' ) ) { return array(); }
 $pace = $row['strip_scroll_pace'] ?? 1.6;
 if ( ! is_numeric( $pace ) || (float) $pace < 0.1 || (float) $pace > 10 ) { $pace = 1.6; }
 return array( 'data-mrn-strip-behavior' => 'flow', 'data-mrn-strip-pace' => (string) (float) $pace );
}
