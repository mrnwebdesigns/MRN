<?php
/** Native Image drift: saved root/nested fields, safe markup and conditional assets. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || home_url() !== getenv( 'MRN_STACK_TEST_HOME' ) || ( '127.0.0.1' !== wp_parse_url( home_url(), PHP_URL_HOST ) && ! str_ends_with( (string) wp_parse_url( home_url(), PHP_URL_HOST ), '.localhost' ) ) || ! current_user_can( 'manage_options' ) ) { throw new RuntimeException( 'An explicitly selected local administrator runtime is required.' ); }
$checks = array(); $expect = static function ( $ok, $label ) use ( &$checks ) { if ( ! $ok ) { throw new RuntimeException( esc_html( $label ) ); } $checks[] = $label; };
$image = absint( explode( ',', getenv( 'MRN_CARD_TEST_IMAGE_IDS' ) ?: '' )[0] ); $expect( 'attachment' === get_post_type( $image ), 'Explicit local fixture image exists' );
$layouts = array_column( acf_get_field( 'field_mrn_page_content_rows' )['layouts'], null, 'name' );
$fields = array_column( $layouts['image_content']['sub_fields'], null, 'name' );
$expect( isset( $fields['image_presentation']['choices']['scroll-drift'] ), 'Native Image exposes optional Scroll Drift' );
$split_fields = array_column( $layouts['two_column_split']['sub_fields'], null, 'name' );
foreach ( array( 'left_column_rows', 'right_column_rows' ) as $side ) {
	$nested = array_column( $split_fields[ $side ]['layouts'], null, 'name' ); $nested_fields = array_column( $nested['image_content']['sub_fields'], null, 'name' );
	foreach ( array( 'image_drift_speed', 'image_drift_maximum' ) as $name ) { $expect( isset( $fields[ $name ], $nested_fields[ $name ] ) && $fields[ $name ]['key'] !== $nested_fields[ $name ]['key'], 'Independent root and Split Image fields: ' . $side . '/' . $name ); }
}
$media = array( 'acf_fc_layout' => 'image_content', 'image' => $image, 'image_presentation' => 'scroll-drift', 'image_drift_speed' => 0.08, 'image_drift_maximum' => 36 );
$split = array( 'acf_fc_layout' => 'two_column_split', 'column_rows_mode' => 'stack', 'left_column_rows' => array( $media ), 'right_column_rows' => array() );
$id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'Owned native image scroll drift proof' ), true ); $expect( ! is_wp_error( $id ) && $id > 0, 'Owned drift fixture created' );
try {
	update_field( 'field_mrn_page_content_rows', wp_slash( array( $media, $split ) ), $id ); acf_flush_value_cache( $id, 'page_content_rows' ); $saved = get_field( 'page_content_rows', $id );
	foreach ( array( $saved[0], $saved[1]['left_column_rows'][0] ) as $item ) { $expect( 'scroll-drift' === $item['image_presentation'] && 0.08 === (float) $item['image_drift_speed'] && 36 === (int) $item['image_drift_maximum'], 'Native ACF persists drift selection, speed and maximum' ); }
	$needs = mrn_base_stack_get_front_end_component_needs_for_post( $id ); $expect( $needs['presentation'] && ! $needs['motion'], 'Saved root/nested drift requests existing presentation asset without Motion library' );
	$needs = mrn_base_stack_collect_front_end_component_needs_from_content( '<figure data-mrn-image-drift="{}"></figure>', mrn_base_stack_get_default_front_end_component_needs() ); $expect( $needs['presentation'] && ! $needs['motion'], 'Authored reusable HTML requests the existing presentation component' );
	ob_start(); mrn_base_stack_render_builder_rows( $saved, $id, 'page_content_rows' ); $html = ob_get_clean(); $expect( 2 === substr_count( $html, 'data-mrn-image-drift=' ) && ! str_contains( $html, 'data-mrn-image-stage=' ), 'Root and nested images render drift without fixed-height/cropped stage markup' );
	$tags = new WP_HTML_Tag_Processor( $html ); $count = 0; while ( $tags->next_tag( 'FIGURE' ) ) { $value = $tags->get_attribute( 'data-mrn-image-drift' ); if ( $value ) { ++$count; $settings = json_decode( $value, true ); $expect( 0.08 === $settings['speed'] && 36 === (int) $settings['maximum'], 'Escaped JSON attributes retain qualified numeric settings' ); } } $expect( 2 === $count, 'Both saved markers parsed by the native HTML processor' );
	foreach ( array( array( 'image_drift_speed' => '0.16' ), array( 'image_drift_speed' => array( 0.08 ) ), array( 'image_drift_speed' => '0.08" onclick="bad' ), array( 'image_drift_maximum' => -1 ), array( 'image_drift_maximum' => 101 ), array( 'image_drift_maximum' => 36.5 ), array( 'image_drift_speed' => true ) ) as $bad ) { $expect( '' === mrn_base_stack_get_image_stage_attributes( array_merge( $media, $bad ) ), 'Unsafe/out-of-range drift values cannot emit markup' ); }
	$plain = $media; $plain['image_presentation'] = 'standard';
	foreach ( array( 'standard', 'scroll-drift' ) as $mode ) {
		$framed = $plain; $framed['image_frame'] = 'contained'; $framed['image_presentation'] = $mode; $framed['image_overlay'] = 1;
		ob_start(); mrn_base_stack_render_builder_row( $framed, $id, 2 ); $framed_html = ob_get_clean();
		$dimensions = mrn_base_stack_get_attachment_image_src( $image, 'mrn-content-media' );
		$expect( str_contains( $framed_html, sprintf( 'aspect-ratio:auto %d / %d', $dimensions[1], $dimensions[2] ) ), 'Native image-only frame reserves the attachment ratio with WordPress auto-sizes: ' . $mode );
		$expect( ! str_contains( $framed_html, 'class="mrn-image-stage__overlay"' ), 'Dormant crop overlay cannot activate on the intrinsic image frame: ' . $mode );
		$framed['heading'] = 'Authored heading beside the framed image';
		ob_start(); mrn_base_stack_render_builder_row( $framed, $id, 2 ); $headed_html = ob_get_clean();
		$expect( str_contains( $headed_html, sprintf( 'aspect-ratio:auto %d / %d', $dimensions[1], $dimensions[2] ) ), 'A framed image with text also reserves its attachment ratio: ' . $mode );
	}
	ob_start(); mrn_base_stack_render_builder_row( $plain, $id, 3 ); $plain_html = ob_get_clean();
	$expect( ! str_contains( $plain_html, 'aspect-ratio:auto ' ), 'Ordinary Image layouts retain their existing markup' );
	$expect( '' === mrn_base_stack_get_image_stage_attributes( array( 'image_drift_speed' => 0.08, 'image_drift_maximum' => 36 ) ), 'Dormant controls preserve ordinary Image markup' );
	$expect( str_contains( mrn_base_stack_get_image_stage_attributes( array( 'image_presentation' => 'scroll-zoom' ) ), 'data-mrn-image-stage="scroll-zoom"' ), 'Existing Scroll Zoom stage remains available' );
	$expect( str_contains( mrn_base_stack_get_image_stage_attributes( array( 'image_presentation' => 'scroll-drift' ) ), 'data-mrn-image-drift=' ), 'Unset optional dimensions use the bounded native defaults' );
} finally { wp_delete_post( $id, true ); }
$expect( null === get_post( $id ), 'Owned drift fixture removed' );
WP_CLI::line( wp_json_encode( array( 'result' => 'passed', 'count' => count( $checks ), 'checks' => $checks, 'candidate_only' => true ), JSON_PRETTY_PRINT ) );
