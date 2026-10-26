<?php
/** Optional service composition: native persistence, isolation and asset scoping. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || home_url() !== getenv( 'MRN_STACK_TEST_HOME' ) || ( '127.0.0.1' !== wp_parse_url( home_url(), PHP_URL_HOST ) && ! str_ends_with( (string) wp_parse_url( home_url(), PHP_URL_HOST ), '.localhost' ) ) || ! current_user_can( 'manage_options' ) ) { throw new RuntimeException( 'An explicitly selected local administrator runtime is required.' ); }
$checks = array(); $expect = static function ( $ok, $label ) use ( &$checks ) { if ( ! $ok ) { throw new RuntimeException( esc_html( $label ) ); } $checks[] = $label; };
$image = absint( explode( ',', getenv( 'MRN_CARD_TEST_IMAGE_IDS' ) ?: '' )[0] ); $expect( 'attachment' === get_post_type( $image ), 'Explicit fixture image exists' );
$layouts = array_column( acf_get_field( 'field_mrn_page_content_rows' )['layouts'], null, 'name' );
$logo_fields = array_column( $layouts['logos']['sub_fields'], null, 'name' );
$expect( isset( $logo_fields['display_mode'], $logo_fields['display_style'], $logo_fields['section_width'] ), 'New column Logos does not remove existing root display/width controls' );
$split_fields = array_column( $layouts['two_column_split']['sub_fields'], null, 'name' );
$nested = array_column( $split_fields['left_column_rows']['layouts'], null, 'name' );
foreach ( array( 'faq' => array( 'faq_exclusive', 'faq_icon_style', 'faq_question_font', 'faq_answer_size', 'faq_question_open_color' ), 'logos' => array( 'logos_presentation', 'logos_icon_size', 'logos_icon_gap' ), 'body_text' => array( 'body_heading_space_before', 'body_heading_font' ) ) as $layout => $names ) {
	$root_fields = array_column( $layouts[ $layout ]['sub_fields'], null, 'name' ); $nested_fields = array_column( $nested[ $layout ]['sub_fields'], null, 'name' );
	foreach ( $names as $name ) { $expect( isset( $root_fields[ $name ], $nested_fields[ $name ] ) && $root_fields[ $name ]['key'] !== $nested_fields[ $name ]['key'], 'Root and nested native fields have isolated keys: ' . $name ); }
}
foreach ( array( 'field_mrn_two_column_split_left_column', 'field_mrn_two_column_split_right_column', 'field_mrn_tabbed_panel_field_mrn_two_column_split_left_column' ) as $column_key ) {
	mrn_base_stack_get_tabbed_layout_nested_layouts(); $column = acf_get_field( $column_key );
	$column_layouts = array_column( $column['layouts'], null, 'name' ); $logo_fields = array_column( $column_layouts['logos']['sub_fields'], null, 'name' ); $items = array_column( $logo_fields['logo_items']['sub_fields'], null, 'name' ); $key = $items['image']['key'];
	acf_remove_local_field( $key ); acf_get_store( 'fields' )->remove( $key ); mrn_base_stack_register_cloned_acf_field_key( $key ); $recovered = acf_get_field( $key );
	$expect( is_array( $recovered ) && 'image' === $recovered['type'] && 'image' === $recovered['name'], 'Native Logos media selector recovers through its column factory: ' . $column_key );
}
$hide_logos = static function ( $names ) { return array_values( array_diff( $names, array( 'logos' ) ) ); };
add_filter( 'mrn_base_stack_two_column_column_layout_source_names', $hide_logos );
try {
	$hidden = mrn_base_stack_populate_split_stats_column_field( array( 'key' => 'field_mrn_two_column_split_left_column', 'layouts' => array() ) );
	$expect( ! isset( $hidden['layouts']['layout_mrn_nested_logos'] ), 'Hidden Logos remains unavailable for new column rows' );
	$existing = mrn_base_stack_populate_split_stats_column_field( array( 'key' => 'field_mrn_two_column_split_left_column', 'layouts' => array( 'layout_mrn_nested_logos' => array( 'max' => -1 ) ) ) );
	$expect( -1 === $existing['layouts']['layout_mrn_nested_logos']['max'] && ! empty( $existing['layouts']['layout_mrn_nested_logos']['sub_fields'] ), 'Existing-only hidden Logos retains its fields and cannot be newly added' );
} finally { remove_filter( 'mrn_base_stack_two_column_column_layout_source_names', $hide_logos ); }
$faq = array( 'acf_fc_layout' => 'faq', 'faq_frame' => 'editorial', 'faq_exclusive' => 1, 'faq_icon_style' => 'chevron', 'faq_item_border' => 'none', 'faq_question_font' => 'body', 'faq_question_size' => 'var(--site-text-h5-size)', 'faq_answer_size' => '16px', 'faq_answer_size_mobile' => '15px', 'faq_answer_paragraph_space_after' => '0px', 'faq_question_open_color' => 'burgundy', 'faq_icon_open_color' => 'red', 'start_open' => 1, 'faq_items' => array( array( 'question' => 'One?', 'answer' => '<p>Keep <a href="/keep/">the first link</a>.</p>' ), array( 'question' => 'Two?', 'answer' => '<p>Keep the second answer.</p>' ) ) );
$icons = array( 'acf_fc_layout' => 'logos', 'logos_presentation' => 'icons', 'display_mode' => 'slider', 'logos_icon_size' => '36px', 'logos_icon_gap' => '24px', 'logos_icon_space_before' => '36px', 'logo_items' => array( array( 'image' => $image ) ) );
$body = array( 'acf_fc_layout' => 'body_text', 'heading' => 'Existing destination', 'heading_tag' => 'p', 'heading_link' => array( 'title' => 'Existing destination', 'url' => '/keep/', 'target' => '_blank' ), 'heading_link_icon' => 'arrow', 'body_frame' => 'contained', 'body_heading_space_before' => '33px', 'body_heading_font' => 'body' );
$props = array( 'split_first_column_max_tablet' => '360px', 'split_second_column_max_tablet' => '400px', 'split_heading_alignment' => 'center', 'split_heading_color' => 'burgundy', 'split_heading_space_after_mobile' => '20px' );
$split = array_merge( $props, array( 'acf_fc_layout' => 'two_column_split', 'split_frame' => 'bounded', 'column_rows_mode' => 'stack', 'heading' => 'Section heading', 'left_column_rows' => array( $body, $icons ), 'right_column_rows' => array( $faq, $faq ) ) );
$id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'Owned optional service composition proof' ), true ); $expect( ! is_wp_error( $id ) && $id > 0, 'Owned fixture created' );
try {
	update_field( 'field_mrn_page_content_rows', wp_slash( array( $split ) ), $id ); acf_flush_value_cache( $id, 'page_content_rows' ); $saved = get_field( 'page_content_rows', $id )[0];
	foreach ( $props as $key => $value ) { $expect( $value === $saved[ $key ], 'Native Split retains ' . $key ); }
	foreach ( array( 'faq_exclusive', 'faq_icon_style', 'faq_item_border', 'faq_question_font', 'faq_question_size', 'faq_answer_size', 'faq_answer_size_mobile', 'faq_answer_paragraph_space_after', 'faq_question_open_color', 'faq_icon_open_color' ) as $key ) { $expect( (string) $faq[ $key ] === (string) $saved['right_column_rows'][0][ $key ], 'Native FAQ retains ' . $key ); }
	ob_start(); mrn_base_stack_render_builder_row( $saved, $id, 0 ); $html = ob_get_clean();
	$tags = new WP_HTML_Tag_Processor( $html ); $names = array(); while ( $tags->next_tag( array( 'tag_name' => 'DETAILS' ) ) ) { $names[] = $tags->get_attribute( 'name' ); }
	$expect( 4 === count( $names ) && $names[0] === $names[1] && $names[2] === $names[3] && $names[0] !== $names[2], 'Each exclusive FAQ group has a distinct native name shared only by its own items: ' . wp_json_encode( $names ) );
	foreach ( array( 'has-faq-chevron', 'has-faq-no-dividers', 'has-faq-question-font-body', '--mrn-faq-frame-question-open-color:var(--site-color-burgundy)', '--mrn-faq-frame-icon-open-color:var(--site-color-red)', 'has-icon-logos', '--mrn-logos-icon-size:36px', 'has-body-heading-font-body', '--mrn-body-frame-heading-space-before:33px', 'mrn-heading-action--always', 'rel="noopener noreferrer"' ) as $fragment ) { $expect( str_contains( $html, $fragment ), 'Rendered native service output includes ' . $fragment ); }
	$expect( ! str_contains( $html, 'splide__' ) && empty( mrn_base_stack_get_front_end_component_needs_for_post( $id )['slider'] ), 'Icon strips ignore dormant slider selection in both markup and asset discovery' );
	$plain = $faq; unset( $plain['faq_frame'] ); ob_start(); mrn_base_stack_render_builder_row( $plain, $id, 1 ); $html = ob_get_clean(); $expect( ! str_contains( $html, ' name=' ) && ! str_contains( $html, 'has-faq-' ), 'Dormant FAQ choices preserve ordinary markup and independent opening' );
	foreach ( array( '-1px', '1px;color:red', 'var(--unknown)', array( '12px' ) ) as $bad ) {
		$contract = mrn_base_stack_get_faq_frame_contract( array( 'faq_frame' => 'editorial', 'faq_answer_size' => $bad, 'faq_question_open_color' => $bad, 'faq_question_font' => $bad, 'faq_icon_style' => $bad ) ); $expect( array( 'has-faq-frame' ) === $contract['classes'] && empty( $contract['attributes'] ), 'Invalid optional FAQ values cannot emit styles/classes' );
		$contract = mrn_base_stack_get_labeled_logos_contract( array( 'logos_presentation' => 'icons', 'logos_icon_size' => $bad ) ); $expect( empty( $contract['attributes'] ), 'Invalid icon geometry cannot emit styles' );
	}
} finally { wp_delete_post( $id, true ); }
$expect( null === get_post( $id ), 'Owned fixture removed' );
WP_CLI::line( wp_json_encode( array( 'result' => 'passed', 'count' => count( $checks ), 'checks' => $checks, 'candidate_only' => true ), JSON_PRETTY_PRINT ) );
