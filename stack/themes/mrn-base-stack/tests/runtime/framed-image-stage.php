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
$expect( '' === $fields['image_row_spacing_mobile_breakpoint']['default_value'] && isset( $fields['image_row_spacing_mobile_breakpoint']['choices']['767'] ), 'Image row spacing keeps the 782px default and offers an independent 767px selection' );
$expect( '' === $fields['gallery_mobile_behavior']['default_value'] && isset( $fields['gallery_mobile_behavior']['choices']['rail'], $fields['gallery_mobile_label'] ), 'Phone gallery rail is opt-in and has an editable accessible name' );
$expect( '' === $fields['gallery_mobile_start']['default_value'] && isset( $fields['gallery_mobile_start']['choices']['center'] ), 'Phone gallery centering is an opt-in native setting' );
$expect( 'id' === $fields['image_overlay_media']['return_format'], 'Overlay artwork is an optional editable attachment selection' );
$expect( '' === $fields['gallery_desktop_behavior']['default_value'] && isset( $fields['gallery_desktop_behavior']['choices']['cropped-wall'], $fields['gallery_desktop_column_width'], $fields['gallery_desktop_image_height'], $fields['gallery_desktop_top_offset'], $fields['gallery_desktop_inline_offset'] ), 'Cropped desktop wall and its dimensions are opt-in native fields' );
$image = get_posts( array( 'post_type' => 'attachment', 'post_mime_type' => 'image', 'post_status' => 'inherit', 'numberposts' => 1, 'fields' => 'ids' ) )[0] ?? 0;
$expect( $image > 0, 'Local image fixture exists' );
foreach ( array( '', 'cover', 'unsafe" onclick="bad', array( 'cover' ) ) as $sizing ) {
 $row = array( 'acf_fc_layout' => 'image_content', 'image' => $image, 'image_presentation' => 'framed-zoom', 'image_stage_sizing' => $sizing );
 ob_start(); mrn_base_stack_render_builder_row( $row, 0, 0 ); $html = ob_get_clean();
 $expect( ( 'cover' === $sizing ) === str_contains( $html, 'has-image-stage-cover' ) && ! str_contains( $html, 'onclick=' ), 'Only the exact cover selection activates cropping' );
 $row['image_presentation'] = 'scroll-zoom'; ob_start(); mrn_base_stack_render_builder_row( $row, 0, 0 ); $html = ob_get_clean();
 $expect( ! str_contains( $html, 'has-image-stage-cover' ), 'Other image modes ignore dormant crop selections' );
}
foreach ( array( '', '767', array( '767' ), '767" onclick="bad' ) as $breakpoint ) {
 ob_start(); mrn_base_stack_render_builder_row( array( 'acf_fc_layout' => 'image_content', 'image' => $image, 'image_row_spacing_mobile_breakpoint' => $breakpoint ), 0, 0 ); $html = ob_get_clean();
 $expect( ( '767' === $breakpoint ) === str_contains( $html, 'has-image-row-spacing-at-767' ) && ! str_contains( $html, 'onclick=' ), 'Only the exact optional Image row-spacing breakpoint activates' );
}
$gallery = array( 'image_presentation' => 'tilted-gallery', 'gallery_columns' => array( array( 'images' => array( $image ) ) ) );
$plain = mrn_base_stack_get_column_gallery_markup( $gallery );
$expect( str_contains( $plain, 'mrn-column-gallery__image' ) && ! str_contains( $plain, 'data-mrn-gallery-mobile' ) && ! str_contains( $plain, 'tabindex=' ), 'Existing rotated column gallery stays unchanged without the rail selection' );
$gallery['gallery_mobile_behavior'] = 'rail';
$gallery['gallery_mobile_label'] = 'Projects" onclick="bad';
$rail = mrn_base_stack_get_column_gallery_markup( $gallery );
$expect( str_contains( $rail, 'data-mrn-gallery-mobile="rail"' ) && str_contains( $rail, 'role="region" tabindex="0"' ) && str_contains( $rail, 'aria-label="Projects&quot; onclick=&quot;bad"' ) && ! str_contains( $rail, 'onclick="bad' ), 'Phone rail preserves media and escapes its keyboard region name' );
$gallery['gallery_mobile_label'] = array( 'unexpected' );
$expect( str_contains( mrn_base_stack_get_column_gallery_markup( $gallery ), 'aria-label="Image gallery"' ), 'Invalid gallery name falls back without a PHP warning' );
$gallery['gallery_mobile_start'] = 'center';
$expect( str_contains( mrn_base_stack_get_column_gallery_markup( $gallery ), 'data-mrn-gallery-start="center"' ), 'Selected phone rail centers without changing gallery media' );
$gallery['gallery_mobile_start'] = 'center" onclick="bad';
$expect( ! str_contains( mrn_base_stack_get_column_gallery_markup( $gallery ), 'data-mrn-gallery-start=' ), 'Invalid phone start setting adds no attribute' );
$gallery['gallery_mobile_start'] = 'center';
$gallery['gallery_desktop_behavior'] = 'cropped-wall';
$gallery['gallery_desktop_column_width'] = 370;
$gallery['gallery_desktop_image_height'] = 250;
$gallery['gallery_desktop_top_offset'] = 140;
$gallery['gallery_desktop_inline_offset'] = -100;
$wall = mrn_base_stack_get_column_gallery_markup( $gallery );
$expect( str_contains( $wall, 'data-mrn-gallery-desktop="cropped-wall"' ) && str_contains( $wall, '--mrn-gallery-column-width:370px' ) && str_contains( $wall, '--mrn-gallery-image-height:250px' ) && str_contains( $wall, '--mrn-gallery-top-offset:140px' ) && str_contains( $wall, '--mrn-gallery-inline-offset:-100px' ), 'Cropped wall emits bounded editable dimensions' );
$gallery['gallery_desktop_column_width'] = '370px; background:red';
$wall_invalid = mrn_base_stack_get_column_gallery_markup( $gallery );
$expect( str_contains( $wall_invalid, '--mrn-gallery-column-width:370px' ) && ! str_contains( $wall_invalid, 'background:red' ), 'Malformed wall dimensions cannot inject CSS' );
$overlay = array( 'acf_fc_layout' => 'image_content', 'image_presentation' => 'tilted-gallery', 'gallery_columns' => array( array( 'images' => array( $image ) ) ), 'image_overlay' => 1 );
ob_start(); mrn_base_stack_render_builder_row( $overlay, 0, 0 ); $plain_overlay = ob_get_clean();
$expect( str_contains( $plain_overlay, 'class="mrn-image-stage__overlay" aria-hidden="true"></span>' ), 'Existing gradient overlay remains without selected media' );
$overlay['image_overlay_media'] = $image;
ob_start(); mrn_base_stack_render_builder_row( $overlay, 0, 0 ); $media_overlay = ob_get_clean();
$expect( str_contains( $media_overlay, 'mrn-image-stage__overlay--media' ) && str_contains( $media_overlay, 'src="' . esc_url( wp_get_attachment_url( $image ) ) . '" alt=""' ), 'Selected image artwork is decorative and uses the attachment URL' );
$overlay['image_overlay_media'] = array( 'invalid' );
ob_start(); mrn_base_stack_render_builder_row( $overlay, 0, 0 ); $invalid_overlay = ob_get_clean();
$expect( str_contains( $invalid_overlay, 'class="mrn-image-stage__overlay" aria-hidden="true"></span>' ) && ! str_contains( $invalid_overlay, 'mrn-image-stage__overlay--media' ), 'Malformed artwork selection falls back to the existing gradient' );
WP_CLI::line( wp_json_encode( array( 'result' => 'passed', 'count' => count( $checks ), 'checks' => $checks, 'candidate_only' => true ), JSON_PRETTY_PRINT ) );
