<?php
/** Framed image stage controls, existing defaults and safe native attributes. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || home_url() !== getenv( 'MRN_STACK_TEST_HOME' ) || ! str_ends_with( (string) wp_parse_url( home_url(), PHP_URL_HOST ), '.localhost' ) || ! current_user_can( 'manage_options' ) ) { throw new RuntimeException( 'An explicitly selected local administrator runtime is required.' ); }
$checks = array(); $expect = static function ( $ok, $label ) use ( &$checks ) { if ( ! $ok ) { throw new RuntimeException( esc_html( $label ) ); } $checks[] = $label; };
$layouts = array_column( acf_get_field( 'field_mrn_page_content_rows' )['layouts'], null, 'name' ); $fields = array_column( $layouts['image_content']['sub_fields'], null, 'name' );
$expect( 'standard' === $fields['image_presentation']['default_value'] && isset( $fields['image_presentation']['choices']['framed-zoom'] ), 'Existing default remains Standard; framed zoom is optional' );
$expect( '' === $fields['media_mobile_breakpoint']['default_value'] && isset( $fields['media_mobile_breakpoint']['choices']['767'] ), 'Existing 999px breakpoint stays default; 767px is editable' );
$expect( '' === $fields['image_stage_sizing']['default_value'] && isset( $fields['image_stage_sizing']['choices']['cover'] ), 'Original proportions stay default; cover is explicitly editable' );
$first = array_column( mrn_base_stack_get_presentation_fields( 'image_content', 'field_mrn_first_stage' ), 'key' ); $second = array_column( mrn_base_stack_get_presentation_fields( 'image_content', 'field_mrn_second_stage' ), 'key' );
$expect( count( $first ) === count( array_unique( $first ) ) && ! array_intersect( $first, $second ), 'Root/nested stage field keys remain isolated' );
foreach ( array( 0, 60, 240, '60', '' ) as $inset ) { $attrs = mrn_base_stack_get_image_stage_attributes( array( 'image_presentation' => 'framed-zoom', 'image_zoom_viewport_inset' => $inset, 'media_mobile_breakpoint' => '767', 'media_height' => '847px', 'media_height_mobile' => '340px' ) ); $expect( str_contains( $attrs, 'data-mrn-image-stage="framed-zoom"' ) && str_contains( $attrs, 'data-mrn-image-zoom-inset="' . (int) $inset . '"' ) && str_contains( $attrs, 'data-mrn-media-breakpoint="767"' ) && str_contains( $attrs, '--mrn-media-height:847px' ), 'Editable framed dimensions and bounded inset render safely' ); }
foreach ( array( -1, 241, 60.5, INF, NAN, true, array( 60 ), '60px', '60" onclick="alert(1)' ) as $bad ) { $expect( '' === mrn_base_stack_get_image_stage_attributes( array( 'image_presentation' => 'framed-zoom', 'image_zoom_viewport_inset' => $bad ) ), 'Invalid framed inset cannot emit an attribute' ); }
foreach ( array( 'scroll-zoom', 'tilted-gallery' ) as $mode ) { $attrs = mrn_base_stack_get_image_stage_attributes( array( 'image_presentation' => $mode, 'image_zoom_viewport_inset' => 'invalid', 'media_height' => '847px', 'media_height_mobile' => '340px' ) ); $expect( str_contains( $attrs, 'data-mrn-image-stage="' . $mode . '"' ) && ! str_contains( $attrs, 'data-mrn-media-breakpoint' ) && ! str_contains( $attrs, 'data-mrn-image-zoom-inset' ), 'Existing mode ignores dormant inset and keeps its breakpoint: ' . $mode ); }
$attrs = mrn_base_stack_get_image_stage_attributes( array( 'image_presentation' => 'framed-zoom', 'media_mobile_breakpoint' => '767" onclick="alert(1)', 'media_height' => '10px;color:red', 'media_height_mobile' => array( '340px' ) ) );
$expect( ! str_contains( $attrs, 'onclick' ) && ! str_contains( $attrs, 'color:red' ) && ! str_contains( $attrs, '--mrn-media-height' ), 'Malformed dimensions and breakpoint cannot inject CSS or attributes' );
$expect( '' === mrn_base_stack_get_image_stage_attributes( array( 'image_presentation' => 'standard' ) ), 'Standard image adds no stage attributes' );
$image = get_posts( array( 'post_type' => 'attachment', 'post_mime_type' => 'image', 'post_status' => 'inherit', 'numberposts' => 1, 'fields' => 'ids' ) )[0] ?? 0;
$expect( $image > 0, 'Local image fixture exists' );
foreach ( array( '', 'cover', 'unsafe" onclick="bad', array( 'cover' ) ) as $sizing ) {
 $row = array( 'acf_fc_layout' => 'image_content', 'image' => $image, 'image_presentation' => 'framed-zoom', 'image_stage_sizing' => $sizing );
 ob_start(); mrn_base_stack_render_builder_row( $row, 0, 0 ); $html = ob_get_clean();
 $expect( ( 'cover' === $sizing ) === str_contains( $html, 'has-image-stage-cover' ) && ! str_contains( $html, 'onclick=' ), 'Only the exact cover selection activates cropping' );
 $row['image_presentation'] = 'scroll-zoom'; ob_start(); mrn_base_stack_render_builder_row( $row, 0, 0 ); $html = ob_get_clean();
 $expect( ! str_contains( $html, 'has-image-stage-cover' ), 'Other image modes ignore dormant crop selections' );
}
WP_CLI::line( wp_json_encode( array( 'result' => 'passed', 'count' => count( $checks ), 'checks' => $checks, 'candidate_only' => true ), JSON_PRETTY_PRINT ) );
