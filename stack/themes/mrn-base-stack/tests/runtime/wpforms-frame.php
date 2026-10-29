<?php
/** Guarded owned runtime proof for optional WPForms framing. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { exit; }
if ( 'https://mrnwebdesigns-stack-migration.localhost' !== home_url() || 'local' !== wp_get_environment_type() || ! current_user_can( 'manage_options' ) || ! mrn_local_safety_is_local_clone() ) { WP_CLI::error( 'Guarded local runtime required.' ); }
$checks = array(); $id = 0; $original = $GLOBALS['shortcode_tags']['wpforms'] ?? null;
$assert = static function ( $ok, $label ) use ( &$checks ) { if ( ! $ok ) { throw new RuntimeException( esc_html( $label ) ); } $checks[] = $label; };
$authored = static function () {
	$posts = get_posts( array( 'post_type' => array( 'page','case_study','mrn_career','wpforms' ), 'post_status' => array( 'draft','publish' ), 'numberposts' => -1, 'orderby' => 'ID', 'order' => 'ASC' ) ); $out = array();
	foreach ( $posts as $post ) { $meta = get_post_meta( $post->ID ); unset( $meta['_edit_lock'], $meta['_edit_last'] ); ksort( $meta ); $out[ $post->ID ] = hash( 'sha256', wp_json_encode( array( $post->to_array(), $meta ) ) ); } return $out;
};
$before = $authored();
try {
	$root = acf_get_field( 'field_mrn_page_content_rows' ); $layout = array_column( $root['layouts'], null, 'name' )['wpforms']; $fields = array_column( $layout['sub_fields'], null, 'name' );
	foreach ( array( 'form_frame','form_canvas_width','form_content_width','form_inner_gutter','form_inner_gutter_compact','form_heading_size','form_heading_line_height','form_heading_weight','form_heading_color','form_heading_space_after','form_content_alignment','form_heading_mobile' ) as $name ) { $assert( isset( $fields[ $name ] ), 'Registered editable control: ' . $name ); }
	$base = array( 'acf_fc_layout' => 'wpforms', 'form' => 11558, 'heading' => 'Owned form heading', 'heading_tag' => 'h2' );
	$assert( array( 'classes' => array(), 'attributes' => array() ) === mrn_base_stack_get_content_frame_contract( array_merge( $base, array( 'form_canvas_width' => '1200px' ) ), 'wpforms' ), 'Blank frame preserves the existing contract' );
	$row = array_merge( $base, array( 'form_frame' => 'contained', 'form_canvas_width' => '1200px', 'form_content_width' => '750px', 'form_inner_gutter' => '30px', 'form_inner_gutter_compact' => '15px', 'form_heading_size' => 'var(--site-text-h3-size)', 'form_heading_line_height' => 'var(--site-text-h3-line)', 'form_heading_weight' => '400', 'form_heading_color' => 'burgundy', 'form_heading_space_after' => '32px', 'form_content_alignment' => 'end', 'form_heading_mobile' => 'screen-reader' ) );
	$c = mrn_base_stack_get_content_frame_contract( $row, 'wpforms' );
	$assert( in_array( 'has-form-frame', $c['classes'], true ) && in_array( 'has-form-content-align-end', $c['classes'], true ) && in_array( 'has-form-heading-mobile-sr', $c['classes'], true ), 'Frame and optional alignment activate explicitly' );
	$assert( str_contains( $c['attributes']['style'], '--mrn-form-frame-canvas-width:1200px' ) && str_contains( $c['attributes']['style'], '--mrn-form-frame-content-width:750px' ), 'Canvas and content remain separate bounded widths' );
	foreach ( array( '-1px','none','1px;color:red','url(https://example.test)' ) as $value ) { $bad = mrn_base_stack_get_content_frame_contract( array( 'form_frame' => 'contained', 'form_canvas_width' => $value ), 'wpforms' ); $assert( empty( $bad['attributes']['style'] ), 'Invalid canvas value refused: ' . $value ); }
	$bad = mrn_base_stack_get_content_frame_contract( array( 'form_frame' => 'contained', 'form_heading_size' => 'var(--private-token)', 'form_heading_weight' => '999', 'form_content_alignment' => 'inject', 'form_heading_color' => 'not-in-palette' ), 'wpforms' );
	$assert( array( 'has-form-frame' ) === $bad['classes'] && ! $bad['attributes'], 'Unregistered typography, colors and alignment cannot emit overrides' );
	$id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'Owned WPForms frame ' . wp_generate_uuid4() ), true ); if ( is_wp_error( $id ) ) { throw new RuntimeException( 'Could not create owned fixture.' ); }
	add_shortcode( 'wpforms', static function () { return '<form aria-label="Owned form"></form>'; } );
	$encode = static function ( $row ) use ( $fields ) { $out = array( 'acf_fc_layout' => 'wpforms' ); unset( $row['acf_fc_layout'] ); foreach ( $row as $name => $value ) { $out[ $fields[ $name ]['key'] ] = $value; } return array( $out ); };
	update_field( 'field_mrn_page_content_rows', $encode( $base ), $id ); $plain = mrn_base_stack_get_content_builder_markup( $id );
	update_field( 'field_mrn_page_content_rows', $encode( array_merge( $base, array( 'form_frame' => '', 'form_canvas_width' => '' ) ) ), $id ); acf_flush_value_cache( $id, 'page_content_rows' );
	$assert( $plain === mrn_base_stack_get_content_builder_markup( $id ), 'Unconfigured saved row markup remains identical' );
	update_field( 'field_mrn_page_content_rows', $encode( $row ), $id ); acf_flush_value_cache( $id, 'page_content_rows' ); $html = mrn_base_stack_get_content_builder_markup( $id );
	$assert( str_contains( $html, 'has-form-frame' ) && str_contains( $html, 'has-form-content-align-end' ) && str_contains( $html, '--mrn-form-frame-canvas-width:1200px' ), 'Saved native frame reaches the actual renderer' );
	$assert( 1 === substr_count( $html, '<form ' ) && 1 === substr_count( $html, '<h2' ) && ! str_contains( $html, '<script' ), 'One form and semantic heading, no added scripts' );
	foreach ( $row as $name => $value ) { if ( 'acf_fc_layout' === $name ) { continue; } $saved = get_field( 'page_content_rows', $id, false )[0][ $fields[ $name ]['key'] ] ?? null; $assert( (string) $saved === (string) $value, 'Saved field retained: ' . $name ); }
	WP_CLI::line( wp_json_encode( array( 'result' => 'passed', 'count' => count( $checks ), 'checks' => $checks, 'parent_directory' => get_template_directory() ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
} finally {
	if ( $id && ! is_wp_error( $id ) ) { wp_delete_post( $id, true ); }
	if ( $original ) { add_shortcode( 'wpforms', $original ); } else { remove_shortcode( 'wpforms' ); }
	if ( $before !== $authored() ) { WP_CLI::error( 'Owned cleanup or authored preservation failed.' ); }
}
