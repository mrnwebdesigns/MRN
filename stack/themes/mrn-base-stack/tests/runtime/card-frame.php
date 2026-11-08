<?php
/** Native Cards frame persistence, optional behavior and unsafe-value checks. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || home_url() !== getenv( 'MRN_STACK_TEST_HOME' ) || ! str_ends_with( (string) wp_parse_url( home_url(), PHP_URL_HOST ), '.localhost' ) || ! current_user_can( 'manage_options' ) ) { throw new RuntimeException( 'Requires the explicitly selected local administrator runtime.' ); }
$checks = array(); $expect = static function ( $ok, $message ) use ( &$checks ) { if ( ! $ok ) { throw new RuntimeException( esc_html( $message ) ); } $checks[] = $message; };
$layouts = array_column( acf_get_field( 'field_mrn_page_content_rows' )['layouts'], null, 'name' );
$fields = array_column( $layouts['card']['sub_fields'], null, 'name' );
foreach ( mrn_base_stack_get_card_frame_fields( 'fixture' ) as $field ) { $expect( isset( $fields[ $field['name'] ] ), 'Registered editable Card frame field: ' . $field['name'] ); }
$empty = array( 'classes' => array(), 'attributes' => array() );
$expect( $empty === mrn_base_stack_get_card_frame_contract( array( 'card_inner_gutter' => '30px' ) ) && $empty === mrn_base_stack_get_card_frame_contract( array( 'card_frame' => 'unknown' ) ), 'Unset and unknown frame choices retain existing output' );
$expect( $empty === mrn_base_stack_get_card_frame_contract( array( 'card_frame' => 'contained', 'card_presentation' => 'timeline' ) ), 'The Cards frame cannot alter the separate Timeline presentation' );
foreach ( array( '-1px', '1px;color:red', 'url(https://invalid.test)', array( '30px' ) ) as $bad ) {
	$contract = mrn_base_stack_get_card_frame_contract( array( 'card_frame' => 'contained', 'card_inner_gutter' => $bad, 'card_header_alignment' => $bad ) );
	$expect( ! str_contains( $contract['attributes']['style'], 'gutter' ) && ! str_contains( $contract['attributes']['style'], 'alignment' ), 'Unsafe frame values cannot emit styles: ' . wp_json_encode( $bad ) );
}
$expect( '' === mrn_base_stack_validate_card_frame_dimension( 'clamp(40px, 4vw + url(bad), 64px)' ) && '' === mrn_base_stack_validate_card_frame_dimension( 'clamp(40px, 4vw + -1rem, 64px)' ), 'Unsafe or negative fluid expressions are rejected' );
$expect( ! str_contains( mrn_base_stack_get_card_frame_contract( array( 'card_frame' => 'contained', 'background_color' => 'unknown;bad' ) )['attributes']['style'], '--mrn-card-row-bg' ), 'Unknown palette values cannot emit a frame background' );
$palette = mrn_site_colors_get_all(); $slug = $palette[0]['slug'];
$expect( str_contains( mrn_base_stack_get_card_frame_contract( array( 'card_frame' => 'contained', 'background_color' => $slug ) )['attributes']['style'], '--mrn-card-row-bg:var(' . mrn_site_colors_get_css_var( $slug ) . ')' ), 'The existing authored palette color covers outer row spacing as well as the inner surface' );
$four = mrn_base_stack_get_card_frame_contract( array( 'card_frame' => 'contained', 'cards_per_row' => '4' ) );
$expect( str_contains( $four['attributes']['style'], '--mrn-card-frame-columns:4' ) && str_contains( $four['attributes']['style'], '--mrn-card-frame-tablet-columns:2' ), 'Native authored columns survive frame resolution at desktop and tablet' );
$single = mrn_base_stack_get_card_frame_contract( array( 'card_frame' => 'contained', 'card_grid_breakpoints' => '900-single' ) );
$expect( in_array( 'has-card-grid-900-single', $single['classes'], true ), 'Optional 900px single-column view is resolved without changing existing views' );
foreach ( array( array(), '900;bad', 900, '-1px', 'url(bad)' ) as $bad ) { $contract = mrn_base_stack_get_frame_spacing_contract( array( 'card_padding_mobile_breakpoint' => $bad, 'card_inner_gutter_narrow' => $bad ), 'card' ); $expect( ! $contract['classes'] && ! $contract['styles'], 'Invalid responsive spacing cannot activate a view or emit gutter styles: ' . wp_json_encode( $bad ) ); }
$id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'Owned native Cards frame proof' ), true );
$expect( ! is_wp_error( $id ) && $id > 0, 'Owned draft created' );
try {
	$row = array( 'acf_fc_layout' => 'card', 'heading' => 'Image-optional cards', 'card_intro' => '<p>Keep the introduction.</p>', 'card_presentation' => 'image-overlay', 'card_layout' => 'grid', 'cards_per_row' => '3', 'card_frame' => 'contained', 'card_content_width' => '1200px', 'card_inner_gutter' => '30px', 'card_inner_gutter_mobile' => '15px', 'card_header_space_after' => '36px', 'card_intro_width' => '900px', 'card_cards_space_before' => 'clamp(40px, 4vw + 0.5rem, 64px)', 'card_header_alignment' => 'center', 'card_gap' => '30px', 'card_items' => array( array( 'image' => '', 'card_rows' => array( array( 'acf_fc_layout' => 'body_text', 'heading' => 'Keep this text card', 'heading_tag' => 'h3', 'body_text' => '<p>Image-free copy remains visible.</p>' ) ) ) ) );
	$row = array_merge( $row, array( 'card_label_size' => 'var(--site-text-label-2-size)', 'card_label_line_height' => 'var(--site-text-label-2-line)', 'card_label_space_after' => '24px', 'card_label_space_after_mobile' => '32px', 'card_grid_column_gap' => '120px', 'card_grid_column_gap_tablet' => '64px', 'card_grid_column_gap_medium' => '40px', 'card_grid_row_gap' => '64px', 'card_grid_row_gap_medium' => '48px', 'card_grid_row_gap_mobile' => '40px', 'card_grid_breakpoints' => '900-560', 'card_label_weight' => '600', 'card_label_transform' => 'uppercase', 'card_label_letter_spacing' => '-0.02em', 'card_label_color' => $slug ) );
	$row = array_merge( $row, array( 'card_grid_row_gap_tablet' => '56px', 'card_heading_size' => 'var(--site-text-h2-size)', 'card_heading_line_height' => 'var(--site-text-h2-line)', 'card_heading_size_tablet' => '32px', 'card_heading_line_height_tablet' => '42px', 'card_heading_size_mobile' => '28px', 'card_heading_line_height_mobile' => '36px', 'card_header_space_after_tablet' => '24px', 'card_header_space_after_mobile' => '16px', 'card_heading_weight' => '400', 'card_heading_color' => $slug ) );
	$row = array_merge( $row, array( 'card_row_spacing_mobile_breakpoint' => '767', 'card_padding_mobile_breakpoint' => '900', 'card_inner_gutter_narrow' => '28px', 'card_inner_gutter_small' => '20px' ) );
	$row = array_merge( $row, array( 'card_heading_width' => '750px', 'card_heading_width_tablet' => 'none', 'card_intro_size' => 'var(--site-text-body-2-size)', 'card_intro_line_height' => 'var(--site-text-body-2-line)', 'card_intro_paragraph_space_after' => '0px', 'card_cards_space_before_tablet' => '20px', 'card_intro_alignment' => 'start', 'card_intro_lead_style' => 'label', 'card_equal_height_items' => true, 'card_grid_breakpoints' => '1199-900' ) );
	$row['card_heading_breaks_mobile'] = true;
	update_field( 'field_mrn_page_content_rows', array( $row ), $id ); acf_flush_value_cache( $id, 'page_content_rows' ); $saved = get_field( 'page_content_rows', $id )[0];
	foreach ( mrn_base_stack_get_card_frame_fields( 'fixture' ) as $field ) {
		$name = $field['name'];
		if ( array_key_exists( $name, $row ) ) { $expect( $row[ $name ] === $saved[ $name ], 'Native ACF retains exact ' . $name ); }
	}
	ob_start(); mrn_base_stack_render_builder_row( $saved, $id, 0 ); $html = ob_get_clean();
	$expect( str_contains( $html, 'has-card-frame' ) && str_contains( $html, '--mrn-card-frame-inner-gutter:30px' ) && str_contains( $html, '--mrn-card-frame-inner-gutter-mobile:15px' ), 'Native renderer emits the validated frame contract' );
	$expect( str_contains( $html, '--mrn-card-frame-cards-space-before:clamp(40px, 4vw + 0.5rem, 64px)' ), 'The exact positive fluid card spacing is emitted instead of silently discarded' );
	$expect( str_contains( $html, 'Image-free copy remains visible.' ) && ! str_contains( $html, 'mrn-card-row__item--has-image' ) && ! str_contains( $html, '<img' ), 'Image-free captions render without fabricated media or media-only hiding classes' );
	$expect( str_contains( $html, '--mrn-card-frame-heading-width:750px' ) && str_contains( $html, '--mrn-card-frame-heading-width-tablet:none' ) && str_contains( $html, 'has-card-intro-lead-label' ) && str_contains( $html, 'has-card-equal-height-items' ) && str_contains( $html, 'has-card-heading-breaks-mobile' ), 'Saved introduction, heading width and filled item options render natively' );
	$unsafe = mrn_base_stack_get_card_frame_contract( array( 'card_frame' => 'contained', 'card_heading_width' => '1px;bad', 'card_intro_alignment' => array( 'start' ), 'card_intro_lead_style' => 'label;bad', 'card_equal_height_items' => '1;bad', 'card_intro_size' => 'var(--bad)' ) );
	$expect( ! str_contains( $unsafe['attributes']['style'], 'heading-width' ) && ! str_contains( $unsafe['attributes']['style'], 'intro-' ) && ! in_array( 'has-card-equal-height-items', $unsafe['classes'], true ), 'Malformed optional introduction and fill settings cannot emit presentation' );
	$expect( ! in_array( 'has-card-heading-breaks-mobile', mrn_base_stack_get_card_frame_contract( array( 'card_frame' => 'contained', 'card_heading_breaks_mobile' => '1;bad' ) )['classes'], true ), 'Malformed responsive break selection stays inert' );
	$saved['card_frame'] = '';
	ob_start(); mrn_base_stack_render_builder_row( $saved, $id, 0 ); $default_html = ob_get_clean();
	$expect( ! str_contains( $default_html, 'has-card-frame' ) && ! str_contains( $default_html, '--mrn-card-frame-' ), 'Unselected frames do not activate any new output contract' );
} finally { wp_delete_post( $id, true ); }
$expect( null === get_post( $id ), 'Owned draft removed' );
WP_CLI::line( wp_json_encode( array( 'result' => 'passed', 'count' => count( $checks ), 'checks' => $checks, 'candidate_only' => true ), JSON_PRETTY_PRINT ) );
