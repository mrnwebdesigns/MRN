<?php
// phpcs:ignoreFile -- Isolated WordPress boundary stubs exercise the actual carousel, filter, query and render helpers.
if ( 'cli' !== PHP_SAPI ) { exit; }
class WP_Post { public $ID = 27; public $post_type = 'case_study'; public $post_status = 'publish'; }
class WP_Error {}
function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $v ) { return esc_html( $v ); }
function esc_attr__( $v, $domain = '' ) { return esc_attr( $v ); }
function esc_html__( $v, $domain = '' ) { return esc_html( $v ); }
function __( $v, $domain = '' ) { return $v; }
function esc_url_raw( $v, $schemes = array() ) { return is_string( $v ) && preg_match( '/^https?:\/\//', $v ) ? $v : ''; }
function esc_url( $v, $schemes = array() ) { return esc_attr( esc_url_raw( $v, $schemes ) ); }
function absint( $v ) { return abs( (int) $v ); }
function sanitize_key( $v ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $v ) ); }
function sanitize_title( $v ) { return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $v ) ), '-' ); }
function sanitize_html_class( $v ) { return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $v ); }
function wp_unique_id( $prefix = '' ) { static $n = 0; return $prefix . ++$n; }
function is_wp_error( $v ) { return $v instanceof WP_Error; }
function mrn_base_stack_get_background_color_field( $key ) { return array( 'key' => $key, 'name' => 'background_color', 'type' => 'select' ); }
function mrn_rbl_get_content_link_fields( $seed, $label, $name, $max ) { return array( array( 'key' => $seed, 'name' => $name, 'type' => 'repeater', 'max' => $max ) ); }
function mrn_base_stack_enqueue_presentation() { $GLOBALS['enqueued'] = true; }
function mrn_site_colors_get_all() { return array( array( 'slug' => 'ink' ) ); }
function mrn_site_colors_get_css_var( $slug ) { return '--site-color-' . $slug; }
function get_field( $field, $id ) { return false; }
function get_post_meta( $id, $field, $single ) { return ''; }
function get_post_thumbnail_id( $post ) { return 101; }
function wp_attachment_is_image( $id ) { return 101 === $id; }
function mrn_base_stack_get_image_attachment_id( $value ) { return is_numeric( $value ) ? (int) $value : 0; }
function taxonomy_exists( $tax ) { return in_array( $tax, array( 'service', 'industry', 'feature' ), true ); }
function is_object_in_taxonomy( $type, $tax ) { return 'case_study' === $type; }
function get_the_terms( $id, $tax ) { return $GLOBALS['terms'][ $tax ] ?? false; }
function get_terms( $args ) { return $GLOBALS['all_terms'][ $args['taxonomy'] ] ?? new WP_Error(); }
function get_the_title( $post ) { return $GLOBALS['title']; }
function get_permalink( $post ) { return 'https://example.test/project/'; }
function wp_get_attachment_url( $id ) { return false; }
function mrn_base_stack_get_attachment_image( $id, $size, $attr ) { return '<img src="https://example.test/' . $id . '.jpg" alt="' . esc_attr( $attr['alt'] ) . '">'; }
require_once dirname( __DIR__, 2 ) . '/inc/builder/hero-frame.php';
require_once dirname( __DIR__, 2 ) . '/inc/builder/card-frame.php';
require_once dirname( __DIR__, 2 ) . '/inc/builder/content-frame.php';
require_once dirname( __DIR__, 2 ) . '/inc/builder/faq-aside.php';
require_once dirname( __DIR__, 2 ) . '/inc/builder/content-list-projects.php';
require_once dirname( __DIR__, 2 ) . '/inc/builder/content-list-project-filters.php';
$checks = array(); $expect = static function ( $ok, $message ) use ( &$checks ) { if ( ! $ok ) { throw new RuntimeException( $message ); } $checks[] = $message; };
$term = static fn( $slug, $name, $count = 1 ) => (object) array( 'slug' => $slug, 'name' => $name, 'count' => $count );

// Carousel.
$carousel = array( 'record_presentation' => 'projects', 'project_layout' => 'carousel', 'project_carousel_autoplay' => 1 );
$contract = mrn_base_stack_get_project_list_contract( $carousel );
$expect( in_array( 'has-project-carousel', $contract['classes'], true ) && 'autoplay' === $contract['attributes']['data-mrn-project-carousel'], 'Carousel layout adds its class and autoplay mode' );
$manual = mrn_base_stack_get_project_list_contract( array( 'project_carousel_autoplay' => 0 ) + $carousel );
$expect( 'manual' === $manual['attributes']['data-mrn-project-carousel'], 'Autoplay is off unless selected' );
foreach ( array( '', 'grid', 'carousel ', array( 'carousel' ) ) as $i => $bad ) { $probe = array( 'project_layout' => $bad ) + $carousel; $expect( ! in_array( 'has-project-carousel', mrn_base_stack_get_project_list_contract( $probe )['classes'], true ), 'Unknown layout value ' . $i . ' keeps the grid' ); }
$expect( ! in_array( 'has-project-carousel', mrn_base_stack_get_project_list_contract( array( 'record_presentation' => 'people' ) + $carousel )['classes'], true ), 'Other presentations never become carousels' );
$expect( '' === mrn_base_stack_get_project_carousel_markup( array( 'record_presentation' => 'projects' ), 3, 'list' ) && '' === mrn_base_stack_get_project_carousel_markup( array(), 3, 'list', true ), 'Grid rows get no carousel markup' );
$open = mrn_base_stack_get_project_carousel_markup( $carousel, 3, 'list' ); $close = mrn_base_stack_get_project_carousel_markup( $carousel, 3, 'list', true );
$expect( str_contains( $open, 'data-mrn-project-viewport' ) && str_contains( $close, 'data-mrn-project-prev' ) && str_contains( $close, 'data-mrn-project-next' ) && 2 === substr_count( $close, 'aria-controls="list"' ) && 2 === substr_count( $close, ' hidden ' ), 'Two or more records get hidden-until-enhanced buttons bound to the list' );
$expect( ! str_contains( mrn_base_stack_get_project_carousel_markup( $carousel, 1, 'list', true ), '<button' ) && ! str_contains( mrn_base_stack_get_project_carousel_markup( $carousel, 0, 'list', true ), '<button' ), 'A single record gets no carousel buttons' );
$gap = mrn_base_stack_get_project_list_contract( $carousel + array( 'project_carousel_gap' => '30px', 'project_carousel_card_padding_bottom' => '10px', 'project_carousel_control_top' => '1px;position:fixed' ) );
$expect( str_contains( $gap['attributes']['style'], '--mrn-project-carousel-gap:30px' ) && str_contains( $gap['attributes']['style'], '--mrn-project-carousel-card-padding-bottom:10px' ) && ! str_contains( $gap['attributes']['style'], 'position' ), 'Carousel dimensions are bounded custom properties' );

// Collection query: skip records marked off.
$query = array( 'post_type' => 'case_study', 'posts_per_page' => -1, 'orderby' => 'title' );
$row = array( 'record_presentation' => 'projects', 'project_selection' => 'unless_off', 'project_flag_field' => 'show' );
$out = mrn_base_stack_get_project_collection_query( $row, $query );
$expect( array( array( 'relation' => 'OR', array( 'key' => 'show', 'compare' => 'NOT EXISTS' ), array( 'key' => 'show', 'value' => '0', 'compare' => '!=' ) ) ) === $out['meta_query'], 'Skip Records Marked Off keeps unset records and hides only a stored zero' );
foreach ( array( '', 'show OR 1=1', true ) as $i => $bad ) { $probe = $row; $probe['project_flag_field'] = $bad; $expect( array( 0 ) === mrn_base_stack_get_project_collection_query( $probe, $query )['post__in'], 'Malformed skip field ' . $i . ' returns no records' ); }
$expect( $query === mrn_base_stack_get_project_collection_query( array( 'record_presentation' => 'people' ) + $row, $query ), 'Other presentations keep their query' );

// Fields.
$fields = mrn_base_stack_get_project_list_fields( 'field_projects' );
$expect( count( $fields ) === count( array_unique( array_column( $fields, 'key' ) ) ) && count( $fields ) === count( array_unique( array_column( $fields, 'name' ) ) ), 'Project, carousel and filter fields keep unique keys and names' );
$expect( ! array_filter( $fields, static fn( $field ) => $field['conditional_logic'] !== $fields[0]['conditional_logic'] ), 'Every project field stays scoped to the project presentation' );
$expect( in_array( 'project_filters', array_column( $fields, 'name' ), true ) && in_array( 'project_layout', array_column( $fields, 'name' ), true ) && in_array( 'project_media_ratio', array_column( $fields, 'name' ), true ), 'Layout, filter and ratio fields are registered' );

// Media ratio.
foreach ( array( '360/420' => '360 / 420', ' 4 / 3 ' => '4 / 3', '1.5/1' => '1.5 / 1' ) as $raw => $expected ) { $expect( $expected === mrn_base_stack_get_project_media_ratio( array( 'project_media_ratio' => $raw ) ), 'Ratio ' . trim( $raw ) . ' is accepted' ); }
foreach ( array( '0/5', '5/0', 'auto', '1/1;x', '-1/2', array( '1/1' ), false, '99999/1' ) as $i => $bad ) { $expect( '' === mrn_base_stack_get_project_media_ratio( array( 'project_media_ratio' => $bad ) ), 'Invalid ratio ' . $i . ' is ignored' ); }

// Filters.
$GLOBALS['all_terms'] = array( 'service' => array( $term( 'logo', 'Logo Design', 10 ), $term( 'website-maintenance', 'Website Maintenance', 2 ), $term( 'web', 'Websites', 27 ) ), 'industry' => array( $term( 'retail', 'Retail & Co', 3 ) ), 'feature' => array( $term( 'blog', 'Blog', 7 ) ) );
$filters = array( 'record_presentation' => 'projects', 'project_filters' => 'taxonomy-controls', 'project_filter_tabs_taxonomy' => 'service', 'project_filter_tabs_excluded' => 'website-maintenance', 'project_filter_one_taxonomy' => 'industry', 'project_filter_one_terms' => 'first', 'project_filter_two_taxonomy' => 'feature', 'project_filter_initial' => 9, 'project_filter_batch' => 6 );
$fc = mrn_base_stack_get_project_filter_contract( $filters );
$expect( in_array( 'has-project-filters', $fc['classes'], true ) && '9,6' === $fc['attributes']['data-mrn-project-filters'], 'Filtered rows expose their batch sizes' );
$expect( array() === mrn_base_stack_get_project_filter_contract( array( 'project_filters' => 'taxonomy-controls' ) )['classes'] && array() === mrn_base_stack_get_project_filter_contract( array( 'project_filters' => 'x' ) + $filters )['classes'], 'Filters only run for project lists with the exact mode' );
$bounds = mrn_base_stack_get_project_filter_contract( array( 'project_filter_initial' => 99999, 'project_filter_batch' => 0 ) + $filters );
$expect( '1000,1' === $bounds['attributes']['data-mrn-project-filters'], 'Batch sizes are clamped to 1 through 1000' );
$controls = mrn_base_stack_get_project_filter_controls( $filters, 'case_study' );
$expect( str_contains( $controls, 'data-project-controls hidden' ) && str_contains( $controls, 'data-project-tab="logo"' ) && str_contains( $controls, 'data-project-tab="web"' ) && ! str_contains( $controls, 'website-maintenance' ), 'Tabs are hidden until enhanced and skip excluded terms' );
$expect( str_contains( $controls, 'Retail &amp; Co' ) && str_contains( $controls, 'data-project-group="one"' ) && str_contains( $controls, 'data-project-group="two"' ) && str_contains( $controls, 'data-project-reset disabled' ), 'Dropdowns escape labels and reset starts disabled' );
$expect( 2 === substr_count( $controls, 'aria-expanded="false"' ) && ! str_contains( $controls, 'aria-expanded="true"' ), 'Both dropdown triggers start collapsed' );
$expect( '' === mrn_base_stack_get_project_filter_controls( array( 'record_presentation' => 'projects' ), 'case_study' ) && '' === mrn_base_stack_get_project_filter_controls( array( 'record_presentation' => 'projects' ), 'case_study', true ), 'Unfiltered rows output nothing' );
$more = mrn_base_stack_get_project_filter_controls( $filters, 'case_study', true );
$expect( str_contains( $more, 'data-project-more' ) && str_contains( $more, 'role="status"' ) && str_contains( $more, 'hidden' ), 'Load more and the status region follow the list' );
$unknown = mrn_base_stack_get_project_filter_controls( array( 'project_filter_tabs_taxonomy' => 'missing' ) + $filters, 'case_study' );
$expect( ! str_contains( $unknown, 'mrn-project-filters__tabs' ), 'An unregistered tab taxonomy produces no tabs' );
$post = new WP_Post(); $GLOBALS['terms'] = array( 'service' => array( $term( 'logo', 'Logo' ), $term( 'web', 'Web' ) ), 'industry' => array( $term( 'retail', 'Retail' ), $term( 'tech', 'Tech' ) ), 'feature' => array( $term( 'blog', 'Blog' ), $term( 'seo', 'SEO' ) ) );
$attrs = mrn_base_stack_get_project_filter_item_attributes( $post, $filters );
$expect( str_contains( $attrs, 'data-project-tabs="logo web"' ) && str_contains( $attrs, 'data-project-one="retail"' ) && ! str_contains( $attrs, 'tech' ) && str_contains( $attrs, 'data-project-two="blog seo"' ) && str_contains( $attrs, 'data-project-card' ), 'Card data keeps every term, or only the first where selected' );
$expect( ! str_contains( mrn_base_stack_get_project_filter_item_attributes( $post, $filters, 8 ), 'data-project-late' ) && str_contains( mrn_base_stack_get_project_filter_item_attributes( $post, $filters, 9 ), ' data-project-late' ) && ! str_contains( mrn_base_stack_get_project_filter_item_attributes( $post, $filters ), 'data-project-late' ), 'Only records past the first batch are marked late' );
$expect( '' === mrn_base_stack_get_project_filter_item_attributes( $post, array( 'record_presentation' => 'projects' ) ), 'Plain project cards gain no filter attributes' );
$GLOBALS['title'] = 'Amish Mike <b>'; $html = mrn_base_stack_render_project_list_item( $post, array( 'project_settings' => $filters + array( 'project_heading_tag' => 'h3' ), 'link_items' => true ) );
$expect( str_contains( $html, '<span class="mrn-project__title-tail">&lt;b&gt;<svg' ) && str_contains( $html, 'Amish Mike ' ) && 1 === substr_count( $html, 'mrn-project__arrow' ), 'The last word and arrow stay together, escaped, with one arrow' );
$plain = mrn_base_stack_render_project_list_item( $post, array( 'project_settings' => array( 'record_presentation' => 'projects' ), 'link_items' => true ) );
$expect( ! str_contains( $plain, 'title-tail' ) && ! str_contains( $plain, 'data-project-card' ), 'Unfiltered cards keep their existing markup' );
$GLOBALS['title'] = 'Solo'; $solo = mrn_base_stack_render_project_list_item( $post, array( 'project_settings' => $filters, 'link_items' => true ) );
$expect( str_contains( $solo, '<span class="mrn-project__title-tail">Solo<svg' ), 'A one-word title keeps its arrow with the word' );
echo json_encode( array( 'result' => 'passed', 'assertions' => $checks, 'scope' => 'Real source helpers with isolated WordPress boundaries; registered runtime, browser and complete-page acceptance remain separate.' ), JSON_PRETTY_PRINT ) . "\n";
