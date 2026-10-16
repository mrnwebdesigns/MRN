<?php
/**
 * Native WordPress/SEOPress regression, only in the disposable loopback fixture.
 * Run with wp eval-file and one action: seed, enabled, menu_hidden, content_only,
 * disabled, reenabled, assert. The fixture registers its shared taxonomies.
 *
 * @package MRN_Admin_Data_Post_Types
 */

if ( ! defined( 'MRN_DISABLED_TYPES_FIXTURE' ) || ! MRN_DISABLED_TYPES_FIXTURE || 'local' !== wp_get_environment_type() || '127.0.0.1' !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
	throw new RuntimeException( 'Requires the isolated local disabled-types fixture.' );
}

/**
 * Assert a native contract.
 *
 * @param bool   $condition Expected true.
 * @param string $message   Failure detail.
 * @return void
 * @throws RuntimeException When a fixture contract fails.
 */
function mrn_disabled_fixture_check( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( esc_html( $message ) );
	}
}

( static function ( $args ) {
	$action = isset( $args[0] ) ? $args[0] : 'assert';
	$types  = array( 'gallery', 'case_study' );
	if ( 'seed' === $action ) {
		$posts = array();
		$urls  = array();
		foreach ( array_merge( $types, array( 'team_member' ) ) as $type ) {
			$id = wp_insert_post(
				array(
					'post_type'    => $type,
					'post_status'  => 'publish',
					'post_title'   => 'Fixture ' . $type,
					'post_content' => 'Retained fixture content.',
					'post_name'    => 'fixture-' . $type,
				),
				true
			);
			mrn_disabled_fixture_check( ! is_wp_error( $id ), 'Fixture insert failed.' );
			update_post_meta( $id, '_seopress_titles_desc', 'Preserved description ' . $type );
			$posts[ $type ] = $id;
			$urls[ $type ]  = array(
				'single'  => get_permalink( $id ),
				'archive' => get_post_type_archive_link( $type ),
			);
		}
		foreach ( array( 'gallery_category', 'gallery_tag', 'fixture_shared', 'fixture_media', 'category', 'post_tag' ) as $taxonomy ) {
			$term = wp_insert_term( 'Fixture term', $taxonomy, array( 'slug' => 'fixture-term' ) );
			mrn_disabled_fixture_check( ! is_wp_error( $term ), 'Fixture term failed: ' . $taxonomy );
			wp_set_object_terms( $posts['gallery'], array( $term['term_id'] ), $taxonomy );
			$urls[ $taxonomy ] = get_term_link( (int) $term['term_id'], $taxonomy );
		}
		update_option(
			'seopress_titles_option_name',
			array(
				'seopress_titles_single_titles' => array(
					'gallery' => array(
						'title'       => 'saved title',
						'description' => 'saved description',
					),
				),
				'seopress_titles_tax_titles'    => array( 'gallery_category' => array( 'title' => 'saved taxonomy title' ) ),
			)
		);
		$includes = array_fill_keys( array_merge( $types, array( 'team_member', 'page' ) ), array( 'include' => '1' ) );
		update_option(
			'seopress_xml_sitemap_option_name',
			array(
				'seopress_xml_sitemap_general_enable'  => '1',
				'seopress_xml_sitemap_post_types_list' => $includes,
				'seopress_xml_sitemap_taxonomies_list' => array_fill_keys( array( 'gallery_category', 'gallery_tag', 'fixture_shared', 'fixture_media', 'category', 'post_tag' ), array( 'include' => '1' ) ),
			)
		);
		$toggle                       = get_option( 'seopress_toggle', array() );
		$toggle['toggle-xml-sitemap'] = '1';
		$toggle['toggle-news']        = '1';
		update_option( 'seopress_toggle', $toggle );
		$pro                                       = get_option( 'seopress_pro_option_name', array() );
		$pro['seopress_news_enable']               = '1';
		$pro['seopress_news_name']                 = 'Fixture publication';
		$pro['seopress_news_name_post_types_list'] = $includes;
		update_option( 'seopress_pro_option_name', $pro );
		update_option( 'mrn_disabled_fixture_posts', $posts );
		update_option( 'mrn_disabled_fixture_urls', $urls );
		update_option( 'mrn_disabled_fixture_titles', get_option( 'seopress_titles_option_name' ) );
		update_option( 'mrn_disabled_fixture_sitemaps', get_option( 'seopress_xml_sitemap_option_name' ) );
		echo wp_json_encode(
			array(
				'seeded' => true,
				'urls'   => $urls,
			)
		);
		return;
	}
	if ( 'legacy-preservation' === $action ) {
		update_option( MRN_ADMIN_DATA_POST_TYPES_DISABLED_OPTION, array() );
		$settings                                     = get_option( 'mrn_helper_settings', array() );
		$settings['disabled_admin_cpts']              = $types;
		$settings['content_only_post_types']          = $types;
		$settings['content_only_search_destinations'] = array( 'gallery' => get_option( 'mrn_disabled_fixture_posts' )['team_member'] );
		update_option( 'mrn_helper_settings', $settings );
		update_option( MRN_ADMIN_DATA_POST_TYPES_OPTION, $types );
		update_option( MRN_ADMIN_DATA_POST_TYPES_DISABLED_OPTION, $types );
		$input                                     = $settings;
		$input['disabled_admin_cpts']              = array();
		$input['content_only_post_types']          = array();
		$input['content_only_search_destinations'] = array();
		update_option( 'mrn_helper_settings', $input );
		update_option( MRN_ADMIN_DATA_POST_TYPES_OPTION, array() );
		$after = get_option( 'mrn_helper_settings' );
		mrn_disabled_fixture_check( $settings === $after, 'Omitted legacy preferences were lost.' );
		$manual = get_option( MRN_ADMIN_DATA_POST_TYPES_OPTION );
		mrn_disabled_fixture_check( $types === $manual, 'Content Only synchronization lost prior selection.' );
		update_option( MRN_ADMIN_DATA_POST_TYPES_DISABLED_OPTION, array() );
		mrn_disabled_fixture_check( 'content_only' === mrn_admin_data_post_types_get_state( 'gallery' ), 'Re-enabling failed to restore Content Only.' );
		echo wp_json_encode( array( 'legacy_preferences_preserved' => true ) );
		return;
	}

	if ( in_array( $action, array( 'enabled', 'menu_hidden', 'content_only', 'disabled', 'reenabled' ), true ) ) {
		$settings                        = get_option( 'mrn_helper_settings', array() );
		$settings['disabled_admin_cpts'] = 'enabled' === $action ? array() : $types;
		update_option( 'mrn_helper_settings', $settings );
		update_option( MRN_ADMIN_DATA_POST_TYPES_OPTION, 'content_only' === $action ? $types : array() );
		update_option( MRN_ADMIN_DATA_POST_TYPES_DISABLED_OPTION, 'disabled' === $action ? $types : array() );
		update_option( 'mrn_disabled_fixture_state', $action );
		echo wp_json_encode(
			array(
				'state'           => $action,
				'rewrite_pending' => (bool) get_option( 'mrn_disabled_post_types_rewrite_pending' ),
			)
		);
		return;
	}

	$state    = get_option( 'mrn_disabled_fixture_state', 'enabled' );
	$excluded = in_array( $state, array( 'disabled', 'content_only' ), true );
	$disabled = 'disabled' === $state;
	if ( $disabled ) {
		$before = mrn_admin_data_post_types_get_disabled_selection();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Preserve and restore synthetic POST while testing rejected nonces in the isolated fixture.
		$previous_post = $_POST;
		$user          = get_current_user_id();
		wp_set_current_user( 1 );
		$_POST = array(
			'mrn_disabled_post_types_present' => '1',
			'mrn_disabled_post_types_nonce'   => 'invalid',
		);
		mrn_admin_data_post_types_save_disabled_settings();
		$after = mrn_admin_data_post_types_get_disabled_selection();
		mrn_disabled_fixture_check( $before === $after, 'Invalid nonce changed selection.' );
		$_POST['mrn_disabled_post_types_nonce'] = wp_create_nonce( 'mrn_disabled_post_types_save' );
		wp_set_current_user( 0 );
		mrn_admin_data_post_types_save_disabled_settings();
		$after = mrn_admin_data_post_types_get_disabled_selection();
		mrn_disabled_fixture_check( $before === $after, 'Unprivileged save changed selection.' );
		wp_set_current_user( 1 );
		$_POST = array();
		mrn_admin_data_post_types_save_disabled_settings();
		$after = mrn_admin_data_post_types_get_disabled_selection();
		mrn_disabled_fixture_check( $before === $after, 'Absent form reset selection.' );
		$_POST = array(
			'mrn_disabled_post_types_present' => '1',
			'mrn_disabled_post_types_nonce'   => wp_create_nonce( 'mrn_disabled_post_types_save' ),
			'mrn_disabled_post_types'         => array( 'gallery', 'case_study', 'post', 'page', 'attachment', 'wp_block', 'any', 'unregistered', array( 'invalid' ) ),
		);
		mrn_admin_data_post_types_save_disabled_settings();
		$after = mrn_admin_data_post_types_get_disabled_selection();
		mrn_disabled_fixture_check( $before === $after, 'Selection sanitization changed allowed intent.' );
		$_POST = $previous_post;
		wp_set_current_user( $user );
	}
	$posts    = get_option( 'mrn_disabled_fixture_posts' );
	$checks   = array();
	$settings = get_option( 'mrn_helper_settings', array() );
	if ( $disabled ) {
		mrn_disabled_fixture_check( array() === array_diff( $types, $settings['disabled_admin_cpts'] ), 'Native save lost menu-hidden preferences.' );
	}
	foreach ( $types as $type ) {
		$object       = get_post_type_object( $type );
		$expect       = 'reenabled' === $state ? 'menu_hidden' : $state;
		$actual_state = mrn_admin_data_post_types_get_state( $type );
		mrn_disabled_fixture_check( $expect === $actual_state, 'Canonical state mismatch: ' . $type );
		$expected_public = ! $excluded;
		mrn_disabled_fixture_check( $expected_public === $object->public && $expected_public === $object->publicly_queryable, 'Public flags mismatch: ' . $type );
		if ( $disabled ) {
			mrn_disabled_fixture_check( ! $object->show_ui && ! $object->show_in_rest && ! $object->rewrite && ! $object->has_archive, 'Disabled registration mismatch.' );
		}
		mrn_disabled_fixture_check( 'Retained fixture content.' === get_post( $posts[ $type ] )->post_content, 'Post content lost.' );
		$expected_description = 'Preserved description ' . $type;
		$actual_description   = get_post_meta( $posts[ $type ], '_seopress_titles_desc', true );
		mrn_disabled_fixture_check( $expected_description === $actual_description, 'Post metadata lost.' );
		mrn_disabled_fixture_check(
			1 === count(
				get_posts(
					array(
						'post_type' => $type,
						'include'   => array( $posts[ $type ] ),
					)
				)
			),
			'Programmatic data access lost.'
		);
	}
	mrn_disabled_fixture_check( 'enabled' === mrn_admin_data_post_types_get_state( 'team_member' ) && get_post_type_object( 'team_member' )->public, 'Team Members changed.' );
	$taxonomies = mrn_admin_data_post_types_get_excluded_taxonomies();
	foreach ( array( 'gallery_category', 'gallery_tag' ) as $tax ) {
		mrn_disabled_fixture_check( in_array( $tax, $taxonomies, true ) === $disabled, 'Exclusive taxonomy mismatch: ' . $tax );
		mrn_disabled_fixture_check( count( wp_get_object_terms( $posts['gallery'], $tax ) ) === 1, 'Terms/relationships lost.' );
	}
	foreach ( array( 'fixture_shared', 'fixture_media', 'category', 'post_tag' ) as $tax ) {
		mrn_disabled_fixture_check( ! in_array( $tax, $taxonomies, true ), 'Shared taxonomy removed: ' . $tax );
	}
	$core = apply_filters( 'wp_sitemaps_post_types', get_post_types( array( 'public' => true ), 'objects' ) );
	mrn_disabled_fixture_check( isset( $core['gallery'] ) === ! $excluded && isset( $core['team_member'] ), 'Core sitemap registry mismatch.' );

	if ( function_exists( 'seopress_get_service' ) ) {
		$data         = seopress_get_service( 'WordPressData' );
		$html_service = new \SEOPress\Services\HTMLSitemap\HTMLSitemapService( seopress_get_service( 'SitemapOption' ) );
		$html         = $html_service->renderSitemap( array( 'cpt' => 'gallery,case_study,team_member' ) );
		mrn_disabled_fixture_check( ( false !== strpos( $html, 'fixture-gallery' ) ) === ! $excluded && false !== strpos( $html, 'fixture-team_member' ), 'Native HTML sitemap override leaked excluded type.' );
		foreach ( array( 'title', 'description' ) as $metadata ) {
			$warnings = (string) seopress_get_empty_templates( 'cpt', $metadata, false );
			mrn_disabled_fixture_check( ( false !== strpos( $warnings, '<li>case_study</li>' ) ) === ! $excluded, 'Native missing-default CPT warning mismatch.' );
			$warnings = (string) seopress_get_empty_templates( 'tax', $metadata, false );
			mrn_disabled_fixture_check( ( false !== strpos( $warnings, '<li>gallery_tag</li>' ) ) === ! $disabled, 'Native missing-default taxonomy warning mismatch.' );
		}
		foreach ( array( false, true ) as $all ) {
			$list = $data->getPostTypes( $all );
			mrn_disabled_fixture_check( isset( $list['gallery'] ) === ! $excluded && isset( $list['case_study'] ) === ! $excluded && isset( $list['team_member'] ), 'SEOPress type discovery mismatch.' );
			$list = $data->getTaxonomies( false, $all );
			mrn_disabled_fixture_check( isset( $list['gallery_category'] ) === ! $disabled && isset( $list['fixture_media'] ), 'SEOPress taxonomy discovery mismatch.' );
		}
		$map = seopress_get_service( 'SitemapOption' )->getPostTypesList();
		mrn_disabled_fixture_check( isset( $map['gallery'] ) === ! $excluded && isset( $map['team_member'] ), 'Sitemap saved include mask mismatch.' );
		foreach ( array( 'seopress_site_audit_query', 'seopress_bot_query', 'seopress_sitemaps_single_gnews_query', 'seopress_sitemaps_video_query' ) as $hook ) {
			$query = apply_filters(
				$hook,
				array(
					'post_type'   => array( 'gallery', 'case_study', 'team_member' ),
					'post_status' => 'publish',
				)
			);
			mrn_disabled_fixture_check( in_array( 'gallery', (array) $query['post_type'], true ) === ! $excluded && in_array( 'team_member', (array) $query['post_type'], true ), 'Audit discovery mismatch.' );
			$query = apply_filters(
				$hook,
				array(
					'post_type'   => $types,
					'post_status' => 'publish',
				)
			);
			if ( $excluded ) {
				mrn_disabled_fixture_check( array( 0 ) === $query['post__in'] && array() === get_posts( $query ), 'Empty audit selection broadened.' );
			}
		}
		if ( $excluded ) {
			$pro   = get_option( 'seopress_pro_option_name' );
			$input = $pro;
			unset( $input['seopress_news_name_post_types_list']['gallery'] );
			update_option( 'seopress_pro_option_name', $input );
			$after = get_option( 'seopress_pro_option_name' );
			ksort( $pro['seopress_news_name_post_types_list'] );
			ksort( $after['seopress_news_name_post_types_list'] );
			mrn_disabled_fixture_check( $pro === $after, 'Hidden News sitemap selection lost on save.' );
			$titles = get_option( 'seopress_titles_option_name' );
			unset( $titles['seopress_titles_single_titles']['gallery'] );
			if ( $disabled ) {
				unset( $titles['seopress_titles_tax_titles']['gallery_category'] );
			}
			update_option( 'seopress_titles_option_name', $titles );
			mrn_disabled_fixture_check( get_option( 'mrn_disabled_fixture_titles' ) === get_option( 'seopress_titles_option_name' ), 'Hidden title settings lost on save.' );
			$sitemaps = get_option( 'seopress_xml_sitemap_option_name' );
			update_option( 'seopress_xml_sitemap_option_name', $sitemaps );
		}
		if ( ! $excluded ) {
			// phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual -- Restored associative include-map order is immaterial; every key and value is compared.
			mrn_disabled_fixture_check( get_option( 'mrn_disabled_fixture_sitemaps' ) == get_option( 'seopress_xml_sitemap_option_name' ), 'Sitemap settings not restored.' );
		}
		$checks[] = 'native SEOPress registries, sitemap reads, audit guards, settings preservation';
	}

	// A later enabled association must immediately preserve a formerly exclusive taxonomy.
	if ( $disabled ) {
		register_taxonomy_for_object_type( 'gallery_category', 'team_member' );
		mrn_disabled_fixture_check( ! in_array( 'gallery_category', mrn_admin_data_post_types_get_excluded_taxonomies(), true ), 'Late shared association was cached.' );
	}
	$checks[] = 'canonical states, public flags, shared and attachment taxonomies, content and relationships';
	echo wp_json_encode(
		array(
			'state'    => $state,
			'checks'   => $checks,
			'seopress' => function_exists( 'seopress_get_service' ),
			'urls'     => get_option( 'mrn_disabled_fixture_urls' ),
		)
	);
} )( isset( $args ) ? $args : array() );
