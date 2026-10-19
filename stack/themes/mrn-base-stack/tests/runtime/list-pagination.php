<?php
// phpcs:ignoreFile -- Disposable native WordPress/ACF regression fixture.
/** Seed list owners without touching a client runtime. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || 'local' !== wp_get_environment_type() || ! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( '127.0.0.1', 'localhost' ), true ) ) {
	throw new RuntimeException( 'Disposable loopback runtime required.' );
}
$posts = array();
foreach ( range( 1, 7 ) as $number ) {
	$posts[] = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Pagination item ' . $number, 'post_name' => 'pagination-item-' . $number, 'post_content' => '<p>Published fixture article.</p>' ) );
}
$row = array(
	'acf_fc_layout' => 'content_lists', 'heading' => 'Fixture articles', 'heading_tag' => 'h2',
	'list_post_type' => 'post', 'enable_pagination' => 1, 'posts_per_page' => 3,
	'filter_source' => 'manual_posts', 'filter_posts' => $posts, 'link_items' => 1,
	'orderby' => 'date', 'order' => 'DESC', 'offset' => 2,
);
$block = wp_insert_post( array( 'post_type' => 'mrn_reusable_list', 'post_status' => 'publish', 'post_title' => 'Reusable paginated fixture' ) );
foreach ( array( 'post_type' => 'post', 'pagination' => 1, 'filter_source' => 'manual_posts', 'filter_posts' => $posts, 'posts_per_page' => 3 ) as $suffix => $value ) {
	$field = 'field_mrn_reusable_content_lists_' . $suffix;
	if ( ! acf_get_field( $field ) ) {
		throw new RuntimeException( 'Missing native reusable field: ' . $field );
	}
	update_field( $field, $value, $block );
}
$pages = array();
foreach ( array( 'single', 'multiple', 'empty', 'nested' ) as $name ) {
	$id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Pagination ' . $name, 'post_name' => 'pagination-' . $name ) );
	$rows = array( $row );
	if ( 'multiple' === $name ) {
		$second                   = $row;
		$second['filter_posts']    = array_reverse( $posts );
		$second['heading']         = 'Second independent list';
		$rows[]                   = $second;
	} elseif ( 'empty' === $name ) {
		$rows[0]['filter_posts'] = array();
	} elseif ( 'nested' === $name ) {
		$rows = array( array( 'acf_fc_layout' => 'two_column_split', 'left_column_rows' => array( array( 'acf_fc_layout' => 'reusable_block', 'block' => $block ) ), 'column_rows_mode' => 'stack' ) );
	}
	update_field( 'field_mrn_page_content_rows', $rows, $id );
	$pages[ $name ] = array( 'id' => $id, 'url' => get_permalink( $id ) );
}
foreach ( array( 'team_member', 'job_posting' ) as $type ) {
	$id = wp_insert_post( array( 'post_type' => $type, 'post_status' => 'publish', 'post_title' => 'Pagination ' . $type, 'post_name' => 'pagination-' . $type ) );
	update_field( 'field_mrn_page_after_content_rows', array( array( 'acf_fc_layout' => 'reusable_block', 'block' => $block ) ), $id );
	$pages[ $type ] = array( 'id' => $id, 'url' => get_permalink( $id ) );
}
$gallery = array();
foreach ( range( 1, 4 ) as $number ) {
	$gallery[] = wp_insert_post( array( 'post_type' => 'gallery', 'post_status' => 'publish', 'post_title' => 'Retained gallery fixture ' . $number ) );
}
$id       = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Disabled source fixture' ) );
$disabled = array_merge( $row, array( 'list_post_type' => 'gallery', 'filter_posts' => $gallery ) );
update_field( 'field_mrn_page_content_rows', array( $disabled ), $id );
$pages['disabled_source'] = array( 'id' => $id, 'url' => get_permalink( $id ) );
$titles = get_option( 'seopress_titles_option_name', array() );
foreach ( array( 'page', 'team_member', 'job_posting' ) as $type ) {
	$titles['seopress_titles_single_titles'][ $type ]['title'] = '%%post_title%%';
	$titles['seopress_titles_single_titles'][ $type ]['description'] = 'Native pagination regression fixture.';
}
update_option( 'seopress_titles_option_name', $titles );
$social = get_option( 'seopress_social_option_name', array() );
$social['seopress_social_facebook_og'] = '1';
update_option( 'seopress_social_option_name', $social );
update_option( 'posts_per_page', 3 );
echo wp_json_encode( array( 'pages' => $pages, 'posts' => $posts, 'gallery_url' => get_permalink( $gallery[0] ), 'archive_url' => get_term_link( get_term( (int) get_option( 'default_category' ), 'category' ) ) . 'page/2/' ) );
