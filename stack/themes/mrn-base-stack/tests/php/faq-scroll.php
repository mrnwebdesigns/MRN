<?php
/** Standalone native FAQ presentation contract; never writes WordPress state. */
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function sanitize_hex_color( $value ) { return preg_match( '/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/D', $value ) ? $value : null; }
function mrn_base_stack_get_background_color_field( $key ) { return array( 'key' => $key, 'name' => 'background_color', 'type' => 'select' ); }
function mrn_site_colors_get_all() { return array( array( 'slug' => 'ink', 'value' => '#112233' ) ); }
function mrn_site_colors_get_css_var( $slug ) { return '--site-color-' . $slug; }
$base = dirname( __DIR__, 2 ) . '/inc/builder/';
foreach ( array( 'hero-frame.php', 'card-frame.php', 'content-frame.php', 'faq-aside.php', 'faq-scroll.php', 'presentation.php' ) as $file ) { require_once $base . $file; }
$checks = array();
$expect = static function ( $ok, $label ) use ( &$checks ) { if ( ! $ok ) { throw new RuntimeException( esc_html( $label ) ); } $checks[] = $label; };
$empty = array( 'classes' => array(), 'attributes' => array() );
foreach ( array( array(), array( 'faq_frame' => 'editorial' ), array( 'faq_presentation' => 'invalid' ), array( 'faq_presentation' => array( 'scroll-active' ) ), array( 'faq_heading_aside_width' => '361px' ) ) as $i => $row ) { $expect( $empty === mrn_base_stack_get_faq_scroll_contract( $row ), 'Existing or unselected FAQs remain byte-empty: ' . $i ); }
$row = array( 'faq_presentation' => 'scroll-active', 'faq_heading_aside_width' => '361px', 'faq_heading_size' => 'var(--site-text-h2-size)', 'faq_heading_line_height_tablet' => '40px', 'faq_heading_weight' => '400', 'faq_heading_letter_spacing' => '-0.02em', 'faq_heading_color' => 'ink', 'faq_features_color' => '#031E35', 'faq_activation_ratio' => '0.45', 'faq_mobile_expand_all' => '1', 'faq_inner_gutter' => '0px' );
$c = mrn_base_stack_get_faq_scroll_contract( $row );
$expect( array( 'has-faq-scroll-active' ) === $c['classes'] && '0.45' === $c['attributes']['data-mrn-faq-scroll'] && 'true' === $c['attributes']['data-mrn-faq-mobile-all'], 'Mode, threshold and mobile expansion are explicit' );
foreach ( array( 'heading-aside-width:361px', 'heading-size:var(--site-text-h2-size)', 'heading-line-height-tablet:40px', 'heading-weight:400', 'heading-letter-spacing:-0.02em', 'heading-color:var(--site-color-ink)', 'features-color:#031E35', 'inner-gutter:0px' ) as $part ) { $expect( str_contains( $c['attributes']['style'], '--mrn-faq-scroll-' . $part ), 'Validated native property: ' . $part ); }
foreach ( array( -1, 0, 0.09, 0.91, 2, 'Infinity', '0.45 onclick=evil', array( 0.4 ), false ) as $i => $bad ) { $changed = $row; $changed['faq_activation_ratio'] = $bad; $expect( '0.45' === mrn_base_stack_get_faq_scroll_contract( $changed )['attributes']['data-mrn-faq-scroll'], 'Malformed/out-of-range threshold safely defaults: ' . $i ); }
foreach ( array( 0.1, 0.9 ) as $value ) { $changed = $row; $changed['faq_activation_ratio'] = $value; $expect( (string) $value === mrn_base_stack_get_faq_scroll_contract( $changed )['attributes']['data-mrn-faq-scroll'], 'Threshold endpoint is admitted: ' . $value ); }
foreach ( array( 'faq_heading_aside_width' => '-1px', 'faq_heading_size' => 'var(--unknown-size)', 'faq_heading_line_height' => 'var(--site-text-h2-size)', 'faq_content_width' => '1px;position:fixed', 'faq_features_color' => 'rgba(0,0,0,.5)', 'faq_heading_color' => 'unknown', 'faq_heading_weight' => '450', 'faq_heading_letter_spacing' => '1em;display:none' ) as $key => $value ) { $bad = array( 'faq_presentation' => 'scroll-active', $key => $value ); $expect( ! isset( mrn_base_stack_get_faq_scroll_contract( $bad )['attributes']['style'] ), 'Unsafe or unresolved style is omitted: ' . $key ); }
$tracking = mrn_base_stack_get_faq_frame_contract( array( 'faq_frame' => 'editorial', 'faq_question_letter_spacing' => '-0.02em' ) );
$expect( in_array( 'has-faq-question-tracking', $tracking['classes'], true ) && '--mrn-faq-frame-question-letter-spacing:-0.02em' === $tracking['attributes']['style'], 'Question tracking is independently editable and supports bounded negative em' );
$tracking = mrn_base_stack_get_faq_frame_contract( array( 'faq_frame' => 'editorial', 'faq_question_letter_spacing' => '0px;color:red' ) );
$expect( empty( $tracking['attributes'] ) && ! in_array( 'has-faq-question-tracking', $tracking['classes'], true ), 'Question tracking cannot inject styles or activate a selector' );
$fields = mrn_base_stack_get_faq_frame_fields( 'field_one' ); $other = mrn_base_stack_get_faq_frame_fields( 'field_two' );
$expect( count( $fields ) === count( array_unique( array_column( $fields, 'key' ) ) ) && count( $fields ) === count( array_unique( array_column( $fields, 'name' ) ) ), 'New controls do not collide with existing editorial keys or names' );
$expect( ! array_intersect( array_column( $fields, 'key' ), array_column( $other, 'key' ) ), 'Nested field factories have independent native keys' );
foreach ( mrn_base_stack_get_faq_scroll_fields( 'field_one' ) as $field ) { $expect( 'faq_presentation' === $field['name'] || array( array( array( 'field' => 'field_one_faq_presentation', 'operator' => '==', 'value' => 'scroll-active' ) ) ) === $field['conditional_logic'], 'Control is visible only in its selected presentation: ' . $field['name'] ); }
foreach ( array( false, 0, '0', '', 'false', array( 1 ) ) as $i => $value ) { $changed = $row; $changed['faq_mobile_expand_all'] = $value; $expect( 'false' === mrn_base_stack_get_faq_scroll_contract( $changed )['attributes']['data-mrn-faq-mobile-all'], 'Mobile false does not rely on string truthiness: ' . $i ); }
echo json_encode( array( 'result' => 'passed', 'count' => count( $checks ), 'checks' => $checks ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . PHP_EOL;
