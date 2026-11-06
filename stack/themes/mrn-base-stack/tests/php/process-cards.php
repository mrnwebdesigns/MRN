<?php
// phpcs:ignoreFile -- Isolated WordPress boundary stubs exercise real field factories, contracts and renderer.
/** Native process Cards: explicit overview-only content, bounded configuration and semantic output. */
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $value ) { return esc_html( $value ); }
function esc_url( $value ) { return preg_match( '/^(?:https?:|\/|#)/', $value ) ? esc_attr( $value ) : ''; }
function __( $value ) { return $value; }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $value ) ); }
function sanitize_hex_color( $value ) { return preg_match( '/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/D', $value ) ? $value : null; }
function mrn_base_stack_get_background_color_field( $key ) { return array( 'key' => $key, 'name' => 'background_color', 'type' => 'select' ); }
function mrn_site_colors_get_all() { return array( array( 'slug' => 'ink', 'value' => '#112233' ), array( 'slug' => 'mist', 'value' => '#eeeeee' ) ); }
function mrn_site_colors_get_css_var( $slug ) { return '--site-color-' . $slug; }
function mrn_base_stack_get_builder_display_contract( $row, $layout ) { return array( 'classes' => array( 'native-display-contract' ), 'attributes' => array() ); }
function mrn_base_stack_get_builder_motion_contract( $row ) { return array( 'classes' => array(), 'attributes' => array() ); }
function mrn_base_stack_get_builder_accent_contract( $enabled, $style ) { return array( 'classes' => array(), 'attributes' => array() ); }
function mrn_base_stack_merge_builder_section_classes( $classes, $contract ) { return array_merge( $classes, $contract['classes'] ?? array() ); }
function mrn_base_stack_merge_builder_attributes( $old, $new ) { foreach ( $new as $key => $value ) { $old[ $key ] = 'style' === $key && ! empty( $old[ $key ] ) ? $old[ $key ] . ';' . $value : $value; } return $old; }
function mrn_base_stack_get_section_width_layers() { return array( 'width' => 'wide', 'section_class' => 'mrn-layout-section--contained', 'container_class' => 'mrn-layout-container--wide' ); }
function mrn_base_stack_normalize_text_tag( $value, $fallback ) { return in_array( $value, array( 'h1', 'h2', 'h3', 'p' ), true ) ? $value : $fallback; }
function mrn_base_stack_get_builder_anchor_markup( $row ) { return ''; }
function mrn_base_stack_get_html_attributes( $attributes ) { $out = array(); foreach ( $attributes as $key => $value ) { $out[] = esc_attr( $key ) . '="' . esc_attr( $value ) . '"'; } return implode( ' ', $out ); }
function wp_kses_post( $value ) { return strip_tags( $value, '<p><ul><li><a><strong><em>' ); }
function mrn_rbl_get_content_links( $row, $options ) { return $row['test_links'] ?? array(); }
$base = dirname( __DIR__, 2 );
foreach ( array( 'hero-frame', 'card-frame', 'content-frame', 'faq-aside', 'faq-scroll', 'process-cards', 'pricing-cards', 'presentation' ) as $file ) { require_once $base . '/inc/builder/' . $file . '.php'; }
$checks = array(); $expect = static function ( $ok, $label ) use ( &$checks ) { if ( ! $ok ) { throw new RuntimeException( $label ); } $checks[] = $label; };
$empty = array( 'classes' => array(), 'attributes' => array() );
foreach ( array( array(), array( 'card_presentation' => 'standard' ), array( 'card_presentation' => 'pricing' ), array( 'card_presentation' => array( 'process' ) ), array( 'process_step_diameter' => '193px' ) ) as $i => $row ) { $expect( $empty === mrn_base_stack_get_process_card_contract( $row ) && ! mrn_base_stack_get_process_card_items( $row ), 'Dormant process values cannot change other presentations: ' . $i ); }
$fields = mrn_base_stack_get_process_card_fields( 'field_one' ); $other = mrn_base_stack_get_process_card_fields( 'field_two' );
$expect( count( $fields ) === count( array_unique( array_column( $fields, 'key' ) ) ) && count( $fields ) === count( array_unique( array_column( $fields, 'name' ) ) ), 'Process controls have unique native names and keys' );
$expect( ! array_intersect( array_column( $fields, 'key' ), array_column( $other, 'key' ) ), 'Nested field factories cannot share ACF keys' );
foreach ( $fields as $field ) { $expect( array( array( array( 'field' => 'field_one_card_presentation', 'operator' => '==', 'value' => 'process' ) ) ) === $field['conditional_logic'], 'Optional control appears only in Process presentation: ' . $field['name'] ); }
$presentation = array_column( mrn_base_stack_get_presentation_fields( 'card', 'field_card' ), null, 'name' );
$expect( 'standard' === $presentation['card_presentation']['default_value'] && isset( $presentation['card_presentation']['choices']['process'] ), 'Process is opt-in and Standard remains the default' );
$existing = array( array( 'key' => 'field_card_heading', 'name' => 'heading', 'type' => 'text' ), array( 'key' => 'field_items', 'name' => 'card_items', 'type' => 'repeater', 'max' => 12, 'sub_fields' => array( array( 'key' => 'field_label', 'name' => 'card_label', 'type' => 'text' ), array( 'key' => 'field_rows', 'name' => 'card_rows', 'type' => 'flexible_content', 'min' => 1, 'max' => 1 ) ) ) );
$augmented = mrn_base_stack_ensure_presentation_fields( $existing, 'card', 'field_card' ); $items = array_column( array_column( $augmented, null, 'name' )['card_items']['sub_fields'], null, 'name' );
$expect( 'field_label' === $items['card_label']['key'] && 1 === $items['card_rows']['min'] && 1 === $items['card_rows']['max'], 'Existing card label key and required single Card Row remain unchanged' );
$expect( isset( $items['process_body'], $items['process_item_display'], $items['process_item_tone'] ) && count( $items ) === count( array_column( $augmented, null, 'name' )['card_items']['sub_fields'] ), 'Structured Process fields are added once without replacing existing Card content' );
$again = mrn_base_stack_ensure_presentation_fields( $augmented, 'card', 'field_card' );
$expect( array_column( $augmented, null, 'name' )['card_items'] === array_column( $again, null, 'name' )['card_items'], 'Repeated native field augmentation is idempotent' );
$row = array( 'card_presentation' => 'process', 'heading' => 'Example process', 'card_intro' => '<p>Exact introduction with <a href="/intro/">its link</a>.</p>', 'process_number_start' => '0', 'process_step_diameter' => '193px', 'process_step_diameter_mobile' => '90px', 'process_step_label_size_mobile' => '12px', 'process_step_label_size' => 'var(--site-text-body-1-size)', 'process_detail_heading_line_height' => '1.3', 'process_step_background_color' => 'ink', 'process_muted_background_color' => 'mist', 'process_step_text_color' => 'mist', 'process_muted_text_color' => 'ink', 'process_list_columns' => '2', 'process_toggle_icon' => 'none', 'card_items' => array() );
for ( $i = 0; $i < 6; $i++ ) { $row['card_items'][] = array( 'card_label' => 'Example step ' . $i, 'process_body' => $i < 5 ? '<p>Exact detail ' . $i . ' with <a href="/detail-' . $i . '/">a link</a>.</p><ul><li>First feature</li><li>Second feature</li></ul>' : '', 'process_item_display' => 5 === $i ? 'overview-only' : '', 'process_item_tone' => in_array( $i, array( 0, 5 ), true ) ? 'muted' : '' ); }
$c = mrn_base_stack_get_process_card_contract( $row ); $normalized = mrn_base_stack_get_process_card_items( $row );
$expect( '0.45' === $c['attributes']['data-mrn-faq-scroll'] && 'true' === $c['attributes']['data-mrn-faq-mobile-all'], 'Process shares the existing bounded, focus-safe scroll controller' );
foreach ( array( '--mrn-process-step-diameter:193px', '--mrn-process-step-diameter-mobile:90px', '--mrn-process-step-label-size:var(--site-text-body-1-size)', '--mrn-process-detail-heading-line-height:1.3', '--mrn-process-step-background-color:var(--site-color-ink)' ) as $style ) { $expect( str_contains( $c['attributes']['style'], $style ), 'Validated native geometry/type/palette value: ' . $style ); }
foreach ( array( '-1px', '1px;color:red', 'url(https://example.test/)', 'var(--unknown)', array( '24px' ), false ) as $i => $value ) { $bad = mrn_base_stack_get_process_card_contract( array( 'card_presentation' => 'process', 'process_step_diameter' => $value, 'process_step_label_size' => $value, 'process_step_background_color' => $value ) ); $expect( ! isset( $bad['attributes']['style'] ), 'Malformed/injectable styles are omitted: ' . $i ); }
foreach ( array( -1, 0.91, '0.45 onclick=bad', array( 0.4 ), false ) as $i => $value ) { $bad = mrn_base_stack_get_process_card_contract( array( 'card_presentation' => 'process', 'process_activation_ratio' => $value ) ); $expect( '0.45' === $bad['attributes']['data-mrn-faq-scroll'], 'Malformed activation positions use the existing safe default: ' . $i ); }
foreach ( array( -1, 1000, '1.5', false, array( 0 ) ) as $i => $value ) { $bad = $row; $bad['process_number_start'] = $value; $expect( '01' === mrn_base_stack_get_process_card_items( $bad )[0]['number'], 'Malformed starting numbers cannot hide labels or inject markup: ' . $i ); }
$expect( array( '00', '01', '02', '03', '04', '05' ) === array_column( $normalized, 'number' ) && 6 === count( $normalized ) && 1 === count( array_filter( $normalized, static fn( $item ) => $item['overview_only'] ) ), 'Six ordered overview labels are independent of five detail panels' );
$normal_empty = $row; $normal_empty['card_items'][5]['process_item_display'] = ''; $expect( false === mrn_base_stack_get_process_card_items( $normal_empty )[5]['overview_only'], 'Empty detail does not implicitly become Overview Only' );
$render = static function ( $row ) use ( $base ) { $args = array( 'row' => $row, 'post_id' => 42, 'index' => 1 ); ob_start(); include $base . '/template-parts/builder/process-cards.php'; return '<div class="mrn-content-builder">' . ob_get_clean() . '</div>'; };
$html = $render( $row );
$expect( 6 === substr_count( $html, 'class="mrn-process__step-label"' ) && 5 === substr_count( $html, '<details ' ) && ! preg_match( '/<h3>Example step 5<\/h3>/', $html ), 'Real renderer keeps all overview labels and creates only selected details' );
$expect( 5 === substr_count( $html, 'class="mrn-process__item" open' ) && 5 === substr_count( $html, '<summary ' ) && 5 === substr_count( $html, '<h3>' ), 'No-script output exposes all five details with native semantic summaries and headings' );
foreach ( array_slice( $row['card_items'], 0, 5 ) as $item ) { $expect( str_contains( $html, $item['process_body'] ), 'Complete rich detail wording and links reach the real renderer: ' . $item['card_label'] ); }
$malicious = $row; $malicious['heading'] = '<script>bad()</script>'; $malicious['card_items'][0]['card_label'] = '<img src=x onerror=bad()>'; $escaped = $render( $malicious );
$expect( str_contains( $escaped, '&lt;script&gt;' ) && str_contains( $escaped, '&lt;img' ) && ! str_contains( $escaped, '<script>' ) && ! str_contains( $escaped, '<img' ), 'Plain section and step labels escape executable markup' );
$linked = $row; $linked['test_links'] = array( array( 'url' => 'https://example.test/path?a=1&b=2', 'text' => 'Exact & link', 'target' => '_blank' ) ); $linked_html = $render( $linked );
$expect( str_contains( $linked_html, 'href="https://example.test/path?a=1&amp;b=2" target="_blank" rel="noopener noreferrer"' ), 'Common section action preserves its destination and secures new tabs' );
if ( in_array( '--render', $argv, true ) ) { echo $html; $second = $row; $second['heading'] = 'Independent process'; $second['card_items'] = array_slice( $row['card_items'], 0, 2 ); echo $render( $second ); } else { echo json_encode( array( 'result' => 'passed', 'count' => count( $checks ), 'checks' => $checks, 'runtime_writes' => 0, 'scope' => 'Isolated source contracts and actual template with WordPress boundary stubs; registered ACF/editor/full-page runtime acceptance remains separate.' ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . PHP_EOL; }
