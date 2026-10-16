<?php
/** Native image Cards qualification; only an owned local draft is written. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || home_url() !== getenv( 'MRN_STACK_TEST_HOME' ) || ! str_ends_with( (string) wp_parse_url( home_url(), PHP_URL_HOST ), '.localhost' ) || ! current_user_can( 'manage_options' ) ) {
	throw new RuntimeException( 'An explicitly selected local administrator runtime is required.' );
}
$checks = array();
$expect = static function ( $condition, $message ) use ( &$checks ) {
	if ( ! $condition ) { throw new RuntimeException( esc_html( $message ) ); }
	$checks[] = $message;
};
$id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'Native image Cards qualification' ), true );
$expect( ! is_wp_error( $id ) && $id > 0, 'Owned local draft created' );
try {
	$layouts = array_column( acf_get_field( 'field_mrn_page_content_rows' )['layouts'], null, 'name' );
	$fields = array_column( $layouts['card']['sub_fields'], null, 'name' );
	$nested = mrn_base_stack_get_two_column_nested_layouts();
	$nested_fields = array_column( mrn_base_stack_ensure_presentation_fields( $nested['layout_mrn_nested_card']['sub_fields'], 'card', 'field_mrn_nested_card' ), null, 'name' );
	$expect( isset( $fields['card_presentation']['choices']['image-overlay'], $nested_fields['card_presentation']['choices']['image-overlay'] ), 'Root and nested Cards expose the opt-in image profile' );
	$item_fields = array_column( $fields['card_items']['sub_fields'], null, 'name' );
	$nested_items = array_column( $nested_fields['card_items']['sub_fields'], null, 'name' );
	$expect( isset( $item_fields['image'], $nested_items['image'] ) && $item_fields['image']['key'] !== $nested_items['image']['key'], 'Root and nested Card Images have unique native ACF keys' );
	$expect( 'wysiwyg' === $fields['card_intro']['type'], 'Card introduction retains rich editable content' );
	$selection = getenv( 'MRN_CARD_TEST_IMAGE_IDS' );
	$expect( (bool) preg_match( '/^\d+(?:,\d+){2}$/D', (string) $selection ), 'Three explicit existing images selected' );
	$images = array_map( 'intval', explode( ',', $selection ) );
	foreach ( $images as $image ) {
		$expect( 'attachment' === get_post_type( $image ) && str_starts_with( (string) get_post_mime_type( $image ), 'image/' ) && is_file( (string) get_attached_file( $image ) ), 'Selected image exists locally: ' . $image );
	}
	$zero = array();
	foreach ( array( 'margin', 'padding' ) as $dimension ) {
		foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) { $zero[ 'row_spacing_' . $dimension . '_' . $side . '_preset' ] = '0px'; }
	}
	$items = array();
	foreach ( $images as $index => $image ) {
		$items[] = array( 'image' => $image, 'background_color' => 'off-white', 'link' => 0 === $index ? array( 'url' => home_url( '/contact-us/' ), 'title' => '', 'target' => '_blank' ) : '', 'card_rows' => array( array_merge( $zero, array( 'acf_fc_layout' => 'body_text', 'heading' => 'Card ' . ( $index + 1 ) . '<br>with editable copy', 'heading_tag' => 'h3', 'body_text' => '<p>Full native description for card ' . ( $index + 1 ) . '. Keyboard focus reveals the same copy as hover.</p>' ) ) ) );
	}
	$row = array_merge( $zero, array( 'acf_fc_layout' => 'card', 'heading' => 'Native image overlay Cards', 'heading_tag' => 'h2', 'card_intro' => '<p>Editable <strong>rich introduction</strong>.</p>', 'card_presentation' => 'image-overlay', 'card_layout' => 'grid', 'cards_per_row' => '3', 'card_reveal_content' => 1, 'card_image_ratio' => '460/488', 'card_image_loading' => 'eager', 'card_gap' => '30px', 'card_caption_padding' => '30px', 'card_caption_gap' => '12px', 'card_items' => $items ) );
	update_field( 'field_mrn_page_content_rows', array( $row ), $id );
	$readback = get_field( 'page_content_rows', $id )[0];
	$expect( $images === array_map( 'intval', array_column( $readback['card_items'], 'image' ) ), 'Native ACF retains every image in order' );
	$expect( str_contains( $readback['card_intro'], '<strong>rich introduction</strong>' ) && str_contains( $readback['card_items'][2]['card_rows'][0]['body_text'], 'card 3.' ), 'Rich introduction and final nested description survive ACF' );
	$expect( '460/488' === $readback['card_image_ratio'] && '30px' === $readback['card_gap'] && '30px' === $readback['card_caption_padding'], 'Native aspect ratio, gap and padding survive readback' );
	ob_start(); mrn_base_stack_render_builder_row( $readback, $id, 0 ); $html = ob_get_clean();
	$expect( 3 === substr_count( $html, 'class="mrn-card-row__image"' ) && str_contains( $html, 'mrn-content-builder__row--image-card-reveal' ), 'Native renderer outputs ordered images and overlay profile' );
	$expect( str_contains( $html, '--mrn-card-caption-gap:12px' ), 'Native heading-to-copy gap is field controlled' );
	$expect( 3 === substr_count( $html, 'loading="eager"' ), 'Native loading control avoids deferring above-fold images' );
	$expect( 2 === substr_count( $html, 'tabindex="0"' ) && str_contains( $html, 'aria-label="Card 2 with editable copy"' ), 'Unlinked reveal cards have named keyboard focus stops' );
	$expect( str_contains( $html, 'rel="noopener noreferrer"' ) && str_contains( $html, 'Card 1 with editable copy</span>' ), 'Linked cards have a meaningful name and safe new-window relation' );
	$expect( str_contains( $html, '--mrn-card-image-ratio:460/488' ) && str_contains( $html, '--mrn-card-row-gap:30px' ), 'Only authored native geometry reaches escaped styles' );
	$caption = $readback; $caption['card_reveal_content'] = 0; $caption['heading'] = 'Always-visible captions';
	ob_start(); mrn_base_stack_render_builder_row( $caption, $id, 1 ); $caption_html = ob_get_clean();
	$expect( str_contains( $caption_html, 'mrn-content-builder__row--image-card-caption' ) && ! str_contains( $caption_html, 'tabindex="0"' ), 'Always-visible caption variant avoids redundant focus stops' );
	$default = $row; unset( $default['card_presentation'], $default['card_intro'] );
	$default['content'] = '<p>Retained unused value must not become an introduction.</p>';
	ob_start(); mrn_base_stack_render_builder_row( $default, $id, 2 ); $default_html = ob_get_clean();
	$expect( ! str_contains( $default_html, 'mrn-card-row__image-media' ) && ! str_contains( $default_html, 'mrn-card-row__caption' ) && ! str_contains( $default_html, '--mrn-card-image-ratio' ), 'Unset presentation retains standard Card rendering' );
	$expect( ! str_contains( $default_html, 'Retained unused value' ), 'Old unrelated content metadata is not activated as a new introduction' );
	$expect( array() === mrn_base_stack_get_image_card_styles( array( 'card_image_ratio' => '0/2; color:red', 'card_gap' => '-1px', 'card_caption_padding' => '10px; color:red' ) ), 'Malformed or negative geometry cannot enter CSS' );
	$proof = getenv( 'MRN_CARD_PROOF_PATH' );
	if ( $proof ) {
		$expect( str_ends_with( $proof, '.html' ) && is_dir( dirname( $proof ) ), 'Explicit proof destination exists outside authored content' );
		$color = mrn_site_colors_get_value( 'off-white' );
		$expect( (bool) preg_match( '/^#[a-f0-9]{6}$/iD', $color ), 'Proof uses the existing exact caption color' );
		$document = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Native image Cards qualification</title><link rel="stylesheet" href="style.css"><style>:root{--site-color-off-white:' . $color . ';}</style></head><body><main><h1>Native image Cards preview</h1>' . $html . $caption_html . '</main></body></html>';
		$expect( false !== file_put_contents( $proof, $document ), 'Static native proof saved without modifying authored pages' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Explicit local CLI proof.
	}
} finally {
	wp_delete_post( $id, true );
}
$expect( null === get_post( $id ), 'Owned fixture removed' );
WP_CLI::line( wp_json_encode( array( 'checks' => $checks, 'count' => count( $checks ), 'result' => 'passed', 'source_version' => _S_VERSION ), JSON_PRETTY_PRINT ) );
