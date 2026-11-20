<?php
/** Actual registered process ACF fields and renderer; read-only, exact selected local runtime. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || home_url() !== getenv( 'MRN_STACK_TEST_HOME' ) || ! str_ends_with( (string) wp_parse_url( home_url(), PHP_URL_HOST ), '.localhost' ) || ! current_user_can( 'manage_options' ) || 'local' !== wp_get_environment_type() ) { throw new RuntimeException( 'An explicitly selected local administrator runtime is required.' ); }
$checks = array(); $expect = static function ( $ok, $label ) use ( &$checks ) { if ( ! $ok ) { throw new RuntimeException( esc_html( $label ) ); } $checks[] = $label; };
$root = acf_get_field( 'field_mrn_page_content_rows' ); $layouts = array_column( $root['layouts'], null, 'name' ); $fields = array_column( $layouts['card']['sub_fields'], null, 'name' ); $items = array_column( $fields['card_items']['sub_fields'], null, 'name' );
$expect( isset( $fields['card_presentation']['choices']['process'] ) && 'standard' === $fields['card_presentation']['default_value'], 'Actual registered Cards expose Process while retaining Standard as the default' );
$expect( 1 === $items['card_rows']['min'] && 1 === $items['card_rows']['max'], 'Existing required single Card Row contract is unchanged in actual registered ACF' );
$expect( 12 === $fields['card_items']['max'] && 12 === acf_get_field_type( 'repeater' )->get_rest_schema( $fields['card_items'] )['maxItems'], 'Editor and REST retain the native twelve-item Cards bound' );
foreach ( array( 'process_item_display', 'process_item_tone', 'process_body' ) as $name ) { $expect( isset( $items[ $name ] ), 'Actual registered item control: ' . $name ); }
$expect( 'wysiwyg' === $items['process_body']['type'] && isset( $items['process_item_display']['choices']['overview-only'] ), 'Rich detail and explicit Overview Only use native editable fields' );
foreach ( mrn_base_stack_get_process_card_fields( 'field_probe' ) as $field ) { $expect( isset( $fields[ $field['name'] ] ), 'Actual registered process control: ' . $field['name'] ); }
$keys = array_column( $fields, 'key' ); $item_keys = array_column( $items, 'key' );
foreach ( array( 'process_item_display', 'process_item_tone', 'process_body' ) as $name ) {
 foreach ( $items[ $name ]['conditional_logic'] as $group ) { foreach ( $group as $condition ) { $expect( in_array( $condition['field'], array_merge( $keys, $item_keys ), true ), 'Registered conditional key resolves in the actual clone: ' . $name ); } }
}
$row = array( 'acf_fc_layout' => 'card', 'card_presentation' => 'process', 'heading' => 'Example process', 'card_intro' => '<p>Exact introduction with <a href="/intro/">its link</a>.</p>', 'process_number_start' => '0', 'process_step_diameter' => '193px', 'process_step_diameter_mobile' => '90px', 'process_step_label_size_mobile' => '12px', 'process_list_columns' => '2', 'process_toggle_icon' => 'none', 'card_items' => array() );
for ( $i = 0; $i < 6; $i++ ) { $row['card_items'][] = array( 'card_label' => 'Example step ' . $i, 'process_body' => $i < 5 ? '<p>Exact detail ' . $i . ' with <a href="/detail-' . $i . '/">a link</a>.</p><ul><li>First feature</li><li>Second feature</li></ul>' : '', 'process_item_display' => 5 === $i ? 'overview-only' : '', 'process_item_tone' => in_array( $i, array( 0, 5 ), true ) ? 'muted' : '' ); }
if ( 'native-frame' === ( $args[1] ?? '' ) ) {
 $row = array_merge( $row, array( 'card_frame' => 'contained', 'card_content_width' => '1160px', 'card_inner_gutter' => '0px', 'card_inner_gutter_mobile' => '0px' ) );
 foreach ( array( 'margin', 'padding' ) as $dimension ) { foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) { $row[ 'row_spacing_' . $dimension . '_' . $side . '_preset' ] = '0px'; } }
}
$render = static function ( $row ) { ob_start(); mrn_base_stack_render_builder_rows( array( $row ), 308, 'page_content_rows' ); return ob_get_clean(); }; $html = $render( $row );
if ( 'native-frame' === ( $args[1] ?? '' ) ) {
 foreach ( array( 'margin', 'padding' ) as $dimension ) { foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) { $expect( 2 === preg_match_all( '/--mrn-row-' . $dimension . '-' . $side . '-(?:desktop|mobile):\s*0px/', $html ), 'Native zero selector reaches desktop/mobile output once: ' . $dimension . '-' . $side ); } }
}
$expect( 6 === substr_count( $html, 'class="mrn-process__step-label"' ) && 5 === substr_count( $html, '<details ' ) && ! str_contains( $html, '<h3>Example step 5</h3>' ), 'Actual WordPress renderer preserves six overview labels and five selected detail panels' );
foreach ( array_slice( $row['card_items'], 0, 5 ) as $item ) { $expect( str_contains( $html, $item['process_body'] ), 'Actual renderer retains complete detail copy and destination: ' . $item['card_label'] ); }
$malicious = $row; $malicious['heading'] = '<script>bad()</script>'; $malicious['card_items'][0]['card_label'] = '<img src=x onerror=bad()>'; $malicious['card_items'][0]['process_body'] = '<script>bad()</script><p onclick="bad()">Safe words</p><a href="javascript:bad()">Safe link words</a>'; $unsafe = $render( $malicious );
$expect( ! str_contains( $unsafe, '<script>' ) && ! str_contains( $unsafe, '<img' ) && str_contains( $unsafe, '&lt;script&gt;' ), 'Actual WordPress escaping protects plain section and step labels' );
$expect( ! str_contains( $unsafe, 'onclick=' ) && ! str_contains( $unsafe, 'href="javascript:' ) && str_contains( $unsafe, 'Safe words' ), 'Actual WordPress KSES removes executable rich-text attributes/protocols without dropping safe wording' );
$needs = mrn_base_stack_collect_front_end_component_needs_from_content( $html, mrn_base_stack_get_default_front_end_component_needs() ); $expect( ! empty( $needs['faq'] ), 'Actual rendered markup requests the existing shared FAQ controller' );
$destination = $args[0] ?? '';
if ( $destination ) {
 $parent = realpath( dirname( $destination ) ); if ( '/srv/mrn-sites/mrnwebdesigns-stack-migration/dumps/process-stack-adoption-20261106' !== $parent || 'actual-process-fragment.html' !== basename( $destination ) ) { throw new RuntimeException( 'Exact private proof destination required.' ); }
 $second = $row; $second['heading'] = 'Independent process'; $second['card_items'] = array_slice( $row['card_items'], 0, 2 ); file_put_contents( $destination, $html . $render( $second ) ); chmod( $destination, 0600 );
}
WP_CLI::line( wp_json_encode( array( 'result' => 'passed', 'count' => count( $checks ), 'checks' => $checks, 'scope' => 'Actual registered local ACF/REST and real WordPress renderer only; no saved fixture, editor acceptance or authored conversion.', 'authored_writes' => 0 ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
