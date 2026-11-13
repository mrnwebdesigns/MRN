<?php
/**
 * Versioned, opt-in presentation data for private Layout Library fixtures.
 *
 * A profile is deliberately presentation data, not client content. Sites may
 * register one with the `mrn_active_style_guide_layout_preview_profile` filter.
 * See the plugin README for the complete contract.
 *
 * @package mrn-active-style-guide
 */

defined( 'ABSPATH' ) || exit;

const MRN_ACTIVE_STYLE_GUIDE_LAYOUT_PREVIEW_PROFILE_VERSION = 1;
const MRN_ACTIVE_STYLE_GUIDE_LAYOUT_PREVIEW_ASSET_META = '_mrn_layout_library_preview_asset';

/** Return the safe profile used when a site has not opted into an override. */
function mrn_active_style_guide_get_layout_preview_profile(): array {
	$profile = array(
		'version' => MRN_ACTIVE_STYLE_GUIDE_LAYOUT_PREVIEW_PROFILE_VERSION,
		'id'      => 'mrn-generic-curated',
		'copy'    => array(
			'label'      => __( 'A considered example', 'mrn-active-style-guide' ),
			'heading'    => __( 'Make the next step feel clear', 'mrn-active-style-guide' ),
			'subheading' => __( 'A polished preview, ready for editor-owned content.', 'mrn-active-style-guide' ),
			'body'       => __( '<p>Use this private preview to understand the layout’s hierarchy, media treatment, and calls to action before adding your own content.</p>', 'mrn-active-style-guide' ),
			'cta'        => __( 'Explore the layout', 'mrn-active-style-guide' ),
		),
		'presentation_defaults' => array( 'section_width' => 'wide', 'per_page' => 1, 'show_arrows' => 1, 'show_pagination' => 1, 'autoplay' => 0 ),
		// `generated` is a prompt descriptor, never an instruction to use a client asset.
		// The importer creates a deterministic, text-free abstract SVG for it.
		'assets' => array(
			'feature' => array( 'kind' => 'generated', 'prompt' => 'abstract editorial geometry, soft layered color fields, no words, no logos, no watermark', 'alt' => __( 'Abstract preview artwork', 'mrn-active-style-guide' ) ),
		),
		'layouts' => array(),
	);

	$profile = apply_filters( 'mrn_active_style_guide_layout_preview_profile', $profile, get_stylesheet(), home_url( '/' ) );
	if ( ! is_array( $profile ) || absint( $profile['version'] ?? 0 ) !== MRN_ACTIVE_STYLE_GUIDE_LAYOUT_PREVIEW_PROFILE_VERSION ) {
		return $profile;
	}
	return $profile;
}

/** Check that an attachment is reserved for private preview use. */
function mrn_active_style_guide_is_layout_preview_asset( int $attachment_id ): bool {
	return $attachment_id > 0 && '1' === (string) get_post_meta( $attachment_id, MRN_ACTIVE_STYLE_GUIDE_LAYOUT_PREVIEW_ASSET_META, true );
}

/** Create/find an attachment for a local descriptor or deterministic generated demo. */
function mrn_active_style_guide_get_layout_preview_asset_id( string $asset_name, array $profile ): int {
	$descriptor = $profile['assets'][ $asset_name ] ?? array();
	if ( ! is_array( $descriptor ) ) { return 0; }
	$signature = hash( 'sha256', wp_json_encode( $descriptor ) ?: '' );
	$existing = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'any', 'posts_per_page' => 1, 'meta_key' => MRN_ACTIVE_STYLE_GUIDE_LAYOUT_PREVIEW_ASSET_META, 'meta_value' => $signature, 'fields' => 'ids' ) );
	if ( ! empty( $existing ) ) { return absint( $existing[0] ); }

	$kind = sanitize_key( (string) ( $descriptor['kind'] ?? '' ) );
	$source = '';
	if ( 'local' === $kind && ! empty( $descriptor['path'] ) ) {
		$candidate = wp_normalize_path( (string) $descriptor['path'] );
		$roots = array_filter( array( wp_normalize_path( dirname( __DIR__, 2 ) ), wp_normalize_path( get_stylesheet_directory() ), wp_normalize_path( get_template_directory() ) ) );
		foreach ( $roots as $root ) {
			if ( 0 === strpos( $candidate, trailingslashit( $root ) ) && is_readable( $candidate ) ) { $source = (string) file_get_contents( $candidate ); /* phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- verified local, Stack-owned descriptor path. */ break; }
		}
	}
	if ( '' === $source && 'generated' === $kind ) {
		// No claims, brands, text, or watermarks: generated-demo assets are abstract only.
		$source = '<svg xmlns="http://www.w3.org/2000/svg" width="1600" height="1000" viewBox="0 0 1600 1000"><defs><linearGradient id="a" x1="0" y1="0" x2="1" y2="1"><stop stop-color="#183a4a"/><stop offset="1" stop-color="#c8a45c"/></linearGradient></defs><rect width="1600" height="1000" fill="url(#a)"/><circle cx="1230" cy="230" r="310" fill="#f1e5cd" fill-opacity=".52"/><path d="M0 780C300 490 650 1040 970 650s470-170 630-370v720H0z" fill="#fff" fill-opacity=".22"/></svg>';
	}
	if ( '' === $source || ! function_exists( 'wp_upload_bits' ) ) { return 0; }
	$upload = wp_upload_bits( 'mrn-layout-preview-' . sanitize_file_name( $asset_name ) . '-' . substr( $signature, 0, 12 ) . '.svg', null, $source );
	if ( ! empty( $upload['error'] ) || empty( $upload['file'] ) ) { return 0; }
	$attachment_id = wp_insert_attachment( array( 'post_title' => 'Private Layout Library Preview Asset', 'post_status' => 'private', 'post_mime_type' => 'image/svg+xml', 'post_content' => __( 'Generated/demo asset for private Layout Library fixtures. Not client content.', 'mrn-active-style-guide' ) ), $upload['file'], 0, true );
	if ( is_wp_error( $attachment_id ) ) { return 0; }
	update_post_meta( $attachment_id, MRN_ACTIVE_STYLE_GUIDE_LAYOUT_PREVIEW_ASSET_META, $signature );
	update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( (string) ( $descriptor['alt'] ?? __( 'Abstract preview artwork', 'mrn-active-style-guide' ) ) ) );
	return absint( $attachment_id );
}

/** Keep private preview assets out of ordinary Media Library browsing. */
function mrn_active_style_guide_exclude_layout_preview_assets_from_media_library( WP_Query $query ): void {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list context.
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
	if ( ! is_admin() || ! $query->is_main_query() || 'attachment' !== $query->get( 'post_type' ) || current_user_can( 'manage_options' ) && 'mrn-layout-library' === $page ) { return; }
	$meta_query = (array) $query->get( 'meta_query' );
	$meta_query[] = array( 'key' => MRN_ACTIVE_STYLE_GUIDE_LAYOUT_PREVIEW_ASSET_META, 'compare' => 'NOT EXISTS' );
	$query->set( 'meta_query', $meta_query );
}
add_action( 'pre_get_posts', 'mrn_active_style_guide_exclude_layout_preview_assets_from_media_library' );
