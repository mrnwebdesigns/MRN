<?php
/** Visual treatments sit in Display Styles, technical settings in Configs and behavior in Effects, with keys unchanged; read-only. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || home_url() !== getenv( 'MRN_STACK_TEST_HOME' ) || ! str_ends_with( (string) wp_parse_url( home_url(), PHP_URL_HOST ), '.localhost' ) || ! current_user_can( 'manage_options' ) ) {
	throw new RuntimeException( 'An explicitly selected local administrator runtime is required.' );
}
$checks = array();
$expect = static function ( $condition, $message ) use ( &$checks ) {
	if ( ! $condition ) { throw new RuntimeException( esc_html( $message ) ); }
	$checks[] = $message;
};
$tab_of = static function ( array $fields ) {
	$tabs = array();
	$tab  = '';
	foreach ( $fields as $field ) {
		if ( 'tab' === ( $field['type'] ?? '' ) ) { $tab = sanitize_title( (string) ( $field['label'] ?? '' ) ); continue; }
		if ( '' !== ( $field['name'] ?? '' ) ) { $tabs[ $field['name'] ] = array( $tab, $field['key'] ?? '' ); }
	}
	return $tabs;
};

$display = mrn_base_stack_get_display_styles_tab_field_names();
$configs = mrn_base_stack_get_configs_tab_field_names();
$effects = mrn_base_stack_get_effects_tab_field_names();
$expect( 41 === count( $display ) && 4 === count( $configs ), 'The Display Styles list names 41 visual choices and the Configs list names 4 technical settings' );
$expect( ! array_intersect( $display, $configs ) && ! array_intersect( $display, $effects ) && ! array_intersect( $display, mrn_base_stack_get_layout_tab_field_names() ), 'No field is listed for two tabs' );
foreach ( array( 'faq_exclusive', 'rotation_interval', 'article_load_more', 'project_filter_batch' ) as $name ) {
	$expect( in_array( $name, $effects, true ), "$name is listed for the Effects tab" );
}

$fixture = array(
	array( 'key' => 'field_ds_content_tab', 'label' => 'Content', 'name' => '', 'type' => 'tab' ),
	array( 'key' => 'field_ds_heading', 'label' => 'Heading', 'name' => 'heading', 'type' => 'text' ),
	array( 'key' => 'field_ds_overlay', 'label' => 'Overlay', 'name' => 'image_overlay', 'type' => 'select' ),
	array( 'key' => 'field_ds_loading', 'label' => 'Image Loading', 'name' => 'card_image_loading', 'type' => 'select' ),
	array( 'key' => 'field_ds_configs_tab', 'label' => 'Configs', 'name' => '', 'type' => 'tab' ),
	array( 'key' => 'field_ds_anchor', 'label' => 'Anchor', 'name' => 'anchor', 'type' => 'text' ),
	array( 'key' => 'field_ds_display_tab', 'label' => 'Display Styles', 'name' => '', 'type' => 'tab' ),
	array( 'key' => 'field_ds_style', 'label' => 'Display Style', 'name' => 'display_style', 'type' => 'select' ),
	array( 'key' => 'field_ds_spacing_tab', 'label' => 'Spacing', 'name' => '', 'type' => 'tab' ),
	array( 'key' => 'field_ds_preset', 'label' => 'Top Margin', 'name' => 'row_spacing_margin_top_preset', 'type' => 'select' ),
);
$moved = mrn_base_stack_relocate_effect_fields( $fixture );
$tabs  = $tab_of( $moved );
$expect( array( 'display-styles', 'field_ds_overlay' ) === $tabs['image_overlay'], 'A listed visual choice moves to Display Styles with its key unchanged' );
$expect( array( 'configs', 'field_ds_loading' ) === $tabs['card_image_loading'], 'A listed technical setting moves to Configs with its key unchanged' );
$expect( array( 'heading', 'anchor', 'card_image_loading', 'display_style', 'image_overlay', 'row_spacing_margin_top_preset' ) === array_values( array_filter( array_column( $moved, 'name' ) ) ), 'Moved fields land at the end of their tab' );
$expect( 'content' === $tabs['heading'][0], 'Content stays in Content' );
$untabbed = array_values( array_filter( $fixture, static fn( $field ) => 'tab' !== $field['type'] ) );
$expect( $untabbed === mrn_base_stack_relocate_effect_fields( $untabbed ), 'A list without tabs is left as it is' );
$expect( $moved === mrn_base_stack_move_fields_to_existing_tabs( $moved ), 'Fields already in their tab stay put' );
$late = mrn_base_stack_move_fields_to_existing_tabs( $fixture );
$expect( 'display-styles' === $tab_of( $late )['image_overlay'][0] && 'configs' === $tab_of( $late )['card_image_loading'][0], 'Fields added after the tab contracts still reach their tabs' );

$layouts = array();
foreach ( acf_get_field_groups() as $group ) {
	if ( 'group_mrn_content_builder' !== ( $group['key'] ?? '' ) ) { continue; }
	foreach ( acf_get_fields( $group ) as $field ) {
		foreach ( $field['layouts'] ?? array() as $layout ) {
			$layouts[ $layout['name'] ] = $layouts[ $layout['name'] ] ?? $tab_of( $layout['sub_fields'] ?? array() );
		}
	}
}
$registered = array(
	'display-styles' => array( 'image_content' => 'image_overlay', 'card' => 'card_presentation', 'body_text' => 'list_markers', 'faq' => 'faq_presentation', 'cta' => 'cta_action_frame', 'content_lists' => 'record_presentation', 'logos' => 'logos_presentation', 'showcase' => 'showcase_presentation' ),
	'configs'        => array( 'card' => 'card_image_loading', 'content_lists' => 'people_image_size', 'wpforms' => 'form_modal_id' ),
	'effects'        => array( 'faq' => 'faq_exclusive', 'cta' => 'rotation_interval', 'content_lists' => 'article_load_more', 'image_content' => 'image_action_follow_pointer', 'logos' => 'continuous_scroll' ),
	'spacing'        => array( 'logos' => 'slider_gap' ),
);
foreach ( $registered as $tab => $pairs ) {
	foreach ( $pairs as $layout => $name ) {
		$expect( $tab === ( $layouts[ $layout ][ $name ][0] ?? '' ), "$layout: $name is registered in the $tab tab" );
	}
}
foreach ( array( 'faq' => 'faq_items', 'content_lists' => 'list_count_mode', 'image_content' => 'image_caption_source' ) as $layout => $name ) {
	$expect( 'content' === ( $layouts[ $layout ][ $name ][0] ?? '' ), "$layout: $name stays in Content" );
}

WP_CLI::line( wp_json_encode( array( 'checks' => $checks, 'count' => count( $checks ), 'result' => 'passed', 'source_version' => _S_VERSION ), JSON_PRETTY_PRINT ) );
