<?php
// phpcs:ignoreFile -- Isolated WordPress boundary stubs exercise actual query/data/render/style helpers.
if ( 'cli' !== PHP_SAPI ) { exit; }
class WP_Post { public $ID = 27; public $post_type = 'case_study'; public $post_status = 'publish'; }
function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $v ) { return esc_html( $v ); }
function esc_attr__( $v, $domain = '' ) { return esc_attr( $v ); }
function __( $v, $domain = '' ) { return $v; }
function esc_url_raw( $v, $schemes = array() ) { return is_string( $v ) && preg_match( '/^https?:\/\//', $v ) ? $v : ''; }
function esc_url( $v, $schemes = array() ) { return esc_attr( esc_url_raw( $v, $schemes ) ); }
function mrn_base_stack_normalize_card_per_row( $v ) { return max( 1, min( 6, (int) $v ) ); }
function sanitize_key( $v ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $v ) ); }
function mrn_base_stack_get_background_color_field( $key ) { return array( 'key' => $key, 'name' => 'background_color', 'type' => 'select' ); }
function mrn_rbl_get_content_link_fields( $seed, $label, $name, $max ) { return array( array( 'key' => $seed, 'name' => $name, 'type' => 'repeater', 'max' => $max ) ); }
function mrn_base_stack_enqueue_presentation() { $GLOBALS['enqueued'] = true; }
function mrn_site_colors_get_all() { return array( array( 'slug' => 'ink' ) ); }
function mrn_site_colors_get_css_var( $slug ) { return '--site-color-' . $slug; }
function get_field( $field, $id ) { return $GLOBALS['values'][$field] ?? false; }
function get_post_meta( $id, $field, $single ) { return $GLOBALS['raw'][$field] ?? ''; }
function get_post_thumbnail_id( $post ) { return 101; }
function wp_attachment_is_image( $id ) { return in_array( $id, array( 101, 202 ), true ); }
function mrn_base_stack_get_image_attachment_id( $value ) { return is_array( $value ) ? (int) ( $value['ID'] ?? 0 ) : ( is_numeric( $value ) ? (int) $value : 0 ); }
function taxonomy_exists( $tax ) { return in_array( $tax, array( 'industry', 'features' ), true ); }
function is_object_in_taxonomy( $type, $tax ) { return 'case_study' === $type; }
function get_the_terms( $id, $tax ) { return $GLOBALS['terms'][$tax] ?? false; }
function get_the_title( $post ) { return 'Example & <Project>'; }
function get_permalink( $post ) { return 'https://example.test/project/?a=1&b=2'; }
function wp_get_attachment_url( $id ) { return 303 === $id ? 'https://example.test/preview.mp4' : false; }
function mrn_base_stack_get_attachment_image( $id, $size, $attr ) { $GLOBALS['image_request'] = array( $id, $size, $attr ); return '<img src="https://example.test/' . $id . '.jpg" alt="' . esc_attr( $attr['alt'] ) . '">'; }
require_once dirname( __DIR__, 2 ) . '/inc/builder/hero-frame.php';
require_once dirname( __DIR__, 2 ) . '/inc/builder/card-frame.php';
require_once dirname( __DIR__, 2 ) . '/inc/builder/content-frame.php';
require_once dirname( __DIR__, 2 ) . '/inc/builder/faq-aside.php';
require_once dirname( __DIR__, 2 ) . '/inc/builder/content-list-projects.php';
$checks = array(); $expect = static function ( $ok, $message ) use ( &$checks ) { if ( ! $ok ) { throw new RuntimeException( $message ); } $checks[] = $message; };
$query = array( 'post_type' => 'case_study', 'post_status' => 'publish', 'posts_per_page' => 3, 'orderby' => array( 'menu_order' => 'ASC', 'date' => 'DESC' ), 'post__in' => array( 27, 28 ) );
foreach ( array( '', 'standard', 'people', 'comparisons', 'careers', 'articles' ) as $profile ) { $row = array( 'record_presentation' => $profile, 'project_selection' => 'flagged', 'project_flag_field' => 'story' ); $expect( $query === mrn_base_stack_get_project_collection_query( $row, $query ) && array( 'classes' => array(), 'attributes' => array() ) === mrn_base_stack_get_project_list_contract( $row ) && '' === mrn_base_stack_get_project_actions( $row ), 'Existing query, style and actions stay dormant: ' . $profile ); }
$row = array( 'record_presentation' => 'projects', 'project_selection' => 'flagged', 'project_flag_field' => 'story' ); $out = mrn_base_stack_get_project_collection_query( $row, $query );
$expect( $out['post__in'] === $query['post__in'] && $out['orderby'] === $query['orderby'] && 3 === $out['posts_per_page'], 'Flag selection keeps manual identity, explicit order and count' );
$expect( array( array( 'key' => 'story', 'value' => '1', 'compare' => '=' ) ) === $out['meta_query'], 'Marked records require a bound equality instead of arbitrary SQL' );
$prior = $query; $prior['meta_query'] = array( 'relation' => 'OR', array( 'key' => 'visible', 'value' => '1' ), array( 'key' => 'visible', 'compare' => 'NOT EXISTS' ) ); $out = mrn_base_stack_get_project_collection_query( $row, $prior ); $expect( 'AND' === $out['meta_query']['relation'] && $prior['meta_query'] === $out['meta_query'][0], 'Prior OR predicates remain grouped under the required flag AND' );
foreach ( array( '', 'story OR 1=1', array( 'story' ), true, 'story-name' ) as $i => $bad ) { $probe = $row; $probe['project_flag_field'] = $bad; $expect( array( 0 ) === mrn_base_stack_get_project_collection_query( $probe, $query )['post__in'], 'Malformed required field returns no records: ' . $i ); }
$plain = $row; $plain['project_selection'] = ''; $expect( $query === mrn_base_stack_get_project_collection_query( $plain, $query ), 'All-source mode preserves the existing query exactly' );
$fields = mrn_base_stack_get_project_list_fields( 'field_projects' ); $expect( count( $fields ) === count( array_unique( array_column( $fields, 'key' ) ) ) && count( $fields ) === count( array_unique( array_column( $fields, 'name' ) ) ), 'Native project fields have unique names and keys' );
$expect( ! array_intersect( array_column( $fields, 'key' ), array_column( mrn_base_stack_get_project_list_fields( 'field_other' ), 'key' ) ), 'Separate project row factories cannot share ACF keys' );
$expect( ! array_filter( $fields, static fn( $field ) => $field['conditional_logic'] !== $fields[0]['conditional_logic'] ), 'All project fields remain scoped to the selected project presentation' );
$post = new WP_Post(); $row += array( 'project_image_field' => 'listing_image', 'project_subtitle_field' => 'subtitle', 'project_subtitle_taxonomy' => 'industry', 'project_feature_taxonomy' => 'features', 'project_video_field' => 'video', 'project_image_size' => 'full', 'project_heading_tag' => 'h3' );
$GLOBALS['terms'] = array( 'industry' => array( (object) array( 'name' => 'First industry' ), (object) array( 'name' => 'Later industry' ) ), 'features' => array( (object) array( 'name' => 'Zeta & exact' ), (object) array( 'name' => '<Alpha>' ) ) );
$GLOBALS['values'] = array( 'listing_image' => array( 'ID' => 202 ), 'subtitle' => '  Authored subtitle  ', 'video' => array( 'url' => 'https://example.test/hover.mp4' ) );
$data = mrn_base_stack_get_project_card_data( $post, $row ); $expect( 202 === $data['image'] && 'Authored subtitle' === $data['subtitle'], 'Valid listing-image override and authored subtitle remain current' ); $expect( array( 'Zeta & exact', '<Alpha>' ) === array_column( $data['features'], 'name' ), 'Feature labels preserve returned source order without alphabetizing' );
$GLOBALS['values']['listing_image'] = 999; $GLOBALS['values']['subtitle'] = ''; $data = mrn_base_stack_get_project_card_data( $post, $row ); $expect( 101 === $data['image'] && 'First industry' === $data['subtitle'], 'Invalid image falls back to featured image and blank subtitle uses the first industry term' );
$GLOBALS['values']['listing_image'] = false; $GLOBALS['raw']['listing_image'] = 202; $expect( 202 === mrn_base_stack_get_project_card_data( $post, $row )['image'], 'Existing raw metadata remains usable when no ACF value resolves' );
foreach ( array( 'javascript:alert(1)', array( 'url' => 'data:text/html,bad' ), false ) as $i => $bad ) { $GLOBALS['values']['video'] = $bad; $expect( '' === mrn_base_stack_get_project_card_data( $post, $row )['video'], 'Unsafe or absent video URLs cannot render: ' . $i ); }
$GLOBALS['values']['video'] = 303; $expect( 'https://example.test/preview.mp4' === mrn_base_stack_get_project_card_data( $post, $row )['video'], 'An attachment video resolves through the WordPress URL API' );
$html = mrn_base_stack_render_project_list_item( $post, array( 'project_settings' => $row, 'link_items' => true ) ); $expect( 2 === substr_count( $html, '<a ' ) && str_contains( $html, '<h3 class="mrn-project__title">' ) && str_contains( $html, 'Example &amp; &lt;Project&gt;' ), 'Image and title links preserve semantic heading structure and escape the title' );
$expect( str_contains( $html, 'href="https://example.test/project/?a=1&amp;b=2"' ) && str_contains( $html, 'Zeta &amp; exact' ) && str_contains( $html, '&lt;Alpha&gt;' ), 'Destinations and ordered feature text are escaped independently' );
$expect( str_contains( $html, 'data-mrn-hover-video data-src=' ) && ! str_contains( $html, '<video src=' ) && str_contains( $html, 'preload="none"' ), 'Preview reuses lazy existing hover/focus behavior without eager video requests' ); $expect( array( 202, 'full', array( 'alt' => 'Example & <Project>', 'loading' => 'lazy' ) ) === $GLOBALS['image_request'], 'The selected image size and per-use source title alt stay exact' );
$post->post_status = 'draft'; $expect( '' === mrn_base_stack_render_project_list_item( $post, array( 'project_settings' => $row ) ), 'Unpublished project records never render' ); $post->post_status = 'publish';
$style = $row + array( 'project_content_width' => '1160px', 'project_title_size' => 'var(--site-text-h5-size)', 'project_title_color' => 'ink', 'project_grid_breakpoint' => '576' ); $contract = mrn_base_stack_get_project_list_contract( $style ); $expect( str_contains( $contract['attributes']['style'], '--mrn-project-content-width:1160px' ) && str_contains( $contract['attributes']['style'], '--mrn-project-title-size:var(--site-text-h5-size)' ) && in_array( 'project-grid-at-576', $contract['classes'], true ), 'Actual validators preserve bounded dimensions, Site Text tokens and closed breakpoint choices' );
foreach ( array( '-1px', '1px;position:fixed', 'url(https://example.test/)', array( '1px' ), false ) as $i => $bad ) { $probe = array( 'record_presentation' => 'projects', 'project_content_width' => $bad, 'project_title_size' => $bad ); $out = mrn_base_stack_get_project_list_contract( $probe ); $expect( '--mrn-project-columns:3' === $out['attributes']['style'], 'Malformed dimensions cannot inject CSS or activate a size: ' . $i ); }
$card = mrn_base_stack_get_card_frame_contract( array( 'card_frame' => 'contained', 'card_item_padding' => '32px', 'card_item_radius' => '2px', 'card_item_lift' => '5px', 'card_grid_breakpoints' => '991-576' ) );
$expect( in_array( 'has-card-grid-991-600', $card['classes'], true ) && in_array( 'has-card-grid-991-576', $card['classes'], true ) && str_contains( $card['attributes']['style'], '--mrn-card-frame-item-padding:32px' ), 'Native card geometry and 991/576 grid are explicit opt-ins' );
$expect( in_array( 'has-body-heading-width-compact', mrn_base_stack_get_content_frame_contract( array( 'body_frame' => 'contained', 'body_heading_width_compact' => '100%' ), 'body_text' )['classes'], true ), 'Body heading compact width activates only when validated and selected' );
$expect( in_array( 'has-card-grid-767-single', mrn_base_stack_get_card_frame_contract( array( 'card_frame' => 'contained', 'card_grid_breakpoints' => '767-single', 'cards_per_row' => 3 ) )['classes'], true ), 'Selected-count Cards expose the 767px single-column opt-in' );
$expect( in_array( 'has-split-reverse-columns', mrn_base_stack_get_split_aside_contract( array( 'split_frame' => 'bounded', 'split_stack_breakpoint' => '767', 'split_column_order' => 'reverse' ) )['classes'], true ), 'Bounded Split can retain authored small-screen order and reverse only desktop columns' );
$expect( ! in_array( 'has-split-reverse-columns', mrn_base_stack_get_split_aside_contract( array( 'split_frame' => 'aside', 'split_column_order' => 'reverse' ) )['classes'], true ) && ! in_array( 'has-split-reverse-columns', mrn_base_stack_get_split_aside_contract( array( 'split_frame' => 'bounded', 'split_column_order' => 'reverse injected' ) )['classes'], true ), 'Aside and invalid order selections cannot activate reversed columns' );
if ( in_array( '--render', $argv, true ) ) { echo $html; } else { echo json_encode( array( 'result' => 'passed', 'assertions' => count( $checks ), 'checks' => $checks, 'scope' => 'Real source helpers with isolated WordPress boundaries; registered runtime, browser and complete-page acceptance remain separate.' ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . PHP_EOL; }
