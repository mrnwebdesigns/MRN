<?php
// phpcs:ignoreFile -- Explicit local-only integration fixture, run through wp eval-file.
/**
 * Real WordPress/ACF regression fixtures for Reference Content.
 * MRN_REFERENCE_FIXTURE=/absolute/private/state.json wp --path=... eval-file this.php setup|check|cleanup
 * Only creates/deletes its own tagged records; never edits existing site content.
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! str_ends_with( (string) wp_parse_url( home_url(), PHP_URL_HOST ), '.localhost' ) ) {
	throw new RuntimeException( 'This fixture requires an explicitly resolved local .localhost WordPress runtime.' );
}
if ( ! function_exists( 'acf_update_value' ) || ! function_exists( 'update_field' ) || ! function_exists( 'acf_get_field' ) ) {
	throw new RuntimeException( 'ACF Pro must be active in the local runtime.' );
}
$state_path = getenv( 'MRN_REFERENCE_FIXTURE' );
$action     = $args[0] ?? 'check';
if ( ! $state_path || '/' !== $state_path[0] ) {
	throw new RuntimeException( 'Set MRN_REFERENCE_FIXTURE to an absolute local state path.' );
}
function mrn_reference_assert( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
	echo 'PASS: ' . $message . PHP_EOL;
}
function mrn_reference_save_state( $path, $state ) {
	file_put_contents( $path, wp_json_encode( $state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
	chmod( $path, 0600 );
}
if ( 'setup' === $action ) {
	if ( file_exists( $state_path ) ) {
		throw new RuntimeException( 'State exists; inspect or clean up that fixture first.' );
	}
	$state = array( 'home' => home_url(), 'posts' => array(), 'terms' => array(), 'attachments' => array() );
	mrn_reference_save_state( $state_path, $state );
	foreach ( array( 'partner', 'customer', 'unrelated' ) as $term ) {
		$slug = 'mrn-reference-qa-' . $term;
		mrn_reference_assert( ! term_exists( $slug, 'category' ), 'Fixture term is unambiguous: ' . $slug );
		$result = wp_insert_term( 'MRN Reference QA ' . $term, 'category', array( 'slug' => $slug ) );
		if ( is_wp_error( $result ) ) {
			throw new RuntimeException( $result->get_error_message() );
		}
		$state['terms'][ $term ] = $result['term_id'];
		mrn_reference_save_state( $state_path, $state );
	}
	foreach ( array( 'match', 'both', 'nonmatch', 'missing', 'public', 'private', 'page' ) as $name ) {
		$type = 'public' === $name ? 'post' : ( 'private' === $name ? 'location' : ( 'page' === $name ? 'page' : 'resource' ) );
		$id = wp_insert_post( array( 'post_type' => $type, 'post_status' => 'publish', 'post_title' => 'MRN Reference QA ' . $name, 'post_name' => 'mrn-reference-qa-' . $name ), true );
		if ( is_wp_error( $id ) ) {
			throw new RuntimeException( $id->get_error_message() );
		}
		update_post_meta( $id, '_mrn_reference_qa_fixture', 'reference-content' );
		$state['posts'][ $name ] = $id;
		$terms = 'nonmatch' === $name ? array( $state['terms']['unrelated'] ) : array( $state['terms']['partner'] );
		if ( 'both' === $name ) {
			$terms[] = $state['terms']['customer'];
		}
		wp_set_object_terms( $id, $terms, 'category' );
		mrn_reference_save_state( $state_path, $state );
	}
	foreach ( array( 'pdf' => "%PDF-1.4\n1 0 obj <</Type /Catalog /Pages 2 0 R>> endobj\n2 0 obj <</Type /Pages /Count 0 /Kids []>> endobj\ntrailer <</Root 1 0 R>>\n%%EOF\n", 'txt' => "MRN Reference Content local test file.\n" ) as $ext => $contents ) {
		$upload = wp_upload_bits( 'mrn-reference-qa.' . $ext, null, $contents );
		mrn_reference_assert( empty( $upload['error'] ), 'Fixture upload created: ' . $ext );
		$id = wp_insert_attachment( array( 'post_title' => 'MRN Reference QA ' . $ext, 'post_mime_type' => 'pdf' === $ext ? 'application/pdf' : 'text/plain', 'post_status' => 'inherit' ), $upload['file'], 0, true );
		if ( is_wp_error( $id ) ) {
			throw new RuntimeException( $id->get_error_message() );
		}
		update_post_meta( $id, '_mrn_reference_qa_fixture', 'reference-content' );
		$state['attachments'][ $ext ] = $id;
		mrn_reference_save_state( $state_path, $state );
	}
	update_field( 'field_mrn_resource_file', $state['attachments']['pdf'], $state['posts']['match'] );
	update_field( 'field_mrn_resource_file', $state['attachments']['txt'], $state['posts']['both'] );
	$row = array(
		'acf_fc_layout' => 'content_lists', 'internal_name' => 'Reference QA Resources', 'heading' => 'Reference QA Resources',
		'list_post_type' => 'resource', 'filter_source' => 'manual_terms', 'filter_taxonomy' => 'category',
		'filter_term_slugs' => 'mrn-reference-qa-partner,mrn-reference-qa-customer', 'filter_match' => 'any',
		'link_items' => 1, 'posts_per_page' => 20, 'show_read_more' => 1, 'read_more_label' => 'Read More',
		'heading_tag' => 'h2', 'layout_class' => 'mrn-reference-qa-resources',
	);
	$public = array_replace( $row, array( 'list_post_type' => 'post', 'heading' => 'Reference QA Posts', 'internal_name' => 'Reference QA Posts', 'layout_class' => 'mrn-reference-qa-posts' ) );
	$private = array_replace( $row, array( 'list_post_type' => 'location', 'heading' => 'Reference QA Content Only', 'internal_name' => 'Reference QA Content Only', 'layout_class' => 'mrn-reference-qa-private', 'filter_source' => 'manual_posts', 'filter_posts' => array( $state['posts']['private'] ) ) );
	$nested = array_replace( $row, array( 'heading' => 'Reference QA Nested', 'internal_name' => 'Reference QA Nested', 'layout_class' => 'mrn-reference-qa-nested', 'link_items' => 0 ) );
	$tabs = array( 'acf_fc_layout' => 'tabbed_layout', 'heading' => 'Reference QA Tabs', 'tabs' => array( array( 'tab_label' => 'Resources', 'panel_rows' => array( $nested ) ) ) );
	update_field( 'field_mrn_page_content_rows', array( $row, $public, $private, $tabs ), $state['posts']['page'] );
	// Seed existing cloned-row compatibility, using the real source field tree.
	// These contexts admit saved Reference Content rows but do not offer new ones by default.
	$reference_layout = null;
	foreach ( mrn_base_stack_get_content_builder_source_layouts() as $layout ) {
		if ( 'content_lists' === $layout['name'] ) {
			$reference_layout = $layout;
			break;
		}
	}
	if ( null === $reference_layout ) {
		throw new RuntimeException( 'The current Stack must register Reference Content.' );
	}
	$after_field = acf_get_field( 'field_mrn_page_after_content_rows' );
	$after_field['layouts'] = array( mrn_base_stack_clone_acf_keys_with_prefix( $reference_layout, 'after_content_' ) );
	acf_update_value( array( array_replace( $row, array( 'heading' => 'Reference QA After Content', 'internal_name' => 'Reference QA After Content', 'layout_class' => 'mrn-reference-qa-after', 'link_items' => 0 ) ) ), $state['posts']['page'], $after_field );
	$panel_field = acf_get_field( 'field_mrn_tabbed_layout_panel_rows' );
	$panel_field['name'] = 'page_content_rows_3_tabs_0_panel_rows';
	$panel_field['layouts'] = array( mrn_base_stack_clone_acf_keys_with_prefix( $reference_layout, 'field_mrn_tabbed_panel_' ) );
	acf_update_value( array( $nested ), $state['posts']['page'], $panel_field );
	update_post_meta( $state['posts']['page'], 'page_content_rows_3_tabs_0_panel_rows_0_acf_fc_layout', 'content_lists' );
	$state['url'] = get_permalink( $state['posts']['page'] );
	$state['edit_url'] = admin_url( 'post.php?post=' . $state['posts']['page'] . '&action=edit' );
	mrn_reference_save_state( $state_path, $state );
	echo 'Created local fixture: ' . $state['url'] . PHP_EOL;
	return;
}
$state = json_decode( file_get_contents( $state_path ), true );
mrn_reference_assert( $state['home'] === home_url(), 'Fixture belongs to this local runtime' );
if ( 'cleanup' === $action ) {
	foreach ( array_merge( array_values( $state['posts'] ), array_values( $state['attachments'] ) ) as $id ) {
		if ( ! get_post( $id ) ) {
			continue;
		}
		mrn_reference_assert( 'reference-content' === get_post_meta( $id, '_mrn_reference_qa_fixture', true ), 'Delete only fixture-owned post ' . $id );
		if ( 'attachment' === get_post_type( $id ) ) {
			wp_delete_attachment( $id, true );
		} else {
			wp_delete_post( $id, true );
		}
	}
	foreach ( $state['terms'] as $name => $id ) {
		$term = get_term( $id, 'category' );
		if ( $term && ! is_wp_error( $term ) ) {
			mrn_reference_assert( 'mrn-reference-qa-' . $name === $term->slug, 'Delete only fixture-owned term ' . $id );
			wp_delete_term( $id, 'category' );
		}
	}
	unlink( $state_path );
	echo 'Removed local fixture records and media.' . PHP_EOL;
	return;
}
mrn_reference_assert( 'check' === $action, 'Recognized fixture action' );
$map = mrn_base_stack_get_content_list_post_type_taxonomy_map();
mrn_reference_assert( isset( $map['resource']['category'], $map['resource']['post_tag'], $map['post']['category'] ), 'Resources and public posts expose registered Categories and Tags' );
foreach ( $map as $source => $choices ) {
	foreach ( array_keys( $choices ) as $taxonomy ) {
		mrn_reference_assert( is_object_in_taxonomy( $source, $taxonomy ) && ! in_array( $taxonomy, array( 'nav_menu', 'post_format', 'link_category' ), true ), $source . ' taxonomy is eligible: ' . $taxonomy );
	}
}
register_post_type( 'mrn_qa_no_tax', array( 'public' => true, 'show_ui' => true ) );
register_taxonomy( 'mrn_qa_hidden', 'resource', array( 'public' => true, 'show_ui' => false ) );
register_taxonomy( 'mrn_qa_private', 'resource', array( 'public' => false, 'show_ui' => true ) );
$map = mrn_base_stack_get_content_list_post_type_taxonomy_map();
mrn_reference_assert( array() === $map['mrn_qa_no_tax'] && ! isset( $map['resource']['mrn_qa_hidden'] ) && ! isset( $map['resource']['mrn_qa_private'] ), 'No-taxonomy and hidden-taxonomy sources follow editor visibility rules' );
$support = mrn_base_stack_get_content_list_link_support_map();
mrn_reference_assert( $support['resource'] && $support['post'] && ! $support['location'], 'Editor and renderer share Resource/public/Content Only destination eligibility' );
$resource = get_post_type_object( 'resource' );
mrn_reference_assert( ! $resource->public && ! $resource->publicly_queryable && ! $resource->has_archive && ! $resource->rewrite, 'Resources retain Content Only registration and no public profiles/archives' );
$rows = get_field( 'page_content_rows', $state['posts']['page'] );
$row = $rows[0];
mrn_reference_assert( 'category' === $row['filter_taxonomy'] && 'mrn-reference-qa-partner,mrn-reference-qa-customer' === $row['filter_term_slugs'] && 'any' === $row['filter_match'], 'Saved field names, term slugs and matching mode survive ACF reload' );
foreach ( array( 'page_after_content_rows_0' => 'after_content_', 'page_content_rows_3_tabs_0_panel_rows_0' => 'field_mrn_tabbed_panel_' ) as $meta_prefix => $key_prefix ) {
	mrn_reference_assert( $key_prefix . 'field_mrn_content_lists_link_items' === get_post_meta( $state['posts']['page'], '_' . $meta_prefix . '_link_items', true ), 'Cloned link field key remains stable: ' . $key_prefix );
	mrn_reference_assert( '0' === get_post_meta( $state['posts']['page'], $meta_prefix . '_link_items', true ) && 'category' === get_post_meta( $state['posts']['page'], $meta_prefix . '_filter_taxonomy', true ), 'Cloned saved link-off and taxonomy survive reload: ' . $key_prefix );
}
foreach ( array( 'any' => array( 'match', 'both', 'missing' ), 'all' => array( 'both' ) ) as $mode => $names ) {
	$query_row = array_replace( $row, array( 'filter_match' => $mode ) );
	$query = new WP_Query( array( 'post_type' => 'resource', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids', 'tax_query' => mrn_base_stack_get_content_list_tax_query( $query_row, $state['posts']['page'], 'resource' ) ) );
	$expected = array_map( static function( $name ) use ( $state ) { return $state['posts'][ $name ]; }, $names );
	sort( $expected );
	$actual = $query->posts;
	sort( $actual );
	mrn_reference_assert( $actual === $expected, 'Actual filtered WP_Query matches only expected resources for ' . $mode );
}
$item = get_post( $state['posts']['match'] );
$file_url = wp_get_attachment_url( $state['attachments']['pdf'] );
mrn_reference_assert( $file_url === mrn_base_stack_get_content_list_item_permalink( $item, array( 'link_items' => 1 ) ), 'Enabled Resource uses existing PDF destination' );
foreach ( array( false, 0, '0' ) as $off ) {
	mrn_reference_assert( '' === mrn_base_stack_get_content_list_item_permalink( $item, array( 'link_items' => $off ) ), 'Explicit false/zero link preference produces no link' );
}
mrn_reference_assert( '' === mrn_base_stack_get_content_list_item_permalink( get_post( $state['posts']['missing'] ), array( 'link_items' => 1 ) ), 'Missing Resource file produces no profile fallback' );
mrn_reference_assert( '' === mrn_base_stack_filter_resource_content_list_permalink( '/resource/broken/', get_post( $state['posts']['missing'] ) ), 'Resource provider never returns a missing-file profile fallback' );
mrn_reference_assert( '' === mrn_base_stack_get_content_list_item_permalink( get_post( $state['posts']['private'] ) ), 'Content Only location remains unlinked' );
mrn_reference_assert( get_permalink( $state['posts']['public'] ) === mrn_base_stack_get_content_list_item_permalink( get_post( $state['posts']['public'] ) ), 'Public post retains its permalink' );
$team_type = get_post_type_object( 'team_member' );
$was_queryable = $team_type->publicly_queryable;
$team_type->publicly_queryable = true;
$team = clone $item;
$team->post_type = 'team_member';
try {
	update_post_meta( $team->ID, 'team_member_public_profile', '0' );
	mrn_reference_assert( '' === mrn_base_stack_get_content_list_item_permalink( $team ), 'Per-item disabled Team Member profile stays unlinked even for a public source' );
	delete_post_meta( $team->ID, 'team_member_public_profile' );
} finally {
	$team_type->publicly_queryable = $was_queryable;
}
$attributes = mrn_base_stack_get_content_list_item_link_attributes( $item );
mrn_reference_assert( str_contains( $attributes, 'target="_blank"' ) && str_contains( $attributes, 'noopener' ), 'PDF new-tab attributes are preserved' );
mrn_reference_assert( ! str_contains( mrn_base_stack_get_content_list_item_link_attributes( get_post( $state['posts']['both'] ) ), 'target="_blank"' ), 'Non-PDF Resource retains existing same-tab behavior' );
foreach ( array( 1, 0 ) as $enabled ) {
	$render_row = array_replace( $row, array( 'link_items' => $enabled ) );
	ob_start();
	get_template_part( 'template-parts/builder/content-lists', null, array( 'row' => $render_row, 'post_id' => $state['posts']['page'], 'index' => 0 ) );
	$html = ob_get_clean();
	mrn_reference_assert( str_contains( $html, 'MRN Reference QA match' ) && ! str_contains( $html, 'MRN Reference QA nonmatch' ), 'Real row renderer excludes nonmatching content' );
	mrn_reference_assert( (bool) $enabled === str_contains( $html, 'href="' . esc_url( $file_url ) . '"' ), 'Real row renderer respects links ' . ( $enabled ? 'on' : 'off' ) );
}
echo 'Reference Content runtime regression checks passed.' . PHP_EOL;
