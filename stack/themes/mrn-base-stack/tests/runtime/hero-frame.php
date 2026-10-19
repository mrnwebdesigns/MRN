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
		'acf_fc_layout' => 'basic_block', 'hero_heading_mode' => 'custom_title', 'heading' => 'An editable native Hero',
		'content' => '<p>Keep the first paragraph.</p><h2>Semantic second-level heading</h2><p>Keep the second paragraph.</p>',
		'hero_inner_gutter' => '30px', 'hero_content_width' => '750px', 'hero_content_width_mobile' => '750px',
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
	$expect( 1 === substr_count( $html, '<h1 ' ) && str_contains( $html, 'second=2' ) && str_contains( $html, 'Keep the second paragraph.' ), 'Heading semantics, complete action and rich content survive rendering' );
	$expect( str_contains( $html, 'has-hero-gutter-at-1199' ) && str_contains( $html, 'has-hero-body-h2-style-h3' ) && str_contains( $html, '--mrn-hero-body-h2-space-before:1em' ) && str_contains( $html, '<h2>Semantic second-level heading</h2>' ), 'Saved native breakpoint, H2 typography and heading spacing retain semantic heading markup' );
	$bad = mrn_base_stack_get_hero_frame_contract( array( 'hero_heading_size' => '1px;position:fixed', 'hero_text_size' => array(), 'hero_heading_weight' => '999', 'hero_heading_color' => 'unconfigured-color', 'hero_inner_gutter_mobile_breakpoint' => '1199;bad', 'hero_body_h2_style' => array( 'h3' ), 'hero_body_h2_space_before' => '-1em' ) );
	$expect( $empty === $bad, 'Malformed optional data cannot inject CSS or invent Site Colors' );
	$expect( $empty === mrn_base_stack_get_hero_frame_contract( array( 'hero_inner_gutter_mobile_breakpoint' => '767' ) ), 'Explicit existing 767px breakpoint keeps prior output' );
	$default = array( 'acf_fc_layout' => 'basic_block', 'heading' => 'Existing native defaults', 'hero_heading_mode' => 'custom_title', 'content' => '<p>Original formatting.</p>' );
	$expect( ! str_contains( $render( $default ), '--mrn-hero-content-width' ) && ! str_contains( $render( $default ), 'has-hero-text-' ), 'Existing basic Hero rendering stays opt-in' );
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
