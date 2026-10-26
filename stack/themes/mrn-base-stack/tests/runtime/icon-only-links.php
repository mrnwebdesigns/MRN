<?php
/** Qualify optional icon-only labels through native ACF and the reusable CTA. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || home_url() !== getenv( 'MRN_STACK_TEST_HOME' ) || ( '127.0.0.1' !== wp_parse_url( home_url(), PHP_URL_HOST ) && ! str_ends_with( (string) wp_parse_url( home_url(), PHP_URL_HOST ), '.localhost' ) ) || ! current_user_can( 'manage_options' ) ) { throw new RuntimeException( 'An explicitly selected local administrator runtime is required.' ); }
$checks = array();
$expect = static function ( $ok, $label ) use ( &$checks ) { if ( ! $ok ) { throw new RuntimeException( esc_html( $label ) ); } $checks[] = $label; };
$fields = array_column( mrn_base_stack_get_button_link_icon_fields( 'owned_icon', '' ), null, 'name' );
$expect( isset( $fields['link_icon_position']['choices']['only'] ), 'Shared icon fields expose an explicit icon-only choice' );
$layouts = array_column( acf_get_field( 'field_mrn_page_content_rows' )['layouts'], null, 'name' );
$cta = array_column( $layouts['cta']['sub_fields'], null, 'name' );
$link_fields = array_column( $cta['links']['sub_fields'], null, 'name' );
$expect( isset( $link_fields['link_icon_position']['choices']['only'] ), 'Cloned reusable CTA field choices accept icon-only through the native write guard' );
$unrelated = array( 'key' => 'field_client_position', 'type' => 'select', 'choices' => array( 'left' => 'Left', 'right' => 'Right' ) );
$expect( $unrelated === mrn_base_stack_extend_link_icon_position_field( $unrelated ), 'A client-owned field with the same name retains its original choices' );
$icon = mrn_base_stack_get_button_link_icon_markup( array( 'link_icon_source' => 'dashicons', 'link_icon_dashicon' => 'dashicons-arrow-right-alt', 'link_icon_position' => 'only' ) );
$expect( str_contains( $icon, 'mrn-ui__link-icon--only' ) && str_contains( $icon, 'aria-hidden="true"' ), 'Icon-only glyph is decorative and retains its presentation selection' );
$label = 'Read <unsafe> & retain the label';
$expect( '<span class="screen-reader-text">' . esc_html( $label ) . '</span>' . $icon === mrn_base_stack_get_compact_link_label_markup( $label, $icon, 'only' ), 'Accessible label is escaped and hidden visually only when a valid icon is present' );
$expect( esc_html( $label ) === mrn_base_stack_get_compact_link_label_markup( $label, '', 'only' ), 'Missing icon restores the visible escaped label' );
$expect( esc_html( $label ) . $icon === mrn_base_stack_get_compact_link_label_markup( $label, $icon, 'right' ) && $icon . esc_html( $label ) === mrn_base_stack_get_compact_link_label_markup( $label, $icon, 'left' ), 'Existing left and right label composition remains exact' );
$id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'Owned native icon-only qualification' ), true );
if ( is_wp_error( $id ) ) { throw new RuntimeException( 'Owned draft creation failed.' ); }
try {
	$row = array( 'acf_fc_layout' => 'cta', 'heading' => 'Read the document', 'cta_frame' => 'stacked', 'cta_action_icon_height' => '10px', 'links' => array( array( 'link' => array( 'title' => 'Read the document', 'url' => home_url( '/document.pdf' ), 'target' => '_blank' ), 'is_button' => 1, 'link_icon_source' => 'dashicons', 'link_icon_dashicon' => 'dashicons-arrow-right-alt', 'link_icon_position' => 'only', 'link_icon_gap' => 0 ) ) );
	update_field( 'field_mrn_page_content_rows', array( $row ), $id );
	acf_flush_value_cache( $id, 'page_content_rows' ); $saved = get_field( 'page_content_rows', $id )[0];
	$expect( 'only' === $saved['links'][0]['link_icon_position'] && '10px' === $saved['cta_action_icon_height'], 'Native ACF retains the optional icon-only choice and CTA media icon height' );
	$render = static function ( $value ) use ( $id ) { ob_start(); mrn_base_stack_render_builder_rows( array( $value ), $id, 'page_content_rows' ); return ob_get_clean(); };
	$html = $render( $saved );
	$expect( str_contains( $html, '<span class="screen-reader-text">Read the document</span>' ) && str_contains( $html, 'mrn-ui__link-icon--only' ), 'Reusable CTA uses the shared label helper without a plugin override' );
	$expect( str_contains( $html, 'target="_blank"' ) && str_contains( $html, 'rel="noopener noreferrer"' ) && str_contains( $html, '--mrn-cta-frame-action-icon-height:10px' ), 'Destination target safety and selected icon height survive rendering' );
	$missing = $saved; $missing['links'][0]['link_icon_source'] = ''; $missing['links'][0]['link_icon_dashicon'] = '';
	$expect( ! str_contains( $render( $missing ), '<span class="screen-reader-text">Read the document</span>' ), 'No glyph cannot silently hide the action name in a rendered CTA' );
} finally { wp_delete_post( $id, true ); }
$expect( null === get_post( $id ), 'Owned fixture removed' );
WP_CLI::line( wp_json_encode( array( 'result' => 'passed', 'count' => count( $checks ), 'checks' => $checks ), JSON_PRETTY_PRINT ) );
