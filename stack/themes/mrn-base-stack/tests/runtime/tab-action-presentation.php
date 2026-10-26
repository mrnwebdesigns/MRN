<?php
/** Native editable action controls, asset selection, storage and unsafe-value guards. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || home_url() !== getenv( 'MRN_STACK_TEST_HOME' ) || ( '127.0.0.1' !== wp_parse_url( home_url(), PHP_URL_HOST ) && ! str_ends_with( (string) wp_parse_url( home_url(), PHP_URL_HOST ), '.localhost' ) ) || ! current_user_can( 'manage_options' ) ) { throw new RuntimeException( 'Requires an explicitly selected local administrator runtime.' ); }
$checks = array(); $expect = static function ( $ok, $label ) use ( &$checks ) { if ( ! $ok ) { throw new RuntimeException( esc_html( $label ) ); } $checks[] = $label; };
$catalog = array_column( acf_get_field( 'field_mrn_page_content_rows' )['layouts'], null, 'name' );
foreach ( array( 'tabbed_layout' => array( 'tab_inactive_icon_filter', 'tab_inactive_icon_color', 'tab_active_color' ), 'body_text' => array( 'heading_link_icon', 'heading_link_icon_size', 'heading_link_icon_gap', 'heading_link_hover_color' ), 'image_content' => array( 'image_action_follow_pointer', 'image_action_follow_inset', 'image_action_offset_inline_mobile', 'image_action_offset_block_mobile', 'image_action_padding_block_mobile', 'image_action_background_color', 'image_action_background_opacity' ) ) as $layout => $names ) {
	$fields = array_column( $catalog[ $layout ]['sub_fields'], null, 'name' ); foreach ( $names as $name ) { $expect( isset( $fields[ $name ] ), 'Registered ' . $layout . ': ' . $name ); }
}
$image = absint( explode( ',', getenv( 'MRN_CARD_TEST_IMAGE_IDS' ) ?: '' )[0] );
$expect( 'attachment' === get_post_type( $image ), 'Explicit local fixture media exists' );
$id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'Owned native tab action presentation' ), true );
$expect( ! is_wp_error( $id ) && $id > 0, 'Owned draft created' );
try {
	$link = array( 'url' => home_url( '/our-work/' ), 'title' => '', 'target' => '_blank' );
	$text = array( 'acf_fc_layout' => 'body_text', 'heading' => 'Named heading destination', 'heading_link' => $link, 'heading_link_icon' => 'arrow-reveal', 'heading_link_icon_size' => '22px', 'heading_link_icon_gap' => '8px', 'heading_link_hover_color' => 'burgundy' );
	$media = array( 'acf_fc_layout' => 'image_content', 'image' => $image, 'image_action' => $link, 'image_action_label' => 'Read the complete case study', 'image_action_marker' => 1, 'image_action_follow_pointer' => 1, 'image_action_follow_inset' => 18, 'image_action_offset_inline_mobile' => '15px', 'image_action_offset_block_mobile' => '20px', 'image_action_padding_block_mobile' => '12px', 'image_action_background_color' => 'off-white', 'image_action_hover_background_color' => 'off-white', 'image_action_text_color' => 'charcoal', 'image_action_background_opacity' => 75 );
	update_field( 'field_mrn_page_content_rows', array( $text, $media ), $id ); acf_flush_value_cache( $id, 'page_content_rows' ); $saved = get_field( 'page_content_rows', $id );
	foreach ( array( $text, $media ) as $i => $row ) { foreach ( $row as $name => $value ) { if ( in_array( $name, array( 'acf_fc_layout', 'heading', 'image', 'heading_link', 'image_action' ), true ) ) { continue; } $expect( (string) $value === (string) $saved[ $i ][ $name ], 'ACF retains ' . $name ); } }
	$expect( get_post_meta( $id, 'page_content_rows_0_heading_link', true ) === $link && get_post_meta( $id, 'page_content_rows_1_image_action', true ) === $link, 'Both complete original links retain their blank titles' );
	$needs = mrn_base_stack_get_front_end_component_needs_for_post( $id );
	$expect( ! empty( $needs['image_action'] ) && ! $needs['tabs'], 'Standalone image action selects its small dependency-free asset without loading Tabs' );
	$manifest = mrn_base_stack_get_front_end_component_asset_manifest();
	$expect( array( 'mrn-base-stack-front-end-image-action' ) === $manifest['image_action']['scripts'], 'Image action asset has its own component entry' );
	ob_start(); mrn_base_stack_render_builder_rows( $saved, $id, 'page_content_rows' ); $html = ob_get_clean();
	$expect( str_contains( $html, 'data-mrn-image-action-follow="18"' ) && str_contains( $html, '--mrn-image-action-offset-inline-mobile:15px' ) && str_contains( $html, '--mrn-image-action-background-opacity:75%' ), 'Native renderer emits the bounded pointer and responsive presentation contract' );
	$expect( str_contains( $html, 'mrn-heading-action--reveal' ) && str_contains( $html, 'aria-hidden="true"' ) && str_contains( $html, 'rel="noopener noreferrer"' ), 'Heading icon is decorative; original actions retain safe targets' );
	foreach ( array( '-1', '101', '1.5', '75%;color:red', array( '75' ), true ) as $bad ) { $expect( null === mrn_base_stack_get_image_action_integer( $bad, null ), 'Unsafe percentage/inset rejected: ' . wp_json_encode( $bad ) ); }
	$bad = $media; $bad['image_action_follow_pointer'] = 'arbitrary'; $bad['image_action_offset_inline_mobile'] = '1px;color:red'; $bad['image_action_background_color'] = 'unknown;color:red'; $bad['image_action_background_opacity'] = '75%;color:red';
	$unsafe = mrn_base_stack_wrap_image_action( '<img alt="">', $bad, $image );
	$expect( ! str_contains( $unsafe, 'data-mrn-image-action-follow' ) && ! str_contains( $unsafe, 'color:red' ), 'Invalid opt-in flags, geometry, palette and opacity cannot reach markup' );
	$bad = $text; $bad['heading_link_icon_size'] = '1px;color:red'; $bad['heading_link_hover_color'] = 'unknown;color:red';
	$expect( ! str_contains( mrn_base_stack_get_body_heading_markup( $text['heading'], $link, $bad ), 'color:red' ), 'Unsafe heading decoration cannot reach styles' );
	$plain = mrn_base_stack_get_body_heading_markup( 'Plain link', $link );
	$expect( ! str_contains( $plain, 'mrn-heading-action' ) && ! str_contains( $plain, '<svg' ), 'Unselected heading treatment preserves existing links' );
	$saved[1]['image_action_follow_pointer'] = 0; update_field( 'field_mrn_page_content_rows', $saved, $id );
	$expect( ! mrn_base_stack_get_front_end_component_needs_for_post( $id )['image_action'], 'Disabled pointer behavior does not request the script' );
} finally { wp_delete_post( $id, true ); }
$expect( null === get_post( $id ), 'Owned draft removed' );
WP_CLI::line( wp_json_encode( array( 'result' => 'passed', 'count' => count( $checks ), 'checks' => $checks, 'candidate_only' => true ), JSON_PRETTY_PRINT ) );
