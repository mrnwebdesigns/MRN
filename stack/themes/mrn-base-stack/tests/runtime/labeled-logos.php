<?php
/** Native labeled logos retain relationships without changing existing sliders. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || home_url() !== getenv( 'MRN_STACK_TEST_HOME' ) || ( '127.0.0.1' !== wp_parse_url( home_url(), PHP_URL_HOST ) && ! str_ends_with( (string) wp_parse_url( home_url(), PHP_URL_HOST ), '.localhost' ) ) || ! current_user_can( 'manage_options' ) ) { throw new RuntimeException( 'Requires the explicitly selected local administrator runtime.' ); }
$checks = array(); $expect = static function ( $ok, $message ) use ( &$checks ) { if ( ! $ok ) { throw new RuntimeException( esc_html( $message ) ); } $checks[] = $message; };
$image = absint( explode( ',', getenv( 'MRN_CARD_TEST_IMAGE_IDS' ) ?: '' )[0] ); $expect( 'attachment' === get_post_type( $image ), 'Explicit local logo attachment exists' );
$layouts = array_column( acf_get_field( 'field_mrn_page_content_rows' )['layouts'], null, 'name' ); $fields = array_column( $layouts['logos']['sub_fields'], null, 'name' );
$expect( isset( $fields['logos_presentation']['choices']['labeled'], $fields['logos_description'], $fields['logos_section_gap_mobile'] ), 'Native labeled presentation, rich description and responsive frame are registered' );
$item_fields = array_column( $fields['logo_items']['sub_fields'], null, 'name' ); $expect( isset( $item_fields['item_label'], $item_fields['image'], $item_fields['links'] ), 'Native logo label preserves existing media/link fields' );
$props = array( 'logos_content_width' => '1200px', 'logos_inner_gutter' => '24px', 'logos_section_gap' => '80px', 'logos_item_gap_tablet' => '64px', 'logos_label_gap_mobile' => '16px', 'logos_heading_size' => 'var(--site-text-h3-size)', 'logos_text_line_height' => 'var(--site-text-body-2-line)' );
$row = array_merge( $props, array( 'acf_fc_layout' => 'logos', 'logos_presentation' => 'labeled', 'display_mode' => 'slider', 'heading' => 'Editable logo comparison', 'logos_description' => '<p>Keep the <a href="/our-work/">description link</a>.</p>', 'logo_items' => array( array( 'item_label' => 'Original <script>literal</script>', 'image' => $image, 'links' => array( array( 'link' => array( 'url' => home_url( '/our-work/' ), 'title' => 'See the work', 'target' => '_blank' ) ) ) ), array( 'item_label' => 'Updated label only', 'image' => 0, 'links' => array() ) ) ) );
$id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'Owned labeled logo proof' ), true ); $expect( ! is_wp_error( $id ) && $id > 0, 'Owned labeled logo draft created' );
try {
	update_field( 'field_mrn_page_content_rows', wp_slash( array( $row ) ), $id ); acf_flush_value_cache( $id, 'page_content_rows' ); $saved = get_field( 'page_content_rows', $id )[0];
	foreach ( $props as $name => $value ) { $expect( $value === $saved[ $name ], 'Native ACF retains ' . $name ); }
	$expect( $row['logos_description'] === get_post_meta( $id, 'page_content_rows_0_logos_description', true ) && 2 === count( $saved['logo_items'] ), 'Rich description and ordered label/image items persist' );
	ob_start(); mrn_base_stack_render_builder_row( $saved, $id, 0 ); $html = ob_get_clean();
	$expect( str_contains( $html, '--mrn-logos-image-width:' ), 'Intrinsic logo geometry is reserved before lazy loading' );
	$expect( str_contains( $html, 'has-labeled-logos' ) && ! str_contains( $html, 'splide__' ), 'Selected labeled presentation renders without slider markup' );
	$expect( str_contains( $html, '&lt;script&gt;literal&lt;/script&gt;' ) && ! str_contains( $html, '<script>literal' ) && str_contains( $html, 'Updated label only' ), 'Labels are escaped and label-only items remain visible' );
	$expect( str_contains( $html, 'description link' ) && str_contains( $html, 'rel="noopener noreferrer"' ), 'Description and existing safe-target media link render' );
	$needs = mrn_base_stack_get_front_end_component_needs_for_post( $id ); $expect( empty( $needs['slider'] ), 'Labeled Logos ignores dormant slider selection in asset discovery' );
	$plain = $saved; $plain['logos_presentation'] = '';
	ob_start(); mrn_base_stack_render_builder_row( $plain, $id, 1 ); $html = ob_get_clean();
	$expect( str_contains( $html, 'splide__' ) && ! str_contains( $html, 'mrn-logos-row__item-label' ) && ! str_contains( $html, 'description link' ), 'Unselected presentation retains existing slider output without dormant labels/description' );
	foreach ( array( '-1px', '1px;color:red', 'var(--unknown)', array( '24px' ) ) as $bad ) {
		$contract = mrn_base_stack_get_labeled_logos_contract( array( 'logos_presentation' => 'labeled', 'logos_content_width' => $bad, 'logos_item_gap' => $bad, 'logos_text_color' => $bad ) );
		$expect( empty( $contract['attributes'] ), 'Unsafe labeled dimensions and palette cannot emit styles' );
	}
	$expect( array( 'classes' => array(), 'attributes' => array() ) === mrn_base_stack_get_labeled_logos_contract( $props ), 'Dormant labeled controls preserve existing rows' );
	set_error_handler( static function ( $severity, $message ) { throw new RuntimeException( esc_html( $message ) ); } );
	try {
		$empty = array( 'acf_fc_layout' => 'logos', 'logos_presentation' => 'labeled' ); ob_start(); mrn_base_stack_render_builder_row( $empty, $id, 2 ); $html = ob_get_clean(); $expect( '' === $html, 'Entirely empty labeled Logos does not create a section or warning' );
		$empty['logo_items'] = array( array( 'image' => $image ) ); ob_start(); mrn_base_stack_render_builder_row( $empty, $id, 2 ); $html = ob_get_clean();
		$expect( 1 === substr_count( $html, '<img ' ), 'A logo with unset optional label and description renders without warnings' );
	} finally { restore_error_handler(); }
} finally { wp_delete_post( $id, true ); }
$expect( null === get_post( $id ), 'Owned labeled logo draft removed' );
WP_CLI::line( wp_json_encode( array( 'result' => 'passed', 'count' => count( $checks ), 'checks' => $checks, 'candidate_only' => true ), JSON_PRETTY_PRINT ) );
