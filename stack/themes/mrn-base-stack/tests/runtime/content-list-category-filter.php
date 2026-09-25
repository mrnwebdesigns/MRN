<?php
// phpcs:ignoreFile -- Local-only WordPress/ACF integration fixture.
/**
 * Run setup|check|cleanup using wp eval-file and MRN_CATEGORY_FIXTURE=/private/state.json.
 * Requires an isolated .localhost WordPress runtime with ACF and this parent theme.
 */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! str_ends_with( (string) wp_parse_url( home_url(), PHP_URL_HOST ), '.localhost' ) ) {
	throw new RuntimeException( 'Local .localhost WordPress runtime required.' );
}
$path = getenv( 'MRN_CATEGORY_FIXTURE' );
$action = $args[0] ?? 'check';
if ( ! $path || '/' !== $path[0] || ! function_exists( 'update_field' ) ) {
	throw new RuntimeException( 'ACF and an absolute MRN_CATEGORY_FIXTURE state path are required.' );
}
function mrn_category_assert( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
	echo 'PASS: ' . $message . PHP_EOL;
}
function mrn_category_state( $path, $state ) {
	file_put_contents( $path, wp_json_encode( $state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
	chmod( $path, 0600 );
}
if ( 'setup' === $action ) {
	mrn_category_assert( ! file_exists( $path ), 'No existing fixture state' );
	$state = array( 'home' => home_url(), 'terms' => array(), 'posts' => array() );
	mrn_category_state( $path, $state );
	foreach ( array( 'news' => 'category', 'updates' => 'category', 'other' => 'category', 'child' => 'category', 'featured' => 'post_tag', 'special' => 'post_tag' ) as $name => $taxonomy ) {
		$slug = 'mrn-catqa-' . $name;
		mrn_category_assert( ! term_exists( $slug, $taxonomy ), 'Unique fixture term: ' . $slug );
		$term = wp_insert_term( 'Category QA ' . ucfirst( $name ), $taxonomy, array( 'slug' => $slug, 'parent' => 'child' === $name ? $state['terms']['news']['id'] : 0 ) );
		mrn_category_assert( ! is_wp_error( $term ), 'Created term: ' . $name );
		$state['terms'][ $name ] = array( 'id' => $term['term_id'], 'taxonomy' => $taxonomy, 'slug' => $slug );
		mrn_category_state( $path, $state );
	}
	foreach ( array( 'both', 'category_only', 'tag_only', 'neither', 'two_categories', 'child', 'page', 'empty_page' ) as $name ) {
		$id = wp_insert_post( array( 'post_title' => 'Category QA ' . $name, 'post_type' => str_contains( $name, 'page' ) ? 'page' : 'post', 'post_status' => 'publish' ), true );
		mrn_category_assert( ! is_wp_error( $id ), 'Created fixture: ' . $name );
		$state['posts'][ $name ] = $id;
		update_post_meta( $id, '_mrn_category_fixture', 'content-list-category-filter' );
		mrn_category_state( $path, $state );
		if ( str_contains( $name, 'page' ) ) {
			continue;
		}
		$categories = array( 'child' === $name ? 'child' : ( in_array( $name, array( 'tag_only', 'neither' ), true ) ? 'other' : 'news' ) );
		if ( 'two_categories' === $name ) {
			$categories[] = 'updates';
		}
		wp_set_object_terms( $id, array_map( static fn( $term ) => (int) $state['terms'][ $term ]['id'], $categories ), 'category' );
		if ( ! in_array( $name, array( 'category_only', 'neither' ), true ) ) {
			wp_set_object_terms( $id, array( (int) $state['terms']['featured']['id'] ), 'post_tag' );
		}
		if ( 'two_categories' === $name ) {
			wp_set_object_terms( $id, array( (int) $state['terms']['special']['id'] ), 'post_tag', true );
		}
	}
	register_taxonomy_for_object_type( 'category', 'page' );
	wp_set_object_terms( $state['posts']['page'], array( (int) $state['terms']['news']['id'] ), 'category' );
	$row = array(
		'acf_fc_layout' => 'content_lists', 'internal_name' => 'Category filter QA', 'heading' => 'Category filter QA',
		'list_post_type' => 'post', 'filter_source' => 'manual_terms', 'filter_taxonomy' => 'post_tag',
		'filter_term_slugs' => 'mrn-catqa-featured', 'filter_match' => 'any',
		'category_filter_source' => 'manual_terms', 'category_filter_term_slugs' => 'mrn-catqa-news', 'category_filter_match' => 'any',
		'posts_per_page' => 20, 'heading_tag' => 'h2', 'link_items' => 1,
	);
	update_field( 'field_mrn_page_content_rows', array( $row ), $state['posts']['page'] );
	$state['url'] = get_permalink( $state['posts']['page'] );
	$state['edit_url'] = admin_url( 'post.php?post=' . $state['posts']['page'] . '&action=edit' );
	mrn_category_state( $path, $state );
	echo 'Fixture ready: ' . $state['url'] . PHP_EOL;
	return;
}
$state = json_decode( file_get_contents( $path ), true );
mrn_category_assert( $state['home'] === home_url(), 'Fixture belongs to this runtime' );
if ( 'cleanup' === $action ) {
	foreach ( $state['posts'] as $id ) {
		mrn_category_assert( 'content-list-category-filter' === get_post_meta( $id, '_mrn_category_fixture', true ), 'Delete only owned fixture post ' . $id );
		wp_delete_post( $id, true );
	}
	foreach ( array_reverse( $state['terms'] ) as $term ) {
		$record = get_term( $term['id'], $term['taxonomy'] );
		mrn_category_assert( $record instanceof WP_Term && $record->slug === $term['slug'], 'Delete only owned fixture term' );
		wp_delete_term( $term['id'], $term['taxonomy'] );
	}
	unlink( $path );
	return;
}
mrn_category_assert( 'check' === $action, 'Recognized fixture action' );
$row = get_field( 'page_content_rows', $state['posts']['page'] )[0];
mrn_category_assert( 'post_tag' === $row['filter_taxonomy'] && 'mrn-catqa-featured' === $row['filter_term_slugs'], 'Existing tag fields survive ACF save/reload' );
mrn_category_assert( 'manual_terms' === $row['category_filter_source'] && 'mrn-catqa-news' === $row['category_filter_term_slugs'], 'Category fields survive ACF save/reload' );
$cases = array(
	'Combined tags and categories' => array( array(), array( 'both', 'two_categories', 'child' ) ),
	'Tag only preserves existing behavior' => array( array( 'category_filter_source' => 'none' ), array( 'both', 'tag_only', 'two_categories', 'child' ) ),
	'Category only includes descendants' => array( array( 'filter_source' => 'none' ), array( 'both', 'category_only', 'two_categories', 'child' ) ),
	'Any selected category plus tag' => array( array( 'category_filter_term_slugs' => 'mrn-catqa-news,mrn-catqa-other' ), array( 'both', 'tag_only', 'two_categories', 'child' ) ),
	'All selected categories plus tag' => array( array( 'category_filter_term_slugs' => 'mrn-catqa-news,mrn-catqa-updates', 'category_filter_match' => 'all' ), array( 'two_categories' ) ),
	'All selected tags plus category' => array( array( 'filter_term_slugs' => 'mrn-catqa-featured,mrn-catqa-special', 'filter_match' => 'all' ), array( 'two_categories' ) ),
	'Unknown category returns no content' => array( array( 'category_filter_term_slugs' => 'mrn-catqa-does-not-exist' ), array() ),
	'Empty optional categories preserve tag filter' => array( array( 'category_filter_term_slugs' => '' ), array( 'both', 'tag_only', 'two_categories', 'child' ) ),
	'Current page categories plus tag' => array( array( 'category_filter_source' => 'current_post_terms' ), array( 'both', 'two_categories', 'child' ) ),
	'No current page categories returns no content' => array( array( 'category_filter_source' => 'current_post_terms', 'context' => $state['posts']['empty_page'] ), array() ),
	'Legacy category filter remains valid' => array( array( 'category_filter_source' => 'none', 'filter_taxonomy' => 'category', 'filter_term_slugs' => 'mrn-catqa-news' ), array( 'both', 'category_only', 'two_categories', 'child' ) ),
);
foreach ( $cases as $label => list( $overrides, $names ) ) {
	$settings = array_replace( $row, $overrides );
	$query = new WP_Query( array( 'post_type' => 'post', 'posts_per_page' => -1, 'post_status' => 'publish', 'fields' => 'ids', 'tax_query' => mrn_base_stack_get_content_list_tax_query( $settings, $overrides['context'] ?? $state['posts']['page'], 'post' ) ) );
	$actual = $query->posts;
	$expected = array_map( static fn( $name ) => $state['posts'][ $name ], $names );
	sort( $actual ); sort( $expected );
	mrn_category_assert( $actual === $expected, $label . ' returns exactly the expected posts' );
}
$legacy = $row;
unset( $legacy['category_filter_source'], $legacy['category_filter_term_slugs'], $legacy['category_filter_match'] );
mrn_category_assert( mrn_base_stack_get_content_list_tax_query( $legacy, 0, 'post' ) === mrn_base_stack_get_content_list_tax_query( array_replace( $row, array( 'category_filter_source' => 'none' ) ), 0, 'post' ), 'Rows without new fields are unchanged' );
register_post_type( 'mrn_qa_no_tax', array( 'public' => true, 'show_ui' => true ) );
mrn_category_assert( array() === mrn_base_stack_get_content_list_tax_query( $row, 0, 'mrn_qa_no_tax' ), 'Unsupported source ignores unavailable taxonomies' );
foreach ( mrn_base_stack_get_content_builder_source_layouts() as $layout ) {
	if ( 'content_lists' !== $layout['name'] ) { continue; }
	$clone = mrn_base_stack_clone_acf_keys_with_prefix( $layout, 'category_qa_' );
	foreach ( $clone['sub_fields'] as $field ) {
		if ( 'category_filter_term_slugs' === $field['name'] ) {
			mrn_category_assert( 'category_qa_field_mrn_content_lists_category_filter_source' === $field['conditional_logic'][0][0]['field'], 'Cloned category conditions reference cloned source keys' );
		}
	}
}
ob_start();
get_template_part( 'template-parts/builder/content-lists', null, array( 'row' => $row, 'post_id' => $state['posts']['page'], 'index' => 0 ) );
$html = ob_get_clean();
mrn_category_assert( str_contains( $html, 'Category QA both' ) && str_contains( $html, 'Category QA child' ) && ! str_contains( $html, 'Category QA tag_only' ) && ! str_contains( $html, 'Category QA category_only' ), 'Real row renderer respects the combined filter' );
echo 'Content list category regression checks passed.' . PHP_EOL;
