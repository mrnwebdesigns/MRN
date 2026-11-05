<?php
/**
 * Native Hero frame integration; changes only its own local draft.
 *
 * @package mrn-base-stack
 */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || home_url() !== getenv( 'MRN_STACK_TEST_HOME' ) || ! str_ends_with( (string) wp_parse_url( home_url(), PHP_URL_HOST ), '.localhost' ) || ! current_user_can( 'manage_options' ) ) {
	throw new RuntimeException( 'An explicitly selected local administrator runtime is required.' );
}
$checks = array();
$expect = static function ( $condition, $message ) use ( &$checks ) {
	if ( ! $condition ) { throw new RuntimeException( esc_html( $message ) ); }
	$checks[] = $message;
};
$id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'Native Hero frame qualification' ), true );
$expect( ! is_wp_error( $id ) && $id > 0, 'Owned local draft created' );
try {
	$hero = array_column( acf_get_field( 'field_mrn_page_hero_rows' )['layouts'], null, 'name' );
	$body = array_column( acf_get_field( 'field_mrn_page_content_rows' )['layouts'], null, 'name' );
	$fields = array_column( $hero['basic_block']['sub_fields'], null, 'name' );
	foreach ( mrn_base_stack_get_hero_frame_fields() as $field ) {
		$expect( isset( $fields[ $field['name'] ] ) && $fields[ $field['name'] ]['key'] === $field['key'], 'Native editable Hero control: ' . $field['name'] );
	}
	$expect( ! in_array( 'hero_content_width', array_column( $body['basic_block']['sub_fields'], 'name' ), true ) && ! in_array( 'hero_content_width', array_column( $hero['two_column_split']['sub_fields'], 'name' ), true ), 'Frame controls do not leak into body Basic or Split Heroes' );
	$again = mrn_base_stack_add_hero_sizing_fields_to_layout( $hero['basic_block'] );
	$names = array_filter( array_column( $again['sub_fields'], 'name' ) );
	$expect( count( $names ) === count( array_unique( $names ) ), 'Repeated Hero normalization does not duplicate controls' );
	$expect( isset( $fields['hero_inner_gutter_mobile_breakpoint']['choices']['1199'], $fields['hero_body_h2_style']['choices']['h3'] ), 'Gutter breakpoints and semantic H2 typography are native editable choices' );
	$empty = array( 'styles' => array(), 'heading_styles' => array(), 'classes' => array() );
	$expect( $empty === mrn_base_stack_get_hero_frame_contract( array() ), 'Unconfigured Heroes receive no new class or inline declaration' );
	foreach ( array( '-20px', '1px;position:fixed', 'url(https://invalid.test)', 'var(--unknown)', array( '2rem' ), 'clamp(1px, calc(2px + 3px), 4px)' ) as $bad ) {
		$expect( '' === mrn_base_stack_validate_hero_frame_dimension( $bad ), 'Unsafe or unsupported length is refused: ' . wp_json_encode( $bad ) );
	}
	$expect( 'clamp(32px, 5vw, 64px)' === mrn_base_stack_validate_hero_frame_dimension( 'clamp(32px,5vw,64px)' ) && 'none' === mrn_base_stack_validate_hero_frame_dimension( 'none', true ) && '1.2' === mrn_base_stack_validate_hero_frame_dimension( '1.2', false, true ), 'Fluid lengths, unbounded widths and unitless line heights remain native values' );
	$row = array(
		'hero_heading_entrance' => 'gentle-rise', 'acf_fc_layout' => 'basic_block', 'hero_heading_mode' => 'custom_title', 'heading' => 'An editable native Hero',
		'content' => '<p>Keep the first paragraph.</p><h2>Semantic second-level heading</h2><p>Keep the second paragraph.</p>',
		'hero_inner_gutter' => '30px', 'hero_inner_max_width' => '1170px', 'hero_row_spacing_mobile_breakpoint' => '767', 'hero_content_width' => '750px', 'hero_content_width_mobile' => '750px',
		'hero_heading_width' => 'none', 'hero_heading_width_mobile' => 'none', 'hero_text_width' => 'none',
		'hero_content_gap' => '0px', 'hero_heading_space_after' => '36px', 'hero_heading_space_after_mobile' => '16px',
		'hero_actions_space_before' => '56px', 'hero_actions_row_gap' => '15px', 'hero_actions_column_gap' => '18px',
		'hero_action_icon_height' => '10px',
		'hero_heading_size' => 'clamp(32px,5vw,64px)', 'hero_heading_line_height' => '1.2', 'hero_heading_weight' => '500',
		'hero_text_size' => '20px', 'hero_text_line_height' => '1.5', 'hero_paragraph_space_after' => '0px',
		'hero_inner_gutter_mobile' => '15px', 'hero_text_size_mobile' => '16px', 'hero_text_line_height_mobile' => '1.2',
		'hero_inner_gutter_mobile_breakpoint' => '1199', 'hero_body_h2_style' => 'h3', 'hero_body_h2_space_before' => '1em', 'hero_body_h2_space_after' => '1em',
		'links' => array( array( 'link' => array( 'title' => 'Preserved query action', 'url' => home_url( '/?first=1&second=2' ), 'target' => '_blank' ), 'is_button' => 1 ) ),
	);
	foreach ( array( 'margin', 'padding' ) as $dimension ) {
		foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) { $row[ 'row_spacing_' . $dimension . '_' . $side . '_preset' ] = '0px'; }
	}
	update_field( 'field_mrn_page_hero_rows', array( $row ), $id );
	$readback = get_field( 'page_hero_rows', $id )[0];
	foreach ( $row as $name => $value ) {
		if ( 0 === strpos( $name, 'hero_' ) ) { $expect( $value === $readback[ $name ], 'ACF save/readback preserves ' . $name ); }
	}
	$render = static function ( $value ) use ( $id ) {
		ob_start(); get_template_part( 'template-parts/builder/hero', null, array( 'row' => $value, 'post_id' => $id ) ); return ob_get_clean();
	};
	$html = $render( $readback );
	$expect( str_contains( $html, '--mrn-hero-content-width:750px' ) && str_contains( $html, '--mrn-hero-content-gap:0px' ) && str_contains( $html, 'font-size:clamp(32px, 5vw, 64px)' ), 'Native renderer uses actual saved frame and fluid typography values' );
	$expect( str_contains( $html, '--mrn-hero-inner-max-width:1170px' ) && str_contains( $html, 'has-hero-row-spacing-at-767' ), 'Saved inner canvas cap and independent row-spacing breakpoint render natively' );
	$expect( 1 === substr_count( $html, '<h1 ' ) && str_contains( $html, 'second=2' ) && str_contains( $html, 'Keep the second paragraph.' ), 'Heading semantics, complete action and rich content survive rendering' );
	$expect( str_contains( $html, 'has-hero-gutter-at-1199' ) && str_contains( $html, 'has-hero-body-h2-style-h3' ) && str_contains( $html, '--mrn-hero-body-h2-space-before:1em' ) && str_contains( $html, '<h2>Semantic second-level heading</h2>' ), 'Saved native breakpoint, H2 typography and heading spacing retain semantic heading markup' );
	$expect( str_contains( $html, 'has-hero-heading-entrance-gentle-rise' ), 'Saved native heading entrance reaches the Hero class contract' );
	foreach ( array( '', 'unknown', 'gentle-rise;display:none', array( 'gentle-rise' ) ) as $bad_entrance ) {
		$expect( $empty === mrn_base_stack_get_hero_frame_contract( array( 'hero_heading_entrance' => $bad_entrance ) ), 'Unknown or malformed entrance stays inactive' );
	}
	$expect( $empty === mrn_base_stack_get_hero_frame_contract( array( 'hero_heading_entrance' => 'gentle-rise', 'motion_settings' => array( 'enabled' => 1, 'effect' => 'text-reveal' ) ) ), 'Existing Row Effects prevent competing heading entrance transforms' );
	$needs = mrn_base_stack_get_front_end_component_needs_for_post( $id );
	$expect( empty( $needs['motion'] ), 'CSS heading entrance requests no Motion library' );
	$bad = mrn_base_stack_get_hero_frame_contract( array( 'hero_inner_max_width' => 'none', 'hero_row_spacing_mobile_breakpoint' => array( '767' ), 'hero_heading_size' => '1px;position:fixed', 'hero_text_size' => array(), 'hero_heading_weight' => '999', 'hero_heading_color' => 'unconfigured-color', 'hero_inner_gutter_mobile_breakpoint' => '1199;bad', 'hero_body_h2_style' => array( 'h3' ), 'hero_body_h2_space_before' => '-1em' ) );
	$expect( $empty === $bad, 'Malformed optional data cannot inject CSS or invent Site Colors' );
	$expect( $empty === mrn_base_stack_get_hero_frame_contract( array( 'hero_inner_gutter_mobile_breakpoint' => '767' ) ), 'Explicit existing 767px breakpoint keeps prior output' );
	$expect( $empty === mrn_base_stack_get_hero_frame_contract( array( 'hero_row_spacing_mobile_breakpoint' => '782', 'hero_inner_max_width' => '-1px' ) ), 'Existing row-spacing breakpoint and invalid width retain defaults' );
	$default = array( 'acf_fc_layout' => 'basic_block', 'heading' => 'Existing native defaults', 'hero_heading_mode' => 'custom_title', 'content' => '<p>Original formatting.</p>' );
	$expect( ! str_contains( $render( $default ), '--mrn-hero-content-width' ) && ! str_contains( $render( $default ), 'has-hero-text-' ), 'Existing basic Hero rendering stays opt-in' );
	$media = array_merge( $row, array(
		'hero_inner_gutter_narrow' => '28px', 'hero_inner_gutter_small' => '20px', 'hero_padding_mobile_breakpoint' => '900', 'hero_padding_bottom_mobile_source' => 'desktop',
		'hero_media_width' => '422px', 'hero_media_max_share' => '40', 'hero_media_stack_breakpoint' => '900', 'hero_media_column_gap' => '64px', 'hero_media_row_gap' => '40px', 'hero_media_radius' => '0px', 'hero_media_ratio' => '540 / 430', 'hero_media_shadow' => 'none', 'hero_media_size' => 'full',
		'hero_heading_size_narrow' => 'clamp(44px, 9vw, 64px)', 'hero_heading_line_height_narrow' => '1', 'hero_heading_space_after_narrow' => '24px', 'hero_actions_space_before_narrow' => '32px', 'hero_actions_gap_narrow' => '12px', 'hero_actions_small_layout' => 'full-column',
		'hero_paragraph_space_before' => '22px', 'hero_lead_size' => '15px', 'hero_lead_line_height' => '36px', 'hero_lead_space_after' => '14px', 'hero_lead_weight' => '600', 'hero_lead_transform' => 'uppercase',
	) );
	update_field( 'field_mrn_page_hero_rows', array( $media ), $id ); $saved_media = get_field( 'page_hero_rows', $id )[0];
	foreach ( $media as $name => $value ) { if ( str_starts_with( $name, 'hero_' ) ) { $expect( $value === $saved_media[ $name ], 'Native media frame persists ' . $name ); } }
	$media_html = $render( $saved_media );
	$expect( str_contains( $media_html, 'has-hero-media-stack-breakpoint-900' ) && str_contains( $media_html, '--mrn-hero-media-max-share:40%' ) && str_contains( $media_html, '--mrn-hero-media-ratio:540 / 430' ), 'Native renderer consumes bounded media, ratio and stacking fields' );
	$expect( str_contains( $media_html, 'font-size:var(--mrn-hero-current-heading-size, clamp(32px, 5vw, 64px))' ) && str_contains( $media_html, '--mrn-hero-heading-size-narrow:clamp(44px, 9vw, 64px)' ), 'Narrow heading override preserves the authored desktop inline fallback' );
	$expect( str_contains( $media_html, 'has-hero-padding-mobile-breakpoint-900' ) && str_contains( $media_html, 'has-hero-lead-transform' ) && str_contains( $media_html, 'has-hero-actions-small-layout-full-column' ), 'Editable preset breakpoint, lead paragraph and small actions are opt-in' );
	foreach ( array( '0 / 1', '1 / 0', '10001 / 2', '1 / 2;position:fixed', array( '4 / 3' ) ) as $bad_ratio ) { $expect( $empty === mrn_base_stack_get_hero_frame_contract( array( 'hero_media_ratio' => $bad_ratio ) ), 'Unsafe media ratio is refused: ' . wp_json_encode( $bad_ratio ) ); }
	$expect( $empty === mrn_base_stack_get_hero_frame_contract( array( 'hero_media_width' => '-1px', 'hero_media_max_share' => '100', 'hero_media_shadow' => 'url(bad)', 'hero_media_stack_breakpoint' => array( '900' ), 'hero_padding_mobile_breakpoint' => '900;bad', 'hero_lead_weight' => '999', 'hero_lead_transform' => 'bad', 'hero_lead_color' => 'unconfigured-color', 'hero_heading_size_narrow' => 'calc(2px + 3px)' ) ), 'Malformed media/type/breakpoint settings cannot add output' );
	$attributes = mrn_base_stack_get_hero_media_image_attributes( $media );
	$expect( 'eager' === $attributes['loading'] && 'high' === $attributes['fetchpriority'] && 'async' === $attributes['decoding'] && '(max-width: 900px) 100vw, min(422px, 40vw)' === $attributes['sizes'], 'Bounded responsive image hints retain native Hero priority' );
	$expect( mrn_base_stack_get_hero_image_attributes() === mrn_base_stack_get_hero_media_image_attributes( array( 'hero_media_width' => '422px;bad', 'hero_media_max_share' => '40' ) ) && mrn_base_stack_get_hero_image_attributes() === mrn_base_stack_get_hero_media_image_attributes( array() ), 'Default and malformed media hints retain original priority attributes' );
	$proof = getenv( 'MRN_HERO_FRAME_PROOF_PATH' );
	if ( $proof ) {
		$expect( str_ends_with( $proof, '.html' ) && is_dir( dirname( $proof ) ), 'Explicit source proof directory exists' );
		$document = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Native Hero frame qualification</title><link rel="stylesheet" href="style.css"><link rel="stylesheet" href="hero.css"></head><body><main>' . $html . '</main></body></html>';
		$expect( false !== file_put_contents( $proof, $document ), 'Source preview exported without switching an authored page' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Explicit CLI-only local proof.
	}
} finally {
	wp_delete_post( $id, true );
}
$expect( null === get_post( $id ), 'Owned local draft removed' );
WP_CLI::line( wp_json_encode( array( 'checks' => $checks, 'count' => count( $checks ), 'result' => 'passed', 'source_version' => _S_VERSION ), JSON_PRETTY_PRINT ) );
