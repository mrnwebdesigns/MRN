<?php
/** Native flowing strip field, persistence and default-output qualification. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || home_url() !== getenv( 'MRN_STACK_TEST_HOME' ) || ! str_ends_with( (string) wp_parse_url( home_url(), PHP_URL_HOST ), '.localhost' ) || ! current_user_can( 'manage_options' ) ) { throw new RuntimeException( 'Explicit local administrator target required.' ); }
$checks = array(); $expect = static function ( $ok, $label ) use ( &$checks ) { if ( ! $ok ) { throw new RuntimeException( esc_html( $label ) ); } $checks[] = $label; };
$layouts = array_column( acf_get_field( 'field_mrn_page_content_rows' )['layouts'], null, 'name' ); $fields = array_column( $layouts['showcase']['sub_fields'], null, 'name' );
foreach ( mrn_base_stack_get_strip_frame_fields( 'fixture' ) as $field ) { $expect( isset( $fields[ $field['name'] ] ), 'Native editable strip field: ' . $field['name'] ); }
$empty = array( 'classes' => array(), 'attributes' => array() );
$expect( $empty === mrn_base_stack_get_strip_frame_contract( array() ) && $empty === mrn_base_stack_get_strip_frame_contract( array( 'showcase_presentation' => 'standard', 'strip_frame' => 'responsive' ) ), 'Existing presentations ignore dormant strip controls' );
$expect( array( 'classes' => array( 'has-strip-frame' ), 'attributes' => array() ) === mrn_base_stack_get_strip_frame_contract( array( 'showcase_presentation' => 'scroll-strip', 'strip_frame' => 'responsive', 'strip_width' => '492px', 'strip_spacing_breakpoint' => '767' ) ), 'Strip sizes and breakpoints are not row values; the frame adds only its class' );
$expect( ! array_intersect( array( 'strip_width', 'strip_gap', 'strip_radius', 'strip_spacing_breakpoint', 'strip_content_width' ), array_keys( $fields ) ), 'Strip sizes, spacing and breakpoints are not row fields' );
$expect( array() === mrn_base_stack_get_strip_behavior_attributes( array( 'strip_behavior' => 'flow' ) ), 'Flow behavior requires the selected native frame and presentation' );
$id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'Owned native gallery frame' ), true ); $expect( ! is_wp_error( $id ) && $id > 0, 'Owned local fixture created' );
try {
 $row = array( 'acf_fc_layout' => 'showcase', 'showcase_presentation' => 'scroll-strip', 'strip_frame' => 'responsive', 'strip_behavior' => 'flow', 'strip_scroll_pace' => 1.6, 'section_width' => 'full-width', 'showcase_items' => array( array( 'image' => 10054 ) ) );
 foreach ( array( 'margin', 'padding' ) as $kind ) { foreach ( array( 'top','right','bottom','left' ) as $side ) { $row[ 'row_spacing_' . $kind . '_' . $side . '_preset' ] = '0px'; } }
 update_field( 'field_mrn_page_content_rows', array( $row ), $id ); acf_flush_value_cache( $id, 'page_content_rows' ); $saved = get_field( 'page_content_rows', $id )[0];
 foreach ( $row as $name => $value ) { if ( str_starts_with( $name, 'strip_' ) ) { $expect( (string) $value === (string) $saved[ $name ], 'Saved strip field persists: ' . $name ); } }
 ob_start(); mrn_base_stack_render_builder_rows( array( $saved ), $id ); $html = ob_get_clean();
 $expect( str_contains( $html, 'has-strip-frame' ) && str_contains( $html, 'data-mrn-strip-behavior="flow"' ) && str_contains( $html, 'data-mrn-strip-pace="1.6"' ) && 1 === substr_count( $html, 'data-mrn-row-spacing=' ), 'Saved gallery activates flow with one native section-spacing owner' );
 $expect( str_contains( $html, 'role="region"' ) && str_contains( $html, 'aria-label="Image gallery"' ) && str_contains( $html, 'tabindex="0"' ) && ! str_contains( $html, '<script' ), 'Gallery is keyboard named without inline JavaScript' );
 $editorial = $row;
 $editorial['heading'] = 'See more work';
 $editorial['links'] = array( array( 'link' => array( 'title' => 'Our Work', 'url' => home_url( '/our-work/' ) ), 'is_button' => 1 ) );
 $editorial['strip_header_layout'] = 'inline-action'; $editorial['strip_stagger'] = 'alternating';
 update_field( 'field_mrn_page_content_rows', array( $editorial ), $id ); acf_flush_value_cache( $id, 'page_content_rows' ); $editorial_saved = get_field( 'page_content_rows', $id )[0];
 foreach ( array( 'strip_header_layout', 'strip_stagger' ) as $name ) { $expect( $editorial[ $name ] === $editorial_saved[ $name ], 'Editorial strip field persists: ' . $name ); }
 ob_start(); mrn_base_stack_render_builder_rows( array( $editorial_saved ), $id ); $editorial_html = ob_get_clean();
 $expect( str_contains( $editorial_html, 'has-strip-inline-action' ) && str_contains( $editorial_html, 'has-strip-alternating-stagger' ), 'Editorial opt-in classes render' );
 $expect( false !== strpos( $editorial_html, 'Our Work' ) && strpos( $editorial_html, 'Our Work' ) < strpos( $editorial_html, 'data-mrn-scroll-strip' ) && 1 === substr_count( $editorial_html, 'Our Work' ), 'Action renders once before the image strip in DOM order' );
 $expect( ! str_contains( $editorial_html, '--mrn-strip-' ), 'No per-row strip dimensions render' );
 $invalid_editorial = $editorial; $invalid_editorial['strip_header_layout'] = 'unreviewed';
 $invalid_contract = mrn_base_stack_get_strip_frame_contract( $invalid_editorial );
 $expect( ! in_array( 'has-strip-inline-action', $invalid_contract['classes'], true ) && ! str_contains( $invalid_contract['attributes']['style'] ?? '', 'background:red' ), 'Invalid editorial controls cannot inject markup or CSS' );
 unset( $saved['strip_frame'] ); ob_start(); mrn_base_stack_render_builder_rows( array( $saved ), $id ); $old = ob_get_clean(); $expect( ! str_contains( $old, 'has-strip-frame' ) && ! str_contains( $old, 'data-mrn-strip-behavior' ), 'Unset frame retains existing pinned strip output' );
 $standard = $row; $standard['showcase_presentation'] = 'standard'; unset( $standard['strip_frame'] ); ob_start(); mrn_base_stack_render_builder_rows( array( $standard ), $id ); $plain = ob_get_clean(); $standard['strip_frame'] = 'responsive'; ob_start(); mrn_base_stack_render_builder_rows( array( $standard ), $id ); $dormant = ob_get_clean(); $expect( $plain === $dormant, 'Dormant frame preserves complete Standard Showcase markup including its image rendition' );
 foreach ( array( '-1','0','11','bad',array(1) ) as $bad ) { $row['strip_scroll_pace'] = $bad; $expect( '1.6' === mrn_base_stack_get_strip_behavior_attributes( $row )['data-mrn-strip-pace'], 'Invalid pace falls back safely: ' . wp_json_encode( $bad ) ); }
} finally { wp_delete_post( $id, true ); }
$expect( null === get_post( $id ), 'Owned fixture removed' );
WP_CLI::line( wp_json_encode( array( 'result' => 'passed', 'count' => count( $checks ), 'checks' => $checks ), JSON_PRETTY_PRINT ) );
