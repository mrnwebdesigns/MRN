<?php
// phpcs:ignoreFile -- Disposable native WordPress/ACF/SEOPress regression fixture.
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || 'local' !== wp_get_environment_type() || ! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( '127.0.0.1', 'localhost' ), true ) ) {
	throw new RuntimeException( 'Disposable loopback runtime required.' );
}
$create = static function ( $name, $type = 'page', $status = 'publish', $native = '' ) {
	return wp_insert_post( array( 'post_type' => $type, 'post_status' => $status, 'post_title' => 'Analysis ' . $name, 'post_name' => 'analysis-' . $name, 'post_content' => $native ) );
};
$row = static function ( $html ) { return array( 'acf_fc_layout' => 'body_text', 'heading' => 'Authored analysis heading', 'heading_tag' => 'h2', 'body_text' => $html ); };
$ids = array();
$ids['target'] = $create( 'target' );
$ids['acf_only_target'] = $create( 'acf-only-target' );
$ids['native'] = $create( 'native', 'post', 'publish', '<h2>Native heading</h2><p>' . str_repeat( 'authored native words ', 120 ) . '</p><p><a href="' . get_permalink( $ids['target'] ) . '">Native incoming</a></p>' );
foreach ( array( 'target', 'acf_only_target' ) as $key ) {
	update_field( 'field_mrn_page_content_rows', array( $row( '<p>' . str_repeat( 'authored ACF body words ', 100 ) . '</p><img src="' . includes_url( 'images/w-logo-blue.png' ) . '" width="256" height="256"><a href="' . get_permalink( $ids[ $key ] ) . '">Self link</a>' ) ), $ids[ $key ] );
}
$links = '<p><a href="' . get_permalink( $ids['target'] ) . '">ACF incoming</a><a href="' . wp_parse_url( get_permalink( $ids['acf_only_target'] ), PHP_URL_PATH ) . '">Relative ACF incoming</a></p>';
foreach ( array( 'acf_source', 'draft_source', 'disabled_source', 'job_source', 'mixed' ) as $key ) {
	$type = 'disabled_source' === $key ? 'team_member' : ( 'job_source' === $key ? 'job_posting' : 'page' );
	$ids[ $key ] = $create( $key, $type, 'draft_source' === $key ? 'draft' : 'publish', 'mixed' === $key ? '<p>A native introduction.</p>' : '' );
	update_field( 'field_mrn_page_content_rows', array( $row( $links . '<p>Original ACF text.</p>' ) ), $ids[ $key ] );
}
$ids['unused_source'] = $create( 'unused-source' );
update_post_meta( $ids['unused_source'], 'unrendered_fixture_field', $links );
$ids['reusable'] = $create( 'reusable', 'mrn_reusable_basic' );
update_field( 'field_mrn_basic_block_text', $links, $ids['reusable'] );
$ids['reusable_host'] = $create( 'reusable-host' );
update_field( 'field_mrn_page_content_rows', array( array( 'acf_fc_layout' => 'reusable_block', 'block' => $ids['reusable'] ) ), $ids['reusable_host'] );
update_option( 'mrn_disabled_post_types', array( 'team_member' ), false );
$titles = get_option( 'seopress_titles_option_name', array() );
$titles['seopress_titles_single_titles']['page'] = array( 'title' => '%%post_title%%', 'description' => 'A native analysis fixture description.' );
update_option( 'seopress_titles_option_name', $titles );
echo wp_json_encode( array( 'ids' => $ids, 'urls' => array_map( 'get_permalink', $ids ) ) );
