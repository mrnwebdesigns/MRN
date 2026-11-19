<?php
/** Synthetic content, stored through WordPress/ACF in the private fixture only. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || '127.0.0.1' !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
	throw new RuntimeException( 'Disposable loopback fixture required.' );
}
wp_set_current_user( get_user_by( 'login', 'fleet-admin' )->ID );
$colors = array();
foreach ( array( 'white' => '#FFFFFF', 'black' => '#111111', 'charcoal' => '#222222', 'medium-gray' => '#595959', 'light-gray' => '#EEEEEE', 'mid-dark-gray' => '#444444', 'burgundy' => '#660033', 'red' => '#990000' ) as $slug => $value ) {
	$colors[] = array( 'name' => ucwords( str_replace( '-', ' ', $slug ) ), 'slug' => $slug, 'value' => $value );
}
update_option( 'mrn_site_colors', $colors, false );
require_once ABSPATH . 'wp-admin/includes/image.php';
$image = imagecreatetruecolor( 1280, 720 );
imagefill( $image, 0, 0, imagecolorallocate( $image, 40, 90, 110 ) );
ob_start();
imagepng( $image );
$upload = wp_upload_bits( 'presentation-fixture.png', null, ob_get_clean() );
if ( $upload['error'] ) { throw new RuntimeException( 'Synthetic image upload failed.' ); }
$id = wp_insert_attachment( array( 'post_title' => 'Synthetic presentation image', 'post_mime_type' => 'image/png', 'post_status' => 'inherit' ), $upload['file'], 0, true );
if ( is_wp_error( $id ) ) { throw new RuntimeException( 'Synthetic image registration failed.' ); }
wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $upload['file'] ) );
update_post_meta( $id, '_wp_attachment_image_alt', 'Synthetic teal rectangle for layout qualification' );
$tabs = array();
for ( $index = 1; $index <= 4; ++$index ) {
	$tabs[] = array( 'tab_label' => 'Panel ' . $index, 'panel_rows' => array( array( 'acf_fc_layout' => 'body_text', 'heading' => 'Panel heading ' . $index, 'heading_tag' => 'h3', 'body_text' => '<p>Unique panel copy ' . $index . ' with a <a href="' . esc_url( home_url( '/about/' ) ) . '">working link</a>.</p>' ) ) );
}
$rows = array(
	array( 'acf_fc_layout' => 'body_text', 'heading' => 'Optional presentations', 'body_text' => '<p>Keep <em>emphasis</em>, <strong>strong text</strong> and <a href="' . esc_url( home_url( '/about/?one=1&two=2' ) ) . '">this link</a>.<br>Keep the break.</p><p>Second paragraph.</p>', 'body_frame' => 'contained', 'body_content_width' => '1100px', 'body_inner_gutter' => '24px', 'text_presentation' => 'scroll-words', 'word_reveal_target' => 'text', 'word_reveal_scope' => 'row', 'word_reveal_from' => 'medium-gray', 'word_reveal_to' => 'charcoal', 'text_tags' => array( array( 'text' => 'Plain tag', 'url' => '' ), array( 'text' => 'Linked tag', 'url' => home_url( '/about/' ) ) ) ),
	array( 'acf_fc_layout' => 'tabbed_layout', 'heading' => 'Responsive tabs', 'tabs' => $tabs, 'tab_frame' => 'contained', 'tab_mobile_behavior' => 'accordion', 'tab_mobile_breakpoint' => '767', 'tab_content_width' => '1100px', 'tab_inner_gutter' => '24px', 'tab_inner_gutter_mobile' => '16px', 'tab_switch_effect' => 'text-swipe' ),
	array( 'acf_fc_layout' => 'faq', 'heading' => 'Exclusive questions', 'faq_frame' => 'editorial', 'faq_exclusive' => 1, 'faq_icon_style' => 'chevron', 'faq_question_open_color' => 'burgundy', 'faq_items' => array( array( 'question' => 'First question?', 'answer' => '<p>First answer with a <a href="' . esc_url( home_url( '/about/' ) ) . '">useful link</a>.</p>' ), array( 'question' => 'Second question?', 'answer' => '<p>Second answer remains readable.</p>' ) ) ),
	array( 'acf_fc_layout' => 'logos', 'heading' => 'Adaptive logos', 'logos_presentation' => 'auto-grid', 'display_mode' => 'slider', 'autoplay' => 1, 'logos_content_width' => '1100px', 'logos_inner_gutter' => '24px', 'logos_grid_min_width' => '72px', 'logos_grid_column_gap' => '24px', 'logos_grid_row_gap' => '24px', 'logos_grid_image_max_width' => '70px', 'logo_items' => array_fill( 0, 8, array( 'image' => $id ) ) ),
	array( 'acf_fc_layout' => 'image_content', 'heading' => 'Image action', 'image' => $id, 'image_frame' => 'contained', 'image_content_width' => '700px', 'image_presentation' => 'scroll-drift', 'image_drift_speed' => '.08', 'image_drift_maximum' => '32', 'image_action' => array( 'url' => home_url( '/about/' ), 'title' => 'Read about the fixture' ), 'image_action_label' => 'Read about the fixture', 'image_action_marker' => 1, 'image_action_follow_pointer' => 1 ),
	array( 'acf_fc_layout' => 'body_text', 'heading' => 'A semantic quotation', 'body_frame' => 'contained', 'body_content_width' => '900px', 'body_inner_gutter' => '24px', 'body_text_semantics' => 'quote', 'body_quote_mark' => 'opening', 'body_quote_mark_color' => 'burgundy', 'body_text' => '<p>Preserve the authored quotation, its emphasis and its editable text.</p>' ),
	array( 'acf_fc_layout' => 'card', 'heading' => 'Image-free cards', 'card_frame' => 'contained', 'card_content_width' => '1100px', 'card_inner_gutter' => '24px', 'cards_per_row' => '2', 'card_items' => array( array( 'card_rows' => array( array( 'acf_fc_layout' => 'body_text', 'heading' => 'First card', 'heading_tag' => 'h3', 'body_text' => '<p>First card content.</p>' ) ) ), array( 'card_rows' => array( array( 'acf_fc_layout' => 'body_text', 'heading' => 'Second card', 'heading_tag' => 'h3', 'body_text' => '<p>Second card content.</p>' ) ) ) ) ),
	array( 'acf_fc_layout' => 'cta', 'heading' => 'Named icon action', 'content' => '<p>The icon retains its accessible action name.</p>', 'cta_frame' => 'stacked', 'cta_content_width' => '1100px', 'cta_inner_gutter' => '24px', 'links' => array( array( 'link' => array( 'url' => home_url( '/about/' ), 'title' => 'Continue to About' ), 'is_button' => 1, 'link_icon_source' => 'dashicons', 'link_icon_dashicon' => 'dashicons-arrow-right-alt', 'link_icon_position' => 'only' ) ) ),
);
$swipe = array();
for ( $index = 1; $index <= 6; ++$index ) {
	$swipe[] = array( 'tab_label' => 'Swipe ' . $index, 'panel_rows' => array( array( 'acf_fc_layout' => 'body_text', 'heading' => 'Swipe heading ' . $index, 'heading_tag' => 'h3', 'body_text' => '<p>Preserved Text Swipe panel ' . $index . '.</p>' ) ) );
}
$rows[] = array( 'acf_fc_layout' => 'tabbed_layout', 'heading' => 'Existing six-panel Text Swipe', 'tabs' => $swipe, 'tab_switch_effect' => 'text-swipe' );
$page = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => 'presentation-qualification', 'post_title' => 'Presentation qualification' ), true );
if ( is_wp_error( $page ) ) { throw new RuntimeException( 'Presentation page creation failed.' ); }
// Model a six-tab legacy collection, which the five-item new-entry policy must
// preserve on later ACF saves. This fixture-owned metadata is never client data.
update_post_meta( $page, 'page_content_rows_8_tabs', 6 );
if ( ! update_field( 'field_mrn_page_content_rows', wp_slash( $rows ), $page ) ) {
	throw new RuntimeException( 'Presentation seed was rejected by the native write guards.' );
}
$saved = get_field( 'page_content_rows', $page );
if ( 9 !== count( $saved ) || 6 !== count( $saved[8]['tabs'] ) ) {
	throw new RuntimeException( 'Presentation seed did not preserve all rows and six legacy tabs.' );
}
echo wp_json_encode( array( 'image' => $id, 'page' => $page, 'url' => get_permalink( $page ) ) );
