<?php
/** Local-only real-content fixture and readback for the W0–W4 pilot. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || 'admin-editor-pilot.localhost' !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
	throw new RuntimeException( 'Only the authorized admin-editor-pilot Local Hub fixture is supported.' );
}
$mode = $args[0] ?? 'readback';
$ids = get_option( 'mrn_section_pilot_fixture', array() );
if ( 'setup' === $mode && empty( $ids ) ) {
	$source = get_post( 1189 );
	if ( ! $source || 'Design Layouts Page' !== $source->post_title ) { throw new RuntimeException( 'Expected real Trilliant source page is missing.' ); }
	foreach ( array( 'real' => 'Local pilot — Design Layouts Page', 'stress' => 'Derived stress fixture — 30+ sections' ) as $kind => $title ) {
		$id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => $title, 'post_content' => $source->post_content ), true );
		if ( is_wp_error( $id ) ) { throw new RuntimeException( esc_html( $id->get_error_message() ) ); }
		foreach ( get_post_meta( $source->ID ) as $key => $values ) {
			if ( in_array( $key, array( '_edit_lock', '_edit_last' ), true ) ) { continue; }
			foreach ( $values as $value ) { add_post_meta( $id, $key, maybe_unserialize( $value ) ); }
		}
		$ids[ $kind ] = $id;
	}
	update_option( 'mrn_section_pilot_fixture', $ids, false );
}
if ( ! $ids ) { throw new RuntimeException( 'Run setup first.' ); }
if ( 'reset' === $mode ) {
	update_field( 'page_content_rows', get_field( 'page_content_rows', 1189, false ), $ids['real'] );
}
if ( 'stress' === $mode ) {
	$value = get_field( 'page_content_rows', $ids['real'], false );
	if ( ! is_array( $value ) || 19 !== count( $value ) ) { throw new RuntimeException( 'Unexpected source structure.' ); }
	update_field( 'page_content_rows', array_merge( $value, array_slice( $value, 0, 13 ) ), $ids['stress'] );
}
// Recovery for an interrupted browser test: prove the exact restored snapshot before writing.
if ( 'restore-workspace-test' === $mode ) {
	$value  = get_field( 'page_content_rows', $ids['real'], false );
	$source = get_field( 'page_content_rows', 1189, false );
	$name   = 'field_mrn_body_text_content_tab_internal_name';
	$body   = 'field_mrn_body_text_content';
	$faq    = 'field_mrn_page_faq_fields_field_mrn_faq_items';
	$question = 'field_mrn_faq_item_question';
	if ( 2523 !== (int) $ids['real'] || 'Workspace verified section' !== ( $value[5][$name] ?? '' )
		|| 'Workspace verified nested question' !== ( $value[8][$faq][0][$question] ?? '' ) ) {
		throw new RuntimeException( 'Expected local test markers are missing; refusing recovery write.' );
	}
	$value[5][$name] = $source[5][$name];
	$value[5][$body] = $source[5][$body];
	$value[8][$faq][0][$question] = $source[8][$faq][0][$question];
	if ( '125c3120813340710c01d3d6602341cb3381d319d0080c23f09ea04bf3c36708' !== hash( 'sha256', wp_json_encode( $value ) ) ) {
		throw new RuntimeException( 'Restored snapshot differs from the recorded baseline; refusing recovery write.' );
	}
	update_field( 'page_content_rows', $value, $ids['real'] );
}
$report = array( 'source' => array( 'id' => 1189, 'title' => 'Design Layouts Page', 'local_snapshot' => 'trilliant' ), 'ids' => $ids, 'pages' => array(), 'versions' => array( 'wp' => get_bloginfo( 'version' ), 'acf' => ACF_VERSION ) );
if ( 'compare' === $mode ) {
	$differences = array();
	$compare = function ( $before, $after, $path = '' ) use ( &$compare, &$differences ) {
		if ( is_array( $before ) && is_array( $after ) ) {
			foreach ( array_unique( array_merge( array_keys( $before ), array_keys( $after ) ) ) as $key ) {
				$compare( $before[ $key ] ?? null, $after[ $key ] ?? null, $path . '/' . $key );
			}
		} elseif ( $before !== $after ) {
			$differences[] = array( 'path' => $path, 'before_hash' => hash( 'sha256', wp_json_encode( $before ) ), 'after_hash' => hash( 'sha256', wp_json_encode( $after ) ) );
		}
	};
	$compare( get_field( 'page_content_rows', 1189, false ), get_field( 'page_content_rows', $ids['real'], false ) );
	$report['differences'] = $differences;
}
foreach ( $ids as $kind => $id ) {
	$value = get_field( 'page_content_rows', $id, false );
	$report['pages'][ $kind ] = array( 'id' => $id, 'rows' => is_array( $value ) ? count( $value ) : 0, 'content_hash' => hash( 'sha256', wp_json_encode( $value ) ), 'title' => get_the_title( $id ), 'status' => get_post_status( $id ) );
	$report['pages'][ $kind ]['native_acf_hash'] = hash( 'sha256', wp_json_encode( get_fields( $id, false ) ) );
	$report['pages'][ $kind ]['post_content_hash'] = hash( 'sha256', get_post_field( 'post_content', $id ) );
	$report['pages'][ $kind ]['revisions'] = count( wp_get_post_revisions( $id ) );
}
if ( 'auth' === $mode || 'setup' === $mode ) {
	foreach ( array( 'administrator', 'editor', 'subscriber' ) as $role ) {
		$users = get_users( array( 'role' => $role, 'number' => 1 ) );
		if ( ! $users ) {
			$user_id = wp_create_user( 'mrn-pilot-' . $role, wp_generate_password( 32, true, true ), 'pilot-' . $role . '@example.invalid' );
			$user = new WP_User( $user_id ); $user->set_role( $role );
		} else { $user = $users[0]; }
		$expires = time() + DAY_IN_SECONDS;
		$token = WP_Session_Tokens::get_instance( $user->ID )->create( $expires );
		$cookies = array();
		foreach ( array( LOGGED_IN_COOKIE => 'logged_in', SECURE_AUTH_COOKIE => 'secure_auth' ) as $name => $scheme ) {
			$cookies[] = array( 'name' => $name, 'value' => wp_generate_auth_cookie( $user->ID, $expires, $scheme, $token ), 'domain' => 'admin-editor-pilot.localhost', 'path' => '/', 'secure' => true, 'httpOnly' => true, 'sameSite' => 'Lax' );
		}
		$file = dirname( ABSPATH ) . '/logs/' . $role . '-auth.json';
		file_put_contents( $file, wp_json_encode( $cookies ) ); chmod( $file, 0600 );
	}
}
WP_CLI::line( wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
