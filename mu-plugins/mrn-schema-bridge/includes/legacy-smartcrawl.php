<?php
/**
 * Legacy SmartCrawl compatibility for sites awaiting SEOPress migration.
 *
 * @package MRN_Schema_Bridge
 */

defined( 'ABSPATH' ) || exit;

/**
 * Check whether legacy SmartCrawl mutations should run for the active provider.
 *
 * SmartCrawl remains available as a migration fallback, but it must not alter
 * provider state after SEOPress becomes authoritative.
 *
 * @return bool
 */
function mrn_schema_bridge_legacy_smartcrawl_compatibility_enabled() {
	$enabled = mrn_schema_bridge_smartcrawl_provider_loaded()
		&& 'smartcrawl' === mrn_schema_bridge_get_active_schema_provider();

	return (bool) apply_filters( 'mrn_schema_bridge_legacy_smartcrawl_compatibility_enabled', $enabled );
}

/**
 * Get the canonical business logo attachment ID.
 *
 * @return int
 */
function mrn_schema_bridge_get_business_logo_id() {
	$logo = function_exists( 'mrn_base_stack_get_business_logo' ) ? mrn_base_stack_get_business_logo( 'header' ) : 0;

	if ( is_array( $logo ) && ! empty( $logo['ID'] ) ) {
		return absint( $logo['ID'] );
	}

	return absint( $logo );
}

/**
 * Overlay canonical stack identity values on SmartCrawl social options.
 *
 * @param mixed $options SmartCrawl option value.
 * @return array<string,mixed>
 */
function mrn_schema_bridge_filter_smartcrawl_social_options( $options ) {
	if ( ! mrn_schema_bridge_legacy_smartcrawl_compatibility_enabled() ) {
		return $options;
	}

	$options  = is_array( $options ) ? $options : array();
	$logo_id  = mrn_schema_bridge_get_business_logo_id();
	$logo_url = $logo_id ? wp_get_attachment_image_url( $logo_id, 'full' ) : '';

	$options['disable-schema']    = false;
	$options['schema_type']       = 'Organization';
	$options['sitename']          = sanitize_text_field( (string) get_bloginfo( 'name' ) );
	$options['organization_name'] = sanitize_text_field( (string) get_bloginfo( 'name' ) );

	if ( is_string( $logo_url ) && '' !== $logo_url ) {
		$options['organization_logo'] = esc_url_raw( $logo_url );
	}

	return $options;
}
add_filter( 'option_wds_social_options', 'mrn_schema_bridge_filter_smartcrawl_social_options', 20 );

/**
 * Overlay canonical stack identity values on SmartCrawl schema options.
 *
 * @param mixed $options SmartCrawl option value.
 * @return array<string,mixed>
 */
function mrn_schema_bridge_filter_smartcrawl_schema_options( $options ) {
	if ( ! mrn_schema_bridge_legacy_smartcrawl_compatibility_enabled() ) {
		return $options;
	}

	$options  = is_array( $options ) ? $options : array();
	$identity = mrn_schema_bridge_get_canonical_organization_properties();
	$logo_id  = mrn_schema_bridge_get_business_logo_id();

	if ( $logo_id > 0 ) {
		$options['schema_website_logo'] = $logo_id;
	}

	if ( ! empty( $identity['description'] ) ) {
		$options['organization_description'] = sanitize_text_field( (string) $identity['description'] );
	}

	if ( ! empty( $identity['telephone'] ) ) {
		$options['organization_phone_number'] = sanitize_text_field( (string) $identity['telephone'] );
	}

	return $options;
}
add_filter( 'option_wds_schema_options', 'mrn_schema_bridge_filter_smartcrawl_schema_options', 20 );

/**
 * Apply non-destructive SmartCrawl defaults once per bridge release.
 *
 * Existing values are preserved. MRN SEO Helper remains the owner of public
 * post-type title and description templates.
 *
 * @return void
 */
function mrn_schema_bridge_apply_smartcrawl_defaults() {
	if ( ! mrn_schema_bridge_legacy_smartcrawl_compatibility_enabled() ) {
		return;
	}

	if ( MRN_SCHEMA_BRIDGE_VERSION === get_option( MRN_SCHEMA_BRIDGE_SMARTCRAWL_DEFAULTS_OPTION, '' ) ) {
		return;
	}

	$settings_defaults = array(
		'sitemap'                    => true,
		'onpage'                     => true,
		'social'                     => true,
		'instant_indexing'            => true,
		'analysis-seo'               => true,
		'analysis-readability'       => true,
		'general-suppress-generator' => true,
		'keep_settings_on_uninstall' => true,
		'keep_data_on_uninstall'     => true,
		'usage_tracking'             => false,
	);
	$schema_defaults = array(
		'schema_enable_post_type_archives' => true,
		'schema_enable_taxonomy_archives'  => true,
		'schema_archive_main_entity_type'  => 'ItemList',
		'schema_enable_test_button'        => true,
		'schema_enable_comments'           => false,
		'schema_enable_author_archives'    => false,
		'schema_enable_search'             => false,
		'schema_enable_date_archives'      => false,
		'schema_enable_audio'              => false,
		'schema_enable_video'              => false,
	);
	$social_defaults = array(
		'disable-schema'      => false,
		'schema_type'         => 'Organization',
		'sitename'            => sanitize_text_field( (string) get_bloginfo( 'name' ) ),
		'organization_name'   => sanitize_text_field( (string) get_bloginfo( 'name' ) ),
		'twitter-card-enable' => true,
		'og-enable'           => true,
	);
	$sitemap_defaults = array(
		'override-native'                       => true,
		'wds_sitemap-setup'                     => true,
		'sitemap-stylesheet'                    => true,
		'sitemap-disable-automatic-regeneration' => 'auto',
		'sitemap-update-frequency'              => 'weekly',
		'items-per-sitemap'                     => 2000,
		'enable-news-sitemap'                   => false,
	);

	$option_defaults = array(
		'wds_settings_options' => $settings_defaults,
		'wds_schema_options'   => $schema_defaults,
		'wds_social_options'   => $social_defaults,
		'wds_sitemap_options'  => $sitemap_defaults,
	);

	foreach ( $option_defaults as $option_name => $defaults ) {
		$current = get_option( $option_name, array() );
		$current = is_array( $current ) ? $current : array();
		update_option( $option_name, array_merge( $defaults, $current ), false );
	}

	update_option( MRN_SCHEMA_BRIDGE_SMARTCRAWL_DEFAULTS_OPTION, MRN_SCHEMA_BRIDGE_VERSION, false );
}
add_action( 'init', 'mrn_schema_bridge_apply_smartcrawl_defaults', 20 );
