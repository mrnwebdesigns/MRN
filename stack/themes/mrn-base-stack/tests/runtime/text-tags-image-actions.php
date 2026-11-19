<?php
/** Native structured tags, linked headings and named image action qualification. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || home_url() !== getenv( 'MRN_STACK_TEST_HOME' ) || ( '127.0.0.1' !== wp_parse_url( home_url(), PHP_URL_HOST ) && ! str_ends_with( (string) wp_parse_url( home_url(), PHP_URL_HOST ), '.localhost' ) ) || ! current_user_can( 'manage_options' ) ) { throw new RuntimeException( 'Requires the explicitly selected local administrator runtime.' ); }
$checks = array(); $expect = static function ( $ok, $message ) use ( &$checks ) { if ( ! $ok ) { throw new RuntimeException( esc_html( $message ) ); } $checks[] = $message; };
$image = absint( explode( ',', getenv( 'MRN_CARD_TEST_IMAGE_IDS' ) ?: '' )[0] );
$expect( 'attachment' === get_post_type( $image ), 'Explicit local test attachment exists' );
$id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'Owned structured text tags and image actions' ), true );
$expect( ! is_wp_error( $id ) && $id > 0, 'Owned local draft created' );
try {
	$root = array_column( acf_get_field( 'field_mrn_page_content_rows' )['layouts'], null, 'name' );
	$fields = array_column( $root['body_text']['sub_fields'], null, 'name' );
	foreach ( array( 'text_tags', 'heading_link', 'list_presentation', 'tag_gap', 'tag_space_before' ) as $name ) { $expect( isset( $fields[ $name ] ), 'Registered editable Body Text field: ' . $name ); }
	$expect( 5 === $fields['text_tags']['max'] && isset( $fields['list_presentation']['choices']['pills'] ), 'Native tags expose bounded pill presentation' );
	$tag_fields = array_column( $fields['text_tags']['sub_fields'], null, 'name' );
	$expect( 'text' === $tag_fields['url']['type'] && acf_validate_value( '#', $tag_fields['url'], 'local_tag_url' ) && acf_validate_value( '/our-work/?first=1&second=2', $tag_fields['url'], 'local_tag_url' ), 'Native editor validation accepts retained placeholders and site-relative tag URLs' );
	$split = array_column( $root['two_column_split']['sub_fields'], null, 'name' );
	$nested = array_column( $split['left_column_rows']['layouts'], null, 'name' );
	$nested_fields = array_column( $nested['body_text']['sub_fields'], null, 'name' );
	$expect( $fields['text_tags']['key'] !== $nested_fields['text_tags']['key'], 'Nested structured tag fields have isolated ACF keys' );
	$tokens = array(); $tag_limit_error = mrn_base_stack_check_repeater_tree( array_fill( 0, 6, array( 'text' => 'Keep every tag' ) ), $id, $fields['text_tags'], 'not_yet_saved_tags', $tokens );
	$expect( '' !== $tag_limit_error, 'The native guard refuses six tags instead of silently discarding data' );
	$action = array( 'url' => home_url( '/our-work/?first=1&second=2' ), 'title' => '', 'target' => '_blank' );
	$row = array( 'acf_fc_layout' => 'body_text', 'label' => 'Panel subtitle', 'label_tag' => 'p', 'heading' => 'Exact panel heading', 'heading_tag' => 'h3', 'heading_link' => $action, 'body_text' => '<p>Keep the rich copy.</p>', 'text_tags' => array( array( 'text' => 'Repeated tag', 'url' => '#' ), array( 'text' => 'Repeated tag', 'url' => $action['url'] ), array( 'text' => '<script>literal text</script>', 'url' => '' ) ), 'list_presentation' => 'pills', 'tag_gap' => '10px', 'tag_space_before' => '36px', 'tag_padding_inline' => '12px', 'tag_padding_block' => '8px', 'tag_radius' => '30px' );
	$media = array( 'acf_fc_layout' => 'image_content', 'image' => $image, 'image_action' => $action, 'image_action_label' => 'View the complete case study', 'image_action_marker' => 1, 'image_action_offset_inline' => '35px' );
	$stats = array( 'acf_fc_layout' => 'stats', 'columns' => '1', 'show_dividers' => 0, 'stat_items' => array( array( 'value' => '120%', 'item_label' => 'Traffic growth', 'animate_value' => 0 ) ) );
	update_field( 'field_mrn_page_content_rows', array( $row, $media, $stats ), $id ); acf_flush_value_cache( $id, 'page_content_rows' );
	$saved = get_field( 'page_content_rows', $id );
	$expect( $row['text_tags'] === $saved[0]['text_tags'], 'Tag text, repeated order and complete destinations survive ACF' );
	$expect( $action === get_post_meta( $id, 'page_content_rows_0_heading_link', true ) && $action === get_post_meta( $id, 'page_content_rows_1_image_action', true ), 'Both native action fields preserve the complete link including its blank original title' );
	$expect( 'View the complete case study' === $saved[1]['image_action_label'] && '1' === $saved[2]['columns'], 'Editable image naming and single-column Stats survive ACF' );
	ob_start(); mrn_base_stack_render_builder_rows( $saved, $id, 'page_content_rows' ); $html = ob_get_clean();
	$expect( str_contains( $html, 'mrn-text-tags--pills' ) && 2 === substr_count( $html, 'Repeated tag' ) && str_contains( $html, '--mrn-tag-space-before:36px' ), 'Native renderer preserves tag order and editable geometry' );
	$expect( str_contains( $html, '&lt;script&gt;literal text&lt;/script&gt;' ) && ! str_contains( $html, '<script>literal text' ), 'Tag text is escaped, never treated as authored markup' );
	$expect( str_contains( $html, '<span>Repeated tag</span>' ) && ! str_contains( $html, 'href="#"' ), 'Placeholder tags remain text without invented click targets' );
	$expect( str_contains( $html, 'aria-label="View the complete case study"' ) && str_contains( $html, 'rel="noopener noreferrer"' ) && str_contains( html_entity_decode( $html ), 'first=1&second=2' ), 'Image action retains its full URL, meaningful name and safe target' );
	$expect( str_contains( $html, 'mrn-content-builder__row--stats-columns-1' ) && str_contains( $html, '120%' ) && str_contains( $html, 'Traffic growth' ), 'A single native metric renders both value and label in one column' );
	$tags_only = $row; unset( $tags_only['label'], $tags_only['heading'], $tags_only['body_text'] );
	ob_start(); mrn_base_stack_render_builder_row( $tags_only, $id, 4 ); $tags_html = ob_get_clean();
	$expect( str_contains( $tags_html, 'Repeated tag' ), 'A tag-only native Body Text row remains renderable' );
	$before = '<img alt="">';
	$expect( $before === mrn_base_stack_wrap_image_action( $before, array(), 0 ) && $before === mrn_base_stack_wrap_image_action( $before, array( 'image_action' => $action ), 0 ), 'Unset and unnamed image actions retain original markup without an empty link' );
	$expect( $before === mrn_base_stack_wrap_image_action( $before, array( 'image_action' => array( 'url' => 'javascript:alert(1)', 'title' => 'Unsafe action' ) ), $image ), 'Unsafe image destinations cannot render an action' );
	$unsafe = $row; $unsafe['tag_gap'] = '1px;color:red'; $unsafe['text_tags'] = array( array( 'text' => 'Safe tag', 'url' => 'javascript:alert(1)' ) );
	$unsafe_html = mrn_base_stack_get_text_tags_markup( $unsafe );
	$expect( ! str_contains( $unsafe_html, 'javascript' ) && ! str_contains( $unsafe_html, 'color:red' ) && ! str_contains( $unsafe_html, '<a ' ), 'Unsafe tag destinations and style injections cannot render' );
	$expect( ! str_contains( mrn_base_stack_get_body_heading_markup( 'Safe heading', array( 'url' => 'javascript:alert(1)' ) ), '<a ' ), 'Unsafe heading destinations cannot render' );
	$empty_stats = $stats; $empty_stats['columns'] = '';
	ob_start(); mrn_base_stack_render_builder_row( $empty_stats, $id, 5 ); $old_stats = ob_get_clean();
	$expect( str_contains( $old_stats, 'mrn-content-builder__row--stats-columns-2' ), 'Blank existing Stats column values retain their previous two-column default' );
} finally { wp_delete_post( $id, true ); }
$expect( null === get_post( $id ), 'Owned draft removed' );
WP_CLI::line( wp_json_encode( array( 'result' => 'passed', 'count' => count( $checks ), 'checks' => $checks, 'candidate_only' => true ), JSON_PRETTY_PRINT ) );
